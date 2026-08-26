<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  What a real media library looks like, if the owner says yes.
 *
 *  Every parameter decision this plugin has left -- how many files a batch
 *  should take, what a sensible default folder scheme is, what any of it is
 *  worth -- is currently a guess, because nobody here has seen a library that
 *  is not a test fixture. Eight numbers answer most of it.
 *
 *  Three rules, and they are the whole design:
 *
 *  - **Numbers only.** Counts, versions, a locale. No filename, no URL, no
 *    title, no term name, nothing a person wrote and nothing that identifies
 *    a site. Read the snapshot builder below: if it ever grows a string that
 *    came from the database, that is the bug.
 *  - **Off until somebody says otherwise.** No default-on, no pre-ticked box,
 *    no "improve the plugin" checkbox buried in a settings tab. A card on the
 *    home screen that says exactly what is collected, and a switch.
 *  - **Local until there is somewhere to send it.** The snapshot is written to
 *    an option and stays there. There is no endpoint and no request in this
 *    file; transmission waits for a licensed connection that does not exist
 *    yet, which is also why the card does not promise one.
 *
 *  @since 3.2
 */


const VERGEML_STATS_OPTION = 'vergeml_stats';

// A library does not change shape hourly, and a snapshot is a handful of
// counting queries. Once a day is more resolution than the question needs.
const VERGEML_STATS_INTERVAL = DAY_IN_SECONDS;


function vergeml_stats_state() {

    $state = get_option( VERGEML_STATS_OPTION, array() );
    $state = is_array( $state ) ? $state : array();

    return array_merge( array(
        'opted'    => 0,
        'snapshot' => array(),
        'time'     => 0,
    ), $state );
}


function vergeml_stats_opted() {
    $state = vergeml_stats_state();
    return ! empty( $state['opted'] );
}


/**
 *  vergeml_stats_snapshot
 *
 *  The eight numbers, as they stand right now.
 *
 *  mime is a family count -- image, video, audio, application, other -- not a
 *  list of types, because "how many of these libraries are mostly video" is
 *  the question and "which exotic MIME does site 41 have one of" is not.
 */

function vergeml_stats_snapshot() {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- counting rows for a snapshot taken once a day.
    $attachments = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
    );

    $families = $wpdb->get_results(
        "SELECT SUBSTRING_INDEX( post_mime_type, '/', 1 ) AS family, COUNT(*) AS c
         FROM {$wpdb->posts}
         WHERE post_type = 'attachment' AND post_status = 'inherit'
         GROUP BY family"
    );

    $recent = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_date_gmt > %s",
        gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS )
    ) );
    // phpcs:enable

    $mimes = array();

    foreach ( (array) $families as $row ) {
        // sanitize_key, so a malformed mime in the database cannot put an
        // arbitrary string into the snapshot through the back door.
        $mimes[ sanitize_key( (string) $row->family ) ] = (int) $row->c;
    }

    $folders = 0;
    $depth   = 0;

    foreach ( vergeml_stats_taxonomies() as $taxonomy ) {

        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $terms ) ) {
            continue;
        }

        $folders += count( $terms );

        $parents = array();

        foreach ( $terms as $term ) {
            $parents[ (int) $term->term_id ] = (int) $term->parent;
        }

        foreach ( array_keys( $parents ) as $term_id ) {
            $depth = max( $depth, vergeml_stats_depth( $term_id, $parents ) );
        }
    }

    return array(
        'attachments' => $attachments,
        'mimes'       => $mimes,
        'folders'     => $folders,
        'depth'       => $depth,
        'recent'      => $recent,
        'wp'          => get_bloginfo( 'version' ),
        'php'         => PHP_VERSION,
        'locale'      => get_locale(),
    );
}


function vergeml_stats_taxonomies() {
    return function_exists( 'vergeml_tree_taxonomies' ) ? vergeml_tree_taxonomies() : array();
}


/**
 *  How deep one term sits. Walks up rather than down, and stops if the chain
 *  ever exceeds the number of terms there are -- a parent loop is a broken
 *  database, not a reason to hang the admin screen.
 */

function vergeml_stats_depth( $term_id, $parents ) {

    $depth = 1;
    $limit = count( $parents ) + 1;

    while ( ! empty( $parents[ $term_id ] ) && $depth < $limit ) {
        $term_id = $parents[ $term_id ];
        $depth++;
    }

    return $depth;
}


/**
 *  vergeml_stats_refresh
 *
 *  Take a snapshot if one is due. Only ever called on an admin screen, and
 *  only while the switch is on: no cron entry, nothing running on a front-end
 *  request, and nothing at all happening on a site that never opted in.
 */

function vergeml_stats_refresh( $force = false ) {

    $state = vergeml_stats_state();

    if ( empty( $state['opted'] ) ) {
        return $state;
    }

    if ( ! $force && ( time() - (int) $state['time'] ) < VERGEML_STATS_INTERVAL ) {
        return $state;
    }

    $state['snapshot'] = vergeml_stats_snapshot();
    $state['time']     = time();

    update_option( VERGEML_STATS_OPTION, $state, false );

    return $state;
}


add_action( 'admin_init', 'vergeml_stats_maybe_refresh' );

function vergeml_stats_maybe_refresh() {

    if ( wp_doing_ajax() || ! vergeml_stats_opted() ) {
        return;
    }

    vergeml_stats_refresh();
}


/* --------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_stats_routes' );

function vergeml_stats_routes() {

    register_rest_route( VERGEML_REST_NS, '/stats-opt', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_stats_rest_opt',
        // Deciding what a site shares about itself is the site owner's call,
        // not a folder manager's.
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
        'args'                => array(
            'opted' => array( 'type' => 'boolean', 'required' => true ),
        ),
    ) );
}


function vergeml_stats_rest_opt( WP_REST_Request $request ) {

    $opted = (bool) $request->get_param( 'opted' );
    $state = vergeml_stats_state();

    $state['opted'] = $opted ? 1 : 0;

    // Switching off forgets what was already collected. A stored snapshot
    // nobody consented to keep is the same thing as not having asked.
    if ( ! $opted ) {
        $state['snapshot'] = array();
        $state['time']     = 0;
    }

    update_option( VERGEML_STATS_OPTION, $state, false );

    if ( $opted ) {
        $state = vergeml_stats_refresh( true );
    }

    return rest_ensure_response( array(
        'opted'    => ! empty( $state['opted'] ),
        'snapshot' => $state['snapshot'],
        'time'     => (int) $state['time'],
    ) );
}


/* ------------------------------------------------------------------- screen */

add_action( 'vergeml_admin_home_cards', 'vergeml_stats_card' );

function vergeml_stats_card() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $state = vergeml_stats_state();

    ?>
    <div class="vgml-stats-card">

        <h2><?php esc_html_e( 'Help size the next features', 'vergelabs-media-library' ); ?></h2>

        <p class="description">
            <?php esc_html_e( 'This plugin is built by one person who has never seen your library. If you switch this on, it keeps a count of how big it is and how it is arranged, so the defaults get chosen for real libraries instead of test ones.', 'vergelabs-media-library' ); ?>
        </p>

        <p class="description">
            <strong><?php esc_html_e( 'Exactly this, and nothing else:', 'vergelabs-media-library' ); ?></strong>
            <?php esc_html_e( 'how many files you have, how many of them are images, video, audio or documents, how many folders there are and how deeply they nest, how many files were added in the last 30 days, your WordPress and PHP versions, and your site language.', 'vergelabs-media-library' ); ?>
        </p>

        <p class="description">
            <?php esc_html_e( 'No filenames, no titles, no folder names, no addresses, and nothing you or anyone else has written. The numbers are kept on this site — nothing is sent anywhere.', 'vergelabs-media-library' ); ?>
        </p>

        <p>
            <label>
                <input type="checkbox" id="vgml-stats-opt" <?php checked( ! empty( $state['opted'] ) ); ?>>
                <?php esc_html_e( 'Keep these numbers', 'vergelabs-media-library' ); ?>
            </label>
            <span id="vgml-stats-note" class="description"></span>
        </p>

    </div>
    <?php
}


/**
 *  The home screen does not load wp-api-fetch on its own account, and the card
 *  above is the only thing on it that talks to REST. The switch's behaviour
 *  rides along as inline script rather than a file: it is twenty lines that
 *  exist on exactly one screen.
 */

add_action( 'admin_enqueue_scripts', 'vergeml_stats_assets' );

function vergeml_stats_assets( $hook ) {

    if ( ! defined( 'VERGEML_MENU' ) || 'toplevel_page_' . VERGEML_MENU !== $hook ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    wp_enqueue_script( 'wp-api-fetch' );

    $l10n = wp_json_encode( array(
        'on'     => __( 'Saved. Thank you.', 'vergelabs-media-library' ),
        'off'    => __( 'Off, and what was collected has been deleted.', 'vergelabs-media-library' ),
        'failed' => __( 'That did not save.', 'vergelabs-media-library' ),
    ) );

    wp_add_inline_script( 'wp-api-fetch', '( function () {
	var l10n = ' . $l10n . ';
	var box = document.getElementById( "vgml-stats-opt" );
	var note = document.getElementById( "vgml-stats-note" );
	if ( ! box || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}
	box.addEventListener( "change", function () {
		box.disabled = true;
		window.wp.apiFetch( {
			path: "/vergeml/v1/stats-opt",
			method: "POST",
			data: { opted: box.checked }
		} ).then( function ( r ) {
			box.disabled = false;
			note.textContent = r.opted ? l10n.on : l10n.off;
		} ).catch( function () {
			box.disabled = false;
			box.checked = ! box.checked;
			note.textContent = l10n.failed;
		} );
	} );
} )();' );
}
