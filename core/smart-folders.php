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
const VERGEML_META_USED_IN  = '_vergeml_used_in';
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

    $folders = array(
        'unused'     => array(
            'label' => __( 'Unused media', 'vergelabs-media-library' ),
            'scan'  => true,
            'group' => 'clean',
        ),
        'no-alt'     => array(
            'label' => __( 'Missing alt text', 'vergelabs-media-library' ),
            'scan'  => false,
            'group' => 'clean',
        ),
        'large'      => array(
            'label' => __( 'Large files', 'vergelabs-media-library' ),
            'scan'  => true,
            'group' => 'clean',
        ),
        'unattached' => array(
            'label' => __( 'Unattached', 'vergelabs-media-library' ),
            'scan'  => false,
            'group' => 'clean',
        ),
        'recent'     => array(
            'label' => __( 'This month', 'vergelabs-media-library' ),
            'scan'  => false,
            'group' => 'clean',
        ),
    );

    /*
     *  The seam. core/ai-folders.php hangs the folders that read the AI index
     *  here rather than being wired into this file, so that a site in safe
     *  mode -- where that file never loads -- simply has five folders again
     *  instead of five broken ones.
     *
     *  Everything downstream is gated on this array: both query filters check
     *  `array_key_exists` against it before translating anything, so a folder
     *  that is not registered cannot be selected however the request is
     *  spelled. Adding here is therefore the only thing an extension has to
     *  do, and the only thing it is allowed to do.
     */
    $folders = apply_filters( 'vergeml_smart_folders', $folders );

    return is_array( $folders ) ? $folders : array();
}


/**
 *  vergeml_smart_query_args
 *
 *  One smart key, as WP_Query arguments. The single translation used by the
 *  grid's ajax filter, the list screen and the counts -- three surfaces, one
 *  meaning.
 */

function vergeml_smart_query_args( $key ) {

    /*
     *  The folders that read the AI index cannot be expressed as WP_Query
     *  arguments: they are a join onto a table of ours, and there is no
     *  argument for that. So they return a marker instead, and the
     *  posts_clauses filter in core/ai-folders.php turns it into SQL.
     *
     *  A marker rather than a `post__in` of matching ids, deliberately: an id
     *  list is a second query that grows with the library and puts thousands
     *  of integers into every request. The join does not move with either.
     */
    $folders = vergeml_smart_folders();

    if ( isset( $folders[ $key ]['index'] ) ) {
        return array( 'vergeml_ai_filter' => $key );
    }

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

function vergeml_smart_counts( $fresh = false ) {

    global $wpdb;

    /*
     *  Once per request unless somebody asks otherwise.
     *
     *  The tree endpoint reads these twice -- once for the rows, once for the
     *  line above the AI group -- and two identical statements would put the
     *  endpoint over its budget of six for no answer it did not already have.
     *  The scan endpoint passes true, because it runs after doing the work
     *  that changes the numbers and a cached answer there would be the old
     *  one.
     */
    static $cache = null;

    if ( ! $fresh && null !== $cache ) {
        return $cache;
    }

    $scanned = vergeml_smart_scan_state();
    $done    = ! empty( $scanned['finished'] );

    /*
     *  All five numbers in ONE statement. They used to be five separate
     *  counts, which read clearly but pushed the tree endpoint from four
     *  queries to ten -- and the query budget is the budget. A UNION of the
     *  same five indexed counts keeps the endpoint flat.
     *
     *  Anything else that wants a number in this panel joins the same
     *  statement rather than running its own, for that reason. See
     *  core/ai-folders.php.
     */

    $core_sql = "SELECT 'unused' AS k, COUNT(*) AS c FROM {$wpdb->posts} p
          JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s AND m.meta_value = '1'
         WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
         UNION ALL
         SELECT 'no-alt', COUNT(*) FROM {$wpdb->posts} p
          LEFT JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = '_wp_attachment_image_alt'
         WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
           AND p.post_mime_type LIKE %s
           AND ( a.meta_id IS NULL OR a.meta_value = '' )
         UNION ALL
         SELECT 'large', COUNT(*) FROM {$wpdb->posts} p
          JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = %s
         WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
           AND CAST( f.meta_value AS UNSIGNED ) > %d
         UNION ALL
         SELECT 'unattached', COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_parent = 0
         UNION ALL
         SELECT 'recent', COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = 'attachment' AND post_status = 'inherit'
           AND YEAR( post_date ) = %d AND MONTH( post_date ) = %d";

    $core_args = array(
        VERGEML_META_UNUSED,
        $wpdb->esc_like( 'image/' ) . '%',
        VERGEML_META_FILESIZE,
        vergeml_large_bytes(),
        (int) current_time( 'Y' ),
        (int) current_time( 'n' ),
    );

    /*
     *  Extra branches, each `array( 'sql' => ..., 'args' => array() )`, and
     *  each producing the same two columns as the five above.
     */
    $extra_sql  = '';
    $extra_args = array();

    foreach ( (array) apply_filters( 'vergeml_smart_count_branches', array() ) as $branch ) {

        if ( empty( $branch['sql'] ) ) {
            continue;
        }

        $extra_sql .= ' UNION ALL ' . $branch['sql'];

        if ( ! empty( $branch['args'] ) ) {
            $extra_args = array_merge( $extra_args, array_values( (array) $branch['args'] ) );
        }
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- one indexed statement for a panel that ships with the page; every value is bound below.
    $wpdb->last_error = '';

    $rows = $wpdb->get_results( $wpdb->prepare(
        $core_sql . $extra_sql,
        array_merge( $core_args, $extra_args )
    ) );

    /*
     *  If the extension's half of the statement failed -- the index table
     *  dropped by hand is the realistic way -- the five core numbers went down
     *  with it, and the panel would show a tree with no counts at all because
     *  of a feature it may not even use. So the core five are asked again on
     *  their own, and everything the extension was going to answer reports
     *  null: not looked, rather than none.
     *
     *  One query on the normal path. Two only when something is already
     *  broken, which is the right place to spend an extra one.
     */
    $extended = '' !== $extra_sql;

    if ( $extended && '' !== (string) $wpdb->last_error ) {
        $extended = false;
        $rows     = $wpdb->get_results( $wpdb->prepare( $core_sql, $core_args ) );
    }
    // phpcs:enable

    $counts = array();
    foreach ( (array) $rows as $row ) {
        $counts[ $row->k ] = (int) $row->c;
    }

    $out = array();

    foreach ( vergeml_smart_folders() as $key => $spec ) {

        // A folder answered by the extended half of the statement, when that
        // half did not run or did not survive.
        if ( isset( $spec['index'] ) && ! $extended ) {
            $out[ $key ] = null;
            continue;
        }

        // Scan-backed folders whose scan never ran report null, not zero:
        // "we have not looked" and "there are none" are different answers.
        $out[ $key ] = ( ! empty( $spec['scan'] ) && ! $done )
            ? null
            : ( isset( $counts[ $key ] ) ? $counts[ $key ] : 0 );
    }

    /*
     *  Two numbers that are not folders: how much of the library has been
     *  looked at. The panel needs them to keep a count honest -- forty
     *  screenshots out of two hundred described files is not forty
     *  screenshots -- and they ride the same statement.
     */
    $out['_described'] = $extended && isset( $counts['_described'] ) ? $counts['_described'] : null;
    $out['_total']     = isset( $counts['_total'] ) ? $counts['_total'] : null;

    $cache = $out;

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

/**
 *  vergeml_refs_in
 *
 *  Every attachment a blob of text refers to. One extractor for post content,
 *  postmeta and options, because a reference is a reference wherever it hides:
 *  editor markup (wp-image-N), block and builder JSON ("id":N), gallery
 *  shortcodes (ids=), and file URLs under the uploads directory -- including
 *  the JSON-escaped form (http:\/\/...) that Elementor and every other
 *  builder storing JSON writes, which a straight URL match never sees.
 */

function vergeml_refs_in( $blob, $base_url ) {

    global $wpdb;

    $refs = array();

    if ( ! is_string( $blob ) || '' === $blob ) {
        return $refs;
    }

    // JSON-escaped slashes unescaped once, so one set of patterns serves both.
    if ( false !== strpos( $blob, '\/' ) ) {
        $blob = str_replace( '\/', '/', $blob );
    }

    if ( preg_match_all( '/wp-image-(\d+)/', $blob, $m ) ) {
        foreach ( $m[1] as $id ) {
            $refs[ (int) $id ] = true;
        }
    }
    if ( preg_match_all( '/"id"\s*:\s*(\d+)/', $blob, $m ) ) {
        foreach ( $m[1] as $id ) {
            $refs[ (int) $id ] = true;
        }
    }
    if ( preg_match_all( '/ids="([\d,\s]+)"/', $blob, $m ) ) {
        foreach ( $m[1] as $list ) {
            foreach ( explode( ',', $list ) as $id ) {
                if ( (int) $id > 0 ) {
                    $refs[ (int) $id ] = true;
                }
            }
        }
    }

    if ( $base_url && false !== strpos( $blob, $base_url )
        && preg_match_all( '#' . preg_quote( $base_url, '#' ) . '([^\s"\'<>\?]+)#', $blob, $m ) ) {

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- resolving paths to ids inside the scan.
        foreach ( array_unique( $m[1] ) as $path ) {
            // A sized copy points at its original: photo-300x200.jpg is
            // photo.jpg's use.
            $path = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $path );

            $found = $wpdb->get_var( $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
                $path
            ) );

            if ( $found ) {
                $refs[ (int) $found ] = true;
            }
        }
        // phpcs:enable
    }

    return array_keys( $refs );
}


function vergeml_smart_scan_step( $resume = null ) {

    global $wpdb;

    $chunk = (int) apply_filters( 'vergeml_scan_chunk', 200 );
    $chunk = $chunk > 0 ? $chunk : 200;

    /*
     *  refs is a map: attachment id => the post ids that use it (0 standing in
     *  for "site settings" -- widgets, the customiser, the logo). It is what
     *  makes "unused" checkable and "where is this used" answerable from the
     *  same walk. Capped per attachment, because "used in 400 places" and
     *  "used in 20 places, and more" call for the same caution.
     */
    $state = is_array( $resume ) ? $resume : array(
        'phase' => 1,
        'at'    => 0,
        'refs'  => array(),
    );

    $cap = 20;

    $uploads  = wp_get_upload_dir();
    $base_url = trailingslashit( $uploads['baseurl'] );

    $refs = array();
    foreach ( (array) $state['refs'] as $aid => $sources ) {
        $refs[ (int) $aid ] = array_map( 'intval', (array) $sources );
    }

    $note = function ( $aid, $source ) use ( &$refs, $cap ) {
        $aid = (int) $aid;
        if ( ! isset( $refs[ $aid ] ) ) {
            $refs[ $aid ] = array();
        }
        if ( count( $refs[ $aid ] ) < $cap && ! in_array( (int) $source, $refs[ $aid ], true ) ) {
            $refs[ $aid ][] = (int) $source;
        }
    };

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a bounded scan walking the tables directly is the entire feature.

    /* --- phase one: posts, and their meta ----------------------------------- */

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

        $ids = array_map( 'intval', wp_list_pluck( (array) $posts, 'ID' ) );

        /*
         *  The post's meta as well as its content. Elementor keeps the whole
         *  layout in _elementor_data, custom-field plugins keep image ids in
         *  their own keys -- a scan that reads only post_content calls every
         *  image a builder page uses "unused", which is the delete key pointed
         *  at somebody's homepage.
         */
        $meta_blobs = array();

        if ( $ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

            $params   = $ids;
            $params[] = $wpdb->esc_like( '_vergeml' ) . '%';
            $params[] = '%' . $wpdb->esc_like( 'wp-image-' ) . '%';
            $params[] = '%' . $wpdb->esc_like( 'uploads' ) . '%';

            /*
             *  $placeholders is a string of %d markers matching count( $ids ),
             *  and every value -- ids and LIKE patterns alike -- travels
             *  through prepare. The sniff cannot count a dynamic list.
             */
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                 WHERE post_id IN ( $placeholders )
                   AND meta_key NOT LIKE %s
                   AND ( meta_value LIKE %s OR meta_value LIKE %s )",
                $params
            ) );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

            foreach ( (array) $rows as $row ) {
                $meta_blobs[ (int) $row->post_id ][] = (string) $row->meta_value;
            }
        }

        foreach ( (array) $posts as $post ) {

            $pid = (int) $post->ID;

            $thumb = (int) get_post_meta( $pid, '_thumbnail_id', true );
            if ( $thumb > 0 ) {
                $note( $thumb, $pid );
            }

            foreach ( vergeml_refs_in( (string) $post->post_content, $base_url ) as $aid ) {
                $note( $aid, $pid );
            }

            if ( isset( $meta_blobs[ $pid ] ) ) {
                foreach ( $meta_blobs[ $pid ] as $blob ) {
                    foreach ( vergeml_refs_in( $blob, $base_url ) as $aid ) {
                        $note( $aid, $pid );
                    }
                }
            }
        }

        $state['refs'] = $refs;
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

    /* --- phase two: options -- widgets, the customiser, the logo ------------ */

    if ( 2 === (int) $state['phase'] ) {

        $options = $wpdb->get_col( $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options}
             WHERE option_name NOT LIKE %s AND option_name NOT LIKE %s
               AND ( option_value LIKE %s OR option_value LIKE %s )
             ORDER BY option_id ASC
             LIMIT %d OFFSET %d",
            $wpdb->esc_like( '_transient' ) . '%',
            $wpdb->esc_like( '_site_transient' ) . '%',
            '%' . $wpdb->esc_like( 'wp-image-' ) . '%',
            '%' . $wpdb->esc_like( 'uploads' ) . '%',
            $chunk,
            (int) $state['at']
        ) );

        foreach ( (array) $options as $blob ) {
            foreach ( vergeml_refs_in( (string) $blob, $base_url ) as $aid ) {
                $note( $aid, 0 ); // 0: used by the site itself, not by a post.
            }
        }

        $state['refs'] = $refs;
        $state['at']   = (int) $state['at'] + count( (array) $options );

        if ( count( (array) $options ) < $chunk ) {
            $state['phase'] = 3;
            $state['at']    = 0;
        }

        return array(
            'complete' => false,
            'phase'    => 2,
            'done'     => 0,
            'total'    => 0,
            'resume'   => $state,
        );
    }

    /* --- phase three: stamp the attachments --------------------------------- */

    $attachments = $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = 'attachment' AND post_status = 'inherit'
         ORDER BY ID ASC
         LIMIT %d OFFSET %d",
        $chunk,
        (int) $state['at']
    ) );

    foreach ( (array) $attachments as $id ) {

        $id = (int) $id;

        $sources = isset( $refs[ $id ] ) ? $refs[ $id ] : array();

        // Attached to a post is used, whatever anything else says.
        $parent = (int) get_post_field( 'post_parent', $id );
        if ( $parent > 0 && ! in_array( $parent, $sources, true ) ) {
            $sources[] = $parent;
        }

        update_post_meta( $id, VERGEML_META_UNUSED, $sources ? '0' : '1' );

        if ( $sources ) {
            update_post_meta( $id, VERGEML_META_USED_IN, implode( ',', array_slice( $sources, 0, $cap ) ) );
        } else {
            delete_post_meta( $id, VERGEML_META_USED_IN );
        }

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
            'phase'    => 3,
            'done'     => $total,
            'total'    => $total,
            'resume'   => null,
            // Fresh: this runs after the step that changed the numbers.
            'counts'   => vergeml_smart_counts( true ),
        );
    }

    return array(
        'complete' => false,
        'phase'    => 3,
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


/* ------------------------------------------------- where is this used */

/**
 *  "Used in", on every attachment's details.
 *
 *  The single question people delete files without being able to answer. The
 *  scan already knows; this puts the answer where the delete button is --
 *  linked post titles, or "Site settings" for the logo and widgets, or a plain
 *  "nothing found" that tells somebody the file is safe to remove.
 */

add_filter( 'attachment_fields_to_edit', 'vergeml_used_in_field', 12, 2 );

function vergeml_used_in_field( $fields, $post ) {

    if ( empty( vergeml_smart_scan_state()['finished'] ) ) {
        return $fields; // No index, no claims.
    }

    $raw = (string) get_post_meta( $post->ID, VERGEML_META_USED_IN, true );

    if ( '' === $raw ) {

        $unused = get_post_meta( $post->ID, VERGEML_META_UNUSED, true );

        /*
         *  Not "nothing found", which reads as "safe to delete".
         *
         *  The scan covers post content, builder layouts, widgets and site
         *  settings. It does not read theme files, stylesheets, or anything
         *  off the site, so a file this scan cannot place may still be on the
         *  homepage. The one-star reviews the competition collects are all the
         *  same review -- somebody deleted what a tool called unused -- and the
         *  wording is what invites it.
         *
         *  The headline is short enough to read and the caveat sits under it,
         *  because a single italic paragraph is what people skim past.
         */
        $html = '1' === $unused
            ? '<span class="vgml-used-pill is-none">' . esc_html__( 'No references found', 'vergelabs-media-library' ) . '</span>'
                . '<p class="vgml-used-note">'
                . esc_html__( 'The scan covers post content, builder layouts, widgets and site settings. Other uses — theme files, external links — are not scanned.', 'vergelabs-media-library' )
                . '</p>'
            : '<span class="vgml-used-pill is-none">' . esc_html__( 'Not scanned yet', 'vergelabs-media-library' ) . '</span>';

    } else {

        $links = array();

        foreach ( array_map( 'intval', explode( ',', $raw ) ) as $source ) {

            // Not a link, because there is no one screen that is "the site's
            // settings" -- the logo, the widgets and the customiser are three
            // different places. Styled as a fact rather than a destination.
            if ( 0 === $source ) {
                $links[] = '<span class="vgml-used-pill is-site">' . esc_html__( 'Site settings', 'vergelabs-media-library' ) . '</span>';
                continue;
            }

            $title = get_the_title( $source );

            if ( '' === $title ) {
                continue; // The referencing post has been deleted since the scan.
            }

            $type  = get_post_type_object( get_post_type( $source ) );
            $label = ( $type && isset( $type->labels->singular_name ) ) ? $type->labels->singular_name : '';
            $edit  = get_edit_post_link( $source );

            $inside = esc_html( $title )
                . ( '' !== $label ? '<span class="vgml-used-type">' . esc_html( $label ) . '</span>' : '' );

            /*
             *  No edit link means this user cannot edit that post, and a pill
             *  with an empty href is a link that looks clickable and goes
             *  nowhere. Tell them where the file is used either way -- that is
             *  the answer they came for -- but only make it a destination when
             *  it actually is one.
             */
            $links[] = $edit
                ? '<a class="vgml-used-pill" href="' . esc_url( $edit ) . '">' . $inside . '</a>'
                : '<span class="vgml-used-pill is-flat">' . $inside . '</span>';
        }

        $html = $links
            ? implode( '', $links )
            : '<span class="vgml-used-pill is-none">' . esc_html__( 'The pages that used this have since been deleted', 'vergelabs-media-library' ) . '</span>';
    }

    $fields['vergeml_used_in'] = array(
        'label' => __( 'Used in', 'vergelabs-media-library' ),
        'input' => 'html',
        'html'  => '<div class="vgml-used-in">' . $html . '</div>',
    );

    return $fields;
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
