<?php
/**
 *  Render every admin screen once and say how big it came out.
 *
 *      wp eval-file tools/smoke-screens.php --allow-root
 *
 *  This exists because a screen can lint clean and still fatal the moment it
 *  is drawn -- an undefined helper, a variable removed from a heading that
 *  something further down still read. Eight screens, each rendered under an
 *  administrator with the admin includes loaded, so what runs here is what
 *  runs when somebody opens the page.
 *
 *  wp eval-file evaluates this inside a function, so nothing here relies on a
 *  global to carry the result out (see docs/testing.md, "The traps"). A
 *  failure is printed and the process exits non-zero, which is the only
 *  signal a caller can actually trust.
 */

ini_set( 'display_errors', 1 );
error_reporting( E_ALL );

wp_set_current_user( 1 );

require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';

/*
 *  The settings screens live in a file the plugin only includes when
 *  is_admin() is true, and under WP-CLI it is not. Loading it here is loading
 *  exactly what an admin request loads -- the alternative was three screens
 *  that could never be smoked, which is three screens that fatal in front of
 *  a person first.
 */
if ( ! function_exists( 'vergeml_print_media_library_options' ) && defined( 'VERGEML_FILE' ) ) {
	// Same order the plugin uses: the option pages hang off admin-menu's menu.
	include_once dirname( VERGEML_FILE ) . '/core/admin-menu.php';
	include_once dirname( VERGEML_FILE ) . '/core/options-pages.php';
}

$screens = array(
	'Dashboard'              => 'vergeml_journey_screen',
	'AI'                     => 'vergeml_ai_page',
	'Duplicates'             => 'vergeml_health_page',
	'Sort into folders'      => 'vergeml_librarian_page',
	'Import folders'         => 'vergeml_import_screen',
	'Library settings'       => 'vergeml_print_media_library_options',
	'Folders and categories' => 'vergeml_print_taxonomies_options',
	'File types'             => 'vergeml_print_mimetypes_options',
);

$failed = 0;

foreach ( $screens as $label => $fn ) {

	printf( '%-24s ', $label );

	if ( ! function_exists( $fn ) ) {
		echo "MISSING  {$fn}\n";
		$failed++;
		continue;
	}

	try {
		ob_start();
		$fn();
		$html = (string) ob_get_clean();
	} catch ( Throwable $e ) {
		ob_end_clean();
		echo 'FATAL    ', get_class( $e ), ': ', $e->getMessage(), ' @ ', basename( $e->getFile() ), ':', $e->getLine(), "\n";
		$failed++;
		continue;
	}

	$len   = strlen( $html );
	$head  = substr_count( $html, 'class="vgml-pg-head"' );
	$cards = substr_count( $html, 'class="vgml-pg-card' );
	$boxes = substr_count( $html, 'class="postbox"' );
	$h1s   = substr_count( $html, '<h1>' );

	$ok = $len > 500 && 1 === $head && 0 === $h1s;

	printf(
		"%s %6d bytes   head=%d  cards=%d  postbox=%d  stray-h1=%d\n",
		$ok ? 'ok     ' : 'CHECK  ',
		$len, $head, $cards, $boxes, $h1s
	);

	if ( ! $ok ) {
		$failed++;
	}
}

echo "\n";
echo $failed ? "{$failed} screen(s) need looking at\n" : "all eight render\n";

exit( $failed ? 1 : 0 );
