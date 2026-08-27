<?php
/**
 *  The rest of Phase 5: merging that does not merge, alt text that does not
 *  overwrite, and similarity that costs nothing.
 *
 *      wp eval-file tests/tree/utilities.php --allow-root
 *
 *  or through tests/tree/utilities-blueprint.json in Playground.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_alt_fill_step' ) ) {
    echo "core/utilities.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

global $wpdb;

$u_pass = 0;
$u_fail = 0;
$u_log  = '';

function u_say( $line ) {
    global $u_log;
    $u_log .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function u_check( $label, $ok, $note = '' ) {
    global $u_pass, $u_fail;
    if ( $ok ) {
        $u_pass++;
    } else {
        $u_fail++;
    }
    u_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

function u_report() {
    global $u_log;
    @file_put_contents( __DIR__ . '/utilities-last-run.txt', $u_log ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}


$u_posts   = array();
$GLOBALS['u_vectors'] = array();

/*
 *  Vectors through the seam, for the reason tests/tree/auto-file.php explains:
 *  Playground's SQLite layer cannot store packed floats at all.
 */
add_filter( 'vergeml_autofile_vector', function ( $vector, $attachment_id ) {
    return isset( $GLOBALS['u_vectors'][ (int) $attachment_id ] )
        ? $GLOBALS['u_vectors'][ (int) $attachment_id ]
        : $vector;
}, 10, 2 );

u_say( "\nthe rest of the utilities\n\n" );

vergeml_index_install();


function u_make( $title, $alt_in_index = '', $vector = null, $locked = '' ) {

    global $u_posts;

    $id = (int) wp_insert_post( array(
        'post_title'     => 'zz util ' . $title,
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/png',
    ) );

    $u_posts[] = $id;

    $data = array( 'caption' => 'a seeded caption', 'described_at' => gmdate( 'Y-m-d H:i:s' ) );

    if ( '' !== $alt_in_index ) {
        $data['alt'] = $alt_in_index;
    }

    if ( '' !== $locked ) {
        $data['locked'] = $locked;
    }

    vergeml_index_set( $id, $data );

    if ( null !== $vector ) {
        $GLOBALS['u_vectors'][ $id ] = $vector;
    }

    return $id;
}


/* ---------------------------------------------------------------- alt text */

u_say( "filling in alt text\n" );

$u_empty  = u_make( 'no-alt', 'a described alt' );
$u_has    = u_make( 'has-alt', 'index alt' );
$u_locked = u_make( 'locked-alt', 'index alt', null, 'alt' );

update_post_meta( $u_has, '_wp_attachment_image_alt', 'written by a person' );

$u_step = vergeml_alt_fill_step( 50 );

u_check( 'it filled the empty one', $u_step['filled'] >= 1, $u_step['filled'] . ' filled' );

u_check( 'the empty one now has the described alt',
    'a described alt' === get_post_meta( $u_empty, '_wp_attachment_image_alt', true ),
    (string) get_post_meta( $u_empty, '_wp_attachment_image_alt', true ) );

u_check( 'a person\'s own words were not touched',
    'written by a person' === get_post_meta( $u_has, '_wp_attachment_image_alt', true ),
    'it was never empty, so it was never a candidate' );

u_check( 'a locked field was skipped even though it was empty',
    '' === (string) get_post_meta( $u_locked, '_wp_attachment_image_alt', true ),
    'somebody who cleared a field meant to clear it' );

u_check( 'and what it wrote is stamped',
    '1' === (string) get_post_meta( $u_empty, VERGEML_ALT_FILLED, true ) );

$u_undone = vergeml_alt_undo();

u_check( 'all of it can be taken back', $u_undone >= 1, $u_undone . ' undone' );

u_check( 'the filled one is empty again',
    '' === (string) get_post_meta( $u_empty, '_wp_attachment_image_alt', true ) );

u_check( 'and the hand-written one still says what it said',
    'written by a person' === get_post_meta( $u_has, '_wp_attachment_image_alt', true ),
    'undo removes what it wrote, not what it found' );


/* -------------------------------------------------------------- similarity */

u_say( "\nmore like this one\n" );

function u_vec( $corner, $n ) {
    $v = array_fill( 0, 8, 0.0 );
    $v[ $corner ] = 1.0 + ( $n * 0.01 );
    return $v;
}

$u_a1 = u_make( 'near-1', '', u_vec( 0, 1 ) );
$u_a2 = u_make( 'near-2', '', u_vec( 0, 2 ) );
$u_b1 = u_make( 'far-1', '', u_vec( 4, 1 ) );

$u_similar = vergeml_similar( $u_a1, 3 );

$u_ids = array();
foreach ( $u_similar as $hit ) {
    $u_ids[] = (int) $hit['id'];
}

u_check( 'the nearest file comes first',
    isset( $u_ids[0] ) && $u_a2 === $u_ids[0],
    implode( ',', $u_ids ) );

u_check( 'the far one is further down',
    ! isset( $u_ids[0] ) || $u_b1 !== $u_ids[0] );

u_check( 'it never returns the file you asked about',
    ! in_array( $u_a1, $u_ids, true ) );

u_check( 'a file with no vector gets an empty answer, not an error',
    array() === vergeml_similar( $u_has, 3 ) );


/* ------------------------------------------------------------- duplicates */

u_say( "\nmerging, which sets aside\n" );

$u_plan = array( array(
    'keep'  => $u_a1,
    'aside' => array( $u_a2 ),
) );

$u_merged = vergeml_merge_run( $u_plan );

u_check( 'the copy was set aside', 1 === $u_merged );

u_check( 'and it still exists', get_post( $u_a2 ) instanceof WP_Post,
    'merging here never deletes a file' );

u_check( 'it is marked as set aside', true === vergeml_quarantine_has( $u_a2 ) );

u_check( 'with a reason naming the copy that was kept',
    false !== strpos( (string) get_post_meta( $u_a2, VERGEML_QUARANTINE_REASON, true ), (string) $u_a1 ),
    (string) get_post_meta( $u_a2, VERGEML_QUARANTINE_REASON, true ) );

u_check( 'the keeper was left alone', false === vergeml_quarantine_has( $u_a1 ) );

$u_again = vergeml_merge_run( $u_plan );

u_check( 'running it twice sets nothing aside a second time', 0 === $u_again );

/*
 *  The same tokenised check the quarantine suite makes, for the same reason:
 *  the claim is that this file cannot delete media, and a claim like that is
 *  worth checking against the code rather than the intention.
 */
$u_source = file_get_contents( dirname( __DIR__, 2 ) . '/core/utilities.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$u_code   = '';

foreach ( token_get_all( $u_source ) as $u_token ) {
    if ( is_array( $u_token ) ) {
        if ( in_array( $u_token[0], array( T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_INLINE_HTML ), true ) ) {
            continue;
        }
        $u_code .= $u_token[1];
        continue;
    }
    $u_code .= $u_token;
}

foreach ( array( 'wp_delete_attachment', 'wp_delete_post', 'unlink', 'wp_delete_file' ) as $u_forbidden ) {
    u_check( 'nothing in core/utilities.php calls ' . $u_forbidden,
        false === strpos( $u_code, $u_forbidden ) );
}


/* -------------------------------------------------------------------- tidy */

u_say( "\ntidying up\n" );

foreach ( $u_posts as $id ) {
    if ( get_post( $id ) ) {
        vergeml_index_delete( $id );
        wp_delete_post( $id, true );
    }
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$u_left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE 'zz util%' AND post_type = 'attachment'" );

u_check( 'the seeded files are gone', 0 === $u_left, $u_left . ' left behind' );

u_say( sprintf( "\n%d/%d passed\n", $u_pass, $u_pass + $u_fail ) );

u_report();

if ( $u_fail > 0 ) {
    exit( 1 );
}
