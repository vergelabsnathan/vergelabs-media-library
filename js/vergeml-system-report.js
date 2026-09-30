/*
 *  Copy system report, on the Utilities screen. Enqueued by
 *  core/system-report.php where the report is printed.
 */
( function () {
	var button = document.getElementById( 'vergeml-copy-report' );
	var field  = document.getElementById( 'vergeml-system-report' );
	var done   = document.getElementById( 'vergeml-copy-report-done' );
	if ( ! button || ! field ) { return; }

	field.addEventListener( 'click', function () { field.select(); } );

	button.addEventListener( 'click', function () {
		field.select();
		/*
		 *  navigator.clipboard is unavailable over plain http, which plenty
		 *  of local and staging installs still are. execCommand is
		 *  deprecated but it is the one that works there, so it is the
		 *  fallback rather than the other way round.
		 */
		var copied = false;
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( field.value ).then( function () {
				done.hidden = false;
			} );
			copied = true;
		}
		if ( ! copied ) {
			try { copied = document.execCommand( 'copy' ); } catch ( e ) { copied = false; }
			if ( copied ) { done.hidden = false; }
		}
	} );
} )();
