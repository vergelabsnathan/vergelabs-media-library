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
 *                      this list has the controls.
 *    Possibly related  a 64-bit hash thinks they look alike. That is a guess,
 *                      and a guess never gets a delete button. This list draws
 *                      and nothing else, exactly as before.
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

		head.appendChild( el( 'span', 'vgml-health-count', sprintf(
			1 === group.items.length
				? text( 'countOne', '%s file' )
				: text( 'countMany', '%s files' ),
			[ String( group.items.length ) ]
		) ) );
		head.appendChild( el( 'span', 'vgml-health-wasted',
			sprintf( text( 'groupWasted', '%s potentially recoverable' ), [ bytes( group.wasted ) ] ) ) );
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
	function deleteControls( wrap, group, name ) {

		var foot = el( 'div', 'vgml-health-act' );
		var note = el( 'span', 'vgml-health-act-note' );
		var entry = { wrap: wrap, group: group, name: name, done: false };

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

		var card = el( 'div', 'vgml-ai-card vgml-health-list' );

		card.appendChild( el( 'h2', null, heading ) );
		card.appendChild( el( 'p', 'description', note ) );

		if ( ! section.groups.length ) {
			card.appendChild( el( 'p', 'vgml-health-empty', empty ) );
			return card;
		}

		card.appendChild( el( 'p', 'vgml-health-summary',
			sprintf( text( 'summary', '%1$s groups · %2$s potentially recoverable' ),
				[ String( section.groups.length ), bytes( section.wasted ) ] ) ) );

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
				sprintf( text( 'more', 'and %s more' ), [ String( section.more ) ] ) ) );
		}

		return card;
	}

	function report() {

		$( 'vgml-health-note' ).textContent = text( 'building', 'Comparing…' );

		return apiFetch( { path: '/vergeml/v1/health-report' } ).then( function ( r ) {

			var target = $( 'vgml-health-report' );
			target.innerHTML = '';

			if ( ! r.scanned ) {
				$( 'vgml-health-counts' ).textContent = text( 'never', '' );
				$( 'vgml-health-note' ).textContent = '';
				return r;
			}

			var groups = r.duplicates.groups.length + r.related.groups.length;

			$( 'vgml-health-counts' ).textContent =
				sprintf( text( 'summary', '%1$s groups · %2$s potentially recoverable' ),
					[ String( groups ), bytes( r.wasted ) ] );

			target.appendChild( drawList(
				text( 'duplicates', 'Duplicates' ),
				text( 'dupeNote', '' ),
				text( 'noDuplicates', 'No duplicates found.' ),
				r.duplicates,
				true // byte-identical: deleting one is provably lossless
			) );

			target.appendChild( drawList(
				text( 'related', 'Possibly related' ),
				text( 'relatedNote', '' ),
				text( 'noRelated', '' ),
				r.related,
				false // a guess, and a guess never gets a delete button
			) );

			target.appendChild( el( 'p', 'vgml-health-readonly', text( 'careful', '' ) ) );

			$( 'vgml-health-note' ).textContent = '';
			$( 'vgml-health-scan' ).textContent = text( 'rescan', 'Scan again' );

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
