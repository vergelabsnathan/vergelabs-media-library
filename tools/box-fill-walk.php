<?php
/**
 *  The walk, by script (every-picture-a-home A.4).
 *
 *  Step 2 and Step 3 of the spec, end to end, on the box's real library and
 *  real index, with every stop asserted rather than read:
 *
 *      undo any Move still open  ->  the last tree as a fixture  ->  confirm
 *      ->  fill  ->  every question answered by script  ->  0 in no folder
 *      ->  undo, and the library exactly as it was.
 *
 *  The answers are the plan's: keep-parent for a sibling question, new-folder
 *  for a residue group of twenty or more, leave for the rest. What "leave"
 *  leaves goes into To sort, and the walk asserts To sort holds exactly that.
 *
 *      node tools/verify.mjs fill-walk            # or: bash tools/box-fill-walk.sh
 *
 *  Spends nothing. The tree is a fixture (the session's draft on 2026-09-15,
 *  the tree Nathan's Move made on 09-14), so no propose; confirm asks the
 *  planner about the folders the fixture gives no classes for (metered, no
 *  credit -- see the walk's own line for how many); the residue groups are
 *  named through /name-group, metered the same way; no picture is described.
 *
 *  Nothing stays. Every membership, folder, profile, lock, placed-by mark,
 *  option and moves row is recorded before the first change and put back
 *  after the undo -- the undo is asserted, and then the snapshot is applied
 *  over it, so a gap in undo is a red row here rather than a changed library.
 *
 *  Mutations this catches (on the box copy): the `leave` branch removed from
 *  vergeml_filing_answer_plan() -> "0 in no folder" red; the sibling branch
 *  removed from vergeml_filing_pick() -> "at least one sibling question" red.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_guide_confirm' ) || ! function_exists( 'vergeml_talk_answer' ) || ! function_exists( 'vergeml_guide_draft_fit' ) ) {
    echo "this build has no confirm, no answer or no preview -- plugin inactive, or before A.3?\n";
    exit( 1 );
}

global $wpdb;

wp_set_current_user( 1 );
set_time_limit( 0 );

$GLOBALS['fw_pass'] = 0;
$GLOBALS['fw_fail'] = 0;

// $GLOBALS, not `global`: wp eval-file runs this inside a function (tests/tree/filing-trail.php).
function fw_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['fw_pass']++;
    } else {
        $GLOBALS['fw_fail']++;
    }
    echo sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function fw_tally_line( $t ) {
    return sprintf( 'looked %d = fits %d (sure %d, likely %d) + siblings %d + nothing %d (floor %d, margin %d, gated %d) + kept %d',
        (int) $t['looked'], (int) $t['fits'], (int) $t['sure'], (int) $t['likely'], (int) $t['siblings'], (int) $t['nothing'],
        (int) $t['why']['floor'], (int) $t['why']['margin'], (int) $t['why']['gated'], (int) $t['kept'] );
}

$fw_tax   = vergeml_librarian_taxonomy();
$fw_index = $wpdb->vergeml_ai_index;
$fw_moves = $wpdb->vergeml_librarian_moves;
$fw_bat   = $wpdb->vergeml_librarian_batches;
$fw_t0    = microtime( true );

if ( '' === $fw_tax || ! taxonomy_exists( $fw_tax ) ) {
    echo "no folder taxonomy on this site\n";
    exit( 1 );
}
if ( ! empty( vergeml_talk_progress()['running'] ) ) {
    echo "a fill is running on this site -- not walking over it\n";
    exit( 1 );
}

vergeml_librarian_maybe_install();

/* ------------------------------------------------------------- the fixture */

/*
 *  The last tree: the Folders screen's draft as the box's session held it on
 *  2026-09-15, which is the tree Nathan's Move made on 09-14 with the
 *  planner's classes. Five folders carry no classes (2026, Cooling,
 *  Interviews, Launches, September); confirm asks the planner about the ones
 *  with no plan stored either, and the walk prints how many that was.
 */
$fw_fixture = array( 'folders' => array(
    array( 'key' => 't2420', 'term_id' => 2420, 'name' => '2026', 'parent' => '', 'classes' => array(), 'kinds' => array(), 'audience' => '', 'matches' => '' ),
    array( 'key' => 't2427', 'term_id' => 2427, 'name' => 'Batteries', 'parent' => 't2422', 'classes' => array( 'battery', 'electronics' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Battery packs and battery systems with terminals and casings' ),
    array( 'key' => 't2428', 'term_id' => 2428, 'name' => 'Components', 'parent' => 't2423', 'classes' => array( 'electronics component', 'semiconductor component', 'circuit board' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Circuit boards, chips, wafers and internal PC components' ),
    array( 'key' => 't2429', 'term_id' => 2429, 'name' => 'Conference talks', 'parent' => 't2424', 'classes' => array( 'event', 'people' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Speakers and audiences at technology conferences' ),
    array( 'key' => 't2430', 'term_id' => 2430, 'name' => 'Cooling', 'parent' => 't2421', 'classes' => array(), 'kinds' => array(), 'audience' => '', 'matches' => '' ),
    array( 'key' => 't2421', 'term_id' => 2421, 'name' => 'Data centres', 'parent' => '', 'classes' => array( 'infrastructure', 'networking equipment' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Server rooms and rack interiors' ),
    array( 'key' => 't2422', 'term_id' => 2422, 'name' => 'Energy', 'parent' => '', 'classes' => array( 'renewable energy infrastructure' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Renewable energy infrastructure and equipment' ),
    array( 'key' => 't2423', 'term_id' => 2423, 'name' => 'Hardware', 'parent' => '', 'classes' => array( 'computer hardware', 'computer' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Computer hardware, desktops and internal builds' ),
    array( 'key' => 't2431', 'term_id' => 2431, 'name' => 'Interviews', 'parent' => 't2424', 'classes' => array(), 'kinds' => array(), 'audience' => '', 'matches' => '' ),
    array( 'key' => 't2432', 'term_id' => 2432, 'name' => 'Laptops', 'parent' => 't2423', 'classes' => array( 'laptop', 'computer hardware' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Laptop computers, open and closed' ),
    array( 'key' => 't2433', 'term_id' => 2433, 'name' => 'Launches', 'parent' => 't2426', 'classes' => array(), 'kinds' => array(), 'audience' => '', 'matches' => '' ),
    array( 'key' => 't2424', 'term_id' => 2424, 'name' => 'People', 'parent' => '', 'classes' => array( 'people', 'event' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'People at events, talks and interviews' ),
    array( 'key' => 't2434', 'term_id' => 2434, 'name' => 'Phones', 'parent' => 't2423', 'classes' => array( 'mobile device' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Mobile phones and smartphones' ),
    array( 'key' => 't2425', 'term_id' => 2425, 'name' => 'Robotics', 'parent' => '', 'classes' => array( 'robotics' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Humanoid and industrial robots' ),
    array( 'key' => 't2435', 'term_id' => 2435, 'name' => 'Satellites', 'parent' => 't2426', 'classes' => array( 'infrastructure' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Satellites and orbital imagery' ),
    array( 'key' => 't2436', 'term_id' => 2436, 'name' => 'September', 'parent' => 't2420', 'classes' => array(), 'kinds' => array(), 'audience' => '', 'matches' => '' ),
    array( 'key' => 't2437', 'term_id' => 2437, 'name' => 'Server racks', 'parent' => 't2421', 'classes' => array( 'infrastructure', 'networking equipment' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Rack-mounted servers and cabling' ),
    array( 'key' => 't2438', 'term_id' => 2438, 'name' => 'Solar', 'parent' => 't2422', 'classes' => array( 'renewable energy equipment' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Solar panels and installations' ),
    array( 'key' => 't2426', 'term_id' => 2426, 'name' => 'Space', 'parent' => '', 'classes' => array( 'infrastructure' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Satellites, launches and spacecraft' ),
    array( 'key' => 't2439', 'term_id' => 2439, 'name' => 'Wind', 'parent' => 't2422', 'classes' => array( 'renewable energy equipment' ), 'kinds' => array( 'photo' ), 'audience' => '', 'matches' => 'Wind turbines' ),
) );

// A fixture folder whose term is gone on this box is made by the Move instead of renamed: the walk still runs, and says so.
$fw_missing = 0;
foreach ( $fw_fixture['folders'] as &$fw_f ) {
    if ( ! ( get_term( (int) $fw_f['term_id'], $fw_tax ) instanceof WP_Term ) ) {
        $fw_f['term_id'] = null;
        $fw_missing++;
    }
}
unset( $fw_f );

/* ------------------------------------------------------------ the snapshot */

echo "\nA  before\n\n";

$fw_described = array_map( 'intval', (array) $wpdb->get_col( "SELECT attachment_id FROM {$fw_index} WHERE error = '' AND embedding IS NOT NULL" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/** Every described picture's folders, as attachment => sorted term ids. One query. */
function fw_memberships( $taxonomy ) {
    global $wpdb;
    $out = array();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    foreach ( (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr
           JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
          WHERE tt.taxonomy = %s AND tr.object_id IN ( SELECT attachment_id FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL )",
        $taxonomy
    ), ARRAY_A ) as $r ) {
        $out[ (int) $r['object_id'] ][] = (int) $r['term_id'];
    }
    foreach ( $out as &$ids ) {
        sort( $ids );
    }
    unset( $ids );
    ksort( $out );
    return $out;
}

/** Every folder: id => name, parent, slug, profile meta, locked meta. */
function fw_terms( $taxonomy ) {
    $out   = array();
    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
    foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
        $out[ (int) $t->term_id ] = array(
            'name'    => (string) $t->name,
            'parent'  => (int) $t->parent,
            'slug'    => (string) $t->slug,
            'profile' => get_term_meta( (int) $t->term_id, VERGEML_FILING_META, true ),
            'locked'  => get_term_meta( (int) $t->term_id, VERGEML_FILING_LOCKED, true ),
        );
    }
    ksort( $out );
    return $out;
}

/** Every placed-by mark: attachment => value. */
function fw_placed() {
    global $wpdb;
    $out = array();
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", VERGEML_FILING_PLACED_BY ), ARRAY_A ) as $r ) {
        $out[ (int) $r['post_id'] ] = (string) $r['meta_value'];
    }
    ksort( $out );
    return $out;
}

$fw_snap = array(
    'files'   => fw_memberships( $fw_tax ),
    'terms'   => fw_terms( $fw_tax ),
    'placed'  => fw_placed(),
    'session' => get_option( VERGEML_GUIDE_OPTION ),
    'state'   => get_option( VERGEML_TALK_STATE ),
    'undo'    => get_option( VERGEML_TALK_UNDO ),
    'move_id' => (int) $wpdb->get_var( "SELECT COALESCE(MAX(move_id), 0) FROM {$fw_moves}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    'moves'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$fw_moves}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    'batches' => array(),
);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
foreach ( (array) $wpdb->get_results( "SELECT batch_id, params FROM {$fw_bat}", ARRAY_A ) as $fw_b ) {
    $fw_snap['batches'][ (int) $fw_b['batch_id'] ] = (string) $fw_b['params'];
}

$fw_unfiled_before = vergeml_talk_fill_status()['unfiled'];
printf( "  %d described, %d folders, %d in no folder, %d placed by hand, %d moves rows; fixture: %d folders, %d not on this box\n",
    count( $fw_described ), count( $fw_snap['terms'] ), $fw_unfiled_before, count( $fw_snap['placed'] ), $fw_snap['moves'], count( $fw_fixture['folders'] ), $fw_missing );

/* ------------------------------------------------ B  undo any Move still open */

echo "\nB  a Move still open is undone first\n\n";

$fw_open = vergeml_talk_undo_available();
if ( ! empty( $fw_open['available'] ) ) {
    $fw_u = vergeml_talk_undo();
    fw_check( 'B1 the open Move is undone', ! is_wp_error( $fw_u ), is_wp_error( $fw_u ) ? $fw_u->get_error_message() : sprintf( '%d put back, %d folders unmade', (int) $fw_u['restored'], (int) $fw_u['unmade'] ) );
    // The library the walk starts from is the one after that undo; the snapshot is retaken so the end compares with it.
    $fw_snap['files']  = fw_memberships( $fw_tax );
    $fw_snap['terms']  = fw_terms( $fw_tax );
    $fw_snap['placed'] = fw_placed();
    $fw_snap['undo']   = false;
    $fw_unfiled_before = vergeml_talk_fill_status()['unfiled'];
    printf( "  now %d folders, %d in no folder\n", count( $fw_snap['terms'] ), $fw_unfiled_before );
} else {
    echo "  no Move is open to undo (the record is gone or past its day); the walk starts from the library as it is\n";
}

/* ---------------------------------------------------------------- C  confirm */

echo "\nC  the tree, confirmed\n\n";

// No request leaves for wp-cron: the passes are driven here, one runner, so no slice is looked at twice.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
    return false !== strpos( (string) $url, 'wp-cron.php' ) ? new WP_Error( 'fw_no_cron', 'the walk drives the passes itself' ) : $pre;
}, 1, 3 );
// The preview over the whole library, cold or warm: not the screen's twenty seconds.
add_filter( 'vergeml_guide_fit_budget', function () { return 300; } );

$fw_s          = vergeml_guide_fresh();
$fw_s['draft'] = vergeml_guide_clean_draft( $fw_fixture );
vergeml_guide_save( $fw_s );

$fw_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/confirm' );
$fw_res = rest_do_request( $fw_req );
$fw_out = $fw_res->get_data();
fw_check( 'C1 /guide/confirm answers 200, the tree is confirmed', 200 === $fw_res->get_status() && 'confirmed' === ( isset( $fw_out['session']['tree'] ) ? $fw_out['session']['tree'] : '' ), $fw_res->get_status() . ( isset( $fw_out['message'] ) ? ' ' . $fw_out['message'] : '' ) );
printf( "  the planner described %d folder(s) the fixture gave no classes for (metered, no credit); %.1fs\n", isset( $fw_out['profiled'] ) ? (int) $fw_out['profiled'] : 0, microtime( true ) - $fw_t0 );

$fw_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/rule' );
$fw_req->set_body_params( array( 'rule' => 'kind', 'options' => array() ) );
$fw_res = rest_do_request( $fw_req );
fw_check( 'C2 a rule is refused with 409 while confirmed', 409 === $fw_res->get_status(), (string) $fw_res->get_status() );

/* ---------------------------------------------------------------- D  preview */

echo "\nD  the preview, over the confirmed tree\n\n";

$fw_s   = vergeml_guide_session();
$fw_t1  = microtime( true );
$fw_fit = vergeml_guide_draft_fit( $fw_s['draft'], $fw_tax );
fw_check( 'D1 the preview counted the whole library', is_array( $fw_fit ) && ! empty( $fw_fit['counted'] ) && count( $fw_described ) === (int) $fw_fit['looked'], is_array( $fw_fit ) ? (int) $fw_fit['looked'] . ' looked, ' . round( microtime( true ) - $fw_t1, 1 ) . 's' : 'null (over budget, or no profile)' );
if ( is_array( $fw_fit ) && ! empty( $fw_fit['tally'] ) ) {
    echo '  preview: ' . fw_tally_line( $fw_fit['tally'] ) . "\n";
} else {
    $fw_fit = array( 'tally' => vergeml_filing_tally_fresh() ); // The rows below then say how far off "nothing" is.
}

/* ------------------------------------------------------------------- E  fill */

echo "\nE  the fill\n\n";

$fw_req = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/guide/apply' );
$fw_res = rest_do_request( $fw_req );
$fw_out = $fw_res->get_data();
fw_check( 'E1 /guide/apply answers 200 and the run is on', 200 === $fw_res->get_status() && ! empty( $fw_out['report']['running'] ), $fw_res->get_status() . ( isset( $fw_out['message'] ) ? ' ' . $fw_out['message'] : '' ) );
wp_clear_scheduled_hook( VERGEML_TALK_HOOK );

$fw_t2     = microtime( true );
$fw_passes = 0;
$fw_state  = get_option( VERGEML_TALK_STATE );
while ( is_array( $fw_state ) && ! empty( $fw_state['active'] ) && $fw_passes < 200 ) {
    $fw_state = vergeml_talk_refile_run( microtime( true ) + 60.0 );
    $fw_passes++;
    printf( "  pass %d: %d seen, %d moved\n", $fw_passes, (int) $fw_state['seen'], (int) $fw_state['moved'] );
}
$fw_report = vergeml_talk_report( $fw_state );
fw_check( 'E2 the run ended and looked at every described picture', empty( $fw_state['active'] ) && count( $fw_described ) === (int) $fw_report['seen'], sprintf( '%d seen in %d passes, %.1fs', (int) $fw_report['seen'], $fw_passes, microtime( true ) - $fw_t2 ) );
echo '  run:     ' . fw_tally_line( $fw_report['tally'] ) . "\n";

$fw_same = array( 'looked', 'fits', 'siblings', 'nothing', 'kept', 'sure', 'likely' );
$fw_diff = array();
foreach ( $fw_same as $fw_k ) {
    if ( (int) $fw_fit['tally'][ $fw_k ] !== (int) $fw_report['tally'][ $fw_k ] ) {
        $fw_diff[] = $fw_k . ' ' . (int) $fw_fit['tally'][ $fw_k ] . '/' . (int) $fw_report['tally'][ $fw_k ];
    }
}
foreach ( array( 'floor', 'margin', 'gated' ) as $fw_k ) {
    if ( (int) $fw_fit['tally']['why'][ $fw_k ] !== (int) $fw_report['tally']['why'][ $fw_k ] ) {
        $fw_diff[] = $fw_k . ' ' . (int) $fw_fit['tally']['why'][ $fw_k ] . '/' . (int) $fw_report['tally']['why'][ $fw_k ];
    }
}
$fw_pv = array_values( (array) $fw_fit['tally']['by_term'] );
$fw_rv = array_values( (array) $fw_report['tally']['by_term'] );
sort( $fw_pv );
sort( $fw_rv );
fw_check( 'E3 preview tally = run tally, per outcome and per folder', ! $fw_diff && $fw_pv === $fw_rv, $fw_diff ? implode( ', ', $fw_diff ) : ( $fw_pv === $fw_rv ? 'identical' : 'per-folder counts differ' ) );
$fw_sum = (int) $fw_report['tally']['fits'] + (int) $fw_report['tally']['siblings'] + (int) $fw_report['tally']['nothing'] + (int) $fw_report['tally']['kept'];
fw_check( 'E4 the outcomes sum to looked', $fw_sum === (int) $fw_report['tally']['looked'], $fw_sum . ' vs ' . (int) $fw_report['tally']['looked'] );

/* ------------------------------------------------------------ F  the questions */

echo "\nF  the questions, answered by script\n\n";

$fw_questions = vergeml_talk_questions();
$fw_sib       = 0;
$fw_res_q     = 0;
foreach ( $fw_questions as $fw_q ) {
    if ( 'siblings' === $fw_q['kind'] ) {
        $fw_sib++;
    } else {
        $fw_res_q++;
    }
}
printf( "  %d questions: %d sibling, %d residue\n", count( $fw_questions ), $fw_sib, $fw_res_q );
fw_check( 'F1 at least one sibling question (two children of one folder tied)', $fw_sib >= 1, $fw_sib . ' sibling questions; siblings in the tally ' . (int) $fw_report['tally']['siblings'] );
fw_check( 'F2 the residue is asked about in groups, not per picture', $fw_res_q >= 1 && $fw_res_q < (int) $fw_report['tally']['nothing'], $fw_res_q . ' questions for ' . (int) $fw_report['tally']['nothing'] . ' pictures' );

$fw_left     = array(); // What "leave" leaves: To sort must hold exactly these.
$fw_answered = 0;
$fw_made     = array();
foreach ( $fw_questions as $fw_q ) {
    if ( 'siblings' === $fw_q['kind'] ) {
        $fw_a = 'keep-parent';
    } elseif ( empty( $fw_q['name'] ) || (int) $fw_q['count'] < 20 ) {
        $fw_a = 'leave';
    } else {
        $fw_a = 'new-folder';
    }
    $fw_r = vergeml_talk_answer( $fw_q['id'], $fw_a );
    if ( is_wp_error( $fw_r ) ) {
        printf( "  %-6s %-48s %-11s FAILED %s\n", $fw_q['id'], mb_substr( $fw_q['text'], 0, 48 ), $fw_a, $fw_r->get_error_message() );
        continue;
    }
    $fw_answered++;
    if ( 'leave' === $fw_a ) {
        // The question's ids are not in the screen's shape; read them off the state.
        foreach ( (array) get_option( VERGEML_TALK_STATE )['questions'] as $fw_sq ) {
            if ( $fw_sq['id'] === $fw_q['id'] ) {
                $fw_left = array_merge( $fw_left, array_map( 'intval', (array) $fw_sq['ids'] ) );
            }
        }
    }
    if ( ! empty( $fw_r['made'] ) && ! isset( $fw_snap['terms'][ (int) $fw_r['made'] ] ) ) {
        $fw_made[] = (int) $fw_r['made']; // Made by this answer; a name that already existed is reused, not made.
    }
    printf( "  %-6s %-48s %-11s %d moved%s\n", $fw_q['id'], mb_substr( $fw_q['text'], 0, 48 ), $fw_a, (int) $fw_r['moved'], ! empty( $fw_r['made'] ) ? ' -> ' . get_term( (int) $fw_r['made'], $fw_tax )->name : '' );
}
fw_check( 'F3 every question answered', count( $fw_questions ) === $fw_answered, $fw_answered . ' of ' . count( $fw_questions ) );

$fw_status = vergeml_talk_fill_status();
fw_check( 'F4 0 in no folder, 0 open questions: the step is done', 0 === (int) $fw_status['unfiled'] && 0 === (int) $fw_status['open'] && ! empty( $fw_status['done'] ), sprintf( '%d in no folder, %d open', (int) $fw_status['unfiled'], (int) $fw_status['open'] ) );

$fw_to_sort = get_term_by( 'slug', VERGEML_FILING_TO_SORT_SLUG, $fw_tax );
$fw_in_sort = $fw_to_sort instanceof WP_Term ? array_map( 'intval', (array) get_objects_in_term( (int) $fw_to_sort->term_id, $fw_tax ) ) : array();
sort( $fw_in_sort );
$fw_left = array_values( array_unique( $fw_left ) );
sort( $fw_left );
fw_check( 'F5 To sort holds exactly what "leave" left, and is locked', $fw_left === $fw_in_sort && $fw_to_sort instanceof WP_Term && get_term_meta( (int) $fw_to_sort->term_id, VERGEML_FILING_LOCKED, true ), count( $fw_in_sort ) . ' in To sort, ' . count( $fw_left ) . ' left by answers' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$fw_trail = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT attachment_id FROM {$fw_moves} WHERE move_id > %d", $fw_snap['move_id'] ) ) );
$fw_judged = count( $fw_described ) - (int) $fw_report['tally']['kept'];
fw_check( 'F6 a trail row per picture the fill judged', $fw_judged === count( array_intersect( $fw_trail, $fw_described ) ), count( $fw_trail ) . ' pictures with rows, ' . $fw_judged . ' judged (' . (int) $fw_report['tally']['kept'] . ' kept without a row)' );
printf( "  fill + answers: %.1fs\n", microtime( true ) - $fw_t2 );

/* ------------------------------------------------------------------- G  undo */

echo "\nG  undo, and the library as it was\n\n";

$fw_u = vergeml_talk_undo();
fw_check( 'G1 undo puts the pictures back', ! is_wp_error( $fw_u ), is_wp_error( $fw_u ) ? $fw_u->get_error_message() : sprintf( '%d put back, %d folders unmade', (int) $fw_u['restored'], (int) $fw_u['unmade'] ) );

$fw_after = fw_memberships( $fw_tax );
$fw_moved = 0;
foreach ( $fw_described as $fw_id ) {
    $fw_was = isset( $fw_snap['files'][ $fw_id ] ) ? $fw_snap['files'][ $fw_id ] : array();
    $fw_now = isset( $fw_after[ $fw_id ] ) ? $fw_after[ $fw_id ] : array();
    if ( $fw_was !== $fw_now ) {
        $fw_moved++;
    }
}
fw_check( 'G2 after undo every picture is in the folders it was in', 0 === $fw_moved, $fw_moved . ' differ' );
fw_check( 'G3 the folders the answers made are gone', ! array_filter( $fw_made, function ( $tid ) use ( $fw_tax ) { return get_term( $tid, $fw_tax ) instanceof WP_Term; } ), count( $fw_made ) . ' made' );
fw_check( 'G4 no placed-by mark the walk made remains', fw_placed() === $fw_snap['placed'], count( fw_placed() ) . ' now, ' . count( $fw_snap['placed'] ) . ' before' );

/*
 *  The snapshot, applied over the undo. Undo covers the pictures and the
 *  folders the answers made; it does not know To sort, the profiles confirm
 *  stored, the options or the moves rows. Anything G2-G4 found is put right
 *  here too, so a red row above never leaves the library changed.
 */
foreach ( $fw_described as $fw_id ) {
    $fw_was = isset( $fw_snap['files'][ $fw_id ] ) ? $fw_snap['files'][ $fw_id ] : array();
    $fw_now = isset( $fw_after[ $fw_id ] ) ? $fw_after[ $fw_id ] : array();
    if ( $fw_was !== $fw_now ) {
        wp_set_object_terms( $fw_id, $fw_was, $fw_tax, false );
    }
}
foreach ( fw_terms( $fw_tax ) as $fw_tid => $fw_t ) {
    if ( ! isset( $fw_snap['terms'][ $fw_tid ] ) ) {
        wp_delete_term( $fw_tid, $fw_tax ); // To sort, or a folder an answer made that undo left (it held something).
    }
}
foreach ( $fw_snap['terms'] as $fw_tid => $fw_t ) {
    $fw_live = get_term( $fw_tid, $fw_tax );
    if ( ! ( $fw_live instanceof WP_Term ) ) {
        continue;
    }
    if ( $fw_live->name !== $fw_t['name'] || (int) $fw_live->parent !== $fw_t['parent'] ) {
        wp_update_term( $fw_tid, $fw_tax, array( 'name' => $fw_t['name'], 'parent' => $fw_t['parent'] ) );
    }
    if ( '' === $fw_t['profile'] || false === $fw_t['profile'] ) {
        delete_term_meta( $fw_tid, VERGEML_FILING_META );
    } else {
        update_term_meta( $fw_tid, VERGEML_FILING_META, $fw_t['profile'] );
    }
    if ( '' === $fw_t['locked'] || false === $fw_t['locked'] ) {
        delete_term_meta( $fw_tid, VERGEML_FILING_LOCKED );
    } else {
        update_term_meta( $fw_tid, VERGEML_FILING_LOCKED, $fw_t['locked'] );
    }
}
foreach ( fw_placed() as $fw_id => $fw_v ) {
    if ( ! isset( $fw_snap['placed'][ $fw_id ] ) ) {
        delete_post_meta( $fw_id, VERGEML_FILING_PLACED_BY );
    }
}
foreach ( $fw_snap['placed'] as $fw_id => $fw_v ) {
    update_post_meta( $fw_id, VERGEML_FILING_PLACED_BY, $fw_v );
}
foreach ( array( VERGEML_GUIDE_OPTION => 'session', VERGEML_TALK_STATE => 'state', VERGEML_TALK_UNDO => 'undo' ) as $fw_opt => $fw_k ) {
    if ( false === $fw_snap[ $fw_k ] ) {
        delete_option( $fw_opt );
    } else {
        update_option( $fw_opt, $fw_snap[ $fw_k ], false );
    }
}
// The walk's own rows and the batches it opened; a batch that was here keeps the params it had (undo stamps them).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( $wpdb->prepare( "DELETE FROM {$fw_moves} WHERE move_id > %d", $fw_snap['move_id'] ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
foreach ( (array) $wpdb->get_results( "SELECT batch_id, params FROM {$fw_bat}", ARRAY_A ) as $fw_b ) {
    $fw_bid = (int) $fw_b['batch_id'];
    if ( ! isset( $fw_snap['batches'][ $fw_bid ] ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$fw_moves} WHERE batch_id = %d", $fw_bid ) ) ) {
            $wpdb->delete( $fw_bat, array( 'batch_id' => $fw_bid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }
    } elseif ( $fw_snap['batches'][ $fw_bid ] !== (string) $fw_b['params'] ) {
        $wpdb->update( $fw_bat, array( 'params' => $fw_snap['batches'][ $fw_bid ] ), array( 'batch_id' => $fw_bid ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
if ( function_exists( 'vergeml_folders_moved' ) ) {
    vergeml_folders_moved( 'undo' );
}

$fw_end_terms = fw_terms( $fw_tax );
fw_check( 'G5 the folders are as they were: same ids, names, parents, profiles and locks', $fw_end_terms === $fw_snap['terms'], count( $fw_end_terms ) . ' folders' );
fw_check( 'G6 the memberships are as they were', fw_memberships( $fw_tax ) === $fw_snap['files'] );
fw_check( 'G7 in no folder is what it was', $fw_unfiled_before === vergeml_talk_fill_status()['unfiled'], (string) vergeml_talk_fill_status()['unfiled'] );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
fw_check( 'G8 the moves table holds what it held', $fw_snap['moves'] === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$fw_moves}" ) );
fw_check( 'G9 the session, the fill state and the undo record are as found', get_option( VERGEML_GUIDE_OPTION ) === $fw_snap['session'] && get_option( VERGEML_TALK_STATE ) === $fw_snap['state'] && get_option( VERGEML_TALK_UNDO ) === $fw_snap['undo'] );

printf( "\n%d/%d passed  (%.0fs)\n", $GLOBALS['fw_pass'], $GLOBALS['fw_pass'] + $GLOBALS['fw_fail'], microtime( true ) - $fw_t0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
if ( $GLOBALS['fw_fail'] > 0 ) {
    exit( 1 );
}
