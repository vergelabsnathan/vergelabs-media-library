<?php
/*
 *  The same number, asked for in every place that shows it.
 *
 *  Three readers, and they have disagreed before: the dashboard read a cached
 *  option straight while the AI screen asked the service, and the two drifted
 *  apart by a whole purchase. This prints all of them side by side, including
 *  what the service itself says, which is the only one that is authoritative.
 */

$settings = vergeml_ai_settings();
$licence  = vergeml_ai_unseal( $settings['license_key'] );

printf( "licence on this site: %s\n\n", '' === $licence ? '(none)' : substr( $licence, 0, 8 ) . '...' );

if ( '' === $licence ) {
    echo "no licence, so nothing to compare\n";
    return;
}

// 1. what the plugin has cached in its own option
$cached = get_option( 'vergeml_ai_credits', null );
printf( "  1. cached option (vergeml_ai_credits)   %s\n",
    null === $cached ? '(unset)' : var_export( $cached, true ) );

// 2. what the plugin's own refresh helper returns
if ( function_exists( 'vergeml_ai_refresh_credits' ) ) {
    // Forced, or the TTL returns the cache without ever calling out.
    $fresh = vergeml_ai_refresh_credits( true );
    printf( "  2. vergeml_ai_refresh_credits()         %s\n", var_export( $fresh, true ) );
}

// 3. what the service says, asked directly
$response = wp_remote_post( vergeml_ai_service_url() . '/licence', array(
    'timeout'   => 20,
    'headers'   => array( 'Content-Type' => 'application/json' ),
    'body'      => wp_json_encode( array(
        'license_key' => $licence,
        'site'        => home_url(),
    ) ),
) );

if ( is_wp_error( $response ) ) {
    printf( "  3. service                              unreachable: %s\n", $response->get_error_message() );
} else {
    $code = (int) wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    printf( "  3. service /verify (HTTP %d)             %s\n",
        $code,
        is_array( $body ) && isset( $body['credits'] ) ? var_export( $body['credits'], true ) : '(no credits field)' );
    if ( is_array( $body ) ) {
        printf( "     fields returned: %s\n", implode( ', ', array_keys( $body ) ) );
    }
}

// 4. and what the option says again, after the refresh above
printf( "  4. cached option, after refresh         %s\n",
    var_export( get_option( 'vergeml_ai_credits', null ), true ) );

printf( "\nsite url as this install reports it: %s\n", home_url() );
