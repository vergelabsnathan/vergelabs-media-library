<?php
/**
 *  A scratch driver for the organise step loop, used while building it.
 *
 *      wp eval-file tools/organize-smoke.php --allow-root
 *
 *  Lives in tools/, which is export-ignored, so nothing here ships. The real
 *  assertions are in tests/organize/test-organize.php; this only prints what a
 *  run produced, which is what a person needs while the thresholds are being
 *  argued with.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_organize_step' ) ) {
    echo "core/organize.php is not loaded. Safe mode?\n";
    return;
}

$result = vergeml_organize_step( array() );
$run_id = $result['run_id'];
$steps  = 0;

echo "run {$run_id}\n";

while ( ! $result['done'] && $steps < 200 ) {
    $result = vergeml_organize_step( array( 'run_id' => $run_id ) );
    $steps++;
    printf( "  step %-3d %-8s loaded %-5d remaining %-5d %6.1fms\n",
        $steps, $result['phase'], $result['loaded'], $result['remaining'], $result['step_ms'] );
}

$run = vergeml_organize_run_get( $run_id );

printf( "\nstatus %s, n=%d, %d branches, took %s\n\n",
    $run['status'], $run['n'], count( $run['tree'] ), wp_json_encode( $run['params']['took'] ) );

$total = 0;

foreach ( $run['tree'] as $branch ) {
    $total += $branch['size'];
    printf( "  %-46s %3d files%s\n",
        implode( ' / ', $branch['path'] ),
        $branch['size'],
        ! empty( $branch['capped'] ) ? '   [capped]' : '' );
    foreach ( array_slice( $branch['members'], 0, 2 ) as $member ) {
        printf( "      %s\n", $member['why'] );
    }
}

printf( "\nassigned %d of %d\n", $total, $run['n'] );

// The property everything else rests on.
$second = vergeml_organize_step( array() );
while ( ! $second['done'] ) {
    $second = vergeml_organize_step( array( 'run_id' => $second['run_id'] ) );
}

$diff = vergeml_organize_diff( $run_id, $second['run_id'] );

printf( "deterministic: %s\n", $diff['same'] ? 'yes — two runs, identical tree' : 'NO — ' . wp_json_encode( array(
    'added'   => $diff['added'],
    'removed' => $diff['removed'],
    'renamed' => $diff['renamed'],
    'moved'   => count( $diff['moved'] ),
) ) );
