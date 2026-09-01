/*
 *  Telling the plugin what folders you want.
 *
 *  Two presses, never one. The first shows what would change; the second does
 *  it. Nothing here moves a file until somebody has read the difference,
 *  because "reorganise my whole library" is not a thing to do on a typo.
 *
 *  ES5, no build step, same as everything else in this folder.
 */
( function () {
	'use strict';

	var apiFetch = window.wp && window.wp.apiFetch;
	var l10n = ( window.vergemlTalk && window.vergemlTalk.l10n ) || {};

	if ( ! apiFetch ) {
		return;
	}

	function text( key, fallback ) {
		return l10n[ key ] || fallback || '';
	}

	function sprintf( template, values ) {
		var i = 0;
		return String( template ).replace( /%s/g, function () {
			return values[ i++ ];
		} );
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

	var say = document.getElementById( 'vgml-talk-say' );
	var go = document.getElementById( 'vgml-talk-go' );
	var note = document.getElementById( 'vgml-talk-note' );
	var out = document.getElementById( 'vgml-talk-plan' );

	if ( ! say || ! go || ! out ) {
		return;
	}

	/**
	 *  One list in the difference: what is kept, what is new, what goes.
	 */
	function drawList( heading, className, items ) {

		if ( ! items.length ) {
			return null;
		}

		var box = el( 'div', 'vgml-talk-set ' + className );

		box.appendChild( el( 'h4', null, heading + ' · ' + items.length ) );

		var list = el( 'ul' );

		items.forEach( function ( item ) {

			var li = el( 'li' );

			if ( 'string' === typeof item ) {
				li.appendChild( el( 'span', 'vgml-talk-name', item ) );
			} else {
				li.appendChild( el( 'span', 'vgml-talk-name', item.name ) );
				li.appendChild( el( 'span', 'vgml-talk-held',
					sprintf( text( 'held', '%s files' ), [ String( item.count ) ] ) ) );
			}

			list.appendChild( li );
		} );

		box.appendChild( list );

		return box;
	}

	/**
	 *  The tree we would end up with, as a tree.
	 */
	function drawTree( folders ) {

		var box = el( 'div', 'vgml-talk-tree' );
		var list = el( 'ul' );

		folders.forEach( function ( f ) {

			var li = el( 'li', f.parent ? 'vgml-talk-child' : null );

			li.appendChild( el( 'span', 'vgml-talk-name', f.name ) );

			if ( f.matches ) {
				li.appendChild( el( 'span', 'vgml-talk-matches', f.matches ) );
			}

			list.appendChild( li );
		} );

		box.appendChild( list );

		return box;
	}

	function drawUndo() {

		var wrap = el( 'p', 'vgml-talk-actions' );

		var undo = document.createElement( 'button' );
		undo.type = 'button';
		undo.className = 'button';
		undo.textContent = text( 'undo', 'Undo this' );

		var said = el( 'span', 'vgml-talk-note' );

		undo.addEventListener( 'click', function () {

			undo.disabled = true;
			said.textContent = text( 'undoing', '' );

			apiFetch( { path: '/vergeml/v1/folders-undo', method: 'POST' } )
				.then( function ( r ) {
					said.textContent = r.message || '';
					undo.parentNode.removeChild( undo );
				} )
				.catch( function ( err ) {
					undo.disabled = false;
					said.textContent = ( err && err.message ) || text( 'failed', '' );
				} );
		} );

		wrap.appendChild( undo );
		wrap.appendChild( said );

		return wrap;
	}

	/**
	 *  The proposal, and the button that makes it real.
	 */
	function drawPlan( plan ) {

		out.innerHTML = '';

		var card = el( 'div', 'vgml-talk-proposal' );

		if ( plan.note ) {
			card.appendChild( el( 'p', 'vgml-talk-said', plan.note ) );
		}

		var diff = plan.diff || { kept: [], added: [], removed: [] };

		if ( ! diff.added.length && ! diff.removed.length ) {
			card.appendChild( el( 'p', 'description', text( 'nothing', '' ) ) );
			out.appendChild( card );
			return;
		}

		var sets = el( 'div', 'vgml-talk-sets' );

		[
			[ text( 'adding', 'New' ), 'is-new', diff.added ],
			[ text( 'removing', 'Going away' ), 'is-gone', diff.removed ],
			[ text( 'keeping', 'Keeping' ), 'is-kept', diff.kept ]
		].forEach( function ( row ) {
			var box = drawList( row[ 0 ], row[ 1 ], row[ 2 ] );
			if ( box ) {
				sets.appendChild( box );
			}
		} );

		card.appendChild( sets );

		card.appendChild( el( 'h4', 'vgml-talk-head', text( 'proposed', '' ) ) );
		card.appendChild( drawTree( plan.folders ) );

		var actions = el( 'p', 'vgml-talk-actions' );

		var apply = document.createElement( 'button' );
		apply.type = 'button';
		apply.className = 'button button-primary';
		apply.textContent = text( 'apply', 'Do it' );

		var cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.className = 'button';
		cancel.textContent = text( 'cancel', 'No, leave it' );

		var said = el( 'span', 'vgml-talk-note' );

		cancel.addEventListener( 'click', function () {
			out.innerHTML = '';
		} );

		apply.addEventListener( 'click', function () {

			apply.disabled = true;
			cancel.disabled = true;
			said.textContent = text( 'applying', '' );

			apiFetch( {
				path: '/vergeml/v1/folders-apply',
				method: 'POST',
				// The plan id binds this press to the proposal that was shown.
				data: { folders: plan.folders, plan_id: plan.plan_id },
			} ).then( function ( r ) {

				out.innerHTML = '';

				var done = el( 'div', 'vgml-talk-done' );
				done.appendChild( el( 'p', 'vgml-talk-doneline', r.message || '' ) );

				if ( r.skipped > 0 ) {
					done.appendChild( el( 'p', 'description',
						sprintf( text( 'skipped', '' ), [ String( r.skipped ) ] ) ) );
				}

				done.appendChild( drawUndo() );
				out.appendChild( done );

			} ).catch( function ( err ) {
				apply.disabled = false;
				cancel.disabled = false;
				said.textContent = ( err && err.message ) || text( 'failed', '' );
			} );
		} );

		actions.appendChild( apply );
		actions.appendChild( cancel );
		actions.appendChild( said );

		card.appendChild( actions );
		out.appendChild( card );
	}

	go.addEventListener( 'click', function () {

		var instruction = String( say.value || '' ).trim();

		if ( '' === instruction ) {
			note.textContent = text( 'empty', '' );
			say.focus();
			return;
		}

		go.disabled = true;
		note.textContent = text( 'thinking', '' );
		out.innerHTML = '';

		apiFetch( {
			path: '/vergeml/v1/folders-propose',
			method: 'POST',
			data: { instruction: instruction },
		} ).then( function ( plan ) {
			note.textContent = '';
			go.disabled = false;
			drawPlan( plan );
		} ).catch( function ( err ) {
			go.disabled = false;
			note.textContent = ( err && err.message ) || text( 'failed', '' );
		} );
	} );

	// Ctrl/Cmd+Enter submits, because this is a textarea somebody is typing a
	// sentence into and Enter has to stay a newline.
	say.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key && ( event.metaKey || event.ctrlKey ) ) {
			go.click();
		}
	} );
}() );
