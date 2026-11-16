<?php

if ( ! defined( 'ABSPATH' ) )
    exit;



/**
 *  Elementor
 *
 *  @since    2.5
 *  @since    2.9.2 register scripts for Elementor added
 *  @created  28/01/18
 */

add_action( 'elementor/editor/before_enqueue_scripts', 'vergeml_register_scripts' );

add_action( 'elementor/editor/after_enqueue_scripts', 'vergeml_elementor_scripts' );

function vergeml_elementor_scripts() {

    global $vergeml_dir;


    wp_enqueue_style(
        'vergeml-elementor-media-style',
        $vergeml_dir . 'css/eml-admin-media.css',
        array(),
        VERGEML_VERSION
    );
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
