/*
 *  Demo mode on the Licence screen: the switch saves itself through the AI
 *  settings route, which writes only the flags it is sent.
 */
( function () {
	var box = document.getElementById( 'vgml-demo-mode' );
	if ( ! box || box.disabled || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}
	box.addEventListener( 'change', function () {
		box.disabled = true;
		window.wp.apiFetch( {
			path: '/vergeml/v1/ai-settings',
			method: 'POST',
			data: { mock: box.checked ? 1 : 0 }
		} ).then( function () {
			box.disabled = false;
		} ).catch( function () {
			box.disabled = false;
			box.checked = ! box.checked;
		} );
	} );
}() );
