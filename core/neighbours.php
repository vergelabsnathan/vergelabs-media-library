<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Neighbours.
 *
 *  Two kinds of plugin sit next to this one and can quietly break it: another
 *  folder plugin, and an optimiser. Neither is an error on their part; both
 *  are worth a word before the support ticket.
 *
 *  A second folder plugin filters the same media queries -- often the same
 *  media_category taxonomy -- and two filters on one query intersect rather
 *  than add up. The visible symptom is "my files disappeared", and nothing on
 *  the screen says why. So: one notice, once per person, naming the plugin.
 *  Not a refusal to run; agencies migrate one site at a time and want both
 *  active for an afternoon.
 *
 *  An optimiser that combines or defers scripts can move the transport
 *  fallback -- printed inline after wp-api-fetch on purpose -- away from the
 *  script it patches. Our files are ES5 and survive minification; it is the
 *  order that matters. WP Rocket exposes filters for exactly this, and the
 *  attributes below are read by Autoptimize, LiteSpeed Cache and Cloudflare
 *  Rocket Loader.
 *
 *  @since 3.14
 */


/* ------------------------------------------------------- other folder plugins */

/**
 *  vergeml_neighbour_folder_plugins
 *
 *  Active plugins that also put folders on the media library.
 *
 *  @return array  slug => human name, for the ones active right now.
 */

function vergeml_neighbour_folder_plugins() {

    $known = array(
        'filebird/filebird.php'                       => 'FileBird',
        'filebird-pro/filebird.php'                   => 'FileBird Pro',
        'real-media-library-lite/index.php'           => 'Real Media Library',
        'real-media-library/index.php'                => 'Real Media Library',
        'folders/folders.php'                         => 'Folders (Premio)',
        'folders-pro/folders.php'                     => 'Folders Pro (Premio)',
        'happyfiles/happyfiles.php'                   => 'HappyFiles',
        'happyfiles-pro/happyfiles.php'               => 'HappyFiles Pro',
        'wicked-folders/wicked-folders.php'           => 'Wicked Folders',
        'wicked-folders-pro/wicked-folders-pro.php'   => 'Wicked Folders Pro',
        'wp-media-folder/wp-media-folder.php'         => 'WP Media Folder',
        'enhanced-media-library/enhanced-media-library.php'         => 'Enhanced Media Library',
        'enhanced-media-library-pro/enhanced-media-library-pro.php' => 'Enhanced Media Library PRO',
    );

    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $active = array();

    foreach ( $known as $slug => $name ) {
        if ( is_plugin_active( $slug ) ) {
            $active[ $slug ] = $name;
        }
    }

    return $active;
}


add_action( 'admin_notices', 'vergeml_neighbour_notice' );

function vergeml_neighbour_notice() {

    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    // The media screens and this plugin's own; nowhere else.
    $ours = $screen && ( 'upload' === $screen->base || 'attachment' === $screen->post_type
        || false !== strpos( (string) $screen->id, 'vergelabs' ) || false !== strpos( (string) $screen->id, 'media-' ) );

    if ( ! $ours ) {
        return;
    }

    $others = vergeml_neighbour_folder_plugins();

    if ( ! $others ) {
        return;
    }

    $key = 'vergeml_neighbours_' . md5( implode( ',', array_keys( $others ) ) );

    if ( get_user_meta( get_current_user_id(), $key, true ) ) {
        return;
    }

    $names = implode( ', ', array_unique( array_values( $others ) ) );
    $url   = wp_nonce_url( add_query_arg( 'vergeml_dismiss_neighbours', $key ), 'vergeml_dismiss_neighbours' );

    echo '<div class="notice notice-warning is-dismissible vgml-neighbour-notice"><p>';
    printf(
        /* translators: 1: the other plugin's name, 2: this plugin's name. */
        esc_html__( '%1$s is also active and also puts folders on the media library. Two folder plugins filter the same lists, so files can seem to vanish from one while they sit in the other. Finish moving into %2$s -- the importer under Folders is built for that -- and then deactivate the other one.', 'vergelabs-media-library' ),
        esc_html( $names ),
        'VergeLabs Media Library'
    );
    echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Understood, do not show this again', 'vergelabs-media-library' ) . '</a>';
    echo '</p></div>';
}


add_action( 'admin_init', 'vergeml_neighbour_dismiss' );

function vergeml_neighbour_dismiss() {

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified two lines down.
    if ( empty( $_GET['vergeml_dismiss_neighbours'] ) ) {
        return;
    }

    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'vergeml_dismiss_neighbours' ) ) {
        return;
    }

    $key = preg_replace( '/[^a-z0-9_]/', '', (string) wp_unslash( $_GET['vergeml_dismiss_neighbours'] ) );

    if ( 0 === strpos( $key, 'vergeml_neighbours_' ) ) {
        update_user_meta( get_current_user_id(), $key, 1 );
    }

    wp_safe_redirect( remove_query_arg( array( 'vergeml_dismiss_neighbours', '_wpnonce' ) ) );
    exit;
}


/* -------------------------------------------------------------- optimisers */

/**
 *  vergeml_neighbour_is_ours
 *
 *  Whether a script handle is one of this plugin's.
 */

function vergeml_neighbour_is_ours( $handle ) {
    return 0 === strpos( (string) $handle, 'vergeml-' );
}


/*
 *  WP Rocket: leave our scripts out of combine, minify and defer. The
 *  patterns are matched against the script URL.
 */
function vergeml_neighbour_rocket_exclude( $excluded ) {
    $excluded   = (array) $excluded;
    $excluded[] = '/wp-content/plugins/vergelabs-media-library/js/(.*).js';
    $excluded[] = 'wp-includes/js/dist/api-fetch(.*).js'; // the transport is printed after this one
    return $excluded;
}

add_filter( 'rocket_exclude_js', 'vergeml_neighbour_rocket_exclude' );
add_filter( 'rocket_exclude_defer_js', 'vergeml_neighbour_rocket_exclude' );
add_filter( 'rocket_delay_js_exclusions', 'vergeml_neighbour_rocket_exclude' );


/*
 *  Everyone else reads attributes: Autoptimize and LiteSpeed honour
 *  data-no-optimize / data-no-minify, Cloudflare's Rocket Loader honours
 *  data-cfasync="false". Admin scripts only; the front end has none of ours
 *  except in page builders, where the same rule applies.
 */
add_filter( 'script_loader_tag', 'vergeml_neighbour_script_tag', 10, 2 );

function vergeml_neighbour_script_tag( $tag, $handle ) {

    if ( ! vergeml_neighbour_is_ours( $handle ) || false !== strpos( $tag, 'data-no-optimize' ) ) {
        return $tag;
    }

    return str_replace( '<script ', '<script data-no-optimize="1" data-no-minify="1" data-cfasync="false" ', $tag );
}
