<?php
/**
 *  The brief as a conversation (core/brief.php).
 *
 *      wp eval-file tests/tree/brief.php --allow-root
 *
 *  The pieces that decide what the "How it describes" tab costs and writes:
 *  the opener is built from the catalogue and makes no request; a turn is
 *  persisted and the cap holds; "Test on 5 pictures" holds its answers in
 *  the session and writes nothing to the catalogue, so the stamp does not
 *  move and no sweep starts; using the brief saves it, writes the held
 *  answers, and starts the stale run with its reason. Discarding drops the
 *  draft.
 *
 *  Nothing is sent to the service: the describer is stood in for through
 *  the vergeml_brief_describe filter, and the run is kept from spawning a
 *  pass through vergeml_ai_run_should_nudge. Everything written is put back:
 *  the five rows, their alt text, the profile, the session, the run state.
 *
 *  Mutation checks run against this suite on 2026-09-06: with the write
 *  moved into vergeml_brief_test() (the held rows stored as they arrive),
 *  C3 and C4 go red; with vergeml_ai_run_start() dropped from
 *  vergeml_brief_adopt(), D5 and D6 go red.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_brief_boot' ) || ! function_exists( 'vergeml_ai_index_store' ) ) {
    echo "core/brief.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

wp_set_current_user( 1 );

$GLOBALS['b_pass'] = 0;
$GLOBALS['b_fail'] = 0;

function b_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['b_pass']++;
    } else {
        $GLOBALS['b_fail']++;
    }
    echo sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

global $wpdb;
$b_table = $wpdb->vergeml_ai_index;

/* --------------------------------------------------------- what is found */

$b_session_was = get_option( VERGEML_BRIEF_OPTION );
$b_ai_was      = get_option( 'vergeml_ai' );
$b_run_was     = get_option( 'vergeml_ai_run' );
$b_cron_was    = wp_next_scheduled( VERGEML_AI_RUN_HOOK );
$b_profile_was = vergeml_brief_in_use();

// No request leaves this process unless a check says it may.
$GLOBALS['b_requests'] = 0;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
    $GLOBALS['b_requests']++;
    return new WP_Error( 'b_blocked', 'the suite blocks HTTP: ' . $url );
}, 10, 3 );
add_filter( 'vergeml_ai_run_should_nudge', '__return_false' );

$b_restore = function () use ( $b_session_was, $b_ai_was, $b_run_was, $b_cron_was ) {
    if ( false === $b_session_was ) {
        delete_option( VERGEML_BRIEF_OPTION );
    } else {
        update_option( VERGEML_BRIEF_OPTION, $b_session_was, false );
    }
    if ( false === $b_ai_was ) {
        delete_option( 'vergeml_ai' );
    } else {
        update_option( 'vergeml_ai', $b_ai_was, false );
    }
    if ( function_exists( 'vergeml_ai_run_unschedule' ) ) {
        vergeml_ai_run_unschedule();
    }
    if ( false === $b_run_was ) {
        delete_option( 'vergeml_ai_run' );
    } else {
        update_option( 'vergeml_ai_run', $b_run_was, false );
    }
    if ( false !== $b_cron_was && false === wp_next_scheduled( VERGEML_AI_RUN_HOOK ) ) {
        wp_schedule_single_event( $b_cron_was, VERGEML_AI_RUN_HOOK );
    }
    vergeml_brief_catalogue_forget();
};

/* ---------------------------------------------------- A  the free opener */

echo "\nA  The opener costs nothing\n\n";

vergeml_brief_save( vergeml_brief_fresh() );
$b_t0   = $GLOBALS['b_requests'];
$b_cat  = vergeml_brief_catalogue();
$b_boot = vergeml_brief_boot();

b_check( 'A1 the catalogue carries the described count, the kinds, the top tags and the brief words', isset( $b_cat['described'], $b_cat['kinds'], $b_cat['top_tags'], $b_cat['brief_words'], $b_cat['tags'] ) && is_array( $b_cat['top_tags'] ), wp_json_encode( array_keys( $b_cat ) ) );
b_check( 'A2 the described count is the catalogue\'s', (int) $b_cat['described'] === (int) vergeml_guide_described_count(), $b_cat['described'] . ' vs ' . vergeml_guide_described_count() );
b_check( 'A3 booting wrote the opener as the first turn', 1 === count( $b_boot['session']['turns'] ) && 'assistant' === $b_boot['session']['turns'][0]['role'], count( $b_boot['session']['turns'] ) . ' turns' );
b_check( 'A4 the opener is free: not a turn against the cap', 0 === (int) $b_boot['session']['assistant_turns'] && ! empty( $b_boot['session']['turns'][0]['free'] ) );
b_check( 'A5 no request left for the service', $GLOBALS['b_requests'] === $b_t0, ( $GLOBALS['b_requests'] - $b_t0 ) . ' requests' );
$b_open = (string) $b_boot['session']['turns'][0]['text'];
b_check( 'A6 the opener ends with one question', '?' === substr( rtrim( $b_open ), -1 ), substr( $b_open, -60 ) );
if ( '' !== $b_profile_was ) {
    b_check( 'A7 with a brief in use, the opener names the described count and asks about the brief', false !== strpos( $b_open, number_format_i18n( (int) $b_cat['described'] ) ) && false !== strpos( $b_open, 'brief' ), substr( $b_open, 0, 80 ) );
    b_check( 'A8 and offers the two answers', array( 'Yes, keep it', 'No' ) === $b_boot['session']['turns'][0]['choices'] );
} else {
    b_check( 'A7 with no brief in use, the opener says so and asks what the site is', false !== strpos( $b_open, 'No brief yet' ) && false !== strpos( $b_open, 'sell or publish' ) );
    b_check( 'A8 and offers no chip: the answer is typed', array() === $b_boot['session']['turns'][0]['choices'] );
}
b_check( 'A9 booting again does not write a second opener', 1 === count( vergeml_brief_boot()['session']['turns'] ) );

/* ------------------------------------------------------------- B  turns */

echo "\nB  Turns, the draft made safe, and the cap\n\n";

$b_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/brief/turn' );
$b_req->set_param( 'said', array( 'kind' => 'said', 'text' => 'No. A stock photo library for our Dutch design studio.' ) );
$b_req->set_param( 'say', array( 'text' => "In the draft brief:\n- Out: the skate shop\nIs the apparel line yours?", 'choices' => array( 'Ours', 'Stocked' ) ) );
$b_req->set_param( 'draft', array( ' A stock photo library for a Dutch design studio. ', 'No brands other than Verge.', '' ) );
$b_out = vergeml_brief_rest_turn( $b_req )->get_data();

b_check( 'B1 what was said and what was answered are two more turns', 3 === count( $b_out['turns'] ) && 'user' === $b_out['turns'][1]['role'] && 'assistant' === $b_out['turns'][2]['role'], count( $b_out['turns'] ) . ' turns' );
b_check( 'B2 the answer counts against the cap; the opener did not', 1 === (int) $b_out['assistant_turns'] );
b_check( 'B3 the chips travel with the answer', array( 'Ours', 'Stocked' ) === $b_out['turns'][2]['choices'] );
b_check( 'B4 the draft is trimmed lines, empty ones dropped', array( 'A stock photo library for a Dutch design studio.', 'No brands other than Verge.' ) === $b_out['draft'], wp_json_encode( $b_out['draft'] ) );

$b_long = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/brief/turn' );
$b_long->set_param( 'draft', array( str_repeat( 'a', 300 ), str_repeat( 'b', 300 ), 'c' ) );
$b_long_out = vergeml_brief_rest_turn( $b_long )->get_data();
b_check( 'B5 a draft longer than 500 characters joined loses its last lines until it fits', 1 === count( $b_long_out['draft'] ) && strlen( vergeml_brief_join( $b_long_out['draft'] ) ) <= VERGEML_BRIEF_MAX, strlen( vergeml_brief_join( $b_long_out['draft'] ) ) . ' chars in ' . count( $b_long_out['draft'] ) . ' lines' );

$b_seven = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/brief/turn' );
$b_seven->set_param( 'draft', array( '1.', '2.', '3.', '4.', '5.', '6.', '7.' ) );
b_check( 'B6 at most six lines', 6 === count( vergeml_brief_rest_turn( $b_seven )->get_data()['draft'] ) );

$b_s = vergeml_brief_session();
$b_s['assistant_turns'] = VERGEML_BRIEF_TURN_CAP;
vergeml_brief_save( $b_s );
$b_capped = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/brief/turn' );
$b_capped->set_param( 'say', array( 'text' => 'one more' ) );
$b_cap_out = vergeml_brief_rest_turn( $b_capped );
b_check( 'B7 at the cap an answer is refused', is_wp_error( $b_cap_out ) && 'turn_cap' === $b_cap_out->get_error_code() );
b_check( 'B8 and not counted', VERGEML_BRIEF_TURN_CAP === (int) vergeml_brief_session()['assistant_turns'] );

/* -------------------------------------------------- C  the test holds */

echo "\nC  Test on 5 pictures writes nothing\n\n";

$b_s = vergeml_brief_session();
$b_s['assistant_turns'] = 1;
$b_s['draft'] = array( 'A stock photo library for a Dutch design studio.', 'No brands other than Verge.' );
$b_s['test']  = null;
vergeml_brief_save( $b_s );

$b_ids = vergeml_brief_test_pick( VERGEML_BRIEF_TEST_N );
b_check( 'C1 five described pictures are picked, and the same five twice', VERGEML_BRIEF_TEST_N === count( $b_ids ) && $b_ids === vergeml_brief_test_pick( VERGEML_BRIEF_TEST_N ), wp_json_encode( $b_ids ) );

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the suite reads and restores this plugin's own table.
$b_rows_was = $b_ids ? $wpdb->get_results( "SELECT * FROM {$b_table} WHERE attachment_id IN (" . implode( ',', array_map( 'intval', $b_ids ) ) . ')', ARRAY_A ) : array();
$b_alt_was  = array();
foreach ( $b_ids as $b_id ) {
    $b_alt_was[ $b_id ] = get_post_meta( $b_id, '_wp_attachment_image_alt', true );
}
$b_stamp_was = vergeml_index_current_stamp();

$GLOBALS['b_described'] = array();
add_filter( 'vergeml_brief_describe', function () {
    return function ( $id, $brief ) {
        $GLOBALS['b_described'][] = array( $id, $brief );
        return array(
            'caption'       => 'Stub caption for #' . $id . ' under: ' . $brief,
            'alt'           => 'Stub alt for #' . $id,
            'tags'          => array( 'stub', 'verge' ),
            'title'         => 'Stub Title ' . $id,
            'kind'          => 'photo',
            'has_people'    => false,
            'has_text'      => false,
            'document_type' => 'none',
            'embedding'     => null,
            'model'         => 'stub',
            'model_version' => 'stub-1',
            'prompt_hash'   => 'stubhash' . substr( md5( $brief ), 0, 8 ),
            'filing'        => array( 'object' => 'stub; thing', 'material' => '', 'colour' => '', 'setting' => '', 'style' => '', 'audience' => '', 'season' => '', 'details' => '' ),
        );
    };
} );

$b_t0   = $GLOBALS['b_requests'];
$b_test = vergeml_brief_test();
$b_rows_now = $b_ids ? $wpdb->get_results( "SELECT * FROM {$b_table} WHERE attachment_id IN (" . implode( ',', array_map( 'intval', $b_ids ) ) . ')', ARRAY_A ) : array();

b_check( 'C2 the five were described with the draft, through the stand-in', ! is_wp_error( $b_test ) && VERGEML_BRIEF_TEST_N === count( $GLOBALS['b_described'] ) && $GLOBALS['b_described'][0][1] === vergeml_brief_join( $b_s['draft'] ), is_wp_error( $b_test ) ? $b_test->get_error_message() : count( $GLOBALS['b_described'] ) . ' described' );
b_check( 'C3 the catalogue rows are exactly as they were', $b_rows_was === $b_rows_now );
b_check( 'C4 the stamp did not move', $b_stamp_was === vergeml_index_current_stamp(), wp_json_encode( vergeml_index_current_stamp() ) );
b_check( 'C5 the answers are held in the session, five rows, before and after', ! is_wp_error( $b_test ) && is_array( $b_test['test'] ) && VERGEML_BRIEF_TEST_N === count( $b_test['test']['rows'] ) && isset( $b_test['test']['rows'][0]['before']['caption'], $b_test['test']['rows'][0]['after']['caption'] ) );
b_check( 'C6 the assistant\'s turn says what changed and that nothing is written yet', ! is_wp_error( $b_test ) && 'test' === end( $b_test['turns'] )['kind'] && false !== strpos( end( $b_test['turns'] )['text'], 'Caption changed on' ) && false !== strpos( end( $b_test['turns'] )['text'], 'Nothing is written until the brief is used' ), ! is_wp_error( $b_test ) ? end( $b_test['turns'] )['text'] : '' );
b_check( 'C7 the test cost is the count, and it is a free turn against the cap', ! is_wp_error( $b_test ) && VERGEML_BRIEF_TEST_N === (int) $b_test['test']['credits'] && 1 === (int) $b_test['assistant_turns'] );
b_check( 'C8 no run started', empty( vergeml_ai_run_state()['active'] ) || ( is_array( $b_run_was ) && ! empty( $b_run_was['active'] ) ) );
b_check( 'C9 nothing left this process', $GLOBALS['b_requests'] === $b_t0 );

$b_changed = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/brief/turn' );
$b_changed->set_param( 'draft', array( 'Something else entirely.' ) );
$b_changed_out = vergeml_brief_rest_turn( $b_changed )->get_data();
b_check( 'C10 a draft that changed drops the held answers: they were for another brief', null === $b_changed_out['test'] );

/* --------------------------------------------------------- D  adopting */

echo "\nD  Using the brief saves it, writes the held answers, starts the sweep\n\n";

$b_s = vergeml_brief_session();
$b_s['draft'] = array( 'A stock photo library for a Dutch design studio.', 'No brands other than Verge.' );
$b_s['test']  = null;
vergeml_brief_save( $b_s );
$b_test = vergeml_brief_test();
$b_brief = vergeml_brief_join( $b_s['draft'] );

$b_stale_was = vergeml_ai_pending_count( 'stale' );
$b_adopt = vergeml_brief_adopt();
$b_rows_after = $b_ids ? $wpdb->get_results( "SELECT attachment_id, caption, prompt_hash, model FROM {$b_table} WHERE attachment_id IN (" . implode( ',', array_map( 'intval', $b_ids ) ) . ')', ARRAY_A ) : array();
$b_all_stub = (bool) $b_rows_after;
foreach ( $b_rows_after as $b_row ) {
    if ( 0 !== strpos( (string) $b_row['caption'], 'Stub caption' ) || 'stub' !== (string) $b_row['model'] ) {
        $b_all_stub = false;
    }
}
$b_state = vergeml_ai_run_state();

b_check( 'D1 the brief is the site profile now', ! is_wp_error( $b_adopt ) && vergeml_brief_in_use() === $b_brief, vergeml_brief_in_use() );
b_check( 'D2 the five held answers are written, and no more were described', ! is_wp_error( $b_adopt ) && VERGEML_BRIEF_TEST_N === (int) $b_adopt['written'] && $b_all_stub && VERGEML_BRIEF_TEST_N * 2 === count( $GLOBALS['b_described'] ), ( is_wp_error( $b_adopt ) ? $b_adopt->get_error_message() : $b_adopt['written'] . ' written, ' . count( $GLOBALS['b_described'] ) . ' described in all' ) );
b_check( 'D3 the stamp reads the new prompt', 0 === strpos( vergeml_index_current_stamp()['prompt_hash'], 'stubhash' ), vergeml_index_current_stamp()['prompt_hash'] );
b_check( 'D4 the rest of the library is stale now', vergeml_ai_pending_count( 'stale' ) >= (int) vergeml_guide_described_count() - VERGEML_BRIEF_TEST_N - 1, vergeml_ai_pending_count( 'stale' ) . ' stale of ' . vergeml_guide_described_count() . ' (was ' . $b_stale_was . ')' );
b_check( 'D5 the stale run started, and says why', ! empty( $b_state['active'] ) && 'stale' === $b_state['scope'] && 'brief_changed' === $b_state['reason'], wp_json_encode( array( 'active' => $b_state['active'], 'scope' => $b_state['scope'], 'reason' => $b_state['reason'] ) ) );
b_check( 'D6 the answer carries the run', ! is_wp_error( $b_adopt ) && is_array( $b_adopt['run'] ) && ! empty( $b_adopt['run']['active'] ) && (int) $b_adopt['pending'] > 0 );
b_check( 'D7 the draft and the held answers are cleared; the conversation says the brief is in use', ! is_wp_error( $b_adopt ) && null === $b_adopt['session']['draft'] && null === $b_adopt['session']['test'] && false !== strpos( end( $b_adopt['session']['turns'] )['text'], 'Brief in use' ) );
b_check( 'D8 no pass was spawned from here', $GLOBALS['b_requests'] === $b_t0 );

/* --------------------------------------------------------------- E  discard */

echo "\nE  Discard\n\n";

$b_s = vergeml_brief_session();
$b_s['draft'] = array( 'A draft to throw away.' );
vergeml_brief_save( $b_s );
$b_discard = vergeml_brief_rest_discard( new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/brief/discard' ) )->get_data();
b_check( 'E1 the draft is gone and the line says so', null === $b_discard['draft'] && 'discard' === end( $b_discard['turns'] )['kind'] );

/* --------------------------------------------------------------- put back */

vergeml_ai_run_stop();
foreach ( $b_rows_was as $b_row ) {
    $wpdb->replace( $b_table, $b_row );
}
foreach ( $b_alt_was as $b_id => $b_alt ) {
    if ( '' === (string) $b_alt ) {
        delete_post_meta( $b_id, '_wp_attachment_image_alt' );
    } else {
        update_post_meta( $b_id, '_wp_attachment_image_alt', $b_alt );
    }
}
// phpcs:enable
$b_restore();

$b_rows_back = $b_ids ? $wpdb->get_results( "SELECT * FROM {$b_table} WHERE attachment_id IN (" . implode( ',', array_map( 'intval', $b_ids ) ) . ')', ARRAY_A ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
b_check( 'F1 the five rows are back as found', $b_rows_was === $b_rows_back );
b_check( 'F2 the profile is back as found', vergeml_brief_in_use() === $b_profile_was );
b_check( 'F3 the stamp is back as found', $b_stamp_was === vergeml_index_current_stamp() );
b_check( 'F4 no run is active, none scheduled beyond what was', empty( vergeml_ai_run_state()['active'] ) || ( is_array( $b_run_was ) && ! empty( $b_run_was['active'] ) ) );

echo sprintf( "\n%d/%d passed\n", $GLOBALS['b_pass'], $GLOBALS['b_pass'] + $GLOBALS['b_fail'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $GLOBALS['b_fail'] ? 1 : 0 );
