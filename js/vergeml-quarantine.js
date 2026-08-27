/*
 *  What is set aside, and the one way back.
 *
 *  Every row has a "take it back" button and none of them has a delete
 *  button, because there is no endpoint behind one. When the wait is over the
 *  row says so and the list is downloadable; what happens next is somebody's
 *  own decision, made in WordPress, having looked at it.
 *
 *  ES5, no build step.
 */
( function () {

	var apiFetch = window.wp && window.wp.apiFetch;

	if ( ! apiFetch ) {
		return;
	}

	var l10n = window.vergemlQuarantine || {};

	var refreshBtn = document.getElementById( 'vgml-quarantine-refresh' );
	var manifestBtn = document.getElementById( 'vgml-quarantine-manifest' );
	var list = document.getElementById( 'vgml-quarantine-list' );
	var note = document.getElementById( 'vgml-quarantine-note' );

	if ( ! refreshBtn || ! list ) {
		return;
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

	function draw( file ) {

		var row = el( 'li', { class: 'vgml-autofile-row' } );

		if ( file.thumb ) {
			row.appendChild( el( 'img', { class: 'vgml-autofile-thumb', src: file.thumb, alt: '' } ) );
		}

		var body = el( 'div', { class: 'vgml-autofile-body' } );
		body.appendChild( el( 'span', { class: 'vgml-autofile-title' }, file.title || file.file || '(no title)' ) );

		var when = file.eligible
			? ( l10n.eligible || 'The wait is over' )
			: ( l10n.waiting || 'Set aside %d days ago' ).replace( '%d', file.days );

		body.appendChild( el( 'span', { class: 'vgml-autofile-folder' }, file.reason ? when + ' — ' + file.reason : when ) );

		row.appendChild( body );

		var back = el( 'button', { type: 'button', class: 'button button-small' }, l10n.takeBack || 'Take it back' );

		back.addEventListener( 'click', function () {
			row.classList.add( 'is-busy' );
			apiFetch( {
				path: '/vergeml/v1/quarantine-act',
				method: 'POST',
				data: { ids: [ file.id ], action: 'take-back' }
			} ).then( function () {
				row.parentNode.removeChild( row );
				if ( ! list.children.length ) {
					say( l10n.empty || 'Nothing is set aside.' );
				}
			} ).catch( function ( e ) {
				row.classList.remove( 'is-busy' );
				say( ( e && e.message ) || 'That did not work.' );
			} );
		} );

		var actions = el( 'div', { class: 'vgml-autofile-actions' } );
		actions.appendChild( back );
		row.appendChild( actions );

		return row;
	}

	function load() {

		say( l10n.loading || 'Looking…' );
		list.innerHTML = '';

		apiFetch( { path: '/vergeml/v1/quarantine' } ).then( function ( res ) {

			( res.files || [] ).forEach( function ( file ) {
				list.appendChild( draw( file ) );
			} );

			say( ( res.files || [] ).length ? '' : ( l10n.empty || 'Nothing is set aside.' ) );

		} ).catch( function ( e ) {
			say( ( e && e.message ) || 'That did not work.' );
		} );
	}

	refreshBtn.addEventListener( 'click', load );

	if ( manifestBtn ) {
		manifestBtn.addEventListener( 'click', function () {
			apiFetch( { path: '/vergeml/v1/quarantine-manifest' } ).then( function ( manifest ) {
				/*
				 *  Built in the browser from what the endpoint returned, so
				 *  the file somebody keeps is the same data the screen showed
				 *  them rather than a second query that could disagree.
				 */
				var blob = new Blob( [ JSON.stringify( manifest, null, 2 ) ], { type: 'application/json' } );
				var url = URL.createObjectURL( blob );
				var a = document.createElement( 'a' );
				a.href = url;
				a.download = 'set-aside.json';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( url );
			} ).catch( function ( e ) {
				say( ( e && e.message ) || 'That did not work.' );
			} );
		} );
	}
}() );
