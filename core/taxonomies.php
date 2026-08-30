<?php

if ( ! defined( 'ABSPATH' ) )
    exit;



/**
 *  vergeml_taxonomies_validate
 *
 *  @type     callback function
 *  @since    1.0
 *  @created  28/09/13
 */

function vergeml_taxonomies_validate( $input ) {

    /*
     *  A form that did not carry the taxonomies must not erase them.
     *
     *  vergeml_taxonomies and vergeml_tax_options are registered in the same
     *  settings group, and the Media Taxonomies screen submits them from two
     *  separate forms. WordPress saves every option in a group whenever any form
     *  in it is posted, handing null to the options that were not there -- so
     *  saving the options box handed this function nothing, and it rebuilt every
     *  taxonomy from nothing.
     *
     *  What that looked like: tick one checkbox, press Save, and the folder tree
     *  is gone. Not the folders -- every term is still in the database -- but the
     *  taxonomy they hang from, unregistered, so no screen can show them. No
     *  message, nothing to undo, and no way to guess what happened.
     *
     *  Absent is not empty, and this is the difference.
     */
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the Settings API checked the nonce for this option group before calling us.
    if ( ! isset( $_POST['vergeml_taxonomies'] ) ) {
        $kept = get_option( 'vergeml_taxonomies', array() );
        return is_array( $kept ) ? $kept : array();
    }

    if ( ! $input ) $input = array();

    /*
     *  Anything this function does not write is carried over from what is stored.
     *
     *  It rebuilds each taxonomy from the input it was handed, which is right for
     *  the settings form -- an unticked checkbox submits nothing and has to come
     *  out as 0. It is wrong for every other caller. A write that does not happen
     *  to include the labels dropped them, and a media taxonomy with no labels is
     *  not registered at all: the taxonomy disappears, and with it the folder
     *  tree, the filters and the Media Categories screen. The terms are all still
     *  in the database and every folder is gone from the site, which is a very
     *  hard thing to work out from the outside.
     *
     *  Booleans are not carried over -- those are exactly what an empty checkbox
     *  means -- only keys this function never touches.
     */
    $stored = get_option( 'vergeml_taxonomies', array() );
    $stored = is_array( $stored ) ? $stored : array();

    foreach ( $input as $taxonomy => $params ) {

        $sanitized_taxonomy = sanitize_key( $taxonomy );

        if ( isset( $params['create_taxonomy'] ) ) {

            unset( $input[$taxonomy]['create_taxonomy'] );

            if ( taxonomy_exists( $sanitized_taxonomy ) ) {

                unset( $input[$taxonomy] );
                continue;
            }
        }


        if ( ! empty( $sanitized_taxonomy ) ) {

            $input[$sanitized_taxonomy] = $input[$taxonomy];
            unset( $input[$taxonomy] );
            $taxonomy = $sanitized_taxonomy;
        }
        else {
            unset( $input[$taxonomy] );
            continue;
        }


        $input[$taxonomy]['eml_media'] = isset( $params['eml_media'] ) && !! $params['eml_media'] ? 1 : 0;

        /*
         *  Only a form that carried the whole taxonomy may rewrite the whole taxonomy.
         *
         *  Everything in the branch below is a checkbox, and an absent checkbox means
         *  off -- correct when the box was on the form, catastrophic when it was not.
         *  The full editor marks itself; anything else keeps what is already stored.
         */
        if ( $input[$taxonomy]['eml_media'] && empty( $params['_full'] ) ) {
            foreach ( array( 'hierarchical', 'show_in_rest', 'sort', 'show_admin_column', 'rewrite', 'post_types', 'labels' ) as $keep ) {
                if ( isset( $stored[ $taxonomy ][ $keep ] ) ) {
                    $input[$taxonomy][ $keep ] = $stored[ $taxonomy ][ $keep ];
                }
            }
        }
        
        unset( $input[$taxonomy]['_full'] );
        
        if ( $input[$taxonomy]['eml_media'] && ! empty( $params['_full'] ) ) {
            $input[$taxonomy]['hierarchical'] = isset($params['hierarchical']) && !! $params['hierarchical'] ? 1 : 0;
            $input[$taxonomy]['show_in_rest'] = isset($params['show_in_rest']) && !! $params['show_in_rest'] ? 1 : 0;
            $input[$taxonomy]['sort'] = isset($params['sort']) && !! $params['sort'] ? 1 : 0;
            $input[$taxonomy]['show_admin_column'] = isset($params['show_admin_column']) && !! $params['show_admin_column'] ? 1 : 0;
            $input[$taxonomy]['rewrite']['with_front'] = isset($params['rewrite']['with_front']) && !! $params['rewrite']['with_front'] ? 1 : 0;
            $input[$taxonomy]['rewrite']['slug'] = isset($params['rewrite']['slug']) ? vergeml_sanitize_slug( $params['rewrite']['slug'], $taxonomy ) : '';

            /*
             *  The post types this taxonomy also applies to. Checked against what
             *  actually exists rather than stored as given: a post type can be
             *  removed with the plugin that registered it, and a stale name here
             *  would register the taxonomy for something that is not there.
             */
            $post_types = array();

            if ( isset( $params['post_types'] ) && is_array( $params['post_types'] ) ) {

                foreach ( $params['post_types'] as $post_type ) {

                    $post_type = sanitize_key( $post_type );

                    if ( $post_type && 'attachment' !== $post_type && post_type_exists( $post_type ) ) {
                        $post_types[] = $post_type;
                    }
                }
            }

            $input[$taxonomy]['post_types'] = array_values( array_unique( $post_types ) );
        }

        if ( ! $input[$taxonomy]['eml_media'] ) {
            $input[$taxonomy]['taxonomy_auto_assign'] = isset($params['taxonomy_auto_assign']) && !! $params['taxonomy_auto_assign'] ? 1 : 0;
        }


        $input[$taxonomy]['assigned'] = isset($params['assigned']) && !! $params['assigned'] ? 1 : 0;
        $input[$taxonomy]['admin_filter'] = isset($params['admin_filter']) && !! $params['admin_filter'] ? 1 : 0;
        $input[$taxonomy]['media_uploader_filter'] = isset($params['media_uploader_filter']) && !! $params['media_uploader_filter'] ? 1 : 0;
        $input[$taxonomy]['media_popup_taxonomy_edit'] = isset($params['media_popup_taxonomy_edit']) && !! $params['media_popup_taxonomy_edit'] ? 1 : 0;


        if ( isset( $params['labels'] ) ) {

            $default_labels = array(
                'menu_name' => $params['labels']['name'],
                'all_items' => 'All ' . $params['labels']['name'],
                'edit_item' => 'Edit ' . $params['labels']['singular_name'],
                'view_item' => 'View ' . $params['labels']['singular_name'],
                'update_item' => 'Update ' . $params['labels']['singular_name'],
                'add_new_item' => 'Add New ' . $params['labels']['singular_name'],
                'new_item_name' => 'New ' . $params['labels']['singular_name'] . ' Name',
                'parent_item' => 'Parent ' . $params['labels']['singular_name'],
                'search_items' => 'Search ' . $params['labels']['name']
            );

            foreach ( $params['labels'] as $label => $value ) {

                $input[$taxonomy]['labels'][$label] = sanitize_text_field($value);

                if ( empty($value) && isset($default_labels[$label]) )
                    $input[$taxonomy]['labels'][$label] = sanitize_text_field($default_labels[$label]);
            }
        }
    }

    /*
     *  Labels last: they are the one thing whose absence stops a taxonomy being
     *  registered, and the one thing a partial write is most likely to omit.
     */
    foreach ( $input as $taxonomy => $params ) {

        if ( empty( $params['labels'] ) && ! empty( $stored[ $taxonomy ]['labels'] ) ) {
            $input[ $taxonomy ]['labels'] = $stored[ $taxonomy ]['labels'];
        }
    }

    add_settings_error(
        'media-taxonomies',
        'eml_taxonomy_settings_saved',
        __('Folders and categories settings saved.', 'vergelabs-media-library'),
        'updated'
    );

    return $input;
}



/**
 *  vergeml_sanitize_slug
 *
 *  @since    2.0.4
 *  @created  07/02/15
 */

function vergeml_sanitize_slug( $slug, $fallback_slug = '' ) {

    $slug_array = explode ( '/', $slug );
    $slug_array = array_filter( $slug_array );
    $slug_array = array_map ( 'remove_accents', $slug_array );
    $slug_array = array_map ( 'sanitize_title_with_dashes', $slug_array );

    $slug = implode ( '/', $slug_array );

    if ( '' === $slug || false === $slug )
        $slug = $fallback_slug;

    return $slug;
}



/**
 *  vergeml_lib_options_validate
 *
 *  @since    2.2.1
 */

function vergeml_lib_options_validate( $input ) {

    foreach ( (array)$input as $key => $option ) {

        if (    'media_orderby' === $key || 
                'media_order' === $key || 
                'grid_caption_type' === $key
            ) {
            $input[$key] = sanitize_text_field( $option );

        }
        elseif ( 'filters_to_show' === $key ) {
            // 'ai' is the AI folder group in the tree panel, not a filter
            // dropdown. It lives here because this is where "which of these
            // do you want to see" is already answered -- and it has to be in
            // this allowlist or saving the settings page would quietly drop
            // it every time.
            $allowed = array( 'types', 'dates', 'authors', 'taxonomies', 'ai' );
            $input[$key] = array_values( array_intersect( $option, $allowed ) );
        }
        elseif ( 'search_in' === $key ) {
            $allowed = array( 'filenames', 'titles', 'captions', 'descriptions', 'authors', 'taxonomies' );
            $input[$key] = array_values( array_intersect( $option, $allowed ) );
        }
        elseif ( 'loads_per_page' === $key || 
                 'grid_sidebar_width' === $key || 
                 'ideal_column_width' === $key ||
                 'search_min_letters' === $key
            ) {
            $input[$key] = (int) $option;
        }
        else {
            $input[$key] = isset( $option ) && !! $option ? 1 : 0;
        }
    }

    if ( ! isset( $input['media_order'] ) ) {
        $input['media_order'] = 'ASC';
    }

    add_settings_error(
        'media-library',
        'eml_library_settings_saved',
        __('Media Library settings saved.', 'vergelabs-media-library'),
        'updated'
    );

    return $input;
}



/**
 *  vergeml_tax_options_validate
 *
 *  @type     callback function
 *  @since    2.0.4
 *  @created  28/01/15
 */

function vergeml_tax_options_validate( $input ) {

    // The same trap the other way round: saving the taxonomies form carries no
    // options, and rebuilding them from nothing switches every one of them off.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the Settings API checked the nonce for this option group before calling us.
    if ( ! isset( $_POST['vergeml_tax_options'] ) ) {
        $kept = get_option( 'vergeml_tax_options', array() );
        return is_array( $kept ) ? $kept : array();
    }

    foreach ( (array)$input as $key => $option ) {
        $input[$key] = isset( $option ) && !! $option ? 1 : 0;
    }

    return $input;
}



/**
 *  vergeml_ajax_query_attachments_args
 *
 *  @since    2.3.2
 *  @created  24/09/16
 */

add_filter( 'ajax_query_attachments_args', 'vergeml_ajax_query_attachments_args' );

/*
 *  Reads the filter values the media grid sent and turns them into query args.
 *  Nothing is written, so there is no nonce: this is the same request WordPress
 *  itself already authenticated for the attachments query, and a nonce would
 *  make the library unfilterable from any link or bookmark. The tax_query is
 *  the plugin's entire purpose, so the slow-query notice does not apply.
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.DB.SlowDBQuery.slow_db_query_tax_query

function vergeml_ajax_query_attachments_args( $query ) {

    $vergeml_taxonomies = get_option( 'vergeml_taxonomies', array() );
    $vergeml_lib_options = get_option( 'vergeml_lib_options' );
    $tax_query = array();
    $eml_query = isset( $_REQUEST['query'] ) ? map_deep( wp_unslash( (array) $_REQUEST['query'] ), 'sanitize_text_field' ) : array();
    $processed_taxonomies = get_object_taxonomies( 'attachment', 'object' );
    $keys = array(
        'uncategorized',
        'author'
    );


    foreach ( $processed_taxonomies as $taxonomy => $params ) {
        if ( isset( $eml_query[$taxonomy] ) ) {
            $keys[] = $taxonomy;
        }
    }


    $eml_query = array_intersect_key( $eml_query, array_flip( $keys ) );
    $query = array_merge( $query, $eml_query );

    $uncategorized = ( isset( $query['uncategorized'] ) && $query['uncategorized'] ) ? 1 : 0;


    foreach ( $processed_taxonomies as $taxonomy_name => $params ) {

        if ( ! isset( $vergeml_taxonomies[$taxonomy_name] ) ) {
            continue;
        }

        if ( $uncategorized ) {

            $tax_query[] = array(
                'taxonomy' => $taxonomy_name,
                'operator' => 'NOT EXISTS'
            );

            unset( $query['uncategorized'] );
        }
        else {

            if ( isset( $query[$taxonomy_name] ) && $query[$taxonomy_name] ) {

                if( is_numeric( $query[$taxonomy_name] ) || is_array( $query[$taxonomy_name] ) ) {

                    $field = ctype_digit( implode( '', (array) $query[$taxonomy_name] ) ) ? 'term_id' : 'slug';

                    $tax_query[] = array(
                        'taxonomy' => $taxonomy_name,
                        'field' => $field,
                        'terms' => (array) $query[$taxonomy_name],
                        'include_children' => (bool) $vergeml_lib_options['include_children']
                    );
                }
                else {

                    if ( 'in' === $query[$taxonomy_name] || 'not_in' === $query[$taxonomy_name] ) {

                        $operator = ( 'in' === $query[$taxonomy_name] ) ? 'EXISTS' : 'NOT EXISTS';

                        $tax_query[] = array(
                            'taxonomy' => $taxonomy_name,
                            'operator' => $operator
                        );
                    }
                    else {

                        $operator  = 'IN';

                        if ( str_contains( $query[$taxonomy_name], '+' ) ) {
                            $terms = explode( '+', $query[$taxonomy_name] );
                            $operator = 'AND';
                        }
                        else {
                            $terms = explode( ',', $query[$taxonomy_name] );
                        }

                        $field = ctype_digit( implode( '', $terms ) ) ? 'term_id' : 'slug';

                        $tax_query[] = array(
                            'taxonomy'         => $taxonomy_name,
                            'field'            => $field,
                            'terms'            => (array) $terms,
                            'operator'         => $operator,
                            'include_children' => (bool) $vergeml_lib_options['include_children']
                        );
                    }
                }

                unset( $query[$taxonomy_name] );
            }
        }

    } // endforeach

    if ( ! empty( $tax_query ) ) {

        $tax_query['relation'] = 'AND';
        $query['tax_query'] = $tax_query;
    }

    return $query;
}



/**
 *  vergeml_restrict_manage_posts
 *
 *  Adds taxonomy filters to Media Library List View
 *
 *  @since    1.0
 *  @created  11/08/13
 */

add_action( 'restrict_manage_posts', 'vergeml_restrict_manage_posts', 10, 2 );

// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.DB.SlowDBQuery.slow_db_query_tax_query

/*
 *  Draws the filter dropdowns above the media list table and marks the current
 *  selection. Read-only, so no nonce; requiring one would break every filtered
 *  URL people share or bookmark.
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended

function vergeml_restrict_manage_posts( $post_type, $which ) {

    global $current_screen,
           $wp_query;


    $media_library_mode = get_user_option( 'media_library_mode'  ) ? get_user_option( 'media_library_mode'  ) : 'grid';


    if ( ! isset( $current_screen ) || 'upload' !== $current_screen->base || 'list' !== $media_library_mode ) {
        return;
    }

    $vergeml_lib_options = get_option( 'vergeml_lib_options' );
    $vergeml_taxonomies = get_option( 'vergeml_taxonomies', array() );

    $uncategorized = ( isset( $_REQUEST['attachment-filter'] ) && 'uncategorized' === $_REQUEST['attachment-filter'] ) ? 1 : 0;


    if ( current_user_can( 'manage_options' ) && in_array( 'authors', $vergeml_lib_options['filters_to_show'] ) ) {

        echo "<label for='author' class='screen-reader-text'>" . esc_html__( 'Filter by author', 'vergelabs-media-library' ) . "</label>";

        wp_dropdown_users(
            array(
                'show_option_all'         => __( 'All Authors', 'vergelabs-media-library' ),
                'name'                    => 'author',
                'class'                   => 'attachment-filters',
                'capability'              => 'upload_files',
                'hide_if_only_one_author' => true
            )
        );
    }


    if ( in_array( 'taxonomies', $vergeml_lib_options['filters_to_show'] ) ) {

        foreach ( get_object_taxonomies( 'attachment', 'object' ) as $taxonomy ) {

            if ( ! (bool) $vergeml_taxonomies[$taxonomy->name]['admin_filter'] )
                continue;

            echo "<label for='" . esc_attr( $taxonomy->name ) . "' class='screen-reader-text'>" . esc_html__( 'Filter by', 'vergelabs-media-library' ) . ' ' . esc_html( $taxonomy->labels->name ) . "</label>";

            $selected = ( ! $uncategorized && isset( $wp_query->query[$taxonomy->name] ) ) ? $wp_query->query[$taxonomy->name] : 0;

            wp_dropdown_categories(
                array(
                    'show_option_all'    =>  __( 'Filter by', 'vergelabs-media-library' ) . ' ' . esc_html($taxonomy->labels->name),
                    'show_option_in'     =>  '— ' . __( 'All', 'vergelabs-media-library' ) . ' ' . esc_html($taxonomy->labels->name) . ' —',
                    'show_option_not_in' =>  '— ' . __( 'Not in', 'vergelabs-media-library' ) . ' ' . esc_html($taxonomy->labels->name) . ' —',
                    'taxonomy'           =>  $taxonomy->name,
                    'name'               =>  $taxonomy->name,
                    'orderby'            =>  'name',
                    'selected'           =>  $selected,
                    'hierarchical'       =>  true,
                    'show_count'         =>  (bool) $vergeml_lib_options['show_count'],
                    'hide_empty'         =>  false,
                    'hide_if_empty'      =>  true,
                    'class'              =>  'attachment-filters eml-taxonomy-filters',
                    'walker'             =>  new vergeml_Walker_CategoryDropdown()
                )
            );
        } // endforeach
    } // endif
}



/**
 *  vergeml_disable_months_dropdown
 *
 *  @since    2.6
 *  @created  07/03/18
 */

add_action( 'load-upload.php', 'vergeml_disable_months_dropdown' );

function vergeml_disable_months_dropdown() {

    $vergeml_lib_options = get_option( 'vergeml_lib_options' );

    if( isset( $vergeml_lib_options['filters_to_show'] ) &&
        ! in_array( 'dates', $vergeml_lib_options['filters_to_show'] ) ) {
        add_filter( 'disable_months_dropdown', '__return_true' );
    }
}



/**
 *  vergeml_dropdown_cats
 *
 *  Modifies taxonomy filters in Media Library List View
 *
 *  @since    2.0.4.5
 *  @created  19/04/15
 */

add_filter( 'wp_dropdown_cats', 'vergeml_dropdown_cats', 10, 2 );

function vergeml_dropdown_cats( $output, $r ) {

    global $current_screen;


    if ( ! is_admin() || empty( $output ) || ! isset( $current_screen ) ) {
        return $output;
    }


    $media_library_mode = get_user_option( 'media_library_mode' ) ? get_user_option( 'media_library_mode' ) : 'grid';


    if ( 'upload' !== $current_screen->base || 'list' !== $media_library_mode ) {
        return $output;
    }


    $whole_select = $output;
    $options_array = array();

    while ( strlen( $whole_select ) >= 7 && false !== ( $option_pos = strpos( $whole_select, '<option', 7 ) ) ) {

        $options_array[] = substr($whole_select, 0, $option_pos);
        $whole_select = substr($whole_select, $option_pos);
    }
    $options_array[] = $whole_select;

    if ( empty( $options_array ) )
        return $output;

    $new_output = '';

    if ( isset( $r['show_option_in'] ) && $r['show_option_in'] ) {

        $show_option_in = $r['show_option_in'];
        $selected = ( isset($r['selected']) && 'in' === strval($r['selected']) ) ? " selected='selected'" : '';
        $new_output .= "\t<option value='in'{$selected}>" . esc_html($show_option_in) . "</option>\n";
    }

    if ( isset( $r['show_option_not_in'] ) && $r['show_option_not_in'] ) {

        $show_option_not_in = $r['show_option_not_in'];
        $selected = ( isset($r['selected']) && 'not_in' === strval($r['selected']) ) ? " selected='selected'" : '';
        $new_output .= "\t<option value='not_in'{$selected}>" . esc_html($show_option_not_in) . "</option>\n";
    }

    array_splice( $options_array, 2, 0, $new_output );

    $output = implode('', $options_array);

    return $output;
}



/**
 *  vergeml_parse_tax_query
 *
 *  @since    2.6.4
 *  @created  23/05/18
 */

add_action( 'parse_tax_query', 'vergeml_parse_tax_query' );

function vergeml_parse_tax_query( $query ) {

    if ( is_admin() ) {
        return;
    }

    if ( ! $query->is_main_query() ) {
        return;
    }


    $vergeml_tax_options = get_option( 'vergeml_tax_options', array() );

    if ( (bool) $vergeml_tax_options['tax_archives'] ) {

        $vergeml_lib_options = get_option( 'vergeml_lib_options', array() );

        foreach ( get_option('vergeml_taxonomies', array() ) as $taxonomy => $params ) {

            if ( (bool) $params['assigned'] && (bool) $params['eml_media'] && is_tax( $taxonomy ) ) {

                $query->tax_query->queries[0]['include_children'] = (bool) $vergeml_lib_options['include_children'];
            }
        }
    }
}



/**
 *  vergeml_backend_parse_tax_query
 *
 *  @since    2.6.4
 *  @created  23/05/18
 */

add_action( 'parse_tax_query', 'vergeml_backend_parse_tax_query' );

// phpcs:enable WordPress.Security.NonceVerification.Recommended

/*
 *  Translates the filter values in the request into the tax_query for the media
 *  list table. Read-only and no nonce, for the same reason as the dropdowns
 *  above: these are shareable, bookmarkable filter URLs, not state changes.
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended

function vergeml_backend_parse_tax_query( $query ) {

    if ( ! is_admin() ) {
        return;
    }


    global $current_screen;

    if ( ! isset( $current_screen ) || 'upload' !== $current_screen->base ) {
        return;
    }

    if ( ! $query->is_main_query() ) {
        return;
    }


    $media_library_mode = get_user_option( 'media_library_mode' ) ? get_user_option( 'media_library_mode' ) : 'grid';

    if (  'list' !== $media_library_mode ) {
        return;
    }


    $uncategorized = ( isset( $_REQUEST['attachment-filter'] ) && 'uncategorized' === $_REQUEST['attachment-filter'] ) ? 1 : 0;
    $vergeml_lib_options = get_option( 'vergeml_lib_options' );


    if ( isset( $_REQUEST['category'] ) )
        $query->query['category'] = $query->query_vars['category'] = sanitize_text_field( wp_unslash( $_REQUEST['category'] ) );

    if ( isset( $_REQUEST['post_tag'] ) )
        $query->query['post_tag'] = $query->query_vars['post_tag'] = sanitize_text_field( wp_unslash( $_REQUEST['post_tag'] ) );

    if ( isset( $query->query_vars['taxonomy'] ) && isset( $query->query_vars['term'] ) ) {

        $tax = $query->query_vars['taxonomy'];
        $term = get_term_by( 'slug', $query->query_vars['term'], $tax );

        if ( $term ) {

            $query->query_vars[$tax] = $term->term_id;
            $query->query[$tax] = $term->term_id;

            unset( $query->query_vars['taxonomy'] );
            unset( $query->query_vars['term'] );

            unset( $query->query['taxonomy'] );
            unset( $query->query['term'] );
        }
    }


    /*
     *  One filter value, four ways of arriving at it.
     *
     *  A taxonomy query var can reach this screen as a slug (`?media_category=
     *  folder-141`, which is what a link and the folder tree produce) or as a term
     *  id (`?media_category=143`, which is what the filter dropdown submits and
     *  what core's own term-count links use). Both have to mean the same thing.
     *
     *  They did not. Whether the id form worked depended on `filter_action` -- the
     *  name of the Filter button -- being in the URL, because that was what chose
     *  between the two branches that used to be here. So the dropdown worked, and
     *  the identical URL with the button's name stripped off, which is what a
     *  bookmark or a shared link is, came back with an empty library: core resolves
     *  the query var by slug, found no term called "143", and filtered everything
     *  out. It looked like the media had gone.
     *
     *  Resolved once here instead, slug first and then id, and the query var is
     *  normalised to the term id afterwards so the dropdown shows the folder that
     *  is actually being filtered on however the screen was reached.
     */
    foreach ( get_object_taxonomies( 'attachment','names' ) as $taxonomy ) {

        if ( $uncategorized ) {

            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'operator' => 'NOT EXISTS'
            );

            unset( $query->query[$taxonomy] );
            unset( $query->query_vars[$taxonomy] );

            continue;
        }

        $raw = '';

        if ( isset( $_REQUEST[ $taxonomy ] ) ) {
            $raw = sanitize_text_field( wp_unslash( $_REQUEST[ $taxonomy ] ) );
        } elseif ( isset( $query->query[ $taxonomy ] ) ) {
            $raw = (string) $query->query[ $taxonomy ];
        }

        if ( '' === $raw || '0' === $raw ) {
            continue;
        }

        // "Any" and "none", which the dropdown offers above the folders.
        if ( 'in' === $raw || 'not_in' === $raw ) {

            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'operator' => ( 'in' === $raw ) ? 'EXISTS' : 'NOT EXISTS'
            );

            continue;
        }

        $term = get_term_by( 'slug', $raw, $taxonomy );

        if ( ! $term && is_numeric( $raw ) ) {
            $found = get_term( (int) $raw, $taxonomy );
            $term  = ( $found instanceof WP_Term ) ? $found : false;
        }

        if ( ! $term ) {
            continue;
        }

        $tax_query[] = array(
            'taxonomy' => $taxonomy,
            'field' => 'term_id',
            'terms' => array( $term->term_id ),
            'include_children' => (bool) $vergeml_lib_options['include_children']
        );

        // The dropdown's options carry term ids, so this is what makes it show
        // the folder as selected when the URL named it by slug.
        $query->query_vars[$taxonomy] = $term->term_id;
        $query->query[$taxonomy] = $term->term_id;

    } // endforeach

    if ( ! empty( $tax_query ) ) {
        $query->tax_query = new WP_Tax_Query( $tax_query );
    }
}



/**
 *  vergeml_attachment_fields_to_edit
 *
 *  Based on /wp-admin/includes/media.php
 *
 *  @since    1.0
 *  @created  14/08/13
 */

add_filter( 'attachment_fields_to_edit', 'vergeml_attachment_fields_to_edit', 10, 2 );

function vergeml_attachment_fields_to_edit( $form_fields, $post ) {

    global $pagenow;
    

    // a workaround to handle media taxonomies for js only
    if ( 'post.php' === $pagenow ) {
        return $form_fields;
    }

    if ( ! function_exists( 'wp_terms_checklist' ) ) {
        return $form_fields;
    }


    $vergeml_tax_options = get_option( 'vergeml_tax_options' );

    foreach( get_taxonomies_for_attachments() as $taxonomy ) {

        $t = (array) get_taxonomy($taxonomy);
        if ( ! $t['show_ui'] )
            continue;
        if ( empty($t['label']) )
            $t['label'] = $taxonomy;
        if ( empty($t['args']) )
            $t['args'] = array();

        if ( (bool) $vergeml_tax_options['edit_all_as_hierarchical'] || (bool) $t['hierarchical'] ) {

            ob_start();

                wp_terms_checklist( $post->ID, array( 'taxonomy' => $taxonomy, 'checked_ontop' => false, 'walker' => new Walker_Media_Taxonomy_Checklist() ) );

                if ( ob_get_contents() != false ) {
                    $html = '<ul class="term-list">' . ob_get_contents() . '</ul>';
                }
                else {

                    $not_found = sprintf(
                        /* translators: %s: name of a taxonomy, for example "Media Categories" */
                        esc_html__( 'No %s found.', 'vergelabs-media-library' ),
                        esc_html( $t['label'] )
                    );
                    $html = '<ul class="term-list"><li>' . $not_found . ' <a href="' . esc_url( admin_url( '/edit-tags.php?taxonomy=' . $taxonomy . '&post_type=attachment' ) ) . '">' . esc_html__( 'Add some', 'vergelabs-media-library' ) . '.</a></li></ul>';
                }

            ob_end_clean();

            $t['input'] = 'html';
            $t['html'] = $html;
        }
        else {
            $values = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
            $t['value'] = join(', ', $values);
        } // if

        $t['taxonomy'] = true;
        // get rid of a current instance of the tax_field if exists
        unset( $form_fields[$taxonomy] ); 

        // re-order the tax_field to the end
        $form_fields = $form_fields + array( $taxonomy => $t );
    } // foreach

    return $form_fields;
}



/**
 *  vergeml_Walker_CategoryDropdown
 *
 *  Based on /wp-includes/class-walker-category-dropdown.php
 *
 *  @since    2.3
 *  @created  14/06/16
 */

class vergeml_Walker_CategoryDropdown extends Walker_CategoryDropdown {

    function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {

        $vergeml_lib_options = get_option( 'vergeml_lib_options' );

        $pad = str_repeat('&nbsp;', $depth * 3);

        /** This filter is documented in wp-includes/category-template.php */
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter, applied so term names render the way core does.
        $cat_name = apply_filters( 'list_cats', $category->name, $category );

        if ( isset( $args['value_field'] ) && isset( $category->{$args['value_field']} ) ) {
            $value_field = $args['value_field'];
        } else {
            $value_field = 'term_id';
        }

        $output .= "\t<option class=\"level-$depth\" value=\"" . esc_attr( $category->{$value_field} ) . "\"";

        // Type-juggling causes false matches, so we force everything to a string.
        if ( (string) $category->{$value_field} === (string) $args['selected'] )
            $output .= ' selected="selected"';
        $output .= '>';
        $output .= $pad.$cat_name;


        if ( $args['show_count'] && (bool) $vergeml_lib_options['show_count'] ) {

            $count = vergeml_get_media_term_count( $category->term_id, $category->term_taxonomy_id );
            $output .= '&nbsp;&nbsp;('. number_format_i18n( $count ) .')';
        }

        $output .= "</option>\n";
    }
}



/**
 *  vergeml_get_media_term_count
 *
 *  @since    2.3
 *  @created  14/06/16
 */

/*
 *  Counts attachments in a term, optionally including child terms. There is no
 *  core API for this: get_term() returns a count for all post types, and this
 *  needs attachments only, with the child rollup the plugin's filters promise.
 *  Table names come from $wpdb and every value is placeheld. The result is
 *  deliberately uncached because it is read while the counts are being changed,
 *  and a stale number is worse than the query.
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

function vergeml_get_media_term_count( $term_id, $tt_id ) {

    global $wpdb;


    $terms = array( $tt_id );
    $children = array();
    $vergeml_lib_options = get_option( 'vergeml_lib_options' );


    if ( (bool) $vergeml_lib_options['include_children'] ) {
        $children = $wpdb->get_results( $wpdb->prepare( "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy WHERE parent = %d", (int) $term_id ) );
    }


    if ( ! empty( $children ) ) {

        foreach ( $children as $child ) {
            $terms[] = $child->term_taxonomy_id;
        }
    }

    $terms_format = join( ', ', array_fill( 0, count( $terms ), '%d' ) );

    $results = $wpdb->get_results( $wpdb->prepare(
        "
            SELECT ID FROM $wpdb->posts, $wpdb->term_relationships WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_type = 'attachment' AND ( post_status = 'publish' OR post_status = 'inherit' ) AND term_taxonomy_id IN ($terms_format) GROUP BY ID
        ",
        $terms
    ) );

    $count = $results ? $wpdb->num_rows : 0;
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

    return $count;
}



/**
 *  Walker_Media_Taxonomy_Checklist
 *
 *  Based on /wp-includes/category-template.php
 *
 *  @since    1.0
 *  @created  09/09/13
 */

if ( ! class_exists( 'Walker_Media_Taxonomy_Checklist' ) ) {

    class Walker_Media_Taxonomy_Checklist extends Walker {

        var $tree_type = 'category';
        var $db_fields = array ('parent' => 'parent', 'id' => 'term_id');

        function start_lvl( &$output, $depth = 0, $args = array() ) {

            $indent = str_repeat("\t", $depth);
            $output .= "$indent<ul class='children'>\n";
        }

        function end_lvl( &$output, $depth = 0, $args = array() ) {

            $indent = str_repeat("\t", $depth);
            $output .= "$indent</ul>\n";
        }

        function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {

            extract($args);

            if ( empty($taxonomy) )
                $taxonomy = 'category';

            $class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter, applied so term names render the way core does.
            $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . "<label class='selectit'><input value='0' type='hidden' name='tax_input[{$taxonomy}][{$category->term_id}]' /><input value='1' type='checkbox' name='tax_input[{$taxonomy}][{$category->term_id}]' id='in-{$taxonomy}-{$category->term_id}'" . checked( in_array( $category->term_id, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . " />" . esc_html( apply_filters('the_category', $category->name )) . "</label>";
        }

        function end_el( &$output, $category, $depth = 0, $args = array() ) {

            $output .= "</li>\n";
        }
    }
}



/**
 *  Walker_Media_Taxonomy_Uploader_Filter
 *
 *  Based on /wp-includes/category-template.php
 *
 *  @since    1.0.1
 *  @created  05/11/13
 */

if ( ! class_exists( 'Walker_Media_Taxonomy_Uploader_Filter' ) ) {

    class Walker_Media_Taxonomy_Uploader_Filter extends Walker {

        var $tree_type = 'category';
        var $db_fields = array ('parent' => 'parent', 'id' => 'term_id');


        function start_lvl( &$output, $depth = 0, $args = array() ) {

            $output .= "";
        }

        function end_lvl( &$output, $depth = 0, $args = array() ) {

            $output .= "";
        }

        function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {

            extract($args);

            $vergeml_lib_options = get_option( 'vergeml_lib_options' );
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);

            $count = ( (bool) $vergeml_lib_options['show_count'] ) ? '&nbsp;&nbsp;('. number_format_i18n( vergeml_get_media_term_count( $category->term_id, $category->term_taxonomy_id ) ) .')' : '';

            $el = array(
                'term_id' => intval( $category->term_id ),
                'slug' => $category->slug,
                // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter, applied so term names render the way core does.
                'term_name' => esc_html( apply_filters( 'the_category', $category->name ) ),
                'term_row' => $indent . esc_html( apply_filters( 'the_category', $category->name ) ) . $count
                // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            );

            $output .= json_encode( $el );
        }

        function end_el( &$output, $category, $depth = 0, $args = array() ) {

                $output .= "";
        }
    }
}



/**
 *  vergeml_save_attachment_compat
 *
 *  Based on /wp-admin/includes/ajax-actions.php
 *
 *  @since    1.0.6
 *  @created  06/14/14
 */

add_action( 'wp_ajax_save-attachment-compat', 'vergeml_save_attachment_compat', 0 );

function vergeml_save_attachment_compat() {

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only the id, and only to build the nonce action checked immediately below.
    $id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;

    if ( ! $id )
        wp_send_json_error();

    /*
     *  Verify before reading anything else. Upstream checked the nonce after
     *  pulling the submitted fields out of the request; nothing was exploitable
     *  because the write still came later, but there is no reason to touch the
     *  payload of an unverified request at all.
     */

    check_ajax_referer( 'update-post_' . $id, 'nonce' );

    if ( empty( $_REQUEST['attachments'] ) || empty( $_REQUEST['attachments'][ $id ] ) )
        wp_send_json_error();


    $vergeml_lib_options = get_option( 'vergeml_lib_options' );

    /*
     *  Left slashed and unsanitised on purpose, exactly as core's own
     *  wp_ajax_save_attachment_compat does. This is attachment field content --
     *  title, caption, description -- which is handed to the
     *  attachment_fields_to_save filter and then to wp_update_post, both of
     *  which expect slashed input and do the sanitising themselves. Cleaning it
     *  here would double-unslash and strip markup people are allowed to save.
     */

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- see above; sanitised downstream by wp_update_post.
    $attachment_data = $_REQUEST['attachments'][ $id ];

    if ( ! current_user_can( 'edit_post', $id ) )
        wp_send_json_error();

    $post = get_post( $id, ARRAY_A );

    if ( 'attachment' != $post['post_type'] )
        wp_send_json_error();

    /** This filter is documented in wp-admin/includes/media.php */
    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter; this handler stands in for core's, so it must run the same filter.
    $post = apply_filters( 'attachment_fields_to_save', $post, $attachment_data );
    // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

    if ( isset( $post['errors'] ) ) {

        $errors = $post['errors']; // @todo return me and display me!
        unset( $post['errors'] );
    }

    wp_update_post( $post );


    $media_taxonomy_names = get_object_taxonomies( 'attachment','names' );

    if ( (bool) $vergeml_lib_options['show_count'] ) {

        $terms = get_terms( array( 'taxonomy' => $media_taxonomy_names, 'fields' => 'all', 'get' => 'all' ) );
        $term_pairs = vergeml_get_media_term_pairs( $terms, 'id=>tt_id' );
    }


    foreach ( $media_taxonomy_names as $taxonomy ) {

        if ( isset( $attachment_data[ $taxonomy ] ) ) {

            $term_ids = array_map( 'trim', preg_split( '/,+/', $attachment_data[ $taxonomy ] ) );
        }
        elseif ( isset( $_REQUEST['tax_input'] ) ) {

            if ( ! isset( $_REQUEST['tax_input'][ $taxonomy ] ) ) {
                continue;
            }
            else {
                $term_ids = array_keys( $_REQUEST['tax_input'][ $taxonomy ], 1 );
                $term_ids = array_map( 'intval', $term_ids );
            }
        }

        wp_set_object_terms( $id, $term_ids, $taxonomy, false );

        if ( (bool) $vergeml_lib_options['show_count'] ) {

            foreach( $term_pairs as $term_id => $tt_id) {
                $tcount[$term_id] = vergeml_get_media_term_count( $term_id, $tt_id );
            }
        }
    }

    if ( ! $attachment = wp_prepare_attachment_for_js( $id ) )
        wp_send_json_error();

    if ( (bool) $vergeml_lib_options['show_count'] )
        $attachment['tcount'] = $tcount;


    wp_send_json_success( $attachment );
}



/**
 *  vergeml_delete_post
 *
 *  Based on /wp-admin/includes/ajax-actions.php
 *
 *  @since    2.3
 *  @created  17/06/16
 */

add_action( 'wp_ajax_delete-post', 'vergeml_delete_post', 0 );

function vergeml_delete_post() {

    if ( empty( $action ) )
        $action = 'delete-post';

    $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

    check_ajax_referer( "{$action}_$id" );

    if ( ! current_user_can( 'delete_post', $id ) )
        wp_die( -1 );

    if ( ! $post = get_post( $id ) )
        wp_die( 1 );


    if ( 'attachment' === $post->post_type ) {

        $response = array();
        $vergeml_lib_options = get_option('vergeml_lib_options');

        if ( wp_delete_post( $id ) ) {

            if ( (bool) $vergeml_lib_options['show_count'] ) {

                $terms = get_terms( array( 'taxonomy' => get_object_taxonomies( 'attachment', 'names' ), 'fields' => 'all', 'get' => 'all' ) );

                foreach( vergeml_get_media_term_pairs( $terms, 'id=>tt_id' ) as $term_id => $tt_id ) {
                    $response['tcount'][$term_id] = vergeml_get_media_term_count( $term_id, $tt_id );
                }
            }

            wp_send_json_success( $response );
        }
        else
            wp_send_json_error();
    }
    elseif ( wp_delete_post( $id ) )
        wp_die( 1 );
    else
        wp_die( 0 );
}



/**
 *  vergeml_save_attachment_order
 *
 *  Based on /wp-admin/includes/ajax-actions.php
 *
 *  @since    2.2
 *  @created  11/02/16
 */

add_action( 'wp_ajax_save-attachment-order', 'vergeml_save_attachment_order', 0 );

// phpcs:enable WordPress.Security.NonceVerification.Recommended

function vergeml_save_attachment_order() {

    global $wpdb;


    /*
     *  post_id has to be read before the nonce, because it is what picks which
     *  nonce action applies: attached media is verified against that post,
     *  unattached media against the bulk edit nonce. Nothing is read beyond the
     *  id itself until one of those checks has passed.
     */

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- the id below selects which nonce action to verify; both branches verify before doing anything.
    if ( ! isset( $_REQUEST['post_id'] ) )
        wp_send_json_error();

    if ( empty( $_REQUEST['attachments'] ) )
        wp_send_json_error();

    $post_id = absint( $_REQUEST['post_id'] );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $post_id ) {

        check_ajax_referer( 'update-post_' . $post_id, 'nonce' );

        if ( ! current_user_can( 'edit_post', $post_id ) )
            wp_send_json_error();
    }
    else {
        check_ajax_referer( 'eml-bulk-edit-nonce', 'nonce' );
    }


    // this payload is only ever attachment id => menu order position
    $attachments = map_deep( wp_unslash( (array) $_REQUEST['attachments'] ), 'intval' );
    $attachments2edit = array();

    foreach ( $attachments as $attachment_id => $menu_order ) {

        $attachment_id = absint( $attachment_id );

        if ( ! current_user_can( 'edit_post', $attachment_id ) )
            continue;
        if ( ! $attachment = get_post( $attachment_id ) )
            continue;
        if ( 'attachment' != $attachment->post_type )
            continue;

        $attachments2edit[$attachment_id] = $menu_order;
    }


    asort( $attachments2edit );
    $order = array_keys( $attachments2edit );
    $order_format = join( ', ', array_fill( 0, count( $order ), '%d' ) );

    /*
     *  Renumbering menu_order across a whole drag-and-drop reorder in one
     *  statement. Doing it through wp_update_post would be one full post save
     *  per item, which on a few hundred images is the difference between
     *  instant and unusable. The MySQL counter variable is why it cannot be
     *  expressed with the posts API. Ids are placeheld; table names are $wpdb's.
     */

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    $wpdb->query( 'SELECT @i:=0' );

    $result = $wpdb->query( $wpdb->prepare(
        "
            UPDATE $wpdb->posts SET $wpdb->posts.menu_order = ( @i:=@i+1 )
            WHERE $wpdb->posts.ID IN ( $order_format ) ORDER BY FIELD( $wpdb->posts.ID, $order_format )
        ",
        array_merge( $order, $order )
    ) );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

    /*
     *  The rows were changed underneath the object cache, so drop each post
     *  from it or the old menu_order is served until something else evicts it.
     */

    foreach ( $order as $reordered_id ) {
        clean_post_cache( $reordered_id );
    }


    if ( ! $result )
        wp_send_json_error();

    wp_send_json_success();
}



/**
 *  vergeml_get_eml_taxonomies
 *
 *  @since    2.2
 *  @created  13/03/16
 */

function vergeml_get_eml_taxonomies( $all_media_taxonomies = array() ) {

    if ( empty( $all_media_taxonomies ) )
        $all_media_taxonomies = get_option( 'vergeml_taxonomies', array() );

    $return = array_filter( $all_media_taxonomies, 'vergeml_filter_by_eml_taxonomies' );

    return $return;
}



/**
 *  vergeml_filter_by_eml_taxonomies
 *
 *  @since    2.2
 *  @created  13/03/16
 */

function vergeml_filter_by_eml_taxonomies( $taxonomy ) {

    return (bool) $taxonomy['eml_media'];
}



/**
 *  vergeml_get_media_term_pairs
 *
 *  @since    2.3
 *  @created  19/06/16
 */

function vergeml_get_media_term_pairs( $terms = array(), $mode = 'id=>tt_id' ) {

    $result = array();


    foreach( $terms as $term ) {

        if ( ! is_object( $term ) ) {
            continue;
        }

        if ( 'id=>tt_id' === $mode )
            $result[$term->term_id] = $term->term_taxonomy_id;

        if ( 'tt_id=>id' === $mode )
            $result[$term->term_taxonomy_id] = $term->term_id;

        if ( 'id=>name' === $mode )
            $result[$term->term_id] = $term->name;
    }

    return $result;
}



/**
 *  vergeml_update_attachment_term_count
 *
 *  @since    2.3
 *  @created  22/06/16
 */

/*
 *  Both functions below are registered as a taxonomy's update_count_callback,
 *  so they stand in for core's own and have to behave the same way: recount
 *  from the tables, fire core's term_taxonomy hooks around the write, and skip
 *  the object cache, since the whole point is to replace the number that is
 *  cached. The hooks are core's, not ours, and must keep their own names.
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

function vergeml_update_attachment_term_count( $terms, $taxonomy ) {

    global $wpdb;

    foreach ( (array) $terms as $term ) {

        $count = 0;

        $count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts p1 WHERE p1.ID = $wpdb->term_relationships.object_id AND post_type = 'attachment' AND ( post_status = 'publish' OR post_status = 'inherit' ) AND term_taxonomy_id = %d", $term ) );

        /*
         *  These are core's term-count hooks, fired around core's own
         *  term_taxonomy table write, exactly as core's count callbacks do.
         *  This function is registered as a taxonomy's update_count_callback,
         *  so it stands in for core's version and has to behave like it.
         */

        do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
        $wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
        do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
    }
}



/**
 *  vergeml_update_post_term_count
 *
 *  @since    2.3
 *  @created  22/06/16
 */

function vergeml_update_post_term_count( $terms, $taxonomy ) {

    global $wpdb;

    $object_types = (array) $taxonomy->object_type;

    foreach ( $object_types as &$object_type )
        list( $object_type ) = explode( ':', $object_type );

    $object_types = array_unique( $object_types );

    if ( false !== ( $check_attachments = array_search( 'attachment', $object_types ) ) )
        unset( $object_types[ $check_attachments ] );

    if ( $object_types )
        $object_types = array_values( array_filter( $object_types, 'post_type_exists' ) );

    foreach ( (array) $terms as $term ) {

        $count = 0;

        if ( $object_types ) {

            /*
             *  Post types are placeheld rather than quoted into the string, so
             *  there is nothing left to escape by hand. Table names come from
             *  $wpdb and are not input, which is why they stay interpolated.
             */

            $placeholders = implode( ', ', array_fill( 0, count( $object_types ), '%s' ) );

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are from $wpdb, every value is placeheld.
            $count += (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_status = 'publish' AND post_type IN ( $placeholders ) AND term_taxonomy_id = %d",
                    array_merge( $object_types, array( $term ) )
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        /*
         *  These are core's term-count hooks, fired around core's own
         *  term_taxonomy table write, exactly as core's count callbacks do.
         *  This function is registered as a taxonomy's update_count_callback,
         *  so it stands in for core's version and has to behave like it.
         */

        do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
        $wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
        do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
    }
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound



// TODO: Quick Edit for the List mode (MediaFrame.EditAttachments)
// add_filter( 'media_row_actions', 'vergeml_media_row_actions', 10, 2 );
//
// if ( ! function_exists( 'vergeml_media_row_actions' ) ) {
//
//     function vergeml_media_row_actions( $actions, $post ) {
//
//         $first = array_splice ( $actions, 0, 1 );
//         $actions = array_merge ( $first, array( 'eml_quick_edit' => '<a href="#" data-attachment-id="' . $post->ID . '">Quick Edit</a>' ), $actions );
//
//         return $actions;
//     }
// }



/**
 *  vergeml_the_posts
 *
 *  Natural sort order for titles (List Mode)
 *
 *  @since    2.5
 *  @created  12/01/18
 */

add_filter( 'the_posts', 'vergeml_the_posts', 10, 2 );

function vergeml_the_posts( $posts, $query ) {

    $vergeml_lib_options = get_option('vergeml_lib_options');


    if ( ! (bool) $vergeml_lib_options['natural_sort'] ||
         ! isset($query->query_vars['orderby']) ||
         'title' !== $query->query_vars['orderby'] ||
         'attachment' !== $query->query_vars['post_type'] ) {

        return $posts;
    }


    usort( $posts, 'vergeml_cmp' );

    if ( "desc" === strtolower( $query->query_vars['order'] ) ) {
        $posts = array_reverse( $posts );
    }

    return $posts;
}



/**
 *  vergeml_cmp
 *
 *  Apply natural compare to post titles
 *
 *  @since    2.7
 *  @created  15/06/18
 */

function vergeml_cmp( $a, $b ) {

    return strnatcmp( $a->post_title, $b->post_title );
}



/**
 *  vergeml_pre_get_posts
 *
 *  Taxonomy archive specific query (front-end)
 *  Ensure correct items order
 *
 *  @since    1.0
 *  @created  03/08/13
 */

add_action( 'pre_get_posts', 'vergeml_pre_get_posts', 99 );

function vergeml_pre_get_posts( $query ) {

    global $current_screen;


    if ( ! $query->is_main_query() ) {
        return;
    }

    if ( is_admin() ) {
        $media_library_mode = get_user_option( 'media_library_mode'  ) ? get_user_option( 'media_library_mode'  ) : 'grid';
    }

    if ( is_admin() && ! ( isset( $current_screen ) && 'upload' === $current_screen->base && 'list' === $media_library_mode ) ) {
        return;
    }


    // front-end only
    if ( ! is_admin() ) {

        $vergeml_tax_options = get_option( 'vergeml_tax_options', array() );
        $vergeml_taxonomies = get_option( 'vergeml_taxonomies', array() );

        foreach ( (array) $vergeml_taxonomies as $taxonomy => $params ) {

            if ( (bool) $params['assigned'] && (bool) $params['eml_media'] && is_tax( $taxonomy ) ) {

                if ( (bool) $vergeml_tax_options['tax_archives'] ) {
                    $query->set( 'post_type', 'attachment' );
                    $query->set( 'post_status', 'inherit' );
                }
                else {
                    $query->set_404();
                }
            }
        }
    }


    // both front-end and back-end
    if ( 'attachment' !== $query->get('post_type') ) {
        return;
    }


    $query_orderby = $query->get('orderby');
    $query_order = $query->get('order');

    if ( $query_orderby && $query_order ) {
        return;
    }


    $vergeml_lib_options = get_option( 'vergeml_lib_options', array() );

    $orderby = ( 'menuOrder' === $vergeml_lib_options['media_orderby'] ) ? 'menu_order' : esc_attr( $vergeml_lib_options['media_orderby'] );
    $order = esc_attr( $vergeml_lib_options['media_order'] );

    $query->set('orderby', $orderby );
    $query->set('order', $order );
}
