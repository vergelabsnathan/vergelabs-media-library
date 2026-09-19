<?php
/**
 *  Does /ai-status answer with the line under the title when asked?
 *
 *      node tools/box-eval.mjs tools/box-ai-line-probe.php
 *
 *  Reads only. Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_set_current_user( 1 );

printf( "vergeml_ai_facts_line   %s\n", function_exists( 'vergeml_ai_facts_line' ) ? 'defined' : 'MISSING' );
printf( "vergeml_ai_screen_counts %s\n", function_exists( 'vergeml_ai_screen_counts' ) ? 'defined' : 'MISSING' );

$req = new WP_REST_Request( 'GET', '/vergeml/v1/ai-status' );
$req->set_param( 'line', true );
$res = rest_do_request( $req );
$data = $res->get_data();

printf( "status %d\n", $res->get_status() );
printf( "counts_line key present: %s\n", array_key_exists( 'counts_line', (array) $data ) ? 'yes' : 'NO' );
printf( "counts_line: %s\n", isset( $data['counts_line'] ) ? wp_json_encode( $data['counts_line'] ) : 'null/absent' );

$plain = rest_do_request( new WP_REST_Request( 'GET', '/vergeml/v1/ai-status' ) )->get_data();
printf( "without the param: %s\n", array_key_exists( 'counts_line', (array) $plain ) ? wp_json_encode( $plain['counts_line'] ) : 'key absent' );
