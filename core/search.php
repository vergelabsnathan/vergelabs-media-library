<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Media library search.
 *
 *  WordPress searches attachments by title, caption and description only. This
 *  widens that to filenames, the uploader's name and any term the item is filed
 *  under, and lets the site owner turn each column off again from
 *  Settings > Media > Media Library.
 *
 *  Scope is deliberately narrow. These filters do nothing unless the query is
 *  for attachments, carries a search term, and is running in the admin or the
 *  media modal's AJAX endpoint. A front-end post search is never touched.
 *
 *  @since    2.10
 */


/**
 *  vergeml_search_columns
 *
 *  The columns the site owner has enabled, intersected with the ones we know
 *  how to search. Returns an empty array when the option is unusable, which
 *  makes every filter below fall through to core behaviour.
 */

function vergeml_search_columns() {

    $options = get_option( 'vergeml_lib_options', array() );

    if ( ! isset( $options['search_in'] ) || ! is_array( $options['search_in'] ) )
        return array();

    $known = array( 'titles', 'captions', 'descriptions', 'filenames', 'authors', 'taxonomies' );

    return array_values( array_intersect( $options['search_in'], $known ) );
}


/**
 *  vergeml_search_applies
 *
 *  True only for an attachment query that is actually searching, inside the
 *  admin or the media modal. Everything else is left alone.
 */

function vergeml_search_applies( $query ) {

    if ( ! $query instanceof WP_Query )
        return false;

    if ( ! is_admin() && ! wp_doing_ajax() )
        return false;

    // Searching by uploader, filename and folder is for people who see the library.
    if ( ! current_user_can( 'upload_files' ) )
        return false;

    $post_type = $query->get( 'post_type' );

    if ( is_array( $post_type ) ) {
        if ( ! in_array( 'attachment', $post_type, true ) )
            return false;
    }
    elseif ( 'attachment' !== $post_type ) {
        return false;
    }

    $search = $query->get( 's' );

    if ( ! is_string( $search ) || '' === trim( $search ) )
        return false;

    // nothing enabled, or only what core already does: leave core to it
    $columns = vergeml_search_columns();

    if ( empty( $columns ) )
        return false;

    return true;
}


/**
 *  vergeml_search_terms
 *
 *  Splits the search string the way a person expects: every word has to match
 *  something, but not necessarily the same column. "blue logo" finds an item
 *  titled "Logo" filed under "Blue".
 */

function vergeml_search_terms( $search ) {

    $terms = preg_split( '/\s+/', trim( $search ), -1, PREG_SPLIT_NO_EMPTY );

    if ( ! $terms )
        return array();

    // a hard cap keeps a pathological query from building a hundred ORs
    return array_slice( $terms, 0, 8 );
}


/**
 *  vergeml_posts_join
 *
 *  Adds only the joins the enabled columns actually need. Aliases are prefixed
 *  so they cannot collide with core's own sq1/sq2 or another plugin's.
 */

add_filter( 'posts_join', 'vergeml_posts_join', 10, 2 );

function vergeml_posts_join( $join, $query ) {

    global $wpdb;

    if ( ! vergeml_search_applies( $query ) )
        return $join;

    $columns = vergeml_search_columns();

    if ( in_array( 'filenames', $columns, true ) ) {
        $join .= " LEFT JOIN {$wpdb->postmeta} AS vgml_file"
               . " ON ( {$wpdb->posts}.ID = vgml_file.post_id AND vgml_file.meta_key = '_wp_attached_file' )";
    }

    if ( in_array( 'authors', $columns, true ) ) {
        $join .= " LEFT JOIN {$wpdb->users} AS vgml_user ON {$wpdb->posts}.post_author = vgml_user.ID";
    }

    if ( in_array( 'taxonomies', $columns, true ) ) {
        $join .= " LEFT JOIN {$wpdb->term_relationships} AS vgml_tr ON {$wpdb->posts}.ID = vgml_tr.object_id"
               . " LEFT JOIN {$wpdb->term_taxonomy} AS vgml_tt ON vgml_tr.term_taxonomy_id = vgml_tt.term_taxonomy_id"
               . " LEFT JOIN {$wpdb->terms} AS vgml_term ON vgml_tt.term_id = vgml_term.term_id";
    }

    return $join;
}


/**
 *  vergeml_posts_distinct
 *
 *  An item filed under three terms joins three rows. Without DISTINCT it would
 *  appear three times in the grid.
 */

add_filter( 'posts_distinct', 'vergeml_posts_distinct', 10, 2 );

function vergeml_posts_distinct( $distinct, $query ) {

    if ( ! vergeml_search_applies( $query ) )
        return $distinct;

    $columns = vergeml_search_columns();

    if ( in_array( 'taxonomies', $columns, true ) || in_array( 'filenames', $columns, true ) )
        return 'DISTINCT';

    return $distinct;
}


/**
 *  vergeml_posts_search
 *
 *  Replaces core's search clause for these queries. Core's own clause is
 *  discarded on purpose rather than appended to: the point of the setting is to
 *  be able to switch title, caption and description off, which cannot be done
 *  by adding to what core built.
 */

add_filter( 'posts_search', 'vergeml_posts_search', 10, 2 );

function vergeml_posts_search( $search, $query ) {

    global $wpdb;

    if ( ! vergeml_search_applies( $query ) )
        return $search;

    $columns = vergeml_search_columns();
    $terms   = vergeml_search_terms( $query->get( 's' ) );

    if ( empty( $terms ) )
        return $search;

    $clauses = array();

    foreach ( $terms as $term ) {

        $like = '%' . $wpdb->esc_like( $term ) . '%';
        $ors  = array();

        if ( in_array( 'titles', $columns, true ) )
            $ors[] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $like );

        if ( in_array( 'captions', $columns, true ) )
            $ors[] = $wpdb->prepare( "{$wpdb->posts}.post_excerpt LIKE %s", $like );

        if ( in_array( 'descriptions', $columns, true ) )
            $ors[] = $wpdb->prepare( "{$wpdb->posts}.post_content LIKE %s", $like );

        if ( in_array( 'filenames', $columns, true ) )
            $ors[] = $wpdb->prepare( 'vgml_file.meta_value LIKE %s', $like );

        if ( in_array( 'authors', $columns, true ) ) {
            $ors[] = $wpdb->prepare( 'vgml_user.display_name LIKE %s', $like );
            $ors[] = $wpdb->prepare( 'vgml_user.user_nicename LIKE %s', $like );
        }

        if ( in_array( 'taxonomies', $columns, true ) )
            $ors[] = $wpdb->prepare( 'vgml_term.name LIKE %s', $like );

        if ( empty( $ors ) )
            continue;

        $clauses[] = '(' . implode( ' OR ', $ors ) . ')';
    }

    if ( empty( $clauses ) )
        return $search;

    // every word must match something; which column is up to the word
    return ' AND (' . implode( ' AND ', $clauses ) . ') ';
}
