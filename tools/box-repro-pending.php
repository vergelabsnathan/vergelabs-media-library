<?php
/* Describe exactly the rows a run cannot get past, and print what the service says. */
$ids = vergeml_ai_pending( 'stale', 8 );
if ( ! $ids ) { $ids = vergeml_ai_pending( 'unindexed', 8 ); }
printf( "pending ids: %s\n", $ids ? implode( ', ', $ids ) : '(none)' );
foreach ( $ids as $id ) {
    $f = get_attached_file( $id ); $sz = $f && file_exists( $f ) ? round( filesize( $f ) / 1024 ) : -1;
    $m = wp_get_attachment_metadata( $id );
    printf( "  #%-6d %-32s %5dKB %s×%s %s\n", $id, mb_substr( get_the_title( $id ), 0, 30 ), $sz, $m['width'] ?? '?', $m['height'] ?? '?', get_post_mime_type( $id ) );
    $t = microtime( true ); $r = vergeml_ai_describe( $id );
    printf( "      -> %.1fs  %s\n", microtime( true ) - $t, is_wp_error( $r ) ? $r->get_error_code() . ': ' . $r->get_error_message() : 'ok' );
}
