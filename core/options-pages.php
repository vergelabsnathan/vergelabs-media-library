<?php

if ( ! defined( 'ABSPATH' ) )
    exit;



/**
 *  vergeml_register_setting
 *
 *  @since    1.0
 *  @created  03/08/13
 */

add_action( 'admin_init', 'vergeml_register_setting' );

function vergeml_register_setting() {

    // plugin settings: media library
    register_setting(
        'media-library', //option_group
        'vergeml_lib_options', //option_name
        'vergeml_lib_options_validate' //sanitize_callback
    );

    // plugin settings: taxonomies
    register_setting(
        'media-taxonomies', //option_group
        'vergeml_taxonomies', //option_name
        'vergeml_taxonomies_validate' //sanitize_callback
    );

    // plugin settings: taxonomies options
    register_setting(
        'media-taxonomies', //option_group
        'vergeml_tax_options', //option_name
        'vergeml_tax_options_validate' //sanitize_callback
    );

    // plugin settings: mime types
    register_setting(
        'mime-types', //option_group
        'vergeml_mimes', //option_name
        'vergeml_mimes_validate' //sanitize_callback
    );

    // plugin settings: network settings
    // validated explicitly in vergeml_update_network_settings; the callback
    // below is the generic guard for anything arriving via the Settings API
    register_setting(
        'eml-network-settings', //option_group
        'vergeml_network_options', //option_name
        'vergeml_sanitize_option_array' //sanitize_callback
    );

    // plugin settings: all settings backup before import
    register_setting(
        'vergeml_backup', //option_group
        'vergeml_backup', //option_name
        'vergeml_sanitize_option_array' //sanitize_callback
    );

    // plugin settings: remote admin notices
    register_setting(
        'vergeml_notices', //option_group
        'vergeml_notices', //option_name
        'vergeml_sanitize_option_array' //sanitize_callback
    );
}



/**
 *  vergeml_sanitize_option_array
 *
 *  Generic sanitiser for the option groups that carry no validation callback of
 *  their own. Walks the array and runs every string through
 *  sanitize_text_field, leaving structure and scalar types alone so stored
 *  integers and booleans do not come back as strings.
 *
 *  These three options are normally written with update_option rather than
 *  through options.php, so in practice this runs only if something posts them
 *  via the Settings API.
 *
 *  @since    2.9.8
 */

function vergeml_sanitize_option_array( $value ) {

    if ( is_array( $value ) ) {
        return array_map( 'vergeml_sanitize_option_array', $value );
    }

    if ( is_string( $value ) ) {
        return sanitize_text_field( $value );
    }

    // int, float, bool and null are already safe to store as they are
    return $value;
}



/**
 *  vergeml_admin_media_menu
 *
 *  @since    2.6
 *  @created  28/04/18
 */

add_action( 'admin_menu', 'vergeml_admin_media_menu', 12 );

function vergeml_admin_media_menu() {

    if ( is_multisite() ) {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        if ( ! current_user_can( 'manage_network_options' ) && ! (bool) $vergeml_network_options['media_settings'] )
            return;
    }


    $eml_media_options_page = add_submenu_page(
        '',
        __('Media Settings','vergelabs-media-library'), //page_title
        '',                                //menu_title
        'manage_options',                  //capability
        'media',                           //menu_slug
        'vergeml_print_media_settings'  //callback
    );

    $eml_medialibrary_options_page = add_submenu_page(
        'options-general.php',
        __('Media Library','vergelabs-media-library') . ' &lsaquo; ' . __('Media Settings','vergelabs-media-library'),
        __('Media Library','vergelabs-media-library'),
        'manage_options',
        'media-library',
        'vergeml_print_media_library_options'
    );

    $eml_taxonomies_options_page = add_submenu_page(
        'options-general.php',
        __('Media Taxonomies','vergelabs-media-library') . ' &lsaquo; ' . __('Media Settings','vergelabs-media-library'),
        __('Media Taxonomies','vergelabs-media-library'),
        'manage_options',
        'media-taxonomies',
        'vergeml_print_taxonomies_options'
    );

    $eml_mimetype_options_page = add_submenu_page(
        'options-general.php',
        __('MIME Types','vergelabs-media-library') . ' &lsaquo; ' . __('Media Settings','vergelabs-media-library'),
        __('MIME Types','vergelabs-media-library'),
        'manage_options',
        'mime-types',
        'vergeml_print_mimetypes_options'
    );


    add_action( 'load-' . $eml_media_options_page, 'vergeml_load_media_options_page' );
    add_action( $eml_media_options_page, 'vergeml_media_options_page' );

    add_action('admin_print_scripts-' . $eml_medialibrary_options_page, 'vergeml_medialibrary_options_page_scripts');
    add_action('admin_print_scripts-' . $eml_taxonomies_options_page, 'vergeml_taxonomies_options_page_scripts');
    add_action('admin_print_scripts-' . $eml_mimetype_options_page, 'vergeml_mimetype_options_page_scripts');
}



/**
 *  vergeml_admin_utility_menu
 *
 *  @since    2.6
 *  @created  28/04/18
 */

add_action( 'admin_menu', 'vergeml_admin_utility_menu' );

function vergeml_admin_utility_menu() {

    if ( is_multisite() ) {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        if ( ! current_user_can( 'manage_network_options' ) && ! (bool) $vergeml_network_options['utilities'] )
            return;
    }


    $eml_options_page = add_options_page(
       __('VergeLabs Media Library Utilities','vergelabs-media-library'),
       __('Media Utilities','vergelabs-media-library'),
       'manage_options',
       'eml-settings',
       'vergeml_print_settings'
    );

    add_action('admin_print_scripts-' . $eml_options_page, 'vergeml_options_page_scripts');
}



/**
 *  vergeml_network_admin_menu
 *
 *  @since    2.6
 *  @created  22/04/18
 */

add_action( 'network_admin_menu', 'vergeml_network_admin_menu' );

function vergeml_network_admin_menu() {

    $eml_network_options_page = add_submenu_page(
        'settings.php',
        __('VergeLabs Media Library Utilities','vergelabs-media-library'),
        __('Media Utilities','vergelabs-media-library'),
        'manage_options',
        'eml-settings',
        'vergeml_print_network_settings'
    );

    add_action('admin_print_scripts-' . $eml_network_options_page, 'vergeml_options_page_scripts');
}



/**
 *  vergeml_submenu_order
 *
 *  Custom admin media menu.
 *
 *  @since    2.6
 *  @created  04/03/18
 */

add_action( 'admin_menu', 'vergeml_submenu_order', 1001 );

function vergeml_submenu_order() {

    global $submenu;


    if ( ! isset( $submenu['options-general.php'] ) ) {
        return;
    }

    $media_key = 0;
    $media_items = array();
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which settings tab to mark active, no state is changed.
    $requested_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $page = in_array( $requested_page, array( 'media', 'media-library', 'media-taxonomies', 'mime-types' ), true ) ? $requested_page : '';
    $settings_menu = array_values( $submenu['options-general.php'] );

    foreach( $settings_menu as $key => $item ) {

        if ( 'options-media.php' === $item[2] ) {

            $media_key = $key;
            $settings_menu[$key][2] = 'options-general.php?page=media';
            $settings_menu[$key][4] = ( 'media' === $page ) ? 'eml-menu-media current' : 'eml-menu-media';
        }

        if ( in_array( $item[2], array('media-library','media-taxonomies','mime-types') ) ) {

            $item[4] = 'eml-media-submenu';
            $media_items[] = $item;

            unset( $settings_menu[$key] );
        }
    }

    array_splice( $settings_menu, $media_key+1, 0, $media_items );
    $submenu['options-general.php'] = $settings_menu;
}



/**
 *  vergeml_load_media_options_page
 *
 *  Ensure compatibility with default options-media.php for third-parties
 *
 *  @since    2.3
 *  @created  14/06/16
 */

function vergeml_load_media_options_page() {

    global $pagenow, $title;

    // to avoid the unknown global value (php 8)
    // @todo: look deeper
    $title = '';

    $hook_suffix = $pagenow = 'options-media.php';

    /*
     *  These are WordPress's own admin page lifecycle hooks, not ours. This
     *  screen replaces options-media.php, so it has to fire them or every other
     *  plugin that enqueues assets or prints markup for that screen is silently
     *  skipped. Prefixing them would not namespace anything, it would stop them
     *  reaching the callbacks they exist for.
     */

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hooks, deliberately fired.
    do_action( "load-{$hook_suffix}" );
    do_action( 'admin_enqueue_scripts', $hook_suffix );
    do_action( "admin_print_styles-{$hook_suffix}" );
    do_action( "admin_print_scripts-{$hook_suffix}" );
    do_action( "admin_head-{$hook_suffix}" );
    // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

    add_filter( 'admin_body_class', 'vergeml_admin_body_class_for_media_options_page' );
    add_filter( 'admin_title', 'vergeml_admin_title_for_media_options_page', 10, 2 );
}



/**
 *  vergeml_admin_body_class_for_media_options_page
 *
 *  Ensure compatibility with default options-media.php for third-parties
 *
 *  @since    2.3.6
 *  @created  16/12/16
 */

function vergeml_admin_body_class_for_media_options_page( $admin_body_class ) {

    $hook_suffix = 'options-media.php';

    $admin_body_class .= preg_replace('/[^a-z0-9_\-]+/i', '-', $hook_suffix);

    return $admin_body_class;
}



/**
 *  vergeml_admin_title_for_media_options_page
 *
 *  @since    2.3.6
 *  @created  16/12/16
 */

function vergeml_admin_title_for_media_options_page( $admin_title, $title ) {

    $admin_title = __('Media Settings','vergelabs-media-library') . $admin_title;

    return $admin_title;
}



/**
 *  vergeml_media_options_page
 *
 *  Ensure compatibility with default options-media.php for third-parties
 *
 *  @since    2.3.6
 *  @created  16/12/16
 */

function vergeml_media_options_page() {

    $hook_suffix = 'options-media.php';

    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- fires core's own options-media.php screen hook.
    do_action( $hook_suffix );
}



/**
 *  vergeml_print_media_settings_tabs
 *
 *  @since    2.2.1
 *  @created  11/04/16
 */

function vergeml_print_media_settings_tabs( $active ) { ?>

    <h2 class="nav-tab-wrapper wp-clearfix" id="eml-options-media-tabs">
        <a href="<?php echo esc_url( get_admin_url( null, 'options-general.php?page=media' ) ); ?>" class="nav-tab<?php echo ( 'media' === $active ) ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'vergelabs-media-library' ); ?></a>
        <a href="<?php echo esc_url( get_admin_url( null, 'options-general.php?page=media-library' ) ); ?>" class="nav-tab<?php echo ( 'library' === $active ) ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Media Library', 'vergelabs-media-library' ); ?></a>
        <a href="<?php echo esc_url( get_admin_url( null, 'options-general.php?page=media-taxonomies' ) ); ?>" class="nav-tab<?php echo ( 'taxonomies' === $active ) ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Media Taxonomies', 'vergelabs-media-library' ); ?></a>
        <a href="<?php echo esc_url( get_admin_url( null, 'options-general.php?page=mime-types' ) ); ?>" class="nav-tab<?php echo ( 'mimetypes' === $active ) ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'MIME Types', 'vergelabs-media-library' ); ?></a>
    </h2>

<?php
}



/**
 *  vergeml_print_media_settings
 *
 *  Based on wp-admin/options-media.php
 *
 *  @since    2.2.1
 *  @created  11/04/16
 */

function vergeml_print_media_settings() {

    if ( ! current_user_can( 'manage_options' ) )
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );

    if ( is_multisite() ) {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        if ( ! current_user_can( 'manage_network_options' ) && ! (bool) $vergeml_network_options['media_settings'] )
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }

    settings_errors();


    $title = __( 'Media Settings', 'vergelabs-media-library' );
    ?>

    <div class="wrap">
    <h1><?php echo esc_html( $title ); ?></h1>

    <?php vergeml_print_media_settings_tabs( 'media' ); ?>

    <form action="options.php" method="post">
    <?php settings_fields( 'media' ); ?>

    <h2 class="title"><?php esc_html_e( 'Image sizes', 'vergelabs-media-library' ); ?></h2>
    <p><?php esc_html_e( 'The sizes listed below determine the maximum dimensions in pixels to use when adding an image to the Media Library.', 'vergelabs-media-library' ); ?></p>

    <table class="form-table" role="presentation">
    <tr>
    <th scope="row"><?php esc_html_e( 'Thumbnail size', 'vergelabs-media-library' ); ?></th>
    <td><fieldset><legend class="screen-reader-text"><span>
        <?php
        /* translators: Hidden accessibility text. */
        esc_html_e( 'Thumbnail size', 'vergelabs-media-library' );
        ?>
    </span></legend>
    <label for="thumbnail_size_w"><?php esc_html_e( 'Width', 'vergelabs-media-library' ); ?></label>
    <input name="thumbnail_size_w" type="number" step="1" min="0" id="thumbnail_size_w" value="<?php form_option( 'thumbnail_size_w' ); ?>" class="small-text" />
    <br />
    <label for="thumbnail_size_h"><?php esc_html_e( 'Height', 'vergelabs-media-library' ); ?></label>
    <input name="thumbnail_size_h" type="number" step="1" min="0" id="thumbnail_size_h" value="<?php form_option( 'thumbnail_size_h' ); ?>" class="small-text" />
    </fieldset>
    <input name="thumbnail_crop" type="checkbox" id="thumbnail_crop" value="1" <?php checked( '1', get_option( 'thumbnail_crop' ) ); ?>/>
    <label for="thumbnail_crop"><?php esc_html_e( 'Crop thumbnail to exact dimensions (normally thumbnails are proportional)', 'vergelabs-media-library' ); ?></label>
    </td>
    </tr>

    <tr>
    <th scope="row"><?php esc_html_e( 'Medium size', 'vergelabs-media-library' ); ?></th>
    <td><fieldset><legend class="screen-reader-text"><span>
        <?php
        /* translators: Hidden accessibility text. */
        esc_html_e( 'Medium size', 'vergelabs-media-library' );
        ?>
    </span></legend>
    <label for="medium_size_w"><?php esc_html_e( 'Max Width', 'vergelabs-media-library' ); ?></label>
    <input name="medium_size_w" type="number" step="1" min="0" id="medium_size_w" value="<?php form_option( 'medium_size_w' ); ?>" class="small-text" />
    <br />
    <label for="medium_size_h"><?php esc_html_e( 'Max Height', 'vergelabs-media-library' ); ?></label>
    <input name="medium_size_h" type="number" step="1" min="0" id="medium_size_h" value="<?php form_option( 'medium_size_h' ); ?>" class="small-text" />
    </fieldset></td>
    </tr>

    <tr>
    <th scope="row"><?php esc_html_e( 'Large size', 'vergelabs-media-library' ); ?></th>
    <td><fieldset><legend class="screen-reader-text"><span>
        <?php
        /* translators: Hidden accessibility text. */
        esc_html_e( 'Large size', 'vergelabs-media-library' );
        ?>
    </span></legend>
    <label for="large_size_w"><?php esc_html_e( 'Max Width', 'vergelabs-media-library' ); ?></label>
    <input name="large_size_w" type="number" step="1" min="0" id="large_size_w" value="<?php form_option( 'large_size_w' ); ?>" class="small-text" />
    <br />
    <label for="large_size_h"><?php esc_html_e( 'Max Height', 'vergelabs-media-library' ); ?></label>
    <input name="large_size_h" type="number" step="1" min="0" id="large_size_h" value="<?php form_option( 'large_size_h' ); ?>" class="small-text" />
    </fieldset></td>
    </tr>

    <?php do_settings_fields( 'media', 'default' ); ?>
    </table>

    <?php
    /**
     * @global array $wp_settings
     */
    if ( isset( $GLOBALS['wp_settings']['media']['embeds'] ) ) :
        ?>
    <h2 class="title"><?php esc_html_e( 'Embeds', 'vergelabs-media-library' ); ?></h2>
    <table class="form-table" role="presentation">
        <?php do_settings_fields( 'media', 'embeds' ); ?>
    </table>
    <?php endif; ?>

    <?php if ( ! is_multisite() ) : ?>
    <h2 class="title"><?php esc_html_e( 'Uploading Files', 'vergelabs-media-library' ); ?></h2>
    <table class="form-table" role="presentation">
        <?php
        /*
         * If upload_url_path is not the default (empty),
         * or upload_path is not the default ('wp-content/uploads' or empty),
         * they can be edited, otherwise they're locked.
         */
        if ( get_option( 'upload_url_path' )
            || get_option( 'upload_path' ) && 'wp-content/uploads' !== get_option( 'upload_path' ) ) :
            ?>
    <tr>
    <th scope="row"><label for="upload_path"><?php esc_html_e( 'Store uploads in this folder', 'vergelabs-media-library' ); ?></label></th>
    <td><input name="upload_path" type="text" id="upload_path" value="<?php echo esc_attr( get_option( 'upload_path' ) ); ?>" class="regular-text code" />
    <p class="description">
            <?php
            /* translators: %s: wp-content/uploads */
            printf( esc_html__( 'Default is %s', 'vergelabs-media-library' ), '<code>wp-content/uploads</code>' );
            ?>
    </p>
    </td>
    </tr>

    <tr>
    <th scope="row"><label for="upload_url_path"><?php esc_html_e( 'Full URL path to files', 'vergelabs-media-library' ); ?></label></th>
    <td><input name="upload_url_path" type="text" id="upload_url_path" value="<?php echo esc_attr( get_option( 'upload_url_path' ) ); ?>" class="regular-text code" />
    <p class="description"><?php esc_html_e( 'Configuring this is optional. By default, it should be blank.', 'vergelabs-media-library' ); ?></p>
    </td>
    </tr>
    <tr>
    <td colspan="2" class="td-full">
    <?php else : ?>
    <tr>
    <td class="td-full">
    <?php endif; ?>
    <label for="uploads_use_yearmonth_folders">
    <input name="uploads_use_yearmonth_folders" type="checkbox" id="uploads_use_yearmonth_folders" value="1"<?php checked( '1', get_option( 'uploads_use_yearmonth_folders' ) ); ?> />
        <?php esc_html_e( 'Organize my uploads into month- and year-based folders', 'vergelabs-media-library' ); ?>
    </label>
    </td>
    </tr>

        <?php do_settings_fields( 'media', 'uploads' ); ?>
    </table>
    <?php endif; ?>

    <?php do_settings_sections( 'media' ); ?>

    <?php submit_button(); ?>

    </form>

    </div>

    <?php
}



/**
 *  vergeml_medialibrary_options_page_scripts
 *
 *  @since    2.2.1
 *  @created  11/04/16
 */

function vergeml_medialibrary_options_page_scripts() {

    global $vergeml_dir;

    wp_enqueue_script(
        'vergeml-medialibrary-options-script',
        $vergeml_dir . 'js/eml-medialibrary-options.js',
        array( 'jquery' ),
        VERGEML_VERSION,
        true
    );
}



/**
 *  vergeml_taxonomies_options_page_scripts
 *
 *  @since    2.2
 *  @created  08/03/16
 */

function vergeml_taxonomies_options_page_scripts() {

    global $vergeml_dir;

    wp_enqueue_script(
        'vergeml-taxonomies-options-script'
        // $vergeml_dir . 'js/eml-taxonomies-options.js',
        // array( 'jquery', 'underscore', 'vergeml-admin-script' ),
        // VERGEML_VERSION,
        // true
    );

    $l10n_data = array(
        'edit' => __( 'Edit', 'vergelabs-media-library' ),
        'close' => __( 'Close', 'vergelabs-media-library' ),
        'view' => __( 'View', 'vergelabs-media-library' ),
        'update' => __( 'Update', 'vergelabs-media-library' ),
        'add_new' => __( 'Add New', 'vergelabs-media-library' ),
        'new' => __( 'New', 'vergelabs-media-library' ),
        'name' => __( 'Name', 'vergelabs-media-library' ),
        'parent' => __( 'Parent', 'vergelabs-media-library' ),
        'all' => __( 'All', 'vergelabs-media-library' ),
        'search' => __( 'Search', 'vergelabs-media-library' ),

        'tax_new' => __( 'New Taxonomy', 'vergelabs-media-library' ),

        'tax_deletion_confirm_title' => __( 'Remove Taxonomy', 'vergelabs-media-library' ),
        'tax_deletion_confirm_text_p1' => '<p>' . __( 'Taxonomy will be removed.', 'vergelabs-media-library' ) . '</p>',
        'tax_deletion_confirm_text_p2' => '<p>' . __( 'Taxonomy terms (categories) will remain intact in the database. If you create a taxonomy with the same name in the future, its terms (categories) will be available again.', 'vergelabs-media-library' ) . '</p>',
        'tax_deletion_confirm_text_p3' => '<p>' . __( 'Media items will remain intact.', 'vergelabs-media-library' ) . '</p>',
        'tax_deletion_confirm_text_p4' => '<p>' . __( 'Are you still sure?', 'vergelabs-media-library' ) . '</p>',
        'tax_deletion_yes' => __( 'Yes, remove taxonomy', 'vergelabs-media-library' ),

        'tax_error_duplicate_title' => __( 'Duplicate', 'vergelabs-media-library' ),
        'tax_error_duplicate_text' => __( 'Taxonomy with the same name already exists. Please chose other one.', 'vergelabs-media-library' ),

        'tax_error_empty_fileds_title' => __( 'Empty Fields', 'vergelabs-media-library' ),
        'tax_error_wrong_taxname_title' => __( 'Wrong Taxonomy Name', 'vergelabs-media-library' ),
        'tax_error_wrong_slug_title' => __( 'Wrong Slug', 'vergelabs-media-library' ),

        'tax_error_empty_both' => __( 'Please choose Singular and Plural names for all new taxomonies.', 'vergelabs-media-library' ),
        'tax_error_empty_singular' => __( 'Please choose Singular name for all new taxomonies.', 'vergelabs-media-library' ),
        'tax_error_empty_plural' => __( 'Please choose Plural name for all new taxomonies.', 'vergelabs-media-library' ),

        'tax_error_empty_taxname' => __( 'Taxonomy Name cannot be empty. If it was not generated from the Singular name please enter it manually.', 'vergelabs-media-library' ),
        'tax_error_wrong_taxname' => __( 'Taxonomy Name should only contain lowercase Latin letters, the underscore character ( _ ), and be 3-32 characters long.', 'vergelabs-media-library' ),
        'tax_error_wrong_slug' => __( 'Slug should only contain lowercase Latin letters, numbers, underscore ( _ ) or hyphen ( - ) characters.', 'vergelabs-media-library' ),

        'okay' => __( 'Ok', 'vergelabs-media-library' ),
        'cancel' => __( 'Cancel', 'vergelabs-media-library' ),

        'sync_warning_title' => __( 'Synchronize Now', 'vergelabs-media-library' ),
        'sync_warning_text' => __( 'This operation cannot be canceled! Are you still sure?', 'vergelabs-media-library' ),
        'sync_warning_yes' => __( 'Synchronize', 'vergelabs-media-library' ),
        'sync_warning_no' => __( 'Cancel', 'vergelabs-media-library' ),
        'in_progress_sync_text' => __( 'Synchronizing...', 'vergelabs-media-library' ),

        'bulk_edit_nonce' => wp_create_nonce( 'eml-bulk-edit-nonce' )
    );

    wp_localize_script(
        'vergeml-taxonomies-options-script',
        'vergeml_taxonomies_options_l10n_data',
        $l10n_data
    );
}



/**
 *  vergeml_mimetype_options_page_scripts
 *
 *  @since    2.2
 *  @created  08/03/16
 */

function vergeml_mimetype_options_page_scripts() {

    global $vergeml_dir;

    wp_enqueue_script(
        'vergeml-mimetype-options-script',
        $vergeml_dir . 'js/eml-mimetype-options.js',
        array( 'jquery', 'underscore' ),
        VERGEML_VERSION,
        true
    );

    $l10n_data = array(
        'mime_restoring_confirm_title' => __( 'Restore WordPress default MIME Types', 'vergelabs-media-library' ),
        'mime_restoring_confirm_text' => __( 'Warning! All your custom MIME Types will be deleted by this operation.', 'vergelabs-media-library' ),
        'mime_restoring_yes' => __( 'Restore Defaults', 'vergelabs-media-library' ),
        'in_progress_restoring_text' => __( 'Restoring...', 'vergelabs-media-library' ),

        'okay' => __( 'Ok', 'vergelabs-media-library' ),
        'cancel' => __( 'Cancel', 'vergelabs-media-library' ),

        'mime_error_cannot_save_title' => __( 'MIME Types cannot be saved', 'vergelabs-media-library' ),
        'mime_error_empty_fields' => __( 'Please fill into all fields.', 'vergelabs-media-library' ),
        'mime_error_duplicate' => __( 'Duplicate extensions or MIME types. Please choose other one.', 'vergelabs-media-library' )
    );

    wp_localize_script(
        'vergeml-mimetype-options-script',
        'vergeml_mimetype_options_l10n_data',
        $l10n_data
    );
}



/**
 *  vergeml_options_page_scripts
 *
 *  @since    2.2
 *  @created  08/03/16
 */

function vergeml_options_page_scripts() {

    global $vergeml_dir;


    wp_enqueue_script(
        'vergeml-options-script',
        $vergeml_dir . 'js/eml-options.js',
        array( 'jquery', 'underscore', 'vergeml-admin-script' ),
        VERGEML_VERSION,
        true
    );

    $l10n_data = array(
        'cleanup_warning_title' => __( 'Complete Cleanup', 'vergelabs-media-library' ),
        'cleanup_warning_text_p1' => '<p>' . __( 'You are about to <strong style="text-transform:uppercase">delete all plugin data</strong> from the database including backups.', 'vergelabs-media-library' ) . '</p>',
        'cleanup_warning_text_p2' => '<p>' . __( 'This operation cannot be canceled! Are you still sure?', 'vergelabs-media-library') . '</p>',
        'cleanup_warning_yes' => __( 'Yes, delete all data', 'vergelabs-media-library' ),
        'in_progress_cleanup_text' => __( 'Cleaning...', 'vergelabs-media-library' ),
        'cancel' => __( 'Cancel', 'vergelabs-media-library' ),

        'apply_to_network_nonce' => wp_create_nonce( 'eml-apply-to-network-nonce' ),
        'applying_settings_title' => __( 'Unify Media Settings over Network', 'vergelabs-media-library' ),
        'applying_media_library_settings_text' => sprintf(
            /* translators: %s: the emphasised phrase "will be overwritten" */
            __( 'ALL Media Library Settings on the Network %s with the settings of the main website.', 'vergelabs-media-library' ),
            '<strong style="text-transform:uppercase">' . esc_html__( 'will be overwritten', 'vergelabs-media-library' ) . '</strong>'
        ),
        'applying_media_taxonomies_settings_text' => sprintf(
            /* translators: %s: the emphasised phrase "will be overwritten" */
            __( 'ALL Media Taxonomies Settings on the Network %s with the settings of the main website. If your websites have individual taxonomies registered, they will be overwritten with the taxonomies from the main website.', 'vergelabs-media-library' ),
            '<strong style="text-transform:uppercase">' . esc_html__( 'will be overwritten', 'vergelabs-media-library' ) . '</strong>'
        ),
        'applying_mime_types_settings_text' => sprintf(
            /* translators: %s: the emphasised phrase "will be overwritten" */
            __( 'ALL MIME Types Settings on the Network %s with the settings of the main website.', 'vergelabs-media-library' ),
            '<strong style="text-transform:uppercase">' . esc_html__( 'will be overwritten', 'vergelabs-media-library' ) . '</strong>'
        ),
        'applying_settings_yes' => __( 'Apply', 'vergelabs-media-library' ),
        'in_progress_apply_setings_text' => __( 'Applying Settings...', 'vergelabs-media-library' )
    );

    wp_localize_script(
        'vergeml-options-script',
        'vergeml_options_l10n_data',
        $l10n_data
    );
}



/**
 *  vergeml_print_settings
 *
 *  @since    2.1
 *  @created  25/10/15
 */

function vergeml_print_settings() {

    if ( ! current_user_can( 'manage_options' ) )
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );


    if ( is_multisite() ) {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        if ( ! current_user_can( 'manage_network_options' ) && ! (bool) $vergeml_network_options['utilities'] )
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    } ?>


    <div id="vergeml-global-options-wrap" class="wrap eml-options">

        <h2><?php esc_html_e( 'VergeLabs Media Library Utilities', 'vergelabs-media-library' ); ?></h2>

        <div id="poststuff">

            <div id="post-body" class="metabox-holder columns-2">

                <div id="postbox-container-2" class="postbox-container">

                    <div class="postbox">

                        <h3 class="hndle"><?php esc_html_e( 'Export', 'vergelabs-media-library' ); ?></h3>

                        <div class="inside">

                            <ul>
                                <li><strong><?php esc_html_e( 'Plugin settings to export:', 'vergelabs-media-library' ); ?></strong></li>
                                <li><?php esc_html_e( 'Settings > Media Library', 'vergelabs-media-library' ); ?></li>
                                <li><?php esc_html_e( 'Settings > Media Taxonomies', 'vergelabs-media-library' ); ?></li>
                                <li><?php esc_html_e( 'Settings > MIME Types', 'vergelabs-media-library' ); ?></li>
                            </ul>


                            <p><?php esc_html_e( 'Use generated JSON file to import the configuration into another website.', 'vergelabs-media-library' ); ?></p>

                            <form method="post">
                                <input type='hidden' name='eml-settings-export' />
                                <?php wp_nonce_field( 'eml_settings_export_nonce', 'eml-settings-export-nonce' ); ?>
                                <?php submit_button( __( 'Export Plugin Settings', 'vergelabs-media-library' ), 'primary', 'eml-submit-settings-export', true ); ?>
                            </form>

                        </div>

                    </div>


                    <div class="postbox">

                        <h3 class="hndle"><?php esc_html_e( 'Import', 'vergelabs-media-library' ); ?></h3>

                        <div class="inside">

                            <ul>
                                <li><strong><?php esc_html_e( 'Plugin settings to import:', 'vergelabs-media-library' ); ?></strong></li>
                                <li><?php esc_html_e( 'Settings > Media Library', 'vergelabs-media-library' ); ?></li>
                                <li><?php esc_html_e( 'Settings > Media Taxonomies', 'vergelabs-media-library' ); ?></li>
                                <li><?php esc_html_e( 'Settings > MIME Types', 'vergelabs-media-library' ); ?></li>
                            </ul>

                            <p><?php esc_html_e( 'Plugin settings will be imported from a configuration JSON file which can be obtained by exporting the settings on another website using the export button above.', 'vergelabs-media-library' ); ?></p>
                            <p><?php esc_html_e( 'All plugin settings will be overridden by the import. You will have a chance to restore current data from an automatic backup in case you are not satisfied with the result of the import.', 'vergelabs-media-library' ); ?></p>

                            <form method="post" enctype="multipart/form-data">
                                <p><input type="file" name="import_file"/></p>
                                <input type='hidden' name='eml-settings-import' />
                                <?php wp_nonce_field( 'eml_settings_import_nonce', 'eml-settings-import-nonce' ); ?>
                                <?php submit_button(  __( 'Import Plugin Settings', 'vergelabs-media-library' ), 'primary', 'eml-submit-settings-import' ); ?>
                            </form>

                        </div>

                    </div>


                    <?php $vergeml_backup = get_option( 'vergeml_backup' ); ?>

                    <div class="postbox">

                        <h3 class="hndle"><?php esc_html_e( 'Restore', 'vergelabs-media-library' ); ?></h3>

                        <div class="inside">

                            <?php if ( empty( $vergeml_backup ) ) : ?>

                                <p><?php esc_html_e( 'No backup available at the moment.', 'vergelabs-media-library' ); ?></p>

                                <p><?php esc_html_e( 'Backup will be created automatically before any import operation.', 'vergelabs-media-library' ); ?></p>

                            <?php else : ?>

                                <p><?php esc_html_e( 'The backup has been automatically created before the latest import operation.', 'vergelabs-media-library' ); ?></p>

                                <ul>
                                    <li><strong><?php esc_html_e( 'Plugin settings to restore:', 'vergelabs-media-library' ); ?></strong></li>
                                    <li><?php esc_html_e( 'Settings > Media Library', 'vergelabs-media-library' ); ?></li>
                                    <li><?php esc_html_e( 'Settings > Media Taxonomies', 'vergelabs-media-library' ); ?></li>
                                    <li><?php esc_html_e( 'Settings > MIME Types', 'vergelabs-media-library' ); ?></li>
                                </ul>

                                <form method="post">
                                    <input type='hidden' name='eml-settings-restore' />
                                    <?php wp_nonce_field( 'eml_settings_restore_nonce', 'eml-settings-restore-nonce' ); ?>
                                    <?php submit_button( __( 'Restore Settings from the Backup', 'vergelabs-media-library' ), 'primary', 'eml-submit-settings-restore', true, array( 'id' => 'eml-submit-settings-restore' ) ); ?>
                                </form>

                            <?php endif; ?>


                        </div>

                    </div>


                    <?php if ( ! is_multisite() || is_network_admin() ) : ?>


                        <div class="postbox">

                            <h3 class="hndle"><?php esc_html_e( 'Complete Cleanup', 'vergelabs-media-library' ); ?></h3>

                            <div class="inside">

                                <?php $vergeml_taxonomies = vergeml_get_eml_taxonomies(); ?>

                                <ul>
                                    <li><strong><?php esc_html_e( 'What will be deleted:', 'vergelabs-media-library' ); ?></strong></li>
                                    <?php foreach( (array) $vergeml_taxonomies as $taxonomy => $params ) : ?>
                                        <li><?php esc_html_e( 'All', 'vergelabs-media-library' );
                                        echo ' ' . esc_html( $params['labels']['name'] ); ?></li>
                                    <?php endforeach; ?>
                                    <li><?php esc_html_e( 'All plugin options', 'vergelabs-media-library' ); ?></li>
                                    <li><?php esc_html_e( 'All plugin backups stored in the database', 'vergelabs-media-library' ); ?></li>
                                </ul>

                                <ul>
                                    <li><strong><?php esc_html_e( 'What will remain intact:', 'vergelabs-media-library' ); ?></strong></li>
                                    <li><?php esc_html_e( 'All media items', 'vergelabs-media-library' ); ?></li>
                                    <li><?php esc_html_e( 'All taxonomies not listed above', 'vergelabs-media-library' ); ?></li>
                                </ul>

                                <p><?php esc_html_e( 'The plugin cannot delete itself for security reasons. Please delete it manually from the plugin list after the cleanup is complete.', 'vergelabs-media-library' ); ?></p>

                                <p><strong style="color:red;"><?php esc_html_e( 'If you are not sure about this operation it\'s HIGHLY RECOMMENDED to create a backup of your database prior to cleanup!', 'vergelabs-media-library' ); ?></strong></p>

                                <form id="eml-form-cleanup" method="post">
                                    <input type='hidden' name='eml-settings-cleanup' />
                                    <?php wp_nonce_field( 'eml_settings_cleanup_nonce', 'eml-settings-cleanup-nonce' ); ?>
                                    <?php submit_button( __( 'Delete All Data & Deactivate', 'vergelabs-media-library' ), 'primary', 'eml-submit-settings-cleanup', true ); ?>
                                </form>

                            </div>

                        </div>

                        <?php do_action( 'vergeml_extend_settings_page' ); ?>

                    <?php endif; ?>

                    <?php vergeml_system_report_render(); ?>

                </div>

                <div id="postbox-container-1" class="postbox-container">

                    <?php vergeml_print_credits(); ?>

                </div>

            </div>

        </div>

    </div>

    <?php
}



/**
 *  vergeml_print_network_settings
 *
 *  @since    2.6
 *  @created  22/04/18
 */

function vergeml_print_network_settings() {

    if ( ! current_user_can( 'manage_network_options' ) )
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );


    settings_errors();

    $vergeml_network_options = get_site_option( 'vergeml_network_options', array() ); ?>


    <div id="vergeml-global-options-wrap" class="wrap eml-options">

        <h2><?php esc_html_e( 'VergeLabs Media Library Utilities', 'vergelabs-media-library' ); ?></h2>

        <div id="poststuff">

            <div id="post-body" class="metabox-holder columns-2">

                <div id="postbox-container-2" class="postbox-container">

                    <div class="postbox">

                        <h3 class="hndle" id="eml-license-key-section"><?php esc_html_e('Network Settings','vergelabs-media-library'); ?></h3>


                        <div class="inside">

                            <?php if ( ! is_plugin_active_for_network( vergeml_get_basename() ) ) : ?>

                                <p class="description"><?php esc_html_e( 'No settings available. The plugin is not network activated.', 'vergelabs-media-library' ); ?></p>

                            <?php else : ?>

                                <form method="post">

                                    <?php settings_fields( 'eml-network-settings' ); ?>

                                    <table class="form-table">

                                        <tr>
                                            <th scope="row"><?php esc_html_e('Media Settings per site','vergelabs-media-library'); ?></th>
                                            <td>
                                                <fieldset>
                                                    <legend class="screen-reader-text"><span><?php esc_html_e('Enable Media Settings','vergelabs-media-library'); ?></span></legend>
                                                    <label><input name="vergeml_network_options[media_settings]" type="hidden" value="0" /><input name="vergeml_network_options[media_settings]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_network_options['media_settings'], true ); ?> /> <?php esc_html_e('Allow an individual site admin to edit enhanced Media Settings','vergelabs-media-library'); ?></label>
                                                    <p class="description"><?php esc_html_e( 'Otherwise, only a network (super) admin can see the menu and edit media settings.', 'vergelabs-media-library' ); ?></p>
                                                </fieldset>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><?php esc_html_e('Plugin Utilities per site','vergelabs-media-library'); ?></th>
                                            <td>
                                                <fieldset>
                                                    <legend class="screen-reader-text"><span><?php esc_html_e('Enable plugin Utilities','vergelabs-media-library'); ?></span></legend>
                                                    <label><input name="vergeml_network_options[utilities]" type="hidden" value="0" /><input name="vergeml_network_options[utilities]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_network_options['utilities'], true ); ?> /> <?php esc_html_e('Allow an individual site admin to import / export / restore plugin settings and perform the complete cleanup for a specific site','vergelabs-media-library'); ?></label>
                                                    <p class="description"><?php esc_html_e( 'Otherwise, only a network (super) admin can see the menu and perform those actions.', 'vergelabs-media-library' ); ?></p>
                                                </fieldset>
                                            </td>
                                        </tr>

                                    </table>

                                    <?php submit_button( __( 'Save Changes', 'vergelabs-media-library' ), 'primary', 'eml-submit-network-settings', true ); ?>

                                </form>

                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="postbox">

                        <h3 class="hndle"><?php esc_html_e('Unify Media Settings over Network','vergelabs-media-library'); ?></h3>


                        <div class="inside">

                            <?php if ( ! is_plugin_active_for_network( vergeml_get_basename() ) ) : ?>

                                <p class="description"><?php esc_html_e( 'No settings available. The plugin is not network activated.', 'vergelabs-media-library' ); ?></p>

                            <?php else : ?>

                                <form method="post">

                                    <table class="form-table">

                                        <tr>
                                            <th scope="row"><?php esc_html_e('Media Library Settings','vergelabs-media-library'); ?></th>
                                            <td>
                                                <fieldset>
                                                    <legend class="screen-reader-text"><span><?php esc_html_e('Media Library Settings','vergelabs-media-library'); ?></span></legend>
                                                    <a class="add-new-h2 vergeml-apply-settings-to-network" data-settings="media-library" href="javascript:;"><?php esc_html_e( 'Apply to ALL Network websites', 'vergelabs-media-library' ); ?></a>
                                                    <p class="description"><?php printf(
                                                        /* translators: %s: link to the Media Library settings page */
                                                        esc_html__( 'Main website %s settings will be applied to all websites on the Network.', 'vergelabs-media-library' ),
                                                        '<a href="' . esc_url( admin_url( 'options-general.php?page=media-library' ) ) . '" target="_blank">' . esc_html__( 'Media Library', 'vergelabs-media-library' ) . '</a>'
                                                    ); ?></p>
                                                </fieldset>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><?php esc_html_e('Media Taxonomies Settings','vergelabs-media-library'); ?></th>
                                            <td>
                                                <fieldset>
                                                    <legend class="screen-reader-text"><span><?php esc_html_e('Media Taxonomies Settings','vergelabs-media-library'); ?></span></legend>
                                                    <a class="add-new-h2 vergeml-apply-settings-to-network" data-settings="media-taxonomies" href="javascript:;"><?php esc_html_e( 'Apply to ALL Network websites', 'vergelabs-media-library' ); ?></a>
                                                    <p class="description"><?php printf(
                                                        /* translators: %s: link to the Media Taxonomies settings page */
                                                        esc_html__( 'Main website %s settings will be applied to all websites on the Network.', 'vergelabs-media-library' ),
                                                        '<a href="' . esc_url( admin_url( 'options-general.php?page=media-taxonomies' ) ) . '" target="_blank">' . esc_html__( 'Media Taxonomies', 'vergelabs-media-library' ) . '</a>'
                                                    ); ?></p>
                                                </fieldset>
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row"><?php esc_html_e('MIME Types Settings','vergelabs-media-library'); ?></th>
                                            <td>
                                                <fieldset>
                                                    <legend class="screen-reader-text"><span><?php esc_html_e('MIME Types Settings','vergelabs-media-library'); ?></span></legend>
                                                    <a class="add-new-h2 vergeml-apply-settings-to-network" data-settings="mime-types" href="javascript:;"><?php esc_html_e( 'Apply to ALL Network websites', 'vergelabs-media-library' ); ?></a>
                                                    <p class="description"><?php printf(
                                                        /* translators: %s: link to the MIME Types settings page */
                                                        esc_html__( 'Main website %s settings will be applied to all websites on the Network.', 'vergelabs-media-library' ),
                                                        '<a href="' . esc_url( admin_url( 'options-general.php?page=mime-types' ) ) . '" target="_blank">' . esc_html__( 'MIME Types', 'vergelabs-media-library' ) . '</a>'
                                                    ); ?></p>
                                                </fieldset>
                                            </td>
                                        </tr>

                                    </table>

                                </form>

                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="postbox">

                        <h3 class="hndle"><?php esc_html_e( 'Complete Cleanup', 'vergelabs-media-library' ); ?></h3>

                        <div class="inside">

                            <?php
                            $vergeml_taxonomies = array();

                            foreach( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) :

                                switch_to_blog( $site_id );

                                $vergeml_taxonomies = array_merge( $vergeml_taxonomies, vergeml_get_eml_taxonomies() );

                                restore_current_blog();

                            endforeach; ?>


                            <ul>
                                <li><strong><?php esc_html_e( 'What will be deleted:', 'vergelabs-media-library' ); ?></strong></li>
                                <?php foreach( (array) $vergeml_taxonomies as $taxonomy => $params ) : ?>
                                    <li><?php esc_html_e( 'All', 'vergelabs-media-library' );
                                    echo ' ' . esc_html( $params['labels']['name'] ); ?></li>
                                <?php endforeach; ?>
                                <li><?php esc_html_e( 'All plugin options on every site', 'vergelabs-media-library' ); ?></li>
                                <li><?php esc_html_e( 'Network settings', 'vergelabs-media-library' ); ?></li>
                                <li><?php esc_html_e( 'All plugin backups stored in the database', 'vergelabs-media-library' ); ?></li>
                            </ul>

                            <ul>
                                <li><strong><?php esc_html_e( 'What will remain intact:', 'vergelabs-media-library' ); ?></strong></li>
                                <li><?php esc_html_e( 'All media items', 'vergelabs-media-library' ); ?></li>
                                <li><?php esc_html_e( 'All taxonomies not listed above', 'vergelabs-media-library' ); ?></li>
                            </ul>

                            <p><?php esc_html_e( 'The plugin cannot delete itself for security reasons. Please delete it manually from the plugin list after the cleanup is complete.', 'vergelabs-media-library' ); ?></p>

                            <p><strong style="color:red;"><?php esc_html_e( 'If you are not sure about this operation it\'s HIGHLY RECOMMENDED to create a backup of your database prior to cleanup!', 'vergelabs-media-library' ); ?></strong></p>

                            <form id="eml-form-cleanup" method="post">
                                <input type='hidden' name='eml-settings-cleanup' />
                                <?php wp_nonce_field( 'eml_settings_cleanup_nonce', 'eml-settings-cleanup-nonce' ); ?>
                                <?php submit_button( __( 'Delete All Data & Network Deactivate', 'vergelabs-media-library' ), 'primary', 'eml-submit-settings-cleanup', true ); ?>
                            </form>

                        </div>

                    </div>

                    <?php do_action( 'vergeml_extend_settings_page' ); ?>

                </div>

                <div id="postbox-container-1" class="postbox-container">

                    <?php vergeml_print_credits(); ?>

                </div>

            </div>

        </div>

    </div>

<?php
}



/**
 *  vergeml_apply_settings_to_network
 *
 *  @since    2.7
 *  @created  21/06/18
 */

add_action( 'wp_ajax_vergeml-apply-settings-to-network', 'vergeml_apply_settings_to_network' );

function vergeml_apply_settings_to_network() {

    if ( ! isset( $_REQUEST['settings'] ) )
        wp_send_json_error();

    check_ajax_referer( 'eml-apply-to-network-nonce', 'nonce' );

    /*
     *  This writes options into every site on the network, so it needs the
     *  capability that guards network settings, not just a valid nonce.
     *  Upstream relied on the nonce alone, which only held because the nonce
     *  is printed on a super-admin screen.
     *
     *  Gated on is_multisite() because a single site grants nobody
     *  manage_network_options, not even an administrator. Checking it
     *  unconditionally would reject the very users allowed to be here.
     */

    if ( is_multisite() && ! current_user_can( 'manage_network_options' ) )
        wp_send_json_error();


    $plugins = get_site_option( 'active_sitewide_plugins');

    if ( is_multisite() && isset($plugins[vergeml_get_basename()]) ) {

        switch_to_blog( get_main_site_id() );

        $vergeml_taxonomies = get_option( 'vergeml_taxonomies', array() );
        $vergeml_lib_options = get_option( 'vergeml_lib_options', array() );
        $vergeml_tax_options = get_option( 'vergeml_tax_options', array() );
        $vergeml_mimes = get_option( 'vergeml_mimes', array() );


        foreach( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {

            switch_to_blog( $site_id );

            switch ( $_REQUEST['settings'] ) {
                case 'media-library':
                    update_option( 'vergeml_lib_options', $vergeml_lib_options );
                    break;

                case 'media-taxonomies':
                    update_option( 'vergeml_taxonomies', $vergeml_taxonomies );
                    update_option( 'vergeml_tax_options', $vergeml_tax_options );
                    break;

                case 'mime-types':
                    update_option( 'vergeml_mimes', $vergeml_mimes );
                    break;
            }

            restore_current_blog();
        }
    }

    wp_send_json_success();
}



/**
 *  vergeml_update_network_settings
 *
 *  @since    2.6
 *  @created  28/04/18
 */

add_action( 'network_admin_menu', 'vergeml_update_network_settings' );

function vergeml_update_network_settings() {

    if ( ! isset($_POST['eml-submit-network-settings']) )
        return;

    check_admin_referer( 'eml-network-settings-options' );

    if ( ! current_user_can( 'manage_network_options' ) )
        return;


    $vergeml_network_options = isset( $_POST['vergeml_network_options'] )
        ? map_deep( wp_unslash( (array) $_POST['vergeml_network_options'] ), 'sanitize_text_field' )
        : array();

    $vergeml_network_options = vergeml_tax_options_validate( $vergeml_network_options );

    update_site_option( 'vergeml_network_options', $vergeml_network_options );

    add_settings_error(
        'eml-network-settings',
        'eml_network_settings_saved',
        __('Network settings saved.', 'vergelabs-media-library'),
        'updated'
    );
}



/**
 *  vergeml_settings_export
 *
 *  @since    2.1
 *  @created  25/10/15
 */

add_action( 'admin_init', 'vergeml_settings_export' );

function vergeml_settings_export() {

    if ( ! isset( $_POST['eml-settings-export'] ) )
        return;

    if ( ! isset( $_POST['eml-settings-export-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eml-settings-export-nonce'] ) ), 'eml_settings_export_nonce' ) )
        return;

    if ( ! current_user_can( 'manage_options' ) )
        return;

    if ( is_multisite() ) {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        if ( ! current_user_can( 'manage_network_options' ) && ! (bool) $vergeml_network_options['utilities'] )
            return;
    }


    $settings = vergeml_get_settings();

    ignore_user_abort( true );

    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=vergeml-settings-' . gmdate( 'm-d-Y_hia' ) . '.json' );
    header( "Expires: 0" );

    echo json_encode( $settings );

    exit;
}



/**
 *  vergeml_settings_import
 *
 *  @since    2.1
 *  @created  25/10/15
 */

add_action( 'admin_init', 'vergeml_settings_import' );

function vergeml_settings_import() {

    if ( ! isset( $_POST['eml-settings-import'] ) )
        return;

    if ( ! isset( $_POST['eml-settings-import-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eml-settings-import-nonce'] ) ), 'eml_settings_import_nonce' ) )
        return;

    if ( ! current_user_can( 'manage_options' ) )
        return;

    if ( is_multisite() ) {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        if ( ! current_user_can( 'manage_network_options' ) && ! (bool) $vergeml_network_options['utilities'] )
            return;
    }


    /*
     *  Only tmp_name is read, and only to hand to wp_handle_upload below, which
     *  does its own MIME and error checking. The name is sanitised so nothing
     *  from the upload reaches the filesystem or a message unchecked.
     */

    $import_file = array(
        'name'     => isset( $_FILES['import_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['import_file']['name'] ) ) : '',
        'type'     => isset( $_FILES['import_file']['type'] ) ? sanitize_mime_type( wp_unslash( $_FILES['import_file']['type'] ) ) : '',
        'tmp_name' => isset( $_FILES['import_file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['import_file']['tmp_name'] ) ) : '',
        'error'    => isset( $_FILES['import_file']['error'] ) ? (int) $_FILES['import_file']['error'] : UPLOAD_ERR_NO_FILE,
        'size'     => isset( $_FILES['import_file']['size'] ) ? (int) $_FILES['import_file']['size'] : 0,
    );

    if ( empty( $import_file['tmp_name'] ) ) {

        add_settings_error(
            'eml-settings',
            'eml_settings_file_absent',
            __('Settings cannot be imported. Please upload a file to import settings.', 'vergelabs-media-library'),
            'error'
        );

        return;
    }


    // backup settings
    $settings = vergeml_get_settings();
    update_option( 'vergeml_backup', $settings );


    $json_data = file_get_contents( $import_file['tmp_name'] );
    $settings = json_decode( $json_data, true );

    if ( empty( $settings ) ) {

        add_settings_error(
            'eml-settings',
            'eml_settings_wrong_format',
            __('Settings cannot be imported. Please upload a correct JSON file to import settings.', 'vergelabs-media-library'),
            'error'
        );

        return;
    }


    update_option( 'vergeml_taxonomies', $settings['taxonomies'] );
    update_option( 'vergeml_lib_options', $settings['lib_options'] );
    update_option( 'vergeml_tax_options', $settings['tax_options'] );
    update_option( 'vergeml_mimes', $settings['mimes'] );

    add_settings_error(
        'eml-settings',
        'eml_settings_imported',
        __('Plugin settings imported.', 'vergelabs-media-library'),
        'updated'
    );
}



/**
 *  vergeml_settings_restoring
 *
 *  @since    2.1
 *  @created  25/10/15
 */

add_action( 'admin_init', 'vergeml_settings_restoring' );

function vergeml_settings_restoring() {

    if ( ! isset( $_POST['eml-settings-restore'] ) )
        return;

    if ( ! isset( $_POST['eml-settings-restore-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eml-settings-restore-nonce'] ) ), 'eml_settings_restore_nonce' ) )
        return;

    if ( ! current_user_can( 'manage_options' ) )
        return;

    if ( is_multisite() ) {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        if ( ! current_user_can( 'manage_network_options' ) && ! (bool) $vergeml_network_options['utilities'] )
            return;
    }


    $vergeml_backup = get_option( 'vergeml_backup' );

    update_option( 'vergeml_taxonomies', $vergeml_backup['taxonomies'] );
    update_option( 'vergeml_lib_options', $vergeml_backup['lib_options'] );
    update_option( 'vergeml_tax_options', $vergeml_backup['tax_options'] );
    update_option( 'vergeml_mimes', $vergeml_backup['mimes'] );

    do_action( 'vergeml_pro_set_settings', $vergeml_backup );

    update_option( 'vergeml_backup', '' );

    add_settings_error(
        'eml-settings',
        'eml_settings_restored',
        __('Plugin settings restored from the backup.', 'vergelabs-media-library'),
        'updated'
    );
}



/**
 *  vergeml_settings_cleanup
 *
 *  @since    2.2
 *  @created  23/02/16
 */

add_action( 'admin_init', 'vergeml_settings_cleanup' );

function vergeml_settings_cleanup() {

    if ( ! isset( $_POST['eml-settings-cleanup'] ) )
        return;

    if ( ! isset( $_POST['eml-settings-cleanup-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eml-settings-cleanup-nonce'] ) ), 'eml_settings_cleanup_nonce' ) )
        return;

    if ( ! current_user_can( 'manage_options' ) )
        return;

    if ( is_multisite() ) {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        if ( ! current_user_can( 'manage_network_options' ) && ! (bool) $vergeml_network_options['utilities'] )
            return;
    }


    if ( is_multisite()  ) {

        foreach( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {

            switch_to_blog( $site_id );

            vergeml_term_relationship_cleanup();
            vergeml_options_cleanup();
            deactivate_plugins( vergeml_get_basename() );

            restore_current_blog();
        }
    }
    else {

        vergeml_term_relationship_cleanup();
        vergeml_options_cleanup();
    }

    // we need this one because of = vs LIKE in the DB query
    vergeml_user_meta_cleanup();

    vergeml_site_options_cleanup();
    vergeml_transients_cleanup();
    deactivate_plugins( vergeml_get_basename(), false, is_multisite() );


    wp_safe_redirect( self_admin_url( 'plugins.php' ) );
    exit;
}



/**
 *  vergeml_term_relationship_cleanup
 *
 *  @since    2.6
 *  @created  28/04/18
 */

/*
 *  Deletes this plugin's taxonomies and their attachment relationships in bulk.
 *  There is no core call that removes term relationships for one post type
 *  only, and doing it through wp_remove_object_terms() would be one query per
 *  attachment per term, which on a large library is tens of thousands of
 *  queries. Table names are $wpdb's, every value is placeheld, and the caches
 *  that matter are cleaned by the core hooks fired around the delete.
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter

function vergeml_term_relationship_cleanup() {

    global $wpdb;


    foreach ( get_option( 'vergeml_taxonomies', array() ) as $taxonomy => $params ) {

        $terms = get_terms( array( 'taxonomy' => $taxonomy, 'fields' => 'all', 'get' => 'all' ) );
        $term_pairs = vergeml_get_media_term_pairs( $terms, 'id=>tt_id' );

        if ( (bool) $params['eml_media'] ) {

            foreach ( $term_pairs as $id => $tt_id ) {
                wp_delete_term( $id, $taxonomy );
            }

            $wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => $taxonomy ), array( '%s' ) );
            delete_option( $taxonomy . '_children' );
        }
        elseif ( ! empty( $term_pairs ) ) {

            $deleted_tt_ids = array();
            $rows2remove_format = join( ', ', array_fill( 0, count( $term_pairs ), '%d' ) );

            $results = $wpdb->get_results( $wpdb->prepare(
                "
                    SELECT $wpdb->term_relationships.term_taxonomy_id, $wpdb->term_relationships.object_id
                    FROM $wpdb->term_relationships
                    INNER JOIN $wpdb->posts
                    ON $wpdb->term_relationships.object_id = $wpdb->posts.ID
                    WHERE $wpdb->posts.post_type = 'attachment'
                    AND $wpdb->term_relationships.term_taxonomy_id IN ($rows2remove_format)
                ",
                $term_pairs
            ) );

            foreach ( $results as $result ) {
                $deleted_tt_ids[$result->object_id][] = $result->term_taxonomy_id;
            }

            foreach( $deleted_tt_ids as $attachment_id => $tt_ids ) {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook; other plugins and core caches rely on it firing.
                do_action( 'delete_term_relationships', $attachment_id, $tt_ids );
            }

            $removed = $wpdb->query( $wpdb->prepare(
                "
                    DELETE $wpdb->term_relationships.* FROM $wpdb->term_relationships
                    INNER JOIN $wpdb->posts
                    ON $wpdb->term_relationships.object_id = $wpdb->posts.ID
                    WHERE $wpdb->posts.post_type = 'attachment'
                    AND $wpdb->term_relationships.term_taxonomy_id IN ($rows2remove_format)
                ",
                $term_pairs
            ) );

            if ( false !== $removed ) {

                foreach( $deleted_tt_ids as $attachment_id => $tt_ids ) {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook; other plugins and core caches rely on it firing.
                    do_action( 'deleted_term_relationships', $attachment_id, $tt_ids );
                }
            }
        }
    }
}



/**
 *  vergeml_user_meta_cleanup
 *
 *  @since    2.8.10
 *  @created  2024/04
 */

function vergeml_user_meta_cleanup() {

    global $wpdb;

    $meta_key  = 'vergeml_';
    $id_column = 'umeta_id';
    $table     = _get_meta_table( 'user' );


    /*
     *  $table and $id_column are internal constants from _get_meta_table(), not
     *  user input, so they are interpolated. Every value is placeheld. There is
     *  no cache to prime or invalidate here: this runs once, from the settings
     *  cleanup action, over rows this plugin owns.
     */

    $meta_ids = $wpdb->get_col(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers are internal, value is placeheld below.
        $wpdb->prepare( "SELECT $id_column FROM $table WHERE meta_key LIKE %s", $wpdb->esc_like( $meta_key ) . '%' )
    ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off cleanup of this plugin's own user meta.


    if ( ! count( $meta_ids ) ) {
        return;
    }

    $meta_ids     = array_map( 'absint', $meta_ids );
    $placeholders = implode( ',', array_fill( 0, count( $meta_ids ), '%d' ) );

    $wpdb->query(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers are internal, ids are placeheld.
        $wpdb->prepare( "DELETE FROM $table WHERE $id_column IN ( $placeholders )", $meta_ids )
    ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off cleanup of this plugin's own user meta.
}



/**
 *  vergeml_options_cleanup
 *
 *  @since    2.6
 *  @created  28/04/18
 */

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter

function vergeml_options_cleanup() {

    $options = array(
        'vergeml_taxonomies',
        'vergeml_lib_options',
        'vergeml_tax_options',
        'vergeml_mimes_backup', // in case it remains since previous versions
        'vergeml_mimes',
        'vergeml_backup',
        'vergeml_version',
        'vergeml_notices'
    );

    $options = apply_filters( 'vergeml_pro_add_options', $options );

    foreach ( $options as $option ) {
        delete_option( $option );
    }
}



/**
 *  vergeml_site_options_cleanup
 *
 *  @since    2.6
 *  @created  28/04/18
 */

function vergeml_site_options_cleanup() {

    $options = array(
        'vergeml_version',
        'vergeml_mimes_backup',
        'vergeml_notices'
    );

    if ( is_multisite() ) {
        $options[] = 'vergeml_network_options';
    }

    $options = apply_filters( 'vergeml_pro_add_options', $options );

    foreach ( $options as $option ) {
        delete_site_option( $option );
    }
}



/**
 *  vergeml_transients_cleanup
 *
 *  @since    2.6
 *  @created  28/04/18
 */

function vergeml_transients_cleanup() {

    $transients = array();

    $transients = apply_filters( 'vergeml_pro_add_transients', $transients );

    foreach ( $transients as $transient ) {
        delete_site_transient( $transient );
    }
}



/**
 *  vergeml_get_settings
 *
 *  @since    2.1
 *  @created  25/10/15
 */

function vergeml_get_settings() {

    $vergeml_taxonomies = get_option( 'vergeml_taxonomies' );
    $vergeml_lib_options = get_option( 'vergeml_lib_options' );
    $vergeml_tax_options = get_option( 'vergeml_tax_options' );
    $vergeml_mimes = get_option( 'vergeml_mimes' );

    $settings = array (
        'taxonomies' => $vergeml_taxonomies,
        'lib_options' => $vergeml_lib_options,
        'tax_options' => $vergeml_tax_options,
        'mimes' => $vergeml_mimes,
    );

    return $settings;
}



/**
 *  vergeml_print_media_library_options
 *
 *  @type     callback function
 *  @since    1.0
 *  @created  28/09/13
 */

function vergeml_print_media_library_options() {

    if ( ! current_user_can( 'manage_options' ) )
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );

    if ( is_multisite() ) {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        if ( ! current_user_can( 'manage_network_options' ) && ! (bool) $vergeml_network_options['media_settings'] )
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }


    $vergeml_lib_options = get_option( 'vergeml_lib_options' );
    $title = __( 'Media Settings', 'vergelabs-media-library' ); ?>


    <div id="vergeml-media-library-options-wrap" class="wrap eml-options">

        <h1><?php echo esc_html( $title ); ?></h1>

        <?php vergeml_print_media_settings_tabs( 'library' ); ?>

        <div id="poststuff">

            <div id="post-body" class="metabox-holder">

                <div id="postbox-container-2" class="postbox-container">

                    <form id="vergeml-form-media-library" method="post" action="options.php">

                        <?php settings_fields( 'media-library' ); ?>


                        <h2><?php esc_html_e('Filters','vergelabs-media-library'); ?></h2>

                        <div class="postbox">

                            <div class="inside">

                                <table class="form-table">

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Force filters','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Force filters','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[force_filters]" type="hidden" value="0" /><input name="vergeml_lib_options[force_filters]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_lib_options['force_filters'], true ); ?> /> <?php esc_html_e('Show media filters for ANY Media Popup','vergelabs-media-library'); ?></label>
                                                <p class="description"><?php esc_html_e( 'Try this if filters are not shown for third-party plugins or themes.', 'vergelabs-media-library' ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Filters to show', 'vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Filters to show', 'vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[filters_to_show][]" type="hidden" value="none" /><input name="vergeml_lib_options[filters_to_show][]" type="checkbox" value="types" <?php echo in_array('types', $vergeml_lib_options['filters_to_show']) ? 'checked' : ''; ?> /> <?php esc_html_e('Types','vergelabs-media-library'); ?>
                                                <em>(<?php esc_html_e( 'Can be disabled for Grid Mode only', 'vergelabs-media-library' ); ?>)</em></label><br />
                                                <label><input name="vergeml_lib_options[filters_to_show][]" type="checkbox" value="dates" <?php echo in_array('dates', $vergeml_lib_options['filters_to_show']) ? 'checked' : ''; ?> /> <?php esc_html_e('Dates','vergelabs-media-library'); ?></label><br />
                                                <label><input name="vergeml_lib_options[filters_to_show][]" type="checkbox" value="authors" <?php echo in_array('authors', $vergeml_lib_options['filters_to_show']) ? 'checked' : ''; ?> /> <?php esc_html_e('Authors','vergelabs-media-library'); ?></label><br />
                                                <label><input name="vergeml_lib_options[filters_to_show][]" type="checkbox" value="taxonomies" <?php echo in_array('taxonomies', $vergeml_lib_options['filters_to_show']) ? 'checked' : ''; ?> /> <?php esc_html_e('Media Taxonomies','vergelabs-media-library'); ?></label>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Show count','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Show count','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[show_count]" type="hidden" value="0" /><input name="vergeml_lib_options[show_count]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_lib_options['show_count'], true ); ?> /> <?php esc_html_e('Show item count per category for media filters','vergelabs-media-library'); ?></label>
                                                <p class="description"><?php esc_html_e( 'Counting items per category costs a query per term, so turn this off if your admin feels slow on a large library.', 'vergelabs-media-library' ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Include children','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Include children','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[include_children]" type="hidden" value="0" /><input name="vergeml_lib_options[include_children]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_lib_options['include_children'], true ); ?> /> <?php esc_html_e('Show media items of child media categories as a result of filtering', 'vergelabs-media-library'); ?></label>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Uploaded to this post by default','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Uploaded to this post by default','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[filter_uploaded]" type="hidden" value="0" /><input name="vergeml_lib_options[filter_uploaded]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_lib_options['filter_uploaded'], true ); ?> /> <?php esc_html_e('Show media files initially filtered by Uploaded to this post when applicable', 'vergelabs-media-library'); ?></label>
                                                <p class="description"><?php esc_html_e( 'Enable this to get media files initially filtered by "Uploaded to this post" in a Media Popup while adding or editing them for a post, page, or custom post type.', 'vergelabs-media-library' ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>

                                </table>

                                <?php submit_button( __( 'Save Changes', 'vergelabs-media-library' ), 'primary', 'submit', true, array( 'id' => 'eml-submit-lib-settings-filters' ) ); ?>

                            </div>

                        </div>

                        <h2><?php esc_html_e('Scrolling','vergelabs-media-library'); ?></h2>

                        <div class="postbox">

                            <div class="inside">

                                <table class="form-table">

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Infinite scrolling','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Infinite scrolling','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[infinite_scrolling]" type="hidden" value="0" /><input name="vergeml_lib_options[infinite_scrolling]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_lib_options['infinite_scrolling'], true ); ?> /> <?php esc_html_e('Enable infinite scrolling','vergelabs-media-library'); ?></label>
                                                <p class="description"><?php esc_html_e( 'Works for Media Library and Media Popups.', 'vergelabs-media-library' ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Number per page','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Number per page','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[loads_per_page]" type="number" min="40" step="10" value="<?php echo (int) $vergeml_lib_options['loads_per_page']; ?>" /> <?php esc_html_e('Load this number of media files per page','vergelabs-media-library'); ?></label>
                                                <p class="description"><?php esc_html_e( 'Works for Media Library and Media Popups.', 'vergelabs-media-library' ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>

                                </table>

                                <?php submit_button( __( 'Save Changes', 'vergelabs-media-library' ), 'primary', 'submit', true, array( 'id' => 'eml-submit-lib-settings-scrolling' ) ); ?>

                            </div>

                        </div>


                        <?php
                            /*
                             *  This whole box used to be greyed out and labelled
                             *  "/ Premium Feature", even though search on enter, the
                             *  minimum letter count and auto search all work here. Only
                             *  the "Enable search in" fieldset was genuinely paid-only,
                             *  and its option was stored but never read, so that one
                             *  fieldset is gone and the rest is simply enabled.
                             */
                        ?>

                        <h2><?php esc_html_e( 'Search', 'vergelabs-media-library' ); ?></h2>

                        <div class="postbox">

                            <div class="inside">

                                <table class="form-table">

                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Search in', 'vergelabs-media-library' ); ?></th>
                                        <td>
                                            <fieldset id="vergeml_lib_options_search_in">
                                                <legend class="screen-reader-text"><span><?php esc_html_e( 'Search in', 'vergelabs-media-library' ); ?></span></legend>
                                                <input name="vergeml_lib_options[search_in][]" type="hidden" value="none" />
<?php
                                                $vergeml_search_fields = array(
                                                    'titles'       => __( 'Titles', 'vergelabs-media-library' ),
                                                    'captions'     => __( 'Captions', 'vergelabs-media-library' ),
                                                    'descriptions' => __( 'Descriptions', 'vergelabs-media-library' ),
                                                    'filenames'    => __( 'Filenames', 'vergelabs-media-library' ),
                                                    'authors'      => __( 'Authors', 'vergelabs-media-library' ),
                                                    'taxonomies'   => __( 'Media taxonomies', 'vergelabs-media-library' ),
                                                );

                                                foreach ( $vergeml_search_fields as $vergeml_field => $vergeml_label ) :
?>
                                                <label><input name="vergeml_lib_options[search_in][]" type="checkbox" value="<?php echo esc_attr( $vergeml_field ); ?>" class="search_columns" <?php checked( in_array( $vergeml_field, (array) $vergeml_lib_options['search_in'], true ) ); ?> /> <?php echo esc_html( $vergeml_label ); ?></label><br />
<?php
                                                endforeach;
?>
                                                <p class="description"><?php esc_html_e( 'WordPress searches titles, captions and descriptions. The rest is added by this plugin.', 'vergelabs-media-library' ); ?></p>
                                                <p class="description"><?php esc_html_e( 'Searching taxonomies finds an item by the name of a category or tag it is filed under. Every word you type has to match something, though not all in the same field.', 'vergelabs-media-library' ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Search on enter','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Search on enter','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[search_on_enter]" type="hidden" value="0" /><input id="vergeml_lib_options_search_on_enter" name="vergeml_lib_options[search_on_enter]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_lib_options['search_on_enter'], true ); ?> /> <?php esc_html_e('Enable search on hitting Enter key','vergelabs-media-library'); ?></label>
                                                <p class="description"><?php esc_html_e( 'Use in combination with the higher minimum number of letters or disable auto search at all.', 'vergelabs-media-library' ); ?></p>
                                                <p class="description"><?php esc_html_e( 'Works for Media Library Grid Mode and Media Popups.', 'vergelabs-media-library' ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Auto search','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Auto search','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[search_auto]" type="hidden" value="0" /><input id="vergeml_lib_options_search_auto" name="vergeml_lib_options[search_auto]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_lib_options['search_auto'], true ); ?> /> <?php esc_html_e('Enable auto search while typing search request','vergelabs-media-library'); ?></label>
                                                <p class="description"><?php esc_html_e( 'Default WordPress behavior for Media Library Grid Mode and Media Popups.', 'vergelabs-media-library' ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr id="vergeml_lib_options_search_min_letters">
                                        <th scope="row"><?php esc_html_e('Minimun number of letters','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Minimun number of letters','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[search_min_letters]" type="number" min="2" step="1" value="<?php echo (int) $vergeml_lib_options['search_min_letters']; ?>" /> <?php esc_html_e('Set the minimum number of letters required to start the auto search','vergelabs-media-library'); ?></label>
                                                <p class="description"><?php esc_html_e('Set higher number to prevent multiple search requests to the database.','vergelabs-media-library'); ?></p>
                                                <p class="description"><?php esc_html_e( 'Using a higher number can improve auto search query performance.', 'vergelabs-media-library' ); ?></p>
                                                <p class="description"><?php esc_html_e( 'Works for Media Library Grid Mode and Media Popups.', 'vergelabs-media-library' ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>

                                </table>

                                <?php submit_button( __( 'Save Changes', 'vergelabs-media-library' ), 'primary', 'submit', true, array( 'id' => 'eml-submit-lib-settings-search' ) ); ?>

                            </div>

                        </div>




                        <h2><?php esc_html_e('Order','vergelabs-media-library'); ?></h2>

                        <div class="postbox">

                            <div class="inside">

                                <table class="form-table">

                                    <tr>
                                        <th scope="row"><label for="vergeml_lib_options[media_orderby]"><?php esc_html_e('Order media items by','vergelabs-media-library'); ?></label></th>
                                        <td>
                                            <select name="vergeml_lib_options[media_orderby]" id="vergeml_lib_options_media_orderby">
                                                <option value="date" <?php selected( $vergeml_lib_options['media_orderby'], 'date' ); ?>><?php esc_html_e('Date','vergelabs-media-library'); ?></option>
                                                <option value="title" <?php selected( $vergeml_lib_options['media_orderby'], 'title' ); ?>><?php esc_html_e('Title','vergelabs-media-library'); ?></option>
                                                <option value="menuOrder" <?php selected( $vergeml_lib_options['media_orderby'], 'menuOrder' ); ?>><?php esc_html_e('Custom Order','vergelabs-media-library'); ?></option>
                                            </select>
                                            <?php esc_html_e('For media library and media popups','vergelabs-media-library'); ?>
                                            <p class="description"><?php esc_html_e( 'Allows changing media items order by drag and drop with Custom Order value.', 'vergelabs-media-library' ); ?></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><label for="vergeml_lib_options[media_order]"><?php esc_html_e('Sort order','vergelabs-media-library'); ?></label></th>
                                        <td>
                                            <select name="vergeml_lib_options[media_order]" id="vergeml_lib_options_media_order">
                                                <option value="ASC" <?php selected( $vergeml_lib_options['media_order'], 'ASC' ); ?>><?php esc_html_e('Ascending','vergelabs-media-library'); ?></option>
                                                <option value="DESC" <?php selected( $vergeml_lib_options['media_order'], 'DESC' ); ?>><?php esc_html_e('Descending','vergelabs-media-library'); ?></option>
                                            </select>
                                            <?php esc_html_e('For media library and media popups','vergelabs-media-library'); ?>
                                        </td>
                                    </tr>

                                    <tr id="vergeml_lib_options_natural_sort">
                                        <th scope="row"><?php esc_html_e('Natural sort order','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Natural sort order','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[natural_sort]" type="hidden" value="0" /><input name="vergeml_lib_options[natural_sort]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_lib_options['natural_sort'], true ); ?> /> <?php esc_html_e('Apply human-friendly sort order to Media Library and Galleries','vergelabs-media-library'); ?></label>
                                                <p class="description"><?php esc_html_e( 'Example: [1, 2, 3, 10, 18, 22, abc-2, abc-11] instead of [1, 10, 18, 2, 22, 3, abc-11, abc-2]', 'vergelabs-media-library' );  ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>
                                </table>

                                <?php submit_button( __( 'Save Changes', 'vergelabs-media-library' ), 'primary', 'submit', true, array( 'id' => 'eml-submit-lib-settings-order' ) ); ?>

                            </div>

                        </div>


                        <h2><?php esc_html_e('Grid Mode','vergelabs-media-library'); ?></h2>

                        <div class="postbox">

                            <div class="inside">

                                <table class="form-table">

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Right sidebar width','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Right sidebar width','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[grid_sidebar_width]" type="number" min="200" step="10" value="<?php echo (int) $vergeml_lib_options['grid_sidebar_width']; ?>" /> <?php esc_html_e('Applies when the screen width is more than 900px','vergelabs-media-library'); ?></label>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Ideal column width','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Ideal column width','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[ideal_column_width]" type="number" min="50" step="10" value="<?php echo (int) $vergeml_lib_options['ideal_column_width']; ?>" /> <?php esc_html_e('Set preferable size for thumbnails in the media library and media popups','vergelabs-media-library'); ?></label>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Show caption','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Show caption','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[grid_show_caption]" type="hidden" value="0" /><input id="vergeml_lib_options_grid_show_caption" name="vergeml_lib_options[grid_show_caption]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_lib_options['grid_show_caption'], true ); ?> /> <?php esc_html_e('Add text caption for media item thumbnails', 'vergelabs-media-library'); ?></label>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr id="vergeml_lib_options_grid_caption_type">
                                        <th scope="row"><label for="vergeml_lib_options[grid_caption_type]"><?php esc_html_e('Caption type','vergelabs-media-library'); ?></label></th>
                                        <td>
                                            <select name="vergeml_lib_options[grid_caption_type]">
                                                <option value="title" <?php selected( $vergeml_lib_options['grid_caption_type'], 'title' ); ?>><?php esc_html_e('Title','vergelabs-media-library'); ?></option>
                                                <option value="filename" <?php selected( $vergeml_lib_options['grid_caption_type'], 'filename' ); ?>><?php esc_html_e('Filename','vergelabs-media-library'); ?></option>
                                                <option value="caption" <?php selected( $vergeml_lib_options['grid_caption_type'], 'caption' ); ?>><?php esc_html_e('Caption','vergelabs-media-library'); ?></option>
                                            </select>
                                        </td>
                                    </tr>

                                </table>

                                <?php submit_button( __( 'Save Changes', 'vergelabs-media-library' ), 'primary', 'submit', true, array( 'id' => 'eml-submit-lib-settings-grid-mode' ) ); ?>

                            </div>

                        </div>


                        <h2><?php esc_html_e('Media Shortcodes','vergelabs-media-library'); ?></h2>

                        <div class="postbox">

                            <div class="inside">

                                <table class="form-table">

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Enhanced media shortcodes','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Enhanced media shortcodes','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_lib_options[enhance_media_shortcodes]" type="hidden" value="0" /><input name="vergeml_lib_options[enhance_media_shortcodes]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_lib_options['enhance_media_shortcodes'], true ); ?> /> <?php esc_html_e('Enhance WordPress media shortcodes to make them understand media taxonomies, upload date, and media items number limit','vergelabs-media-library'); ?></label>
                                                <p class="description"><?php esc_html_e( 'Gallery example:', 'vergelabs-media-library' );  ?> [gallery media_category="5" limit="10" monthnum="12" year="2015"]</p>
                                                <p class="description"><?php esc_html_e( 'Audio playlist example:', 'vergelabs-media-library' ); ?> [playlist media_category="5" limit="10" monthnum="12" year="2015"]</p>
                                                <p class="description"><?php esc_html_e( 'Video playlist example:', 'vergelabs-media-library' ); ?> [playlist type="video" media_category="5" limit="10" monthnum="12" year="2015"]</p>
                                                <p class="description"><?php
                                                printf(
                                                    '<strong style="color:red">%s!</strong> ',
                                                    esc_html__( 'Warning', 'vergelabs-media-library' )
                                                );
                                                esc_html_e( 'Other gallery plugins and some themes replace the default gallery, and enabling this can conflict with them.', 'vergelabs-media-library' );
                                                echo ' ';
                                                printf(
                                                    /* translators: %s: link to the plugin's issue tracker */
                                                    esc_html__( 'Check your galleries on the front end and in the editor once this is on, and report anything broken at %s.', 'vergelabs-media-library' ),
                                                    '<a href="' . esc_url( 'https://github.com/vergelabsnathan/vergelabs-media-library/issues' ) . '">GitHub</a>'
                                                ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>
                                </table>

                                <?php submit_button( __( 'Save Changes', 'vergelabs-media-library' ), 'primary', 'submit', true, array( 'id' => 'eml-submit-lib-settings-media-shortcode' ) ); ?>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

    <?php
}



/**
 *  vergeml_print_taxonomies_options
 *
 *  @type     callback function
 *  @since    1.0
 *  @created  28/09/13
 */

function vergeml_print_taxonomies_options() {

    if ( ! current_user_can( 'manage_options' ) )
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );

    if ( is_multisite() ) {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        if ( ! current_user_can( 'manage_network_options' ) && ! (bool) $vergeml_network_options['media_settings'] )
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }


    $vergeml_taxonomies = get_option( 'vergeml_taxonomies', array() );
    $title = __( 'Media Settings', 'vergelabs-media-library' ); ?>


    <div id="vergeml-global-options-wrap" class="wrap eml-options">

        <h1><?php echo esc_html( $title ); ?></h1>

        <?php vergeml_print_media_settings_tabs( 'taxonomies' ); ?>

        <div id="poststuff">

            <div id="post-body" class="metabox-holder">

                <div id="postbox-container-2" class="postbox-container">

                    <form id="vergeml-form-taxonomies" method="post" action="options.php">

                        <?php settings_fields( 'media-taxonomies' ); ?>

                        <div class="postbox">

                            <h3 class="hndle"><?php esc_html_e('Media Taxonomies','vergelabs-media-library'); ?></h3>

                            <div class="inside">

                                <p><?php esc_html_e('Assign following taxonomies to Media Library:','vergelabs-media-library'); ?></p>

                                <?php $html = '';

                                foreach ( get_taxonomies(array(),'object') as $taxonomy ) {

                                    if ( (in_array('attachment',$taxonomy->object_type) && count($taxonomy->object_type) == 1) || empty($taxonomy->object_type) ) {

                                        $assigned = (bool) $vergeml_taxonomies[$taxonomy->name]['assigned'];
                                        $eml_media = (bool) $vergeml_taxonomies[$taxonomy->name]['eml_media'];

                                        if ( $eml_media )
                                            $li_class = 'vergeml-taxonomy';
                                        else
                                            $li_class = 'wpuxss-non-eml-taxonomy';

                                        $html .= '<li class="' . $li_class . '" id="' . esc_attr($taxonomy->name) . '">';

                                        $html .= '<input name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][eml_media]" type="hidden" value="' . $eml_media . '" />';
                                        $html .= '<label><input class="vergeml-assigned" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][assigned]" type="checkbox" value="1" ' . checked( true, $assigned, false ) . ' title="' . __('Assign Taxonomy','vergelabs-media-library') . '" />' . esc_html($taxonomy->label) . '</label>';
                                        $html .= '<a class="vergeml-button-edit" title="' . __('Edit Taxonomy','vergelabs-media-library') . '" href="javascript:;">' . __('Edit','vergelabs-media-library') . ' &darr;</a>';

                                        if ( $eml_media ) {

                                            $html .= '<a class="vergeml-button-remove" title="' . __('Delete Taxonomy','vergelabs-media-library') . '" href="javascript:;">&ndash;</a>';

                                            $html .= '<div class="vergeml-taxonomy-edit" style="display:none;">';

                                            $html .= '<div class="vergeml-labels-edit">';
                                            $html .= '<h4>' . __('Labels','vergelabs-media-library') . '</h4>';
                                            $html .= '<ul>';
                                            $html .= '<li><label>' . __('Singular','vergelabs-media-library') . '</label><input type="text" class="vergeml-singular_name" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][labels][singular_name]" value="' . esc_html($taxonomy->labels->singular_name) . '" /></li>';
                                            $html .= '<li><label>' . __('Plural','vergelabs-media-library') . '</label><input type="text" class="vergeml-name" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][labels][name]" value="' . esc_html($taxonomy->labels->name) . '" /></li>';
                                            $html .= '<li><label>' . __('Menu Name','vergelabs-media-library') . '</label><input type="text" class="vergeml-menu_name" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][labels][menu_name]" value="' . esc_html($taxonomy->labels->menu_name) . '" /></li>';
                                            $html .= '<li><label>' . __('All','vergelabs-media-library') . '</label><input type="text" class="vergeml-all_items" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][labels][all_items]" value="' . esc_html($taxonomy->labels->all_items) . '" /></li>';
                                            $html .= '<li><label>' . __('Edit','vergelabs-media-library') . '</label><input type="text" class="vergeml-edit_item" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][labels][edit_item]" value="' . esc_html($taxonomy->labels->edit_item) . '" /></li>';
                                            $html .= '<li><label>' . __('View','vergelabs-media-library') . '</label><input type="text" class="vergeml-view_item" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][labels][view_item]" value="' . esc_html($taxonomy->labels->view_item) . '" /></li>';
                                            $html .= '<li><label>' . __('Update','vergelabs-media-library') . '</label><input type="text" class="vergeml-update_item" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][labels][update_item]" value="' . esc_html($taxonomy->labels->update_item) . '" /></li>';
                                            $html .= '<li><label>' . __('Add New','vergelabs-media-library') . '</label><input type="text" class="vergeml-add_new_item" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][labels][add_new_item]" value="' . esc_html($taxonomy->labels->add_new_item) . '" /></li>';
                                            $html .= '<li><label>' . __('New','vergelabs-media-library') . '</label><input type="text" class="vergeml-new_item_name" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][labels][new_item_name]" value="' . esc_html($taxonomy->labels->new_item_name) . '" /></li>';
                                            $html .= '<li><label>' . __('Parent','vergelabs-media-library') . '</label><input type="text" class="vergeml-parent_item" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][labels][parent_item]" value="' . esc_html($taxonomy->labels->parent_item) . '" /></li>';
                                            $html .= '<li><label>' . __('Search','vergelabs-media-library') . '</label><input type="text" class="vergeml-search_items" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][labels][search_items]" value="' . esc_html($taxonomy->labels->search_items) . '" /></li>';
                                            $html .= '</ul>';
                                            $html .= '</div>';

                                            $html .= '<div class="vergeml-settings-edit">';
                                            $html .= '<h4>' . __('Settings','vergelabs-media-library') . '</h4>';
                                            $html .= '<ul>';
                                            $html .= '<li><label>' . __('Taxonomy Name','vergelabs-media-library') . '</label><input type="text" class="vergeml-taxonomy-name" name="" value="' . esc_attr($taxonomy->name) . '" disabled="disabled" /></li>';
                                            $html .= '<li><label>' . __('Hierarchical','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-hierarchical" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][hierarchical]" value="1" ' . checked( true, (bool) $taxonomy->hierarchical, false ) . ' /></li>';
                                            $html .= '<li><label>' . __('Column for List View','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-show_admin_column" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][show_admin_column]" value="1" ' . checked( true, (bool) $taxonomy->show_admin_column, false ) . ' /></li>';
                                            $html .= '<li><label>' . __('Filter for List View','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-admin_filter" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][admin_filter]" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['admin_filter'], false ) . ' /></li>';
                                            $html .= '<li><label>' . __('Filter for Grid View / Media Popup','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-media_uploader_filter" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][media_uploader_filter]" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['media_uploader_filter'], false ) . ' /></li>';
                                            $html .= '<li><label>' . __('Edit in Media Popup','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-media_popup_taxonomy_edit" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][media_popup_taxonomy_edit]" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['media_popup_taxonomy_edit'], false ) . ' /></li>';
                                            $html .= '<li><label>' . __('Remember Terms Order (sort)','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-sort" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][sort]" value="1" ' . checked( true, (bool) $taxonomy->sort, false ) . ' /></li>';
                                            $html .= '<li><label>' . __('Show in REST','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-show_in_rest" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][show_in_rest]" value="1" ' . checked( true, (bool) $taxonomy->show_in_rest, false ) . ' /></li>';
                                            $html .= '<li><label>' . __('Rewrite Slug','vergelabs-media-library') . '</label><input type="text" class="vergeml-slug" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][rewrite][slug]" value="' . esc_attr($vergeml_taxonomies[$taxonomy->name]['rewrite']['slug']) . '" /></li>';
                                            $html .= '<li><label>' . __('Slug with Front','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-with_front" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][rewrite][with_front]" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['rewrite']['with_front'], false ) . ' /></li>';
                                            $html .= '</ul>';
                                            $html .= '</div>';

                                            $html .= '</div>';
                                        }
                                        else {

                                            $html .= '<div class="vergeml-taxonomy-edit" style="display:none;">';

                                            $html .= '<div class="vergeml-settings-edit">';
                                            $html .= '<h4>' . __('Settings','vergelabs-media-library') . '</h4>';
                                            $html .= '<ul>';
                                            $html .= '<li><label>' . __('Filter for List View','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-admin_filter" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][admin_filter]" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['admin_filter'], false ) . ' /></li>';
                                            $html .= '<li><label>' . __('Filter for Grid View / Media Popup','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-media_uploader_filter" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][media_uploader_filter]" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['media_uploader_filter'], false ) . ' /></li>';
                                            $html .= '<li><label>' . __('Edit in Media Popup','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-media_popup_taxonomy_edit" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][media_popup_taxonomy_edit]" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['media_popup_taxonomy_edit'], false ) . ' /></li>';
                                            $html .= '</ul>';
                                            $html .= '</div>';
                                            $html .= '</div>';
                                        }
                                        $html .= '</li>';
                                    }
                                }

                                $html .= '<li class="vergeml-clone" style="display:none">';
                                $html .= '<input name="" type="hidden" class="vergeml-eml_media" value="1" />';
                                $html .= '<input name="" type="hidden" class="vergeml-create_taxonomy" value="1" />';
                                $html .= '<label class="vergeml-taxonomy-label"><input class="vergeml-assigned" name="" type="checkbox" class="vergeml-assigned" value="1" checked="checked" title="' . __('Assign Taxonomy','vergelabs-media-library') . '" />' . '<span>' . __('New Taxonomy','vergelabs-media-library') . '</span></label>';

                                $html .= '<a class="vergeml-button-remove" title="' . __('Delete Taxonomy','vergelabs-media-library') . '" href="javascript:;">&ndash;</a>';

                                $html .= '<div class="vergeml-taxonomy-edit">';

                                $html .= '<div class="vergeml-labels-edit">';
                                $html .= '<h4>' . __('Labels','vergelabs-media-library') . '</h4>';
                                $html .= '<ul>';
                                $html .= '<li><label>' . __('Singular','vergelabs-media-library') . '</label><input type="text" class="vergeml-singular_name" name="" value="" /></li>';
                                $html .= '<li><label>' . __('Plural','vergelabs-media-library') . '</label><input type="text" class="vergeml-name" name="" value="" /></li>';
                                $html .= '<li><label>' . __('Menu Name','vergelabs-media-library') . '</label><input type="text" class="vergeml-menu_name" name="" value="" /></li>';
                                $html .= '<li><label>' . __('All','vergelabs-media-library') . '</label><input type="text" class="vergeml-all_items" name="" value="" /></li>';
                                $html .= '<li><label>' . __('Edit','vergelabs-media-library') . '</label><input type="text" class="vergeml-edit_item" name="" value="" /></li>';
                                $html .= '<li><label>' . __('View','vergelabs-media-library') . '</label><input type="text" class="vergeml-view_item" name="" value="" /></li>';
                                $html .= '<li><label>' . __('Update','vergelabs-media-library') . '</label><input type="text" class="vergeml-update_item" name="" value="" /></li>';
                                $html .= '<li><label>' . __('Add New','vergelabs-media-library') . '</label><input type="text" class="vergeml-add_new_item" name="" value="" /></li>';
                                $html .= '<li><label>' . __('New','vergelabs-media-library') . '</label><input type="text" class="vergeml-new_item_name" name="" value="" /></li>';
                                $html .= '<li><label>' . __('Parent','vergelabs-media-library') . '</label><input type="text" class="vergeml-parent_item" name="" value="" /></li>';
                                $html .= '<li><label>' . __('Search','vergelabs-media-library') . '</label><input type="text" class="vergeml-search_items" name="" value="" /></li>';
                                $html .= '</ul>';
                                $html .= '</div>';

                                $html .= '<div class="vergeml-settings-edit">';
                                $html .= '<h4>' . __('Settings','vergelabs-media-library') . '</h4>';
                                $html .= '<ul>';
                                $html .= '<li><label>' . __('Taxonomy Name','vergelabs-media-library') . '</label><input type="text" class="vergeml-taxonomy-name" name="" value="" /></li>';
                                $html .= '<li><label>' . __('Hierarchical','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-hierarchical" name="" value="1" checked="checked" /></li>';
                                $html .= '<li><label>' . __('Column for List View','vergelabs-media-library') . '</label><input class="vergeml-show_admin_column" type="checkbox" name="" value="1" /></li>';
                                $html .= '<li><label>' . __('Filter for List View','vergelabs-media-library') . '</label><input class="vergeml-admin_filter" type="checkbox"  name="" value="1" /></li>';
                                $html .= '<li><label>' . __('Filter for Grid View / Media Popup','vergelabs-media-library') . '</label><input class="vergeml-media_uploader_filter" type="checkbox" name="" value="1" /></li>';
                                $html .= '<li><label>' . __('Edit in Media Popup','vergelabs-media-library') . '</label><input class="vergeml-media_popup_taxonomy_edit" type="checkbox" name="" value="1" /></li>';
                                $html .= '<li><label>' . __('Remember Terms Order (sort)','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-sort" name="" value="1" /></li>';
                                $html .= '<li><label>' . __('Show in REST','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-show_in_rest" name="" value="1" /></li>';
                                $html .= '<li><label>' . __('Rewrite Slug','vergelabs-media-library') . '</label><input type="text" class="vergeml-slug" name="" value="" /></li>';
                                $html .= '<li><label>' . __('Slug with Front','vergelabs-media-library') . '</label><input type="checkbox" class="vergeml-with_front" name="" value="1" checked="checked" /></li>';
                                $html .= '</ul>';
                                $html .= '</div>';

                                $html .= '</div>';
                                $html .= '</li>'; ?>

                                <?php if ( ! empty( $html ) ) : ?>

                                    <ul class="vergeml-settings-list vergeml-media-taxonomy-list">
                                        <?php
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled above from esc_attr() and esc_html() parts plus literal form markup, which wp_kses would strip.
                                        echo $html;
                                        ?>
                                    </ul>
                                    <div class="vergeml-button-container-right"><a class="add-new-h2 vergeml-button-create-taxonomy" href="javascript:;">+ <?php esc_html_e( 'Add New Taxonomy', 'vergelabs-media-library' ); ?></a></div>
                                <?php endif; ?>

                                <?php submit_button( __( 'Save Changes', 'vergelabs-media-library' ), 'primary', 'submit', true, array( 'id' => 'eml-submit-tax-settings-media' ) ); ?>
                            </div>

                        </div>

                        <div class="postbox">

                            <h3 class="hndle"><?php esc_html_e('Non-Media Taxonomies','vergelabs-media-library'); ?></h3>

                            <div class="inside">

                                <p><?php esc_html_e('Assign following taxonomies to Media Library:','vergelabs-media-library'); ?></p>

                                <?php $unuse = array('revision','nav_menu_item','attachment');

                                foreach ( get_post_types(array(),'object') as $post_type ) {

                                    if ( ! in_array( $post_type->name, $unuse ) ) {

                                        $taxonomies = get_object_taxonomies($post_type->name,'object');
                                        if ( ! empty( $taxonomies ) ) {

                                            $html = '';

                                            foreach ( $taxonomies as $taxonomy ) {

                                                if ( $taxonomy->name == 'post_format' || 
                                                     $taxonomy->name == 'wp_theme'||
                                                     $taxonomy->name == 'wp_pattern_category'||
                                                     $taxonomy->name == 'wp_template_part_area' ) {
                                                    continue;
                                                }


                                                $html .= '<li class="wpuxss-non-eml-taxonomy" id="' . esc_attr($taxonomy->name) . '">';
                                                $html .= '<input name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][eml_media]" type="hidden" value="' . esc_attr($vergeml_taxonomies[$taxonomy->name]['eml_media']) . '" />';
                                                $html .= '<label><input class="vergeml-assigned" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][assigned]" type="checkbox" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['assigned'], false ) . ' title="' . __('Assign Taxonomy','vergelabs-media-library') . '" />' . esc_html($taxonomy->label) . '</label>';
                                                $html .= '<a class="vergeml-button-edit" title="' . __('Edit Taxonomy','vergelabs-media-library') . '" href="javascript:;">' . __('Edit','vergelabs-media-library') . ' &darr;</a>';
                                                $html .= '<div class="vergeml-taxonomy-edit" style="display:none;">';

                                                $html .= '<h4>' . __('Settings','vergelabs-media-library') . '</h4>';
                                                $html .= '<ul>';
                                                $html .= '<li><input type="checkbox" class="vergeml-admin_filter" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][admin_filter]" id="vergeml_taxonomies-' . esc_attr($taxonomy->name) . '-admin_filter" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['admin_filter'], false ) . ' /><label for="vergeml_taxonomies-' . esc_attr($taxonomy->name) . '-admin_filter">' . __('Filter for List View','vergelabs-media-library') . '</label></li>';
                                                $html .= '<li><input type="checkbox" class="vergeml-media_uploader_filter" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][media_uploader_filter]" id="vergeml_taxonomies-' . esc_attr($taxonomy->name) . '-media_uploader_filter" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['media_uploader_filter'], false ) . ' /><label for="vergeml_taxonomies-' . esc_attr($taxonomy->name) . '-media_uploader_filter">' . __('Filter for Grid View / Media Popup','vergelabs-media-library') . '</label></li>';
                                                $html .= '<li><input type="checkbox" class="vergeml-media_popup_taxonomy_edit" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][media_popup_taxonomy_edit]" id="vergeml_taxonomies-' . esc_attr($taxonomy->name) . '-media_popup_taxonomy_edit" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['media_popup_taxonomy_edit'], false ) . ' /><label for="vergeml_taxonomies-' . esc_attr($taxonomy->name) . '-media_popup_taxonomy_edit">' . __('Edit in Media Popup','vergelabs-media-library') . '</label></li>';

                                                /*
                                                 *  Auto-assign. This used to be greyed out and labelled
                                                 *  "/ Premium Feature" with nothing behind it; it now
                                                 *  works, see core/auto-assign.php. Only offered for a
                                                 *  taxonomy the parent post type actually has, because a
                                                 *  post cannot pass on terms of a taxonomy it lacks.
                                                 */

                                                $vergeml_auto_assign_types = array();

                                                foreach ( (array) $taxonomy->object_type as $vergeml_object_type ) {
                                                    if ( 'attachment' === $vergeml_object_type )
                                                        continue;
                                                    $vergeml_pt = get_post_type_object( $vergeml_object_type );
                                                    if ( $vergeml_pt )
                                                        $vergeml_auto_assign_types[] = strtolower( $vergeml_pt->labels->singular_name );
                                                }

                                                if ( ! empty( $vergeml_auto_assign_types ) ) {

                                                    $html .= '<li><input type="checkbox" class="vergeml-taxonomy_auto_assign" name="vergeml_taxonomies[' . esc_attr($taxonomy->name) . '][taxonomy_auto_assign]" id="vergeml_taxonomies-' . esc_attr($taxonomy->name) . '-taxonomy_auto_assign" value="1" ' . checked( true, (bool) $vergeml_taxonomies[$taxonomy->name]['taxonomy_auto_assign'], false ) . ' />';
                                                    $html .= '<label for="vergeml_taxonomies-' . esc_attr($taxonomy->name) . '-taxonomy_auto_assign">' . sprintf(
                                                        /* translators: 1: taxonomy name, for example "Categories", 2: post type name, for example "post" */
                                                        esc_html__( 'On upload, give media the %1$s of the %2$s it was uploaded to', 'vergelabs-media-library' ),
                                                        esc_html( $taxonomy->label ),
                                                        esc_html( implode( ' / ', $vergeml_auto_assign_types ) )
                                                    ) . '</label></li>';
                                                }

                                                $html .= '</ul>';

                                                $html .= '</div>';
                                                $html .= '</li>';
                                            } ?>

                                            <?php if ( ! empty( $html ) ) : ?>

                                                <h4><?php echo esc_html($post_type->label); ?></h4>
                                                <ul class="vergeml-settings-list vergeml-non-media-taxonomy-list">
                                                    <?php
                                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled above from esc_attr() and esc_html() parts plus literal form markup, which wp_kses would strip.
                                                    echo $html;
                                                    ?>
                                                </ul>

                                            <?php endif;
                                        }
                                    }
                                }

                                submit_button( __( 'Save Changes', 'vergelabs-media-library' ), 'primary', 'submit', true, array( 'id' => 'eml-submit-tax-settings-non-media' ) ); ?>

                            </div>

                        </div>

                        <h2><?php esc_html_e('Options','vergelabs-media-library'); ?></h2>

                        <?php $vergeml_tax_options = get_option( 'vergeml_tax_options' ); ?>

                        <div class="postbox">

                            <div class="inside">

                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Taxonomy archive pages','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Taxonomy archive pages','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_tax_options[tax_archives]" type="hidden" value="0" /><input name="vergeml_tax_options[tax_archives]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_tax_options['tax_archives'], true ); ?> /> <?php esc_html_e('Turn on media taxonomy archive pages on the front-end','vergelabs-media-library'); ?></label>
                                                <p class="description"><?php esc_html_e( 'Re-save your permalink settings after this option change to make it work.', 'vergelabs-media-library' ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Assign all like hierarchical','vergelabs-media-library'); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e('Assign all like hierarchical','vergelabs-media-library'); ?></span></legend>
                                                <label><input name="vergeml_tax_options[edit_all_as_hierarchical]" type="hidden" value="0" /><input name="vergeml_tax_options[edit_all_as_hierarchical]" type="checkbox" value="1" <?php checked( true, (bool) $vergeml_tax_options['edit_all_as_hierarchical'], true ); ?> /> <?php esc_html_e('Show non-hierarchical taxonomies like hierarchical in Grid View / Media Popup','vergelabs-media-library'); ?></label>
                                            </fieldset>
                                        </td>
                                    </tr>

                                    <?php
                                        /*
                                         *  The one setting that makes this behave like the folder
                                         *  plugins people arrive from.
                                         *
                                         *  A file here can sit in several folders at once, which is
                                         *  the thing neither FileBird nor Folders can do. It is also
                                         *  not what somebody switching from them expects: after years
                                         *  of folders that hold a file once, a drag that adds rather
                                         *  than moves reads as a bug.
                                         *
                                         *  Off by default, because the capability is the point. On,
                                         *  a plain drag moves and the library behaves exactly like
                                         *  the one they left. Either way nothing already filed is
                                         *  touched -- it changes what a drag means, not the data.
                                         */
                                    ?>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'One folder per file', 'vergelabs-media-library' ); ?></th>
                                        <td>
                                            <fieldset>
                                                <legend class="screen-reader-text"><span><?php esc_html_e( 'One folder per file', 'vergelabs-media-library' ); ?></span></legend>
                                                <label><input name="vergeml_tax_options[one_folder_per_file]" type="hidden" value="0" /><input name="vergeml_tax_options[one_folder_per_file]" type="checkbox" value="1" <?php checked( true, ! empty( $vergeml_tax_options['one_folder_per_file'] ), true ); ?> /> <?php esc_html_e( 'Dragging a file into a folder moves it, instead of adding it', 'vergelabs-media-library' ); ?></label>
                                                <p class="description"><?php esc_html_e( 'Off, a file can be in several folders at once and dragging adds it to another — hold Ctrl while dragging to move instead. On, each file lives in one folder, the way a normal folder tree works. Nothing already filed is changed either way.', 'vergelabs-media-library' ); ?></p>
                                            </fieldset>
                                        </td>
                                    </tr>

                                </table>

                                <?php submit_button( __( 'Save Changes', 'vergelabs-media-library' ), 'primary', 'submit', true, array( 'id' => 'eml-submit-tax-settings' ) ); ?>

                            </div>

                        </div>

                        <?php
                            /*
                             *  A "Bulk Edit" settings box used to sit here, permanently
                             *  greyed out and labelled "/ Premium Feature". Its one
                             *  option, bulk_edit_save_button, was stored and validated
                             *  but never read by anything in this plugin: the behaviour
                             *  lived in the paid add-on. Removed rather than shipped as
                             *  a locked teaser for a product this is not.
                             */
                        ?>

                    </form>

                </div>

            </div>

        </div>

    </div>

    <?php
}



/**
 *  vergeml_print_mimetypes_options
 *
 *  @type     callback function
 *  @since    1.0
 *  @created  28/09/13
 */

function vergeml_print_mimetypes_options() {

    if ( ! current_user_can('manage_options' ) )
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );

    if ( is_multisite() ) {

        $vergeml_network_options = get_site_option( 'vergeml_network_options', array() );

        if ( ! current_user_can( 'manage_network_options' ) && ! (bool) $vergeml_network_options['media_settings'] )
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }


    $vergeml_mimes = get_option('vergeml_mimes');

    $title = __( 'Media Settings', 'vergelabs-media-library' ); ?>

    <div id="vergeml-global-options-wrap" class="wrap eml-options">

        <h1>
            <?php echo esc_html( $title ); ?>
            <a class="add-new-h2 vergeml-button-create-mime" href="javascript:;">+ <?php esc_html_e('Add New MIME Type','vergelabs-media-library'); ?></a>
        </h1>

        <?php
        $warning = sprintf( 
            /* translators: %s: html <strong> and <br> tags to emphaseize some points. */
            esc_html__( 'WordPress %1$scommon role restrictions%2$s apply to the allowed MIME Types %1$sto avoid security issues%2$s. Advanced role management is coming.%3$s If you experience an issue with uploading file types report it, please.', 'vergelabs-media-library' ),
            '<strong>',
            '</strong>',
            '<br />'
        );
        printf(
            '<div class="notice notice-news eml-admin-notice dashicons-before">
                <p>%1$s</p>
                <a href="%2$s" target="_blank" class="button button-primary">%3$s</a>
            </div>',
            wp_kses_post( $warning ),
            esc_url( 'https://github.com/vergelabsnathan/vergelabs-media-library/issues' ),
            esc_html__( 'Report a filetype', 'vergelabs-media-library' )
        );
        ?>

        <?php vergeml_print_media_settings_tabs( 'mimetypes' ); ?>

        <div id="poststuff">

            <div id="post-body" class="metabox-holder">

                <div id="postbox-container-2" class="postbox-container">

                    <form method="post" action="options.php" id="vergeml-form-mimetypes">

                        <?php settings_fields( 'mime-types' ); ?>

                        <?php vergeml_print_mimetypes_buttons(); ?>

                        <table class="vergeml-mime-type-list wp-list-table widefat" cellspacing="0">
                            <thead>
                            <tr>
                                <th scope="col" class="manage-column vergeml-column-extension"><?php esc_html_e('Extension','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-mime"><?php esc_html_e('MIME Type','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-singular"><?php esc_html_e('Singular Label','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-plural"><?php esc_html_e('Plural Label','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-filter"><?php esc_html_e('Add Filter','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-upload"><?php esc_html_e('Allow Upload','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-delete"></th>
                            </tr>
                            </thead>


                            <tbody>

                            <?php
                            $all_mimes = wp_get_mime_types();
                            ksort( $all_mimes, SORT_STRING ); ?>

                            <?php foreach ( $all_mimes as $type => $mime ) :

                                if ( isset( $vergeml_mimes[$type] ) ) :

                                    $label = '<code>'. str_replace( '|', '</code>, <code>', esc_html($type) ) .'</code>';

                                    $allowed = (bool) $vergeml_mimes[$type]['upload']; ?>

                                    <tr>
                                    <td id="<?php echo esc_attr( $type ); ?>"><?php echo wp_kses( $label, array( 'code' => array() ) ); ?></td>
                                    <td><code><?php echo esc_html($mime); ?></code><input type="hidden" class="vergeml-mime" name="vergeml_mimes[<?php echo esc_attr($type); ?>][mime]" value="<?php echo esc_html($vergeml_mimes[$type]['mime']); ?>" /></td>
                                    <td><input type="text" name="vergeml_mimes[<?php echo esc_attr($type); ?>][singular]" value="<?php echo esc_html($vergeml_mimes[$type]['singular']); ?>" /></td>
                                    <td><input type="text" name="vergeml_mimes[<?php echo esc_attr($type); ?>][plural]" value="<?php echo esc_html($vergeml_mimes[$type]['plural']); ?>" /></td>
                                    <td class="checkbox_td"><input type="checkbox" name="vergeml_mimes[<?php echo esc_attr($type); ?>][filter]" title="<?php esc_html_e('Add Filter','vergelabs-media-library'); ?>" value="1" <?php checked(true, (bool) $vergeml_mimes[$type]['filter']); ?> /></td>
                                    <td class="checkbox_td"><input type="checkbox" name="vergeml_mimes[<?php echo esc_attr($type); ?>][upload]" title="<?php esc_html_e('Allow Upload','vergelabs-media-library'); ?>" value="1" <?php checked(true, $allowed); ?> /></td>
                                    <td><a class="vergeml-button-remove" title="<?php esc_html_e('Delete MIME Type','vergelabs-media-library'); ?>" href="javascript:;">&ndash;</a></td>
                                    </tr>

                                <?php endif; ?>
                            <?php endforeach; ?>

                            <tr class="vergeml-clone" style="display:none;">
                                <td><input type="text" class="vergeml-type" placeholder="jpg|jpeg|jpe" /></td>
                                <td><input type="text" class="vergeml-mime" placeholder="image/jpeg" /></td>
                                <td><input type="text" class="vergeml-singular" placeholder="Image" /></td>
                                <td><input type="text" class="vergeml-plural" placeholder="Images" /></td>
                                <td class="checkbox_td"><input type="checkbox" class="vergeml-filter" title="<?php esc_html_e('Add Filter','vergelabs-media-library'); ?>" value="1" /></td>
                                <td class="checkbox_td"><input type="checkbox" class="vergeml-upload" title="<?php esc_html_e('Allow Upload','vergelabs-media-library'); ?>" value="1" /></td>
                                <td><a class="vergeml-button-remove" title="<?php esc_html_e('Delete MIME Type','vergelabs-media-library'); ?>" href="javascript:;">&ndash;</a></td>
                            </tr>

                            </tbody>
                            <tfoot>
                            <tr>
                                <th scope="col" class="manage-column vergeml-column-extension"><?php esc_html_e('Extension','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-mime"><?php esc_html_e('MIME Type','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-singular"><?php esc_html_e('Singular Label','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-plural"><?php esc_html_e('Plural Label','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-filter"><?php esc_html_e('Add Filter','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-upload"><?php esc_html_e('Allow Upload','vergelabs-media-library'); ?></th>
                                <th scope="col" class="manage-column vergeml-column-delete"></th>
                            </tr>
                            </tfoot>
                        </table>

                        <?php vergeml_print_mimetypes_buttons(); ?>

                    </form>

                </div>

            </div>

        </div>

    </div>

    <?php
}



/**
 *  vergeml_print_mimetypes_buttons
 *
 *  @since    2.3.1
 *  @created  01/08/16
 */

function vergeml_print_mimetypes_buttons() { ?>

    <p class="submit">
        <?php submit_button( __( 'Save Changes', 'vergelabs-media-library' ), 'primary', 'eml-save-mime-types-settings', false, array( 'id' => 'eml-submit-settings-save-mime-types' ) ); ?>

        <input type="button" name="eml-restore-mime-types-settings" id="eml-restore-mime-types-settings" class="button" value="<?php esc_html_e('Restore WordPress Default MIME Types','vergelabs-media-library'); ?>">
    </p>

    <?php
}



/**
 *  vergeml_print_credits
 *
 *  @since    1.0
 *  @created  28/09/13
 */

function vergeml_print_credits() { ?>

    <div class="postbox" id="vergeml-credits">

        <h3 class="hndle">VergeLabs Media Library <?php echo esc_html( VERGEML_VERSION ); ?></h3>

        <div class="inside">

            <h4><?php esc_html_e( 'Changelog', 'vergelabs-media-library' ); ?></h4>
            <p><?php esc_html_e( 'What\'s new in', 'vergelabs-media-library' ); ?> <a href="https://github.com/vergelabsnathan/vergelabs-media-library/releases"><?php esc_html_e( 'version', 'vergelabs-media-library' ); echo ' ' . esc_html( VERGEML_VERSION ); ?></a>.</p>

            <h4><?php esc_html_e( 'Support', 'vergelabs-media-library' ); ?></h4>
            <p><?php esc_html_e( 'Report a problem on', 'vergelabs-media-library' ); ?> <a href="https://github.com/vergelabsnathan/vergelabs-media-library/issues">GitHub</a>.</p>

            <div class="author">
                <span><?php esc_html_e( 'Based on', 'vergelabs-media-library' ); ?> <a href="https://wordpress.org/plugins/enhanced-media-library/">Enhanced Media Library</a> <?php esc_html_e( 'by', 'vergelabs-media-library' ); ?> <a href="https://wpuxsolutions.com/">wpUXsolutions</a></span>
            </div>

        </div>

    </div>

    <?php
}







/**
 *  vergeml_admin_notice
 *
 *  Shows a notice
 * 
 *  @since    2.8.10
 *  @created  2024/04
 */

add_action( 'admin_notices', 'vergeml_admin_notice' );
add_action( 'network_admin_notices', 'vergeml_admin_notice' );

function vergeml_admin_notice() {

    global // $pagenow,
           $current_screen;


    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }


    $notices = get_site_option( 'vergeml_notices', array() );


    if ( empty( $notices ) ) {
        return;
    }


    if ( ! isset( $notices['current'] ) ) {
        return;
    }


    $notice_id = $notices['current'];


    $user_id = get_current_user_id();
    if ( get_user_meta( $user_id, "vergeml_{$notice_id}_notice_dismissed" ) ) {
        return;
    }


    $notice = $notices[$notice_id];


    if (    ! empty( $notice['version'] ) && 
            version_compare( VERGEML_VERSION, $notice['version'], '>=' ) 
        ) {
        return;
    }


    if (    ! empty( $notice['for'] ) ) {

        // a notice for free users only
        if ( in_array( 'free', $notice['for'] ) && defined( 'EML_IS_PRO' ) ) {
            return;
        }

        // a notice for pro users only
        if ( in_array( 'pro', $notice['for'] ) && ! defined( 'EML_IS_PRO' ) ) {
            return;
        }

        // a notice for multisite users only
        if ( in_array( 'multisite', $notice['for'] ) && ! is_multisite() ) {
            return;
        }
    }


    if (    ! isset( $notice['screens'] ) || 
            ! in_array( $current_screen->base, $notice['screens'] ) 
        ) {
        return;
    }


    printf(
        '<div class="notice notice-%2$s is-dismissible eml-admin-notice dashicons-before" id="%3$s">
            %1$s
        </div>',
        wp_kses(
            $notice['message'],
            array(
                'p'      => array(),
                'a'      => array(
                    'href'   => array(),
                    'title'  => array(),
                    'class'  => array(),
                    'target' => array()
                ),
                'br'     => array(),
                'em'     => array(),
                'strong' => array( 
                    'class'  => array()
                )
            )
        ),
        esc_attr( $notice['type'] ),
        esc_html( $notice_id )
    );
}



/**
 *  vergeml_admin_notice_dismiss
 *
 *  Associates a dismissed notice mark with a user
 * 
 *  @since    2.8.10
 *  @created  2024/04
 */

add_action( 'wp_ajax_vergeml-admin-notice-dismiss', 'vergeml_admin_notice_dismiss' );

function vergeml_admin_notice_dismiss() {

    if ( ! isset( $_POST['notice_id'] ) )
        wp_die();


    check_ajax_referer( 'eml-admin-notice-nonce', 'nonce' );


    if ( ! isset( $_POST['notice_id'] ) )
        wp_send_json_error();

    $notice_id = sanitize_text_field( wp_unslash( $_POST['notice_id'] ) );
    $user_id = get_current_user_id();

    update_user_meta( $user_id, "vergeml_{$notice_id}_notice_dismissed", true );


    wp_die();
}




