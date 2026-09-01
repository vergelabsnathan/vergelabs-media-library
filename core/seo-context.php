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

    /*
     *  Yoast Premium's related keyphrases: a JSON list of {keyword, score}
     *  under _yoast_wpseo_focuskeywords (read from its source, 28.3). Rank
     *  Math and SEOPress put their extra keywords after the first comma of
     *  the same field, which the focus read above skipped past. Three at
     *  most -- wording, not a list to satisfy.
     */
    $related = array();

    $yoast_more = get_post_meta( $post_id, '_yoast_wpseo_focuskeywords', true );

    if ( is_string( $yoast_more ) && '' !== $yoast_more ) {
        foreach ( (array) json_decode( $yoast_more, true ) as $entry ) {
            if ( is_array( $entry ) && ! empty( $entry['keyword'] ) ) {
                $related[] = vergeml_seo_clean( (string) $entry['keyword'], 80 );
            }
        }
    }

    if ( ! $related ) {
        foreach ( array( 'rank_math_focus_keyword', '_seopress_analysis_target_kw' ) as $key ) {
            $value = get_post_meta( $post_id, $key, true );
            if ( is_string( $value ) && false !== strpos( $value, ',' ) ) {
                $parts = array_map( 'trim', explode( ',', $value ) );
                array_shift( $parts ); // the focus keyphrase, already taken
                foreach ( $parts as $part ) {
                    if ( '' !== $part ) {
                        $related[] = vergeml_seo_clean( $part, 80 );
                    }
                }
                break;
            }
        }
    }

    $related = array_values( array_unique( array_filter( $related ) ) );

    if ( $related ) {
        $out['related'] = implode( ', ', array_slice( $related, 0, 3 ) );
    }

    return $out;
}


/**
 *  vergeml_seo_cornerstone_keys
 *
 *  Where the SEO plugins mark a page as the one that matters most: Yoast
 *  calls it cornerstone, Rank Math pillar content. Read from their source.
 *  The gap report puts these pages' images first.
 */

function vergeml_seo_cornerstone_keys() {
    return array( '_yoast_wpseo_is_cornerstone', 'rank_math_pillar_content' );
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


/* ------------------------------------------------------ the alt gap report */

/**
 *  vergeml_seo_keyphrase_keys
 *
 *  The post meta keys under which the SEO plugins keep a page's focus
 *  keyphrase. One list, read by the context and by the gap report, so the two
 *  can never disagree about what "a page with a keyphrase" means.
 */

function vergeml_seo_keyphrase_keys() {
    return array( '_yoast_wpseo_focuskw', 'rank_math_focus_keyword', '_seopress_analysis_target_kw' );
}


/**
 *  vergeml_seo_gap_sql
 *
 *  Images sitting on a page that has a focus keyphrase, with no alt text.
 *
 *  These are the images an SEO plugin is already scoring the page down for,
 *  and the ones a screen reader skips on the page the site most wants read.
 *  A page speaks for an image the same two ways as the context does: it was
 *  uploaded there, or the "Used in" scan found it there.
 *
 *  @param  string $select  What to select: 'p.ID' or 'COUNT(DISTINCT p.ID)'.
 *  @return string          A prepared statement, minus the trailing LIMIT.
 */

function vergeml_seo_gap_sql( $select ) {

    global $wpdb;

    $keys  = "'" . implode( "','", array_map( 'esc_sql', vergeml_seo_keyphrase_keys() ) ) . "'";
    $stars = "'" . implode( "','", array_map( 'esc_sql', vergeml_seo_cornerstone_keys() ) ) . "'";
    $mime  = $wpdb->esc_like( 'image/' ) . '%';

    /*
     *  Pages with a keyphrase, each with a flag for cornerstone / pillar so
     *  the pages the site cares most about come first. One pass over the
     *  meta table: a row counts as a keyphrase when it is one of those keys
     *  with a value, and as a star when it is one of the cornerstone keys
     *  switched on. All in One SEO keeps its own table and joins in flat.
     */
    $pages = "SELECT post_id,
                     MAX( CASE WHEN meta_key IN ($stars) AND meta_value IN ('1','on') THEN 1 ELSE 0 END ) AS pri
                FROM {$wpdb->postmeta}
               WHERE ( meta_key IN ($keys) AND meta_value <> '' )
                  OR ( meta_key IN ($stars) AND meta_value IN ('1','on') )
            GROUP BY post_id
              HAVING SUM( CASE WHEN meta_key IN ($keys) AND meta_value <> '' THEN 1 ELSE 0 END ) > 0";

    if ( defined( 'AIOSEO_VERSION' ) ) {
        $pages .= " UNION SELECT post_id, 0 AS pri FROM {$wpdb->prefix}aioseo_posts WHERE keyphrases LIKE '%\"keyphrase\":\"_%'";
    }

    // $select, $keys and $stars are literals from this file, never input; the
    // one value from outside is the MIME pattern, and it is placeheld.
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return $wpdb->prepare(
        "SELECT $select FROM {$wpdb->posts} p
           LEFT JOIN {$wpdb->postmeta} alt  ON alt.post_id  = p.ID AND alt.meta_key  = '_wp_attachment_image_alt'
           LEFT JOIN {$wpdb->postmeta} used ON used.post_id = p.ID AND used.meta_key = '_vergeml_used_in'
          INNER JOIN ( $pages ) kw
                  ON kw.post_id = p.post_parent
                  OR ( used.meta_value IS NOT NULL AND FIND_IN_SET( kw.post_id, used.meta_value ) )
          WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE %s
            AND ( alt.meta_id IS NULL OR alt.meta_value = '' )",
        $mime
    );
}


function vergeml_seo_gap_ids( $limit = 0 ) {

    global $wpdb;

    $cap = $limit > 0 ? (int) $limit : PHP_INT_MAX;

    // Cornerstone and pillar pages first, then by id so a run walks in order.
    // The statement is prepared in vergeml_seo_gap_sql; the LIMIT is placeheld here.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        vergeml_seo_gap_sql( 'p.ID, MAX( kw.pri ) AS pri' ) . ' GROUP BY p.ID ORDER BY pri DESC, p.ID ASC LIMIT %d',
        $cap
    ) ) );
    // phpcs:enable
}


/**
 *  vergeml_seo_gap_count
 *
 *  Counted in the database, held for a minute: the number sits on a button
 *  that is redrawn on every status poll, and the join is not free at scale.
 */

function vergeml_seo_gap_count() {

    $cached = get_transient( 'vergeml_seo_gap_count' );

    if ( false !== $cached ) {
        return (int) $cached;
    }

    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- prepared in vergeml_seo_gap_sql.
    $n = (int) $wpdb->get_var( vergeml_seo_gap_sql( 'COUNT(DISTINCT p.ID)' ) );

    set_transient( 'vergeml_seo_gap_count', $n, MINUTE_IN_SECONDS );

    return $n;
}


/* ------------------------------------------------------------- file names */

/**
 *  vergeml_seo_lead_slug
 *
 *  Lead a file name with the page's keyphrase -- only when the picture
 *  earned it.
 *
 *  "Earned" is decided by the description, not by the page: every word of
 *  the keyphrase has to appear in what the model wrote about the image (alt,
 *  caption, title or tags). That is the model having looked and said "yes,
 *  this is that thing". A page about oak tables with a photo of the workshop
 *  door keeps its door; the photo of the table becomes
 *  handmade-oak-table-<what-it-is>.jpg.
 *
 *  Subject to the same switch as the rest of the page context, and never
 *  applied twice: a slug that already carries the keyphrase is left alone.
 *
 *  @param  int    $attachment_id
 *  @param  string $slug   The slug the title produced.
 *  @param  array  $row    The index row: alt, caption, title, tags.
 *  @return string         The slug, led by the keyphrase or unchanged.
 */

function vergeml_seo_lead_slug( $attachment_id, $slug, $row ) {

    if ( ! function_exists( 'vergeml_ai_settings' ) ) {
        return $slug;
    }

    $settings = vergeml_ai_settings();

    if ( empty( $settings['page_context'] ) ) {
        return $slug;
    }

    $page_id = vergeml_seo_page_for( $attachment_id );

    if ( ! $page_id ) {
        return $slug;
    }

    $context = vergeml_seo_page_context( $page_id );

    if ( empty( $context['keyphrase'] ) ) {
        return $slug;
    }

    $lead = sanitize_title( $context['keyphrase'] );

    if ( '' === $lead || false !== strpos( '-' . $slug . '-', '-' . $lead . '-' ) ) {
        return $slug;
    }

    $said = strtolower( implode( ' ', array(
        isset( $row['alt'] )     ? (string) $row['alt']     : '',
        isset( $row['caption'] ) ? (string) $row['caption'] : '',
        isset( $row['title'] )   ? (string) $row['title']   : '',
        isset( $row['tags'] )    ? ( is_array( $row['tags'] ) ? implode( ' ', $row['tags'] ) : (string) $row['tags'] ) : '',
    ) ) );

    $said = ' ' . sanitize_title_with_dashes( remove_accents( $said ), '', 'save' ) . ' ';
    $said = str_replace( '-', ' ', $said );

    foreach ( explode( '-', $lead ) as $word ) {
        if ( false === strpos( $said, ' ' . $word . ' ' ) ) {
            return $slug; // the model did not see it; the page does not get to say it did
        }
    }

    return $lead . '-' . $slug;
}
