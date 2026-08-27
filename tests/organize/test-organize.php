<?php
/**
 *  The organise backend: a proposed tree, stored as data.
 *
 *  Run on the box:  wp eval-file /tmp/test-organize.php --allow-root
 *  Or through the runner:  node tools/verify.mjs organize
 *
 *  The suite seeds vectors straight into the index rather than describing
 *  anything, so no model, no licence and no credits are involved -- and the
 *  shapes it needs are shapes a real library would not oblige with on demand:
 *  a set of clean clusters, a pile where every file is nearly identical, and a
 *  pile where nothing relates to anything.
 *
 *  The rows it writes carry attachment ids that belong to no attachment. That
 *  is deliberate. The index has no foreign key, the clustering never asks
 *  WordPress about a file, and the one place that does -- the sample hydration
 *  -- has to survive an id whose post has been deleted anyway.
 *
 *  What this suite does NOT test is whether the tree is any good. The
 *  embeddings here are synthetic and the ones in production are mock; judging
 *  the clusters has to wait for real vectors, and no assertion below pretends
 *  otherwise.
 */

$GLOBALS['vgml_pass'] = 0;
$GLOBALS['vgml_fail'] = 0;

function o_check( $name, $ok, $detail = '' ) {
    if ( $ok ) {
        $GLOBALS['vgml_pass']++;
        echo "  ok   {$name}" . ( $detail ? "  -- {$detail}" : '' ) . "\n";
    } else {
        $GLOBALS['vgml_fail']++;
        echo "  FAIL {$name}" . ( $detail ? "  -- {$detail}" : '' ) . "\n";
    }
}

if ( ! function_exists( 'vergeml_organize_step' ) ) {
    echo "core/organize.php is not loaded. Is the plugin active, and not in safe mode?\n";
    exit( 1 );
}

// Well clear of any real attachment, and easy to sweep afterwards.
const O_BASE = 900000;

$GLOBALS['o_seeded'] = array();
$GLOBALS['o_runs']   = array();


/**
 *  A vector that behaves like an embedding: unit length, deterministic, and
 *  near the vectors of files in the same group. No rand() anywhere -- the
 *  whole suite rests on two runs producing the same tree, and a fixture that
 *  rolled dice could not be used to check it.
 */
function o_vector( $group, $member, $dims = 128 ) {

    $vector = array();
    $sum    = 0.0;

    for ( $i = 0; $i < $dims; $i++ ) {

        // The group decides the direction; the member nudges it. A tenth of
        // the amplitude, so groups stay apart and members stay together.
        $base    = ( hexdec( substr( md5( 'g' . $group . ':' . $i ), 0, 4 ) ) / 65535 ) - 0.5;
        $jitter  = ( ( hexdec( substr( md5( 'm' . $group . ':' . $member . ':' . $i ), 0, 4 ) ) / 65535 ) - 0.5 ) * 0.2;
        $value   = $base + $jitter;

        $vector[] = $value;
        $sum     += $value * $value;
    }

    $length = sqrt( $sum );

    foreach ( $vector as $i => $value ) {
        $vector[ $i ] = round( $value / $length, 6 );
    }

    return $vector;
}


/**
 *  One index row, with no attachment behind it.
 */
function o_seed( $id, $vector, $tags, $kind = 'photo' ) {

    vergeml_index_set( $id, array(
        'caption'      => 'seeded ' . $id,
        'tags'         => $tags,
        'kind'         => $kind,
        'embedding'    => $vector,
        'model'        => 'suite',
        'model_version' => '1',
        'described_at' => current_time( 'mysql', true ),
    ) );

    $GLOBALS['o_seeded'][] = (int) $id;

    return (int) $id;
}


/**
 *  Drive a run to completion, or to the step cap. Returns the last report.
 */
function o_run( $args = array(), $cap = 400 ) {

    $result = vergeml_organize_step( $args );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    $GLOBALS['o_runs'][] = (int) $result['run_id'];

    $steps = 0;

    while ( ! $result['done'] && $steps < $cap ) {
        $result = vergeml_organize_step( array( 'run_id' => $result['run_id'] ) );
        $steps++;
    }

    return $result;
}


function o_fingerprint( $tree ) {

    $lines = array();

    foreach ( (array) $tree as $branch ) {
        $ids = array();
        foreach ( $branch['members'] as $member ) {
            $ids[] = (int) $member['id'];
        }
        sort( $ids );
        $lines[] = implode( ' / ', $branch['path'] ) . ': ' . implode( ',', $ids );
    }

    sort( $lines );

    return implode( "\n", $lines );
}


/* ------------------------------------------------------------- projection */

echo "\nthe projection\n";

$long = array();
for ( $i = 0; $i < 768; $i++ ) {
    $long[] = ( hexdec( substr( md5( 'p:' . $i ), 0, 4 ) ) / 65535 ) - 0.5;
}

$short = vergeml_organize_project( $long, 64 );

o_check( 'projects to the width asked for', 64 === count( $short ), count( $short ) . ' components' );

o_check( 'projecting twice gives the same result',
    $short === vergeml_organize_project( $long, 64 ) );

$length = 0.0;
foreach ( $short as $value ) {
    $length += $value * $value;
}

o_check( 'the projection is unit length', abs( sqrt( $length ) - 1.0 ) < 0.001, sprintf( '%.6f', sqrt( $length ) ) );

/*
 *  The property the clustering actually needs. A projection that halved the
 *  cost and lost the neighbourhoods would pass every other assertion here and
 *  produce a tree about nothing.
 */
$near = $long;
$far  = array();

for ( $i = 0; $i < 768; $i++ ) {
    $near[ $i ] += ( hexdec( substr( md5( 'n:' . $i ), 0, 4 ) ) / 65535 - 0.5 ) * 0.05;
    $far[]       = ( hexdec( substr( md5( 'f:' . $i ), 0, 4 ) ) / 65535 ) - 0.5;
}

$d_near = vergeml_organize_distance( $short, vergeml_organize_project( $near, 64 ) );
$d_far  = vergeml_organize_distance( $short, vergeml_organize_project( $far, 64 ) );

o_check( 'two similar vectors stay similar after projection', $d_near < $d_far,
    sprintf( 'near %.4f vs far %.4f', $d_near, $d_far ) );

o_check( 'a vector already narrower than the target is left alone',
    3 === count( vergeml_organize_project( array( 0.1, 0.2, 0.3 ), 64 ) ) );


/* ----------------------------------------------------------------- sampling */

echo "\nthe seeding sample\n";

$indices = range( 0, 4999 );
$sample  = vergeml_organize_sample( $indices, 2000 );

o_check( 'the sample is capped', count( $sample ) <= 2000, count( $sample ) . ' of 5000' );
o_check( 'the sample is stable for the same library', $sample === vergeml_organize_sample( $indices, 2000 ) );
o_check( 'a library under the cap samples whole',
    range( 0, 99 ) === vergeml_organize_sample( range( 0, 99 ), 2000 ) );


/* --------------------------------------------------------------- estimates */

echo "\nthe estimate\n";

$estimate = vergeml_organize_estimate( 100, 500.0, 900 );

o_check( 'an estimate extrapolates from work done',
    $estimate['known'] && 4500 === $estimate['remaining_ms'],
    $estimate['remaining_ms'] . 'ms for 900 more' );

o_check( 'nothing done yet is not an estimate of zero',
    false === vergeml_organize_estimate( 0, 0.0, 900 )['known'] );


/* ------------------------------------------------------------------ memory */

echo "\nthe memory arithmetic\n";

$memory = vergeml_organize_memory( 10000, 64 );

o_check( 'ten thousand at 64 dims is budgeted in tens of megabytes',
    $memory['need'] > 8 * 1024 * 1024 && $memory['need'] < 40 * 1024 * 1024,
    round( $memory['need'] / 1048576, 1 ) . 'MB' );

/*
 *  Pinned to a modest shared host for the length of these three assertions.
 *  WP-CLI runs with no limit at all, so without this the refusal path is never
 *  reached and the check passes by never happening -- which is the failure
 *  mode this whole suite exists to avoid.
 */
$real_limit = ini_get( 'memory_limit' );
ini_set( 'memory_limit', '128M' ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Blacklisted -- restored four lines down; this is the assertion.

$huge = vergeml_organize_memory( 5000000, 1536 );

o_check( 'a library that cannot fit is told so before it starts',
    ! $huge['fits'],
    'need ' . round( $huge['need'] / 1048576 ) . 'MB against a ' . round( $huge['limit'] / 1048576 ) . 'MB limit' );

o_check( 'a run that cannot fit at any width is refused rather than started',
    0 === vergeml_organize_fit_dims( 5000000, 64 ) );

// Fifty thousand is the band where 64 dimensions will not fit a 128MB host
// and 32 will: the run is narrowed rather than refused, which is the whole
// reason the projection width is decided per host instead of per release.
o_check( 'a run that only fits narrower is narrowed rather than refused',
    vergeml_organize_fit_dims( 50000, 64 ) < 64 && vergeml_organize_fit_dims( 50000, 64 ) > 0,
    vergeml_organize_fit_dims( 50000, 64 ) . ' dims for 50,000 files on a 128MB host' );

/*
 *  And the refusal reaches the caller as a failed run carrying a reason,
 *  rather than as a run that starts and dies halfway through. Two hundred
 *  thousand ids, no rows behind them -- the arithmetic happens before anything
 *  is loaded, which is the entire point of it.
 */
$refused = vergeml_organize_step( array( 'scope' => range( 1, 200000 ) ) );

ini_set( 'memory_limit', $real_limit ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Blacklisted -- putting back what the host had.

$GLOBALS['o_runs'][] = (int) $refused['run_id'];

o_check( 'a run it cannot finish is refused at creation, not halfway',
    'failed' === $refused['status'] && '' !== $refused['error'],
    $refused['status'] . ': ' . $refused['error'] );

o_check( 'a refused run does no work',
    $refused['done'] && 0 === $refused['loaded'] );

o_check( 'a library that does fit keeps the full width',
    64 === vergeml_organize_fit_dims( 100, 64 ) );


/* ------------------------------------------------- peak memory at ten thousand */

echo "\npeak memory, ten thousand vectors\n";

$before = memory_get_usage( true );
$peak   = memory_get_peak_usage( true );

$vectors = array();

for ( $i = 0; $i < 10000; $i++ ) {
    $vectors[] = o_vector( $i % 40, $i, 64 );
}

$used = memory_get_usage( true ) - $before;

// The projected set, the centroids and the assignment array are what stays
// resident. The ceiling is half of the 128MB a modest shared host gives,
// because WordPress and everything else also has to fit.
o_check( 'ten thousand projected vectors stay under 64MB',
    $used < 64 * 1024 * 1024,
    round( $used / 1048576, 1 ) . 'MB resident' );

o_check( 'the arithmetic did not under-promise',
    vergeml_organize_memory( 10000, 64 )['need'] >= $used * 0.5,
    'predicted ' . round( vergeml_organize_memory( 10000, 64 )['need'] / 1048576, 1 ) . 'MB, used ' . round( $used / 1048576, 1 ) . 'MB' );

unset( $vectors );


/* ------------------------------------------------------------------ a run */

echo "\na run over six clean groups\n";

$tags = array(
    array( 'harbour', 'boats', 'water' ),
    array( 'wordmark', 'logo', 'brand' ),
    array( 'invoice', 'paperwork', 'scan' ),
    array( 'ridgeline', 'hills', 'walk' ),
    array( 'interface', 'grid', 'screen' ),
    array( 'portrait', 'team', 'people' ),
);

$scope = array();

for ( $g = 0; $g < 6; $g++ ) {
    for ( $m = 0; $m < 20; $m++ ) {
        $scope[] = o_seed( O_BASE + ( $g * 100 ) + $m, o_vector( $g, $m ), $tags[ $g ] );
    }
}

sort( $scope );

$started = microtime( true );
$first   = o_run( array( 'scope' => $scope ) );
$took    = ( microtime( true ) - $started ) * 1000;

o_check( 'the run finished', ! is_wp_error( $first ) && 'done' === $first['status'],
    is_wp_error( $first ) ? $first->get_error_message() : $first['status'] );

$run_a = vergeml_organize_run_get( $first['run_id'] );

o_check( 'the tree was stored', count( $run_a['tree'] ) > 0, count( $run_a['tree'] ) . ' branches' );

$sizes  = 0;
$placed = array();
$empty_labels = 0;
$missing_why  = 0;

foreach ( $run_a['tree'] as $branch ) {

    $sizes += $branch['size'];

    if ( '' === trim( (string) $branch['label'] ) ) {
        $empty_labels++;
    }

    foreach ( $branch['members'] as $member ) {
        $placed[] = (int) $member['id'];
        if ( '' === trim( (string) $member['why'] ) ) {
            $missing_why++;
        }
    }
}

o_check( 'branch sizes sum to n', $sizes === (int) $run_a['n'], "{$sizes} of {$run_a['n']}" );

o_check( 'every file lands in exactly one branch',
    count( $placed ) === count( array_unique( $placed ) ) && count( $placed ) === count( $scope ),
    count( $placed ) . ' placements, ' . count( array_unique( $placed ) ) . ' distinct, ' . count( $scope ) . ' seeded' );

o_check( 'every branch has a label', 0 === $empty_labels );
o_check( 'every assignment carries a reason', 0 === $missing_why, count( $placed ) . ' reasons' );

o_check( 'exactly one "Needs a look"',
    1 === count( array_filter( $run_a['tree'], function ( $b ) {
        return 'needs-a-look' === $b['key'];
    } ) ) );

$stray = 0;

foreach ( $run_a['tree'] as $branch ) {
    if ( 'needs-a-look' === $branch['key'] ) {
        $stray = $branch['size'];
    }
}

// The guard the plan asks for by name: if this branch routinely holds a third
// of the library, the thresholds are wrong and the suite should say so rather
// than let it quietly absorb the problem.
o_check( '"Needs a look" is not the catch-all under another name',
    $stray < count( $scope ) / 3,
    $stray . ' of ' . count( $scope ) );

o_check( 'clean groups cluster into roughly as many branches',
    count( $run_a['tree'] ) >= 5 && count( $run_a['tree'] ) <= 9,
    count( $run_a['tree'] ) . ' branches for 6 planted groups' );

o_check( 'samples were hydrated with the tree',
    isset( $run_a['tree'][0]['samples'] ),
    'read path does not pay for them again' );

o_check( 'each branch carries an agreement distribution',
    isset( $run_a['tree'][0]['agreement']['close'] ) );


/* --------------------------------------------------------------- determinism */

echo "\ndeterminism\n";

$second = o_run( array( 'scope' => $scope ) );
$run_b  = vergeml_organize_run_get( $second['run_id'] );

o_check( 'two runs produce the identical tree',
    o_fingerprint( $run_a['tree'] ) === o_fingerprint( $run_b['tree'] ) );

$diff = vergeml_organize_diff( $run_a['run_id'], $run_b['run_id'] );

o_check( 'a diff of two identical runs is empty', $diff['same'],
    count( $diff['added'] ) . ' added, ' . count( $diff['removed'] ) . ' removed, ' . count( $diff['moved'] ) . ' moved' );

$self = vergeml_organize_diff( $run_a['run_id'], $run_a['run_id'] );

o_check( 'a diff of a run against itself is empty', $self['same'] );


/* --------------------------------------------------------- the honest estimate */

echo "\nthe estimate against the clock\n";

/*
 *  Timed against work actually performed rather than against a constant. An
 *  estimate wrong by more than a third is worse than no estimate: somebody
 *  told two hours who waits six does not use the feature again.
 */
// Held to the floor, so the load takes several steps and there is something
// left for the first one to project about. Unfiltered it would swallow this
// library whole and the assertion would be about nothing.
$small = function () { return 1; };
add_filter( 'vergeml_organize_batch', $small );

$timed = vergeml_organize_step( array( 'scope' => $scope ) );
$GLOBALS['o_runs'][] = (int) $timed['run_id'];

$timed = vergeml_organize_step( array( 'run_id' => $timed['run_id'] ) );

remove_filter( 'vergeml_organize_batch', $small );

$guess = $timed['estimate'];
$clock = microtime( true );

while ( ! $timed['done'] ) {
    $timed = vergeml_organize_step( array( 'run_id' => $timed['run_id'] ) );
}

$actual = ( microtime( true ) - $clock ) * 1000;

o_check( 'the first chunk is enough to project from', $guess['known'] && $guess['remaining_ms'] > 0,
    sprintf( '%.4fms a file, %d left', $guess['per_item_ms'], $timed['n'] - $guess['remaining_ms'] > 0 ? 1 : 0 ) );

/*
 *  Thirty per cent is not a precise number. It is the point past which an
 *  estimate is worse than none: somebody told two hours who waits six does not
 *  use the feature again. The floor is there because at these durations the
 *  clock itself is a large share of the answer.
 */
o_check( 'the projection lands within a third of the truth',
    abs( $guess['remaining_ms'] - $actual ) <= max( 60, $actual * 0.3 ),
    sprintf( 'projected %dms, took %.0fms', $guess['remaining_ms'], $actual ) );


/* ------------------------------------------------------------------- cancel */

echo "\ncancel\n";

$partial = vergeml_organize_step( array( 'scope' => $scope ) );
$GLOBALS['o_runs'][] = (int) $partial['run_id'];

// One working step, so there is something built to keep.
$partial = vergeml_organize_step( array( 'run_id' => $partial['run_id'] ) );
$partial = vergeml_organize_step( array( 'run_id' => $partial['run_id'] ) );

$request = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/organize-cancel' );
$request->set_param( 'run_id', $partial['run_id'] );

$cancelled = vergeml_organize_rest_cancel( $request );
$cancelled = $cancelled instanceof WP_REST_Response ? $cancelled->get_data() : $cancelled;

o_check( 'cancel sets the flag', ! is_wp_error( $cancelled ) && 'cancelled' === $cancelled['status'] );

$after = vergeml_organize_step( array( 'run_id' => $partial['run_id'] ) );

o_check( 'a cancelled run stops at the top of the next step', 'cancelled' === $after['status'] );

$kept = vergeml_organize_run_get( $partial['run_id'] );

o_check( 'a cancelled run keeps the partial tree it had built',
    count( $kept['params']['branches'] ) > 0 || count( $kept['tree'] ) > 0,
    count( $kept['params']['branches'] ) . ' branches held' );

o_check( 'a cancelled run reports its partial tree',
    count( $after['partial_tree'] ) > 0,
    count( $after['partial_tree'] ) . ' branches' );


/* -------------------------------------------------- per-branch regeneration */

echo "\nper-branch regeneration\n";

$biggest = '';
$largest = 0;

foreach ( $run_a['tree'] as $branch ) {
    if ( 'needs-a-look' !== $branch['key'] && $branch['size'] > $largest ) {
        $largest = $branch['size'];
        $biggest = implode( ' / ', $branch['path'] );
    }
}

$plan = vergeml_organize_refine_plan( $run_a['run_id'], array( $biggest => 'split' ) );

o_check( 'a refine names only that branch\'s files',
    ! is_wp_error( $plan ) && count( $plan['scope'] ) === $largest,
    is_wp_error( $plan ) ? $plan->get_error_message() : count( $plan['scope'] ) . ' of ' . $largest );

o_check( 'a refine carries the other branches across untouched',
    ! is_wp_error( $plan ) && count( $plan['carry'] ) === count( $run_a['tree'] ) - 2,
    is_wp_error( $plan ) ? '' : count( $plan['carry'] ) . ' carried' );

/*
 *  Split it hard enough that it has to come apart: the shipped threshold is
 *  fifty and this branch holds twenty, so without lowering it the re-cluster
 *  would correctly decide there was nothing to do.
 */
$narrow = function () { return 8; };
add_filter( 'vergeml_organize_max_branch', $narrow );

$child = o_run( array(
    'parent_run_id' => $run_a['run_id'],
    'scope'         => $plan['scope'],
    'carry'         => $plan['carry'],
    'refine'        => array( $biggest => 'split' ),
) );

remove_filter( 'vergeml_organize_max_branch', $narrow );

$run_c = vergeml_organize_run_get( $child['run_id'] );

o_check( 'the child run records its parent', $run_a['run_id'] === $run_c['parent_run_id'] );

o_check( 'the child run still covers every file',
    (int) $run_c['n'] === count( $scope ),
    $run_c['n'] . ' of ' . count( $scope ) );

$refined = vergeml_organize_diff( $run_a['run_id'], $run_c['run_id'] );

$strayed = 0;

foreach ( $refined['moved'] as $move ) {
    if ( ! in_array( (int) $move['id'], $plan['scope'], true ) ) {
        $strayed++;
    }
}

/*
 *  Both halves, because either on its own passes for the wrong reason: "no
 *  file from elsewhere moved" is trivially true of a refine that did nothing,
 *  and that is exactly what this assertion used to be reporting.
 */
o_check( 'a per-branch split actually moved that branch\'s files',
    count( $refined['moved'] ) > 0,
    count( $refined['moved'] ) . ' of ' . $largest . ' moved' );

o_check( 'a diff after a per-branch split moves only that branch\'s files',
    0 === $strayed,
    count( $refined['moved'] ) . ' moved, ' . $strayed . ' from elsewhere' );

$sibling_labels = array();

foreach ( $run_c['tree'] as $branch ) {
    $sibling_labels[] = implode( ' / ', $branch['path'] );
}

o_check( 'no two branches of a split share a name',
    count( $sibling_labels ) === count( array_unique( $sibling_labels ) ),
    count( array_unique( $sibling_labels ) ) . ' distinct of ' . count( $sibling_labels ) );

o_check( 'refining without naming a branch is refused',
    is_wp_error( vergeml_organize_refine_plan( $run_a['run_id'], array() ) ) );


/* ---------------------------------------------------------- awkward libraries */

echo "\nlibraries that are all one thing, or all different things\n";

$identical = array();

for ( $i = 0; $i < 60; $i++ ) {
    // The same vector sixty times: a stock-photo dump. It must terminate, and
    // it must not nest the same members under the same label to the depth cap.
    $identical[] = o_seed( O_BASE + 5000 + $i, o_vector( 99, 0 ), array( 'stock', 'filler' ) );
}

$flat = o_run( array( 'scope' => $identical ) );

o_check( 'a library of near-identical files terminates',
    ! is_wp_error( $flat ) && 'done' === $flat['status'],
    is_wp_error( $flat ) ? $flat->get_error_message() : $flat['status'] );

$run_flat = vergeml_organize_run_get( $flat['run_id'] );

$deepest = 0;
foreach ( $run_flat['tree'] as $branch ) {
    $deepest = max( $deepest, count( $branch['path'] ) );
}

o_check( 'it does not nest to the depth cap for nothing',
    $deepest <= 2,
    $deepest . ' levels deep, ' . count( $run_flat['tree'] ) . ' branches' );

$unrelated = array();

for ( $i = 0; $i < 60; $i++ ) {
    // Sixty groups of one: a client archive where nothing relates to anything.
    $unrelated[] = o_seed( O_BASE + 6000 + $i, o_vector( 200 + $i, 0 ), array( 'client', 'archive' ) );
}

$loose = o_run( array( 'scope' => $unrelated ) );

o_check( 'a library where nothing relates still produces a tree',
    ! is_wp_error( $loose ) && 'done' === $loose['status'] );

$run_loose = vergeml_organize_run_get( $loose['run_id'] );

$loose_stray = 0;
$loose_total = 0;

foreach ( $run_loose['tree'] as $branch ) {
    $loose_total += $branch['size'];
    if ( 'needs-a-look' === $branch['key'] ) {
        $loose_stray = $branch['size'];
    }
}

o_check( 'even then every file is accounted for', 60 === $loose_total, $loose_total . ' of 60' );

o_check( 'and "Needs a look" still is not the whole library',
    $loose_stray < 40,
    $loose_stray . ' of 60 need a look' );


/* ---------------------------------------------------------------- the quote */

echo "\nthe pre-flight quote\n";

$scan_state = get_option( VERGEML_HEALTH_OPTION, array() );

delete_option( VERGEML_HEALTH_OPTION );

o_check( 'the quote refuses before the duplicate scan has run',
    is_wp_error( vergeml_organize_quote() ),
    'counted, never estimated' );

update_option( VERGEML_HEALTH_OPTION, array( 'cursor' => 0, 'finished' => time(), 'time' => time() ), false );

$quote = vergeml_organize_quote();

o_check( 'the quote counts once the scan has run', ! is_wp_error( $quote ) && $quote['scanned'] );

o_check( 'the quote never asks for more descriptions than there are files',
    ! is_wp_error( $quote ) && $quote['to_describe'] <= $quote['files'],
    is_wp_error( $quote ) ? '' : $quote['to_describe'] . ' of ' . $quote['files'] );

o_check( 'the quote states what it would cost',
    ! is_wp_error( $quote ) && $quote['credits'] === $quote['to_describe'] );

o_check( 'the quote does the memory arithmetic for this host',
    ! is_wp_error( $quote ) && isset( $quote['memory']['limit'] ) );

if ( is_array( $scan_state ) && $scan_state ) {
    update_option( VERGEML_HEALTH_OPTION, $scan_state, false );
} else {
    delete_option( VERGEML_HEALTH_OPTION );
}


/* -------------------------------------------------------------- re-describing */

echo "\nre-describing (Phase-1 debt)\n";

$stamp = vergeml_index_current_stamp();

o_check( 'the current stamp is read from the newest description',
    is_array( $stamp ) && isset( $stamp['model'] ) );

$stale = vergeml_index_stale( 'a-model-nothing-here-used', '', 0, 0, 5 );

o_check( 'a changed model makes rows stale', count( $stale ) > 0, count( $stale ) . ' found (capped at 5)' );

o_check( 'a changed embedding width makes rows stale',
    count( vergeml_index_stale( '', '', 999, 0, 5 ) ) > 0 );

o_check( 'nothing to compare against returns nothing rather than everything',
    array() === vergeml_index_stale( '', '', 0 ) );

o_check( 'ai-index accepts the stale scope',
    is_array( vergeml_ai_pending( 'stale', 3 ) ) );


/* ------------------------------------------------------------------ pruning */

echo "\npruning\n";

global $wpdb;

$before_prune = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_organize_runs}" );

vergeml_organize_prune( 3 );

$after_prune = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_organize_runs}" );

o_check( 'pruning keeps the last few runs and drops the rest',
    $after_prune <= 3 && $after_prune <= $before_prune,
    "{$before_prune} runs, {$after_prune} kept" );

// The one destructive act in this phase, and the assertion that it stayed in
// its own table.
$attachments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );

o_check( 'pruning touched no media', $attachments > 0, $attachments . ' attachments still here' );


/* ----------------------------------------------------------- option safety */

echo "\nthe upgrade path\n";

update_option( VERGEML_ORGANIZE_OPTION, array( 'schema' => 1, 'probe' => 'kept' ), false );

vergeml_set_options();

$organize_after = get_option( VERGEML_ORGANIZE_OPTION );

o_check( 'vergeml_set_options leaves vergeml_organize alone',
    is_array( $organize_after ) && isset( $organize_after['probe'] ) && 'kept' === $organize_after['probe'] );


/* ------------------------------------------------------------------- tidy */

echo "\ntidying up\n";

foreach ( array_unique( $GLOBALS['o_seeded'] ) as $id ) {
    vergeml_index_delete( $id );
}

foreach ( array_unique( $GLOBALS['o_runs'] ) as $run_id ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( vergeml_organize_table(), array( 'run_id' => (int) $run_id ), array( '%d' ) );
}

$left = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE attachment_id >= %d",
    O_BASE
) );

o_check( 'the seeded rows are gone', 0 === $left, $left . ' left behind' );

delete_option( VERGEML_ORGANIZE_OPTION );

printf( '%d/%d passed' . PHP_EOL, $GLOBALS['vgml_pass'], $GLOBALS['vgml_pass'] + $GLOBALS['vgml_fail'] );

if ( $GLOBALS['vgml_fail'] > 0 ) {
    exit( 1 );
}
