<?php
/**
 *  What a describe run does while the service is away.
 *
 *      node tools/verify.mjs ai-outage        # Playground, WordPress booted, the checkout mounted
 *
 *  Playground only: the pass takes the library's backlog lowest id first, so
 *  on a site with any other undescribed picture step 1 would take that
 *  picture, not this suite's. The suite refuses (exit 2, SKIPPED) unless the
 *  backlog is exactly its own pictures.
 *
 *  The line behind the readme's "What happens when the AI service is down?".
 *  First written 2026-09-21 (E3-2) to pin what the code did then -- marked on
 *  the first miss when unreachable, on the third 503 -- and rewritten the
 *  next day as E3-1's proof: nothing is marked for a failure that is not the
 *  file's. Six pictures, a stand-in service, the 'unindexed' pass one
 *  picture per step unless said otherwise, then the way back:
 *
 *     1. the service cannot be reached (WP_Error from the transport, the
 *        sequential path): picture A is held, no row, reported once.
 *     2. the same through the parallel path's seam ('vergeml_ai_transport'):
 *        picture E is held, no row.
 *   3-5. 503 three times running: picture B is held every time, no row,
 *        no strikes kept.
 *     6. four transients in one step end the step: of four pictures offered,
 *        four are asked and the step breaks before the fifth.
 *     7. 400 -- the service answers that this file cannot be described:
 *        picture C is marked on the first answer.
 *     8. a described picture D, offered again and answered 400, keeps its
 *        description: error empty, stamped current (the stale sweep's own
 *        query is MySQL-only, so D is reached through 'missing-alt' here).
 *     9. the service back: the hold lifted, one 'unindexed' step describes
 *        A, B and E, alt written.
 *    10. C, marked, has no alt: 'missing-alt' reaches it.
 *    11. a background run with the service away books its next pass after
 *        the hold and does not chase it.
 *
 *  ## No request leaves this suite
 *
 *  pre_http_request answers every call. The describe endpoint gets the
 *  scripted answer and is counted -- the calls per step are asserted, so a
 *  step that described nothing cannot pass as "no row written". wp-cron.php
 *  (a run's nudge) and anything else get an empty 200.
 *
 *  ## What it touches, and puts back
 *
 *  vergeml_ai (a sealed placeholder key goes in), the vergeml_ai_recent
 *  transient, the run state and its cron event, six attachments of its own
 *  from literal PNG bytes (no GD needed) and their index rows. All removed
 *  from a shutdown function.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

foreach ( array( 'vergeml_ai_index_step', 'vergeml_ai_pending', 'vergeml_ai_seal', 'vergeml_ai_ready', 'vergeml_ai_settings', 'vergeml_ai_recently_described', 'vergeml_ai_transient', 'vergeml_index_get', 'vergeml_index_set', 'vergeml_index_delete', 'vergeml_index_current_stamp', 'vergeml_ai_run_start', 'vergeml_ai_run_tick', 'vergeml_ai_run_stop', 'vergeml_ai_run_state' ) as $ao_fn ) {
    if ( ! function_exists( $ao_fn ) ) {
        echo "the plugin is not loaded, or is in safe mode: $ao_fn is missing\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit( 1 );
    }
}

if ( ! defined( 'VERGEML_AI_ALLOW_NONPROD' ) ) {
    define( 'VERGEML_AI_ALLOW_NONPROD', true );
}

$GLOBALS['ao_pass']      = 0;
$GLOBALS['ao_fail']      = 0;
$GLOBALS['ao_answer']    = null;      // what the describe endpoint answers next
$GLOBALS['ao_describes'] = 0;         // describe calls seen since the counter was read
$GLOBALS['ao_others']    = array();
$GLOBALS['ao_handed']    = null;      // answers handed through the parallel seam, or null
$GLOBALS['ao_before']    = array();
$GLOBALS['ao_ids']       = array();
$GLOBALS['ao_done']      = false;

function ao_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['ao_pass']++;
    } else {
        $GLOBALS['ao_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/** The stand-in service. */
function ao_catch( $pre, $args, $url ) {

    $path = (string) wp_parse_url( $url, PHP_URL_PATH );

    if ( '/describe' !== substr( $path, -9 ) ) {
        if ( 'wp-cron.php' !== substr( $path, -11 ) ) {
            $GLOBALS['ao_others'][] = $path;
        }
        return array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
    }

    $GLOBALS['ao_describes']++;

    if ( 'unreachable' === $GLOBALS['ao_answer'] ) {
        return new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' );
    }

    if ( 'back' === $GLOBALS['ao_answer'] ) {
        return array( 'headers' => array(), 'body' => wp_json_encode( array( 'caption' => 'A one-pixel test picture', 'alt' => 'One pixel', 'model' => 'stand-in', 'prompt_hash' => 'p2' ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
    }

    return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => (int) $GLOBALS['ao_answer'], 'message' => 'Service Unavailable' ), 'cookies' => array(), 'filename' => null );
}

/** The parallel path's seam: hands the loop the answers a request_multiple would have brought back. */
function ao_hand( $answers, $ids ) {
    if ( null === $GLOBALS['ao_handed'] ) {
        return $answers;
    }
    $GLOBALS['ao_describes'] += count( $ids );
    $out = array();
    foreach ( $ids as $id ) {
        $out[ $id ] = $GLOBALS['ao_handed'];
    }
    return $out;
}

function ao_describes() {
    $n = $GLOBALS['ao_describes'];
    $GLOBALS['ao_describes'] = 0;
    return $n;
}

/** A 1×1 PNG on disk, attached. */
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

    wp_update_attachment_metadata( $id, array( 'width' => 1, 'height' => 1, 'file' => _wp_relative_upload_path( $path ), 'sizes' => array() ) );

    $GLOBALS['ao_ids'][] = (int) $id;

    return (int) $id;
}

function ao_restore() {

    if ( $GLOBALS['ao_done'] ) {
        return;
    }
    $GLOBALS['ao_done'] = true;

    remove_filter( 'pre_http_request', 'ao_catch', 1 );
    remove_filter( 'vergeml_ai_describe_answers', 'ao_hand', 1 );

    $state = vergeml_ai_run_state();
    if ( ! empty( $state['active'] ) ) {
        vergeml_ai_run_stop( 'outage suite' );
    }
    if ( array_key_exists( 'run', $GLOBALS['ao_before'] ) ) {
        if ( false === $GLOBALS['ao_before']['run'] ) {
            delete_option( 'vergeml_ai_run' );
        } else {
            update_option( 'vergeml_ai_run', $GLOBALS['ao_before']['run'], false );
        }
    }
    $next = wp_next_scheduled( 'vergeml_ai_run_tick' );
    if ( false !== $next ) {
        wp_unschedule_event( $next, 'vergeml_ai_run_tick' );
    }

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

    if ( array_key_exists( 'vergeml_ai_recent', $GLOBALS['ao_before'] ) ) {
        if ( false === $GLOBALS['ao_before']['vergeml_ai_recent'] ) {
            delete_transient( 'vergeml_ai_recent' );
        } else {
            set_transient( 'vergeml_ai_recent', $GLOBALS['ao_before']['vergeml_ai_recent'], VERGEML_AI_HOLD_SECONDS );
        }
    }
}

/** One step, the hold lifted first as ten minutes would. */
function ao_step( $answer, $scope = 'unindexed', $limit = 1, $apply_alt = false ) {
    delete_transient( 'vergeml_ai_recent' );
    $GLOBALS['ao_answer'] = $answer;
    return vergeml_ai_index_step( $scope, $limit, $apply_alt );
}

/** The index row's error code, or '' for no row. */
function ao_error( $id ) {
    $row = vergeml_index_get( $id );
    return $row ? (string) $row['error'] : '';
}

/** A held picture is reported exactly once, not fatal. */
function ao_reported_once( $result, $id ) {
    $mine = array_filter( $result['errors'], function ( $e ) use ( $id ) { return $id === (int) $e['id']; } );
    return 1 === count( $mine ) && 1 === count( $result['errors'] ) && empty( reset( $mine )['fatal'] );
}


/* ------------------------------------------------------------- the stage */

wp_set_current_user( 1 );

$GLOBALS['ao_before']['vergeml_ai']        = get_option( 'vergeml_ai', false );
$GLOBALS['ao_before']['vergeml_ai_recent'] = get_transient( 'vergeml_ai_recent' );
$GLOBALS['ao_before']['run']               = get_option( 'vergeml_ai_run', false );

register_shutdown_function( 'ao_restore' );
add_filter( 'pre_http_request', 'ao_catch', 1, 3 );
add_filter( 'vergeml_ai_describe_answers', 'ao_hand', 1, 2 );
add_filter( 'vergeml_ai_parallel', function () { return 1; } );

$ao_ai = is_array( $GLOBALS['ao_before']['vergeml_ai'] ) ? $GLOBALS['ao_before']['vergeml_ai'] : array();
$ao_ai['license_key'] = vergeml_ai_seal( 'VGML-' . strtoupper( wp_generate_password( 30, false, false ) ) . 'ZZZZ' );
$ao_ai['mock']        = 0;
update_option( 'vergeml_ai', $ao_ai, false );

echo "\nai-outage: what a describe run does while the service is away\n\n";

ao_check( 'the pass is ready: a key is set and mock is off', vergeml_ai_ready() && empty( vergeml_ai_settings()['mock'] ) && ! defined( 'VERGEML_AI_MOCK' ) );
ao_check( 'transient: no answer at all, on either path, and a temporary status', vergeml_ai_transient( new WP_Error( 'http_request_failed', '' ) ) && vergeml_ai_transient( new WP_Error( 'vergeml_ai_transport', '' ) ) && vergeml_ai_transient( new WP_Error( 'vergeml_ai_service_503', '' ) ) && vergeml_ai_transient( new WP_Error( 'vergeml_ai_service_429', '' ) ) );
ao_check( 'not transient: an answer about the file, a bad key, no credits', ! vergeml_ai_transient( new WP_Error( 'vergeml_ai_service_400', '' ) ) && ! vergeml_ai_transient( new WP_Error( 'vergeml_ai_service_200', '' ) ) && ! vergeml_ai_transient( new WP_Error( 'vergeml_ai_bad_license', '' ) ) && ! vergeml_ai_transient( new WP_Error( 'vergeml_ai_out_of_credits', '' ) ) );

// A, B, C, D, E, then four more for the streak (F1-F4). D gets a description below.
$ao_a = ao_make( 'zz-outage-a.png' );
$ao_b = ao_make( 'zz-outage-b.png' );
$ao_c = ao_make( 'zz-outage-c.png' );
$ao_d = ao_make( 'zz-outage-d.png' );
$ao_e = ao_make( 'zz-outage-e.png' );
$ao_f = array( ao_make( 'zz-outage-f1.png' ), ao_make( 'zz-outage-f2.png' ), ao_make( 'zz-outage-f3.png' ), ao_make( 'zz-outage-f4.png' ) );
$ao_mine = $GLOBALS['ao_ids'];

$ao_backlog = vergeml_ai_pending( 'unindexed' );
if ( $ao_mine !== $ao_backlog ) {
    ao_restore();
    echo 'SKIPPED: the unindexed backlog holds ' . count( $ao_backlog ) . " picture(s), not only this suite's; run it in Playground (node tools/verify.mjs ai-outage)\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit( 2 );
}
ao_check( 'nine pictures of its own, the whole unindexed backlog', 9 === count( $ao_mine ), implode( ', ', $ao_mine ) );

// D is already described, under an older prompt: the stale sweep's case.
vergeml_index_set( $ao_d, array( 'caption' => 'An older answer', 'alt' => 'Older', 'model' => 'stand-in', 'prompt_hash' => 'p1', 'error' => '', 'described_at' => gmdate( 'Y-m-d H:i:s', time() - 20 * MINUTE_IN_SECONDS ) ) );
ao_check( 'D holds a description under prompt p1', 'An older answer' === (string) vergeml_index_get( $ao_d )['caption'] && 'p1' === (string) vergeml_index_get( $ao_d )['prompt_hash'] );


/* ------------------------------------- 1. the service cannot be reached */

echo "\n1  the service cannot be reached (sequential path)\n";

$ao_r1 = ao_step( 'unreachable' );

ao_check( 'one describe call went out', 1 === ao_describes() );
ao_check( 'picture A is held: reported once, not fatal', ao_reported_once( $ao_r1, $ao_a ), wp_json_encode( $ao_r1['errors'] ) );
ao_check( 'no row written for A', null === vergeml_index_get( $ao_a ) );
ao_check( 'A is off the table for ten minutes', array( $ao_a ) === vergeml_ai_recently_described( array( $ao_a ) ) );
ao_check( "A is still in 'unindexed'", in_array( $ao_a, vergeml_ai_pending( 'unindexed' ), true ) );


/* ---------------------------------------- 2. the parallel path's seam */

echo "\n2  the service cannot be reached (parallel path, through the seam)\n";

// B is next by id; the seam answers for whatever the step asks.
delete_transient( 'vergeml_ai_recent' );
vergeml_ai_recently_described( array_values( array_diff( $ao_mine, array( $ao_e ) ) ), true );
$GLOBALS['ao_handed'] = new WP_Error( 'vergeml_ai_transport', 'cURL error 28: Operation timed out' );
$ao_r2 = vergeml_ai_index_step( 'unindexed', 24, false );
$GLOBALS['ao_handed'] = null;

ao_check( 'one picture asked through the seam', 1 === ao_describes() );
ao_check( 'picture E is held: reported once, not fatal, no row', ao_reported_once( $ao_r2, $ao_e ) && null === vergeml_index_get( $ao_e ), wp_json_encode( $ao_r2['errors'] ) );


/* --------------------------------------- 3-5. the service answers 503 */

echo "\n3-5  503 three times running\n";

$ao_seen = array();
foreach ( array( 3, 4, 5 ) as $ao_n ) {
    // The hold lifted each time, as ten minutes would; the same picture comes round.
    delete_transient( 'vergeml_ai_recent' );
    // Put every other picture on hold so the step offers the one under test.
    vergeml_ai_recently_described( array_values( array_diff( $ao_mine, array( $ao_b ) ) ), true );
    $GLOBALS['ao_answer'] = 503;
    $ao_r = vergeml_ai_index_step( 'unindexed', 24, false );
    $ao_asked = ao_describes();
    $ao_seen[] = array( 'asked' => $ao_asked, 'errors' => $ao_r['errors'], 'row' => ao_error( $ao_b ), 'ok' => 1 === $ao_asked && ao_reported_once( $ao_r, $ao_b ) && null === vergeml_index_get( $ao_b ) );
}
ao_check( 'B asked three times, held each time, reported once each, no row ever', array( true, true, true ) === array_column( $ao_seen, 'ok' ), wp_json_encode( $ao_seen ) );
ao_check( 'no strikes are kept', false === get_transient( 'vergeml_ai_strikes' ) );


/* ------------------------------------------ 6. four in a row end the step */

echo "\n6  four transients in a row end the step\n";

delete_transient( 'vergeml_ai_recent' );
// Five offered -- C and the four F -- so a stop after four is a stop, not the end of the list.
vergeml_ai_recently_described( array( $ao_a, $ao_b, $ao_d, $ao_e ), true );
$GLOBALS['ao_answer'] = 503;
$ao_r6 = vergeml_ai_index_step( 'unindexed', 24, false );
$ao_asked6 = ao_describes();

// The five go out as one batch; the loop stops reading answers after the fourth.
ao_check( 'five pictures offered, asked as one batch; four processed, then the step stopped', 5 === $ao_asked6 && 4 === count( $ao_r6['errors'] ), $ao_asked6 . ' asked, ' . count( $ao_r6['errors'] ) . ' reported' );
ao_check( 'none of them has a row', null === vergeml_index_get( $ao_f[0] ) && null === vergeml_index_get( $ao_f[3] ) );


/* ------------------------------------- 7. the service answers 400 */

echo "\n7  the service answers 400: this file cannot be described\n";

delete_transient( 'vergeml_ai_recent' );
vergeml_ai_recently_described( array_values( array_diff( $ao_mine, array( $ao_c ) ) ), true );
$GLOBALS['ao_answer'] = 400;
$ao_r7 = vergeml_ai_index_step( 'unindexed', 24, false );
$ao_asked7 = ao_describes();

ao_check( 'one describe call went out', 1 === $ao_asked7, $ao_asked7 . ' asked; ' . wp_json_encode( $ao_r7['errors'] ) );
ao_check( 'picture C is marked on the first answer', 'vergeml_ai_service_400' === ao_error( $ao_c ), "error = '" . ao_error( $ao_c ) . "'" );
ao_check( 'reported once, not fatal', ao_reported_once( $ao_r7, $ao_c ) );


/* ------------------------- 8. a described picture on a stale run, 400 */

echo "\n8  a Re-describe run: the service refuses a picture that holds a description\n";

// The stamp is the newest real row's prompt hash: describe A under p2 first, then D (p1) is stale.
delete_transient( 'vergeml_ai_recent' );
vergeml_ai_recently_described( array_values( array_diff( $ao_mine, array( $ao_a ) ) ), true );
$GLOBALS['ao_answer'] = 'back';
$ao_r8a = vergeml_ai_index_step( 'unindexed', 24, true );
ao_check( 'A described under prompt p2 (the stamp)', 1 === ao_describes() && 1 === count( $ao_r8a['described'] ) && 'p2' === (string) vergeml_index_current_stamp()['prompt_hash'], wp_json_encode( vergeml_index_current_stamp() ) );
/*
 *  The stale scope's own query (UTC_TIMESTAMP() - INTERVAL n SECOND) is
 *  MySQL's; Playground's SQLite refuses it, so D is reached through
 *  'missing-alt' here (D has no alt in postmeta). The branch under test is
 *  the same: a refusal for a picture that already holds a description.
 */
delete_transient( 'vergeml_ai_recent' );
vergeml_ai_recently_described( array_values( array_diff( $ao_mine, array( $ao_d ) ) ), true );
$GLOBALS['ao_answer'] = 400;
$ao_r8 = vergeml_ai_index_step( 'missing-alt', 24, false );
$ao_d_row = vergeml_index_get( $ao_d );

ao_check( 'one describe call went out', 1 === ao_describes() );
ao_check( 'D keeps its description', $ao_d_row && 'An older answer' === (string) $ao_d_row['caption'] && '' === (string) $ao_d_row['error'], "caption '" . ( $ao_d_row ? $ao_d_row['caption'] : '' ) . "', error '" . ao_error( $ao_d ) . "'" );
ao_check( 'D is stamped current (prompt p2), so the stale sweep moves on', $ao_d_row && 'p2' === (string) $ao_d_row['prompt_hash'], 'prompt_hash ' . ( $ao_d_row ? $ao_d_row['prompt_hash'] : '' ) );
ao_check( 'the failure is in the report', ao_reported_once( $ao_r8, $ao_d ) );


/* ------------------------------------------------ 9. the service back */

echo "\n9  the service back: one step after the hold lapses\n";

delete_transient( 'vergeml_ai_recent' );
$GLOBALS['ao_answer'] = 'back';
$ao_r9 = vergeml_ai_index_step( 'unindexed', 24, true );
$ao_r9_ids = array_map( function ( $d ) { return (int) $d['id']; }, $ao_r9['described'] );

ao_check( 'everything still waiting is described: B, E and the four of step 6', 6 === ao_describes() && 6 === count( $ao_r9['described'] ) && in_array( $ao_b, $ao_r9_ids, true ) && in_array( $ao_e, $ao_r9_ids, true ) && empty( $ao_r9['errors'] ), count( $ao_r9['described'] ) . ' described: ' . implode( ', ', $ao_r9_ids ) );
ao_check( 'the alt text is on B', 'One pixel' === get_post_meta( $ao_b, '_wp_attachment_image_alt', true ) );
ao_check( "'unindexed' is empty", array() === vergeml_ai_pending( 'unindexed' ) );


/* -------------------------------------------- 10. the marked one's way back */

echo "\n10  C, marked, no alt: the Alt text button reaches it\n";

$ao_missing = vergeml_ai_pending( 'missing-alt' );
ao_check( "C is in 'missing-alt'; A and B, described with alt, are not", in_array( $ao_c, $ao_missing, true ) && ! in_array( $ao_a, $ao_missing, true ) && ! in_array( $ao_b, $ao_missing, true ), implode( ', ', array_intersect( $ao_missing, $ao_mine ) ) );


/* ------------------------------- 11. a background run, service away */

echo "\n11  a background run while the service is away\n";

// A fresh backlog: A's row and the four of step 6 removed so the run has work; C stays marked.
foreach ( array( $ao_a, $ao_b, $ao_e, $ao_f[0], $ao_f[1], $ao_f[2], $ao_f[3] ) as $ao_id ) {
    vergeml_index_delete( $ao_id );
    delete_post_meta( $ao_id, '_wp_attachment_image_alt' );
}
delete_transient( 'vergeml_ai_recent' );
delete_transient( 'vergeml_ai_run_lock' );
$GLOBALS['ao_answer'] = 'unreachable';
$ao_started = vergeml_ai_run_start( 'unindexed', false, 'outage suite' );
ao_check( 'the run starts', ! is_wp_error( $ao_started ) && ! empty( vergeml_ai_run_state()['active'] ), is_wp_error( $ao_started ) ? $ao_started->get_error_message() : 'active' );

// Cron takes the event off the schedule before it calls the tick; done here by hand.
$ao_booked = wp_next_scheduled( 'vergeml_ai_run_tick' );
if ( false !== $ao_booked ) {
    wp_unschedule_event( $ao_booked, 'vergeml_ai_run_tick' );
}
$ao_t0 = time();
vergeml_ai_run_tick();
$ao_state = vergeml_ai_run_state();
$ao_next  = wp_next_scheduled( 'vergeml_ai_run_tick' );
$ao_asked = ao_describes();

ao_check( 'the tick asked, held what it asked, and the run is still active', $ao_asked >= 1 && ! empty( $ao_state['active'] ), "$ao_asked asked" );
ao_check( 'the next pass is booked after the hold, not now', false !== $ao_next && $ao_next >= $ao_t0 + VERGEML_AI_HOLD_SECONDS - 5, false === $ao_next ? 'nothing booked' : ( $ao_next - $ao_t0 ) . ' s away' );
ao_check( 'no request other than describe (and the cron nudge) was answered', array() === $GLOBALS['ao_others'], implode( ', ', $GLOBALS['ao_others'] ) );

vergeml_ai_run_stop( 'outage suite' );
ao_restore();

ao_check( 'the pictures, their rows, the run and its event are gone', null === get_post( $ao_a ) && null === get_post( $ao_f[3] ) && null === vergeml_index_get( $ao_c ) && false === wp_next_scheduled( 'vergeml_ai_run_tick' ) && empty( vergeml_ai_run_state()['active'] ) );
ao_check( 'the settings are back as found', get_option( 'vergeml_ai', false ) === $GLOBALS['ao_before']['vergeml_ai'] );

printf( "\n%d/%d passed\n\n", $GLOBALS['ao_pass'], $GLOBALS['ao_pass'] + $GLOBALS['ao_fail'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

exit( $GLOBALS['ao_fail'] > 0 ? 1 : 0 );
