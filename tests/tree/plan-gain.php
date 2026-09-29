<?php
/*
 *  The planner's never-worse guard, on fixtures (spec-tree-planner stories 8
 *  and 9).
 *
 *  Local: no WordPress, no database, no box. vergeml_plan_gain_add() and
 *  vergeml_plan_gain_of() are arithmetic on unit vectors, so two-dimensional
 *  ones do: the pictures already in a folder, measured now and after the
 *  plan, the mean cosine of each to the centre of the rest of its folder.
 *
 *      node tools/verify.mjs plan-gain
 *
 *  The mutations it catches: before and after swapped -> rows 1 and 2; the
 *  To-sort pictures counted in the measure -> row 3; a picture the plan
 *  unfiles counted as staying tight -> row 4; an empty site refused -> row 5;
 *  a picture counted towards its own centre (story 8's measure) -> rows 6
 *  and 7; the margin moved off what the replays allow -> row 9.
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

$x = array( 1.0, 0.0 );
$y = array( 0.0, 1.0 );

// Two folders that each mix x and y pictures; the plan sorts them apart.
$sorts = pg_run( array(
    array( $x, 't1', 'p1' ), array( $x, 't1', 'p1' ), array( $y, 't1', 'p2' ),
    array( $y, 't2', 'p2' ), array( $y, 't2', 'p2' ), array( $x, 't2', 'p1' ),
) );
// Each folder now sums to [2,1] or [1,2] over 3: (5 - 3) / sqrt(8/3) each, over 6 pictures = sqrt(6)/6. After: every picture at cosine 1 to the other two.
pg_check( '1. a plan that sorts mixed folders apart reads tighter after', abs( $sorts['before'] - sqrt( 6 ) / 6 ) < 1e-9 && abs( $sorts['after'] - 1 ) < 1e-9, sprintf( '%.4f -> %.4f', $sorts['before'], $sorts['after'] ) );

// The same pictures, already sorted; the plan mixes them back.
$mixes = pg_run( array(
    array( $x, 't1', 'p1' ), array( $x, 't1', 'p1' ), array( $x, 't1', 'p2' ),
    array( $y, 't2', 'p2' ), array( $y, 't2', 'p2' ), array( $y, 't2', 'p1' ),
) );
pg_check( '2. a plan that mixes sorted folders reads looser after', abs( $mixes['before'] - 1 ) < 1e-9 && $mixes['after'] < $mixes['before'] - VERGEML_PLAN_GAIN, sprintf( '%.4f -> %.4f', $mixes['before'], $mixes['after'] ) );

// Two tidy folders and a To-sort pile the plan files into a folder of its own: only the four already filed are measured.
$pile = pg_run( array(
    array( $x, 't1', 't1' ), array( $x, 't1', 't1' ), array( $y, 't2', 't2' ), array( $y, 't2', 't2' ),
    array( array( 1.0, 1.0 ), '', 'p3' ), array( array( 1.0, 1.0 ), '', 'p3' ), array( array( -1.0, 1.0 ), '', 'p3' ),
) );
pg_check( '3. pictures waiting in To sort are not measured, before or after', abs( $pile['before'] - 1 ) < 1e-9 && abs( $pile['after'] - 1 ) < 1e-9, sprintf( '%.4f -> %.4f', $pile['before'], $pile['after'] ) );

// Three alike in a folder; the plan unfiles one: it counts 0 after, the two left still sit together.
$drop = pg_run( array(
    array( $x, 't1', 't1' ), array( $x, 't1', 't1' ), array( $x, 't1', '' ),
) );
pg_check( '4. a picture the plan unfiles counts 0 after', abs( $drop['before'] - 1 ) < 1e-9 && abs( $drop['after'] - 2 / 3 ) < 1e-9, sprintf( '%.4f -> %.4f', $drop['before'], $drop['after'] ) );

// Nothing filed yet: any plan is offered.
$empty = pg_run( array( array( $x, '', 'p1' ), array( $y, '', 'p2' ) ) );
pg_check( '5. a site with nothing filed is always offered its plan', $empty['after'] >= $empty['before'] + VERGEML_PLAN_GAIN, sprintf( '%.4f -> %.4f', $empty['before'], $empty['after'] ) );

// A folder of one groups nothing. Story 8's measure read it as perfectly tight, which is how many small folders beat a plan.
$alone = pg_run( array(
    array( $x, 't1', 't1' ), array( $y, 't2', 't2' ), array( $y, 't2', 't2' ),
) );
pg_check( '6. a picture alone in its folder counts 0, now and after', abs( $alone['before'] - 2 / 3 ) < 1e-9 && abs( $alone['after'] - 2 / 3 ) < 1e-9, sprintf( '%.4f -> %.4f', $alone['before'], $alone['after'] ) );

// Story 9's bias, small: four alike pictures the site keeps as two pairs; the plan puts the four together.
// Story 8's measure read this 0.9764 -> 0.9764, no gain, refused. Leave-one-out: each picture now has three alike beside it, not one.
$a     = array( 1.0, 0.1 );
$b     = array( 1.0, -0.1 );
$c     = array( 1.0, 0.3 );
$d     = array( 1.0, -0.3 );
$pairs = pg_run( array(
    array( $a, 't1', 'p1' ), array( $b, 't1', 'p1' ), array( $c, 't2', 'p1' ), array( $d, 't2', 'p1' ),
) );
pg_check( '7. a group the site split into pairs, put back together, is offered', $pairs['after'] >= $pairs['before'] + VERGEML_PLAN_GAIN && abs( $pairs['before'] - 0.90753 ) < 1e-4 && abs( $pairs['after'] - 0.95793 ) < 1e-4, sprintf( '%.4f -> %.4f', $pairs['before'], $pairs['after'] ) );

// The closed form is exact for a folder of two: each picture's cosine to the other, twice.
$u = vergeml_plan_unit( $a );
$w = vergeml_plan_unit( $c );
pg_check( '8. for a folder of two, the measure is the cosine between the two', abs( vergeml_plan_gain_folder( vergeml_plan_add( $u, $w ), 2, vergeml_plan_add( $u, $w ) ) - 2 * vergeml_plan_dot( $u, $w ) ) < 1e-12 );

/*
 *  The margin, from free replays of saved plans through what follows the
 *  model (tools/plan-sim.mjs --gain; confirmed on the box through the real
 *  vergeml_plan_draft and vergeml_plan_gain, story 9): tech's run 4 of
 *  2026-09-28 (62 % purity, below the site's own 67 %) gains 0.0131 and must
 *  stay refused; the plan the real flow would keep from the shop's twenty
 *  chain runs (39/47, 76 %, as good as its existing 125 folders at 34/47,
 *  76 %) gains 0.0167 and must be offered.
 */
pg_check( '9. the margin sits between tech\'s worse plan (+0.0131) and the shop\'s kept plan (+0.0167)', VERGEML_PLAN_GAIN > 0.0131 && VERGEML_PLAN_GAIN <= 0.0167, (string) VERGEML_PLAN_GAIN );

printf( "\n%d/%d passed\n", $GLOBALS['pg_pass'], $GLOBALS['pg_pass'] + $GLOBALS['pg_fail'] );

if ( $GLOBALS['pg_fail'] > 0 ) {
    exit( 1 );
}
