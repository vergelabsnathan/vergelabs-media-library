<?php
/**
 *  A site from before the prefixes upgrades (wordpress.org review 8, 2026-10-20/22): folders
 *  in media_category move to vergeml_folder, 4.0.x tags keyed vgml_* move to vergeml_tag_*,
 *  and the pictures in them come along. With Enhanced Media Library active (RT_EML) its
 *  folders are left where they are until it is off, then the next admin visit moves them.
 */

require __DIR__ . '/common.php';

wp_set_current_user( 1 );

switch ( RT_PHASE ) {

	case 'old':
		// What 4.0.x (or Enhanced Media Library) left behind: registrations in the option, terms under the old keys.
		register_taxonomy( 'media_category', 'attachment', array( 'hierarchical' => true ) );
		register_taxonomy( 'vgml_colour', 'attachment', array( 'hierarchical' => false ) );
		$folder = wp_insert_term( 'Old folder', 'media_category' );
		$child  = wp_insert_term( 'Old child', 'media_category', array( 'parent' => (int) $folder['term_id'] ) );
		$tag    = wp_insert_term( 'Blue', 'vgml_colour' );
		$pic    = rt_picture( 'old picture' );
		wp_set_object_terms( $pic, (int) $child['term_id'], 'media_category' );
		wp_set_object_terms( $pic, (int) $tag['term_id'], 'vgml_colour' );
		update_option( 'vergeml_taxonomies', array(
			'media_category' => array( 'assigned' => 1, 'eml_media' => 1, 'labels' => array( 'name' => 'Media Categories', 'singular_name' => 'Media Category' ), 'hierarchical' => 1, 'rewrite' => array( 'slug' => 'media_category', 'with_front' => 1 ) ),
			'vgml_colour'    => array( 'assigned' => 1, 'eml_media' => 1, 'labels' => array( 'name' => 'Colour', 'singular_name' => 'Colour' ), 'hierarchical' => 0, 'rewrite' => array( 'slug' => 'vgml_colour', 'with_front' => 1 ) ),
		) );
		update_option( 'rt_pic', $pic );
		rt_t( 'the old folder holds the picture', 1 === count( wp_get_object_terms( $pic, 'media_category', array( 'fields' => 'ids' ) ) ) );
		rt_done( 'old' );
		break;

	case 'activate':
		rt_quiet( 'activation over the old keys', rt_watch( function () { $r = activate_plugin( RT_PLUGIN ); return null === $r ? true : $r; } ) );
		rt_done( 'activate' );
		break;

	case 'check':
		global $wpdb;
		$all = get_option( 'vergeml_taxonomies', array() );
		$pic = (int) get_option( 'rt_pic' );
		$rows = function ( $taxonomy ) use ( $wpdb ) {
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE taxonomy = %s', $wpdb->term_taxonomy, $taxonomy ) );
		};

		rt_t( 'the tag is keyed vergeml_tag_colour', isset( $all['vergeml_tag_colour'] ) && ! isset( $all['vgml_colour'] ), implode( ',', array_keys( (array) $all ) ) );
		rt_t( 'no term row is left under vgml_colour', 0 === $rows( 'vgml_colour' ) );
		rt_t( 'the picture is still tagged Blue', array( 'Blue' ) === wp_get_object_terms( $pic, 'vergeml_tag_colour', array( 'fields' => 'names' ) ) );

		if ( defined( 'RT_EML' ) && RT_EML ) {
			rt_t( 'Enhanced Media Library is active', function_exists( 'vergeml_eml_active' ) && vergeml_eml_active() );
			rt_t( 'its folders are left in media_category while it runs', 2 === $rows( 'media_category' ) && isset( $all['media_category'] ) );
			if ( function_exists( 'vergeml_term_relationship_cleanup' ) ) {
				rt_quiet( 'Complete Cleanup\'s term wipe while it runs', rt_watch( function () { vergeml_term_relationship_cleanup(); return true; } ) );
				rt_t( 'the wipe left its folders and the picture in them', 2 === $rows( 'media_category' ) && 1 === count( wp_get_object_terms( (int) get_option( 'rt_pic' ), 'media_category', array( 'fields' => 'ids' ) ) ) );
			}
			deactivate_plugins( 'enhanced-media-library/enhanced-media-library.php' );
			rt_t( 'Enhanced Media Library is off', ! vergeml_eml_active() );
			rt_quiet( 'the next admin visit', rt_watch( function () { vergeml_migrate_prefixes_when_due(); return true; } ) );
			$all = get_option( 'vergeml_taxonomies', array() );
			register_taxonomy( 'vergeml_folder', 'attachment', array( 'hierarchical' => true ) );
		}

		rt_t( 'the folders are keyed vergeml_folder', isset( $all['vergeml_folder'] ) && ! isset( $all['media_category'] ), implode( ',', array_keys( (array) $all ) ) );
		rt_t( 'its rewrite slug followed', isset( $all['vergeml_folder']['rewrite']['slug'] ) && 'vergeml_folder' === $all['vergeml_folder']['rewrite']['slug'] );
		rt_t( 'both folders moved, none left in media_category', 2 === $rows( 'vergeml_folder' ) && 0 === $rows( 'media_category' ), $rows( 'vergeml_folder' ) . ' / ' . $rows( 'media_category' ) );
		rt_t( 'the picture is still in Old child', array( 'Old child' ) === wp_get_object_terms( $pic, 'vergeml_folder', array( 'fields' => 'names' ) ) );
		$child = get_term_by( 'name', 'Old child', 'vergeml_folder' );
		$top   = get_term_by( 'name', 'Old folder', 'vergeml_folder' );
		rt_t( 'Old child is still under Old folder', $child && $top && (int) $child->parent === (int) $top->term_id );
		rt_done( 'check' );
		break;
}
