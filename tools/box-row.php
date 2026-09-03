<?php
/* Every row carrying an error: what it is, what we would send, and what the service says to it. Costs one credit per row that succeeds. */
global $wpdb; $t = $wpdb->prefix . 'vergeml_ai_index';
foreach ( $wpdb->get_results( "SELECT attachment_id, error, prompt_hash FROM {$t} WHERE error <> '' AND model <> 'mock' LIMIT 6", ARRAY_A ) as $r ) {
    $id = (int) $r['attachment_id']; $f = get_attached_file( $id ); $m = wp_get_attachment_metadata( $id );
    printf( "#%d  stub=%s  %s  %s  %dKB  %s×%s\n", $id, $r['error'], get_post_mime_type( $id ), basename( (string) $f ), $f && file_exists( $f ) ? filesize( $f ) / 1024 : -1, $m['width'] ?? '?', $m['height'] ?? '?' );
    $p = vergeml_ai_image_payload( $id );
    if ( is_wp_error( $p ) ) { printf( "   payload: ERROR %s\n", $p->get_error_message() ); continue; }
    printf( "   payload: %s, %d KB\n", substr( $p, 0, strpos( $p, ';' ) ), (int) ( ( strlen( $p ) - strpos( $p, ',' ) ) * 0.75 / 1024 ) );
    $d = vergeml_ai_describe( $id );
    printf( "   service: %s\n", is_wp_error( $d ) ? $d->get_error_code() . ' -- ' . $d->get_error_message() : 'ok: ' . mb_substr( $d['caption'], 0, 80 ) );
}
