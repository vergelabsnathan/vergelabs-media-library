/*
 *  The quick actions.
 *
 *  A dashboard whose every button navigates somewhere else is a table of
 *  contents. These start the work from here: the describe and alt-text ones
 *  begin a background run, which means the page can be left immediately, and
 *  the panel then reports on it until it finishes.
 */
( function () {
	'use strict';

	var apiFetch = window.wp && window.wp.apiFetch;
	var T = window.vergemlJourney || {};

	if ( ! apiFetch ) {
		return;
	}

	var said = document.getElementById( 'vgml-quick-said' );
	var timer = null;

	function say( text ) {
		if ( said ) {
			said.textContent = text;
		}
	}

	function sprintf( template, values ) {
		var i = 0;
		return String( template ).replace( /%(\d+\$)?[ds]/g, function ( _all, pos ) {
			var n = pos ? parseInt( pos, 10 ) - 1 : i++;
			return String( values[ n ] );
		} );
	}

	function watch() {
		if ( timer ) {
			window.clearTimeout( timer );
			timer = null;
		}

		apiFetch( { path: '/vergeml/v1/ai-run' } ).then( function ( s ) {
			if ( ! s.active ) {
				say( s.stopped ? T.stopped + ' ' + s.stopped : T.finished );
				// The figures above are now wrong, and a dashboard showing
				// numbers it knows are stale is worse than one that reloads.
				window.setTimeout( function () { window.location.reload(); }, 1200 );
				return;
			}

			say( sprintf( T.running, [ s.described, s.total ] ) );
			timer = window.setTimeout( watch, 4000 );
		} ).catch( function () {
			say( T.failed );
		} );
	}

	function start( scope, button ) {
		button.disabled = true;
		say( T.starting );

		apiFetch( {
			path: '/vergeml/v1/ai-run',
			method: 'POST',
			data: {
				action: 'start',
				scope: scope,
				apply_alt: 'missing-alt' === scope
			}
		} ).then( watch ).catch( function ( err ) {
			button.disabled = false;
			say( ( err && err.message ) || T.failed );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest ? e.target.closest( '[data-do]' ) : null;

		if ( ! button ) {
			return;
		}

		e.preventDefault();

		var what = button.getAttribute( 'data-do' );

		if ( 'describe' === what ) {
			start( 'unindexed', button );
		} else if ( 'alt' === what ) {
			start( 'missing-alt', button );
		} else if ( 'stale' === what ) {
			// The one action here that spends money, so it asks first.
			if ( window.confirm( T.confirmStale ) ) {
				start( 'stale', button );
			}
		}
	} );

	// A run already going when the page opens should show itself, or the panel
	// reads as idle while the library is being described behind it.
	if ( said ) {
		apiFetch( { path: '/vergeml/v1/ai-run' } ).then( function ( s ) {
			if ( s.active ) {
				watch();
			}
		} ).catch( function () {} );
	}
}() );
