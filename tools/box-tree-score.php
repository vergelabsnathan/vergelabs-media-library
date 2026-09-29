<?php
/*
 *  The tree a site holds, scored against its truth (2026-09-26, spec-tree-planner CAP-6).
 *
 *      node tools/box-eval.mjs tools/box-tree-score.php --site shop
 *      node tools/box-eval.mjs tools/box-tree-score.php --copy tests/tree/truth-tech.json:/tmp/vgml-truth.json --env VGML_TRUTH=/tmp/vgml-truth.json
 *
 *  The truth as tools/box-truth-score.php reads it (the seed stamp, or
 *  VGML_TRUTH). Where each labelled picture sits now in the librarian's
 *  taxonomy; a picture in several folders counts in its deepest. Scored as
 *  tools/tree-lab.mjs scores, names ignored: two pictures together in the
 *  truth and in the tree (pair precision, recall, F1) at the leaf and the
 *  top level; purity; real folders of five or more recovered (a folder
 *  holds at least half of it, and it is at least half of that folder);
 *  unfiled; folders, depth, same leaf names. Read-only.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
wp_set_current_user( 1 );
$tax = vergeml_librarian_taxonomy();

$truth = array();
$file  = (string) getenv( 'VGML_TRUTH' );
if ( '' !== $file && is_readable( $file ) ) {
    foreach ( (array) json_decode( (string) file_get_contents( $file ), true ) as $id => $path ) {
        $truth[ (int) $id ] = trim( preg_replace( '/\s*[>\/]\s*/u', ' > ', (string) $path ) );
    }
} else {
    foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_vergeml_seed_leaf'", ARRAY_A ) as $r ) {
        $truth[ (int) $r['post_id'] ] = trim( preg_replace( '/\s*[>\/]\s*/u', ' > ', (string) $r['meta_value'] ) );
    }
}
// The labelled pictures the lab sees too (tools/box-filing-export.php): described, in id order so a tie breaks the same way every run.
$indexed = array_flip( array_map( 'intval', (array) $wpdb->get_col( "SELECT attachment_id FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL" ) ) );
$truth   = array_intersect_key( $truth, $indexed );
ksort( $truth );
if ( ! $truth ) {
    echo "no truth on this site: no _vergeml_seed_leaf rows and no VGML_TRUTH file\n";
    return;
}

$by_id = array();
foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) as $t ) {
    $by_id[ (int) $t->term_id ] = $t;
}
$path_of = function ( $tid ) use ( $by_id ) {
    $out = array();
    $g   = 0;
    while ( $tid && isset( $by_id[ $tid ] ) && $g++ < 32 ) {
        array_unshift( $out, vergeml_term_name( $by_id[ $tid ] ) );
        $tid = (int) $by_id[ $tid ]->parent;
    }
    return implode( ' > ', $out );
};

// Where each labelled picture sits now: its deepest folder.
$at  = array_fill_keys( array_keys( $truth ), '' );
$ids = implode( ',', array_map( 'intval', array_keys( $truth ) ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids cast to int.
foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s WHERE tr.object_id IN ({$ids})", $tax ), ARRAY_A ) as $r ) {
    $p   = $path_of( (int) $r['term_id'] );
    $o   = (int) $r['object_id'];
    $cur = $at[ $o ];
    $d   = substr_count( $p, ' > ' );
    // The deepest folder; between two as deep, the first by name, so the score never depends on row order.
    if ( '' === $cur || $d > substr_count( $cur, ' > ' ) || ( substr_count( $cur, ' > ' ) === $d && strcmp( $p, $cur ) < 0 ) ) {
        $at[ $o ] = $p;
    }
}

$pairs = function ( $level ) use ( $truth, $at ) {
    $cut  = function ( $s ) use ( $level ) {
        return '' === $s ? '' : ( 'top' === $level ? explode( ' > ', $s )[0] : $s );
    };
    $cell = array();
    $rt   = array();
    $rp   = array();
    foreach ( $truth as $id => $t ) {
        $t = $cut( $t );
        $g = $cut( $at[ $id ] );
        if ( '' !== $t ) {
            $rt[ $t ] = ( $rt[ $t ] ?? 0 ) + 1;
        }
        if ( '' !== $g ) {
            $rp[ $g ] = ( $rp[ $g ] ?? 0 ) + 1;
        }
        if ( '' !== $t && '' !== $g ) {
            $cell[ $t . "\0" . $g ] = ( $cell[ $t . "\0" . $g ] ?? 0 ) + 1;
        }
    }
    $c2 = function ( $n ) {
        return $n * ( $n - 1 ) / 2;
    };
    $tp = array_sum( array_map( $c2, $cell ) );
    $p  = $tp / max( 1, array_sum( array_map( $c2, $rp ) ) );
    $r  = $tp / max( 1, array_sum( array_map( $c2, $rt ) ) );
    return array( $p, $r, 2 * $p * $r / max( 1e-9, $p + $r ) );
};
list( $lp, $lr, $lf ) = $pairs( 'leaf' );
list( , , $tf )       = $pairs( 'top' );

$in = array();
foreach ( $at as $id => $g ) {
    if ( '' !== $g ) {
        $in[ $g ][] = $id;
    }
}
$pure     = 0;
$placed   = 0;
$majority = array();
foreach ( $in as $g => $members ) {
    $count = array();
    foreach ( $members as $id ) {
        $k           = '' !== $truth[ $id ] ? $truth[ $id ] : '(none)';
        $count[ $k ] = ( $count[ $k ] ?? 0 ) + 1;
    }
    arsort( $count );
    $best         = (string) key( $count );
    $n            = (int) current( $count );
    $majority[]   = array( $best, $n, count( $members ) );
    $pure        += $n;
    $placed      += count( $members );
}
$size = array();
foreach ( $truth as $t ) {
    if ( '' !== $t ) {
        $size[ $t ] = ( $size[ $t ] ?? 0 ) + 1;
    }
}
$big       = array_filter( $size, function ( $n ) { return $n >= 5; } );
$recovered = 0;
foreach ( $big as $t => $n ) {
    foreach ( $majority as $m ) {
        if ( $m[0] === $t && $m[1] >= $n / 2 && $m[1] >= $m[2] / 2 ) {
            $recovered++;
            break;
        }
    }
}
$leaves = array_map( function ( $g ) { return mb_strtolower( (string) substr( strrchr( ' > ' . $g, '>' ), 2 ) ); }, array_keys( $in ) );
$depth  = $in ? max( array_map( function ( $g ) { return substr_count( $g, ' > ' ) + 1; }, array_keys( $in ) ) ) : 0;
$pc     = function ( $x ) {
    return round( 100 * $x ) . '%';
};
// A hand-marked truth may say "belongs in no folder" (an empty path): how many of those the tree still holds.
$nowhere = array_keys( array_filter( $truth, function ( $t ) { return '' === $t; } ) );
$held    = count( array_filter( $nowhere, function ( $id ) use ( $at ) { return '' !== $at[ $id ]; } ) );

printf(
    "TREE F1 leaf %s (P %s R %s) · F1 top %s · purity %s · recovered %d/%d · unfiled %s%s · folders %d · depth %d · same names %d · %d labelled pictures\n",
    $pc( $lf ), $pc( $lp ), $pc( $lr ), $pc( $tf ), $pc( $pure / max( 1, $placed ) ), $recovered, count( $big ),
    $pc( ( count( $truth ) - $placed ) / count( $truth ) ), $nowhere ? sprintf( ' · belongs-nowhere placed %d/%d', $held, count( $nowhere ) ) : '',
    count( $in ), $depth, count( $leaves ) - count( array_unique( $leaves ) ), count( $truth )
);
