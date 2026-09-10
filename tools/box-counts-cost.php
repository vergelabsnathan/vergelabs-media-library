<?php
/**
 *  What vergeml_smart_counts() costs, cold and warm, and what it answers.
 *
 *  The acceptance check for .claude/plans/media-library-counts-at-scale.md.
 *  Three things, in this order of importance:
 *
 *    1. the numbers, which must not change;
 *    2. the query count, which is a property of the algorithm and transfers
 *       between Playground and real MariaDB (tests/perf/bench.mjs argues this);
 *    3. the milliseconds, which are a canary and depend on the box.
 *
 *      scp tools/box-counts-cost.php root@box:/tmp/vgml-counts.php
 *      bash tools/box-counts-cost.sh
 *
 *  Read-only apart from the cache it deliberately clears, which is what makes
 *  the cold measurement cold.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

if ( ! function_exists( 'vergeml_smart_counts' ) ) {
    echo "this build has no vergeml_smart_counts()\n";
    return;
}

printf( "attachments: %s\n", number_format( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit'" ) ) );
printf( "threshold:   %s\n", number_format( (int) apply_filters( 'vergeml_smart_counts_threshold', 50000 ) ) );
printf( "over it:     %s\n\n", vergeml_smart_counts_too_big() ? 'yes -- nothing is counted on a page load' : 'no' );

$run = function ( $label, $fresh = false ) use ( $wpdb ) {

    $q0 = (int) $wpdb->num_queries;
    $t0 = microtime( true );

    $counts = vergeml_smart_counts( $fresh );

    $ms      = ( microtime( true ) - $t0 ) * 1000;
    $queries = (int) $wpdb->num_queries - $q0;

    printf( "%-34s %8.1f ms   %3d queries\n", $label, $ms, $queries );

    return $counts;
};

// Cold: nothing kept anywhere.
vergeml_smart_counts_forget();
wp_cache_flush();

$cold = $run( 'cold (nothing cached)' );

// Warm within the same request is the static; warm across requests is the store.
$run( 'warm (same request)' );

// Drop the static the only way a script can: ask for fresh, then read again.
$stored = vergeml_smart_counts_stored( get_current_blog_id() );
printf( "%-34s %8s      %s\n", 'kept for the next page load', '', is_array( $stored ) ? 'yes' : 'NO -- nothing was stored' );

echo "\nthe numbers themselves\n";
foreach ( (array) $cold as $key => $value ) {
    printf( "  %-14s %s\n", $key, null === $value ? '(not looked)' : number_format( (int) $value ) );
}

/*
 *  And the true numbers, computed the expensive way, so a cheap answer can be
 *  checked rather than trusted. This is the slow part of this script and it is
 *  the point of it.
 */
if ( getenv( 'VGML_COUNTS_VERIFY' ) ) {

    echo "\nthe same numbers, computed the expensive way\n";

    vergeml_smart_counts_forget();
    add_filter( 'vergeml_smart_counts_threshold', function () { return PHP_INT_MAX; } );

    $t0   = microtime( true );
    $true = vergeml_smart_counts( true );
    printf( "  (took %.0f ms)\n", ( microtime( true ) - $t0 ) * 1000 );

    foreach ( (array) $true as $key => $value ) {
        printf( "  %-14s %s\n", $key, null === $value ? '(not looked)' : number_format( (int) $value ) );
    }
}
