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
 *    - a table of the plugin's own, or an IN list, interpolated into SQL (round 4)
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

// SQL: no table of the plugin's own and no IN list is interpolated into a statement (wordpress.org review 2,
// round 4: "you cannot add variables like $wpdb->vergeml_ai_index directly to the SQL query"). Tables go in as %i,
// IN lists as the inline implode( array_fill() ) WPCS reads.
const tables = hits( php, /\{\$wpdb->vergeml_\w+\}|\$wpdb->prefix\s*\}|"[^"]*\{\$(?:t|table|tbl)\}[^"]*"/g );
check( 'no table of the plugin\'s own is interpolated into SQL', 0 === tables.length, tables.map( ( h ) => `${ h.at } ${ h.m[ 0 ].slice( 0, 60 ) }` ).join( ' | ' ) );
const inLists = hits( php, /IN\s*\(\s*\$\w+\s*\)|IN\s*\(\s*\{\$\w+\}\s*\)/g );
check( 'no IN list is interpolated into SQL', 0 === inLists.length, inLists.map( ( h ) => `${ h.at } ${ h.m[ 0 ] }` ).join( ' | ' ) );

// Inline code (wordpress.org review 2, round 5: "the helper outputs the caller-supplied JavaScript string directly").
// Scripts are files in js/ with their data through wp_localize_script; an inline style is escaped at output.
const inlineJs = hits( php, /wp_add_inline_script\s*\(/g );
check( 'no script is added inline; every script is a file', 0 === inlineJs.length, inlineJs.map( ( h ) => h.at ).join( ' | ' ) );
const inlineCss = hits( php, /wp_add_inline_style\s*\(\s*'[^']+'\s*,(?!\s*wp_strip_all_tags\s*\()/g );
check( 'every inline style is escaped at output (wp_strip_all_tags)', 0 === inlineCss.length, inlineCss.map( ( h ) => h.at ).join( ' | ' ) );

// Writes to WordPress's own posts and postmeta go through its API (round 5 pass: health rewrote meta by meta_id and
// parents with $wpdb->update). uninstall.php removes the plugin's own rows and is read on its own.
const coreWrites = hits( php.filter( ( f ) => 'uninstall.php' !== f ), /\$wpdb->(?:update|insert|replace|delete)\(\s*\$wpdb->(?:posts|postmeta)\b|(?:UPDATE|INSERT INTO)\s+\{\$wpdb->(?:posts|postmeta)\}/g );
check( 'no direct write to posts or postmeta outside uninstall', 0 === coreWrites.length, coreWrites.map( ( h ) => h.at ).join( ' | ' ) );
const loop = hits( php, /http:\/\/127\.0\.0\.1|http:\/\/localhost/g );
check( 'no plain-http loopback address in shipped code', 0 === loop.length, loop.map( ( h ) => h.at ).join( ' | ' ) );

// Seams that existed for the suites, and filters that returned SQL.
const GONE = [ 'vergeml_journey_facts', 'vergeml_ai_run_budget', 'vergeml_ai_describe_answers', 'vergeml_ai_run_should_nudge', 'vergeml_brief_describe', 'vergeml_folders_walk', 'vergeml_offer_trial_possible', 'vergeml_ai_parallel', 'vergeml_smart_count_branches', 'vergeml_smart_count_exclude', 'vergeml_autofile_vector' ];
const seams = hits( php, /apply_filters\(\s*'([^']+)'/g ).filter( ( h ) => GONE.includes( h.m[ 1 ] ) );
// Round 8 (2026-10-20): every name the plugin makes carries "vergeml", and a route says its capability where it is registered.
const shortPrefix = hits( [ ...php, ...js ], /\bvgml(?:_\w*|[A-Z]\w*)/g ).filter( ( h ) => ! /'vgml_'|vgml_' \)|'\/\^vgml_\/'/.test( h.m.input ) );
check( 'no vgml_ name or vgml* JS global in shipped PHP or JS (only the migration reads the old key)', 0 === shortPrefix.length, shortPrefix.slice( 0, 8 ).map( ( h ) => `${ h.at } ${ h.m[ 0 ] }` ).join( ' | ' ) );
const wrappedCaps = hits( php, /'permission_callback'\s*=>\s*'[^']+'/g );
check( 'every route states its capability where it is registered (no permission_callback given by name)', 0 === wrappedCaps.length, wrappedCaps.map( ( h ) => `${ h.at } ${ h.m[ 0 ] }` ).join( ' | ' ) );
// The folders are vergeml_folder; media_category is only ever read as the old or another plugin's key.
const mc = hits( [ ...php, ...js ], /media_category/g ).filter( ( h ) => ! /'media_category' === \$(key|old)|atts\['media_category'\]|'taxonomy' => 'media_category'|'vergeml_folder', 'media_category'/.test( h.m.input ) );
check( 'media_category is read only by the migration, the EML import, the old gallery alias and the wipes keep-list', 0 === mc.length, mc.slice( 0, 8 ).map( ( h ) => h.at ).join( ' | ' ) );
// Round 9 (2026-10-28): a route that takes a post_type checks the caller's capability for that type, and counts stay behind the check.
const treeSrc = strip( fs.readFileSync( path.join( ROOT, 'core/rest-tree.php' ), 'utf8' ) );
check( '/tree checks the capability of the post type it was asked about', /'\/tree'[\s\S]{0,1200}get_post_type_object\( \$type \)[\s\S]{0,80}->cap->edit_posts/.test( treeSrc ) );
check( 'the tree and assign counts both go through vergeml_counts_post_type', 4 === ( treeSrc.match( /vergeml_counts_post_type\(/g ) || [] ).length && /->cap->edit_posts \) \? \$post_type : ''/.test( treeSrc ) );
const countCalls = hits( php, /vergeml_(?:folder_counts|folder_unfiled_count)\(/g ).filter( ( h ) => ! /core[\\/](?:rest-tree|post-folders)\.php/.test( h.at ) );
check( 'post-type counts are read only by rest-tree.php', 0 === countCalls.length, countCalls.map( ( h ) => h.at ).join( ' | ' ) );
const postFolders = strip( fs.readFileSync( path.join( ROOT, 'core/post-folders.php' ), 'utf8' ) );
check( 'post-type counts are limited for a user who cannot edit others\' posts', 2 === ( postFolders.match( /->cap->edit_others_posts/g ) || [] ).length && 2 === ( postFolders.match( /%d = 0 OR p\.post_status = 'publish'/g ) || [] ).length );
// Round 9 review: every door that takes a folder id refuses somebody else's private folder; reads ask about the taxonomy; the unfinished renamer stays out.
for ( const [ rel, min ] of [ [ 'core/folder-tools.php', 2 ], [ 'core/rest-tree.php', 1 ], [ 'core/bulk-terms.php', 1 ], [ 'core/media-list.php', 1 ] ] ) {
	const n = ( strip( fs.readFileSync( path.join( ROOT, rel ), 'utf8' ) ).match( /vergeml_private_is_hidden\(/g ) || [] ).length;
	check( `${ rel } refuses a hidden private folder`, n >= min, String( n ) );
}
check( '/tree asks about the requested taxonomy', /'\/tree'[\s\S]{0,1200}\$tax->cap->assign_terms/.test( treeSrc ) );
// Round 9, sixth pass: the doors next to /tree.
const read = ( rel ) => strip( fs.readFileSync( path.join( ROOT, rel ), 'utf8' ) );
const privateSrc = read( 'core/private-folders.php' );
check( '/assign gives file counts only to someone who sees the files', /\$sees_files = current_user_can\( 'upload_files' \)/.test( treeSrc ) && /\$sees_files \|\| vergeml_counts_post_type\(/.test( treeSrc ) && /'unassigned' => \$sees_files \?/.test( treeSrc ) );
check( '/state does not answer with a hidden folder', /\$selected > 0 && \( ! get_term\( \$selected, \$taxonomy \) instanceof WP_Term \|\| vergeml_private_is_hidden\( \$selected \)/.test( treeSrc ) );
const medialistSrc = read( 'core/medialist.php' );
check( "core's shortcode preview lists nothing for someone without upload_files", /doing_action\( 'wp_ajax_parse-media-shortcode' \)[\s\S]{0,120}! current_user_can\( 'upload_files' \)/.test( medialistSrc ) && /\$preview && 'term_id' === \$field/.test( medialistSrc ) );
check( "get_the_terms is filtered, because core's object cache is shared", /add_filter\( 'get_the_terms', 'vergeml_private_filter_object_terms'/.test( privateSrc ) );
check( "core's object cache does not persist between users on a site with private folders", /wp_cache_add_non_persistent_groups\( \$taxonomy \. '_relationships' \)/.test( privateSrc ) && /add_action\( 'wp_loaded', 'vergeml_private_cache_per_request' \)/.test( privateSrc ) && ! /all_with_object_id/.test( privateSrc ) );
check( 'that stays one-way, and the wipe removes its flag', /! get_option\( 'vergeml_private_used' \)[\s\S]{0,120}! vergeml_private_map\(\)/.test( privateSrc ) && /'vergeml_private_used'/.test( read( 'core/options-pages.php' ) ) );
check( 'an undo asks for the counts of the list it came from', /data: forThisList\( \{ taxonomy: payload\.taxonomy, batch: payload\.batch \} \)/.test( fs.readFileSync( path.join( ROOT, 'js/vergeml-tree.js' ), 'utf8' ) ) && /\$sub->set_param\( 'post_type', \$for_type \)/.test( treeSrc ) );
check( "core's delete finds a file's private folders, during the delete and only by id", /! empty\( \$GLOBALS\['vergeml_private_deleting'\] \) && \( ! empty\( \$args\['include'\] \) \|\| \( isset\( \$args\['fields'\] \) && 'ids' === \$args\['fields'\] \) \)/.test( privateSrc ) && /add_action\( 'delete_attachment', 'vergeml_private_deleting_starts' \)/.test( privateSrc ) && /add_action\( 'deleted_post', 'vergeml_private_deleting_ends' \)/.test( privateSrc ) );
check( "core's list column drops a taxonomy the user may not assign", /add_filter\( 'manage_taxonomies_for_attachment_columns', 'vergeml_assignable_media_columns' \)/.test( read( 'core/taxonomies.php' ) ) && /! current_user_can\( \$object->cap->assign_terms \)/.test( read( 'core/taxonomies.php' ) ) );
const assignBatch = ( treeSrc.match( /'unassigned' => current_user_can\( 'upload_files' \) \?/g ) || [] ).length;
check( 'the /assign batch answer asks upload_files too', 1 === assignBatch, String( assignBatch ) );
const taxSrc = read( 'core/taxonomies.php' );
check( 'an empty taxonomy list never reaches get_terms', /return \$names \? get_terms\(/.test( taxSrc ) && 2 === ( taxSrc.match( /\$terms = vergeml_assignable_media_terms\(\)/g ) || [] ).length && /\$terms = vergeml_assignable_media_terms\(\)/.test( read( 'vergelabs-media-library.php' ) ) );
check( 'attachment details drop a taxonomy the user may not assign', /! current_user_can\( \$t\['cap'\]->assign_terms \)[\s\S]{0,60}unset\( \$form_fields\[ \$taxonomy \] \)/.test( taxSrc ) );
check( 'media search skips a hidden folder name', /vergeml_search_not_hidden\(\)\s*\. " LEFT JOIN/.test( read( 'core/search.php' ) ) );
check( "core's term route is refused without assign_terms", /'vergeml_' \)[^\n]*! current_user_can\( \$object->cap->assign_terms \)/.test( privateSrc ) && ! /! is_user_logged_in\(\)/.test( privateSrc ) );
check( 'a hidden folder is not a parent', /wp_update_term_parent/.test( privateSrc ) && /pre_insert_term/.test( privateSrc ) && /get_param\( 'parent' \)/.test( privateSrc ) );
check( 'limited counts also look at read_private_posts', 2 === ( postFolders.match( /->cap->read_private_posts/g ) || [] ).length );
for ( const rel of [ 'core/bulk-terms.php', 'core/taxonomies.php', 'core/media-list.php', 'vergelabs-media-library.php' ] ) {
	check( `${ rel } lists only taxonomies the user may assign`, ( read( rel ).match( /vergeml_assignable_media_taxonomies\(/g ) || [] ).length >= ( 'core/taxonomies.php' === rel ? 3 : 1 ) );
}
const whySrc = read( 'core/librarian.php' );
check( 'the why reader returns no hidden folder id', 3 === ( whySrc.match( /in_array\( \(int\) \$row\['(?:term_id|runner_up|nearest)'\], \$hidden, true \)/g ) || [] ).length );
const renamer = hits( php, /VERGEML_FILE_RENAME|vergeml_file_rename\b/g );
check( 'the unfinished file renamer is not in the build', 0 === renamer.length, renamer.map( ( h ) => h.at ).join( ' | ' ) );
const routes = hits( php, /register_rest_route\s*\(/g ).length;
const callbacks = php.reduce( ( n, rel ) => n + ( strip( fs.readFileSync( path.join( ROOT, rel ), 'utf8' ) ).match( /'permission_callback'\s*=>/g ) || [] ).length, 0 );
check( `every REST endpoint has a permission_callback (${ routes } routes, ${ callbacks } callbacks)`, callbacks >= routes );

check( 'none of the removed test seams or SQL-returning filters is back', 0 === seams.length, seams.map( ( h ) => `${ h.at } ${ h.m[ 1 ] }` ).join( ' | ' ) );

const passed = results.filter( Boolean ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
