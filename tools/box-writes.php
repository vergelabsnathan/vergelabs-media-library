<?php
/*
 *  When did the last run actually write? The cadence test only sees the
 *  run-state option; the described_at column is what every process agrees on.
 *  Read-only.
 */

global $wpdb;
$t = $wpdb->prefix . 'vergeml_ai_index';

echo "=== run state\n";
wp_cache_delete( VERGEML_AI_RUN_OPTION, 'options' );
foreach ( vergeml_ai_run_state() as $k => $v ) {
    printf( "  %-12s %s\n", $k . ':', is_scalar( $v ) || null === $v ? var_export( $v, true ) : wp_json_encode( $v ) );
}
printf( "  cron next:   %s\n", ( $n = wp_next_scheduled( 'vergeml_ai_run_tick' ) ) ? gmdate( 'H:i:s', $n ) . ' UTC' : 'none' );
printf( "  now:         %s UTC\n", gmdate( 'H:i:s' ) );

echo "\n=== writes in the last 30 minutes, per 30 seconds (UTC)\n";
$rows = $wpdb->get_results(
    "SELECT FROM_UNIXTIME( FLOOR( UNIX_TIMESTAMP( described_at ) / 30 ) * 30 ) AS slot, COUNT(*) AS n
       FROM {$t} WHERE described_at >= UTC_TIMESTAMP() - INTERVAL 30 MINUTE
      GROUP BY slot ORDER BY slot",
    ARRAY_A
);
foreach ( $rows as $r ) { printf( "  %s  %3d  %s\n", substr( $r['slot'], 11 ), (int) $r['n'], str_repeat( '#', (int) $r['n'] ) ); }
if ( ! $rows ) { echo "  (none)\n"; }

echo "\n=== the loopback the nudge uses\n";
$url = site_url( 'wp-cron.php' );
$t0  = microtime( true );
$r   = wp_remote_get( add_query_arg( 'doing_wp_cron', sprintf( '%.22F', microtime( true ) ), $url ), array( 'timeout' => 30, 'sslverify' => false ) );
printf( "  %s -> %s in %.2fs\n", $url, is_wp_error( $r ) ? $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $r ), microtime( true ) - $t0 );
printf( "  DISABLE_WP_CRON: %s   ALTERNATE_WP_CRON: %s\n", defined( 'DISABLE_WP_CRON' ) ? var_export( DISABLE_WP_CRON, true ) : 'undefined', defined( 'ALTERNATE_WP_CRON' ) ? var_export( ALTERNATE_WP_CRON, true ) : 'undefined' );
