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

/**
 *  A member word is alike to a folder's own word from here up (S16), under
 *  the class match's floor: measured on the tech library (2026-09-18), the
 *  words the members teach rightly sit at 0.51-0.58 against the base
 *  (industrial robot ~ robotics 0.57, falcon 9 rocket ~ rocket launch 0.58,
 *  graphics card ~ computer hardware 0.57, loudspeaker ~ audio equipment
 *  0.56) and the fill's learned misses at 0.15-0.44 (cable reels ~ cooling
 *  0.27, robotic arm ~ server racks 0.35, smart speaker ~ electronics
 *  component 0.33, telecommunications mast ~ networking equipment 0.44).
 */
const VERGEML_FILING_MEMBERS_ALIKE = 0.5;

/** Term meta: the profile a re-profiling replaced, for a day's Restore (C.4). */
const VERGEML_FILING_META_PREV = '_vergeml_filing_profile_prev';

/**
 *  A folder's name as a person wrote it. WordPress stores an ampersand in a
 *  term name as "&amp;" (kses on the way in), and every reader here took
 *  the stored form as the name: on the box's shop catalogue (C.5,
 *  2026-09-16) "Bags & Luggage" and its thirteen "&" siblings showed as
 *  "Bags &amp; Luggage" on the tree, never matched their draft rows by
 *  name, and were profiled from a text that differed from the draft's.
 */
function vergeml_term_name( $term ) {
    $name = is_object( $term ) ? ( isset( $term->name ) ? $term->name : '' ) : $term;
    return wp_specialchars_decode( (string) $name, ENT_QUOTES );
}

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
    if ( 'siblings' === $why || 'answer' === $why ) {
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
    /*
     *  Dutch says it in a compound (S17, HEMA's tree): dameskleding,
     *  herenkleding, kinderkleding, meisjeskleding, jongenskleding,
     *  babyspeelgoed -- the audience is the word's start, so those count as
     *  a prefix; the English words stay whole words ("mankind" is no one's).
     *  "kind" alone is Dutch for a child and English for a sort of thing:
     *  read only on a Dutch site.
     */
    if ( preg_match( '/ (men|mens|man|male|gents|gentlemen|heren|mannen) /u', $t ) || preg_match( '/ heren\S/u', $t ) ) {
        return 'men';
    }
    if ( preg_match( '/ (women|womens|woman|female|ladies|lady|dames|vrouwen) /u', $t ) || preg_match( '/ dames\S/u', $t ) ) {
        return 'women';
    }
    if ( preg_match( '/ (kids|kid|children|child|baby|babies|boys|girls|toddler|toddlers|kinderen|kinder|meisjes|jongens|peuters) /u', $t ) || preg_match( '/ (kinder|baby|meisjes|jongens|peuter)\S/u', $t ) ) {
        return 'kids';
    }
    if ( preg_match( '/ kind /u', $t ) && function_exists( 'get_locale' ) && 'nl' === substr( (string) get_locale(), 0, 2 ) ) {
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
        $segments = preg_split( '/\s*\/\s*/u', vergeml_term_name( $walk ) );
        $segments = array_values( array_filter( array_map( 'trim', (array) $segments ), 'strlen' ) );
        $path     = array_merge( $segments ? $segments : array( vergeml_term_name( $walk ) ), $path );
        $walk     = $walk->parent ? get_term( $walk->parent, $taxonomy ) : null;
    }
    $leaf = $path ? (string) end( $path ) : vergeml_term_name( $term );

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
     *  A rebuild from the name replaces nothing worth keeping -- unless a
     *  person asked for it (× on the folder's last word, `nowords`, S12):
     *  that is a choice, and undoable like the others.
     */
    $was = get_term_meta( $term->term_id, VERGEML_FILING_META, true );
    if ( ( $plan || ! empty( $seed['nowords'] ) ) && is_array( $was ) && ! empty( $was['plan'] ) && $was['plan'] !== $plan ) {
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
    // Read once a request: the confirm's ask is worked out on every session answer (S10.1).
    static $known = array();
    if ( isset( $known[ (int) $limit ] ) ) {
        return $known[ (int) $limit ];
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
    // 0: the whole vocabulary, for the ask's split; the planner's prompt takes the top eighty.
    foreach ( ( (int) $limit > 0 ? array_slice( $count, 0, (int) $limit, true ) : $count ) as $key => $n ) {
        $out[] = array( 'term' => (string) $seen[ $key ], 'n' => (int) $n );
    }
    return $known[ (int) $limit ] = $out;
}

/**
 *  Whether a folder's name is already one of the library's words (S10.1):
 *  its leaf, spelled the one way, equals a vocabulary term -- or the two
 *  meet at a head noun, either way round: "Shoes" is the describer's
 *  "running shoe", "Sneakers" its "platform sneaker", "Running shoes" its
 *  "shoe". Such a folder is profiled from its name and never sent to the
 *  planner: on the shop's 318-folder tree (C.5) 259 of 308 answers were
 *  "nothing", and batches that could not see each other hung leftover
 *  words on the parents ("Garden = power tool, architecture").
 *
 *  @param string $name       The folder's name, as the person wrote it.
 *  @param array  $vocabulary vergeml_filing_vocabulary()'s rows, or plain terms.
 */
function vergeml_filing_name_in_vocabulary( $name, $vocabulary ) {
    $canon = vergeml_filing_canon( vergeml_filing_name_class( vergeml_term_name( $name ) ) );
    if ( '' === $canon ) {
        return false;
    }
    $words = explode( ' ', $canon );
    $head  = end( $words );
    foreach ( (array) $vocabulary as $term ) {
        $t = vergeml_filing_canon( is_array( $term ) ? ( isset( $term['term'] ) ? $term['term'] : '' ) : $term );
        if ( '' === $t ) {
            continue;
        }
        if ( $t === $canon ) {
            return true;
        }
        $tw = explode( ' ', $t );
        if ( ( count( $words ) > 1 && $head === $t ) || ( count( $tw ) > 1 && end( $tw ) === $canon ) ) {
            return true;
        }
    }
    return false;
}

/**
 *  Which folders of a tree a planner can add to (S10.1). Pure, over the
 *  shape: a leaf under a parent is a concrete noun -- "Blouses", "Kettlebells"
 *  -- and its name is its class, so it is profiled from the name whether or
 *  not a picture says it yet (an empty catalogue leaf mostly has none: on the
 *  shop's 322 folders 177 names were in no picture's words, and the planner
 *  had answered "nothing" for 259 of the 308 it was asked). A parent, or a
 *  folder at the top, is a category ("Home", "Sports & Outdoors", "Kids"):
 *  its name says little, and the planner's classes are what the matcher has
 *  -- unless the name is already a library word. On the shop that is 37 of
 *  322: one batch, no credits.
 *
 *  A tree in another language than the describer's (S15, HEMA's tree on
 *  the shop: 292 Dutch folders over English descriptions, every sure a
 *  top-level parent because "wandelschoenen" meets no English word and
 *  never will): when $foreign, a leaf whose name no picture says goes too,
 *  for a class in the describer's words -- on HEMA that is 283 leaves,
 *  five batches, ~36 credits once, said on the button. The caller reads
 *  the site's language (vergeml_filing_tree_is_foreign).
 *
 *  @param array $folders    [ { key, name, parent } ], parent a key or ''.
 *  @param array $vocabulary vergeml_filing_vocabulary()'s rows.
 *  @param bool  $foreign    The site's language is not the describer's.
 *  @return array The keys that go to the planner, in the tree's order.
 */
function vergeml_filing_ask_split( $folders, $vocabulary, $foreign = false ) {
    $children = array();
    foreach ( (array) $folders as $f ) {
        if ( '' !== (string) $f['parent'] ) {
            $children[ (string) $f['parent'] ] = true;
        }
    }
    $views = vergeml_filing_draft_views( $folders );
    $go    = array();
    foreach ( (array) $folders as $f ) {
        $key = (string) $f['key'];
        if ( isset( $views[ $key ] ) ) {
            continue; // A view of the tree, or under one (S16): it owns nothing, so the planner's answer would be words it cannot use.
        }
        if ( ! $foreign && '' !== (string) $f['parent'] && ! isset( $children[ $key ] ) ) {
            continue; // A leaf under a parent: its name is its class.
        }
        if ( vergeml_filing_name_in_vocabulary( $f['name'], $vocabulary ) ) {
            continue;
        }
        $go[] = $key;
    }
    return $go;
}

/**
 *  The views of a draft (S16): vergeml_filing_views' rule on the draft's own
 *  shape -- { key, name, parent } -- before any term exists, so the ask can
 *  leave a view and everything under it home. A folder more than half of
 *  whose children (a child named for an audience not counted) repeat a name
 *  held higher in the tree is a view. Pure.
 *
 *  @return array key => true, for every view and every folder under one.
 */
function vergeml_filing_draft_views( $folders ) {
    $by_key   = array();
    $children = array();
    foreach ( (array) $folders as $f ) {
        $by_key[ (string) $f['key'] ] = $f;
        $children[ (string) $f['parent'] ][] = (string) $f['key'];
    }
    $depth_of = function ( $key ) use ( $by_key ) {
        $d = 0;
        for ( $g = 0; '' !== $key && isset( $by_key[ $key ] ) && $g < 32; $g++ ) {
            $d++;
            $key = (string) $by_key[ $key ]['parent'];
        }
        return $d;
    };
    $holders = array();
    foreach ( $by_key as $key => $f ) {
        $holders[ vergeml_filing_canon( (string) $f['name'] ) ][ $key ] = $depth_of( $key );
    }
    $views = array();
    foreach ( $children as $pid => $kids ) {
        if ( '' === $pid || ! isset( $by_key[ $pid ] ) ) {
            continue;
        }
        $repeat = 0;
        $named  = 0;
        foreach ( $kids as $kid ) {
            $name = (string) $by_key[ $kid ]['name'];
            if ( '' !== vergeml_filing_audience_of( $name ) ) {
                continue;
            }
            $named++;
            $depth = $depth_of( $kid );
            foreach ( $holders[ vergeml_filing_canon( $name ) ] as $other => $d ) {
                if ( $other !== $kid && $d < $depth ) {
                    $repeat++;
                    break;
                }
            }
        }
        if ( $repeat * 2 > $named ) {
            $views[ $pid ] = true;
        }
    }
    $out = array();
    foreach ( $by_key as $key => $f ) {
        for ( $k = $key, $g = 0; '' !== $k && isset( $by_key[ $k ] ) && $g < 32; $g++ ) {
            if ( isset( $views[ $k ] ) ) {
                $out[ $key ] = true;
                break;
            }
            $k = (string) $by_key[ $k ]['parent'];
        }
    }
    return $out;
}

/** The site's language is not the describer's (English): its folder names will not be the pictures' words. */
function vergeml_filing_tree_is_foreign() {
    return 'en' !== substr( (string) get_locale(), 0, 2 );
}

/** Profiles for a set of terms, keyed by term id, each read over what the folder holds (S10.7). Terms without one are left out. */
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
    $out   = vergeml_filing_views( $out );
    $learn = array_keys( array_filter( $out, function ( $p ) { return empty( $p['view'] ); } ) ); // A view learns nothing from what a person put in it.
    foreach ( vergeml_filing_members_settle( vergeml_filing_members_alike( vergeml_filing_members_layers( $learn, $taxonomy ), $out ) ) as $id => $layer ) {
        $out[ $id ] = vergeml_filing_members_apply( $out[ $id ], $layer );
    }
    return vergeml_filing_settle_claims( vergeml_filing_name_claims( $out ) );
}

/**
 *  File by the product (S10.8): a product category's folder, pure. The
 *  category's path (root to leaf, as names) maps to the folder whose path
 *  is the same by canon names; failing that, to the folder whose leaf is
 *  the category's leaf name when exactly one folder has it -- a shop's
 *  "Dresses" product category and its Clothing › Women › Dresses folder
 *  are one thing under two roots; two folders named Backpacks are a
 *  question, not a guess. A view of the tree is never the folder. 0 when
 *  nothing matches.
 *
 *  @param string[] $cat_path The category's ancestors and itself, names.
 *  @param array    $profiles From vergeml_filing_profiles(), keyed by term id.
 *  @return int A term id, or 0.
 */
function vergeml_filing_product_folder( $cat_path, $profiles ) {
    $want = array_map( 'vergeml_filing_canon', array_values( array_filter( array_map( 'strval', (array) $cat_path ), 'strlen' ) ) );
    if ( ! $want ) {
        return 0;
    }
    $leaf    = end( $want );
    $by_leaf = array();
    foreach ( (array) $profiles as $tid => $p ) {
        if ( ! empty( $p['view'] ) || empty( $p['path'] ) ) {
            continue;
        }
        $path = array_map( 'vergeml_filing_canon', (array) $p['path'] );
        if ( $path === $want ) {
            return (int) $tid;
        }
        if ( end( $path ) === $leaf ) {
            $by_leaf[] = (int) $tid;
        }
    }
    return 1 === count( $by_leaf ) ? $by_leaf[0] : 0;
}

/**
 *  A folder's name claims the planner classes it names (S16). The planner
 *  put "camera lens" first on TV & Video beside a leaf named Lenses, and
 *  on the shop's truth score (2026-09-18) 11 of 12 lens pictures went to a
 *  margin or to TV & Video; "public bookcase" on Shelving beside Bookcases
 *  took 11 of 11 bookcases to a margin; "people" first on Interviews made
 *  "figure; person" an Interviews sure on the tech library. A planner
 *  class whose whole is another folder's leaf name, or whose head noun is
 *  another folder's one-word leaf name, is that folder's and comes off the
 *  planner's folder -- vergeml_filing_clean_seed does this at build time
 *  against other plans, not against a name-only leaf, and a re-plan is
 *  what it would take to reach a cached plan. A member word stays (it
 *  passed the likeness test), the folder's own leaf stays, a view claims
 *  nothing. Dry on the shop: right 333 -> 347 of 581, none lost. Pure.
 */
function vergeml_filing_name_claims( $profiles ) {
    $named = array();
    foreach ( (array) $profiles as $tid => $p ) {
        if ( ! empty( $p['view'] ) ) {
            continue;
        }
        $leaf = vergeml_filing_canon( vergeml_filing_name_class( isset( $p['path'] ) && $p['path'] ? end( $p['path'] ) : '' ) );
        if ( '' !== $leaf ) {
            $named[ $leaf ][ (int) $tid ] = true;
        }
    }
    foreach ( (array) $profiles as $tid => $p ) {
        if ( ! empty( $p['view'] ) || empty( $p['classes'] ) ) {
            continue;
        }
        $own  = vergeml_filing_canon( vergeml_filing_name_class( isset( $p['path'] ) && $p['path'] ? end( $p['path'] ) : '' ) );
        $keep = array();
        foreach ( (array) $p['classes'] as $c ) {
            $cc = vergeml_filing_canon( $c );
            $wc = explode( ' ', $cc );
            $hd = (string) end( $wc );
            $by = isset( $named[ $cc ] ) ? $cc : ( count( $wc ) > 1 && isset( $named[ $hd ] ) ? $hd : '' );
            if ( '' !== $by && $by !== $own && ! isset( $named[ $by ][ (int) $tid ] ) && ! isset( $p['words'][ $c ] ) ) {
                continue;
            }
            $keep[] = $c;
        }
        $profiles[ $tid ]['classes'] = $keep;
    }
    return $profiles;
}

/**
 *  A view of the tree owns nothing (S15, HEMA's tree). A shop repeats its
 *  departments under "sale" and "nieuwe collectie": ten of sale's eleven
 *  children and eleven of nieuwe collectie's twelve were the tree's own
 *  top-level names, where a real department repeats one in twelve ("baby"
 *  under wonen en slapen). Asked what "nieuwe collectie" holds, the planner
 *  answered with the library's vocabulary -- 24 classes against 3 to 11 on
 *  every other parent -- and the folder took 201 of 626 pictures on the
 *  first round, every one of them some department's. A folder more than
 *  half of whose children repeat names held higher in the tree is a view,
 *  not a folder for a kind of thing (higher, because the view's own copy
 *  of a department repeats that department's children in turn -- sale ›
 *  home › furniture is deeper than home › furniture, and the department is
 *  no view for it): it and everything under it keep
 *  no class and no vector, so nothing lands there by words or by likeness,
 *  and the copy of a name under it is never filed into -- the folder of
 *  that name elsewhere is the one. What a person puts there stays (the
 *  pick never files out of anything but a fill's own placement), and is
 *  not learned from: a sale is curated, not a kind. Pure; 'view' carries
 *  the view's own term id on it and on each descendant.
 *
 *  Judged first and not built: reading a planner profile past eight
 *  classes from its name alone took HEMA's round 1 from fits 422 to 140,
 *  because buiten en onderweg (bicycle, hiking boot, footwear, luggage,
 *  sport: eleven) is a department.
 */
function vergeml_filing_views( $profiles ) {
    $children = array();
    $holders  = array();
    foreach ( $profiles as $tid => $p ) {
        $children[ (int) ( isset( $p['parent_id'] ) ? $p['parent_id'] : 0 ) ][] = (int) $tid;
        $holders[ vergeml_filing_canon( isset( $p['path'] ) && $p['path'] ? end( $p['path'] ) : '' ) ][] = (int) $tid;
    }
    $under = function ( $tid, $top ) use ( $profiles ) {
        for ( $steps = 0; $tid && $steps < 32; $steps++ ) {
            if ( $tid === $top ) {
                return true;
            }
            $tid = isset( $profiles[ $tid ]['parent_id'] ) ? (int) $profiles[ $tid ]['parent_id'] : 0;
        }
        return false;
    };
    $views = array();
    foreach ( $children as $pid => $kids ) {
        if ( ! $pid || ! isset( $profiles[ $pid ] ) ) {
            continue;
        }
        $repeat = 0;
        $named  = 0;
        foreach ( $kids as $kid ) {
            $leaf = (string) end( $profiles[ $kid ]['path'] );
            // A child named for an audience (Watches › Men, Women) is a split, never a repeat and never a vote: on the C.5 tree Men and Women sit at the top too (2026-09-17).
            if ( '' !== vergeml_filing_audience_of( $leaf ) ) {
                continue;
            }
            $named++;
            $name  = vergeml_filing_canon( $leaf );
            $depth = count( (array) $profiles[ $kid ]['path'] );
            foreach ( isset( $holders[ $name ] ) ? $holders[ $name ] : array() as $other ) {
                if ( $other !== $kid && count( (array) $profiles[ $other ]['path'] ) < $depth ) {
                    $repeat++;
                    break;
                }
            }
        }
        if ( $repeat * 2 > $named ) {
            $views[] = $pid;
        }
    }
    foreach ( $views as $view ) {
        foreach ( $profiles as $tid => $p ) {
            if ( empty( $p['view'] ) && $under( (int) $tid, $view ) ) {
                $profiles[ $tid ]['view']    = $view;
                $profiles[ $tid ]['classes'] = array();
                $profiles[ $tid ]['vector']  = null;
            }
        }
    }
    return $profiles;
}


/* ------------------------------------------------- profiles from members */

/*
 *  The fill learns from its own placements (S10.7). A folder holding this
 *  many described pictures is profiled from them, and that profile outranks
 *  the planner's and the name's: what a folder holds is better evidence of
 *  what it is for than what a planner guessed from its name -- on the shop
 *  (2026-09-16) the planner gave Garden "power tool, architecture" and six of
 *  eight wrong likelies sat there; on the tech library the folder names are
 *  not the pictures' words at all (sure 34 %). Two is a coincidence; three
 *  is a folder.
 */
const VERGEML_FILING_MEMBERS_MIN = 3;

/*
 *  How many of the members' words a profile carries. The pick matches every
 *  picture phrase against every folder class, memoised per pair, and a dry
 *  run of 626 pictures against 319 folders (C.5) is inside its twenty
 *  seconds at three or four classes a folder; eight keeps a folder of many
 *  things under that, and the words past the eighth most carried are the
 *  odd pictures, not the folder.
 */
const VERGEML_FILING_MEMBERS_WORDS = 8;

/*
 *  A word is the folder's when this many members say it. On the tech library
 *  (2026-09-17, the first read) every folder held a few of the fill's own
 *  misses -- Space held "conference venue, computer lab, hallway", Cooling
 *  "cable reels, brewery production line" -- and each became a class of the
 *  folder that held it, so "smart speaker" was a word on Batteries and on
 *  Components at once and 32 pictures tied between them (margin 36 -> 104).
 *  A word one picture says is that picture, not the folder; a folder whose
 *  members agree on nothing keeps the profile it had.
 */
const VERGEML_FILING_MEMBERS_AGREE = 2;

/** Term meta: the layer a folder's members make, with the stamp of the members it was built from. */
const VERGEML_FILING_META_MEMBERS = '_vergeml_profile_members';

/**
 *  The layer a folder's members make. Pure: the members are facts
 *  (vergeml_filing_facts()), and the answer is their object words -- the
 *  first phrase of each, never the class half: every folder under Clothing
 *  would hold "clothing" and a word every folder holds is worth 1/k on each
 *  (vergeml_filing_settle_claims) -- spelled the one way, most carried
 *  first, and the centroid of their vectors. Null under MEMBERS_MIN.
 *
 *  Every member counts, whatever the fill scored it (S14, judged dry on
 *  both sheets): counting only sure and hand placements moved 3 of the S13
 *  sheet's 17 wrong likelies on the tech library -- its misses were taught
 *  by sure members -- and on the shop took Watches' and Laptops' right,
 *  likely members out of their layers, so Smart watches and Keyboards took
 *  their pictures, sure. A placement's confidence does not say whether the
 *  word it teaches is true.
 *
 *  The class halves ride along as 'halves' -- spelled the one way, the ones
 *  AGREE members say -- not as the folder's words but as what the library
 *  says (S15): a planner class that the pictures of other folders say as
 *  their class half is a library word, and vergeml_filing_settle_claims
 *  counts those folders into its k.
 *
 *  @return array|null 'n', 'classes', 'words' (class => members carrying it), 'halves' (canon half => members), 'vector', 'built_at'.
 */
function vergeml_filing_members_layer( $members ) {
    $members = array_values( (array) $members );
    if ( count( $members ) < VERGEML_FILING_MEMBERS_MIN ) {
        return null;
    }
    $count   = array();
    $seen    = array();
    $halves  = array();
    $vectors = array();
    foreach ( $members as $m ) {
        $object = isset( $m['classes'][0] ) ? vergeml_filing_name_class( $m['classes'][0] ) : '';
        $key    = vergeml_filing_canon( $object );
        if ( '' !== $key && ! in_array( $key, vergeml_filing_kind_words(), true ) ) {
            $count[ $key ] = ( isset( $count[ $key ] ) ? $count[ $key ] : 0 ) + 1;
            if ( ! isset( $seen[ $key ] ) ) {
                $seen[ $key ] = $object; // The first spelling met, as the describer wrote it.
            }
        }
        $half = isset( $m['classes'][1] ) ? vergeml_filing_canon( $m['classes'][1] ) : '';
        if ( '' !== $half && ! in_array( $half, vergeml_filing_kind_words(), true ) ) {
            $halves[ $half ] = ( isset( $halves[ $half ] ) ? $halves[ $half ] : 0 ) + 1;
        }
        if ( isset( $m['vector'] ) && is_array( $m['vector'] ) && $m['vector'] ) {
            $vectors[] = $m['vector'];
        }
    }
    $halves = array_filter( $halves, function ( $n ) { return $n >= VERGEML_FILING_MEMBERS_AGREE; } );
    arsort( $count ); // Stable: ties keep the order met, which is the members' own.
    $classes = array();
    $words   = array();
    foreach ( array_slice( $count, 0, VERGEML_FILING_MEMBERS_WORDS, true ) as $key => $n ) {
        if ( $n < VERGEML_FILING_MEMBERS_AGREE ) {
            break;
        }
        $classes[]                  = $seen[ $key ];
        $words[ (string) $seen[ $key ] ] = (int) $n;
    }
    if ( ! $classes ) {
        return null; // Members that agree on nothing say nothing about the folder.
    }
    return array(
        'n'        => count( $members ),
        'classes'  => $classes,
        'words'    => $words,
        'halves'   => $halves,
        'vector'   => vergeml_filing_centroid( $vectors ),
        'built_at' => time(),
    );
}

/**
 *  A member word only where it is alike to the folder's own (S16). Nathan's
 *  S15 verdict on the tech library (2026-09-18): six of the seven wrong
 *  sures stood on words the folders had learned from the fill's own earlier
 *  misses -- Cooling learned "cable reels" from fibre reels the fill had put
 *  there, Server racks "network switches", Components "smart speaker" -- a
 *  members layer built on a round that was 34 % right entrenches its misses
 *  as sures. So a member word counts only where it is alike to one of the
 *  base profile's words, the plan's or the name's: spelled the one way, one
 *  inside the other or its head noun (vergeml_filing_class_match), or the
 *  phrase vector at the members' own floor, VERGEML_FILING_MEMBERS_ALIKE
 *  (0.5, under the class match's 0.6: the rightful words sit at 0.51-0.58).
 *  Hardware keeps "graphics card" (0.57 to computer hardware), Cooling does
 *  not keep "cable reel" (0.27 to cooling). Judged before the settle, so a
 *  word a folder cannot keep is not the one its rivals cede to; a layer
 *  left with no word is no layer. The halves stay: they only count k. Pure.
 */
function vergeml_filing_members_alike( $layers, $profiles ) {
    $alike = function ( $a, $b ) {
        if ( vergeml_filing_class_match( $a, $b, true ) > 0.0 ) {
            return true;
        }
        $va = vergeml_filing_phrase_vector( $a );
        $vb = vergeml_filing_phrase_vector( $b );
        return null !== $va && null !== $vb && vergeml_filing_cosine( $va['v'], $va['n'], $vb['v'], $vb['n'] ) >= VERGEML_FILING_MEMBERS_ALIKE;
    };
    foreach ( (array) $layers as $tid => $l ) {
        $p    = isset( $profiles[ $tid ] ) ? $profiles[ $tid ] : array();
        $own  = (array) ( isset( $p['classes'] ) ? $p['classes'] : array() );
        $leaf = vergeml_filing_name_class( isset( $p['path'] ) && $p['path'] ? end( $p['path'] ) : '' );
        if ( '' !== $leaf ) {
            $own[] = $leaf;
        }
        $keep  = array();
        $words = array();
        foreach ( (array) $l['classes'] as $c ) {
            foreach ( $own as $base ) {
                if ( $alike( $c, $base ) ) {
                    $keep[]      = $c;
                    $words[ $c ] = isset( $l['words'][ $c ] ) ? (int) $l['words'][ $c ] : 0;
                    break;
                }
            }
        }
        if ( ! $keep ) {
            unset( $layers[ $tid ] );
            continue;
        }
        $layers[ $tid ]['classes'] = $keep;
        $layers[ $tid ]['words']   = $words;
    }
    return $layers;
}

/**
 *  One folder per member word. A word belongs to the folder holding most of
 *  the pictures that say it, as a planner's class belongs to one folder
 *  (vergeml_filing_clean_seed): on the tech library (2026-09-17) the fill
 *  had put two smart speakers in Batteries and eight in Components, both
 *  folders learned the word, and 34 pictures tied between them. The others
 *  cede it and say so ('ceded', word => the folder); equal counts keep it
 *  on both, an honest tie worth 1/k. A layer left with no word is no layer.
 *  Pure, over the layers of one read.
 */
function vergeml_filing_members_settle( $layers ) {
    $holders = array();
    foreach ( (array) $layers as $tid => $l ) {
        foreach ( (array) $l['words'] as $w => $n ) {
            $holders[ vergeml_filing_canon( $w ) ][ (int) $tid ] = (int) $n;
        }
    }
    foreach ( (array) $layers as $tid => $l ) {
        $keep  = array();
        $words = array();
        $ceded = array();
        foreach ( (array) $l['classes'] as $c ) {
            $key = vergeml_filing_canon( $c );
            $n   = isset( $l['words'][ $c ] ) ? (int) $l['words'][ $c ] : 0;
            $most = isset( $holders[ $key ] ) ? max( $holders[ $key ] ) : $n;
            if ( $most > $n ) {
                $ceded[ $c ] = (int) array_search( $most, $holders[ $key ], true );
                continue;
            }
            $keep[]      = $c;
            $words[ $c ] = $n;
        }
        if ( ! $keep ) {
            unset( $layers[ $tid ] );
            continue;
        }
        $layers[ $tid ]['classes'] = $keep;
        $layers[ $tid ]['words']   = $words;
        $layers[ $tid ]['ceded']   = $ceded;
    }
    return $layers;
}

/**
 *  A profile read over its members' layer: the members' words first, the
 *  base's own (a plan's, or the name) after them so what the planner said
 *  a folder also takes still reaches it, the leaf kept, the vector the
 *  centroid. 'source' says members and 'base_source' what it was; the plan
 *  stays as it was for the next rebuild. Pure; a null layer changes nothing.
 */
function vergeml_filing_members_apply( $profile, $layer ) {
    if ( ! is_array( $layer ) || empty( $layer['classes'] ) ) {
        return $profile;
    }
    $classes = array();
    $canons  = array();
    foreach ( array_merge( (array) $layer['classes'], (array) $profile['classes'] ) as $c ) {
        $key = vergeml_filing_canon( $c );
        if ( '' !== $key && ! isset( $canons[ $key ] ) ) {
            $canons[ $key ] = true;
            $classes[]      = $c;
        }
    }
    $profile['base_source'] = isset( $profile['base_source'] ) ? $profile['base_source'] : $profile['source'];
    $profile['source']      = 'members';
    $profile['members']     = (int) $layer['n'];
    $profile['words']       = (array) $layer['words'];
    $profile['halves']      = isset( $layer['halves'] ) ? (array) $layer['halves'] : array();
    $profile['classes']     = $classes;
    if ( is_array( $layer['vector'] ) && $layer['vector'] ) {
        $profile['vector']   = $layer['vector'];
        $profile['built_at'] = (int) $layer['built_at']; // The norm cache keys on it: a new vector, a new key.
    }
    return $profile;
}

/**
 *  The members' layers for a set of folders, keyed by term id; folders under
 *  MEMBERS_MIN are left out. Kept in term meta with a stamp of the members
 *  it was built from -- how many, which (their ids summed and squared), and
 *  when the last was described -- and rebuilt when the stamp moves: a fill,
 *  a hand placement, an undo, a re-describe all move it, and no hook has to
 *  know (vergeml_talk_answer takes the term hooks off around its write).
 *  One light query reads every stamp; one more reads the rows of the
 *  folders whose layer is stale.
 */
function vergeml_filing_members_layers( $term_ids, $taxonomy ) {
    global $wpdb;
    $term_ids = array_values( array_unique( array_map( 'intval', (array) $term_ids ) ) );
    if ( ! $term_ids || ! isset( $wpdb->vergeml_ai_index ) ) {
        return array();
    }
    $in = implode( ',', $term_ids );
    // A locked folder is never a candidate, so it has no layer and owns no word: on the tech library To sort held seven
    // smart speakers, won the word, and Batteries and Components both ceded it (2026-09-17).
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- this plugin's own table; ids are integers.
    $stamps = $wpdb->get_results( $wpdb->prepare(
        "SELECT tt.term_id, COUNT(*) AS n, SUM(i.attachment_id) AS ids, SUM(i.attachment_id * i.attachment_id) AS sq, MAX(i.described_at) AS last
           FROM {$wpdb->term_relationships} tr
           JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
           JOIN {$wpdb->vergeml_ai_index} i ON i.attachment_id = tr.object_id
           LEFT JOIN {$wpdb->termmeta} lk ON lk.term_id = tt.term_id AND lk.meta_key = %s AND lk.meta_value = '1'
          WHERE tt.taxonomy = %s AND tt.term_id IN ({$in}) AND lk.term_id IS NULL AND i.error = '' AND i.filing IS NOT NULL AND i.filing <> ''
       GROUP BY tt.term_id",
        VERGEML_FILING_LOCKED,
        $taxonomy
    ), ARRAY_A );
    $out   = array();
    $stale = array();
    foreach ( (array) $stamps as $s ) {
        if ( (int) $s['n'] < VERGEML_FILING_MEMBERS_MIN ) {
            continue;
        }
        $tid   = (int) $s['term_id'];
        // The rule's own numbers are in the stamp: a changed rule rebuilds every layer, the members unchanged ('h': the layer carries the class halves, S15).
        $stamp = VERGEML_FILING_MEMBERS_MIN . '/' . VERGEML_FILING_MEMBERS_AGREE . '/' . VERGEML_FILING_MEMBERS_WORDS . '/h:' . $s['n'] . ':' . $s['ids'] . ':' . $s['sq'] . ':' . $s['last'];
        $meta  = get_term_meta( $tid, VERGEML_FILING_META_MEMBERS, true );
        if ( is_array( $meta ) && isset( $meta['stamp'] ) && $meta['stamp'] === $stamp && ! empty( $meta['classes'] ) ) {
            $out[ $tid ] = $meta;
        } else {
            $stale[ $tid ] = $stamp;
        }
    }
    if ( ! $stale ) {
        return $out;
    }
    $in   = implode( ',', array_keys( $stale ) );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT tt.term_id, i.attachment_id, i.filing, i.kind, i.embedding
           FROM {$wpdb->term_relationships} tr
           JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
           JOIN {$wpdb->vergeml_ai_index} i ON i.attachment_id = tr.object_id
          WHERE tt.taxonomy = %s AND tt.term_id IN ({$in}) AND i.error = '' AND i.filing IS NOT NULL AND i.filing <> ''
       ORDER BY tt.term_id, i.attachment_id",
        $taxonomy
    ), ARRAY_A );
    // phpcs:enable
    $members = array();
    foreach ( (array) $rows as $row ) {
        $members[ (int) $row['term_id'] ][] = vergeml_filing_facts( $row );
    }
    foreach ( $stale as $tid => $stamp ) {
        $layer = vergeml_filing_members_layer( isset( $members[ $tid ] ) ? $members[ $tid ] : array() );
        if ( ! is_array( $layer ) ) {
            delete_term_meta( $tid, VERGEML_FILING_META_MEMBERS );
            continue;
        }
        $layer['stamp'] = $stamp;
        update_term_meta( $tid, VERGEML_FILING_META_MEMBERS, $layer );
        $out[ $tid ] = $layer;
    }
    return $out;
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
    /*
     *  Counted by lines (S16, 2026-09-17): a folder whose ancestor holds the
     *  same word is that ancestor's line, not a second holder. On HEMA's
     *  tree, once the planner named the Dutch leaves, buiten en onderweg
     *  and its leaf both held "hiking boot" and 1/k halved it on each --
     *  the boots went to the floor (round 1 fits 297 -> 203; by lines 247,
     *  sure 159 -> 195). A department holds the word because its leaf
     *  does; between the two the depth rule chooses.
     */
    $holders = array();
    foreach ( $profiles as $tid => $p ) {
        foreach ( array_unique( array_map( 'vergeml_filing_group_key', (array) $p['classes'] ) ) as $key ) {
            $holders[ $key ][ (int) $tid ] = true;
        }
    }
    $shared = array();
    foreach ( $holders as $key => $tids ) {
        $shared[ $key ] = vergeml_filing_lines( $tids, $profiles );
    }

    /*
     *  And the folders whose pictures say the word (S15). The planner put
     *  "electronics" on Batteries alone, so it was worth 1.0 there; on the
     *  tech library (2026-09-17) 93 pictures said "electronics" as their
     *  class half and sat in Components, Phones and Hardware -- none in
     *  Batteries -- and "vr headset; electronics" went to Batteries, likely,
     *  on a word its own pictures never say. A class half the members of
     *  several folders say is the library's word, not the one folder's: k
     *  counts those folders too. The class is the library's when it is what
     *  they say, or sits inside it ("electronics" in "consumer electronics")
     *  -- never when it contains it: "desktop computer interior", four
     *  Hardware members' own object, is not the library's word "computer",
     *  and read that way it took Hardware's sure placements down (the first
     *  cut, 2026-09-17: sure 599 -> 580). A word one folder's pictures say
     *  keeps k 1 and still places alone; a word two say cannot -- 0.85 x
     *  0.85 / 2 is under the floor -- and the pick falls to the object and
     *  the vector.
     */
    $says = array();
    foreach ( $profiles as $tid => $p ) {
        foreach ( array_keys( isset( $p['halves'] ) ? (array) $p['halves'] : array() ) as $half ) {
            $says[ $half ][ (int) $tid ] = true;
        }
    }
    if ( $says ) {
        foreach ( $profiles as $p ) {
            foreach ( (array) $p['classes'] as $fc ) {
                $key  = vergeml_filing_group_key( $fc );
                $cf   = ' ' . vergeml_filing_canon( $fc ) . ' ';
                $said = array();
                foreach ( $says as $half => $tids ) {
                    if ( $cf === ' ' . $half . ' ' || vergeml_filing_inside( $fc, $half ) ) {
                        $said += $tids;
                    }
                }
                $lines = vergeml_filing_lines( $said, $profiles );
                if ( $lines > $shared[ $key ] ) {
                    $shared[ $key ] = $lines;
                }
            }
        }
    }

    foreach ( $profiles as $tid => $p ) {
        $profiles[ $tid ]['shared'] = $shared;
    }
    return $profiles;
}

/** How many lines a set of folders (term id => true) is: one per folder with no ancestor in the set. */
function vergeml_filing_lines( $tids, $profiles ) {
    $n = 0;
    foreach ( array_keys( (array) $tids ) as $tid ) {
        $anc = isset( $profiles[ $tid ]['parent_id'] ) ? (int) $profiles[ $tid ]['parent_id'] : 0;
        $up  = false;
        for ( $g = 0; $anc && $g < 32; $g++ ) {
            if ( isset( $tids[ $anc ] ) ) {
                $up = true;
                break;
            }
            $anc = isset( $profiles[ $anc ]['parent_id'] ) ? (int) $profiles[ $anc ]['parent_id'] : 0;
        }
        if ( ! $up ) {
            $n++;
        }
    }
    return max( 1, $n );
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
        } elseif ( in_array( $w, $irregular, true ) ) {
            // Already the singular the table gives: "lens" is not "len" (S16).
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

/** Whether one-word phrase $a is the head noun (last word) of the longer phrase $b, both spelled the one way: "space" of "interior space". */
function vergeml_filing_head_of( $a, $b ) {
    $ca = vergeml_filing_canon( $a );
    $wb = explode( ' ', vergeml_filing_canon( $b ) );
    return '' !== $ca && false === mb_strpos( $ca, ' ' ) && count( $wb ) > 1 && end( $wb ) === $ca;
}

/** Whether phrase $a sits whole inside phrase $b, both spelled the one way, and is not $b: "electronics" inside "consumer electronics". */
function vergeml_filing_inside( $a, $b ) {
    $ca = vergeml_filing_canon( $a );
    $cb = vergeml_filing_canon( $b );
    return '' !== $ca && $ca !== $cb && false !== mb_strpos( ' ' . $cb . ' ', ' ' . $ca . ' ' );
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
 *  The picture's own words (S10.9): the filename split on -_. and space,
 *  the title and the alt, lowercased, each word once, in the order met. The
 *  extension goes, so do words under three letters and numbers -- a shop's
 *  "summer-dress-red-front.jpg" says summer, dress, red, front; a
 *  photographer's "2024-06-smith-wedding-012.jpg" says smith, wedding; a
 *  camera's "IMG_4021.HEIC" says nothing.
 *
 *  @return string[]
 */
function vergeml_filing_words_of( $file, $title, $alt ) {
    $name = (string) $file;
    $name = '' === $name ? '' : preg_replace( '/\.[a-z0-9]{2,5}$/iu', '', basename( str_replace( '\\', '/', $name ) ) );
    $out  = array();
    foreach ( array( $name, (string) $title, (string) $alt ) as $text ) {
        foreach ( (array) preg_split( '/[\s\-_.,;:\/()\[\]"\'!?]+/u', mb_strtolower( $text ) ) as $w ) {
            if ( mb_strlen( $w ) < 3 || preg_match( '/^\d+$/u', $w ) || in_array( $w, $out, true ) ) {
                continue;
            }
            $out[] = $w;
        }
    }
    return $out;
}

/**
 *  One of the picture's words against a folder class, by words alone: 1
 *  when it is the class spelled the one way ("dress" is dresses), 0.95 when
 *  it is the class's head noun ("dress" is a summer dress), else 0 -- never
 *  a modifier (a keyboard is not a keyboard layout diagram), never by
 *  vector: a filename's "red" and "front" against every folder would ask
 *  the service a question per pair.
 */
function vergeml_filing_word_match( $word, $class ) {
    $w = vergeml_filing_canon( $word );
    $c = vergeml_filing_canon( $class );
    if ( '' === $w || '' === $c ) {
        return 0.0;
    }
    if ( $w === $c ) {
        return 1.0;
    }
    $cw = explode( ' ', $c );
    return count( $cw ) > 1 && end( $cw ) === $w ? 0.95 : 0.0;
}

/**
 *  The SELECT and JOIN fragments a reader adds so a row carries the picture's
 *  file, title and alt (S10.9); $i is the index table's alias. The alt counts
 *  only when a person wrote it: the describer's own alt (the index keeps it,
 *  and the AI screen counts "alt equals the model's" the same way) is a
 *  sentence naming everything in the scene, and on the tech library, where
 *  every alt is the model's, it doubled the ties (margin 62 -> 162 against
 *  104 from the file and title alone, 2026-09-17).
 */
function vergeml_filing_words_sql( $i = 'i' ) {
    global $wpdb;
    return array(
        'select' => "wp_p.post_title AS title, wp_f.meta_value AS file, CASE WHEN wp_a.meta_value = {$i}.alt THEN '' ELSE wp_a.meta_value END AS alt",
        'join'   => "LEFT JOIN {$wpdb->posts} wp_p ON wp_p.ID = {$i}.attachment_id
           LEFT JOIN {$wpdb->postmeta} wp_f ON wp_f.post_id = {$i}.attachment_id AND wp_f.meta_key = '_wp_attached_file'
           LEFT JOIN {$wpdb->postmeta} wp_a ON wp_a.post_id = {$i}.attachment_id AND wp_a.meta_key = '_wp_attachment_image_alt'",
    );
}

/**
 *  The SELECT and JOIN fragments a reader adds so a row carries the product
 *  the picture belongs to (S10.8): the product it is the featured image of
 *  (_thumbnail_id), the one whose gallery lists it (_product_image_gallery),
 *  or the one it was uploaded to (post_parent) -- the first that is a
 *  product, as product_id, else NULL. Each is one correlated lookup that
 *  narrows on the meta key first, so a picture in no shop costs three index
 *  reads. Without WooCommerce's product type there is nothing to join: the
 *  column is NULL and the join empty.
 */
function vergeml_filing_product_sql( $i = 'i' ) {
    global $wpdb;
    if ( ! function_exists( 'post_type_exists' ) || ! post_type_exists( 'product' ) ) {
        return array( 'select' => 'NULL AS product_id', 'join' => '' );
    }
    return array(
        'select' => "COALESCE(
            ( SELECT vp1.ID FROM {$wpdb->postmeta} vm1 JOIN {$wpdb->posts} vp1 ON vp1.ID = vm1.post_id AND vp1.post_type = 'product'
               WHERE vm1.meta_key = '_thumbnail_id' AND vm1.meta_value = CAST({$i}.attachment_id AS CHAR) LIMIT 1 ),
            ( SELECT vp2.ID FROM {$wpdb->postmeta} vm2 JOIN {$wpdb->posts} vp2 ON vp2.ID = vm2.post_id AND vp2.post_type = 'product'
               WHERE vm2.meta_key = '_product_image_gallery' AND FIND_IN_SET({$i}.attachment_id, vm2.meta_value) LIMIT 1 ),
            ( SELECT vp3.ID FROM {$wpdb->posts} va3 JOIN {$wpdb->posts} vp3 ON vp3.ID = va3.post_parent AND vp3.post_type = 'product'
               WHERE va3.ID = {$i}.attachment_id LIMIT 1 )
          ) AS product_id",
        'join'   => '',
    );
}

/**
 *  The product's folder on each row (S10.8): product_id -> the folder its
 *  categories name, through vergeml_filing_product_folder, as
 *  'product_folder'. One terms query for the slice's products; a product
 *  in several categories takes the deepest path that names a folder. Rows
 *  with no product, or whose product's categories name no folder, are left
 *  as they are (the matcher's).
 */
function vergeml_filing_product_folders( $rows, $profiles ) {
    $products = array();
    foreach ( (array) $rows as $k => $r ) {
        if ( ! empty( $r['product_id'] ) ) {
            $products[ (int) $r['product_id'] ][] = $k;
        }
    }
    if ( ! $products || ! function_exists( 'taxonomy_exists' ) || ! taxonomy_exists( 'product_cat' ) ) {
        return $rows;
    }
    $terms = wp_get_object_terms( array_keys( $products ), 'product_cat', array( 'fields' => 'all_with_object_id' ) );
    if ( is_wp_error( $terms ) ) {
        return $rows;
    }
    $paths = array();
    foreach ( (array) $terms as $t ) {
        $path = array();
        foreach ( array_reverse( get_ancestors( (int) $t->term_id, 'product_cat', 'taxonomy' ) ) as $anc ) {
            $a      = get_term( (int) $anc, 'product_cat' );
            $path[] = $a instanceof WP_Term ? vergeml_term_name( $a ) : '';
        }
        $path[] = vergeml_term_name( $t );
        $paths[ (int) $t->object_id ][] = $path;
    }
    foreach ( $products as $pid => $keys ) {
        if ( empty( $paths[ $pid ] ) ) {
            continue;
        }
        usort( $paths[ $pid ], function ( $a, $b ) { return count( $b ) <=> count( $a ); } );
        foreach ( $paths[ $pid ] as $path ) {
            $tid = vergeml_filing_product_folder( $path, $profiles );
            if ( $tid ) {
                foreach ( $keys as $k ) {
                    $rows[ $k ]['product_folder'] = $tid;
                }
                break;
            }
        }
    }
    return $rows;
}

/**
 *  The tree as the text model reads it (S18): every folder that is not a
 *  view, as a path "Parent > Child", sorted, To sort never among them; the
 *  term id behind each path by its canon spelling; and a hash of the paths,
 *  so an answer is cached against the tree it was given for.
 *
 *  @return array 'paths' (string[]), 'by_path' (canon path => term id), 'hash'.
 */
function vergeml_filing_model_tree( $profiles ) {
    $paths   = array();
    $by_path = array();
    foreach ( (array) $profiles as $tid => $p ) {
        if ( ! empty( $p['view'] ) || empty( $p['path'] ) ) {
            continue;
        }
        $path = implode( ' > ', array_map( 'strval', (array) $p['path'] ) );
        if ( preg_match( '/^to sort$/i', $path ) ) {
            continue;
        }
        $paths[]                                                          = $path;
        $by_path[ implode( ' > ', array_map( 'vergeml_filing_canon', (array) $p['path'] ) ) ] = (int) $tid;
    }
    sort( $paths );
    return array( 'paths' => $paths, 'by_path' => $by_path, 'hash' => md5( implode( "\n", $paths ) ) );
}

/** What a row says to the model: the describer's phrases, then its caption. */
function vergeml_filing_model_says( $row ) {
    $filing = isset( $row['filing'] ) ? json_decode( (string) $row['filing'], true ) : null;
    $filing = is_array( $filing ) ? $filing : array();
    return array(
        'says'    => implode( '; ', vergeml_filing_classes_of_object( isset( $filing['object'] ) ? $filing['object'] : '' ) ),
        'caption' => isset( $row['caption'] ) ? (string) $row['caption'] : '',
    );
}

/** The cache key of one picture's answer: the tree's paths and the picture's description, a week. */
function vergeml_filing_model_key( $tree_hash, $row ) {
    $d = vergeml_filing_model_says( $row );
    return 'vergeml_fm_' . $tree_hash . ':' . md5( $d['says'] . "\n" . $d['caption'] );
}

/**
 *  The text model's word on each row (S18, plans/agree-or-ask.md): the
 *  service's /file reads the tree as paths and forty descriptions a call
 *  and names a folder or none for each. On the row as 'model_folder': a
 *  term id, 0 for "nothing of this tree fits" (null, or a path the tree
 *  does not hold), -1 for unasked -- placed already (by hand, by an answer,
 *  by a product) or in a locked folder, so the pick decides before the
 *  model; no licence; the service down. Asked once per picture per tree:
 *  the answer sits in a transient keyed by the tree's paths and the
 *  picture's description for a week, and a cached picture is not sent. A
 *  failed call leaves the rest of the batch and the batches after it
 *  unasked; the pick then runs rules-only, as before S18. Metered on the
 *  service, not debited, until the call is priced.
 */
function vergeml_filing_ask_model( $rows, $profiles ) {
    $rows = (array) $rows;
    $tree = vergeml_filing_model_tree( $profiles );
    foreach ( $rows as $k => $r ) {
        $rows[ $k ]['model_folder'] = -1;
    }
    if ( ! $tree['paths'] ) {
        return $rows;
    }

    $licence = '';
    if ( function_exists( 'vergeml_ai_settings' ) && function_exists( 'vergeml_ai_unseal' ) ) {
        $settings = vergeml_ai_settings();
        $licence  = vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
    }

    $ask = array(); // row key => the picture as sent.
    foreach ( $rows as $k => $r ) {
        if ( in_array( (string) ( isset( $r['placed_by'] ) ? $r['placed_by'] : '' ), array( 'user', 'answer', 'product' ), true ) || ! empty( $r['in_locked'] ) || ! empty( $r['product_folder'] ) ) {
            continue;
        }
        $d = vergeml_filing_model_says( $r );
        if ( '' === $d['says'] && '' === $d['caption'] ) {
            continue;
        }
        $cached = get_transient( vergeml_filing_model_key( $tree['hash'], $r ) );
        if ( false !== $cached ) {
            $rows[ $k ]['model_folder'] = (int) $cached;
            continue;
        }
        $ask[ $k ] = array( 'id' => (int) $r['attachment_id'], 'says' => $d['says'], 'caption' => $d['caption'] );
    }
    if ( ! $ask || '' === $licence ) {
        return $rows;
    }

    foreach ( array_chunk( array_keys( $ask ), 40 ) as $keys ) {
        $response = wp_remote_post(
            vergeml_ai_service_url() . '/file',
            array(
                'timeout'   => 30,
                'headers'   => array( 'Content-Type' => 'application/json' ),
                'sslverify' => true,
                'body'      => wp_json_encode( array(
                    'license_key' => $licence,
                    'site'        => home_url(),
                    'folders'     => $tree['paths'],
                    'pictures'    => array_values( array_intersect_key( $ask, array_flip( $keys ) ) ),
                ) ),
            )
        );
        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return $rows; // Unasked from here on: the pick runs rules-only for these.
        }
        $data    = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        $answers = is_array( $data ) && isset( $data['answers'] ) && is_array( $data['answers'] ) ? $data['answers'] : array();
        foreach ( $keys as $k ) {
            $path = isset( $answers[ (string) $ask[ $k ]['id'] ] ) ? $answers[ (string) $ask[ $k ]['id'] ] : null;
            $tid  = 0;
            if ( is_string( $path ) && '' !== $path ) {
                $canon = implode( ' > ', array_map( 'vergeml_filing_canon', preg_split( '/\s*>\s*/', $path ) ) );
                $tid   = isset( $tree['by_path'][ $canon ] ) ? (int) $tree['by_path'][ $canon ] : 0;
            }
            $rows[ $k ]['model_folder'] = $tid;
            set_transient( vergeml_filing_model_key( $tree['hash'], $rows[ $k ] ), $tid, WEEK_IN_SECONDS );
        }
    }
    return $rows;
}

/**
 *  The picture's side of the match, as the caller reads it off the index row.
 *
 *  @param array $row An index row: 'filing' (json), 'kind', 'embedding'; 'placed_by' and
 *                    'in_locked' (sits in a locked folder) when the caller joined them;
 *                    'file', 'title', 'alt' (vergeml_filing_words_sql) or 'words' ready-made.
 *  @return array 'classes', 'kind', 'audience', 'vector', 'placed_by', 'in_locked', 'words'.
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
        'words'     => isset( $row['words'] ) && is_array( $row['words'] ) ? $row['words'] : vergeml_filing_words_of( isset( $row['file'] ) ? $row['file'] : '', isset( $row['title'] ) ? $row['title'] : '', isset( $row['alt'] ) ? $row['alt'] : '' ),
        'product'   => isset( $row['product_folder'] ) ? (int) $row['product_folder'] : 0, // The folder its product's categories name (S10.8), resolved by the caller.
        'model'     => isset( $row['model_folder'] ) ? (int) $row['model_folder'] : -1,   // The text model's word (S18): a term id, 0 nothing fits, -1 unasked.
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

    // Placed by the person, by their answer to a question (2026-09-17), or by the product it belongs to (S10.8): decided, and not asked again.
    if ( isset( $facts['placed_by'] ) && in_array( (string) $facts['placed_by'], array( 'user', 'answer', 'product' ), true ) ) {
        return vergeml_filing_outcome( 'nothing', 'placed', array( 'scores' => array(), 'gated' => array() ) );
    }
    if ( ! empty( $facts['in_locked'] ) ) {
        return vergeml_filing_outcome( 'nothing', 'locked', array( 'scores' => array(), 'gated' => array() ) );
    }
    /*
     *  File by the product (S10.8): a picture that is a product's featured
     *  image or in its gallery goes where the product's categories say --
     *  the folder the caller resolved through vergeml_filing_product_folder
     *  -- sure, before any matching. A fact, not a guess: no model, no
     *  score, no runner-up, and a locked or gated folder does not come into
     *  it, because the product owns the picture whatever the folder is for.
     */
    if ( ! empty( $facts['product'] ) && isset( $profiles[ (int) $facts['product'] ] ) ) {
        $tid = (int) $facts['product'];
        return vergeml_filing_outcome( 'fits', 'product', array(
            'term_id'    => $tid,
            'parent_id'  => vergeml_filing_parent_of( $tid, $profiles ),
            'score'      => 1.0,
            'confidence' => 'sure',
            'source'     => 'product',
            'hit'        => 'product',
            'scores'     => array( $tid => 1.0 ),
            'gated'      => array(),
        ) );
    }

    $scores   = array();
    $gated    = array();
    $shadow   = array(); // Folders gated by audience while the picture says none (S17): never the pick, a runner-up at most.
    $evidence = array(); // Per folder, where its best class hit came from (S16): the winner's rides out as 'source' and 'hit'.
    // The picture's vector length once, not once per folder.
    $pnorm  = is_array( $facts['vector'] ) ? vergeml_filing_norm( $facts['vector'] ) : 0.0;

    /*
     *  The head noun against its own class (S10.5 rule 3). "cheese wheel;
     *  cheese" hit Wheels in full by its head noun and Cheese by the phrase
     *  inside, and a wheel of cheese was a wheel; "hair dryer" was a chair's.
     *  The describer's class half says what the thing is: where that half
     *  names some folder in full, a first-phrase hit on a class that is only
     *  the phrase's head noun is capped at 0.9 on every other folder, so the
     *  folder the class half names outranks it. "rocket launch; launch" keeps
     *  its 1.0 on Launches: the class half names Launches itself. Named in
     *  full means what the folder is for -- its first class or its own name:
     *  on the tech library (2026-09-17) "mobile phone; electronics" lost its
     *  1.0 on Phones because the planner had put "electronics" fourth on
     *  Batteries, and a right sure placement became a question. And the
     *  class half must be the phrase's own modifier -- "cheese" of "cheese
     *  wheel" -- because that is the describer saying the thing is the
     *  modifier's kind; "klippan sofa; furniture" on the shop names the
     *  category, the head noun is right, and Sofas lost a sure to Garden ›
     *  Furniture when the cap read every class half.
     */
    $head  = '';
    $full2 = array();
    if ( isset( $facts['classes'][1] ) ) {
        $words = explode( ' ', vergeml_filing_canon( $facts['classes'][0] ) );
        $head  = count( $words ) > 1 ? (string) end( $words ) : '';
        $mod   = ' ' . implode( ' ', array_slice( $words, 0, -1 ) ) . ' ';
        if ( '' !== $head && false === mb_strpos( $mod, ' ' . vergeml_filing_canon( $facts['classes'][1] ) . ' ' ) ) {
            $head = ''; // The class half is not the modifier: nothing contradicts the head noun.
        }
        if ( '' !== $head ) {
            foreach ( $profiles as $tid => $p ) {
                // Only a folder the picture could land in names it: a locked or gated one is nowhere.
                if ( ! empty( $p['locked'] ) || ! in_array( $facts['kind'], (array) $p['kinds'], true ) || ( '' !== $p['audience'] && $facts['audience'] !== $p['audience'] ) ) {
                    continue;
                }
                $own = vergeml_filing_group_key( vergeml_filing_name_class( isset( $p['path'] ) && $p['path'] ? end( $p['path'] ) : '' ) );
                foreach ( array_values( (array) $p['classes'] ) as $rank => $fc ) {
                    if ( ( 0 === $rank || vergeml_filing_group_key( $fc ) === $own ) && vergeml_filing_class_match( $facts['classes'][1], $fc ) >= 1.0 ) {
                        $full2[ (int) $tid ] = true;
                        break;
                    }
                }
            }
        }
    }

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
        /*
         *  Gate: audience. A gendered folder needs the picture to say so.
         *  When the picture says nothing (S17, Nathan's yes): the folder is
         *  still gated -- never the pick -- but its score is kept as a
         *  shadow and stands as the runner-up the margin is judged against.
         *  On the shop's truth (2026-09-18) 44 of 581 had their own men's or
         *  women's folder gated because the describer did not say who a
         *  jacket on a hanger is for, and the namesake took them: motorbikes ›
         *  jackets four wrong sures. A question ("Men's jackets or Motorbike
         *  jackets?") is the honest answer; a picture the describer called
         *  women's stays out of a men's folder for good.
         */
        $shadowed = false;
        if ( '' !== $p['audience'] && $facts['audience'] !== $p['audience'] ) {
            $gated[ $tid ] = 'audience';
            if ( '' !== (string) $facts['audience'] ) {
                continue;
            }
            $shadowed = true;
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
        // The class half names a folder in full, and not this one: this folder's head-noun hits are capped (rule 3).
        $capped = '' !== $head && $full2 && ! ( 1 === count( $full2 ) && isset( $full2[ (int) $tid ] ) );
        foreach ( array_values( (array) $facts['classes'] ) as $pi => $pc ) {
            $phrase = 0 === $pi ? 1.0 : 0.85;
            foreach ( array_values( (array) $p['classes'] ) as $rank => $fc ) {
                $is_leaf = '' !== $leaf && vergeml_filing_group_key( $fc ) === $leaf;
                $match   = vergeml_filing_class_match( $pc, $fc, 0 === $pi );
                if ( 0 === $pi && $capped && $match > 0.9 && vergeml_filing_canon( $fc ) === $head ) {
                    $match = 0.9;
                }
                /*
                 *  The class half is the generic word; sitting whole inside a
                 *  folder's specific phrase it is not that phrase (S15):
                 *  "drone; electronics" is not Components' "electronics
                 *  component", "3d printer; equipment" not Energy's
                 *  "renewable energy equipment" -- on the tech library
                 *  (2026-09-17) 22 pictures with no folder of their own went
                 *  there, likely, by that 0.95. The other way round the
                 *  folder's word is what the picture is ("launch" of "launch
                 *  event") and stands; so does a folder's first class or its
                 *  own leaf ("appliance" under Kitchen appliances).
                 */
                if ( 1 === $pi && 0 !== $rank && ! $is_leaf && vergeml_filing_inside( $pc, $fc ) ) {
                    $match = 0.0;
                }
                /*
                 *  And the other way round, for a one-word class as the
                 *  half's head noun (S16, Nathan's S15 verdict): "hallway;
                 *  interior space" went to Space, "garage; commercial space"
                 *  to Space three times, "griptape; skateboard component" to
                 *  Components -- five of the sheet's thirteen wrong likelies.
                 *  The modifier direction ("launch" of "launch event") is
                 *  what the picture is and stands; a polysemous one-word
                 *  class as the head noun is not. A folder's first class
                 *  keeps it: a name-only folder has nothing else.
                 */
                if ( 1 === $pi && 0 !== $rank && $match > 0.0 && $match < 1.0 && vergeml_filing_head_of( $fc, $pc ) ) {
                    $match = 0.0;
                }
                $weight  = ( 0 === $rank || ( $is_leaf && $match >= 1.0 ) ) ? 1.0 : 0.85;
                $k       = $is_leaf ? 1 : max( 1, (int) ( isset( $shared[ vergeml_filing_group_key( $fc ) ] ) ? $shared[ vergeml_filing_group_key( $fc ) ] : 1 ) );
                $this_hit = $phrase * $weight * $match / $k;
                if ( $this_hit > $class ) {
                    // Where the hit came from (S16): a word the members taught, the folder's own leaf, or the planner's.
                    $ev = array( 'source' => isset( $p['words'][ $fc ] ) ? 'members' : ( $is_leaf || 'name' === ( isset( $p['base_source'] ) ? $p['base_source'] : $p['source'] ) ? 'name' : 'plan' ), 'hit' => $pc . ' ~ ' . $fc );
                }
                $class   = max( $class, $this_hit );
                if ( $class >= 1.0 ) {
                    break 2;
                }
            }
        }
        // The specific phrase against the folder's descriptive phrase, when there is one.
        if ( $class < 0.95 && '' !== $p['matches'] && ! empty( $facts['classes'] ) ) {
            $this_hit = 0.9 * vergeml_filing_class_match( $facts['classes'][0], $p['matches'] );
            if ( $this_hit > $class ) {
                $ev = array( 'source' => 'matches', 'hit' => $facts['classes'][0] . ' ~ ' . $p['matches'] );
            }
            $class = max( $class, $this_hit );
        }
        /*
         *  The picture's own words (S10.9): its filename, title and alt as a
         *  third list, a hit there worth what the describer's second phrase
         *  is (0.85) -- "summer-dress-red-front.jpg" says dress where no
         *  describer word need. By words alone (vergeml_filing_word_match):
         *  no vector, no modifier. Read only when the describer's phrases
         *  left room: a word never outranks the object.
         *
         *  And corroboration, never evidence on its own (S14): read only
         *  where the describer's phrases already hit this folder. On the
         *  tech library (2026-09-17) ten of the S13 sheet's seventeen wrong
         *  likelies stood on one filename word where the describer hit the
         *  folder not at all -- "farm" of a 3D-printer farm was Wind's "wind
         *  farm", "switch" of a smart plug was Server racks' "network
         *  switches" -- a likely with a runner-up at 0.13; and a title that
         *  says "phone" put an office desk in Phones, sure, when the folder's
         *  own name was let count alone. A folder named for a shoot
         *  ("smith-wedding-012.jpg" in Smith wedding) is a signal about the
         *  pack, and S10.10 hands those to the planner.
         */
        if ( $class < 0.85 && $class > 0.0 && ! empty( $facts['words'] ) ) {
            foreach ( (array) $facts['words'] as $word ) {
                foreach ( array_values( (array) $p['classes'] ) as $rank => $fc ) {
                    $match = vergeml_filing_word_match( $word, $fc );
                    if ( $match <= 0.0 ) {
                        continue;
                    }
                    $is_leaf = '' !== $leaf && vergeml_filing_group_key( $fc ) === $leaf;
                    $weight  = ( 0 === $rank || ( $is_leaf && $match >= 1.0 ) ) ? 1.0 : 0.85;
                    $k       = $is_leaf ? 1 : max( 1, (int) ( isset( $shared[ vergeml_filing_group_key( $fc ) ] ) ? $shared[ vergeml_filing_group_key( $fc ) ] : 1 ) );
                    $this_hit = 0.85 * $weight * $match / $k;
                    if ( $this_hit > $class ) {
                        $ev = array( 'source' => 'word', 'hit' => $word . ' ~ ' . $fc );
                    }
                    $class   = max( $class, $this_hit );
                }
            }
        }

        $embed = 0.0;
        if ( is_array( $facts['vector'] ) && is_array( $p['vector'] ) ) {
            // Keyed by source too: a folder's base vector and its members' centroid can be built in the same second (S10.7).
            $embed = max( 0.0, vergeml_filing_cosine( $p['vector'], vergeml_filing_norm( $p['vector'], $tid . ':' . $p['source'] . ':' . ( isset( $p['built_at'] ) ? $p['built_at'] : 0 ) ), $facts['vector'], $pnorm ) );
        }

        $evidence[ $tid ] = $class > 0.0 && isset( $ev ) ? $ev : array( 'source' => 'vector', 'hit' => '' );
        unset( $ev );
        if ( $shadowed ) {
            $shadow[ $tid ] = VERGEML_FILING_CLASS_WEIGHT * $class + ( 1 - VERGEML_FILING_CLASS_WEIGHT ) * $embed;
            continue;
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

    // A shadowed folder (gated by audience, the picture saying none) outscoring the runner-up is the runner-up: the margin is judged against it.
    foreach ( $shadow as $cand => $s ) {
        $cand = (int) $cand;
        if ( (float) $s > $rscore && ! vergeml_filing_is_descendant( $cand, $best, $profiles ) && ! vergeml_filing_is_descendant( $best, $cand, $profiles ) ) {
            $runner = $cand;
            $rscore = (float) $s;
        }
    }

    $common = array( 'score' => $score, 'runner_up' => $runner, 'runner_score' => $rscore, 'scores' => $scores + $shadow, 'gated' => $gated, 'source' => $evidence[ $best ]['source'], 'hit' => $evidence[ $best ]['hit'] );

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
        'source'       => '',
        'hit'          => '',
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
        'product'  => 0, // Of 'fits': by the product the picture belongs to (S10.8), before any matching.
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
    if ( 'product' === $pick['why'] ) {
        $counts['product'] = ( isset( $counts['product'] ) ? (int) $counts['product'] : 0 ) + 1;
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
    foreach ( array( 'looked', 'fits', 'siblings', 'nothing', 'kept', 'either', 'sure', 'likely', 'product' ) as $k ) {
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

    /*
     *  Either/or: the folder more often best is named first and offered
     *  first. Largest pairs first. The pairs with one picture fold into one
     *  card (S10.5): on the shop, 38 of 41 either/or cards were about one
     *  picture each -- "1 picture: Backpacks or Backpacks?" thirty-eight times
     *  -- and the grain, not the cap, was what made them tedious. The folded
     *  card keeps each picture's own two folders ('pairs', best first) so the
     *  strip can say them, and offers what fits them all: split, leave, look.
     *  One such pair alone stays its own card, with its put-ins.
     */
    $pairs = (array) $either;
    uasort( $pairs, function ( $a, $b ) { return count( (array) $b['ids'] ) <=> count( (array) $a['ids'] ); } );
    $singles = array();
    foreach ( $pairs as $key => $e ) {
        $ids      = isset( $e['ids'] ) ? (array) $e['ids'] : array();
        $children = isset( $e['children'] ) ? (array) $e['children'] : array();
        arsort( $children );
        $two = array_map( 'intval', array_slice( array_keys( $children ), 0, 2 ) );
        if ( 2 !== count( $two ) || ! $ids ) {
            continue;
        }
        if ( 1 === count( $ids ) ) {
            $singles[ (int) key( $ids ) ] = $two;
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
    if ( 1 === count( $singles ) ) {
        // Alone, a pair of one picture is still a pair: its own card, its own put-ins.
        $pid = (int) key( $singles );
        $two = $singles[ $pid ];
        $out[] = array(
            'id'         => 'e:' . min( $two ) . ':' . max( $two ),
            'kind'       => 'either',
            'term_id'    => 0,
            'children'   => $two,
            'count'      => 1,
            'sample'     => array( $pid ),
            'ids'        => array( $pid => $two[0] ),
            'name'       => '',
            'class'      => '',
            'unreadable' => false,
            'answers'    => array( 'put-in:' . $two[0], 'put-in:' . $two[1], 'split', 'leave', 'show-me' ),
        );
    } elseif ( $singles ) {
        $best = array();
        foreach ( $singles as $pid => $two ) {
            $best[ $pid ] = $two[0];
        }
        $out[] = array(
            'id'         => 'e:one',
            'kind'       => 'either',
            'term_id'    => 0,
            'children'   => array(),
            'count'      => count( $singles ),
            'sample'     => array_slice( array_keys( $singles ), 0, VERGEML_FILING_SAMPLE ),
            'ids'        => $best,
            'pairs'      => $singles,
            'name'       => '',
            'class'      => '',
            'unreadable' => false,
            'answers'    => array( 'split', 'leave', 'show-me' ),
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

/**
 *  The names a question calls two folders by: the leaf, unless another
 *  folder in the question has the same leaf -- then the path (S10.5: "10
 *  pictures: Backpacks or Backpacks?" was the shop's Bags & Luggage ›
 *  Backpacks against Camping › Backpacks, and unanswerable as worded).
 *
 * @param array $folders Each: 'name' (the leaf), 'path' ("Parent › Leaf").
 * @return string[] One name per folder, in order.
 */
function vergeml_filing_question_names( $folders ) {
    $seen = array();
    foreach ( (array) $folders as $f ) {
        $k          = mb_strtolower( trim( (string) $f['name'] ) );
        $seen[ $k ] = isset( $seen[ $k ] ) ? $seen[ $k ] + 1 : 1;
    }
    $out = array();
    foreach ( (array) $folders as $f ) {
        $k     = mb_strtolower( trim( (string) $f['name'] ) );
        $out[] = $seen[ $k ] > 1 && '' !== (string) $f['path'] ? (string) $f['path'] : (string) $f['name'];
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

    /*
     *  Split and keep-parent are decisions too (2026-09-17: on the shop every
     *  fill asked the same questions again, because these two left no mark
     *  and the next fill scored the same tie). Marked 'answer': kept by the
     *  fill like 'user', while the word on each picture stays the fill's own
     *  ("likely"), not "by you".
     */
    if ( $mapped && 'split' === $answer ) {
        $plan['placed_by'] = 'answer';
        foreach ( (array) $q['ids'] as $id => $best ) {
            $plan['moves'][ (int) $id ] = (int) $best;
        }
        return $plan;
    }
    if ( 'siblings' === $q['kind'] ) {
        // keep-parent: they are in the parent already; the move is a no-op that carries the mark.
        $plan['placed_by'] = 'answer';
        foreach ( array_keys( (array) $q['ids'] ) as $id ) {
            $plan['moves'][ (int) $id ] = (int) $q['term_id'];
        }
        return $plan;
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
