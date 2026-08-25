<?php
/**
 *  Uploads that land in the open folder, and folders that leave as ZIPs.
 *
 *      wp eval-file tests/tree/folder-tools.php --allow-root
 *
 *  The upload half is tested at the hook, with the request shaped exactly the
 *  way the tree's script shapes it -- and, more importantly, with the requests
 *  that must NOT file anything: no folder given, a folder from a taxonomy that
 *  is not ours, a folder that does not exist. An upload that lands somewhere
 *  surprising is worse than one that lands unfiled.
 *
 *  The ZIP half opens the archive it built and reads it back: an endpoint that
 *  returns bytes with a zip header proves almost nothing about what is inside.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_set_current_user( 1 );

$tax = 'media_category';

$GLOBALS['vgml_ok']  = 0;
$GLOBALS['vgml_bad'] = 0;

function t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['vgml_ok']++ : $GLOBALS['vgml_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, $detail ? '  -- ' . $detail : '' );
}

function vgml_fake_upload( $post ) {

	$id = wp_insert_attachment( array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => 'tools probe ' . wp_rand( 1000, 9999 ),
		'post_status'    => 'inherit',
	) );

	// The hook fires on add_attachment, which wp_insert_attachment already ran
	// -- but without our $_POST in place. Set the request up and call the
	// handler the way the hook would.
	$_POST = $post;
	vergeml_file_upload_into_folder( $id );
	$_POST = array();

	return $id;
}

echo "\nuploads into the open folder\n\n";

$term = get_term_by( 'slug', 'press', $tax );
$folder = (int) $term->term_id;

/* --- the request the tree sends ------------------------------------------- */

$id = vgml_fake_upload( array( 'vergeml_folder' => (string) $folder, 'vergeml_folder_tax' => $tax ) );
$in = array_map( 'absint', (array) wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) ) );

t( 'an upload carrying a folder lands in it', in_array( $folder, $in, true ), implode( ',', $in ) );
wp_delete_attachment( $id, true );

/* --- the requests that must file nothing ----------------------------------- */

$cases = array(
	'no folder at all'            => array(),
	'a folder that does not exist' => array( 'vergeml_folder' => '999999', 'vergeml_folder_tax' => $tax ),
	'a taxonomy that is not ours' => array( 'vergeml_folder' => (string) $folder, 'vergeml_folder_tax' => 'category' ),
	'a negative id'               => array( 'vergeml_folder' => '-5', 'vergeml_folder_tax' => $tax ),
);

foreach ( $cases as $label => $post ) {
	$id = vgml_fake_upload( $post );
	$in = (array) wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
	t( "$label files nothing", empty( $in ), implode( ',', array_map( 'absint', $in ) ) );
	wp_delete_attachment( $id, true );
}

/* --- the ZIP ---------------------------------------------------------------- */

echo "\nthe folder as a ZIP\n";

$tmp  = wp_tempnam( 'vgml-zip-test' );
$made = vergeml_zip_folder( $folder, $tax, $tmp );

t( 'the archive was built', ! is_wp_error( $made ), is_wp_error( $made ) ? $made->get_error_code() : $made['added'] . ' files' );

if ( ! is_wp_error( $made ) ) {

	t( 'every file in the folder is in it', $made['added'] === (int) $term->count && 0 === $made['missing'],
		$made['added'] . ' added, ' . $made['missing'] . ' missing' );

	$zip = new ZipArchive();
	$zip->open( $tmp );
	t( 'and the archive opens with that many entries', $zip->numFiles === $made['added'], $zip->numFiles . ' entries' );
	$zip->close();
}

wp_delete_file( $tmp );

/* a parent folder: sub-folders become directories inside the archive */

$parent = get_term_by( 'name', 'Products', $tax );

if ( $parent instanceof WP_Term ) {

	$tmp2  = wp_tempnam( 'vgml-zip-test' );
	$made2 = vergeml_zip_folder( (int) $parent->term_id, $tax, $tmp2 );

	t( 'a parent folder zips its whole branch', ! is_wp_error( $made2 ) && $made2['added'] > (int) $parent->count,
		is_wp_error( $made2 ) ? $made2->get_error_code() : $made2['added'] . ' files from the branch' );

	if ( ! is_wp_error( $made2 ) ) {

		$zip = new ZipArchive();
		$zip->open( $tmp2 );

		$nested = 0;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			if ( false !== strpos( $zip->getNameIndex( $i ), '/' ) ) {
				$nested++;
			}
		}

		$zip->close();

		t( 'with sub-folders as directories inside it', $nested > 0, $nested . ' nested entries' );
	}

	wp_delete_file( $tmp2 );
}

/* --- what it refuses -------------------------------------------------------- */

$empty_term = wp_insert_term( 'zip empty probe ' . wp_rand( 100, 999 ), $tax );
$empty_id   = is_wp_error( $empty_term ) ? 0 : (int) $empty_term['term_id'];

$tmp3 = wp_tempnam( 'vgml-zip-test' );
$none = vergeml_zip_folder( $empty_id, $tax, $tmp3 );

t( 'an empty folder is an error, not an empty file', is_wp_error( $none ) && ! file_exists( $tmp3 ),
	is_wp_error( $none ) ? $none->get_error_code() : 'BUILT' );

if ( file_exists( $tmp3 ) ) {
	wp_delete_file( $tmp3 );
}
if ( $empty_id ) {
	wp_delete_term( $empty_id, $tax );
}

$ghost = vergeml_zip_folder( 999999, $tax, wp_tempnam( 'vgml-zip-test' ) );
t( 'so is a folder that does not exist', is_wp_error( $ghost ) );

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
