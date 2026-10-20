<?php
/**
 *  A site whose tags were made before 4.1.0 (keyed vgml_*) upgrades: the tags move to
 *  vergeml_*, their terms and the pictures in them come along (wordpress.org review 8,
 *  2026-10-20: taxonomies must carry the plugin's prefix).
 */

require __DIR__ . '/common.php';

wp_set_current_user( 1 );

switch ( RT_PHASE ) {

	case 'old':
		// What 4.0.x left behind: a registration in vergeml_taxonomies and terms under the old key.
		global $wpdb;
		register_taxonomy( 'vgml_colour', 'attachment', array( 'hierarchical' => false ) );
		$t   = wp_insert_term( 'Blue', 'vgml_colour' );
		$pic = rt_picture( 'blue picture' );
		wp_set_object_terms( $pic, (int) $t['term_id'], 'vgml_colour' );
		update_option( 'vergeml_taxonomies', array(
			'vgml_colour' => array( 'assigned' => 1, 'eml_media' => 1, 'labels' => array( 'name' => 'Colour', 'singular_name' => 'Colour' ), 'hierarchical' => 0, 'rewrite' => array( 'slug' => 'vgml_colour', 'with_front' => 1 ) ),
		) );
		update_option( 'rt_pic', $pic );
		rt_t( 'the old tag holds one picture', 1 === count( wp_get_object_terms( $pic, 'vgml_colour', array( 'fields' => 'ids' ) ) ) );
		rt_done( 'old' );
		break;

	case 'activate':
		rt_quiet( 'activation over 4.0.x tags', rt_watch( function () { $r = activate_plugin( RT_PLUGIN ); return null === $r ? true : $r; } ) );
		rt_done( 'activate' );
		break;

	case 'check':
		global $wpdb;
		$all = get_option( 'vergeml_taxonomies', array() );
		$pic = (int) get_option( 'rt_pic' );
		rt_t( 'the registration is keyed vergeml_colour', isset( $all['vergeml_colour'] ) && ! isset( $all['vgml_colour'] ), implode( ',', array_keys( (array) $all ) ) );
		rt_t( 'its rewrite slug followed', isset( $all['vergeml_colour']['rewrite']['slug'] ) && 'vergeml_colour' === $all['vergeml_colour']['rewrite']['slug'] );
		rt_t( 'vergeml_colour is registered', taxonomy_exists( 'vergeml_colour' ) );
		rt_t( 'no term row is left under vgml_', 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE taxonomy LIKE %s', $wpdb->term_taxonomy, 'vgml\_%' ) ) );
		$names = wp_get_object_terms( $pic, 'vergeml_colour', array( 'fields' => 'names' ) );
		rt_t( 'the picture is still tagged Blue', array( 'Blue' ) === $names, wp_json_encode( $names ) );
		rt_done( 'check' );
		break;
}
