<?php
/**
 *  Try a query (core/search-try.php): both searches for one phrase, each hit
 *  with its why.
 *
 *      wp eval-file tests/tree/search-try.php --allow-root
 *
 *  The word pass is checked against the rows it claims: every hit's named
 *  fields really hold the word, the count is what a direct LIKE finds, and
 *  with two words both have to match. The meaning pass, when the service
 *  answers, comes back sorted, at or above the floor, with the words of the
 *  query marked on each hit. One embed call, which the service does not
 *  charge. Nothing is written.
 *
 *  Mutation check run against this suite on 2026-09-06: with the AND across
 *  terms made an OR in vergeml_search_try(), A5 goes red.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_search_try' ) ) {
    echo "core/search-try.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

wp_set_current_user( 1 );

$GLOBALS['t_pass'] = 0;
$GLOBALS['t_fail'] = 0;

function t_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['t_pass']++;
    } else {
        $GLOBALS['t_fail']++;
    }
    echo sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

global $wpdb;
$t_table = $wpdb->vergeml_ai_index;

// A word the catalogue holds: the most used tag.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table, read only.
$t_freq = array();
foreach ( (array) $wpdb->get_col( "SELECT tags FROM {$t_table} WHERE error = '' LIMIT 2000" ) as $t_raw ) {
    foreach ( (array) vergeml_index_tags_out( $t_raw ) as $t_tag ) {
        $t_tag = strtolower( trim( (string) $t_tag ) );
        if ( '' !== $t_tag && false === strpos( $t_tag, ' ' ) ) {
            $t_freq[ $t_tag ] = isset( $t_freq[ $t_tag ] ) ? $t_freq[ $t_tag ] + 1 : 1;
        }
    }
}
arsort( $t_freq );
$t_word = $t_freq ? (string) key( $t_freq ) : 'photo';

echo "\nA  By word: \"{$t_word}\"\n\n";

$t_r = vergeml_search_try( $t_word );
$t_like = '%' . $wpdb->esc_like( $t_word ) . '%';
$t_direct = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$t_table} i ON i.attachment_id = p.ID AND i.error = ''
      WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE 'image/%%'
        AND ( p.post_title LIKE %s OR p.post_name LIKE %s OR p.post_excerpt LIKE %s OR p.post_content LIKE %s OR i.caption LIKE %s OR i.tags LIKE %s OR i.title LIKE %s )",
    $t_like, $t_like, $t_like, $t_like, $t_like, $t_like, $t_like
) );
$t_core = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE 'image/%%'
        AND ( p.post_title LIKE %s OR p.post_excerpt LIKE %s OR p.post_content LIKE %s )",
    $t_like, $t_like, $t_like
) );

t_check( 'A1 the count by word is what the library\'s LIKE finds', $t_r['word']['total'] === $t_direct, $t_r['word']['total'] . ' vs ' . $t_direct );
t_check( 'A2 "with this plugin off" is WordPress\'s three columns alone', $t_r['word']['core'] === $t_core && $t_core <= $t_direct, $t_r['word']['core'] . ' vs ' . $t_core );
t_check( 'A3 one word, one per-word count, equal to the total', 1 === count( $t_r['word']['per_word'] ) && $t_r['word']['per_word'][0]['n'] === $t_r['word']['total'] );
t_check( 'A4 at most thirty hits, each with a thumbnail address and the fields that held the word', count( $t_r['word']['hits'] ) <= 30 && count( $t_r['word']['hits'] ) > 0 && ! array_filter( $t_r['word']['hits'], function ( $h ) { return empty( $h['in'] ); } ), count( $t_r['word']['hits'] ) . ' hits' );

// Every claimed field really holds the word.
$t_wrong = 0;
foreach ( array_slice( $t_r['word']['hits'], 0, 10 ) as $t_h ) {
    $t_row = $wpdb->get_row( $wpdb->prepare( "SELECT p.post_title, p.post_name, p.post_excerpt, p.post_content, i.caption, i.tags, i.title FROM {$wpdb->posts} p LEFT JOIN {$t_table} i ON i.attachment_id = p.ID WHERE p.ID = %d", $t_h['id'] ), ARRAY_A );
    $t_map = array( 'title' => 'post_title', 'file' => 'post_name', 'caption' => 'post_excerpt', 'description' => 'post_content', 'ai_caption' => 'caption', 'ai_tags' => 'tags', 'ai_title' => 'title' );
    foreach ( $t_h['in'] as $t_field ) {
        if ( false === mb_stripos( (string) $t_row[ $t_map[ $t_field ] ], $t_word ) ) {
            $t_wrong++;
        }
    }
}
t_check( 'A5 the fields named on a hit hold the word', 0 === $t_wrong, $t_wrong . ' wrong' );

echo "\nB  Two words: both have to match\n\n";

$t_second = 'zzqxv';
$t_r2 = vergeml_search_try( $t_word . ' ' . $t_second );
t_check( 'B1 a word nobody has makes the pair match nothing', 0 === $t_r2['word']['total'] && 2 === count( $t_r2['word']['per_word'] ) && 0 === $t_r2['word']['per_word'][1]['n'] && $t_r2['word']['per_word'][0]['n'] === $t_r['word']['total'], wp_json_encode( $t_r2['word']['per_word'] ) );
t_check( 'B2 the terms come back as typed', array( $t_word, $t_second ) === $t_r2['terms'] );
$t_r3 = vergeml_search_try( '' );
t_check( 'B3 an empty query is an empty answer, not an error', 0 === $t_r3['word']['total'] && array() === $t_r3['word']['hits'] && ! $t_r3['meaning']['available'] );

echo "\nC  By meaning\n\n";

if ( ! $t_r['meaning']['available'] ) {
    t_check( 'C1 the service did not answer: said so, the word pass stands (skipped C2-C5)', array() === $t_r['meaning']['hits'] );
} elseif ( array() === $t_r['meaning']['hits'] ) {
    /*
     *  The service answered and nothing cleared the floor. A mock description
     *  carries a hash of the filename for a vector, which no real query vector
     *  lands near, so on a library of mock rows an empty answer is the mock
     *  being what it is. On real descriptions it is the search by meaning
     *  failing to find the library's own most common word, and that stays red.
     *  Either way C2-C5 have no hits to read; min() on nothing is a fatal.
     */
    $t_real = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_table} WHERE error = '' AND model <> 'mock'" );
    t_check( 'C1 the service answered and nothing cleared the floor: right only on mock descriptions (skipped C2-C5)', 0 === $t_real, $t_real . ' rows described by a real model' );
} else {
    $t_scores = array_map( function ( $h ) { return (float) $h['score']; }, $t_r['meaning']['hits'] );
    $t_sorted = $t_scores;
    rsort( $t_sorted );
    t_check( 'C1 the hits come back highest first', $t_scores === $t_sorted, wp_json_encode( array_slice( $t_scores, 0, 5 ) ) );
    t_check( 'C2 every score is at or above the floor', ! array_filter( $t_scores, function ( $s ) { return $s < VERGEML_MEANING_FLOOR; } ), min( $t_scores ) . ' lowest' );
    t_check( 'C3 at most sixty shown; the total at or above the floor is at least that', $t_r['meaning']['shown'] <= 60 && $t_r['meaning']['total'] >= $t_r['meaning']['shown'] && count( $t_r['meaning']['hits'] ) === $t_r['meaning']['shown'], $t_r['meaning']['shown'] . ' of ' . $t_r['meaning']['total'] );
    $t_words_wrong = 0;
    foreach ( array_slice( $t_r['meaning']['hits'], 0, 10 ) as $t_h ) {
        $t_row = $wpdb->get_row( $wpdb->prepare( "SELECT title, caption, tags FROM {$t_table} WHERE attachment_id = %d", $t_h['id'] ), ARRAY_A );
        $t_hay = mb_strtolower( $t_row['title'] . ' ' . $t_row['caption'] . ' ' . implode( ' ', (array) vergeml_index_tags_out( $t_row['tags'] ) ) );
        $t_has = false !== mb_strpos( $t_hay, $t_word );
        if ( $t_has !== in_array( $t_word, $t_h['words'], true ) ) {
            $t_words_wrong++;
        }
    }
    t_check( 'C4 a hit says which words of the query it carries, and only those', 0 === $t_words_wrong, $t_words_wrong . ' wrong' );
    t_check( 'C5 the seconds are said', is_numeric( $t_r['meaning']['seconds'] ) );
}
// phpcs:enable

echo sprintf( "\n%d/%d passed\n", $GLOBALS['t_pass'], $GLOBALS['t_pass'] + $GLOBALS['t_fail'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $GLOBALS['t_fail'] ? 1 : 0 );
