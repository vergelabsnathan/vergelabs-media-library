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
	var words = cfg.words || {};

	/*
	 *  The line, and after the folder the word on the picture as a pill --
	 *  sure, likely, by you -- when the record has one (spec §3). The pill is
	 *  on the same line: nothing of ours makes a row taller.
	 */
	function line( row ) {

		var entry = meta[ row.id.replace( 'post-', '' ) ];
		var cell = row.querySelector( '.column-title .filename' );

		if ( ! entry || ! cell ) {
			return;
		}

		var text = 'string' === typeof entry ? entry : entry.text;
		var word = 'string' === typeof entry ? '' : ( entry.word || '' );
		var label = cell.querySelector( '.screen-reader-text' );

		cell.textContent = '';

		if ( label ) {
			cell.appendChild( label );
		}

		if ( word && entry.folder && text.indexOf( ' · ' + entry.folder ) !== -1 ) {
			var at = text.indexOf( ' · ' + entry.folder ) + entry.folder.length + 3;
			cell.appendChild( document.createTextNode( text.slice( 0, at ) + ' ' ) );
			var pill = document.createElement( 'span' );
			pill.className = 'vgml-word g-pill is-' + word.replace( ' ', '-' );
			pill.textContent = words[ word ] || word;
			cell.appendChild( pill );
			cell.appendChild( document.createTextNode( text.slice( at ) ) );
			return;
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

	/*
	 *  File's floor, applied only when File needs it.
	 *
	 *  core/media-list.php writes File's share (30% beside another plugin's
	 *  columns, 40% among ours) behind body.vgml-file-share, because written
	 *  as a plain width it sized every column and the fixed table gave its
	 *  leftover to the checkbox column -- 324px wide beside FileBird. So
	 *  File starts as core has it, auto, and is measured once: narrower than
	 *  its share, the class goes on and the unsized columns beside it give
	 *  way; at or above it, File is the column that takes the leftover and
	 *  nothing is done. The head cell is the one that sizes a fixed column.
	 *
	 *  Measured in the frame after DOMContentLoaded, not in it: this script's
	 *  listener runs before js/vergeml-tree.js's, and that one puts the panel
	 *  beside the list and narrows the table. The ratio is what is compared,
	 *  so the em-wide checkbox is the only thing the width changes -- but the
	 *  table the person sees is the one to measure.
	 */
	function share() {

		var table = document.querySelector( '.wp-list-table.media' );
		var title = table && table.querySelector( 'thead .column-title' );
		var share = parseInt( cfg.share, 10 ) || 0; // localize hands numbers over as strings

		if ( ! share || ! title ) {
			return;
		}

		document.body.classList.toggle(
			'vgml-file-share',
			title.getBoundingClientRect().width < table.getBoundingClientRect().width * share / 100
		);
	}

	function draw() {
		var rows = document.querySelectorAll( '#the-list > tr[id^="post-"]' );
		for ( var i = 0; i < rows.length; i++ ) {
			line( rows[ i ] );
		}
		// The titles read what the widths cut short, so they follow the share.
		window.requestAnimationFrame( function () {
			share();
			for ( var i = 0; i < rows.length; i++ ) {
				titles( rows[ i ] );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', draw );
	} else {
		draw();
	}
}() );
