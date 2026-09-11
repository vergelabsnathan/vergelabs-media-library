<?php
/**
 *  Plugin Name: VergeML uninstall probe (test only)
 *  Description: Answers ?vgml_probe=state with what the database holds, before and after the plugin is deleted. Mounted into a Playground as an mu-plugin by tools/uninstall-walk.mjs; never shipped.
 *
 *  It has to be an mu-plugin because the thing under test is the plugin
 *  being gone: once uninstall.php has run there is no plugin function to ask,
 *  no taxonomy registered, and no WP-CLI in a Playground to ask from outside.
 *  So this reads the tables directly, the same way uninstall.php writes them.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function () {

    if ( ! isset( $_GET['vgml_probe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only probe on a throwaway site.
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json( array( 'error' => 'not an administrator' ), 403 );
    }

    global $wpdb;

    $action = sanitize_key( (string) $_GET['vgml_probe'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( 'set-wipe' === $action ) {
        $v = isset( $_GET['v'] ) && '1' === $_GET['v'] ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        update_option( 'vergeml_uninstall_wipe', $v );
        wp_send_json( array( 'wipe' => get_option( 'vergeml_uninstall_wipe' ) ) );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the probe reads the tables uninstall.php writes, by name.

    $tt = $wpdb->get_results( "SELECT term_taxonomy_id, term_id, parent, count FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'media_category'", ARRAY_A );

    $terms = array();
    foreach ( (array) $tt as $row ) {
        $terms[] = array(
            'name'   => (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->terms} WHERE term_id = %d", (int) $row['term_id'] ) ),
            'parent' => (int) $row['parent'],
        );
    }

    $tt_ids = array_map( 'intval', wp_list_pluck( (array) $tt, 'term_taxonomy_id' ) );
    $links  = 0;
    if ( $tt_ids ) {
        $in    = implode( ',', $tt_ids );
        $links = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.post_type = 'attachment' AND tr.term_taxonomy_id IN ($in)" );
    }

    $attachments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );

    $files_present = 0;
    foreach ( (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'" ) as $id ) {
        $path = get_attached_file( (int) $id );
        if ( $path && file_exists( $path ) ) {
            $files_present++;
        }
    }

    $options    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'vergeml\_%'" );
    $transients = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_vergeml\_%' OR option_name LIKE '\_transient\_timeout\_vergeml\_%'" );
    $usermeta   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE 'vergeml\_%'" );

    $tables = array();
    foreach ( array( 'vergeml_ai_index', 'vergeml_librarian_batches', 'vergeml_librarian_moves', 'vergeml_organize_runs' ) as $t ) {
        $name = $wpdb->prefix . $t;
        // SHOW TABLES LIKE is what the SQLite layer and MySQL both answer.
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) === $name ) {
            $tables[] = $t;
        }
    }

    $cron = array();
    foreach ( array( 'vergeml_meaning_convert', 'vergeml_ai_run_tick' ) as $hook ) {
        if ( wp_next_scheduled( $hook ) ) {
            $cron[] = $hook;
        }
    }

    $log      = WP_CONTENT_DIR . '/debug.log';
    $log_hits = array();
    if ( file_exists( $log ) ) {
        foreach ( (array) file( $log ) as $line ) {
            if ( false !== stripos( $line, 'vergeml' ) || false !== stripos( $line, 'vergelabs-media-library' ) ) {
                $log_hits[] = trim( $line );
            }
        }
    }

    // phpcs:enable

    wp_send_json( array(
        'plugin_active' => function_exists( 'vergeml_ai_settings' ),
        'wipe'          => (string) get_option( 'vergeml_uninstall_wipe', '' ),
        'terms'         => $terms,
        'links'         => $links,
        'attachments'   => $attachments,
        'files_present' => $files_present,
        'options'       => $options,
        'transients'    => $transients,
        'usermeta'      => $usermeta,
        'tables'        => $tables,
        'cron'          => $cron,
        'log_hits'      => array_slice( $log_hits, 0, 20 ),
    ) );
} );
