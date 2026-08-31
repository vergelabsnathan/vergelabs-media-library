/*
 *  Touch, for a drag built on mouse events.
 *
 *  The tree drags with jQuery UI, which is what WordPress's own media grid
 *  uses and why it costs nothing to ship -- and which listens to the mouse
 *  and nothing else. On a tablet, a finger on a file did nothing. FileBird
 *  shipped the same fix in August 2026, eight years in; here it is a dozen
 *  lines that turn one finger into the mouse jQuery UI is waiting for.
 *
 *  Only a single finger, only from a draggable or a droppable of ours or of
 *  the media grid, and only once the finger has actually moved -- a tap stays
 *  a tap, and pinch-zoom stays pinch-zoom.
 */
( function () {
	'use strict';

	if ( ! ( 'ontouchstart' in window ) ) {
		return;
	}

	var FROM = '.vgml-tree .ui-draggable, .vgml-tree .ui-droppable, .attachments-browser .attachment, .attachments-browser .ui-draggable';
	var active = null;
	var moved = false;

	function mouse( type, touch, target ) {
		var ev = document.createEvent( 'MouseEvents' );
		ev.initMouseEvent( type, true, true, window, 1,
			touch.screenX, touch.screenY, touch.clientX, touch.clientY,
			false, false, false, false, 0, null );
		( target || touch.target ).dispatchEvent( ev );
	}

	document.addEventListener( 'touchstart', function ( e ) {
		if ( e.touches.length !== 1 ) {
			return;
		}
		var from = e.target.closest ? e.target.closest( FROM ) : null;
		if ( ! from ) {
			return;
		}
		active = e.touches[ 0 ];
		moved = false;
		mouse( 'mouseover', active );
		mouse( 'mousemove', active );
		mouse( 'mousedown', active );
	}, { passive: true } );

	document.addEventListener( 'touchmove', function ( e ) {
		if ( ! active || e.touches.length !== 1 ) {
			return;
		}
		var t = e.touches[ 0 ];
		if ( ! moved && Math.abs( t.clientX - active.clientX ) < 6 && Math.abs( t.clientY - active.clientY ) < 6 ) {
			return; // still a tap; let the page scroll
		}
		moved = true;
		// A drag in progress owns the finger; otherwise the page scrolls under it.
		e.preventDefault();
		mouse( 'mousemove', t );
	}, { passive: false } );

	function end( e ) {
		if ( ! active ) {
			return;
		}
		var t = e.changedTouches && e.changedTouches[ 0 ] ? e.changedTouches[ 0 ] : active;
		// The drop target is whatever is under the finger now, not where it started.
		var under = document.elementFromPoint( t.clientX, t.clientY );
		mouse( 'mouseup', t, under || undefined );
		if ( ! moved ) {
			mouse( 'click', t );
		}
		active = null;
		moved = false;
	}

	document.addEventListener( 'touchend', end, { passive: true } );
	document.addEventListener( 'touchcancel', end, { passive: true } );
} )();
