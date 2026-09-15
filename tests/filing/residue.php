<?php
/*
 *  The residue, grouped, named and answered -- on fixtures.
 *
 *  Local, like pick.php: no WordPress, no database. Forty pictures the fill
 *  could not place go through vergeml_filing_residue_groups(); the groups and
 *  a sibling tally go through vergeml_filing_questions(); each answer goes
 *  through vergeml_filing_answer_plan() and its moves are applied to a map
 *  of attachment => folder, which is where the assertions read.
 *
 *      node tools/verify.mjs filing
 *
 *  The mutation the plan names: remove the merge-under-5 rule and the three
 *  "robotic arm" pictures survive as a group of their own -- row 1 goes red.
 */

define( 'ABSPATH', '/' );

function add_action() {}
function sanitize_key( $k ) {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
}
function sanitize_text_field( $t ) {
    return trim( strip_tags( (string) $t ) );
}
function vergeml_index_vector_out( $packed ) {
    return is_array( $packed ) ? $packed : null;
}
function vergeml_meaning_vector( $text ) {
    return null;
}
function vergeml_meaning_similarity( $a, $b ) {
    $dot = 0.0;
    $la  = 0.0;
    $lb  = 0.0;
    foreach ( $a as $i => $v ) {
        $o    = isset( $b[ $i ] ) ? $b[ $i ] : 0.0;
        $dot += $v * $o;
        $la  += $v * $v;
        $lb  += $o * $o;
    }
    return ( $la <= 0.0 || $lb <= 0.0 ) ? 0.0 : $dot / ( sqrt( $la ) * sqrt( $lb ) );
}

require dirname( __DIR__, 2 ) . '/core/filing.php';

$GLOBALS['f_pass'] = 0;
$GLOBALS['f_fail'] = 0;

function f_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['f_pass']++;
    } else {
        $GLOBALS['f_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note );
}


/* ---------------------------------------------------------- forty pictures */

/*
 *  Five directions in a five-dimensional space, one per subject, so "near"
 *  and "far" are exact: cosine 1 inside a subject, 0 between subjects, and
 *  the two small groups that are meant to merge lean 0.8 towards the big one
 *  they belong with.
 */
function f_vec( $axis, $lean_axis = -1, $lean = 0.0 ) {
    $v = array( 0.0, 0.0, 0.0, 0.0, 0.0 );
    $v[ $axis ] = 1.0;
    if ( $lean_axis >= 0 ) {
        $v[ $axis ]      = sqrt( 1 - $lean * $lean );
        $v[ $lean_axis ] = $lean;
    }
    return $v;
}

$facts = array();
$n     = 100;
$add   = function ( $count, $object, $vector ) use ( &$facts, &$n ) {
    for ( $i = 0; $i < $count; $i++ ) {
        $facts[ $n++ ] = array(
            'classes'   => vergeml_filing_classes_of_object( $object ),
            'kind'      => 'photo',
            'audience'  => '',
            'vector'    => $vector,
            'placed_by' => '',
        );
    }
};

$add( 14, 'robot arm; machinery', f_vec( 0 ) );                 // 100-113
$add( 3, 'robotic arm; machinery', f_vec( 1, 0, 0.8 ) );        // 114-116  a group of 3 that leans to the robot arms
$add( 9, 'conveyor belt; machinery', f_vec( 2 ) );              // 117-125
$add( 2, 'forklift; vehicle', f_vec( 3, 2, 0.8 ) );             // 126-127  a pair that leans to the conveyors
$add( 4, 'keynote speaker; person', f_vec( 4 ) );               // 128-131  four on their own axis: a small group that stands
$add( 2, 'blurry; unknown', f_vec( 1 ) );                       // 132-133  near nothing
$add( 2, '', null );                                            // 134-135  no class at all
$add( 2, 'sticker; label', null );                              // 136-137  a class and no vector
$add( 2, 'sign; signage', array( 0.0, 0.0, 0.0, -1.0, 0.0 ) ); // 138-139  near nothing (the forklifts' axis, the other way)

f_check( '0 forty pictures', 40 === count( $facts ), (string) count( $facts ) );

echo "\n== the groups\n";

$groups = vergeml_filing_residue_groups( $facts );

$sizes = array_map( function ( $g ) { return $g['count']; }, $groups );
$last  = end( $groups );
f_check(
    '1 four groups: robot arm 17 (the 3 robotic arms merged in), conveyor belt 11 (the 2 forklifts merged in), keynote speaker 4, and the 8 unreadable last',
    4 === count( $groups ) && array( 17, 11, 4, 8 ) === array_values( $sizes )
        && 'robot arm' === $groups[0]['class'] && 'conveyor belt' === $groups[1]['class'] && 'keynote speaker' === $groups[2]['class']
        && ! empty( $last['unreadable'] ) && '' === $last['class'],
    json_encode( array_map( function ( $g ) { return array( $g['class'], $g['count'], ! empty( $g['unreadable'] ) ); }, $groups ) )
);
f_check( '2 the merged group carries both classes, the bigger first', isset( $groups[0]['classes'] ) && array( 'robot arm', 'robotic arm' ) === array_keys( $groups[0]['classes'] ) && 14 === $groups[0]['classes']['robot arm'], json_encode( isset( $groups[0]['classes'] ) ? $groups[0]['classes'] : null ) );
f_check( '3 every one of the forty is in exactly one group', 40 === array_sum( $sizes ) && 40 === count( array_unique( array_merge( ...array_map( function ( $g ) { return $g['ids']; }, $groups ) ) ) ), (string) array_sum( $sizes ) );
f_check( '4 the unreadable group is the pictures near nothing, without a class, or without a vector', array( 132, 133, 134, 135, 136, 137, 138, 139 ) === array_values( $last['ids'] ), json_encode( array_values( $last['ids'] ) ) );
f_check( '5 a group has a centroid where its pictures have vectors, and none where they do not', is_array( $groups[0]['centroid'] ) && 5 === count( $groups[0]['centroid'] ), '' );

echo "\n== the questions\n";

$siblings = array(
    // Parent 1 (Data centres): six pictures that tied between its children 2 and 3; two of them between 2 and 4.
    1 => array(
        'ids'      => array( 201 => 2, 202 => 3, 203 => 2, 204 => 2, 205 => 3, 206 => 2 ),
        'children' => array( 2 => 6, 3 => 4, 4 => 2 ),
    ),
);
$names   = array( 0 => 'Robotics', 1 => 'Conveyors' ); // The keynote group (2) got no name: the class word stands in.
$nearest = array( 0 => 7, 1 => 7, 2 => 0 );            // Group 2 has no folder near enough to offer.

$questions = vergeml_filing_questions( $groups, $siblings, $names, $nearest );

f_check( '6 five questions: the sibling parent first, then the groups by size, the unreadable last', 5 === count( $questions ) && array( 'siblings', 'residue', 'residue', 'residue', 'residue' ) === array_column( $questions, 'kind' ), json_encode( array_column( $questions, 'id' ) ) );

$q = $questions[0];
f_check( '7 the sibling question: id s:1, term 1, the two children most often tied (2, 3), count 6, sample of 6, three answers', 's:1' === $q['id'] && 1 === $q['term_id'] && array( 2, 3 ) === $q['children'] && 6 === $q['count'] && 6 === count( $q['sample'] ) && array( 'keep-parent', 'split', 'show-me' ) === $q['answers'], json_encode( array( $q['id'], $q['term_id'], $q['children'], $q['count'], count( $q['sample'] ), $q['answers'] ) ) );

$q = $questions[1];
f_check( '8 the named group: id r:0, Robotics, count 17, eight in the sample, new-folder / put-in:7 / leave / show-me', 'r:0' === $q['id'] && 'Robotics' === $q['name'] && 17 === $q['count'] && 8 === count( $q['sample'] ) && array( 'new-folder', 'put-in:7', 'leave', 'show-me' ) === $q['answers'] && empty( $q['unreadable'] ), json_encode( array( $q['id'], $q['name'], $q['count'], count( $q['sample'] ), $q['answers'] ) ) );

$q = $questions[3];
f_check( '9 a group with no name and no folder near: the class word as its name, no put-in', 'r:2' === $q['id'] && 'Keynote speaker' === $q['name'] && array( 'new-folder', 'leave', 'show-me' ) === $q['answers'], json_encode( array( $q['id'], $q['name'], $q['answers'] ) ) );

$q = $questions[4];
f_check( '10 the unreadable group: no name, count 8, leave / show-me only', 'r:3' === $q['id'] && '' === $q['name'] && 8 === $q['count'] && ! empty( $q['unreadable'] ) && array( 'leave', 'show-me' ) === $q['answers'], json_encode( array( $q['id'], $q['name'], $q['count'], $q['answers'] ) ) );

echo "\n== the answers, on a map of attachment => folder\n";

/*
 *  The map stands in for the term relationships. A plan's moves name a term
 *  id, or 'new' for the folder the answer makes, or 'to-sort' for the To
 *  sort folder -- the executor resolves those two; here they are 99 and 98.
 */
function f_apply( $map, $plan ) {
    foreach ( (array) $plan['moves'] as $id => $to ) {
        $map[ $id ] = 'new' === $to ? 99 : ( 'to-sort' === $to ? 98 : (int) $to );
    }
    return $map;
}

$map = array();
foreach ( $facts as $id => $f ) {
    $map[ $id ] = 0; // Residue: in no folder.
}
foreach ( $siblings[1]['ids'] as $id => $best ) {
    $map[ $id ] = 1; // Placed in the parent by the fill.
}

$plan = vergeml_filing_answer_plan( $questions[0], 'keep-parent' );
$m    = f_apply( $map, $plan );
f_check( '11 keep-parent: answered, nothing moves, the six stay in 1', ! empty( $plan['answered'] ) && array() === $plan['moves'] && array( 1, 1, 1, 1, 1, 1 ) === array_values( array_intersect_key( $m, $siblings[1]['ids'] ) ), json_encode( $plan ) );

$plan = vergeml_filing_answer_plan( $questions[0], 'split' );
$m    = f_apply( $map, $plan );
f_check( '12 split: each of the six goes to its own best child, not by hand', ! empty( $plan['answered'] ) && $siblings[1]['ids'] === array_intersect_key( $m, $siblings[1]['ids'] ) && empty( $plan['placed_by'] ), json_encode( array_intersect_key( $m, $siblings[1]['ids'] ) ) );

$plan = vergeml_filing_answer_plan( $questions[1], 'new-folder' );
$m    = f_apply( $map, $plan );
$in   = array_intersect_key( $m, array_flip( $questions[1]['ids'] ) );
f_check( '13 new-folder: makes Robotics, all 17 go in, by the user\'s hand', ! empty( $plan['answered'] ) && 'Robotics' === $plan['make'] && 17 === count( $in ) && array( 99 ) === array_values( array_unique( $in ) ) && ! empty( $plan['placed_by'] ), json_encode( array( $plan['make'], count( $in ), array_values( array_unique( $in ) ) ) ) );

$plan = vergeml_filing_answer_plan( $questions[2], 'put-in:7' );
$m    = f_apply( $map, $plan );
$in   = array_intersect_key( $m, array_flip( $questions[2]['ids'] ) );
f_check( '14 put-in:7: all 11 go into 7, by the user\'s hand, no folder made', ! empty( $plan['answered'] ) && null === $plan['make'] && 11 === count( $in ) && array( 7 ) === array_values( array_unique( $in ) ) && ! empty( $plan['placed_by'] ), json_encode( array_values( array_unique( $in ) ) ) );

$plan = vergeml_filing_answer_plan( $questions[4], 'leave' );
$m    = f_apply( $map, $plan );
$in   = array_intersect_key( $m, array_flip( $questions[4]['ids'] ) );
f_check( '15 leave: all 8 go into To sort -- none is left in no folder', ! empty( $plan['answered'] ) && 8 === count( $in ) && array( 98 ) === array_values( array_unique( $in ) ) && ! in_array( 0, $in, true ), json_encode( array_values( array_unique( $in ) ) ) );

$plan = vergeml_filing_answer_plan( $questions[1], 'show-me' );
$m    = f_apply( $map, $plan );
f_check( '16 show-me: not answered, nothing moves, the ids come back', empty( $plan['answered'] ) && array() === $plan['moves'] && $questions[1]['ids'] === $plan['show'] && $map === $m, json_encode( array( $plan['answered'], count( $plan['show'] ) ) ) );

f_check( '17 an answer the question does not offer is refused', null === vergeml_filing_answer_plan( $questions[4], 'new-folder' ) && null === vergeml_filing_answer_plan( $questions[0], 'leave' ) && null === vergeml_filing_answer_plan( $questions[1], 'put-in:5' ) && null === vergeml_filing_answer_plan( $questions[1], 'delete' ), '' );

printf( "\n%d/%d passed\n", $GLOBALS['f_pass'], $GLOBALS['f_pass'] + $GLOBALS['f_fail'] );
exit( $GLOBALS['f_fail'] > 0 ? 1 : 0 );
