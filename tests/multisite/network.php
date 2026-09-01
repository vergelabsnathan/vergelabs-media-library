<?php
/*
 *  Multisite, from activation to uninstall, on a throwaway network.
 *
 *  Run through tests/multisite/blueprint.json by tools/multisite.mjs. Every
 *  line is PASS or FAIL with what was measured; the runner fails on any FAIL.
 *  The network is Playground's and is destroyed afterwards, so the last test
 *  may run the real uninstall.php with the network wipe switched on.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 *  $GLOBALS, never a bare global: `wp eval-file` runs this file inside a
 *  function, so a top-level `$vgml_fail = 0` is a local and `global $vgml_fail`
 *  in the helper binds to something else -- the suite would then report
 *  ALL PASSED over a page of FAIL lines. It did, once.
 */
$GLOBALS['vgml_fail']         = 0;
$GLOBALS['vgml_results_file'] = __DIR__ . '/results.txt';

function vgml_t( $name, $ok, $detail = '' ) {
    if ( ! $ok ) { $GLOBALS['vgml_fail']++; }
    $line = ( $ok ? 'PASS' : 'FAIL' ) . '  ' . $name . ( '' !== $detail ? '  -- ' . $detail : '' ) . "\n";
    echo $line;
    @file_put_contents( $GLOBALS['vgml_results_file'], $line, FILE_APPEND );
}

function vgml_tables_for( $blog_id ) {
    global $wpdb;
    $prefix = $wpdb->get_blog_prefix( $blog_id );
    $have   = array();
    foreach ( array( 'vergeml_ai_index', 'vergeml_librarian_batches', 'vergeml_librarian_moves', 'vergeml_organize_runs' ) as $t ) {
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . $t ) ) ) {
            $have[] = $t;
        }
    }
    return $have;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/ms.php';

wp_set_current_user( 1 );
grant_super_admin( 1 );

$plugin = 'vergelabs-media-library/vergelabs-media-library.php';

vgml_t( 'this is a network', is_multisite() );

/* ---- network activation provisions the main site ---------------------- */

if ( is_plugin_active( $plugin ) ) {
    deactivate_plugins( $plugin );
}
$result = activate_plugin( $plugin, '', true );
vgml_t( 'network activation succeeds', ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : '' );
vgml_t( 'plugin is network active', is_plugin_active_for_network( $plugin ) );

$tables = vgml_tables_for( 1 );
vgml_t( 'main site has all four tables', 4 === count( $tables ), implode( ',', $tables ) );
vgml_t( 'main site has its options', is_array( get_option( 'vergeml_taxonomies' ) ) && '' !== get_option( 'vergeml_version', '' ), 'version=' . get_option( 'vergeml_version', '' ) );
vgml_t( 'network options written', is_array( get_site_option( 'vergeml_network_options' ) ) );

/* ---- a new site is provisioned the moment it exists ------------------- */

$network = get_network();
$site_id = wp_insert_site( array(
    'domain'     => $network->domain,
    'path'       => trailingslashit( $network->path ) . 'client-two/',
    'network_id' => $network->id,
    'title'      => 'Client two',
    'user_id'    => 1,
) );

vgml_t( 'second site created', ! is_wp_error( $site_id ) && (int) $site_id > 1, is_wp_error( $site_id ) ? $site_id->get_error_message() : 'id=' . $site_id );

if ( ! is_wp_error( $site_id ) ) {

    $tables2 = vgml_tables_for( (int) $site_id );
    vgml_t( 'new site has all four tables without anyone visiting it', 4 === count( $tables2 ), implode( ',', $tables2 ) );

    switch_to_blog( (int) $site_id );
    vgml_t( 'new site has its options', is_array( get_option( 'vergeml_taxonomies' ) ) && VERGEML_VERSION === get_option( 'vergeml_version' ), 'version=' . get_option( 'vergeml_version', '' ) );
    vgml_t( 'new site sees a folder taxonomy definition', isset( get_option( 'vergeml_taxonomies' )['media_category'] ) );

    // Request caches do not leak across the switch.
    $GLOBALS['vergeml_index_primed'] = array( 1 => array( 'alt' => 'from site one' ) );
    restore_current_blog();
    vgml_t( 'switch_blog forgets request caches', ! isset( $GLOBALS['vergeml_index_primed'] ) );
}

/* ---- the network settings save keeps its own flags ------------------- */

// Admin-only files under WP-CLI: the settings screens are what is under test.
if ( ! function_exists( 'vergeml_update_network_settings' ) ) {
    foreach ( array( 'core/options-pages.php' ) as $vgml_admin_file ) {
        if ( file_exists( WP_PLUGIN_DIR . '/vergelabs-media-library/' . $vgml_admin_file ) ) {
            require_once WP_PLUGIN_DIR . '/vergelabs-media-library/' . $vgml_admin_file;
        }
    }
}

update_site_option( 'vergeml_network_options', array( 'media_settings' => 1, 'utilities' => 1 ) );
$_POST['eml-submit-network-settings'] = '1';
$_POST['vergeml_network_options']     = array( 'media_settings' => '0', 'utilities' => '1', 'uninstall_wipe' => '0' );
$_REQUEST['_wpnonce'] = $_POST['_wpnonce'] = wp_create_nonce( 'eml-network-settings-options' );
if ( function_exists( 'vergeml_update_network_settings' ) ) {
    vergeml_update_network_settings();
    $after = get_site_option( 'vergeml_network_options' );
    vgml_t( 'network settings save round-trips the flags', is_array( $after ) && 0 === (int) $after['media_settings'] && 1 === (int) $after['utilities'], wp_json_encode( $after ) );
    vgml_t( 'flag helper reads the saved values', ! vergeml_network_flag( 'media_settings' ) && vergeml_network_flag( 'utilities' ) );
    delete_site_option( 'vergeml_network_options' );
    vgml_t( 'flag helper defaults to allowed when the option is missing', vergeml_network_flag( 'media_settings' ) && vergeml_network_flag( 'utilities' ) );
} else {
    vgml_t( 'network settings functions loaded', false, 'options-pages.php not loaded in this context' );
}
unset( $_POST['eml-submit-network-settings'], $_POST['vergeml_network_options'] );

/* ---- the network licence is inherited, and a lock holds --------------- */

if ( function_exists( 'vergeml_ai_seal' ) && ! is_wp_error( $site_id ) ) {
    update_site_option( 'vergeml_ai_network', array( 'license_key' => vergeml_ai_seal( 'VGML-NETWORK-KEY-0001' ), 'lock' => 1 ) );
    switch_to_blog( (int) $site_id );
    $s = vergeml_ai_settings();
    vgml_t( 'subsite inherits the locked network key', 'VGML-NETWORK-KEY-0001' === vergeml_ai_unseal( $s['license_key'] ) && ! empty( $s['network_locked'] ) );
    restore_current_blog();
    delete_site_option( 'vergeml_ai_network' );
}

/* ---- the watchdog never deactivates the network ---------------------- */

update_site_option( 'vergeml_watchdog_network', array( (int) ( is_wp_error( $site_id ) ? 1 : $site_id ) => array( 'at' => time(), 'message' => 'probe', 'file' => 'x', 'line' => 1 ) ) );
if ( ! is_wp_error( $site_id ) ) {
    switch_to_blog( (int) $site_id );
    update_option( 'vergeml_watchdog', array( 'safe' => true, 'safe_since' => time() - 3600 ), true );
    restore_current_blog();
}
ob_start();
if ( function_exists( 'vergeml_watchdog_network_notice' ) ) { vergeml_watchdog_network_notice(); }
$notice = ob_get_clean();
vgml_t( 'network admin notice names the site in safe mode', false !== strpos( $notice, 'Client two' ), substr( wp_strip_all_tags( $notice ), 0, 80 ) );
vgml_t( 'plugin still network active after a site went into safe mode', is_plugin_active_for_network( $plugin ) );
if ( ! is_wp_error( $site_id ) ) {
    switch_to_blog( (int) $site_id );
    delete_option( 'vergeml_watchdog' );
    restore_current_blog();
}
delete_site_option( 'vergeml_watchdog_network' );

/* ---- deleting a site drops its tables --------------------------------- */

if ( ! is_wp_error( $site_id ) ) {
    $deleted = wp_delete_site( (int) $site_id );
    $left    = vgml_tables_for( (int) $site_id );
    vgml_t( 'deleting the site drops all four tables', ! is_wp_error( $deleted ) && 0 === count( $left ), implode( ',', $left ) );
}

/* ---- uninstall with the network wipe on ------------------------------- */

update_site_option( 'vergeml_uninstall_wipe_network', 1 );
set_transient( 'vergeml_probe', 'x', 300 );
deactivate_plugins( $plugin, true, true );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    define( 'WP_UNINSTALL_PLUGIN', true );
}
include WP_PLUGIN_DIR . '/vergelabs-media-library/uninstall.php';

// uninstall.php deletes rows with SQL; in a real uninstall the next request
// starts cold. Here the same request would keep answering from the option
// cache, so it is emptied before asking the database what is left.
wp_cache_flush();

vgml_t( 'uninstall cleared transients', false === get_transient( 'vergeml_probe' ) );
vgml_t( 'uninstall wiped the main site options', ! get_option( 'vergeml_taxonomies' ) && '' === get_option( 'vergeml_version', '' ) );
vgml_t( 'uninstall dropped the main site tables', 0 === count( vgml_tables_for( 1 ) ), implode( ',', vgml_tables_for( 1 ) ) );
vgml_t( 'uninstall removed the network options', false === get_site_option( 'vergeml_network_options' ) && false === get_site_option( 'vergeml_uninstall_wipe_network' ) );

$vgml_summary = "\n" . ( $GLOBALS['vgml_fail'] ? $GLOBALS['vgml_fail'] . ' FAILED' : 'ALL PASSED' ) . "\n";
echo $vgml_summary;
@file_put_contents( $GLOBALS['vgml_results_file'], $vgml_summary, FILE_APPEND );
