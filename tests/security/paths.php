<?php
/**
 *  Files, paths and uploads: nothing outside uploads is moved, read out or deleted.
 *
 *      wp eval-file tests/security/paths.php --allow-root
 *
 *  Phase 5.5 of plans/four-yesses.md. Every file this plugin touches is found
 *  through get_attached_file(), which hands back a `_wp_attached_file` that
 *  begins with a slash verbatim and resolves a `../` inside a relative one. So
 *  the way a path gets out of uploads is not a request parameter -- none of the
 *  routes take one -- but a poisoned attachment row, or a symlink placed inside
 *  uploads that points out. This suite plants all three shapes and drives each
 *  site that renames, packs, reads out or deletes through them.
 *
 *  ## Where the poison goes
 *
 *  Three attachments, one per shape, all pointing at a file this suite created
 *  outside uploads (wp-content/vgml-paths-outside.txt) whose hash is taken
 *  before and after every case:
 *
 *    - traversal:  `_wp_attached_file` = vgml-paths/../../vgml-paths-outside.txt
 *    - absolute:   `_wp_attached_file` = /…/wp-content/vgml-paths-outside.txt
 *    - symlink:    uploads/vgml-paths/link.txt -> the same outside file
 *
 *  Plus one real file in the same fixture folder, so that a site refusing the
 *  three can be told apart from a site refusing everything.
 *
 *  ## The sites
 *
 *    - vergeml_file_rename() and vergeml_file_undo() -- the one rename() the
 *      plugin owns. Called directly: the renamer stays behind
 *      VERGEML_FILE_RENAME and is never enabled here (section A asserts it is
 *      off, route and admin_post handlers both).
 *    - vergeml_zip_folder() -- addFile() reads what the path resolves to and the
 *      archive goes to the browser.
 *    - vergeml_ai_image_payload() -- the bytes leave the site for the service.
 *    - wp_delete_attachment() -- the deletes are WordPress's own, and its
 *      wp_delete_file_from_directory() is asserted here rather than believed.
 *    - vergeml_settings_import() -- the one $_FILES read; it must refuse a
 *      tmp_name that PHP did not put there.
 *
 *  ## What it creates, and puts back
 *
 *  uploads/vgml-paths/ with a real file and a symlink, wp-content/vgml-paths-
 *  outside.txt, four attachment rows with index rows, one term. All removed at
 *  the end; the outside file's hash is compared once more before it goes.
 *  Options it touches (vergeml_file_rename_last, vergeml_backup) are saved and
 *  restored. Nothing here reaches a model or spends a credit.
 *
 *  ## Mutation checks
 *
 *  Run on 2026-09-13 against the box copy, each red at the rows named and
 *  66/66 again once the repo copy was redeployed:
 *
 *    1. `|| false === vergeml_path_in_uploads( $path )` removed from the archive
 *       walk and the describe payload, and `|| ! is_uploaded_file( … )` from the
 *       settings import, together
 *       → 12 red: "one file added, three counted missing" (4 added, 0 missing),
 *         "the archive holds real.jpg and nothing else" (the outside file and
 *         the link packed under their own names), every payload row (a 103-byte
 *         data URL each), and the import's three (eml_settings_wrong_format).
 *    2. the renamer's per-file check made `if ( false )`
 *       → 19 red, first "traversal: vergeml_file_rename() returns false"; the
 *         mutated renamer moved the outside target and the mutated undo moved
 *         it again, so every later "unchanged" row followed. The suite's own
 *         file, removed by hand afterwards; nothing else was touched.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_file_rename' ) || ! function_exists( 'vergeml_path_in_uploads' ) ) {
    echo "the plugin is not loaded, or is older than Phase 5.5 -- inactive, or safe mode?\n";
    exit( 1 );
}

/*
 *  $GLOBALS, not `global`: wp eval-file evaluates this file inside a function.
 *  See tests/security/roles.php for the full reason.
 */
$GLOBALS['p_pass'] = 0;
$GLOBALS['p_fail'] = 0;

function p_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['p_pass']++;
    } else {
        $GLOBALS['p_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

wp_set_current_user( 1 );

echo "\nfiles, paths and uploads\n\n";


/* ------------------------------------------------- A. the renamer is off */

echo "A  the renamer is off\n";

$p_flag = defined( 'VERGEML_FILE_RENAME' ) && VERGEML_FILE_RENAME;

p_check( 'VERGEML_FILE_RENAME is not defined on this site', ! $p_flag );

p_check(
    'the /rename-files route is not registered',
    ! array_key_exists( '/' . VERGEML_REST_NS . '/rename-files', rest_get_server()->get_routes() )
);

/*
 *  The handlers exist (the file is loaded) and are not hooked. Both halves,
 *  because a has_action() of false on a file that never loaded proves nothing.
 */
p_check( 'core/journey.php is loaded', function_exists( 'vergeml_journey_do_file_rename' ) );
p_check( 'admin_post_vergeml_do_file_rename is not hooked', false === has_action( 'admin_post_vergeml_do_file_rename' ) );
p_check( 'admin_post_vergeml_undo_file_rename is not hooked', false === has_action( 'admin_post_vergeml_undo_file_rename' ) );


/* ------------------------------------------------------- B. the fixtures */

echo "\nB  the fixtures\n";

$p_uploads = wp_get_upload_dir();
$p_base    = wp_normalize_path( $p_uploads['basedir'] );
$p_dir     = $p_base . '/vgml-paths';
$p_outside = wp_normalize_path( WP_CONTENT_DIR ) . '/vgml-paths-outside.txt';

/*
 *  Anything a previous run left behind, before this one adds to it. Only the
 *  suite's own names: the folder, the outside file, rows titled zz-vgml-paths.
 */
foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 's' => 'zz-vgml-paths' ) ) as $p_stale ) {
    wp_delete_attachment( (int) $p_stale, true );
}
if ( is_dir( $p_dir ) ) {
    foreach ( (array) scandir( $p_dir ) as $p_f ) {
        if ( '.' !== $p_f && '..' !== $p_f ) {
            @unlink( $p_dir . '/' . $p_f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
        }
    }
    @rmdir( $p_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
}
if ( file_exists( $p_outside ) ) {
    @unlink( $p_outside ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
}

wp_mkdir_p( $p_dir );

// Not JSON on purpose: if the settings import ever reads it, the import must
// fail on format rather than write these bytes into the site's options.
file_put_contents( $p_outside, "vgml-paths outside target " . wp_generate_password( 24, false ) . "\nnot json\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
$p_hash = md5_file( $p_outside );

p_check( 'the outside target exists, outside uploads', file_exists( $p_outside ) && false === vergeml_path_in_uploads( $p_outside ) );

$p_real_path = $p_dir . '/real.jpg';
$p_im = imagecreatetruecolor( 64, 48 );
imagefilledrectangle( $p_im, 0, 0, 64, 48, imagecolorallocate( $p_im, 200, 60, 40 ) );
imagejpeg( $p_im, $p_real_path, 70 );
imagedestroy( $p_im );

$p_link_path = $p_dir . '/link.txt';
$p_linked    = @symlink( $p_outside, $p_link_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

p_check( 'a symlink inside uploads points at the outside target', $p_linked && is_link( $p_link_path ), $p_linked ? $p_link_path : 'symlink() failed on this host' );

/*
 *  The helper itself, on the four shapes, before any site is driven through
 *  it: a site refusing for the wrong reason would otherwise look right.
 */
p_check( 'vergeml_path_in_uploads() accepts the real file', $p_real_path === vergeml_path_in_uploads( $p_real_path ) );
p_check( 'and refuses the traversal', false === vergeml_path_in_uploads( $p_base . '/vgml-paths/../../vgml-paths-outside.txt' ) );
p_check( 'and refuses the absolute path', false === vergeml_path_in_uploads( $p_outside ) );
p_check( 'and refuses the symlink out', ! $p_linked || false === vergeml_path_in_uploads( $p_link_path ) );
p_check( 'and refuses a file that does not exist', false === vergeml_path_in_uploads( $p_dir . '/nothing-here.jpg' ) );

/**
 *  One attachment whose row says $file. wp_insert_attachment() stores it
 *  relative when it starts with basedir, and verbatim otherwise -- which is
 *  exactly how a traversal and an absolute path end up in a real database.
 */
function p_plant( $title, $file, $mime ) {

    $id = wp_insert_attachment( array(
        'post_mime_type' => $mime,
        'post_title'     => 'zz-vgml-paths ' . $title,
        'post_status'    => 'inherit',
    ), $file );

    wp_update_attachment_metadata( $id, array( 'width' => 64, 'height' => 48, 'file' => _wp_relative_upload_path( $file ), 'sizes' => array() ) );

    // An index row with a title, so the renamer has a name to rename to.
    vergeml_index_writing( true );
    vergeml_index_set( $id, array(
        'caption'      => 'A planted row.',
        'alt'          => 'A planted row.',
        'title'        => 'Planted ' . $title,
        'error'        => '',
        'described_at' => current_time( 'mysql', true ),
    ) );
    vergeml_index_writing( false );

    return (int) $id;
}

$p_rows = array(
    'traversal' => p_plant( 'traversal', $p_base . '/vgml-paths/../../vgml-paths-outside.txt', 'text/plain' ),
    'absolute'  => p_plant( 'absolute', $p_outside, 'text/plain' ),
    'symlink'   => p_plant( 'symlink', $p_link_path, 'image/jpeg' ),
);
$p_real = p_plant( 'real', $p_real_path, 'image/jpeg' );

p_check(
    'the traversal row resolves to the outside target',
    realpath( get_attached_file( $p_rows['traversal'] ) ) === realpath( $p_outside ),
    (string) get_attached_file( $p_rows['traversal'] )
);
p_check( 'the absolute row is stored verbatim', $p_outside === wp_normalize_path( get_attached_file( $p_rows['absolute'] ) ) );
p_check( 'the real row resolves inside uploads', $p_real_path === vergeml_path_in_uploads( get_attached_file( $p_real ) ) );

function p_intact( $label ) {
    p_check( $label . ': the outside target is unchanged', file_exists( $GLOBALS['p_outside_path'] ) && md5_file( $GLOBALS['p_outside_path'] ) === $GLOBALS['p_outside_hash'] );
}
$GLOBALS['p_outside_path'] = $p_outside;
$GLOBALS['p_outside_hash'] = $p_hash;


/* ------------------------------------------------------- C. the renamer */

echo "\nC  the renamer refuses to move anything outside uploads\n";

$p_last_was = get_option( VERGEML_FILE_OPTION, null );

foreach ( $p_rows as $p_shape => $p_id ) {

    $p_wanted = vergeml_file_name_for( $p_id );

    p_check( $p_shape . ': a name is worked out, so the refusal below is the path check and not an empty name', '' !== $p_wanted, $p_wanted );
    p_check( $p_shape . ': vergeml_file_rename() returns false', false === vergeml_file_rename( $p_id ) );
    p_intact( $p_shape . ' rename' );
    p_check( $p_shape . ': nothing was created beside the target', ! file_exists( dirname( $p_outside ) . '/' . $p_wanted ) );

    // Undo, with a "before" planted so it has something to put back.
    update_post_meta( $p_id, VERGEML_FILE_BEFORE, 'vgml-paths/before.txt' );
    update_option( VERGEML_FILE_OPTION, array( 'ids' => array( $p_id ), 'when' => time() ), false );

    $p_back = vergeml_file_undo();

    p_check( $p_shape . ': vergeml_file_undo() does not report it', ! in_array( $p_id, (array) $p_back, true ) );
    p_intact( $p_shape . ' undo' );

    delete_post_meta( $p_id, VERGEML_FILE_BEFORE );
}

p_check( 'the symlink itself was not renamed', ! $p_linked || is_link( $p_link_path ) );

// The real file, for contrast: the same call moves it, and undo puts it back.
$p_real_wanted = vergeml_file_name_for( $p_real );
p_check( 'real: the same call renames a file inside uploads', true === vergeml_file_rename( $p_real ) && file_exists( $p_dir . '/' . $p_real_wanted ), $p_real_wanted );
update_option( VERGEML_FILE_OPTION, array( 'ids' => array( $p_real ), 'when' => time() ), false );
vergeml_file_undo();
p_check( 'real: and undo puts it back', file_exists( $p_real_path ) && $p_real_path === wp_normalize_path( get_attached_file( $p_real ) ) );

if ( null === $p_last_was ) {
    delete_option( VERGEML_FILE_OPTION );
} else {
    update_option( VERGEML_FILE_OPTION, $p_last_was, false );
}


/* ------------------------------------------------------- D. the archive */

echo "\nD  the folder archive packs only what is inside uploads\n";

$p_taxes = function_exists( 'vergeml_tree_taxonomies' ) ? vergeml_tree_taxonomies() : array( 'media_category' );
$p_tax   = $p_taxes[0];
$p_term  = wp_insert_term( 'zz-vgml-paths', $p_tax );
$p_term  = is_wp_error( $p_term ) ? 0 : (int) $p_term['term_id'];

p_check( 'a fixture folder exists', $p_term > 0 );

foreach ( array_merge( array_values( $p_rows ), array( $p_real ) ) as $p_id ) {
    wp_set_object_terms( $p_id, array( $p_term ), $p_tax, false );
}

$p_zip_path = wp_tempnam( 'vgml-paths' );
$p_made     = vergeml_zip_folder( $p_term, $p_tax, $p_zip_path );

p_check( 'the archive is built', ! is_wp_error( $p_made ), is_wp_error( $p_made ) ? $p_made->get_error_message() : '' );
p_check( 'one file added, three counted missing', ! is_wp_error( $p_made ) && 1 === (int) $p_made['added'] && 3 === (int) $p_made['missing'], is_wp_error( $p_made ) ? '' : $p_made['added'] . ' added, ' . $p_made['missing'] . ' missing' );

$p_names = array();
$p_leak  = false;
if ( ! is_wp_error( $p_made ) && class_exists( 'ZipArchive' ) ) {
    $p_zip = new ZipArchive();
    if ( true === $p_zip->open( $p_zip_path ) ) {
        for ( $i = 0; $i < $p_zip->numFiles; $i++ ) {
            $p_names[] = $p_zip->getNameIndex( $i );
            if ( md5( (string) $p_zip->getFromIndex( $i ) ) === $p_hash ) {
                $p_leak = true;
            }
        }
        $p_zip->close();
    }
}

p_check( 'the archive holds real.jpg and nothing else', array( 'real.jpg' ) === $p_names, implode( ', ', $p_names ) );
p_check( 'no entry carries the outside target\'s bytes', ! $p_leak );

if ( file_exists( $p_zip_path ) ) {
    wp_delete_file( $p_zip_path );
}


/* ------------------------------------------------- E. the bytes sent out */

echo "\nE  the describe payload never reads outside uploads\n";

$p_outside_b64 = base64_encode( (string) file_get_contents( $p_outside ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

foreach ( $p_rows as $p_shape => $p_id ) {
    $p_payload = vergeml_ai_image_payload( $p_id );
    p_check(
        $p_shape . ': vergeml_ai_image_payload() answers vergeml_ai_no_file',
        is_wp_error( $p_payload ) && 'vergeml_ai_no_file' === $p_payload->get_error_code(),
        is_wp_error( $p_payload ) ? $p_payload->get_error_code() : 'a payload of ' . strlen( (string) $p_payload ) . ' bytes'
    );
    p_check( $p_shape . ': and no payload carries the target\'s bytes', ! is_string( $p_payload ) || false === strpos( $p_payload, $p_outside_b64 ) );
}

$p_payload = vergeml_ai_image_payload( $p_real );
p_check( 'real: the same call returns a data URL for a file inside uploads', is_string( $p_payload ) && 0 === strpos( $p_payload, 'data:image/jpeg;base64,' ) );


/* ------------------------------------------------ F. the settings import */

echo "\nF  the settings import reads only PHP's own upload\n";

if ( ! function_exists( 'vergeml_settings_import' ) ) {
    // Admin-only in the plugin; under WP-CLI is_admin() is false. Its top
    // level is hooks and register_setting(), harmless this late.
    include_once plugin_dir_path( VERGEML_FILE ) . 'core/options-pages.php';
}

p_check( 'core/options-pages.php is loaded', function_exists( 'vergeml_settings_import' ) );

$p_backup_was = get_option( 'vergeml_backup', null );

$_POST['eml-settings-import']       = '1';
$_POST['eml-settings-import-nonce'] = wp_create_nonce( 'eml_settings_import_nonce' );
$_FILES['import_file'] = array(
    'name'     => 'settings.json',
    'type'     => 'application/json',
    'tmp_name' => $p_outside,
    'error'    => 0,
    'size'     => filesize( $p_outside ),
);

vergeml_settings_import();

$p_codes = wp_list_pluck( get_settings_errors( 'eml-settings' ), 'code' );

p_check( 'a tmp_name PHP did not upload is treated as no file', in_array( 'eml_settings_file_absent', $p_codes, true ), implode( ', ', $p_codes ) );
p_check( 'and it was not read', ! in_array( 'eml_settings_wrong_format', $p_codes, true ) && ! in_array( 'eml_settings_imported', $p_codes, true ) );
p_check( 'and no backup was taken on its account', get_option( 'vergeml_backup', null ) === $p_backup_was );

unset( $_POST['eml-settings-import'], $_POST['eml-settings-import-nonce'], $_FILES['import_file'] );

if ( null === $p_backup_was ) {
    delete_option( 'vergeml_backup' );
} else {
    update_option( 'vergeml_backup', $p_backup_was );
}


/* ------------------------------------------------------- G. the import */

echo "\nG  the CSV import takes text, not a path\n";

$p_routes = rest_get_server()->get_routes();
$p_import = isset( $p_routes[ '/' . VERGEML_REST_NS . '/import' ] ) ? $p_routes[ '/' . VERGEML_REST_NS . '/import' ] : array();
$p_args   = array();
foreach ( $p_import as $p_h ) {
    $p_args = array_merge( $p_args, array_keys( (array) ( isset( $p_h['args'] ) ? $p_h['args'] : array() ) ) );
}
$p_args = array_unique( $p_args );

p_check( '/import declares a text argument', in_array( 'text', $p_args, true ), implode( ', ', $p_args ) );
p_check( 'and none named file, path or tmp_name', ! array_intersect( array( 'file', 'path', 'tmp_name', 'filename' ), $p_args ) );


/* ------------------------------------------------ H. the deletes, and tidy */

echo "\nH  WordPress's own delete refuses the same three\n";

foreach ( $p_rows as $p_shape => $p_id ) {
    wp_delete_attachment( $p_id, true );
    p_check( $p_shape . ': the row is gone', ! get_post( $p_id ) );
    p_intact( $p_shape . ' delete' );
}

p_check( 'the symlink survived the delete of its row', ! $p_linked || is_link( $p_link_path ) );

wp_delete_attachment( $p_real, true );
p_check( 'real: the same call deleted the real file', ! file_exists( $p_real_path ) );

if ( $p_term ) {
    wp_delete_term( $p_term, $p_tax );
}

p_intact( 'end of suite' );

if ( is_link( $p_link_path ) || file_exists( $p_link_path ) ) {
    @unlink( $p_link_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
}
@rmdir( $p_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
@unlink( $p_outside ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink

p_check( 'the fixture folder is gone', ! is_dir( $p_dir ) );
p_check( 'the outside target is gone', ! file_exists( $p_outside ) );
p_check( 'no zz-vgml-paths rows remain', 0 === count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 's' => 'zz-vgml-paths' ) ) ) );

printf( "\n%d/%d passed\n\n", $GLOBALS['p_pass'], $GLOBALS['p_pass'] + $GLOBALS['p_fail'] );

exit( $GLOBALS['p_fail'] > 0 ? 1 : 0 );
