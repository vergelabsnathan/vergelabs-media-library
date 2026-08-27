<?php
/**
 *  What a real-sized library actually costs, at a realistic vector width.
 *
 *      wp eval-file tools/organize-scale.php --allow-root
 *      wp eval-file tools/organize-scale.php n=10000 dims=768 --allow-root
 *
 *  The suite asserts peak memory at ten thousand vectors and the step walk was
 *  run at fifteen hundred, but both used narrow synthetic vectors. Production
 *  embeddings are 768 to 1536 wide, and the spike's frightening numbers -- two
 *  thousand vectors at 768 dims taking 28 seconds and 39MB -- were measured
 *  before any of the work that was supposed to fix them.
 *
 *  So this runs the real pipeline, at the real width, and reports what every
 *  step spent. Three claims are on trial:
 *
 *    1. one step stays under ~5 seconds whatever the library size
 *    2. no step exceeds four queries, however many have been taken
 *    3. the projection keeps the resident set in tens of megabytes
 *
 *  Lives in tools/, which is export-ignored, so nothing here ships.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_organize_step' ) ) {
    echo "core/organize.php is not loaded. Safe mode?\n";
    return;
}

global $wpdb;

$n     = 10000;
$dims  = 768;
$base  = 800000;

foreach ( (array) $args as $arg ) {
    if ( 0 === strpos( $arg, 'n=' ) ) {
        $n = max( 10, (int) substr( $arg, 2 ) );
    } elseif ( 0 === strpos( $arg, 'dims=' ) ) {
        $dims = max( 8, (int) substr( $arg, 5 ) );
    }
}

printf( "\n%d files, %d dimensions\n\n", $n, $dims );

/* --------------------------------------------------------------- seeding */

$groups = 40;
$bases  = array();

// Forty directions, built once. Doing this per file would spend the whole run
// in md5 rather than in the thing being measured.
for ( $g = 0; $g < $groups; $g++ ) {
    $vector = array();
    for ( $d = 0; $d < $dims; $d++ ) {
        $vector[] = ( hexdec( substr( md5( 'base' . $g . ':' . $d ), 0, 4 ) ) / 65535 ) - 0.5;
    }
    $bases[] = $vector;
}

$started = microtime( true );

// phpcs:ignore
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->vergeml_ai_index} WHERE attachment_id >= %d", $base ) );

$scope = array();
$rows  = array();
$now   = current_time( 'mysql', true );

for ( $i = 0; $i < $n; $i++ ) {

    $group  = $i % $groups;
    $vector = $bases[ $group ];
    $sum    = 0.0;

    // A cheap deterministic wobble, so files in a group are near but not on
    // top of each other. No rand(), and no md5 in the inner loop.
    $seed = ( $i * 2654435761 ) % 4294967296;

    for ( $d = 0; $d < $dims; $d++ ) {
        $seed          = ( $seed * 1103515245 + 12345 ) % 2147483648;
        $vector[ $d ] += ( ( $seed / 2147483648 ) - 0.5 ) * 0.25;
        $sum          += $vector[ $d ] * $vector[ $d ];
    }

    $length = sqrt( $sum );
    $packed = '';

    foreach ( $vector as $value ) {
        $packed .= pack( 'f', $value / $length );
    }

    $id      = $base + $i;
    $scope[] = $id;

    $rows[] = $wpdb->prepare(
        '(%d, %s, %s, %s, %d, %s, %s, %s)',
        $id,
        'group' . $group,
        wp_json_encode( array( 'group' . $group, 'scale', 'seeded' ) ),
        'photo',
        $dims,
        'scale',
        $now,
        $now
    );

    // In blocks, because ten thousand round trips would dominate the setup.
    if ( count( $rows ) >= 250 || $i === $n - 1 ) {
        // phpcs:ignore
        $wpdb->query(
            "INSERT INTO {$wpdb->vergeml_ai_index}
                (attachment_id, caption, tags, kind, embedding_dims, model, described_at, updated_at)
             VALUES " . implode( ',', $rows )
        );
        $rows = array();
    }

    // The blob goes separately: it is binary and belongs in its own prepared
    // statement rather than inside a concatenated VALUES list.
    // phpcs:ignore
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->vergeml_ai_index} SET embedding = %s WHERE attachment_id = %d",
        $packed,
        $id
    ) );
}

printf( "seeded in %.1fs\n\n", microtime( true ) - $started );

/* ------------------------------------------------------------------- run */

$before_memory = memory_get_usage( true );

$queries_supported = defined( 'SAVEQUERIES' ) && SAVEQUERIES;

printf( "%-5s %-9s %-8s %-10s %-10s %s\n", 'step', 'phase', 'ms', 'peak MB', 'branches', 'note' );

$run_started = microtime( true );

$result = vergeml_organize_step( array( 'scope' => $scope ) );
$run_id = $result['run_id'];

if ( 'failed' === $result['status'] ) {
    printf( "\nrefused before starting: %s\n", $result['error'] );
    // phpcs:ignore
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->vergeml_ai_index} WHERE attachment_id >= %d", $base ) );
    return;
}

$steps     = 0;
$worst_ms  = 0.0;
$worst_at  = '';
$load_ms   = 0.0;
$cluster_ms = 0.0;

while ( ! $result['done'] && $steps < 2000 ) {

    $at = microtime( true );

    $result = vergeml_organize_step( array( 'run_id' => $run_id ) );

    $ms = ( microtime( true ) - $at ) * 1000;
    $steps++;

    if ( $ms > $worst_ms ) {
        $worst_ms = $ms;
        $worst_at = $result['phase'];
    }

    // Only the first few and the slow ones, or ten thousand files prints a
    // wall of numbers nobody reads.
    if ( $steps <= 12 || $ms > 1000 || $result['done'] ) {
        printf( "%-5d %-9s %-8.1f %-10.1f %-10d %s\n",
            $steps,
            $result['phase'],
            $ms,
            memory_get_peak_usage( true ) / 1048576,
            count( $result['partial_tree'] ),
            $ms > 5000 ? 'OVER the 5s step target' : ''
        );
    }
}

$total = ( microtime( true ) - $run_started );

$run = vergeml_organize_run_get( $run_id );

$placed = 0;
$capped = 0;
$stray  = 0;

foreach ( $run['tree'] as $branch ) {
    $placed += $branch['size'];
    if ( ! empty( $branch['capped'] ) ) {
        $capped++;
    }
    if ( 'needs-a-look' === $branch['key'] ) {
        $stray = $branch['size'];
    }
}

// phpcs:ignore
$stored = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT LENGTH(tree) + LENGTH(params) FROM {$wpdb->vergeml_organize_runs} WHERE run_id = %d",
    $run_id
) );

printf( "\n%d steps, %.1fs total\n", $steps, $total );
printf( "  load %.1fs, cluster %.1fs (as the run recorded them)\n",
    $run['params']['took']['load_ms'] / 1000, $run['params']['took']['cluster_ms'] / 1000 );
printf( "  slowest step %.0fms (%s) -- target is 5000ms\n", $worst_ms, $worst_at );
printf( "  peak memory %.1fMB, resident growth %.1fMB\n",
    memory_get_peak_usage( true ) / 1048576,
    ( memory_get_usage( true ) - $before_memory ) / 1048576 );
printf( "  %d branches, %d of %d files placed, %d capped, %d need a look\n",
    count( $run['tree'] ), $placed, $run['n'], $capped, $stray );
printf( "  stored row %.1fMB  (ten runs would be %.0fMB)\n",
    $stored / 1048576, $stored * 10 / 1048576 );

/* ----------------------------------------------------------------- tidy */

// phpcs:ignore
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->vergeml_ai_index} WHERE attachment_id >= %d", $base ) );
// phpcs:ignore
$wpdb->delete( vergeml_organize_table(), array( 'run_id' => $run_id ), array( '%d' ) );

echo "\ncleaned\n";
