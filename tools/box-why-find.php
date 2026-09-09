<?php
/**
 *  Which pictures the record can actually answer for.
 *
 *  The Phase 6 browser proof needs a picture that already has a row --
 *  nothing planted -- and its first run found none among the hundred newest.
 *  This says whether that is the library or the reader: how many rows there
 *  are, how many of them belong to attachments that still exist, and what
 *  vergeml_librarian_why() returns for the first handful of them.
 *
 *      scp tools/box-why-find.php root@box:/tmp/vgml-why-find.php
 *      bash tools/box-why-find.sh
 *
 *  Read-only. It selects and prints; it writes nothing anywhere.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

if ( ! function_exists( 'vergeml_librarian_why' ) ) {
    echo "this build has no vergeml_librarian_why()\n";
    exit( 1 );
}

$moves = $wpdb->vergeml_librarian_moves;

printf( "%d rows in the moves table\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$moves}" ) );
printf( "%d of them not undone\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$moves} WHERE undone = 0" ) );

$by_why = (array) $wpdb->get_results( "SELECT why, COUNT(*) n FROM {$moves} WHERE undone = 0 GROUP BY why", ARRAY_A );

foreach ( $by_why as $r ) {
    printf( "  why %-10s %d\n", '' === $r['why'] ? '(empty)' : $r['why'], (int) $r['n'] );
}

$ids = (array) $wpdb->get_col( "SELECT DISTINCT attachment_id FROM {$moves} WHERE undone = 0 ORDER BY move_id DESC LIMIT 40" );

printf( "\n%d distinct attachments on those rows\n", count( $ids ) );

$alive = 0;
$answered = 0;
$first = array();

foreach ( $ids as $id ) {

    $post = get_post( (int) $id );

    if ( ! $post || 'attachment' !== $post->post_type ) {
        continue;
    }

    $alive++;

    $why = vergeml_librarian_why( (int) $id );

    if ( $why && ! empty( $why['lines'] ) ) {
        $answered++;
        if ( count( $first ) < 3 ) {
            $first[] = array( 'id' => (int) $id, 'lines' => $why['lines'] );
        }
    }
}

printf( "%d of them still exist, %d of those the reader can answer for\n\n", $alive, $answered );

foreach ( $first as $one ) {
    printf( "picture %d\n", $one['id'] );
    foreach ( $one['lines'] as $line ) {
        printf( "    %s\n", $line );
    }
    echo "\n";
}
