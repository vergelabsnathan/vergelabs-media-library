<?php
// wp eval-file probe: why does the caption search miss?

if ( ! defined( 'WP_ADMIN' ) ) {
    define( 'WP_ADMIN', true );
}

$settings = vergeml_ai_settings();
echo 'enrich_search: ' . var_export( $settings['enrich_search'], true ) . "\n";
echo 'is_admin(): ' . var_export( is_admin(), true ) . "\n";

$probe = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0];
update_post_meta( $probe, '_vergeml_ai', array( 'caption' => 'zzqxunique lighthouse', 'tags' => array( 'zzqxunique' ), 'time' => time() ) );
echo "probe id: {$probe}\n";

add_filter( 'posts_request', function ( $sql ) {
    echo "SQL: " . substr( $sql, 0, 900 ) . "\n";
    return $sql;
} );

$q = new WP_Query( array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    's'              => 'zzqxunique',
    'posts_per_page' => 5,
    'fields'         => 'ids',
) );

echo 'results: ' . wp_json_encode( $q->posts ) . "\n";
delete_post_meta( $probe, '_vergeml_ai' );
