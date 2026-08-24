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


/*
 *  Late on purpose. Whether a screen has a media modal is only knowable after
 *  whoever wanted one has asked for it, and wp_enqueue_media() is itself usually
 *  called from this same hook. At the default priority the answer is a coin toss
 *  depending on which plugin registered first.
 */
add_action( 'admin_enqueue_scripts', 'vergeml_tree_assets', 20 );

function vergeml_tree_assets( $hook ) {

    /*
     *  Which post type's list screen this is, if it is one and folders are on for
     *  it. Worked out first because it decides both whether to load at all and
     *  what to load -- a post screen gets one taxonomy and counts for that post
     *  type, not the media library's tree with the media library's numbers.
     */
    $folder_post_type = '';

    if ( 'edit.php' === $hook && function_exists( 'vergeml_folder_taxonomy_for' ) ) {

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $type   = ( $screen && $screen->post_type ) ? $screen->post_type : 'post';

        if ( vergeml_folder_taxonomy_for( $type ) ) {
            $folder_post_type = $type;
        }
    }

    /*
     *  Uploading is the wrong question on a post screen. Somebody who edits posts
     *  and cannot upload still has folders on their posts; what they may actually
     *  change is decided per object by the endpoint.
     */
    if ( $folder_post_type ) {
        if ( ! current_user_can( 'edit_posts' ) )
            return;
    } elseif ( ! current_user_can( 'upload_files' ) ) {
        return;
    }

    /*
     *  The library screen, and anywhere the media modal can be opened.
     *
     *  This used to load on upload.php alone, which meant the tree did not exist
     *  when inserting an image into a post -- half of what a media library is for.
     *
     *  Decided by asking whether wp.media is on the screen rather than by listing
     *  screens, because that list goes stale: a plugin, a block, a metabox or the
     *  next release of core can open a media modal anywhere. did_action gives the
     *  honest answer, and everything below is a no-op when there is no frame.
     */
    $library = ( 'upload.php' === $hook );

    // The one script every media modal needs. If it is going out, a frame can be
    // opened on this screen; if it is not, nothing below would have anything to
    // attach to anyway.
    $modal = wp_script_is( 'media-views', 'enqueued' ) || wp_script_is( 'media-views', 'to_do' );

    if ( ! $library && ! $modal && ! $folder_post_type )
        return;

    $taxonomies = $folder_post_type
        ? array( vergeml_folder_taxonomy_for( $folder_post_type ) )
        : vergeml_tree_taxonomies();

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

        if ( $folder_post_type ) {
            $request->set_param( 'post_type', $folder_post_type );
        }

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
         *  The modal is a different room: about 700px wide, opened to pick a file
         *  rather than to reorganise. The panel is collapsible there, folders can
         *  be dragged into but not renamed or deleted, and it always opens on All
         *  files -- the folder someone was browsing on the library screen is
         *  rarely the one they want when inserting an image into a post.
         */
        'onLibrary'  => $library || (bool) $folder_post_type,
        /*
         *  Which list the tree is filtering. 'attachment' everywhere it always
         *  was, so nothing that reads this has to know post folders exist.
         */
        'postType'   => $folder_post_type ? $folder_post_type : 'attachment',
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
            /*
             *  "All files" is wrong above a list of pages. The post type's own
             *  plural is what that screen calls them everywhere else, so it is
             *  what the tree calls them too.
             */
            'all'            => $folder_post_type
                ? ( get_post_type_object( $folder_post_type )
                    ? get_post_type_object( $folder_post_type )->labels->all_items
                    : __( 'All', 'vergelabs-media-library' ) )
                : __( 'All files', 'vergelabs-media-library' ),
            'unassigned'     => __( 'Unfiled', 'vergelabs-media-library' ),
            'newFolder'      => __( 'New folder', 'vergelabs-media-library' ),
            'rename'         => __( 'Rename', 'vergelabs-media-library' ),
            'color'          => __( 'Colour', 'vergelabs-media-library' ),
            /*
             *  Named, not just shown. Eight coloured circles with no names are
             *  unusable with a screen reader and ambiguous with colour blindness,
             *  which is most of the reason to have a fixed palette rather than a
             *  hex field: a fixed set can be named.
             */
            'colorNone'      => __( 'No colour', 'vergelabs-media-library' ),
            'colorRed'       => __( 'Red', 'vergelabs-media-library' ),
            'colorAmber'     => __( 'Amber', 'vergelabs-media-library' ),
            'colorOlive'     => __( 'Olive', 'vergelabs-media-library' ),
            'colorGreen'     => __( 'Green', 'vergelabs-media-library' ),
            'colorTeal'      => __( 'Teal', 'vergelabs-media-library' ),
            'colorBlue'      => __( 'Blue', 'vergelabs-media-library' ),
            'colorViolet'    => __( 'Violet', 'vergelabs-media-library' ),
            'colorMagenta'   => __( 'Magenta', 'vergelabs-media-library' ),
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
            /* translators: 1: folder name, 2: number of sub-folders. */
            'deleteConfirm'  => __( 'Delete the folder "%1$s"? Its %2$d sub-folders move up one level. No files are deleted — they simply leave this folder.', 'vergelabs-media-library' ),
            /* translators: %s: folder name. */
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
            /* translators: %d: number of folders not shown. */
            'moreFolders'    => __( '%d more — use search to find them', 'vergelabs-media-library' ),
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
