<?php
/**
 *  The pictures a describer eval runs on, read off a site (S11). Read-only.
 *
 *      node tools/box-eval.mjs tools/box-eval-set.php --env VGML_IDS=1,2,3 [--env VGML_TO_SORT=21] [--site shop]
 *
 *  For every id: its file's URL and type, the folder it sits in (its path)
 *  and that folder's profile words -- what the eval scores a describer's
 *  phrases against. VGML_TO_SORT=n adds the first n pictures of the To sort
 *  folder by id, with no folder of their own: for them the eval asks whether
 *  the describer names any leaf of the tree at all. The tree's leaves come
 *  with their words for that. Nothing is written, no model is reached.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tax = vergeml_librarian_taxonomy();
$ids = array_values( array_filter( array_map( 'intval', explode( ',', (string) getenv( 'VGML_IDS' ) ) ) ) );
$n   = max( 0, (int) getenv( 'VGML_TO_SORT' ) );

$path_of = function ( $tid ) use ( $tax ) {
    $t = get_term( (int) $tid, $tax );
    if ( ! ( $t instanceof WP_Term ) ) {
        return '';
    }
    $path = array( vergeml_term_name( $t ) );
    foreach ( get_ancestors( (int) $t->term_id, $tax, 'taxonomy' ) as $aid ) {
        $a = get_term( (int) $aid, $tax );
        if ( $a instanceof WP_Term ) {
            array_unshift( $path, vergeml_term_name( $a ) );
        }
    }
    return implode( ' / ', $path );
};
$words_of = function ( $tid ) use ( $tax ) {
    $p = function_exists( 'vergeml_filing_profile' ) ? vergeml_filing_profile( (int) $tid, $tax ) : null;
    return is_array( $p ) && isset( $p['classes'] ) ? array_values( array_map( 'strval', (array) $p['classes'] ) ) : array();
};

$to_sort = get_term_by( 'slug', VERGEML_FILING_TO_SORT_SLUG, $tax );
$parked  = array();
if ( $n > 0 && $to_sort instanceof WP_Term ) {
    $held = get_objects_in_term( (int) $to_sort->term_id, $tax );
    $held = is_wp_error( $held ) ? array() : array_map( 'intval', (array) $held );
    sort( $held );
    $parked = array_slice( $held, 0, $n );
}

$rows = array();
foreach ( array_merge( $ids, $parked ) as $id ) {
    $url = wp_get_attachment_url( $id );
    if ( ! $url ) {
        continue;
    }
    $in    = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
    $in    = is_wp_error( $in ) ? array() : array_map( 'intval', $in );
    $own   = array_values( array_filter( $in, function ( $t ) use ( $to_sort ) { return ! ( $to_sort instanceof WP_Term ) || $t !== (int) $to_sort->term_id; } ) );
    $rows[] = array(
        'id'      => (int) $id,
        'url'     => $url,
        'mime'    => (string) get_post_mime_type( $id ),
        'title'   => (string) get_the_title( $id ),
        'parked'  => in_array( (int) $id, $parked, true ),
        'folder'  => $own ? $path_of( $own[0] ) : '',
        'words'   => $own ? $words_of( $own[0] ) : array(),
    );
}

$leaves = array();
foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) as $t ) {
    if ( $to_sort instanceof WP_Term && (int) $t->term_id === (int) $to_sort->term_id ) {
        continue;
    }
    $leaves[] = array( 'path' => $path_of( $t->term_id ), 'name' => vergeml_term_name( $t ), 'words' => $words_of( $t->term_id ) );
}

echo wp_json_encode( array( 'site' => home_url(), 'rows' => $rows, 'leaves' => $leaves ) ) . "\n";
