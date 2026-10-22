<?php
// Fixtures for the compatibility matrix. Deleted after use.
// Titles deliberately share no substring with the category names, so a search
// hit on "Logos" can only come from the taxonomy join.

require_once ABSPATH . 'wp-admin/includes/image.php';

foreach ( array( 'Logos', 'Photos' ) as $name ) {
    if ( ! term_exists( $name, 'vergeml_folder' ) ) {
        wp_insert_term( $name, 'vergeml_folder' );
    }
}

$logos  = get_term_by( 'name', 'Logos', 'vergeml_folder' );
$photos = get_term_by( 'name', 'Photos', 'vergeml_folder' );

$fixtures = array(
    array( 'Zephyr mark', $logos ),
    array( 'Quartz emblem', $logos ),
    array( 'Harbour at dusk', $photos ),
    array( 'Ridgeline study', $photos ),
);

$dir = wp_upload_dir();

foreach ( $fixtures as $i => $fixture ) {

    list( $title, $term ) = $fixture;

    $im = imagecreatetruecolor( 320, 200 );
    imagefill( $im, 0, 0, imagecolorallocate( $im, 50 + $i * 40, 100, 150 - $i * 20 ) );
    $path = trailingslashit( $dir['path'] ) . 'compat-' . $i . '.png';
    imagepng( $im, $path );
    imagedestroy( $im );

    $id = wp_insert_attachment(
        array( 'post_title' => $title, 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ),
        $path
    );
    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );

    if ( $term && ! is_wp_error( $term ) ) {
        wp_set_object_terms( $id, array( (int) $term->term_id ), 'vergeml_folder' );
    }
}

// a second author so the author filter renders
if ( ! get_user_by( 'login', 'editor2' ) ) {
    wp_insert_user( array( 'user_login' => 'editor2', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
}

$options = get_option( 'vergeml_lib_options', array() );
$options['search_in']       = array( 'titles', 'captions', 'descriptions', 'filenames', 'authors', 'taxonomies' );
$options['filters_to_show'] = array( 'types', 'dates', 'authors', 'taxonomies' );
update_option( 'vergeml_lib_options', $options );

echo "seeded 4 attachments, 2 terms, 1 extra author\n";
