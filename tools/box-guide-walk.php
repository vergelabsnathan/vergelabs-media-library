<?php
/*
 *  A scripted session through the guide's REST callbacks, as an
 *  administrator (--user=1). Read-mostly: it applies only with VGML_APPLY=1.
 */

$apply = '1' === (string) getenv( 'VGML_APPLY' );
$req   = function ( $method, $params = array() ) {
    $r = new WP_REST_Request( $method );
    foreach ( $params as $k => $v ) { $r->set_param( $k, $v ); }
    return $r;
};
$show = function ( $label, $res ) {
    $d = $res instanceof WP_REST_Response ? $res->get_data() : $res;
    printf( "%s: %s\n", $label, is_wp_error( $d ) ? 'ERROR ' . $d->get_error_message() : mb_substr( wp_json_encode( $d ), 0, 420 ) );
    return $d;
};

delete_option( VERGEML_GUIDE_OPTION );
$s = $show( 'session', vergeml_guide_rest_session( $req( 'GET' ) ) );
if ( 'library' !== ( $s['state'] ?? '' ) ) { echo "FAIL: a fresh session is not at 'library'\n"; return; }

$t0  = microtime( true );
$sum = $show( 'summary', vergeml_guide_rest_summary( $req( 'POST' ) ) );
printf( "  (summary in %.1fs; %d groups, evidence %s)\n", microtime( true ) - $t0, count( $sum['groups'] ?? array() ), wp_json_encode( $sum['evidence'] ?? null ) );
if ( empty( $sum['total'] ) || empty( $sum['groups'] ) ) { echo "FAIL: summary has no total or groups\n"; return; }

$t0 = microtime( true );
$p  = $show( 'propose', vergeml_guide_rest_propose( $req( 'POST', array( 'goal' => 'a fashion and lifestyle shop' ) ) ) );
if ( is_wp_error( $p ) || empty( $p['proposals'][0]['tree']['folders'] ) ) { echo "FAIL: no proposal\n"; return; }
printf( "  (proposals in %.1fs)\n", microtime( true ) - $t0 );
foreach ( $p['proposals'] as $pr ) {
    printf( "  %s: %s\n", $pr['name'], implode( ' · ', array_map( function ( $f ) { return ( '' !== $f['parent'] ? $f['parent'] . '/' : '' ) . $f['name'] . '=' . $f['count']; }, $pr['tree']['folders'] ) ) );
}
$first = $p['proposals'][0]['tree'];

$show( 'start from first', vergeml_guide_rest_session( $req( 'POST', array( 'session' => array( 'state' => 'shaping', 'draft' => $first ) ) ) ) );

$t0 = microtime( true );
$t1 = $show( 'turn 1', vergeml_guide_rest_turn( $req( 'POST', array( 'text' => 'I want shoes split by size, colour and brand.' ) ) ) );
if ( is_wp_error( $t1 ) || empty( $t1['message'] ) ) { echo "FAIL: no message\n"; return; }
printf( "  (turn in %.1fs)\n", microtime( true ) - $t0 );
$choice = $t1['choices'][0] ?? 'Size as folders';
$t2 = $show( 'turn 2 (' . $choice . ')', vergeml_guide_rest_turn( $req( 'POST', array( 'choice' => $choice ) ) ) );
$t3 = $show( 'turn 3 (edit)', vergeml_guide_rest_turn( $req( 'POST', array( 'edit' => 'renamed Bags to Bags and luggage' ) ) ) );

$sess = vergeml_guide_session();
printf( "assistant turns used: %d of %d; draft version %d with %d folders, %d tags\n", $sess['assistant_turns'], VERGEML_GUIDE_TURN_CAP, $sess['draft']['version'] ?? 0, count( $sess['draft']['folders'] ?? array() ), count( $sess['draft']['tags'] ?? array() ) );

if ( ! $apply ) { echo "dry run: not applying\n"; return; }
$a = $show( 'apply', vergeml_guide_rest_apply( $req( 'POST' ) ) );
for ( $i = 0; $i < 120; $i++ ) {
    wp_cache_delete( VERGEML_TALK_STATE, 'options' );
    $st = get_option( VERGEML_TALK_STATE );
    if ( ! is_array( $st ) || empty( $st['active'] ) ) { break; }
    vergeml_talk_refile_run( time() + 20 );
}
$show( 'progress', vergeml_guide_rest_progress( $req( 'GET' ) ) );
