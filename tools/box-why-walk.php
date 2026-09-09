<?php
/**
 *  The smallest filing run that leaves a record.
 *
 *  `vergeml_librarian_moves` was emptied by Phase 4's gate7-schema incident and
 *  nothing has filed since, so every picture on the box correctly shows no
 *  "why is it here" section and the surface cannot be seen by a person. This
 *  files a handful of pictures the way the plugin files them -- the same
 *  matcher (`core/filing.php`), the same reason tuple, the same insert the
 *  Folders screen's Move uses -- and writes down what it touched so
 *  `box-why-walk-undo.php` can put it back exactly.
 *
 *  It is a walk, not a pass. VGML_WALK_N pictures (8 by default), the newest
 *  that are in no folder at all, and nothing else in the library is looked at.
 *
 *  Two things the Move does that this does not: it never evicts a picture from
 *  a folder it already sits in, and it does not schedule anything. What it
 *  writes is a placement row for each picture the evidence places and an
 *  abstention row for each one it refuses -- which is the record the reader
 *  reads.
 *
 *      scp tools/box-why-walk.php root@box:/tmp/vgml-why-walk.php
 *      bash tools/box-why-walk.sh
 *
 *  Nothing here reaches a language model: the pictures are already described
 *  and the folders already have profiles.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$option = 'vergeml_why_walk_restore';
$limit  = max( 1, (int) ( getenv( 'VGML_WALK_N' ) ? getenv( 'VGML_WALK_N' ) : 8 ) );

if ( get_option( $option ) ) {
    echo "a walk is already open -- run box-why-walk-undo.sh before making another\n";
    return;
}

if ( ! function_exists( 'vergeml_filing_pick' ) || ! function_exists( 'vergeml_librarian_moves_insert' ) ) {
    echo "this build cannot file or record\n";
    return;
}

$moves = $wpdb->vergeml_librarian_moves;

printf( "%d rows in the moves table before this walk\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$moves}" ) );

$taxonomy = vergeml_librarian_taxonomy();
$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
$terms    = is_wp_error( $terms ) ? array() : $terms;
$term_ids = array_map( function ( $t ) { return (int) $t->term_id; }, $terms );
$profiles = vergeml_filing_profiles( $term_ids, $taxonomy );

printf( "%d folders, %d of them profiled\n", count( $term_ids ), count( $profiles ) );

$table = vergeml_index_table();
$tt    = vergeml_autofile_tt_ids( $taxonomy );

// The newest pictures in no folder at all: nothing is taken out of a folder it is in.
$ids = (array) $wpdb->get_col( $wpdb->prepare(
    "SELECT p.ID FROM {$wpdb->posts} p JOIN {$table} x ON x.attachment_id = p.ID
      WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
        AND x.error = '' AND x.embedding IS NOT NULL
        AND NOT EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} tr
                          WHERE tr.object_id = p.ID AND tr.term_taxonomy_id IN ( {$tt} ) )
      ORDER BY p.ID DESC LIMIT %d",
    $limit
) );

if ( ! $ids ) {
    echo "no unfiled described picture to walk\n";
    return;
}

$batch_id = vergeml_autofile_batch( 'refile' );

if ( is_wp_error( $batch_id ) ) {
    printf( "no batch: %s\n", $batch_id->get_error_message() );
    return;
}

$name = function ( $tid ) use ( $taxonomy ) {
    $t = $tid ? get_term( (int) $tid, $taxonomy ) : null;
    return $t instanceof WP_Term ? $t->name : '-';
};

$trail = array();
$undo  = array();

foreach ( $ids as $id ) {

    $id  = (int) $id;
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT attachment_id, embedding, kind, filing, prompt_hash, model_version FROM {$table} WHERE attachment_id = %d",
        $id
    ), ARRAY_A );

    if ( ! $row ) {
        continue;
    }

    $facts = vergeml_filing_facts( $row );
    $pick  = vergeml_filing_pick( $facts, $profiles );

    // The reason tuple the Move packs, so the row is the same row.
    $reason = vergeml_talk_reason(
        array(
            isset( $pick['why'] ) ? $pick['why'] : 'floor',
            $pick['score'],
            $pick['runner_up'],
            $pick['runner_score'],
            isset( $pick['nearest'] ) ? $pick['nearest'] : 0,
        ),
        $row
    );

    if ( ! $pick['term_id'] ) {
        // Looked at and left alone. No eviction here: this walk takes nothing out of a folder.
        $trail[] = array( (int) $batch_id, $id, 0, 0, $reason );
        printf( "  %-6d %-32s stays   %-8s @%.2f\n", $id, mb_substr( (string) get_the_title( $id ), 0, 32 ), (string) $pick['why'], (float) $pick['score'] );
        continue;
    }

    $was = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
    $undo[ $id ] = is_wp_error( $was ) ? array() : array_map( 'intval', $was );

    wp_set_object_terms( $id, array( (int) $pick['term_id'] ), $taxonomy, false );

    $trail[] = array( (int) $batch_id, $id, (int) $pick['term_id'], 0, $reason );
    printf( "  %-6d %-32s MOVED   -> %-24s @%.2f\n", $id, mb_substr( (string) get_the_title( $id ), 0, 32 ), $name( $pick['term_id'] ), (float) $pick['score'] );
}

if ( ! $trail ) {
    echo "nothing to record\n";
    return;
}

vergeml_librarian_moves_insert( $trail );

$written = (array) $wpdb->get_col( $wpdb->prepare(
    "SELECT move_id FROM {$moves} WHERE batch_id = %d",
    (int) $batch_id
) );

update_option(
    $option,
    array(
        'batch_id' => (int) $batch_id,
        'move_ids' => array_map( 'intval', $written ),
        'terms'    => $undo,
        'made'     => time(),
    ),
    false
);

printf( "\n%d rows written in batch %d, %d pictures moved, %d left where they were\n", count( $written ), (int) $batch_id, count( $undo ), count( $trail ) - count( $undo ) );

echo "\n=== what the reader says for each of them\n";

foreach ( $ids as $id ) {
    $why = vergeml_librarian_why( (int) $id );
    printf( "\n  %d  %s\n", (int) $id, mb_substr( (string) get_the_title( (int) $id ), 0, 48 ) );
    if ( ! $why ) {
        echo "    (no lines)\n";
        continue;
    }
    foreach ( (array) $why['lines'] as $line ) {
        printf( "    %s\n", $line );
    }
}

echo "\nthe way back: bash tools/box-why-walk-undo.sh\n";
