<?php
/*
 *  A frozen tree planned again and grown, through the routes the Folders
 *  screen calls (spec-tree-planner story 10). Free: every outbound request is
 *  refused in this script and counted, so nothing reaches the service.
 *
 *      node tools/box-eval.mjs tools/box-tree-snapshot.php --site shop --env VGML_MODE=snapshot --env VGML_FILE=/var/tmp/vgml-shop-grow.json
 *      node tools/box-eval.mjs tools/box-grow-proof.php --site shop
 *      node tools/box-eval.mjs tools/box-tree-score.php --site shop
 *      node tools/box-eval.mjs tools/box-grow-proof.php --site shop --env VGML_STEP=undo
 *      node tools/box-eval.mjs tools/box-tree-snapshot.php --site shop --env VGML_MODE=restore --env VGML_FILE=/var/tmp/vgml-shop-grow.json
 *
 *  WRITES: run only between a snapshot and its restore.
 *
 *  The site's folders stand in for an accepted plan: every label is frozen
 *  to the folder holding most of its pictures, except a quarter of the
 *  labels (crc32 % 4 == 0), whose pictures are taken out of every folder --
 *  pictures described after the freeze. Then: plan twice (the two drafts
 *  must be identical), confirm, fill, and check that every existing folder
 *  kept its name, its parent and every picture, that only new-label
 *  pictures moved, and that a third plan finds nothing new.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
wp_set_current_user( 1 );
$gp_tax = vergeml_librarian_taxonomy();

$GLOBALS['gp_out'] = array();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
    // The site's own loopback (spawn_cron) is not the service; only other hosts count.
    if ( wp_parse_url( (string) $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
        $GLOBALS['gp_out'][] = strtok( (string) $url, '?' );
    }
    return new WP_Error( 'blocked', 'blocked by box-grow-proof' );
}, 1, 3 );

function gp_call( $method, $route ) {
    $res = rest_do_request( new WP_REST_Request( $method, '/' . VERGEML_REST_NS . $route ) );
    return array( $res->get_status(), $res->get_data() );
}

function gp_check( $label, $ok, $note = '' ) {
    $GLOBALS[ $ok ? 'gp_pass' : 'gp_fail' ] = ( isset( $GLOBALS[ $ok ? 'gp_pass' : 'gp_fail' ] ) ? $GLOBALS[ $ok ? 'gp_pass' : 'gp_fail' ] : 0 ) + 1;
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note );
}

/** Every folder: name, parent, members -- literal SQL, not the code under test. */
function gp_tree( $tax ) {
    global $wpdb;
    $out = array();
    foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, tt.parent, tt.term_taxonomy_id FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s", $tax ), ARRAY_A ) as $r ) {
        $out[ (int) $r['term_id'] ] = array(
            'name'    => wp_specialchars_decode( $r['name'], ENT_QUOTES ),
            'parent'  => (int) $r['parent'],
            'members' => array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d ORDER BY object_id", (int) $r['term_taxonomy_id'] ) ) ),
        );
    }
    return $out;
}

function gp_plan() {
    list( $code, $out ) = gp_call( 'POST', '/guide/plan' );
    if ( 200 !== $code ) {
        return array( $code, $out );
    }
    wp_clear_scheduled_hook( VERGEML_PLAN_HOOK );
    vergeml_plan_event();
    return array( 200, vergeml_guide_session() );
}

if ( 'undo' === getenv( 'VGML_STEP' ) ) {
    $was = get_option( 'vergeml_grow_proof_frozen' );
    list( $code, $out ) = gp_call( 'POST', '/guide/undo' );
    gp_check( 'undo answers', 200 === $code, (string) $code );
    gp_check( 'undo puts the frozen map back as it was before the growth, not away', is_array( $was ) && get_option( VERGEML_TALK_LABEL_MAP ) === $was, count( (array) get_option( VERGEML_TALK_LABEL_MAP ) ) . ' labels' );
    $before = get_option( 'vergeml_grow_proof_tree' );
    $now    = gp_tree( $gp_tax );
    $same   = 0;
    foreach ( (array) $before as $tid => $f ) {
        $same += isset( $now[ $tid ] ) && $now[ $tid ]['members'] === $f['members'] && $now[ $tid ]['name'] === $f['name'] ? 1 : 0;
    }
    gp_check( 'undo puts every folder back as it was before the fill', count( (array) $before ) === $same && count( $now ) === count( (array) $before ), $same . ' of ' . count( (array) $before ) . ', ' . count( $now ) . ' folders now' );
    delete_option( 'vergeml_grow_proof_frozen' );
    delete_option( 'vergeml_grow_proof_tree' );
    printf( "outbound requests refused: %d %s\n", count( $GLOBALS['gp_out'] ), wp_json_encode( array_count_values( $GLOBALS['gp_out'] ) ) );
    return;
}

// A stale map (every folder it names gone) is overwritten; the snapshot holds it.
if ( vergeml_plan_frozen() ) {
    echo "a frozen map exists already: restore the snapshot first\n";
    return;
}

// ---- the freeze: every label to the folder holding most of its pictures; a quarter held out as new.
$gp_to_sort = get_term_by( 'slug', VERGEML_FILING_TO_SORT_SLUG, $gp_tax );
$gp_skip    = $gp_to_sort ? array( (int) $gp_to_sort->term_id => true ) : array();
$gp_par     = array();
foreach ( get_terms( array( 'taxonomy' => $gp_tax, 'hide_empty' => false ) ) as $t ) {
    $gp_par[ (int) $t->term_id ] = (int) $t->parent;
}
$gp_depth = vergeml_plan_grow_depths( $gp_par );
$gp_by    = array();
$gp_held  = array();
$gp_label = array();
foreach ( vergeml_guide_rule_rows( $gp_tax, 'all', array( 'filing', 'terms' ) ) as $r ) {
    $label = vergeml_plan_label_of( $r['kind'], json_decode( (string) $r['filing'], true ) );
    if ( '' === $label ) {
        continue;
    }
    $id               = (int) $r['attachment_id'];
    $gp_label[ $id ]  = $label;
    if ( 0 === crc32( $label ) % 4 ) {
        $gp_held[ $id ] = true;
        continue;
    }
    $now = vergeml_plan_grow_now( $r['in_terms'], $gp_depth, $gp_skip );
    if ( $now ) {
        $gp_by[ $label ][ $now ] = ( isset( $gp_by[ $label ][ $now ] ) ? $gp_by[ $label ][ $now ] : 0 ) + 1;
    }
}
$gp_frozen = array();
foreach ( $gp_by as $label => $by ) {
    arsort( $by );
    $gp_frozen[ $label ] = (int) key( $by );
}
foreach ( $gp_held as $id => $x ) {
    wp_set_object_terms( $id, array(), $gp_tax, false );
}
update_option( VERGEML_TALK_LABEL_MAP, $gp_frozen, false );
update_option( 'vergeml_grow_proof_frozen', $gp_frozen, false );
$s          = vergeml_guide_session();
$s['tree']  = 'confirmed';
$s['draft'] = null;
$s['plan']  = null;
vergeml_guide_save( $s );
$gp_before = gp_tree( $gp_tax );
update_option( 'vergeml_grow_proof_tree', $gp_before, false );
// Waiting now: in no folder but To sort -- the held-out pictures, and whatever already waited.
$gp_waiting = array();
foreach ( vergeml_guide_rule_rows( $gp_tax, 'all', array( 'terms' ) ) as $r ) {
    if ( ! vergeml_plan_grow_now( $r['in_terms'], $gp_depth, $gp_skip ) ) {
        $gp_waiting[ (int) $r['attachment_id'] ] = true;
    }
}
printf( "frozen: %d labels to %d folders; %d pictures of %d held-out labels taken out of their folders\n", count( $gp_frozen ), count( array_unique( $gp_frozen ) ), count( $gp_held ), count( array_unique( array_intersect_key( $gp_label, $gp_held ) ) ) );

// ---- plan twice.
$gp_facts = vergeml_plan_facts();
gp_check( 'the button\'s price on a frozen tree is 0', 0 === $gp_facts['price'], (string) $gp_facts['price'] );
$gp_t = microtime( true );
list( $code, $s1 ) = gp_plan();
gp_check( 'planning a confirmed, frozen tree is allowed (was 409)', 200 === $code, (string) $code . ( 200 !== $code ? ' ' . wp_json_encode( $s1 ) : '' ) );
if ( 200 !== $code ) {
    return;
}
printf( "plan 1: %.1f s, state %s, %d new folders, %d labels placed into existing, %d left\n", microtime( true ) - $gp_t, $s1['plan']['state'], $s1['plan']['folders'], $s1['plan']['placed'], $s1['plan']['left'] );
gp_check( 'nothing was charged and nothing left the site', 0 === $s1['plan']['charged'] && ! $GLOBALS['gp_out'], wp_json_encode( $GLOBALS['gp_out'] ) );
gp_check( 'the growth comes back to editing for the owner\'s yes', 'done' === $s1['plan']['state'] && 'editing' === $s1['tree'] && 'grow' === $s1['draft']['origin'] );

list( $code, $s2 ) = gp_plan();
gp_check( 'planned again, the identical tree and label map', 200 === $code && wp_json_encode( array( $s1['draft']['folders'], $s1['draft']['label_map'] ) ) === wp_json_encode( array( $s2['draft']['folders'], $s2['draft']['label_map'] ) ) );

$gp_keys  = array();
$gp_same  = 0;
$gp_grown = array();
foreach ( $s2['draft']['folders'] as $f ) {
    if ( $f['term_id'] ) {
        $b        = $gp_before[ (int) $f['term_id'] ];
        $gp_same += $b['name'] === $f['name'] && ( $b['parent'] ? 't' . $b['parent'] : '' ) === $f['parent'] ? 1 : 0;
    } else {
        $gp_grown[ $f['key'] ] = $f;
    }
}
gp_check( 'every existing folder is in the draft with its name and parent', count( $gp_before ) === $gp_same, $gp_same . ' of ' . count( $gp_before ) );
$gp_bad = 0;
foreach ( $gp_frozen as $label => $tid ) {
    $gp_bad += ! isset( $s2['draft']['label_map'][ $label ] ) || 't' . $tid !== $s2['draft']['label_map'][ $label ] ? 1 : 0;
}
gp_check( 'every frozen label keeps its folder', 0 === $gp_bad, $gp_bad . ' changed' );
// New: a label the frozen map does not hold -- held out here, or one whose every picture already waited when it froze.
$gp_heldl  = array_flip( array_intersect_key( $gp_label, $gp_held ) );
$gp_growth = array_diff_key( $s2['draft']['label_map'], $gp_frozen );
$gp_extra  = count( array_diff_key( $s2['draft']['label_map'], $gp_frozen, $gp_heldl ) );
gp_check( 'the growth maps only labels the frozen map does not hold', count( $s2['draft']['label_map'] ) === count( $gp_frozen ) + count( $gp_growth ), count( $gp_growth ) . ' new labels: ' . ( count( $gp_growth ) - $gp_extra ) . ' held out, ' . $gp_extra . ' that already waited' );
foreach ( $gp_grown as $key => $f ) {
    printf( "   new: %-30s under %-26s dry run %s\n", $f['name'], '' === $f['parent'] ? '(top)' : $gp_before[ (int) substr( $f['parent'], 1 ) ]['name'], null === $f['count'] ? '-' : (string) $f['count'] );
}
if ( is_array( $s2['fit'] ) ) {
    printf( "dry run: %s pictures move, %d stay waiting\n", var_export( $s2['fit']['move'], true ), (int) $s2['fit']['unfiled']['to_sort'] );
}

// ---- the owner's yes: confirm, fill.
$left = 1;
for ( $i = 0; $left > 0 && $i < 40; $i++ ) {
    list( $code, $out ) = gp_call( 'POST', '/guide/confirm' );
    if ( 200 !== $code ) {
        break;
    }
    $left = (int) $out['left'];
}
gp_check( 'the grown tree confirms, asking the planner nothing', 200 === $code && 0 === (int) $out['charged'] && ! $GLOBALS['gp_out'], $code . ' ' . wp_json_encode( $GLOBALS['gp_out'] ) );

$gp_t = microtime( true );
list( $code, $out ) = gp_call( 'POST', '/guide/apply' );
gp_check( 'the fill starts', 200 === $code, $code . ' ' . ( 200 !== $code ? wp_json_encode( $out ) : '' ) );
wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
$st = get_option( VERGEML_TALK_STATE );
for ( $i = 0; is_array( $st ) && ! empty( $st['active'] ) && $i < 200; $i++ ) {
    $st = vergeml_talk_refile_run( microtime( true ) + 60.0 );
}
$s = vergeml_guide_session();
vergeml_guide_progress_out( $s );
vergeml_guide_save( $s );
printf( "fill: %.1f s, %d moved\n", microtime( true ) - $gp_t, (int) $st['moved'] );

$gp_after = gp_tree( $gp_tax );
$gp_kept  = 0;
$gp_moved = array();
$gp_sort  = $gp_to_sort ? (int) $gp_to_sort->term_id : 0;
foreach ( $gp_before as $tid => $b ) {
    $a = isset( $gp_after[ $tid ] ) ? $gp_after[ $tid ] : null;
    if ( $tid === $gp_sort ) {
        // To sort is the waiting room: what the growth files leaves it, by design.
        printf( "To sort: %d before, %d after\n", count( $b['members'] ), $a ? count( $a['members'] ) : 0 );
        $gp_kept++;
        continue;
    }
    $gp_kept  += $a && $a['name'] === $b['name'] && $a['parent'] === $b['parent'] && ! array_diff( $b['members'], $a['members'] ) ? 1 : 0;
    foreach ( $a ? array_diff( $a['members'], $b['members'] ) : array() as $id ) {
        $gp_moved[ $id ] = true;
    }
}
foreach ( array_diff_key( $gp_after, $gp_before ) as $tid => $a ) {
    foreach ( $a['members'] as $id ) {
        $gp_moved[ $id ] = true;
    }
}
gp_check( 'every existing folder keeps its name, its parent and every picture', count( $gp_before ) === $gp_kept, $gp_kept . ' of ' . count( $gp_before ) );
gp_check( 'only waiting pictures moved', ! array_diff_key( $gp_moved, $gp_waiting ), count( $gp_moved ) . ' moved: ' . count( array_intersect_key( $gp_moved, $gp_held ) ) . ' held out, ' . count( array_diff_key( $gp_moved, $gp_held ) ) . ' that already waited, ' . count( array_diff_key( $gp_moved, $gp_waiting ) ) . ' from a folder' );
gp_check( 'the fill made the new folders and no others', count( $gp_after ) - count( $gp_before ) === count( $gp_grown ), ( count( $gp_after ) - count( $gp_before ) ) . ' made, ' . count( $gp_grown ) . ' proposed' );
$gp_map = (array) get_option( VERGEML_TALK_LABEL_MAP );
gp_check( 'the frozen map now holds the growth, every frozen label unchanged', ! array_diff_assoc( $gp_frozen, $gp_map ) && count( $gp_map ) > count( $gp_frozen ), count( $gp_map ) . ' labels' );

list( $code, $s3 ) = gp_plan();
// A label left out the first time may now sit near a folder the growth made; placing it is filing into the tree, not growing it.
gp_check( 'planned once more, no new folder is proposed', 200 === $code && ( 'kept' === $s3['plan']['state'] || 0 === $s3['plan']['folders'] ), $s3['plan']['state'] . ', ' . $s3['plan']['folders'] . ' new folders, ' . $s3['plan']['placed'] . ' labels placed' );

printf( "outbound requests refused: %d %s\n", count( $GLOBALS['gp_out'] ), wp_json_encode( array_count_values( $GLOBALS['gp_out'] ) ) );
printf( "%d/%d passed\n", isset( $GLOBALS['gp_pass'] ) ? $GLOBALS['gp_pass'] : 0, ( isset( $GLOBALS['gp_pass'] ) ? $GLOBALS['gp_pass'] : 0 ) + ( isset( $GLOBALS['gp_fail'] ) ? $GLOBALS['gp_fail'] : 0 ) );
