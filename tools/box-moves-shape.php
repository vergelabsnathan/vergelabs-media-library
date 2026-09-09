<?php
/**
 *  What shape of row does each way of writing a move produce?
 *
 *      wp eval-file tools/box-moves-shape.php --allow-root
 *
 *  Batch 18 holds 109 rows whose six reason columns are all at their column
 *  defaults -- why '', score NULL, runner_up 0, runner_score NULL,
 *  prompt_hash '', model_version ''. Two things could put a row in that state
 *  and they mean opposite things:
 *
 *      the writer omitted the columns   -- the row predates them, and an empty
 *                                         why means what the schema says it
 *                                         means
 *      the writer passed no reason      -- the row is a live defect and the
 *                                         write path is dropping reasons now
 *
 *  So write one of each into a batch id no batch has, read the three rows back
 *  whole, and delete them. Nothing real is touched: the batch id is not a
 *  batch, the attachment ids are not attachments, and the rows are gone by the
 *  last line -- which is asserted rather than assumed.
 *
 *  Read-only in effect. No service call, no credits.
 *
 *  Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

if ( ! function_exists( 'vergeml_librarian_moves_insert' ) ) {
    echo "core/librarian.php is not loaded\n";
    return;
}

$scratch = 999998;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$before = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id = %d",
    $scratch
) );

if ( $before ) {
    printf( "batch %d already holds %d rows -- refusing to write beside them\n", $scratch, $before );
    return;
}

/*
 *  Three rows, the three ways a move is written today.
 *
 *  The first is the shape core/folder-talk.php hands over for a placement the
 *  matcher made; the second is what it hands over for a placement a plan made
 *  and nothing scored; the third is the four-element row the insert's own
 *  header says still works, which is the shape a build without the columns
 *  would have produced.
 */
$fake_row = array( 'prompt_hash' => 'abc123def456', 'model_version' => 'anthropic/claude-haiku-4.5' );

$moves = array(
    array( $scratch, 900001, 1781, 0, vergeml_talk_reason( array( 'ok', 0.812345, 1797, 0.441234 ), $fake_row ) ),
    array( $scratch, 900002, 1781, 0, vergeml_talk_reason( null, $fake_row ) ),
    array( $scratch, 900003, 1781, 0 ),
);

vergeml_librarian_moves_insert( $moves );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$rows = (array) $wpdb->get_results( $wpdb->prepare(
    "SELECT attachment_id, why, score, runner_up, runner_score, prompt_hash, model_version
       FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id = %d ORDER BY attachment_id ASC",
    $scratch
), ARRAY_A );

$names = array(
    900001 => 'five elements, a matcher reason',
    900002 => 'five elements, no packed scores',
    900003 => 'four elements, no reason at all',
);

printf( "rows written: %d\n\n", count( $rows ) );

foreach ( $rows as $r ) {
    printf( "%s\n", isset( $names[ (int) $r['attachment_id'] ] ) ? $names[ (int) $r['attachment_id'] ] : (string) $r['attachment_id'] );
    foreach ( array( 'why', 'score', 'runner_up', 'runner_score', 'prompt_hash', 'model_version' ) as $c ) {
        printf( "    %-14s %s\n", $c, null === $r[ $c ] ? 'NULL' : "'" . $r[ $c ] . "'" );
    }
    echo "\n";
}

echo "batch 18's rows, for comparison:\n";
echo "    why            ''\n";
echo "    score          NULL\n";
echo "    runner_up      '0'\n";
echo "    runner_score   NULL\n";
echo "    prompt_hash    ''\n";
echo "    model_version  ''\n\n";

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id = %d",
    $scratch
) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$left = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id = %d",
    $scratch
) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_moves}" );

printf( "scratch rows left behind: %d\n", $left );
printf( "rows in the table now:    %d\n", $total );
