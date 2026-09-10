<?php
/**
 *  Empty the test box's library back to nothing.
 *
 *  Every attachment and its files, every folder, every second-axis term, the
 *  describe index, the librarian's record and the conversation state. What is
 *  left is a site with the plugin installed and a library nobody has touched
 *  -- which is what a new customer has, and the only honest place to start a
 *  test of the whole journey from.
 *
 *  It does NOT touch posts, pages, products, users, settings or the licence.
 *  The 34 posts that used a picture as their featured image lose it, because
 *  the picture is gone; that is stated before anything is deleted, not after.
 *
 *      scp tools/box-reset-library.php root@box:/tmp/vgml-reset.php
 *      VGML_RESET=yes-really bash tools/box-reset-library.sh
 *
 *  Without VGML_RESET=yes-really it counts what it would destroy and stops.
 *  There is no undo. The box is a test box; this would be unforgivable
 *  anywhere else.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$go  = 'yes-really' === (string) getenv( 'VGML_RESET' );
$tax = vergeml_librarian_taxonomy();

$attachments = (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'" );
$taxonomies  = array_values( array_unique( array_filter( array_merge( array( $tax ), get_object_taxonomies( 'attachment' ) ) ) ) );

echo "what this would destroy\n";
printf( "  %d attachments, and every file and generated size on disk\n", count( $attachments ) );

foreach ( $taxonomies as $t ) {
    $n = wp_count_terms( array( 'taxonomy' => $t, 'hide_empty' => false ) );
    printf( "  %-22s %s terms\n", $t, is_wp_error( $n ) ? '?' : $n );
}

$index = vergeml_index_table();
printf( "  %d rows in the describe index\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$index}" ) );

if ( isset( $wpdb->vergeml_librarian_moves ) ) {
    printf( "  %d move rows, %d batches\n",
        (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_moves}" ),
        (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_batches}" )
    );
}

printf( "  %d posts lose their featured image\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'" ) );

if ( ! $go ) {
    echo "\nnothing was deleted. VGML_RESET=yes-really to go through with it.\n";
    return;
}

echo "\ndeleting\n";

$gone = 0;
foreach ( $attachments as $id ) {
    if ( wp_delete_attachment( (int) $id, true ) ) {
        $gone++;
    }
    if ( 0 === $gone % 100 && $gone ) {
        printf( "  %d of %d\n", $gone, count( $attachments ) );
    }
}
printf( "  %d attachments deleted\n", $gone );

foreach ( $taxonomies as $t ) {
    $terms = get_terms( array( 'taxonomy' => $t, 'hide_empty' => false, 'fields' => 'ids' ) );
    $terms = is_wp_error( $terms ) ? array() : $terms;
    // Children first: deleting a parent re-parents its children rather than removing them.
    usort( $terms, function ( $a, $b ) use ( $t ) {
        $ta = get_term( $a, $t );
        $tb = get_term( $b, $t );
        return ( $tb instanceof WP_Term ? (int) $tb->parent : 0 ) <=> ( $ta instanceof WP_Term ? (int) $ta->parent : 0 );
    } );
    foreach ( $terms as $tid ) {
        wp_delete_term( (int) $tid, $t );
    }
    printf( "  %-22s emptied\n", $t );
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "TRUNCATE TABLE {$index}" );
echo "  describe index emptied\n";

if ( isset( $wpdb->vergeml_librarian_moves ) ) {
    $wpdb->query( "TRUNCATE TABLE {$wpdb->vergeml_librarian_moves}" );
    $wpdb->query( "TRUNCATE TABLE {$wpdb->vergeml_librarian_batches}" );
    echo "  librarian record emptied\n";
}
// phpcs:enable

/*
 *  The conversation and its leftovers. A session with turns in it, or a draft
 *  nobody applied, is the previous test still talking -- and a stale draft
 *  behind a Move button is how twenty-one folders went once before.
 */
foreach ( array( 'vergeml_guide_session', 'vergeml_talk_state', 'vergeml_talk_undo', 'vergeml_why_walk_restore' ) as $option ) {
    delete_option( $option );
}
foreach ( array( 'VERGEML_TALK_STATE', 'VERGEML_TALK_UNDO', 'VERGEML_GUIDE_OPTION' ) as $constant ) {
    if ( defined( $constant ) ) {
        delete_option( constant( $constant ) );
    }
}
echo "  conversation, draft and undo record cleared\n";

echo "\nwhat is left\n";
printf( "  %d attachments\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" ) );
foreach ( $taxonomies as $t ) {
    $n = wp_count_terms( array( 'taxonomy' => $t, 'hide_empty' => false ) );
    printf( "  %-22s %s terms\n", $t, is_wp_error( $n ) ? '?' : $n );
}
printf( "  %d rows in the describe index\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$index}" ) );
