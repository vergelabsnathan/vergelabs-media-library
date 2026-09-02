<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  Searching by what a picture means, not by what its caption happens to say.
 *
 *  The library search already looks at the words the model wrote -- caption,
 *  tags, title -- which is a real gain over core, since core can only see a
 *  filename. But it is `LIKE '%term%'`, so it is string matching: "seaside"
 *  never finds a beach, "dog" never finds a golden retriever, and a search
 *  either matches a substring or returns nothing.
 *
 *  Every described file already carries a vector of what it means. It has been
 *  stored since the column existed and used only for grouping folders. The one
 *  missing piece was a vector for the phrase somebody typed, which is what
 *  /v1/embed on the service now returns.
 *
 *  Deliberately a second search rather than a replacement:
 *
 *   - Keyword first, always. It is instant, free, needs no network, and when
 *     somebody types the exact word that is in the caption it is also the
 *     right answer. Sending every keystroke to an API to find a file called
 *     "invoice" would be worse in every way.
 *   - This is offered when that returns little or nothing, which is exactly
 *     when a synonym is the likely problem.
 *   - If the service cannot be reached, the screen keeps the results keyword
 *     search already found. Degrading to today is not a failure state.
 */

/** How many files to rank at once. Beyond this the shortlist comes first. */
/*
 *  Budgets, sized for the smallest server this will run on.
 *
 *  A shared host gives thirty seconds and 128MB, and a search is something a
 *  person is waiting on. So: conversion of old rows happens in small slices,
 *  the scan reads 256-byte projections in modest chunks, and everything stops
 *  at the deadline and says it was partial rather than timing the page out.
 */
const VERGEML_MEANING_CHUNK   = 5000;  // projection rows read per query
const VERGEML_MEANING_CONVERT = 250;   // old rows converted per slice
const VERGEML_MEANING_BUDGET  = 2000;  // ms a search may spend, filterable

/** Below this the vectors are not talking about the same thing at all. */
const VERGEML_MEANING_FLOOR = 0.22;


/**
 *  A vector for a phrase, from the service.
 *
 *  Cached for an hour against the phrase itself: the same search runs several
 *  times a session -- somebody pages through, or refines and comes back -- and
 *  a phrase means the same thing every time.
 */

function vergeml_meaning_vector( $text ) {

    $text = trim( (string) $text );

    if ( '' === $text ) {
        return null;
    }

    $slot = 'vergeml_qv2_' . md5( strtolower( $text ) );
    $seen = get_transient( $slot );

    if ( is_array( $seen ) ) {
        return $seen;
    }

    if ( ! function_exists( 'vergeml_ai_settings' ) ) {
        return null;
    }

    $settings = vergeml_ai_settings();
    $licence  = vergeml_ai_unseal( $settings['license_key'] );

    if ( '' === $licence ) {
        return null;
    }

    $response = wp_remote_post(
        vergeml_ai_service_url() . '/embed',
        array(
            // Short: this is in the way of somebody reading a search result.
            'timeout'   => 8,
            'headers'   => array( 'Content-Type' => 'application/json' ),
            'sslverify' => true,
            'body'      => wp_json_encode( array(
                'license_key' => $licence,
                'site'        => home_url(),
                'text'        => $text,
            ) ),
        )
    );

    if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        return null;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! is_array( $data ) || empty( $data['embedding'] ) || ! is_array( $data['embedding'] ) ) {
        return null;
    }

    /*
     *  Kept whole. This used to hand back the 64-dimension projection, which
     *  meant nothing downstream could ever be more accurate than the
     *  projection was -- including the folder manager, which had the full
     *  picture embeddings in hand and threw them away to meet it. Callers
     *  that want the short form now ask for it.
     */
    $vector = array_map( 'floatval', $data['embedding'] );

    set_transient( $slot, $vector, HOUR_IN_SECONDS );

    return $vector;
}


/**
 *  The files that mean something like this phrase, best first.
 *
 *  Cosine similarity on the projected vectors, which is what the folder
 *  clustering already compares -- one definition of "alike" in the plugin, so
 *  a file that lands in a folder and a file that answers a search are alike in
 *  the same sense.
 */

function vergeml_meaning_search( $text, $limit = 60 ) {

    global $wpdb;

    if ( ! function_exists( 'vergeml_organize_project' ) ) {
        return null; // organize.php is the only place the projection lives
    }

    $query = vergeml_meaning_vector( $text );

    if ( null === $query ) {
        return null;
    }

    /*
     *  The whole library, not the newest five thousand of it.
     *
     *  This used to unpack every 768-float embedding at search time, which is
     *  why it was capped at 5,000 rows -- and why, on a 20,000-file library,
     *  three quarters of the pictures silently could not be found. Rows now
     *  carry a 64-dim projection written when they are described; a search
     *  reads 256 bytes a row, in chunks, until it has seen everything or the
     *  budget says stop.
     *
     *  Rows from before the projection column are converted here, a slice at
     *  a time inside the same budget -- and scored while their embedding is
     *  in hand, so even an unconverted file can be found by the search that
     *  converts it. A cron tick picks the remainder up in the background.
     */
    $deadline = microtime( true ) + max( 500, (int) apply_filters( 'vergeml_meaning_budget_ms', VERGEML_MEANING_BUDGET ) ) / 1000;
    $scored   = array();
    $scanned  = 0;

    /*
     *  Two passes, because the two jobs want different things.
     *
     *  Reading a 64-dimension projection per row is what lets a search cross a
     *  twenty-thousand file library inside its budget, and that is worth
     *  keeping. What is not worth keeping is answering the customer with it:
     *  the projection decides who gets looked at, and the whole embedding
     *  decides who wins. Ranking on the short form is why "none of the terms
     *  actually come up with the correct images".
     *
     *  The gate is deliberately lower than the floor the answer is held to, so
     *  a picture whose projection undersells it still reaches the pass that
     *  can tell.
     */
    $gate = VERGEML_MEANING_FLOOR * 0.6;

    $short = vergeml_organize_project( $query, VERGEML_ORGANIZE_DIMS );

    $keep = function ( $id, $vector ) use ( &$scored, $short ) {
        $score = vergeml_meaning_similarity( $short, $vector );
        if ( $score >= VERGEML_MEANING_FLOOR * 0.6 ) {
            $scored[ (int) $id ] = $score;
        }
    };

    // Old rows first: convert a few slices, scoring each on the way through.
    $converted = vergeml_meaning_convert_batch( $deadline, 4, $keep );
    $scanned  += $converted;

    // Then the projections, smallest-id first so a partial pass is a stable
    // prefix rather than a different sample on every reload.
    $after = 0;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    do {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT attachment_id, projection
               FROM {$wpdb->vergeml_ai_index}
              WHERE error = '' AND projection IS NOT NULL AND attachment_id > %d
           ORDER BY attachment_id ASC
              LIMIT %d",
            $after,
            VERGEML_MEANING_CHUNK
        ), ARRAY_A );

        foreach ( (array) $rows as $row ) {
            $after = (int) $row['attachment_id'];
            $keep( $after, vergeml_index_vector_out( $row['projection'] ) );
            $scanned++;
        }

        /*
         *  A library where everything clears the floor must not grow without
         *  bound: keep a generous multiple of anything a caller can ask for,
         *  trimmed rarely so the sort is not paid per row.
         */
        if ( count( $scored ) > 4000 ) {
            arsort( $scored );
            $scored = array_slice( $scored, 0, 1500, true );
        }
    } while ( count( $rows ) === VERGEML_MEANING_CHUNK && microtime( true ) < $deadline );

    $pending = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index}
          WHERE error = '' AND embedding IS NOT NULL AND projection IS NULL"
    );
    // phpcs:enable

    // Unconverted rows left means the background tick has work to do.
    if ( $pending > 0 ) {
        vergeml_meaning_convert_schedule();
    }

    $GLOBALS['vergeml_meaning_meta'] = array(
        'scanned' => $scanned,
        'pending' => $pending,
        'partial' => ( $pending > 0 ) || ( microtime( true ) >= $deadline && count( $rows ) === VERGEML_MEANING_CHUNK ),
    );

    if ( ! $scored ) {
        return array();
    }

    /*
     *  Now score the shortlist properly.
     *
     *  Only the best few hundred projections are re-read in full, so this
     *  costs one bounded query however large the library is -- and every
     *  number the customer sees, and the floor they are held to, comes from
     *  the whole embedding rather than a summary of it.
     */
    /*
     *  How deep the shortlist goes, measured rather than guessed.
     *
     *  On the test library -- 214 described pictures -- the projection's own
     *  top ten holds only 54% of the ten genuinely closest, which is what
     *  search used to answer with and why so little of what people looked for
     *  came back. Its top hundred holds 99.3%. So the projection is a fine
     *  filter and a poor judge, and the depth is what decides whether the
     *  right picture survives to be judged properly.
     *
     *  That measurement was taken where a hundred rows is half the library,
     *  and it does not carry over to a library of twenty thousand unchanged.
     *  So the depth follows the library rather than sitting at a number that
     *  happened to work once: a share of what was scanned, never less than a
     *  few hundred, and capped where the cost of re-reading embeddings starts
     *  to be felt.
     */
    $depth = (int) min( 2000, max( 400, ceil( $scanned * 0.05 ) ) );

    arsort( $scored );
    $shortlist = array_slice( $scored, 0, $depth, true );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $ready = $wpdb->get_results(
        "SELECT attachment_id, embedding
           FROM {$wpdb->vergeml_ai_index}
          WHERE error = '' AND embedding IS NOT NULL
            AND attachment_id IN (" . implode( ',', array_map( 'intval', array_keys( $shortlist ) ) ) . ')',
        ARRAY_A
    );
    // phpcs:enable

    $scored = array();

    foreach ( (array) $ready as $row ) {

        $score = vergeml_meaning_similarity(
            $query,
            vergeml_index_vector_out( $row['embedding'] )
        );

        if ( $score >= VERGEML_MEANING_FLOOR ) {
            $scored[ (int) $row['attachment_id'] ] = $score;
        }
    }

    if ( ! $scored ) {
        return array();
    }

    /*
     *  Highest first, ties broken by id. Not arsort(): PHP's sorts were only
     *  made stable in 8.0 and this plugin runs on 7.4, so two files of equal
     *  score would come back in a different order on a different host -- and
     *  a search result that reshuffles between page loads looks broken.
     */
    $ids = array_keys( $scored );

    usort( $ids, function ( $a, $b ) use ( $scored ) {
        if ( $scored[ $a ] === $scored[ $b ] ) {
            return $a < $b ? 1 : -1;
        }
        return $scored[ $a ] < $scored[ $b ] ? 1 : -1;
    } );

    return array_slice( $ids, 0, max( 1, (int) $limit ) );
}


/**
 *  Convert rows from before the projection column, a bounded slice at a time.
 *
 *  Each slice unpacks at most VERGEML_MEANING_CONVERT embeddings, writes their
 *  projections back, and hands each vector to $keep so the caller can score it
 *  in the same breath. Stops at the deadline whatever remains -- on the
 *  smallest shared server this is a fraction of a second per slice, never a
 *  timeout. Returns how many rows it converted.
 */
function vergeml_meaning_convert_batch( $deadline, $max_slices = 4, $keep = null ) {

    global $wpdb;

    $done = 0;

    for ( $slice = 0; $slice < $max_slices && microtime( true ) < $deadline; $slice++ ) {

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT attachment_id, embedding
               FROM {$wpdb->vergeml_ai_index}
              WHERE error = '' AND embedding IS NOT NULL AND projection IS NULL
           ORDER BY described_at DESC
              LIMIT %d",
            VERGEML_MEANING_CONVERT
        ), ARRAY_A );

        if ( ! $rows ) {
            break;
        }

        foreach ( $rows as $row ) {

            $vector = vergeml_organize_project(
                vergeml_index_vector_out( $row['embedding'] ),
                VERGEML_ORGANIZE_DIMS
            );

            $wpdb->update(
                $wpdb->vergeml_ai_index,
                array( 'projection' => vergeml_index_vector_in( $vector ) ),
                array( 'attachment_id' => (int) $row['attachment_id'] ),
                array( '%s' ),
                array( '%d' )
            );

            if ( is_callable( $keep ) ) {
                $keep( (int) $row['attachment_id'], $vector );
            }

            $done++;

            if ( microtime( true ) >= $deadline ) {
                break 2;
            }
        }
        // phpcs:enable
    }

    return $done;
}


/**
 *  The background half of the conversion: a cron tick that works for a few
 *  seconds and books itself again until nothing is left. Started by the first
 *  search that finds unconverted rows, so a library upgraded today is fully
 *  searchable within minutes without anybody's page waiting on it.
 */
function vergeml_meaning_convert_schedule() {

    if ( ! wp_next_scheduled( 'vergeml_meaning_convert' ) ) {
        wp_schedule_single_event( time() + 15, 'vergeml_meaning_convert' );
    }
}


add_action( 'vergeml_meaning_convert', 'vergeml_meaning_convert_tick' );

function vergeml_meaning_convert_tick() {

    global $wpdb;

    if ( get_transient( 'vergeml_meaning_convert_lock' ) ) {
        return;
    }

    set_transient( 'vergeml_meaning_convert_lock', 1, MINUTE_IN_SECONDS );

    // Five seconds a tick: real progress, and no strain a shared host notices.
    vergeml_meaning_convert_batch( microtime( true ) + 5, 40 );

    delete_transient( 'vergeml_meaning_convert_lock' );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $pending = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index}
          WHERE error = '' AND embedding IS NOT NULL AND projection IS NULL"
    );
    // phpcs:enable

    if ( $pending > 0 ) {
        vergeml_meaning_convert_schedule();
    }
}


/** Cosine similarity of two equal-length vectors, 0 when either is flat. */
function vergeml_meaning_similarity( $a, $b ) {

    $dot = 0.0;
    $la  = 0.0;
    $lb  = 0.0;

    foreach ( $a as $i => $value ) {

        $other = isset( $b[ $i ] ) ? $b[ $i ] : 0.0;

        $dot += $value * $other;
        $la  += $value * $value;
        $lb  += $other * $other;
    }

    if ( $la <= 0.0 || $lb <= 0.0 ) {
        return 0.0;
    }

    return $dot / ( sqrt( $la ) * sqrt( $lb ) );
}


/* ------------------------------------------------------------------ the API */

add_action( 'rest_api_init', 'vergeml_meaning_routes' );

function vergeml_meaning_routes() {

    register_rest_route( VERGEML_REST_NS, '/search-meaning', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'vergeml_meaning_rest',
        'permission_callback' => function () {
            return current_user_can( 'upload_files' );
        },
        'args'                => array(
            's'     => array( 'type' => 'string', 'required' => true ),
            'limit' => array( 'type' => 'integer', 'default' => 60 ),
        ),
    ) );
}


function vergeml_meaning_rest( WP_REST_Request $request ) {

    $ids = vergeml_meaning_search(
        (string) $request->get_param( 's' ),
        max( 1, min( 200, (int) $request->get_param( 'limit' ) ) )
    );

    if ( null === $ids ) {
        /*
         *  Not an error the screen should shout about. The service is down, or
         *  there is no licence, and the keyword results the person is already
         *  looking at are still perfectly good.
         */
        return rest_ensure_response( array( 'available' => false, 'ids' => array() ) );
    }

    $meta = isset( $GLOBALS['vergeml_meaning_meta'] ) ? $GLOBALS['vergeml_meaning_meta'] : array();

    return rest_ensure_response( array(
        'available' => true,
        'ids'       => $ids,
        // How much of the library this answer is based on. 'partial' true
        // means older files are still being indexed in the background and a
        // repeat of the same search in a minute may find more.
        'scanned'   => isset( $meta['scanned'] ) ? (int) $meta['scanned'] : null,
        'pending'   => isset( $meta['pending'] ) ? (int) $meta['pending'] : null,
        'partial'   => ! empty( $meta['partial'] ),
    ) );
}


/* --------------------------------------------------------------- the screen */

/**
 *  The offer, in the row of view links, and nowhere near a notice.
 *
 *  It was an admin notice. Core hoists those to the top of the screen and
 *  pushes everything below them down, so an offer about a search sat above the
 *  search box, shoved the table down, and on a narrow window clipped. A banner
 *  is for something that has happened; this is a link to another way of
 *  looking.
 *
 *  So it goes in the filter bar above the table, beside the file-type and
 *  date dropdowns -- a row that is already about changing what you are
 *  looking at, an inch from the box the words were typed into. It takes no
 *  vertical space of its own and cannot overlap anything.
 *
 *  Not the "All | Images | Unattached" row, which was the first idea: core
 *  does not render that row at all during a search, which is the only time
 *  this has anything to say.
 */

add_action( 'restrict_manage_posts', 'vergeml_meaning_offer', 20, 2 );

function vergeml_meaning_offer( $post_type, $which = '' ) {

    if ( 'attachment' !== $post_type || 'bottom' === $which ) {
        return; // once, in the bar above the table
    }

    $term = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the search WordPress is already running.

    if ( '' === $term ) {
        return;
    }

    // Already looking at one, so there is nowhere for a link to lead.
    if ( isset( $_GET['vgml_meaning'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        printf(
            '<span class="vgml-meaning-on">%s</span>',
            esc_html__( 'Sorted by meaning', 'vergelabs-media-library' )
        );

        return;
    }

    printf(
        '<a class="vgml-meaning-go" href="%s">%s</a>',
        esc_url( add_query_arg( array( 's' => $term, 'vgml_meaning' => 1 ), admin_url( 'upload.php' ) ) ),
        esc_html__( 'Search by meaning', 'vergelabs-media-library' )
    );
}


/**
 *  Running the search by meaning: the ids, in order, and nothing else.
 *
 *  post__in with orderby post__in, because the ranking is ours and WP_Query
 *  has no idea what a cosine is. An empty result has to become a query that
 *  matches nothing rather than one with no constraint at all -- post__in with
 *  an empty array is ignored by WordPress, which would quietly show the whole
 *  library as the answer to a search that found none of it.
 */

add_action( 'pre_get_posts', 'vergeml_meaning_take_over' );

function vergeml_meaning_take_over( $query ) {

    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    if ( ! isset( $_GET['vgml_meaning'] ) || ! isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    if ( ! $screen || 'upload' !== $screen->id ) {
        return;
    }

    $term = trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $ids  = vergeml_meaning_search( $term, 120 );

    if ( null === $ids ) {
        return; // service unreachable: leave the keyword search alone
    }

    $query->set( 's', '' );
    $query->set( 'post__in', $ids ? $ids : array( 0 ) );
    $query->set( 'orderby', 'post__in' );
}
