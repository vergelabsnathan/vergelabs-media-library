<?php
/**
 *  What the endpoints refuse.
 *
 *      wp eval-file tests/tree/hardening.php --allow-root
 *
 *  Every other suite checks that things work for an administrator. This one is
 *  the opposite: it takes each role in turn and tries what that role must not be
 *  able to do, plus the malformed input that a permission check never sees.
 *
 *  The interesting cases are not "can a subscriber delete a folder" -- that one
 *  everybody remembers. They are the ones where a capability looks sufficient
 *  and is not:
 *
 *    - an author can upload, so they pass the coarse gate on the assign
 *      endpoint. They must still not be able to retag somebody else's file.
 *    - a term id is just a number, so a term from a different taxonomy is a
 *      valid-looking argument that would create a relationship nothing shows.
 *    - the folder endpoints take a taxonomy name from the request, and any
 *      taxonomy on the site is a plausible value.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['vgml_ok']  = 0;
$GLOBALS['vgml_bad'] = 0;

function t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['vgml_ok']++ : $GLOBALS['vgml_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, $detail ? '  -- ' . $detail : '' );
}

function vgml_user( $role ) {

	$login = 'vgmlh_' . $role;
	$user  = get_user_by( 'login', $login );

	if ( $user ) {
		return (int) $user->ID;
	}

	$id = wp_insert_user( array(
		'user_login' => $login,
		'user_pass'  => wp_generate_password(),
		'user_email' => $login . '@example.invalid',
		'role'       => $role,
	) );

	return is_wp_error( $id ) ? 0 : (int) $id;
}

function vgml_call( $route, $params ) {

	$method = ( '/tree' === $route ) ? 'GET' : 'POST';
	$r      = new WP_REST_Request( $method, '/' . VERGEML_REST_NS . $route );

	foreach ( $params as $k => $v ) {
		$r->set_param( $k, $v );
	}

	return rest_do_request( $r );
}

function vgml_refused( $response ) {

	if ( is_wp_error( $response ) ) {
		return true;
	}

	if ( $response instanceof WP_REST_Response ) {
		return $response->get_status() >= 400;
	}

	return false;
}

$tax = 'media_category';

wp_set_current_user( 1 );

echo "\nwhat the endpoints refuse\n\n";

/* --- something to attack -------------------------------------------------- */

$folder = wp_insert_term( 'hardening ' . wp_rand( 1000, 9999 ), $tax );
$folder = is_wp_error( $folder ) ? 0 : (int) $folder['term_id'];

$files = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids' ) );
$file  = $files ? (int) $files[0] : 0;

t( 'a folder and a file to try things on', $folder && $file, "term $folder, file $file" );

$roles = array( 'subscriber', 'contributor', 'author', 'editor' );
$users = array();

foreach ( $roles as $role ) {
	$users[ $role ] = vgml_user( $role );
}

t( 'four roles to try them as', count( array_filter( $users ) ) === 4, implode( ', ', array_keys( array_filter( $users ) ) ) );

/* --- the folder endpoints ------------------------------------------------- */

echo "\nfolders: creating, renaming, deleting\n";

foreach ( array( 'subscriber', 'contributor', 'author' ) as $role ) {

	wp_set_current_user( $users[ $role ] );

	$made = vgml_call( '/folder', array( 'taxonomy' => $tax, 'action' => 'create', 'name' => 'sneaky ' . $role ) );
	t( "a $role cannot create a folder", vgml_refused( $made ),
		vgml_refused( $made ) ? '' : 'CREATED' );

	$gone = vgml_call( '/folder', array( 'taxonomy' => $tax, 'action' => 'delete', 'id' => $folder ) );
	t( "a $role cannot delete one", vgml_refused( $gone ) && get_term( $folder, $tax ) instanceof WP_Term,
		get_term( $folder, $tax ) instanceof WP_Term ? '' : 'THE FOLDER IS GONE' );

	$moved = vgml_call( '/folder', array( 'taxonomy' => $tax, 'action' => 'order', 'parent' => 0, 'ids' => array( $folder ) ) );
	t( "nor rearrange them", vgml_refused( $moved ) );
}

wp_set_current_user( $users['editor'] );
$editor_made = vgml_call( '/folder', array( 'taxonomy' => $tax, 'action' => 'create', 'name' => 'editor folder ' . wp_rand( 100, 999 ) ) );
t( 'an editor can, because they manage categories', ! vgml_refused( $editor_made ) );

if ( ! vgml_refused( $editor_made ) ) {
	$data = $editor_made->get_data();
	if ( ! empty( $data['id'] ) ) {
		wp_set_current_user( 1 );
		wp_delete_term( (int) $data['id'], $tax );
	}
}

/* --- filing files --------------------------------------------------------- */

echo "\nfiling somebody else's file\n";

wp_set_current_user( $users['subscriber'] );
$sub = vgml_call( '/assign', array( 'taxonomy' => $tax, 'attachments' => array( $file ), 'add' => array( $folder ), 'mode' => 'add' ) );
t( 'a subscriber is refused outright', vgml_refused( $sub ) );

/*
 *  The one that matters. An author has upload_files, so they get through the
 *  coarse gate -- and the file belongs to the administrator.
 */
wp_set_current_user( $users['author'] );
$author = vgml_call( '/assign', array( 'taxonomy' => $tax, 'attachments' => array( $file ), 'add' => array( $folder ), 'mode' => 'add' ) );

$after = wp_get_object_terms( $file, $tax, array( 'fields' => 'ids' ) );
$after = is_wp_error( $after ) ? array() : array_map( 'absint', $after );

t( 'an author reaches the endpoint', ! vgml_refused( $author ) );
t( 'but cannot file a file that is not theirs', ! in_array( $folder, $after, true ),
	in_array( $folder, $after, true ) ? 'IT WAS FILED' : 'refused per file' );

if ( ! vgml_refused( $author ) ) {
	$body = $author->get_data();
	t( 'and is told it was refused rather than ignored',
		! empty( $body['refused'] ) && in_array( $file, array_map( 'absint', (array) $body['refused'] ), true ),
		wp_json_encode( $body['refused'] ?? null ) );
}

/* --- arguments that look valid -------------------------------------------- */

echo "\narguments that look valid\n";

wp_set_current_user( 1 );

$other = wp_insert_term( 'hardening other ' . wp_rand( 1000, 9999 ), 'category' );
$other = is_wp_error( $other ) ? 0 : (int) $other['term_id'];

$cross = vgml_call( '/assign', array( 'taxonomy' => $tax, 'attachments' => array( $file ), 'add' => array( $other ), 'mode' => 'add' ) );
t( 'a term from another taxonomy is refused', vgml_refused( $cross ),
	vgml_refused( $cross ) ? '' : 'ACCEPTED' );

$cross_order = vgml_call( '/folder', array( 'taxonomy' => $tax, 'action' => 'order', 'parent' => 0, 'ids' => array( $other ) ) );
t( 'and cannot be rearranged into ours', vgml_refused( $cross_order ) );

$not_ours = vgml_call( '/tree', array( 'taxonomy' => 'category' ) );
t( 'the tree refuses a taxonomy that is not a media one', vgml_refused( $not_ours ) );

$nonsense = vgml_call( '/folder', array( 'taxonomy' => $tax, 'action' => 'create', 'name' => '   ' ) );
t( 'a folder named only spaces is refused', vgml_refused( $nonsense ) );

$negative = vgml_call( '/assign', array( 'taxonomy' => $tax, 'attachments' => array( -5, 0 ), 'add' => array( $folder ), 'mode' => 'add' ) );
t( 'ids that cannot exist are refused', vgml_refused( $negative ),
	vgml_refused( $negative ) ? '' : 'ACCEPTED' );

/* --- a name that is trying something --------------------------------------- */

echo "\na folder name that is trying something\n";

$payload = '<script>alert(1)</script>';
$made    = vgml_call( '/folder', array( 'taxonomy' => $tax, 'action' => 'create', 'name' => $payload ) );

if ( ! vgml_refused( $made ) ) {

	$id   = (int) $made->get_data()['id'];
	$term = get_term( $id, $tax );
	$name = $term instanceof WP_Term ? $term->name : '';

	t( 'a script tag does not survive as one', false === strpos( $name, '<script' ), $name );

	$tree  = vgml_call( '/tree', array( 'taxonomy' => $tax ) );
	$found = '';

	foreach ( $tree->get_data()['nodes'] as $n ) {
		if ( (int) $n['id'] === $id ) {
			$found = $n['name'];
		}
	}

	t( 'nor in what the tree hands the browser', false === strpos( $found, '<script' ), $found );

	wp_delete_term( $id, $tax );
} else {
	t( 'a script tag is refused outright', true );
	t( 'nor in what the tree hands the browser', true );
}

/* --- the importer ---------------------------------------------------------- */

echo "\nimporting\n";

foreach ( array( 'subscriber', 'author' ) as $role ) {
	wp_set_current_user( $users[ $role ] );
	$run = vgml_call( '/import', array( 'action' => 'run', 'source' => 'filebird', 'taxonomy' => $tax ) );
	t( "a $role cannot run an import", vgml_refused( $run ), vgml_refused( $run ) ? '' : 'IT RAN' );
}

wp_set_current_user( 1 );

$bogus = vgml_call( '/import', array( 'action' => 'run', 'source' => 'not-a-plugin', 'taxonomy' => $tax ) );
t( 'an unknown source is refused', vgml_refused( $bogus ) );

$bogus_action = vgml_call( '/import', array( 'action' => 'destroy-everything' ) );
t( 'an unknown action is refused', vgml_refused( $bogus_action ) );

/* --- reading --------------------------------------------------------------- */

echo "\nreading\n";

wp_set_current_user( $users['subscriber'] );
$read = vgml_call( '/tree', array( 'taxonomy' => $tax ) );
t( 'a subscriber cannot read the tree', vgml_refused( $read ),
	vgml_refused( $read ) ? '' : 'READABLE' );

wp_set_current_user( 0 );
$out = vgml_call( '/tree', array( 'taxonomy' => $tax ) );
t( 'nor can somebody logged out', vgml_refused( $out ) );

$out_write = vgml_call( '/folder', array( 'taxonomy' => $tax, 'action' => 'create', 'name' => 'anon' ) );
t( 'and they certainly cannot write', vgml_refused( $out_write ) );

/* tidy */
wp_set_current_user( 1 );

if ( $folder ) {
	wp_delete_term( $folder, $tax );
}
if ( $other ) {
	wp_delete_term( $other, 'category' );
}
foreach ( $users as $id ) {
	if ( $id ) {
		wp_delete_user( $id );
	}
}

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
