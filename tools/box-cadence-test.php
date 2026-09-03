<?php
/*
 *  How fast does the background run go now, and does the image bound do
 *  anything? Forty-eight real pictures are dropped from the index so they
 *  count as unindexed, a run is started, and it is nudged every five seconds
 *  until done. Costs 48 credits. The rows come back described.
 */

global $wpdb;
$t = $wpdb->prefix . 'vergeml_ai_index';

$ids = array_map( 'intval', $wpdb->get_col(
    "SELECT attachment_id FROM {$t} WHERE model <> 'mock' AND error = '' ORDER BY attachment_id DESC LIMIT 48"
) );

printf( "credits before: %s\n", var_export( vergeml_ai_refresh_credits( true ), true ) );

// ---- does the outgoing image get bounded?

$shrunk = 0; $sent = 0; $orig = 0; $biggest_in = 0; $biggest_out = 0;

foreach ( array_slice( $ids, 0, 12 ) as $id ) {
    $f = get_attached_file( $id );
    $o = $f && file_exists( $f ) ? (int) filesize( $f ) : 0;
    $p = vergeml_ai_image_payload( $id );
    if ( is_wp_error( $p ) ) { continue; }
    $b = (int) ( ( strlen( $p ) - strpos( $p, ',' ) ) * 0.75 );
    $sent += $b; $orig += $o;
    if ( $o > $biggest_in ) { $biggest_in = $o; $biggest_out = $b; }
    if ( $b < $o * 0.9 ) { $shrunk++; }
}

printf( "payload check on 12: originals %d KB -> sent %d KB; %d shrunk; largest %d KB -> %d KB\n",
    (int) ( $orig / 1024 ), (int) ( $sent / 1024 ), $shrunk, (int) ( $biggest_in / 1024 ), (int) ( $biggest_out / 1024 ) );

// ---- the run

$wpdb->query( "DELETE FROM {$t} WHERE attachment_id IN (" . implode( ',', $ids ) . ")" );

$r = vergeml_ai_run_start( 'unindexed', false );
if ( is_wp_error( $r ) ) { echo 'could not start: ' . $r->get_error_message() . "\n"; return; }

$t0 = time(); $last = -1;

while ( time() - $t0 < 12 * 60 ) {
    vergeml_ai_run_nudge();
    sleep( 5 );
    $s = vergeml_ai_run_state();
    $d = (int) $s['described'];
    if ( $d !== $last ) {
        printf( "  %4ds  described %3d  failed %2d  remaining %3d\n", time() - $t0, $d, (int) $s['failed'], (int) $s['remaining'] );
        $last = $d;
    }
    if ( empty( $s['active'] ) ) { break; }
}

$el = max( 1, time() - $t0 );
$s  = vergeml_ai_run_state();

printf( "done: %d described in %ds = %.1f per minute (was 12-14)  failed %d  credits after: %s\n",
    (int) $s['described'], $el, 60 * (int) $s['described'] / $el, (int) $s['failed'], var_export( vergeml_ai_refresh_credits( true ), true ) );
