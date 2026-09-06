/*
 *  Which settings sections this person had open.
 *
 *  The sections are <details> and the server prints every one of them closed,
 *  so the screen arrives short whatever the browser remembers. This opens back
 *  the ones that were open when they left, and it is the only thing that ever
 *  opens one -- nothing here closes a section somebody opened, because a
 *  settings screen is where two things get changed at once.
 *
 *  Per browser profile, not per account: localStorage. Nothing is sent
 *  anywhere and nothing is stored on the site.
 */
( function () {

	var acc = document.querySelector( '.vgml-acc' );

	if ( ! acc ) {
		return;
	}

	var key = 'vgml-settings-open:' + ( acc.getAttribute( 'data-acc' ) || 'settings' );
	var items = acc.querySelectorAll( '.vgml-acc-item' );

	/*
	 *  localStorage throws in a browser set to block site data, and returns
	 *  whatever was left there by an older version of this file. Both end the
	 *  same way: no section opens, which is the state the server printed.
	 */
	function remembered() {
		try {
			var raw = JSON.parse( window.localStorage.getItem( key ) );
			return Array.isArray( raw ) ? raw : [];
		} catch ( e ) {
			return [];
		}
	}

	function remember( ids ) {
		try {
			window.localStorage.setItem( key, JSON.stringify( ids ) );
		} catch ( e ) {
			// A browser that will not store it still works; it just forgets.
		}
	}

	var open = remembered();

	Array.prototype.forEach.call( items, function ( item ) {

		var id = item.getAttribute( 'data-section' );

		if ( ! id ) {
			return;
		}

		if ( open.indexOf( id ) >= 0 ) {
			item.open = true;
		}

		/*
		 *  'toggle' also fires when find-in-page opens a section to show a
		 *  match, which is the right thing to remember: they were reading it.
		 */
		item.addEventListener( 'toggle', function () {

			var ids = remembered();
			var at = ids.indexOf( id );

			if ( item.open && at < 0 ) {
				ids.push( id );
			} else if ( ! item.open && at >= 0 ) {
				ids.splice( at, 1 );
			} else {
				return;
			}

			remember( ids );
		} );
	} );
}() );
