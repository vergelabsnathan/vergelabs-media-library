<?php
/*
 *  The large-library fold (spec-tree-planner story 7): rare labels folded
 *  into their class's fold label until the inventory fits
 *  VERGEML_PLAN_FOLD_BUDGET, so no library is ever refused for its size.
 *
 *  Local: no WordPress, no database, no box. vergeml_plan_fold(),
 *  vergeml_plan_fold_label() and vergeml_plan_effective_label() are
 *  arithmetic and string-matching on a label table this file builds; the
 *  cache-version row stands in for $wpdb and the options table with the
 *  smallest fakes that answer what vergeml_plan_inventory() asks of them.
 *
 *      node tools/verify.mjs plan-fold
 *
 *  The mutations it catches, in the rows' own running order: an untouched
 *  set gets folded anyway -> row 1; folding stops counting distinct fold
 *  labels toward the budget -> row 2; a fold-label collision drops a count
 *  instead of summing it -> row 3; the kind suffix dropped from the fold
 *  label, merging a photo and a screenshot of the same class -> row 4; the
 *  drop phase removes a kept label instead of the rarest fold label -> row
 *  5; the fold-label fallback stops firing, or fires before the exact
 *  label -> row 6/6b; a map with neither returns something -> row 7; the
 *  near-copy match stops resolving a folded picture through its fold label
 *  -> row 8/8b; the cache key loses its version suffix -> row 9/9b; a
 *  refresh already booked is not honoured, or the forced read settles for
 *  it too -> row 10/10b; a fold label that collides with a kept label's own
 *  text is dropped instead of merged -> row 11; the partial-count cache
 *  is not served while its refresh is booked, or never expires once it
 *  isn't -> row 12/12b; vergeml_plan_draft's paged read of the rule rows
 *  stops after the first page -> row 13; vergeml_plan_inventory() itself
 *  stops folding, or drops a picture instead of counting it kept, folded or
 *  left out, on a real over-budget read -> row 14.
 */

define( 'ABSPATH', '/' );
if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

function add_action() {}
function wp_next_scheduled( $hook ) { return false; }
function wp_schedule_single_event( $ts, $hook ) { return true; }
function spawn_cron() {}
function sanitize_key( $key ) {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function wp_json_encode( $v ) {
    return json_encode( $v );
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
// The fold rows below carry no audience text; only row 3 sets one, by hand, on the fixture itself.
function vergeml_filing_audience_of_picture( $audience ) {
    return in_array( $audience, array( 'men', 'women', 'kids' ), true ) ? $audience : '';
}

$GLOBALS['pf_options'] = array();
function get_option( $name, $default = false ) {
    return isset( $GLOBALS['pf_options'][ $name ] ) ? $GLOBALS['pf_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
    $GLOBALS['pf_options'][ $name ] = $value;
    return true;
}
$GLOBALS['pf_transients'] = array();
function get_transient( $name ) {
    return isset( $GLOBALS['pf_transients'][ $name ] ) ? $GLOBALS['pf_transients'][ $name ] : false;
}

require dirname( __DIR__, 2 ) . '/core/plan-tree.php';

$GLOBALS['pf_pass'] = 0;
$GLOBALS['pf_fail'] = 0;

function pf_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['pf_pass']++;
    } else {
        $GLOBALS['pf_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note );
}
function pf_json( $v ) {
    return json_encode( $v );
}

/**
 *  A fixture entry shaped exactly as vergeml_plan_inventory() builds one:
 *  $object is the describer's raw "specific; class" text (never a [kind]
 *  suffix -- that comes from $kind, as vergeml_plan_label_of adds it).
 */
function pf_entry( $object, $count, $kind = 'photo', $audience = array() ) {
    $classes = vergeml_filing_classes_of_object( $object );
    $k       = sanitize_key( (string) $kind );
    $k       = '' === $k ? 'photo' : $k;
    $label   = $classes[0] . ( isset( $classes[1] ) ? '; ' . $classes[1] : '' ) . ( 'photo' === $k ? '' : ' [' . $k . ']' );
    return array(
        'label'      => $label,
        'class'      => isset( $classes[0] ) ? $classes[0] : $label,
        'kind'       => $k,
        'count'      => $count,
        'audience'   => $audience,
        'fold'       => vergeml_plan_fold_label( $kind, array( 'object' => $object ) ),
        'fold_class' => isset( $classes[1] ) ? $classes[1] : ( isset( $classes[0] ) ? $classes[0] : $label ),
    );
}

/*
 *  Row 1. Under the budget: untouched, whatever the counts.
 */
$e1a = pf_entry( 'ankle boot; footwear', 4 );
$e1b = pf_entry( 'kettle; kitchenware', 1 );
$by1 = array( $e1a['label'] => $e1a, $e1b['label'] => $e1b );
list( $r1, $r1_folded, $r1_left ) = vergeml_plan_fold( $by1, 5 );
pf_check( '1. under the budget: the set comes back untouched', $r1 === $by1 && 0 === $r1_folded && 0 === $r1_left, pf_json( $r1 ) );

/*
 *  Row 2. Over the budget: at most the budget's labels, and every picture
 *  counted before is counted after -- folding never drops a picture, only
 *  the label that named it.
 */
$by2 = array();
for ( $i = 0; $i < 6; $i++ ) {
    $e = pf_entry( sprintf( 'shoe style %d; footwear', $i ), $i + 1 ); // counts 1..6, 21 pictures in all
    $by2[ $e['label'] ] = $e;
}
list( $r2, $r2_folded, $r2_left ) = vergeml_plan_fold( $by2, 3 );
$sum_before = array_sum( array_column( $by2, 'count' ) );
$sum_after  = array_sum( array_column( $r2, 'count' ) ) + $r2_left;
pf_check( '2. over the budget: at most the budget survives', count( $r2 ) <= 3, (string) count( $r2 ) );
pf_check( '2b. every picture counted before is counted after (kept, folded, or left out)', $sum_before === $sum_after, "$sum_before vs $sum_after" );
pf_check( '2c. some labels actually folded', $r2_folded > 0, (string) $r2_folded );

/*
 *  Row 3. A fold-label collision: two rare labels of the same class fold
 *  onto the same fold label, their counts and audience summed.
 */
$fold_footwear = vergeml_plan_fold_label( 'photo', array( 'object' => 'x; footwear' ) );
$e3a = pf_entry( 'common shoe; footwear', 100 );
$e3b = pf_entry( 'sandal; footwear', 3, 'photo', array( 'women' => 2, 'men' => 1 ) );
$e3c = pf_entry( 'clog; footwear', 2, 'photo', array( 'women' => 1 ) );
$by3 = array( $e3a['label'] => $e3a, $e3b['label'] => $e3b, $e3c['label'] => $e3c );
list( $r3 ) = vergeml_plan_fold( $by3, 2 );
pf_check(
    '3. a fold-label collision merges into one entry',
    isset( $r3[ $fold_footwear ] ) && 5 === $r3[ $fold_footwear ]['count'],
    pf_json( $r3 )
);
pf_check(
    '3b. its audience is summed across the labels that folded into it',
    isset( $r3[ $fold_footwear ] ) && array( 'women' => 3, 'men' => 1 ) === $r3[ $fold_footwear ]['audience'],
    pf_json( isset( $r3[ $fold_footwear ] ) ? $r3[ $fold_footwear ]['audience'] : null )
);

/*
 *  Row 4. The kind is kept: a screenshot and a photo of the same class do
 *  not share a fold label, so folding three labels of one class down to a
 *  budget of two still leaves two distinct entries, not one that lost
 *  which kind its pictures are.
 */
$fold_photo      = vergeml_plan_fold_label( 'photo', array( 'object' => 'x; diagram' ) );
$fold_screenshot = vergeml_plan_fold_label( 'screenshot', array( 'object' => 'x; diagram' ) );
$e4a = pf_entry( 'render; diagram', 100, 'photo' );
$e4b = pf_entry( 'hand sketch; diagram', 1, 'photo' );
$e4c = pf_entry( 'sales chart; diagram', 1, 'screenshot' );
$by4 = array( $e4a['label'] => $e4a, $e4b['label'] => $e4b, $e4c['label'] => $e4c );
list( $r4 ) = vergeml_plan_fold( $by4, 2 );
pf_check(
    '4. the kind is kept: a photo and a screenshot of the same class fold to different labels',
    $fold_photo !== $fold_screenshot && isset( $r4[ $fold_photo ] ) && isset( $r4[ $fold_screenshot ] ),
    pf_json( array_keys( $r4 ) )
);
pf_check( '4b. each fold label still says its own kind', false !== strpos( $fold_screenshot, '[screenshot]' ) && false === strpos( $fold_photo, '[' ), $fold_photo . ' / ' . $fold_screenshot );

/*
 *  Row 5. Fold labels over the budget: three classes, budget of two --
 *  folding alone cannot fit, so the rarest fold label (kitchenware, four
 *  pictures) is dropped and counted; the two larger ones (footwear,
 *  electronics) stay in the map.
 */
$by5 = array();
$plan = array(
    'footwear'    => array( 5, 4, 3 ),   // 12 pictures
    'electronics' => array( 6, 2 ),      // 8 pictures
    'kitchenware' => array( 4 ),         // 4 pictures
);
foreach ( $plan as $class => $counts ) {
    foreach ( $counts as $i => $c ) {
        $e = pf_entry( sprintf( 'item %d; %s', $i, $class ), $c );
        $by5[ $e['label'] ] = $e;
    }
}
list( $r5, $r5_folded, $r5_left ) = vergeml_plan_fold( $by5, 2 );
$fold_kitchen = vergeml_plan_fold_label( 'photo', array( 'object' => 'x; kitchenware' ) );
$fold_shoe    = vergeml_plan_fold_label( 'photo', array( 'object' => 'x; footwear' ) );
$fold_elec    = vergeml_plan_fold_label( 'photo', array( 'object' => 'x; electronics' ) );
pf_check( '5. more classes than the budget: at most the budget survives', count( $r5 ) <= 2, (string) count( $r5 ) );
pf_check( '5b. the rarest class is left out, not one of the two larger ones', ! isset( $r5[ $fold_kitchen ] ) && isset( $r5[ $fold_shoe ] ) && isset( $r5[ $fold_elec ] ), pf_json( array_keys( $r5 ) ) );
pf_check( '5c. its pictures are counted as left out', 4 === $r5_left, (string) $r5_left );

/*
 *  Row 6. vergeml_plan_effective_label(): a folded picture resolves through
 *  its fold label when the map holds only that; an exact label in the map
 *  wins over the fold label when both are there; a label in neither
 *  returns ''.
 */
$filing_shoe = array( 'object' => 'derby shoe; footwear' );
$exact_shoe  = vergeml_plan_label_of( 'photo', $filing_shoe );
$fold_shoe6  = vergeml_plan_fold_label( 'photo', $filing_shoe );

$known_fold_only = array( $fold_shoe6 => 'f1' );
pf_check( '6. a folded picture resolves through its fold label', $fold_shoe6 === vergeml_plan_effective_label( 'photo', $filing_shoe, $known_fold_only ), vergeml_plan_effective_label( 'photo', $filing_shoe, $known_fold_only ) );

$known_both = array( $exact_shoe => 'l1', $fold_shoe6 => 'f1' );
pf_check( '6b. an exact label in the map beats its own fold label', $exact_shoe === vergeml_plan_effective_label( 'photo', $filing_shoe, $known_both ), vergeml_plan_effective_label( 'photo', $filing_shoe, $known_both ) );

pf_check( '7. an unfolded map with a missing label returns nothing', '' === vergeml_plan_effective_label( 'photo', $filing_shoe, array() ), vergeml_plan_effective_label( 'photo', $filing_shoe, array() ) );

/*
 *  Row 8. The draft's near-copy match counts a folded picture under its
 *  fold label: ten pictures share the folded label "various footwear;
 *  footwear"; the underlying rows carry their own specific labels the map
 *  never mentions, but most of them already sit in one existing folder
 *  (term 50, named nothing like the fold label). That existing folder
 *  wins, exactly as an unfolded label's near-copy would (spec-tree-planner
 *  story 4).
 */
function vergeml_librarian_taxonomy() { return 'media_category'; }
function get_terms( $args ) { return $GLOBALS['pf_terms']; }
// A real page: rows already past $after, at most $limit of them -- so a fixture bigger than one page actually forces vergeml_plan_draft's do-while to come back for the rest.
function vergeml_guide_rule_rows( $taxonomy, $scope, $need, $after = 0, $limit = 0 ) {
    $out = array();
    foreach ( $GLOBALS['pf_rule_rows'] as $r ) {
        if ( isset( $r['attachment_id'] ) && (int) $r['attachment_id'] <= $after ) {
            continue;
        }
        $out[] = $r;
        if ( $limit > 0 && count( $out ) >= $limit ) {
            break;
        }
    }
    return $out;
}
function wp_specialchars_decode( $t, $q = 0 ) { return html_entity_decode( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function vergeml_term_name( $term ) { return wp_specialchars_decode( (string) $term->name, ENT_QUOTES ); }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function vergeml_guide_clean_draft( $draft ) { return $draft; }

$pf_fold_label = vergeml_plan_fold_label( 'photo', array( 'object' => 'x; footwear' ) );
$GLOBALS['pf_terms']     = array( (object) array( 'term_id' => 50, 'name' => 'Shoes', 'parent' => 0 ) );
$GLOBALS['pf_rule_rows'] = array();
foreach ( array_merge(
    array_fill( 0, 6, 'platform sneaker; footwear' ),
    array_fill( 0, 2, 'ankle boot; footwear' )
) as $i => $object ) {
    $GLOBALS['pf_rule_rows'][] = array( 'attachment_id' => $i + 1, 'kind' => 'photo', 'filing' => pf_json( array( 'object' => $object ) ), 'in_terms' => '50' );
}
$pf_draft = vergeml_plan_draft(
    array( array( 'path' => 'X', 'name' => 'Footwear Extras', 'parent' => '', 'labels' => array( 'f1' ) ) ),
    array( array( 'id' => 'f1', 'label' => $pf_fold_label, 'class' => 'footwear', 'kind' => 'photo', 'count' => 10 ) )
);
pf_check(
    '8. the draft near-copy match counts pictures folded under the fold label',
    1 === count( $pf_draft['folders'] ) && 50 === $pf_draft['folders'][0]['term_id'] && 'Shoes' === $pf_draft['folders'][0]['name'],
    pf_json( $pf_draft['folders'] )
);
pf_check(
    '8b. the label map keys the fold label itself, not the pictures\' own labels',
    isset( $pf_draft['label_map'][ $pf_fold_label ] ) && $pf_draft['label_map'][ $pf_fold_label ] === $pf_draft['folders'][0]['key'],
    pf_json( isset( $pf_draft['label_map'] ) ? $pf_draft['label_map'] : null )
);

/*
 *  Row 9. A cache built before the version-and-budget suffix was added to
 *  the key (or with a different budget) is not read back as this one's:
 *  vergeml_plan_inventory() recomputes rather than serving it.
 */
class PfWpdb {
    public $vergeml_ai_index = 'wp_vergeml_ai_index';
    public $rows             = array();
    public $stamp            = array( 'n' => 0, 'at' => '2026-09-28 00:00:00' );

    public function get_row( $sql, $output = ARRAY_A ) {
        return $this->stamp;
    }
    public function prepare( $sql, ...$args ) {
        foreach ( $args as $a ) {
            $sql = preg_replace( '/%d/', (string) (int) $a, $sql, 1 );
        }
        return $sql;
    }
    public function get_results( $sql, $output = ARRAY_A ) {
        $after = 0;
        if ( preg_match( '/attachment_id > (\d+)/', $sql, $m ) ) {
            $after = (int) $m[1];
        }
        $limit = 500;
        if ( preg_match( '/LIMIT (\d+)/', $sql, $m2 ) ) {
            $limit = (int) $m2[1];
        }
        $out = array();
        foreach ( $this->rows as $r ) {
            if ( (int) $r['attachment_id'] > $after ) {
                $out[] = $r;
                if ( count( $out ) >= $limit ) {
                    break;
                }
            }
        }
        return $out;
    }
}

global $wpdb;
$wpdb       = new PfWpdb();
$wpdb->rows = array(
    array( 'attachment_id' => 1, 'kind' => 'photo', 'filing' => pf_json( array( 'object' => 'ankle boot; footwear' ) ) ),
    array( 'attachment_id' => 2, 'kind' => 'photo', 'filing' => pf_json( array( 'object' => 'ankle boot; footwear' ) ) ),
    array( 'attachment_id' => 3, 'kind' => 'photo', 'filing' => pf_json( array( 'object' => 'kettle; kitchenware' ) ) ),
);
$wpdb->stamp = array( 'n' => 3, 'at' => '2026-09-28 00:00:00' );

// A stale, pre-version cache: the key an old build would have computed (no version/budget suffix), holding an inventory this run never produced.
$stale_key = md5( wp_json_encode( $wpdb->stamp ) );
update_option( VERGEML_PLAN_CACHE, array( 'key' => $stale_key, 'inventory' => array( 'labels' => array( array( 'id' => 'stale', 'label' => 'stale; stale', 'class' => 'stale', 'kind' => 'photo', 'count' => 999, 'audience' => array() ) ), 'pictures' => 999, 'unlabelled' => 0, 'folded_labels' => 0, 'left_out_pictures' => 0 ) ) );

$inv9 = vergeml_plan_inventory();
pf_check( '9. a pre-version cache is recomputed rather than served', 2 === count( $inv9['labels'] ) && 3 === $inv9['pictures'], pf_json( $inv9 ) );

$held9 = get_option( VERGEML_PLAN_CACHE );
$inv9b = vergeml_plan_inventory();
pf_check( '9b. the freshly computed inventory is itself cached and served on the next read', $held9['inventory'] === $inv9b, pf_json( $inv9b ) );

// Row 10. A refresh already booked: a changed library is not rescanned by the page, the held inventory is served; the plan's forced read still rescans.
$wpdb->rows[] = array( 'attachment_id' => 4, 'kind' => 'photo', 'filing' => pf_json( array( 'object' => 'teapot; kitchenware' ) ) );
$wpdb->stamp  = array( 'n' => 4, 'at' => '2026-09-28 00:01:00' );
$GLOBALS['pf_transients'][ VERGEML_PLAN_REFRESH_HOOK ] = 1;
pf_check( '10. while a refresh is booked the page gets the held inventory', $held9['inventory'] === vergeml_plan_inventory(), '' );
pf_check( '10b. the forced read still counts the library through', 4 === vergeml_plan_inventory( true )['pictures'], '' );
unset( $GLOBALS['pf_transients'][ VERGEML_PLAN_REFRESH_HOOK ] );

// Row 11. A fold label that is also a kept label's own text takes that label in, counts and audience summed.
$e11k = pf_entry( 'various footwear; footwear', 50, 'photo', array( 'men' => 2 ) );
$e11a = pf_entry( 'sandal; footwear', 1, 'photo', array( 'men' => 1 ) );
$e11b = pf_entry( 'clog; footwear', 1 );
$e11c = pf_entry( 'kettle; kitchenware', 40 );
list( $r11 ) = vergeml_plan_fold( array( $e11k['label'] => $e11k, $e11a['label'] => $e11a, $e11b['label'] => $e11b, $e11c['label'] => $e11c ), 2 );
pf_check(
    '11. a fold label equal to a kept label merges with it',
    2 === count( $r11 ) && isset( $r11['various footwear; footwear'] ) && 52 === $r11['various footwear; footwear']['count'] && array( 'men' => 3 ) === $r11['various footwear; footwear']['audience'],
    pf_json( $r11 )
);

// Row 12. A partial count (the page timed out with nothing cached) is served while its refresh is booked, and never matches a real key.
update_option( VERGEML_PLAN_CACHE, array( 'key' => 'partial', 'inventory' => array( 'labels' => array(), 'pictures' => 2, 'unlabelled' => 0, 'folded_labels' => 0, 'left_out_pictures' => 0 ) ) );
$GLOBALS['pf_transients'][ VERGEML_PLAN_REFRESH_HOOK ] = 1;
pf_check( '12. while a refresh is booked the partial count is served', 2 === vergeml_plan_inventory()['pictures'], '' );
unset( $GLOBALS['pf_transients'][ VERGEML_PLAN_REFRESH_HOOK ] );
pf_check( '12b. once the refresh is no longer booked, the partial count is recounted', 4 === vergeml_plan_inventory()['pictures'], '' );

/*
 *  Row 13. The near-copy match pages past the first 500 rule rows: 550
 *  rows in all, the first 500 padding (no term, just bulk so the first
 *  page comes back full and the loop asks for a second), the last 50
 *  carrying the label under test into an existing folder. The label's own
 *  count (80) only clears the majority floor once those 50 are counted, so
 *  a loop that never asks for page two -- the bug this suite was missing --
 *  would leave the folder new instead of the existing one.
 */
$GLOBALS['pf_rule_rows'] = array();
for ( $n = 1; $n <= 500; $n++ ) {
    $GLOBALS['pf_rule_rows'][] = array( 'attachment_id' => $n ); // padding: no in_terms, just bulk
}
for ( $n = 501; $n <= 550; $n++ ) {
    $GLOBALS['pf_rule_rows'][] = array( 'attachment_id' => $n, 'kind' => 'photo', 'filing' => pf_json( array( 'object' => 'sneaker; footwear' ) ), 'in_terms' => '70' );
}
$GLOBALS['pf_terms'] = array( (object) array( 'term_id' => 70, 'name' => 'Old Sneakers', 'parent' => 0 ) );
$pf_draft13 = vergeml_plan_draft(
    array( array( 'path' => 'Z', 'name' => 'New Sneakers', 'parent' => '', 'labels' => array( 's1' ) ) ),
    array( array( 'id' => 's1', 'label' => 'sneaker; footwear', 'class' => 'sneaker', 'kind' => 'photo', 'count' => 80 ) )
);
pf_check(
    '13. the near-copy match reads every page of 550 rule rows, not just the first 500, and the paged loop terminates',
    1 === count( $pf_draft13['folders'] ) && 70 === $pf_draft13['folders'][0]['term_id'] && 'Old Sneakers' === $pf_draft13['folders'][0]['name'],
    pf_json( $pf_draft13['folders'] )
);

/*
 *  Row 14. Over budget through vergeml_plan_inventory() itself, end to
 *  end: read, count, fold, cache. 100 classes of 20 labels each -- 2,000
 *  distinct labels, one picture apiece -- over the 1,500 budget only once
 *  folding actually runs.
 */
unset( $GLOBALS['pf_options'][ VERGEML_PLAN_CACHE ] );
$GLOBALS['pf_transients'] = array();
$wpdb->rows = array();
$n14        = 0;
for ( $c = 0; $c < 100; $c++ ) {
    for ( $s = 0; $s < 20; $s++ ) {
        $n14++;
        $wpdb->rows[] = array( 'attachment_id' => $n14, 'kind' => 'photo', 'filing' => pf_json( array( 'object' => sprintf( 'item %d; class %d', $s, $c ) ) ) );
    }
}
$wpdb->stamp = array( 'n' => $n14, 'at' => '2026-09-28 00:02:00' );

$inv14      = vergeml_plan_inventory( true );
$conserved  = array_sum( array_column( $inv14['labels'], 'count' ) ) + $inv14['left_out_pictures'];
pf_check( '14. an over-budget library reads, counts, folds and caches through vergeml_plan_inventory() itself: at most the budget survives', count( $inv14['labels'] ) <= 1500, (string) count( $inv14['labels'] ) );
pf_check( '14b. some of its 2,000 labels were actually folded away', $inv14['folded_labels'] > 0, (string) $inv14['folded_labels'] );
pf_check( '14c. every one of its 2,000 pictures is accounted for: kept + folded counts + left out', $n14 === $conserved, "$n14 vs $conserved" );

printf( "\n%d/%d passed\n", $GLOBALS['pf_pass'], $GLOBALS['pf_pass'] + $GLOBALS['pf_fail'] );

if ( $GLOBALS['pf_fail'] > 0 ) {
    exit( 1 );
}
