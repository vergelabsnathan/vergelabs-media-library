<?php
/*
 *  The matcher's outcomes, on fixtures.
 *
 *  Local: no WordPress, no database, no box. The suite stands in for the four
 *  things core/filing.php reaches for -- the WordPress helpers it calls at
 *  load, the meaning vectors, the index row's vector -- and drives
 *  vergeml_filing_pick() and vergeml_filing_count() over a tree of eleven
 *  folders and fifteen pictures whose scores are arithmetic on the fixtures.
 *
 *      node tools/verify.mjs filing
 *
 *  The tree has the box's shape on purpose (2026-09-15: `infrastructure` on
 *  four folders, `server` on three, a folder's own name below rank 0): the
 *  matcher ties there unless a shared word is worth less than a folder's own.
 *  The mutations the plan names, each with the row that goes red: the 1/k
 *  weight removed -> row 15; the second-phrase weight removed -> row 13; the
 *  leaf-name lift removed -> row 13; the cross-parent branch removed -> row 4;
 *  the siblings branch removed -> row 3.
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
function wp_specialchars_decode( $t, $q = 0 ) {
    return html_entity_decode( (string) $t, ENT_QUOTES, 'UTF-8' );
}
// The index row's vector, as the fixture stores it: already an array.
function vergeml_index_vector_out( $packed ) {
    return is_array( $packed ) ? $packed : null;
}
/*
 *  Phrase vectors, fixed. The class match asks for one only when two phrases
 *  are neither equal, plural of each other nor one inside the other; a phrase
 *  not listed here has no vector, which is what a service that is down looks
 *  like, and scores 0 -- so every number below comes from the words, and from
 *  the three folder vectors the tie rows lean on.
 */
function vergeml_meaning_vector( $text ) {
    // Row 18 lends three phrase vectors for the floor; everywhere else, none.
    return isset( $GLOBALS['f_vec'][ $text ] ) ? $GLOBALS['f_vec'][ $text ] : null;
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

/*
 *  Shared words, as the box has them: infrastructure on four folders (k 4),
 *  server on three, networking equipment / device / charger / footwear on two.
 *  Server racks' own name sits at rank 1, below a planner word, as it did on
 *  the box. Cooling and Phones carry vectors so two rows can tie by vector.
 */
$profiles = array(
    1  => f_profile( 1, 0, array( 'Data centres' ), array( 'data centre', 'infrastructure', 'networking equipment', 'server' ) ),
    2  => f_profile( 2, 1, array( 'Data centres', 'Server racks' ), array( 'networking equipment', 'server racks', 'rack', 'server', 'infrastructure' ) ),
    3  => f_profile( 3, 1, array( 'Data centres', 'Cooling' ), array( 'cooling unit', 'infrastructure', 'chiller', 'charger', 'server' ), array( 'vector' => array( 0.96, 0.28, 0.0, 0.0 ) ) ),
    4  => f_profile( 4, 0, array( 'Hardware' ), array( 'computer hardware', 'computer', 'hardware', 'device' ) ),
    5  => f_profile( 5, 4, array( 'Hardware', 'Phones' ), array( 'phone', 'device', 'charger' ), array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
    6  => f_profile( 6, 0, array( 'Logos' ), array( 'logo' ), array( 'kinds' => array( 'logo' ) ) ),
    7  => f_profile( 7, 0, array( 'Women' ), array( 'woman' ), array( 'audience' => 'women' ) ),
    8  => f_profile( 8, 0, array( 'Archive' ), array( 'archive' ), array( 'locked' => true ) ),
    // Two slash-named orphans: siblings by path, with no folder at their parent's path.
    9  => f_profile( 9, 0, array( 'Apparel', 'Men', 'Sneakers' ), array( 'footwear' ), array( 'vector' => array( 0.0, 0.0, 0.0, 1.0 ) ) ),
    10 => f_profile( 10, 0, array( 'Apparel', 'Men', 'Boots' ), array( 'footwear' ), array( 'vector' => array( 0.0, 0.0, 0.0, 1.0 ) ) ),
    11 => f_profile( 11, 0, array( 'Space' ), array( 'infrastructure', 'spacecraft' ) ),
);
// The last step every real caller takes: claims settled, shared words counted.
$profiles = vergeml_filing_settle_claims( $profiles );

f_check( '0 shared words counted once for the set: infrastructure 4, server 3, device 2, phone 1', isset( $profiles[1]['shared'] ) && 4 === $profiles[1]['shared']['infrastructure'] && 3 === $profiles[2]['shared']['server'] && 2 === $profiles[5]['shared']['device'] && 1 === $profiles[5]['shared']['phone'], json_encode( isset( $profiles[1]['shared'] ) ? $profiles[1]['shared'] : null ) );

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
 *  picture's first phrase weighs 1.0 and its second 0.85; a folder's first
 *  class weighs 1.0, a later one 0.85, except the folder's own name, which is
 *  1.0 wherever it sits when the phrase is exactly it; a class on k folders is
 *  worth 1/k of that. So an exact first-class hit is 0.75, a second-rank hit
 *  0.6375, a word on four folders at most 0.1875; the floor is 0.55, sure 0.70.
 */
$rows = array(
    // 1. A clear fit: phone is Phones' first class, and the vectors agree -> 1.0, sure.
    1  => f_facts( 'phone; device', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
    // 2. A fit inside 0.70: phone is the picture's second phrase (0.85 -> 0.6375) -> likely.
    2  => f_facts( 'smartphone; phone' ),
    // 3. Two siblings inside the margin: the words say Server racks (rack, 0.6375), the vector leans to Cooling (chiller 0.542 + 0.07) -> the parent.
    3  => f_facts( 'rack; chiller', array( 'vector' => array( 0.0, 1.0, 0.0, 0.0 ) ) ),
    // 4. Two non-siblings inside the margin: charger is on Phones and Cooling (1/2), both vectors near -> nothing, and an either/or.
    4  => f_facts( 'charger', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
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
    // 10. The parent fits and no child does -> the parent, sure (its own name at rank 2 counts 1.0).
    10 => f_facts( 'hardware' ),
    // 11. Siblings by path with no folder at the parent's path -> nothing, margin (footwear on both, 1/2, vectors equal).
    11 => f_facts( 'footwear', array( 'vector' => array( 0.0, 0.0, 0.0, 1.0 ) ) ),
    // 12. A second placed picture, so the tally has two to keep.
    12 => f_facts( 'banana; fruit', array( 'placed_by' => 'user' ) ),
    // 13. The box's case: the folder named for the object (Server racks, rank 1) against the parent's neighbour holding the second phrase at rank 0.
    13 => f_facts( 'server rack; computer hardware' ),
    // 14. Four folders share infrastructure and one is also named for the object -> that one, sure, no margin.
    14 => f_facts( 'server rack; infrastructure' ),
    // 15. Only the shared word: a first-phrase hit on a word four folders hold is worth at most 0.25 x 0.75.
    15 => f_facts( 'infrastructure; structure' ),
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
f_check( '3 two siblings inside the margin: siblings, placed in Data centres, likely', 'siblings' === $p['outcome'] && 1 === $p['term_id'] && 1 === $p['parent_id'] && 'likely' === $p['confidence'] && array( 2, 3 ) === array_values( array_map( 'intval', (array) $p['children'] ) ), sprintf( '%s %d parent %d children %s @%.3f / %.3f', $p['outcome'], $p['term_id'], $p['parent_id'], wp_json_encode_lite( isset( $p['children'] ) ? $p['children'] : null ), $p['score'], $p['runner_score'] ) );

$p = $pick[4];
f_check( '4 two non-siblings inside the margin: nothing, margin, and the two as children (Phones, Cooling) for an either/or', 'nothing' === $p['outcome'] && 0 === $p['term_id'] && 'margin' === $p['why'] && '' === $p['confidence'] && isset( $p['children'] ) && array( 5, 3 ) === array_values( array_map( 'intval', (array) $p['children'] ) ), sprintf( '%s %d why %s children %s @%.3f / %.3f', $p['outcome'], $p['term_id'], $p['why'], wp_json_encode_lite( isset( $p['children'] ) ? $p['children'] : null ), $p['score'], $p['runner_score'] ) );

$p = $pick[5];
f_check( '5 all gated: nothing, gated, every folder named, and the kind carried out', 'nothing' === $p['outcome'] && 'gated' === $p['why'] && count( $p['gated'] ) === count( $profiles ) && isset( $p['kind'] ) && 'screenshot' === $p['kind'], sprintf( '%s why %s gated %d kind %s', $p['outcome'], $p['why'], count( $p['gated'] ), isset( $p['kind'] ) ? $p['kind'] : '-' ) );

$p = $pick[6];
f_check( '6 a locked folder is never picked', 'nothing' === $p['outcome'] && 8 !== $p['term_id'] && isset( $p['gated'][8] ) && 'locked' === $p['gated'][8], sprintf( '%s %d gated[8]=%s', $p['outcome'], $p['term_id'], isset( $p['gated'][8] ) ? $p['gated'][8] : '-' ) );

$p = $pick[7];
f_check( '7 a user-placed picture is not re-picked', 'nothing' === $p['outcome'] && 0 === $p['term_id'] && 'placed' === $p['why'], sprintf( '%s %d why %s', $p['outcome'], $p['term_id'], $p['why'] ) );

$p = $pick[8];
f_check( '8 below the floor: nothing, floor, nearest named', 'nothing' === $p['outcome'] && 'floor' === $p['why'] && $p['score'] < VERGEML_FILING_FLOOR && isset( $p['nearest'] ), sprintf( '%s why %s @%.3f', $p['outcome'], $p['why'], $p['score'] ) );

$p = $pick[9];
f_check( '9 the audience gate holds Women', 'nothing' === $p['outcome'] && isset( $p['gated'][7] ) && 'audience' === $p['gated'][7], sprintf( '%s gated[7]=%s', $p['outcome'], isset( $p['gated'][7] ) ? $p['gated'][7] : '-' ) );

$p = $pick[10];
f_check( '10 the parent fits, no child does: fits Hardware, parent 0, sure by its own name', 'fits' === $p['outcome'] && 4 === $p['term_id'] && 0 === $p['parent_id'] && 'sure' === $p['confidence'], sprintf( '%s %d parent %d %s @%.3f', $p['outcome'], $p['term_id'], $p['parent_id'], $p['confidence'], $p['score'] ) );

$p = $pick[11];
f_check( '11 siblings with no folder at the parent path: nothing, margin, the two as children', 'nothing' === $p['outcome'] && 'margin' === $p['why'] && 0 === $p['term_id'] && isset( $p['children'] ) && array( 9, 10 ) === array_values( array_map( 'intval', (array) $p['children'] ) ), sprintf( '%s %d why %s children %s', $p['outcome'], $p['term_id'], $p['why'], wp_json_encode_lite( isset( $p['children'] ) ? $p['children'] : null ) ) );

$p = $pick[13];
f_check( '13 "server rack; computer hardware": Server racks by its own name (0.75), sure, over Hardware holding the second phrase (0.6375)', 'fits' === $p['outcome'] && 2 === $p['term_id'] && 'sure' === $p['confidence'] && 4 === $p['runner_up'] && abs( $p['score'] - 0.75 ) < 1e-9 && abs( $p['runner_score'] - 0.6375 ) < 1e-9, sprintf( '%s %d @%.4f %s next %d @%.4f', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'], $p['runner_up'], $p['runner_score'] ) );

$p = $pick[14];
f_check( '14 "server rack; infrastructure" among four folders sharing infrastructure: Server racks, sure, no margin', 'fits' === $p['outcome'] && 2 === $p['term_id'] && 'sure' === $p['confidence'] && $p['score'] - $p['runner_score'] >= VERGEML_FILING_MARGIN, sprintf( '%s %d @%.4f %s next %d @%.4f', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'], $p['runner_up'], $p['runner_score'] ) );

$p = $pick[15];
f_check( '15 a word on four folders is worth at most 0.25 x 0.75 on any of them: nothing, floor', 'nothing' === $p['outcome'] && 'floor' === $p['why'] && max( $p['scores'] ) <= 0.25 * VERGEML_FILING_CLASS_WEIGHT + 1e-9, sprintf( '%s why %s best @%.4f', $p['outcome'], $p['why'], $p['scores'] ? max( $p['scores'] ) : 0 ) );

echo "\n== the count, over the first twelve as index rows\n";

$index = array();
foreach ( array_slice( $rows, 0, 12, true ) as $n => $facts ) {
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
    '12 count: fits 3, siblings 1, nothing 6 (either 2), kept 2; they sum to looked; sure 2, likely 2; by_term Phones 2 Data centres 1 Hardware 1',
    3 === $c['fits'] && 1 === $c['siblings'] && 6 === $c['nothing'] && 2 === $c['kept'] && 12 === $c['looked'] && $sum === $c['looked']
        && 2 === $c['sure'] && 2 === $c['likely']
        && 2 === $c['by_term'][5] && 1 === $c['by_term'][1] && 1 === $c['by_term'][4]
        // Floor takes three: the banana, the locked archive's picture and the woman nobody is for (0 against everything else).
        && 3 === $c['why']['floor'] && 2 === $c['why']['margin'] && 1 === $c['why']['gated']
        // The two margins are both between folders that are not siblings under a folder: asked as either/or.
        && isset( $c['either'] ) && 2 === $c['either']
        && count( $count['picks'] ) === 12 && 'siblings' === $count['picks'][3]['outcome'],
    json_encode( $c )
);

function wp_json_encode_lite( $v ) {
    return json_encode( $v );
}

/*
 *  C.4: the canon fold, the cosine floor, the kind-word guard and the
 *  one-folder drop. Mutations: the spelling table emptied -> row 16 red; the
 *  plural fold back to rtrim -> row 17 red; the cosine floor removed -> row
 *  18 red; the kind-word guard removed -> row 19 red; the neighbour drop
 *  removed -> row 20 red.
 */
echo "\n== the words, spelled the one way (C.4)\n";

f_check( '16 class_match: "data centre" and "data center" are the same word', 1.0 === vergeml_filing_class_match( 'data centre', 'data center' ), sprintf( '%.2f', vergeml_filing_class_match( 'data centre', 'data center' ) ) );
f_check( '17 class_match: "rocket launch" as the picture\'s object hits a folder named Launches in full, as its class half 0.95 (the fold strips "es", not one "s")', 1.0 === vergeml_filing_class_match( 'rocket launch', 'launches', true ) && 0.95 === vergeml_filing_class_match( 'rocket launch', 'launches' ) && 'launch' === vergeml_filing_canon( 'launches' ) && 'battery' === vergeml_filing_canon( 'batteries' ) && 'person' === vergeml_filing_canon( 'people' ) && 'glass' === vergeml_filing_canon( 'glasses' ) && 'server rack' === vergeml_filing_canon( 'Server Racks' ), sprintf( '%.2f · %s · %s · %s', vergeml_filing_class_match( 'rocket launch', 'launches', true ), vergeml_filing_canon( 'launches' ), vergeml_filing_canon( 'batteries' ), vergeml_filing_canon( 'glasses' ) ) );
f_check( '17b class_match: a phrase whole inside the other is 0.95; the picture\'s head noun as the folder\'s word is 1.0, not the reverse ("rack" is 0.95 of a server rack); unrelated words are 0', 0.95 === vergeml_filing_class_match( 'phone case', 'phones' ) && 1.0 === vergeml_filing_class_match( 'network switches', 'switch', true ) && 0.95 === vergeml_filing_class_match( 'network switches', 'switch' ) && 0.95 === vergeml_filing_class_match( 'rack', 'server racks', true ) && 0.0 === vergeml_filing_class_match( 'banana', 'server rack' ), sprintf( '%.2f %.2f %.2f %.2f', vergeml_filing_class_match( 'phone case', 'phones' ), vergeml_filing_class_match( 'network switches', 'switch', true ), vergeml_filing_class_match( 'rack', 'server racks', true ), vergeml_filing_class_match( 'banana', 'server rack' ) ) );

// The vector path, floored: two phrases at cosine 0.5 are not alike; at 0.7 they are, as 0.7.
$GLOBALS['f_vec'] = array( 'sofa' => array( 1, 0 ), 'couch' => array( 0.7, 0.714 ), 'banana' => array( 0.5, 0.866 ) );
f_check( '18 the cosine path is floored at 0.6: sofa/couch 0.70 counts, sofa/banana 0.50 is 0', abs( vergeml_filing_class_match( 'sofa', 'couch' ) - 0.7 ) < 0.01 && 0.0 === vergeml_filing_class_match( 'sofa', 'banana' ), sprintf( '%.3f %.3f', vergeml_filing_class_match( 'sofa', 'couch' ), vergeml_filing_class_match( 'sofa', 'banana' ) ) );
$GLOBALS['f_vec'] = array();

$seed = vergeml_filing_clean_seed( array( 'classes' => array( 'satellite', 'diagram', 'spacecraft' ), 'kinds' => array( 'photo' ) ), array() );
f_check( '19 a planner answer with "diagram" as a class: kinds gains it, classes does not', array( 'satellite', 'spacecraft' ) === $seed['classes'] && array( 'photo', 'diagram' ) === $seed['kinds'], json_encode( $seed ) );

$seed = vergeml_filing_clean_seed( array( 'classes' => array( 'electronics component', 'computer hardware', 'semiconductor' ) ), array( 'computer hardware' => 4 ) );
f_check( '20 a class another folder holds first is dropped and recorded: computer hardware -> Hardware (4)', array( 'electronics component', 'semiconductor' ) === $seed['classes'] && array( 'computer hardware' => 4 ) === $seed['dropped'], json_encode( $seed ) );

/*
 *  The profile ask in batches (C.5). The service answers at most
 *  VERGEML_FILING_PROFILE_BATCH folders a call and charges the folders past
 *  the first VERGEML_FILING_PROFILE_FREE of an ask, one credit per
 *  VERGEML_FILING_PROFILE_PER_CREDIT rounded up per batch
 *  (service/lib/profile-price.ts: the same three numbers, the same sum).
 *  Mutation: the batch made 500 -> row 21 red (one batch, and the service
 *  would answer the first sixty of it); the round-up per batch made one
 *  round-up over the whole -> row 22 red on the 161-folder ask.
 */
echo "\n== the profile ask, in batches (C.5)\n";

$ask = array();
for ( $i = 0; $i < 318; $i++ ) {
    $ask[] = array( 'name' => 'F' . $i, 'parent' => '', 'count' => 0 );
}
$batches = vergeml_filing_profile_batches( $ask );
f_check( '21 318 folders go as six batches of at most 60, offsets 0..300, each carrying the total', 6 === count( $batches ) && 60 === count( $batches[0]['current'] ) && 18 === count( $batches[5]['current'] ) && array( 0, 60, 120, 180, 240, 300 ) === array_column( $batches, 'offset' ) && 318 === $batches[3]['total'] && 'F300' === $batches[5]['current'][0]['name'], json_encode( array_column( $batches, 'offset' ) ) );

f_check( '22 the charge: 21 folders free; 318 cost 37 (4 + 10 + 10 + 10 + 3); 161 cost 11, rounded up per batch', 0 === vergeml_filing_profile_credits( 21 ) && 0 === vergeml_filing_profile_credits( 100 ) && 37 === vergeml_filing_profile_credits( 318 ) && 11 === vergeml_filing_profile_credits( 161 ) && 4 === vergeml_filing_profile_charge( 60, 60, 318 ) && 0 === vergeml_filing_profile_charge( 0, 60, 318 ), sprintf( '%d %d %d %d', vergeml_filing_profile_credits( 21 ), vergeml_filing_profile_credits( 318 ), vergeml_filing_profile_credits( 161 ), vergeml_filing_profile_charge( 60, 60, 318 ) ) );

// A term name as WordPress stores it (kses: "&" is "&amp;") read back as the person wrote it (C.5). Mutation: the decode removed -> red.
f_check( '23 a stored "Bags &amp; Luggage" is the folder "Bags & Luggage"; a plain name is itself', 'Bags & Luggage' === vergeml_term_name( (object) array( 'name' => 'Bags &amp; Luggage' ) ) && 'Shoes' === vergeml_term_name( (object) array( 'name' => 'Shoes' ) ) && "Children's books" === vergeml_term_name( 'Children&#039;s books' ), vergeml_term_name( (object) array( 'name' => 'Bags &amp; Luggage' ) ) );

/*
 *  The confirm asks only what a planner can add (S10.1). Two rules over the
 *  shape: a leaf under a parent is profiled from its name whether or not a
 *  picture says it yet; a parent or a top-level folder goes to the planner
 *  unless its name is one of the library's own words -- spelled the one way,
 *  or meeting one at a head noun either way round. On the shop's 322 folders
 *  308 went before (259 answered "nothing"); 39 go now. The vocabulary here
 *  is what a describer wrote for a shop: "platform sneaker", "headphones"
 *  and "running shoe" are its words, "bag", "luggage" and "kid" are not.
 *  Mutations: the head-noun match removed -> row 24 red (Sneakers and Shoes
 *  go); the leaf rule removed -> row 24 red (Blouses goes).
 */
echo "\n== the confirm's ask over the shape and the vocabulary (S10.1)\n";

$vocab = array( array( 'term' => 'platform sneaker', 'n' => 40 ), array( 'term' => 'footwear', 'n' => 60 ), array( 'term' => 'headphones', 'n' => 12 ), array( 'term' => 'running shoe', 'n' => 9 ), array( 'term' => 'garden chair', 'n' => 3 ) );
$tree  = array(
    array( 'key' => 'sneakers', 'name' => 'Sneakers', 'parent' => '' ),          // top level, the head noun of "platform sneaker": home
    array( 'key' => 'shoes', 'name' => 'Shoes', 'parent' => '' ),                // top level, the head noun of "running shoe": home
    array( 'key' => 'audio', 'name' => 'Audio', 'parent' => '' ),                // top level, no picture says it: goes
    array( 'key' => 'headphones', 'name' => 'Headphones', 'parent' => 'audio' ), // a leaf, and the word itself: home
    array( 'key' => 'bags', 'name' => 'Bags & Luggage', 'parent' => '' ),        // a parent, not a word: goes
    array( 'key' => 'backpacks', 'name' => 'Backpacks', 'parent' => 'bags' ),    // a leaf no picture says yet: home, by the shape
    array( 'key' => 'kids', 'name' => 'Kids', 'parent' => '' ),                  // a parent: goes
    array( 'key' => 'women', 'name' => 'Women', 'parent' => 'kids' ),            // a parent under a parent, not a word: goes
    array( 'key' => 'blouses', 'name' => 'Blouses', 'parent' => 'women' ),       // a leaf: home
    array( 'key' => 'garden', 'name' => 'Garden', 'parent' => '' ),              // top level; "garden chair" ends in chair, not garden: goes
);
$goes = vergeml_filing_ask_split( $tree, $vocab );
f_check( '24 of ten folders five go: Audio, Bags & Luggage, Kids, Women, Garden (parents and the top level no picture names); Sneakers and Shoes stay by a head noun, Headphones by the word, Backpacks and Blouses by being leaves', array( 'audio', 'bags', 'kids', 'women', 'garden' ) === $goes, json_encode( $goes ) );

printf( "\n%d/%d passed\n", $GLOBALS['f_pass'], $GLOBALS['f_pass'] + $GLOBALS['f_fail'] );
exit( $GLOBALS['f_fail'] > 0 ? 1 : 0 );
