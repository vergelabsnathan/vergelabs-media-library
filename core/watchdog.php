<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Recovery from a fatal error in this plugin's own code.
 *
 *  The situation this exists for: a plugin fatals on every request, the site
 *  shows a white screen, and the only way back in is FTP -- because wp-admin is
 *  behind the same fatal that took the front end down. WordPress 5.2 added
 *  recovery mode, which helps, but it depends on the admin email arriving and
 *  on the fatal being caught in a context where core's handler runs.
 *
 *  So this plugin watches itself. It does not watch anything else: a fatal is
 *  only counted when the file that died is inside this plugin's own directory.
 *  Deactivating on somebody else's crash would be both wrong and infuriating.
 *
 *  Three rungs, deliberately:
 *
 *    1. A fatal in our code is recorded. Once is not a pattern -- a one-off can
 *       be a timeout, a corrupt upload, a host restarting mid-request.
 *    2. Twice inside an hour and the plugin puts itself in SAFE MODE: the
 *       feature files stop loading, so whatever crashed is no longer running,
 *       and the site comes back. The plugin stays active, which is the point --
 *       it can still show an admin notice explaining what happened and offer to
 *       switch back on.
 *    3. A fatal while already in safe mode means the small remaining shell is
 *       itself broken, so it deactivates outright. That is the last resort, and
 *       it is still better than a dead site.
 *
 *  Set VERGEML_NO_WATCHDOG in wp-config.php to switch all of this off.
 *
 *  @since 2.10.1
 */


/**
 *  vergeml_watchdog_boot
 *
 *  Called as early as the plugin loads, so the handler is in place before any
 *  of the code it is guarding runs.
 */

function vergeml_watchdog_boot() {

    if ( defined( 'VERGEML_NO_WATCHDOG' ) && VERGEML_NO_WATCHDOG )
        return;

    /*
     *  A quarter of a megabyte, held only so it can be given back. Memory
     *  exhaustion is one of the commonest causes of a white screen, and a
     *  handler that needs to write to the database cannot do it in a process
     *  with nothing left. Released first thing in the handler below.
     */
    $GLOBALS['vergeml_watchdog_reserve'] = str_repeat( ' ', 256 * 1024 );

    register_shutdown_function( 'vergeml_watchdog_shutdown' );
}


/**
 *  vergeml_watchdog_shutdown
 *
 *  Runs at the end of every request, including the ones that ended badly.
 */

function vergeml_watchdog_shutdown() {

    unset( $GLOBALS['vergeml_watchdog_reserve'] );

    $error = error_get_last();

    if ( ! is_array( $error ) || ! isset( $error['type'], $error['file'] ) )
        return;

    $fatal = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );

    if ( ! in_array( (int) $error['type'], $fatal, true ) )
        return;

    // Ours, or somebody else's? Only ours counts.
    if ( ! vergeml_watchdog_is_ours( $error['file'] ) )
        return;

    vergeml_watchdog_strike( $error );
}


/**
 *  vergeml_watchdog_is_ours
 *
 *  Path comparison, normalised, because Windows hosts report backslashes and a
 *  string compare would quietly never match.
 */

function vergeml_watchdog_is_ours( $file ) {

    $dir  = defined( 'VERGEML_FILE' )
        ? wp_normalize_path( plugin_dir_path( VERGEML_FILE ) )
        : trailingslashit( wp_normalize_path( dirname( dirname( __FILE__ ) ) ) );
    $file = wp_normalize_path( (string) $file );

    return '' !== $dir && 0 === strpos( $file, $dir );
}


function vergeml_watchdog_state() {

    $state = get_option( 'vergeml_watchdog', array() );

    return is_array( $state ) ? $state : array();
}


/**
 *  vergeml_watchdog_strike
 *
 *  Records the crash and decides which rung of the ladder we are on.
 */

function vergeml_watchdog_strike( $error ) {

    $prev = vergeml_watchdog_state();
    $now  = time();

    // Strikes expire, so two unrelated crashes months apart never add up to a
    // plugin that switched itself off for no reason the user can see.
    $recent = isset( $prev['at'] ) && ( $now - (int) $prev['at'] ) < HOUR_IN_SECONDS;
    $count  = $recent && isset( $prev['count'] ) ? (int) $prev['count'] + 1 : 1;

    $was_safe   = ! empty( $prev['safe'] );
    $safe_since = isset( $prev['safe_since'] ) ? (int) $prev['safe_since'] : 0;

    $state = array(
        'count'      => $count,
        'at'         => $now,
        'file'       => (string) $error['file'],
        'line'       => isset( $error['line'] ) ? (int) $error['line'] : 0,
        'message'    => isset( $error['message'] ) ? substr( (string) $error['message'], 0, 500 ) : '',
        'safe'       => $was_safe,
        'safe_since' => $safe_since,
    );

    /*
     *  Rung three -- but only once safe mode has had a minute to prove itself.
     *
     *  One broken page view is not one PHP request. It is the page, its admin
     *  ajax, its favicon, whatever else the browser asks for, and every one of
     *  them fatals. Without this delay three of them land in the same second
     *  and the plugin goes from healthy to deactivated without safe mode ever
     *  being given the chance it exists for. Measured, not assumed: it is
     *  exactly what happened the first time this was tested.
     */
    if ( $was_safe && $safe_since > 0 && ( $now - $safe_since ) >= MINUTE_IN_SECONDS ) {

        if ( ! function_exists( 'deactivate_plugins' ) )
            require_once ABSPATH . 'wp-admin/includes/plugin.php';

        /*
         *  On a network, never. deactivate_plugins() on a network-activated
         *  plugin deactivates it for every site -- one subsite with a broken
         *  upload would have switched the folders off for the whole agency.
         *  That site stays in safe mode (its own option, its own notice), and
         *  the network admin gets a notice naming it, so the person who can
         *  actually judge whether this is the plugin or that site's data is
         *  the one who decides.
         */
        if ( is_multisite() && is_plugin_active_for_network( plugin_basename( VERGEML_FILE ) ) ) {

            $network = get_site_option( 'vergeml_watchdog_network', array() );
            $network = is_array( $network ) ? $network : array();

            $network[ get_current_blog_id() ] = array(
                'at'      => $now,
                'message' => $state['message'],
                'file'    => $state['file'],
                'line'    => $state['line'],
            );

            update_site_option( 'vergeml_watchdog_network', $network );
            update_option( 'vergeml_watchdog', $state, true );

            return;
        }

        $state['deactivated_at'] = $now;
        update_option( 'vergeml_watchdog', $state, true );

        // Silently: the deactivation hooks are more code, and more code is the
        // thing currently failing.
        deactivate_plugins( plugin_basename( VERGEML_FILE ), false );

        return;
    }

    // Rung two: stop loading the features and let the site come back.
    if ( ! $was_safe && $count >= 2 ) {
        $state['safe']       = true;
        $state['safe_since'] = $now;
    }

    update_option( 'vergeml_watchdog', $state, true );
}


/**
 *  vergeml_safe_mode
 *
 *  Whether the feature files should load. Read once per request and cached,
 *  because it is consulted from the top of the plugin.
 */

function vergeml_safe_mode() {

    if ( defined( 'VERGEML_SAFE_MODE' ) && VERGEML_SAFE_MODE )
        return true;

    /*
     *  get_option, and cached for the request.
     *
     *  This read used to go through wp_load_alloptions() to save a query. It
     *  was wrong: whether a row appears there depends on its autoload flag, and
     *  a row written by an earlier version did not have it. The result was the
     *  worst possible failure -- safe mode recorded and the notice shown, while
     *  the features carried on loading, so the thing was reporting a protection
     *  it was not applying. Found by looking at a real site rather than at the
     *  code. One cached read per request is the right price for being right.
     */
    // Per site: a network job that switches blogs must not carry one site's
    // answer to the next.
    static $safe = array();

    $blog = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;

    if ( isset( $safe[ $blog ] ) )
        return $safe[ $blog ];

    $state         = get_option( 'vergeml_watchdog', array() );
    $safe[ $blog ] = is_array( $state ) && ! empty( $state['safe'] );

    return $safe[ $blog ];
}


/**
 *  The notice. This is the whole reason safe mode is preferred to switching the
 *  plugin off: something has to be left running that can explain what happened.
 */

add_action( 'admin_notices', 'vergeml_watchdog_notice' );

function vergeml_watchdog_notice() {

    if ( ! vergeml_safe_mode() || ! current_user_can( 'activate_plugins' ) )
        return;

    $state = vergeml_watchdog_state();

    $resume = wp_nonce_url(
        admin_url( 'admin-post.php?action=vergeml_watchdog_resume' ),
        'vergeml_watchdog_resume'
    );

    echo '<div class="notice notice-warning"><p><strong>' .
        esc_html__( 'VergeLabs Media Library is in safe mode.', 'vergelabs-media-library' ) .
        '</strong> ' .
        esc_html__( 'It stopped its own features after a fatal error, so the site would keep working instead of showing a white screen. Nothing has been deleted and your settings are intact.', 'vergelabs-media-library' ) .
        '</p>';

    if ( ! empty( $state['message'] ) ) {
        printf(
            '<p><code>%s</code><br><span class="description">%s:%d</span></p>',
            esc_html( $state['message'] ),
            esc_html( str_replace( wp_normalize_path( WP_PLUGIN_DIR ) . '/', '', wp_normalize_path( $state['file'] ) ) ),
            (int) $state['line']
        );
    }

    echo '<p><a href="' . esc_url( $resume ) . '" class="button button-primary">' .
        esc_html__( 'Switch the features back on', 'vergelabs-media-library' ) .
        '</a> <a href="' . esc_url( admin_url( 'options-general.php?page=vergeml-settings&tab=report' ) ) . '" class="button">' .
        esc_html__( 'System report', 'vergelabs-media-library' ) .
        '</a></p></div>';
}


/*
 *  The network administrator's view: which sites are in safe mode, with the
 *  error each one hit and a link into that site's dashboard where the resume
 *  button lives. Without this a super admin who never visits site 47 never
 *  learns that site 47 has had no folders for a week.
 */
add_action( 'network_admin_notices', 'vergeml_watchdog_network_notice' );

function vergeml_watchdog_network_notice() {

    if ( ! current_user_can( 'manage_network_options' ) )
        return;

    $network = get_site_option( 'vergeml_watchdog_network', array() );

    if ( ! is_array( $network ) || ! $network )
        return;

    // Only sites still in safe mode; a resumed site drops out of the list.
    $still = array();

    foreach ( $network as $blog_id => $entry ) {
        switch_to_blog( (int) $blog_id );
        $state = get_option( 'vergeml_watchdog', array() );
        if ( is_array( $state ) && ! empty( $state['safe'] ) ) {
            $still[ (int) $blog_id ] = $entry;
        }
        restore_current_blog();
    }

    if ( count( $still ) !== count( $network ) ) {
        update_site_option( 'vergeml_watchdog_network', $still );
    }

    if ( ! $still )
        return;

    echo '<div class="notice notice-warning"><p><strong>' .
        esc_html__( 'VergeLabs Media Library is in safe mode on these sites:', 'vergelabs-media-library' ) .
        '</strong></p><ul>';

    foreach ( $still as $blog_id => $entry ) {
        $name = get_blog_option( $blog_id, 'blogname', '#' . $blog_id );
        printf(
            '<li><a href="%s">%s</a> &mdash; <code>%s</code></li>',
            esc_url( get_admin_url( $blog_id ) ),
            esc_html( $name ),
            esc_html( isset( $entry['message'] ) ? substr( (string) $entry['message'], 0, 160 ) : '' )
        );
    }

    echo '</ul><p>' . esc_html__( 'Each site keeps working without the plugin\'s features; open it to switch them back on. The plugin was not deactivated for the network.', 'vergelabs-media-library' ) . '</p></div>';
}


add_action( 'admin_post_vergeml_watchdog_resume', 'vergeml_watchdog_resume' );

function vergeml_watchdog_resume() {

    if ( ! current_user_can( 'activate_plugins' ) )
        wp_die( esc_html__( 'You do not have permission to do that.', 'vergelabs-media-library' ) );

    check_admin_referer( 'vergeml_watchdog_resume' );

    /*
     *  Cleared entirely rather than just unsetting the flag: leaving the strike
     *  count behind would mean the very next crash tripped safe mode again
     *  immediately, which reads as "it did not work".
     */
    delete_option( 'vergeml_watchdog' );

    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'plugins.php' ) );
    exit;
}
