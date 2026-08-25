<?php
/**
 *  The folder gallery, through Elementor and Divi.
 *
 *      wp eval-file tests/tree/gallery-widgets.php --allow-root
 *
 *  tests/tree/gallery.mjs proves the Gutenberg door. This proves the other two
 *  -- and it proves them by building a page the way each host saves one and
 *  fetching it from the front of the site, because "the class registered" says
 *  nothing about whether the host actually calls it. Elementor renders from
 *  _elementor_data postmeta, not post content; Divi renders its module
 *  shortcode only inside its own section markup on a builder-enabled page.
 *  Either detail wrong means a widget that exists and never draws.
 *
 *  A host that is not active is a skip, not a failure: its absence is a fact
 *  about this box, and a suite that goes red for the machine teaches everyone
 *  to ignore a red suite.
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

function vgml_front_imgs( $post_id ) {

	$got = wp_remote_get( get_permalink( $post_id ), array( 'timeout' => 30 ) );

	if ( is_wp_error( $got ) ) {
		return array( 'err' => $got->get_error_message() );
	}

	$html = (string) wp_remote_retrieve_body( $got );

	return array(
		'gallery'  => substr_count( $html, 'vgml-folder-gallery' ),
		'imgs'     => substr_count( $html, '<img ' ),
		'carousel' => substr_count( $html, 'is-carousel' ),
		'lightbox' => substr_count( $html, 'vgml-lightbox' ),
		'assets'   => ( false !== strpos( $html, 'vergeml-gallery.css' ) && false !== strpos( $html, 'vergeml-gallery.js' ) ),
	);
}

echo "\nthe gallery, through the page builders\n\n";

/* --- the shared pieces ---------------------------------------------------- */

$options = vergeml_gallery_folder_options();
t( 'the folder list is flat with paths in the labels', count( $options ) > 5, count( $options ) . ' folders' );

$nested = array_filter( $options, function ( $label ) {
	return false !== strpos( $label, ' / ' );
} );
t( 'and nested folders say where they live', count( $nested ) > 0, reset( $nested ) ?: '' );

/*
 *  The one translation both hosts use. Divi hands over strings and 'on'/'off';
 *  Elementor strings and 'yes'/''. Both must come out as the renderer's types.
 */
$divi_style = vergeml_gallery_widget_atts( array(
	'folder' => '9048', 'columns' => '5', 'children' => 'off', 'link_to' => 'file', 'order_by' => 'newest', 'limit' => '7',
) );
t( 'divi settings translate', 9048 === $divi_style['folder'] && 5 === $divi_style['columns']
	&& false === $divi_style['children'] && 'file' === $divi_style['linkTo'] && 7 === $divi_style['limit'],
	wp_json_encode( $divi_style ) );

$elementor_style = vergeml_gallery_widget_atts( array( 'folder' => 12, 'children' => 'yes' ) );
t( 'elementor settings translate', true === $elementor_style['children'] && 3 === $elementor_style['columns'] );

$garbage = vergeml_gallery_widget_atts( array( 'folder' => 12, 'columns' => '999', 'link_to' => 'javascript:alert(1)', 'order_by' => 'DROP TABLE' ) );
t( 'nonsense settings come out as the defaults', 8 === $garbage['columns'] && 'none' === $garbage['linkTo'] && 'name' === $garbage['orderBy'],
	wp_json_encode( array( $garbage['columns'], $garbage['linkTo'], $garbage['orderBy'] ) ) );

/* --- something to show ----------------------------------------------------- */

$term = get_term_by( 'slug', 'press', 'media_category' );

if ( ! $term ) {
	$terms = get_terms( array( 'taxonomy' => 'media_category', 'hide_empty' => true, 'number' => 1, 'orderby' => 'count', 'order' => 'DESC' ) );
	$term  = $terms ? $terms[0] : null;
}

t( 'a folder with images', $term && $term->count > 0, $term ? $term->name . ' (' . $term->count . ')' : 'none' );

$folder_id = $term ? (int) $term->term_id : 0;
$expected  = count( vergeml_gallery_query( array( 'folder' => $folder_id, 'children' => true ) ) );

$made = array();

/* --- Elementor ------------------------------------------------------------- */

echo "\nElementor\n";

if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {

	echo "  ---- Elementor is not active here; skipped\n";

} else {

	$widget_types = \Elementor\Plugin::instance()->widgets_manager->get_widget_types( 'vergeml-folder-gallery' );
	t( 'the widget is registered', ! empty( $widget_types ) );

	/*
	 *  Built exactly the way Elementor saves a page: the layout lives in
	 *  _elementor_data, and the post content is beside the point.
	 */
	$data = array( array(
		'id'       => 'vgmltsec',
		'elType'   => 'section',
		'settings' => new stdClass(),
		'elements' => array( array(
			'id'       => 'vgmltcol',
			'elType'   => 'column',
			'settings' => array( '_column_size' => 100 ),
			'elements' => array( array(
				'id'         => 'vgmltwid',
				'elType'     => 'widget',
				'widgetType' => 'vergeml-folder-gallery',
				'settings'   => array( 'folder' => (string) $folder_id, 'columns' => '3', 'size' => 'medium', 'layout' => 'carousel', 'link_to' => 'lightbox' ),
				'elements'   => array(),
			) ),
		) ),
	) );

	$pid = wp_insert_post( array(
		'post_title'  => 'vgml widget probe elementor',
		'post_type'   => 'page',
		'post_status' => 'publish',
	) );

	update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
	update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	update_post_meta( $pid, '_elementor_version', ELEMENTOR_VERSION );

	$made[] = $pid;
	$front  = vgml_front_imgs( $pid );

	t( 'the page renders the gallery', ! isset( $front['err'] ) && $front['gallery'] > 0,
		isset( $front['err'] ) ? $front['err'] : $front['gallery'] . ' markers' );
	t( 'with every image in the folder', isset( $front['imgs'] ) && $front['imgs'] >= $expected,
		( $front['imgs'] ?? '?' ) . ' of ' . $expected );
	t( 'as a carousel with lightbox links', ! empty( $front['carousel'] ) && $front['lightbox'] >= $expected,
		( $front['carousel'] ?? 0 ) . ' carousel, ' . ( $front['lightbox'] ?? 0 ) . ' lightbox links' );
	t( 'and the assets rode along', ! empty( $front['assets'] ) );
}

/* --- Divi ------------------------------------------------------------------- */

echo "\nDivi\n";

if ( ! class_exists( 'ET_Builder_Module' ) && ! defined( 'ET_BUILDER_PLUGIN_DIR' ) ) {

	echo "  ---- Divi is not active here; skipped\n";

} else {

	/*
	 *  Divi renders module shortcodes only inside its own section markup, on a
	 *  post the builder is switched on for. A bare [vergeml_folder_gallery]
	 *  outside that wrapper renders as literal text, which is the mistake this
	 *  arrangement exists to catch.
	 */
	$did = wp_insert_post( array(
		'post_title'   => 'vgml widget probe divi',
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_content' => sprintf(
			'[et_pb_section][et_pb_row][et_pb_column type="4_4"][vergeml_folder_gallery folder="%d" columns="3" size="medium" layout="carousel" link_to="lightbox"][/et_pb_column][/et_pb_row][/et_pb_section]',
			$folder_id
		),
	) );

	update_post_meta( $did, '_et_pb_use_builder', 'on' );

	$made[] = $did;
	$front  = vgml_front_imgs( $did );

	t( 'the page renders the gallery', ! isset( $front['err'] ) && $front['gallery'] > 0,
		isset( $front['err'] ) ? $front['err'] : $front['gallery'] . ' markers' );
	t( 'with every image in the folder', isset( $front['imgs'] ) && $front['imgs'] >= $expected,
		( $front['imgs'] ?? '?' ) . ' of ' . $expected );
	t( 'as a carousel with lightbox links', ! empty( $front['carousel'] ) && $front['lightbox'] >= $expected,
		( $front['carousel'] ?? 0 ) . ' carousel, ' . ( $front['lightbox'] ?? 0 ) . ' lightbox links' );
	t( 'and the assets rode along', ! empty( $front['assets'] ) );
}

/* tidy */
foreach ( $made as $pid ) {
	wp_delete_post( $pid, true );
}

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
