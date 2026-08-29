<?php
/**
 *  Folders as a file, out and back in again.
 *
 *  The round trip is the test. Anything else -- counting rows, checking a
 *  header -- can pass while the thing people will actually do with this is
 *  broken, and the thing people will actually do is write the tree out, edit
 *  it in a spreadsheet, and read it back.
 *
 *      wp eval-file tests/import/csv.php --allow-root
 *
 *  Seeds its own folders under a zzcsv root, and deletes only those. The rest
 *  of the library's tree is read (the export is whole-taxonomy, and that is
 *  the point) but never removed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_csv_export_rows' ) ) {
    echo "core/import-csv.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

global $wpdb;

$GLOBALS['cs_pass'] = 0;
$GLOBALS['cs_fail'] = 0;
$GLOBALS['cs_log']  = '';

function cs_say( $line ) {
    $GLOBALS['cs_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function cs_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['cs_pass']++;
    } else {
        $GLOBALS['cs_fail']++;
    }
    cs_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

/** The whole taxonomy as path => sorted attachment ids. What "identical"
 *  means for a tree, in one comparable value. */
function cs_shape( $taxonomy, $prefix = '' ) {

    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

    if ( is_wp_error( $terms ) ) {
        return array();
    }

    $names  = array();
    $parent = array();

    foreach ( $terms as $term ) {
        $names[ (int) $term->term_id ]  = $term->name;
        $parent[ (int) $term->term_id ] = (int) $term->parent;
    }

    $shape = array();

    foreach ( array_keys( $names ) as $id ) {

        $path = vergeml_csv_path( $id, $names, $parent );

        if ( '' !== $prefix && 0 !== strpos( $path, $prefix ) ) {
            continue;
        }

        $files = get_objects_in_term( array( $id ), $taxonomy );
        $files = is_wp_error( $files ) ? array() : array_map( 'intval', $files );
        sort( $files );

        $shape[ $path ] = $files;
    }

    ksort( $shape );

    return $shape;
}

function cs_text( $rows ) {
    $out = '';
    foreach ( $rows as $row ) {
        $out .= vergeml_csv_line( $row );
    }
    return $out;
}


cs_say( "\nfolders as a file\n\n" );

$cs_tax = 'media_category';

if ( ! in_array( $cs_tax, vergeml_tree_taxonomies(), true ) ) {
    cs_say( "media_category is not a tree taxonomy on this site.\n" );
    exit( 1 );
}

$cs_made_terms = array();
$cs_made_posts = array();


/* ------------------------------------------------------------ the fixture */

cs_say( "A  a tree to write out\n" );

// Four images, so "which files are in which folder" is a real question.
for ( $cs_i = 0; $cs_i < 4; $cs_i++ ) {

    $cs_name = 'zzcsv-img-' . $cs_i;

    $cs_id = wp_insert_post( array(
        'post_title'     => $cs_name,
        'post_name'      => $cs_name,
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/jpeg',
        'guid'           => 'http://example.test/' . $cs_name . '.jpg',
    ) );

    if ( $cs_id && ! is_wp_error( $cs_id ) ) {
        update_post_meta( $cs_id, '_wp_attached_file', $cs_name . '.jpg' );
        $cs_made_posts[] = (int) $cs_id;
    }
}

cs_check( 'four files seeded', 4 === count( $cs_made_posts ), count( $cs_made_posts ) . ' made' );

/*
 *  zzcsv root
 *    Acme
 *      Twenty Four   -- two files
 *      Logos         -- one file
 *    Empty           -- nothing, and it has to survive the round trip anyway
 */
$cs_paths = array(
    'zzcsv'                        => 0,
    'zzcsv/Acme'                   => 'zzcsv',
    'zzcsv/Acme/Twenty Four'       => 'zzcsv/Acme',
    'zzcsv/Acme/Logos'             => 'zzcsv/Acme',
    'zzcsv/Empty'                  => 'zzcsv',
);

$cs_term = array();

foreach ( $cs_paths as $cs_path => $cs_parent ) {

    $cs_leaf = substr( $cs_path, strrpos( $cs_path, '/' ) === false ? 0 : strrpos( $cs_path, '/' ) + 1 );

    $cs_new = wp_insert_term( $cs_leaf, $cs_tax, array(
        'parent' => $cs_parent ? $cs_term[ $cs_parent ] : 0,
    ) );

    if ( is_wp_error( $cs_new ) ) {
        cs_check( 'seeded ' . $cs_path, false, $cs_new->get_error_message() );
        continue;
    }

    $cs_term[ $cs_path ] = (int) $cs_new['term_id'];
    $cs_made_terms[]     = (int) $cs_new['term_id'];
}

cs_check( 'five folders seeded', 5 === count( $cs_made_terms ), count( $cs_made_terms ) . ' made' );

wp_set_object_terms( $cs_made_posts[0], array( $cs_term['zzcsv/Acme/Twenty Four'] ), $cs_tax );
wp_set_object_terms( $cs_made_posts[1], array( $cs_term['zzcsv/Acme/Twenty Four'] ), $cs_tax );
wp_set_object_terms( $cs_made_posts[2], array( $cs_term['zzcsv/Acme/Logos'] ), $cs_tax );
// The fourth is left unfiled on purpose: a file in no folder must not appear.

$cs_before = cs_shape( $cs_tax, 'zzcsv' );

cs_check( 'the fixture reads back as five folders', 5 === count( $cs_before ), count( $cs_before ) . ' paths' );


/* ------------------------------------------------------------- exporting */

cs_say( "\nB  writing it out\n" );

$cs_rows = vergeml_csv_export_rows( $cs_tax );

cs_check( 'export succeeded', ! is_wp_error( $cs_rows ), is_wp_error( $cs_rows ) ? $cs_rows->get_error_message() : count( $cs_rows ) . ' rows' );

$cs_flat = array();

foreach ( $cs_rows as $cs_row ) {
    $cs_flat[] = $cs_row[0] . '|' . $cs_row[1];
}

cs_check( 'the header is first', isset( $cs_rows[0] ) && 'folder' === $cs_rows[0][0] );
cs_check(
    'a nested path is written with slashes',
    in_array( 'zzcsv/Acme/Twenty Four|' . $cs_made_posts[0], $cs_flat, true ),
    'zzcsv/Acme/Twenty Four|' . $cs_made_posts[0]
);
cs_check(
    'an empty folder gets a row of its own',
    in_array( 'zzcsv/Empty|', $cs_flat, true ),
    'without this it would vanish on the way out'
);
cs_check(
    'the unfiled file is nowhere in the export',
    ! in_array( $cs_made_posts[3], array_map( 'intval', wp_list_pluck( $cs_rows, 1 ) ), true )
);

$cs_text = cs_text( $cs_rows );

// A folder name with a comma in it is the ordinary case that breaks a naive
// writer, so the quoting is asserted rather than assumed.
$cs_quoted = vergeml_csv_line( array( 'A, B', '7', 'x"y.jpg' ) );
cs_check( 'a comma in a name is quoted', false !== strpos( $cs_quoted, '"A, B"' ), trim( $cs_quoted ) );
cs_check( 'a quote in a name is doubled', false !== strpos( $cs_quoted, '"x""y.jpg"' ), trim( $cs_quoted ) );


/* -------------------------------------------------------------- parsing */

cs_say( "\nC  reading it back\n" );

$cs_parsed = vergeml_csv_parse( $cs_text );

cs_check( 'parse succeeded', ! is_wp_error( $cs_parsed ), is_wp_error( $cs_parsed ) ? $cs_parsed->get_error_message() : '' );

if ( ! is_wp_error( $cs_parsed ) ) {

    $cs_names = wp_list_pluck( $cs_parsed['folders'], 'name' );

    cs_check( 'the header row was not read as a folder', ! in_array( 'folder', $cs_names, true ) );
    cs_check( 'nothing was reported as a problem', 0 === (int) $cs_parsed['problem_count'], implode( ' ', $cs_parsed['problems'] ) );
}


/* ------------------------------------------------ wipe, import, compare */

cs_say( "\nD  the round trip\n" );

$cs_all_before = count( cs_shape( $cs_tax ) );

// Only the seeded subtree goes. The files stay exactly where they are: a
// folder is a label, and deleting the label must not touch the picture.
foreach ( array_reverse( $cs_made_terms ) as $cs_id ) {
    wp_delete_term( $cs_id, $cs_tax );
}

$cs_gone = cs_shape( $cs_tax, 'zzcsv' );

cs_check( 'the subtree is gone', 0 === count( $cs_gone ), count( $cs_gone ) . ' left' );

$cs_files_survived = 0;

foreach ( $cs_made_posts as $cs_id ) {
    if ( get_post( $cs_id ) ) {
        $cs_files_survived++;
    }
}

cs_check( 'and the files did not go with it', 4 === $cs_files_survived, $cs_files_survived . ' of 4' );

vergeml_csv_stash( $cs_parsed );

$cs_sources = vergeml_import_sources();
cs_check( 'a staged file appears as a source', isset( $cs_sources['csv'] ) );

$cs_read = vergeml_import_read( 'csv' );
cs_check( 'and the importer can read it', ! is_wp_error( $cs_read ) && ! empty( $cs_read['folders'] ) );

$cs_plan = vergeml_import_plan( 'csv', $cs_tax );
cs_check( 'it plans', ! is_wp_error( $cs_plan ), is_wp_error( $cs_plan ) ? $cs_plan->get_error_message() : $cs_plan['create'] . ' new, ' . $cs_plan['merge'] . ' merged' );

/*
 *  Every folder that was NOT deleted is still there, so the plan must say it
 *  is merging those rather than creating them. This is the assertion that
 *  catches a round trip that "works" by duplicating the entire library.
 */
if ( ! is_wp_error( $cs_plan ) ) {
    cs_check( 'the folders that still exist are merged, not recreated', $cs_plan['merge'] > 0, $cs_plan['merge'] . ' merged' );
    cs_check( 'and the five that were deleted are the new ones', 5 === (int) $cs_plan['create'], $cs_plan['create'] . ' to create' );
}

$cs_result = vergeml_import_run( 'csv', $cs_tax );
$cs_passes = 0;

while ( ! is_wp_error( $cs_result ) && empty( $cs_result['complete'] ) && ! empty( $cs_result['resume'] ) && $cs_passes < 200 ) {
    $cs_result = vergeml_import_run( 'csv', $cs_tax, $cs_result['resume'] );
    $cs_passes++;
}

cs_check( 'the import ran to completion', ! is_wp_error( $cs_result ) && ! empty( $cs_result['complete'] ), is_wp_error( $cs_result ) ? $cs_result->get_error_message() : $cs_passes . ' extra passes' );

$cs_after = cs_shape( $cs_tax, 'zzcsv' );

cs_check(
    'THE ROUND TRIP: the tree that came back is the tree that went out',
    $cs_before === $cs_after,
    $cs_before === $cs_after ? '' : wp_json_encode( array( 'out' => $cs_before, 'back' => $cs_after ) )
);

cs_check(
    'and the rest of the library gained no folders',
    $cs_all_before === count( cs_shape( $cs_tax ) ),
    count( cs_shape( $cs_tax ) ) . ' vs ' . $cs_all_before
);

// Everything the import made is recorded, so it inherits undo unchanged.
$cs_import_id = is_wp_error( $cs_result ) ? '' : (string) $cs_result['id'];
cs_check( 'the import is undoable', '' !== $cs_import_id, $cs_import_id );


/* ------------------------------------------------------------- refusals */

cs_say( "\nE  what it refuses, and what it merely reports\n" );

$cs_empty = vergeml_csv_parse( "\n\n" );
cs_check( 'an empty file is refused', is_wp_error( $cs_empty ), is_wp_error( $cs_empty ) ? $cs_empty->get_error_code() : 'accepted' );

$cs_none = vergeml_csv_parse( "folder,attachment_id,filename\n" );
cs_check( 'a file with only a header is refused', is_wp_error( $cs_none ), is_wp_error( $cs_none ) ? $cs_none->get_error_code() : 'accepted' );

/*
 *  A file naming ids from another site is the mistake somebody will actually
 *  make, and filing a page into a media folder is what it would do. The row
 *  is dropped and counted; the rest of the file still imports, because
 *  refusing twenty thousand good rows over fifty bad ones helps nobody.
 */
$cs_bad = vergeml_csv_parse( "folder,attachment_id,filename\nzzcsv-x/A,1,ok.jpg\nzzcsv-x/A,999999999,ghost.jpg\nzzcsv-x/B,not-a-number,odd.jpg\n" );

if ( is_wp_error( $cs_bad ) ) {
    cs_check( 'a file with bad rows still imports the good ones', false, $cs_bad->get_error_message() );
} else {
    // Three: zzcsv-x, and A and B inside it. The root counts -- a path is
    // every level of itself, which is what makes Clients/Acme/2024 three
    // folders rather than one.
    cs_check( 'a file with bad rows still imports the good ones', 3 === count( $cs_bad['folders'] ), count( $cs_bad['folders'] ) . ' folders' );
    cs_check( 'an id that is not an attachment here is reported', $cs_bad['problem_count'] > 0, implode( ' ', $cs_bad['problems'] ) );
    cs_check( 'and a value that is not a number is reported too', 2 === (int) $cs_bad['problem_count'], $cs_bad['problem_count'] . ' problems' );
}

$cs_deep = vergeml_csv_parse( "folder,attachment_id\n" . implode( '/', array_fill( 0, VERGEML_CSV_MAX_DEPTH + 2, 'x' ) ) . ",\nzzcsv-y/ok,\n" );

/*
 *  Asserted on the names rather than on a count. The depth guard refuses the
 *  path before creating any level of it, so not one 'x' may appear -- a count
 *  alone would pass if it had built the first few and stopped.
 */
$cs_deep_names = is_wp_error( $cs_deep ) ? array() : array_values( wp_list_pluck( $cs_deep['folders'], 'name' ) );

cs_check(
    'a pathologically deep path is refused whole, not built partway',
    array( 'zzcsv-y', 'ok' ) === $cs_deep_names,
    is_wp_error( $cs_deep ) ? $cs_deep->get_error_code() : wp_json_encode( $cs_deep_names )
);

// A file written by hand may have no header at all, and eating its first line
// would silently lose somebody's first folder.
$cs_noheader = vergeml_csv_parse( "zzcsv-z/First,\nzzcsv-z/Second,\n" );
cs_check(
    'a file without a header keeps its first row',
    ! is_wp_error( $cs_noheader ) && 3 === count( $cs_noheader['folders'] ),
    is_wp_error( $cs_noheader ) ? $cs_noheader->get_error_code() : count( $cs_noheader['folders'] ) . ' folders (zzcsv-z, First, Second)'
);


/* ------------------------------------------------------------- tidying */

cs_say( "\ntidying up\n" );

vergeml_csv_clear();

$cs_left_terms = get_terms( array(
    'taxonomy'   => $cs_tax,
    'hide_empty' => false,
    'name__like' => 'zzcsv',
) );

foreach ( array( 'zzcsv/Acme/Twenty Four', 'zzcsv/Acme/Logos', 'zzcsv/Acme', 'zzcsv/Empty', 'zzcsv' ) as $cs_path ) {
    $cs_leaf = substr( $cs_path, strrpos( $cs_path, '/' ) === false ? 0 : strrpos( $cs_path, '/' ) + 1 );
    $cs_hit  = get_terms( array( 'taxonomy' => $cs_tax, 'hide_empty' => false, 'name' => $cs_leaf ) );
    foreach ( (array) $cs_hit as $cs_t ) {
        wp_delete_term( (int) $cs_t->term_id, $cs_tax );
    }
}

foreach ( $cs_made_posts as $cs_id ) {
    wp_delete_post( $cs_id, true );
}

cs_check( 'the staged file is gone', null === vergeml_csv_stashed() );
cs_check( 'the seeded subtree is gone', 0 === count( cs_shape( $cs_tax, 'zzcsv' ) ), wp_json_encode( array_keys( cs_shape( $cs_tax, 'zzcsv' ) ) ) );

cs_say( sprintf( "\n%d/%d passed\n", $GLOBALS['cs_pass'], $GLOBALS['cs_pass'] + $GLOBALS['cs_fail'] ) );

@file_put_contents( __DIR__ . '/csv-last-run.txt', $GLOBALS['cs_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['cs_fail'] > 0 ) {
    exit( 1 );
}
