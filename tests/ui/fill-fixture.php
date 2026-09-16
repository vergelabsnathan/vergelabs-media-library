<?php
/**
 *  A Fill step to walk on the box, and the box exactly as it was after.
 *
 *  tests/ui/folders.spec.mjs plants drafts through /guide/session; the
 *  questions the fill leaves live in the talk state option
 *  (vergeml_talk_refile), which no route writes. So the spec ships this file
 *  over SSH (tests/ui/box.mjs) and runs it twice, the way tools/verify.mjs
 *  runs every PHP suite:
 *
 *      VGML_MODE=plant [VGML_LEFT=n] [VGML_ALT=1] wp eval-file fill-fixture.php
 *      VGML_MODE=restore                            wp eval-file fill-fixture.php
 *
 *  plant   two questions on eight real unfiled pictures -- "5 look like spec
 *          probes" (new-folder · leave · show-me) and "3 with nothing to go on" (leave ·
 *          show-me) -- and every other unfiled picture parked in To sort, so
 *          that answering both leaves 0 in no folder: the done state. VGML_LEFT
 *          leaves that many unparked (the mutation gate: unfiled 3 is not
 *          done). VGML_ALT=1 also clears the file alt of three described
 *          pictures and gives a fourth an alt of its own that the catalogue's
 *          differs from -- inside the writing flag, so nothing is locked.
 *  restore the eight pictures out of every folder and their placed-by mark
 *          gone; Spec probe deleted; the parked pictures out of To sort, and
 *          To sort gone if it did not exist; the state and undo options put
 *          back; the moves rows the answers wrote deleted; the alts put back.
 *
 *  Spends nothing: no model, no describe, no embed. Idempotent: a plant over
 *  a plant restores first. Written 2026-09-15 (every-picture-a-home B.4).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

wp_set_current_user( 1 );

$ff_mode = (string) getenv( 'VGML_MODE' );
$ff_tax  = vergeml_librarian_taxonomy();
$ff_opt  = 'vergeml_spec_fill';
$ff_snap = get_option( $ff_opt );

$ff_restore = function () use ( $wpdb, $ff_tax, $ff_opt ) {
    $snap = get_option( $ff_opt );
    if ( ! is_array( $snap ) ) {
        echo "nothing planted\n";
        return;
    }
    wp_defer_term_counting( true );

    foreach ( (array) $snap['question_ids'] as $id ) {
        wp_set_object_terms( (int) $id, array(), $ff_tax, false );
        delete_post_meta( (int) $id, VERGEML_FILING_PLACED_BY );
    }
    foreach ( (array) get_terms( array( 'taxonomy' => $ff_tax, 'name' => 'Spec probe', 'hide_empty' => false ) ) as $t ) {
        if ( $t instanceof WP_Term ) {
            wp_delete_term( (int) $t->term_id, $ff_tax );
        }
    }
    $to_sort = get_term_by( 'slug', VERGEML_FILING_TO_SORT_SLUG, $ff_tax );
    if ( $to_sort instanceof WP_Term ) {
        foreach ( (array) $snap['parked'] as $id ) {
            wp_remove_object_terms( (int) $id, array( (int) $to_sort->term_id ), $ff_tax );
        }
    }
    wp_defer_term_counting( false );
    if ( $to_sort instanceof WP_Term && empty( $snap['had_to_sort'] ) ) {
        $held = get_objects_in_term( (int) $to_sort->term_id, $ff_tax );
        if ( is_wp_error( $held ) || ! $held ) {
            wp_delete_term( (int) $to_sort->term_id, $ff_tax );
        }
    }

    foreach ( array( VERGEML_TALK_STATE => 'state', VERGEML_TALK_UNDO => 'undo' ) as $opt => $k ) {
        if ( false === $snap[ $k ] ) {
            delete_option( $opt );
        } else {
            update_option( $opt, $snap[ $k ], false );
        }
    }

    if ( isset( $wpdb->vergeml_librarian_moves ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->vergeml_librarian_moves} WHERE move_id > %d", (int) $snap['move_id'] ) );
        if ( isset( $wpdb->vergeml_librarian_batches ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->vergeml_librarian_batches} WHERE batch_id > %d AND batch_id NOT IN ( SELECT DISTINCT batch_id FROM {$wpdb->vergeml_librarian_moves} )", (int) $snap['batch_id'] ) );
        }
    }

    vergeml_index_writing( true );
    foreach ( (array) $snap['alts'] as $id => $alt ) {
        if ( '' === (string) $alt ) {
            delete_post_meta( (int) $id, '_wp_attachment_image_alt' );
        } else {
            update_post_meta( (int) $id, '_wp_attachment_image_alt', $alt );
        }
    }
    // The press writes the catalogue's alt onto every picture without one; those had none before, and have none after.
    foreach ( (array) ( isset( $snap['alt_none'] ) ? $snap['alt_none'] : array() ) as $id ) {
        delete_post_meta( (int) $id, '_wp_attachment_image_alt' );
    }
    vergeml_index_writing( false );

    if ( ! empty( $snap['word_id'] ) ) {
        delete_post_meta( (int) $snap['word_id'], VERGEML_FILING_PLACED_BY );
    }

    delete_option( $ff_opt );
    if ( function_exists( 'vergeml_folders_moved' ) ) {
        vergeml_folders_moved( 'undo' );
    }
    echo "restored\n";
};

if ( 'restore' === $ff_mode ) {
    $ff_restore();
    return;
}

if ( 'plant' !== $ff_mode ) {
    echo "VGML_MODE must be plant or restore\n";
    exit( 1 );
}

if ( is_array( $ff_snap ) ) {
    $ff_restore();
}

$ff_left = max( 0, (int) getenv( 'VGML_LEFT' ) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$ff_unfiled = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
    "SELECT i.attachment_id FROM {$wpdb->vergeml_ai_index} i
      WHERE i.error = '' AND i.embedding IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
         WHERE tr.object_id = i.attachment_id AND tt.taxonomy = %s )
      ORDER BY i.attachment_id ASC",
    $ff_tax
) ) );

if ( count( $ff_unfiled ) < 8 + $ff_left ) {
    printf( "only %d unfiled described pictures; 8 + %d needed\n", count( $ff_unfiled ), $ff_left );
    exit( 1 );
}

$ff_state = get_option( VERGEML_TALK_STATE );
$ff_snap  = array(
    'state'        => $ff_state,
    'undo'         => get_option( VERGEML_TALK_UNDO ),
    'had_to_sort'  => get_term_by( 'slug', VERGEML_FILING_TO_SORT_SLUG, $ff_tax ) instanceof WP_Term,
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    'move_id'      => isset( $wpdb->vergeml_librarian_moves ) ? (int) $wpdb->get_var( "SELECT COALESCE(MAX(move_id),0) FROM {$wpdb->vergeml_librarian_moves}" ) : 0,
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    'batch_id'     => isset( $wpdb->vergeml_librarian_batches ) ? (int) $wpdb->get_var( "SELECT COALESCE(MAX(batch_id),0) FROM {$wpdb->vergeml_librarian_batches}" ) : 0,
    'question_ids' => array_slice( $ff_unfiled, 0, 8 ),
    'parked'       => array(),
    'alts'         => array(),
);

$ff_q1 = array_slice( $ff_unfiled, 0, 5 );
$ff_q2 = array_slice( $ff_unfiled, 5, 3 );
$ff_park = array_slice( $ff_unfiled, 8, max( 0, count( $ff_unfiled ) - 8 - $ff_left ) );

// Written before anything moves, so a plant that dies half-way can still be restored.
update_option( $ff_opt, $ff_snap, false );

if ( $ff_park ) {
    $ff_to_sort = vergeml_talk_to_sort( $ff_tax );
    wp_defer_term_counting( true );
    foreach ( $ff_park as $id ) {
        wp_set_object_terms( $id, array( $ff_to_sort ), $ff_tax, false );
    }
    wp_defer_term_counting( false );
    $ff_snap['parked'] = $ff_park;
    update_option( $ff_opt, $ff_snap, false );
}

$ff_questions = array(
    array( 'id' => 'r:0', 'kind' => 'residue', 'term_id' => 0, 'children' => array(), 'count' => 5, 'sample' => $ff_q1, 'ids' => $ff_q1, 'name' => 'Spec probe', 'class' => 'spec probe', 'unreadable' => false, 'answers' => array( 'new-folder', 'leave', 'show-me' ) ),
    array( 'id' => 'r:1', 'kind' => 'residue', 'term_id' => 0, 'children' => array(), 'count' => 3, 'sample' => $ff_q2, 'ids' => $ff_q2, 'name' => '', 'class' => '', 'unreadable' => true, 'answers' => array( 'leave', 'show-me' ) ),
);
$ff_new = is_array( $ff_state ) ? $ff_state : array();
$ff_new['taxonomy']       = $ff_tax;
$ff_new['active']         = false;
$ff_new['questions']      = $ff_questions;
$ff_new['made_by_answer'] = array();
if ( ! isset( $ff_new['ids'] ) ) {
    $ff_new['ids'] = array();
}
update_option( VERGEML_TALK_STATE, $ff_new, false );
wp_clear_scheduled_hook( VERGEML_TALK_HOOK );

$ff_alt = array( 'cleared' => array(), 'kept' => 0, 'kept_alt' => '' );
if ( getenv( 'VGML_ALT' ) ) {
    // Described, with a catalogue alt, and an alt on the file: the four the step will be asked about.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $ff_rows = (array) $wpdb->get_results(
        "SELECT i.attachment_id AS id, i.alt AS cat, alt.meta_value AS file_alt FROM {$wpdb->vergeml_ai_index} i
           JOIN {$wpdb->postmeta} alt ON alt.post_id = i.attachment_id AND alt.meta_key = '_wp_attachment_image_alt'
          WHERE i.error = '' AND i.alt <> '' AND alt.meta_value <> ''
          ORDER BY i.attachment_id ASC LIMIT 4",
        ARRAY_A
    );
    if ( count( $ff_rows ) < 4 ) {
        printf( "only %d described pictures carry an alt on the file; 4 needed\n", count( $ff_rows ) );
        $ff_restore();
        exit( 1 );
    }
    foreach ( $ff_rows as $i => $row ) {
        $ff_snap['alts'][ (int) $row['id'] ] = (string) $row['file_alt'];
    }
    /*
     *  Every picture the press will write that had no alt at all: put back to
     *  none after. Read with the fixture's own query, never through the
     *  plugin's reader: on 2026-09-15 that reader was the thing under
     *  mutation (the never-overwrites guard removed), it answered "every
     *  picture", and the restore deleted a hundred real alts.
     */
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $ff_snap['alt_none'] = array_map( 'intval', (array) $wpdb->get_col(
        "SELECT i.attachment_id FROM {$wpdb->vergeml_ai_index} i
      LEFT JOIN {$wpdb->postmeta} alt ON alt.post_id = i.attachment_id AND alt.meta_key = '_wp_attachment_image_alt'
          WHERE i.error = '' AND i.alt <> '' AND ( alt.meta_id IS NULL OR alt.meta_value = '' )"
    ) );
    update_option( $ff_opt, $ff_snap, false );
    vergeml_index_writing( true );
    foreach ( array_slice( $ff_rows, 0, 3 ) as $row ) {
        update_post_meta( (int) $row['id'], '_wp_attachment_image_alt', '' );
        $ff_alt['cleared'][] = (int) $row['id'];
    }
    $ff_alt['kept']     = (int) $ff_rows[3]['id'];
    $ff_alt['kept_alt'] = 'Spec alt probe, yours';
    update_post_meta( $ff_alt['kept'], '_wp_attachment_image_alt', $ff_alt['kept_alt'] );
    vergeml_index_writing( false );
}

/*
 *  The word on a picture (B.5): one filed picture marked as placed by hand,
 *  so the media list's row and the modal have a "by you" to show. The box
 *  holds no fill that was not undone, so nothing there carries a word today.
 */
$ff_word = array( 'id' => 0, 'folder' => '' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$ff_row = $wpdb->get_row( $wpdb->prepare(
    "SELECT tr.object_id AS id, t.slug FROM {$wpdb->term_relationships} tr
       JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
       JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
       JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'attachment' AND p.post_mime_type LIKE %s
      WHERE tt.taxonomy = %s AND t.slug <> %s AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = tr.object_id AND pm.meta_key = %s )
      ORDER BY p.post_date DESC, p.ID DESC LIMIT 1",
    $wpdb->esc_like( 'image/' ) . '%',
    $ff_tax,
    VERGEML_FILING_TO_SORT_SLUG,
    VERGEML_FILING_PLACED_BY
), ARRAY_A );
if ( is_array( $ff_row ) ) {
    update_post_meta( (int) $ff_row['id'], VERGEML_FILING_PLACED_BY, 'user' );
    $ff_snap['word_id'] = (int) $ff_row['id'];
    update_option( $ff_opt, $ff_snap, false );
    $ff_word = array( 'id' => (int) $ff_row['id'], 'folder' => (string) $ff_row['slug'] );
}

if ( function_exists( 'vergeml_folders_moved' ) ) {
    vergeml_folders_moved( 'undo' );
}

echo wp_json_encode( array(
    'word'     => $ff_word,
    'planted'  => true,
    'q1'       => $ff_q1,
    'q2'       => $ff_q2,
    'parked'   => count( $ff_park ),
    'left'     => $ff_left,
    'unfiled'  => vergeml_talk_fill_status()['unfiled'],
    'open'     => vergeml_talk_fill_status()['open'],
    'alt'      => $ff_alt,
) ) . "\n";
