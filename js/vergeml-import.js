/*
 *  The import screen.
 *
 *  Four states and no more: what was found, what would happen, doing it, done.
 *  Plain JS and WordPress's own button classes -- an import screen is used once
 *  and should look like part of the admin, not like a product.
 *
 *  The run is chunked and the browser drives the loop, because sixteen thousand
 *  assignments will not finish inside one PHP request on the kind of host this
 *  mostly runs on. Each pass returns a resume token, which is handed straight
 *  back; nothing here has to understand what is in it.
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

	/*
	 *  The result of an import, in words. The merge clause is dropped when nothing
	 *  merged: "0 merged into folders you already have" is a sentence about
	 *  something that did not happen.
	 */
	function outcome( created, merged, assignments ) {
		return merged
			? sprintf( l10n.plan, n( created ), n( merged ), n( assignments ) )
			: sprintf( l10n.planPlain, n( created ), n( assignments ) );
	}

	function sprintf( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s ).replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			return pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ];
		} );
	}

	var call = function ( data ) {
		return wp.apiFetch( { path: '/vergeml/v1/import', method: 'POST', data: data } );
	};

	function ago( seconds ) {
		var mins = Math.max( 1, Math.round( seconds / 60 ) );
		if ( mins < 60 ) { return mins + 'm'; }
		var hours = Math.round( mins / 60 );
		if ( hours < 24 ) { return hours + 'h'; }
		return Math.round( hours / 24 ) + 'd';
	}

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

		app.appendChild( fileCard( sources.length === 0 ) );

		if ( sources.length ) {
			var found = el( 'div', { class: 'vgml-section' } );
			found.appendChild( el( 'h6', { class: 'vgml-kicker' }, l10n.found ) );
			sources.forEach( function ( source ) {
				found.appendChild( sourceCard( source ) );
			} );
			app.appendChild( found );
		}

		if ( history && history.length ) {
			app.appendChild( historyBox( history ) );
		}
	}

	/*
	 *  Folders as a file, above the plugin cards.
	 *
	 *  Always shown, unlike a source card: the other cards answer "what is
	 *  there to import from", and this one is the offer to make something to
	 *  import from. A card that only appeared once a file had been chosen
	 *  would be a feature nobody could discover.
	 */
	function fileCard( nothingFound ) {

		/*
		 *  Two cards side by side: Read in and Write out (design handoff,
		 *  screen 5). The file input is behind a real button; what was
		 *  chosen, or that nothing was, is said beside it.
		 */
		var box = el( 'div', { class: 'vgml-import-two' } );

		// ------------------------------------------------------------- in
		var into = el( 'div', { class: 'vgml-import-col' } );
		into.appendChild( el( 'h6', { class: 'vgml-kicker' }, l10n.importWhat ) );
		var intoBody = el( 'div', { class: 'vgml-import-colbody' } );
		intoBody.appendChild( el( 'p', { class: 'vgml-note' }, l10n.fileWhat ) );

		var pickRow = el( 'div', { class: 'vgml-import-pickrow' } );
		var file = el( 'input', { type: 'file', accept: '.csv,text/csv', 'aria-label': l10n.pickFile, class: 'vgml-import-file' } );
		var choose = el( 'button', { type: 'button', class: 'button button-primary' }, l10n.pickFile );
		choose.addEventListener( 'click', function () { file.click(); } );
		var note = el( 'span', { class: 'vgml-muted vgml-import-filenote' }, l10n.noFile );
		pickRow.appendChild( choose );
		pickRow.appendChild( note );
		pickRow.appendChild( file );
		intoBody.appendChild( pickRow );

		if ( nothingFound ) {
			intoBody.appendChild( el( 'p', { class: 'vgml-note vgml-import-none' }, l10n.none ) );
		}

		into.appendChild( intoBody );
		box.appendChild( into );

		// ------------------------------------------------------------ out
		var out = el( 'div', { class: 'vgml-import-col' } );
		out.appendChild( el( 'h6', { class: 'vgml-kicker' }, l10n.exportWhat ) );
		var outBody = el( 'div', { class: 'vgml-import-colbody' } );
		outBody.appendChild( el( 'p', { class: 'vgml-note' }, l10n.exportWhatNote ) );

		var outRow = el( 'div', { class: 'vgml-import-pickrow' } );
		var pick = el( 'select', { class: 'vgml-import-tax vgml-input' } );

		taxonomies.forEach( function ( t ) {
			var opt = el( 'option', { value: t.name }, t.label );
			pick.appendChild( opt );
		} );

		outRow.appendChild( pick );

		var down = el( 'a', { class: 'button', href: '#' }, l10n.exportGo );
		down.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			// The nonce is already on the base URL; only the taxonomy varies.
			window.location.href = base.exportUrl + '&taxonomy=' + encodeURIComponent( pick.value );
		} );
		outRow.appendChild( down );
		outBody.appendChild( outRow );
		out.appendChild( outBody );
		box.appendChild( out );

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

		return box;
	}


	function sourceCard( source ) {

		var box = el( 'div', { class: 'vgml-import-card' } );

		var head = el( 'div', { class: 'vgml-import-head' } );
		head.appendChild( el( 'h2', {}, source.name ) );
		head.appendChild( el( 'span', { class: 'vgml-import-by' }, source.author ) );
		box.appendChild( head );

		box.appendChild( el( 'p', { class: 'vgml-import-found' },
			sprintf( l10n.summary, n( source.folders ), n( source.files ) ) ) );

		var row = el( 'div', { class: 'vgml-import-actions' } );

		var pick = null;

		if ( taxonomies.length > 1 ) {
			row.appendChild( el( 'label', { class: 'vgml-import-label', for: 'tax-' + source.key }, l10n.importInto ) );
			pick = el( 'select', { id: 'tax-' + source.key } );
			taxonomies.forEach( function ( t ) {
				pick.appendChild( el( 'option', { value: t.name }, t.label ) );
			} );
			row.appendChild( pick );
		}

		var go = el( 'button', { type: 'button', class: 'button button-primary' }, l10n.preview );
		row.appendChild( go );
		box.appendChild( row );

		var out = el( 'div', { class: 'vgml-import-out', 'aria-live': 'polite' } );
		box.appendChild( out );

		go.addEventListener( 'click', function () {

			var taxonomy = pick ? pick.value : ( taxonomies[ 0 ] && taxonomies[ 0 ].name );

			go.disabled = true;
			out.innerHTML = '';
			out.appendChild( el( 'p', {}, l10n.working ) );

			call( { action: 'plan', source: source.key, taxonomy: taxonomy } )
				.then( function ( plan ) {
					go.disabled = false;
					showPlan( out, source, taxonomy, plan, go );
				} )
				.catch( function ( err ) {
					go.disabled = false;
					out.innerHTML = '';
					out.appendChild( el( 'p', { class: 'vgml-import-bad' }, ( err && err.message ) || l10n.failed ) );
				} );
		} );

		return box;
	}

	/*
	 *  What would happen, before it happens. The merge count is the number that
	 *  matters: it is the difference between "this will add 200 folders" and
	 *  "this will quietly duplicate the 12 you already have".
	 */
	function showPlan( out, source, taxonomy, plan, trigger ) {

		out.innerHTML = '';

		var box = el( 'div', { class: 'vgml-import-plan' } );

		box.appendChild( el( 'p', { class: 'vgml-import-plan-line' },
			outcome( plan.create, plan.merge, plan.assignments ) ) );

		if ( plan.merge ) {
			box.appendChild( el( 'p', { class: 'description' }, l10n.mergeNote ) );
		}

		box.appendChild( el( 'p', { class: 'description' }, l10n.stillThere ) );

		var row = el( 'div', { class: 'vgml-import-actions' } );

		var run = el( 'button', { type: 'button', class: 'button button-primary' }, l10n.importNow );
		var no = el( 'button', { type: 'button', class: 'button' }, l10n.cancel );

		no.addEventListener( 'click', function () {
			out.innerHTML = '';
		} );

		run.addEventListener( 'click', function () {
			trigger.disabled = true;
			runImport( out, source, taxonomy, plan );
		} );

		row.appendChild( run );
		row.appendChild( no );
		box.appendChild( row );
		out.appendChild( box );
	}

	/*
	 *  The chunk loop. The bar moves on real numbers from the server rather than
	 *  on a guess, because an import of sixteen thousand files is long enough that
	 *  a fake progress bar becomes a lie somebody notices.
	 */
	function runImport( out, source, taxonomy, plan ) {

		out.innerHTML = '';

		var line = el( 'p', {}, l10n.working );
		var bar = el( 'div', { class: 'vgml-import-bar' } );
		var fill = el( 'div', { class: 'vgml-import-fill' } );

		bar.appendChild( fill );
		out.appendChild( line );
		out.appendChild( bar );

		function step( resume ) {

			var data = { action: 'run', source: source.key, taxonomy: taxonomy };

			if ( resume ) {
				data.resume = resume;
			}

			call( data ).then( function ( result ) {

				var total = result.total || plan.assignments || 1;
				var done = result.done || 0;

				fill.style.width = Math.min( 100, Math.round( ( done / total ) * 100 ) ) + '%';
				line.textContent = sprintf( l10n.progress, n( done ), n( total ) );

				if ( ! result.complete && result.resume ) {
					step( result.resume );
					return;
				}

				flash = {
					text: l10n.done,
					detail: outcome( result.created, result.merged, result.assignments ),
				};
				refresh();

			} ).catch( function ( err ) {
				out.innerHTML = '';
				out.appendChild( el( 'p', { class: 'vgml-import-bad' }, ( err && err.message ) || l10n.failed ) );
			} );
		}

		step( null );
	}

	function historyBox( history ) {

		var box = el( 'div', { class: 'vgml-section vgml-import-recent' } );
		box.appendChild( el( 'h6', { class: 'vgml-kicker' }, l10n.history ) );
		var list = el( 'div', { class: 'vgml-import-rows' } );
		box.appendChild( list );

		var now = Math.floor( Date.now() / 1000 );

		history.forEach( function ( entry ) {

			var row = el( 'div', { class: 'vgml-import-history' } );

			row.appendChild( el( 'span', { class: 'vgml-import-history-what' },
				entry.name + ' — ' + sprintf( l10n.historyLine, n( entry.folders ), n( entry.assignments ) ) ) );
			row.appendChild( el( 'span', { class: 'vgml-muted vgml-import-history-when' }, ago( now - entry.when ) ) );

			var undo = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-ghost vgml-import-undo' }, l10n.undo );

			/*
			 *  Undo is chunked the same way the import is, and driven the same way
			 *  from here. The button carries the count so a long undo does not look
			 *  like a button that stopped responding.
			 */
			undo.addEventListener( 'click', function () {

				undo.disabled = true;
				undo.textContent = l10n.undoing;

				function step( resume ) {

					var data = { action: 'undo', id: entry.id };

					if ( resume ) {
						data.resume = resume;
					}

					call( data ).then( function ( result ) {

						if ( ! result.complete && result.resume ) {
							undo.textContent = sprintf( l10n.progress, n( result.done ), n( result.total ) );
							step( result.resume );
							return;
						}

						flash = { text: l10n.undone };
						refresh();

					} ).catch( function ( err ) {
						undo.disabled = false;
						undo.textContent = l10n.undo;
						row.appendChild( el( 'span', { class: 'vgml-import-bad' }, ( err && err.message ) || l10n.failed ) );
					} );
				}

				step( null );
			} );

			row.appendChild( undo );
			list.appendChild( row );
		} );

		return box;
	}

	function refresh() {
		Promise.all( [
			call( { action: 'found' } ),
			call( { action: 'history' } ),
		] ).then( function ( r ) {
			render( r[ 0 ].sources || [], r[ 1 ].history || [] );
		} ).catch( function () {
			app.innerHTML = '';
			app.appendChild( el( 'p', { class: 'vgml-import-bad' }, l10n.failed ) );
		} );
	}

	refresh();
} )();
