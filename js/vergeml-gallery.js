/*
 *  Carousel arrows and the lightbox.
 *
 *  Everything here is an improvement on markup that already works without it.
 *  The carousel scrolls by touch and trackpad through scroll-snap alone; the
 *  arrows are added by this script because a mouse has no natural way to swipe.
 *  A lightbox link is an ordinary <a> to the full-size file; this script
 *  intercepts it and shows the image in place instead of leaving the page.
 *  With JavaScript missing, both degrade to exactly what they say they are.
 *
 *  Vanilla, no dependencies, loaded only on pages that render a gallery.
 */

( function () {
	'use strict';

	/* --- carousel ---------------------------------------------------------- */

	function armCarousel( gallery ) {

		if ( gallery.closest( '.vgml-carousel-wrap' ) ) {
			return;
		}

		var wrap = document.createElement( 'div' );
		wrap.className = 'vgml-carousel-wrap';
		gallery.parentNode.insertBefore( wrap, gallery );
		wrap.appendChild( gallery );

		var mk = function ( dir, glyph, label ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.className = 'vgml-carousel-arrow';
			b.setAttribute( 'data-dir', dir );
			b.setAttribute( 'aria-label', label );
			b.innerHTML = glyph;
			wrap.appendChild( b );
			return b;
		};

		var rtl = 'rtl' === document.documentElement.dir;
		var prev = mk( 'prev', rtl ? '&#8250;' : '&#8249;', 'Previous images' );
		var next = mk( 'next', rtl ? '&#8249;' : '&#8250;', 'Next images' );

		var step = function () {
			var slide = gallery.querySelector( '.wp-block-image' );
			return slide ? slide.getBoundingClientRect().width + 16 : gallery.clientWidth;
		};

		var sync = function () {
			var max = gallery.scrollWidth - gallery.clientWidth - 2;
			var at = Math.abs( gallery.scrollLeft );
			prev.disabled = at <= 2;
			next.disabled = at >= max;
		};

		prev.addEventListener( 'click', function () {
			gallery.scrollBy( { left: ( rtl ? 1 : -1 ) * step(), behavior: 'smooth' } );
		} );
		next.addEventListener( 'click', function () {
			gallery.scrollBy( { left: ( rtl ? -1 : 1 ) * step(), behavior: 'smooth' } );
		} );

		gallery.addEventListener( 'scroll', sync, { passive: true } );
		window.addEventListener( 'resize', sync );
		sync();
	}

	/* --- lightbox ----------------------------------------------------------- */

	var overlay = null;
	var items = [];
	var at = 0;
	var lastFocus = null;

	function closeLightbox() {
		if ( overlay ) {
			overlay.remove();
			overlay = null;
			document.removeEventListener( 'keydown', onKey );
			if ( lastFocus && lastFocus.focus ) {
				lastFocus.focus();
			}
		}
	}

	function show( index ) {

		at = ( index + items.length ) % items.length;

		var link = items[ at ];
		var thumb = link.querySelector( 'img' );

		var img = overlay.querySelector( 'img' );
		img.src = link.getAttribute( 'href' );
		img.alt = thumb ? thumb.alt || '' : '';

		var caption = overlay.querySelector( '.vgml-lightbox-caption' );
		var fig = link.closest( 'figure' );
		var cap = fig ? fig.querySelector( 'figcaption' ) : null;
		caption.textContent = cap ? cap.textContent : ( thumb ? thumb.alt || '' : '' );
	}

	function onKey( e ) {
		if ( 'Escape' === e.key ) { closeLightbox(); }
		if ( 'ArrowRight' === e.key ) { show( at + 1 ); }
		if ( 'ArrowLeft' === e.key ) { show( at - 1 ); }
	}

	function openLightbox( gallery, link ) {

		items = [].slice.call( gallery.querySelectorAll( 'a.vgml-lightbox' ) );
		lastFocus = link;

		overlay = document.createElement( 'div' );
		overlay.className = 'vgml-lightbox-overlay' + ( items.length < 2 ? ' is-single' : '' );
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );

		overlay.innerHTML =
			'<img alt="" />' +
			'<p class="vgml-lightbox-caption"></p>' +
			'<button type="button" class="vgml-lightbox-nav" data-dir="prev" aria-label="Previous image">&#8249;</button>' +
			'<button type="button" class="vgml-lightbox-nav" data-dir="next" aria-label="Next image">&#8250;</button>' +
			'<button type="button" class="vgml-lightbox-close" aria-label="Close">&#10005;</button>';

		overlay.addEventListener( 'click', function ( e ) {
			var nav = e.target.closest( '.vgml-lightbox-nav' );
			if ( nav ) {
				show( at + ( 'next' === nav.getAttribute( 'data-dir' ) ? 1 : -1 ) );
				return;
			}
			// The image itself is not a close target; everything else is.
			if ( ! e.target.closest( 'img' ) ) {
				closeLightbox();
			}
		} );

		document.body.appendChild( overlay );
		document.addEventListener( 'keydown', onKey );

		show( items.indexOf( link ) );
		overlay.querySelector( '.vgml-lightbox-close' ).focus();
	}

	/* --- wiring -------------------------------------------------------------- */

	function boot() {

		document.querySelectorAll( '.vgml-folder-gallery.is-carousel' ).forEach( armCarousel );

		document.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( 'a.vgml-lightbox' );
			if ( ! link ) {
				return;
			}
			var gallery = link.closest( '.vgml-folder-gallery' );
			if ( ! gallery ) {
				return;
			}
			e.preventDefault();
			openLightbox( gallery, link );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
