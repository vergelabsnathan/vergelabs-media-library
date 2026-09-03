<?php
/*
 *  Thirty pictures, described again, shown side by side.
 *
 *  Nothing is written. The point is to see what the new prompt produces
 *  against what is already stored, before six hundred images are re-described
 *  on the strength of an argument.
 *
 *  It costs one credit per picture, and it costs them for real: the service
 *  charges for the call whether or not this script keeps the answer.
 */

global $wpdb;

$table = $wpdb->prefix . 'vergeml_ai_index';

$rows = $wpdb->get_results(
    "SELECT attachment_id, caption, tags
       FROM {$table}
      WHERE error = '' AND caption != '' AND model != 'mock'
   ORDER BY attachment_id ASC
      LIMIT 30",
    ARRAY_A
);

if ( count( $rows ) < 5 ) {
    echo "not enough real descriptions on this box to sample\n";
    return;
}

printf( "%d pictures, re-described with the new prompt. Nothing is saved.\n", count( $rows ) );

$filled = array();
$empty  = array();
$slow   = 0.0;
$ok     = 0;

foreach ( $rows as $i => $row ) {

    $id = (int) $row['attachment_id'];

    $started = microtime( true );
    $new     = vergeml_ai_describe( $id );
    $took    = microtime( true ) - $started;
    $slow   += $took;

    if ( is_wp_error( $new ) ) {
        printf( "\n--- %d  FAILED: %s\n", $id, $new->get_error_message() );
        continue;
    }

    $ok++;

    // Only the first six are printed in full; the rest are counted.
    if ( $i < 6 ) {
        printf( "\n--- %d  (%.1fs)  %s\n", $id, $took, get_the_title( $id ) );
        printf( "  BEFORE caption: %s\n", mb_substr( (string) $row['caption'], 0, 150 ) );
        printf( "  AFTER  caption: %s\n", mb_substr( (string) $new['caption'], 0, 150 ) );

        if ( isset( $new['filing'] ) && is_array( $new['filing'] ) ) {
            foreach ( $new['filing'] as $field => $value ) {
                printf( "    %-9s %s\n", $field . ':', '' === $value ? '(empty)' : mb_substr( (string) $value, 0, 110 ) );
            }
        } else {
            printf( "    (no filing came back)\n" );
        }
    }

    if ( isset( $new['filing'] ) && is_array( $new['filing'] ) ) {
        foreach ( $new['filing'] as $field => $value ) {
            if ( '' === trim( (string) $value ) ) {
                $empty[ $field ] = isset( $empty[ $field ] ) ? $empty[ $field ] + 1 : 1;
            } else {
                $filled[ $field ] = isset( $filled[ $field ] ) ? $filled[ $field ] + 1 : 1;
            }
        }
    }
}

printf( "\n\n=== how often each field was filled, across %d pictures\n\n", $ok );

foreach ( array( 'object', 'material', 'colour', 'setting', 'style', 'audience', 'season', 'details' ) as $field ) {
    $f = isset( $filled[ $field ] ) ? $filled[ $field ] : 0;
    printf( "  %-9s %2d filled, %2d left empty\n", $field . ':', $f, $ok - $f );
}

printf( "\naverage %.1fs per picture, %d credits spent\n", $ok > 0 ? $slow / $ok : 0, $ok );
printf( "\naudience and season SHOULD be mostly empty -- a product shot does not\n" );
printf( "show who it is for or what season it is, and guessing is what fills a\n" );
printf( "folder with the wrong pictures.\n" );
