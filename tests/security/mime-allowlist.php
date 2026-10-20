<?php
/**
 *  The File types settings can only switch WordPress's own types on and off.
 *
 *      node tools/verify.mjs mime-allowlist
 *
 *  wordpress.org review 2 (2026-10-03), "Adding insecure MIME types": until
 *  4.1.0 a setting could allow any extension, with a short blocklist. Now a
 *  setting that names a type WordPress does not know is dropped on save and on
 *  read, and a type that can run code is never allowed for upload.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['vergeml_ok']  = 0;
$GLOBALS['vergeml_bad'] = 0;

function vergeml_t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['vergeml_ok']++ : $GLOBALS['vergeml_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, '' !== $detail ? '  -- ' . $detail : '' );
}

require_once ABSPATH . 'wp-admin/includes/template.php';
wp_set_current_user( 1 );

echo "\nthe MIME allowlist\n\n";

$restore = get_option( 'vergeml_mimes', null );
$known   = wp_get_mime_types();

vergeml_t( 'the plugin adds no type to WordPress\'s list', ! has_filter( 'mime_types', 'vergeml_mime_types' ) );
vergeml_t( 'jpg is a type WordPress knows', isset( $known['jpg|jpeg|jpe'] ) );
vergeml_t( 'svg is not', ! isset( $known['svg'] ) );

/* --- on save --------------------------------------------------------------- */

$saved = vergeml_mimes_validate( array(
	'jpg|jpeg|jpe' => array( 'mime' => 'text/x-php', 'singular' => 'Image', 'plural' => 'Images', 'upload' => 1 ),
	'php'          => array( 'mime' => 'text/x-php', 'singular' => 'PHP', 'plural' => 'PHP', 'upload' => 1 ),
	'phtml|php'    => array( 'mime' => 'text/x-php', 'singular' => 'PHP', 'plural' => 'PHP', 'upload' => 1 ),
	'svg'          => array( 'mime' => 'image/svg+xml', 'singular' => 'SVG', 'plural' => 'SVG', 'upload' => 1 ),
	'xyz'          => array( 'mime' => 'application/x-xyz', 'singular' => 'X', 'plural' => 'X', 'upload' => 1 ),
	'htm|html'     => array( 'mime' => 'text/html', 'singular' => 'HTML', 'plural' => 'HTML', 'upload' => 1 ),
	'js'           => array( 'mime' => 'application/javascript', 'singular' => 'JS', 'plural' => 'JS', 'upload' => 1 ),
) );

vergeml_t( 'a php setting cannot be saved', ! isset( $saved['php'] ) && ! isset( $saved['phtml|php'] ) );
vergeml_t( 'nor svg', ! isset( $saved['svg'] ) );
vergeml_t( 'nor a type WordPress does not know', ! isset( $saved['xyz'] ) );
vergeml_t( 'html is kept as a label but never for upload', isset( $saved['htm|html'] ) && 0 === $saved['htm|html']['upload'] );
vergeml_t( 'js the same', isset( $saved['js'] ) && 0 === $saved['js']['upload'] );
vergeml_t( 'a known type keeps WordPress\'s MIME type, not the one posted', 'image/jpeg' === $saved['jpg|jpeg|jpe']['mime'], $saved['jpg|jpeg|jpe']['mime'] );

$said = wp_list_pluck( get_settings_errors( 'vergeml-mime-types' ), 'message' );
$said = implode( ' ', $said );

vergeml_t( 'the screen is told which were left out',
	false !== strpos( $said, 'php' ) && false !== strpos( $said, 'svg' ) && false !== strpos( $said, 'xyz' ) && false !== strpos( $said, 'js' ),
	$said );

/* --- on read: a setting written before 4.1.0 -------------------------------- */

update_option( 'vergeml_mimes', array(
	'jpg|jpeg|jpe' => array( 'mime' => 'image/jpeg', 'singular' => 'I', 'plural' => 'I', 'filter' => 0, 'upload' => 0 ),
	'png'          => array( 'mime' => 'image/png', 'singular' => 'P', 'plural' => 'P', 'filter' => 0, 'upload' => 1 ),
	'php'          => array( 'mime' => 'text/x-php', 'singular' => 'P', 'plural' => 'P', 'filter' => 0, 'upload' => 1 ),
	'svg'          => array( 'mime' => 'image/svg+xml', 'singular' => 'S', 'plural' => 'S', 'filter' => 0, 'upload' => 1 ),
	'xyz'          => array( 'mime' => 'application/x-xyz', 'singular' => 'X', 'plural' => 'X', 'filter' => 0, 'upload' => 1 ),
	'js'           => array( 'mime' => 'application/javascript', 'singular' => 'J', 'plural' => 'J', 'filter' => 0, 'upload' => 1 ),
	'gif'          => array( 'mime' => 'text/html', 'singular' => 'G', 'plural' => 'G', 'filter' => 0, 'upload' => 1 ),
), false );

// An author: no unfiltered_html, so core itself allows neither html nor js
// (a single site's editor has unfiltered_html, and there core's own list
// includes them; the plugin leaves that to core).
$editor = wp_insert_user( array( 'user_login' => 'vergeml_mime_author_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
wp_set_current_user( $editor );

$allowed = get_allowed_mime_types();

vergeml_t( 'an old php setting does not reach the upload list', ! isset( $allowed['php'] ) );
vergeml_t( 'nor svg', ! isset( $allowed['svg'] ) );
vergeml_t( 'nor an unknown extension', ! isset( $allowed['xyz'] ) );
vergeml_t( 'nor js for an author, though the old setting said upload', ! isset( $allowed['js'] ) );
vergeml_t( 'a known type switched on is allowed', isset( $allowed['png'] ) );
vergeml_t( 'a known type switched off is not', ! isset( $allowed['jpg|jpeg|jpe'] ) );
vergeml_t( 'a stored MIME type is not trusted', isset( $allowed['gif'] ) && 'image/gif' === $allowed['gif'], isset( $allowed['gif'] ) ? $allowed['gif'] : 'absent' );

// Style sheets and XML are what a browser interprets, not shows (wordpress.org review 2, round 4): off even for an
// administrator, whom core itself would allow css, and whatever an old setting said.
$vergeml_css_opts = get_option( 'vergeml_mimes' );
$vergeml_css_opts['css'] = array( 'mime' => 'text/css', 'singular' => 'C', 'plural' => 'C', 'filter' => 0, 'upload' => 1 );
update_option( 'vergeml_mimes', $vergeml_css_opts, false );
wp_set_current_user( 1 );
$vergeml_admin_allowed = get_allowed_mime_types();
vergeml_t( 'css is never allowed, not even for an administrator', ! isset( $vergeml_admin_allowed['css'] ) );
vergeml_t( 'css, xml, xsl and dfxp are refused types', vergeml_mime_refused( 'css' ) && vergeml_mime_refused( 'xml' ) && vergeml_mime_refused( 'xsl' ) && vergeml_mime_refused( 'dfxp' ) && ! vergeml_mime_refused( 'png' ) && ! vergeml_mime_refused( 'pdf' ) );
$vergeml_known_open = array_filter( array_keys( wp_get_mime_types() ), function ( $ext ) { return ! vergeml_mime_refused( $ext ); } );
vergeml_t( 'of the types WordPress knows, none left open is markup, style, script or XML', array() === array_filter( $vergeml_known_open, function ( $ext ) { return (bool) preg_match( '/(^|\|)(css|html?|js|xml|xsl|svg|swf|class|exe|dfxp)(\||$)/', $ext ); } ), implode( ', ', $vergeml_known_open ) );
// Remove-only (round 4 review pass): a type switched on in the settings but absent from the list WordPress hands the
// filter (another plugin took it out) stays absent.
$vergeml_without_png = get_allowed_mime_types();
unset( $vergeml_without_png['png'] );
$vergeml_filtered = vergeml_upload_mimes( $vergeml_without_png );
vergeml_t( 'the filter never adds a type, even one switched on', ! isset( $vergeml_filtered['png'] ) && count( $vergeml_filtered ) <= count( $vergeml_without_png ) );
wp_set_current_user( $editor );

$dropped = vergeml_mimes_dropped();

vergeml_t( 'the screen lists what an old setting named',
	in_array( 'php', $dropped, true ) && in_array( 'svg', $dropped, true ) && in_array( 'xyz', $dropped, true ) && in_array( 'js', $dropped, true ) && ! in_array( 'png', $dropped, true ),
	implode( ', ', $dropped ) );

/* --- content check ------------------------------------------------------------ */

$blank = array( 'ext' => false, 'type' => false, 'proper_filename' => false );
$zip   = vergeml_check_filetype_and_ext( $blank, '/tmp/x.zip', 'x.zip', null, 'application/octet-stream' );

vergeml_t( 'unrecognised content no longer passes under any application/* extension', false === $zip['type'], var_export( $zip['type'], true ) );

/* --- tidy -------------------------------------------------------------------- */

wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $editor );

if ( null === $restore ) {
	delete_option( 'vergeml_mimes' );
} else {
	update_option( 'vergeml_mimes', $restore );
}

printf( "\n%d/%d passed\n\n", $GLOBALS['vergeml_ok'], $GLOBALS['vergeml_ok'] + $GLOBALS['vergeml_bad'] );
exit( $GLOBALS['vergeml_bad'] ? 1 : 0 );
