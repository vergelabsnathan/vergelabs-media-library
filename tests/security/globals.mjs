/*
 *  Every global helper a script calls is one a script defines.
 *
 *      node tests/security/globals.mjs
 *
 *  On 2026-09-10 the Phase 5.4 escaping scan found that js/eml-admin.js defines
 *  window.vergemlConfirmDialog, vergemlAlertDialog, vergemlFullscreenSpinnerStart
 *  and vergemlFullscreenSpinnerStop -- and that all sixteen call sites spelled
 *  them without the vergeml prefix. Each threw a ReferenceError. It failed
 *  closed: preventDefault() ran first and the handler then aborted, so Complete
 *  Cleanup, Restore default MIME types, Apply settings to the network and six
 *  taxonomy dialogs did nothing at all, silently, for however long that rename
 *  had been in the tree.
 *
 *  This reads every shipped script, collects the eml- and vergeml- names each one
 *  defines on window (or as a bare function/var), and fails on any bare call to
 *  such a name that nothing defines. A property call -- media.view.emlGrid() --
 *  is a different namespace and is left alone; those are Backbone views, and
 *  they resolve at run time through wp.media.
 *
 *  Nothing here reaches a site. It reads JavaScript as text.
 *
 *  Mutation check run on 2026-09-11: `vergemlConfirmDialog(` changed back to
 *  `emlConfirmDialog(` at js/eml-options.js:15 → "every bare eml- or vergeml-
 *  call names a defined global" FAILs, naming that line.
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const ROOT = path.resolve( HERE, '..', '..' );

const results = [];

function check( what, ok, detail ) {
	results.push( { ok, what } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' }  ${ what }${ ok || ! detail ? '' : `  -- ${ detail }` }` );
}

console.log( '\nglobal helpers, called and defined\n' );

const files = execFileSync( 'git', [ 'ls-files', 'js' ], { cwd: ROOT } ).toString().split( '\n' ).filter( ( f ) => f.endsWith( '.js' ) );

/** Comments and strings blanked, so a name in a docblock is not a definition. */
function blank( src ) {
	let out = '';
	let i = 0;
	while ( i < src.length ) {
		const c = src[ i ];
		const two = src.slice( i, i + 2 );
		if ( c === "'" || c === '"' || c === '`' ) {
			let j = i + 1;
			while ( j < src.length ) {
				if ( src[ j ] === '\\' ) { j += 2; continue; }
				if ( src[ j ] === c ) { j++; break; }
				j++;
			}
			out += c + ' '.repeat( Math.max( 0, j - i - 2 ) ) + c;
			i = j;
			continue;
		}
		if ( two === '/*' ) {
			const end = src.indexOf( '*/', i + 2 );
			const stop = end === -1 ? src.length : end + 2;
			out += src.slice( i, stop ).replace( /[^\n]/g, ' ' );
			i = stop;
			continue;
		}
		if ( two === '//' ) {
			const end = src.indexOf( '\n', i );
			const stop = end === -1 ? src.length : end;
			out += ' '.repeat( stop - i );
			i = stop;
			continue;
		}
		out += c;
		i++;
	}
	return out;
}

const defined = new Set();
const calls = [];

for ( const rel of files ) {

	const text = blank( fs.readFileSync( path.join( ROOT, rel ), 'utf8' ) );

	for ( const m of text.matchAll( /(?:window\.|\bvar\s+|\blet\s+|\bconst\s+|\bfunction\s+)((?:eml|vergeml)[A-Za-z0-9_]*)\s*(?:=|\()/g ) ) {
		defined.add( m[ 1 ] );
	}

	/* A bare call: not preceded by `.`, so media.view.emlGrid() is not counted. */
	for ( const m of text.matchAll( /(^|[^.\w$])((?:eml|vergeml)[A-Z][A-Za-z0-9_]*)\s*\(/g ) ) {
		calls.push( { name: m[ 2 ], at: `${ rel }:${ text.slice( 0, m.index ).split( '\n' ).length }` } );
	}
}

const missing = calls.filter( ( c ) => ! defined.has( c.name ) );

check( `the scripts define ${ defined.size } eml- or vergeml- globals`, defined.size >= 10, `found ${ defined.size }` );
check( `and make ${ calls.length } bare calls to such names`, calls.length >= 16, `found ${ calls.length }` );

check(
	'every bare eml- or vergeml- call names a defined global',
	0 === missing.length,
	missing.map( ( c ) => `${ c.name } at ${ c.at }` ).join( ', ' )
);

/*
 *  The four that were broken, named, so the row reads as what it protects and
 *  not as an abstract invariant.
 */
for ( const name of [ 'vergemlConfirmDialog', 'vergemlAlertDialog', 'vergemlFullscreenSpinnerStart', 'vergemlFullscreenSpinnerStop' ] ) {
	const n = calls.filter( ( c ) => c.name === name ).length;
	check( `${ name }() is defined and called (${ n } site${ n === 1 ? '' : 's' })`, defined.has( name ) && n > 0, defined.has( name ) ? 'no callers' : 'not defined' );
}

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
