<?php
/**
 *  Plugin Name: Upgrade test helper
 *
 *  Drives the Enhanced Media Library -> VergeLabs Media Library upgrade test.
 *  Loaded as an mu-plugin by tests/compat/upgrade.js, never shipped.
 *
 *  Every action is behind a shared secret and only answers when the constant
 *  is defined, so this cannot do anything on a site that is not the test site.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/*
 *  wp_loaded, not init: both plugins register their taxonomies during init, so
 *  a handler running inside init sees no media taxonomies at all and reports
 *  every term assignment as missing.
 */
/*
 *  ?vergeml_rtl=1 renders the admin right-to-left, so the mirrored stylesheet can
 *  actually be looked at. WordPress swaps in the -rtl.css file when is_rtl() is
 *  true, and is_rtl() just reads the locale's text direction -- no language
 *  pack needed to exercise the layout.
 */
if ( isset( $_GET['vergeml_rtl'] ) ) {

    add_action( 'init', function () {
        if ( isset( $GLOBALS['wp_locale'] ) )
            $GLOBALS['wp_locale']->text_direction = 'rtl';
    }, 1 );

    add_filter( 'language_attributes', function ( $output ) {
        return false === strpos( $output, 'dir=' ) ? $output . ' dir="rtl"' : str_replace( 'dir="ltr"', 'dir="rtl"', $output );
    } );
}

add_action( 'wp_loaded', function () {

    if ( ! isset( $_GET['vergeml_test'] ) )
        return;

    if ( ! defined( 'VGML_TEST_KEY' ) || ! isset( $_GET['key'] ) || $_GET['key'] !== VGML_TEST_KEY )
        return;

    $action = sanitize_key( $_GET['vergeml_test'] );
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $out = array( 'action' => $action );

    if ( 'activate' === $action || 'deactivate' === $action ) {

        $file = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';

        if ( 'activate' === $action ) {
            $r = activate_plugin( $file );
            $out['result'] = is_wp_error( $r ) ? $r->get_error_message() : 'activated';
        }
        else {
            deactivate_plugins( array( $file ) );
            $out['result'] = 'deactivated';
        }

        $out['active'] = get_option( 'active_plugins', array() );
    }

    /*
     *  Returns the site to a pre-install state so a run starts from nothing.
     *  Faster and more certain than rebooting the server, and it keeps the
     *  "fork options absent" precondition honest between runs.
     */
    elseif ( 'reset' === $action ) {

        deactivate_plugins( array(
            'enhanced-media-library/enhanced-media-library.php',
            'vergelabs-media-library/vergelabs-media-library.php',
        ) );

        foreach ( array( 'version', 'lib_options', 'taxonomies', 'tax_options', 'mimes', 'backup' ) as $key ) {
            delete_option( 'wpuxss_eml_' . $key );
            delete_option( 'vergeml_' . $key );
        }

        foreach ( array( 'media_category', 'client_project' ) as $tax ) {
            if ( taxonomy_exists( $tax ) ) {
                foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) as $t )
                    wp_delete_term( $t->term_id, $tax );
            }
        }

        foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p )
            wp_delete_attachment( $p->ID, true );

        $out['result'] = 'reset';
    }

    /*
     *  Seeds a site that looks like a real Enhanced Media Library install:
     *  its own taxonomy, terms with files filed under them, a custom MIME
     *  type, and settings deliberately set away from their defaults. Defaults
     *  would make a migration that silently did nothing look like a success.
     */
    elseif ( 'seed_eml' === $action ) {

        /*
         *  Edit the options Enhanced Media Library wrote itself rather than
         *  inventing their shape. An invented shape makes the fork's own
         *  defaults look like data loss on migration, which is a false alarm,
         *  and would hide a real one.
         *
         *  Values are moved off their defaults on purpose: settings that
         *  already equal the default cannot show whether they were carried
         *  over or simply re-created.
         */

        $lib = get_option( 'wpuxss_eml_lib_options', array() );

        if ( ! $lib ) { $out['error'] = 'EML options absent -- activate EML first'; }
        else {

            $lib['media_orderby']      = 'title';
            $lib['media_order']        = 'ASC';
            $lib['natural_sort']       = 1;
            $lib['grid_show_caption']  = 1;
            $lib['filters_to_show']    = array( 'types', 'taxonomies' );
            $lib['search_in']          = array( 'titles', 'filenames' );
            $lib['loads_per_page']     = 40;
            update_option( 'wpuxss_eml_lib_options', $lib );

            $tax_opts = get_option( 'wpuxss_eml_tax_options', array() );
            $tax_opts['tax_archives']          = 1;
            $tax_opts['bulk_edit_save_button'] = 1;
            update_option( 'wpuxss_eml_tax_options', $tax_opts );

            // a second taxonomy, cloned from the real media_category entry
            $taxes = get_option( 'wpuxss_eml_taxonomies', array() );

            if ( isset( $taxes['media_category'] ) ) {

                $taxes['media_category']['media_popup_taxonomy_edit'] = 1;

                $clone = $taxes['media_category'];
                $clone['hierarchical'] = 0;
                $clone['labels'] = array_map(
                    function ( $l ) { return str_replace( array( 'Media Categories', 'Media Category' ), array( 'Client projects', 'Client project' ), $l ); },
                    $clone['labels']
                );
                if ( isset( $clone['rewrite']['slug'] ) ) $clone['rewrite']['slug'] = 'client_project';

                $taxes['client_project'] = $clone;
                update_option( 'wpuxss_eml_taxonomies', $taxes );
            }

            $mimes = get_option( 'wpuxss_eml_mimes', array() );
            $mimes['psd'] = array( 'mime' => 'image/vnd.adobe.photoshop', 'singular' => 'Photoshop file', 'plural' => 'Photoshop files', 'filter' => 1, 'upload' => 1 );
            if ( isset( $mimes['png'] ) ) $mimes['png']['filter'] = 1;
            update_option( 'wpuxss_eml_mimes', $mimes );
        }

        // terms need the taxonomies registered in *this* request too
        foreach ( array( 'media_category', 'client_project' ) as $tax ) {
            if ( ! taxonomy_exists( $tax ) )
                register_taxonomy( $tax, 'attachment', array( 'hierarchical' => true, 'public' => true ) );
        }

        $terms = array(
            'media_category' => array( 'Logos', 'Photos', 'Client work & co' ),
            'client_project' => array( 'Acme rebrand', 'Zenith launch' ),
        );

        $created = array();

        foreach ( $terms as $tax => $names ) {
            foreach ( $names as $name ) {
                $t = term_exists( $name, $tax );
                if ( ! $t ) $t = wp_insert_term( $name, $tax );
                if ( ! is_wp_error( $t ) ) $created[ $tax ][ $name ] = (int) $t['term_id'];
            }
        }

        $dir   = wp_upload_dir();
        $files = array( 'Zephyr mark' => 'Logos', 'Quartz emblem' => 'Logos', 'Harbour at dusk' => 'Photos', 'Ridgeline study' => 'Client work & co' );
        $i     = 0;
        $ids   = array();

        foreach ( $files as $title => $term_name ) {

            $found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'title' => $title, 'numberposts' => 1 ) );

            if ( $found ) { $ids[ $title ] = $found[0]->ID; $i++; continue; }

            /*
             *  A literal 1x1 PNG. GD is not present in the Playground build, so
             *  drawing one is not an option, and a real file still has to exist
             *  for the attachment to behave like one.
             */
            $png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
            $path = trailingslashit( $dir['path'] ) . 'upg-' . $i . '.png';
            file_put_contents( $path, $png );

            $id = wp_insert_attachment( array( 'post_title' => $title, 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $path );
            wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
            wp_set_object_terms( $id, array( $created['media_category'][ $term_name ] ), 'media_category' );

            if ( 'Zephyr mark' === $title )
                wp_set_object_terms( $id, array( $created['client_project']['Acme rebrand'] ), 'client_project' );

            $ids[ $title ] = $id;
            $i++;
        }

        $out['terms']       = $created;
        $out['attachments'] = $ids;
    }

    elseif ( 'plugins' === $action ) {
        $out['installed'] = array_keys( get_plugins() );
    }

    /*
     *  Turns every search column and filter on. Used to prove that a search
     *  returning nothing was the column being switched off rather than the
     *  search being broken.
     */
    elseif ( 'enable_all_search' === $action ) {

        $lib = get_option( 'vergeml_lib_options', array() );
        $lib['search_in']       = array( 'titles', 'captions', 'descriptions', 'filenames', 'authors', 'taxonomies' );
        $lib['filters_to_show'] = array( 'types', 'dates', 'authors', 'taxonomies' );
        update_option( 'vergeml_lib_options', $lib );

        $out['search_in'] = $lib['search_in'];
    }

    /*
     *  The comparison surface: both option sets, plus the term assignments,
     *  because options surviving while every file loses its category would
     *  still be a broken upgrade.
     */
    elseif ( 'dump' === $action ) {

        foreach ( array( 'version', 'lib_options', 'taxonomies', 'tax_options', 'mimes', 'backup' ) as $key ) {
            $out['eml'][ $key ]     = get_option( 'wpuxss_eml_' . $key, '__absent__' );
            $out['vergeml'][ $key ] = get_option( 'vergeml_' . $key, '__absent__' );
        }

        $out['assignments'] = array();

        foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
            foreach ( array( 'media_category', 'client_project' ) as $tax ) {
                $t = wp_get_object_terms( $p->ID, $tax, array( 'fields' => 'names' ) );
                if ( ! is_wp_error( $t ) && $t )
                    $out['assignments'][ $p->post_title ][ $tax ] = $t;
            }
        }

        $out['registered_taxonomies'] = array_values( array_intersect(
            array( 'media_category', 'client_project' ),
            get_object_taxonomies( 'attachment' )
        ) );

        $out['active'] = get_option( 'active_plugins', array() );
    }

    header( 'Content-Type: application/json' );
    echo wp_json_encode( $out );
    exit;
} );
