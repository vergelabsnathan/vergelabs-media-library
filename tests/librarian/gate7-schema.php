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
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_librarian_install' ) ) {
    echo "core/librarian.php is not loaded -- is the plugin active, or is safe mode on?\n";
    exit( 1 );
}

global $wpdb;

$g7_pass = 0;
$g7_fail = 0;

/*
 *  Playground's run-blueprint only shows a step's output when the step fails,
 *  so a passing run prints nothing at all. Everything said here is kept as
 *  well and written beside this file at the end, where the host can read it
 *  either way. An output buffer would not do: WordPress flushes its own on
 *  shutdown before anything registered later gets a look at it.
 */
$g7_log = '';

function g7_say( $line ) {
    global $g7_log;
    $g7_log .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}


function g7_report() {
    global $g7_log;
    @file_put_contents( __DIR__ . '/gate7-last-run.txt', $g7_log ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}


function g7_check( $label, $ok, $note = '' ) {

    global $g7_pass, $g7_fail;

    if ( $ok ) {
        $g7_pass++;
    } else {
        $g7_fail++;
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

g7_say( sprintf( "\n%d/%d passed\n", $g7_pass, $g7_pass + $g7_fail ) );

g7_report();

if ( $g7_fail > 0 ) {
    exit( 1 );
}
