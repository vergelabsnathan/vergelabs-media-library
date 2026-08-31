<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 *  A second road to every endpoint.
 *
 *  Everything this plugin does after the first paint goes through
 *  /wp-json/vergeml/v1/. That is the right design and it is also the single
 *  most fragile joint in every plugin of this kind: a security plugin that
 *  blocks REST for logged-in users, a host that rewrites /wp-json/ away, a
 *  caching layer that answers an API call with a cached HTML page. The two
 *  market leaders both fail there, and both fail by showing an empty sidebar
 *  that reads as "your folders are gone".
 *
 *  This is the same endpoints reached through admin-ajax.php instead. Not a
 *  second implementation: the request is handed to rest_do_request(), so the
 *  same handler runs with the same permission callback and the same
 *  validation, and the only thing that changed is which door it came in by.
 *  admin-ajax has worked on every WordPress host since 2.x; nothing blocks it
 *  without also breaking WordPress itself.
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

    if ( ! $nonce && isset( $_REQUEST['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified on the next line.
        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
    }

    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        wp_send_json( array( 'code' => 'rest_cookie_invalid_nonce', 'message' => __( 'Cookie check failed', 'vergelabs-media-library' ), 'data' => array( 'status' => 403 ) ), 403 );
    }

    $raw  = file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    $body = json_decode( (string) $raw, true );

    if ( ! is_array( $body ) ) {
        wp_send_json( array( 'code' => 'vergeml_bad_bridge', 'message' => 'The bridge needs a JSON body.', 'data' => array( 'status' => 400 ) ), 400 );
    }

    $route  = isset( $body['route'] ) ? (string) $body['route'] : '';
    $method = isset( $body['method'] ) ? strtoupper( (string) $body['method'] ) : 'GET';
    $params = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : array();

    // Only our own namespace. This is a road to our endpoints, not to all of them.
    if ( 0 !== strpos( $route, '/' . VERGEML_REST_NS . '/' ) ) {
        wp_send_json( array( 'code' => 'vergeml_bad_bridge', 'message' => 'Not one of ours.', 'data' => array( 'status' => 400 ) ), 400 );
    }

    if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
        $method = 'GET';
    }

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
 *  The browser half, loaded on every admin screen where our scripts run.
 *
 *  Registered against wp-api-fetch and enqueued early, so any of our scripts
 *  that calls apiFetch -- thirteen of them, sixty-odd call sites -- goes through
 *  the middleware without knowing it exists.
 */

add_action( 'admin_enqueue_scripts', 'vergeml_transport_assets', 1 );

function vergeml_transport_assets() {

    if ( ! current_user_can( 'upload_files' ) ) {
        return;
    }

    /*
     *  Attached to wp-api-fetch itself, not shipped as a script of our own.
     *
     *  A middleware only covers calls made after it is installed. As a
     *  separate file it ran after some of our scripts had already asked their
     *  first question -- the first version left one call unbridged on every
     *  page load, which is one unhandled 403 and one missing strip. Printed
     *  inline straight after wp-api-fetch, it is there before any script that
     *  depends on wp-api-fetch can run, whoever enqueued it and in what order.
     *
     *  The handle is still registered, without a source, so a script that names
     *  it as a dependency keeps resolving.
     */
    wp_register_script( 'vergeml-transport', false, array( 'wp-api-fetch' ), vergeml_asset_ver( 'js/vergeml-transport.js' ), true );

    static $source = null;

    if ( null === $source ) {
        $source = (string) file_get_contents( plugin_dir_path( VERGEML_FILE ) . 'js/vergeml-transport.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- our own file, read once per request.
    }

    wp_add_inline_script( 'wp-api-fetch', $source, 'after' );

    wp_localize_script( 'wp-api-fetch', 'vergemlTransport', array(
        'ajax'      => admin_url( 'admin-ajax.php' ),
        'namespace' => VERGEML_REST_NS,
        'l10n'      => array(
            'blocked'  => __( 'WordPress’s REST API is blocked on this site, so folders are using a slower route. Your folders and files are safe. This is usually a security or caching plugin — see Help for what to allow.', 'vergelabs-media-library' ),
            /* translators: %s: an HTTP status code or a short reason. */
            'failed'   => __( 'The folders could not be refreshed (%s). Your folders and files are safe; the browser could not reach WordPress. Reload the page, or check for a caching or security plugin.', 'vergelabs-media-library' ),
            'retry'    => __( 'Try again', 'vergelabs-media-library' ),
        ),
    ) );

    wp_enqueue_script( 'vergeml-transport' );
}
