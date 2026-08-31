/*
 *  Quick edit, on the media list.
 *
 *  The title and the alt text, changed where somebody is already looking,
 *  saved without a page load, and written back into the row. The pattern is
 *  core's own from the posts list, which is the point: nobody has to learn it.
 *
 *  ES5, no build step, same as everything else in this folder.
 */
( function () {
	'use strict';

	var apiFetch = window.wp && window.wp.apiFetch;
	var l10n = window.vergemlQuick || {};

	if ( ! apiFetch ) {
		return;
	}

	var open = null; // the row being edited, if any

	function text( key, fallback ) {
		return l10n[ key ] || fallback || '';
	}

	function close() {

		if ( ! open ) {
			return;
		}

		if ( open.editor && open.editor.parentNode ) {
			open.editor.parentNode.removeChild( open.editor );
		}

		open.row.style.display = '';
		open = null;
	}

	/*
	 *  Where the row shows what we just changed.
	 *
	 *  Written back rather than reloaded: a reload is the thing this exists to
	 *  avoid, and a row that still shows the old value after a save that
	 *  succeeded is worse than no quick edit at all -- somebody types it twice.
	 */
	function writeBack( row, saved ) {

		var titleCell = row.querySelector( '.column-title .row-title, .column-title strong a, .column-title strong' );

		if ( titleCell ) {
			titleCell.textContent = saved.title;
		}

		var altCell = row.querySelector( '.column-vgmlpro_source' );

		if ( altCell ) {
			// The provenance line under it is now wrong -- a person has just
			// written this -- and saying so is more honest than leaving the
			// old attribution in place until the next page load.
			altCell.innerHTML = '';

			var shown = document.createElement( 'span' );
			shown.title = saved.alt;
			shown.textContent = saved.alt === '' ? '' : ( saved.alt.length > 68 ? saved.alt.slice( 0, 68 ) + '…' : saved.alt );

			altCell.appendChild( shown );
			altCell.appendChild( document.createElement( 'br' ) );

			var who = document.createElement( 'span' );
			who.className = 'description';
			who.style.fontSize = '11px';
			who.style.color = '#50575e';
			who.textContent = text( 'yours', 'You wrote this' );

			altCell.appendChild( who );
		}

		// So a second quick edit starts from what is actually there now.
		var link = row.querySelector( '.vgml-quick-edit' );

		if ( link ) {
			link.setAttribute( 'data-title', saved.title );
			link.setAttribute( 'data-alt', saved.alt );
		}
	}

	document.addEventListener( 'click', function ( event ) {

		var target = event.target;

		if ( ! target || ! target.closest ) {
			return;
		}

		/* ------------------------------------------------------- opening */

		var link = target.closest( '.vgml-quick-edit' );

		if ( link ) {

			event.preventDefault();

			var row = link.closest( 'tr' );
			var template = document.getElementById( 'vgml-quick-template' );

			if ( ! row || ! template ) {
				return;
			}

			close();

			var editor = template.content.cloneNode( true ).querySelector( 'tr' );

			editor.querySelector( '.vgml-quick-title' ).value = link.getAttribute( 'data-title' ) || '';
			editor.querySelector( '.vgml-quick-alt' ).value = link.getAttribute( 'data-alt' ) || '';

			row.parentNode.insertBefore( editor, row.nextSibling );
			row.style.display = 'none';

			open = { row: row, editor: editor, id: link.getAttribute( 'data-id' ) };

			editor.querySelector( '.vgml-quick-title' ).focus();
			return;
		}

		/* ------------------------------------------------------ cancelling */

		if ( target.closest( '.vgml-quick-cancel' ) ) {
			event.preventDefault();
			close();
			return;
		}

		/* --------------------------------------------------------- saving */

		if ( target.closest( '.vgml-quick-save' ) && open ) {

			event.preventDefault();

			var button = target.closest( '.vgml-quick-save' );
			var note = open.editor.querySelector( '.vgml-quick-note' );
			var row2 = open.row;

			button.disabled = true;
			note.removeAttribute( 'data-state' );
			note.textContent = text( 'saving', 'Saving…' );

			apiFetch( {
				path: '/vergeml/v1/file/' + open.id,
				method: 'POST',
				data: {
					title: open.editor.querySelector( '.vgml-quick-title' ).value,
					alt: open.editor.querySelector( '.vgml-quick-alt' ).value,
				},
			} ).then( function ( saved ) {
				writeBack( row2, saved );
				close();
			} ).catch( function () {
				button.disabled = false;
				note.setAttribute( 'data-state', 'failed' );
				note.textContent = text( 'failed', '' );
			} );
		}
	} );

	// Escape closes it, because every other inline editor in this admin does.
	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && open ) {
			close();
		}
	} );
}() );
