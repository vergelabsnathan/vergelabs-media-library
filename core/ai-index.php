<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  The described library, in a table of its own.
 *
 *  Descriptions lived in postmeta, as one serialised array per file under
 *  `_vergeml_ai`. That works for storing and is useless for asking: every
 *  question worth asking of a described library -- which files show people,
 *  which are documents, which look like this one -- is a question about a
 *  field inside that blob, and a field inside a serialised blob is not a
 *  column. It cannot be indexed, compared, or grouped, and the only query
 *  postmeta supports over it is LIKE across the whole thing.
 *
 *  So: one row per attachment, one column per answer. The caption and alt
 *  text stay text; the attributes the model returns become their own indexed
 *  columns; the embedding gets a blob and a dimension count. Everything is
 *  stamped with the model, its version, and a hash of the prompt that
 *  produced it, because a description is only reproducible if you know what
 *  asked for it.
 *
 *  The old postmeta is copied in, not moved: nothing is deleted, so a site
 *  that needs to go back still can. The migration is chunked and resumable
 *  like every other walk in this plugin.
 *
 *  Written ahead of the service that fills it. The attribute columns and the
 *  embedding stay null until the service returns them -- the storage, the
 *  migration and the edit protection are all testable without a model, and
 *  they are the two-thirds of this phase that does not need one.
 *
 *  @since 3.3
 */


const VERGEML_INDEX_TABLE   = 'vergeml_ai_index';
// 2 since 3.4.0: KEY has_text, for the "Text in the picture" smart folder.
const VERGEML_INDEX_VERSION = 2;
const VERGEML_INDEX_OPTION  = 'vergeml_index';

// The postmeta key the index was grown from. Still written by nothing, still
// read by the migration, and deliberately still present on disk.
const VERGEML_META_AI = '_vergeml_ai';

// Rows per migration step. The same reasoning as every other chunk in this
// plugin: shared hosting, and a step that cannot time out.
const VERGEML_INDEX_BATCH = 100;


/**
 *  The table, registered on $wpdb the way core registers its own.
 *
 *  Not decoration. A name interpolated from a helper function is a string the
 *  static analysis cannot follow, so every query built that way reads to
 *  Plugin Check as an unprepared one -- and Plugin Check is the submission
 *  gate. Registered here, `$wpdb->vergeml_ai_index` is a property like
 *  `$wpdb->posts`, understood by the sniffs and re-prefixed for us whenever a
 *  multisite install switches blog.
 */

vergeml_index_register_table();

function vergeml_index_register_table() {

    global $wpdb;

    $wpdb->vergeml_ai_index = $wpdb->prefix . VERGEML_INDEX_TABLE;

    if ( ! in_array( VERGEML_INDEX_TABLE, $wpdb->tables, true ) ) {
        $wpdb->tables[] = VERGEML_INDEX_TABLE;
    }
}


function vergeml_index_table() {
    global $wpdb;
    return $wpdb->vergeml_ai_index;
}


function vergeml_index_state() {
    $state = get_option( VERGEML_INDEX_OPTION, array() );
    return is_array( $state ) ? $state : array();
}


/* ------------------------------------------------------------------ schema */

/**
 *  vergeml_index_install
 *
 *  Create or update the table. Safe to call on every load: dbDelta compares
 *  the schema it is given against the one that exists and issues only the
 *  difference, so this is a no-op once the table matches.
 *
 *  TEXT and BLOB columns carry no DEFAULT. MySQL before 8.0.13 refuses one
 *  outright, and this plugin's floor is a great deal older than that.
 */

function vergeml_index_install() {

    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table   = vergeml_index_table();
    $collate = $wpdb->get_charset_collate();

    /*
     *  dbDelta parses this string rather than executing it, and it is fussy in
     *  ways that fail silently: two spaces after PRIMARY KEY, KEY rather than
     *  INDEX, one field per line, and the same field names on every run or it
     *  will add a second column beside the first.
     */
    $sql = "CREATE TABLE {$table} (
        attachment_id bigint(20) unsigned NOT NULL,
        caption text NULL,
        alt text NULL,
        title varchar(255) NOT NULL DEFAULT '',
        tags text NULL,
        kind varchar(32) NOT NULL DEFAULT '',
        has_people tinyint(1) NULL,
        has_text tinyint(1) NULL,
        document_type varchar(32) NOT NULL DEFAULT '',
        orientation varchar(16) NOT NULL DEFAULT '',
        embedding longblob NULL,
        embedding_dims smallint(5) unsigned NULL,
        model varchar(96) NOT NULL DEFAULT '',
        model_version varchar(64) NOT NULL DEFAULT '',
        prompt_hash varchar(64) NOT NULL DEFAULT '',
        locked varchar(191) NOT NULL DEFAULT '',
        error varchar(64) NOT NULL DEFAULT '',
        described_at datetime NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (attachment_id),
        KEY kind (kind),
        KEY has_people (has_people),
        KEY has_text (has_text),
        KEY document_type (document_type),
        KEY described_at (described_at)
    ) {$collate};";

    dbDelta( $sql );

    $state = vergeml_index_state();
    $state['schema'] = VERGEML_INDEX_VERSION;

    update_option( VERGEML_INDEX_OPTION, $state, false );
}


/**
 *  Hooked rather than called, so that safe mode -- which skips every file in
 *  this folder -- simply means nobody answers and the activation does not
 *  fatal on a function that was never defined.
 */

add_action( 'vergeml_activate', 'vergeml_index_install' );


function vergeml_index_table_exists() {

    global $wpdb;

    $table = vergeml_index_table();

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- asking the schema, not the data.
    return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
    // phpcs:enable
}


/* ------------------------------------------------------------------- rows */

/**
 *  The columns a caller may write. Anything else handed to the setter is
 *  dropped rather than trusted: this is the one place a description crosses
 *  from the service into the database.
 */

function vergeml_index_fields() {

    return array(
        'caption'        => '%s',
        'alt'            => '%s',
        'title'          => '%s',
        'tags'           => '%s',
        'kind'           => '%s',
        'has_people'     => '%d',
        'has_text'       => '%d',
        'document_type'  => '%s',
        'orientation'    => '%s',
        'embedding'      => '%s',
        'embedding_dims' => '%d',
        'model'          => '%s',
        'model_version'  => '%s',
        'prompt_hash'    => '%s',
        'locked'         => '%s',
        'error'          => '%s',
        'described_at'   => '%s',
    );
}


/**
 *  vergeml_index_get
 *
 *  One file's row, as an array, or null. Tags come back as an array and the
 *  embedding as floats, so callers never handle the storage format.
 */

/**
 *  Rows this request is about to ask for one at a time, fetched together.
 *
 *  A loop over a thousand candidates called vergeml_index_get() a thousand
 *  times -- a thousand statements to answer one count on the dashboard.
 *  Primed, the same loop is one statement and a thousand array lookups. The
 *  cache lives for the request and is dropped for any row the setter writes,
 *  so nothing here can serve a stale answer to the code that just changed it.
 */
function vergeml_index_prime( $ids ) {

    global $wpdb;

    $ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );

    if ( ! $ids ) {
        return;
    }

    if ( ! isset( $GLOBALS['vergeml_index_primed'] ) ) {
        $GLOBALS['vergeml_index_primed'] = array();
    }

    foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
        $in = implode( ',', $chunk );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are cast to int above.
        $rows = $wpdb->get_results( "SELECT * FROM {$wpdb->vergeml_ai_index} WHERE attachment_id IN ($in)", ARRAY_A );
        // phpcs:enable
        foreach ( $chunk as $id ) {
            $GLOBALS['vergeml_index_primed'][ $id ] = null; // known absent unless a row says otherwise
        }
        foreach ( (array) $rows as $row ) {
            $GLOBALS['vergeml_index_primed'][ (int) $row['attachment_id'] ] = $row;
        }
    }
}


function vergeml_index_forget( $attachment_id ) {
    unset( $GLOBALS['vergeml_index_primed'][ (int) $attachment_id ] );
}


function vergeml_index_get( $attachment_id ) {

    global $wpdb;

    $attachment_id = (int) $attachment_id;

    if ( isset( $GLOBALS['vergeml_index_primed'] ) && array_key_exists( $attachment_id, $GLOBALS['vergeml_index_primed'] ) ) {
        $row = $GLOBALS['vergeml_index_primed'][ $attachment_id ];
    } else {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table; there is no core API for it.
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->vergeml_ai_index} WHERE attachment_id = %d",
            $attachment_id
        ), ARRAY_A );
        // phpcs:enable
    }

    if ( ! $row ) {
        return null;
    }

    $row = $row + array(); // a copy: the primed row is shared, the decoded one is the caller's

    $row['tags']       = vergeml_index_tags_out( $row['tags'] );
    $row['embedding']  = vergeml_index_vector_out( $row['embedding'] );
    $row['locked']     = vergeml_index_locked_list( $row['locked'] );
    $row['has_people'] = null === $row['has_people'] ? null : (bool) $row['has_people'];
    $row['has_text']   = null === $row['has_text'] ? null : (bool) $row['has_text'];

    return $row;
}


/**
 *  vergeml_index_set
 *
 *  Write some fields of one row, creating it if needed.
 *
 *  Fields the user has edited are refused unless the caller says otherwise:
 *  the whole point of the locked list is that a re-run does not paint over
 *  somebody's own words, and the safe default is the one that protects them.
 */

function vergeml_index_set( $attachment_id, $data, $overwrite_locked = false ) {

    global $wpdb;

    $attachment_id = (int) $attachment_id;

    if ( $attachment_id <= 0 ) {
        return false;
    }

    // A row about to change is not one to answer from the primed copy, and
    // the dashboard's minute-long numbers are about to be wrong.
    vergeml_index_forget( $attachment_id );

    if ( function_exists( 'vergeml_journey_touch' ) ) {
        vergeml_journey_touch();
    }

    $fields  = vergeml_index_fields();
    $existing = vergeml_index_get( $attachment_id );
    $locked   = $existing ? $existing['locked'] : array();

    $row     = array();
    $formats = array();

    foreach ( $data as $key => $value ) {

        if ( ! isset( $fields[ $key ] ) ) {
            continue;
        }

        if ( ! $overwrite_locked && in_array( $key, $locked, true ) ) {
            continue;
        }

        if ( 'tags' === $key ) {
            $value = vergeml_index_tags_in( $value );
        } elseif ( 'embedding' === $key ) {
            $row['embedding_dims'] = is_array( $value ) ? count( $value ) : null;
            $formats['embedding_dims'] = '%d';
            $value = vergeml_index_vector_in( $value );
        } elseif ( 'locked' === $key ) {
            $value = implode( ',', vergeml_index_locked_list( $value ) );
        }

        $row[ $key ]     = $value;
        $formats[ $key ] = $fields[ $key ];
    }

    if ( ! $row ) {
        return false;
    }

    $row['updated_at']     = current_time( 'mysql', true );
    $formats['updated_at'] = '%s';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ( $existing ) {
        $done = $wpdb->update(
            vergeml_index_table(),
            $row,
            array( 'attachment_id' => $attachment_id ),
            array_values( $formats ),
            array( '%d' )
        );
    } else {
        $row['attachment_id']     = $attachment_id;
        $formats['attachment_id'] = '%d';
        $done = $wpdb->insert( vergeml_index_table(), $row, array_values( $formats ) );
    }
    // phpcs:enable

    return false !== $done;
}


function vergeml_index_delete( $attachment_id ) {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return (bool) $wpdb->delete( vergeml_index_table(), array( 'attachment_id' => (int) $attachment_id ), array( '%d' ) );
    // phpcs:enable
}


/**
 *  A deleted attachment takes its description with it. Nothing else in the
 *  plugin would ever clear the row, and an index that outlives its files
 *  answers questions about media that is not there.
 */

add_action( 'delete_attachment', 'vergeml_index_delete' );


/* --------------------------------------------------------- storage formats */

function vergeml_index_tags_in( $tags ) {

    if ( is_string( $tags ) ) {
        $tags = preg_split( '/\s*,\s*/', $tags, -1, PREG_SPLIT_NO_EMPTY );
    }

    $tags = array_values( array_filter( array_map( 'sanitize_text_field', (array) $tags ) ) );

    return $tags ? wp_json_encode( $tags ) : '';
}


function vergeml_index_tags_out( $stored ) {

    if ( ! is_string( $stored ) || '' === $stored ) {
        return array();
    }

    $tags = json_decode( $stored, true );

    return is_array( $tags ) ? $tags : array();
}


/**
 *  The embedding, as bytes.
 *
 *  Packed single-precision floats rather than JSON: a 1536-dimension vector
 *  is 6KB packed and about 20KB as text, and it is written once and read in
 *  bulk. Machine byte order both ways -- these never leave the database they
 *  were written to, and a site that moves hosts moves the whole thing.
 */

function vergeml_index_vector_in( $vector ) {

    if ( ! is_array( $vector ) || ! $vector ) {
        return null;
    }

    $packed = '';

    foreach ( $vector as $value ) {
        $packed .= pack( 'f', (float) $value );
    }

    return $packed;
}


function vergeml_index_vector_out( $packed ) {

    if ( ! is_string( $packed ) || '' === $packed ) {
        return null;
    }

    $floats = unpack( 'f*', $packed );

    return $floats ? array_values( $floats ) : null;
}


function vergeml_index_locked_list( $locked ) {

    if ( is_array( $locked ) ) {
        $list = $locked;
    } else {
        $list = preg_split( '/\s*,\s*/', (string) $locked, -1, PREG_SPLIT_NO_EMPTY );
    }

    $fields = vergeml_index_fields();

    return array_values( array_unique( array_filter( (array) $list, function ( $field ) use ( $fields ) {
        return isset( $fields[ $field ] );
    } ) ) );
}


/* ------------------------------------------------------- edit protection */

/**
 *  vergeml_index_lock
 *
 *  Mark a field as the user's. Nothing generated will overwrite it again,
 *  and the plugin has no screen that unlocks one -- clearing the field is
 *  how somebody asks for it to be filled in again.
 */

function vergeml_index_lock( $attachment_id, $field ) {

    $row = vergeml_index_get( $attachment_id );

    if ( ! $row ) {
        return false;
    }

    $locked = $row['locked'];

    if ( in_array( $field, $locked, true ) ) {
        return true;
    }

    $locked[] = $field;

    return vergeml_index_set( $attachment_id, array( 'locked' => $locked ), true );
}


/**
 *  While the plugin is writing its own description, an edit is not the
 *  user's. Every generated write happens inside this flag, so the hooks below
 *  can tell somebody typing from the pipeline filling a field in.
 */

function vergeml_index_writing( $set = null ) {

    static $writing = false;

    if ( null !== $set ) {
        $writing = (bool) $set;
    }

    return $writing;
}


/**
 *  Alt text is the field this matters most for: it is the one people write by
 *  hand, the one the accessibility pass fills in, and the one a re-run would
 *  otherwise quietly replace.
 */

add_action( 'updated_post_meta', 'vergeml_index_watch_alt', 10, 4 );
add_action( 'added_post_meta', 'vergeml_index_watch_alt', 10, 4 );

function vergeml_index_watch_alt( $meta_id, $post_id, $meta_key, $meta_value ) {

    if ( '_wp_attachment_image_alt' !== $meta_key || vergeml_index_writing() ) {
        return;
    }

    if ( '' === trim( (string) $meta_value ) ) {
        return; // Emptying a field is how somebody asks for it back.
    }

    vergeml_index_lock( $post_id, 'alt' );
}


/**
 *  A retitled attachment is the same statement about the title.
 */

add_action( 'attachment_updated', 'vergeml_index_watch_title', 10, 3 );

function vergeml_index_watch_title( $post_id, $after, $before ) {

    if ( vergeml_index_writing() ) {
        return;
    }

    if ( $after->post_title !== $before->post_title && '' !== trim( (string) $after->post_title ) ) {
        vergeml_index_lock( $post_id, 'title' );
    }
}


/* ---------------------------------------------------------------- derived */

/**
 *  vergeml_index_orientation
 *
 *  Portrait, landscape or square, from the dimensions WordPress already
 *  stored. One of the five attributes the phase asks for, and the only one
 *  that never needed a model to answer -- so it is answered here, free, for
 *  every image in the library.
 */

function vergeml_index_orientation( $attachment_id ) {

    $meta = wp_get_attachment_metadata( $attachment_id );

    $width  = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
    $height = isset( $meta['height'] ) ? (int) $meta['height'] : 0;

    if ( $width <= 0 || $height <= 0 ) {
        return '';
    }

    // A tolerance, because a 1000x1004 photograph is square to everyone
    // except a comparison operator.
    $ratio = $width / $height;

    if ( $ratio > 1.05 ) {
        return 'landscape';
    }

    if ( $ratio < 0.95 ) {
        return 'portrait';
    }

    return 'square';
}


/* ------------------------------------------------------------ re-describing */

/**
 *  vergeml_index_current_stamp
 *
 *  What the pipeline is producing now: the model, its version and the width of
 *  the embedding, taken from the most recently described row.
 *
 *  Read from the data rather than declared in a constant, because the plugin
 *  does not choose the model -- the service does, and it may change it between
 *  one call and the next. The newest description is by definition the one made
 *  by whatever is answering today, so it is the only honest answer to "what is
 *  current".
 */

function vergeml_index_current_stamp() {

    global $wpdb;

    /*
     *  Never a demo row.
     *
     *  On 31-08-2026 the newest row in a library was a mock -- a byte-identical
     *  twin of a file described in demo mode, refilled from its twin on every
     *  pass -- so the stamp read "mock", every real row was stale, the first
     *  eight were re-described, the stamp read "haiku", the four mock rows were
     *  stale, they were refilled from their mock twins, and the stamp read
     *  "mock". Eight credits a cycle, a cycle a minute, 2,391 credits before
     *  lunch. The pipeline that answers today is never the demo.
     */
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row(
        "SELECT model, model_version, embedding_dims, prompt_hash FROM {$wpdb->vergeml_ai_index}
          WHERE error = '' AND described_at IS NOT NULL AND model <> 'mock'
          ORDER BY described_at DESC, attachment_id DESC
          LIMIT 1",
        ARRAY_A
    );
    // phpcs:enable

    if ( ! $row ) {
        return array( 'model' => '', 'model_version' => '', 'dims' => 0, 'prompt_hash' => '' );
    }

    return array(
        'model'         => (string) $row['model'],
        'model_version' => (string) $row['model_version'],
        'dims'          => (int) $row['embedding_dims'],
        /*
         *  The prompt that produced the newest answer.
         *
         *  This column has been written since the index existed, and its
         *  docblock on the service says it is there so that "nothing can
         *  decide whether a stored row is worth re-running after the prompt is
         *  improved". Nothing read it. Improving the prompt -- or, now, typing
         *  a site profile -- left every existing description in place with no
         *  way to find them.
         */
        'prompt_hash'   => (string) $row['prompt_hash'],
    );
}


/**
 *  vergeml_index_stale
 *
 *  Ids whose stamp or embedding width no longer matches the current one.
 *
 *  Without this a model change strands every row already in the table: the
 *  backlog is defined by the absence of a row, so a described file is never
 *  looked at again however far the thing that described it has moved. Clearing
 *  fifty-eight rows by hand to re-run a fixture is the version of that problem
 *  that shows up first, and it showed up in Phase 1.
 *
 *  An empty model, an empty version or a zero width means "do not judge this
 *  one" -- a caller that only cares about the embedding width should not have
 *  every row with a different model handed back as well.
 */

function vergeml_index_stale( $model = '', $version = '', $dims = 0, $after = 0, $limit = 0, $prompt_hash = '', $cooldown = 0 ) {

    global $wpdb;

    $check_model   = '' !== (string) $model ? 1 : 0;
    $check_version = '' !== (string) $version ? 1 : 0;
    $check_dims    = (int) $dims > 0 ? 1 : 0;
    $check_prompt  = '' !== (string) $prompt_hash ? 1 : 0;

    /*
     *  A row described in the last $cooldown seconds is not offered again,
     *  whatever its stamp says. Staleness is judged against the newest row,
     *  and a set judged that way can chase its own tail; this is the floor
     *  under it -- a file is re-described at most once per cooldown, so a
     *  loop that would have cost a credit a second costs one a cooldown and
     *  is visible long before it matters.
     */
    $cooldown = max( 0, (int) $cooldown );

    // Nothing to compare against: no row can be stale. Handing back the whole
    // table because the caller passed nothing would be the worst available
    // reading of an empty argument.
    if ( ! $check_model && ! $check_version && ! $check_dims && ! $check_prompt ) {
        return array();
    }

    /*
     *  One statement, whichever parts of the stamp the caller cares about.
     *
     *  Assembling the WHERE from an array read to the sniffs as unprepared
     *  SQL -- correctly, since they cannot follow a clause built in a loop --
     *  and Plugin Check is the submission gate. So each test carries its own
     *  switch instead: a zero makes that half of the AND false and the term
     *  disappears, and the string handed to prepare is a literal.
     *
     *  OR between the three, because a row is stale if *any* part of the stamp
     *  moved. AND would ask for a row where all of them changed at once, which
     *  is a rarer thing and not what "stale" means.
     */
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT attachment_id FROM {$wpdb->vergeml_ai_index}
          WHERE error = '' AND attachment_id > %d
            AND ( %d = 0 OR described_at IS NULL OR described_at < UTC_TIMESTAMP() - INTERVAL %d SECOND )
            AND (
                    ( %d = 1 AND model <> %s )
                 OR ( %d = 1 AND model_version <> %s )
                 OR ( %d = 1 AND ( embedding_dims IS NULL OR embedding_dims <> %d ) )
                 OR ( %d = 1 AND ( prompt_hash IS NULL OR prompt_hash <> %s ) )
                )
          ORDER BY attachment_id ASC
          LIMIT %d",
        (int) $after,
        $cooldown > 0 ? 1 : 0,
        $cooldown,
        $check_model,
        (string) $model,
        $check_version,
        (string) $version,
        $check_dims,
        (int) $dims,
        $check_prompt,
        (string) $prompt_hash,
        $limit > 0 ? (int) $limit : PHP_INT_MAX
    ) ) );
    // phpcs:enable
}


/* -------------------------------------------------------------- migration */

/**
 *  vergeml_index_pending_migration
 *
 *  Attachments whose description is still only in postmeta. Defined by the
 *  absence of a row rather than by a list, so the walk is resumable without
 *  remembering anything -- the same shape as the health scan.
 */

function vergeml_index_pending_migration( $after = 0, $limit = 0 ) {

    global $wpdb;

    $cap = $limit > 0 ? (int) $limit : PHP_INT_MAX;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT m.post_id FROM {$wpdb->postmeta} m
          LEFT JOIN {$wpdb->vergeml_ai_index} i ON i.attachment_id = m.post_id
         WHERE m.meta_key = %s
           AND m.post_id > %d
           AND i.attachment_id IS NULL
         ORDER BY m.post_id ASC
         LIMIT %d",
        VERGEML_META_AI,
        (int) $after,
        $cap
    ) ) );
    // phpcs:enable
}


/**
 *  vergeml_index_migrate_step
 *
 *  One chunk of postmeta into the table. The old meta is left exactly where
 *  it is: this copies, and a site that rolls back to a previous version finds
 *  its descriptions still there.
 */

function vergeml_index_migrate_step( $cursor = 0 ) {

    $cursor = max( 0, (int) $cursor );
    $ids    = vergeml_index_pending_migration( $cursor, VERGEML_INDEX_BATCH );
    $moved  = 0;

    foreach ( $ids as $id ) {

        $legacy = get_post_meta( $id, VERGEML_META_AI, true );
        $cursor = max( $cursor, (int) $id );

        if ( ! is_array( $legacy ) ) {
            continue;
        }

        // A stub recording that this file could not be described. Worth
        // carrying over: it is why the file is not in the backlog.
        if ( isset( $legacy['error'] ) ) {
            vergeml_index_set( $id, array(
                'error'        => substr( (string) $legacy['error'], 0, 64 ),
                'described_at' => vergeml_index_stamp( $legacy ),
            ) );
            $moved++;
            continue;
        }

        vergeml_index_set( $id, array(
            'caption'      => isset( $legacy['caption'] ) ? (string) $legacy['caption'] : '',
            'alt'          => isset( $legacy['alt'] ) ? (string) $legacy['alt'] : '',
            'title'        => isset( $legacy['title'] ) ? (string) $legacy['title'] : '',
            'tags'         => isset( $legacy['tags'] ) ? $legacy['tags'] : array(),
            'orientation'  => vergeml_index_orientation( $id ),
            'model'        => isset( $legacy['model'] ) ? substr( (string) $legacy['model'], 0, 96 ) : '',
            'described_at' => vergeml_index_stamp( $legacy ),
        ) );

        $moved++;
    }

    $remaining = count( vergeml_index_pending_migration( $cursor ) );
    $done      = count( $ids ) < VERGEML_INDEX_BATCH && 0 === $remaining;

    $state = vergeml_index_state();

    $state['cursor']   = $done ? 0 : $cursor;
    $state['migrated'] = $done ? time() : 0;

    update_option( VERGEML_INDEX_OPTION, $state, false );

    return array(
        'done'      => $done,
        'moved'     => $moved,
        'remaining' => $remaining,
        'cursor'    => $done ? 0 : $cursor,
    );
}


/**
 *  The migration runs itself, a chunk per admin screen, and then never again.
 *
 *  No REST loop and no button, because unlike the scans this one is nobody's
 *  decision: the descriptions are already there, the table is where they now
 *  live, and a site with two hundred described files finishes it in two page
 *  loads without being told to. One bounded query is the cost on the loads
 *  after that, and the option makes even that stop once it is done.
 */

add_action( 'admin_init', 'vergeml_index_maybe_migrate' );

function vergeml_index_maybe_migrate() {

    if ( wp_doing_ajax() ) {
        return;
    }

    /*
     *  Before the early return below, deliberately. The schema check used to
     *  sit after it, which meant that a site which had finished the legacy
     *  migration -- every site that has run this feature for a while -- never
     *  checked its schema again. A later version could add a column or an
     *  index and those sites would silently never get it.
     */
    vergeml_index_maybe_install();

    $state = vergeml_index_state();

    if ( ! empty( $state['migrated'] ) ) {
        return;
    }

    vergeml_index_migrate_step( isset( $state['cursor'] ) ? (int) $state['cursor'] : 0 );
}


/**
 *  vergeml_index_maybe_install
 *
 *  The schema, laid down when it is missing or out of date.
 *
 *  Three cases, and the middle one is the one that used to be missed:
 *
 *   - never installed, because the activation hook never fired on a site that
 *     upgraded without visiting wp-admin;
 *   - installed, but at an older VERGEML_INDEX_VERSION than this code wants --
 *     which is how a new column or a new KEY reaches an existing install.
 *     Comparing against the constant is the whole point; testing only for
 *     "empty" made the constant decorative;
 *   - installed at the right version, but the table itself is gone: dropped by
 *     hand, by a host's migration, by a half-restored backup. The option still
 *     says the schema is current, so the database has to be asked.
 *
 *  The last of those costs a query, so it is only asked once the cheap checks
 *  have passed. core/librarian.php carries the same shape for its own tables.
 */

function vergeml_index_maybe_install() {

    $state = vergeml_index_state();

    if ( empty( $state['schema'] ) || VERGEML_INDEX_VERSION !== (int) $state['schema'] ) {
        vergeml_index_install();
        return;
    }

    if ( ! vergeml_index_table_exists() ) {
        vergeml_index_install();
    }
}


/**
 *  The old blob stored a unix timestamp under 'time'. The column wants a
 *  datetime, and a missing one is null rather than the epoch -- "described at
 *  midnight on the first of January 1970" is worse than "we do not know".
 */

function vergeml_index_stamp( $legacy ) {

    if ( empty( $legacy['time'] ) ) {
        return null;
    }

    return gmdate( 'Y-m-d H:i:s', (int) $legacy['time'] );
}
