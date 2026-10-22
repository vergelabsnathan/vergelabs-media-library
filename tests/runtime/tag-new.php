<?php
/**
 *  A tag made after the round 8 rename (2026-10-20): keyed vergeml_*, never longer than
 *  WordPress's 32 characters even from a long name, registered at once, and holding a picture.
 */

require __DIR__ . '/common.php';

wp_set_current_user( 1 );

switch ( RT_PHASE ) {

	case 'activate':
		rt_quiet( 'activation', rt_watch( function () { $r = activate_plugin( RT_PLUGIN ); return null === $r ? true : $r; } ) );
		rt_done( 'activate' );
		break;

	case 'make':
		rt_t( 'the tag maker is loaded', function_exists( 'vergeml_guide_tag_taxonomy' ) );
		foreach ( array( 'Colour', 'Photographer who took the picture in the field' ) as $name ) {
			$made = null;
			rt_quiet( "making the tag \"$name\"", rt_watch( function () use ( $name, &$made ) { $made = vergeml_guide_tag_taxonomy( $name ); return is_array( $made ); } ) );
			$key = is_array( $made ) ? (string) $made['taxonomy'] : '';
			rt_t( "\"$name\" is keyed with the plugin's prefix", 0 === strpos( $key, 'vergeml_' ), $key );
			rt_t( "\"$name\" fits WordPress's 32 characters", '' !== $key && strlen( $key ) <= 32, $key . ' (' . strlen( $key ) . ')' );
			rt_t( "\"$name\" is registered on attachments", taxonomy_exists( $key ) && in_array( 'attachment', (array) get_taxonomy( $key )->object_type, true ) );
			$term = taxonomy_exists( $key ) ? wp_insert_term( 'Sample', $key ) : null;
			$pic  = rt_picture( 'tagged ' . $key );
			$set  = is_array( $term ) ? wp_set_object_terms( $pic, (int) $term['term_id'], $key ) : null;
			rt_t( "a picture can be tagged with \"$name\"", is_array( $set ) && array( 'Sample' ) === wp_get_object_terms( $pic, $key, array( 'fields' => 'names' ) ) );
			$all = get_option( 'vergeml_taxonomies', array() );
			rt_t( "\"$name\" is in the plugin's list, so it is registered on every load", isset( $all[ $key ] ) );
		}
		rt_done( 'make' );
		break;
}
