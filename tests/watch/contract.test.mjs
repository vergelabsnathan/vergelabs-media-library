/*
 *  The contract check must be able to fail.
 *
 *  Builds a tiny fake plugin carrying the three kinds of symbol the contract
 *  uses most, checks that all three are found, then deletes one and checks the
 *  run exits 2 naming it. A regex that matches nothing reports every symbol
 *  present, which is the same failure shape as the suites that could not fail.
 *
 *      node tests/watch/contract.test.mjs
 */
import { mkdtempSync, writeFileSync, mkdirSync, rmSync, readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { join } from 'node:path';
import { tmpdir } from 'node:os';

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( ok );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? `  -- ${ detail }` : '' }` );
};

const root = mkdtempSync( join( tmpdir(), 'vgml-contract-test-' ) );
const plugin = join( root, 'polylang' );
mkdirSync( join( plugin, 'include' ), { recursive: true } );

// The real contract's polylang entry: two hooks and two taxonomy names.
const full = `<?php
class PLL_Translated_Post {
	public function create_media_translation( $post_id, $lang ) {
		do_action( 'pll_translate_media', $post_id, $tr_id, $lang->slug );
	}
	public function taxonomies() {
		$taxonomies = (array) apply_filters( 'pll_get_taxonomies', $taxonomies, false );
		register_taxonomy( 'language', 'post' );
		register_taxonomy( 'post_translations', 'post' );
	}
}
`;
writeFileSync( join( plugin, 'include', 'translated-post.php' ), full );

const run = () => spawnSync( process.execPath, [ 'tools/watch/contract-check.mjs', plugin, 'polylang', '--json' ], { encoding: 'utf8' } );

console.log( '\nall symbols present' );
let r = run();
let out = JSON.parse( r.stdout || '{}' );
check( 'exits 0', r.status === 0, `status ${ r.status } ${ r.stderr.slice( 0, 120 ) }` );
check( 'finds every symbol', out.missing?.length === 0 && out.found?.length === 4, `found ${ out.found?.length }, missing ${ out.missing?.length }` );

console.log( '\none hook removed' );
writeFileSync( join( plugin, 'include', 'translated-post.php' ), full.replace( "do_action( 'pll_translate_media', $post_id, $tr_id, $lang->slug );", '// gone' ) );
r = run();
out = JSON.parse( r.stdout || '{}' );
check( 'exits 2', r.status === 2, `status ${ r.status }` );
check( 'names the missing hook and where we rely on it',
	out.missing?.length === 1 && out.missing[ 0 ].value === 'pll_translate_media' && /multilingual\.php/.test( out.missing[ 0 ].usedIn ),
	JSON.stringify( out.missing ) );

console.log( '\nthe contract file itself' );
const contract = JSON.parse( readFileSync( 'tools/watch/contract.json', 'utf8' ) );
const deps = Object.entries( contract.dependencies );
check( 'every dependency has a source type', deps.every( ( [ , d ] ) => d.source && d.source.type ) );
check( 'every symbol has a kind, a value and a usedIn', deps.every( ( [ , d ] ) => d.symbols.every( ( s ) => s.kind && s.value && s.usedIn ) ) );
check( 'no symbol is listed twice within a dependency', deps.every( ( [ , d ] ) => new Set( d.symbols.map( ( s ) => s.kind + ':' + s.value ) ).size === d.symbols.length ) );

rmSync( root, { recursive: true, force: true } );

const bad = results.filter( ( x ) => ! x ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
