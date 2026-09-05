<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  What a real media library looks like, if the owner says yes.
 *
 *  Every parameter decision this plugin has left -- how many files a batch
 *  should take, what a sensible default folder scheme is, what any of it is
 *  worth -- is a guess until somebody has seen a library that is not a test
 *  fixture. Eight numbers answer most of it.
 *
 *  Three rules, and they are the whole design:
 *
 *  - **Numbers only.** Counts, versions, a locale. No filename, no URL, no
 *    title, no term name, nothing a person wrote and nothing that identifies
 *    a site beyond the address every /v1 call already carries. Read the
 *    snapshot builder below: if it ever grows a string that came from the
 *    database, that is the bug -- and the service refuses the payload whole.
 *  - **Off until somebody says otherwise.** No default-on, no pre-ticked box.
 *    A switch under "Share library counts" in Library settings, with the
 *    three lines that say exactly what goes and what never does.
 *  - **Once a day, to one place.** The snapshot is written to an option and
 *    posted to the service with the licence key and site, the way every
 *    other /v1 call is made. No key, nothing is sent: the service could not
 *    accept it.
 *
 *  @since 3.2, sent since 3.16.2
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
        'sent'     => 0,
    ), $state );
}


function vergeml_stats_opted() {
    $state = vergeml_stats_state();
    return ! empty( $state['opted'] );
}


/**
 *  vergeml_stats_snapshot
 *
 *  The eight numbers, as they stand right now, and the three versions.
 *
 *  mime is a family count -- image, video, audio, application, other -- not a
 *  list of types, because "how many of these libraries are mostly video" is
 *  the question and "which exotic MIME does site 41 have one of" is not.
 *
 *  These keys are the contract with the service (app/api/counts): it accepts
 *  exactly this set and nothing more.
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
        'plugin'      => VERGEML_VERSION,
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
 *  Take a snapshot if one is due, and send it. Only ever called on an admin
 *  screen, and only while the switch is on: no cron entry, nothing running
 *  on a front-end request, and nothing at all happening on a site that never
 *  opted in.
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

    if ( vergeml_stats_send( $state['snapshot'] ) ) {
        $state['sent'] = time();
    }

    update_option( VERGEML_STATS_OPTION, $state, false );

    return $state;
}


/**
 *  vergeml_stats_send
 *
 *  The snapshot to the service, with the licence key and the site, as every
 *  /v1 call. Exactly what the builder returned and nothing beside it: the
 *  body is the snapshot under one key, not a merge anything could leak into.
 *  Without a key there is nothing to authenticate with and nothing is sent.
 *  True when the service took it.
 */

function vergeml_stats_send( $snapshot ) {

    if ( ! function_exists( 'vergeml_ai_settings' ) || ! function_exists( 'vergeml_ai_unseal' ) || ! function_exists( 'vergeml_ai_service_url' ) ) {
        return false;
    }

    $settings = vergeml_ai_settings();
    $key      = vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );

    if ( '' === $key ) {
        return false;
    }

    $response = wp_remote_post(
        vergeml_ai_service_url() . '/counts',
        array(
            'timeout'   => 8,
            'headers'   => array( 'Content-Type' => 'application/json' ),
            'sslverify' => true,
            'body'      => wp_json_encode( array(
                'license_key' => $key,
                'site'        => home_url(),
                'counts'      => $snapshot,
            ) ),
        )
    );

    return ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
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
        $state['sent']     = 0;
    }

    update_option( VERGEML_STATS_OPTION, $state, false );

    if ( $opted ) {
        $state = vergeml_stats_refresh( true );
    }

    return rest_ensure_response( array(
        'opted'    => ! empty( $state['opted'] ),
        'snapshot' => $state['snapshot'],
        'time'     => (int) $state['time'],
        'sent'     => (int) $state['sent'],
    ) );
}


/* ------------------------------------------------------------------- screen */

/**
 *  The switch, in Library settings. Called from the settings form in
 *  core/options-pages.php, where a site owner expects a question about what
 *  the site shares to be asked -- not on the dashboard, where it was a card
 *  that said "Size counts" and did nothing.
 *
 *  Three lines under the switch, and they are the whole disclosure: what
 *  goes, how often, and what never does.
 */

function vergeml_stats_settings_section() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $state = vergeml_stats_state();

    ?>
    <h2><?php esc_html_e( 'Share library counts', 'vergelabs-media-library' ); ?></h2>

    <div class="postbox">

        <div class="inside">

            <table class="form-table">

                <tr>
                    <th scope="row"><?php esc_html_e( 'Send the counts', 'vergelabs-media-library' ); ?></th>
                    <td>
                        <fieldset class="vgml-stats-share">
                            <legend class="screen-reader-text"><span><?php esc_html_e( 'Send the counts', 'vergelabs-media-library' ); ?></span></legend>
                            <label><input type="checkbox" id="vgml-stats-opt" <?php checked( ! empty( $state['opted'] ) ); ?>> <?php esc_html_e( 'Send the counts', 'vergelabs-media-library' ); ?></label>
                            <ul class="vgml-facts">
                                <li><?php esc_html_e( 'Once a day: files, folders, how deep they nest', 'vergelabs-media-library' ); ?></li>
                                <li><?php esc_html_e( 'Plugin, WordPress and PHP versions, and the site language', 'vergelabs-media-library' ); ?></li>
                                <li><?php esc_html_e( 'Never a file name, a title, a folder name or a picture', 'vergelabs-media-library' ); ?></li>
                            </ul>
                            <p class="description vgml-accent-text" id="vgml-stats-note"></p>
                        </fieldset>
                    </td>
                </tr>

            </table>

        </div>

    </div>
    <?php

    vergeml_stats_script();
}


/**
 *  The switch saves itself through REST the moment it is moved; the page's
 *  own Save button is for the options form and this is not one of them.
 *  Enqueued from the section rather than from a hook: it exists on one
 *  screen, and a script queued while the page renders is printed with the
 *  footer.
 */

function vergeml_stats_script() {

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
