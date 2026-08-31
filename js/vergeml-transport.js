/*
 *  A second road to every endpoint -- the browser half.
 *
 *  Installed as an apiFetch middleware, so every one of this plugin's scripts
 *  benefits without a line of change: a call to /vergeml/v1/... that fails in
 *  a way REST never fails -- the network refused, an HTML page came back where
 *  JSON should be, a 401/403/404 that is not one of our own error codes -- is
 *  retried through admin-ajax.php, where the same handler answers.
 *
 *  A real answer of "no" from our handler is left alone. The fallback is for
 *  the road being closed, not for the answer being no. See core/transport.php.
 *
 *  Once the bridge has had to be used, the page is told so it can say it:
 *  a `vergeml:transport` event on the document, and a flag on
 *  window.vergemlTransport. Nothing else about the page changes.
 */
( function () {
	'use strict';

	var cfg = window.vergemlTransport;
	var api = window.wp && window.wp.apiFetch;

	if ( ! cfg || ! api || typeof api.use !== 'function' ) {
		return;
	}

	var ns = '/' + cfg.namespace + '/';
	var bridged = false;
	var announced = false;

	function ours( path ) {
		return typeof path === 'string' && path.indexOf( ns ) !== -1;
	}

	/*
	 *  Which failures mean "REST is unreachable" rather than "REST said no".
	 *
	 *  Our own codes start with vergeml_ and are the handler speaking; the
	 *  cookie-nonce code is WordPress speaking about a stale page, and the
	 *  bridge would fail on the same nonce. Everything else in this list is a
	 *  layer between the browser and the handler: the network, a proxy, a
	 *  security plugin, a cache returning HTML.
	 */
	function roadClosed( err ) {
		if ( ! err ) {
			return false;
		}
		var code = String( err.code || '' );

		// Our handler spoke: that is an answer, not a closed road.
		if ( code.indexOf( 'vergeml_' ) === 0 ) {
			return false;
		}
		// A stale page's nonce fails on the bridge too; retrying would only cost.
		if ( code === 'rest_cookie_invalid_nonce' ) {
			return false;
		}
		/*
		 *  What a security plugin's block actually looks like: rest_forbidden
		 *  or rest_not_logged_in from the rest_authentication_errors filter,
		 *  rest_no_route from a host that rewrote /wp-json/ away, a network
		 *  failure, or HTML where JSON was expected. A genuine "you may not"
		 *  from a permission callback is also rest_forbidden -- and costs one
		 *  extra request through the bridge, which then says the same thing.
		 */
		if ( [ 'fetch_error', 'invalid_json', 'rest_no_route', 'rest_disabled', 'rest_forbidden', 'rest_not_logged_in', 'rest_login_required' ].indexOf( code ) !== -1 ) {
			return true;
		}
		var status = err.data && err.data.status ? Number( err.data.status ) : 0;
		return [ 401, 403, 404, 405, 406, 429, 500, 502, 503, 504 ].indexOf( status ) !== -1;
	}

	/* Split "/vergeml/v1/tree?taxonomy=x" into a route and its query params. */
	function parsePath( path ) {
		var at = path.indexOf( '?' );
		var route = at === -1 ? path : path.slice( 0, at );
		var params = {};
		if ( at !== -1 ) {
			path.slice( at + 1 ).split( '&' ).forEach( function ( pair ) {
				if ( ! pair ) {
					return;
				}
				var kv = pair.split( '=' );
				params[ decodeURIComponent( kv[ 0 ] ) ] = decodeURIComponent( ( kv[ 1 ] || '' ).replace( /\+/g, ' ' ) );
			} );
		}
		return { route: route, params: params };
	}

	function nonce() {
		return ( api.nonceMiddleware && api.nonceMiddleware.nonce ) || '';
	}

	function announce( state, detail ) {
		if ( state === 'bridged' ) {
			bridged = true;
			window.vergemlTransport.bridged = true;
		}
		if ( state === 'bridged' && announced ) {
			return;
		}
		if ( state === 'bridged' ) {
			announced = true;
		}
		try {
			document.dispatchEvent( new CustomEvent( 'vergeml:transport', { detail: { state: state, status: detail || 0 } } ) );
		} catch ( e ) {}
	}

	function bridge( options ) {
		var parsed = parsePath( options.path );
		var method = ( options.method || 'GET' ).toUpperCase();
		var params = method === 'GET' ? parsed.params : ( options.data || parsed.params || {} );

		return window.fetch( cfg.ajax + '?action=vergeml_rest', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce() },
			body: JSON.stringify( { route: parsed.route, method: method, params: params } )
		} ).then( function ( res ) {
			return res.text().then( function ( text ) {
				var json;
				try {
					json = JSON.parse( text );
				} catch ( e ) {
					throw { code: 'invalid_json', message: 'The bridge did not answer with JSON.', data: { status: res.status } };
				}
				if ( res.status >= 400 ) {
					throw json;
				}
				announce( 'bridged', res.status );
				return json;
			} );
		} );
	}

	api.use( function ( options, next ) {

		if ( ! ours( options.path ) || options.vergemlNoBridge ) {
			return next( options );
		}

		// Once the direct road is known to be closed, stop knocking on it.
		if ( bridged ) {
			return bridge( options );
		}

		return next( options ).catch( function ( err ) {
			if ( ! roadClosed( err ) ) {
				throw err;
			}
			return bridge( options ).catch( function ( err2 ) {
				announce( 'failed', ( err2 && err2.data && err2.data.status ) || ( err && err.data && err.data.status ) || 0 );
				throw err2;
			} );
		} );
	} );

	window.vergemlTransport.bridged = false;
	window.vergemlTransport.text = cfg.l10n;
} )();
