<?php
/**
 *  Where every picture would go today, and nothing written.
 *
 *      wp eval-file tools/box-filing-baseline.php --allow-root
 *
 *  The traces work records the reason a picture moved. It must not change
 *  where a single picture goes, and an assertion that big needs both ends:
 *  this is the first end. It runs the matcher over the whole library against
 *  today's folders and prints one line per picture. It moves nothing: no
 *  term, no move row, no state. The one thing it can write is a folder's own
 *  profile cache, which vergeml_filing_profiles() fills on first ask for any
 *  caller alike -- so a second run is reading what the first one built, which
 *  is what every real run does too. Run this again after the last phase and
 *  the two files must be identical, byte for byte.
 *
 *  Sorted by attachment id and printed to six decimals, so a diff is a real
 *  diff rather than float noise or a changed row order.
 *
 *  Lives in tools/, which is export-ignored, so nothing here ships.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_filing_pick' ) ) {
    echo "core/filing.php is not loaded\n";
    return;
}

global $wpdb;

$taxonomy = vergeml_librarian_taxonomy();

if ( '' === $taxonomy ) {
    echo "no folder taxonomy on this site\n";
    return;
}

/*
 *  Every folder that exists, in id order. The order matters: the matcher
 *  walks the profiles it is handed, and two runs that disagree about the
 *  order can disagree about a tie.
 */
$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
$terms = is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
sort( $terms );

$profiles = $terms ? vergeml_filing_profiles( $terms, $taxonomy ) : array();

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
$rows = (array) $wpdb->get_results(
    "SELECT attachment_id, embedding, kind, filing
       FROM {$wpdb->vergeml_ai_index}
      WHERE error = '' AND embedding IS NOT NULL
   ORDER BY attachment_id ASC",
    ARRAY_A
);

printf( "# filing baseline, taxonomy %s\n", $taxonomy );
printf( "# folders %d, pictures %d\n", count( $profiles ), count( $rows ) );
echo "# attachment\tterm_id\twhy\tscore\trunner_up\trunner_score\n";

// A first fill's picks: a hand placement is picked like any other, so the tally is the engine's and not the owner's.
foreach ( $rows as $k => $r ) {
    $rows[ $k ]['placed_by'] = '';
}
$counted = vergeml_filing_count( $profiles, $rows );
$t       = $counted['counts'];

foreach ( $rows as $r ) {

    $pick = $counted['picks'][ (int) $r['attachment_id'] ];
    $why  = isset( $pick['why'] ) ? (string) $pick['why'] : 'floor';

    printf(
        "%d\t%d\t%s\t%.6f\t%d\t%.6f\n",
        (int) $r['attachment_id'],
        (int) $pick['term_id'],
        $why,
        (float) $pick['score'],
        (int) $pick['runner_up'],
        (float) $pick['runner_score']
    );
}

/*
 *  The second band (every-picture-a-home C.1): the outcome tally of a first
 *  fill over this library. tools/filing-baseline-check.mjs fails when any
 *  outcome moves more than 3 % of the pictures -- an engine, prompt or
 *  planner change that shifts what the fill does is news, whatever it does
 *  to a single score.
 */
printf( "# tally looked %d fits %d sure %d likely %d siblings %d nothing %d floor %d margin %d either %d gated %d kept %d\n",
    $t['looked'], $t['fits'], $t['sure'], $t['likely'], $t['siblings'], $t['nothing'], $t['why']['floor'], $t['why']['margin'], (int) $t['either'], $t['why']['gated'], $t['kept'] );
