/*
 *  The Library health screen: the scan loop, and the two lists.
 *
 *  The loop is the same shape as the importer's and the AI screen's -- a REST
 *  call carrying the cursor the last one returned -- so closing the tab loses
 *  nothing and the next click carries on from the last file that got hashed.
 *
 *  The report used to only draw. It now deletes, and the line between the two
 *  lists is what makes that defensible:
 *
 *    Duplicates        byte-identical. Deleting one is provably lossless, so
 *                      this list has the delete controls.
 *    Look-alikes       a 64-bit hash thinks they look alike. That is a guess,
 *                      and a guess never gets a delete button. Since Phase 6
 *                      each set is a card: the pictures side by side, the four
 *                      facts and where each is used, and Keep this one --
 *                      which rewrites the other's pages to the kept file and
 *                      sets the other aside (core/health-keep.php), never
 *                      deletes -- Keep both, and Open both.
 *
 *  The browser suite asserts that separation rather than the absence of every
 *  control, because the separation is the actual safety property.
 */
( function () {
	'use strict';

	var apiFetch = window.wp && window.wp.apiFetch;
	var l10n = ( window.vergemlHealth && window.vergemlHealth.l10n ) || {};

	if ( ! apiFetch ) {
		return;
	}

	var $ = function ( id ) {
		return document.getElementById( id );
	};

	var running = false;

	function text( key, fallback ) {
		return l10n[ key ] || fallback;
	}

	function sprintf( template, values ) {
		var i = 0;
		return String( template )
			.replace( /%(\d)\$s/g, function ( match, position ) {
				return values[ Number( position ) - 1 ];
			} )
			.replace( /%s/g, function () {
				return values[ i++ ];
			} );
	}

	// Bytes as something a person reads. Deliberately not exact: the number is
	// an argument for looking, not an accounting figure.
	function bytes( n ) {

		var units = [ 'B', 'KB', 'MB', 'GB' ];
		var value = Number( n ) || 0;
		var unit = 0;

		while ( value >= 1024 && unit < units.length - 1 ) {
			value = value / 1024;
			unit++;
		}

		return ( unit === 0 ? Math.round( value ) : value.toFixed( 1 ) ) + ' ' + units[ unit ];
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

	var groupSeq = 0;

	/*
	 *  How much this copy is used, in words.
	 *
	 *  -1 means the usage scan has not run. That is not zero, and saying
	 *  "used nowhere" when we have not looked is how somebody deletes the one
	 *  copy that was on their home page.
	 */
	function usesLabel( uses ) {

		if ( uses < 0 ) {
			return text( 'usesUnknown', 'Usage not scanned' );
		}

		if ( uses === 0 ) {
			return text( 'usesNone', 'Used nowhere' );
		}

		return sprintf(
			1 === uses
				? text( 'usesOne', 'Used in %s place' )
				: text( 'usesMany', 'Used in %s places' ),
			[ String( uses ) ]
		);
	}

	/**
	 *  One set of copies.
	 *
	 *  @param {Object}  group     items, wasted, keep.
	 *  @param {boolean} deletable Byte-identical, so deleting is lossless.
	 *  @param {Array}   entries   Collects the deletable sets, so the bulk bar
	 *                             at the top of the card can drive them.
	 */
	function drawGroup( group, deletable, entries ) {

		var wrap = el( 'div', 'vgml-health-group' );
		var name = 'vgml-keep-' + ( groupSeq++ );

		var head = el( 'div', 'vgml-health-group-head' );

		/*
		 *  The tick that puts this set in a bulk run.
		 *
		 *  It is first in the head because that is where a WordPress list
		 *  table puts it, and doing the same thing here means somebody who has
		 *  used the media library already knows what it is for.
		 */
		var pick = null;

		if ( deletable ) {

			pick = document.createElement( 'input' );
			pick.type = 'checkbox';
			pick.className = 'vgml-health-pick';

			var picklabel = el( 'label', 'vgml-health-picklabel' );
			picklabel.appendChild( pick );
			picklabel.appendChild( el( 'span', 'screen-reader-text',
				text( 'selectSet', 'Select this set' ) ) );

			head.appendChild( picklabel );
		}

		var files = sprintf(
			1 === group.items.length
				? text( 'countOne', '%s file' )
				: text( 'countMany', '%s files' ),
			[ String( group.items.length ) ]
		);
		var line = el( 'span', 'vgml-health-count' );
		line.appendChild( el( 'b', null, files ) );
		line.appendChild( document.createTextNode( ' · ' ) );
		var back = sprintf( text( 'setLine', '%1$s · keep one and get %2$s back' ), [ files, bytes( group.wasted ) ] ).split( ' · ' )[ 1 ] || '';
		line.appendChild( document.createTextNode( back ) );
		head.appendChild( line );
		wrap.appendChild( head );

		var list = el( 'ul', 'vgml-health-files' );

		group.items.forEach( function ( item ) {

			var li = el( 'li' );

			/*
			 *  On a deletable set, every row is a choice of which copy to
			 *  keep -- a radio, because keeping two of a byte-identical set is
			 *  not a thing anybody means to do.
			 */
			if ( deletable ) {

				var radio = document.createElement( 'input' );
				radio.type = 'radio';
				radio.name = name;
				radio.value = String( item.id );
				radio.className = 'vgml-health-keep';
				radio.checked = Number( item.id ) === Number( group.keep );

				var label = el( 'label', 'vgml-health-choose' );
				label.appendChild( radio );
				label.appendChild( el( 'span', null, text( 'keepThis', 'Keep this one' ) ) );

				li.appendChild( label );
			}

			// The link goes to the file's own edit screen, which is where
			// looking at it properly belongs.
			var link = document.createElement( 'a' );
			link.href = item.edit || '#';

			if ( item.thumb ) {
				var img = document.createElement( 'img' );
				img.src = item.thumb;
				img.alt = '';
				img.loading = 'lazy';
				link.appendChild( img );
			} else {
				link.appendChild( el( 'span', 'vgml-health-nothumb', item.mime || '' ) );
			}

			link.appendChild( el( 'span', 'vgml-health-name', item.name || item.title || ( '#' + item.id ) ) );
			link.appendChild( el( 'span', 'vgml-health-size', bytes( item.bytes ) ) );

			if ( deletable ) {
				link.appendChild( el( 'span', 'vgml-health-uses', usesLabel( Number( item.uses ) ) ) );
			}

			li.appendChild( link );
			list.appendChild( li );
		} );

		wrap.appendChild( list );

		/*
		 *  One record per set, shared by the button in its foot and the bulk
		 *  bar above the list. Two records would mean a set deleted by hand
		 *  still looked undeleted to a bulk run, which would then ask the
		 *  server to delete files that are already gone.
		 */
		if ( deletable ) {

			var entry = { wrap: wrap, group: group, name: name, pick: pick, done: false };

			wrap.appendChild( deleteControls( entry ) );
			entries.push( entry );
		}

		return wrap;
	}


	/* ------------------------------------------------------- the look-alikes */

	/*
	 *  A set per card. The pictures side by side at a size a person can judge;
	 *  under each the catalogue title, the file name, the four facts and where
	 *  it is used; then the controls, each with its consequence in the label
	 *  (spec section 4.1). Done is one line where the card was (4.3), with
	 *  Undo for a day and a link to the Set aside list the file went to.
	 */

	function whenText( ts ) {

		var d = new Date( ts * 1000 );
		var now = new Date();
		var time = d.toLocaleTimeString( undefined, { hour: '2-digit', minute: '2-digit' } );
		var day = d.toDateString();

		if ( day === now.toDateString() ) {
			return sprintf( text( 'today', 'today %s' ), [ time ] );
		}
		if ( day === new Date( now.getTime() + 86400000 ).toDateString() ) {
			return sprintf( text( 'tomorrow', 'tomorrow %s' ), [ time ] );
		}
		return d.toLocaleDateString( undefined, { day: 'numeric', month: 'long' } ) + ' ' + time;
	}

	function pageCount( n ) {
		return sprintf( 1 === n ? text( 'pageOne', '%s page' ) : text( 'pageMany', '%s pages' ), [ String( n ) ] );
	}

	/* Where a file is used, named and linked; or that nothing was found; or that nobody has looked. */
	function usesLine( item, scanned ) {

		var li = el( 'li' );

		if ( ! scanned ) {
			li.className = 'is-dim';
			li.textContent = text( 'usesUnknown', 'Usage not scanned' );
			return li;
		}

		var uses = item.used_in || [];

		if ( ! uses.length ) {
			li.textContent = text( 'notUsed', 'Not used in a post, page, layout or widget' );
			return li;
		}

		li.appendChild( document.createTextNode( usesLabel( uses.length ) + ' · ' ) );

		uses.forEach( function ( use, i ) {
			if ( i ) {
				li.appendChild( document.createTextNode( ' · ' ) );
			}
			var node;
			if ( use.edit ) {
				node = document.createElement( 'a' );
				node.href = use.edit;
			} else {
				node = el( 'span' );
			}
			node.textContent = use.title;
			li.appendChild( node );
			if ( use.type ) {
				li.appendChild( el( 'small', 'vgml-pair-type', use.type ) );
			}
		} );

		return li;
	}

	/* One picture of a set, with its facts and its Keep this one. */
	function drawSide( item, others, scanned, onKeep ) {

		var side = el( 'div', 'vgml-pair-side' );

		var pic = document.createElement( 'a' );
		pic.className = 'vgml-pair-pic';
		pic.href = item.edit || '#';

		if ( item.large || item.thumb ) {
			var img = document.createElement( 'img' );
			img.src = item.large || item.thumb;
			img.alt = '';
			img.loading = 'lazy';
			pic.appendChild( img );
		} else {
			pic.appendChild( el( 'span', 'vgml-health-nothumb', item.mime || '' ) );
		}

		side.appendChild( pic );
		side.appendChild( el( 'div', 'vgml-pair-title', item.title || item.name || ( '#' + item.id ) ) );
		side.appendChild( el( 'div', 'vgml-pair-file', item.name || '' ) );

		var facts = el( 'ul', 'vgml-facts vgml-pair-facts' );
		facts.appendChild( el( 'li', null,
			( item.width && item.height ? Number( item.width ).toLocaleString() + ' × ' + Number( item.height ).toLocaleString() + ' · ' : '' ) + bytes( item.bytes ) ) );
		if ( item.date ) {
			facts.appendChild( el( 'li', null, item.date ) );
		}
		facts.appendChild( el( 'li', null, item.folder || text( 'noFolder', 'No folder' ) ) );
		facts.appendChild( usesLine( item, scanned ) );
		side.appendChild( facts );

		var keep = document.createElement( 'button' );
		keep.type = 'button';
		keep.className = 'vgml-btn vgml-pair-keep';

		if ( ! scanned ) {
			keep.disabled = true;
			keep.textContent = text( 'keepUnscan', 'Keep this one · usage not scanned' );
		} else {
			// The pages the button will rewrite: the others' uses that are
			// posts. The site's own settings are not rewritten, and the
			// answer says so if that is where a file is used.
			var pages = 0;
			others.forEach( function ( o ) {
				( o.used_in || [] ).forEach( function ( use ) {
					if ( use.id > 0 ) {
						pages++;
					}
				} );
			} );
			var what = 1 === others.length ? others[ 0 ].name : String( others.length );
			keep.textContent = pages
				? sprintf( text( 'keepOneRw', 'Keep this one · %1$s rewritten, %2$s set aside' ), [ pageCount( pages ), what ] )
				: sprintf( text( 1 === others.length ? 'keepOne' : 'keepOneOf', 'Keep this one · %s set aside' ), [ what ] );
		}

		keep.addEventListener( 'click', function () {
			onKeep( keep );
		} );

		side.appendChild( keep );

		return side;
	}

	function drawPair( group, scanned ) {

		var wrap = el( 'div', 'vgml-pair' );
		wrap.setAttribute( 'data-n', String( group.items.length ) );

		var busy = false;
		var ids = group.items.map( function ( o ) { return o.id; } );

		/* The card becomes one line, with what to press next beside it. */
		function done( line, links ) {
			wrap.className = 'vgml-pair is-done';
			wrap.innerHTML = '';
			var p = el( 'p', 'vgml-pair-line' );
			p.appendChild( document.createTextNode( line ) );
			( links || [] ).forEach( function ( link ) {
				p.appendChild( document.createTextNode( ' · ' ) );
				p.appendChild( link );
			} );
			wrap.appendChild( p );
			return p;
		}

		function link( label, run ) {
			var a = document.createElement( 'a' );
			a.href = '#';
			a.className = 'vgml-pair-undo';
			a.textContent = label;
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				run( a );
			} );
			return a;
		}

		function undoOf( path, data ) {
			return function ( a ) {
				a.textContent = text( 'working', 'Working…' );
				apiFetch( { path: path, method: 'POST', data: data } ).then( function ( u ) {
					done( u.line, [] );
					report();
				} ).catch( function ( err ) {
					a.textContent = ( err && err.message ) || text( 'failed', '' );
				} );
			};
		}

		function asideLink() {
			var a = document.createElement( 'a' );
			a.href = '#vgml-quarantine-list';
			a.className = 'vgml-pair-aside';
			a.textContent = text( 'asideLink', 'Set aside ↓' );
			a.addEventListener( 'click', function () {
				var show = $( 'vgml-quarantine-refresh' );
				if ( show ) {
					show.click();
				}
			} );
			return a;
		}

		function failed( button, label, err ) {
			busy = false;
			button.disabled = false;
			button.textContent = label;
			var note = wrap.querySelector( '.vgml-pair-note' ) || wrap.appendChild( el( 'p', 'vgml-pair-note' ) );
			note.textContent = ( err && err.message ) || text( 'failed', '' );
		}

		var sides = el( 'div', 'vgml-pair-sides' );

		group.items.forEach( function ( item ) {

			var others = group.items.filter( function ( o ) { return o.id !== item.id; } );

			sides.appendChild( drawSide( item, others, scanned, function ( button ) {

				if ( busy ) {
					return;
				}
				busy = true;

				var label = button.textContent;
				button.disabled = true;
				button.textContent = text( 'working', 'Working…' );

				apiFetch( {
					path: '/vergeml/v1/health-keep',
					method: 'POST',
					data: { keep: item.id, drop: others.map( function ( o ) { return o.id; } ) },
				} ).then( function ( r ) {
					var links = [ link(
						sprintf( text( 'undoUntil', 'Undo until %s' ), [ whenText( r.undo.until ) ] ),
						undoOf( '/vergeml/v1/health-keep-undo', { token: r.undo.token } )
					) ];
					if ( r.aside && r.aside.length ) {
						links.push( asideLink() );
					}
					done( r.line, links );
				} ).catch( function ( err ) {
					failed( button, label, err );
				} );
			} ) );
		} );

		wrap.appendChild( sides );

		var foot = el( 'div', 'vgml-pair-foot' );

		var both = document.createElement( 'button' );
		both.type = 'button';
		both.className = 'vgml-btn vgml-pair-both';
		both.textContent = 2 === ids.length
			? text( 'keepBoth', 'Keep both · not shown again' )
			: sprintf( text( 'keepAll', 'Keep all %s · not shown again' ), [ String( ids.length ) ] );

		both.addEventListener( 'click', function () {

			if ( busy ) {
				return;
			}
			busy = true;

			var label = both.textContent;
			both.disabled = true;
			both.textContent = text( 'working', 'Working…' );

			apiFetch( { path: '/vergeml/v1/health-retire', method: 'POST', data: { ids: ids } } ).then( function ( r ) {
				done( r.line, [ link( text( 'undo', 'Undo' ), undoOf( '/vergeml/v1/health-retire', { ids: ids, undo: true } ) ) ] );
			} ).catch( function ( err ) {
				failed( both, label, err );
			} );
		} );

		foot.appendChild( both );

		/*
		 *  One tab per file. A browser that allows one window per click
		 *  leaves the rest for a second press, and the button says which
		 *  file that press opens.
		 */
		var openable = group.items.filter( function ( o ) { return !! o.edit; } );
		var pending = [];
		var allLabel = 2 === ids.length
			? text( 'openBoth', 'Open both ↗' )
			: sprintf( text( 'openAll', 'Open all %s ↗' ), [ String( ids.length ) ] );

		var open = document.createElement( 'a' );
		open.className = 'vgml-btn vgml-pair-open';
		open.href = openable.length ? openable[ 0 ].edit : '#';
		open.target = '_blank';
		open.textContent = allLabel;

		open.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( ! pending.length ) {
				pending = openable.slice();
			}
			while ( pending.length ) {
				var w = window.open( pending[ 0 ].edit, '_blank' );
				if ( ! w ) {
					break;
				}
				pending.shift();
			}
			if ( pending.length && pending.length < openable.length ) {
				open.textContent = sprintf( text( 'openOne', 'Open %s ↗' ), [ pending[ 0 ].name ] );
				open.href = pending[ 0 ].edit;
			} else {
				open.textContent = allLabel;
				open.href = openable.length ? openable[ 0 ].edit : '#';
			}
		} );

		if ( openable.length ) {
			foot.appendChild( open );
		}

		wrap.appendChild( foot );

		return wrap;
	}

	/*
	 *  Without the usage scan, which pages show a picture is unknown, and a
	 *  file on the home page could be set aside with nothing rewritten. The
	 *  Keep buttons say so; this runs the scan from here.
	 */
	function usageControl() {

		var wrap = el( 'div', 'vgml-pairs-unscanned' );

		var facts = el( 'ul', 'vgml-facts' );
		facts.appendChild( el( 'li', null, text( 'unscannedLine', '' ) ) );
		wrap.appendChild( facts );

		var go = document.createElement( 'button' );
		go.type = 'button';
		go.className = 'vgml-btn';
		go.textContent = text( 'scanUsage', 'Scan usage' );

		go.addEventListener( 'click', function () {

			go.disabled = true;

			( function step( resume ) {
				apiFetch( { path: '/vergeml/v1/smart-scan', method: 'POST', data: resume ? { resume: resume } : {} } ).then( function ( res ) {
					if ( ! res.complete && res.resume ) {
						go.textContent = sprintf( text( 'scanningUse', 'Scanning usage · %1$s of %2$s' ), [ String( res.done || 0 ), String( res.total || 0 ) ] );
						step( res.resume );
						return;
					}
					report();
				} ).catch( function ( err ) {
					go.disabled = false;
					go.textContent = ( err && err.message ) || text( 'failed', '' );
				} );
			} )( null );
		} );

		wrap.appendChild( go );

		return wrap;
	}

	function drawPairs( section, scanned ) {

		var card = el( 'section', 'vgml-health-list is-related' );
		var head = el( 'div', 'vgml-health-list-head vgml-pairs-head' );
		var n = section.groups.length + ( section.more || 0 );

		head.appendChild( el( 'h6', 'vgml-kicker', n
			? sprintf( 1 === n ? text( 'relatedOne', 'Look-alikes · %s set' ) : text( 'relatedMany', 'Look-alikes · %s sets' ), [ String( n ) ] )
			: text( 'related', 'Look-alikes' ) ) );

		if ( section.groups.length ) {
			var facts = el( 'ul', 'vgml-facts vgml-pairs-facts' );
			( l10n.relatedFacts || [] ).forEach( function ( fact ) {
				facts.appendChild( el( 'li', null, fact ) );
			} );
			head.appendChild( facts );
		}

		card.appendChild( head );

		if ( ! section.groups.length ) {
			card.appendChild( el( 'div', 'vgml-health-empty', text( 'noRelated', 'Nothing else looked alike.' ) ) );
			return card;
		}

		var body = el( 'div', 'vgml-pg-card-body' );

		if ( ! scanned ) {
			body.appendChild( usageControl() );
		}

		var list = el( 'div', 'vgml-pairs' );

		section.groups.forEach( function ( group ) {
			list.appendChild( drawPair( group, scanned ) );
		} );

		body.appendChild( list );

		if ( section.more > 0 ) {
			body.appendChild( el( 'p', 'vgml-health-more',
				sprintf( text( 'moreSets', '…and %s more sets, listed in the media library view.' ), [ String( section.more ) ] ) ) );
		}

		card.appendChild( body );

		return card;
	}


	/* ------------------------------------------------------- doing the delete */

	/*
	 *  One set's delete, with no interface attached to it.
	 *
	 *  The button below and the bulk bar further down are two ways of asking
	 *  for the same thing, so the asking is here once. Which copy survives is
	 *  read from the radios at the moment it runs, never from a value captured
	 *  earlier -- somebody may well change their mind after ticking the box.
	 */

	function keptId( entry ) {
		var chosen = entry.wrap.querySelector( 'input[name="' + entry.name + '"]:checked' );
		return chosen ? Number( chosen.value ) : 0;
	}

	function dropsFor( entry, keep ) {
		var drop = [];
		entry.group.items.forEach( function ( item ) {
			if ( Number( item.id ) !== keep ) {
				drop.push( Number( item.id ) );
			}
		} );
		return drop;
	}

	function markDone( entry, r ) {

		entry.done = true;
		entry.wrap.className = 'vgml-health-group vgml-health-done';
		entry.wrap.innerHTML = '';
		entry.wrap.appendChild( el( 'p', 'vgml-health-doneline', r.message || '' ) );

		if ( r.content > 0 || r.thumbs > 0 ) {
			entry.wrap.appendChild( el( 'p', 'vgml-health-doneline description', sprintf(
				text( 'repointed', '%1$s posts and %2$s featured images now point at the copy you kept.' ),
				[ String( r.content ), String( r.thumbs ) ]
			) ) );
		}
	}

	/** Resolves with how many files went, or rejects with what stopped it. */
	function runDelete( entry ) {

		var keep = keptId( entry );

		if ( ! keep ) {
			return Promise.reject( new Error( text( 'pickOne', 'Choose which copy to keep first.' ) ) );
		}

		var drop = dropsFor( entry, keep );

		return apiFetch( {
			path: '/vergeml/v1/health-delete',
			method: 'POST',
			data: { keep: keep, drop: drop },
		} ).then( function ( r ) {
			markDone( entry, r );
			return drop.length;
		} );
	}

	/*
	 *  The delete control, with its confirmation.
	 *
	 *  Two presses, and the second one names the number and the file being
	 *  kept. The bytes do not come back, so the sentence has to be true rather
	 *  than reassuring: there is no undo and it says so.
	 */
	function deleteControls( entry ) {

		/*
		 *  Takes the set's record, the same one the bulk bar holds. The call
		 *  was changed to pass it and the signature was not, so `group` was
		 *  undefined and the first library with an actual duplicate crashed
		 *  the report at group.items -- the scan finished and the screen said
		 *  "Comparing…" for ever.
		 */
		var group = entry.group;
		var foot = el( 'div', 'vgml-health-act' );
		var note = el( 'span', 'vgml-health-act-note' );

		var go = document.createElement( 'button' );
		go.type = 'button';
		go.className = 'button';
		go.textContent = sprintf(
			2 === group.items.length
				? text( 'deleteOne', 'Delete the other copy' )
				: text( 'deleteMany', 'Delete the other %s copies' ),
			[ String( group.items.length - 1 ) ]
		);

		var armed = false;

		go.addEventListener( 'click', function () {

			var keep = keptId( entry );

			if ( ! keep ) {
				note.textContent = text( 'pickOne', 'Choose which copy to keep first.' );
				return;
			}

			var drop = dropsFor( entry, keep );

			if ( ! armed ) {
				armed = true;
				go.className = 'button button-primary vgml-health-armed';
				go.textContent = sprintf(
					1 === drop.length
						? text( 'confirmOne', 'Yes, delete %s file permanently' )
						: text( 'confirmMany', 'Yes, delete %s files permanently' ),
					[ String( drop.length ) ]
				);
				note.textContent = text( 'noUndo', 'The files are removed from disk. Anything pointing at them is repointed at the copy you keep first. There is no undo.' );
				return;
			}

			go.disabled = true;
			note.textContent = text( 'deleting', 'Deleting…' );

			runDelete( entry ).catch( function ( err ) {
				go.disabled = false;
				note.textContent = ( err && err.message ) || text( 'failed', '' );
			} );
		} );

		foot.appendChild( go );
		foot.appendChild( note );

		return foot;
	}

	/*
	 *  Doing the whole list at once.
	 *
	 *  A library with two hundred duplicate sets is the normal case, and
	 *  clicking through two hundred confirmations is not a feature, it is a
	 *  reason to give up and leave the duplicates there.
	 *
	 *  Sequential, one set per request, against the same endpoint the single
	 *  button uses. One request carrying two hundred deletions is a request
	 *  that dies against max_execution_time on shared hosting -- which is the
	 *  whole audience -- and dies halfway, having deleted an unknown amount.
	 *  One at a time is slower and it can always say exactly where it got to.
	 *
	 *  The keep radio still decides what survives in every set, and it is read
	 *  at the moment each set runs. The server picked a sensible default for
	 *  every one of them, so the common path is: tick all, press once.
	 */
	function bulkBar( entries ) {

		var bar = el( 'div', 'vgml-health-bulk' );

		var all = document.createElement( 'input' );
		all.type = 'checkbox';
		all.className = 'vgml-health-all';

		var alllabel = el( 'label', 'vgml-health-alllabel' );
		alllabel.appendChild( all );
		alllabel.appendChild( el( 'span', null, text( 'selectAll', 'Select every set' ) ) );

		var count = el( 'span', 'vgml-health-bulk-count' );
		var note  = el( 'span', 'vgml-health-bulk-note' );

		var go = document.createElement( 'button' );
		go.type = 'button';
		go.className = 'button';
		go.disabled = true;

		var stop = document.createElement( 'button' );
		stop.type = 'button';
		stop.className = 'button-link vgml-health-stop';
		stop.textContent = text( 'stop', 'Stop' );
		stop.hidden = true;

		var armed = false;
		var halt  = false;

		function selected() {
			var out = [];
			entries.forEach( function ( entry ) {
				if ( ! entry.done && entry.pick && entry.pick.checked ) {
					out.push( entry );
				}
			} );
			return out;
		}

		/* How many files a run would remove, which is never the number of sets. */
		function fileCount( list ) {
			var n = 0;
			list.forEach( function ( entry ) {
				n += dropsFor( entry, keptId( entry ) ).length;
			} );
			return n;
		}

		function refresh() {

			var list = selected();

			/* Arming is about a specific set of files; changing the selection
			 * has to disarm, or the second press deletes something the first
			 * press never named. */
			armed = false;
			go.className = 'button';
			note.textContent = '';

			count.textContent = list.length
				? sprintf(
					1 === list.length
						? text( 'chosenOne', '%s set selected' )
						: text( 'chosenMany', '%s sets selected' ),
					[ String( list.length ) ]
				)
				: '';

			go.disabled = ! list.length;
			go.textContent = sprintf(
				text( 'bulkDelete', 'Delete the extra copies in %s sets' ),
				[ String( list.length || 0 ) ]
			);
		}

		all.addEventListener( 'change', function () {
			entries.forEach( function ( entry ) {
				if ( ! entry.done && entry.pick ) {
					entry.pick.checked = all.checked;
				}
			} );
			refresh();
		} );

		entries.forEach( function ( entry ) {
			if ( entry.pick ) {
				entry.pick.addEventListener( 'change', refresh );
			}
		} );

		stop.addEventListener( 'click', function () {
			halt = true;
			note.textContent = text( 'stopping', 'Stopping after this one…' );
		} );

		go.addEventListener( 'click', function () {

			var list = selected();

			if ( ! list.length ) {
				return;
			}

			if ( ! armed ) {
				armed = true;
				go.className = 'button button-primary vgml-health-armed';
				go.textContent = sprintf(
					text( 'bulkConfirm', 'Yes, delete %s files permanently' ),
					[ String( fileCount( list ) ) ]
				);
				note.textContent = text( 'noUndo', 'The files are removed from disk. Anything pointing at them is repointed at the copy you keep first. There is no undo.' );
				return;
			}

			go.disabled = true;
			all.disabled = true;
			stop.hidden = false;
			halt = false;

			var freed = 0;
			var gone  = 0;
			var total = list.length;

			( function next( i ) {

				if ( halt || i >= list.length ) {

					stop.hidden = true;
					all.disabled = false;
					note.textContent = sprintf(
						text( 'bulkDone', 'Deleted %1$s files across %2$s sets.' ),
						[ String( freed ), String( gone ) ]
					);
					count.textContent = '';
					refresh();
					return;
				}

				note.textContent = sprintf(
					text( 'bulkProgress', 'Set %1$s of %2$s…' ),
					[ String( i + 1 ), String( total ) ]
				);

				runDelete( list[ i ] ).then( function ( n ) {
					freed += n;
					gone  += 1;
					next( i + 1 );
				} ).catch( function ( err ) {

					/*
					 *  One set failing is not the run failing. It is marked
					 *  where it sits and the rest carry on -- stopping the
					 *  whole thing because one file was already gone would be
					 *  the worst of both.
					 */
					list[ i ].wrap.appendChild( el( 'p', 'vgml-health-act-note',
						( err && err.message ) || text( 'failed', '' ) ) );
					next( i + 1 );
				} );
			} )( 0 );
		} );

		bar.appendChild( alllabel );
		bar.appendChild( count );
		bar.appendChild( go );
		bar.appendChild( stop );
		bar.appendChild( note );

		refresh();

		return bar;
	}

	function drawList( heading, note, empty, section, deletable ) {

		/*
		 *  The same card the rest of the plugin draws in PHP -- a head with
		 *  the title and its one-line note, then the body -- so a screen whose
		 *  cards come from a script is not the one screen shaped differently.
		 */
		var card = el( 'section', 'vgml-health-list' + ( deletable ? ' is-exact' : ' is-related' ) );
		var head = el( 'div', 'vgml-health-list-head' );
		head.appendChild( el( 'h6', 'vgml-kicker', heading ) );

		if ( note && section.groups.length ) {
			head.appendChild( el( 'span', 'vgml-health-list-note', note ) );
		}

		card.appendChild( head );

		if ( ! section.groups.length ) {
			card.appendChild( el( 'div', 'vgml-health-empty', empty ) );
			return card;
		}

		var shell = card;
		var body = el( 'div', 'vgml-pg-card-body' );
		card.appendChild( body );
		card = body;

		var entries = [];
		var groups  = el( 'div', 'vgml-health-groups' );

		section.groups.forEach( function ( group ) {
			groups.appendChild( drawGroup( group, deletable === true, entries ) );
		} );

		// The bar goes above the sets it acts on, and only where deleting is
		// provably lossless. A guess never gets a bulk delete button.
		if ( entries.length ) {
			card.appendChild( bulkBar( entries ) );
		}

		card.appendChild( groups );

		if ( section.more > 0 ) {
			card.appendChild( el( 'p', 'vgml-health-more',
				sprintf( text( 'moreSets', '…and %s more sets, listed in the media library view.' ), [ String( section.more ) ] ) ) );
		}

		// The card, not its body: everything above was appended into the body.
		return shell;
	}

	var justScanned = false;

	function report() {

		$( 'vgml-health-note' ).textContent = text( 'building', 'Comparing…' );

		return apiFetch( { path: '/vergeml/v1/health-report' } ).then( function ( r ) {

			var target = $( 'vgml-health-report' );
			target.innerHTML = '';

			if ( ! r.scanned ) {
				$( 'vgml-health-note' ).textContent = text( 'never', '' );
				return r;
			}

			/*
			 *  The three-cell band: exact copies, look-alike sets, and what
			 *  keeping one of each frees. Kept apart on purpose -- only the
			 *  first can be deleted from here (design handoff, item 3).
			 */
			var exact = 0;
			r.duplicates.groups.forEach( function ( g ) { exact += Math.max( 0, g.items.length - 1 ); } );
			var sets = r.related.groups.length + ( r.related.more || 0 );
			$( 'vgml-health-n-exact' ).textContent = String( exact );
			$( 'vgml-health-n-sets' ).textContent = String( sets );
			$( 'vgml-health-n-freed' ).textContent = bytes( r.wasted );
			$( 'vgml-health-band' ).hidden = false;

			target.appendChild( drawList(
				text( 'duplicates', 'Exact duplicates' ),
				text( 'dupeNote', '' ),
				text( 'noDuplicates', 'No duplicates found.' ),
				r.duplicates,
				true // byte-identical: deleting one is provably lossless
			) );

			// A guess, and a guess never gets a delete button: the cards keep
			// this one by setting the other aside, or keep both.
			target.appendChild( drawPairs( r.related, !! r.uses_scanned ) );

			$( 'vgml-health-note' ).textContent = '';
			$( 'vgml-health-scan' ).textContent = text( 'rescan', 'Scan again' );
			$( 'vgml-health-bar' ).hidden = true;
			if ( justScanned ) {
				$( 'vgml-health-counts' ).textContent = text( 'scannedNow', 'Scanned just now.' );
				justScanned = false;
			}

			return r;
		} );
	}

	function scan() {

		if ( running ) {
			return;
		}
		running = true;

		var bar = $( 'vgml-health-bar' );
		var fill = $( 'vgml-health-fill' );
		var note = $( 'vgml-health-note' );
		var total = 0;

		bar.hidden = false;
		fill.style.width = '0';
		note.textContent = text( 'scanning', 'Reading files…' );

		( function step( cursor, reset ) {
			apiFetch( {
				path: '/vergeml/v1/health-scan',
				method: 'POST',
				data: { cursor: cursor, reset: reset },
			} ).then( function ( r ) {

				if ( ! total ) {
					total = r.total || ( r.remaining + r.hashed );
				}

				var done = Math.max( 0, total - r.remaining );
				fill.style.width = total ? Math.round( ( done / total ) * 100 ) + '%' : '100%';
				note.textContent = sprintf( text( 'remaining', '%s to go' ), [ String( r.remaining ) ] );

				if ( ! r.done ) {
					step( r.cursor, false );
					return;
				}

				fill.style.width = '100%';
				running = false;
				justScanned = true;
				report();
			} ).catch( function ( err ) {
				running = false;
				note.textContent = ( err && err.message ) ? err.message : text( 'failed', 'Request failed.' );
			} );
		} )( 0, true );
	}

	function boot() {

		report().catch( function () {
			$( 'vgml-health-counts' ).textContent = text( 'failed', 'Request failed.' );
		} );

		$( 'vgml-health-scan' ).addEventListener( 'click', function () {
			scan();
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
