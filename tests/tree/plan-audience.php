<?php
/*
 *  CAP-4 (spec-tree-planner story 6): the two free reads that decide whether
 *  the Tree step asks its one audience question -- the inventory's own
 *  audience share, and whether the site's product categories already say
 *  who a folder is for.
 *
 *  Local: no WordPress, no database, no box. Both functions are arithmetic
 *  and a string scan over their arguments; vergeml_plan_product_audience_evidence
 *  degrades to false with no function to read product categories or read a
 *  name's audience, which is exactly what an older WordPress or a site with
 *  no WooCommerce gives it.
 *
 *      node tools/verify.mjs plan-audience
 *
 *  The mutations it catches: the share counted against label counts instead
 *  of picture counts -> row 2; division by zero on an empty inventory -> row
 *  3; the evidence read stopping at the first path instead of scanning every
 *  segment -> row 6; the ask threshold itself moved -> row 7.
 */

define( 'ABSPATH', '/' );

// What core/plan-tree.php calls at load; nothing else is reached.
function add_action() {}

require dirname( __DIR__, 2 ) . '/core/plan-tree.php';

$GLOBALS['pa_pass'] = 0;
$GLOBALS['pa_fail'] = 0;

function pa_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['pa_pass']++;
    } else {
        $GLOBALS['pa_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note );
}

/* -------------------------------------------------- vergeml_plan_audience_share */

pa_check(
    '1. no labels at all reads 0, no division by zero',
    0.0 === vergeml_plan_audience_share( array() )
);

pa_check(
    '2. labels with no audience key, and an empty one, read 0',
    0.0 === vergeml_plan_audience_share( array(
        array( 'count' => 10, 'audience' => array() ),
        array( 'count' => 5 ),
    ) )
);

$share = vergeml_plan_audience_share( array(
    array( 'count' => 80, 'audience' => array() ),
    array( 'count' => 20, 'audience' => array( 'men' => 15, 'women' => 5 ) ),
) );
pa_check( '3. a fifth of the pictures tagged reads 0.2, by pictures not by labels', 0.2 === $share, (string) $share );

$share_rounded = vergeml_plan_audience_share( array(
    array( 'count' => 3, 'audience' => array( 'kids' => 1 ) ),
    array( 'count' => 88, 'audience' => array() ),
) );
pa_check(
    "4. the SPEC's own '3 of 88' reads about 1 %, well under the ask threshold",
    $share_rounded < VERGEML_PLAN_AUDIENCE_ASK && $share_rounded > 0.0,
    (string) $share_rounded
);

/* --------------------------------------------- vergeml_plan_product_audience_evidence */

pa_check(
    '5. with no product-category reader (no WooCommerce, or an older core), evidence reads false',
    false === vergeml_plan_product_audience_evidence()
);

/*
 *  Declared inside a branch so PHP defines them here, in execution order,
 *  rather than hoisting them to the top of the file as a plain top-level
 *  function would be -- which would make test 5 above see them as already
 *  defined. A lightweight stand-in for core/filing.php's real reader: it
 *  only has to answer '' or not for this suite's purpose, which is
 *  vergeml_plan_product_audience_evidence's own loop, not the word list.
 */
$GLOBALS['pa_paths'] = array();
if ( ! function_exists( 'vergeml_folders_product_paths' ) ) {
    function vergeml_folders_product_paths() {
        return array( 'paths' => $GLOBALS['pa_paths'] );
    }
    function vergeml_filing_audience_of( $text ) {
        $t = ' ' . mb_strtolower( trim( (string) $text ) ) . ' ';
        if ( preg_match( '/ (men|women|kids) /', $t ) ) {
            return 'yes';
        }
        return '';
    }
}

$GLOBALS['pa_paths'] = array( array( 'Clothing', 'Shoes' ), array( 'Home', 'Kitchen' ) );
pa_check(
    '6. categories that name no audience: no evidence, every path and segment read',
    false === vergeml_plan_product_audience_evidence()
);

$GLOBALS['pa_paths'] = array( array( 'Clothing', 'Shoes' ), array( 'Clothing', 'Men' ) );
pa_check(
    '7. a category two levels down naming an audience is still found',
    true === vergeml_plan_product_audience_evidence()
);

$GLOBALS['pa_paths'] = array( array( 'Kids' ) );
pa_check(
    '8. the top level itself naming an audience is found',
    true === vergeml_plan_product_audience_evidence()
);

/* ------------------------------------------------------------- the ask threshold */

pa_check( '9. the ask threshold is 15 %, documented in the story rather than tuned against a live library', 0.15 === VERGEML_PLAN_AUDIENCE_ASK );

printf( "\n%d/%d passed\n", $GLOBALS['pa_pass'], $GLOBALS['pa_pass'] + $GLOBALS['pa_fail'] );

if ( $GLOBALS['pa_fail'] > 0 ) {
    exit( 1 );
}
