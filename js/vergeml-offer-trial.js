/*
 *  The AI screen's free try, on a site without a licence: Try opens the form,
 *  Start asks admin-ajax.php (vergeml_trial) for a key and follows where it
 *  says to go. Enqueued by core/offer.php, which passes the nonce and the
 *  words as vergemlOfferTrial.
 */
( function () {
	var T      = window.vergemlOfferTrial || {},
		tryBtn = document.getElementById( 'vgml-offer-try' ),
		start  = document.getElementById( 'vgml-offer-start-panel' ),
		form   = document.getElementById( 'vgml-offer-form' ),
		go     = document.getElementById( 'vgml-offer-go' ),
		email  = document.getElementById( 'vgml-offer-email' ),
		msg    = document.getElementById( 'vgml-offer-msg' );
	if ( ! tryBtn ) { return; }
	tryBtn.addEventListener( 'click', function () { start.hidden = true; form.hidden = false; email.focus(); } );
	go.addEventListener( 'click', function () {
		go.disabled = true;
		go.textContent = T.making;
		var body = new URLSearchParams( { action: 'vergeml_trial', _ajax_nonce: T.nonce, email: email.value } );
		fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( r ) {
				if ( r && r.success ) { location.href = r.data.next; return; }
				msg.textContent = ( r && r.data && r.data.message ) || T.failed;
				go.disabled = false;
				go.textContent = T.start;
			} )
			.catch( function () {
				msg.textContent = T.silent;
				go.disabled = false;
			} );
	} );
} )();
