<?php
/*
Plugin Name: VergeLabs Media Library
Plugin URI: https://vergelabsmedia.com
Description: Categories, tags and custom taxonomies for the media library, MIME type management, and configurable media grid filters.
Version: 3.15.0
Requires at least: 6.5
Requires PHP: 7.4
Author: VergeLabs
Author URI: https://vergelabs.nl
Text Domain: vergelabs-media-library
Domain Path: /languages
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Based on Enhanced Media Library by wpUXsolutions.
https://wordpress.org/plugins/enhanced-media-library/

Copyright 2013-2024 wpUXsolutions  (original work)
Copyright 2026 VergeLabs  (modifications)

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version.
*/



if ( ! defined( 'ABSPATH' ) )
    exit;



if ( ! defined('VERGEML_VERSION') ) define( 'VERGEML_VERSION', '3.15.0' );

/**
 *  Cache-busting asset version: the plugin version plus the file's mtime.
 *
 *  A fixed version string means a browser that has visited the screen once
 *  keeps its cached copy of every script until the next release -- which,
 *  during development, means every fix ships to a fresh profile and never to
 *  the person reporting the bug. The mtime suffix makes each deployed change
 *  its own URL. Falls back to the bare version if the file cannot be stated.
 */
function vergeml_asset_ver( $rel ) {
    $mtime = @filemtime( plugin_dir_path( VERGEML_FILE ) . $rel );
    return $mtime ? VERGEML_VERSION . '.' . $mtime : VERGEML_VERSION;
}

/*
 *  Translations from the plugin's own languages/ folder, which the header's
 *  Domain Path advertises. WordPress's just-in-time loading covers files
 *  dropped into wp-content/languages/plugins/; a .mo placed here, where the
 *  header says it goes, was silently ignored on WordPress before 6.7.
 */
add_action( 'init', function () {
    load_plugin_textdomain( 'vergelabs-media-library', false, dirname( plugin_basename( VERGEML_FILE ) ) . '/languages' );
}, 1 );

/*
 *  Right-to-left. WordPress swaps <name>.css for <name>-rtl.css on an RTL
 *  locale for any style marked this way; the -rtl files are generated from
 *  the sources by tools/rtl.mjs and committed. Late in the enqueue order so
 *  every sheet is registered by the time it is marked -- marking is cheap and
 *  only read at print time.
 */
function vergeml_rtl_styles() {
    foreach ( array( 'vergeml-admin', 'vergeml-admin-shell', 'vergeml-journey', 'vergeml-librarian', 'vergeml-media-list', 'vergeml-gallery' ) as $handle ) {
        if ( wp_style_is( $handle, 'registered' ) ) {
            wp_style_add_data( $handle, 'rtl', 'replace' );
        }
    }
}
add_action( 'admin_enqueue_scripts', 'vergeml_rtl_styles', 999 );
add_action( 'wp_enqueue_scripts', 'vergeml_rtl_styles', 999 );
add_action( 'enqueue_block_editor_assets', 'vergeml_rtl_styles', 999 );
// The plugin's own top-level admin menu. Named here because screens registered
// from files that load outside the admin still have to name their parent.
if ( ! defined('VERGEML_MENU') ) define( 'VERGEML_MENU', 'vergelabs-media' );
if ( ! defined('VERGEML_FILE') )    define( 'VERGEML_FILE', __FILE__ );


/*
 *  Loaded before anything else, so the handler is watching while the code it
 *  guards is running. See core/watchdog.php for what it does and does not do.
 */
require_once plugin_dir_path( __FILE__ ) . 'core/watchdog.php';
vergeml_watchdog_boot();



global $vergeml_dir,
       $vergeml_slug,
       $vergeml_filename,
       $vergeml_basename;



if ( ! function_exists( 'vergeml_get_slug' ) ) {


    /**
     *  vergeml_get_slug
     *
     *  keeping the name for older versions compatibility
     * 
     *  @since    2.1
     *  @since    2.9 modified
     *  @created  27/10/15
     */

    function vergeml_get_slug() {

        $path_array = explode( '/', dirname( __FILE__ ) );
        $slug = end( $path_array );

        return $slug;
    }



    /**
     *  vergeml_get_basename
     *
     *  @since    2.1
     *  @since    2.8.16 modified
     *  @created  27/10/15
     */

    function vergeml_get_basename() {

        global $vergeml_basename;

        return $vergeml_basename;
    }



    /**
     *  vergeml_enhance_media_shortcodes
     *
     *  @since    2.1.4
     *  @created  08/01/16
     */

    function vergeml_enhance_media_shortcodes() {

        $vergeml_lib_options = get_option( 'vergeml_lib_options', array() );

        $enhance_media_shortcodes = isset( $vergeml_lib_options['enhance_media_shortcodes'] ) ? (bool) $vergeml_lib_options['enhance_media_shortcodes'] : false;

        return $enhance_media_shortcodes;
    }



    /**
     *  vergeml_enable_infinite_scrolling
     *
     *  @since    2.9
     *  @created  09/2021
     */

    function vergeml_enable_infinite_scrolling() {

        $vergeml_lib_options = get_option( 'vergeml_lib_options', array() );

        $enable_infinite_scrolling = isset( $vergeml_lib_options['infinite_scrolling'] ) ? (bool) $vergeml_lib_options['infinite_scrolling'] : false;

        return $enable_infinite_scrolling;
    }



    /**
     *  vergeml_on_activation
     *
     *  @since    2.7
     *  @created  31/08/18
     */

    register_activation_hook( __FILE__, 'vergeml_on_activation' );

    /**
     *  vergeml_on_activation
     *
     *  @param bool $network_wide  WordPress passes true for "Network Activate".
     *
     *  Network activation used to provision only the site the network admin
     *  happened to be on; every other subsite had no options and no tables
     *  until somebody opened its admin. Now it walks the network. Networks
     *  above a hundred sites provision the current site immediately and the
     *  rest on their first admin request, so activation cannot time out --
     *  every site self-provisions anyway, this just makes the small case whole.
     */
    function vergeml_on_activation( $network_wide = false ) {

        if ( is_multisite() && $network_wide ) {

            vergeml_set_network_options();
            do_action( 'vergeml_set_site_options' );

            $sites = get_sites( array( 'fields' => 'ids', 'number' => 101 ) );

            if ( count( $sites ) <= 100 ) {
                foreach ( $sites as $site_id ) {
                    switch_to_blog( $site_id );
                    vergeml_provision_site();
                    restore_current_blog();
                }
                return;
            }
        }

        vergeml_provision_site();

        if ( is_multisite() && is_network_admin() ) {
            vergeml_set_network_options();
            do_action( 'vergeml_set_site_options' );
        }
    }


    /**
     *  vergeml_provision_site
     *
     *  Everything one site needs: options, and the tables of every feature
     *  that keeps one. Called for the current blog -- inside switch_to_blog()
     *  when walking a network -- and stamps the version so the lazy check on
     *  the next request has nothing left to do.
     */
    function vergeml_provision_site() {

        // carry settings over from Enhanced Media Library, if it left any
        vergeml_migrate_legacy_options();

        vergeml_set_options();


        /*
         *  Schema, for the feature files that keep their own table.
         *
         *  An action rather than a call: in safe mode none of those files are
         *  loaded, so nothing answers and activation does not fatal on a
         *  function that was never defined. Runs on activation and on every
         *  version change, which is where dbDelta belongs.
         */
        do_action( 'vergeml_activate' );

        update_option( 'vergeml_version', VERGEML_VERSION );
    }


    /*
     *  The rest of a site's life on a network.
     *
     *  A subsite created after network activation gets its options and tables
     *  the moment core has finished creating it -- not on its first admin
     *  visit, which on the front end left it with no media_category at all.
     *  A subsite being deleted takes its four tables with it whether or not
     *  this plugin is loaded in that request (safe mode, deactivated): the
     *  wpmu_drop_tables filter is the belt to the $wpdb->tables braces.
     */
    add_action( 'wp_initialize_site', 'vergeml_on_new_site', 100 );

    function vergeml_on_new_site( $site ) {

        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
            return;
        }

        switch_to_blog( (int) $site->id );
        vergeml_provision_site();
        restore_current_blog();
    }


    add_filter( 'wpmu_drop_tables', 'vergeml_site_tables_to_drop', 10, 2 );

    function vergeml_site_tables_to_drop( $tables, $site_id ) {

        global $wpdb;

        $prefix = $wpdb->get_blog_prefix( (int) $site_id );

        foreach ( array( 'vergeml_ai_index', 'vergeml_librarian_batches', 'vergeml_librarian_moves', 'vergeml_organize_runs' ) as $table ) {
            $tables[] = $prefix . $table;
        }

        return $tables;
    }


    /*
     *  Request caches that must not outlive a switch_to_blog(). Each is keyed
     *  by an id that means something else on the next site -- attachment ids
     *  collide across a network constantly -- so a network job that walks
     *  sites would read one site's captions for another's files.
     */
    add_action( 'switch_blog', 'vergeml_forget_request_caches' );

    function vergeml_forget_request_caches() {
        unset( $GLOBALS['vergeml_index_primed'], $GLOBALS['vergeml_organize_points'] );
        if ( function_exists( 'vergeml_mimes_prepared' ) ) {
            vergeml_mimes_prepared( true );
        }
    }



    /**
     *  vergeml_migrate_legacy_options
     *
     *  Carries settings over from Enhanced Media Library, whose option names
     *  this plugin used before the fork was renamed. Copies a value only when
     *  this plugin has none of its own yet, and never removes the originals,
     *  so Enhanced Media Library still works if someone switches back.
     *
     *  @since    2.9.5
     */

    function vergeml_migrate_legacy_options() {

        $legacy = array(
            'wpuxss_eml_version'     => 'vergeml_version',
            'wpuxss_eml_lib_options' => 'vergeml_lib_options',
            'wpuxss_eml_taxonomies'  => 'vergeml_taxonomies',
            'wpuxss_eml_tax_options' => 'vergeml_tax_options',
            'wpuxss_eml_mimes'       => 'vergeml_mimes',
            'wpuxss_eml_backup'      => 'vergeml_backup'
        );

        foreach ( $legacy as $old_name => $new_name ) {

            if ( false !== get_option( $new_name, false ) )
                continue;

            $value = get_option( $old_name, false );

            if ( false !== $value )
                update_option( $new_name, $value );
        }


        if ( is_multisite() && false === get_site_option( 'vergeml_network_options', false ) ) {

            $network_options = get_site_option( 'wpuxss_eml_network_options', false );

            if ( false !== $network_options )
                update_site_option( 'vergeml_network_options', $network_options );
        }
    }



    /**
     *  vergeml_on_plugins_loaded
     *
     *  @since    2.6.1
     *  @since    2.9 modified
     *  @created  20/05/18
     */

    add_action( 'plugins_loaded', 'vergeml_on_plugins_loaded' );

    function vergeml_on_plugins_loaded() {

        global $vergeml_dir,
               $vergeml_slug,
               $vergeml_filename,
               $vergeml_basename;


        $vergeml_dir      = plugin_dir_url( __FILE__ );
        $vergeml_slug     = vergeml_get_slug();
        $vergeml_filename = basename( __FILE__ );
        $vergeml_basename = $vergeml_slug . '/' . $vergeml_filename;


        /*
         *  On update.
         *
         *  Runs the activation routine -- option defaults, three dbDelta
         *  calls -- when the stored version is behind. It used to do that on
         *  whichever request came first after an upgrade, front-end visitors
         *  included, with nothing stopping several requests doing it at once:
         *  a cache expiring after a deploy is exactly N concurrent requests,
         *  and dbDelta run N times over the same table is not a thing MySQL
         *  enjoys. Now: admin, cron or CLI only, and one at a time. add_option
         *  is atomic, so the first request to plant the lock wins and the
         *  rest carry on with the previous version's tables, which still
         *  work -- every migration here is additive. A lock older than two
         *  minutes belongs to a request that died and is taken over.
         */
        $vergeml_stored_version = get_option( 'vergeml_version', '' );

        if ( VERGEML_VERSION !== $vergeml_stored_version
             && ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) ) {

            $stale = (int) get_option( 'vergeml_upgrading', 0 );

            if ( $stale && $stale < time() - 120 ) {
                delete_option( 'vergeml_upgrading' );
            }

            if ( add_option( 'vergeml_upgrading', time(), '', 'no' ) ) {
                vergeml_provision_site();
                delete_site_transient( 'eml_transient' );
                delete_option( 'vergeml_upgrading' );
            }
        }
        elseif ( '' === $vergeml_stored_version && ! get_option( 'vergeml_taxonomies' ) ) {
            /*
             *  A site that has never been provisioned -- a subsite created
             *  while the plugin was not yet network-active, say -- serving a
             *  front-end request. The options are three cheap writes and
             *  without them no media_category exists on this site; the
             *  schema can wait for the first admin visit or the cron tick
             *  scheduled here.
             */
            vergeml_set_options();
            if ( ! wp_next_scheduled( 'vergeml_provision_site' ) ) {
                wp_schedule_single_event( time() + 60, 'vergeml_provision_site' );
            }
        }

        add_action( 'vergeml_provision_site', 'vergeml_provision_site' );


        // plugin action links
        add_filter( 
            'plugin_action_links_' . vergeml_get_basename(),
            'vergeml_settings_link', 10, 4 
        );
        add_filter( 
            'network_admin_plugin_action_links_' . vergeml_get_basename(),
            'vergeml_settings_link', 10, 4 
        );

        add_filter( 'plugin_row_meta', 'vergeml_plugin_row_meta', 10, 4 );
    }



    /**
     *  vergeml_on_init
     *
     *  @since    1.0
     *  @created  03/08/13
     */

    add_action( 'init', 'vergeml_on_init', 12 );

    function vergeml_on_init() {

        $vergeml_taxonomies = get_option( 'vergeml_taxonomies', array() );
        $vergeml_tax_options = get_option( 'vergeml_tax_options', array() );

        // register eml taxonomies
        foreach ( (array) $vergeml_taxonomies as $taxonomy => $params ) {

            if ( $params['eml_media'] ) {

                /*
                 *  Missing labels are named, not fatal.
                 *
                 *  Registration used to require a name and a singular name and
                 *  skip the taxonomy silently without them. Anything that wrote
                 *  the settings without carrying the labels through therefore
                 *  unregistered the taxonomy outright -- every folder gone from
                 *  the media library, the filters and the menu, with all the terms
                 *  still sitting in the database. A label derived from the slug is
                 *  an imperfect name; not registering is a missing feature.
                 */
                $labels = isset( $params['labels'] ) && is_array( $params['labels'] ) ? $params['labels'] : array();

                if ( empty( $labels['name'] ) ) {
                    $labels['name'] = ucwords( str_replace( array( '_', '-' ), ' ', $taxonomy ) );
                }

                if ( empty( $labels['singular_name'] ) ) {
                    $labels['singular_name'] = $labels['name'];
                }

                $labels = array_map( 'sanitize_text_field', $labels );

                if ( (bool) $vergeml_tax_options['tax_archives'] ) {

                    $rewrite = array(
                        'slug' => vergeml_sanitize_slug( $params['rewrite']['slug'], $taxonomy ),
                        'with_front' => (bool) $params['rewrite']['with_front']
                    );
                    $public = true;
                }
                else {
                    $rewrite = $public = false;
                }

                /*
                 *  Attachments always, plus whatever else this taxonomy has been
                 *  turned on for. One argument -- there is no second storage
                 *  model for "folders on posts", because a taxonomy was never
                 *  specific to one object type in the first place.
                 */
                register_taxonomy(
                    $taxonomy,
                    function_exists( 'vergeml_folder_object_types' ) ? vergeml_folder_object_types( $taxonomy ) : 'attachment',
                    array(
                        'labels'                => $labels,
                        'public'                => $public,
                        'show_ui'               => true,
                        'show_admin_column'     => (bool) $params['show_admin_column'],
                        'hierarchical'          => (bool) $params['hierarchical'],
                        'update_count_callback' => 'vergeml_update_attachment_term_count',
                        'sort'                  => (bool) $params['sort'],
                        'show_in_rest'          => (bool) $params['show_in_rest'],
                        'query_var'             => sanitize_key( $taxonomy ),
                        'rewrite'               => $rewrite
                    )
                );
            }
        } // endforeach
    }



    /**
     *  vergeml_on_wp_loaded
     *
     *  @since    1.0
     *  @created  03/11/13
     */

    add_action( 'wp_loaded', 'vergeml_on_wp_loaded' );

    function vergeml_on_wp_loaded() {

        global $wp_taxonomies;

        $vergeml_taxonomies = get_option( 'vergeml_taxonomies', array() );
        $taxonomies = get_taxonomies( array(), 'object' );

        // discover 'foreign' taxonomies
        foreach ( $taxonomies as $taxonomy => $params ) {

            if ( ! empty( $params->object_type ) && ! array_key_exists( $taxonomy, $vergeml_taxonomies ) &&
                 ! in_array( 'revision', $params->object_type ) &&
                 ! in_array( 'nav_menu_item', $params->object_type ) &&
                 $taxonomy !== 'wp_theme' &&
                 $taxonomy !== 'post_format' &&
                 $taxonomy !== 'link_category' &&
                 $taxonomy !== 'wp_pattern_category' &&
                 $taxonomy !== 'wp_template_part_area' ) {

                $vergeml_taxonomies[$taxonomy] = array(
                    'eml_media' => 0,
                    'admin_filter' => 1, // since 2.7
                    'media_uploader_filter' => 1, // since 2.7
                    'media_popup_taxonomy_edit' => 0,
                    'taxonomy_auto_assign' => 0
                );

                if ( in_array('attachment',$params->object_type) )
                    $vergeml_taxonomies[$taxonomy]['assigned'] = 1;
                else
                    $vergeml_taxonomies[$taxonomy]['assigned'] = 0;
            }
        }

        // assign/unassign taxonomies to atachment
        foreach ( $vergeml_taxonomies as $taxonomy => $params ) {

            $taxonomy = sanitize_key($taxonomy);

            if ( (bool) $params['assigned'] )
                register_taxonomy_for_object_type( $taxonomy, 'attachment' );

            if ( ! (bool) $params['assigned'] )
                unregister_taxonomy_for_object_type( $taxonomy, 'attachment' );
        }


        /**
         *  Clean up update_count_callback
         *  Set custom update_count_callback for post type
         *
         *  @since 2.3
         */
        foreach ( $taxonomies as $taxonomy => $params ) {

            /*
             *  A media taxonomy keeps counting attachments, whatever else it is
             *  attached to.
             *
             *  The rule below was written when the only taxonomies on both posts
             *  and attachments were other people's, and it swaps in a counter that
             *  counts everything *except* attachments. Once a folder taxonomy
             *  could be turned on for posts as well, that quietly redefined the
             *  number the media library reads: a folder holding one image and
             *  three blog posts reported three files. The tree, the dropdown and
             *  the Media Categories screen all show that number, and all three
             *  were wrong together, which is the kind of wrong nobody reports as a
             *  bug -- they just stop trusting the counts.
             *
             *  Post counts are worked out per post type when a post screen asks
             *  for them; see vergeml_folder_counts().
             */
            if ( ! empty( $vergeml_taxonomies[ $taxonomy ]['eml_media'] ) ) {
                continue;
            }

            if ( in_array( 'attachment', $params->object_type ) &&
                 isset( $wp_taxonomies[$taxonomy]->update_count_callback ) &&
                 '_update_generic_term_count' === $wp_taxonomies[$taxonomy]->update_count_callback ) {

                unset( $wp_taxonomies[$taxonomy]->update_count_callback );
            }

            if ( in_array( 'post', $params->object_type ) ) {

                if ( in_array( 'attachment', $params->object_type ) )
                    $wp_taxonomies[$taxonomy]->update_count_callback = 'vergeml_update_post_term_count';
                else
                    unset( $wp_taxonomies[$taxonomy]->update_count_callback );
            }
        }

        update_option( 'vergeml_taxonomies', $vergeml_taxonomies );
    }



    /**
     *  vergeml_admin_enqueue_scripts
     *
     *  @since    1.1.1
     *  @created  07/04/14
     */

    add_action( 'admin_enqueue_scripts', 'vergeml_admin_enqueue_scripts' );

    function vergeml_admin_enqueue_scripts() {

        global $vergeml_dir,
               $current_screen;


        $media_library_mode = get_user_option( 'media_library_mode', get_current_user_id() ) ? get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';

        $vergeml_lib_options = get_option( 'vergeml_lib_options' );


        // admin styles
        wp_enqueue_style(
            'vergeml-admin-custom-style',
            $vergeml_dir . 'css/eml-admin.css',
            false,
            VERGEML_VERSION,
            'all'
        );
        wp_style_add_data( 'vergeml-admin-custom-style', 'rtl', 'replace' );

        // media styles
        wp_enqueue_style(
            'vergeml-admin-media-style',
            $vergeml_dir . 'css/eml-admin-media.css',
            false,
            VERGEML_VERSION,
            'all'
        );
        wp_style_add_data( 'vergeml-admin-media-style', 'rtl', 'replace' );


        wp_enqueue_style ( 'wp-jquery-ui-dialog' );


        // admin scripts
        wp_enqueue_script(
            'vergeml-admin-script',
            $vergeml_dir . 'js/eml-admin.js',
            array( 'jquery', 'jquery-ui-dialog', 'underscore' ),
            VERGEML_VERSION,
            true
        );

        $admin_l10n = array(
            'admin_notice_nonce' => wp_create_nonce( 'eml-admin-notice-nonce' )
        );

        wp_localize_script(
            'vergeml-admin-script',
            'vergeml_admin_l10n',
            $admin_l10n
        );


        // scripts for list view :: /wp-admin/upload.php
        if ( isset( $current_screen ) && 'upload' === $current_screen->base && 'list' === $media_library_mode ) {

            wp_enqueue_script(
                'vergeml-media-list-script',
                $vergeml_dir . 'js/eml-media-list.js',
                array('jquery'),
                VERGEML_VERSION,
                true
            );

            $media_list_l10n = array(
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: the list view rebuilds its own filter links from the current query string.
                '$_GET'             => wp_json_encode( map_deep( wp_unslash( $_GET ), 'sanitize_text_field' ) ),
                'uncategorized'     => __( 'All Uncategorized', 'vergelabs-media-library' ),
                'reset_all_filters' => __( 'Reset All Filters', 'vergelabs-media-library' ),
                'filters_to_show'   => $vergeml_lib_options ? array_map( 'sanitize_key', $vergeml_lib_options['filters_to_show'] ) : array(
                    'types',
                    'dates',
                    'taxonomies'
                )
            );

            wp_localize_script(
                'vergeml-media-list-script',
                'vergeml_media_list_l10n',
                $media_list_l10n
            );
        }


        // scripts for grid view :: /wp-admin/upload.php
        if ( isset( $current_screen ) && 'upload' === $current_screen->base && 'grid' === $media_library_mode ) {

            wp_dequeue_script( 'media' );
        }
    }



    /**
     *  vergeml_register_scripts
     * 
     *  @todo :: make a separate one for Elementor if need be
     *
     *  @since    2.9.1
     *  @created  2024/05
     */

    add_action( 'wp_loaded', 'vergeml_register_scripts' );

    function vergeml_register_scripts() {

        global $vergeml_dir;


        /*
         *  Upstream shipped these two as minified-only and switched on an
         *  EML_SCRIPT_DEBUG constant to load js/source/, a directory that was
         *  never in the distribution. There was no readable copy of either
         *  file. They are now plain source, so there is nothing to switch.
         */

        wp_register_script(
            'vergeml-media-views-script',
            $vergeml_dir . 'js/vergeml-media-views.js',
            array('media-views'),
            VERGEML_VERSION,
            true
        );

        wp_register_script(
            'vergeml-taxonomies-options-script',
            $vergeml_dir . 'js/vergeml-taxonomies-options.js',
            array( 'jquery', 'underscore', 'vergeml-admin-script' ),
            VERGEML_VERSION,
            true
        );
    }



    /**
     *  vergeml_enqueue_media
     *
     *  @since    2.0
     *  @created  04/09/14
     */

    add_action( 'wp_enqueue_media', 'vergeml_enqueue_media' );

    function vergeml_enqueue_media() {

        global $vergeml_dir,
               $current_screen;


        if ( ! is_admin() ) {
            return;
        }


        $media_library_mode = get_user_option( 'media_library_mode', get_current_user_id() ) ? get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';

        $vergeml_lib_options = get_option( 'vergeml_lib_options' );
        $vergeml_taxonomies = get_option( 'vergeml_taxonomies', array() );
        $media_taxonomies = get_object_taxonomies( 'attachment','object' );
        $media_taxonomy_names = array_keys( $media_taxonomies );

        $media_taxonomies_ready_for_script = array();
        $filter_taxonomy_names_ready_for_script = array();
        $compat_taxonomies_to_hide = array();


        $terms = get_terms( array( 'taxonomy' => $media_taxonomy_names, 'fields' => 'all', 'get' => 'all' ) );
        $terms_id_tt_id_ready_for_script = vergeml_get_media_term_pairs( $terms, 'id=>tt_id' );


        $users_ready_for_script = array();

        if( current_user_can( 'manage_options' ) && $vergeml_lib_options ) {

            if ( in_array( 'authors', $vergeml_lib_options['filters_to_show'] ) ) {

                foreach( get_users( array( 'capability' => 'upload_files' ) ) as $user ) {
                    $users_ready_for_script[] = array(
                        'user_id' => $user->ID,
                        'user_name' => $user->data->display_name
                    );
                }
            }
        }


        if ( function_exists( 'wp_terms_checklist' ) ) {

            foreach ( $media_taxonomies as $taxonomy ) {

                $taxonomy_terms = array();


                ob_start();

                    wp_terms_checklist( 0, array( 'taxonomy' => $taxonomy->name, 'checked_ontop' => false, 'walker' => new Walker_Media_Taxonomy_Uploader_Filter() ) );

                    $html = '';
                    if ( ob_get_contents() != false ) {
                        $html = ob_get_contents();
                    }

                ob_end_clean();


                $html = str_replace( '}{', '},{', $html );
                $html = '[' . $html . ']';
                $taxonomy_terms = json_decode( $html, true );

                $media_taxonomies_ready_for_script[$taxonomy->name] = array(
                    'singular_name' => $taxonomy->labels->singular_name,
                    'plural_name'   => $taxonomy->labels->name,
                    'term_list'     => $taxonomy_terms,
                );


                if ( (bool) $vergeml_taxonomies[$taxonomy->name]['media_uploader_filter'] ) {
                    $filter_taxonomy_names_ready_for_script[] = $taxonomy->name;
                }

                if ( ! (bool) $vergeml_taxonomies[$taxonomy->name]['media_popup_taxonomy_edit'] ) {
                    $compat_taxonomies_to_hide[] = $taxonomy->name;
                }
            } // foreach
        }


        // generic scripts

        wp_enqueue_script(
            'vergeml-media-models-script',
            $vergeml_dir . 'js/eml-media-models.js',
            array('media-models'),
            VERGEML_VERSION,
            true
        );

        wp_enqueue_script( 'vergeml-media-views-script' );


        // TODO:
        // wp_enqueue_script(
        //     'vergeml-tags-box-script',
        //     '/wp-admin/js/tags-box.js',
        //     array(),
        //     VERGEML_VERSION,
        //     true
        // );


        $media_models_l10n = array(
            'media_orderby'   => $vergeml_lib_options ? sanitize_text_field( $vergeml_lib_options['media_orderby'] ) : 'date',
            'media_order'     => $vergeml_lib_options ? strtoupper( sanitize_text_field( $vergeml_lib_options['media_order'] ) ) : 'DESC',
            'bulk_edit_nonce' => wp_create_nonce( 'eml-bulk-edit-nonce' ),
            'natural_sort'    => (bool) $vergeml_lib_options['natural_sort'],
            'loads_per_page'  => (int) $vergeml_lib_options['loads_per_page']
        );

        wp_localize_script(
            'vergeml-media-models-script',
            'vergeml_media_models_l10n',
            $media_models_l10n
        );


        $media_views_l10n = array(
            'terms'                     => $terms_id_tt_id_ready_for_script,
            'taxonomies'                => $media_taxonomies_ready_for_script,
            'filter_taxonomies'         => $filter_taxonomy_names_ready_for_script,
            'compat_taxonomies'         => $media_taxonomy_names,
            'compat_taxonomies_to_hide' => $compat_taxonomies_to_hide,
            'is_tax_compat'             => count( $media_taxonomy_names ) - count( $compat_taxonomies_to_hide ) > 0 ? 1 : 0,
            'force_filters'             => (bool) $vergeml_lib_options['force_filters'],
            'filter_uploaded'           => (bool) $vergeml_lib_options['filter_uploaded'],
            'filters_to_show'           => $vergeml_lib_options ? array_map( 'sanitize_key', $vergeml_lib_options['filters_to_show'] ) : array(
                'types',
                'dates',
                'taxonomies'
            ),
            'users'                     => $users_ready_for_script,
            'uncategorized'             => __( 'All Uncategorized', 'vergelabs-media-library' ),
            'filter_by'                 => __( 'Filter by', 'vergelabs-media-library' ),
            'in'                        => __( 'All', 'vergelabs-media-library' ),
            'not_in'                    => __( 'Not in', 'vergelabs-media-library' ),
            'reset_filters'             => __( 'Reset All Filters', 'vergelabs-media-library' ),
            'author'                    => __( 'author', 'vergelabs-media-library' ),
            'authors'                   => __( 'authors', 'vergelabs-media-library' ),
            'current_screen'            => isset( $current_screen ) ? $current_screen->id : '',

            'saveButton_success'        => __( 'Saved.', 'vergelabs-media-library' ),
            'saveButton_failure'        => __( 'Something went wrong.', 'vergelabs-media-library' ),
            'saveButton_text'           => __( 'Save Changes', 'vergelabs-media-library' ),

            'select_all'                => __( 'Select All', 'vergelabs-media-library' ),
            'deselect'                  => __( 'Deselect ', 'vergelabs-media-library'),
            'grid_sidebar_width'        => (int) $vergeml_lib_options['grid_sidebar_width'],
            'ideal_column_width'        => (int) $vergeml_lib_options['ideal_column_width'],
        );

        wp_localize_script(
            'vergeml-media-views-script',
            'vergeml_mvln',
            $media_views_l10n
        );


        if ( vergeml_enhance_media_shortcodes() ) {

            wp_enqueue_script(
                'vergeml-enhanced-medialist-script',
                $vergeml_dir . 'js/eml-enhanced-medialist.js',
                array('media-views'),
                VERGEML_VERSION,
                true
            );

            wp_enqueue_script(
                'vergeml-media-editor-script',
                $vergeml_dir . 'js/eml-media-editor.js',
                array('media-editor','media-views', 'vergeml-enhanced-medialist-script'),
                VERGEML_VERSION,
                true
            );

            $enhanced_medialist_l10n = array(
                'uploaded_to' => __( 'Uploaded to post #', 'vergelabs-media-library' ),
                'based_on' => __( 'Based On', 'vergelabs-media-library' )
            );

            wp_localize_script(
                'vergeml-enhanced-medialist-script',
                'vergeml_enhanced_medialist_l10n',
                $enhanced_medialist_l10n
            );
        }


        // scripts for grid view :: /wp-admin/upload.php
        if ( isset( $current_screen ) && 'upload' === $current_screen->base && 'grid' === $media_library_mode ) {

            wp_enqueue_script(
                'vergeml-media-grid-script',
                $vergeml_dir . 'js/eml-media-grid.js',
                array( 'media-grid'),
                VERGEML_VERSION,
                true
            );

            wp_enqueue_script(
                'vergeml-media-script',
                $vergeml_dir . 'js/eml-media.js',
                array( 'media-grid' ),
                VERGEML_VERSION,
                true
            );
            
            $media_grid_l10n = array(
                'grid_show_caption' => (int) $vergeml_lib_options['grid_show_caption'],
                'grid_caption_type' => isset( $vergeml_lib_options['grid_caption_type'] ) ? sanitize_key( $vergeml_lib_options['grid_caption_type'] ) : 'title',
                'ideal_column_width' => (int) $vergeml_lib_options['ideal_column_width'],
                'more_details' => __( 'More Details', 'vergelabs-media-library' ),
                'less_details' => __( 'Less Details', 'vergelabs-media-library' )
            );

            wp_localize_script(
                'vergeml-media-grid-script',
                'vergeml_media_grid_l10n',
                $media_grid_l10n
            );
        }
    }



    /**
     *  vergeml_set_options
     *
     *  @since    2.6
     *  @created  02/05/18
     */

    function vergeml_set_options() {

        $vergeml_taxonomies  = get_option( 'vergeml_taxonomies' );
        $vergeml_lib_options = get_option( 'vergeml_lib_options', array() );
        $vergeml_tax_options = get_option( 'vergeml_tax_options', array() );


        // taxonomies
        if ( false === $vergeml_taxonomies ) {

            $vergeml_taxonomies = array(

                'media_category' => array(
                    'assigned' => 1,
                    'eml_media' => 1,

                    'labels' => array(
                        'name' => __( 'Media Categories', 'vergelabs-media-library' ),
                        'singular_name' => __( 'Media Category', 'vergelabs-media-library' ),
                        'menu_name' => __( 'Media Categories', 'vergelabs-media-library' ),
                        'all_items' => __( 'All Media Categories', 'vergelabs-media-library' ),
                        'edit_item' => __( 'Edit Media Category', 'vergelabs-media-library' ),
                        'view_item' => __( 'View Media Category', 'vergelabs-media-library' ),
                        'update_item' => __( 'Update Media Category', 'vergelabs-media-library' ),
                        'add_new_item' => __( 'Add New Media Category', 'vergelabs-media-library' ),
                        'new_item_name' => __( 'New Media Category Name', 'vergelabs-media-library' ),
                        'parent_item' => __( 'Parent Media Category', 'vergelabs-media-library' ),
                        'parent_item_colon' => __( 'Parent Media Category:', 'vergelabs-media-library' ),
                        'search_items' => __( 'Search Media Categories', 'vergelabs-media-library' )
                    ),

                    'hierarchical' => 1,

                    'show_admin_column' => 1,
                    'admin_filter' => 1,          // list view filter
                    'media_uploader_filter' => 1, // grid view filter
                    'media_popup_taxonomy_edit' => 0, // since 2.7

                    'sort' => 0,
                    'show_in_rest' => 1,
                    'rewrite' => array(
                        'slug' => 'media_category',
                        'with_front' => 1
                    )
                )
            );
        }

        // false !== $vergeml_taxonomies
        else {

            $media_taxonomy_args_defaults = array(
                'assigned' => 1,
                'eml_media' => 1,
                'labels' => array(),

                'hierarchical' => 1,
                'show_admin_column' => 1,
                'admin_filter' => 1,          // list view filter
                'media_uploader_filter' => 1, // grid view filter
                'media_popup_taxonomy_edit' => 0, // since 2.7

                'sort' => 0,
                'show_in_rest' => 1,
                'rewrite' => array(
                    'slug' => '',
                    'with_front' => 1
                )
            );

            $non_media_taxonomy_args_defaults = array(
                'assigned' => 0,
                'eml_media' => 0,
                'admin_filter' => 1, // since 2.7
                'media_uploader_filter' => 1, // since 2.7
                'media_popup_taxonomy_edit' => 0,
                'taxonomy_auto_assign' => 0
            );


            foreach( $vergeml_taxonomies as $taxonomy => $params ) {

                if ( empty( $params['eml_media'] ) ) {
                    $vergeml_taxonomies[$taxonomy]['eml_media'] = 0;
                }

                $defaults = (bool) $vergeml_taxonomies[$taxonomy]['eml_media'] ? $media_taxonomy_args_defaults : $non_media_taxonomy_args_defaults;

                /*
                 *  Known keys are normalised against the defaults; keys the
                 *  defaults do NOT know (post_types, the _full marker, and
                 *  anything a later version adds) are carried over untouched.
                 *  The old intersect-only merge silently deleted them on
                 *  every activation and upgrade -- which is how "Also use
                 *  for Posts" kept switching itself off.
                 */
                $taxonomy_params = array_intersect_key( $params, $defaults );
                $extra_params    = array_diff_key( $params, $defaults );
                $vergeml_taxonomies[$taxonomy] = array_merge( $defaults, $taxonomy_params, $extra_params );

                if ( (bool) $vergeml_taxonomies[$taxonomy]['eml_media'] && empty( $params['rewrite']['slug'] ) ) {
                    $vergeml_taxonomies[$taxonomy]['rewrite']['slug'] = $taxonomy;
                }
            } // foreach
        } // if

        /*
         *  Media taxonomies were registered with show_in_rest off, inherited from
         *  Enhanced Media Library, which predates the flag. That keeps them out of
         *  wp/v2/media entirely: the REST API cannot report which folders a file is
         *  in, so nothing built on it -- the folder tree, the block editor, any
         *  other plugin -- can see the categories the user has been filling in.
         *
         *  Turned on once, for media taxonomies only. Non-media taxonomies keep
         *  whatever the site already chose, and anyone who wants it off can still
         *  switch it off afterwards: this runs on the 3.0.0 boundary and never again.
         */
        if ( version_compare( get_option( 'vergeml_version', '' ), '3.0.0', '<' ) ) {

            foreach ( $vergeml_taxonomies as $taxonomy => $params ) {
                if ( ! empty( $params['eml_media'] ) ) {
                    $vergeml_taxonomies[$taxonomy]['show_in_rest'] = 1;
                }
            }
        }

        update_option( 'vergeml_taxonomies', $vergeml_taxonomies );


        // media library options
        $eml_lib_options_defaults = array(
            'enhance_media_shortcodes' => isset( $vergeml_tax_options['enhance_media_shortcodes'] ) ? (bool) $vergeml_tax_options['enhance_media_shortcodes'] : ( isset( $vergeml_tax_options['enhance_gallery_shortcode'] ) ? (bool) $vergeml_tax_options['enhance_gallery_shortcode'] : 0 ),
            'media_orderby' => isset( $vergeml_tax_options['media_orderby'] ) ? sanitize_text_field( $vergeml_tax_options['media_orderby'] ) : 'date',
            'media_order' => isset( $vergeml_tax_options['media_order'] ) ? strtoupper( sanitize_text_field( $vergeml_tax_options['media_order'] ) ) : 'DESC',
            'natural_sort' => 0,
            'force_filters' => isset( $vergeml_tax_options['force_filters'] ) ? (bool) $vergeml_tax_options['force_filters'] : 1,
            'filters_to_show' => array(
                'types',
                'dates',
                'taxonomies',
                // The AI folder group in the tree panel. A member here rather
                // than an option of its own; the list-view filter bar tests
                // for its own names explicitly and ignores this one.
                'ai'
            ),
            'show_count' => isset( $vergeml_tax_options['show_count'] ) ? (bool) $vergeml_tax_options['show_count'] : 1,
            'include_children' => 1,
            'filter_uploaded' => 0,
            'infinite_scrolling' => 0,
            'loads_per_page' => 80,
            'grid_show_caption' => 0,
            'grid_caption_type' => 'title',
            'grid_sidebar_width' => 270,
            'ideal_column_width' => 170,
            'search_in' => array(
                'filenames',
                'titles',
                'captions',
                'descriptions'
            ),
            'search_min_letters' => 2,
            'search_on_enter' => 0,
            'search_auto' => 1
        );

        $vergeml_lib_options = array_intersect_key( $vergeml_lib_options, $eml_lib_options_defaults );
        $vergeml_lib_options = array_merge( $eml_lib_options_defaults, $vergeml_lib_options );

        // check previous version
        if ( version_compare( get_option( 'vergeml_version', '' ), '2.8.9', '<=' ) ) {
            // ensure that filenames included in the search by default -- once:
            // a fresh install has no stored version, compares as older, and
            // was getting the entry twice.
            if ( ! in_array( 'filenames', (array) $vergeml_lib_options['search_in'], true ) ) {
                array_push( $vergeml_lib_options['search_in'], 'filenames' );
            }
        }

        /*
         *  The AI folder group, switched on for sites that existed before it.
         *
         *  Changing the default above does nothing here: every existing
         *  install already has its own filters_to_show written to the
         *  database, and array_merge keeps the saved one. So the member is
         *  added once, on the 3.4.0 boundary, and only if it is absent --
         *  anyone who switches it off afterwards keeps it off, because this
         *  never runs again.
         *
         *  Nothing else in the array is touched: a site that turned off dates
         *  in 2019 still has dates off after this.
         */
        if ( version_compare( get_option( 'vergeml_version', '' ), '3.4.0', '<' ) ) {

            if ( ! in_array( 'ai', (array) $vergeml_lib_options['filters_to_show'], true ) ) {
                $vergeml_lib_options['filters_to_show'][] = 'ai';
            }
        }

        update_option( 'vergeml_lib_options', $vergeml_lib_options );

        /*
         *  3.9.1: the index gains a projection column, so search by meaning
         *  can read 256 bytes a row instead of unpacking 3KB. dbDelta adds
         *  the column and touches nothing else; existing rows are converted
         *  a batch at a time by the searches themselves.
         */
        if ( version_compare( get_option( 'vergeml_version', '' ), '3.9.1', '<' ) && function_exists( 'vergeml_index_install' ) ) {
            vergeml_index_install();
        }


        /*
         *  Private folders: the option has to EXIST, not merely be autoloaded.
         *
         *  core/private-folders.php keeps a term-id-to-owner map and reasons
         *  that an autoloaded option is free. That is true of an option that is
         *  there. An option that has never been written is not in alloptions, so
         *  get_option() runs a real query for it and caches the miss -- once per
         *  request, on every site where nobody has ever made a private folder,
         *  which is nearly every site.
         *
         *  Gate 5 caught it: Playground read 8 on the tree against the box's 7,
         *  and core's own wp/v2/media had gained one as well -- which is what
         *  said it was not a folder problem. Writing the empty map here puts it
         *  in alloptions and the query goes away.
         */
        /*
         *  The AI settings array gains site_profile. Merged rather than
         *  written, so a site that already has a key and its choices keeps
         *  them -- a changed default does nothing for an install that already
         *  has the old value written down.
         */
        $vergeml_ai_settings = get_option( 'vergeml_ai', array() );

        if ( is_array( $vergeml_ai_settings ) && ! array_key_exists( 'site_profile', $vergeml_ai_settings ) ) {
            $vergeml_ai_settings['site_profile'] = '';
            update_option( 'vergeml_ai', $vergeml_ai_settings, false );
        }

        if ( false === get_option( 'vergeml_private_folders', false ) ) {
            add_option( 'vergeml_private_folders', array(), '', true );
        }


        // taxonomy options
        $eml_tax_options_defaults = array(
            'tax_archives' => 0, // since 2.6
            'edit_all_as_hierarchical' => 0,
            'bulk_edit_save_button' => 0, // since 2.7
            /*
             *  Off, because a file living in several folders is the thing this
             *  plugin can do and the others cannot. On, a plain drag moves --
             *  which is what somebody switching from FileBird expects.
             */
            'one_folder_per_file' => 0 // since 3.1
        );

        $vergeml_tax_options = array_intersect_key( $vergeml_tax_options, $eml_tax_options_defaults );
        $vergeml_tax_options = array_merge( $eml_tax_options_defaults, $vergeml_tax_options );

        update_option( 'vergeml_tax_options', $vergeml_tax_options );


        // MIME types
        if ( false === get_option( 'vergeml_mimes' ) ) {

            $allowed_mimes = get_allowed_mime_types();

            foreach ( wp_get_mime_types() as $ext => $type ) {
                $vergeml_mimes[$ext] = array(
                    'mime'     => $type,
                    'singular' => $type,
                    'plural'   => $type,
                    'filter'   => 0,
                    'upload'   => isset($allowed_mimes[$ext]) ? 1 : 0
                );
            }

            update_option( 'vergeml_mimes', $vergeml_mimes );
        }

        if ( version_compare( get_option( 'vergeml_version', '' ), '2.8.9', '<=' ) ) {
            // getting rid of mime type backup
            delete_site_option( 'vergeml_mimes_backup' );
        }

        do_action( 'vergeml_set_options' );
    }



    /**
     *  vergeml_set_network_options
     *
     *  @since    2.6.3
     *  @created  21/05/18
     */

    function vergeml_set_network_options() {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        $vergeml_network_options_defaults = array(
            'media_settings' => 1,
            'utilities' => 1
        );

        $vergeml_network_options = array_intersect_key( $vergeml_network_options, $vergeml_network_options_defaults );
        $vergeml_network_options = array_merge( $vergeml_network_options_defaults, $vergeml_network_options );

        update_site_option( 'vergeml_network_options', $vergeml_network_options );
    }



    /**
     *  vergeml_settings_link
     *
     *  Add settings link to the plugin action links
     *
     *  @since    2.1
     *  @created  27/10/15
     */

    function vergeml_settings_link( $actions ) {

        $settings_page = is_network_admin() ? 'settings.php' : 'options-general.php';

        if ( ! is_network_admin() ) {
            $custom_links['settings'] = '<a href="' . self_admin_url($settings_page.'?page=media') . '">' . __( 'Media Settings', 'vergelabs-media-library' ) . '</a>';
        }

        $custom_links['utility'] = '<a href="' . self_admin_url($settings_page.'?page=eml-settings') . '">' . __( 'Utilities', 'vergelabs-media-library' ) . '</a>';

        return array_merge( $custom_links, $actions );
    }



    /**
     *  vergeml_plugin_row_meta
     *
     *  @since    2.2.1
     *  @created  11/04/15
     */

    function vergeml_plugin_row_meta( $plugin_meta, $plugin_file ) {

        if ( vergeml_get_basename() !== $plugin_file ) {
            return $plugin_meta;
        }

        /*
         *  These pointed at the original author's site: documentation, a
         *  support desk that does not answer for this fork, an upsell to their
         *  paid version, and a beta of their 3.0. None of them help somebody
         *  running this plugin, and the upsell sent our own users off to buy
         *  someone else's product.
         *
         *  Attribution stays -- in the plugin header, the readme, and the
         *  settings footer, where it belongs. A link labelled "Support" has to
         *  lead somewhere that actually gives support.
         */

        $plugin_meta[] = '<a href="' . esc_url( 'https://vergelabsmedia.com' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Docs', 'vergelabs-media-library' ) . '</a>';
        $plugin_meta[] = '<a href="' . esc_url( 'https://github.com/vergelabsnathan/vergelabs-media-library/issues' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Support', 'vergelabs-media-library' ) . '</a>';

        return $plugin_meta;
    }



    /**
     *  Enable Infinite Scrolling
     *
     *  @since    2.9
     *  @created  09/2021
     */

    if ( vergeml_enable_infinite_scrolling() ) {
        add_filter( 'media_library_infinite_scrolling', '__return_true' );
    }



    /**
     *  Free functionality
     */

    /*
     *  Safe mode stops here. Everything below this line is the plugin's actual
     *  behaviour, and after two fatal errors in an hour none of it loads --
     *  the site comes back, the plugin stays active, and the notice in
     *  core/watchdog.php explains why.
     */
    // Outside the guard on purpose: a site in safe mode is the one that most
    // needs to ask for help, and the report is what support needs first.
    // Both files are functions and one menu item; neither touches the database.
    include_once( 'core/system-report.php' );
    include_once( 'core/get-help.php' );

    if ( ! vergeml_safe_mode() ) {

        include_once( 'core/mime-types.php' );
        include_once( 'core/taxonomies.php' );
        include_once( 'core/media-templates.php' );
        include_once( 'core/compatibility.php' );
        // Other folder plugins and optimisers: a notice, and exclusions.
        include_once( 'core/neighbours.php' );
        include_once( 'core/search.php' );
        include_once( 'core/auto-assign.php' );
        // Polylang and WPML: a translated copy of a filed image keeps its folders.
        include_once( 'core/multilingual.php' );
        include_once( 'core/bulk-terms.php' );
        include_once( 'core/rest-tree.php' );
        include_once( 'core/rest-folders.php' );
        // The second road to every endpoint above and below: the same handlers
        // reached through admin-ajax when a host or a security plugin has
        // closed /wp-json/. Inside the guard, like everything it stands in for.
        include_once( 'core/transport.php' );
        // After rest-tree.php, whose taxonomy list it filters and whose nodes
        // it adds a field to. Inside the guard: it hides folders from people,
        // and anything that hides things has to be switchable off.
        include_once( 'core/private-folders.php' );
        include_once( 'core/tree-ui.php' );
        include_once( 'core/import-sources.php' );
        // After import-sources.php, whose reader dispatch it adds a case to,
        // and whose source registry it filters. Before import-ui.php, which
        // routes the upload at it.
        include_once( 'core/import-csv.php' );
        include_once( 'core/import.php' );
        include_once( 'core/import-ui.php' );
        include_once( 'core/post-folders.php' );
        include_once( 'core/page-builders.php' );
        include_once( 'core/gallery-block.php' );
        include_once( 'core/gallery-widgets.php' );
        include_once( 'core/folder-tools.php' );
        include_once( 'core/smart-folders.php' );
        // After smart-folders.php: the health report reads the size index that
        // file defines, and reuses its constant rather than restating it.
        include_once( 'core/health.php' );
        include_once( 'core/instrument.php' );
        // Before ai.php: the AI layer reads and writes through the index.
        include_once( 'core/ai-index.php' );
        include_once( 'core/ai.php' );
        // What the page an image sits on is trying to be; advisory context for describe.
        include_once( 'core/seo-context.php' );
        // After ai.php: the background run is that file's own step function on
        // a schedule, so it is meaningless without it. Inside the guard like
        // everything else -- a run that crashes has to be switchable off, and
        // safe mode also unhooks the cron callback that would crash again.
        include_once( 'core/ai-background.php' );
        // After both: the AI smart folders hang off smart-folders.php's
        // registry and join ai-index.php's table, so neither may be missing
        // when this file registers its filters. Inside the guard like the
        // rest -- in safe mode the panel goes back to five folders rather
        // than to five broken ones.
        include_once( 'core/ai-folders.php' );
        // After ai-index.php: the proposed tree is clustered from the vectors
        // that file stores, and reads them through $wpdb->vergeml_ai_index --
        // which does not exist until ai-index.php has registered it.
        include_once( 'core/organize.php' );
        // After organize.php: the Librarian reviews and applies the trees that
        // file proposes, and reads them through its helpers. Inside the guard
        // like everything else here, so a crash while applying can be switched
        // off -- which for the one feature that writes to somebody's library
        // is the whole reason the guard exists.
        include_once( 'core/librarian.php' );
        // After librarian.php: filing by itself writes into that file's moves
        // log, so its undo covers this without knowing it exists, and it uses
        // its taxonomy helper. Inside the guard, like everything that writes.
        include_once( 'core/auto-file.php' );
        // After auto-file.php: spoken commands log their moves through the
        // same batch helper, and refuse to exist without the taxonomy helper
        // both of them share.
        include_once( 'core/nl-commands.php' );
        // Setting files aside. After smart-folders.php, whose registry it
        // adds a row to; inside the guard, because it hides things from the
        // media library and that has to be switchable off.
        include_once( 'core/quarantine.php' );
        // Naming files after what is in them. After the index, whose stored
        // title it applies and whose locked list it obeys; inside the guard,
        // because it writes to posts.
        include_once( 'core/rename.php' );
        // Renaming the file on disk, which needs both the index (for the name)
        // and smart-folders (for what points at it) already loaded.
        include_once( 'core/rename-file.php' );
        // After health.php: the report asks it which copy to keep.
        include_once( 'core/health-delete.php' );
        // After librarian.php and search-meaning.php: it borrows the folder
        // taxonomy from one and the embedding call from the other.
        include_once( 'core/folder-talk.php' );
        // Editing a file from the list it is in. After the index, whose lock
        // on a hand-written field is the whole reason this is safe.
        include_once( 'core/quick-edit.php' );
        // Searching by what a picture means. After organize.php, whose
        // projection and distance it borrows so that "alike" means one thing
        // in this plugin, and after ai.php for the service URL.
        include_once( 'core/search-meaning.php' );
        // Last, because each of the three things in it stands on something
        // above: the health report's duplicate groups, the index's locked
        // fields, and the vectors auto-file reads.
        include_once( 'core/utilities.php' );
        // Last of the guarded files: it wraps every screen the ones above
        // registered, so it has to see a finished menu. Inside the guard --
        // if the shell breaks, safe mode gives back plain WordPress screens
        // rather than nine broken ones.
        // Reads the state helpers of librarian, health, organize, ai and
        // import, so it loads after all of them. Every read is guarded --
        // in safe mode those files are absent and the stage is simply not
        // in the list.
        include_once( 'core/icons.php' );
        include_once( 'core/journey.php' );
        include_once( 'core/help.php' );
        include_once( 'core/admin-shell.php' );

        if ( vergeml_enhance_media_shortcodes() ) {
            include_once( 'core/medialist.php' );
        }

        if ( is_admin() ) {
            // Before the option pages: they hang their screens off its menu.
            include_once( 'core/admin-menu.php' );
            include_once( 'core/options-pages.php' );
        }
    }

}
