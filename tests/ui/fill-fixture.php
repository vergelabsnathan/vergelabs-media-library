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
 *      VGML_MODE=plant [VGML_LEFT=n] [VGML_ALT=1] [VGML_COUNT=n] [VGML_Q1=n] wp eval-file fill-fixture.php
 *      VGML_MODE=restore                                                       wp eval-file fill-fixture.php
 *
 *  plant   two questions on eight real pictures -- "5 look like spec probes"
 *          (new-folder · leave · show-me) and "3 with nothing to go on" (leave ·
 *          show-me) -- and every other unfiled picture parked in To sort, so
 *          that answering both leaves 0 in no folder: the done state. The
 *          pictures are taken from the unfiled ones first and then out of To
 *          sort (after Nathan's walk the box has 0 unfiled and 327 there);
 *          each one's folders are snapshotted by literal SQL and put back.
 *          VGML_LEFT leaves that many unparked (the mutation gate: unfiled 3
 *          is not done). VGML_COUNT=n plants n questions: the two above, an
 *          either/or card ("4 pictures: A or B?"), a small-groups card ("3
 *          more, in small groups") and residue cards of eight pictures each
 *          to n -- the 30 the box's own fill left, for timing an answer.
 *          VGML_Q1=n gives the first question n pictures (the 61 case).
 *          VGML_ALT=1 also clears the file alt of three described pictures
 *          and gives a fourth an alt of its own that the catalogue's differs
 *          from -- inside the writing flag, so nothing is locked.
 *          VGML_SEED_FAKE=1 (Playground, which has no described library) makes
 *          fake described attachments first: index rows written by this file,
 *          not through the plugin's writer, with an 8-float embedding.
 *  restore every question picture back in the folders it had and its placed-by
 *          mark gone; Spec probe deleted; the parked pictures out of To sort,
 *          and To sort gone if it did not exist; the state and undo options put
 *          back; the moves rows the answers wrote deleted; the alts put back;
 *          the fake attachments deleted.
 *
 *  Spends nothing: no model, no describe, no embed. Idempotent: a plant over
 *  a plant restores first. Written 2026-09-15 (every-picture-a-home B.4);
 *  the To sort pool, the count and the fakes 2026-09-16 (C.3).
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

    // Every question picture back where it was: the folders it had (none, or To sort), and no mark.
    foreach ( (array) $snap['question_ids'] as $id ) {
        $was = isset( $snap['question_terms'][ $id ] ) ? array_map( 'intval', (array) $snap['question_terms'][ $id ] ) : array();
        wp_set_object_terms( (int) $id, $was, $ff_tax, false );
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
    // The picture marked "by you" for the media list: its folders as they were, mark gone.
    if ( ! empty( $snap['word_id'] ) ) {
        if ( isset( $snap['word_terms'] ) ) {
            wp_set_object_terms( (int) $snap['word_id'], array_map( 'intval', (array) $snap['word_terms'] ), $ff_tax, false );
        }
        delete_post_meta( (int) $snap['word_id'], VERGEML_FILING_PLACED_BY );
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

    foreach ( (array) ( isset( $snap['fake'] ) ? $snap['fake'] : array() ) as $id ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->delete( $wpdb->vergeml_ai_index, array( 'attachment_id' => (int) $id ) );
        wp_delete_post( (int) $id, true );
    }

    // The fixture's own product (S10.8), gone with its meta; its picture was never moved.
    if ( ! empty( $snap['product'] ) && get_post( (int) $snap['product'] ) ) {
        wp_delete_post( (int) $snap['product'], true );
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

$ff_left  = max( 0, (int) getenv( 'VGML_LEFT' ) );
// VGML_COUNT=0 is a Fill step with no open question at all; unset means the two of B.4.
$ff_count = false === getenv( 'VGML_COUNT' ) || '' === getenv( 'VGML_COUNT' ) ? 2 : max( 0, (int) getenv( 'VGML_COUNT' ) );
$ff_q1n   = max( 1, (int) getenv( 'VGML_Q1' ) ?: 5 );

// The pictures the questions take: q1, q2 (3), then either (4), more (3), and eight a residue card.
$ff_need = 0;
if ( $ff_count >= 1 ) {
    $ff_need += $ff_q1n;
}
if ( $ff_count >= 2 ) {
    $ff_need += 3;
}
if ( $ff_count >= 3 ) {
    $ff_need += 4;
}
if ( $ff_count >= 4 ) {
    $ff_need += 3;
}
if ( $ff_count > 4 ) {
    $ff_need += 8 * ( $ff_count - 4 );
}

$ff_snap = array(
    'state'          => get_option( VERGEML_TALK_STATE ),
    'undo'           => get_option( VERGEML_TALK_UNDO ),
    'had_to_sort'    => get_term_by( 'slug', VERGEML_FILING_TO_SORT_SLUG, $ff_tax ) instanceof WP_Term,
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    'move_id'        => isset( $wpdb->vergeml_librarian_moves ) ? (int) $wpdb->get_var( "SELECT COALESCE(MAX(move_id),0) FROM {$wpdb->vergeml_librarian_moves}" ) : 0,
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    'batch_id'       => isset( $wpdb->vergeml_librarian_batches ) ? (int) $wpdb->get_var( "SELECT COALESCE(MAX(batch_id),0) FROM {$wpdb->vergeml_librarian_batches}" ) : 0,
    'question_ids'   => array(),
    'question_terms' => array(),
    'parked'         => array(),
    'alts'           => array(),
    'fake'           => array(),
);

// Written before anything moves, so a plant that dies half-way can still be restored.
update_option( $ff_opt, $ff_snap, false );

/*
 *  VGML_PRODUCT=1 (S10.8): a product of the fixture's own whose featured
 *  image is one real picture, so the page reads as a site that sells --
 *  the rail turned round, the line under it, the "on products" pill. Only
 *  where WooCommerce's product type exists (the box's tech site); the
 *  restore deletes the product, and the picture was never moved.
 */
$ff_product = 0;
if ( '1' === (string) getenv( 'VGML_PRODUCT' ) && post_type_exists( 'product' ) ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ff_pic = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type LIKE 'image/%' ORDER BY ID ASC LIMIT 1" );
    if ( $ff_pic ) {
        $ff_product = (int) wp_insert_post( array( 'post_title' => 'zz spec product', 'post_type' => 'product', 'post_status' => 'publish' ) );
        update_post_meta( $ff_product, '_thumbnail_id', (string) $ff_pic );
        $ff_snap['product'] = $ff_product;
        update_option( $ff_opt, $ff_snap, false );
    }
}

/*
 *  Playground has no described library: fake ones, as index rows this file
 *  writes itself (never through the plugin's writer, which is not under test
 *  here and might be under mutation), each with a short embedding so the
 *  status route counts them as described.
 */
if ( getenv( 'VGML_SEED_FAKE' ) ) {
    if ( function_exists( 'vergeml_index_install' ) ) {
        vergeml_index_install();
    }
    for ( $i = 0; $i < $ff_need + $ff_left; $i++ ) {
        $id = wp_insert_post( array( 'post_title' => 'spec fake ' . $i, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/png' ) );
        if ( ! $id || is_wp_error( $id ) ) {
            continue;
        }
        // Not a packed vector: Playground's SQLite layer throws on the bytes. Eight ASCII bytes are "an embedding" to every count that asks IS NOT NULL, and unpack to two harmless floats.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $ok = $wpdb->insert( $wpdb->vergeml_ai_index, array(
            'attachment_id'  => (int) $id,
            'caption'        => 'spec fake',
            'kind'           => 'photo',
            'filing'         => wp_json_encode( array( 'object' => 'spec probe; probe' ) ),
            'embedding'      => 'ABCDEFGH',
            'embedding_dims' => 2,
            'error'          => '',
            'described_at'   => gmdate( 'Y-m-d H:i:s' ),
            'updated_at'     => gmdate( 'Y-m-d H:i:s' ),
        ) );
        if ( false === $ok ) {
            printf( "fake index row refused: %s\n", $wpdb->last_error );
        }
        $ff_snap['fake'][] = (int) $id;
    }
    update_option( $ff_opt, $ff_snap, false );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    printf( "fakes: %d made · index rows %d · with embedding %d · error '' %d\n", count( $ff_snap['fake'] ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index}" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE embedding IS NOT NULL" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = ''" ) );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$ff_unfiled = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
    "SELECT i.attachment_id FROM {$wpdb->vergeml_ai_index} i
      WHERE i.error = '' AND i.embedding IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
         WHERE tr.object_id = i.attachment_id AND tt.taxonomy = %s )
      ORDER BY i.attachment_id ASC",
    $ff_tax
) ) );

/*
 *  The pool: the unfiled pictures, then -- when those run short -- pictures
 *  out of To sort, which the questions take out of it for the test. Their
 *  folders are read here by literal SQL and put back by the restore.
 */
$ff_pool  = $ff_unfiled;
$ff_terms = array();
if ( count( $ff_pool ) < $ff_need + $ff_left ) {
    $ff_ts = get_term_by( 'slug', VERGEML_FILING_TO_SORT_SLUG, $ff_tax );
    if ( $ff_ts instanceof WP_Term ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $ff_in_ts = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
            "SELECT i.attachment_id FROM {$wpdb->vergeml_ai_index} i
               JOIN {$wpdb->term_relationships} tr ON tr.object_id = i.attachment_id
              WHERE i.error = '' AND i.embedding IS NOT NULL AND tr.term_taxonomy_id = %d
              ORDER BY i.attachment_id ASC LIMIT %d",
            (int) $ff_ts->term_taxonomy_id,
            $ff_need + $ff_left - count( $ff_pool )
        ) ) );
        foreach ( $ff_in_ts as $id ) {
            $ff_terms[ $id ] = array( (int) $ff_ts->term_id );
        }
        $ff_pool = array_merge( $ff_pool, $ff_in_ts );
    }
}

if ( count( $ff_pool ) < $ff_need + $ff_left ) {
    printf( "only %d described pictures unfiled or in To sort; %d + %d needed\n", count( $ff_pool ), $ff_need, $ff_left );
    $ff_restore();
    exit( 1 );
}

// Taken: the questions' pictures and the VGML_LEFT ones, all out of whatever folder they had.
$ff_taken = array_slice( $ff_pool, 0, $ff_need + $ff_left );
$ff_qids  = array_slice( $ff_taken, 0, $ff_need );
$ff_snap['question_ids'] = $ff_taken;
foreach ( $ff_taken as $id ) {
    $ff_snap['question_terms'][ $id ] = isset( $ff_terms[ $id ] ) ? $ff_terms[ $id ] : array();
}
update_option( $ff_opt, $ff_snap, false );

wp_defer_term_counting( true );
foreach ( $ff_taken as $id ) {
    if ( isset( $ff_terms[ $id ] ) ) {
        wp_set_object_terms( $id, array(), $ff_tax, false );
    }
}
wp_defer_term_counting( false );

$ff_q1   = array_slice( $ff_qids, 0, $ff_q1n );
$ff_q2   = array_slice( $ff_qids, $ff_q1n, 3 );
$ff_at   = $ff_q1n + 3;
$ff_park = array_slice( $ff_unfiled, $ff_need, max( 0, count( $ff_unfiled ) - $ff_need - $ff_left ) );

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

$ff_questions = array();
if ( $ff_count >= 1 ) {
    $ff_questions[] = array( 'id' => 'r:0', 'kind' => 'residue', 'term_id' => 0, 'children' => array(), 'count' => count( $ff_q1 ), 'sample' => array_slice( $ff_q1, 0, 8 ), 'ids' => $ff_q1, 'name' => 'Spec probe', 'class' => 'spec probe', 'unreadable' => false, 'answers' => array( 'new-folder', 'leave', 'show-me' ) );
}
if ( $ff_count >= 2 ) {
    $ff_questions[] = array( 'id' => 'r:1', 'kind' => 'residue', 'term_id' => 0, 'children' => array(), 'count' => 3, 'sample' => $ff_q2, 'ids' => $ff_q2, 'name' => '', 'class' => '', 'unreadable' => true, 'answers' => array( 'leave', 'show-me' ) );
}
$ff_either = array();
if ( $ff_count >= 3 ) {
    // Either/or: two top-level folders that are not To sort, the pictures mapped to the first.
    $ff_ts_term = get_term_by( 'slug', VERGEML_FILING_TO_SORT_SLUG, $ff_tax );
    $ff_tops    = get_terms( array( 'taxonomy' => $ff_tax, 'parent' => 0, 'hide_empty' => false, 'exclude' => $ff_ts_term instanceof WP_Term ? array( (int) $ff_ts_term->term_id ) : array(), 'number' => 2, 'orderby' => 'name' ) );
    if ( ! is_wp_error( $ff_tops ) && 2 === count( $ff_tops ) ) {
        $ff_e = array_slice( $ff_qids, $ff_at, 4 );
        $ff_either = array( (int) $ff_tops[0]->term_id, (int) $ff_tops[1]->term_id );
        $ff_questions[] = array( 'id' => 'e:' . $ff_either[0] . ':' . $ff_either[1], 'kind' => 'either', 'term_id' => 0, 'children' => $ff_either, 'count' => 4, 'sample' => $ff_e, 'ids' => array_fill_keys( $ff_e, $ff_either[0] ), 'name' => '', 'class' => '', 'unreadable' => false, 'answers' => array( 'put-in:' . $ff_either[0], 'put-in:' . $ff_either[1], 'split', 'leave', 'show-me' ) );
    }
    $ff_at += 4;
}
if ( $ff_count >= 4 ) {
    $ff_m = array_slice( $ff_qids, $ff_at, 3 );
    $ff_questions[] = array( 'id' => 'r:2', 'kind' => 'residue', 'term_id' => 0, 'children' => array(), 'count' => 3, 'sample' => $ff_m, 'ids' => $ff_m, 'name' => '', 'class' => '', 'share' => 1.0, 'group_kind' => 'photo', 'more' => true, 'unreadable' => false, 'answers' => array( 'leave', 'show-me' ) );
    $ff_at += 3;
}
for ( $i = 4; $i < $ff_count; $i++ ) {
    $ff_r = array_slice( $ff_qids, $ff_at, 8 );
    $ff_at += 8;
    $ff_questions[] = array( 'id' => 'r:' . ( $i - 1 ), 'kind' => 'residue', 'term_id' => 0, 'children' => array(), 'count' => count( $ff_r ), 'sample' => $ff_r, 'ids' => $ff_r, 'name' => 'Spec group ' . $i, 'class' => 'spec group ' . $i, 'share' => 1.0, 'group_kind' => 'photo', 'more' => false, 'unreadable' => false, 'answers' => array( 'new-folder', 'leave', 'show-me' ) );
}

$ff_state = $ff_snap['state'];
$ff_new   = is_array( $ff_state ) ? $ff_state : array();
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
 *  so the media list's row, the modal and the Placed by hand filter have a
 *  "by you" to show. Its folders are snapshotted: the C.3 spec drags it out
 *  through the assign route, which must clear the mark.
 */
$ff_word = array( 'id' => 0, 'folder' => '', 'term_id' => 0 );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$ff_row = $wpdb->get_row( $wpdb->prepare(
    "SELECT tr.object_id AS id, t.slug, t.term_id FROM {$wpdb->term_relationships} tr
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
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $ff_snap['word_terms'] = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
        "SELECT tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = %s",
        (int) $ff_row['id'],
        $ff_tax
    ) ) );
    update_post_meta( (int) $ff_row['id'], VERGEML_FILING_PLACED_BY, 'user' );
    $ff_snap['word_id'] = (int) $ff_row['id'];
    update_option( $ff_opt, $ff_snap, false );
    $ff_word = array( 'id' => (int) $ff_row['id'], 'folder' => (string) $ff_row['slug'], 'term_id' => (int) $ff_row['term_id'] );
}

if ( function_exists( 'vergeml_folders_moved' ) ) {
    vergeml_folders_moved( 'undo' );
}

echo wp_json_encode( array(
    'word'     => $ff_word,
    // Every picture that carries the mark, by this file's own SQL: what the Placed by hand filter must list, exactly.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    'placed'   => array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'attachment' WHERE pm.meta_key = %s AND pm.meta_value = 'user' ORDER BY pm.post_id", VERGEML_FILING_PLACED_BY ) ) ),
    'planted'  => true,
    'product'  => $ff_product,
    'q1'       => $ff_q1,
    'q2'       => $ff_q2,
    'either'   => $ff_either,
    'count'    => count( $ff_questions ),
    'parked'   => count( $ff_park ),
    'from_to_sort' => count( $ff_terms ),
    'left'     => $ff_left,
    'unfiled'  => vergeml_talk_fill_status()['unfiled'],
    'open'     => vergeml_talk_fill_status()['open'],
    'alt'      => $ff_alt,
) ) . "\n";
