<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Smart folders: folders whose contents are a question, not a membership.
 *
 *  "Unused media", "Missing alt text", "Over a megabyte", "Unattached", "This
 *  month". Each is a saved query drawn as a row in the tree, and that is the
 *  whole trick -- folders here are taxonomy terms, terms are queryable, and a
 *  query result can sit beside them in the same list. A folder plugin built on
 *  a parent-child table has nowhere to put any of this.
 *
 *  Two of the five need an index. "Unused" cannot be asked live -- it means
 *  reading every post's content -- and the file size WordPress stores is buried
 *  inside serialised metadata where no query reaches it. So there is a scan:
 *  chunked like the importer, driven by the browser, walking posts once to
 *  collect every attachment they reference and stamping every attachment with
 *  what it learned. The other three are cheap enough to ask every time.
 *
 *  @since 3.2
 */


const VERGEML_META_UNUSED   = '_vergeml_unused';
const VERGEML_META_FILESIZE = '_vergeml_filesize';
const VERGEML_SCAN_OPTION   = 'vergeml_smart_scan';

// Over this many bytes counts as a large file. One megabyte, unless a site
// that deals in RAW files says otherwise.
function vergeml_large_bytes() {
    return (int) apply_filters( 'vergeml_large_file_bytes', MB_IN_BYTES );
}


/**
 *  vergeml_smart_folders
 *
 *  The registry: what each smart folder is called, whether it needs the scan,
 *  and how to count it. Counting is one SQL statement each, because these
 *  numbers sit in the tree and the tree loads with the page.
 */

function vergeml_smart_folders() {

    return array(
        'unused'     => array(
            'label' => __( 'Unused media', 'vergelabs-media-library' ),
            'scan'  => true,
        ),
        'no-alt'     => array(
            'label' => __( 'Missing alt text', 'vergelabs-media-library' ),
            'scan'  => false,
        ),
        'large'      => array(
            'label' => __( 'Large files', 'vergelabs-media-library' ),
            'scan'  => true,
        ),
        'unattached' => array(
            'label' => __( 'Unattached', 'vergelabs-media-library' ),
            'scan'  => false,
        ),
        'recent'     => array(
            'label' => __( 'This month', 'vergelabs-media-library' ),
            'scan'  => false,
        ),
    );
}


/**
 *  vergeml_smart_query_args
 *
 *  One smart key, as WP_Query arguments. The single translation used by the
 *  grid's ajax filter, the list screen and the counts -- three surfaces, one
 *  meaning.
 */

function vergeml_smart_query_args( $key ) {

    switch ( $key ) {

        case 'unused':
            return array(
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the smart folder IS a meta lookup, over the index the scan built.
                'meta_query' => array( array( 'key' => VERGEML_META_UNUSED, 'value' => '1' ) ),
            );

        case 'no-alt':
            return array(
                'post_mime_type' => 'image',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- "missing alt" is by definition a meta absence; core keys alt text on meta.
                'meta_query'     => array(
                    'relation' => 'OR',
                    array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ),
                    array( 'key' => '_wp_attachment_image_alt', 'value' => '' ),
                ),
            );

        case 'large':
            return array(
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- over the numeric size index the scan maintains.
                'meta_query' => array( array(
                    'key'     => VERGEML_META_FILESIZE,
                    'value'   => vergeml_large_bytes(),
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ) ),
            );

        case 'unattached':
            return array( 'post_parent' => 0 );

        case 'recent':
            return array( 'date_query' => array( array(
                'year'  => (int) current_time( 'Y' ),
                'month' => (int) current_time( 'n' ),
            ) ) );
    }

    return array();
}


/**
 *  vergeml_smart_counts
 *
 *  All five numbers, cheaply. Scan-backed folders whose scan has never run
 *  report null rather than zero -- "we have not looked" and "there are none"
 *  are different answers, and the tree shows them differently.
 */

function vergeml_smart_counts() {

    global $wpdb;

    $scanned = vergeml_smart_scan_state();
    $done    = ! empty( $scanned['finished'] );

    $out = array();

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- five counts for a panel that ships with the page; each is one indexed statement.

    foreach ( vergeml_smart_folders() as $key => $spec ) {

        if ( $spec['scan'] && ! $done ) {
            $out[ $key ] = null;
            continue;
        }

        switch ( $key ) {

            case 'unused':
                $out[ $key ] = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} p
                      JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = '1'
                     WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'",
                    VERGEML_META_UNUSED
                ) );
                break;

            case 'no-alt':
                $out[ $key ] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->posts} p
                      LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'
                     WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
                       AND p.post_mime_type LIKE 'image/%'
                       AND ( m.meta_id IS NULL OR m.meta_value = '' )"
                );
                break;

            case 'large':
                $out[ $key ] = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} p
                      JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
                     WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
                       AND CAST( m.meta_value AS UNSIGNED ) > %d",
                    VERGEML_META_FILESIZE,
                    vergeml_large_bytes()
                ) );
                break;

            case 'unattached':
                $out[ $key ] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_parent = 0"
                );
                break;

            case 'recent':
                $out[ $key ] = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type = 'attachment' AND post_status = 'inherit'
                       AND YEAR( post_date ) = %d AND MONTH( post_date ) = %d",
                    (int) current_time( 'Y' ),
                    (int) current_time( 'n' )
                ) );
                break;
        }
    }

    // phpcs:enable

    return $out;
}


/* ------------------------------------------------------------------ scan */

function vergeml_smart_scan_state() {
    $state = get_option( VERGEML_SCAN_OPTION, array() );
    return is_array( $state ) ? $state : array();
}


/**
 *  vergeml_smart_scan_step
 *
 *  One chunk of the scan, resumable, exactly the importer's shape.
 *
 *  Phase one walks POSTS, not attachments -- asking "which attachments does
 *  this post use" once per post is bounded work, where asking "which posts use
 *  this attachment" once per attachment is a LIKE over all content per file.
 *  A post uses an attachment when it is the post's featured image, or the
 *  post's parent, or its id or file URL appears in the content -- which covers
 *  image blocks, galleries, page builders and pasted URLs alike.
 *
 *  Phase two walks attachments, stamping each with what phase one learned and,
 *  while it is holding the file anyway, its size on disk -- one scan feeds
 *  both indexes.
 */

function vergeml_smart_scan_step( $resume = null ) {

    global $wpdb;

    $chunk = (int) apply_filters( 'vergeml_scan_chunk', 200 );
    $chunk = $chunk > 0 ? $chunk : 200;

    $state = is_array( $resume ) ? $resume : array(
        'phase' => 1,
        'at'    => 0,
        'used'  => array(),
    );

    $uploads  = wp_get_upload_dir();
    $base_url = trailingslashit( $uploads['baseurl'] );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a bounded scan walking the tables directly is the entire feature.

    if ( 1 === (int) $state['phase'] ) {

        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_content FROM {$wpdb->posts}
             WHERE post_type NOT IN ( 'attachment', 'revision', 'nav_menu_item' )
               AND post_status NOT IN ( 'trash', 'auto-draft' )
             ORDER BY ID ASC
             LIMIT %d OFFSET %d",
            $chunk,
            (int) $state['at']
        ) );

        $used = array_fill_keys( array_map( 'intval', (array) $state['used'] ), true );

        foreach ( (array) $posts as $post ) {

            // The featured image.
            $thumb = (int) get_post_meta( $post->ID, '_thumbnail_id', true );
            if ( $thumb > 0 ) {
                $used[ $thumb ] = true;
            }

            $content = (string) $post->post_content;

            if ( '' === $content ) {
                continue;
            }

            // Ids the editor writes into markup: wp-image-123, "id":123 in
            // block comments, ids="1,2,3" in gallery shortcodes.
            if ( preg_match_all( '/wp-image-(\d+)/', $content, $m ) ) {
                foreach ( $m[1] as $id ) {
                    $used[ (int) $id ] = true;
                }
            }
            if ( preg_match_all( '/"id"\s*:\s*(\d+)/', $content, $m ) ) {
                foreach ( $m[1] as $id ) {
                    $used[ (int) $id ] = true;
                }
            }
            if ( preg_match_all( '/ids="([\d,\s]+)"/', $content, $m ) ) {
                foreach ( $m[1] as $list ) {
                    foreach ( explode( ',', $list ) as $id ) {
                        if ( (int) $id > 0 ) {
                            $used[ (int) $id ] = true;
                        }
                    }
                }
            }

            // File URLs, resolved back to attachments at the end of the phase
            // would mean keeping every URL; resolving here keeps the state
            // small. Only paths under the uploads directory can match.
            if ( $base_url && false !== strpos( $content, $base_url )
                && preg_match_all( '#' . preg_quote( $base_url, '#' ) . '([^\s"\'<>\?]+)#', $content, $m ) ) {

                foreach ( array_unique( $m[1] ) as $path ) {
                    // Sized copies point at their original: photo-300x200.jpg
                    // is photo.jpg's use.
                    $path = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $path );

                    $found = $wpdb->get_var( $wpdb->prepare(
                        "SELECT post_id FROM {$wpdb->postmeta}
                         WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
                        $path
                    ) );

                    if ( $found ) {
                        $used[ (int) $found ] = true;
                    }
                }
            }
        }

        $state['used'] = array_keys( $used );
        $state['at']   = (int) $state['at'] + count( (array) $posts );

        $total_posts = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type NOT IN ( 'attachment', 'revision', 'nav_menu_item' )
               AND post_status NOT IN ( 'trash', 'auto-draft' )"
        );

        if ( count( (array) $posts ) < $chunk ) {
            $state['phase'] = 2;
            $state['at']    = 0;
        }

        return array(
            'complete' => false,
            'phase'    => 1,
            'done'     => min( $state['at'], $total_posts ),
            'total'    => $total_posts,
            'resume'   => $state,
        );
    }

    /* --- phase two: stamp the attachments ---------------------------------- */

    $attachments = $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = 'attachment' AND post_status = 'inherit'
         ORDER BY ID ASC
         LIMIT %d OFFSET %d",
        $chunk,
        (int) $state['at']
    ) );

    $used = array_fill_keys( array_map( 'intval', (array) $state['used'] ), true );

    foreach ( (array) $attachments as $id ) {

        $id = (int) $id;

        // Attached to a post is used, whatever the content says.
        $parent = (int) get_post_field( 'post_parent', $id );

        $is_used = $parent > 0 || isset( $used[ $id ] );

        update_post_meta( $id, VERGEML_META_UNUSED, $is_used ? '0' : '1' );

        // The size index, fed by the same walk.
        $file = get_attached_file( $id );
        $size = ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : 0;
        update_post_meta( $id, VERGEML_META_FILESIZE, (string) $size );
    }

    $state['at'] = (int) $state['at'] + count( (array) $attachments );

    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
    );

    // phpcs:enable

    if ( count( (array) $attachments ) < $chunk ) {

        update_option( VERGEML_SCAN_OPTION, array( 'finished' => time() ), false );

        return array(
            'complete' => true,
            'phase'    => 2,
            'done'     => $total,
            'total'    => $total,
            'resume'   => null,
            // Every count, so the caller's five rows become real numbers at
            // once -- and so completion means the same thing whoever asked.
            'counts'   => vergeml_smart_counts(),
        );
    }

    return array(
        'complete' => false,
        'phase'    => 2,
        'done'     => (int) $state['at'],
        'total'    => $total,
        'resume'   => $state,
    );
}


/**
 *  New uploads keep both indexes current, so a finished scan does not rot the
 *  moment somebody adds a file: a fresh upload is unused until something uses
 *  it, and its size is knowable right now.
 */

add_action( 'add_attachment', 'vergeml_smart_stamp_new', 20 );

function vergeml_smart_stamp_new( $attachment_id ) {

    if ( empty( vergeml_smart_scan_state()['finished'] ) ) {
        return; // No index yet; the scan will stamp it with everything else.
    }

    $parent = (int) get_post_field( 'post_parent', $attachment_id );
    update_post_meta( $attachment_id, VERGEML_META_UNUSED, $parent > 0 ? '0' : '1' );

    $file = get_attached_file( $attachment_id );
    $size = ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : 0;
    update_post_meta( $attachment_id, VERGEML_META_FILESIZE, (string) $size );
}


/* ------------------------------------------------------------------ REST */

add_action( 'rest_api_init', 'vergeml_register_smart_routes' );

function vergeml_register_smart_routes() {

    register_rest_route( VERGEML_REST_NS, '/smart-scan', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_rest_smart_scan',
        // The scan writes meta on every attachment; that is curation, not
        // uploading, so it takes the folder-management capability.
        'permission_callback' => function () {
            return current_user_can( 'manage_categories' );
        },
        'args'                => array(
            'resume' => array( 'type' => 'object' ),
        ),
    ) );
}


function vergeml_rest_smart_scan( WP_REST_Request $request ) {

    $resume = $request->get_param( 'resume' );

    return rest_ensure_response( vergeml_smart_scan_step( is_array( $resume ) ? $resume : null ) );
}


/* ------------------------------------------- the two screens' query paths */

/**
 *  The grid. wp.media hands every prop the browser set straight through to
 *  this filter, so the tree marks its selection with `vergeml_smart` and the
 *  translation happens here, on the server, once.
 */

add_filter( 'ajax_query_attachments_args', 'vergeml_smart_grid_query', 20 );

function vergeml_smart_grid_query( $args ) {

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- reading which filter the media grid asked for; core has checked the ajax nonce already.
    $key = isset( $_POST['query']['vergeml_smart'] )
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        ? sanitize_key( wp_unslash( $_POST['query']['vergeml_smart'] ) )
        : '';

    if ( '' === $key ) {
        return $args;
    }

    if ( ! array_key_exists( $key, vergeml_smart_folders() ) ) {
        return $args;
    }

    return array_merge( $args, vergeml_smart_query_args( $key ) );
}


/**
 *  The list. A plain, bookmarkable query var, like the folder filters.
 */

add_action( 'pre_get_posts', 'vergeml_smart_list_query' );

function vergeml_smart_list_query( $query ) {

    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    global $pagenow;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a bookmarkable read-only filter.
    $key = isset( $_GET['vgml_smart'] ) ? sanitize_key( wp_unslash( $_GET['vgml_smart'] ) ) : '';

    if ( 'upload.php' !== $pagenow || '' === $key || ! array_key_exists( $key, vergeml_smart_folders() ) ) {
        return;
    }

    foreach ( vergeml_smart_query_args( $key ) as $arg => $value ) {
        $query->set( $arg, $value );
    }
}
