/*
 *  Sorting into folders, as a conversation.
 *
 *  What was here asked one question and drew one answer with Do it and Cancel
 *  underneath. Every refinement started from nothing, and saying "no, split
 *  that one further" meant retyping the whole sentence -- which is not a
 *  conversation, it is a slot machine with a text box.
 *
 *  So this is a transcript. What you said and what it proposed stay on screen
 *  in order, each proposal drawn as the tree it would leave you with, and the
 *  box at the bottom stays where it is. The turns travel with the next
 *  request, so "drop nature as well" refines the thing in front of you rather
 *  than planning the library again from scratch.
 *
 *  Nothing here applies anything. Do it is one deliberate action at the end,
 *  on the proposal you are looking at.
 */
( function () {

	var apiFetch = window.wp && window.wp.apiFetch;
	var log = document.getElementById( 'vgml-talk-log' );
	var say = document.getElementById( 'vgml-talk-say' );
	var go = document.getElementById( 'vgml-talk-go' );
	var note = document.getElementById( 'vgml-talk-note' );
	var strings = window.vergemlTalk || {};

	if ( ! apiFetch || ! log || ! say || ! go ) {
		return;
	}

	/** Everything said so far, oldest first, as the service wants it. */
	var history = [];

	/** The proposal the Do it button would apply, and the plan id it came with. */
	var live = null;

	function text( key, fallback ) {
		return typeof strings[ key ] === 'string' && strings[ key ] !== '' ? strings[ key ] : fallback;
	}

	function el( tag, className, textContent ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( textContent !== undefined && textContent !== null ) {
			node.textContent = textContent;
		}
		return node;
	}

	function scrollToEnd() {
		log.scrollTop = log.scrollHeight;
	}

	/** One turn in the transcript. Returns the body, for callers that fill it. */
	function turn( who, className ) {
		var row = el( 'div', 'vgml-talk-turn vgml-talk-' + who );
		var body = el( 'div', 'vgml-talk-bubble ' + ( className || '' ) );
		row.appendChild( body );
		log.appendChild( row );
		scrollToEnd();
		return body;
	}

	/** The folders a proposal would leave, parents before their children. */
	function drawTree( folders ) {

		var wrap = el( 'ul', 'vgml-talk-tree' );
		var byParent = {};

		folders.forEach( function ( f ) {
			var key = f.parent || '';
			if ( ! byParent[ key ] ) {
				byParent[ key ] = [];
			}
			byParent[ key ].push( f );
		} );

		function branch( parent, into ) {
			( byParent[ parent ] || [] ).forEach( function ( f ) {
				var li = el( 'li' );
				li.appendChild( el( 'span', 'vgml-talk-folder', f.name ) );
				if ( typeof f.matches === 'number' ) {
					li.appendChild( el( 'span', 'vgml-talk-count', String( f.matches ) ) );
				}
				if ( byParent[ f.name ] ) {
					var kids = el( 'ul' );
					branch( f.name, kids );
					li.appendChild( kids );
				}
				into.appendChild( li );
			} );
		}

		branch( '', wrap );
		return wrap;
	}

	/** Draw what it proposed, and offer to apply that one. */
	function drawProposal( plan ) {

		live = plan;

		var body = turn( 'them' );

		if ( plan.note ) {
			body.appendChild( el( 'p', 'vgml-talk-said', plan.note ) );
		}

		var folders = plan.folders || [];

		if ( folders.length === 0 ) {
			body.appendChild( el( 'p', 'vgml-talk-said', text( 'empty', 'That would leave you with no folders at all, so nothing has been changed.' ) ) );
			return;
		}

		body.appendChild( drawTree( folders ) );

		var actions = el( 'p', 'vgml-talk-apply' );
		var apply = el( 'button', 'button button-primary', text( 'apply', 'Do it' ) );
		apply.type = 'button';

		apply.addEventListener( 'click', function () {

			apply.disabled = true;
			apply.textContent = text( 'applying', 'Moving the files…' );

			apiFetch( {
				path: '/vergeml/v1/folders-apply',
				method: 'POST',
				data: { folders: folders, plan_id: plan.plan_id }
			} ).then( function ( done ) {
				actions.textContent = '';
				actions.appendChild( el( 'span', 'vgml-talk-done', ( done && done.message )
					|| text( 'applied', 'Done. The folders are as you asked.' ) ) );
				history.push( { role: 'user', text: '(applied that)' } );
				scrollToEnd();
			} ).catch( function ( err ) {
				apply.disabled = false;
				apply.textContent = text( 'apply', 'Do it' );
				actions.appendChild( el( 'span', 'vgml-talk-error',
					( err && err.message ) || text( 'failed', 'That did not go through. Try again.' ) ) );
			} );
		} );

		actions.appendChild( apply );
		actions.appendChild( el( 'span', 'vgml-talk-hint', text( 'refine', 'Or just say what to change.' ) ) );
		body.appendChild( actions );
		scrollToEnd();
	}

	function send() {

		var instruction = String( say.value || '' ).trim();

		if ( instruction === '' ) {
			say.focus();
			return;
		}

		turn( 'you' ).appendChild( el( 'p', null, instruction ) );
		history.push( { role: 'user', text: instruction } );

		say.value = '';
		go.disabled = true;
		note.textContent = text( 'thinking', 'Working out what that would look like…' );

		apiFetch( {
			path: '/vergeml/v1/folders-propose',
			method: 'POST',
			data: {
				instruction: instruction,
				// Only what was said, and only the last few turns: the service
				// caps this as well, and a transcript is not a memory.
				history: history.slice( -12 )
			}
		} ).then( function ( plan ) {
			note.textContent = '';
			go.disabled = false;
			drawProposal( plan );
			history.push( {
				role: 'assistant',
				text: ( plan.note ? plan.note + ' ' : '' )
					+ 'Folders: ' + ( plan.folders || [] ).map( function ( f ) {
						return f.parent ? f.parent + ' / ' + f.name : f.name;
					} ).join( ', ' )
			} );
			say.focus();
		} ).catch( function ( err ) {
			note.textContent = '';
			go.disabled = false;
			turn( 'them' ).appendChild( el( 'p', 'vgml-talk-error',
				( err && err.message ) || text( 'failed', 'That did not go through. Try again.' ) ) );
			say.focus();
		} );
	}

	go.addEventListener( 'click', send );

	// Enter sends, Shift+Enter is a newline: this is a chat box, and every
	// other chat box on earth behaves this way.
	say.addEventListener( 'keydown', function ( event ) {
		if ( event.key === 'Enter' && ! event.shiftKey ) {
			event.preventDefault();
			send();
		}
	} );
}() );
