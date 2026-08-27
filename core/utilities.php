<?php

if ( ! defined( 'ABSPATH' ) )
    exit;

/**
 *  The rest of Phase 5, in trust order.
 *
 *  Three utilities that each stand on something already built and each stop
 *  short of the thing that would make them dangerous:
 *
 *  **Merging duplicates** works on the health report's groups and does not
 *  merge anything. It keeps the copy that is actually referenced -- or the
 *  oldest, when none of them is -- and sets the others aside, which is
 *  reversible, waits thirty days, and never deletes. "Merge" in every other
 *  plugin means "delete the ones I picked".
 *
 *  **Filling in alt text** writes only where alt is empty, only from a
 *  description the site already paid for, and never over a field a person has
 *  touched -- core/ai-index.php has tracked which of those are hand-written
 *  since it shipped. Every value it writes is stamped, so all of it can be
 *  taken back in one go.
 *
 *  **Finding similar files** is the piece deferred out of Phase 4a. It is
 *  arithmetic over vectors the library already holds: no model call, no
 *  credit, no service. It answers "more like this one", which is a question
 *  about one file and therefore bounded -- unlike a similarity *view*, which
 *  would have to score the whole library while somebody waits, and is still
 *  not built for exactly that reason.
 *
 *  @since 3.8
 */


const VERGEML_ALT_FILLED = '_vergeml_alt_filled';


/* ------------------------------------------------------------- duplicates */

/**
 *  vergeml_merge_plan
 *
 *  For each group of duplicates: which copy to keep, and which to set aside.
 *
 *  The keeper is the one something points at. A file referenced by a post is
 *  the one whose URL is in somebody's content, and keeping a different copy
 *  would mean the reference now points at a file that has been set aside --
 *  which is exactly the breakage this whole area exists to avoid. Only when
 *  none of them is referenced does age decide, and then the oldest wins
 *  because it is the one whose URL has had longest to escape.
 */

function vergeml_merge_plan() {

    if ( ! function_exists( 'vergeml_health_report' ) ) {
        return array();
    }

    $report = vergeml_health_report();

    $groups = isset( $report['duplicates'] ) ? (array) $report['duplicates'] : array();

    $out = array();

    foreach ( $groups as $group ) {

        $ids = array();

        foreach ( (array) $group as $item ) {
            $ids[] = is_array( $item ) && isset( $item['id'] ) ? (int) $item['id'] : (int) $item;
        }

        $ids = array_values( array_filter( array_unique( $ids ) ) );

        if ( count( $ids ) < 2 ) {
            continue;
        }

        $keep = 0;

        // Referenced beats unreferenced.
        foreach ( $ids as $id ) {
            if ( '1' !== (string) get_post_meta( $id, VERGEML_META_UNUSED, true ) ) {
                $keep = $id;
                break;
            }
        }

        // Nothing referenced: the oldest, which is the lowest id.
        if ( ! $keep ) {
            $sorted = $ids;
            sort( $sorted );
            $keep = (int) $sorted[0];
        }

        $aside = array();

        foreach ( $ids as $id ) {
            if ( $id !== $keep && ! vergeml_quarantine_has( $id ) ) {
                $aside[] = $id;
            }
        }

        if ( ! $aside ) {
            continue;
        }

        $out[] = array(
            'keep'  => (int) $keep,
            'aside' => $aside,
            'why'   => '1' !== (string) get_post_meta( $keep, VERGEML_META_UNUSED, true )
                ? __( 'kept the copy something points at', 'vergelabs-media-library' )
                : __( 'nothing points at any of them, so kept the oldest', 'vergelabs-media-library' ),
        );
    }

    return $out;
}


/**
 *  Carry out a merge plan, which means setting copies aside and nothing else.
 */

function vergeml_merge_run( $plan ) {

    $done = 0;

    foreach ( (array) $plan as $group ) {

        $keep = isset( $group['keep'] ) ? (int) $group['keep'] : 0;

        foreach ( (array) ( isset( $group['aside'] ) ? $group['aside'] : array() ) as $id ) {

            $id = (int) $id;

            if ( ! $id || $id === $keep ) {
                continue;
            }

            /*
             *  Already set aside: nothing to do, and nothing to count.
             *  vergeml_quarantine_add() answers true for a file it has
             *  already marked -- correct for it, wrong here, because running
             *  the same plan twice would then report the same files set aside
             *  twice and a screen would say it had done work it had not.
             */
            if ( vergeml_quarantine_has( $id ) ) {
                continue;
            }

            $result = vergeml_quarantine_add(
                $id,
                sprintf(
                    /* translators: %d: the id of the copy that was kept. */
                    __( 'a duplicate of #%d, which was kept', 'vergelabs-media-library' ),
                    $keep
                )
            );

            if ( ! is_wp_error( $result ) ) {
                $done++;
            }
        }
    }

    return $done;
}


/* --------------------------------------------------------------- alt text */

/**
 *  vergeml_alt_fill_step
 *
 *  Fill in missing alt text from descriptions the library already has.
 *
 *  Three conditions, all of them required: the image has no alt at all, the
 *  index holds one for it, and nobody has hand-edited that field. The third
 *  is the one that matters -- a person's own words are never replaced by a
 *  model's, and ai-index.php already knows which is which.
 *
 *  Chunked, and every value written is stamped so the whole lot can be
 *  reversed by somebody who does not like the result.
 */

function vergeml_alt_fill_step( $limit = 25 ) {

    $query = new WP_Query( array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => (int) $limit,
        'fields'         => 'ids',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- "missing alt" is a meta absence; core keys alt on meta.
        'meta_query'     => array(
            'relation' => 'OR',
            array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ),
            array( 'key' => '_wp_attachment_image_alt', 'value' => '' ),
        ),
    ) );

    $filled  = 0;
    $skipped = 0;

    foreach ( $query->posts as $id ) {

        $row = vergeml_index_get( (int) $id );

        if ( ! $row ) {
            $skipped++;
            continue;
        }

        // Never over somebody's own writing, even when it is currently empty:
        // a person who cleared a field meant to clear it.
        $locked = isset( $row['locked'] ) ? (array) $row['locked'] : array();

        if ( in_array( 'alt', $locked, true ) ) {
            $skipped++;
            continue;
        }

        $alt = '' !== (string) $row['alt'] ? (string) $row['alt'] : (string) $row['caption'];

        if ( '' === trim( $alt ) ) {
            $skipped++;
            continue;
        }

        /*
         *  Written behind ai-index.php's own guard.
         *
         *  That file watches `_wp_attachment_image_alt` and locks the field
         *  the moment it changes, so that a model never writes over somebody's
         *  words. Without the guard this feature tripped that watcher with its
         *  own write: every alt it filled was immediately marked as
         *  hand-written, which made undo refuse to touch it and made a second
         *  run skip it. The protection working against the only writer it was
         *  meant to allow.
         */
        vergeml_index_writing( true );

        update_post_meta( (int) $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );

        vergeml_index_writing( false );

        update_post_meta( (int) $id, VERGEML_ALT_FILLED, 1 );

        $filled++;
    }

    return array(
        'filled'    => $filled,
        'skipped'   => $skipped,
        'remaining' => max( 0, (int) $query->found_posts - count( $query->posts ) ),
    );
}


/**
 *  Undo the lot. Only the values this wrote -- the stamp is the record, and
 *  anything a person has edited since carries a lock that keeps it.
 */

function vergeml_alt_undo() {

    global $wpdb;

    /*
     *  Asked of postmeta directly rather than through WP_Query.
     *
     *  The stamp is our own key and the question is "which rows carry it",
     *  which is one indexed read. Going through WP_Query put a meta_query,
     *  a post_type join and a status filter between this and the answer, and
     *  returned nothing -- so undo reported success having undone nothing,
     *  which is the worst way for an undo to fail.
     */
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
        VERGEML_ALT_FILLED
    ) );
    // phpcs:enable

    $undone = 0;

    foreach ( (array) $ids as $id ) {

        $row    = vergeml_index_get( (int) $id );
        $locked = ( $row && isset( $row['locked'] ) ) ? (array) $row['locked'] : array();

        // Edited since: that is the person's now, and it stays.
        if ( in_array( 'alt', $locked, true ) ) {
            delete_post_meta( (int) $id, VERGEML_ALT_FILLED );
            continue;
        }

        // Same guard on the way back out, so undoing does not itself count
        // as somebody editing the field.
        vergeml_index_writing( true );

        delete_post_meta( (int) $id, '_wp_attachment_image_alt' );

        vergeml_index_writing( false );

        delete_post_meta( (int) $id, VERGEML_ALT_FILLED );

        $undone++;
    }

    return $undone;
}


/* -------------------------------------------------------------- similarity */

/**
 *  vergeml_similar
 *
 *  The files nearest this one, by the vectors the library already holds.
 *
 *  Bounded on purpose. This answers "more like this one", which costs one
 *  pass over the described files and is asked when somebody clicks. A
 *  similarity *view* -- every file scored against every other -- is the same
 *  arithmetic done n times while a page renders, and that is the N+1 the
 *  query budgets exist to prevent. It is still not built for that reason.
 *
 *  No model call, no credit, no service: the vectors are already here.
 */

function vergeml_similar( $attachment_id, $limit = 12 ) {

    global $wpdb;

    $vector = vergeml_autofile_vector( (int) $attachment_id );

    if ( ! $vector ) {
        return array();
    }

    $table = vergeml_index_table();

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT x.attachment_id
           FROM {$table} x
           JOIN {$wpdb->posts} p ON p.ID = x.attachment_id
          WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
            AND x.attachment_id <> %d",
        (int) $attachment_id
    ) );
    // phpcs:enable

    $scored = array();

    foreach ( (array) $ids as $id ) {

        $other = vergeml_autofile_vector( (int) $id );

        if ( ! $other ) {
            continue;
        }

        $scored[] = array(
            'id'       => (int) $id,
            'distance' => vergeml_organize_distance( $vector, $other ),
        );
    }

    usort( $scored, 'vergeml_similar_by_distance' );

    $out = array();

    foreach ( array_slice( $scored, 0, (int) $limit ) as $hit ) {
        $out[] = array(
            'id'    => $hit['id'],
            'title' => get_the_title( $hit['id'] ),
            'thumb' => wp_get_attachment_image_url( $hit['id'], 'thumbnail' ),
        );
    }

    return $out;
}


function vergeml_similar_by_distance( $a, $b ) {

    if ( $a['distance'] === $b['distance'] ) {
        return 0;
    }

    return ( $a['distance'] < $b['distance'] ) ? -1 : 1;
}


/* --------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_utilities_routes' );

function vergeml_utilities_routes() {

    $can = function () {
        return current_user_can( 'manage_categories' );
    };

    register_rest_route( VERGEML_REST_NS, '/merge-plan', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_rest_merge_plan',
    ) );

    register_rest_route( VERGEML_REST_NS, '/merge-run', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_rest_merge_run',
        'args'                => array( 'plan' => array( 'required' => true ) ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/alt-fill-step', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_rest_alt_fill',
        'args'                => array( 'limit' => array( 'type' => 'integer', 'default' => 25 ) ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/alt-undo', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_rest_alt_undo',
    ) );

    register_rest_route( VERGEML_REST_NS, '/similar', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_rest_similar',
        'args'                => array(
            'id'    => array( 'type' => 'integer', 'required' => true ),
            'limit' => array( 'type' => 'integer', 'default' => 12 ),
        ),
    ) );
}


function vergeml_rest_merge_plan( WP_REST_Request $request ) {

    return rest_ensure_response( array( 'groups' => vergeml_merge_plan() ) );
}


function vergeml_rest_merge_run( WP_REST_Request $request ) {

    $plan = $request->get_param( 'plan' );

    if ( ! is_array( $plan ) ) {
        return new WP_Error( 'vergeml_merge_bad_plan', __( 'That is not a plan this can carry out.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    return rest_ensure_response( array( 'set_aside' => vergeml_merge_run( $plan ) ) );
}


function vergeml_rest_alt_fill( WP_REST_Request $request ) {

    $limit = max( 1, min( 100, (int) $request->get_param( 'limit' ) ) );

    return rest_ensure_response( vergeml_alt_fill_step( $limit ) );
}


function vergeml_rest_alt_undo( WP_REST_Request $request ) {

    return rest_ensure_response( array( 'undone' => vergeml_alt_undo() ) );
}


function vergeml_rest_similar( WP_REST_Request $request ) {

    $limit = max( 1, min( 50, (int) $request->get_param( 'limit' ) ) );

    return rest_ensure_response( array(
        'files' => vergeml_similar( (int) $request->get_param( 'id' ), $limit ),
    ) );
}
