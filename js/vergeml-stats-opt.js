/*
 *  "Share library counts": the switch saves itself through /stats-opt.
 *  Its words arrive in vergemlStatsOpt (wp_localize_script).
 */
( function () {
	var l10n = window.vergemlStatsOpt || {};
	var box = document.getElementById( 'vgml-stats-opt' );
	var note = document.getElementById( 'vgml-stats-note' );
	if ( ! box || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}
	box.addEventListener( 'change', function () {
		box.disabled = true;
		window.wp.apiFetch( {
			path: '/vergeml/v1/stats-opt',
			method: 'POST',
			data: { opted: box.checked }
		} ).then( function ( r ) {
			box.disabled = false;
			if ( note ) {
				note.textContent = r.opted ? l10n.on : l10n.off;
			}
		} ).catch( function () {
			box.disabled = false;
			box.checked = ! box.checked;
			if ( note ) {
				note.textContent = l10n.failed;
			}
		} );
	} );
}() );
