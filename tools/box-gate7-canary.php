<?php
/**
 *  Marker rows, so gate 7's restore has something to lose.
 *
 *      ACTION=plant  wp eval-file tools/box-gate7-canary.php --allow-root
 *      ACTION=check  wp eval-file tools/box-gate7-canary.php --allow-root
 *      ACTION=clear  wp eval-file tools/box-gate7-canary.php --allow-root
 *
 *  tests/librarian/gate7-schema.php drops both librarian tables four times.
 *  Until 2026-09-09 it dropped the site's own, and one run of it on the box
 *  destroyed 109 move rows and two batches with no way back. It now renames
 *  them aside and back, and this is how that is proved rather than described:
 *  plant a batch and three moves whose values are known, run the suite, and
 *  read them back whole.
 *
 *  With the fix off, `check` reports them gone.
 *
 *  Everything it writes is prefixed zz and carries batch id 999996, which no
 *  batch has. Read-only in effect once `clear` has run, which is asserted.
 *
 *  Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$action = getenv( 'ACTION' ) ? (string) getenv( 'ACTION' ) : 'check';
$batch  = 999996;

$want = array(
    array( 'attachment_id' => 999101, 'term_id' => 991, 'why' => 'ok',     'nearest' => 0 ),
    array( 'attachment_id' => 999102, 'term_id' => 0,   'why' => 'margin', 'nearest' => 992 ),
    array( 'attachment_id' => 999103, 'term_id' => 0,   'why' => 'floor',  'nearest' => 993 ),
);

if ( 'plant' === $action ) {

    $now = current_time( 'mysql', true );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->insert( $wpdb->vergeml_librarian_batches, array(
        'batch_id'    => $batch,
        'run_id'      => 0,
        'scheme'      => 'refile',
        'status'      => 'running',
        'step_cursor' => 0,
        'done_n'      => 0,
        'skip_n'      => 0,
        'params'      => wp_json_encode( array( 'source' => 'zz gate7 canary' ) ),
        'reason'      => '',
        'created_at'  => $now,
        'updated_at'  => $now,
    ), array( '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ) );

    foreach ( $want as $row ) {
        vergeml_librarian_moves_insert( array( array( $batch, $row['attachment_id'], $row['term_id'], 0, array(
            'why'           => $row['why'],
            'score'         => 0.5,
            'runner_up'     => 994,
            'runner_score'  => 0.25,
            'prompt_hash'   => 'zzcanary',
            'model_version' => 'zz/canary',
            'nearest'       => $row['nearest'],
        ) ) ) );
    }

    echo "planted\n";
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$have_batch = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_batches} WHERE batch_id = %d",
    $batch
) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$rows = (array) $wpdb->get_results( $wpdb->prepare(
    "SELECT attachment_id, term_id, why, nearest, score, prompt_hash FROM {$wpdb->vergeml_librarian_moves}
      WHERE batch_id = %d ORDER BY attachment_id ASC",
    $batch
), ARRAY_A );

printf( "batch rows: %d of 1\n", $have_batch );
printf( "move rows:  %d of 3\n", count( $rows ) );

$ok = 1 === $have_batch && 3 === count( $rows );

foreach ( $rows as $i => $r ) {
    printf(
        "  %d  term %-4d why %-7s nearest %-4d score %s prompt %s\n",
        (int) $r['attachment_id'],
        (int) $r['term_id'],
        (string) $r['why'],
        (int) $r['nearest'],
        (string) $r['score'],
        (string) $r['prompt_hash']
    );

    if ( ! isset( $want[ $i ] )
        || (int) $r['attachment_id'] !== (int) $want[ $i ]['attachment_id']
        || (int) $r['term_id'] !== (int) $want[ $i ]['term_id']
        || (string) $r['why'] !== (string) $want[ $i ]['why']
        || (int) $r['nearest'] !== (int) $want[ $i ]['nearest'] ) {
        $ok = false;
    }
}

printf( "\n%s\n", $ok ? 'every marker row came back exactly as it was written' : 'MARKER ROWS ARE GONE OR CHANGED' );

if ( 'clear' === $action ) {

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( $wpdb->vergeml_librarian_moves, array( 'batch_id' => $batch ), array( '%d' ) );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( $wpdb->vergeml_librarian_batches, array( 'batch_id' => $batch ), array( '%d' ) );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $left = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id = %d",
        $batch
    ) );

    printf( "scratch rows left behind: %d\n", $left );
}
