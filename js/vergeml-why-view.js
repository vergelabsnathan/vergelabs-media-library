/*
 *  "Why is it here", in the media modal.
 *
 *  The field on the attachment's own screen comes from
 *  vergeml_why_here_field(), and that filter is deliberately skipped on
 *  `query-attachments` -- the listing runs it once per item and it costs 5.7
 *  queries a picture, measured on the box, some 450 on an eighty-item page.
 *  The grid's modal renders from that same listing response, so the field
 *  never reaches the screen where most people meet a picture.
 *
 *  So the modal asks for itself: one picture, when somebody opens it, from a
 *  read-only route that returns vergeml_librarian_why()'s own answer. The
 *  listing sends exactly the requests it sent before this file existed.
 *
 *  Core's render is wrapped, never replaced -- see the header of
 *  js/vergeml-media-views.js for what replacing it costs. Attachment.Details
 *  is wrapped once and the modal's two-column view inherits it.
 *
 *  ES5, no build step, same as everything else in this folder.
 */
( function () {
	'use strict';

	var wp = window.wp;
	var apiFetch = wp && wp.apiFetch;

	if ( ! apiFetch || ! wp.media || ! wp.media.view || ! wp.media.view.Attachment ) {
		return;
	}

	var Details = wp.media.view.Attachment.Details;

	if ( ! Details || Details.prototype.vgmlWhyWrapped ) {
		return;
	}

	/*
	 *  Answers, by attachment id, and the ids being asked about.
	 *
	 *  A details view re-renders on every model change -- a title typed, a
	 *  folder ticked -- and the answer to "why is it here" does not change
	 *  while somebody looks at it. Asked once a picture; the arrows walking
	 *  the library come back to a picture already answered without a request.
	 */
	var answers = {};
	var asking = {};

	/**
	 *  Where the answer goes: under the fields, above the actions.
	 *
	 *  `.attachment-compat` is where every other plugin's attachment field is
	 *  rendered, and where this one is on the attachment's own screen, so the
	 *  section reads in the same place on both. Failing that the right-hand
	 *  column, and failing that the view itself -- a fact at the bottom of the
	 *  panel is worth more than a fact nowhere.
	 */
	function place( view, section ) {

		var compat = view.$el.find( '.attachment-compat' );

		if ( compat.length ) {
			compat.first().after( section );
			return;
		}

		var info = view.$el.find( '.attachment-info' );

		( info.length ? info.first() : view.$el ).append( section );
	}

	function section( id, answer ) {

		var wrap = document.createElement( 'div' );
		var name = document.createElement( 'span' );
		var list = document.createElement( 'ul' );

		wrap.className = 'setting vgml-why';
		wrap.setAttribute( 'data-id', String( id ) );

		name.className = 'name';
		name.textContent = answer.label;

		list.className = 'vgml-why-facts';

		answer.lines.forEach( function ( line ) {
			var item = document.createElement( 'li' );
			item.textContent = line;
			list.appendChild( item );
		} );

		wrap.appendChild( name );
		wrap.appendChild( list );

		return wrap;
	}

	/**
	 *  Paint what is known about the picture this view is showing.
	 *
	 *  A picture with no record shows no section at all, exactly as the field
	 *  does with the same nothing. Nothing is drawn while the answer is on its
	 *  way either: a heading over an empty list would be a claim that there is
	 *  something to read.
	 */
	function paint( view ) {

		var id = view.model && view.model.get( 'id' );

		view.$el.find( '.vgml-why' ).remove();

		if ( ! id || ! answers[ id ] || ! answers[ id ].lines.length ) {
			return;
		}

		place( view, section( id, answers[ id ] ) );
	}

	function ask( view ) {

		var id = view.model && view.model.get( 'id' );

		if ( ! id || answers[ id ] || asking[ id ] ) {
			return;
		}

		asking[ id ] = true;

		apiFetch( { path: '/vergeml/v1/librarian-why/' + id } ).then( function ( answer ) {
			delete asking[ id ];
			answers[ id ] = {
				label: answer.label || '',
				lines: answer.lines || [],
			};
			/*
			 *  Only if this view is still on that picture. The arrows walk
			 *  faster than a request comes back, and a late answer painted
			 *  under the wrong picture is the one failure this must not have.
			 */
			if ( view.model && view.model.get( 'id' ) === id ) {
				paint( view );
			}
		} ).catch( function () {
			// A refused request leaves the section off. There is no answer to
			// show and inventing a placeholder for one is what this whole
			// phase is against.
			delete asking[ id ];
		} );
	}

	var render = Details.prototype.render;

	Details.prototype.vgmlWhyWrapped = true;

	Details.prototype.render = function () {
		var out = render.apply( this, arguments );
		paint( this );
		ask( this );
		return out;
	};
}() );
