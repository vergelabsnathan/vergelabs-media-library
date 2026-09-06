/*
 *  One conversation, streamed: the component the Folders screen and the AI
 *  screen's "How it describes" tab share.
 *
 *  It owns the log of turns and the composer: turns render as messages with
 *  a who-line, "- " lines as the brand-mark list and **name** as bold, never
 *  through innerHTML; chips sit under the last reply; the composer grows,
 *  Enter sends, Shift + Enter breaks a line, and the arrow is Stop while a
 *  reply streams. A turn streams straight from the service -- fetch and
 *  server-sent events parsed by hand -- with a token the caller mints; a
 *  401 re-mints once. What the surface does with the block event (a tree,
 *  a brief) and what it persists is the caller's, through the hooks below.
 *
 *  Plain JavaScript, no build step, as everything else in this plugin.
 */
( function () {
	'use strict';

	var wp = window.wp;
	if ( ! wp || ! wp.i18n ) {
		return;
	}
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	function fmt( n ) {
		return new Intl.NumberFormat().format( Number( n ) || 0 );
	}

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( k ) {
				if ( 'class' === k ) {
					node.className = attrs[ k ];
				} else if ( undefined !== attrs[ k ] && null !== attrs[ k ] ) {
					node.setAttribute( k, attrs[ k ] );
				}
			} );
		}
		if ( undefined !== text && null !== text ) {
			node.textContent = text;
		}
		return node;
	}

	/** Text with **bold** into nodes, without ever handing the model's words to innerHTML. */
	function inline( target, text ) {
		var parts = String( text ).split( '**' );
		parts.forEach( function ( part, i ) {
			if ( ! part ) {
				return;
			}
			target.appendChild( i % 2 ? el( 'b', null, part ) : document.createTextNode( part ) );
		} );
	}

	/** "- " lines become the brand-mark list; the rest are paragraphs. */
	function renderSay( target, text ) {
		target.innerHTML = '';
		var lines = String( text || '' ).split( /\r?\n/ );
		var list = null;
		lines.forEach( function ( line ) {
			var t = line.trim();
			if ( ! t ) {
				list = null;
				return;
			}
			var m = /^[-•*]\s+(.*)$/.exec( t );
			if ( m ) {
				if ( ! list ) {
					list = el( 'ul', { class: 'vgml-facts' } );
					target.appendChild( list );
				}
				var li = el( 'li' );
				inline( li, m[ 1 ] );
				list.appendChild( li );
				return;
			}
			list = null;
			var p = el( 'p' );
			inline( p, t );
			target.appendChild( p );
		} );
	}

	/** Server-sent events off a fetch body: "event: x\ndata: {...}\n\n". */
	function readEvents( res, onEvent ) {
		var reader = res.body.getReader();
		var decoder = new TextDecoder();
		var buffer = '';
		function pump() {
			return reader.read().then( function ( r ) {
				if ( r.done ) {
					return;
				}
				buffer += decoder.decode( r.value, { stream: true } );
				var blocks = buffer.split( /\r?\n\r?\n/ );
				buffer = blocks.pop();
				blocks.forEach( function ( block ) {
					var type = 'message';
					var data = '';
					block.split( /\r?\n/ ).forEach( function ( line ) {
						if ( 0 === line.indexOf( 'event:' ) ) {
							type = line.slice( 6 ).trim();
						} else if ( 0 === line.indexOf( 'data:' ) ) {
							data += line.slice( 5 ).trim();
						}
					} );
					var parsed = {};
					try {
						parsed = data ? JSON.parse( data ) : {};
					} catch ( e ) {
						parsed = {};
					}
					onEvent( type, parsed );
				} );
				return pump();
			} );
		}
		return pump();
	}

	/**
	 *  opts:
	 *    session      { turns, assistant_turns, cap }  -- shared with the caller, mutated here
	 *    canTalk()    whether a turn may be sent now
	 *    mint()       Promise of { token, expires_at, stream, ... }: the caller's token route
	 *    streamPath   '/guide/stream' or '/brief/stream', appended to token.stream
	 *    request( token, input )   the body of the stream request
	 *    persist( body )           Promise: { said } | { say, ...extra } to the caller's turn route
	 *    blocks       the event names that carry the block, e.g. [ 'tree' ]
	 *    onBlock( type, data )     the block, as it arrives
	 *    onFinish( r )             r = { text, choices, stopped, done }; returns extra fields to persist with say, or null
	 *    onRender()               after every render, for kickers and states the caller owns
	 *    lead()                   an element to show before the turns, or null (a note on why nothing can be said)
	 *    renderTurn( turn )       a body element for a turn kind the surface owns, or null for the default
	 *    who( turn )              the who-line for a user turn kind, or '' for the default
	 *    placeholder              the composer's placeholder
	 *    cappedPlaceholder( used, cap )
	 *    startOver()              what the Start over button does
	 *    why                      { turn_cap, bad_block } texts
	 */
	function create( opts ) {
		var state = {
			session: opts.session,
			stream: null,
			note: '',
			token: null
		};

		var conv = el( 'div', { class: 'vgml-conv', role: 'log', 'aria-live': 'polite' } );
		var composer = el( 'div', { class: 'vgml-composer' } );
		var text = el( 'textarea', { class: 'vgml-composer-text', rows: '1', placeholder: opts.placeholder, 'aria-label': opts.placeholder } );
		composer.appendChild( text );
		var bar = el( 'div', { class: 'vgml-composer-bar' } );
		var hint = el( 'small', { class: 'vgml-composer-hint' } );
		bar.appendChild( hint );
		var send = el( 'button', { type: 'button', class: 'vgml-send', 'aria-label': __( 'Send', 'vergelabs-media-library' ) } );
		send.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg><i></i>';
		bar.appendChild( send );
		composer.appendChild( bar );

		function grow() {
			text.style.height = 'auto';
			text.style.height = Math.min( 240, text.scrollHeight ) + 'px';
		}
		text.addEventListener( 'input', grow );
		text.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key && ! e.shiftKey ) {
				e.preventDefault();
				sendText();
			}
		} );
		send.addEventListener( 'click', function () {
			if ( state.stream ) {
				stop();
			} else {
				sendText();
			}
		} );

		function cap() {
			return state.session.cap || opts.cap || 25;
		}

		function used() {
			return state.session.assistant_turns || 0;
		}

		function capped() {
			return used() >= cap();
		}

		function canTalk() {
			return ! capped() && ( ! opts.canTalk || opts.canTalk() );
		}

		function who( turn ) {
			if ( 'assistant' === turn.role ) {
				return __( 'Assistant', 'vergelabs-media-library' );
			}
			var own = opts.who ? opts.who( turn ) : '';
			if ( own ) {
				return own;
			}
			if ( 'edit' === turn.kind ) {
				return __( 'You · edited the tree', 'vergelabs-media-library' );
			}
			if ( 'rule' === turn.kind ) {
				return __( 'You · applied a rule', 'vergelabs-media-library' );
			}
			return __( 'You', 'vergelabs-media-library' );
		}

		function messageEl( turn, last ) {
			var msg = el( 'div', { class: 'vgml-msg is-' + ( 'assistant' === turn.role ? 'assistant' : 'user' ) + ( turn.kind ? ' is-' + turn.kind : '' ) } );
			msg.appendChild( el( 'span', { class: 'vgml-msg-who' }, who( turn ) ) );
			var body = opts.renderTurn ? opts.renderTurn( turn ) : null;
			if ( ! body ) {
				body = el( 'div', { class: 'vgml-msg-body' } );
				renderSay( body, turn.text );
			}
			msg.appendChild( body );
			if ( last && 'assistant' === turn.role && turn.choices && turn.choices.length && canTalk() ) {
				var chips = el( 'div', { class: 'vgml-chips' } );
				turn.choices.slice( 0, 3 ).forEach( function ( c ) {
					var b = el( 'button', { type: 'button', class: 'vgml-chip' }, c );
					b.addEventListener( 'click', function () {
						turn( { choice: c }, { kind: 'choice', text: c } );
					} );
					chips.appendChild( b );
				} );
				msg.appendChild( chips );
			}
			return msg;
		}

		function render() {
			conv.innerHTML = '';
			var turns = state.session.turns || [];
			var lastAssistant = -1;
			turns.forEach( function ( t, i ) {
				if ( 'assistant' === t.role ) {
					lastAssistant = i;
				}
			} );
			var lead = opts.lead ? opts.lead() : null;
			if ( lead ) {
				conv.appendChild( lead );
			}
			turns.forEach( function ( t, i ) {
				conv.appendChild( messageEl( t, i === lastAssistant && i === turns.length - 1 ) );
			} );
			if ( state.stream ) {
				conv.appendChild( state.stream.el );
			}
			if ( state.note ) {
				var note = el( 'div', { class: 'vgml-msg is-note' } );
				var facts = el( 'ul', { class: 'vgml-facts' } );
				facts.appendChild( el( 'li', null, state.note ) );
				note.appendChild( facts );
				conv.appendChild( note );
			}
			renderComposer();
			if ( opts.onRender ) {
				opts.onRender();
			}
		}

		function renderComposer() {
			var streaming = !! state.stream;
			send.classList.toggle( 'is-stop', streaming );
			send.setAttribute( 'aria-label', streaming ? __( 'Stop', 'vergelabs-media-library' ) : __( 'Send', 'vergelabs-media-library' ) );
			hint.innerHTML = '';
			if ( capped() ) {
				text.placeholder = opts.cappedPlaceholder
					? opts.cappedPlaceholder( fmt( used() ), fmt( cap() ) )
					/* translators: 1: turns used, 2: the cap */
					: sprintf( __( '%1$s of %2$s turns used. Start over.', 'vergelabs-media-library' ), fmt( used() ), fmt( cap() ) );
				text.disabled = true;
				send.disabled = true;
				var over = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-ghost vgml-startover' }, __( 'Start over', 'vergelabs-media-library' ) );
				over.addEventListener( 'click', function () {
					if ( state.stream ) {
						stop();
					}
					if ( opts.startOver ) {
						opts.startOver();
					}
				} );
				hint.appendChild( over );
				return;
			}
			text.placeholder = opts.placeholder;
			text.disabled = ! canTalk() || streaming;
			send.disabled = ! canTalk() && ! streaming;
			hint.textContent = __( 'Enter sends · Shift + Enter for a new line', 'vergelabs-media-library' );
		}

		/** The turns as the service takes them: role and text. */
		function history() {
			return ( state.session.turns || [] ).map( function ( t ) {
				return { role: t.role, text: t.text };
			} );
		}

		/** A user turn that spends no model call: a hand edit while nothing can be said, a rule. */
		function pushUser( said ) {
			state.session.turns.push( { role: 'user', kind: said.kind, text: said.text, rule: said.rule, at: Math.floor( Date.now() / 1000 ) } );
			var p = opts.persist( { said: said } );
			render();
			return p;
		}

		function ensureToken( force ) {
			if ( ! force && state.token && state.token.expires_at * 1000 - Date.now() > 60000 ) {
				return Promise.resolve( state.token );
			}
			return opts.mint().then( function ( t ) {
				state.token = t;
				return t;
			} );
		}

		function sendText() {
			var value = text.value.trim();
			if ( ! value || ! canTalk() || state.stream ) {
				return;
			}
			text.value = '';
			grow();
			turn( { text: value }, { kind: 'said', text: value } );
		}

		function serviceWhy( status, j ) {
			var code = ( j && ( j.code || j.error ) ) || '';
			if ( 'provider_busy' === code ) {
				/* translators: %s: seconds */
				return sprintf( __( 'The assistant is busy. Try again in %s seconds.', 'vergelabs-media-library' ), fmt( j.retry_after || 60 ) );
			}
			if ( 'turn_cap' === code ) {
				return ( opts.why && opts.why.turn_cap ) || __( 'Every turn of this conversation is used. Start over.', 'vergelabs-media-library' );
			}
			if ( 'bad_tree' === code || 'bad_brief' === code || 'bad_block' === code ) {
				return ( opts.why && opts.why.bad_block ) || __( 'The answer did not come through whole. The words stayed; the draft is as it was.', 'vergelabs-media-library' );
			}
			if ( 'bad_token' === code ) {
				return __( 'The session token was refused. Reload the page.', 'vergelabs-media-library' );
			}
			if ( 429 === status ) {
				return __( 'The day\'s limit of turns for this site is used. Tomorrow it resets.', 'vergelabs-media-library' );
			}
			return __( 'That did not go through. The draft is safe. Try again.', 'vergelabs-media-library' );
		}

		/**
		 *  One streamed turn. What the person said goes into the log and the
		 *  session first; the reply streams into a message of its own, word by
		 *  word; the block lands when it does; the finished turn is persisted.
		 *  Stop keeps the words that arrived.
		 */
		function turn( input, said ) {
			if ( state.stream || ! canTalk() ) {
				return;
			}
			state.note = '';
			if ( said ) {
				state.session.turns.push( { role: 'user', kind: said.kind, text: said.text, rule: said.rule, at: Math.floor( Date.now() / 1000 ) } );
				opts.persist( { said: said } );
			}
			var msg = el( 'div', { class: 'vgml-msg is-assistant is-streaming' } );
			msg.appendChild( el( 'span', { class: 'vgml-msg-who' }, __( 'Assistant', 'vergelabs-media-library' ) ) );
			var body = el( 'div', { class: 'vgml-msg-body' } );
			msg.appendChild( body );
			var controller = new AbortController();
			state.stream = { controller: controller, el: msg, body: body, text: '', choices: [], done: false, paint: null };
			render();
			msg.scrollIntoView( { block: 'nearest' } );

			var paint = function () {
				if ( state.stream && state.stream.paint ) {
					return;
				}
				state.stream.paint = window.requestAnimationFrame( function () {
					if ( ! state.stream ) {
						return;
					}
					state.stream.paint = null;
					renderSay( body, state.stream.text || '…' );
				} );
			};
			renderSay( body, '…' );

			var attempt = function ( force ) {
				return ensureToken( force ).then( function ( token ) {
					return window.fetch( token.stream + opts.streamPath, {
						method: 'POST',
						mode: 'cors',
						signal: controller.signal,
						headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + token.token },
						body: JSON.stringify( opts.request( token, input, history() ) )
					} );
				} );
			};

			attempt( false ).then( function ( res ) {
				if ( 401 === res.status ) {
					return attempt( true );
				}
				return res;
			} ).then( function ( res ) {
				if ( ! res.ok ) {
					return res.json().catch( function () { return {}; } ).then( function ( j ) {
						throw new Error( serviceWhy( res.status, j ) );
					} );
				}
				return readEvents( res, onEvent );
			} ).then( function () {
				finish( false );
			} ).catch( function ( err ) {
				if ( err && 'AbortError' === err.name ) {
					finish( true );
					return;
				}
				state.note = ( err && err.message ) || __( 'That did not go through. The draft is safe. Try again.', 'vergelabs-media-library' );
				finish( false );
			} );

			function onEvent( type, data ) {
				if ( ! state.stream ) {
					return;
				}
				if ( 'say' === type ) {
					state.stream.text += data.text || '';
					paint();
				} else if ( ( opts.blocks || [] ).indexOf( type ) >= 0 ) {
					opts.onBlock( type, data );
				} else if ( 'done' === type ) {
					state.stream.choices = data.choices || [];
					state.stream.done = true;
				} else if ( 'error' === type ) {
					state.note = serviceWhy( 0, data );
				}
			}

			function finish( stopped ) {
				var s = state.stream;
				if ( ! s ) {
					return;
				}
				if ( s.paint ) {
					window.cancelAnimationFrame( s.paint );
				}
				state.stream = null;
				var said = s.text.trim();
				var body = {};
				if ( said ) {
					body.say = { text: said, choices: s.done && ! stopped ? s.choices : [] };
					state.session.turns.push( { role: 'assistant', kind: 'say', text: said, choices: body.say.choices, at: Math.floor( Date.now() / 1000 ) } );
					state.session.assistant_turns = ( state.session.assistant_turns || 0 ) + 1;
				}
				var extra = opts.onFinish ? opts.onFinish( { text: said, choices: body.say ? body.say.choices : [], stopped: stopped, done: s.done } ) : null;
				if ( extra ) {
					Object.keys( extra ).forEach( function ( k ) {
						body[ k ] = extra[ k ];
					} );
				}
				if ( Object.keys( body ).length ) {
					opts.persist( body ).then( function () { render(); } );
				}
				if ( stopped && ! state.note ) {
					state.note = ( opts.why && opts.why.stopped ) || __( 'Stopped. What arrived stays; the draft is as it was.', 'vergelabs-media-library' );
				}
				render();
			}
		}

		function stop() {
			if ( state.stream ) {
				state.stream.controller.abort();
			}
		}

		return {
			conv: conv,
			composer: composer,
			render: render,
			renderComposer: renderComposer,
			turn: turn,
			stop: stop,
			pushUser: pushUser,
			streaming: function () {
				return !! state.stream;
			},
			note: function ( t ) {
				state.note = t || '';
				render();
			},
			setSession: function ( s ) {
				state.session = s;
				opts.session = s;
			},
			used: used,
			cap: cap,
			capped: capped,
			canTalk: canTalk,
			el: el,
			fmt: fmt,
			renderSay: renderSay
		};
	}

	window.vergemlTalk = { create: create, el: el, fmt: fmt, renderSay: renderSay, inline: inline };
}() );
