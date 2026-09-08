<?php
/**
 *  The second half of the same question: the cosines account for 7 seconds of
 *  44, so the rest is the wrapper around them. This times the real functions
 *  on real arguments rather than the arithmetic in isolation.
 *
 *      wp eval-file tools/box-fit-where2.php --allow-root
 *
 *  Changes nothing, writes nothing but the folder profile cache.
 *  Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$taxonomy = vergeml_librarian_taxonomy();
$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
$profiles = vergeml_filing_profiles( $terms, $taxonomy );

$rows = (array) $wpdb->get_results(
    "SELECT attachment_id, kind, filing, embedding, tags FROM {$wpdb->vergeml_ai_index}
      WHERE error = '' AND embedding IS NOT NULL ORDER BY attachment_id ASC LIMIT 100",
    ARRAY_A
);

// vergeml_filing_facts(), on its own: it decodes JSON and inflates a vector.
$t = microtime( true );
$facts = array();
foreach ( $rows as $r ) {
    $facts[] = vergeml_filing_facts( $r );
}
printf( "facts        %.1f ms per picture  (%.1f s over 641)\n",
    ( microtime( true ) - $t ) * 1000 / count( $rows ),
    ( microtime( true ) - $t ) / count( $rows ) * 641 );

// vergeml_filing_pick(), the whole thing.
$t = microtime( true );
foreach ( $facts as $f ) {
    vergeml_filing_pick( $f, $profiles );
}
$pick = ( microtime( true ) - $t ) / count( $facts );
printf( "pick         %.1f ms per picture  (%.1f s over 641)\n", $pick * 1000, $pick * 641 );

// One vergeml_filing_class_match() on a pair that reaches the cosine.
$a = 'landscape';
$b = 'footwear';
$t = microtime( true );
for ( $i = 0; $i < 2000; $i++ ) {
    vergeml_filing_class_match( $a, $b );
}
$one = ( microtime( true ) - $t ) / 2000;
printf( "class_match  %.3f ms each     (%.1f s over the 102,007 the run asks)\n", $one * 1000, $one * 102007 );

// And the transient lookup inside it, on its own.
$t = microtime( true );
for ( $i = 0; $i < 2000; $i++ ) {
    vergeml_meaning_vector( $a );
}
$mv = ( microtime( true ) - $t ) / 2000;
printf( "meaning_vector %.3f ms each   (%.1f s over the 204,014 the run asks)\n", $mv * 1000, $mv * 204014 );

printf( "\nobject cache: %s\n", wp_using_ext_object_cache() ? 'persistent' : 'per-request only' );
