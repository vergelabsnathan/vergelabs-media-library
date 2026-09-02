<?php
global $wpdb;
$ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts}
      WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
);
$missing = 0;
foreach ( (array) $ids as $id ) {
    $meta = wp_get_attachment_metadata( (int) $id );
    if ( ! is_array( $meta ) || empty( $meta['sizes']['thumbnail'] ) ) {
        $missing++;
    }
}
printf( "%d of %d images have no thumbnail\n", $missing, count( (array) $ids ) );
