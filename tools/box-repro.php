<?php
/*
 *  Reproduce the refusal through the plugin's own describe path, and print
 *  the message -- which carries the HTTP status the generic code hides.
 *  Twelve pictures, in the same eight-wide bursts the background run uses.
 */
global $wpdb; $t = $wpdb->prefix . 'vergeml_ai_index';
$ids = array_map( 'intval', $wpdb->get_col( "SELECT attachment_id FROM {$t} WHERE model <> 'mock' AND caption <> '' ORDER BY attachment_id DESC LIMIT 12" ) );
printf( "credits before: %s\n", var_export( vergeml_ai_refresh_credits( true ), true ) );
$ok = 0; $t0 = microtime( true );
foreach ( array_chunk( $ids, 8 ) as $chunk ) {
    $t1 = microtime( true );
    $res = function_exists( 'vergeml_ai_describe_many' ) ? vergeml_ai_describe_many( $chunk ) : array_combine( $chunk, array_map( 'vergeml_ai_describe', $chunk ) );
    foreach ( $chunk as $id ) {
        $r = $res[ $id ] ?? null;
        if ( is_wp_error( $r ) ) { printf( "  #%-6d %-28s %s\n", $id, $r->get_error_code(), $r->get_error_message() ); }
        else { $ok++; printf( "  #%-6d ok  filing=%s\n", $id, isset( $r['filing'] ) ? 'yes' : 'no' ); }
    }
    printf( "  (burst of %d in %.1fs)\n", count( $chunk ), microtime( true ) - $t1 );
}
printf( "ok %d of %d in %.1fs; credits after: %s\n", $ok, count( $ids ), microtime( true ) - $t0, var_export( vergeml_ai_refresh_credits( true ), true ) );
