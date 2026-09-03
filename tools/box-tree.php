<?php
/* The folder tree as it stands, with counts and a few members each. Read-only. */
global $wpdb;
$tax = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';
$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
if ( is_wp_error( $terms ) ) { echo $terms->get_error_message(), "\n"; return; }
$by_parent = array(); foreach ( $terms as $t ) { $by_parent[ $t->parent ][] = $t; }
$walk = function ( $parent, $depth ) use ( &$walk, &$by_parent, $wpdb, $tax ) {
    foreach ( (array) ( $by_parent[ $parent ] ?? array() ) as $t ) {
        $ids = get_objects_in_term( $t->term_id, $tax ); $ids = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
        printf( "%s%s  (%d)\n", str_repeat( '    ', $depth ), $t->name, count( $ids ) );
        foreach ( array_slice( $ids, 0, 5 ) as $id ) {
            $f = json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT filing FROM {$wpdb->vergeml_ai_index} WHERE attachment_id = %d", $id ) ), true );
            printf( "%s    · %s  [%s]\n", str_repeat( '    ', $depth ), mb_substr( get_the_title( $id ), 0, 40 ), is_array( $f ) ? mb_substr( (string) ( $f['object'] ?? '' ), 0, 30 ) : '' );
        }
        $walk( $t->term_id, $depth + 1 );
    }
};
$walk( 0, 0 );
printf( "\nfolder-talk state: %s\n", wp_json_encode( array_intersect_key( (array) get_option( VERGEML_TALK_STATE ), array_flip( array( 'active', 'moved', 'skipped', 'seen', 'total', 'counts' ) ) ) ) );
$moves = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}vergeml_librarian_moves" );
printf( "librarian moves recorded: %s   last batch: %s\n", var_export( $moves, true ), var_export( $wpdb->get_var( "SELECT MAX(created_at) FROM {$wpdb->prefix}vergeml_librarian_batches" ), true ) );
