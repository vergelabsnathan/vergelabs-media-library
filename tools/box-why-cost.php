<?php
/**
 *  What "why is it here" costs the media grid, and what the guard saves.
 *
 *  attachment_fields_to_edit is not only the details panel. WordPress runs it
 *  through get_compat_media_markup() inside wp_prepare_attachment_for_js(),
 *  and the grid's own `query-attachments` request prepares a whole page of
 *  attachments in one go -- so a filter that costs two queries a picture costs
 *  two hundred on a listing that never shows the field.
 *
 *  This measures both sides on real rows: the cost of preparing N attachments
 *  as a listing does, and the same N with DOING_AJAX and the listing's action
 *  set, which is what the guard in vergeml_why_here_field() reads.
 *
 *      scp tools/box-why-cost.php root@box:/tmp/vgml-why-cost.php
 *      bash tools/box-why-cost.sh
 *
 *  Read-only. It prepares attachments for JavaScript and prints numbers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

if ( ! function_exists( 'vergeml_librarian_why' ) ) {
    echo "this build has no vergeml_librarian_why()\n";
    exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$n = 20;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ids = (array) $wpdb->get_col( $wpdb->prepare(
    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' ORDER BY ID DESC LIMIT %d",
    $n
) );

if ( count( $ids ) < 2 ) {
    echo "not enough pictures on this box to measure\n";
    exit( 1 );
}

function vgml_cost_prepare( $ids ) {
    global $wpdb;
    // Warm the caches a listing warms, so the number is the filter's and not the post's.
    _prime_post_caches( $ids, false, true );
    $before = (int) $wpdb->num_queries;
    foreach ( $ids as $id ) {
        wp_prepare_attachment_for_js( get_post( (int) $id ) );
    }
    return (int) $wpdb->num_queries - $before;
}

$open = vgml_cost_prepare( $ids );

/*
 *  And again as the listing asks for it. The guard reads wp_doing_ajax() and
 *  the request's action, so both are what the grid sends.
 */
if ( ! defined( 'DOING_AJAX' ) ) {
    define( 'DOING_AJAX', true );
}

$_REQUEST['action'] = 'query-attachments';

$listing = vgml_cost_prepare( $ids );

printf( "%d pictures prepared\n\n", count( $ids ) );
printf( "  as the details panel does   %4d queries\n", $open );
printf( "  as the grid's listing does  %4d queries\n", $listing );
printf( "\n  the listing is spared %d queries, %.1f a picture\n", $open - $listing, ( $open - $listing ) / max( 1, count( $ids ) ) );
