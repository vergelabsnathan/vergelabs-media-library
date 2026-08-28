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
 *    E  the AI counts ride the statement the five original folders already
 *       run, rather than adding one of their own. Proved from the statement
 *       itself rather than from a query counter, because no counter is
 *       portable: real MySQL moves $wpdb->num_queries, Playground's SQLite
 *       layer moves neither that, nor $wpdb->queries under SAVEQUERIES, nor
 *       the `query` filter -- and a budget read off a counter that never
 *       moves reads zero and passes while checking nothing
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

$GLOBALS['af_pass'] = 0;
$GLOBALS['af_fail'] = 0;
$GLOBALS['af_log']  = '';

function af_say( $line ) {
    $GLOBALS['af_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function af_report() {
    @file_put_contents( __DIR__ . '/ai-folders-last-run.txt', $GLOBALS['af_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
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
    /*
     *  $GLOBALS, not `global`. wp eval-file evaluates this file inside a
     *  function, so the counters declared at the top of it are locals of
     *  that function and never globals at all -- `global` here bound to a
     *  second, empty pair. They stayed at zero however many checks ran, the
     *  summary read "0/0 passed", and the exit(1) below could not fire: the
     *  suite reported success no matter what failed. tests/librarian and
     *  tests/organize already do it this way, which is why theirs count.
     */
    if ( $ok ) {
        $GLOBALS['af_pass']++;
    } else {
        $GLOBALS['af_fail']++;
    }
    af_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}


/*
 *  Somebody allowed to read the tree.
 *
 *  Section E calls the endpoint through rest_do_request(), whose permission
 *  callback wants manage_categories. tests/tree/ai-folders-blueprint.json sets
 *  a user before requiring this file, so the check passed in Playground -- but
 *  tools/verify.mjs runs it through `wp eval-file` on the box, where there is no
 *  current user at all and the endpoint answered 401. A suite that only works
 *  under one of its two runners is a suite that will be believed by the wrong
 *  one, so it sets its own user, the way tests/librarian/gate7-schema.php does.
 */
wp_set_current_user( 1 );

if ( ! current_user_can( 'manage_categories' ) ) {
    $af_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
    if ( $af_admins ) {
        wp_set_current_user( (int) $af_admins[0] );
    }
}

af_check( 'running as somebody allowed to read the tree', current_user_can( 'manage_categories' ) );


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

/*
 *  The five originals and the AI thirteen, by name rather than by counting
 *  rows. Eighteen was right when they were all there was; core/quarantine.php
 *  has since registered "Set aside" through the same filter, so the total is
 *  nineteen and a bare count made that addition a failure of this file. What
 *  matters here is that the thirteen arrive and the five survive them.
 */
$af_core_five = array( 'unused', 'no-alt', 'large', 'unattached', 'recent' );

$af_expected_ai = array(
    'ai-kind-photo', 'ai-kind-illustration', 'ai-kind-screenshot',
    'ai-kind-document', 'ai-kind-diagram', 'ai-kind-logo',
    'ai-people', 'ai-text',
    'ai-doc-invoice', 'ai-doc-receipt', 'ai-doc-contract',
    'ai-doc-form', 'ai-doc-report',
);

$af_want = array_merge( $af_core_five, $af_expected_ai );

$af_absent = array_diff( $af_want, $af_keys );

af_check( 'the five originals and the AI thirteen are all registered',
    array() === $af_absent,
    array() === $af_absent ? count( $af_keys ) . ' registered in total' : 'missing: ' . implode( ',', $af_absent ) );

af_check( 'the five originals still come first and in order',
    array( 'unused', 'no-alt', 'large', 'unattached', 'recent' ) === array_slice( $af_keys, 0, 5 ) );

/*
 *  Their order relative to each other, not their position in the whole list:
 *  anything else registering through the filter sits in here too, and where it
 *  lands is not this file's business.
 */
af_check( 'the AI thirteen are in their fixed order',
    $af_expected_ai === array_values( array_intersect( $af_keys, $af_expected_ai ) ),
    implode( ',', array_values( array_intersect( $af_keys, $af_expected_ai ) ) ) );

af_check( 'every AI folder is in the ai group',
    'ai' === $af_folders['ai-kind-photo']['group'] && 'clean' === $af_folders['unused']['group'] );

af_set_group( false );

/*
 *  Off means none of the thirteen, not a list of exactly five -- "Set aside"
 *  is registered whatever this setting says, and rightly.
 */
$af_off_keys = array_keys( vergeml_smart_folders() );

af_check( 'none of the thirteen with the group off',
    array() === array_intersect( $af_off_keys, $af_expected_ai ),
    implode( ',', $af_off_keys ) );

af_check( 'and the five originals are still there with it off',
    array() === array_diff( $af_core_five, $af_off_keys ) );

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

/*
 *  Put back, and refilled. Dropping the table took the descriptions with it,
 *  so anything after this would be measuring an empty index and reporting it
 *  as a failure of the thing it was actually testing.
 */
vergeml_index_install();

foreach ( $af_seed as $i => $row ) {
    vergeml_index_set( $af_made[ $i ], array(
        'caption'       => 'seeded',
        'kind'          => $row[0],
        'has_people'    => $row[1],
        'has_text'      => $row[2],
        'document_type' => $row[3],
        'described_at'  => gmdate( 'Y-m-d H:i:s' ),
    ) );
}

af_check( 'the seed is back after the drop',
    9 === (int) vergeml_smart_counts( true )['_described'] );


/* ------------------------------------------------------------- E: the budget */

$af_taxonomies = vergeml_tree_taxonomies();
$af_taxonomy   = $af_taxonomies ? $af_taxonomies[0] : '';

af_say( "
E  the budget
" );

/*
 *  Not counted -- proved.
 *
 *  Counting statements needs a counter, and there isn't a portable one:
 *  real MySQL moves $wpdb->num_queries, Playground's SQLite layer moves
 *  neither that nor $wpdb->queries under SAVEQUERIES nor the `query` filter,
 *  and a budget read off a counter that never moves reads zero and passes
 *  while proving nothing.
 *
 *  But the claim is not "few queries". The claim is that the AI counts ride
 *  the statement the five original folders already run, instead of adding one
 *  of their own -- and that is visible in the statement itself. If both
 *  halves are in one string, there is one statement. That holds wherever this
 *  runs, needs no counter, and is a sharper assertion than a number: a number
 *  can be right for the wrong reason.
 */

af_set_group( false );
vergeml_smart_counts( true );
$af_sql_off = (string) $wpdb->last_query;

af_set_group( true );
vergeml_smart_counts( true );
$af_sql_on = (string) $wpdb->last_query;

af_check( 'the counts are one statement with the group off',
    false !== strpos( $af_sql_off, "'unused'" ) && false === strpos( $af_sql_off, 'ai-kind-' ) );

af_check( 'and still one statement with the group on -- both halves in it',
    false !== strpos( $af_sql_on, "'unused'" ) && false !== strpos( $af_sql_on, 'ai-kind-' ),
    'the AI counts did not join the existing UNION' );

af_check( 'the AI half is a UNION branch, not a second statement',
    substr_count( strtolower( $af_sql_on ), 'union all' ) > substr_count( strtolower( $af_sql_off ), 'union all' ) );

/*
 *  The other half of the promise: the statement must not grow with the
 *  library. Six kinds is six kinds whether there are nine files or nine
 *  million, because the branches group rather than listing.
 */
af_check( 'the statement does not name a single attachment id',
    ! preg_match( '/IN\s*\(\s*\d+\s*,/', $af_sql_on ),
    'an id list here would grow with the library' );

/*
 *  And the endpoint still answers, which the SQL alone would not tell us.
 */
$af_request = new WP_REST_Request( 'GET', '/' . VERGEML_REST_NS . '/tree' );
$af_request->set_param( 'taxonomy', $af_taxonomy );
$af_response = rest_do_request( $af_request );

af_check( 'the tree endpoint answers with the group on',
    200 === (int) $af_response->get_status(),
    'taxonomy "' . $af_taxonomy . '", status ' . $af_response->get_status() );

$af_data = $af_response->get_data();

af_check( 'and its payload carries the AI group',
    is_array( $af_data ) && ! empty( $af_data['ai'] ) && 9 === (int) $af_data['ai']['described'] );


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

af_say( sprintf( "\n%d/%d passed\n", $GLOBALS['af_pass'], $GLOBALS['af_pass'] + $GLOBALS['af_fail'] ) );

af_report();

if ( $GLOBALS['af_fail'] > 0 ) {
    exit( 1 );
}
