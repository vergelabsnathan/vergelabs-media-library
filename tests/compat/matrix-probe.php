<?php
/**
 *  Plugin Name: VergeML matrix probe (test only)
 *  Description: Answers ?vgml_matrix=state with what the site holds -- folders, files, links, locale, the debug log. Written into a Playground as an mu-plugin by tools/matrix.mjs; never shipped.
 *
 *  An mu-plugin for the same reason tests/uninstall/probe.php is one: the
 *  last step of the five-minute script deletes the plugin, and the check
 *  after that has nothing of ours left to ask. So this reads the tables the
 *  way uninstall.php writes them, and reads the debug log from disk.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function () {

    if ( ! isset( $_GET['vgml_matrix'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only probe on a throwaway site.
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json( array( 'error' => 'not an administrator' ), 403 );
    }

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the probe reads the tables by name, plugin or no plugin.

    $tt = $wpdb->get_results( "SELECT term_taxonomy_id, term_id, parent, count FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'media_category'", ARRAY_A );

    $terms = array();
    foreach ( (array) $tt as $row ) {
        $t       = $wpdb->get_row( $wpdb->prepare( "SELECT name, slug FROM {$wpdb->terms} WHERE term_id = %d", (int) $row['term_id'] ), ARRAY_A );
        $terms[] = array(
            'id'     => (int) $row['term_id'],
            'name'   => (string) ( $t ? $t['name'] : '' ),
            'slug'   => (string) ( $t ? $t['slug'] : '' ),
            'parent' => (int) $row['parent'],
            'count'  => (int) $row['count'],
        );
    }

    $tt_ids = array_map( 'intval', wp_list_pluck( (array) $tt, 'term_taxonomy_id' ) );
    $links  = array();
    if ( $tt_ids ) {
        $in   = implode( ',', $tt_ids );
        $rows = $wpdb->get_results( "SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.post_type = 'attachment' AND tr.term_taxonomy_id IN ($in)", ARRAY_A );
        foreach ( (array) $rows as $r ) {
            $links[ (int) $r['object_id'] ][] = (int) $r['term_id'];
        }
    }

    $attachments = array_map( 'intval', (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY ID" ) );

    $log   = WP_CONTENT_DIR . '/debug.log';
    $lines = array();
    if ( file_exists( $log ) ) {
        foreach ( (array) file( $log ) as $line ) {
            $line = trim( $line );
            if ( '' !== $line ) {
                $lines[] = $line;
            }
        }
    }

    // phpcs:enable

    wp_send_json( array(
        'plugin_active'  => function_exists( 'vergeml_ai_settings' ),
        'active_plugins' => array_values( (array) get_option( 'active_plugins', array() ) ),
        'locale'         => get_locale(),
        'rtl'            => is_rtl(),
        'wp'             => get_bloginfo( 'version' ),
        'php'            => PHP_VERSION,
        'terms'          => $terms,
        'links'          => $links,
        'attachments'    => $attachments,
        'log'            => array_slice( $lines, -200 ),
    ) );
} );
