<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  The two endpoints the folder tree runs on.
 *
 *  T0 of the tree build: no interface yet, nothing visible, nothing that can
 *  break an existing screen. Just the substrate, so the tree itself is a client
 *  of a tested API rather than a pile of ajax handlers grown alongside it.
 *
 *  Two endpoints and no more, on purpose:
 *
 *    GET  vergeml/v1/tree    everything needed to draw the tree, in one call.
 *    POST vergeml/v1/assign  the only write path. Drag, multi-drag, keyboard
 *                            assign and undo all come through here, so there is
 *                            one place where permission is decided and one
 *                            place where counts are kept honest.
 *
 *  A folder is a term. Not a row in a private table -- which is what makes the
 *  structure survive us being uninstalled, and what lets a file live in two
 *  folders at once. Everything below is ordinary taxonomy work.
 *
 *  @since 3.1
 */


const VERGEML_REST_NS = 'vergeml/v1';

/** Term meta the tree adds. Absent meta means default, so there is no migration. */
const VERGEML_TERM_COLOR = 'vergeml_color';
const VERGEML_TERM_ORDER = 'vergeml_order';

/** Where a user's own open branches and selection live. */
const VERGEML_USER_TREE_STATE = 'vergeml_tree_state';


add_action( 'init', 'vergeml_register_tree_meta' );

function vergeml_register_tree_meta() {

    foreach ( vergeml_tree_taxonomies() as $taxonomy ) {

        register_term_meta( $taxonomy, VERGEML_TERM_COLOR, array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'vergeml_sanitize_color',
            'auth_callback'     => function () { return current_user_can( 'manage_categories' ); },
        ) );

        register_term_meta( $taxonomy, VERGEML_TERM_ORDER, array(
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'absint',
            'auth_callback'     => function () { return current_user_can( 'manage_categories' ); },
        ) );
    }
}


/**
 *  vergeml_sanitize_color
 *
 *  A hex colour or nothing. Anything else is nothing -- a colour arriving from
 *  a request has no business becoming a style attribute unchecked.
 */

function vergeml_sanitize_color( $value ) {

    $value = is_string( $value ) ? trim( $value ) : '';

    return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : '';
}


/**
 *  vergeml_tree_taxonomies
 *
 *  The taxonomies the tree may show: ours, on attachments. Flat ones are
 *  included because the endpoint returns them as flat lists -- a tag cloud
 *  drawn as a tree is a lie about the data.
 */

/**
 *  vergeml_ids
 *
 *  Ids from a request, as given -- not bent into different ones.
 *
 *  absint() was doing this, and absint( -5 ) is 5. A caller sending a negative id
 *  did not get an error, they got a valid operation on an entirely different
 *  object: -3 meant folder 3, and asking to file attachment -5 filed attachment
 *  5. Nothing was ever exposed that a capability check would have allowed
 *  through, but "the id you sent was rejected" and "we acted on a different one"
 *  are not close to the same answer.
 *
 *  Anything that is not a positive integer is dropped, so the caller gets the
 *  "nothing to do" refusal the endpoints already have.
 */

function vergeml_ids( $value ) {

    $out = array();

    foreach ( (array) $value as $one ) {

        if ( ! is_numeric( $one ) ) {
            continue;
        }

        $one = (int) $one;

        if ( $one > 0 ) {
            $out[] = $one;
        }
    }

    return array_values( array_unique( $out ) );
}


/**
 *  vergeml_id
 *
 *  One id, or zero for "none given".
 */

function vergeml_id( $value ) {
    $ids = vergeml_ids( $value );
    return $ids ? (int) $ids[0] : 0;
}


function vergeml_tree_taxonomies() {

    $found = get_object_taxonomies( 'attachment', 'objects' );
    $mine  = array();

    foreach ( $found as $taxonomy ) {
        // Core's own attachment taxonomies are not ours to redraw.
        if ( in_array( $taxonomy->name, array( 'post_tag', 'category' ), true ) )
            continue;

        $mine[] = $taxonomy->name;
    }

    return $mine;
}


add_action( 'rest_api_init', 'vergeml_register_tree_routes' );

function vergeml_register_tree_routes() {

    register_rest_route( VERGEML_REST_NS, '/tree', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'vergeml_rest_tree',
        'permission_callback' => 'vergeml_can_read_tree',
        'args'                => array(
            'taxonomy' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_key',
            ),
            /*
             *  Which post type the counts are about. Attachments unless asked
             *  otherwise, so every existing caller keeps the answer it had.
             */
            'post_type' => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_key',
            ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/assign', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_rest_assign',
        'permission_callback' => 'vergeml_can_assign',
        'args'                => array(
            'taxonomy'    => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            /*
             *  Which list the caller is looking at, so the counts that come back
             *  are the ones it is showing. A post screen filing a post needs the
             *  folder's post count, not its file count.
             */
            'post_type'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            // Not required at this layer: a batch call carries its attachments
            // inside each group instead. The handler refuses a call that has
            // neither, so nothing gets through unnamed.
            'attachments' => array( 'type' => 'array' ),
            'add'         => array( 'type' => 'array' ),
            'remove'      => array( 'type' => 'array' ),
            'mode'        => array( 'type' => 'string' ),
            'batch'       => array( 'type' => 'array' ),
        ),
    ) );
}


function vergeml_can_read_tree() {
    return current_user_can( 'upload_files' );
}


/**
 *  vergeml_can_assign
 *
 *  Coarse gate only. The real check is per attachment inside the handler,
 *  because a batch is a batch of separate permissions: one call naming forty
 *  files must not be authorised by whether the caller may edit the first.
 */

function vergeml_can_assign() {
    /*
     *  Coarse gate over two different jobs now: filing a file, and filing a post.
     *  Someone who edits posts but cannot upload is still allowed to reach the
     *  endpoint -- what they may actually touch is decided per object inside the
     *  handler, which is where it has always really been decided.
     */
    return current_user_can( 'upload_files' ) || current_user_can( 'edit_posts' );
}


/**
 *  vergeml_rest_tree
 *
 *  Everything needed to draw the tree, in one response: the terms, their
 *  parents, their counts, their colours, and this user's open branches.
 *
 *  One call because the tree cannot draw usefully with half of it -- and two
 *  calls only widen the window where it shows a half-built answer.
 */

function vergeml_rest_tree( WP_REST_Request $request ) {

    $taxonomy = $request->get_param( 'taxonomy' );

    if ( ! in_array( $taxonomy, vergeml_tree_taxonomies(), true ) ) {
        return new WP_Error( 'vergeml_unknown_taxonomy', __( 'That is not a media taxonomy.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    $object = get_taxonomy( $taxonomy );

    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ) );

    if ( is_wp_error( $terms ) ) {
        return new WP_Error( 'vergeml_terms_failed', $terms->get_error_message(), array( 'status' => 500 ) );
    }

    /*
     *  Counts belong to a post type, not to a term.
     *
     *  The number stored on a term is its attachment count -- that is what the
     *  count callback maintains and what the media library reads, and it is free
     *  because get_terms has already fetched it. A post screen is asking a
     *  different question, so it gets a different answer: one grouped query for
     *  every folder at once, which is why the tree costs one extra query there
     *  rather than one per folder.
     */
    $post_type = (string) $request->get_param( 'post_type' );
    $post_type = ( $post_type && 'attachment' !== $post_type && post_type_exists( $post_type ) ) ? $post_type : '';

    if ( $post_type && function_exists( 'vergeml_folder_counts' ) ) {
        $counts     = vergeml_folder_counts( $taxonomy, $post_type );
        $unassigned = vergeml_folder_unfiled_count( $taxonomy, $post_type );
    } else {
        $counts     = null;
        $unassigned = vergeml_count_unassigned( $taxonomy );
    }

    $nodes = array();

    foreach ( $terms as $term ) {
        $order = get_term_meta( $term->term_id, VERGEML_TERM_ORDER, true );

        $nodes[] = array(
            'id'     => (int) $term->term_id,
            'parent' => (int) $term->parent,
            'name'   => $term->name,
            'slug'   => $term->slug,
            'count'  => ( null === $counts )
                ? (int) $term->count
                : ( isset( $counts[ (int) $term->term_id ] ) ? (int) $counts[ (int) $term->term_id ] : 0 ),
            'color'  => (string) get_term_meta( $term->term_id, VERGEML_TERM_COLOR, true ),
            'order'  => $order === '' ? 0 : (int) $order,
            /*
             *  Null unless the folder belongs to somebody. Read from an
             *  autoloaded option rather than term meta, so this adds no query
             *  and the budget of seven above is untouched -- see
             *  core/private-folders.php for why that decided the storage.
             */
            'private' => function_exists( 'vergeml_private_node' ) ? vergeml_private_node( $term->term_id ) : null,
        );
    }

    return rest_ensure_response( array(
        'taxonomy'     => $taxonomy,
        'hierarchical' => $object instanceof WP_Taxonomy ? (bool) $object->hierarchical : false,
        'label'        => $object instanceof WP_Taxonomy ? $object->labels->name : $taxonomy,
        'nodes'        => $nodes,
        'postType'     => $post_type ? $post_type : 'attachment',
        /*
         *  The smart folders, media-library only: their questions are about
         *  files, and a post screen asking "which posts have no alt text" is
         *  a category error.
         */
        'smart'        => vergeml_smart_for_tree( $post_type ),
        /*
         *  What the AI group says above its own rows, or null when the group
         *  is switched off. Its own key rather than a row, because "nothing
         *  has been described yet" is a statement about the group and not a
         *  folder somebody could open.
         */
        'ai'           => vergeml_ai_for_tree( $post_type ),
        /*
         *  Things with no term in this taxonomy. Counted here rather than by the
         *  browser, which would otherwise have to fetch the list to find out.
         */
        'unassigned'   => $unassigned,
        'state'        => vergeml_tree_state( $taxonomy ),
    ) );
}


/**
 *  vergeml_smart_for_tree
 *
 *  The smart rows the panel shows: label, count, and whether the number is
 *  real yet. Counted once for all five, not once each.
 */

function vergeml_smart_for_tree( $post_type ) {

    if ( $post_type || ! function_exists( 'vergeml_smart_folders' ) ) {
        return array();
    }

    $counts = vergeml_smart_counts();
    $out    = array();

    foreach ( vergeml_smart_folders() as $key => $spec ) {

        $group = isset( $spec['group'] ) ? (string) $spec['group'] : 'clean';
        $count = isset( $counts[ $key ] ) ? $counts[ $key ] : null;

        /*
         *  An AI folder with nothing in it is not shown at all -- a photo
         *  library has no invoices and does not need five empty rows saying
         *  so. Zero and null are different answers here and only zero is
         *  hidden: null means the index has not been looked at, which the
         *  group says once at the top rather than thirteen times.
         */
        if ( 'ai' === $group && 0 === $count ) {
            continue;
        }

        if ( 'ai' === $group && null === $count ) {
            continue;
        }

        $out[] = array(
            'key'   => $key,
            'label' => $spec['label'],
            'count' => $count,
            'scan'  => ! empty( $spec['scan'] ),
            'group' => $group,
        );
    }

    return $out;
}


/**
 *  vergeml_ai_for_tree
 *
 *  What the AI group says about itself, above its rows.
 *
 *  Three states the panel has to tell apart, and the numbers here are what it
 *  tells them apart by: switched off entirely; on but nothing looked at yet,
 *  which draws the ladder to the AI screen; on and partly looked at, which
 *  draws the rows plus how much of the library they are drawn from. A count
 *  of forty on a library where two hundred of eight thousand files have been
 *  described is not forty, and saying so is cheaper than being wrong.
 */

function vergeml_ai_for_tree( $post_type ) {

    if ( $post_type || ! function_exists( 'vergeml_ai_folders_enabled' ) ) {
        return null;
    }

    if ( ! vergeml_ai_folders_enabled() ) {
        return null;
    }

    $counts = vergeml_smart_counts();

    $described = isset( $counts['_described'] ) ? $counts['_described'] : null;
    $total     = isset( $counts['_total'] ) ? $counts['_total'] : null;

    return array(
        'described' => $described,
        'total'     => $total,
        // Nothing has been looked at: either the index has never been asked,
        // or it holds no finished descriptions.
        'ladder'    => ( null === $described || 0 === (int) $described ),
        'url'       => admin_url( 'admin.php?page=media-ai' ),
    );
}


/**
 *  vergeml_count_unassigned
 *
 *  How many attachments hold no term in this taxonomy. A NOT EXISTS query
 *  rather than fetching ids and counting them in PHP, because on a large
 *  library the difference is the whole response time.
 */

function vergeml_count_unassigned( $taxonomy ) {

    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a count with no core API equivalent; cached below.
    $cached = wp_cache_get( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );
    if ( false !== $cached )
        return (int) $cached;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
              WHERE p.post_type = 'attachment'
                AND NOT EXISTS (
                    SELECT 1 FROM {$wpdb->term_relationships} tr
                      JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                     WHERE tr.object_id = p.ID AND tt.taxonomy = %s
                )",
            $taxonomy
        )
    );

    wp_cache_set( 'vergeml_unassigned_' . $taxonomy, $count, 'vergeml', 5 * MINUTE_IN_SECONDS );

    return $count;
}


function vergeml_tree_state( $taxonomy ) {

    $all = get_user_meta( get_current_user_id(), VERGEML_USER_TREE_STATE, true );
    $all = is_array( $all ) ? $all : array();

    $mine = isset( $all[ $taxonomy ] ) && is_array( $all[ $taxonomy ] ) ? $all[ $taxonomy ] : array();

    /*
     *  A remembered selection is only worth remembering while the folder exists.
     *
     *  Delete the folder you were looking at and the selection outlived it: every
     *  later visit filtered the library to a term that was not there any more, so
     *  the media library came up empty, stayed empty, and said nothing about why.
     *  The only way out was knowing to click "All files" -- and somebody whose
     *  library has just gone blank is not thinking about the sidebar. Checked on
     *  the way out rather than on delete, so it heals however the folder went:
     *  our delete, another plugin's, WP-CLI, or a database restore.
     */
    $selected = isset( $mine['selected'] ) ? (int) $mine['selected'] : 0;

    if ( $selected > 0 && ! get_term( $selected, $taxonomy ) instanceof WP_Term ) {
        $selected = 0;
    }

    return array(
        'open'     => isset( $mine['open'] ) && is_array( $mine['open'] ) ? array_map( 'intval', $mine['open'] ) : array(),
        'selected' => $selected,
        'width'    => isset( $mine['width'] ) ? (int) $mine['width'] : 0,
        'collapsed' => ! empty( $mine['collapsed'] ) ? 1 : 0,
        'filtersOpen' => isset( $mine['filtersOpen'] ) ? (int) $mine['filtersOpen'] : 1,
        // The AI group, open by default like the filters group. It only
        // appears at all once there is something to open.
        'aiOpen'      => isset( $mine['aiOpen'] ) ? (int) $mine['aiOpen'] : 1,
        /*
         *  'native' derives its accent from whichever admin colour scheme this
         *  user already chose, so the tree looks like part of the admin rather
         *  than bolted onto it. That is why it is the default.
         */
        'skin'     => isset( $mine['skin'] ) ? (string) $mine['skin'] : 'native',
        'density'  => isset( $mine['density'] ) ? (string) $mine['density'] : 'comfortable',
    );
}


/**
 *  vergeml_assign_count
 *
 *  A folder's count, for whichever list asked.
 *
 *  The stored count is the attachment count and stays that way, so a post screen
 *  cannot read it. Rather than fetching the whole tree again after every drop,
 *  the assign response carries the number for the screen that made the call.
 */

function vergeml_assign_count( $term_id, $taxonomy, $post_type ) {

    if ( $post_type && 'attachment' !== $post_type && function_exists( 'vergeml_folder_counts' ) ) {
        $counts = vergeml_folder_counts( $taxonomy, $post_type );
        return isset( $counts[ (int) $term_id ] ) ? (int) $counts[ (int) $term_id ] : 0;
    }

    $term = get_term( $term_id, $taxonomy );

    return $term instanceof WP_Term ? (int) $term->count : 0;
}


/**
 *  vergeml_rest_assign
 *
 *  The only write path the tree has.
 *
 *  Permission is decided per attachment, not per request: a call naming forty
 *  files is forty separate questions, and answering them once with the first
 *  file's answer is how a contributor ends up retagging somebody else's
 *  library. Files the caller may not edit are skipped and reported, never
 *  silently dropped and never fatal.
 *
 *  Counting is deferred across the batch and resumed once. Recounting a
 *  taxonomy after every one of forty assignments is what makes a folder drag
 *  take thirty seconds on a large library.
 */

function vergeml_rest_assign( WP_REST_Request $request ) {

    $taxonomy = $request->get_param( 'taxonomy' );

    if ( ! in_array( $taxonomy, vergeml_tree_taxonomies(), true ) ) {
        return new WP_Error( 'vergeml_unknown_taxonomy', __( 'That is not a media taxonomy.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    /*
     *  A batch is several (attachments, add, remove) groups in one call, and it
     *  exists because that is the shape an undo has to take: files in a single
     *  drag can have moved differently, so taking it back is several different
     *  operations that must all land or none of them.
     *
     *  Applied by calling this same handler per group, so a batch cannot behave
     *  differently from the individual calls it is made of.
     */
    $batch = $request->get_param( 'batch' );

    if ( is_array( $batch ) && ! empty( $batch ) ) {
        return vergeml_rest_assign_batch( $request, $taxonomy, $batch );
    }

    $attachments = vergeml_ids( $request->get_param( 'attachments' ) );
    $add         = vergeml_ids( $request->get_param( 'add' ) );
    $remove      = vergeml_ids( $request->get_param( 'remove' ) );

    /*
     *  'move' empties the taxonomy for these files before adding, which is what
     *  a one-folder-per-file library expects from a drag. Enforced here rather
     *  than in the browser: a mode that only exists in the interface is a mode
     *  the next caller ignores.
     */
    $mode = $request->get_param( 'mode' ) === 'move' ? 'move' : 'add';

    if ( empty( $attachments ) ) {
        return new WP_Error( 'vergeml_nothing_to_do', __( 'No files were named.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    if ( empty( $add ) && empty( $remove ) && 'move' !== $mode ) {
        return new WP_Error( 'vergeml_nothing_to_do', __( 'No terms were named.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    // Terms must exist in this taxonomy; an id from another one is refused
    // rather than quietly creating a relationship that nothing will show.
    foreach ( array_merge( $add, $remove ) as $term_id ) {
        $term = get_term( $term_id, $taxonomy );
        if ( ! $term instanceof WP_Term ) {
            return new WP_Error( 'vergeml_unknown_term', __( 'One of those folders does not exist.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
        }
    }

    $changed = array();
    $refused = array();

    /*
     *  Undo is built from what actually changed, never from what was asked for.
     *
     *  Those are different things and the difference destroys work. Drag three
     *  files onto Summer when one of them was already in Summer, and an inverse
     *  derived from the request removes Summer from all three -- including the
     *  file that was there first and should have stayed. The user asked to take
     *  back their last action and lost an earlier one instead.
     *
     *  So each file's membership is read before and compared after, and the
     *  inverse names only files whose membership actually moved. A file already
     *  in the requested state still counts as changed -- the caller's intent was
     *  satisfied -- but contributes nothing to the undo.
     */
    $deltas = array();

    wp_defer_term_counting( true );

    foreach ( $attachments as $attachment_id ) {

        /*
         *  The object must be something this taxonomy is actually registered for.
         *
         *  This used to be "must be an attachment", which stopped being the whole
         *  answer once a media taxonomy could be turned on for posts as well. The
         *  registration is the right boundary rather than a list of post types
         *  kept here: turning the setting off immediately stops the endpoint
         *  accepting posts, without this code knowing the setting exists.
         */
        $object_type = get_post_type( $attachment_id );

        if ( ! $object_type || ! is_object_in_taxonomy( $object_type, $taxonomy ) ) {
            $refused[] = $attachment_id;
            continue;
        }

        if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
            $refused[] = $attachment_id;
            continue;
        }

        $before = wp_get_object_terms( $attachment_id, $taxonomy, array( 'fields' => 'ids' ) );
        $before = is_wp_error( $before ) ? array() : array_map( 'absint', $before );

        if ( 'move' === $mode ) {
            // Replace outright: the target terms become the whole membership.
            wp_set_object_terms( $attachment_id, $add, $taxonomy, false );
            $after = $add;
        } else {
            if ( ! empty( $add ) ) {
                wp_set_object_terms( $attachment_id, $add, $taxonomy, true );
            }
            if ( ! empty( $remove ) ) {
                wp_remove_object_terms( $attachment_id, $remove, $taxonomy );
            }
            $after = array_diff( array_unique( array_merge( $before, $add ) ), $remove );
        }

        $gained = array_values( array_diff( $after, $before ) );
        $lost   = array_values( array_diff( $before, $after ) );

        $changed[] = $attachment_id;

        if ( empty( $gained ) && empty( $lost ) ) {
            continue; // Already in the requested state; there is nothing to take back.
        }

        /*
         *  Grouped by identical delta. A drag of forty files usually produces one
         *  or two distinct ones, so the undo stays a short payload rather than a
         *  per-file list.
         */
        sort( $gained );
        sort( $lost );
        $key = implode( ',', $gained ) . '|' . implode( ',', $lost );

        if ( ! isset( $deltas[ $key ] ) ) {
            $deltas[ $key ] = array(
                'attachments' => array(),
                'add'         => $lost,   // to undo: give back what was lost
                'remove'      => $gained, // and take away what was gained
            );
        }
        $deltas[ $key ]['attachments'][] = $attachment_id;
    }

    wp_defer_term_counting( false );

    wp_cache_delete( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );

    /*
     *  Fresh counts come back with the response, so the tree updates from what
     *  the database now says rather than from arithmetic the browser did on the
     *  way -- which is how a count drifts and never comes back.
     */
    $touched = array_merge( $add, $remove );
    foreach ( $deltas as $d ) {
        $touched = array_merge( $touched, $d['add'], $d['remove'] );
    }

    $for_type = (string) $request->get_param( 'post_type' );

    $counts = array();
    foreach ( array_unique( $touched ) as $term_id ) {
        $term = get_term( $term_id, $taxonomy );
        if ( $term instanceof WP_Term )
            $counts[ (int) $term_id ] = vergeml_assign_count( $term_id, $taxonomy, $for_type );
    }

    return rest_ensure_response( array(
        'changed'    => $changed,
        'refused'    => $refused,
        'counts'     => $counts,
        'unassigned' => vergeml_count_unassigned( $taxonomy ),
        /*
         *  What to send back to undo this. The toast posts it straight back to
         *  this endpoint, so the inverse is computed here and the browser never
         *  has an opinion about it.
         *
         *  A batch rather than one set of terms, because different files can have
         *  moved differently in the same drag. 'move' is undoable too: replacing a
         *  file's whole membership is only irreversible if you failed to write
         *  down what it was.
         */
        'undo'       => empty( $deltas ) ? null : array(
            'taxonomy' => $taxonomy,
            'batch'    => array_values( $deltas ),
        ),
    ) );
}


/**
 *  vergeml_rest_assign_batch
 *
 *  Several assign groups in one call.
 *
 *  Undo needs this. A single drag can move different files differently -- one
 *  gained a folder, another swapped one for another, a third was already where
 *  it was being dragged to -- so taking that drag back is several distinct
 *  operations, and offering the user a toast that only reverses some of them
 *  would be worse than offering none.
 *
 *  Each group is run through the single-group handler rather than reimplemented,
 *  so a batch cannot drift from the behaviour of the calls it is made of: the
 *  per-attachment capability check, the term validation and the deferred
 *  counting are all the same code.
 */

function vergeml_rest_assign_batch( WP_REST_Request $request, $taxonomy, array $batch ) {

    $changed = array();
    $refused = array();
    $counts  = array();
    $undo    = array();

    foreach ( $batch as $group ) {

        if ( ! is_array( $group ) ) {
            continue;
        }

        $sub = new WP_REST_Request( 'POST', '/' . VERGEML_REST_NS . '/assign' );
        $sub->set_param( 'taxonomy', $taxonomy );
        $sub->set_param( 'attachments', isset( $group['attachments'] ) ? (array) $group['attachments'] : array() );
        $sub->set_param( 'add', isset( $group['add'] ) ? (array) $group['add'] : array() );
        $sub->set_param( 'remove', isset( $group['remove'] ) ? (array) $group['remove'] : array() );
        $sub->set_param( 'mode', isset( $group['mode'] ) ? $group['mode'] : 'add' );

        $result = vergeml_rest_assign( $sub );

        /*
         *  One bad group fails the whole call. A half-applied undo leaves the
         *  library in a state the user never asked for and cannot name, which is
         *  worse than an undo that visibly did not work.
         */
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $data = $result instanceof WP_REST_Response ? $result->get_data() : (array) $result;

        $changed = array_merge( $changed, isset( $data['changed'] ) ? $data['changed'] : array() );
        $refused = array_merge( $refused, isset( $data['refused'] ) ? $data['refused'] : array() );
        $counts  = isset( $data['counts'] ) ? $counts + $data['counts'] : $counts;

        if ( ! empty( $data['undo']['batch'] ) ) {
            $undo = array_merge( $undo, $data['undo']['batch'] );
        }
    }

    // Counts were gathered group by group, so the last group's figures are the
    // current ones; re-read anything an earlier group reported.
    $for_type = (string) $request->get_param( 'post_type' );

    foreach ( array_keys( $counts ) as $term_id ) {
        $term = get_term( $term_id, $taxonomy );
        if ( $term instanceof WP_Term ) {
            $counts[ $term_id ] = vergeml_assign_count( $term_id, $taxonomy, $for_type );
        }
    }

    return rest_ensure_response( array(
        'changed'    => array_values( array_unique( $changed ) ),
        'refused'    => array_values( array_unique( $refused ) ),
        'counts'     => $counts,
        'unassigned' => vergeml_count_unassigned( $taxonomy ),
        'undo'       => empty( $undo ) ? null : array( 'taxonomy' => $taxonomy, 'batch' => $undo ),
    ) );
}
