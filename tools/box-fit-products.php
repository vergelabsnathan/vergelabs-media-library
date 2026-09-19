<?php
/**
 *  What the Tree step's estimate misses on a site that sells.
 *
 *      node tools/box-eval.mjs tools/box-fit-products.php --site realshop
 *
 *  The draft the categories button makes (one path a folder, all new), counted
 *  twice: once as vergeml_guide_draft_fit() counts it today, and once with the
 *  one step the fill takes and the dry run does not --
 *  vergeml_filing_product_folders(). Prints both, and what each costs.
 *
 *  Writes nothing. Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

if ( ! function_exists( 'vergeml_guide_draft_fit' ) || ! function_exists( 'vergeml_folders_product_paths' ) ) {
    echo "core/guide.php is not loaded\n";
    return;
}

$taxonomy = vergeml_librarian_taxonomy();
$cats     = vergeml_folders_product_paths();

if ( ! $cats['paths'] ) {
    echo "this site sells nothing\n";
    return;
}

printf( "live folders           %d\n", count( vergeml_folders_nodes( $taxonomy ) ) );
printf( "product categories     %d\n", (int) $cats['total'] );

// The draft the press makes: every category path a new folder, a parent before its child.
$by_path = array();
$folders = array();
foreach ( $cats['paths'] as $p ) {
    $trail = implode( ' > ', $p );
    $up    = count( $p ) > 1 ? implode( ' > ', array_slice( $p, 0, -1 ) ) : '';
    $key   = 'c' . count( $folders );
    $by_path[ $trail ] = $key;
    $folders[] = array(
        'key'      => $key,
        'term_id'  => null,
        'name'     => (string) end( $p ),
        'parent'   => '' !== $up && isset( $by_path[ $up ] ) ? $by_path[ $up ] : '',
        'count'    => null,
        'matches'  => '',
        'classes'  => array(),
        'kinds'    => array(),
        'audience' => '',
        'by'       => '',
    );
}
$draft = array( 'folders' => $folders, 'gone' => array(), 'tags' => array(), 'origin' => 'talk', 'rule' => null );

printf( "draft folders          %d\n\n", count( $folders ) );

/* ------------------------------------------------ today, as the screen reads it */

$q0  = (int) $wpdb->num_queries;
$t0  = microtime( true );
$fit = vergeml_guide_draft_fit( $draft, $taxonomy );
$t1  = microtime( true );
$q1  = (int) $wpdb->num_queries;

if ( ! $fit ) {
    echo "the dry run answered nothing\n";
    return;
}

$why  = $fit['unfiled'];
$out  = (int) $why['floor'] + (int) $why['margin'] + (int) $why['gated'];
printf( "TODAY  looked %d · %d would be placed · %d would stay unfiled (floor %d, margin %d, gated %d)\n", (int) $fit['looked'], max( 0, (int) $fit['looked'] - $out ), $out, (int) $why['floor'], (int) $why['margin'], (int) $why['gated'] );
printf( "       fits %d (product %d) · siblings %d · nothing %d · kept %d\n", (int) $fit['tally']['fits'], (int) $fit['tally']['product'], (int) $fit['tally']['siblings'], (int) $fit['tally']['nothing'], (int) $fit['tally']['kept'] );
printf( "       %.2f s, %d queries\n\n", $t1 - $t0, $q1 - $q0 );

/* ------------------------------ the same count, with the fill's product step */

$live     = vergeml_guide_live_index( $taxonomy );
$key_of   = array();
$profiles = array();
$n        = 0;
$paths    = array();
$by_key   = array();
foreach ( $draft['folders'] as $f ) {
    $by_key[ (string) $f['key'] ] = $f;
}
foreach ( $draft['folders'] as $f ) {
    $path = array();
    $walk = (string) $f['key'];
    $g    = 0;
    while ( isset( $by_key[ $walk ] ) && $g++ < 64 ) {
        array_unshift( $path, (string) $by_key[ $walk ]['name'] );
        $walk = (string) $by_key[ $walk ]['parent'];
    }
    $paths[ (string) $f['key'] ] = $path;
}
foreach ( $draft['folders'] as $f ) {
    $p = vergeml_guide_draft_profile( $f, $paths[ (string) $f['key'] ], $live, $taxonomy );
    if ( ! is_array( $p ) ) {
        continue;
    }
    $n++;
    $p['term_id']    = $n;
    $p['parent_id']  = 0;
    $profiles[ $n ]  = $p;
    $key_of[ $n ]    = (string) $f['key'];
}
$profiles = vergeml_filing_settle_claims( $profiles );

$rows    = vergeml_guide_rule_rows( $taxonomy, 'all', array( 'filing', 'terms' ) );
$words   = vergeml_filing_words_sql( 'i' );
$product = vergeml_filing_product_sql( 'i' );
$vectors = array();

$q2 = (int) $wpdb->num_queries;
$t2 = microtime( true );
foreach ( array_chunk( array_map( function ( $r ) { return (int) $r['attachment_id']; }, $rows ), 500 ) as $chunk ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- this plugin's own table; ids are integers.
    foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT i.attachment_id, i.embedding, i.tags, pm.meta_value AS placed_by, {$words['select']}, {$product['select']} FROM {$wpdb->vergeml_ai_index} i LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = i.attachment_id AND pm.meta_key = %s {$words['join']} {$product['join']} WHERE i.attachment_id IN (" . implode( ',', $chunk ) . ')', VERGEML_FILING_PLACED_BY ), ARRAY_A ) as $v ) {
        $vectors[ (int) $v['attachment_id'] ] = $v;
    }
}
$t3 = microtime( true );
$q3 = (int) $wpdb->num_queries;

$index = array();
foreach ( $rows as $r ) {
    $id      = (int) $r['attachment_id'];
    $index[] = array_merge( $r, isset( $vectors[ $id ] ) ? $vectors[ $id ] : array( 'embedding' => null, 'tags' => '', 'placed_by' => '' ), array( 'in_locked' => false ) );
}

$t4      = microtime( true );
$index   = vergeml_filing_product_folders( $index, $profiles );
$t5      = microtime( true );
$q4      = (int) $wpdb->num_queries;

$with    = vergeml_filing_count( $profiles, $index );
$why2    = $with['counts']['why'];
$out2    = (int) $why2['floor'] + (int) $why2['margin'] + (int) $why2['gated'];

printf( "WITH   looked %d · %d would be placed · %d would stay unfiled (floor %d, margin %d, gated %d)\n", count( $rows ), max( 0, count( $rows ) - $out2 ), $out2, (int) $why2['floor'], (int) $why2['margin'], (int) $why2['gated'] );
printf( "       fits %d (product %d) · siblings %d · nothing %d · kept %d\n", (int) $with['counts']['fits'], (int) $with['counts']['product'], (int) $with['counts']['siblings'], (int) $with['counts']['nothing'], (int) $with['counts']['kept'] );
printf( "       the product_id column: %.3f s, %d queries; the folders step: %.3f s, %d queries\n", $t3 - $t2, $q3 - $q2, $t5 - $t4, $q4 - $q3 );

$has = 0;
foreach ( $index as $r ) {
    if ( ! empty( $r['product_folder'] ) ) {
        $has++;
    }
}
printf( "       rows with a product folder: %d of %d\n\n", $has, count( $index ) );

/* ----------------------------------- the state at the press: no folder, nothing placed yet */

$fresh = array();
foreach ( $index as $r ) {
    $r['in_terms']  = '';
    $r['placed_by'] = '';
    unset( $r['product_folder'] );
    $fresh[] = $r;
}

$a    = vergeml_filing_count( $profiles, $fresh );
$whya = $a['counts']['why'];
$outa = (int) $whya['floor'] + (int) $whya['margin'] + (int) $whya['gated'];
printf( "AT THE PRESS, rules only   %d would be placed · %d would stay unfiled (floor %d, margin %d, gated %d)\n", count( $fresh ) - $outa, $outa, (int) $whya['floor'], (int) $whya['margin'], (int) $whya['gated'] );

$fresh = vergeml_filing_product_folders( $fresh, $profiles );
$b     = vergeml_filing_count( $profiles, $fresh );
$whyb  = $b['counts']['why'];
$outb  = (int) $whyb['floor'] + (int) $whyb['margin'] + (int) $whyb['gated'];
printf( "AT THE PRESS, with products %d would be placed · %d would stay unfiled (floor %d, margin %d, gated %d) · product %d\n", count( $fresh ) - $outb, $outb, (int) $whyb['floor'], (int) $whyb['margin'], (int) $whyb['gated'], (int) $b['counts']['product'] );
