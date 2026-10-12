/*
 *  The free try's first run: after the owner's own press on the trial offer
 *  (the server checked that press's nonce before enqueueing this file), the
 *  AI screen presses its alt-text button once the page has loaded.
 */
( function () {
	window.addEventListener( 'load', function () {
		var b = document.getElementById( 'vgml-ai-alt' );
		if ( b && ! b.disabled ) {
			b.click();
		}
	} );
}() );
