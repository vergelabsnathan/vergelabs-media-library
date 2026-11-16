/*
 *  The File types screen's filter: a search box and kind buttons over the
 *  MIME type rows. Enqueued by core/options-pages.php on that screen, which
 *  passes the "%1$s of %2$s types shown" words as vergemlFileTypes.shownOf.
 */
( function () {
	var q = document.getElementById( 'vgml-ft-q' );
	var kinds = document.querySelectorAll( '.vgml-ft-kinds button' );
	var rows = document.querySelectorAll( '.vergeml-mime-type-list tbody tr[data-kind]' );
	var count = document.getElementById( 'vgml-ft-count' );
	var empty = document.getElementById( 'vgml-ft-empty' );
	var clear = document.getElementById( 'vgml-ft-clear' );
	var kind = 'all';
	var total = rows.length;
	var shownOf = ( window.vergemlFileTypes || {} ).shownOf;
	var sections = document.querySelectorAll( '.vgml-acc-item' );
	/*
	 *  A filter that hides rows inside closed sections
	 *  finds nothing a person can see. So a filter opens
	 *  the sections that still have a row and closes the
	 *  ones that do not; with no filter on, the sections
	 *  are theirs again and js/vergeml-settings.js has
	 *  the last word.
	 */
	function apply() {
		var needle = ( q.value || '' ).trim().toLowerCase();
		var shown = 0;
		var live = {};
		Array.prototype.forEach.call( rows, function ( r ) {
			var ok = ( kind === 'all' || r.getAttribute( 'data-kind' ) === kind ) && ( ! needle || r.getAttribute( 'data-search' ).indexOf( needle ) >= 0 );
			r.classList.toggle( 'is-hidden', ! ok );
			if ( ok ) { shown++; live[ r.getAttribute( 'data-kind' ) ] = true; }
		} );
		if ( 'all' !== kind || needle ) {
			Array.prototype.forEach.call( sections, function ( s ) {
				s.open = live[ s.getAttribute( 'data-section' ) ] === true;
			} );
		}
		count.textContent = shownOf.replace( '%1$s', String( shown ) ).replace( '%2$s', String( total ) );
		empty.hidden = shown > 0;
	}
	q.addEventListener( 'input', apply );
	Array.prototype.forEach.call( kinds, function ( b ) {
		b.addEventListener( 'click', function () {
			kind = b.getAttribute( 'data-kind' );
			Array.prototype.forEach.call( kinds, function ( x ) { x.classList.toggle( 'is-on', x === b ); } );
			apply();
		} );
	} );
	clear.addEventListener( 'click', function () {
		q.value = '';
		kind = 'all';
		Array.prototype.forEach.call( kinds, function ( x ) { x.classList.toggle( 'is-on', x.getAttribute( 'data-kind' ) === 'all' ); } );
		apply();
	} );
	apply();
}() );
