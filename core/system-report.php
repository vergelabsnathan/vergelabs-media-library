<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  System report.
 *
 *  A bug report normally costs four exchanges: what broke, which WordPress,
 *  which other plugins, can you paste the error. This collapses that into one
 *  button the reporter presses before they write a word.
 *
 *  What it deliberately does NOT contain: licence keys, user email addresses,
 *  usernames, database credentials, or anything from wp-config beyond the two
 *  debug flags. People paste this into public support forums, and a report that
 *  leaks a credential is worse than no report at all.
 *
 *  @since    2.11
 */


/**
 *  vergeml_system_report_data
 *
 *  Everything worth knowing when something has gone wrong, and nothing that
 *  would embarrass the person pasting it.
 */

function vergeml_system_report_data() {

    global $wp_version, $wpdb;

    $theme  = wp_get_theme();
    $uploads = wp_upload_dir();

    $report = array();

    $report[ __( 'Plugin', 'vergelabs-media-library' ) ] = array(
        'version'        => VERGEML_VERSION,
        'stored version' => get_option( 'vergeml_version', '(none)' ),
        // Tells us instantly whether they upgraded from the original plugin,
        // which is the first question on half of all reports.
        'migrated from'  => get_option( 'wpuxss_eml_version', false ) ? 'Enhanced Media Library ' . get_option( 'wpuxss_eml_version' ) : 'no',
    );

    $report[ __( 'WordPress', 'vergelabs-media-library' ) ] = array(
        'version'    => $wp_version,
        'multisite'  => is_multisite() ? 'yes' : 'no',
        'language'   => get_locale(),
        'home'       => get_option( 'home' ),
        'permalinks' => get_option( 'permalink_structure' ) ? 'pretty' : 'plain',
        'debug'      => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'on' : 'off',
        'debug log'  => ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? 'on' : 'off',
    );

    $report[ __( 'Server', 'vergelabs-media-library' ) ] = array(
        'php'                 => PHP_VERSION,
        'mysql'               => $wpdb->db_version(),
        'memory limit'        => ini_get( 'memory_limit' ),
        'wp memory limit'     => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '(default)',
        'max execution time'  => ini_get( 'max_execution_time' ) . 's',
        'max upload size'     => size_format( wp_max_upload_size() ),
        'post max size'       => ini_get( 'post_max_size' ),
        'max input vars'      => ini_get( 'max_input_vars' ),
        // A large library with too few input vars is the cause of settings
        // silently truncating on save, which looks like a plugin bug.
        'gd'                  => extension_loaded( 'gd' ) ? 'yes' : 'no',
        'imagick'             => extension_loaded( 'imagick' ) ? 'yes' : 'no',
        'uploads writable'    => wp_is_writable( $uploads['basedir'] ) ? 'yes' : 'no',
    );

    $report[ __( 'Theme', 'vergelabs-media-library' ) ] = array(
        'name'    => $theme->get( 'Name' ),
        'version' => $theme->get( 'Version' ),
        'parent'  => $theme->parent() ? $theme->parent()->get( 'Name' ) . ' ' . $theme->parent()->get( 'Version' ) : '(none)',
    );

    /*
     *  Media counts. "It is slow" means something different at 400 items than
     *  at 400,000, and the answer is usually in these two numbers.
     */
    $counts = (array) wp_count_attachments();
    $total  = 0;
    foreach ( $counts as $n ) {
        $total += (int) $n;
    }

    $taxonomies = array();
    foreach ( get_object_taxonomies( 'attachment', 'objects' ) as $tax ) {
        $terms = wp_count_terms( array( 'taxonomy' => $tax->name, 'hide_empty' => false ) );
        $taxonomies[ $tax->name ] = is_wp_error( $terms ) ? '?' : (int) $terms . ' terms';
    }

    $report[ __( 'Media library', 'vergelabs-media-library' ) ] = array_merge(
        array( 'attachments' => $total ),
        $taxonomies
    );

    /*
     *  Plugin settings, verbatim. Half of all "it does not work" reports are a
     *  setting switched off, and asking someone to describe their settings is
     *  slower and less reliable than reading them.
     */
    $lib = get_option( 'vergeml_lib_options', array() );
    $settings = array();
    foreach ( array( 'media_orderby', 'media_order', 'natural_sort', 'force_filters', 'show_count',
                     'include_children', 'infinite_scrolling', 'loads_per_page', 'grid_show_caption',
                     'search_on_enter', 'search_min_letters', 'enhance_media_shortcodes' ) as $k ) {
        if ( isset( $lib[ $k ] ) )
            $settings[ $k ] = is_array( $lib[ $k ] ) ? implode( ', ', $lib[ $k ] ) : (string) $lib[ $k ];
    }
    foreach ( array( 'filters_to_show', 'search_in' ) as $k ) {
        $settings[ $k ] = isset( $lib[ $k ] ) && is_array( $lib[ $k ] ) ? implode( ', ', $lib[ $k ] ) : '(none)';
    }

    $report[ __( 'Plugin settings', 'vergelabs-media-library' ) ] = $settings;

    /*
     *  Every active plugin, with versions. This is the single most useful part
     *  of the report: nearly every hard bug is an interaction, and the list is
     *  the first thing that has to be asked for otherwise.
     */
    if ( ! function_exists( 'get_plugins' ) )
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $active = array();
    foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
        $data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
        $active[] = $data['Name'] . ' ' . $data['Version'];
    }
    sort( $active );

    if ( is_multisite() ) {
        foreach ( array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) as $file ) {
            $data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
            $active[] = $data['Name'] . ' ' . $data['Version'] . ' (network)';
        }
    }

    $report[ __( 'Active plugins', 'vergelabs-media-library' ) ] = $active;

    /**
     *  Filter the system report before it is rendered.
     *
     *  @since 2.11
     *  @param array $report
     */
    return apply_filters( 'vergeml_system_report', $report );
}


/**
 *  vergeml_system_report_text
 *
 *  Plain text, because it gets pasted into forums, emails and issue trackers
 *  that do not agree on anything else.
 */

function vergeml_system_report_text() {

    $lines = array();

    foreach ( vergeml_system_report_data() as $section => $rows ) {

        $lines[] = '### ' . $section;

        if ( wp_is_numeric_array( $rows ) ) {
            foreach ( $rows as $value ) {
                $lines[] = '- ' . $value;
            }
        }
        else {
            $width = 0;
            foreach ( array_keys( $rows ) as $label ) {
                $width = max( $width, strlen( $label ) );
            }
            foreach ( $rows as $label => $value ) {
                $lines[] = str_pad( $label, $width ) . ' : ' . $value;
            }
        }

        $lines[] = '';
    }

    return implode( "\n", $lines );
}


/**
 *  vergeml_system_report_render
 *
 *  Rendered into the Utilities screen. The textarea is readonly and selected on
 *  focus, so the whole thing can be copied without the clipboard API, which
 *  browsers refuse outside a secure context.
 */

function vergeml_system_report_render() {

    if ( ! current_user_can( 'manage_options' ) )
        return;

    $report = vergeml_system_report_text();
    ?>
    <div class="postbox">

        <h3 class="hndle"><?php esc_html_e( 'System report', 'vergelabs-media-library' ); ?></h3>

        <div class="inside">

            <p class="description">
                <?php esc_html_e( 'Paste this into a bug report and skip the back-and-forth. It contains no licence key, no email address and no password.', 'vergelabs-media-library' ); ?>
            </p>

            <p>
                <button type="button" class="button button-primary" id="vergeml-copy-report"><?php esc_html_e( 'Copy system report', 'vergelabs-media-library' ); ?></button>
                <span id="vergeml-copy-report-done" class="description" style="margin-left:8px" hidden><?php esc_html_e( 'Copied.', 'vergelabs-media-library' ); ?></span>
            </p>

            <label for="vergeml-system-report" class="screen-reader-text"><?php esc_html_e( 'System report', 'vergelabs-media-library' ); ?></label>
            <textarea id="vergeml-system-report" readonly rows="14"
                      style="width:100%;font-family:Consolas,Monaco,monospace;font-size:12px;line-height:1.5"><?php echo esc_textarea( $report ); ?></textarea>

        </div>
    </div>

    <?php
    wp_enqueue_script( 'vergeml-system-report', plugins_url( 'js/vergeml-system-report.js', VERGEML_FILE ), array(), vergeml_asset_ver( 'js/vergeml-system-report.js' ), true );
}
