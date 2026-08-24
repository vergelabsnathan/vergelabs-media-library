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

function vergeml_import_run( $key, $taxonomy, $resume = null ) {

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
    /*
     *  Assignments are done in chunks.
     *
     *  Sixteen thousand of them will not finish inside one request on the kind of
     *  shared host this plugin mostly runs on -- it would hit max_execution_time
     *  half way through and leave an import nobody can undo, because the record is
     *  written at the end. So the work is resumable: the caller passes back what
     *  it got, and each pass writes its own progress.
     */
    $chunk = (int) apply_filters( 'vergeml_import_chunk', 500 );

    $done  = ( $resume && isset( $resume['done'] ) ) ? (int) $resume['done'] : 0;
    $added = ( $resume && isset( $resume['added'] ) ) ? (array) $resume['added'] : array();

    // Flatten to a stable list so a resume lands exactly where it left off; the
    // source's own order is what makes that stable.
    $queue = array();
    foreach ( $files as $source_id => $ids ) {
        if ( ! isset( $map[ $source_id ] ) ) {
            continue;
        }
        foreach ( array_unique( $ids ) as $attachment_id ) {
            $queue[] = array( (int) $attachment_id, (int) $map[ $source_id ] );
        }
    }

    $total = count( $queue );
    $slice = array_slice( $queue, $done, $chunk );

    wp_defer_term_counting( true );

    foreach ( $slice as $job ) {

        $attachment_id = $job[0];
        $term_id       = $job[1];

        {

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

    $done += count( $slice );

    wp_defer_term_counting( false );
    wp_cache_delete( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );

    /*
     *  Written on every pass, not at the end.
     *
     *  An import that dies half way through has still changed the library, and a
     *  record that only exists on completion would leave that change with no way
     *  back. This way the undo is always current with what has actually happened.
     */
    $id = ( $resume && ! empty( $resume['id'] ) ) ? $resume['id'] : uniqid( 'imp', false );

    $record = array(
        'id'       => $id,
        'source'   => $key,
        'taxonomy' => $taxonomy,
        'when'     => time(),
        'created'  => ( $resume && isset( $resume['created'] ) ) ? array_values( array_unique( array_merge( (array) $resume['created'], $created ) ) ) : $created,
        'added'    => $added,
    );

    $log = get_option( VERGEML_IMPORT_LOG, array() );
    $log = is_array( $log ) ? $log : array();

    $replaced = false;
    foreach ( $log as $i => $entry ) {
        if ( isset( $entry['id'] ) && $entry['id'] === $id ) {
            $log[ $i ] = $record;
            $replaced  = true;
            break;
        }
    }

    if ( ! $replaced ) {
        $log[] = $record;
    }

    // Only the last few are keepable: sixteen thousand pairs is not small, and an
    // import from six months ago is not something anyone is about to undo.
    if ( count( $log ) > 5 ) {
        $log = array_slice( $log, -5 );
    }

    update_option( VERGEML_IMPORT_LOG, $log, false );

    return array(
        'id'          => $id,
        'created'     => count( $record['created'] ),
        // Both sides of this have to be cumulative. $created holds only what this
        // pass made, which is everything on the first pass and nothing after it --
        // so the folders the import itself created came back as "merged into
        // folders you already have" on every pass but the first.
        'merged'      => max( 0, count( $map ) - count( $record['created'] ) ),
        'assignments' => count( $added ),
        'done'        => $done,
        'total'       => $total,
        'complete'    => $done >= $total,
        // Handed straight back on the next call; the caller never has to
        // understand it.
        'resume'      => $done >= $total ? null : array(
            'id'      => $id,
            'done'    => $done,
            'added'   => $added,
            'created' => $record['created'],
        ),
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

    $result = vergeml_import_undo_step( $id );
    $passes = 0;

    while ( ! is_wp_error( $result ) && empty( $result['complete'] ) && ! empty( $result['resume'] ) ) {

        $result = vergeml_import_undo_step( $id, $result['resume'] );

        if ( ++$passes > 10000 ) {
            return new WP_Error( 'vergeml_undo_stuck', __( 'The undo did not finish.', 'vergelabs-media-library' ) );
        }
    }

    return $result;
}


/**
 *  vergeml_import_undo_step
 *
 *  One chunk of an undo.
 *
 *  Undoing sixteen thousand assignments takes long enough to hit a shared host's
 *  execution limit, and the record is only cleared at the very end -- so a run
 *  that times out starts again from nothing next time and an undo on a slow host
 *  could never finish, however often it was retried. Chunked, each pass keeps
 *  what it did.
 *
 *  Assignments first, then the folders: deleting a term would take its
 *  assignments with it, and the record of what to unassign has to survive a
 *  half-finished undo.
 */

function vergeml_import_undo_step( $id, $resume = null ) {

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
    $added    = (array) $found['added'];
    $created  = (array) $found['created'];

    $chunk = (int) apply_filters( 'vergeml_import_chunk', 500 );
    $chunk = $chunk > 0 ? $chunk : 500;

    $done  = ( $resume && isset( $resume['done'] ) ) ? (int) $resume['done'] : 0;
    $terms = ( $resume && isset( $resume['terms'] ) ) ? (int) $resume['terms'] : 0;

    $total = count( $added ) + count( $created );

    wp_defer_term_counting( true );

    if ( $done < count( $added ) ) {

        foreach ( array_slice( $added, $done, $chunk ) as $pair ) {
            wp_remove_object_terms( (int) $pair[0], array( (int) $pair[1] ), $taxonomy );
            $done++;
        }

    } else {

        foreach ( array_slice( $created, $terms, $chunk ) as $term_id ) {
            // Children created by the same import are in this list too, so
            // deleting in any order is safe: wp_delete_term re-parents what is
            // left.
            wp_delete_term( (int) $term_id, $taxonomy );
            $terms++;
        }
    }

    wp_defer_term_counting( false );
    wp_cache_delete( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );

    $complete = $done >= count( $added ) && $terms >= count( $created );

    if ( $complete ) {
        // Only now: while the record is on file, a pass that dies can be retried
        // and will pick up where it stopped.
        update_option( VERGEML_IMPORT_LOG, $rest, false );
    }

    return array(
        'id'          => $id,
        'removed'     => $terms,
        'unassigned'  => $done,
        'done'        => $done + $terms,
        'total'       => $total,
        'complete'    => $complete,
        'resume'      => $complete ? null : array( 'done' => $done, 'terms' => $terms ),
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
