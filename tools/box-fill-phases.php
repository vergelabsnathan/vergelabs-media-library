<?php
/**
 *  Where a one-slice fill's seconds go, before the row loop can beat.
 *
 *      node tools/box-eval.mjs tools/box-fill-phases.php --site realshop
 *
 *  "Filling 0 of 33 - 15 s" stands still on the shop (S19). The heartbeat is
 *  inside the row loop (core/folder-talk.php, every two seconds since S10.0),
 *  so it cannot fire until the loop starts. This runs the slice's setup in the
 *  order the fill runs it and times each phase, so the row's standstill can be
 *  attributed rather than guessed at.
 *
 *  Moves nothing: the picks are computed and thrown away. The only outward
 *  call is the text model's, which is metered and not debited, and only for
 *  rows no product claimed. VGML_ASK=0 leaves even that alone.
 *
 *  Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$tax   = vergeml_librarian_taxonomy();
$nodes = vergeml_folders_nodes( $tax );
$ids   = array_map( function ( $n ) { return (int) $n['id']; }, (array) $nodes );

if ( ! $ids ) {
    echo "no folders on this site: confirm a tree first\n";
    return;
}

$ask = '0' !== (string) getenv( 'VGML_ASK' );

printf( "folders %d\n", count( $ids ) );

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

$phase = array();
$q     = array();
$mark  = function ( $label ) use ( &$phase, &$q, &$last, &$lastq ) {
    global $wpdb;
    $now           = microtime( true );
    $phase[ $label ] = $now - $last;
    $q[ $label ]     = (int) $wpdb->num_queries - $lastq;
    $last          = $now;
    $lastq         = (int) $wpdb->num_queries;
};

$last  = microtime( true );
$lastq = (int) $wpdb->num_queries;
$t_all = $last;

$profiles = vergeml_filing_profiles( $ids, $tax );
$mark( 'the folder profiles are seeded' );

$words   = vergeml_filing_words_sql( 'i' );
$product = vergeml_filing_product_sql( 'i' );
$rows    = (array) $wpdb->get_results( $wpdb->prepare(
    "SELECT i.attachment_id, i.embedding, i.tags, i.filing, i.kind, pm.meta_value AS placed_by, {$words['select']}, {$product['select']}
       FROM {$wpdb->vergeml_ai_index} i
       LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = i.attachment_id AND pm.meta_key = %s
       {$words['join']} {$product['join']}
      WHERE i.error = '' AND i.embedding IS NOT NULL
   ORDER BY i.attachment_id ASC",
    VERGEML_FILING_PLACED_BY
), ARRAY_A );
$mark( 'the slice is read' );

$rows = vergeml_filing_product_folders( $rows, $profiles );
$mark( 'the products name their folders' );

$claimed = 0;
foreach ( $rows as $r ) {
    if ( ! empty( $r['product_folder'] ) || in_array( (string) ( isset( $r['placed_by'] ) ? $r['placed_by'] : '' ), array( 'user', 'answer', 'product' ), true ) ) {
        $claimed++;
    }
}

if ( $ask && function_exists( 'vergeml_filing_ask_model' ) ) {
    foreach ( array_chunk( $rows, 40, true ) as $chunk ) {
        vergeml_filing_ask_model( $chunk, $profiles );
    }
    $mark( 'the text model is asked about the rest' );
} else {
    $mark( 'the text model was not asked (VGML_ASK=0)' );
}

$counted = vergeml_filing_count( $profiles, $rows );
$mark( 'the picks are counted' );

$total = microtime( true ) - $t_all;

printf( "\n%-46s %8s %8s\n", 'phase', 'seconds', 'queries' );
foreach ( $phase as $label => $secs ) {
    printf( "%-46s %8.2f %8d\n", $label, $secs, $q[ $label ] );
}
printf( "%-46s %8.2f\n", 'ALL of it', $total );

printf( "\npictures %d - already claimed %d (a product, a person, an answer) - left for the matcher %d\n", count( $rows ), $claimed, count( $rows ) - $claimed );
printf( "of the total, the row loop's own share is the last line; everything above it is before the first beat can fire\n" );
printf( "counted: fits %d (product %d) - nothing %d - kept %d\n", (int) $counted['counts']['fits'], (int) $counted['counts']['product'], (int) $counted['counts']['nothing'], (int) $counted['counts']['kept'] );
// phpcs:enable
