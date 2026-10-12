/*
 *  The system report on the Get help screen: one click selects all of it, so
 *  it can be copied into a forum post or an email.
 */
( function () {
	var f = document.getElementById( 'vergeml-help-report' );
	if ( f ) {
		f.addEventListener( 'click', function () {
			f.select();
		} );
	}
}() );
