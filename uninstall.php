<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}


/*
 *  What deleting the plugin deletes -- and what it deliberately does not.
 *
 *  The folders are vergeml_folder terms in WordPress's own tables, and the AI
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
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall removes this plugin's own rows directly: no API deletes every row with the vergeml_ prefix, and nothing is left to cache.
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '\_transient\_vergeml\_%'
            OR option_name LIKE '\_transient\_timeout\_vergeml\_%'"
    );

    // Every hook the plugin books, whatever arguments an event carries.
    foreach ( array( 'vergeml_meaning_convert', 'vergeml_ai_run_tick', 'vergeml_talk_refile_event', 'vergeml_guide_fit_event', 'vergeml_plan_inventory_refresh', 'vergeml_plan_event', 'vergeml_provision_site' ) as $vergeml_hook ) {
        wp_unschedule_hook( $vergeml_hook );
    }
}


function vergeml_uninstall_wipe_site() {

    global $wpdb;

    /*
     *  The taxonomies this plugin managed, read from its own option. Ones it
     *  created (eml_media) lose their terms, except vergeml_folder, the
     *  folders; taxonomies core or another plugin owns are not touched.
     */
    foreach ( (array) get_option( 'vergeml_taxonomies', array() ) as $taxonomy => $params ) {

        $params   = (array) $params;
        $own      = ! empty( $params['eml_media'] );
        $taxonomy = (string) $taxonomy;

        // Folders are ordinary terms and outlive the plugin: the vergeml_folder terms and
        // which files are in them stay in WordPress's own tables -- under media_category too,
        // on a site whose folders have not moved yet or where Enhanced Media Library still owns them.
        // A taxonomy another plugin or core owns (a post category assigned to media, Polylang's
        // languages, another folder plugin's) is left exactly as it is.
        if ( in_array( $taxonomy, array( 'vergeml_folder', 'media_category' ), true ) || ! $own ) {
            continue;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall reads the term ids of a taxonomy this plugin registered, to delete them below; it runs once, so there is nothing to cache.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
            $taxonomy
        ) );

        if ( ! $rows ) {
            continue;
        }

        $tt_ids   = array_map( 'absint', wp_list_pluck( $rows, 'term_taxonomy_id' ) );
        $term_ids = array_map( 'absint', wp_list_pluck( $rows, 'term_id' ) );

        if ( $own ) {

            // The id lists are absint()ed above; one %d per id, every value is placeheld.
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall removes this plugin's own rows directly: no API deletes every row with the vergeml_ prefix, and nothing is left to cache.
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ( " . implode( ', ', array_fill( 0, count( $tt_ids ), '%d' ) ) . ' )', $tt_ids ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ( " . implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) . ' )', $term_ids ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->terms} WHERE term_id IN ( " . implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) . ' )', $term_ids ) );
            // phpcs:enable

            delete_option( $taxonomy . '_children' );
        }
    }

    // The plugin's own tables: the AI index, the librarian's batches and
    // moves, the organize runs. Descriptions already written into
    // attachments stay there; these are working tables.
    foreach ( array( 'vergeml_ai_index', 'vergeml_librarian_batches', 'vergeml_librarian_moves', 'vergeml_organize_runs' ) as $table ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall drops this plugin's own four tables; $table comes from the literal list above.
        $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
    }

    /*
     *  A sweep, not a list. The plugin has grown options faster than any list
     *  kept up with -- a wipe that names them individually leaves behind
     *  whichever ones were added after it was written. Transients do not
     *  match: their rows start with _transient_, not vergeml_.
     */
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall removes this plugin's own rows directly: no API deletes every row with the vergeml_ prefix, and nothing is left to cache.
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'vergeml\_%'" );

    /*
     *  The plugin's own meta on files and folders: what it recorded about a
     *  picture (_vergeml_*) and about a folder (vergeml_* and _vergeml_*).
     *  WordPress's own fields -- the alt text and captions it wrote -- stay.
     */
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall removes this plugin's own rows directly: no API deletes every row with the prefix, and nothing is left to cache.
    $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_vergeml\_%'" );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the same, for term meta.
    $wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE 'vergeml\_%' OR meta_key LIKE '\_vergeml\_%'" );
}


/*
 *  On a network, WordPress runs this file once, in the main site's context.
 *  The housekeeping runs on every site regardless. The wipe runs on a site
 *  when the network asked for it (a network administrator's switch) or that
 *  site asked for itself -- the main site's checkbox does not decide for the
 *  other hundred and ninety-nine.
 */
if ( is_multisite() ) {

    global $wpdb;

    $vergeml_network_wipe = (bool) get_site_option( 'vergeml_uninstall_wipe_network' );
    $vergeml_any_wipe     = false;

    // number 0: every site. The default is the first hundred, and a network
    // with more than that would have been silently half-cleaned.
    foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $vergeml_site_id ) {

        switch_to_blog( $vergeml_site_id );

        vergeml_uninstall_housekeeping();

        if ( $vergeml_network_wipe || get_option( 'vergeml_uninstall_wipe' ) ) {
            vergeml_uninstall_wipe_site();
            $vergeml_any_wipe = true;
        }

        restore_current_blog();
    }

    if ( $vergeml_network_wipe ) {
        foreach ( array( 'vergeml_network_options', 'vergeml_uninstall_wipe_network', 'vergeml_watchdog_network', 'vergeml_ai_network', 'vergeml_notices', 'vergeml_mimes_backup' ) as $vergeml_site_option ) {
            delete_site_option( $vergeml_site_option );
        }
        delete_site_transient( 'vergeml_network_overview' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall removes this plugin's own network transients directly: no API deletes them by prefix.
        $wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '\_site\_transient\_vergeml\_%' OR meta_key LIKE '\_site\_transient\_timeout\_vergeml\_%'" );
    }

    if ( $vergeml_any_wipe ) {
        // Per-user leftovers on every site: plain keys and blog-prefixed ones.
        $vergeml_base = str_replace( '_', '\_', $wpdb->base_prefix );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- both patterns are bound below; the prefix is WordPress's own, escaped for LIKE.
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
            'vergeml\_%',
            $vergeml_base . '%\_vergeml\_%'
        ) );
    }
}
else {

    vergeml_uninstall_housekeeping();

    if ( get_option( 'vergeml_uninstall_wipe' ) ) {

        global $wpdb;

        vergeml_uninstall_wipe_site();

        delete_site_option( 'vergeml_mimes_backup' );
        delete_site_option( 'vergeml_notices' );

        // On a single site, site transients live in the options table.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall removes this plugin's own transients directly: no API deletes them by prefix.
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_site\_transient\_vergeml\_%' OR option_name LIKE '\_site\_transient\_timeout\_vergeml\_%'" );

        // Per-user leftovers: dismissed notices, remembered tree state.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall removes this plugin's own rows directly: no API deletes every row with the vergeml_ prefix, and nothing is left to cache.
        $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'vergeml\_%'" );
    }
}
