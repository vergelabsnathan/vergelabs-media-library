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

/** A class match by phrase vectors alone counts from here up; below, two phrases are simply not alike (C.4). */
const VERGEML_FILING_CLASS_COSINE_FLOOR = 0.6;

/** Term meta: the profile a re-profiling replaced, for a day's Restore (C.4). */
const VERGEML_FILING_META_PREV = '_vergeml_filing_profile_prev';

/*
 *  The profile ask, in batches (C.5). The service answers at most this many
 *  folders a call (its PROFILE_BATCH: the planner returns every folder it
 *  is given and sixty fill the output budget), and until 2026-09-16 it took
 *  the first sixty of a bigger ask and said nothing -- a 318-folder catalogue
 *  came back with 258 folders unprofiled. The three numbers mirror
 *  service/lib/profile-price.ts and are what the button says before the
 *  press: the first hundred folders of an ask are free, the rest one credit
 *  per six, rounded up per batch.
 */
const VERGEML_FILING_PROFILE_BATCH      = 60;
const VERGEML_FILING_PROFILE_FREE       = 100;
const VERGEML_FILING_PROFILE_PER_CREDIT = 6;

/**
 *  The ask cut into what the service takes at once: [ ['offset', 'total', 'current'], ... ].
 *  $from and $total place a partial ask inside a bigger one (the confirm sends a batch a call).
 */
function vergeml_filing_profile_batches( $current, $from = 0, $total = null ) {
    $current = array_values( (array) $current );
    $from    = max( 0, (int) $from );
    $total   = null === $total ? $from + count( $current ) : max( (int) $total, $from + count( $current ) );
    $out     = array();
    foreach ( array_chunk( $current, VERGEML_FILING_PROFILE_BATCH ) as $i => $chunk ) {
        $out[] = array( 'offset' => $from + $i * VERGEML_FILING_PROFILE_BATCH, 'total' => $total, 'current' => $chunk );
    }
    return $out;
}

/** Credits one batch costs: its folders past the free hundred of the whole ask (the service's own arithmetic). */
function vergeml_filing_profile_charge( $offset, $count, $total ) {
    $start = max( 0, (int) $offset );
    $end   = min( max( 0, (int) $total ), $start + max( 0, (int) $count ) );
    $paid  = max( 0, $end - max( $start, VERGEML_FILING_PROFILE_FREE ) );
    return (int) ceil( $paid / VERGEML_FILING_PROFILE_PER_CREDIT );
}

/** What an ask about this many folders costs, batch by batch. */
function vergeml_filing_profile_credits( $folders ) {
    $folders = max( 0, (int) $folders );
    $credits = 0;
    for ( $offset = 0; $offset < $folders; $offset += VERGEML_FILING_PROFILE_BATCH ) {
        $credits += vergeml_filing_profile_charge( $offset, min( VERGEML_FILING_PROFILE_BATCH, $folders - $offset ), $folders );
    }
    return $credits;
}

/*
 *  Below this, a picture plainly does not match the folder it is sitting in,
 *  and "nothing else fits either" is no reason to leave it there. Out it comes,
 *  to unfiled, which is the truthful place. Between this and the floor the
 *  old placement stands: the matcher has no evidence either way.
 */
const VERGEML_FILING_MISFIT = 0.40;

/**
 *  The word on a picture: 'by you', 'sure', 'likely', or '' (spec §3, the
 *  confidence pill). A folder the person chose -- a drag, a spoken command,
 *  an answer that named a folder -- is theirs whatever the matcher scored;
 *  otherwise the word is the fill's own, read back off the move that put the
 *  picture where it is ('ok' by score, 'siblings' always likely).
 *
 *  @param int        $attachment_id
 *  @param array|null $move  The moves row (why, score) that speaks for the folder, or null.
 */
function vergeml_filing_confidence( $attachment_id, $move ) {

    if ( 'user' === (string) get_post_meta( (int) $attachment_id, VERGEML_FILING_PLACED_BY, true ) ) {
        return 'by you';
    }
    $why = is_array( $move ) && isset( $move['why'] ) ? (string) $move['why'] : '';
    if ( 'by hand' === $why || 'user' === $why ) {
        return 'by you';
    }
    if ( 'siblings' === $why ) {
        return 'likely';
    }
    if ( 'ok' === $why ) {
        return ( isset( $move['score'] ) && (float) $move['score'] >= VERGEML_FILING_SURE ) ? 'sure' : 'likely';
    }
    return '';
}


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
    /*
     *  The seed, cleaned before it is trusted (C.4): a kind word answered as
     *  a class moves to kinds, and a class some other folder already holds
     *  first is dropped -- a class belongs to one folder. What was dropped is
     *  kept on the profile, so the tree can say so.
     */
    $taken = array();
    if ( ! empty( $seed['classes'] ) ) {
        $taken = vergeml_filing_claimed_classes( $taxonomy, (int) $term->term_id );
    }
    $seed = vergeml_filing_clean_seed( $seed, $taken );

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
        'dropped'  => isset( $seed['dropped'] ) ? (array) $seed['dropped'] : array(),
        'text'     => $text,
        'vector'   => $vector,
        'built_at' => time(),
    );

    /*
     *  A planned profile replacing a planned one is kept for a day, so a
     *  confirm's re-profiling can be undone like a Move (Unconfirm -> Restore).
     *  A rebuild from the name replaces nothing worth keeping.
     */
    $was = get_term_meta( $term->term_id, VERGEML_FILING_META, true );
    if ( $plan && is_array( $was ) && ! empty( $was['plan'] ) && $was['plan'] !== $plan ) {
        update_term_meta( $term->term_id, VERGEML_FILING_META_PREV, array( 'profile' => $was, 'at' => time() ) );
    }

    update_term_meta( $term->term_id, VERGEML_FILING_META, $profile );

    return $profile;
}

/**
 *  The first class of every other stored profile in the taxonomy, spelled the
 *  one way: what a new seed may not claim. A first class is the claim a
 *  folder makes (vergeml_filing_settle_claims), and a class belongs to one
 *  folder (C.4); the planner is told so, and this is the plugin holding it to
 *  that.
 *
 *  @return array canon class => term id that holds it first.
 */
function vergeml_filing_claimed_classes( $taxonomy, $except ) {
    $taken = array();
    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
    if ( is_wp_error( $terms ) ) {
        return $taken;
    }
    foreach ( array_map( 'intval', (array) $terms ) as $tid ) {
        if ( $tid === (int) $except ) {
            continue;
        }
        $meta = get_term_meta( $tid, VERGEML_FILING_META, true );
        if ( is_array( $meta ) && ! empty( $meta['plan']['classes'] ) ) {
            $first = vergeml_filing_canon( (string) $meta['plan']['classes'][0] );
            if ( '' !== $first && ! isset( $taken[ $first ] ) ) {
                $taken[ $first ] = $tid;
            }
        }
    }
    return $taken;
}

/**
 *  A planner's seed, made honest before it is stored. Pure, so a suite can
 *  drive it: a class that is a kind word ("diagram") leaves 'classes' for
 *  'kinds'; a class held first by another folder ($taken: canon class =>
 *  term id) is dropped and recorded in 'dropped' (class => term id), so the
 *  tree can say "held by Hardware". The leaf's own name is added afterwards
 *  by the caller and is never subject to this.
 */
function vergeml_filing_clean_seed( $seed, $taken = array() ) {
    $seed    = is_array( $seed ) ? $seed : array();
    $classes = isset( $seed['classes'] ) && is_array( $seed['classes'] ) ? $seed['classes'] : array();
    $kinds   = isset( $seed['kinds'] ) && is_array( $seed['kinds'] ) ? array_values( array_map( 'sanitize_key', $seed['kinds'] ) ) : array();
    $kept    = array();
    $dropped = isset( $seed['dropped'] ) && is_array( $seed['dropped'] ) ? $seed['dropped'] : array();
    $words   = vergeml_filing_kind_words();
    foreach ( $classes as $c ) {
        $c     = vergeml_filing_name_class( $c );
        $canon = vergeml_filing_canon( $c );
        if ( '' === $c ) {
            continue;
        }
        if ( in_array( $canon, $words, true ) ) {
            $kind = 'photograph' === $canon ? 'photo' : ( in_array( $canon, array( 'picture', 'image' ), true ) ? '' : $canon );
            if ( '' !== $kind && ! in_array( $kind, $kinds, true ) ) {
                $kinds[] = $kind;
            }
            continue;
        }
        if ( isset( $taken[ $canon ] ) ) {
            $dropped[ $c ] = (int) $taken[ $canon ];
            continue;
        }
        if ( ! in_array( $c, $kept, true ) ) {
            $kept[] = $c;
        }
    }
    $seed['classes'] = $kept;
    if ( $kinds ) {
        $seed['kinds'] = $kinds;
    }
    $seed['dropped'] = $dropped;
    return $seed;
}

/**
 *  The library's own words for what its pictures show, with counts: both
 *  phrases of every record's object ("server rack; computer hardware"),
 *  spelled the one way, the most carried first. What the planner is told to
 *  use and nothing else (C.4).
 *
 *  @return array [ { term, n } ], at most $limit.
 */
function vergeml_filing_vocabulary( $limit = 80 ) {
    global $wpdb;
    if ( ! isset( $wpdb->vergeml_ai_index ) ) {
        return array();
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $rows  = (array) $wpdb->get_col( "SELECT filing FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND filing IS NOT NULL AND filing <> '' ORDER BY described_at DESC LIMIT 5000" );
    $count = array();
    $seen  = array();
    foreach ( $rows as $json ) {
        $f = json_decode( (string) $json, true );
        foreach ( vergeml_filing_classes_of_object( is_array( $f ) && isset( $f['object'] ) ? $f['object'] : '' ) as $phrase ) {
            $key = vergeml_filing_canon( $phrase );
            if ( '' === $key || in_array( $key, vergeml_filing_kind_words(), true ) ) {
                continue;
            }
            $count[ $key ] = ( isset( $count[ $key ] ) ? $count[ $key ] : 0 ) + 1;
            if ( ! isset( $seen[ $key ] ) ) {
                $seen[ $key ] = $phrase; // The first spelling met, as the describer wrote it.
            }
        }
    }
    arsort( $count );
    $out = array();
    foreach ( array_slice( $count, 0, max( 1, (int) $limit ), true ) as $key => $n ) {
        $out[] = array( 'term' => (string) $seen[ $key ], 'n' => (int) $n );
    }
    return $out;
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

    /*
     *  How many folders hold each word, plural folded, counted once for the
     *  set and carried on every profile. On the box (2026-09-15) the planner
     *  put `infrastructure` on five folders and `people` on three; a hit on
     *  such a word scored the same on every one of them, so the matcher tied
     *  and abstained -- 109 of 388 residue. In the pick a class on k folders
     *  is worth 1/k: a word everyone holds tells nobody apart.
     */
    $shared = array();
    foreach ( $profiles as $p ) {
        foreach ( array_unique( array_map( 'vergeml_filing_group_key', (array) $p['classes'] ) ) as $key ) {
            $shared[ $key ] = isset( $shared[ $key ] ) ? $shared[ $key ] + 1 : 1;
        }
    }
    foreach ( $profiles as $tid => $p ) {
        $profiles[ $tid ]['shared'] = $shared;
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
 *  One spelling for a class phrase: lowercase, British folded onto American,
 *  every word singular -- irregulars from a table, then the regular endings
 *  in the right order ("batteries" -> "battery", "launches" -> "launch",
 *  "glasses" -> "glass", "racks" -> "rack"). Until 2026-09-16 the match
 *  stripped one trailing "s" from the whole phrase, so "launches" became
 *  "launche" and the Launches folder's own name never matched its pictures.
 */
function vergeml_filing_canon( $phrase ) {
    static $spelling = array(
        'centre' => 'center', 'centres' => 'centers', 'colour' => 'color', 'colours' => 'colors', 'catalogue' => 'catalog',
        'catalogues' => 'catalogs', 'organisation' => 'organization', 'organisations' => 'organizations', 'fibre' => 'fiber',
        'fibres' => 'fibers', 'metre' => 'meter', 'metres' => 'meters', 'theatre' => 'theater', 'theatres' => 'theaters',
        'tyre' => 'tire', 'tyres' => 'tires', 'aluminium' => 'aluminum', 'grey' => 'gray', 'jewellery' => 'jewelry',
        'programme' => 'program', 'programmes' => 'programs', 'litre' => 'liter', 'litres' => 'liters', 'mould' => 'mold',
        'moulds' => 'molds', 'armour' => 'armor', 'harbour' => 'harbor', 'harbours' => 'harbors', 'labour' => 'labor',
        'favourite' => 'favorite', 'analogue' => 'analog', 'dialogue' => 'dialog', 'defence' => 'defense', 'licence' => 'license',
        'licences' => 'licenses', 'storey' => 'story', 'storeys' => 'stories', 'cheque' => 'check', 'cheques' => 'checks',
        'pyjamas' => 'pajamas', 'kerb' => 'curb', 'kerbs' => 'curbs', 'plough' => 'plow', 'ploughs' => 'plows',
        'sceptical' => 'skeptical', 'travelling' => 'traveling', 'modelling' => 'modeling', 'cancelled' => 'canceled',
    );
    static $irregular = array(
        'people' => 'person', 'men' => 'man', 'women' => 'woman', 'children' => 'child', 'feet' => 'foot', 'teeth' => 'tooth',
        'mice' => 'mouse', 'geese' => 'goose', 'oxen' => 'ox', 'leaves' => 'leaf', 'shelves' => 'shelf', 'knives' => 'knife',
        'wolves' => 'wolf', 'halves' => 'half', 'lives' => 'life', 'wives' => 'wife', 'calves' => 'calf', 'loaves' => 'loaf',
        'scarves' => 'scarf', 'lenses' => 'lens', 'buses' => 'bus', 'cacti' => 'cactus', 'fungi' => 'fungus', 'antennae' => 'antenna',
        'media' => 'medium', 'data' => 'data', 'series' => 'series', 'species' => 'species', 'glasses' => 'glass',
        'chassis' => 'chassis', 'analyses' => 'analysis', 'axes' => 'axis', 'indices' => 'index', 'matrices' => 'matrix',
    );
    // Spelled once per phrase per request: a dry run asks about a few thousand phrases half a million times.
    static $spelled = array();
    $phrase = (string) $phrase;
    if ( isset( $spelled[ $phrase ] ) ) {
        return $spelled[ $phrase ];
    }
    if ( count( $spelled ) > 50000 ) {
        $spelled = array();
    }
    $words = preg_split( '/\s+/u', trim( mb_strtolower( $phrase ) ) );
    $out   = array();
    foreach ( (array) $words as $w ) {
        if ( '' === $w ) {
            continue;
        }
        if ( isset( $spelling[ $w ] ) ) {
            $w = $spelling[ $w ];
        }
        if ( isset( $irregular[ $w ] ) ) {
            $w = $irregular[ $w ];
        } elseif ( mb_strlen( $w ) > 3 && preg_match( '/[^aeiou]ies$/u', $w ) ) {
            $w = mb_substr( $w, 0, -3 ) . 'y';
        } elseif ( mb_strlen( $w ) > 4 && preg_match( '/(ch|sh|ss|x|z)es$/u', $w ) ) {
            $w = mb_substr( $w, 0, -2 );
        } elseif ( mb_strlen( $w ) > 3 && 's' === mb_substr( $w, -1 ) && ! preg_match( '/(ss|us|is)$/u', $w ) ) {
            $w = mb_substr( $w, 0, -1 );
        }
        $out[] = $w;
    }
    return $spelled[ $phrase ] = implode( ' ', $out );
}

/** The kind words a describer writes; never a class, whatever a planner answers. */
function vergeml_filing_kind_words() {
    return array( 'photo', 'photograph', 'illustration', 'screenshot', 'document', 'diagram', 'logo', 'picture', 'image' );
}

/**
 *  How alike two class phrases are: 1 for the same phrase once both are
 *  spelled the one way (singular, American), or when one is the other's
 *  head noun ("rocket launch" is a launch); 0.95 when one phrase sits whole
 *  inside the other; otherwise the cosine of their short-phrase vectors,
 *  floored at 0.6 -- below that two phrases are not alike at all, and the
 *  matcher used to add 0.4 for "banana" against "server rack".
 */
function vergeml_filing_class_match( $a, $b, $head = false ) {
    /*
     *  Remembered per pair for the request. A dry run asks this once per
     *  picture phrase per folder class: 626 pictures against 319 folders on
     *  the box's shop site (C.5) is some 800,000 asks about a few thousand
     *  distinct pairs, and spelling the same two phrases the one way each
     *  time put the run past its twenty-second budget with every vector
     *  already in hand (21-28 s warm, 2026-09-16).
     */
    static $seen = array();
    $slot = $a . "\0" . $b . "\0" . ( $head ? 1 : 0 );
    if ( isset( $seen[ $slot ] ) ) {
        return $seen[ $slot ];
    }
    if ( count( $seen ) > 200000 ) {
        $seen = array();
    }
    return $seen[ $slot ] = vergeml_filing_class_match_( $a, $b, $head );
}

function vergeml_filing_class_match_( $a, $b, $head ) {
    $a = trim( mb_strtolower( $a ) );
    $b = trim( mb_strtolower( $b ) );
    if ( '' === $a || '' === $b ) {
        return 0.0;
    }
    if ( $a === $b ) {
        return 1.0;
    }
    $ca = vergeml_filing_canon( $a );
    $cb = vergeml_filing_canon( $b );
    if ( $ca === $cb ) {
        return 1.0;
    }
    /*
     *  The head noun, only when the caller says $a is the picture's object
     *  (its first phrase): "rocket launch" is the folder's one-word class
     *  "launch" in full -- a rocket launch is a launch. Not for the picture's
     *  class half: "desktop pc; computer hardware" against the parent named
     *  Hardware is 0.95, as it was -- on 2026-09-16 the full hit there lifted
     *  every too-broad likely in Hardware to sure. And not the reverse: a
     *  picture that says only "rack" is 0.95 of a server rack.
     */
    $wa = explode( ' ', $ca );
    $wb = explode( ' ', $cb );
    if ( $head && count( $wa ) > 1 && 1 === count( $wb ) && end( $wa ) === $wb[0] ) {
        return 1.0;
    }
    if ( false !== mb_strpos( ' ' . $ca . ' ', ' ' . $cb . ' ' ) || false !== mb_strpos( ' ' . $cb . ' ', ' ' . $ca . ' ' ) ) {
        return 0.95;
    }
    if ( ! function_exists( 'vergeml_meaning_vector' ) ) {
        return 0.0;
    }
    $va = vergeml_filing_phrase_vector( $a );
    $vb = vergeml_filing_phrase_vector( $b );
    if ( null === $va || null === $vb ) {
        return 0.0;
    }
    $cos = vergeml_filing_cosine( $va['v'], $va['n'], $vb['v'], $vb['n'] );
    return $cos >= VERGEML_FILING_CLASS_COSINE_FLOOR ? $cos : 0.0;
}

/** A phrase's vector and its length, asked of the cache once per request: ['v', 'n'], or null when there is none. */
function vergeml_filing_phrase_vector( $text ) {
    static $known = array();
    if ( array_key_exists( $text, $known ) ) {
        return $known[ $text ];
    }
    if ( count( $known ) > 20000 ) {
        $known = array();
    }
    $v = vergeml_meaning_vector( $text );
    return $known[ $text ] = is_array( $v ) && $v ? array( 'v' => $v, 'n' => vergeml_filing_norm( $v ) ) : null;
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
 *              run groups these and asks -- the last kind as one either/or
 *              question per pair of folders ('children' => the two), the
 *              rest as residue by what they look like.
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
 *                'children' (siblings, and a margin between non-siblings: the
 *                two), 'nearest' (nothing: the folder it came closest to),
 *                'kind' (gated everywhere: the picture's kind), 'scores'
 *                (term id => score), 'gated' (term id => 'kind' | 'audience' | 'locked').
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
    // The picture's vector length once, not once per folder.
    $pnorm  = is_array( $facts['vector'] ) ? vergeml_filing_norm( $facts['vector'] ) : 0.0;

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
         *
         *  Three more weights, from the box (2026-09-15, S6b): the picture's
         *  first phrase is the object, the second its class, so the second
         *  weighs 0.85 -- "server rack; computer hardware" is a server rack
         *  before it is hardware; the folder's own name is what it is for
         *  wherever the planner ranked it, so an exact hit on it weighs 1.0;
         *  and a class held by k folders is worth 1/k on each, so a word
         *  everyone holds cannot outscore the folder named for the object.
         */
        $class  = 0.0;
        $leaf   = vergeml_filing_group_key( vergeml_filing_name_class( isset( $p['path'] ) && $p['path'] ? end( $p['path'] ) : '' ) );
        $shared = isset( $p['shared'] ) ? (array) $p['shared'] : array();
        foreach ( array_values( (array) $facts['classes'] ) as $pi => $pc ) {
            $phrase = 0 === $pi ? 1.0 : 0.85;
            foreach ( array_values( (array) $p['classes'] ) as $rank => $fc ) {
                $match   = vergeml_filing_class_match( $pc, $fc, 0 === $pi );
                $is_leaf = '' !== $leaf && vergeml_filing_group_key( $fc ) === $leaf;
                $weight  = ( 0 === $rank || ( $is_leaf && $match >= 1.0 ) ) ? 1.0 : 0.85;
                $k       = $is_leaf ? 1 : max( 1, (int) ( isset( $shared[ vergeml_filing_group_key( $fc ) ] ) ? $shared[ vergeml_filing_group_key( $fc ) ] : 1 ) );
                $class   = max( $class, $phrase * $weight * $match / $k );
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
        if ( is_array( $facts['vector'] ) && is_array( $p['vector'] ) ) {
            $embed = max( 0.0, vergeml_filing_cosine( $p['vector'], vergeml_filing_norm( $p['vector'], $tid . ':' . ( isset( $p['built_at'] ) ? $p['built_at'] : 0 ) ), $facts['vector'], $pnorm ) );
        }

        $scores[ $tid ] = VERGEML_FILING_CLASS_WEIGHT * $class + ( 1 - VERGEML_FILING_CLASS_WEIGHT ) * $embed;
    }

    if ( ! $scores ) {
        // Gated everywhere, most often by kind: the kind rides out, so the residue can group screenshots with screenshots.
        return vergeml_filing_outcome( 'nothing', 'gated', array( 'scores' => array(), 'gated' => $gated, 'kind' => (string) $facts['kind'] ) );
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
        /*
         *  Two folders that are not siblings under a folder, too close to
         *  call: not placed, and not residue either. "Hardware or Server
         *  racks?" is a question a person answers in one press, and until
         *  2026-09-15 it was never asked -- the picture fell into the residue
         *  by its object word and was named after something else.
         */
        return vergeml_filing_outcome( 'nothing', 'margin', $common + array( 'nearest' => $best, 'children' => array( $best, $runner ) ) );
    }

    return vergeml_filing_outcome( 'fits', 'ok', $common + array(
        'term_id'    => $best,
        'parent_id'  => vergeml_filing_parent_of( $best, $profiles ),
        'confidence' => $score >= VERGEML_FILING_SURE ? 'sure' : 'likely',
    ) );
}

/**
 *  A vector's length, remembered by key when one is given (a folder's, the
 *  same across every picture of a run); the picture's is asked without a
 *  key, once per pick. The pair loop below was three products a dimension
 *  for every picture-folder pair -- 626 pictures against 319 folders on the
 *  box's shop site (C.5) is 100 million of them -- and two of the three
 *  never changed between pairs.
 */
function vergeml_filing_norm( $v, $key = '' ) {
    static $known = array();
    if ( '' !== $key && isset( $known[ $key ] ) ) {
        return $known[ $key ];
    }
    $sum = 0.0;
    foreach ( $v as $x ) {
        $sum += $x * $x;
    }
    $norm = sqrt( $sum );
    if ( '' !== $key ) {
        if ( count( $known ) > 5000 ) {
            $known = array();
        }
        $known[ $key ] = $norm;
    }
    return $norm;
}

/** The cosine of two vectors whose lengths are known: one product a dimension. The same number vergeml_meaning_similarity() gives. */
function vergeml_filing_cosine( $a, $na, $b, $nb ) {
    if ( $na <= 0.0 || $nb <= 0.0 ) {
        return 0.0;
    }
    $dot = 0.0;
    foreach ( $a as $i => $x ) {
        if ( isset( $b[ $i ] ) ) {
            $dot += $x * $b[ $i ];
        }
    }
    return $dot / ( $na * $nb );
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
        'either'   => 0, // Of 'nothing': too close to call between two folders that are not siblings; asked as either/or, not residue.
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
    if ( vergeml_filing_is_either( $pick ) ) {
        $counts['either'] = ( isset( $counts['either'] ) ? (int) $counts['either'] : 0 ) + 1;
    }
}

/** A pick that is an either/or question: not placed, two folders too close to call, not siblings under a folder. */
function vergeml_filing_is_either( $pick ) {
    return isset( $pick['outcome'], $pick['children'] ) && 'nothing' === $pick['outcome'] && 'margin' === $pick['why'] && 2 === count( (array) $pick['children'] );
}

/** A pick the fill acts on in no way: placed by the user, or in a locked folder. Looked at, kept, no row. */
function vergeml_filing_kept( $pick ) {
    return isset( $pick['why'] ) && ( 'placed' === $pick['why'] || 'locked' === $pick['why'] );
}

/** Two tallies into one: the run adds each slice's to the state's. */
function vergeml_filing_tally_add( $a, $b ) {
    foreach ( array( 'looked', 'fits', 'siblings', 'nothing', 'kept', 'either', 'sure', 'likely' ) as $k ) {
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
 *  a question: five pictures. Under that it joins a standing group only when
 *  the two are near-identical -- the phrases match (class_match over
 *  GROUP_SAME) or the centroids do (cosine over GROUP_NEAR) -- and still
 *  under five after that, it is one of the small groups, asked about together
 *  on one card. The GROUP_ASK largest groups are asked; the rest join that
 *  card. Eight pictures is what a question shows.
 *
 *  NEAR was 0.5 until 2026-09-15. On the box that pulled sixteen telecom
 *  towers, three robots, a barn and a walnut tree onto a seed of nine server
 *  racks, and the card said "61 look like server racks".
 */
const VERGEML_FILING_GROUP_MIN  = 5;
const VERGEML_FILING_GROUP_TINY = 5;
const VERGEML_FILING_GROUP_NEAR = 0.8;
const VERGEML_FILING_GROUP_SAME = 0.95;
const VERGEML_FILING_GROUP_ASK  = 8;
const VERGEML_FILING_SAMPLE     = 8;

/** Under this share of the majority phrase, the sentence says "mixed, mostly X" rather than "look like X". */
const VERGEML_FILING_GROUP_PURE = 0.7;

/** Offered "Put in X" only when X is the nearest folder for this share of the group. */
const VERGEML_FILING_GROUP_MAJORITY = 0.6;

/** Term meta and slug of the one folder "leave them" leaves things in. Locked: the fill never files into or out of it. */
const VERGEML_FILING_TO_SORT_SLUG = 'to-sort';

/**
 *  The residue, grouped.
 *
 *  Kind first: a screenshot, an illustration, a diagram, a document, a logo
 *  goes with its own kind and never with a photograph of the same thing.
 *  Photographs group by the specific phrase the describer wrote first ("robot
 *  arm" of "robot arm; machinery"), plural folded. Then every group under
 *  GROUP_MIN joins a standing group when the two are near-identical --
 *  phrases over GROUP_SAME or centroids over GROUP_NEAR -- largest first, the
 *  centroid recomputed over the merged vectors; after merging, the class is
 *  the majority phrase and 'share' says how much of the group it is. The
 *  GROUP_ASK largest groups of GROUP_TINY or more are asked about one by one;
 *  everything still readable but small or beyond the cap is one group
 *  ('more'); the pictures with no class at all are the last ('unreadable').
 *
 *  @param array $facts attachment id => vergeml_filing_facts().
 *  @return array[] Each: 'class', 'classes' (class => n, biggest first),
 *                  'share' (of the class), 'kind', 'ids', 'count', 'centroid'
 *                  (vector|null), 'more', 'unreadable'.
 */
function vergeml_filing_residue_groups( $facts ) {

    $groups = array();
    $pool   = array();

    foreach ( (array) $facts as $id => $f ) {
        $kind  = isset( $f['kind'] ) && '' !== (string) $f['kind'] ? (string) $f['kind'] : 'photo';
        $class = isset( $f['classes'][0] ) ? vergeml_filing_group_key( $f['classes'][0] ) : '';
        if ( 'photo' !== $kind ) {
            $key   = 'kind:' . $kind;
            $class = $kind;
        } elseif ( '' === $class ) {
            $pool[] = (int) $id;
            continue;
        } else {
            $key = 'class:' . $class;
        }
        if ( ! isset( $groups[ $key ] ) ) {
            $groups[ $key ] = array( 'class' => $class, 'classes' => array( $class => 0 ), 'kind' => $kind, 'ids' => array(), 'vectors' => array() );
        }
        $groups[ $key ]['ids'][] = (int) $id;
        $groups[ $key ]['classes'][ $class ]++;
        if ( is_array( $f['vector'] ) && $f['vector'] ) {
            $groups[ $key ]['vectors'][] = $f['vector'];
        }
    }

    foreach ( $groups as $k => $g ) {
        $groups[ $k ]['centroid'] = vergeml_filing_centroid( $g['vectors'] );
    }

    // Small photo groups, largest first, each into the standing photo group it is near-identical to, if any.
    $small = array_filter( array_keys( $groups ), function ( $k ) use ( $groups ) { return 'photo' === $groups[ $k ]['kind'] && count( $groups[ $k ]['ids'] ) < VERGEML_FILING_GROUP_MIN; } );
    usort( $small, function ( $a, $b ) use ( $groups ) { return count( $groups[ $b ]['ids'] ) <=> count( $groups[ $a ]['ids'] ) ?: min( $groups[ $a ]['ids'] ) <=> min( $groups[ $b ]['ids'] ); } );

    foreach ( $small as $k ) {
        if ( ! isset( $groups[ $k ] ) ) {
            continue;
        }
        $best  = '';
        $score = 0.0;
        foreach ( $groups as $other => $g ) {
            if ( $other === $k || 'photo' !== $g['kind'] ) {
                continue;
            }
            $s = 0.0;
            if ( is_array( $g['centroid'] ) && is_array( $groups[ $k ]['centroid'] ) ) {
                $c = (float) vergeml_meaning_similarity( $g['centroid'], $groups[ $k ]['centroid'] );
                $s = $c >= VERGEML_FILING_GROUP_NEAR ? $c : 0.0;
            }
            $m = vergeml_filing_class_match( $g['class'], $groups[ $k ]['class'] );
            if ( $m >= VERGEML_FILING_GROUP_SAME ) {
                $s = max( $s, $m );
            }
            if ( $s > $score ) {
                $score = $s;
                $best  = $other;
            }
        }
        if ( '' === $best ) {
            continue;
        }
        $groups[ $best ]['ids']     = array_merge( $groups[ $best ]['ids'], $groups[ $k ]['ids'] );
        $groups[ $best ]['vectors'] = array_merge( $groups[ $best ]['vectors'], $groups[ $k ]['vectors'] );
        foreach ( $groups[ $k ]['classes'] as $class => $n ) {
            $groups[ $best ]['classes'][ $class ] = ( isset( $groups[ $best ]['classes'][ $class ] ) ? $groups[ $best ]['classes'][ $class ] : 0 ) + $n;
        }
        $groups[ $best ]['centroid'] = vergeml_filing_centroid( $groups[ $best ]['vectors'] );
        unset( $groups[ $k ] );
    }

    // The label is the majority, said with its share; a seed of nine racks does not name sixteen towers.
    $readable = array();
    $more     = array();
    foreach ( $groups as $g ) {
        unset( $g['vectors'] );
        arsort( $g['classes'] );
        $g['count']      = count( $g['ids'] );
        $g['class']      = (string) array_key_first( $g['classes'] );
        $g['share']      = $g['count'] ? reset( $g['classes'] ) / $g['count'] : 0.0;
        $g['more']       = false;
        $g['unreadable'] = false;
        if ( $g['count'] < VERGEML_FILING_GROUP_TINY ) {
            $more = array_merge( $more, $g['ids'] );
            continue;
        }
        $readable[] = $g;
    }
    usort( $readable, function ( $a, $b ) { return $b['count'] <=> $a['count'] ?: min( $a['ids'] ) <=> min( $b['ids'] ); } );

    // The cap: eight questions is what a person answers; the rest is one card.
    foreach ( array_slice( $readable, VERGEML_FILING_GROUP_ASK ) as $g ) {
        $more = array_merge( $more, $g['ids'] );
    }
    $out = array_slice( $readable, 0, VERGEML_FILING_GROUP_ASK );

    if ( $more ) {
        sort( $more );
        $out[] = array( 'class' => '', 'classes' => array(), 'share' => 0.0, 'kind' => '', 'ids' => array_values( $more ), 'count' => count( $more ), 'centroid' => null, 'more' => true, 'unreadable' => false );
    }
    if ( $pool ) {
        sort( $pool );
        $out[] = array( 'class' => '', 'classes' => array(), 'share' => 0.0, 'kind' => '', 'ids' => array_values( $pool ), 'count' => count( $pool ), 'centroid' => null, 'more' => false, 'unreadable' => true );
    }

    return $out;
}

/**
 *  The folder "Put in X" may offer for a group: the one that is the nearest
 *  folder for GROUP_MAJORITY of its pictures, and takes the group's kind, is
 *  not locked and is not for an audience the group does not name. 0 when
 *  there is no such folder -- on the box (2026-09-15) the folder nearest a
 *  61-picture group's centroid was Server racks, nearest for 9 of them, and
 *  the press put all 61 there.
 *
 *  @param array $group    A group from vergeml_filing_residue_groups() ('ids', 'count', 'kind').
 *  @param array $nearest  attachment id => the folder the pick came closest to (0 for none).
 *  @param array $profiles term id => profile ('kinds', 'audience', 'locked').
 *  @param array $facts    attachment id => facts, for the audience; optional.
 */
function vergeml_filing_group_nearest( $group, $nearest, $profiles, $facts = array() ) {

    $ids = array_map( 'intval', (array) $group['ids'] );
    if ( ! $ids ) {
        return 0;
    }
    $votes = array();
    foreach ( $ids as $id ) {
        $tid = isset( $nearest[ $id ] ) ? (int) $nearest[ $id ] : 0;
        if ( $tid ) {
            $votes[ $tid ] = isset( $votes[ $tid ] ) ? $votes[ $tid ] + 1 : 1;
        }
    }
    if ( ! $votes ) {
        return 0;
    }
    arsort( $votes );
    $tid = (int) array_key_first( $votes );
    if ( reset( $votes ) < VERGEML_FILING_GROUP_MAJORITY * count( $ids ) || ! isset( $profiles[ $tid ] ) ) {
        return 0;
    }
    $p    = $profiles[ $tid ];
    $kind = isset( $group['kind'] ) && '' !== (string) $group['kind'] ? (string) $group['kind'] : 'photo';
    if ( ! empty( $p['locked'] ) || ! in_array( $kind, (array) $p['kinds'], true ) ) {
        return 0;
    }
    if ( isset( $p['audience'] ) && '' !== (string) $p['audience'] ) {
        foreach ( $ids as $id ) {
            if ( ! isset( $facts[ $id ]['audience'] ) || $facts[ $id ]['audience'] !== $p['audience'] ) {
                return 0;
            }
        }
    }
    return $tid;
}

/** "Robot arms" and "robot arm" are one group; so are "network switches" and "network switch". */
function vergeml_filing_group_key( $phrase ) {
    $p = trim( mb_strtolower( (string) $phrase ) );
    if ( preg_match( '/(ch|sh|x|z)es$/u', $p ) ) {
        return mb_substr( $p, 0, -2 );
    }
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
 *  then one per pair of folders that are not siblings and tied ("Hardware or
 *  Server racks?"); then one per residue group, by size; the unreadable one
 *  last. The shape is what /guide/questions serves and /guide/answer takes:
 *
 *    { id, kind: 'siblings'|'either'|'residue', term_id, children, count, sample,
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
 *  @param array $either   "a:b" (the two term ids, lower first) => [ 'ids' => attachment => its best of the two, 'children' => term => n ].
 */
function vergeml_filing_questions( $groups, $siblings, $names = array(), $nearest = array(), $either = array() ) {

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

    // Either/or: the folder more often best is named first and offered first. Largest pairs first.
    $pairs = (array) $either;
    uasort( $pairs, function ( $a, $b ) { return count( (array) $b['ids'] ) <=> count( (array) $a['ids'] ); } );
    foreach ( $pairs as $key => $e ) {
        $ids      = isset( $e['ids'] ) ? (array) $e['ids'] : array();
        $children = isset( $e['children'] ) ? (array) $e['children'] : array();
        arsort( $children );
        $two = array_map( 'intval', array_slice( array_keys( $children ), 0, 2 ) );
        if ( 2 !== count( $two ) || ! $ids ) {
            continue;
        }
        $out[] = array(
            'id'         => 'e:' . (string) $key,
            'kind'       => 'either',
            'term_id'    => 0,
            'children'   => $two,
            'count'      => count( $ids ),
            'sample'     => array_map( 'intval', array_slice( array_keys( $ids ), 0, VERGEML_FILING_SAMPLE ) ),
            'ids'        => array_map( 'intval', $ids ),
            'name'       => '',
            'class'      => '',
            'unreadable' => false,
            'answers'    => array( 'put-in:' . $two[0], 'put-in:' . $two[1], 'split', 'leave', 'show-me' ),
        );
    }

    foreach ( (array) $groups as $i => $g ) {
        $near = isset( $nearest[ $i ] ) ? (int) $nearest[ $i ] : 0;
        if ( ! empty( $g['unreadable'] ) || ! empty( $g['more'] ) ) {
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
            'share'      => isset( $g['share'] ) ? (float) $g['share'] : 1.0,
            'group_kind' => isset( $g['kind'] ) ? (string) $g['kind'] : 'photo',
            'more'       => ! empty( $g['more'] ),
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

    // A sibling or either/or question's ids are a map picture => its best folder (split reads it); the pictures are its keys.
    $mapped = in_array( $q['kind'], array( 'siblings', 'either' ), true );

    if ( 'show-me' === $answer ) {
        $plan['show']     = $mapped ? array_map( 'intval', array_keys( (array) $q['ids'] ) ) : array_values( (array) $q['ids'] );
        $plan['answered'] = false;
        return $plan;
    }

    if ( $mapped && 'split' === $answer ) {
        // The matcher's own best of the two, so the word on each stays the fill's, not the user's.
        foreach ( (array) $q['ids'] as $id => $best ) {
            $plan['moves'][ (int) $id ] = (int) $best;
        }
        return $plan;
    }
    if ( 'siblings' === $q['kind'] ) {
        return $plan; // keep-parent: they are in the parent already.
    }

    $ids = $mapped ? array_map( 'intval', array_keys( (array) $q['ids'] ) ) : array_values( (array) $q['ids'] );

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
function vergeml_filing_profile_ask( $current, &$charged = 0, $from = 0, $total = null ) {

    if ( ! function_exists( 'vergeml_ai_settings' ) ) {
        return new WP_Error( 'no_ai', 'AI not loaded.' );
    }
    $settings = vergeml_ai_settings();
    $licence  = vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
    if ( '' === $licence ) {
        return new WP_Error( 'no_licence', __( 'No licence key.', 'vergelabs-media-library' ) );
    }

    $samples = function_exists( 'vergeml_talk_samples' ) ? vergeml_talk_samples() : array();
    // The library's own words, with counts: the profile's classes must be these (C.4).
    $terms   = vergeml_filing_vocabulary();
    $out     = array();
    $charged = 0;

    // One call per batch, each with its place in the whole ask so the service charges the right folders.
    foreach ( vergeml_filing_profile_batches( $current, $from, $total ) as $batch ) {
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
                    'current'     => $batch['current'],
                    'offset'      => $batch['offset'],
                    'total'       => $batch['total'],
                    'samples'     => $samples,
                    'terms'       => $terms,
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
        $charged += isset( $data['charged'] ) ? (int) $data['charged'] : 0;

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
    }
    return $out;
}
