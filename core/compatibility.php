<?php

if ( ! defined( 'ABSPATH' ) )
    exit;



/**
 *  Elementor
 *  @TODO: temporary solution
 *
 *  @since    2.5
 *  @since    2.9.2 register scripts for Elementor added
 *  @created  28/01/18
 */

add_action( 'elementor/editor/before_enqueue_scripts', 'vergeml_register_scripts' );

add_action( 'elementor/editor/after_enqueue_scripts', 'vergeml_elementor_scripts' );

function vergeml_elementor_scripts() {

    global $vergeml_dir;


    wp_enqueue_style( 'common' );
    wp_enqueue_style(
        'vergeml-elementor-media-style',
        $vergeml_dir . 'css/eml-admin-media.css',
        array(),
        VERGEML_VERSION
    );
}





/**
 *  Impreza Theme
 *
 *  @since    2.8.8
 *  @created  08/2021
 */

add_action( 'after_setup_theme', 'vergeml_after_setup_theme_impreza', 9 );

function vergeml_after_setup_theme_impreza() {

    remove_filter( 'attachment_fields_to_edit', 'us_attachment_fields_to_edit_categories' );
}



/**
 *  SimpLy Gallery plugin
 *
 *  @since    2.8.9
 *  @created  10/2021
 */

add_action( 'wp_loaded', 'vergeml_compat_on_wp_loaded' );

function vergeml_compat_on_wp_loaded() {

    remove_filter( 'ajax_query_attachments_args', 'pgc_sgb_ajaxQueryAttachmentsArgs', 20 );
}



/**
 *  Enhanced Media Library, active at the same time
 *
 *  The careless upgrade: this plugin installed before the old one is switched
 *  off. Both are then on the media screens, and both ship the same forked
 *  media JavaScript -- models, views, grid, admin. Two copies on one page and
 *  the older one answers: the tree sets media_category on the library, the
 *  request leaves without it, and the grid shows every file (matrix,
 *  2026-09-11). The newer fork wins, so the old one's scripts and styles are
 *  taken off the page while it is still active -- and so is its media
 *  template: both print #tmpl-attachment-grid-view, wp.template() takes the
 *  first on the page, and the old one reads a global its own scripts no longer
 *  define, so the grid rendered nothing. Its other PHP is left alone; the
 *  upgrade suite proves both can run.
 *
 *  @since    3.16.2
 */

add_action( 'admin_enqueue_scripts', 'vergeml_compat_eml_scripts', 100 );
add_action( 'wp_enqueue_media', 'vergeml_compat_eml_scripts', 100 );
add_action( 'wp_loaded', 'vergeml_compat_eml_templates' );

function vergeml_compat_eml_templates() {

    if ( function_exists( 'wpuxss_eml_print_media_templates' ) ) {
        remove_action( 'print_media_templates', 'wpuxss_eml_print_media_templates' );
    }
}

function vergeml_compat_eml_scripts() {

    if ( ! function_exists( 'wpuxss_eml_enqueue_media' ) ) {
        return;
    }

    $scripts = array(
        'wpuxss-eml-admin-script',
        'wpuxss-eml-media-list-script',
        'wpuxss-eml-media-models-script',
        'wpuxss-eml-media-views-script',
        'wpuxss-eml-enhanced-medialist-script',
        'wpuxss-eml-media-editor-script',
        'wpuxss-eml-media-grid-script',
        'wpuxss-eml-media-script',
        'wpuxss-eml-taxonomies-options-script',
    );

    foreach ( $scripts as $handle ) {
        wp_dequeue_script( $handle );
        wp_deregister_script( $handle );
    }

    foreach ( array( 'wpuxss-eml-admin-custom-style', 'wpuxss-eml-admin-media-style' ) as $handle ) {
        wp_dequeue_style( $handle );
        wp_deregister_style( $handle );
    }
}



/**
 *  Media Shorcodes
 *
 *  @since    2.8
 *  @created  10/2020
 */

if ( vergeml_enhance_media_shortcodes() ) {

    /**
     *  Enfold Theme
     *  for [av_masonry_gallery] shortcode
     *
     *  Use Default Layout and choose the shortcode Media Elements > Masonry Gallery 
     *  to make theme gallery shows images from the specific category.
     *
     *  @since    2.8
     *  @created  9/10/20
     */

    $vergeml_theme = wp_get_theme();

    if ( ! empty( $vergeml_theme ) ) {

        $vergeml_parent_theme = $vergeml_theme->parent();

        if ( ! empty( $vergeml_parent_theme ) ) {
            $vergeml_theme = $vergeml_parent_theme;
        }

        if ( 'Enfold' === $vergeml_theme->get( 'Name' ) && version_compare( $vergeml_theme->get( 'Version' ), '4.8.4', '>=') ) {

            add_filter( 'shortcode_atts_av_masonry_gallery', 'vergeml_shortcode_atts', 10, 3 );
        }   
        else {
            add_filter( 'shortcode_atts_av_masonry_entries', 'vergeml_shortcode_atts', 10, 3 );
        }
    }


    /**
     *  FooGallery
     *
     *  @since    2.8.4
     *  @created  08/04/21
     */

    add_filter( 'foogallery_shortcode_atts', 'vergeml_foogallery_shortcode_atts' );
}

function vergeml_foogallery_shortcode_atts( $atts ) {

    $id = isset( $atts['id'] ) ? intval( $atts['id'] ) : 0;
    unset( $atts['id'] );

    $atts = vergeml_shortcode_atts( array(), array(), $atts );
    $atts['id'] = $id;

    if ( isset( $atts['ids'] ) ) {
        $atts['attachment_ids'] = $atts['ids'];
        unset( $atts['ids'] );
    }

    return $atts;
}
