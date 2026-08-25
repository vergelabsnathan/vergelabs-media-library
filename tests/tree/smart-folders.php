<?php
/**
 *  Smart folders: the questions, and the scan that makes two of them
 *  answerable.
 *
 *      wp eval-file tests/tree/smart-folders.php --allow-root
 *
 *  The scan is the part that can lie. "Unused" is a claim somebody will act on
 *  with the delete key, so the test builds one attachment used each way a post
 *  can use one -- embedded in content by id, embedded by URL, set as the
 *  featured image, attached as a child -- plus one used no way at all, and the
 *  scan must sort exactly those five correctly. A scan that calls a used image
 *  unused is not a feature, it is a data-loss assistant.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_set_current_user( 1 );

$GLOBALS['vgml_ok']  = 0;
$GLOBALS['vgml_bad'] = 0;

function t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['vgml_ok']++ : $GLOBALS['vgml_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, $detail ? '  -- ' . $detail : '' );
}

function vgml_scan_to_end() {
	$result = vergeml_smart_scan_step( null );
	$guard  = 0;
	while ( empty( $result['complete'] ) && ! empty( $result['resume'] ) && $guard++ < 500 ) {
		$result = vergeml_smart_scan_step( $result['resume'] );
	}
	return $result;
}

function vgml_unused_flag( $id ) {
	return get_post_meta( $id, VERGEML_META_UNUSED, true );
}

echo "\nsmart folders\n\n";

/* --- five attachments, used four ways and no way --------------------------- */

$files = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 5, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
t( 'five attachments to arrange', count( $files ) === 5 );

list( $by_id, $by_url, $featured, $attached, $untouched ) = array_map( 'intval', $files );

$url = wp_get_attachment_url( $by_url );

$p1 = wp_insert_post( array(
	'post_title'   => 'smart probe content',
	'post_type'    => 'post',
	'post_status'  => 'publish',
	'post_content' => '<img class="wp-image-' . $by_id . '" src="x.jpg" /> and a pasted file: ' . $url,
) );

$p2 = wp_insert_post( array( 'post_title' => 'smart probe featured', 'post_type' => 'post', 'post_status' => 'publish' ) );
set_post_thumbnail( $p2, $featured );

$old_parent = (int) get_post_field( 'post_parent', $attached );
wp_update_post( array( 'ID' => $attached, 'post_parent' => $p2 ) );

/* --- the scan --------------------------------------------------------------- */

$done = vgml_scan_to_end();

t( 'the scan runs to completion', ! empty( $done['complete'] ), wp_json_encode( array( $done['phase'] ?? '?', $done['done'] ?? '?' ) ) );
t( 'and reports every count when it finishes', isset( $done['counts']['unused'] ) && null !== $done['counts']['unused'] );

t( 'embedded by id is used', '0' === vgml_unused_flag( $by_id ) );
t( 'embedded by pasted URL is used', '0' === vgml_unused_flag( $by_url ) );
t( 'the featured image is used', '0' === vgml_unused_flag( $featured ) );
t( 'the attached child is used', '0' === vgml_unused_flag( $attached ) );
t( 'the untouched one is unused', '1' === vgml_unused_flag( $untouched ) );

/* --- the size index came along ---------------------------------------------- */

$size = (int) get_post_meta( $untouched, VERGEML_META_FILESIZE, true );
$real = (int) filesize( get_attached_file( $untouched ) );
t( 'the same walk indexed file sizes', $size > 0 && $size === $real, $size . ' bytes' );

/* --- the counts -------------------------------------------------------------- */

$counts = vergeml_smart_counts();

t( 'unused counts the unused', $counts['unused'] >= 1, $counts['unused'] );
t( 'and not the used ones', $counts['unused'] <= wp_count_posts( 'attachment' )->inherit - 4,
	$counts['unused'] . ' of ' . wp_count_posts( 'attachment' )->inherit );

// Alt text: give one image an alt and the count must fall by exactly one.
$before = (int) $counts['no-alt'];
update_post_meta( $untouched, '_wp_attachment_image_alt', 'A described image' );
$after = (int) vergeml_smart_counts()['no-alt'];
t( 'missing-alt drops by one when one is described', $after === $before - 1, $before . ' -> ' . $after );
delete_post_meta( $untouched, '_wp_attachment_image_alt' );

// Large: nothing here is over a megabyte until we make one.
t( 'nothing small counts as large', 0 === (int) $counts['large'], $counts['large'] );

$uploads = wp_get_upload_dir();
$big     = trailingslashit( $uploads['path'] ) . 'vgml-smart-big.jpg';
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- building a fixture file.
file_put_contents( $big, str_repeat( 'x', (int) ( 1.5 * MB_IN_BYTES ) ) );

$big_id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'smart big probe', 'post_status' => 'inherit' ), $big );

t( 'a fresh upload is stamped without a rescan', '1' === vgml_unused_flag( $big_id ),
	var_export( vgml_unused_flag( $big_id ), true ) );
t( 'including its size', (int) get_post_meta( $big_id, VERGEML_META_FILESIZE, true ) > MB_IN_BYTES );
t( 'so large finds it live', 1 === (int) vergeml_smart_counts()['large'] );

/* --- the query translations --------------------------------------------------- */

echo "\nthe translations\n";

$_POST['query'] = array( 'vergeml_smart' => 'no-alt' );
$mapped = vergeml_smart_grid_query( array( 'post_type' => 'attachment' ) );
unset( $_POST['query'] );

t( 'the grid filter maps a smart key', isset( $mapped['meta_query'] ) && 'image' === $mapped['post_mime_type'] );

$_POST['query'] = array( 'vergeml_smart' => 'drop-tables' );
$unmapped = vergeml_smart_grid_query( array( 'post_type' => 'attachment' ) );
unset( $_POST['query'] );

t( 'an unknown key changes nothing', array( 'post_type' => 'attachment' ) === $unmapped );

// The unused query returns exactly the stamped set.
$q = new WP_Query( array_merge(
	array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids' ),
	vergeml_smart_query_args( 'unused' )
) );

t( 'the unused query returns the stamped set', (int) $q->found_posts === (int) vergeml_smart_counts()['unused'],
	$q->found_posts . ' vs ' . vergeml_smart_counts()['unused'] );
t( 'and not the used ones', ! in_array( $featured, array_map( 'intval', $q->posts ), true ) );

/* tidy */
wp_delete_attachment( $big_id, true );
wp_delete_post( $p1, true );
wp_delete_post( $p2, true );
wp_update_post( array( 'ID' => $attached, 'post_parent' => $old_parent ) );

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
