/*
 *  The Licence screen's "Remove from this site": a two-step confirm inline,
 *  not a browser dialog. The question sits where the button was, with Keep
 *  it beside Yes. Enqueued by core/licence-page.php when a key is connected.
 */
( function () {
	var box = document.querySelector( '.vgml-licence-remove' );
	if ( ! box ) { return; }
	var open = box.querySelector( '.vgml-licence-remove-open' );
	var ask = box.querySelector( '.vgml-licence-remove-confirm' );
	var no = box.querySelector( '.vgml-licence-remove-no' );
	open.addEventListener( 'click', function () { open.hidden = true; ask.hidden = false; } );
	no.addEventListener( 'click', function () { ask.hidden = true; open.hidden = false; } );
}() );
