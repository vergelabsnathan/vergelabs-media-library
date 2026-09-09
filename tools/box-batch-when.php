<?php
/**
 *  What each batch of moves says about itself.
 *
 *      wp eval-file tools/box-batch-when.php --allow-root
 *
 *  Batch 18 holds 109 rows with an empty reason, undone = 0, dated
 *  2026-09-08 15:56 -- after the columns that hold a reason shipped. All four
 *  callers pass a reason today, so nothing now writes an empty one, and the
 *  question is which build wrote those and when it was on the box.
 *
 *  This prints every batch beside the rows it carries: how many have a reason
 *  and how many do not, how many are marked undone, and whether the pictures
 *  are still in the folders the rows claim. That last one is the difference
 *  between "undone = 0 and it was never undone" and "undone = 0 and it was".
 *
 *  Read-only.
 *
 *  Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$batches = (array) $wpdb->get_results(
    "SELECT * FROM {$wpdb->vergeml_librarian_batches} ORDER BY batch_id ASC",
    ARRAY_A
);

printf( "%-4s %-20s %-10s %-10s %-26s %s\n", 'id', 'created', 'scheme', 'status', 'params', 'rows' );

foreach ( $batches as $b ) {

    $id = (int) $b['batch_id'];

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
    $stat = (array) $wpdb->get_row(
        $wpdb->prepare(
            "SELECT COUNT(*) AS n,
                    SUM(CASE WHEN why IS NULL OR why = '' THEN 1 ELSE 0 END) AS blank,
                    SUM(CASE WHEN undone = 1 THEN 1 ELSE 0 END) AS undone,
                    SUM(CASE WHEN term_created = 1 THEN 1 ELSE 0 END) AS made,
                    SUM(CASE WHEN term_id = 0 THEN 1 ELSE 0 END) AS abstained
               FROM {$wpdb->vergeml_librarian_moves}
              WHERE batch_id = %d",
            $id
        ),
        ARRAY_A
    );

    $params = (string) ( isset( $b['params'] ) ? $b['params'] : '' );

    printf(
        "%-4d %-20s %-10s %-10s %-26s n=%d blank=%d undone=%d made=%d term0=%d\n",
        $id,
        (string) $b['created_at'],
        (string) $b['scheme'],
        (string) $b['status'],
        mb_strlen( $params ) > 25 ? mb_substr( $params, 0, 22 ) . '...' : $params,
        (int) $stat['n'],
        (int) $stat['blank'],
        (int) $stat['undone'],
        (int) $stat['made'],
        (int) $stat['abstained']
    );
}

echo "\n== batch 18, in detail ==\n";

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$rows = (array) $wpdb->get_results(
    "SELECT attachment_id, term_id, term_created, undone, why, score, runner_up, runner_score, prompt_hash, model_version
       FROM {$wpdb->vergeml_librarian_moves}
      WHERE batch_id = 18
   ORDER BY attachment_id ASC",
    ARRAY_A
);

printf( "rows %d\n", count( $rows ) );

if ( $rows ) {

    $terms = array();
    $still = 0;
    $gone  = 0;

    foreach ( $rows as $r ) {
        $terms[ (int) $r['term_id'] ] = isset( $terms[ (int) $r['term_id'] ] ) ? $terms[ (int) $r['term_id'] ] + 1 : 1;

        $has = wp_get_object_terms( (int) $r['attachment_id'], $taxonomy, array( 'fields' => 'ids' ) );
        $has = is_wp_error( $has ) ? array() : array_map( 'intval', $has );

        if ( in_array( (int) $r['term_id'], $has, true ) ) {
            $still++;
        } else {
            $gone++;
        }
    }

    echo "folders claimed:\n";
    foreach ( $terms as $tid => $n ) {
        $t = get_term( $tid, $taxonomy );
        printf( "  %6d  %-34s %d rows%s\n", $tid, ( $t && ! is_wp_error( $t ) ) ? $t->name : '(no such folder)', $n, ( $t && ! is_wp_error( $t ) ) ? '' : '  <- deleted' );
    }

    printf( "\npictures still in the folder the row claims: %d\n", $still );
    printf( "pictures no longer in it:                    %d\n", $gone );

    $first = $rows[0];
    echo "\nthe first row, whole:\n";
    foreach ( $first as $k => $v ) {
        printf( "  %-14s %s\n", $k, null === $v ? 'NULL' : "'" . $v . "'" );
    }
}

echo "\n== the reason columns, across the whole table ==\n";

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$byWhy = (array) $wpdb->get_results(
    "SELECT COALESCE(NULLIF(why, ''), '(empty)') AS w, COUNT(*) AS n,
            MIN(batch_id) AS lo, MAX(batch_id) AS hi
       FROM {$wpdb->vergeml_librarian_moves}
   GROUP BY w ORDER BY n DESC",
    ARRAY_A
);

foreach ( $byWhy as $r ) {
    printf( "  %-10s %5d rows, batches %d..%d\n", $r['w'], (int) $r['n'], (int) $r['lo'], (int) $r['hi'] );
}
