<?php
/*
 *  The planner's choice among the service's trees, on fixtures.
 *
 *  Local: no WordPress, no database, no box. vergeml_plan_choose() is
 *  arithmetic on label vectors, so two-dimensional ones do: a tree whose
 *  folders hold pictures pointing the same way is tighter than one that mixes
 *  them, and a label the model left out joins the folder it points at only
 *  when the cosine is VERGEML_PLAN_PLACE or more.
 *
 *      node tools/verify.mjs plan-choose
 *
 *  The mutations it catches: the comparison turned (the looser tree kept) ->
 *  row 1; the nearest folder turned into the farthest -> row 3; the
 *  threshold dropped -> row 4; tightness counted without the pictures -> row 2.
 */

define( 'ABSPATH', '/' );

// What core/plan-tree.php calls at load; nothing else is reached.
function add_action() {}

require dirname( __DIR__, 2 ) . '/core/plan-tree.php';

$GLOBALS['pc_pass'] = 0;
$GLOBALS['pc_fail'] = 0;

function pc_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['pc_pass']++;
    } else {
        $GLOBALS['pc_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note );
}

// Each label's pictures as the sum of their unit vectors: a, b and c's pictures point one way each; d, e and f are left out.
$sums   = array(
    'a' => array( 3.0, 0.0 ),   // three pictures along x
    'b' => array( 2.0, 0.0 ),   // two along x
    'c' => array( 0.0, 4.0 ),   // four along y
    'd' => array( 0.0, 1.0 ),   // one along y: cosine 1 to a y folder
    'e' => array( -1.0, 0.2 ),  // one pointing away from both: cosine below 0.2 to anything
    'f' => array( 0.6, 0.8 ),   // one between: cosine 0.8 to y, 0.6 to x
);
$counts = array( 'a' => 3, 'b' => 2, 'c' => 4, 'd' => 1, 'e' => 1, 'f' => 1 );

$tight = array(
    'folders' => array(
        array( 'path' => 'X', 'labels' => array( 'a', 'b' ) ),
        array( 'path' => 'Y', 'labels' => array( 'c' ) ),
    ),
    'unfiled' => array( 'd', 'e', 'f' ),
);
$loose = array(
    'folders' => array(
        array( 'path' => 'X', 'labels' => array( 'a', 'c' ) ),
        array( 'path' => 'Y', 'labels' => array( 'b' ) ),
    ),
    'unfiled' => array( 'd', 'e', 'f' ),
);

$pc = vergeml_plan_choose( array( $loose, $tight ), $sums, $counts );
$in = array();
foreach ( $pc['folders'] as $f ) {
    $in[ $f['path'] ] = $f['labels'];
}

pc_check( '1. the tighter tree is kept, whatever its place in the list', 1 === $pc['index'], 'kept ' . $pc['index'] );
// X holds a and b: [5, 0] over 5 pictures. Y holds c, d and f once placed: [0.6, 5.8] over 6. Eleven pictures in all.
pc_check( '2. its tightness is the mean cosine of a picture to its folder', abs( $pc['tightness'] - ( 5 + sqrt( 34 ) ) / 11 ) < 1e-9, sprintf( '%.6f', $pc['tightness'] ) );
pc_check( '3. a label left out joins the folder it points at', in_array( 'd', $in['Y'], true ) && ! in_array( 'd', $in['X'], true ), wp_json( $in ) );
pc_check( '4. a label that points at nothing stays out', ! in_array( 'e', $in['X'], true ) && ! in_array( 'e', $in['Y'], true ), wp_json( $in ) );
pc_check( '5. a label between two folders joins the nearer', in_array( 'f', $in['Y'], true ), wp_json( $in ) );
pc_check( '6. the placed count is the labels placed', 2 === $pc['placed'], (string) $pc['placed'] );

// One tree -- an older service's answer -- is kept, and still has its labels placed.
$one = vergeml_plan_choose( array( $tight ), $sums, $counts );
pc_check( '7. a single tree is kept and placed', 0 === $one['index'] && 2 === $one['placed'], $one['index'] . '/' . $one['placed'] );

// A label with no vector (described before the index held one) is neither counted nor placed.
$gap = vergeml_plan_choose( array( array( 'folders' => $tight['folders'], 'unfiled' => array( 'z' ) ) ), $sums, $counts );
pc_check( '8. a label with no vector stays out', 0 === $gap['placed'], (string) $gap['placed'] );

function wp_json( $v ) {
    return json_encode( $v );
}

printf( "\n%d/%d passed\n", $GLOBALS['pc_pass'], $GLOBALS['pc_pass'] + $GLOBALS['pc_fail'] );

if ( $GLOBALS['pc_fail'] > 0 ) {
    exit( 1 );
}
