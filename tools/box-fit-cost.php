<?php
/**
 *  What one dry run of a draft costs, and where the time goes.
 *
 *      wp eval-file tools/box-fit-cost.php --allow-root
 *
 *  The turn route runs vergeml_filing_pick() over the whole draft before it
 *  hands the turn back, and on this box that took over two minutes -- which
 *  is not a thing to put in front of somebody waiting for an answer. This
 *  says why: how many pictures, how many folders, how many database queries,
 *  how many seconds, and how much of it is the phrase matching underneath
 *  vergeml_filing_class_match().
 *
 *  It writes nothing but the folder profile cache, which any real run fills
 *  the same way. Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_guide_draft_fit' ) ) {
    echo "core/guide.php is not loaded\n";
    return;
}

global $wpdb;

$taxonomy = vergeml_librarian_taxonomy();
$nodes    = vergeml_folders_nodes( $taxonomy );

if ( ! $nodes ) {
    echo "no folders on this site\n";
    return;
}

// The live tree as a draft, plus one folder that does not exist -- the same
// shape the conversation hands back, so the cost is the real one.
$folders = array();
foreach ( $nodes as $n ) {
    $folders[] = array(
        'key'      => 't' . $n['id'],
        'term_id'  => (int) $n['id'],
        'name'     => (string) $n['name'],
        'parent'   => $n['parent'] ? 't' . $n['parent'] : '',
        'count'    => null,
        'matches'  => '',
        'classes'  => array(),
        'kinds'    => array(),
        'audience' => '',
        'by'       => '',
    );
}
$folders[] = array(
    'key'      => 'probe1',
    'term_id'  => null,
    'name'     => 'Draft probe',
    'parent'   => '',
    'count'    => 12,
    'matches'  => 'a probe',
    'classes'  => array( 'probe' ),
    'kinds'    => array( 'photo' ),
    'audience' => '',
    'by'       => '',
);

$draft = array( 'folders' => $folders, 'gone' => array(), 'tags' => array(), 'origin' => 'talk', 'rule' => null );

$q0 = (int) $wpdb->num_queries;
$t0 = microtime( true );
$fit = vergeml_guide_draft_fit( $draft, $taxonomy );
$t1 = microtime( true );
$q1 = (int) $wpdb->num_queries;

printf( "folders in the draft   %d\n", count( $folders ) );
printf( "pictures looked at     %s\n", $fit ? $fit['looked'] : 'none -- the run answered nothing' );
printf( "seconds                %.2f\n", $t1 - $t0 );
printf( "queries                %d\n", $q1 - $q0 );

if ( ! $fit ) {
    return;
}

printf( "pictures that move     %d\n", (int) $fit['move'] );
printf( "would not place        floor %d, margin %d, gated %d\n", $fit['unfiled']['floor'], $fit['unfiled']['margin'], $fit['unfiled']['gated'] );

// A second run, with every phrase now in the transient cache: the difference
// between the two is what the cold cache costs.
$q2  = (int) $wpdb->num_queries;
$t2  = microtime( true );
$again = vergeml_guide_draft_fit( $draft, $taxonomy );
$t3  = microtime( true );
$q3  = (int) $wpdb->num_queries;

printf( "warm again             %.2f seconds, %d queries\n", $t3 - $t2, $q3 - $q2 );
printf( "same answer            %s\n", wp_json_encode( $again['counts'] ) === wp_json_encode( $fit['counts'] ) ? 'yes' : 'NO -- the run is not repeatable' );
