<?php
/**
 *  The undo inverse.
 *
 *      wp eval-file tests/tree/undo-inverse.php --allow-root
 *
 *  Run against the handler directly rather than over HTTP: the thing under test
 *  is what the inverse contains, and a nonce adds nothing to that question.
 *
 *  The case that matters is the third one. Drag three files onto a folder when
 *  one of them is already in it, then undo. The naive inverse -- "remove what
 *  the request added, from every file it touched" -- takes the folder away from
 *  the file that was there first. The user asked to take back their last action
 *  and lost an earlier one instead.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 *  wp eval-file runs with no current user, and the handler decides permission per
 *  attachment -- so without this every file is refused and every assertion fails
 *  for a reason that has nothing to do with the inverse.
 */
wp_set_current_user( 1 );

$tax = 'media_category';

// eval-file's top-level scope is not the global scope, so a `global $ok` inside a
// function binds to nothing and the tally silently reads 0/0.
$GLOBALS['vgml_ok']  = 0;
$GLOBALS['vgml_bad'] = 0;

function t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['vgml_ok']++ : $GLOBALS['vgml_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, $detail ? '  -- ' . $detail : '' );
}

function terms_of( $id, $tax ) {
	$t = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
	$t = is_wp_error( $t ) ? array() : array_map( 'absint', $t );
	sort( $t );
	return $t;
}

function call( $params ) {
	$r = new WP_REST_Request( 'POST', '/vergeml/v1/assign' );
	foreach ( $params as $k => $v ) {
		$r->set_param( $k, $v );
	}
	$res = vergeml_rest_assign( $r );
	if ( is_wp_error( $res ) ) {
		return array( 'error' => $res->get_error_code() );
	}
	return $res instanceof WP_REST_Response ? $res->get_data() : (array) $res;
}

// Fresh fixtures, so a rerun cannot pass on last run's leftovers.
foreach ( array( 'undo-a', 'undo-b' ) as $slug ) {
	$existing = get_term_by( 'slug', $slug, $tax );
	if ( $existing ) {
		wp_delete_term( $existing->term_id, $tax );
	}
}
$A = wp_insert_term( 'Undo A', $tax, array( 'slug' => 'undo-a' ) );
$B = wp_insert_term( 'Undo B', $tax, array( 'slug' => 'undo-b' ) );
$A = (int) $A['term_id'];
$B = (int) $B['term_id'];

$files = get_posts( array( 'post_type' => 'attachment', 'posts_per_page' => 3, 'fields' => 'ids', 'orderby' => 'ID' ) );
if ( count( $files ) < 3 ) {
	echo "need at least 3 attachments\n";
	exit( 1 );
}
list( $f1, $f2, $f3 ) = $files;
foreach ( $files as $f ) {
	wp_set_object_terms( $f, array(), $tax );
}

echo "\nundo inverse\n\n";

/* 1. A plain add, taken back, leaves nothing behind. */
$r = call( array( 'taxonomy' => $tax, 'attachments' => array( $f2, $f3 ), 'add' => array( $A ) ) );
t( 'add reports an undo', ! empty( $r['undo']['batch'] ) );
call( array_merge( array( 'taxonomy' => $tax ), array( 'batch' => $r['undo']['batch'] ) ) );
t( 'undo of an add clears both files', terms_of( $f2, $tax ) === array() && terms_of( $f3, $tax ) === array() );

/* 2. Undo restores a removal. */
wp_set_object_terms( $f2, array( $A ), $tax );
$r = call( array( 'taxonomy' => $tax, 'attachments' => array( $f2 ), 'remove' => array( $A ) ) );
t( 'remove empties it', terms_of( $f2, $tax ) === array() );
call( array( 'taxonomy' => $tax, 'batch' => $r['undo']['batch'] ) );
t( 'undo of a remove puts it back', terms_of( $f2, $tax ) === array( $A ) );

/* 3. The bug: one file was already there, and undo must not evict it. */
wp_set_object_terms( $f1, array( $A ), $tax );   // f1 was filed here yesterday
wp_set_object_terms( $f2, array(), $tax );
wp_set_object_terms( $f3, array(), $tax );

$r = call( array( 'taxonomy' => $tax, 'attachments' => array( $f1, $f2, $f3 ), 'add' => array( $A ) ) );
t( 'all three are in the folder', count( array_filter( array( $f1, $f2, $f3 ), function ( $f ) use ( $tax, $A ) {
	return in_array( $A, terms_of( $f, $tax ), true );
} ) ) === 3 );
$named = empty( $r['undo']['batch'][0]['attachments'] ) ? 0 : count( $r['undo']['batch'][0]['attachments'] );
t( 'undo names only the two that moved', 2 === $named, 'named ' . $named );

call( array( 'taxonomy' => $tax, 'batch' => isset( $r['undo']['batch'] ) ? $r['undo']['batch'] : array() ) );
t( 'the file that was already there KEEPS the folder', terms_of( $f1, $tax ) === array( $A ),
	'f1 now in ' . count( terms_of( $f1, $tax ) ) . ' folders' );
t( 'the two that moved are back out', terms_of( $f2, $tax ) === array() && terms_of( $f3, $tax ) === array() );

/* 4. Move is undoable, and restores the exact prior membership. */
wp_set_object_terms( $f1, array( $A, $B ), $tax );
$before = terms_of( $f1, $tax );
$r      = call( array( 'taxonomy' => $tax, 'attachments' => array( $f1 ), 'add' => array( $B ), 'mode' => 'move' ) );
t( 'move replaces membership', terms_of( $f1, $tax ) === array( $B ) );
t( 'move reports an undo', ! empty( $r['undo']['batch'] ) );
if ( ! empty( $r['undo']['batch'] ) ) {
	call( array( 'taxonomy' => $tax, 'batch' => $r['undo']['batch'] ) );
	t( 'undo of a move restores both folders', terms_of( $f1, $tax ) === $before,
		'expected ' . implode( ',', $before ) . ' got ' . implode( ',', terms_of( $f1, $tax ) ) );
}

/* 5. Nothing to take back when nothing moved. */
wp_set_object_terms( $f2, array( $A ), $tax );
$r = call( array( 'taxonomy' => $tax, 'attachments' => array( $f2 ), 'add' => array( $A ) ) );
t( 'a no-op reports no undo', empty( $r['undo'] ) );
t( 'a no-op still reports the file as changed', in_array( $f2, (array) $r['changed'], true ) );

foreach ( $files as $f ) {
	wp_set_object_terms( $f, array(), $tax );
}
wp_delete_term( $A, $tax );
wp_delete_term( $B, $tax );

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
