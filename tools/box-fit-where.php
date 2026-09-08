<?php
/**
 *  Where the dry run's 44 seconds go, measured rather than guessed.
 *
 *      wp eval-file tools/box-fit-where.php --allow-root
 *
 *  Warm and cold cost the same and a warm run makes 3 queries, so it is
 *  neither the /embed calls nor the database: it is arithmetic. This counts
 *  the arithmetic. Two candidates, and they are the only two:
 *
 *    - one cosine per folder per picture, profile vector against picture
 *      vector (vergeml_filing_pick, the tie-break);
 *    - one vergeml_filing_class_match() per class pair per folder per
 *      picture, which falls through to a cosine of two phrase vectors
 *      whenever the two phrases do not match as strings.
 *
 *  It changes nothing and writes nothing but the folder profile cache.
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
      WHERE error = '' AND embedding IS NOT NULL ORDER BY attachment_id ASC",
    ARRAY_A
);

printf( "%d pictures, %d folders with a profile\n", count( $rows ), count( $profiles ) );

// How many class-phrase pairs the run asks about, and how many are distinct.
$pairs    = 0;
$distinct = array();
$dims     = 0;
$facts    = array();

foreach ( $rows as $r ) {
    $f        = vergeml_filing_facts( $r );
    $facts[]  = $f;
    $dims     = $dims ? $dims : ( is_array( $f['vector'] ) ? count( $f['vector'] ) : 0 );
    foreach ( $profiles as $p ) {
        foreach ( (array) $f['classes'] as $pc ) {
            foreach ( (array) $p['classes'] as $fc ) {
                $pairs++;
                $distinct[ mb_strtolower( $pc ) . '|' . mb_strtolower( $fc ) ] = true;
            }
        }
    }
}

printf( "vector dimensions      %d\n", $dims );
printf( "class pairs asked      %s\n", number_format( $pairs ) );
printf( "class pairs distinct   %s\n", number_format( count( $distinct ) ) );
printf( "folder comparisons     %s\n", number_format( count( $rows ) * count( $profiles ) ) );

// What one cosine costs at this dimension, timed over the real pairs.
$vectors = array_values( array_filter( array_map( function ( $p ) { return $p['vector']; }, $profiles ) ) );
$sample  = array_slice( array_filter( array_map( function ( $f ) { return $f['vector']; }, $facts ) ), 0, 200 );
$t       = microtime( true );
$n       = 0;
foreach ( $sample as $v ) {
    foreach ( $vectors as $w ) {
        vergeml_meaning_similarity( $v, $w );
        $n++;
    }
}
$per = ( microtime( true ) - $t ) / max( 1, $n );
printf( "one cosine             %.3f ms\n", $per * 1000 );
printf( "  the tie-break costs  %.1f s over the run\n", $per * count( $rows ) * count( $profiles ) );

// How many of the asked pairs actually reach a cosine (no string match).
$falls = 0;
foreach ( array_keys( $distinct ) as $key ) {
    list( $a, $b ) = explode( '|', $key, 2 );
    if ( $a === $b || rtrim( $a, 's' ) === rtrim( $b, 's' ) ) {
        continue;
    }
    if ( false !== mb_strpos( ' ' . $a . ' ', ' ' . $b . ' ' ) || false !== mb_strpos( ' ' . $b . ' ', ' ' . $a . ' ' ) ) {
        continue;
    }
    $falls++;
}
printf( "distinct pairs that reach a cosine  %s of %s\n", number_format( $falls ), number_format( count( $distinct ) ) );
printf( "  class matching costs %.1f s over the run, and %.1f s if each distinct pair were computed once\n",
    $per * $pairs * ( $falls / max( 1, count( $distinct ) ) ),
    $per * $falls
);
