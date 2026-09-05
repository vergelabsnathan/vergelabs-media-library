<?php
/**
 *  The folders version stamp.
 *
 *      wp eval-file tests/tree/folders-version.php --allow-root
 *
 *  Three claims. The stamp moves whenever the tree changes shape -- a folder
 *  made, renamed, re-parented, re-ordered or deleted, by any road -- and when
 *  a Move or an undo completes. Its route answers { version } to anyone who
 *  can read the tree and to nobody else. And the route is one option read:
 *  every open tab asks every five seconds, so a second query here is a
 *  second query per tab per five seconds on every site.
 *
 *  The draft's side of the contract -- a draft survives a rename by id, a
 *  draft folder whose live folder was deleted becomes a new folder -- lives
 *  in the browser and is asserted in tests/tree/tree-view.mjs.
 *
 *  Mutation check run against this suite: with the edited_term hook removed
 *  from core/folders-version.php, A2 (rename) and A3 (re-parent) go red.
 *
 *  Makes three folders and deletes them again. The stamp itself is left
 *  where it lands; it only ever counts up.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_folders_version' ) || ! function_exists( 'vergeml_rest_folder' ) ) {
    echo "core/folders-version.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

wp_set_current_user( 1 );

$GLOBALS['fv_pass'] = 0;
$GLOBALS['fv_fail'] = 0;
$GLOBALS['fv_log']  = '';
$GLOBALS['fv_tax']  = 'media_category';

function fv_say( $line ) {
    $GLOBALS['fv_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function fv_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['fv_pass']++;
    } else {
        $GLOBALS['fv_fail']++;
    }
    fv_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

/** Did the stamp move past $from? Returns the new value for the next step. */
function fv_moved( $label, $from ) {
    $now = vergeml_folders_version();
    fv_check( $label, $now > $from, sprintf( 'was %d, now %d', $from, $now ) );
    return $now;
}

function fv_folder( $params ) {
    $r = new WP_REST_Request( 'POST', '/vergeml/v1/folder' );
    $params['taxonomy'] = $GLOBALS['fv_tax'];
    foreach ( $params as $k => $v ) {
        $r->set_param( $k, $v );
    }
    $res = vergeml_rest_folder( $r );
    if ( is_wp_error( $res ) ) {
        return array( 'error' => $res->get_error_code() );
    }
    return $res instanceof WP_REST_Response ? $res->get_data() : (array) $res;
}

function fv_cleanup() {
    foreach ( array( 'fv-root', 'fv-child', 'fv-child-renamed', 'fv-other' ) as $slug ) {
        $e = get_term_by( 'slug', $slug, $GLOBALS['fv_tax'] );
        if ( $e ) {
            wp_delete_term( $e->term_id, $GLOBALS['fv_tax'] );
        }
    }
    $tag = get_term_by( 'slug', 'fv-not-a-folder', 'post_tag' );
    if ( $tag ) {
        wp_delete_term( $tag->term_id, 'post_tag' );
    }
}

if ( ! taxonomy_exists( $GLOBALS['fv_tax'] ) ) {
    echo "media_category is not registered here\n";
    exit( 1 );
}

fv_cleanup();

fv_say( "\nfolders version\n" );

/* ------------------------------------------------ A  every change moves it */

fv_say( "\nA  the stamp moves on every change of shape\n" );

$fv_v = vergeml_folders_version();
fv_check( 'the stamp is an integer', is_int( $fv_v ) && $fv_v >= 0, (string) $fv_v );

$fv_root = wp_insert_term( 'FV Root', $GLOBALS['fv_tax'], array( 'slug' => 'fv-root' ) );
$fv_root = is_wp_error( $fv_root ) ? 0 : (int) $fv_root['term_id'];
fv_check( 'a folder was made for the run', $fv_root > 0 );
$fv_v = fv_moved( 'A1 create, through WordPress itself', $fv_v );

$fv_child = wp_insert_term( 'FV Child', $GLOBALS['fv_tax'], array( 'slug' => 'fv-child', 'parent' => $fv_root ) );
$fv_child = is_wp_error( $fv_child ) ? 0 : (int) $fv_child['term_id'];
$fv_v     = vergeml_folders_version();

wp_update_term( $fv_child, $GLOBALS['fv_tax'], array( 'name' => 'FV Child renamed', 'slug' => 'fv-child-renamed' ) );
$fv_v = fv_moved( 'A2 rename', $fv_v );

$fv_other = wp_insert_term( 'FV Other', $GLOBALS['fv_tax'], array( 'slug' => 'fv-other' ) );
$fv_other = is_wp_error( $fv_other ) ? 0 : (int) $fv_other['term_id'];
$fv_v     = vergeml_folders_version();

wp_update_term( $fv_child, $GLOBALS['fv_tax'], array( 'parent' => $fv_other ) );
$fv_v = fv_moved( 'A3 re-parent', $fv_v );

$fv_res = fv_folder( array( 'action' => 'order', 'ids' => array( $fv_other, $fv_root ), 'parent' => 0 ) );
$fv_v   = fv_moved( 'A4 re-order through the route, which touches no term', $fv_v );
fv_check( 'the route answers with the version it left behind',
    isset( $fv_res['version'] ) && (int) $fv_res['version'] === vergeml_folders_version(),
    isset( $fv_res['version'] ) ? (string) $fv_res['version'] : 'no version in the response' );

fv_folder( array( 'action' => 'color', 'id' => $fv_root, 'color' => '#3858e9' ) );
$fv_v = fv_moved( 'A5 colour through the route', $fv_v );

wp_delete_term( $fv_child, $GLOBALS['fv_tax'] );
$fv_v = fv_moved( 'A6 delete', $fv_v );

$fv_tag = wp_insert_term( 'FV not a folder', 'post_tag', array( 'slug' => 'fv-not-a-folder' ) );
fv_check( 'A7 a term that is not a folder leaves it alone', vergeml_folders_version() === $fv_v, sprintf( 'was %d, now %d', $fv_v, vergeml_folders_version() ) );

/* ------------------------------------------- B  a Move done, an undo done */

fv_say( "\nB  a Move completing, an undo completing\n" );

if ( function_exists( 'vergeml_talk_refile_finish' ) ) {
    $fv_state = array( 'taxonomy' => $GLOBALS['fv_tax'], 'remove' => array(), 'active' => true );
    $fv_v     = vergeml_folders_version();
    vergeml_talk_refile_finish( $fv_state );
    fv_check( 'B1 the guide\'s re-filing, finishing, moves it', vergeml_folders_version() > $fv_v && false === $fv_state['active'] );
} else {
    fv_check( 'B1 core/folder-talk.php is loaded', false );
}

$fv_talk = file_get_contents( WP_PLUGIN_DIR . '/vergelabs-media-library/core/folder-talk.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$fv_lib  = file_get_contents( WP_PLUGIN_DIR . '/vergelabs-media-library/core/librarian.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
fv_check( 'B2 the guide\'s undo calls it', false !== strpos( $fv_talk, 'vergeml_folders_moved( \'undo\' )' ) );
fv_check( 'B3 a Librarian batch done calls it', false !== strpos( $fv_lib, 'vergeml_folders_moved( \'librarian\' )' ) );
fv_check( 'B4 a Librarian batch undone calls it', false !== strpos( $fv_lib, 'vergeml_folders_moved( \'librarian-undo\' )' ) );

$fv_v = vergeml_folders_version();
vergeml_folders_moved( 'suite' );
fv_check( 'B5 and what it calls moves the stamp', vergeml_folders_version() === $fv_v + 1 );

/* -------------------------------------------------------- C  the route */

fv_say( "\nC  GET /vergeml/v1/folders/version\n" );

$fv_routes = rest_get_server()->get_routes( 'vergeml/v1' );
fv_check( 'the route is registered', isset( $fv_routes['/vergeml/v1/folders/version'] ) );

$fv_req = new WP_REST_Request( 'GET', '/vergeml/v1/folders/version' );
$fv_res = rest_do_request( $fv_req );
$fv_data = $fv_res->get_data();
fv_check( 'it answers 200 with { version }', 200 === $fv_res->get_status() && is_array( $fv_data ) && isset( $fv_data['version'] ) && is_int( $fv_data['version'] ), wp_json_encode( $fv_data ) );
fv_check( 'and the number is the stamp', isset( $fv_data['version'] ) && $fv_data['version'] === vergeml_folders_version() );
fv_check( 'it is never cached', 'no-store' === $fv_res->get_headers()['Cache-Control'] );

wp_set_current_user( 0 );
$fv_anon = rest_do_request( new WP_REST_Request( 'GET', '/vergeml/v1/folders/version' ) );
fv_check( 'a visitor is refused', in_array( $fv_anon->get_status(), array( 401, 403 ), true ), (string) $fv_anon->get_status() );
wp_set_current_user( 1 );

/* -------------------------------------------- D  Gate 5: one option read */

fv_say( "\nD  the poll costs one option read\n" );

$fv_wpdb = $GLOBALS['wpdb'];

wp_cache_delete( VERGEML_FOLDERS_VERSION, 'options' );
wp_cache_delete( 'notoptions', 'options' );

$fv_before = $fv_wpdb->num_queries;
vergeml_rest_folders_version();
$fv_cold = $fv_wpdb->num_queries - $fv_before;
fv_check( 'D1 cold: exactly one query, the option', 1 === $fv_cold, $fv_cold . ' queries' );

$fv_before = $fv_wpdb->num_queries;
vergeml_rest_folders_version();
$fv_warm = $fv_wpdb->num_queries - $fv_before;
fv_check( 'D2 warm: none', 0 === $fv_warm, $fv_warm . ' queries' );

$fv_autoload = $fv_wpdb->get_var( $fv_wpdb->prepare( "SELECT autoload FROM {$fv_wpdb->options} WHERE option_name = %s", VERGEML_FOLDERS_VERSION ) );
fv_check( 'D3 the option is not autoloaded, so no other request pays for it', in_array( $fv_autoload, array( 'no', 'off', 'auto-off' ), true ), (string) $fv_autoload );

/* ------------------------------------------------------------- cleanup */

fv_cleanup();
fv_check( 'the folders this suite made are gone', ! get_term_by( 'slug', 'fv-root', $GLOBALS['fv_tax'] ) && ! get_term_by( 'slug', 'fv-other', $GLOBALS['fv_tax'] ) );

fv_say( sprintf( "\n%d/%d passed\n", $GLOBALS['fv_pass'], $GLOBALS['fv_pass'] + $GLOBALS['fv_fail'] ) );

@file_put_contents( __DIR__ . '/folders-version-last-run.txt', $GLOBALS['fv_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['fv_fail'] > 0 ) {
    exit( 1 );
}
