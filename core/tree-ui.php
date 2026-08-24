<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  The folder tree, on Media > Library.
 *
 *  Grid and list, and nothing else in this phase -- the media modal is its own
 *  problem with seven contexts and it gets its own phase rather than being
 *  smuggled in here.
 *
 *  This file loads assets and hands the browser what it needs. It deliberately
 *  does not render the tree: the markup is built from the same endpoint the
 *  tree refreshes from, so there is one code path producing it and no chance of
 *  the first paint disagreeing with every paint after it.
 *
 *  @since 3.1
 */


add_action( 'admin_enqueue_scripts', 'vergeml_tree_assets' );

function vergeml_tree_assets( $hook ) {

    if ( 'upload.php' !== $hook )
        return;

    if ( ! current_user_can( 'upload_files' ) )
        return;

    $taxonomies = vergeml_tree_taxonomies();

    if ( empty( $taxonomies ) )
        return;

    wp_enqueue_style(
        'vergeml-tree',
        plugins_url( 'css/vergeml-tree.css', VERGEML_FILE ),
        array(),
        VERGEML_VERSION
    );

    if ( is_rtl() ) {
        wp_enqueue_style(
            'vergeml-tree-rtl',
            plugins_url( 'css/vergeml-tree-rtl.css', VERGEML_FILE ),
            array( 'vergeml-tree' ),
            VERGEML_VERSION
        );
    }

    wp_enqueue_script(
        'vergeml-tree',
        plugins_url( 'js/vergeml-tree.js', VERGEML_FILE ),
        /*
         *  jQuery UI draggable/droppable rather than the HTML5 drag API.
         *
         *  Both ship with WordPress and core's own media grid already uses them,
         *  so this costs no download and adds no dependency. It matters because
         *  the HTML5 API hands the browser control of the drag image, has no
         *  distance threshold -- so a sloppy click becomes a drag -- and does not
         *  work on touch at all. jQuery UI is plain pointer events with a helper
         *  element we own, which is why it can show a real tile with a count.
         */
        array( 'wp-api-fetch', 'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable' ),
        VERGEML_VERSION,
        true
    );

    $list = array();

    foreach ( $taxonomies as $name ) {

        $object = get_taxonomy( $name );

        if ( ! $object instanceof WP_Taxonomy || ! $object->hierarchical )
            continue; // A flat taxonomy drawn as a tree is a lie about the data.

        $list[] = array(
            'name'  => $name,
            'label' => $object->labels->name,
        );
    }

    if ( empty( $list ) )
        return;

    $state = vergeml_tree_state( $list[0]['name'] );

    /*
     *  The tree travels with the page, so opening the library never fetches it.
     *
     *  It used to render empty and then call vergeml/v1/tree, which meant a blank
     *  panel followed by a pop -- on every single visit, for data this request
     *  already had in hand. The endpoint stays, because everything after the first
     *  paint still uses it, but the first paint costs nothing.
     *
     *  Built by calling the REST handler rather than by repeating its query here:
     *  one code path produces the tree, so the first paint cannot disagree with
     *  every paint after it.
     */
    $boot = null;

    if ( class_exists( 'WP_REST_Request' ) && function_exists( 'vergeml_rest_tree' ) ) {

        $request = new WP_REST_Request( 'GET', '/' . VERGEML_REST_NS . '/tree' );
        $request->set_param( 'taxonomy', $list[0]['name'] );

        $result = vergeml_rest_tree( $request );

        if ( $result instanceof WP_REST_Response ) {
            $boot = $result->get_data();
        }
    }

    wp_localize_script( 'vergeml-tree', 'vergemlTree', array(
        'taxonomies' => $list,
        'state'      => $state,
        'boot'       => $boot,
        'canManage'  => current_user_can( 'manage_categories' ),
        /*
         *  When on, a plain drag moves instead of adding -- the behaviour someone
         *  switching from FileBird or Folders expects, since their folders hold a
         *  file once. Off by default: a file in several folders is the thing this
         *  plugin can do that they cannot.
         */
        'onePerFile' => ! empty( get_option( 'vergeml_tax_options', array() )['one_folder_per_file'] ),
        'palette'    => vergeml_tree_palette(),
        'skins'      => vergeml_tree_skins(),
        'densities'  => vergeml_tree_densities(),
        /*
         *  The accent of whichever admin colour scheme this user picked. The
         *  'native' skin uses it so the tree belongs to the admin it is sitting
         *  in rather than announcing itself. Neither competitor reads this.
         */
        'accent'     => vergeml_admin_accent(),
        'l10n'       => array(
            'all'            => __( 'All files', 'vergelabs-media-library' ),
            'unassigned'     => __( 'Unfiled', 'vergelabs-media-library' ),
            'newFolder'      => __( 'New folder', 'vergelabs-media-library' ),
            'rename'         => __( 'Rename', 'vergelabs-media-library' ),
            'color'          => __( 'Colour', 'vergelabs-media-library' ),
            'delete'         => __( 'Delete', 'vergelabs-media-library' ),
            'search'         => __( 'Search folders', 'vergelabs-media-library' ),
            'folders'        => __( 'Folders', 'vergelabs-media-library' ),
            'namePrompt'     => __( 'Folder name', 'vergelabs-media-library' ),
            /* translators: %s: folder name. */
            'renamePrompt'   => __( 'Rename %s to', 'vergelabs-media-library' ),
            /*
             *  Said in these words on purpose. "Delete folder" reads as "delete
             *  my photos" to anyone who has not thought about it, and the whole
             *  reason folders are terms is that it is not true.
             */
            'deleteConfirm'  => __( 'Delete the folder "%1$s"? Its %2$d sub-folders move up one level. No files are deleted — they simply leave this folder.', 'vergelabs-media-library' ),
            'deleteSimple'   => __( 'Delete the folder "%s"? No files are deleted — they simply leave this folder.', 'vergelabs-media-library' ),
            /* translators: %d: number of files. */
            'undoAssigned'   => __( '%d files filed', 'vergelabs-media-library' ),
            'cancel'         => __( 'Cancel', 'vergelabs-media-library' ),
            'oneFile'        => __( '1 file', 'vergelabs-media-library' ),
            /* translators: %d: number of files being dragged. */
            'manyFiles'      => __( '%d files', 'vergelabs-media-library' ),
            'undo'           => __( 'Undo', 'vergelabs-media-library' ),
            'undone'         => __( 'Put back', 'vergelabs-media-library' ),
            /* translators: %d: number of folders a file belongs to. */
            'inFolders'      => __( 'in %d folders', 'vergelabs-media-library' ),
            'skin'           => __( 'Appearance', 'vergelabs-media-library' ),
            'skinNative'     => __( 'Native', 'vergelabs-media-library' ),
            'skinClassic'    => __( 'Classic', 'vergelabs-media-library' ),
            'skinMinimal'    => __( 'Minimal', 'vergelabs-media-library' ),
            'skinContrast'   => __( 'High contrast', 'vergelabs-media-library' ),
            'comfortable'    => __( 'Comfortable', 'vergelabs-media-library' ),
            'compact'        => __( 'Compact', 'vergelabs-media-library' ),
            'nothingFound'   => __( 'No folders match', 'vergelabs-media-library' ),
            'failed'         => __( 'That did not work. Nothing was changed.', 'vergelabs-media-library' ),
        ),
    ) );
}


/**
 *  vergeml_tree_palette
 *
 *  Eight colours, fixed. Not a colour picker: a dot in a sidebar has to stay
 *  legible against eight different admin schemes, in light and dark, and a free
 *  hex field guarantees that some of them will not be. These were picked to
 *  hold contrast in all of them.
 */

function vergeml_tree_palette() {

    return array(
        '',         // none -- inherits the skin's own colour
        '#d63638',  // red
        '#e07d10',  // amber
        '#997e00',  // olive
        '#2f8f45',  // green
        '#0f7c8c',  // teal
        '#3858e9',  // blue
        '#7c3aed',  // violet
        '#a4286a',  // magenta
    );
}


/**
 *  vergeml_admin_accent
 *
 *  The highlight colour of this user's admin colour scheme, or the default blue
 *  if the scheme is unknown. Read once and handed to the browser rather than
 *  guessed at in CSS, because the schemes are registered in PHP and a plugin
 *  can add more.
 */

function vergeml_admin_accent() {

    global $_wp_admin_css_colors;

    $scheme = get_user_option( 'admin_color' );

    if ( ! $scheme || ! isset( $_wp_admin_css_colors[ $scheme ] ) )
        return '#3858e9';

    $colors = $_wp_admin_css_colors[ $scheme ]->colors;

    // The schemes list four colours; the third is the one core uses for
    // highlights, and it is the one that reads as "the accent" on screen.
    if ( isset( $colors[2] ) )
        return $colors[2];

    return isset( $colors[0] ) ? $colors[0] : '#3858e9';
}
