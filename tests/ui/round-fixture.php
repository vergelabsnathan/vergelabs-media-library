<?php
/**
 *  The owner's round on the box, and the box exactly as it was after.
 *
 *  tests/ui/round.spec.mjs presses the real buttons -- This is my tree, Fill,
 *  an answer, Leave the rest, Fill, Unconfirm, a word's ×, This is my tree,
 *  Fill -- over the library the box holds, so three real fills move real
 *  pictures and two confirms re-profile real folders. Nothing of that may
 *  outlive the spec (memory: tests never touch live state; a spec once left
 *  a draft behind and twenty-one folders went). So the spec ships this file
 *  over SSH (tests/ui/box.mjs) the way every PHP suite runs, and runs it
 *  three ways:
 *
 *      VGML_MODE=snapshot   wp eval-file round-fixture.php
 *      VGML_MODE=questions  wp eval-file round-fixture.php
 *      VGML_MODE=restore    wp eval-file round-fixture.php
 *
 *  snapshot   every folder relationship in the library, every folder's meta
 *             (the profiles the confirm rewrites), every placed-by mark, the
 *             fill's state and undo, the screen's session, the trail's high
 *             water -- read by literal SQL, never through the plugin's own
 *             readers (memory: fixtures never read through the code under
 *             test; a restore list computed by a mutated reader deleted 100
 *             alts). Kept in one option until restore.
 *  questions  the open questions with every picture id each carries, off the
 *             state option: the spec's gate (a second fill asks nothing about
 *             a picture an answer placed) needs all of them, and the screen's
 *             route carries only a sample of eight.
 *  restore    the relationships put back row for row, the folders the round
 *             made deleted, the meta and the marks put back, the options put
 *             back, the trail trimmed, the caches and counts rebuilt.
 *
 *  Spends nothing itself. Idempotent: a snapshot over a snapshot restores
 *  first. Written 2026-09-17 (every-picture-a-home S11).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

wp_set_current_user( 1 );

$rf_mode = (string) getenv( 'VGML_MODE' );
$rf_tax  = vergeml_librarian_taxonomy();
$rf_opt  = 'vergeml_spec_round';

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- a fixture: literal SQL on purpose, ids cast to int where they are interpolated.

$rf_tt_ids = function () use ( $wpdb, $rf_tax ) {
    return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $rf_tax ) ) );
};
$rf_term_ids = function () use ( $wpdb, $rf_tax ) {
    return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $rf_tax ) ) );
};

$rf_restore = function () use ( $wpdb, $rf_tax, $rf_opt, $rf_tt_ids, $rf_term_ids ) {
    $snap = get_option( $rf_opt );
    if ( ! is_array( $snap ) ) {
        echo "nothing to restore\n";
        return;
    }

    // The relationships: every row of the taxonomy gone, the snapshot's rows back.
    $tts = $rf_tt_ids();
    if ( $tts ) {
        $wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ',', $tts ) . ')' );
    }
    foreach ( array_chunk( (array) $snap['rels'], 500 ) as $chunk ) {
        $values = array();
        foreach ( $chunk as $r ) {
            $values[] = $wpdb->prepare( '(%d,%d,%d)', (int) $r[0], (int) $r[1], (int) $r[2] );
        }
        $wpdb->query( "INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order) VALUES " . implode( ',', $values ) );
    }

    // The folders the round made (an answer's new folder, To sort if it was not there): gone, with their meta.
    $kept = array_map( 'intval', (array) $snap['terms'] );
    foreach ( array_diff( $rf_term_ids(), $kept ) as $tid ) {
        wp_delete_term( (int) $tid, $rf_tax );
    }

    // The folders' meta (profiles, the earlier profile, locks): the snapshot's rows and no others.
    if ( $kept ) {
        $wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN (" . implode( ',', $kept ) . ')' );
    }
    foreach ( (array) $snap['termmeta'] as $m ) {
        $wpdb->insert( $wpdb->termmeta, array( 'term_id' => (int) $m[0], 'meta_key' => (string) $m[1], 'meta_value' => (string) $m[2] ), array( '%d', '%s', '%s' ) );
    }

    // The marks: as they were.
    $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => VERGEML_FILING_PLACED_BY ), array( '%s' ) );
    foreach ( (array) $snap['placed'] as $p ) {
        $wpdb->insert( $wpdb->postmeta, array( 'post_id' => (int) $p[0], 'meta_key' => VERGEML_FILING_PLACED_BY, 'meta_value' => (string) $p[1] ), array( '%d', '%s', '%s' ) );
    }

    // Counts and caches: the rows changed under WordPress's feet.
    $objects = array();
    foreach ( (array) $snap['rels'] as $r ) {
        $objects[ (int) $r[0] ] = true;
    }
    foreach ( array_chunk( array_keys( $objects ), 500 ) as $chunk ) {
        clean_object_term_cache( $chunk, 'attachment' );
    }
    clean_term_cache( $kept, $rf_tax );
    $tts = $rf_tt_ids();
    if ( $tts ) {
        wp_update_term_count_now( $tts, $rf_tax );
    }
    wp_cache_flush();

    foreach ( array( VERGEML_TALK_STATE => 'state', VERGEML_TALK_UNDO => 'undo', VERGEML_GUIDE_OPTION => 'session' ) as $opt => $k ) {
        if ( false === $snap[ $k ] ) {
            delete_option( $opt );
        } else {
            update_option( $opt, $snap[ $k ], false );
        }
    }
    delete_transient( VERGEML_TALK_BEAT );
    delete_transient( VERGEML_TALK_PASS_LOCK );
    wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
    if ( defined( 'VERGEML_GUIDE_FIT_HOOK' ) ) {
        wp_clear_scheduled_hook( VERGEML_GUIDE_FIT_HOOK );
        delete_transient( VERGEML_GUIDE_FIT_LOCK );
    }

    if ( isset( $wpdb->vergeml_librarian_moves ) ) {
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->vergeml_librarian_moves} WHERE move_id > %d", (int) $snap['move_id'] ) );
        if ( isset( $wpdb->vergeml_librarian_batches ) ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->vergeml_librarian_batches} WHERE batch_id > %d AND batch_id NOT IN ( SELECT DISTINCT batch_id FROM {$wpdb->vergeml_librarian_moves} )", (int) $snap['batch_id'] ) );
        }
    }

    delete_option( $rf_opt );
    if ( function_exists( 'vergeml_folders_moved' ) ) {
        vergeml_folders_moved( 'undo' );
    }

    // Said with numbers, so the spec can hold the restore to the snapshot.
    $tts  = $rf_tt_ids();
    $rels = $tts ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ',', $tts ) . ')' ) : 0;
    echo 'restored ' . wp_json_encode( array( 'rels' => $rels, 'rels_was' => count( (array) $snap['rels'] ), 'terms' => count( $rf_term_ids() ), 'terms_was' => count( $kept ) ) ) . "\n";
};

if ( 'restore' === $rf_mode ) {
    $rf_restore();
    return;
}

if ( 'questions' === $rf_mode ) {
    $state = get_option( VERGEML_TALK_STATE );
    $open  = array();
    foreach ( ( is_array( $state ) && isset( $state['questions'] ) ? (array) $state['questions'] : array() ) as $q ) {
        if ( ! empty( $q['answered'] ) ) {
            continue;
        }
        $ids    = (array) $q['ids'];
        $open[] = array(
            'id'   => (string) $q['id'],
            'kind' => (string) $q['kind'],
            // A sibling or either/or question keys its map by picture; the others list them.
            'ids'  => array_map( 'intval', in_array( (string) $q['kind'], array( 'siblings', 'either' ), true ) ? array_keys( $ids ) : array_values( $ids ) ),
        );
    }
    echo wp_json_encode( array( 'open' => $open, 'running' => is_array( $state ) && ! empty( $state['active'] ) ) ) . "\n";
    return;
}

if ( 'snapshot' !== $rf_mode ) {
    echo "VGML_MODE must be snapshot, questions or restore\n";
    exit( 1 );
}

if ( is_array( get_option( $rf_opt ) ) ) {
    $rf_restore();
}

$rf_tts   = $rf_tt_ids();
$rf_terms = $rf_term_ids();
$rf_snap  = array(
    'rels'     => $rf_tts ? array_map( function ( $r ) { return array( (int) $r[0], (int) $r[1], (int) $r[2] ); }, (array) $wpdb->get_results( "SELECT object_id, term_taxonomy_id, term_order FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ',', $rf_tts ) . ')', ARRAY_N ) ) : array(),
    'terms'    => $rf_terms,
    'termmeta' => $rf_terms ? array_map( function ( $r ) { return array( (int) $r[0], (string) $r[1], (string) $r[2] ); }, (array) $wpdb->get_results( "SELECT term_id, meta_key, meta_value FROM {$wpdb->termmeta} WHERE term_id IN (" . implode( ',', $rf_terms ) . ')', ARRAY_N ) ) : array(),
    'placed'   => array_map( function ( $r ) { return array( (int) $r[0], (string) $r[1] ); }, (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", VERGEML_FILING_PLACED_BY ), ARRAY_N ) ),
    'state'    => get_option( VERGEML_TALK_STATE ),
    'undo'     => get_option( VERGEML_TALK_UNDO ),
    'session'  => get_option( VERGEML_GUIDE_OPTION ),
    'move_id'  => isset( $wpdb->vergeml_librarian_moves ) ? (int) $wpdb->get_var( "SELECT COALESCE(MAX(move_id),0) FROM {$wpdb->vergeml_librarian_moves}" ) : 0,
    'batch_id' => isset( $wpdb->vergeml_librarian_batches ) ? (int) $wpdb->get_var( "SELECT COALESCE(MAX(batch_id),0) FROM {$wpdb->vergeml_librarian_batches}" ) : 0,
);
update_option( $rf_opt, $rf_snap, false );

// The round starts as an owner does, with no fill behind it: the box's own state (thirty open questions since the 09-16 walk) waits in the snapshot.
delete_option( VERGEML_TALK_STATE );
delete_option( VERGEML_TALK_UNDO );
delete_transient( VERGEML_TALK_BEAT );
wp_clear_scheduled_hook( VERGEML_TALK_HOOK );

// phpcs:enable

$rf_described = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

echo wp_json_encode( array(
    'rels'      => count( $rf_snap['rels'] ),
    'terms'     => count( $rf_terms ),
    'placed'    => count( $rf_snap['placed'] ),
    'described' => $rf_described,
    'tree'      => is_array( $rf_snap['session'] ) && isset( $rf_snap['session']['tree'] ) ? (string) $rf_snap['session']['tree'] : '',
) ) . "\n";
