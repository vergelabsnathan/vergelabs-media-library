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
const VERGEML_AI_RUN_BUDGET = 50;

/** Seconds before the next tick is due. Cron cannot fire more often than the
 *  site is visited, so this is a floor rather than a promise. */
const VERGEML_AI_RUN_GAP = 5;

/** How many files one step describes before the budget is checked again.
 *
 *  Eight, which is one group in flight together -- so a chunk now costs about
 *  what a single file used to, and the budget is still only checked between
 *  chunks. Three was chosen when they went one at a time. */
const VERGEML_AI_RUN_CHUNK = 16;


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
        'reason'     => '',
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
    /*
     *  Due now, not in five seconds: spawn_cron() only spawns for an event
     *  that is already due, so a run whose first tick sat five seconds in the
     *  future was nudged too early and then waited for a visitor.
     */
    if ( ! wp_next_scheduled( VERGEML_AI_RUN_HOOK ) ) {
        wp_schedule_single_event( time(), VERGEML_AI_RUN_HOOK );
    }
}


/**
 *  Keep going without waiting for a visitor.
 *
 *  WP-Cron is not a clock. It fires when somebody loads a page, so on a site
 *  nobody is browsing -- a staging copy, a shop at four in the morning, a box
 *  a developer left open on another tab -- a run that says "you can close this
 *  tab" simply stops, and the screen goes on claiming it is working. That is
 *  the whole complaint: it does not finish, and it does not continue when you
 *  move to another page.
 *
 *  So the tick asks the site to run its own due events, in a request it does
 *  not wait for. The overlap lock in the tick is what makes this safe: a
 *  second one arriving early finds the lock and returns. Cron stays as the
 *  fallback for whenever a visitor does turn up.
 */
function vergeml_ai_run_nudge() {

    // Nothing to chase if the work is done or somebody stopped it.
    $state = vergeml_ai_run_state();
    if ( empty( $state['active'] ) ) {
        return;
    }

    /*
     *  wp-cron.php only works when the key in the request matches the
     *  'doing_cron' lock. Posting a fresh key without taking the lock, which
     *  is what this did until 3 September 2026, is refused on line one of
     *  the lock check: every nudge on the box came back in zero seconds and
     *  the run waited for whatever else happened to spawn cron. Outside a
     *  cron run, core's spawn_cron() takes the lock and posts; it declines
     *  while a run holds the lock, and that run drops it as it ends.
     */
    if ( ! defined( 'DOING_CRON' ) ) {
        spawn_cron();
        return;
    }

    /*
     *  Inside a tick spawn_cron() refuses outright, so the next request is
     *  chained the way core's own does it: take the lock with a new key and
     *  post that key. The finishing run only clears the lock when it still
     *  holds it, so the handover is clean.
     */
    $key = sprintf( '%.22F', microtime( true ) );
    set_transient( 'doing_cron', $key );

    wp_remote_post(
        add_query_arg( 'doing_wp_cron', $key, site_url( 'wp-cron.php' ) ),
        array(
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
            'headers'   => array( 'Cache-Control' => 'no-cache' ),
        )
    );
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
function vergeml_ai_run_start( $scope, $apply_alt, $reason = '' ) {

    if ( ! in_array( $scope, array( 'unindexed', 'missing-alt', 'page-gap', 'stale' ), true ) ) {
        return new WP_Error( 'vergeml_ai_bad_scope', __( 'Unknown scope.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    if ( ! vergeml_ai_ready() ) {
        return new WP_Error( 'vergeml_ai_unconfigured', __( 'Add a licence key, or switch on demo mode, before starting a background run.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $pending = vergeml_ai_pending_count( $scope );

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
        // Why it started, when it started itself, so the screen can say so.
        'reason'     => (string) $reason,
    ) );

    vergeml_ai_run_schedule();
    vergeml_ai_run_nudge();

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
    $state['remaining'] = vergeml_ai_pending_count( $state['scope'] );

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
        @set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- a long cron job on shared hosting; refused silently where disallowed.
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

    /*
     *  Recounted before deciding the run is over.
     *
     *  'remaining' came from the step, and the step has already dropped every
     *  id on the ten-minute hold -- a transient refusal, a duplicate. So the
     *  count reached zero with 96 pictures still on the old prompt, and the
     *  run declared itself finished. pending_count() does not apply the hold,
     *  so a run with held pictures stays active, reschedules, and collects
     *  them once the hold lapses -- which is what the hold was for.
     */
    $state['remaining'] = (int) vergeml_ai_pending_count( $state['scope'] );

    if ( $state['remaining'] < 1 ) {
        vergeml_ai_run_save( $state );
        vergeml_ai_run_stop( '' );
        vergeml_ai_run_sweep_stale( $state );
        return;
    }

    vergeml_ai_run_save( $state );

    /*
     *  Due now rather than in thirty seconds, and chased immediately: the gap
     *  was only ever politeness to shared hosting, and the batch size is what
     *  actually bounds the work per tick. A run that pauses for half a minute
     *  between batches and then waits indefinitely for a page load is not a
     *  background run, it is a stalled one.
     */
    if ( ! wp_next_scheduled( VERGEML_AI_RUN_HOOK ) ) {
        wp_schedule_single_event( time(), VERGEML_AI_RUN_HOOK );
    }
    vergeml_ai_run_nudge();
}


/**
 *  A run has just finished on the current prompt. Anything still filed under
 *  an older one is stale and, left alone, loses every search to the pictures
 *  already redone (the note above vergeml_ai_index_step's own trigger says
 *  why). That trigger cannot fire from inside a background run, because it
 *  waits for no run to be active and the run that just described under the
 *  new prompt still is. So a finishing run hands over here.
 */
function vergeml_ai_run_sweep_stale( $state ) {
    if ( 'stale' === $state['scope'] ) {
        return;
    }
    $stamp = vergeml_index_current_stamp();
    if ( '' === (string) $stamp['prompt_hash'] ) {
        return;
    }
    if ( vergeml_ai_pending_count( 'stale' ) < 1 ) {
        return;
    }
    vergeml_ai_run_start( 'stale', ! empty( $state['apply_alt'] ), 'prompt_changed' );
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

/*
 *  No card of its own.
 *
 *  This used to render "Describe in the background" directly under "Describe
 *  the library", with two buttons doing the same two jobs. One feature, shown
 *  twice, with nothing saying which to pick. The choice now lives inside the
 *  one describe section in core/ai.php, and the progress line and stop button
 *  there are these -- same ids, so the JS below is unchanged.
 */


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
