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
const VERGEML_FILING_VERSION = 5; // 2: slash-named folders as paths. 3-4: a rebuild reuses only what a plan said. 5: the plan's first class stays first.

/*
 *  Calibrated on the box, 3 September 2026: with class matching the right
 *  folder scores 0.75-1.0 and the wrong ones 0.2-0.45. The floor sits in the
 *  gap; the margin is what "clearly one folder, not two" means.
 */
const VERGEML_FILING_FLOOR  = 0.55;
const VERGEML_FILING_MARGIN = 0.08;

/*
 *  Above this a placement is 'sure'; between the floor and this it is
 *  'likely'. The word rides with the picture so a person checking the fill
 *  can look at the likely ones first (spec 2026-09-14, §2 Step 3).
 */
const VERGEML_FILING_SURE = 0.70;

/** Term meta: a folder the fill stays out of, both ways. Set by A.3; read here. */
const VERGEML_FILING_LOCKED = '_vergeml_locked';

/** Post meta: who put the picture where it is. 'user' means every fill leaves it alone. */
const VERGEML_FILING_PLACED_BY = '_vergeml_placed_by';

/** How much the class match weighs against the vector. */
const VERGEML_FILING_CLASS_WEIGHT = 0.75;

/*
 *  Below this, a picture plainly does not match the folder it is sitting in,
 *  and "nothing else fits either" is no reason to leave it there. Out it comes,
 *  to unfiled, which is the truthful place. Between this and the floor the
 *  old placement stands: the matcher has no evidence either way.
 */
const VERGEML_FILING_MISFIT = 0.40;


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

    /*
     *  Only what a plan said is worth carrying into a rebuild, and it is kept
     *  as the plan said it, apart from everything derived. Version 2 seeded a
     *  rebuild from the whole old profile and then stamped the result "plan",
     *  so version 3 trusted it and the leaf's own path came back as a class.
     */
    $seed = is_array( $meta ) && isset( $meta['plan'] ) && is_array( $meta['plan'] ) ? $meta['plan'] : array();

    return vergeml_filing_profile_build( $term, $taxonomy, $seed );
}

/**
 *  Build and store. $seed carries what a plan said (classes, kinds, audience,
 *  matches); anything it did not say is derived from the name and the path.
 */
function vergeml_filing_profile_build( $term, $taxonomy, $seed = array() ) {

    /*
     *  The path, from the real parents -- and from the name itself when the
     *  name is a path. An older planner answered "Apparel / Men / Shoes" as a
     *  folder name, and the plugin made exactly that: one flat folder with
     *  slashes in it. Those exist on real sites now. Read as a path, the leaf
     *  is the class, the segments carry the audience, and siblings under one
     *  parent are family rather than rivals.
     */
    $path  = array();
    $walk  = $term;
    $guard = 0;
    while ( $walk && ! is_wp_error( $walk ) && $guard++ < 10 ) {
        $segments = preg_split( '/\s*\/\s*/u', (string) $walk->name );
        $segments = array_values( array_filter( array_map( 'trim', (array) $segments ), 'strlen' ) );
        $path     = array_merge( $segments ? $segments : array( (string) $walk->name ), $path );
        $walk     = $walk->parent ? get_term( $walk->parent, $taxonomy ) : null;
    }
    $leaf = $path ? (string) end( $path ) : (string) $term->name;

    /*
     *  The plan's first class is what the folder is for, so it stays first;
     *  the name goes last as a fallback. With the name in front, "Bikes and
     *  cycling" ranked bicycle second and tied with Objects, which also holds
     *  bicycles, and every road bike was too close to call.
     */
    $classes = isset( $seed['classes'] ) && is_array( $seed['classes'] ) ? array_values( array_filter( array_map( 'vergeml_filing_name_class', $seed['classes'] ) ) ) : array();
    if ( ! in_array( vergeml_filing_name_class( $leaf ), $classes, true ) ) {
        $classes[] = vergeml_filing_name_class( $leaf );
    }

    $kinds = isset( $seed['kinds'] ) && is_array( $seed['kinds'] ) && $seed['kinds'] ? array_values( array_map( 'sanitize_key', $seed['kinds'] ) ) : vergeml_filing_kinds_of( $leaf );

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

    // What the plan said, kept verbatim for the next rebuild; nothing derived.
    $plan = array_filter( array_intersect_key( (array) $seed, array_flip( array( 'classes', 'kinds', 'audience', 'matches' ) ) ) );

    $profile = array(
        'version'  => VERGEML_FILING_VERSION,
        'source'   => $plan ? 'plan' : 'name',
        'plan'     => $plan,
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
            $p['locked']    = (bool) get_term_meta( (int) $id, VERGEML_FILING_LOCKED, true );
            $out[ (int) $id ] = $p;
        }
    }
    return vergeml_filing_settle_claims( $out );
}

/**
 *  One folder per first class.
 *
 *  The planner describes a folder by what it holds, and a folder that held
 *  fifteen bikes on the day it was profiled put "bicycle" first -- as did the
 *  Bikes folder made the next hour. Two folders with the same first class tie
 *  by construction and every road bike was too close to call. So a first
 *  class is a claim, and among the folders that make it the most specific
 *  one keeps it: the fewest classes, then the deeper path. The others still
 *  hold that class, second-rank, as what they also take.
 */
function vergeml_filing_settle_claims( $profiles ) {
    $claims = array();
    foreach ( $profiles as $tid => $p ) {
        if ( ! empty( $p['classes'] ) ) {
            $claims[ (string) $p['classes'][0] ][] = (int) $tid;
        }
    }
    foreach ( $claims as $class => $tids ) {
        if ( count( $tids ) < 2 ) {
            continue;
        }
        usort( $tids, function ( $a, $b ) use ( $profiles ) {
            $ca = count( $profiles[ $a ]['classes'] );
            $cb = count( $profiles[ $b ]['classes'] );
            if ( $ca !== $cb ) {
                return $ca <=> $cb;
            }
            return count( $profiles[ $b ]['path'] ) <=> count( $profiles[ $a ]['path'] );
        } );
        foreach ( array_slice( $tids, 1 ) as $loser ) {
            $classes = array_values( array_filter( $profiles[ $loser ]['classes'], function ( $c ) use ( $class ) { return $c !== $class; } ) );
            $classes[] = $class;
            $profiles[ $loser ]['classes'] = $classes;
        }
    }
    return $profiles;
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
 *  @param array $row An index row: 'filing' (json), 'kind', 'embedding'; 'placed_by' and
 *                    'in_locked' (sits in a locked folder) when the caller joined them.
 *  @return array 'classes', 'kind', 'audience', 'vector', 'placed_by', 'in_locked'.
 */
function vergeml_filing_facts( $row ) {
    $filing = isset( $row['filing'] ) ? json_decode( (string) $row['filing'], true ) : null;
    $filing = is_array( $filing ) ? $filing : array();
    $kind   = isset( $row['kind'] ) && '' !== (string) $row['kind'] ? sanitize_key( (string) $row['kind'] ) : 'photo';
    return array(
        'classes'   => vergeml_filing_classes_of_object( isset( $filing['object'] ) ? $filing['object'] : '' ),
        'kind'      => $kind,
        'audience'  => vergeml_filing_audience_of_picture( isset( $filing['audience'] ) ? $filing['audience'] : '' ),
        'vector'    => isset( $row['embedding'] ) && function_exists( 'vergeml_index_vector_out' ) ? vergeml_index_vector_out( $row['embedding'] ) : null,
        'placed_by' => isset( $row['placed_by'] ) ? (string) $row['placed_by'] : '',
        'in_locked' => ! empty( $row['in_locked'] ),
    );
}

/**
 *  Pick: one of three outcomes.
 *
 *    fits      best clears the floor and beats the runner-up by the margin.
 *              Placed there; 'sure' from 0.70, 'likely' below it.
 *    siblings  best and runner-up are two children of one folder and too
 *              close to call between them. Placed in that parent, 'likely',
 *              and the parent is asked about once for the whole group.
 *    nothing   below the floor, every folder gated, or too close to call
 *              between two folders that are not siblings. Not placed; the
 *              run groups these and asks.
 *
 *  Abstaining between two siblings was the largest hole in the 2026-09-14
 *  Move: 232 of 513 unfiled pictures were a data-centre photo scoring close
 *  on Server racks and Cooling and going nowhere. The parent is the honest
 *  answer to "which of these two", and it is a folder.
 *
 *  Three things are never picked. A locked folder (VERGEML_FILING_LOCKED) is
 *  never filed into; a picture sitting in one is never filed out of it ('nothing',
 *  why 'locked'); and a picture the user placed by hand gets no new folder
 *  ('nothing', why 'placed'). Every fill leaves those two exactly where they are.
 *
 *  @param array $facts    From vergeml_filing_facts().
 *  @param array $profiles From vergeml_filing_profiles(), keyed by term id.
 *  @return array 'outcome', 'term_id' (0 for none; the parent for siblings),
 *                'parent_id', 'score', 'runner_up', 'runner_score',
 *                'confidence' ('sure' | 'likely' | ''), 'why' ('ok' |
 *                'siblings' | 'floor' | 'margin' | 'gated' | 'placed' | 'locked'),
 *                'children' (siblings: the two), 'nearest' (nothing: the
 *                folder it came closest to), 'scores' (term id => score),
 *                'gated' (term id => 'kind' | 'audience' | 'locked').
 */
function vergeml_filing_pick( $facts, $profiles ) {

    if ( isset( $facts['placed_by'] ) && 'user' === $facts['placed_by'] ) {
        return vergeml_filing_outcome( 'nothing', 'placed', array( 'scores' => array(), 'gated' => array() ) );
    }
    if ( ! empty( $facts['in_locked'] ) ) {
        return vergeml_filing_outcome( 'nothing', 'locked', array( 'scores' => array(), 'gated' => array() ) );
    }

    $scores = array();
    $gated  = array();

    foreach ( $profiles as $tid => $p ) {

        // Locked: the fill stays out of it.
        if ( ! empty( $p['locked'] ) ) {
            $gated[ $tid ] = 'locked';
            continue;
        }
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

        /*
         *  A folder's first class is what it is for; the ones after are what
         *  it also holds. "Bikes and cycling" (bicycle, person) and "Objects"
         *  (object, bicycle, gadget) both claimed a road bike outright and the
         *  picture was too close to call. The folder that is *for* bicycles
         *  wins by the weight a secondary class does not carry.
         */
        $class = 0.0;
        foreach ( (array) $facts['classes'] as $pc ) {
            foreach ( array_values( (array) $p['classes'] ) as $rank => $fc ) {
                $weight = 0 === $rank ? 1.0 : 0.85;
                $class  = max( $class, $weight * vergeml_filing_class_match( $pc, $fc ) );
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
        return vergeml_filing_outcome( 'nothing', 'gated', array( 'scores' => array(), 'gated' => $gated ) );
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
    $common = array( 'score' => $score, 'runner_up' => $runner, 'runner_score' => $rscore, 'scores' => $scores, 'gated' => $gated );

    if ( $score < VERGEML_FILING_FLOOR ) {
        return vergeml_filing_outcome( 'nothing', 'floor', $common + array( 'nearest' => $best ) );
    }

    if ( $runner && $score - $rscore < VERGEML_FILING_MARGIN ) {
        /*
         *  Two children of one folder, too close to call between them: the
         *  parent takes the picture. Decided on the paths, like descent is,
         *  and only when the parent is a folder the fill may use -- two
         *  slash-named orphans under a parent that does not exist, or a
         *  locked one, are still nothing.
         */
        $parent = vergeml_filing_parent_of( $best, $profiles );
        if ( $parent && $parent === vergeml_filing_parent_of( $runner, $profiles ) && empty( $profiles[ $parent ]['locked'] ) ) {
            return vergeml_filing_outcome( 'siblings', 'siblings', $common + array(
                'term_id'    => $parent,
                'parent_id'  => $parent,
                'confidence' => 'likely',
                'children'   => array( $best, $runner ),
            ) );
        }
        return vergeml_filing_outcome( 'nothing', 'margin', $common + array( 'nearest' => $best ) );
    }

    return vergeml_filing_outcome( 'fits', 'ok', $common + array(
        'term_id'    => $best,
        'parent_id'  => vergeml_filing_parent_of( $best, $profiles ),
        'confidence' => $score >= VERGEML_FILING_SURE ? 'sure' : 'likely',
    ) );
}

/** The pick's answer in one shape, whatever it is. */
function vergeml_filing_outcome( $outcome, $why, $extra = array() ) {
    return array_merge( array(
        'outcome'      => $outcome,
        'term_id'      => 0,
        'parent_id'    => 0,
        'score'        => 0.0,
        'runner_up'    => 0,
        'runner_score' => 0.0,
        'confidence'   => '',
        'why'          => $why,
        'scores'       => array(),
        'gated'        => array(),
    ), $extra );
}

/**
 *  The folder above $tid, as a term id in $profiles, or 0.
 *
 *  By path first: the preview scores a draft whose folders have no term ids
 *  yet, so its profiles carry no parent_id, and a slash-named folder's parent
 *  is a path segment rather than a term. The stored parent_id is the answer
 *  when it names a profile the paths did not.
 */
function vergeml_filing_parent_of( $tid, $profiles ) {
    if ( ! isset( $profiles[ $tid ]['path'] ) || count( (array) $profiles[ $tid ]['path'] ) < 2 ) {
        return 0;
    }
    $want = array_map( 'mb_strtolower', array_slice( (array) $profiles[ $tid ]['path'], 0, -1 ) );
    foreach ( $profiles as $pid => $p ) {
        if ( (int) $pid !== (int) $tid && isset( $p['path'] ) && array_map( 'mb_strtolower', (array) $p['path'] ) === $want ) {
            return (int) $pid;
        }
    }
    $stored = isset( $profiles[ $tid ]['parent_id'] ) ? (int) $profiles[ $tid ]['parent_id'] : 0;
    return $stored && isset( $profiles[ $stored ] ) ? $stored : 0;
}


/* ------------------------------------------------------------- counting */

/**
 *  Every picture in $rows picked, and the outcomes counted.
 *
 *  The one function both the preview and the run read their numbers from.
 *  Until 2026-09-14 the preview counted against the draft's own classes and
 *  the run re-profiled every folder through the planner and counted against
 *  that: 816 on the button, 487 in the library. Now the run is this over each
 *  slice, acting on the picks, and the preview is this over the whole
 *  library, reading the counts -- same profiles, same pick, same tally.
 *
 *  @param array $profiles Keyed by term id (real or the preview's own).
 *  @param array $rows     Index rows (attachment_id, filing, kind, embedding, placed_by).
 *  @param float $deadline microtime to stop at; 0 for none. Past it the answer
 *                         is null, never a count over part of the library.
 *  @return array|null 'picks' (attachment id => pick), 'counts' (vergeml_filing_tally_fresh()).
 */
function vergeml_filing_count( $profiles, $rows, $deadline = 0.0 ) {
    $picks  = array();
    $counts = vergeml_filing_tally_fresh();
    foreach ( (array) $rows as $row ) {
        if ( $deadline > 0 && microtime( true ) > $deadline ) {
            return null;
        }
        $id           = (int) $row['attachment_id'];
        $picks[ $id ] = vergeml_filing_pick( vergeml_filing_facts( $row ), $profiles );
        vergeml_filing_tally( $counts, $picks[ $id ] );
    }
    return array( 'picks' => $picks, 'counts' => $counts );
}

/** The tally's shape: what the Fill step shows as pills, and where things land. */
function vergeml_filing_tally_fresh() {
    return array(
        'looked'   => 0,
        'fits'     => 0,
        'siblings' => 0,
        'nothing'  => 0,
        'kept'     => 0, // Placed by the user, or sitting in a locked folder: looked at, left alone, not asked about.
        'sure'     => 0,
        'likely'   => 0,
        'why'      => array( 'floor' => 0, 'margin' => 0, 'gated' => 0 ),
        'by_term'  => array(),
    );
}

/** One pick into the tally. */
function vergeml_filing_tally( &$counts, $pick ) {
    $counts['looked']++;
    if ( vergeml_filing_kept( $pick ) ) {
        $counts['kept']++;
        return;
    }
    $counts[ $pick['outcome'] ]++;
    if ( '' !== $pick['confidence'] ) {
        $counts[ $pick['confidence'] ]++;
    }
    if ( $pick['term_id'] ) {
        $counts['by_term'][ (int) $pick['term_id'] ] = isset( $counts['by_term'][ (int) $pick['term_id'] ] ) ? $counts['by_term'][ (int) $pick['term_id'] ] + 1 : 1;
    } elseif ( isset( $counts['why'][ $pick['why'] ] ) ) {
        $counts['why'][ $pick['why'] ]++;
    }
}

/** A pick the fill acts on in no way: placed by the user, or in a locked folder. Looked at, kept, no row. */
function vergeml_filing_kept( $pick ) {
    return isset( $pick['why'] ) && ( 'placed' === $pick['why'] || 'locked' === $pick['why'] );
}

/** Two tallies into one: the run adds each slice's to the state's. */
function vergeml_filing_tally_add( $a, $b ) {
    foreach ( array( 'looked', 'fits', 'siblings', 'nothing', 'kept', 'sure', 'likely' ) as $k ) {
        $a[ $k ] = (int) ( isset( $a[ $k ] ) ? $a[ $k ] : 0 ) + (int) ( isset( $b[ $k ] ) ? $b[ $k ] : 0 );
    }
    foreach ( array( 'why', 'by_term' ) as $map ) {
        foreach ( (array) ( isset( $b[ $map ] ) ? $b[ $map ] : array() ) as $k => $n ) {
            $a[ $map ][ $k ] = (int) ( isset( $a[ $map ][ $k ] ) ? $a[ $map ][ $k ] : 0 ) + (int) $n;
        }
    }
    return $a;
}

/**
 *  Is $child below $ancestor? Decided on the paths the profiles carry, which
 *  come from real parents and from slash-named folders alike, so
 *  "Apparel / Men / Shoes" counts as below "Apparel" whichever way it was made.
 */
function vergeml_filing_is_descendant( $child, $ancestor, $profiles ) {
    if ( ! isset( $profiles[ $child ]['path'], $profiles[ $ancestor ]['path'] ) ) {
        return false;
    }
    $c = array_map( 'mb_strtolower', (array) $profiles[ $child ]['path'] );
    $a = array_map( 'mb_strtolower', (array) $profiles[ $ancestor ]['path'] );
    if ( count( $a ) >= count( $c ) ) {
        return false;
    }
    return array_slice( $c, 0, count( $a ) ) === $a;
}


/* -------------------------------------------------------------- residue */

/*
 *  A group of residue is asked about as one question, so it has to be worth
 *  a question: five pictures. Under that it joins the nearest group it looks
 *  like (cosine of centroids over NEAR); still under three after that, it is
 *  one of the pictures the fill "can't read", asked about together. Eight
 *  pictures is what a question shows.
 */
const VERGEML_FILING_GROUP_MIN  = 5;
const VERGEML_FILING_GROUP_TINY = 3;
const VERGEML_FILING_GROUP_NEAR = 0.5;
const VERGEML_FILING_SAMPLE     = 8;

/** Term meta and slug of the one folder "leave them" leaves things in. Locked: the fill never files into or out of it. */
const VERGEML_FILING_TO_SORT_SLUG = 'to-sort';

/**
 *  The residue, grouped.
 *
 *  By the specific phrase the describer wrote first ("robot arm" of "robot
 *  arm; machinery"), plural folded; then every group under GROUP_MIN joins
 *  the nearest group by centroid when it is near enough, largest first;
 *  then everything still under GROUP_TINY, with the pictures that have no
 *  class or no vector, is the one unreadable group, last.
 *
 *  @param array $facts attachment id => vergeml_filing_facts().
 *  @return array[] Each: 'class', 'classes' (class => n, biggest first),
 *                  'ids', 'count', 'centroid' (vector|null), 'unreadable'.
 */
function vergeml_filing_residue_groups( $facts ) {

    $groups = array();
    $pool   = array();

    foreach ( (array) $facts as $id => $f ) {
        $class = isset( $f['classes'][0] ) ? vergeml_filing_group_key( $f['classes'][0] ) : '';
        if ( '' === $class ) {
            $pool[] = (int) $id;
            continue;
        }
        if ( ! isset( $groups[ $class ] ) ) {
            $groups[ $class ] = array( 'class' => $class, 'classes' => array( $class => 0 ), 'ids' => array(), 'vectors' => array() );
        }
        $groups[ $class ]['ids'][] = (int) $id;
        $groups[ $class ]['classes'][ $class ]++;
        if ( is_array( $f['vector'] ) && $f['vector'] ) {
            $groups[ $class ]['vectors'][] = $f['vector'];
        }
    }

    foreach ( $groups as $k => $g ) {
        $groups[ $k ]['centroid'] = vergeml_filing_centroid( $g['vectors'] );
        unset( $groups[ $k ]['vectors'] );
    }

    // Small groups, largest first, each into the nearest group still standing.
    $small = array_filter( array_keys( $groups ), function ( $k ) use ( $groups ) { return count( $groups[ $k ]['ids'] ) < VERGEML_FILING_GROUP_MIN; } );
    usort( $small, function ( $a, $b ) use ( $groups ) { return count( $groups[ $b ]['ids'] ) <=> count( $groups[ $a ]['ids'] ) ?: min( $groups[ $a ]['ids'] ) <=> min( $groups[ $b ]['ids'] ); } );

    foreach ( $small as $k ) {
        if ( ! isset( $groups[ $k ] ) || ! is_array( $groups[ $k ]['centroid'] ) ) {
            continue;
        }
        $best  = '';
        $score = VERGEML_FILING_GROUP_NEAR;
        foreach ( $groups as $other => $g ) {
            if ( $other === $k || ! is_array( $g['centroid'] ) ) {
                continue;
            }
            $s = (float) vergeml_meaning_similarity( $g['centroid'], $groups[ $k ]['centroid'] );
            if ( $s >= $score ) {
                $score = $s;
                $best  = $other;
            }
        }
        if ( '' === $best ) {
            continue;
        }
        $groups[ $best ]['ids'] = array_merge( $groups[ $best ]['ids'], $groups[ $k ]['ids'] );
        foreach ( $groups[ $k ]['classes'] as $class => $n ) {
            $groups[ $best ]['classes'][ $class ] = ( isset( $groups[ $best ]['classes'][ $class ] ) ? $groups[ $best ]['classes'][ $class ] : 0 ) + $n;
        }
        unset( $groups[ $k ] );
    }

    // What is still too small to ask about on its own.
    $out = array();
    foreach ( $groups as $g ) {
        if ( count( $g['ids'] ) < VERGEML_FILING_GROUP_TINY ) {
            $pool = array_merge( $pool, $g['ids'] );
            continue;
        }
        arsort( $g['classes'] );
        $g['count'] = count( $g['ids'] );
        $g['unreadable'] = false;
        $out[] = $g;
    }
    usort( $out, function ( $a, $b ) { return $b['count'] <=> $a['count'] ?: min( $a['ids'] ) <=> min( $b['ids'] ); } );

    if ( $pool ) {
        sort( $pool );
        $out[] = array( 'class' => '', 'classes' => array(), 'ids' => array_values( $pool ), 'count' => count( $pool ), 'centroid' => null, 'unreadable' => true );
    }

    return $out;
}

/** "Robot arms" and "robot arm" are one group. */
function vergeml_filing_group_key( $phrase ) {
    $p = trim( mb_strtolower( (string) $phrase ) );
    return mb_strlen( $p ) > 3 && 's' === mb_substr( $p, -1 ) && 's' !== mb_substr( $p, -2, 1 ) ? mb_substr( $p, 0, -1 ) : $p;
}

/** The mean of some vectors, or null with none. */
function vergeml_filing_centroid( $vectors ) {
    if ( ! $vectors ) {
        return null;
    }
    $sum = array();
    foreach ( $vectors as $v ) {
        foreach ( (array) $v as $d => $x ) {
            $sum[ $d ] = ( isset( $sum[ $d ] ) ? $sum[ $d ] : 0.0 ) + (float) $x;
        }
    }
    $n = count( $vectors );
    foreach ( $sum as $d => $x ) {
        $sum[ $d ] = $x / $n;
    }
    return $sum;
}

/**
 *  The questions, from the groups and the sibling tally.
 *
 *  One per parent that took pictures two of its children tied over, first;
 *  then one per residue group, by size; the unreadable one last. The shape
 *  is what /guide/questions serves and /guide/answer takes:
 *
 *    { id, kind: 'siblings'|'residue', term_id, children, count, sample,
 *      ids, name, class, unreadable, answers: [...] }
 *
 *  The answers are the words a question offers, and vergeml_filing_answer_plan()
 *  refuses any other. 'put-in:<term>' is offered only when a folder is near
 *  enough to name.
 *
 *  @param array $groups   vergeml_filing_residue_groups().
 *  @param array $siblings parent term id => [ 'ids' => attachment => best child, 'children' => child => n ].
 *  @param array $names    group index => name from the naming call; missing = the class word.
 *  @param array $nearest  group index => nearest folder term id, 0 for none.
 */
function vergeml_filing_questions( $groups, $siblings, $names = array(), $nearest = array() ) {

    $out = array();

    foreach ( (array) $siblings as $parent => $s ) {
        $ids      = isset( $s['ids'] ) ? (array) $s['ids'] : array();
        $children = isset( $s['children'] ) ? (array) $s['children'] : array();
        arsort( $children );
        $out[] = array(
            'id'         => 's:' . (int) $parent,
            'kind'       => 'siblings',
            'term_id'    => (int) $parent,
            'children'   => array_map( 'intval', array_slice( array_keys( $children ), 0, 2 ) ),
            'count'      => count( $ids ),
            'sample'     => array_map( 'intval', array_slice( array_keys( $ids ), 0, VERGEML_FILING_SAMPLE ) ),
            'ids'        => array_map( 'intval', $ids ),
            'name'       => '',
            'class'      => '',
            'unreadable' => false,
            'answers'    => array( 'keep-parent', 'split', 'show-me' ),
        );
    }

    foreach ( (array) $groups as $i => $g ) {
        $near = isset( $nearest[ $i ] ) ? (int) $nearest[ $i ] : 0;
        if ( ! empty( $g['unreadable'] ) ) {
            $name    = '';
            $answers = array( 'leave', 'show-me' );
        } else {
            $name    = isset( $names[ $i ] ) && '' !== trim( (string) $names[ $i ] ) ? trim( (string) $names[ $i ] ) : vergeml_filing_class_name( $g['class'] );
            $answers = array_values( array_filter( array( 'new-folder', $near ? 'put-in:' . $near : '', 'leave', 'show-me' ) ) );
        }
        $out[] = array(
            'id'         => 'r:' . (int) $i,
            'kind'       => 'residue',
            'term_id'    => 0,
            'children'   => array(),
            'count'      => (int) $g['count'],
            'sample'     => array_map( 'intval', array_slice( (array) $g['ids'], 0, VERGEML_FILING_SAMPLE ) ),
            'ids'        => array_map( 'intval', (array) $g['ids'] ),
            'name'       => $name,
            'class'      => (string) $g['class'],
            'unreadable' => ! empty( $g['unreadable'] ),
            'answers'    => $answers,
        );
    }

    return $out;
}

/** A class word as a folder name: "keynote speaker" -> "Keynote speaker". */
function vergeml_filing_class_name( $class ) {
    $c = trim( (string) $class );
    return '' === $c ? '' : mb_strtoupper( mb_substr( $c, 0, 1 ) ) . mb_substr( $c, 1 );
}

/**
 *  What an answer does, as a plan the executor carries out and a suite can
 *  check on a map. Null for an answer the question does not offer.
 *
 *  'moves' is attachment => term id, or 'new' (the folder 'make' names) or
 *  'to-sort' (the To sort folder). 'placed_by' says the pictures are the
 *  user's after this: a folder they chose is never re-filed. 'show' carries
 *  the ids for "show me", which answers nothing.
 */
function vergeml_filing_answer_plan( $q, $answer ) {

    $answer = (string) $answer;
    if ( ! is_array( $q ) || ! in_array( $answer, (array) $q['answers'], true ) ) {
        return null;
    }

    $plan = array( 'answer' => $answer, 'moves' => array(), 'make' => null, 'placed_by' => false, 'show' => null, 'answered' => true );

    if ( 'show-me' === $answer ) {
        $plan['show']     = array_values( (array) $q['ids'] );
        $plan['answered'] = false;
        return $plan;
    }

    if ( 'siblings' === $q['kind'] ) {
        if ( 'split' === $answer ) {
            foreach ( (array) $q['ids'] as $id => $best ) {
                $plan['moves'][ (int) $id ] = (int) $best;
            }
        }
        return $plan; // keep-parent: they are in the parent already.
    }

    $ids = array_values( (array) $q['ids'] );

    if ( 'new-folder' === $answer ) {
        $plan['make']      = (string) $q['name'];
        $plan['placed_by'] = true;
        foreach ( $ids as $id ) {
            $plan['moves'][ (int) $id ] = 'new';
        }
    } elseif ( 0 === strpos( $answer, 'put-in:' ) ) {
        $plan['placed_by'] = true;
        foreach ( $ids as $id ) {
            $plan['moves'][ (int) $id ] = (int) substr( $answer, 7 );
        }
    } elseif ( 'leave' === $answer ) {
        foreach ( $ids as $id ) {
            $plan['moves'][ (int) $id ] = 'to-sort';
        }
    }

    return $plan;
}


/* ------------------------------------------- profiles from the planner */

/**
 *  Ask the planner what each existing folder takes.
 *
 *  A folder somebody made by hand knows only its leaf name as a class, and
 *  "blazer; outerwear" never reaches a folder called Apparel on that alone
 *  (0.47 against a floor of 0.55, measured on the box). The planner can say
 *  what belongs in "Apparel" -- clothing, outerwear, footwear, apparel -- in
 *  one cheap call for the whole tree. Its answer is kept as the folder's plan
 *  and the profile rebuilt from it; folders the planner does not name are
 *  left as they were.
 *
 *  @param string $taxonomy
 *  @param bool   $force   Re-ask for folders that already have a plan.
 *  @return int|WP_Error  How many folders were profiled.
 */
function vergeml_filing_profile_existing( $taxonomy, $force = false ) {

    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
    if ( is_wp_error( $terms ) ) {
        return $terms;
    }

    // The tree as name + parent path, and which terms still want a plan.
    $by_id   = array();
    $current = array();
    $want    = array();
    foreach ( $terms as $t ) {
        $by_id[ (int) $t->term_id ] = $t;
    }
    foreach ( $terms as $t ) {
        $p = vergeml_filing_profile( (int) $t->term_id, $taxonomy );
        if ( ! is_array( $p ) ) {
            continue;
        }
        $path      = (array) $p['path'];
        $leaf      = (string) end( $path );
        $parent    = implode( ' / ', array_slice( $path, 0, -1 ) );
        $current[] = array( 'name' => $leaf, 'parent' => $parent, 'count' => (int) $t->count );
        if ( $force || 'plan' !== $p['source'] ) {
            $want[ mb_strtolower( $parent . ' / ' . $leaf ) ] = (int) $t->term_id;
        }
    }
    if ( ! $want ) {
        return 0;
    }

    $seeds = vergeml_filing_profile_ask( $current );
    if ( is_wp_error( $seeds ) ) {
        return $seeds;
    }

    $done = 0;
    foreach ( $seeds as $key => $seed ) {
        if ( isset( $want[ $key ], $by_id[ $want[ $key ] ] ) && is_array( vergeml_filing_profile_build( $by_id[ $want[ $key ] ], $taxonomy, $seed ) ) ) {
            $done++;
        }
    }
    return $done;
}

/**
 *  The planner's one profiling call, over a tree given as names.
 *
 *  Shared by the folders that exist (above) and by confirm (core/guide.php),
 *  which asks about a draft's folders before some of them exist -- a pasted
 *  tree -- and keeps the answer in the draft for the Move to seed. Metered on
 *  the service like an embed, never a credit.
 *
 *  @param array $current [ { name, parent (a path, "A / B"), count } ] -- the whole tree, so the planner sees the shape.
 *  @return array|WP_Error lowercase "parent / name" => seed { classes, kinds, audience, matches }; folders the
 *                         planner gave no classes are left out.
 */
function vergeml_filing_profile_ask( $current ) {

    if ( ! function_exists( 'vergeml_ai_settings' ) ) {
        return new WP_Error( 'no_ai', 'AI not loaded.' );
    }
    $settings = vergeml_ai_settings();
    $licence  = vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
    if ( '' === $licence ) {
        return new WP_Error( 'no_licence', __( 'No licence key.', 'vergelabs-media-library' ) );
    }

    $response = wp_remote_post(
        vergeml_ai_service_url() . '/folders',
        array(
            'timeout'   => 60,
            'headers'   => array( 'Content-Type' => 'application/json' ),
            'sslverify' => true,
            'body'      => wp_json_encode( array(
                'license_key' => $licence,
                'site'        => home_url(),
                'mode'        => 'profile',
                'instruction' => 'profile',
                'current'     => array_values( (array) $current ),
                'samples'     => function_exists( 'vergeml_talk_samples' ) ? vergeml_talk_samples() : array(),
            ) ),
        )
    );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( 200 !== $code || ! is_array( $data ) || empty( $data['folders'] ) ) {
        return new WP_Error( 'vergeml_ai_service_' . $code, is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : 'HTTP ' . $code );
    }

    $out = array();
    foreach ( (array) $data['folders'] as $f ) {
        if ( ! is_array( $f ) || empty( $f['name'] ) ) {
            continue;
        }
        $seed = array(
            'classes'  => isset( $f['classes'] ) && is_array( $f['classes'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $f['classes'] ) ) ) : array(),
            'kinds'    => isset( $f['kinds'] ) && is_array( $f['kinds'] ) ? array_values( array_filter( array_map( 'sanitize_key', $f['kinds'] ) ) ) : array(),
            'audience' => isset( $f['audience'] ) ? sanitize_text_field( (string) $f['audience'] ) : '',
            'matches'  => isset( $f['matches'] ) ? sanitize_text_field( (string) $f['matches'] ) : '',
        );
        if ( ! $seed['classes'] ) {
            continue; // Nothing worth keeping over the name.
        }
        $out[ mb_strtolower( ( isset( $f['parent'] ) ? (string) $f['parent'] : '' ) . ' / ' . (string) $f['name'] ) ] = $seed;
    }
    return $out;
}
