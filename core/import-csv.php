<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  Folders as a file.
 *
 *  The answer to "how do I set up two hundred folders without clicking two
 *  hundred times", and to its mirror, "how do I get my structure out of here".
 *
 *  Import is deliberately not a second importer. `vergeml_import_read()`
 *  normalises seven folder plugins into one shape -- folders as
 *  `id => array( name, parent )`, files as `folder id => array of ids` -- and
 *  everything after it, the plan, the chunked run and the undo, works on that
 *  shape without knowing where it came from. So a CSV is an eighth source that
 *  answers the same two questions, and it inherits all of that unchanged.
 *
 *  Export is the same walk in reverse, which is what makes the round trip
 *  testable: export a tree, wipe it, import the file, and the tree that comes
 *  back has to be the one that went in.
 *
 *  The format is one row per assignment:
 *
 *      folder,attachment_id,filename
 *      Clients/Acme/2024,1841,acme-hero.jpg
 *      Clients/Acme/Logos,,
 *
 *  Slashes separate the levels, so a nested tree survives a round trip. A row
 *  with no attachment id is a folder that has no files in it -- without those,
 *  an empty folder would vanish on the way out and never come back. The
 *  filename is not read on import; it is there so the file can be read by a
 *  person, which is most of the point of choosing CSV over JSON.
 */


/** Where a staged upload waits between being parsed and being imported. Not
 *  autoloaded: it can be a megabyte and is read on one screen. */
const VERGEML_CSV_OPTION = 'vergeml_import_csv';

/** Refused above this many rows. A file this size is a mistake or an attack,
 *  and either way the honest answer is to say so rather than to run out of
 *  memory halfway through parsing it. */
const VERGEML_CSV_MAX_ROWS = 200000;

/** How deep a path may nest. Folders are terms, and a pathological depth makes
 *  the ordering walk in vergeml_import_order() quadratic for no benefit. */
const VERGEML_CSV_MAX_DEPTH = 12;


/* --------------------------------------------------------------- exporting */

/**
 *  vergeml_csv_export_rows
 *
 *  Every folder, and every file in it, as rows. Folders with nothing in them
 *  get one row with an empty id so they survive the round trip.
 */
function vergeml_csv_export_rows( $taxonomy ) {

    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ) );

    if ( is_wp_error( $terms ) ) {
        return $terms;
    }

    $names  = array();
    $parent = array();

    foreach ( $terms as $term ) {
        $names[ (int) $term->term_id ]  = $term->name;
        $parent[ (int) $term->term_id ] = (int) $term->parent;
    }

    $rows = array( array( 'folder', 'attachment_id', 'filename' ) );

    // Sorted by path so the file reads as a tree rather than as term-id order,
    // which is the order they happened to be created in.
    $paths = array();

    foreach ( array_keys( $names ) as $id ) {
        $paths[ $id ] = vergeml_csv_path( $id, $names, $parent );
    }

    asort( $paths );

    foreach ( $paths as $id => $path ) {

        $files = get_objects_in_term( array( $id ), $taxonomy );

        if ( is_wp_error( $files ) || empty( $files ) ) {
            $rows[] = array( $path, '', '' );
            continue;
        }

        sort( $files );

        foreach ( $files as $attachment_id ) {

            $attachment_id = (int) $attachment_id;

            if ( 'attachment' !== get_post_type( $attachment_id ) ) {
                continue;
            }

            $rows[] = array(
                $path,
                (string) $attachment_id,
                wp_basename( (string) get_attached_file( $attachment_id ) ),
            );
        }
    }

    return $rows;
}


/** A term's full path, built by walking up. Guarded against a cycle in the
 *  parent column, which should not exist and has been seen in the wild after a
 *  half-finished import from another plugin. */
function vergeml_csv_path( $id, $names, $parent ) {

    $parts = array();
    $seen  = array();

    while ( $id && isset( $names[ $id ] ) && ! isset( $seen[ $id ] ) ) {
        $seen[ $id ] = true;
        array_unshift( $parts, str_replace( '/', '-', $names[ $id ] ) );
        $id = isset( $parent[ $id ] ) ? $parent[ $id ] : 0;
    }

    return implode( '/', $parts );
}


function vergeml_csv_line( $fields ) {

    $out = array();

    foreach ( $fields as $field ) {
        $field = (string) $field;
        // Quote whenever the field could otherwise be misread, and double any
        // quote inside it -- RFC 4180, which is what every spreadsheet expects.
        if ( preg_match( '/[",\r\n]/', $field ) ) {
            $field = '"' . str_replace( '"', '""', $field ) . '"';
        }
        $out[] = $field;
    }

    return implode( ',', $out ) . "\r\n";
}


add_action( 'admin_post_vergeml_export_csv', 'vergeml_csv_download' );

function vergeml_csv_download() {

    if ( ! vergeml_can_import() ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to export folders.', 'vergelabs-media-library' ) );
    }

    check_admin_referer( 'vergeml_export_csv' );

    $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';

    if ( ! in_array( $taxonomy, vergeml_tree_taxonomies(), true ) ) {
        wp_die( esc_html__( 'That is not a folder taxonomy.', 'vergelabs-media-library' ) );
    }

    $rows = vergeml_csv_export_rows( $taxonomy );

    if ( is_wp_error( $rows ) ) {
        wp_die( esc_html( $rows->get_error_message() ) );
    }

    $name = 'folders-' . $taxonomy . '-' . gmdate( 'Y-m-d' ) . '.csv';

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $name . '"' );

    /*
     *  A BOM, and only for this. Excel on Windows reads a CSV as the system
     *  code page unless one is present, which turns every accented folder name
     *  into mojibake in the one program most people will open this with.
     */
    echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    foreach ( $rows as $row ) {
        echo vergeml_csv_line( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    exit;
}


/* --------------------------------------------------------------- importing */

/**
 *  vergeml_csv_parse
 *
 *  Text in, the importer's own shape out, plus everything that was wrong with
 *  it. Nothing is written here and nothing is refused for one bad row: a file
 *  with fifty unknown ids in twenty thousand rows should import the other
 *  19,950 and say what it skipped, because the alternative is a person editing
 *  a spreadsheet by hand to find out which line offended.
 */
function vergeml_csv_parse( $text ) {

    $text = (string) $text;

    // Excel's BOM again, from the other side.
    if ( 0 === strpos( $text, "\xEF\xBB\xBF" ) ) {
        $text = substr( $text, 3 );
    }

    if ( '' === trim( $text ) ) {
        return new WP_Error( 'vergeml_csv_empty', __( 'That file is empty.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

    if ( false === $handle ) {
        return new WP_Error( 'vergeml_csv_unreadable', __( 'The file could not be read.', 'vergelabs-media-library' ), array( 'status' => 500 ) );
    }

    fwrite( $handle, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
    rewind( $handle );

    $folders  = array();
    $files    = array();
    $ids      = array();
    $problems = array();
    $seen     = array();
    $rows     = 0;
    $line     = 0;

    /*
     *  The fifth argument is not decoration. PHP 8.5 deprecates calling
     *  fgetcsv() without it, and a twenty-thousand-row import would write
     *  twenty thousand deprecation lines into somebody's debug.log -- which is
     *  precisely the bug imagedestroy() produced here in August.
     *
     *  Empty rather than a backslash, because that is RFC 4180 and where PHP
     *  is going: a backslash in a folder name is a backslash, not an escape.
     */
    while ( false !== ( $cells = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {

        $line++;

        if ( null === $cells || array( null ) === $cells ) {
            continue;
        }

        $rows++;

        if ( $rows > VERGEML_CSV_MAX_ROWS ) {
            fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            return new WP_Error(
                'vergeml_csv_too_big',
                sprintf(
                    /* translators: %s: the row limit. */
                    __( 'That file has more than %s rows, which is more than this can import in one go.', 'vergelabs-media-library' ),
                    number_format_i18n( VERGEML_CSV_MAX_ROWS )
                ),
                array( 'status' => 400 )
            );
        }

        $path = isset( $cells[0] ) ? trim( (string) $cells[0] ) : '';
        $raw  = isset( $cells[1] ) ? trim( (string) $cells[1] ) : '';

        if ( '' === $path ) {
            continue;
        }

        // The header row, if the file has one. Recognised rather than assumed:
        // a file written by hand may not have one, and skipping the first row
        // blindly would eat somebody's first folder.
        if ( 1 === $line && 'folder' === strtolower( $path ) ) {
            $rows--;
            continue;
        }

        $folder_id = vergeml_csv_folder( $path, $folders );

        if ( is_wp_error( $folder_id ) ) {
            $problems[] = sprintf(
                /* translators: 1: line number, 2: what was wrong. */
                __( 'Line %1$d: %2$s', 'vergelabs-media-library' ),
                $line,
                $folder_id->get_error_message()
            );
            continue;
        }

        if ( '' === $raw ) {
            // A folder with no files. Already created above, which is the point.
            continue;
        }

        if ( ! ctype_digit( $raw ) ) {
            $problems[] = sprintf(
                /* translators: 1: line number, 2: the value found. */
                __( 'Line %1$d: “%2$s” is not an attachment id.', 'vergelabs-media-library' ),
                $line,
                $raw
            );
            continue;
        }

        $attachment_id = (int) $raw;

        // The same file twice in the same folder is not an error, it is a
        // duplicate row; the same file in two folders is allowed and normal.
        $mark = $folder_id . ':' . $attachment_id;

        if ( isset( $seen[ $mark ] ) ) {
            continue;
        }

        $seen[ $mark ] = true;
        $ids[ $attachment_id ] = true;
        $files[ $folder_id ][] = $attachment_id;
    }

    fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

    /*
     *  Which of those ids are real, in one query rather than one per row. A
     *  file naming ids from another site is the most likely mistake somebody
     *  makes with this feature, and it has to be caught here -- assigning a
     *  term to a post id that belongs to a page would file that page in a media
     *  folder.
     */
    $unknown = vergeml_csv_reject_unknown( array_keys( $ids ), $files );

    if ( $unknown > 0 ) {
        $problems[] = sprintf(
            /* translators: %s: how many ids were not attachments on this site. */
            _n(
                '%s row named an id that is not an attachment on this site, and was skipped.',
                '%s rows named an id that is not an attachment on this site, and were skipped.',
                $unknown,
                'vergelabs-media-library'
            ),
            number_format_i18n( $unknown )
        );
    }

    if ( empty( $folders ) ) {
        return new WP_Error( 'vergeml_csv_no_folders', __( 'No folders were found in that file. The first column is the folder path.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $out = array( 'folders' => array(), 'files' => $files );

    foreach ( $folders as $path => $folder ) {
        $out['folders'][ $folder['id'] ] = array(
            'name'   => $folder['name'],
            'parent' => $folder['parent'],
        );
    }

    // Only the first few, or a badly wrong file produces a wall of text nobody
    // reads. The count above already says how many there were in total.
    $out['problems'] = array_slice( $problems, 0, 20 );
    $out['problem_count'] = count( $problems );

    return $out;
}


/**
 *  Turns `Clients/Acme/2024` into a folder id, creating every level above it
 *  that does not exist yet. Ids are this file's own and mean nothing outside
 *  it, exactly like FileBird's row ids mean nothing outside FileBird.
 */
function vergeml_csv_folder( $path, &$folders ) {

    $parts = array();

    foreach ( explode( '/', $path ) as $part ) {
        $part = trim( $part );
        if ( '' !== $part ) {
            $parts[] = $part;
        }
    }

    if ( empty( $parts ) ) {
        return new WP_Error( 'vergeml_csv_bad_path', __( 'the folder path is empty.', 'vergelabs-media-library' ) );
    }

    if ( count( $parts ) > VERGEML_CSV_MAX_DEPTH ) {
        return new WP_Error(
            'vergeml_csv_deep',
            sprintf(
                /* translators: %d: the maximum folder depth. */
                __( 'that path is more than %d folders deep.', 'vergelabs-media-library' ),
                VERGEML_CSV_MAX_DEPTH
            )
        );
    }

    $parent = 0;
    $walked = '';

    foreach ( $parts as $part ) {

        $walked = '' === $walked ? $part : $walked . '/' . $part;

        if ( ! isset( $folders[ $walked ] ) ) {
            $folders[ $walked ] = array(
                // Sequential and starting at 1: the importer treats these as
                // opaque source ids and only cares that parents refer to them.
                'id'     => count( $folders ) + 1,
                'name'   => $part,
                'parent' => $parent,
            );
        }

        $parent = $folders[ $walked ]['id'];
    }

    return $parent;
}


/**
 *  Drops every id that is not an attachment on this site, in one query, and
 *  returns how many assignments were dropped.
 */
function vergeml_csv_reject_unknown( $ids, &$files ) {

    global $wpdb;

    if ( empty( $ids ) ) {
        return 0;
    }

    $real = array();

    // In batches: a single IN () with twenty thousand members is a statement
    // some MySQL configurations refuse outright.
    foreach ( array_chunk( $ids, 2000 ) as $chunk ) {

        $marks = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $found = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ID IN ({$marks})",
            $chunk
        ) );
        // phpcs:enable

        foreach ( (array) $found as $id ) {
            $real[ (int) $id ] = true;
        }
    }

    $dropped = 0;

    foreach ( $files as $folder_id => $list ) {

        $keep = array();

        foreach ( $list as $id ) {
            if ( isset( $real[ $id ] ) ) {
                $keep[] = $id;
            } else {
                $dropped++;
            }
        }

        if ( empty( $keep ) ) {
            unset( $files[ $folder_id ] );
        } else {
            $files[ $folder_id ] = $keep;
        }
    }

    return $dropped;
}


/* ------------------------------------------------------- the staged upload */

function vergeml_csv_stash( $parsed ) {
    update_option( VERGEML_CSV_OPTION, $parsed, false );
}

function vergeml_csv_stashed() {
    $stashed = get_option( VERGEML_CSV_OPTION, array() );
    return is_array( $stashed ) && ! empty( $stashed['folders'] ) ? $stashed : null;
}

function vergeml_csv_clear() {
    delete_option( VERGEML_CSV_OPTION );
}


/** The reader the importer calls. Same contract as every other source. */
function vergeml_import_read_csv() {

    $stashed = vergeml_csv_stashed();

    if ( null === $stashed ) {
        return array( 'folders' => array(), 'files' => array() );
    }

    return array(
        'folders' => $stashed['folders'],
        'files'   => isset( $stashed['files'] ) ? $stashed['files'] : array(),
    );
}


/*
 *  Listed only once a file has been staged. A source that is always there but
 *  always empty would sit on the import screen saying "nothing to import from
 *  CSV", which reads as a broken feature rather than as one waiting for a file.
 */
add_filter( 'vergeml_import_sources', 'vergeml_csv_source' );

function vergeml_csv_source( $sources ) {

    if ( null === vergeml_csv_stashed() ) {
        return $sources;
    }

    $sources['csv'] = array(
        'name'   => __( 'A CSV file', 'vergelabs-media-library' ),
        'author' => __( 'uploaded here', 'vergelabs-media-library' ),
        'kind'   => 'csv',
    );

    return $sources;
}
