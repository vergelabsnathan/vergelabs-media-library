<?php
/**
 *  VERGEML_SAFE_MODE set in wp-config.php: the plugin activates and the site works.
 *  Before round 6 the first folder assignment here was a fatal error on PHP 8 (a
 *  term-count callback named but not loaded).
 */

require __DIR__ . '/common.php';

wp_set_current_user( 1 );

switch ( RT_PHASE ) {

	case 'activate':
		rt_t( 'safe mode is set and the plugin not yet loaded', defined( 'VERGEML_SAFE_MODE' ) && VERGEML_SAFE_MODE && ! function_exists( 'vergeml_provision_site' ) );
		rt_quiet( 'activation in safe mode', rt_watch( function () { $r = activate_plugin( RT_PLUGIN ); return null === $r ? true : $r; } ) );
		rt_t( 'no tables are made while the features are off', array() === rt_tables(), implode( ',', rt_tables() ) );
		rt_t( 'the version is not stamped, so the tables are made once safe mode is off', false === get_option( 'vergeml_version' ) );
		rt_done( 'activate' );
		break;

	case 'work':
		rt_t( 'the plugin is loaded, in safe mode', function_exists( 'vergeml_safe_mode' ) && vergeml_safe_mode() );
		$watched = rt_watch( function () {
			if ( ! taxonomy_exists( 'vergeml_folder' ) ) {
				return true; // nothing to file into: the folders are off, and that is not an error
			}
			$folder = wp_insert_term( 'Safe folder', 'vergeml_folder' );
			$a      = rt_picture( 'safe picture' );
			$b      = rt_picture( 'safe deleted' );
			if ( ! is_wp_error( $folder ) ) {
				wp_set_object_terms( $a, (int) $folder['term_id'], 'vergeml_folder' );
				wp_set_object_terms( $b, (int) $folder['term_id'], 'vergeml_folder' );
			}
			wp_delete_attachment( $b, true );
			return true;
		} );
		rt_quiet( 'filing and deleting pictures in safe mode', $watched );
		rt_t( 'vergeml_folder is ' . ( taxonomy_exists( 'vergeml_folder' ) ? 'registered' : 'not registered' ) . ' in safe mode', true );
		list( , $notice ) = rt_watch( function () { do_action( 'admin_notices' ); return true; } );
		rt_t( 'the admin notice says the plugin is in safe mode', false !== strpos( $notice, 'safe mode' ), substr( wp_strip_all_tags( $notice ), 0, 160 ) );
		rt_done( 'work' );
		break;
}
