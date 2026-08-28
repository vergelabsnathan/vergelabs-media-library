<?php
/**
 *  Filing by itself: the arithmetic, and the restraint.
 *
 *  The arithmetic is easy to test and the restraint is the part that matters,
 *  so most of this is about the cases where the right answer is to do
 *  nothing: a file between two folders, a file near none of them, a file
 *  somebody has already put somewhere, a folder that has not earned the right
 *  to act, and a folder that has just lost it.
 *
 *  Vectors are hand-written and eight-dimensional so that "near" and "far"
 *  are facts of the fixture rather than of a model. What is being tested is
 *  the decision, and the decision is the same whatever produced the numbers.
 *
 *      wp eval-file tests/tree/auto-file.php --allow-root
 *
 *  or through tests/tree/auto-file-blueprint.json in Playground.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_autofile_suggest' ) ) {
    echo "core/auto-file.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

global $wpdb;

$GLOBALS['uf_pass'] = 0;
$GLOBALS['uf_fail'] = 0;
$uf_log  = '';

function uf_say( $line ) {
    global $uf_log;
    $uf_log .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function uf_check( $label, $ok, $note = '' ) {
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
        $GLOBALS['uf_pass']++;
    } else {
        $GLOBALS['uf_fail']++;
    }
    uf_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

function uf_report() {
    global $uf_log;
    @file_put_contents( __DIR__ . '/auto-file-last-run.txt', $uf_log ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}


$uf_tax   = vergeml_librarian_taxonomy();
$uf_posts = array();
$uf_terms = array();

/*
 *  Vectors are served through the seam rather than written to the index.
 *
 *  Not a convenience: Playground's SQLite layer refuses any INSERT carrying
 *  packed floats, so an embedding cannot be stored there at all. Storing one
 *  is core/ai-index.php's job and is exercised where storage works; what this
 *  suite is about is the decision taken once a vector exists, and that is the
 *  same decision whatever handed it over.
 */
$GLOBALS['uf_vectors'] = array();

add_filter( 'vergeml_autofile_vector', function ( $vector, $attachment_id ) {
    return isset( $GLOBALS['uf_vectors'][ (int) $attachment_id ] )
        ? $GLOBALS['uf_vectors'][ (int) $attachment_id ]
        : $vector;
}, 10, 2 );

uf_say( "\nfiling by itself\n\n" );

if ( '' === $uf_tax ) {
    uf_say( "no media taxonomy is switched on -- nothing to file into\n" );
    uf_report();
    exit( 1 );
}

vergeml_index_install();
vergeml_librarian_maybe_install();


/**
 *  An eight-dimensional point near a named corner, nudged by $n so that a
 *  folder built from several has a spread rather than a single repeated
 *  point.
 */
function uf_vector( $corner, $n ) {

    $v = array_fill( 0, 8, 0.0 );

    $v[ $corner ]           = 1.0 + ( $n * 0.01 );
    $v[ ( $corner + 1 ) % 8 ] = 0.05 * $n;

    return $v;
}


function uf_file( $title, $vector, $term_id = 0 ) {

    global $uf_posts, $uf_tax;

    $id = wp_insert_post( array(
        'post_title'     => 'zz autofile ' . $title,
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/png',
    ) );

    $uf_posts[] = (int) $id;

    // The row says "described"; the vector comes through the seam.
    vergeml_index_set( (int) $id, array(
        'caption'      => 'seeded',
        'kind'         => 'photo',
        'described_at' => gmdate( 'Y-m-d H:i:s' ),
    ) );

    $GLOBALS['uf_vectors'][ (int) $id ] = $vector;

    if ( $term_id ) {
        wp_set_object_terms( (int) $id, array( (int) $term_id ), $uf_tax );
    }

    return (int) $id;
}


/* ------------------------------------------------------------- the fixture */

uf_say( "the fixture\n" );

foreach ( array( 'zzInvoices', 'zzHarbour' ) as $name ) {
    $term = wp_insert_term( $name, $uf_tax );
    if ( is_wp_error( $term ) ) {
        $existing = get_term_by( 'name', $name, $uf_tax );
        $uf_terms[ $name ] = $existing instanceof WP_Term ? (int) $existing->term_id : 0;
    } else {
        $uf_terms[ $name ] = (int) $term['term_id'];
    }
}

// Four filed files per folder, clustered at opposite corners.
for ( $i = 0; $i < 4; $i++ ) {
    uf_file( 'inv-' . $i, uf_vector( 0, $i ), $uf_terms['zzInvoices'] );
    uf_file( 'har-' . $i, uf_vector( 4, $i ), $uf_terms['zzHarbour'] );
}

$uf_centroid = vergeml_autofile_centroid( $uf_terms['zzInvoices'], $uf_tax );

uf_check( 'a folder with four described files has a middle',
    is_array( $uf_centroid ) && 4 === (int) $uf_centroid['n'] && $uf_centroid['spread'] > 0 );

$uf_thin = wp_insert_term( 'zzThin', $uf_tax );
$uf_terms['zzThin'] = is_wp_error( $uf_thin ) ? 0 : (int) $uf_thin['term_id'];
uf_file( 'thin-0', uf_vector( 2, 0 ), $uf_terms['zzThin'] );

uf_check( 'a folder with one described file has none',
    null === vergeml_autofile_centroid( $uf_terms['zzThin'], $uf_tax ),
    'two points have a midpoint but no spread, and a spread of zero admits anything' );


/* ------------------------------------------------------------ the answer */

uf_say( "\nwhere it would go\n" );

$uf_near = uf_file( 'near-invoices', uf_vector( 0, 2 ) );

$uf_suggestion = vergeml_autofile_suggest( $uf_near );

uf_check( 'a file near one folder is suggested for it',
    is_array( $uf_suggestion ) && $uf_terms['zzInvoices'] === $uf_suggestion['term_id'],
    is_array( $uf_suggestion ) ? 'term ' . $uf_suggestion['term_id'] : 'null' );

uf_check( 'and it is not earned yet', is_array( $uf_suggestion ) && false === $uf_suggestion['earned'] );

// Halfway between the two clusters: both would do, so neither.
$uf_between = array_fill( 0, 8, 0.0 );
$uf_between[0] = 0.5;
$uf_between[4] = 0.5;

$uf_mid = uf_file( 'between', $uf_between );

uf_check( 'a file between two folders is suggested for neither',
    null === vergeml_autofile_suggest( $uf_mid ),
    'when two folders would both do, choosing is worse than not' );

// Nowhere near anything.
$uf_far = uf_file( 'far', uf_vector( 6, 9 ) );

uf_check( 'a file near nothing is suggested for nothing',
    null === vergeml_autofile_suggest( $uf_far ) );

$uf_already = uf_file( 'already-filed', uf_vector( 0, 3 ), $uf_terms['zzHarbour'] );

uf_check( 'a file somebody already filed is left alone',
    null === vergeml_autofile_suggest( $uf_already ),
    'even when it looks like it belongs elsewhere' );

$uf_blank = wp_insert_post( array(
    'post_title'     => 'zz autofile undescribed',
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => 'image/png',
) );
$uf_posts[] = (int) $uf_blank;

uf_check( 'a file nobody has described is left alone',
    null === vergeml_autofile_suggest( (int) $uf_blank ) );


/* ------------------------------------------------------------ the restraint */

uf_say( "\nthe restraint\n" );

$uf_before_terms = wp_get_object_terms( $uf_near, $uf_tax, array( 'fields' => 'ids' ) );

$uf_sweep = vergeml_autofile_sweep( 50 );

uf_check( 'a sweep files nothing while no folder has earned it',
    0 === (int) $uf_sweep['filed'], $uf_sweep['filed'] . ' filed' );

uf_check( 'and the near file is still where it was',
    wp_get_object_terms( $uf_near, $uf_tax, array( 'fields' => 'ids' ) ) === $uf_before_terms );

uf_check( 'but it is offered', count( $uf_sweep['suggested'] ) > 0, count( $uf_sweep['suggested'] ) . ' suggested' );


/* ------------------------------------------------------------ earning it */

uf_say( "\nearning it\n" );

for ( $i = 0; $i < VERGEML_AUTOFILE_EARN; $i++ ) {
    vergeml_autofile_record( $uf_terms['zzInvoices'], 'accepted' );
}

uf_check( 'five accepted suggestions earn a folder its autonomy',
    true === vergeml_autofile_earned( $uf_terms['zzInvoices'] ) );

uf_check( 'the other folder has earned nothing',
    false === vergeml_autofile_earned( $uf_terms['zzHarbour'] ) );

$uf_auto = uf_file( 'auto-me', uf_vector( 0, 1 ) );

$uf_sweep2 = vergeml_autofile_sweep( 50 );

uf_check( 'now a sweep files into it', $uf_sweep2['filed'] > 0, $uf_sweep2['filed'] . ' filed' );

$uf_landed = wp_get_object_terms( $uf_auto, $uf_tax, array( 'fields' => 'ids' ) );

uf_check( 'and the file landed in that folder',
    in_array( $uf_terms['zzInvoices'], array_map( 'intval', (array) $uf_landed ), true ) );

uf_check( 'the file that belongs nowhere was still left alone',
    empty( wp_get_object_terms( $uf_far, $uf_tax, array( 'fields' => 'ids' ) ) ) );


/* ---------------------------------------------------------- and losing it */

uf_say( "\nand losing it\n" );

vergeml_autofile_record( $uf_terms['zzInvoices'], 'dismissed' );

uf_check( 'one refusal takes the autonomy away',
    false === vergeml_autofile_earned( $uf_terms['zzInvoices'] ) );

$uf_ledger = vergeml_autofile_ledger( $uf_terms['zzInvoices'] );

uf_check( 'and the accepted count is withdrawn, not decremented',
    0 === $uf_ledger['accepted'],
    'a refusal is evidence the middle does not mean what we thought' );

$uf_after = uf_file( 'after-refusal', uf_vector( 0, 2 ) );

$uf_sweep3 = vergeml_autofile_sweep( 50 );

uf_check( 'so it goes back to asking', 0 === (int) $uf_sweep3['filed'], $uf_sweep3['filed'] . ' filed' );


/* -------------------------------------------------------------- undoable */

uf_say( "\nundoable\n" );

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$uf_logged = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_moves} WHERE attachment_id = %d",
    $uf_auto
) );

$uf_batch = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT batch_id FROM {$wpdb->vergeml_librarian_moves} WHERE attachment_id = %d LIMIT 1",
    $uf_auto
) );

$uf_created = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT term_created FROM {$wpdb->vergeml_librarian_moves} WHERE attachment_id = %d LIMIT 1",
    $uf_auto
) );

$uf_scheme = (string) $wpdb->get_var( $wpdb->prepare(
    "SELECT scheme FROM {$wpdb->vergeml_librarian_batches} WHERE batch_id = %d",
    $uf_batch
) );
// phpcs:enable

uf_check( 'the automatic file is in the moves log', 1 === $uf_logged );

uf_check( 'in a batch of its own', 'auto' === $uf_scheme, $uf_scheme );

uf_check( 'and never claims to have made the folder', 0 === $uf_created,
    'so undo can never delete a folder because of this' );


/* -------------------------------------------------------------------- tidy */

uf_say( "\ntidying up\n" );

foreach ( array_unique( $uf_posts ) as $id ) {
    if ( get_post( $id ) ) {
        vergeml_index_delete( $id );
        wp_delete_post( $id, true );
    }
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
foreach ( array_unique( $uf_posts ) as $id ) {
    $wpdb->delete( vergeml_librarian_moves_table(), array( 'attachment_id' => (int) $id ), array( '%d' ) );
}

$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->vergeml_librarian_batches} WHERE scheme IN ( 'auto', 'suggested' ) AND created_at >= %s",
    gmdate( 'Y-m-d 00:00:00' )
) );
// phpcs:enable

foreach ( $uf_terms as $term_id ) {
    if ( $term_id ) {
        wp_delete_term( (int) $term_id, $uf_tax );
    }
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$uf_left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE 'zz autofile%' AND post_type = 'attachment'" );

uf_check( 'the seeded files are gone', 0 === $uf_left, $uf_left . ' left behind' );

uf_say( sprintf( "\n%d/%d passed\n", $GLOBALS['uf_pass'], $GLOBALS['uf_pass'] + $GLOBALS['uf_fail'] ) );

uf_report();

if ( $GLOBALS['uf_fail'] > 0 ) {
    exit( 1 );
}
