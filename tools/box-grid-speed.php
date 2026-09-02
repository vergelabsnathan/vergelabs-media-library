<?php
/*
 *  Why does the grid take so long when a folder is picked?
 *
 *  Six hundred pictures is nothing for a taxonomy query, so before touching
 *  the query this asks the other question: what is actually being sent to the
 *  browser. A grid that is slow because it is downloading six hundred
 *  full-size photographs and scaling them in the page looks exactly like a
 *  grid that is slow because of a query, and no amount of query tuning fixes
 *  it.
 *
 *  Read-only.
 */

global $wpdb;

$taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';

printf( "taxonomy: %s\n", $taxonomy );

/*
 *  The biggest folder, not the first one alphabetically.
 *
 *  The first version took whichever folder came back first and landed on one
 *  holding sixteen files, which answers a question nobody asked: of course
 *  sixteen files are quick. The complaint is about a real folder.
 */
$terms = get_terms( array(
    'taxonomy'   => $taxonomy,
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 5,
) );

if ( is_wp_error( $terms ) || ! $terms ) {
    echo "no folders with anything in them\n";
    return;
}

$term = $terms[0];
printf( "picking the largest folder, \"%s\" (%d files)\n\n", $term->name, (int) $term->count );

// ------------------------------------------------- the library as a whole

$library = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts}
      WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
);

$no_thumb = 0;
$heavy    = 0;
$lib_bytes = 0;

$ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts}
      WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'
      LIMIT 800"
);

foreach ( (array) $ids as $one ) {
    $m = wp_get_attachment_metadata( (int) $one );
    if ( ! is_array( $m ) || empty( $m['sizes']['thumbnail'] ) ) {
        $no_thumb++;
    }
    $f = get_attached_file( (int) $one );
    if ( $f && file_exists( $f ) ) {
        $b = (int) filesize( $f );
        $lib_bytes += $b;
        if ( $b > 1048576 ) {
            $heavy++;
        }
    }
}

$looked = max( 1, count( (array) $ids ) );

printf( "library: %d images; of %d looked at, %d have no thumbnail (%d%%), %d are over 1 MB, average %.2f MB\n\n",
    $library, $looked, $no_thumb, (int) round( 100 * $no_thumb / $looked ), $heavy, $lib_bytes / $looked / 1048576 );

// ---------------------------------------------------------------- the query

$args = array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'posts_per_page' => 40,
    'tax_query'      => array( array(
        'taxonomy' => $taxonomy,
        'field'    => 'term_id',
        'terms'    => array( (int) $term->term_id ),
    ) ),
);

$t = microtime( true );
$q = new WP_Query( $args );
$query_ms = ( microtime( true ) - $t ) * 1000;

printf( "WP_Query for one page of 40: %.0f ms, %d found\n", $query_ms, (int) $q->found_posts );

$t = microtime( true );
$all = new WP_Query( array_merge( $args, array( 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
$all_ms = ( microtime( true ) - $t ) * 1000;

printf( "WP_Query for every file in it:  %.0f ms, %d found\n\n", $all_ms, count( $all->posts ) );

// ------------------------------------------------------- what the grid sends

$total   = 0;
$missing = 0;
$bytes   = 0;
$thumb   = 0;
$biggest = 0;
$worst   = '';

foreach ( array_slice( (array) $all->posts, 0, 400 ) as $id ) {

    $meta = wp_get_attachment_metadata( $id );
    $file = get_attached_file( $id );
    $total++;

    if ( ! is_array( $meta ) || empty( $meta['sizes'] ) || ! isset( $meta['sizes']['thumbnail'] ) ) {
        $missing++;
    } else {
        $dir = dirname( (string) $file );
        $t_file = $dir . '/' . $meta['sizes']['thumbnail']['file'];
        if ( file_exists( $t_file ) ) {
            $thumb += (int) filesize( $t_file );
        }
    }

    if ( $file && file_exists( $file ) ) {
        $size   = (int) filesize( $file );
        $bytes += $size;
        if ( $size > $biggest ) {
            $biggest = $size;
            $worst   = basename( $file );
        }
    }
}

if ( 0 === $total ) {
    echo "nothing to weigh\n";
    return;
}

printf( "of %d files in this folder:\n", $total );
printf( "  %d have no thumbnail size registered\n", $missing );
printf( "  average original: %.2f MB\n", $bytes / $total / 1048576 );
printf( "  largest original: %.2f MB (%s)\n", $biggest / 1048576, $worst );

if ( $thumb > 0 ) {
    printf( "  average thumbnail: %.0f KB\n", $thumb / max( 1, $total - $missing ) / 1024 );
}

printf( "\nif the grid served originals that page would weigh %.0f MB; on thumbnails, %.1f MB\n",
    $bytes / 1048576,
    $thumb / 1048576
);

// --------------------------------------------------- what else is on the hook

$hooks = array( 'pre_get_posts', 'ajax_query_attachments_args' );

echo "\nfilters on the queries the grid runs:\n";

foreach ( $hooks as $hook ) {

    global $wp_filter;

    if ( ! isset( $wp_filter[ $hook ] ) ) {
        continue;
    }

    $names = array();

    foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
        foreach ( $callbacks as $cb ) {
            $fn = $cb['function'];
            if ( is_string( $fn ) ) {
                $names[] = $fn;
            } elseif ( is_array( $fn ) && isset( $fn[1] ) ) {
                $names[] = ( is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0] ) . '::' . $fn[1];
            }
        }
    }

    printf( "  %s (%d):\n", $hook, count( $names ) );
    foreach ( $names as $n ) {
        printf( "    %s%s\n", $n, 0 === strpos( $n, 'vergeml' ) ? '   <- ours' : '' );
    }
}
