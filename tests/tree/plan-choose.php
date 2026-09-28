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

/*
 *  The draft keeps an existing folder as the owner reads it. WordPress stores
 *  "Bags & Luggage" as "Bags &amp; Luggage"; copied raw, the draft showed
 *  every such folder as renamed, and accepting it would have renamed them
 *  (the shop, 2026-09-28). The stand-ins below are WordPress's and the
 *  plugin's own, as small as the call needs. $GLOBALS['pc_terms'] and
 *  ['pc_rows'] are set per row below, so one file plays every fixture
 *  vergeml_plan_draft asks its two collaborators for.
 */
function vergeml_librarian_taxonomy() {
    return 'media_category';
}
function get_terms( $args ) {
    return $GLOBALS['pc_terms'];
}
// Stands in for core/guide.php's own: label id -> kind, filing, in_terms for every described picture.
function vergeml_guide_rule_rows( $taxonomy, $scope, $need ) {
    return $GLOBALS['pc_rows'];
}
// Stands in for core/filing.php's own: "backpack; bag" -> array( 'backpack', 'bag' ).
function vergeml_filing_classes_of_object( $object ) {
    $parts = preg_split( '/\s*[;,]\s*/u', mb_strtolower( trim( (string) $object ) ) );
    $out   = array();
    foreach ( (array) $parts as $p ) {
        $p = trim( $p );
        if ( '' !== $p ) {
            $out[] = $p;
        }
    }
    return $out;
}
function sanitize_key( $key ) {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function wp_specialchars_decode( $t, $q = 0 ) {
    return html_entity_decode( (string) $t, ENT_QUOTES, 'UTF-8' );
}
function vergeml_term_name( $term ) {
    return wp_specialchars_decode( (string) $term->name, ENT_QUOTES );
}
function sanitize_text_field( $t ) {
    return trim( strip_tags( (string) $t ) );
}
function vergeml_guide_clean_draft( $draft ) {
    return $draft;
}

function wp_json( $v ) {
    return json_encode( $v );
}

// Row 9: no picture data at all (an inventory stub with no counts, as the caller may hand it) -- the exact-name fallback still applies.
$GLOBALS['pc_terms'] = array( (object) array( 'term_id' => 7, 'name' => 'Bags &amp; Luggage', 'parent' => 0 ) );
$GLOBALS['pc_rows']  = array();
$pc_draft = vergeml_plan_draft( array( array( 'path' => 'Bags & luggage', 'name' => 'Bags & luggage', 'parent' => '', 'labels' => array( 'a' ) ) ), array( array( 'id' => 'a', 'class' => 'backpack', 'kind' => 'photo' ) ) );
pc_check( '9. an existing folder keeps its name as read, and a planned folder of that name is it', 1 === count( $pc_draft['folders'] ) && 'Bags & Luggage' === $pc_draft['folders'][0]['name'] && 7 === $pc_draft['folders'][0]['term_id'] && array( 'backpack' ) === $pc_draft['folders'][0]['classes'], wp_json( $pc_draft['folders'] ) );

/*
 *  Row 10 (spec-tree-planner story 4). A planned folder's pictures already
 *  sit mostly in one existing folder that is named nothing like it: the
 *  near-copy the plan would otherwise propose beside it is that folder
 *  instead. Ten pictures share the label; six of them (more than half) are
 *  already in term 9, "Luggage Storage" -- a name the plan never mentions.
 */
$GLOBALS['pc_terms'] = array( (object) array( 'term_id' => 9, 'name' => 'Luggage Storage', 'parent' => 0 ) );
$GLOBALS['pc_rows']  = array_fill( 0, 6, array( 'kind' => 'photo', 'filing' => wp_json( array( 'object' => 'backpack; bag' ) ), 'in_terms' => '9' ) );
$pc_draft10 = vergeml_plan_draft(
    array( array( 'path' => 'X', 'name' => 'Bags and Luggage', 'parent' => '', 'labels' => array( 'a' ) ) ),
    array( array( 'id' => 'a', 'label' => 'backpack; bag', 'class' => 'backpack', 'kind' => 'photo', 'count' => 10 ) )
);
pc_check(
    '10. a planned folder whose pictures mostly sit in an existing folder becomes that folder, whatever its name',
    1 === count( $pc_draft10['folders'] ) && 9 === $pc_draft10['folders'][0]['term_id'] && 'Luggage Storage' === $pc_draft10['folders'][0]['name'] && array( 'backpack' ) === $pc_draft10['folders'][0]['classes'],
    wp_json( $pc_draft10['folders'] )
);
pc_check(
    '10b. the label map keys the label by its text and points it at that same folder',
    isset( $pc_draft10['label_map']['backpack; bag'] ) && $pc_draft10['label_map']['backpack; bag'] === $pc_draft10['folders'][0]['key'],
    wp_json( isset( $pc_draft10['label_map'] ) ? $pc_draft10['label_map'] : null )
);

/*
 *  Row 11. Two planned folders, named differently from each other and from
 *  the existing folder, both hold their pictures mostly in one existing
 *  "Kitchen": both become it, rather than two near-copies of each other
 *  and of Kitchen.
 */
$GLOBALS['pc_terms'] = array( (object) array( 'term_id' => 20, 'name' => 'Kitchen', 'parent' => 0 ) );
$GLOBALS['pc_rows']  = array_merge(
    array_fill( 0, 4, array( 'kind' => 'photo', 'filing' => wp_json( array( 'object' => 'kettle; kitchenware' ) ), 'in_terms' => '20' ) ),
    array_fill( 0, 3, array( 'kind' => 'photo', 'filing' => wp_json( array( 'object' => 'toaster; kitchenware' ) ), 'in_terms' => '20' ) )
);
$pc_draft11 = vergeml_plan_draft(
    array(
        array( 'path' => 'X', 'name' => 'Kitchen Extras', 'parent' => '', 'labels' => array( 'k' ) ),
        array( 'path' => 'Y', 'name' => 'Kitchen Add-ons', 'parent' => '', 'labels' => array( 't' ) ),
    ),
    array(
        array( 'id' => 'k', 'label' => 'kettle; kitchenware', 'class' => 'kettle', 'kind' => 'photo', 'count' => 4 ),
        array( 'id' => 't', 'label' => 'toaster; kitchenware', 'class' => 'toaster', 'kind' => 'photo', 'count' => 3 ),
    )
);
pc_check(
    '11. two planned folders that each mostly hold one existing folder\'s pictures both become it, not two folders beside it',
    1 === count( $pc_draft11['folders'] ) && 20 === $pc_draft11['folders'][0]['term_id'] && array( 'kettle', 'toaster' ) === $pc_draft11['folders'][0]['classes'],
    wp_json( $pc_draft11['folders'] )
);
pc_check(
    '11b. both labels\' map keys point at Kitchen\'s own key',
    isset( $pc_draft11['label_map']['kettle; kitchenware'], $pc_draft11['label_map']['toaster; kitchenware'] )
        && $pc_draft11['folders'][0]['key'] === $pc_draft11['label_map']['kettle; kitchenware']
        && $pc_draft11['folders'][0]['key'] === $pc_draft11['label_map']['toaster; kitchenware'],
    wp_json( isset( $pc_draft11['label_map'] ) ? $pc_draft11['label_map'] : null )
);

/*
 *  Row 12. Under half: a planned folder whose pictures mostly sit nowhere
 *  yet (two of ten in an unrelated existing folder) is new, as it always
 *  was, and its label maps onto that new folder's own key.
 */
$GLOBALS['pc_terms'] = array( (object) array( 'term_id' => 30, 'name' => 'Outdoor Gear', 'parent' => 0 ) );
$GLOBALS['pc_rows']  = array_fill( 0, 2, array( 'kind' => 'photo', 'filing' => wp_json( array( 'object' => 'tent; camping' ) ), 'in_terms' => '30' ) );
$pc_draft12 = vergeml_plan_draft(
    array( array( 'path' => 'X', 'name' => 'Camping', 'parent' => '', 'labels' => array( 'c' ) ) ),
    array( array( 'id' => 'c', 'label' => 'tent; camping', 'class' => 'tent', 'kind' => 'photo', 'count' => 10 ) )
);
pc_check(
    '12. under half in any one existing folder: the planned folder is new, and its label maps onto it',
    2 === count( $pc_draft12['folders'] ) && null === $pc_draft12['folders'][1]['term_id'] && 'Camping' === $pc_draft12['folders'][1]['name']
        && isset( $pc_draft12['label_map']['tent; camping'] ) && $pc_draft12['folders'][1]['key'] === $pc_draft12['label_map']['tent; camping'],
    wp_json( array( $pc_draft12['folders'], isset( $pc_draft12['label_map'] ) ? $pc_draft12['label_map'] : null ) )
);

/*
 *  Row 13. Pictures waiting in "To sort" are what a plan is for: a planned
 *  folder made mostly of them is new, never To sort itself.
 */
define( 'VERGEML_FILING_TO_SORT_SLUG', 'to-sort' );
$GLOBALS['pc_terms'] = array( (object) array( 'term_id' => 40, 'name' => 'To sort', 'slug' => 'to-sort', 'parent' => 0 ) );
$GLOBALS['pc_rows']  = array_fill( 0, 8, array( 'kind' => 'photo', 'filing' => wp_json( array( 'object' => 'tent; camping' ) ), 'in_terms' => '40' ) );
$pc_draft13 = vergeml_plan_draft(
    array( array( 'path' => 'X', 'name' => 'Camping', 'parent' => '', 'labels' => array( 'c' ) ) ),
    array( array( 'id' => 'c', 'label' => 'tent; camping', 'class' => 'tent', 'kind' => 'photo', 'count' => 10 ) )
);
pc_check(
    '13. a planned folder made mostly of pictures waiting in To sort is new, not To sort',
    2 === count( $pc_draft13['folders'] ) && 'Camping' === $pc_draft13['folders'][1]['name'] && null === $pc_draft13['folders'][1]['term_id'],
    wp_json( $pc_draft13['folders'] )
);

printf( "\n%d/%d passed\n", $GLOBALS['pc_pass'], $GLOBALS['pc_pass'] + $GLOBALS['pc_fail'] );

if ( $GLOBALS['pc_fail'] > 0 ) {
    exit( 1 );
}
