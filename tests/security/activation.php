<?php
/**
 *  Activation on a clean site (wordpress.org review 2, round 5, 2026-10-11):
 *  the plugin activates with no output, no PHP notice or warning and no
 *  outgoing request, and the four tables are made by dbDelta during the
 *  activation itself -- not by a later request.
 *
 *      node tools/verify.mjs activation
 *
 *  Runs fresh (tools/verify.mjs): the plugin is mounted but was never loaded,
 *  so activate_plugin() below is the first time its files are included, after
 *  plugins_loaded has fired -- the request a site owner's Activate click makes.
 *  The first version of this suite activated a plugin that was already loaded
 *  in the same request, which is not that.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$GLOBALS['ac_ok']  = 0;
$GLOBALS['ac_bad'] = 0;

function ac_t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['ac_ok']++ : $GLOBALS['ac_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, '' !== $detail ? '  -- ' . $detail : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

global $wpdb;

$ac_plugin = 'vergelabs-media-library/vergelabs-media-library.php';
// Literal names: the plugin is not loaded yet, so its $wpdb properties do not exist.
$ac_tables = array( $wpdb->prefix . 'vergeml_ai_index', $wpdb->prefix . 'vergeml_organize_runs', $wpdb->prefix . 'vergeml_librarian_batches', $wpdb->prefix . 'vergeml_librarian_moves' );

$ac_exists = function ( $table ) use ( $wpdb ) {
	return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
};

echo "\nactivation on a clean site\n\n";

wp_set_current_user( 1 );

ac_t( 'before: the plugin is not loaded in this request', ! function_exists( 'vergeml_provision_site' ) && did_action( 'plugins_loaded' ) );
ac_t( 'before: none of the four tables exists', 0 === count( array_filter( $ac_tables, $ac_exists ) ) );
ac_t( 'before: no version is stamped', false === get_option( 'vergeml_version' ) );

// Every PHP notice, warning or deprecation raised during the activation, and every outgoing request.
$GLOBALS['ac_notices'] = array();
$GLOBALS['ac_http']    = array();
set_error_handler( function ( $no, $str, $file, $line ) {
	$GLOBALS['ac_notices'][] = basename( $file ) . ':' . $line . ' ' . $str;
	return true;
} );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$GLOBALS['ac_http'][] = $url;
	return $pre;
}, 1, 3 );

ob_start();
$ac_result = activate_plugin( $ac_plugin );
$ac_output = ob_get_clean();
restore_error_handler();

ac_t( 'activate_plugin() reports no error', ! is_wp_error( $ac_result ), is_wp_error( $ac_result ) ? $ac_result->get_error_message() : '' );
ac_t( 'the activation prints nothing', '' === $ac_output, substr( $ac_output, 0, 200 ) );
ac_t( 'no PHP notice, warning or deprecation during the activation', array() === $GLOBALS['ac_notices'], implode( ' | ', array_slice( $GLOBALS['ac_notices'], 0, 5 ) ) );
ac_t( 'no outgoing request during the activation', array() === $GLOBALS['ac_http'], implode( ', ', $GLOBALS['ac_http'] ) );
ac_t( 'the plugin is active', is_plugin_active( $ac_plugin ) );

foreach ( $ac_tables as $ac_table ) {
	ac_t( "dbDelta made {$ac_table} during the activation", $ac_exists( $ac_table ) );
}
ac_t( 'the version is stamped', defined( 'VERGEML_VERSION' ) && VERGEML_VERSION === get_option( 'vergeml_version' ) );

printf( "\n%d/%d passed\n", $GLOBALS['ac_ok'], $GLOBALS['ac_ok'] + $GLOBALS['ac_bad'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $GLOBALS['ac_bad'] > 0 ? 1 : 0 );
