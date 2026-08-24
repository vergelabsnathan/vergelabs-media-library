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
        'options-general.php',
        __( 'Import Folders', 'vergelabs-media-library' ) . ' &lsaquo; ' . __( 'Media Settings', 'vergelabs-media-library' ),
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
        return rest_ensure_response( array( 'sources' => vergeml_import_found() ) );
    }

    if ( 'history' === $action ) {
        return rest_ensure_response( array( 'history' => vergeml_import_history() ) );
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
 *  Every source that actually has something in it.
 *
 *  Reading all seven means seven small queries on a screen nobody opens twice, and
 *  it is the difference between "we support FileBird" and "FileBird: 200 folders,
 *  16,000 files" -- which is what tells somebody the importer is looking at their
 *  real data rather than at a list of names.
 */

function vergeml_import_found() {

    $out = array();

    foreach ( vergeml_import_sources() as $key => $source ) {

        $read = vergeml_import_read( $key );

        if ( is_wp_error( $read ) || empty( $read['folders'] ) ) {
            continue;
        }

        $files = 0;
        foreach ( $read['files'] as $ids ) {
            $files += count( array_unique( $ids ) );
        }

        $out[] = array(
            'key'     => $key,
            'name'    => $source['name'],
            'author'  => $source['author'],
            'folders' => count( $read['folders'] ),
            'files'   => $files,
        );
    }

    return $out;
}


function vergeml_import_history() {

    $log = get_option( VERGEML_IMPORT_LOG, array() );
    $log = is_array( $log ) ? $log : array();

    $sources = vergeml_import_sources();
    $out     = array();

    foreach ( array_reverse( $log ) as $entry ) {

        $key = isset( $entry['source'] ) ? $entry['source'] : '';

        $out[] = array(
            'id'          => isset( $entry['id'] ) ? $entry['id'] : '',
            'name'        => isset( $sources[ $key ]['name'] ) ? $sources[ $key ]['name'] : $key,
            'when'        => isset( $entry['when'] ) ? (int) $entry['when'] : 0,
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

        <h1><?php esc_html_e( 'Import Folders', 'vergelabs-media-library' ); ?></h1>

        <p class="description" style="max-width:46em">
            <?php esc_html_e( 'Folders from another media plugin become folders here. Nothing is taken from the other plugin — it keeps everything exactly as it is, so you can go back at any time, and an import can be undone from this page.', 'vergelabs-media-library' ); ?>
        </p>

        <div id="vgml-import-app" data-taxonomies="<?php echo esc_attr( wp_json_encode( $taxonomies ) ); ?>">
            <p><span class="spinner is-active" style="float:none;margin:0 6px 0 0"></span><?php esc_html_e( 'Looking for folders to import…', 'vergelabs-media-library' ); ?></p>
        </div>

    </div>
    <?php
}


add_action( 'admin_enqueue_scripts', 'vergeml_import_assets' );

function vergeml_import_assets( $hook ) {

    if ( 'settings_page_media-import-folders' !== $hook ) {
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
            'none'        => __( 'No other folder plugin has anything to import. If you have just installed one, add your folders there first.', 'vergelabs-media-library' ),
            'found'       => __( 'Found', 'vergelabs-media-library' ),
            /* translators: 1: number of folders, 2: number of files. */
            'summary'     => __( '%1$s folders, %2$s files', 'vergelabs-media-library' ),
            'importInto'  => __( 'Import into', 'vergelabs-media-library' ),
            'preview'     => __( 'Preview import', 'vergelabs-media-library' ),
            'importNow'   => __( 'Import', 'vergelabs-media-library' ),
            'cancel'      => __( 'Cancel', 'vergelabs-media-library' ),
            /* translators: 1: folders created, 2: folders merged, 3: files filed. */
            'plan'        => __( '%1$s new folders, %2$s merged into folders you already have, and %3$s files filed.', 'vergelabs-media-library' ),
            /* translators: 1: folders created, 2: files filed. Used when nothing merges. */
            'planPlain'   => __( '%1$s new folders and %2$s files filed.', 'vergelabs-media-library' ),
            'mergeNote'   => __( 'A folder with the same name in the same place is treated as the same folder, so nothing is duplicated.', 'vergelabs-media-library' ),
            'working'     => __( 'Importing…', 'vergelabs-media-library' ),
            /* translators: 1: files done, 2: files total. */
            'progress'    => __( '%1$s of %2$s files', 'vergelabs-media-library' ),
            'done'        => __( 'Imported.', 'vergelabs-media-library' ),
            'failed'      => __( 'That did not work, and nothing further was changed.', 'vergelabs-media-library' ),
            'history'     => __( 'Recent imports', 'vergelabs-media-library' ),
            'undo'        => __( 'Undo this import', 'vergelabs-media-library' ),
            'undoing'     => __( 'Undoing…', 'vergelabs-media-library' ),
            'undone'      => __( 'Undone. The folders it made are gone; anything you filed yourself is untouched.', 'vergelabs-media-library' ),
            /* translators: 1: folders, 2: files, 3: how long ago. */
            'historyLine' => __( '%1$s folders and %2$s files, %3$s ago', 'vergelabs-media-library' ),
            'stillThere'  => __( 'Your folders in the other plugin are untouched.', 'vergelabs-media-library' ),
        ),
    ) );
}
