<?php
/*
 *  A frozen tree's growth, measured on a site's own folders (spec-tree-planner
 *  story 10). Read-only: nothing is stored, no service is called.
 *
 *      node tools/box-eval.mjs tools/box-grow-heldout.php --site shop
 *      MSYS_NO_PATHCONV=1 node tools/box-eval.mjs tools/box-grow-heldout.php --copy tests/tree/truth-tech.json:/tmp/vgml-truth.json --env VGML_TRUTH=/tmp/vgml-truth.json
 *
 *  The site's folders as they stand are the frozen tree. Two ways of making
 *  pictures "new", each run through vergeml_plan_grow_decide exactly as the
 *  re-plan runs it, with the new pictures taken out of every folder first:
 *
 *    H  labels held out a quarter at a time (as tools/box-profile-heldout.php):
 *       a held picture counts when it lands in its truth folder's home, the
 *       existing folder holding most of that truth folder's other pictures.
 *       "was" is the same count for where the picture sits today.
 *    F  one existing leaf folder at a time taken away with every label that
 *       mostly lives there: its pictures should come back as one new folder
 *       under the parent it had.
 *
 *  VGML_SWEEP="0.7/0.05/0.6,0.8/0.05/0.7" tries other join/margin/group
 *  settings than VERGEML_PLAN_GROW_*; VGML_UNDER the parent's cosine;
 *  VGML_PRINT=1 lists F per folder. About 2 s a setting on the shop.
 *
 *  2026-09-29, the settings chosen (0.7/0.05/0.6, under 0.5):
 *    shop H placed 264 of 625, 87 % in their home (57 % where they sit
 *         today), new folders 98 % pure; F 53 % back in one new folder
 *    tech H placed 75 of 127, 83 % home (68 % today), new folders 81 %
 *         pure; F 55 % back in one new folder, 10 of 12 under the old parent
 *  An absolute cosine alone (0.5, the plan's own) placed 94 % at 70 % and
 *  never made a new folder: a label sits at 0.7-0.9 to half a department.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$gh_tax = vergeml_librarian_taxonomy();

$gh_truth = array();
$gh_file  = (string) getenv( 'VGML_TRUTH' );
if ( '' !== $gh_file && is_readable( $gh_file ) ) {
    foreach ( (array) json_decode( (string) file_get_contents( $gh_file ), true ) as $id => $path ) {
        $gh_truth[ (int) $id ] = trim( preg_replace( '/\s*[>\/]\s*/u', ' > ', (string) $path ) );
    }
} else {
    foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_vergeml_seed_leaf'", ARRAY_A ) as $r ) {
        $gh_truth[ (int) $r['post_id'] ] = trim( preg_replace( '/\s*[>\/]\s*/u', ' > ', (string) $r['meta_value'] ) );
    }
}

$gh_terms = get_terms( array( 'taxonomy' => $gh_tax, 'hide_empty' => false ) );
$gh_fold  = array();
$gh_par   = array();
$gh_skip  = array();
foreach ( $gh_terms as $t ) {
    $gh_par[ (int) $t->term_id ] = (int) $t->parent;
    if ( VERGEML_FILING_TO_SORT_SLUG === $t->slug ) {
        $gh_skip[ (int) $t->term_id ] = true;
    }
}
$gh_depth = vergeml_plan_grow_depths( $gh_par );
// Targets exactly as the real read decides them: read it once with an empty frozen map and keep only the shape.
$gh_shape = vergeml_plan_grow_read( array(), $gh_tax );
foreach ( $gh_shape['folders'] as $tid => $f ) {
    $gh_fold[ $tid ] = array( 'name' => $f['name'], 'parent' => $f['parent'], 'direct' => null, 'n' => 0, 'sub' => null, 'target' => $f['target'] );
}

$gh_pic = array();
$after  = 0;
do {
    $rows = (array) vergeml_guide_rule_rows( $gh_tax, 'all', array( 'filing', 'terms', 'embedding' ), $after, 500 );
    foreach ( $rows as $r ) {
        $after = (int) $r['attachment_id'];
        $v     = vergeml_index_vector_out( $r['embedding'] );
        $f     = json_decode( (string) $r['filing'], true );
        $label = vergeml_plan_label_of( $r['kind'], $f );
        if ( ! $v || '' === $label ) {
            continue;
        }
        $c    = vergeml_filing_classes_of_object( is_array( $f ) && isset( $f['object'] ) ? $f['object'] : '' );
        $kind = sanitize_key( (string) $r['kind'] );
        $gh_pic[ $after ] = array(
            'v'      => vergeml_plan_unit( $v ),
            'label'  => $label,
            'object' => $c[0],
            'class'  => isset( $c[1] ) ? $c[1] : $c[0],
            'kind'   => '' === $kind ? 'photo' : $kind,
            'now'    => vergeml_plan_grow_now( $r['in_terms'], $gh_depth, $gh_skip ),
            'truth'  => isset( $gh_truth[ $after ] ) ? $gh_truth[ $after ] : '',
        );
    }
} while ( 500 === count( $rows ) );

/** The decide inputs with $new_ids taken out of every folder and made waiting; $drop folders removed. */
function gh_inputs( $pic, $folders, $new_ids, $drop = array() ) {
    foreach ( $drop as $tid => $x ) {
        unset( $folders[ $tid ] );
    }
    $new = array();
    foreach ( $pic as $id => $p ) {
        if ( isset( $new_ids[ $id ] ) ) {
            $l = $p['label'];
            if ( ! isset( $new[ $l ] ) ) {
                $new[ $l ] = array( 'sum' => null, 'n' => 0, 'object' => $p['object'], 'class' => $p['class'], 'kind' => $p['kind'] );
            }
            $new[ $l ]['sum'] = null === $new[ $l ]['sum'] ? $p['v'] : vergeml_plan_add( $new[ $l ]['sum'], $p['v'] );
            $new[ $l ]['n']++;
            continue;
        }
        $now = $p['now'];
        if ( ! $now || ! isset( $folders[ $now ] ) ) {
            continue;
        }
        $folders[ $now ]['direct'] = null === $folders[ $now ]['direct'] ? $p['v'] : vergeml_plan_add( $folders[ $now ]['direct'], $p['v'] );
        $folders[ $now ]['n']++;
        for ( $t = $now, $g = 0; isset( $folders[ $t ] ) && $g < 32; $t = $folders[ $t ]['parent'], $g++ ) {
            $folders[ $t ]['sub'] = null === $folders[ $t ]['sub'] ? $p['v'] : vergeml_plan_add( $folders[ $t ]['sub'], $p['v'] );
        }
    }
    return array( $new, $folders );
}

/** Where each new picture lands: 't<id>' placed, 'g<i>' a new folder, '' left. */
function gh_land( $pic, $new_ids, $d ) {
    $in_group = array();
    foreach ( $d['grow'] as $i => $g ) {
        foreach ( $g['labels'] as $l ) {
            $in_group[ $l ] = $i;
        }
    }
    $out = array();
    foreach ( $new_ids as $id => $x ) {
        $l          = $pic[ $id ]['label'];
        $out[ $id ] = isset( $d['place'][ $l ] ) ? 't' . $d['place'][ $l ] : ( isset( $in_group[ $l ] ) ? 'g' . $in_group[ $l ] : '' );
    }
    return $out;
}

/** The share of a group's pictures that share its most common truth folder. */
function gh_purity( $pic, $land ) {
    $by = array();
    foreach ( $land as $id => $to ) {
        if ( '' !== $to && '' !== $pic[ $id ]['truth'] ) {
            $by[ $to ][ $pic[ $id ]['truth'] ] = ( isset( $by[ $to ][ $pic[ $id ]['truth'] ] ) ? $by[ $to ][ $pic[ $id ]['truth'] ] : 0 ) + 1;
        }
    }
    $top = 0;
    $all = 0;
    foreach ( $by as $t ) {
        $top += max( $t );
        $all += array_sum( $t );
    }
    return $all ? $top / $all : 0;
}

$gh_t0    = microtime( true );
// VGML_SWEEP="join/margin/group,..." tries other settings than VERGEML_PLAN_GROW_*; VGML_UNDER sets the parent's cosine.
$gh_under = getenv( 'VGML_UNDER' ) ? (float) getenv( 'VGML_UNDER' ) : VERGEML_PLAN_GROW_UNDER;
foreach ( explode( ',', getenv( 'VGML_SWEEP' ) ? (string) getenv( 'VGML_SWEEP' ) : VERGEML_PLAN_GROW_JOIN . '/' . VERGEML_PLAN_GROW_MARGIN . '/' . VERGEML_PLAN_GROW_GROUP ) as $gh_set ) {
list( $gh_join, $gh_margin, $gh_group ) = array_map( 'floatval', explode( '/', $gh_set ) );
$gh_opts = array( 'join' => $gh_join, 'margin' => $gh_margin, 'group' => $gh_group, 'under' => $gh_under );
printf( "join %.2f · margin %.2f · group %.2f · under %.2f\n", $gh_join, $gh_margin, $gh_group, $gh_under );

// ---- H: labels held out a quarter at a time.
$n = array( 'held' => 0, 'placed' => 0, 'grown' => 0, 'left' => 0, 'home' => 0, 'was' => 0, 'was_filed' => 0, 'folders' => 0 );
$gh_land_all = array();
for ( $fold = 0; $fold < 4; $fold++ ) {
    $held = array();
    foreach ( $gh_pic as $id => $p ) {
        if ( ( crc32( $p['label'] ) % 4 ) === $fold ) {
            $held[ $id ] = true;
        }
    }
    list( $new, $folders ) = gh_inputs( $gh_pic, $gh_fold, $held );
    $d    = vergeml_plan_grow_decide( $new, $folders, $gh_opts );
    $land = gh_land( $gh_pic, $held, $d );
    // Home: the existing folder holding most of a truth folder's pictures that are not held.
    $home = array();
    foreach ( $gh_pic as $id => $p ) {
        if ( ! isset( $held[ $id ] ) && $p['now'] && '' !== $p['truth'] ) {
            $home[ $p['truth'] ][ $p['now'] ] = ( isset( $home[ $p['truth'] ][ $p['now'] ] ) ? $home[ $p['truth'] ][ $p['now'] ] : 0 ) + 1;
        }
    }
    foreach ( $home as $t => $by ) {
        arsort( $by );
        $home[ $t ] = key( $by );
    }
    $n['folders'] += count( $d['grow'] );
    foreach ( $land as $id => $to ) {
        $p = $gh_pic[ $id ];
        if ( '' === $p['truth'] ) {
            continue;
        }
        $n['held']++;
        $n[ '' === $to ? 'left' : ( 't' === $to[0] ? 'placed' : 'grown' ) ]++;
        if ( '' !== $to && 't' === $to[0] && isset( $home[ $p['truth'] ] ) && (int) substr( $to, 1 ) === $home[ $p['truth'] ] ) {
            $n['home']++;
        }
        if ( $p['now'] ) {
            $n['was_filed']++;
            $n['was'] += isset( $home[ $p['truth'] ] ) && $p['now'] === $home[ $p['truth'] ] ? 1 : 0;
        }
        $gh_land_all[ $id ] = '' === $to ? '' : $fold . $to;
    }
}
printf( "H  held %d · placed %d (home %d = %.0f%% of placed) · in %d new folders %d · left %d (%.0f%%) · grown purity %.0f%% · today in home %d of %d filed (%.0f%%)\n",
    $n['held'], $n['placed'], $n['home'], 100 * $n['home'] / max( 1, $n['placed'] ), $n['folders'], $n['grown'], $n['left'], 100 * $n['left'] / max( 1, $n['held'] ),
    100 * gh_purity( $gh_pic, array_filter( $gh_land_all, function ( $to ) { return '' !== $to && 'g' === $to[1]; } ) ),
    $n['was'], $n['was_filed'], 100 * $n['was'] / max( 1, $n['was_filed'] ) );

// ---- F: one leaf folder at a time taken away with the labels that mostly live there.
$kids = array();
foreach ( $gh_fold as $tid => $f ) {
    $kids[ $f['parent'] ] = ( isset( $kids[ $f['parent'] ] ) ? $kids[ $f['parent'] ] : 0 ) + 1;
}
$major = array();
foreach ( $gh_pic as $id => $p ) {
    if ( $p['now'] ) {
        $major[ $p['label'] ][ $p['now'] ] = ( isset( $major[ $p['label'] ][ $p['now'] ] ) ? $major[ $p['label'] ][ $p['now'] ] : 0 ) + 1;
    }
}
foreach ( $major as $l => $by ) {
    arsort( $by );
    $major[ $l ] = key( $by );
}
$f = array( 'folders' => 0, 'pics' => 0, 'one' => 0, 'parent' => 0, 'placed' => 0, 'left' => 0, 'made' => 0 );
foreach ( $gh_fold as $tid => $fo ) {
    if ( ! empty( $kids[ $tid ] ) || empty( $fo['target'] ) ) {
        continue;
    }
    $gone = array();
    foreach ( $gh_pic as $id => $p ) {
        if ( $p['now'] === $tid && isset( $major[ $p['label'] ] ) && $major[ $p['label'] ] === $tid ) {
            $gone[ $id ] = true;
        }
    }
    if ( count( $gone ) < VERGEML_PLAN_MIN ) {
        continue;
    }
    list( $new, $folders ) = gh_inputs( $gh_pic, $gh_fold, $gone, array( $tid => true ) );
    $d    = vergeml_plan_grow_decide( $new, $folders, $gh_opts );
    $land = gh_land( $gh_pic, $gone, $d );
    $cnt  = array_count_values( array_filter( $land, 'strlen' ) );
    $big  = '';
    foreach ( $cnt as $to => $c ) {
        if ( 'g' === $to[0] && ( '' === $big || $c > $cnt[ $big ] ) ) {
            $big = $to;
        }
    }
    $f['folders']++;
    $f['pics']  += count( $gone );
    $f['made']  += count( $d['grow'] );
    $f['one']   += '' !== $big ? $cnt[ $big ] : 0;
    $f['parent'] += '' !== $big && (int) $d['grow'][ (int) substr( $big, 1 ) ]['parent'] === (int) $fo['parent'] ? 1 : 0;
    $placed = 0;
    $left   = 0;
    foreach ( $land as $to ) {
        $placed += '' !== $to && 't' === $to[0] ? 1 : 0;
        $left   += '' === $to ? 1 : 0;
    }
    $f['placed'] += $placed;
    $f['left']   += $left;
    if ( getenv( 'VGML_PRINT' ) ) {
        $g = '' !== $big ? $d['grow'][ (int) substr( $big, 1 ) ] : null;
        printf( "   %-28s %3d · new %s%s · placed %d · left %d\n", mb_substr( $fo['name'], 0, 28 ), count( $gone ), $g ? '"' . $g['name'] . '" ' . $cnt[ $big ] : 'none', $g && (int) $g['parent'] === (int) $fo['parent'] ? ' (old parent)' : '', $placed, $left );
    }
}
printf( "F  %d leaf folders taken away, %d pictures · back in their largest new folder %d (%.0f%%) · that folder under the old parent %d of %d · placed in existing %d · left %d · new folders made %d\n",
    $f['folders'], $f['pics'], $f['one'], 100 * $f['one'] / max( 1, $f['pics'] ), $f['parent'], $f['folders'], $f['placed'], $f['left'], $f['made'] );
}
printf( "   %.1f s\n", microtime( true ) - $gh_t0 );
