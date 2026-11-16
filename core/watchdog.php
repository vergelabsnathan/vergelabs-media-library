<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Safe mode, switched by the site owner.
 *
 *  define( 'VERGEML_SAFE_MODE', true ) in wp-config.php stops the feature
 *  files from loading: folders, the media views and the AI layer are off, the
 *  System report and Get help stay, and nothing is deleted. A fatal error in
 *  the plugin is WordPress's own recovery mode's to handle; the plugin no
 *  longer watches for crashes or switches anything off by itself.
 *
 *  @since 2.10.1
 *  @since 4.1.0 owner-switched only
 */

function vergeml_safe_mode() {

    return defined( 'VERGEML_SAFE_MODE' ) && VERGEML_SAFE_MODE;
}


add_action( 'admin_notices', 'vergeml_safe_mode_notice' );
add_action( 'network_admin_notices', 'vergeml_safe_mode_notice' );

function vergeml_safe_mode_notice() {

    if ( ! vergeml_safe_mode() || ! current_user_can( 'activate_plugins' ) )
        return;

    echo '<div class="notice notice-warning"><p><strong>' .
        esc_html__( 'VergeLabs Media Library is in safe mode.', 'vergelabs-media-library' ) .
        '</strong> ' .
        esc_html__( 'VERGEML_SAFE_MODE is set in wp-config.php, so the plugin\'s features are off. Nothing has been deleted and your settings are intact; remove the line to switch them back on.', 'vergelabs-media-library' ) .
        '</p></div>';
}
