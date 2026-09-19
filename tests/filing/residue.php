<?php
/*
 *  The residue, grouped, named and answered -- on fixtures.
 *
 *  Local, like pick.php: no WordPress, no database. Fifty-five pictures the
 *  fill could not place go through vergeml_filing_residue_groups(); the groups
 *  and a sibling tally go through vergeml_filing_questions(); each answer goes
 *  through vergeml_filing_answer_plan() and its moves are applied to a map
 *  of attachment => folder, which is where the assertions read.
 *
 *      node tools/verify.mjs filing
 *
 *  The fixture has the box's residue in miniature (2026-09-15: a 0.5 cosine
 *  that pulled sixteen towers in with nine racks, seven screenshots grouped
 *  with photographs, a seed that kept its label after the majority changed).
 *  The mutations the plan names, each with the row that goes red: the kind
 *  key removed -> row 1; GROUP_NEAR back to 0.5 -> row 1b; the cap removed
 *  -> row 1e; the majority rule for put-in removed -> row 5b.
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


/* ------------------------------------------------------- fifty-five pictures */

/*
 *  Seven directions in a seven-dimensional space, one per subject, so "near"
 *  and "far" are exact: cosine 1 inside a subject, 0 between subjects, and a
 *  group that is meant to lean towards another leans by exactly the cosine
 *  given.
 */
function f_vec( $axis, $lean_axis = -1, $lean = 0.0 ) {
    $v = array_fill( 0, 7, 0.0 );
    $v[ $axis ] = 1.0;
    if ( $lean_axis >= 0 ) {
        $v[ $axis ]      = sqrt( 1 - $lean * $lean );
        $v[ $lean_axis ] = $lean;
    }
    return $v;
}

$facts = array();
$n     = 100;
$add   = function ( $count, $object, $vector, $kind = 'photo' ) use ( &$facts, &$n ) {
    for ( $i = 0; $i < $count; $i++ ) {
        $facts[ $n++ ] = array(
            'classes'   => vergeml_filing_classes_of_object( $object ),
            'kind'      => $kind,
            'audience'  => '',
            'vector'    => $vector,
            'placed_by' => '',
        );
    }
};

$add( 14, 'robot arm; machinery', f_vec( 0 ) );                          // 100-113
$add( 3, 'robotic arm; machinery', f_vec( 1, 0, 0.85 ) );                // 114-116  three at cosine 0.85 to the robot arms: merge
$add( 9, 'conveyor belt; machinery', f_vec( 2 ) );                       // 117-125
$add( 3, 'forklift; vehicle', f_vec( 3, 2, 0.6 ) );                      // 126-128  three at cosine 0.6 to the conveyors: do not merge (0.5 did)
$add( 4, 'keynote speaker; person', f_vec( 4 ) );                        // 129-132  four on their own axis: too small to ask about alone
$add( 7, 'settings page; screenshot', f_vec( 0 ), 'screenshot' );        // 133-139  seven screenshots on the robot arms' own axis
$add( 3, 'espresso maker; appliance', f_vec( 5 ) );                      // 140-142  a seed of three ...
$add( 4, 'coffee machine; appliance', f_vec( 6, 5, 0.85 ) );             // 143-146  ... four join it and the majority changes
$add( 2, 'blurry; unknown', f_vec( 1 ) );                                // 147-148  near nothing
$add( 2, '', null );                                                     // 149-150  no class at all
$add( 2, 'sticker; label', null );                                       // 151-152  a class and no vector
$add( 2, 'sign; signage', array( 0.0, 0.0, 0.0, -1.0, 0.0, 0.0, 0.0 ) ); // 153-154  near nothing

f_check( '0 fifty-five pictures', 55 === count( $facts ), (string) count( $facts ) );

echo "\n== the groups\n";

$groups = vergeml_filing_residue_groups( $facts );

$brief = function ( $g ) { return array( $g['class'], $g['count'], isset( $g['kind'] ) ? $g['kind'] : null, isset( $g['share'] ) ? round( $g['share'], 3 ) : null, ! empty( $g['more'] ), ! empty( $g['unreadable'] ) ); };
$sizes = array_map( function ( $g ) { return $g['count']; }, $groups );
f_check(
    '1 six groups: robot arm 17, conveyor belt 9, screenshot 7 (kind, never merged with the robot arms), coffee machine 7, then 13 more in small groups, then 2 with nothing to go on',
    6 === count( $groups ) && array( 17, 9, 7, 7, 13, 2 ) === array_values( $sizes )
        && 'robot arm' === $groups[0]['class'] && 'conveyor belt' === $groups[1]['class'] && 'screenshot' === $groups[2]['class'] && 'screenshot' === $groups[2]['kind'] && 'coffee machine' === $groups[3]['class']
        && ! empty( $groups[4]['more'] ) && ! empty( $groups[5]['unreadable'] ),
    json_encode( array_map( $brief, $groups ) )
);
f_check( '1b the three at cosine 0.6 did not merge into the conveyors (9, not 12); the three at 0.85 did (17)', 9 === $groups[1]['count'] && array( 'conveyor belt' => 9 ) === $groups[1]['classes'] && 17 === $groups[0]['count'], json_encode( $groups[1]['classes'] ) );
f_check( '1c after merging, the class is the majority phrase with its share: robot arm 14/17 = 0.824; coffee machine flipped from the seed, 4/7 = 0.571', abs( $groups[0]['share'] - 14 / 17 ) < 1e-9 && 'coffee machine' === $groups[3]['class'] && abs( $groups[3]['share'] - 4 / 7 ) < 1e-9 && array( 'coffee machine' => 4, 'espresso maker' => 3 ) === $groups[3]['classes'], json_encode( array( $groups[0]['share'], $groups[3]['class'], $groups[3]['share'] ) ) );
f_check( '1d the small groups: forklifts 3, keynote 4, blurry 2, stickers 2, signs 2 -- one card of 13; the unreadable are the two with no class', array( 126, 127, 128, 129, 130, 131, 132, 147, 148, 151, 152, 153, 154 ) === array_values( $groups[4]['ids'] ) && array( 149, 150 ) === array_values( $groups[5]['ids'] ), json_encode( array( array_values( $groups[4]['ids'] ), array_values( $groups[5]['ids'] ) ) ) );
f_check( '2 every one of the fifty-five is in exactly one group', 55 === array_sum( $sizes ) && 55 === count( array_unique( array_merge( ...array_map( function ( $g ) { return $g['ids']; }, $groups ) ) ) ), (string) array_sum( $sizes ) );
f_check( '3 the centroid is over the merged vectors: the robot arms lean where the three pulled them', is_array( $groups[0]['centroid'] ) && 7 === count( $groups[0]['centroid'] ) && $groups[0]['centroid'][1] > 0.05 && $groups[0]['centroid'][0] > 0.9, json_encode( array_map( function ( $x ) { return round( $x, 3 ); }, $groups[0]['centroid'] ) ) );

// Twelve readable groups of five, nothing alike: the eight largest are asked, the other four are one card.
$twelve = array();
$m      = 500;
foreach ( range( 1, 12 ) as $i ) {
    for ( $j = 0; $j < 5 + ( 12 - $i ); $j++ ) {
        $twelve[ $m++ ] = array( 'classes' => array( 'subject ' . $i, 'thing' ), 'kind' => 'photo', 'audience' => '', 'vector' => null, 'placed_by' => '' );
    }
}
$capped = vergeml_filing_residue_groups( $twelve );
f_check( '1e twelve groups -> nine: the eight largest by size, then one card with the other four (5+6+7+8 = 26)', 9 === count( $capped ) && ! empty( $capped[8]['more'] ) && 26 === $capped[8]['count'] && 'subject 1' === $capped[0]['class'] && 'subject 8' === $capped[7]['class'] && empty( $capped[7]['more'] ), json_encode( array_map( $brief, $capped ) ) );

echo "\n== the questions\n";

$siblings = array(
    // Parent 1 (Data centres): six pictures that tied between its children 2 and 3; two of them between 2 and 4.
    1 => array(
        'ids'      => array( 201 => 2, 202 => 3, 203 => 2, 204 => 2, 205 => 3, 206 => 2 ),
        'children' => array( 2 => 6, 3 => 4, 4 => 2 ),
    ),
);
$names   = array( 0 => 'Robotics', 1 => 'Conveyors' ); // The screenshot and coffee groups got no name: the class word stands in.
$nearest = array( 0 => 7, 1 => 7, 2 => 0, 3 => 0 );    // Groups 2 and 3 have no folder that most of them are nearest to.

$questions = vergeml_filing_questions( $groups, $siblings, $names, $nearest );

f_check( '4 seven questions: the sibling parent first, then the four groups by size, the small-groups card, the unreadable last', 7 === count( $questions ) && array( 'siblings', 'residue', 'residue', 'residue', 'residue', 'residue', 'residue' ) === array_column( $questions, 'kind' ), json_encode( array_column( $questions, 'id' ) ) );

$q = $questions[0];
// Six pictures, and the card shows all six: no Let me look, because there is nothing it has not already shown (S21).
f_check( '4b the sibling question: id s:1, term 1, the two children most often tied (2, 3), count 6, sample of 6, two answers and no look', 's:1' === $q['id'] && 1 === $q['term_id'] && array( 2, 3 ) === $q['children'] && 6 === $q['count'] && 6 === count( $q['sample'] ) && array( 'keep-parent', 'split' ) === $q['answers'], json_encode( array( $q['id'], $q['term_id'], $q['children'], $q['count'], count( $q['sample'] ), $q['answers'] ) ) );

$q = $questions[1];
f_check( '4c the named group: id r:0, Robotics, count 17, share 0.824, eight in the sample, new-folder / put-in:7 / leave / show-me', 'r:0' === $q['id'] && 'Robotics' === $q['name'] && 17 === $q['count'] && abs( $q['share'] - 14 / 17 ) < 1e-9 && 8 === count( $q['sample'] ) && array( 'new-folder', 'put-in:7', 'leave', 'show-me' ) === $q['answers'] && empty( $q['unreadable'] ) && empty( $q['more'] ), json_encode( array( $q['id'], $q['name'], $q['count'], $q['share'], count( $q['sample'] ), $q['answers'] ) ) );

$q = $questions[3];
// Seven pictures, all seven on the card: no Show me here either.
f_check( '4d a group with no name and no folder near: the class word as its name, no put-in, no Show me on its seven; the mixed one carries its share', 'r:2' === $q['id'] && 'Screenshot' === $q['name'] && 'screenshot' === $q['group_kind'] && array( 'new-folder', 'leave' ) === $q['answers'] && 'r:3' === $questions[4]['id'] && abs( $questions[4]['share'] - 4 / 7 ) < 1e-9, json_encode( array( $q['id'], $q['name'], $q['group_kind'], $q['answers'], $questions[4]['share'] ) ) );

$q = $questions[5];
// Thirteen against a card of eight: Show me has five it has not shown, and keeps it. The unreadable two do not.
f_check( '4e the small-groups card: id r:4, more, count 13, leave / show-me; the unreadable one: r:5, count 2, leave alone', 'r:4' === $q['id'] && ! empty( $q['more'] ) && 13 === $q['count'] && array( 'leave', 'show-me' ) === $q['answers'] && 'r:5' === $questions[6]['id'] && ! empty( $questions[6]['unreadable'] ) && 2 === $questions[6]['count'] && array( 'leave' ) === $questions[6]['answers'], json_encode( array( $q['id'], $q['count'], $q['answers'], $questions[6]['id'], $questions[6]['count'] ) ) );

/*
 *  The boundary, stated on its own (S21). Show me returns the group's
 *  pictures and the card already shows VERGEML_FILING_SAMPLE of them, so on a
 *  group of eight it showed exactly what was on the screen and nothing
 *  changed -- Nathan, on the real shop's one-picture card (S19).
 */
$edge = vergeml_filing_questions(
    array(
        array( 'ids' => range( 700, 707 ), 'count' => 8, 'class' => 'edge eight', 'kind' => 'photo', 'share' => 1.0 ),
        array( 'ids' => range( 710, 718 ), 'count' => 9, 'class' => 'edge nine', 'kind' => 'photo', 'share' => 1.0 ),
    ),
    array(),
    array(),
    array()
);
f_check( '4f eight is the whole card and offers no Show me; nine has one it has not shown and offers it', 2 === count( $edge ) && 8 === count( $edge[0]['sample'] ) && ! in_array( 'show-me', (array) $edge[0]['answers'], true ) && in_array( 'show-me', (array) $edge[1]['answers'], true ), json_encode( array( $edge[0]['answers'], $edge[1]['answers'] ) ) );

echo "\n== put in X: only when X is where most of the group would go\n";

$profiles = array(
    7 => array( 'kinds' => array( 'photo', 'illustration' ), 'audience' => '', 'locked' => false ),
    8 => array( 'kinds' => array( 'photo' ), 'audience' => '', 'locked' => true ),
    9 => array( 'kinds' => array( 'photo' ), 'audience' => 'women', 'locked' => false ),
);
$sixty_one = range( 1000, 1060 );
$near_9    = array_fill_keys( array_slice( $sixty_one, 0, 9 ), 7 ) + array_fill_keys( $sixty_one, 0 );
$near_40   = array_fill_keys( array_slice( $sixty_one, 0, 40 ), 7 ) + array_fill_keys( $sixty_one, 0 );
$g61       = array( 'ids' => $sixty_one, 'count' => 61, 'kind' => 'photo' );
f_check( '5a 9 of 61 nearest folder 7 -> no put-in (the 61 on the box: 9 racks, 16 towers, and Put in Server racks took all of them)', 0 === vergeml_filing_group_nearest( $g61, $near_9, $profiles ), '' );
f_check( '5b 40 of 61 nearest folder 7 -> put-in:7', 7 === vergeml_filing_group_nearest( $g61, $near_40, $profiles ), '' );
f_check( '5c the majority folder still has to pass the gates: a screenshot group is not offered a photo folder; a locked one is never offered; nor is one for women to a group that does not say so', 0 === vergeml_filing_group_nearest( array( 'kind' => 'screenshot' ) + $g61, array_fill_keys( $sixty_one, 7 ), $profiles ) && 0 === vergeml_filing_group_nearest( $g61, array_fill_keys( $sixty_one, 8 ), $profiles ) && 0 === vergeml_filing_group_nearest( $g61, array_fill_keys( $sixty_one, 9 ), $profiles ) && 7 === vergeml_filing_group_nearest( $g61, array_fill_keys( $sixty_one, 7 ), $profiles ), '' );

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
f_check( '11 keep-parent: answered, the six stay in 1 and are marked answered, so the next fill leaves them (2026-09-17)', ! empty( $plan['answered'] ) && array_fill_keys( array_keys( $siblings[1]['ids'] ), 1 ) === $plan['moves'] && 'answer' === $plan['placed_by'] && array( 1, 1, 1, 1, 1, 1 ) === array_values( array_intersect_key( $m, $siblings[1]['ids'] ) ), json_encode( $plan ) );

$plan = vergeml_filing_answer_plan( $questions[0], 'split' );
$m    = f_apply( $map, $plan );
f_check( '12 split: each of the six goes to its own best child, marked answered, not by hand', ! empty( $plan['answered'] ) && $siblings[1]['ids'] === array_intersect_key( $m, $siblings[1]['ids'] ) && 'answer' === $plan['placed_by'], json_encode( array_intersect_key( $m, $siblings[1]['ids'] ) ) );

$plan = vergeml_filing_answer_plan( $questions[1], 'new-folder' );
$m    = f_apply( $map, $plan );
$in   = array_intersect_key( $m, array_flip( $questions[1]['ids'] ) );
f_check( '13 new-folder: makes Robotics, all 17 go in, by the user\'s hand', ! empty( $plan['answered'] ) && 'Robotics' === $plan['make'] && 17 === count( $in ) && array( 99 ) === array_values( array_unique( $in ) ) && ! empty( $plan['placed_by'] ), json_encode( array( $plan['make'], count( $in ), array_values( array_unique( $in ) ) ) ) );

$plan = vergeml_filing_answer_plan( $questions[2], 'put-in:7' );
$m    = f_apply( $map, $plan );
$in   = array_intersect_key( $m, array_flip( $questions[2]['ids'] ) );
f_check( '14 put-in:7: all 9 go into 7, by the user\'s hand, no folder made', ! empty( $plan['answered'] ) && null === $plan['make'] && 9 === count( $in ) && array( 7 ) === array_values( array_unique( $in ) ) && ! empty( $plan['placed_by'] ), json_encode( array_values( array_unique( $in ) ) ) );

$plan = vergeml_filing_answer_plan( $questions[5], 'leave' );
$m    = f_apply( $map, $plan );
$in   = array_intersect_key( $m, array_flip( $questions[5]['ids'] ) );
f_check( '15 leave on the small-groups card: all 13 go into To sort -- none is left in no folder', ! empty( $plan['answered'] ) && 13 === count( $in ) && array( 98 ) === array_values( array_unique( $in ) ) && ! in_array( 0, $in, true ), json_encode( array_values( array_unique( $in ) ) ) );

$plan = vergeml_filing_answer_plan( $questions[1], 'show-me' );
$m    = f_apply( $map, $plan );
f_check( '16 show-me: not answered, nothing moves, the ids come back', empty( $plan['answered'] ) && array() === $plan['moves'] && $questions[1]['ids'] === $plan['show'] && $map === $m, json_encode( array( $plan['answered'], count( $plan['show'] ) ) ) );

/*
 *  A sibling question's ids are a map picture => best child; "Let me look"
 *  must hand back the pictures, not the children (on the box, 2026-09-15, it
 *  handed back term ids and the strip opened empty). Asked on a parent of
 *  nine, because a card that shows all six no longer offers it (S21).
 */
$nine_ids = array();
foreach ( range( 401, 409 ) as $n ) {
    $nine_ids[ $n ] = 0 === $n % 2 ? 3 : 2;
}
$look = vergeml_filing_questions( array(), array( 1 => array( 'ids' => $nine_ids, 'children' => array( 2 => 5, 3 => 4 ) ) ), array(), array() );
$plan = vergeml_filing_answer_plan( $look[0], 'show-me' );
f_check( '16b show-me on a sibling question of nine: the pictures, not their best children', in_array( 'show-me', (array) $look[0]['answers'], true ) && empty( $plan['answered'] ) && array_map( 'intval', array_keys( $nine_ids ) ) === $plan['show'] && ! in_array( 2, $plan['show'], true ) && ! in_array( 3, $plan['show'], true ), json_encode( $plan['show'] ) );
// And on the card that shows all six, the answer is refused as any answer it does not offer is.
f_check( '16c a card that has already shown everything refuses show-me, as it refuses any answer it does not offer', null === vergeml_filing_answer_plan( $questions[0], 'show-me' ), json_encode( $questions[0]['answers'] ) );

f_check( '17 an answer the question does not offer is refused', null === vergeml_filing_answer_plan( $questions[6], 'new-folder' ) && null === vergeml_filing_answer_plan( $questions[5], 'new-folder' ) && null === vergeml_filing_answer_plan( $questions[0], 'leave' ) && null === vergeml_filing_answer_plan( $questions[1], 'put-in:5' ) && null === vergeml_filing_answer_plan( $questions[1], 'delete' ), '' );

echo "\n== either/or: two folders that are not siblings, too close to call (C.1)\n";

/*
 *  Three pictures tied between Hardware (4) and Server racks (2) -- different
 *  parents, so the sibling rule never fired and, until 2026-09-15, they fell
 *  into the residue by their object word. Two were nearer Server racks.
 */
$either = array(
    '2:4' => array( 'ids' => array( 301 => 2, 302 => 4, 303 => 2 ), 'children' => array( 2 => 2, 4 => 1 ) ),
);
$with   = vergeml_filing_questions( $groups, $siblings, $names, $nearest, $either );
f_check( '18 the either question sits after the sibling one and before the residue', 8 === count( $with ) && array( 'siblings', 'either', 'residue', 'residue', 'residue', 'residue', 'residue', 'residue' ) === array_column( $with, 'kind' ), json_encode( array_column( $with, 'id' ) ) );
$q = $with[1];
// Three pictures, all three on the card: no show-me (S21).
f_check( '19 its shape: id e:2:4, no term, children by how often best (2, 4), count 3, put-in:2 / put-in:4 / split / leave', 'e:2:4' === $q['id'] && 0 === $q['term_id'] && array( 2, 4 ) === $q['children'] && 3 === $q['count'] && array( 301, 302, 303 ) === $q['sample'] && array( 'put-in:2', 'put-in:4', 'split', 'leave' ) === $q['answers'], json_encode( array( $q['id'], $q['term_id'], $q['children'], $q['count'], $q['sample'], $q['answers'] ) ) );

$map3 = array( 301 => 0, 302 => 0, 303 => 0 );
$plan = vergeml_filing_answer_plan( $q, 'split' );
f_check( '20 split: each to its own best of the two, marked answered, not by hand', ! empty( $plan['answered'] ) && array( 301 => 2, 302 => 4, 303 => 2 ) === f_apply( $map3, $plan ) && 'answer' === $plan['placed_by'], json_encode( f_apply( $map3, $plan ) ) );
$plan = vergeml_filing_answer_plan( $q, 'put-in:4' );
f_check( '21 put-in:4: all three into Hardware, by hand', ! empty( $plan['answered'] ) && array( 4, 4, 4 ) === array_values( f_apply( $map3, $plan ) ) && ! empty( $plan['placed_by'] ) && null === $plan['make'], json_encode( f_apply( $map3, $plan ) ) );
$plan = vergeml_filing_answer_plan( $q, 'leave' );
f_check( '22 leave: all three into To sort', ! empty( $plan['answered'] ) && array( 98, 98, 98 ) === array_values( f_apply( $map3, $plan ) ), json_encode( f_apply( $map3, $plan ) ) );
f_check( '23 keep-parent and new-folder refused, and so is show-me on a card of three that has shown all three', null === vergeml_filing_answer_plan( $q, 'show-me' ) && null === vergeml_filing_answer_plan( $q, 'keep-parent' ) && null === vergeml_filing_answer_plan( $q, 'new-folder' ), json_encode( $q['answers'] ) );

/*
 *  The same either/or over nine pictures: it offers the look, and the look
 *  hands back the pictures rather than the two folders they are tied between
 *  (the bug 23 was written for, kept on a card that still offers it).
 */
$nine_pairs = array();
foreach ( range( 331, 339 ) as $n ) {
    $nine_pairs[ $n ] = 0 === $n % 2 ? 4 : 2;
}
$big  = array_values( array_filter( vergeml_filing_questions( array(), array(), array(), array(), array( '2:4' => array( 'ids' => $nine_pairs, 'children' => array( 2 => 5, 4 => 4 ) ) ) ), function ( $x ) { return 'either' === $x['kind']; } ) );
$plan = vergeml_filing_answer_plan( $big[0], 'show-me' );
f_check( '23b an either/or of nine offers the look, and it hands back the pictures, not the two folders', in_array( 'show-me', (array) $big[0]['answers'], true ) && empty( $plan['answered'] ) && array_map( 'intval', array_keys( $nine_pairs ) ) === $plan['show'] && ! in_array( 2, $plan['show'], true ) && ! in_array( 4, $plan['show'], true ), json_encode( $plan['show'] ) );

echo "\n== the questions' grain (S10.5): either/ors of one picture fold into one card; two folders of one name are told apart by their paths\n";

/*
 *  The shop (2026-09-16): 41 either/or cards, 38 of them about one picture --
 *  "1 picture: Backpacks or Backpacks?" thirty-eight times, and Nathan: "very
 *  tedious". The pairs with two or more pictures keep their own card, with
 *  both folders as put-in answers; every pair with one picture folds into one
 *  card, its pictures each remembering their own two folders, and the answers
 *  are the ones that fit them all: split (each to its own best), leave, look.
 *  Mutation: the fold removed -> 24 red (five either cards).
 */
$many = array(
    '2:4'   => array( 'ids' => array( 301 => 2, 302 => 4, 303 => 2 ), 'children' => array( 2 => 2, 4 => 1 ) ),
    '5:7'   => array( 'ids' => array( 311 => 5, 312 => 7 ), 'children' => array( 5 => 1, 7 => 1 ) ),
    '8:9'   => array( 'ids' => array( 321 => 9 ), 'children' => array( 9 => 1, 8 => 0 ) ),
    '10:11' => array( 'ids' => array( 322 => 10 ), 'children' => array( 10 => 1, 11 => 0 ) ),
    '12:13' => array( 'ids' => array( 323 => 13 ), 'children' => array( 13 => 1, 12 => 0 ) ),
);
$folded = array_values( array_filter( vergeml_filing_questions( array(), array(), array(), array(), $many ), function ( $q ) { return 'either' === $q['kind']; } ) );
$one    = end( $folded );
f_check( '24 five pairs, three of one picture: two pair cards (largest first) and one folded card of the three, e:one, ids by best, each picture with its own two folders, split / leave', 3 === count( $folded ) && 'e:2:4' === $folded[0]['id'] && 'e:5:7' === $folded[1]['id'] && 'e:one' === $one['id'] && 'either' === $one['kind'] && 3 === $one['count'] && array( 321 => 9, 322 => 10, 323 => 13 ) === $one['ids'] && array( 321 => array( 9, 8 ), 322 => array( 10, 11 ), 323 => array( 13, 12 ) ) === $one['pairs'] && array( 321, 322, 323 ) === $one['sample'] && array( 'split', 'leave' ) === $one['answers'], json_encode( array( array_column( $folded, 'id' ), isset( $one['ids'] ) ? $one['ids'] : null, isset( $one['pairs'] ) ? $one['pairs'] : null, isset( $one['answers'] ) ? $one['answers'] : null ) ) );
$mapo = array( 321 => 0, 322 => 0, 323 => 0 );
$plan = vergeml_filing_answer_plan( $one, 'split' );
$plan2 = vergeml_filing_answer_plan( $one, 'leave' );
f_check( '25 on the folded card: split files each to its own best (answer), leave parks all three, put-in and show-me are refused', is_array( $plan ) && array( 321 => 9, 322 => 10, 323 => 13 ) === f_apply( $mapo, $plan ) && 'answer' === $plan['placed_by'] && array( 98, 98, 98 ) === array_values( f_apply( $mapo, $plan2 ) ) && null === vergeml_filing_answer_plan( $one, 'show-me' ) && null === vergeml_filing_answer_plan( $one, 'put-in:9' ), json_encode( array( is_array( $plan ) ? f_apply( $mapo, $plan ) : null ) ) );
$single = array_values( array_filter( vergeml_filing_questions( array(), array(), array(), array(), array( '8:9' => $many['8:9'] ) ), function ( $q ) { return 'either' === $q['kind']; } ) );
f_check( '25b one pair of one picture alone stays its own card, with its put-ins and no look at the picture it is showing', 1 === count( $single ) && 'e:8:9' === $single[0]['id'] && array( 'put-in:9', 'put-in:8', 'split', 'leave' ) === $single[0]['answers'], json_encode( array_column( $single, 'id' ) ) );

/*
 *  "10 pictures: Backpacks or Backpacks?" -- the seeded collisions worded by
 *  leaf (Backpacks under Bags & Luggage and under Camping). Two folders that
 *  share a leaf name are named by their paths; folders whose leaves differ
 *  keep the leaf. Pure, over the names and paths the question text is given.
 *  Mutation: the path dropped -> 26 red.
 */
$same = vergeml_filing_question_names( array( array( 'name' => 'Backpacks', 'path' => 'Bags & Luggage › Backpacks' ), array( 'name' => 'Backpacks', 'path' => 'Camping › Backpacks' ) ) );
$diff = vergeml_filing_question_names( array( array( 'name' => 'Backpacks', 'path' => 'Bags & Luggage › Backpacks' ), array( 'name' => 'Helmets', 'path' => 'Cycling › Helmets' ) ) );
$case = vergeml_filing_question_names( array( array( 'name' => 'Jackets', 'path' => 'Men › Jackets' ), array( 'name' => 'jackets', 'path' => 'Women › jackets' ) ) );
f_check( '26 two folders of one name are named by their paths; two of different names by their leaves; the match ignores case', array( 'Bags & Luggage › Backpacks', 'Camping › Backpacks' ) === $same && array( 'Backpacks', 'Helmets' ) === $diff && array( 'Men › Jackets', 'Women › jackets' ) === $case, json_encode( array( $same, $diff, $case ) ) );

printf( "\n%d/%d passed\n", $GLOBALS['f_pass'], $GLOBALS['f_pass'] + $GLOBALS['f_fail'] );
exit( $GLOBALS['f_fail'] > 0 ? 1 : 0 );
