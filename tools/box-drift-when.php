<?php
/**
 *  When did the matcher's inputs last change?
 *
 *      wp eval-file tools/box-drift-when.php --allow-root
 *
 *  tests/tree/filing-baseline.txt drifted from the box and nobody could say
 *  why. Two runs a minute apart are byte-identical; two runs a day apart are
 *  not. So something the matcher reads is written between them, and there are
 *  only three candidates:
 *
 *      the picture's own vector   -- vergeml_ai_index.embedding, per picture
 *      the folder's vector        -- term meta _vergeml_profile, per folder
 *      the class phrase's vector  -- a transient, per phrase, from the service
 *
 *  The first two carry their own timestamps and Phase 2 read them. The third
 *  does not -- but WordPress keeps `_transient_timeout_<slot>` beside every
 *  transient, and that is the write moment plus the lifetime. So the moment a
 *  phrase vector was fetched is recoverable to the second, for every phrase
 *  the box holds, without asking the service anything.
 *
 *  Read-only. It writes nothing, fetches nothing, and spends nothing.
 *
 *  Lives in tools/, which is export-ignored, so nothing here ships.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

echo "== phrase vectors: when each was fetched ==\n";

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$timeouts = (array) $wpdb->get_results(
    "SELECT option_name, option_value
       FROM {$wpdb->options}
      WHERE option_name LIKE '\_transient\_timeout\_vergeml\_qv2\_%'
   ORDER BY option_value + 0 ASC",
    ARRAY_A
);

printf( "phrase vectors held: %d\n", count( $timeouts ) );

if ( $timeouts ) {

    /*
     *  The lifetime is not recorded, only the deadline. It has been an hour
     *  and is now a week; both are visible in the spread of deadlines, so the
     *  fetch moment is reported for each of the two readings and the one that
     *  lands in the past is the wrong one.
     */
    $week = 7 * DAY_IN_SECONDS;
    $hour = HOUR_IN_SECONDS;

    $buckets = array();
    foreach ( $timeouts as $t ) {
        $deadline = (int) $t['option_value'];
        $written  = $deadline - $week;
        $buckets[ gmdate( 'Y-m-d', $written ) ][] = $written;
    }
    ksort( $buckets );

    printf( "now is %s UTC\n\n", gmdate( 'Y-m-d H:i:s' ) );
    echo "reading every deadline as write + 7 days:\n";
    foreach ( $buckets as $day => $whens ) {
        sort( $whens );
        printf(
            "  %s  %4d phrases   first %s   last %s\n",
            $day,
            count( $whens ),
            gmdate( 'H:i:s', $whens[0] ),
            gmdate( 'H:i:s', end( $whens ) )
        );
    }

    $earliest = (int) $timeouts[0]['option_value'];
    $latest   = (int) $timeouts[ count( $timeouts ) - 1 ]['option_value'];
    printf(
        "\ndeadlines run %s .. %s UTC (a spread of %.1f days)\n",
        gmdate( 'Y-m-d H:i:s', $earliest ),
        gmdate( 'Y-m-d H:i:s', $latest ),
        ( $latest - $earliest ) / DAY_IN_SECONDS
    );
    printf( "the shortest deadline is %.2f hours from now\n", ( $earliest - time() ) / HOUR_IN_SECONDS );
    printf( "an hour's lifetime would put the oldest write at %s\n", gmdate( 'Y-m-d H:i:s', $earliest - $hour ) );
}

echo "\n== phrase vectors that have already gone ==\n";

/*
 *  A transient whose deadline has passed is not deleted until something asks
 *  for it. One that has no deadline row at all was collected. Both mean the
 *  next ask goes to the service.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$values = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->options}
      WHERE option_name LIKE '\_transient\_vergeml\_qv2\_%'"
);
printf( "vector rows %d, deadline rows %d\n", $values, count( $timeouts ) );

$expired = 0;
foreach ( $timeouts as $t ) {
    if ( (int) $t['option_value'] <= time() ) {
        $expired++;
    }
}
printf( "already past their deadline: %d\n", $expired );

echo "\n== folder profiles ==\n";

$taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';
$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
$terms    = is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
sort( $terms );

printf( "folders %d\n", count( $terms ) );
foreach ( $terms as $tid ) {
    $p = get_term_meta( $tid, '_vergeml_profile', true );
    if ( ! is_array( $p ) ) {
        printf( "  %5d  (no profile)\n", $tid );
        continue;
    }
    printf(
        "  %5d  v%d  built %s  vector %s  classes %s\n",
        $tid,
        isset( $p['version'] ) ? (int) $p['version'] : 0,
        isset( $p['built_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $p['built_at'] ) : '?',
        substr( md5( wp_json_encode( isset( $p['vector'] ) ? $p['vector'] : array() ) ), 0, 8 ),
        implode( ',', array_slice( (array) ( isset( $p['classes'] ) ? $p['classes'] : array() ), 0, 4 ) )
    );
}

echo "\n== picture vectors ==\n";

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$idx = (array) $wpdb->get_row(
    "SELECT COUNT(*) AS n, MAX(described_at) AS newest
       FROM {$wpdb->vergeml_ai_index}
      WHERE error = '' AND embedding IS NOT NULL",
    ARRAY_A
);
printf( "described pictures %d, newest description %s\n", (int) $idx['n'], (string) $idx['newest'] );
