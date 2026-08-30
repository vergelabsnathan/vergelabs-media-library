<?php

if ( ! defined( 'ABSPATH' ) )
    exit;

/**
 *  Filing by itself — earned, never assumed.
 *
 *  Phase 4b. A described file has an embedding; a folder that already holds
 *  described files has a middle. So "which of your folders does this belong
 *  in" is answerable without asking a model anything at the moment somebody
 *  is waiting. That much is arithmetic.
 *
 *  What is not arithmetic is whether to act on the answer, and the whole of
 *  this file is about being careful there. Three rules, and they are the
 *  reason it is safe to have at all:
 *
 *  1. **Suggesting is the default; filing is earned, per folder.** A folder
 *     that has never had a suggestion accepted never files anything by
 *     itself. It earns that one accepted suggestion at a time, and loses it
 *     the moment somebody says no. Autonomy belongs to a folder rather than
 *     to a setting because the evidence is per folder: "Invoices is
 *     unambiguous and Misc is not" is a true thing about somebody's library
 *     that no global switch can express.
 *
 *  2. **Nothing is ever certain out loud.** The chip says "Looks like
 *     Invoices". It never says a percentage, a score, or "confident" --
 *     those numbers are uncalibrated, and a number attached to a guess is
 *     read as a promise. The standing rule in docs/ai-roadmap.md, kept here.
 *
 *  3. **Everything it does is written down and undoable.** An automatic file
 *     is logged in the Librarian's own moves table, in a batch of its own,
 *     so the undo that already exists removes exactly what this did and
 *     nothing else. There is no path in this file that assigns without
 *     logging.
 *
 *  And one more, quieter: it only ever touches files with no folder at all.
 *  A library somebody has organised by hand is not improved by this.
 *
 *  @since 3.5
 */


const VERGEML_AUTOFILE_CENTROID = '_vergeml_centroid';
const VERGEML_AUTOFILE_LEDGER   = '_vergeml_autonomy';

/*
 *  How many accepted suggestions a folder needs before it may file on its
 *  own. Five rather than three because the cost of the two errors is not
 *  symmetric: a suggestion nobody wanted is a click, and a file that moved
 *  itself into the wrong folder is somebody hunting for it.
 */
const VERGEML_AUTOFILE_EARN = 5;

/*
 *  The margin. The nearest folder has to be this much nearer than the one
 *  behind it, or the answer is "two of your folders would both do" -- which
 *  is a real answer, and the right thing to do with it is nothing.
 */
const VERGEML_AUTOFILE_MARGIN = 1.25;

// A folder's own spread, multiplied. Beyond this the file is not near the
// middle of that folder, it is merely nearer to it than to the others.
const VERGEML_AUTOFILE_REACH = 1.5;

// Files considered in one pass. The loop is chunked for the same reason
// everything else here is: shared hosting times out at thirty seconds.
const VERGEML_AUTOFILE_CHUNK = 20;


/* -------------------------------------------------------------- centroids */

/**
 *  vergeml_autofile_centroid
 *
 *  A folder's middle, and how far its files sit from it.
 *
 *  Cached in term meta with the number of described files it was built from.
 *  When that number changes the cache is stale and is rebuilt -- cheap,
 *  because it is one query over an indexed join, and it only happens when the
 *  folder has actually changed.
 *
 *  Returns null for a folder with too few described files to have a middle
 *  worth the name. Three is the floor: two points have a midpoint but no
 *  spread, and a spread of zero would let anything in.
 */

function vergeml_autofile_centroid( $term_id, $taxonomy ) {

    global $wpdb;

    $term_id = (int) $term_id;

    $cached = get_term_meta( $term_id, VERGEML_AUTOFILE_CENTROID, true );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT tr.object_id
           FROM {$wpdb->term_relationships} tr
          WHERE tr.term_taxonomy_id = (
                SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy}
                 WHERE term_id = %d AND taxonomy = %s LIMIT 1
          )",
        $term_id,
        $taxonomy
    ) );
    // phpcs:enable

    $vectors = array();

    foreach ( (array) $ids as $id ) {
        $vector = vergeml_autofile_vector( (int) $id );
        if ( $vector ) {
            $vectors[] = $vector;
        }
    }

    $n = count( $vectors );

    if ( $n < 3 ) {
        return null;
    }

    if ( is_array( $cached ) && isset( $cached['n'] ) && $n === (int) $cached['n'] && ! empty( $cached['mean'] ) ) {
        return $cached;
    }

    $dims = count( $vectors[0] );
    $mean = array_fill( 0, $dims, 0.0 );

    foreach ( $vectors as $vector ) {
        foreach ( $vector as $i => $value ) {
            if ( isset( $mean[ $i ] ) ) {
                $mean[ $i ] += $value;
            }
        }
    }

    foreach ( $mean as $i => $sum ) {
        $mean[ $i ] = $sum / $n;
    }

    /*
     *  The spread is the mean distance from the middle, not the largest.
     *  One badly filed photo would otherwise widen a folder's reach enough
     *  to swallow everything near it.
     */
    $total = 0.0;

    foreach ( $vectors as $vector ) {
        $total += vergeml_organize_distance( $vector, $mean );
    }

    $centroid = array(
        'mean'   => $mean,
        'spread' => $total / $n,
        'n'      => $n,
    );

    update_term_meta( $term_id, VERGEML_AUTOFILE_CENTROID, $centroid );

    return $centroid;
}


/**
 *  vergeml_autofile_vector
 *
 *  One file's embedding, or null.
 *
 *  The single place this file reads a vector from, and filterable, which is
 *  not a courtesy: Playground cannot store one. Its SQLite layer refuses any
 *  INSERT carrying packed floats -- `WP_MySQL_Token::get_value(): must be
 *  string, null returned` -- so on the only environment available without a
 *  box, the index can hold descriptions but never embeddings. Reading through
 *  one seam means the arithmetic and the judgement in this file can be tested
 *  anywhere, with the storage proven separately where storage works.
 *
 *  It is also the hook a Pro build would use to serve vectors from somewhere
 *  else entirely.
 */

function vergeml_autofile_vector( $attachment_id ) {

    $vector = apply_filters( 'vergeml_autofile_vector', null, (int) $attachment_id );

    if ( is_array( $vector ) && $vector ) {
        return $vector;
    }

    $row = vergeml_index_get( (int) $attachment_id );

    return ( $row && ! empty( $row['embedding'] ) ) ? $row['embedding'] : null;
}


/**
 *  Every folder in the target taxonomy that has a middle, with it.
 */

function vergeml_autofile_folders( $taxonomy ) {

    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
        'fields'     => 'ids',
    ) );

    if ( is_wp_error( $terms ) ) {
        return array();
    }

    $out = array();

    foreach ( $terms as $term_id ) {

        $centroid = vergeml_autofile_centroid( $term_id, $taxonomy );

        if ( $centroid ) {
            $out[ (int) $term_id ] = $centroid;
        }
    }

    return $out;
}


/* ------------------------------------------------------------- the answer */

/**
 *  vergeml_autofile_suggest
 *
 *  Where this file would go, or null.
 *
 *  Null is a real answer and by far the most common one: no embedding yet, no
 *  folder with enough described files to have a middle, nothing near enough,
 *  or two folders equally close. Every one of those is a case where doing
 *  nothing is correct, and none of them is an error.
 */

function vergeml_autofile_suggest( $attachment_id, $folders = null ) {

    $taxonomy = vergeml_librarian_taxonomy();

    if ( '' === $taxonomy ) {
        return null;
    }

    // Only the unfiled. A library organised by hand is not improved by this.
    $existing = wp_get_object_terms( (int) $attachment_id, $taxonomy, array( 'fields' => 'ids' ) );

    if ( is_wp_error( $existing ) || ! empty( $existing ) ) {
        return null;
    }

    $vector = vergeml_autofile_vector( (int) $attachment_id );

    if ( ! $vector ) {
        return null;
    }

    $folders = null === $folders ? vergeml_autofile_folders( $taxonomy ) : $folders;

    if ( ! $folders ) {
        return null;
    }

    $best      = null;
    $best_dist = null;
    $runner_up = null;

    foreach ( $folders as $term_id => $centroid ) {

        $distance = vergeml_organize_distance( $vector, $centroid['mean'] );

        if ( null === $best_dist || $distance < $best_dist ) {
            $runner_up = $best_dist;
            $best_dist = $distance;
            $best      = (int) $term_id;
            continue;
        }

        if ( null === $runner_up || $distance < $runner_up ) {
            $runner_up = $distance;
        }
    }

    if ( null === $best ) {
        return null;
    }

    $centroid = $folders[ $best ];

    // Near the middle of that folder, not merely nearer to it than to the
    // others. A file can be the least bad fit for every folder you own.
    $reach = $centroid['spread'] * VERGEML_AUTOFILE_REACH;

    if ( $reach <= 0 || $best_dist > $reach ) {
        return null;
    }

    /*
     *  And clearly nearer than the runner-up. When two folders would both
     *  do, saying so is not useful and choosing is worse -- so neither.
     */
    if ( null !== $runner_up && $best_dist * VERGEML_AUTOFILE_MARGIN > $runner_up ) {
        return null;
    }

    return array(
        'attachment_id' => (int) $attachment_id,
        'term_id'       => $best,
        'taxonomy'      => $taxonomy,
        'earned'        => vergeml_autofile_earned( $best ),
    );
}


/* ------------------------------------------------------------ the ledger */

/**
 *  What a folder has been told about its own suggestions.
 */

function vergeml_autofile_ledger( $term_id ) {

    $ledger = get_term_meta( (int) $term_id, VERGEML_AUTOFILE_LEDGER, true );

    if ( ! is_array( $ledger ) ) {
        $ledger = array();
    }

    return array(
        'accepted'  => isset( $ledger['accepted'] ) ? (int) $ledger['accepted'] : 0,
        'dismissed' => isset( $ledger['dismissed'] ) ? (int) $ledger['dismissed'] : 0,
        'auto'      => isset( $ledger['auto'] ) ? (int) $ledger['auto'] : 0,
    );
}


/**
 *  Whether this folder may file on its own yet.
 *
 *  Earned by agreement and lost by disagreement, and lost completely rather
 *  than decremented: somebody saying "no, not that one" is evidence that the
 *  folder's middle does not mean what we thought, and the honest response to
 *  that is to go back to asking.
 */

function vergeml_autofile_earned( $term_id ) {

    $ledger = vergeml_autofile_ledger( $term_id );

    if ( $ledger['dismissed'] > 0 ) {
        return false;
    }

    return $ledger['accepted'] >= VERGEML_AUTOFILE_EARN;
}


function vergeml_autofile_record( $term_id, $what ) {

    $ledger = vergeml_autofile_ledger( $term_id );

    if ( 'dismissed' === $what ) {
        // Trust is not decremented, it is withdrawn. See above.
        $ledger['accepted']  = 0;
        $ledger['dismissed'] = $ledger['dismissed'] + 1;
    } elseif ( isset( $ledger[ $what ] ) ) {
        $ledger[ $what ] = $ledger[ $what ] + 1;
    }

    update_term_meta( (int) $term_id, VERGEML_AUTOFILE_LEDGER, $ledger );

    return $ledger;
}


/* -------------------------------------------------------------- the doing */

/**
 *  vergeml_autofile_file
 *
 *  Assign, and write it down.
 *
 *  Through the Librarian's own moves log, in a batch of its own, so the undo
 *  that already exists covers this without knowing this file exists. There is
 *  no way to file from here that skips the log -- that is the point of there
 *  being one function.
 *
 *  `term_created` is always 0: this never makes a folder. It can only ever
 *  put a file into one somebody already has, which is also why undo will
 *  never delete a folder because of anything here.
 */

function vergeml_autofile_file( $attachment_id, $term_id, $how ) {

    $taxonomy = vergeml_librarian_taxonomy();

    if ( '' === $taxonomy ) {
        return new WP_Error( 'vergeml_autofile_no_taxonomy', __( 'No category is set up to act as a folder yet.', 'vergelabs-media-library' ), array( 'status' => 409 ) );
    }

    $attachment_id = (int) $attachment_id;
    $term_id       = (int) $term_id;

    if ( ! get_post( $attachment_id ) || ! get_term( $term_id, $taxonomy ) instanceof WP_Term ) {
        return new WP_Error( 'vergeml_autofile_gone', __( 'That file or folder is no longer there.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    $batch_id = vergeml_autofile_batch( $how );

    if ( is_wp_error( $batch_id ) ) {
        return $batch_id;
    }

    $set = wp_set_object_terms( $attachment_id, array( $term_id ), $taxonomy, true );

    if ( is_wp_error( $set ) ) {
        return $set;
    }

    vergeml_librarian_moves_insert( array( array( $batch_id, $attachment_id, $term_id, 0 ) ) );

    // The folder just gained a file, so its middle moved. Dropping the cache
    // is cheaper than being subtly wrong about the next one.
    delete_term_meta( $term_id, VERGEML_AUTOFILE_CENTROID );

    return array(
        'attachment_id' => $attachment_id,
        'term_id'       => $term_id,
        'batch_id'      => (int) $batch_id,
    );
}


/**
 *  The batch everything filed this way is logged against.
 *
 *  One open batch per day rather than one per file: ten thousand batches of
 *  one would push every reviewable batch out of the ten the Librarian keeps,
 *  and "undo what it did on Tuesday" is the question somebody actually asks.
 */

function vergeml_autofile_batch( $how ) {

    global $wpdb;

    vergeml_librarian_maybe_install();

    /*
     *  The scheme is how the Librarian's own list explains a batch to
     *  somebody looking at it later, so the three ways a file can arrive here
     *  stay distinguishable: one you agreed to, one that happened by itself,
     *  and one you asked for in words.
     */
    $schemes = array(
        'accepted' => 'suggested',
        'spoken'   => 'spoken',
        'auto'     => 'auto',
    );

    $scheme = isset( $schemes[ $how ] ) ? $schemes[ $how ] : 'auto';
    $day    = gmdate( 'Y-m-d' );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT batch_id FROM {$wpdb->vergeml_librarian_batches}
          WHERE scheme = %s AND status = %s AND DATE( created_at ) = %s
          ORDER BY batch_id DESC LIMIT 1",
        $scheme,
        'running',
        $day
    ) );

    if ( $existing ) {
        return (int) $existing;
    }

    $now = current_time( 'mysql', true );

    $wpdb->insert(
        vergeml_librarian_batches_table(),
        array(
            'run_id'      => 0,
            'scheme'      => $scheme,
            'status'      => 'running',
            'step_cursor' => 0,
            'done_n'      => 0,
            'skip_n'      => 0,
            'params'      => wp_json_encode( array( 'source' => 'auto-file' ) ),
            'reason'      => '',
            'created_at'  => $now,
            'updated_at'  => $now,
        ),
        array( '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
    );
    // phpcs:enable

    return (int) $wpdb->insert_id;
}


/**
 *  vergeml_autofile_sweep
 *
 *  One pass over described, unfiled files.
 *
 *  Files into folders that have earned it; collects the rest as suggestions
 *  for somebody to look at. Chunked, and the folders' middles are computed
 *  once for the whole chunk rather than once per file -- which is the
 *  difference between one pass and an N+1.
 */

function vergeml_autofile_sweep( $limit = VERGEML_AUTOFILE_CHUNK ) {

    global $wpdb;

    $taxonomy = vergeml_librarian_taxonomy();

    if ( '' === $taxonomy ) {
        return array( 'filed' => 0, 'suggested' => array(), 'looked' => 0 );
    }

    $table = vergeml_index_table();

    $tt = vergeml_autofile_tt_ids( $taxonomy );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID
           FROM {$wpdb->posts} p
           JOIN {$table} x ON x.attachment_id = p.ID
          WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
            -- described, which is the cheap half of the question. Whether it
            -- has a usable vector is asked per file through the seam above,
            -- because that is the half the database cannot answer everywhere.
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr
                 WHERE tr.object_id = p.ID AND tr.term_taxonomy_id IN ( {$tt} )
            )
          ORDER BY p.ID DESC
          LIMIT %d",
        (int) $limit
    ) );
    // phpcs:enable

    $folders = vergeml_autofile_folders( $taxonomy );

    $filed     = 0;
    $suggested = array();

    foreach ( (array) $ids as $id ) {

        $suggestion = vergeml_autofile_suggest( (int) $id, $folders );

        if ( ! $suggestion ) {
            continue;
        }

        if ( $suggestion['earned'] ) {
            $done = vergeml_autofile_file( $suggestion['attachment_id'], $suggestion['term_id'], 'auto' );
            if ( ! is_wp_error( $done ) ) {
                vergeml_autofile_record( $suggestion['term_id'], 'auto' );
                $filed++;
                // Its middle moved, so the rest of this chunk should see it.
                unset( $folders[ $suggestion['term_id'] ] );
                $centroid = vergeml_autofile_centroid( $suggestion['term_id'], $taxonomy );
                if ( $centroid ) {
                    $folders[ $suggestion['term_id'] ] = $centroid;
                }
            }
            continue;
        }

        $suggested[] = $suggestion;
    }

    return array(
        'filed'     => $filed,
        'suggested' => $suggested,
        'looked'    => count( (array) $ids ),
    );
}


/**
 *  The term_taxonomy_ids of a taxonomy, as a safe SQL list.
 *
 *  Built from integers this function fetched itself, never from input, so the
 *  interpolation above is a list of numbers and not a hole.
 */

function vergeml_autofile_tt_ids( $taxonomy ) {

    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'fields'     => 'tt_ids',
    ) );

    if ( is_wp_error( $terms ) || ! $terms ) {
        return '0';
    }

    return implode( ',', array_map( 'intval', $terms ) );
}


/* --------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_autofile_routes' );

function vergeml_autofile_routes() {

    $can = function () {
        return current_user_can( 'manage_categories' );
    };

    /*
     *  One pass. Creatable rather than readable because it can file things --
     *  for the folders that have earned it -- and a GET that changes the
     *  library is a GET somebody's prefetcher will fire.
     */
    register_rest_route( VERGEML_REST_NS, '/autofile-step', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_autofile_rest_step',
        'args'                => array(
            'limit' => array( 'type' => 'integer', 'default' => VERGEML_AUTOFILE_CHUNK ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/autofile-act', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_autofile_rest_act',
        'args'                => array(
            'attachment_id' => array( 'type' => 'integer', 'required' => true ),
            'term_id'       => array( 'type' => 'integer', 'required' => true ),
            'action'        => array( 'type' => 'string', 'required' => true ),
        ),
    ) );
}


function vergeml_autofile_rest_step( WP_REST_Request $request ) {

    $limit = max( 1, min( 100, (int) $request->get_param( 'limit' ) ) );

    $result = vergeml_autofile_sweep( $limit );

    $out = array();

    foreach ( $result['suggested'] as $suggestion ) {

        $term = get_term( $suggestion['term_id'], $suggestion['taxonomy'] );
        $post = get_post( $suggestion['attachment_id'] );

        if ( ! $term instanceof WP_Term || ! $post ) {
            continue;
        }

        $out[] = array(
            'attachment_id' => $suggestion['attachment_id'],
            'title'         => $post->post_title,
            'thumb'         => wp_get_attachment_image_url( $suggestion['attachment_id'], 'thumbnail' ),
            'term_id'       => $suggestion['term_id'],
            'folder'        => $term->name,
        );
    }

    /*
     *  What is left, and why.
     *
     *  Without these two numbers the screen could only end on "none of them
     *  clearly belongs anywhere yet" and stop -- true, useless, and a dead end
     *  in the middle of somebody's afternoon. The difference between "we have
     *  not looked at these" and "we looked and your folders do not fit" is the
     *  difference between two completely different next steps, and only the
     *  server can tell them apart.
     */
    return rest_ensure_response( array(
        'filed'      => (int) $result['filed'],
        'looked'     => (int) $result['looked'],
        'suggested'  => $out,
        'loose'      => vergeml_autofile_loose_count( false ),
        'unlooked'   => vergeml_autofile_loose_count( true ),
    ) );
}


/**
 *  vergeml_autofile_loose_count
 *
 *  Files in no folder. With $unlooked, only the ones we have never described --
 *  those cannot be suggested a folder because there is nothing to compare.
 */
function vergeml_autofile_loose_count( $unlooked = false ) {

    global $wpdb;

    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';

    if ( '' === $taxonomy ) {
        return 0;
    }

    $tt = vergeml_autofile_tt_ids( $taxonomy );

    if ( '' === $tt ) {
        return 0;
    }

    $table = vergeml_index_table();

    $described = $unlooked
        ? "AND NOT EXISTS ( SELECT 1 FROM {$table} x WHERE x.attachment_id = p.ID )"
        : '';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
          WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
            {$described}
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr
                 WHERE tr.object_id = p.ID AND tr.term_taxonomy_id IN ( {$tt} )
            )"
    );
    // phpcs:enable
}


function vergeml_autofile_rest_act( WP_REST_Request $request ) {

    $attachment_id = (int) $request->get_param( 'attachment_id' );
    $term_id       = (int) $request->get_param( 'term_id' );
    $action        = (string) $request->get_param( 'action' );

    if ( 'dismiss' === $action ) {

        /*
         *  Recorded against the folder, not the file. "Not that one" is
         *  information about how well that folder's middle describes it, and
         *  it costs the folder its autonomy -- which is the point: the only
         *  way this earns the right to act is by being told when it is right.
         */
        vergeml_autofile_record( $term_id, 'dismissed' );

        return rest_ensure_response( array(
            'ok'     => true,
            'earned' => vergeml_autofile_earned( $term_id ),
        ) );
    }

    if ( 'accept' !== $action ) {
        return new WP_Error( 'vergeml_autofile_unknown_action', __( 'That is not something you can do with a suggestion.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $done = vergeml_autofile_file( $attachment_id, $term_id, 'accepted' );

    if ( is_wp_error( $done ) ) {
        return $done;
    }

    vergeml_autofile_record( $term_id, 'accepted' );

    return rest_ensure_response( array(
        'ok'       => true,
        'batch_id' => $done['batch_id'],
        'earned'   => vergeml_autofile_earned( $term_id ),
    ) );
}


/* ----------------------------------------------------------------- the card */

// Filing, so it belongs with the Librarian rather than beside a licence key.
add_action( 'vergeml_librarian_page_cards', 'vergeml_autofile_card' );

function vergeml_autofile_card() {

    ?>
    <div class="vgml-ai-card">
        <h2><?php esc_html_e( 'Suggest a folder for each file', 'vergelabs-media-library' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Takes the files that are in no folder, looks at what is in each picture, and points at the folder it most resembles. We suggest, you decide. Once you have accepted five suggestions for a folder and refused none, that folder starts taking new files by itself — and stops the moment you refuse one.', 'vergelabs-media-library' ); ?></p>
        <p>
            <button type="button" class="button button-primary" id="vgml-autofile-run"><?php esc_html_e( 'Suggest folders', 'vergelabs-media-library' ); ?></button>
            <span id="vgml-autofile-note"></span>
        </p>
        <ul id="vgml-autofile-list" class="vgml-autofile-list"></ul>
        <p id="vgml-autofile-next" class="vgml-autofile-next"></p>
    </div>
    <?php
}


/*
 *  Its own script on the AI screen, enqueued from here rather than added to
 *  ai.php's list -- same reason the card is an action: this feature loads
 *  inside the safe-mode guard, and a screen that hard-codes its assets would
 *  ask for a file that is not there.
 */

add_action( 'admin_enqueue_scripts', 'vergeml_autofile_assets' );

function vergeml_autofile_assets( $hook ) {

    if ( false === strpos( (string) $hook, 'media-librarian' ) ) {
        return;
    }

    wp_enqueue_script(
        'vergeml-autofile',
        plugins_url( 'js/vergeml-autofile.js', VERGEML_FILE ),
        array( 'wp-api-fetch' ),
        vergeml_asset_ver( 'js/vergeml-autofile.js' ),
        true
    );

    wp_localize_script( 'vergeml-autofile', 'vergemlAutofile', array(
        /* translators: %s: folder name. */
        'looksLike'    => __( 'Looks like %s', 'vergelabs-media-library' ),
        'accept'       => __( 'File it there', 'vergelabs-media-library' ),
        'dismiss'      => __( 'Not that one', 'vergelabs-media-library' ),
        'working'      => __( 'Looking…', 'vergelabs-media-library' ),
        'allDone'      => __( 'We have been through everything that had no folder.', 'vergelabs-media-library' ),
        /* translators: %d: number of files. */
        'filed'        => __( 'Filed %d into folders that had earned it.', 'vergelabs-media-library' ),
        /* translators: %d: number of files. */
        'waiting'      => __( '%d waiting for you.', 'vergelabs-media-library' ),
        /* translators: %d: number of files looked at. */
        // Built here rather than derived from ajaxurl in the browser: a
        // string-replace on a global that WordPress happens to print is not a
        // way to know where a screen lives.
        'aiUrl'         => admin_url( 'admin.php?page=media-ai' ),
        'nextPropose'   => __( 'None of the folders you already have is a close enough match. Sorting into folders, below, can work out new folders for these instead — it looks at what is in the pictures rather than trying to fit them into what exists.', 'vergelabs-media-library' ),
        'nextDescribe'  => __( 'We have not looked at %d of your loose files yet, so there is nothing to compare them against. Describe them on the AI screen and come back.', 'vergelabs-media-library' ),
        'nextNothing'   => __( 'Every file is in a folder. Nothing to do here.', 'vergelabs-media-library' ),
        'goPropose'     => __( 'Work out new folders', 'vergelabs-media-library' ),
        'goDescribe'    => __( 'Go and describe them', 'vergelabs-media-library' ),
        'noneNear'     => __( 'We looked at %d of them and none clearly belongs in a folder you already have.', 'vergelabs-media-library' ),
        'nothingLoose' => __( 'Every file we have looked at is already in a folder.', 'vergelabs-media-library' ),
    ) );
}
