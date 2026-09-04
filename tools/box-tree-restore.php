<?php
/*
 *  Put the box's folder tree back the way it was on 2026-09-04 morning: the
 *  parents the undo could not restore (it recreates folders flat), and the two
 *  empty folders a test left behind. The names come from the design handoff,
 *  which was drawn from this very tree.
 */
$tax  = 'media_category';
$tree = array(
    'Landscape and nature' => array( 'Mountains and mist', 'Meadow and blossom', 'Rural farmland', 'Piers and sunsets' ),
    'Architecture'         => array( 'City architecture details', 'Manhattan skyline' ),
    'Apparel'              => array( 'Denim styling' ),
    'Objects'              => array( 'Phones and gadgets', 'Edison bulb lighting', 'Cosmetics and personal care' ),
);
$id = function ( $name ) use ( $tax ) {
    $t = get_terms( array( 'taxonomy' => $tax, 'name' => $name, 'hide_empty' => false, 'number' => 1 ) );
    return ( ! is_wp_error( $t ) && $t ) ? (int) $t[0]->term_id : 0;
};
foreach ( $tree as $parent => $children ) {
    $pid = $id( $parent );
    if ( ! $pid ) { printf( "missing parent %s\n", $parent ); continue; }
    foreach ( $children as $child ) {
        $cid = $id( $child );
        if ( ! $cid ) { printf( "missing child %s\n", $child ); continue; }
        $r = wp_update_term( $cid, $tax, array( 'parent' => $pid ) );
        printf( "%-30s -> %s %s\n", $child, $parent, is_wp_error( $r ) ? 'ERROR ' . $r->get_error_message() : 'ok' );
    }
}
foreach ( array( 'Landscapes', 'Women' ) as $left ) {
    $tid = $id( $left );
    if ( $tid ) {
        $term = get_term( $tid, $tax );
        if ( $term && 0 === (int) $term->count ) {
            wp_delete_term( $tid, $tax );
            printf( "removed empty test folder %s\n", $left );
        } else {
            printf( "kept %s (%d files)\n", $left, $term ? $term->count : -1 );
        }
    }
}
if ( function_exists( 'vergeml_journey_touch' ) ) { vergeml_journey_touch(); }
echo "\n== tree ==\n";
$walk = function ( $parent, $depth ) use ( &$walk, $tax ) {
    foreach ( get_terms( array( 'taxonomy' => $tax, 'parent' => $parent, 'hide_empty' => false ) ) as $t ) {
        printf( "%s%s (%d)\n", str_repeat( '  ', $depth ), $t->name, $t->count );
        $walk( $t->term_id, $depth + 1 );
    }
};
$walk( 0, 0 );
