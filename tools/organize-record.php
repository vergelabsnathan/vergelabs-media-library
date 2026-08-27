<?php
/**
 *  Record organise runs as fixtures.
 *
 *      wp eval-file tools/organize-record.php --allow-root
 *
 *  The recorded runs are the phase's actual deliverable: Phase 3's review
 *  screen is built against these rather than against a live library, so the
 *  screen can be written, reviewed and tested before anything has to cluster
 *  anything.
 *
 *  Two runs at different thresholds, because the plan asked for two at
 *  different k and this is what replaced k -- how big a folder is allowed to
 *  get is the only number anyone sets, and it is the number that decides
 *  whether the tree is flat or deep. A screen that has only ever been shown a
 *  flat tree is a screen that will meet a deep one in front of a customer.
 *
 *  Lives in tools/, which is export-ignored, so nothing here ships.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_organize_step' ) ) {
    echo "core/organize.php is not loaded. Safe mode?\n";
    return;
}

$out = '/tmp/vgml-fixtures';

if ( ! is_dir( $out ) ) {
    mkdir( $out, 0755, true );
}

$home = trailingslashit( home_url() );

/**
 *  Recorded relative to the site that produced them. An absolute URL to
 *  whichever box happened to record this would make the fixture about the box.
 */
function vgml_record_relative( $branches, $home ) {

    foreach ( $branches as $i => $branch ) {

        if ( empty( $branch['samples'] ) ) {
            continue;
        }

        foreach ( $branch['samples'] as $j => $sample ) {
            $branches[ $i ]['samples'][ $j ]['thumb'] = str_replace( $home, '', (string) $sample['thumb'] );
        }
    }

    return $branches;
}

$shapes = array(
    array(
        'name'       => 'flat',
        'max_branch' => 50,
        'note'       => 'The shipped threshold. A library of this size comes apart in one cut, which is the common case and the one the screen opens on.',
    ),
    array(
        'name'       => 'deep',
        'max_branch' => 8,
        'note'       => 'The same library with folders allowed to hold only eight, so the splitting recurses. Branches that stopped because they ran out of depth rather than because they were small enough are flagged "capped", and the screen has to show them differently.',
    ),
);

foreach ( $shapes as $shape ) {

    $threshold = $shape['max_branch'];

    $narrow = function () use ( $threshold ) {
        return $threshold;
    };

    add_filter( 'vergeml_organize_max_branch', $narrow );

    $result = vergeml_organize_step( array() );
    $steps  = 0;

    while ( ! $result['done'] && $steps < 400 ) {
        $result = vergeml_organize_step( array( 'run_id' => $result['run_id'] ) );
        $steps++;
    }

    remove_filter( 'vergeml_organize_max_branch', $narrow );

    $run = vergeml_organize_run_get( $result['run_id'] );

    $fixture = array(
        'fixture'    => $shape['name'],
        'note'       => $shape['note'],
        'recorded'   => gmdate( 'Y-m-d' ),
        'source'     => 'mock embeddings; the vectors are derived from filenames, so the clusters are not evidence about quality',
        'max_branch' => $threshold,
        'steps'      => $steps,
        'run'        => array(
            'run_id'        => $run['run_id'],
            'parent_run_id' => $run['parent_run_id'],
            'status'        => $run['status'],
            'k'             => $run['k'],
            'n'             => $run['n'],
            'took'          => $run['params']['took'],
            'tree'          => vgml_record_relative( $run['tree'], $home ),
        ),
    );

    $path = $out . '/' . $shape['name'] . '.json';

    file_put_contents( $path, wp_json_encode( $fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );

    printf( "%-6s %2d branches, n=%d, %d steps -> %s\n",
        $shape['name'], count( $run['tree'] ), $run['n'], $steps, $path );

    $GLOBALS['vgml_recorded'][] = $run['run_id'];
}

/*
 *  And a diff between the two, because "runs produce diffs" is a claim this
 *  phase makes and Phase 3 has to render. Recording it means the screen is
 *  built against a real one rather than an invented one.
 */
$diff = vergeml_organize_diff( $GLOBALS['vgml_recorded'][0], $GLOBALS['vgml_recorded'][1] );

file_put_contents(
    $out . '/diff.json',
    wp_json_encode( array(
        'fixture'  => 'diff',
        'note'     => 'The same library at two thresholds, diffed from the stored trees rather than by re-running. This is what the screen shows when somebody changes how big a folder may be.',
        'recorded' => gmdate( 'Y-m-d' ),
        'diff'     => $diff,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

printf( "diff   %d added, %d removed, %d renamed, %d moved -> %s\n",
    count( $diff['added'] ), count( $diff['removed'] ), count( $diff['renamed'] ), count( $diff['moved'] ), $out . '/diff.json' );
