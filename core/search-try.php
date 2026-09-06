<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  Try a query: what the media library's search would find, and why.
 *
 *  A search in the library is two things that look like one: a word search
 *  over what WordPress and this plugin's catalogue hold, where every word has
 *  to match something; and a search by meaning, offered beside it, that
 *  compares the phrase with every described picture. When a search finds
 *  nothing, or the wrong things, nothing on the library screen says which of
 *  the two did what. This route runs both for one query and answers, per hit,
 *  with the field that held the word or the score that put it there. The AI
 *  screen's Search tab draws it.
 *
 *  Read-only. The word pass is the same LIKE the library runs
 *  (core/search.php, core/ai.php); the meaning pass is
 *  vergeml_meaning_search(), whose one embed call the service does not charge.
 */

/** The fields a word can be found in, with the column each is. */
function vergeml_search_try_fields() {
    return array(
        'title'       => 'p.post_title',
        'file'        => 'p.post_name',
        'caption'     => 'p.post_excerpt',
        'description' => 'p.post_content',
        'ai_caption'  => 'i.caption',
        'ai_tags'     => 'i.tags',
        'ai_title'    => 'i.title',
    );
}

/** WordPress's own search columns: what a search finds with this plugin off. */
function vergeml_search_try_core_fields() {
    return array( 'title', 'caption', 'description' );
}

/**
 *  @param string $q
 *  @return array { query, terms, word: { total, core, per_word, hits[] }, meaning: { available, total, shown, seconds, hits[] } }
 */
function vergeml_search_try( $q ) {
    global $wpdb;

    $q     = trim( (string) $q );
    $terms = function_exists( 'vergeml_search_terms' ) ? vergeml_search_terms( $q ) : array_slice( preg_split( '/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY ), 0, 8 );
    $out   = array(
        'query'   => $q,
        'terms'   => $terms,
        'word'    => array( 'total' => 0, 'core' => 0, 'per_word' => array(), 'hits' => array() ),
        'meaning' => array( 'available' => false, 'total' => 0, 'shown' => 0, 'seconds' => 0, 'hits' => array() ),
    );
    if ( '' === $q || ! $terms || ! isset( $wpdb->vergeml_ai_index ) ) {
        return $out;
    }

    $fields = vergeml_search_try_fields();
    $core   = vergeml_search_try_core_fields();
    $from   = "FROM {$wpdb->posts} p LEFT JOIN {$wpdb->vergeml_ai_index} i ON i.attachment_id = p.ID AND i.error = ''
               WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE 'image/%'";

    // Every word has to match something, in any of the fields.
    $where_all  = array();
    $where_core = array();
    foreach ( $terms as $term ) {
        $like = '%' . $wpdb->esc_like( $term ) . '%';
        $any  = array();
        $own  = array();
        foreach ( $fields as $name => $column ) {
            $any[] = $wpdb->prepare( "{$column} LIKE %s", $like ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column from a fixed list.
            if ( in_array( $name, $core, true ) ) {
                $own[] = $wpdb->prepare( "{$column} LIKE %s", $like ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
        }
        $where_all[]  = '( ' . implode( ' OR ', $any ) . ' )';
        $where_core[] = '( ' . implode( ' OR ', $own ) . ' )';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $out['word']['per_word'][] = array( 'word' => $term, 'n' => (int) $wpdb->get_var( "SELECT COUNT(*) {$from} AND ( " . implode( ' OR ', $any ) . ' )' ) );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- assembled from prepared pieces.
    $out['word']['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) {$from} AND " . implode( ' AND ', $where_all ) );
    $out['word']['core']  = (int) $wpdb->get_var( "SELECT COUNT(*) {$from} AND " . implode( ' AND ', $where_core ) );

    $rows = $wpdb->get_results(
        "SELECT p.ID, p.post_title, p.post_name, p.post_excerpt, p.post_content, i.caption, i.tags, i.title AS ai_title
         {$from} AND " . implode( ' AND ', $where_all ) . '
         ORDER BY p.post_date DESC, p.ID DESC LIMIT 30',
        ARRAY_A
    );
    // phpcs:enable

    $word_ids = array();
    foreach ( (array) $rows as $row ) {
        $texts = array(
            'title'       => (string) $row['post_title'],
            'file'        => (string) $row['post_name'],
            'caption'     => (string) $row['post_excerpt'],
            'description' => (string) $row['post_content'],
            'ai_caption'  => (string) $row['caption'],
            'ai_tags'     => implode( ', ', (array) vergeml_index_tags_out( $row['tags'] ) ),
            'ai_title'    => (string) $row['ai_title'],
        );
        $in      = array();
        $snippet = '';
        foreach ( $texts as $name => $text ) {
            foreach ( $terms as $term ) {
                if ( '' !== $text && false !== mb_stripos( $text, $term ) ) {
                    $in[ $name ] = true;
                    if ( '' === $snippet && ! in_array( $name, array( 'title', 'ai_title', 'file' ), true ) ) {
                        $snippet = vergeml_search_try_snippet( $text, $term );
                    }
                }
            }
        }
        $id = (int) $row['ID'];
        $word_ids[ $id ] = true;
        $out['word']['hits'][] = array(
            'id'      => $id,
            'title'   => '' !== (string) $row['ai_title'] ? (string) $row['ai_title'] : (string) $row['post_title'],
            'thumb'   => (string) wp_get_attachment_image_url( $id, 'thumbnail' ),
            'in'      => array_keys( $in ),
            'snippet' => $snippet,
            'tags'    => (array) vergeml_index_tags_out( $row['tags'] ),
        );
    }

    if ( function_exists( 'vergeml_meaning_search' ) ) {
        $t0  = microtime( true );
        $ids = vergeml_meaning_search( $q, 60 );
        $out['meaning']['seconds'] = round( microtime( true ) - $t0, 1 );
        if ( null !== $ids ) {
            $meta   = isset( $GLOBALS['vergeml_meaning_meta'] ) ? $GLOBALS['vergeml_meaning_meta'] : array();
            $scores = isset( $meta['scores'] ) && is_array( $meta['scores'] ) ? $meta['scores'] : array();
            $out['meaning']['available'] = true;
            $out['meaning']['total']     = count( $scores );
            $out['meaning']['shown']     = count( $ids );
            if ( $ids ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids cast to int.
                $found = $wpdb->get_results( "SELECT attachment_id, title, caption, tags FROM {$wpdb->vergeml_ai_index} WHERE attachment_id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')', ARRAY_A );
                $by    = array();
                foreach ( (array) $found as $row ) {
                    $by[ (int) $row['attachment_id'] ] = $row;
                }
                foreach ( $ids as $id ) {
                    $id = (int) $id;
                    if ( ! isset( $by[ $id ] ) ) {
                        continue;
                    }
                    $tags = (array) vergeml_index_tags_out( $by[ $id ]['tags'] );
                    $hay  = mb_strtolower( $by[ $id ]['title'] . ' ' . $by[ $id ]['caption'] . ' ' . implode( ' ', $tags ) );
                    $words = array();
                    foreach ( $terms as $term ) {
                        if ( false !== mb_strpos( $hay, mb_strtolower( $term ) ) ) {
                            $words[] = $term;
                        }
                    }
                    $out['meaning']['hits'][] = array(
                        'id'      => $id,
                        'title'   => (string) $by[ $id ]['title'],
                        'thumb'   => (string) wp_get_attachment_image_url( $id, 'thumbnail' ),
                        'score'   => isset( $scores[ $id ] ) ? round( (float) $scores[ $id ], 2 ) : null,
                        'tags'    => array_slice( $tags, 0, 6 ),
                        'words'   => $words,
                        'by_word' => isset( $word_ids[ $id ] ),
                        'snippet' => $words ? vergeml_search_try_snippet( (string) $by[ $id ]['caption'], $words[0] ) : '',
                    );
                }
            }
        }
    }

    return $out;
}

/** The text around the first place a word appears, trimmed to a line. */
function vergeml_search_try_snippet( $text, $term ) {
    $text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
    $at   = mb_stripos( $text, $term );
    if ( false === $at ) {
        return mb_substr( $text, 0, 110 );
    }
    $start = max( 0, $at - 50 );
    $piece = mb_substr( $text, $start, 120 );
    return ( $start > 0 ? '…' : '' ) . $piece . ( $start + 120 < mb_strlen( $text ) ? '…' : '' );
}

add_action( 'rest_api_init', 'vergeml_search_try_routes' );

function vergeml_search_try_routes() {
    register_rest_route( VERGEML_REST_NS, '/search-try', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'vergeml_search_try_rest',
        'permission_callback' => function () {
            return current_user_can( 'upload_files' );
        },
        'args'                => array(
            's' => array( 'type' => 'string', 'required' => true ),
        ),
    ) );
}

function vergeml_search_try_rest( WP_REST_Request $request ) {
    return rest_ensure_response( vergeml_search_try( sanitize_text_field( (string) $request->get_param( 's' ) ) ) );
}
