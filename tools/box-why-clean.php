<?php
/**
 *  Takes tools/box-why-shot.php's fixture back out of the library.
 *
 *  Everything it made is prefixed zz, and nothing else is touched: the four
 *  attachments by title, their index rows, their moves rows, and the two
 *  folders. A library that has no zz anything left is the pass condition, and
 *  this prints what it found so a run that removed nothing is visible rather
 *  than silent.
 *
 *  It sweeps tests/tree/filing-trail.php's fixtures too, which are named the
 *  same way. That suite tears its own down and stops before the teardown when
 *  its safety guard refuses to run the pass -- and four "zz trail" pictures
 *  from 8 September were still sitting in the box's library on the 9th, in the
 *  grid, among somebody's real ones. Debris from a suite is live state like
 *  any other.
 *
 *      scp tools/box-why-clean.php root@box:/tmp/vgml-why-clean.php
 *      bash tools/box-why-clean.sh
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$tax = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ids = (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND ( post_title LIKE 'zz shot %' OR post_title LIKE 'zz trail %' )" );

foreach ( $ids as $id ) {
    $id = (int) $id;
    if ( isset( $wpdb->vergeml_librarian_moves ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $wpdb->vergeml_librarian_moves, array( 'attachment_id' => $id ), array( '%d' ) );
    }
    if ( isset( $wpdb->vergeml_ai_index ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $wpdb->vergeml_ai_index, array( 'attachment_id' => $id ), array( '%d' ) );
    }
    wp_delete_post( $id, true );
}

$gone = 0;

foreach ( array( 'zzShotA', 'zzShotC', 'zzTrailA', 'zzTrailC' ) as $name ) {
    $t = get_term_by( 'name', $name, $tax );
    if ( $t instanceof WP_Term ) {
        wp_delete_term( (int) $t->term_id, $tax );
        $gone++;
    }
}

printf( "removed %d pictures and %d folders\n", count( $ids ), $gone );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title LIKE 'zz %'" );

printf( "%d left behind\n", $left );
