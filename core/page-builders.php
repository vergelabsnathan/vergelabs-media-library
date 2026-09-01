<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Page builders.
 *
 *  Every builder opens WordPress's own media modal to pick an image, so the
 *  folder tree needs no new interface for any of them -- it needs to be on the
 *  page. That is the whole problem: none of them is reached by
 *  admin_enqueue_scripts the way an admin screen is. Some are their own admin
 *  screen with their own enqueue hook; many run on the *front end*, where
 *  admin_enqueue_scripts never fires at all -- which is why a media plugin can
 *  look thoroughly broken inside a builder while working everywhere else.
 *
 *  So this file is a table: detect the builder, find the moment it loads its
 *  editor, put the tree there. Nothing here draws anything. Each entry decides
 *  "the tree belongs on this request", and the same code that puts it on the
 *  media library puts it here.
 *
 *  The detection guards and hook names are the accumulated field knowledge of
 *  this plugin category -- the moments each builder exposes for exactly this
 *  purpose -- checked against the current release of each builder where we
 *  could install one. Entries for builders we could not run are marked, and
 *  are best effort until someone can.
 *
 *  @since 3.2
 *  @since 3.11 the full table
 */


/**
 *  vergeml_builder_load_tree
 *
 *  Put the tree on this request, wherever the request came from.
 *
 *  @param bool $print  Print the assets right now as well as enqueueing them,
 *                      for hooks that fire after the footer has already gone
 *                      out -- an enqueue alone would then print nothing.
 */

function vergeml_builder_load_tree( $print = false ) {

    if ( ! function_exists( 'vergeml_tree_assets' ) ) {
        return;
    }

    if ( ! current_user_can( 'upload_files' ) ) {
        return;
    }

    /*
     *  The frames have to exist before there is anything to attach to. Builders
     *  call this themselves, but the order is theirs and not ours, and calling it
     *  twice costs nothing.
     */
    if ( function_exists( 'wp_enqueue_media' ) ) {
        wp_enqueue_media();
    }

    /*
     *  The tree script depends on the fallback transport, which registers on
     *  admin screens only. On a front-end builder that dependency would be
     *  missing and WordPress prints nothing -- silently. Register it here too.
     */
    if ( function_exists( 'vergeml_transport_assets' ) && ! wp_script_is( 'vergeml-transport', 'registered' ) ) {
        vergeml_transport_assets();
    }

    $GLOBALS['vergeml_force_tree'] = true;

    vergeml_tree_assets( '' );

    unset( $GLOBALS['vergeml_force_tree'] );

    if ( true === $print ) {
        wp_print_styles( array( 'vergeml-tree', 'vergeml-tree-rtl' ) );
        wp_print_scripts( array( 'vergeml-tree', 'vergeml-touch' ) );
    }
}


/**
 *  vergeml_builder_print_tree
 *
 *  For hooks named "footer" or "after": the page is already on its way out.
 */

function vergeml_builder_print_tree() {
    vergeml_builder_load_tree( true );
}


/**
 *  vergeml_builder_load_tree_passthrough
 *
 *  For builders that expose no action at the right moment, only a filter --
 *  the value goes back untouched.
 */

function vergeml_builder_load_tree_passthrough( $value = null ) {
    vergeml_builder_load_tree();
    return $value;
}


/* -------------------------------------------------------------- WPBakery */

/*
 *  Both hooks are WPBakery's own, fired when it enqueues its editors, so this
 *  costs nothing on sites without it and cannot fire too early. Verified in
 *  the browser: Single Image > Add image > Media Library tab shows the tree.
 */
add_action( 'vc_backend_editor_enqueue_js_css', 'vergeml_builder_load_tree' );
add_action( 'vc_frontend_editor_enqueue_js_css', 'vergeml_builder_load_tree' );


/* ------------------------------------------------------------- Elementor */

/*
 *  before_enqueue_scripts rather than after: the tree declares media-views as a
 *  dependency, and registering it before Elementor's own bundle keeps WordPress
 *  free to order the two however it needs to. Elementor's constant exists from
 *  plugins_loaded, so this can hook directly.
 */
add_action( 'elementor/editor/before_enqueue_scripts', 'vergeml_builder_load_tree' );


/* ------------------------------------------------------------------ Divi */

/**
 *  vergeml_builder_is_divi_fb
 *
 *  Whether this front-end request is Divi's visual builder.
 *
 *  Asked three ways because Divi has spelled it differently across versions and
 *  a media plugin that guesses wrong here is invisible inside the builder with
 *  nothing on screen to explain why.
 */

function vergeml_builder_is_divi_fb() {

    if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
        return true;
    }

    if ( function_exists( 'et_fb_is_enabled' ) && et_fb_is_enabled() ) {
        return true;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which screen this is, not acting on it.
    $flag = isset( $_GET['et_fb'] ) ? sanitize_text_field( wp_unslash( $_GET['et_fb'] ) ) : '';

    return '1' === $flag && is_user_logged_in();
}


add_action( 'wp_enqueue_scripts', 'vergeml_builder_divi', 20 );

function vergeml_builder_divi() {

    if ( ! vergeml_builder_is_divi_fb() ) {
        return;
    }

    vergeml_builder_load_tree();
}


/* ------------------------------------------------------------- the table */

/*
 *  Themes declare their constants in functions.php and plugins at
 *  plugins_loaded; init at 20 is after both.
 */
add_action( 'init', 'vergeml_builder_register', 20 );

function vergeml_builder_register() {

    // Divi 4 and 5 both expose an assets hook; the front-end check above
    // stays as the belt to these braces.
    if ( class_exists( 'ET_Builder_Element' ) || function_exists( 'et_builder_d5_enabled' ) ) {
        add_action( 'et_fb_enqueue_assets', 'vergeml_builder_load_tree' );
        add_action( 'et_fb_framework_loaded', 'vergeml_builder_load_tree' );
        add_action( 'divi_visual_builder_assets_before_enqueue_scripts', 'vergeml_builder_load_tree' );
    }

    // Beaver Builder: current hook plus the legacy one older versions fire.
    if ( class_exists( 'FLBuilderLoader' ) || class_exists( 'FLBuilder' ) ) {
        add_action( 'fl_builder_ui_enqueue_scripts', 'vergeml_builder_load_tree' );
        add_action( 'fl_before_sortable_enqueue', 'vergeml_builder_load_tree' );
    }

    if ( class_exists( 'Brizy_Editor' ) ) {
        add_action( 'brizy_editor_enqueue_scripts', 'vergeml_builder_load_tree' );
    }

    // Cornerstone (Pro / X theme): the current app hook and the legacy editor one.
    if ( class_exists( 'Cornerstone_Plugin' ) || class_exists( 'Cornerstone_Preview_Frame_Loader' ) ) {
        add_action( 'cornerstone_before_wp_editor', 'vergeml_builder_load_tree' );
        add_action( 'cornerstone_before_boot_app', 'vergeml_builder_load_tree' );
    }

    // Thrive Architect exposes an action; Thrive Quiz Builder only a filter.
    if ( defined( 'TVE_IN_ARCHITECT' ) || class_exists( 'Thrive_Quiz_Builder' ) ) {
        add_action( 'tcb_main_frame_enqueue', 'vergeml_builder_load_tree' );
        add_filter( 'tge_filter_edit_post', 'vergeml_builder_load_tree_passthrough' );
    }

    // Avada: Fusion Builder Live when the builder plugin is present, the
    // theme's own live hook when it is the theme alone.
    if ( class_exists( 'Fusion_Builder_Front' ) ) {
        add_action( 'fusion_builder_enqueue_live_scripts', 'vergeml_builder_load_tree' );
    }
    elseif ( defined( 'AVADA_VERSION' ) ) {
        add_action( 'fusion_enqueue_live_scripts', 'vergeml_builder_load_tree' );
    }

    if ( defined( 'CT_VERSION' ) ) { // Oxygen
        add_action( 'oxygen_enqueue_ui_scripts', 'vergeml_builder_load_tree' );
    }

    if ( defined( 'BRICKS_VERSION' ) ) {
        add_action( 'bricks_after_footer', 'vergeml_builder_bricks' );
    }

    if ( defined( '__BREAKDANCE_VERSION' ) ) {
        // Breakdance opens WordPress's media UI in a frame flagged by this
        // parameter; that frame is an admin request. Reading which screen this
        // is, not acting on it.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! empty( $_GET['breakdance_wpuiforbuilder_media'] ) ) {
            add_action( 'admin_enqueue_scripts', 'vergeml_builder_load_tree', 9 );
        }
    }

    if ( class_exists( 'ZionBuilder\Plugin' ) || class_exists( 'ZionBuilder\Utils' ) ) {
        add_action( 'zionbuilder/editor/before_scripts', 'vergeml_builder_load_tree' );
    }
    if ( function_exists( 'znb_kallyas_integration' ) ) { // Zion inside Kallyas
        add_action( 'znpb_editor_after_load_scripts', 'vergeml_builder_load_tree' );
    }

    if ( class_exists( 'YOOtheme\Builder' ) ) {
        add_action( 'admin_print_footer_scripts-yootheme_customizer', 'vergeml_builder_print_tree' );
    }

    if ( defined( 'TATSU_VERSION' ) ) { // Oshine
        add_action( 'tatsu_builder_footer', 'vergeml_builder_print_tree' );
    }

    if ( defined( 'MFN_THEME_VERSION' ) ) { // BeTheme / BeBuilder
        add_action( 'mfn_header_enqueue', 'vergeml_builder_load_tree' );
        add_action( 'mfn_footer_enqueue', 'vergeml_builder_print_tree' );
    }

    if ( defined( 'THEMIFY_VERSION' ) && class_exists( 'Themify_Builder_Model' ) ) {
        // Themify loads its editor over admin-ajax and prints enqueued scripts
        // into that response; 9 is before it does.
        add_action( 'wp_ajax_tb_load_editor', 'vergeml_builder_load_tree', 9 );
    }

    if ( class_exists( 'Tailor' ) ) {
        add_action( 'tailor_enqueue_sidebar_scripts', 'vergeml_builder_load_tree' );
    }

    if ( class_exists( 'LP_Addon_Frontend_Editor_Preload' ) ) { // LearnPress front-end editor
        add_action( 'learnpress/addons/frontend_editor/enqueue_scripts', 'vergeml_builder_load_tree' );
    }

    if ( defined( 'DOKAN_PLUGIN_VERSION' ) ) {
        add_action( 'dokan_enqueue_scripts', 'vergeml_builder_dokan' );
    }
}


/**
 *  vergeml_builder_bricks
 *
 *  Bricks decides it is the builder per request, so the check belongs at the
 *  moment of the hook rather than at init. Its hook is after the footer.
 */

function vergeml_builder_bricks() {

    if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
        vergeml_builder_print_tree();
    }
}


/**
 *  vergeml_builder_dokan
 *
 *  Dokan's vendor dashboard is a front-end page where sellers upload product
 *  images; the tree belongs there and nowhere else on the storefront.
 */

function vergeml_builder_dokan() {

    if ( ! function_exists( 'dokan_is_seller_dashboard' ) ) {
        return;
    }

    $editing = get_query_var( 'edit' ) && is_singular( 'product' );

    if ( dokan_is_seller_dashboard() || $editing || apply_filters( 'dokan_forced_load_scripts', false ) ) {
        vergeml_builder_load_tree();
    }
}
