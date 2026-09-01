<?php
/**
 *  Divi, live on the box. Divi is a theme, so it is switched on for the run
 *  and the previous theme put back after:
 *
 *      wp theme activate Divi
 *      wp eval-file tests/integrations/divi.php <folder-term-id>
 *      wp theme activate twentytwentyfive
 *
 *  The Folder gallery module registers on et_builder_ready and renders through
 *  Divi's shortcode pipeline, so a page built of Divi sections that carries the
 *  module must come out of the front end as a gallery, with no raw shortcode
 *  left in the HTML.
 */

$GLOBALS['vgml_fail'] = 0;
$GLOBALS['vgml_n']    = 0;

function vgml_check( $name, $ok, $detail = '' ) {
    $GLOBALS['vgml_n']++;
    if ( ! $ok ) {
        $GLOBALS['vgml_fail']++;
    }
    echo ( $ok ? '  ok   ' : '  FAIL ' ) . $name . ( '' !== $detail ? '  -- ' . $detail : '' ) . "\n";
}

$folder = isset( $args[0] ) ? (int) $args[0] : 0;
$theme  = wp_get_theme();

echo "\n[divi]\n";
vgml_check( 'Divi is the active theme', 'Divi' === $theme->get( 'Name' ), $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) );

$content = '[et_pb_section fb_built="1"][et_pb_row][et_pb_column type="4_4"]'
    . '[vergeml_folder_gallery folder="' . $folder . '" columns="3" limit="6" layout="grid"]'
    . '[/et_pb_column][/et_pb_row][/et_pb_section]';

$page_id = wp_insert_post( array(
    'post_type'    => 'page',
    'post_status'  => 'publish',
    'post_title'   => 'VGML Divi probe',
    'post_content' => $content,
) );
update_post_meta( $page_id, '_et_pb_use_builder', 'on' );
update_post_meta( $page_id, '_et_pb_page_layout', 'et_full_width_page' );

$response = wp_remote_get( get_permalink( $page_id ), array( 'timeout' => 60, 'sslverify' => false ) );
$html     = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
$code     = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response );

vgml_check( 'the page is served', 200 === $code, 'status ' . $code . ', ' . strlen( $html ) . ' bytes' );
vgml_check( 'Divi built the page', false !== strpos( $html, 'et_pb_section' ) );
vgml_check( 'no raw shortcode leaked into the HTML', false === strpos( $html, '[vergeml_folder_gallery' ) );

$gallery = preg_match( '/class="[^"]*vgml-folder-gallery[^"]*"/', $html, $m );
vgml_check( 'the Folder gallery module rendered', 1 === $gallery, $gallery ? $m[0] : 'no vgml-folder-gallery wrapper' );

preg_match_all( '/<img[^>]+wp-content\/uploads/', $html, $imgs );
vgml_check( 'with images from the folder', count( $imgs[0] ) > 0, count( $imgs[0] ) . ' images' );

wp_delete_post( $page_id, true );

echo "\n" . ( $GLOBALS['vgml_n'] - $GLOBALS['vgml_fail'] ) . '/' . $GLOBALS['vgml_n'] . " passed\n";
if ( $GLOBALS['vgml_fail'] ) {
    exit( 1 );
}
