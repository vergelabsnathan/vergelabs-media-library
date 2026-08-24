<?php
/**
 *  Importing folders from another plugin.
 *
 *      wp eval-file tests/tree/import.php --allow-root
 *
 *  Run against the real FileBird tables on the test box: 200 folders and 16,000
 *  assignments, made by FileBird itself rather than by a fixture pretending to be
 *  it. An importer tested against data it also wrote is testing its own opinion
 *  of the format.
 *
 *  The two things that matter here are not "did it import" but:
 *
 *    - undo takes back exactly what the import added, and nothing that was
 *      already there. A folder that existed before must survive, and a file
 *      somebody filed by hand afterwards must stay filed.
 *    - the source is never touched. The whole safety argument for imports is
 *      that the other plugin still has its data if this goes wrong.
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

function count_terms( $tax ) {
	$t = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ) );
	return is_wp_error( $t ) ? 0 : count( $t );
}

function fb_counts() {
	global $wpdb;
	// phpcs:disable
	return array(
		(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fbv" ),
		(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fbv_attachment_folder" ),
	);
	// phpcs:enable
}

echo "\nimporting from FileBird\n\n";

$read = vergeml_import_read( 'filebird' );
t( 'FileBird is readable', ! is_wp_error( $read ) && count( $read['folders'] ) > 0,
	is_wp_error( $read ) ? $read->get_error_code() : count( $read['folders'] ) . ' folders' );

$source_before = fb_counts();
$terms_before  = count_terms( $tax );

/*
 *  A folder that already exists by the same name, so the merge path is exercised
 *  rather than assumed, and a file filed into it by hand -- which undo must not
 *  touch.
 */
$first = null;
foreach ( $read['folders'] as $id => $f ) {
	if ( empty( $f['parent'] ) ) {
		$first = $f['name'];
		break;
	}
}

$decoy = wp_insert_term( $first, $tax );
$decoy_id = is_wp_error( $decoy ) ? (int) $decoy->get_error_data()['term_id'] : (int) $decoy['term_id'];
t( 'a folder with a clashing name exists', $decoy_id > 0, $first );

$files = get_posts( array( 'post_type' => 'attachment', 'posts_per_page' => 1, 'fields' => 'ids' ) );
$hand_filed = (int) $files[0];
wp_set_object_terms( $hand_filed, array( $decoy_id ), $tax, true );
t( 'and a file filed into it by hand', in_array( $decoy_id, array_map( 'absint', wp_get_object_terms( $hand_filed, $tax, array( 'fields' => 'ids' ) ) ), true ) );

/* --- the plan ---------------------------------------------------------- */

$plan = vergeml_import_plan( 'filebird', $tax );
t( 'a plan can be made', ! is_wp_error( $plan ) );
t( 'the plan counts the folders', $plan['folders'] === count( $read['folders'] ), $plan['folders'] . ' folders' );
t( 'the plan spots the clash as a merge', $plan['merge'] >= 1, $plan['merge'] . ' merges, ' . $plan['create'] . ' new' );
t( 'the plan changed nothing', count_terms( $tax ) === $terms_before + 1, 'terms still ' . count_terms( $tax ) );

/* --- the import -------------------------------------------------------- */

$result = vergeml_import_run( 'filebird', $tax );
t( 'the import ran', ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_code() : '' );

if ( ! is_wp_error( $result ) ) {

	t( 'it created what the plan said', $result['created'] === $plan['create'],
		$result['created'] . ' created, plan said ' . $plan['create'] );
	t( 'it merged rather than duplicating', $result['merged'] >= 1, $result['merged'] . ' merged' );
	t( 'it filed a lot of files', $result['assignments'] > 10000, $result['assignments'] . ' assignments' );

	t( 'the clashing folder was not duplicated',
		count( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'name' => $first ) ) ) === 1 );

	// Order carried across from FileBird's ord column.
	$with_order = 0;
	foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ) ) as $tid ) {
		if ( get_term_meta( $tid, VERGEML_TERM_ORDER, true ) !== '' ) {
			$with_order++;
		}
	}
	t( 'folder order came across', $with_order > 0, $with_order . ' folders carry an order' );

	/* --- the source is untouched --------------------------------------- */

	t( 'FileBird still has its folders', fb_counts() === $source_before,
		implode( '/', fb_counts() ) . ' vs ' . implode( '/', $source_before ) );

	/* --- undo ----------------------------------------------------------- */

	$undo = vergeml_import_undo( $result['id'] );
	t( 'undo ran', ! is_wp_error( $undo ), is_wp_error( $undo ) ? $undo->get_error_code() : '' );

	t( 'the folders it created are gone', count_terms( $tax ) === $terms_before + 1,
		count_terms( $tax ) . ' terms, expected ' . ( $terms_before + 1 ) );

	// The important one: what was here before is still here.
	t( 'the folder that existed before survived', get_term( $decoy_id, $tax ) instanceof WP_Term );
	t( 'the hand-filed file is still filed',
		in_array( $decoy_id, array_map( 'absint', wp_get_object_terms( $hand_filed, $tax, array( 'fields' => 'ids' ) ) ), true ) );

	t( 'FileBird is still untouched after undo', fb_counts() === $source_before );

	t( 'a second undo of the same import is refused',
		is_wp_error( vergeml_import_undo( $result['id'] ) ) );
}

/* tidy */
wp_remove_object_terms( $hand_filed, array( $decoy_id ), $tax );
wp_delete_term( $decoy_id, $tax );

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
