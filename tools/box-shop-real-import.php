<?php
/**
 *  WooCommerce's own sample catalogue into the real shop (S19).
 *
 *      VGML_CSV=/path/a.csv[,/path/b.csv]  wp eval-file tools/box-shop-real-import.php --url=http://shop.ms2...
 *
 *  Run by tools/box-shop-real-build.sh on the shop subsite. Each CSV goes
 *  through WooCommerce's own CSV importer with its own column map, parsed,
 *  so the products, their categories ("Clothing > Hoodies"), variations,
 *  galleries and featured images land exactly as the Products > Import
 *  screen would put them: every photo fetched into this site's media
 *  library, its post_parent the product, `_wc_attachment_source` the URL it
 *  came from (a photo two products share is fetched once). Spends nothing:
 *  no describe starts on an upload. Prints what came in.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
    echo "WooCommerce is not active on this site\n";
    exit( 1 );
}

$files = array_filter( array_map( 'trim', explode( ',', (string) getenv( 'VGML_CSV' ) ) ) );
if ( ! $files ) {
    echo "VGML_CSV=<csv>[,<csv>] is required\n";
    exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
include_once WC_ABSPATH . 'includes/interfaces/class-wc-importer-interface.php';
include_once WC_ABSPATH . 'includes/import/abstract-wc-product-importer.php';
include_once WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';
include_once WC_ABSPATH . 'includes/admin/importers/class-wc-product-csv-importer-controller.php';

/** The Import screen's own column map, which the controller keeps protected. */
class VGML_Shop_Real_Map extends WC_Product_CSV_Importer_Controller {
    public function map( $headers ) {
        return $this->auto_map_columns( $headers, false );
    }
}

$before = (int) wp_count_posts( 'attachment' )->inherit;
$mapper = new VGML_Shop_Real_Map();

foreach ( $files as $file ) {
    if ( ! is_readable( $file ) ) {
        printf( "unreadable: %s\n", $file );
        exit( 1 );
    }
    $raw     = new WC_Product_CSV_Importer( $file, array( 'lines' => 1 ) );
    $headers = $raw->get_raw_keys();
    $mapping = $mapper->map( $headers );
    $unmapped = array_diff( $headers, array_keys( $mapping ) );

    $importer = WC_Product_CSV_Importer_Controller::get_importer( $file, array(
        'mapping'          => $mapping,
        'parse'            => true,
        'update_existing'  => false,
        'prevent_timeouts' => false,
    ) );
    $result = $importer->import();

    printf(
        "%s: imported %d · updated %d · skipped %d · failed %d%s\n",
        basename( $file ),
        count( $result['imported'] ),
        count( $result['updated'] ),
        count( $result['skipped'] ),
        count( $result['failed'] ),
        $unmapped ? ' · unmapped columns: ' . implode( ', ', $unmapped ) : ''
    );
    foreach ( $result['failed'] as $err ) {
        printf( "  failed: %s\n", is_wp_error( $err ) ? $err->get_error_message() : wp_json_encode( $err ) );
    }
}

$after    = (int) wp_count_posts( 'attachment' )->inherit;
$products = wp_count_posts( 'product' );
$cats     = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'names' ) );
$parented = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} a JOIN {$GLOBALS['wpdb']->posts} p ON p.ID = a.post_parent WHERE a.post_type = 'attachment' AND p.post_type IN ('product','product_variation')" );

printf( "pictures: %d new (%d in the library, %d with a product as parent)\n", $after - $before, $after, $parented );
printf( "products: %d published · %d draft\n", (int) $products->publish, (int) $products->draft );
printf( "categories: %s\n", implode( ', ', is_array( $cats ) ? $cats : array() ) );
