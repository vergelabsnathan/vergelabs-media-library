<?php
/*
 *  The fill scored against the truth, where a truth exists (S16).
 *
 *      node tools/box-eval.mjs tools/box-truth-score.php --site shop
 *
 *  Every shop picture carries `_vergeml_seed_leaf`, the catalogue path it was
 *  fetched for ("Shoes > Men > Sneakers", tools/box-seed-shop.php): 626
 *  labelled pictures that the sheets never used. This picks every picture
 *  fresh, as the baseline does, and says per band how many landed in the
 *  right folder, in an ancestor of it (broad), somewhere else (wrong) or
 *  nowhere -- and which wrong pairs happen most. A rule is judged in one
 *  run, not on sixty cards marked by hand.
 *
 *  VGML_TRUTH=/path/file.json: a truth of the same shape for a site with no
 *  stamp (attachment id => folder path with " > " or " / "), e.g. the tech
 *  library's hand-marked set. Read-only: nothing moves, nothing is spent.
 *
 *  VGML_MODEL=1 (S18): the picks carry the text model's word, asked through
 *  vergeml_filing_ask_model for the labelled pictures only -- the service's
 *  /file, forty a call, ~130 tokens a picture, about 25 cents a library,
 *  and cached a week so a second run is free. The SCORE line then also
 *  says agree · doubt · questions (right among the two) and what the
 *  library reads with the questions answered. The bands stay rules-only
 *  (tests/tree/filing-baseline*.txt); this line is never a band.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
wp_set_current_user( 1 );
$tax = vergeml_librarian_taxonomy();

// The truth: attachment id => path.
$truth = array();
$file  = (string) getenv( 'VGML_TRUTH' );
if ( '' !== $file && is_readable( $file ) ) {
    foreach ( (array) json_decode( (string) file_get_contents( $file ), true ) as $id => $path ) {
        $truth[ (int) $id ] = (string) $path;
    }
} else {
    foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_vergeml_seed_leaf'", ARRAY_A ) as $r ) {
        $truth[ (int) $r['post_id'] ] = (string) $r['meta_value'];
    }
}
if ( ! $truth ) {
    echo "no truth on this site: no _vergeml_seed_leaf rows and no VGML_TRUTH file\n";
    return;
}

// Folders by path, and each folder's ancestors.
$terms   = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
$by_id   = array();
$by_path = array();
foreach ( $terms as $t ) {
    $by_id[ (int) $t->term_id ] = $t;
}
$path_of = function ( $tid ) use ( $by_id ) {
    $out = array();
    $g   = 0;
    while ( $tid && isset( $by_id[ $tid ] ) && $g++ < 32 ) {
        array_unshift( $out, mb_strtolower( vergeml_term_name( $by_id[ $tid ] ) ) );
        $tid = (int) $by_id[ $tid ]->parent;
    }
    return implode( ' > ', $out );
};
$ancestors = array();
foreach ( $by_id as $tid => $t ) {
    $by_path[ $path_of( $tid ) ] = $tid;
    $anc = array();
    $p   = (int) $t->parent;
    $g   = 0;
    while ( $p && $g++ < 32 ) {
        $anc[ $p ] = true;
        $p = isset( $by_id[ $p ] ) ? (int) $by_id[ $p ]->parent : 0;
    }
    $ancestors[ $tid ] = $anc;
}
$truth_tid = array();
$unmapped  = array();
foreach ( $truth as $id => $path ) {
    $key = mb_strtolower( trim( preg_replace( '/\s*[>\/]\s*/u', ' > ', $path ) ) );
    if ( '' === $key ) {
        $truth_tid[ $id ] = 0; // A hand-marked truth (tools/truth-page.php): this picture belongs in no folder; right is leaving it.
    } elseif ( isset( $by_path[ $key ] ) ) {
        $truth_tid[ $id ] = $by_path[ $key ];
    } else {
        $unmapped[ $path ] = ( $unmapped[ $path ] ?? 0 ) + 1;
    }
}

// The picks, fresh, as the baseline takes them.
$ids      = array_map( 'intval', array_keys( $by_id ) );
sort( $ids );
$profiles = vergeml_filing_profiles( $ids, $tax );
$words    = vergeml_filing_words_sql( 'i' );
$rows     = (array) $wpdb->get_results( "SELECT i.attachment_id, i.embedding, i.kind, i.filing, i.caption, {$words['select']} FROM {$wpdb->vergeml_ai_index} i {$words['join']} WHERE i.error = '' AND i.embedding IS NOT NULL ORDER BY i.attachment_id ASC", ARRAY_A );
foreach ( $rows as $k => $r ) {
    $rows[ $k ]['placed_by'] = '';
}
$with_model = '1' === (string) getenv( 'VGML_MODEL' );
if ( $with_model ) {
    // Only the labelled pictures are asked about: the score reads no others, and the tech box carries 900 mock rows nobody should pay for.
    $rows = array_values( array_filter( $rows, function ( $r ) use ( $truth_tid ) { return isset( $truth_tid[ (int) $r['attachment_id'] ] ); } ) );
    $rows = vergeml_filing_ask_model( $rows, $profiles );
    $unasked = count( array_filter( $rows, function ( $r ) { return -1 === (int) $r['model_folder']; } ) );
    printf( "model: %d pictures asked about, %d unasked (no licence, the service down, or nothing to say)\n", count( $rows ) - $unasked, $unasked );
}
$picks = vergeml_filing_count( $profiles, $rows )['picks'];

// The score.
$bands = array( 'sure' => array(), 'likely' => array(), 'siblings' => array(), 'none' => array() );
foreach ( $bands as $b => $_ ) {
    $bands[ $b ] = array( 'right' => 0, 'broad' => 0, 'wrong' => 0, 'n' => 0 );
}
$pairs   = array();
$perleaf = array();
$bysrc   = array();
$looked  = 0;
// The model's tiers (S18): agree, doubt ("X, or nowhere?"), the either/or questions, and how many of those a person answering right would get.
$tiers = array( 'agree' => 0, 'doubt' => 0, 'doubt_right' => 0, 'questions' => 0, 'questions_right' => 0 );
foreach ( $truth_tid as $id => $want ) {
    if ( ! isset( $picks[ $id ] ) ) {
        continue;
    }
    $looked++;
    $pick = $picks[ $id ];
    $got  = (int) $pick['term_id'];
    $band = ! $got ? 'none' : ( 'siblings' === $pick['why'] ? 'siblings' : $pick['confidence'] );
    if ( 'agree' === $pick['why'] ) {
        $tiers['agree']++;
    } elseif ( 'doubt' === $pick['why'] ) {
        $tiers['doubt']++;
        // Answered right when the truth is the rules' folder, or no folder at all.
        if ( $want === (int) $pick['nearest'] || ! $want ) {
            $tiers['doubt_right']++;
        }
    } elseif ( vergeml_filing_is_either( $pick ) ) {
        $tiers['questions']++;
        if ( in_array( $want, array_map( 'intval', (array) $pick['children'] ), true ) ) {
            $tiers['questions_right']++;
        }
    }
    if ( ! $want ) {
        // Belongs in no folder: leaving it is right, placing it anywhere is wrong.
        $fate = ! $got ? 'right' : 'wrong';
    } else {
        $fate = ! $got ? 'none' : ( $got === $want ? 'right' : ( isset( $ancestors[ $want ][ $got ] ) ? 'broad' : 'wrong' ) );
    }
    $bands[ $band ]['n']++;
    if ( 'none' !== $fate ) {
        $bands[ $band ][ $fate ]++;
        // The placement's source (S16): plan, name, members, matches, word or vector -- so "the wrong sures are learned words" is a line here, not a probe.
        $src = isset( $pick['source'] ) && '' !== $pick['source'] ? $pick['source'] : '?';
        $bysrc[ $src ][ $fate ] = ( $bysrc[ $src ][ $fate ] ?? 0 ) + 1;
        $bysrc[ $src ]['n']     = ( $bysrc[ $src ]['n'] ?? 0 ) + 1;
    }
    $leafp = $want ? $path_of( $want ) : '(no folder)';
    $perleaf[ $leafp ]['n']     = ( $perleaf[ $leafp ]['n'] ?? 0 ) + 1;
    $perleaf[ $leafp ][ $fate ] = ( $perleaf[ $leafp ][ $fate ] ?? 0 ) + 1;
    if ( 'wrong' === $fate ) {
        $k = $leafp . '  ->  ' . $path_of( $got ) . ' (' . $band . ')';
        $pairs[ $k ] = ( $pairs[ $k ] ?? 0 ) + 1;
    }
}

printf( "truth: %d pictures labelled, %d map to a folder of this tree, %d looked at; %d labels name no folder%s\n", count( $truth ), count( $truth_tid ), $looked, array_sum( $unmapped ), $unmapped ? ' (' . implode( '; ', array_slice( array_keys( $unmapped ), 0, 5 ) ) . ')' : '' );
$right = 0; $placed = 0;
foreach ( array( 'sure', 'likely', 'siblings' ) as $b ) {
    $x = $bands[ $b ];
    $placed += $x['n'];
    $right  += $x['right'];
    printf( "%-9s %4d placed · right %3d (%3d%%) · broad %3d (%3d%%) · wrong %3d (%3d%%)\n", $b, $x['n'], $x['right'], $x['n'] ? round( 100 * $x['right'] / $x['n'] ) : 0, $x['broad'], $x['n'] ? round( 100 * $x['broad'] / $x['n'] ) : 0, $x['wrong'], $x['n'] ? round( 100 * $x['wrong'] / $x['n'] ) : 0 );
}
printf( "%-9s %4d%s\n", 'none', $bands['none']['n'], $bands['none']['right'] ? sprintf( ' · right to leave %d', $bands['none']['right'] ) : '' );
// A no-folder truth counts as right when left: "right" is the engine's answer being right, placed or not.
$right += $bands['none']['right'];
$line   = sprintf( 'SCORE right-and-placed %d of %d (%d%%) · right of placed %d%%', $right, $looked, $looked ? round( 100 * $right / $looked ) : 0, $placed ? round( 100 * ( $right - $bands['none']['right'] ) / $placed ) : 0 );
if ( $with_model ) {
    // A doubt answered right is a picture in the rules' folder or left alone; the none band already counts a doubt left alone on a no-folder truth as right, so only the placed half is added.
    $doubt_placed_right = 0;
    foreach ( $truth_tid as $id => $want ) {
        if ( isset( $picks[ $id ] ) && 'doubt' === $picks[ $id ]['why'] && $want && $want === (int) $picks[ $id ]['nearest'] ) {
            $doubt_placed_right++;
        }
    }
    $answered = $right + $tiers['questions_right'] + $doubt_placed_right;
    $line    .= sprintf( ' · agree %d · doubt %d (X or nowhere right %d) · questions %d (right among the two %d) · with the questions answered %d of %d (%d%%) · sure right %d%%', $tiers['agree'], $tiers['doubt'], $tiers['doubt_right'], $tiers['questions'], $tiers['questions_right'], $answered, $looked, $looked ? round( 100 * $answered / $looked ) : 0, $bands['sure']['n'] ? round( 100 * $bands['sure']['right'] / $bands['sure']['n'] ) : 0 );
}
echo $line . "\n";

uasort( $bysrc, function ( $a, $b ) { return $b['n'] <=> $a['n']; } );
echo "\nplaced, by the source of the hit (right / broad / wrong of n):\n";
foreach ( $bysrc as $src => $x ) {
    printf( "  %-8s %3d / %3d / %3d of %3d\n", $src, $x['right'] ?? 0, $x['broad'] ?? 0, $x['wrong'] ?? 0, $x['n'] );
}

arsort( $pairs );
echo "\nwrong pairs, most first:\n";
foreach ( array_slice( $pairs, 0, 20, true ) as $k => $n ) {
    printf( "%3d  %s\n", $n, $k );
}
uasort( $perleaf, function ( $a, $b ) { return ( ( $b['wrong'] ?? 0 ) + ( $b['none'] ?? 0 ) ) <=> ( ( $a['wrong'] ?? 0 ) + ( $a['none'] ?? 0 ) ); } );
echo "\nleaves by what they lose (right / broad / wrong / none of n):\n";
foreach ( array_slice( $perleaf, 0, 15, true ) as $leaf => $x ) {
    printf( "  %-44s %2d / %2d / %2d / %2d of %2d\n", $leaf, $x['right'] ?? 0, $x['broad'] ?? 0, $x['wrong'] ?? 0, $x['none'] ?? 0, $x['n'] );
}
