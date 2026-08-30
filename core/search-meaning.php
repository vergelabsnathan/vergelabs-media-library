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
const VERGEML_MEANING_SCAN = 5000;

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

    $slot = 'vergeml_q_' . md5( strtolower( $text ) );
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

    $vector = vergeml_organize_project(
        array_map( 'floatval', $data['embedding'] ),
        VERGEML_ORGANIZE_DIMS
    );

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

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT attachment_id, embedding
               FROM {$wpdb->vergeml_ai_index}
              WHERE error = '' AND embedding IS NOT NULL
           ORDER BY described_at DESC
              LIMIT %d",
            VERGEML_MEANING_SCAN
        ),
        ARRAY_A
    );
    // phpcs:enable

    if ( ! $rows ) {
        return array();
    }

    $scored = array();

    foreach ( $rows as $row ) {

        $vector = vergeml_organize_project(
            vergeml_index_vector_out( $row['embedding'] ),
            VERGEML_ORGANIZE_DIMS
        );

        $score = vergeml_meaning_similarity( $query, $vector );

        if ( $score < VERGEML_MEANING_FLOOR ) {
            continue;
        }

        $scored[ (int) $row['attachment_id'] ] = $score;
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

    return rest_ensure_response( array( 'available' => true, 'ids' => $ids ) );
}


/* --------------------------------------------------------------- the screen */

/**
 *  The offer, under a thin set of results.
 *
 *  Only on a search, only in the admin, and only when the keyword pass found
 *  little -- which is the moment a synonym is the likely explanation. Offering
 *  it beside forty good results would be noise.
 */

add_action( 'admin_notices', 'vergeml_meaning_offer' );

function vergeml_meaning_offer() {

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    if ( ! $screen || 'upload' !== $screen->id ) {
        return;
    }

    $term = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the search WordPress is already running.

    if ( '' === $term ) {
        return;
    }

    // Already looking at a meaning search; nothing more to offer.
    if ( isset( $_GET['vgml_meaning'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    global $wp_query;

    $found = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : 0;

    if ( $found > 5 ) {
        return;
    }

    $url = add_query_arg( array( 's' => $term, 'vgml_meaning' => 1 ), admin_url( 'upload.php' ) );

    printf(
        '<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
        esc_html(
            0 === $found
                ? sprintf(
                    /* translators: %s: what was searched for. */
                    __( 'Nothing matched the word “%s”.', 'vergelabs-media-library' ),
                    $term
                )
                : sprintf(
                    /* translators: 1: number of results, 2: what was searched for. */
                    _n( '%1$s file has the word “%2$s” in it.', '%1$s files have the word “%2$s” in them.', $found, 'vergelabs-media-library' ),
                    number_format_i18n( $found ),
                    $term
                )
        ),
        esc_url( $url ),
        esc_html__( 'Search by meaning instead — finds pictures of it, whatever the words say.', 'vergelabs-media-library' )
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
