/*
 *  The AI screen: the Describe tab's run, and the Search tab's query.
 *
 *  Describing is a REST call in a loop, a few files per call, so it is
 *  resumable by construction: close the tab mid-run and the next click
 *  carries on from where the catalogue says things stand. Progress is in
 *  the button that started it ("Describing 9 of 24"), Stop beside it, and
 *  done is one line where the button was looking (spec section 4).
 *
 *  The two switches on this screen save as they are changed; the line
 *  under each says so and when it applies. The brief's tab is
 *  js/vergeml-brief.js.
 */
( function () {
	'use strict';

	var apiFetch = window.wp && window.wp.apiFetch;
	var i18n = window.wp && window.wp.i18n;
	var cfg = window.vgmlAi || {};

	if ( ! apiFetch || ! i18n ) {
		return;
	}

	var __ = i18n.__;
	var _n = i18n._n;
	var sprintf = i18n.sprintf;

	var $ = function ( id ) {
		return document.getElementById( id );
	};

	function fmt( n ) {
		return new Intl.NumberFormat().format( Number( n ) || 0 );
	}

	function timeNow() {
		return new Date().toLocaleTimeString( undefined, { hour: '2-digit', minute: '2-digit' } );
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

	/* ------------------------------------------------------ the switches */

	/**
	 *  A switch that saves itself. The line under it says "Saved" for a
	 *  moment, then goes back to saying when the setting applies.
	 */
	function bindSwitch( id, key ) {
		var input = $( id );
		var note = $( id + '-note' );
		if ( ! input ) {
			return;
		}
		var idle = note ? note.textContent : '';
		input.addEventListener( 'change', function () {
			var data = {};
			data[ key ] = input.checked ? 1 : 0;
			input.disabled = true;
			apiFetch( { path: '/vergeml/v1/ai-settings', method: 'POST', data: data } ).then( function () {
				input.disabled = false;
				if ( note ) {
					note.textContent = __( 'Saved', 'vergelabs-media-library' ) + ' · ' + idle;
					window.setTimeout( function () {
						note.textContent = idle;
					}, 2500 );
				}
			} ).catch( function ( err ) {
				input.disabled = false;
				input.checked = ! input.checked;
				if ( note ) {
					note.textContent = ( err && err.message ) || __( 'That did not save. Try again.', 'vergelabs-media-library' );
				}
			} );
		} );
	}

	/* ------------------------------------------------------ the Describe tab */

	var running = false;
	var stopping = false;

	function label( button, count, some, none ) {
		if ( ! button ) {
			return;
		}
		button.textContent = count > 0 ? some( count ) : none;
		button.disabled = ! ( count > 0 );
		// The background run borrows the button's face and gives it back from here.
		button.setAttribute( 'data-idle', button.textContent );
		button.setAttribute( 'data-idle-off', button.disabled ? '1' : '' );
	}

	function refresh() {
		return apiFetch( { path: '/vergeml/v1/ai-status' } ).then( function ( s ) {
			var fresh = parseInt( s.unindexed, 10 ) || 0;
			var missing = parseInt( s.missing_alt, 10 ) || 0;
			var gap = parseInt( s.page_gap, 10 ) || 0;

			if ( $( 'vgml-ai-fact-new' ) ) {
				/* translators: %s: pictures */
				$( 'vgml-ai-fact-new' ).textContent = sprintf( _n( '%s new picture', '%s new pictures', fresh, 'vergelabs-media-library' ), fmt( fresh ) );
			}
			if ( $( 'vgml-ai-fact-alt' ) ) {
				/* translators: %s: pictures */
				$( 'vgml-ai-fact-alt' ).textContent = sprintf( __( '%s without alt text', 'vergelabs-media-library' ), fmt( missing ) );
			}
			if ( ! running ) {
				label( $( 'vgml-ai-run' ), fresh, function ( n ) {
					/* translators: %s: pictures */
					return sprintf( _n( 'Describe %s new picture', 'Describe %s new pictures', n, 'vergelabs-media-library' ), fmt( n ) );
				}, __( 'Describe · nothing new', 'vergelabs-media-library' ) );
				label( $( 'vgml-ai-alt' ), missing, function ( n ) {
					/* translators: %s: pictures */
					return sprintf( __( 'Alt text for %s', 'vergelabs-media-library' ), fmt( n ) );
				}, __( 'Alt text · none missing', 'vergelabs-media-library' ) );
			}
			if ( $( 'vgml-ai-page-gap' ) ) {
				$( 'vgml-ai-page-gap' ).hidden = ! gap;
				/* translators: %s: pictures */
				$( 'vgml-ai-page-gap' ).textContent = sprintf( __( 'Alt text on your SEO pages · %s', 'vergelabs-media-library' ), fmt( gap ) );
				$( 'vgml-ai-page-gap' ).setAttribute( 'data-idle', $( 'vgml-ai-page-gap' ).textContent );
			}
			if ( $( 'vgml-ai-enrich' ) ) {
				$( 'vgml-ai-enrich' ).checked = !! s.settings.enrich_search;
			}
			if ( $( 'vgml-ai-page-context' ) ) {
				$( 'vgml-ai-page-context' ).checked = !! s.settings.page_context;
			}
			return s;
		} );
	}

	function log( text, bad ) {
		var li = document.createElement( 'li' );
		li.textContent = text;
		if ( bad ) {
			li.className = 'is-error';
		}
		var list = $( 'vgml-ai-log' );
		list.insertBefore( li, list.firstChild );
		while ( list.children.length > 40 ) {
			list.removeChild( list.lastChild );
		}
	}

	/** The run, watched here: the button counts, Stop sits beside it, done is one line. */
	function run( scope, applyAlt ) {

		if ( running ) {
			return;
		}
		running = true;
		stopping = false;

		var button = $( scope === 'missing-alt' ? 'vgml-ai-alt' : ( scope === 'page-gap' ? 'vgml-ai-page-gap' : 'vgml-ai-run' ) );
		var others = [ $( 'vgml-ai-run' ), $( 'vgml-ai-alt' ), $( 'vgml-ai-page-gap' ) ];
		var stop = $( 'vgml-ai-stop' );
		var bar = $( 'vgml-ai-bar' );
		var fill = $( 'vgml-ai-fill' );
		var note = $( 'vgml-ai-note' );
		var total = 0;
		var described = 0;
		var failed = 0;

		others.forEach( function ( b ) { if ( b ) { b.disabled = true; } } );
		stop.hidden = false;
		stop.disabled = false;
		bar.hidden = false;
		fill.style.width = '0';
		note.textContent = '';
		/* translators: 1: pictures described so far, 2: pictures in the run */
		button.textContent = sprintf( __( 'Describing %1$s of %2$s', 'vergelabs-media-library' ), fmt( 0 ), '…' );

		function finish( text ) {
			running = false;
			stop.hidden = true;
			bar.hidden = true;
			note.textContent = text;
			refresh();
		}

		( function step() {
			apiFetch( {
				path: '/vergeml/v1/ai-index',
				method: 'POST',
				data: { scope: scope, limit: 24, apply_alt: applyAlt },
			} ).then( function ( r ) {

				r.described.forEach( function ( d ) {
					log( '#' + d.id + ' ' + d.caption );
				} );
				r.errors.forEach( function ( e ) {
					log( '#' + e.id + ' — ' + e.error, true );
				} );
				described += r.described.length;
				failed += r.errors.length;

				if ( ! total ) {
					total = r.remaining + r.described.length + r.errors.length;
				}

				var doneCount = Math.max( 0, total - r.remaining );
				fill.style.width = total ? Math.round( ( doneCount / total ) * 100 ) + '%' : '100%';
				/* translators: 1: pictures described so far, 2: pictures in the run */
				button.textContent = sprintf( __( 'Describing %1$s of %2$s', 'vergelabs-media-library' ), fmt( doneCount ), fmt( total ) );

				if ( stopping && r.remaining > 0 ) {
					/* translators: 1: pictures described, 2: pictures in the run, 3: pictures left, 4: a time */
					finish( sprintf( __( '%1$s of %2$s described · %3$s left · Stop at %4$s', 'vergelabs-media-library' ), fmt( doneCount ), fmt( total ), fmt( r.remaining ), timeNow() ) );
					return;
				}

				if ( r.remaining > 0 && ( r.described.length || r.errors.length ) ) {
					step();
					return;
				}

				if ( r.remaining > 0 ) {
					/* translators: 1: pictures described, 2: pictures that kept failing */
					finish( sprintf( __( '%1$s described · %2$s kept failing and were left', 'vergelabs-media-library' ), fmt( described ), fmt( r.remaining ) ) );
					return;
				}
				/* translators: 1: pictures described, 2: pictures that failed, 3: a time */
				finish( sprintf( _n( '%1$s picture described · %2$s failed · %3$s', '%1$s pictures described · %2$s failed · %3$s', described, 'vergelabs-media-library' ), fmt( described ), fmt( failed ), timeNow() ) );
			} ).catch( function ( err ) {
				finish( ( err && err.message ) ? err.message : __( 'That did not go through. Try again.', 'vergelabs-media-library' ) );
			} );
		} )();
	}

	function bootDescribe() {
		refresh();

		function inBackground() {
			var picked = document.querySelector( 'input[name="vgml-ai-where"]:checked' );
			return !! picked && 'background' === picked.value;
		}

		function start( scope ) {
			if ( ! inBackground() ) {
				run( scope, true );
				return;
			}
			if ( window.vergemlStartBackground ) {
				window.vergemlStartBackground( scope );
			}
		}

		[ [ 'vgml-ai-run', 'unindexed' ], [ 'vgml-ai-alt', 'missing-alt' ], [ 'vgml-ai-page-gap', 'page-gap' ] ].forEach( function ( pair ) {
			if ( $( pair[ 0 ] ) ) {
				$( pair[ 0 ] ).addEventListener( 'click', function () {
					start( pair[ 1 ] );
				} );
			}
		} );

		$( 'vgml-ai-stop' ).addEventListener( 'click', function () {
			// The step in flight finishes; the next does not start.
			stopping = true;
			$( 'vgml-ai-stop' ).disabled = true;
		} );
	}

	/* -------------------------------------------------------- the Search tab */

	/** The words of the query, bold inside a snippet, without innerHTML. */
	function marked( target, text, terms ) {
		var lower = String( text ).toLowerCase();
		var at = 0;
		var out = [];
		while ( at < text.length ) {
			var best = -1;
			var bestTerm = '';
			terms.forEach( function ( term ) {
				var i = lower.indexOf( term.toLowerCase(), at );
				if ( i >= 0 && ( best < 0 || i < best ) ) {
					best = i;
					bestTerm = term;
				}
			} );
			if ( best < 0 ) {
				out.push( document.createTextNode( text.slice( at ) ) );
				break;
			}
			if ( best > at ) {
				out.push( document.createTextNode( text.slice( at, best ) ) );
			}
			out.push( el( 'b', null, text.substr( best, bestTerm.length ) ) );
			at = best + bestTerm.length;
		}
		out.forEach( function ( n ) { target.appendChild( n ); } );
	}

	var FIELDS = {
		title: __( 'title', 'vergelabs-media-library' ),
		file: __( 'file name', 'vergelabs-media-library' ),
		caption: __( 'caption', 'vergelabs-media-library' ),
		description: __( 'description', 'vergelabs-media-library' ),
		ai_caption: __( 'the model\'s caption', 'vergelabs-media-library' ),
		ai_tags: __( 'tags', 'vergelabs-media-library' ),
		ai_title: __( 'the model\'s title', 'vergelabs-media-library' )
	};

	function hitRow( h, why, score ) {
		var row = el( 'div', { class: 'vgml-hit' } );
		var img = el( 'img', { alt: '', src: h.thumb || '' } );
		if ( ! h.thumb ) {
			img.hidden = true;
		}
		row.appendChild( img );
		var mid = el( 'div' );
		mid.appendChild( el( 'div', { class: 'vgml-hit-t' }, h.title || ( '#' + h.id ) ) );
		var w = el( 'div', { class: 'vgml-hit-w' } );
		why( w );
		mid.appendChild( w );
		row.appendChild( mid );
		row.appendChild( el( 'div', { class: 'vgml-hit-s' }, null === score || undefined === score ? '' : String( score.toFixed( 2 ) ) ) );
		return row;
	}

	function renderSearch( r ) {
		var out = $( 'vgml-search-out' );
		out.innerHTML = '';
		var terms = r.terms || [];
		if ( ! terms.length ) {
			return;
		}

		var facts = el( 'ul', { class: 'vgml-facts vgml-hits-head' } );
		var perWord = ( r.word.per_word || [] ).map( function ( p ) {
			/* translators: 1: a word, 2: pictures */
			return sprintf( __( '"%1$s" in %2$s', 'vergelabs-media-library' ), p.word, fmt( p.n ) );
		} ).join( ', ' );
		var byWord = el( 'li' );
		byWord.appendChild( document.createTextNode( __( 'By word: ', 'vergelabs-media-library' ) ) );
		byWord.appendChild( el( 'b', null, fmt( r.word.total ) ) );
		byWord.appendChild( document.createTextNode( ' · ' + ( terms.length > 1
			/* translators: %s: the words with their counts */
			? sprintf( __( 'every word has to match: %s', 'vergelabs-media-library' ), perWord )
			/* translators: %s: pictures */
			: sprintf( __( '%s with this plugin off', 'vergelabs-media-library' ), fmt( r.word.core ) ) ) ) );
		facts.appendChild( byWord );

		var byMeaning = el( 'li' );
		if ( r.meaning.available ) {
			byMeaning.appendChild( document.createTextNode( __( 'By meaning: the ', 'vergelabs-media-library' ) ) );
			byMeaning.appendChild( el( 'b', null, fmt( r.meaning.shown ) ) );
			/* translators: 1: pictures at or above the floor, 2: seconds */
			byMeaning.appendChild( document.createTextNode( ' ' + sprintf( __( 'closest of %1$s compared · %2$s s · no credit', 'vergelabs-media-library' ), fmt( r.meaning.total ), String( r.meaning.seconds ) ) ) );
		} else {
			byMeaning.textContent = __( 'By meaning: the service did not answer, so only the word search ran', 'vergelabs-media-library' );
		}
		facts.appendChild( byMeaning );
		out.appendChild( facts );

		var list = el( 'div', { class: 'vgml-hits' } );
		var shown = {};

		( r.word.hits || [] ).forEach( function ( h ) {
			shown[ h.id ] = true;
			list.appendChild( hitRow( h, function ( w ) {
				var fields = ( h.in || [] ).map( function ( f ) { return FIELDS[ f ] || f; } );
				w.appendChild( document.createTextNode( ( fields.length ? fields.join( ', ' ) : __( 'By word', 'vergelabs-media-library' ) ) ) );
				if ( h.snippet ) {
					w.appendChild( document.createTextNode( ' · "' ) );
					marked( w, h.snippet, terms );
					w.appendChild( document.createTextNode( '"' ) );
				}
			}, null ) );
		} );
		if ( r.word.total > ( r.word.hits || [] ).length ) {
			/* translators: %s: pictures */
			list.appendChild( el( 'div', { class: 'vgml-hits-more' }, sprintf( __( '%s more by word', 'vergelabs-media-library' ), fmt( r.word.total - r.word.hits.length ) ) ) );
		}

		( r.meaning.hits || [] ).forEach( function ( h ) {
			if ( shown[ h.id ] ) {
				return;
			}
			list.appendChild( hitRow( h, function ( w ) {
				if ( h.words && h.words.length ) {
					marked( w, h.words.join( ', ' ), terms );
					w.appendChild( document.createTextNode( terms.length > 1 ? ' ' + __( 'only', 'vergelabs-media-library' ) + ' · ' : ' · ' ) );
				} else {
					w.appendChild( document.createTextNode( __( 'No word from the query', 'vergelabs-media-library' ) + ' · ' ) );
				}
				if ( h.tags && h.tags.length ) {
					w.appendChild( document.createTextNode( __( 'tags', 'vergelabs-media-library' ) + ' ' ) );
					marked( w, h.tags.join( ', ' ), terms );
				} else if ( h.snippet ) {
					marked( w, h.snippet, terms );
				}
			}, h.score ) );
		} );

		if ( ! list.children.length ) {
			list.appendChild( el( 'div', { class: 'vgml-hits-more' }, __( 'Nothing matches, by word or by meaning', 'vergelabs-media-library' ) ) );
		}
		out.appendChild( list );
	}

	function bootSearch() {
		var form = $( 'vgml-search-form' );
		var q = $( 'vgml-search-q' );
		var go = $( 'vgml-search-go' );
		if ( ! form ) {
			return;
		}
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var text = q.value.trim();
			if ( ! text ) {
				return;
			}
			go.disabled = true;
			go.textContent = __( 'Searching', 'vergelabs-media-library' );
			apiFetch( { path: '/vergeml/v1/search-try?s=' + encodeURIComponent( text ) } ).then( function ( r ) {
				renderSearch( r );
			} ).catch( function ( err ) {
				$( 'vgml-search-out' ).textContent = ( err && err.message ) || __( 'That did not go through. Try again.', 'vergelabs-media-library' );
			} ).then( function () {
				go.disabled = false;
				go.textContent = __( 'Search', 'vergelabs-media-library' );
			} );
		} );
	}

	/* ------------------------------------------------------------------ go */

	function boot() {
		// The background run's script asks for fresh counts when its run ends.
		window.vergemlAiRefresh = refresh;
		bindSwitch( 'vgml-ai-enrich', 'enrich_search' );
		bindSwitch( 'vgml-ai-page-context', 'page_context' );
		if ( $( 'vgml-ai-run' ) ) {
			bootDescribe();
		}
		bootSearch();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
