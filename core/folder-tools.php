<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Two things a folder can do besides hold files: catch uploads, and leave as
 *  a ZIP.
 *
 *  @since 3.2
 */


/* ----------------------------------------------- uploads land in the folder */

/**
 *  An upload made while a folder is open lands in that folder.
 *
 *  It is the first thing anybody coming from FileBird tries, and until now it
 *  quietly did nothing: the file arrived in Unfiled and the folder they were
 *  looking at stayed as it was.
 *
 *  The folder travels WITH the upload rather than being guessed at from here.
 *  The tree's script adds it to the upload request itself, so the server never
 *  has to reconstruct which screen the user was on -- an upload from a plugin,
 *  a front-end form or WP-CLI carries no folder and is left alone, exactly as
 *  before.
 */

add_action( 'add_attachment', 'vergeml_file_upload_into_folder' );

function vergeml_file_upload_into_folder( $attachment_id ) {

    // phpcs:disable WordPress.Security.NonceVerification.Missing -- riding along
    // on core's own upload request, whose nonce core has already checked; this
    // reads a hint from it and verifies everything about the hint itself.
    if ( ! isset( $_POST['vergeml_folder'] ) ) {
        return;
    }

    $folder   = (int) $_POST['vergeml_folder'];
    $taxonomy = isset( $_POST['vergeml_folder_tax'] ) ? sanitize_key( wp_unslash( $_POST['vergeml_folder_tax'] ) ) : '';
    // phpcs:enable

    if ( $folder < 1 ) {
        return;
    }

    $taxonomies = function_exists( 'vergeml_tree_taxonomies' ) ? vergeml_tree_taxonomies() : array();

    if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
        return;
    }

    if ( ! get_term( $folder, $taxonomy ) instanceof WP_Term ) {
        return;
    }

    // The uploader is the attachment's author; no further capability question
    // arises from filing your own upload into the folder you are looking at.
    wp_set_object_terms( $attachment_id, array( $folder ), $taxonomy, true );

    if ( function_exists( 'vergeml_folder_flush_counts' ) ) {
        vergeml_folder_flush_counts();
    }
}


/* --------------------------------------------------------- folder as a ZIP */

/**
 *  vergeml_zip_folder
 *
 *  The folder's files, and its sub-folders' files, into one archive on disk.
 *
 *  Kept apart from the download handler so it can be tested without an HTTP
 *  response in the way, and reused by anything else that wants an archive.
 *  Sub-folders become directories inside the ZIP, so what comes out of the
 *  archive looks like what the tree showed.
 */

function vergeml_zip_folder( $folder, $taxonomy, $zip_path ) {

    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_Error( 'vergeml_no_zip', __( 'This server cannot build ZIP archives.', 'vergelabs-media-library' ) );
    }

    $term = get_term( $folder, $taxonomy );

    if ( ! $term instanceof WP_Term ) {
        return new WP_Error( 'vergeml_unknown_term', __( 'That folder does not exist.', 'vergelabs-media-library' ) );
    }

    $zip = new ZipArchive();

    if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
        return new WP_Error( 'vergeml_zip_failed', __( 'The archive could not be created.', 'vergelabs-media-library' ) );
    }

    $added   = 0;
    $missing = 0;

    /*
     *  Walked folder by folder rather than one flat query, because each file's
     *  place in the archive is its place in the tree -- and a file in two
     *  sub-folders appears in both, which is what the tree says.
     */
    $walk = function ( $term_id, $prefix ) use ( &$walk, &$added, &$missing, $zip, $taxonomy ) {

        $files = get_posts( array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- zipping a folder IS a term lookup; one term per walk step, attachments only.
            'tax_query'      => array( array(
                'taxonomy'         => $taxonomy,
                'field'            => 'term_id',
                'terms'            => array( $term_id ),
                'include_children' => false,
            ) ),
        ) );

        foreach ( $files as $id ) {

            $path = get_attached_file( $id );

            if ( ! $path || ! file_exists( $path ) ) {
                $missing++;
                continue;
            }

            $zip->addFile( $path, $prefix . basename( $path ) );
            $added++;
        }

        $children = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'parent' => $term_id ) );

        foreach ( (array) $children as $child ) {
            if ( $child instanceof WP_Term ) {
                $walk( $child->term_id, $prefix . sanitize_file_name( $child->name ) . '/' );
            }
        }
    };

    $walk( (int) $folder, '' );

    $zip->close();

    if ( 0 === $added ) {
        // An archive of nothing helps nobody; the empty file should not linger.
        if ( file_exists( $zip_path ) ) {
            wp_delete_file( $zip_path );
        }
        return new WP_Error( 'vergeml_zip_empty', __( 'That folder has no files on disk to download.', 'vergelabs-media-library' ) );
    }

    return array( 'added' => $added, 'missing' => $missing, 'name' => $term->slug );
}


/**
 *  The download itself, from a plain link the tree builds.
 *
 *  admin-post rather than REST, because the result is a file for the browser to
 *  save and REST answers are JSON envelopes. Nonce-checked: the link is per
 *  session, so a folder cannot be zipped by luring an admin onto a page with an
 *  image tag pointing here.
 */

add_action( 'admin_post_vergeml_zip', 'vergeml_zip_download' );

function vergeml_zip_download() {

    if ( ! current_user_can( 'upload_files' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to download folders.', 'vergelabs-media-library' ), 403 );
    }

    check_admin_referer( 'vergeml_zip' );

    $folder     = isset( $_GET['folder'] ) ? (int) $_GET['folder'] : 0;
    $taxonomy   = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
    $taxonomies = function_exists( 'vergeml_tree_taxonomies' ) ? vergeml_tree_taxonomies() : array();

    if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
        wp_die( esc_html__( 'That is not a media taxonomy.', 'vergelabs-media-library' ), 404 );
    }

    $tmp = wp_tempnam( 'vergeml-zip' );

    $made = vergeml_zip_folder( $folder, $taxonomy, $tmp );

    if ( is_wp_error( $made ) ) {
        if ( file_exists( $tmp ) ) {
            wp_delete_file( $tmp );
        }
        wp_die( esc_html( $made->get_error_message() ), 404 );
    }

    nocache_headers();
    header( 'Content-Type: application/zip' );
    header( 'Content-Disposition: attachment; filename="' . $made['name'] . '.zip"' );
    header( 'Content-Length: ' . (string) filesize( $tmp ) );

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a file we just built is the entire job here.
    readfile( $tmp );

    wp_delete_file( $tmp );
    exit;
}
