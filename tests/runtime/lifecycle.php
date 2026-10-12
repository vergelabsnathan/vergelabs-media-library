<?php
/**
 *  The plugin's life on one site, from the uploaded zip: activate, work, deactivate,
 *  reactivate, delete. RT_PHASE picks the step; RT_WIPE turns on "remove everything
 *  when deleted" first.
 */

require __DIR__ . '/common.php';

wp_set_current_user( 1 );

switch ( RT_PHASE ) {

	case 'activate':
		rt_t( 'the zip is installed and the plugin not yet loaded', file_exists( WP_PLUGIN_DIR . '/' . RT_PLUGIN ) && ! function_exists( 'vergeml_provision_site' ) );
		rt_quiet( 'activation', rt_watch( function () { $r = activate_plugin( RT_PLUGIN ); return null === $r ? true : $r; } ) );
		rt_t( 'the four tables were made during the activation', 4 === count( rt_tables() ), implode( ',', rt_tables() ) );
		rt_t( 'the version is stamped', defined( 'VERGEML_VERSION' ) && VERGEML_VERSION === get_option( 'vergeml_version' ) );
		rt_done( 'activate' );
		break;

	case 'work':
		rt_t( 'the plugin is loaded as an active plugin', function_exists( 'vergeml_provision_site' ) && is_plugin_active( RT_PLUGIN ) );
		$watched = rt_watch( function () {
			$folder = wp_insert_term( 'Kept folder', 'media_category' );
			if ( is_wp_error( $folder ) ) {
				return $folder;
			}
			$a = rt_picture( 'kept picture' );
			$b = rt_picture( 'deleted picture' );
			wp_set_object_terms( $a, (int) $folder['term_id'], 'media_category' );
			wp_set_object_terms( $b, (int) $folder['term_id'], 'media_category' );
			wp_delete_attachment( $b, true );
			update_option( 'rt_folder', (int) $folder['term_id'] );
			update_option( 'rt_picture', (int) $a );
			return true;
		} );
		rt_quiet( 'filing two pictures and deleting one', $watched );
		$term = get_term( (int) get_option( 'rt_folder' ), 'media_category' );
		rt_t( 'the folder counts the one picture left', $term && ! is_wp_error( $term ) && 1 === (int) $term->count, $term && ! is_wp_error( $term ) ? 'count ' . $term->count : 'no term' );
		if ( defined( 'RT_WIPE' ) && RT_WIPE ) {
			update_option( 'vergeml_uninstall_wipe', 1 );
		}
		rt_done( 'work' );
		break;

	case 'cycle':
		rt_quiet( 'deactivation', rt_watch( function () { deactivate_plugins( RT_PLUGIN ); return ! is_plugin_active( RT_PLUGIN ); } ) );
		rt_quiet( 'reactivation', rt_watch( function () { $r = activate_plugin( RT_PLUGIN ); return null === $r ? true : $r; } ) );
		rt_t( 'the four tables are still there', 4 === count( rt_tables() ) );
		rt_quiet( 'deactivation before delete', rt_watch( function () { deactivate_plugins( RT_PLUGIN ); return true; } ) );
		rt_done( 'cycle' );
		break;

	case 'delete':
		rt_t( 'the plugin is not loaded (deactivated)', ! function_exists( 'vergeml_provision_site' ) );
		$wipe = (bool) get_option( 'vergeml_uninstall_wipe' );
		rt_quiet( 'deleting the plugin' . ( $wipe ? ' (remove everything on)' : '' ), rt_watch( function () { return delete_plugins( array( RT_PLUGIN ) ); } ) );
		rt_t( 'the plugin files are gone', ! file_exists( WP_PLUGIN_DIR . '/vergelabs-media-library' ) );
		global $wpdb;
		$folder = (int) get_option( 'rt_folder' );
		$kept   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'media_category'", $folder ) );
		$in     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.term_id = %d AND tr.object_id = %d", $folder, (int) get_option( 'rt_picture' ) ) );
		rt_t( 'the folder is still there', 1 === $kept );
		rt_t( 'the picture is still in its folder', 1 === $in );
		if ( $wipe ) {
			rt_t( 'remove everything: the plugin\'s own tables are gone', array() === rt_tables(), implode( ',', rt_tables() ) );
			rt_t( 'remove everything: no vergeml_ option is left', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'vergeml\_%'" ) );
		} else {
			rt_t( 'by default the plugin\'s tables stay for a reinstall', 4 === count( rt_tables() ) );
		}
		rt_done( 'delete' );
		break;
}
