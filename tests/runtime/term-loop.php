<?php
/**
 *  A parent loop in the terms table -- A under B, B under A, C under itself -- and
 *  every walk over the folder tree run on it (round 7, 2026-10-15: "recursive ZIP
 *  traversal has no visited-node or depth limit"). WordPress refuses such a loop
 *  through its API; a hand edit or another tool's import can still write one, so
 *  the rows are written here with $wpdb, inside this Playground only.
 *
 *  A walk that starts at the root never reaches a loop (each term has one parent),
 *  so only the ZIP, which starts at the folder asked for, could recurse without
 *  end; the root walks are run too, so a change that lets them start elsewhere
 *  is caught here.
 */

require __DIR__ . '/common.php';

wp_set_current_user( 1 );

switch ( RT_PHASE ) {

	case 'activate':
		rt_quiet( 'activation', rt_watch( function () { $r = activate_plugin( RT_PLUGIN ); return null === $r ? true : $r; } ) );
		rt_done( 'activate' );
		break;

	case 'loop':
		global $wpdb;
		register_taxonomy( 'product_cat', 'post', array( 'hierarchical' => true ) );

		$ids = array();
		foreach ( array( 'vergeml_folder' => array( 'Loop A', 'Loop B', 'Loop C' ), 'product_cat' => array( 'Shop A', 'Shop B' ) ) as $tax => $names ) {
			foreach ( $names as $name ) {
				$t = wp_insert_term( $name, $tax );
				$ids[ $name ] = is_wp_error( $t ) ? 0 : (int) $t['term_taxonomy_id'];
			}
		}
		$set_parent = function ( $child, $parent ) use ( $wpdb, $ids ) {
			$parent_term = (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $ids[ $parent ] ) );
			$wpdb->update( $wpdb->term_taxonomy, array( 'parent' => $parent_term ), array( 'term_taxonomy_id' => $ids[ $child ] ) );
		};
		$set_parent( 'Loop A', 'Loop B' );
		$set_parent( 'Loop B', 'Loop A' );
		$set_parent( 'Loop C', 'Loop C' );
		$set_parent( 'Shop A', 'Shop B' );
		$set_parent( 'Shop B', 'Shop A' );
		delete_option( 'vergeml_folder_children' );
		delete_option( 'product_cat_children' );
		wp_cache_flush();

		$a_term = (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $ids['Loop A'] ) );
		$c_term = (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $ids['Loop C'] ) );
		$pic    = rt_picture( 'loop picture' );
		wp_set_object_terms( $pic, $a_term, 'vergeml_folder' );

		$b_term   = (int) get_term( $a_term, 'vergeml_folder' )->parent;
		$kids_of  = function ( $p ) { return wp_list_pluck( get_terms( array( 'taxonomy' => 'vergeml_folder', 'hide_empty' => false, 'parent' => $p ) ), 'term_id' ); };
		$a_kids   = $kids_of( $a_term );
		$b_kids   = $kids_of( $b_term );
		rt_t( 'get_terms sees the loop: B is a child of A and A of B', in_array( $b_term, $a_kids, true ) && in_array( $a_term, $b_kids, true ), "A=$a_term kids " . wp_json_encode( $a_kids ) . "; B=$b_term kids " . wp_json_encode( $b_kids ) . '; rows ' . wp_json_encode( $wpdb->get_results( $wpdb->prepare( "SELECT term_taxonomy_id, term_id, parent FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", 'vergeml_folder' ) ) ) . ' ids ' . wp_json_encode( $ids ) );

		foreach ( array( 'A (A under B under A)' => $a_term, 'C (under itself)' => $c_term ) as $label => $start ) {
			$zip_path = trailingslashit( get_temp_dir() ) . 'vgml-loop-' . $start . '.zip';
			$watched  = rt_watch( function () use ( $start, $zip_path ) {
				$r = vergeml_zip_folder( $start, 'vergeml_folder', $zip_path );
				return is_wp_error( $r ) && 'vergeml_zip_empty' === $r->get_error_code() ? true : $r;
			} );
			rt_quiet( "ZIP of folder $label", $watched );
		}
		$zipped = vergeml_zip_folder( $a_term, 'vergeml_folder', trailingslashit( get_temp_dir() ) . 'vgml-loop-count.zip' );
		rt_t( 'the ZIP of A holds the one picture once', is_array( $zipped ) && 1 === $zipped['added'], wp_json_encode( $zipped ) );

		rt_quiet( 'gallery widget folder list', rt_watch( function () { return is_array( vergeml_gallery_folder_options() ); } ) );
		rt_quiet( 'gallery block folder list', rt_watch( function () { return ! is_wp_error( vergeml_rest_gallery_folders( new WP_REST_Request( 'GET', '/' ) ) ); } ) );
		rt_quiet( 'shop category paths', rt_watch( function () { return is_array( vergeml_folders_product_paths() ); } ) );
		rt_quiet( 'list-mode Move to folder options', rt_watch( function () { return is_array( vergeml_list_folder_options( 'vergeml_folder' ) ); } ) );
		rt_done( 'loop' );
		break;
}
