/*
 *  The import screen.
 *
 *  One card per source, in one list: the sources with folders here first, the
 *  ones with nothing in a line beneath them, then the spreadsheet in its own
 *  section. A card says what it holds, what pressing the button will do, and
 *  carries one button that says the same number. There is no preview step --
 *  the outcome line comes down with the card, so the confirmation is the
 *  button itself.
 *
 *  The run is chunked and the browser drives the loop, because sixteen thousand
 *  assignments will not finish inside one PHP request on the kind of host this
 *  mostly runs on. Each pass returns a resume token, which is handed straight
 *  back; nothing here has to understand what is in it. Progress is drawn inside
 *  the button that started it, so the thing that was pressed is the thing that
 *  reports.
 */

( function () {
	'use strict';

	var app = document.getElementById( 'vgml-import-app' );

	if ( ! app || ! window.wp || ! wp.apiFetch || ! window.vergemlImport ) {
		return;
	}

	var base = window.vergemlImport;
	var l10n = base.l10n;
	var taxonomies = [];

	try {
		taxonomies = JSON.parse( app.getAttribute( 'data-taxonomies' ) ) || [];
	} catch ( e ) {
		taxonomies = [];
	}

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( k ) {
				if ( k === 'class' ) { node.className = attrs[ k ]; }
				else if ( attrs[ k ] !== null && attrs[ k ] !== undefined ) { node.setAttribute( k, attrs[ k ] ); }
			} );
		}
		if ( text !== undefined && text !== null ) {
			node.appendChild( document.createTextNode( text ) );
		}
		return node;
	}

	/*
	 *  Sixteen thousand rather than 16000. These counts are the whole point of the
	 *  screen -- they are what tells somebody the importer is looking at their real
	 *  library -- and an unseparated five-digit number has to be counted by eye.
	 */
	function n( value ) {
		return Number( value || 0 ).toLocaleString();
	}

	function sprintf( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s ).replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			return pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ];
		} );
	}

	/*
	 *  What the button will do, in words. The merge clause is dropped when
	 *  nothing merges: "0 merged into folders you already have" is a sentence
	 *  about something that did not happen.
	 */
	function outcome( plan ) {
		return plan.merge
			? sprintf( l10n.plan, n( plan.create ), n( plan.merge ), n( plan.assignments ), plan.label )
			: sprintf( l10n.planPlain, n( plan.create ), n( plan.assignments ), plan.label );
	}

	var call = function ( data ) {
		return wp.apiFetch( { path: '/vergeml/v1/import', method: 'POST', data: data } );
	};

	/* ------------------------------------------------------------- states */

	/*
	 *  A message that survives the redraw.
	 *
	 *  The result used to be written into the card's own output area and then
	 *  refresh() rebuilt the whole screen underneath it, so "Imported." appeared
	 *  and vanished in the same frame. Anything worth telling somebody has to
	 *  outlive the render that follows it.
	 */
	var flash = null;

	/*
	 *  What an import that has just finished did, by source key.
	 *
	 *  The card stays where it was and states the fact in place, so the number
	 *  is where the person was looking when they pressed the button. The source
	 *  still holds its folders -- nothing is taken from it -- so without this
	 *  the card would redraw as "Import 14 folders" the instant it finished.
	 */
	var finished = {};

	var facts = null;

	function render( sources, history ) {

		app.innerHTML = '';

		if ( flash ) {
			var note = el( 'div', { class: 'notice notice-' + ( flash.bad ? 'error' : 'success' ) } );
			note.appendChild( el( 'p', { class: flash.bad ? 'vgml-import-bad' : 'vgml-import-good' }, flash.text ) );
			if ( flash.detail ) {
				note.appendChild( el( 'p', { class: 'description' }, flash.detail ) );
			}
			app.appendChild( note );
			flash = null;
		}

		var plugins = sources.filter( function ( s ) { return s.key !== 'csv'; } );
		var csv = sources.filter( function ( s ) { return s.key === 'csv'; } )[ 0 ] || null;

		/*
		 *  The ones that found something first, then the rest by name. A list
		 *  in registry order puts the only card that can be pressed halfway
		 *  down a list of names.
		 */
		plugins.sort( function ( a, b ) {
			if ( ( a.folders > 0 ) !== ( b.folders > 0 ) ) {
				return a.folders > 0 ? -1 : 1;
			}
			return a.name.localeCompare( b.name );
		} );

		var found = plugins.filter( function ( s ) { return s.folders > 0; } );
		var empty = plugins.filter( function ( s ) { return ! s.folders; } );

		var box = el( 'div', { class: 'vgml-section vgml-import-sources' } );
		box.appendChild( el( 'h6', { class: 'vgml-kicker' }, l10n.plugins ) );
		box.appendChild( el( 'p', { class: 'vgml-note' }, l10n.pluginsNote ) );

		var list = el( 'div', { class: 'vgml-srcs' } );
		found.forEach( function ( source ) {
			list.appendChild( sourceCard( source ) );
		} );
		box.appendChild( list );

		if ( empty.length ) {
			box.appendChild( alsoRead( empty ) );
		}

		app.appendChild( box );
		app.appendChild( csvSection( csv ) );
		app.appendChild( exportSection() );

		if ( history && history.length ) {
			app.appendChild( historyBox( history ) );
		}
	}

	/*
	 *  The sources with nothing on this site, as one line.
	 *
	 *  A card each spends six rows on the same sentence above the one card that
	 *  can be acted on. The promise the list makes -- every plugin we read is
	 *  named, whether or not it has anything -- is kept in a sentence, and the
	 *  sentence ends with what somebody whose old plugin is switched off needs
	 *  to know.
	 */
	function alsoRead( empty ) {

		var line = el( 'p', { class: 'vgml-note vgml-srcs-also' } );
		var names = el( 'span' );

		empty.forEach( function ( source, i ) {
			if ( i ) {
				names.appendChild( document.createTextNode( i === empty.length - 1 ? l10n.listAnd : l10n.listComma ) );
			}
			names.appendChild( el( 'b', {}, source.qualify ? sprintf( l10n.nameBy, source.name, source.author ) : source.name ) );
		} );

		// The list is a run of elements, so the sentence is split around it
		// rather than built as a string.
		var parts = String( l10n.alsoRead ).split( '%s' );
		line.appendChild( document.createTextNode( parts[ 0 ] ) );
		line.appendChild( names );
		line.appendChild( document.createTextNode( parts.length > 1 ? parts[ 1 ] : '' ) );

		return line;
	}

	/*
	 *  One source: what it holds, what the button will do, and the button.
	 *
	 *  The taxonomy select is drawn only where there is a choice to make. On the
	 *  one-taxonomy site that is nearly everybody, the destination is named in
	 *  the sentence instead -- "filed into Media Categories" -- which is the
	 *  thing the select was silently answering.
	 */
	function sourceCard( source ) {

		var card = el( 'article', { class: 'vgml-src is-found', 'data-source': source.key } );

		var head = el( 'div', { class: 'vgml-src-head' } );
		head.appendChild( el( 'h2', {}, source.name ) );
		head.appendChild( el( 'span', { class: 'vgml-src-by' }, source.author ) );
		card.appendChild( head );

		var has = el( 'p', { class: 'vgml-src-has' },
			sprintf( l10n.summary, n( source.folders ), n( source.files ) ) );
		card.appendChild( has );

		var does = el( 'p', { class: 'vgml-src-does', 'aria-live': 'polite' } );
		card.appendChild( does );

		var act = el( 'div', { class: 'vgml-src-act' } );
		card.appendChild( act );

		var done = finished[ source.key ];

		if ( done ) {

			has.textContent = sprintf( l10n.doneLine, n( done.created ), n( done.assignments ) );
			does.textContent = facts ? sprintf( l10n.doneNow, n( facts.folders ), n( facts.unfiled ) ) : '';

			var back = el( 'button', { type: 'button', class: 'button' }, sprintf( l10n.undo, n( done.created ) ) );
			back.addEventListener( 'click', function () {
				undoInto( back, done.id, function () {
					delete finished[ source.key ];
				} );
			} );
			act.appendChild( back );

			return card;
		}

		if ( ! source.plan ) {
			return card;
		}

		var plan = source.plan;
		var pick = null;

		does.textContent = outcome( plan );

		if ( taxonomies.length > 1 ) {
			act.appendChild( el( 'label', { class: 'vgml-src-label', for: 'tax-' + source.key }, l10n.importInto ) );
			pick = el( 'select', { id: 'tax-' + source.key, class: 'vgml-input' } );
			taxonomies.forEach( function ( t ) {
				pick.appendChild( el( 'option', { value: t.name, selected: t.name === plan.taxonomy ? 'selected' : null }, t.label ) );
			} );
			// The numbers came down for one destination. Choosing another asks
			// for that one's, rather than printing the first one's beside it.
			pick.addEventListener( 'change', function () {
				does.textContent = '';
				call( { action: 'plan', source: source.key, taxonomy: pick.value } ).then( function ( fresh ) {
					plan = { taxonomy: pick.value, label: pick.options[ pick.selectedIndex ].textContent,
						create: fresh.create, merge: fresh.merge, assignments: fresh.assignments, files: fresh.files };
					does.textContent = outcome( plan );
				} );
			} );
			act.appendChild( pick );
		}

		var go = el( 'button', { type: 'button', class: 'button button-primary vgml-src-go' },
			sprintf( l10n.importGo, n( source.folders ) ) );

		go.addEventListener( 'click', function () {
			if ( pick ) {
				pick.disabled = true;
			}
			runImport( source, plan, go, does );
		} );

		act.appendChild( go );

		return card;
	}

	/*
	 *  The chunk loop, reporting inside its own button.
	 *
	 *  The bar moves on real numbers from the server rather than on a guess,
	 *  because an import of sixteen thousand files is long enough that a fake
	 *  progress bar becomes a lie somebody notices.
	 */
	function runImport( source, plan, go, does ) {

		go.disabled = true;
		go.classList.add( 'is-going' );

		var fill = el( 'i', { class: 'vgml-src-fill', 'aria-hidden': 'true' } );
		var label = el( 'span', { class: 'vgml-src-going' }, sprintf( l10n.working, n( 0 ), n( plan.assignments ) ) );

		go.innerHTML = '';
		go.appendChild( fill );
		go.appendChild( label );

		function step( resume ) {

			var data = { action: 'run', source: source.key, taxonomy: plan.taxonomy };

			if ( resume ) {
				data.resume = resume;
			}

			call( data ).then( function ( result ) {

				var total = result.total || plan.assignments || 1;
				var done = result.done || 0;

				fill.style.width = Math.min( 100, Math.round( ( done / total ) * 100 ) ) + '%';
				label.textContent = sprintf( l10n.working, n( done ), n( total ) );
				does.textContent = sprintf( l10n.madeSoFar, n( result.created ) );

				if ( ! result.complete && result.resume ) {
					step( result.resume );
					return;
				}

				finished[ source.key ] = {
					id: result.id,
					created: result.created,
					merged: result.merged,
					assignments: result.assignments,
				};

				refresh();

			} ).catch( function ( err ) {
				go.classList.remove( 'is-going' );
				go.disabled = false;
				go.textContent = sprintf( l10n.importGo, n( source.folders ) );
				does.textContent = ( err && err.message ) || l10n.failed;
				does.classList.add( 'vgml-import-bad' );
			} );
		}

		step( null );
	}

	/*
	 *  Undo, from a card or from a history row. Chunked the same way the import
	 *  is and driven the same way from here; the button carries the count so a
	 *  long undo does not look like a button that stopped responding.
	 */
	function undoInto( button, id, before ) {

		var was = button.textContent;

		button.disabled = true;
		button.textContent = l10n.undoing;

		function step( resume ) {

			var data = { action: 'undo', id: id };

			if ( resume ) {
				data.resume = resume;
			}

			call( data ).then( function ( result ) {

				if ( ! result.complete && result.resume ) {
					button.textContent = sprintf( l10n.working, n( result.done ), n( result.total ) );
					step( result.resume );
					return;
				}

				if ( before ) {
					before();
				}

				flash = { text: sprintf( l10n.undone, n( result.removed ), n( result.unassigned ) ) };
				refresh();

			} ).catch( function ( err ) {
				button.disabled = false;
				button.textContent = was;
				flash = { bad: true, text: ( err && err.message ) || l10n.failed };
				refresh();
			} );
		}

		step( null );
	}

	/*
	 *  Reading a spreadsheet in, as a card in the same list.
	 *
	 *  Always shown, unlike a plugin card: the plugin cards answer "what is
	 *  there to import from", and this one is the offer to make something to
	 *  import from. Once a file has been read it carries the same outcome line
	 *  and the same button as any other source, and the picker stays beside it
	 *  so a different file can replace it.
	 */
	function csvSection( staged ) {

		var box = el( 'div', { class: 'vgml-section vgml-import-file-in' } );
		box.appendChild( el( 'h6', { class: 'vgml-kicker' }, l10n.spreadsheet ) );

		var list = el( 'div', { class: 'vgml-srcs' } );
		var card = el( 'article', { class: 'vgml-src is-file' } );

		var head = el( 'div', { class: 'vgml-src-head' } );
		head.appendChild( el( 'h2', {}, l10n.fileTitle ) );
		head.appendChild( el( 'span', { class: 'vgml-src-by' }, l10n.fileBy ) );
		card.appendChild( head );

		var has = el( 'p', { class: 'vgml-src-has' },
			staged && staged.folders
				? sprintf( l10n.summary, n( staged.folders ), n( staged.files ) )
				: l10n.fileWhat );
		card.appendChild( has );

		var does = el( 'p', { class: 'vgml-src-does', 'aria-live': 'polite' } );
		card.appendChild( does );

		var act = el( 'div', { class: 'vgml-src-act' } );

		var file = el( 'input', { type: 'file', accept: '.csv,text/csv', 'aria-label': l10n.pickFile, class: 'vgml-import-file' } );
		var note = el( 'span', { class: 'vgml-muted vgml-src-note' }, l10n.noFile );

		var done = staged ? finished.csv : null;

		if ( done ) {

			has.textContent = sprintf( l10n.doneLine, n( done.created ), n( done.assignments ) );
			does.textContent = facts ? sprintf( l10n.doneNow, n( facts.folders ), n( facts.unfiled ) ) : '';

			var back = el( 'button', { type: 'button', class: 'button' }, sprintf( l10n.undo, n( done.created ) ) );
			back.addEventListener( 'click', function () {
				undoInto( back, done.id, function () {
					delete finished.csv;
					call( { action: 'csv-clear' } );
				} );
			} );
			act.appendChild( back );

		} else if ( staged && staged.plan ) {

			does.textContent = outcome( staged.plan );

			var go = el( 'button', { type: 'button', class: 'button button-primary vgml-src-go' },
				sprintf( l10n.importGo, n( staged.folders ) ) );
			go.addEventListener( 'click', function () {
				runImport( staged, staged.plan, go, does );
			} );
			act.appendChild( go );
		}

		var choose = el( 'button', { type: 'button', class: staged ? 'button' : 'button button-primary' }, l10n.pickFile );
		choose.addEventListener( 'click', function () { file.click(); } );

		act.appendChild( choose );
		act.appendChild( note );
		act.appendChild( file );
		card.appendChild( act );

		file.addEventListener( 'change', function () {

			var chosen = file.files && file.files[ 0 ];

			if ( ! chosen ) {
				return;
			}

			note.textContent = l10n.reading;

			var reader = new FileReader();

			reader.onerror = function () {
				note.textContent = l10n.unreadable;
			};

			reader.onload = function () {
				call( { action: 'csv', text: String( reader.result || '' ) } )
					.then( function ( r ) {
						delete finished.csv;
						flash = {
							bad: false,
							text: sprintf( l10n.staged, n( r.folders ), n( r.files ) ),
							detail: r.skipped
								? sprintf( l10n.stagedSkips, n( r.skipped ) ) + ' ' + ( r.problems || [] ).join( ' ' )
								: ''
						};
						refresh();
					} )
					.catch( function ( err ) {
						flash = { bad: true, text: ( err && err.message ) || l10n.unreadable, detail: '' };
						refresh();
					} );
			};

			// Read as text, not as a data URL: the file never leaves the
			// browser as bytes and nothing is written to wp-content.
			reader.readAsText( chosen );
		} );

		list.appendChild( card );
		box.appendChild( list );

		return box;
	}

	/*
	 *  Writing one out: its own section, because exporting is not importing and
	 *  the two side by side made the spreadsheet read as the main road.
	 */
	function exportSection() {

		var box = el( 'div', { class: 'vgml-section vgml-import-file-out' } );
		box.appendChild( el( 'h6', { class: 'vgml-kicker' }, l10n.export ) );

		var list = el( 'div', { class: 'vgml-srcs' } );
		var card = el( 'article', { class: 'vgml-src is-file' } );

		var head = el( 'div', { class: 'vgml-src-head' } );
		head.appendChild( el( 'h2', {}, taxonomies.length ? taxonomies[ 0 ].label : l10n.export ) );

		if ( facts ) {
			head.appendChild( el( 'span', { class: 'vgml-src-by' },
				sprintf( l10n.summary, n( facts.folders ), n( facts.filed ) ) ) );
		}

		card.appendChild( head );
		card.appendChild( el( 'p', { class: 'vgml-src-has' }, l10n.exportWhatNote ) );

		var act = el( 'div', { class: 'vgml-src-act' } );
		var pick = null;

		if ( taxonomies.length > 1 ) {
			pick = el( 'select', { class: 'vgml-import-tax vgml-input', 'aria-label': l10n.export } );
			taxonomies.forEach( function ( t ) {
				pick.appendChild( el( 'option', { value: t.name }, t.label ) );
			} );
			act.appendChild( pick );
		}

		var down = el( 'a', { class: 'button', href: '#' }, l10n.exportGo );
		down.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			// The nonce is already on the base URL; only the taxonomy varies.
			var taxonomy = pick ? pick.value : ( taxonomies[ 0 ] && taxonomies[ 0 ].name );
			window.location.href = base.exportUrl + '&taxonomy=' + encodeURIComponent( taxonomy || '' );
		} );

		act.appendChild( down );
		card.appendChild( act );
		list.appendChild( card );
		box.appendChild( list );

		return box;
	}

	/*
	 *  What has been imported, named and dated.
	 *
	 *  Five rows reading "csv — 5 folders and 3 files · 4d" cannot be told
	 *  apart, and the one thing somebody needs from this list is which of them
	 *  to take back.
	 */
	function historyBox( history ) {

		var box = el( 'div', { class: 'vgml-section vgml-import-recent' } );
		box.appendChild( el( 'h6', { class: 'vgml-kicker' }, l10n.history ) );
		var list = el( 'div', { class: 'vgml-import-rows' } );
		box.appendChild( list );

		history.forEach( function ( entry ) {

			var row = el( 'div', { class: 'vgml-import-history' } );

			var what = el( 'span', { class: 'vgml-import-history-what' } );
			what.appendChild( el( 'b', {}, entry.name ) );
			what.appendChild( document.createTextNode( ' · ' +
				sprintf( l10n.historyLine, n( entry.folders ), n( entry.assignments ) ) ) );
			row.appendChild( what );

			row.appendChild( el( 'span', { class: 'vgml-muted vgml-import-history-when' }, entry.when_text || '' ) );

			var undo = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-ghost vgml-import-undo' },
				sprintf( l10n.undo, n( entry.folders ) ) );

			undo.addEventListener( 'click', function () {
				undoInto( undo, entry.id, null );
			} );

			row.appendChild( undo );
			list.appendChild( row );
		} );

		box.appendChild( el( 'p', { class: 'vgml-note vgml-import-recent-note' }, l10n.historyNote ) );

		return box;
	}

	function refresh() {
		Promise.all( [
			call( { action: 'found' } ),
			call( { action: 'history' } ),
		] ).then( function ( r ) {
			facts = r[ 0 ].facts || null;
			var line = document.getElementById( 'vgml-import-facts' );
			if ( line && facts ) {
				line.textContent = facts.line;
			}
			render( r[ 0 ].sources || [], r[ 1 ].history || [] );
		} ).catch( function () {
			app.innerHTML = '';
			app.appendChild( el( 'p', { class: 'vgml-import-bad' }, l10n.failed ) );
		} );
	}

	refresh();
} )();
