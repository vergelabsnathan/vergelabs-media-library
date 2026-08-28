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

$GLOBALS['q_pass'] = 0;
$GLOBALS['q_fail'] = 0;
$GLOBALS['q_log']  = '';

function q_say( $line ) {
    $GLOBALS['q_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function q_check( $label, $ok, $note = '' ) {
    /*
     *  $GLOBALS, not `global`. wp eval-file evaluates this file inside a
     *  function, so the counters declared at the top of it are locals of
     *  that function and never globals at all -- `global` here bound to a
     *  second, empty pair. They stayed at zero however many checks ran, the
     *  summary read "0/0 passed", and the exit(1) below could not fire: the
     *  suite reported success no matter what failed. tests/librarian and
     *  tests/organize already do it this way, which is why theirs count.
     */
    if ( $ok ) {
        $GLOBALS['q_pass']++;
    } else {
        $GLOBALS['q_fail']++;
    }
    q_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

function q_report() {
    @file_put_contents( __DIR__ . '/quarantine-last-run.txt', $GLOBALS['q_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
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

/*
 *  That it lists ours, not that ours is the only one on the site.
 *
 *  vergeml_quarantine_manifest() reports everything set aside anywhere -- that
 *  is its job, it is the export a site owner checks against a backup. Asserting
 *  the site holds exactly one made this check fail whenever anything else was
 *  also set aside, which tests/tree/utilities.php does on its way through
 *  merging. It reported four listed and none of the three extra were this
 *  suite's to care about.
 */
$q_listed = wp_list_pluck( $q_manifest['files'], 'id' );

q_check( 'it lists what is set aside',
    in_array( $q_one, array_map( 'intval', $q_listed ), true ),
    $q_manifest['count'] . ' listed site-wide, ours among them' );

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

/*
 *  Through the plugin's own constant, not this file's path. tools/verify.mjs
 *  copies the suite to /tmp on the box before running it, so dirname( __DIR__,
 *  2 ) was '/' and this read '//core/quarantine.php', which does not exist --
 *  leaving every check below searching an empty string and finding, of course,
 *  nothing forbidden in it.
 */
$q_source = file_get_contents( dirname( VERGEML_FILE ) . '/core/quarantine.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

q_check( 'the source of core/quarantine.php was there to read',
    is_string( $q_source ) && strlen( $q_source ) > 1000,
    is_string( $q_source ) ? strlen( $q_source ) . ' bytes' : 'could not read it' );

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
        // Released first: a file left set aside is one the next suite's
        // manifest counts, which is the failure this suite just stopped
        // making about somebody else.
        vergeml_quarantine_release( (int) $id );
        wp_delete_post( $id, true );
    }
}

/*
 *  The ids this run seeded, not the shared "zz quarantine" prefix -- for the
 *  reason tests/librarian/test-librarian.php now states: that prefix belongs to
 *  whatever else ran today as much as to this file.
 */
$q_left = 0;

foreach ( $q_posts as $id ) {
    if ( get_post( (int) $id ) ) {
        $q_left++;
    }
}

q_check( 'the seeded files are gone', 0 === $q_left, $q_left . ' left behind' );

q_say( sprintf( "\n%d/%d passed\n", $GLOBALS['q_pass'], $GLOBALS['q_pass'] + $GLOBALS['q_fail'] ) );

q_report();

if ( $GLOBALS['q_fail'] > 0 ) {
    exit( 1 );
}
