<?php
/*
 *  The wrong sures with the model in, one line each: id, the truth, the pick,
 *  what the describer said, the thumbnail's URL. For Nathan's eye (S18).
 *
 *      node tools/box-eval.mjs tools/box-truth-wrong-sures.php --copy tests/tree/truth-tech.json:/tmp/vgml-truth.json --env VGML_TRUTH=/tmp/vgml-truth.json
 *
 *  Read-only apart from the model's cache (already warm).
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
global $wpdb;
wp_set_current_user( 1 );
$tax   = vergeml_librarian_taxonomy();
$truth = array();
foreach ( (array) json_decode( (string) file_get_contents( (string) getenv( 'VGML_TRUTH' ) ), true ) as $id => $path ) {
    $truth[ (int) $id ] = (string) $path;
}
$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
$by_id = array();
foreach ( $terms as $t ) {
    $by_id[ (int) $t->term_id ] = $t;
}
$path_of = function ( $tid ) use ( $by_id ) {
    $out = array();
    while ( $tid && isset( $by_id[ $tid ] ) ) {
        array_unshift( $out, mb_strtolower( vergeml_term_name( $by_id[ $tid ] ) ) );
        $tid = (int) $by_id[ $tid ]->parent;
    }
    return implode( ' > ', $out );
};
$by_path = array();
foreach ( $by_id as $tid => $t ) {
    $by_path[ $path_of( $tid ) ] = $tid;
}
$ids      = array_map( 'intval', array_keys( $by_id ) );
sort( $ids );
$profiles = vergeml_filing_profiles( $ids, $tax );
$words    = vergeml_filing_words_sql( 'i' );
$rows     = (array) $wpdb->get_results( "SELECT i.attachment_id, i.embedding, i.kind, i.filing, i.caption, {$words['select']} FROM {$wpdb->vergeml_ai_index} i {$words['join']} WHERE i.error = '' AND i.embedding IS NOT NULL AND i.attachment_id IN (" . implode( ',', array_map( 'intval', array_keys( $truth ) ) ) . ") ORDER BY i.attachment_id ASC", ARRAY_A );
foreach ( $rows as $k => $r ) {
    $rows[ $k ]['placed_by'] = '';
}
$rows  = vergeml_filing_ask_model( $rows, $profiles );
$picks = vergeml_filing_count( $profiles, $rows )['picks'];
$says  = array();
foreach ( $rows as $r ) {
    $says[ (int) $r['attachment_id'] ] = vergeml_filing_model_says( $r );
}
foreach ( $truth as $id => $path ) {
    if ( ! isset( $picks[ $id ] ) ) {
        continue;
    }
    $want = mb_strtolower( trim( preg_replace( '/\s*[>\/]\s*/u', ' > ', $path ) ) );
    $p    = $picks[ $id ];
    if ( 'sure' !== $p['confidence'] || ! $p['term_id'] ) {
        continue;
    }
    $got = $path_of( (int) $p['term_id'] );
    if ( $got === $want ) {
        continue;
    }
    printf( "%d\t%s\t%s\t%s\t%s\t%s\n", $id, '' === $want ? '(no folder)' : $want, $got, $p['why'], $says[ $id ]['says'] . ' — ' . $says[ $id ]['caption'], wp_get_attachment_image_url( $id, 'medium' ) );
}
