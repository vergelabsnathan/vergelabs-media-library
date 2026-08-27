/*
 *  The command box.
 *
 *  Two steps, always, and the second one is never reachable without the
 *  first: type, see what it would do with the actual files, then agree. The
 *  button that acts sends the plan the server produced, never the sentence --
 *  so what somebody approves is what happens.
 *
 *  ES5, no build step.
 */
( function () {

	var apiFetch = window.wp && window.wp.apiFetch;

	if ( ! apiFetch ) {
		return;
	}

	var l10n = window.vergemlSay || {};

	var input = document.getElementById( 'vgml-say-text' );
	var planBtn = document.getElementById( 'vgml-say-plan' );
	var goBtn = document.getElementById( 'vgml-say-go' );
	var cancelBtn = document.getElementById( 'vgml-say-cancel' );
	var preview = document.getElementById( 'vgml-say-preview' );
	var summary = document.getElementById( 'vgml-say-summary' );
	var sample = document.getElementById( 'vgml-say-sample' );
	var note = document.getElementById( 'vgml-say-note' );

	if ( ! input || ! planBtn || ! preview ) {
		return;
	}

	var plan = null;

	function say( text ) {
		if ( note ) {
			note.textContent = text || '';
		}
	}

	function hide() {
		plan = null;
		preview.hidden = true;
		sample.innerHTML = '';
		summary.textContent = '';
	}

	function ask() {

		var text = input.value;

		if ( ! text ) {
			return;
		}

		hide();
		say( l10n.thinking || 'Working out what that means…' );
		planBtn.disabled = true;

		apiFetch( {
			path: '/vergeml/v1/say-plan',
			method: 'POST',
			data: { text: text }
		} ).then( function ( res ) {

			planBtn.disabled = false;
			say( '' );

			plan = res;
			summary.textContent = res.summary || '';

			( res.sample || [] ).forEach( function ( file ) {
				var li = document.createElement( 'li' );
				if ( file.thumb ) {
					var img = document.createElement( 'img' );
					img.src = file.thumb;
					img.alt = '';
					li.appendChild( img );
				}
				var span = document.createElement( 'span' );
				span.textContent = file.title || '(no title)';
				li.appendChild( span );
				sample.appendChild( li );
			} );

			preview.hidden = false;

		} ).catch( function ( e ) {
			planBtn.disabled = false;
			hide();
			// The server's refusals are written to be read -- "this does not
			// delete anything, whatever you ask it" -- so they are shown as
			// they are rather than replaced with something generic.
			say( ( e && e.message ) || 'That did not work.' );
		} );
	}

	function run() {

		if ( ! plan ) {
			return;
		}

		goBtn.disabled = true;
		say( l10n.doing || 'Doing it…' );

		apiFetch( {
			path: '/vergeml/v1/say-run',
			method: 'POST',
			data: { plan: plan }
		} ).then( function ( res ) {

			goBtn.disabled = false;
			hide();
			input.value = '';

			if ( res && res.done ) {
				say( res.done > 1 || undefined !== res.batch_id
					? ( l10n.done || 'Done — %d files.' ).replace( '%d', res.done )
					: ( l10n.madeIt || 'Done.' ) );
			} else {
				say( l10n.nothing || 'Nothing matched, so nothing happened.' );
			}

		} ).catch( function ( e ) {
			goBtn.disabled = false;
			say( ( e && e.message ) || 'That did not work.' );
		} );
	}

	planBtn.addEventListener( 'click', ask );

	input.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			ask();
		}
	} );

	if ( goBtn ) {
		goBtn.addEventListener( 'click', run );
	}

	if ( cancelBtn ) {
		cancelBtn.addEventListener( 'click', function () {
			hide();
			say( '' );
		} );
	}
}() );
