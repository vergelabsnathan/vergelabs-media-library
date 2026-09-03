<?php
/*
 *  What the library looks like right now, in the terms a customer judges it by.
 *
 *  Run once before a re-describe and once after, and the analysis is a diff of
 *  two files rather than a feeling. Everything here is read-only and costs no
 *  credits except the eight search queries, which embed a phrase each.
 */

global $wpdb;

$table = $wpdb->prefix . 'vergeml_ai_index';
$tax   = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';

$name = function ( $id ) {
    $t = get_the_title( $id );
    return '' === trim( (string) $t ) ? basename( (string) get_attached_file( $id ) ) : (string) $t;
};

// ------------------------------------------------------------ rows by prompt

echo "=== rows by prompt_hash (which prompt described them)\n";

$rows = $wpdb->get_results(
    "SELECT prompt_hash, model, COUNT(*) n,
            SUM(embedding IS NOT NULL) with_embedding,
            SUM(error <> '') errored
       FROM {$table}
      WHERE model <> 'mock'
   GROUP BY prompt_hash, model
   ORDER BY n DESC",
    ARRAY_A
);

foreach ( (array) $rows as $r ) {
    printf( "  %-34s %-18s %4d rows  %4d embedded  %3d errored\n",
        '' === $r['prompt_hash'] ? '(none)' : substr( $r['prompt_hash'], 0, 12 ) . '…',
        $r['model'], (int) $r['n'], (int) $r['with_embedding'], (int) $r['errored'] );
}

// ---------------------------------------------------------- filing fill rates

echo "\n=== filing fields (the catalogue record)\n";

$has_col = (bool) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = %s AND column_name = 'filing'",
    $table
) );

if ( ! $has_col ) {
    echo "  (no filing column yet -- nothing stored)\n";
} else {
    $all = $wpdb->get_col( "SELECT filing FROM {$table} WHERE error = '' AND model <> 'mock' AND filing IS NOT NULL AND filing <> ''" );
    $n   = count( $all );
    $filled = array();
    foreach ( $all as $json ) {
        $f = json_decode( (string) $json, true );
        if ( ! is_array( $f ) ) { continue; }
        foreach ( $f as $k => $v ) {
            if ( '' !== trim( (string) $v ) ) { $filled[ $k ] = ( $filled[ $k ] ?? 0 ) + 1; }
        }
    }
    printf( "  %d rows carry a filing record\n", $n );
    foreach ( array( 'object', 'material', 'colour', 'setting', 'style', 'audience', 'season', 'details' ) as $k ) {
        $c = $filled[ $k ] ?? 0;
        printf( "  %-9s %4d filled  %4d empty  (%3d%%)\n", $k . ':', $c, $n - $c, $n > 0 ? (int) round( 100 * $c / $n ) : 0 );
    }
}

// ------------------------------------------------------- what a folder pulls

echo "\n=== what each folder name would collect (top 5, whole embeddings)\n";

$rows = $wpdb->get_results(
    "SELECT attachment_id, embedding FROM {$table} WHERE error = '' AND embedding IS NOT NULL AND model <> 'mock'",
    ARRAY_A
);
$full = array();
foreach ( (array) $rows as $r ) {
    $v = vergeml_index_vector_out( $r['embedding'] );
    if ( is_array( $v ) && $v ) { $full[ (int) $r['attachment_id'] ] = $v; }
}
printf( "  (%d pictures considered)\n", count( $full ) );

foreach ( array( 'Footwear', 'Furniture', 'Bicycles', "Women's apparel", 'Leather', 'Studio shots', 'Winter', 'Interiors' ) as $folder ) {
    $q = vergeml_meaning_vector( $folder );
    if ( ! is_array( $q ) || ! $q ) { printf( "  %-16s (no vector)\n", $folder ); continue; }
    $s = array();
    foreach ( $full as $id => $v ) { $s[ $id ] = vergeml_meaning_similarity( $q, $v ); }
    arsort( $s );
    $top = array();
    foreach ( array_slice( $s, 0, 5, true ) as $id => $sc ) { $top[] = sprintf( '%.2f %s', $sc, mb_substr( $name( $id ), 0, 30 ) ); }
    printf( "  %-16s %s\n", $folder . ':', implode( ' | ', $top ) );
}

// ------------------------------------------------------------- search top 5

echo "\n=== meaning search, top 5 titles\n";

foreach ( array( 'leather boots', 'white shirt', 'gothic cathedral', 'a bicycle', 'a sofa', 'light bulb', "women's coat", 'denim jeans' ) as $query ) {
    $ids = function_exists( 'vergeml_meaning_search' ) ? vergeml_meaning_search( $query, 5 ) : array();
    $out = array();
    foreach ( (array) $ids as $id ) { $out[] = mb_substr( $name( (int) $id ), 0, 28 ); }
    printf( "  %-18s %s\n", $query . ':', $out ? implode( ' | ', $out ) : '(nothing)' );
}

// ------------------------------------------------------------------ credits

echo "\n=== credits\n";
if ( function_exists( 'vergeml_ai_refresh_credits' ) ) {
    printf( "  balance: %s\n", var_export( vergeml_ai_refresh_credits( true ), true ) );
}
