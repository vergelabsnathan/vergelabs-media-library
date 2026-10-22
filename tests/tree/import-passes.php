<?php
/**
 *  An import in several passes, and a resume the browser cannot forge
 *  (code review of review 2's fixes, Verification Gap layer, 2026-10-04).
 *
 *    A  five files in two folders, two files a pass: three passes, each
 *       handing back only where it is; the site's own record grows with them
 *    B  a resume naming an import the site has no record of is a 404, not a
 *       fresh start
 *    C  a resume that smuggles its own "added" list is read from the site's
 *       record instead: undo removes what the import did and nothing else
 *
 *      node tools/verify.mjs import-passes
 *
 *  A source of its own (a taxonomy on attachments, read by the generic
 *  reader) so nothing of another plugin is needed. Everything is deleted at
 *  the end.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['ip_ok']  = 0;
$GLOBALS['ip_bad'] = 0;

function ip_t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['ip_ok']++ : $GLOBALS['ip_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, '' !== $detail ? '  -- ' . $detail : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

wp_set_current_user( 1 );

register_taxonomy( 'zz_ip_src', 'attachment', array( 'hierarchical' => true, 'public' => false ) );
add_filter( 'vergeml_import_sources', function ( $sources ) {
	$sources['zzip'] = array( 'name' => 'Import passes suite', 'author' => 'suite', 'kind' => 'taxonomy', 'taxonomy' => 'zz_ip_src' );
	return $sources;
} );
add_filter( 'vergeml_import_chunk', function () {
	return 2;
} );

$ip_log_was = get_option( VERGEML_IMPORT_LOG, false );
$ip_parent  = wp_insert_term( 'ip parent ' . wp_rand(), 'zz_ip_src' );
$ip_parent  = (int) $ip_parent['term_id'];
$ip_child   = wp_insert_term( 'ip child ' . wp_rand(), 'zz_ip_src', array( 'parent' => $ip_parent ) );
$ip_child   = (int) $ip_child['term_id'];

$ip_files = array();
for ( $n = 0; $n < 5; $n++ ) {
	$ip_files[] = (int) wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'ip file ' . $n, 'post_status' => 'inherit' ) );
}
wp_set_object_terms( $ip_files[0], array( $ip_parent ), 'zz_ip_src' );
wp_set_object_terms( $ip_files[1], array( $ip_parent ), 'zz_ip_src' );
foreach ( array( 2, 3, 4 ) as $n ) {
	wp_set_object_terms( $ip_files[ $n ], array( $ip_child ), 'zz_ip_src' );
}

// One file already sits in the folder the import will merge into, so undo must leave it there.
$ip_existing = wp_insert_term( get_term( $ip_parent, 'zz_ip_src' )->name, 'vergeml_folder' );
$ip_existing = (int) $ip_existing['term_id'];
wp_set_object_terms( $ip_files[0], array( $ip_existing ), 'vergeml_folder' );


/* ---------------------------------------------------------- A  three passes */

echo "\nA  five files, two a pass\n\n";

$ip_1 = vergeml_import_run( 'zzip', 'vergeml_folder' );
ip_t( 'pass 1: two of five, not complete, a resume handed back', is_array( $ip_1 ) && 2 === $ip_1['done'] && 5 === $ip_1['total'] && ! $ip_1['complete'] && array( 'id', 'done' ) === array_keys( (array) $ip_1['resume'] ), wp_json_encode( $ip_1 ) );

$ip_id  = is_array( $ip_1 ) ? $ip_1['id'] : '';
$ip_rec = vergeml_import_record( $ip_id );
ip_t( 'the site recorded the pass before the import finished', is_array( $ip_rec ) && 1 === count( $ip_rec['added'] ), wp_json_encode( $ip_rec ) );

$ip_2 = vergeml_import_run( 'zzip', 'vergeml_folder', $ip_1['resume'] );
ip_t( 'pass 2: four of five, the same import', is_array( $ip_2 ) && 4 === $ip_2['done'] && $ip_id === $ip_2['id'] && ! $ip_2['complete'], wp_json_encode( $ip_2 ) );

$ip_3 = vergeml_import_run( 'zzip', 'vergeml_folder', $ip_2['resume'] );
ip_t( 'pass 3: complete, nothing more to hand back', is_array( $ip_3 ) && 5 === $ip_3['done'] && $ip_3['complete'] && null === $ip_3['resume'], wp_json_encode( $ip_3 ) );
ip_t( 'one folder merged into the existing one, one created', 1 === $ip_3['merged'] && 1 === $ip_3['created'], 'merged ' . $ip_3['merged'] . ', created ' . $ip_3['created'] );

$ip_rec = vergeml_import_record( $ip_id );
ip_t( 'the record holds the four the import added, not the one already filed', is_array( $ip_rec ) && 4 === count( $ip_rec['added'] ), wp_json_encode( $ip_rec['added'] ?? null ) );


/* ---------------------------------------------------------- B  a forged id */

echo "\nB  a resume for an import the site has no record of\n\n";

$ip_forged = vergeml_import_run( 'zzip', 'vergeml_folder', array( 'id' => 'impnotours', 'done' => 0 ) );
ip_t( 'a 404, not a fresh start', is_wp_error( $ip_forged ) && 'vergeml_no_import' === $ip_forged->get_error_code() && 404 === ( $ip_forged->get_error_data()['status'] ?? 0 ) );
ip_t( 'and no record was written for it', null === vergeml_import_record( 'impnotours' ) && 1 === count( (array) get_option( VERGEML_IMPORT_LOG ) ) - ( is_array( $ip_log_was ) ? count( $ip_log_was ) : 0 ) );


/* --------------------------------------------- C  a smuggled "added" list */

echo "\nC  what the import added is the site's record, never the request's\n\n";

$ip_smuggled = vergeml_import_run( 'zzip', 'vergeml_folder', array( 'id' => $ip_id, 'done' => 5, 'added' => array( array( $ip_existing, 999999 ) ), 'created' => array( $ip_existing ) ) );
$ip_rec      = vergeml_import_record( $ip_id );
ip_t( 'the record did not take the smuggled pairs', 4 === count( $ip_rec['added'] ) && ! in_array( $ip_existing, array_map( 'intval', (array) $ip_rec['created'] ), true ), wp_json_encode( $ip_rec ) );

$ip_undo = vergeml_import_undo( $ip_id );
$ip_left = array_map( 'intval', (array) wp_get_object_terms( $ip_files[0], 'vergeml_folder', array( 'fields' => 'ids' ) ) );
ip_t( 'undo: the four files the import filed are unfiled', array() === (array) wp_get_object_terms( $ip_files[4], 'vergeml_folder', array( 'fields' => 'ids' ) ) && array() === (array) wp_get_object_terms( $ip_files[1], 'vergeml_folder', array( 'fields' => 'ids' ) ) );
ip_t( 'undo: the file that was there before stays, in the folder that was there before', array( $ip_existing ) === $ip_left && get_term( $ip_existing, 'vergeml_folder' ) instanceof WP_Term, wp_json_encode( $ip_left ) );


/* ---------------------------------------------------------------- tidy up */

foreach ( $ip_files as $ip_f ) {
	wp_delete_attachment( $ip_f, true );
}
foreach ( get_terms( array( 'taxonomy' => array( 'zz_ip_src', 'vergeml_folder' ), 'hide_empty' => false, 'name__like' => 'ip ' ) ) as $ip_term ) {
	wp_delete_term( $ip_term->term_id, $ip_term->taxonomy );
}
false === $ip_log_was ? delete_option( VERGEML_IMPORT_LOG ) : update_option( VERGEML_IMPORT_LOG, $ip_log_was, false );

printf( "\n%d/%d passed\n", $GLOBALS['ip_ok'], $GLOBALS['ip_ok'] + $GLOBALS['ip_bad'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $GLOBALS['ip_bad'] > 0 ? 1 : 0 );
