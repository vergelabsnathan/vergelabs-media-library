# A permanent reference page on the box: three folder galleries inside Elementor
# (grid of four, grid of five with a lightbox, carousel of three), on the
# fullest folder the library has. Re-running refreshes the same page.
set -e
cd /var/www/wp
cat > /tmp/vgml-gallery-page.php <<'PHP'
<?php
global $wpdb;
$folder = (int) $wpdb->get_var(
    "SELECT tt.term_id FROM {$wpdb->term_taxonomy} tt
      WHERE tt.taxonomy = 'media_category' ORDER BY tt.count DESC LIMIT 1"
);
$name = $folder ? get_term( $folder )->name : '(none)';
printf( "folder: #%d %s\n", $folder, $name );

$widget = function ( $id, $extra ) use ( $folder ) {
    return array(
        'id' => $id, 'elType' => 'widget', 'widgetType' => 'vergeml-folder-gallery',
        'settings' => array_merge( array( 'folder' => (string) $folder, 'layout' => 'grid', 'children' => 'yes', 'columns' => '3', 'limit' => 8, 'order_by' => 'name', 'size' => 'large', 'link_to' => 'none' ), $extra ),
        'elements' => array(),
    );
};
$heading = function ( $id, $text ) {
    return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => $text, 'header_size' => 'h3' ), 'elements' => array() );
};
$section = function ( $id, $children ) {
    return array( 'id' => $id, 'elType' => 'section', 'settings' => array(), 'elements' => array(
        array( 'id' => $id . 'c', 'elType' => 'column', 'settings' => array( '_column_size' => 100 ), 'elements' => $children ),
    ) );
};
$data = array(
    $section( 'a1', array( $heading( 'a1h', 'Grid, four columns' ), $widget( 'a1g', array( 'columns' => '4' ) ) ) ),
    $section( 'a2', array( $heading( 'a2h', 'Grid, five columns, lightbox' ), $widget( 'a2g', array( 'columns' => '5', 'limit' => 10, 'link_to' => 'lightbox' ) ) ) ),
    $section( 'a3', array( $heading( 'a3h', 'Carousel, three across' ), $widget( 'a3g', array( 'layout' => 'carousel', 'columns' => '3', 'limit' => 9 ) ) ) ),
);

$page = get_page_by_path( 'vgml-gallery-probe', OBJECT, 'page' );
$post = array( 'post_title' => 'VGML gallery probe', 'post_name' => 'vgml-gallery-probe', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '' );
$id   = $page ? wp_update_post( array_merge( $post, array( 'ID' => $page->ID ) ) ) : wp_insert_post( $post );

update_post_meta( $id, '_elementor_edit_mode', 'builder' );
update_post_meta( $id, '_elementor_template_type', 'wp-page' );
update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
delete_post_meta( $id, '_elementor_css' );
if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }

printf( "page: #%d %s\n", $id, get_permalink( $id ) );
PHP
wp eval-file /tmp/vgml-gallery-page.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
rm -f /tmp/vgml-gallery-page.php
