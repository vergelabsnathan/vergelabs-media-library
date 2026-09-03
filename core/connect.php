<?php
/**
 *  Connecting a site to a licence, without anybody copying a key.
 *
 *  Pasting a licence key is four steps and a tab switch: buy, find the email,
 *  select the key, paste it. Most people lose one of those. This is one
 *  button: we send the administrator to vergelabsmedia.com carrying a state
 *  nonce and the address to come back to, they pick which licence this site
 *  should use, and they arrive back here with a short-lived code. The key
 *  itself is fetched server to server and never travels through the browser.
 *
 *  The state nonce is what makes the return trustworthy: it is minted here,
 *  kept in a transient for this user alone, and must come back unchanged.
 *
 *  @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const VERGEML_CONNECT_NONCE  = 'vergeml_connect';
// A day, not fifteen minutes: a new customer creates an account, confirms an
// email and buys a licence before coming back, and the link must still work.
// The state is single-use and bound to this administrator either way.
const VERGEML_CONNECT_TTL    = DAY_IN_SECONDS;

/** Where the handshake happens. Filterable so a staging service can be used. */
function vergeml_connect_base() {
    $base = defined( 'VERGEML_SITE_URL' ) ? VERGEML_SITE_URL : 'https://vergelabsmedia.com';

    return untrailingslashit( apply_filters( 'vergeml_connect_base', $base ) );
}

/** The nonce-protected URL behind the "Connect" button. */
function vergeml_connect_start_url() {
    return wp_nonce_url(
        admin_url( 'admin.php?page=media-licence&vergeml_connect=start' ),
        VERGEML_CONNECT_NONCE
    );
}

/** Whether this site already has a key of its own or from the network. */
function vergeml_connect_has_key() {
    $settings = function_exists( 'vergeml_ai_settings' ) ? vergeml_ai_settings() : array();

    return function_exists( 'vergeml_ai_unseal' )
        ? '' !== vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' )
        : ! empty( $settings['license_key'] );
}

/**
 *  The one place the handshake host is trusted, and only for the redirect
 *  that starts it. Left in place permanently, this would let anything on the
 *  admin redirect off-site.
 */
function vergeml_connect_allow_host( $hosts ) {
    $host = wp_parse_url( vergeml_connect_base(), PHP_URL_HOST );
    if ( is_string( $host ) && '' !== $host ) {
        $hosts[] = $host;
    }

    return $hosts;
}

add_action( 'admin_init', 'vergeml_connect_router' );

/** Both halves of the round trip arrive on admin_init. */
function vergeml_connect_router() {

    if ( ! isset( $_GET['vergeml_connect'] ) ) {
        return;
    }
    $step = sanitize_key( wp_unslash( $_GET['vergeml_connect'] ) );

    // Binding a paid licence to a site is an owner's decision, not an editor's.
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( 'start' === $step ) {
        vergeml_connect_start();
    } elseif ( 'callback' === $step ) {
        vergeml_connect_finish();
    }
}

/** Mint the state, remember it, and hand the browser to the service. */
function vergeml_connect_start() {

    if ( ! isset( $_GET['_wpnonce'] )
        || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), VERGEML_CONNECT_NONCE ) ) {
        // Back to the screen with its notice, not a dead white page.
        vergeml_connect_redirect_with( 'state' );
    }

    $state = wp_generate_password( 32, false, false );
    set_transient( 'vergeml_connect_state_' . get_current_user_id(), $state, VERGEML_CONNECT_TTL );

    $destination = add_query_arg(
        array(
            'state'        => rawurlencode( $state ),
            'site'         => rawurlencode( home_url() ),
            'redirect_uri' => rawurlencode( admin_url( 'admin.php?page=media-licence' ) ),
        ),
        vergeml_connect_base() . '/connect'
    );

    add_filter( 'allowed_redirect_hosts', 'vergeml_connect_allow_host' );
    wp_safe_redirect( esc_url_raw( $destination ) );
    exit;
}

/** Check the state, redeem the code for the key, store it. */
function vergeml_connect_finish() {

    $state  = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
    $code   = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
    $expect = get_transient( 'vergeml_connect_state_' . get_current_user_id() );

    delete_transient( 'vergeml_connect_state_' . get_current_user_id() );

    if ( '' === $code || ! is_string( $expect ) || ! hash_equals( $expect, $state ) ) {
        vergeml_connect_redirect_with( 'state' );
    }

    $response = wp_remote_post(
        vergeml_connect_base() . '/api/connect/exchange',
        array(
            'timeout' => 20,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array( 'code' => $code, 'site' => home_url() ) ),
        )
    );

    if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        vergeml_connect_redirect_with( 'exchange' );
    }

    $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || empty( $body['key'] ) ) {
        vergeml_connect_redirect_with( 'exchange' );
    }

    $settings                = get_option( 'vergeml_ai', array() );
    $settings                = is_array( $settings ) ? $settings : array();

    // Switching licence: give the old one its seat back on the service, so the
    // site is not counted against two licences at once. Best effort.
    $old = vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
    if ( '' !== $old && $old !== (string) $body['key'] ) {
        wp_remote_post(
            vergeml_ai_service_url() . '/licence',
            array(
                'timeout' => 8,
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( array( 'key' => $old, 'site' => home_url(), 'action' => 'deactivate' ) ),
            )
        );
    }

    // Sealed at rest, the same way the settings form stores it: the plugin
    // unseals on use, and a raw key here reads back as no licence at all.
    $settings['license_key'] = vergeml_ai_seal( sanitize_text_field( (string) $body['key'] ) );
    update_option( 'vergeml_ai', $settings, false );

    // The seat, then the balance. The exchange takes the seat server-side as
    // well; this covers an older service, and costs one request.
    if ( function_exists( 'vergeml_ai_activate_site' ) ) {
        vergeml_ai_activate_site();
    }
    // The screen this returns to shows the balance; fetch it now so the number
    // is right the moment the site is connected.
    if ( function_exists( 'vergeml_ai_refresh_credits' ) ) {
        vergeml_ai_refresh_credits( true );
    }

    vergeml_connect_redirect_with( 'connected' );
}

/** Back to the AI screen, saying what happened. */
function vergeml_connect_redirect_with( $result ) {
    wp_safe_redirect( admin_url( 'admin.php?page=media-licence&vergeml_connected=' . rawurlencode( $result ) ) );
    exit;
}

/**
 *  The invitation, and the result.
 *
 *  Printed by the AI screen itself rather than hooked to admin_notices: the
 *  shell clears every notice action on a VergeLabs screen, deliberately, so
 *  anything that belongs on one is drawn by it.
 *
 *  A site with no key cannot describe anything, so the button belongs exactly
 *  where somebody has just found that out.
 */
function vergeml_connect_banner() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_GET['vergeml_connected'] ) ) {
        $result = sanitize_key( wp_unslash( $_GET['vergeml_connected'] ) );

        if ( 'connected' === $result ) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html__( 'This site is connected. The AI features are ready to use.', 'vergelabs-media-library' )
            );
            return;
        }

        $why = 'state' === $result
            ? __( 'That connection could not be verified, so nothing was changed. Please try again.', 'vergelabs-media-library' )
            : __( 'The licence could not be fetched. Nothing was changed -- please try again, or paste your key by hand.', 'vergelabs-media-library' );

        printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $why ) );
    }

    /*
     *  Connected sites get their button in the licence row of the AI screen
     *  (see vergeml_ai_page), where somebody looks for it. A line up here in
     *  small grey type was not found by the one person who needed it. Only
     *  the no-key invitation and the result notices belong at the top.
     */
    if ( vergeml_connect_has_key() ) {
        return;
    }

    printf(
        '<div class="notice notice-info"><p><strong>%s</strong> %s</p><p><a class="button button-primary" href="%s">%s</a> <a href="%s" target="_blank" rel="noopener">%s</a></p></div>',
        esc_html__( 'Connect this site to use the AI features.', 'vergelabs-media-library' ),
        esc_html__( 'One button: sign in, pick your licence, and you are sent straight back with the key in place. No copying anything.', 'vergelabs-media-library' ),
        esc_url( vergeml_connect_start_url() ),
        esc_html__( 'Connect to VergeLabs', 'vergelabs-media-library' ),
        esc_url( vergeml_connect_base() . '/pricing' ),
        esc_html__( 'See the plans', 'vergelabs-media-library' )
    );
}
