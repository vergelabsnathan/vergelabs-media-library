<?php
/**
 *  Folders for posts, pages and custom post types.
 *
 *      wp eval-file tests/tree/post-folders.php --allow-root
 *
 *  Putting a media taxonomy on another post type is one argument to
 *  register_taxonomy, so almost nothing here is about storage. What is worth
 *  proving is the counting, because there are now two different questions a
 *  folder can be asked and only one number stored on it:
 *
 *    - the media library asks how many *files* are in the folder, and that must
 *      not move when somebody files a blog post;
 *    - a post screen asks how many *posts of that type* are in it, and that
 *      cannot come from the stored count.
 *
 *  Getting this wrong is not subtle from the outside: every folder in the media
 *  library silently gains the posts filed in it.
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

function tree_for( $tax, $post_type = '' ) {
	$r = new WP_REST_Request( 'GET', '/' . VERGEML_REST_NS . '/tree' );
	$r->set_param( 'taxonomy', $tax );
	if ( $post_type ) {
		$r->set_param( 'post_type', $post_type );
	}
	$res = vergeml_rest_tree( $r );
	return $res instanceof WP_REST_Response ? $res->get_data() : array();
}

function node_count( $data, $id ) {
	foreach ( $data['nodes'] as $n ) {
		if ( (int) $n['id'] === (int) $id ) {
			return (int) $n['count'];
		}
	}
	return null;
}

echo "\nfolders for posts\n\n";

$options = get_option( 'vergeml_taxonomies', array() );
$restore = isset( $options[ $tax ]['post_types'] ) ? $options[ $tax ]['post_types'] : null;

$stamp = 'pf' . wp_rand( 1000, 9999 );

/* --- turn it on ---------------------------------------------------------- */

$options[ $tax ]['post_types'] = array( 'post' );
update_option( 'vergeml_taxonomies', $options );

t( 'the setting reads back', vergeml_folder_post_types( $tax ) === array( 'post' ),
	implode( ',', vergeml_folder_post_types( $tax ) ) );
t( 'and attachments are still included', in_array( 'attachment', vergeml_folder_object_types( $tax ), true ) );

// Re-register with the new object types, which is what the next page load does.
vergeml_on_init();

t( 'the taxonomy is now on posts', in_array( $tax, get_object_taxonomies( 'post', 'names' ), true ) );
t( 'and still on attachments', in_array( $tax, get_object_taxonomies( 'attachment', 'names' ), true ) );

/* --- something to count -------------------------------------------------- */

$a = wp_insert_term( $stamp . ' a', $tax );
$b = wp_insert_term( $stamp . ' b', $tax );

$a = is_wp_error( $a ) ? 0 : (int) $a['term_id'];
$b = is_wp_error( $b ) ? 0 : (int) $b['term_id'];

t( 'two folders to file into', $a && $b );

// A file in folder A, so the two counts are provably different numbers.
$files = get_posts( array( 'post_type' => 'attachment', 'posts_per_page' => 1, 'fields' => 'ids' ) );
$file  = (int) $files[0];
wp_set_object_terms( $file, array( $a ), $tax, true );

$posts = array();

foreach ( array( 'one', 'two', 'three' ) as $i => $title ) {
	$posts[ $title ] = wp_insert_post( array(
		'post_title'  => $stamp . ' ' . $title,
		'post_type'   => 'post',
		'post_status' => 'publish',
	) );
}

wp_set_object_terms( $posts['one'], array( $a ), $tax, true );
wp_set_object_terms( $posts['two'], array( $a ), $tax, true );
wp_set_object_terms( $posts['three'], array( $b ), $tax, true );

$trashed = wp_insert_post( array( 'post_title' => $stamp . ' trashed', 'post_type' => 'post', 'post_status' => 'publish' ) );
wp_set_object_terms( $trashed, array( $a ), $tax, true );
wp_trash_post( $trashed );

/* --- the post counts ----------------------------------------------------- */

$counts = vergeml_folder_counts( $tax, 'post' );

t( 'folder A holds two posts', isset( $counts[ $a ] ) && 2 === $counts[ $a ],
	isset( $counts[ $a ] ) ? $counts[ $a ] : 'none' );
t( 'folder B holds one', isset( $counts[ $b ] ) && 1 === $counts[ $b ],
	isset( $counts[ $b ] ) ? $counts[ $b ] : 'none' );

// The trashed one is in folder A too, and is not on the list screen either.
t( 'a trashed post is not counted', isset( $counts[ $a ] ) && 2 === $counts[ $a ] );

/* --- the file count did not move ----------------------------------------- */

$media = tree_for( $tax );
$onposts = tree_for( $tax, 'post' );

t( 'the media tree counts the file, not the posts', 1 === node_count( $media, $a ),
	'media says ' . var_export( node_count( $media, $a ), true ) );
t( 'the post tree counts the posts, not the file', 2 === node_count( $onposts, $a ),
	'posts says ' . var_export( node_count( $onposts, $a ), true ) );
t( 'and the two are answering the same folder', node_count( $media, $a ) !== node_count( $onposts, $a ) );

t( 'the media tree says so', 'attachment' === $media['postType'], $media['postType'] );
t( 'the post tree says so too', 'post' === $onposts['postType'], $onposts['postType'] );

/* --- the stored count is still about files ------------------------------- */

/*
 *  The number WordPress keeps on the term is what the media library, the filter
 *  dropdown and the Media Categories screen all read. Registering the taxonomy
 *  for posts used to swap in a counter that counts everything *except*
 *  attachments, so that number silently became the post count and all three
 *  screens were wrong together.
 */
$object = get_taxonomy( $tax );

t( 'a media taxonomy keeps the attachment counter',
	'vergeml_update_attachment_term_count' === $object->update_count_callback,
	var_export( $object->update_count_callback, true ) );

wp_update_term_count_now( array( get_term( $a, $tax )->term_taxonomy_id ), $tax );

t( 'so the stored count is the file, not the posts', 1 === (int) get_term( $a, $tax )->count,
	'stored ' . get_term( $a, $tax )->count );

/* --- unfiled ------------------------------------------------------------- */

$unfiled = vergeml_folder_unfiled_count( $tax, 'post' );
$all_posts = (int) wp_count_posts( 'post' )->publish;

t( 'unfiled posts are counted', $unfiled === $all_posts - 3,
	$unfiled . ' unfiled of ' . $all_posts . ' published' );
t( 'the post tree reports it', (int) $onposts['unassigned'] === $unfiled,
	$onposts['unassigned'] . ' vs ' . $unfiled );
t( 'and the media tree reports its own', (int) $media['unassigned'] !== $unfiled || 0 === $unfiled,
	$media['unassigned'] . ' unfiled files' );

/* --- one tree per screen -------------------------------------------------- */

t( 'a post screen knows which taxonomy to show', $tax === vergeml_folder_taxonomy_for( 'post' ),
	vergeml_folder_taxonomy_for( 'post' ) );
t( 'a post type it is off for shows none', '' === vergeml_folder_taxonomy_for( 'page' ),
	vergeml_folder_taxonomy_for( 'page' ) );
t( 'and attachments never route here', '' === vergeml_folder_taxonomy_for( 'attachment' ) );

/* --- turning it off ------------------------------------------------------ */

$options = get_option( 'vergeml_taxonomies', array() );
$options[ $tax ]['post_types'] = array();
update_option( 'vergeml_taxonomies', $options );

t( 'it can be turned off again', array() === vergeml_folder_post_types( $tax ) );

/*
 *  Turning it off leaves the terms on the posts. That is the point of storing
 *  this in a taxonomy: nothing is destroyed by a setting, and turning it back on
 *  finds everything where it was.
 */
$still = wp_get_object_terms( $posts['one'], $tax, array( 'fields' => 'ids' ) );
t( 'and the posts keep their folders', in_array( $a, array_map( 'absint', $still ), true ) );

/* tidy */
foreach ( $posts as $id ) {
	wp_delete_post( $id, true );
}
wp_delete_post( $trashed, true );
wp_remove_object_terms( $file, array( $a ), $tax );
wp_delete_term( $a, $tax );
wp_delete_term( $b, $tax );

$options = get_option( 'vergeml_taxonomies', array() );
if ( null === $restore ) {
	unset( $options[ $tax ]['post_types'] );
} else {
	$options[ $tax ]['post_types'] = $restore;
}
update_option( 'vergeml_taxonomies', $options );

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
