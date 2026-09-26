<?php
/*
 *  Everything the filer could know about each labelled picture, as JSON (2026-09-26).
 *
 *      node tools/box-eval.mjs tools/box-filing-export.php --site shop > shop.json
 *      node tools/box-eval.mjs tools/box-filing-export.php --copy tests/tree/truth-tech.json:/tmp/vgml-truth.json --env VGML_TRUTH=/tmp/vgml-truth.json > tech.json
 *
 *  For tools/filing-lab.mjs, which tries matchers offline against the truth.
 *  The truth is read as box-truth-score.php reads it (the seed stamp, or
 *  VGML_TRUTH). Per picture: the index row whole (caption, the describer's
 *  filing JSON, kind), the file name, title and alt, the post it is attached
 *  to, and the rules' own pick today. Per folder: its path and its profile.
 *  Read-only: nothing moves, nothing is spent.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
wp_set_current_user( 1 );
$tax = vergeml_librarian_taxonomy();

$truth = array();
$file  = (string) getenv( 'VGML_TRUTH' );
if ( '' !== $file && is_readable( $file ) ) {
    foreach ( (array) json_decode( (string) file_get_contents( $file ), true ) as $id => $path ) {
        $truth[ (int) $id ] = (string) $path;
    }
} else {
    foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_vergeml_seed_leaf'", ARRAY_A ) as $r ) {
        $truth[ (int) $r['post_id'] ] = (string) $r['meta_value'];
    }
}

$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
$by_id = array();
foreach ( $terms as $t ) {
    $by_id[ (int) $t->term_id ] = $t;
}
$path_of = function ( $tid ) use ( $by_id ) {
    $out = array();
    $g   = 0;
    while ( $tid && isset( $by_id[ $tid ] ) && $g++ < 32 ) {
        array_unshift( $out, vergeml_term_name( $by_id[ $tid ] ) );
        $tid = (int) $by_id[ $tid ]->parent;
    }
    return implode( ' > ', $out );
};

$ids = array_map( 'intval', array_keys( $by_id ) );
sort( $ids );
$profiles = vergeml_filing_profiles( $ids, $tax );
$folders  = array();
foreach ( $by_id as $tid => $t ) {
    $p = isset( $profiles[ $tid ] ) ? $profiles[ $tid ] : array();
    $folders[ $tid ] = array(
        'path'     => $path_of( $tid ),
        'count'    => (int) $t->count,
        'classes'  => isset( $p['classes'] ) ? $p['classes'] : array(),
        'kinds'    => isset( $p['kinds'] ) ? $p['kinds'] : array(),
        'audience' => isset( $p['audience'] ) ? $p['audience'] : '',
        'view'     => ! empty( $p['view'] ),
        'locked'   => ! empty( $p['locked'] ),
    );
}

$words = vergeml_filing_words_sql( 'i' );
$rows  = (array) $wpdb->get_results( "SELECT i.attachment_id, i.embedding, i.kind, i.filing, i.caption, {$words['select']}, wp_p.post_parent AS parent FROM {$wpdb->vergeml_ai_index} i {$words['join']} WHERE i.error = '' AND i.embedding IS NOT NULL ORDER BY i.attachment_id ASC", ARRAY_A );
foreach ( $rows as $k => $r ) {
    $rows[ $k ]['placed_by'] = '';
}
$picks = vergeml_filing_count( $profiles, $rows )['picks'];

$pictures = array();
foreach ( $rows as $r ) {
    $id = (int) $r['attachment_id'];
    if ( ! isset( $truth[ $id ] ) ) {
        continue;
    }
    $parent = (int) $r['parent'];
    $pp     = $parent ? get_post( $parent ) : null;
    $pick   = isset( $picks[ $id ] ) ? $picks[ $id ] : array();
    $pictures[] = array(
        'id'       => $id,
        'truth'    => $truth[ $id ],
        'file'     => (string) $r['file'],
        'title'    => (string) $r['title'],
        'alt'      => (string) $r['alt'],
        'caption'  => (string) $r['caption'],
        'kind'     => (string) $r['kind'],
        'filing'   => json_decode( (string) $r['filing'], true ),
        'post'     => $pp ? array( 'type' => $pp->post_type, 'title' => $pp->post_title, 'terms' => wp_list_pluck( (array) wp_get_object_terms( $parent, get_object_taxonomies( $pp->post_type ) ), 'name' ) ) : null,
        'folders'  => array_map( $path_of, wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) ) ),
        'rules'    => array(
            'path'       => ! empty( $pick['term_id'] ) ? $path_of( (int) $pick['term_id'] ) : '',
            'outcome'    => isset( $pick['outcome'] ) ? $pick['outcome'] : '',
            'why'        => isset( $pick['why'] ) ? $pick['why'] : '',
            'confidence' => isset( $pick['confidence'] ) ? $pick['confidence'] : '',
            'score'      => isset( $pick['score'] ) ? round( (float) $pick['score'], 3 ) : 0,
        ),
    );
}

echo wp_json_encode( array( 'site' => home_url(), 'taken' => gmdate( 'c' ), 'folders' => array_values( $folders ), 'pictures' => $pictures ) );
