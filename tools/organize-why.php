<?php
/**
 *  Why the tree came out like that.
 *
 *      wp eval-file tools/organize-why.php --allow-root
 *
 *  Two thresholds decide the shape of a proposal -- how much a split has to
 *  tighten things to be worth doing, and how much of a branch has to carry a
 *  tag before it can be its name. Both were set by argument rather than by
 *  looking, and both were wrong: a library of five hundred landscape
 *  photographs came back as twenty-nine folders, six of them called some
 *  variation of "Photo".
 *
 *  So this prints what the numbers actually are on a real library, per split
 *  and per branch, before anything is tuned. Lives in tools/, which is
 *  export-ignored, so nothing here ships.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_organize_split_gain' ) ) {
    echo "core/organize.php is not loaded, or predates the cohesion test\n";
    return;
}

global $wpdb;

/* ---------------------------------------------------------------- the data */

$rows = $wpdb->get_results(
    "SELECT attachment_id, embedding, embedding_dims, tags, kind
       FROM {$wpdb->vergeml_ai_index}
      WHERE error = '' AND embedding IS NOT NULL
      ORDER BY attachment_id ASC",
    ARRAY_A
);

if ( ! $rows ) {
    echo "nothing described here\n";
    return;
}

$points  = array();
$vectors = array();

foreach ( $rows as $i => $row ) {

    $points[ $i ] = array(
        'id'   => (int) $row['attachment_id'],
        'tags' => vergeml_index_tags_out( $row['tags'] ),
        'kind' => (string) $row['kind'],
    );

    $vectors[ $i ] = vergeml_organize_project(
        vergeml_index_vector_out( $row['embedding'] ),
        VERGEML_ORGANIZE_DIMS
    );
}

$global  = vergeml_organize_global_tags( $points );
$library = count( $points );

printf( "%d described files, %d distinct tags\n\n", $library, count( $global ) );


/* -------------------------------------------------- what a split achieves */

echo "A  how much each split tightens things\n";
echo "   (the drop in average distance from a file to the middle of its folder)\n\n";

$queue = array( array( 'members' => array_keys( $points ), 'depth' => 0, 'path' => '(everything)' ) );
$seen  = 0;

while ( $queue && $seen < 40 ) {

    $job = array_shift( $queue );
    $seen++;

    $members = $job['members'];

    if ( count( $members ) < VERGEML_ORGANIZE_MIN_BRANCH * 2 ) {
        continue;
    }

    $k      = min( VERGEML_ORGANIZE_WIDTH, count( $members ) );
    $result = vergeml_organize_kmeans( $members, $k, $vectors );

    $clusters = array();

    foreach ( $members as $i => $index ) {
        $clusters[ $result['assignment'][ $i ] ][] = $index;
    }

    ksort( $clusters );

    $gain = vergeml_organize_split_gain( $members, $clusters, $result['centroids'], $vectors );

    printf(
        "   %-34s %4d files -> %d branches   gain %5.1f%%\n",
        substr( $job['path'], 0, 34 ),
        count( $members ),
        count( $clusters ),
        $gain * 100
    );

    // Only follow the big ones, so the output stays readable.
    foreach ( $clusters as $cluster ) {
        if ( count( $cluster ) >= 20 && $job['depth'] < 2 ) {
            $queue[] = array(
                'members' => $cluster,
                'depth'   => $job['depth'] + 1,
                'path'    => $job['path'] . ' / ' . vergeml_organize_label( $cluster, $global, $points, $library ),
            );
        }
    }
}


/* ------------------------------------------ what could name each branch */

echo "\nB  the best tag each top-level branch has, and how much of it carries that tag\n";
echo "   (the floor for a name is currently " . ( VERGEML_ORGANIZE_NAME_SHARE * 100 ) . "%)\n\n";

$k      = min( VERGEML_ORGANIZE_WIDTH, $library );
$result = vergeml_organize_kmeans( array_keys( $points ), $k, $vectors );

$top = array();

foreach ( array_keys( $points ) as $i => $index ) {
    $top[ $result['assignment'][ $i ] ][] = $index;
}

ksort( $top );

$below = 0;

foreach ( $top as $c => $members ) {

    $counts = array();

    foreach ( $members as $index ) {
        foreach ( array_unique( $points[ $index ]['tags'] ) as $tag ) {
            if ( vergeml_organize_nameable( $tag ) ) {
                $counts[ $tag ] = isset( $counts[ $tag ] ) ? $counts[ $tag ] + 1 : 1;
            }
        }
    }

    arsort( $counts );

    $total = max( 1, count( $members ) );
    $line  = array();
    $best  = 0.0;
    $n     = 0;

    foreach ( $counts as $tag => $count ) {

        $share  = $count / $total;
        $spread = isset( $global[ $tag ] ) ? $global[ $tag ] : 1;

        // What the namer would refuse, and why.
        $why = '';

        if ( ( $spread / $library ) > VERGEML_ORGANIZE_NAME_CEILING ) {
            $why = ' [on ' . round( 100 * $spread / $library ) . '% of the library]';
        }

        if ( '' === $why && $best <= 0.0 ) {
            $best = $share;
        }

        $line[] = sprintf( '%s %d%%%s', $tag, round( $share * 100 ), $why );

        if ( ++$n >= 4 ) {
            break;
        }
    }

    if ( $best < VERGEML_ORGANIZE_NAME_SHARE ) {
        $below++;
    }

    printf(
        "   %4d files  best usable tag %3d%%%s\n        %s\n",
        count( $members ),
        round( $best * 100 ),
        $best < VERGEML_ORGANIZE_NAME_SHARE ? '   <- below the floor, so it falls back to the file kind' : '',
        implode( ' · ', $line )
    );
}

printf( "\n   %d of %d top-level branches cannot be named by a tag at the current floor\n", $below, count( $top ) );
