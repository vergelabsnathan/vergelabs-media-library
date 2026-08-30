<?php
/**
 *  Renaming the file on disk, and putting it back.
 *
 *      wp eval-file tests/rename/files.php --allow-root
 *
 *  This is the dangerous half of renaming: a filename is a URL, and moving it
 *  breaks every <img src> already written into a page. The claims worth
 *  defending are therefore mostly about what does NOT happen.
 *
 *   - The file and every generated size move together, or none of them do.
 *   - A post that referenced the old name references the new one afterwards --
 *     in src and in srcset, which is why the rewrite works on the stem.
 *   - Undo restores the files, the metadata AND the references.
 *   - Nothing runs at all before the usage scan has finished, because
 *     renaming a file without knowing what points at it is the thing this
 *     feature exists to avoid.
 *
 *  Builds its own attachment and its own post, and removes both.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_file_rename' ) ) {
    echo "core/rename-file.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

$GLOBALS['fr_pass'] = 0;
$GLOBALS['fr_fail'] = 0;
$GLOBALS['fr_log']  = '';

function fr_say( $line ) {
    $GLOBALS['fr_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function fr_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['fr_pass']++;
    } else {
        $GLOBALS['fr_fail']++;
    }
    fr_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

wp_set_current_user( 1 );

fr_say( "\nrenaming the file itself\n\n" );

/* ------------------------------------------------------------ the fixture */

$fr_uploads = wp_upload_dir();
$fr_stem    = 'vgml-file-test-' . wp_generate_password( 6, false );
$fr_path    = trailingslashit( $fr_uploads['path'] ) . $fr_stem . '.jpg';

$fr_im = imagecreatetruecolor( 400, 300 );
imagefilledrectangle( $fr_im, 0, 0, 400, 300, imagecolorallocate( $fr_im, 40, 90, 160 ) );
imagejpeg( $fr_im, $fr_path, 70 );
imagedestroy( $fr_im );

$fr_id = wp_insert_attachment( array(
    'post_mime_type' => 'image/jpeg',
    'post_title'     => $fr_stem,
    'post_status'    => 'inherit',
), $fr_path );

// A generated size beside it, because the sizes are the half that gets missed.
$fr_thumb = trailingslashit( $fr_uploads['path'] ) . $fr_stem . '-150x150.jpg';
copy( $fr_path, $fr_thumb );

wp_update_attachment_metadata( $fr_id, array(
    'width'  => 400,
    'height' => 300,
    'file'   => _wp_relative_upload_path( $fr_path ),
    'sizes'  => array(
        'thumbnail' => array( 'file' => $fr_stem . '-150x150.jpg', 'width' => 150, 'height' => 150, 'mime-type' => 'image/jpeg' ),
    ),
) );

vergeml_index_writing( true );
vergeml_index_set( $fr_id, array(
    'caption'      => 'A test file.',
    'alt'          => 'A test file.',
    'title'        => 'Blue Test Rectangle',
    'error'        => '',
    'described_at' => current_time( 'mysql', true ),
) );
vergeml_index_writing( false );

// A post that uses it, in src and in srcset -- which is the shape a real
// WordPress image block has, and the reason the rewrite works on the stem.
$fr_url = trailingslashit( $fr_uploads['url'] );

$fr_post = wp_insert_post( array(
    'post_title'   => 'A page using the test file',
    'post_status'  => 'publish',
    'post_content' => '<img src="' . $fr_url . $fr_stem . '.jpg" srcset="' . $fr_url . $fr_stem . '-150x150.jpg 150w" alt="">',
) );

update_post_meta( $fr_id, VERGEML_META_USED_IN, (string) $fr_post );


/* ------------------------------------------------------------- the refusal */

fr_say( "A  nothing happens without the usage scan\n" );

$fr_scan_was = get_option( VERGEML_SCAN_OPTION, array() );

delete_option( VERGEML_SCAN_OPTION );

fr_check(
    'with no scan, nothing is offered',
    array() === vergeml_file_pending(),
    'renaming a file without knowing what points at it is the whole risk'
);

update_option( VERGEML_SCAN_OPTION, array( 'finished' => time() ), false );


/* -------------------------------------------------------------- the rename */

fr_say( "\nB  the file, its sizes and the page that uses it\n" );

$fr_wanted = vergeml_file_name_for( $fr_id );

fr_check( 'a name is worked out from the description', 'blue-test-rectangle.jpg' === $fr_wanted, $fr_wanted );

fr_check( 'and it is renamed', true === vergeml_file_rename( $fr_id ) );

$fr_now = get_attached_file( $fr_id );

fr_check( 'the file on disk has the new name', 'blue-test-rectangle.jpg' === wp_basename( $fr_now ), wp_basename( (string) $fr_now ) );
fr_check( 'and it is really there', file_exists( $fr_now ) );
fr_check( 'the old one is gone', ! file_exists( $fr_path ) );

$fr_meta = wp_get_attachment_metadata( $fr_id );

fr_check(
    'the generated size moved with it',
    file_exists( trailingslashit( $fr_uploads['path'] ) . 'blue-test-rectangle-150x150.jpg' )
);

fr_check(
    'and the metadata knows where it went',
    isset( $fr_meta['sizes']['thumbnail']['file'] ) && 'blue-test-rectangle-150x150.jpg' === $fr_meta['sizes']['thumbnail']['file'],
    isset( $fr_meta['sizes']['thumbnail']['file'] ) ? $fr_meta['sizes']['thumbnail']['file'] : '(gone)'
);

$fr_content = get_post( $fr_post )->post_content;

fr_check( 'the page points at the new name', false !== strpos( $fr_content, 'blue-test-rectangle.jpg' ) );
fr_check( 'the srcset does too', false !== strpos( $fr_content, 'blue-test-rectangle-150x150.jpg' ) );
fr_check( 'and nothing still points at the old one', false === strpos( $fr_content, $fr_stem ) );

fr_check( 'it is not offered a second time', '' === vergeml_file_name_for( $fr_id ) );


/* ---------------------------------------------------------------- the undo */

fr_say( "\nC  and all the way back\n" );

update_option( VERGEML_FILE_OPTION, array( 'ids' => array( $fr_id ), 'when' => time() ), false );

$fr_back = vergeml_file_undo();

fr_check( 'undo reports the file', in_array( $fr_id, $fr_back, true ) );
fr_check( 'the original name is back on disk', file_exists( $fr_path ), $fr_path );
fr_check( 'and its size with it', file_exists( $fr_thumb ) );
fr_check( 'the attachment points at it again', $fr_path === get_attached_file( $fr_id ), (string) get_attached_file( $fr_id ) );

$fr_content = get_post( $fr_post )->post_content;

fr_check( 'and so does the page', false !== strpos( $fr_content, $fr_stem . '.jpg' ) );
fr_check( 'including the srcset', false !== strpos( $fr_content, $fr_stem . '-150x150.jpg' ) );


/* -------------------------------------------------------------- tidy up */

wp_delete_post( $fr_post, true );
wp_delete_attachment( $fr_id, true );

@unlink( $fr_path );   // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
@unlink( $fr_thumb );  // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink

if ( $fr_scan_was ) {
    update_option( VERGEML_SCAN_OPTION, $fr_scan_was, false );
}

fr_say( sprintf( "\n%d/%d passed\n", $GLOBALS['fr_pass'], $GLOBALS['fr_pass'] + $GLOBALS['fr_fail'] ) );

@file_put_contents( __DIR__ . '/files-last-run.txt', $GLOBALS['fr_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['fr_fail'] > 0 ) {
    exit( 1 );
}
