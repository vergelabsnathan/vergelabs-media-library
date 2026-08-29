/*
 *  The question marks beside the options.
 *
 *  One bubble exists for the whole page and moves to whichever button was
 *  pressed. Twenty hidden divs would be twenty things to keep in sync, and a
 *  tooltip is not content -- nothing is lost by it living outside the form.
 *
 *  Click rather than hover, on purpose: a hover tooltip cannot be read on a
 *  touch screen and cannot be reached by keyboard, and this exists precisely
 *  for the person who is not sure what they are looking at.
 */
( function () {
	'use strict';

	var T = window.vergemlHelp || {};
	var bubble = null;
	var open = null;

	function make() {
		if ( bubble ) {
			return bubble;
		}

		bubble = document.createElement( 'div' );
		bubble.className = 'vgml-help-bubble';
		bubble.setAttribute( 'role', 'tooltip' );
		bubble.hidden = true;
		document.body.appendChild( bubble );

		return bubble;
	}

	function close() {
		if ( ! open ) {
			return;
		}

		open.setAttribute( 'aria-expanded', 'false' );
		open.removeAttribute( 'aria-describedby' );
		open = null;

		if ( bubble ) {
			bubble.hidden = true;
		}
	}

	function place( button ) {
		var box = button.getBoundingClientRect();
		var top = window.pageYOffset + box.bottom + 8;
		var left = window.pageXOffset + box.left;

		bubble.hidden = false;

		// Keep it on screen: a bubble half off the right edge is worse than
		// one that does not line up with its button.
		var width = bubble.offsetWidth;
		var edge = window.pageXOffset + document.documentElement.clientWidth - 16;

		if ( left + width > edge ) {
			left = Math.max( window.pageXOffset + 16, edge - width );
		}

		bubble.style.top = top + 'px';
		bubble.style.left = left + 'px';
	}

	function show( button ) {
		var text = button.getAttribute( 'data-help' );

		if ( ! text ) {
			return;
		}

		close();
		make();

		bubble.textContent = text;
		bubble.id = bubble.id || 'vgml-help-bubble';

		button.setAttribute( 'aria-expanded', 'true' );
		button.setAttribute( 'aria-describedby', bubble.id );

		open = button;
		place( button );
	}

	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest ? e.target.closest( '.vgml-help' ) : null;

		if ( button ) {
			e.preventDefault();
			e.stopPropagation();

			if ( open === button ) {
				close();
			} else {
				show( button );
			}

			return;
		}

		// Anywhere else, including inside the bubble: reading it is done.
		close();
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key || 'Esc' === e.key ) {
			if ( open ) {
				var was = open;
				close();
				was.focus();
			}
		}
	} );

	// A bubble pinned to a button it is no longer beside is worse than none.
	window.addEventListener( 'resize', close );
	window.addEventListener( 'scroll', function () {
		if ( open ) {
			place( open );
		}
	}, true );
}() );
