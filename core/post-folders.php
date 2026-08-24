<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Folders for posts, pages and custom post types.
 *
 *  A media taxonomy is an ordinary taxonomy, so putting it on another post type
 *  is one extra argument to register_taxonomy. That is the whole storage story --
 *  there is no second table, no `type` column, and no separate addon, because
 *  taxonomies were always many-to-many between terms and *any* object type. The
 *  plugins that keep folders in a table of their own had to build all of that to
 *  reach the same place.
 *
 *  What does need writing is the counting. A term's stored count is the number of
 *  attachments in it and stays that way, because the media library is the screen
 *  that reads it and a folder claiming 40 files when 12 of them are blog posts is
 *  a lie on the screen that matters most. Counts for another post type are worked
 *  out when that screen asks for them.
 *
 *  @since 3.2
 */


/**
 *  vergeml_folder_post_types
 *
 *  The post types a media taxonomy has been turned on for, attachments aside.
 *
 *  Read from the taxonomy's own settings rather than from the registered object
 *  types, so it answers the same before and after `init` and can be used to
 *  decide what to register.
 */

function vergeml_folder_post_types( $taxonomy ) {

    $all = get_option( 'vergeml_taxonomies', array() );

    if ( ! isset( $all[ $taxonomy ]['post_types'] ) || ! is_array( $all[ $taxonomy ]['post_types'] ) ) {
        return array();
    }

    $out = array();

    foreach ( $all[ $taxonomy ]['post_types'] as $type ) {

        $type = sanitize_key( $type );

        // Never attachment: that one is not optional and is added separately.
        if ( 'attachment' === $type || ! $type ) {
            continue;
        }

        $out[] = $type;
    }

    return array_values( array_unique( $out ) );
}


/**
 *  vergeml_folder_object_types
 *
 *  Everything a media taxonomy should be registered for.
 */

function vergeml_folder_object_types( $taxonomy ) {
    return array_merge( array( 'attachment' ), vergeml_folder_post_types( $taxonomy ) );
}


/**
 *  vergeml_folderable_post_types
 *
 *  The post types worth offering. Anything with a UI that is not an attachment
 *  and not one of core's internal types, which have list screens nobody files
 *  things on.
 */

function vergeml_folderable_post_types() {

    $skip = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
        'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part',
        'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face' );

    $out = array();

    foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {

        if ( in_array( $type->name, $skip, true ) ) {
            continue;
        }

        $out[ $type->name ] = $type->labels->name;
    }

    return $out;
}


/**
 *  vergeml_folder_counts
 *
 *  How many posts of one type are in each term, in one query.
 *
 *  The stored term count cannot answer this: it counts attachments, deliberately.
 *  One grouped query answers it for every term at once, which is why the tree
 *  costs one extra query on a post screen rather than one per folder.
 *
 *  Trashed and auto-draft posts are left out because they are not on the list
 *  screen either -- a folder reading 12 next to a list of 11 is the kind of small
 *  wrongness that makes somebody stop trusting the number.
 */

function vergeml_folder_counts( $taxonomy, $post_type ) {

    global $wpdb;

    $counts = array();

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a grouped count with no core API equivalent; cached below.

    $key    = 'vergeml_counts_' . $taxonomy . '_' . $post_type;
    $cached = wp_cache_get( $key, 'vergeml' );

    if ( false !== $cached ) {
        return (array) $cached;
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT tt.term_id AS term_id, COUNT(*) AS total
           FROM {$wpdb->term_relationships} tr
           JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
           JOIN {$wpdb->posts} p ON p.ID = tr.object_id
          WHERE tt.taxonomy = %s
            AND p.post_type = %s
            AND p.post_status NOT IN ( 'trash', 'auto-draft' )
          GROUP BY tt.term_id",
        $taxonomy,
        $post_type
    ) );

    // phpcs:enable

    foreach ( (array) $rows as $row ) {
        $counts[ (int) $row->term_id ] = (int) $row->total;
    }

    wp_cache_set( $key, $counts, 'vergeml', 30 );

    return $counts;
}


/**
 *  vergeml_folder_unfiled_count
 *
 *  Posts of one type holding no term in this taxonomy.
 */

function vergeml_folder_unfiled_count( $taxonomy, $post_type ) {

    global $wpdb;

    $key    = 'vergeml_unfiled_' . $taxonomy . '_' . $post_type;
    $cached = wp_cache_get( $key, 'vergeml' );

    if ( false !== $cached ) {
        return (int) $cached;
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a NOT EXISTS count with no core API equivalent; cached below.

    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*)
           FROM {$wpdb->posts} p
          WHERE p.post_type = %s
            AND p.post_status NOT IN ( 'trash', 'auto-draft' )
            AND NOT EXISTS (
                SELECT 1
                  FROM {$wpdb->term_relationships} tr
                  JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 WHERE tr.object_id = p.ID AND tt.taxonomy = %s
            )",
        $post_type,
        $taxonomy
    ) );

    // phpcs:enable

    wp_cache_set( $key, $count, 'vergeml', 30 );

    return $count;
}


/**
 *  Counts go stale the moment anything is filed, and the tree is usually the
 *  thing that filed it. Thirty seconds of cache is for the several reads inside
 *  one page load, not for the next one.
 */

add_action( 'set_object_terms', 'vergeml_folder_flush_counts', 10, 0 );
add_action( 'deleted_term_relationships', 'vergeml_folder_flush_counts', 10, 0 );

function vergeml_folder_flush_counts() {

    foreach ( vergeml_tree_taxonomies() as $taxonomy ) {

        wp_cache_delete( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );

        foreach ( vergeml_folder_post_types( $taxonomy ) as $type ) {
            wp_cache_delete( 'vergeml_counts_' . $taxonomy . '_' . $type, 'vergeml' );
            wp_cache_delete( 'vergeml_unfiled_' . $taxonomy . '_' . $type, 'vergeml' );
        }
    }
}


/**
 *  vergeml_folder_filter_posts
 *
 *  "Unfiled", on a post type's list screen.
 *
 *  Filtering by a folder needs nothing: the taxonomy has a query var and
 *  WordPress resolves it on edit.php the same way it does anywhere else. The
 *  absence of a folder is the part with no query var, because "no term in this
 *  taxonomy" is not a term.
 *
 *  The media library has spelled this `uncategorized=1` since long before the
 *  tree existed and keeps that spelling; a post screen has no such history, so
 *  it uses one of ours rather than borrowing a name that means something else
 *  there.
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a bookmarkable read-only filter, not a state change.

add_action( 'pre_get_posts', 'vergeml_folder_filter_posts' );

function vergeml_folder_filter_posts( $query ) {

    if ( ! is_admin() || ! $query->is_main_query() || empty( $_GET['vgml_unfiled'] ) ) {
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

    if ( ! $screen || 'edit' !== $screen->base ) {
        return;
    }

    $taxonomy = vergeml_folder_taxonomy_for( $screen->post_type );

    if ( ! $taxonomy ) {
        return;
    }

    $tax_query = (array) $query->get( 'tax_query' );

    $tax_query[] = array(
        'taxonomy' => $taxonomy,
        'operator' => 'NOT EXISTS',
    );

    $query->set( 'tax_query', $tax_query );

    // A folder and "no folder" cannot both be asked for; the later one wins.
    $query->set( $taxonomy, '' );
}

// phpcs:enable WordPress.Security.NonceVerification.Recommended


/**
 *  vergeml_folder_post_types_field
 *
 *  The control for choosing them, built to match the rows around it.
 *
 *  A multiple select rather than a row of checkboxes: the list of post types on a
 *  real site is as long as the plugins installed, and the settings panel it sits
 *  in is a narrow column of one-line rows.
 */

function vergeml_folder_post_types_field( $taxonomy ) {

    $chosen = vergeml_folder_post_types( $taxonomy );
    $types  = vergeml_folderable_post_types();

    if ( ! $types ) {
        return '<span class="description">' . esc_html__( 'No other post types are available.', 'vergelabs-media-library' ) . '</span>';
    }

    $name = 'vergeml_taxonomies[' . esc_attr( $taxonomy ) . '][post_types][]';

    $html = '<select multiple="multiple" size="' . esc_attr( min( 5, count( $types ) ) ) . '" class="vergeml-post_types" name="' . $name . '">';

    foreach ( $types as $type => $label ) {
        $html .= '<option value="' . esc_attr( $type ) . '"' . selected( true, in_array( $type, $chosen, true ), false ) . '>'
            . esc_html( $label ) . '</option>';
    }

    $html .= '</select>';

    return $html;
}


/**
 *  vergeml_folder_taxonomy_for
 *
 *  The media taxonomy to show on a given post type's screen, or '' for none.
 *
 *  One tree per screen. Several media taxonomies could be turned on for the same
 *  post type and the first one wins, because two folder trees down the side of
 *  one list is not an improvement on none.
 */

function vergeml_folder_taxonomy_for( $post_type ) {

    if ( ! $post_type || 'attachment' === $post_type ) {
        return '';
    }

    foreach ( vergeml_tree_taxonomies() as $taxonomy ) {

        if ( in_array( $post_type, vergeml_folder_post_types( $taxonomy ), true ) ) {
            return $taxonomy;
        }
    }

    return '';
}
