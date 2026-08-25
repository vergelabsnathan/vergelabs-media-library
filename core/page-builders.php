<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Page builders.
 *
 *  Elementor and Divi both open WordPress's own media modal to pick an image, so
 *  the folder tree needs no new interface for either of them -- it needs to be on
 *  the page. That is the whole problem: neither builder is reached by
 *  admin_enqueue_scripts the way an admin screen is.
 *
 *  Elementor's editor is its own screen with its own enqueue hook. Divi's visual
 *  builder runs on the *front end*, where admin_enqueue_scripts never fires at
 *  all -- which is why a media plugin can look thoroughly broken inside Divi
 *  while working everywhere else.
 *
 *  Nothing here draws anything. Each hook decides "the tree belongs on this
 *  page", and the same code that puts it on the media library puts it here.
 *
 *  @since 3.2
 */


/**
 *  vergeml_builder_load_tree
 *
 *  Put the tree on this request, wherever the request came from.
 */

function vergeml_builder_load_tree() {

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

    $GLOBALS['vergeml_force_tree'] = true;

    vergeml_tree_assets( '' );

    unset( $GLOBALS['vergeml_force_tree'] );
}


/* ------------------------------------------------------------- Elementor */

/*
 *  before_enqueue_scripts rather than after: the tree declares media-views as a
 *  dependency, and registering it before Elementor's own bundle keeps WordPress
 *  free to order the two however it needs to.
 */
add_action( 'elementor/editor/before_enqueue_scripts', 'vergeml_builder_elementor' );

function vergeml_builder_elementor() {
    vergeml_builder_load_tree();
}


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
