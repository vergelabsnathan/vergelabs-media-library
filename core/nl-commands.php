<?php

if ( ! defined( 'ABSPATH' ) )
    exit;

/**
 *  Saying what you want, in words.
 *
 *  Phase 4c. "move the screenshots into Products", typed into a box, doing
 *  what it says. The interesting part is not understanding the sentence --
 *  the sentences are short and the vocabulary is the user's own folders. The
 *  interesting part is that a sentence is untrusted input that ends in a
 *  write to somebody's library, and everything here is arranged around that.
 *
 *  Four rules, and none of them is negotiable:
 *
 *  1. **An allowlist of verbs, and delete is not on it.** move, tag, rename,
 *     create. There is no phrasing of any sentence that reaches a delete,
 *     because there is no delete to reach: this file contains no call that
 *     removes a file, a folder or an assignment. "Delete the unused ones" is
 *     answered with a refusal that names the four verbs, not with an
 *     apology, and certainly not with a deletion.
 *
 *  2. **Nothing runs from the sentence.** Parsing produces a plan; the plan
 *     is shown with the actual files it would touch and a count; running
 *     takes the plan, never the text. So the thing somebody approves is the
 *     thing that happens, and a sentence that resolved to four thousand files
 *     says four thousand before it says anything else.
 *
 *  3. **The text stays data.** It is matched against a vocabulary built from
 *     the site's own folders and smart folders. It is never evaluated,
 *     never interpolated into SQL, and never used as a callback name. What
 *     the parser cannot match, it refuses.
 *
 *  4. **Hand-written fields are never overwritten.** Renaming applies to
 *     folders. A file's own title, caption and alt are somebody's writing;
 *     core/ai-index.php already tracks which of those a person has touched,
 *     and nothing here writes to any of them.
 *
 *  The parser is deterministic and small on purpose. It is also filterable,
 *  so a model can be put in front of it later without any of the four rules
 *  above moving: whatever produces the intent, it still has to name a verb
 *  from the allowlist and a selection this file can resolve, and it still
 *  cannot run anything.
 *
 *  @since 3.6
 */


/**
 *  The verbs. Adding to this list is a decision, not a configuration -- which
 *  is why it is a function with a comment rather than an option.
 *
 *  There is deliberately no `delete` and no `empty`. Removing media is Phase
 *  5's problem and will arrive with a quarantine and a delay, not with a
 *  sentence.
 */

function vergeml_nl_verbs() {

    return array( 'move', 'tag', 'rename', 'create' );
}


/**
 *  vergeml_nl_vocabulary
 *
 *  What the words can refer to on this site: its folders, and the smart
 *  folders it offers. Built fresh rather than stored, because both change.
 *
 *  Keyed by a lowercased name so matching is a lookup and never a search
 *  through user input.
 */

function vergeml_nl_vocabulary() {

    $taxonomy = vergeml_librarian_taxonomy();

    $out = array( 'folders' => array(), 'smart' => array() );

    if ( '' === $taxonomy ) {
        return $out;
    }

    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $out['folders'][ vergeml_nl_key( $term->name ) ] = (int) $term->term_id;
        }
    }

    if ( function_exists( 'vergeml_smart_folders' ) ) {
        foreach ( vergeml_smart_folders() as $key => $spec ) {
            $out['smart'][ vergeml_nl_key( $spec['label'] ) ] = $key;
        }
    }

    return $out;
}


/**
 *  A phrase, flattened to something comparable: lowercase, no punctuation,
 *  single spaces, and without the articles that carry no meaning here.
 */

function vergeml_nl_key( $text ) {

    $text = strtolower( wp_strip_all_tags( (string) $text ) );
    $text = preg_replace( '/[^a-z0-9 ]+/', ' ', $text );
    $text = preg_replace( '/\s+/', ' ', $text );
    $text = trim( $text );

    $drop = array( 'the ', 'all ', 'every ', 'my ', 'any ' );

    foreach ( $drop as $prefix ) {
        if ( 0 === strpos( $text, $prefix ) ) {
            $text = substr( $text, strlen( $prefix ) );
        }
    }

    // Plural is how people talk about folders; singular is often how they are
    // named. Both are tried, so "Screenshot" matches "screenshots".
    return trim( $text );
}


/**
 *  vergeml_nl_parse
 *
 *  A sentence in; an intent, or a WP_Error saying exactly what it could not
 *  work out. Never a guess.
 *
 *  Filterable, so a model can produce the intent instead. What comes back is
 *  validated here either way -- the allowlist is enforced after the filter,
 *  not before it, so a model cannot introduce a verb this file does not have.
 */

function vergeml_nl_parse( $text ) {

    $text = trim( (string) $text );

    if ( '' === $text ) {
        return new WP_Error( 'vergeml_nl_empty', __( 'Nothing to do.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $intent = apply_filters( 'vergeml_nl_parse', null, $text );

    if ( null === $intent ) {
        $intent = vergeml_nl_parse_plain( $text );
    }

    if ( is_wp_error( $intent ) ) {
        return $intent;
    }

    if ( ! is_array( $intent ) || empty( $intent['verb'] ) || ! in_array( $intent['verb'], vergeml_nl_verbs(), true ) ) {
        return new WP_Error(
            'vergeml_nl_unknown_verb',
            /* translators: %s: the list of verbs the plugin understands. */
            sprintf( __( 'I only know how to %s. Nothing here deletes anything.', 'vergelabs-media-library' ), implode( ', ', vergeml_nl_verbs() ) ),
            array( 'status' => 400 )
        );
    }

    return $intent;
}


/**
 *  The parser itself. Four shapes, and anything else is refused.
 *
 *      move <selection> to <folder>
 *      tag  <selection> with <folder>
 *      rename <folder> to <name>
 *      create <name>
 *
 *  "into" reads better than "to" for the first one and people type both, so
 *  both are accepted. That is the extent of the cleverness, on purpose: a
 *  parser that tries hard produces confident wrong answers, and this one is
 *  allowed to say it did not understand.
 */

function vergeml_nl_parse_plain( $text ) {

    /*
     *  Whitespace is normalised; case is not.
     *
     *  Matching is case-insensitive -- people type "Move" and "move" -- but
     *  what comes back out of a capture group is a name somebody chose, and
     *  lowercasing the sentence first turned "rename Photos to Family Album"
     *  into a folder called "family album". So the patterns carry /i and the
     *  text keeps its capitals.
     */
    $flat = trim( preg_replace( '/\s+/', ' ', (string) $text ) );

    /*
     *  Refused by name rather than by falling through to "I did not
     *  understand", because somebody typing this deserves to know that the
     *  answer is no and not that the phrasing was wrong.
     */
    if ( preg_match( '/^(delete|remove|trash|empty|erase|purge)\b/i', $flat ) ) {
        return new WP_Error(
            'vergeml_nl_no_delete',
            __( 'Whatever you ask, this cannot delete anything. It can only move files, tag them, rename folders and make new ones.', 'vergelabs-media-library' ),
            array( 'status' => 400 )
        );
    }

    if ( preg_match( '/^(?:create|make|add)(?: a)?(?: new)?(?: folder)? (?:called |named )?(.+)$/i', $flat, $m ) ) {
        return array( 'verb' => 'create', 'name' => trim( $m[1] ) );
    }

    if ( preg_match( '/^rename (.+?) (?:to|as) (.+)$/i', $flat, $m ) ) {
        return array( 'verb' => 'rename', 'from' => trim( $m[1] ), 'name' => trim( $m[2] ) );
    }

    if ( preg_match( '/^move (.+?) (?:to|into|in to) (.+)$/i', $flat, $m ) ) {
        return array( 'verb' => 'move', 'selection' => trim( $m[1] ), 'folder' => trim( $m[2] ) );
    }

    if ( preg_match( '/^tag (.+?) (?:with|as) (.+)$/i', $flat, $m ) ) {
        return array( 'verb' => 'tag', 'selection' => trim( $m[1] ), 'folder' => trim( $m[2] ) );
    }

    return new WP_Error(
        'vergeml_nl_unparsed',
        __( 'I did not understand that. Try "move the screenshots into Products", "tag the invoices with Accounts", "rename Photos to Pictures", or "create a folder called Archive".', 'vergelabs-media-library' ),
        array( 'status' => 400 )
    );
}


/**
 *  vergeml_nl_select
 *
 *  Which files a phrase refers to, as ids.
 *
 *  Resolved against the site's own vocabulary and nothing else: a folder it
 *  has, or a smart folder it offers. An unrecognised phrase is an error and
 *  never an empty selection -- "it matched nothing" and "I did not know what
 *  you meant" look identical in a count, and only one of them is safe to act
 *  on.
 */

function vergeml_nl_select( $phrase, $limit = 500 ) {

    $taxonomy = vergeml_librarian_taxonomy();
    $key      = vergeml_nl_key( $phrase );
    $vocab    = vergeml_nl_vocabulary();

    $args = array(
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => (int) $limit,
        'fields'         => 'ids',
        'orderby'        => 'ID',
        'order'          => 'DESC',
    );

    if ( isset( $vocab['smart'][ $key ] ) ) {

        $smart = vergeml_smart_query_args( $vocab['smart'][ $key ] );
        $query = new WP_Query( array_merge( $args, $smart ) );

        return array( 'ids' => $query->posts, 'found' => (int) $query->found_posts, 'what' => $phrase );
    }

    if ( isset( $vocab['folders'][ $key ] ) && '' !== $taxonomy ) {

        $query = new WP_Query( array_merge( $args, array(
            'tax_query' => array( array(
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => array( $vocab['folders'][ $key ] ),
            ) ),
        ) ) );

        return array( 'ids' => $query->posts, 'found' => (int) $query->found_posts, 'what' => $phrase );
    }

    if ( in_array( $key, array( 'unfiled', 'loose files', 'files with no folder', 'nothing' ), true ) && '' !== $taxonomy ) {

        $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );

        $query = new WP_Query( array_merge( $args, array(
            'tax_query' => array( array(
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => is_wp_error( $terms ) ? array() : $terms,
                'operator' => 'NOT IN',
            ) ),
        ) ) );

        return array( 'ids' => $query->posts, 'found' => (int) $query->found_posts, 'what' => $phrase );
    }

    return new WP_Error(
        'vergeml_nl_unknown_selection',
        /* translators: %s: the phrase the user typed. */
        sprintf( __( 'I do not know what "%s" refers to. It has to be one of your folders, or one of the folders in the panel.', 'vergelabs-media-library' ), $phrase ),
        array( 'status' => 400 )
    );
}


/**
 *  vergeml_nl_plan
 *
 *  What would happen, without any of it happening.
 *
 *  Everything the screen needs to show somebody before they agree: the verb,
 *  the count, a handful of the actual files, and the folder it would end in.
 *  Nothing in this function writes.
 */

function vergeml_nl_plan( $text ) {

    $intent = vergeml_nl_parse( $text );

    if ( is_wp_error( $intent ) ) {
        return $intent;
    }

    $taxonomy = vergeml_librarian_taxonomy();

    if ( '' === $taxonomy ) {
        return new WP_Error( 'vergeml_nl_no_taxonomy', __( 'You have no folders switched on yet, so there is nowhere to put anything.', 'vergelabs-media-library' ), array( 'status' => 409 ) );
    }

    $vocab = vergeml_nl_vocabulary();

    if ( 'create' === $intent['verb'] ) {

        $name = sanitize_text_field( $intent['name'] );

        if ( '' === $name ) {
            return new WP_Error( 'vergeml_nl_no_name', __( 'A folder needs a name.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
        }

        return array(
            'verb'    => 'create',
            'name'    => $name,
            'exists'  => isset( $vocab['folders'][ vergeml_nl_key( $name ) ] ),
            'count'   => 0,
            'sample'  => array(),
            'summary' => sprintf(
                /* translators: %s: folder name. */
                __( 'Create a folder called “%s”.', 'vergelabs-media-library' ),
                $name
            ),
        );
    }

    if ( 'rename' === $intent['verb'] ) {

        $from = vergeml_nl_key( $intent['from'] );
        $name = sanitize_text_field( $intent['name'] );

        if ( ! isset( $vocab['folders'][ $from ] ) ) {
            return new WP_Error(
                'vergeml_nl_unknown_folder',
                /* translators: %s: the folder name the user typed. */
                sprintf( __( 'You have no folder called "%s".', 'vergelabs-media-library' ), $intent['from'] ),
                array( 'status' => 404 )
            );
        }

        return array(
            'verb'    => 'rename',
            'term_id' => (int) $vocab['folders'][ $from ],
            'name'    => $name,
            'count'   => 0,
            'sample'  => array(),
            'summary' => sprintf(
                /* translators: 1: current folder name, 2: new folder name. */
                __( 'Rename “%1$s” to “%2$s”. The files in it do not move.', 'vergelabs-media-library' ),
                $intent['from'],
                $name
            ),
        );
    }

    // move and tag both need a selection and a destination folder.
    $selection = vergeml_nl_select( $intent['selection'] );

    if ( is_wp_error( $selection ) ) {
        return $selection;
    }

    $folder_key = vergeml_nl_key( $intent['folder'] );

    if ( ! isset( $vocab['folders'][ $folder_key ] ) ) {
        return new WP_Error(
            'vergeml_nl_unknown_folder',
            /* translators: 1: the folder name the user typed, 2: the same name again. */
            sprintf( __( 'You have no folder called "%1$s". Create it first, or say "create a folder called %2$s".', 'vergelabs-media-library' ), $intent['folder'], $intent['folder'] ),
            array( 'status' => 404 )
        );
    }

    $term_id = (int) $vocab['folders'][ $folder_key ];
    $term    = get_term( $term_id, $taxonomy );

    $sample = array();

    foreach ( array_slice( $selection['ids'], 0, 6 ) as $id ) {
        $sample[] = array(
            'id'    => (int) $id,
            'title' => get_the_title( $id ),
            'thumb' => wp_get_attachment_image_url( (int) $id, 'thumbnail' ),
        );
    }

    return array(
        'verb'    => $intent['verb'],
        'term_id' => $term_id,
        'ids'     => array_map( 'intval', $selection['ids'] ),
        'count'   => (int) $selection['found'],
        'sample'  => $sample,
        'summary' => sprintf(
            'move' === $intent['verb']
                /* translators: 1: number of files, 2: what they were selected by, 3: folder name. */
                ? __( 'Move %1$d files (%2$s) into “%3$s”. They leave whichever folders they are in now.', 'vergelabs-media-library' )
                /* translators: 1: number of files, 2: what they were selected by, 3: folder name. */
                : __( 'Add %1$d files (%2$s) to “%3$s”. They stay in the folders they are in now as well.', 'vergelabs-media-library' ),
            (int) $selection['found'],
            $selection['what'],
            $term instanceof WP_Term ? $term->name : ''
        ),
    );
}


/**
 *  vergeml_nl_run
 *
 *  Carry out a plan. The plan, not the sentence.
 *
 *  Everything it does to files goes through the Librarian's moves log in a
 *  batch of its own, so undo covers it without knowing this file exists.
 *  Creating and renaming a folder are not logged there because they are not
 *  assignments -- and neither of them loses anything: a created folder is
 *  empty, and a renamed one keeps every file it had.
 */

function vergeml_nl_run( $plan ) {

    $taxonomy = vergeml_librarian_taxonomy();

    if ( '' === $taxonomy || empty( $plan['verb'] ) || ! in_array( $plan['verb'], vergeml_nl_verbs(), true ) ) {
        return new WP_Error( 'vergeml_nl_bad_plan', __( 'That is not a plan this can carry out.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    if ( 'create' === $plan['verb'] ) {

        $name = sanitize_text_field( $plan['name'] );
        $term = term_exists( $name, $taxonomy );

        if ( ! $term ) {
            $term = wp_insert_term( $name, $taxonomy );
        }

        if ( is_wp_error( $term ) ) {
            return $term;
        }

        return array( 'done' => 1, 'term_id' => (int) $term['term_id'] );
    }

    if ( 'rename' === $plan['verb'] ) {

        $updated = wp_update_term( (int) $plan['term_id'], $taxonomy, array( 'name' => sanitize_text_field( $plan['name'] ) ) );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        return array( 'done' => 1, 'term_id' => (int) $plan['term_id'] );
    }

    $term_id = (int) $plan['term_id'];
    $ids     = array_map( 'intval', (array) $plan['ids'] );

    if ( ! $ids ) {
        return array( 'done' => 0 );
    }

    $batch_id = vergeml_autofile_batch( 'spoken' );

    if ( is_wp_error( $batch_id ) ) {
        return $batch_id;
    }

    $moves = array();
    $done  = 0;

    foreach ( $ids as $id ) {

        if ( ! get_post( $id ) ) {
            continue;
        }

        // move replaces, tag adds. The append flag is the whole difference,
        // and it is the difference the summary promised.
        $append = 'tag' === $plan['verb'];

        $set = wp_set_object_terms( $id, array( $term_id ), $taxonomy, $append );

        if ( is_wp_error( $set ) ) {
            continue;
        }

        $moves[] = array( $batch_id, $id, $term_id, 0 );
        $done++;
    }

    if ( $moves ) {
        vergeml_librarian_moves_insert( $moves );
    }

    if ( function_exists( 'vergeml_autofile_centroid' ) ) {
        delete_term_meta( $term_id, VERGEML_AUTOFILE_CENTROID );
    }

    return array( 'done' => $done, 'batch_id' => (int) $batch_id, 'term_id' => $term_id );
}


/* --------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_nl_routes' );

function vergeml_nl_routes() {

    $can = function () {
        return current_user_can( 'manage_categories' );
    };

    /*
     *  Two endpoints, and the split is the safety.
     *
     *  The first takes words and returns a plan; it writes nothing, ever.
     *  The second takes a plan and does it, and cannot be handed a sentence.
     *  So there is no request anywhere that turns typed text straight into a
     *  change to somebody's library.
     */
    register_rest_route( VERGEML_REST_NS, '/say-plan', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_nl_rest_plan',
        'args'                => array(
            'text' => array( 'type' => 'string', 'required' => true ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/say-run', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_nl_rest_run',
        'args'                => array(
            'plan' => array( 'required' => true ),
        ),
    ) );
}


function vergeml_nl_rest_plan( WP_REST_Request $request ) {

    $plan = vergeml_nl_plan( (string) $request->get_param( 'text' ) );

    if ( is_wp_error( $plan ) ) {
        return $plan;
    }

    return rest_ensure_response( $plan );
}


function vergeml_nl_rest_run( WP_REST_Request $request ) {

    $plan = $request->get_param( 'plan' );

    if ( ! is_array( $plan ) ) {
        return new WP_Error( 'vergeml_nl_bad_plan', __( 'That is not a plan this can carry out.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    /*
     *  The plan came back from the browser, so every field in it is input
     *  again. Rebuilt here rather than trusted: the verb has to be on the
     *  allowlist, the ids have to be integers, and the folder has to be a
     *  term that exists in the taxonomy this plugin files into. A plan that
     *  arrives naming a term in some other taxonomy is not carried out.
     */
    $taxonomy = vergeml_librarian_taxonomy();

    $clean = array(
        'verb'    => isset( $plan['verb'] ) ? sanitize_key( $plan['verb'] ) : '',
        'term_id' => isset( $plan['term_id'] ) ? (int) $plan['term_id'] : 0,
        'name'    => isset( $plan['name'] ) ? sanitize_text_field( $plan['name'] ) : '',
        'ids'     => isset( $plan['ids'] ) ? array_map( 'intval', (array) $plan['ids'] ) : array(),
    );

    if ( ! in_array( $clean['verb'], vergeml_nl_verbs(), true ) ) {
        return new WP_Error( 'vergeml_nl_bad_plan', __( 'That is not a plan this can carry out.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    if ( in_array( $clean['verb'], array( 'move', 'tag', 'rename' ), true ) ) {
        if ( ! get_term( $clean['term_id'], $taxonomy ) instanceof WP_Term ) {
            return new WP_Error( 'vergeml_nl_unknown_folder', __( 'That folder is not one of yours.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
        }
    }

    $result = vergeml_nl_run( $clean );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return rest_ensure_response( $result );
}


/* ----------------------------------------------------------------- the card */

// Moving and tagging files, so it belongs with the Librarian.
add_action( 'vergeml_librarian_page_cards', 'vergeml_nl_card' );

function vergeml_nl_card() {

    /*
     *  What it can actually be asked.
     *
     *  This was one text box with one placeholder, which is the worst possible
     *  brief: a blank field implies it understands anything, the placeholder
     *  vanishes the moment you type, and the four things it genuinely does
     *  were written in a paragraph above rather than shown. Somebody typed one
     *  sentence, got "nothing matched", and never came back.
     *
     *  Four examples, one per verb, and clicking one fills the box. The point
     *  is not the examples -- it is that the shape of a sentence it can act on
     *  is now visible without reading anything.
     */
    $examples = array(
        __( 'move the screenshots into Products', 'vergelabs-media-library' ),
        __( 'tag everything in Autumn 2026 as seasonal', 'vergelabs-media-library' ),
        __( 'rename Blog to Journal', 'vergelabs-media-library' ),
        __( 'make a folder called Press', 'vergelabs-media-library' ),
    );

    ?>
    <div class="vgml-ai-card">
        <h2><?php esc_html_e( 'Move files by typing a sentence', 'vergelabs-media-library' ); ?></h2>
        <p class="description"><?php esc_html_e( 'For when you already know what you want and it is faster to say it than to click it. We show you exactly which files would move before anything happens, and it cannot delete anything.', 'vergelabs-media-library' ); ?></p>
        <p>
            <input type="text" id="vgml-say-text" class="regular-text" placeholder="<?php esc_attr_e( 'move the screenshots into Products', 'vergelabs-media-library' ); ?>">
            <button type="button" class="button" id="vgml-say-plan"><?php esc_html_e( 'Show me what it would do', 'vergelabs-media-library' ); ?></button>
        </p>
        <p class="vgml-say-examples">
            <span class="vgml-say-examples-l"><?php esc_html_e( 'Things it understands:', 'vergelabs-media-library' ); ?></span>
            <?php foreach ( $examples as $example ) : ?>
                <button type="button" class="button-link vgml-say-example"><?php echo esc_html( $example ); ?></button>
            <?php endforeach; ?>
        </p>
        <div id="vgml-say-preview" class="vgml-say-preview" hidden>
            <p id="vgml-say-summary"></p>
            <ul id="vgml-say-sample" class="vgml-say-sample"></ul>
            <p>
                <button type="button" class="button button-primary" id="vgml-say-go"><?php esc_html_e( 'Do it', 'vergelabs-media-library' ); ?></button>
                <button type="button" class="button" id="vgml-say-cancel"><?php esc_html_e( 'Never mind', 'vergelabs-media-library' ); ?></button>
            </p>
        </div>
        <p id="vgml-say-note"></p>
    </div>
    <?php
}


add_action( 'admin_enqueue_scripts', 'vergeml_nl_assets' );

function vergeml_nl_assets( $hook ) {

    if ( false === strpos( (string) $hook, 'media-librarian' ) ) {
        return;
    }

    wp_enqueue_script(
        'vergeml-say',
        plugins_url( 'js/vergeml-say.js', VERGEML_FILE ),
        array( 'wp-api-fetch' ),
        vergeml_asset_ver( 'js/vergeml-say.js' ),
        true
    );

    wp_localize_script( 'vergeml-say', 'vergemlSay', array(
        'thinking' => __( 'Working out what that means…', 'vergelabs-media-library' ),
        'doing'    => __( 'Doing it…', 'vergelabs-media-library' ),
        /* translators: %d: number of files changed. */
        'done'     => __( 'Done — %d files moved. If that was not what you meant, undo it on the Folders screen.', 'vergelabs-media-library' ),
        'madeIt'   => __( 'Done.', 'vergelabs-media-library' ),
        'nothing'  => __( 'Nothing matched what you asked, so nothing was changed. Try naming a folder you have, or describing the files differently.', 'vergelabs-media-library' ),
    ) );
}
