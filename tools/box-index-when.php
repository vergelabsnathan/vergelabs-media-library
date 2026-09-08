<?php
/**
 *  When the catalogue rows behind the filing baseline were last written.
 *
 *      wp eval-file tools/box-index-when.php --allow-root
 *
 *  The baseline drifted on 2026-09-08: 108 of 641 rows differ, 107 of them by
 *  about 0.0001 in the runner-up's score alone -- same folder, same word, same
 *  winning score -- and one picture (2817) moved from ok to margin. It is not
 *  the request-scoped memo (with and without it the box answers identically),
 *  it is not the service (the same phrase returns the same 512 floats), and it
 *  is not the folders (no profile has been rebuilt since 7 September).
 *
 *  That leaves the pictures. If rows have been described again since the
 *  baseline was taken, their vectors changed and so did every score computed
 *  against them -- which is the library moving under the assertion, not the
 *  matcher being unstable, and a completely different problem.
 *
 *  Reads only. Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$t = $wpdb->vergeml_ai_index;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
$by_day = $wpdb->get_results(
    "SELECT DATE(described_at) AS day, COUNT(*) AS n, MAX(described_at) AS last
       FROM {$t} WHERE error = '' AND embedding IS NOT NULL
   GROUP BY DATE(described_at) ORDER BY day DESC LIMIT 8",
    ARRAY_A
);

printf( "described, by day\n" );
foreach ( (array) $by_day as $r ) {
    printf( "  %-12s %5d   last %s\n", $r['day'], $r['n'], $r['last'] );
}

$since = $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND described_at >= '2026-09-08 00:00:00'" );
printf( "\ndescribed today        %d\n", (int) $since );

foreach ( array( 2817, 1792, 1798, 1781 ) as $id ) {
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT attachment_id, described_at, prompt_hash, model_version, LENGTH(embedding) AS bytes FROM {$t} WHERE attachment_id = %d", $id ), ARRAY_A );
    if ( $row ) {
        printf( "picture %-7d described %s  prompt %s  model %s  %s bytes\n",
            $row['attachment_id'], $row['described_at'], mb_substr( (string) $row['prompt_hash'], 0, 8 ), $row['model_version'], $row['bytes'] );
    }
}
// phpcs:enable

printf( "\nnow (UTC)              %s\n", gmdate( 'Y-m-d H:i:s' ) );
