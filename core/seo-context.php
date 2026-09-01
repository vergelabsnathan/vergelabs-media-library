<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  What the page an image sits on is trying to be.
 *
 *  An SEO plugin knows a page's focus keyphrase and the sentence it wants
 *  search engines to show. The page knows which images it uses. Chained, an
 *  image inherits an intent it never had: DSC_4821.jpg on the "Handmade oak
 *  tables" page is, in all likelihood, a handmade oak table.
 *
 *  Everything here is ADVISORY. It travels to the describe service as context,
 *  where the prompt already says: for wording and subject only, never repeat
 *  it back if the picture does not show it. The model still describes what is
 *  visible -- alt text stuffed with a keyphrase is exactly what these same SEO
 *  plugins mark down, and what a screen reader user cannot use. And it is the
 *  site owner's choice: one switch on the AI screen, and off means nothing
 *  from here leaves the site.
 *
 *  Read straight from post meta, the way the SEO plugins store it, rather
 *  than through their APIs: no dependency, no version coupling, and it still
 *  works for a site that switched SEO plugins and kept the data.
 *
 *  @since 3.12
 */


/**
 *  vergeml_seo_page_context
 *
 *  @param  int $post_id  The page or post.
 *  @return array         Any of 'keyphrase', 'description' -- or empty.
 */

function vergeml_seo_page_context( $post_id ) {

    $post_id = (int) $post_id;
    $out     = array();

    if ( $post_id <= 0 ) {
        return $out;
    }

    // Yoast, Rank Math, SEOPress -- in the order of their install bases. The
    // first one with a keyphrase wins; a site rarely has two with data.
    $keyphrase_keys   = array( '_yoast_wpseo_focuskw', 'rank_math_focus_keyword', '_seopress_analysis_target_kw' );
    $description_keys = array( '_yoast_wpseo_metadesc', 'rank_math_description', '_seopress_titles_desc' );

    foreach ( $keyphrase_keys as $key ) {

        $value = get_post_meta( $post_id, $key, true );

        if ( is_string( $value ) && '' !== trim( $value ) ) {
            // Rank Math and SEOPress store several, comma-separated; the
            // first is the focus.
            $first            = trim( (string) strtok( $value, ',' ) );
            $out['keyphrase'] = vergeml_seo_clean( $first, 120 );
            break;
        }
    }

    foreach ( $description_keys as $key ) {

        $value = get_post_meta( $post_id, $key, true );

        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $out['description'] = vergeml_seo_clean( $value, 300 );
            break;
        }
    }

    // All in One SEO keeps its own table.
    if ( empty( $out['keyphrase'] ) && defined( 'AIOSEO_VERSION' ) ) {

        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT keyphrases, description FROM {$wpdb->prefix}aioseo_posts WHERE post_id = %d",
            $post_id
        ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- another plugin's table, read once per describe.

        if ( $row ) {

            $keyphrases = json_decode( (string) $row->keyphrases, true );

            if ( is_array( $keyphrases ) && ! empty( $keyphrases['focus']['keyphrase'] ) ) {
                $out['keyphrase'] = vergeml_seo_clean( (string) $keyphrases['focus']['keyphrase'], 120 );
            }

            if ( empty( $out['description'] ) && is_string( $row->description ) && '' !== trim( $row->description ) ) {
                $out['description'] = vergeml_seo_clean( $row->description, 300 );
            }
        }
    }

    // Template variables left unexpanded ("%%title%% %%sep%%") say nothing
    // about the page; drop a value that is mostly placeholders.
    foreach ( $out as $k => $v ) {
        if ( '' === $v || substr_count( $v, '%%' ) >= 2 ) {
            unset( $out[ $k ] );
        }
    }

    return $out;
}


/**
 *  vergeml_seo_clean
 *
 *  Meta typed by a person into a settings box: flatten whitespace, strip
 *  tags and control characters, bound the length. It sits next to a prompt.
 */

function vergeml_seo_clean( $value, $max ) {

    $value = wp_strip_all_tags( (string) $value );
    $value = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $value );
    $value = trim( preg_replace( '/\s+/', ' ', $value ) );

    if ( function_exists( 'mb_substr' ) ) {
        return mb_substr( $value, 0, $max );
    }

    return substr( $value, 0, $max );
}


/**
 *  vergeml_seo_page_for
 *
 *  Which page speaks for an image: the post it was uploaded to, else the
 *  first page the "Used in" scan found it on.
 *
 *  @return int  A post ID, or 0.
 */

function vergeml_seo_page_for( $attachment_id ) {

    $post = get_post( $attachment_id );

    if ( $post && $post->post_parent ) {
        return (int) $post->post_parent;
    }

    if ( defined( 'VERGEML_META_USED_IN' ) ) {

        $raw = (string) get_post_meta( (int) $attachment_id, VERGEML_META_USED_IN, true );

        if ( '' !== $raw ) {

            foreach ( explode( ',', $raw ) as $id ) {

                $id = (int) $id;

                if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
                    return $id;
                }
            }
        }
    }

    return 0;
}
