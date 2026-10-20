<?php
/**
 *  What deleting the plugin removes: its scheduled events always, and with the
 *  wipe switched on its options, meta and transients -- never WordPress's own.
 *
 *      node tools/verify.mjs uninstall
 *
 *  wordpress.org rulebook audit (HB-149): uninstall cleared two of the seven
 *  hooks the plugin books, and the opt-in wipe left the plugin's post meta,
 *  term meta and site transients behind.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['vergeml_ok']  = 0;
$GLOBALS['vergeml_bad'] = 0;

function vergeml_t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['vergeml_ok']++ : $GLOBALS['vergeml_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, '' !== $detail ? '  -- ' . $detail : '' );
}

echo "\nuninstall\n\n";

wp_set_current_user( 1 );

$hooks = array( 'vergeml_meaning_convert', 'vergeml_ai_run_tick', 'vergeml_talk_refile_event', 'vergeml_guide_fit_event', 'vergeml_plan_inventory_refresh', 'vergeml_plan_event', 'vergeml_provision_site' );

foreach ( $hooks as $i => $hook ) {
	// Some with arguments: wp_clear_scheduled_hook() without them would miss those.
	wp_schedule_single_event( time() + 3600 + $i, $hook, $i % 2 ? array( 7 ) : array() );
}

$att  = wp_insert_attachment( array( 'post_title' => 'vgml uninstall', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
$term = wp_insert_term( 'vgml-uninstall-' . wp_rand(), 'media_category' );
$term = is_wp_error( $term ) ? 0 : (int) $term['term_id'];

update_post_meta( $att, '_wp_attachment_image_alt', 'kept: WordPress owns this' );
update_post_meta( $att, '_vergeml_ai', 'x' );
update_post_meta( $att, '_vergeml_placed_by', 'user' );
update_term_meta( $term, 'vergeml_color', '#123456' );
update_term_meta( $term, '_vergeml_profile_members', 'x' );
set_site_transient( 'vergeml_probe', 1, 600 );
update_option( 'vergeml_uninstall_wipe', '1' );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', plugin_basename( VERGEML_FILE ) );
}
include plugin_dir_path( VERGEML_FILE ) . 'uninstall.php';

wp_cache_flush();

$left = array();
foreach ( $hooks as $hook ) {
	if ( false !== wp_next_scheduled( $hook ) || false !== wp_next_scheduled( $hook, array( 7 ) ) ) {
		$left[] = $hook;
	}
}
vergeml_t( 'every hook the plugin books is cleared, with or without arguments', ! $left, implode( ', ', $left ) );
vergeml_t( 'the plugin\'s post meta is gone', '' === get_post_meta( $att, '_vergeml_ai', true ) && '' === get_post_meta( $att, '_vergeml_placed_by', true ) );
vergeml_t( 'WordPress\'s own alt text stays', 'kept: WordPress owns this' === get_post_meta( $att, '_wp_attachment_image_alt', true ) );
vergeml_t( 'the plugin\'s term meta is gone', '' === get_term_meta( $term, 'vergeml_color', true ) && '' === get_term_meta( $term, '_vergeml_profile_members', true ) );
vergeml_t( 'its site transients are gone', false === get_site_transient( 'vergeml_probe' ) );
vergeml_t( 'its options are gone', false === get_option( 'vergeml_uninstall_wipe', false ) && false === get_option( 'vergeml_taxonomies', false ) );
vergeml_t( 'the media file itself stays', 'attachment' === get_post_type( $att ) );

wp_delete_attachment( $att, true );

printf( "\n%d/%d passed\n\n", $GLOBALS['vergeml_ok'], $GLOBALS['vergeml_ok'] + $GLOBALS['vergeml_bad'] );
exit( $GLOBALS['vergeml_bad'] ? 1 : 0 );
