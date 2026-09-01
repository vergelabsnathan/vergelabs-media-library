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
        'site_profile'  => '',
        'auto_alt'      => 1,
        'enrich_search' => 1,
        'page_context'  => 1,
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
/**
 *  vergeml_ai_context
 *
 *  What this site already knows about one file.
 *
 *  The service has taken this since it was built -- userPrompt() injects
 *  filename, title, caption and the page it is used on -- and the free plugin
 *  sent only the filename, so that paragraph collapsed to "Describe this
 *  image." on every call it made. Pro has been sending the full set all along.
 *
 *  It is context, not instruction: the prompt says to use it for wording and
 *  subject and not to repeat it back if the picture does not show it.
 */
/**
 *  A first draft of the profile, from what WordPress already knows.
 *
 *  Shown as a placeholder rather than saved: a value nobody typed should not
 *  quietly start shaping their descriptions, but a blank box with no example
 *  is a field nobody fills in.
 */
function vergeml_ai_profile_hint() {

    $name    = trim( (string) get_bloginfo( 'name' ) );
    $tagline = trim( (string) get_bloginfo( 'description' ) );

    $shop = class_exists( 'WooCommerce' )
        ? __( 'An online shop.', 'vergelabs-media-library' )
        : '';

    $bits = array_filter( array( $shop, $name, $tagline ) );

    if ( empty( $bits ) ) {
        return __( 'For example: an online shop selling skateboards — decks, trucks, wheels and apparel. Brands stocked: Powell-Peralta, Element, Baker.', 'vergelabs-media-library' );
    }

    return implode( ' ', $bits ) . ' ' . __( '— and the words your trade uses.', 'vergelabs-media-library' );
}


function vergeml_ai_context( $attachment_id ) {

    $post = get_post( $attachment_id );

    $context = array(
        'filename' => wp_basename( (string) get_attached_file( $attachment_id ) ),
    );

    if ( $post ) {

        if ( '' !== (string) $post->post_title ) {
            $context['title'] = (string) $post->post_title;
        }

        if ( '' !== (string) $post->post_excerpt ) {
            $context['caption'] = (string) $post->post_excerpt;
        }

        if ( $post->post_parent ) {

            $parent = get_post( $post->post_parent );

            if ( $parent ) {

                $context['post_title'] = (string) $parent->post_title;

                /*
                 *  A product knows more about itself than its title does. On a
                 *  shop this is the difference between "a wheeled board" and
                 *  the deck it actually is -- and the prompt forbids guessing a
                 *  brand, so if we do not say it, nothing will.
                 *
                 *  Guarded on the class rather than a function: no dependency
                 *  is added and nothing here runs on a site without it.
                 */
                if ( class_exists( 'WooCommerce' ) && 'product' === $parent->post_type ) {

                    $terms = wp_get_object_terms( $parent->ID, 'product_cat', array( 'fields' => 'names' ) );

                    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                        $context['product_categories'] = implode( ', ', array_slice( $terms, 0, 6 ) );
                    }
                }
            }
        }
    }

    /*
     *  What the page is trying to be, if the site owner wants that used.
     *
     *  Advisory, and theirs to switch off: the page's title, its focus
     *  keyphrase and its meta description travel as context, and the prompt
     *  holds them to wording and subject. An image with no parent post can
     *  still have a page: the one the "Used in" scan found it on.
     */
    $settings = vergeml_ai_settings();

    if ( ! empty( $settings['page_context'] ) && function_exists( 'vergeml_seo_page_for' ) ) {

        $page_id = vergeml_seo_page_for( $attachment_id );

        if ( $page_id ) {

            if ( empty( $context['post_title'] ) ) {
                $context['post_title'] = (string) get_the_title( $page_id );
            }

            foreach ( vergeml_seo_page_context( $page_id ) as $key => $value ) {
                $context[ 'page_' . $key ] = $value;
            }
        }
    }

    return $context;
}


/**
 *  The request for one description, built but not sent.
 *
 *  Separate from sending it because describing a backlog sends eight at a
 *  time, and eight requests have to exist before any of them goes out. One
 *  place builds the body, so the parallel path and the single path can never
 *  drift into asking the service two different questions.
 */

function vergeml_ai_describe_request( $attachment_id ) {

    $settings = vergeml_ai_settings();

    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        return new WP_Error( 'vergeml_ai_not_image', __( 'Only images are described for now.', 'vergelabs-media-library' ) );
    }

    $license = vergeml_ai_unseal( $settings['license_key'] );

    if ( '' === $license ) {
        return new WP_Error( 'vergeml_ai_no_license', __( 'No licence key configured.', 'vergelabs-media-library' ) );
    }

    $file = vergeml_ai_image_payload( $attachment_id );

    if ( is_wp_error( $file ) ) {
        return $file;
    }

    return array(
        'url'     => vergeml_ai_service_url() . '/describe',
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( array(
            'license_key' => $license,
            'site'        => home_url(),
            'filename'    => wp_basename( get_attached_file( $attachment_id ) ),
            'mime'        => get_post_mime_type( $attachment_id ),
            'image'       => $file,
            // Everything this site already knows, so the model does not
            // have to guess what it is not allowed to guess.
            'context'     => vergeml_ai_context( $attachment_id ),
            'profile'     => (string) $settings['site_profile'],
        ) ),
    );
}


function vergeml_ai_describe( $attachment_id ) {

    $settings = vergeml_ai_settings();

    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        return new WP_Error( 'vergeml_ai_not_image', __( 'Only images are described for now.', 'vergelabs-media-library' ) );
    }

    if ( ! empty( $settings['mock'] ) || defined( 'VERGEML_AI_MOCK' ) ) {
        return vergeml_ai_mock_describe( $attachment_id );
    }

    $request = vergeml_ai_describe_request( $attachment_id );

    if ( is_wp_error( $request ) ) {
        return $request;
    }

    $response = wp_remote_post(
        $request['url'],
        array(
            'timeout'   => 60,
            'headers'   => $request['headers'],
            'sslverify' => true,
            'body'      => $request['body'],
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    return vergeml_ai_describe_result(
        wp_remote_retrieve_response_code( $response ),
        wp_remote_retrieve_body( $response )
    );
}


/**
 *  One answer from the service, turned into what the index stores.
 *
 *  Every field is taken deliberately and nothing is passed through: the
 *  columns exist so that `kind = document` means the same thing on every row,
 *  and one unexpected string in the set makes every filter over it a guess.
 */

function vergeml_ai_describe_result( $code, $body ) {

    $code = (int) $code;
    $data = json_decode( (string) $body, true );

    if ( 402 === $code ) {
        return new WP_Error( 'vergeml_ai_out_of_credits', __( 'This licence is out of credits.', 'vergelabs-media-library' ) );
    }

    if ( 401 === $code || 403 === $code ) {
        return new WP_Error( 'vergeml_ai_bad_license', __( 'The licence key was not accepted.', 'vergelabs-media-library' ) );
    }

    // The service saw this exact picture from this site, under this prompt,
    // minutes ago, and declined to charge for it again. Not an error with the
    // file; a loop somewhere sending it twice.
    if ( 409 === $code ) {
        return new WP_Error( 'vergeml_ai_duplicate', __( 'This picture was described a moment ago and was not sent again.', 'vergelabs-media-library' ) );
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
 *  How many descriptions are in flight at once.
 *
 *  The loop that fills a backlog was strictly sequential: one request, wait
 *  for the model, next. Almost all of that time is waiting, so five hundred
 *  pictures took half an hour of a browser tab doing nothing. Eight at a time
 *  turns that into about four minutes.
 *
 *  Eight rather than sixteen because the ceiling is not this end. It is the
 *  service and the model provider behind it, and a number chosen here is
 *  multiplied by every site running a backlog at the same time. Filterable so
 *  a big single-tenant install can raise it, capped so nobody can set 200.
 */
const VERGEML_AI_PARALLEL = 8;

function vergeml_ai_parallel() {

    $n = (int) apply_filters( 'vergeml_ai_parallel', VERGEML_AI_PARALLEL );

    return max( 1, min( 16, $n ) );
}


/**
 *  Describe several, in flight together.
 *
 *  Returns attachment id => the same thing vergeml_ai_describe() returns for
 *  it, so the caller's error handling does not change at all.
 *
 *  WpOrg\Requests is WordPress's own bundled HTTP library -- core uses it for
 *  update checks -- and request_multiple() is curl_multi underneath. No queue,
 *  no worker, no second service.
 *
 *  It does bypass the WP_Http layer, which means the pre_http_request filter
 *  and WP_PROXY_HOST do not apply to it. A site behind a proxy is therefore
 *  sent down the sequential path instead: slower, and it works, which is the
 *  right way round.
 */

function vergeml_ai_describe_many( $ids ) {

    $ids = array_values( array_unique( array_map( 'intval', (array) $ids ) ) );
    $out = array();

    if ( ! $ids ) {
        return $out;
    }

    $settings = vergeml_ai_settings();
    $mock     = ! empty( $settings['mock'] ) || defined( 'VERGEML_AI_MOCK' );

    $sequential = $mock
        || defined( 'WP_PROXY_HOST' )
        || ! class_exists( '\WpOrg\Requests\Requests' )
        || 1 === vergeml_ai_parallel();

    if ( $sequential ) {

        foreach ( $ids as $id ) {
            $out[ $id ] = vergeml_ai_describe( $id );
        }

        return $out;
    }

    foreach ( array_chunk( $ids, vergeml_ai_parallel() ) as $group ) {

        $requests = array();

        foreach ( $group as $id ) {

            $request = vergeml_ai_describe_request( $id );

            // A file that cannot even be read never becomes a request, and
            // its error is the one the caller would have got anyway.
            if ( is_wp_error( $request ) ) {
                $out[ $id ] = $request;
                continue;
            }

            $requests[ $id ] = array(
                'url'     => $request['url'],
                'headers' => $request['headers'],
                'data'    => $request['body'],
                'type'    => \WpOrg\Requests\Requests::POST,
            );
        }

        if ( ! $requests ) {
            continue;
        }

        try {
            $answers = \WpOrg\Requests\Requests::request_multiple( $requests, array(
                'timeout'          => 60,
                'connect_timeout'  => 15,
                'verify'           => true,
            ) );
        } catch ( \Exception $e ) {

            // The whole group failed to go out. Every file in it is reported
            // as failed rather than silently skipped -- a file that quietly
            // never gets described is worse than one that says why.
            foreach ( array_keys( $requests ) as $id ) {
                $out[ $id ] = new WP_Error( 'vergeml_ai_transport', $e->getMessage() );
            }

            continue;
        }

        foreach ( $answers as $id => $answer ) {

            if ( $answer instanceof \WpOrg\Requests\Exception || $answer instanceof \Exception ) {
                $out[ (int) $id ] = new WP_Error( 'vergeml_ai_transport', $answer->getMessage() );
                continue;
            }

            $out[ (int) $id ] = vergeml_ai_describe_result( $answer->status_code, $answer->body );
        }
    }

    return $out;
}


/**
 *  Another file that is byte-for-byte the same picture, already described.
 *
 *  Step one of the sort flow says on screen that checking for copies "stops
 *  the same photo being described twice, and paid for twice". Until this
 *  existed, nothing in the describing path had ever looked at the hashes that
 *  scan computes -- the claim was true of the scan and false of the product.
 *
 *  Exact matches only. Near-duplicates within the health report's tolerance
 *  are a crop or a recolour of each other, and a crop can be a different
 *  picture in every way that matters to a caption. Distance zero is the same
 *  image, and copying its description is a fact rather than a judgement.
 */

function vergeml_ai_twin( $attachment_id ) {

    global $wpdb;

    if ( ! defined( 'VERGEML_META_HASH' ) ) {
        return 0; // health is not loaded, so there are no hashes to match on
    }

    $hash = (string) get_post_meta( (int) $attachment_id, VERGEML_META_HASH, true );

    if ( '' === $hash ) {
        return 0;
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table; there is no core API for it.
    $twin = $wpdb->get_var( $wpdb->prepare(
        "SELECT m.post_id
           FROM {$wpdb->postmeta} m
     INNER JOIN {$wpdb->vergeml_ai_index} i
             ON i.attachment_id = m.post_id AND i.error = '' AND i.described_at IS NOT NULL
          WHERE m.meta_key = %s AND m.meta_value = %s AND m.post_id <> %d
       ORDER BY m.post_id ASC
          LIMIT 1",
        VERGEML_META_HASH,
        $hash,
        (int) $attachment_id
    ) );
    // phpcs:enable

    return (int) $twin;
}


/**
 *  Give one file the description its identical twin already has.
 *
 *  Everything the model produced is copied, prompt_hash included, so a file
 *  filled this way goes stale at the same moment as the one it came from
 *  rather than looking fresh for ever.
 */

function vergeml_ai_fill_from_twin( $attachment_id, $twin_id, $apply_alt = false ) {

    $row = vergeml_index_get( $twin_id );

    if ( ! $row || '' !== (string) $row['error'] ) {
        return false;
    }

    $copy = array();

    foreach ( array( 'caption', 'alt', 'tags', 'title', 'kind', 'has_people', 'has_text', 'document_type', 'model', 'model_version', 'prompt_hash', 'embedding' ) as $field ) {
        if ( array_key_exists( $field, $row ) ) {
            $copy[ $field ] = $row[ $field ];
        }
    }

    $copy['error']        = '';
    $copy['described_at'] = current_time( 'mysql', true );

    vergeml_index_writing( true );
    vergeml_index_set( $attachment_id, $copy );

    if ( $apply_alt && '' === (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) && ! empty( $copy['alt'] ) ) {
        update_post_meta( $attachment_id, '_wp_attachment_image_alt', $copy['alt'] );
    }

    vergeml_index_writing( false );

    return true;
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

        /*
         *  The prompt hash alone, not the model.
         *
         *  The service escalates a hard picture to a stronger model and says
         *  so in `model`; judged on model, one escalated answer made every
         *  ordinary one stale, and the run set off to re-describe the whole
         *  library. The service owns the prompt hash and can fold a model
         *  generation into it on the day it wants a library re-run; until
         *  then a different model for one picture is a better answer, not a
         *  stale library. Ten minutes of cooldown so a set that will not
         *  converge is a slow leak somebody sees, not a flood.
         */
        return vergeml_index_stale(
            '',
            '',
            0,
            0,
            $limit,
            isset( $stamp['prompt_hash'] ) ? $stamp['prompt_hash'] : '',
            VERGEML_AI_HOLD_SECONDS
        );
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
 *  vergeml_ai_pending_count
 *
 *  How many, without the list.
 *
 *  Every "remaining" figure on the AI screen, the dashboard and the end of
 *  every step used to be count( vergeml_ai_pending() ) -- fifty thousand ids
 *  read into PHP to print one number, on every step of a run. Measured at
 *  50,000 files on 31-08-2026: half a second and 270MB per call. This asks
 *  the database to count instead.
 */
function vergeml_ai_pending_count( $scope ) {

    global $wpdb;

    if ( 'stale' === $scope ) {
        // Small by construction -- the rows under an older prompt -- and the
        // cooldown filter lives in the id query, so counting it is fine.
        return count( vergeml_ai_pending( 'stale' ) );
    }

    $mime = $wpdb->esc_like( 'image/' ) . '%';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( 'missing-alt' === $scope ) {
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} alt ON alt.post_id = p.ID AND alt.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE %s
               AND ( alt.meta_id IS NULL OR alt.meta_value = '' )",
            $mime
        ) );
    }

    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
          LEFT JOIN {$wpdb->vergeml_ai_index} i ON i.attachment_id = p.ID
         WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE %s
           AND i.attachment_id IS NULL",
        $mime
    ) );
    // phpcs:enable
}

/**
 *  The most files one call will take on.
 *
 *  Was ten, when they went one at a time. Eight in flight makes a group take
 *  about as long as one file used to, so the ceiling that matters now is the
 *  execution time a shared host allows -- three groups of eight is roughly
 *  twelve seconds, comfortably inside the thirty second cap those hosts still
 *  ship, with the slowest plausible group still leaving room.
 */
const VERGEML_AI_STEP_MAX = 24;

/**
 *  vergeml_ai_index_step
 *
 *  Describe the next few files. Returns what happened, plus how much is
 *  left, so the caller can loop until the answer is zero.
 *
 *  Two things happen before the service is asked anything. Files that are
 *  byte-for-byte copies of something already described are filled in from
 *  their twin -- free, instant, and the thing step one of the sort flow
 *  promises on screen. What is left goes out in groups of eight rather than
 *  one at a time.
 */
/**
 *  Ids the loop described in the last ten minutes, whatever the scope.
 *
 *  The one rule that makes every loop above this safe: the automatic runner
 *  does not describe the same file twice in ten minutes. A scope whose
 *  membership does not shrink when its members are described -- alt text
 *  refused because the field is locked, a stamp that flips between two
 *  answers -- would otherwise spend a credit per file per pass until the
 *  balance was gone, and on 31-08-2026 it did.
 */
const VERGEML_AI_HOLD_SECONDS = 10 * MINUTE_IN_SECONDS;

function vergeml_ai_recently_described( $ids = null, $record = false ) {

    $seen = get_transient( 'vergeml_ai_recent' );
    $seen = is_array( $seen ) ? $seen : array();
    $now  = time();

    // Forget anything older than the hold, so the transient stays small.
    foreach ( $seen as $id => $at ) {
        if ( $now - (int) $at > VERGEML_AI_HOLD_SECONDS ) {
            unset( $seen[ $id ] );
        }
    }

    if ( $record && is_array( $ids ) ) {
        foreach ( $ids as $id ) {
            $seen[ (int) $id ] = $now;
        }
        set_transient( 'vergeml_ai_recent', $seen, VERGEML_AI_HOLD_SECONDS );
        return array();
    }

    if ( ! is_array( $ids ) ) {
        return array_keys( $seen );
    }

    return array_values( array_filter( $ids, function ( $id ) use ( $seen ) {
        return isset( $seen[ (int) $id ] );
    } ) );
}


function vergeml_ai_index_step( $scope, $limit, $apply_alt ) {

    $ids    = vergeml_ai_pending( $scope, max( 1, min( VERGEML_AI_STEP_MAX, $limit ) ) );
    $done   = array();
    $errors = array();

    /*
     *  Anything described in the last ten minutes is held back, and if that
     *  is everything the step was offered, the run is told it is finished
     *  rather than handed the same eight files again. See
     *  vergeml_ai_recently_described() for the day this earned its place.
     */
    $held = vergeml_ai_recently_described( $ids );
    $ids  = array_values( array_diff( $ids, $held ) );

    if ( ! $ids && $held ) {
        return array(
            'described' => array(),
            'errors'    => array(),
            'remaining' => 0,
            'held'      => count( $held ),
            'notice'    => __( 'These files were described in the last few minutes and are not being sent again. If they still look out of date, the definition of "out of date" is the problem, not the files — please report it.', 'vergelabs-media-library' ),
        );
    }

    $stamp = vergeml_index_current_stamp();

    /*
     *  The copies first.
     *
     *  A library of product shots has the same packshot in three places more
     *  often than not, and describing it three times is three credits for one
     *  answer. The scan in step one already worked out which those are; until
     *  this, nothing ever read it.
     *
     *  Only from a twin that is itself current. A copy of a demo row, or of a
     *  row under an older prompt, is a copy of something this run exists to
     *  replace -- and copying it back in was one half of the loop.
     */
    $ask = array();

    foreach ( $ids as $id ) {

        $twin     = vergeml_ai_twin( $id );
        $twin_row = $twin ? vergeml_index_get( $twin ) : null;
        $current  = $twin_row
            && 'mock' !== (string) $twin_row['model']
            && ( '' === (string) $stamp['prompt_hash'] || (string) $twin_row['prompt_hash'] === (string) $stamp['prompt_hash'] );

        if ( $twin && $current && vergeml_ai_fill_from_twin( $id, $twin, $apply_alt ) ) {

            $row = vergeml_index_get( $id );

            $done[] = array(
                'id'      => $id,
                'caption' => $row ? $row['caption'] : '',
                // The caller shows this: a file that cost nothing is worth
                // saying so about, and it explains a count moving faster than
                // the credits do.
                'twin'    => $twin,
            );

            continue;
        }

        $ask[] = $id;
    }

    $answers = vergeml_ai_describe_many( $ask );

    foreach ( $ask as $id ) {

        $described = isset( $answers[ $id ] )
            ? $answers[ $id ]
            : new WP_Error( 'vergeml_ai_no_answer', __( 'The service did not answer for this file.', 'vergelabs-media-library' ) );

        if ( is_wp_error( $described ) ) {

            /*
             *  A duplicate is the service's own version of the ten-minute
             *  hold above. It is reported so the screen can say so, and the
             *  file is neither stubbed as broken nor offered again this pass.
             */
            if ( 'vergeml_ai_duplicate' === $described->get_error_code() ) {

                /*
                 *  The service has this exact picture already -- almost always
                 *  because its identical twin was charged a moment ago in the
                 *  same batch. That twin is now current, so copy it, which is
                 *  what would have happened had the twin gone first.
                 */
                $twin = vergeml_ai_twin( $id );
                $row  = $twin ? vergeml_index_get( $twin ) : null;

                if ( $row && 'mock' !== (string) $row['model'] && vergeml_ai_fill_from_twin( $id, $twin, $apply_alt ) ) {
                    $done[] = array( 'id' => $id, 'caption' => $row['caption'], 'twin' => $twin );
                } else {
                    $errors[] = array( 'id' => $id, 'error' => $described->get_error_message(), 'fatal' => false );
                    vergeml_ai_recently_described( array( $id ), true );
                }
                continue;
            }

            $fatal = in_array( $described->get_error_code(), array( 'vergeml_ai_out_of_credits', 'vergeml_ai_bad_license', 'vergeml_ai_no_license' ), true );

            $errors[] = array( 'id' => $id, 'error' => $described->get_error_message(), 'fatal' => $fatal );

            if ( $fatal ) {
                /*
                 *  Out of credits, or a bad key. Every other answer in this
                 *  group failed for the same reason or is about to, so
                 *  nothing further is written and nothing is stubbed -- a
                 *  stub here would mark a perfectly good file as permanently
                 *  broken because the licence lapsed for a minute.
                 */
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

    // Everything this step touched, twins included, is off the table for ten
    // minutes -- the held list above is only as good as what gets written here.
    vergeml_ai_recently_described( array_map( function ( $d ) { return (int) $d['id']; }, $done ), true );

    return array(
        'described' => $done,
        'errors'    => $errors,
        'remaining' => vergeml_ai_pending_count( $scope ),
        'held'      => count( $held ),
    );
}


/** ------------------------------------------------------------------------
 *  Alt text, from a description already paid for.
 */

/**
 *  Images whose alt text is empty but whose description is already stored.
 *
 *  Not the same question as 'missing-alt', which is every image without alt
 *  text whether or not anything has ever looked at it -- that scope describes,
 *  and describing costs. These have been described. Their alt text was written
 *  at the same time as the caption, by the same call, and is sitting in the
 *  index. Putting it on the file is a copy between two rows of a database.
 *
 *  It is its own scope because it is its own price: free.
 */

function vergeml_ai_alt_pending( $limit = 0 ) {

    global $wpdb;

    $cap = $limit > 0 ? (int) $limit : PHP_INT_MAX;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT i.attachment_id
           FROM {$wpdb->vergeml_ai_index} i
      LEFT JOIN {$wpdb->postmeta} alt
             ON alt.post_id = i.attachment_id AND alt.meta_key = '_wp_attachment_image_alt'
          WHERE i.error = '' AND i.alt <> ''
            AND ( alt.meta_id IS NULL OR alt.meta_value = '' )
       ORDER BY i.attachment_id ASC
          LIMIT %d",
        $cap
    ) ) );
    // phpcs:enable
}


/**
 *  Put the stored alt text on the files.
 *
 *  Inside the writing flag, like every other pipeline write: core/ai-index.php
 *  watches this exact meta key and treats a change as a person typing, so
 *  without it every file would be locked against ever being written again --
 *  by the very act of writing it.
 */

function vergeml_ai_apply_alt( $limit = 0 ) {

    $done = 0;

    foreach ( vergeml_ai_alt_pending( $limit ) as $id ) {

        $row = vergeml_index_get( $id );

        if ( ! $row || '' === trim( (string) $row['alt'] ) ) {
            continue;
        }

        vergeml_index_writing( true );
        update_post_meta( $id, '_wp_attachment_image_alt', $row['alt'] );
        vergeml_index_writing( false );

        $done++;
    }

    return $done;
}


add_action( 'rest_api_init', 'vergeml_ai_alt_route' );

function vergeml_ai_alt_route() {

    register_rest_route( VERGEML_REST_NS, '/ai-alt', array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => function () {
                return rest_ensure_response( array( 'remaining' => count( vergeml_ai_alt_pending() ) ) );
            },
            'permission_callback' => function () {
                return current_user_can( 'upload_files' );
            },
        ),
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => function ( WP_REST_Request $request ) {
                $wrote = vergeml_ai_apply_alt( max( 1, min( 200, (int) $request->get_param( 'limit' ) ) ) );
                return rest_ensure_response( array(
                    'wrote'     => $wrote,
                    'remaining' => count( vergeml_ai_alt_pending() ),
                ) );
            },
            // Writes post meta on attachments, so the same bar as editing one.
            'permission_callback' => function () {
                return current_user_can( 'upload_files' );
            },
            'args'                => array(
                'limit' => array( 'type' => 'integer', 'default' => 100 ),
            ),
        ),
    ) );
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
            'limit'     => array( 'type' => 'integer', 'default' => 8 ),
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
            'site_profile'  => array( 'type' => 'string' ),
            'auto_alt'      => array( 'type' => 'integer' ),
            'enrich_search' => array( 'type' => 'integer' ),
            'page_context'  => array( 'type' => 'integer' ),
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
        'unindexed'   => vergeml_ai_pending_count( 'unindexed' ),
        'missing_alt' => vergeml_ai_pending_count( 'missing-alt' ),
        'ready'       => vergeml_ai_ready(),
        'credits'     => isset( $credits['remaining'] ) ? $credits['remaining'] : null,
        'settings'    => array(
            'auto_alt'      => (int) $settings['auto_alt'],
            'enrich_search' => (int) $settings['enrich_search'],
            'mock'          => (int) $settings['mock'],
            'has_license'   => '' !== vergeml_ai_unseal( $settings['license_key'] ),
            'site_profile'  => (string) $settings['site_profile'],
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

    $profile = $request->get_param( 'site_profile' );

    if ( null !== $profile ) {
        // 500 to match MAX_PROFILE on the service, which truncates anything
        // longer -- better to cut it here, where somebody can see it happen.
        $settings['site_profile'] = substr( sanitize_textarea_field( (string) $profile ), 0, 500 );
    }

    foreach ( array( 'auto_alt', 'enrich_search', 'page_context', 'mock' ) as $flag ) {
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

        <?php
        /*
         *  Head, cards, rows -- the grammar in core/admin-shell.php, not this
         *  screen's own. What was here was an h1, a paragraph, and a
         *  form-table of prose: nothing to scan, and the one button somebody
         *  came to press was the last thing on the page inside a <p>.
         *
         *  The counts line keeps its id. The JS on this screen writes into it
         *  and into every field id below, so the markup changed and the
         *  contract did not.
         */
        echo vergeml_pg_head( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'AI', 'vergelabs-media-library' ),
            __( 'Descriptions, alt text and search, written from your images.', 'vergelabs-media-library' ),
            '<span class="vgml-home-counts" id="vgml-ai-counts">' . esc_html__( 'Loading…', 'vergelabs-media-library' ) . '</span>'
        );
        ?>

        <?php if ( $can_configure ) : ?>
        <?php
        $help = function ( $key ) {
            if ( ! function_exists( 'vergeml_help' ) ) {
                return '';
            }
            ob_start();
            vergeml_help( $key );
            return ob_get_clean();
        };

        echo vergeml_pg_card_open( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Licence and credits', 'vergelabs-media-library' ),
            array(
                'note'        => __( 'Images go to the VergeLabs AI service only to be described. Nothing else leaves your site.', 'vergelabs-media-library' ),
                'action_html' => '<a class="button" href="https://vergelabsmedia.com/#pricing" target="_blank" rel="noopener">'
                    . esc_html__( 'Get credits', 'vergelabs-media-library' ) . '</a>',
                'rows'        => true,
            )
        );

        echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Licence key', 'vergelabs-media-library' ),
            __( 'From your account at vergelabsmedia.com.', 'vergelabs-media-library' ),
            '<input type="text" id="vgml-ai-license" class="regular-text" autocomplete="off" spellcheck="false" placeholder="'
                . esc_attr__( 'unchanged', 'vergelabs-media-library' ) . '">' . $help( 'license_key' )
        );

        echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Credits', 'vergelabs-media-library' ),
            __( 'One credit describes one image.', 'vergelabs-media-library' ),
            '<span id="vgml-ai-credits">' . esc_html__( 'Unknown until the first run', 'vergelabs-media-library' )
                . '</span>' . $help( 'credits' )
        );

        echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'What this site is about', 'vergelabs-media-library' ),
            __( 'Your trade\'s words, so descriptions name what you actually sell. Applies to images described from now on.', 'vergelabs-media-library' ),
            '<textarea id="vgml-ai-profile" rows="3" class="large-text" maxlength="500" placeholder="'
                . esc_attr( vergeml_ai_profile_hint() ) . '"></textarea>' . $help( 'site_profile' ),
            true
        );

        echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Search', 'vergelabs-media-library' ),
            __( 'Media search also matches AI captions and tags.', 'vergelabs-media-library' ),
            '<label><input type="checkbox" id="vgml-ai-enrich"> ' . esc_html__( 'On', 'vergelabs-media-library' )
                . '</label>' . $help( 'enrich_search' )
        );

        echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Page context', 'vergelabs-media-library' ),
            __( 'Descriptions know which page an image is on — its title, and with Yoast, Rank Math, SEOPress or AIOSEO its focus keyphrase and description. Advisory: the model still describes only what it sees.', 'vergelabs-media-library' ),
            '<label><input type="checkbox" id="vgml-ai-page-context"> ' . esc_html__( 'On', 'vergelabs-media-library' )
                . '</label>' . $help( 'page_context' )
        );

        /*
         *  Demo mode, labelled without euphemism. The captions it writes are
         *  invented from filenames; a screen that let anyone mistake them for
         *  a model's answer would be lying, and this plugin's whole argument
         *  is that its numbers are counted.
         */
        $mock_help = defined( 'VERGEML_AI_MOCK' )
            ? __( 'Forced on by the VERGEML_AI_MOCK constant in this site\'s configuration — the switch cannot turn it off.', 'vergelabs-media-library' )
            : __( 'Invent captions here, send nothing, spend nothing.', 'vergelabs-media-library' );

        echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Try it free', 'vergelabs-media-library' ),
            $mock_help,
            '<label><input type="checkbox" id="vgml-ai-mock"> ' . esc_html__( 'Demo mode', 'vergelabs-media-library' )
                . '</label>' . $help( 'mock' )
        );

        echo '</div>'; // close the rows body before the actions foot.

        echo vergeml_pg_actions( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            '<button type="button" class="button button-primary" id="vgml-ai-save">'
                . esc_html__( 'Save changes', 'vergelabs-media-library' )
                . '</button><span id="vgml-ai-save-note"></span>'
        );

        echo '</section>';
        ?>
        <?php endif; ?>

        <?php
        /*
         *  One describe section.
         *
         *  There were two -- "Describe the library" and "Describe in the
         *  background" -- offering the same two jobs with different buttons,
         *  and nothing said why you would pick one. Watching it happen or
         *  letting it run on its own is a choice about this run, not a
         *  different feature, so it is a choice inside the section.
         */
        ?>
        <div class="vgml-ai-card">
            <h2><?php esc_html_e( 'Describe your images', 'vergelabs-media-library' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Each image is shown to the model once. The description powers search, alt text, and everything after it.', 'vergelabs-media-library' ); ?></p>

            <p class="vgml-ai-choice">
                <label><input type="radio" name="vgml-ai-where" value="here" checked> <?php esc_html_e( 'Watch it here', 'vergelabs-media-library' ); ?></label>
                <label><input type="radio" name="vgml-ai-where" value="background"> <?php esc_html_e( 'Run in the background — you can close this tab', 'vergelabs-media-library' ); ?></label>
            </p>

            <p>
                <button type="button" class="button button-primary" id="vgml-ai-run" data-scope="unindexed"><?php esc_html_e( 'Describe new images', 'vergelabs-media-library' ); ?></button>
                <button type="button" class="button" id="vgml-ai-alt" data-scope="missing-alt"><?php esc_html_e( 'Fix missing alt text', 'vergelabs-media-library' ); ?></button>
                <button type="button" class="button" id="vgml-ai-bg-stop" hidden><?php esc_html_e( 'Stop', 'vergelabs-media-library' ); ?></button>
            </p>
            <div class="vgml-import-bar" id="vgml-ai-bg-bar" hidden><div class="vgml-import-fill" id="vgml-ai-bg-fill"></div></div>
            <p id="vgml-ai-bg-note"></p>
            <div class="vgml-import-bar" id="vgml-ai-bar" hidden><div class="vgml-import-fill" id="vgml-ai-fill"></div></div>
            <p id="vgml-ai-note"></p>
            <ul id="vgml-ai-log" class="vgml-ai-log"></ul>
        </div>

        <?php
        /*
         *  Where anything built on top of the descriptions puts its own card.
         *  An action rather than more markup here, so a feature that lives in
         *  its own file -- and can therefore be switched off by safe mode --
         *  does not have to be wired into this page to appear on it.
         */
        do_action( 'vergeml_ai_page_cards' );
        ?>

    </div>
    <?php
}
