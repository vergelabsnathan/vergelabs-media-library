<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}


/*
 *  What deleting the plugin deletes -- and what it deliberately does not.
 *
 *  The folders are media_category terms in WordPress's own tables, and the AI
 *  descriptions were paid for in credits. Neither can be rebuilt for free, so
 *  neither goes just because the plugin does: by default, uninstalling removes
 *  only the plugin's housekeeping -- transients and scheduled events -- and
 *  everything of value stays where a reinstall, or another folder plugin
 *  reading the same taxonomy, will find it.
 *
 *  The full wipe is opt-in twice over: either the Complete Cleanup button on
 *  the Utilities page (immediate, while the plugin still runs), or the
 *  "also remove everything when the plugin is deleted" switch there, which is
 *  what this file honours. Nothing here ever touches a media file.
 *
 *  This runs without the plugin loaded, so no taxonomy is registered and no
 *  plugin function exists. Everything is done directly, per table, with
 *  every value placeheld.
 */


function vergeml_uninstall_housekeeping() {

    global $wpdb;

    // Transients, in both storage shapes -- underscores escaped so the
    // wildcard is the only wildcard.
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '\_transient\_vergeml\_%'
            OR option_name LIKE '\_transient\_timeout\_vergeml\_%'"
    );

    wp_clear_scheduled_hook( 'vergeml_meaning_convert' );
}


function vergeml_uninstall_wipe_site() {

    global $wpdb;

    /*
     *  The taxonomies this plugin managed, read from its own option. Ones it
     *  created (eml_media) lose their terms outright; shared ones -- a post
     *  category that was merely assigned to media -- only lose their links to
     *  attachments, never the terms themselves.
     */
    foreach ( (array) get_option( 'vergeml_taxonomies', array() ) as $taxonomy => $params ) {

        $params   = (array) $params;
        $own      = ! empty( $params['eml_media'] );
        $taxonomy = (string) $taxonomy;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
            $taxonomy
        ) );

        if ( ! $rows ) {
            continue;
        }

        $tt_ids   = array_map( 'absint', wp_list_pluck( $rows, 'term_taxonomy_id' ) );
        $term_ids = array_map( 'absint', wp_list_pluck( $rows, 'term_id' ) );
        $tt_in    = implode( ',', array_fill( 0, count( $tt_ids ), '%d' ) );

        if ( $own ) {

            $term_in = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );

            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($tt_in)", $tt_ids ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ($term_in)", $term_ids ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->terms} WHERE term_id IN ($term_in)", $term_ids ) );

            delete_option( $taxonomy . '_children' );
        }
        else {

            // Attachments only; the taxonomy keeps living its own life.
            $wpdb->query( $wpdb->prepare(
                "DELETE tr FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
                 WHERE p.post_type = 'attachment' AND tr.term_taxonomy_id IN ($tt_in)",
                $tt_ids
            ) );
        }
    }

    // The AI index. Descriptions already written into attachments stay there;
    // this is the plugin's own working table.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}vergeml_ai_index" );

    /*
     *  A sweep, not a list. The plugin has grown options faster than any list
     *  kept up with -- a wipe that names them individually leaves behind
     *  whichever ones were added after it was written. Transients do not
     *  match: their rows start with _transient_, not vergeml_.
     */
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'vergeml\_%'" );
}


vergeml_uninstall_housekeeping();

if ( get_option( 'vergeml_uninstall_wipe' ) ) {

    global $wpdb;

    if ( is_multisite() ) {

        foreach ( get_sites( array( 'fields' => 'ids' ) ) as $vergeml_site_id ) {
            switch_to_blog( $vergeml_site_id );
            vergeml_uninstall_housekeeping();
            vergeml_uninstall_wipe_site();
            restore_current_blog();
        }

        delete_site_option( 'vergeml_network_options' );
    }
    else {
        vergeml_uninstall_wipe_site();
    }

    delete_site_option( 'vergeml_version' );
    delete_site_option( 'vergeml_mimes_backup' );
    delete_site_option( 'vergeml_notices' );

    // Per-user leftovers: dismissed notices, remembered tree state.
    $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'vergeml\_%'" );
}
