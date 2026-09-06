<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  The import screen, and the endpoints behind it.
 *
 *  Settings > Import Folders. Four things it has to do: say what it found, say
 *  what would happen, do it a chunk at a time so a big library does not time out,
 *  and offer to take it back.
 *
 *  @since 3.2
 */


add_action( 'admin_menu', 'vergeml_import_menu', 20 );

function vergeml_import_menu() {

    add_submenu_page(
        VERGEML_MENU,
        __( 'Import Folders', 'vergelabs-media-library' ),
        __( 'Import Folders', 'vergelabs-media-library' ),
        'manage_categories',
        'media-import-folders',
        'vergeml_import_screen'
    );
}


add_action( 'rest_api_init', 'vergeml_import_routes' );

function vergeml_import_routes() {

    register_rest_route( VERGEML_REST_NS, '/import', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_rest_import',
        'permission_callback' => 'vergeml_can_import',
        'args'                => array(
            'action'   => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            'source'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            'taxonomy' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
            'id'       => array( 'type' => 'string' ),
            'resume'   => array( 'type' => 'object' ),
            // The whole file, read in the browser and posted as text. No
            // upload handling, nothing written to disk, and nothing left in
            // wp-content if the person changes their mind.
            'text'     => array( 'type' => 'string' ),
        ),
    ) );
}


/**
 *  Importing writes folders and files wholesale, so it takes the capability that
 *  governs restructuring rather than the one that governs uploading.
 */

function vergeml_can_import() {
    return current_user_can( 'manage_categories' );
}


function vergeml_rest_import( WP_REST_Request $request ) {

    $action = $request->get_param( 'action' );

    if ( 'found' === $action ) {
        return rest_ensure_response( array(
            'sources' => vergeml_import_found(),
            // The line under the title comes back with the cards: an import
            // moves two of its four numbers while somebody is looking at them.
            'facts'   => vergeml_import_facts(),
        ) );
    }

    if ( 'history' === $action ) {
        return rest_ensure_response( array( 'history' => vergeml_import_history() ) );
    }

    if ( 'csv' === $action ) {

        $parsed = vergeml_csv_parse( (string) $request->get_param( 'text' ) );

        if ( is_wp_error( $parsed ) ) {
            return $parsed;
        }

        vergeml_csv_stash( $parsed );

        $files = 0;
        foreach ( $parsed['files'] as $ids ) {
            $files += count( $ids );
        }

        return rest_ensure_response( array(
            'folders'  => count( $parsed['folders'] ),
            'files'    => $files,
            'problems' => $parsed['problems'],
            'skipped'  => $parsed['problem_count'],
        ) );
    }

    if ( 'csv-clear' === $action ) {
        vergeml_csv_clear();
        return rest_ensure_response( array( 'cleared' => true ) );
    }

    if ( 'undo' === $action ) {
        // Chunked like the import, for the same reason: a big undo does not fit
        // in one request on a shared host. The caller hands the token back.
        $resume = $request->get_param( 'resume' );
        $done   = vergeml_import_undo_step( (string) $request->get_param( 'id' ), is_array( $resume ) ? $resume : null );
        return is_wp_error( $done ) ? $done : rest_ensure_response( $done );
    }

    $source   = (string) $request->get_param( 'source' );
    $taxonomy = (string) $request->get_param( 'taxonomy' );

    if ( 'plan' === $action ) {
        $plan = vergeml_import_plan( $source, $taxonomy );
        return is_wp_error( $plan ) ? $plan : rest_ensure_response( $plan );
    }

    if ( 'run' === $action ) {
        $resume = $request->get_param( 'resume' );
        $result = vergeml_import_run( $source, $taxonomy, is_array( $resume ) ? $resume : null );
        return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
    }

    return new WP_Error( 'vergeml_unknown_action', __( 'That is not something the importer does.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
}


/**
 *  vergeml_import_found
 *
 *  Every source, with what it holds on this site.
 *
 *  All of them, including the ones holding nothing. A source that is left out
 *  reads as a plugin the importer cannot handle, and the answer somebody came
 *  for is "FileBird, 14 folders" or "nothing in HappyFiles" -- not silence.
 *  Reading all seven is seven small queries on a screen nobody opens twice.
 *
 *  A source that has folders also carries the plan's own numbers, so the card
 *  can state what the button will do without a second request and without a
 *  preview step in front of it.
 */

function vergeml_import_found() {

    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';

    $out = array();

    foreach ( vergeml_import_sources() as $key => $source ) {

        $read    = vergeml_import_read( $key );
        $folders = is_wp_error( $read ) ? array() : $read['folders'];

        $files = 0;

        if ( ! is_wp_error( $read ) ) {
            foreach ( $read['files'] as $ids ) {
                $files += count( array_unique( $ids ) );
            }
        }

        $card = array(
            'key'     => $key,
            'name'    => $source['name'],
            'author'  => $source['author'],
            // A name that is an ordinary word needs its author beside it in a
            // sentence about folders; the rest carry theirs on the card alone.
            'qualify' => ! empty( $source['qualify'] ),
            'folders' => count( $folders ),
            'files'   => $files,
        );

        if ( $folders && '' !== $taxonomy ) {

            $plan = vergeml_import_plan( $key, $taxonomy );

            if ( ! is_wp_error( $plan ) ) {
                $card['plan'] = array(
                    'taxonomy'    => $taxonomy,
                    'label'       => vergeml_import_taxonomy_label( $taxonomy ),
                    'create'      => (int) $plan['create'],
                    'merge'       => (int) $plan['merge'],
                    'assignments' => (int) $plan['assignments'],
                    'files'       => (int) $plan['files'],
                );
            }
        }

        $out[] = $card;
    }

    return $out;
}


/** What the folders are called here, for the sentence that names where they land. */

function vergeml_import_taxonomy_label( $taxonomy ) {

    $object = get_taxonomy( $taxonomy );

    return $object instanceof WP_Taxonomy ? (string) $object->labels->name : (string) $taxonomy;
}


/**
 *  vergeml_import_facts
 *
 *  The line under the title, and the two numbers a card states once its import
 *  has finished. Composed here rather than in the browser so the first paint
 *  carries it and every refresh says it the same way.
 */

function vergeml_import_facts() {

    global $wpdb;

    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- two counts with no core API equivalent, on a screen opened once.
    $files    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );
    $pictures = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" );
    // phpcs:enable

    $folders = 0;
    $unfiled = 0;

    if ( '' !== $taxonomy ) {
        $terms   = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
        $folders = is_wp_error( $terms ) ? 0 : count( $terms );
        $unfiled = vergeml_count_unassigned( $taxonomy );
    }

    $parts = array(
        /* translators: %s: a number of files. */
        sprintf( _n( '%s file', '%s files', $files, 'vergelabs-media-library' ), number_format_i18n( $files ) ),
        /* translators: %s: a number of pictures. */
        sprintf( _n( '%s picture', '%s pictures', $pictures, 'vergelabs-media-library' ), number_format_i18n( $pictures ) ),
        /* translators: %s: a number of folders. */
        sprintf( _n( '%s folder', '%s folders', $folders, 'vergelabs-media-library' ), number_format_i18n( $folders ) ),
        /* translators: %s: a number of files. */
        sprintf( __( '%s in no folder', 'vergelabs-media-library' ), number_format_i18n( $unfiled ) ),
    );

    return array(
        'files'    => $files,
        'pictures' => $pictures,
        'folders'  => $folders,
        'filed'    => max( 0, $files - $unfiled ),
        'unfiled'  => $unfiled,
        'line'     => implode( ' · ', $parts ),
    );
}


function vergeml_import_history() {

    $log = get_option( VERGEML_IMPORT_LOG, array() );
    $log = is_array( $log ) ? $log : array();

    $sources = vergeml_import_sources();
    $out     = array();

    foreach ( array_reverse( $log ) as $entry ) {

        $key  = isset( $entry['source'] ) ? $entry['source'] : '';
        $when = isset( $entry['when'] ) ? (int) $entry['when'] : 0;

        /*
         *  The CSV source is registered only while a file is staged, so every
         *  row of the history read back "csv" -- the key, not a name -- once
         *  the file it came from had been imported and cleared.
         */
        $name = isset( $sources[ $key ]['name'] ) ? $sources[ $key ]['name'] : $key;

        if ( 'csv' === $key && ! isset( $sources[ $key ] ) ) {
            $name = __( 'A CSV file', 'vergelabs-media-library' );
        }

        $out[] = array(
            'id'          => isset( $entry['id'] ) ? $entry['id'] : '',
            'name'        => $name,
            'when'        => $when,
            // "4d" cannot tell five imports of the same file apart. A date can.
            'when_text'   => $when ? sprintf(
                /* translators: 1: a date, e.g. "2 September". 2: a time, e.g. "08:00". */
                __( '%1$s, %2$s', 'vergelabs-media-library' ),
                wp_date( 'j F', $when ),
                wp_date( (string) get_option( 'time_format', 'H:i' ), $when )
            ) : '',
            'folders'     => isset( $entry['created'] ) ? count( (array) $entry['created'] ) : 0,
            'assignments' => isset( $entry['added'] ) ? count( (array) $entry['added'] ) : 0,
        );
    }

    return $out;
}


function vergeml_import_screen() {

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }

    $taxonomies = array();

    foreach ( vergeml_tree_taxonomies() as $name ) {
        $object = get_taxonomy( $name );
        if ( $object instanceof WP_Taxonomy && $object->hierarchical ) {
            $taxonomies[] = array( 'name' => $name, 'label' => $object->labels->name );
        }
    }

    ?>
    <div class="wrap vgml-import">

        <?php
        echo vergeml_pg_head( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Import folders', 'vergelabs-media-library' )
        );
        ?>

        <?php
        /*
         *  The library's own numbers, not a description of the screen. What
         *  the other plugin is left holding, and that an import can be taken
         *  back, are said under the first section head where they apply.
         */
        ?>
        <p class="vgml-import-facts" id="vgml-import-facts"><?php echo esc_html( vergeml_import_facts()['line'] ); ?></p>

        <div id="vgml-import-app" data-taxonomies="<?php echo esc_attr( wp_json_encode( $taxonomies ) ); ?>">
            <p><span class="spinner is-active" style="float:none;margin:0 6px 0 0"></span><?php esc_html_e( 'Looking for folders to import…', 'vergelabs-media-library' ); ?></p>
        </div>

    </div>
    <?php
}


add_action( 'admin_enqueue_scripts', 'vergeml_import_assets' );

function vergeml_import_assets( $hook ) {

    /*
     *  The screen moved out of Settings into the plugin's own menu, so its hook
     *  suffix moved with it. Matched on the slug rather than on the whole hook,
     *  which is the part that does not change when the menu is rearranged again.
     */
    if ( 'media-import-folders' !== substr( (string) $hook, -20 ) ) {
        return;
    }

    wp_enqueue_script(
        'vergeml-import',
        plugins_url( 'js/vergeml-import.js', VERGEML_FILE ),
        array( 'wp-api-fetch' ),
        VERGEML_VERSION,
        true
    );

    wp_enqueue_style(
        'vergeml-tree',
        plugins_url( 'css/vergeml-tree.css', VERGEML_FILE ),
        array(),
        VERGEML_VERSION
    );

    wp_localize_script( 'vergeml-import', 'vergemlImport', array(
        'l10n' => array(
            /*
             *  The screen names the plugins it read and what each one holds
             *  here, so nothing on it is a description of the screen.
             */
            'plugins'     => __( 'From another plugin', 'vergelabs-media-library' ),
            'pluginsNote' => __( 'The other plugin keeps its folders. Every import can be undone here.', 'vergelabs-media-library' ),
            /* translators: 1: number of folders, 2: number of files. */
            'summary'     => __( '%1$s folders · %2$s files', 'vergelabs-media-library' ),
            /* translators: 1: folders created, 2: folders merged, 3: files filed, 4: where they land, e.g. Media Categories. */
            'plan'        => __( '%1$s new folders, %2$s merged into folders you already have, and %3$s files filed into %4$s.', 'vergelabs-media-library' ),
            /* translators: 1: folders created, 2: files filed, 3: where they land. Used when nothing merges. */
            'planPlain'   => __( '%1$s new folders and %2$s files filed into %3$s.', 'vergelabs-media-library' ),
            /* translators: %s: number of folders. */
            'importGo'    => __( 'Import %s folders', 'vergelabs-media-library' ),
            'importInto'  => __( 'Import into', 'vergelabs-media-library' ),

            /* translators: 1: files done, 2: files total. Said inside the button that started it. */
            'working'     => __( 'Importing · %1$s of %2$s files', 'vergelabs-media-library' ),
            /* translators: %s: number of folders made so far. */
            'madeSoFar'   => __( '%s folders made so far.', 'vergelabs-media-library' ),
            /* translators: 1: folders made, 2: files filed. */
            'doneLine'    => __( '%1$s folders made · %2$s files filed', 'vergelabs-media-library' ),
            /* translators: 1: folders on this site now, 2: files in no folder now. */
            'doneNow'     => __( '%1$s folders now, %2$s files in no folder.', 'vergelabs-media-library' ),
            'failed'      => __( 'That did not work, and nothing further was changed.', 'vergelabs-media-library' ),

            /*
             *  The sources with nothing here are one line rather than a card
             *  each, and the line ends with the fact somebody whose old plugin
             *  is switched off needs.
             */
            /* translators: %s: a list of plugin names. */
            'alsoRead'    => __( 'Also read, with no folders on this site: %s. A card appears for any of them that has folders here, whether or not the plugin is still switched on.', 'vergelabs-media-library' ),
            /* translators: 1: a plugin name, 2: its author. */
            'nameBy'      => __( '%1$s by %2$s', 'vergelabs-media-library' ),
            'listComma'   => __( ', ', 'vergelabs-media-library' ),
            'listAnd'     => __( ' and ', 'vergelabs-media-library' ),

            'spreadsheet' => __( 'From a spreadsheet', 'vergelabs-media-library' ),
            'fileTitle'   => __( 'A CSV file', 'vergelabs-media-library' ),
            'fileBy'      => __( 'One row per file', 'vergelabs-media-library' ),
            'fileWhat'    => __( 'One row per file: which folder it goes in, the file’s ID, and its name. Slashes make the levels — Clients/Acme/2024 is three folders deep. A row with no ID is an empty folder.', 'vergelabs-media-library' ),
            'noFile'      => __( 'No file chosen', 'vergelabs-media-library' ),
            'pickFile'    => __( 'Choose a CSV…', 'vergelabs-media-library' ),
            'reading'     => __( 'Reading the file…', 'vergelabs-media-library' ),
            /* translators: 1: number of folders, 2: number of files. */
            'staged'      => __( 'We read %1$s folders and %2$s files from that file. Nothing has changed yet — look at what it would do below, and it can be undone afterwards like anything else here.', 'vergelabs-media-library' ),
            /* translators: %s: number of rows skipped. */
            'stagedSkips' => __( '%s rows were skipped:', 'vergelabs-media-library' ),
            'discard'     => __( 'Discard this file', 'vergelabs-media-library' ),
            'unreadable'  => __( 'That file could not be read.', 'vergelabs-media-library' ),

            'export'      => __( 'Write out your folders', 'vergelabs-media-library' ),
            'exportWhatNote' => __( 'Your whole folder structure as a spreadsheet — change it there, and read it back.', 'vergelabs-media-library' ),
            'exportGo'    => __( 'Download CSV', 'vergelabs-media-library' ),

            'history'     => __( 'Imports you have made', 'vergelabs-media-library' ),
            /* translators: 1: folders, 2: files. */
            'historyLine' => __( '%1$s folders, %2$s files', 'vergelabs-media-library' ),
            'historyNote' => __( 'An undo removes the folders that import made and the filing it did. A folder you already had stays, and a file you filed yourself afterwards stays where you put it.', 'vergelabs-media-library' ),
            /* translators: %s: number of folders the undo removes. */
            'undo'        => __( 'Undo · %s folders', 'vergelabs-media-library' ),
            'undoing'     => __( 'Undoing…', 'vergelabs-media-library' ),
            /* translators: 1: folders removed, 2: files put back. */
            'undone'      => __( '%1$s folders removed · %2$s files back where they were', 'vergelabs-media-library' ),
        ),
        'exportUrl' => wp_nonce_url( admin_url( 'admin-post.php?action=vergeml_export_csv' ), 'vergeml_export_csv' ),
    ) );
}
