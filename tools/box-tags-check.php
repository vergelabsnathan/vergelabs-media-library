<?php
/*
 *  The guide's tags, end to end but dry. Makes a Colour and a Material tag
 *  set through vergeml_guide_make_tags(), matches the first 300 described
 *  rows against them without touching a picture, prints the counts and one
 *  worked example, checks a second call reuses rather than duplicates, and
 *  takes everything back through vergeml_guide_unmake_tags().
 */

global $wpdb;

$draft = array(
    array( 'name' => 'Colour', 'values' => array( 'tan', 'red', 'black', 'white', 'blue' ) ),
    array( 'name' => 'Material', 'values' => array( 'leather', 'denim', 'cotton' ) ),
);

$made = vergeml_guide_make_tags( $draft );
printf( "made: %s\n", wp_json_encode( array_map( function ( $e ) {
    return array( 'taxonomy' => $e['taxonomy'], 'created' => $e['created'], 'terms' => count( $e['terms'] ) );
}, $made ) ) );
if ( count( $made ) !== 2 ) {
    echo "FAIL: expected two tag sets\n";
}
foreach ( $made as $e ) {
    if ( ! taxonomy_exists( $e['taxonomy'] ) ) {
        printf( "FAIL: %s not registered\n", $e['taxonomy'] );
    }
}

$map = array();
foreach ( $made as $e ) {
    foreach ( $e['terms'] as $tid => $t ) {
        $map[ $e['taxonomy'] ][ $tid ] = $t['needles'];
    }
}

$rows = $wpdb->get_results( "SELECT attachment_id, filing, tags FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL ORDER BY attachment_id ASC LIMIT 300", ARRAY_A );
$per  = array();
$hit  = 0;
foreach ( $rows as $row ) {
    $r = vergeml_talk_tag_row( $row, $map );
    if ( $r ) {
        $hit++;
    }
    foreach ( $r as $tax => $ids ) {
        foreach ( $ids as $id ) {
            $per[ $tax ][ $id ] = isset( $per[ $tax ][ $id ] ) ? $per[ $tax ][ $id ] + 1 : 1;
        }
    }
}
printf( "rows: %d, with at least one tag: %d\n", count( $rows ), $hit );
foreach ( $per as $tax => $ids ) {
    foreach ( $ids as $id => $n ) {
        $term = get_term( $id, $tax );
        printf( "  %s / %s: %d\n", $tax, $term && ! is_wp_error( $term ) ? $term->name : $id, $n );
    }
}
foreach ( $rows as $row ) {
    $r = vergeml_talk_tag_row( $row, $map );
    if ( $r ) {
        $f = json_decode( (string) $row['filing'], true );
        printf( "example #%d: colour=[%s] material=[%s] tags=%s -> %s\n", $row['attachment_id'], isset( $f['colour'] ) ? $f['colour'] : '', isset( $f['material'] ) ? $f['material'] : '', $row['tags'], wp_json_encode( $r ) );
        break;
    }
}

$opt = get_option( 'vergeml_taxonomies' );
foreach ( $made as $e ) {
    if ( empty( $opt[ $e['taxonomy'] ]['eml_media'] ) ) {
        printf( "FAIL: %s not in vergeml_taxonomies\n", $e['taxonomy'] );
    }
}

$again = vergeml_guide_make_tags( $draft );
foreach ( $again as $e ) {
    if ( $e['created'] ) {
        printf( "FAIL: %s made twice\n", $e['taxonomy'] );
    }
    foreach ( $e['terms'] as $t ) {
        if ( $t['created'] ) {
            echo "FAIL: a term was made twice\n";
            break;
        }
    }
}

vergeml_guide_unmake_tags( $made );
$opt = get_option( 'vergeml_taxonomies' );
foreach ( $made as $e ) {
    if ( isset( $opt[ $e['taxonomy'] ] ) ) {
        printf( "FAIL: %s still in the option after unmake\n", $e['taxonomy'] );
    }
    $left = get_terms( array( 'taxonomy' => $e['taxonomy'], 'hide_empty' => false, 'fields' => 'ids' ) );
    if ( ! is_wp_error( $left ) && $left ) {
        printf( "FAIL: %d terms left in %s\n", count( $left ), $e['taxonomy'] );
    }
}
echo "tags-check done\n";
