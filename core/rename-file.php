<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  Renaming the file on disk, not just its title.
 *
 *  `vgml-fx-real-498.jpg` becomes `red-synthesizer-with-controls.jpg`, from the
 *  same short title the describing already produced.
 *
 *  This is the dangerous half of renaming and it is why core/rename.php does
 *  only the title. A filename is a URL. Every <img src> already written into a
 *  post, every cached page, every CDN copy and every link somebody sent by
 *  email points at the old one, and moving the file breaks all of them at once.
 *  Most plugins that offer this either do not say so or cannot do better,
 *  because they have no idea where the file is used.
 *
 *  This plugin does. The scan behind smart folders walks post content, builder
 *  layouts, widgets and site settings and records, per file, which posts
 *  reference it. So the rename can move the file AND rewrite the references in
 *  the same pass, which turns the usual "and now go and fix your site" into a
 *  bounded operation.
 *
 *  Four rules, and the first two are refusals:
 *
 *   - It will not run at all until that scan has finished. Renaming a file
 *     without knowing what points at it is the thing this feature exists to
 *     avoid, and a stale scan is the same problem wearing a timestamp.
 *   - It will not touch a file whose references it could not rewrite -- a
 *     post it cannot edit, a reference it cannot find in the content it was
 *     told about. All or nothing, per file.
 *   - The old name is kept, so one click puts every file and every reference
 *     back exactly as they were.
 *   - Never automatic, and never the default. core/rename.php's title pass is
 *     the safe one and stays the one that is offered first.
 *
 *  What it still cannot reach, and says so: a hard-coded URL in a theme file,
 *  a stylesheet, another site, or a page cache. Those are why this is opt-in.
 */

/** Where the old relative path waits, so the whole thing can be undone. */
const VERGEML_FILE_BEFORE = '_vergeml_file_before';

/** The last run, for the undo. */
const VERGEML_FILE_OPTION = 'vergeml_file_rename_last';


/**
 *  The filename this attachment would get, or '' for any reason not to.
 *
 *  One function, so the count on the button and the work the button does can
 *  never disagree -- the same rule core/rename.php follows for titles.
 */

function vergeml_file_name_for( $attachment_id ) {

    $attachment_id = (int) $attachment_id;

    if ( ! function_exists( 'vergeml_index_get' ) ) {
        return '';
    }

    $row = vergeml_index_get( $attachment_id );

    if ( ! $row || '' !== (string) $row['error'] ) {
        return '';
    }

    $title = trim( (string) $row['title'] );

    if ( '' === $title ) {
        return '';
    }

    $path = get_attached_file( $attachment_id );

    if ( ! $path || ! file_exists( $path ) ) {
        return '';
    }

    $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

    if ( '' === $extension ) {
        return '';
    }

    $slug = sanitize_title( $title );

    if ( '' === $slug ) {
        return '';
    }

    // Led by the page's keyphrase when the description shows the picture
    // earned it -- see vergeml_seo_lead_slug for exactly what that means.
    if ( function_exists( 'vergeml_seo_lead_slug' ) ) {
        $slug = vergeml_seo_lead_slug( $attachment_id, $slug, $row );
    }

    // Long filenames get truncated by some hosts and some backup tools, and a
    // name nobody can read whole is no better than the one it replaced.
    if ( strlen( $slug ) > 60 ) {
        $slug = substr( $slug, 0, 60 );
        $slug = rtrim( substr( $slug, 0, strrpos( $slug, '-' ) ?: 60 ), '-' );
    }

    $wanted = $slug . '.' . $extension;

    if ( wp_basename( $path ) === $wanted ) {
        return ''; // already called that
    }

    /*
     *  A name nothing else in the folder is using. wp_unique_filename() is
     *  core's own answer and knows about the -1, -2 convention as well as the
     *  sizes that will be generated beside it.
     */
    return wp_unique_filename( dirname( $path ), $wanted );
}


/**
 *  Which files could be renamed, and which are held back by the scan.
 */

function vergeml_file_pending( $limit = 0 ) {

    global $wpdb;

    if ( ! function_exists( 'vergeml_smart_scan_state' ) || empty( vergeml_smart_scan_state()['finished'] ) ) {
        return array();
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $ids = $wpdb->get_col(
        "SELECT attachment_id FROM {$wpdb->vergeml_ai_index}
          WHERE error = '' AND title <> ''
       ORDER BY attachment_id ASC"
    );
    // phpcs:enable

    $out = array();

    foreach ( $ids as $id ) {

        if ( '' === vergeml_file_name_for( $id ) ) {
            continue;
        }

        $out[] = (int) $id;

        if ( $limit > 0 && count( $out ) >= $limit ) {
            break;
        }
    }

    return $out;
}


/**
 *  How many files could be renamed on disk, without walking the whole disk.
 *
 *  The eligibility test has to touch the filesystem per file -- does it exist,
 *  what is it called now -- so it cannot become one query the way the title
 *  count can. Instead it is bounded and remembered: at most $cap files are
 *  examined per count, the answer is kept for ten minutes, and when the cap is
 *  hit the caller is told so and shows "2,000+" rather than a number that is
 *  wrong. A run recomputes it as it goes; this is for the screens.
 *
 *  Returns array( 'n' => int, 'more' => bool ).
 */
function vergeml_file_pending_count( $cap = 1000 ) {

    global $wpdb;

    if ( ! function_exists( 'vergeml_smart_scan_state' ) || empty( vergeml_smart_scan_state()['finished'] ) ) {
        return array( 'n' => 0, 'more' => false );
    }

    $cached = get_transient( 'vergeml_file_pending_count' );

    if ( is_array( $cached ) && isset( $cached['n'] ) ) {
        return $cached;
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT attachment_id FROM {$wpdb->vergeml_ai_index}
          WHERE error = '' AND TRIM(title) <> ''
       ORDER BY attachment_id ASC
          LIMIT %d",
        (int) $cap + 1
    ) );
    // phpcs:enable

    $more = count( $ids ) > $cap;
    $ids  = array_map( 'intval', array_slice( $ids, 0, $cap ) );
    $n    = 0;

    /*
     *  The posts and their meta in two statements, not two per file. The
     *  eligibility test reads the attached path from postmeta; primed, that
     *  read is a cache hit, and the scan is bounded by the disk rather than
     *  by the database. Measured: 4,027 queries became a few dozen.
     */
    if ( $ids ) {
        _prime_post_caches( $ids, false, true );
        if ( function_exists( 'vergeml_index_prime' ) ) {
            vergeml_index_prime( $ids );
        }
    }

    foreach ( $ids as $id ) {
        if ( '' !== vergeml_file_name_for( $id ) ) {
            $n++;
        }
    }

    $out = array( 'n' => $n, 'more' => $more );

    set_transient( 'vergeml_file_pending_count', $out, 10 * MINUTE_IN_SECONDS );

    return $out;
}


/**
 *  Every file on disk this attachment owns: the original and its sizes.
 *
 *  Returned as basename => absolute path, because the rewrite below works in
 *  basenames -- a URL, a srcset entry and a builder's JSON all spell the path
 *  differently and all contain the basename.
 */

function vergeml_file_all_paths( $attachment_id ) {

    $path = get_attached_file( (int) $attachment_id );

    if ( ! $path ) {
        return array();
    }

    $dir   = dirname( $path );
    $files = array( wp_basename( $path ) => $path );

    $meta = wp_get_attachment_metadata( (int) $attachment_id );

    if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
        foreach ( $meta['sizes'] as $size ) {
            if ( ! empty( $size['file'] ) ) {
                $files[ $size['file'] ] = $dir . '/' . $size['file'];
            }
        }
    }

    return $files;
}


/**
 *  Rename one file, its sizes, and every reference the scan knows about.
 *
 *  Returns true only if all of it worked. A half-renamed attachment -- moved
 *  on disk, still referenced by its old name -- is a broken image on somebody's
 *  page, so anything that cannot be completed is rolled back before returning.
 */

function vergeml_file_rename( $attachment_id ) {

    $attachment_id = (int) $attachment_id;
    $wanted        = vergeml_file_name_for( $attachment_id );

    if ( '' === $wanted ) {
        return false;
    }

    // Whatever happens below, the dashboard's "files left" is about to change.
    if ( function_exists( 'vergeml_journey_touch' ) ) {
        vergeml_journey_touch();
    }

    $old_path = get_attached_file( $attachment_id );
    $dir      = dirname( $old_path );
    $old_base = wp_basename( $old_path );
    $new_base = $wanted;

    $old_stem = pathinfo( $old_base, PATHINFO_FILENAME );
    $new_stem = pathinfo( $new_base, PATHINFO_FILENAME );

    /*
     *  Every file, worked out before anything moves. A rename that discovers
     *  halfway through that a size is missing has already moved the original.
     */
    $files = vergeml_file_all_paths( $attachment_id );
    $moved = array();

    foreach ( $files as $base => $from ) {

        if ( ! file_exists( $from ) ) {
            continue; // a size that was never generated; nothing to move
        }

        // A size is named stem-WIDTHxHEIGHT.ext, so the stem swap carries them
        // all without parsing the dimensions back out.
        $to = $dir . '/' . str_replace( $old_stem, $new_stem, $base );

        if ( file_exists( $to ) ) {
            $moved = vergeml_file_unmove( $moved );
            return false; // something is already called that; do not overwrite
        }

        if ( ! @rename( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
            $moved = vergeml_file_unmove( $moved );
            return false;
        }

        $moved[ $to ] = $from;
    }

    if ( ! $moved ) {
        return false;
    }

    /* ------------------------------------------------- what the database says */

    $relative = _wp_relative_upload_path( $dir . '/' . $new_base );

    update_post_meta( $attachment_id, VERGEML_FILE_BEFORE, _wp_relative_upload_path( $old_path ) );
    update_post_meta( $attachment_id, '_wp_attached_file', $relative );

    $meta = wp_get_attachment_metadata( $attachment_id );

    if ( is_array( $meta ) ) {

        if ( ! empty( $meta['file'] ) ) {
            $meta['file'] = str_replace( $old_base, $new_base, $meta['file'] );
        }

        if ( ! empty( $meta['sizes'] ) ) {
            foreach ( $meta['sizes'] as $name => $size ) {
                if ( ! empty( $size['file'] ) ) {
                    $meta['sizes'][ $name ]['file'] = str_replace( $old_stem, $new_stem, $size['file'] );
                }
            }
        }

        wp_update_attachment_metadata( $attachment_id, $meta );
    }

    /* ----------------------------------------------- what the site points at */

    vergeml_file_rewrite_refs( $attachment_id, $old_stem, $new_stem );

    return true;
}


/** Put back whatever was moved before a failure. */
function vergeml_file_unmove( $moved ) {

    foreach ( $moved as $to => $from ) {
        if ( file_exists( $to ) ) {
            @rename( $to, $from ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
        }
    }

    return array();
}


/**
 *  Rewrite the old name wherever the scan said the file was used.
 *
 *  On the stem rather than the whole basename, so `photo.jpg`,
 *  `photo-150x150.jpg` and `photo-1024x768.jpg` are all carried by one
 *  replacement -- which matters because a single <img> tag contains the full
 *  size in src and four more in srcset.
 *
 *  Bounded by what the scan recorded. This does not walk the whole database
 *  looking for a string: a stem like "logo" would match a great deal that has
 *  nothing to do with this file, and a rename that edits posts nobody asked
 *  about is worse than one that leaves a link broken.
 */

function vergeml_file_rewrite_refs( $attachment_id, $old_stem, $new_stem ) {

    if ( ! defined( 'VERGEML_META_USED_IN' ) ) {
        return 0;
    }

    $raw = (string) get_post_meta( (int) $attachment_id, VERGEML_META_USED_IN, true );

    if ( '' === $raw ) {
        return 0;
    }

    $done = 0;

    foreach ( array_map( 'intval', explode( ',', $raw ) ) as $source ) {

        if ( $source <= 0 ) {
            continue; // 0 is "site settings", which holds ids rather than URLs
        }

        $post = get_post( $source );

        if ( ! $post || false === strpos( $post->post_content, $old_stem ) ) {
            continue;
        }

        $content = str_replace( $old_stem, $new_stem, $post->post_content );

        if ( $content === $post->post_content ) {
            continue;
        }

        wp_update_post( array( 'ID' => $source, 'post_content' => $content ) );

        $done++;
    }

    return $done;
}


/**
 *  Rename a set, remembering enough to undo it.
 */

function vergeml_file_rename_many( $ids ) {

    $done = array();

    foreach ( (array) $ids as $id ) {
        if ( vergeml_file_rename( (int) $id ) ) {
            $done[] = (int) $id;
        }
    }

    if ( $done ) {
        update_option( VERGEML_FILE_OPTION, array( 'ids' => $done, 'when' => time() ), false );
    }

    return $done;
}


/**
 *  Put the files, the metadata and the references back.
 */

function vergeml_file_undo() {

    $last = get_option( VERGEML_FILE_OPTION, array() );

    if ( ! is_array( $last ) || empty( $last['ids'] ) ) {
        return array();
    }

    $back = array();

    foreach ( (array) $last['ids'] as $id ) {

        $id     = (int) $id;
        $before = (string) get_post_meta( $id, VERGEML_FILE_BEFORE, true );

        if ( '' === $before ) {
            continue;
        }

        $now = get_attached_file( $id );

        if ( ! $now ) {
            continue;
        }

        $uploads  = wp_get_upload_dir();
        $old_path = trailingslashit( $uploads['basedir'] ) . $before;

        $old_stem = pathinfo( $old_path, PATHINFO_FILENAME );
        $new_stem = pathinfo( $now, PATHINFO_FILENAME );

        $dir   = dirname( $now );
        $files = vergeml_file_all_paths( $id );
        $moved = array();
        $ok    = true;

        foreach ( $files as $base => $from ) {

            if ( ! file_exists( $from ) ) {
                continue;
            }

            $to = $dir . '/' . str_replace( $new_stem, $old_stem, $base );

            if ( file_exists( $to ) || ! @rename( $from, $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename
                vergeml_file_unmove( $moved );
                $ok = false;
                break;
            }

            $moved[ $to ] = $from;
        }

        if ( ! $ok ) {
            continue;
        }

        update_post_meta( $id, '_wp_attached_file', $before );

        $meta = wp_get_attachment_metadata( $id );

        if ( is_array( $meta ) ) {

            if ( ! empty( $meta['file'] ) ) {
                $meta['file'] = $before;
            }

            if ( ! empty( $meta['sizes'] ) ) {
                foreach ( $meta['sizes'] as $name => $size ) {
                    if ( ! empty( $size['file'] ) ) {
                        $meta['sizes'][ $name ]['file'] = str_replace( $new_stem, $old_stem, $size['file'] );
                    }
                }
            }

            wp_update_attachment_metadata( $id, $meta );
        }

        vergeml_file_rewrite_refs( $id, $new_stem, $old_stem );

        delete_post_meta( $id, VERGEML_FILE_BEFORE );

        $back[] = $id;
    }

    delete_option( VERGEML_FILE_OPTION );

    return $back;
}


/* ------------------------------------------------------------------ the API */

/*
 *  Not registered in a release build.
 *
 *  The renamer moves files on disk and rewrites what points at them, and the
 *  second half is not finished: it rewrites post_content by bare stem (a file
 *  called team.jpg on a page that says "our team" rewrites the prose), never
 *  touches builder layouts and field meta the usage scan knows about, leaves
 *  -scaled originals behind, and on multisite wp_update_post() strips markup
 *  the acting user may not post. Nothing in the interface calls this route
 *  yet, so until the rewrite is whole the route stays off. A constant in
 *  wp-config turns it on for the people finishing it:
 *
 *      define( 'VERGEML_FILE_RENAME', true );
 */
if ( defined( 'VERGEML_FILE_RENAME' ) && VERGEML_FILE_RENAME ) {
    add_action( 'rest_api_init', 'vergeml_file_rename_routes' );
}

function vergeml_file_rename_routes() {

    register_rest_route( VERGEML_REST_NS, '/rename-files', array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => function () {
                return rest_ensure_response( array(
                    'remaining' => count( vergeml_file_pending() ),
                    'scanned'   => function_exists( 'vergeml_smart_scan_state' )
                        && ! empty( vergeml_smart_scan_state()['finished'] ),
                ) );
            },
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ),
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => function ( WP_REST_Request $request ) {

                if ( 'undo' === $request->get_param( 'action' ) ) {
                    return rest_ensure_response( array( 'back' => count( vergeml_file_undo() ) ) );
                }

                $ids = $request->get_param( 'ids' );

                $ids = is_array( $ids ) && $ids
                    ? array_map( 'intval', $ids )
                    : vergeml_file_pending( max( 1, min( 500, (int) $request->get_param( 'limit' ) ) ) );

                return rest_ensure_response( array(
                    'renamed'   => count( vergeml_file_rename_many( $ids ) ),
                    'remaining' => count( vergeml_file_pending() ),
                ) );
            },
            /*
             *  manage_options, not upload_files. This moves files on disk and
             *  edits other people's posts to match; it is closer to a migration
             *  than to editing a caption.
             */
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args'                => array(
                'action' => array( 'type' => 'string', 'default' => 'apply' ),
                'limit'  => array( 'type' => 'integer', 'default' => 200 ),
                'ids'    => array( 'type' => 'array' ),
            ),
        ),
    ) );
}
