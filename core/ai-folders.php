<?php
/**
 *  AI smart folders — the index, asked out loud.
 *
 *  Since 3.2.0 every described attachment carries a handful of enums in
 *  {prefix}vergeml_ai_index: what kind of thing it is, whether there are
 *  people in it, whether there is text in the picture, and — for documents —
 *  what kind of document. Nothing has ever read them except the Librarian's
 *  date/type scheme. This file turns them into rows in the folder panel.
 *
 *  Three ideas, and they are all borrowed rather than invented:
 *
 *  1. **It attaches, it does not replace.** Every folder here is registered
 *     through the `vergeml_smart_folders` filter, so the five folders that
 *     were here first do not know this file exists. In safe mode, where this
 *     file never loads, the panel is simply the panel it was.
 *
 *  2. **One statement, still.** The counts are extra branches on the UNION
 *     that smart-folders.php already runs, not a second query. The tree
 *     endpoint's budget is six and the panel ships with the page.
 *
 *  3. **A join, not a list of ids.** WP_Query has no argument for "join a
 *     table of ours", so the translation hands back a marker and the
 *     posts_clauses filter below turns it into one INNER JOIN on an indexed
 *     column. Resolving to `post__in` instead would be a second query whose
 *     cost grows with the library, which is the thing the budget exists to
 *     prevent.
 *
 *  What this file deliberately does not do: similarity. Distance between
 *  embeddings cannot be asked of SQL, organize.php does it in PHP over
 *  projected vectors in a stepped loop, and doing that while rendering a page
 *  would be exactly the N+1 everything here is arranged to avoid.
 *
 *  @since 3.4
 */


/**
 *  The columns this file may filter on, and nothing else.
 *
 *  A hard allowlist rather than a check, because a column name cannot be
 *  bound by $wpdb->prepare -- it goes into the SQL as a name -- so the only
 *  safe column name is one that was written here by hand.
 */

function vergeml_ai_folder_columns() {
    return array( 'kind', 'has_people', 'has_text', 'document_type' );
}


/**
 *  vergeml_ai_folders
 *
 *  The thirteen, in the order they are shown. Fixed, not sorted by size: a
 *  row that moves when the numbers move is a row nobody can learn the place
 *  of.
 *
 *  Ordered kind-first because that is the question a media library is asked
 *  most, then the two whole-library questions, then the document types --
 *  which are only ever populated when `kind` is `document`, and are therefore
 *  the rows most often absent.
 */

function vergeml_ai_folders() {

    $folders = array();

    $kinds = array(
        'photo'        => __( 'Photos', 'vergelabs-media-library' ),
        'illustration' => __( 'Illustrations', 'vergelabs-media-library' ),
        'screenshot'   => __( 'Screenshots', 'vergelabs-media-library' ),
        'document'     => __( 'Documents', 'vergelabs-media-library' ),
        'diagram'      => __( 'Diagrams', 'vergelabs-media-library' ),
        'logo'         => __( 'Logos', 'vergelabs-media-library' ),
    );

    foreach ( $kinds as $value => $label ) {
        $folders[ 'ai-kind-' . $value ] = array(
            'label' => $label,
            'scan'  => false,
            'group' => 'ai',
            'index' => array( 'column' => 'kind', 'value' => $value ),
        );
    }

    $folders['ai-people'] = array(
        'label' => __( 'People in the picture', 'vergelabs-media-library' ),
        'scan'  => false,
        'group' => 'ai',
        'index' => array( 'column' => 'has_people', 'value' => 1 ),
    );

    $folders['ai-text'] = array(
        'label' => __( 'Text in the picture', 'vergelabs-media-library' ),
        'scan'  => false,
        'group' => 'ai',
        'index' => array( 'column' => 'has_text', 'value' => 1 ),
    );

    $documents = array(
        'invoice'  => __( 'Invoices', 'vergelabs-media-library' ),
        'receipt'  => __( 'Receipts', 'vergelabs-media-library' ),
        'contract' => __( 'Contracts', 'vergelabs-media-library' ),
        'form'     => __( 'Forms', 'vergelabs-media-library' ),
        'report'   => __( 'Reports', 'vergelabs-media-library' ),
    );

    foreach ( $documents as $value => $label ) {
        $folders[ 'ai-doc-' . $value ] = array(
            'label' => $label,
            'scan'  => false,
            'group' => 'ai',
            'index' => array( 'column' => 'document_type', 'value' => $value ),
        );
    }

    return $folders;
}


/**
 *  Whether the group is switched on at all.
 *
 *  A member of the filter settings that were already there rather than an
 *  option of its own: "which of these do you want in the panel" is a question
 *  this plugin already asks, and a second place to answer it would be a
 *  second place to look.
 */

function vergeml_ai_folders_enabled() {

    $options = get_option( 'vergeml_lib_options', array() );

    $show = isset( $options['filters_to_show'] ) ? (array) $options['filters_to_show'] : array();

    return in_array( 'ai', $show, true );
}


add_filter( 'vergeml_smart_folders', 'vergeml_ai_folders_register' );

function vergeml_ai_folders_register( $folders ) {

    if ( ! vergeml_ai_folders_enabled() ) {
        return $folders;
    }

    return array_merge( (array) $folders, vergeml_ai_folders() );
}


/* -------------------------------------------------------------- the join */

/**
 *  vergeml_ai_folders_clauses
 *
 *  One INNER JOIN and one WHERE, and only for the query that asked.
 *
 *  Three guards, and all three are load-bearing. Without the first this would
 *  join the index onto every attachment query on the site, including core's
 *  own grid with nothing selected. Without the second it would fire on posts.
 *  Without the third an unregistered key -- a hand-typed URL -- would reach
 *  the column allowlist with something nobody registered.
 */

add_filter( 'posts_clauses', 'vergeml_ai_folders_clauses', 20, 2 );

function vergeml_ai_folders_clauses( $clauses, $query ) {

    global $wpdb;

    $key = $query->get( 'vergeml_ai_filter' );

    if ( ! is_string( $key ) || '' === $key ) {
        return $clauses;
    }

    $post_type = $query->get( 'post_type' );

    if ( 'attachment' !== $post_type && array( 'attachment' ) !== (array) $post_type ) {
        return $clauses;
    }

    $folders = vergeml_smart_folders();

    if ( ! isset( $folders[ $key ]['index'] ) ) {
        return $clauses;
    }

    $spec   = $folders[ $key ]['index'];
    $column = isset( $spec['column'] ) ? (string) $spec['column'] : '';

    if ( ! in_array( $column, vergeml_ai_folder_columns(), true ) ) {
        return $clauses;
    }

    $table = vergeml_index_table();

    // The table name and the column name are both ours -- one registered on
    // $wpdb, one out of the allowlist above. The value is the only thing that
    // came from outside, and it is bound.
    $clauses['join'] .= " INNER JOIN {$table} vgml_ai ON vgml_ai.attachment_id = {$wpdb->posts}.ID ";

    $clauses['where'] .= $wpdb->prepare( " AND vgml_ai.{$column} = %s ", (string) $spec['value'] );

    return $clauses;
}


/*
 *  WP_Query drops arguments it does not know when it builds the query vars
 *  for the main query, so the marker has to be declared to survive the trip
 *  from the list screen's URL.
 */

add_filter( 'query_vars', 'vergeml_ai_folders_query_var' );

function vergeml_ai_folders_query_var( $vars ) {
    $vars[] = 'vergeml_ai_filter';
    return $vars;
}


/* ------------------------------------------------------------- the counts */

/**
 *  vergeml_ai_folders_count_branches
 *
 *  Four branches, not thirteen. Grouping inside the branch gives every value
 *  of a column its own row out of one pass, so the statement stays readable
 *  and the six kinds cost what one costs.
 *
 *  Plus the honesty pair: how many files have been looked at, and how many
 *  there are. Without those a count of forty reads as "forty screenshots" on
 *  a library where two hundred of eight thousand files have been described.
 *
 *  Nothing is added at all when the schema has never been laid down -- there
 *  would be no table to join, and the whole statement would fail rather than
 *  this half of it.
 */

add_filter( 'vergeml_smart_count_branches', 'vergeml_ai_folders_count_branches' );

function vergeml_ai_folders_count_branches( $branches ) {

    global $wpdb;

    if ( ! vergeml_ai_folders_enabled() ) {
        return $branches;
    }

    if ( ! function_exists( 'vergeml_index_state' ) ) {
        return $branches;
    }

    $state = vergeml_index_state();

    if ( empty( $state['schema'] ) ) {
        return $branches;
    }

    $table = vergeml_index_table();

    $attachment = "p.post_type = 'attachment' AND p.post_status = 'inherit'";

    // Unknown enum values are stored verbatim by the contract and match no
    // folder, so these rows do not sum to `_described`. That is why
    // `_described` is asked for separately rather than added up.
    $branches[] = array(
        'sql' => "SELECT CONCAT( 'ai-kind-', x.kind ) AS k, COUNT(*) AS c
                    FROM {$wpdb->posts} p
                    JOIN {$table} x ON x.attachment_id = p.ID
                   WHERE {$attachment} AND x.kind <> ''
                   GROUP BY x.kind",
        'args' => array(),
    );

    $branches[] = array(
        'sql' => "SELECT CONCAT( 'ai-doc-', x.document_type ) AS k, COUNT(*) AS c
                    FROM {$wpdb->posts} p
                    JOIN {$table} x ON x.attachment_id = p.ID
                   WHERE {$attachment} AND x.document_type <> ''
                   GROUP BY x.document_type",
        'args' => array(),
    );

    $branches[] = array(
        'sql' => "SELECT 'ai-people' AS k, COUNT(*) AS c
                    FROM {$wpdb->posts} p
                    JOIN {$table} x ON x.attachment_id = p.ID
                   WHERE {$attachment} AND x.has_people = 1",
        'args' => array(),
    );

    $branches[] = array(
        'sql' => "SELECT 'ai-text' AS k, COUNT(*) AS c
                    FROM {$wpdb->posts} p
                    JOIN {$table} x ON x.attachment_id = p.ID
                   WHERE {$attachment} AND x.has_text = 1",
        'args' => array(),
    );

    $branches[] = array(
        'sql' => "SELECT '_described' AS k, COUNT(*) AS c
                    FROM {$wpdb->posts} p
                    JOIN {$table} x ON x.attachment_id = p.ID
                   WHERE {$attachment} AND x.described_at IS NOT NULL",
        'args' => array(),
    );

    $branches[] = array(
        'sql' => "SELECT '_total' AS k, COUNT(*) AS c
                    FROM {$wpdb->posts} p
                   WHERE {$attachment}",
        'args' => array(),
    );

    return $branches;
}
