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
 *  one-hot vector per distinct phrase, in 4096 dimensions from index 1, so the
 *  pictures' own vector ([1,0,0,0]) is orthogonal to every folder and the
 *  class match alone decides a score. (64 until S13: by section H the suite
 *  had embedded more than sixty-four phrases, two unrelated ones shared a
 *  slot and matched at 1.0, and a picture landed in the wrong folder.) The planner answers a profile for the
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
        $v = array_fill( 0, 4096, 0.0 );
        $v[ $GLOBALS['sk_texts'][ $text ] % 4096 ] = 1.0;
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
    // The group name the run's end asks for (F): counted, so the suite can say it was asked once.
    if ( false !== strpos( $url, '/name-group' ) ) {
        $GLOBALS['sk_named']++;
        return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'name' => 'zzNamed' ) ), 'headers' => array() );
    }
    return $pre;
}
add_filter( 'pre_http_request', 'sk_answer', 1, 3 );
$GLOBALS['sk_cron']  = array();
$GLOBALS['sk_named'] = 0;

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

/*
 *  × on a folder's last word (S12): the draft carries an explicit empty
 *  (`nowords`), which is not "the draft says nothing". The confirm writes a
 *  name-only profile over A's plan, asks the planner nothing about it, and
 *  keeps the plan a day so Restore brings the words back. Mutation: the
 *  `nowords` branch dropped from the confirm's seeding -> C11 red (A keeps
 *  zzstickything, `empty( classes )` reads as nothing said).
 */
$sk_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/unconfirm' );
rest_do_request( $sk_req );
$sk_s = vergeml_guide_session();
foreach ( $sk_s['draft']['folders'] as $sk_i => $sk_f ) {
    if ( (int) $sk_f['term_id'] === $sk_terms['zzStickyA'] ) {
        $sk_s['draft']['folders'][ $sk_i ]['classes'] = array();
        $sk_s['draft']['folders'][ $sk_i ]['nowords'] = true;
    }
}
$sk_s['draft'] = vergeml_guide_clean_draft( $sk_s['draft'] );
vergeml_guide_save( $sk_s );
$sk_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/confirm' );
$sk_res = rest_do_request( $sk_req );
$sk_pa  = get_term_meta( $sk_terms['zzStickyA'], VERGEML_FILING_META, true );
$sk_pv  = get_term_meta( $sk_terms['zzStickyA'], VERGEML_FILING_META_PREV, true );
sk_check(
    'C11 × on A\'s last word: the confirm writes a name-only profile (one class, from the name, no plan), asks the planner nothing, and keeps the plan for Restore',
    200 === $sk_res->get_status() && 1 === $GLOBALS['sk_planner']
        && is_array( $sk_pa ) && 'name' === $sk_pa['source'] && empty( $sk_pa['plan'] ) && 1 === count( $sk_pa['classes'] ) && 'zzstickything' !== $sk_pa['classes'][0]
        && is_array( $sk_pv ) && isset( $sk_pv['profile']['classes'][0] ) && 'zzstickything' === $sk_pv['profile']['classes'][0],
    json_encode( array( 'status' => $sk_res->get_status(), 'planner' => $GLOBALS['sk_planner'], 'source' => is_array( $sk_pa ) ? $sk_pa['source'] : null, 'classes' => is_array( $sk_pa ) ? $sk_pa['classes'] : null, 'prev' => is_array( $sk_pv ) && isset( $sk_pv['profile']['classes'] ) ? $sk_pv['profile']['classes'] : null ) )
);

$sk_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/unconfirm' );
rest_do_request( $sk_req );
$sk_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/profiles-restore' );
$sk_res = rest_do_request( $sk_req );
$sk_pa  = get_term_meta( $sk_terms['zzStickyA'], VERGEML_FILING_META, true );
$sk_s   = vergeml_guide_session();
$sk_fa  = null;
foreach ( (array) $sk_s['draft']['folders'] as $sk_f ) {
    if ( (int) $sk_f['term_id'] === $sk_terms['zzStickyA'] ) {
        $sk_fa = $sk_f;
    }
}
sk_check(
    'C12 Restore brings A\'s words back, on the folder and in the draft, and the explicit empty is gone',
    200 === $sk_res->get_status() && is_array( $sk_pa ) && 'plan' === $sk_pa['source'] && 'zzstickything' === $sk_pa['classes'][0]
        && is_array( $sk_fa ) && array( 'zzstickything' ) === array_values( (array) $sk_fa['classes'] ) && empty( $sk_fa['nowords'] ),
    json_encode( array( 'status' => $sk_res->get_status(), 'classes' => is_array( $sk_pa ) ? $sk_pa['classes'] : null, 'draft' => $sk_fa ? array( $sk_fa['classes'], ! empty( $sk_fa['nowords'] ) ) : null ) )
);

$sk_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/confirm' );
$sk_res = rest_do_request( $sk_req );
$sk_pa  = get_term_meta( $sk_terms['zzStickyA'], VERGEML_FILING_META, true );
sk_check( 'C13 confirmed once more on the restored words: A is for zzstickything again, the planner still asked once', 200 === $sk_res->get_status() && 1 === $GLOBALS['sk_planner'] && is_array( $sk_pa ) && 'plan' === $sk_pa['source'] && 'zzstickything' === $sk_pa['classes'][0], $sk_res->get_status() . ' / ' . $GLOBALS['sk_planner'] . ' calls / ' . json_encode( is_array( $sk_pa ) ? $sk_pa['classes'] : null ) );

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

    /*
     *  S11 review. A tick that meets the pass lock (a poll's kick holds the
     *  slice) used to book the event at time() and post a chained request,
     *  which met the lock again at once: a loopback a second for as long as
     *  the lock stood. Now it is booked a stall's length out and not posted.
     *  Mutation: the lock check removed from the event -> E5 red (booked now,
     *  and posted).
     */
    wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
    delete_transient( 'doing_cron' );
    set_transient( VERGEML_TALK_PASS_LOCK, time(), 120 );
    $GLOBALS['sk_cron'] = array();
    vergeml_talk_refile_event();
    $sk_next = wp_next_scheduled( VERGEML_TALK_HOOK );
    sk_check( 'E5 a tick that meets the pass lock books the event a stall out and posts nothing', false !== $sk_next && $sk_next >= time() + VERGEML_TALK_STALL - 1 && 0 === count( $GLOBALS['sk_cron'] ), json_encode( array( 'in' => false === $sk_next ? null : $sk_next - time(), 'posts' => count( $GLOBALS['sk_cron'] ) ) ) );
    delete_transient( VERGEML_TALK_PASS_LOCK );

    /* ------------------------------------------------------ F  the finish is bounded */

    /*
     *  S11 review. The run's end names each residue group through the
     *  service (a 20 s call each) and then deletes the folders it was asked to
     *  remove -- and a poll's kick can be the pass that reaches it, inside
     *  the browser's request. Now the naming happens before the deletes,
     *  each group is asked once per run, the name is written into the state
     *  before the next call, and a finish that runs out of its pass's time
     *  leaves the rest to the next pass with the run still active. Five
     *  pictures of one class make the one group a name is asked for.
     *  Mutation: the deadline check removed from the naming -> F1 red (the
     *  folder gone, the name asked, on a pass with no time left).
     */
    echo "\nF  the finish is bounded: names first, once, in the time the pass has\n\n";

    $sk_files['in4'] = sk_file( 'in4', 'zzstickylocked' );
    $sk_in           = implode( ',', array_map( 'intval', array_values( $sk_files ) ) );
    $sk_residue      = array();
    foreach ( array( 'in1', 'in2', 'in3', 'in4', 'wants' ) as $sk_k ) {
        $sk_residue[ $sk_files[ $sk_k ] ] = 0;
    }
    $sk_finish = function () use ( $sk_tax, $sk_terms, $sk_files, $sk_residue ) {
        return array(
            'active'   => true,
            'taxonomy' => $sk_tax,
            'ids'      => array( 'a' => $sk_terms['zzStickyA'], 'b' => $sk_terms['zzStickyB'] ),
            'vectors'  => array(),
            'assign'   => array(),
            'fallback' => array(),
            'reasons'  => array(),
            'after'    => max( array_values( $sk_files ) ),
            'moved'    => 0,
            'skipped'  => 0,
            'seen'     => 6,
            'total'    => 6,
            'counts'   => array(),
            'by_term'  => array(),
            'unfiled'  => array(),
            'tags'     => array(),
            'tagged'   => 0,
            'until'    => time() + DAY_IN_SECONDS,
            'remove'   => array( $sk_terms['zzStickyN'] ),
            'residue'  => $sk_residue,
            'started'  => time(),
            'ticked'   => time(),
        );
    };
    $GLOBALS['sk_named'] = 0;
    wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
    update_option( VERGEML_TALK_STATE, $sk_finish(), false );
    $sk_done = vergeml_talk_refile_run( microtime( true ) - 1.0 );
    sk_check( 'F1 a pass with no time left leaves the finish to the next: the folder still there, the run active, no name asked', get_term( $sk_terms['zzStickyN'], $sk_tax ) instanceof WP_Term && ! empty( $sk_done['active'] ) && 0 === $GLOBALS['sk_named'] && empty( $sk_done['questions'] ), json_encode( array( 'folder' => get_term( $sk_terms['zzStickyN'], $sk_tax ) instanceof WP_Term, 'active' => ! empty( $sk_done['active'] ), 'named' => $GLOBALS['sk_named'] ) ) );
    $sk_done = vergeml_talk_refile_run( microtime( true ) + 30.0 );
    $sk_q    = isset( $sk_done['questions'][0] ) ? $sk_done['questions'][0] : array();
    sk_check( 'F2 the next pass finishes it: the folder gone, the run over, one name asked, the card carries it over the five', ! ( get_term( $sk_terms['zzStickyN'], $sk_tax ) instanceof WP_Term ) && empty( $sk_done['active'] ) && 1 === $GLOBALS['sk_named'] && isset( $sk_q['name'], $sk_q['count'] ) && 'zzNamed' === $sk_q['name'] && 5 === (int) $sk_q['count'], json_encode( array( 'folder' => get_term( $sk_terms['zzStickyN'], $sk_tax ) instanceof WP_Term, 'active' => ! empty( $sk_done['active'] ), 'named' => $GLOBALS['sk_named'], 'card' => isset( $sk_q['name'] ) ? $sk_q['name'] . ' ' . $sk_q['count'] : null ) ) );
    // Asked once per run: a state that says the group was asked, and has no name for it, is not asked again -- the class word stands in.
    $sk_again           = $sk_finish();
    $sk_again['remove'] = array();
    $sk_again['asked']  = array( (string) key( (array) $sk_done['names'] ) => true ); // The group's key as F2 cached it.
    update_option( VERGEML_TALK_STATE, $sk_again, false );
    $sk_done = vergeml_talk_refile_run( microtime( true ) + 30.0 );
    $sk_q    = isset( $sk_done['questions'][0] ) ? $sk_done['questions'][0] : array();
    sk_check( 'F3 a group the run already asked about is not asked again: the class word stands in', 1 === $GLOBALS['sk_named'] && isset( $sk_q['name'] ) && 'Zzstickylocked' === $sk_q['name'], json_encode( array( 'named' => $GLOBALS['sk_named'], 'name' => isset( $sk_q['name'] ) ? $sk_q['name'] : null ) ) );

    /* ------------------------------------------------------ G  an answer yields to the person */

    /*
     *  S11 review. Since 2026-09-17 keep-parent and split move every picture
     *  of the question and mark them 'answer'. A picture the person dragged
     *  into a child after the fill (placed_by = user) was pulled back to the
     *  parent and its mark downgraded. Now an 'answer' move skips a picture
     *  that is the person's. `hand` is theirs and in B; `free` is nobody's
     *  and in B. Mutation: the skip removed -> G1 red (hand in A, 'answer').
     */
    echo "\nG  an answer never outranks a hand move\n\n";

    update_option( VERGEML_TALK_STATE, array_merge( $sk_finish(), array(
        'active'    => false,
        'residue'   => array(),
        'remove'    => array(),
        'questions' => array( array(
            'id'         => 's:' . $sk_terms['zzStickyA'],
            'kind'       => 'siblings',
            'term_id'    => $sk_terms['zzStickyA'],
            'children'   => array( $sk_terms['zzStickyB'], $sk_terms['zzStickyN'] ),
            'count'      => 2,
            'sample'     => array( $sk_files['hand'], $sk_files['free'] ),
            'ids'        => array( $sk_files['hand'] => $sk_terms['zzStickyB'], $sk_files['free'] => $sk_terms['zzStickyB'] ),
            'name'       => '',
            'class'      => '',
            'unreadable' => false,
            'answers'    => array( 'keep-parent', 'split', 'show-me' ),
        ) ),
    ) ), false );
    $sk_ans = vergeml_talk_answer( 's:' . $sk_terms['zzStickyA'], 'keep-parent' );
    sk_check( 'G1 keep-parent moves the free picture to the parent and marks it answer; the hand-moved one stays in B, still the user\'s', ! is_wp_error( $sk_ans ) && 1 === (int) $sk_ans['moved'] && array( $sk_terms['zzStickyA'] ) === $sk_where( $sk_files['free'] ) && 'answer' === get_post_meta( $sk_files['free'], VERGEML_FILING_PLACED_BY, true ) && array( $sk_terms['zzStickyB'] ) === $sk_where( $sk_files['hand'] ) && 'user' === get_post_meta( $sk_files['hand'], VERGEML_FILING_PLACED_BY, true ), json_encode( array( 'moved' => is_wp_error( $sk_ans ) ? $sk_ans->get_error_message() : $sk_ans['moved'], 'free' => $sk_where( $sk_files['free'] ), 'free_by' => get_post_meta( $sk_files['free'], VERGEML_FILING_PLACED_BY, true ), 'hand' => $sk_where( $sk_files['hand'] ), 'hand_by' => get_post_meta( $sk_files['hand'], VERGEML_FILING_PLACED_BY, true ) ) ) );

    /* ------------------------------------------------------ H  the fill learns from its own placements */

    /*
     *  S10.7. A folder holding three described pictures is profiled from
     *  them, and a fill runs a second round when its first left pictures
     *  unplaced and moved others: the folders now hold pictures, their
     *  members' words are read, and the leftovers get a second look. R is
     *  planned for "zzstickyround" and holds two pictures the person put
     *  there that say "zzstickyround gear" (two is not a folder). Round 1
     *  places the "zzstickyround" picture in R and leaves the one that says
     *  only "gear" (the plan word does not contain it); R now holds three,
     *  its members say "zzstickyround gear" twice -- a word alike to the
     *  plan's, so R keeps it (S16: a member word only where alike to the
     *  folder's own; the first fixture taught R "zzstickymember", a word
     *  nothing about R is like, and that is what the rule refuses) -- and
     *  round 2 places the leftover there: "gear" sits inside the learned
     *  word at 0.95. Mutation: the second round removed -> H1 red (the
     *  leftover in no folder, one round).
     */
    echo "\nH  the fill learns from its own placements (S10.7)\n\n";

    $sk_r = wp_insert_term( 'zzStickyR', $sk_tax );
    $sk_terms['zzStickyR'] = is_wp_error( $sk_r ) ? (int) get_term_by( 'name', 'zzStickyR', $sk_tax )->term_id : (int) $sk_r['term_id'];
    $sk_rp = vergeml_filing_profile_build( get_term( $sk_terms['zzStickyR'], $sk_tax ), $sk_tax, array( 'classes' => array( 'zzstickyround' ), 'kinds' => array( 'photo' ) ) );
    sk_check( 'H0 R is planned for zzstickyround', is_array( $sk_rp ) && 'plan' === $sk_rp['source'] && 'zzstickyround' === $sk_rp['classes'][0], json_encode( is_array( $sk_rp ) ? $sk_rp['classes'] : null ) );

    $sk_files['m1']    = sk_file( 'm1', 'zzstickyround gear; zzthing' );
    $sk_files['m2']    = sk_file( 'm2', 'zzstickyround gear; zzthing' );
    $sk_files['round'] = sk_file( 'round', 'zzstickyround; zzthing' );
    $sk_files['other'] = sk_file( 'other', 'gear; zzthing' );
    $sk_in             = implode( ',', array_map( 'intval', array_values( $sk_files ) ) );
    foreach ( array( 'm1', 'm2' ) as $sk_k ) {
        wp_set_object_terms( $sk_files[ $sk_k ], array( $sk_terms['zzStickyR'] ), $sk_tax, false );
        update_post_meta( $sk_files[ $sk_k ], VERGEML_FILING_PLACED_BY, 'user' );
    }
    wp_set_object_terms( $sk_files['round'], array(), $sk_tax, false );
    wp_set_object_terms( $sk_files['other'], array(), $sk_tax, false );

    $sk_layers = vergeml_filing_members_layers( array( $sk_terms['zzStickyR'] ), $sk_tax );
    sk_check( 'H0b two members make no layer', array() === $sk_layers, json_encode( $sk_layers ) );

    wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
    update_option( VERGEML_TALK_STATE, array(
        'active'   => true,
        'taxonomy' => $sk_tax,
        'ids'      => array( 'r' => $sk_terms['zzStickyR'], 'b' => $sk_terms['zzStickyB'] ),
        'vectors'  => array(),
        'assign'   => array(),
        'fallback' => array(),
        'reasons'  => array(),
        'after'    => $sk_files['m1'] - 1,
        'moved'    => 0,
        'skipped'  => 0,
        'seen'     => 0,
        'total'    => 4,
        'counts'   => array(),
        'by_term'  => array(),
        'unfiled'  => array(),
        'tags'     => array(),
        'tagged'   => 0,
        'until'    => time() + DAY_IN_SECONDS,
        'remove'   => array(),
        'started'  => time(),
        'ticked'   => time(),
    ), false );
    $sk_done = vergeml_talk_refile_run( microtime( true ) + 30.0 );
    $sk_tally = isset( $sk_done['tally'] ) ? $sk_done['tally'] : array();
    sk_check( 'H1 two rounds: the round picture lands in R in round 1, the other in round 2 -- both in R, moved 2, rounds 1 then 2, nothing 0', array( $sk_terms['zzStickyR'] ) === $sk_where( $sk_files['round'] ) && array( $sk_terms['zzStickyR'] ) === $sk_where( $sk_files['other'] ) && 2 === (int) $sk_done['moved'] && isset( $sk_done['rounds'] ) && array( 1 => 1, 2 => 2 ) === array_map( 'intval', (array) $sk_done['rounds'] ) && 0 === (int) $sk_tally['nothing'] && empty( $sk_done['active'] ), json_encode( array( 'round' => $sk_where( $sk_files['round'] ), 'other' => $sk_where( $sk_files['other'] ), 'moved' => $sk_done['moved'], 'rounds' => isset( $sk_done['rounds'] ) ? $sk_done['rounds'] : null, 'tally' => array_intersect_key( $sk_tally, array_flip( array( 'looked', 'fits', 'nothing', 'kept' ) ) ) ) ) );
    sk_check( 'H2 the tally counts each picture once across the rounds: looked 4, fits 2, kept 2', 4 === (int) $sk_tally['looked'] && 2 === (int) $sk_tally['fits'] && 2 === (int) $sk_tally['kept'], json_encode( array_intersect_key( $sk_tally, array_flip( array( 'looked', 'fits', 'siblings', 'nothing', 'kept' ) ) ) ) );
    $sk_layers = vergeml_filing_members_layers( array( $sk_terms['zzStickyR'] ), $sk_tax );
    $sk_layer  = isset( $sk_layers[ $sk_terms['zzStickyR'] ] ) ? $sk_layers[ $sk_terms['zzStickyR'] ] : array();
    sk_check( 'H3 R\'s layer after the fill: four members, zzstickyround gear 2 (zzstickyround and gear, said once each, are those pictures), the stamp kept in term meta', isset( $sk_layer['n'] ) && 4 === (int) $sk_layer['n'] && array( 'zzstickyround gear' => 2 ) === $sk_layer['words'] && is_array( get_term_meta( $sk_terms['zzStickyR'], VERGEML_FILING_META_MEMBERS, true ) ), json_encode( isset( $sk_layer['words'] ) ? $sk_layer['words'] : $sk_layers ) );
    $sk_report = vergeml_talk_report( $sk_done );
    sk_check( 'H4 the report carries the rounds', isset( $sk_report['rounds'] ) && array( 1 => 1, 2 => 2 ) === array_map( 'intval', (array) $sk_report['rounds'] ), json_encode( isset( $sk_report['rounds'] ) ? $sk_report['rounds'] : null ) );

    /* ------------------------------------------------------ I  file by the product */

    /*
     *  S10.8. A picture that is a product's featured image or in its gallery
     *  goes where the product's categories say -- sure, before any matching,
     *  no model, no credits -- and is then the product's, not the fill's to
     *  move again. The suite's own product sits in a product category named
     *  like a folder of the tree; its featured picture and a gallery picture
     *  say a word no folder has, and a third picture saying the same word is
     *  nobody's product. Only where WooCommerce's product type exists (the
     *  box's tech site). Mutation: the product path removed from the run
     *  (vergeml_filing_product_folders made to return its rows unchanged)
     *  -> I1 red (the two fall to the matcher and land nowhere).
     */
    echo "\nI  file by the product (S10.8)\n\n";

    if ( ! post_type_exists( 'product' ) || ! taxonomy_exists( 'product_cat' ) ) {
        echo "  skip  no product type on this site: WooCommerce is not active\n";
    } else {
        $sk_d = wp_insert_term( 'zzStickyDresses', $sk_tax );
        $sk_terms['zzStickyDresses'] = is_wp_error( $sk_d ) ? (int) get_term_by( 'name', 'zzStickyDresses', $sk_tax )->term_id : (int) $sk_d['term_id'];
        $sk_pc = wp_insert_term( 'zzStickyDresses', 'product_cat' );
        $sk_pc = is_wp_error( $sk_pc ) ? (int) get_term_by( 'name', 'zzStickyDresses', 'product_cat' )->term_id : (int) $sk_pc['term_id'];
        $sk_product = wp_insert_post( array( 'post_title' => 'zz sticky product', 'post_type' => 'product', 'post_status' => 'publish' ) );
        $GLOBALS['sk_posts'][] = (int) $sk_product;
        wp_set_object_terms( (int) $sk_product, array( $sk_pc ), 'product_cat', false );

        $sk_files['feat']  = sk_file( 'feat', 'zzstickyfrock; zzthing' );
        $sk_files['gal']   = sk_file( 'gal', 'zzstickyfrock; zzthing' );
        $sk_files['stray'] = sk_file( 'stray', 'zzstickyfrock; zzthing' );
        $sk_in             = implode( ',', array_map( 'intval', array_values( $sk_files ) ) );
        update_post_meta( (int) $sk_product, '_thumbnail_id', (string) $sk_files['feat'] );
        update_post_meta( (int) $sk_product, '_product_image_gallery', $sk_files['gal'] . ',999999999' );
        foreach ( array( 'feat', 'gal', 'stray' ) as $sk_k ) {
            wp_set_object_terms( $sk_files[ $sk_k ], array(), $sk_tax, false );
        }

        $sk_product_state = array(
            'active'   => true,
            'taxonomy' => $sk_tax,
            'ids'      => array( 'd' => $sk_terms['zzStickyDresses'], 'b' => $sk_terms['zzStickyB'] ),
            'vectors'  => array(),
            'assign'   => array(),
            'fallback' => array(),
            'reasons'  => array(),
            'after'    => $sk_files['feat'] - 1,
            'moved'    => 0,
            'skipped'  => 0,
            'seen'     => 0,
            'total'    => 3,
            'counts'   => array(),
            'by_term'  => array(),
            'unfiled'  => array(),
            'tags'     => array(),
            'tagged'   => 0,
            'until'    => time() + DAY_IN_SECONDS,
            'remove'   => array(),
            'started'  => time(),
            'ticked'   => time(),
        );
        wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
        update_option( VERGEML_TALK_STATE, $sk_product_state, false );
        update_option( VERGEML_TALK_UNDO, array( 'terms' => array( array( 'term_id' => $sk_terms['zzStickyDresses'], 'name' => 'zzStickyDresses', 'parent' => '' ) ), 'files' => array(), 'placed' => array(), 'batches' => array(), 'until' => time() + DAY_IN_SECONDS ), false );
        $sk_done  = vergeml_talk_refile_run( microtime( true ) + 30.0 );
        $sk_tally = isset( $sk_done['tally'] ) ? $sk_done['tally'] : array();
        sk_check( 'I1 the featured and the gallery picture land in Dresses by the product, sure; the stray one, saying the same word, lands nowhere', array( $sk_terms['zzStickyDresses'] ) === $sk_where( $sk_files['feat'] ) && array( $sk_terms['zzStickyDresses'] ) === $sk_where( $sk_files['gal'] ) && array() === $sk_where( $sk_files['stray'] ) && 2 === (int) $sk_done['moved'], json_encode( array( 'feat' => $sk_where( $sk_files['feat'] ), 'gal' => $sk_where( $sk_files['gal'] ), 'stray' => $sk_where( $sk_files['stray'] ), 'moved' => $sk_done['moved'] ) ) );
        sk_check( 'I2 both are marked placed by product, and the tally counts them: product 2, fits 2, nothing 1', 'product' === get_post_meta( $sk_files['feat'], VERGEML_FILING_PLACED_BY, true ) && 'product' === get_post_meta( $sk_files['gal'], VERGEML_FILING_PLACED_BY, true ) && '' === (string) get_post_meta( $sk_files['stray'], VERGEML_FILING_PLACED_BY, true ) && 2 === (int) $sk_tally['product'] && 2 === (int) $sk_tally['fits'] && 1 === (int) $sk_tally['nothing'], json_encode( array( 'feat_by' => get_post_meta( $sk_files['feat'], VERGEML_FILING_PLACED_BY, true ), 'tally' => array_intersect_key( $sk_tally, array_flip( array( 'looked', 'fits', 'nothing', 'product' ) ) ) ) ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $sk_prow = $wpdb->get_row( $wpdb->prepare( "SELECT why, score, source, hit FROM {$sk_moves} WHERE attachment_id = %d AND term_id = %d ORDER BY move_id DESC LIMIT 1", $sk_files['feat'], $sk_terms['zzStickyDresses'] ), ARRAY_A );
        sk_check( 'I3 the trail row says why product, score 1, source product', is_array( $sk_prow ) && 'product' === $sk_prow['why'] && abs( (float) $sk_prow['score'] - 1.0 ) < 1e-6 && 'product' === $sk_prow['source'], json_encode( $sk_prow ) );

        // A second count: the two are the product's now, looked at and kept, not moved or asked about.
        wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
        update_option( VERGEML_TALK_STATE, array_merge( $sk_product_state, array( 'after' => $sk_files['feat'] - 1 ) ), false );
        $sk_again = vergeml_talk_refile_run( microtime( true ) + 30.0 );
        $sk_tally = isset( $sk_again['tally'] ) ? $sk_again['tally'] : array();
        sk_check( 'I4 a second fill keeps them: moved 0, kept 2, still in Dresses', 0 === (int) $sk_again['moved'] && 2 === (int) $sk_tally['kept'] && array( $sk_terms['zzStickyDresses'] ) === $sk_where( $sk_files['feat'] ), json_encode( array( 'moved' => $sk_again['moved'], 'kept' => $sk_tally['kept'], 'feat' => $sk_where( $sk_files['feat'] ) ) ) );

        // Undo puts the pictures back and clears the product's mark with them.
        $sk_undone = vergeml_talk_undo();
        sk_check( 'I5 undo takes both out of Dresses and clears the mark', ! is_wp_error( $sk_undone ) && array() === $sk_where( $sk_files['feat'] ) && '' === (string) get_post_meta( $sk_files['feat'], VERGEML_FILING_PLACED_BY, true ), json_encode( array( 'undo' => is_wp_error( $sk_undone ) ? $sk_undone->get_error_message() : 'ok', 'feat' => $sk_where( $sk_files['feat'] ), 'by' => get_post_meta( $sk_files['feat'], VERGEML_FILING_PLACED_BY, true ) ) ) );

        wp_delete_term( $sk_pc, 'product_cat' );
    }

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
