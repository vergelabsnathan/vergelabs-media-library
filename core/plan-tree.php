<?php
/**
 *  The bottom-up planner (spec-tree-planner, story 3): every described
 *  picture's label, counted here into an inventory of distinct labels, sent
 *  to the service's /plan-tree, which arranges them fifteen times and sends
 *  back every valid answer. Here, with the pictures' vectors that never leave
 *  the site, the answer whose pictures sit closest together is kept and the
 *  labels the model left out join their nearest folder. That becomes the
 *  Folders screen's draft, with every existing folder kept where it is.
 *
 *  Measured in tools/tree-lab.mjs (improve mode, 2026-09-27): the shop's two
 *  builds recovered 41 and 43 of 47 real folders at 77-80 % purity with 9 %
 *  unfiled; tech 9 of 11 at 81-82 %, 4-6 % unfiled.
 *
 *  The price is counted on this site from the inventory, with no service
 *  call, and is the same sum the service charges (lib/plan-tree.ts planPrice).
 *
 *  The service asks fifteen runs at once and can take longer than a proxy holds a
 *  request open, so the press books a job and the screen polls it.
 *
 *  @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const VERGEML_PLAN_HOOK    = 'vergeml_plan_event';
const VERGEML_PLAN_LOCK    = 'vergeml_planning';
const VERGEML_PLAN_TIMEOUT = 280;
const VERGEML_PLAN_CACHE   = 'vergeml_plan_inventory';
/** A label the model left out joins the nearest folder at this cosine or more; below it, it stays unfiled. Tuned on shop and tech. */
const VERGEML_PLAN_PLACE   = 0.5;
/** Bumped whenever the inventory's shape changes, so a cache built before the fold (spec-tree-planner story 7) is never read as one built after it. */
const VERGEML_PLAN_INVENTORY_VERSION = 2;
/** The service refuses more than 3,000 labels and its answer stops fitting its own budget well before that; the inventory folds down to this many so a library of any size is always under both. */
const VERGEML_PLAN_FOLD_BUDGET = 1500;
/** A cold inventory read past this many seconds serves the last cached one instead and hands the rest to cron. */
const VERGEML_PLAN_FACTS_BUDGET = 15;
const VERGEML_PLAN_REFRESH_HOOK = 'vergeml_plan_inventory_refresh';
/** A plan is offered only when the pictures already in a folder sit this much tighter after it than now (spec-tree-planner story 8: clearly better, never worse). */
const VERGEML_PLAN_GAIN = 0.02;
/** Under this share of described pictures carrying any audience tag, the Tree step asks the one question (CAP-4, story 6); the shop's own share is about 3 %. */
const VERGEML_PLAN_AUDIENCE_ASK = 0.15;

/** Ten credits and six per hundred labels, rounded up: the service's planPrice(). */
function vergeml_plan_price( $labels ) {
    return 10 + (int) ceil( max( 0, (int) $labels ) * 6 / 100 );
}

/**
 *  Every described picture's label, as distinct labels with their counts.
 *  A label is the describer's object and its broader class, and the kind
 *  when the picture is not a photo -- the key tools/tree-lab.mjs measured.
 *  Cached against the described count and the newest description.
 *
 *  Read in pages of 500 so a library of any size never sits in memory whole
 *  (spec-tree-planner story 7). Over VERGEML_PLAN_FOLD_BUDGET distinct
 *  labels, the rarest fold into their class's fold label
 *  (vergeml_plan_fold_label) until the count is at or under the budget; if
 *  even every label folded still leaves more fold labels than the budget,
 *  the rarest of those are left out of the inventory entirely, counted in
 *  'left_out_pictures' -- the matcher decides those pictures as it does an
 *  unfolded library's leftovers.
 *
 *  A cold read past VERGEML_PLAN_FACTS_BUDGET seconds gives up and serves
 *  the last cached inventory instead, and books one cron event
 *  (VERGEML_PLAN_REFRESH_HOOK) to finish the count in the background. The
 *  plan job itself calls with $force so its own read is never the stale one
 *  the page render can settle for.
 *
 * @param bool $force Skip the time budget: read the whole library through,
 *                     however long it takes. The plan job's own call.
 * @return array{labels:array,pictures:int,unlabelled:int,folded_labels:int,left_out_pictures:int}
 */
function vergeml_plan_inventory( $force = false ) {
    global $wpdb;
    $t = $wpdb->vergeml_ai_index;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $stamp = $wpdb->get_row( "SELECT COUNT(*) AS n, MAX(described_at) AS at FROM {$t} WHERE error = '' AND embedding IS NOT NULL", ARRAY_A );
    $key   = md5( wp_json_encode( $stamp ) . '|' . VERGEML_PLAN_INVENTORY_VERSION . '|' . VERGEML_PLAN_FOLD_BUDGET );
    $held  = get_option( VERGEML_PLAN_CACHE );
    if ( is_array( $held ) && isset( $held['key'] ) && $held['key'] === $key ) {
        return $held['inventory'];
    }
    // A refresh is already on its way: the page does not spend another 15 s finding that out.
    if ( ! $force && is_array( $held ) && isset( $held['inventory'] ) && get_transient( VERGEML_PLAN_REFRESH_HOOK ) ) {
        return $held['inventory'];
    }

    $started    = microtime( true );
    $by         = array();
    $unlabelled = 0;
    $pictures   = 0;
    $after      = 0;
    $timed_out  = false;
    do {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
        $rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id, kind, filing FROM {$t} WHERE error = '' AND embedding IS NOT NULL AND attachment_id > %d ORDER BY attachment_id ASC LIMIT 500", $after ), ARRAY_A );
        foreach ( $rows as $r ) {
            $after = (int) $r['attachment_id'];
            ++$pictures;
            $filing = json_decode( (string) $r['filing'], true );
            $label  = vergeml_plan_label_of( $r['kind'], $filing );
            if ( '' === $label ) {
                ++$unlabelled;
                continue;
            }
            if ( ! isset( $by[ $label ] ) ) {
                $classes      = vergeml_filing_classes_of_object( is_array( $filing ) && isset( $filing['object'] ) ? $filing['object'] : '' );
                $kind         = sanitize_key( (string) $r['kind'] );
                $by[ $label ] = array(
                    'label'      => $label,
                    'class'      => $classes[0],
                    'kind'       => '' === $kind ? 'photo' : $kind,
                    'count'      => 0,
                    'audience'   => array(),
                    'fold'       => vergeml_plan_fold_label( $r['kind'], $filing ),
                    'fold_class' => isset( $classes[1] ) ? $classes[1] : $classes[0],
                );
            }
            ++$by[ $label ]['count'];
            $aud = vergeml_filing_audience_of_picture( is_array( $filing ) && isset( $filing['audience'] ) ? $filing['audience'] : '' );
            if ( in_array( $aud, array( 'men', 'women', 'kids' ), true ) ) {
                $by[ $label ]['audience'][ $aud ] = ( isset( $by[ $label ]['audience'][ $aud ] ) ? $by[ $label ]['audience'][ $aud ] : 0 ) + 1;
            }
        }
        if ( ! $force && ( microtime( true ) - $started ) > VERGEML_PLAN_FACTS_BUDGET ) {
            $timed_out = true;
            break;
        }
    } while ( 500 === count( $rows ) );

    if ( $timed_out ) {
        vergeml_plan_schedule_refresh();
        if ( is_array( $held ) && isset( $held['inventory'] ) ) {
            return $held['inventory'];
        }
        // No cache exists yet at all: fold and hand back what the partial scan already saw rather than nothing.
    }

    list( $by, $folded_labels, $left_out_pictures ) = vergeml_plan_fold( $by, VERGEML_PLAN_FOLD_BUDGET );

    uasort( $by, function ( $a, $b ) {
        return $b['count'] - $a['count'] ?: strcmp( $a['label'], $b['label'] );
    } );
    $labels = array();
    foreach ( array_values( $by ) as $i => $l ) {
        unset( $l['fold'], $l['fold_class'] );
        $labels[] = array_merge( array( 'id' => 'l' . $i ), $l );
    }

    $inventory = array(
        'labels'             => $labels,
        'pictures'           => $pictures,
        'unlabelled'         => $unlabelled,
        'folded_labels'      => $folded_labels,
        'left_out_pictures'  => $left_out_pictures,
    );
    // A partial count is held under a key no read matches, so the renders until the refresh lands serve it instead of scanning again.
    update_option( VERGEML_PLAN_CACHE, array( 'key' => $timed_out ? 'partial' : $key, 'inventory' => $inventory ), false );
    return $inventory;
}

/**
 *  The rarest labels folded into their class's fold label until the count
 *  is at or under $budget (spec-tree-planner story 7). Under budget,
 *  untouched. Rarest-first: a label with no vector for a folder to sit near
 *  is no less foldable than one with a vector -- pictures-count is the only
 *  thing that decides which fold first. Two labels that fold to the same
 *  target merge into one entry, their counts and audience summed, the kind
 *  kept (a photo and a screenshot of the same class never share a fold
 *  label, because their label text -- and so their fold target -- differ by
 *  the [kind] suffix).
 *
 *  If folding every last label still leaves more distinct fold labels than
 *  the budget -- more classes in the library than the budget allows -- the
 *  rarest fold labels are dropped from the answer entirely. Their pictures
 *  are counted in $left_out_pictures but appear nowhere in the map that
 *  comes back, so the matcher decides them exactly as it decides an
 *  unfolded library's leftovers.
 *
 * @param array $by     label text => {label,class,kind,count,audience,fold,fold_class}.
 * @param int   $budget VERGEML_PLAN_FOLD_BUDGET.
 * @return array{0:array,1:int,2:int} the folded set, labels folded away, pictures left out.
 */
function vergeml_plan_fold( $by, $budget ) {
    if ( count( $by ) <= $budget ) {
        return array( $by, 0, 0 );
    }

    $order = $by;
    uasort( $order, function ( $a, $b ) {
        return $a['count'] - $b['count'] ?: strcmp( $a['label'], $b['label'] );
    } );

    $kept          = $by;
    $folds         = array();
    $folded_labels = 0;
    foreach ( $order as $label => $entry ) {
        if ( count( $kept ) + count( $folds ) <= $budget ) {
            break;
        }
        unset( $kept[ $label ] );
        ++$folded_labels;
        $fl = $entry['fold'];
        if ( ! isset( $folds[ $fl ] ) ) {
            $folds[ $fl ] = array(
                'label'      => $fl,
                'class'      => $entry['fold_class'],
                'kind'       => $entry['kind'],
                'count'      => 0,
                'audience'   => array(),
                'fold'       => '',
                'fold_class' => $entry['fold_class'],
            );
        }
        $folds[ $fl ]['count'] += (int) $entry['count'];
        foreach ( (array) $entry['audience'] as $a => $n ) {
            $folds[ $fl ]['audience'][ $a ] = ( isset( $folds[ $fl ]['audience'][ $a ] ) ? $folds[ $fl ]['audience'][ $a ] : 0 ) + $n;
        }
    }

    // A fold label that is also a kept label's own text takes that label in: counts and audience summed.
    foreach ( $folds as $fl => $f ) {
        if ( ! isset( $kept[ $fl ] ) ) {
            continue;
        }
        $folds[ $fl ]['count'] += (int) $kept[ $fl ]['count'];
        foreach ( (array) $kept[ $fl ]['audience'] as $a => $n ) {
            $folds[ $fl ]['audience'][ $a ] = ( isset( $folds[ $fl ]['audience'][ $a ] ) ? $folds[ $fl ]['audience'][ $a ] : 0 ) + $n;
        }
        unset( $kept[ $fl ] );
    }
    $merged = array_merge( $kept, $folds );

    $left_out_pictures = 0;
    if ( count( $merged ) > $budget ) {
        uasort( $folds, function ( $a, $b ) {
            return $a['count'] - $b['count'] ?: strcmp( $a['label'], $b['label'] );
        } );
        foreach ( $folds as $label => $entry ) {
            if ( count( $merged ) <= $budget ) {
                break;
            }
            unset( $merged[ $label ] );
            $left_out_pictures += (int) $entry['count'];
        }
    }

    return array( $merged, $folded_labels, $left_out_pictures );
}

/**
 *  The class a rare label folds into when the inventory is over budget:
 *  "various <class>; <class>", the [kind] kept as the label's own carries
 *  it (lib/plan-tree.ts reads a label as "object; class [kind]", which is
 *  why the fold's "object" is "various <class>"). '' when the picture has
 *  no object at all -- nothing to fold, as vergeml_plan_label_of already
 *  reads '' the same way.
 */
function vergeml_plan_fold_label( $kind, $filing ) {
    $classes = vergeml_filing_classes_of_object( is_array( $filing ) && isset( $filing['object'] ) ? $filing['object'] : '' );
    if ( ! $classes ) {
        return '';
    }
    $broad = isset( $classes[1] ) ? $classes[1] : $classes[0];
    $kind  = sanitize_key( (string) $kind );
    $kind  = '' === $kind ? 'photo' : $kind;
    return 'various ' . $broad . '; ' . $broad . ( 'photo' === $kind ? '' : ' [' . $kind . ']' );
}

/**
 *  The label a picture is known under in a label-keyed map: its own label
 *  where the map holds that text, else its fold label where the map holds
 *  that instead, else ''. A plan that stayed under budget never folded, so
 *  no fold label is ever in its map and this reads exactly as
 *  vergeml_plan_label_of did before story 7. Reused by the inventory's own
 *  vector sums, the draft's near-copy match, and the frozen label map's
 *  fill (core/filing.php's vergeml_filing_label_folders).
 *
 * @param array $known label text => anything; only isset() is read.
 */
function vergeml_plan_effective_label( $kind, $filing, $known ) {
    $label = vergeml_plan_label_of( $kind, $filing );
    if ( '' !== $label && isset( $known[ $label ] ) ) {
        return $label;
    }
    $fold = vergeml_plan_fold_label( $kind, $filing );
    if ( '' !== $fold && isset( $known[ $fold ] ) ) {
        return $fold;
    }
    return '';
}

function vergeml_plan_schedule_refresh() {
    set_transient( VERGEML_PLAN_REFRESH_HOOK, 1, 10 * MINUTE_IN_SECONDS );
    if ( ! wp_next_scheduled( VERGEML_PLAN_REFRESH_HOOK ) ) {
        wp_schedule_single_event( time(), VERGEML_PLAN_REFRESH_HOOK );
    }
    if ( ! defined( 'DOING_CRON' ) ) {
        spawn_cron();
    }
}

add_action( VERGEML_PLAN_REFRESH_HOOK, 'vergeml_plan_inventory_refresh_event' );

function vergeml_plan_inventory_refresh_event() {
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- a cron job; refused silently where disallowed.
    }
    vergeml_plan_inventory( true );
    delete_transient( VERGEML_PLAN_REFRESH_HOOK );
}

/** A picture's label: "object; class", with [kind] when not a photo. '' when the describer named no object. */
function vergeml_plan_label_of( $kind, $filing ) {
    $classes = vergeml_filing_classes_of_object( is_array( $filing ) && isset( $filing['object'] ) ? $filing['object'] : '' );
    if ( ! $classes ) {
        return '';
    }
    $kind = sanitize_key( (string) $kind );
    $kind = '' === $kind ? 'photo' : $kind;
    return $classes[0] . ( isset( $classes[1] ) ? '; ' . $classes[1] : '' ) . ( 'photo' === $kind ? '' : ' [' . $kind . ']' );
}

/**
 *  Each label's pictures as one vector: the sum of their unit vectors, read
 *  in pages so a large library never sits in memory whole. A folder's sum is
 *  its labels' sums added; its length over the folder's pictures is the mean
 *  cosine of a picture to the folder's centre -- how tight the folder is.
 *
 * @return array label id => float[]
 */
function vergeml_plan_label_sums( $labels ) {
    global $wpdb;
    $t     = $wpdb->vergeml_ai_index;
    $id_of = array();
    foreach ( $labels as $l ) {
        $id_of[ $l['label'] ] = $l['id'];
    }
    // Mid re-embed after a model change the index holds two sizes of vector; only the library's most common one is summed.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $dims  = (int) $wpdb->get_var( "SELECT embedding_dims FROM {$t} WHERE error = '' AND embedding IS NOT NULL GROUP BY embedding_dims ORDER BY COUNT(*) DESC LIMIT 1" );
    $sums  = array();
    $after = 0;
    do {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
        $rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id, kind, filing, embedding, embedding_dims FROM {$t} WHERE error = '' AND embedding IS NOT NULL AND attachment_id > %d ORDER BY attachment_id ASC LIMIT 500", $after ), ARRAY_A );
        foreach ( $rows as $r ) {
            $after = (int) $r['attachment_id'];
            // A label the inventory folded away is not in $id_of by its own text; its fold label, if that survived the budget, is.
            $label = vergeml_plan_effective_label( $r['kind'], json_decode( (string) $r['filing'], true ), $id_of );
            $v     = vergeml_index_vector_out( $r['embedding'] );
            if ( '' === $label || ! isset( $id_of[ $label ] ) || ! $v || ( $dims > 0 && count( $v ) !== $dims ) ) {
                continue;
            }
            $v  = vergeml_plan_unit( $v );
            $id = $id_of[ $label ];
            $sums[ $id ] = isset( $sums[ $id ] ) ? vergeml_plan_add( $sums[ $id ], $v ) : $v;
        }
    } while ( 500 === count( $rows ) );
    return $sums;
}

function vergeml_plan_unit( $v ) {
    $n = sqrt( vergeml_plan_dot( $v, $v ) );
    return $n > 0 ? array_map( function ( $x ) use ( $n ) {
        return $x / $n;
    }, $v ) : $v;
}

function vergeml_plan_add( $a, $b ) {
    foreach ( $b as $i => $x ) {
        $a[ $i ] += $x;
    }
    return $a;
}

function vergeml_plan_dot( $a, $b ) {
    $d = 0.0;
    foreach ( $a as $i => $x ) {
        $d += $x * $b[ $i ];
    }
    return $d;
}

/**
 *  One picture into the before/after measure (vergeml_plan_gain_of). $now is
 *  the folder it sits in ('' for none or To sort), $then the one it will sit
 *  in once the plan fills. Only a picture already in a folder is measured;
 *  every picture in $then still shapes that folder's centre.
 */
function vergeml_plan_gain_add( $acc, $v, $now, $then ) {
    if ( '' !== $then ) {
        $acc['all'][ $then ] = isset( $acc['all'][ $then ] ) ? vergeml_plan_add( $acc['all'][ $then ], $v ) : $v;
    }
    if ( '' === $now ) {
        return $acc;
    }
    $acc['n']++;
    $acc['now'][ $now ] = isset( $acc['now'][ $now ] ) ? vergeml_plan_add( $acc['now'][ $now ], $v ) : $v;
    if ( '' !== $then ) {
        $acc['kept'][ $then ] = isset( $acc['kept'][ $then ] ) ? vergeml_plan_add( $acc['kept'][ $then ], $v ) : $v;
    }
    return $acc;
}

/**
 *  How tight the pictures already in a folder sit, now and after the plan:
 *  the mean cosine of each to its folder's centre. The same pictures on both
 *  sides, so a plan is not marked down for also filing what waited in To
 *  sort. A picture the plan leaves without a folder counts 0 after. Nothing
 *  filed yet reads 0 before, which any plan beats.
 *
 * @return array{before:float,after:float}
 */
function vergeml_plan_gain_of( $acc ) {
    if ( 0 === $acc['n'] ) {
        return array( 'before' => 0.0, 'after' => 1.0 );
    }
    $before = 0.0;
    foreach ( $acc['now'] as $s ) {
        $before += sqrt( vergeml_plan_dot( $s, $s ) );
    }
    $after = 0.0;
    foreach ( $acc['kept'] as $k => $s ) {
        $after += vergeml_plan_dot( $s, vergeml_plan_unit( $acc['all'][ $k ] ) );
    }
    return array( 'before' => $before / $acc['n'], 'after' => $after / $acc['n'] );
}

/**
 *  The site's before/after for a draft: each described picture's deepest
 *  folder now, and where the fill will put it -- its label's folder in the
 *  draft's label map, unless a person placed it or its label is not in the
 *  map, when it stays. Read in pages of 500 like vergeml_plan_label_sums.
 */
function vergeml_plan_gain( $label_map, $label_index, $taxonomy ) {
    global $wpdb;
    $acc = array( 'n' => 0, 'now' => array(), 'kept' => array(), 'all' => array() );
    if ( '' === $taxonomy || ! function_exists( 'vergeml_guide_rule_rows' ) ) {
        return vergeml_plan_gain_of( $acc );
    }
    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
    $up    = array();
    $skip  = array();
    foreach ( is_array( $terms ) ? $terms : array() as $t ) {
        $up[ (int) $t->term_id ] = (int) $t->parent;
        if ( defined( 'VERGEML_FILING_TO_SORT_SLUG' ) && VERGEML_FILING_TO_SORT_SLUG === $t->slug ) {
            $skip[ (int) $t->term_id ] = true;
        }
    }
    $depth = function ( $tid ) use ( $up ) {
        $d = 0;
        while ( isset( $up[ $tid ] ) && $d < 32 ) {
            $tid = $up[ $tid ];
            $d++;
        }
        return $d;
    };
    $user = array();
    if ( defined( 'VERGEML_FILING_PLACED_BY' ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one read of the hand-placed pictures.
        $user = array_flip( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = 'user'", VERGEML_FILING_PLACED_BY ) ) ) );
    }
    $t = $wpdb->vergeml_ai_index;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $dims  = (int) $wpdb->get_var( "SELECT embedding_dims FROM {$t} WHERE error = '' AND embedding IS NOT NULL GROUP BY embedding_dims ORDER BY COUNT(*) DESC LIMIT 1" );
    $after = 0;
    do {
        $rows = (array) vergeml_guide_rule_rows( $taxonomy, 'all', array( 'filing', 'terms', 'embedding' ), $after, 500 );
        foreach ( $rows as $r ) {
            $after = (int) $r['attachment_id'];
            $v     = vergeml_index_vector_out( $r['embedding'] );
            if ( ! $v || ( $dims > 0 && count( $v ) !== $dims ) ) {
                continue;
            }
            $now = '';
            $at  = -1;
            foreach ( array_filter( array_map( 'intval', explode( ',', (string) $r['in_terms'] ) ) ) as $tid ) {
                if ( ! isset( $skip[ $tid ] ) && ( $depth( $tid ) > $at || ( $depth( $tid ) === $at && 't' . $tid < $now ) ) ) {
                    $at  = $depth( $tid );
                    $now = 't' . $tid;
                }
            }
            $label = vergeml_plan_effective_label( $r['kind'], json_decode( (string) $r['filing'], true ), $label_index );
            $then  = ! isset( $user[ $after ] ) && '' !== $label && isset( $label_map[ $label ] ) ? $label_map[ $label ] : $now;
            $acc   = vergeml_plan_gain_add( $acc, vergeml_plan_unit( $v ), $now, $then );
        }
    } while ( 500 === count( $rows ) );
    return vergeml_plan_gain_of( $acc );
}

/**
 *  Of the service's trees, the one to keep, with the labels it left out
 *  placed. Per tree: each folder's centre from the labels in it; a label left
 *  out joins the folder whose centre is nearest, at VERGEML_PLAN_PLACE or
 *  more; then its tightness, the mean cosine of a picture to its folder's
 *  centre. The tightest is kept -- no truth read, and in the lab it ranked
 *  the runs as their true scores did (shop: 0.770 for 41 of 47, 0.729 for 26).
 *
 * @param array $trees  The service's trees: folders [{path, labels[]}], unfiled [ids].
 * @param array $sums   vergeml_plan_label_sums().
 * @param array $counts Label id => pictures.
 * @return array{folders:array,index:int,placed:int,tightness:float}
 */
function vergeml_plan_choose( $trees, $sums, $counts ) {
    $best = null;
    foreach ( $trees as $index => $tree ) {
        $folders = array();
        $centre  = array();
        foreach ( (array) $tree['folders'] as $f ) {
            $folders[ $f['path'] ] = $f;
            $s                     = null;
            foreach ( (array) $f['labels'] as $id ) {
                if ( isset( $sums[ $id ] ) ) {
                    $s = null === $s ? $sums[ $id ] : vergeml_plan_add( $s, $sums[ $id ] );
                }
            }
            if ( null !== $s ) {
                $centre[ $f['path'] ] = vergeml_plan_unit( $s );
            }
        }
        $placed = 0;
        foreach ( (array) $tree['unfiled'] as $id ) {
            if ( ! isset( $sums[ $id ] ) ) {
                continue;
            }
            $v   = vergeml_plan_unit( $sums[ $id ] );
            $to  = '';
            $top = -1.0;
            foreach ( $centre as $path => $c ) {
                $x = vergeml_plan_dot( $v, $c );
                if ( $x > $top ) {
                    $top = $x;
                    $to  = $path;
                }
            }
            if ( '' !== $to && $top >= VERGEML_PLAN_PLACE ) {
                $folders[ $to ]['labels'][] = $id;
                ++$placed;
            }
        }
        $length = 0.0;
        $n      = 0;
        foreach ( $folders as $f ) {
            $s = null;
            foreach ( (array) $f['labels'] as $id ) {
                if ( isset( $sums[ $id ] ) ) {
                    $s  = null === $s ? $sums[ $id ] : vergeml_plan_add( $s, $sums[ $id ] );
                    $n += isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0;
                }
            }
            if ( null !== $s ) {
                $length += sqrt( vergeml_plan_dot( $s, $s ) );
            }
        }
        $tightness = $n > 0 ? $length / $n : 0.0;
        if ( null === $best || $tightness > $best['tightness'] ) {
            $best = array( 'folders' => array_values( $folders ), 'index' => (int) $index, 'placed' => $placed, 'tightness' => $tightness );
        }
    }
    return $best;
}

/**
 *  What share of the inventory's pictures carry any audience tag (CAP-4),
 *  from the label counts vergeml_plan_inventory() already gathered -- no
 *  extra query. A label folded away by vergeml_plan_fold still carries its
 *  audience sum (vergeml_plan_fold), so a folded inventory reads the same
 *  share an unfolded one would.
 */
function vergeml_plan_audience_share( $labels ) {
    $total = 0;
    $with  = 0;
    foreach ( (array) $labels as $l ) {
        $total += (int) ( isset( $l['count'] ) ? $l['count'] : 0 );
        $with  += array_sum( (array) ( isset( $l['audience'] ) ? $l['audience'] : array() ) );
    }
    return $total > 0 ? round( $with / $total, 3 ) : 0.0;
}

/**
 *  True when a WooCommerce product category's own name already says who it
 *  is for (CAP-4: "or from the shop's product categories"), read with the
 *  same word list a folder name is judged by (vergeml_filing_audience_of,
 *  which also reads Dutch compounds). False with no WooCommerce, or with
 *  categories that say nothing about audience.
 */
function vergeml_plan_product_audience_evidence() {
    if ( ! function_exists( 'vergeml_folders_product_paths' ) || ! function_exists( 'vergeml_filing_audience_of' ) ) {
        return false;
    }
    foreach ( (array) vergeml_folders_product_paths()['paths'] as $path ) {
        foreach ( (array) $path as $segment ) {
            if ( '' !== vergeml_filing_audience_of( $segment ) ) {
                return true;
            }
        }
    }
    return false;
}

/** What the button needs: the price, the label count, and the balance where one is known; and whether to ask the audience question (CAP-4). */
function vergeml_plan_facts() {
    $inv      = vergeml_plan_inventory();
    $state    = function_exists( 'vergeml_ai_credits_state' ) ? vergeml_ai_credits_state() : array( 'remaining' => null );
    $evidence = vergeml_plan_product_audience_evidence();
    return array(
        'labels'            => count( $inv['labels'] ),
        'price'             => vergeml_plan_price( count( $inv['labels'] ) ),
        'balance'           => null === $state['remaining'] ? null : (int) $state['remaining'],
        // Asked only while the site's own pictures say too little and the categories say nothing either; the screen stops asking once the session has an answer.
        'audience_ask'      => ! $evidence && vergeml_plan_audience_share( $inv['labels'] ) < VERGEML_PLAN_AUDIENCE_ASK,
        'audience_evidence' => $evidence,
    );
}


/* ---------------------------------------------------------------- the job */

/** The press: books the job, once. The session carries its state so a reload keeps polling. */
function vergeml_plan_rest_start() {
    $s = vergeml_guide_session();
    if ( 'confirmed' === $s['tree'] ) {
        return vergeml_guide_confirmed_refusal();
    }
    if ( isset( $s['plan']['state'] ) && 'running' === $s['plan']['state'] ) {
        return rest_ensure_response( vergeml_plan_out( $s ) );
    }
    $facts = vergeml_plan_facts();
    if ( 0 === $facts['labels'] ) {
        return new WP_Error( 'empty', __( 'No picture is described yet.', 'vergelabs-media-library' ), array( 'status' => 409 ) );
    }
    $s['plan'] = array( 'state' => 'running', 'at' => time(), 'price' => $facts['price'] );
    vergeml_guide_save( $s );
    vergeml_plan_schedule();
    return rest_ensure_response( vergeml_plan_out( $s ) );
}

/** The poll: the plan's state and, once it is done, the session with its draft. A job cron never started is booked again. */
function vergeml_plan_rest_poll() {
    $s = vergeml_guide_session();
    if ( isset( $s['plan']['state'] ) && 'running' === $s['plan']['state'] && ! get_transient( VERGEML_PLAN_LOCK ) ) {
        if ( time() - (int) $s['plan']['at'] > VERGEML_PLAN_TIMEOUT + 120 ) {
            $s['plan'] = array( 'state' => 'failed', 'message' => __( 'The plan did not finish. Try again.', 'vergelabs-media-library' ) );
            vergeml_guide_save( $s );
        } elseif ( ! wp_next_scheduled( VERGEML_PLAN_HOOK ) ) {
            vergeml_plan_schedule();
        }
    }
    return rest_ensure_response( vergeml_plan_out( $s ) );
}

function vergeml_plan_out( $s ) {
    return vergeml_guide_session_out( $s );
}

function vergeml_plan_schedule() {
    if ( ! wp_next_scheduled( VERGEML_PLAN_HOOK ) ) {
        wp_schedule_single_event( time(), VERGEML_PLAN_HOOK );
    }
    if ( ! defined( 'DOING_CRON' ) ) {
        spawn_cron();
    }
}

add_action( VERGEML_PLAN_HOOK, 'vergeml_plan_event' );

function vergeml_plan_event() {
    $s = vergeml_guide_session();
    if ( ! isset( $s['plan']['state'] ) || 'running' !== $s['plan']['state'] || get_transient( VERGEML_PLAN_LOCK ) ) {
        return;
    }
    set_transient( VERGEML_PLAN_LOCK, time(), VERGEML_PLAN_TIMEOUT + 60 );
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( VERGEML_PLAN_TIMEOUT + 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- a cron job; refused silently where disallowed.
    }

    // The split is no longer a guess once the owner has said yes, or the site's own product categories already name one (CAP-4).
    $audience_confirmed = ( isset( $s['audience_split'] ) && 'yes' === $s['audience_split'] ) || vergeml_plan_product_audience_evidence();

    // Forced: the paid plan reads the library through, whatever that costs here, rather than settling for the page render's 15s-or-stale inventory.
    $inv    = vergeml_plan_inventory( true );
    $result = vergeml_plan_ask( $inv['labels'], $audience_confirmed );

    $s = vergeml_guide_session();
    if ( is_wp_error( $result ) ) {
        $s['plan'] = array( 'state' => 'failed', 'message' => $result->get_error_message() );
    } elseif ( 'confirmed' === $s['tree'] ) {
        // Confirmed while the plan ran: the confirmed tree wins and the plan is dropped.
        $s['plan'] = null;
    } else {
        // An older service sends its one kept tree; a current one every valid tree, to choose among here.
        $trees      = ! empty( $result['trees'] ) ? $result['trees'] : array( array( 'folders' => $result['folders'], 'unfiled' => isset( $result['unfiled'] ) ? $result['unfiled'] : array() ) );
        $chosen     = vergeml_plan_choose( $trees, vergeml_plan_label_sums( $inv['labels'] ), array_column( $inv['labels'], 'count', 'id' ) );
        $draft    = vergeml_plan_draft( $chosen['folders'], $inv['labels'] );
        $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
        $gain     = vergeml_plan_gain( $draft['label_map'], array_column( $inv['labels'], 'id', 'label' ), $taxonomy );
        // Not clearly tidier than the folders the site already has: the plan is not offered (spec-tree-planner story 8).
        $better = $gain['after'] >= $gain['before'] + VERGEML_PLAN_GAIN;
        if ( $better ) {
            $s['draft'] = $draft;
            if ( '' !== $taxonomy ) {
                vergeml_guide_fit_take( $s, $taxonomy );
            }
        }
        $s['plan'] = array(
            'state'   => $better ? 'done' : 'kept',
            'message' => $better ? '' : __( 'Your folders are already well organised — a new plan wouldn\'t improve them.', 'vergelabs-media-library' ),
            'gain'    => array( 'before' => round( $gain['before'], 4 ), 'after' => round( $gain['after'], 4 ) ),
            'charged' => isset( $result['charged'] ) ? (int) $result['charged'] : 0,
            'runs'    => isset( $result['runs'] ) ? $result['runs'] : null,
            'kept'    => $chosen['index'],
            'placed'  => $chosen['placed'],
            'left_out'=> (int) $inv['unlabelled'],
        );
        if ( isset( $result['credits_remaining'] ) ) {
            $credits              = get_option( 'vergeml_ai_credits', array() );
            $credits              = is_array( $credits ) ? $credits : array();
            $credits['remaining'] = (int) $result['credits_remaining'];
            $credits['time']      = time();
            update_option( 'vergeml_ai_credits', $credits, false );
        }
    }
    vergeml_guide_save( $s );
    delete_transient( VERGEML_PLAN_LOCK );
}

/**
 *  The one outbound call: the labels, their counts and audience counts, and
 *  whether an audience split is confirmed (CAP-4, story 6). Never a picture,
 *  a caption or a file name. $labels is always vergeml_plan_inventory()'s,
 *  which never hands back more than VERGEML_PLAN_FOLD_BUDGET -- the
 *  service's 'too_many_labels' refusal (spec-tree-planner story 7's problem)
 *  has no path here to reach any more, so it is not given its own message
 *  and falls, like any other code this service has not sent before, to the
 *  generic one below.
 *
 * @param bool $audience_confirmed True when the owner answered yes to the
 *                                  one audience question, or the site's own
 *                                  product categories already name a split:
 *                                  the service then skips stripping an
 *                                  audience folder the label counts alone
 *                                  would not support.
 */
function vergeml_plan_ask( $labels, $audience_confirmed = false ) {
    $licence = function_exists( 'vergeml_ai_settings' ) ? vergeml_ai_unseal( vergeml_ai_settings()['license_key'] ) : '';
    if ( '' === $licence ) {
        return new WP_Error( 'no_licence', __( 'This needs a licence key. Add yours under AI.', 'vergelabs-media-library' ) );
    }

    $send = array();
    foreach ( $labels as $l ) {
        $send[] = array( 'id' => $l['id'], 'label' => $l['label'], 'count' => $l['count'], 'audience' => (object) $l['audience'] );
    }

    $response = wp_remote_post( vergeml_ai_service_url() . '/plan-tree', array(
        'timeout'   => VERGEML_PLAN_TIMEOUT,
        'headers'   => array( 'Content-Type' => 'application/json' ),
        'sslverify' => true,
        'body'      => wp_json_encode( array( 'license_key' => $licence, 'site' => home_url(), 'labels' => $send, 'audienceConfirmed' => (bool) $audience_confirmed ) ),
    ) );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'unreachable', __( 'The service could not be reached. Nothing was charged.', 'vergelabs-media-library' ) );
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    $err  = is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : '';

    if ( 200 === $code && is_array( $data ) && ! empty( $data['folders'] ) ) {
        return $data;
    }
    if ( 402 === $code ) {
        /* translators: 1: credits the plan costs, 2: credits left */
        return new WP_Error( 'credits', sprintf( __( 'The plan costs %1$s credits and %2$s are left.', 'vergelabs-media-library' ), (int) ( isset( $data['charge'] ) ? $data['charge'] : 0 ), (int) ( isset( $data['credits_remaining'] ) ? $data['credits_remaining'] : 0 ) ) );
    }
    if ( 'could_not_plan' === $err ) {
        return new WP_Error( 'could_not_plan', __( 'The plan did not come together. Nothing was charged.', 'vergelabs-media-library' ) );
    }
    if ( in_array( $err, array( 'not_entitled', 'not_found', 'site_not_activated', 'bad_key' ), true ) ) {
        return new WP_Error( 'licence', __( 'This site\'s licence is not active. Check it under Licence.', 'vergelabs-media-library' ) );
    }
    if ( 429 === $code ) {
        return new WP_Error( 'capped', __( 'That is today\'s limit of plans. Try again tomorrow.', 'vergelabs-media-library' ) );
    }
    /* translators: %s: the service's error code */
    return new WP_Error( 'failed', sprintf( __( 'The service answered: %s', 'vergelabs-media-library' ), '' !== $err ? $err : (string) $code ) );
}

/**
 *  The service's folders as the screen's draft. Every existing folder is in
 *  it where it stands (a folder left out of a draft reads as removed). A
 *  planned folder whose pictures already sit at least half in one existing
 *  folder becomes that folder -- the near-copy fix (spec-tree-planner story
 *  4: "Bags and luggage" proposed beside an existing "Bags & Luggage" was
 *  the same folder twice); failing that, a planned folder with an existing
 *  folder's exact name is still that folder, as before. The rest are new,
 *  under their planned parents. A folder's classes are its labels' objects,
 *  which is what the matcher files by.
 *
 *  Alongside the draft, the label text -> draft key of every planned label is
 *  kept on the draft's own side (vergeml_guide_clean_draft's 'label_map'):
 *  the near-copies above, and what the fill freezes when the owner accepts.
 */
function vergeml_plan_draft( $planned, $labels ) {
    $by_id = array();
    foreach ( $labels as $l ) {
        $by_id[ $l['id'] ] = $l;
    }

    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
    $terms    = '' !== $taxonomy ? get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) : array();
    $terms    = is_array( $terms ) ? $terms : array();

    $out     = array( 'folders' => array(), 'gone' => array(), 'tags' => array(), 'origin' => 'talk', 'rule' => null, 'label_map' => array() );
    $by_name = array();
    foreach ( $terms as $t ) {
        // WordPress stores a term name escaped ("Bags &amp; Luggage"); the draft carries it as the owner reads it, or the folder reads as renamed.
        $by_name[ mb_strtolower( vergeml_term_name( $t ) ) ] = count( $out['folders'] );
        $out['folders'][] = array(
            'key' => 't' . $t->term_id, 'term_id' => (int) $t->term_id, 'name' => vergeml_term_name( $t ),
            'parent' => $t->parent ? 't' . $t->parent : '', 'count' => null, 'matches' => '',
            'classes' => array(), 'nowords' => false, 'kinds' => array(), 'audience' => '', 'by' => '', 'asked' => false,
        );
    }

    /*
     *  Where each label's pictures already sit, from the described library
     *  itself: label text -> existing term id -> how many of that label's
     *  pictures are in it now. The source both the near-copy mapping below
     *  and, once the owner accepts, the frozen label map read from. Read in
     *  pages of 500, as vergeml_plan_label_sums is, so a large library never
     *  sits in memory whole; a picture whose own label the inventory folded
     *  away counts under its fold label instead, when that survived the
     *  budget (vergeml_plan_effective_label).
     */
    $label_terms = array();
    $label_index = array_column( $labels, 'id', 'label' );
    if ( '' !== $taxonomy && function_exists( 'vergeml_guide_rule_rows' ) ) {
        $after = 0;
        do {
            $rows = (array) vergeml_guide_rule_rows( $taxonomy, 'all', array( 'filing', 'terms' ), $after, 500 );
            foreach ( $rows as $r ) {
                if ( isset( $r['attachment_id'] ) ) {
                    $after = (int) $r['attachment_id'];
                }
                if ( empty( $r['in_terms'] ) ) {
                    continue;
                }
                $filing = json_decode( (string) ( isset( $r['filing'] ) ? $r['filing'] : '' ), true );
                $label  = vergeml_plan_effective_label( isset( $r['kind'] ) ? $r['kind'] : '', $filing, $label_index );
                if ( '' === $label ) {
                    continue;
                }
                foreach ( array_map( 'intval', explode( ',', (string) $r['in_terms'] ) ) as $tid ) {
                    $label_terms[ $label ][ $tid ] = isset( $label_terms[ $label ][ $tid ] ) ? $label_terms[ $label ][ $tid ] + 1 : 1;
                }
            }
        } while ( 500 === count( $rows ) );
    }
    // The folders a planned folder may become. Not "To sort": pictures waiting there are what a plan is for, and a planned folder made mostly of them is not To sort.
    $waiting = array();
    foreach ( $terms as $t ) {
        if ( defined( 'VERGEML_FILING_TO_SORT_SLUG' ) && isset( $t->slug ) && VERGEML_FILING_TO_SORT_SLUG === $t->slug ) {
            $waiting[ (int) $t->term_id ] = true;
        }
    }
    $by_term = array();
    foreach ( $out['folders'] as $at => $t ) {
        if ( null === $t['term_id'] || isset( $waiting[ (int) $t['term_id'] ] ) ) {
            continue;
        }
        // Read the lock the way core/filing.php does: a locked folder is not the fill's to file into, so it is not the mapping's to fold a planned folder onto either.
        if ( defined( 'VERGEML_FILING_LOCKED' ) && function_exists( 'get_term_meta' ) && get_term_meta( (int) $t['term_id'], VERGEML_FILING_LOCKED, true ) ) {
            continue;
        }
        $by_term[ (int) $t['term_id'] ] = $at;
    }

    $key_of    = array();
    $label_map = array();
    foreach ( (array) $planned as $i => $f ) {
        $name = str_replace( '/', '-', sanitize_text_field( (string) ( isset( $f['name'] ) ? $f['name'] : '' ) ) );
        $path = (string) ( isset( $f['path'] ) ? $f['path'] : '' );
        if ( '' === $name || '' === $path ) {
            continue;
        }
        $classes = array();
        $kinds   = array();
        $total   = 0;   // This planned folder's pictures, by its labels' own counts.
        $hits    = array(); // existing term id => how many of those pictures sit there already.
        foreach ( (array) ( isset( $f['labels'] ) ? $f['labels'] : array() ) as $id ) {
            if ( ! isset( $by_id[ $id ] ) ) {
                continue;
            }
            $classes[] = $by_id[ $id ]['class'];
            $kinds[]   = $by_id[ $id ]['kind'];
            $total    += (int) ( isset( $by_id[ $id ]['count'] ) ? $by_id[ $id ]['count'] : 0 );
            $label     = isset( $by_id[ $id ]['label'] ) ? $by_id[ $id ]['label'] : '';
            if ( '' !== $label && isset( $label_terms[ $label ] ) ) {
                foreach ( $label_terms[ $label ] as $tid => $n ) {
                    $hits[ $tid ] = isset( $hits[ $tid ] ) ? $hits[ $tid ] + $n : $n;
                }
            }
        }
        $classes = array_values( array_unique( $classes ) );
        $kinds   = array_values( array_unique( $kinds ) );

        // The existing folder holding at least half this planned folder's pictures, if one does. A tie goes to the lower term id, not to row scan order.
        $onto = 0;
        $best = 0;
        foreach ( $hits as $tid => $n ) {
            if ( ! isset( $by_term[ $tid ] ) ) {
                continue;
            }
            if ( $n > $best || ( $n === $best && $tid < $onto ) ) {
                $best = $n;
                $onto = $tid;
            }
        }
        $at = ( $total > 0 && $best * 2 >= $total ) ? $by_term[ $onto ] : null;

        // No majority: today's fallback, an existing folder of the very same name.
        if ( null === $at ) {
            $lower = mb_strtolower( $name );
            $at    = isset( $by_name[ $lower ] ) ? $by_name[ $lower ] : null;
        }

        if ( null !== $at ) {
            $out['folders'][ $at ]['classes'] = array_values( array_unique( array_merge( $out['folders'][ $at ]['classes'], $classes ) ) );
            $out['folders'][ $at ]['kinds']   = array_values( array_unique( array_merge( $out['folders'][ $at ]['kinds'], $kinds ) ) );
            $key_of[ $path ] = $out['folders'][ $at ]['key'];
            foreach ( (array) ( isset( $f['labels'] ) ? $f['labels'] : array() ) as $id ) {
                if ( isset( $by_id[ $id ]['label'] ) ) {
                    $label_map[ $by_id[ $id ]['label'] ] = $out['folders'][ $at ]['key'];
                }
            }
            continue;
        }
        $key_of[ $path ]  = 'p' . $i;
        $by_name[ mb_strtolower( $name ) ] = count( $out['folders'] );
        $out['folders'][] = array(
            'key' => 'p' . $i, 'term_id' => null, 'name' => $name, 'parent' => (string) ( isset( $f['parent'] ) ? $f['parent'] : '' ),
            'count' => null, 'matches' => '', 'classes' => $classes, 'nowords' => false, 'kinds' => $kinds,
            'audience' => '', 'by' => '', 'asked' => false,
        );
        foreach ( (array) ( isset( $f['labels'] ) ? $f['labels'] : array() ) as $id ) {
            if ( isset( $by_id[ $id ]['label'] ) ) {
                $label_map[ $by_id[ $id ]['label'] ] = 'p' . $i;
            }
        }
    }
    // Parents were paths; now they are keys. A planned parent that is not in the answer leaves the folder at the top.
    foreach ( $out['folders'] as &$f ) {
        if ( null === $f['term_id'] ) {
            $f['parent'] = '' !== $f['parent'] && isset( $key_of[ $f['parent'] ] ) ? $key_of[ $f['parent'] ] : '';
        }
    }
    unset( $f );

    $out['label_map'] = $label_map;

    return vergeml_guide_clean_draft( $out );
}

/**
 *  The one question CAP-4 asks: whether the owner wants an audience split.
 *  Stored on the session so it is asked at most once, and read by
 *  vergeml_plan_event() the next time a plan runs.
 */
function vergeml_plan_rest_audience_split( WP_REST_Request $request ) {
    $s = vergeml_guide_session();
    if ( 'confirmed' === $s['tree'] ) {
        return vergeml_guide_confirmed_refusal();
    }
    $answer = sanitize_key( (string) $request->get_param( 'answer' ) );
    if ( ! in_array( $answer, array( 'yes', 'no' ), true ) ) {
        return new WP_Error( 'bad_answer', __( 'That is not yes or no.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }
    $s['audience_split'] = $answer;
    vergeml_guide_save( $s );
    return rest_ensure_response( array( 'audience_split' => $answer ) );
}

function vergeml_plan_routes() {
    $may = function () {
        return current_user_can( 'manage_categories' );
    };
    register_rest_route( VERGEML_REST_NS, '/guide/plan', array(
        array( 'methods' => WP_REST_Server::READABLE, 'callback' => 'vergeml_plan_rest_poll', 'permission_callback' => $may ),
        array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => 'vergeml_plan_rest_start', 'permission_callback' => $may ),
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/audience-split', array(
        array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => 'vergeml_plan_rest_audience_split', 'permission_callback' => $may ),
    ) );
}

add_action( 'rest_api_init', 'vergeml_plan_routes' );
