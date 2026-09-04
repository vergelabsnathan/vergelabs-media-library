<?php
/*
 *  Why the missing-alt run cannot finish: the pictures it keeps failing on,
 *  what their rows hold, and what one step says about them. One step of
 *  the real run, so at most six credits if any of them succeed now.
 */
global $wpdb;
$t   = $wpdb->prefix . 'vergeml_ai_index';
$ids = vergeml_ai_pending( 'missing-alt', 20 );
printf( "pending missing-alt: %s\n", wp_json_encode( $ids ) );
foreach ( $ids as $id ) {
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT model, error, alt, caption, described_at, prompt_hash FROM {$t} WHERE attachment_id = %d", $id ), ARRAY_A );
    printf( "#%d %s %s | row: %s\n", $id, get_post_mime_type( $id ), basename( (string) get_attached_file( $id ) ),
        $row ? sprintf( 'model=%s error=[%s] alt=[%s] caption=[%s] at=%s', $row['model'], $row['error'], mb_substr( (string) $row['alt'], 0, 60 ), mb_substr( (string) $row['caption'], 0, 40 ), $row['described_at'] ) : 'NO ROW' );
}
$r = vergeml_ai_index_step( 'missing-alt', 6, true );
printf( "step: described=%d remaining=%d errors=%s\n", count( $r['described'] ), (int) $r['remaining'], wp_json_encode( $r['errors'] ) );

echo "\n== folders now ==\n";
foreach ( get_terms( array( 'taxonomy' => 'media_category', 'hide_empty' => false ) ) as $term ) {
    printf( "%-32s %4d  parent=%d\n", $term->name, $term->count, $term->parent );
}
