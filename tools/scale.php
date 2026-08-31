<?php
/**
 *  A library the size the complaints are about, and what every hot path costs
 *  against it.
 *
 *      wp eval-file tools/scale.php up n=50000 folders=500 --allow-root
 *      wp eval-file tools/scale.php time --allow-root
 *      wp eval-file tools/scale.php down --allow-root
 *
 *  The published benchmarks were measured on a few hundred files. The buyers in
 *  the competitor threads hit the wall at ten thousand files and five hundred
 *  folders (Real Media Library: 45 seconds to load an image) and at 200GB
 *  (FileBird). "We are faster" is not a claim that survives contact with a
 *  library we have never had. So this fabricates one.
 *
 *  `up` writes synthetic attachments -- posts rows, an attached-file meta, a
 *  folder each across a real term tree, an AI-index row with a real-width
 *  embedding for a share of them, and duplicate hashes for a share -- straight
 *  into the tables in batches. No files on disk: nothing here reads a picture,
 *  and every path timed below is a database and PHP path.
 *
 *  `time` runs each hot path with the object cache cleared first, and reports
 *  wall-clock, query count and peak memory. `down` removes everything by its
 *  marker and recounts the terms it touched.
 *
 *  Lives in tools/, which never ships.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$mode    = 'time';
$n       = 50000;
$folders = 500;
$dims    = 768;
$embed   = 0.4;   // share of files that get an embedding
$dupes   = 0.1;   // share of files that share a hash with another
$tax     = 'media_category';
$marker  = 'vgml-scale-';
$parent_name = 'Scale fixture';

foreach ( (array) $args as $arg ) {
    if ( in_array( $arg, array( 'up', 'time', 'down' ), true ) ) {
        $mode = $arg;
    } elseif ( 0 === strpos( $arg, 'n=' ) ) {
        $n = max( 100, (int) substr( $arg, 2 ) );
    } elseif ( 0 === strpos( $arg, 'folders=' ) ) {
        $folders = max( 1, (int) substr( $arg, 8 ) );
    }
}

wp_set_current_user( 1 );

function vgml_scale_line( $label, $started, $q0, $extra = '' ) {
    global $wpdb;
    printf(
        "  %-46s %7.0f ms  %5d queries  %4d MB%s\n",
        $label,
        ( microtime( true ) - $started ) * 1000,
        $wpdb->num_queries - $q0,
        memory_get_peak_usage( true ) / 1048576,
        $extra ? '  ' . $extra : ''
    );
}

/* -------------------------------------------------------------------- up */

if ( 'up' === $mode ) {

    $t0 = microtime( true );
    printf( "\nfabricating %d attachments across %d folders (%d%% with embeddings, %d%% duplicates)\n", $n, $folders, $embed * 100, $dupes * 100 );

    // The folder tree: one parent, folders under it, a third of them nested one deeper.
    $parent = term_exists( $parent_name, $tax );
    $parent = $parent ? (int) $parent['term_id'] : (int) wp_insert_term( $parent_name, $tax )['term_id'];

    $term_ids = array();
    $tt_ids   = array();
    for ( $f = 0; $f < $folders; $f++ ) {
        $name = sprintf( '%s%04d', $marker, $f );
        $up   = ( $f % 3 === 0 && $f > 0 && isset( $term_ids[ $f - 1 ] ) ) ? $term_ids[ $f - 1 ] : $parent;
        $t    = term_exists( $name, $tax );
        $id   = $t ? (int) $t['term_id'] : (int) wp_insert_term( $name, $tax, array( 'parent' => $up ) )['term_id'];
        $term_ids[ $f ] = $id;
        $tt_ids[ $f ]   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s", $id, $tax ) );
    }
    printf( "  %d folders ready (%.1fs)\n", $folders, microtime( true ) - $t0 );

    // Posts, in multi-row inserts of 500.
    $base_id = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" ) + 1000;
    $now     = current_time( 'mysql' );
    $now_gmt = current_time( 'mysql', true );
    $batch   = array();
    $rel     = array();
    $meta    = array();
    $ids     = array();

    for ( $i = 0; $i < $n; $i++ ) {
        $id    = $base_id + $i;
        $ids[] = $id;
        $f     = $i % $folders;
        $title = $marker . $i;
        $file  = sprintf( '2026/08/%s.jpg', $title );

        // Columns: ID, author, date, date_gmt, content, title, excerpt, status,
        // comment, ping, name, to_ping, pinged, modified, modified_gmt, parent,
        // guid, menu_order, type, mime, comment_count.
        $batch[] = $wpdb->prepare(
            "(%d, 1, %s, %s, '', %s, '', 'inherit', 'open', 'closed', %s, '', '', %s, %s, 0, %s, 0, 'attachment', 'image/jpeg', 0)",
            $id, $now, $now_gmt, $title, $title, $now, $now_gmt, home_url( '/wp-content/uploads/' . $file )
        );
        $rel[]  = $wpdb->prepare( '(%d, %d, 0)', $id, $tt_ids[ $f ] );
        $meta[] = $wpdb->prepare( '(%d, %s, %s)', $id, '_wp_attached_file', $file );

        // Duplicates: every tenth file shares a hash with the file 5 before it.
        if ( $dupes > 0 && $i % (int) round( 1 / $dupes ) === 0 && $i >= 5 ) {
            $h = md5( 'dupe-' . ( $i - 5 ) );
            $meta[] = $wpdb->prepare( '(%d, %s, %s)', $id, VERGEML_META_HASH, $h );
            $meta[] = $wpdb->prepare( '(%d, %s, %s)', $id - 5, VERGEML_META_HASH, $h );
        } elseif ( $i % 7 === 0 ) {
            $meta[] = $wpdb->prepare( '(%d, %s, %s)', $id, VERGEML_META_HASH, md5( 'uniq-' . $i ) );
        }

        if ( count( $batch ) >= 500 ) {
            $wpdb->query( "INSERT INTO {$wpdb->posts} (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_parent, guid, menu_order, post_type, post_mime_type, comment_count) VALUES " . implode( ',', $batch ) );
            $wpdb->query( "INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order) VALUES " . implode( ',', $rel ) );
            $wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $meta ) );
            $batch = array(); $rel = array(); $meta = array();
            if ( $i % 10000 === 0 ) printf( "  %d posts (%.1fs)\n", $i, microtime( true ) - $t0 );
        }
    }
    if ( $batch ) {
        $wpdb->query( "INSERT INTO {$wpdb->posts} (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_parent, guid, menu_order, post_type, post_mime_type, comment_count) VALUES " . implode( ',', $batch ) );
        $wpdb->query( "INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order) VALUES " . implode( ',', $rel ) );
        $wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ',', $meta ) );
    }
    printf( "  %d posts + relationships + meta (%.1fs)\n", $n, microtime( true ) - $t0 );

    // Index rows with real-width embeddings for a share of the files.
    $groups = 40;
    $bases  = array();
    for ( $g = 0; $g < $groups; $g++ ) {
        $v = array();
        for ( $d = 0; $d < $dims; $d++ ) {
            $v[] = ( hexdec( substr( md5( 'base' . $g . ':' . $d ), 0, 4 ) ) / 65535 ) - 0.5;
        }
        $bases[] = $v;
    }
    $rows = array();
    $with = (int) ( $n * $embed );
    for ( $i = 0; $i < $with; $i++ ) {
        $id     = $base_id + $i;
        $g      = $i % $groups;
        $vector = $bases[ $g ];
        $seed   = ( $i * 2654435761 ) % 4294967296;
        $sum    = 0.0;
        for ( $d = 0; $d < $dims; $d++ ) {
            $seed = ( $seed * 1103515245 + 12345 ) % 2147483648;
            $vector[ $d ] += ( ( $seed / 2147483648 ) - 0.5 ) * 0.25;
            $sum += $vector[ $d ] * $vector[ $d ];
        }
        $len = sqrt( $sum );
        $packed = '';
        foreach ( $vector as $val ) { $packed .= pack( 'f', $val / $len ); }

        $rows[] = $wpdb->prepare(
            "(%d, %s, %s, %s, %s, 'photo', %s, %d, 'scale-model', '1', 'scalehash', '', '', %s, %s)",
            $id, 'Scale caption ' . $i, 'Scale alt ' . $i, 'Scale title ' . $i, wp_json_encode( array( 'group' . $g, 'scale' ) ),
            $packed, $dims, $now_gmt, $now_gmt
        );
        if ( count( $rows ) >= 200 ) {
            $wpdb->query( "INSERT IGNORE INTO {$wpdb->vergeml_ai_index} (attachment_id, caption, alt, title, tags, kind, embedding, embedding_dims, model, model_version, prompt_hash, locked, error, described_at, updated_at) VALUES " . implode( ',', $rows ) );
            $rows = array();
        }
    }
    if ( $rows ) {
        $wpdb->query( "INSERT IGNORE INTO {$wpdb->vergeml_ai_index} (attachment_id, caption, alt, title, tags, kind, embedding, embedding_dims, model, model_version, prompt_hash, locked, error, described_at, updated_at) VALUES " . implode( ',', $rows ) );
    }
    printf( "  %d index rows with %d-dim embeddings (%.1fs)\n", $with, $dims, microtime( true ) - $t0 );

    // Counts the way the plugin maintains them.
    $t1 = microtime( true );
    vergeml_update_attachment_term_count( $tt_ids, get_taxonomy( $tax ) );
    clean_term_cache( $term_ids, $tax );
    printf( "  recounted %d terms (%.1fs)\n", count( $tt_ids ), microtime( true ) - $t1 );

    update_option( 'vergeml_scale_fixture', array( 'base' => $base_id, 'n' => $n, 'terms' => $term_ids, 'tt' => $tt_ids, 'parent' => $parent ), false );
    printf( "\nready in %.1fs · attachments now: %d\n\n", microtime( true ) - $t0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'" ) );
    return;
}

/* ------------------------------------------------------------------ down */

if ( 'down' === $mode ) {
    /*
     *  By marker, not by the option: an `up` that died halfway never wrote the
     *  option, and its half a fixture still has to come out.
     */
    $t0  = microtime( true );
    $ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title LIKE %s", $wpdb->esc_like( $marker ) . '%' ) ) );
    foreach ( array_chunk( $ids, 2000 ) as $chunk ) {
        $in = implode( ',', $chunk );
        $wpdb->query( "DELETE FROM {$wpdb->vergeml_ai_index} WHERE attachment_id IN ($in)" );
        $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($in)" );
        $wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ($in)" );
        $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ($in)" );
    }
    $wpdb->query( "DELETE FROM {$wpdb->vergeml_ai_index} WHERE model = 'scale-model'" );
    $terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'search' => $marker, 'fields' => 'ids' ) );
    foreach ( array_reverse( (array) $terms ) as $tid ) { wp_delete_term( (int) $tid, $tax ); }
    $p = term_exists( $parent_name, $tax );
    if ( $p ) { wp_delete_term( (int) $p['term_id'], $tax ); }
    delete_option( 'vergeml_scale_fixture' );
    wp_cache_flush();
    printf( "removed %d posts and %d folders", count( $ids ), count( (array) $terms ) );
    printf( "removed in %.1fs · attachments now: %d\n", microtime( true ) - $t0, (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'" ) );
    return;
}

/* ------------------------------------------------------------------ time */

$fx = get_option( 'vergeml_scale_fixture' );
$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'" );
$nterms = (int) wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
printf( "\n%s attachments · %d folders · %s\n\n", number_format( $total ), $nterms, $fx ? 'fixture present' : 'no fixture' );

$big_tt = $fx ? (int) $fx['tt'][0] : 0;
$big_term = $fx ? (int) $fx['terms'][0] : 0;

// 1. the tree, exactly as the sidebar requests it
wp_cache_flush(); $q = $wpdb->num_queries; $t = microtime( true );
$req = new WP_REST_Request( 'GET', '/vergeml/v1/tree' ); $req->set_param( 'taxonomy', $tax );
$res = rest_do_request( $req ); $data = $res->get_data();
vgml_scale_line( 'GET /vergeml/v1/tree (cold cache)', $t, $q, sprintf( '%d nodes, %s unfiled, %d KB', count( $data['nodes'] ?? array() ), number_format( (int) ( $data['unassigned'] ?? 0 ) ), strlen( wp_json_encode( $data ) ) / 1024 ) );

$q = $wpdb->num_queries; $t = microtime( true );
$res = rest_do_request( $req );
vgml_scale_line( 'GET /vergeml/v1/tree (warm cache)', $t, $q );

// 2. the unfiled count on its own, uncached
wp_cache_delete( 'vergeml_unfiled_' . $tax . '_attachment', 'vergeml' ); wp_cache_flush();
$q = $wpdb->num_queries; $t = microtime( true );
$u = function_exists( 'vergeml_count_unassigned' ) ? vergeml_count_unassigned( $tax ) : -1;
vgml_scale_line( 'unfiled count (NOT EXISTS)', $t, $q, number_format( $u ) );

// 3. recounting one big term, as every assignment does
if ( $big_tt ) {
    $q = $wpdb->num_queries; $t = microtime( true );
    vergeml_update_attachment_term_count( array( $big_tt ), get_taxonomy( $tax ) );
    vgml_scale_line( 'recount one folder (per assignment)', $t, $q, get_term( $big_term, $tax )->count . ' files' );
}

// 4. the media grid, one folder, 40 per page, with our filter
if ( $big_term ) {
    wp_cache_flush(); $q = $wpdb->num_queries; $t = microtime( true );
    $_REQUEST['query'] = array( $tax => $big_term );
    $args = vergeml_ajax_query_attachments_args( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 40, 'paged' => 1 ) );
    $grid = new WP_Query( $args );
    unset( $_REQUEST['query'] );
    vgml_scale_line( 'media grid, one folder, 40/page', $t, $q, $grid->found_posts . ' found' );

    wp_cache_flush(); $q = $wpdb->num_queries; $t = microtime( true );
    $grid = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 40, 'paged' => 1 ) );
    vgml_scale_line( 'media grid, all files, 40/page (core)', $t, $q, number_format( $grid->found_posts ) . ' found' );
}

// 5. wp/v2/media through REST, which the block editor and our search use
wp_cache_flush(); $q = $wpdb->num_queries; $t = microtime( true );
$req = new WP_REST_Request( 'GET', '/wp/v2/media' ); $req->set_param( 'per_page', 40 );
$res = rest_do_request( $req );
vgml_scale_line( 'GET /wp/v2/media?per_page=40', $t, $q, count( (array) $res->get_data() ) . ' items' );

// 6. the dashboard facts, which every plugin page loads
wp_cache_flush(); $q = $wpdb->num_queries; $t = microtime( true );
$f = vergeml_journey_facts();
vgml_scale_line( 'dashboard facts (journey)', $t, $q, sprintf( '%s images, %s described, %s stale', number_format( $f['images'] ), number_format( $f['described'] ), number_format( $f['stale'] ) ) );

// 7. the whole dashboard screen, rendered
wp_cache_flush(); $q = $wpdb->num_queries; $t = microtime( true );
ob_start(); vergeml_journey_screen(); $html = ob_get_clean();
vgml_scale_line( 'dashboard screen, full render', $t, $q, ( strlen( $html ) / 1024 ) . ' KB' );

// 8. duplicates report over the hashes
wp_cache_flush(); $q = $wpdb->num_queries; $t = microtime( true );
$rep = vergeml_health_report();
vgml_scale_line( 'duplicates report', $t, $q, sprintf( '%d dupe groups', count( $rep['duplicates']['groups'] ?? array() ) ) );

// 9. AI backlog queries, as the AI screen and the runner ask them
foreach ( array( 'unindexed', 'missing-alt', 'stale' ) as $scope ) {
    wp_cache_flush(); $q = $wpdb->num_queries; $t = microtime( true );
    $c = count( vergeml_ai_pending( $scope ) );
    vgml_scale_line( "pending('$scope')", $t, $q, number_format( $c ) );
}

// 10. search by meaning: the scan of the newest VERGEML_MEANING_SCAN embeddings
if ( function_exists( 'vergeml_organize_project' ) ) {
    $with = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE embedding IS NOT NULL" );
    wp_cache_flush(); $q = $wpdb->num_queries; $t = microtime( true );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id, embedding FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL ORDER BY described_at DESC LIMIT %d", VERGEML_MEANING_SCAN ), ARRAY_A );
    $query = vergeml_organize_project( array_fill( 0, $dims, 0.03 ), VERGEML_ORGANIZE_DIMS );
    $hits = 0;
    foreach ( $rows as $row ) {
        $v = vergeml_organize_project( vergeml_index_vector_out( $row['embedding'] ), VERGEML_ORGANIZE_DIMS );
        if ( vergeml_meaning_similarity( $query, $v ) >= VERGEML_MEANING_FLOOR ) $hits++;
    }
    vgml_scale_line( 'search by meaning (scan + score)', $t, $q, sprintf( 'scanned %s of %s embedded', number_format( count( $rows ) ), number_format( $with ) ) );
}

echo "\n";
