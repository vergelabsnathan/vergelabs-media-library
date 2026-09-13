/*
 *  Every outbound request names a host somebody chose on purpose.
 *
 *      node tests/security/hosts.mjs           # the suite
 *      node tests/security/hosts.mjs --list    # every call site, for the register
 *
 *  Phase 5.6 of plans/four-yesses.md. Every wp_remote_* call in the shipped
 *  tree -- the plugin's core/ and root, and ../pro/includes when that repo sits
 *  beside this one -- has a row in docs/security-hosts.md saying what decides
 *  the host, and that is one of three kinds: a constant in the plugin, a
 *  define() the site owner put in wp-config.php, or something derived from one
 *  of those (the site's own address included). Nothing else: not a REST
 *  parameter, not an option, not a post, and not a filter -- a filter is a way
 *  in for any co-installed plugin, and the requests carry the licence key.
 *
 *  What goes red:
 *
 *    - a call site without a row. A new wp_remote_get() anywhere in the tree
 *      fails here on the commit that adds it, until somebody writes down what
 *      decides its host.
 *    - a row without a call site. A reason that no longer matches anything has
 *      quietly stopped being checked.
 *    - a kind that is not one of the three.
 *    - a URL expression that reads the request or an option.
 *    - a URL helper whose body applies a filter or reads an option. The
 *      helpers are the functions named in a URL expression or in a row's
 *      "decided by"; each is found in the tree and read to its closing brace.
 *      This is the check that found vergeml_connect_base()'s filter.
 *    - `'sslverify' => false`, anywhere. The two loopbacks to this site's own
 *      wp-cron.php follow core's spawn_cron(): `apply_filters(
 *      'https_local_ssl_verify', false )`, and that form is allowed only
 *      inside a function that names wp-cron.php. Every other site verifies --
 *      `'sslverify' => true`, or nothing said, which WordPress treats as true.
 *
 *  Nothing here reaches a site, a box or a model. It reads PHP as text.
 *
 *  ## Mutation checks
 *
 *  Run on 2026-09-13, each red at the rows named and 13/13 once reverted:
 *
 *    1. core/zz-scratch.php holding `wp_remote_get( $_GET["u"] )`
 *       → "every call site has a row", "no URL expression reads the request"
 *         and the bare-variable row all name core/zz-scratch.php:2.
 *    2. vergeml_connect_base()'s apply_filters() put back
 *       → "no URL helper applies a filter, reads an option or reads the
 *         request" names it. This is the finding the suite was written after.
 *    3. `'sslverify' => true` made `false` at search-meaning.php
 *       → "no site says sslverify => false" and the 17-verify row.
 *    4. the https_local_ssl_verify form put on ai.php's activate call
 *       → "loopbacks … and only those" names vergeml_ai_activate_site().
 *    5. three register rows' kind changed to `filter`, the instrument row deleted
 *       → the kind row names all three; "every call site has a row" names
 *         core/instrument.php:228.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const ROOT = path.resolve( HERE, '..', '..' );
const PRO = path.resolve( ROOT, '..', 'pro' );
const DOC = path.join( ROOT, 'docs', 'security-hosts.md' );

const LIST = process.argv.includes( '--list' );

const results = [];

function check( what, ok, detail ) {
	results.push( { ok, what } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' }  ${ what }${ ok || ! detail ? '' : `  -- ${ detail }` }` );
}


/* ----------------------------------------------------------- the shipped tree */

/**
 *  What ships, and nothing else: tests/ and tools/ reach no site. The pro
 *  repo's includes/ and root, when it is checked out beside this one -- its
 *  three sites are in the register, and a machine without the repo reports
 *  them unchecked rather than pretending.
 */
function phpUnder( dir, rel = '' ) {
	const out = [];
	const full = path.join( dir, rel );
	if ( ! fs.existsSync( full ) ) return out;
	for ( const entry of fs.readdirSync( full, { withFileTypes: true } ) ) {
		const r = rel ? `${ rel }/${ entry.name }` : entry.name;
		if ( entry.isDirectory() ) {
			if ( [ 'tests', 'tools', 'node_modules', 'vendor', '.git', 'docs', 'plans', 'playground', 'research', 'assets', 'dist' ].includes( entry.name ) ) continue;
			out.push( ...phpUnder( dir, r ) );
		} else if ( entry.name.endsWith( '.php' ) ) {
			out.push( r );
		}
	}
	return out;
}

const trees = [ { name: 'plugin', dir: ROOT, prefix: '' } ];
const proPresent = fs.existsSync( path.join( PRO, 'includes' ) );
if ( proPresent ) trees.push( { name: 'pro', dir: PRO, prefix: 'pro/' } );

/** rel (as the register spells it) → source text. */
const src = new Map();
for ( const t of trees ) {
	for ( const rel of phpUnder( t.dir ) ) {
		src.set( t.prefix + rel, fs.readFileSync( path.join( t.dir, rel ), 'utf8' ) );
	}
}


/* ------------------------------------------------------- a string-aware eye */

/** Comments blanked to spaces, same length, so offsets and lines stay honest. */
function blankComments( text ) {
	return text
		.replace( /\/\*[\s\S]*?\*\//g, ( m ) => m.replace( /[^\n]/g, ' ' ) )
		.replace( /(^|[^:'"\\])\/\/[^\n]*/g, ( m, lead ) => lead + ' '.repeat( m.length - lead.length ) )
		.replace( /(^|\s)#[^\n]*/g, ( m, lead ) => lead + ' '.repeat( m.length - lead.length ) );
}

/** From an opening paren, the index of the one that closes it. Strings skipped. */
function closeParen( text, open ) {
	let depth = 0;
	let quote = null;
	for ( let i = open; i < text.length; i++ ) {
		const c = text[ i ];
		if ( quote ) {
			if ( c === '\\' ) i++;
			else if ( c === quote ) quote = null;
			continue;
		}
		if ( c === "'" || c === '"' ) quote = c;
		else if ( c === '(' ) depth++;
		else if ( c === ')' && 0 === --depth ) return i;
	}
	return -1;
}

/** Top-level comma split of an argument list. */
function splitArgs( text ) {
	const parts = [];
	let depth = 0;
	let quote = null;
	let start = 0;
	for ( let i = 0; i < text.length; i++ ) {
		const c = text[ i ];
		if ( quote ) {
			if ( c === '\\' ) i++;
			else if ( c === quote ) quote = null;
			continue;
		}
		if ( c === "'" || c === '"' ) quote = c;
		else if ( c === '(' || c === '[' ) depth++;
		else if ( c === ')' || c === ']' ) depth--;
		else if ( c === ',' && 0 === depth ) {
			parts.push( text.slice( start, i ) );
			start = i + 1;
		}
	}
	parts.push( text.slice( start ) );
	return parts.map( ( p ) => p.trim() );
}

const tidy = ( s ) => s.replace( /\s+/g, ' ' ).trim();

/** The value of `'sslverify' => …` in an options list, balanced, or null when unsaid. */
function sslverifyOf( options ) {
	const at = options.search( /['"]sslverify['"]\s*=>\s*/ );
	if ( at < 0 ) return null;
	const start = at + options.slice( at ).match( /['"]sslverify['"]\s*=>\s*/ )[ 0 ].length;
	let depth = 0;
	let quote = null;
	for ( let i = start; i < options.length; i++ ) {
		const c = options[ i ];
		if ( quote ) {
			if ( c === '\\' ) i++;
			else if ( c === quote ) quote = null;
			continue;
		}
		if ( c === "'" || c === '"' ) quote = c;
		else if ( c === '(' || c === '[' ) depth++;
		else if ( c === ')' || c === ']' ) {
			if ( 0 === depth ) return tidy( options.slice( start, i ) );
			depth--;
		} else if ( ( c === ',' || c === '\n' ) && 0 === depth ) {
			return tidy( options.slice( start, i ) );
		}
	}
	return tidy( options.slice( start ) );
}

/** The function a position sits in: name and body to the closing brace in column one. */
function enclosingFunction( text, at ) {
	const before = text.slice( 0, at );
	const start = before.lastIndexOf( '\nfunction ' );
	if ( start < 0 ) return { name: '(top level)', body: '' };
	const close = text.indexOf( '\n}', at );
	const body = text.slice( start + 1, close < 0 ? text.length : close + 2 );
	const name = ( body.match( /^function\s+(\w+)/ ) || [ '', '?' ] )[ 1 ];
	return { name, body };
}

/** A function's body anywhere in the tree, read to the closing brace in column one. */
function functionBody( name ) {
	const re = new RegExp( `\\nfunction\\s+${ name }\\s*\\(` );
	for ( const [ rel, text ] of src ) {
		const at = text.search( re );
		if ( at < 0 ) continue;
		const close = text.indexOf( '\n}', at );
		return { rel, body: text.slice( at + 1, close < 0 ? text.length : close + 2 ) };
	}
	return null;
}


/* ----------------------------------------------------------- the call sites */

const CALL = /\bwp_(?:safe_)?remote_(?:get|post|head|request)\s*\(/g;

const sites = [];

for ( const [ rel, raw ] of src ) {
	const text = blankComments( raw );
	let m;
	while ( ( m = CALL.exec( text ) ) ) {
		const open = m.index + m[ 0 ].length - 1;
		const close = closeParen( text, open );
		const args = splitArgs( text.slice( open + 1, close ) );
		const line = text.slice( 0, m.index ).split( '\n' ).length;
		const options = args.slice( 1 ).join( ',' );
		sites.push( {
			rel,
			line,
			fn: m[ 0 ].replace( /\s*\($/, '' ),
			url: tidy( args[ 0 ] || '' ),
			sslverify: sslverifyOf( options ),
			within: enclosingFunction( text, m.index ),
		} );
	}
}

sites.sort( ( a, b ) => a.rel.localeCompare( b.rel ) || a.line - b.line );

if ( LIST ) {
	for ( const s of sites ) {
		console.log( `${ s.rel }:${ s.line }  ${ s.fn }  url: ${ s.url }  sslverify: ${ s.sslverify ?? '(default true)' }  in ${ s.within.name }()` );
	}
	process.exit( 0 );
}

console.log( '\nevery outbound request, decided on purpose\n' );

check( `${ sites.length } call sites found across ${ trees.map( ( t ) => t.name ).join( ' + ' ) }`, sites.length >= ( proPresent ? 19 : 16 ), `${ sites.length } found` );

if ( ! proPresent ) {
	console.log( '  note  ../pro is not checked out beside this repo: its rows are not checked here' );
}


/* ------------------------------------------------------------ the register */

check( 'docs/security-hosts.md is committed', fs.existsSync( DOC ), 'the file is missing' );

const doc = fs.existsSync( DOC ) ? fs.readFileSync( DOC, 'utf8' ) : '';

/**
 *  The rows: | where | url | host | kind | decided by |, url in backticks.
 *  Keyed by file and URL expression rather than by line -- a line moves every
 *  time somebody edits above it, an expression moves when the call changes.
 */
const KINDS = new Set( [ 'constant', 'define', 'derived' ] );
const rows = [];

for ( const line of doc.split( '\n' ) ) {
	if ( ! line.startsWith( '| ' ) ) continue;
	const cells = line.split( '|' ).slice( 1, -1 ).map( ( c ) => c.trim() );
	if ( cells.length < 5 || cells[ 0 ] === 'where' || cells[ 0 ].startsWith( '---' ) ) continue;
	const url = cells[ 1 ].match( /^`(.*)`$/ );
	if ( ! url ) continue;
	rows.push( { rel: cells[ 0 ], url: tidy( url[ 1 ] ), host: cells[ 2 ], kind: cells[ 3 ], why: cells[ 4 ] } );
}

check( 'the register has rows', rows.length > 0 );

const rowFor = ( s ) => rows.find( ( r ) => r.rel === s.rel && r.url === s.url );

const unexplained = sites.filter( ( s ) => ! rowFor( s ) );
check(
	'every call site has a row in docs/security-hosts.md',
	0 === unexplained.length,
	unexplained.map( ( s ) => `${ s.rel }:${ s.line } ${ s.url }` ).join( ' | ' )
);

const stale = rows.filter( ( r ) => {
	if ( r.rel.startsWith( 'pro/' ) && ! proPresent ) return false;
	return ! sites.some( ( s ) => s.rel === r.rel && s.url === r.url );
} );
check(
	'every row still matches a call site',
	0 === stale.length,
	stale.map( ( r ) => `${ r.rel } ${ r.url }` ).join( ' | ' )
);

const badKind = rows.filter( ( r ) => ! KINDS.has( r.kind ) );
check(
	'every row\'s kind is constant, define or derived',
	0 === badKind.length,
	badKind.map( ( r ) => `${ r.rel }: ${ r.kind }` ).join( ' | ' )
);


/* ------------------------------------------- where the URL must not come from */

const FROM_REQUEST = /\$_(?:GET|POST|REQUEST|SERVER|COOKIE)\b|->get_param\s*\(|->get_params\s*\(|->get_json_params\s*\(|\bget_option\s*\(|\bget_site_option\s*\(|\bget_post_meta\s*\(|\bget_the_content\s*\(|\bapply_filters\s*\(/;

const fromRequest = sites.filter( ( s ) => FROM_REQUEST.test( s.url ) );
check(
	'no URL expression reads the request, an option, a post or a filter',
	0 === fromRequest.length,
	fromRequest.map( ( s ) => `${ s.rel }:${ s.line } ${ s.url }` ).join( ' | ' )
);

/*
 *  A bare variable as the URL -- `$url`, `$request['url']` -- says nothing by
 *  itself, so its row must name, in backticks with parentheses, the helper that
 *  decides its host; that helper is then read like the others below. The
 *  reading of how the variable was built is the row's author's, as it is for
 *  the eleven hand-read calls in docs/security-db-calls.md.
 */
const bare = sites.filter( ( s ) => /^\$\w+(\[[^\]]*\])?$/.test( s.url ) );
const bareUnnamed = bare.filter( ( s ) => {
	const r = rowFor( s );
	return ! r || ! /`\w+\(\)`/.test( r.why );
} );
check(
	`${ bare.length } sites pass a variable; each row names the helper that decides its host`,
	0 === bareUnnamed.length,
	bareUnnamed.map( ( s ) => `${ s.rel }:${ s.line } ${ s.url }` ).join( ' | ' )
);


/* -------------------------------------------------------- the helpers, read */

/*
 *  Every function a URL expression calls, and every `name()` a row's reason
 *  cites, read to its closing brace: no filter, no option, no request. Core's
 *  own address helpers are not in this tree and are the site's own address by
 *  definition -- derived, in the register's terms. Not transitive: a URL
 *  helper's own body is the thing under test, and reading everything it calls
 *  would flag the licence key's get_option() as if it decided a host.
 */
const CORE_OWN = new Set( [ 'site_url', 'home_url', 'admin_url', 'add_query_arg', 'untrailingslashit', 'trailingslashit', 'rawurlencode', 'sprintf', 'defined', 'is_string', 'wp_parse_url', 'esc_url_raw' ] );

const helperNames = new Set();
for ( const s of sites ) {
	for ( const m of s.url.matchAll( /\b([a-z_][a-z0-9_]*)\s*\(/gi ) ) helperNames.add( m[ 1 ] );
	const r = rowFor( s );
	if ( r ) for ( const m of r.why.matchAll( /`([a-z_][a-z0-9_]*)\(\)`/gi ) ) helperNames.add( m[ 1 ] );
}

const helperFindings = [];
const helperMissing = [];
let helpersRead = 0;

for ( const name of helperNames ) {
	if ( CORE_OWN.has( name ) ) continue;
	const found = functionBody( name );
	if ( ! found ) {
		helperMissing.push( name );
		continue;
	}
	helpersRead++;
	const hit = blankComments( found.body ).match( FROM_REQUEST );
	if ( hit ) helperFindings.push( `${ name }() in ${ found.rel }: ${ hit[ 0 ].trim() }` );
}

check(
	`every URL helper is in the tree (${ helpersRead } read)`,
	0 === helperMissing.length,
	`not found: ${ helperMissing.join( ', ' ) }`
);

check(
	'no URL helper applies a filter, reads an option or reads the request',
	0 === helperFindings.length,
	helperFindings.join( ' | ' )
);


/* ------------------------------------------------------------- sslverify */

const literalFalse = sites.filter( ( s ) => s.sslverify && /^false$/i.test( s.sslverify ) );
check(
	'no site says sslverify => false',
	0 === literalFalse.length,
	literalFalse.map( ( s ) => `${ s.rel }:${ s.line }` ).join( ' | ' )
);

const LOOPBACK_RULE = /^apply_filters\(\s*'https_local_ssl_verify'\s*,\s*false\s*\)$/;

const loopbacks = sites.filter( ( s ) => s.sslverify && LOOPBACK_RULE.test( s.sslverify ) );
const loopbackWrong = loopbacks.filter( ( s ) => ! s.within.body.includes( 'wp-cron.php' ) );
check(
	`${ loopbacks.length } loopbacks to this site's wp-cron.php use core's https_local_ssl_verify rule, and only those`,
	0 === loopbackWrong.length,
	loopbackWrong.map( ( s ) => `${ s.rel }:${ s.line } in ${ s.within.name }()` ).join( ' | ' )
);

const other = sites.filter( ( s ) => ! loopbacks.includes( s ) );
const notVerified = other.filter( ( s ) => null !== s.sslverify && ! /^true$/i.test( s.sslverify ) );
check(
	`the other ${ other.length } sites verify TLS: sslverify => true, or unsaid (WordPress's default is true)`,
	0 === notVerified.length,
	notVerified.map( ( s ) => `${ s.rel }:${ s.line } sslverify => ${ s.sslverify }` ).join( ' | ' )
);

const saidTrue = other.filter( ( s ) => s.sslverify && /^true$/i.test( s.sslverify ) ).length;
console.log( `  note  ${ saidTrue } say sslverify => true, ${ other.length - saidTrue } leave it to the default` );


/* ------------------------------------------------------------ the summary */

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
