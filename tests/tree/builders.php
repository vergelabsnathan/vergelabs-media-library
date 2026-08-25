<?php
/**
 *  The page-builder entry points.
 *
 *      wp eval-file tests/tree/builders.php --allow-root
 *
 *  tests/tree/builders.mjs drives the Elementor editor in a real browser. This
 *  covers the part that can be checked without the builder installed: that the
 *  detection says yes only when it should, and that asking for the tree directly
 *  actually enqueues it.
 *
 *  Divi is the reason this file exists. Its visual builder runs on the *front
 *  end*, so admin_enqueue_scripts never fires and a media plugin can be entirely
 *  absent inside the builder while working everywhere else. The detection is
 *  three questions because Divi has spelled the answer differently across
 *  versions, and getting it wrong is invisible rather than noisy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_set_current_user( 1 );

$GLOBALS['vgml_ok']  = 0;
$GLOBALS['vgml_bad'] = 0;

function t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['vgml_ok']++ : $GLOBALS['vgml_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, $detail ? '  -- ' . $detail : '' );
}

echo "\npage builders\n\n";

/* --- the pieces exist ---------------------------------------------------- */

t( 'the builder file loaded', function_exists( 'vergeml_builder_load_tree' ) );
t( 'Elementor has a hook', has_action( 'elementor/editor/before_enqueue_scripts', 'vergeml_builder_elementor' ) !== false );
t( 'Divi has one on the front end', has_action( 'wp_enqueue_scripts', 'vergeml_builder_divi' ) !== false );

/* --- Divi detection ------------------------------------------------------ */

unset( $_GET['et_fb'] );

t( 'an ordinary front-end request is not the builder', ! vergeml_builder_is_divi_fb() );

$_GET['et_fb'] = '1';
t( 'et_fb=1 is', vergeml_builder_is_divi_fb() );

$_GET['et_fb'] = '0';
t( 'et_fb=0 is not', ! vergeml_builder_is_divi_fb() );

$_GET['et_fb'] = 'yes';
t( 'and neither is anything else', ! vergeml_builder_is_divi_fb(), 'et_fb=yes' );

unset( $_GET['et_fb'] );

/* --- asking for the tree directly ---------------------------------------- */

/*
 *  What both builders end up calling. The assets have to arrive without any of
 *  the admin-screen conditions being true, because on Divi's front end none of
 *  them ever are.
 */
wp_dequeue_script( 'vergeml-tree' );
wp_deregister_script( 'vergeml-tree' );

t( 'the tree is not loaded to begin with', ! wp_script_is( 'vergeml-tree', 'enqueued' ) );

vergeml_builder_load_tree();

t( 'asking for it enqueues the script', wp_script_is( 'vergeml-tree', 'enqueued' ) );
t( 'and the stylesheet', wp_style_is( 'vergeml-tree', 'enqueued' ) );

/*
 *  The configuration has to travel with it, or the script loads and does
 *  nothing -- which is exactly how this failed in Elementor before the hook
 *  existed: script present, config absent, no tree, no error.
 */
$data = wp_scripts()->get_data( 'vergeml-tree', 'data' );

t( 'the configuration goes with it', is_string( $data ) && false !== strpos( $data, 'vergemlTree' ) );
t( 'and it carries the folders', is_string( $data ) && false !== strpos( $data, '"nodes"' ) );

/*
 *  The flag must not survive the call. It is a global, and a global that stays
 *  set turns every later request in the same process into a builder request.
 */
t( 'the override does not linger', empty( $GLOBALS['vergeml_force_tree'] ) );

/* --- who may have it ----------------------------------------------------- */

$subscriber = get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ID' ) );

if ( ! $subscriber ) {
	$made = wp_insert_user( array(
		'user_login' => 'vgmlsub' . wp_rand( 100, 999 ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	) );
	$subscriber = is_wp_error( $made ) ? array() : array( $made );
}

if ( $subscriber ) {

	wp_dequeue_script( 'vergeml-tree' );
	wp_deregister_script( 'vergeml-tree' );

	wp_set_current_user( (int) $subscriber[0] );

	vergeml_builder_load_tree();

	t( 'somebody who cannot upload does not get it', ! wp_script_is( 'vergeml-tree', 'enqueued' ) );

	wp_set_current_user( 1 );

	if ( isset( $made ) && ! is_wp_error( $made ) ) {
		wp_delete_user( (int) $made );
	}
}

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
