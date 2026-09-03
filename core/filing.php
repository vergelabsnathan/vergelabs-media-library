<?php
/**
 *  Filing: which folder does this picture belong in?
 *
 *  One answer for every path that puts pictures into folders -- the chat's
 *  re-filing, the upload hook, the librarian. Before this each had its own
 *  rule, and the chat's was "nearest folder by cosine above 0.16". Measured on
 *  the test box on 3 September 2026: every picture scored every folder in a
 *  flat 0.28-0.44 band, so nearest was a coin flip. A sales chart went into
 *  Women / Shoes, tailor-shop logos into Men, a phone into Bags. The
 *  descriptions knew better -- "sales chart; business diagram" was right there
 *  in the record -- and the matcher never read them.
 *
 *  So this files by evidence, in this order:
 *
 *    1. Gates. A folder can say what kinds of picture it takes (a Logos folder
 *       takes logos; a product folder does not) and who it is for (Men, Women,
 *       Kids). A picture that fails a gate is not a candidate, however close
 *       its vector. A picture with no audience evidence does not enter a
 *       gendered folder: it stops at the parent, which is the honest answer.
 *    2. Class. The describer files every picture as "specific; class" --
 *       "ankle boot; footwear". A folder's classes come from the planner, or
 *       from its name. Matching class words against each other, short phrase
 *       to short phrase, gives a spread the whole-record cosine never had.
 *    3. Vector, as a tie-break only.
 *
 *  And it abstains. The best folder must clear a floor and beat the runner-up
 *  by a margin, or the picture stays where it was and the run says how many
 *  did. "Thirty-eight did not fit any folder" is a real answer; a wrong folder
 *  is a wrong answer that looks like a right one.
 *
 *  @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const VERGEML_FILING_META    = '_vergeml_profile';
const VERGEML_FILING_VERSION = 1;

/*
 *  Calibrated on the box, 3 September 2026: with class matching the right
 *  folder scores 0.75-1.0 and the wrong ones 0.2-0.45. The floor sits in the
 *  gap; the margin is what "clearly one folder, not two" means.
 */
const VERGEML_FILING_FLOOR  = 0.55;
const VERGEML_FILING_MARGIN = 0.08;

/** How much the class match weighs against the vector. */
const VERGEML_FILING_CLASS_WEIGHT = 0.75;


/* ------------------------------------------------------------ vocabulary */

/**
 *  What a folder's name says about who it is for.
 *
 *  @return string 'men', 'women', 'kids', or ''.
 */
function vergeml_filing_audience_of( $text ) {
    $t = ' ' . mb_strtolower( trim( (string) $text ) ) . ' ';
    $t = str_replace( array( "'", '’' ), '', $t );
    if ( preg_match( '/ (men|mens|man|male|gents|gentlemen|heren|mannen) /u', $t ) ) {
        return 'men';
    }
    if ( preg_match( '/ (women|womens|woman|female|ladies|lady|dames|vrouwen) /u', $t ) ) {
        return 'women';
    }
    if ( preg_match( '/ (kids|kid|children|child|baby|babies|boys|girls|toddler|toddlers|kinderen) /u', $t ) ) {
        return 'kids';
    }
    return '';
}

/**
 *  What a folder's name says about the kind of picture it holds.
 *
 *  @return string[] Subset of the describer's kinds.
 */
function vergeml_filing_kinds_of( $name ) {
    $n = ' ' . mb_strtolower( trim( (string) $name ) ) . ' ';
    if ( preg_match( '/ (logo|logos|brand|branding|wordmark|wordmarks|marks) /u', $n ) ) {
        return array( 'logo' );
    }
    if ( preg_match( '/ (icon|icons) /u', $n ) ) {
        return array( 'illustration', 'logo' );
    }
    if ( preg_match( '/ (screenshot|screenshots|screens|ui|interface|app) /u', $n ) ) {
        return array( 'screenshot' );
    }
    if ( preg_match( '/ (diagram|diagrams|chart|charts|graph|graphs|infographic|infographics) /u', $n ) ) {
        return array( 'diagram' );
    }
    if ( preg_match( '/ (document|documents|scan|scans|invoice|invoices|pdf|pdfs|paperwork|forms) /u', $n ) ) {
        return array( 'document' );
    }
    if ( preg_match( '/ (illustration|illustrations|drawing|drawings|artwork|sketch|sketches|render|renders) /u', $n ) ) {
        return array( 'illustration' );
    }
    // A folder that says nothing about kind takes what a camera or a pen makes.
    return array( 'photo', 'illustration' );
}

/** The describer's audience field, folded onto the same three words. */
function vergeml_filing_audience_of_picture( $audience ) {
    return vergeml_filing_audience_of( $audience );
}

/**
 *  "platform sneaker; footwear" -> array( 'platform sneaker', 'footwear' ).
 *  Older records carry one phrase; a phrase with commas is split too.
 */
function vergeml_filing_classes_of_object( $object ) {
    $parts = preg_split( '/\s*[;,]\s*/u', mb_strtolower( trim( (string) $object ) ) );
    $out   = array();
    foreach ( (array) $parts as $p ) {
        $p = trim( $p );
        if ( '' !== $p ) {
            $out[] = $p;
        }
    }
    return array_values( array_unique( $out ) );
}

/** A folder name as a class word: lowercase, no trailing "s" ambiguity handled by the match itself. */
function vergeml_filing_name_class( $name ) {
    return mb_strtolower( trim( (string) $name ) );
}


/* -------------------------------------------------------------- profiles */

/**
 *  The profile a folder is matched against.
 *
 *  Kept on the term, so it is built once and every path reads the same one.
 *  A plan (the chat) writes a richer profile at creation; a folder somebody
 *  made by hand gets one derived from its name and its ancestors the first
 *  time anything asks.
 *
 *  @return array|null null when the vector could not be made (no licence, service down).
 */
function vergeml_filing_profile( $term_id, $taxonomy ) {

    $term_id = (int) $term_id;
    $meta    = get_term_meta( $term_id, VERGEML_FILING_META, true );

    if ( is_array( $meta ) && isset( $meta['version'] ) && (int) $meta['version'] === VERGEML_FILING_VERSION && ! empty( $meta['vector'] ) ) {
        return $meta;
    }

    $term = get_term( $term_id, $taxonomy );
    if ( ! $term || is_wp_error( $term ) ) {
        return null;
    }

    $seed = is_array( $meta ) ? $meta : array();

    return vergeml_filing_profile_build( $term, $taxonomy, $seed );
}

/**
 *  Build and store. $seed carries what a plan said (classes, kinds, audience,
 *  matches); anything it did not say is derived from the name and the path.
 */
function vergeml_filing_profile_build( $term, $taxonomy, $seed = array() ) {

    $path  = array();
    $walk  = $term;
    $guard = 0;
    while ( $walk && ! is_wp_error( $walk ) && $guard++ < 10 ) {
        array_unshift( $path, $walk->name );
        $walk = $walk->parent ? get_term( $walk->parent, $taxonomy ) : null;
    }

    $classes = isset( $seed['classes'] ) && is_array( $seed['classes'] ) ? array_values( array_filter( array_map( 'vergeml_filing_name_class', $seed['classes'] ) ) ) : array();
    if ( ! in_array( vergeml_filing_name_class( $term->name ), $classes, true ) ) {
        array_unshift( $classes, vergeml_filing_name_class( $term->name ) );
    }

    $kinds = isset( $seed['kinds'] ) && is_array( $seed['kinds'] ) && $seed['kinds'] ? array_values( array_map( 'sanitize_key', $seed['kinds'] ) ) : vergeml_filing_kinds_of( $term->name );

    // Audience comes from the folder or any ancestor: Shoes under Women is for women.
    $audience = isset( $seed['audience'] ) ? vergeml_filing_audience_of( $seed['audience'] ) : '';
    if ( '' === $audience ) {
        $audience = vergeml_filing_audience_of( implode( ' ', $path ) );
    }

    $matches = isset( $seed['matches'] ) ? sanitize_text_field( (string) $seed['matches'] ) : '';

    /*
     *  The text the vector is made from, in the same labelled shape the
     *  describer's records are embedded in, so the two live in the same part
     *  of the space. The old text was "Shoes. footwear on feet".
     */
    $text = trim( implode( ' / ', $path ) . ( '' !== $matches ? '. ' . $matches : '' ) )
        . ' | object: ' . implode( '; ', $classes )
        . ( '' !== $audience ? ' | audience: ' . $audience : '' );

    $vector = function_exists( 'vergeml_meaning_vector' ) ? vergeml_meaning_vector( $text ) : null;
    if ( ! is_array( $vector ) || ! $vector ) {
        return null;
    }

    $profile = array(
        'version'  => VERGEML_FILING_VERSION,
        'source'   => isset( $seed['classes'] ) ? 'plan' : 'name',
        'path'     => $path,
        'classes'  => $classes,
        'kinds'    => $kinds,
        'audience' => $audience,
        'matches'  => $matches,
        'text'     => $text,
        'vector'   => $vector,
        'built_at' => time(),
    );

    update_term_meta( $term->term_id, VERGEML_FILING_META, $profile );

    return $profile;
}

/** Profiles for a set of terms, keyed by term id. Terms without one are left out. */
function vergeml_filing_profiles( $term_ids, $taxonomy ) {
    $out = array();
    foreach ( (array) $term_ids as $id ) {
        $p = vergeml_filing_profile( (int) $id, $taxonomy );
        if ( is_array( $p ) ) {
            $p['term_id']   = (int) $id;
            $p['parent_id'] = (int) ( get_term( (int) $id, $taxonomy )->parent ?? 0 );
            $out[ (int) $id ] = $p;
        }
    }
    return $out;
}

/** Forget a folder's profile, so the next ask rebuilds it (renamed, moved, re-planned). */
function vergeml_filing_forget( $term_id ) {
    delete_term_meta( (int) $term_id, VERGEML_FILING_META );
}

add_action( 'edited_term', 'vergeml_filing_on_term_change', 10, 3 );
add_action( 'delete_term', 'vergeml_filing_on_term_change', 10, 3 );

function vergeml_filing_on_term_change( $term_id, $tt_id, $taxonomy ) {
    $wanted = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';
    if ( $taxonomy === $wanted ) {
        vergeml_filing_forget( $term_id );
    }
}


/* ------------------------------------------------------------- matching */

/**
 *  How alike two class phrases are: 1 for the same word, a substring, or a
 *  plural of the other; otherwise the cosine of their short-phrase vectors,
 *  which for "footwear" against "shoes" is high and against "logo" is low.
 */
function vergeml_filing_class_match( $a, $b ) {
    $a = trim( mb_strtolower( $a ) );
    $b = trim( mb_strtolower( $b ) );
    if ( '' === $a || '' === $b ) {
        return 0.0;
    }
    if ( $a === $b || rtrim( $a, 's' ) === rtrim( $b, 's' ) ) {
        return 1.0;
    }
    if ( false !== mb_strpos( ' ' . $a . ' ', ' ' . $b . ' ' ) || false !== mb_strpos( ' ' . $b . ' ', ' ' . $a . ' ' ) ) {
        return 0.95;
    }
    if ( ! function_exists( 'vergeml_meaning_vector' ) ) {
        return 0.0;
    }
    $va = vergeml_meaning_vector( $a );
    $vb = vergeml_meaning_vector( $b );
    if ( ! is_array( $va ) || ! is_array( $vb ) ) {
        return 0.0;
    }
    return max( 0.0, (float) vergeml_meaning_similarity( $va, $vb ) );
}

/**
 *  The picture's side of the match, as the caller reads it off the index row.
 *
 *  @param array $row An index row: 'filing' (json), 'kind', 'embedding'.
 *  @return array 'classes', 'kind', 'audience', 'vector'.
 */
function vergeml_filing_facts( $row ) {
    $filing = isset( $row['filing'] ) ? json_decode( (string) $row['filing'], true ) : null;
    $filing = is_array( $filing ) ? $filing : array();
    $kind   = isset( $row['kind'] ) && '' !== (string) $row['kind'] ? sanitize_key( (string) $row['kind'] ) : 'photo';
    return array(
        'classes'  => vergeml_filing_classes_of_object( isset( $filing['object'] ) ? $filing['object'] : '' ),
        'kind'     => $kind,
        'audience' => vergeml_filing_audience_of_picture( isset( $filing['audience'] ) ? $filing['audience'] : '' ),
        'vector'   => isset( $row['embedding'] ) && function_exists( 'vergeml_index_vector_out' ) ? vergeml_index_vector_out( $row['embedding'] ) : null,
    );
}

/**
 *  Pick.
 *
 *  @param array $facts    From vergeml_filing_facts().
 *  @param array $profiles From vergeml_filing_profiles(), keyed by term id.
 *  @return array 'term_id' (0 for none), 'score', 'runner_up', 'runner_score', 'why', 'scores' (term id => score).
 */
function vergeml_filing_pick( $facts, $profiles ) {

    $scores = array();
    $gated  = array();

    foreach ( $profiles as $tid => $p ) {

        // Gate: kind.
        if ( ! in_array( $facts['kind'], (array) $p['kinds'], true ) ) {
            $gated[ $tid ] = 'kind';
            continue;
        }
        // Gate: audience. A gendered folder needs the picture to say so.
        if ( '' !== $p['audience'] && $facts['audience'] !== $p['audience'] ) {
            $gated[ $tid ] = 'audience';
            continue;
        }

        $class = 0.0;
        foreach ( (array) $facts['classes'] as $pc ) {
            foreach ( (array) $p['classes'] as $fc ) {
                $class = max( $class, vergeml_filing_class_match( $pc, $fc ) );
                if ( $class >= 1.0 ) {
                    break 2;
                }
            }
        }
        // The specific phrase against the folder's descriptive phrase, when there is one.
        if ( $class < 0.95 && '' !== $p['matches'] && ! empty( $facts['classes'] ) ) {
            $class = max( $class, 0.9 * vergeml_filing_class_match( $facts['classes'][0], $p['matches'] ) );
        }

        $embed = 0.0;
        if ( is_array( $facts['vector'] ) && is_array( $p['vector'] ) && function_exists( 'vergeml_meaning_similarity' ) ) {
            $embed = max( 0.0, (float) vergeml_meaning_similarity( $p['vector'], $facts['vector'] ) );
        }

        $scores[ $tid ] = VERGEML_FILING_CLASS_WEIGHT * $class + ( 1 - VERGEML_FILING_CLASS_WEIGHT ) * $embed;
    }

    if ( ! $scores ) {
        return array( 'term_id' => 0, 'score' => 0.0, 'runner_up' => 0, 'runner_score' => 0.0, 'why' => 'gated', 'scores' => array(), 'gated' => $gated );
    }

    arsort( $scores );
    $ids  = array_keys( $scores );
    $best = (int) $ids[0];

    /*
     *  Deepest folder that fits. A parent ("Apparel") and its child ("Shoes")
     *  both scoring well is not a tie, it is the child being right: the
     *  child is chosen when it is within a whisker of the parent. The reverse
     *  -- a child scoring well below its parent -- means the picture belongs
     *  to the family but not that member, and the parent stands.
     */
    foreach ( $ids as $cand ) {
        $cand = (int) $cand;
        if ( $cand === $best ) {
            continue;
        }
        if ( vergeml_filing_is_descendant( $cand, $best, $profiles ) && $scores[ $cand ] >= $scores[ $best ] - 0.03 ) {
            $best = $cand;
        }
    }

    // The runner-up that matters is one outside the chosen folder's own line.
    $runner = 0;
    foreach ( $ids as $cand ) {
        $cand = (int) $cand;
        if ( $cand === $best || vergeml_filing_is_descendant( $cand, $best, $profiles ) || vergeml_filing_is_descendant( $best, $cand, $profiles ) ) {
            continue;
        }
        $runner = $cand;
        break;
    }

    $score  = (float) $scores[ $best ];
    $rscore = $runner ? (float) $scores[ $runner ] : 0.0;

    if ( $score < VERGEML_FILING_FLOOR ) {
        return array( 'term_id' => 0, 'score' => $score, 'runner_up' => $runner, 'runner_score' => $rscore, 'why' => 'floor', 'scores' => $scores, 'gated' => $gated, 'nearest' => $best );
    }
    if ( $runner && $score - $rscore < VERGEML_FILING_MARGIN ) {
        return array( 'term_id' => 0, 'score' => $score, 'runner_up' => $runner, 'runner_score' => $rscore, 'why' => 'margin', 'scores' => $scores, 'gated' => $gated, 'nearest' => $best );
    }

    return array( 'term_id' => $best, 'score' => $score, 'runner_up' => $runner, 'runner_score' => $rscore, 'why' => 'ok', 'scores' => $scores, 'gated' => $gated );
}

/** Is $child below $ancestor, going by the parent ids the profiles carry? */
function vergeml_filing_is_descendant( $child, $ancestor, $profiles ) {
    $guard = 0;
    $walk  = isset( $profiles[ $child ] ) ? (int) $profiles[ $child ]['parent_id'] : 0;
    while ( $walk && $guard++ < 10 ) {
        if ( $walk === (int) $ancestor ) {
            return true;
        }
        $walk = isset( $profiles[ $walk ] ) ? (int) $profiles[ $walk ]['parent_id'] : 0;
    }
    return false;
}
