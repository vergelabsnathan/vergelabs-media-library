<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  Folders only one person sees.
 *
 *  A magazine or an agency has ten people uploading into one library, and ten
 *  people's filing in one sidebar is nobody's filing. So a folder can belong to
 *  the person who made it, and stay out of everyone else's way.
 *
 *  **This is organisation, not access control, and the screen says so.**
 *
 *  A folder here is a term -- a label stuck on a file. Hiding the label does
 *  not hide the file: anyone who can open the media library can still reach
 *  that picture by searching, by its URL, or through the REST API, exactly as
 *  they could before. Hiding the files as well would look like security while
 *  being none, and somebody would eventually put something sensitive in a
 *  "private" folder believing it was protected. That is the one outcome this
 *  feature must not produce, so the label reads "only you see this folder" and
 *  never "only you can see these files".
 *
 *  Administrators see other people's private folders, marked with whose they
 *  are. Without that, a folder belonging to a deleted user would be invisible
 *  to everyone and impossible to tidy up -- and an administrator who wants to
 *  read somebody's filing can read the database anyway.
 *
 *  ## Why an option and not a term meta query
 *
 *  Which folders are private is kept in one autoloaded option, term id to
 *  owner id. Term meta would be the obvious home, but every folder query
 *  anywhere would then need a meta join or a second query to know what to
 *  exclude, and `vergeml/v1/tree` has a query budget of seven that must not
 *  move with folder count.
 *
 *  Autoloaded, this costs nothing: the option is already in memory before the
 *  first query runs, so a site with no private folders pays exactly zero, and a
 *  site with fifty pays zero as well. The option holds ids and ids only -- it
 *  is small by construction, and it is rebuilt from nothing if it is lost.
 */


/** term id => owner user id. Autoloaded on purpose; see above. */
const VERGEML_PRIVATE_OPTION = 'vergeml_private_folders';


function vergeml_private_map() {

    $map = get_option( VERGEML_PRIVATE_OPTION, array() );

    if ( ! is_array( $map ) ) {
        return array();
    }

    $clean = array();

    foreach ( $map as $term_id => $owner ) {
        $term_id = (int) $term_id;
        $owner   = (int) $owner;

        if ( $term_id > 0 && $owner > 0 ) {
            $clean[ $term_id ] = $owner;
        }
    }

    return $clean;
}


function vergeml_private_owner( $term_id ) {
    $map = vergeml_private_map();
    return isset( $map[ (int) $term_id ] ) ? (int) $map[ (int) $term_id ] : 0;
}


/**
 *  vergeml_private_set
 *
 *  @param int  $term_id
 *  @param bool $mine  true to make it the current user's, false to share it.
 */
function vergeml_private_set( $term_id, $mine ) {

    $term_id = (int) $term_id;
    $map     = vergeml_private_map();

    if ( $mine ) {
        $map[ $term_id ] = get_current_user_id();
    } else {
        unset( $map[ $term_id ] );
    }

    update_option( VERGEML_PRIVATE_OPTION, $map, true );

    return $map;
}


/**
 *  Which folders this user must not be shown.
 *
 *  Everyone else's, unless the viewer administers the site. An empty array
 *  is the answer for almost every site that ever installs this.
 */
function vergeml_private_hidden_from( $user_id = 0 ) {

    $map = vergeml_private_map();

    if ( empty( $map ) ) {
        return array();
    }

    if ( current_user_can( 'manage_options' ) ) {
        return array();
    }

    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    $hidden  = array();

    foreach ( $map as $term_id => $owner ) {
        if ( $owner !== $user_id ) {
            $hidden[] = $term_id;
        }
    }

    return $hidden;
}


/**
 *  Keep other people's folders out of every folder query, not only the tree.
 *
 *  A folder hidden from the sidebar but still offered in the media library's
 *  filter dropdown would be a strange half-feature, and the name is the thing
 *  being hidden.
 *
 *  `include` is left alone inside the plugin, where a query naming ids already
 *  knows which it wants; a REST request names them for the caller, so there a
 *  hidden id is dropped.
 */
add_filter( 'get_terms_args', 'vergeml_private_filter_terms', 10, 2 );

function vergeml_private_filter_terms( $args, $taxonomies ) {

    if ( ! function_exists( 'vergeml_tree_taxonomies' ) ) {
        return $args;
    }

    $ours = array_intersect( (array) $taxonomies, vergeml_tree_taxonomies() );

    if ( empty( $ours ) ) {
        return $args;
    }

    if ( ! empty( $args['include'] ) && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return $args;
    }

    // Core's hierarchy option is shared by every user and holds only ids: it must hold every folder, whoever rebuilds it.
    if ( isset( $args['fields'] ) && 'id=>parent' === $args['fields'] ) {
        return $args;
    }

    $hidden = vergeml_private_hidden_from();

    if ( empty( $hidden ) ) {
        return $args;
    }

    if ( ! empty( $args['include'] ) ) {
        // Naming only hidden folders finds nothing, not every other folder.
        $kept            = array_values( array_diff( wp_parse_id_list( $args['include'] ), $hidden ) );
        $args['include'] = $kept ? $kept : array( 0 );

        return $args;
    }

    // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- hides the folders marked private from the term list: a handful of term ids, not posts.
    $args['exclude'] = array_merge(
        isset( $args['exclude'] ) ? (array) $args['exclude'] : array(),
        $hidden
    );

    return $args;
}


/**
 *  Core caches a file's folders per file, not per user, and the filter above decides
 *  what goes in. So once a site has had a private folder that cache lasts one request,
 *  for one person: a persistent object cache would hand one person's list to the next.
 *  It stays that way, because core's deletes never reach the backend meanwhile and
 *  going back would serve the lists from before.
 */
add_action( 'wp_loaded', 'vergeml_private_cache_per_request' );

function vergeml_private_cache_per_request() {

    $first = false;

    if ( ! get_option( 'vergeml_private_used' ) ) {

        if ( ! vergeml_private_map() ) {
            return;
        }

        update_option( 'vergeml_private_used', 1, true );
        $first = function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' );
    }

    foreach ( vergeml_tree_taxonomies() as $taxonomy ) {

        if ( $first ) {
            wp_cache_flush_group( $taxonomy . '_relationships' );
        }

        wp_cache_add_non_persistent_groups( $taxonomy . '_relationships' );
    }
}


add_filter( 'get_the_terms', 'vergeml_private_filter_object_terms', 10, 3 );

function vergeml_private_filter_object_terms( $terms, $post_id, $taxonomy ) {

    if ( ! is_array( $terms ) || ! in_array( $taxonomy, vergeml_tree_taxonomies(), true ) ) {
        return $terms;
    }

    $hidden = vergeml_private_hidden_from();
    $kept   = array();

    foreach ( $terms as $term ) {
        if ( ! in_array( (int) $term->term_id, $hidden, true ) ) {
            $kept[] = $term;
        }
    }

    return $kept ? $kept : false;
}


/**
 *  True when this folder is somebody else's private folder. The plugin's admin doors
 *  that file into a folder or list one ask this, because get_term() does not pass
 *  through the filter above. A gallery placed in a published post keeps showing its
 *  pictures: visitors see that gallery by the author's choice.
 */
function vergeml_private_is_hidden( $term_id ) {
    return in_array( (int) $term_id, vergeml_private_hidden_from(), true );
}


/**
 *  Core's term screens and actions ask these capabilities: somebody else's private
 *  folder cannot be opened, renamed, deleted or assigned there either.
 */
add_filter( 'map_meta_cap', 'vergeml_private_map_meta_cap', 10, 4 );

function vergeml_private_map_meta_cap( $caps, $cap, $user_id, $args ) {

    if ( ! in_array( $cap, array( 'edit_term', 'delete_term', 'assign_term' ), true ) || empty( $args[0] ) || (int) $user_id !== get_current_user_id() ) {
        return $caps;
    }

    return vergeml_private_is_hidden( (int) $args[0] ) ? array( 'do_not_allow' ) : $caps;
}


/**
 *  Somebody else's private folder is not a parent: a shared folder moved under one
 *  stops being drawn for everyone who cannot see it. Core's forms come through here.
 */
add_filter( 'wp_update_term_parent', 'vergeml_private_keep_parent', 10, 3 );

function vergeml_private_keep_parent( $parent, $term_id, $taxonomy ) {

    if ( ! vergeml_private_is_hidden( (int) $parent ) ) {
        return $parent;
    }

    $term = get_term( $term_id, $taxonomy );

    return $term instanceof WP_Term ? $term->parent : 0;
}


add_filter( 'pre_insert_term', 'vergeml_private_refuse_parent', 10, 3 );

function vergeml_private_refuse_parent( $term, $taxonomy, $args = array() ) {

    if ( ! empty( $args['parent'] ) && vergeml_private_is_hidden( (int) $args['parent'] ) ) {
        return new WP_Error( 'vergeml_no_folder', __( 'That folder does not exist.', 'vergelabs-media-library' ) );
    }

    return $term;
}


/**
 *  Core's own term routes for the folder taxonomies.
 *
 *  A non-public taxonomy's names are for people who may file into it, as on /tree,
 *  and somebody else's private folder is not reachable by id, include or parent.
 */
add_filter( 'rest_request_before_callbacks', 'vergeml_private_guard_core_rest', 10, 3 );

function vergeml_private_guard_core_rest( $response, $handler, $request ) {

    // A callback can be a closure or a function name: only a controller's array form says which route this is.
    if ( is_wp_error( $response ) || ! isset( $handler['callback'] ) || ! is_array( $handler['callback'] ) || empty( $handler['callback'][0] ) ) {
        return $response;
    }

    $controller = $handler['callback'][0];

    // Core's post and media routes filter and file by a folder's id through a parameter named after its REST base.
    if ( $controller instanceof WP_REST_Posts_Controller ) {
        vergeml_private_strip_media_params( $request );
        return $response;
    }

    if ( ! $controller instanceof WP_REST_Terms_Controller ) {
        return $response;
    }

    // From the controller and the matched id, never the route's spelling: WordPress matches routes loosely.
    $schema   = $controller->get_item_schema();
    $taxonomy = isset( $schema['title'] ) ? (string) $schema['title'] : '';
    $object   = get_taxonomy( $taxonomy );

    if ( ! $object || ! in_array( $taxonomy, vergeml_tree_taxonomies(), true ) ) {
        return $response;
    }

    // Only this plugin's own taxonomies: another plugin's stay as that plugin registered them.
    if ( 0 === strpos( $taxonomy, 'vergeml_' ) && empty( $object->public ) && ! current_user_can( $object->cap->assign_terms ) ) {
        return new WP_Error( 'rest_forbidden', __( 'You cannot read these folders.', 'vergelabs-media-library' ), array( 'status' => rest_authorization_required_code() ) );
    }

    $url = $request->get_url_params();

    if ( ! empty( $url['id'] ) && vergeml_private_is_hidden( (int) $url['id'] ) ) {
        return new WP_Error( 'rest_term_invalid', __( 'That folder does not exist.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    // Naming only hidden folders finds nothing, exactly as naming ids that do not exist.
    if ( $request->get_param( 'include' ) ) {
        $kept = array_values( array_diff( wp_parse_id_list( $request->get_param( 'include' ) ), vergeml_private_hidden_from() ) );
        $request->set_param( 'include', $kept ? $kept : array( 0 ) );
    }

    if ( vergeml_private_is_hidden( (int) $request->get_param( 'parent' ) ) ) {

        if ( 'GET' !== $request->get_method() ) {
            return new WP_Error( 'rest_term_invalid', __( 'That folder does not exist.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
        }

        $request->set_param( 'include', array( 0 ) );
    }

    return $response;
}


/**
 *  A hidden folder's id means nothing on core's post and media routes: a read naming only
 *  hidden folders lists nothing, and a write does not file into one.
 */
function vergeml_private_strip_media_params( $request ) {

    $hidden = vergeml_private_hidden_from();

    if ( ! $hidden ) {
        return;
    }

    foreach ( vergeml_tree_taxonomies() as $taxonomy ) {

        $object = get_taxonomy( $taxonomy );
        $base   = $object && $object->rest_base ? $object->rest_base : $taxonomy;

        // A request that names no hidden id is left exactly as it came, in either of core's two forms.
        foreach ( array( $base, $base . '_exclude' ) as $param ) {

            $value = $request->get_param( $param );

            if ( null === $value ) {
                continue;
            }

            $object_form = is_array( $value ) && isset( $value['terms'] );
            $asked       = wp_parse_id_list( $object_form ? $value['terms'] : $value );

            if ( ! array_intersect( $asked, $hidden ) ) {
                continue;
            }

            $kept = array_values( array_diff( $asked, $hidden ) );

            // A read naming only hidden folders lists nothing; excluding one shows nothing by what goes missing.
            if ( ! $kept && $base === $param && 'GET' === $request->get_method() ) {
                $kept = array( 0 );
            }

            if ( $object_form ) {
                $value['terms'] = $kept;
                $kept           = $value;
            }

            $request->set_param( $param, $kept );
        }
    }
}


/**
 *  A deleted folder must not leave its id behind. The option would otherwise
 *  grow for ever and, once term ids were reused, start hiding folders that
 *  have nothing to do with the person who made the original.
 */
add_action( 'delete_term', 'vergeml_private_forget', 10, 3 );

function vergeml_private_forget( $term_id, $tt_id, $taxonomy ) {

    $map = vergeml_private_map();

    if ( ! isset( $map[ (int) $term_id ] ) ) {
        return;
    }

    unset( $map[ (int) $term_id ] );
    update_option( VERGEML_PRIVATE_OPTION, $map, true );
}


/* ----------------------------------------------------------------- the API */

add_action( 'rest_api_init', 'vergeml_private_routes' );

function vergeml_private_routes() {

    register_rest_route( VERGEML_REST_NS, '/folder-privacy', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_private_rest',
        'permission_callback' => function ( WP_REST_Request $request ) { return current_user_can( 'manage_options' ) && vergeml_private_can_edit( $request ); },
        'args'                => array(
            'id'      => array( 'required' => true, 'type' => 'integer' ),
            'private' => array( 'type' => 'boolean', 'default' => true ),
        ),
    ) );
}


/*
 *  Administrators only: making a shared folder private hides it from everyone
 *  else on the site (wordpress.org review 2, 2026-10-03).
 */
function vergeml_private_can_edit( WP_REST_Request $request ) {

    $term_id = (int) $request->get_param( 'id' );

    return current_user_can( 'manage_options' )
        && ( ! ( get_term( $term_id ) instanceof WP_Term ) || current_user_can( 'edit_term', $term_id ) );
}


function vergeml_private_rest( WP_REST_Request $request ) {

    $term_id = (int) $request->get_param( 'id' );
    $term    = get_term( $term_id );

    if ( ! $term || is_wp_error( $term ) || ! in_array( $term->taxonomy, vergeml_tree_taxonomies(), true ) ) {
        return new WP_Error( 'vergeml_no_folder', __( 'That folder does not exist.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    $owner = vergeml_private_owner( $term_id );

    /*
     *  Somebody else's folder is not yours to share out, even if you can see
     *  it because you administer the site. Taking it over silently is how a
     *  colleague's filing disappears.
     */
    if ( $owner && $owner !== get_current_user_id() ) {
        return new WP_Error(
            'vergeml_not_yours',
            __( 'That folder belongs to somebody else.', 'vergelabs-media-library' ),
            array( 'status' => 403 )
        );
    }

    vergeml_private_set( $term_id, (bool) $request->get_param( 'private' ) );

    return rest_ensure_response( array(
        'id'      => $term_id,
        'private' => (bool) vergeml_private_owner( $term_id ),
        'mine'    => true,
    ) );
}


/**
 *  What the row shows.
 *
 *  Read from the autoloaded map, so this adds no query to the tree and its
 *  budget of seven is untouched.
 */
function vergeml_private_node( $term_id ) {

    $owner = vergeml_private_owner( $term_id );

    if ( ! $owner ) {
        return null;
    }

    $mine = $owner === get_current_user_id();

    if ( $mine ) {
        return array( 'mine' => true, 'who' => '' );
    }

    // Only an administrator ever sees this branch: everyone else had the
    // folder removed from the query before it got here.
    $user = get_userdata( $owner );

    return array(
        'mine' => false,
        'who'  => $user ? $user->display_name : __( 'a deleted user', 'vergelabs-media-library' ),
    );
}
