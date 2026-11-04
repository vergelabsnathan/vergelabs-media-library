<?php
/**
 *  Who may call which route: every endpoint in vergeml/v1, asked as an author,
 *  an editor and an administrator.
 *
 *      node tools/verify.mjs route-caps
 *
 *  wordpress.org review 2 (2026-10-03): /ai-run POST started a library-wide
 *  run behind manage_categories. Every route that writes across the library,
 *  runs, applies, imports or undoes, or calls the AI service, now needs
 *  manage_options. This asks each route's own permission_callback -- the
 *  handlers never run, so nothing is written or sent -- and compares what
 *  each role reaches with the list below, so a route that drifts either way,
 *  or a new one, fails here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['vergeml_ok']  = 0;
$GLOBALS['vergeml_bad'] = 0;

function vergeml_t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['vergeml_ok']++ : $GLOBALS['vergeml_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, '' !== $detail ? '  -- ' . $detail : '' );
}

echo "\nwho may call which route\n\n";

wp_set_current_user( 1 );

$term = wp_insert_term( 'vgml-caps-' . wp_rand(), 'vergeml_folder' );
$term = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
$att  = wp_insert_attachment( array( 'post_title' => 'vgml caps', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'post_author' => 1 ) );

$users = array();
foreach ( array( 'author', 'editor', 'administrator' ) as $role ) {
	$users[ $role ] = wp_insert_user( array( 'user_login' => 'vergeml_caps_' . $role . '_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => $role ) );
}

$routes = rest_get_server()->get_routes( 'vergeml/v1' );

function vergeml_reach( $routes, $att, $term ) {

	$reach = array();

	foreach ( $routes as $route => $handlers ) {

		if ( '/vergeml/v1' === $route ) {
			continue;
		}

		foreach ( $handlers as $handler ) {

			foreach ( array_keys( $handler['methods'] ) as $method ) {

				if ( in_array( $method, array( 'PUT', 'PATCH' ), true ) ) {
					continue; // the same handler as its POST
				}

				$path = preg_replace( '/\(\?P<id>[^)]+\)/', (string) $att, $route );
				$req  = new WP_REST_Request( $method, $path );

				$req->set_url_params( array( 'id' => $att ) );
				$req->set_param( 'taxonomy', 'vergeml_folder' );
				$req->set_param( 'action', false !== strpos( $route, '/folder' ) && false === strpos( $route, 'folders' ) ? 'rename' : 'accept' );
				$req->set_param( 'id', false !== strpos( $route, 'folder' ) ? $term : $att );
				$req->set_param( 'term_id', $term );
				$req->set_param( 'attachment_id', $att );

				$ok = isset( $handler['permission_callback'] ) ? call_user_func( $handler['permission_callback'], $req ) : true;

				if ( true === $ok ) {
					$reach[] = $method . ' ' . preg_replace( '#^/vergeml/v1#', '', $route );
				}
			}
		}
	}

	sort( $reach );
	return $reach;
}

/*
 *  What a role below administrator still reaches: reading the tree, filing
 *  pictures they may edit, and changing one folder or one file at a time.
 */
$expect = array(
	'author' => array(
		'GET /ai-alt',
		'GET /folders/version',
		'GET /gallery-folders',
		'GET /rename',
		'GET /state',
		'GET /tree',
		'POST /assign',
		'POST /state',
	),
	'editor' => array(
		'GET /ai-alt',
		'GET /folders/version',
		'GET /gallery-folders',
		'GET /librarian-why/(?P<id>\d+)',
		'GET /rename',
		'GET /state',
		'GET /tree',
		'POST /assign',
		'POST /file/(?P<id>\d+)',
		'POST /folder',
		'POST /state',
	),
);
sort( $expect['author'] );
sort( $expect['editor'] );

foreach ( array( 'author', 'editor' ) as $role ) {

	wp_set_current_user( $users[ $role ] );
	$got = vergeml_reach( $routes, $att, $term );

	$extra   = array_diff( $got, $expect[ $role ] );
	$missing = array_diff( $expect[ $role ], $got );

	vergeml_t( "an {$role} reaches exactly the routes listed", ! $extra && ! $missing,
		( $extra ? 'EXTRA ' . implode( ', ', $extra ) . ' ' : '' ) . ( $missing ? 'MISSING ' . implode( ', ', $missing ) : '' ) );
}

wp_set_current_user( $users['editor'] );
$editor = vergeml_reach( $routes, $att, $term );

foreach ( array( 'POST /ai-run', 'GET /ai-run', 'POST /ai-index', 'POST /guide/apply', 'POST /import', 'POST /health-scan', 'POST /smart-scan', 'GET /search-meaning' ) as $named ) {
	vergeml_t( "an editor is refused {$named}", ! in_array( $named, $editor, true ) );
}

wp_set_current_user( $users['administrator'] );
$admin = vergeml_reach( $routes, $att, $term );

foreach ( array( 'POST /ai-run', 'GET /ai-run', 'POST /ai-index', 'POST /guide/apply', 'POST /import', 'POST /health-scan', 'POST /smart-scan' ) as $named ) {
	vergeml_t( "an administrator may {$named}", in_array( $named, $admin, true ) );
}

$all = count( vergeml_reach( $routes, $att, $term ) );
vergeml_t( 'an administrator reaches every route the editor reaches', ! array_diff( $editor, $admin ) );

printf( "\n  (%d endpoints asked; administrator %d, editor %d)\n", count( $routes ), $all, count( $editor ) );

/* --- the form handlers ------------------------------------------------------- */

$journey = file_get_contents( plugin_dir_path( VERGEML_FILE ) . 'core/journey.php' );
$rename  = file_get_contents( plugin_dir_path( VERGEML_FILE ) . 'core/rename.php' );

vergeml_t( 'Write the alt text (admin-post) needs manage_options',
	1 === preg_match( "/function vergeml_journey_do_alt\(\)\s*\{[^}]*?current_user_can\( 'manage_options' \)/s", $journey ) );
vergeml_t( 'Rename from descriptions (admin-post) needs manage_options',
	1 === preg_match( "/function vergeml_journey_do_rename\(\)\s*\{[^}]*?current_user_can\( 'manage_options' \)/s", $journey ) );
vergeml_t( 'the rename undo (admin-post) needs manage_options',
	1 === preg_match( "/function vergeml_rename_handle_undo\(\)\s*\{[^}]*?current_user_can\( 'manage_options' \)/s", $rename ) );

/* --- wordpress.org round 9: counts for a post type follow that type's capabilities ---- */

register_post_type( 'vergeml_caps_secret', array( 'public' => false, 'capability_type' => 'vergeml_secret', 'map_meta_cap' => true ) );
register_taxonomy_for_object_type( 'vergeml_folder', 'vergeml_caps_secret' );
register_taxonomy_for_object_type( 'vergeml_folder', 'post' );

$planted = array();
foreach ( array(
	array( 'vergeml_caps_secret', 'draft', 1 ),
	array( 'vergeml_caps_secret', 'private', 1 ),
	array( 'post', 'publish', 1 ),
	array( 'post', 'private', 1 ),
	array( 'post', 'draft', 1 ),
	array( 'post', 'draft', $users['author'] ),
) as $p ) {
	$id = wp_insert_post( array( 'post_type' => $p[0], 'post_status' => $p[1], 'post_author' => $p[2], 'post_title' => 'vgml caps' ) );
	wp_set_object_terms( $id, array( $term ), 'vergeml_folder' );
	$planted[] = $id;
}

function vergeml_tree_gate( $routes, $type ) {
	$handler = $routes['/vergeml/v1/tree'][0];
	$req     = new WP_REST_Request( 'GET', '/vergeml/v1/tree' );
	$req->set_param( 'taxonomy', 'vergeml_folder' );
	if ( '' !== $type ) {
		$req->set_param( 'post_type', $type );
	}
	return true === call_user_func( $handler['permission_callback'], $req );
}

function vergeml_tree_answer( $type ) {
	$req = new WP_REST_Request( 'GET', '/vergeml/v1/tree' );
	$req->set_param( 'taxonomy', 'vergeml_folder' );
	$req->set_param( 'post_type', $type );
	return rest_ensure_response( vergeml_rest_tree( $req ) )->get_data();
}

function vergeml_stored_count( $term ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT count FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $term ) );
}

wp_set_current_user( 1 );
$granted = wp_get_current_user();
$granted->add_cap( 'edit_vergeml_secrets' );
vergeml_t( 'an administrator holding the capability may ask /tree about the restricted type', vergeml_tree_gate( $routes, 'vergeml_caps_secret' ) );
vergeml_t( 'an administrator counts all its posts', 2 === vergeml_folder_counts( 'vergeml_folder', 'vergeml_caps_secret' )[ $term ] );
vergeml_t( 'an administrator counts every post, cached for nobody else', 4 === vergeml_folder_counts( 'vergeml_folder', 'post' )[ $term ] );

foreach ( array( 'author', 'editor' ) as $role ) {
	wp_set_current_user( $users[ $role ] );
	vergeml_t( "an {$role} is refused /tree for a type they cannot edit", ! vergeml_tree_gate( $routes, 'vergeml_caps_secret' ) );
	vergeml_t( "an {$role} still reaches /tree for attachments", vergeml_tree_gate( $routes, '' ) && vergeml_tree_gate( $routes, 'attachment' ) );
	vergeml_t( "an {$role} still reaches /tree for posts", vergeml_tree_gate( $routes, 'post' ) );
	$answer = vergeml_tree_answer( 'vergeml_caps_secret' );
	vergeml_t( "the tree handler called directly gives an {$role} attachment counts", 'attachment' === $answer['postType'], (string) $answer['postType'] );
	vergeml_t( "the assign count gives an {$role} no count of that type", vergeml_stored_count( $term ) === vergeml_assign_count( $term, 'vergeml_folder', 'vergeml_caps_secret' ) );
}

wp_set_current_user( $users['editor'] );
$req = new WP_REST_Request( 'POST', '/vergeml/v1/folder' );
foreach ( array( 'taxonomy' => 'vergeml_folder', 'action' => 'rename', 'id' => $term, 'name' => 'vgml caps renamed', 'post_type' => 'vergeml_caps_secret' ) as $k => $v ) {
	$req->set_param( $k, $v );
}
$data = rest_ensure_response( vergeml_rest_folder( $req ) )->get_data();
vergeml_t( 'the folder route gives an editor attachment counts for that type', isset( $data['postType'] ) && 'attachment' === $data['postType'] );

// post: administrator's publish, private and draft, plus the author's own draft.
wp_set_current_user( $users['editor'] );
vergeml_t( 'an editor counts all four posts', 4 === vergeml_assign_count( $term, 'vergeml_folder', 'post' ) );
wp_set_current_user( $users['author'] );
vergeml_t( "an author counts published posts and their own, not another's private or draft", 2 === vergeml_assign_count( $term, 'vergeml_folder', 'post' ), (string) vergeml_assign_count( $term, 'vergeml_folder', 'post' ) );

$loose_private = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'private', 'post_author' => 1, 'post_title' => 'vgml caps loose' ) );
$planted[]     = $loose_private;
wp_set_current_user( 1 );
$all_unfiled = vergeml_folder_unfiled_count( 'vergeml_folder', 'post' );
wp_set_current_user( $users['author'] );
vergeml_t( "an author's unfiled count leaves out another's private post", vergeml_folder_unfiled_count( 'vergeml_folder', 'post' ) < $all_unfiled );

wp_set_current_user( 1 );
foreach ( $planted as $id ) {
	wp_delete_post( $id, true );
}
$granted->remove_cap( 'edit_vergeml_secrets' );
unregister_taxonomy_for_object_type( 'vergeml_folder', 'post' );

/* --- wordpress.org round 9 review: private folders at every door, and the taxonomy on reads --- */

wp_set_current_user( $users['editor'] );
$hidden_term = wp_insert_term( 'vgml-caps-hidden-' . wp_rand(), 'vergeml_folder' );
$hidden_term = (int) $hidden_term['term_id'];
vergeml_private_set( $hidden_term, true );

wp_set_current_user( $users['author'] );
vergeml_t( "another's private folder is hidden from an author", vergeml_private_is_hidden( $hidden_term ) );

$own = wp_insert_attachment( array( 'post_title' => 'vgml caps own', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'post_author' => $users['author'] ) );
$req = new WP_REST_Request( 'POST', '/vergeml/v1/assign' );
foreach ( array( 'taxonomy' => 'vergeml_folder', 'attachments' => array( $own ), 'add' => array( $hidden_term ) ) as $k => $v ) {
	$req->set_param( $k, $v );
}
vergeml_t( '/assign refuses it as a destination', is_wp_error( vergeml_rest_assign( $req ) ) && ! has_term( $hidden_term, 'vergeml_folder', $own ) );

// Through WordPress's own router: it matches routes without regard to case.
function vergeml_core_rest( $route, $params = array() ) {
	$req = new WP_REST_Request( 'GET', $route );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	$res = rest_do_request( $req );
	return array( $res->get_status(), wp_json_encode( $res->get_data() ) );
}

$hidden_name = get_term( $hidden_term )->name;
$own_filed   = wp_insert_attachment( array( 'post_title' => 'vgml caps filed', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'post_author' => 1 ) );
wp_set_object_terms( $own_filed, array( $hidden_term ), 'vergeml_folder' );

foreach ( array( '/wp/v2/vergeml_folder/', '/wp/v2/VERGEML_FOLDER/', '/WP/V2/vergeml_folder/', '/wp/v2/Vergeml_Folder/' ) as $spelling ) {
	list( $status, $body ) = vergeml_core_rest( $spelling . $hidden_term );
	vergeml_t( "core REST will not read it at {$spelling}<id>", 404 === $status && false === strpos( $body, $hidden_name ), (string) $status );
}
foreach ( array( '/wp/v2/vergeml_folder', '/wp/v2/VERGEML_FOLDER' ) as $spelling ) {
	list( $status, $body ) = vergeml_core_rest( $spelling, array( 'include' => array( $hidden_term ) ) );
	vergeml_t( "core REST takes it out of include at {$spelling}", false === strpos( $body, $hidden_name ) );
}
list( $status ) = vergeml_core_rest( '/wp/v2/vergeml_folder/' . $term );
vergeml_t( 'core REST still reads a shared folder for a logged-in user', 200 === $status, (string) $status );
list( $status, $body ) = vergeml_core_rest( '/wp/v2/media', array( 'vergeml_folder' => array( $hidden_term ) ) );
vergeml_t( 'core media will not list it by id', 200 === $status && '[]' === $body, $status . ' ' . substr( $body, 0, 60 ) );

list( $status, $body ) = vergeml_core_rest( "/wp/v2/vergeml_folder\n", array( 'include' => array( $hidden_term ) ) );
vergeml_t( 'a newline after the route does not get past it', false === strpos( $body, $hidden_name ), (string) $status );
list( $status, $body ) = vergeml_core_rest( '/wp/v2/media', array( 'vergeml_folder_exclude' => array( $hidden_term ) ) );
vergeml_t( 'excluding it from core media does not show what is in it', 200 === $status && false !== strpos( $body, '"id":' . $own_filed ), $status . ' ' . substr( $body, 0, 60 ) );
foreach ( array( (string) $hidden_term, $hidden_term . ',0', $hidden_term . '+0' ) as $form ) {
	$_REQUEST['query'] = array( 'vergeml_folder' => $form );
	$args  = apply_filters( 'ajax_query_attachments_args', array( 'post_type' => 'attachment', 'vergeml_folder' => $form ) );
	unset( $_REQUEST['query'] );
	$named = isset( $args['tax_query'] ) ? wp_json_encode( $args['tax_query'] ) : '';
	vergeml_t( "query-attachments will not list it as {$form}", false === strpos( $named, (string) $hidden_term ), $named );
}

$nested_query = array( 'vergeml_folder' => array( array( (string) $term ) ) );
$_REQUEST['query'] = $nested_query;
$args = apply_filters( 'ajax_query_attachments_args', array( 'post_type' => 'attachment' ) + $nested_query );
unset( $_REQUEST['query'] );
vergeml_t( 'a nested query-attachments value does not reach core as an array', false === strpos( isset( $args['tax_query'] ) ? wp_json_encode( $args['tax_query'] ) : '', '[[' ) );

$req = new WP_REST_Request( 'GET', '/wp/v2/media' );
$req->set_param( 'vergeml_folder', array( 'terms' => array( $hidden_term, $term ), 'operator' => 'AND', 'include_children' => true ) );
vergeml_private_strip_media_params( $req );
$shape = $req->get_param( 'vergeml_folder' );
vergeml_t( 'the object form keeps its operator when a hidden id is dropped', array( $term ) === $shape['terms'] && 'AND' === $shape['operator'] && true === $shape['include_children'] );
$req = new WP_REST_Request( 'GET', '/wp/v2/media' );
$req->set_param( 'vergeml_folder', array( 'terms' => array( $term ), 'operator' => 'AND' ) );
vergeml_private_strip_media_params( $req );
vergeml_t( 'a request naming no hidden id is untouched', array( 'terms' => array( $term ), 'operator' => 'AND' ) === $req->get_param( 'vergeml_folder' ) );

$handler = array( 'callback' => array( new WP_REST_Posts_Controller( 'post' ), 'get_items' ) );
$req     = new WP_REST_Request( 'GET', '/wp/v2/posts' );
$req->set_param( 'vergeml_folder', array( $hidden_term ) );
vergeml_private_guard_core_rest( null, $handler, $req );
vergeml_t( 'core post routes will not list it by id', array( 0 ) === $req->get_param( 'vergeml_folder' ) );

vergeml_t( "core's term capabilities refuse another's private folder", ! current_user_can( 'edit_term', $hidden_term ) && ! current_user_can( 'delete_term', $hidden_term ) && ! current_user_can( 'assign_term', $hidden_term ) );
wp_set_current_user( $users['editor'] );
vergeml_t( 'its owner keeps them', current_user_can( 'edit_term', $hidden_term ) );
$second_editor = wp_insert_user( array( 'user_login' => 'vergeml_caps_editor2_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
$users['editor2'] = $second_editor;
wp_set_current_user( $second_editor );
vergeml_t( 'another editor cannot open or rename it', ! current_user_can( 'edit_term', $hidden_term ) );
vergeml_t( 'another editor still edits a shared folder', current_user_can( 'edit_term', $term ) );
wp_set_current_user( $users['author'] );

if ( ! defined( 'REST_REQUEST' ) ) {
	define( 'REST_REQUEST', true );
}
$terms_filter = vergeml_private_filter_terms( array( 'include' => array( $hidden_term ) ), array( 'vergeml_folder' ) );
vergeml_t( 'naming only a hidden folder over REST finds nothing', array( 0 ) === $terms_filter['include'] );

// Every shape of callback a route can have: the guard runs before all of them.
register_rest_route( 'vergeml-caps/v1', '/closure', array( 'methods' => 'GET', 'callback' => function () { return 'ok'; }, 'permission_callback' => '__return_true' ) );
register_rest_route( 'vergeml-caps/v1', '/name', array( 'methods' => 'GET', 'callback' => 'rest_get_url_prefix', 'permission_callback' => '__return_true' ) );
foreach ( array( '/vergeml-caps/v1/closure', '/vergeml-caps/v1/name' ) as $shape ) {
	list( $status ) = vergeml_core_rest( $shape );
	vergeml_t( "the guard lets {$shape} through", 200 === $status, (string) $status );
}
wp_set_current_user( 1 );
list( $status ) = vergeml_core_rest( '/vergeml/v1/folders-progress' );
vergeml_t( "the plugin's own closure route answers", 500 !== $status, (string) $status );
wp_set_current_user( $users['author'] );

wp_set_current_user( 0 );
foreach ( array( '/wp/v2/vergeml_folder', '/wp/v2/VERGEML_FOLDER', "/wp/v2/vergeml_folder\n", '/WP/V2/vergeml_folder/' . $hidden_term ) as $spelling ) {
	list( $status, $body ) = vergeml_core_rest( $spelling );
	vergeml_t( "a logged-out visitor is refused {$spelling}", 401 === $status, (string) $status );
}
list( $status, $body ) = vergeml_core_rest( '/wp/v2/media', array( 'vergeml_folder' => array( $hidden_term ) ) );
vergeml_t( 'core media will not list it logged out', '[]' === $body, substr( $body, 0, 60 ) );

register_taxonomy( 'acme_labels', 'attachment', array( 'show_ui' => true, 'public' => false, 'show_in_rest' => true ) );
( new WP_REST_Terms_Controller( 'acme_labels' ) )->register_routes();
list( $status ) = vergeml_core_rest( '/wp/v2/acme_labels' );
vergeml_t( "another plugin's non-public taxonomy stays as it registered it, logged out", 401 !== $status, (string) $status );
unregister_taxonomy( 'acme_labels' );

wp_set_current_user( 1 );
wp_delete_attachment( $own_filed, true );

wp_set_current_user( $users['editor'] );
vergeml_t( 'its owner still sees it', ! vergeml_private_is_hidden( $hidden_term ) );

register_taxonomy( 'vergeml_caps_strict', 'attachment', array( 'show_ui' => true, 'capabilities' => array( 'assign_terms' => 'vergeml_strict_assign' ) ) );
wp_set_current_user( $users['author'] );
$req = new WP_REST_Request( 'GET', '/vergeml/v1/tree' );
$req->set_param( 'taxonomy', 'vergeml_caps_strict' );
vergeml_t( '/tree refuses a taxonomy the caller may not assign', true !== call_user_func( $routes['/vergeml/v1/tree'][0]['permission_callback'], $req ) );
$req = new WP_REST_Request( 'GET', '/vergeml/v1/state' );
$req->set_param( 'taxonomy', 'vergeml_caps_strict' );
vergeml_t( '/state refuses it too', true !== call_user_func( $routes['/vergeml/v1/state'][0]['permission_callback'], $req ) );
$req = new WP_REST_Request( 'GET', '/vergeml/v1/gallery-folders' );
$req->set_param( 'taxonomy', 'vergeml_caps_strict' );

wp_set_current_user( 1 );
unregister_taxonomy( 'vergeml_caps_strict' );
wp_delete_attachment( $own, true );
vergeml_private_set( $hidden_term, false );
wp_delete_term( $hidden_term, 'vergeml_folder' );

/* --- tidy -------------------------------------------------------------------- */

wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $users as $id ) {
	wp_delete_user( $id );
}
wp_delete_attachment( $att, true );
wp_delete_term( $term, 'vergeml_folder' );

printf( "\n%d/%d passed\n\n", $GLOBALS['vergeml_ok'], $GLOBALS['vergeml_ok'] + $GLOBALS['vergeml_bad'] );
exit( $GLOBALS['vergeml_bad'] ? 1 : 0 );
