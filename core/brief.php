<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  The brief: what this site is about, as a conversation.
 *
 *  Every picture goes to the service with the brief -- what the site sells
 *  or publishes, its trade's words, the names it stocks -- and the describer
 *  reads it after its own rules, as background. It was a textarea on a
 *  settings screen and nobody knew what to type into it. Now the owner says
 *  what the site is on the AI screen's "How it describes" tab, the assistant
 *  writes the brief and says what it changed (js/vergeml-talk.js, the same
 *  conversation as Folders, streamed from /v1/brief/stream on a token this
 *  file mints), and the brief stays a draft until it is used.
 *
 *  Using a brief is re-describing the library with it: the describe prompt
 *  carries the brief, so a changed brief is a changed prompt, and the stale
 *  sweep (core/ai-background.php) re-describes everything on the old one.
 *  The control says so, with the number. "Test on 5 pictures" describes five
 *  with the draft and holds the answers in the session, writing nothing:
 *  written, they would stamp the library and start that sweep by themselves.
 *
 *  The opener costs nothing: it is built here from the catalogue -- which
 *  words of the brief the tags carry, what is tagged most -- and the first
 *  model call is the person's first message.
 */

const VERGEML_BRIEF_OPTION   = 'vergeml_brief_session';
const VERGEML_BRIEF_TURN_CAP = 25;
const VERGEML_BRIEF_MAX      = 500;
const VERGEML_BRIEF_TEST_N   = 5;
const VERGEML_BRIEF_CATALOGUE_TTL = HOUR_IN_SECONDS;

/* ------------------------------------------------------------ the session */

function vergeml_brief_fresh() {
    return array(
        'version'         => 1,
        'started_at'      => time(),
        'updated_at'      => time(),
        'turns'           => array(),
        'assistant_turns' => 0,
        'draft'           => null,
        'test'            => null,
        'token'           => null,
    );
}

function vergeml_brief_session() {
    $s = get_option( VERGEML_BRIEF_OPTION );
    return is_array( $s ) && isset( $s['version'] ) ? array_merge( vergeml_brief_fresh(), $s ) : vergeml_brief_fresh();
}

function vergeml_brief_save( $s ) {
    $s['updated_at'] = time();
    $s['turns']      = array_slice( (array) ( isset( $s['turns'] ) ? $s['turns'] : array() ), -60 );
    update_option( VERGEML_BRIEF_OPTION, $s, false );
    return $s;
}

/** The brief every picture is described with today. */
function vergeml_brief_in_use() {
    if ( ! function_exists( 'vergeml_ai_settings' ) ) {
        return '';
    }
    $settings = vergeml_ai_settings();
    return trim( (string) ( isset( $settings['site_profile'] ) ? $settings['site_profile'] : '' ) );
}

/**
 *  A draft from the browser or the service, made safe: lines of text, at
 *  most six, that read as one brief of at most 500 characters when joined
 *  with a space -- which is how the describe request carries it.
 */
function vergeml_brief_clean_lines( $in ) {
    if ( is_string( $in ) ) {
        $in = array( $in );
    }
    if ( ! is_array( $in ) ) {
        return null;
    }
    $lines = array();
    foreach ( $in as $line ) {
        $line = trim( preg_replace( '/\s+/', ' ', sanitize_textarea_field( (string) $line ) ) );
        if ( '' !== $line ) {
            $lines[] = $line;
        }
        if ( count( $lines ) >= 6 ) {
            break;
        }
    }
    if ( ! $lines ) {
        return null;
    }
    while ( strlen( implode( ' ', $lines ) ) > VERGEML_BRIEF_MAX && count( $lines ) > 1 ) {
        array_pop( $lines );
    }
    if ( strlen( implode( ' ', $lines ) ) > VERGEML_BRIEF_MAX ) {
        $lines = array( substr( $lines[0], 0, VERGEML_BRIEF_MAX ) );
    }
    return $lines;
}

function vergeml_brief_join( $lines ) {
    return is_array( $lines ) ? implode( ' ', $lines ) : '';
}

function vergeml_brief_turn_add( &$s, $role, $kind, $text, $extra = array() ) {
    $turn = array_merge( array(
        'role' => 'assistant' === $role ? 'assistant' : 'user',
        'kind' => $kind,
        'text' => (string) $text,
        'at'   => time(),
    ), $extra );
    $s['turns'][] = $turn;
    return $turn;
}

/** What the browser gets: never the token beyond itself, never the held answers' vectors. */
function vergeml_brief_session_out( $s ) {
    return array(
        'turns'           => array_values( (array) $s['turns'] ),
        'draft'           => $s['draft'],
        'assistant_turns' => (int) $s['assistant_turns'],
        'cap'             => VERGEML_BRIEF_TURN_CAP,
        'test'            => vergeml_brief_test_out( $s['test'] ),
        'in_use'          => vergeml_brief_in_use(),
    );
}

/* ---------------------------------------------------------- the catalogue */

/**
 *  Words of a brief worth looking for in the tags: four letters or more, not
 *  the connective tissue, and a plural folded onto its singular -- the brief
 *  says "skateboards", the tag says "skateboard", and that is one word.
 */
function vergeml_brief_words( $text ) {
    $stop = array( 'this', 'that', 'with', 'from', 'about', 'their', 'there', 'these', 'those', 'have', 'here', 'what', 'which', 'when', 'where', 'also', 'into', 'over', 'under', 'only', 'than', 'then', 'them', 'they', 'your', 'ours', 'shop', 'store', 'site', 'website', 'online', 'selling', 'sells', 'sell', 'brands', 'brand', 'stocked', 'stock', 'include', 'includes', 'including', 'terminology', 'terms', 'words', 'word', 'library', 'pictures', 'picture', 'photo', 'photos', 'photography', 'images', 'image', 'company', 'studio', 'design', 'small', 'large', 'other', 'name', 'names', 'sold', 'made' );
    $words = array();
    foreach ( preg_split( '/[^\p{L}\p{N}\-]+/u', mb_strtolower( (string) $text ) ) as $w ) {
        $w = trim( $w, '-' );
        if ( mb_strlen( $w ) < 4 || in_array( $w, $stop, true ) ) {
            continue;
        }
        $stem = vergeml_brief_stem( $w );
        if ( ! isset( $words[ $stem ] ) ) {
            $words[ $stem ] = true;
        }
        if ( count( $words ) >= 24 ) {
            break;
        }
    }
    return array_keys( $words );
}

/** "skateboards" and "skateboard" are one word; "bushings" and "bushing" too. Nothing cleverer than that. */
function vergeml_brief_stem( $w ) {
    if ( mb_strlen( $w ) > 4 && 's' === mb_substr( $w, -1 ) && 's' !== mb_substr( $w, -2, 1 ) ) {
        return mb_substr( $w, 0, -1 );
    }
    return $w;
}

/**
 *  What the tags say about the library: the most used, how many are
 *  distinct, and how many pictures carry each word of the brief. One pass
 *  over the tags column, cached for an hour against the described count and
 *  the brief, because on a large library it is the one expensive read here.
 */
function vergeml_brief_catalogue() {
    global $wpdb;

    $in_use    = vergeml_brief_in_use();
    $described = function_exists( 'vergeml_guide_described_count' ) ? vergeml_guide_described_count() : 0;
    $key       = 'vergeml_brief_catalogue';
    $cached    = get_transient( $key );
    $stamp     = md5( $described . '|' . $in_use );

    if ( is_array( $cached ) && isset( $cached['stamp'] ) && $cached['stamp'] === $stamp ) {
        return $cached['catalogue'];
    }

    $catalogue = array(
        'described'   => $described,
        'kinds'       => array(),
        'top_tags'    => array(),
        'tags'        => 0,
        'brief_words' => array(),
    );

    if ( $described > 0 && isset( $wpdb->vergeml_ai_index ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
        foreach ( (array) $wpdb->get_results( "SELECT kind, COUNT(*) n FROM {$wpdb->vergeml_ai_index} WHERE error = '' GROUP BY kind ORDER BY n DESC", ARRAY_A ) as $row ) {
            $catalogue['kinds'][ '' === (string) $row['kind'] ? 'none' : (string) $row['kind'] ] = (int) $row['n'];
        }
        $freq = array();
        foreach ( (array) $wpdb->get_col( "SELECT tags FROM {$wpdb->vergeml_ai_index} WHERE error = ''" ) as $raw ) {
            foreach ( (array) vergeml_index_tags_out( $raw ) as $tag ) {
                $tag = mb_strtolower( trim( (string) $tag ) );
                if ( '' !== $tag ) {
                    $freq[ $tag ] = isset( $freq[ $tag ] ) ? $freq[ $tag ] + 1 : 1;
                }
            }
        }
        // phpcs:enable
        arsort( $freq );
        $catalogue['tags']     = count( $freq );
        $catalogue['top_tags'] = array_slice( $freq, 0, 25, true );
        foreach ( vergeml_brief_words( $in_use ) as $word ) {
            $n = 0;
            foreach ( $freq as $tag => $count ) {
                if ( false !== mb_strpos( $tag, $word ) ) {
                    $n += $count;
                }
            }
            $catalogue['brief_words'][ $word ] = $n;
        }
        arsort( $catalogue['brief_words'] );
    }

    set_transient( $key, array( 'stamp' => $stamp, 'catalogue' => $catalogue ), VERGEML_BRIEF_CATALOGUE_TTL );

    return $catalogue;
}

function vergeml_brief_catalogue_forget() {
    delete_transient( 'vergeml_brief_catalogue' );
}

/** "landscape 161, nature 114, architecture 73" */
function vergeml_brief_counts_line( $counts, $limit ) {
    $bits = array();
    foreach ( array_slice( (array) $counts, 0, $limit, true ) as $name => $n ) {
        $bits[] = $name . ' ' . number_format_i18n( (int) $n );
    }
    return implode( ', ', $bits );
}

/**
 *  The first thing the assistant says, built here from the catalogue so that
 *  opening the tab costs nothing. Facts, then one question.
 */
function vergeml_brief_opener( $catalogue, $in_use ) {

    $lines   = array();
    $choices = array();

    if ( '' === $in_use ) {
        $lines[] = __( 'No brief yet. Every picture is described from what it shows, with nothing about the site.', 'vergelabs-media-library' );
        if ( ! empty( $catalogue['top_tags'] ) ) {
            /* translators: %s: tags with their counts */
            $lines[] = '- ' . sprintf( __( 'Most tagged: %s', 'vergelabs-media-library' ), vergeml_brief_counts_line( $catalogue['top_tags'], 4 ) );
        }
        $lines[] = __( 'What does this site sell or publish?', 'vergelabs-media-library' );
        return array( 'text' => implode( "\n", $lines ), 'choices' => $choices );
    }

    /* translators: %s: pictures */
    $lines[] = sprintf( _n( '%s picture described with the brief on the right.', '%s pictures described with the brief on the right.', (int) $catalogue['described'], 'vergelabs-media-library' ), number_format_i18n( (int) $catalogue['described'] ) );

    $carried = array_filter( (array) $catalogue['brief_words'], function ( $n ) { return $n > 0; } );
    $missing = array_keys( array_filter( (array) $catalogue['brief_words'], function ( $n ) { return 0 === $n; } ) );

    if ( $carried ) {
        /* translators: %s: words with their counts */
        $lines[] = '- ' . sprintf( __( 'Tags carrying a word from it: %s', 'vergelabs-media-library' ), vergeml_brief_counts_line( $carried, 6 ) );
    } elseif ( ! empty( $catalogue['brief_words'] ) ) {
        $lines[] = '- ' . __( 'No tag carries a word from it', 'vergelabs-media-library' );
    }
    if ( $missing && $carried ) {
        /* translators: %s: words */
        $lines[] = '- ' . sprintf( __( 'No tag carries %s', 'vergelabs-media-library' ), implode( ', ', array_slice( $missing, 0, 8 ) ) );
    }
    if ( ! empty( $catalogue['top_tags'] ) ) {
        /* translators: %s: tags with their counts */
        $lines[] = '- ' . sprintf( __( 'Most tagged: %s', 'vergelabs-media-library' ), vergeml_brief_counts_line( $catalogue['top_tags'], 4 ) );
    }
    $lines[]   = __( 'Does the brief describe this site?', 'vergelabs-media-library' );
    $choices[] = __( 'Yes, keep it', 'vergelabs-media-library' );
    $choices[] = __( 'No', 'vergelabs-media-library' );

    return array( 'text' => implode( "\n", $lines ), 'choices' => $choices );
}

/**
 *  Everything the tab's first paint needs. Writes the opener into an empty
 *  session, so a reload shows the same conversation.
 */
function vergeml_brief_boot() {
    $s         = vergeml_brief_session();
    $catalogue = vergeml_brief_catalogue();
    $in_use    = vergeml_brief_in_use();

    if ( ! ( $s['turns'] ) ) {
        $open = vergeml_brief_opener( $catalogue, $in_use );
        // Free: built here, not by the model, so it is not a turn against the cap.
        vergeml_brief_turn_add( $s, 'assistant', 'say', $open['text'], array( 'choices' => $open['choices'], 'free' => true ) );
        vergeml_brief_save( $s );
    }

    $licensed = function_exists( 'vergeml_ai_unseal' ) && function_exists( 'vergeml_ai_settings' )
        && '' !== vergeml_ai_unseal( vergeml_ai_settings()['license_key'] );

    return array(
        'session'   => vergeml_brief_session_out( $s ),
        'catalogue' => $catalogue,
        'in_use'    => $in_use,
        'described' => (int) $catalogue['described'],
        'licensed'  => (bool) $licensed,
        'cap'       => VERGEML_BRIEF_TURN_CAP,
        'test_n'    => VERGEML_BRIEF_TEST_N,
        'max'       => VERGEML_BRIEF_MAX,
    );
}

/* --------------------------------------------------------------- the token */

/**
 *  A token for the browser, bound to the catalogue the assistant reads:
 *  cached while it lasts and the catalogue is the same, else minted anew
 *  through the same call Folders uses (core/guide.php).
 */
function vergeml_brief_token() {
    if ( ! function_exists( 'vergeml_guide_mint' ) || ! function_exists( 'vergeml_ai_settings' ) || ! function_exists( 'vergeml_ai_unseal' ) ) {
        return new WP_Error( 'no_licence', __( 'Connect a licence on the Licence screen first.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }
    $licence = vergeml_ai_unseal( vergeml_ai_settings()['license_key'] );
    if ( '' === $licence ) {
        return new WP_Error( 'no_licence', __( 'Connect a licence on the Licence screen first.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $s         = vergeml_brief_session();
    $catalogue = vergeml_brief_catalogue();
    $stamp     = md5( wp_json_encode( $catalogue ) );
    $cached    = is_array( $s['token'] ) ? $s['token'] : null;

    if ( ! ( $cached && (string) $cached['stamp'] === $stamp && (int) $cached['expires_at'] - time() > VERGEML_GUIDE_TOKEN_SLACK ) ) {
        $minted = vergeml_guide_mint( $licence, $catalogue );
        if ( is_wp_error( $minted ) ) {
            return $minted;
        }
        $s['token'] = array(
            'token'      => (string) $minted['token'],
            'expires_at' => isset( $minted['expires_at'] ) ? (int) $minted['expires_at'] : time() + HOUR_IN_SECONDS,
            'stamp'      => $stamp,
        );
        vergeml_brief_save( $s );
    }

    return array(
        'token'      => (string) $s['token']['token'],
        'expires_at' => (int) $s['token']['expires_at'],
        'summary'    => $catalogue,
        'in_use'     => vergeml_brief_in_use(),
        'stream'     => vergeml_guide_stream_url(),
    );
}

/* ---------------------------------------------------------------- the test */

/**
 *  Five described pictures the draft would touch: one from each of the
 *  largest folders, then the newest described until there are five. The
 *  same pictures every time the same library is asked, so two tests of two
 *  drafts compare.
 */
function vergeml_brief_test_pick( $n ) {
    global $wpdb;

    $ids = array();
    $tax = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';

    $terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => $n ) );
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
            $id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT i.attachment_id FROM {$wpdb->vergeml_ai_index} i
                   JOIN {$wpdb->term_relationships} tr ON tr.object_id = i.attachment_id
                  WHERE i.error = '' AND i.embedding IS NOT NULL AND tr.term_taxonomy_id = %d
                  ORDER BY i.attachment_id ASC LIMIT 1",
                $term->term_taxonomy_id
            ) );
            if ( $id && ! in_array( $id, $ids, true ) ) {
                $ids[] = $id;
            }
            if ( count( $ids ) >= $n ) {
                break;
            }
        }
    }
    if ( count( $ids ) < $n ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
        foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT attachment_id FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL ORDER BY described_at DESC, attachment_id DESC LIMIT %d", $n * 2 ) ) as $id ) {
            $id = (int) $id;
            if ( ! in_array( $id, $ids, true ) ) {
                $ids[] = $id;
            }
            if ( count( $ids ) >= $n ) {
                break;
            }
        }
    }
    return $ids;
}

/** One picture described with a brief other than the one in use, straight against the service, nothing written. */
function vergeml_brief_describe_with( $attachment_id, $brief ) {
    /*
     *  A suite may stand in for the service here: a test that spends five
     *  credits on every push is not a test that runs on every push.
     */
    $stand_in = apply_filters( 'vergeml_brief_describe', null );
    if ( is_callable( $stand_in ) ) {
        return call_user_func( $stand_in, $attachment_id, $brief );
    }
    $request = vergeml_ai_describe_request( $attachment_id );
    if ( is_wp_error( $request ) ) {
        return $request;
    }
    $body = json_decode( $request['body'], true );
    $body['profile'] = (string) $brief;
    $response = wp_remote_post( $request['url'], array(
        'timeout'   => 60,
        'headers'   => $request['headers'],
        'sslverify' => true,
        'body'      => wp_json_encode( $body ),
    ) );
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    return vergeml_ai_describe_result( wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_body( $response ) );
}

/** What the screen shows of a held answer: no vectors, no model stamps. */
function vergeml_brief_test_out( $test ) {
    if ( ! is_array( $test ) ) {
        return null;
    }
    $rows = array();
    foreach ( (array) $test['rows'] as $row ) {
        $rows[] = array(
            'id'     => (int) $row['id'],
            'title'  => (string) $row['before']['title'],
            'thumb'  => (string) wp_get_attachment_image_url( (int) $row['id'], 'thumbnail' ),
            'before' => array( 'caption' => $row['before']['caption'], 'alt' => $row['before']['alt'], 'title' => $row['before']['title'], 'tags' => $row['before']['tags'] ),
            'after'  => array( 'caption' => $row['after']['caption'], 'alt' => $row['after']['alt'], 'title' => $row['after']['title'], 'tags' => $row['after']['tags'] ),
        );
    }
    return array( 'brief' => (string) $test['brief'], 'rows' => $rows, 'at' => (int) $test['at'], 'credits' => (int) $test['credits'] );
}

/**
 *  Test on N pictures: the draft, sent with each of them, the answers held
 *  in the session beside what the catalogue holds now. Costs N credits;
 *  writes nothing to the catalogue. The assistant's turn says what changed.
 *
 *  @return array|WP_Error the session as the browser sees it
 */
function vergeml_brief_test() {
    $s = vergeml_brief_session();
    if ( ! is_array( $s['draft'] ) || ! $s['draft'] ) {
        return new WP_Error( 'no_draft', __( 'There is no draft brief to test.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }
    if ( ! function_exists( 'vergeml_ai_ready' ) || ! vergeml_ai_ready() ) {
        return new WP_Error( 'no_licence', __( 'Connect a licence on the Licence screen first.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $brief = vergeml_brief_join( $s['draft'] );
    $ids   = vergeml_brief_test_pick( VERGEML_BRIEF_TEST_N );
    if ( ! $ids ) {
        return new WP_Error( 'nothing_described', __( 'No picture is described yet, so there is nothing to test the brief on.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $rows    = array();
    $failed  = 0;
    $changed = array( 'caption' => 0, 'alt' => 0, 'title' => 0, 'tags' => 0 );

    foreach ( $ids as $id ) {
        $before = vergeml_index_get( $id );
        $after  = vergeml_brief_describe_with( $id, $brief );
        if ( ! $before || is_wp_error( $after ) ) {
            $failed++;
            continue;
        }
        foreach ( array_keys( $changed ) as $field ) {
            $was = is_array( $before[ $field ] ) ? implode( ',', $before[ $field ] ) : (string) $before[ $field ];
            $now = is_array( $after[ $field ] ) ? implode( ',', $after[ $field ] ) : (string) $after[ $field ];
            if ( $was !== $now ) {
                $changed[ $field ]++;
            }
        }
        $rows[] = array(
            'id'     => $id,
            'before' => array( 'caption' => $before['caption'], 'alt' => $before['alt'], 'title' => $before['title'], 'tags' => (array) $before['tags'] ),
            'after'  => $after,
        );
    }

    if ( ! $rows ) {
        return new WP_Error( 'test_failed', __( 'The service did not answer for any of the pictures. Nothing was spent on the ones it refused.', 'vergelabs-media-library' ), array( 'status' => 502 ) );
    }

    $n = count( $rows );
    $s['test'] = array( 'brief' => $brief, 'rows' => $rows, 'at' => time(), 'credits' => $n );

    $lines = array(
        /* translators: 1: pictures, 2: credits */
        sprintf( __( '%1$s pictures described again with the draft brief · %2$s credits', 'vergelabs-media-library' ), number_format_i18n( $n ), number_format_i18n( $n ) ),
        /* translators: 1: pictures whose caption changed, 2: alt text, 3: title, 4: pictures tested */
        '- ' . sprintf( __( 'Caption changed on %1$s of %4$s, alt text on %2$s of %4$s, title on %3$s of %4$s', 'vergelabs-media-library' ), number_format_i18n( $changed['caption'] ), number_format_i18n( $changed['alt'] ), number_format_i18n( $changed['title'] ), number_format_i18n( $n ) ),
        /* translators: 1: pictures whose tags changed, 2: pictures tested */
        '- ' . sprintf( __( 'Tags changed on %1$s of %2$s', 'vergelabs-media-library' ), number_format_i18n( $changed['tags'] ), number_format_i18n( $n ) ),
    );
    if ( $failed ) {
        /* translators: %s: pictures */
        $lines[] = '- ' . sprintf( __( '%s could not be described', 'vergelabs-media-library' ), number_format_i18n( $failed ) );
    }
    $lines[] = '- ' . __( 'Nothing is written until the brief is used', 'vergelabs-media-library' );

    vergeml_brief_turn_add( $s, 'assistant', 'test', implode( "\n", $lines ), array( 'free' => true ) );
    vergeml_brief_save( $s );

    return vergeml_brief_session_out( $s );
}

/* ---------------------------------------------------------------- adopting */

/**
 *  Use the draft: the brief is saved as the site profile, the answers the
 *  test held are written (they were paid for), and the library is
 *  re-described on the new brief in the background -- the stale sweep,
 *  started here rather than left to the next run, because the control the
 *  person pressed said it would.
 *
 *  @param array $args 'start' false leaves the run unstarted (a suite).
 *  @return array|WP_Error { session, run }
 */
function vergeml_brief_adopt( $args = array() ) {
    $args = array_merge( array( 'start' => true ), (array) $args );
    $s    = vergeml_brief_session();
    if ( ! is_array( $s['draft'] ) || ! $s['draft'] ) {
        return new WP_Error( 'no_draft', __( 'There is no draft brief to use.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }
    if ( ! function_exists( 'vergeml_ai_ready' ) || ! vergeml_ai_ready() ) {
        return new WP_Error( 'no_licence', __( 'Connect a licence on the Licence screen first.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $brief    = substr( vergeml_brief_join( $s['draft'] ), 0, VERGEML_BRIEF_MAX );
    $settings = vergeml_ai_settings();
    $own      = get_option( 'vergeml_ai', array() );
    $own      = is_array( $own ) ? $own : array();
    $own['site_profile'] = $brief;
    update_option( 'vergeml_ai', $own, false );
    vergeml_brief_catalogue_forget();

    $apply_alt = ! empty( $settings['auto_alt'] );
    $written   = 0;

    // The held answers, if they were for this brief: paid for, and now the newest rows, so the stamp reads the new prompt.
    if ( is_array( $s['test'] ) && (string) $s['test']['brief'] === $brief ) {
        foreach ( (array) $s['test']['rows'] as $row ) {
            if ( is_array( $row['after'] ) && ! empty( $row['after']['caption'] ) ) {
                vergeml_ai_index_store( (int) $row['id'], $row['after'], $apply_alt );
                $written++;
            }
        }
    }

    /*
     *  No held answer: one picture is described now with the new brief (one
     *  credit), so the stamp moves to the new prompt and the rest of the
     *  library counts as stale. Without it there would be nothing to sweep.
     */
    if ( 0 === $written ) {
        $ids = vergeml_brief_test_pick( 1 );
        if ( $ids ) {
            $after = vergeml_brief_describe_with( $ids[0], $brief );
            if ( ! is_wp_error( $after ) ) {
                vergeml_ai_index_store( $ids[0], $after, $apply_alt );
                $written++;
            }
        }
    }

    $pending = function_exists( 'vergeml_ai_pending_count' ) ? (int) vergeml_ai_pending_count( 'stale' ) : 0;
    $run     = null;

    if ( $args['start'] && $pending > 0 && function_exists( 'vergeml_ai_run_start' ) ) {
        $run = vergeml_ai_run_start( 'stale', $apply_alt, 'brief_changed' );
        if ( is_wp_error( $run ) ) {
            $run = null;
        }
    }

    $s['draft'] = null;
    $s['test']  = null;
    $s['token'] = null;
    vergeml_brief_turn_add( $s, 'user', 'adopt', __( 'Used this brief', 'vergelabs-media-library' ) );
    vergeml_brief_turn_add(
        $s,
        'assistant',
        'say',
        $pending > 0
            /* translators: %s: pictures */
            ? sprintf( __( 'Brief in use.', 'vergelabs-media-library' ) . "\n- " . _n( '%s picture is described again in the background', '%s pictures are described again in the background', $pending, 'vergelabs-media-library' ) . "\n- " . __( 'The Describe tab shows the run', 'vergelabs-media-library' ), number_format_i18n( $pending ) )
            : __( 'Brief in use.', 'vergelabs-media-library' ) . "\n- " . __( 'Every picture is on it', 'vergelabs-media-library' ),
        array( 'free' => true )
    );
    vergeml_brief_save( $s );

    return array(
        'session' => vergeml_brief_session_out( $s ),
        'written' => $written,
        'pending' => $pending,
        'run'     => function_exists( 'vergeml_ai_run_payload' ) ? vergeml_ai_run_payload() : $run,
    );
}

/* ----------------------------------------------------------------- routes */

add_action( 'rest_api_init', 'vergeml_brief_routes' );

function vergeml_brief_routes() {
    $may = function () {
        return current_user_can( 'manage_options' );
    };
    register_rest_route( VERGEML_REST_NS, '/brief/session', array(
        array( 'methods' => WP_REST_Server::READABLE, 'callback' => 'vergeml_brief_rest_session', 'permission_callback' => $may ),
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'vergeml_brief_rest_session',
            'permission_callback' => $may,
            'args'                => array(
                'reset' => array( 'type' => 'boolean', 'required' => false ),
                'draft' => array( 'required' => false ),
            ),
        ),
    ) );
    register_rest_route( VERGEML_REST_NS, '/brief/token', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_brief_rest_token',
        'permission_callback' => $may,
    ) );
    register_rest_route( VERGEML_REST_NS, '/brief/turn', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_brief_rest_turn',
        'permission_callback' => $may,
        'args'                => array(
            'said'  => array( 'type' => 'object', 'required' => false ),
            'say'   => array( 'type' => 'object', 'required' => false ),
            'draft' => array( 'required' => false ),
        ),
    ) );
    register_rest_route( VERGEML_REST_NS, '/brief/test', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_brief_rest_test',
        'permission_callback' => $may,
    ) );
    register_rest_route( VERGEML_REST_NS, '/brief/adopt', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_brief_rest_adopt',
        'permission_callback' => $may,
    ) );
    register_rest_route( VERGEML_REST_NS, '/brief/discard', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_brief_rest_discard',
        'permission_callback' => $may,
    ) );
}

function vergeml_brief_rest_session( WP_REST_Request $request ) {
    if ( 'POST' === $request->get_method() ) {
        if ( $request->get_param( 'reset' ) ) {
            vergeml_brief_save( vergeml_brief_fresh() );
            return rest_ensure_response( vergeml_brief_boot() );
        }
        $s = vergeml_brief_session();
        if ( null !== $request->get_param( 'draft' ) ) {
            $s['draft'] = vergeml_brief_clean_lines( $request->get_param( 'draft' ) );
        }
        vergeml_brief_save( $s );
        return rest_ensure_response( vergeml_brief_session_out( $s ) );
    }
    return rest_ensure_response( vergeml_brief_boot() );
}

function vergeml_brief_rest_token( WP_REST_Request $request ) {
    $out = vergeml_brief_token();
    return is_wp_error( $out ) ? $out : rest_ensure_response( $out );
}

/** A finished turn, or half of one: what was said, what was answered, the draft as it now reads. */
function vergeml_brief_rest_turn( WP_REST_Request $request ) {
    $s    = vergeml_brief_session();
    $said = $request->get_param( 'said' );
    $say  = $request->get_param( 'say' );

    if ( is_array( $said ) && '' !== trim( (string) ( isset( $said['text'] ) ? $said['text'] : '' ) ) ) {
        $kind = isset( $said['kind'] ) && in_array( (string) $said['kind'], array( 'said', 'choice', 'test', 'adopt', 'discard' ), true ) ? (string) $said['kind'] : 'said';
        vergeml_brief_turn_add( $s, 'user', $kind, sanitize_textarea_field( (string) $said['text'] ) );
    }

    if ( is_array( $say ) && '' !== trim( (string) ( isset( $say['text'] ) ? $say['text'] : '' ) ) ) {
        if ( (int) $s['assistant_turns'] >= VERGEML_BRIEF_TURN_CAP ) {
            vergeml_brief_save( $s );
            return new WP_Error( 'turn_cap', __( 'Every turn of this conversation is used. Start over.', 'vergelabs-media-library' ), array( 'status' => 409 ) );
        }
        $choices = array();
        foreach ( array_slice( (array) ( isset( $say['choices'] ) ? $say['choices'] : array() ), 0, 3 ) as $c ) {
            $c = sanitize_text_field( (string) $c );
            if ( '' !== $c ) {
                $choices[] = $c;
            }
        }
        vergeml_brief_turn_add( $s, 'assistant', 'say', sanitize_textarea_field( (string) $say['text'] ), array( 'choices' => $choices ) );
        $s['assistant_turns'] = (int) $s['assistant_turns'] + 1;
    }

    if ( null !== $request->get_param( 'draft' ) ) {
        $s['draft'] = vergeml_brief_clean_lines( $request->get_param( 'draft' ) );
        // A draft that changed is not the one the held answers were for.
        if ( is_array( $s['test'] ) && (string) $s['test']['brief'] !== vergeml_brief_join( $s['draft'] ) ) {
            $s['test'] = null;
        }
    }

    vergeml_brief_save( $s );
    return rest_ensure_response( vergeml_brief_session_out( $s ) );
}

function vergeml_brief_rest_test( WP_REST_Request $request ) {
    $out = vergeml_brief_test();
    return is_wp_error( $out ) ? $out : rest_ensure_response( $out );
}

function vergeml_brief_rest_adopt( WP_REST_Request $request ) {
    $out = vergeml_brief_adopt();
    return is_wp_error( $out ) ? $out : rest_ensure_response( $out );
}

function vergeml_brief_rest_discard( WP_REST_Request $request ) {
    $s = vergeml_brief_session();
    $s['draft'] = null;
    $s['test']  = null;
    vergeml_brief_turn_add( $s, 'user', 'discard', __( 'Discarded the draft', 'vergelabs-media-library' ) );
    vergeml_brief_save( $s );
    return rest_ensure_response( vergeml_brief_session_out( $s ) );
}

/* ----------------------------------------------------------------- assets */

/** The tab's script and the conversation component, with everything the first paint needs. */
function vergeml_brief_assets() {
    if ( ! function_exists( 'vergeml_talk_assets' ) ) {
        return;
    }
    vergeml_talk_assets();
    wp_enqueue_script( 'vergeml-brief', plugins_url( 'js/vergeml-brief.js', VERGEML_FILE ), array( 'wp-api-fetch', 'wp-i18n', 'vergeml-talk' ), vergeml_asset_ver( 'js/vergeml-brief.js' ), true );
    wp_set_script_translations( 'vergeml-brief', 'vergelabs-media-library' );
    $boot = vergeml_brief_boot();
    $credits = get_option( 'vergeml_ai_credits', array() );
    wp_localize_script( 'vergeml-brief', 'vgmlBrief', array_merge( $boot, array(
        'ns'         => VERGEML_REST_NS,
        'credits'    => is_array( $credits ) && isset( $credits['remaining'] ) && null !== $credits['remaining'] ? (int) $credits['remaining'] : null,
        'licenceUrl' => admin_url( 'admin.php?page=media-licence' ),
        'describeUrl'=> admin_url( 'admin.php?page=media-ai' ),
    ) ) );
}
