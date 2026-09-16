<?php
/**
 *  Connect the box's second network to the box's own licence.
 *
 *      bash tools/box-connect-ms2.sh
 *
 *  The licence key at rest is sealed with the site's auth salt, so the main
 *  site's option cannot be copied over: the wrapper unseals it there, hands
 *  it here in the environment, and this seals it again for this site, takes
 *  the site's seat on the licence (an agency licence: sites are not counted)
 *  and asks for the balance. Prints what state the site is in and never the
 *  key. Spends nothing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$key = (string) getenv( 'VGML_KEY' );

if ( '' === $key ) {
    echo "no key in the environment\n";
    return;
}

$settings                = get_option( 'vergeml_ai', array() );
$settings                = is_array( $settings ) ? $settings : array();
$settings['license_key'] = vergeml_ai_seal( $key );
update_option( 'vergeml_ai', $settings, false );

printf( "sealed: %s\n", '' !== vergeml_ai_unseal( $settings['license_key'] ) ? 'yes' : 'NO' );
printf( "service: %s\n", vergeml_ai_service_url() );

$seat = vergeml_ai_activate_site();
printf( "seat: %s\n", true === $seat ? 'taken' : 'REFUSED ' . $seat->get_error_message() );

$balance = vergeml_ai_refresh_credits( true );
printf( "credits: %s\n", null === $balance ? 'unknown' : (string) $balance );
printf( "ready: %s\n", vergeml_ai_ready() ? 'yes' : 'NO' );
