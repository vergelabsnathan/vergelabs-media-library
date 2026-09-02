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
	var empty = document.getElementById( 'vgml-talk-empty' );
	var flow = document.getElementById( 'vgml-lib-flow' );
	var fold = document.getElementById( 'vgml-lib-fold' );
	var unfold = document.getElementById( 'vgml-lib-unfold' );
	var strings = window.vergemlTalk || {};

	if ( ! apiFetch || ! log || ! say || ! go ) {
		return;
	}

	/** Whether anything has been said yet. Only the first turn rearranges the page. */
	var started = false;

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

	/*
	 *  Follow the conversation, but not over the reader's shoulder.
	 *
	 *  This set scrollTop to scrollHeight on every turn, which yanked the
	 *  transcript to the bottom mid-sentence whenever a reply landed while
	 *  somebody was reading back through an earlier proposal. Scrolling away
	 *  from the end is a decision; it is not something to undo for them.
	 */
	function scrollToEnd( force ) {

		var near = log.scrollHeight - log.scrollTop - log.clientHeight < 120;

		if ( ! force && ! near ) {
			return;
		}

		if ( typeof log.scrollTo === 'function' ) {
			log.scrollTo( { top: log.scrollHeight, behavior: 'smooth' } );
		} else {
			log.scrollTop = log.scrollHeight;
		}
	}

	/*
	 *  The page belongs to the conversation now.
	 *
	 *  The step-by-step suggestions and a chat proposing different folders
	 *  were both on screen at once, each showing a set of folders, with no way
	 *  to tell which one Do it referred to. The wizard folds away on the first
	 *  thing said, and says where it went.
	 */
	function beginConversation() {

		if ( started ) {
			return;
		}

		started = true;

		if ( empty ) {
			empty.hidden = true;
		}
		if ( flow ) {
			flow.hidden = true;
		}
		if ( fold ) {
			fold.hidden = false;
		}
	}

	if ( unfold && flow && fold ) {
		unfold.addEventListener( 'click', function () {
			flow.hidden = false;
			fold.hidden = true;
		} );
	}

	/** The openers in the empty state: pressing one is the same as typing it. */
	if ( empty ) {
		Array.prototype.forEach.call(
			empty.querySelectorAll( '.vgml-talk-chip' ),
			function ( chip ) {
				chip.addEventListener( 'click', function () {
					say.value = chip.textContent;
					send();
				} );
			}
		);
	}

	/** A textarea that grows with what is in it, up to the height the CSS allows. */
	function fitBox() {
		say.style.height = 'auto';
		say.style.height = Math.min( say.scrollHeight, 176 ) + 'px';
	}

	say.addEventListener( 'input', fitBox );

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

	/*
	 *  Keep one line honest while the re-filing finishes behind it.
	 *
	 *  Re-filing a large library does not fit in the request that starts it, so
	 *  it carries on afterwards. Without this the screen said a number that
	 *  stopped being true a second later -- the same shape of lie the describe
	 *  run told when it claimed to be working and had quietly stopped.
	 *
	 *  Stops on its own when the job says it is finished, and stops on a failed
	 *  poll as well: a progress line that cannot reach the site is worse than
	 *  no progress line at all.
	 */
	function watch( line ) {

		var timer = window.setInterval( function () {

			apiFetch( { path: '/vergeml/v1/folders-progress' } ).then( function ( at ) {

				if ( ! at || ! at.running ) {
					window.clearInterval( timer );
				}

				if ( at && at.message ) {
					line.textContent = at.message;
				}
			} ).catch( function () {
				window.clearInterval( timer );
			} );
		}, 2500 );
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
			/*
			 *  'empty' means the box was empty, which is a different sentence
			 *  said in a different place. While every lookup was silently
			 *  missing, both fell back to the right words and the collision
			 *  could not be seen; now that they resolve, it can.
			 */
			body.appendChild( el( 'p', 'vgml-talk-said', text( 'noFolders', 'That would leave you with no folders at all, so nothing has been changed.' ) ) );
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

				var said = el( 'span', 'vgml-talk-done', ( done && done.message )
					|| text( 'applied', 'Done. The folders are as you asked.' ) );
				actions.appendChild( said );

				history.push( { role: 'user', text: '(applied that)' } );
				scrollToEnd();

				/*
				 *  A library larger than one pass is still being filed when this
				 *  returns, so the line has to keep up rather than announcing it
				 *  is done and leaving somebody to guess.
				 */
				if ( done && done.running ) {
					watch( said );
				}
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

		beginConversation();

		turn( 'you' ).appendChild( el( 'p', null, instruction ) );
		history.push( { role: 'user', text: instruction } );

		say.value = '';
		fitBox();
		go.disabled = true;

		/*
		 *  Three dots in a bubble where the reply will be, rather than a
		 *  sentence somewhere else on the page. It appears in the place the
		 *  answer is coming, so the wait reads as part of the conversation --
		 *  and it is removed by the same code that replaces it, whichever way
		 *  the request ends.
		 */
		var waiting = turn( 'them' );
		var dots = el( 'span', 'vgml-talk-thinking' );
		dots.appendChild( el( 'i' ) );
		dots.appendChild( el( 'i' ) );
		dots.appendChild( el( 'i' ) );
		waiting.appendChild( dots );
		waiting.parentNode.setAttribute( 'data-waiting', '1' );

		note.textContent = text( 'thinking', 'Working out what that would look like…' );
		scrollToEnd( true );

		var done = function () {
			var row = log.querySelector( '[data-waiting]' );
			if ( row !== null ) {
				row.parentNode.removeChild( row );
			}
			note.textContent = '';
			go.disabled = false;
		};

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
			done();
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
			done();
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
