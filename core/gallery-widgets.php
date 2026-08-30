<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  The folder gallery, inside Elementor and Divi.
 *
 *  Neither of these is a second gallery. The Gutenberg block, the Elementor
 *  widget and the Divi module all call vergeml_render_gallery_block(), so there
 *  is one renderer and three doors to it -- a folder gallery looks the same and
 *  stays current the same way wherever it was placed, and a fix lands in all
 *  three at once.
 *
 *  Both classes are declared inside their host's own registration hook rather
 *  than at the top of the file, because each extends a class that only exists
 *  when its host plugin is active -- declared at file scope, loading this plugin
 *  without Elementor would be a fatal error in the feature meant to support it.
 *
 *  @since 3.2
 */


/**
 *  vergeml_gallery_folder_options
 *
 *  Every folder, flattened, with the path in the label -- a select cannot show
 *  a tree and "2025" on its own does not say which 2025 it is.
 */

function vergeml_gallery_folder_options() {

    $taxonomies = function_exists( 'vergeml_tree_taxonomies' ) ? vergeml_tree_taxonomies() : array();

    if ( empty( $taxonomies ) ) {
        return array();
    }

    $terms = get_terms( array( 'taxonomy' => $taxonomies[0], 'hide_empty' => false ) );

    if ( is_wp_error( $terms ) ) {
        return array();
    }

    $by_parent = array();

    foreach ( $terms as $term ) {
        $by_parent[ (int) $term->parent ][] = $term;
    }

    $out = array();

    $walk = function ( $parent, $prefix ) use ( &$walk, &$out, $by_parent ) {

        if ( empty( $by_parent[ $parent ] ) ) {
            return;
        }

        foreach ( $by_parent[ $parent ] as $term ) {
            $label = $prefix ? $prefix . ' / ' . $term->name : $term->name;
            $out[ (int) $term->term_id ] = $label;
            $walk( (int) $term->term_id, $label );
        }
    };

    $walk( 0, '' );

    return $out;
}


/**
 *  vergeml_gallery_widget_atts
 *
 *  Host settings, translated into the renderer's attributes.
 *
 *  One translation, used by both hosts, so what a control means cannot drift
 *  between them -- and so the mapping is testable without either host installed.
 */

function vergeml_gallery_widget_atts( $settings ) {

    return array(
        'folder'   => isset( $settings['folder'] ) ? (int) $settings['folder'] : 0,
        'columns'  => isset( $settings['columns'] ) ? max( 1, min( 8, (int) $settings['columns'] ) ) : 3,
        'limit'    => isset( $settings['limit'] ) ? max( 0, (int) $settings['limit'] ) : 0,
        'size'     => isset( $settings['size'] ) && '' !== $settings['size'] ? (string) $settings['size'] : 'large',
        'linkTo'   => isset( $settings['link_to'] ) && in_array( $settings['link_to'], array( 'none', 'lightbox', 'file', 'post' ), true )
            ? (string) $settings['link_to'] : 'none',
        'layout'   => isset( $settings['layout'] ) && 'carousel' === $settings['layout'] ? 'carousel' : 'grid',
        'orderBy'  => isset( $settings['order_by'] ) && in_array( $settings['order_by'], array( 'name', 'newest', 'oldest', 'manual' ), true )
            ? (string) $settings['order_by'] : 'name',
        // Divi's toggles arrive as the strings 'on'/'off'; Elementor's as 'yes'/''.
        'children' => isset( $settings['children'] )
            ? in_array( $settings['children'], array( 'yes', 'on', '1', 1, true ), true )
            : true,
    );
}


/* ------------------------------------------------------- the shortcode */

/*
 *  [vergeml_gallery folder="12" columns="3" layout="carousel" link_to="lightbox"]
 *
 *  The fourth door, and the one that needs no builder at all: it works in a
 *  plain post, a text widget, a theme that never heard of blocks. Registered
 *  unconditionally -- unlike the inherited [gallery media_category=...]
 *  enhancement, it hides behind no setting, because a shortcode somebody has to
 *  switch on first is a shortcode that renders nothing with no explanation.
 *
 *  It is also what the WPBakery element maps onto: WPBakery's model is
 *  "an element is a shortcode", so the door and the element are one thing.
 */

add_shortcode( 'vergeml_gallery', 'vergeml_gallery_shortcode' );

function vergeml_gallery_shortcode( $atts ) {

    $settings = shortcode_atts( array(
        'folder'   => 0,
        'columns'  => 3,
        'limit'    => 0,
        'size'     => 'large',
        'link_to'  => 'none',
        'order_by' => 'name',
        'children' => 'yes',
        'layout'   => 'grid',
    ), $atts, 'vergeml_gallery' );

    return vergeml_render_gallery_block( vergeml_gallery_widget_atts( $settings ) );
}


/* -------------------------------------------------------------- WPBakery */

add_action( 'vc_before_init', 'vergeml_register_wpbakery_gallery' );

function vergeml_register_wpbakery_gallery() {

    if ( ! function_exists( 'vc_map' ) ) {
        return;
    }

    /*
     *  WPBakery's dropdown quirk, worth naming because it is exactly backwards:
     *  'value' takes array( Label => value ), label first. Feed it value => label
     *  and every option saves its label as its value.
     */
    $folders = array( __( 'Choose a folder', 'vergelabs-media-library' ) => '0' );

    foreach ( vergeml_gallery_folder_options() as $id => $label ) {
        $folders[ $label ] = (string) $id;
    }

    $sizes = array();

    foreach ( vergeml_gallery_sizes() as $size ) {
        $sizes[ $size['label'] ] = $size['value'];
    }

    vc_map( array(
        'name'        => __( 'Folder gallery', 'vergelabs-media-library' ),
        'base'        => 'vergeml_gallery',
        'category'    => __( 'Content', 'vergelabs-media-library' ),
        'icon'        => 'icon-wpb-images-stack',
        'description' => __( 'Every image in a folder, kept current.', 'vergelabs-media-library' ),
        'params'      => array(
            array(
                'type'        => 'dropdown',
                'heading'     => __( 'Folder', 'vergelabs-media-library' ),
                'param_name'  => 'folder',
                'value'       => $folders,
                'admin_label' => true,
            ),
            array(
                'type'       => 'dropdown',
                'heading'    => __( 'Layout', 'vergelabs-media-library' ),
                'param_name' => 'layout',
                'value'      => array(
                    __( 'Grid', 'vergelabs-media-library' )     => 'grid',
                    __( 'Carousel', 'vergelabs-media-library' ) => 'carousel',
                ),
                'std'        => 'grid',
            ),
            array(
                'type'       => 'dropdown',
                'heading'    => __( 'Include sub-folders', 'vergelabs-media-library' ),
                'param_name' => 'children',
                'value'      => array(
                    __( 'Yes', 'vergelabs-media-library' ) => 'yes',
                    __( 'No', 'vergelabs-media-library' )  => 'no',
                ),
                'std'        => 'yes',
            ),
            array(
                'type'       => 'dropdown',
                'heading'    => __( 'Columns', 'vergelabs-media-library' ),
                'param_name' => 'columns',
                'value'      => array_combine( array_map( 'strval', range( 1, 8 ) ), array_map( 'strval', range( 1, 8 ) ) ),
                'std'        => '3',
            ),
            array(
                'type'        => 'textfield',
                'heading'     => __( 'Maximum images', 'vergelabs-media-library' ),
                'param_name'  => 'limit',
                'value'       => '0',
                'description' => __( 'Zero shows every image in the folder.', 'vergelabs-media-library' ),
            ),
            array(
                'type'       => 'dropdown',
                'heading'    => __( 'Order', 'vergelabs-media-library' ),
                'param_name' => 'order_by',
                'value'      => array(
                    __( 'By name', 'vergelabs-media-library' )                  => 'name',
                    __( 'Newest first', 'vergelabs-media-library' )             => 'newest',
                    __( 'Oldest first', 'vergelabs-media-library' )             => 'oldest',
                    __( 'The order set in the library', 'vergelabs-media-library' ) => 'manual',
                ),
                'std'        => 'name',
            ),
            array(
                'type'       => 'dropdown',
                'heading'    => __( 'Image size', 'vergelabs-media-library' ),
                'param_name' => 'size',
                'value'      => $sizes,
                'std'        => 'large',
            ),
            array(
                'type'       => 'dropdown',
                'heading'    => __( 'Link to', 'vergelabs-media-library' ),
                'param_name' => 'link_to',
                'value'      => array(
                    __( 'Nothing', 'vergelabs-media-library' )             => 'none',
                    __( 'A lightbox', 'vergelabs-media-library' )          => 'lightbox',
                    __( 'The image file', 'vergelabs-media-library' )      => 'file',
                    __( 'The file’s own page', 'vergelabs-media-library' ) => 'post',
                ),
                'std'        => 'none',
            ),
        ),
    ) );
}


/* ------------------------------------------------------------- Elementor */

add_action( 'elementor/widgets/register', 'vergeml_register_elementor_gallery' );

function vergeml_register_elementor_gallery( $widgets_manager ) {

    if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
        return;
    }

    /*
     *  A named class, not an anonymous one. Anonymous class names carry a NUL
     *  byte and the file path, and both hosts put class names into caches --
     *  Divi serialises its module registry to disk. A name that cannot be
     *  serialised is a corruption nobody can diagnose from the outside.
     */
    class VergeML_Elementor_Gallery extends \Elementor\Widget_Base {

        public function get_name() {
            return 'vergeml-folder-gallery';
        }

        public function get_title() {
            return __( 'Folder gallery', 'vergelabs-media-library' );
        }

        public function get_icon() {
            return 'eicon-gallery-grid';
        }

        public function get_categories() {
            return array( 'basic' );
        }

        public function get_keywords() {
            return array( 'gallery', 'folder', 'media', 'images' );
        }

        protected function register_controls() {

            $folders = array( '0' => __( 'Choose a folder', 'vergelabs-media-library' ) );

            foreach ( vergeml_gallery_folder_options() as $id => $label ) {
                $folders[ (string) $id ] = $label;
            }

            $sizes = array();

            foreach ( vergeml_gallery_sizes() as $size ) {
                $sizes[ $size['value'] ] = $size['label'];
            }

            $this->start_controls_section( 'vergeml_gallery', array(
                'label' => __( 'Folder gallery', 'vergelabs-media-library' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ) );

            $this->add_control( 'folder', array(
                'label'   => __( 'Folder', 'vergelabs-media-library' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => $folders,
                'default' => '0',
            ) );

            $this->add_control( 'layout', array(
                'label'   => __( 'Layout', 'vergelabs-media-library' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => array(
                    'grid'     => __( 'Grid', 'vergelabs-media-library' ),
                    'carousel' => __( 'Carousel', 'vergelabs-media-library' ),
                ),
                'default' => 'grid',
            ) );

            $this->add_control( 'children', array(
                'label'   => __( 'Include sub-folders', 'vergelabs-media-library' ),
                'type'    => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ) );

            $this->add_control( 'columns', array(
                'label'   => __( 'Columns', 'vergelabs-media-library' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => array_combine( range( 1, 8 ), range( 1, 8 ) ),
                'default' => '3',
            ) );

            $this->add_control( 'limit', array(
                'label'       => __( 'Maximum images', 'vergelabs-media-library' ),
                'description' => __( 'Zero shows every image in the folder.', 'vergelabs-media-library' ),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'min'         => 0,
                'max'         => 100,
                'default'     => 0,
            ) );

            $this->add_control( 'order_by', array(
                'label'   => __( 'Order', 'vergelabs-media-library' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => array(
                    'name'   => __( 'By name', 'vergelabs-media-library' ),
                    'newest' => __( 'Newest first', 'vergelabs-media-library' ),
                    'oldest' => __( 'Oldest first', 'vergelabs-media-library' ),
                    'manual' => __( 'The order set in the library', 'vergelabs-media-library' ),
                ),
                'default' => 'name',
            ) );

            $this->add_control( 'size', array(
                'label'   => __( 'Image size', 'vergelabs-media-library' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => $sizes,
                'default' => 'large',
            ) );

            $this->add_control( 'link_to', array(
                'label'   => __( 'Link to', 'vergelabs-media-library' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'options' => array(
                    'none'     => __( 'Nothing', 'vergelabs-media-library' ),
                    'lightbox' => __( 'A lightbox', 'vergelabs-media-library' ),
                    'file'     => __( 'The image file', 'vergelabs-media-library' ),
                    'post'     => __( 'The file’s own page', 'vergelabs-media-library' ),
                ),
                'default' => 'none',
            ) );

            $this->end_controls_section();
        }

        protected function render() {

            $atts = vergeml_gallery_widget_atts( $this->get_settings_for_display() );
            $html = vergeml_render_gallery_block( $atts );

            /*
             *  Only inside the editor: an empty result there reads as a broken
             *  widget, so say what is missing. The front of the site stays
             *  silent -- an empty folder is not an error and a message about
             *  one does not belong on somebody's page.
             */
            if ( '' === $html && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                $html = '<div style="padding:24px;text-align:center;color:#646970;border:1px dashed #dcdcde;border-radius:4px;">'
                    . esc_html( $atts['folder']
                        ? __( 'That folder has no images in it yet.', 'vergelabs-media-library' )
                        : __( 'Pick a folder to show its images.', 'vergelabs-media-library' ) )
                    . '</div>';
            }

            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built entirely from esc_*'d parts by vergeml_render_gallery_block().
        }
    }

    $widgets_manager->register( new VergeML_Elementor_Gallery() );
}


/* ------------------------------------------------------------------ Divi */

add_action( 'et_builder_ready', 'vergeml_register_divi_gallery' );

function vergeml_register_divi_gallery() {

    if ( ! class_exists( 'ET_Builder_Module' ) ) {
        return;
    }

    class VergeML_Divi_Gallery extends ET_Builder_Module {

        public $slug       = 'vergeml_folder_gallery';
        // 'partial': the module renders through a server round-trip in the
        // visual builder instead of a React bundle of its own. A bundle is a
        // build step, and the plugin does not have one.
        public $vb_support = 'partial';

        public function init() {
            $this->name = esc_html__( 'Folder gallery', 'vergelabs-media-library' );
        }

        public function get_fields() {

            $folders = array( '0' => esc_html__( 'Choose a folder', 'vergelabs-media-library' ) );

            foreach ( vergeml_gallery_folder_options() as $id => $label ) {
                $folders[ (string) $id ] = $label;
            }

            $sizes = array();

            foreach ( vergeml_gallery_sizes() as $size ) {
                $sizes[ $size['value'] ] = $size['label'];
            }

            return array(
                'folder'   => array(
                    'label'           => esc_html__( 'Folder', 'vergelabs-media-library' ),
                    'type'            => 'select',
                    'options'         => $folders,
                    'default'         => '0',
                    'option_category' => 'basic_option',
                ),
                'layout'   => array(
                    'label'           => esc_html__( 'Layout', 'vergelabs-media-library' ),
                    'type'            => 'select',
                    'options'         => array(
                        'grid'     => esc_html__( 'Grid', 'vergelabs-media-library' ),
                        'carousel' => esc_html__( 'Carousel', 'vergelabs-media-library' ),
                    ),
                    'default'         => 'grid',
                    'option_category' => 'layout',
                ),
                'children' => array(
                    'label'           => esc_html__( 'Include sub-folders', 'vergelabs-media-library' ),
                    'type'            => 'yes_no_button',
                    'options'         => array(
                        'on'  => esc_html__( 'Yes', 'vergelabs-media-library' ),
                        'off' => esc_html__( 'No', 'vergelabs-media-library' ),
                    ),
                    'default'         => 'on',
                    'option_category' => 'basic_option',
                ),
                'columns'  => array(
                    'label'           => esc_html__( 'Columns', 'vergelabs-media-library' ),
                    'type'            => 'select',
                    'options'         => array_combine( array_map( 'strval', range( 1, 8 ) ), range( 1, 8 ) ),
                    'default'         => '3',
                    'option_category' => 'layout',
                ),
                'limit'    => array(
                    'label'           => esc_html__( 'Maximum images', 'vergelabs-media-library' ),
                    'description'     => esc_html__( 'Zero shows every image in the folder.', 'vergelabs-media-library' ),
                    'type'            => 'text',
                    'default'         => '0',
                    'option_category' => 'basic_option',
                ),
                'order_by' => array(
                    'label'           => esc_html__( 'Order', 'vergelabs-media-library' ),
                    'type'            => 'select',
                    'options'         => array(
                        'name'   => esc_html__( 'By name', 'vergelabs-media-library' ),
                        'newest' => esc_html__( 'Newest first', 'vergelabs-media-library' ),
                        'oldest' => esc_html__( 'Oldest first', 'vergelabs-media-library' ),
                        'manual' => esc_html__( 'The order set in the library', 'vergelabs-media-library' ),
                    ),
                    'default'         => 'name',
                    'option_category' => 'basic_option',
                ),
                'size'     => array(
                    'label'           => esc_html__( 'Image size', 'vergelabs-media-library' ),
                    'type'            => 'select',
                    'options'         => $sizes,
                    'default'         => 'large',
                    'option_category' => 'basic_option',
                ),
                'link_to'  => array(
                    'label'           => esc_html__( 'Link to', 'vergelabs-media-library' ),
                    'type'            => 'select',
                    'options'         => array(
                        'none'     => esc_html__( 'Nothing', 'vergelabs-media-library' ),
                        'lightbox' => esc_html__( 'A lightbox', 'vergelabs-media-library' ),
                        'file'     => esc_html__( 'The image file', 'vergelabs-media-library' ),
                        'post'     => esc_html__( 'The file’s own page', 'vergelabs-media-library' ),
                    ),
                    'default'         => 'none',
                    'option_category' => 'basic_option',
                ),
            );
        }

        public function render( $attrs, $content = null, $render_slug = '' ) {
            return vergeml_render_gallery_block( vergeml_gallery_widget_atts( $this->props ) );
        }
    }

    // Divi builds its module list from instances, not from a register call:
    // constructing one IS registering it.
    new VergeML_Divi_Gallery();
}
