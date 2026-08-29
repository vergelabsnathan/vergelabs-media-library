<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  Describing a library without keeping a tab open.
 *
 *  The on-screen run in js/vergeml-ai.js is a loop of REST calls, which makes
 *  it resumable but not unattended: close the browser and it stops until
 *  somebody clicks again. That is fine for a hundred images and useless for
 *  twenty thousand, which is exactly the library that most needs describing.
 *
 *  So the same step function runs on WP-Cron instead. Nothing new is stored
 *  anywhere: this is the identical vergeml_ai_index_step() the button calls,
 *  on a schedule rather than on a click, and it writes the same index rows.
 *
 *  It is deliberately NOT a queue on the service. A server-side queue would
 *  have to hold the customer's images at rest while it worked through them,
 *  and the retention position this product sells is that nothing is kept --
 *  an image is described in the request that carries it and is never written
 *  down. Moving the waiting to this side keeps that true.
 *
 *  Slower than the on-screen run and it says so on the screen: cron fires when
 *  somebody visits the site, so progress tracks traffic. A site with a real
 *  system cron runs it every minute regardless.
 */


/** The option is not autoloaded: it is read on cron and on one admin screen. */
const VERGEML_AI_RUN_OPTION = 'vergeml_ai_run';

/** The cron hook. Single events, re-scheduled by each tick, rather than a
 *  recurring schedule -- a finite job should not leave a repeating hook
 *  behind when it finishes. */
const VERGEML_AI_RUN_HOOK = 'vergeml_ai_run_tick';

/** Seconds of describing per tick. Under the 30s max_execution_time that
 *  shared hosting still ships, with room for the last call to finish. */
const VERGEML_AI_RUN_BUDGET = 20;

/** Seconds before the next tick is due. Cron cannot fire more often than the
 *  site is visited, so this is a floor rather than a promise. */
const VERGEML_AI_RUN_GAP = 30;

/** How many files one step describes before the budget is checked again.
 *  Small, because the budget can only be honoured between steps. */
const VERGEML_AI_RUN_CHUNK = 3;


/**
 *  vergeml_ai_run_state
 *
 *  Never returns false: a run that was never started and a run that finished
 *  are both "not running", and no caller should have to tell them apart.
 */
function vergeml_ai_run_state() {

    $state = get_option( VERGEML_AI_RUN_OPTION, array() );

    if ( ! is_array( $state ) ) {
        $state = array();
    }

    return wp_parse_args( $state, array(
        'active'     => false,
        'scope'      => 'unindexed',
        'apply_alt'  => false,
        'total'      => 0,
        'described'  => 0,
        'failed'     => 0,
        'remaining'  => 0,
        'started_at' => '',
        'updated_at' => '',
        'stopped'    => '',
    ) );
}


function vergeml_ai_run_save( $state ) {
    $state['updated_at'] = current_time( 'mysql', true );
    update_option( VERGEML_AI_RUN_OPTION, $state, false );
    return $state;
}


function vergeml_ai_run_schedule() {
    if ( ! wp_next_scheduled( VERGEML_AI_RUN_HOOK ) ) {
        wp_schedule_single_event( time() + VERGEML_AI_RUN_GAP, VERGEML_AI_RUN_HOOK );
    }
}


function vergeml_ai_run_unschedule() {
    $next = wp_next_scheduled( VERGEML_AI_RUN_HOOK );
    while ( false !== $next ) {
        wp_unschedule_event( $next, VERGEML_AI_RUN_HOOK );
        $next = wp_next_scheduled( VERGEML_AI_RUN_HOOK );
    }
}


/**
 *  vergeml_ai_run_start
 *
 *  @return array|WP_Error  the new state, or why it will not start.
 */
function vergeml_ai_run_start( $scope, $apply_alt ) {

    if ( ! in_array( $scope, array( 'unindexed', 'missing-alt', 'stale' ), true ) ) {
        return new WP_Error( 'vergeml_ai_bad_scope', __( 'Unknown scope.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    if ( ! vergeml_ai_ready() ) {
        return new WP_Error( 'vergeml_ai_unconfigured', __( 'Add a licence key, or switch on demo mode, before starting a background run.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $pending = count( vergeml_ai_pending( $scope ) );

    if ( 0 === $pending ) {
        return new WP_Error( 'vergeml_ai_nothing_to_do', __( 'There is nothing left to describe in that scope.', 'vergelabs-media-library' ), array( 'status' => 409 ) );
    }

    $state = vergeml_ai_run_save( array(
        'active'     => true,
        'scope'      => $scope,
        'apply_alt'  => (bool) $apply_alt,
        'total'      => $pending,
        'described'  => 0,
        'failed'     => 0,
        'remaining'  => $pending,
        'started_at' => current_time( 'mysql', true ),
        'stopped'    => '',
    ) );

    vergeml_ai_run_schedule();

    return $state;
}


/**
 *  vergeml_ai_run_stop
 *
 *  @param string $reason  what the screen should say. Empty means the person
 *                         stopped it, which needs no explaining.
 */
function vergeml_ai_run_stop( $reason = '' ) {

    vergeml_ai_run_unschedule();

    $state = vergeml_ai_run_state();

    $state['active']    = false;
    $state['stopped']   = (string) $reason;
    $state['remaining'] = count( vergeml_ai_pending( $state['scope'] ) );

    return vergeml_ai_run_save( $state );
}


/**
 *  vergeml_ai_run_tick
 *
 *  One cron pass. Describes for as long as the budget allows, writes down what
 *  happened, and books the next pass -- or stops, if there is nothing left or
 *  something happened that every further call would hit as well.
 */

add_action( VERGEML_AI_RUN_HOOK, 'vergeml_ai_run_tick' );

function vergeml_ai_run_tick() {

    $state = vergeml_ai_run_state();

    if ( empty( $state['active'] ) ) {
        return;
    }

    /*
     *  One pass at a time. Cron normally sees to this itself, but a busy site
     *  can spawn a second loopback before the first has finished, and two
     *  passes describing the same files would spend a customer's credits
     *  twice for one result.
     */
    if ( get_transient( 'vergeml_ai_run_lock' ) ) {
        return;
    }

    set_transient( 'vergeml_ai_run_lock', 1, 5 * MINUTE_IN_SECONDS );

    // Cron requests inherit the site's limit, which on shared hosting is
    // often 30 seconds. Ask for more; carry on without it if refused.
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    }

    $started = time();
    $stop    = '';

    do {
        $result = vergeml_ai_index_step( $state['scope'], VERGEML_AI_RUN_CHUNK, $state['apply_alt'] );

        $state['described'] += count( $result['described'] );
        $state['remaining']  = (int) $result['remaining'];

        foreach ( $result['errors'] as $error ) {

            $state['failed']++;

            /*
             *  A fatal is out of credits, a refused licence or no licence at
             *  all. Every remaining file would fail the same way, so the run
             *  ends and says why rather than grinding through the backlog
             *  writing error stubs over it.
             */
            if ( ! empty( $error['fatal'] ) ) {
                $stop = (string) $error['error'];
            }
        }

        if ( '' !== $stop ) {
            break;
        }

        // Nothing described and nothing failed means the step found no work
        // it could take; looping on that would spin.
        if ( empty( $result['described'] ) && empty( $result['errors'] ) ) {
            break;
        }

    } while ( $state['remaining'] > 0 && ( time() - $started ) < VERGEML_AI_RUN_BUDGET );

    delete_transient( 'vergeml_ai_run_lock' );

    if ( '' !== $stop ) {
        vergeml_ai_run_save( $state );
        vergeml_ai_run_stop( $stop );
        return;
    }

    if ( $state['remaining'] < 1 ) {
        vergeml_ai_run_save( $state );
        vergeml_ai_run_stop( '' );
        return;
    }

    vergeml_ai_run_save( $state );
    vergeml_ai_run_schedule();
}


/* ----------------------------------------------------------------- the API */

add_action( 'rest_api_init', 'vergeml_ai_run_routes' );

function vergeml_ai_run_routes() {

    register_rest_route( VERGEML_REST_NS, '/ai-run', array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'vergeml_ai_run_rest_status',
            'permission_callback' => function () {
                return current_user_can( 'manage_categories' );
            },
        ),
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'vergeml_ai_run_rest_write',
            // Same bar as /ai-index: this describes files and writes their meta.
            'permission_callback' => function () {
                return current_user_can( 'manage_categories' );
            },
            'args'                => array(
                'action'    => array( 'type' => 'string', 'default' => 'start' ),
                'scope'     => array( 'type' => 'string', 'default' => 'unindexed' ),
                'apply_alt' => array( 'type' => 'boolean', 'default' => false ),
            ),
        ),
    ) );
}


function vergeml_ai_run_payload() {

    $state = vergeml_ai_run_state();

    $next = wp_next_scheduled( VERGEML_AI_RUN_HOOK );

    return array(
        'active'    => (bool) $state['active'],
        'scope'     => $state['scope'],
        'total'     => (int) $state['total'],
        'described' => (int) $state['described'],
        'failed'    => (int) $state['failed'],
        'remaining' => (int) $state['remaining'],
        'stopped'   => (string) $state['stopped'],
        'started'   => (string) $state['started_at'],
        'updated'   => (string) $state['updated_at'],
        // The screen says "next pass is due" rather than "is running", because
        // cron fires on a visit and there is no honest way to promise a time.
        'next'      => false === $next ? null : max( 0, (int) $next - time() ),
        /*
         *  A site with DISABLE_WP_CRON and no system cron behind it will never
         *  fire the hook, and a progress bar that never moves is a worse
         *  answer than a sentence saying so.
         */
        'cron_off'  => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
    );
}


function vergeml_ai_run_rest_status() {
    return rest_ensure_response( vergeml_ai_run_payload() );
}


function vergeml_ai_run_rest_write( WP_REST_Request $request ) {

    if ( 'stop' === $request->get_param( 'action' ) ) {
        vergeml_ai_run_stop( '' );
        return rest_ensure_response( vergeml_ai_run_payload() );
    }

    $started = vergeml_ai_run_start(
        $request->get_param( 'scope' ),
        (bool) $request->get_param( 'apply_alt' )
    );

    if ( is_wp_error( $started ) ) {
        return $started;
    }

    return rest_ensure_response( vergeml_ai_run_payload() );
}


/* ---------------------------------------------------------------- the card */

add_action( 'vergeml_ai_page_cards', 'vergeml_ai_run_card' );

function vergeml_ai_run_card() {

    ?>
    <div class="vgml-ai-card">
        <h2><?php esc_html_e( 'Describe in the background', 'vergelabs-media-library' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'The same descriptions, without keeping this page open. The run continues on its own and picks up where it left off, so a library of twenty thousand does not need somebody watching it. It is slower than the button above, because it works whenever the site is visited rather than as fast as your browser can ask.', 'vergelabs-media-library' ); ?>
        </p>
        <p>
            <button type="button" class="button button-primary" id="vgml-ai-bg-start" data-scope="unindexed"><?php esc_html_e( 'Start in the background', 'vergelabs-media-library' ); ?></button>
            <button type="button" class="button" id="vgml-ai-bg-alt" data-scope="missing-alt"><?php esc_html_e( 'Alt text in the background', 'vergelabs-media-library' ); ?></button>
            <button type="button" class="button" id="vgml-ai-bg-stop" hidden><?php esc_html_e( 'Stop', 'vergelabs-media-library' ); ?></button>
        </p>
        <div class="vgml-import-bar" id="vgml-ai-bg-bar" hidden><div class="vgml-import-fill" id="vgml-ai-bg-fill"></div></div>
        <p id="vgml-ai-bg-note"></p>
    </div>
    <?php
}


add_action( 'admin_enqueue_scripts', 'vergeml_ai_run_assets' );

function vergeml_ai_run_assets( $hook ) {

    if ( false === strpos( (string) $hook, 'media-ai' ) ) {
        return;
    }

    wp_enqueue_script(
        'vergeml-ai-background',
        plugins_url( 'js/vergeml-ai-background.js', VERGEML_FILE ),
        array( 'wp-api-fetch' ),
        vergeml_asset_ver( 'js/vergeml-ai-background.js' ),
        true
    );

    wp_localize_script( 'vergeml-ai-background', 'vergemlAiRun', array(
        'idle'     => __( 'Not running.', 'vergelabs-media-library' ),
        /* translators: 1: images described so far, 2: images in the run. */
        'progress' => __( '%1$d of %2$d described', 'vergelabs-media-library' ),
        /* translators: %d: seconds until the next pass is due. */
        'next'     => __( 'next pass due in %ds', 'vergelabs-media-library' ),
        'due'      => __( 'next pass is due', 'vergelabs-media-library' ),
        /* translators: %d: number of files that could not be described. */
        'failed'   => __( '%d could not be described', 'vergelabs-media-library' ),
        'done'     => __( 'Finished.', 'vergelabs-media-library' ),
        'stopped'  => __( 'Stopped.', 'vergelabs-media-library' ),
        'cronOff'  => __( 'This site has WP-Cron disabled. A background run only moves if a real system cron calls wp-cron.php.', 'vergelabs-media-library' ),
        'starting' => __( 'Starting…', 'vergelabs-media-library' ),
    ) );
}
