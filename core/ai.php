<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  The AI layer: a described library.
 *
 *  One pipeline feeds everything. Each image is shown once to a vision model,
 *  which returns a caption, alt text, tags and a human title as strict JSON.
 *  That description is stored per file, and every AI feature reads from it:
 *  missing alt text is filled from it, search matches against it, and later
 *  features (filing suggestions, similarity) will grow out of the same meta.
 *
 *  The provider is any OpenAI-compatible chat endpoint -- OpenRouter, OpenAI,
 *  a local server -- configured with a base URL, key and model. Nothing here
 *  hard-codes a vendor. A mock mode returns deterministic descriptions built
 *  from the filename, so the whole pipeline is testable without a key and
 *  without spending anything.
 *
 *  Indexing is a batch endpoint the browser calls in a loop, exactly like the
 *  importer and the usage scan: each call takes the next few undescribed
 *  files, so it is resumable by construction and survives page reloads,
 *  timeouts and impatience.
 *
 *  @since 3.2
 */


/** ------------------------------------------------------------------------
 *  Settings.
 */

function vergeml_ai_settings() {

    $defaults = array(
        'license_key'   => '',
        'auto_alt'      => 1,
        'enrich_search' => 1,
        'mock'          => 0,
    );

    $saved = get_option( 'vergeml_ai', array() );

    return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
}

/**
 *  The VergeLabs AI service. The plugin never talks to a model provider
 *  directly: it sends images to this service with the site's licence key,
 *  and the service meters credits, chooses the model, and answers with the
 *  description.
 *
 *  Deliberately NOT filterable. The requests carry the licence key, and a
 *  filter would let any other plugin on the site quietly redirect them to a
 *  server that harvests keys. The only override is a wp-config constant --
 *  the person who can edit wp-config already owns the site -- and anything
 *  that is not https is refused outright.
 */
function vergeml_ai_service_url() {

    $url = defined( 'VERGEML_AI_SERVICE' ) ? VERGEML_AI_SERVICE : 'https://ai.vergelabs.nl/v1';

    if ( 0 !== strpos( $url, 'https://' ) && 0 !== strpos( $url, 'http://localhost' ) && 0 !== strpos( $url, 'http://127.0.0.1' ) ) {
        $url = 'https://ai.vergelabs.nl/v1';
    }

    return untrailingslashit( $url );
}

/**
 *  The licence key at rest. Sealed with a key derived from this site's auth
 *  salt, so a copied database or a stray SQL export does not hand out
 *  working licences. If the salts ever change the seal stops opening and the
 *  licence simply reads as unset -- re-entering it is the recovery.
 */
function vergeml_ai_seal( $plain ) {

    if ( '' === $plain ) {
        return '';
    }

    $key = hash( 'sha256', wp_salt( 'auth' ), true );
    $iv  = random_bytes( 12 );
    $tag = '';

    $sealed = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

    if ( false === $sealed ) {
        return '';
    }

    return 'v1:' . base64_encode( $iv . $tag . $sealed );
}

function vergeml_ai_unseal( $stored ) {

    if ( ! is_string( $stored ) || 0 !== strpos( $stored, 'v1:' ) ) {
        return '';
    }

    $blob = base64_decode( substr( $stored, 3 ), true );

    if ( false === $blob || strlen( $blob ) < 29 ) {
        return '';
    }

    $key   = hash( 'sha256', wp_salt( 'auth' ), true );
    $plain = openssl_decrypt( substr( $blob, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $blob, 0, 12 ), substr( $blob, 12, 16 ) );

    return false === $plain ? '' : $plain;
}

function vergeml_ai_ready() {
    $s = vergeml_ai_settings();
    return ! empty( $s['mock'] ) || defined( 'VERGEML_AI_MOCK' ) || '' !== vergeml_ai_unseal( $s['license_key'] );
}


/** ------------------------------------------------------------------------
 *  Describing one file.
 */

/**
 *  vergeml_ai_describe
 *
 *  One attachment in, one description out: array( caption, alt, tags, title )
 *  or WP_Error. The image travels as a data URI built from the largest
 *  intermediate that stays under a size cap, because the model needs to see
 *  the picture, not the original 8MB scan of it.
 */
function vergeml_ai_describe( $attachment_id ) {

    $settings = vergeml_ai_settings();

    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        return new WP_Error( 'vergeml_ai_not_image', __( 'Only images are described for now.', 'vergelabs-media-library' ) );
    }

    if ( ! empty( $settings['mock'] ) || defined( 'VERGEML_AI_MOCK' ) ) {
        return vergeml_ai_mock_describe( $attachment_id );
    }

    $license = vergeml_ai_unseal( $settings['license_key'] );

    if ( '' === $license ) {
        return new WP_Error( 'vergeml_ai_no_license', __( 'No licence key configured.', 'vergelabs-media-library' ) );
    }

    $file = vergeml_ai_image_payload( $attachment_id );

    if ( is_wp_error( $file ) ) {
        return $file;
    }

    $response = wp_remote_post(
        vergeml_ai_service_url() . '/describe',
        array(
            'timeout' => 60,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'sslverify' => true,
            'body'    => wp_json_encode( array(
                'license_key' => $license,
                'site'        => home_url(),
                'filename'    => wp_basename( get_attached_file( $attachment_id ) ),
                'mime'        => get_post_mime_type( $attachment_id ),
                'image'       => $file,
            ) ),
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( 402 === $code ) {
        return new WP_Error( 'vergeml_ai_out_of_credits', __( 'This licence is out of credits.', 'vergelabs-media-library' ) );
    }

    if ( 401 === $code || 403 === $code ) {
        return new WP_Error( 'vergeml_ai_bad_license', __( 'The licence key was not accepted.', 'vergelabs-media-library' ) );
    }

    if ( 200 !== $code || ! is_array( $data ) || empty( $data['caption'] ) ) {
        return new WP_Error(
            'vergeml_ai_service_error',
            /* translators: %d: HTTP status code from the AI service. */
            sprintf( __( 'The AI service answered with HTTP %d.', 'vergelabs-media-library' ), $code )
        );
    }

    // The service reports the balance with every answer; remembered for the
    // screen, so "how many credits are left" never needs its own request.
    if ( isset( $data['credits'] ) && is_array( $data['credits'] ) ) {
        update_option( 'vergeml_ai_credits', array(
            'remaining' => isset( $data['credits']['remaining'] ) ? (int) $data['credits']['remaining'] : null,
            'time'      => time(),
        ), false );
    }

    /*
     *  The stamp is whatever the service says produced this, and nothing
     *  invented when it says nothing. A description is only reproducible if
     *  you know which model and which prompt made it, and a hash the client
     *  made up would answer the question wrongly rather than not at all.
     */
    return array(
        'caption'       => sanitize_text_field( $data['caption'] ),
        'alt'           => sanitize_text_field( isset( $data['alt'] ) ? $data['alt'] : $data['caption'] ),
        'tags'          => array_map( 'sanitize_text_field', array_slice( (array) ( isset( $data['tags'] ) ? $data['tags'] : array() ), 0, 8 ) ),
        'title'         => sanitize_text_field( isset( $data['title'] ) ? $data['title'] : '' ),
        'kind'          => vergeml_ai_enum( isset( $data['kind'] ) ? $data['kind'] : '', vergeml_ai_kinds() ),
        'has_people'    => isset( $data['has_people'] ) ? (bool) $data['has_people'] : null,
        'has_text'      => isset( $data['has_text'] ) ? (bool) $data['has_text'] : null,
        'document_type' => vergeml_ai_enum( isset( $data['document_type'] ) ? $data['document_type'] : '', vergeml_ai_document_types() ),
        'embedding'     => isset( $data['embedding'] ) && is_array( $data['embedding'] )
            ? array_map( 'floatval', $data['embedding'] )
            : null,
        'model'         => isset( $data['model'] ) ? sanitize_text_field( $data['model'] ) : '',
        'model_version' => isset( $data['model_version'] ) ? sanitize_text_field( $data['model_version'] ) : '',
        'prompt_hash'   => isset( $data['prompt_hash'] ) ? sanitize_text_field( $data['prompt_hash'] ) : '',
    );
}

/**
 *  The enums, in one place, and the answer to anything not in them.
 *
 *  A value the service invented is dropped rather than stored: the whole
 *  reason these are columns is that `kind = document` means the same thing on
 *  every row, and one unexpected string in the set makes every filter over it
 *  a guess. A new member ships in docs/ai-service.md first.
 */
function vergeml_ai_kinds() {
    return array( 'photo', 'illustration', 'screenshot', 'document', 'diagram', 'logo', 'other' );
}

function vergeml_ai_document_types() {
    return array( 'invoice', 'receipt', 'contract', 'form', 'slide', 'report', 'other' );
}

function vergeml_ai_enum( $value, $allowed ) {
    $value = sanitize_key( (string) $value );
    return in_array( $value, $allowed, true ) ? $value : '';
}

/**
 *  vergeml_ai_mock_describe
 *
 *  The whole contract, answered from the filename, without a key or a credit.
 *
 *  This is the difference between the plugin waiting for the service and the
 *  two being built at once. Everything `docs/ai-service.md` promises comes
 *  back here in the right shape -- the enums, the stamp, the embedding -- so
 *  the storage, the migrations, the filters and the screens can be finished
 *  and tested now, and connecting the real service becomes a change of
 *  provider rather than a change of design.
 *
 *  Deterministic, and that is the point twice over: the release test for the
 *  attributes is "same file, three runs, same enums", and a mock that rolled
 *  dice could not be used to check it.
 */
function vergeml_ai_mock_describe( $attachment_id ) {

    $file  = get_attached_file( $attachment_id );
    $name  = pathinfo( (string) $file, PATHINFO_FILENAME );
    $words = array_values( array_filter( preg_split( '/[^a-z0-9]+/i', strtolower( $name ) ) ) );

    // One stable number per file, and every answer below derives from it, so
    // the same file always describes the same way on any machine.
    $seed = hexdec( substr( md5( $name ), 0, 6 ) );

    $kinds = array( 'photo', 'illustration', 'screenshot', 'document', 'diagram', 'logo' );
    $kind  = $kinds[ $seed % count( $kinds ) ];

    // The filename usually knows better than the hash does.
    if ( preg_match( '/(screenshot|screen|grab)/i', $name ) ) {
        $kind = 'screenshot';
    } elseif ( preg_match( '/(invoice|receipt|contract|scan|form|report)/i', $name ) ) {
        $kind = 'document';
    } elseif ( preg_match( '/(logo|wordmark|lockup|monogram)/i', $name ) ) {
        $kind = 'logo';
    }

    $document_type = null;

    if ( 'document' === $kind ) {
        $types = array( 'invoice', 'receipt', 'contract', 'form', 'slide', 'report', 'other' );
        foreach ( $types as $type ) {
            if ( false !== strpos( strtolower( $name ), $type ) ) {
                $document_type = $type;
                break;
            }
        }
        $document_type = null === $document_type ? $types[ $seed % count( $types ) ] : $document_type;
    }

    return array(
        'caption'       => 'Mock caption describing ' . implode( ' ', $words ),
        'alt'           => 'Mock alt for ' . implode( ' ', $words ),
        'tags'          => array_slice( $words, 0, 5 ),
        'title'         => ucwords( implode( ' ', $words ) ),
        'kind'          => $kind,
        'has_people'    => (bool) ( ( $seed >> 3 ) & 1 ),
        'has_text'      => in_array( $kind, array( 'screenshot', 'document', 'logo', 'diagram' ), true ),
        'document_type' => $document_type,
        'embedding'     => vergeml_ai_mock_vector( $name ),
        'model'         => 'mock',
        'model_version' => VERGEML_VERSION,
        'prompt_hash'   => substr( hash( 'sha256', 'mock:filename:v2' ), 0, 32 ),
    );
}

/**
 *  A vector that behaves like one: unit length, stable per file, and close to
 *  the vectors of files with similar names. Similar names standing in for
 *  similar pictures is a fiction, but it is the fiction that lets clustering
 *  be written and watched before a real embedding exists -- and a real one
 *  drops in without the code around it changing.
 */
function vergeml_ai_mock_vector( $name, $dims = 64 ) {

    $vector = array();
    $sum    = 0.0;

    // Each word nudges the same dimensions every time, so two filenames that
    // share words come out near each other.
    $words = array_filter( preg_split( '/[^a-z0-9]+/i', strtolower( $name ) ) );

    for ( $i = 0; $i < $dims; $i++ ) {
        $value = 0.0;
        foreach ( $words as $word ) {
            $value += ( hexdec( substr( md5( $word . ':' . $i ), 0, 4 ) ) / 65535 ) - 0.5;
        }
        $vector[] = $value;
        $sum     += $value * $value;
    }

    $length = sqrt( $sum );

    if ( $length <= 0 ) {
        return array_fill( 0, $dims, 0.0 );
    }

    foreach ( $vector as $i => $value ) {
        $vector[ $i ] = round( $value / $length, 6 );
    }

    return $vector;
}

/**
 *  The picture, as a data URI the endpoint can see. Prefers the 'large'
 *  intermediate and walks down until something fits under ~1.5MB on disk;
 *  models do not caption better for extra megapixels.
 */
function vergeml_ai_image_payload( $attachment_id ) {

    $candidates = array( 'large', 'medium_large', 'medium', 'thumbnail' );
    $base       = get_attached_file( $attachment_id );
    $dir        = dirname( $base );
    $path       = '';

    foreach ( $candidates as $size ) {
        $meta = image_get_intermediate_size( $attachment_id, $size );
        if ( $meta && ! empty( $meta['file'] ) && file_exists( $dir . '/' . wp_basename( $meta['file'] ) ) ) {
            $path = $dir . '/' . wp_basename( $meta['file'] );
            break;
        }
    }

    if ( ! $path && file_exists( $base ) && filesize( $base ) < 1500000 ) {
        $path = $base;
    }

    if ( ! $path ) {
        return new WP_Error( 'vergeml_ai_no_file', __( 'No usable image file found.', 'vergelabs-media-library' ) );
    }

    $mime = get_post_mime_type( $attachment_id );

    return 'data:' . $mime . ';base64,' . base64_encode( file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
}


/** ------------------------------------------------------------------------
 *  The index: which files still need describing, and describing them.
 */

/**
 *  vergeml_ai_pending
 *
 *  Ids still to do for a scope. 'unindexed' is every image without a stored
 *  description; 'missing-alt' is every image without alt text, described or
 *  not, because the point of that pass is the alt; 'stale' is every image
 *  already described by something the pipeline is no longer running.
 */
function vergeml_ai_pending( $scope, $limit = 0 ) {

    global $wpdb;

    /*
     *  Re-describing. The backlog below is the absence of a row, so a
     *  described file is never revisited however far the model that described
     *  it has moved -- which is how a model change strands an entire library.
     *  This is the scope that reaches those rows.
     */
    if ( 'stale' === $scope ) {

        $stamp = vergeml_index_current_stamp();

        return vergeml_index_stale( $stamp['model'], $stamp['model_version'], $stamp['dims'], 0, $limit );
    }

    // The LIMIT is always a prepared placeholder; "no limit" is simply the
    // largest one MySQL accepts, which keeps the statement a single shape.
    $cap  = $limit > 0 ? (int) $limit : PHP_INT_MAX;
    $mime = $wpdb->esc_like( 'image/' ) . '%';

    if ( 'missing-alt' === $scope ) {

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} alt ON alt.post_id = p.ID AND alt.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE %s
               AND ( alt.meta_id IS NULL OR alt.meta_value = '' )
             ORDER BY p.ID ASC LIMIT %d",
            $mime,
            $cap
        ) ) );
        // phpcs:enable
    }

    // The backlog is the absence of an index row. A described file has one,
    // including the stub written for a file that could not be described --
    // which is what stops a permanently failing file wedging the loop.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
          LEFT JOIN {$wpdb->vergeml_ai_index} i ON i.attachment_id = p.ID
         WHERE p.post_type = %s AND p.post_mime_type LIKE %s
           AND i.attachment_id IS NULL
         ORDER BY p.ID ASC LIMIT %d",
        'attachment',
        $mime,
        $cap
    ) ) );
    // phpcs:enable
}

/**
 *  vergeml_ai_index_step
 *
 *  Describe the next few files. Returns what happened, plus how much is
 *  left, so the caller can loop until the answer is zero.
 */
function vergeml_ai_index_step( $scope, $limit, $apply_alt ) {

    $ids    = vergeml_ai_pending( $scope, max( 1, min( 10, $limit ) ) );
    $done   = array();
    $errors = array();

    foreach ( $ids as $id ) {

        $described = vergeml_ai_describe( $id );

        if ( is_wp_error( $described ) ) {

            $fatal = in_array( $described->get_error_code(), array( 'vergeml_ai_out_of_credits', 'vergeml_ai_bad_license', 'vergeml_ai_no_license' ), true );

            $errors[] = array( 'id' => $id, 'error' => $described->get_error_message(), 'fatal' => $fatal );

            if ( $fatal ) {
                // No stub and no next file: every further call would fail the
                // same way, and burning the batch on it helps nobody.
                break;
            }

            // A stub keeps a permanently failing file from wedging the loop;
            // reindexing later replaces it.
            vergeml_index_set( $id, array(
                'error'        => substr( $described->get_error_code(), 0, 64 ),
                'described_at' => current_time( 'mysql', true ),
            ) );
            continue;
        }

        /*
         *  Inside the writing flag: everything below is the pipeline filling
         *  fields in, and the hooks that protect a user's own words must not
         *  mistake it for somebody typing.
         */
        vergeml_index_writing( true );

        $row = array(
            'caption'       => $described['caption'],
            'alt'           => $described['alt'],
            'title'         => $described['title'],
            'tags'          => $described['tags'],
            'kind'          => isset( $described['kind'] ) ? $described['kind'] : '',
            'document_type' => isset( $described['document_type'] ) ? $described['document_type'] : '',
            'orientation'   => vergeml_index_orientation( $id ),
            'model'         => $described['model'],
            'model_version' => $described['model_version'],
            'prompt_hash'   => $described['prompt_hash'],
            'error'         => '',
            'described_at'  => current_time( 'mysql', true ),
        );

        // Null is "the service did not say", which is not the same as false
        // and must not be stored as it.
        foreach ( array( 'has_people', 'has_text' ) as $flag ) {
            if ( isset( $described[ $flag ] ) && null !== $described[ $flag ] ) {
                $row[ $flag ] = $described[ $flag ] ? 1 : 0;
            }
        }

        if ( ! empty( $described['embedding'] ) ) {
            $row['embedding'] = $described['embedding'];
        }

        vergeml_index_set( $id, $row );

        if ( $apply_alt && '' === (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) {
            update_post_meta( $id, '_wp_attachment_image_alt', $described['alt'] );
        }

        vergeml_index_writing( false );

        $done[] = array( 'id' => $id, 'caption' => $described['caption'] );
    }

    return array(
        'described' => $done,
        'errors'    => $errors,
        'remaining' => count( vergeml_ai_pending( $scope ) ),
    );
}


/** ------------------------------------------------------------------------
 *  Search knows what the pictures show.
 */

/**
 *  The stored descriptions join the existing widened search: a query for
 *  "beach" finds the file whose caption says so, even though nothing in its
 *  title or filename does. Rides the same applies-guard as the rest of the
 *  search module, so front-end queries are never touched.
 */
add_filter( 'posts_join', 'vergeml_ai_search_join', 11, 2 );

function vergeml_ai_search_join( $join, $query ) {

    if ( ! vergeml_ai_search_on( $query ) ) {
        return $join;
    }

    global $wpdb;

    if ( false === strpos( $join, 'vergeml_ai_index' ) ) {
        $join .= " LEFT JOIN {$wpdb->vergeml_ai_index} vergeml_ai_index ON vergeml_ai_index.attachment_id = {$wpdb->posts}.ID ";
    }

    return $join;
}

add_filter( 'posts_search', 'vergeml_ai_search_where', 11, 2 );

function vergeml_ai_search_where( $search, $query ) {

    if ( ! vergeml_ai_search_on( $query ) || '' === trim( $search ) ) {
        return $search;
    }

    global $wpdb;

    $terms = function_exists( 'vergeml_search_terms' )
        ? vergeml_search_terms( $query->get( 's' ) )
        : array( $query->get( 's' ) );

    $extra = array();

    /*
     *  Three columns rather than one serialised blob. The old LIKE ran across
     *  the whole array including its PHP serialisation, so a search for "s"
     *  matched the type markers -- this asks the fields somebody actually
     *  meant.
     */
    foreach ( $terms as $term ) {
        $like    = '%' . $wpdb->esc_like( $term ) . '%';
        $extra[] = $wpdb->prepare(
            '( vergeml_ai_index.caption LIKE %s OR vergeml_ai_index.tags LIKE %s OR vergeml_ai_index.title LIKE %s )',
            $like,
            $like,
            $like
        );
    }

    if ( ! $extra ) {
        return $search;
    }

    // Widen the closing paren of core's search clause with OR caption-match.
    $pos = strrpos( $search, ')' );

    if ( false === $pos ) {
        return $search;
    }

    return substr( $search, 0, $pos ) . ' OR ( ' . implode( ' OR ', $extra ) . ' ) ' . substr( $search, $pos );
}

add_filter( 'posts_distinct', 'vergeml_ai_search_distinct', 11, 2 );

function vergeml_ai_search_distinct( $distinct, $query ) {
    return vergeml_ai_search_on( $query ) ? 'DISTINCT' : $distinct;
}

function vergeml_ai_search_on( $query ) {

    $settings = vergeml_ai_settings();

    if ( empty( $settings['enrich_search'] ) ) {
        return false;
    }

    /*
     *  Deliberately NOT vergeml_search_applies(): that helper also demands
     *  the classic extended-search columns be switched on, and captions
     *  should match whether or not someone ever visited that settings screen.
     *  The safety conditions are restated here instead.
     */
    if ( ! $query instanceof WP_Query ) {
        return false;
    }

    if ( ! is_admin() && ! wp_doing_ajax() ) {
        return false;
    }

    $post_type = $query->get( 'post_type' );

    if ( is_array( $post_type ) ) {
        if ( ! in_array( 'attachment', $post_type, true ) ) {
            return false;
        }
    } elseif ( 'attachment' !== $post_type ) {
        return false;
    }

    $search = $query->get( 's' );

    return is_string( $search ) && '' !== trim( $search );
}


/** ------------------------------------------------------------------------
 *  REST.
 */

add_action( 'rest_api_init', 'vergeml_ai_routes' );

function vergeml_ai_routes() {

    register_rest_route( VERGEML_REST_NS, '/ai-status', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'vergeml_ai_rest_status',
        'permission_callback' => function () {
            return current_user_can( 'manage_categories' );
        },
    ) );

    register_rest_route( VERGEML_REST_NS, '/ai-index', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_ai_rest_index',
        // Describing files writes their meta: curation, same bar as the scan.
        'permission_callback' => function () {
            return current_user_can( 'manage_categories' );
        },
        'args'                => array(
            'scope'     => array( 'type' => 'string', 'default' => 'unindexed' ),
            'limit'     => array( 'type' => 'integer', 'default' => 3 ),
            'apply_alt' => array( 'type' => 'boolean', 'default' => false ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/ai-settings', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_ai_rest_settings',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
        'args'                => array(
            'license_key'   => array( 'type' => 'string' ),
            'auto_alt'      => array( 'type' => 'integer' ),
            'enrich_search' => array( 'type' => 'integer' ),
            'mock'          => array( 'type' => 'integer' ),
        ),
    ) );
}

function vergeml_ai_rest_status() {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $images  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE %s", $wpdb->esc_like( 'image/' ) . '%' ) );
    $indexed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = ''" );
    // phpcs:enable

    $settings = vergeml_ai_settings();
    $credits  = get_option( 'vergeml_ai_credits', array() );

    return rest_ensure_response( array(
        'images'      => $images,
        'indexed'     => $indexed,
        'unindexed'   => count( vergeml_ai_pending( 'unindexed' ) ),
        'missing_alt' => count( vergeml_ai_pending( 'missing-alt' ) ),
        'ready'       => vergeml_ai_ready(),
        'credits'     => isset( $credits['remaining'] ) ? $credits['remaining'] : null,
        'settings'    => array(
            'auto_alt'      => (int) $settings['auto_alt'],
            'enrich_search' => (int) $settings['enrich_search'],
            'mock'          => (int) $settings['mock'],
            'has_license'   => '' !== vergeml_ai_unseal( $settings['license_key'] ),
        ),
    ) );
}

function vergeml_ai_rest_index( WP_REST_Request $request ) {

    if ( ! vergeml_ai_ready() ) {
        return new WP_Error( 'vergeml_ai_unconfigured', __( 'Configure an AI endpoint and key first.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $scope = $request->get_param( 'scope' );

    if ( ! in_array( $scope, array( 'unindexed', 'missing-alt', 'stale' ), true ) ) {
        return new WP_Error( 'vergeml_ai_bad_scope', __( 'Unknown scope.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    return rest_ensure_response( vergeml_ai_index_step(
        $scope,
        (int) $request->get_param( 'limit' ),
        (bool) $request->get_param( 'apply_alt' )
    ) );
}

function vergeml_ai_rest_settings( WP_REST_Request $request ) {

    $settings = vergeml_ai_settings();

    // An empty string leaves the stored key alone, so the form can render a
    // masked field without wiping the licence on every save.
    $key = $request->get_param( 'license_key' );
    if ( null !== $key && '' !== $key ) {
        $settings['license_key'] = vergeml_ai_seal( sanitize_text_field( $key ) );
    }

    foreach ( array( 'auto_alt', 'enrich_search', 'mock' ) as $flag ) {
        if ( null !== $request->get_param( $flag ) ) {
            $settings[ $flag ] = (int) (bool) $request->get_param( $flag );
        }
    }

    update_option( 'vergeml_ai', $settings, false );

    return vergeml_ai_rest_status();
}


/** ------------------------------------------------------------------------
 *  The admin screen.
 */

add_action( 'admin_menu', 'vergeml_ai_menu', 12 );

function vergeml_ai_menu() {

    if ( ! defined( 'VERGEML_MENU' ) ) {
        return;
    }

    add_submenu_page(
        VERGEML_MENU,
        __( 'AI', 'vergelabs-media-library' ),
        __( 'AI', 'vergelabs-media-library' ),
        'manage_categories',
        'media-ai',
        'vergeml_ai_page'
    );
}

add_action( 'admin_enqueue_scripts', 'vergeml_ai_assets' );

function vergeml_ai_assets( $hook ) {

    if ( false === strpos( (string) $hook, 'media-ai' ) ) {
        return;
    }

    wp_enqueue_script(
        'vergeml-ai',
        plugins_url( 'js/vergeml-ai.js', VERGEML_FILE ),
        array( 'wp-api-fetch' ),
        vergeml_asset_ver( 'js/vergeml-ai.js' ),
        true
    );

    wp_enqueue_style(
        'vergeml-admin',
        plugins_url( 'css/vergeml-admin.css', VERGEML_FILE ),
        array(),
        vergeml_asset_ver( 'css/vergeml-admin.css' )
    );
}

function vergeml_ai_page() {

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }

    $can_configure = current_user_can( 'manage_options' );

    ?>
    <div class="wrap vgml-home vgml-ai">

        <div class="vgml-home-head">
            <h1><?php esc_html_e( 'AI', 'vergelabs-media-library' ); ?></h1>
            <p class="vgml-home-counts" id="vgml-ai-counts"><?php esc_html_e( 'Loading…', 'vergelabs-media-library' ); ?></p>
        </div>

        <?php if ( $can_configure ) : ?>
        <div class="vgml-ai-card">
            <h2><?php esc_html_e( 'Licence', 'vergelabs-media-library' ); ?></h2>
            <p class="description"><?php esc_html_e( 'AI features run on VergeLabs credits. Your licence key connects this site; images are sent to the VergeLabs AI service only to be described, and nothing else leaves your site.', 'vergelabs-media-library' ); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="vgml-ai-license"><?php esc_html_e( 'Licence key', 'vergelabs-media-library' ); ?></label></th>
                    <td><input type="password" id="vgml-ai-license" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'unchanged', 'vergelabs-media-library' ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Credits', 'vergelabs-media-library' ); ?></th>
                    <td>
                        <span id="vgml-ai-credits"><?php esc_html_e( 'Unknown until the first run', 'vergelabs-media-library' ); ?></span>
                        &nbsp;·&nbsp;
                        <a href="https://vergelabs.nl/ai-credits" target="_blank" rel="noopener"><?php esc_html_e( 'Get credits', 'vergelabs-media-library' ); ?></a>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Behaviour', 'vergelabs-media-library' ); ?></th>
                    <td>
                        <label><input type="checkbox" id="vgml-ai-enrich"> <?php esc_html_e( 'Let media search match AI captions and tags', 'vergelabs-media-library' ); ?></label>
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" class="button button-primary" id="vgml-ai-save"><?php esc_html_e( 'Save', 'vergelabs-media-library' ); ?></button>
                <span id="vgml-ai-save-note"></span>
            </p>
        </div>
        <?php endif; ?>

        <div class="vgml-ai-card">
            <h2><?php esc_html_e( 'Describe the library', 'vergelabs-media-library' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Each image is shown to the model once. The description powers search, alt text, and everything after it.', 'vergelabs-media-library' ); ?></p>
            <p>
                <button type="button" class="button button-primary" id="vgml-ai-run" data-scope="unindexed"><?php esc_html_e( 'Describe new images', 'vergelabs-media-library' ); ?></button>
                <button type="button" class="button" id="vgml-ai-alt" data-scope="missing-alt"><?php esc_html_e( 'Fix missing alt text', 'vergelabs-media-library' ); ?></button>
            </p>
            <div class="vgml-import-bar" id="vgml-ai-bar" hidden><div class="vgml-import-fill" id="vgml-ai-fill"></div></div>
            <p id="vgml-ai-note"></p>
            <ul id="vgml-ai-log" class="vgml-ai-log"></ul>
        </div>

    </div>
    <?php
}
