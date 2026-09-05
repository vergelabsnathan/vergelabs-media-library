/*
 *  The AI screen: settings, and the describe loop.
 *
 *  Indexing is a REST call in a loop, a few files per call, so it is
 *  resumable by construction: close the tab mid-run and the next click
 *  carries on from where the meta says things stand.
 */
( function () {
	'use strict';

	var apiFetch = window.wp && window.wp.apiFetch;

	if ( ! apiFetch ) {
		return;
	}

	var $ = function ( id ) {
		return document.getElementById( id );
	};

	var running = false;

	function refresh() {
		return apiFetch( { path: '/vergeml/v1/ai-status' } ).then( function ( s ) {

			$( 'vgml-ai-counts' ).textContent =
				s.images + ' images · ' + s.indexed + ' described · ' + s.missing_alt + ' missing alt text';

			if ( $( 'vgml-ai-enrich' ) ) {
				$( 'vgml-ai-enrich' ).checked = !! s.settings.enrich_search;
				if ( $( 'vgml-ai-page-context' ) ) {
					$( 'vgml-ai-page-context' ).checked = !! s.settings.page_context;
				}
				if ( $( 'vgml-ai-profile' ) ) {
					$( 'vgml-ai-profile' ).value = s.settings.site_profile || '';
				}
				if ( s.settings.has_license && $( 'vgml-ai-license' ) ) {
					$( 'vgml-ai-license' ).placeholder = '•••••••• (saved)';
				}
				// A key set for the whole network: shown as such, and not
				// editable here when the network administrator locked it.
				if ( s.settings.from_network && $( 'vgml-ai-license' ) ) {
					$( 'vgml-ai-license' ).placeholder = s.settings.network_locked
						? '•••••••• (set for the network)'
						: '•••••••• (from the network — enter one to use your own)';
				}
				if ( $( 'vgml-ai-license' ) ) {
					$( 'vgml-ai-license' ).disabled = !! s.settings.network_locked;
				}
			}

			// The SEO-pages button carries its own count and stays out of the
			// way when the count is zero.
			if ( $( 'vgml-ai-page-gap' ) ) {
				var gap = parseInt( s.page_gap, 10 ) || 0;
				$( 'vgml-ai-page-gap' ).hidden = ! gap;
				if ( gap ) {
					$( 'vgml-ai-page-gap' ).textContent = 'Fix alt text on your SEO pages (' + gap + ')';
				}
			}

			if ( $( 'vgml-ai-credits' ) && null !== s.credits && undefined !== s.credits ) {
				$( 'vgml-ai-credits' ).textContent = s.credits + ' remaining';
			}

			return s;
		} );
	}

	function log( text, bad ) {
		var li = document.createElement( 'li' );
		li.textContent = text;
		if ( bad ) {
			li.className = 'is-error';
		}
		var list = $( 'vgml-ai-log' );
		list.insertBefore( li, list.firstChild );
		while ( list.children.length > 40 ) {
			list.removeChild( list.lastChild );
		}
	}

	function run( scope, applyAlt ) {

		if ( running ) {
			return;
		}
		running = true;

		var bar = $( 'vgml-ai-bar' );
		var fill = $( 'vgml-ai-fill' );
		var note = $( 'vgml-ai-note' );
		var total = 0;

		bar.hidden = false;
		fill.style.width = '0';
		note.textContent = '';

		( function step() {
			apiFetch( {
				path: '/vergeml/v1/ai-index',
				method: 'POST',
				data: { scope: scope, limit: 24, apply_alt: applyAlt },
			} ).then( function ( r ) {

				r.described.forEach( function ( d ) {
					log( '#' + d.id + ' — ' + d.caption );
				} );
				r.errors.forEach( function ( e ) {
					log( '#' + e.id + ' — ' + e.error, true );
				} );

				if ( ! total ) {
					total = r.remaining + r.described.length + r.errors.length;
				}

				var doneCount = total - r.remaining;
				fill.style.width = total ? Math.round( ( doneCount / total ) * 100 ) + '%' : '100%';
				note.textContent = r.remaining + ' to go';

				if ( r.remaining > 0 && ( r.described.length || r.errors.length ) ) {
					step();
					return;
				}

				running = false;
				note.textContent = r.remaining > 0
					? 'Stopped — the remaining files kept failing.'
					: 'Done.';
				refresh();
			} ).catch( function ( err ) {
				running = false;
				note.textContent = ( err && err.message ) ? err.message : 'Request failed.';
			} );
		} )();
	}

	function boot() {

		refresh();

		if ( $( 'vgml-ai-save' ) ) {
			$( 'vgml-ai-save' ).addEventListener( 'click', function () {
				apiFetch( {
					path: '/vergeml/v1/ai-settings',
					method: 'POST',
					data: {
						license_key: $( 'vgml-ai-license' ) ? $( 'vgml-ai-license' ).value : '',
						enrich_search: $( 'vgml-ai-enrich' ).checked ? 1 : 0,
						page_context: $( 'vgml-ai-page-context' ) && $( 'vgml-ai-page-context' ).checked ? 1 : 0,
					},
				} ).then( function () {
					if ( $( 'vgml-ai-license' ) ) {
						$( 'vgml-ai-license' ).value = '';
					}
					$( 'vgml-ai-save-note' ).textContent = 'Saved.';
					window.setTimeout( function () {
						$( 'vgml-ai-save-note' ).textContent = '';
					}, 2500 );
					refresh();
				} );
			} );
		}

		/*
		 *  Where the run happens is a choice about this run, not a different
		 *  feature -- so it is a radio beside the buttons rather than a second
		 *  section with a second pair of buttons, which is what it was.
		 */
		function inBackground() {
			var picked = document.querySelector( 'input[name="vgml-ai-where"]:checked' );
			return !! picked && 'background' === picked.value;
		}

		function start( scope ) {
			if ( ! inBackground() ) {
				run( scope, true );
				return;
			}

			if ( window.vergemlStartBackground ) {
				window.vergemlStartBackground( scope );
			}
		}

		$( 'vgml-ai-run' ).addEventListener( 'click', function () {
			start( 'unindexed' );
		} );

		$( 'vgml-ai-alt' ).addEventListener( 'click', function () {
			start( 'missing-alt' );
		} );

		if ( $( 'vgml-ai-page-gap' ) ) {
			$( 'vgml-ai-page-gap' ).addEventListener( 'click', function () {
				start( 'page-gap' );
			} );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
