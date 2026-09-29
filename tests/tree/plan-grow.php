<?php
/*
 *  A frozen tree planned again, for free, and only grown (spec-tree-planner
 *  story 10, CAP-2).
 *
 *  Local: no WordPress, no database, no box. vergeml_plan_grow_decide() is
 *  arithmetic on three-dimensional vectors; the read, the draft and the
 *  fill's assignment run against the smallest stand-ins for WordPress and
 *  $wpdb that answer what they ask.
 *
 *      node tools/verify.mjs plan-grow
 *
 *  The mutations it catches: the relative rule dropped (a label as close as
 *  a sibling joins) -> row 2; grouping by likeness dropped -> row 3; the
 *  minimum dropped -> row 4; a full parent or the depth limit ignored ->
 *  rows 6, 7; the order of the input leaking into the answer -> row 9; a
 *  frozen label or a filed picture counted as new -> rows 11, 12; the draft
 *  moving, renaming or dropping an existing folder or a frozen label ->
 *  row 13; the fill's assignment touching a filed, hand-placed or frozen
 *  picture -> row 15.
 */

define( 'ABSPATH', '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'VERGEML_FILING_TO_SORT_SLUG', 'to-sort' );
define( 'VERGEML_FILING_PLACED_BY', '_vergeml_placed_by' );
define( 'VERGEML_FILING_LOCKED', '_vergeml_locked' );
define( 'VERGEML_TALK_LABEL_MAP', 'vergeml_talk_label_map' );

function add_action() {}
function sanitize_key( $key ) {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_text_field( $t ) {
    return trim( strip_tags( (string) $t ) );
}
function vergeml_filing_classes_of_object( $object ) {
    return array_values( array_filter( array_map( 'trim', preg_split( '/\s*[;,]\s*/u', mb_strtolower( trim( (string) $object ) ) ) ), 'strlen' ) );
}
function vergeml_term_name( $t ) {
    return html_entity_decode( (string) $t->name, ENT_QUOTES, 'UTF-8' );
}
function get_option( $name ) {
    return isset( $GLOBALS['gr_options'][ $name ] ) ? $GLOBALS['gr_options'][ $name ] : false;
}
function get_terms( $args ) {
    if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
        return array_values( array_intersect( array_map( function ( $t ) { return (int) $t->term_id; }, $GLOBALS['gr_terms'] ), (array) $args['include'] ) );
    }
    return $GLOBALS['gr_terms'];
}
function get_term_meta( $id, $key, $single ) {
    return ! empty( $GLOBALS['gr_locked'][ $id ] );
}
function vergeml_index_vector_out( $e ) {
    return json_decode( (string) $e, true );
}
// Stands in for core/guide.php's own: every described picture's kind, filing, folders and vector, in pages.
function vergeml_guide_rule_rows( $taxonomy, $scope, $need, $after = 0, $limit = 0 ) {
    $out = array();
    foreach ( $GLOBALS['gr_rows'] as $r ) {
        if ( $r['attachment_id'] > $after ) {
            $out[] = $r;
        }
    }
    return $limit ? array_slice( $out, 0, $limit ) : $out;
}
function vergeml_guide_clean_draft( $d ) {
    return $d;
}
class GR_Wpdb {
    public $vergeml_ai_index = 'wp_vergeml_ai_index';
    public $postmeta         = 'wp_postmeta';
    public function prepare( $q ) {
        return $q;
    }
    public function get_var( $q ) {
        return 3;
    }
    public function get_col( $q ) {
        return $GLOBALS['gr_placed'];
    }
}
$GLOBALS['wpdb'] = new GR_Wpdb();

require dirname( __DIR__, 2 ) . '/core/plan-tree.php';

$GLOBALS['gr_pass'] = 0;
$GLOBALS['gr_fail'] = 0;

function gr_check( $label, $ok, $note = '' ) {
    $GLOBALS[ $ok ? 'gr_pass' : 'gr_fail' ]++;
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note );
}

/** n pictures pointing along $v: their sum. */
function gr_sum( $v, $n ) {
    $u = vergeml_plan_unit( $v );
    return array_map( function ( $x ) use ( $n ) {
        return $x * $n;
    }, $u );
}
function gr_folder( $name, $parent, $v = null, $n = 0, $sub = null, $target = true ) {
    return array( 'name' => $name, 'parent' => $parent, 'direct' => $v ? gr_sum( $v, $n ) : null, 'n' => $n, 'sub' => $sub, 'target' => $target );
}
function gr_label( $v, $n, $object, $class, $kind = 'photo' ) {
    return array( 'sum' => gr_sum( $v, $n ), 'n' => $n, 'object' => $object, 'class' => $class, 'kind' => $kind );
}

/*
 *  The frozen tree: Shoes (Boots, Sneakers), Bags, To sort. Every member of
 *  a folder points exactly its way, so a folder's own pictures sit at 1.
 */
$boots    = array( 1, 0, 0 );
$sneakers = array( 0.9, 0.44, 0 );
$bags     = array( 0, 0, 1 );
$folders  = array(
    10 => gr_folder( 'Shoes', 0, null, 0, vergeml_plan_add( gr_sum( $boots, 3 ), gr_sum( $sneakers, 3 ) ) ),
    11 => gr_folder( 'Boots', 10, $boots, 3 ),
    12 => gr_folder( 'Sneakers', 10, $sneakers, 3 ),
    20 => gr_folder( 'Bags', 0, $bags, 4 ),
    30 => gr_folder( 'To sort', 0, null, 0, null, false ),
);
$new = array(
    'ankle boot; footwear'      => gr_label( $boots, 2, 'ankle boot', 'footwear' ),
    'sandal; footwear'          => gr_label( array( 0.75, 0, 0.66 ), 2, 'sandal', 'footwear' ),
    'hiking sandal; footwear'   => gr_label( array( 0.72, 0.05, 0.69 ), 3, 'hiking sandal', 'footwear' ),
    'wallet; accessory'         => gr_label( array( -1, 0, 0 ), 1, 'wallet', 'accessory' ),
    'app screen; software [screenshot]' => gr_label( array( 0, 0.7, 0.7 ), 5, 'app screen', 'software', 'screenshot' ),
    'tote; bags'                => gr_label( array( 0, 0.6, 0.8 ), 5, 'tote', 'bags' ),
);
$d = vergeml_plan_grow_decide( $new, $folders );
$by_name = array();
foreach ( $d['grow'] as $g ) {
    $by_name[ $g['name'] ] = $g;
}

gr_check( '1. a new label whose pictures sit as close as a folder\'s own joins that folder', isset( $d['place']['ankle boot; footwear'] ) && 11 === $d['place']['ankle boot; footwear'], json_encode( $d['place'] ) );
gr_check( '2. a label near a folder (0.75) but looser than its members is not put in it', ! isset( $d['place']['sandal; footwear'] ), json_encode( $d['place'] ) );
gr_check( '3. alike leftover labels group into one new folder, named for their class', isset( $by_name['Footwear'] ) && array( 'hiking sandal; footwear', 'sandal; footwear' ) === $by_name['Footwear']['labels'] && 5 === $by_name['Footwear']['n'], json_encode( $d['grow'] ) );
gr_check( '4. a group under five pictures is left for the matcher', in_array( 'wallet; accessory', $d['left'], true ) && ! isset( $d['place']['wallet; accessory'] ), json_encode( $d['left'] ) );
gr_check( '5. a new folder hangs under the parent whose subtree it sits nearest; another kind is its own folder at the top', 10 === $by_name['Footwear']['parent'] && isset( $by_name['Software screenshots'] ) && 0 === $by_name['Software screenshots']['parent'], json_encode( $d['grow'] ) );
gr_check( '5b. a group named like an existing folder joins it', isset( $d['place']['tote; bags'] ) && 20 === $d['place']['tote; bags'] && ! isset( $by_name['Bags'] ), json_encode( $d['place'] ) );

// A parent with twelve children takes no thirteenth: the group goes to the top instead.
$full = $folders;
for ( $i = 0; $i < 10; $i++ ) {
    $full[ 100 + $i ] = gr_folder( 'Kid ' . $i, 10 );
}
$df = vergeml_plan_grow_decide( $new, $full );
$fw = null;
foreach ( $df['grow'] as $g ) {
    $fw = 'Footwear' === $g['name'] ? $g : $fw;
}
gr_check( '6. a parent already holding twelve takes no more: the new folder goes to the top', $fw && 0 === $fw['parent'], json_encode( $df['grow'] ) );

// Three levels at most: a parent at the third level takes no child.
$deep = array(
    1 => gr_folder( 'A', 0, null, 0, gr_sum( array( 0.3, 0, 0.95 ), 4 ) ),
    2 => gr_folder( 'B', 1, null, 0, gr_sum( array( 0.7, 0, 0.71 ), 4 ) ),
    3 => gr_folder( 'C', 2, null, 0, gr_sum( array( 0.73, 0, 0.68 ), 4 ) ),
    4 => gr_folder( 'D', 3, array( 0, 1, 0 ), 4 ),
);
$dd = vergeml_plan_grow_decide( array( 'sandal; footwear' => $new['sandal; footwear'], 'hiking sandal; footwear' => $new['hiking sandal; footwear'] ), $deep );
gr_check( '7. a new folder never makes a fourth level', 1 === count( $dd['grow'] ) && 2 === $dd['grow'][0]['parent'], json_encode( $dd['grow'] ) );

gr_check( '8. a name is three words at most, the kind kept', 'Home decor screenshots' === vergeml_plan_grow_name( 'modern home decor', 'screenshot' ) && 'Footwear' === vergeml_plan_grow_name( 'footwear', 'photo' ), vergeml_plan_grow_name( 'modern home decor', 'screenshot' ) );

// The same library planned twice, handed over in any order, grows the same.
$shuffled_new = array_reverse( $new, true );
$shuffled_f   = array_reverse( $folders, true );
gr_check( '9. the same library planned twice gives the identical growth, whatever the order', json_encode( $d ) === json_encode( vergeml_plan_grow_decide( $shuffled_new, $shuffled_f ) ) );

/*
 *  The read, against a small site: Boots (11) under Shoes (10), To sort (30).
 *  Picture 1 filed in Boots; 2 waiting with a frozen label; 3 waiting with a
 *  new label; 4 in To sort with a new label; 5 hand-placed, waiting; 6 filed
 *  in Boots with a new label.
 */
$GLOBALS['gr_terms'] = array(
    (object) array( 'term_id' => 10, 'name' => 'Shoes', 'parent' => 0, 'slug' => 'shoes' ),
    (object) array( 'term_id' => 11, 'name' => 'Boots', 'parent' => 10, 'slug' => 'boots' ),
    (object) array( 'term_id' => 30, 'name' => 'To sort', 'parent' => 0, 'slug' => 'to-sort' ),
);
$row = function ( $id, $object, $terms, $v ) {
    return array( 'attachment_id' => $id, 'kind' => 'photo', 'filing' => json_encode( array( 'object' => $object ) ), 'in_terms' => $terms, 'embedding' => json_encode( $v ) );
};
$GLOBALS['gr_rows'] = array(
    $row( 1, 'chelsea boot; footwear', '11', array( 1, 0, 0 ) ),
    $row( 2, 'chelsea boot; footwear', '', array( 1, 0, 0 ) ),
    $row( 3, 'sandal; footwear', '', array( 0, 1, 0 ) ),
    $row( 4, 'sandal; footwear', '30', array( 0, 1, 0 ) ),
    $row( 5, 'sandal; footwear', '', array( 0, 1, 0 ) ),
    $row( 6, 'sandal; footwear', '11', array( 0, 0, 1 ) ),
);
$GLOBALS['gr_placed'] = array( 5 );
$frozen = array( 'chelsea boot; footwear' => 11 );
$read   = vergeml_plan_grow_read( $frozen, 'media_category' );
gr_check( '10. a waiting picture with a new label is new, To sort counts as waiting', isset( $read['new']['sandal; footwear'] ) && 2 === $read['new']['sandal; footwear']['n'] && 'footwear' === $read['new']['sandal; footwear']['class'], json_encode( $read['new'] ) );
gr_check( '11. a frozen label is never new, and a hand-placed picture never waits', ! isset( $read['new']['chelsea boot; footwear'] ), json_encode( array_keys( $read['new'] ) ) );
gr_check( '12. a filed picture shapes its folder and its parent\'s subtree, never the growth', 2 === $read['folders'][11]['n'] && array( 1, 0, 1 ) == $read['folders'][11]['direct'] && array( 1, 0, 1 ) == $read['folders'][10]['sub'] && false === $read['folders'][30]['target'], json_encode( $read['folders'][11] ) );

// The draft: every existing folder where it stands, the frozen map kept, the growth mapped to its new folder.
$decided = array( 'place' => array( 'ankle boot; footwear' => 11 ), 'grow' => array( array( 'name' => 'Footwear', 'parent' => 10, 'labels' => array( 'sandal; footwear' ), 'n' => 5, 'objects' => array( 'sandal' ), 'kinds' => array( 'photo' ) ) ), 'left' => array() );
$draft   = vergeml_plan_grow_draft( $decided, $frozen, 'media_category' );
$keys    = array_column( $draft['folders'], null, 'key' );
gr_check( '13. the grown draft keeps every folder, name and parent, and the frozen map as it was', 4 === count( $draft['folders'] ) && 'Boots' === $keys['t11']['name'] && 't10' === $keys['t11']['parent'] && ! empty( $keys['t11']['asked'] ) && 't11' === $draft['label_map']['chelsea boot; footwear'], json_encode( $draft ) );
gr_check( '14. the growth is new folders under their parents, its labels mapped to them, origin grow', 'Footwear' === $keys['g0']['name'] && null === $keys['g0']['term_id'] && 't10' === $keys['g0']['parent'] && 'g0' === $draft['label_map']['sandal; footwear'] && 't11' === $draft['label_map']['ankle boot; footwear'] && 'grow' === $draft['origin'], json_encode( $draft['label_map'] ) );

// The fill's assignment: only waiting pictures of the growth's labels.
$GLOBALS['gr_options'] = array( VERGEML_TALK_LABEL_MAP => $frozen );
$a = vergeml_plan_grow_assign( $draft, 'media_category' );
gr_check( '15. the fill files only waiting pictures of the growth -- not a filed, hand-placed or frozen one', array( 3 => 'g0', 4 => 'g0' ) === $a['assign'] && 4 === $a['waiting'], json_encode( $a ) );

// A map whose folders are all gone is no frozen tree: the site plans from scratch (and pays) again.
$GLOBALS['gr_options'] = array( VERGEML_TALK_LABEL_MAP => array( 'chelsea boot; footwear' => 11, 'sandal; footwear' => 647 ) );
$live_only = vergeml_plan_frozen();
$GLOBALS['gr_options'] = array( VERGEML_TALK_LABEL_MAP => array( 'sandal; footwear' => 647 ) );
gr_check( '15b. only entries whose folder still exists are frozen; none left, nothing is', array( 'chelsea boot; footwear' => 11 ) === $live_only && array() === vergeml_plan_frozen(), json_encode( $live_only ) );

// A second re-plan once the growth is filled: nothing new is left, so nothing is proposed.
$GLOBALS['gr_rows'][2]['in_terms'] = '99';
$GLOBALS['gr_rows'][3]['in_terms'] = '99';
$GLOBALS['gr_terms'][] = (object) array( 'term_id' => 99, 'name' => 'Footwear', 'parent' => 10, 'slug' => 'footwear' );
$again = vergeml_plan_grow_read( array( 'chelsea boot; footwear' => 11, 'sandal; footwear' => 99 ), 'media_category' );
$da    = vergeml_plan_grow_decide( $again['new'], $again['folders'] );
gr_check( '16. planned again after the growth is filled, the tree grows no further', ! $da['place'] && ! $da['grow'], json_encode( $da ) );

printf( "\n%d/%d passed\n", $GLOBALS['gr_pass'], $GLOBALS['gr_pass'] + $GLOBALS['gr_fail'] );
if ( $GLOBALS['gr_fail'] > 0 ) {
    exit( 1 );
}
