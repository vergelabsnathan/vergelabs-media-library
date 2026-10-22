<?php

if ( ! defined( 'ABSPATH' ) )
    exit;



add_filter( 'shortcode_atts_gallery', 'vergeml_shortcode_atts', 10, 4 );
add_filter( 'shortcode_atts_playlist', 'vergeml_shortcode_atts', 10, 4 );
add_filter( 'shortcode_atts_slideshow', 'vergeml_shortcode_atts', 10, 4 );



/**
 *  vergeml_shortcode_atts
 *
 *  @since    2.1.6
 *  @created  19/01/16
 */

function vergeml_shortcode_atts( $output, $defaults = array(), $atts = array(), $shortcode = '' ) {

    $vergeml_lib_options = get_option( 'vergeml_lib_options', array() );


    // 3rd-party plugins can send string as a 3rd param
    if ( is_string( $atts ) || empty( $atts ) ) {
        // $shortcode = $atts;  // at the moment we do not use $shortcode anywhere
        $atts = $output;
    }


    $custom_query = false;
    $id = isset( $atts['id'] ) ? intval( $atts['id'] ) : 0;
    unset( $atts['id'] );
    unset( $defaults['id'] );


    // 3rd-party plugin sends null
    if ( $defaults ) {
        $atts = array_merge( $defaults, $atts );
    }


    if ( ! empty( $atts['monthnum'] ) && ! empty( $atts['year'] ) ) {
        $custom_query = true;
    }


    $tax_query = array();
    $operator  = 'IN';

    // A gallery written before the folders moved to vergeml_folder still names them the old way.
    if ( ! empty( $atts['media_category'] ) && empty( $atts['vergeml_folder'] ) && taxonomy_exists( 'vergeml_folder' ) ) {
        $atts['vergeml_folder'] = $atts['media_category'];
    }

    foreach ( get_taxonomies_for_attachments( 'names' ) as $taxonomy ) {

        if ( ! empty( $atts[$taxonomy] ) ) {

            if ( false !== strpos( $atts[$taxonomy], '+' ) ) {
                $terms = explode( '+', $atts[$taxonomy] );
                $operator = 'AND';
            }
            else {
                $terms = explode( ',', $atts[$taxonomy] );
            }

            $field = preg_match( '/^\d+$/', implode( '', $terms ) ) ? 'term_id' : 'slug';

            $tax_query[] = array(
                'taxonomy'         => $taxonomy,
                'field'            => $field,
                'terms'            => $terms,
                'operator'         => $operator,
                'include_children' => (bool) $vergeml_lib_options['include_children']
            );

            unset( $atts[$taxonomy] );
            $custom_query = true;
        }
    }


    if ( empty( $atts['ids'] ) || $custom_query ) {

        if ( empty( $atts['orderby'] ) || 'post__in' === $atts['orderby'] ) {
            $output['orderby'] = $atts['orderby'] = vergeml_lib_orderby( $vergeml_lib_options );
        }

        if ( empty( $atts['order'] ) ) {
            $output['order'] = $atts['order'] = vergeml_lib_order( $vergeml_lib_options );
        }
    }


    if ( ! $custom_query ) {
        return $output;
    }


    $mime_type_valuemap = array(
        'pdf' => 'application/pdf',
        'image' => 'image',
        'audio' => 'audio',
        'video' => 'video'
    );

    if ( ! empty( $atts['type'] ) ) {
        $mime_type = $atts['type'];
    }
    else {
        $mime_type = 'image';
    }

    $mime_type = isset( $mime_type_valuemap[$mime_type] ) ? $mime_type_valuemap[$mime_type] : 'image';

    $posts_per_page = isset( $atts['limit'] ) ? intval( $atts['limit'] ) : -1;
    unset( $atts['limit'] );

    $query = array(
        'post_status' => 'inherit',
        'post_type' => 'attachment',
        'post_mime_type' => $mime_type,
        'order' => $atts['order'],
        'orderby' => $atts['orderby'],
        'posts_per_page' => $posts_per_page,
    );


    if ( ! empty( $atts['monthnum'] ) && ! empty( $atts['year'] ) ) {

        $query['monthnum'] = $atts['monthnum'];
        $query['year'] = $atts['year'];

        unset( $atts['monthnum'] );
        unset( $atts['year'] );
    }


    if ( ! empty( $tax_query ) ) {

        $tax_query['relation'] = 'AND';
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- filtering a gallery by media taxonomy is the feature; there is no other way to express it.
        $query['tax_query'] = $tax_query;
    }

    if ( $id ) {
        $query['post_parent'] = $id;
    }


    $get_posts = new WP_Query;
    $attachments = $get_posts->query($query);


    $ids = array();

    foreach ( $attachments as $attachment ) {
        $ids[] = $attachment->ID;
    }


    $output = $atts;
    $output['id'] = $id; // shortcodes require it!


    if ( $ids ) {
        $output['ids'] = $output['include'] = implode( ',', $ids );
        $output['orderby'] = ( 'title' === $output['orderby'] && (bool) $vergeml_lib_options['natural_sort'] ) ? 'post__in' : $output['orderby'];
    }

    return $output;
}
