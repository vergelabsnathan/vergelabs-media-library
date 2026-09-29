<?php
/*
 *  The planner's never-worse guard, on fixtures (spec-tree-planner story 8).
 *
 *  Local: no WordPress, no database, no box. vergeml_plan_gain_add() and
 *  vergeml_plan_gain_of() are arithmetic on unit vectors, so two-dimensional
 *  ones do: the pictures already in a folder, measured now and after the
 *  plan, the mean cosine of each to its folder's centre.
 *
 *      node tools/verify.mjs plan-gain
 *
 *  The mutations it catches: before and after swapped -> rows 1 and 2; the
 *  To-sort pictures counted in the measure -> row 3; a picture the plan
 *  unfiles counted as staying tight -> row 4; an empty site refused -> row 5.
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
    $acc = array( 'n' => 0, 'now' => array(), 'kept' => array(), 'all' => array() );
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
// Each folder now: [2,1] or [1,2], length sqrt(5), over 6 pictures. After: every picture at cosine 1.
pg_check( '1. a plan that sorts mixed folders apart reads tighter after', abs( $sorts['before'] - 2 * sqrt( 5 ) / 6 ) < 1e-9 && abs( $sorts['after'] - 1 ) < 1e-9, sprintf( '%.4f -> %.4f', $sorts['before'], $sorts['after'] ) );

// The same pictures, already sorted; the plan mixes them back.
$mixes = pg_run( array(
    array( $x, 't1', 'p1' ), array( $x, 't1', 'p1' ), array( $x, 't1', 'p2' ),
    array( $y, 't2', 'p2' ), array( $y, 't2', 'p2' ), array( $y, 't2', 'p1' ),
) );
pg_check( '2. a plan that mixes sorted folders reads looser after', abs( $mixes['before'] - 1 ) < 1e-9 && $mixes['after'] < $mixes['before'] - VERGEML_PLAN_GAIN, sprintf( '%.4f -> %.4f', $mixes['before'], $mixes['after'] ) );

// Two tidy folders and a To-sort pile the plan files into a folder of its own: only the six already filed are measured.
$pile = pg_run( array(
    array( $x, 't1', 't1' ), array( $x, 't1', 't1' ), array( $y, 't2', 't2' ), array( $y, 't2', 't2' ),
    array( array( 1.0, 1.0 ), '', 'p3' ), array( array( 1.0, 1.0 ), '', 'p3' ), array( array( -1.0, 1.0 ), '', 'p3' ),
) );
pg_check( '3. pictures waiting in To sort are not measured, before or after', abs( $pile['before'] - 1 ) < 1e-9 && abs( $pile['after'] - 1 ) < 1e-9, sprintf( '%.4f -> %.4f', $pile['before'], $pile['after'] ) );

// A picture in a folder that the plan leaves without one counts 0 after.
$drop = pg_run( array(
    array( $x, 't1', 't1' ), array( $x, 't1', '' ),
) );
pg_check( '4. a picture the plan unfiles counts 0 after', abs( $drop['before'] - 1 ) < 1e-9 && abs( $drop['after'] - 0.5 ) < 1e-9, sprintf( '%.4f -> %.4f', $drop['before'], $drop['after'] ) );

// Nothing filed yet: any plan is offered.
$empty = pg_run( array( array( $x, '', 'p1' ), array( $y, '', 'p2' ) ) );
pg_check( '5. a site with nothing filed is always offered its plan', $empty['after'] >= $empty['before'] + VERGEML_PLAN_GAIN, sprintf( '%.4f -> %.4f', $empty['before'], $empty['after'] ) );

printf( "\n%d/%d passed\n", $GLOBALS['pg_pass'], $GLOBALS['pg_pass'] + $GLOBALS['pg_fail'] );

if ( $GLOBALS['pg_fail'] > 0 ) {
    exit( 1 );
}
