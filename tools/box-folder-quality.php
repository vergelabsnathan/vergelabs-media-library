<?php
/*
 *  What actually lands in a folder, before and after.
 *
 *  Takes a few folder names of the kind Nathan asked for, and prints the
 *  pictures each one would collect -- scored the way re-filing scored them
 *  until now, and the way it scores them from 3.16.1. The titles are the
 *  point: "Footwear" holding a bicycle is visible here without applying
 *  anything or moving a single file.
 *
 *  Read-only. Nothing is re-filed and no folder is created.
 */

global $wpdb;
$table = $wpdb->prefix . 'vergeml_ai_index';

$rows = $wpdb->get_results(
    "SELECT attachment_id, embedding FROM {$table}
      WHERE error = '' AND embedding IS NOT NULL",
    ARRAY_A
);

if ( count( $rows ) < 10 ) {
    echo "not enough described pictures here\n";
    return;
}

$full = array();
foreach ( $rows as $r ) {
    $v = vergeml_index_vector_out( $r['embedding'] );
    if ( is_array( $v ) && $v ) {
        $full[ (int) $r['attachment_id'] ] = $v;
    }
}

$names = array( 'Footwear', "Women's apparel", 'Furniture', 'Bicycles' );

foreach ( $names as $name ) {

    $q = vergeml_meaning_vector( $name );

    if ( ! is_array( $q ) || ! $q ) {
        echo "-- could not get a vector for {$name}\n";
        continue;
    }

    $short = vergeml_organize_project( $q, VERGEML_ORGANIZE_DIMS );

    $byOld = array();
    $byNew = array();

    foreach ( $full as $id => $v ) {
        $byOld[ $id ] = vergeml_meaning_similarity( $short, vergeml_organize_project( $v, VERGEML_ORGANIZE_DIMS ) );
        $byNew[ $id ] = vergeml_meaning_similarity( $q, $v );
    }

    arsort( $byOld );
    arsort( $byNew );

    $show = function ( $scores ) {
        $out = array();
        foreach ( array_slice( $scores, 0, 5, true ) as $id => $s ) {
            $t = get_the_title( $id );
            if ( '' === trim( (string) $t ) ) {
                $t = basename( (string) get_attached_file( $id ) );
            }
            $out[] = sprintf( '%.2f %s', $s, substr( (string) $t, 0, 44 ) );
        }
        return $out;
    };

    printf( "\n=== %s\n", $name );
    printf( "  what it used to collect (projected, as shipped):\n" );
    foreach ( $show( $byOld ) as $line ) { printf( "    %s\n", $line ); }
    printf( "  what it collects now (whole embeddings):\n" );
    foreach ( $show( $byNew ) as $line ) { printf( "    %s\n", $line ); }
}

printf( "\n%d described pictures considered.\n", count( $full ) );
