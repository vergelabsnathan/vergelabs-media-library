<?php
/**
 *  The way back from box-why-walk.php.
 *
 *  Every picture the walk moved goes back to exactly the folders it was in --
 *  read from the record the walk wrote, not guessed -- and every row the walk
 *  wrote is marked undone, which is what `vergeml_talk_undo()` does to the
 *  rows a Move wrote. Marked, not deleted: a reversed move is a thing that
 *  happened, and the reader already ignores `undone = 1`.
 *
 *      scp tools/box-why-walk-undo.php root@box:/tmp/vgml-why-walk-undo.php
 *      bash tools/box-why-walk-undo.sh
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$option = 'vergeml_why_walk_restore';
$state  = get_option( $option );

if ( ! is_array( $state ) || empty( $state['move_ids'] ) ) {
    echo "no walk is open\n";
    return;
}

$taxonomy = vergeml_librarian_taxonomy();
$back     = 0;

foreach ( (array) $state['terms'] as $id => $was ) {
    wp_set_object_terms( (int) $id, array_map( 'intval', (array) $was ), $taxonomy, false );
    printf( "  %d back to %s\n", (int) $id, $was ? implode( ', ', array_map( 'intval', (array) $was ) ) : 'no folder' );
    $back++;
}

$ids   = array_map( 'intval', (array) $state['move_ids'] );
$holes = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( $wpdb->prepare(
    "UPDATE {$wpdb->vergeml_librarian_moves} SET undone = 1 WHERE move_id IN ( {$holes} )",
    $ids
) );

delete_option( $option );

$moves = $wpdb->vergeml_librarian_moves;

printf( "\n%d pictures put back, %d rows marked undone\n", $back, count( $ids ) );
printf( "%d rows in the moves table, %d of them not undone\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$moves}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$moves} WHERE undone = 0" ) );
