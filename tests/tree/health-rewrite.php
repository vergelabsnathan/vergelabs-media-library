<?php
/**
 *  Keeping or deleting a duplicate rewrites other people's posts: through
 *  wp_update_post, and only the posts this person may edit (wordpress.org
 *  review 2, likely question 7, 2026-10-05).
 *
 *    A  vergeml_health_may_rewrite(): edit_post on the post, and nothing kses
 *       would cut for someone without unfiltered_html
 *    B  delete as a manager who may not edit an author's post: the copy that
 *       post shows is not deleted and the post is untouched
 *    C  delete as an administrator: the post points at the kept copy, the
 *       change went through wp_update_post (a revision exists), the copy is gone
 *    D  keep's rewrite of one post: refused for the manager, done for the
 *       administrator, and the record holds what was stored
 *
 *      node tools/verify.mjs health-rewrite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/user.php';

$GLOBALS['hr_ok']  = 0;
$GLOBALS['hr_bad'] = 0;

function hr_t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['hr_ok']++ : $GLOBALS['hr_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, '' !== $detail ? '  -- ' . $detail : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

foreach ( array( 'vergeml_health_may_rewrite', 'vergeml_health_repoint_refused', 'vergeml_health_delete_copies', 'vergeml_health_rewrite_source', 'vergeml_health_url_map' ) as $hr_fn ) {
	if ( ! function_exists( $hr_fn ) ) {
		echo "core/health-delete.php or health-keep.php is not loaded: $hr_fn\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit( 1 );
	}
}

$GLOBALS['hr_posts'] = array();

/** Two byte-identical files, hashed the way the scan writes it. */
function hr_twin( $name, $md5 ) {
	$uploads = wp_upload_dir();
	$path    = trailingslashit( $uploads['path'] ) . $name;
	file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	$id = (int) wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $name, 'post_status' => 'inherit', 'post_author' => 1 ), $path );
	wp_update_attachment_metadata( $id, array( 'width' => 1, 'height' => 1, 'file' => _wp_relative_upload_path( $path ), 'sizes' => array() ) );
	update_post_meta( $id, VERGEML_META_HASH, 'md5:' . $md5 . '|dhash:0000000000000000' );
	$GLOBALS['hr_posts'][] = $id;
	return $id;
}

function hr_post( $author, $content ) {
	$id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'hr post', 'post_author' => $author, 'post_content' => $content ) );
	$GLOBALS['hr_posts'][] = $id;
	return $id;
}

wp_set_current_user( 1 );

// A manager: may run the health screens (manage_options) but edits only its own posts, without unfiltered_html.
add_role( 'hr_manager', 'HR manager', array( 'read' => true, 'manage_options' => true, 'upload_files' => true, 'edit_posts' => true, 'edit_published_posts' => true, 'delete_posts' => true, 'delete_published_posts' => true ) );
$hr_manager = (int) wp_insert_user( array( 'user_login' => 'hr_manager_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'hr_manager' ) );
$hr_author  = (int) wp_insert_user( array( 'user_login' => 'hr_author_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
$hr_admin   = 1;

// Delete refuses without the usage scan; this suite's posts are its uses. Put back as found.
$hr_scan_was = get_option( VERGEML_SCAN_OPTION, false );
update_option( VERGEML_SCAN_OPTION, array_merge( is_array( $hr_scan_was ) ? $hr_scan_was : array(), array( 'finished' => time() ) ), false );


/* ------------------------------------------------------- A  may rewrite */

echo "\nA  who may have a post rewritten\n\n";

$hr_theirs = hr_post( $hr_author, '<p>plain</p>' );
$hr_own    = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'hr own', 'post_author' => $hr_manager, 'post_content' => '<p>plain</p>' ) );
$GLOBALS['hr_posts'][] = $hr_own;

wp_set_current_user( $hr_manager );
hr_t( 'the manager, an author\'s post: no', ! vergeml_health_may_rewrite( $hr_theirs, '<p>plain</p>' ) );
hr_t( 'the manager, its own post, plain markup: yes', vergeml_health_may_rewrite( $hr_own, '<p>plain</p>' ) );
hr_t( 'the manager, its own post, markup kses would cut: no', ! vergeml_health_may_rewrite( $hr_own, '<p>plain</p><iframe src="https://example.com/"></iframe>' ) );
wp_set_current_user( $hr_admin );
hr_t( 'an administrator, any post, any markup: yes', vergeml_health_may_rewrite( $hr_theirs, '<iframe src="https://example.com/"></iframe>' ) );


/* ----------------------------------------------------- B  delete, refused */

echo "\nB  delete as the manager: an author's post shows the copy\n\n";

$hr_md5   = md5( 'hr-b-' . wp_rand() );
$hr_keep  = hr_twin( 'hr-keep-' . wp_rand() . '.png', $hr_md5 );
$hr_drop  = hr_twin( 'hr-drop-' . wp_rand() . '.png', $hr_md5 );
$hr_url   = wp_get_attachment_url( $hr_drop );
$hr_body  = '<!-- wp:image {"id":' . $hr_drop . '} --><figure class="wp-block-image"><img src="' . $hr_url . '" class="wp-image-' . $hr_drop . '"/></figure><!-- /wp:image -->';
$hr_page  = hr_post( $hr_author, $hr_body );

wp_set_current_user( $hr_manager );
hr_t( 'the author\'s post is named as refused', in_array( $hr_page, vergeml_health_repoint_refused( $hr_drop, $hr_keep ), true ) );
$hr_res = vergeml_health_delete_copies( $hr_keep, array( $hr_drop ) );
hr_t( 'the copy is not deleted, and says so', is_array( $hr_res ) && array( $hr_drop ) === $hr_res['failed'] && array() === $hr_res['deleted'] && get_post( $hr_drop ) instanceof WP_Post, wp_json_encode( $hr_res ) );
hr_t( 'the author\'s post is untouched', $hr_body === get_post_field( 'post_content', $hr_page, 'raw' ) );


/* ------------------------------------------------- C  delete, administrator */

echo "\nC  delete as an administrator\n\n";

wp_set_current_user( $hr_admin );
$hr_revs_before = count( wp_get_post_revisions( $hr_page ) );
hr_t( 'nothing refused', array() === vergeml_health_repoint_refused( $hr_drop, $hr_keep ) );
$hr_res  = vergeml_health_delete_copies( $hr_keep, array( $hr_drop ) );
$hr_now  = (string) get_post_field( 'post_content', $hr_page, 'raw' );
hr_t( 'the copy is deleted', is_array( $hr_res ) && array( $hr_drop ) === $hr_res['deleted'] && null === get_post( $hr_drop ), wp_json_encode( $hr_res ) );
hr_t( 'the post points at the kept copy', false !== strpos( $hr_now, wp_get_attachment_url( $hr_keep ) ) && false !== strpos( $hr_now, 'wp-image-' . $hr_keep ) && false === strpos( $hr_now, 'wp-image-' . $hr_drop . '"' ), $hr_now );
hr_t( 'through wp_update_post: a revision was saved', count( wp_get_post_revisions( $hr_page ) ) > $hr_revs_before, $hr_revs_before . ' -> ' . count( wp_get_post_revisions( $hr_page ) ) );


/* --------------------------------------------------- D  keep's rewrite */

echo "\nD  keep rewrites one post at a time, as the person may\n\n";

$hr_md5   = md5( 'hr-d-' . wp_rand() );
$hr_keep2 = hr_twin( 'hr-keep2-' . wp_rand() . '.png', $hr_md5 );
$hr_drop2 = hr_twin( 'hr-drop2-' . wp_rand() . '.png', $hr_md5 );
$hr_body2 = '<p><img src="' . wp_get_attachment_url( $hr_drop2 ) . '" class="wp-image-' . $hr_drop2 . '"/></p>';
$hr_page2 = hr_post( $hr_author, $hr_body2 );
$hr_map   = vergeml_health_url_map( $hr_drop2, $hr_keep2 );

wp_set_current_user( $hr_manager );
$hr_record = array( 'posts' => array(), 'metas' => array(), 'thumbs' => array(), 'parents' => array() );
hr_t( 'the manager: refused, the post untouched, nothing recorded', false === vergeml_health_rewrite_source( $hr_page2, $hr_drop2, $hr_keep2, $hr_map, $hr_record ) && $hr_body2 === get_post_field( 'post_content', $hr_page2, 'raw' ) && array() === $hr_record['posts'] );

wp_set_current_user( $hr_admin );
$hr_record = array( 'posts' => array(), 'metas' => array(), 'thumbs' => array(), 'parents' => array() );
$hr_done   = vergeml_health_rewrite_source( $hr_page2, $hr_drop2, $hr_keep2, $hr_map, $hr_record );
$hr_now2   = (string) get_post_field( 'post_content', $hr_page2, 'raw' );
hr_t( 'the administrator: rewritten to the kept copy', true === $hr_done && false !== strpos( $hr_now2, wp_get_attachment_url( $hr_keep2 ) ), $hr_now2 );
hr_t( 'the record holds what was before and what was stored', 1 === count( $hr_record['posts'] ) && $hr_body2 === $hr_record['posts'][0]['before'] && $hr_now2 === $hr_record['posts'][0]['after'] );


/* ---------------------------------------------------------------- tidy up */

wp_set_current_user( 1 );
foreach ( $GLOBALS['hr_posts'] as $hr_id ) {
	'attachment' === get_post_type( $hr_id ) ? wp_delete_attachment( $hr_id, true ) : wp_delete_post( $hr_id, true );
}
wp_delete_user( $hr_manager );
wp_delete_user( $hr_author );
remove_role( 'hr_manager' );
false === $hr_scan_was ? delete_option( VERGEML_SCAN_OPTION ) : update_option( VERGEML_SCAN_OPTION, $hr_scan_was, false );

printf( "\n%d/%d passed\n", $GLOBALS['hr_ok'], $GLOBALS['hr_ok'] + $GLOBALS['hr_bad'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $GLOBALS['hr_bad'] > 0 ? 1 : 0 );
