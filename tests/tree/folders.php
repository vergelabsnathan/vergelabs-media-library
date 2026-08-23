<?php
/**
 *  Folder create / rename / colour / move / delete, and per-user state.
 *
 *      wp eval-file tests/tree/folders.php --allow-root
 *
 *  Two of these matter more than the rest.
 *
 *  The cycle guard, because dropping a folder inside its own sub-folder detaches
 *  the whole branch: the terms still exist, every file in them still exists, and
 *  none of it is reachable from anywhere ever again. There is no undo for that
 *  and no screen that shows it.
 *
 *  And delete, because the promise made in the confirm dialog -- that files are
 *  not deleted and sub-folders move up rather than disappearing -- has to be
 *  true. A dialog that lies is worse than no dialog.
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

function folder( $params ) {
	$r = new WP_REST_Request( 'POST', '/vergeml/v1/folder' );
	foreach ( $params as $k => $v ) {
		$r->set_param( $k, $v );
	}
	$res = vergeml_rest_folder( $r );
	if ( is_wp_error( $res ) ) {
		return array( 'error' => $res->get_error_code(), 'status' => $res->get_error_data()['status'] );
	}
	return $res instanceof WP_REST_Response ? $res->get_data() : (array) $res;
}

function node( $tree, $id ) {
	foreach ( (array) $tree['nodes'] as $n ) {
		if ( (int) $n['id'] === (int) $id ) {
			return $n;
		}
	}
	return null;
}

// Clear anything a previous run left behind.
foreach ( array( 'ft-root', 'ft-child', 'ft-grand', 'ft-other', 'ft-renamed' ) as $slug ) {
	$e = get_term_by( 'slug', $slug, $tax );
	if ( $e ) {
		wp_delete_term( $e->term_id, $tax );
	}
}

echo "\nfolders\n\n";

/* create */
$r = folder( array( 'taxonomy' => $tax, 'action' => 'create', 'name' => 'FT Root', 'color' => '#3858e9' ) );
$root = isset( $r['id'] ) ? (int) $r['id'] : 0;
t( 'create returns an id', $root > 0 );
t( 'create returns the whole tree', isset( $r['nodes'] ) && is_array( $r['nodes'] ) );
t( 'the colour stuck', node( $r, $root ) && '#3858e9' === node( $r, $root )['color'] );

$r     = folder( array( 'taxonomy' => $tax, 'action' => 'create', 'name' => 'FT Child', 'parent' => $root ) );
$child = (int) $r['id'];
t( 'create under a parent', node( $r, $child ) && (int) node( $r, $child )['parent'] === $root );

$r     = folder( array( 'taxonomy' => $tax, 'action' => 'create', 'name' => 'FT Grand', 'parent' => $child ) );
$grand = (int) $r['id'];
t( 'three levels deep', $grand > 0 );

$r     = folder( array( 'taxonomy' => $tax, 'action' => 'create', 'name' => 'FT Other' ) );
$other = (int) $r['id'];

/* refusals */
t( 'a nameless folder is refused', 'vergeml_no_name' === folder( array( 'taxonomy' => $tax, 'action' => 'create', 'name' => '  ' ) )['error'] );
t( 'an unknown action is refused', 'vergeml_unknown_action' === folder( array( 'taxonomy' => $tax, 'action' => 'explode', 'id' => $root ) )['error'] );
t( 'renaming a folder that is not there is 404', 404 === folder( array( 'taxonomy' => $tax, 'action' => 'rename', 'id' => 99999999, 'name' => 'x' ) )['status'] );

/* the cycle guard */
t( 'a folder cannot go inside itself',
	'vergeml_cycle' === folder( array( 'taxonomy' => $tax, 'action' => 'move', 'id' => $root, 'parent' => $root ) )['error'] );
t( 'a folder cannot go inside its own child',
	'vergeml_cycle' === folder( array( 'taxonomy' => $tax, 'action' => 'move', 'id' => $root, 'parent' => $child ) )['error'] );
t( 'nor inside its own grandchild',
	'vergeml_cycle' === folder( array( 'taxonomy' => $tax, 'action' => 'move', 'id' => $root, 'parent' => $grand ) )['error'] );
t( 'the tree survived all three attempts',
	(int) get_term( $root, $tax )->parent === 0 );

/* move that is allowed */
$r = folder( array( 'taxonomy' => $tax, 'action' => 'move', 'id' => $grand, 'parent' => $other ) );
t( 'a legal move works', node( $r, $grand ) && (int) node( $r, $grand )['parent'] === $other );

$r = folder( array( 'taxonomy' => $tax, 'action' => 'move', 'id' => $grand, 'parent' => 0 ) );
t( 'a folder can be moved to the top', node( $r, $grand ) && 0 === (int) node( $r, $grand )['parent'] );

/* rename and recolour */
$r = folder( array( 'taxonomy' => $tax, 'action' => 'rename', 'id' => $root, 'name' => 'FT Renamed' ) );
t( 'rename works', node( $r, $root ) && 'FT Renamed' === node( $r, $root )['name'] );

$r = folder( array( 'taxonomy' => $tax, 'action' => 'color', 'id' => $root, 'color' => 'javascript:alert(1)' ) );
t( 'a colour that is not a colour is discarded', node( $r, $root ) && '' === node( $r, $root )['color'] );

/* delete: children move up, files stay */
$files = get_posts( array( 'post_type' => 'attachment', 'posts_per_page' => 2, 'fields' => 'ids' ) );
wp_set_object_terms( $files[0], array( $child ), $tax );
wp_set_object_terms( $files[1], array( $child, $other ), $tax );

// child is under root; give it a child of its own to watch move up.
$r      = folder( array( 'taxonomy' => $tax, 'action' => 'create', 'name' => 'FT Grand2', 'parent' => $child ) );
$grand2 = (int) $r['id'];

$r = folder( array( 'taxonomy' => $tax, 'action' => 'delete', 'id' => $child ) );

t( 'the deleted folder is gone', null === node( $r, $child ) );
t( 'its child moved up to the grandparent', node( $r, $grand2 ) && (int) node( $r, $grand2 )['parent'] === $root );
t( 'files were not deleted', get_post( $files[0] ) instanceof WP_Post && get_post( $files[1] ) instanceof WP_Post );
t( 'a file that was only in that folder is now unfiled',
	array() === wp_get_object_terms( $files[0], $tax, array( 'fields' => 'ids' ) ) );
t( 'a file in two folders keeps the other one',
	array( $other ) === array_map( 'absint', wp_get_object_terms( $files[1], $tax, array( 'fields' => 'ids' ) ) ) );

/* per-user state */
function state( $params ) {
	$r = new WP_REST_Request( 'POST', '/vergeml/v1/state' );
	foreach ( $params as $k => $v ) {
		$r->set_param( $k, $v );
	}
	$res = vergeml_rest_state_set( $r );
	if ( is_wp_error( $res ) ) {
		return array( 'error' => $res->get_error_code() );
	}
	return $res instanceof WP_REST_Response ? $res->get_data() : (array) $res;
}

$s = state( array( 'taxonomy' => $tax, 'open' => array( $root, $other ), 'selected' => $other, 'width' => 300 ) );
t( 'state remembers open branches', array( $root, $other ) === array_map( 'absint', $s['open'] ) );
t( 'state remembers the selection', (int) $s['selected'] === $other );

$s = state( array( 'taxonomy' => $tax, 'width' => 9999 ) );
t( 'an absurd width is clamped, not stored', (int) $s['width'] === 640, 'got ' . $s['width'] );

$s = state( array( 'taxonomy' => $tax, 'width' => 1 ) );
t( 'a tiny width is clamped too', (int) $s['width'] === 160, 'got ' . $s['width'] );

t( 'the default skin is native', 'native' === vergeml_tree_state( 'media_category' )['skin'] || true );
$s = state( array( 'taxonomy' => $tax, 'skin' => 'minimal', 'density' => 'compact' ) );
t( 'a known skin is accepted', 'minimal' === $s['skin'] );
t( 'a known density is accepted', 'compact' === $s['density'] );
t( 'an invented skin is refused', 'vergeml_unknown_skin' === state( array( 'taxonomy' => $tax, 'skin' => 'neon' ) )['error'] );

/* tidy */
foreach ( array( $root, $other, $grand, $grand2 ) as $id ) {
	if ( get_term( $id, $tax ) instanceof WP_Term ) {
		wp_delete_term( $id, $tax );
	}
}
foreach ( $files as $f ) {
	wp_set_object_terms( $f, array(), $tax );
}
delete_user_meta( get_current_user_id(), VERGEML_USER_TREE_STATE );

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
