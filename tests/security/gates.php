<?php
/**
 *  The gates the code review of review 2's fixes found untested (2026-10-04,
 *  Verification Gap layer), each asked with real objects:
 *
 *    A  the admin-allowed rows: /health-keep, /health-delete, /autofile-act
 *       and /folder-privacy answer an administrator naming real files and
 *       folders, and refuse an editor naming the same ones;
 *    B  the private-folder gate as an editor: no folder created under, renamed
 *       in or moved into somebody else's private folder; their own is fine;
 *    C  bulk terms and /assign as an author naming a page: the page is
 *       skipped or refused, the author's own file is filed;
 *    D  admin_init as a logged-out request (admin-post.php): no run revived,
 *       nothing sent; as an administrator the run is revived;
 *    E  the REST bridge: the nonce, upload_files, the site's own
 *       rest_authentication_errors, and only vergeml/v1 routes.
 *
 *      node tools/verify.mjs gates
 *
 *  A fresh Playground each run; everything it makes is deleted at the end.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/user.php';

$GLOBALS['gt_ok']  = 0;
$GLOBALS['gt_bad'] = 0;

function gt_t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['gt_ok']++ : $GLOBALS['gt_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, '' !== $detail ? '  -- ' . $detail : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

class Gt_Die extends Exception {}

/** wp_send_json()/wp_die() inside an AJAX handler throw instead of ending the suite. */
function gt_ajax( $callback ) {
	$die = function () {
		return function ( $message = '', $title = '', $args = array() ) {
			throw new Gt_Die( is_scalar( $message ) ? (string) $message : '' );
		};
	};
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	$thrown = '';
	try {
		call_user_func( $callback );
	} catch ( Gt_Die $e ) {
		$thrown = $e->getMessage();
	}
	$out = (string) ob_get_clean();
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', $die );
	return array( 'out' => $out, 'json' => json_decode( $out, true ), 'die' => $thrown );
}

function gt_req( $method, $route, $params ) {
	$req = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	return $req;
}

/** A 1x1 PNG on disk, attached, by this author. */
function gt_file( $name, $author ) {
	$uploads = wp_upload_dir();
	$path    = trailingslashit( $uploads['path'] ) . $name;
	file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $name, 'post_status' => 'inherit', 'post_author' => $author ), $path );
	$GLOBALS['gt_posts'][] = (int) $id;
	return (int) $id;
}

$GLOBALS['gt_posts'] = array();
$GLOBALS['gt_terms'] = array();
$GLOBALS['gt_users'] = array();
$GLOBALS['gt_http']  = array();

add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$GLOBALS['gt_http'][] = (string) $url;
	return array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
}, 1, 3 );

wp_set_current_user( 1 );

foreach ( array( 'author', 'editor', 'administrator' ) as $role ) {
	$GLOBALS['gt_users'][ $role ] = (int) wp_insert_user( array( 'user_login' => 'gt_' . $role . '_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => $role ) );
}
$gt_author = $GLOBALS['gt_users']['author'];
$gt_editor = $GLOBALS['gt_users']['editor'];
$gt_admin  = $GLOBALS['gt_users']['administrator'];

$gt_tax  = 'vergeml_folder';
$gt_term = function ( $name, $parent = 0 ) use ( $gt_tax ) {
	$t = wp_insert_term( $name . ' ' . wp_rand(), $gt_tax, array( 'parent' => $parent ) );
	$GLOBALS['gt_terms'][] = (int) $t['term_id'];
	return (int) $t['term_id'];
};

$gt_folder = $gt_term( 'gt folder' );
$gt_keep   = gt_file( 'gt-keep-' . wp_rand() . '.png', 1 );
$gt_drop   = gt_file( 'gt-drop-' . wp_rand() . '.png', 1 );
$gt_mine   = gt_file( 'gt-author-' . wp_rand() . '.png', $gt_author );
$gt_page   = (int) wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'gt page', 'post_status' => 'publish', 'post_author' => $gt_author ) );
$GLOBALS['gt_posts'][] = $gt_page;


/* ------------------------------------------------------- A  admin-allowed rows */

echo "\nA  the routes an administrator may call with real objects, and an editor may not\n\n";

$gt_rows = array(
	'/health-keep'    => array( 'vergeml_health_can_drop', array( 'keep' => $gt_keep, 'drop' => array( $gt_drop ) ) ),
	'/health-delete'  => array( 'vergeml_health_can_drop', array( 'keep' => $gt_keep, 'drop' => array( $gt_drop ) ) ),
	'/autofile-act'   => array( 'vergeml_autofile_can_act', array( 'attachment_id' => $gt_keep, 'term_id' => $gt_folder, 'action' => 'accept' ) ),
	'/folder-privacy' => array( 'vergeml_private_can_edit', array( 'id' => $gt_folder, 'private' => true ) ),
);

foreach ( $gt_rows as $route => $row ) {
	list( $cb, $params ) = $row;
	if ( ! function_exists( $cb ) ) {
		gt_t( "$route: $cb is loaded", false );
		continue;
	}
	wp_set_current_user( $gt_admin );
	$admin = call_user_func( $cb, gt_req( 'POST', '/vergeml/v1' . $route, $params ) );
	wp_set_current_user( $gt_editor );
	$editor = call_user_func( $cb, gt_req( 'POST', '/vergeml/v1' . $route, $params ) );
	gt_t( "$route: administrator yes, editor no", true === $admin && true !== $editor, 'admin ' . var_export( $admin, true ) . ', editor ' . var_export( $editor, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
}

// The same administrator, naming a page where a file belongs: refused.
wp_set_current_user( $gt_admin );
gt_t( '/health-delete: a page named as the copy to drop is refused', true !== vergeml_health_can_drop( gt_req( 'POST', '/vergeml/v1/health-delete', array( 'keep' => $gt_keep, 'drop' => array( $gt_page ) ) ) ) );
gt_t( '/autofile-act: a page named as the picture is refused', true !== vergeml_autofile_can_act( gt_req( 'POST', '/vergeml/v1/autofile-act', array( 'attachment_id' => $gt_page, 'term_id' => $gt_folder, 'action' => 'accept' ) ) ) );


/* ------------------------------------------------ B  private folders, as editor */

echo "\nB  somebody else's private folder is no editor's to build on\n\n";

$gt_secret = $gt_term( 'gt admin private' );
$gt_own    = $gt_term( 'gt editor private' );
$gt_child  = $gt_term( 'gt shared child' );

wp_set_current_user( $gt_admin );
vergeml_private_rest( gt_req( 'POST', '/vergeml/v1/folder-privacy', array( 'id' => $gt_secret, 'private' => true ) ) );
$gt_map = vergeml_private_map();
$gt_map[ $gt_own ] = $gt_editor;
update_option( VERGEML_PRIVATE_OPTION, $gt_map, true );

wp_set_current_user( $gt_editor );
$gt_folder_may = function ( $params ) {
	return vergeml_can_manage_folders( gt_req( 'POST', '/vergeml/v1/folder', array_merge( array( 'taxonomy' => 'vergeml_folder' ), $params ) ) );
};

gt_t( 'the admin\'s private folder is hidden from the editor', in_array( $gt_secret, vergeml_private_hidden_from(), true ) );
gt_t( 'create under it: refused', true !== $gt_folder_may( array( 'action' => 'create', 'parent' => $gt_secret ) ) );
gt_t( 'rename it: refused', true !== $gt_folder_may( array( 'action' => 'rename', 'id' => $gt_secret ) ) );
gt_t( 'move a shared folder into it: refused', true !== $gt_folder_may( array( 'action' => 'move', 'id' => $gt_child, 'parent' => $gt_secret ) ) );
gt_t( 'order siblings that include it: refused', true !== $gt_folder_may( array( 'action' => 'order', 'ids' => array( $gt_child, $gt_secret ) ) ) );
gt_t( 'create under the editor\'s own private folder: allowed', true === $gt_folder_may( array( 'action' => 'create', 'parent' => $gt_own ) ) );
gt_t( 'move a shared folder into the editor\'s own: allowed', true === $gt_folder_may( array( 'action' => 'move', 'id' => $gt_child, 'parent' => $gt_own ) ) );
gt_t( 'making a folder private stays an administrator\'s', true !== vergeml_private_can_edit( gt_req( 'POST', '/vergeml/v1/folder-privacy', array( 'id' => $gt_own, 'private' => true ) ) ) );

wp_set_current_user( $gt_admin );
gt_t( 'the administrator may create under any private folder', true === $gt_folder_may( array( 'action' => 'create', 'parent' => $gt_secret ) ) );


/* ------------------------------------------- C  an author naming a page */

echo "\nC  bulk terms and /assign as an author: their own file yes, a page no\n\n";

wp_set_current_user( $gt_author );

$_REQUEST['_wpnonce']           = wp_create_nonce( 'bulk-media' );
$_REQUEST['vergeml_bulk_term'] = $gt_tax . ':' . $gt_folder;
$gt_loc = vergeml_handle_bulk_terms( admin_url( 'upload.php' ), 'vergeml-add-term', array( $gt_mine, $gt_page ) );
unset( $_REQUEST['_wpnonce'], $_REQUEST['vergeml_bulk_term'] );
parse_str( (string) wp_parse_url( $gt_loc, PHP_URL_QUERY ), $gt_q );

gt_t( 'bulk add: one done, one skipped', '1' === ( $gt_q['vergeml_done'] ?? '' ) && '1' === ( $gt_q['vergeml_skipped'] ?? '' ), $gt_loc );
gt_t( 'the author\'s file is in the folder', has_term( $gt_folder, $gt_tax, $gt_mine ) );
gt_t( 'the page is not', ! has_term( $gt_folder, $gt_tax, $gt_page ) );

wp_set_object_terms( $gt_mine, array(), $gt_tax );
$gt_assign_req = gt_req( 'POST', '/vergeml/v1/assign', array( 'taxonomy' => $gt_tax, 'attachments' => array( $gt_mine, $gt_page ), 'add' => array( $gt_folder ), 'mode' => 'move' ) );
$gt_may        = vergeml_can_assign( $gt_assign_req );
$gt_assign     = true === $gt_may ? vergeml_rest_assign( $gt_assign_req ) : null;
$gt_assign     = $gt_assign instanceof WP_REST_Response ? $gt_assign->get_data() : $gt_assign;

gt_t( '/assign: the author may call it', true === $gt_may );
gt_t( '/assign move: the author\'s file is moved, the page refused', is_array( $gt_assign ) && has_term( $gt_folder, $gt_tax, $gt_mine ) && ! has_term( $gt_folder, $gt_tax, $gt_page ), wp_json_encode( $gt_assign ) );

$gt_other = gt_file( 'gt-other-' . wp_rand() . '.png', $gt_editor );
$gt_r     = vergeml_rest_assign( gt_req( 'POST', '/vergeml/v1/assign', array( 'taxonomy' => $gt_tax, 'attachments' => array( $gt_other ), 'add' => array( $gt_folder ) ) ) );
gt_t( '/assign: somebody else\'s file is refused to an author', ! has_term( $gt_folder, $gt_tax, $gt_other ) );


/* ------------------------------------------------ D  admin_init, logged out */

echo "\nD  admin_init on a logged-out admin-post.php request does no housekeeping\n\n";

$gt_run_was   = get_option( 'vergeml_ai_run', false );
$gt_stats_was = get_option( VERGEML_STATS_OPTION, false );
$gt_ai_was    = get_option( 'vergeml_ai', false );

$gt_ai = is_array( $gt_ai_was ) ? $gt_ai_was : array();
$gt_ai['license_key'] = vergeml_ai_seal( 'VGML-' . strtoupper( wp_generate_password( 30, false, false ) ) . 'ZZZZ' );
update_option( 'vergeml_ai', $gt_ai, false );
update_option( VERGEML_STATS_OPTION, array( 'opted' => 1, 'snapshot' => array(), 'time' => 0, 'sent' => 0 ), false );
update_option( 'vergeml_ai_run', array( 'active' => true, 'scope' => 'unindexed' ), false );
delete_transient( 'vergeml_ai_run_lock' );
delete_transient( 'doing_cron' );
wp_clear_scheduled_hook( VERGEML_AI_RUN_HOOK );

wp_set_current_user( 0 );
$GLOBALS['gt_http'] = array();
do_action( 'admin_init' );
gt_t( 'no run revived: nothing booked', false === wp_next_scheduled( VERGEML_AI_RUN_HOOK ) );
// WordPress's own update checks hang off admin_init too; they are core's, not ours.
$gt_ours = array_filter( $GLOBALS['gt_http'], function ( $u ) { return false === strpos( $u, 'api.wordpress.org' ); } );
gt_t( 'nothing of ours sent anywhere (no service call, no cron loopback)', array() === $gt_ours, implode( ', ', $gt_ours ) );
gt_t( 'the library counts were not refreshed', 0 === (int) vergeml_stats_state()['time'] );

wp_set_current_user( $gt_admin );
$GLOBALS['gt_http'] = array();
do_action( 'admin_init' );
gt_t( 'as an administrator the stalled run is booked again', false !== wp_next_scheduled( VERGEML_AI_RUN_HOOK ) );
gt_t( 'and the opted-in counts are refreshed', 0 < (int) vergeml_stats_state()['time'] );

wp_clear_scheduled_hook( VERGEML_AI_RUN_HOOK );
delete_transient( 'doing_cron' );
foreach ( array( 'vergeml_ai_run' => $gt_run_was, VERGEML_STATS_OPTION => $gt_stats_was, 'vergeml_ai' => $gt_ai_was ) as $gt_opt => $gt_was ) {
	false === $gt_was ? delete_option( $gt_opt ) : update_option( $gt_opt, $gt_was, false );
}


/* ------------------------------------------------------- E  the REST bridge */

echo "\nE  the REST bridge asks what REST asks\n\n";

wp_set_current_user( $gt_editor );

unset( $_SERVER['HTTP_X_WP_NONCE'] );
$gt_b = gt_ajax( 'vergeml_transport_bridge' );
gt_t( 'no nonce: 403 rest_cookie_invalid_nonce', isset( $gt_b['json']['code'] ) && 'rest_cookie_invalid_nonce' === $gt_b['json']['code'], $gt_b['out'] );

$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );
$gt_off = function () {
	return new WP_Error( 'gt_rest_off', 'REST is off on this site', array( 'status' => 401 ) );
};
add_filter( 'rest_authentication_errors', $gt_off );
$gt_b = gt_ajax( 'vergeml_transport_bridge' );
remove_filter( 'rest_authentication_errors', $gt_off );
gt_t( 'a site that switched REST off has switched the bridge off', isset( $gt_b['json']['code'] ) && 'gt_rest_off' === $gt_b['json']['code'], $gt_b['out'] );

$gt_sub = (int) wp_insert_user( array( 'user_login' => 'gt_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$GLOBALS['gt_users']['subscriber'] = $gt_sub;
wp_set_current_user( $gt_sub );
$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );
$gt_b = gt_ajax( 'vergeml_transport_bridge' );
gt_t( 'without upload_files: rest_forbidden', isset( $gt_b['json']['code'] ) && 'rest_forbidden' === $gt_b['json']['code'], $gt_b['out'] );
unset( $_SERVER['HTTP_X_WP_NONCE'] );

wp_set_current_user( $gt_editor );
gt_t( 'one of ours, its own method: yes', vergeml_transport_is_ours( '/vergeml/v1/tree', 'GET' ) );
gt_t( 'core\'s own route: no', ! vergeml_transport_is_ours( '/wp/v2/users', 'GET' ) );
gt_t( 'a path that climbs out: no', ! vergeml_transport_is_ours( '/vergeml/v1/../wp/v2/users', 'GET' ) );
gt_t( 'a route that is not registered: no', ! vergeml_transport_is_ours( '/vergeml/v1/no-such-route', 'GET' ) );
gt_t( 'a method the route does not answer: no', ! vergeml_transport_is_ours( '/vergeml/v1/tree', 'DELETE' ) );


/* ---------------------------------------------------------------- tidy up */

wp_set_current_user( 1 );
foreach ( $GLOBALS['gt_posts'] as $gt_id ) {
	wp_delete_post( $gt_id, true );
}
foreach ( $GLOBALS['gt_terms'] as $gt_id ) {
	wp_delete_term( $gt_id, $gt_tax );
}
foreach ( $GLOBALS['gt_users'] as $gt_id ) {
	wp_delete_user( $gt_id );
}
$gt_map = vergeml_private_map();
unset( $gt_map[ $gt_secret ], $gt_map[ $gt_own ] );
update_option( VERGEML_PRIVATE_OPTION, $gt_map, true );

printf( "\n%d/%d passed\n", $GLOBALS['gt_ok'], $GLOBALS['gt_ok'] + $GLOBALS['gt_bad'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $GLOBALS['gt_bad'] > 0 ? 1 : 0 );
