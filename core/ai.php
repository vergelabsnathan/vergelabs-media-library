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
        'endpoint'      => 'https://openrouter.ai/api/v1',
        'api_key'       => '',
        'model'         => 'google/gemini-2.0-flash-lite-001',
        'auto_alt'      => 1,
        'enrich_search' => 1,
        'mock'          => 0,
    );

    $saved = get_option( 'vergeml_ai', array() );

    return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
}

function vergeml_ai_ready() {
    $s = vergeml_ai_settings();
    return ! empty( $s['mock'] ) || defined( 'VERGEML_AI_MOCK' ) || ( ! empty( $s['api_key'] ) && ! empty( $s['endpoint'] ) );
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

        $name  = pathinfo( get_attached_file( $attachment_id ), PATHINFO_FILENAME );
        $words = array_values( array_filter( preg_split( '/[^a-z0-9]+/i', strtolower( $name ) ) ) );

        return array(
            'caption' => 'Mock caption describing ' . implode( ' ', $words ),
            'alt'     => 'Mock alt for ' . implode( ' ', $words ),
            'tags'    => array_slice( $words, 0, 5 ),
            'title'   => ucwords( implode( ' ', $words ) ),
        );
    }

    if ( empty( $settings['api_key'] ) ) {
        return new WP_Error( 'vergeml_ai_no_key', __( 'No API key configured.', 'vergelabs-media-library' ) );
    }

    $file = vergeml_ai_image_payload( $attachment_id );

    if ( is_wp_error( $file ) ) {
        return $file;
    }

    $prompt = 'Describe this image for a media library. Reply with ONLY a JSON object, no prose: '
        . '{"caption": one factual sentence, "alt": short alt text for accessibility, '
        . '"tags": [3-6 lowercase keywords], "title": a short human title}';

    $body = array(
        'model'    => $settings['model'],
        'messages' => array(
            array(
                'role'    => 'user',
                'content' => array(
                    array( 'type' => 'text', 'text' => $prompt ),
                    array( 'type' => 'image_url', 'image_url' => array( 'url' => $file ) ),
                ),
            ),
        ),
        'max_tokens' => 300,
    );

    $response = wp_remote_post(
        rtrim( $settings['endpoint'], '/' ) . '/chat/completions',
        array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $body ),
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );

    if ( 200 !== $code ) {
        return new WP_Error(
            'vergeml_ai_http_' . $code,
            /* translators: %d: HTTP status code from the AI provider. */
            sprintf( __( 'The AI provider answered with HTTP %d.', 'vergelabs-media-library' ), $code )
        );
    }

    $json    = json_decode( wp_remote_retrieve_body( $response ), true );
    $content = isset( $json['choices'][0]['message']['content'] ) ? $json['choices'][0]['message']['content'] : '';

    // The strict-JSON instruction usually holds; when a model wraps it in a
    // code fence anyway, the object is still in there.
    if ( ! preg_match( '/\{.*\}/s', (string) $content, $m ) ) {
        return new WP_Error( 'vergeml_ai_bad_reply', __( 'The AI reply carried no JSON.', 'vergelabs-media-library' ) );
    }

    $data = json_decode( $m[0], true );

    if ( ! is_array( $data ) || empty( $data['caption'] ) ) {
        return new WP_Error( 'vergeml_ai_bad_reply', __( 'The AI reply could not be parsed.', 'vergelabs-media-library' ) );
    }

    return array(
        'caption' => sanitize_text_field( $data['caption'] ),
        'alt'     => sanitize_text_field( isset( $data['alt'] ) ? $data['alt'] : $data['caption'] ),
        'tags'    => array_map( 'sanitize_text_field', array_slice( (array) ( isset( $data['tags'] ) ? $data['tags'] : array() ), 0, 8 ) ),
        'title'   => sanitize_text_field( isset( $data['title'] ) ? $data['title'] : '' ),
    );
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
 *  not, because the point of that pass is the alt.
 */
function vergeml_ai_pending( $scope, $limit = 0 ) {

    global $wpdb;

    $limit_sql = $limit > 0 ? $wpdb->prepare( 'LIMIT %d', $limit ) : '';

    if ( 'missing-alt' === $scope ) {

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return array_map( 'intval', $wpdb->get_col(
            "SELECT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} alt ON alt.post_id = p.ID AND alt.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'
               AND ( alt.meta_id IS NULL OR alt.meta_value = '' )
             ORDER BY p.ID ASC {$limit_sql}"
        ) );
        // phpcs:enable
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return array_map( 'intval', $wpdb->get_col(
        "SELECT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} ai ON ai.post_id = p.ID AND ai.meta_key = '_vergeml_ai'
         WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'
           AND ai.meta_id IS NULL
         ORDER BY p.ID ASC {$limit_sql}"
    ) );
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
            $errors[] = array( 'id' => $id, 'error' => $described->get_error_message() );
            // A stub keeps a permanently failing file from wedging the loop;
            // reindexing later replaces it.
            update_post_meta( $id, '_vergeml_ai', array( 'error' => $described->get_error_code(), 'time' => time() ) );
            continue;
        }

        $described['time']  = time();
        $described['model'] = vergeml_ai_settings()['model'];

        update_post_meta( $id, '_vergeml_ai', $described );

        if ( $apply_alt && '' === (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) {
            update_post_meta( $id, '_wp_attachment_image_alt', $described['alt'] );
        }

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

    if ( false === strpos( $join, 'vergeml_ai_meta' ) ) {
        $join .= " LEFT JOIN {$wpdb->postmeta} vergeml_ai_meta ON vergeml_ai_meta.post_id = {$wpdb->posts}.ID AND vergeml_ai_meta.meta_key = '_vergeml_ai' ";
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

    foreach ( $terms as $term ) {
        $like    = '%' . $wpdb->esc_like( $term ) . '%';
        $extra[] = $wpdb->prepare( 'vergeml_ai_meta.meta_value LIKE %s', $like );
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
            'endpoint'      => array( 'type' => 'string' ),
            'api_key'       => array( 'type' => 'string' ),
            'model'         => array( 'type' => 'string' ),
            'auto_alt'      => array( 'type' => 'integer' ),
            'enrich_search' => array( 'type' => 'integer' ),
            'mock'          => array( 'type' => 'integer' ),
        ),
    ) );
}

function vergeml_ai_rest_status() {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $images  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" );
    $indexed = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_vergeml_ai'" );
    // phpcs:enable

    $settings = vergeml_ai_settings();

    return rest_ensure_response( array(
        'images'      => $images,
        'indexed'     => $indexed,
        'unindexed'   => count( vergeml_ai_pending( 'unindexed' ) ),
        'missing_alt' => count( vergeml_ai_pending( 'missing-alt' ) ),
        'ready'       => vergeml_ai_ready(),
        'settings'    => array(
            'endpoint'      => $settings['endpoint'],
            'model'         => $settings['model'],
            'auto_alt'      => (int) $settings['auto_alt'],
            'enrich_search' => (int) $settings['enrich_search'],
            'mock'          => (int) $settings['mock'],
            'has_key'       => '' !== $settings['api_key'],
        ),
    ) );
}

function vergeml_ai_rest_index( WP_REST_Request $request ) {

    if ( ! vergeml_ai_ready() ) {
        return new WP_Error( 'vergeml_ai_unconfigured', __( 'Configure an AI endpoint and key first.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $scope = $request->get_param( 'scope' );

    if ( ! in_array( $scope, array( 'unindexed', 'missing-alt' ), true ) ) {
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

    foreach ( array( 'endpoint', 'model' ) as $key ) {
        if ( null !== $request->get_param( $key ) ) {
            $settings[ $key ] = sanitize_text_field( $request->get_param( $key ) );
        }
    }

    // An empty string leaves the stored key alone, so the form can render a
    // masked field without wiping the secret on every save.
    $key = $request->get_param( 'api_key' );
    if ( null !== $key && '' !== $key ) {
        $settings['api_key'] = sanitize_text_field( $key );
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
            <h2><?php esc_html_e( 'Provider', 'vergelabs-media-library' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Any OpenAI-compatible endpoint works: OpenRouter, OpenAI, or a local server. The key is stored on this site and used only to describe your images.', 'vergelabs-media-library' ); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="vgml-ai-endpoint"><?php esc_html_e( 'Endpoint', 'vergelabs-media-library' ); ?></label></th>
                    <td><input type="url" id="vgml-ai-endpoint" class="regular-text" placeholder="https://openrouter.ai/api/v1"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vgml-ai-key"><?php esc_html_e( 'API key', 'vergelabs-media-library' ); ?></label></th>
                    <td><input type="password" id="vgml-ai-key" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'unchanged', 'vergelabs-media-library' ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="vgml-ai-model"><?php esc_html_e( 'Model', 'vergelabs-media-library' ); ?></label></th>
                    <td><input type="text" id="vgml-ai-model" class="regular-text" placeholder="google/gemini-2.0-flash-lite-001"></td>
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
