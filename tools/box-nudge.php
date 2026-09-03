<?php
/*
 *  Keep a background run moving on a box nobody is browsing.
 *
 *  WP-Cron fires on page loads. The run's own loopback nudge is fired by
 *  whatever started the run, and when that process ends the run waits for a
 *  visitor. This nudges every ten seconds -- the run is bounded by cron
 *  cadence, not by the model -- and reports progress until both scopes are
 *  done or twenty-five minutes have passed.
 */
$t0 = time(); $last = -1;
while ( time() - $t0 < 25 * 60 ) {
    $s = vergeml_ai_run_state();
    if ( empty( $s['active'] ) ) {
        $next = vergeml_ai_pending_count( 'unindexed' );
        if ( $next > 0 ) { $r = vergeml_ai_run_start( 'unindexed', false ); printf( "stale done; unindexed run started: total %d\n", is_wp_error( $r ) ? -1 : (int) $r['total'] ); continue; }
        echo "nothing active and nothing pending -- done\n"; break;
    }
    vergeml_ai_run_nudge();
    $done = (int) $s['described'] + (int) $s['failed'];
    if ( $done !== $last ) { printf( "  %5ds  %-9s described %4d  failed %3d  remaining %4d\n", time() - $t0, $s['scope'], (int) $s['described'], (int) $s['failed'], (int) $s['remaining'] ); $last = $done; }
    sleep( 10 );
}
$s = vergeml_ai_run_state();
printf( "end: active=%s scope=%s described=%d failed=%d remaining=%d  credits=%s\n", empty( $s['active'] ) ? 'no' : 'yes', $s['scope'], (int) $s['described'], (int) $s['failed'], (int) $s['remaining'], var_export( vergeml_ai_refresh_credits( true ), true ) );
