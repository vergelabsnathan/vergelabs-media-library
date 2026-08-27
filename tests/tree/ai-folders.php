<?php
/**
 *  The AI smart folders: the registry, the join, the counts and the budget.
 *
 *  What this proves, in the order it matters:
 *
 *    A  the registry gains thirteen rows, in their fixed order, and loses them
 *       again when the setting is off
 *    B  the translation hands back a marker for an AI key and the five
 *       original keys still return exactly what they returned before
 *    C  the join returns the right files, and does not touch a query that did
 *       not ask for it -- the one that would silently break the whole media
 *       library
 *    D  the counts are right, hidden at zero, and null rather than zero when
 *       the index is not there
 *    E  switching the group on costs the tree endpoint **no** extra queries.
 *       This is the budget assertion. It is a delta between two requests in
 *       one process rather than the real figure -- tests/perf/bench.mjs
 *       measures that over HTTP -- but "did enabling this add a query" is
 *       answerable here and is the regression worth catching early.
 *
 *      wp eval-file tests/tree/ai-folders.php --allow-root
 *
 *  or through tests/tree/ai-folders-blueprint.json in Playground.
 *
 *  It cleans up after itself: every attachment it makes is deleted, every
 *  index row with it, and the setting is put back as it was found.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_ai_folders' ) ) {
    echo "core/ai-folders.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

global $wpdb;

$af_pass = 0;
$af_fail = 0;
$af_log  = '';

function af_say( $line ) {
    global $af_log;
    $af_log .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function af_report() {
    global $af_log;
    @file_put_contents( __DIR__ . '/ai-folders-last-run.txt', $af_log ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}

/**
 *  Not passed and not failed: not measurable here.
 *
 *  Playground's SQLite layer does not move $wpdb->num_queries, so a query
 *  count taken there is zero however many statements ran. Reporting that as a
 *  pass would be a gate that goes green while checking nothing, which this
 *  repo has been bitten by more than once.
 */
function af_skip( $label, $why ) {
    af_say( sprintf( "  skip  %s  -- %s\n", $label, $why ) );
}

function af_check( $label, $ok, $note = '' ) {
    global $af_pass, $af_fail;
    if ( $ok ) {
        $af_pass++;
    } else {
        $af_fail++;
    }
    af_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}


/**
 *  The setting, set for the duration of a check and put back after.
 */
function af_set_group( $on ) {

    $options = get_option( 'vergeml_lib_options', array() );
    $show    = isset( $options['filters_to_show'] ) ? array_values( (array) $options['filters_to_show'] ) : array();

    $show = array_values( array_diff( $show, array( 'ai' ) ) );

    if ( $on ) {
        $show[] = 'ai';
    }

    $options['filters_to_show'] = $show;

    update_option( 'vergeml_lib_options', $options );
}


$af_before  = get_option( 'vergeml_lib_options', array() );
$af_made    = array();

af_say( "\nAI smart folders\n\n" );


/* ------------------------------------------------------------- the seeding */

af_say( "seeding\n" );

vergeml_index_install();

/*
 *  Nine files with descriptions and one without, so that "described" and
 *  "in the library" are different numbers and the honesty line has something
 *  to say.
 */
$af_seed = array(
    array( 'photo', 1, 0, '' ),
    array( 'photo', 0, 0, '' ),
    array( 'photo', 0, 1, '' ),
    array( 'screenshot', 0, 1, '' ),
    array( 'screenshot', 0, 1, '' ),
    array( 'logo', 0, 1, '' ),
    array( 'document', 0, 1, 'invoice' ),
    array( 'document', 0, 1, 'invoice' ),
    array( 'document', 0, 1, 'contract' ),
);

foreach ( $af_seed as $i => $row ) {

    $id = wp_insert_post( array(
        'post_title'     => 'zz ai-folder ' . $i,
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/png',
    ) );

    $af_made[] = (int) $id;

    vergeml_index_set( (int) $id, array(
        'caption'       => 'seeded',
        'kind'          => $row[0],
        'has_people'    => $row[1],
        'has_text'      => $row[2],
        'document_type' => $row[3],
        'described_at'  => gmdate( 'Y-m-d H:i:s' ),
    ) );
}

// One undescribed file, so described < total.
$af_plain = wp_insert_post( array(
    'post_title'     => 'zz ai-folder plain',
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => 'image/png',
) );

$af_made[] = (int) $af_plain;

af_check( 'ten attachments seeded, nine of them described', 10 === count( $af_made ) );


/* --------------------------------------------------------- A: the registry */

af_say( "\nA  the registry\n" );

af_set_group( true );

$af_folders = vergeml_smart_folders();
$af_keys    = array_keys( $af_folders );

af_check( 'eighteen folders with the group on', 18 === count( $af_keys ), count( $af_keys ) . ' found' );

af_check( 'the five originals still come first and in order',
    array( 'unused', 'no-alt', 'large', 'unattached', 'recent' ) === array_slice( $af_keys, 0, 5 ) );

af_check( 'the AI thirteen are in their fixed order',
    array(
        'ai-kind-photo', 'ai-kind-illustration', 'ai-kind-screenshot',
        'ai-kind-document', 'ai-kind-diagram', 'ai-kind-logo',
        'ai-people', 'ai-text',
        'ai-doc-invoice', 'ai-doc-receipt', 'ai-doc-contract',
        'ai-doc-form', 'ai-doc-report',
    ) === array_slice( $af_keys, 5 ) );

af_check( 'every AI folder is in the ai group',
    'ai' === $af_folders['ai-kind-photo']['group'] && 'clean' === $af_folders['unused']['group'] );

af_set_group( false );

af_check( 'five again with the group off', 5 === count( vergeml_smart_folders() ) );

af_set_group( true );


/* ------------------------------------------------------ B: the translation */

af_say( "\nB  the translation\n" );

af_check( 'an AI key returns the marker',
    array( 'vergeml_ai_filter' => 'ai-kind-screenshot' ) === vergeml_smart_query_args( 'ai-kind-screenshot' ) );

$af_unattached = vergeml_smart_query_args( 'unattached' );

af_check( 'the five originals are untouched',
    array( 'post_parent' => 0 ) === $af_unattached );

af_check( 'an unregistered key still returns nothing',
    array() === vergeml_smart_query_args( 'ai-kind-nonsense' ) );


/* -------------------------------------------------------------- C: the join */

af_say( "\nC  the join\n" );

$af_q = new WP_Query( array(
    'post_type'        => 'attachment',
    'post_status'      => 'inherit',
    'posts_per_page'   => 50,
    'fields'           => 'ids',
    'vergeml_ai_filter' => 'ai-kind-screenshot',
) );

af_check( 'the screenshot folder finds exactly its two files',
    2 === (int) $af_q->found_posts, $af_q->found_posts . ' found' );

$af_docs = new WP_Query( array(
    'post_type'        => 'attachment',
    'post_status'      => 'inherit',
    'posts_per_page'   => 50,
    'fields'           => 'ids',
    'vergeml_ai_filter' => 'ai-doc-invoice',
) );

af_check( 'the invoice folder finds exactly its two files',
    2 === (int) $af_docs->found_posts, $af_docs->found_posts . ' found' );

$af_people = new WP_Query( array(
    'post_type'        => 'attachment',
    'post_status'      => 'inherit',
    'posts_per_page'   => 50,
    'fields'           => 'ids',
    'vergeml_ai_filter' => 'ai-people',
) );

af_check( 'the people folder finds its one file', 1 === (int) $af_people->found_posts );

/*
 *  The one that matters most. A posts_clauses filter that forgets to check
 *  which query it is looking at joins the index onto every attachment query
 *  on the site, and the media library shows only described files from then on
 *  -- a failure nobody would connect to this feature.
 */
$af_all = new WP_Query( array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'posts_per_page' => 100,
    'fields'         => 'ids',
) );

af_check( 'a query that did not ask is not joined',
    (int) $af_all->found_posts >= 10, $af_all->found_posts . ' found, expected every attachment' );

$af_hand = new WP_Query( array(
    'post_type'        => 'attachment',
    'post_status'      => 'inherit',
    'posts_per_page'   => 50,
    'fields'           => 'ids',
    'vergeml_ai_filter' => 'ai-kind-nonsense',
) );

af_check( 'a hand-typed key that nobody registered filters nothing',
    (int) $af_hand->found_posts === (int) $af_all->found_posts );


/* ------------------------------------------------------------ D: the counts */

af_say( "\nD  the counts\n" );

$af_counts = vergeml_smart_counts( true );

af_check( 'photos', 3 === $af_counts['ai-kind-photo'], var_export( $af_counts['ai-kind-photo'], true ) );
af_check( 'screenshots', 2 === $af_counts['ai-kind-screenshot'] );
af_check( 'logos', 1 === $af_counts['ai-kind-logo'] );
af_check( 'invoices', 2 === $af_counts['ai-doc-invoice'] );
af_check( 'contracts', 1 === $af_counts['ai-doc-contract'] );
af_check( 'people', 1 === $af_counts['ai-people'] );
// Seven of the nine described rows carry has_text: everything except the two
// photos seeded without it.
af_check( 'text in the picture', 7 === $af_counts['ai-text'], var_export( $af_counts['ai-text'], true ) );

af_check( 'a kind nothing was seeded with counts zero, not null',
    0 === $af_counts['ai-kind-diagram'], var_export( $af_counts['ai-kind-diagram'], true ) );

af_check( 'described is nine of at least ten',
    9 === (int) $af_counts['_described'] && (int) $af_counts['_total'] >= 10,
    $af_counts['_described'] . '/' . $af_counts['_total'] );

$af_rows = vergeml_smart_for_tree( '' );
$af_shown = array();
foreach ( $af_rows as $row ) {
    $af_shown[] = $row['key'];
}

af_check( 'the empty AI folders are not in the payload',
    ! in_array( 'ai-kind-diagram', $af_shown, true ) && in_array( 'ai-kind-photo', $af_shown, true ) );

af_check( 'the five originals are in the payload whatever their count',
    in_array( 'unattached', $af_shown, true ) );

$af_ai = vergeml_ai_for_tree( '' );

af_check( 'the group reports itself as partly described',
    is_array( $af_ai ) && false === $af_ai['ladder'] && 9 === (int) $af_ai['described'] );


/* ------------------------------------------------- D2: the index taken away */

af_say( "\nD2 the index dropped by hand\n" );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( 'DROP TABLE IF EXISTS ' . vergeml_index_table() );

$af_gone = vergeml_smart_counts( true );

af_check( 'the AI folders report null, not zero', null === $af_gone['ai-kind-photo'] );

af_check( 'the five originals still have their numbers',
    null !== $af_gone['unattached'] && null !== $af_gone['recent'],
    'a broken index must not cost the tree its own counts' );

vergeml_index_install();


/* ------------------------------------------------------------- E: the budget */

af_say( "\nE  the query budget\n" );

$af_taxonomies = vergeml_tree_taxonomies();
$af_taxonomy   = $af_taxonomies ? $af_taxonomies[0] : '';

$af_tree = function () use ( $af_taxonomy ) {
    global $wpdb;
    $before  = $wpdb->num_queries;
    $request = new WP_REST_Request( 'GET', '/' . VERGEML_REST_NS . '/tree' );
    $request->set_param( 'taxonomy', $af_taxonomy );
    $response = rest_do_request( $request );
    return array(
        'queries' => $wpdb->num_queries - $before,
        'status'  => (int) $response->get_status(),
    );
};

// Warm first: the first request of a process pays for things that have
// nothing to do with this feature.
$af_tree();

af_set_group( false );
$af_off = $af_tree();

af_set_group( true );
$af_on = $af_tree();

/*
 *  Checked before the budget is compared, because without it this passes for
 *  the wrong reason. A request that 403s or 404s runs no queries at all, both
 *  sides read zero, "no extra query" is true, and the assertion has proved
 *  nothing. That is exactly the shape of gate that has gone green while
 *  checking nothing in this repo before.
 */
af_check( 'the tree endpoint actually answered',
    200 === $af_off['status'] && 200 === $af_on['status'],
    'taxonomy "' . $af_taxonomy . '", status ' . $af_off['status'] . '/' . $af_on['status'] );

if ( $af_off['queries'] < 1 ) {

    af_skip( 'switching the AI group on costs no extra query',
        'the query counter did not move at all, so nothing here can be measured -- '
        . 'Playground\'s SQLite layer does not maintain $wpdb->num_queries. '
        . 'Run this suite on the box, and tests/perf/bench.mjs for the real figure' );

} else {

    af_check( 'switching the AI group on costs no extra query',
        $af_on['queries'] <= $af_off['queries'],
        'off ' . $af_off['queries'] . ', on ' . $af_on['queries'] );

    af_say( sprintf(
        "  (in-process delta; bench.mjs measures the real figure over HTTP: off %d, on %d)\n",
        $af_off['queries'],
        $af_on['queries']
    ) );
}


/* -------------------------------------------------------------------- tidy */

af_say( "\ntidying up\n" );

foreach ( $af_made as $id ) {
    if ( get_post( $id ) ) {
        vergeml_index_delete( $id );
        wp_delete_post( $id, true );
    }
}

if ( is_array( $af_before ) && $af_before ) {
    update_option( 'vergeml_lib_options', $af_before );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$af_left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE 'zz ai-folder%' AND post_type = 'attachment'" );

af_check( 'the seeded attachments are gone', 0 === $af_left, $af_left . ' left behind' );

af_say( sprintf( "\n%d/%d passed\n", $af_pass, $af_pass + $af_fail ) );

af_report();

if ( $af_fail > 0 ) {
    exit( 1 );
}
