/*
 *  Contract check.
 *
 *  Given an unpacked release of a dependency, looks for every symbol this plugin
 *  relies on (tools/watch/contract.json). A hook we add_action() on that the new
 *  release no longer fires is a break we can prove with grep, hours before a
 *  person could reproduce it -- and without a model guessing from a changelog.
 *
 *      node tools/watch/contract-check.mjs <dir|zip> <dependency>
 *      node tools/watch/contract-check.mjs --list
 *
 *  Exit 0 when every symbol is found, 2 when one is missing, 1 on a usage error.
 *  --json prints the verdict as JSON for the watcher.
 *
 *  What "found" means per kind:
 *    hook      do_action|apply_filters(_ref_array|_deprecated)( 'name'   (any quote, any spacing)
 *    class     class Name  (namespaced: the namespace line and the short class)
 *    function  function name(
 *    constant  define( 'NAME'  or  const NAME
 *    meta      the literal, quoted, anywhere
 *    table     the literal, quoted, anywhere (a table suffix)
 *    js        the member path in the file the contract names, as an assignment
 *  A symbol marked dynamic:true is reported as "unverifiable", never as missing:
 *  a hook built from a variable cannot be grepped for and should not turn the
 *  watch red on its own.
 */
import { readFileSync, readdirSync, statSync, existsSync, mkdtempSync, rmSync, mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join, dirname, extname, relative } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

const HERE = dirname( fileURLToPath( import.meta.url ) );
export const CONTRACT = JSON.parse( readFileSync( join( HERE, 'contract.json' ), 'utf8' ) );

/* --- patterns ------------------------------------------------------------ */

const esc = ( s ) => s.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
const Q = `['"]`;

export function patternFor( symbol ) {
	const v = symbol.value;
	switch ( symbol.kind ) {
		case 'hook':
			return new RegExp( `(?:do_action|apply_filters)(?:_ref_array|_deprecated)?\\(\\s*${ Q }${ esc( v ) }${ Q }` );
		case 'class': {
			const parts = v.split( '\\' );
			const short = parts.pop();
			return new RegExp( `\\b(?:class|interface|trait)\\s+${ esc( short ) }\\b` );
		}
		case 'function':
			return new RegExp( `\\bfunction\\s+${ esc( v ) }\\s*\\(` );
		case 'constant':
			return new RegExp( `(?:define\\(\\s*${ Q }${ esc( v ) }${ Q }|\\bconst\\s+${ esc( v ) }\\b)` );
		case 'meta':
		case 'table':
			return new RegExp( `${ Q }${ esc( v ) }${ Q }` );
		case 'js':
			return new RegExp( `\\b${ esc( v ) }\\s*=` );
		default:
			throw new Error( `unknown symbol kind ${ symbol.kind }` );
	}
}

/* Namespaced classes also need the namespace to still exist. */
function namespacePattern( symbol ) {
	if ( symbol.kind !== 'class' || ! symbol.value.includes( '\\' ) ) return null;
	const ns = symbol.value.split( '\\' ).slice( 0, -1 ).join( '\\\\' );
	return new RegExp( `\\bnamespace\\s+${ ns }\\b` );
}

/* --- the tree ------------------------------------------------------------ */

const SOURCE_EXT = new Set( [ '.php', '.js', '.inc' ] );

function* files( root ) {
	const stack = [ root ];
	while ( stack.length ) {
		const dir = stack.pop();
		for ( const name of readdirSync( dir ) ) {
			if ( name === 'node_modules' || name === 'vendor-prefixed' || name === '.git' ) continue;
			const p = join( dir, name );
			const s = statSync( p );
			if ( s.isDirectory() ) { stack.push( p ); continue; }
			if ( ! SOURCE_EXT.has( extname( name ) ) ) continue;
			if ( name.endsWith( '.min.js' ) ) continue;
			yield p;
		}
	}
}

export function check( root, dep ) {
	const wanted = dep.symbols.map( ( s ) => ( {
		...s,
		re: patternFor( s ),
		ns: namespacePattern( s ),
		found: null,
		nsFound: null,
	} ) );

	let scanned = 0;
	for ( const file of files( root ) ) {
		const rel = relative( root, file ).replace( /\\/g, '/' );
		const pending = wanted.filter( ( w ) => ! w.found || ( w.ns && ! w.nsFound ) );
		if ( ! pending.length ) break;
		let text;
		try { text = readFileSync( file, 'utf8' ); } catch { continue; }
		scanned++;
		for ( const w of pending ) {
			// A js symbol is looked for in the one file the contract names, so
			// that "media.view.Attachment =" in some plugin's bundle cannot stand
			// in for core's own definition.
			if ( w.kind === 'js' && w.file && ! rel.endsWith( w.file ) ) continue;
			if ( ! w.found && w.re.test( text ) ) w.found = rel;
			if ( w.ns && ! w.nsFound && w.ns.test( text ) ) w.nsFound = rel;
		}
	}

	const missing = [];
	const unverifiable = [];
	const found = [];
	for ( const w of wanted ) {
		const ok = w.found && ( ! w.ns || w.nsFound );
		if ( ok ) { found.push( { kind: w.kind, value: w.value, in: w.found } ); continue; }
		if ( w.dynamic ) { unverifiable.push( { kind: w.kind, value: w.value, usedIn: w.usedIn } ); continue; }
		if ( w.optional ) { unverifiable.push( { kind: w.kind, value: w.value, usedIn: w.usedIn, note: w.note } ); continue; }
		missing.push( { kind: w.kind, value: w.value, usedIn: w.usedIn } );
	}
	return { scanned, found, missing, unverifiable };
}

/* --- the command line ------------------------------------------------------ */

const isMain = process.argv[ 1 ] && fileURLToPath( import.meta.url ) === ( await import( 'node:path' ) ).resolve( process.argv[ 1 ] );

if ( isMain ) {
	const argv = process.argv.slice( 2 );
	const JSON_OUT = argv.includes( '--json' );
	const positional = argv.filter( ( a ) => ! a.startsWith( '--' ) );

	if ( argv.includes( '--list' ) ) {
		for ( const [ slug, dep ] of Object.entries( CONTRACT.dependencies ) ) {
			console.log( `${ slug.padEnd( 30 ) } ${ String( dep.symbols.length ).padStart( 3 ) } symbols  ${ dep.source.type }` );
		}
		process.exit( 0 );
	}

	const [ target, slug ] = positional;
	if ( ! target || ! slug || ! CONTRACT.dependencies[ slug ] ) {
		console.error( 'usage: contract-check.mjs <dir|zip> <dependency>   (see --list)' );
		process.exit( 1 );
	}

	/* --- unpack if needed ----------------------------------------------------- */

	function unpack( zip ) {
		const dir = mkdtempSync( join( tmpdir(), 'vgml-contract-' ) );
		if ( process.platform === 'win32' ) {
			execFileSync( 'powershell', [ '-NoProfile', '-Command', `Expand-Archive -Path '${ zip }' -DestinationPath '${ dir }' -Force` ], { stdio: 'pipe' } );
		} else {
			mkdirSync( dir, { recursive: true } );
			execFileSync( 'unzip', [ '-oq', zip, '-d', dir ], { stdio: 'pipe' } );
		}
		return dir;
	}

	if ( ! existsSync( target ) ) {
		console.error( `no such path: ${ target }` );
		process.exit( 1 );
	}

	const isZip = statSync( target ).isFile();
	const root = isZip ? unpack( target ) : target;
	const dep = CONTRACT.dependencies[ slug ];
	const result = { slug, name: dep.name, ...check( root, dep ) };
	if ( isZip ) rmSync( root, { recursive: true, force: true } );

	if ( JSON_OUT ) {
		console.log( JSON.stringify( result, null, 2 ) );
	} else {
		console.log( `\n${ dep.name }: ${ result.scanned } files read` );
		for ( const f of result.found ) console.log( `  ok   ${ f.kind.padEnd( 9 ) } ${ f.value }  (${ f.in })` );
		for ( const u of result.unverifiable ) console.log( `  ?    ${ u.kind.padEnd( 9 ) } ${ u.value }  -- not verifiable by grep${ u.note ? ': ' + u.note : '' }` );
		for ( const m of result.missing ) console.log( `  GONE ${ m.kind.padEnd( 9 ) } ${ m.value }  -- relied on in ${ m.usedIn }` );
		console.log( result.missing.length ? `\n${ result.missing.length } missing\n` : '\ncontract intact\n' );
	}
	process.exit( result.missing.length ? 2 : 0 );

}
