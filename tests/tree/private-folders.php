<?php
/**
 *  Folders only one person sees.
 *
 *  The claim is narrow and the suite exists to keep it narrow: the FOLDER is
 *  hidden from other people, and the FILES inside it are not. If the files ever
 *  start disappearing too, this feature has quietly turned into something that
 *  looks like access control while being none, and somebody will trust it with
 *  a picture they should not have trusted it with.
 *
 *      wp eval-file tests/tree/private-folders.php --allow-root
 *
 *  Seeds its own users, folders and files under zzpf, and removes all of them.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_private_set' ) ) {
    echo "core/private-folders.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

global $wpdb;

$GLOBALS['pf_pass'] = 0;
$GLOBALS['pf_fail'] = 0;
$GLOBALS['pf_log']  = '';

function pf_say( $line ) {
    $GLOBALS['pf_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function pf_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['pf_pass']++;
    } else {
        $GLOBALS['pf_fail']++;
    }
    pf_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

/** Folder ids this user's folder queries return. */
function pf_visible( $taxonomy ) {
    $ids = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
    return is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
}


pf_say( "\nfolders only one person sees\n\n" );

$pf_tax    = 'media_category';
$pf_before = get_option( 'vergeml_private_folders', array() );

delete_option( 'vergeml_private_folders' );

$pf_users = array();
$pf_terms = array();
$pf_file  = 0;


/* ------------------------------------------------------------ two people */

pf_say( "A  two editors and an administrator\n" );

foreach ( array( 'zzpf-anna' => 'editor', 'zzpf-bob' => 'editor', 'zzpf-admin' => 'administrator' ) as $pf_login => $pf_role ) {

    $pf_id = username_exists( $pf_login );

    if ( ! $pf_id ) {
        $pf_id = wp_insert_user( array(
            'user_login' => $pf_login,
            'user_pass'  => wp_generate_password( 24 ),
            'user_email' => $pf_login . '@example.test',
            'role'       => $pf_role,
        ) );
    }

    if ( ! is_wp_error( $pf_id ) ) {
        $pf_users[ $pf_login ] = (int) $pf_id;
    }
}

pf_check( 'three users to work with', 3 === count( $pf_users ), wp_json_encode( $pf_users ) );

if ( 3 !== count( $pf_users ) ) {
    pf_say( sprintf( "\n%d/%d passed\n", $GLOBALS['pf_pass'], $GLOBALS['pf_pass'] + $GLOBALS['pf_fail'] ) );
    exit( 1 );
}


/* ------------------------------------------------- a folder, and a file in it */

pf_say( "\nB  a folder of Anna's, with a picture in it\n" );

wp_set_current_user( $pf_users['zzpf-anna'] );

foreach ( array( 'zzpf Anna drafts', 'zzpf Shared' ) as $pf_name ) {

    $pf_term = wp_insert_term( $pf_name, $pf_tax );

    if ( is_wp_error( $pf_term ) ) {
        pf_check( 'seeded ' . $pf_name, false, $pf_term->get_error_message() );
        continue;
    }

    $pf_terms[ $pf_name ] = (int) $pf_term['term_id'];
}

pf_check( 'two folders seeded', 2 === count( $pf_terms ), wp_json_encode( $pf_terms ) );

$pf_file = wp_insert_post( array(
    'post_title'     => 'zzpf-img',
    'post_name'      => 'zzpf-img',
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => 'image/jpeg',
    'guid'           => 'http://example.test/zzpf-img.jpg',
) );

$pf_file = ( $pf_file && ! is_wp_error( $pf_file ) ) ? (int) $pf_file : 0;

pf_check( 'a picture to file', $pf_file > 0 );

wp_set_object_terms( $pf_file, array( $pf_terms['zzpf Anna drafts'] ), $pf_tax );

vergeml_private_set( $pf_terms['zzpf Anna drafts'], true );

pf_check(
    'the folder is now Anna\'s',
    $pf_users['zzpf-anna'] === vergeml_private_owner( $pf_terms['zzpf Anna drafts'] ),
    (string) vergeml_private_owner( $pf_terms['zzpf Anna drafts'] )
);


/* -------------------------------------------------------------- what Anna sees */

pf_say( "\nC  Anna\n" );

$pf_anna_sees = pf_visible( $pf_tax );

pf_check( 'she sees her own folder', in_array( $pf_terms['zzpf Anna drafts'], $pf_anna_sees, true ) );
pf_check( 'and the shared one', in_array( $pf_terms['zzpf Shared'], $pf_anna_sees, true ) );


/* --------------------------------------------------------------- what Bob sees */

pf_say( "\nD  Bob\n" );

wp_set_current_user( $pf_users['zzpf-bob'] );

$pf_bob_sees = pf_visible( $pf_tax );

pf_check(
    'Anna\'s folder is not in his sidebar',
    ! in_array( $pf_terms['zzpf Anna drafts'], $pf_bob_sees, true ),
    'that is the whole feature'
);
pf_check( 'the shared folder still is', in_array( $pf_terms['zzpf Shared'], $pf_bob_sees, true ) );

/*
 *  THE ASSERTION THIS FILE EXISTS FOR.
 *
 *  The picture inside Anna's folder is still Bob's to find. A folder is a
 *  label; hiding the label must not hide the thing. If this ever fails, the
 *  feature has become something that looks like access control -- and it is
 *  not, and cannot be: the file is still reachable by URL and through the
 *  REST API however the library query behaves.
 */
$pf_found = get_posts( array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'name'           => 'zzpf-img',
) );

pf_check(
    'THE POINT: the picture inside it is still his to find',
    in_array( $pf_file, array_map( 'intval', $pf_found ), true ),
    'hiding a folder must never hide a file'
);

// And he cannot take it over, even though he can name the id.
$pf_steal = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/folder-privacy' );
$pf_steal->set_param( 'id', $pf_terms['zzpf Anna drafts'] );
$pf_steal->set_param( 'private', false );

$pf_refused = vergeml_private_rest( $pf_steal );

pf_check(
    'he cannot share out a folder that is not his',
    is_wp_error( $pf_refused ) && 'vergeml_not_yours' === $pf_refused->get_error_code(),
    is_wp_error( $pf_refused ) ? $pf_refused->get_error_code() : 'it let him'
);


/* ------------------------------------------------------- what an admin sees */

pf_say( "\nE  an administrator\n" );

wp_set_current_user( $pf_users['zzpf-admin'] );

$pf_admin_sees = pf_visible( $pf_tax );

pf_check(
    'sees it, so a deleted user\'s folder can still be tidied up',
    in_array( $pf_terms['zzpf Anna drafts'], $pf_admin_sees, true )
);

$pf_node = vergeml_private_node( $pf_terms['zzpf Anna drafts'] );

pf_check( 'and is told whose it is', is_array( $pf_node ) && false === $pf_node['mine'] && '' !== $pf_node['who'], wp_json_encode( $pf_node ) );

// Even an administrator does not silently take a folder over.
$pf_admin_try = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/folder-privacy' );
$pf_admin_try->set_param( 'id', $pf_terms['zzpf Anna drafts'] );
$pf_admin_try->set_param( 'private', false );

$pf_admin_res = vergeml_private_rest( $pf_admin_try );

pf_check(
    'and still cannot share out somebody else\'s folder',
    is_wp_error( $pf_admin_res ),
    is_wp_error( $pf_admin_res ) ? $pf_admin_res->get_error_code() : 'it let them'
);


/* ------------------------------------------------------------- giving it back */

pf_say( "\nF  Anna shares it again\n" );

wp_set_current_user( $pf_users['zzpf-anna'] );

vergeml_private_set( $pf_terms['zzpf Anna drafts'], false );

wp_set_current_user( $pf_users['zzpf-bob'] );

pf_check(
    'and Bob sees it',
    in_array( $pf_terms['zzpf Anna drafts'], pf_visible( $pf_tax ), true )
);


/* ---------------------------------------------------------- deleting a folder */

pf_say( "\nG  the map does not leak\n" );

wp_set_current_user( $pf_users['zzpf-anna'] );
vergeml_private_set( $pf_terms['zzpf Shared'], true );

pf_check( 'the second folder is private', 0 !== vergeml_private_owner( $pf_terms['zzpf Shared'] ) );

wp_delete_term( $pf_terms['zzpf Shared'], $pf_tax );

/*
 *  A deleted folder must take its entry with it. Term ids are reused, so a
 *  stale id would eventually start hiding a folder made by somebody with no
 *  connection to the original at all.
 */
pf_check(
    'deleting the folder forgets it',
    0 === vergeml_private_owner( $pf_terms['zzpf Shared'] ),
    wp_json_encode( vergeml_private_map() )
);


/* ------------------------------------------------------------------ tidying */

pf_say( "\ntidying up\n" );

wp_set_current_user( 0 );

if ( $pf_file > 0 ) {
    wp_delete_post( $pf_file, true );
}

foreach ( $pf_terms as $pf_id ) {
    wp_delete_term( $pf_id, $pf_tax );
}

if ( ! function_exists( 'wp_delete_user' ) ) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}

foreach ( $pf_users as $pf_id ) {
    wp_delete_user( $pf_id );
}

update_option( 'vergeml_private_folders', $pf_before, true );

pf_check( 'the seeded users are gone', 0 === count( array_filter( $pf_users, 'get_userdata' ) ), '' );
pf_check( 'and the map is back as it was', get_option( 'vergeml_private_folders', array() ) === $pf_before );

pf_say( sprintf( "\n%d/%d passed\n", $GLOBALS['pf_pass'], $GLOBALS['pf_pass'] + $GLOBALS['pf_fail'] ) );

@file_put_contents( __DIR__ . '/private-folders-last-run.txt', $GLOBALS['pf_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['pf_fail'] > 0 ) {
    exit( 1 );
}
