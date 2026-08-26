/*
 *  The Library health screen: the scan loop, and the two lists.
 *
 *  The loop is the same shape as the importer's and the AI screen's -- a REST
 *  call carrying the cursor the last one returned -- so closing the tab loses
 *  nothing and the next click carries on from the last file that got hashed.
 *
 *  The report draws, and only draws. There is no control on this screen that
 *  changes a file, which is deliberate: the browser suite asserts the absence
 *  of one.
 */
( function () {
	'use strict';

	var apiFetch = window.wp && window.wp.apiFetch;
	var l10n = ( window.vergemlHealth && window.vergemlHealth.l10n ) || {};

	if ( ! apiFetch ) {
		return;
	}

	var $ = function ( id ) {
		return document.getElementById( id );
	};

	var running = false;

	function text( key, fallback ) {
		return l10n[ key ] || fallback;
	}

	function sprintf( template, values ) {
		var i = 0;
		return String( template )
			.replace( /%(\d)\$s/g, function ( match, position ) {
				return values[ Number( position ) - 1 ];
			} )
			.replace( /%s/g, function () {
				return values[ i++ ];
			} );
	}

	// Bytes as something a person reads. Deliberately not exact: the number is
	// an argument for looking, not an accounting figure.
	function bytes( n ) {

		var units = [ 'B', 'KB', 'MB', 'GB' ];
		var value = Number( n ) || 0;
		var unit = 0;

		while ( value >= 1024 && unit < units.length - 1 ) {
			value = value / 1024;
			unit++;
		}

		return ( unit === 0 ? Math.round( value ) : value.toFixed( 1 ) ) + ' ' + units[ unit ];
	}

	function el( tag, className, textContent ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== textContent && null !== textContent ) {
			node.textContent = textContent;
		}
		return node;
	}

	function drawGroup( group ) {

		var wrap = el( 'div', 'vgml-health-group' );

		var head = el( 'div', 'vgml-health-group-head' );
		head.appendChild( el( 'span', 'vgml-health-count', group.items.length + ' files' ) );
		head.appendChild( el( 'span', 'vgml-health-wasted',
			sprintf( text( 'groupWasted', '%s potentially recoverable' ), [ bytes( group.wasted ) ] ) ) );
		wrap.appendChild( head );

		var list = el( 'ul', 'vgml-health-files' );

		group.items.forEach( function ( item ) {

			var li = el( 'li' );

			// The link goes to the file's own edit screen, which is where
			// acting on it belongs. Nothing on this page acts.
			var link = document.createElement( 'a' );
			link.href = item.edit || '#';

			if ( item.thumb ) {
				var img = document.createElement( 'img' );
				img.src = item.thumb;
				img.alt = '';
				img.loading = 'lazy';
				link.appendChild( img );
			} else {
				link.appendChild( el( 'span', 'vgml-health-nothumb', item.mime || '' ) );
			}

			link.appendChild( el( 'span', 'vgml-health-name', item.name || item.title || ( '#' + item.id ) ) );
			link.appendChild( el( 'span', 'vgml-health-size', bytes( item.bytes ) ) );

			li.appendChild( link );
			list.appendChild( li );
		} );

		wrap.appendChild( list );

		return wrap;
	}

	function drawList( heading, note, empty, section ) {

		var card = el( 'div', 'vgml-ai-card vgml-health-list' );

		card.appendChild( el( 'h2', null, heading ) );
		card.appendChild( el( 'p', 'description', note ) );

		if ( ! section.groups.length ) {
			card.appendChild( el( 'p', 'vgml-health-empty', empty ) );
			return card;
		}

		card.appendChild( el( 'p', 'vgml-health-summary',
			sprintf( text( 'summary', '%1$s groups · %2$s potentially recoverable' ),
				[ String( section.groups.length ), bytes( section.wasted ) ] ) ) );

		section.groups.forEach( function ( group ) {
			card.appendChild( drawGroup( group ) );
		} );

		if ( section.more > 0 ) {
			card.appendChild( el( 'p', 'vgml-health-more',
				sprintf( text( 'more', 'and %s more' ), [ String( section.more ) ] ) ) );
		}

		return card;
	}

	function report() {

		$( 'vgml-health-note' ).textContent = text( 'building', 'Comparing…' );

		return apiFetch( { path: '/vergeml/v1/health-report' } ).then( function ( r ) {

			var target = $( 'vgml-health-report' );
			target.innerHTML = '';

			if ( ! r.scanned ) {
				$( 'vgml-health-counts' ).textContent = text( 'never', '' );
				$( 'vgml-health-note' ).textContent = '';
				return r;
			}

			var groups = r.duplicates.groups.length + r.related.groups.length;

			$( 'vgml-health-counts' ).textContent =
				sprintf( text( 'summary', '%1$s groups · %2$s potentially recoverable' ),
					[ String( groups ), bytes( r.wasted ) ] );

			target.appendChild( drawList(
				text( 'duplicates', 'Duplicates' ),
				text( 'dupeNote', '' ),
				text( 'noDuplicates', 'No duplicates found.' ),
				r.duplicates
			) );

			target.appendChild( drawList(
				text( 'related', 'Possibly related' ),
				text( 'relatedNote', '' ),
				text( 'noRelated', '' ),
				r.related
			) );

			target.appendChild( el( 'p', 'vgml-health-readonly', text( 'readOnly', '' ) ) );

			$( 'vgml-health-note' ).textContent = '';
			$( 'vgml-health-scan' ).textContent = text( 'rescan', 'Scan again' );

			return r;
		} );
	}

	function scan() {

		if ( running ) {
			return;
		}
		running = true;

		var bar = $( 'vgml-health-bar' );
		var fill = $( 'vgml-health-fill' );
		var note = $( 'vgml-health-note' );
		var total = 0;

		bar.hidden = false;
		fill.style.width = '0';
		note.textContent = text( 'scanning', 'Reading files…' );

		( function step( cursor, reset ) {
			apiFetch( {
				path: '/vergeml/v1/health-scan',
				method: 'POST',
				data: { cursor: cursor, reset: reset },
			} ).then( function ( r ) {

				if ( ! total ) {
					total = r.total || ( r.remaining + r.hashed );
				}

				var done = Math.max( 0, total - r.remaining );
				fill.style.width = total ? Math.round( ( done / total ) * 100 ) + '%' : '100%';
				note.textContent = sprintf( text( 'remaining', '%s to go' ), [ String( r.remaining ) ] );

				if ( ! r.done ) {
					step( r.cursor, false );
					return;
				}

				fill.style.width = '100%';
				running = false;
				report();
			} ).catch( function ( err ) {
				running = false;
				note.textContent = ( err && err.message ) ? err.message : text( 'failed', 'Request failed.' );
			} );
		} )( 0, true );
	}

	function boot() {

		report().catch( function () {
			$( 'vgml-health-counts' ).textContent = text( 'failed', 'Request failed.' );
		} );

		$( 'vgml-health-scan' ).addEventListener( 'click', function () {
			scan();
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
