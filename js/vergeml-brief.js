/*
 *  How it describes: the brief as a conversation.
 *
 *  Left, the conversation (js/vergeml-talk.js, the Folders component),
 *  streamed from /v1/brief/stream with a token core/brief.php mints. Right,
 *  the brief: the draft in ink as the assistant writes it, the brief in use
 *  in grey beneath, and the controls that say what they cost -- "Test on 5
 *  pictures · 5 credits", "Re-describe 641 pictures with this brief". The
 *  opener came with the page and cost nothing; the first model call is the
 *  person's first message.
 */
( function () {
	'use strict';

	var cfg = window.vgmlBrief || {};
	var TALK = window.vergemlTalk;
	var wp = window.wp;
	if ( ! TALK || ! wp || ! wp.apiFetch || ! wp.i18n ) {
		return;
	}
	var __ = wp.i18n.__;
	var _n = wp.i18n._n;
	var sprintf = wp.i18n.sprintf;
	var el = TALK.el;
	var fmt = TALK.fmt;

	var root = document.getElementById( 'vgml-brief-talk' );
	var panel = document.getElementById( 'vgml-brief-panel' );
	var turnsKicker = document.getElementById( 'vgml-brief-turns' );
	if ( ! root || ! panel ) {
		return;
	}

	function api( method, route, data ) {
		var o = { path: '/' + ( cfg.ns || 'vergeml/v1' ) + '/' + route, method: method };
		if ( data ) {
			o.data = data;
		}
		return wp.apiFetch( o );
	}

	var state = {
		session: cfg.session || { turns: [], draft: null, assistant_turns: 0, cap: cfg.cap || 25, test: null },
		inUse: cfg.in_use || '',
		described: cfg.described || 0,
		licensed: !! cfg.licensed,
		max: cfg.max || 500,
		testN: cfg.test_n || 5,
		busy: '',
		run: null
	};
	var streamBrief = null;
	var queue = Promise.resolve();

	/** Writes to the session go one after another, in the order they happened. */
	function persist( body ) {
		queue = queue.then( function () {
			return api( 'POST', 'brief/turn', body ).then( take );
		}, function () {} );
		return queue;
	}

	/** The session as the server answers it, taken whole. */
	function take( r ) {
		if ( r && r.turns ) {
			state.session.turns = r.turns;
			state.session.assistant_turns = r.assistant_turns;
			state.session.draft = r.draft;
			state.session.test = r.test;
			state.inUse = r.in_use || '';
			renderPanel();
		}
		return r;
	}

	/* ---------------------------------------------------- the conversation */

	function leadNote() {
		if ( ! state.licensed ) {
			var nol = el( 'div', { class: 'vgml-msg is-note' } );
			var l = el( 'ul', { class: 'vgml-facts' } );
			l.appendChild( el( 'li', null, __( 'No licence connected', 'vergelabs-media-library' ) ) );
			l.appendChild( el( 'li', null, __( 'The conversation needs one', 'vergelabs-media-library' ) ) );
			nol.appendChild( l );
			nol.appendChild( el( 'a', { class: 'vgml-btn', href: cfg.licenceUrl || '#' }, __( 'Connect a licence', 'vergelabs-media-library' ) ) );
			return nol;
		}
		return null;
	}

	/** The test's turn: the assistant's lines, then each picture before and after. */
	function renderTest( turn ) {
		var body = el( 'div', { class: 'vgml-msg-body' } );
		TALK.renderSay( body, turn.text );
		var test = state.session.test;
		var last = null;
		( state.session.turns || [] ).forEach( function ( t ) {
			if ( 'test' === t.kind && 'assistant' === t.role ) {
				last = t;
			}
		} );
		if ( ! test || ! test.rows || last !== turn ) {
			return body;
		}
		var list = el( 'div', { class: 'vgml-diffs' } );
		test.rows.forEach( function ( row ) {
			var d = el( 'div', { class: 'vgml-diff' } );
			var img = el( 'img', { alt: '', src: row.thumb || '' } );
			if ( ! row.thumb ) {
				img.hidden = true;
			}
			d.appendChild( img );
			var txt = el( 'div' );
			txt.appendChild( el( 'div', { class: 'vgml-diff-t' }, row.title || ( '#' + row.id ) ) );
			var was = el( 'div', { class: 'vgml-diff-was' } );
			was.appendChild( el( 'i', null, __( 'was', 'vergelabs-media-library' ) ) );
			was.appendChild( el( 'span', null, row.before.caption ) );
			var now = el( 'div', { class: 'vgml-diff-now' } );
			now.appendChild( el( 'i', null, __( 'now', 'vergelabs-media-library' ) ) );
			now.appendChild( el( 'span', null, row.after.caption ) );
			txt.appendChild( was );
			txt.appendChild( now );
			d.appendChild( txt );
			list.appendChild( d );
		} );
		// The lines the server wrote come after the rows: the summary reads better under the evidence.
		var facts = body.querySelector( '.vgml-facts' );
		if ( facts ) {
			body.insertBefore( list, facts );
		} else {
			body.appendChild( list );
		}
		return body;
	}

	var talk = TALK.create( {
		session: state.session,
		cap: cfg.cap || 25,
		canTalk: function () {
			return state.licensed && ! state.busy;
		},
		mint: function () {
			return api( 'POST', 'brief/token' );
		},
		streamPath: '/brief/stream',
		request: function ( token, input, history ) {
			return { conversation: history, in_use: token.in_use || state.inUse, draft: state.session.draft, input: input, summary: token.summary };
		},
		persist: persist,
		blocks: [ 'brief' ],
		onBlock: function ( type, data ) {
			streamBrief = Array.isArray( data.brief ) && data.brief.length ? data.brief : null;
			state.session.draft = streamBrief;
			renderPanel();
		},
		onFinish: function () {
			var extra = streamBrief ? { draft: streamBrief } : null;
			streamBrief = null;
			renderPanel();
			return extra;
		},
		onRender: renderKicker,
		lead: leadNote,
		renderTurn: function ( turn ) {
			return 'test' === turn.kind && 'assistant' === turn.role ? renderTest( turn ) : null;
		},
		who: function ( turn ) {
			if ( 'test' === turn.kind ) {
				return __( 'You · tested the draft', 'vergelabs-media-library' );
			}
			return '';
		},
		placeholder: __( 'What this site sells or publishes', 'vergelabs-media-library' ),
		startOver: function () {
			api( 'POST', 'brief/session', { reset: true } ).then( function ( boot ) {
				state.session = boot.session;
				state.inUse = boot.in_use || '';
				talk.setSession( state.session );
				renderPanel();
				talk.note( '' );
			} );
		},
		why: {
			turn_cap: __( 'Every turn of this conversation is used. Start over.', 'vergelabs-media-library' ),
			bad_block: __( 'The brief did not come through. The words stayed; the draft is as it was.', 'vergelabs-media-library' )
		}
	} );

	root.appendChild( talk.conv );
	root.appendChild( talk.composer );

	function renderKicker() {
		if ( turnsKicker ) {
			/* translators: 1: turns used, 2: the cap */
			turnsKicker.textContent = sprintf( __( '%1$s of %2$s turns', 'vergelabs-media-library' ), fmt( talk.used() ), fmt( talk.cap() ) );
		}
	}

	/* ----------------------------------------------------------- the brief */

	function chars( lines ) {
		return ( lines || [] ).join( ' ' ).length;
	}

	function renderPanel() {
		panel.innerHTML = '';
		var draft = state.session.draft && state.session.draft.length ? state.session.draft : null;
		var head = el( 'div', { class: 'vgml-talk-head' } );
		head.appendChild( el( 'span', { class: 'vgml-kicker' }, draft ? __( 'The brief · draft', 'vergelabs-media-library' ) : ( state.inUse ? __( 'The brief · in use', 'vergelabs-media-library' ) : __( 'The brief', 'vergelabs-media-library' ) ) ) );
		var n = draft ? chars( draft ) : state.inUse.length;
		/* translators: 1: characters used, 2: the most allowed */
		head.appendChild( el( 'span', { class: 'vgml-kicker' }, sprintf( __( '%1$s of %2$s characters', 'vergelabs-media-library' ), fmt( n ), fmt( state.max ) ) ) );
		panel.appendChild( head );

		var block = el( 'div', { class: 'vgml-brief-block' + ( draft ? '' : ' is-inuse' ) } );
		var lines = el( 'ul', { class: 'vgml-facts' } );
		if ( draft ) {
			draft.forEach( function ( line ) {
				lines.appendChild( el( 'li', null, line ) );
			} );
		} else if ( state.inUse ) {
			lines.appendChild( el( 'li', null, state.inUse ) );
		} else {
			lines.appendChild( el( 'li', null, __( 'No brief yet · every picture is described from what it shows', 'vergelabs-media-library' ) ) );
		}
		block.appendChild( lines );
		if ( draft && state.inUse ) {
			var now = el( 'div', { class: 'vgml-brief-now' } );
			now.appendChild( el( 'b', null, __( 'In use', 'vergelabs-media-library' ) ) );
			now.appendChild( document.createTextNode( state.inUse ) );
			block.appendChild( now );
		}
		panel.appendChild( block );

		var actions = el( 'div', { class: 'vgml-brief-actions' } );
		var run = state.run;
		if ( run && run.active ) {
			var going = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary', disabled: 'disabled' },
				/* translators: 1: pictures described again so far, 2: pictures in the run */
				sprintf( __( 'Re-describing %1$s of %2$s', 'vergelabs-media-library' ), fmt( run.described ), fmt( run.total ) ) );
			var stop = el( 'button', { type: 'button', class: 'vgml-btn' }, __( 'Stop', 'vergelabs-media-library' ) );
			stop.addEventListener( 'click', function () {
				stop.disabled = true;
				api( 'POST', 'ai-run', { action: 'stop' } ).then( function ( r ) {
					state.run = r;
					renderPanel();
				} );
			} );
			actions.appendChild( going );
			actions.appendChild( stop );
			panel.appendChild( actions );
			var bar = el( 'div', { class: 'vgml-import-bar vgml-ai-bar' } );
			var fill = el( 'div', { class: 'vgml-import-fill' } );
			fill.style.width = run.total ? Math.round( ( run.described / run.total ) * 100 ) + '%' : '0';
			bar.appendChild( fill );
			panel.appendChild( bar );
			var facts = el( 'ul', { class: 'vgml-facts' } );
			facts.appendChild( el( 'li', null, __( 'Runs in the background · the Describe tab shows the same run', 'vergelabs-media-library' ) ) );
			panel.appendChild( facts );
			return;
		}

		if ( draft ) {
			var adopt = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary', id: 'vgml-brief-adopt' },
				'adopt' === state.busy
					? __( 'Saving the brief', 'vergelabs-media-library' )
					/* translators: %s: pictures */
					: sprintf( _n( 'Re-describe %s picture with this brief', 'Re-describe %s pictures with this brief', state.described, 'vergelabs-media-library' ), fmt( state.described ) ) );
			adopt.disabled = !! state.busy || ! state.licensed || ! state.described;
			adopt.addEventListener( 'click', onAdopt );
			var test = el( 'button', { type: 'button', class: 'vgml-btn', id: 'vgml-brief-test' },
				'test' === state.busy
					/* translators: %s: pictures */
					? sprintf( __( 'Testing %s pictures', 'vergelabs-media-library' ), fmt( state.testN ) )
					/* translators: 1: pictures, 2: credits */
					: sprintf( __( 'Test on %1$s pictures · %2$s credits', 'vergelabs-media-library' ), fmt( state.testN ), fmt( state.testN ) ) );
			test.disabled = !! state.busy || ! state.licensed || ! state.described;
			test.addEventListener( 'click', onTest );
			var discard = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-ghost', id: 'vgml-brief-discard' }, __( 'Discard the draft', 'vergelabs-media-library' ) );
			discard.disabled = !! state.busy;
			discard.addEventListener( 'click', onDiscard );
			actions.appendChild( adopt );
			actions.appendChild( test );
			actions.appendChild( discard );
			panel.appendChild( actions );
		}
	}

	function onTest() {
		if ( state.busy || talk.streaming() ) {
			return;
		}
		state.busy = 'test';
		renderPanel();
		// The cost, in the log, before it runs.
		/* translators: 1: pictures, 2: credits */
		talk.pushUser( { kind: 'test', text: sprintf( __( 'Test on %1$s pictures · %2$s credits', 'vergelabs-media-library' ), fmt( state.testN ), fmt( state.testN ) ) } );
		queue.then( function () {
			return api( 'POST', 'brief/test' );
		} ).then( function ( r ) {
			state.busy = '';
			take( r );
			talk.render();
		} ).catch( function ( err ) {
			state.busy = '';
			renderPanel();
			talk.note( ( err && err.message ) || __( 'The test did not go through. Nothing was spent.', 'vergelabs-media-library' ) );
		} );
	}

	function onAdopt() {
		if ( state.busy || talk.streaming() ) {
			return;
		}
		state.busy = 'adopt';
		renderPanel();
		api( 'POST', 'brief/adopt' ).then( function ( r ) {
			state.busy = '';
			state.run = r.run || null;
			take( r.session );
			talk.render();
			watchRun();
		} ).catch( function ( err ) {
			state.busy = '';
			renderPanel();
			talk.note( ( err && err.message ) || __( 'That did not go through. The brief in use is unchanged.', 'vergelabs-media-library' ) );
		} );
	}

	function onDiscard() {
		if ( state.busy ) {
			return;
		}
		api( 'POST', 'brief/discard' ).then( function ( r ) {
			take( r );
			talk.render();
		} );
	}

	var runTimer = null;

	/** The re-describe started by adopting, watched until it ends. */
	function watchRun() {
		window.clearTimeout( runTimer );
		if ( ! state.run || ! state.run.active ) {
			renderPanel();
			return;
		}
		runTimer = window.setTimeout( function () {
			api( 'GET', 'ai-run' ).then( function ( r ) {
				state.run = r;
				renderPanel();
				watchRun();
			} ).catch( function () {} );
		}, 5000 );
	}

	/* ------------------------------------------------------------------ go */

	renderPanel();
	talk.render();
	// A run already going -- the sweep from an earlier adopt -- is watched from here too.
	api( 'GET', 'ai-run' ).then( function ( r ) {
		if ( r && r.active && 'stale' === r.scope ) {
			state.run = r;
			renderPanel();
			watchRun();
		}
	} ).catch( function () {} );

	window.vgmlBriefApp = { state: state, talk: talk };
}() );
