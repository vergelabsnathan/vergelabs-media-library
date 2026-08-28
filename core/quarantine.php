<?php

if ( ! defined( 'ABSPATH' ) )
    exit;

/**
 *  Setting things aside, which is not the same as deleting them.
 *
 *  Phase 5, first and most carefully. The plugin can already tell you that a
 *  file appears in no post it scanned. What it must not do with that is
 *  offer a delete button, and this file is the reason it does not have one.
 *
 *  WordPress hard-deletes attachments. `wp_delete_attachment()` unlinks the
 *  file; MEDIA_TRASH is off by default and turning it on for somebody is not
 *  ours to do. So "unused" plus a delete button is a permanent action taken
 *  on the strength of a scan that can only ever say "I did not find a
 *  reference in the places I looked".
 *
 *  What this does instead:
 *
 *  - **Quarantine is a mark, not a move.** The file stays exactly where it
 *    is, on disk and in the database, with every id and URL intact. It is
 *    hidden from the media library and it is listed on a screen of its own.
 *    Anything still using it goes on working, which is the point: if a theme
 *    the scan cannot read is showing that photo, quarantining it breaks
 *    nothing and you find out.
 *
 *  - **Nothing may leave quarantine for at least thirty days.** Not a
 *    setting, a floor. The reason to wait is that the evidence for "unused"
 *    is absence, and absence is disproved by somebody noticing -- which
 *    takes weeks, not minutes.
 *
 *  - **There is a manifest before there is anything else.** Every file in
 *    quarantine, with its id, title, path, size, and where the scan did look.
 *    Downloadable. A list you can check against a backup is the difference
 *    between a decision and a leap.
 *
 *  - **This file never deletes anything.** Not after the delay, not on
 *    request, not at all. It contains no call to wp_delete_attachment(), no
 *    unlink(), no file removal of any kind. When the delay is up the screen
 *    says the file is eligible and hands the person the manifest; removing
 *    media stays something they do themselves, in WordPress, having looked.
 *
 *  @since 3.7
 */


const VERGEML_QUARANTINE_META   = '_vergeml_quarantined';
const VERGEML_QUARANTINE_REASON = '_vergeml_quarantine_why';

/*
 *  The floor, in days. Thirty because a month covers a monthly newsletter, a
 *  monthly report and most people's "I'll look at that later" -- and because
 *  the roadmap fixed a 30-90 day window and the short end is the one that has
 *  to be safe.
 */
const VERGEML_QUARANTINE_DAYS = 30;


/**
 *  vergeml_quarantine_add
 *
 *  Set a file aside, with a reason worth reading later.
 *
 *  The reason is stored as given rather than as a code, because the person
 *  reading it in six weeks is deciding whether to trust it, and "no
 *  references found in posts, pages and widgets on 27 August" tells them what
 *  "unused" actually meant that day.
 */

function vergeml_quarantine_add( $attachment_id, $reason = '' ) {

    $attachment_id = (int) $attachment_id;

    $post = get_post( $attachment_id );

    if ( ! $post || 'attachment' !== $post->post_type ) {
        return new WP_Error( 'vergeml_quarantine_not_a_file', __( 'That is not a media file.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    if ( vergeml_quarantine_has( $attachment_id ) ) {
        return true;
    }

    update_post_meta( $attachment_id, VERGEML_QUARANTINE_META, time() );

    if ( '' !== $reason ) {
        update_post_meta( $attachment_id, VERGEML_QUARANTINE_REASON, sanitize_text_field( $reason ) );
    }

    return true;
}


/**
 *  Take it back out. Always allowed, immediately, with no delay of any kind:
 *  a wait that protects you from deleting something must never also be a wait
 *  that stops you undoing it.
 */

function vergeml_quarantine_release( $attachment_id ) {

    $attachment_id = (int) $attachment_id;

    delete_post_meta( $attachment_id, VERGEML_QUARANTINE_META );
    delete_post_meta( $attachment_id, VERGEML_QUARANTINE_REASON );

    return true;
}


function vergeml_quarantine_has( $attachment_id ) {

    return '' !== (string) get_post_meta( (int) $attachment_id, VERGEML_QUARANTINE_META, true );
}


function vergeml_quarantine_since( $attachment_id ) {

    return (int) get_post_meta( (int) $attachment_id, VERGEML_QUARANTINE_META, true );
}


/**
 *  Whether the waiting is over for this file.
 *
 *  "Eligible" means the delay has passed and nothing has come up. It does not
 *  mean deleted, and nothing in this plugin turns it into deleted.
 */

function vergeml_quarantine_eligible( $attachment_id ) {

    $since = vergeml_quarantine_since( $attachment_id );

    if ( ! $since ) {
        return false;
    }

    return ( time() - $since ) >= ( VERGEML_QUARANTINE_DAYS * DAY_IN_SECONDS );
}


/**
 *  vergeml_quarantine_list
 *
 *  Everything set aside, newest first, with what is known about each one.
 */

function vergeml_quarantine_list( $limit = 200 ) {

    $query = new WP_Query( array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => (int) $limit,
        'fields'         => 'ids',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- quarantine IS a meta mark; that is the design.
        'meta_query'     => array( array( 'key' => VERGEML_QUARANTINE_META, 'compare' => 'EXISTS' ) ),
        'orderby'        => 'ID',
        'order'          => 'DESC',
    ) );

    $out = array();

    foreach ( $query->posts as $id ) {

        $since = vergeml_quarantine_since( $id );
        $file  = get_attached_file( $id );

        $out[] = array(
            'id'       => (int) $id,
            'title'    => get_the_title( $id ),
            'thumb'    => wp_get_attachment_image_url( (int) $id, 'thumbnail' ),
            'file'     => $file ? wp_basename( $file ) : '',
            'path'     => $file ? _wp_relative_upload_path( $file ) : '',
            'bytes'    => ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : 0,
            'since'    => $since,
            'days'     => $since ? (int) floor( ( time() - $since ) / DAY_IN_SECONDS ) : 0,
            'eligible' => vergeml_quarantine_eligible( $id ),
            'reason'   => (string) get_post_meta( $id, VERGEML_QUARANTINE_REASON, true ),
        );
    }

    return $out;
}


/**
 *  vergeml_quarantine_manifest
 *
 *  The list, as something you can keep.
 *
 *  Written before anything is decided rather than after, and carrying what
 *  the scan actually looked at -- because a manifest that says "unused" and
 *  nothing else is asking to be trusted, and this one is meant to be checked.
 */

function vergeml_quarantine_manifest() {

    $files = vergeml_quarantine_list( 5000 );
    $total = 0;

    foreach ( $files as $file ) {
        $total += (int) $file['bytes'];
    }

    return array(
        'site'      => home_url(),
        'generated' => gmdate( 'c' ),
        'note'      => __( 'These files are set aside, not deleted. Every one of them is still on disk and still at its original URL. "No references found" means none were found in the places this plugin scanned; it is not proof that nothing uses them.', 'vergelabs-media-library' ),
        'wait_days' => VERGEML_QUARANTINE_DAYS,
        'count'     => count( $files ),
        'bytes'     => $total,
        'files'     => $files,
    );
}


/* ------------------------------------------------------- out of the library */

/**
 *  Quarantined files leave the media library, and only the media library.
 *
 *  Not the front end, not an existing post, not a direct URL -- because the
 *  whole point is that anything still using the file keeps working while
 *  somebody waits to find out. This hides it from the two admin screens where
 *  it would otherwise be picked again by mistake.
 */

add_filter( 'ajax_query_attachments_args', 'vergeml_quarantine_hide_grid', 30 );

function vergeml_quarantine_hide_grid( $args ) {

    /*
     *  The "Set aside" folder asks for exactly these, so it must not be
     *  handed a NOT EXISTS on the way past. Without this guard the one view
     *  that exists to show quarantined files would always be empty.
     */
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- reading which view was asked for; core checked the ajax nonce.
    $asked = isset( $_POST['query']['vergeml_smart'] ) ? sanitize_key( wp_unslash( $_POST['query']['vergeml_smart'] ) ) : '';

    if ( 'quarantine' === $asked ) {
        return $args;
    }

    return vergeml_quarantine_exclude( $args );
}


add_action( 'pre_get_posts', 'vergeml_quarantine_hide_list' );

function vergeml_quarantine_hide_list( $query ) {

    global $pagenow;

    if ( ! is_admin() || ! $query->is_main_query() || 'upload.php' !== $pagenow ) {
        return;
    }

    // Same guard as the grid: the folder that shows them must be allowed to.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only view switch.
    $asked = isset( $_GET['vgml_smart'] ) ? sanitize_key( wp_unslash( $_GET['vgml_smart'] ) ) : '';

    if ( 'quarantine' === $asked ) {
        return;
    }

    $query->set( 'meta_query', vergeml_quarantine_and_not( $query->get( 'meta_query' ) ) );
}


function vergeml_quarantine_exclude( $args ) {

    $args['meta_query'] = vergeml_quarantine_and_not( isset( $args['meta_query'] ) ? $args['meta_query'] : array() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

    return $args;
}


/**
 *  vergeml_quarantine_and_not
 *
 *  The exclusion, AND-ed with whatever the view already asked for.
 *
 *  Appending to the caller's array is only correct while that array is a plain
 *  list of AND-ed clauses. "Missing alt text" is not: it carries
 *  `'relation' => 'OR'`, because missing alt means the meta is absent *or*
 *  empty. Pushing the exclusion in beside those made it a third alternative,
 *  and since every file that is not quarantined satisfies it, the whole
 *  meta_query matched the entire library -- the filter returned 81 files where
 *  it should have returned 20, on the grid and the list screen alike.
 *
 *  Nesting the caller's clause instead keeps its own relation to itself, so
 *  this holds however the view spells what it wants.
 */

function vergeml_quarantine_and_not( $existing ) {

    $mine = array( 'key' => VERGEML_QUARANTINE_META, 'compare' => 'NOT EXISTS' );

    $existing = (array) $existing;

    if ( empty( $existing ) ) {
        return array( $mine );
    }

    return array(
        'relation' => 'AND',
        $existing,
        $mine,
    );
}


/* ----------------------------------------------------------- a smart folder */

add_filter( 'vergeml_smart_folders', 'vergeml_quarantine_folder' );

function vergeml_quarantine_folder( $folders ) {

    $folders['quarantine'] = array(
        'label' => __( 'Set aside', 'vergelabs-media-library' ),
        'scan'  => false,
        'group' => 'clean',
    );

    return $folders;
}


add_filter( 'vergeml_smart_count_branches', 'vergeml_quarantine_count_branch' );

function vergeml_quarantine_count_branch( $branches ) {

    global $wpdb;

    $branches[] = array(
        'sql'  => "SELECT 'quarantine' AS k, COUNT(*) AS c
                     FROM {$wpdb->posts} p
                     JOIN {$wpdb->postmeta} q ON q.post_id = p.ID AND q.meta_key = %s
                    WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'",
        'args' => array( VERGEML_QUARANTINE_META ),
    );

    return $branches;
}


/*
 *  And the other five must not count what this one has taken away, or the
 *  badge above a folder disagrees with the number of files inside it. The
 *  meta key is a class constant rather than anything a request carries, and
 *  the fragment holds no placeholders, because the statement it joins is
 *  prepared with a positional argument list.
 */

add_filter( 'vergeml_smart_count_exclude', 'vergeml_quarantine_count_exclude' );

function vergeml_quarantine_count_exclude( $sql ) {

    global $wpdb;

    return $sql . " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} qx
                                      WHERE qx.post_id = p.ID
                                        AND qx.meta_key = '" . VERGEML_QUARANTINE_META . "' )";
}


add_filter( 'vergeml_smart_query_args', 'vergeml_quarantine_query_args', 10, 2 );

function vergeml_quarantine_query_args( $args, $key ) {

    if ( 'quarantine' !== $key ) {
        return $args;
    }

    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the folder IS the mark.
    return array( 'meta_query' => array( array( 'key' => VERGEML_QUARANTINE_META, 'compare' => 'EXISTS' ) ) );
}


/* --------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_quarantine_routes' );

function vergeml_quarantine_routes() {

    $can = function () {
        return current_user_can( 'manage_categories' );
    };

    register_rest_route( VERGEML_REST_NS, '/quarantine', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_quarantine_rest_list',
    ) );

    /*
     *  Setting aside and taking back, and nothing else. There is deliberately
     *  no third action here: no purge, no empty, no delete. The endpoint that
     *  would remove a file does not exist, so no request can reach one.
     */
    register_rest_route( VERGEML_REST_NS, '/quarantine-act', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_quarantine_rest_act',
        'args'                => array(
            'ids'    => array( 'required' => true ),
            'action' => array( 'type' => 'string', 'required' => true ),
            'reason' => array( 'type' => 'string', 'default' => '' ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/quarantine-manifest', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_quarantine_rest_manifest',
    ) );
}


function vergeml_quarantine_rest_list( WP_REST_Request $request ) {

    return rest_ensure_response( array(
        'wait_days' => VERGEML_QUARANTINE_DAYS,
        'files'     => vergeml_quarantine_list(),
    ) );
}


function vergeml_quarantine_rest_act( WP_REST_Request $request ) {

    $ids    = array_map( 'intval', (array) $request->get_param( 'ids' ) );
    $action = (string) $request->get_param( 'action' );
    $reason = (string) $request->get_param( 'reason' );

    if ( ! in_array( $action, array( 'set-aside', 'take-back' ), true ) ) {
        return new WP_Error(
            'vergeml_quarantine_unknown_action',
            __( 'The only two things that happen here are setting aside and taking back. Nothing deletes.', 'vergelabs-media-library' ),
            array( 'status' => 400 )
        );
    }

    $done = 0;

    foreach ( $ids as $id ) {

        $result = ( 'set-aside' === $action )
            ? vergeml_quarantine_add( $id, $reason )
            : vergeml_quarantine_release( $id );

        if ( ! is_wp_error( $result ) ) {
            $done++;
        }
    }

    return rest_ensure_response( array( 'done' => $done ) );
}


function vergeml_quarantine_rest_manifest( WP_REST_Request $request ) {

    return rest_ensure_response( vergeml_quarantine_manifest() );
}


/* ----------------------------------------------------------------- the card */

add_action( 'vergeml_ai_page_cards', 'vergeml_quarantine_card' );

function vergeml_quarantine_card() {

    ?>
    <div class="vgml-ai-card">
        <h2><?php esc_html_e( 'Set aside', 'vergelabs-media-library' ); ?></h2>
        <p class="description">
            <?php
            printf(
                /* translators: %d: the number of days a file waits. */
                esc_html__( 'Files you set aside leave the media library but stay exactly where they are — same file, same URL, still working anywhere that uses them. Nothing may be considered for removal for %d days, and this plugin never removes anything itself: when the wait is over it hands you a list.', 'vergelabs-media-library' ),
                (int) VERGEML_QUARANTINE_DAYS
            );
            ?>
        </p>
        <p>
            <button type="button" class="button" id="vgml-quarantine-refresh"><?php esc_html_e( 'Show what is set aside', 'vergelabs-media-library' ); ?></button>
            <button type="button" class="button" id="vgml-quarantine-manifest"><?php esc_html_e( 'Download the list', 'vergelabs-media-library' ); ?></button>
            <span id="vgml-quarantine-note"></span>
        </p>
        <ul id="vgml-quarantine-list" class="vgml-autofile-list"></ul>
    </div>
    <?php
}


add_action( 'admin_enqueue_scripts', 'vergeml_quarantine_assets' );

function vergeml_quarantine_assets( $hook ) {

    if ( false === strpos( (string) $hook, 'media-ai' ) ) {
        return;
    }

    wp_enqueue_script(
        'vergeml-quarantine',
        plugins_url( 'js/vergeml-quarantine.js', VERGEML_FILE ),
        array( 'wp-api-fetch' ),
        vergeml_asset_ver( 'js/vergeml-quarantine.js' ),
        true
    );

    wp_localize_script( 'vergeml-quarantine', 'vergemlQuarantine', array(
        'empty'    => __( 'Nothing is set aside.', 'vergelabs-media-library' ),
        'takeBack' => __( 'Take it back', 'vergelabs-media-library' ),
        /* translators: %d: number of days. */
        'waiting'  => __( 'Set aside %d days ago', 'vergelabs-media-library' ),
        'eligible' => __( 'The wait is over — it is on the list', 'vergelabs-media-library' ),
        'loading'  => __( 'Looking…', 'vergelabs-media-library' ),
    ) );
}
