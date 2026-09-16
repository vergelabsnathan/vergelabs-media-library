<?php
/**
 *  Confirm, lock, sticky (every-picture-a-home A.3) -- on the box.
 *
 *      node tools/verify.mjs sticky
 *
 *  Three rules the fill keeps, each proven on pictures and folders this suite
 *  makes and removes:
 *
 *    - a picture moved by hand (the tree's /assign route) carries
 *      _vergeml_placed_by = user and survives a fill that would have moved it;
 *    - a locked folder keeps what it holds and gains nothing, however well a
 *      picture scores;
 *    - a confirmed tree refuses /guide/rule, /guide/turn and a draft through
 *      /guide/session with 409 until it is unconfirmed; confirm stores the
 *      draft's own classes on the folders it describes and asks the planner
 *      once, only about the folders the draft says nothing about.
 *
 *  The box, not Playground: the fixture stores a packed embedding, which the
 *  SQLite layer refuses. Nothing is spent: every request to the service is
 *  answered here (pre_http_request) -- the embed with a one-hot vector per
 *  phrase, so two different phrases are unrelated and the same phrase is
 *  itself; the planner with a canned profile that also tries to re-describe a
 *  folder the draft described, which confirm must ignore.
 *
 *  Mutation named in the plan: remove the placed_by check from
 *  vergeml_filing_pick() -> D1 goes red (the hand-moved picture is filed).
 *
 *  Writes term meta, post meta, index rows, moves rows, the guide session and
 *  the fill state; all of it removed or put back at the end.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_talk_refile_run' ) || ! function_exists( 'vergeml_guide_confirm' ) ) {
    echo "core/folder-talk.php or core/guide.php is not loaded -- plugin inactive, safe mode, or a build before A.3?\n";
    exit( 1 );
}

global $wpdb;

wp_set_current_user( 1 );

$GLOBALS['sk_pass'] = 0;
$GLOBALS['sk_fail'] = 0;

// $GLOBALS, not `global`: wp eval-file runs this inside a function (see tests/tree/filing-trail.php).
function sk_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['sk_pass']++;
    } else {
        $GLOBALS['sk_fail']++;
    }
    echo sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

$sk_tax = vergeml_librarian_taxonomy();
if ( '' === $sk_tax || ! taxonomy_exists( $sk_tax ) ) {
    echo "no folder taxonomy on this site\n";
    exit( 1 );
}

vergeml_librarian_maybe_install();

/* ------------------------------------------------------------ the service */

/*
 *  Every request the plugin would make is answered here. The embed answers a
 *  one-hot vector per distinct phrase, in 64 dimensions from index 1, so the
 *  pictures' own vector ([1,0,0,0]) is orthogonal to every folder and the
 *  class match alone decides a score. The planner answers a profile for the
 *  folder the draft gave no classes and, deliberately, one for a folder the
 *  draft did describe -- which confirm must not take.
 */
$GLOBALS['sk_texts']   = array();
$GLOBALS['sk_planner'] = 0;

function sk_answer( $pre, $args, $url ) {
    $body = isset( $args['body'] ) ? json_decode( (string) $args['body'], true ) : array();
    if ( false !== strpos( $url, '/embed' ) ) {
        $text = isset( $body['text'] ) ? strtolower( trim( (string) $body['text'] ) ) : '';
        if ( ! isset( $GLOBALS['sk_texts'][ $text ] ) ) {
            $GLOBALS['sk_texts'][ $text ] = count( $GLOBALS['sk_texts'] ) + 1;
        }
        $v = array_fill( 0, 64, 0.0 );
        $v[ $GLOBALS['sk_texts'][ $text ] % 64 ] = 1.0;
        return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'embedding' => $v ) ), 'headers' => array() );
    }
    if ( false !== strpos( $url, '/folders' ) ) {
        $GLOBALS['sk_planner']++;
        return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'folders' => array(
            array( 'name' => 'zzStickyN', 'parent' => '', 'classes' => array( 'zzstickyn' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'planted by the suite' ),
            array( 'name' => 'zzStickyA', 'parent' => '', 'classes' => array( 'hijack' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => '' ),
        ) ) ), 'headers' => array() );
    }
    // The nudge to this site's own wp-cron.php (S10.2): answered, counted, and its key kept for E3.
    if ( false !== strpos( $url, 'wp-cron.php' ) ) {
        $GLOBALS['sk_cron'][] = (string) $url;
        return array( 'response' => array( 'code' => 200 ), 'body' => '', 'headers' => array() );
    }
    return $pre;
}
add_filter( 'pre_http_request', 'sk_answer', 1, 3 );
$GLOBALS['sk_cron'] = array();

/* ------------------------------------------------------------- the fixture */

echo "\nA  the fixture\n\n";

$sk_session_was = get_option( VERGEML_GUIDE_OPTION );
$sk_state_was   = get_option( VERGEML_TALK_STATE );
$sk_undo_was    = get_option( VERGEML_TALK_UNDO );

$sk_terms = array();
foreach ( array( 'zzStickyA', 'zzStickyB', 'zzStickyL', 'zzStickyN' ) as $sk_name ) {
    $sk_t = wp_insert_term( $sk_name, $sk_tax );
    if ( is_wp_error( $sk_t ) ) {
        $sk_e = get_term_by( 'name', $sk_name, $sk_tax );
        $sk_terms[ $sk_name ] = $sk_e instanceof WP_Term ? (int) $sk_e->term_id : 0;
    } else {
        $sk_terms[ $sk_name ] = (int) $sk_t['term_id'];
    }
}
sk_check( 'A1 four folders of its own', 4 === count( array_filter( $sk_terms ) ), json_encode( $sk_terms ) );
if ( 4 !== count( array_filter( $sk_terms ) ) ) {
    echo "\n0/1 passed\n";
    exit( 1 );
}
update_term_meta( $sk_terms['zzStickyL'], VERGEML_FILING_LOCKED, 1 );

$GLOBALS['sk_posts'] = array();

function sk_file( $title, $object ) {
    $id = wp_insert_post( array(
        'post_title'     => 'zz sticky ' . $title,
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/png',
    ) );
    $GLOBALS['sk_posts'][] = (int) $id;
    vergeml_index_set( (int) $id, array(
        'caption'       => 'seeded',
        'kind'          => 'photo',
        'filing'        => wp_json_encode( array( 'object' => $object, 'audience' => '' ) ),
        'embedding'     => array( 1.0, 0.0, 0.0, 0.0 ),
        'model'         => 'zz-test',
        'model_version' => 'zz-model-7',
        'prompt_hash'   => 'zzhash0123456789',
        'error'         => '',
        'described_at'  => gmdate( 'Y-m-d H:i:s' ),
    ) );
    return (int) $id;
}

$sk_files = array(
    'hand'  => sk_file( 'hand',  'zzstickything' ),
    'free'  => sk_file( 'free',  'zzstickything' ),
    'in1'   => sk_file( 'in1',   'zzstickylocked' ),
    'in2'   => sk_file( 'in2',   'zzstickylocked' ),
    'in3'   => sk_file( 'in3',   'zzstickylocked' ),
    'wants' => sk_file( 'wants', 'zzstickylocked' ),
);
sk_check( 'A2 six described pictures', 6 === count( array_filter( $sk_files ) ) );

// Where they start. `free` and the three in L are put there directly; `hand` goes through the tree's route below.
wp_set_object_terms( $sk_files['free'], array( $sk_terms['zzStickyB'] ), $sk_tax, false );
foreach ( array( 'in1', 'in2', 'in3' ) as $sk_k ) {
    wp_set_object_terms( $sk_files[ $sk_k ], array( $sk_terms['zzStickyL'] ), $sk_tax, false );
}

/* ------------------------------------------------------------ B  the hand move */

echo "\nB  a hand move is the user's\n\n";

$sk_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/assign' );
$sk_req->set_body_params( array( 'taxonomy' => $sk_tax, 'attachments' => array( $sk_files['hand'] ), 'add' => array( $sk_terms['zzStickyB'] ), 'mode' => 'move' ) );
$sk_res = rest_do_request( $sk_req );
$sk_in  = wp_get_object_terms( $sk_files['hand'], $sk_tax, array( 'fields' => 'ids' ) );
sk_check( 'B1 the tree\'s /assign route puts the picture in B', 200 === $sk_res->get_status() && array( $sk_terms['zzStickyB'] ) === array_map( 'intval', (array) $sk_in ), (string) $sk_res->get_status() );
sk_check( 'B2 and marks it placed by the user', 'user' === get_post_meta( $sk_files['hand'], VERGEML_FILING_PLACED_BY, true ), (string) get_post_meta( $sk_files['hand'], VERGEML_FILING_PLACED_BY, true ) );
sk_check( 'B3 the picture put there directly carries no mark', '' === (string) get_post_meta( $sk_files['free'], VERGEML_FILING_PLACED_BY, true ) );

/* ---------------------------------------------------------------- C  confirm */

echo "\nC  confirm: the tree is locked, the profiles stored, the planner asked once\n\n";

$sk_draft = array( 'folders' => array(), 'gone' => array(), 'origin' => 'talk' );
foreach ( array(
    'zzStickyA' => array( 'classes' => array( 'zzstickything' ), 'matches' => 'the things' ),
    'zzStickyB' => array( 'classes' => array( 'zzstickyother' ), 'matches' => 'the others' ),
    'zzStickyL' => array( 'classes' => array( 'zzstickylocked' ), 'matches' => 'the locked ones' ),
    'zzStickyN' => array( 'classes' => array(), 'matches' => '' ),
) as $sk_name => $sk_seed ) {
    $sk_draft['folders'][] = array( 'key' => 't' . $sk_terms[ $sk_name ], 'term_id' => $sk_terms[ $sk_name ], 'name' => $sk_name, 'parent' => '', 'classes' => $sk_seed['classes'], 'kinds' => $sk_seed['classes'] ? array( 'photo' ) : array(), 'audience' => '', 'matches' => $sk_seed['matches'] );
}

$sk_s          = vergeml_guide_fresh();
$sk_s['draft'] = vergeml_guide_clean_draft( $sk_draft );
vergeml_guide_save( $sk_s );

$sk_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/confirm' );
$sk_res = rest_do_request( $sk_req );
$sk_out = $sk_res->get_data();
sk_check( 'C1 /guide/confirm answers 200 and the session says confirmed', 200 === $sk_res->get_status() && isset( $sk_out['session']['tree'] ) && 'confirmed' === $sk_out['session']['tree'], $sk_res->get_status() . ' ' . json_encode( isset( $sk_out['session']['tree'] ) ? $sk_out['session']['tree'] : ( isset( $sk_out['message'] ) ? $sk_out['message'] : null ) ) );

$sk_s     = vergeml_guide_session();
$sk_by    = array();
foreach ( (array) ( isset( $sk_s['draft']['folders'] ) ? $sk_s['draft']['folders'] : array() ) as $sk_f ) {
    $sk_by[ $sk_f['name'] ] = $sk_f;
}
sk_check( 'C2 the planner was asked once, and only the folder without classes took its answer', 1 === $GLOBALS['sk_planner'] && isset( $sk_by['zzStickyN'] ) && array( 'zzstickyn' ) === $sk_by['zzStickyN']['classes'] && 'planted by the suite' === $sk_by['zzStickyN']['matches'], $GLOBALS['sk_planner'] . ' calls; N ' . json_encode( isset( $sk_by['zzStickyN'] ) ? $sk_by['zzStickyN']['classes'] : null ) );
sk_check( 'C3 a folder the draft described keeps the draft\'s classes, not the planner\'s', isset( $sk_by['zzStickyA'] ) && array( 'zzstickything' ) === $sk_by['zzStickyA']['classes'], json_encode( isset( $sk_by['zzStickyA'] ) ? $sk_by['zzStickyA']['classes'] : null ) );

$sk_pa = get_term_meta( $sk_terms['zzStickyA'], VERGEML_FILING_META, true );
$sk_pn = get_term_meta( $sk_terms['zzStickyN'], VERGEML_FILING_META, true );
sk_check( 'C4 the profiles are stored on the terms, from the draft: A is for zzstickything, N for zzstickyn, both from a plan', is_array( $sk_pa ) && 'plan' === $sk_pa['source'] && 'zzstickything' === $sk_pa['classes'][0] && is_array( $sk_pn ) && 'plan' === $sk_pn['source'] && 'zzstickyn' === $sk_pn['classes'][0], json_encode( array( is_array( $sk_pa ) ? $sk_pa['classes'] : null, is_array( $sk_pn ) ? $sk_pn['classes'] : null ) ) );

$sk_profiles = vergeml_filing_profiles( array_values( $sk_terms ), $sk_tax );
sk_check( 'C5 the locked folder reads as locked and the others do not', ! empty( $sk_profiles[ $sk_terms['zzStickyL'] ]['locked'] ) && empty( $sk_profiles[ $sk_terms['zzStickyA'] ]['locked'] ) );

foreach ( array(
    'C6 /guide/rule'                => array( '/guide/rule', array( 'rule' => 'kind', 'options' => array() ) ),
    'C7 /guide/turn'                => array( '/guide/turn', array( 'said' => array( 'kind' => 'edit', 'text' => 'Moved A under B' ) ) ),
    'C8 a draft through /guide/session' => array( '/guide/session', array( 'draft' => $sk_draft ) ),
) as $sk_label => $sk_call ) {
    $sk_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . $sk_call[0] );
    $sk_req->set_body_params( $sk_call[1] );
    $sk_res = rest_do_request( $sk_req );
    $sk_msg = $sk_res->get_data();
    sk_check( $sk_label . ' answers 409 with the line', 409 === $sk_res->get_status() && isset( $sk_msg['message'] ) && 'The tree is confirmed. Unconfirm it to change it.' === $sk_msg['message'], $sk_res->get_status() . ' ' . json_encode( isset( $sk_msg['message'] ) ? $sk_msg['message'] : null ) );
}

$sk_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/unconfirm' );
$sk_res = rest_do_request( $sk_req );
$sk_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/turn' );
$sk_req->set_body_params( array( 'said' => array( 'kind' => 'edit', 'text' => 'Moved A under B' ) ) );
$sk_turn = rest_do_request( $sk_req );
sk_check( 'C9 unconfirm answers 200 and a turn is taken again', 200 === $sk_res->get_status() && 'editing' === $sk_res->get_data()['session']['tree'] && 200 === $sk_turn->get_status(), $sk_res->get_status() . ' / ' . $sk_turn->get_status() );

$sk_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/confirm' );
$sk_res = rest_do_request( $sk_req );
sk_check( 'C10 confirmed again, the planner is not asked twice: every folder has classes now', 200 === $sk_res->get_status() && 1 === $GLOBALS['sk_planner'], $sk_res->get_status() . ' / ' . $GLOBALS['sk_planner'] . ' calls' );

/* ----------------------------------------------------------------- D  the fill */

echo "\nD  the fill: a hand move survives, a locked folder keeps its three and gains none\n\n";

$sk_after = min( array_values( $sk_files ) ) - 1;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$sk_reach = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL AND attachment_id > %d", $sk_after ) );
sk_check( 'D0 the pass can only reach this suite\'s own pictures', 6 === $sk_reach, $sk_reach . ' in range' );

if ( 6 === $sk_reach ) {

    $sk_moves = $wpdb->vergeml_librarian_moves;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $sk_batches_before = array_map( 'intval', (array) $wpdb->get_col( "SELECT batch_id FROM {$wpdb->vergeml_librarian_batches}" ) );

    update_option( VERGEML_TALK_UNDO, array( 'terms' => array(), 'files' => array(), 'made' => array(), 'until' => time() + DAY_IN_SECONDS ), false );
    update_option( VERGEML_TALK_STATE, array(
        'active'   => true,
        'taxonomy' => $sk_tax,
        'ids'      => array( 'a' => $sk_terms['zzStickyA'], 'b' => $sk_terms['zzStickyB'], 'l' => $sk_terms['zzStickyL'], 'n' => $sk_terms['zzStickyN'] ),
        'vectors'  => array(),
        'assign'   => array(),
        'fallback' => array(),
        'reasons'  => array(),
        'after'    => $sk_after,
        'moved'    => 0,
        'skipped'  => 0,
        'seen'     => 0,
        'total'    => 6,
        'counts'   => array(),
        'by_term'  => array(),
        'unfiled'  => array(),
        'tags'     => array(),
        'tagged'   => 0,
        'until'    => time() + DAY_IN_SECONDS,
        'remove'   => array(),
        'started'  => time(),
    ), false );

    $sk_done = vergeml_talk_refile_run( microtime( true ) + 30.0 );

    $sk_where = function ( $id ) use ( $sk_tax ) {
        $t = wp_get_object_terms( (int) $id, $sk_tax, array( 'fields' => 'ids' ) );
        return is_wp_error( $t ) ? array() : array_map( 'intval', $t );
    };

    sk_check( 'D1 the hand-moved picture is still in B after a fill that would have put it in A', array( $sk_terms['zzStickyB'] ) === $sk_where( $sk_files['hand'] ), json_encode( $sk_where( $sk_files['hand'] ) ) );
    sk_check( 'D2 the same picture put there directly did move to A (so the fill would have)', array( $sk_terms['zzStickyA'] ) === $sk_where( $sk_files['free'] ), json_encode( $sk_where( $sk_files['free'] ) ) );
    $sk_l = array_map( 'intval', (array) get_objects_in_term( $sk_terms['zzStickyL'], $sk_tax ) );
    sort( $sk_l );
    $sk_three = array( $sk_files['in1'], $sk_files['in2'], $sk_files['in3'] );
    sort( $sk_three );
    sk_check( 'D3 the locked folder keeps exactly its three', $sk_three === $sk_l, json_encode( $sk_l ) );
    sk_check( 'D4 and gains none: the picture that scores for it stays in no folder', array() === $sk_where( $sk_files['wants'] ), json_encode( $sk_where( $sk_files['wants'] ) ) );
    $sk_tally = isset( $sk_done['tally'] ) ? $sk_done['tally'] : array();
    sk_check( 'D5 the tally: 6 looked, 1 fits, 1 nothing, 4 kept (one placed by hand, three in a locked folder)', 6 === (int) $sk_tally['looked'] && 1 === (int) $sk_tally['fits'] && 1 === (int) $sk_tally['nothing'] && 4 === (int) $sk_tally['kept'], json_encode( array_intersect_key( $sk_tally, array_flip( array( 'looked', 'fits', 'siblings', 'nothing', 'kept', 'why' ) ) ) ) );

    $sk_in = implode( ',', array_map( 'intval', array_values( $sk_files ) ) );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids cast to int above.
    $sk_rows = array_map( 'intval', (array) $wpdb->get_col( "SELECT attachment_id FROM {$sk_moves} WHERE attachment_id IN ($sk_in)" ) );
    sort( $sk_rows );
    $sk_expect = array( $sk_files['free'], $sk_files['wants'] );
    sort( $sk_expect );
    sk_check( 'D6 the trail has a row for the two the fill judged and none for the four it kept', $sk_expect === $sk_rows, json_encode( $sk_rows ) );
    sk_check( 'D7 the hand-moved picture is still the user\'s', 'user' === get_post_meta( $sk_files['hand'], VERGEML_FILING_PLACED_BY, true ) );

    /* ------------------------------------------------------ E  a fill that cannot stall */

    /*
     *  S10.2. On the box's shop site (C.5, 2026-09-16) the first tick took
     *  cron's lock with a key no arriving request carried, every later
     *  wp-cron.php was refused as not its own, and nothing moved for four
     *  minutes. Planted here as it was: an active run, its event past due, a
     *  lock twenty seconds old that core's spawn_cron() declines to replace,
     *  and no tick for two minutes. One poll must move the picture itself.
     *  The run assigns outright, so the pass touches only this suite's
     *  picture and builds no profile. Mutation: the poll's kick removed ->
     *  E1 red (moved 0: the poll only re-books the event and waits).
     */
    echo "\nE  a fill that cannot stall behind a cron lock (S10.2)\n\n";

    $sk_hook_was = wp_next_scheduled( VERGEML_TALK_HOOK );
    wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
    wp_schedule_single_event( time() - 90, VERGEML_TALK_HOOK );
    $sk_lock_was = get_transient( 'doing_cron' );
    set_transient( 'doing_cron', sprintf( '%.22F', microtime( true ) - 20 ) );
    $GLOBALS['sk_cron'] = array();

    update_option( VERGEML_TALK_STATE, array(
        'active'   => true,
        'taxonomy' => $sk_tax,
        'ids'      => array( 'a' => $sk_terms['zzStickyA'], 'b' => $sk_terms['zzStickyB'] ),
        'vectors'  => array(),
        'assign'   => array( $sk_files['free'] => $sk_terms['zzStickyB'] ),
        'fallback' => array(),
        'reasons'  => array(),
        'after'    => $sk_after,
        'moved'    => 0,
        'skipped'  => 0,
        'seen'     => 0,
        'total'    => 6,
        'counts'   => array(),
        'by_term'  => array(),
        'unfiled'  => array(),
        'tags'     => array(),
        'tagged'   => 0,
        'until'    => time() + DAY_IN_SECONDS,
        'remove'   => array(),
        'started'  => time() - 120,
        'ticked'   => time() - 120,
    ), false );

    $sk_poll = vergeml_talk_progress();
    sk_check( 'E1 one poll on the stalled run moves the picture itself: moved 1, the picture in B, the run over', 1 === (int) $sk_poll['moved'] && array( $sk_terms['zzStickyB'] ) === $sk_where( $sk_files['free'] ) && empty( $sk_poll['running'] ), json_encode( array( 'moved' => $sk_poll['moved'], 'running' => $sk_poll['running'], 'in' => $sk_where( $sk_files['free'] ) ) ) );
    sk_check( 'E2 the report says when it last moved (ticked, within this second)', isset( $sk_poll['ticked'] ) && time() - (int) $sk_poll['ticked'] <= 2, isset( $sk_poll['ticked'] ) ? ( time() - (int) $sk_poll['ticked'] ) . ' s ago' : 'no ticked' );

    // The lock free and a run just booked: the schedule takes the lock with its own key and posts that key (core's spawn_cron), and the poll leaves the run to cron.
    update_option( VERGEML_TALK_STATE, array_merge( get_option( VERGEML_TALK_STATE ), array( 'active' => true, 'after' => $sk_after, 'moved' => 0, 'seen' => 0, 'started' => time(), 'ticked' => time() ) ), false );
    wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
    delete_transient( 'doing_cron' );
    $GLOBALS['sk_cron'] = array();
    vergeml_talk_refile_schedule();
    $sk_key = get_transient( 'doing_cron' );
    sk_check( 'E3 the nudge takes cron\'s lock with a new key and posts that key, never a key no request carries', 1 === count( $GLOBALS['sk_cron'] ) && is_string( $sk_key ) && false !== strpos( $GLOBALS['sk_cron'][0], 'doing_wp_cron=' . rawurlencode( $sk_key ) ), json_encode( array( 'posts' => count( $GLOBALS['sk_cron'] ), 'key' => $sk_key, 'url' => isset( $GLOBALS['sk_cron'][0] ) ? $GLOBALS['sk_cron'][0] : null ) ) );
    $sk_poll = vergeml_talk_progress();
    sk_check( 'E4 a run just booked is not run by the poll: moved stays 0', 0 === (int) $sk_poll['moved'] && ! empty( $sk_poll['running'] ), json_encode( array( 'moved' => $sk_poll['moved'], 'running' => $sk_poll['running'] ) ) );

    wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
    if ( false !== $sk_hook_was ) {
        wp_schedule_single_event( (int) $sk_hook_was, VERGEML_TALK_HOOK );
    }
    if ( false === $sk_lock_was ) {
        delete_transient( 'doing_cron' );
    } else {
        set_transient( 'doing_cron', $sk_lock_was );
    }

    // Put the screen's own state back before anything else reads it.
    if ( false === $sk_state_was ) {
        delete_option( VERGEML_TALK_STATE );
    } else {
        update_option( VERGEML_TALK_STATE, $sk_state_was, false );
    }
    if ( false === $sk_undo_was ) {
        delete_option( VERGEML_TALK_UNDO );
    } else {
        update_option( VERGEML_TALK_UNDO, $sk_undo_was, false );
    }

    // The batches this run caused and nothing else's (tests/tree/filing-trail.php's rule).
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $sk_batch_mine = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT batch_id FROM {$sk_moves} WHERE attachment_id IN ($sk_in)" ) );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DELETE FROM {$sk_moves} WHERE attachment_id IN ($sk_in)" );
    foreach ( $sk_batch_mine as $sk_bid ) {
        if ( in_array( $sk_bid, $sk_batches_before, true ) ) {
            continue;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sk_moves} WHERE batch_id = %d", $sk_bid ) ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete( $wpdb->vergeml_librarian_batches, array( 'batch_id' => $sk_bid ), array( '%d' ) );
        }
    }
}

/* ---------------------------------------------------------------- teardown */

echo "\nputting it back\n\n";

remove_filter( 'pre_http_request', 'sk_answer', 1 );

foreach ( array_unique( $GLOBALS['sk_posts'] ) as $sk_id ) {
    delete_post_meta( $sk_id, VERGEML_FILING_PLACED_BY );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( $wpdb->vergeml_ai_index, array( 'attachment_id' => $sk_id ), array( '%d' ) );
    wp_delete_post( $sk_id, true );
}
foreach ( $sk_terms as $sk_tid ) {
    if ( $sk_tid ) {
        wp_delete_term( $sk_tid, $sk_tax ); // Takes its meta (profile, lock) with it.
    }
}
// The vectors the stand-in service answered, cached by phrase for a week: not this site's.
foreach ( array_keys( $GLOBALS['sk_texts'] ) as $sk_text ) {
    delete_transient( 'vergeml_qv2_' . md5( $sk_text ) );
}
if ( false === $sk_session_was ) {
    delete_option( VERGEML_GUIDE_OPTION );
} else {
    update_option( VERGEML_GUIDE_OPTION, $sk_session_was, false );
}

$sk_left = 0;
foreach ( array_unique( $GLOBALS['sk_posts'] ) as $sk_id ) {
    if ( get_post( $sk_id ) ) {
        $sk_left++;
    }
}
sk_check( 'the pictures it made are gone', 0 === $sk_left, $sk_left . ' left' );
sk_check( 'the folders it made are gone', ! ( get_term( $sk_terms['zzStickyA'], $sk_tax ) instanceof WP_Term ) && ! ( get_term( $sk_terms['zzStickyL'], $sk_tax ) instanceof WP_Term ) );
sk_check( 'the session is as it was found', ( false === $sk_session_was && false === get_option( VERGEML_GUIDE_OPTION ) ) || ( false !== $sk_session_was && get_option( VERGEML_GUIDE_OPTION ) === $sk_session_was ) );

echo sprintf( "\n%d/%d passed\n", $GLOBALS['sk_pass'], $GLOBALS['sk_pass'] + $GLOBALS['sk_fail'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
if ( $GLOBALS['sk_fail'] > 0 ) {
    exit( 1 );
}
