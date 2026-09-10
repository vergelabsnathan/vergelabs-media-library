<?php
/**
 *  What a describe actually costs, measured on this library.
 *
 *  The pricing ladder was derived from "about EUR 0.002 all in" per describe
 *  (service/lib/credits.ts). The token measurement in service/lib/anthropic.ts
 *  says $0.0043 on standard Haiku, and a week of real spend said $0.0058 --
 *  two to three times the figure the prices were built on. The difference is
 *  escalation: a describe whose answer fails its checks is retried once on a
 *  much more expensive model, and how often that happens depends on the
 *  pictures, not on us.
 *
 *  So this describes a sample for real and reports what came back, including
 *  which model answered for each picture -- which is the escalation rate, and
 *  the thing no estimate can supply.
 *
 *      scp tools/box-describe-cost.php root@box:/tmp/vgml-cost.php
 *      VGML_N=50 bash tools/box-describe-cost.sh
 *
 *  IT SPENDS REAL MONEY AND KEEPS NOTHING.
 *
 *  vergeml_ai_describe() asks the service and RETURNS the answer; writing it
 *  to the index is a separate step this script does not take. So a run of a
 *  hundred pictures costs a hundred credits and about fifty cents, and leaves
 *  the library exactly as undescribed as it found it. That is fine for
 *  measuring what a describe costs, which is the only thing this is for, and
 *  it is not fine as a way to describe a library -- use the AI screen for
 *  that. Learned the expensive way on 2026-09-10: 99 pictures, $0.48, and
 *  0 rows in the index afterwards.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$n     = max( 1, min( 200, (int) ( getenv( 'VGML_N' ) ? getenv( 'VGML_N' ) : 50 ) ) );
$table = vergeml_index_table();

$ids = (array) $wpdb->get_col( $wpdb->prepare(
    "SELECT p.ID FROM {$wpdb->posts} p
      WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
        AND p.post_mime_type LIKE 'image/%'
        AND NOT EXISTS ( SELECT 1 FROM {$table} x WHERE x.attachment_id = p.ID )
      ORDER BY RAND() LIMIT %d",
    $n
) );

if ( ! $ids ) {
    echo "nothing left to describe\n";
    return;
}

printf( "describing %d pictures, one credit each\n\n", count( $ids ) );

$t0   = microtime( true );
$done = 0;
$fail = 0;

foreach ( $ids as $id ) {
    $r = vergeml_ai_describe( (int) $id );
    if ( is_wp_error( $r ) ) {
        $fail++;
        printf( "  %-7d FAILED  %s\n", (int) $id, $r->get_error_message() );
        continue;
    }
    $done++;
    if ( 0 === $done % 10 ) {
        printf( "  %d of %d, %.0fs\n", $done, count( $ids ), microtime( true ) - $t0 );
    }
}

$secs = microtime( true ) - $t0;

printf( "\n%d described, %d failed, in %.0fs (%.1f a minute)\n", $done, $fail, $secs, $done / max( 0.001, $secs / 60 ) );

/*
 *  Which model answered. A row naming the escalation model is a picture the
 *  cheap path could not describe well enough -- and it cost both calls, not
 *  one.
 */
$holes = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$by_model = (array) $wpdb->get_results( $wpdb->prepare(
    "SELECT model_version, COUNT(*) n FROM {$table}
      WHERE attachment_id IN ( {$holes} ) GROUP BY model_version ORDER BY n DESC",
    array_map( 'intval', $ids )
), ARRAY_A );

echo "\nwhich model answered\n";
$escalated = 0;
foreach ( $by_model as $r ) {
    $model = '' === (string) $r['model_version'] ? '(not recorded)' : (string) $r['model_version'];
    printf( "  %-42s %d\n", $model, (int) $r['n'] );
    if ( false !== stripos( $model, 'opus' ) || false !== stripos( $model, 'sonnet' ) ) {
        $escalated += (int) $r['n'];
    }
}

printf( "\nescalated to a dearer model: %d of %d (%.0f%%)\n", $escalated, $done, $done ? 100 * $escalated / $done : 0 );

/*
 *  The arithmetic, from OpenRouter's published prices and the token counts
 *  measured in service/lib/anthropic.ts (3,019 in / 254 out per describe).
 *  Haiku 4.5 is $1/M in and $5/M out; Opus 5 is $15/M in and $75/M out.
 */
$haiku = ( 3019 * 1.0 + 254 * 5.0 ) / 1000000;
$opus  = ( 3019 * 15.0 + 254 * 75.0 ) / 1000000;

$cost = $done * $haiku + $escalated * $opus;

printf( "\nestimated provider cost\n" );
printf( "  every picture, cheap path      %d x $%.5f = $%.3f\n", $done, $haiku, $done * $haiku );
printf( "  the escalations, on top        %d x $%.5f = $%.3f\n", $escalated, $opus, $escalated * $opus );
printf( "  ------------------------------------------------\n" );
printf( "  $%.3f for %d pictures = $%.5f each\n", $cost, $done, $done ? $cost / $done : 0 );
printf( "  a thousand of these would be $%.2f\n", $done ? 1000 * $cost / $done : 0 );

echo "\nCheck this against OpenRouter's own activity for the same minutes -- that\n";
echo "is the bank statement, and this is only arithmetic over token counts.\n";
