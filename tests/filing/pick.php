<?php
/*
 *  The matcher's outcomes, on fixtures.
 *
 *  Local: no WordPress, no database, no box. The suite stands in for the four
 *  things core/filing.php reaches for -- the WordPress helpers it calls at
 *  load, the meaning vectors, the index row's vector -- and drives
 *  vergeml_filing_pick() and vergeml_filing_count() over a tree of nine
 *  folders and twelve pictures whose scores are arithmetic on the fixtures.
 *
 *      node tools/verify.mjs filing
 *
 *  Every outcome the spec names has a row here, and the one branch the plan
 *  names as the mutation (two siblings inside the margin land in the parent)
 *  has its own: remove it and row 3 goes red.
 */

define( 'ABSPATH', '/' );

// What core/filing.php calls at load and inside a pick; nothing else is reached.
function add_action() {}
function sanitize_key( $k ) {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) );
}
function sanitize_text_field( $t ) {
    return trim( strip_tags( (string) $t ) );
}
// The index row's vector, as the fixture stores it: already an array.
function vergeml_index_vector_out( $packed ) {
    return is_array( $packed ) ? $packed : null;
}
/*
 *  Phrase vectors, fixed. The class match asks for one only when two phrases
 *  are neither equal, plural of each other nor one inside the other; a phrase
 *  not listed here has no vector, which is what a service that is down looks
 *  like, and scores 0 -- so every number below comes from the words.
 */
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


/* ------------------------------------------------------------- the tree */

function f_profile( $id, $parent, $path, $classes, $extra = array() ) {
    return array_merge( array(
        'version'   => VERGEML_FILING_VERSION,
        'source'    => 'plan',
        'plan'      => array(),
        'path'      => $path,
        'classes'   => $classes,
        'kinds'     => array( 'photo', 'illustration' ),
        'audience'  => '',
        'matches'   => '',
        'text'      => implode( ' / ', $path ),
        'vector'    => null,
        'built_at'  => 0,
        'term_id'   => $id,
        'parent_id' => $parent,
    ), $extra );
}

$profiles = array(
    1  => f_profile( 1, 0, array( 'Data centres' ), array( 'data centre', 'server' ) ),
    2  => f_profile( 2, 1, array( 'Data centres', 'Server racks' ), array( 'server rack', 'server' ) ),
    3  => f_profile( 3, 1, array( 'Data centres', 'Cooling' ), array( 'cooling unit', 'server', 'charger' ) ),
    4  => f_profile( 4, 0, array( 'Hardware' ), array( 'hardware', 'device' ) ),
    5  => f_profile( 5, 4, array( 'Hardware', 'Phones' ), array( 'phone', 'device', 'charger' ), array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
    6  => f_profile( 6, 0, array( 'Logos' ), array( 'logo' ), array( 'kinds' => array( 'logo' ) ) ),
    7  => f_profile( 7, 0, array( 'Women' ), array( 'woman' ), array( 'audience' => 'women' ) ),
    8  => f_profile( 8, 0, array( 'Archive' ), array( 'archive' ), array( 'locked' => true ) ),
    // Two slash-named orphans: siblings by path, with no folder at their parent's path.
    9  => f_profile( 9, 0, array( 'Apparel', 'Men', 'Shoes' ), array( 'shoe' ) ),
    10 => f_profile( 10, 0, array( 'Apparel', 'Men', 'Boots' ), array( 'shoe' ) ),
);

function f_facts( $object, $extra = array() ) {
    return array_merge( array(
        'classes'   => vergeml_filing_classes_of_object( $object ),
        'kind'      => 'photo',
        'audience'  => '',
        'vector'    => null,
        'placed_by' => '',
    ), $extra );
}

/*
 *  The scores, for the reader: class weight 0.75, vector weight 0.25. A
 *  picture's class against a folder's first class is 1.0, against a later one
 *  0.85; a folder with no vector adds nothing. So an exact first-class hit is
 *  0.75, a second-rank hit 0.6375, and the floor is 0.55, sure is 0.70.
 */
$rows = array(
    // 1. A clear fit: phone is Phones' first class, and the vectors agree -> 1.0, sure.
    1  => f_facts( 'phone; device', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
    // 2. A fit inside 0.70: device is Phones' second class (0.6375), and the child beats its parent -> likely.
    2  => f_facts( 'tablet; device' ),
    // 3. Two siblings inside the margin: server is second-rank in Server racks and Cooling alike -> the parent.
    3  => f_facts( 'blade; server' ),
    // 4. Two non-siblings inside the margin: charger is third-rank in Phones and in Cooling -> nothing.
    4  => f_facts( 'charger' ),
    // 5. All gated: a screenshot, and no folder takes screenshots.
    5  => f_facts( 'settings page; screenshot', array( 'kind' => 'screenshot' ) ),
    // 6. The locked folder is the only one that fits, and it is never picked.
    6  => f_facts( 'archive' ),
    // 7. Placed by the user: the clearest fit there is, and not re-picked.
    7  => f_facts( 'phone; device', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ), 'placed_by' => 'user' ) ),
    // 8. Below the floor: nothing in the tree knows a banana.
    8  => f_facts( 'banana; fruit' ),
    // 9. The audience gate: woman is Women's first class, but the picture does not say who it is for.
    9  => f_facts( 'woman' ),
    // 10. The parent fits and no child does -> the parent, sure.
    10 => f_facts( 'hardware' ),
    // 11. Siblings by path with no folder at the parent's path -> nothing, margin.
    11 => f_facts( 'shoe' ),
    // 12. A second placed picture, so the tally has two to keep.
    12 => f_facts( 'banana; fruit', array( 'placed_by' => 'user' ) ),
);

$pick = array();
foreach ( $rows as $n => $facts ) {
    $pick[ $n ] = vergeml_filing_pick( $facts, $profiles );
}

echo "\n== outcomes\n";

$p = $pick[1];
f_check( '1 clear fit: fits Phones, sure', 'fits' === $p['outcome'] && 5 === $p['term_id'] && 4 === $p['parent_id'] && 'sure' === $p['confidence'], sprintf( '%s %d @%.3f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );

$p = $pick[2];
f_check( '2 fit inside 0.70: fits Phones, likely', 'fits' === $p['outcome'] && 5 === $p['term_id'] && 'likely' === $p['confidence'] && $p['score'] < VERGEML_FILING_SURE && $p['score'] >= VERGEML_FILING_FLOOR, sprintf( '%s %d @%.3f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );

$p = $pick[3];
f_check( '3 two siblings inside the margin: siblings, placed in Data centres, likely', 'siblings' === $p['outcome'] && 1 === $p['term_id'] && 1 === $p['parent_id'] && 'likely' === $p['confidence'] && array( 2, 3 ) === array_values( array_map( 'intval', (array) $p['children'] ) ), sprintf( '%s %d parent %d children %s', $p['outcome'], $p['term_id'], $p['parent_id'], wp_json_encode_lite( isset( $p['children'] ) ? $p['children'] : null ) ) );

$p = $pick[4];
f_check( '4 two non-siblings inside the margin: nothing, margin', 'nothing' === $p['outcome'] && 0 === $p['term_id'] && 'margin' === $p['why'] && '' === $p['confidence'], sprintf( '%s %d why %s', $p['outcome'], $p['term_id'], $p['why'] ) );

$p = $pick[5];
f_check( '5 all gated: nothing, gated, every folder named', 'nothing' === $p['outcome'] && 'gated' === $p['why'] && count( $p['gated'] ) === count( $profiles ), sprintf( '%s why %s gated %d', $p['outcome'], $p['why'], count( $p['gated'] ) ) );

$p = $pick[6];
f_check( '6 a locked folder is never picked', 'nothing' === $p['outcome'] && 8 !== $p['term_id'] && isset( $p['gated'][8] ) && 'locked' === $p['gated'][8], sprintf( '%s %d gated[8]=%s', $p['outcome'], $p['term_id'], isset( $p['gated'][8] ) ? $p['gated'][8] : '-' ) );

$p = $pick[7];
f_check( '7 a user-placed picture is not re-picked', 'nothing' === $p['outcome'] && 0 === $p['term_id'] && 'placed' === $p['why'], sprintf( '%s %d why %s', $p['outcome'], $p['term_id'], $p['why'] ) );

$p = $pick[8];
f_check( '8 below the floor: nothing, floor, nearest named', 'nothing' === $p['outcome'] && 'floor' === $p['why'] && $p['score'] < VERGEML_FILING_FLOOR && isset( $p['nearest'] ), sprintf( '%s why %s @%.3f', $p['outcome'], $p['why'], $p['score'] ) );

$p = $pick[9];
f_check( '9 the audience gate holds Women', 'nothing' === $p['outcome'] && isset( $p['gated'][7] ) && 'audience' === $p['gated'][7], sprintf( '%s gated[7]=%s', $p['outcome'], isset( $p['gated'][7] ) ? $p['gated'][7] : '-' ) );

$p = $pick[10];
f_check( '10 the parent fits, no child does: fits Hardware, parent 0', 'fits' === $p['outcome'] && 4 === $p['term_id'] && 0 === $p['parent_id'] && 'sure' === $p['confidence'], sprintf( '%s %d parent %d %s', $p['outcome'], $p['term_id'], $p['parent_id'], $p['confidence'] ) );

$p = $pick[11];
f_check( '11 siblings with no folder at the parent path: nothing, margin', 'nothing' === $p['outcome'] && 'margin' === $p['why'] && 0 === $p['term_id'], sprintf( '%s %d why %s', $p['outcome'], $p['term_id'], $p['why'] ) );

echo "\n== the count, over the same twelve as index rows\n";

$index = array();
foreach ( $rows as $n => $facts ) {
    $index[] = array(
        'attachment_id' => $n,
        'filing'        => json_encode( array( 'object' => implode( '; ', $facts['classes'] ), 'audience' => $facts['audience'] ) ),
        'kind'          => $facts['kind'],
        'embedding'     => $facts['vector'],
        'placed_by'     => $facts['placed_by'],
    );
}
$count = vergeml_filing_count( $profiles, $index );
$c     = $count['counts'];
$sum   = $c['fits'] + $c['siblings'] + $c['nothing'] + $c['kept'];
f_check(
    '12 count: fits 3, siblings 1, nothing 6, kept 2; they sum to looked; sure 2, likely 2; by_term Phones 2 Data centres 1 Hardware 1',
    3 === $c['fits'] && 1 === $c['siblings'] && 6 === $c['nothing'] && 2 === $c['kept'] && 12 === $c['looked'] && $sum === $c['looked']
        && 2 === $c['sure'] && 2 === $c['likely']
        && 2 === $c['by_term'][5] && 1 === $c['by_term'][1] && 1 === $c['by_term'][4]
        // Floor takes three: the banana, the locked archive's picture and the woman nobody is for (0 against everything else).
        && 3 === $c['why']['floor'] && 2 === $c['why']['margin'] && 1 === $c['why']['gated']
        && count( $count['picks'] ) === 12 && 'siblings' === $count['picks'][3]['outcome'],
    json_encode( $c )
);

function wp_json_encode_lite( $v ) {
    return json_encode( $v );
}

printf( "\n%d/%d passed\n", $GLOBALS['f_pass'], $GLOBALS['f_pass'] + $GLOBALS['f_fail'] );
exit( $GLOBALS['f_fail'] > 0 ? 1 : 0 );
