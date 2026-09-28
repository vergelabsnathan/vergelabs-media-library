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
 *  VGML_SCALE_LABELS=1 also writes an ai_index row beside each post it makes
 *  (spec-tree-planner story 7's proof): about 40,000 distinct labels over
 *  about 1,200 classes, Zipf-shaped so a few labels carry most of the
 *  pictures and most labels carry few, 3% non-photo kinds, and a 512-dim
 *  vector per picture that sits near its class's own centre rather than
 *  scattered -- the shape vergeml_plan_inventory()'s fold and
 *  vergeml_plan_choose()'s tightness are meant to be measured against.
 *  Removal (VGML_SCALE_REMOVE=1) always also removes any such rows, by the
 *  same marker, in literal SQL never routed through the code under test.
 *
 *      VGML_SCALE_N=500000 VGML_SCALE_LABELS=1 bash tools/box-scale-fixture.sh
 *
 *  Written 2026-09-10. Labels added 2026-09-28.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$posts = $wpdb->posts;
$meta  = $wpdb->postmeta;
$rels  = $wpdb->term_relationships;
$index = $wpdb->vergeml_ai_index;

/* ------------------------------------------------------------------ remove */

if ( getenv( 'VGML_SCALE_REMOVE' ) ) {

    $n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$posts} WHERE post_name LIKE 'vgmlscale-%'" );
    printf( "removing %s synthetic rows\n", number_format( $n ) );

    // Literal SQL, joined on the marker, never the plugin's own readers: a snapshot or a restore list built with the code under test is the mistake this avoids twice over.
    $wpdb->query( "DELETE i FROM {$index} i JOIN {$posts} p ON p.ID = i.attachment_id WHERE p.post_name LIKE 'vgmlscale-%'" );
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

/* -------------------------------------------------------- labels (story 7) */

$labels_on = (bool) getenv( 'VGML_SCALE_LABELS' );

// About 1,200 classes, about 40,000 labels -- the fold's shape, not the fill's: 1200 * 34 = 40,800.
$vgmlscale_classes_n = 1200;
$vgmlscale_per_class = 34;
$vgmlscale_labels_n  = $vgmlscale_classes_n * $vgmlscale_per_class;
$vgmlscale_kinds     = array( 'illustration', 'screenshot', 'diagram', 'document', 'logo' );
$vgmlscale_cum       = array();
$vgmlscale_centers   = array();

/** rank (0-based, 0 = most common) drawn from a cumulative Zipf distribution over $cum. */
function vgmlscale_rank( $cum ) {
    $u  = mt_rand( 1, 1000000 ) / 1000000.0;
    $lo = 0;
    $hi = count( $cum ) - 1;
    while ( $lo < $hi ) {
        $mid = (int) ( ( $lo + $hi ) / 2 );
        if ( $cum[ $mid ] < $u ) {
            $lo = $mid + 1;
        } else {
            $hi = $mid;
        }
    }
    return $lo;
}

/** A unit vector, 512 random dimensions. */
function vgmlscale_unit( $dims ) {
    $v = array();
    for ( $d = 0; $d < $dims; $d++ ) {
        $v[] = mt_rand( -1000, 1000 ) / 1000.0;
    }
    return vgmlscale_normalise( $v );
}

function vgmlscale_normalise( $v ) {
    $n = 0.0;
    foreach ( $v as $x ) {
        $n += $x * $x;
    }
    $n = sqrt( $n );
    if ( $n > 0 ) {
        foreach ( $v as $k => $x ) {
            $v[ $k ] = $x / $n;
        }
    }
    return $v;
}

/** A vector near $center: two dozen dimensions perturbed, then re-normalised -- close enough to cluster by class, not so close every picture in it is the same point. */
function vgmlscale_near( $center ) {
    $v    = $center;
    $dims = count( $center );
    for ( $k = 0; $k < 24; $k++ ) {
        $d          = mt_rand( 0, $dims - 1 );
        $v[ $d ]   += ( mt_rand( -1000, 1000 ) / 1000.0 ) * 0.15;
    }
    return vgmlscale_normalise( $v );
}

/** 3% non-photo, spread over the five other kinds; the rest photo. */
function vgmlscale_kind( $n, $kinds ) {
    return ( $n % 100 ) < 3 ? $kinds[ $n % count( $kinds ) ] : 'photo';
}

if ( $labels_on ) {
    $w = 0.0;
    for ( $r = 1; $r <= $vgmlscale_labels_n; $r++ ) {
        $w += 1.0 / $r;
    }
    $acc = 0.0;
    for ( $r = 1; $r <= $vgmlscale_labels_n; $r++ ) {
        $acc              += ( 1.0 / $r ) / $w;
        $vgmlscale_cum[]   = $acc;
    }
    for ( $c = 0; $c < $vgmlscale_classes_n; $c++ ) {
        $vgmlscale_centers[] = vgmlscale_unit( 512 );
    }
    printf( "labels: %s classes, %s labels (Zipf-shaped), 512-dim vectors clustered by class\n", number_format( $vgmlscale_classes_n ), number_format( $vgmlscale_labels_n ) );
}

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

    if ( $labels_on ) {
        $index_rows = array();
        for ( $i = 0; $i < $take; $i++ ) {

            $n       = $already + $made + $i + 1;
            $post_id = $first + $i;

            $rank    = vgmlscale_rank( $vgmlscale_cum );
            $class_i = intdiv( $rank, $vgmlscale_per_class );
            $spec_i  = $rank % $vgmlscale_per_class;
            $kind    = vgmlscale_kind( $n, $vgmlscale_kinds );

            $object = sprintf( 'vgmlscale item %d-%d; vgmlscale class %d', $class_i, $spec_i, $class_i );
            $filing = wp_json_encode( array( 'object' => $object, 'audience' => '' ) );
            $vector = vergeml_index_vector_in( vgmlscale_near( $vgmlscale_centers[ $class_i ] ) );

            $days = ( $n * 7919 ) % 1095;
            $date = gmdate( 'Y-m-d H:i:s', time() - $days * 86400 );

            $index_rows[] = $wpdb->prepare(
                '(%d, %s, %s, %s, %d, %s, %s, %s)',
                $post_id, $kind, $filing, $vector, 512, '', $date, $date
            );
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table; $wpdb->prepare() built every value above.
        $wpdb->query(
            "INSERT INTO {$index}
                ( attachment_id, kind, filing, embedding, embedding_dims, error, described_at, updated_at )
             VALUES " . implode( ',', $index_rows )
        );
        // phpcs:enable
    }

    $made += $take;

    if ( 0 === $made % 20000 ) {
        printf( "  %s of %s, %.0fs\n", number_format( $made ), number_format( $want ), microtime( true ) - $t0 );
    }
}

printf( "\n%s rows in %.0fs\n", number_format( $made ), microtime( true ) - $t0 );
printf( "attachments now: %s\n", number_format( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$posts} WHERE post_type='attachment'" ) ) );
printf( "postmeta now:    %s\n", number_format( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$meta}" ) ) );
if ( $labels_on ) {
    printf( "ai_index now:    %s (error='', embedding set)\n", number_format( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$index} WHERE error = '' AND embedding IS NOT NULL" ) ) );
}
echo "\nremove it all with VGML_SCALE_REMOVE=1\n";
