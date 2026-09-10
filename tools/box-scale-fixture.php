<?php
/**
 *  A library the size an agency actually has, so the cost can be measured.
 *
 *  The media library's smart-folder counts are five COUNT(*) branches over the
 *  attachments table, two of them full scans, run on every admin page load with
 *  no cache that outlives the request. At a thousand pictures that is 21ms and
 *  invisible. Agencies have hundreds of thousands. This builds that library so
 *  the cliff can be found rather than argued about.
 *
 *      scp tools/box-scale-fixture.php root@box:/tmp/vgml-scale.php
 *      VGML_SCALE_N=250000 bash tools/box-scale-fixture.sh
 *      VGML_SCALE_REMOVE=1  bash tools/box-scale-fixture.sh
 *
 *  Rows only -- no files are written and no thumbnails are made, because the
 *  queries under test read the database and never touch the disk.
 *
 *  Every row it makes is marked `post_name LIKE 'vgmlscale-%'`, which is how
 *  the removal finds them. It cannot touch a real picture: the thousand already
 *  in this library have their own names and are never selected.
 *
 *  Written 2026-09-10.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$posts = $wpdb->posts;
$meta  = $wpdb->postmeta;
$rels  = $wpdb->term_relationships;

/* ------------------------------------------------------------------ remove */

if ( getenv( 'VGML_SCALE_REMOVE' ) ) {

    $n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$posts} WHERE post_name LIKE 'vgmlscale-%'" );
    printf( "removing %s synthetic rows\n", number_format( $n ) );

    $wpdb->query( "DELETE r FROM {$rels} r JOIN {$posts} p ON p.ID = r.object_id WHERE p.post_name LIKE 'vgmlscale-%'" );
    $wpdb->query( "DELETE m FROM {$meta} m JOIN {$posts} p ON p.ID = m.post_id WHERE p.post_name LIKE 'vgmlscale-%'" );
    $wpdb->query( "DELETE FROM {$posts} WHERE post_name LIKE 'vgmlscale-%'" );

    printf( "%s attachments left in the library\n", number_format( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$posts} WHERE post_type='attachment'" ) ) );
    return;
}

/* ------------------------------------------------------------------- build */

$want  = max( 1000, (int) ( getenv( 'VGML_SCALE_N' ) ? getenv( 'VGML_SCALE_N' ) : 250000 ) );
$batch = 2000;

$filesize_key = defined( 'VERGEML_META_FILESIZE' ) ? VERGEML_META_FILESIZE : '_vergeml_filesize';
$unused_key   = defined( 'VERGEML_META_UNUSED' ) ? VERGEML_META_UNUSED : '_vergeml_unused';

$already = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$posts} WHERE post_name LIKE 'vgmlscale-%'" );
printf( "%s synthetic rows already here; making %s more\n", number_format( $already ), number_format( $want ) );

$home = home_url();
$t0   = microtime( true );
$made = 0;

// A parent to hang the "attached" share off, so post_parent is not all zero.
$parent = (int) $wpdb->get_var( "SELECT ID FROM {$posts} WHERE post_type='post' AND post_status='publish' LIMIT 1" );

while ( $made < $want ) {

    $rows = array();
    $take = min( $batch, $want - $made );

    for ( $i = 0; $i < $take; $i++ ) {

        $n = $already + $made + $i + 1;

        /*
         *  Spread over three years so the "this month" count is a real slice
         *  rather than everything or nothing -- that query is the one whose
         *  plan depends on the date distribution.
         */
        $days = ( $n * 7919 ) % 1095;                 // a prime stride, evenly spread
        $date = gmdate( 'Y-m-d H:i:s', time() - $days * 86400 );

        $rows[] = $wpdb->prepare(
            '(%s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s)',
            'vgmlscale-' . $n,                        // post_name, the marker
            sprintf( 'Scale fixture %d', $n ),        // post_title
            $date,
            $date,
            'inherit',
            'attachment',
            'image/jpeg',
            $home . '/wp-content/uploads/scale/vgmlscale-' . $n . '.jpg',
            ( $n % 10 < 3 ) ? $parent : 0,            // three in ten attached
            '',
            ''
        );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query(
        "INSERT INTO {$posts}
            ( post_name, post_title, post_date, post_date_gmt, post_status, post_type,
              post_mime_type, guid, post_parent, post_content, post_excerpt )
         VALUES " . implode( ',', $rows )
    );

    $first = (int) $wpdb->insert_id;
    $last  = $first + $take - 1;

    /*
     *  The meta the counts actually read. Built as one insert per key over the
     *  range just written, which is what makes a quarter of a million rows a
     *  few minutes rather than a few hours.
     */
    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$meta} ( post_id, meta_key, meta_value )
         SELECT ID, %s, CONCAT( 'scale/vgmlscale-', ID, '.jpg' ) FROM {$posts}
          WHERE ID BETWEEN %d AND %d",
        '_wp_attached_file', $first, $last
    ) );

    // Two in three carry alt text, so "missing alt text" is a real third.
    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$meta} ( post_id, meta_key, meta_value )
         SELECT ID, %s, CONCAT( 'A photograph, number ', ID ) FROM {$posts}
          WHERE ID BETWEEN %d AND %d AND ID %% 3 <> 0",
        '_wp_attachment_image_alt', $first, $last
    ) );

    // Every picture has a size; a fifth of them are over the large threshold.
    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$meta} ( post_id, meta_key, meta_value )
         SELECT ID, %s, CASE WHEN ID %% 5 = 0 THEN 4194304 ELSE 180000 END FROM {$posts}
          WHERE ID BETWEEN %d AND %d",
        $filesize_key, $first, $last
    ) );

    // Two in five are marked unused.
    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$meta} ( post_id, meta_key, meta_value )
         SELECT ID, %s, '1' FROM {$posts}
          WHERE ID BETWEEN %d AND %d AND ID %% 5 < 2",
        $unused_key, $first, $last
    ) );
    // phpcs:enable

    $made += $take;

    if ( 0 === $made % 20000 ) {
        printf( "  %s of %s, %.0fs\n", number_format( $made ), number_format( $want ), microtime( true ) - $t0 );
    }
}

printf( "\n%s rows in %.0fs\n", number_format( $made ), microtime( true ) - $t0 );
printf( "attachments now: %s\n", number_format( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$posts} WHERE post_type='attachment'" ) ) );
printf( "postmeta now:    %s\n", number_format( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$meta}" ) ) );
echo "\nremove it all with VGML_SCALE_REMOVE=1\n";
