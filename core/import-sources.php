<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Where folders can be imported from.
 *
 *  Every folder plugin stores the same two facts -- a tree of named folders, and
 *  which files are in which -- and differs only in where it puts them. So a
 *  source is nothing more than something that can answer those two questions,
 *  and the importer never learns which plugin it is talking to.
 *
 *  Most of them use a taxonomy, which means one reader covers them all: Premio
 *  Folders, HappyFiles, Wicked Folders, WP Media Folder and the rest differ by a
 *  single string. FileBird and Real Media Library keep custom tables and get a
 *  reader each. That split is FileBird's own -- their Controller/Import has one
 *  generic term importer and a class per custom-table rival -- and it is the
 *  right shape, so it is the shape used here.
 *
 *  Nothing here writes. A source is read-only by construction: the plugin being
 *  imported from keeps its data exactly as it was, so a bad import costs nothing
 *  and the user can go back to it.
 *
 *  @since 3.2
 */


/**
 *  vergeml_import_sources
 *
 *  Every source known, whether or not its plugin is installed. The ones with no
 *  data are filtered out later -- listing them anyway is what lets the screen say
 *  "nothing to import from FileBird" rather than silently omitting it, which
 *  reads as the importer not supporting it.
 */

function vergeml_import_sources() {

    $sources = array(

        /*
         *  Taxonomy-based. One reader, five plugins, and the only difference
         *  between them is this string.
         */
        'premio' => array(
            'name'     => 'Folders',
            'author'   => 'Premio',
            'kind'     => 'taxonomy',
            'taxonomy' => 'media_folder',
        ),
        'happyfiles' => array(
            'name'     => 'HappyFiles',
            'author'   => 'Codeer',
            'kind'     => 'taxonomy',
            'taxonomy' => 'happyfiles_category',
        ),
        'wicked' => array(
            'name'     => 'Wicked Folders',
            'author'   => 'Wicked Plugins',
            'kind'     => 'taxonomy',
            'taxonomy' => 'wf_attachment_folders',
        ),
        'wpmf' => array(
            'name'     => 'WP Media Folder',
            'author'   => 'JoomUnited',
            'kind'     => 'taxonomy',
            'taxonomy' => 'wpmf-category',
        ),
        'feml' => array(
            'name'     => 'WP Media Folders',
            'author'   => 'Damien Barrère',
            'kind'     => 'taxonomy',
            'taxonomy' => 'feml-folder',
        ),

        // Custom tables. A reader each.
        'filebird' => array(
            'name'   => 'FileBird',
            'author' => 'Ninja Team',
            'kind'   => 'filebird',
        ),
        'rml' => array(
            'name'   => 'Real Media Library',
            'author' => 'devowl.io',
            'kind'   => 'rml',
        ),
    );

    return apply_filters( 'vergeml_import_sources', $sources );
}


/**
 *  vergeml_import_read
 *
 *  Read a source into one shape, whatever it actually is.
 *
 *  Returns folders as `id => array( name, parent )` in the source's own ids, and
 *  files as `source folder id => array of attachment ids`. Everything downstream
 *  works on that and nothing downstream knows where it came from.
 */

function vergeml_import_read( $key ) {

    $sources = vergeml_import_sources();

    if ( ! isset( $sources[ $key ] ) ) {
        return new WP_Error( 'vergeml_unknown_source', __( 'That is not a folder plugin this can read.', 'vergelabs-media-library' ) );
    }

    $source = $sources[ $key ];

    switch ( $source['kind'] ) {
        case 'taxonomy':
            return vergeml_import_read_taxonomy( $source['taxonomy'] );
        case 'filebird':
            return vergeml_import_read_filebird();
        case 'rml':
            return vergeml_import_read_rml();
    }

    return new WP_Error( 'vergeml_unknown_source', __( 'That is not a folder plugin this can read.', 'vergelabs-media-library' ) );
}


/**
 *  vergeml_import_read_taxonomy
 *
 *  The generic reader, and the reason five plugins cost one function.
 *
 *  Read straight from the tables rather than through get_terms(), because the
 *  plugin that owns this taxonomy is usually deactivated by the time somebody
 *  imports from it -- and a taxonomy nobody registered is invisible to every
 *  taxonomy API in WordPress while its rows sit there untouched.
 */

function vergeml_import_read_taxonomy( $taxonomy ) {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reading a taxonomy whose plugin may be inactive; no core API can see it.
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT tt.term_id, tt.parent, t.name
               FROM {$wpdb->term_taxonomy} tt
               JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
              WHERE tt.taxonomy = %s",
            $taxonomy
        )
    );

    if ( empty( $rows ) ) {
        return array( 'folders' => array(), 'files' => array() );
    }

    $folders = array();

    foreach ( $rows as $row ) {
        $folders[ (int) $row->term_id ] = array(
            'name'   => $row->name,
            'parent' => (int) $row->parent,
        );
    }

    $links = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT tr.object_id, tt.term_id
               FROM {$wpdb->term_relationships} tr
               JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
               JOIN {$wpdb->posts} p ON p.ID = tr.object_id
              WHERE tt.taxonomy = %s
                AND p.post_type = 'attachment'",
            $taxonomy
        )
    );
    // phpcs:enable

    $files = array();

    foreach ( (array) $links as $link ) {
        $files[ (int) $link->term_id ][] = (int) $link->object_id;
    }

    return array( 'folders' => $folders, 'files' => $files );
}


/**
 *  vergeml_import_read_filebird
 *
 *  `wp_fbv` is id, name, parent, type, ord, created_by; `wp_fbv_attachment_folder`
 *  is attachment_id, folder_id.
 *
 *  `type` is theirs for separating media folders from post-type folders, so only
 *  type 0 is taken -- importing somebody's page folders into a media taxonomy
 *  would be worse than importing nothing. `ord` is read because we have somewhere
 *  to put it: vergeml_order term meta.
 */

function vergeml_import_read_filebird() {

    global $wpdb;

    $folders_table = $wpdb->prefix . 'fbv';
    $links_table   = $wpdb->prefix . 'fbv_attachment_folder';

    if ( ! vergeml_table_exists( $folders_table ) ) {
        return array( 'folders' => array(), 'files' => array() );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- another plugin's tables; the names are built from $wpdb->prefix and a literal.
    $rows = $wpdb->get_results( "SELECT id, name, parent, ord FROM {$folders_table} WHERE type = 0" );

    $folders = array();

    foreach ( (array) $rows as $row ) {
        $folders[ (int) $row->id ] = array(
            'name'   => $row->name,
            'parent' => (int) $row->parent,
            'order'  => isset( $row->ord ) ? (int) $row->ord : 0,
        );
    }

    $files = array();

    if ( vergeml_table_exists( $links_table ) ) {

        $links = $wpdb->get_results(
            "SELECT l.attachment_id, l.folder_id
               FROM {$links_table} l
               JOIN {$wpdb->posts} p ON p.ID = l.attachment_id
              WHERE p.post_type = 'attachment'"
        );

        foreach ( (array) $links as $link ) {
            $files[ (int) $link->folder_id ][] = (int) $link->attachment_id;
        }
    }
    // phpcs:enable

    return array( 'folders' => $folders, 'files' => $files );
}


/**
 *  vergeml_import_read_rml
 *
 *  Real Media Library keeps `realmedialibrary` (id, name, parent, ord) and
 *  `realmedialibrary_posts` (attachment, fid). Column names have moved between
 *  their major versions, so what is present is checked rather than assumed: a
 *  reader that guesses wrong here silently imports nothing and reports success.
 */

function vergeml_import_read_rml() {

    global $wpdb;

    $folders_table = $wpdb->prefix . 'realmedialibrary';
    $links_table   = $wpdb->prefix . 'realmedialibrary_posts';

    if ( ! vergeml_table_exists( $folders_table ) ) {
        return array( 'folders' => array(), 'files' => array() );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$folders_table}" );

    $name_col   = in_array( 'name', $columns, true ) ? 'name' : null;
    $parent_col = in_array( 'parent', $columns, true ) ? 'parent' : null;

    if ( ! $name_col || ! $parent_col ) {
        return array( 'folders' => array(), 'files' => array() );
    }

    $order_col = in_array( 'ord', $columns, true ) ? ', ord' : '';

    $rows = $wpdb->get_results( "SELECT id, {$name_col} AS name, {$parent_col} AS parent{$order_col} FROM {$folders_table}" );

    $folders = array();

    foreach ( (array) $rows as $row ) {
        $folders[ (int) $row->id ] = array(
            'name'   => $row->name,
            // RML uses -1 for the root; ours uses 0.
            'parent' => max( 0, (int) $row->parent ),
            'order'  => isset( $row->ord ) ? (int) $row->ord : 0,
        );
    }

    $files = array();

    if ( vergeml_table_exists( $links_table ) ) {

        $link_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$links_table}" );
        $att_col      = in_array( 'attachment', $link_columns, true ) ? 'attachment' : 'attachment_id';
        $fid_col      = in_array( 'fid', $link_columns, true ) ? 'fid' : 'folder_id';

        if ( in_array( $att_col, $link_columns, true ) && in_array( $fid_col, $link_columns, true ) ) {

            $links = $wpdb->get_results(
                "SELECT l.{$att_col} AS attachment, l.{$fid_col} AS fid
                   FROM {$links_table} l
                   JOIN {$wpdb->posts} p ON p.ID = l.{$att_col}
                  WHERE p.post_type = 'attachment'"
            );

            foreach ( (array) $links as $link ) {
                $files[ (int) $link->fid ][] = (int) $link->attachment;
            }
        }
    }
    // phpcs:enable

    return array( 'folders' => $folders, 'files' => $files );
}


/**
 *  vergeml_table_exists
 *
 *  SHOW TABLES rather than a SELECT that might succeed against something else,
 *  and no caching: a plugin can be installed between two page loads.
 */

function vergeml_table_exists( $table ) {

    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
}
