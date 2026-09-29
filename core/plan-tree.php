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
/**
 *  A plan is offered only when the pictures already in a folder sit this much
 *  tighter after it than now by leave-one-out, and no looser by the average
 *  link (vergeml_plan_gain_better; spec-tree-planner story 8: clearly better,
 *  never worse). Story 9's review, from free replays (tools/plan-sim.mjs
 *  --gain): tech's run 4, worse than the site on the truth, gains 0.0140, its
 *  good plans 0.0182-0.0379. No margin separates those with room, so it stays
 *  story 8's 0.02: a good plan near the line is refused and refunded rather
 *  than a worse one offered.
 */
const VERGEML_PLAN_GAIN = 0.02;
/** Under this share of described pictures carrying any audience tag, the Tree step asks the one question (CAP-4, story 6); the shop's own share is about 3 %. */
const VERGEML_PLAN_AUDIENCE_ASK = 0.15;
/** rules.md: a folder holds at least five pictures, a parent at most twelve children (the top level too), the tree at most three levels. */
const VERGEML_PLAN_MIN      = 5;
const VERGEML_PLAN_CHILDREN = 12;
const VERGEML_PLAN_DEPTH    = 3;
/**
 *  Growth of a frozen tree (story 10, vergeml_plan_grow_decide): a new label
 *  joins a folder when its pictures sit within MARGIN of how close the
 *  folder's own sit; the rest group at GROUP; a new folder hangs under the
 *  parent whose subtree is UNDER near. Measured by tools/box-grow-heldout.php.
 */
const VERGEML_PLAN_GROW_JOIN   = 0.7;
const VERGEML_PLAN_GROW_MARGIN = 0.05;
const VERGEML_PLAN_GROW_GROUP  = 0.6;
const VERGEML_PLAN_GROW_UNDER  = 0.5;

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

function vergeml_plan_round4( $x ) {
    return round( $x, 4 );
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

/** The empty before/after measure: vergeml_plan_gain_add() fills it, vergeml_plan_gain_of() reads it. */
function vergeml_plan_gain_acc() {
    return array( 'n' => 0, 'now' => array(), 'now_n' => array(), 'kept' => array(), 'kept_n' => array() );
}

/**
 *  One picture into the before/after measure (vergeml_plan_gain_of). $now is
 *  the folder it sits in ('' for none or To sort), $then the one it will sit
 *  in once the plan fills. Only a picture already in a folder is measured,
 *  on both sides, and only those pictures make a centre: a To-sort picture
 *  the plan files shapes neither (story 9's review -- counted into the after
 *  centre, seven of them lifted a folder of three from 0.671 to 0.747 with
 *  nothing about the three changed).
 */
function vergeml_plan_gain_add( $acc, $v, $now, $then ) {
    if ( '' === $now ) {
        return $acc;
    }
    $acc['n']++;
    $acc['now'][ $now ]   = isset( $acc['now'][ $now ] ) ? vergeml_plan_add( $acc['now'][ $now ], $v ) : $v;
    $acc['now_n'][ $now ] = isset( $acc['now_n'][ $now ] ) ? $acc['now_n'][ $now ] + 1 : 1;
    if ( '' !== $then ) {
        $acc['kept'][ $then ]   = isset( $acc['kept'][ $then ] ) ? vergeml_plan_add( $acc['kept'][ $then ], $v ) : $v;
        $acc['kept_n'][ $then ] = isset( $acc['kept_n'][ $then ] ) ? $acc['kept_n'][ $then ] + 1 : 1;
    }
    return $acc;
}

/**
 *  One folder's share of the leave-one-out measure: for its $nk measured
 *  pictures (their sum $sk), the summed cosine of each to the centre of the
 *  folder's OTHER pictures -- the folder's sum $sa less the picture itself.
 *  A folder of one reads 0 (it groups nothing), and a folder of five is not
 *  flattered by each picture being a fifth of its own centre: counting the
 *  picture itself made the shop's 125 small folders read 0.823 against 0.788
 *  for a plan as good on the truth (story 9). Leave-one-out leans the other
 *  way, towards fewer and larger folders, which is why
 *  vergeml_plan_gain_better() also asks for no loss on
 *  vergeml_plan_gain_link().
 *
 *  From the sums alone, with no second pass over the vectors: the numerator
 *  is exact (sk.sa - nk), each picture's distance to the rest is taken at the
 *  folder's root mean square. Exact for folders of one and two; within
 *  0.0005 of the per-picture sum on all fifty-one replays of shop and tech.
 */
function vergeml_plan_gain_folder( $sk, $nk, $sa ) {
    $cross = vergeml_plan_dot( $sk, $sa );
    $rest  = ( $nk * vergeml_plan_dot( $sa, $sa ) - 2 * $cross + $nk ) / $nk;
    return $rest > 1e-12 ? ( $cross - $nk ) / sqrt( $rest ) : 0.0;
}

/**
 *  One folder's share of the average link: each of its $n pictures' mean
 *  cosine to the others, summed -- (|s|^2 - n) / (n - 1), exact from the sum.
 *  A folder of one reads 0. It leans towards many small folders, the other
 *  way from vergeml_plan_gain_folder, so a plan must not lose on it.
 */
function vergeml_plan_gain_link( $s, $n ) {
    return $n > 1 ? ( vergeml_plan_dot( $s, $s ) - $n ) / ( $n - 1 ) : 0.0;
}

/**
 *  How tight the pictures already in a folder sit, now and after the plan,
 *  two ways: the mean cosine of each to the centre of the rest of its folder
 *  (vergeml_plan_gain_folder), and its mean cosine to each of the others
 *  (vergeml_plan_gain_link). The same pictures on both sides. A picture the
 *  plan leaves without a folder counts 0 after. Nothing filed yet reads 0
 *  before, which any plan beats.
 *
 * @return array{before:float,after:float,link_before:float,link_after:float}
 */
function vergeml_plan_gain_of( $acc ) {
    if ( 0 === $acc['n'] ) {
        return array( 'before' => 0.0, 'after' => 1.0, 'link_before' => 0.0, 'link_after' => 0.0 );
    }
    $out = array( 'before' => 0.0, 'after' => 0.0, 'link_before' => 0.0, 'link_after' => 0.0 );
    foreach ( $acc['now'] as $k => $s ) {
        $out['before']      += vergeml_plan_gain_folder( $s, $acc['now_n'][ $k ], $s );
        $out['link_before'] += vergeml_plan_gain_link( $s, $acc['now_n'][ $k ] );
    }
    foreach ( $acc['kept'] as $k => $s ) {
        $out['after']      += vergeml_plan_gain_folder( $s, $acc['kept_n'][ $k ], $s );
        $out['link_after'] += vergeml_plan_gain_link( $s, $acc['kept_n'][ $k ] );
    }
    foreach ( $out as $k => $x ) {
        $out[ $k ] = $x / $acc['n'];
    }
    return $out;
}

/**
 *  Offered only when clearly tighter by leave-one-out AND no looser by the
 *  average link. Each alone rewards one granularity (story 9's review): a
 *  merge lifts the first, a split the second. Where they disagree the plan
 *  is refused and its credits asked back -- the safer failure.
 */
function vergeml_plan_gain_better( $gain ) {
    return $gain['after'] >= $gain['before'] + VERGEML_PLAN_GAIN && $gain['link_after'] >= $gain['link_before'];
}

/**
 *  The site's before/after for a draft: each described picture's deepest
 *  folder now, and where the fill will put it -- its label's folder in the
 *  draft's label map, unless a person placed it or its label is not in the
 *  map, when it stays. Read in pages of 500 like vergeml_plan_label_sums.
 */
function vergeml_plan_gain( $label_map, $label_index, $taxonomy ) {
    global $wpdb;
    $acc = vergeml_plan_gain_acc();
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
        // A filled plan's re-plan only grows it and costs nothing (story 10, the spec's price constraint).
        'price'             => vergeml_plan_frozen() ? 0 : vergeml_plan_price( count( $inv['labels'] ) ),
        'balance'           => null === $state['remaining'] ? null : (int) $state['remaining'],
        // Asked only while the site's own pictures say too little and the categories say nothing either; the screen stops asking once the session has an answer.
        'audience_ask'      => ! $evidence && vergeml_plan_audience_share( $inv['labels'] ) < VERGEML_PLAN_AUDIENCE_ASK,
        'audience_evidence' => $evidence,
    );
}


/* ---------------------------------------------------------------- the job */

/** The press: books the job, once. The session carries its state so a reload keeps polling. */
function vergeml_plan_rest_start() {
    $s      = vergeml_guide_session();
    $frozen = (bool) vergeml_plan_frozen();
    // A filled plan is planned again for growth, free, even from the confirmed tree (story 10); any other confirmed tree still refuses.
    if ( 'confirmed' === $s['tree'] && ! $frozen ) {
        return vergeml_guide_confirmed_refusal();
    }
    if ( isset( $s['plan']['state'] ) && 'running' === $s['plan']['state'] ) {
        return rest_ensure_response( vergeml_plan_out( $s ) );
    }
    $facts = vergeml_plan_facts();
    if ( 0 === $facts['labels'] ) {
        return new WP_Error( 'empty', __( 'No picture is described yet.', 'vergelabs-media-library' ), array( 'status' => 409 ) );
    }
    $s['plan'] = array( 'state' => 'running', 'at' => time(), 'price' => $facts['price'], 'grow' => $frozen );
    vergeml_guide_save( $s );
    vergeml_plan_schedule();
    return rest_ensure_response( vergeml_plan_out( $s ) );
}

/**
 *  The poll: the plan's state and, once it is done, the session with its
 *  draft. A job cron never started is booked again; a job that started and
 *  holds no lock was stopped by its host mid-plan, and is failed instead --
 *  booked again it would name a new plan and be charged twice.
 */
function vergeml_plan_rest_poll() {
    // The lock before the session: the job saves its answer before it lets the lock go, so a session read after an absent lock is never an older one.
    $locked = get_transient( VERGEML_PLAN_LOCK );
    $s      = vergeml_guide_session();
    if ( isset( $s['plan']['state'] ) && 'running' === $s['plan']['state'] && ! $locked ) {
        if ( ! empty( $s['plan']['started'] ) ) {
            $s = vergeml_plan_lost( $s['plan'] );
        } elseif ( time() - (int) $s['plan']['at'] > VERGEML_PLAN_TIMEOUT + 120 ) {
            $s['plan'] = array( 'state' => 'failed', 'message' => __( 'The plan did not finish. Try again.', 'vergelabs-media-library' ) );
            vergeml_guide_save( $s );
        } elseif ( ! wp_next_scheduled( VERGEML_PLAN_HOOK ) ) {
            vergeml_plan_schedule();
        }
    }
    return rest_ensure_response( vergeml_plan_out( $s ) );
}

/**
 *  A started job that died: failed first, so a second poll meanwhile does not
 *  ask again, then the plan it named asked back -- the service may have
 *  charged it before the host stopped the job. A growth charged nothing.
 */
function vergeml_plan_lost( $plan ) {
    $id = empty( $plan['grow'] ) && ! empty( $plan['id'] ) ? (string) $plan['id'] : '';
    $s  = vergeml_plan_put( array(
        'state'   => 'failed',
        'message' => '' === $id
            ? __( 'The plan did not finish. Try again.', 'vergelabs-media-library' )
            : __( 'The plan did not come back, and it may have been charged. Your balance is on the Licence screen.', 'vergelabs-media-library' ),
    ) );
    if ( '' === $id ) {
        return $s;
    }
    $refund = vergeml_plan_refund( $id );
    if ( null === $refund ) {
        return $s;
    }
    vergeml_plan_credits_left( $refund['credits_remaining'] );
    // Given back, or never charged at all (not_refundable): either way this plan cost nothing.
    return vergeml_plan_put( array( 'state' => 'failed', 'message' => __( 'The plan did not come together. Nothing was charged.', 'vergelabs-media-library' ) ) );
}

/** The plan's state onto the session as it stands now, not as some earlier read of it had it. */
function vergeml_plan_put( $plan ) {
    $s         = vergeml_guide_session();
    $s['plan'] = $plan;
    vergeml_guide_save( $s );
    return $s;
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
    // A job that started once never runs again: the poll fails it and asks its plan back (vergeml_plan_lost).
    if ( ! isset( $s['plan']['state'] ) || 'running' !== $s['plan']['state'] || ! empty( $s['plan']['started'] ) || get_transient( VERGEML_PLAN_LOCK ) ) {
        return;
    }
    set_transient( VERGEML_PLAN_LOCK, time(), VERGEML_PLAN_TIMEOUT + 60 );
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( VERGEML_PLAN_TIMEOUT + 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- a cron job; refused silently where disallowed.
    }
    /*
     *  Named and marked started before anything is spent, in the session and
     *  not only here: a host that stops this job mid-ask leaves the poll a
     *  plan to fail and ask back by name, never one to book again (story 9's
     *  review: a second run was a second charge).
     */
    $plan_id              = wp_generate_uuid4();
    $s['plan']['id']      = $plan_id;
    $s['plan']['started'] = time();
    vergeml_guide_save( $s );

    // A filled plan grows here, with no service call (story 10).
    if ( ! empty( $s['plan']['grow'] ) ) {
        // A growth may start from the confirmed tree; only a confirm made while it ran drops it. It charged nothing, so nothing is asked back.
        $was_confirmed = 'confirmed' === $s['tree'];
        vergeml_plan_grow_event( $s );
        $keys = 'done' === $s['plan']['state'] ? array( 'plan', 'draft', 'fit', 'tree' ) : array( 'plan' );
        if ( 'confirmed' === vergeml_plan_settle( $s, $keys, $plan_id, $was_confirmed ) ) {
            vergeml_plan_put( null );
        }
        delete_transient( VERGEML_PLAN_LOCK );
        return;
    }

    // The split is no longer a guess once the owner has said yes, or the site's own product categories already name one (CAP-4).
    $audience_confirmed = ( isset( $s['audience_split'] ) && 'yes' === $s['audience_split'] ) || vergeml_plan_product_audience_evidence();

    // Forced: the paid plan reads the library through, whatever that costs here, rather than settling for the page render's 15s-or-stale inventory.
    $inv    = vergeml_plan_inventory( true );
    $result = vergeml_plan_ask( $inv['labels'], $audience_confirmed, $plan_id );
    // The ask can take the whole of the lock's time; the guard and the refund come after it.
    set_transient( VERGEML_PLAN_LOCK, time(), VERGEML_PLAN_TIMEOUT + 60 );

    // A working copy: what the plan decides is settled onto the session as it stands at the end (vergeml_plan_settle), never saved over it.
    $s          = vergeml_guide_session();
    $keys       = array( 'plan' );
    $asked_back = false;
    if ( is_wp_error( $result ) ) {
        $message = $result->get_error_message();
        // No answer, or one we cannot read: the service may have charged. Ask for it back by the name this site gave the plan.
        if ( in_array( $result->get_error_code(), array( 'unreachable', 'failed' ), true ) ) {
            $refund     = vergeml_plan_refund( $plan_id );
            $asked_back = true;
            if ( null === $refund && 'unreachable' === $result->get_error_code() ) {
                // Neither given back nor known to be uncharged: say nothing about a charge.
                $message = __( 'The plan did not come back, and it may have been charged. Your balance is on the Licence screen.', 'vergelabs-media-library' );
            }
            vergeml_plan_credits_left( null !== $refund ? $refund['credits_remaining'] : null );
        }
        $s['plan'] = array( 'state' => 'failed', 'message' => $message );
    } elseif ( 'confirmed' !== $s['tree'] ) {
        // An older service sends its one kept tree; a current one every valid tree, to choose among here.
        $trees      = ! empty( $result['trees'] ) ? $result['trees'] : array( array( 'folders' => $result['folders'], 'unfiled' => isset( $result['unfiled'] ) ? $result['unfiled'] : array() ) );
        $chosen     = vergeml_plan_choose( $trees, vergeml_plan_label_sums( $inv['labels'] ), array_column( $inv['labels'], 'count', 'id' ) );
        $draft    = vergeml_plan_draft( $chosen['folders'], $inv['labels'] );
        $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
        $gain     = vergeml_plan_gain( $draft['label_map'], array_column( $inv['labels'], 'id', 'label' ), $taxonomy );
        // Not clearly tidier than the folders the site already has: the plan is not offered (spec-tree-planner story 8), and its credits are asked back (story 9).
        $better = vergeml_plan_gain_better( $gain );
        if ( ! $better ) {
            // Reading a large library's vectors twice can take a while; the lock outlives the refund.
            set_transient( VERGEML_PLAN_LOCK, time(), VERGEML_PLAN_TIMEOUT + 60 );
        }
        $refund     = ! $better && ! empty( $result['plan'] ) ? vergeml_plan_refund( (string) $result['plan'] ) : null;
        $asked_back = ! $better;
        $back       = null !== $refund ? $refund['refunded'] : 0;
        if ( $better ) {
            $s['draft'] = $draft;
            $keys       = array( 'plan', 'draft', 'fit' );
            if ( '' !== $taxonomy ) {
                vergeml_guide_fit_take( $s, $taxonomy );
            }
        }
        $s['plan'] = array(
            'state'   => $better ? 'done' : 'kept',
            'message' => $better ? '' : ( $back > 0
                ? __( 'Your folders are already well organised — a new plan wouldn\'t improve them. Nothing was charged.', 'vergelabs-media-library' )
                : __( 'Your folders are already well organised — a new plan wouldn\'t improve them.', 'vergelabs-media-library' ) ),
            'gain'    => array_map( 'vergeml_plan_round4', $gain ),
            'charged' => max( 0, ( isset( $result['charged'] ) ? (int) $result['charged'] : 0 ) - $back ),
            'refunded'=> $back,
            'runs'    => isset( $result['runs'] ) ? $result['runs'] : null,
            'kept'    => $chosen['index'],
            'placed'  => $chosen['placed'],
            'left_out'=> (int) $inv['unlabelled'],
        );
        vergeml_plan_credits_left( null !== $refund && null !== $refund['credits_remaining'] ? $refund['credits_remaining'] : ( isset( $result['credits_remaining'] ) ? (int) $result['credits_remaining'] : null ) );
    }
    $settled = 'confirmed' === $s['tree'] ? 'confirmed' : vergeml_plan_settle( $s, $keys, $plan_id, false );
    if ( 'confirmed' === $settled ) {
        // Confirmed while the plan ran: the confirmed tree wins, the plan is dropped, and its credits are asked back -- the owner never sees it.
        $refund = ! $asked_back && ! is_wp_error( $result ) && ! empty( $result['plan'] ) ? vergeml_plan_refund( (string) $result['plan'] ) : null;
        vergeml_plan_credits_left( null !== $refund && null !== $refund['credits_remaining'] ? $refund['credits_remaining'] : ( ! is_wp_error( $result ) && isset( $result['credits_remaining'] ) ? (int) $result['credits_remaining'] : null ) );
        vergeml_plan_put( null );
    }
    delete_transient( VERGEML_PLAN_LOCK );
}

/**
 *  What the job decided, onto the session as it stands now. The job read the
 *  session before passes that can take minutes and a refund; a turn, an edit
 *  or a confirm made meanwhile is the owner's, so only the keys the plan owns
 *  are written. Nothing is written when the tree was confirmed meanwhile
 *  (the caller drops the plan and asks its credits back), or when the plan
 *  is no longer this job's -- the poll failed it and asked it back already.
 *
 * @return string 'saved', 'confirmed' or 'gone'.
 */
function vergeml_plan_settle( $w, $keys, $plan_id, $was_confirmed ) {
    $s = vergeml_guide_session();
    if ( ! isset( $s['plan']['id'] ) || $plan_id !== $s['plan']['id'] ) {
        return 'gone';
    }
    if ( ! $was_confirmed && 'confirmed' === $s['tree'] ) {
        return 'confirmed';
    }
    foreach ( $keys as $k ) {
        $s[ $k ] = $w[ $k ];
    }
    vergeml_guide_save( $s );
    return 'saved';
}

/** The balance the service last named, for the button; null leaves the cached one. */
function vergeml_plan_credits_left( $left ) {
    if ( null === $left ) {
        return;
    }
    $credits              = get_option( 'vergeml_ai_credits', array() );
    $credits              = is_array( $credits ) ? $credits : array();
    $credits['remaining'] = (int) $left;
    $credits['time']      = time();
    update_option( 'vergeml_ai_credits', $credits, false );
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
 * @param string $plan              The plan's name, a uuid this site made
 *                                  (story 9's review): the service charges
 *                                  under it, so the credits can be asked back
 *                                  by it when no answer arrives.
 */
function vergeml_plan_ask( $labels, $audience_confirmed = false, $plan = '' ) {
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
        'body'      => wp_json_encode( array( 'license_key' => $licence, 'site' => home_url(), 'labels' => $send, 'audienceConfirmed' => (bool) $audience_confirmed, 'plan' => $plan ) ),
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
 *  A plan the site's own folders already beat, given back (spec-tree-planner
 *  story 9). The never-worse guard runs here, after the service charged,
 *  because it needs the pictures' vectors, which never leave the site. The
 *  service gives back what it charged for the plan it names -- only within
 *  the hour, once per site in thirty days, one per activated site per licence
 *  -- so a second plan the guard keeps inside the month stays charged. Sends
 *  the licence key, the site's address and the plan's id the service issued;
 *  nothing about the pictures or the folders. One retry on a network failure:
 *  a refund lost to a blip is credits an owner paid for nothing.
 *
 * @return array{refunded:int,credits_remaining:int|null}|null The refund; refunded 0
 *         when the service holds nothing to give back for this plan (never
 *         charged, or given back already -- not_refundable); null when it
 *         refused otherwise or could not be reached, so nothing is known.
 */
function vergeml_plan_refund( $plan ) {
    $licence = function_exists( 'vergeml_ai_settings' ) ? vergeml_ai_unseal( vergeml_ai_settings()['license_key'] ) : '';
    if ( '' === $licence ) {
        return null;
    }
    for ( $try = 0; $try < 2; $try++ ) {
        $response = wp_remote_post( vergeml_ai_service_url() . '/plan-tree/refund', array(
            'timeout'   => 20,
            'headers'   => array( 'Content-Type' => 'application/json' ),
            'sslverify' => true,
            'body'      => wp_json_encode( array( 'license_key' => $licence, 'site' => home_url(), 'plan' => $plan ) ),
        ) );
        if ( ! is_wp_error( $response ) ) {
            break;
        }
    }
    $data = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );
    $code = (int) wp_remote_retrieve_response_code( $response );
    if ( 404 === $code && isset( $data['error'] ) && 'not_refundable' === $data['error'] ) {
        return array( 'refunded' => 0, 'credits_remaining' => null );
    }
    if ( 200 !== $code || ! isset( $data['refunded'], $data['credits_remaining'] ) ) {
        return null;
    }
    return array( 'refunded' => (int) $data['refunded'], 'credits_remaining' => (int) $data['credits_remaining'] );
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


/* ------------------------------------------------ growth of a frozen tree */

/*
 *  Planning again once a plan is filled (spec-tree-planner story 10, CAP-2).
 *  The frozen folders and every label in the frozen map stay exactly as they
 *  are. Only a label the map does not hold, and only its pictures waiting in
 *  no folder (or in To sort), are planned: a label whose pictures sit nearest
 *  one existing folder joins it, and the rest, grouped by their broader class
 *  and kind, become a new folder where a group reaches VERGEML_PLAN_MIN. No
 *  model and no service call: it is the site's vectors, so it is free and the
 *  same library always plans the same growth.
 */

/**
 *  The frozen label -> term id map an accepted plan filled (core/folder-talk.php),
 *  only the entries whose folder still exists: empty until a plan was filled,
 *  and empty again once its folders are gone (the box's shop held 399 labels
 *  pointing at 49 deleted folders), so such a site plans from scratch again.
 */
function vergeml_plan_frozen() {
    $map = defined( 'VERGEML_TALK_LABEL_MAP' ) ? get_option( VERGEML_TALK_LABEL_MAP ) : array();
    if ( ! is_array( $map ) || ! $map ) {
        return array();
    }
    $live = get_terms( array(
        'taxonomy'   => function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category',
        'include'    => array_values( array_unique( array_map( 'intval', $map ) ) ),
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );
    $live = array_flip( array_map( 'intval', is_array( $live ) ? $live : array() ) );
    return array_filter( $map, function ( $tid ) use ( $live ) {
        return isset( $live[ (int) $tid ] );
    } );
}

/** The frozen map as a draft's label_map: label -> 't<term id>', the key every existing folder has in a draft. */
function vergeml_plan_frozen_keys() {
    $out = array();
    foreach ( vergeml_plan_frozen() as $label => $tid ) {
        $out[ (string) $label ] = 't' . (int) $tid;
    }
    return $out;
}

/** A picture waits when it sits in no folder but To sort. Its deepest folder otherwise, as vergeml_plan_gain reads it. */
function vergeml_plan_grow_now( $in_terms, $depth, $skip ) {
    $now = 0;
    $at  = -1;
    foreach ( array_filter( array_map( 'intval', explode( ',', (string) $in_terms ) ) ) as $tid ) {
        if ( isset( $skip[ $tid ] ) || ! isset( $depth[ $tid ] ) ) {
            continue;
        }
        if ( $depth[ $tid ] > $at || ( $depth[ $tid ] === $at && $tid < $now ) ) {
            $at  = $depth[ $tid ];
            $now = $tid;
        }
    }
    return $now;
}

/** Each term's depth (a top-level folder is 1) from term id => parent id. */
function vergeml_plan_grow_depths( $parent_of ) {
    $depth = array();
    foreach ( $parent_of as $tid => $p ) {
        $d = 1;
        while ( isset( $parent_of[ $p ] ) && $d < 32 ) {
            $p = $parent_of[ $p ];
            $d++;
        }
        $depth[ (int) $tid ] = $d;
    }
    return $depth;
}

/**
 *  What the growth reads from the site: every existing folder with the
 *  vectors of the pictures in it (its own, and its whole subtree's), and every
 *  label the frozen map does not hold with the vectors of its waiting
 *  pictures. A picture placed by a person or its product is never counted
 *  as waiting: it stays where it is. Read in pages of 500, as
 *  vergeml_plan_gain is.
 *
 * @return array{new:array,folders:array}
 */
function vergeml_plan_grow_read( $frozen, $taxonomy ) {
    global $wpdb;
    $folders = array();
    $terms   = '' !== $taxonomy ? get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) : array();
    $parent  = array();
    $skip    = array();
    $shape   = array();
    foreach ( is_array( $terms ) ? $terms : array() as $t ) {
        $tid            = (int) $t->term_id;
        $parent[ $tid ] = (int) $t->parent;
        $waiting        = defined( 'VERGEML_FILING_TO_SORT_SLUG' ) && VERGEML_FILING_TO_SORT_SLUG === $t->slug;
        if ( $waiting ) {
            $skip[ $tid ] = true;
        }
        $folders[ $tid ] = array(
            'name'   => vergeml_term_name( $t ),
            'parent' => (int) $t->parent,
            'direct' => null,
            'n'      => 0,
            'sub'    => null,
            // Where the fill could file: not To sort, not a locked folder, not a view (vergeml_filing_label_folders refuses the last two).
            'target' => ! $waiting && ! ( defined( 'VERGEML_FILING_LOCKED' ) && get_term_meta( $tid, VERGEML_FILING_LOCKED, true ) ),
        );
    }
    $depth = vergeml_plan_grow_depths( $parent );
    if ( function_exists( 'vergeml_filing_views' ) ) {
        foreach ( $folders as $tid => $f ) {
            $path = array();
            for ( $p = $tid, $g = 0; isset( $folders[ $p ] ) && $g < 32; $p = $folders[ $p ]['parent'], $g++ ) {
                array_unshift( $path, $folders[ $p ]['name'] );
            }
            $shape[ $tid ] = array( 'parent_id' => $f['parent'], 'path' => $path );
        }
        foreach ( vergeml_filing_views( $shape ) as $tid => $p ) {
            if ( ! empty( $p['view'] ) ) {
                $folders[ $tid ]['target'] = false;
            }
        }
    }

    $kept = array();
    if ( defined( 'VERGEML_FILING_PLACED_BY' ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one read of the pictures a person or a product placed.
        $kept = array_flip( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value IN ('user','answer','product')", VERGEML_FILING_PLACED_BY ) ) ) );
    }
    $t = $wpdb->vergeml_ai_index;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $dims  = (int) $wpdb->get_var( "SELECT embedding_dims FROM {$t} WHERE error = '' AND embedding IS NOT NULL GROUP BY embedding_dims ORDER BY COUNT(*) DESC LIMIT 1" );
    $new   = array();
    $after = 0;
    do {
        $rows = '' !== $taxonomy && function_exists( 'vergeml_guide_rule_rows' ) ? (array) vergeml_guide_rule_rows( $taxonomy, 'all', array( 'filing', 'terms', 'embedding' ), $after, 500 ) : array();
        foreach ( $rows as $r ) {
            $after = (int) $r['attachment_id'];
            $v     = vergeml_index_vector_out( $r['embedding'] );
            if ( ! $v || ( $dims > 0 && count( $v ) !== $dims ) ) {
                continue;
            }
            $v   = vergeml_plan_unit( $v );
            $now = vergeml_plan_grow_now( $r['in_terms'], $depth, $skip );
            if ( $now ) {
                $folders[ $now ]['direct'] = null === $folders[ $now ]['direct'] ? $v : vergeml_plan_add( $folders[ $now ]['direct'], $v );
                $folders[ $now ]['n']++;
                for ( $p = $now, $g = 0; isset( $folders[ $p ] ) && $g < 32; $p = $folders[ $p ]['parent'], $g++ ) {
                    $folders[ $p ]['sub'] = null === $folders[ $p ]['sub'] ? $v : vergeml_plan_add( $folders[ $p ]['sub'], $v );
                }
                continue;
            }
            $filing = json_decode( (string) $r['filing'], true );
            if ( isset( $kept[ $after ] ) || '' !== vergeml_plan_effective_label( $r['kind'], $filing, $frozen ) ) {
                continue;
            }
            $label = vergeml_plan_label_of( $r['kind'], $filing );
            if ( '' === $label ) {
                continue;
            }
            if ( ! isset( $new[ $label ] ) ) {
                $classes       = vergeml_filing_classes_of_object( is_array( $filing ) && isset( $filing['object'] ) ? $filing['object'] : '' );
                $kind          = sanitize_key( (string) $r['kind'] );
                $new[ $label ] = array(
                    'sum'    => null,
                    'n'      => 0,
                    'object' => $classes[0],
                    'class'  => isset( $classes[1] ) ? $classes[1] : $classes[0],
                    'kind'   => '' === $kind ? 'photo' : $kind,
                );
            }
            $new[ $label ]['sum'] = null === $new[ $label ]['sum'] ? $v : vergeml_plan_add( $new[ $label ]['sum'], $v );
            $new[ $label ]['n']++;
        }
    } while ( 500 === count( $rows ) );

    return array( 'new' => $new, 'folders' => $folders );
}

/** A new folder's name from its group: the class ("Footwear"), with the kind for a non-photo ("Software screenshots"); three words at most. */
function vergeml_plan_grow_name( $class, $kind ) {
    $words  = array_values( array_filter( preg_split( '/\s+/u', str_replace( '/', ' ', trim( (string) $class ) ) ), 'strlen' ) );
    $suffix = 'photo' === $kind || '' === (string) $kind ? '' : $kind . 's';
    $room   = '' === $suffix ? 3 : 2;
    $words  = array_slice( $words, -$room );
    if ( '' !== $suffix ) {
        $words[] = $suffix;
    }
    $name = implode( ' ', $words );
    return '' === $name ? '' : mb_strtoupper( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 );
}

/**
 *  The growth, decided: pure arithmetic on what vergeml_plan_grow_read gave.
 *
 *  1. A new label joins the existing folder its pictures sit nearest, when
 *     they sit about as close to that folder's centre as the folder's own
 *     pictures do: their mean cosine to the centre at least the folder's own
 *     (each member against the rest, vergeml_plan_gain_folder) less 'margin'.
 *     An absolute cosine does not tell a label's folder from its siblings:
 *     on the shop a label sits at 0.7-0.9 to half a department.
 *  2. The rest group by likeness: largest first, a label joins the first
 *     group of its kind whose centre is at 'group' or more, else starts one.
 *     A group of VERGEML_PLAN_MIN waiting pictures or more is a new folder,
 *     named for the broader class most of its pictures share. It hangs under
 *     the existing parent whose subtree its pictures sit nearest, at 'under'
 *     or more, among parents with children, under VERGEML_PLAN_CHILDREN,
 *     and leaving it within VERGEML_PLAN_DEPTH; else at the top while the
 *     top has room; else under the nearest parent with room. A group named
 *     like an existing folder joins it.
 *  3. Anything else is left for the matcher, as it is today.
 *
 *  Labels in text order and groups largest first, so the same input always
 *  gives the same growth.
 *
 * @param array $new     label => {sum, n, object, class, kind}.
 * @param array $folders term id => {name, parent, direct, n, sub, target}.
 * @param array $opts    margin, group, under: the defaults are VERGEML_PLAN_GROW_*.
 * @return array{place:array,grow:array,left:array} place: label => term id;
 *               grow: [{name, parent, labels, n, objects, kinds}]; left: labels.
 */
function vergeml_plan_grow_decide( $new, $folders, $opts = array() ) {
    $opts = array_merge( array( 'join' => VERGEML_PLAN_GROW_JOIN, 'margin' => VERGEML_PLAN_GROW_MARGIN, 'group' => VERGEML_PLAN_GROW_GROUP, 'under' => VERGEML_PLAN_GROW_UNDER ), $opts );
    ksort( $new, SORT_STRING );
    ksort( $folders );

    $parent_of = array();
    foreach ( $folders as $tid => $f ) {
        $parent_of[ (int) $tid ] = (int) $f['parent'];
    }
    $depth = vergeml_plan_grow_depths( $parent_of );
    $kids  = array( 0 => 0 );
    $named = array();
    foreach ( $folders as $tid => $f ) {
        $p          = isset( $folders[ (int) $f['parent'] ] ) ? (int) $f['parent'] : 0;
        $kids[ $p ] = ( isset( $kids[ $p ] ) ? $kids[ $p ] : 0 ) + 1;
        $named[ mb_strtolower( (string) $f['name'] ) ] = (int) $tid;
    }
    $direct = array();
    $tight  = array();
    $sub    = array();
    foreach ( $folders as $tid => $f ) {
        if ( empty( $f['target'] ) ) {
            continue;
        }
        // A folder of one says nothing about how close its pictures sit; nothing is judged to join it.
        if ( ! empty( $f['direct'] ) && (int) $f['n'] >= 2 ) {
            $direct[ (int) $tid ] = vergeml_plan_unit( $f['direct'] );
            $tight[ (int) $tid ]  = vergeml_plan_gain_folder( $f['direct'], (int) $f['n'], $f['direct'] ) / (int) $f['n'];
        }
        if ( ! empty( $f['sub'] ) && ! empty( $kids[ (int) $tid ] ) ) {
            $sub[ (int) $tid ] = vergeml_plan_unit( $f['sub'] );
        }
    }
    $nearest = function ( $v, $centres, $ok ) {
        $to  = 0;
        $top = -2.0;
        foreach ( $centres as $tid => $c ) {
            if ( ! $ok( $tid ) ) {
                continue;
            }
            $x = vergeml_plan_dot( $v, $c );
            if ( $x > $top ) {
                $top = $x;
                $to  = $tid;
            }
        }
        return array( $to, $top );
    };
    $any = function () {
        return true;
    };

    $place = array();
    $rest  = array();
    foreach ( $new as $label => $l ) {
        if ( empty( $l['sum'] ) || (int) $l['n'] < 1 ) {
            continue;
        }
        list( $to, $top ) = $nearest( vergeml_plan_unit( $l['sum'] ), $direct, $any );
        // Near in absolute terms, and the label's pictures (their mean cosine to that centre) about as close as the folder's own.
        if ( $to && $top >= $opts['join'] && vergeml_plan_dot( $l['sum'], $direct[ $to ] ) / (int) $l['n'] >= $tight[ $to ] - $opts['margin'] ) {
            $place[ (string) $label ] = $to;
            continue;
        }
        $rest[ (string) $label ] = $l;
    }

    // Largest first, then text: the order the groups form in is part of the answer.
    uksort( $rest, function ( $a, $b ) use ( $rest ) {
        return (int) $rest[ $b ]['n'] - (int) $rest[ $a ]['n'] ?: strcmp( $a, $b );
    } );
    $groups = array();
    foreach ( $rest as $label => $l ) {
        $v  = vergeml_plan_unit( $l['sum'] );
        $at = -1;
        foreach ( $groups as $i => $g ) {
            if ( $g['kind'] === $l['kind'] && vergeml_plan_dot( $v, vergeml_plan_unit( $g['sum'] ) ) >= $opts['group'] ) {
                $at = $i;
                break;
            }
        }
        if ( $at < 0 ) {
            $groups[] = array( 'kind' => $l['kind'], 'labels' => array(), 'n' => 0, 'sum' => null, 'objects' => array(), 'classes' => array() );
            $at       = count( $groups ) - 1;
        }
        $groups[ $at ]['labels'][]  = (string) $label;
        $groups[ $at ]['objects'][] = $l['object'];
        $groups[ $at ]['classes'][ $l['class'] ] = ( isset( $groups[ $at ]['classes'][ $l['class'] ] ) ? $groups[ $at ]['classes'][ $l['class'] ] : 0 ) + (int) $l['n'];
        $groups[ $at ]['n']        += (int) $l['n'];
        $groups[ $at ]['sum']       = null === $groups[ $at ]['sum'] ? $l['sum'] : vergeml_plan_add( $groups[ $at ]['sum'], $l['sum'] );
    }
    usort( $groups, function ( $a, $b ) {
        return $b['n'] - $a['n'] ?: strcmp( $a['labels'][0], $b['labels'][0] );
    } );

    $room = function ( $tid ) use ( &$kids, $depth ) {
        return $kids[ $tid ] < VERGEML_PLAN_CHILDREN && $depth[ $tid ] < VERGEML_PLAN_DEPTH;
    };
    $grow = array();
    $left = array();
    foreach ( $groups as $g ) {
        // The broader class most of the group's pictures share names it; a tie goes to the first in text order.
        ksort( $g['classes'], SORT_STRING );
        arsort( $g['classes'] );
        $name = vergeml_plan_grow_name( (string) key( $g['classes'] ), $g['kind'] );
        if ( $g['n'] < VERGEML_PLAN_MIN || '' === $name ) {
            $left = array_merge( $left, $g['labels'] );
            continue;
        }
        $lower = mb_strtolower( $name );
        if ( isset( $named[ $lower ] ) ) {
            // Already a folder of that name: it is that folder (the plan's own near-copy rule), when the fill may file there.
            $tid = $named[ $lower ];
            if ( $tid > 0 && ! empty( $folders[ $tid ]['target'] ) ) {
                foreach ( $g['labels'] as $label ) {
                    $place[ $label ] = $tid;
                }
            } else {
                $left = array_merge( $left, $g['labels'] );
            }
            continue;
        }
        $v = vergeml_plan_unit( $g['sum'] );
        list( $to, $top ) = $nearest( $v, $sub, $room );
        $parent = $to && $top >= $opts['under'] ? $to : ( $kids[0] < VERGEML_PLAN_CHILDREN ? 0 : $to );
        if ( 0 === $parent && $kids[0] >= VERGEML_PLAN_CHILDREN ) {
            $left = array_merge( $left, $g['labels'] );
            continue;
        }
        $kids[ $parent ]++;
        $named[ $lower ] = 0;
        $grow[]          = array(
            'name'    => $name,
            'parent'  => $parent,
            'labels'  => $g['labels'],
            'n'       => $g['n'],
            'objects' => array_values( array_unique( $g['objects'] ) ),
            'kinds'   => array( $g['kind'] ),
        );
    }
    sort( $left, SORT_STRING );
    return array( 'place' => $place, 'grow' => $grow, 'left' => $left );
}

/**
 *  The growth as the screen's draft: every existing folder exactly where it
 *  stands, the new folders under their parents, and the label map the frozen
 *  one plus the growth. Existing folders are marked asked, so the confirm
 *  asks the planner nothing about them and a grown tree confirms for free.
 *  Its origin is 'grow': the fill files only the growth's waiting pictures
 *  (vergeml_plan_grow_assign), never the whole library.
 */
function vergeml_plan_grow_draft( $decided, $frozen, $taxonomy ) {
    $terms = '' !== $taxonomy ? get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) : array();
    $out   = array( 'folders' => array(), 'gone' => array(), 'tags' => array(), 'origin' => 'grow', 'rule' => null, 'label_map' => array() );
    foreach ( is_array( $terms ) ? $terms : array() as $t ) {
        $out['folders'][] = array(
            'key' => 't' . $t->term_id, 'term_id' => (int) $t->term_id, 'name' => vergeml_term_name( $t ),
            'parent' => $t->parent ? 't' . $t->parent : '', 'count' => null, 'matches' => '',
            'classes' => array(), 'nowords' => false, 'kinds' => array(), 'audience' => '', 'by' => '', 'asked' => true,
        );
    }
    foreach ( (array) $frozen as $label => $tid ) {
        $out['label_map'][ (string) $label ] = 't' . (int) $tid;
    }
    foreach ( (array) $decided['place'] as $label => $tid ) {
        $out['label_map'][ (string) $label ] = 't' . (int) $tid;
    }
    foreach ( (array) $decided['grow'] as $i => $g ) {
        $out['folders'][] = array(
            'key' => 'g' . $i, 'term_id' => null, 'name' => $g['name'], 'parent' => $g['parent'] ? 't' . $g['parent'] : '',
            'count' => null, 'matches' => '', 'classes' => $g['objects'], 'nowords' => false, 'kinds' => $g['kinds'],
            'audience' => '', 'by' => '', 'asked' => false,
        );
        foreach ( $g['labels'] as $label ) {
            $out['label_map'][ (string) $label ] = 'g' . $i;
        }
    }
    return vergeml_guide_clean_draft( $out );
}

/**
 *  What a grown draft's fill files: each waiting picture (in no folder but To
 *  sort, not placed by a person or a product) whose label the draft's map
 *  holds and the frozen map does not, into that label's folder. Nothing
 *  else is looked at, so no existing picture moves. Worked out when asked,
 *  not when planned, so a picture described in between is filed too.
 *
 * @return array{assign:array,waiting:int} attachment id => draft key, and every waiting picture.
 */
function vergeml_plan_grow_assign( $draft, $taxonomy ) {
    global $wpdb;
    $grow = array_diff_key( (array) ( isset( $draft['label_map'] ) ? $draft['label_map'] : array() ), vergeml_plan_frozen() );
    $out  = array( 'assign' => array(), 'waiting' => 0 );
    if ( '' === $taxonomy || ! function_exists( 'vergeml_guide_rule_rows' ) ) {
        return $out;
    }
    $parent = array();
    $skip   = array();
    foreach ( (array) get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $t ) {
        $parent[ (int) $t->term_id ] = (int) $t->parent;
        if ( defined( 'VERGEML_FILING_TO_SORT_SLUG' ) && VERGEML_FILING_TO_SORT_SLUG === $t->slug ) {
            $skip[ (int) $t->term_id ] = true;
        }
    }
    $depth = vergeml_plan_grow_depths( $parent );
    $kept  = array();
    if ( defined( 'VERGEML_FILING_PLACED_BY' ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one read of the pictures a person or a product placed.
        $kept = array_flip( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value IN ('user','answer','product')", VERGEML_FILING_PLACED_BY ) ) ) );
    }
    $after = 0;
    do {
        $rows = (array) vergeml_guide_rule_rows( $taxonomy, 'all', array( 'filing', 'terms' ), $after, 500 );
        foreach ( $rows as $r ) {
            $after = (int) $r['attachment_id'];
            if ( vergeml_plan_grow_now( $r['in_terms'], $depth, $skip ) ) {
                continue;
            }
            $out['waiting']++;
            if ( ! $grow || isset( $kept[ $after ] ) ) {
                continue;
            }
            $label = vergeml_plan_label_of( $r['kind'], json_decode( (string) $r['filing'], true ) );
            if ( '' !== $label && isset( $grow[ $label ] ) ) {
                $out['assign'][ $after ] = (string) $grow[ $label ];
            }
        }
    } while ( 500 === count( $rows ) );
    return $out;
}

/**
 *  A grown draft's dry run, in vergeml_guide_draft_fit's shape, from the very
 *  assignment the fill will make: what each folder holds after, how many
 *  pictures move, and how many still wait. No matcher and no phrase vectors.
 */
function vergeml_plan_grow_fit( $draft, $taxonomy ) {
    $a      = vergeml_plan_grow_assign( $draft, $taxonomy );
    $live   = function_exists( 'vergeml_guide_live_index' ) ? vergeml_guide_live_index( $taxonomy ) : array( 'by_id' => array() );
    $landed = array_count_values( $a['assign'] );
    $looked = function_exists( 'vergeml_guide_described_count' ) ? (int) vergeml_guide_described_count() : 0;
    $move   = count( $a['assign'] );
    $stay   = max( 0, $a['waiting'] - $move );
    $counts = array();
    foreach ( $draft['folders'] as $f ) {
        $key = (string) $f['key'];
        $tid = (int) $f['term_id'];
        $in  = isset( $landed[ $key ] ) ? $landed[ $key ] : 0;
        $counts[ $key ] = $tid && isset( $live['by_id'][ $tid ] ) ? (int) $live['by_id'][ $tid ]['count'] + $in : $in;
    }
    $tally = function_exists( 'vergeml_filing_tally_fresh' ) ? vergeml_filing_tally_fresh() : array();
    $tally = array_merge( $tally, array( 'looked' => $looked, 'fits' => max( 0, $looked - $stay ), 'sure' => max( 0, $looked - $stay ), 'nothing' => $stay ) );
    return array(
        'counted' => true,
        'counts'  => $counts,
        'unfiled' => array( 'floor' => 0, 'margin' => 0, 'gated' => 0, 'to_sort' => $stay ),
        'move'    => $move,
        'looked'  => $looked,
        'preview' => array(
            /* translators: 1: pictures that move, 2: folders they go to */
            array( 'text' => $move ? sprintf( _n( '%1$s picture moves into %2$s folders', '%1$s pictures move into %2$s folders', $move, 'vergelabs-media-library' ), number_format_i18n( $move ), number_format_i18n( count( $landed ) ) ) : __( '0 pictures move', 'vergelabs-media-library' ), 'strong' => true ),
        ),
        'tally'   => $tally,
        'residue' => null,
    );
}

/** The job's growth half: the frozen tree planned again, on this site alone, for nothing. */
function vergeml_plan_grow_event( &$s ) {
    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
    $frozen   = vergeml_plan_frozen();
    $read     = vergeml_plan_grow_read( $frozen, $taxonomy );
    $decided  = vergeml_plan_grow_decide( $read['new'], $read['folders'] );
    $grown    = $decided['place'] || $decided['grow'];
    if ( $grown ) {
        $s['draft'] = vergeml_plan_grow_draft( $decided, $frozen, $taxonomy );
        // The owner says yes to the growth before anything is filed: back to the tree, where "This is my tree" confirms it.
        $s['tree'] = 'editing';
        vergeml_guide_fit_take( $s, $taxonomy );
    }
    $s['plan'] = array(
        'state'   => $grown ? 'done' : 'kept',
        'message' => $grown ? '' : __( 'No new folders to propose.', 'vergelabs-media-library' ),
        'grow'    => true,
        'charged' => 0,
        'folders' => count( $decided['grow'] ),
        'placed'  => count( $decided['place'] ),
        'left'    => count( $decided['left'] ),
    );
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
