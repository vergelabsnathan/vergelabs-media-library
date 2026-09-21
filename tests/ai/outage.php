<?php
/**
 *  What a describe run does while the service is down.
 *
 *      node tools/verify.mjs ai-outage        # Playground, WordPress booted, the checkout mounted
 *      wp eval-file tests/ai/outage.php --allow-root
 *
 *  The line behind the readme's "What happens when the AI service is down?"
 *  (E3-2, 2026-09-21): the paragraph was first written from line references
 *  and overstated the pass. This pins what core/ai.php does today, so the
 *  words can say it -- and it is the red test E3-1 starts from.
 *
 *  Two pictures, a stand-in service, four steps of the 'unindexed' pass, one
 *  picture per step:
 *
 *    1. the service cannot be reached (a WP_Error from the transport):
 *       picture A is marked as failed on the first miss -- no hold.
 *    2. the service answers 503: picture B is held, no row written.
 *    3. 503 again (the hold lifted, as ten minutes would): held again.
 *    4. 503 a third time: marked as failed with the status on it.
 *
 *  Then the scopes: neither picture is in 'unindexed' any more; both are in
 *  'missing-alt', which is the one button that reaches them.
 *
 *  ## No request leaves this suite
 *
 *  pre_http_request answers every call. The describe endpoint gets the
 *  scripted answer and is counted -- one call per step is asserted, so a
 *  step that described nothing (a picture still held, a payload that failed
 *  before the request) cannot pass as "no row written". Anything else gets
 *  an empty 200.
 *
 *  ## What it touches, and puts back
 *
 *  vergeml_ai (a sealed placeholder key goes in), the vergeml_ai_recent and
 *  vergeml_ai_strikes transients, two attachments of its own from literal
 *  PNG bytes (no GD needed -- Playground may not have it, and an image error
 *  would mark the picture for the wrong reason) and their index rows. All
 *  removed from a shutdown function.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

foreach ( array( 'vergeml_ai_index_step', 'vergeml_ai_pending', 'vergeml_ai_seal', 'vergeml_index_get', 'vergeml_index_delete', 'vergeml_path_in_uploads' ) as $ao_fn ) {
    if ( ! function_exists( $ao_fn ) ) {
        echo "the plugin is not loaded, or is in safe mode: $ao_fn is missing\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit( 1 );
    }
}

// Playground says it is not production; the pass refuses to spend there unless told.
if ( ! defined( 'VERGEML_AI_ALLOW_NONPROD' ) ) {
    define( 'VERGEML_AI_ALLOW_NONPROD', true );
}

/*
 *  $GLOBALS, not `global`: wp eval-file evaluates this file inside a function,
 *  and so does the Playground runner (a require inside a runPHP step).
 */
$GLOBALS['ao_pass']     = 0;
$GLOBALS['ao_fail']     = 0;
$GLOBALS['ao_answer']   = null;   // what the describe endpoint answers next
$GLOBALS['ao_describes'] = 0;     // describe calls seen since the counter was read
$GLOBALS['ao_others']   = array();
$GLOBALS['ao_before']   = array();
$GLOBALS['ao_ids']      = array();
$GLOBALS['ao_done']     = false;

function ao_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['ao_pass']++;
    } else {
        $GLOBALS['ao_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/** The stand-in service: the describe endpoint answers as scripted, everything else with an empty 200. */
function ao_catch( $pre, $args, $url ) {

    $path = (string) wp_parse_url( $url, PHP_URL_PATH );

    if ( '/describe' !== substr( $path, -9 ) ) {
        $GLOBALS['ao_others'][] = $path;
        return array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
    }

    $GLOBALS['ao_describes']++;

    if ( 'unreachable' === $GLOBALS['ao_answer'] ) {
        // What wp_remote_post() returns when nothing answers: DNS, refused, timed out.
        return new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' );
    }

    return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => (int) $GLOBALS['ao_answer'], 'message' => 'Service Unavailable' ), 'cookies' => array(), 'filename' => null );
}

/** Describe calls since the last read; the counter starts again. */
function ao_describes() {
    $n = $GLOBALS['ao_describes'];
    $GLOBALS['ao_describes'] = 0;
    return $n;
}

/** A 1×1 PNG on disk, attached: enough for getimagesize() and small enough to go out as it is. */
function ao_make( $name ) {

    $uploads = wp_upload_dir();
    $path    = trailingslashit( $uploads['path'] ) . $name;

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) );

    $id = wp_insert_attachment( array(
        'post_mime_type' => 'image/png',
        'post_title'     => 'zz outage suite ' . $name,
        'post_status'    => 'inherit',
    ), $path );

    wp_update_attachment_metadata( $id, array(
        'width'  => 1,
        'height' => 1,
        'file'   => _wp_relative_upload_path( $path ),
        'sizes'  => array(),
    ) );

    $GLOBALS['ao_ids'][] = (int) $id;

    return (int) $id;
}

function ao_restore() {

    if ( $GLOBALS['ao_done'] ) {
        return;
    }
    $GLOBALS['ao_done'] = true;

    remove_filter( 'pre_http_request', 'ao_catch', 1 );

    foreach ( $GLOBALS['ao_ids'] as $id ) {
        vergeml_index_delete( $id );
        wp_delete_attachment( $id, true );
    }

    if ( array_key_exists( 'vergeml_ai', $GLOBALS['ao_before'] ) ) {
        if ( false === $GLOBALS['ao_before']['vergeml_ai'] ) {
            delete_option( 'vergeml_ai' );
        } else {
            update_option( 'vergeml_ai', $GLOBALS['ao_before']['vergeml_ai'], false );
        }
    }

    foreach ( array( 'vergeml_ai_recent', 'vergeml_ai_strikes' ) as $t ) {
        if ( array_key_exists( $t, $GLOBALS['ao_before'] ) ) {
            if ( false === $GLOBALS['ao_before'][ $t ] ) {
                delete_transient( $t );
            } else {
                set_transient( $t, $GLOBALS['ao_before'][ $t ], HOUR_IN_SECONDS );
            }
        }
    }
}

/** One step of the pass on one picture, the hold lifted first as ten minutes would. */
function ao_step( $answer ) {
    delete_transient( 'vergeml_ai_recent' );
    $GLOBALS['ao_answer'] = $answer;
    return vergeml_ai_index_step( 'unindexed', 1, false );
}

/** The index row's error code, or '' for no row. */
function ao_error( $id ) {
    $row = vergeml_index_get( $id );
    return $row ? (string) $row['error'] : '';
}


/* ------------------------------------------------------------- the stage */

wp_set_current_user( 1 );

$GLOBALS['ao_before']['vergeml_ai']        = get_option( 'vergeml_ai', false );
$GLOBALS['ao_before']['vergeml_ai_recent'] = get_transient( 'vergeml_ai_recent' );
$GLOBALS['ao_before']['vergeml_ai_strikes'] = get_transient( 'vergeml_ai_strikes' );

register_shutdown_function( 'ao_restore' );
add_filter( 'pre_http_request', 'ao_catch', 1, 3 );
// The sequential path, through wp_remote_post(), where pre_http_request answers.
add_filter( 'vergeml_ai_parallel', function () { return 1; } );

$ao_ai = is_array( $GLOBALS['ao_before']['vergeml_ai'] ) ? $GLOBALS['ao_before']['vergeml_ai'] : array();
$ao_ai['license_key'] = vergeml_ai_seal( 'VGML-' . strtoupper( wp_generate_password( 30, false, false ) ) . 'ZZZZ' );
$ao_ai['mock']        = 0;
update_option( 'vergeml_ai', $ao_ai, false );
delete_transient( 'vergeml_ai_strikes' );

echo "\nai-outage: what a describe run does while the service is down\n\n";

ao_check( 'the pass is ready: a key is set and mock is off', vergeml_ai_ready() && empty( vergeml_ai_settings()['mock'] ) && ! defined( 'VERGEML_AI_MOCK' ) );

$ao_a = ao_make( 'zz-outage-a.png' );
$ao_b = ao_make( 'zz-outage-b.png' );

ao_check( 'two pictures of its own, both in the unindexed backlog', $ao_a > 0 && $ao_b > 0 && array( $ao_a, $ao_b ) === array_values( array_intersect( vergeml_ai_pending( 'unindexed' ), array( $ao_a, $ao_b ) ) ), "$ao_a, $ao_b" );
ao_check( 'neither has an index row', '' === ao_error( $ao_a ) && null === vergeml_index_get( $ao_a ) && null === vergeml_index_get( $ao_b ) );


/* ------------------------------------ 1. the service cannot be reached */

echo "\n1  the service cannot be reached\n";

$ao_r1 = ao_step( 'unreachable' );

ao_check( 'one describe call went out', 1 === ao_describes() );
ao_check( 'the step reports the picture as an error, not fatal', 1 === count( $ao_r1['errors'] ) && $ao_a === (int) $ao_r1['errors'][0]['id'] && empty( $ao_r1['errors'][0]['fatal'] ), wp_json_encode( $ao_r1['errors'] ) );
ao_check( 'picture A is marked as failed on the first miss', 'http_request_failed' === ao_error( $ao_a ), "error = '" . ao_error( $ao_a ) . "'" );
ao_check( 'nothing was described', empty( $ao_r1['described'] ) );


/* --------------------------------------- 2-4. the service answers 503 */

echo "\n2  the service answers 503\n";

$ao_r2 = ao_step( 503 );

ao_check( 'one describe call went out', 1 === ao_describes() );
/*
 *  Reported at least once, never fatal. It is in fact reported twice -- once
 *  by the general report before the transient branch, once inside it
 *  (core/ai.php, 2026-09-21) -- so the screen's error count over-reads while
 *  the service answers 5xx. That is E3-1's to fix, with the transport branch;
 *  this suite says the count and does not pin it.
 */
$ao_b_errors = array_filter( $ao_r2['errors'], function ( $e ) use ( $ao_b ) { return $ao_b === (int) $e['id']; } );
ao_check( 'picture B is held: reported, not fatal', count( $ao_b_errors ) >= 1 && count( $ao_b_errors ) === count( $ao_r2['errors'] ) && ! array_filter( $ao_b_errors, function ( $e ) { return ! empty( $e['fatal'] ); } ), 'reported ' . count( $ao_b_errors ) . ' time(s)' );
ao_check( 'no row written for picture B', null === vergeml_index_get( $ao_b ) );
ao_check( 'B is off the table for ten minutes', array( $ao_b ) === vergeml_ai_recently_described( array( $ao_b ) ) );
$ao_strikes = get_transient( 'vergeml_ai_strikes' );
ao_check( 'one strike against B', is_array( $ao_strikes ) && 1 === (int) ( $ao_strikes[ $ao_b ] ?? 0 ), wp_json_encode( $ao_strikes ) );

echo "\n3  503 again, the hold lifted\n";

$ao_r3 = ao_step( 503 );

ao_check( 'one describe call went out', 1 === ao_describes() );
ao_check( 'still no row for picture B', null === vergeml_index_get( $ao_b ) );
$ao_strikes = get_transient( 'vergeml_ai_strikes' );
ao_check( 'two strikes against B', is_array( $ao_strikes ) && 2 === (int) ( $ao_strikes[ $ao_b ] ?? 0 ), wp_json_encode( $ao_strikes ) );

echo "\n4  503 a third time\n";

$ao_r4 = ao_step( 503 );

ao_check( 'one describe call went out', 1 === ao_describes() );
ao_check( 'picture B is marked as failed with the status on it', 'vergeml_ai_service_503' === ao_error( $ao_b ), "error = '" . ao_error( $ao_b ) . "'" );
ao_check( 'the step reports it as an error, not fatal', 1 === count( $ao_r4['errors'] ) && empty( $ao_r4['errors'][0]['fatal'] ) );
$ao_strikes = get_transient( 'vergeml_ai_strikes' );
ao_check( 'the strikes against B are cleared with the stub', ! is_array( $ao_strikes ) || ! isset( $ao_strikes[ $ao_b ] ) );


/* ------------------------------------------- what reaches them after */

echo "\n5  the scopes afterwards\n";

$ao_step5 = ao_step( 503 );
ao_check( 'a marked picture is not offered again by itself: the next step describes nothing', 0 === ao_describes() && empty( $ao_step5['described'] ) && empty( $ao_step5['errors'] ) );
ao_check( "neither is in 'unindexed'", array() === array_values( array_intersect( vergeml_ai_pending( 'unindexed' ), array( $ao_a, $ao_b ) ) ) );
ao_check( "both are in 'missing-alt' -- the Alt text button reaches them", array( $ao_a, $ao_b ) === array_values( array_intersect( vergeml_ai_pending( 'missing-alt' ), array( $ao_a, $ao_b ) ) ) );
ao_check( 'no request other than describe was answered', array() === $GLOBALS['ao_others'], implode( ', ', $GLOBALS['ao_others'] ) );

ao_restore();

ao_check( 'the two pictures and their rows are gone', null === get_post( $ao_a ) && null === get_post( $ao_b ) && null === vergeml_index_get( $ao_a ) && null === vergeml_index_get( $ao_b ) );
ao_check( 'the settings are back as found', get_option( 'vergeml_ai', false ) === $GLOBALS['ao_before']['vergeml_ai'] );

printf( "\n%d/%d passed\n\n", $GLOBALS['ao_pass'], $GLOBALS['ao_pass'] + $GLOBALS['ao_fail'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

exit( $GLOBALS['ao_fail'] > 0 ? 1 : 0 );
