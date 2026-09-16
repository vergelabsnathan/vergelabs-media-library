/*
 *  Folders: five steps, one tree, one primary.
 *
 *  The rail at the top is the spec's order (docs/superpowers/specs/
 *  2026-09-14-every-picture-a-home.md §3): Describe · Tree · Fill · Alt text
 *  · Rename. Every pill on it is a button and no step is a gate: a person can
 *  go to any of them, do it, or leave it -- Skip beside the primary, Leave the
 *  rest under the questions. The page opens on the step the session is at
 *  and fires no model route on load: a proposal is a button with its cost.
 *
 *  Step 2 is the approved mock (2026-09-15-step-rail.html): one column, the
 *  tree as the screen with every count a pill, and under it one line to
 *  change it -- the input (its placeholder built from the tree on screen),
 *  three chips, and "Paste or upload a list", a .txt or .csv read here in the
 *  browser into the same paste parser. Then the primary, "This is my tree",
 *  which calls /guide/confirm and moves to Fill.
 *
 *  Either way in produces a draft, never folders: the same draft, over the
 *  same tree, behind the same run with the same undo. A paste settles through
 *  the same turn route a conversation's tree does, so every number beside it
 *  is the matcher's dry run and none is the paste's own.
 *
 *  Everything the first paint needs came with the page (vgmlFolders): no
 *  request stands between the page and the tree. The session persists each
 *  finished turn and the draft, keyed by term id, so a reload shows the same
 *  tree. The conversation's turns are kept for the service (the history a
 *  turn is sent with) and shown one line at a time, under the input.
 *
 *  Plain JavaScript, no build step, as everything else in this plugin.
 */
( function () {
	'use strict';

	var cfg = window.vgmlFolders || {};
	var TV = window.vergemlTreeView;
	var TALK = window.vergemlTalk;
	var STRUCT = window.vergemlStructure;
	var wp = window.wp;
	if ( ! TV || ! TALK || ! STRUCT || ! wp || ! wp.apiFetch || ! wp.i18n ) {
		return;
	}
	var __ = wp.i18n.__;
	var _n = wp.i18n._n;
	var sprintf = wp.i18n.sprintf;
	var el = TV.el;
	var fmt = TV.fmt;

	function api( method, route, data ) {
		var o = { path: '/' + ( cfg.ns || 'vergeml/v1' ) + '/' + route, method: method };
		if ( data ) {
			o.data = data;
		}
		return wp.apiFetch( o );
	}

	function lower( s ) {
		return String( s || '' ).toLowerCase().trim();
	}

	/* ------------------------------------------------------------- state */

	var state = {
		session: cfg.session || { turns: [], draft: null, assistant_turns: 0, cap: cfg.cap || 25, apply: null, tree: 'editing' },
		nodes: cfg.nodes || [],
		version: cfg.version || 0,
		facts: cfg.facts || {},
		fill: cfg.fill || { open: 0, unfiled: 0, running: false, done: false },
		undo: cfg.undo || { available: false, until: 0 },
		// What vergeml_filing_pick() said about the draft, from the turn that
		// settled it. The session carries it, so a reload reads the same numbers.
		fit: ( cfg.session && cfg.session.fit ) || null,
		// A paste handed to the turn route and not yet answered: the rows carry
		// no count and the run waits, because the only numbers that could be
		// shown are ones nobody has computed.
		pastePending: false,
		pasteSeq: 0,
		moving: null,
		step: '',
		// The questions the fill left (/guide/questions), the folders the answers
		// made, the card just answered (shown with its result line until the
		// next answer), and the pictures "Show me" opened, by question id.
		questions: [],
		made: cfg.made || [],
		lastAnswered: '',
		showing: {},
		answering: false,
		// Step 4 while it writes: how many are left.
		altWriting: false,
		altLeft: 0,
		// After an Unconfirm: folders whose classes that confirm replaced, so Restore can be offered (C.4).
		prevProfiles: 0
	};
	var described = ( cfg.described || 0 ) > 0;
	var licensed = !! cfg.licensed;
	var queue = Promise.resolve();

	function confirmed() {
		return 'confirmed' === state.session.tree;
	}

	function running() {
		return !! ( state.applying || ( state.session.apply && state.session.apply.running ) );
	}

	/** Questions the fill left and nobody has answered. */
	function openQuestions() {
		return state.questions.filter( function ( q ) {
			return ! q.answered;
		} );
	}

	/*
	 *  Step 3 is done when there is no open question and 0 pictures in no
	 *  folder (spec §2 Step 3) -- both at zero, and nothing less, and never
	 *  while a run goes.
	 */
	function fillDone() {
		return described && ! running() && 0 === ( Number( state.fill.open ) || 0 ) && 0 === ( Number( state.fill.unfiled ) || 0 );
	}

	/** Writes to the session go one after another, in the order they happened. */
	function persist( route, body ) {
		queue = queue.then( function () {
			return api( 'POST', route, body ).then( function ( r ) {
				if ( r && r.turns ) {
					state.session.turns = r.turns;
					state.session.assistant_turns = r.assistant_turns;
				}
				/*
				 *  The turn route runs the matcher over the draft before it
				 *  hands it back, so this is where the real numbers arrive:
				 *  the count on every folder, and what the run would not
				 *  place -- which is the number an owner needs before the
				 *  fill and the one the model cannot write.
				 *
				 *  A session write answers `fit: null`, and that clears the
				 *  pills rather than leaving an answer about an older tree.
				 */
				if ( r && undefined !== r.fit ) {
					tookFit( r );
				}
				return r;
			} );
		}, function () {} );
		return queue;
	}

	function tookFit( r ) {
		/*
		 *  Not while a run goes. The turn that was in flight when the button
		 *  was pressed answers with the draft it settled, and taking it would
		 *  put back on screen the very draft the run is in the middle of
		 *  applying -- after took() had cleared it.
		 */
		if ( running() ) {
			return;
		}
		if ( r.fit && r.draft ) {
			setDraft( r.draft, true );
		}
		// After setDraft, which clears it: the run belongs to the draft it was
		// computed for and to no other.
		state.fit = r.fit || null;
		renderCards();
	}

	function capped() {
		return ( state.session.assistant_turns || 0 ) >= ( state.session.cap || cfg.cap || 25 );
	}

	function canTalk() {
		return described && licensed && ! capped() && ! running() && ! confirmed();
	}

	/* ------------------------------------------------------------- steps */

	var STEPS = [ 'describe', 'tree', 'fill', 'alt', 'rename' ];

	/** The step the session is at: what a returning person lands on. */
	function stepAt() {
		if ( ! described ) {
			return 'describe';
		}
		if ( confirmed() || running() || ( Number( state.fill.open ) || 0 ) > 0 ) {
			return 'fill';
		}
		return 'tree';
	}

	function stepDone( step ) {
		var f = state.facts;
		if ( 'describe' === step ) {
			return described && ! ( f.not_described > 0 );
		}
		if ( 'tree' === step ) {
			return confirmed();
		}
		if ( 'fill' === step ) {
			return fillDone();
		}
		if ( 'alt' === step ) {
			return f.images > 0 && ! ( f.alt_missing > 0 );
		}
		return false;
	}

	function setStep( step ) {
		state.step = STEPS.indexOf( step ) >= 0 ? step : stepAt();
		renderCards();
	}

	/* --------------------------------------------------------------- DOM */

	var root = document.getElementById( 'vgml-folders' );
	if ( ! root ) {
		return;
	}

	var dom = { cards: {}, slots: {} };
	var rail = root.parentNode.querySelector( '.g-rail' );

	function pill( n, word, tone, attrs ) {
		var p = el( 'span', attrs || {} );
		p.className = 'g-pill' + ( tone ? ' is-' + tone : '' );
		p.appendChild( el( 'b', null, fmt( n ) ) );
		p.appendChild( document.createTextNode( ' ' + word ) );
		return p;
	}

	function card( key, title ) {
		var c = el( 'div', { class: 'g-card', 'data-card': key, hidden: 'hidden' } );
		var head = el( 'div', { class: 'g-card-head' } );
		head.appendChild( el( 'h2', null, title ) );
		var pills = el( 'div', { class: 'g-pills' } );
		head.appendChild( pills );
		c.appendChild( head );
		dom.cards[ key ] = { el: c, pills: pills };
		return c;
	}

	function quiet( text, onClick ) {
		var b = el( 'button', { type: 'button', class: 'g-quiet' }, text );
		b.addEventListener( 'click', onClick );
		return b;
	}

	function build() {
		var cols = el( 'div', { class: 'g-cols' } );
		dom.cols = cols;

		// Step 1 · Describe: what the AI screen runs, here as the first step.
		var describe = card( 'describe', __( 'What each picture shows', 'vergelabs-media-library' ) );
		describe.appendChild( el( 'p', { class: 'g-line' }, __( 'Once a picture, never again for a tree change.', 'vergelabs-media-library' ) ) );
		dom.describeMove = el( 'div', { class: 'g-move' } );
		describe.appendChild( dom.describeMove );
		cols.appendChild( describe );

		// Step 2 · The tree.
		var tree = card( 'tree', __( 'What the pictures show', 'vergelabs-media-library' ) );
		dom.slots.tree = el( 'div', { class: 'g-tree-slot' } );
		tree.appendChild( dom.slots.tree );
		tree.appendChild( buildChange() );
		dom.treeMove = el( 'div', { class: 'g-move' } );
		tree.appendChild( dom.treeMove );
		cols.appendChild( tree );

		// Step 3 · Fill: the run, its progress, and (B.4) the questions.
		var fill = card( 'fill', __( 'Your tree', 'vergelabs-media-library' ) );
		dom.slots.fill = el( 'div', { class: 'g-tree-slot' } );
		fill.appendChild( dom.slots.fill );
		dom.fillMove = el( 'div', { class: 'g-move' } );
		dom.move = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary vgml-move-btn' } );
		dom.stop = el( 'button', { type: 'button', class: 'vgml-btn vgml-move-stop', hidden: 'hidden' }, __( 'Stop', 'vergelabs-media-library' ) );
		dom.undo = el( 'button', { type: 'button', class: 'g-quiet vgml-move-undo', hidden: 'hidden' } );
		dom.move.addEventListener( 'click', onMove );
		dom.stop.addEventListener( 'click', onStop );
		dom.undo.addEventListener( 'click', onUndo );
		fill.appendChild( dom.fillMove );
		cols.appendChild( fill );

		// The questions, to the tree's right at 1600 and under it below 1000px (the approved fill mock): one card a group.
		dom.qs = el( 'div', { class: 'g-qs', hidden: 'hidden' } );
		cols.appendChild( dom.qs );

		// Step 4 · Alt text (B.5 wires the route; the step is on the rail now).
		var alt = card( 'alt', __( 'Alt text from the descriptions', 'vergelabs-media-library' ) );
		alt.appendChild( el( 'p', { class: 'g-line' }, __( 'Never replaces one you have.', 'vergelabs-media-library' ) ) );
		dom.altMove = el( 'div', { class: 'g-move' } );
		alt.appendChild( dom.altMove );
		cols.appendChild( alt );

		// Step 5 · Rename: gated, on the rail, says so.
		var rename = card( 'rename', __( 'File names from the descriptions', 'vergelabs-media-library' ) );
		var not = el( 'span', { class: 'g-pill' } );
		not.appendChild( el( 'b', null, __( 'Not', 'vergelabs-media-library' ) ) );
		not.appendChild( document.createTextNode( ' ' + __( 'available yet', 'vergelabs-media-library' ) ) );
		dom.cards.rename.pills.appendChild( not );
		rename.appendChild( el( 'p', { class: 'g-line' }, __( 'Renames a file to what it shows and keeps every link to it working. Coming after the folders.', 'vergelabs-media-library' ) ) );
		var renameMove = el( 'div', { class: 'g-move' } );
		renameMove.appendChild( el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary', disabled: 'disabled' }, __( 'Rename files', 'vergelabs-media-library' ) ) );
		rename.appendChild( renameMove );
		cols.appendChild( rename );

		dom.tree = el( 'div', { class: 'vgml-folders-tree g-tree' } );
		root.appendChild( cols );

		if ( rail ) {
			Array.prototype.forEach.call( rail.querySelectorAll( '.g-step' ), function ( b ) {
				b.addEventListener( 'click', function () {
					setStep( b.getAttribute( 'data-step' ) );
				} );
			} );
		}
	}

	function renderRail() {
		if ( ! rail ) {
			return;
		}
		Array.prototype.forEach.call( rail.querySelectorAll( '.g-step' ), function ( b ) {
			var step = b.getAttribute( 'data-step' );
			b.classList.toggle( 'is-current', step === state.step );
			b.classList.toggle( 'is-done', stepDone( step ) );
			if ( step === state.step ) {
				b.setAttribute( 'aria-current', 'step' );
			} else {
				b.removeAttribute( 'aria-current' );
			}
		} );
	}

	function renderHead() {
		var f = state.facts;
		var head = root.parentNode.querySelector( '.vgml-folders-facts' );
		if ( ! head ) {
			return;
		}
		var folders = head.querySelector( '[data-fact="folders"] b' );
		if ( folders ) {
			folders.textContent = fmt( f.folders );
		}
	}

	function renderCards() {
		STEPS.forEach( function ( step ) {
			dom.cards[ step ].el.hidden = step !== state.step;
		} );
		renderRail();
		renderHead();
		renderDescribe();
		renderTreeStep();
		renderFill();
		renderAlt();
		root.setAttribute( 'data-step', state.step );
	}

	/* ------------------------------------------------- Step 1 · Describe */

	function renderDescribe() {
		var f = state.facts;
		var c = dom.cards.describe;
		var left = Number( f.not_described ) || 0;
		c.pills.innerHTML = '';
		c.pills.appendChild( pill( f.pictures || 0, __( 'described', 'vergelabs-media-library' ), 'accent' ) );
		if ( left ) {
			c.pills.appendChild( pill( left, __( 'not yet', 'vergelabs-media-library' ), 'ask' ) );
		}
		dom.describeMove.innerHTML = '';
		if ( left ) {
			// The run is the AI screen's; the step hands over to it with the cost said first (a credit a picture).
			/* translators: %s: pictures */
			dom.describeMove.appendChild( el( 'a', { class: 'vgml-btn vgml-btn-primary', href: cfg.aiUrl || '#' }, sprintf( _n( 'Describe %s picture', 'Describe %s pictures', left, 'vergelabs-media-library' ), fmt( left ) ) ) );
			dom.describeMove.appendChild( pill( left, _n( 'credit', 'credits', left, 'vergelabs-media-library' ) ) );
			dom.describeMove.appendChild( quiet( __( 'Skip', 'vergelabs-media-library' ), function () { setStep( 'tree' ); } ) );
		} else {
			var next = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary' }, __( 'Next: Tree', 'vergelabs-media-library' ) );
			next.addEventListener( 'click', function () { setStep( 'tree' ); } );
			dom.describeMove.appendChild( next );
		}
	}

	/* ---------------------------------------------------- Step 2 · Tree */

	/** Pictures the dry run places, and the ones it leaves: the two facts beside the folder count. */
	function fitCounts() {
		var fit = state.fit;
		if ( state.pastePending || ! fit || false === fit.counted ) {
			return null;
		}
		var why = fit.unfiled || {};
		var unfiled = ( Number( why.floor ) || 0 ) + ( Number( why.margin ) || 0 ) + ( Number( why.gated ) || 0 );
		return { placed: Math.max( 0, ( Number( fit.looked ) || 0 ) - unfiled ), unfiled: unfiled };
	}

	function renderTreeStep() {
		var c = dom.cards.tree;
		var s = view ? view.summary() : { now: 0, after: 0, changes: 0 };
		var folders = view && view.getDraft() ? s.after : s.now;
		c.pills.innerHTML = '';
		c.pills.appendChild( pill( folders, _n( 'folder', 'folders', folders, 'vergelabs-media-library' ), 'accent' ) );
		var counts = fitCounts();
		if ( counts && view && view.getDraft() ) {
			c.pills.appendChild( pill( counts.placed, __( 'placed', 'vergelabs-media-library' ), 'accent' ) );
			c.pills.appendChild( pill( counts.unfiled, __( 'stay unfiled', 'vergelabs-media-library' ) ) );
		} else if ( fitUnknown() ) {
			c.pills.appendChild( el( 'span', { class: 'g-pill is-quiet' }, __( 'counts not worked out yet', 'vergelabs-media-library' ) ) );
		}

		if ( 'tree' === state.step && dom.tree.parentNode !== dom.slots.tree ) {
			dom.slots.tree.appendChild( dom.tree );
		}
		syncCounted();

		dom.change.hidden = confirmed();
		renderChange();

		dom.treeMove.innerHTML = '';
		if ( confirmed() ) {
			var next = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary' }, __( 'Next: Fill', 'vergelabs-media-library' ) );
			next.addEventListener( 'click', function () { setStep( 'fill' ); } );
			dom.treeMove.appendChild( next );
			dom.treeMove.appendChild( quiet( __( 'Unconfirm', 'vergelabs-media-library' ), onUnconfirm ) );
			return;
		}
		var hasTree = folders > 0;
		if ( hasTree ) {
			dom.confirm = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary vgml-confirm-btn' }, __( 'This is my tree', 'vergelabs-media-library' ) );
			dom.confirm.addEventListener( 'click', onConfirm );
			dom.confirm.disabled = state.pastePending || running();
			dom.treeMove.appendChild( dom.confirm );
		}
		if ( state.prevProfiles > 0 ) {
			var restore = quiet( __( 'Restore the earlier classes', 'vergelabs-media-library' ), onRestoreProfiles );
			restore.classList.add( 'vgml-restore-profiles' );
			dom.treeMove.appendChild( restore );
		}
		if ( described && licensed && ! capped() ) {
			// Never automatic: a proposal is a planner call, and its cost is on the button.
			var propose = el( 'button', { type: 'button', class: 'vgml-btn vgml-propose-btn' + ( hasTree ? '' : ' vgml-btn-primary' ) }, __( 'Propose folders', 'vergelabs-media-library' ) );
			propose.disabled = ! canTalk() || talk.streaming();
			propose.addEventListener( 'click', onPropose );
			dom.treeMove.appendChild( propose );
			dom.treeMove.appendChild( pill( cfg.proposeCredits || 10, __( 'credits', 'vergelabs-media-library' ) ) );
		}
		dom.treeMove.appendChild( quiet( __( 'Skip', 'vergelabs-media-library' ), function () { setStep( 'fill' ); } ) );
	}

	function onConfirm() {
		if ( dom.confirm ) {
			dom.confirm.disabled = true;
		}
		if ( talk.streaming() ) {
			talk.stop();
		}
		// A paste still settling, or a draft edit not yet written: the confirm reads the session, so it waits its turn.
		queue = queue.then( function () {
			return api( 'POST', 'guide/confirm' ).then( function ( r ) {
				tookSession( r );
				setStep( 'fill' );
			} ).catch( function ( err ) {
				talk.note( ( err && err.message ) || __( 'That did not go through.', 'vergelabs-media-library' ) );
				renderCards();
			} );
		}, function () {} );
	}

	function onUnconfirm() {
		api( 'POST', 'guide/unconfirm' ).then( function ( r ) {
			tookSession( r );
			// Folders whose classes the confirm replaced within the day: Restore is offered beside the confirm (C.4).
			state.prevProfiles = ( r && Number( r.prev ) ) || 0;
			renderCards();
		} ).catch( function ( err ) {
			talk.note( ( err && err.message ) || __( 'That did not go through.', 'vergelabs-media-library' ) );
		} );
	}

	/** The earlier classes back on every folder a confirm re-profiled: the tree re-read with them, the draft carrying them, the fit dropped. */
	function onRestoreProfiles() {
		api( 'POST', 'guide/profiles-restore' ).then( function ( r ) {
			state.prevProfiles = ( r && Number( r.restored ) ) || 0;
			if ( r && r.nodes ) {
				state.nodes = r.nodes;
				view.setTree( state.nodes );
			}
			tookSession( r );
			renderCards();
		} ).catch( function ( err ) {
			talk.note( ( err && err.message ) || __( 'That did not go through.', 'vergelabs-media-library' ) );
		} );
	}

	/** The session as a route hands it back whole: the draft it holds, the tree's state, the fit. */
	function tookSession( r ) {
		if ( ! r || ! r.session ) {
			return;
		}
		state.session = r.session;
		talk.setSession( state.session );
		if ( r.version ) {
			state.version = r.version;
			if ( watcher ) {
				watcher.known( r.version );
			}
		}
		state.fit = null;
		view.setDraft( state.session.draft || null );
		state.fit = state.session.fit || null;
		view.editable = ! confirmed();
		view.render();
	}

	function onPropose() {
		if ( ! canTalk() || talk.streaming() ) {
			return;
		}
		turn_( { open: true }, null );
		renderTreeStep();
	}

	/* ---------------------------------------------------- Step 3 · Fill */

	/*
	 *  Step 3 in its states (the approved fill mock, 2026-09-15-fill-questions.html):
	 *
	 *    running  the counts climb on the rows (view.setProgress) and in the
	 *             pill row -- placed · sure · likely · to sort from the run's
	 *             own tally; no question until it ends.
	 *    asking   the pill row with the questions' count in yellow; the cards
	 *             to the tree's right, three at a time, Leave the rest under
	 *             them.
	 *    done     no open question, 0 in no folder: "N in folders · 0 to
	 *             sort", the parents closed, what the answers made marked new,
	 *             Next: Alt text, Undo.
	 *    else     the run's button (B.2), or the confirm when the tree is not.
	 */
	function renderFill() {
		var c = dom.cards.fill;
		c.pills.innerHTML = '';
		var tally = state.fit && state.fit.tally;
		var open = openQuestions().length;
		var asking = ! running() && open > 0;
		var done = fillDone();
		var unfiled = Number( state.fill.unfiled ) || 0;

		if ( running() ) {
			var r = state.moving || {};
			var t = r.tally || {};
			c.pills.appendChild( pill( Number( r.moved ) || 0, __( 'placed', 'vergelabs-media-library' ), 'accent' ) );
			c.pills.appendChild( pill( Number( t.sure ) || 0, __( 'sure', 'vergelabs-media-library' ) ) );
			c.pills.appendChild( pill( Number( t.likely ) || 0, __( 'likely', 'vergelabs-media-library' ) ) );
			c.pills.appendChild( pill( Number( t.nothing ) || 0, __( 'to sort', 'vergelabs-media-library' ) ) );
		} else if ( asking && state.moving ) {
			// The run just ended: its tally, and the questions it left.
			var m = state.moving.tally || {};
			c.pills.appendChild( pill( Number( state.moving.moved ) || 0, __( 'placed', 'vergelabs-media-library' ), 'accent' ) );
			c.pills.appendChild( pill( Number( m.sure ) || 0, __( 'sure', 'vergelabs-media-library' ) ) );
			c.pills.appendChild( pill( Number( m.likely ) || 0, __( 'likely', 'vergelabs-media-library' ) ) );
			c.pills.appendChild( pill( open, _n( 'question', 'questions', open, 'vergelabs-media-library' ), 'ask' ) );
			c.pills.appendChild( pill( unfiled, __( 'to sort', 'vergelabs-media-library' ) ) );
		} else if ( asking ) {
			// A reload after the run: the library's own counts, and the questions.
			c.pills.appendChild( pill( Math.max( 0, ( Number( state.facts.pictures ) || 0 ) - unfiled ), __( 'placed', 'vergelabs-media-library' ), 'accent' ) );
			c.pills.appendChild( pill( open, _n( 'question', 'questions', open, 'vergelabs-media-library' ), 'ask' ) );
			c.pills.appendChild( pill( unfiled, __( 'to sort', 'vergelabs-media-library' ) ) );
		} else if ( tally && view.getDraft() && ! done ) {
			// The dry run's answer about the confirmed tree, as the run will count it.
			c.pills.appendChild( pill( ( Number( tally.fits ) || 0 ) + ( Number( tally.siblings ) || 0 ), __( 'placed', 'vergelabs-media-library' ), 'accent' ) );
			c.pills.appendChild( pill( Number( tally.sure ) || 0, __( 'sure', 'vergelabs-media-library' ) ) );
			c.pills.appendChild( pill( Number( tally.likely ) || 0, __( 'likely', 'vergelabs-media-library' ) ) );
			c.pills.appendChild( pill( Number( tally.nothing ) || 0, __( 'to sort', 'vergelabs-media-library' ) ) );
		} else {
			var inFolders = Math.max( 0, ( Number( state.facts.pictures ) || 0 ) - unfiled );
			c.pills.appendChild( pill( inFolders, __( 'in folders', 'vergelabs-media-library' ), 'accent' ) );
			c.pills.appendChild( pill( unfiled, __( 'to sort', 'vergelabs-media-library' ), 'accent' ) );
		}

		// Moved into this step's slot once: re-appending the node it is already in restarts every row's entering animation.
		if ( 'fill' === state.step && dom.tree.parentNode !== dom.slots.fill ) {
			dom.slots.fill.appendChild( dom.tree );
		}

		// The grid with the questions beside the tree; the tighter rows of a done tree.
		dom.cols.classList.toggle( 'is-asking', asking && 'fill' === state.step );
		dom.cols.classList.toggle( 'is-done', done && 'fill' === state.step );
		dom.qs.hidden = ! ( asking && 'fill' === state.step );
		renderQuestions();

		// The parents open only while the fill paints its bars (every row landed of total, the approved running mock); closed otherwise.
		var openAll = running() && 'fill' === state.step;
		if ( view && view.openAll !== openAll ) {
			view.openAll = openAll;
			view.openOverride = {};
			view.render();
		}
		// What the answers made reads "new" on this step, as the answered card says it was made.
		if ( view ) {
			view.setNewIds( 'fill' === state.step ? state.made : [] );
		}

		dom.fillMove.innerHTML = '';
		if ( done ) {
			var next = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary' }, __( 'Next: Alt text', 'vergelabs-media-library' ) );
			next.addEventListener( 'click', function () { setStep( 'alt' ); } );
			dom.fillMove.appendChild( next );
			dom.fillMove.appendChild( dom.undo );
			syncCounted();
			root.setAttribute( 'data-state', 'done' );
			renderUndo();
			return;
		}
		if ( asking ) {
			// The answers are the step now; Undo covers the run and every answer.
			dom.fillMove.appendChild( dom.undo );
			syncCounted();
			root.setAttribute( 'data-state', 'asking' );
			renderUndo();
			return;
		}
		if ( ! confirmed() && ! running() ) {
			// Skipped here with the tree unconfirmed: the fill runs against a confirmed tree and nothing else, so that is the one button.
			var confirmBtn = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary vgml-confirm-btn' }, __( 'This is my tree', 'vergelabs-media-library' ) );
			confirmBtn.disabled = ! ( view.getDraft() || state.nodes.length ) || state.pastePending;
			confirmBtn.addEventListener( 'click', onConfirm );
			dom.fillMove.appendChild( confirmBtn );
			dom.fillMove.appendChild( quiet( __( 'Back to the tree', 'vergelabs-media-library' ), function () { setStep( 'tree' ); } ) );
			dom.fillMove.appendChild( dom.undo );
			renderMove();
			return;
		}
		dom.fillMove.appendChild( dom.move );
		dom.fillMove.appendChild( dom.stop );
		dom.fillMove.appendChild( dom.undo );
		if ( confirmed() && ! running() ) {
			dom.fillMove.appendChild( quiet( __( 'Unconfirm', 'vergelabs-media-library' ), onUnconfirm ) );
		}
		renderMove();
	}

	/* ------------------------------------------------- the questions */

	/*
	 *  One card a group (the approved mock): the engine's sentence, the group's
	 *  pictures -- eight at most -- and the answers as buttons, the engine's own
	 *  first and tinted. Three cards at a time; the rest follow as these are
	 *  answered, so no card is ever below the fold. The card just answered
	 *  stays, with its result line, until the next answer.
	 */
	function nodeName( id ) {
		var name = '';
		state.nodes.forEach( function ( n ) {
			if ( n.id === Number( id ) ) {
				name = n.name;
			}
		} );
		return name;
	}

	function resultLine( q ) {
		var r = q.result || {};
		var moved = fmt( Number( r.moved ) || 0 );
		if ( 'new-folder' === q.answered ) {
			/* translators: 1: the folder made, 2: pictures moved into it */
			return sprintf( __( '%1$s made · %2$s moved', 'vergelabs-media-library' ), nodeName( r.made ) || q.name, moved );
		}
		if ( 'keep-parent' === q.answered ) {
			/* translators: %s: the parent folder */
			return sprintf( __( 'Kept in %s', 'vergelabs-media-library' ), nodeName( q.term_id ) );
		}
		if ( 'split' === q.answered ) {
			/* translators: %s: pictures */
			return sprintf( __( '%s moved by best score', 'vergelabs-media-library' ), moved );
		}
		/* translators: 1: pictures, 2: the folder they went to */
		return sprintf( __( '%1$s moved to %2$s', 'vergelabs-media-library' ), moved, nodeName( r.term_id ) || __( 'To sort', 'vergelabs-media-library' ) );
	}

	/** The line under an answered card: what the answer did, and -- when the person chose the folder -- the way to review those pictures. */
	function resultNode( q ) {
		var p = el( 'p', { class: 'g-q-result' }, resultLine( q ) );
		var placed = Number( q.result && q.result.placed ) || 0;
		if ( placed > 0 ) {
			p.appendChild( document.createTextNode( ' · ' ) );
			var url = ( cfg.libraryUrl || 'upload.php' ) + '?mode=list&' + encodeURIComponent( cfg.taxonomy || 'media_category' ) + '=by_you';
			/* translators: %s: pictures the person put in a folder themselves */
			p.appendChild( el( 'a', { class: 'g-q-review', href: url }, sprintf( __( '%s by you · review', 'vergelabs-media-library' ), fmt( placed ) ) ) );
		}
		return p;
	}

	/** The strip under the sentence: eight thumbnails, or -- opened by "Show me" -- every picture, each a way to its own modal, and a count for what the cap of 48 left out. */
	function stripNode( q ) {
		var shown = state.showing[ q.id ];
		var strip = el( 'div', { class: 'g-q-strip' + ( shown ? ' is-open' : '' ) } );
		( shown || q.sample || [] ).forEach( function ( s ) {
			if ( ! s.thumb ) {
				return;
			}
			var img = el( 'img', { src: s.thumb, alt: '', loading: 'lazy' } );
			if ( shown ) {
				var a = el( 'a', { href: ( cfg.libraryUrl || 'upload.php' ) + '?item=' + s.id } );
				a.appendChild( img );
				strip.appendChild( a );
			} else {
				strip.appendChild( img );
			}
		} );
		if ( shown && Number( q.count ) > shown.length ) {
			var rest = pill( Number( q.count ) - shown.length, __( 'more', 'vergelabs-media-library' ), 'quiet' );
			rest.classList.add( 'g-q-strip-more' );
			strip.appendChild( rest );
		}
		return strip;
	}

	/*
	 *  The card's lower half, in place: the answers while it is open, the
	 *  result line once answered, the error line when the answer did not go
	 *  through -- on the card, never in the hidden change line (S6b, seam 3).
	 *  The card node itself is kept: an answer patches it, never rebuilds it.
	 */
	function patchCard( c, q ) {
		c.classList.toggle( 'is-answered', !! q.answered );
		var old = c.querySelector( '.g-q-strip' );
		var shown = state.showing[ q.id ] ? '1' : '';
		if ( ! old || c.getAttribute( 'data-shown' ) !== shown ) {
			// The thumbnails are left alone unless the strip opened: a result line does not reload eight pictures.
			var strip = stripNode( q );
			if ( old ) {
				c.replaceChild( strip, old );
			} else {
				c.appendChild( strip );
			}
			c.setAttribute( 'data-shown', shown );
		}
		Array.prototype.forEach.call( c.querySelectorAll( '.g-q-answers, .g-q-result, .g-q-error' ), function ( n ) {
			c.removeChild( n );
		} );
		if ( q.answered ) {
			c.appendChild( resultNode( q ) );
			return c;
		}
		var answers = el( 'div', { class: 'g-q-answers' } );
		var first = true;
		Object.keys( q.answers || {} ).forEach( function ( key ) {
			var b = el( 'button', { type: 'button', class: 'g-answer' + ( first ? ' is-first' : '' ), 'data-answer': key }, q.answers[ key ] );
			first = false;
			b.disabled = state.answering;
			b.addEventListener( 'click', function () { onAnswer( q, key ); } );
			answers.appendChild( b );
		} );
		c.appendChild( answers );
		if ( q.error ) {
			c.appendChild( el( 'p', { class: 'g-q-error', role: 'alert' }, q.error ) );
		}
		return c;
	}

	function questionCard( q ) {
		var c = el( 'div', { class: 'g-card g-q', 'data-q': q.id, 'data-kind': q.kind, tabindex: '0' } );
		c.appendChild( el( 'p', { class: 'g-q-text' }, q.text ) );
		// From the keyboard, with the card focused: 1-4 press its answers in order, Enter opens the strip.
		c.addEventListener( 'keydown', function ( e ) {
			if ( e.target !== c || q.answered || state.answering ) {
				return;
			}
			var keys = Object.keys( q.answers || {} );
			var n = parseInt( e.key, 10 );
			if ( n >= 1 && n <= 4 && keys[ n - 1 ] ) {
				e.preventDefault();
				onAnswer( q, keys[ n - 1 ] );
			} else if ( 'Enter' === e.key && keys.indexOf( 'show-me' ) >= 0 && ! state.showing[ q.id ] ) {
				e.preventDefault();
				onAnswer( q, 'show-me' );
			}
		} );
		return patchCard( c, q );
	}

	/*
	 *  The grid is reconciled, not rebuilt: the card just answered stays the
	 *  same node with its result line, the next card is appended, a card that
	 *  scrolled out of the three is removed. Rebuilding every card on every
	 *  answer lost the focus and made the next card pop (S6b, seam 3).
	 */
	function renderQuestions() {
		if ( dom.qs.hidden ) {
			dom.qs.innerHTML = '';
			return;
		}
		var open = openQuestions();
		var last = null;
		state.questions.forEach( function ( q ) {
			if ( q.id === state.lastAnswered && q.answered ) {
				last = q;
			}
		} );
		var room = last ? 2 : 3;
		var want = ( last ? [ last ] : [] ).concat( open.slice( 0, room ) );

		var have = {};
		Array.prototype.forEach.call( dom.qs.querySelectorAll( '.g-q' ), function ( c ) {
			have[ c.getAttribute( 'data-q' ) ] = c;
		} );
		var foot = dom.qs.querySelector( '.g-qs-foot' );
		if ( ! foot ) {
			foot = el( 'div', { class: 'g-pills g-qs-foot' } );
			dom.qs.appendChild( foot );
		}

		var keep = {};
		var before = dom.qs.firstChild;
		want.forEach( function ( q ) {
			var c = have[ q.id ];
			if ( c ) {
				var was = c.getAttribute( 'data-answered' ) || '';
				var now = ( q.answered || '' ) + '|' + ( q.error || '' ) + '|' + ( state.showing[ q.id ] ? 'shown' : '' ) + '|' + ( state.answering ? 'busy' : '' );
				if ( was !== now ) {
					patchCard( c, q );
					c.setAttribute( 'data-answered', now );
				}
			} else {
				c = questionCard( q );
				c.setAttribute( 'data-answered', ( q.answered || '' ) + '|' + ( q.error || '' ) + '||' + ( state.answering ? 'busy' : '' ) );
			}
			keep[ q.id ] = true;
			if ( c !== before ) {
				dom.qs.insertBefore( c, before );
			} else {
				before = c.nextSibling;
			}
		} );
		Object.keys( have ).forEach( function ( id ) {
			if ( ! keep[ id ] ) {
				dom.qs.removeChild( have[ id ] );
			}
		} );

		foot.innerHTML = '';
		var more = open.length - Math.min( open.length, room );
		if ( more > 0 ) {
			foot.appendChild( pill( more, __( 'more', 'vergelabs-media-library' ), 'quiet' ) );
		}
		var leave = quiet( __( 'Leave the rest', 'vergelabs-media-library' ), onLeaveRest );
		leave.classList.add( 'g-leave-rest' );
		leave.disabled = state.answering;
		foot.appendChild( leave );
		if ( foot !== dom.qs.lastChild ) {
			dom.qs.appendChild( foot );
		}
	}

	/** What an answer route hands back: the fill's state, the folders made, undo, the version. The questions are the screen's own. */
	function tookAnswer( r ) {
		if ( ! r ) {
			return;
		}
		state.fill = r.status || state.fill;
		state.made = r.made || [];
		state.undo = r.undo || state.undo;
		if ( r.version ) {
			state.version = r.version;
			if ( watcher ) {
				watcher.known( r.version );
			}
		}
	}

	/** The buttons while an answer is in flight, without touching the cards. */
	function setAnswering( busy ) {
		state.answering = busy;
		Array.prototype.forEach.call( dom.qs.querySelectorAll( '.g-answer, .g-leave-rest' ), function ( b ) {
			b.disabled = busy;
		} );
	}

	function onAnswer( q, key ) {
		if ( state.answering ) {
			return;
		}
		q.error = '';
		setAnswering( true );
		api( 'POST', 'guide/answer', { id: q.id, answer: key } ).then( function ( r ) {
			setAnswering( false );
			if ( 'show-me' === key ) {
				// Answers nothing: the card opens on the group's pictures.
				state.showing[ q.id ] = ( r && r.result && r.result.show ) || [];
				renderQuestions();
				return;
			}
			q.answered = key;
			q.result = ( r && r.result ) || {};
			state.lastAnswered = q.id;
			tookAnswer( r );
			renderQuestions();
			// The tree gains the folder the answer made, and the counts it moved: one read, one render.
			return refreshTree();
		} ).catch( function ( err ) {
			setAnswering( false );
			q.error = ( err && err.message ) || __( 'That did not go through.', 'vergelabs-media-library' );
			renderQuestions();
		} );
	}

	/** Every open question answered with leave: the pictures land in To sort, never in nothing. */
	function onLeaveRest() {
		if ( state.answering ) {
			return;
		}
		setAnswering( true );
		state.lastAnswered = '';
		api( 'POST', 'guide/answer', { id: 'rest', answer: 'leave' } ).then( function ( r ) {
			setAnswering( false );
			// As the route answered them: keep-parent for a sibling question, leave for the rest.
			openQuestions().forEach( function ( q ) {
				q.answered = 'siblings' === q.kind ? 'keep-parent' : 'leave';
				q.result = { moved: q.count, term_id: 0, made: 0, placed: 0 };
			} );
			tookAnswer( r );
			return refreshTree();
		} ).catch( function ( err ) {
			setAnswering( false );
			var first = openQuestions()[ 0 ];
			if ( first ) {
				first.error = ( err && err.message ) || __( 'That did not go through.', 'vergelabs-media-library' );
			}
			renderQuestions();
		} );
	}

	/** The questions and the fill's state, read once the run has ended or the page opened on them. */
	function loadQuestions() {
		return api( 'GET', 'guide/questions' ).then( function ( q ) {
			state.questions = ( q && q.questions ) || [];
			state.made = ( q && q.made ) || [];
			if ( q && q.status ) {
				state.fill = q.status;
			}
		} ).catch( function () {} );
	}

	/* ------------------------------------------------ Step 4 · Alt text */

	/*
	 *  Step 4: the catalogue's alt onto every picture that has none, and never
	 *  onto one that has (vergeml_ai_alt_pending: the file's alt is empty). The
	 *  pill is every picture without alt text; the button is the ones the
	 *  catalogue can fill -- the rest are not described yet, which is Step 1's.
	 *  No credit: the alt was written with the description.
	 */
	function renderAlt() {
		var f = state.facts;
		var c = dom.cards.alt;
		var missing = Number( f.alt_missing ) || 0;
		var pending = Number( f.alt_pending ) || 0;
		c.pills.innerHTML = '';
		if ( missing ) {
			c.pills.appendChild( pill( missing, __( 'without alt text', 'vergelabs-media-library' ), 'ask' ) );
		}
		c.pills.appendChild( pill( Number( f.alt_have ) || 0, __( 'have one', 'vergelabs-media-library' ) ) );
		dom.altMove.innerHTML = '';
		if ( state.altWriting ) {
			/* translators: %s: pictures still to write */
			var busy = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary vgml-alt-btn', disabled: 'disabled' }, sprintf( __( 'Writing · %s left', 'vergelabs-media-library' ), fmt( state.altLeft ) ) );
			dom.altMove.appendChild( busy );
			return;
		}
		if ( pending ) {
			/* translators: %s: pictures */
			var write = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary vgml-alt-btn' }, sprintf( __( 'Write alt text for %s', 'vergelabs-media-library' ), fmt( pending ) ) );
			write.addEventListener( 'click', onAlt );
			dom.altMove.appendChild( write );
			dom.altMove.appendChild( pill( 0, __( 'credits', 'vergelabs-media-library' ) ) );
			dom.altMove.appendChild( quiet( __( 'Skip', 'vergelabs-media-library' ), function () { setStep( 'rename' ); } ) );
		} else {
			var next = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary' }, __( 'Next: Rename', 'vergelabs-media-library' ) );
			next.addEventListener( 'click', function () { setStep( 'rename' ); } );
			dom.altMove.appendChild( next );
			if ( missing ) {
				// Without a description there is no alt to copy: the pictures left are Step 1's.
				dom.altMove.appendChild( quiet( __( 'Describe the rest first', 'vergelabs-media-library' ), function () { setStep( 'describe' ); } ) );
			}
		}
	}

	/** Two hundred a request until none is left; every write is a copy from the catalogue, none a model call. */
	function onAlt() {
		if ( state.altWriting ) {
			return;
		}
		state.altWriting = true;
		state.altLeft = Number( state.facts.alt_pending ) || 0;
		renderAlt();
		var wrote = 0;
		function step() {
			var before = state.altLeft;
			return api( 'POST', 'ai-alt', { limit: 200 } ).then( function ( r ) {
				wrote += Number( r.wrote ) || 0;
				state.altLeft = Number( r.remaining ) || 0;
				renderAlt();
				// On while every request brings the number down; a write that leaves it where it was is not repeated.
				if ( state.altLeft > 0 && ( Number( r.wrote ) || 0 ) > 0 && state.altLeft < before ) {
					return step();
				}
			} );
		}
		step().then( function () {
			state.altWriting = false;
			state.facts.alt_pending = state.altLeft;
			state.facts.alt_missing = Math.max( 0, ( Number( state.facts.alt_missing ) || 0 ) - wrote );
			state.facts.alt_have = ( Number( state.facts.alt_have ) || 0 ) + wrote;
			renderCards();
		} ).catch( function ( err ) {
			state.altWriting = false;
			renderCards();
			dom.altMove.appendChild( el( 'span', { class: 'g-pill is-quiet' }, ( err && err.message ) || __( 'That did not go through.', 'vergelabs-media-library' ) ) );
		} );
	}

	/* ------------------------------------------------------------ the tree */

	var view = null;
	var watcher = null;

	function treeL10n() {
		return {
			changes: __( 'Changes', 'vergelabs-media-library' ),
			all: __( 'All', 'vergelabs-media-library' ),
			states: __( 'Which folders to show', 'vergelabs-media-library' ),
			find: __( 'Find a folder', 'vergelabs-media-library' ),
			topLevel: __( 'Top level', 'vergelabs-media-library' ),
			newTag: __( 'new', 'vergelabs-media-library' ),
			/* translators: %s: a word the folder takes ("semiconductor component") */
			removeWord: __( 'Remove the word %s', 'vergelabs-media-library' ),
			/* translators: %s: a number of pictures */
			was: __( 'was %s', 'vergelabs-media-library' ),
			/* translators: 1: pictures, 2: a folder name */
			removedTo: __( 'removed · %1$s pictures go to %2$s', 'vergelabs-media-library' ),
			/* translators: %s: pictures */
			removedNowhere: __( 'removed · %s pictures go to no folder', 'vergelabs-media-library' ),
			/* translators: %s: a folder name */
			movedFrom: __( 'moved from %s', 'vergelabs-media-library' ),
			movedFromTop: __( 'moved from the top level', 'vergelabs-media-library' ),
			byYou: __( ', by you', 'vergelabs-media-library' ),
			/* translators: %s: the old name */
			renamedFrom: __( 'renamed from %s', 'vergelabs-media-library' ),
			folder1: __( '1 folder', 'vergelabs-media-library' ),
			/* translators: %s: folders */
			folderN: __( '%s folders', 'vergelabs-media-library' ),
			change1: __( '1 change', 'vergelabs-media-library' ),
			/* translators: %s: changes */
			changeN: __( '%s changes', 'vergelabs-media-library' ),
			more1: __( '1 more folder, unchanged', 'vergelabs-media-library' ),
			/* translators: %s: folders */
			moreN: __( '%s more folders, unchanged', 'vergelabs-media-library' ),
			/* translators: %s: pictures */
			afterMove: __( '%s pictures after the fill', 'vergelabs-media-library' ),
			/* translators: 1: pictures, 2: folder names */
			fromFolders: __( '%1$s from %2$s', 'vergelabs-media-library' ),
			/* translators: %s: pictures */
			ofN: __( 'of %s', 'vergelabs-media-library' ),
			noChanges: __( 'No changes yet', 'vergelabs-media-library' ),
			nothingFound: __( 'No folder matches', 'vergelabs-media-library' ),
			rename: __( 'Rename', 'vergelabs-media-library' ),
			remove: __( 'Remove from the draft', 'vergelabs-media-library' ),
			/* translators: %s: a folder name */
			addIn: __( 'Add a folder inside %s', 'vergelabs-media-library' ),
			addTop: __( 'New folder', 'vergelabs-media-library' ),
			addName: __( 'Name, or Solar > Rooftop', 'vergelabs-media-library' ),
			/* translators: %s: a folder name */
			removeOne: __( 'Remove %s from the draft', 'vergelabs-media-library' )
		};
	}

	function makeTree() {
		view = TV.create( {
			surface: 'folders',
			root: dom.tree,
			nodes: state.nodes,
			indent: { step: 22, base: 0 },
			editable: ! confirmed(),
			siblings: true,
			// Parents closed, nothing folded (Nathan, 2026-09-15: a tree of 300 reads by its parents): a closed parent carries
			// its folder count; a parent the draft changed under opens by itself; a small open parent's children are chips.
			openAll: false,
			fold: false,
			l10n: treeL10n(),
			onEdit: onHandEdit
		} );
		// One list, the whole tree: the mock has no Changes / All switch.
		view.setMode( 'all', true );
		if ( state.session.draft ) {
			view.setDraft( state.session.draft );
		} else {
			view.render();
		}
		watcher = TV.watchVersion( {
			path: '/' + ( cfg.ns || 'vergeml/v1' ) + '/folders/version',
			version: state.version,
			onChange: function ( v ) {
				state.version = v;
				// While a run goes the folders are half-made; the tree is re-read
				// once, when it ends. That includes the seconds between the press
				// and the answer: on 2026-09-14 the folders being made bumped the
				// version, this re-read redrew the button as "Move 1,000 pictures",
				// enabled, while the request was still in flight.
				if ( running() ) {
					return;
				}
				refreshTree();
			}
		} );
	}

	function refreshTree() {
		return api( 'GET', 'tree?taxonomy=' + encodeURIComponent( cfg.taxonomy || 'media_category' ) ).then( function ( r ) {
			state.nodes = ( r && r.nodes ) || [];
			state.facts.folders = state.nodes.length;
			// The folders the answers made are marked before the tree is set, so the tree renders once, not twice (S6b, seam 3).
			view.setNewIds( 'fill' === state.step ? state.made : [], true );
			view.setTree( state.nodes );
			var draft = view.getDraft();
			if ( draft && state.session.draft && ! confirmed() ) {
				state.session.draft = withOrigin( draft, state.session.draft );
				persistDraft( state.session.draft );
			}
			renderCards();
		} ).catch( function () {} );
	}

	/** The component's draft plus what the session remembers about where it came from. */
	function withOrigin( draft, prev ) {
		return { folders: draft.folders, gone: draft.gone, tags: ( prev && prev.tags ) || [], origin: ( prev && prev.origin ) || 'talk', rule: ( prev && prev.rule ) || null };
	}

	var persistTimer = null;
	function persistDraft( draft ) {
		state.session.draft = draft;
		window.clearTimeout( persistTimer );
		persistTimer = window.setTimeout( function () {
			persist( 'guide/session', { draft: draft } );
		}, 250 );
	}

	function setDraft( draft, quietly ) {
		/*
		 *  A different draft, so the dry run's answer is about a tree that is
		 *  no longer on screen. Dropped rather than shown against the new one:
		 *  a paste's draft would otherwise wear the conversation's number for as
		 *  long as the round trip takes, and the button with it.
		 */
		state.fit = null;
		state.session.draft = draft;
		view.setDraft( draft );
		renderCards();
		if ( ! quietly ) {
			persistDraft( draft );
		}
	}

	/* ----------------------------------------------- the model's tree, keyed */

	/*
	 *  The model speaks names; the draft is keyed by term id. A folder that
	 *  carries the id it was given is that folder; one that lost its id is
	 *  matched by its exact path; what is left is new. A live folder the
	 *  reply does not name is gone, its pictures going to the nearest kept
	 *  ancestor -- the parent that absorbed it -- or to no folder.
	 */
	function resolveTree( tree ) {
		var live = new TV.Model( state.nodes );
		var byPath = {};
		state.nodes.forEach( function ( n ) {
			byPath[ live.pathNames( n.id ).map( lower ).join( '/' ) ] = n.id;
		} );
		var folders = ( tree && tree.folders ) || [];
		var prev = state.session.draft && state.session.draft.folders ? state.session.draft.folders : [];
		var byName = {};
		folders.forEach( function ( f, i ) {
			var n = lower( f.name );
			if ( byName[ n ] === undefined ) {
				byName[ n ] = i;
			}
		} );
		function pathOf( i ) {
			var names = [];
			var guard = 0;
			var at = i;
			while ( at !== undefined && guard++ < 64 ) {
				names.unshift( lower( folders[ at ].name ) );
				var p = lower( folders[ at ].parent );
				at = p && byName[ p ] !== undefined && byName[ p ] !== at ? byName[ p ] : undefined;
			}
			return names.join( '/' );
		}
		var used = {};
		var keys = [];
		folders.forEach( function ( f, i ) {
			var id = Number( f.id ) || 0;
			if ( ! id || ! live.byId[ id ] || used[ id ] ) {
				id = 0;
				var path = pathOf( i );
				if ( byPath[ path ] && ! used[ byPath[ path ] ] ) {
					id = byPath[ path ];
				}
			}
			if ( id ) {
				used[ id ] = true;
			}
			var key = id ? 't' + id : '';
			if ( ! key ) {
				prev.forEach( function ( g ) {
					if ( ! key && ! g.term_id && lower( g.name ) === lower( f.name ) ) {
						key = g.key;
					}
				} );
			}
			keys[ i ] = { key: key || ( 'n' + Date.now().toString( 36 ) + i ), term_id: id || null };
		} );
		var out = { folders: [], gone: {}, tags: ( tree && tree.tags ) || [], origin: 'talk', rule: null };
		folders.forEach( function ( f, i ) {
			var p = lower( f.parent );
			var pi = p && byName[ p ] !== undefined && byName[ p ] !== i ? byName[ p ] : -1;
			out.folders.push( {
				key: keys[ i ].key,
				term_id: keys[ i ].term_id,
				name: String( f.name || '' ).replace( /\//g, '-' ),
				parent: pi >= 0 ? keys[ pi ].key : '',
				/*
				 *  The reply carries a "count" and it is dropped here.
				 *
				 *  It was the model's estimate and nothing else -- the tree
				 *  shape asks a text model for a number and it answers with
				 *  arithmetic nothing performed. On the box on 4 September
				 *  2026 that read "Illustrations (23), Screenshots (6)" for a
				 *  library nobody had counted. Null means "unchanged" to the
				 *  tree, so a folder reads what it holds today until the turn
				 *  route answers with what vergeml_filing_pick() says.
				 */
				count: null,
				matches: f.matches || '',
				classes: f.classes || [],
				kinds: f.kinds || [],
				audience: f.audience || '',
				by: ''
			} );
		} );
		state.nodes.forEach( function ( n ) {
			if ( used[ n.id ] ) {
				return;
			}
			var at = n.parent;
			var guard = 0;
			while ( at && ! used[ at ] && live.byId[ at ] && guard++ < 64 ) {
				at = live.byId[ at ].parent;
			}
			out.gone[ n.id ] = at && used[ at ] ? 't' + at : '';
		} );
		return out;
	}

	/** The draft as the service takes it: names, parent names, and the id of every folder that exists. */
	function treeForService() {
		var draft = view.getDraft() || TV.fromLive( state.nodes );
		var byKey = {};
		draft.folders.forEach( function ( f ) { byKey[ f.key ] = f; } );
		return {
			folders: draft.folders.map( function ( f ) {
				var out = {
					name: f.name,
					parent: f.parent && byKey[ f.parent ] ? byKey[ f.parent ].name : '',
					matches: f.matches || '',
					classes: f.classes || [],
					kinds: f.kinds || [],
					audience: f.audience || ''
				};
				if ( f.term_id ) {
					out.id = f.term_id;
				}
				if ( f.count !== undefined && f.count !== null ) {
					out.count = f.count;
				}
				return out;
			} ),
			tags: ( state.session.draft && state.session.draft.tags ) || []
		};
	}

	/* ---------------------------------------- the change line: one input */

	/*
	 *  js/vergeml-talk.js draws the composer and streams a turn from the
	 *  service. This side hands it the token, the request body with the tree,
	 *  and takes the tree block back as the draft. Its log of turns is in the
	 *  page but only the last message shows (css): one line, with its chips.
	 */
	var talk = null;
	var talkOpts_ = null;
	var streamTree = null;

	/** The note under the input, when nothing can be said yet. */
	function leadNote() {
		if ( ! described ) {
			var none = el( 'div', { class: 'vgml-msg is-note' } );
			var facts = el( 'ul', { class: 'vgml-facts' } );
			facts.appendChild( el( 'li', null, __( 'Folders are worked out from the descriptions', 'vergelabs-media-library' ) ) );
			none.appendChild( facts );
			none.appendChild( el( 'a', { class: 'vgml-btn', href: cfg.aiUrl || '#' }, __( 'Describe the pictures', 'vergelabs-media-library' ) ) );
			return none;
		}
		if ( ! licensed ) {
			var nol = el( 'div', { class: 'vgml-msg is-note' } );
			var l = el( 'ul', { class: 'vgml-facts' } );
			l.appendChild( el( 'li', null, __( 'The conversation needs a licence', 'vergelabs-media-library' ) ) );
			nol.appendChild( l );
			nol.appendChild( el( 'a', { class: 'vgml-btn', href: cfg.licenceUrl || '#' }, __( 'Connect a licence', 'vergelabs-media-library' ) ) );
			return nol;
		}
		return null;
	}

	/*
	 *  The placeholder is built from the tree on screen, never fixed text:
	 *  the largest parent's name ("split Hardware by brand") and, when the dry
	 *  run left a group it could not place, its class word ("add 3d printers").
	 *  A tree with no parents names its largest folder; no tree at all asks
	 *  for one.
	 */
	function placeholder() {
		var o = view && view.overlay;
		var largest = null;
		var largestAny = null;
		if ( o ) {
			o.order.forEach( function ( key ) {
				var row = o.rows[ key ];
				if ( 'removed' === row.status ) {
					return;
				}
				var n = Number( row.after ) || 0;
				if ( ! largestAny || n > largestAny.n ) {
					largestAny = { name: row.name, n: n };
				}
				if ( ( o.children[ key ] || [] ).length && ( ! largest || n > largest.n ) ) {
					largest = { name: row.name, n: n };
				}
			} );
		} else if ( view ) {
			state.nodes.forEach( function ( n ) {
				var c = Number( n.count ) || 0;
				if ( ! largestAny || c > largestAny.n ) {
					largestAny = { name: n.name, n: c };
				}
			} );
		}
		var pick = largest || largestAny;
		if ( ! pick ) {
			return __( 'Describe the folders you want', 'vergelabs-media-library' );
		}
		var parts = [];
		/* translators: %s: a folder name */
		parts.push( sprintf( __( 'split %s by brand', 'vergelabs-media-library' ), pick.name ) );
		var residue = state.fit && state.fit.residue && state.fit.residue.class;
		if ( residue ) {
			/* translators: %s: what a group of pictures shows, e.g. "3d printers" */
			parts.push( sprintf( __( 'add %s', 'vergelabs-media-library' ), residue ) );
		}
		/* translators: %s: examples of a change, comma separated */
		return sprintf( __( 'Change the tree: %s…', 'vergelabs-media-library' ), parts.join( ', ' ) );
	}

	function talkOpts() {
		return {
			session: state.session,
			cap: cfg.cap || 25,
			canTalk: canTalk,
			mint: function () {
				return api( 'POST', 'guide/token' );
			},
			streamPath: '/guide/stream',
			request: function ( token, input, history ) {
				return { conversation: history, tree: treeForService(), input: input, summary: token.summary, current: token.current };
			},
			persist: function ( body ) {
				return persist( 'guide/turn', body );
			},
			blocks: [ 'tree' ],
			onBlock: function ( type, data ) {
				streamTree = resolveTree( data.tree );
				setDraft( streamTree, true );
			},
			onFinish: function () {
				var extra = null;
				if ( streamTree ) {
					extra = { draft: streamTree };
					state.session.draft = streamTree;
				}
				streamTree = null;
				renderTreeStep();
				return extra;
			},
			onRender: renderChange,
			lead: leadNote,
			placeholder: '',
			cappedPlaceholder: function ( used, cap ) {
				/* translators: 1: turns used, 2: the cap */
				return sprintf( __( '%1$s of %2$s turns used. Edit the tree by hand, or start over.', 'vergelabs-media-library' ), used, cap );
			},
			startOver: startOver,
			why: {
				turn_cap: __( 'Every turn of this conversation is used. Edit the tree by hand, or start over.', 'vergelabs-media-library' ),
				bad_block: __( 'The tree did not come through. The words stayed; the draft is as it was.', 'vergelabs-media-library' )
			}
		};
	}

	var CHIPS = [
		__( 'Fewer folders', 'vergelabs-media-library' ),
		__( 'Split by kind', 'vergelabs-media-library' ),
		__( 'By year first', 'vergelabs-media-library' )
	];

	function buildChange() {
		dom.change = el( 'div', { class: 'g-change' } );

		talkOpts_ = talkOpts();
		talk = TALK.create( talkOpts_ );
		talk.composer.querySelector( '.vgml-composer-text' ).setAttribute( 'data-placeholder-from', 'tree' );
		dom.change.appendChild( talk.composer );
		dom.change.appendChild( talk.conv );

		var chips = el( 'div', { class: 'g-chips' } );
		dom.chips = [];
		CHIPS.forEach( function ( c ) {
			var b = el( 'button', { type: 'button', class: 'g-chip' }, c );
			b.addEventListener( 'click', function () {
				if ( canTalk() && ! talk.streaming() ) {
					turn_( { choice: c }, { kind: 'choice', text: c } );
				}
			} );
			dom.chips.push( b );
			chips.appendChild( b );
		} );
		dom.wayIn = el( 'button', { type: 'button', class: 'g-chip is-way-in', 'aria-expanded': 'false' }, __( 'Paste or upload a list', 'vergelabs-media-library' ) );
		dom.wayIn.addEventListener( 'click', function () {
			dom.paste.hidden = ! dom.paste.hidden;
			dom.wayIn.setAttribute( 'aria-expanded', dom.paste.hidden ? 'false' : 'true' );
			if ( ! dom.paste.hidden ) {
				dom.pasteArea.focus();
			}
		} );
		chips.appendChild( dom.wayIn );
		dom.change.appendChild( chips );

		dom.change.appendChild( buildPaste() );
		return dom.change;
	}

	/** After every render of the log: the placeholder from the tree, the chips, the cap. */
	function renderChange() {
		if ( ! talk ) {
			return;
		}
		talkOpts_.placeholder = placeholder();
		var text = talk.composer.querySelector( '.vgml-composer-text' );
		// At the cap the visible placeholder is the cap's own; the label stays the tree's.
		text.setAttribute( 'aria-label', talkOpts_.placeholder );
		if ( ! talk.capped() ) {
			text.placeholder = talkOpts_.placeholder;
		}
		talk.composer.classList.toggle( 'is-capped', talk.capped() );
		// The model's own chips, when it offered some, stand in for the three.
		var turns = state.session.turns || [];
		var last = turns.length ? turns[ turns.length - 1 ] : null;
		var offered = !! ( last && 'assistant' === last.role && last.choices && last.choices.length && canTalk() );
		dom.chips.forEach( function ( b ) {
			b.hidden = offered;
			b.disabled = ! canTalk() || talk.streaming();
		} );
	}

	function turn_( input, said ) {
		talk.turn( input, said );
	}

	function stop() {
		talk.stop();
	}

	function startOver() {
		if ( talk.streaming() ) {
			talk.stop();
		}
		persist( 'guide/session', { reset: true } ).then( function ( r ) {
			state.session = r || { turns: [], draft: null, assistant_turns: 0, cap: cfg.cap, apply: null, tree: 'editing' };
			talk.setSession( state.session );
			view.setDraft( null );
			state.pasteSeq++;
			state.pastePending = false;
			dom.pasteArea.value = '';
			readPaste( false );
			talk.note( '' );
			renderCards();
		} );
	}

	/* ------------------------------------------------------- hand edits */

	function nameOf( draft, key ) {
		var name = '';
		draft.folders.forEach( function ( f ) {
			if ( f.key === key ) {
				name = f.name;
			}
		} );
		return name;
	}

	function onHandEdit( edit ) {
		if ( confirmed() ) {
			return;
		}
		var draft = view.getDraft() || TV.fromLive( state.nodes );
		var line = '';
		if ( 'remove' === edit.type ) {
			draft.folders.forEach( function ( f ) {
				if ( f.key === edit.key ) {
					edit.to = f.parent || '';
				}
			} );
			/* translators: %s: a folder name */
			line = sprintf( __( 'Removed %s', 'vergelabs-media-library' ), nameOf( draft, edit.key ) );
		} else if ( 'rename' === edit.type ) {
			/* translators: 1: the old name, 2: the new name */
			line = sprintf( __( 'Renamed %1$s to %2$s', 'vergelabs-media-library' ), edit.from, edit.to );
		} else if ( 'reparent' === edit.type ) {
			line = edit.parent
				/* translators: 1: a folder name, 2: its new parent */
				? sprintf( __( 'Moved %1$s under %2$s', 'vergelabs-media-library' ), nameOf( draft, edit.key ), nameOf( draft, edit.parent ) )
				/* translators: %s: a folder name */
				: sprintf( __( 'Moved %s to the top level', 'vergelabs-media-library' ), nameOf( draft, edit.key ) );
		} else if ( 'add' === edit.type ) {
			/*
			 *  Typed on the tree. The paste's path, not a model turn: the draft
			 *  goes to the turn route for the matcher's counts and no model is
			 *  asked -- confirm profiles the new folder, as it does a pasted one.
			 */
			var made = edit.name.split( '>' ).map( function ( s ) { return s.trim(); } ).filter( Boolean );
			var last = made.length ? made[ made.length - 1 ] : edit.name;
			line = edit.parent
				/* translators: 1: a folder name, 2: its parent */
				? sprintf( __( 'Added %1$s under %2$s', 'vergelabs-media-library' ), last, nameOf( draft, edit.parent ) )
				/* translators: %s: a folder name */
				: sprintf( __( 'Added %s', 'vergelabs-media-library' ), last );
			talk.pushUser( { kind: 'edit', text: line } );
			pasteDraft( withOrigin( TV.applyEdit( draft, edit ), state.session.draft ) );
			return;
		} else if ( 'classes' === edit.type ) {
			// The words a folder takes, from the tree's × and #word (C.4): the paste's path too -- the fit re-runs, no model is asked, the confirm seeds the profile from them.
			line = edit.added
				/* translators: 1: the word, 2: a folder name */
				? sprintf( __( 'Added the word %1$s to %2$s', 'vergelabs-media-library' ), edit.added, nameOf( draft, edit.key ) )
				/* translators: 1: the word, 2: a folder name */
				: sprintf( __( 'Removed the word %1$s from %2$s', 'vergelabs-media-library' ), edit.removed || '', nameOf( draft, edit.key ) );
			talk.pushUser( { kind: 'edit', text: line } );
			pasteDraft( withOrigin( TV.applyEdit( draft, edit ), state.session.draft ) );
			return;
		}
		// A paste still being answered is answered about a draft this edit has
		// just changed: that answer is dropped when it comes.
		state.pasteSeq++;
		state.pastePending = false;
		var next = withOrigin( TV.applyEdit( draft, edit ), state.session.draft );
		setDraft( next );
		if ( ! line ) {
			return;
		}
		if ( canTalk() && ! talk.streaming() ) {
			turn_( { edit: line }, { kind: 'edit', text: line } );
		} else {
			talk.pushUser( { kind: 'edit', text: line } );
		}
	}

	/* ------------------------------------------------ paste or upload a list */

	/*
	 *  One text box, one folder per line, the full path with ">" between
	 *  levels, or a .txt / .csv of the same read here in the browser (a CSV
	 *  row's cells are its levels). Read as it is typed
	 *  (js/vergeml-structure.js); beside it the tree of what was understood,
	 *  in the tree component's own rows, and under it the facts as pills. A
	 *  paste that reads clean becomes the draft and goes to the turn route for
	 *  its numbers, exactly as a conversation's tree does. A paste with a
	 *  refusal in it is said out loud and makes nothing. No PDF: nothing here
	 *  reads one, and the line says so.
	 */
	var previewView = null;
	var pasteTimer = null;
	var settleTimer = null;

	function structL10n() {
		return {
			/* translators: %s: a line number */
			noName: __( 'Line %s: no name', 'vergelabs-media-library' ),
			/* translators: 1: a line number, 2: the most levels allowed */
			tooDeep: __( 'Line %1$s: deeper than %2$s levels', 'vergelabs-media-library' ),
			/* translators: 1: folders in the paste, 2: the most one paste can hold */
			tooMany: __( '%1$s folders; %2$s is the most one paste can hold', 'vergelabs-media-library' )
		};
	}

	function buildPaste() {
		dom.paste = el( 'div', { class: 'vgml-paste', hidden: 'hidden' } );

		var ways = el( 'div', { class: 'vgml-paste-ways' } );
		var label = el( 'label', { class: 'g-chip', for: 'vgml-paste-file' }, __( 'Upload .txt or .csv', 'vergelabs-media-library' ) );
		dom.file = el( 'input', { type: 'file', id: 'vgml-paste-file', class: 'vgml-paste-file', accept: '.txt,.csv,text/plain,text/csv' } );
		dom.file.addEventListener( 'change', onFile );
		ways.appendChild( label );
		ways.appendChild( dom.file );
		ways.appendChild( el( 'span', { class: 'g-pill is-quiet' }, __( 'One folder per line, Hardware > Phones', 'vergelabs-media-library' ) ) );
		dom.paste.appendChild( ways );

		var cols = el( 'div', { class: 'vgml-paste-cols' } );
		dom.pasteArea = el( 'textarea', { class: 'vgml-paste-area', rows: '8', spellcheck: 'false', 'aria-label': __( 'Paste folders', 'vergelabs-media-library' ) } );
		dom.pasteArea.addEventListener( 'input', function () {
			window.clearTimeout( pasteTimer );
			pasteTimer = window.setTimeout( function () { readPaste( true ); }, 200 );
		} );
		cols.appendChild( dom.pasteArea );

		var box = el( 'div', { class: 'vgml-preview-box' } );
		dom.pasteTree = el( 'div', { class: 'vgml-paste-tree' } );
		box.appendChild( dom.pasteTree );
		cols.appendChild( box );
		dom.paste.appendChild( cols );

		dom.readLine = el( 'div', { class: 'g-pills vgml-read-line', hidden: 'hidden' } );
		dom.paste.appendChild( dom.readLine );
		dom.refused = el( 'ul', { class: 'vgml-facts vgml-paste-refused', hidden: 'hidden' } );
		dom.paste.appendChild( dom.refused );

		previewView = TV.create( {
			surface: 'folders',
			root: dom.pasteTree,
			nodes: [],
			indent: { step: 22, base: 8 },
			editable: false,
			head: false,
			fold: false,
			openAll: true,
			l10n: treeL10n()
		} );
		previewView.setMode( 'all', true );
		// No count on a folder the paste makes: the dry run has not looked yet,
		// and a zero here would be a number nobody computed.
		previewView.setCounted( false );

		return dom.paste;
	}

	function refuse( text ) {
		dom.refused.innerHTML = '';
		dom.refused.appendChild( el( 'li', null, text ) );
		dom.refused.hidden = false;
	}

	/** A .txt is the paste; a .csv row's cells are one path's levels. Anything else is refused in one line. */
	function onFile() {
		var file = dom.file.files && dom.file.files[ 0 ];
		dom.file.value = '';
		if ( ! file ) {
			return;
		}
		var kind = /\.csv$/i.test( file.name ) ? 'csv' : ( /\.txt$/i.test( file.name ) ? 'txt' : '' );
		if ( ! kind ) {
			/* translators: %s: a file name */
			refuse( sprintf( __( '%s is not a .txt or .csv file', 'vergelabs-media-library' ), file.name ) );
			return;
		}
		var reader = new FileReader();
		reader.onload = function () {
			var text = String( reader.result || '' );
			if ( 'csv' === kind ) {
				text = text.split( /\r?\n/ ).map( function ( line ) {
					return line.split( /[,;\t]/ ).map( function ( cell ) {
						return cell.trim().replace( /^"(.*)"$/, '$1' ).trim();
					} ).filter( Boolean ).join( ' > ' );
				} ).filter( Boolean ).join( '\n' );
			}
			dom.pasteArea.value = text;
			readPaste( true );
		};
		reader.readAsText( file );
	}

	function readPaste( andDraft ) {
		var parsed = STRUCT.parse( dom.pasteArea.value, state.nodes, structL10n() );

		var pv = STRUCT.toPreview( parsed, state.nodes );
		previewView.setTree( pv.nodes );
		previewView.setDraft( parsed.count ? pv.draft : null );
		// An empty box shows an empty preview, not the tree's "No changes yet".
		dom.pasteTree.hidden = ! parsed.count;

		dom.readLine.innerHTML = '';
		if ( parsed.count ) {
			dom.readLine.appendChild( pill( parsed.count, _n( 'folder', 'folders', parsed.count, 'vergelabs-media-library' ), 'accent' ) );
			dom.readLine.appendChild( pill( parsed.levels, _n( 'level', 'levels', parsed.levels, 'vergelabs-media-library' ) ) );
			if ( parsed.reused ) {
				dom.readLine.appendChild( pill( parsed.reused, _n( 'exists already', 'exist already', parsed.reused, 'vergelabs-media-library' ), 'quiet' ) );
			}
		}
		dom.readLine.hidden = ! parsed.count;

		dom.refused.innerHTML = '';
		parsed.refusals.forEach( function ( r ) {
			dom.refused.appendChild( el( 'li', null, r.text ) );
		} );
		dom.refused.hidden = ! parsed.refusals.length;

		if ( andDraft && parsed.count && ! parsed.refusals.length ) {
			pasteDraft( STRUCT.toDraft( parsed, state.nodes ) );
		}
	}

	/*
	 *  The paste as the draft. On screen at once, so the tree answers as the
	 *  person types; to the turn route once the typing settles, so the numbers
	 *  that come back are the matcher's. Until they do the rows carry no count
	 *  and the confirm waits: confirming before the session holds this draft
	 *  would confirm the draft before it.
	 */
	function pasteDraft( draft ) {
		state.pasteSeq++;
		var seq = state.pasteSeq;
		state.pastePending = true;
		setDraft( draft, true );

		window.clearTimeout( settleTimer );
		settleTimer = window.setTimeout( function () {
			if ( seq !== state.pasteSeq ) {
				return;
			}
			queue = queue.then( function () {
				return api( 'POST', 'guide/turn', { draft: draft } ).then( function ( r ) {
					if ( seq !== state.pasteSeq ) {
						return;
					}
					state.pastePending = false;
					if ( r && undefined !== r.fit ) {
						tookFit( r );
					} else {
						renderCards();
					}
				}, function ( err ) {
					if ( seq !== state.pasteSeq ) {
						return;
					}
					state.pastePending = false;
					refuse( ( err && err.message ) || __( 'That did not go through. Try again.', 'vergelabs-media-library' ) );
					renderCards();
				} );
			}, function () {} );
		}, 600 );
	}

	/* --------------------------------------------------- the dry run's silence */

	/*
	 *  The dry run looked and gave no answer -- it ran past its budget, or it
	 *  could not score at all -- or it has not answered yet. A folder then
	 *  shows no count, the button offers none, and nothing on the draft reads
	 *  as a zero: zero is what the matcher says after looking, and it never
	 *  finished looking.
	 */
	function fitUnknown() {
		return state.pastePending || !! ( state.fit && false === state.fit.counted );
	}

	function syncCounted() {
		if ( view ) {
			view.setCounted( ! fitUnknown() );
		}
	}

	/* ------------------------------------------------------------ the run */

	function untilText( ts ) {
		if ( ! ts ) {
			return '';
		}
		var d = new Date( ts * 1000 );
		var now = new Date();
		var time = d.toLocaleTimeString( undefined, { hour: '2-digit', minute: '2-digit' } );
		var day = d.toDateString();
		var tomorrow = new Date( now.getTime() + 86400000 ).toDateString();
		if ( day === now.toDateString() ) {
			/* translators: %s: a time */
			return sprintf( __( 'today %s', 'vergelabs-media-library' ), time );
		}
		if ( day === tomorrow ) {
			/* translators: %s: a time */
			return sprintf( __( 'tomorrow %s', 'vergelabs-media-library' ), time );
		}
		return d.toLocaleDateString( undefined, { day: 'numeric', month: 'long' } ) + ' ' + time;
	}

	function renderMove() {
		syncCounted();
		var moving = running();
		dom.stop.hidden = ! moving;
		root.setAttribute( 'data-state', moving ? 'moving' : ( state.undo.available && ! view.getDraft() ? 'done' : 'resting' ) );

		if ( moving ) {
			var r = state.moving || {};
			var seen = Number( r.seen ) || 0;
			var total = Number( r.total ) || 0;
			dom.move.textContent = total
				/* translators: 1: pictures looked at so far, 2: pictures to look at */
				? sprintf( __( 'Filling %1$s of %2$s', 'vergelabs-media-library' ), fmt( seen ), fmt( Math.max( seen, total ) ) )
				/* translators: %s: pictures looked at so far */
				: sprintf( __( 'Filling · %s so far', 'vergelabs-media-library' ), fmt( seen ) );
			dom.move.disabled = true;
			dom.move.hidden = false;
			dom.undo.hidden = true;
			return;
		}

		// Every described picture is scored against the confirmed tree: that is the number on the button.
		var n = state.fit && state.fit.counted ? Number( state.fit.looked ) || 0 : Number( state.facts.pictures ) || 0;
		/* translators: %s: pictures */
		dom.move.textContent = sprintf( _n( 'Fill %s picture', 'Fill %s pictures', n, 'vergelabs-media-library' ), fmt( n ) );
		dom.move.disabled = ! described || state.pastePending || ! confirmed();
		dom.move.hidden = false;
		renderUndo();
	}

	/** Undo covers the whole step -- the run and every answer -- for a day. */
	function renderUndo() {
		if ( state.undo.available ) {
			dom.undo.textContent = state.undo.until
				/* translators: %s: when undo ends */
				? sprintf( __( 'Undo until %s', 'vergelabs-media-library' ), untilText( state.undo.until ) )
				: __( 'Undo', 'vergelabs-media-library' );
			dom.undo.hidden = false;
			dom.undo.disabled = false;
		} else {
			dom.undo.hidden = true;
		}
	}

	function onMove() {
		if ( dom.move.disabled ) {
			return;
		}
		if ( talk.streaming() ) {
			stop();
		}
		// Pressed, not yet answered: the button reads as running from here, so
		// nothing that redraws it in between can hand it back.
		state.applying = true;
		state.moving = null;
		renderFill();
		api( 'POST', 'guide/apply' ).then( function ( r ) {
			state.applying = false;
			took( r );
		} ).catch( function ( err ) {
			state.applying = false;
			talk.note( ( err && err.message ) || __( 'That did not go through. Nothing moved.', 'vergelabs-media-library' ) );
			renderCards();
		} );
	}

	var pollTimer = null;

	/** A progress answer, whether the run is still going or just ended. */
	function took( r ) {
		if ( ! r ) {
			return;
		}
		state.session = r.session || state.session;
		talk.setSession( state.session );
		state.undo = r.undo || state.undo;
		state.moving = r.report || null;
		if ( r.version ) {
			state.version = r.version;
			if ( watcher ) {
				watcher.known( r.version );
			}
		}
		if ( r.report && r.report.running ) {
			view.setProgress( r.report.by_key || r.report.by_term || {} );
			renderRail();
			renderFill();
			window.clearTimeout( pollTimer );
			pollTimer = window.setTimeout( function () {
				api( 'GET', 'guide/progress' ).then( took ).catch( function () {} );
			}, 2000 );
			return;
		}
		// Done, or stopped: the tree is the library now.
		window.clearTimeout( pollTimer );
		view.setProgress( null );
		view.setDraft( null );
		state.session.draft = null;
		state.fit = null;
		state.pasteSeq++;
		state.pastePending = false;
		state.lastAnswered = '';
		state.showing = {};
		loadQuestions().then( function () {
			return refreshTree();
		} ).then( function () {
			renderRail();
			renderCards();
			// The folders a paste named exist now; the preview reads them as such.
			readPaste( false );
		} );
	}

	function onStop() {
		dom.stop.disabled = true;
		api( 'POST', 'guide/stop' ).then( function ( r ) {
			dom.stop.disabled = false;
			took( r );
		} ).catch( function () { dom.stop.disabled = false; } );
	}

	function onUndo() {
		dom.undo.disabled = true;
		api( 'POST', 'guide/undo' ).then( function ( r ) {
			took( { session: r.session, undo: r.undo, version: r.version, report: null } );
		} ).catch( function ( err ) {
			dom.undo.disabled = false;
			talk.note( ( err && err.message ) || __( 'That did not go through.', 'vergelabs-media-library' ) );
		} );
	}

	/* --------------------------------------------------------------- go */

	build();
	makeTree();
	talk.render();
	setStep( stepAt() );
	root.classList.add( 'is-ready' );

	// A run still going from before this page loaded carries on being watched.
	if ( running() ) {
		api( 'GET', 'guide/progress' ).then( took ).catch( function () {} );
	} else if ( ( Number( state.fill.open ) || 0 ) > 0 ) {
		// Questions left open: read once the page has painted. Read-only, no model.
		loadQuestions().then( renderCards );
	}

	// Nothing opens by itself: no turn, no proposal, no model route until a button is pressed.

	window.vgmlFoldersApp = { state: state, view: function () { return view; }, preview: function () { return previewView; }, stop: stop, setStep: setStep, render: renderCards };
}() );
