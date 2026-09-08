<?php
/**
 *  Does the same phrase come back as the same vector?
 *
 *      wp eval-file tools/box-embed-stable.php --allow-root
 *
 *  The filing baseline is the assertion the traces work rests on: run the
 *  matcher before and after, and the two files must be identical. On
 *  2026-09-08 they were not -- one picture of 641 moved from ok to margin,
 *  and a hundred rows shifted in the fourth decimal of the runner-up's score.
 *  It was not the request-scoped memo added that day: with it and without it
 *  the box gives byte-identical answers.
 *
 *  So the question is underneath: a folder's profile vector is rebuilt
 *  whenever its term is edited (vergeml_filing_on_term_change), and a rebuild
 *  asks the service for the vector again. If the service does not answer the
 *  same phrase with the same floats, then every profile rebuild moves every
 *  score a little, and a picture sitting within the tie-break's 0.03 changes
 *  folder without anybody changing a rule.
 *
 *  This asks twice, with the cache cleared between, and prints the largest
 *  difference. Two /embed calls, no credits charged.
 *
 *  Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$text = 'Landscape and nature | object: landscape; mountain; water';
$slot = 'vergeml_qv2_' . md5( strtolower( $text ) );

delete_transient( $slot );
$a = vergeml_meaning_vector( $text );

if ( ! is_array( $a ) ) {
    echo "the service did not answer\n";
    return;
}

// The memo holds it for the rest of the request, so the second ask has to be
// a fresh process. This one is measured across the transient only; the sister
// script runs it twice from the shell for the stronger answer.
echo "dimensions             " . count( $a ) . "\n";
echo "first vector, head     " . implode( ', ', array_map( function ( $f ) { return sprintf( '%.9f', $f ); }, array_slice( $a, 0, 4 ) ) ) . "\n";
echo "checksum               " . md5( wp_json_encode( $a ) ) . "\n";
