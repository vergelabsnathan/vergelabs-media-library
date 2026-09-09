<?php
/**
 *  Is the moves insert failing, or did it fail once in a window that closed?
 *
 *      wp eval-file tools/box-batch-23.php --allow-root
 *
 *  Batch 23 -- refile, created 2026-09-09 06:35:37 -- holds no rows.
 *  vergeml_talk_trail_write() returns before asking for a batch when the trail
 *  is empty, so a batch that exists with no rows means the batch was made and
 *  the insert wrote nothing. Phase 4 is about to put three more columns on that
 *  table, and three columns on a table whose writes fail silently is worse than
 *  none -- so this runs before any edit.
 *
 *  Two stories fit, and they need opposite responses:
 *
 *      the insert is broken now      -- that is the whole phase, and the
 *                                       schema change waits
 *      the insert met a table that
 *      did not have the columns yet  -- the write path is sound and the
 *                                       deploy-then-upgrade order is the
 *                                       defect
 *
 *  So: what the option says the schema is, what the table actually has, when
 *  the table was last altered, and then the real thing -- one row in the exact
 *  shape vergeml_talk_trail_write() builds, through the real
 *  vergeml_librarian_moves_insert(), with the database's own error printed
 *  rather than swallowed. Written into a batch id no batch has, read back, and
 *  deleted, which is asserted rather than assumed.
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

echo "== what the option says, and what the code expects ==\n";

$state = vergeml_librarian_state();

printf( "  option schema      %s\n", isset( $state['schema'] ) ? (string) $state['schema'] : '(none)' );
printf( "  code expects       %d\n", VERGEML_LIBRARIAN_VERSION );
printf( "  they agree         %s\n", ( isset( $state['schema'] ) && VERGEML_LIBRARIAN_VERSION === (int) $state['schema'] ) ? 'yes' : 'NO' );

echo "\n== what the moves table actually has ==\n";

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$cols = (array) $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->vergeml_librarian_moves}", ARRAY_A );

$have = array();

foreach ( $cols as $c ) {
    $have[] = (string) $c['Field'];
    printf( "  %-16s %-24s %-5s %s\n", $c['Field'], $c['Type'], $c['Null'], null === $c['Default'] ? 'NULL' : "'" . $c['Default'] . "'" );
}

$wanted = array( 'why', 'score', 'runner_up', 'runner_score', 'prompt_hash', 'model_version' );
$absent = array_values( array_diff( $wanted, $have ) );

printf( "\n  Phase 1's six columns absent: %s\n", $absent ? implode( ', ', $absent ) : 'none' );

echo "\n== the batches table ==\n";

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$bcols = (array) $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->vergeml_librarian_batches}", ARRAY_A );

$bhave = array();

foreach ( $bcols as $c ) {
    $bhave[] = (string) $c['Field'];
}

printf( "  columns: %s\n", implode( ', ', $bhave ) );
printf( "  Phase 4 would add: %s\n", implode( ', ', array_values( array_diff( array( 'user_id', 'approved_at' ), $bhave ) ) ) );

echo "\n== when the tables were made and last written ==\n";

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$times = (array) $wpdb->get_results( $wpdb->prepare(
    "SELECT TABLE_NAME, CREATE_TIME, UPDATE_TIME, TABLE_ROWS
       FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ( %s, %s )",
    $wpdb->vergeml_librarian_moves,
    $wpdb->vergeml_librarian_batches
), ARRAY_A );

foreach ( $times as $t ) {
    printf( "  %-40s created %s   updated %s\n", $t['TABLE_NAME'], (string) $t['CREATE_TIME'], null === $t['UPDATE_TIME'] ? '(none)' : (string) $t['UPDATE_TIME'] );
}

echo "\n  MariaDB rewrites CREATE_TIME on an ALTER, so the moves table's\n";
echo "  created time is when its columns last changed, not when it was made.\n";

echo "\n== the re-filing pass's own state ==\n";

$talk = get_option( 'vergeml_talk_state' );
$talk = is_array( $talk ) ? $talk : array();

printf( "  active        %s\n", empty( $talk['active'] ) ? 'no' : 'yes' );
printf( "  taxonomy      %s\n", isset( $talk['taxonomy'] ) ? (string) $talk['taxonomy'] : '(none)' );
printf( "  reasons held  %d\n", isset( $talk['reasons'] ) && is_array( $talk['reasons'] ) ? count( $talk['reasons'] ) : 0 );
printf( "  cursor        %s\n", isset( $talk['cursor'] ) ? (string) $talk['cursor'] : '(none)' );

echo "\n== the real write path, one row, into a batch id no batch has ==\n";

$scratch = 999997;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$before = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id = %d",
    $scratch
) );

if ( $before ) {
    printf( "  batch %d already holds %d rows -- refusing to write beside them\n", $scratch, $before );
    return;
}

/*
 *  Exactly what vergeml_talk_trail_write() builds: [ batch, attachment, term,
 *  0, reason ], with the reason vergeml_talk_reason() returns for a placement
 *  the matcher made.
 */
$reason = array(
    'why'           => 'ok',
    'score'         => 0.812345,
    'runner_up'     => 1797,
    'runner_score'  => 0.441234,
    'prompt_hash'   => '6bb36302',
    'model_version' => 'anthropic/claude-haiku-4.5',
);

$wpdb->last_error = '';

vergeml_librarian_moves_insert( array( array( $scratch, 999001, 1781, 0, $reason ) ) );

printf( "  the database said: %s\n", '' === (string) $wpdb->last_error ? '(nothing -- the insert was accepted)' : $wpdb->last_error );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a probe.
$rows = (array) $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id = %d",
    $scratch
), ARRAY_A );

printf( "  rows written: %d\n", count( $rows ) );

foreach ( $rows as $r ) {
    foreach ( $r as $k => $v ) {
        printf( "    %-14s %s\n", $k, null === $v ? 'NULL' : "'" . $v . "'" );
    }
}

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

printf( "\n  scratch rows left behind: %d\n", $left );
printf( "  rows in the table now:    %d\n", $total );
