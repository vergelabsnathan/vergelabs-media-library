<?php
/**
 *  Shared by the runtime phases (tools/runtime-checks.mjs). Each phase is its own
 *  request: wp-load.php has run, with the plugin loaded if it is active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

const RT_PLUGIN = 'vergelabs-media-library/vergelabs-media-library.php';

$GLOBALS['rt_ok']      = 0;
$GLOBALS['rt_bad']     = 0;
$GLOBALS['rt_notices'] = array();
$GLOBALS['rt_http']    = array();

function rt_t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['rt_ok']++ : $GLOBALS['rt_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, ( ! $pass && '' !== $detail ) ? '  -- ' . $detail : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function rt_tables() {
	global $wpdb;
	$made = array();
	foreach ( array( 'vergeml_ai_index', 'vergeml_organize_runs', 'vergeml_librarian_batches', 'vergeml_librarian_moves' ) as $t ) {
		$name = $wpdb->prefix . $t;
		if ( $name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) ) ) {
			$made[] = $t;
		}
	}
	return $made;
}

/** Runs $fn with output buffered, every PHP notice and every outgoing request recorded. */
function rt_watch( $fn ) {
	$GLOBALS['rt_notices'] = array();
	$GLOBALS['rt_http']    = array();
	set_error_handler( function ( $no, $str, $file, $line ) {
		$GLOBALS['rt_notices'][] = basename( $file ) . ':' . $line . ' ' . $str;
		return true;
	} );
	$recorder = function ( $pre, $args, $url ) {
		$GLOBALS['rt_http'][] = $url;
		return $pre;
	};
	add_filter( 'pre_http_request', $recorder, 1, 3 );
	ob_start();
	$result = call_user_func( $fn );
	$output = ob_get_clean();
	remove_filter( 'pre_http_request', $recorder, 1 );
	restore_error_handler();
	return array( $result, $output, $GLOBALS['rt_notices'], $GLOBALS['rt_http'] );
}

function rt_quiet( $label, $watched ) {
	list( $result, $output, $notices, $http ) = $watched;
	rt_t( "$label: no error", ! is_wp_error( $result ) && false !== $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
	rt_t( "$label: prints nothing", '' === trim( (string) $output ), substr( (string) $output, 0, 200 ) );
	rt_t( "$label: no PHP notice, warning or deprecation", array() === $notices, implode( ' | ', array_slice( $notices, 0, 4 ) ) );
	rt_t( "$label: no outgoing request", array() === $http, implode( ', ', $http ) );
}

/** A real one-pixel PNG in the uploads folder, as an attachment. */
function rt_picture( $title ) {
	$up = wp_upload_dir();
	wp_mkdir_p( $up['path'] );
	$path = trailingslashit( $up['path'] ) . sanitize_file_name( $title ) . '.png';
	file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) ); // phpcs:ignore
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $title, 'post_status' => 'inherit' ), $path );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
	return $id;
}

function rt_done( $phase ) {
	printf( "%s: %d/%d passed\n", $phase, $GLOBALS['rt_ok'], $GLOBALS['rt_ok'] + $GLOBALS['rt_bad'] ); // phpcs:ignore
}
