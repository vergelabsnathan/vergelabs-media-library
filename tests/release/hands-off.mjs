/*
 *  Hands off what is not ours, read from the files that ship.
 *
 *      node tests/release/hands-off.mjs
 *
 *  wordpress.org review 2 (2026-10-04/05) would have rejected the plugin
 *  twice more for the same two classes it had already been told about once:
 *  answering a core AJAX action in core's place, and switching off another
 *  plugin's or theme's code. And it asked about a third: core's globals,
 *  core's Settings > Media item and core's media.js. Each was fixed by
 *  removing it; this keeps them removed. It reads the shipped PHP and JS as
 *  text and fails on the shape, wherever it appears:
 *
 *    - an AJAX action that is not the plugin's own (wp_ajax_ / nopriv names
 *      must start with vergeml)
 *    - a dequeue or deregister of a handle that is not vergeml-*
 *    - an unhook (remove_action / remove_filter / remove_all_*) of a callback
 *      that is not the plugin's own -- one reviewed site excepted below
 *    - a write to WordPress's admin globals ($submenu, $menu, $pagenow,
 *      $title, $hook_suffix, $parent_file, $submenu_file)
 *    - JS writing window.adminpage, window.pagenow or window.findPosts, or
 *      patching an Attachment prototype's toggleSelection
 *    - a request outside the HTTP API (Requests::request_multiple, curl_*)
 *    - the filters that existed only for the suites, or that returned SQL
 *
 *  Nothing here reaches a site. Mutation checks, 2026-10-05: re-adding
 *  `add_action( 'wp_ajax_save-attachment-order', ... )`, a
 *  `wp_dequeue_script( 'wpuxss-eml-media-grid-script' )`, `global $submenu;`
 *  and `window.pagenow = 'x'` each turned the matching row red.
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );

const results = [];
function check( what, ok, detail ) {
	results.push( ok );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' }  ${ what }${ ok || ! detail ? '' : `  -- ${ detail }` }` );
}

// What ships: tracked files minus every export-ignore line in .gitattributes.
const ignored = fs.readFileSync( path.join( ROOT, '.gitattributes' ), 'utf8' )
	.split( /\r?\n/ )
	.map( ( l ) => l.trim() )
	.filter( ( l ) => /\sexport-ignore$/.test( l ) )
	.map( ( l ) => l.split( /\s+/ )[ 0 ].replace( /^\//, '' ) );
const shipped = execFileSync( 'git', [ 'ls-files' ], { cwd: ROOT, encoding: 'utf8' } )
	.split( '\n' )
	.filter( Boolean )
	.filter( ( f ) => ! ignored.some( ( i ) => f === i || f.startsWith( i + '/' ) ) );

const php = shipped.filter( ( f ) => f.endsWith( '.php' ) );
const js = shipped.filter( ( f ) => f.endsWith( '.js' ) );

// Comments out, so a docblock that tells the history does not count as the thing it describes.
const strip = ( src ) => src.replace( /\/\*[\s\S]*?\*\//g, ( m ) => m.replace( /[^\n]/g, ' ' ) ).replace( /(^|[^:'"\\])\/\/[^\n]*/g, '$1' ).replace( /^\s*#[^\n]*/gm, '' );

function hits( files, re ) {
	const out = [];
	for ( const rel of files ) {
		const lines = strip( fs.readFileSync( path.join( ROOT, rel ), 'utf8' ) ).split( '\n' );
		lines.forEach( ( line, i ) => {
			for ( const m of line.matchAll( re ) ) {
				out.push( { at: `${ rel }:${ i + 1 }`, m } );
			}
		} );
	}
	return out;
}

console.log( '\nhands off what is not ours\n' );
console.log( `  ${ php.length } PHP and ${ js.length } JS files ship\n` );

// AJAX actions. One reviewed exception: Themify loads its builder over its own tb_load_editor action and prints
// the enqueued scripts into that reply; vergeml_builder_load_tree() enqueues the tree at priority 9 and answers
// nothing, so Themify's handler still runs and still answers.
const REVIEWED_AJAX = { 'core/page-builders.php': [ 'tb_load_editor' ] };
const ajax = hits( php, /add_action\(\s*'wp_ajax_(?:nopriv_)?([^']+)'/g )
	.filter( ( h ) => ! /^vergeml/.test( h.m[ 1 ] ) && ! ( REVIEWED_AJAX[ h.at.split( ':' )[ 0 ] ] || [] ).includes( h.m[ 1 ] ) );
check( 'every AJAX action is the plugin\'s own', 0 === ajax.length, ajax.map( ( h ) => `${ h.at } ${ h.m[ 1 ] }` ).join( ' | ' ) );

const dyn = hits( php, /add_action\(\s*["$](?!')/g ).filter( ( h ) => /wp_ajax/.test( h.m.input ) );
check( 'no AJAX action registered under a computed name', 0 === dyn.length, dyn.map( ( h ) => h.at ).join( ' | ' ) );

// Dequeue and deregister.
const deq = hits( php, /wp_(?:dequeue|deregister)_(?:script|style)\(\s*([^)]*)\)/g ).filter( ( h ) => ! /^'vergeml[-_]/.test( h.m[ 1 ].trim() ) );
check( 'nothing but our own scripts and styles is dequeued or deregistered', 0 === deq.length, deq.map( ( h ) => `${ h.at } ${ h.m[ 1 ] }` ).join( ' | ' ) );

// Unhooking.
const REVIEWED_UNHOOK = {
	// The plugin's screens hide only the plugin's own notices: vergeml_shell_is_own_callback() decides, on the line above.
	'core/admin-shell.php': /remove_action\(\s*\$hook,\s*\$callback\['function'\],\s*\$priority\s*\)/,
};
const unhook = hits( php, /remove_(?:action|filter)\(\s*'[^']+'\s*,\s*([^,)]+)|remove_all_(?:actions|filters)\(\s*([^)]+)\)/g ).filter( ( h ) => {
	if ( undefined !== h.m[ 2 ] ) {
		return true; // remove_all_* in shipped code: never ours to do
	}
	const cb = h.m[ 1 ].trim();
	return ! /^'vergeml_/.test( cb );
} );
const unhookDyn = hits( php, /remove_(?:action|filter)\(\s*\$/g ).filter( ( h ) => ! Object.entries( REVIEWED_UNHOOK ).some( ( [ file, re ] ) => h.at.startsWith( file + ':' ) && re.test( h.m.input ) ) );
check( 'nothing but our own callbacks is unhooked', 0 === unhook.length && 0 === unhookDyn.length, [ ...unhook, ...unhookDyn ].map( ( h ) => `${ h.at } ${ h.m[ 0 ] }` ).join( ' | ' ) );

// Admin globals: read them if you must, never write them. Per function: a global taken and then assigned.
const ADMIN = [ 'submenu', 'menu', 'pagenow', 'title', 'hook_suffix', 'parent_file', 'submenu_file' ];
const globals = [];
for ( const rel of php ) {
	const src = strip( fs.readFileSync( path.join( ROOT, rel ), 'utf8' ) );
	for ( const fn of src.split( /\bfunction\s/ ) ) {
		const took = ( fn.match( /global\s+[^;]+;/g ) || [] ).join( ' ' );
		for ( const g of ADMIN ) {
			const taken = new RegExp( `\\$${ g }\\b` ).test( took );
			const written = new RegExp( `\\$${ g }\\s*(\\[[^\\]]*\\]\\s*)*(=[^=>]|\\.=|\\+=)` ).test( fn );
			if ( taken && written ) {
				globals.push( { at: rel, m: [ '', g ] } );
			}
		}
	}
}
const direct = hits( php, /\$GLOBALS\[\s*'(submenu|menu|pagenow|title|hook_suffix|parent_file|submenu_file)'\s*\]\s*(\[[^\]]*\]\s*)*=[^=]/g );
check( 'no WordPress admin global is written', 0 === globals.length + direct.length, [ ...globals, ...direct ].map( ( h ) => `${ h.at } $${ h.m[ 1 ] }` ).join( ' | ' ) );

// Core screens and scripts, from the JS side.
const jsGlobals = hits( js, /window\.(adminpage|pagenow|findPosts)\s*=[^=]/g );
check( 'no JS writes window.adminpage, window.pagenow or window.findPosts', 0 === jsGlobals.length, jsGlobals.map( ( h ) => `${ h.at } ${ h.m[ 1 ] }` ).join( ' | ' ) );

const proto = hits( js, /Attachment(?:\.Library)?\.prototype\.toggleSelection\s*=[^=]/g );
check( 'no media Attachment prototype has its toggleSelection replaced', 0 === proto.length, proto.map( ( h ) => h.at ).join( ' | ' ) );

const media = hits( php, /wp_dequeue_script\(\s*'media'\s*\)/g );
check( 'core\'s media.js is never taken off a screen', 0 === media.length, media.map( ( h ) => h.at ).join( ' | ' ) );

// The HTTP API.
const http = hits( php, /Requests::request_multiple|\bcurl_(?:init|exec|multi_\w+)\s*\(/g );
check( 'every request goes through the HTTP API', 0 === http.length, http.map( ( h ) => `${ h.at } ${ h.m[ 0 ] }` ).join( ' | ' ) );

// Seams that existed for the suites, and filters that returned SQL.
const GONE = [ 'vergeml_ai_describe_answers', 'vergeml_ai_run_should_nudge', 'vergeml_brief_describe', 'vergeml_folders_walk', 'vergeml_offer_trial_possible', 'vergeml_ai_parallel', 'vergeml_smart_count_branches', 'vergeml_smart_count_exclude' ];
const seams = hits( php, /apply_filters\(\s*'([^']+)'/g ).filter( ( h ) => GONE.includes( h.m[ 1 ] ) );
check( 'none of the removed test seams or SQL-returning filters is back', 0 === seams.length, seams.map( ( h ) => `${ h.at } ${ h.m[ 1 ] }` ).join( ' | ' ) );

const passed = results.filter( Boolean ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
