<?php
/*
 *  A site's folder tree, frozen and put back with the same ids (S10.5b).
 *
 *      VGML_MODE=snapshot VGML_FILE=/path/tree.json  wp eval-file tools/box-tree-snapshot.php
 *      VGML_MODE=restore  VGML_FILE=/path/tree.json  wp eval-file tools/box-tree-snapshot.php
 *
 *  Literal SQL, never the code under test (a restore list computed by the
 *  mutated reader deleted 100 real alts on 2026-09-15): the taxonomy's rows
 *  of terms, term_taxonomy, termmeta and term_relationships, every
 *  attachment's _vergeml_placed_by, and the three options the Folders
 *  screen lives on (the fill state, its undo, the guide session). The
 *  restore deletes what the taxonomy holds now and inserts the rows as they
 *  were, ids included, so a band keyed on term ids (filing-baseline-shop.txt)
 *  reads the same tree. Written for the shop site before HEMA's tree was
 *  pasted over it (2026-09-17); the C.5 tree is the band's reference.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$mode = (string) getenv( 'VGML_MODE' );
$file = (string) getenv( 'VGML_FILE' );
$tax  = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';

if ( '' === $file || ! in_array( $mode, array( 'snapshot', 'restore' ), true ) ) {
    echo "VGML_MODE=snapshot|restore and VGML_FILE=<path> are required\n";
    exit( 1 );
}

$options = array( 'vergeml_talk_state', 'vergeml_talk_undo', 'vergeml_guide_session' );
foreach ( array( 'VERGEML_TALK_STATE', 'VERGEML_TALK_UNDO', 'VERGEML_GUIDE_OPTION' ) as $i => $c ) {
    if ( defined( $c ) ) {
        $options[ $i ] = constant( $c );
    }
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- a snapshot tool; literal SQL on purpose.

if ( 'snapshot' === $mode ) {
    $tt   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s ORDER BY term_id", $tax ), ARRAY_A );
    $ids  = array_map( function ( $r ) { return (int) $r['term_id']; }, $tt );
    $ttid = array_map( function ( $r ) { return (int) $r['term_taxonomy_id']; }, $tt );
    $in   = $ids ? implode( ',', $ids ) : '0';
    $tin  = $ttid ? implode( ',', $ttid ) : '0';
    $snap = array(
        'taken'              => gmdate( 'c' ),
        'site'               => home_url(),
        'taxonomy'           => $tax,
        'terms'              => $wpdb->get_results( "SELECT * FROM {$wpdb->terms} WHERE term_id IN ({$in}) ORDER BY term_id", ARRAY_A ),
        'term_taxonomy'      => $tt,
        'termmeta'           => $wpdb->get_results( "SELECT * FROM {$wpdb->termmeta} WHERE term_id IN ({$in}) ORDER BY meta_id", ARRAY_A ),
        'term_relationships' => $wpdb->get_results( "SELECT * FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$tin}) ORDER BY object_id", ARRAY_A ),
        'placed_by'          => $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_vergeml_placed_by' ORDER BY post_id", ARRAY_A ),
        'options'            => array(),
    );
    foreach ( $options as $o ) {
        $snap['options'][ $o ] = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $o ) );
    }
    if ( false === file_put_contents( $file, wp_json_encode( $snap ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        echo "could not write {$file}\n";
        exit( 1 );
    }
    printf( "snapshot: %d terms, %d termmeta rows, %d relationships, %d placed_by, %d options -> %s (%d bytes)\n", count( $snap['terms'] ), count( $snap['termmeta'] ), count( $snap['term_relationships'] ), count( $snap['placed_by'] ), count( array_filter( $snap['options'], 'is_string' ) ), $file, filesize( $file ) );
    exit( 0 );
}

$snap = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
if ( ! is_array( $snap ) || $snap['taxonomy'] !== $tax || empty( $snap['terms'] ) ) {
    echo "no usable snapshot in {$file}\n";
    exit( 1 );
}
if ( $snap['site'] !== home_url() ) {
    printf( "the snapshot is %s's, this site is %s\n", $snap['site'], home_url() );
    exit( 1 );
}

// What the taxonomy holds now goes, rows only -- no hooks, no counts, no caches until the end.
$now  = $wpdb->get_results( $wpdb->prepare( "SELECT term_id, term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax ), ARRAY_A );
$nids = array_map( function ( $r ) { return (int) $r['term_id']; }, $now );
$ntt  = array_map( function ( $r ) { return (int) $r['term_taxonomy_id']; }, $now );
if ( $ntt ) {
    $wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ',', $ntt ) . ')' );
    $wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN (" . implode( ',', $ntt ) . ')' );
}
if ( $nids ) {
    $wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN (" . implode( ',', $nids ) . ')' );
    $wpdb->query( "DELETE FROM {$wpdb->terms} WHERE term_id IN (" . implode( ',', $nids ) . ')' );
}
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_vergeml_placed_by'" );

$put = function ( $table, $rows ) use ( $wpdb ) {
    $n = 0;
    foreach ( (array) $rows as $row ) {
        if ( false !== $wpdb->replace( $table, $row ) ) {
            $n++;
        }
    }
    return $n;
};
$counts = array(
    'terms'         => $put( $wpdb->terms, $snap['terms'] ),
    'term_taxonomy' => $put( $wpdb->term_taxonomy, $snap['term_taxonomy'] ),
    'termmeta'      => $put( $wpdb->termmeta, $snap['termmeta'] ),
    'relationships' => $put( $wpdb->term_relationships, $snap['term_relationships'] ),
    'placed_by'     => 0,
);
foreach ( (array) $snap['placed_by'] as $p ) {
    $wpdb->insert( $wpdb->postmeta, array( 'post_id' => (int) $p['post_id'], 'meta_key' => '_vergeml_placed_by', 'meta_value' => (string) $p['meta_value'] ), array( '%d', '%s', '%s' ) );
    $counts['placed_by']++;
}
foreach ( (array) $snap['options'] as $name => $value ) {
    if ( null === $value ) {
        $wpdb->delete( $wpdb->options, array( 'option_name' => $name ), array( '%s' ) );
    } else {
        $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)", $name, $value ) );
    }
}
// phpcs:enable

wp_cache_flush();
clean_taxonomy_cache( $tax );
if ( function_exists( 'vergeml_folder_flush_counts' ) ) {
    vergeml_folder_flush_counts();
}
printf( "restored: %s from %s (taken %s)\n", wp_json_encode( $counts ), $file, $snap['taken'] );
