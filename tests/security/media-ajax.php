<?php
/**
 *  The media modal's two saves, now that core answers its own actions
 *  (wordpress.org review 2, would-reject 1, 2026-10-05).
 *
 *    A  nothing of this plugin answers wp_ajax_save-attachment-compat or
 *       wp_ajax_save-attachment-order;
 *    B  core's own save-attachment-compat handler, run the way admin-ajax.php
 *       runs it, saves the folder checkboxes (tax_input) through the plugin's
 *       attachment_fields_to_save filter, carries the term counts back, and
 *       leaves a folder alone that the request did not name;
 *    C  an author cannot file somebody else's picture through it (core's own
 *       edit_post refusal comes first), and the checkbox needs assign_terms;
 *    D  ordering is the plugin's own vergeml-save-attachment-order: a forged
 *       nonce is refused, a good one renumbers.
 *
 *      node tools/verify.mjs media-ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$GLOBALS['ma_ok']  = 0;
$GLOBALS['ma_bad'] = 0;

function ma_t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['ma_ok']++ : $GLOBALS['ma_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, '' !== $detail ? '  -- ' . $detail : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

class Ma_Die extends Exception {}

/** One AJAX action, as admin-ajax.php runs it: wp_send_json and wp_die throw instead of exiting. */
function ma_ajax( $action, $request ) {
	$die = function () {
		return function ( $message = '' ) {
			throw new Ma_Die( is_scalar( $message ) ? (string) $message : '' );
		};
	};
	$_REQUEST = $request;
	$_POST    = $request;
	add_filter( 'wp_doing_ajax', '__return_true' );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	$died = null;
	try {
		do_action( 'wp_ajax_' . $action );
	} catch ( Ma_Die $e ) {
		$died = $e->getMessage();
	}
	$out = (string) ob_get_clean();
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', $die );
	$_REQUEST = array();
	$_POST    = array();
	return array( 'out' => $out, 'json' => json_decode( $out, true ), 'died' => $died );
}

function ma_file( $author ) {
	$uploads = wp_upload_dir();
	$path    = trailingslashit( $uploads['path'] ) . 'ma-' . wp_rand() . '.png';
	file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$id = (int) wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'ma picture', 'post_status' => 'inherit', 'post_author' => $author ), $path );
	$GLOBALS['ma_posts'][] = $id;
	return $id;
}

$GLOBALS['ma_posts'] = array();
$ma_tax  = 'media_category';
$ma_opts = get_option( 'vergeml_lib_options' );

// admin-ajax.php hooks core's handlers itself, at priority 1; a suite has to do the same.
add_action( 'wp_ajax_save-attachment-compat', 'wp_ajax_save_attachment_compat', 1 );
add_action( 'wp_ajax_save-attachment-order', 'wp_ajax_save_attachment_order', 1 );

wp_set_current_user( 1 );
$ma_editor = (int) wp_insert_user( array( 'user_login' => 'ma_editor_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
$ma_author = (int) wp_insert_user( array( 'user_login' => 'ma_author_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
$ma_a      = (int) wp_insert_term( 'ma folder a ' . wp_rand(), $ma_tax )['term_id'];
$ma_b      = (int) wp_insert_term( 'ma folder b ' . wp_rand(), $ma_tax )['term_id'];
$ma_pic    = ma_file( 1 );
$ma_pic2   = ma_file( 1 );
$ma_pic3   = ma_file( 1 );

update_option( 'vergeml_lib_options', array_merge( is_array( $ma_opts ) ? $ma_opts : array(), array( 'show_count' => 1 ) ) );


/* ------------------------------------------------------------- A  hands off */

echo "\nA  core's actions are core's\n\n";

$ma_ours = function ( $hook ) {
	global $wp_filter;
	$found = array();
	if ( isset( $wp_filter[ $hook ] ) ) {
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				if ( is_string( $cb['function'] ) && 0 === strpos( $cb['function'], 'vergeml' ) ) {
					$found[] = $cb['function'] . '@' . $priority;
				}
			}
		}
	}
	return $found;
};

ma_t( 'nothing of ours on wp_ajax_save-attachment-compat', array() === $ma_ours( 'wp_ajax_save-attachment-compat' ), implode( ', ', $ma_ours( 'wp_ajax_save-attachment-compat' ) ) );
ma_t( 'nothing of ours on wp_ajax_save-attachment-order', array() === $ma_ours( 'wp_ajax_save-attachment-order' ), implode( ', ', $ma_ours( 'wp_ajax_save-attachment-order' ) ) );
ma_t( 'nothing of ours on wp_ajax_delete-post', array() === $ma_ours( 'wp_ajax_delete-post' ) );
ma_t( 'the ordering action is the plugin\'s own', false !== has_action( 'wp_ajax_vergeml-save-attachment-order', 'vergeml_save_attachment_order' ) );


/* ---------------------------------------------- B  core saves the checkboxes */

echo "\nB  core's save-attachment-compat saves the folder checkboxes\n\n";

wp_set_current_user( $ma_editor );
wp_set_object_terms( $ma_pic, array( $ma_b ), $ma_tax );

$ma_r = ma_ajax( 'save-attachment-compat', array(
	'id'          => $ma_pic,
	'nonce'       => wp_create_nonce( 'update-post_' . $ma_pic ),
	'attachments' => array( $ma_pic => array( 'post_title' => 'ma renamed' ) ),
	'tax_input'   => array( $ma_tax => array( $ma_a => '1', $ma_b => '0' ) ),
) );

$ma_now = wp_get_object_terms( $ma_pic, $ma_tax, array( 'fields' => 'ids' ) );
ma_t( 'core answered success', ! empty( $ma_r['json']['success'] ), substr( $ma_r['out'], 0, 200 ) );
ma_t( 'the ticked folder is set, the unticked one cleared', array( $ma_a ) === array_map( 'intval', (array) $ma_now ), wp_json_encode( $ma_now ) );
ma_t( 'the reply carries the term counts', isset( $ma_r['json']['data']['tcount'][ $ma_a ] ) && 1 === (int) $ma_r['json']['data']['tcount'][ $ma_a ], wp_json_encode( $ma_r['json']['data']['tcount'] ?? null ) );

// A request that names no checkbox for the taxonomy leaves its folders alone.
$ma_r = ma_ajax( 'save-attachment-compat', array(
	'id'          => $ma_pic,
	'nonce'       => wp_create_nonce( 'update-post_' . $ma_pic ),
	'attachments' => array( $ma_pic => array( 'post_title' => 'ma renamed again' ) ),
) );
ma_t( 'no tax_input: the folders stay', array( $ma_a ) === array_map( 'intval', (array) wp_get_object_terms( $ma_pic, $ma_tax, array( 'fields' => 'ids' ) ) ) );

// The counts ride only on this action's reply.
$ma_js = wp_prepare_attachment_for_js( $ma_pic );
ma_t( 'no term counts on an ordinary attachment payload', ! isset( $ma_js['tcount'] ) );


/* ---------------------------------------------------- C  who may file what */

echo "\nC  core's refusals come first\n\n";

wp_set_object_terms( $ma_pic2, array(), $ma_tax );
wp_set_current_user( $ma_author );
$ma_r = ma_ajax( 'save-attachment-compat', array(
	'id'          => $ma_pic2,
	'nonce'       => wp_create_nonce( 'update-post_' . $ma_pic2 ),
	'attachments' => array( $ma_pic2 => array( 'post_title' => 'not yours' ) ),
	'tax_input'   => array( $ma_tax => array( $ma_a => '1' ) ),
) );
ma_t( 'an author on somebody else\'s picture: refused, nothing filed', empty( $ma_r['json']['success'] ) && array() === (array) wp_get_object_terms( $ma_pic2, $ma_tax, array( 'fields' => 'ids' ) ), substr( $ma_r['out'], 0, 120 ) );

wp_set_current_user( $ma_editor );
$ma_r = ma_ajax( 'save-attachment-compat', array(
	'id'          => $ma_pic2,
	'nonce'       => 'forged',
	'attachments' => array( $ma_pic2 => array( 'post_title' => 'forged' ) ),
	'tax_input'   => array( $ma_tax => array( $ma_a => '1' ) ),
) );
ma_t( 'a forged nonce: refused, nothing filed', array() === (array) wp_get_object_terms( $ma_pic2, $ma_tax, array( 'fields' => 'ids' ) ) && 'forged' !== get_post_field( 'post_title', $ma_pic2 ) );


/* ------------------------------------------------------------ D  ordering */

echo "\nD  ordering is vergeml-save-attachment-order\n\n";

wp_set_current_user( $ma_editor );
$ma_r = ma_ajax( 'vergeml-save-attachment-order', array(
	'nonce'       => 'forged',
	'post_id'     => 0,
	'attachments' => array( $ma_pic3 => 1, $ma_pic2 => 2, $ma_pic => 3 ),
) );
ma_t( 'a forged nonce is refused', '-1' === (string) $ma_r['died'] || '-1' === trim( $ma_r['out'] ), var_export( $ma_r['died'], true ) . ' ' . $ma_r['out'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

$ma_r = ma_ajax( 'vergeml-save-attachment-order', array(
	'nonce'       => wp_create_nonce( 'vergeml-bulk-edit-nonce' ),
	'post_id'     => 0,
	'attachments' => array( $ma_pic3 => 1, $ma_pic2 => 2, $ma_pic => 3 ),
) );
clean_post_cache( $ma_pic );
clean_post_cache( $ma_pic2 );
clean_post_cache( $ma_pic3 );
ma_t( 'the library order is saved', ! empty( $ma_r['json']['success'] ) && 1 === (int) get_post_field( 'menu_order', $ma_pic3 ) && 3 === (int) get_post_field( 'menu_order', $ma_pic ), substr( $ma_r['out'], 0, 160 ) . ' ' . get_post_field( 'menu_order', $ma_pic3 ) . '/' . get_post_field( 'menu_order', $ma_pic ) );


/* ---------------------------------------------------------------- tidy up */

wp_set_current_user( 1 );
foreach ( $GLOBALS['ma_posts'] as $ma_id ) {
	wp_delete_attachment( $ma_id, true );
}
wp_delete_term( $ma_a, $ma_tax );
wp_delete_term( $ma_b, $ma_tax );
wp_delete_user( $ma_editor );
wp_delete_user( $ma_author );
false === $ma_opts ? delete_option( 'vergeml_lib_options' ) : update_option( 'vergeml_lib_options', $ma_opts );

printf( "\n%d/%d passed\n", $GLOBALS['ma_ok'], $GLOBALS['ma_ok'] + $GLOBALS['ma_bad'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $GLOBALS['ma_bad'] > 0 ? 1 : 0 );
