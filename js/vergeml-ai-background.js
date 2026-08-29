/*
 *  The background describe run.
 *
 *  Unlike the loop next to it in vergeml-ai.js, this page is not doing the
 *  work -- WP-Cron is. So there is nothing here but starting, stopping and
 *  asking how far it has got, and closing the tab costs nothing.
 */
( function () {
	'use strict';

	var apiFetch = window.wp && window.wp.apiFetch;
	var T = window.vergemlAiRun || {};

	if ( ! apiFetch ) {
		return;
	}

	var $ = function ( id ) {
		return document.getElementById( id );
	};

	var timer = null;

	function sprintf( template, values ) {
		var i = 0;
		// Only the %1$d / %2$d / %d forms the localised strings use.
		return String( template ).replace( /%(\d+\$)?d/g, function ( _all, pos ) {
			var n = pos ? parseInt( pos, 10 ) - 1 : i++;
			return String( values[ n ] );
		} );
	}

	function render( s ) {
		var note = $( 'vgml-ai-bg-note' );
		var bar = $( 'vgml-ai-bg-bar' );
		var fill = $( 'vgml-ai-bg-fill' );
		var stop = $( 'vgml-ai-bg-stop' );
		var start = $( 'vgml-ai-bg-start' );
		var alt = $( 'vgml-ai-bg-alt' );

		if ( ! note ) {
			return;
		}

		stop.hidden = ! s.active;
		start.disabled = !! s.active;
		alt.disabled = !! s.active;

		if ( ! s.active ) {
			bar.hidden = true;

			if ( s.stopped ) {
				note.textContent = T.stopped + ' ' + s.stopped;
			} else if ( s.described > 0 ) {
				note.textContent = T.done + ' ' + sprintf( T.progress, [ s.described, s.total ] );
			} else {
				note.textContent = T.idle;
			}
			return;
		}

		bar.hidden = false;

		var done = s.total > 0 ? Math.min( 100, Math.round( ( s.described / s.total ) * 100 ) ) : 0;
		fill.style.width = done + '%';

		var parts = [ sprintf( T.progress, [ s.described, s.total ] ) ];

		if ( s.failed > 0 ) {
			parts.push( sprintf( T.failed, [ s.failed ] ) );
		}

		/*
		 *  "Due", not "running". Cron fires when somebody visits the site, so
		 *  a countdown to zero is a countdown to eligibility and not to work
		 *  actually happening -- and a bar that claimed otherwise would be
		 *  lying on any quiet site.
		 */
		if ( s.cron_off ) {
			parts.push( T.cronOff );
		} else if ( null === s.next || undefined === s.next || s.next <= 0 ) {
			parts.push( T.due );
		} else {
			parts.push( sprintf( T.next, [ s.next ] ) );
		}

		note.textContent = parts.join( ' · ' );
	}

	function poll() {
		return apiFetch( { path: '/vergeml/v1/ai-run' } ).then( function ( s ) {
			render( s );

			if ( timer ) {
				window.clearTimeout( timer );
				timer = null;
			}

			if ( s.active ) {
				timer = window.setTimeout( poll, 5000 );
			}

			return s;
		} );
	}

	function begin( scope ) {
		$( 'vgml-ai-bg-note' ).textContent = T.starting;

		return apiFetch( {
			path: '/vergeml/v1/ai-run',
			method: 'POST',
			data: {
				action: 'start',
				scope: scope,
				// Fixing alt text is the whole point of that scope, so the
				// background run applies it without a second switch.
				apply_alt: 'missing-alt' === scope
			}
		} ).then( render ).then( poll ).catch( function ( err ) {
			$( 'vgml-ai-bg-note' ).textContent = err && err.message ? err.message : String( err );
		} );
	}

	function wire( id ) {
		var el = $( id );

		if ( ! el ) {
			return;
		}

		el.addEventListener( 'click', function () {
			begin( el.getAttribute( 'data-scope' ) );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! $( 'vgml-ai-bg-note' ) ) {
			return;
		}

		wire( 'vgml-ai-bg-start' );
		wire( 'vgml-ai-bg-alt' );

		var stop = $( 'vgml-ai-bg-stop' );

		if ( stop ) {
			stop.addEventListener( 'click', function () {
				apiFetch( {
					path: '/vergeml/v1/ai-run',
					method: 'POST',
					data: { action: 'stop' }
				} ).then( render );
			} );
		}

		poll();
	} );
}() );
