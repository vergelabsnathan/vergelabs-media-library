/*
 *  The suggestion list on the AI screen.
 *
 *  Deliberately plain: a thumbnail, a title, the folder it resembles, and two
 *  answers. No score, no percentage, no "confident" -- those numbers are not
 *  calibrated, and a number next to a guess reads as a promise. "Looks like
 *  Invoices" is the whole claim, and it is honest.
 *
 *  ES5, no build step, like everything else here.
 */
( function () {

	var apiFetch = window.wp && window.wp.apiFetch;

	if ( ! apiFetch ) {
		return;
	}

	var l10n = window.vergemlAutofile || {};

	var runBtn = document.getElementById( 'vgml-autofile-run' );
	var list = document.getElementById( 'vgml-autofile-list' );
	var note = document.getElementById( 'vgml-autofile-note' );

	if ( ! runBtn || ! list ) {
		return;
	}

	var next = null;

	/*
	 *  The next step, as a button under the sentence. A sentence saying what
	 *  to do next, with no way to do it, is half an answer.
	 */
	function offerNext() {

		var host = document.getElementById( 'vgml-autofile-next' );

		if ( ! host ) {
			return;
		}

		host.innerHTML = '';

		if ( ! next || ! next.label ) {
			return;
		}

		var go = el( 'button', { type: 'button', class: 'button button-primary' }, next.label );

		go.addEventListener( 'click', function () {
			if ( next.href ) {
				window.location.href = next.href;
				return;
			}

			var target = next.scroll ? document.querySelector( next.scroll ) : null;

			if ( target ) {
				target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );

		host.appendChild( go );
	}

	function say( text ) {
		if ( note ) {
			note.textContent = text || '';
		}
	}

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );
		for ( var key in attrs ) {
			if ( Object.prototype.hasOwnProperty.call( attrs, key ) ) {
				node.setAttribute( key, attrs[ key ] );
			}
		}
		if ( undefined !== text && null !== text ) {
			node.appendChild( document.createTextNode( text ) );
		}
		return node;
	}

	function act( row, suggestion, what ) {

		row.classList.add( 'is-busy' );

		apiFetch( {
			path: '/vergeml/v1/autofile-act',
			method: 'POST',
			data: {
				attachment_id: suggestion.attachment_id,
				term_id: suggestion.term_id,
				action: what
			}
		} ).then( function () {
			row.parentNode.removeChild( row );
			if ( ! list.children.length ) {
				say( l10n.allDone || 'Nothing else to look at.' );
			}
		} ).catch( function ( e ) {
			row.classList.remove( 'is-busy' );
			say( ( e && e.message ) || 'That did not work.' );
		} );
	}

	function draw( suggestion ) {

		var row = el( 'li', { class: 'vgml-autofile-row' } );

		if ( suggestion.thumb ) {
			row.appendChild( el( 'img', { class: 'vgml-autofile-thumb', src: suggestion.thumb, alt: '' } ) );
		}

		var body = el( 'div', { class: 'vgml-autofile-body' } );

		body.appendChild( el( 'span', { class: 'vgml-autofile-title' }, suggestion.title || '(no title)' ) );

		/*
		 *  Built from a template with the folder name substituted, rather than
		 *  glued together here: word order is not the same in every language,
		 *  and "Looks like %s" is translatable where "Looks like " + name is
		 *  not.
		 */
		var phrase = ( l10n.looksLike || 'Looks like %s' ).replace( '%s', suggestion.folder );

		body.appendChild( el( 'span', { class: 'vgml-autofile-folder' }, phrase ) );

		row.appendChild( body );

		var yes = el( 'button', { type: 'button', class: 'button button-small button-primary' }, l10n.accept || 'File it there' );
		var no = el( 'button', { type: 'button', class: 'button button-small' }, l10n.dismiss || 'Not that one' );

		yes.addEventListener( 'click', function () {
			act( row, suggestion, 'accept' );
		} );

		no.addEventListener( 'click', function () {
			act( row, suggestion, 'dismiss' );
		} );

		var actions = el( 'div', { class: 'vgml-autofile-actions' } );
		actions.appendChild( yes );
		actions.appendChild( no );
		row.appendChild( actions );

		return row;
	}

	runBtn.addEventListener( 'click', function () {

		runBtn.disabled = true;
		say( l10n.working || 'Looking…' );
		list.innerHTML = '';

		apiFetch( {
			path: '/vergeml/v1/autofile-step',
			method: 'POST',
			data: {}
		} ).then( function ( res ) {

			runBtn.disabled = false;

			( res.suggested || [] ).forEach( function ( suggestion ) {
				list.appendChild( draw( suggestion ) );
			} );

			/*
			 *  Both numbers, always. "Filed 3" without "looked at 20" hides
			 *  how much it left alone, and how much it left alone is the
			 *  reassuring half.
			 */
			var parts = [];

			if ( res.filed ) {
				parts.push( ( l10n.filed || 'Filed %d into folders that had earned it.' ).replace( '%d', res.filed ) );
			}

			if ( ( res.suggested || [] ).length ) {
				parts.push( ( l10n.waiting || '%d waiting for you.' ).replace( '%d', res.suggested.length ) );
			}

			if ( ! parts.length ) {
				/*
				 *  A dead end is a bug.
				 *
				 *  This used to end on "looked at 20 and none of them clearly
				 *  belongs anywhere yet" and stop -- true, and no help at all
				 *  to somebody who now has twenty loose files and no idea what
				 *  to do with them. There are two different reasons nothing
				 *  was suggested and they have two different answers.
				 */
				if ( res.unlooked > 0 ) {
					parts.push( ( l10n.nextDescribe || '' ).replace( '%d', res.unlooked ) );
					next = { label: l10n.goDescribe, href: l10n.aiUrl };
				} else if ( res.loose > 0 ) {
					parts.push( l10n.nextPropose || '' );
					next = { label: l10n.goPropose, scroll: '#vgml-lib-stage' };
				} else {
					parts.push( l10n.nextNothing || '' );
				}
			}

			say( parts.join( ' ' ) );

		} ).catch( function ( e ) {
			runBtn.disabled = false;
			say( ( e && e.message ) || 'That did not work.' );
		} );
	} );
}() );
