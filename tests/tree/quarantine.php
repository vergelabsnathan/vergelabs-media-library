<?php
/**
 *  Setting aside: a test that the file survives it.
 *
 *  Almost everything worth asserting here is a negative. The file is still on
 *  disk. Its URL still resolves. Nothing in the plugin can delete it. The
 *  wait cannot be skipped, and taking something back is never delayed by the
 *  wait that protects it.
 *
 *      wp eval-file tests/tree/quarantine.php --allow-root
 *
 *  or through tests/tree/quarantine-blueprint.json in Playground.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_quarantine_add' ) ) {
    echo "core/quarantine.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

global $wpdb;

$q_pass = 0;
$q_fail = 0;
$q_log  = '';

function q_say( $line ) {
    global $q_log;
    $q_log .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function q_check( $label, $ok, $note = '' ) {
    global $q_pass, $q_fail;
    if ( $ok ) {
        $q_pass++;
    } else {
        $q_fail++;
    }
    q_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

function q_report() {
    global $q_log;
    @file_put_contents( __DIR__ . '/quarantine-last-run.txt', $q_log ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}


q_say( "\nsetting aside\n\n" );

$q_posts = array();

for ( $i = 0; $i < 3; $i++ ) {
    $q_posts[] = (int) wp_insert_post( array(
        'post_title'     => 'zz quarantine ' . $i,
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/png',
    ) );
}

$q_one = $q_posts[0];

q_check( 'three files seeded', 3 === count( $q_posts ) );


/* ------------------------------------------------------------ setting aside */

q_say( "setting one aside\n" );

$q_url_before = wp_get_attachment_url( $q_one );

vergeml_quarantine_add( $q_one, 'no references found in posts and widgets' );

q_check( 'it is marked', true === vergeml_quarantine_has( $q_one ) );

q_check( 'the post still exists', get_post( $q_one ) instanceof WP_Post,
    'setting aside is a mark, not a removal' );

q_check( 'its URL is unchanged', wp_get_attachment_url( $q_one ) === $q_url_before,
    'anything already using it goes on working' );

q_check( 'the reason was kept as written',
    'no references found in posts and widgets' === get_post_meta( $q_one, VERGEML_QUARANTINE_REASON, true ) );

q_check( 'it is not eligible yet', false === vergeml_quarantine_eligible( $q_one ),
    'the wait starts now' );


/* ------------------------------------------------------------- out of sight */

q_say( "\nout of the library, and only there\n" );

$q_visible = new WP_Query( array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'posts_per_page' => 50,
    'fields'         => 'ids',
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
    'meta_query'     => array( array( 'key' => VERGEML_QUARANTINE_META, 'compare' => 'NOT EXISTS' ) ),
) );

q_check( 'the media library query no longer returns it',
    ! in_array( $q_one, array_map( 'intval', $q_visible->posts ), true ) );

q_check( 'but the other two are still there',
    in_array( $q_posts[1], array_map( 'intval', $q_visible->posts ), true ) );

$q_folder_args = vergeml_smart_query_args( 'quarantine' );

q_check( 'the "Set aside" folder knows what it means',
    isset( $q_folder_args['meta_query'] ),
    'a row with a count and nothing behind it would be worse than no row' );

$q_shown = new WP_Query( array_merge( array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'posts_per_page' => 50,
    'fields'         => 'ids',
), $q_folder_args ) );

q_check( 'and that folder does show it',
    in_array( $q_one, array_map( 'intval', $q_shown->posts ), true ) );


/* ---------------------------------------------------------------- the wait */

q_say( "\nthe wait\n" );

q_check( 'the floor is thirty days', 30 === VERGEML_QUARANTINE_DAYS );

// Backdate it by a fortnight: still waiting.
update_post_meta( $q_one, VERGEML_QUARANTINE_META, time() - ( 14 * DAY_IN_SECONDS ) );

q_check( 'a fortnight is not enough', false === vergeml_quarantine_eligible( $q_one ) );

update_post_meta( $q_one, VERGEML_QUARANTINE_META, time() - ( 31 * DAY_IN_SECONDS ) );

q_check( 'thirty-one days is', true === vergeml_quarantine_eligible( $q_one ) );

q_check( 'and eligible still does not mean gone', get_post( $q_one ) instanceof WP_Post,
    'nothing in this plugin turns the wait being over into a deletion' );


/* -------------------------------------------------------------- the manifest */

q_say( "\nthe manifest\n" );

$q_manifest = vergeml_quarantine_manifest();

q_check( 'it lists what is set aside', 1 === (int) $q_manifest['count'], $q_manifest['count'] . ' listed' );

q_check( 'it names the site and when it was made',
    ! empty( $q_manifest['site'] ) && ! empty( $q_manifest['generated'] ) );

q_check( 'it says the files are not deleted, in words',
    false !== strpos( $q_manifest['note'], 'not deleted' ) );

q_check( 'it says what "no references" actually means',
    false !== strpos( $q_manifest['note'], 'not proof' ),
    'a manifest that asks to be trusted is the wrong kind of manifest' );

q_check( 'each row carries enough to check against a backup',
    isset( $q_manifest['files'][0]['path'] ) && isset( $q_manifest['files'][0]['id'] ) );


/* --------------------------------------------------------------- taking back */

q_say( "\ntaking it back\n" );

vergeml_quarantine_release( $q_one );

q_check( 'it is no longer marked', false === vergeml_quarantine_has( $q_one ) );

q_check( 'and the reason went with it',
    '' === (string) get_post_meta( $q_one, VERGEML_QUARANTINE_REASON, true ) );

// And a fresh one, taken back immediately: the wait must never delay this.
vergeml_quarantine_add( $q_posts[1], 'test' );
vergeml_quarantine_release( $q_posts[1] );

q_check( 'taking back is never delayed by the wait',
    false === vergeml_quarantine_has( $q_posts[1] ),
    'a delay that protects you must not also trap you' );


/* ------------------------------------------------------ there is no delete */

q_say( "\nthere is no delete\n" );

$q_source = file_get_contents( dirname( __DIR__, 2 ) . '/core/quarantine.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

/*
 *  Asserted against the source rather than by trying to delete something,
 *  because the claim is not "deleting is hard to reach" but "there is nothing
 *  here that deletes".
 *
 *  Tokenised rather than grepped, and that distinction earned itself: the
 *  file's own header promises it calls none of these, by name, so a plain
 *  strpos matched the promise and reported it as the thing it promised not to
 *  do. Comments and strings are dropped; what is left is what runs.
 */
$q_code = '';

foreach ( token_get_all( $q_source ) as $q_token ) {

    if ( is_array( $q_token ) ) {

        if ( in_array( $q_token[0], array( T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_INLINE_HTML ), true ) ) {
            continue;
        }

        $q_code .= $q_token[1];
        continue;
    }

    $q_code .= $q_token;
}

foreach ( array( 'wp_delete_attachment', 'wp_delete_post', 'unlink', 'wp_delete_file', 'MEDIA_TRASH' ) as $q_forbidden ) {
    q_check( 'nothing in core/quarantine.php calls ' . $q_forbidden,
        false === strpos( $q_code, $q_forbidden ) );
}

$q_bad = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/quarantine-act' );
$q_bad->set_param( 'ids', array( $q_posts[2] ) );
$q_bad->set_param( 'action', 'delete' );

wp_set_current_user( 1 );

$q_response = rest_do_request( $q_bad );

q_check( 'the endpoint refuses any action but the two',
    400 === (int) $q_response->get_status(),
    'status ' . $q_response->get_status() );

q_check( 'and the file it was asked about is untouched',
    get_post( $q_posts[2] ) instanceof WP_Post );


/* -------------------------------------------------------------------- tidy */

q_say( "\ntidying up\n" );

foreach ( $q_posts as $id ) {
    if ( get_post( $id ) ) {
        wp_delete_post( $id, true );
    }
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$q_left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE 'zz quarantine%' AND post_type = 'attachment'" );

q_check( 'the seeded files are gone', 0 === $q_left, $q_left . ' left behind' );

q_say( sprintf( "\n%d/%d passed\n", $q_pass, $q_pass + $q_fail ) );

q_report();

if ( $q_fail > 0 ) {
    exit( 1 );
}
