<?php
/*
 *  Flat folders named as paths ("Apparel / Men / Shoes", parent 0) become a
 *  real tree: ancestors are found or made, the folder is moved under the last
 *  one and renamed to its leaf. Members stay. Dry run unless VGML_APPLY=1.
 */

global $wpdb;
$apply = '1' === (string) getenv( 'VGML_APPLY' );
$tax   = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';

$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
if ( is_wp_error( $terms ) ) { echo $terms->get_error_message(), "\n"; return; }

$find = function ( $name, $parent ) use ( $tax ) {
    $found = get_terms( array( 'taxonomy' => $tax, 'name' => $name, 'parent' => (int) $parent, 'hide_empty' => false, 'number' => 1 ) );
    return ( ! is_wp_error( $found ) && $found ) ? (int) $found[0]->term_id : 0;
};

$flat = array_filter( $terms, function ( $t ) { return false !== strpos( $t->name, ' / ' ); } );
printf( "%d flat path-named folders (%s)\n", count( $flat ), $apply ? 'APPLYING' : 'dry run' );

// Shortest paths first, so "Apparel / Men" exists before "Apparel / Men / Shoes" asks for it.
usort( $flat, function ( $a, $b ) { return substr_count( $a->name, ' / ' ) <=> substr_count( $b->name, ' / ' ); } );

foreach ( $flat as $t ) {
    $parts  = array_values( array_filter( array_map( 'trim', explode( ' / ', $t->name ) ), 'strlen' ) );
    $leaf   = array_pop( $parts );
    $parent = (int) $t->parent;
    $made   = array();
    foreach ( $parts as $segment ) {
        $id = $find( $segment, $parent );
        if ( ! $id ) {
            if ( $apply ) {
                $ins = wp_insert_term( $segment, $tax, array( 'parent' => $parent ) );
                $id  = is_wp_error( $ins ) ? 0 : (int) $ins['term_id'];
            }
            $made[] = $segment;
        }
        if ( ! $id && $apply ) { break; }
        $parent = $id;
    }
    $members = count( get_objects_in_term( $t->term_id, $tax ) );
    printf( "  #%-5d %-34s -> %s under #%d%s  (%d members)\n", $t->term_id, $t->name, $leaf, $parent, $made ? ', making ' . implode( ', ', $made ) : '', $members );
    if ( $apply && ( $parent || ! $parts ) ) {
        // A sibling with the leaf's name may already exist under that parent (the matcher's key was the path); merge into it.
        $twin = $find( $leaf, $parent );
        if ( $twin && $twin !== (int) $t->term_id ) {
            foreach ( get_objects_in_term( $t->term_id, $tax ) as $aid ) {
                wp_set_object_terms( (int) $aid, array( $twin ), $tax, true );
                wp_remove_object_terms( (int) $aid, array( (int) $t->term_id ), $tax );
            }
            wp_delete_term( $t->term_id, $tax );
            printf( "         merged into existing #%d and deleted\n", $twin );
            continue;
        }
        $r = wp_update_term( $t->term_id, $tax, array( 'name' => $leaf, 'parent' => $parent, 'slug' => sanitize_title( implode( '-', $parts ) . '-' . $leaf ) ) );
        if ( is_wp_error( $r ) ) { printf( "         FAILED: %s\n", $r->get_error_message() ); }
        if ( function_exists( 'vergeml_filing_forget' ) ) { vergeml_filing_forget( $t->term_id ); }
    }
}
if ( $apply ) {
    clean_term_cache( array_map( function ( $t ) { return $t->term_id; }, $terms ), $tax );
    echo "\n=== tree now\n";
    $all = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
    $by  = array(); foreach ( $all as $t ) { $by[ $t->parent ][] = $t; }
    $walk = function ( $p, $d ) use ( &$walk, &$by, $tax ) {
        foreach ( (array) ( $by[ $p ] ?? array() ) as $t ) { printf( "%s%s (%d)\n", str_repeat( '    ', $d ), $t->name, count( get_objects_in_term( $t->term_id, $tax ) ) ); $walk( $t->term_id, $d + 1 ); }
    };
    $walk( 0, 0 );
}
