<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  The folders version stamp.
 *
 *  One integer, moved forward whenever the folder tree changes shape -- a
 *  folder made, renamed, re-parented, re-ordered, re-coloured or deleted --
 *  and whenever a Move finishes or is undone. Every open surface (the media
 *  library's panel, the Folders screen) polls it every five seconds and on
 *  coming back into view, and re-reads the tree when it has moved. Two tabs
 *  therefore never disagree about the folders for longer than that, and a
 *  draft on the Folders screen is rebased onto the tree as it is, by term id,
 *  rather than onto the tree as it was when the session opened.
 *
 *  A stamp and not the tree, because the poll runs on every open tab and the
 *  tree is a few kilobytes of terms and counts. The stamp is one option read.
 *
 *  Not autoloaded, on purpose. An autoloaded option that has never been
 *  written costs a query on every request of every site until it is written
 *  (docs/testing.md, "An autoloaded option is only free once it exists"), and
 *  most sites will not touch a folder for weeks. Kept out of alloptions the
 *  poll costs exactly one query per tick and nothing anywhere else -- which
 *  is also what tests/tree/folders-version.php asserts.
 *
 *  @since 3.17
 */

const VERGEML_FOLDERS_VERSION = 'vergeml_folders_version';


function vergeml_folders_version() {
    return (int) get_option( VERGEML_FOLDERS_VERSION, 0 );
}


function vergeml_folders_version_bump() {
    $next = vergeml_folders_version() + 1;
    update_option( VERGEML_FOLDERS_VERSION, $next, false );
    return $next;
}


/**
 *  Pictures moved, or put back.
 *
 *  The shape of the tree need not have changed -- a Move into folders that
 *  already exist changes only counts -- but every surface shows the counts,
 *  so to them it is a change of the tree. Called where a Move completes and
 *  where an undo completes: the guide's re-filing (core/folder-talk.php) and
 *  the Librarian's batches (core/librarian.php).
 */

function vergeml_folders_moved( $why = '' ) {
    do_action( 'vergeml_folders_moved', $why );
    return vergeml_folders_version_bump();
}


/* ------------------------------------------------------------- term hooks */

/*
 *  Every way a folder can change that does not pass through our own routes:
 *  WordPress's own term screens, an importer, the Librarian making folders,
 *  a plugin. The routes bump as well, once more, at the end of the write --
 *  a re-order touches only term meta and fires no term hook.
 */
add_action( 'created_term', 'vergeml_folders_version_on_term', 10, 3 );
add_action( 'edited_term',  'vergeml_folders_version_on_term', 10, 3 );
add_action( 'delete_term',  'vergeml_folders_version_on_term', 10, 3 );

function vergeml_folders_version_on_term( $term_id, $tt_id, $taxonomy ) {

    if ( ! vergeml_folders_version_taxonomy( $taxonomy ) ) {
        return;
    }

    vergeml_folders_version_bump();
}


/** Is this taxonomy one of the trees a surface draws? */
function vergeml_folders_version_taxonomy( $taxonomy ) {

    if ( ! is_string( $taxonomy ) || '' === $taxonomy || ! is_taxonomy_hierarchical( $taxonomy ) ) {
        return false;
    }

    return function_exists( 'vergeml_tree_taxonomies' ) && in_array( $taxonomy, vergeml_tree_taxonomies(), true );
}


/* ------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_folders_version_route' );

function vergeml_folders_version_route() {

    register_rest_route( VERGEML_REST_NS, '/folders/version', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'vergeml_rest_folders_version',
        'permission_callback' => 'vergeml_can_read_tree',
    ) );
}


/**
 *  { version }. One option read and nothing else -- the budget in
 *  tests/tree/folders-version.php is exactly that.
 */

function vergeml_rest_folders_version() {

    $response = rest_ensure_response( array( 'version' => vergeml_folders_version() ) );
    $response->header( 'Cache-Control', 'no-store' );

    return $response;
}
