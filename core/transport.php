<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 *  A second road to every endpoint.
 *
 *  Everything this plugin does after the first paint goes through
 *  /wp-json/vergeml/v1/. That is the right design and it is also the single
 *  most fragile joint in every plugin of this kind: a host that rewrites
 *  /wp-json/ away, a caching layer that answers an API call with a cached
 *  HTML page. A sidebar that cannot load reads as "your folders are gone".
 *
 *  This is the same endpoints reached through admin-ajax.php instead. Not a
 *  second implementation: the request is handed to rest_do_request(), so the
 *  same handler runs with the same permission callback and the same
 *  validation, and the only thing that changed is which door it came in by.
 *  It is for a road that is broken, not one that is closed on purpose: the
 *  site's own REST authentication filters (a security plugin that turns REST
 *  off) are asked first and answered the same way here. Only routes this
 *  plugin registers are reachable.
 *
 *  The browser side (js/vergeml-transport.js) tries REST first and falls back
 *  here only when REST answers in a way REST never does -- a network error,
 *  an HTML page where JSON was expected, or a 401/403/404 that is not one of
 *  our own error codes. A real "you may not do that" from our handler is
 *  passed through untouched; the fallback is for the road being closed, not
 *  for the answer being no.
 *
 *  @since 3.9
 */

add_action( 'wp_ajax_vergeml_rest', 'vergeml_transport_bridge' );

function vergeml_transport_bridge() {

    /*
     *  The same nonce apiFetch already carries for REST, so the browser sends
     *  nothing it did not have. It is verified against the same action the
     *  REST cookie check uses.
     */
    $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';

    if ( ! $nonce && isset( $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is the nonce, verified on the next line.
        $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
    }

    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        wp_send_json( array( 'code' => 'rest_cookie_invalid_nonce', 'message' => __( 'Cookie check failed', 'vergelabs-media-library' ), 'data' => array( 'status' => 403 ) ), 403 );
    }

    // The screens that load this bridge are for people who may upload; each route still asks its own permission_callback below.
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json( array( 'code' => 'rest_forbidden', 'message' => __( 'Sorry, you are not allowed to do that.', 'vergelabs-media-library' ), 'data' => array( 'status' => 403 ) ), 403 );
    }

    // A site that switched REST off, or limited it, has switched this off too.
    $auth = apply_filters( 'rest_authentication_errors', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own REST authentication filter, asked exactly as WP_REST_Server::check_authentication() asks it.

    if ( is_wp_error( $auth ) ) {
        $status = $auth->get_error_data();
        $status = is_array( $status ) && isset( $status['status'] ) ? (int) $status['status'] : 403;
        wp_send_json( array( 'code' => $auth->get_error_code(), 'message' => $auth->get_error_message(), 'data' => array( 'status' => $status ) ), $status );
    }

    $raw  = file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the request body, not a file.
    $body = json_decode( (string) $raw, true );

    if ( ! is_array( $body ) || ! isset( $body['route'] ) || ! is_string( $body['route'] ) ) {
        wp_send_json( array( 'code' => 'vergeml_bad_bridge', 'message' => __( 'The bridge needs a JSON body.', 'vergelabs-media-library' ), 'data' => array( 'status' => 400 ) ), 400 );
    }

    $route  = $body['route'];
    $method = isset( $body['method'] ) && is_string( $body['method'] ) ? strtoupper( $body['method'] ) : 'GET';

    if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
        $method = 'GET';
    }

    // Only a route this plugin registered.
    if ( ! vergeml_transport_is_ours( $route, $method ) ) {
        wp_send_json( array( 'code' => 'vergeml_bad_bridge', 'message' => __( 'That route does not belong to this plugin.', 'vergelabs-media-library' ), 'data' => array( 'status' => 400 ) ), 400 );
    }

    // Handed on as REST would hand them on: the route's own args schema validates and sanitises them.
    $params = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : array();

    $request = new WP_REST_Request( $method, $route );

    if ( 'GET' === $method ) {
        $request->set_query_params( $params );
    } else {
        $request->set_body_params( $params );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( $params ) );
    }

    $response = rest_do_request( $request );
    $server   = rest_get_server();
    $data     = $server->response_to_data( $response, false );

    if ( $response->is_error() ) {
        $error = $response->as_error();
        $data  = array(
            'code'    => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data'    => $error->get_error_data(),
        );
    }

    nocache_headers();
    header( 'X-VergeML-Transport: bridge' );
    wp_send_json( $data, $response->get_status() );
}


/**
 *  Whether the path is one of this plugin's routes, answering this method,
 *  matched the way the REST server matches it against the routes registered
 *  under our namespace and nothing else.
 */
function vergeml_transport_is_ours( $route, $method ) {

    if ( ! preg_match( '#^/' . preg_quote( VERGEML_REST_NS, '#' ) . '/[A-Za-z0-9/_-]+$#', $route ) || false !== strpos( $route, '..' ) ) {
        return false;
    }

    foreach ( rest_get_server()->get_routes( VERGEML_REST_NS ) as $pattern => $handlers ) {

        if ( ! preg_match( '@^' . $pattern . '$@i', $route ) ) {
            continue;
        }

        foreach ( $handlers as $handler ) {
            if ( ! empty( $handler['methods'][ $method ] ) ) {
                return true;
            }
        }
    }

    return false;
}


/**
 *  The browser half: js/vergeml-transport.js, the plugin's own script, after
 *  wp-api-fetch.
 *
 *  Registered, never enqueued by itself. Every script of the plugin that
 *  calls apiFetch names 'vergeml-transport' as its dependency instead of
 *  'wp-api-fetch', so WordPress prints it before the first of them runs --
 *  a middleware covers only the calls made after it is installed -- and only
 *  on the screens where they run. Core's wp-api-fetch is not touched, and a
 *  screen without the plugin's scripts (the block editor, any other plugin's
 *  page) never sees it. Until 4.1.0 it was printed inline after wp-api-fetch
 *  on every admin screen.
 */

add_action( 'init', 'vergeml_transport_register' );

function vergeml_transport_register() {

    wp_register_script(
        'vergeml-transport',
        plugins_url( 'js/vergeml-transport.js', VERGEML_FILE ),
        array( 'wp-api-fetch' ),
        vergeml_asset_ver( 'js/vergeml-transport.js' ),
        true
    );

    wp_localize_script( 'vergeml-transport', 'vergemlTransport', array(
        'ajax'      => admin_url( 'admin-ajax.php' ),
        'namespace' => VERGEML_REST_NS,
        'l10n'      => array(
            'blocked'  => __( 'WordPress’s REST API is blocked on this site, so folders are using a slower route. Your folders and files are safe. This is usually a security or caching plugin — see Help for what to allow.', 'vergelabs-media-library' ),
            /* translators: %s: an HTTP status code or a short reason. */
            'failed'   => __( 'The folders could not be refreshed (%s). Your folders and files are safe; the browser could not reach WordPress. Reload the page, or check for a caching or security plugin.', 'vergelabs-media-library' ),
            'retry'    => __( 'Try again', 'vergelabs-media-library' ),
        ),
    ) );
}
