<?php
/**
 *  Gate 7, the part the phase-3 report left open: the schema is put back
 *  after the tables themselves are gone.
 *
 *  The suite in test-librarian.php already proves the option half of this --
 *  delete `vergeml_librarian` and the lazy check reinstalls. That is the
 *  "upgraded without ever visiting wp-admin" case. It is not the whole risk.
 *
 *  The other half is the case ai-index.php guards against explicitly: a table
 *  dropped by hand, by a host's migration, by a botched restore. There the
 *  option still says the schema is current, so anything that trusts the
 *  option alone will not notice, and the first Apply writes into a table that
 *  is not there.
 *
 *  Four checks, in the order they matter:
 *
 *    A  baseline -- both tables exist after activation
 *    B  tables dropped, option intact  -> maybe_install must put them back
 *    C  tables dropped, option deleted -> maybe_install must put them back
 *    D  tables dropped, through the real REST route that creates a batch
 *
 *  Run it the way the other PHP suites run:
 *
 *      wp eval-file tests/librarian/gate7-schema.php --allow-root
 *
 *  or, with no box to hand, through the Playground wrapper blueprint in
 *  tests/librarian/gate7-blueprint.json.
 *
 *  It leaves the schema installed and the option as it found it.
 *
 *  **And it leaves the site's own records alone.** Everything below runs
 *  against tables made for this run: the real two are renamed aside before the
 *  first drop and renamed back at the end, with their row counts checked, and
 *  any folder section D's apply created is taken off the tree again. Before
 *  2026-09-09 it did not do this, and one run of it on the box destroyed 109
 *  move rows and two batches with no way back.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_librarian_install' ) ) {
    echo "core/librarian.php is not loaded -- is the plugin active, or is safe mode on?\n";
    exit( 1 );
}

global $wpdb;

$GLOBALS['g7_pass'] = 0;
$GLOBALS['g7_fail'] = 0;

/*
 *  Playground's run-blueprint only shows a step's output when the step fails,
 *  so a passing run prints nothing at all. Everything said here is kept as
 *  well and written beside this file at the end, where the host can read it
 *  either way. An output buffer would not do: WordPress flushes its own on
 *  shutdown before anything registered later gets a look at it.
 */
$GLOBALS['g7_log'] = '';

function g7_say( $line ) {
    $GLOBALS['g7_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}


function g7_report() {
    @file_put_contents( __DIR__ . '/gate7-last-run.txt', $GLOBALS['g7_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}


function g7_check( $label, $ok, $note = '' ) {
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
        $GLOBALS['g7_pass']++;
    } else {
        $GLOBALS['g7_fail']++;
    }

    g7_say( sprintf(
        "  %s  %s%s\n",
        $ok ? 'ok  ' : 'FAIL',
        $label,
        '' === $note ? '' : '  -- ' . $note
    ) );
}


/**
 *  Asked of the database, never of the option -- the option is the thing on
 *  trial here.
 */
function g7_table_exists( $table ) {

    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
}


function g7_drop_both() {

    global $wpdb;

    $batches = vergeml_librarian_batches_table();
    $moves   = vergeml_librarian_moves_table();

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    $wpdb->query( "DROP TABLE IF EXISTS {$moves}" );
    $wpdb->query( "DROP TABLE IF EXISTS {$batches}" );
    // phpcs:enable

    return ! g7_table_exists( $batches ) && ! g7_table_exists( $moves );
}


$g7_batches = vergeml_librarian_batches_table();
$g7_moves   = vergeml_librarian_moves_table();
$g7_before  = get_option( VERGEML_LIBRARIAN_OPTION, array() );

/* ------------------------------------------------- the tables, moved aside */

/*
 *  This suite drops both librarian tables, four times, and then files through
 *  the real route. Until 2026-09-09 it did that to whatever tables it found --
 *  and on the box those are the site's own. Run once for reassurance after a
 *  schema change, it destroyed 109 move rows and two batches that an
 *  investigation was still resting on. `log_bin` is off on that MariaDB and
 *  there is no dump: none of it came back.
 *
 *  So the real tables are renamed out of the way first and renamed back at the
 *  end. Renamed, not copied: it is atomic, it costs the same whether the table
 *  holds ten rows or ten million, and it leaves the rows untouched rather than
 *  round-tripping them through PHP. Under their own names the tables are
 *  genuinely gone, which is the whole thing this suite is about, so nothing it
 *  proves is weakened -- every drop, every reinstall and the REST call all
 *  happen against tables made for this run and thrown away with it.
 *
 *  A previous run that died mid-way leaves the aside copies behind. That is
 *  recoverable and this refuses to run rather than overwrite them, because the
 *  aside copy is then the only place the site's records still exist.
 */
$g7_bak_batches = $g7_batches . '_g7bak';
$g7_bak_moves   = $g7_moves . '_g7bak';

if ( g7_table_exists( $g7_bak_batches ) || g7_table_exists( $g7_bak_moves ) ) {
    g7_say( "\n{$g7_bak_batches} or {$g7_bak_moves} is already here, which means a run died\n" );
    g7_say( "part way through. Those hold this site's real records. Put them back by hand\n" );
    g7_say( "before running this again -- this suite will not write over them.\n" );
    g7_report();
    exit( 1 );
}

$g7_kept = array();

foreach ( array( $g7_batches => $g7_bak_batches, $g7_moves => $g7_bak_moves ) as $g7_live => $g7_bak ) {

    if ( ! g7_table_exists( $g7_live ) ) {
        continue;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own tables.
    $g7_kept[ $g7_live ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$g7_live}" );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own tables.
    $wpdb->query( "RENAME TABLE {$g7_live} TO {$g7_bak}" );
}

/*
 *  And the folders, because section D files for real.
 *
 *  The route it calls is librarian-apply-step with the date/type scheme, which
 *  makes a folder per year and per month before it moves anything. Those are
 *  as much live state as the rows are -- eleven of them were left on the box's
 *  tree the same afternoon -- so the tree is counted here and checked at the
 *  end.
 */
$g7_terms_before = array();

if ( taxonomy_exists( vergeml_librarian_taxonomy() ) ) {
    $g7_terms_before = get_terms( array(
        'taxonomy'   => vergeml_librarian_taxonomy(),
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );
    $g7_terms_before = is_wp_error( $g7_terms_before ) ? array() : array_map( 'intval', $g7_terms_before );
}

g7_say( "\ngate 7 -- the librarian schema survives losing its tables\n" );
g7_say( sprintf( "  tables: %s, %s\n\n", $g7_batches, $g7_moves ) );


/* ------------------------------------------------------------------ A: base */

g7_say( "A  baseline\n" );

vergeml_librarian_install();

g7_check( 'both tables exist to start with',
    g7_table_exists( $g7_batches ) && g7_table_exists( $g7_moves ) );


/* --------------------------------------------- B: dropped, option untouched */

g7_say( "\nB  both tables dropped, the option still says the schema is current\n" );

g7_check( 'the drop worked', g7_drop_both() );

$g7_state = vergeml_librarian_state();

g7_check( 'the option still claims the current schema',
    isset( $g7_state['schema'] ) && VERGEML_LIBRARIAN_VERSION === (int) $g7_state['schema'],
    'this is what makes the check meaningful' );

vergeml_librarian_maybe_install();

g7_check( 'the lazy check noticed and reinstalled both tables',
    g7_table_exists( $g7_batches ) && g7_table_exists( $g7_moves ),
    'ai-index.php also asks the database, not only the option' );


/* ------------------------------------------------ C: dropped, option deleted */

g7_say( "\nC  both tables dropped and the option gone -- upgraded, never visited wp-admin\n" );

vergeml_librarian_install();

g7_check( 'the drop worked', g7_drop_both() );

delete_option( VERGEML_LIBRARIAN_OPTION );

vergeml_librarian_maybe_install();

g7_check( 'the lazy check reinstalled both tables',
    g7_table_exists( $g7_batches ) && g7_table_exists( $g7_moves ) );

$g7_state = vergeml_librarian_state();

g7_check( 'and wrote the schema version down',
    isset( $g7_state['schema'] ) && VERGEML_LIBRARIAN_VERSION === (int) $g7_state['schema'] );


/* ------------------------------------------------------ D: through the route */

g7_say( "\nD  the same loss, reached the way a user reaches it: apply-step over REST\n" );

vergeml_librarian_install();

g7_check( 'the drop worked', g7_drop_both() );

wp_set_current_user( 1 );

if ( ! current_user_can( 'manage_categories' ) ) {
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
    if ( $admins ) {
        wp_set_current_user( (int) $admins[0] );
    }
}

g7_check( 'running as somebody allowed to apply', current_user_can( 'manage_categories' ) );

$g7_request = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/librarian-apply-step' );
$g7_request->set_param( 'scheme', 'datetype' );
$g7_request->set_param( 'branches', array() );

$wpdb->last_error = '';

$g7_response = rest_do_request( $g7_request );
$g7_data     = $g7_response->get_data();
$g7_status   = (int) $g7_response->get_status();

/*
 *  A 4xx that is *about the library* -- nothing unfiled, no taxonomy on --
 *  is a legitimate answer and not what this check is about. A database error,
 *  or a 500, is the failure being hunted.
 */
$g7_db_error = (string) $wpdb->last_error;

g7_check( 'the route did not raise a database error',
    '' === $g7_db_error,
    '' === $g7_db_error ? '' : $g7_db_error );

g7_check( 'the route did not fatal or 500',
    $g7_status < 500,
    'status ' . $g7_status . ( is_array( $g7_data ) && isset( $g7_data['code'] ) ? ' / ' . $g7_data['code'] : '' ) );

g7_check( 'both tables are back after the call',
    g7_table_exists( $g7_batches ) && g7_table_exists( $g7_moves ) );


/* -------------------------------------------------------------------- tidy */

g7_say( "\ntidying up\n" );

vergeml_librarian_install();

if ( is_array( $g7_before ) && $g7_before ) {
    update_option( VERGEML_LIBRARIAN_OPTION, $g7_before, false );
}

g7_check( 'the schema is installed again',
    g7_table_exists( $g7_batches ) && g7_table_exists( $g7_moves ) );


/* ---------------------------------------------------- and the site's own back */

/*
 *  The folders section D made, taken off again -- and only the empty ones. A
 *  folder that has a picture in it means the apply got past its folders phase
 *  and moved something, and the row that would undo that is in the working
 *  table this is about to throw away. That is not something to tidy quietly:
 *  it is named and the suite fails.
 */
$g7_left = array();
$g7_held = array();

if ( $g7_terms_before && taxonomy_exists( vergeml_librarian_taxonomy() ) ) {

    $g7_terms_after = get_terms( array(
        'taxonomy'   => vergeml_librarian_taxonomy(),
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );
    $g7_terms_after = is_wp_error( $g7_terms_after ) ? array() : array_map( 'intval', $g7_terms_after );

    foreach ( array_diff( $g7_terms_after, $g7_terms_before ) as $g7_new ) {

        $g7_term = get_term( (int) $g7_new, vergeml_librarian_taxonomy() );

        if ( ! ( $g7_term instanceof WP_Term ) ) {
            continue;
        }

        if ( (int) $g7_term->count > 0 ) {
            $g7_held[] = sprintf( '%s (%d pictures)', $g7_term->name, (int) $g7_term->count );
            continue;
        }

        $g7_left[] = (int) $g7_new;
    }

    // Deepest first, so a parent is never removed out from under a child.
    foreach ( array_reverse( $g7_left ) as $g7_new ) {
        wp_delete_term( (int) $g7_new, vergeml_librarian_taxonomy() );
    }
}

g7_check(
    'no folder this run made is left on the tree',
    ! $g7_held,
    $g7_held ? 'still holding pictures: ' . implode( ', ', $g7_held ) : count( $g7_left ) . ' removed'
);

/*
 *  The working tables go, and the site's own come back under their own names.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own tables.
foreach ( array( $g7_batches => $g7_bak_batches, $g7_moves => $g7_bak_moves ) as $g7_live => $g7_bak ) {

    if ( ! g7_table_exists( $g7_bak ) ) {
        continue;
    }

    $wpdb->query( "DROP TABLE IF EXISTS {$g7_live}" );
    $wpdb->query( "RENAME TABLE {$g7_bak} TO {$g7_live}" );
}
// phpcs:enable

$g7_back = true;
$g7_note = array();

foreach ( $g7_kept as $g7_live => $g7_was ) {

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own tables.
    $g7_is = g7_table_exists( $g7_live ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$g7_live}" ) : -1;

    $g7_note[] = sprintf( '%s %d/%d', $g7_live, $g7_is, $g7_was );

    if ( $g7_is !== $g7_was ) {
        $g7_back = false;
    }
}

g7_check(
    'the site\'s own batches and moves are back, every row of them',
    $g7_back,
    implode( ', ', $g7_note )
);

// The option describes the tables that are back, not the ones just thrown away.
vergeml_librarian_maybe_install();

g7_say( sprintf( "\n%d/%d passed\n", $GLOBALS['g7_pass'], $GLOBALS['g7_pass'] + $GLOBALS['g7_fail'] ) );

g7_report();

if ( $GLOBALS['g7_fail'] > 0 ) {
    exit( 1 );
}
