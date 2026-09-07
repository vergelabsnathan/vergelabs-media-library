/*
 *  The media list's rows: the quiet line, and the cells that must not wrap.
 *
 *  Core prints the file name in `p.filename` inside its own File cell and
 *  offers no hook between that paragraph and the row actions. So the folder
 *  and the size are written into the paragraph core already drew rather than
 *  added under it -- one line, not two, and no row grows because of us.
 *
 *  The screen-reader label core puts in front of the name is kept, so the
 *  line still reads "File name: vgml-fx-real-499.jpg · Architecture · 68 KB".
 *
 *  Our own columns are ellipsised by the stylesheet core/media-list.php
 *  writes, so each one is given its full value as a title here: a cell cut
 *  short with no way to read the rest is worse than a tall row.
 *
 *  ES5, no build step, same as everything else in this folder.
 */
( function () {
	'use strict';

	var cfg = window.vergemlList || {};
	var meta = cfg.rows || {};
	var columns = cfg.columns || [];

	function line( row ) {

		var text = meta[ row.id.replace( 'post-', '' ) ];
		var cell = row.querySelector( '.column-title .filename' );

		if ( ! text || ! cell ) {
			return;
		}

		var label = cell.querySelector( '.screen-reader-text' );

		cell.textContent = '';

		if ( label ) {
			cell.appendChild( label );
		}

		cell.appendChild( document.createTextNode( text ) );
	}

	/*
	 *  Anything the stylesheet had to cut short gets its full value as a
	 *  title. Only what is actually cut: a cell that fits needs no tooltip,
	 *  and every column of ours is in the list whether it fits or not,
	 *  because those are the ones this plugin is answerable for.
	 */
	function titles( row ) {

		var cells = row.querySelectorAll( 'td[class*="column-"]' );

		for ( var i = 0; i < cells.length; i++ ) {

			var cell = cells[ i ];

			if ( cell.classList.contains( 'column-title' ) || cell.getAttribute( 'title' ) ) {
				continue;
			}

			var ours = columns.indexOf( cell.className.split( ' ' )[ 0 ] ) !== -1;

			if ( ! ours && cell.scrollWidth <= cell.clientWidth ) {
				continue;
			}

			var full = cell.textContent.replace( /\s+/g, ' ' ).trim();

			if ( full ) {
				cell.setAttribute( 'title', full );
			}
		}
	}

	function draw() {
		var rows = document.querySelectorAll( '#the-list > tr[id^="post-"]' );
		for ( var i = 0; i < rows.length; i++ ) {
			line( rows[ i ] );
			titles( rows[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', draw );
	} else {
		draw();
	}
}() );
