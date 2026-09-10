<?php
/**
 *  A library the suites can run against, that the repository can rebuild.
 *
 *  Twelve of the thirty-three suites went red on 2026-09-10 and none of them
 *  had a broken assertion: the library had been emptied and they need a
 *  populated one. `smart` waits sixty seconds for `.vgml-tree .vgml-row` and
 *  there were no folders; `guide` D5 builds a year folder with a month under
 *  it and had no dates to group. A green board that depends on a library
 *  nobody can recreate is not a green board, it is a coincidence.
 *
 *  So: folders, and described pictures, built the same way every time.
 *
 *      scp tools/box-fixture.php root@box:/tmp/vgml-fixture.php
 *      bash tools/box-fixture.sh                 # build it
 *      VGML_FIXTURE_REMOVE=1 bash tools/box-fixture.sh   # take it away again
 *
 *  IT SPENDS NOTHING. No model is called. The descriptions are written
 *  straight into the index through the plugin's own writer, so the rows have
 *  the shape a real describe makes -- and they are deterministic, so two runs
 *  produce the same library and a suite that passes today passes tomorrow for
 *  the same reason.
 *
 *  What it does NOT do is pretend to be the real thing: every row is stamped
 *  model 'fixture', which is how a suite (or a person) can tell fixture data
 *  from a description somebody paid for.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$tax = vergeml_librarian_taxonomy();

/* ------------------------------------------------------------------ remove */

if ( getenv( 'VGML_FIXTURE_REMOVE' ) ) {

    $terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ) );
    $gone  = 0;

    foreach ( is_wp_error( $terms ) ? array() : $terms as $tid ) {
        if ( 0 === strpos( (string) get_term_field( 'slug', $tid, $tax ), 'fixture-' ) ) {
            wp_delete_term( (int) $tid, $tax );
            $gone++;
        }
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
    $rows = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . vergeml_index_table() . ' WHERE model = %s', 'fixture' ) );

    printf( "%d fixture folders removed, %d fixture index rows removed\n", $gone, (int) $rows );
    return;
}

/* ------------------------------------------------------------------- build */

/*
 *  The folders a technology publication keeps, and the words that belong in
 *  each. The words are what the fixture writes as captions and tags, so a
 *  search suite looking for "server rack" finds pictures that say it.
 */
$scheme = array(
    'Hardware'     => array( 'Phones', 'Laptops', 'Components' ),
    'Data centres' => array( 'Server racks', 'Cooling' ),
    'Energy'       => array( 'Solar', 'Wind', 'Batteries' ),
    'People'       => array( 'Interviews', 'Conference talks' ),
    'Space'        => array( 'Launches', 'Satellites' ),
    'Robotics'     => array(),
);

$words = array(
    'Phones'           => array( 'smartphone', 'handset', 'screen' ),
    'Laptops'          => array( 'laptop', 'keyboard', 'desk' ),
    'Components'       => array( 'circuit board', 'chip', 'silicon' ),
    'Server racks'     => array( 'server rack', 'cabling', 'data centre' ),
    'Cooling'          => array( 'cooling', 'fans', 'airflow' ),
    'Solar'            => array( 'solar panel', 'array', 'sunlight' ),
    'Wind'             => array( 'wind turbine', 'blades', 'field' ),
    'Batteries'        => array( 'battery', 'cells', 'storage' ),
    'Interviews'       => array( 'interview', 'portrait', 'speaker' ),
    'Conference talks' => array( 'keynote', 'stage', 'audience' ),
    'Launches'         => array( 'rocket', 'launch', 'pad' ),
    'Satellites'       => array( 'satellite', 'orbit', 'antenna' ),
    'Robotics'         => array( 'robot', 'arm', 'factory' ),
);

$kinds = array( 'photo', 'illustration', 'screenshot', 'document', 'diagram' );

//  Make the folders. Parents first, so a child has somewhere to go.
$ids   = array();
$made  = 0;

foreach ( $scheme as $parent => $children ) {

    $slug = 'fixture-' . sanitize_title( $parent );
    $term = get_term_by( 'slug', $slug, $tax );

    if ( ! $term instanceof WP_Term ) {
        $new = wp_insert_term( $parent, $tax, array( 'slug' => $slug ) );
        if ( is_wp_error( $new ) ) {
            printf( "  could not make %s: %s\n", $parent, $new->get_error_message() );
            continue;
        }
        $made++;
        $term_id = (int) $new['term_id'];
    } else {
        $term_id = (int) $term->term_id;
    }

    $ids[ $parent ] = $term_id;

    foreach ( $children as $child ) {
        $cslug = 'fixture-' . sanitize_title( $child );
        $ct    = get_term_by( 'slug', $cslug, $tax );
        if ( ! $ct instanceof WP_Term ) {
            $new = wp_insert_term( $child, $tax, array( 'slug' => $cslug, 'parent' => $term_id ) );
            if ( is_wp_error( $new ) ) {
                continue;
            }
            $made++;
            $ids[ $child ] = (int) $new['term_id'];
        } else {
            $ids[ $child ] = (int) $ct->term_id;
        }
    }
}

printf( "%d folders made (%d in the scheme)\n", $made, count( $ids ) );

/*
 *  Every picture gets a folder, a description and a date, chosen from its own
 *  id so the same library comes out every time.
 */
$leaves = array_values( array_diff( array_keys( $ids ), array_keys( $scheme ) ) );

$pictures = (array) $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts}
      WHERE post_type = 'attachment' AND post_status = 'inherit'
        AND post_mime_type LIKE 'image/%'
      ORDER BY ID"
);

printf( "%d pictures to file and describe\n", count( $pictures ) );

$filed = 0;
$described = 0;
$now = time();

foreach ( $pictures as $n => $id ) {

    $id   = (int) $id;
    $leaf = $leaves[ $n % count( $leaves ) ];
    $kind = $kinds[ $n % count( $kinds ) ];
    $said = isset( $words[ $leaf ] ) ? $words[ $leaf ] : array( 'picture' );

    wp_set_object_terms( $id, array( (int) $ids[ $leaf ] ), $tax, false );
    $filed++;

    /*
     *  A date spread over three years, so the date schemes have years and
     *  months to group by -- guide's D5 makes a year folder with a month under
     *  it, and every picture landing in the same month gave it nothing to do.
     */
    $when = gmdate( 'Y-m-d H:i:s', $now - ( ( $id * 7919 ) % 1095 ) * 86400 );
    wp_update_post( array( 'ID' => $id, 'post_date' => $when, 'post_date_gmt' => $when ) );

    /*
     *  A deterministic vector. Pictures in the same folder point in nearly the
     *  same direction, so the matcher has something coherent to find, and two
     *  runs of this script produce identical numbers.
     */
    $dims = 32;
    $seed = crc32( $leaf );
    $vec  = array();
    for ( $d = 0; $d < $dims; $d++ ) {
        $vec[] = sin( ( $seed % 997 ) + $d ) * 0.9 + sin( $id + $d ) * 0.1;
    }

    vergeml_index_set( $id, array(
        'caption'       => sprintf( 'A %s showing %s.', $kind, implode( ', ', $said ) ),
        'alt'           => sprintf( '%s: %s', ucfirst( $said[0] ), $leaf ),
        'title'         => ucfirst( $said[0] ) . ' ' . ( $n + 1 ),
        'tags'          => implode( ',', $said ),
        'kind'          => $kind,
        'has_people'    => ( 'Interviews' === $leaf || 'Conference talks' === $leaf ) ? 1 : 0,
        'has_text'      => ( 'screenshot' === $kind || 'document' === $kind ) ? 1 : 0,
        'document_type' => 'document' === $kind ? 'report' : '',
        'filing'        => wp_json_encode( array( 'object' => $said[0], 'setting' => $leaf, 'audience' => '' ) ),
        'orientation'   => 0 === $n % 3 ? 'portrait' : 'landscape',
        'embedding'     => $vec,
        'model'         => 'fixture',
        'model_version' => 'fixture-1',
        'prompt_hash'   => 'fixture',
        'described_at'  => $when,
    ) );

    $described++;
}

printf( "\n%d pictures filed, %d described (model 'fixture', nothing was paid for)\n", $filed, $described );
printf( "%d rows in the index\n", (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . vergeml_index_table() ) );
echo "\ntake it away with VGML_FIXTURE_REMOVE=1\n";
