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
function get_post_meta( $id, $key = '', $single = false ) {
    return '';
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
    9  => f_profile( 9, 0, array( 'Apparel', 'Men', 'Sneakers' ), array( 'footwear', 'sneakers' ), array( 'vector' => array( 0.0, 0.0, 0.0, 1.0 ) ) ),
    10 => f_profile( 10, 0, array( 'Apparel', 'Men', 'Boots' ), array( 'footwear', 'boots' ), array( 'vector' => array( 0.0, 0.0, 0.0, 1.0 ) ) ),
    11 => f_profile( 11, 0, array( 'Space' ), array( 'infrastructure', 'spacecraft' ) ),
);
// The last step every real caller takes: claims settled, shared words counted.
$profiles = vergeml_filing_settle_claims( $profiles );

/*
 *  Counted by lines (S16, from HEMA's round 1, 2026-09-17): a folder whose
 *  ancestor holds the same word is that ancestor's line, not a second
 *  holder -- the department holds "hiking boot" because its leaf does, and
 *  halving both left the boots on the floor once the leaf was named (fits
 *  297 -> 203; by lines 247 with sure 159 -> 195). Between a parent and its
 *  child the depth rule chooses. Infrastructure: Data centres' line (with
 *  Server racks and Cooling under it) and Space -- 2, not 4; server, on the
 *  three of one line, 1; device on Hardware and Phones under it, 1.
 *  Mutation: the ancestor walk removed -> row 0 red (4, 3, 2).
 */
f_check( '0 shared words counted by lines: infrastructure 2 (Data centres\' line, Space), server 1, device 1, phone 1', isset( $profiles[1]['shared'] ) && 2 === $profiles[1]['shared']['infrastructure'] && 1 === $profiles[2]['shared']['server'] && 1 === $profiles[5]['shared']['device'] && 1 === $profiles[5]['shared']['phone'], json_encode( isset( $profiles[1]['shared'] ) ? $profiles[1]['shared'] : null ) );

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
f_check( '15 a word on two lines (four folders) is worth at most 0.5 x 0.75 on any of them: nothing, floor', 'nothing' === $p['outcome'] && 'floor' === $p['why'] && max( $p['scores'] ) <= 0.5 * VERGEML_FILING_CLASS_WEIGHT + 1e-9, sprintf( '%s why %s best @%.4f', $p['outcome'], $p['why'], $p['scores'] ? max( $p['scores'] ) : 0 ) );

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

/*
 *  An irregular singular stays itself (S16, the shop's truth score): "lenses"
 *  folds to "lens" by the table, and "lens" fell to "len" by the plural rule,
 *  so "camera lens" never equalled a leaf named Lenses -- 11 of 12 lens
 *  pictures scored 0.63 on their own folder and went to TV & Video. The
 *  fold removed -> 17c red.
 */
f_check( '17c canon: a word the table gives as a singular is left alone -- lens, bus, scarf -- and "camera lens" is a leaf named Lenses in full', 'lens' === vergeml_filing_canon( 'lens' ) && 'camera lens' === vergeml_filing_canon( 'camera lens' ) && 'lens' === vergeml_filing_canon( 'lenses' ) && 'bus' === vergeml_filing_canon( 'bus' ) && 'scarf' === vergeml_filing_canon( 'scarf' ) && 1.0 === vergeml_filing_class_match( 'camera lens', 'lenses', true ), sprintf( '%s · %s · %.2f', vergeml_filing_canon( 'lens' ), vergeml_filing_canon( 'camera lens' ), vergeml_filing_class_match( 'camera lens', 'lenses', true ) ) );

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
/*
 *  A tree in another language than the describer's (S15, HEMA's second
 *  finding, Nathan's yes on the credits): "wandelschoenen" hits no English
 *  word and never will, so on a site whose language is not the describer's
 *  a leaf whose name no picture says goes to the planner too, for a class
 *  in the describer's words; a leaf that is already a library word stays
 *  home as before. Mutation: the foreign branch removed -> row 24b red.
 */
$goes = vergeml_filing_ask_split( $tree, $vocab, true );
f_check( '24b the same tree on a site in another language: the leaves no picture names go too (Backpacks, Blouses), Headphones still stays', array( 'audio', 'bags', 'backpacks', 'kids', 'women', 'blouses', 'garden' ) === $goes, json_encode( $goes ) );

/*
 *  An answer is a decision (2026-09-17, Nathan on the shop: "every fill
 *  results in the same images being asked again"). "Split them by best
 *  score" moved the pictures with no mark, "Keep them in Phones" moved and
 *  marked nothing, so the next fill scored the same tie and asked the same
 *  question. Both now mark the pictures `answer` -- kept by the fill like
 *  `user`, but the word on the picture stays the fill's ("likely"), not "by
 *  you". Mutations: 'answer' dropped from the pick's kept test -> row 25 red;
 *  the split's placed_by -> row 26 red.
 */
echo "\n== an answer is a decision (S10 hunt)\n";

$p = vergeml_filing_pick( f_facts( 'charger', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ), 'placed_by' => 'answer' ) ), $profiles );
f_check( '25 a picture an answer placed is kept by the next fill, like one the user placed: nothing, placed', 'nothing' === $p['outcome'] && 'placed' === $p['why'], sprintf( '%s why %s', $p['outcome'], $p['why'] ) );
f_check( '25b its word stays the fill\'s: "likely" on a split, never "by you"', 'likely' === vergeml_filing_confidence( 0, array( 'why' => 'answer', 'score' => 0.6 ) ) && '' === vergeml_filing_confidence( 0, array( 'why' => 'floor' ) ), vergeml_filing_confidence( 0, array( 'why' => 'answer', 'score' => 0.6 ) ) );

$q = array( 'id' => 'e:5:3', 'kind' => 'either', 'term_id' => 0, 'ids' => array( 301 => 5, 302 => 3 ), 'answers' => array( 'put-in:5', 'put-in:3', 'split', 'leave', 'show-me' ), 'name' => '' );
$plan = vergeml_filing_answer_plan( $q, 'split' );
f_check( '26 split: each to its best of the two, and marked as answered', array( 301 => 5, 302 => 3 ) === $plan['moves'] && 'answer' === $plan['placed_by'], json_encode( array( $plan['moves'], $plan['placed_by'] ) ) );
$q = array( 'id' => 's:1', 'kind' => 'siblings', 'term_id' => 1, 'ids' => array( 303 => 2, 304 => 3 ), 'answers' => array( 'keep-parent', 'split', 'show-me' ), 'name' => '' );
$plan = vergeml_filing_answer_plan( $q, 'keep-parent' );
f_check( '26b keep-parent: the pictures stay in the parent, and are marked as answered so the next fill leaves them', array( 303 => 1, 304 => 1 ) === $plan['moves'] && 'answer' === $plan['placed_by'], json_encode( array( $plan['moves'], $plan['placed_by'] ) ) );
$plan = vergeml_filing_answer_plan( $q, 'split' );
f_check( '26c a siblings split is marked too', array( 303 => 2, 304 => 3 ) === $plan['moves'] && 'answer' === $plan['placed_by'], json_encode( $plan['placed_by'] ) );

/*
 *  The fill learns from its own placements (S10.7). A folder holding three
 *  or more described pictures is profiled from them: its classes their
 *  object words (the first phrase of each, never the class half -- every
 *  folder under Clothing says "clothing" there, and a word all folders hold
 *  is worth 1/k on each), most carried first, the plan's own words after
 *  them and the leaf kept; its vector their centroid; source 'members'. A
 *  word one member says is that picture, not the folder (the tech library's
 *  folders each held a few of the fill's misses, and every miss became a
 *  class): two members must say it, and members that agree on nothing leave
 *  the profile as it was. Mutations: the layer's classes not put before the
 *  base's -> row 28a red; the centroid not taken -> row 27 red; the
 *  three-member floor removed -> row 27b red; the two-member word rule
 *  removed -> row 27 red (workstation kept) and 27d red.
 */
echo "\n== a folder profiled from what it holds (S10.7)\n";

$ws      = f_profile( 12, 0, array( 'Workstations' ), array( 'workstations' ), array( 'source' => 'name', 'vector' => array( 0.0, 0.0, 1.0, 0.0 ) ) );
$members = array();
for ( $i = 0; $i < 5; $i++ ) {
    $members[] = f_facts( 'desktop pc; computer hardware', array( 'vector' => array( 0.0, 1.0, 1.0, 0.0 ) ) );
}
$members[] = f_facts( 'tower case; computer hardware', array( 'vector' => array( 0.0, 1.0, 0.0, 0.0 ) ) );
$members[] = f_facts( 'tower case; computer hardware', array( 'vector' => array( 0.0, 1.0, 0.0, 0.0 ) ) );
$members[] = f_facts( 'workstation; computer hardware', array( 'vector' => array( 0.0, 1.0, 0.0, 0.0 ) ) );

$layer = vergeml_filing_members_layer( $members );
$mp    = vergeml_filing_members_apply( $ws, $layer );
f_check( '27 eight members {desktop pc x5, tower case x2, workstation x1}: the words two or more say, most carried first, the leaf kept by name, the class half left out; the vector their centroid, source members, 8 counted', array( 'desktop pc', 'tower case', 'workstations' ) === $mp['classes'] && 'members' === $mp['source'] && 8 === $mp['members'] && 'name' === $mp['base_source'] && abs( $mp['vector'][1] - 1.0 ) < 1e-9 && abs( $mp['vector'][2] - 0.625 ) < 1e-9 && array( 'desktop pc' => 5, 'tower case' => 2 ) === $layer['words'], json_encode( array( $mp['classes'], $mp['source'], $mp['members'], $mp['vector'], $layer['words'] ) ) );

$two = vergeml_filing_members_apply( $ws, vergeml_filing_members_layer( array_slice( $members, 0, 2 ) ) );
f_check( '27b two members are not enough: the profile stays the name\'s', array( 'workstations' ) === $two['classes'] && 'name' === $two['source'] && ! isset( $two['members'] ) && null === vergeml_filing_members_layer( array_slice( $members, 0, 2 ) ), json_encode( array( $two['classes'], $two['source'] ) ) );

$hw    = f_profile( 4, 0, array( 'Hardware' ), array( 'computer hardware', 'computer', 'hardware', 'device' ) );
$hwm   = vergeml_filing_members_apply( $hw, vergeml_filing_members_layer( array( f_facts( 'gpu; computer hardware' ), f_facts( 'gpu; computer hardware' ), f_facts( 'motherboard; computer hardware' ) ) ) );
f_check( '27c a planned folder keeps the plan\'s words after its members\' and the leaf: gpu, then computer hardware, computer, hardware, device (motherboard, said once, is not the folder\'s)', array( 'gpu', 'computer hardware', 'computer', 'hardware', 'device' ) === $hwm['classes'] && 'plan' === $hwm['base_source'], json_encode( $hwm['classes'] ) );
$none = vergeml_filing_members_layer( array( f_facts( 'cable reel; cable' ), f_facts( 'brewery production line; industry' ), f_facts( 'laboratory cleanroom; laboratory' ) ) );
f_check( '27d three members that agree on nothing make no layer: the folder keeps the profile it had', null === $none, json_encode( $none ) );

/*
 *  A member word belongs to the folder holding most of the pictures that say
 *  it (as a planner class belongs to one folder): on the tech library the
 *  fill had put two smart speakers in Batteries and eight in Components,
 *  both folders learned the word, and 34 pictures tied between them. Equal
 *  counts keep it on both -- an honest tie, worth 1/k. Mutation: the settle
 *  skipped in vergeml_filing_profiles -> not seen here; the cede made to
 *  keep the word -> row 27e red.
 */
$settled = vergeml_filing_members_settle( array(
    21 => array( 'n' => 20, 'classes' => array( 'lithium-ion battery', 'smart speaker', 'charger' ), 'words' => array( 'lithium-ion battery' => 9, 'smart speaker' => 2, 'charger' => 2 ), 'vector' => null, 'built_at' => 1 ),
    22 => array( 'n' => 132, 'classes' => array( 'circuit board', 'smart speaker', 'chargers' ), 'words' => array( 'circuit board' => 40, 'smart speaker' => 8, 'chargers' => 2 ), 'vector' => null, 'built_at' => 1 ),
    23 => array( 'n' => 3, 'classes' => array( 'smart speaker' ), 'words' => array( 'smart speaker' => 3 ), 'vector' => null, 'built_at' => 1 ),
) );
f_check( '27e smart speaker goes to the folder with eight (Batteries cedes it, and so does the folder of three); charger at two each stays on both; the folder left with no word has no layer', array( 'lithium-ion battery', 'charger' ) === $settled[21]['classes'] && array( 'circuit board', 'smart speaker', 'chargers' ) === $settled[22]['classes'] && ! isset( $settled[23] ) && array( 'smart speaker' => 22 ) === $settled[21]['ceded'], json_encode( array( $settled[21]['classes'], $settled[22]['classes'], isset( $settled[23] ), $settled[21]['ceded'] ) ) );

$desk = f_facts( 'desktop pc; computer hardware', array( 'vector' => array( 0.0, 1.0, 1.0, 0.0 ) ) );
$p    = vergeml_filing_pick( $desk, vergeml_filing_settle_claims( array( 12 => $ws ) ) );
f_check( '28 against Workstations by its name alone, "desktop pc; computer hardware" is below the floor', 'nothing' === $p['outcome'] && 'floor' === $p['why'], sprintf( '%s why %s @%.3f', $p['outcome'], $p['why'], $p['score'] ) );
$p = vergeml_filing_pick( $desk, vergeml_filing_settle_claims( array( 12 => $mp ) ) );
f_check( '28a on the member profile it fits, sure: desktop pc is the first class (0.75) and the centroid agrees', 'fits' === $p['outcome'] && 12 === $p['term_id'] && 'sure' === $p['confidence'] && $p['score'] > 0.9, sprintf( '%s %d @%.3f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );
$p = vergeml_filing_pick( $desk, vergeml_filing_settle_claims( array( 4 => $hw, 12 => $mp ) ) );
f_check( '28b beside Hardware (computer hardware first: 0.6375, likely) the member profile outranks it: Workstations, sure, clear of the margin', 'fits' === $p['outcome'] && 12 === $p['term_id'] && 'sure' === $p['confidence'] && 4 === $p['runner_up'] && $p['score'] - $p['runner_score'] >= VERGEML_FILING_MARGIN, sprintf( '%s %d @%.3f %s next %d @%.3f', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'], $p['runner_up'], $p['runner_score'] ) );

/*
 *  The picture's own words as evidence (S10.9): a third phrase list from
 *  the filename (split on -_. and space), the title and the alt, and a hit
 *  there counts like the describer's second phrase (0.85). Words only: a
 *  word is a class when it is the class spelled the one way, or its head
 *  noun ("dress" is a summer dress); never a modifier ("keyboard" is not a
 *  keyboard layout diagram) and never by vector -- a filename's "red" and
 *  "front" would otherwise ask the service about every folder. Mutations:
 *  the third list dropped from the pick -> row 29 red (margin); the head
 *  noun made "inside anywhere" -> row 29b red (keyboard hits the diagram).
 */
echo "\n== the picture's own words (S10.9)\n";

f_check( '29a words: "summer-dress-red-front.jpg" + "Summer dress" + "A red summer dress, front view" -> summer, dress, red, front, view (the extension, short words and numbers dropped, each word once); a camera\'s IMG_4021.HEIC leaves img, which no folder is called', array( 'summer', 'dress', 'red', 'front', 'view' ) === vergeml_filing_words_of( 'summer-dress-red-front.jpg', 'Summer dress', 'A red summer dress, front view' ) && array( 'smith', 'wedding' ) === vergeml_filing_words_of( '2024-06-smith-wedding-012.jpg', '', '' ) && array( 'img' ) === vergeml_filing_words_of( 'IMG_4021.HEIC', 'IMG_4021', '' ), json_encode( array( vergeml_filing_words_of( 'summer-dress-red-front.jpg', 'Summer dress', 'A red summer dress, front view' ), vergeml_filing_words_of( '2024-06-smith-wedding-012.jpg', '', '' ), vergeml_filing_words_of( 'IMG_4021.HEIC', 'IMG_4021', '' ) ) ) );

f_check( '29b word_match: dress = dresses 1.0; dress is the head of summer dress 0.95; keyboard is not a keyboard layout diagram; red is nothing, and nothing asks the service', 1.0 === vergeml_filing_word_match( 'dress', 'dresses' ) && 0.95 === vergeml_filing_word_match( 'dress', 'summer dress' ) && 0.0 === vergeml_filing_word_match( 'keyboard', 'keyboard layout diagram' ) && 0.0 === vergeml_filing_word_match( 'red', 'sneakers' ) && 0.0 === vergeml_filing_word_match( 'sofa', 'couch' ), sprintf( '%.2f %.2f %.2f %.2f %.2f', vergeml_filing_word_match( 'dress', 'dresses' ), vergeml_filing_word_match( 'dress', 'summer dress' ), vergeml_filing_word_match( 'keyboard', 'keyboard layout diagram' ), vergeml_filing_word_match( 'red', 'sneakers' ), vergeml_filing_word_match( 'sofa', 'couch' ) ) );

// Row 11's shape: footwear on Sneakers and Boots at 1/2, the vectors equal -> margin. The filename says which.
$p = vergeml_filing_pick( f_facts( 'footwear', array( 'vector' => array( 0.0, 0.0, 0.0, 1.0 ), 'words' => array( 'mens', 'sneakers', 'white' ) ) ), $profiles );
f_check( '29 "footwear" that tied Sneakers and Boots is settled by its filename word: Sneakers (0.85 on its own name = 0.6375, plus the vector 0.25)', 'fits' === $p['outcome'] && 9 === $p['term_id'] && 10 === $p['runner_up'] && abs( $p['score'] - 0.8875 ) < 1e-9, sprintf( '%s %d @%.4f %s next %d @%.4f', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'], $p['runner_up'], $p['runner_score'] ) );
// The words never outrank the describer: "phone; device" named "boots.jpg" is still a phone.
$p = vergeml_filing_pick( f_facts( 'phone; device', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ), 'words' => array( 'boots' ) ) ), $profiles );
f_check( '29c a filename word never outranks the describer\'s object: "phone; device" named boots.jpg stays in Phones, sure', 'fits' === $p['outcome'] && 5 === $p['term_id'] && 'sure' === $p['confidence'], sprintf( '%s %d @%.3f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );
/*
 *  A picture's own word is corroboration, not evidence on its own (S14). On
 *  the tech library (2026-09-17) ten of the S13 sheet's seventeen wrong
 *  likelies stood on one filename word where the describer's phrases hit
 *  the folder not at all: "farm" of a 3D-printer farm was Wind's "wind
 *  farm", "switch" of a smart plug was Server racks' "network switches",
 *  "technology" of a conference award was Phones' "wearable technology" --
 *  a likely at 0.57-0.70 with a runner-up at 0.13. So the words are read
 *  only where the describer's phrases already hit the folder -- the
 *  folder's own name included: a title saying "phone" put an office desk
 *  in Phones, sure, when the name was let count alone (a shoot's folder,
 *  "smith-wedding-012.jpg" in Smith wedding, is S10.10's signal to the
 *  planner, not the pick's). Mutation: the words read wherever the class
 *  is under 0.85 -> row 29e red (Wind, sure).
 */
$farm = vergeml_filing_settle_claims( array(
    40 => f_profile( 40, 0, array( 'Energy', 'Wind' ), array( 'wind turbine', 'wind farm', 'wind' ), array( 'vector' => array( 0.0, 0.0, 1.0, 0.0 ) ) ),
    41 => f_profile( 41, 0, array( 'Hardware', 'Phones' ), array( 'smartphone', 'phones' ), array( 'vector' => array( 0.0, 1.0, 0.0, 0.0 ) ) ),
) );
$p = vergeml_filing_pick( f_facts( '3d printer farm; workshop equipment', array( 'vector' => array( 0.0, 0.0, 0.5, 0.0 ), 'words' => array( 'printer', 'farm', 'hackerspace' ) ) ), $farm );
f_check( '29e "3d printer farm; workshop equipment" named printer-farm.jpg: the describer hits Wind not at all, and "farm" alone (the head of wind farm) is not evidence -- nothing, floor', 'nothing' === $p['outcome'] && 'floor' === $p['why'] && $p['score'] < 0.3, sprintf( '%s why %s @%.4f', $p['outcome'], $p['why'], $p['score'] ) );
$p = vergeml_filing_pick( f_facts( 'office desk; furniture', array( 'vector' => array( 0.0, 0.4, 0.0, 0.0 ), 'words' => array( 'office', 'desk', 'with', 'computer', 'phone' ) ) ), $farm );
f_check( '29f "office desk; furniture" whose title says phone: the folder\'s own name alone is no evidence either -- nothing, floor (the tech library, 2026-09-17: Phones, sure 0.74)', 'nothing' === $p['outcome'] && 'floor' === $p['why'] && $p['score'] < 0.3, sprintf( '%s why %s @%.4f', $p['outcome'], $p['why'], $p['score'] ) );
$p = vergeml_filing_pick( f_facts( 'wind turbine; energy infrastructure', array( 'vector' => array( 0.0, 0.0, 0.5, 0.0 ), 'words' => array( 'farm' ) ) ), $farm );
f_check( '29g where the describer hits the folder, the word still corroborates: "wind turbine" named farm.jpg is Wind, sure', 'fits' === $p['outcome'] && 40 === $p['term_id'] && 'sure' === $p['confidence'], sprintf( '%s %d @%.4f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );

// facts() reads the row's title, file and alt into the list.
$fx = vergeml_filing_facts( array( 'filing' => json_encode( array( 'object' => 'footwear' ) ), 'kind' => 'photo', 'file' => '2026/09/mens-sneakers-white.jpg', 'title' => 'mens-sneakers-white', 'alt' => '' ) );
f_check( '29d facts: the row\'s file, title and alt become the words (the upload path\'s folders dropped)', array( 'mens', 'sneakers', 'white' ) === $fx['words'], json_encode( $fx['words'] ) );

/*
 *  The head noun against its own class (S10.5 rule 3). "cheese wheel;
 *  cheese" hit Cheese at 0.95 (the phrase inside) and Wheels at 1.0 (the
 *  head noun), and a wheel of cheese went to Wheels, sure. The describer's
 *  class half says what it is: when that half names another folder in
 *  full, a first-phrase hit on a class that is only the phrase's head noun
 *  is worth 0.9, whichever path scored it -- so Cheese outranks Wheels,
 *  and inside the margin the fill asks rather than files wrongly. "rocket
 *  launch; launch" keeps its 1.0 on Launches: its class half names
 *  Launches itself, not another folder. Mutation: the cap removed -> row
 *  30 red (Wheels, sure).
 */
echo "\n== the head noun against its own class (S10.5 rule 3)\n";

$shop = vergeml_filing_settle_claims( array(
    31 => f_profile( 31, 0, array( 'Cheese' ), array( 'cheese' ), array( 'source' => 'name' ) ),
    32 => f_profile( 32, 0, array( 'Wheels' ), array( 'wheels' ), array( 'source' => 'name' ) ),
    33 => f_profile( 33, 0, array( 'Launches' ), array( 'launches' ), array( 'source' => 'name' ) ),
) );
$p = vergeml_filing_pick( f_facts( 'cheese wheel; cheese' ), $shop );
f_check( '30 "cheese wheel; cheese": Cheese 0.7125 outranks Wheels, now 0.675 -- too close to file, so an either/or with Cheese first, never Wheels sure', 'nothing' === $p['outcome'] && 'margin' === $p['why'] && isset( $p['children'] ) && array( 31, 32 ) === array_values( array_map( 'intval', (array) $p['children'] ) ) && abs( $p['scores'][32] - 0.675 ) < 1e-9 && abs( $p['scores'][31] - 0.7125 ) < 1e-9, sprintf( '%s why %s children %s Cheese @%.4f Wheels @%.4f', $p['outcome'], $p['why'], wp_json_encode_lite( isset( $p['children'] ) ? $p['children'] : null ), $p['scores'][31], $p['scores'][32] ) );
$p = vergeml_filing_pick( f_facts( 'rocket launch; launch' ), $shop );
f_check( '30b "rocket launch; launch" is still a launch in full: Launches, sure, 0.75', 'fits' === $p['outcome'] && 33 === $p['term_id'] && 'sure' === $p['confidence'] && abs( $p['score'] - 0.75 ) < 1e-9, sprintf( '%s %d @%.4f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );
$home = vergeml_filing_settle_claims( array(
    34 => f_profile( 34, 0, array( 'Garden', 'Furniture' ), array( 'furniture' ), array( 'source' => 'name' ) ),
    35 => f_profile( 35, 0, array( 'Home', 'Furniture', 'Sofas' ), array( 'sofas' ), array( 'source' => 'name' ) ),
) );
$p = vergeml_filing_pick( f_facts( 'klippan sofa; furniture' ), $home );
f_check( '30d "klippan sofa; furniture": the class half is the category, not the modifier -- the head noun stands and Sofas takes it, sure (the shop, 2026-09-17: a cap on every class half made this a tie with Garden › Furniture)', 'fits' === $p['outcome'] && 35 === $p['term_id'] && 'sure' === $p['confidence'] && abs( $p['score'] - 0.75 ) < 1e-9, sprintf( '%s %d @%.4f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );
$p = vergeml_filing_pick( f_facts( 'cheese wheel' ), $shop );
f_check( '30c "cheese wheel" with no class half: nothing says otherwise, so the head noun stands -- Wheels 0.75 over Cheese 0.7125, a margin either/or as before', 'nothing' === $p['outcome'] && 'margin' === $p['why'] && abs( $p['scores'][32] - 0.75 ) < 1e-9, sprintf( '%s why %s Wheels @%.4f', $p['outcome'], $p['why'], $p['scores'][32] ) );

/*
 *  A class half the library says is a library word (S15). The planner put
 *  "electronics" on Batteries; on the tech library (2026-09-17) 93 pictures
 *  said "electronics" as their class half and they sat in Components,
 *  Phones and Hardware -- none in Batteries -- so "vr headset; electronics"
 *  was Batteries', likely, on a word Batteries' own pictures never say. The
 *  members' layer carries the class halves two or more members say, and a
 *  folder class is worth 1/k over the folders whose members say it (whole,
 *  or one inside the other), as it already is over the folders that hold
 *  it. A word one folder's pictures say keeps k 1 and still places alone.
 *  Mutations: the halves not counted into the shared words -> rows 31a and
 *  31b red (Batteries, sure); the halves dropped from the layer -> 31 red.
 */
echo "\n== a class half the library says (S15)\n";

$lay = vergeml_filing_members_settle( array(
    51 => vergeml_filing_members_layer( array( f_facts( 'lithium-ion battery; power source' ), f_facts( 'lithium-ion battery; power source' ), f_facts( 'battery charger; power source' ) ) ),
    52 => vergeml_filing_members_layer( array( f_facts( 'circuit board; electronics' ), f_facts( 'circuit board; electronics' ), f_facts( 'silicon wafer; electronic component' ) ) ),
    53 => vergeml_filing_members_layer( array( f_facts( 'smartphone; electronics' ), f_facts( 'smartphone; electronics' ), f_facts( 'smartwatch; wearable technology' ) ) ),
) );
f_check( '31 the layer carries the class halves two or more members say, spelled the one way: Components {electronic 2}, Phones {electronic 2}, Batteries {power source 3}; a half one member says is not the folder\'s', isset( $lay[52]['halves'], $lay[53]['halves'], $lay[51]['halves'] ) && array( 'electronic' => 2 ) === $lay[52]['halves'] && array( 'electronic' => 2 ) === $lay[53]['halves'] && array( 'power source' => 3 ) === $lay[51]['halves'], json_encode( array( isset( $lay[52]['halves'] ) ? $lay[52]['halves'] : null, isset( $lay[53]['halves'] ) ? $lay[53]['halves'] : null, isset( $lay[51]['halves'] ) ? $lay[51]['halves'] : null ) ) );

$lib = vergeml_filing_settle_claims( array(
    51 => vergeml_filing_members_apply( f_profile( 51, 0, array( 'Energy', 'Batteries' ), array( 'battery', 'electronics', 'power source' ), array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ), $lay[51] ),
    52 => vergeml_filing_members_apply( f_profile( 52, 0, array( 'Hardware', 'Components' ), array( 'circuit board', 'electronics component', 'components' ), array( 'vector' => array( 0.0, 1.0, 0.0, 0.0 ) ) ), $lay[52] ),
    53 => vergeml_filing_members_apply( f_profile( 53, 0, array( 'Hardware', 'Phones' ), array( 'smartphone', 'phones' ), array( 'vector' => array( 0.0, 0.0, 1.0, 0.0 ) ) ), $lay[53] ),
) );
f_check( '31a shared: electronics is held by one folder and said by two, so k 2; electronics component contains the library word and is not one, k 1; power source, said by Batteries alone, k 1; battery k 1', isset( $lib[51]['shared'] ) && 2 === $lib[51]['shared']['electronic'] && 1 === $lib[52]['shared']['electronics component'] && 1 === $lib[51]['shared']['power source'] && 1 === $lib[51]['shared']['battery'], json_encode( isset( $lib[51]['shared'] ) ? $lib[51]['shared'] : null ) );
$p = vergeml_filing_pick( f_facts( 'vr headset; electronics', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ), $lib );
f_check( '31b "vr headset; electronics" whose vector is Batteries\' own: the word is the library\'s (0.85 x 0.85 / 2 = 0.36), and no folder clears the floor -- nothing (Batteries, sure 0.79, before)', 'nothing' === $p['outcome'] && 'floor' === $p['why'] && $p['scores'][51] < 0.55, sprintf( '%s why %s Batteries @%.4f Components @%.4f', $p['outcome'], $p['why'], $p['scores'][51], $p['scores'][52] ) );
$p = vergeml_filing_pick( f_facts( 'solar charger; power source', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ), $lib );
f_check( '31c "solar charger; power source": a class half only Batteries\' pictures say still places alone -- Batteries, sure (0.85 x 0.85 x 0.75 + 0.25)', 'fits' === $p['outcome'] && 51 === $p['term_id'] && 'sure' === $p['confidence'], sprintf( '%s %d @%.4f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );
$p = vergeml_filing_pick( f_facts( 'smartphone; electronics', array( 'vector' => array( 0.0, 0.0, 1.0, 0.0 ) ) ), $lib );
f_check( '31d the object word is untouched: "smartphone; electronics" is Phones, sure', 'fits' === $p['outcome'] && 53 === $p['term_id'] && 'sure' === $p['confidence'], sprintf( '%s %d @%.4f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );

/*
 *  The class half inside a folder's specific phrase (S15, the second story).
 *  "drone; electronics" hit Components' planner class "electronics
 *  component" at 0.95 -- the picture's generic half sitting whole inside the
 *  folder's specific phrase -- and "3d printer; equipment" hit Energy's
 *  "renewable energy equipment" the same way: on the tech library
 *  (2026-09-17) 22 pictures with no folder of their own went to Components
 *  or Energy, likely, once the library-word rule took Batteries' hold off
 *  them. A generic half inside a specific class is not that class; the
 *  other way round ("launch" is the head of "launch event") the folder's
 *  word is what the picture is, and stands; and a folder's own leaf keeps
 *  the hit ("appliance" under Kitchen appliances), as does a first class.
 *  Mutation: the inside test removed from the pick -> rows 32 and 32b red
 *  (Components sure, Energy likely).
 */
echo "\n== the class half inside a folder's specific phrase (S15)\n";

$gen = vergeml_filing_settle_claims( array(
    61 => f_profile( 61, 0, array( 'Hardware', 'Components' ), array( 'circuit board', 'electronics component', 'components' ), array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
    62 => f_profile( 62, 0, array( 'Energy' ), array( 'renewable energy infrastructure', 'renewable energy equipment', 'energy' ), array( 'vector' => array( 0.0, 1.0, 0.0, 0.0 ) ) ),
    63 => f_profile( 63, 0, array( 'Kitchen appliances' ), array( 'kitchen appliances' ), array( 'source' => 'name', 'vector' => array( 0.0, 0.0, 1.0, 0.0 ) ) ),
    64 => f_profile( 64, 0, array( 'Space', 'Launches' ), array( 'rocket launch', 'launch' ), array( 'vector' => array( 0.0, 0.0, 0.0, 1.0 ) ) ),
) );
$p = vergeml_filing_pick( f_facts( 'drone; electronics', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ), $gen );
f_check( '32 "drone; electronics" whose vector is Components\' own: electronics inside "electronics component" (rank 1, not the leaf) is no hit -- nothing, floor (Components, sure 0.76, before)', 'nothing' === $p['outcome'] && 'floor' === $p['why'] && $p['scores'][61] < 0.3, sprintf( '%s why %s Components @%.4f', $p['outcome'], $p['why'], $p['scores'][61] ) );
$p = vergeml_filing_pick( f_facts( '3d printer; equipment', array( 'vector' => array( 0.0, 1.0, 0.0, 0.0 ) ) ), $gen );
f_check( '32b "3d printer; equipment": equipment inside "renewable energy equipment" is no hit either -- nothing, floor', 'nothing' === $p['outcome'] && 'floor' === $p['why'] && $p['scores'][62] < 0.3, sprintf( '%s why %s Energy @%.4f', $p['outcome'], $p['why'], $p['scores'][62] ) );
$p = vergeml_filing_pick( f_facts( 'toaster; appliance', array( 'vector' => array( 0.0, 0.0, 1.0, 0.0 ) ) ), $gen );
f_check( '32c a folder\'s own leaf keeps the hit: "toaster; appliance" under Kitchen appliances fits (0.85 x 0.95 x 0.75 + 0.25 = 0.86, sure)', 'fits' === $p['outcome'] && 63 === $p['term_id'] && abs( $p['score'] - 0.855625 ) < 1e-6, sprintf( '%s %d @%.4f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );
$p = vergeml_filing_pick( f_facts( 'soyuz; launch event', array( 'vector' => array( 0.0, 0.0, 0.0, 1.0 ) ) ), $gen );
f_check( '32d the other way round stands: "soyuz; launch event" -- Launches\' "launch" is the head of the half -- Launches, sure', 'fits' === $p['outcome'] && 64 === $p['term_id'] && 'sure' === $p['confidence'], sprintf( '%s %d @%.4f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );

/*
 *  A view of the tree owns nothing (S15, HEMA's first and third findings).
 *  HEMA repeats its departments under "sale" and "nieuwe collectie": 10 of
 *  sale's 11 children and 11 of nieuwe collectie's 12 are the tree's own
 *  top-level names, where a department repeats one in twelve (baby under
 *  wonen en slapen). The planner, asked what "nieuwe collectie" holds,
 *  answered with the library's vocabulary -- 24 classes, every other
 *  parent 3 to 11 -- and the folder took 201 of 626 on round 1. A folder
 *  more than half of whose children repeat names held outside its own
 *  subtree is a view: it and everything under it own nothing by words or
 *  vector, learn nothing from members, and the copy of a name under it is
 *  never filed into -- the folder of that name elsewhere is the one. A
 *  class-count rule was judged first and not built: past eight classes
 *  read from the name alone took HEMA's round 1 from fits 422 to 140 (it
 *  took buiten en onderweg's 95 with nieuwe collectie's 201). Mutations:
 *  the view pass removed -> rows 33 and 33a red (a tie with Sale ›
 *  Electronics); the majority made "any child" -> 33b red.
 */
echo "\n== a view of the tree owns nothing (S15)\n";

$store = array(
    71 => f_profile( 71, 0, array( 'Electronics' ), array( 'electronics' ), array( 'source' => 'name', 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
    72 => f_profile( 72, 0, array( 'Home' ), array( 'home' ), array( 'source' => 'name' ) ),
    73 => f_profile( 73, 72, array( 'Home', 'Baby' ), array( 'baby' ), array( 'source' => 'name' ) ),
    74 => f_profile( 74, 72, array( 'Home', 'Furniture' ), array( 'furniture' ), array( 'source' => 'name' ) ),
    75 => f_profile( 75, 72, array( 'Home', 'Lighting' ), array( 'lighting' ), array( 'source' => 'name' ) ),
    76 => f_profile( 76, 0, array( 'Baby' ), array( 'baby' ), array( 'source' => 'name' ) ),
    77 => f_profile( 77, 0, array( 'Sale' ), array( 'electronics', 'furniture', 'lighting', 'baby' ), array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
    78 => f_profile( 78, 77, array( 'Sale', 'Electronics' ), array( 'electronics' ), array( 'source' => 'name', 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
    79 => f_profile( 79, 77, array( 'Sale', 'Home' ), array( 'home' ), array( 'source' => 'name' ) ),
    80 => f_profile( 80, 77, array( 'Sale', 'Gifts' ), array( 'gifts' ), array( 'source' => 'name' ) ),
    81 => f_profile( 81, 79, array( 'Sale', 'Home', 'Furniture' ), array( 'furniture' ), array( 'source' => 'name' ) ),
);
$views = vergeml_filing_views( $store );
f_check( '33 Sale (two of three children repeat Electronics and Home) is a view, and so is everything under it: 77, 78, 79, 80, 81 own nothing; Home (Baby is one of three) is not, nor are Electronics and Baby', array( 77, 78, 79, 80, 81 ) === array_keys( array_filter( $views, function ( $p ) { return ! empty( $p['view'] ); } ) ) && array() === $views[77]['classes'] && array() === $views[78]['classes'] && null === $views[78]['vector'] && 77 === $views[81]['view'] && array( 'home' ) === $views[72]['classes'] && array( 'furniture' ) === $views[74]['classes'], json_encode( array( array_keys( array_filter( $views, function ( $p ) { return ! empty( $p['view'] ); } ) ), $views[77]['classes'], $views[72]['classes'] ) ) );
$p = vergeml_filing_pick( f_facts( 'smart speaker; electronics', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ), vergeml_filing_settle_claims( $views ) );
f_check( '33a "smart speaker; electronics" is the top-level Electronics\', sure, not a tie with Sale › Electronics or Sale (the three held the word at 1/3 each before)', 'fits' === $p['outcome'] && 71 === $p['term_id'] && ( 0 === $p['runner_up'] || $p['runner_score'] < 0.3 ), sprintf( '%s %d @%.4f %s next %d @%.4f', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'], $p['runner_up'], $p['runner_score'] ) );
$p = vergeml_filing_pick( f_facts( 'crib; baby' ), vergeml_filing_settle_claims( $views ) );
f_check( '33b Home is not a view: Home › Baby and the top-level Baby still share "baby" (k 2) and tie -- a question, as before', 'nothing' === $p['outcome'] && 'margin' === $p['why'], sprintf( '%s why %s', $p['outcome'], $p['why'] ) );
/*
 *  A department split by audience is not a view (the C.5 tree, 2026-09-17):
 *  Watches › Men, Women, Smart watches, with Men and Women at the top of the
 *  tree too, read as two of three repeating a name held higher, and Watches
 *  owned nothing -- eight smartwatch sures and six wristwatch sures fell to
 *  the parent or a margin. A child named for an audience is a split, never
 *  a repeat. Mutation: the audience exception removed -> 33c red.
 */
$split = vergeml_filing_views( array(
    91 => f_profile( 91, 0, array( 'Men' ), array( 'men' ), array( 'source' => 'name', 'audience' => 'men' ) ),
    92 => f_profile( 92, 0, array( 'Women' ), array( 'women' ), array( 'source' => 'name', 'audience' => 'women' ) ),
    93 => f_profile( 93, 0, array( 'Watches' ), array( 'watches' ), array( 'source' => 'name' ) ),
    94 => f_profile( 94, 93, array( 'Watches', 'Men' ), array( 'men' ), array( 'source' => 'name', 'audience' => 'men' ) ),
    95 => f_profile( 95, 93, array( 'Watches', 'Women' ), array( 'women' ), array( 'source' => 'name', 'audience' => 'women' ) ),
    96 => f_profile( 96, 93, array( 'Watches', 'Smart watches' ), array( 'smart watches' ), array( 'source' => 'name' ) ),
) );
f_check( '33c Watches › Men, Women, Smart watches under a tree with Men and Women at the top is a split by audience, not a view: Watches keeps its word', empty( $split[93]['view'] ) && array( 'watches' ) === $split[93]['classes'] && empty( $split[96]['view'] ), json_encode( array( isset( $split[93]['view'] ), $split[93]['classes'] ) ) );

/*
 *  A member word only where it is alike to the folder's own (S16, Nathan's
 *  S15 verdict: 6 of the 7 wrong sures were words the folders had learned
 *  from the fill's own earlier misses -- Cooling learned "cable reels" from
 *  fibre reels the fill had put there, Server racks "network switches"; a
 *  members layer built on a round that was 34 % right entrenches its misses
 *  as sures). A member word counts only where it is alike to one of the
 *  base profile's words -- the plan's or the name's -- spelled the one way,
 *  by containment or the head noun, or by the phrase vector at the members'
 *  own floor, 0.5 (VERGEML_FILING_MEMBERS_ALIKE) -- under the class match's
 *  0.6, because on the tech library the rightful words sit at 0.51-0.58
 *  (industrial robot ~ robotics 0.57, falcon 9 rocket ~ rocket launch 0.58,
 *  graphics card ~ computer hardware 0.57) and the learned misses at
 *  0.15-0.44 (cable reels ~ cooling 0.27, robotic arm ~ server racks 0.35,
 *  smart speaker ~ electronics component 0.33). Judged before the settle,
 *  so a word a folder cannot keep is not the one its rivals cede to.
 *  Mutations: the likeness test removed -> 34, 34a, 34b red; the members'
 *  floor made the class match's 0.6 -> 34 red (graphics card gone).
 */
echo "\n== a member word only where alike to the folder's own (S16)\n";

// Base words the suite has not asked a vector for yet (the phrase cache keeps a miss for the run): pc hardware, electronic part, cooling plant.
$GLOBALS['f_vec'] = array( 'graphics card' => array( 0.55, 0.835 ), 'pc hardware' => array( 1, 0 ), 'cable reel' => array( 1, 0 ), 'cooling plant' => array( 0.45, 0.893 ), 'smart loudspeaker' => array( 0, 1 ), 'electronic part' => array( 0.75, 0.661 ) );
$base = array(
    41 => f_profile( 41, 0, array( 'Data centres', 'Cooling' ), array( 'cooling plant', 'chiller', 'cooling' ) ),
    42 => f_profile( 42, 0, array( 'Hardware' ), array( 'pc hardware', 'hardware' ) ),
    43 => f_profile( 43, 0, array( 'Energy', 'Batteries' ), array( 'battery', 'batteries' ) ),
    44 => f_profile( 44, 0, array( 'Hardware', 'Components' ), array( 'electronic part', 'components' ) ),
);
$alike = vergeml_filing_members_alike( array(
    41 => array( 'n' => 5, 'classes' => array( 'cable reel', 'chiller' ), 'words' => array( 'cable reel' => 3, 'chiller' => 2 ), 'halves' => array(), 'vector' => null, 'built_at' => 1 ),
    42 => array( 'n' => 30, 'classes' => array( 'graphics card', 'smart loudspeaker' ), 'words' => array( 'graphics card' => 19, 'smart loudspeaker' => 8 ), 'halves' => array(), 'vector' => null, 'built_at' => 1 ),
    43 => array( 'n' => 9, 'classes' => array( 'lithium-ion battery' ), 'words' => array( 'lithium-ion battery' => 9 ), 'halves' => array(), 'vector' => null, 'built_at' => 1 ),
    44 => array( 'n' => 3, 'classes' => array( 'smart loudspeaker' ), 'words' => array( 'smart loudspeaker' => 3 ), 'halves' => array(), 'vector' => null, 'built_at' => 1 ),
), $base );
f_check( '34 Cooling keeps chiller (its own word) and not cable reel (0.45 to cooling plant, under the members\' floor of 0.5); Hardware keeps graphics card (0.55 to pc hardware: under the class match\'s 0.6, over the members\' 0.5) and not smart loudspeaker; Batteries keeps lithium-ion battery (battery inside it)', array( 'chiller' ) === $alike[41]['classes'] && array( 'chiller' => 2 ) === $alike[41]['words'] && array( 'graphics card' ) === $alike[42]['classes'] && array( 'lithium-ion battery' ) === $alike[43]['classes'], json_encode( array( $alike[41]['classes'], isset( $alike[42] ) ? $alike[42]['classes'] : null, $alike[43]['classes'] ) ) );
$gone = vergeml_filing_members_alike( array( 41 => array( 'n' => 5, 'classes' => array( 'cable reel' ), 'words' => array( 'cable reel' => 5 ), 'halves' => array(), 'vector' => null, 'built_at' => 1 ) ), $base );
f_check( '34a a layer left with no word alike is no layer: Cooling with only cable reels keeps the plan\'s profile', ! isset( $gone[41] ), json_encode( $gone ) );
$settled_alike = vergeml_filing_members_settle( $alike );
f_check( '34b Components keeps smart loudspeaker (0.66 to electronic part, over the floor) with Hardware\'s eight out before the settle: the word is Components\' alone, not ceded to the folder that cannot keep it', array( 'smart loudspeaker' ) === $alike[44]['classes'] && ! isset( $alike[42]['words']['smart loudspeaker'] ) && array( 'smart loudspeaker' ) === $settled_alike[44]['classes'] && array() === $settled_alike[44]['ceded'], json_encode( array( $alike[44]['classes'], isset( $settled_alike[44] ) ? $settled_alike[44] : null ) ) );
$GLOBALS['f_vec'] = array();

/*
 *  A one-word folder class as the head noun of the class half (S16, Nathan's
 *  S15 verdict): "hallway; interior space" went to Space, "garage;
 *  commercial space" to Space three times, "griptape; skateboard component"
 *  to Components -- 5 of the sheet's 13 wrong likelies. The folder's word
 *  sitting inside the half was kept on purpose in S15's second story for
 *  the modifier ("launch" of "launch event" is what the picture is); as the
 *  head noun of a polysemous one-word class it misfires. So a one-word
 *  class that is the head noun of the picture's class half is no hit,
 *  unless it is the folder's first class (a name-only folder has nothing
 *  else) or the half is the word whole. Mutation: the head-noun test
 *  removed from the pick -> rows 35 and 35a red.
 */
echo "\n== a one-word class as the head noun of the class half (S16)\n";

$poly = vergeml_filing_settle_claims( array(
    65 => f_profile( 65, 0, array( 'Space' ), array( 'spacecraft', 'infrastructure', 'space' ), array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
    66 => f_profile( 66, 0, array( 'Hardware', 'Components' ), array( 'electronics component', 'semiconductor component', 'components' ), array( 'vector' => array( 0.0, 1.0, 0.0, 0.0 ) ) ),
    67 => f_profile( 67, 0, array( 'Venues' ), array( 'venues' ), array( 'source' => 'name', 'vector' => array( 0.0, 0.0, 1.0, 0.0 ) ) ),
    68 => f_profile( 68, 0, array( 'Space', 'Launches' ), array( 'rocket launch', 'launch' ), array( 'vector' => array( 0.0, 0.0, 0.0, 1.0 ) ) ),
) );
$p = vergeml_filing_pick( f_facts( 'hallway; interior space', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ), $poly );
f_check( '35 "hallway; interior space" whose vector is Space\'s own: "space" (rank 2) as the head noun of the half is no hit -- nothing, floor (Space, sure 0.76, before)', 'nothing' === $p['outcome'] && 'floor' === $p['why'] && $p['scores'][65] < 0.3, sprintf( '%s why %s Space @%.4f', $p['outcome'], $p['why'], $p['scores'][65] ) );
$p = vergeml_filing_pick( f_facts( 'griptape; skateboard component', array( 'vector' => array( 0.0, 1.0, 0.0, 0.0 ) ) ), $poly );
f_check( '35a "griptape; skateboard component": "components" as the head noun is no hit either -- nothing, floor', 'nothing' === $p['outcome'] && 'floor' === $p['why'] && $p['scores'][66] < 0.3, sprintf( '%s why %s Components @%.4f', $p['outcome'], $p['why'], $p['scores'][66] ) );
$p = vergeml_filing_pick( f_facts( 'satellite; space', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ), $poly );
f_check( '35b the half as the word whole stands: "satellite; space" is Space, sure', 'fits' === $p['outcome'] && 65 === $p['term_id'] && 'sure' === $p['confidence'], sprintf( '%s %d @%.4f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );
$p = vergeml_filing_pick( f_facts( 'hall; conference venue', array( 'vector' => array( 0.0, 0.0, 1.0, 0.0 ) ) ), $poly );
f_check( '35c a name-only folder keeps the hit, its one word is its first class: "hall; conference venue" whose vector is Venues\' own fits Venues (venue, rank 0), sure', 'fits' === $p['outcome'] && 67 === $p['term_id'] && 'sure' === $p['confidence'], sprintf( '%s %d @%.4f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );
$p = vergeml_filing_pick( f_facts( 'soyuz; launch event', array( 'vector' => array( 0.0, 0.0, 0.0, 1.0 ) ) ), $poly );
f_check( '35d the modifier direction stands, as S15 left it: "soyuz; launch event" is Launches, sure', 'fits' === $p['outcome'] && 68 === $p['term_id'] && 'sure' === $p['confidence'], sprintf( '%s %d @%.4f %s', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'] ) );

/*
 *  A folder's name claims the planner classes it names (S16, the truth
 *  score's second and third leaves, and the card's profile finding). The
 *  planner put "camera lens" first on TV & Video beside a leaf named Lenses
 *  (11 of 12 lens pictures to a margin or TV & Video), "public bookcase" on
 *  Shelving beside Bookcases (11 of 11 to a margin), and "people" first on
 *  Interviews beside People ("figure; person" an Interviews sure). A planner
 *  class whose whole is another folder's leaf name, or whose head noun is
 *  another folder's one-word leaf name, is that folder's: it comes off the
 *  planner's folder. A member word stays (it passed the likeness test), and
 *  so does the folder's own leaf. Pure, in vergeml_filing_profiles before
 *  the claims are settled. Dry on the shop: right 333 -> 347 of 581, no
 *  right lost; on the tech library 6 move, every one to People or
 *  Conference talks. Mutation: the pass removed -> rows 36 and 36a red.
 */
echo "\n== a folder's name claims the planner classes it names (S16)\n";

$named = vergeml_filing_name_claims( array(
    71 => f_profile( 71, 0, array( 'People' ), array( 'people' ), array( 'source' => 'name' ) ),
    72 => f_profile( 72, 71, array( 'People', 'Interviews' ), array( 'people', 'interview', 'interviews' ) ),
    73 => f_profile( 73, 0, array( 'Electronics', 'TV & Video' ), array( 'camera lens', 'television', 'tv & video' ), array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
    74 => f_profile( 74, 0, array( 'Electronics', 'Cameras', 'Lenses' ), array( 'lenses' ), array( 'source' => 'name', 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ),
    75 => f_profile( 75, 0, array( 'Home', 'Storage', 'Shelving' ), array( 'public bookcase', 'shelving' ), array( 'source' => 'members', 'base_source' => 'plan', 'words' => array( 'public bookcase' => 4 ) ) ),
    76 => f_profile( 76, 0, array( 'Home', 'Furniture', 'Bookcases' ), array( 'bookcases' ), array( 'source' => 'name' ) ),
) );
f_check( '36 Interviews loses "people" (People\'s name whole), TV & Video loses "camera lens" (its head noun is Lenses\' name); Shelving keeps "public bookcase" -- its members taught it; People, Lenses and Bookcases keep their own', array( 'interview', 'interviews' ) === $named[72]['classes'] && array( 'television', 'tv & video' ) === $named[73]['classes'] && array( 'public bookcase', 'shelving' ) === $named[75]['classes'] && array( 'people' ) === $named[71]['classes'] && array( 'lenses' ) === $named[74]['classes'], json_encode( array( $named[72]['classes'], $named[73]['classes'], $named[75]['classes'] ) ) );
$p = vergeml_filing_pick( f_facts( 'camera lens; photography equipment', array( 'vector' => array( 1.0, 0.0, 0.0, 0.0 ) ) ), vergeml_filing_settle_claims( $named ) );
f_check( '36a "camera lens; photography equipment" is Lenses, sure, clear of TV & Video (a margin between them before)', 'fits' === $p['outcome'] && 74 === $p['term_id'] && 'sure' === $p['confidence'] && $p['score'] - $p['runner_score'] >= VERGEML_FILING_MARGIN, sprintf( '%s %d @%.4f %s next %d @%.4f', $p['outcome'], $p['term_id'], $p['score'], $p['confidence'], $p['runner_up'], $p['runner_score'] ) );

printf( "\n%d/%d passed\n", $GLOBALS['f_pass'], $GLOBALS['f_pass'] + $GLOBALS['f_fail'] );
exit( $GLOBALS['f_fail'] > 0 ? 1 : 0 );
