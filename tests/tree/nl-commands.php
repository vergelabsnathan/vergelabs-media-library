<?php
/**
 *  Saying what you want: mostly a test of what it refuses.
 *
 *  The parser is small and the interesting cases are all the ones where the
 *  right behaviour is to stop: a verb that is not on the list, a folder that
 *  does not exist, a phrase nobody can resolve, and every spelling of "delete
 *  everything" somebody might try. A command box that quietly does something
 *  approximate is worse than one that says it did not understand.
 *
 *      wp eval-file tests/tree/nl-commands.php --allow-root
 *
 *  or through tests/tree/nl-commands-blueprint.json in Playground.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_nl_plan' ) ) {
    echo "core/nl-commands.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

global $wpdb;

$GLOBALS['nl_pass'] = 0;
$GLOBALS['nl_fail'] = 0;
$nl_log  = '';

function nl_say( $line ) {
    global $nl_log;
    $nl_log .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function nl_check( $label, $ok, $note = '' ) {
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
        $GLOBALS['nl_pass']++;
    } else {
        $GLOBALS['nl_fail']++;
    }
    nl_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

function nl_report() {
    global $nl_log;
    @file_put_contents( __DIR__ . '/nl-commands-last-run.txt', $nl_log ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}


$nl_tax = vergeml_librarian_taxonomy();

nl_say( "\nsaying what you want\n\n" );

if ( '' === $nl_tax ) {
    nl_say( "no media taxonomy is switched on\n" );
    nl_report();
    exit( 1 );
}

vergeml_librarian_maybe_install();

$nl_posts = array();
$nl_terms = array();


/* ------------------------------------------------------------- the fixture */

nl_say( "the fixture\n" );

foreach ( array( 'zzProducts', 'zzAccounts' ) as $name ) {
    $term = wp_insert_term( $name, $nl_tax );
    $nl_terms[ $name ] = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
}

for ( $i = 0; $i < 5; $i++ ) {
    $id = wp_insert_post( array(
        'post_title'     => 'zz nl ' . $i,
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/png',
    ) );
    $nl_posts[] = (int) $id;
    // The first three go in Products; the rest stay loose.
    if ( $i < 3 ) {
        wp_set_object_terms( (int) $id, array( $nl_terms['zzProducts'] ), $nl_tax );
    }
}

nl_check( 'five files, three of them filed', 5 === count( $nl_posts ) );


/* --------------------------------------------------------------- refusals */

nl_say( "\nwhat it refuses\n" );

foreach ( array(
    'delete everything',
    'remove the unused files',
    'trash all screenshots',
    'empty the library',
    'erase zzProducts',
    'purge everything',
) as $nl_bad ) {

    $nl_result = vergeml_nl_plan( $nl_bad );

    nl_check(
        '"' . $nl_bad . '" is refused',
        is_wp_error( $nl_result ) && 'vergeml_nl_no_delete' === $nl_result->get_error_code(),
        is_wp_error( $nl_result ) ? $nl_result->get_error_code() : 'produced a plan'
    );
}

nl_check( 'delete is not one of the verbs',
    ! in_array( 'delete', vergeml_nl_verbs(), true ) );

$nl_gibberish = vergeml_nl_plan( 'please sort out my library somehow' );

nl_check( 'a sentence it cannot parse is refused, not guessed at',
    is_wp_error( $nl_gibberish ) && 'vergeml_nl_unparsed' === $nl_gibberish->get_error_code() );

$nl_nowhere = vergeml_nl_plan( 'move zzProducts into zzNoSuchFolder' );

nl_check( 'a folder that does not exist is refused',
    is_wp_error( $nl_nowhere ) && 'vergeml_nl_unknown_folder' === $nl_nowhere->get_error_code() );

$nl_nothing = vergeml_nl_plan( 'move the wibbles into zzAccounts' );

nl_check( 'a selection nobody can resolve is an error, not an empty set',
    is_wp_error( $nl_nothing ) && 'vergeml_nl_unknown_selection' === $nl_nothing->get_error_code(),
    '"it matched nothing" and "I did not understand" must not look the same' );


/* -------------------------------------------------------- planning is safe */

nl_say( "\nplanning changes nothing\n" );

$nl_before = wp_get_object_terms( $nl_posts[0], $nl_tax, array( 'fields' => 'ids' ) );

$nl_plan = vergeml_nl_plan( 'move zzProducts into zzAccounts' );

nl_check( 'a good sentence produces a plan',
    is_array( $nl_plan ) && 'move' === $nl_plan['verb'],
    is_wp_error( $nl_plan ) ? $nl_plan->get_error_message() : '' );

nl_check( 'the plan counts the files it would touch',
    is_array( $nl_plan ) && 3 === (int) $nl_plan['count'],
    is_array( $nl_plan ) ? $nl_plan['count'] . ' counted' : '' );

nl_check( 'and shows some of them',
    is_array( $nl_plan ) && count( $nl_plan['sample'] ) > 0 );

nl_check( 'and says what it would do, in words',
    is_array( $nl_plan ) && false !== strpos( $nl_plan['summary'], 'zzAccounts' ),
    is_array( $nl_plan ) ? $nl_plan['summary'] : '' );

nl_check( 'and nothing has moved',
    wp_get_object_terms( $nl_posts[0], $nl_tax, array( 'fields' => 'ids' ) ) === $nl_before );


/* ------------------------------------------------------------- doing it */

nl_say( "\ndoing it\n" );

$nl_done = vergeml_nl_run( $nl_plan );

nl_check( 'running the plan moves them', is_array( $nl_done ) && 3 === (int) $nl_done['done'],
    is_wp_error( $nl_done ) ? $nl_done->get_error_message() : $nl_done['done'] . ' done' );

$nl_now = array_map( 'intval', (array) wp_get_object_terms( $nl_posts[0], $nl_tax, array( 'fields' => 'ids' ) ) );

nl_check( 'the file is in the new folder', in_array( $nl_terms['zzAccounts'], $nl_now, true ) );

nl_check( 'and move means move -- it left the old one',
    ! in_array( $nl_terms['zzProducts'], $nl_now, true ) );

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nl_logged = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id = %d",
    (int) $nl_done['batch_id']
) );

$nl_scheme = (string) $wpdb->get_var( $wpdb->prepare(
    "SELECT scheme FROM {$wpdb->vergeml_librarian_batches} WHERE batch_id = %d",
    (int) $nl_done['batch_id']
) );
// phpcs:enable

nl_check( 'every move is in the log, so undo covers it', 3 === $nl_logged, $nl_logged . ' logged' );

nl_check( 'in a batch that says where it came from', 'spoken' === $nl_scheme, $nl_scheme );


/* ------------------------------------------------------------ tag vs move */

nl_say( "\ntag adds, move replaces\n" );

$nl_tag_plan = vergeml_nl_plan( 'tag zzAccounts with zzProducts' );

nl_check( 'a tag sentence plans a tag',
    is_array( $nl_tag_plan ) && 'tag' === $nl_tag_plan['verb'] );

vergeml_nl_run( $nl_tag_plan );

$nl_both = array_map( 'intval', (array) wp_get_object_terms( $nl_posts[0], $nl_tax, array( 'fields' => 'ids' ) ) );

nl_check( 'tagging kept the folder it was already in',
    in_array( $nl_terms['zzAccounts'], $nl_both, true ) && in_array( $nl_terms['zzProducts'], $nl_both, true ),
    implode( ',', $nl_both ) );


/* --------------------------------------------------------- create, rename */

nl_say( "\ncreate and rename\n" );

$nl_create = vergeml_nl_plan( 'create a folder called zzArchive' );

nl_check( 'a create sentence keeps the capitals the user typed',
    is_array( $nl_create ) && 'create' === $nl_create['verb'] && 'zzArchive' === $nl_create['name'],
    is_wp_error( $nl_create ) ? $nl_create->get_error_message() : $nl_create['name'] );

$nl_made = vergeml_nl_run( $nl_create );

nl_check( 'and it makes one', is_array( $nl_made ) && $nl_made['term_id'] > 0 );

if ( is_array( $nl_made ) ) {
    $nl_terms['zzArchive'] = (int) $nl_made['term_id'];
}

$nl_rename = vergeml_nl_plan( 'rename zzAccounts to zzLedger' );

nl_check( 'a rename sentence plans a rename',
    is_array( $nl_rename ) && 'rename' === $nl_rename['verb'] );

nl_check( 'and says the files stay put',
    is_array( $nl_rename ) && false !== strpos( $nl_rename['summary'], 'do not move' ) );

vergeml_nl_run( $nl_rename );

$nl_renamed = get_term( $nl_terms['zzAccounts'], $nl_tax );

nl_check( 'the folder has the new name',
    $nl_renamed instanceof WP_Term && 'zzLedger' === $nl_renamed->name,
    $nl_renamed instanceof WP_Term ? $nl_renamed->name : 'gone' );

$nl_still = array_map( 'intval', (array) wp_get_object_terms( $nl_posts[0], $nl_tax, array( 'fields' => 'ids' ) ) );

nl_check( 'and the files it held are still in it',
    in_array( $nl_terms['zzAccounts'], $nl_still, true ) );


/* --------------------------------------------------- the plan is the input */

nl_say( "\nthe plan is what runs, not the sentence\n" );

$nl_forged = vergeml_nl_run( array( 'verb' => 'delete', 'ids' => $nl_posts, 'term_id' => $nl_terms['zzProducts'] ) );

nl_check( 'a plan naming a verb that does not exist is refused',
    is_wp_error( $nl_forged ) && 'vergeml_nl_bad_plan' === $nl_forged->get_error_code() );

$nl_survivors = 0;
foreach ( $nl_posts as $id ) {
    if ( get_post( $id ) ) {
        $nl_survivors++;
    }
}

nl_check( 'and nothing was deleted by it', 5 === $nl_survivors, $nl_survivors . ' of 5 still there' );


/* -------------------------------------------------------------------- tidy */

nl_say( "\ntidying up\n" );

foreach ( $nl_posts as $id ) {
    if ( get_post( $id ) ) {
        wp_delete_post( $id, true );
    }
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
foreach ( $nl_posts as $id ) {
    $wpdb->delete( vergeml_librarian_moves_table(), array( 'attachment_id' => (int) $id ), array( '%d' ) );
}

$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->vergeml_librarian_batches} WHERE scheme = %s",
    'spoken'
) );
// phpcs:enable

foreach ( $nl_terms as $term_id ) {
    if ( $term_id ) {
        wp_delete_term( (int) $term_id, $nl_tax );
    }
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$nl_left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE 'zz nl %' AND post_type = 'attachment'" );

nl_check( 'the seeded files are gone', 0 === $nl_left, $nl_left . ' left behind' );

nl_say( sprintf( "\n%d/%d passed\n", $GLOBALS['nl_pass'], $GLOBALS['nl_pass'] + $GLOBALS['nl_fail'] ) );

nl_report();

if ( $GLOBALS['nl_fail'] > 0 ) {
    exit( 1 );
}
