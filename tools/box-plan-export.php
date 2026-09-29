<?php
/*
 *  Every described picture as the planner sees it, as JSON (spec-tree-planner story 8).
 *
 *      MSYS_NO_PATHCONV=1 node tools/box-eval.mjs tools/box-plan-export.php --copy tests/tree/truth-tech.json:/tmp/vgml-truth.json --env VGML_TRUTH=/tmp/vgml-truth.json > tech-all.json
 *
 *  Unlike tools/box-filing-export.php this keeps the pictures the truth does
 *  not label (the planner plans on them too) and runs no matcher, which may
 *  ask the service. Per picture: its label (vergeml_plan_label_of), kind,
 *  object, truth ('' when unlabelled or belonging nowhere; 'labelled' says
 *  which), the folders it sits in now, who placed it there, and its vector rounded to four places.
 *  Read-only, literal SQL; outbound HTTP is refused for the whole run.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'pre_http_request', function () {
    return new WP_Error( 'blocked', 'box-plan-export makes no outbound call' );
}, PHP_INT_MAX );

global $wpdb;
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

$terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, tt.parent, tt.term_taxonomy_id FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s", $tax ), ARRAY_A );
$by_id = array();
$by_tt = array();
foreach ( $terms as $t ) {
    $by_id[ (int) $t['term_id'] ] = $t;
    $by_tt[ (int) $t['term_taxonomy_id'] ] = (int) $t['term_id'];
}
$path_of = function ( $tid ) use ( $by_id ) {
    $out = array();
    $g   = 0;
    while ( $tid && isset( $by_id[ $tid ] ) && $g++ < 32 ) {
        array_unshift( $out, html_entity_decode( $by_id[ $tid ]['name'], ENT_QUOTES ) );
        $tid = (int) $by_id[ $tid ]['parent'];
    }
    return implode( ' > ', $out );
};
$in = array();
if ( $by_tt ) {
    foreach ( $wpdb->get_results( 'SELECT object_id, term_taxonomy_id FROM ' . $wpdb->term_relationships . ' WHERE term_taxonomy_id IN (' . implode( ',', array_keys( $by_tt ) ) . ')', ARRAY_A ) as $r ) {
        $in[ (int) $r['object_id'] ][] = $path_of( $by_tt[ (int) $r['term_taxonomy_id'] ] );
    }
}

$placed = array();
foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", VERGEML_FILING_PLACED_BY ), ARRAY_A ) as $r ) {
    $placed[ (int) $r['post_id'] ] = (string) $r['meta_value'];
}

$rows     = (array) $wpdb->get_results( "SELECT attachment_id, kind, filing, embedding FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL ORDER BY attachment_id ASC", ARRAY_A );
$pictures = array();
foreach ( $rows as $r ) {
    $id     = (int) $r['attachment_id'];
    $filing = json_decode( (string) $r['filing'], true );
    $pictures[] = array(
        'id'       => $id,
        'label'    => vergeml_plan_label_of( $r['kind'], $filing ),
        'kind'     => (string) $r['kind'],
        'object'   => is_array( $filing ) && isset( $filing['object'] ) ? (string) $filing['object'] : '',
        'labelled' => isset( $truth[ $id ] ),
        'truth'    => isset( $truth[ $id ] ) ? $truth[ $id ] : '',
        'folders'  => isset( $in[ $id ] ) ? $in[ $id ] : array(),
        'placed_by' => isset( $placed[ $id ] ) ? $placed[ $id ] : '',
        'vector'   => array_map( function ( $x ) {
            return round( $x, 4 );
        }, (array) vergeml_index_vector_out( $r['embedding'] ) ),
    );
}

echo wp_json_encode( array( 'site' => home_url(), 'taken' => gmdate( 'c' ), 'pictures' => $pictures ) );
