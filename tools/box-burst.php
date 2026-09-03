<?php
/* Sixteen at once THROUGH THE PLUGIN, printing what each one came back as. */
global $wpdb; $t = $wpdb->prefix . 'vergeml_ai_index';
$ids = array_map( 'intval', $wpdb->get_col( "SELECT attachment_id FROM {$t} WHERE model <> 'mock' AND error = '' AND described_at < UTC_TIMESTAMP() - INTERVAL 20 MINUTE ORDER BY described_at ASC LIMIT 16" ) );
printf( "parallel setting: %d   ids: %d\n", vergeml_ai_parallel(), count( $ids ) );
$t0 = microtime( true ); $res = vergeml_ai_describe_many( $ids ); $wall = microtime( true ) - $t0;
$codes = array();
foreach ( $ids as $id ) { $r = $res[ $id ] ?? null; $c = is_wp_error( $r ) ? $r->get_error_code() : 'ok'; $codes[ $c ] = ( $codes[ $c ] ?? 0 ) + 1;
    if ( is_wp_error( $r ) ) { printf( "  #%-6d %s -- %s\n", $id, $c, $r->get_error_message() ); } }
printf( "in %.1fs: %s\n", $wall, wp_json_encode( $codes ) );
