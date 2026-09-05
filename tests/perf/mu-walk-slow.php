<?php
/**
 * Plugin Name: VergeML walk: slow passes
 * Description: While the option vergeml_walk_slow is set, the guide's re-filing takes forty pictures a pass, so a Move on a small library lasts long enough to be watched.
 *
 * On the box a Move of a few hundred pictures finishes inside the first pass
 * of the apply request, and the moving state on the Folders screen lasts
 * nothing. The walk in tests/ui/folders.spec.mjs wants to see that state --
 * the button counting, the folders filling -- so the runner sets the option
 * before the walk and deletes it after:
 *
 *     wp option update vergeml_walk_slow 1 --allow-root
 *     wp option delete vergeml_walk_slow --allow-root
 *
 * Drop into wp-content/mu-plugins/. Test-box only; never ships in the plugin.
 */

add_filter( 'vergeml_talk_pass', function ( $n ) {
	return get_option( 'vergeml_walk_slow' ) ? 40 : $n;
} );

add_filter( 'vergeml_talk_slice', function ( $n ) {
	return get_option( 'vergeml_walk_slow' ) ? 40 : $n;
} );
