<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Bringing folders in from another plugin.
 *
 *  Three things happen here and they are deliberately separate: work out what
 *  would happen, do it, and undo it. The preview and the import walk the same
 *  code so the summary cannot promise something the import does not do -- a
 *  preview computed independently is a second implementation that drifts, and
 *  the first time it drifts is the time somebody trusted it.
 *
 *  Nothing is ever taken from the source. The plugin being imported from keeps
 *  its folders exactly as they were, so a bad import costs nothing but the time
 *  to undo it, and the user can always go back.
 *
 *  @since 3.2
 */


/** Where the record of each import lives, so it can be taken back. */
const VERGEML_IMPORT_LOG = 'vergeml_imports';


/**
 *  vergeml_import_plan
 *
 *  What an import would do, without doing any of it.
 *
 *  Folders that already exist by name under the same parent are merged into
 *  rather than duplicated -- the same name in the same place is the same folder,
 *  which is what copying folders does everywhere else and what stops a tree
 *  filling up with "Photos" and "Photos (2)".
 */

function vergeml_import_plan( $key, $taxonomy ) {

    $read = vergeml_import_read( $key );

    if ( is_wp_error( $read ) ) {
        return $read;
    }

    if ( ! in_array( $taxonomy, vergeml_tree_taxonomies(), true ) ) {
        return new WP_Error( 'vergeml_unknown_taxonomy', __( 'That is not a media taxonomy.', 'vergelabs-media-library' ) );
    }

    $folders = $read['folders'];
    $files   = $read['files'];

    /*
     *  Source folders in parent-before-child order.
     *
     *  A child cannot be created until its parent exists, and the source makes no
     *  promise about the order it hands them over -- FileBird's ids are creation
     *  order, which is not tree order once anything has been moved.
     */
    $ordered = vergeml_import_order( $folders );

    $existing = vergeml_import_existing( $taxonomy );

    $map     = array();  // source id => our term id, for folders that already exist
    $create  = array();  // source ids we would have to create
    $merge   = array();  // source ids that land in a folder already here

    foreach ( $ordered as $source_id ) {

        $folder = $folders[ $source_id ];
        $parent = isset( $folder['parent'] ) ? (int) $folder['parent'] : 0;

        // Where this folder's parent ends up on our side.
        $our_parent = 0;
        if ( $parent && isset( $map[ $parent ] ) ) {
            $our_parent = $map[ $parent ];
        }

        $slot = vergeml_import_key( $folder['name'], $our_parent );

        if ( isset( $existing[ $slot ] ) ) {
            $map[ $source_id ] = $existing[ $slot ];
            $merge[]           = $source_id;
        } else {
            $create[] = $source_id;
            // A placeholder so children of this folder are planned against it.
            $map[ $source_id ] = 'new:' . $source_id;
            $existing[ $slot ] = 'new:' . $source_id;
        }
    }

    $assignments = 0;
    foreach ( $files as $source_id => $ids ) {
        if ( isset( $map[ $source_id ] ) ) {
            $assignments += count( array_unique( $ids ) );
        }
    }

    return array(
        'source'      => $key,
        'taxonomy'    => $taxonomy,
        'folders'     => count( $folders ),
        'create'      => count( $create ),
        'merge'       => count( $merge ),
        'assignments' => $assignments,
        'files'       => count( array_unique( call_user_func_array( 'array_merge', $files ? array_values( $files ) : array( array() ) ) ) ),
    );
}


/**
 *  vergeml_import_run
 *
 *  Do it, and write down enough to undo it.
 *
 *  What is recorded is only what this import created: the term ids it made and
 *  the exact file-to-folder pairs it added. Undo removes those and nothing else,
 *  so a folder that already existed survives, and a file somebody filed by hand
 *  afterwards stays filed.
 */

function vergeml_import_run( $key, $taxonomy ) {

    $read = vergeml_import_read( $key );

    if ( is_wp_error( $read ) ) {
        return $read;
    }

    if ( ! in_array( $taxonomy, vergeml_tree_taxonomies(), true ) ) {
        return new WP_Error( 'vergeml_unknown_taxonomy', __( 'That is not a media taxonomy.', 'vergelabs-media-library' ) );
    }

    $folders  = $read['folders'];
    $files    = $read['files'];
    $ordered  = vergeml_import_order( $folders );
    $existing = vergeml_import_existing( $taxonomy );

    $map     = array();
    $created = array();

    foreach ( $ordered as $source_id ) {

        $folder     = $folders[ $source_id ];
        $parent     = isset( $folder['parent'] ) ? (int) $folder['parent'] : 0;
        $our_parent = ( $parent && isset( $map[ $parent ] ) ) ? $map[ $parent ] : 0;

        $slot = vergeml_import_key( $folder['name'], $our_parent );

        if ( isset( $existing[ $slot ] ) ) {
            $map[ $source_id ] = (int) $existing[ $slot ];
            continue;
        }

        $made = wp_insert_term( $folder['name'], $taxonomy, array( 'parent' => $our_parent ) );

        if ( is_wp_error( $made ) ) {
            /*
             *  A name can collide with a term that exists somewhere else in the
             *  taxonomy: WordPress refuses duplicate slugs across a hierarchy even
             *  when the names sit under different parents. Reuse what it points at
             *  rather than failing the whole import for one folder.
             */
            $data = $made->get_error_data();
            if ( is_array( $data ) && isset( $data['term_id'] ) ) {
                $map[ $source_id ]  = (int) $data['term_id'];
                $existing[ $slot ]  = (int) $data['term_id'];
                continue;
            }
            continue;
        }

        $term_id = (int) $made['term_id'];

        $map[ $source_id ]  = $term_id;
        $existing[ $slot ]  = $term_id;
        $created[]          = $term_id;

        if ( isset( $folder['order'] ) && $folder['order'] ) {
            update_term_meta( $term_id, VERGEML_TERM_ORDER, (int) $folder['order'] );
        }
    }

    /*
     *  Counting is deferred across the whole import and resumed once. Recounting
     *  a taxonomy after each of sixteen thousand assignments is the difference
     *  between seconds and hours.
     */
    wp_defer_term_counting( true );

    $added = array();

    foreach ( $files as $source_id => $ids ) {

        if ( ! isset( $map[ $source_id ] ) ) {
            continue;
        }

        $term_id = (int) $map[ $source_id ];

        foreach ( array_unique( $ids ) as $attachment_id ) {

            $attachment_id = (int) $attachment_id;

            // Only what this import actually adds is recorded, so undo cannot
            // remove a file that was already in this folder before.
            $before = wp_get_object_terms( $attachment_id, $taxonomy, array( 'fields' => 'ids' ) );
            $before = is_wp_error( $before ) ? array() : array_map( 'absint', $before );

            if ( in_array( $term_id, $before, true ) ) {
                continue;
            }

            wp_set_object_terms( $attachment_id, array( $term_id ), $taxonomy, true );
            $added[] = array( $attachment_id, $term_id );
        }
    }

    wp_defer_term_counting( false );
    wp_cache_delete( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );

    $record = array(
        'id'       => uniqid( 'imp', false ),
        'source'   => $key,
        'taxonomy' => $taxonomy,
        'when'     => time(),
        'created'  => $created,
        'added'    => $added,
    );

    $log   = get_option( VERGEML_IMPORT_LOG, array() );
    $log   = is_array( $log ) ? $log : array();
    $log[] = $record;

    // Only the last few are keepable: sixteen thousand pairs is not small, and an
    // import from six months ago is not something anyone is about to undo.
    if ( count( $log ) > 5 ) {
        $log = array_slice( $log, -5 );
    }

    update_option( VERGEML_IMPORT_LOG, $log, false );

    return array(
        'id'          => $record['id'],
        'created'     => count( $created ),
        'merged'      => count( $map ) - count( $created ),
        'assignments' => count( $added ),
    );
}


/**
 *  vergeml_import_undo
 *
 *  Take back exactly what one import did.
 *
 *  The pairs go first and the folders second, because a folder that still holds
 *  files it did not import would otherwise be deleted with them still in it.
 *  Folders that existed before the import are never touched: they are not in the
 *  record.
 */

function vergeml_import_undo( $id ) {

    $log = get_option( VERGEML_IMPORT_LOG, array() );
    $log = is_array( $log ) ? $log : array();

    $found = null;
    $rest  = array();

    foreach ( $log as $entry ) {
        if ( isset( $entry['id'] ) && $entry['id'] === $id ) {
            $found = $entry;
        } else {
            $rest[] = $entry;
        }
    }

    if ( ! $found ) {
        return new WP_Error( 'vergeml_no_import', __( 'That import is not on record any more.', 'vergelabs-media-library' ) );
    }

    $taxonomy = $found['taxonomy'];

    wp_defer_term_counting( true );

    foreach ( (array) $found['added'] as $pair ) {
        wp_remove_object_terms( (int) $pair[0], array( (int) $pair[1] ), $taxonomy );
    }

    foreach ( (array) $found['created'] as $term_id ) {
        // Children created by the same import are in this list too, so deleting
        // in any order is safe: wp_delete_term re-parents what is left.
        wp_delete_term( (int) $term_id, $taxonomy );
    }

    wp_defer_term_counting( false );
    wp_cache_delete( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );

    update_option( VERGEML_IMPORT_LOG, $rest, false );

    return array(
        'removed'     => count( (array) $found['created'] ),
        'unassigned'  => count( (array) $found['added'] ),
    );
}


/**
 *  vergeml_import_order
 *
 *  Source folders, parents before children.
 *
 *  Depth-first from the roots, then anything left over -- a folder whose parent
 *  is missing from the source, which happens in real exports, is treated as a
 *  root rather than dropped. Losing somebody's folder because its parent was
 *  deleted years ago would be the worst possible outcome of an import.
 */

function vergeml_import_order( $folders ) {

    $children = array();

    foreach ( $folders as $id => $folder ) {
        $parent = isset( $folder['parent'] ) ? (int) $folder['parent'] : 0;
        if ( ! isset( $folders[ $parent ] ) ) {
            $parent = 0; // orphan: treat as a root
        }
        $children[ $parent ][] = $id;
    }

    $out  = array();
    $seen = array();

    $walk = function ( $parent ) use ( &$walk, &$out, &$seen, $children ) {

        if ( empty( $children[ $parent ] ) ) {
            return;
        }

        foreach ( $children[ $parent ] as $id ) {
            if ( isset( $seen[ $id ] ) ) {
                continue; // a cycle in the source; do not follow it twice
            }
            $seen[ $id ] = true;
            $out[]       = $id;
            $walk( $id );
        }
    };

    $walk( 0 );

    // Anything a cycle kept out still gets imported, flat.
    foreach ( $folders as $id => $folder ) {
        if ( ! isset( $seen[ $id ] ) ) {
            $out[] = $id;
        }
    }

    return $out;
}


/**
 *  The folders already here, keyed the same way the plan keys the incoming ones,
 *  so "same name, same parent" is a lookup rather than a search.
 */

function vergeml_import_existing( $taxonomy ) {

    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

    $out = array();

    if ( is_wp_error( $terms ) ) {
        return $out;
    }

    foreach ( $terms as $term ) {
        $out[ vergeml_import_key( $term->name, (int) $term->parent ) ] = (int) $term->term_id;
    }

    return $out;
}


/**
 *  Case- and space-insensitive, because "Photos" and "photos " are the same
 *  folder to the person who made them and importing both is not a feature.
 */

function vergeml_import_key( $name, $parent ) {

    return strtolower( trim( wp_strip_all_tags( (string) $name ) ) ) . '|' . (int) $parent;
}
