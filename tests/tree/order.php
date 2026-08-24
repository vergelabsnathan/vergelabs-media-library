<?php
/**
 *  Arranging folders by hand.
 *
 *      wp eval-file tests/tree/order.php --allow-root
 *
 *  The tree has always sorted on `vergeml_order` and nothing has ever written it,
 *  so every tree was alphabetical whether or not that was what anybody wanted.
 *  This is the write.
 *
 *  The action takes a whole sibling list rather than a position, and it can
 *  re-parent while it is at it -- so it can detach a branch exactly as a move can,
 *  and it has to refuse the same things.
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

function call_folder( $args ) {
	$r = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/folder' );
	foreach ( $args as $k => $v ) {
		$r->set_param( $k, $v );
	}
	return vergeml_rest_folder( $r );
}

function make( $name, $parent, $tax ) {
	$made = wp_insert_term( $name, $tax, array( 'parent' => $parent ) );
	return is_wp_error( $made ) ? 0 : (int) $made['term_id'];
}

function order_of( $id ) {
	$v = get_term_meta( $id, VERGEML_TERM_ORDER, true );
	return $v === '' ? 0 : (int) $v;
}

echo "\narranging folders by hand\n\n";

$stamp = 'ord' . wp_rand( 1000, 9999 );

$p     = make( $stamp . ' parent', 0, $tax );
$a     = make( $stamp . ' a', $p, $tax );
$b     = make( $stamp . ' b', $p, $tax );
$c     = make( $stamp . ' c', $p, $tax );
$other = make( $stamp . ' elsewhere', 0, $tax );
$stray = make( $stamp . ' stray', $other, $tax );

t( 'a branch to arrange', $p && $a && $b && $c && $other && $stray );
t( 'nothing carries an order yet', 0 === order_of( $a ) && 0 === order_of( $c ) );

/* --- the order sticks ---------------------------------------------------- */

$res = call_folder( array( 'taxonomy' => $tax, 'action' => 'order', 'parent' => $p, 'ids' => array( $c, $b, $a ) ) );

t( 'the order was accepted', ! is_wp_error( $res ), is_wp_error( $res ) ? $res->get_error_code() : '' );
t( 'c is first, a is last', order_of( $c ) < order_of( $b ) && order_of( $b ) < order_of( $a ),
	'c=' . order_of( $c ) . ' b=' . order_of( $b ) . ' a=' . order_of( $a ) );

// The tree has to carry it, or the browser sorts on something it never sees.
$tree = vergeml_rest_tree( ( function () use ( $tax ) {
	$r = new WP_REST_Request( 'GET', '/' . VERGEML_REST_NS . '/tree' );
	$r->set_param( 'taxonomy', $tax );
	return $r;
} )() );

$sent = array();
foreach ( $tree->get_data()['nodes'] as $n ) {
	if ( (int) $n['parent'] === $p ) {
		$sent[ $n['id'] ] = $n['order'];
	}
}

t( 'the tree reports the order', isset( $sent[ $c ], $sent[ $a ] ) && $sent[ $c ] < $sent[ $a ],
	'c=' . $sent[ $c ] . ' a=' . $sent[ $a ] );

/* --- reordering again ---------------------------------------------------- */

call_folder( array( 'taxonomy' => $tax, 'action' => 'order', 'parent' => $p, 'ids' => array( $a, $c, $b ) ) );

t( 'a second arrangement replaces the first', order_of( $a ) < order_of( $c ) && order_of( $c ) < order_of( $b ),
	'a=' . order_of( $a ) . ' c=' . order_of( $c ) . ' b=' . order_of( $b ) );

/* --- it re-parents too --------------------------------------------------- */

call_folder( array( 'taxonomy' => $tax, 'action' => 'order', 'parent' => $p, 'ids' => array( $a, $stray, $c, $b ) ) );

$moved = get_term( $stray, $tax );

t( 'a folder from another branch joined this one', $moved instanceof WP_Term && (int) $moved->parent === $p,
	'parent is ' . ( $moved instanceof WP_Term ? $moved->parent : '?' ) );
t( 'and it landed in the right place', order_of( $a ) < order_of( $stray ) && order_of( $stray ) < order_of( $c ),
	'a=' . order_of( $a ) . ' stray=' . order_of( $stray ) . ' c=' . order_of( $c ) );

/* --- what it must refuse ------------------------------------------------- */

$before = get_term( $p, $tax )->parent;

$cycle = call_folder( array( 'taxonomy' => $tax, 'action' => 'order', 'parent' => $a, 'ids' => array( $p ) ) );
t( 'a folder cannot be ordered into its own child', is_wp_error( $cycle ),
	is_wp_error( $cycle ) ? $cycle->get_error_code() : 'accepted' );
t( 'and the attempt changed nothing', (int) get_term( $p, $tax )->parent === (int) $before );

$self = call_folder( array( 'taxonomy' => $tax, 'action' => 'order', 'parent' => $p, 'ids' => array( $p ) ) );
t( 'nor into itself', is_wp_error( $self ) );

$empty = call_folder( array( 'taxonomy' => $tax, 'action' => 'order', 'parent' => $p, 'ids' => array() ) );
t( 'an empty list is refused', is_wp_error( $empty ) );

$ghost = call_folder( array( 'taxonomy' => $tax, 'action' => 'order', 'parent' => $p, 'ids' => array( 99999999 ) ) );
t( 'a folder that does not exist is refused', is_wp_error( $ghost ) );

/* --- root level ---------------------------------------------------------- */

call_folder( array( 'taxonomy' => $tax, 'action' => 'order', 'parent' => 0, 'ids' => array( $other, $p ) ) );
t( 'top-level folders can be arranged too', order_of( $other ) < order_of( $p ),
	'other=' . order_of( $other ) . ' p=' . order_of( $p ) );

/* --- what happens to folders that arrive afterwards ---------------------- */

$new = call_folder( array( 'taxonomy' => $tax, 'action' => 'create', 'name' => $stamp . ' newcomer', 'parent' => $p ) );
$new_id = is_wp_error( $new ) ? 0 : (int) $new->get_data()['id'];

t( 'a folder can still be created in an arranged branch', $new_id > 0 );
t( 'and it goes to the end, not the top',
	order_of( $new_id ) > order_of( $b ) && order_of( $new_id ) > order_of( $a ),
	'newcomer=' . order_of( $new_id ) . ' vs a=' . order_of( $a ) . ' b=' . order_of( $b ) );

$arrived = make( $stamp . ' arrived', $other, $tax );
call_folder( array( 'taxonomy' => $tax, 'action' => 'move', 'id' => $arrived, 'parent' => $p ) );

t( 'a folder moved into an arranged branch goes to the end too',
	order_of( $arrived ) > order_of( $new_id ),
	'arrived=' . order_of( $arrived ) . ' newcomer=' . order_of( $new_id ) );

/* --- a branch nobody arranged is left alphabetical ----------------------- */

$plain = make( $stamp . ' plain', 0, $tax );
$z     = make( $stamp . ' plain z', $plain, $tax );

$fresh = call_folder( array( 'taxonomy' => $tax, 'action' => 'create', 'name' => $stamp . ' plain a', 'parent' => $plain ) );
$fresh_id = is_wp_error( $fresh ) ? 0 : (int) $fresh->get_data()['id'];

t( 'an unarranged branch is not given an order',
	0 === order_of( $z ) && 0 === order_of( $fresh_id ),
	'z=' . order_of( $z ) . ' a=' . order_of( $fresh_id ) );

/* --- who is allowed to ---------------------------------------------------- */

$editor = get_users( array( 'role' => 'author', 'number' => 1, 'fields' => 'ID' ) );

if ( ! $editor ) {
	$made_user = wp_insert_user( array(
		'user_login' => $stamp . 'author',
		'user_pass'  => wp_generate_password(),
		'role'       => 'author',
	) );
	$editor = is_wp_error( $made_user ) ? array() : array( $made_user );
}

if ( $editor ) {

	wp_set_current_user( (int) $editor[0] );

	t( 'an author cannot arrange folders', ! vergeml_can_manage_folders(),
		'can_manage_folders returned ' . var_export( vergeml_can_manage_folders(), true ) );

	wp_set_current_user( 1 );

	if ( isset( $made_user ) && ! is_wp_error( $made_user ) ) {
		wp_delete_user( (int) $made_user );
	}
}

/* tidy */
foreach ( array( $a, $b, $c, $stray, $new_id, $arrived, $z, $fresh_id, $plain, $p, $other ) as $id ) {
	if ( $id ) {
		wp_delete_term( $id, $tax );
	}
}

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
