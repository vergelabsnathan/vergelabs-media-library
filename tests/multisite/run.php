<?php
/*
 *  HTTP entry for the multisite suite on Playground.
 *
 *  Playground's server mode does not run a blueprint step's output back to
 *  the console, and a PHP step there could not be watched. So the runner
 *  boots the network, waits for "Ready", and then fetches this file directly:
 *  it loads WordPress and runs the suite, and the response body is the
 *  report. tests/ never ships (tools/deploy.mjs), so this is unreachable on a
 *  real site.
 */

header( 'Content-Type: text/plain; charset=utf-8' );

// /wordpress/wp-content/plugins/vergelabs-media-library/tests/multisite -> /wordpress
$wp_load = dirname( __DIR__, 5 ) . '/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
    echo "FAIL  wp-load.php not found at $wp_load\n1 FAILED\n";
    exit;
}

register_shutdown_function( function () {
    $e = error_get_last();
    if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
        echo 'FAIL  fatal: ' . $e['message'] . ' @ ' . basename( $e['file'] ) . ':' . $e['line'] . "\n1 FAILED\n";
    }
} );

require_once $wp_load;
require_once __DIR__ . '/network.php';
