<?php
/**
 *  Which folders carry an earlier profile (_vergeml_filing_profile_prev).
 *
 *      node tools/box-eval.mjs tools/box-prev-profiles.php
 *      node tools/box-eval.mjs tools/box-prev-profiles.php --env VGML_CLEAR=yes
 *
 *  folders.spec:1546 asserts that nothing on the site carries one before its
 *  own second confirm, and the box answers fifteen -- left by fill-walk runs
 *  from before S20's put-back (bb8f6c1). Reads by literal SQL; VGML_CLEAR
 *  removes them and says which folders they were on first.
 *
 *  Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$tax = vergeml_librarian_taxonomy();

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$rows = (array) $wpdb->get_results( $wpdb->prepare(
    "SELECT tm.term_id, t.name, tt.taxonomy, LENGTH( tm.meta_value ) AS bytes
       FROM {$wpdb->termmeta} tm
       JOIN {$wpdb->terms} t ON t.term_id = tm.term_id
       LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
      WHERE tm.meta_key = %s
   ORDER BY tm.term_id ASC",
    VERGEML_FILING_META_PREV
), ARRAY_A );
// phpcs:enable

printf( "%d folders carry an earlier profile\n", count( $rows ) );
foreach ( $rows as $r ) {
    printf( "  %6d  %-34s %-22s %d bytes\n", (int) $r['term_id'], (string) $r['name'], (string) $r['taxonomy'], (int) $r['bytes'] );
}

if ( 'yes' !== (string) getenv( 'VGML_CLEAR' ) ) {
    printf( "\nnothing was removed. VGML_CLEAR=yes to take them off.\n" );
    return;
}

$gone = 0;
foreach ( $rows as $r ) {
    if ( delete_term_meta( (int) $r['term_id'], VERGEML_FILING_META_PREV ) ) {
        $gone++;
    }
}
printf( "\n%d removed; %d left\n", $gone, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = %s", VERGEML_FILING_META_PREV ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
