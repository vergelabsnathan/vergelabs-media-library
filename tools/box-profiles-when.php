<?php
/**
 *  When each folder's profile was built, and what it is built from.
 *
 *      wp eval-file tools/box-profiles-when.php --allow-root
 *
 *  The filing baseline drifted by one picture on 2026-09-08 and it was not
 *  the request-scoped memo (proved: with and without it the box answers
 *  byte-identically). The service is deterministic too -- the same phrase
 *  returns the same 512 floats every time. That leaves the profiles: a folder
 *  whose profile was rebuilt from a different text scores every picture a
 *  little differently, and a picture inside the tie-break's 0.03 changes
 *  folder without any rule changing.
 *
 *  So this prints, per folder, when its profile was built and what text it
 *  was built from. A built_at later than the baseline names the folder that
 *  moved, and the source says whether it came from a plan or from its name.
 *
 *  Reads only. Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$taxonomy = vergeml_librarian_taxonomy();
$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

if ( is_wp_error( $terms ) ) {
    echo "no folders\n";
    return;
}

printf( "%-6s %-28s %-6s %-20s %s\n", 'id', 'name', 'from', 'built', 'text' );

$rows = array();
foreach ( $terms as $t ) {
    $meta = get_term_meta( (int) $t->term_id, VERGEML_FILING_META, true );
    if ( ! is_array( $meta ) ) {
        printf( "%-6d %-28s %-6s %-20s %s\n", $t->term_id, mb_substr( $t->name, 0, 28 ), '-', 'no profile', '' );
        continue;
    }
    $rows[] = array( (int) $meta['built_at'], (int) $t->term_id, (string) $t->name, (string) $meta['source'], (string) $meta['text'] );
}

usort( $rows, function ( $a, $b ) { return $a[0] <=> $b[0]; } );

foreach ( $rows as $r ) {
    printf( "%-6d %-28s %-6s %-20s %s\n", $r[1], mb_substr( $r[2], 0, 28 ), $r[3], gmdate( 'Y-m-d H:i:s', $r[0] ), mb_substr( $r[4], 0, 70 ) );
}

printf( "\nnow (UTC)   %s\n", gmdate( 'Y-m-d H:i:s' ) );
printf( "folders     %d\n", count( $terms ) );
