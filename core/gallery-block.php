<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  A folder, as a gallery.
 *
 *  The query behind this has existed since Enhanced Media Library: `[gallery
 *  media_category="press"]` builds a tax query and renders. What it never had
 *  was a way to reach it -- you had to know the shortcode existed, know the
 *  folder's slug, and switch on a setting that is off by default. So a feature
 *  that worked for years was, in practice, not there.
 *
 *  The block is the same idea with the knowing removed: pick a folder from a
 *  list. It renders on the server, so it needs no build step and no bundle, and
 *  it does not go through the shortcode at all -- which is why the setting does
 *  not apply to it.
 *
 *  The point of the whole thing is that it stays a folder rather than becoming a
 *  list of files: add an image to Press and every page showing the Press gallery
 *  has it. WordPress's own gallery block freezes a list of ids at the moment you
 *  insert it, so keeping it current means re-editing every page that uses it.
 *
 *  @since 3.2
 */


add_action( 'init', 'vergeml_register_gallery_assets', 13 );

/**
 *  Registered up front, enqueued only from the render callback -- so a page
 *  with a gallery pays for the carousel and lightbox once, and a page without
 *  one pays nothing.
 */
function vergeml_register_gallery_assets() {

    wp_register_style(
        'vergeml-gallery',
        plugins_url( 'css/vergeml-gallery.css', VERGEML_FILE ),
        array(),
        VERGEML_VERSION
    );

    wp_register_script(
        'vergeml-gallery',
        plugins_url( 'js/vergeml-gallery.js', VERGEML_FILE ),
        array(),
        VERGEML_VERSION,
        true
    );
}


add_action( 'init', 'vergeml_register_gallery_block', 14 );

function vergeml_register_gallery_block() {

    if ( ! function_exists( 'register_block_type' ) ) {
        return;
    }

    $taxonomies = function_exists( 'vergeml_tree_taxonomies' ) ? vergeml_tree_taxonomies() : array();

    if ( empty( $taxonomies ) ) {
        return;
    }

    wp_register_script(
        'vergeml-gallery-block',
        plugins_url( 'js/vergeml-gallery-block.js', VERGEML_FILE ),
        array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-api-fetch', 'wp-server-side-render' ),
        VERGEML_VERSION,
        true
    );

    wp_localize_script( 'vergeml-gallery-block', 'vergemlGallery', array(
        'taxonomy' => $taxonomies[0],
        'sizes'    => vergeml_gallery_sizes(),
        'l10n'     => array(
            'title'       => __( 'Folder gallery', 'vergelabs-media-library' ),
            'description' => __( 'Every image in a folder. Add one to the folder and it appears here, on every page using it.', 'vergelabs-media-library' ),
            'folder'      => __( 'Folder', 'vergelabs-media-library' ),
            'choose'      => __( 'Choose a folder', 'vergelabs-media-library' ),
            'columns'     => __( 'Columns', 'vergelabs-media-library' ),
            'limit'       => __( 'Maximum images', 'vergelabs-media-library' ),
            'limitHelp'   => __( 'Zero shows every image in the folder.', 'vergelabs-media-library' ),
            'size'        => __( 'Image size', 'vergelabs-media-library' ),
            'linkTo'      => __( 'Link to', 'vergelabs-media-library' ),
            'linkNone'    => __( 'Nothing', 'vergelabs-media-library' ),
            'linkLightbox' => __( 'A lightbox', 'vergelabs-media-library' ),
            'layout'      => __( 'Layout', 'vergelabs-media-library' ),
            'layoutGrid'  => __( 'Grid', 'vergelabs-media-library' ),
            'layoutCarousel' => __( 'Carousel', 'vergelabs-media-library' ),
            'linkFile'    => __( 'The image file', 'vergelabs-media-library' ),
            'linkPage'    => __( 'The file’s own page', 'vergelabs-media-library' ),
            'order'       => __( 'Order', 'vergelabs-media-library' ),
            'orderName'   => __( 'By name', 'vergelabs-media-library' ),
            'orderDate'   => __( 'Newest first', 'vergelabs-media-library' ),
            'orderOldest' => __( 'Oldest first', 'vergelabs-media-library' ),
            'orderManual' => __( 'The order set in the library', 'vergelabs-media-library' ),
            'subfolders'  => __( 'Include sub-folders', 'vergelabs-media-library' ),
            'pick'        => __( 'Pick a folder to show its images.', 'vergelabs-media-library' ),
            'empty'       => __( 'That folder has no images in it yet. Put some in it and they appear here on their own — you will not have to edit this page again.', 'vergelabs-media-library' ),
            'noFolders'   => __( 'There are no folders yet. Make one in the media library.', 'vergelabs-media-library' ),
        ),
    ) );

    register_block_type( 'vergelabs/folder-gallery', array(
        'api_version'     => 2,
        'title'           => __( 'Folder gallery', 'vergelabs-media-library' ),
        'category'        => 'media',
        'icon'            => 'images-alt2',
        'editor_script'   => 'vergeml-gallery-block',
        'render_callback' => 'vergeml_render_gallery_block',
        'attributes'      => array(
            'folder'     => array( 'type' => 'integer', 'default' => 0 ),
            'taxonomy'   => array( 'type' => 'string', 'default' => $taxonomies[0] ),
            'columns'    => array( 'type' => 'integer', 'default' => 3 ),
            'limit'      => array( 'type' => 'integer', 'default' => 0 ),
            'size'       => array( 'type' => 'string', 'default' => 'large' ),
            'linkTo'     => array( 'type' => 'string', 'default' => 'none' ),
            'layout'     => array( 'type' => 'string', 'default' => 'grid' ),
            'orderBy'    => array( 'type' => 'string', 'default' => 'name' ),
            'children'   => array( 'type' => 'boolean', 'default' => true ),
        ),
    ) );
}


/**
 *  vergeml_gallery_sizes
 *
 *  The registered image sizes, named the way the block editor names them.
 */

function vergeml_gallery_sizes() {

    $out = array();

    foreach ( get_intermediate_image_sizes() as $size ) {
        $out[] = array( 'value' => $size, 'label' => ucwords( str_replace( array( '_', '-' ), ' ', $size ) ) );
    }

    $out[] = array( 'value' => 'full', 'label' => __( 'Full size', 'vergelabs-media-library' ) );

    return $out;
}


/**
 *  vergeml_gallery_query
 *
 *  The images in a folder.
 *
 *  Kept apart from the rendering so the shortcode, the block and anything after
 *  them ask the same question. Attachments only, published or inherited, in the
 *  order the caller asked for.
 */

function vergeml_gallery_query( $atts ) {

    $folder = isset( $atts['folder'] ) ? (int) $atts['folder'] : 0;

    if ( ! $folder ) {
        return array();
    }

    /*
     *  The taxonomy is worked out here rather than trusted to arrive.
     *
     *  It has a default declared on the block, and a saved block carries it --
     *  but the editor's preview goes through the block-renderer endpoint, which
     *  hands over only the attributes the editor put in the URL. So the preview
     *  arrived with no taxonomy, found none, and drew an empty gallery beside a
     *  front end that rendered perfectly. A block that lies in the editor is
     *  worse than one that does not work at all.
     */
    $taxonomies = function_exists( 'vergeml_tree_taxonomies' ) ? vergeml_tree_taxonomies() : array();
    $taxonomy   = isset( $atts['taxonomy'] ) ? (string) $atts['taxonomy'] : '';

    if ( ! $taxonomy || ! in_array( $taxonomy, $taxonomies, true ) ) {
        $taxonomy = $taxonomies ? $taxonomies[0] : '';
    }

    if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
        return array();
    }

    $order_by = isset( $atts['orderBy'] ) ? (string) $atts['orderBy'] : 'name';

    $orders = array(
        'name'   => array( 'orderby' => 'title',      'order' => 'ASC' ),
        'newest' => array( 'orderby' => 'date',       'order' => 'DESC' ),
        'oldest' => array( 'orderby' => 'date',       'order' => 'ASC' ),
        'manual' => array( 'orderby' => 'menu_order', 'order' => 'ASC' ),
    );

    $chosen = isset( $orders[ $order_by ] ) ? $orders[ $order_by ] : $orders['name'];

    $limit = isset( $atts['limit'] ) ? (int) $atts['limit'] : 0;

    /*
     *  Yes, this is a tax query, and no, there is no version of this feature
     *  without one: a folder gallery is "the images carrying this term". It is
     *  bounded -- one term, attachments only, and a limit when the author sets
     *  one -- and it runs once per gallery on a page rather than per image.
     */
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- the feature is a term lookup; see above.
    return get_posts( array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => $limit > 0 ? $limit : -1,
        'orderby'        => $chosen['orderby'],
        'order'          => $chosen['order'],
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- a folder gallery is a term lookup; there is no version of the feature without one, and it is bounded to a single term.
        'tax_query'      => array( array(
            'taxonomy'         => $taxonomy,
            'field'            => 'term_id',
            'terms'            => array( $folder ),
            'include_children' => ! empty( $atts['children'] ),
        ) ),
    ) );
}


/**
 *  vergeml_render_gallery_block
 *
 *  Core's own gallery markup, so themes style it without knowing we exist.
 *
 *  wp-block-gallery with nested wp-block-image children is what the block editor
 *  itself emits, and every block theme already has rules for it. Inventing a
 *  wrapper of our own would mean a gallery that looks like nothing else on the
 *  site until somebody writes CSS for it.
 */

function vergeml_render_gallery_block( $attributes ) {

    $images = vergeml_gallery_query( $attributes );

    if ( empty( $images ) ) {
        // Nothing on the front of the site. An empty folder is not an error, and
        // a message about one does not belong on somebody's page.
        return '';
    }

    $columns = isset( $attributes['columns'] ) ? max( 1, min( 8, (int) $attributes['columns'] ) ) : 3;
    $size    = isset( $attributes['size'] ) ? (string) $attributes['size'] : 'large';
    $link_to = isset( $attributes['linkTo'] ) ? (string) $attributes['linkTo'] : 'none';

    $items = '';

    foreach ( $images as $image ) {

        $img = wp_get_attachment_image( $image->ID, $size, false, array(
            'class'    => 'wp-image-' . (int) $image->ID,
            'loading'  => 'lazy',
            'decoding' => 'async',
        ) );

        if ( ! $img ) {
            continue;
        }

        if ( 'file' === $link_to ) {
            $href = wp_get_attachment_url( $image->ID );
            $img  = '<a href="' . esc_url( $href ) . '">' . $img . '</a>';
        } elseif ( 'lightbox' === $link_to ) {
            /*
             *  An ordinary link to the full-size file, marked for the script to
             *  intercept. With JavaScript off it degrades to exactly the 'file'
             *  behaviour it is dressed as -- the lightbox is an improvement on a
             *  working link, never the only way to the image.
             */
            $href = wp_get_attachment_url( $image->ID );
            $img  = '<a class="vgml-lightbox" href="' . esc_url( $href ) . '">' . $img . '</a>';
        } elseif ( 'post' === $link_to ) {
            $img = '<a href="' . esc_url( get_permalink( $image->ID ) ) . '">' . $img . '</a>';
        }

        $caption = wp_get_attachment_caption( $image->ID );

        if ( $caption ) {
            $img .= '<figcaption class="wp-element-caption">' . wp_kses_post( $caption ) . '</figcaption>';
        }

        $items .= '<figure class="wp-block-image size-' . esc_attr( $size ) . '">' . $img . '</figure>';
    }

    if ( '' === $items ) {
        return '';
    }

    $layout   = ( isset( $attributes['layout'] ) && 'carousel' === $attributes['layout'] ) ? 'carousel' : 'grid';
    $carousel = 'carousel' === $layout;

    $classes = 'wp-block-gallery has-nested-images columns-' . $columns
        . ( $carousel ? ' is-carousel' : ' is-layout-flex' )
        . ' vgml-folder-gallery';

    /*
     *  The columns control, handed to CSS.
     *
     *  A carousel needs a slide width; a grid needs the column count. The grid
     *  had neither, so the stylesheet could only guess at three -- and a
     *  gallery set to five came out as three however the control was set.
     */
    $style = $carousel
        ? '--vgml-slide:calc((100% - ' . ( ( $columns - 1 ) * 16 ) . 'px)/' . $columns . ');'
        : '--vgml-columns:' . (int) $columns . ';';

    $wrapper = function_exists( 'get_block_wrapper_attributes' )
        ? get_block_wrapper_attributes( array_filter( array( 'class' => $classes, 'style' => $style ) ) )
        : 'class="' . esc_attr( $classes ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' );

    /*
     *  Assets ride along only when this render needs them, and the style comes
     *  too for the carousel alone -- the grid is core's markup and the theme's
     *  business.
     */
    if ( $carousel || 'lightbox' === $link_to ) {
        wp_enqueue_style( 'vergeml-gallery' );
        wp_enqueue_script( 'vergeml-gallery' );
    }

    return '<figure ' . $wrapper . '>' . $items . '</figure>';
}


/**
 *  The folders the block offers, for the editor's own list.
 *
 *  The tree endpoint would do, but it answers with everything the panel needs --
 *  colours, open branches, counts of files that are not images. This is the one
 *  question the block asks: which folders are there, and how many images does
 *  each hold.
 */

add_action( 'rest_api_init', 'vergeml_register_gallery_route' );

function vergeml_register_gallery_route() {

    register_rest_route( VERGEML_REST_NS, '/gallery-folders', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'vergeml_rest_gallery_folders',
        'permission_callback' => 'vergeml_can_read_tree',
        'args'                => array(
            'taxonomy' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
        ),
    ) );
}


function vergeml_rest_gallery_folders( WP_REST_Request $request ) {

    $taxonomy   = (string) $request->get_param( 'taxonomy' );
    $taxonomies = vergeml_tree_taxonomies();

    if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
        $taxonomy = $taxonomies ? $taxonomies[0] : '';
    }

    if ( ! $taxonomy ) {
        return rest_ensure_response( array( 'folders' => array() ) );
    }

    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

    if ( is_wp_error( $terms ) ) {
        return rest_ensure_response( array( 'folders' => array() ) );
    }

    $by_parent = array();

    foreach ( $terms as $term ) {
        $by_parent[ (int) $term->parent ][] = $term;
    }

    $out = array();

    /*
     *  Flattened with the path in the name, because a select cannot show a tree
     *  and "2025" on its own tells you nothing about which 2025 it is.
     */
    $walk = function ( $parent, $prefix ) use ( &$walk, &$out, $by_parent, $taxonomy ) {

        if ( empty( $by_parent[ $parent ] ) ) {
            return;
        }

        foreach ( $by_parent[ $parent ] as $term ) {

            $label = $prefix ? $prefix . ' / ' . $term->name : $term->name;

            $out[] = array(
                'id'    => (int) $term->term_id,
                'label' => $label,
                'count' => count( vergeml_gallery_query( array(
                    'taxonomy' => $taxonomy,
                    'folder'   => (int) $term->term_id,
                    'children' => false,
                ) ) ),
            );

            $walk( (int) $term->term_id, $label );
        }
    };

    $walk( 0, '' );

    return rest_ensure_response( array( 'folders' => $out ) );
}
