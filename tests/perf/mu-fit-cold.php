<?php
/**
 * Plugin Name: VergeML walk: the dry run gives up
 * Description: While a request carries vgml_fit_cold, the guide's dry run gets a budget of nothing and answers null, so the screen's "no numbers yet" state can be walked on a warm box.
 *
 * vergeml_guide_draft_fit() stops at twenty seconds and answers nothing rather
 * than half a count. On this box, warm, the run takes seven to ten seconds and
 * never reaches that -- which is the right state for the box to be in and the
 * wrong one for proving what the screen does when it does not know.
 *
 * The budget is a filter, so the walk asks for it per request:
 *
 *     wp.apiFetch( { path: '/vergeml/v1/guide/turn?vgml_fit_cold=1', ... } )
 *
 * One request, no option to leave behind, and nothing to clean up if a spec
 * dies mid-way. tests/ui/folders.spec.mjs is the only caller.
 *
 * Drop into wp-content/mu-plugins/. Test-box only; never ships in the plugin.
 */

add_filter( 'vergeml_guide_fit_budget', function ( $n ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, test box, no state changes.
	return isset( $_GET['vgml_fit_cold'] ) ? 0 : $n;
} );
