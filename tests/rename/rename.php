<?php
/**
 *  Naming files after what is in them.
 *
 *      wp eval-file tests/rename/rename.php --allow-root
 *
 *  Three claims, and two of them are about restraint rather than about the
 *  renaming:
 *
 *   - A title somebody wrote is never painted over.
 *   - Renaming a file does not lock it against ever being renamed again --
 *     core/ai-index.php watches post titles and treats a change as a person
 *     naming the file, so a write outside the pipeline flag would poison the
 *     file on the way past.
 *   - Undo puts back what this did, and nothing else.
 *
 *  Runs in mock so it spends nothing, and puts every file it touches back.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_rename_apply' ) ) {
    echo "core/rename.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

$GLOBALS['rn_pass'] = 0;
$GLOBALS['rn_fail'] = 0;
$GLOBALS['rn_log']  = '';

function rn_say( $line ) {
    $GLOBALS['rn_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function rn_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['rn_pass']++;
    } else {
        $GLOBALS['rn_fail']++;
    }
    rn_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

/** A file with a stored description, so the whole path is exercised. */
function rn_make( $filename, $title ) {

    $uploads = wp_upload_dir();
    $path    = trailingslashit( $uploads['path'] ) . $filename;

    $im = imagecreatetruecolor( 200, 150 );
    imagefilledrectangle( $im, 0, 0, 200, 150, imagecolorallocate( $im, 90, 120, 160 ) );
    imagejpeg( $im, $path, 60 );
    imagedestroy( $im );

    $id = wp_insert_attachment( array(
        'post_mime_type' => 'image/jpeg',
        'post_title'     => $filename,
        'post_status'    => 'inherit',
    ), $path );

    wp_update_attachment_metadata( $id, array(
        'width'  => 200,
        'height' => 150,
        'file'   => _wp_relative_upload_path( $path ),
        'sizes'  => array(),
    ) );

    vergeml_index_writing( true );
    vergeml_index_set( $id, array(
        'caption'      => 'A test file for the renaming suite.',
        'alt'          => 'A test file.',
        'title'        => $title,
        'tags'         => array( 'test' ),
        'error'        => '',
        'described_at' => current_time( 'mysql', true ),
    ) );
    vergeml_index_writing( false );

    return (int) $id;
}

wp_set_current_user( 1 );

rn_say( "\nnaming files after what is in them\n\n" );


/* ------------------------------------------------------------- the rename */

rn_say( "A  a file gets the name of what is in it\n" );

$rn_a = rn_make( 'vgml-rename-' . wp_generate_password( 6, false ) . '.jpg', 'Red Synthesizer with Controls' );

rn_check( 'a described file is offered a name', 'Red Synthesizer with Controls' === vergeml_rename_title_for( $rn_a ) );

$rn_done = vergeml_rename_apply( array( $rn_a ) );

rn_check( 'and takes it', 'Red Synthesizer with Controls' === get_post( $rn_a )->post_title, get_post( $rn_a )->post_title );
rn_check( 'reported as one renamed', array( $rn_a ) === $rn_done );

/*
 *  The trap this whole feature walks into if the writing flag is missed.
 *
 *  core/ai-index.php locks the title field whenever a post title changes
 *  outside the pipeline, so renaming a file would immediately mark it as
 *  named-by-a-person -- and it could never be renamed again, including by the
 *  undo. Silent, permanent, and invisible until somebody tried twice.
 */
$rn_row = vergeml_index_get( $rn_a );

rn_check(
    'renaming does not mark the file as named by a person',
    ! in_array( 'title', (array) $rn_row['locked'], true ),
    'locked: ' . implode( ',', (array) $rn_row['locked'] )
);

rn_check( 'a file already wearing the name is not offered it again', '' === vergeml_rename_title_for( $rn_a ) );


/* ------------------------------------------------------------- the undo */

rn_say( "\nB  and the way back\n" );

$rn_back = vergeml_rename_undo();

rn_check( 'undo reports the file', in_array( $rn_a, $rn_back, true ) );
rn_check( 'the old name is back', false !== strpos( get_post( $rn_a )->post_title, 'vgml-rename-' ), get_post( $rn_a )->post_title );
rn_check( 'and the stored original is cleared', '' === (string) get_post_meta( $rn_a, VERGEML_RENAME_META, true ) );

/*
 *  Undo must not overwrite a name somebody chose after the run. Taking back
 *  what this did is one thing; reaching past somebody's later decision to do
 *  it is another.
 */
vergeml_rename_apply( array( $rn_a ) );
wp_update_post( array( 'ID' => $rn_a, 'post_title' => 'A name I chose myself' ) );

vergeml_rename_undo();

rn_check(
    'undo leaves alone a file renamed by hand since',
    'A name I chose myself' === get_post( $rn_a )->post_title,
    get_post( $rn_a )->post_title
);


/* -------------------------------------------------------- somebody's own */

rn_say( "\nC  a name somebody wrote is never painted over\n" );

$rn_b = rn_make( 'vgml-rename-' . wp_generate_password( 6, false ) . '.jpg', 'Computer Desk Setup' );

// The way a person renames a file: through WordPress, outside the pipeline.
wp_update_post( array( 'ID' => $rn_b, 'post_title' => 'Hero image, do not touch' ) );

$rn_row = vergeml_index_get( $rn_b );

rn_check(
    'editing a title by hand locks it',
    in_array( 'title', (array) $rn_row['locked'], true ),
    'locked: ' . implode( ',', (array) $rn_row['locked'] )
);

rn_check( 'so no rename is offered', '' === vergeml_rename_title_for( $rn_b ) );

vergeml_rename_apply( array( $rn_b ) );

rn_check( 'and a run leaves it alone', 'Hero image, do not touch' === get_post( $rn_b )->post_title, get_post( $rn_b )->post_title );

rn_check( 'it is not in the pending list either', ! in_array( $rn_b, vergeml_rename_pending(), true ) );


/* ------------------------------------------------------------ the counts */

rn_say( "\nD  the number on the button is the number it does\n" );

$rn_pending = vergeml_rename_pending();
$rn_sample  = array_slice( $rn_pending, 0, 3 );

if ( ! $rn_sample ) {

    rn_say( "  skipped -- nothing on this library needs renaming\n" );

} else {

    $rn_did = vergeml_rename_apply( $rn_sample );

    rn_check(
        'every file the count included was renamed',
        count( $rn_did ) === count( $rn_sample ),
        count( $rn_did ) . ' of ' . count( $rn_sample )
    );

    rn_check(
        'and the pending count went down by exactly that many',
        count( vergeml_rename_pending() ) === count( $rn_pending ) - count( $rn_sample ),
        count( vergeml_rename_pending() ) . ' left, was ' . count( $rn_pending )
    );

    vergeml_rename_undo();
}


/* -------------------------------------------------------------- tidy up */

wp_delete_attachment( $rn_a, true );
wp_delete_attachment( $rn_b, true );

rn_say( sprintf( "\n%d/%d passed\n", $GLOBALS['rn_pass'], $GLOBALS['rn_pass'] + $GLOBALS['rn_fail'] ) );

@file_put_contents( __DIR__ . '/rename-last-run.txt', $GLOBALS['rn_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['rn_fail'] > 0 ) {
    exit( 1 );
}
