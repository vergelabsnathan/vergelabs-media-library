<?php
/**
 *  Scratch: what does each organise step cost, and does it stay flat?
 *
 *      wp eval-file tools/organize-queries.php --allow-root
 *
 *  The budget is four queries a step and the claim is that it does not move
 *  with the library size, the number of branches, or the number of steps
 *  already taken. An N+1 here would be a query per file, and the file count is
 *  the whole point of the feature -- so this walks a run of a couple of
 *  thousand files end to end and prints what every step spent.
 *
 *  Measured through rest_do_request rather than by calling the functions,
 *  because the budget is a property of the endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

wp_set_current_user( 1 );

if ( ! current_user_can( 'manage_categories' ) ) {
    echo "user 1 cannot manage_categories; pick another\n";
    return;
}

const Q_BASE = 920000;
const Q_N    = 1500;

echo "seeding " . Q_N . " vectors\n";

$scope = array();

for ( $i = 0; $i < Q_N; $i++ ) {

    $group  = $i % 25;
    $vector = array();
    $sum    = 0.0;

    for ( $d = 0; $d < 128; $d++ ) {
        $value    = ( hexdec( substr( md5( 'g' . $group . ':' . $d ), 0, 4 ) ) / 65535 ) - 0.5
                  + ( ( hexdec( substr( md5( 'm' . $i . ':' . $d ), 0, 4 ) ) / 65535 ) - 0.5 ) * 0.3;
        $vector[] = $value;
        $sum     += $value * $value;
    }

    $length = sqrt( $sum );

    foreach ( $vector as $d => $value ) {
        $vector[ $d ] = round( $value / $length, 6 );
    }

    $id = Q_BASE + $i;

    vergeml_index_set( $id, array(
        'tags'         => array( 'group' . $group, 'seeded', 'probe' ),
        'kind'         => 'photo',
        'embedding'    => $vector,
        'model'        => 'probe',
        'described_at' => current_time( 'mysql', true ),
    ) );

    $scope[] = $id;
}

// One throwaway dispatch: the first REST request of a process pays for the
// server being built, and counting that against the first step measured makes
// it look four queries worse than it is.
rest_do_request( new WP_REST_Request( 'GET', '/vergeml/v1/organize-run' ) );

function q_step( $params ) {

    global $wpdb;

    $before = count( $wpdb->queries );

    $request = new WP_REST_Request( 'POST', '/vergeml/v1/organize-step' );

    foreach ( $params as $key => $value ) {
        $request->set_param( $key, $value );
    }

    $data = rest_do_request( $request )->get_data();

    return array( $data, count( $wpdb->queries ) - $before );
}

/*
 *  The scope is a PHP-level argument, so this goes through the step function
 *  for creation and over REST for every step after it -- which is where the
 *  budget actually applies.
 */
$create = vergeml_organize_step( array( 'scope' => $scope ) );
$run_id = $create['run_id'];

printf( "\nrun %d, n=%d\n\n%-5s %-9s %-6s %-9s %s\n", $run_id, $create['n'], 'step', 'phase', 'q', 'ms', 'branches' );

$worst  = 0;
$steps  = 0;
$result = $create;

while ( ! $result['done'] && $steps < 400 ) {

    list( $result, $queries ) = q_step( array( 'run_id' => $run_id ) );

    $steps++;
    $worst = max( $worst, $queries );

    printf( "%-5d %-9s %-6d %-9.1f %d\n",
        $steps, $result['phase'], $queries, $result['step_ms'], count( $result['partial_tree'] ) );
}

printf( "\nworst step: %d queries over %d steps (budget 4)\n", $worst, $steps );

$before = count( $wpdb->queries );
rest_do_request( new WP_REST_Request( 'GET', '/vergeml/v1/organize-run' ) );
printf( "organize-run: %d queries (budget 2)\n", count( $wpdb->queries ) - $before );

$run = vergeml_organize_run_get( $run_id );

$total = 0;
foreach ( $run['tree'] as $branch ) {
    $total += $branch['size'];
}

printf( "tree: %d branches, %d of %d files placed, took %s\n",
    count( $run['tree'] ), $total, $run['n'], wp_json_encode( $run['params']['took'] ) );

printf( "stored row: %s KB\n", round( strlen( (string) $wpdb->get_var( $wpdb->prepare(
    "SELECT tree FROM {$wpdb->vergeml_organize_runs} WHERE run_id = %d", $run_id ) ) ) / 1024, 1 ) );

foreach ( $scope as $id ) {
    vergeml_index_delete( $id );
}

$wpdb->delete( vergeml_organize_table(), array( 'run_id' => $run_id ), array( '%d' ) );

echo "cleaned\n";
