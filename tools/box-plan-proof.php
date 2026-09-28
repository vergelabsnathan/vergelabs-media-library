<?php
/*
 *  One real plan, confirmed and filled, through the routes the Folders screen
 *  calls (spec-tree-planner's proof). Snapshot first and restore after with
 *  tools/box-tree-snapshot.php; score between with tools/box-tree-score.php.
 *
 *      node tools/box-eval.mjs tools/box-plan-proof.php [--site shop]
 *
 *  Charges the site's licence the plan's price. The service must have
 *  /plan-tree: tech is pointed at the box copy until tools/box-vps-unpoint.sh.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_set_current_user( 1 );
$pp_tax = vergeml_librarian_taxonomy();

function pp_call( $method, $route ) {
    $res = rest_do_request( new WP_REST_Request( $method, '/' . VERGEML_REST_NS . $route ) );
    return array( $res->get_status(), $res->get_data() );
}

$pp_s = vergeml_guide_session();
if ( 'confirmed' === $pp_s['tree'] ) {
    echo "the tree is confirmed: restore the snapshot first\n";
    return;
}

$pp_facts = vergeml_plan_facts();
printf( "labels %d, price %d, balance %s\n", $pp_facts['labels'], $pp_facts['price'], var_export( $pp_facts['balance'], true ) );

$pp_t = microtime( true );
list( $pp_code, $pp_out ) = pp_call( 'POST', '/guide/plan' );
if ( 200 !== $pp_code ) {
    echo "plan refused: $pp_code " . wp_json_encode( $pp_out ) . "\n";
    return;
}
wp_clear_scheduled_hook( VERGEML_PLAN_HOOK );
vergeml_plan_event();
$pp_s = vergeml_guide_session();
if ( 'done' !== $pp_s['plan']['state'] ) {
    echo 'plan ' . $pp_s['plan']['state'] . ': ' . ( isset( $pp_s['plan']['message'] ) ? $pp_s['plan']['message'] : '' ) . "\n";
    return;
}
$pp_new = 0;
foreach ( $pp_s['draft']['folders'] as $f ) {
    $pp_new += empty( $f['term_id'] ) ? 1 : 0;
}
printf( "plan: %.0fs, charged %d, runs %s, kept run %d, %d labels placed; draft %d folders, %d new, %d labels in the map\n",
    microtime( true ) - $pp_t, $pp_s['plan']['charged'], wp_json_encode( $pp_s['plan']['runs'] ), $pp_s['plan']['kept'], $pp_s['plan']['placed'],
    count( $pp_s['draft']['folders'] ), $pp_new, count( (array) $pp_s['draft']['label_map'] ) );

$pp_fit = vergeml_guide_draft_fit( $pp_s['draft'], $pp_tax );
if ( is_array( $pp_fit ) && ! empty( $pp_fit['tally'] ) ) {
    $w = $pp_fit['tally']['why'];
    printf( "dry run: looked %d, would stay unfiled %d (floor %d, margin %d, gated %d, to sort %d)\n", $pp_fit['looked'],
        (int) $w['floor'] + (int) $w['margin'] + (int) $w['gated'] + ( isset( $w['to_sort'] ) ? (int) $w['to_sort'] : 0 ),
        (int) $w['floor'], (int) $w['margin'], (int) $w['gated'], isset( $w['to_sort'] ) ? (int) $w['to_sort'] : 0 );
} else {
    echo "dry run: not counted\n";
}

$pp_left = 1;
for ( $i = 0; $pp_left > 0 && $i < 40; $i++ ) {
    list( $pp_code, $pp_out ) = pp_call( 'POST', '/guide/confirm' );
    if ( 200 !== $pp_code ) {
        echo "confirm refused: $pp_code " . wp_json_encode( $pp_out ) . "\n";
        return;
    }
    $pp_left = (int) $pp_out['left'];
}
echo 'confirmed: ' . vergeml_guide_session()['tree'] . "\n";

$pp_t = microtime( true );
list( $pp_code, $pp_out ) = pp_call( 'POST', '/guide/apply' );
if ( 200 !== $pp_code ) {
    echo "fill refused: $pp_code " . wp_json_encode( $pp_out ) . "\n";
    return;
}
wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
$pp_state = get_option( VERGEML_TALK_STATE );
for ( $i = 0; is_array( $pp_state ) && ! empty( $pp_state['active'] ) && $i < 200; $i++ ) {
    $pp_state = vergeml_talk_refile_run( microtime( true ) + 60.0 );
}
$pp_s = vergeml_guide_session();
vergeml_guide_progress_out( $pp_s );
vergeml_guide_save( $pp_s );
printf( "fill: %.0fs, %d moved, unfiled %s\n", microtime( true ) - $pp_t, (int) $pp_state['moved'], wp_json_encode( isset( $pp_state['unfiled'] ) ? $pp_state['unfiled'] : array() ) );
