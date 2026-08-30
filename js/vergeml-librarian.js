/*
 *  The Librarian screen: the ladder, the chooser, the review, and the two
 *  loops that write.
 *
 *  One page with four states, and the states are not four pages. A library
 *  that has not been scanned, one whose files are not described, one with no
 *  proposal and one with a proposal ready are all the same screen at
 *  different depths -- and each rung starts the thing it is missing right
 *  here, using the step loop that already exists for it. A message saying
 *  "go and do X first" is a dead end, and a dead end on the screen the whole
 *  feature converges on is the feature not existing.
 *
 *  The write loops -- apply and undo -- are the shape everything else in this
 *  plugin uses: one REST call a chunk, carrying the id the last one returned,
 *  driven from here so a shared host never has to hold a request open. Closing
 *  the tab pauses; it does not lose anything.
 *
 *  ES5, no build step, no framework. Same as every other script here.
 */
( function () {
	'use strict';

	var apiFetch = window.wp && window.wp.apiFetch;
	var conf = window.vergemlLibrarian || {};
	var l10n = conf.l10n || {};
	var SAMPLES = conf.samples || 6;

	if ( ! apiFetch ) {
		return;
	}

	var $ = function ( id ) {
		return document.getElementById( id );
	};

	function text( key, fallback ) {
		return l10n[ key ] || fallback || '';
	}

	/*
	 *  One file, not "1 files".
	 *
	 *  A folder holding one picture is common at the tail of any real library
	 *  -- the review screen at five hundred files had six of them -- so this
	 *  is not an edge case somebody sees once. wp.i18n's _n is not loaded on
	 *  this screen, and loading it to choose between two strings that PHP has
	 *  already translated would be the long way round.
	 */
	function count( n, key ) {
		return 1 === Number( n )
			? text( key + 'One', '1 file' )
			: sprintf( text( key, '%s files' ), [ String( n ) ] );
	}

	function sprintf( template, values ) {
		var i = 0;
		return String( template )
			.replace( /%(\d)\$[sd]/g, function ( match, position ) {
				return values[ Number( position ) - 1 ];
			} )
			.replace( /%[sd]/g, function () {
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

	function button( label, className ) {
		var node = el( 'button', className || 'button', label );
		node.type = 'button';
		return node;
	}

	// A duration a person reads, not a figure. The estimate is honest about
	// being an estimate, so a rounded one is the truthful presentation.
	function duration( ms ) {

		var seconds = Math.round( Number( ms ) / 1000 );

		if ( seconds < 60 ) {
			return seconds + 's';
		}

		var minutes = Math.round( seconds / 60 );

		return minutes < 60 ? minutes + ' min' : Math.round( minutes / 60 ) + ' h';
	}

	/* ------------------------------------------------------------- state */

	var STEPS = conf.steps || [];

	var state = {
		stage: conf.stage || 'unscanned',
		scheme: '',
		runId: 0,
		tree: [],
		edits: {},      // branch key -> { label, enabled }
		batchId: 0,
		running: false,
		paused: false,
		step: '',       // which of STEPS is on screen
		halt: false,    // the pause button, read by the stepping loops
	};

	/* ------------------------------------------------------------- the steps */

	/*
	 *  Which step a stage is.
	 *
	 *  The stages are what the server knows -- has it been scanned, is
	 *  anything described. The steps are what a person is walked through, and
	 *  the last two of them are not stages at all: choosing a scheme and
	 *  reading the proposal both happen once the library is 'ready'. Keeping
	 *  the two vocabularies apart is what lets the rail say "4 of 5" on a
	 *  screen the server thinks of as one state.
	 */
	function stepOfStage() {

		// Two steps now. The scan and the describing are one job, and
		// everything after them is a choice rather than a stage.
		return ( 'unscanned' === state.stage || 'unindexed' === state.stage )
			? 'ready'
			: 'choose';
	}

	function stepAt( id ) {
		for ( var i = 0; i < STEPS.length; i++ ) {
			if ( STEPS[ i ].id === id ) {
				return i;
			}
		}
		return 0;
	}

	function stepNow() {
		return STEPS[ stepAt( state.step ) ] || { title: '', note: '' };
	}

	/*
	 *  The rail, and the line that replaces it when there is no room.
	 *
	 *  Both are drawn every time, and the stylesheet shows one of them --
	 *  deciding in JavaScript would mean listening to resize and getting it
	 *  wrong on a rotated tablet.
	 */
	function drawSteps() {

		state.step = stepOfStage();

		var at = stepAt( state.step );

		/* ---- the rail */

		var rail = $( 'vgml-lib-steps' );
		rail.innerHTML = '';

		rail.appendChild( el( 'h2', 'vgml-flow-rail-head', text( 'stepsHead', 'What happens' ) ) );

		var list = el( 'ol', 'vgml-flow-steps' );

		STEPS.forEach( function ( item, i ) {

			var row = el( 'li', 'vgml-flow-step-row' );
			var word = i < at ? 'stateDone' : ( i === at ? 'stateNow' : 'stateLater' );

			row.className += i < at ? ' is-done' : ( i === at ? ' is-now' : ' is-later' );

			row.appendChild( el( 'span', 'vgml-flow-step-name', item.title ) );
			row.appendChild( el( 'span', 'vgml-flow-step-state', text( word, '' ) ) );

			list.appendChild( row );
		} );

		rail.appendChild( list );

		/* ---- the line, for when the rail is not shown */

		var head = $( 'vgml-lib-headline' );
		head.innerHTML = '';

		var line = el( 'p', 'vgml-flow-line' );

		line.appendChild( el( 'span', 'vgml-flow-line-n', sprintf(
			text( 'stepOf', 'Step %1$s of %2$s' ),
			[ String( at + 1 ), String( STEPS.length ) ]
		) ) );

		line.appendChild( el( 'span', 'vgml-flow-line-name', stepNow().title ) );

		head.appendChild( line );

		var track = el( 'div', 'vgml-flow-line-bar' );
		var fill = el( 'div', 'vgml-flow-line-fill' );

		fill.style.width = Math.round( ( at / STEPS.length ) * 100 ) + '%';

		track.appendChild( fill );
		head.appendChild( track );

		/*
		 *  One thing on the screen at a time.
		 *
		 *  Everything below the flow -- the history, the two other filing
		 *  tools -- is a follow-up, not an alternative. Offered beside step
		 *  two it competes with the step; offered at the end it answers the
		 *  question somebody actually has by then.
		 */
		var after = $( 'vgml-lib-after' );

		if ( after ) {
			after.hidden = 'review' !== state.step;
		}
	}

	/* -------------------------------------------------------- the ladder */

	function card( title, note ) {
		var wrap = el( 'div', 'vgml-ai-card vgml-lib-rung' );
		wrap.appendChild( el( 'h2', null, title ) );
		wrap.appendChild( el( 'p', 'description', note ) );
		return wrap;
	}

	function bar( id ) {
		var wrap = el( 'div', 'vgml-import-bar' );
		var fill = el( 'div', 'vgml-import-fill' );
		fill.id = id;
		wrap.appendChild( fill );
		wrap.hidden = true;
		return wrap;
	}

	/*
	 *  What a long step shows while it runs.
	 *
	 *  Describing five hundred pictures takes minutes, and what this screen
	 *  used to offer for those minutes was the sentence "495 to go" -- no
	 *  total, so no sense of whether that was nearly done or barely started;
	 *  no estimate, so no way to decide whether to wait; and no way to stop
	 *  that did not mean closing the tab and hoping.
	 *
	 *  So: a bar, "213 of 500", a measured estimate, and a pause. The
	 *  estimate is measured rather than assumed -- the first few files on
	 *  their server are worth more than any constant that could be written
	 *  here -- so it says it does not know yet until it does.
	 */
	function progress() {

		var node = el( 'div', 'vgml-flow-progress' );
		var track = el( 'div', 'vgml-import-bar' );
		var fill = el( 'div', 'vgml-import-fill' );

		track.appendChild( fill );

		var figure = el( 'p', 'vgml-flow-figure' );
		var count = el( 'strong', 'vgml-flow-count' );
		var eta = el( 'span', 'vgml-flow-eta' );

		figure.appendChild( count );
		figure.appendChild( eta );

		node.appendChild( track );
		node.appendChild( figure );
		node.hidden = true;

		var started = 0;
		var base = 0;   // how many were already finished when this run began

		return {
			node: node,

			start: function ( done ) {
				node.hidden = false;
				started = Date.now();
				base = Number( done ) || 0;
			},

			/*
			 *  Every call re-states both numbers, so the bar and the words can
			 *  never disagree -- they are the same two figures rendered twice.
			 */
			set: function ( done, total ) {

				done = Math.max( 0, Number( done ) || 0 );
				total = Math.max( done, Number( total ) || 0 );

				fill.style.width = total ? Math.round( ( done / total ) * 100 ) + '%' : '100%';

				count.textContent = sprintf( text( 'ofTotal', '%1$s of %2$s' ), [
					String( done ),
					String( total ),
				] );

				var moved = done - base;
				var left = total - done;

				if ( ! left ) {
					eta.textContent = '';
					return;
				}

				// Measured, not guessed. Under two finished files there is
				// nothing to extrapolate from and saying so is the honest
				// answer -- a number invented at that point is a number
				// somebody plans their afternoon around.
				if ( moved < 2 ) {
					eta.textContent = ' · ' + text( 'timeUnknown', '' );
					return;
				}

				eta.textContent = ' · ' + sprintf(
					text( 'timeLeft', 'about %s left' ),
					[ duration( ( ( Date.now() - started ) / moved ) * left ) ]
				);
			},

			// An estimate the server worked out, for the steps that measure
			// themselves rather than counting files.
			told: function ( fraction, ms ) {

				fill.style.width = Math.round( Math.min( 1, Math.max( 0, fraction ) ) * 100 ) + '%';
				count.textContent = '';
				eta.textContent = ms ? sprintf( text( 'timeLeft', 'about %s left' ), [ duration( ms ) ] )
					: text( 'timeUnknown', '' );
			},

			stop: function () {
				node.hidden = true;
			},
		};
	}

	/*
	 *  Stop, and mean it.
	 *
	 *  Every long step is a loop of small requests, so pausing is a flag the
	 *  loop reads rather than anything cancelled in flight: the request
	 *  already sent finishes and is kept. That is why the copy can promise
	 *  nothing is lost -- it is true by construction.
	 */
	function pauser( onResume ) {

		var node = button( text( 'pause', 'Pause' ) );

		node.hidden = true;

		node.addEventListener( 'click', function () {

			state.halt = ! state.halt;
			node.textContent = state.halt ? text( 'resume', 'Resume' ) : text( 'pause', 'Pause' );

			if ( ! state.halt ) {
				onResume();
			}
		} );

		return node;
	}

	/*
	 *  The frame every step is drawn into: its name, what it is for, and
	 *  wherever it applies a way back to the one before it.
	 */
	/*
	 *  What this step is about to do, in numbers, before it is started.
	 *
	 *  Sits between the sentence explaining the step and the button that runs
	 *  it, which is the only place somebody reads it in time for it to matter.
	 */
	function figures() {

		var node = el( 'p', 'vgml-flow-cost' );

		node.hidden = true;

		return {
			node: node,
			say: function ( line, warn ) {
				node.textContent = line;
				node.hidden = '' === line;
				node.className = warn ? 'vgml-flow-cost is-short' : 'vgml-flow-cost';
			},
		};
	}

	function stepCard() {

		var item = stepNow();
		var wrap = el( 'div', 'vgml-flow-card vgml-lib-rung' );

		wrap.appendChild( el( 'h2', null, item.title ) );
		wrap.appendChild( el( 'p', 'vgml-flow-note', item.note ) );

		return wrap;
	}

	/*
	 *  Each rung drives the loop that already exists for its step, rather
	 *  than a second implementation of it living here: the duplicate scan is
	 *  /health-scan, describing is /ai-index, proposing is /organize-step.
	 *  Three loops, one shape, and this screen only supplies the progress.
	 */
	function drawLadder() {

		var host = $( 'vgml-lib-stage' );
		host.innerHTML = '';

		state.halt = false;
		drawSteps();

		if ( 'ready' === state.step ) {
			host.appendChild( rungReady() );
			host.appendChild( datePathNote() );
			return;
		}

		drawHub( host );
	}


	/*
	 *  One run: compare for copies, then look at every picture.
	 *
	 *  They were two steps because they are two loops against two endpoints.
	 *  That is a fact about this codebase and not about anybody's afternoon --
	 *  nobody wants to check for copies, they want their library looked at,
	 *  and the copies check is how that is done without paying twice for the
	 *  same photograph. So it is one button, one bar, and a sentence saying
	 *  which half is running.
	 */
	function rungReady() {

		var wrap = stepCard();
		var go = button( text( 'readyGo', 'Start' ), 'button button-primary' );
		var note = el( 'p', 'vgml-lib-note' );
		var meter = progress();
		var cost = figures();
		var mode = el( 'p', 'vgml-flow-keep' );

		var total = 0;
		var done = 0;

		function readStatus( r ) {

			total = ( Number( r.indexed ) || 0 ) + ( Number( r.unindexed ) || 0 );
			done = Number( r.indexed ) || 0;

			var left = Number( r.unindexed ) || 0;
			var purse = null === r.credits || undefined === r.credits ? null : Number( r.credits );

			cost.say( sprintf( text( 'readyCost', '' ), [ String( left ), String( left ) ] ) +
				( null === purse ? '' : ' ' + sprintf( text( 'costCredits', '' ), [ String( left ), String( purse ) ] ) ),
				null !== purse && purse < left );
		}

		/* ---- the second half */

		function look() {

			mode.textContent = text( 'readyLook', '' );

			apiFetch( {
				path: '/vergeml/v1/ai-index',
				method: 'POST',
				// Alt text is its own thing now, asked for separately, so this
				// writes descriptions and touches nobody's alt attribute.
				data: { scope: 'unindexed', limit: 24, apply_alt: false },
			} ).then( function ( r ) {

				var left = r.remaining || 0;
				var moved = ( r.described || [] ).length + ( r.errors || [] ).length;

				if ( total ) {
					meter.set( total - left, total );
				}

				if ( ! left || ! moved ) {
					boot();
					return;
				}

				if ( state.halt ) {
					note.textContent = text( 'paused', 'Paused.' );
					return;
				}

				look();

			} ).catch( fail( note, go ) );
		}

		/* ---- the first half */

		function compare( cursor, reset ) {

			mode.textContent = text( 'readyScan', '' );

			apiFetch( {
				path: '/vergeml/v1/health-scan',
				method: 'POST',
				data: { cursor: cursor, reset: reset },
			} ).then( function ( r ) {

				// The scan has its own total; the bar belongs to the looking,
				// which is the long half. This half only says it is happening.
				if ( r.done ) {
					look();
					return;
				}

				if ( state.halt ) {
					note.textContent = text( 'paused', 'Paused.' );
					return;
				}

				compare( r.cursor, false );

			} ).catch( fail( note, go ) );
		}

		var stop = pauser( function () {
			note.textContent = '';
			look();
		} );

		go.addEventListener( 'click', function () {

			go.disabled = true;
			stop.hidden = false;
			note.textContent = '';
			cost.say( '' );

			meter.start( done );
			meter.set( done, total );

			if ( 'unscanned' === state.stage ) {
				compare( 0, true );
				return;
			}

			look();
		} );

		apiFetch( { path: '/vergeml/v1/ai-status' } ).then( function ( r ) {
			readStatus( r );
			if ( done > 0 ) {
				meter.node.hidden = false;
			}
			meter.set( done, total );
		} ).catch( function () {} );

		var actions = el( 'p', 'vgml-flow-actions' );

		actions.appendChild( go );
		actions.appendChild( stop );

		wrap.appendChild( cost.node );
		wrap.appendChild( actions );
		wrap.appendChild( meter.node );
		wrap.appendChild( mode );
		wrap.appendChild( note );

		return wrap;
	}


	/*
	 *  What can be done with what we found.
	 *
	 *  Four rows, each with a count and one button, and none of them a stage
	 *  of anything. A row that has nothing to do says so rather than
	 *  disappearing: "every picture has alt text" is a thing somebody wants to
	 *  read, and a row that vanishes when it succeeds makes the screen change
	 *  shape for reasons nobody can see.
	 */
	function drawHub( host ) {

		host.innerHTML = '';

		var item = stepNow();

		host.appendChild( el( 'h2', 'vgml-flow-head', item.title ) );
		host.appendChild( el( 'p', 'vgml-flow-note', item.note ) );

		var list = el( 'div', 'vgml-do-list' );
		host.appendChild( list );

		function row( id, title, note, count, label, run, doneWord ) {

			var wrap = el( 'div', 'vgml-do' );

			wrap.appendChild( el( 'h3', 'vgml-do-title', title ) );
			wrap.appendChild( el( 'p', 'vgml-do-note', note ) );

			var line = el( 'p', 'vgml-do-line' );

			if ( count > 0 ) {

				line.appendChild( el( 'strong', 'vgml-do-count', label ) );
				wrap.appendChild( line );

				var go = button( run.label, 'button button-primary' );
				var said = el( 'span', 'vgml-do-said' );

				go.addEventListener( 'click', function () {
					go.disabled = true;
					said.textContent = text( 'applying', 'Working…' );
					run.go( go, said );
				} );

				var actions = el( 'p', 'vgml-flow-actions' );
				actions.appendChild( go );
				actions.appendChild( said );
				wrap.appendChild( actions );

			} else {
				line.className += ' is-done';
				line.textContent = doneWord;
				wrap.appendChild( line );
			}

			list.appendChild( wrap );
		}

		/*
		 *  How many files are in no folder comes from the tree, which is where
		 *  the count in the page heading comes from too.
		 *
		 *  The first cut fell back to the scheme's total when the field was
		 *  missing, which is every described file rather than every unfiled
		 *  one -- so the heading read "0 pictures not in a folder" and the row
		 *  under it read "510 are in no folder", on the same screen.
		 */
		var unfiled = apiFetch( { path: '/vergeml/v1/librarian-schemes' } )
			.then( function ( r ) {
				if ( ! r.taxonomy ) {
					return { schemes: r.schemes || [], unassigned: 0 };
				}
				return apiFetch( { path: '/vergeml/v1/tree?taxonomy=' + encodeURIComponent( r.taxonomy ) } )
					.then( function ( tree ) {
						return { schemes: r.schemes || [], unassigned: Number( tree.unassigned ) || 0 };
					} );
			} )
			.catch( function () { return { schemes: [], unassigned: 0 }; } );

		Promise.all( [
			apiFetch( { path: '/vergeml/v1/ai-alt' } ).catch( function () { return { remaining: 0 }; } ),
			apiFetch( { path: '/vergeml/v1/health-report' } ).catch( function () { return {}; } ),
			unfiled,
			apiFetch( { path: '/vergeml/v1/rename' } ).catch( function () { return { remaining: 0 }; } ),
		] ).then( function ( answers ) {

			var alt = Number( answers[0].remaining ) || 0;
			var health = answers[1];
			var schemes = answers[2];
			var names = Number( answers[3].remaining ) || 0;

			var subject = ( schemes.schemes || [] ).filter( function ( s ) {
				return 'subject' === s.id;
			} )[ 0 ];

			var loose = Number( schemes.unassigned ) || 0;
			var copies = Number( health.duplicates ) || 0;

			/* ---- alt text */

			row( 'alt', text( 'doAlt', '' ), text( 'doAltNote', '' ), alt,
				sprintf( text( 'doAltCount', '' ), [ String( alt ) ] ),
				{
					label: text( 'doAltGo', '' ),
					go: function ( go, said ) {
						( function step() {
							apiFetch( { path: '/vergeml/v1/ai-alt', method: 'POST', data: { limit: 100 } } )
								.then( function ( r ) {
									if ( r.remaining > 0 && r.wrote > 0 ) {
										said.textContent = sprintf( text( 'remaining', '%s to go' ), [ String( r.remaining ) ] );
										step();
										return;
									}
									drawHub( host );
								} )
								.catch( fail( said, go ) );
						} )();
					},
				},
				text( 'doAltDone', '' ) );

			/* ---- names */

			row( 'names', text( 'doNames', '' ), text( 'doNamesNote', '' ), names,
				sprintf( text( 'doNamesCount', '' ), [ String( names ) ] ),
				{
					label: text( 'doNamesGo', '' ),
					go: function ( go, said ) {
						apiFetch( { path: '/vergeml/v1/rename', method: 'POST', data: { limit: 500 } } )
							.then( function () { drawHub( host ); } )
							.catch( fail( said, go ) );
					},
				},
				text( 'doNamesDone', '' ) );

			/* ---- folders */

			row( 'folders', text( 'doFolders', '' ), text( 'doFoldersNote', '' ), loose,
				sprintf( text( 'doFoldersCount', '' ), [ String( loose ) ] ),
				{
					label: subject && subject.ready ? text( 'doFoldersReady', '' ) : text( 'doFoldersGo', '' ),
					go: function () {
						state.stage = subject && subject.ready ? 'ready' : 'unproposed';
						drawFolders( host );
					},
				},
				text( 'doFoldersDone', '' ) );

			/* ---- copies */

			row( 'copies', text( 'doCopies', '' ), text( 'doCopiesNote', '' ), copies,
				sprintf( text( 'doCopiesCount', '' ), [ String( copies ) ] ),
				{
					label: text( 'doCopiesGo', '' ),
					go: function () {
						window.location.href = conf.healthUrl || 'admin.php?page=media-health';
					},
				},
				text( 'doCopiesDone', '' ) );

			if ( ! alt && ! names && ! loose && ! copies ) {
				list.appendChild( el( 'p', 'vgml-do-none', text( 'nothingLeft', '' ) ) );
			}
		} );
	}


	/*
	 *  Folders, reached from the hub rather than walked into.
	 *
	 *  Working out the folders and reading them are still two things, because
	 *  the first is a run and the second is a document. They are just no
	 *  longer numbered steps on the way to something else.
	 */
	function drawFolders( host ) {

		host.innerHTML = '';

		var back = button( text( 'stepBack', 'Back' ), 'button-link' );

		back.addEventListener( 'click', function () {
			state.tree = [];
			$( 'vgml-lib-review' ).innerHTML = '';
			drawHub( host );
		} );

		if ( 'unproposed' === state.stage ) {
			host.appendChild( back );
			host.appendChild( rungPropose() );
			host.appendChild( datePathNote() );
			return;
		}

		// drawChooser clears the host, so the way back goes in after it rather
		// than before -- the first cut put it in and then wiped it.
		drawChooser( host );
		host.insertBefore( back, host.firstChild );
	}

	// The date scheme needs nothing from the two rungs above it, so it is
	// offered from both -- somebody who does not want to describe their
	// library is not stuck on a rung they have no intention of climbing.
	function datePathNote() {

		var wrap = el( 'p', 'vgml-lib-skip' );
		var go = button( text( 'skipToDate', 'Or file by date instead.' ), 'button-link' );

		go.addEventListener( 'click', function () {
			pick( 'datetype', 0 );
		} );

		wrap.appendChild( go );

		return wrap;
	}

	function rungScan() {

		var wrap = stepCard();
		var go = button( text( 'ladderScanGo', 'Scan the library' ), 'button button-primary' );
		var note = el( 'p', 'vgml-lib-note' );
		var meter = progress();
		var at = 0;

		function run( cursor, reset ) {

			apiFetch( {
				path: '/vergeml/v1/health-scan',
				method: 'POST',
				data: { cursor: cursor, reset: reset },
			} ).then( function ( r ) {

				var total = r.total || ( r.remaining + r.hashed );

				at = r.cursor;
				meter.set( total - r.remaining, total );

				if ( r.done ) {
					boot();
					return;
				}

				if ( state.halt ) {
					note.textContent = text( 'paused', 'Paused.' );
					return;
				}

				run( r.cursor, false );

			} ).catch( fail( note, go ) );
		}

		var stop = pauser( function () {
			note.textContent = '';
			run( at, false );
		} );

		go.addEventListener( 'click', function () {
			go.disabled = true;
			stop.hidden = false;
			note.textContent = '';
			cost.say( '' );
			meter.start( 0 );
			run( 0, true );
		} );

		var cost = figures();

		apiFetch( { path: '/vergeml/v1/ai-status' } ).then( function ( r ) {
			cost.say( sprintf( text( 'costScan', '' ), [ String( r.images || 0 ) ] ) );
		} ).catch( function () {} );

		var actions = el( 'p', 'vgml-flow-actions' );

		actions.appendChild( go );
		actions.appendChild( stop );

		wrap.appendChild( cost.node );
		wrap.appendChild( actions );
		wrap.appendChild( meter.node );
		wrap.appendChild( note );

		return wrap;
	}

	function rungIndex() {

		var wrap = stepCard();
		var go = button( text( 'ladderIndexGo', 'Start describing' ), 'button button-primary' );
		var away = button( text( 'bgGo', 'Or run it in the background' ) );
		var note = el( 'p', 'vgml-lib-note' );
		var meter = progress();
		var mode = el( 'p', 'vgml-flow-keep' );

		/*
		 *  The total is the whole library, not what is left when the button is
		 *  pressed.
		 *
		 *  Those differ the moment somebody stops halfway and comes back, and
		 *  the second reading is the one that survives a reload: 213 of 500
		 *  means the same thing on a fresh page as it did before, where "287
		 *  to go" silently restarted its own arithmetic.
		 */
		var total = 0;
		var done = 0;
		var polling = 0;

		var cost = figures();

		/*
		 *  Two figures and what they cost. The credit balance is part of it:
		 *  five hundred pictures and three hundred credits is a thing to be
		 *  told before pressing, not discovered two hundred pictures in.
		 */
		function readStatus( r ) {

			total = ( Number( r.indexed ) || 0 ) + ( Number( r.unindexed ) || 0 );
			done = Number( r.indexed ) || 0;

			var left = Number( r.unindexed ) || 0;
			var purse = null === r.credits || undefined === r.credits ? null : Number( r.credits );

			var line = sprintf( text( 'costDescribe', '' ), [ String( left ), String( total ) ] );

			if ( null === purse ) {
				cost.say( line );
				return;
			}

			if ( purse < left ) {
				cost.say( line + ' ' + sprintf( text( 'costShort', '' ), [ String( left ), String( purse ) ] ), true );
				return;
			}

			cost.say( line + ' ' + sprintf( text( 'costCredits', '' ), [ String( left ), String( purse ) ] ) );
		}

		/* ------------------------------------------------------- in this tab */

		function run() {

			apiFetch( {
				path: '/vergeml/v1/ai-index',
				method: 'POST',
				// Three groups of eight. The server caps this, so a client asking
				// for more gets what the host can safely finish rather than a
				// timeout halfway through somebody's library.
				data: { scope: 'unindexed', limit: 24, apply_alt: true },
			} ).then( function ( r ) {

				var left = r.remaining || 0;
				var moved = ( r.described || [] ).length + ( r.errors || [] ).length;

				if ( total ) {
					meter.set( total - left, total );
				}

				// The same terminating condition vergeml-ai.js uses: stop when
				// there is nothing left, and stop when a pass moved nothing --
				// otherwise a file that keeps failing is an infinite loop
				// rather than a report.
				if ( ! left || ! moved ) {
					boot();
					return;
				}

				if ( state.halt ) {
					note.textContent = text( 'paused', 'Paused.' );
					return;
				}

				run();

			} ).catch( fail( note, go ) );
		}

		var stop = pauser( function () {
			note.textContent = '';
			run();
		} );

		go.addEventListener( 'click', function () {
			go.disabled = true;
			away.hidden = true;
			stop.hidden = false;
			note.textContent = '';
			mode.textContent = text( 'keepOpen', '' );

			/*
			 *  Both of these are figures taken before the run started, and a
			 *  figure that stopped being true while somebody watches it is
			 *  worse than no figure. The bar says the same things, correctly,
			 *  from here on.
			 */
			cost.say( '' );
			anyway.hidden = true;

			meter.start( done );
			meter.set( done, total );
			run();
		} );

		/* ------------------------------------------------ without this tab */

		/*
		 *  Five hundred pictures is minutes of describing, and "keep this tab
		 *  open" is a poor thing to ask of somebody for minutes. The same step
		 *  function runs on WP-Cron -- core/ai-background.php -- so this hands
		 *  it over and then only watches.
		 *
		 *  It is offered rather than defaulted to, because cron fires when
		 *  somebody visits the site: on a quiet site the foreground run is
		 *  genuinely faster, and which is better is a fact about their traffic
		 *  that this screen does not know.
		 */
		function watch() {

			apiFetch( { path: '/vergeml/v1/ai-run' } ).then( function ( r ) {

				if ( r.cron_off ) {
					mode.textContent = text( 'bgCronOff', '' );
					away.hidden = true;
					return;
				}

				if ( ! r.active ) {

					if ( polling ) {
						// It finished while we were watching.
						window.clearInterval( polling );
						polling = 0;
						boot();
					}

					return;
				}

				go.disabled = true;
				away.hidden = true;
				stop.hidden = true;
				cost.say( '' );
				anyway.hidden = true;

				meter.node.hidden = false;
				meter.set( total - r.remaining, total );

				mode.textContent = text( 'bgRunning', '' ) + (
					null === r.next ? '' : ' ' + sprintf( text( 'bgNext', '' ), [ duration( r.next * 1000 ) ] )
				);

				quit.hidden = false;

				if ( ! polling ) {
					polling = window.setInterval( watch, 5000 );
				}

			} ).catch( function () {} );
		}

		var quit = button( text( 'bgStop', 'Stop the background run' ) );

		quit.hidden = true;

		quit.addEventListener( 'click', function () {
			apiFetch( {
				path: '/vergeml/v1/ai-run',
				method: 'POST',
				data: { action: 'stop' },
			} ).then( function () {
				if ( polling ) {
					window.clearInterval( polling );
					polling = 0;
				}
				boot();
			} );
		} );

		away.addEventListener( 'click', function () {
			away.disabled = true;
			apiFetch( {
				path: '/vergeml/v1/ai-run',
				method: 'POST',
				data: { action: 'start', scope: 'unindexed', apply_alt: true },
			} ).then( function () {
				meter.start( done );
				watch();
			} ).catch( fail( note, away ) );
		} );

		/*
		 *  A way past this step without finishing it.
		 *
		 *  Only once something has been described, because "carry on with the
		 *  0 already described" is not an offer. The folders then cover only
		 *  those, which the sentence under it says -- an escape hatch that
		 *  does not explain what you are giving up is a trap.
		 */
		var anyway = el( 'p', 'vgml-lib-skip' );

		anyway.hidden = true;

		function offerAnyway() {

			if ( ! done || anyway.dataset.drawn ) {
				return;
			}

			anyway.dataset.drawn = '1';
			anyway.hidden = false;

			var link = button( sprintf( text( 'goAnyway', '' ), [ String( done ) ] ), 'button-link' );

			link.addEventListener( 'click', function () {
				state.stage = 'unproposed';
				drawLadder();
			} );

			anyway.appendChild( link );
			anyway.appendChild( el( 'span', 'vgml-lib-skip-note', ' ' + text( 'goAnywayNote', '' ) ) );
		}

		apiFetch( { path: '/vergeml/v1/ai-status' } ).then( function ( r ) {

			readStatus( r );

			/*
			 *  Somebody who described two hundred yesterday and came back
			 *  today should see two hundred, not an empty bar. The figure
			 *  survives the reload because it is the library's own count
			 *  rather than anything this page was holding.
			 */
			if ( done > 0 ) {
				meter.node.hidden = false;
			}

			meter.set( done, total );
			offerAnyway();
			watch();

		} ).catch( function () {} );

		var actions = el( 'p', 'vgml-flow-actions' );

		actions.appendChild( go );
		actions.appendChild( away );
		actions.appendChild( stop );
		actions.appendChild( quit );

		wrap.appendChild( cost.node );
		wrap.appendChild( actions );
		wrap.appendChild( meter.node );
		wrap.appendChild( mode );
		wrap.appendChild( note );
		wrap.appendChild( anyway );

		return wrap;
	}

	function rungPropose() {

		var wrap = stepCard();
		var go = button( text( 'ladderRunGo', 'Work out the folders' ), 'button button-primary' );
		var stop = button( text( 'ladderCancel', 'Stop' ) );
		var note = el( 'p', 'vgml-lib-note' );
		var peek = el( 'ul', 'vgml-lib-peek' );
		var meter = progress();
		var runId = 0;
		var opened = 0;

		stop.hidden = true;

		go.addEventListener( 'click', function () {

			go.disabled = true;
			stop.hidden = false;
			note.textContent = '';
			opened = Date.now();
			meter.start( 0 );

			( function step( id ) {
				apiFetch( {
					path: '/vergeml/v1/organize-step',
					method: 'POST',
					data: id ? { run_id: id } : {},
				} ).then( function ( r ) {

					runId = r.run_id;

					/*
					 *  This step counts phases rather than files, so there is
					 *  no "213 of 500" to show. What it does have is an
					 *  estimate the server measured on this host -- so the bar
					 *  is driven by elapsed against elapsed-plus-remaining,
					 *  which is the same claim the estimate makes and no
					 *  stronger.
					 */
					var left = r.estimate && r.estimate.known ? r.estimate.remaining_ms : 0;
					var spent = Date.now() - opened;

					meter.told( left ? spent / ( spent + left ) : 0, left );

					// The partial tree, so stopping early still leaves
					// something to have looked at.
					peek.innerHTML = '';
					( r.partial_tree || [] ).slice( 0, 6 ).forEach( function ( branch ) {
						peek.appendChild( el( 'li', null, branch.label + ' · ' + branch.size ) );
					} );

					if ( ! r.done ) {
						step( r.run_id );
						return;
					}

					boot();
				} ).catch( fail( note, go ) );
			} )( 0 );
		} );

		stop.addEventListener( 'click', function () {
			if ( ! runId ) {
				return;
			}
			apiFetch( {
				path: '/vergeml/v1/organize-cancel',
				method: 'POST',
				data: { run_id: runId },
			} ).then( boot );
		} );

		var cost = figures();

		apiFetch( { path: '/vergeml/v1/ai-status' } ).then( function ( r ) {
			cost.say( sprintf( text( 'costPropose', '' ), [ String( r.indexed || 0 ) ] ) );
		} ).catch( function () {} );

		var actions = el( 'p', 'vgml-flow-actions' );

		actions.appendChild( go );
		actions.appendChild( stop );

		wrap.appendChild( cost.node );
		wrap.appendChild( actions );
		wrap.appendChild( meter.node );
		wrap.appendChild( note );
		wrap.appendChild( peek );

		return wrap;
	}

	function fail( note, go ) {
		return function ( err ) {
			if ( go ) {
				go.disabled = false;
			}
			note.textContent = ( err && err.message ) ? err.message : text( 'failed', 'Request failed.' );
		};
	}

	/* ------------------------------------------------------- the chooser */

	function drawChooser( host ) {

		host.innerHTML = '';

		var item = stepNow();
		var wrap = el( 'div', 'vgml-lib-chooser' );

		// The step's own title and sentence, so this screen is named the same
		// way the rail names it. It had a second heading of its own, which is
		// two answers to "where am I".
		wrap.appendChild( el( 'h2', 'vgml-flow-head', item.title ) );
		wrap.appendChild( el( 'p', 'vgml-flow-note', item.note ) );

		var cards = el( 'div', 'vgml-lib-schemes' );
		wrap.appendChild( cards );
		host.appendChild( wrap );

		apiFetch( { path: '/vergeml/v1/librarian-schemes' } ).then( function ( r ) {

			( r.schemes || [] ).forEach( function ( scheme ) {
				cards.appendChild( drawSchemeCard( scheme ) );
			} );

		} ).catch( function ( err ) {
			cards.appendChild( el( 'p', 'vgml-lib-note',
				( err && err.message ) ? err.message : text( 'failed', '' ) ) );
		} );
	}

	function drawSchemeCard( scheme ) {

		var wrap = el( 'div', 'vgml-ai-card vgml-lib-scheme' );

		wrap.appendChild( el( 'h3', null, scheme.label ) );
		wrap.appendChild( el( 'p', 'description', scheme.note ) );

		if ( null !== scheme.total && undefined !== scheme.total ) {
			wrap.appendChild( el( 'p', 'vgml-lib-scheme-total',
				count( scheme.total, 'schemeTotal' ) ) );
		}

		var list = el( 'ul', 'vgml-lib-scheme-top' );

		( scheme.top || [] ).forEach( function ( branch ) {
			var li = el( 'li', branch.flagged ? 'is-flagged' : null );
			li.appendChild( el( 'span', 'vgml-lib-scheme-name', branch.label ) );
			li.appendChild( el( 'span', 'vgml-lib-scheme-size', String( branch.size ) ) );
			list.appendChild( li );
		} );

		wrap.appendChild( list );

		var go = button(
			scheme.ready ? text( 'choose', 'Look this over' ) : text( 'notReady', 'Not available yet' ),
			'button button-primary'
		);

		go.disabled = ! scheme.ready;

		go.addEventListener( 'click', function () {
			pick( scheme.id, scheme.run_id || 0 );
		} );

		wrap.appendChild( go );

		return wrap;
	}

	function pick( scheme, runId ) {

		state.scheme = scheme;
		state.runId = runId || 0;
		state.edits = {};

		$( 'vgml-lib-stage' ).innerHTML = '';
		$( 'vgml-lib-review' ).innerHTML = '';
		$( 'vgml-lib-counts' ).textContent = text( 'applying', 'Working…' );

		apiFetch( { path: '/vergeml/v1/librarian-schemes?scheme=' + encodeURIComponent( scheme ) } )
			.then( function ( r ) {
				state.tree = r.tree || [];
				state.runId = r.run_id || state.runId;
				drawReview();
			} )
			.catch( function ( err ) {
				$( 'vgml-lib-counts' ).textContent =
					( err && err.message ) ? err.message : text( 'failed', '' );
			} );
	}

	/* -------------------------------------------------------- the review */

	function flagged( branch ) {
		return 'needs-a-look' === branch.key || !! branch.capped;
	}

	function enabled( branch ) {
		var edit = state.edits[ branch.key ];
		return edit ? !! edit.enabled : ! flagged( branch );
	}

	function labelOf( branch ) {
		var edit = state.edits[ branch.key ];
		return ( edit && edit.label ) ? edit.label : branch.label;
	}

	function edits() {

		var out = [];

		state.tree.forEach( function ( branch ) {
			if ( ! branch.members || ! branch.members.length ) {
				return;
			}
			out.push( {
				key: branch.key,
				label: labelOf( branch ),
				enabled: enabled( branch ),
			} );
		} );

		return out;
	}

	function drawReview() {

		var host = $( 'vgml-lib-review' );
		host.innerHTML = '';

		drawSteps();

		var item = stepNow();
		var head = el( 'div', 'vgml-lib-review-head' );

		head.appendChild( el( 'h2', null, item.title ) );

		var back = button( text( 'back', 'Sort a different way instead' ), 'button-link' );
		back.addEventListener( 'click', function () {
			state.tree = [];
			drawLadder();
			$( 'vgml-lib-review' ).innerHTML = '';
			refreshCounts();
		} );
		head.appendChild( back );
		host.appendChild( head );
		host.appendChild( el( 'p', 'vgml-flow-note', item.note ) );

		var grid = el( 'div', 'vgml-lib-branches' );

		state.tree.forEach( function ( branch ) {
			if ( branch.members && branch.members.length ) {
				grid.appendChild( drawBranch( branch ) );
			}
		} );

		host.appendChild( grid );

		var footer = el( 'div', 'vgml-ai-card vgml-lib-preflight' );
		footer.id = 'vgml-lib-preflight';
		host.appendChild( footer );

		thumbs();
		preflight();
		refreshCounts();
	}

	function drawBranch( branch ) {

		var wrap = el( 'div', 'vgml-lib-branch' );

		if ( flagged( branch ) ) {
			wrap.className += ' is-uncertain';
		}

		if ( ! enabled( branch ) ) {
			wrap.className += ' is-refused';
		}

		var head = el( 'div', 'vgml-lib-branch-head' );

		var check = document.createElement( 'input' );
		check.type = 'checkbox';
		check.checked = enabled( branch );
		check.addEventListener( 'change', function () {
			state.edits[ branch.key ] = {
				label: labelOf( branch ),
				enabled: check.checked,
			};
			wrap.className = wrap.className.replace( ' is-refused', '' );
			if ( ! check.checked ) {
				wrap.className += ' is-refused';
			}
			preflight();
		} );
		head.appendChild( check );

		// The name is the folder's name, so it is edited where it is read
		// rather than behind a dialog.
		var name = document.createElement( 'input' );
		name.type = 'text';
		name.className = 'vgml-lib-branch-name';
		name.value = labelOf( branch );
		name.setAttribute( 'aria-label', text( 'rename', 'Folder name' ) );
		name.addEventListener( 'change', function () {
			state.edits[ branch.key ] = {
				label: name.value,
				enabled: check.checked,
			};
			preflight();
		} );
		head.appendChild( name );

		head.appendChild( el( 'span', 'vgml-lib-branch-size',
			count( branch.size, 'branchSize' ) ) );

		wrap.appendChild( head );

		if ( branch.path && branch.path.length > 1 ) {
			wrap.appendChild( el( 'p', 'vgml-lib-branch-path', branch.path.join( ' / ' ) ) );
		}

		if ( flagged( branch ) ) {
			wrap.appendChild( el( 'p', 'vgml-lib-branch-flag',
				branch.capped ? text( 'capped', '' ) : text( 'flagged', '' ) ) );
		}

		if ( branch.reason ) {
			wrap.appendChild( el( 'p', 'vgml-lib-branch-why', branch.reason ) );
		}

		var strip = el( 'div', 'vgml-lib-thumbs' );
		strip.setAttribute( 'data-branch', branch.key );

		// Placeholders, filled by the one batched media request below. Drawn
		// now so the card does not change height when they arrive.
		branch.members.slice( 0, SAMPLES ).forEach( function ( member ) {
			var slot = el( 'span', 'vgml-lib-thumb' );
			slot.setAttribute( 'data-id', String( member.id ) );
			slot.title = member.why || '';
			strip.appendChild( slot );
		} );

		wrap.appendChild( strip );
		wrap.appendChild( drawAgreement( branch ) );

		var refuse = button( text( 'refuse', 'Not this one' ), 'button-link vgml-lib-refuse' );
		refuse.addEventListener( 'click', function () {
			check.checked = false;
			state.edits[ branch.key ] = { label: name.value, enabled: false };
			wrap.className = wrap.className.replace( ' is-refused', '' ) + ' is-refused';
			preflight();
		} );
		wrap.appendChild( refuse );

		return wrap;
	}

	/*
	 *  Three buckets rather than a score. A single number here would be read
	 *  as a confidence figure, and it is not one -- it is how far the files
	 *  in this folder sit from its centre, which is a shape.
	 */
	function drawAgreement( branch ) {

		var wrap = el( 'div', 'vgml-lib-agreement' );
		wrap.title = text( 'agreement', '' );

		var a = branch.agreement || { close: 0, mid: 0, far: 0 };
		var total = ( a.close || 0 ) + ( a.mid || 0 ) + ( a.far || 0 );

		if ( ! total ) {
			return wrap;
		}

		[ [ 'close', a.close ], [ 'mid', a.mid ], [ 'far', a.far ] ].forEach( function ( pair ) {
			if ( ! pair[ 1 ] ) {
				return;
			}
			var part = el( 'span', 'vgml-lib-agreement-' + pair[ 0 ] );
			part.style.width = Math.round( ( pair[ 1 ] / total ) * 100 ) + '%';
			part.title = pair[ 1 ] + ' ' + text( pair[ 0 ], pair[ 0 ] );
			wrap.appendChild( part );
		} );

		return wrap;
	}

	/*
	 *  Six thumbs a branch across forty branches is 240 files, and asking per
	 *  branch would be 40 requests -- the N+1 the query budgets on the server
	 *  exist to prevent, moved into the browser. One request per hundred ids
	 *  instead, which is core's own page cap.
	 */
	function thumbs() {

		var ids = [];

		state.tree.forEach( function ( branch ) {
			( branch.members || [] ).slice( 0, SAMPLES ).forEach( function ( member ) {
				if ( ids.indexOf( member.id ) < 0 ) {
					ids.push( member.id );
				}
			} );
		} );

		if ( ! ids.length ) {
			return;
		}

		var pages = [];

		for ( var i = 0; i < ids.length; i += 100 ) {
			pages.push( ids.slice( i, i + 100 ) );
		}

		pages.forEach( function ( page ) {
			apiFetch( {
				path: '/wp/v2/media?per_page=100&_fields=id,media_details,source_url,title&include=' + page.join( ',' ),
			} ).then( function ( items ) {
				( items || [] ).forEach( paint );
			} ).catch( function () {
				// A thumbnail that will not load is not a reason to stop
				// showing the branch it belongs to.
			} );
		} );
	}

	function paint( item ) {

		var sizes = item.media_details && item.media_details.sizes;
		var src = ( sizes && sizes.thumbnail && sizes.thumbnail.source_url ) || item.source_url;

		if ( ! src ) {
			return;
		}

		var slots = document.querySelectorAll( '.vgml-lib-thumb[data-id="' + item.id + '"]' );

		for ( var i = 0; i < slots.length; i++ ) {

			if ( slots[ i ].firstChild ) {
				continue;
			}

			var img = document.createElement( 'img' );
			img.src = src;
			img.alt = '';
			img.loading = 'lazy';
			slots[ i ].appendChild( img );
		}
	}

	/* ----------------------------------------------------- the pre-flight */

	var preflightTimer = null;

	function preflight() {

		// Every keystroke in a folder name would otherwise be a request; the
		// panel is worth a moment's wait and the server is not worth forty.
		if ( preflightTimer ) {
			window.clearTimeout( preflightTimer );
		}

		preflightTimer = window.setTimeout( askPreflight, 250 );
	}

	function askPreflight() {

		var host = $( 'vgml-lib-preflight' );

		if ( ! host ) {
			return;
		}

		var chosen = edits().filter( function ( e ) {
			return e.enabled;
		} );

		if ( ! chosen.length ) {
			host.innerHTML = '';
			host.appendChild( el( 'h2', null, text( 'preflight', 'What Apply would do' ) ) );
			host.appendChild( el( 'p', 'vgml-lib-note', text( 'applyNothing', 'Nothing is selected.' ) ) );
			return;
		}

		apiFetch( {
			path: '/vergeml/v1/librarian-preflight',
			method: 'POST',
			data: {
				scheme: state.scheme,
				run_id: state.runId,
				branches: edits(),
			},
		} ).then( function ( r ) {
			drawPreflight( r );
		} ).catch( function ( err ) {
			host.innerHTML = '';
			host.appendChild( el( 'h2', null, text( 'preflight', 'What Apply would do' ) ) );
			// The refusal, in the words the endpoint refused with. A screen
			// that paraphrased it would be inventing a second reason.
			host.appendChild( el( 'p', 'vgml-lib-refusal',
				( err && err.message ) ? err.message : text( 'failed', '' ) ) );
		} );
	}

	function drawPreflight( r ) {

		var host = $( 'vgml-lib-preflight' );
		host.innerHTML = '';

		host.appendChild( el( 'h2', null, text( 'preflight', 'What Apply would do' ) ) );

		host.appendChild( el( 'p', 'vgml-lib-count',
			sprintf( text( 'preflightFiles', '%1$s filed, %2$s left alone' ),
				[ String( r.unfiled ), String( r.filed ) ] ) ) );

		host.appendChild( el( 'p', 'vgml-lib-count',
			sprintf( text( 'preflightFolders', '%1$s created, %2$s reused' ),
				[ String( r.folders.create ), String( r.folders.reuse ) ] ) ) );

		host.appendChild( el( 'p', 'vgml-lib-count',
			r.estimate && r.estimate.known
				? sprintf( text( 'preflightTime', 'About %s' ), [ duration( r.estimate.remaining_ms ) ] )
				: text( 'preflightNoTime', '' ) ) );

		/*
		 *  Filing spends nothing, and this says so in words rather than as the
		 *  digit 0.
		 *
		 *  "Costs 0" on a metered product reads as "this is free" -- which is
		 *  untrue of the product and unhelpful about the step. The describing
		 *  was paid for, separately, before any of this; what is about to
		 *  happen is only moving files between folders.
		 */
		host.appendChild( el( 'p', 'vgml-lib-credits',
			conf.mock ? text( 'creditsMock', '' ) : text( 'creditsNone', '' ) ) );

		if ( r.credits && ! r.credits.allow && r.credits.reason ) {
			host.appendChild( el( 'p', 'vgml-lib-refusal', r.credits.reason ) );
		}

		var go = button( text( 'apply', 'Apply' ), 'button button-primary vgml-lib-apply' );
		go.disabled = ! r.unfiled;
		go.addEventListener( 'click', function () {
			go.disabled = true;
			apply();
		} );

		host.appendChild( go );
		host.appendChild( el( 'div', 'vgml-import-bar vgml-lib-progress', null ) );
		host.appendChild( el( 'p', 'vgml-lib-note', null ) );
	}

	/* ------------------------------------------------------------- apply */

	function progressNodes() {

		var host = $( 'vgml-lib-preflight' );

		return {
			bar: host.querySelector( '.vgml-lib-progress' ),
			note: host.querySelector( '.vgml-lib-note' ),
		};
	}

	function apply() {

		var nodes = progressNodes();

		nodes.bar.innerHTML = '';

		var fill = el( 'div', 'vgml-import-fill' );
		nodes.bar.appendChild( fill );

		state.running = true;
		state.paused = false;
		state.batchId = 0;

		var stop = button( text( 'pause', 'Pause' ) );
		stop.addEventListener( 'click', function () {
			if ( ! state.batchId ) {
				return;
			}
			apiFetch( {
				path: '/vergeml/v1/librarian-pause',
				method: 'POST',
				data: { batch_id: state.batchId },
			} );
			state.paused = true;
		} );

		nodes.note.parentNode.insertBefore( stop, nodes.note );

		( function step() {

			var data = state.batchId
				? { batch_id: state.batchId }
				: { scheme: state.scheme, run_id: state.runId, branches: edits() };

			apiFetch( {
				path: '/vergeml/v1/librarian-apply-step',
				method: 'POST',
				data: data,
			} ).then( function ( r ) {

				state.batchId = r.batch_id;

				var did = r.n ? Math.round( ( r.cursor / r.n ) * 100 ) : 100;
				fill.style.width = did + '%';

				if ( 'paused' === r.status ) {
					// A gate refusal and a person pressing Pause end up here
					// alike, and both are told why rather than just stopped.
					state.running = false;
					stop.textContent = text( 'resume', 'Resume' );
					nodes.note.textContent = r.reason || text( 'paused', 'Paused.' );
					resumable( stop );
					history();
					return;
				}

				if ( 'done' === r.status ) {
					state.running = false;
					stop.remove();
					fill.style.width = '100%';
					nodes.note.textContent = sprintf(
						text( 'applied', 'Done. %1$s filed, %2$s left alone.' ),
						[ String( r.done ), String( r.skipped ) ]
					);
					history();
					refreshCounts();
					return;
				}

				nodes.note.textContent = sprintf( text( 'remaining', '%s to go' ), [ String( r.remaining ) ] ) +
					( r.estimate && r.estimate.known ? ' · ' + duration( r.estimate.remaining_ms ) : '' );

				if ( state.paused ) {
					return;
				}

				step();

			} ).catch( function ( err ) {
				state.running = false;
				nodes.note.textContent = ( err && err.message ) ? err.message : text( 'failed', '' );
				history();
			} );
		} )();
	}

	// A paused batch resumes exactly where it stopped: the cursor is on the
	// row, so this is the same call the loop was already making.
	function resumable( stop ) {

		var fresh = stop.cloneNode( true );
		stop.parentNode.replaceChild( fresh, stop );

		fresh.addEventListener( 'click', function () {
			state.paused = false;
			apply();
		} );
	}

	/* -------------------------------------------------------------- undo */

	function history() {

		var host = $( 'vgml-lib-history' );

		apiFetch( { path: '/vergeml/v1/librarian-batches' } ).then( function ( r ) {

			host.innerHTML = '';

			var wrap = el( 'div', 'vgml-ai-card vgml-lib-history' );
			wrap.appendChild( el( 'h2', null, text( 'history', 'What has been applied' ) ) );

			if ( ! r.batches || ! r.batches.length ) {
				wrap.appendChild( el( 'p', 'vgml-lib-note', text( 'noHistory', '' ) ) );
				host.appendChild( wrap );
				return;
			}

			var list = el( 'ul', 'vgml-lib-batches' );

			r.batches.forEach( function ( batch ) {
				list.appendChild( drawBatch( batch ) );
			} );

			wrap.appendChild( list );
			host.appendChild( wrap );

		} ).catch( function () {
			host.innerHTML = '';
		} );
	}

	/*
	 *  The scheme's own name, not its slug. The server calls them "datetype"
	 *  and "subject" because those are keys; nobody reading a history wants a
	 *  key.
	 */
	var SCHEME_NAMES = {
		datetype: text( 'schemeDate', 'by date and file type' ),
		subject: text( 'schemeSubject', 'by what is in the pictures' )
	};

	/** "3 days ago" beats a timestamp to the second for something you are
	 *  only trying to place in your own week. */
	function ago( stamp ) {

		var then = Date.parse( String( stamp ).replace( ' ', 'T' ) + 'Z' );

		if ( isNaN( then ) ) {
			return String( stamp );
		}

		var mins = Math.max( 0, Math.round( ( Date.now() - then ) / 60000 ) );

		if ( mins < 2 ) { return text( 'justNow', 'just now' ); }
		if ( mins < 60 ) { return sprintf( text( 'minsAgo', '%s minutes ago' ), [ String( mins ) ] ); }

		var hours = Math.round( mins / 60 );
		if ( hours < 24 ) { return sprintf( text( 'hoursAgo', '%s hours ago' ), [ String( hours ) ] ); }

		return sprintf( text( 'daysAgo', '%s days ago' ), [ String( Math.round( hours / 24 ) ) ] );
	}

	function drawBatch( batch ) {

		var li = el( 'li', 'vgml-lib-batch' );
		li.setAttribute( 'data-batch', String( batch.batch_id ) );

		/*
		 *  A sentence, not a database row.
		 *
		 *  This printed five fields side by side --
		 *  "2026-08-29 14:16:56  datetype  5 filed · 56 left alone  done" --
		 *  which is the batches table with spaces between the columns.
		 *  "datetype" is not a word anybody types.
		 */
		var scheme = ( SCHEME_NAMES[ batch.scheme ] || batch.scheme );

		li.appendChild( el( 'span', 'vgml-lib-batch-line',
			sprintf( text( 'batchLine', 'Sorted %1$s files %2$s. %3$s were left where they were.' ),
				[ String( batch.done ), scheme, String( batch.skipped ) ] ) ) );

		li.appendChild( el( 'span', 'vgml-lib-batch-when', ago( batch.created_at ) ) );

		if ( 'undone' === batch.status ) {
			li.appendChild( el( 'span', 'vgml-lib-batch-status', text( 'wasUndone', 'Undone' ) ) );
		}

		if ( batch.reason ) {
			li.appendChild( el( 'span', 'vgml-lib-batch-reason', batch.reason ) );
		}

		var note = el( 'p', 'vgml-lib-note' );

		if ( 'undone' !== batch.status && batch.done > 0 ) {

			var go = button( text( 'undo', 'Undo' ) );

			go.addEventListener( 'click', function () {
				go.disabled = true;
				note.textContent = text( 'undoing', 'Putting it back…' );
				undo( batch.batch_id, note );
			} );

			li.appendChild( go );
		}

		li.appendChild( note );

		return li;
	}

	function undo( batchId, note ) {

		( function step() {
			apiFetch( {
				path: '/vergeml/v1/librarian-undo-step',
				method: 'POST',
				data: { batch_id: batchId },
			} ).then( function ( r ) {

				if ( 'undone' !== r.status ) {
					note.textContent = sprintf( text( 'remaining', '%s to go' ), [ String( r.remaining ) ] );
					step();
					return;
				}

				/*
				 *  The report, shown rather than summarised away. What undo
				 *  did NOT do is the part worth reading: files somebody has
				 *  moved since are theirs now, and folders that have picked
				 *  up content are kept -- and both of those are surprises if
				 *  they are not said out loud.
				 */
				note.innerHTML = '';
				note.appendChild( el( 'span', null, sprintf(
					text( 'undone', 'Put back. %1$s unfiled, %2$s folders removed.' ),
					[ String( r.undone ), String( r.folders_removed ) ]
				) ) );

				if ( r.skipped_touched > 0 ) {
					note.appendChild( el( 'span', 'vgml-lib-undo-note',
						sprintf( text( 'undoTouched', '%s files were left as they are.' ),
							[ String( r.skipped_touched ) ] ) ) );
				}

				if ( r.folders_kept > 0 ) {
					note.appendChild( el( 'span', 'vgml-lib-undo-note',
						sprintf( text( 'undoKept', '%s folders were kept.' ),
							[ String( r.folders_kept ) ] ) ) );
				}

				history();
				refreshCounts();

			} ).catch( function ( err ) {
				note.textContent = ( err && err.message ) ? err.message : text( 'failed', '' );
			} );
		} )();
	}

	/* -------------------------------------------------------------- boot */

	function refreshCounts() {

		apiFetch( { path: '/vergeml/v1/librarian-schemes' } ).then( function ( r ) {

			if ( ! r.taxonomy ) {
				return;
			}

			return apiFetch( { path: '/vergeml/v1/tree?taxonomy=' + encodeURIComponent( r.taxonomy ) } )
				.then( function ( tree ) {
					$( 'vgml-lib-counts' ).textContent = sprintf(
						text( 'counts', '%1$s files to file · %2$s folders' ),
						[ String( tree.unassigned ), String( ( tree.nodes || [] ).length ) ]
					);
				} );

		} ).catch( function () {
			$( 'vgml-lib-counts' ).textContent = '';
		} );
	}

	/*
	 *  Which rung this library is on, asked of the endpoints rather than
	 *  trusted from the page load: the scan the user just ran in this tab
	 *  moved them, and a stage decided server-side at render time would still
	 *  be reporting where they were before they pressed the button.
	 */
	function boot() {

		$( 'vgml-lib-stage' ).innerHTML = '';
		$( 'vgml-lib-review' ).innerHTML = '';

		Promise.all( [
			apiFetch( { path: '/vergeml/v1/health-report' } ).catch( function () {
				return { scanned: false };
			} ),
			apiFetch( { path: '/vergeml/v1/ai-status' } ).catch( function () {
				return { indexed: 0 };
			} ),
			apiFetch( { path: '/vergeml/v1/librarian-schemes' } ).catch( function () {
				return { schemes: [] };
			} ),
		] ).then( function ( answers ) {

			var health = answers[ 0 ];
			var ai = answers[ 1 ];
			var schemes = answers[ 2 ];

			var subject = ( schemes.schemes || [] ).filter( function ( s ) {
				return 'subject' === s.id;
			} )[ 0 ];

			/*
			 *  The same order vergeml_librarian_stage() uses, and it has to
			 *  stay the same order: the server decides what the home card
			 *  says, this decides what the screen shows, and the two
			 *  disagreeing is somebody being told to do a step that is not
			 *  the one in front of them.
			 *
			 *  'unindexed' is anything still to describe, not "nothing has
			 *  been described" -- see the note on the PHP.
			 */
			if ( ! health.scanned ) {
				state.stage = 'unscanned';
			} else if ( subject && subject.ready ) {
				state.stage = 'ready';
			} else if ( Number( ai.unindexed ) > 0 ) {
				state.stage = 'unindexed';
			} else {
				state.stage = 'unproposed';
			}

			drawLadder();
			refreshCounts();
			history();
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
