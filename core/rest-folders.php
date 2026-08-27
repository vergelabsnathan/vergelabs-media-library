<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Making and unmaking folders.
 *
 *      POST vergeml/v1/folder    create, rename, recolour, reparent, delete
 *      GET  vergeml/v1/state     this user's open branches, selection, width, skin
 *      POST vergeml/v1/state     and setting them
 *
 *  Separate from rest-tree.php because these are a different kind of operation.
 *  Reading the tree and filing files into it is everyday work that any uploader
 *  does; changing the shape of the filing system is not, and it is gated on
 *  manage_categories accordingly. Keeping them in one file would invite the two
 *  permission models to drift into each other.
 *
 *  @since 3.1
 */


add_action( 'rest_api_init', 'vergeml_register_folder_routes' );

function vergeml_register_folder_routes() {

    register_rest_route( VERGEML_REST_NS, '/folder', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_rest_folder',
        'permission_callback' => 'vergeml_can_manage_folders',
        'args'                => array(
            'taxonomy' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            'action'   => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            'id'       => array( 'type' => 'integer' ),
            'parent'   => array( 'type' => 'integer' ),
            'name'     => array( 'type' => 'string' ),
            'color'    => array( 'type' => 'string' ),
            'ids'      => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
            // Which list the caller is showing, so the tree that comes back
            // carries that list's counts rather than the media library's.
            'post_type' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/state', array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'vergeml_rest_state_get',
            'permission_callback' => 'vergeml_can_read_tree',
            'args'                => array(
                'taxonomy' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            ),
        ),
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'vergeml_rest_state_set',
            'permission_callback' => 'vergeml_can_read_tree',
            'args'                => array(
                'taxonomy' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
                'open'     => array( 'type' => 'array' ),
                'selected' => array( 'type' => 'integer' ),
                'width'    => array( 'type' => 'integer' ),
                'collapsed' => array( 'type' => 'integer' ),
                'filtersOpen' => array( 'type' => 'integer' ),
                'aiOpen'     => array( 'type' => 'integer' ),
                'skin'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
                'density'  => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            ),
        ),
    ) );
}


/**
 *  vergeml_can_manage_folders
 *
 *  Restructuring is not filing. Anyone who can upload may put a file into a
 *  folder -- that is checked per attachment in the assign handler -- but
 *  renaming or deleting one changes what everybody else sees, so it takes the
 *  capability WordPress already uses for managing terms.
 */

function vergeml_can_manage_folders() {
    return current_user_can( 'manage_categories' );
}


/**
 *  The skins, and the density.
 *
 *  Named here rather than in the stylesheet so the server can refuse a value it
 *  does not know. A skin arriving from a request becomes an attribute on the
 *  tree; an unchecked one is a selector nobody wrote.
 */

function vergeml_tree_skins() {
    return array( 'native', 'classic', 'minimal', 'contrast' );
}

function vergeml_tree_densities() {
    return array( 'comfortable', 'compact' );
}


/**
 *  vergeml_rest_folder
 *
 *  One endpoint, five actions, because they are one thing from the tree's point
 *  of view: the shape of the folders changed, here is the part that moved.
 */

function vergeml_rest_folder( WP_REST_Request $request ) {

    $taxonomy = $request->get_param( 'taxonomy' );

    if ( ! in_array( $taxonomy, vergeml_tree_taxonomies(), true ) ) {
        return new WP_Error( 'vergeml_unknown_taxonomy', __( 'That is not a media taxonomy.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    $action = $request->get_param( 'action' );
    $id     = vergeml_id( $request->get_param( 'id' ) );
    $name   = trim( (string) $request->get_param( 'name' ) );
    $parent = vergeml_id( $request->get_param( 'parent' ) );

    switch ( $action ) {

        case 'create':
            if ( '' === $name ) {
                return new WP_Error( 'vergeml_no_name', __( 'A folder needs a name.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
            }
            if ( $parent && ! vergeml_term_in( $parent, $taxonomy ) ) {
                return new WP_Error( 'vergeml_unknown_term', __( 'That parent folder does not exist.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
            }

            $made = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );

            if ( is_wp_error( $made ) ) {
                // A duplicate name under the same parent is the common one, and
                // it deserves the plain message rather than a 500.
                return new WP_Error( 'vergeml_create_failed', $made->get_error_message(), array( 'status' => 400 ) );
            }

            $id = (int) $made['term_id'];
            vergeml_set_color( $id, $taxonomy, $request->get_param( 'color' ) );
            vergeml_place_last( $id, $parent, $taxonomy );
            break;

        case 'rename':
            if ( ! vergeml_term_in( $id, $taxonomy ) ) {
                return new WP_Error( 'vergeml_unknown_term', __( 'That folder does not exist.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
            }
            if ( '' === $name ) {
                return new WP_Error( 'vergeml_no_name', __( 'A folder needs a name.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
            }

            $done = wp_update_term( $id, $taxonomy, array( 'name' => $name ) );
            if ( is_wp_error( $done ) ) {
                return new WP_Error( 'vergeml_rename_failed', $done->get_error_message(), array( 'status' => 400 ) );
            }
            break;

        case 'color':
            if ( ! vergeml_term_in( $id, $taxonomy ) ) {
                return new WP_Error( 'vergeml_unknown_term', __( 'That folder does not exist.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
            }
            vergeml_set_color( $id, $taxonomy, $request->get_param( 'color' ) );
            break;

        case 'move':
            if ( ! vergeml_term_in( $id, $taxonomy ) ) {
                return new WP_Error( 'vergeml_unknown_term', __( 'That folder does not exist.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
            }
            if ( $parent && ! vergeml_term_in( $parent, $taxonomy ) ) {
                return new WP_Error( 'vergeml_unknown_term', __( 'That parent folder does not exist.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
            }

            /*
             *  A folder cannot be dropped inside itself, or inside anything
             *  beneath it. Left unchecked that detaches the whole branch from the
             *  tree: the terms still exist, every file in them still exists, and
             *  none of it appears anywhere ever again. The check is cheap and the
             *  failure is unrecoverable by hand, which is the whole argument.
             */
            if ( $parent === $id ) {
                return new WP_Error( 'vergeml_cycle', __( 'A folder cannot be put inside itself.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
            }
            if ( $parent && in_array( $parent, vergeml_descendants( $id, $taxonomy ), true ) ) {
                return new WP_Error( 'vergeml_cycle', __( 'A folder cannot be put inside one of its own sub-folders.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
            }

            $done = wp_update_term( $id, $taxonomy, array( 'parent' => $parent ) );
            if ( is_wp_error( $done ) ) {
                return new WP_Error( 'vergeml_move_failed', $done->get_error_message(), array( 'status' => 400 ) );
            }

            vergeml_place_last( $id, $parent, $taxonomy );
            break;

        /*
         *  Reordering by hand.
         *
         *  The whole sibling list is sent rather than "move this one up": a
         *  position is only meaningful relative to the others, and a list that
         *  the browser already has is one request instead of one per folder that
         *  shifted. It doubles as the re-parent, because dragging a folder
         *  between two others in a different branch is one gesture and should not
         *  be two writes that can half-fail.
         */
        case 'order':

            $ids = vergeml_ids( $request->get_param( 'ids' ) );

            if ( ! $ids ) {
                return new WP_Error( 'vergeml_no_folders', __( 'No folders were given to order.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
            }

            if ( $parent && ! vergeml_term_in( $parent, $taxonomy ) ) {
                return new WP_Error( 'vergeml_unknown_term', __( 'That parent folder does not exist.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
            }

            foreach ( $ids as $one ) {

                if ( ! vergeml_term_in( $one, $taxonomy ) ) {
                    return new WP_Error( 'vergeml_unknown_term', __( 'One of those folders does not exist.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
                }

                // Same guard as a move, for the same reason: this action can
                // re-parent, so it can detach a branch just as thoroughly.
                if ( $parent === $one ) {
                    return new WP_Error( 'vergeml_cycle', __( 'A folder cannot be put inside itself.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
                }
                if ( $parent && in_array( $parent, vergeml_descendants( $one, $taxonomy ), true ) ) {
                    return new WP_Error( 'vergeml_cycle', __( 'A folder cannot be put inside one of its own sub-folders.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
                }
            }

            foreach ( $ids as $position => $one ) {

                $term = get_term( $one, $taxonomy );

                if ( $term instanceof WP_Term && (int) $term->parent !== $parent ) {
                    $moved = wp_update_term( $one, $taxonomy, array( 'parent' => $parent ) );
                    if ( is_wp_error( $moved ) ) {
                        return new WP_Error( 'vergeml_move_failed', $moved->get_error_message(), array( 'status' => 400 ) );
                    }
                }

                update_term_meta( $one, VERGEML_TERM_ORDER, $position + 1 );
            }

            break;

        case 'delete':
            if ( ! vergeml_term_in( $id, $taxonomy ) ) {
                return new WP_Error( 'vergeml_unknown_term', __( 'That folder does not exist.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
            }

            /*
             *  Children are re-parented to the deleted folder's own parent, so a
             *  delete removes one level and never a branch. WordPress does this
             *  itself for hierarchical taxonomies, but only reliably when the
             *  parent is set first -- so it is done explicitly rather than relied
             *  upon.
             *
             *  Files are never touched. They lose this one folder and keep every
             *  other, and the confirm dialog in the interface says so in those
             *  words, because "delete" reads as "delete my photos" to anyone who
             *  has not thought about it.
             */
            $term      = get_term( $id, $taxonomy );
            $up        = $term instanceof WP_Term ? (int) $term->parent : 0;
            $children  = get_terms( array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'parent'     => $id,
                'fields'     => 'ids',
            ) );

            if ( ! is_wp_error( $children ) ) {
                foreach ( $children as $child ) {
                    wp_update_term( (int) $child, $taxonomy, array( 'parent' => $up ) );
                }
            }

            wp_delete_term( $id, $taxonomy );
            break;

        default:
            return new WP_Error( 'vergeml_unknown_action', __( 'That is not something that can be done to a folder.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    wp_cache_delete( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );

    /*
     *  The whole tree comes back, not a patch of it. A rename touches one label,
     *  but a delete re-parents an unknown number of children and a move changes
     *  every count above both the old and the new parent. Returning the tree is
     *  a few kilobytes and removes an entire class of bug where the browser's
     *  idea of the shape drifts from the database's.
     */
    $tree = new WP_REST_Request( 'GET', '/' . VERGEML_REST_NS . '/tree' );
    $tree->set_param( 'taxonomy', $taxonomy );

    $for_type = (string) $request->get_param( 'post_type' );

    if ( $for_type && 'attachment' !== $for_type ) {
        $tree->set_param( 'post_type', $for_type );
    }

    $result = vergeml_rest_tree( $tree );
    $data   = $result instanceof WP_REST_Response ? $result->get_data() : array();

    return rest_ensure_response( array_merge(
        array( 'action' => $action, 'id' => $id ),
        is_array( $data ) ? $data : array()
    ) );
}


/**
 *  vergeml_term_in
 *
 *  Does this id name a term in this taxonomy? An id from another taxonomy is
 *  not a folder here, however real it is elsewhere.
 */

function vergeml_term_in( $id, $taxonomy ) {
    return get_term( absint( $id ), $taxonomy ) instanceof WP_Term;
}


/**
 *  vergeml_descendants
 *
 *  Every term beneath this one. get_term_children walks the cached hierarchy
 *  rather than querying per level, which matters on a deep tree and matters
 *  more because the cycle check runs on every folder drag.
 */

function vergeml_descendants( $id, $taxonomy ) {

    $kids = get_term_children( absint( $id ), $taxonomy );

    return is_wp_error( $kids ) ? array() : array_map( 'absint', $kids );
}


/**
 *  vergeml_place_last
 *
 *  Put a folder at the end of its siblings, but only where that means anything.
 *
 *  Order is stored as a number and the tree sorts on it before falling back to
 *  the name, so an unset order is zero and sorts ahead of every folder that has
 *  been arranged by hand. A folder created or moved into a branch somebody had
 *  already arranged therefore appeared at the top of it -- the one place nobody
 *  would put a new folder deliberately.
 *
 *  A branch nobody has arranged is left alone: every order there is zero, the
 *  tree is alphabetical, and stamping a number on one folder would quietly end
 *  that for the whole branch.
 */

function vergeml_place_last( $id, $parent, $taxonomy ) {

    $siblings = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'parent'     => (int) $parent,
        'fields'     => 'ids',
    ) );

    if ( is_wp_error( $siblings ) ) {
        return;
    }

    $last = 0;

    foreach ( $siblings as $sibling ) {

        if ( (int) $sibling === (int) $id ) {
            continue;
        }

        $order = (int) get_term_meta( $sibling, VERGEML_TERM_ORDER, true );

        if ( $order > $last ) {
            $last = $order;
        }
    }

    if ( $last > 0 ) {
        update_term_meta( $id, VERGEML_TERM_ORDER, $last + 1 );
    }
}


function vergeml_set_color( $id, $taxonomy, $color ) {

    $clean = vergeml_sanitize_color( $color );

    if ( '' === $clean ) {
        delete_term_meta( $id, VERGEML_TERM_COLOR );
        return;
    }

    update_term_meta( $id, VERGEML_TERM_COLOR, $clean );
}


/**
 *  Per-user tree state.
 *
 *  Which branches are open, which folder is selected, how wide the panel is,
 *  which skin. User meta rather than an option: this is a preference belonging
 *  to a person, and storing it site-wide means two editors fight over it.
 */

function vergeml_rest_state_get( WP_REST_Request $request ) {

    $taxonomy = $request->get_param( 'taxonomy' );

    if ( ! in_array( $taxonomy, vergeml_tree_taxonomies(), true ) ) {
        return new WP_Error( 'vergeml_unknown_taxonomy', __( 'That is not a media taxonomy.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    return rest_ensure_response( vergeml_tree_state( $taxonomy ) );
}


function vergeml_rest_state_set( WP_REST_Request $request ) {

    $taxonomy = $request->get_param( 'taxonomy' );

    if ( ! in_array( $taxonomy, vergeml_tree_taxonomies(), true ) ) {
        return new WP_Error( 'vergeml_unknown_taxonomy', __( 'That is not a media taxonomy.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    $all = get_user_meta( get_current_user_id(), VERGEML_USER_TREE_STATE, true );
    $all = is_array( $all ) ? $all : array();

    $mine = isset( $all[ $taxonomy ] ) && is_array( $all[ $taxonomy ] ) ? $all[ $taxonomy ] : array();

    if ( null !== $request->get_param( 'open' ) ) {
        // Capped: an open-branch list is a convenience, not somewhere to accept
        // an unbounded array into user meta on every click.
        $open         = array_slice( vergeml_ids( $request->get_param( 'open' ) ), 0, 500 );
        $mine['open'] = array_values( array_filter( $open ) );
    }

    if ( null !== $request->get_param( 'selected' ) ) {
        // -1 is 'Unfiled', a real value here, so this one is not an id.
        $mine['selected'] = (int) $request->get_param( 'selected' );
    }

    if ( null !== $request->get_param( 'collapsed' ) ) {
        $mine['collapsed'] = (int) (bool) $request->get_param( 'collapsed' );
    }

    if ( null !== $request->get_param( 'filtersOpen' ) ) {
        $mine['filtersOpen'] = (int) (bool) $request->get_param( 'filtersOpen' );
    }

    if ( null !== $request->get_param( 'aiOpen' ) ) {
        $mine['aiOpen'] = (int) (bool) $request->get_param( 'aiOpen' );
    }

    if ( null !== $request->get_param( 'width' ) ) {
        // Clamped to something a panel can actually be, so a stored value cannot
        // render the media library unusable until someone finds this row.
        $mine['width'] = max( 160, min( 640, absint( $request->get_param( 'width' ) ) ) );
    }

    if ( null !== $request->get_param( 'skin' ) ) {
        $skin = (string) $request->get_param( 'skin' );
        if ( ! in_array( $skin, vergeml_tree_skins(), true ) ) {
            return new WP_Error( 'vergeml_unknown_skin', __( 'That is not one of the skins.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
        }
        $mine['skin'] = $skin;
    }

    if ( null !== $request->get_param( 'density' ) ) {
        $density = (string) $request->get_param( 'density' );
        if ( ! in_array( $density, vergeml_tree_densities(), true ) ) {
            return new WP_Error( 'vergeml_unknown_density', __( 'That is not one of the densities.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
        }
        $mine['density'] = $density;
    }

    $all[ $taxonomy ] = $mine;

    update_user_meta( get_current_user_id(), VERGEML_USER_TREE_STATE, $all );

    return rest_ensure_response( vergeml_tree_state( $taxonomy ) );
}
