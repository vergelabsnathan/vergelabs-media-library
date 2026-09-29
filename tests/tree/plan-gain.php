<?php
/*
 *  The planner's never-worse guard, on fixtures (spec-tree-planner stories 8
 *  and 9).
 *
 *  Local: no WordPress, no database, no box. vergeml_plan_gain_add(),
 *  vergeml_plan_gain_of() and vergeml_plan_gain_better() are arithmetic on
 *  unit vectors: the pictures already in a folder, measured now and after
 *  the plan, by leave-one-out (each against the centre of the rest of its
 *  folder) and by average link (each against every other member).
 *
 *      node tools/verify.mjs plan-gain
 *
 *  The mutations it catches: before and after swapped -> rows 1 and 2;
 *  To-sort pictures measured -> row 3; a picture the plan unfiles counted as
 *  staying tight -> row 4; an empty site refused -> row 5; a picture counted
 *  towards its own centre (story 8) -> row 6; To-sort pictures in the after
 *  centre (story 9's first measure) -> row 7; the average-link rule dropped
 *  -> rows 8 and 9; the margin moved off what the replays allow -> row 11.
 */

define( 'ABSPATH', '/' );

// What core/plan-tree.php calls at load; nothing else is reached.
function add_action() {}

require dirname( __DIR__, 2 ) . '/core/plan-tree.php';

$GLOBALS['pg_pass'] = 0;
$GLOBALS['pg_fail'] = 0;

function pg_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['pg_pass']++;
    } else {
        $GLOBALS['pg_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note );
}

function pg_run( $pictures ) {
    $acc = vergeml_plan_gain_acc();
    foreach ( $pictures as $p ) {
        $acc = vergeml_plan_gain_add( $acc, vergeml_plan_unit( $p[0] ), $p[1], $p[2] );
    }
    return vergeml_plan_gain_of( $acc );
}

function pg_note( $g ) {
    return sprintf( 'loo %.4f -> %.4f, link %.4f -> %.4f, %s', $g['before'], $g['after'], $g['link_before'], $g['link_after'], vergeml_plan_gain_better( $g ) ? 'offered' : 'refused' );
}

/*
 *  A picture with an exact cosine to the others (the review's constructed
 *  cases): a shared part everyone has (cosine $across between groups), a
 *  part its group shares (cosine $within inside the group), and a part of
 *  its own. Forty dimensions, each picture its own axis from 10 up.
 */
function pg_pic( $across, $within, $group ) {
    static $own = 10;
    $v              = array_fill( 0, 40, 0.0 );
    $v[0]           = sqrt( $across );
    $v[ 1 + $group ] = sqrt( $within - $across );
    $v[ $own++ ]    = sqrt( 1 - $within );
    return $v;
}

$x = array( 1.0, 0.0 );
$y = array( 0.0, 1.0 );

// Two folders that each mix x and y pictures; the plan sorts them apart.
$sorts = pg_run( array(
    array( $x, 't1', 'p1' ), array( $x, 't1', 'p1' ), array( $y, 't1', 'p2' ),
    array( $y, 't2', 'p2' ), array( $y, 't2', 'p2' ), array( $x, 't2', 'p1' ),
) );
// Each folder now sums to [2,1] or [1,2] over 3: (5 - 3) / sqrt(8/3) each, over 6 pictures = sqrt(6)/6; its link (5 - 3) / 2 each = 1/3. After: every picture at cosine 1 to the other two.
pg_check( '1. a plan that sorts mixed folders apart is offered', abs( $sorts['before'] - sqrt( 6 ) / 6 ) < 1e-9 && abs( $sorts['after'] - 1 ) < 1e-9 && abs( $sorts['link_before'] - 1 / 3 ) < 1e-9 && abs( $sorts['link_after'] - 1 ) < 1e-9 && vergeml_plan_gain_better( $sorts ), pg_note( $sorts ) );

// The same pictures, already sorted; the plan mixes them back.
$mixes = pg_run( array(
    array( $x, 't1', 'p1' ), array( $x, 't1', 'p1' ), array( $x, 't1', 'p2' ),
    array( $y, 't2', 'p2' ), array( $y, 't2', 'p2' ), array( $y, 't2', 'p1' ),
) );
pg_check( '2. a plan that mixes sorted folders is refused', abs( $mixes['before'] - 1 ) < 1e-9 && $mixes['after'] < $mixes['before'] && ! vergeml_plan_gain_better( $mixes ), pg_note( $mixes ) );

// Two tidy folders and a To-sort pile the plan files into a folder of its own: only the four already filed are measured.
$pile = pg_run( array(
    array( $x, 't1', 't1' ), array( $x, 't1', 't1' ), array( $y, 't2', 't2' ), array( $y, 't2', 't2' ),
    array( array( 1.0, 1.0 ), '', 'p3' ), array( array( 1.0, 1.0 ), '', 'p3' ), array( array( -1.0, 1.0 ), '', 'p3' ),
) );
pg_check( '3. pictures waiting in To sort are not measured, before or after', abs( $pile['before'] - 1 ) < 1e-9 && abs( $pile['after'] - 1 ) < 1e-9, pg_note( $pile ) );

// Three alike in a folder; the plan unfiles one: it counts 0 after, the two left still sit together.
$drop = pg_run( array(
    array( $x, 't1', 't1' ), array( $x, 't1', 't1' ), array( $x, 't1', '' ),
) );
pg_check( '4. a picture the plan unfiles counts 0 after', abs( $drop['before'] - 1 ) < 1e-9 && abs( $drop['after'] - 2 / 3 ) < 1e-9, pg_note( $drop ) );

// Nothing filed yet: any plan is offered.
$empty = pg_run( array( array( $x, '', 'p1' ), array( $y, '', 'p2' ) ) );
pg_check( '5. a site with nothing filed is always offered its plan', vergeml_plan_gain_better( $empty ), pg_note( $empty ) );

// A folder of one groups nothing. Story 8's measure read it as perfectly tight, which is how many small folders beat a plan.
$alone = pg_run( array(
    array( $x, 't1', 't1' ), array( $y, 't2', 't2' ), array( $y, 't2', 't2' ),
) );
pg_check( '6. a picture alone in its folder counts 0, now and after', abs( $alone['before'] - 2 / 3 ) < 1e-9 && abs( $alone['after'] - 2 / 3 ) < 1e-9 && abs( $alone['link_before'] - 2 / 3 ) < 1e-9, pg_note( $alone ) );

// The review's first case: a folder of three at 0.6 stays as it is, and seven like them come out of To sort into it.
// Story 9's first measure put the seven in the after centre and read 0.6708 -> 0.7474, an offer for nothing that changed.
$in = array();
for ( $i = 0; $i < 10; $i++ ) {
    $in[] = array( pg_pic( 0.6, 0.6, 0 ), $i < 3 ? 't1' : '', 'p1' );
}
$inflate = pg_run( $in );
pg_check( '7. To-sort pictures joining a folder do not make its pictures read tighter', abs( $inflate['before'] - 0.67082 ) < 1e-4 && abs( $inflate['after'] - $inflate['before'] ) < 1e-9 && ! vergeml_plan_gain_better( $inflate ), pg_note( $inflate ) );

// The review's second case: three folders of three at 0.6 inside, 0.5 between, merged into one of nine -- similarity lowered to 0.525.
// Leave-one-out reads +0.016 (the review's figure), which story 9's first margin of 0.015 offered; the average link falls 0.600 -> 0.525.
$in = array();
for ( $g = 0; $g < 3; $g++ ) {
    for ( $i = 0; $i < 3; $i++ ) {
        $in[] = array( pg_pic( 0.5, 0.6, $g ), 't' . $g, 'p1' );
    }
}
$lower = pg_run( $in );
pg_check( '8. a merge that lowers similarity to 0.525 is refused', $lower['after'] >= $lower['before'] + 0.015 && abs( $lower['link_before'] - 0.6 ) < 1e-9 && abs( $lower['link_after'] - 0.525 ) < 1e-9 && ! vergeml_plan_gain_better( $lower ), pg_note( $lower ) );

// The review's third case: two folders of two at 0.6, 0.5 between them, merged. Leave-one-out +0.043, over today's margin too; only the link (0.600 -> 0.533) refuses it.
$merge = pg_run( array(
    array( pg_pic( 0.5, 0.6, 3 ), 'a', 'm' ), array( pg_pic( 0.5, 0.6, 3 ), 'a', 'm' ),
    array( pg_pic( 0.5, 0.6, 4 ), 'b', 'm' ), array( pg_pic( 0.5, 0.6, 4 ), 'b', 'm' ),
) );
pg_check( '9. two folders merged are refused on the average link', $merge['after'] >= $merge['before'] + VERGEML_PLAN_GAIN && abs( $merge['link_after'] - 1.6 / 3 ) < 1e-9 && ! vergeml_plan_gain_better( $merge ), pg_note( $merge ) );

// The closed form is exact for a folder of two: each picture's cosine to the other, twice.
$u = vergeml_plan_unit( array( 1.0, 0.1 ) );
$w = vergeml_plan_unit( array( 1.0, 0.3 ) );
pg_check( '10. for a folder of two, both measures are the cosine between the two', abs( vergeml_plan_gain_folder( vergeml_plan_add( $u, $w ), 2, vergeml_plan_add( $u, $w ) ) - 2 * vergeml_plan_dot( $u, $w ) ) < 1e-12 && abs( vergeml_plan_gain_link( vergeml_plan_add( $u, $w ), 2 ) - 2 * vergeml_plan_dot( $u, $w ) ) < 1e-12 );

/*
 *  The margin, from free replays of saved plans through what follows the
 *  model (tools/plan-sim.mjs --gain; the box gave the same through the real
 *  PHP): tech's run 4 of 2026-09-28 (62 % purity, below the site's own 67 %)
 *  gains 0.0140 and must stay refused with room to spare; tech's run 3 (76 %)
 *  gains 0.0205 and is offered. Run 1 (73 %, +0.0182) falls under the margin
 *  and is refused and refunded -- the safer failure, chosen over a margin
 *  0.002 from run 4.
 */
pg_check( '11. the margin keeps 0.005 clear of tech\'s worse plan (+0.0140) and at most tech\'s run 3 (+0.0205)', VERGEML_PLAN_GAIN >= 0.0140 + 0.005 && VERGEML_PLAN_GAIN <= 0.0205, (string) VERGEML_PLAN_GAIN );

printf( "\n%d/%d passed\n", $GLOBALS['pg_pass'], $GLOBALS['pg_pass'] + $GLOBALS['pg_fail'] );

if ( $GLOBALS['pg_fail'] > 0 ) {
    exit( 1 );
}
