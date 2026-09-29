<?php
/*
 *  New pictures into a planned tree, matched against folder profiles built
 *  from words or from member pictures (spec-tree-planner story 5, CAP-3).
 *
 *      MSYS_NO_PATHCONV=1 node <repo>/tools/box-eval.mjs <repo>/tools/box-profile-heldout.php --site shop --copy plan.json:/tmp/vgml-plan.json --env VGML_ASSIGN=/tmp/vgml-plan.json [--env VGML_MODES=A,V,H,B,C] [--env VGML_PRINT=1]
 *
 *  Run from the directory holding plan.json: --copy splits on ':', so a
 *  path with a drive letter breaks it. About 30 s a mode on the shop.
 *
 *  plan.json is a plan as each picture's planned path ({ picture id: path },
 *  what a plan's label map files). The labels are held out in four folds:
 *  a fold's pictures are new, and vergeml_filing_pick files them against
 *  profiles built from the other three folds' pictures. Modes:
 *
 *    A  today: the draft's words (vergeml_guide_draft_words -- the members'
 *       objects and the folder's own name), the vector from that text
 *    V  as A, the vector the centroid of the members' vectors
 *    H  as A, a parent with no labels of its own takes its subtree's class
 *       halves (said by two or more members) instead of its name alone
 *    B  V and H together
 *    C  members only: the members' objects or halves, the centroid, no name
 *
 *  Scored per held-out picture with a truth: filed, filed where the plan
 *  puts it, filed where most of its truth folder's other pictures are.
 *  VGML_PRINT=1 adds each mode's filing as one JSON line (ASSIGN<mode>),
 *  for tools/tree-lab.mjs assign. Read-only: nothing is stored, /embed is
 *  free (the class phrases' vectors are cached a week).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$ph_plan  = json_decode( (string) file_get_contents( (string) getenv( 'VGML_ASSIGN' ) ), true );
$ph_truth = array();
foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_vergeml_seed_leaf'", ARRAY_A ) as $r ) {
    $ph_truth[ (int) $r['post_id'] ] = trim( preg_replace( '/\s*[>\/]\s*/u', ' > ', (string) $r['meta_value'] ) );
}

$ph_pic = array();
foreach ( $wpdb->get_results( "SELECT attachment_id, kind, filing, embedding FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL ORDER BY attachment_id", ARRAY_A ) as $r ) {
    $id = (int) $r['attachment_id'];
    $f  = json_decode( (string) $r['filing'], true );
    $c  = vergeml_filing_classes_of_object( is_array( $f ) && isset( $f['object'] ) ? $f['object'] : '' );
    $ph_pic[ $id ] = array(
        'row'   => $r,
        'label' => vergeml_plan_label_of( $r['kind'], $f ),
        'obj'   => isset( $c[0] ) ? $c[0] : '',
        'half'  => isset( $c[1] ) ? $c[1] : '',
        'kind'  => '' === (string) $r['kind'] ? 'photo' : sanitize_key( $r['kind'] ),
        'v'     => vergeml_index_vector_out( $r['embedding'] ),
        'path'  => isset( $ph_plan[ $id ] ) ? (string) $ph_plan[ $id ] : '',
        'truth' => isset( $ph_truth[ $id ] ) ? $ph_truth[ $id ] : '',
    );
}

$ph_paths = array();
foreach ( $ph_pic as $p ) {
    if ( '' !== $p['path'] ) {
        $parts = explode( ' > ', $p['path'] );
        for ( $i = 1; $i <= count( $parts ); $i++ ) {
            $ph_paths[ implode( ' > ', array_slice( $parts, 0, $i ) ) ] = true;
        }
    }
}
$ph_paths = array_keys( $ph_paths );
sort( $ph_paths );

function ph_held( $p, $fold ) {
    return '' !== $p['label'] && ( crc32( $p['label'] ) % 4 ) === $fold;
}

/** The halves two or more of these members say, most said first, at most eight. */
function ph_halves( $members ) {
    $h = array();
    foreach ( $members as $p ) {
        if ( '' !== $p['half'] ) {
            $h[ $p['half'] ] = ( isset( $h[ $p['half'] ] ) ? $h[ $p['half'] ] : 0 ) + 1;
        }
    }
    arsort( $h );
    return array_keys( array_slice( array_filter( $h, function ( $n ) { return $n >= 2; } ), 0, 8, true ) );
}

function ph_centroid( $members ) {
    $vs = array();
    foreach ( $members as $p ) {
        if ( $p['v'] ) {
            $vs[] = $p['v'];
        }
    }
    return vergeml_filing_centroid( $vs );
}

/** One fold's profiles, keyed by a number of this run's own, and the path of each. */
function ph_profiles( $mode, $fold, $pic, $paths ) {
    $id_of = array();
    foreach ( $paths as $i => $path ) {
        $id_of[ $path ] = $i + 1;
    }
    $profiles = array();
    foreach ( $paths as $path ) {
        $direct  = array();
        $subtree = array();
        foreach ( $pic as $p ) {
            if ( '' === $p['path'] || '' === $p['label'] || ph_held( $p, $fold ) ) {
                continue;
            }
            if ( $p['path'] === $path ) {
                $direct[] = $p;
            }
            if ( $p['path'] === $path || 0 === strpos( $p['path'], $path . ' > ' ) ) {
                $subtree[] = $p;
            }
        }
        $objs  = array_values( array_unique( array_filter( array_column( $direct, 'obj' ) ) ) );
        $kinds = array_values( array_unique( array_column( $direct, 'kind' ) ) );
        $segs  = explode( ' > ', $path );
        $leaf  = (string) end( $segs );
        if ( ! $objs && in_array( $mode, array( 'H', 'B', 'C' ), true ) ) {
            $objs  = ph_halves( $subtree );
            $kinds = array_values( array_unique( array_column( $subtree, 'kind' ) ) );
        }
        if ( 'C' === $mode ) {
            $classes = $objs ? array_values( array_map( 'vergeml_filing_name_class', $objs ) ) : array( vergeml_filing_name_class( $leaf ) );
            $kinds   = $kinds ? $kinds : vergeml_filing_kinds_of( $leaf );
            $vector  = ph_centroid( $direct ? $direct : $subtree );
        } else {
            $w       = vergeml_guide_draft_words( array( 'classes' => $objs, 'kinds' => $kinds, 'audience' => '', 'matches' => '', 'name' => $leaf ), $segs );
            $classes = $w['classes'];
            $kinds   = $w['kinds'];
            $vector  = in_array( $mode, array( 'V', 'B' ), true ) ? ph_centroid( $direct ? $direct : $subtree ) : vergeml_meaning_vector( $w['text'] );
        }
        if ( ! $vector ) {
            continue;
        }
        $n = $id_of[ $path ];
        $profiles[ $n ] = array(
            'version' => VERGEML_FILING_VERSION, 'source' => 'plan', 'plan' => array(), 'path' => $segs,
            'classes' => $classes, 'kinds' => $kinds, 'audience' => '', 'matches' => '', 'text' => $path, 'vector' => $vector,
            // The norm cache keys on built_at: one key per mode, fold and folder.
            'built_at' => 1000000 * ord( $mode ) + 1000 * $fold + $n,
            'term_id' => $n, 'parent_id' => count( $segs ) > 1 ? $id_of[ implode( ' > ', array_slice( $segs, 0, -1 ) ) ] : 0,
        );
    }
    return array( vergeml_filing_settle_claims( $profiles ), array_flip( $id_of ) );
}

foreach ( explode( ',', getenv( 'VGML_MODES' ) ? (string) getenv( 'VGML_MODES' ) : 'A,V,H,B,C' ) as $ph_mode ) {
    $out = array();
    $n   = array( 'held' => 0, 'filed' => 0, 'plan' => 0, 'home' => 0 );
    for ( $fold = 0; $fold < 4; $fold++ ) {
        list( $profiles, $path_of ) = ph_profiles( $ph_mode, $fold, $ph_pic, $ph_paths );
        $home = array(); // truth folder => the planned path holding most of its other pictures
        foreach ( $ph_pic as $p ) {
            if ( '' !== $p['path'] && '' !== $p['truth'] && '' !== $p['label'] && ! ph_held( $p, $fold ) ) {
                $home[ $p['truth'] ][ $p['path'] ] = ( isset( $home[ $p['truth'] ][ $p['path'] ] ) ? $home[ $p['truth'] ][ $p['path'] ] : 0 ) + 1;
            }
        }
        foreach ( $home as $t => $by ) {
            arsort( $by );
            $home[ $t ] = key( $by );
        }
        foreach ( $ph_pic as $id => $p ) {
            if ( ! ph_held( $p, $fold ) ) {
                continue;
            }
            $pick       = vergeml_filing_pick( vergeml_filing_facts( $p['row'] ), $profiles );
            $to         = ! empty( $pick['term_id'] ) && isset( $path_of[ (int) $pick['term_id'] ] ) ? $path_of[ (int) $pick['term_id'] ] : '';
            $out[ $id ] = $to;
            if ( '' === $p['truth'] ) {
                continue;
            }
            $n['held']++;
            if ( '' !== $to ) {
                $n['filed']++;
                $n['plan'] += $to === $p['path'] ? 1 : 0;
                $n['home'] += isset( $home[ $p['truth'] ] ) && $to === $home[ $p['truth'] ] ? 1 : 0;
            }
        }
    }
    printf( "%s  held %d · filed %d (%.0f%%) · where the plan puts it %d (%.0f%% of filed) · with its truth folder %d (%.0f%% of filed)\n", $ph_mode, $n['held'], $n['filed'], 100 * $n['filed'] / max( 1, $n['held'] ), $n['plan'], 100 * $n['plan'] / max( 1, $n['filed'] ), $n['home'], 100 * $n['home'] / max( 1, $n['filed'] ) );
    if ( getenv( 'VGML_PRINT' ) ) {
        echo 'ASSIGN' . $ph_mode . ' ' . wp_json_encode( $out ) . "\n";
    }
}
