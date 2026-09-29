<?php
/*
 *  The plan job's refund, on the box, free (spec-tree-planner story 9).
 *
 *      node tools/box-eval.mjs tools/box-plan-refund.php --site shop \
 *          --copy <trees.json>:/tmp/vgml-trees.json --env VGML_TREES=/tmp/vgml-trees.json
 *
 *  Runs the real vergeml_plan_event() with every outbound request answered
 *  here (pre_http_request): /plan-tree answers with the first tree of
 *  VGML_TREES (service shape, labels as text, as tools/plan-sim.mjs --trees
 *  writes it) -- a tree the guard keeps -- and /plan-tree/refund with a
 *  canned answer per case. Nothing reaches the service; nothing is charged.
 *  The guide session and the credits cache are read raw first and written
 *  back raw after; the tree, the pictures and the terms are never touched.
 *
 *  Cases: the refund is given (the message says nothing was charged, the
 *  state carries charged 0); refused for the month (the approved message,
 *  charged stands); a network blip then given (one retry); an older service
 *  that names no plan (no refund asked). And in every case: the refund
 *  request carries the licence key, the site and the plan id, nothing else.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$GLOBALS['pr_pass'] = 0;
$GLOBALS['pr_fail'] = 0;

function pr_check( $label, $ok, $note = '' ) {
    $GLOBALS[ $ok ? 'pr_pass' : 'pr_fail' ]++;
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note );
}

$pr_session = get_option( VERGEML_GUIDE_OPTION, null );
$pr_credits = get_option( 'vergeml_ai_credits', null );
// A confirmed tree (the shop's, story 4) refuses a plan; the run plans against an editing copy of the session, and the raw one goes back after.
if ( is_array( $pr_session ) && isset( $pr_session['tree'] ) && 'confirmed' === $pr_session['tree'] ) {
    echo "the tree is confirmed: planning against an editing copy of the session, restored after\n";
}

// The first saved tree, its label texts turned into this site's inventory ids.
$pr_inv   = vergeml_plan_inventory();
$pr_id_of = array_column( $pr_inv['labels'], 'id', 'label' );
$pr_saved = json_decode( (string) file_get_contents( getenv( 'VGML_TREES' ) ), true );
$pr_ids   = function ( $texts ) use ( $pr_id_of ) {
    return array_values( array_filter( array_map( function ( $x ) use ( $pr_id_of ) {
        return isset( $pr_id_of[ $x ] ) ? $pr_id_of[ $x ] : null;
    }, (array) $texts ) ) );
};
$pr_tree = array( 'folders' => array(), 'unfiled' => $pr_ids( $pr_saved[0]['unfiled'] ) );
foreach ( $pr_saved[0]['folders'] as $f ) {
    $f['labels']              = $pr_ids( $f['labels'] );
    $pr_tree['folders'][]     = $f;
}
printf( "tree %s, %d folders, inventory %d labels\n", $pr_saved[0]['name'], count( $pr_tree['folders'] ), count( $pr_inv['labels'] ) );

define( 'PR_PLAN', '6f1c2b0e-4d3a-4f7b-9c1e-2a5b8d9e0f13' );
$GLOBALS['pr_refund'] = array( 'answers' => array(), 'bodies' => array(), 'with_plan' => true );

add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( $pr_tree ) {
    if ( '/plan-tree' === substr( $url, -10 ) ) {
        $answer = array( 'folders' => $pr_tree['folders'], 'unfiled' => $pr_tree['unfiled'], 'trees' => array( $pr_tree ), 'runs' => array( 'asked' => 15, 'valid' => 15 ), 'charged' => 38, 'credits_remaining' => 100 );
        if ( $GLOBALS['pr_refund']['with_plan'] ) {
            $answer['plan'] = PR_PLAN;
        }
        return array( 'headers' => array(), 'body' => wp_json_encode( $answer ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
    }
    if ( '/plan-tree/refund' === substr( $url, -17 ) ) {
        $GLOBALS['pr_refund']['bodies'][] = json_decode( (string) $args['body'], true );
        $next = array_shift( $GLOBALS['pr_refund']['answers'] );
        if ( 'blip' === $next ) {
            return new WP_Error( 'http_request_failed', 'cURL error 28' );
        }
        return array( 'headers' => array(), 'body' => wp_json_encode( $next[1] ), 'response' => array( 'code' => $next[0], 'message' => '' ), 'cookies' => array(), 'filename' => null );
    }
    return new WP_Error( 'blocked', 'box-plan-refund answers every request itself: ' . $url );
}, PHP_INT_MAX, 3 );

function pr_run( $answers, $with_plan = true ) {
    $GLOBALS['pr_refund']['answers']   = $answers;
    $GLOBALS['pr_refund']['bodies']    = array();
    $GLOBALS['pr_refund']['with_plan'] = $with_plan;
    $s         = vergeml_guide_session();
    $s['plan']  = array( 'state' => 'running', 'at' => time(), 'price' => 38 );
    $s['draft'] = null;
    $s['tree']  = 'editing';
    vergeml_guide_save( $s );
    delete_transient( VERGEML_PLAN_LOCK );
    vergeml_plan_event();
    $credits = get_option( 'vergeml_ai_credits', array() );
    return array( vergeml_guide_session()['plan'], $GLOBALS['pr_refund']['bodies'], isset( $credits['remaining'] ) ? (int) $credits['remaining'] : null );
}

$pr_nothing = __( 'Your folders are already well organised — a new plan wouldn\'t improve them. Nothing was charged.', 'vergelabs-media-library' );
$pr_kept    = __( 'Your folders are already well organised — a new plan wouldn\'t improve them.', 'vergelabs-media-library' );

try {
    list( $plan, $bodies, $left ) = pr_run( array( array( 200, array( 'refunded' => 38, 'credits_remaining' => 138, 'again' => false ) ) ) );
    printf( "  guard %.4f -> %.4f\n", $plan['gain']['before'], $plan['gain']['after'] );
    pr_check( '1. kept, given back: the message says nothing was charged', 'kept' === $plan['state'] && $pr_nothing === $plan['message'], $plan['state'] . ' / ' . $plan['message'] );
    pr_check( '2. kept, given back: charged 0, refunded 38, the balance is the refund\'s', 0 === $plan['charged'] && 38 === $plan['refunded'] && 138 === $left, sprintf( 'charged %d refunded %d balance %s', $plan['charged'], $plan['refunded'], var_export( $left, true ) ) );
    pr_check( '3. the refund asks with the licence key, the site and the plan id, nothing else', 1 === count( $bodies ) && array( 'license_key', 'site', 'plan' ) === array_keys( $bodies[0] ) && PR_PLAN === $bodies[0]['plan'] && home_url() === $bodies[0]['site'] && '' !== $bodies[0]['license_key'], wp_json_encode( array_keys( (array) ( isset( $bodies[0] ) ? $bodies[0] : array() ) ) ) );
    pr_check( '4. no draft is offered', null === vergeml_guide_session()['draft'] );

    list( $plan, $bodies, $left ) = pr_run( array( array( 429, array( 'error' => 'refund_limit' ) ) ) );
    pr_check( '5. kept, refused for the month: the approved message, charged 38 stands', 'kept' === $plan['state'] && $pr_kept === $plan['message'] && 38 === $plan['charged'] && 0 === $plan['refunded'] && 100 === $left, sprintf( 'charged %d, balance %s', $plan['charged'], var_export( $left, true ) ) );

    list( $plan, $bodies, $left ) = pr_run( array( 'blip', array( 200, array( 'refunded' => 38, 'credits_remaining' => 138, 'again' => false ) ) ) );
    pr_check( '6. a network blip, then given back: one retry', 2 === count( $bodies ) && 0 === $plan['charged'] && $pr_nothing === $plan['message'], count( $bodies ) . ' asks' );

    list( $plan, $bodies, $left ) = pr_run( array( 'blip', 'blip' ) );
    pr_check( '7. two blips: charged stands, no third ask', 2 === count( $bodies ) && 38 === $plan['charged'] && $pr_kept === $plan['message'] );

    list( $plan, $bodies, $left ) = pr_run( array(), false );
    pr_check( '8. an older service names no plan: nothing is asked back', 0 === count( $bodies ) && 'kept' === $plan['state'] && 38 === $plan['charged'] );
} finally {
    // Written back raw, as read: never recomputed through the code under test.
    if ( null === $pr_session ) {
        delete_option( VERGEML_GUIDE_OPTION );
    } else {
        update_option( VERGEML_GUIDE_OPTION, $pr_session, false );
    }
    if ( null === $pr_credits ) {
        delete_option( 'vergeml_ai_credits' );
    } else {
        update_option( 'vergeml_ai_credits', $pr_credits, false );
    }
    delete_transient( VERGEML_PLAN_LOCK );
}
printf( "session restored: %s\n", get_option( VERGEML_GUIDE_OPTION, null ) === $pr_session ? 'yes' : 'NO' );

printf( "\n%d/%d passed\n", $GLOBALS['pr_pass'], $GLOBALS['pr_pass'] + $GLOBALS['pr_fail'] );
