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

	var state = {
		stage: conf.stage || 'unscanned',
		scheme: '',
		runId: 0,
		tree: [],
		edits: {},      // branch key -> { label, enabled }
		batchId: 0,
		running: false,
		paused: false,
	};

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
	 *  Each rung drives the loop that already exists for its step, rather
	 *  than a second implementation of it living here: the duplicate scan is
	 *  /health-scan, describing is /ai-index, proposing is /organize-step.
	 *  Three loops, one shape, and this screen only supplies the progress.
	 */
	function drawLadder() {

		var host = $( 'vgml-lib-stage' );
		host.innerHTML = '';

		if ( 'unscanned' === state.stage ) {
			host.appendChild( rungScan() );
			return;
		}

		if ( 'unindexed' === state.stage ) {
			host.appendChild( rungIndex() );
			host.appendChild( datePathNote() );
			return;
		}

		if ( 'unproposed' === state.stage ) {
			host.appendChild( rungPropose() );
			host.appendChild( datePathNote() );
			return;
		}

		drawChooser( host );
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

		var wrap = card( text( 'ladderScan', 'Read the library first' ), text( 'ladderScanNote', '' ) );
		var go = button( text( 'ladderScanGo', 'Scan the library' ), 'button button-primary' );
		var note = el( 'p', 'vgml-lib-note' );
		var progress = bar( 'vgml-lib-rung-fill' );

		go.addEventListener( 'click', function () {

			go.disabled = true;
			progress.hidden = false;
			note.textContent = text( 'applying', 'Working…' );

			( function step( cursor, reset ) {
				apiFetch( {
					path: '/vergeml/v1/health-scan',
					method: 'POST',
					data: { cursor: cursor, reset: reset },
				} ).then( function ( r ) {

					note.textContent = sprintf( text( 'remaining', '%s to go' ), [ String( r.remaining ) ] );

					var total = r.total || ( r.remaining + r.hashed );
					$( 'vgml-lib-rung-fill' ).style.width =
						total ? Math.round( ( ( total - r.remaining ) / total ) * 100 ) + '%' : '100%';

					if ( ! r.done ) {
						step( r.cursor, false );
						return;
					}

					boot();
				} ).catch( fail( note, go ) );
			} )( 0, true );
		} );

		wrap.appendChild( go );
		wrap.appendChild( progress );
		wrap.appendChild( note );

		return wrap;
	}

	function rungIndex() {

		var wrap = card( text( 'ladderIndex', 'Describe the pictures' ), text( 'ladderIndexNote', '' ) );
		var go = button( text( 'ladderIndexGo', 'Describe them' ), 'button button-primary' );
		var note = el( 'p', 'vgml-lib-note' );

		go.addEventListener( 'click', function () {

			go.disabled = true;
			note.textContent = text( 'applying', 'Working…' );

			( function step() {
				apiFetch( {
					path: '/vergeml/v1/ai-index',
					method: 'POST',
					data: { scope: 'unindexed', limit: 5, apply_alt: true },
				} ).then( function ( r ) {

					var left = r.remaining || 0;
					var moved = ( r.described || [] ).length + ( r.errors || [] ).length;

					note.textContent = sprintf( text( 'remaining', '%s to go' ), [ String( left ) ] );

					// The same terminating condition vergeml-ai.js uses: stop
					// when there is nothing left, and stop when a pass moved
					// nothing -- otherwise a file that keeps failing is an
					// infinite loop rather than a report.
					if ( left > 0 && moved ) {
						step();
						return;
					}

					boot();
				} ).catch( fail( note, go ) );
			} )();
		} );

		wrap.appendChild( go );
		wrap.appendChild( note );

		return wrap;
	}

	function rungPropose() {

		var wrap = card( text( 'ladderRun', 'Propose a tree' ), text( 'ladderRunNote', '' ) );
		var go = button( text( 'ladderRunGo', 'Propose a tree' ), 'button button-primary' );
		var stop = button( text( 'ladderCancel', 'Stop' ) );
		var note = el( 'p', 'vgml-lib-note' );
		var peek = el( 'ul', 'vgml-lib-peek' );
		var runId = 0;

		stop.hidden = true;

		go.addEventListener( 'click', function () {

			go.disabled = true;
			stop.hidden = false;
			note.textContent = text( 'applying', 'Working…' );

			( function step( id ) {
				apiFetch( {
					path: '/vergeml/v1/organize-step',
					method: 'POST',
					data: id ? { run_id: id } : {},
				} ).then( function ( r ) {

					runId = r.run_id;

					note.textContent = r.estimate && r.estimate.known
						? sprintf( text( 'remaining', '%s to go' ), [ String( r.remaining ) ] ) +
							' · ' + duration( r.estimate.remaining_ms )
						: sprintf( text( 'remaining', '%s to go' ), [ String( r.remaining ) ] );

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

		wrap.appendChild( go );
		wrap.appendChild( stop );
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

		var wrap = el( 'div', 'vgml-lib-chooser' );
		wrap.appendChild( el( 'h2', null, text( 'chooser', 'How should this library be filed?' ) ) );

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
				sprintf( text( 'schemeTotal', '%s files' ), [ String( scheme.total ) ] ) ) );
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

		var head = el( 'div', 'vgml-lib-review-head' );
		head.appendChild( el( 'h2', null, text( 'review', 'The proposed folders' ) ) );

		var back = button( text( 'back', 'Choose a different scheme' ), 'button-link' );
		back.addEventListener( 'click', function () {
			state.tree = [];
			drawLadder();
			$( 'vgml-lib-review' ).innerHTML = '';
			refreshCounts();
		} );
		head.appendChild( back );
		host.appendChild( head );

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
			sprintf( text( 'branchSize', '%s files' ), [ String( branch.size ) ] ) ) );

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

		// Zero, and said to be mock. The service is not live and a figure
		// invented for the sake of having one would undo the point of a panel
		// whose whole argument is that it counts rather than estimates.
		host.appendChild( el( 'p', 'vgml-lib-credits', text( 'credits', '' ) ) );

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

	function drawBatch( batch ) {

		var li = el( 'li', 'vgml-lib-batch' );
		li.setAttribute( 'data-batch', String( batch.batch_id ) );

		li.appendChild( el( 'span', 'vgml-lib-batch-when', batch.created_at ) );
		li.appendChild( el( 'span', 'vgml-lib-batch-scheme', batch.scheme ) );
		li.appendChild( el( 'span', 'vgml-lib-batch-count',
			sprintf( text( 'batchCount', '%1$s filed · %2$s left alone' ),
				[ String( batch.done ), String( batch.skipped ) ] ) ) );
		li.appendChild( el( 'span', 'vgml-lib-batch-status', batch.status ) );

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

			if ( ! health.scanned ) {
				state.stage = 'unscanned';
			} else if ( subject && subject.ready ) {
				state.stage = 'ready';
			} else if ( ! ai.indexed ) {
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
