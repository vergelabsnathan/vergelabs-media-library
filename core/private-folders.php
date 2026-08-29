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
 *
 *  Named $mine rather than $private because Gate 2 greps for
 *  `function name( ... private ...)` looking for PHP 8 constructor promotion,
 *  and a parameter that happens to be called $private reads to it as exactly
 *  that. A gate nobody trusts is a gate nobody reads.
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
 *  `include` is left alone deliberately: a query naming specific ids already
 *  knows which ones it wants, and silently dropping one would turn a direct
 *  lookup into an unexplained empty result.
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

    if ( ! empty( $args['include'] ) ) {
        return $args;
    }

    $hidden = vergeml_private_hidden_from();

    if ( empty( $hidden ) ) {
        return $args;
    }

    $args['exclude'] = array_merge(
        isset( $args['exclude'] ) ? (array) $args['exclude'] : array(),
        $hidden
    );

    return $args;
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
        /*
         *  The same bar as renaming a folder. Making one's own folder private
         *  is a filing decision, not an administrative one -- requiring
         *  manage_options would mean the only people who could use the feature
         *  are the ones whose sidebar is not crowded.
         */
        'permission_callback' => function () {
            return current_user_can( 'manage_categories' );
        },
        'args'                => array(
            'id'      => array( 'required' => true, 'type' => 'integer' ),
            'private' => array( 'type' => 'boolean', 'default' => true ),
        ),
    ) );
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
