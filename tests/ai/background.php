<?php
/**
 *  Describing without a tab open.
 *
 *  The claim this suite exists to check is that a run survives the browser
 *  closing: state on the option, work on cron, and a stop that actually
 *  stops. So it never touches the screen -- it calls the tick directly,
 *  which is what WP-Cron would have called.
 *
 *      wp eval-file tests/ai/background.php --allow-root
 *
 *  Runs in demo mode throughout, so it describes nothing real, spends no
 *  credits and needs no licence. The settings it changes are put back.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_ai_run_tick' ) ) {
    echo "core/ai-background.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

global $wpdb;

/*
 *  $GLOBALS, not `global`. wp eval-file evaluates this file inside a
 *  function, so counters declared at the top of it are locals of that
 *  function and `global` binds to a second, empty pair -- the summary reads
 *  "0/0 passed" and the exit(1) can never fire.
 */
$GLOBALS['bg_pass'] = 0;
$GLOBALS['bg_fail'] = 0;
$GLOBALS['bg_log']  = '';

function bg_say( $line ) {
    $GLOBALS['bg_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function bg_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['bg_pass']++;
    } else {
        $GLOBALS['bg_fail']++;
    }
    bg_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

function bg_settings( $changes ) {
    $saved = get_option( 'vergeml_ai', array() );
    update_option( 'vergeml_ai', array_merge( is_array( $saved ) ? $saved : array(), $changes ), false );
}

/** Seeds attachments the mock can describe: it invents from the filename,
 *  so no bytes need to exist on disk. */
function bg_seed( $n, $tag ) {

    $made = array();

    for ( $i = 0; $i < $n; $i++ ) {

        $name = 'zzbg-' . $tag . '-' . $i;

        $id = wp_insert_post( array(
            'post_title'     => $name,
            'post_name'      => $name,
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'image/jpeg',
            'guid'           => 'http://example.test/' . $name . '.jpg',
        ) );

        if ( $id && ! is_wp_error( $id ) ) {
            update_post_meta( $id, '_wp_attached_file', $name . '.jpg' );
            $made[] = (int) $id;
        }
    }

    return $made;
}


bg_say( "\ndescribing in the background\n\n" );

$bg_before_settings = get_option( 'vergeml_ai', array() );
$bg_made            = array();

// Demo mode throughout: nothing is sent anywhere and nothing is charged.
bg_settings( array( 'mock' => 1 ) );

// Start from a clean slate even if a previous run of this suite was
// interrupted, or the option would carry its counters into the assertions.
vergeml_ai_run_stop( '' );
delete_option( 'vergeml_ai_run' );
delete_transient( 'vergeml_ai_run_lock' );


bg_say( "A  refusals\n" );

$bg_bad = vergeml_ai_run_start( 'not-a-scope', false );
bg_check( 'an unknown scope is refused', is_wp_error( $bg_bad ), is_wp_error( $bg_bad ) ? $bg_bad->get_error_code() : 'started anyway' );

// Not ready means no licence and no demo mode. Every file would fail the
// same way, so the run must not start at all.
bg_settings( array( 'mock' => 0, 'license_key' => '' ) );
$bg_unready = vergeml_ai_run_start( 'unindexed', false );
bg_check( 'an unconfigured site cannot start a run', is_wp_error( $bg_unready ), is_wp_error( $bg_unready ) ? $bg_unready->get_error_code() : 'started anyway' );
bg_settings( array( 'mock' => 1 ) );

/*
 *  Refusing an empty backlog, asserted only when the backlog is actually
 *  empty.
 *
 *  This first read `assert it refuses`, full stop, and passed alone and failed
 *  in the battery: tests/tree/ai-folders.php runs before it and leaves images
 *  without index rows, so there WAS a backlog and starting was the correct
 *  answer. The suite was asserting the state of the library rather than the
 *  behaviour of the code -- the debris trap in docs/testing.md, from the
 *  inside.
 *
 *  So it now asks the library first and holds the code to whichever answer is
 *  right. Both branches are real checks; neither is a shrug.
 */
$bg_backlog = count( vergeml_ai_pending( 'unindexed' ) );
$bg_nothing = vergeml_ai_run_start( 'unindexed', false );

if ( 0 === $bg_backlog ) {
    bg_check(
        'with an empty backlog there is nothing to start',
        is_wp_error( $bg_nothing ) && 'vergeml_ai_nothing_to_do' === $bg_nothing->get_error_code(),
        is_wp_error( $bg_nothing ) ? $bg_nothing->get_error_code() : 'started with nothing to do'
    );
} else {
    bg_check(
        'with files still to describe, it starts',
        ! is_wp_error( $bg_nothing ) && ! empty( $bg_nothing['active'] ),
        $bg_backlog . ' waiting -- another suite left them, which is fine'
    );

    // Put it back, or section B starts against a run that is already going.
    vergeml_ai_run_stop( '' );
    delete_option( 'vergeml_ai_run' );
}


bg_say( "\nB  a run starts, and is written down rather than held in a page\n" );

$bg_pending_before = count( vergeml_ai_pending( 'unindexed' ) );
$bg_made           = bg_seed( 7, 'a' );

bg_check( 'seven files seeded', 7 === count( $bg_made ), count( $bg_made ) . ' made' );

$bg_state = vergeml_ai_run_start( 'unindexed', false );

bg_check( 'the run started', ! is_wp_error( $bg_state ), is_wp_error( $bg_state ) ? $bg_state->get_error_message() : '' );
bg_check( 'it is marked active', ! is_wp_error( $bg_state ) && ! empty( $bg_state['active'] ) );
bg_check(
    'it counted the whole backlog up front',
    ! is_wp_error( $bg_state ) && (int) $bg_state['total'] === $bg_pending_before + 7,
    is_wp_error( $bg_state ) ? '-' : $bg_state['total'] . ' vs ' . ( $bg_pending_before + 7 )
);

// The claim under test: the work is booked with cron, so no browser is
// involved in it continuing.
bg_check( 'a cron pass is booked', false !== wp_next_scheduled( 'vergeml_ai_run_tick' ) );

// And it survives the page: state lives on an option, not in a request.
$bg_reread = vergeml_ai_run_state();
bg_check( 'the state is readable from a fresh look at the option', ! empty( $bg_reread['active'] ) && 7 <= (int) $bg_reread['total'] );


bg_say( "\nC  the lock stops two passes describing the same files twice\n" );

/*
 *  Checked before any work happens, not after.
 *
 *  It was written the other way round first -- describe, then hold the lock
 *  and describe again -- and the mock is fast enough that the whole backlog
 *  went in the first pass, so the second had nothing left to describe and the
 *  assertion passed without the lock ever being involved. A check that cannot
 *  fail is worse than no check, because it reads as cover.
 */
set_transient( 'vergeml_ai_run_lock', 1, 5 * MINUTE_IN_SECONDS );

vergeml_ai_run_tick();
$bg_locked = vergeml_ai_run_state();

bg_check( 'a pass that finds the lock held describes nothing', 0 === (int) $bg_locked['described'], $bg_locked['described'] . ' described' );
bg_check( 'and it leaves the run active rather than ending it', ! empty( $bg_locked['active'] ) );
bg_check( 'and the backlog is untouched', (int) $bg_locked['remaining'] === (int) $bg_locked['total'], $bg_locked['remaining'] . ' of ' . $bg_locked['total'] );

delete_transient( 'vergeml_ai_run_lock' );

vergeml_ai_run_tick();
$bg_after_one = vergeml_ai_run_state();

bg_check( 'with the lock free, the pass describes', (int) $bg_after_one['described'] > 0, $bg_after_one['described'] . ' described' );
bg_check(
    'and the backlog fell by exactly what it described',
    (int) $bg_after_one['remaining'] === (int) $bg_after_one['total'] - (int) $bg_after_one['described'] - (int) $bg_after_one['failed'],
    $bg_after_one['remaining'] . ' left of ' . $bg_after_one['total']
);


bg_say( "\nD  it finishes by itself and puts the schedule away\n" );

$bg_rounds = 0;

while ( $bg_rounds < 30 ) {

    $bg_now = vergeml_ai_run_state();

    if ( empty( $bg_now['active'] ) ) {
        break;
    }

    vergeml_ai_run_tick();
    $bg_rounds++;
}

$bg_end = vergeml_ai_run_state();

bg_check( 'the run ended without being told to', empty( $bg_end['active'] ), $bg_rounds . ' passes' );
bg_check( 'it described the whole backlog', (int) $bg_end['described'] + (int) $bg_end['failed'] >= (int) $bg_end['total'], $bg_end['described'] . '+' . $bg_end['failed'] . ' of ' . $bg_end['total'] );
bg_check( 'nothing is left pending', 0 === (int) $bg_end['remaining'], $bg_end['remaining'] . ' left' );
bg_check( 'it did not stop for a reason -- it simply finished', '' === (string) $bg_end['stopped'], $bg_end['stopped'] );

// A finite job must not leave a repeating hook behind.
bg_check( 'no cron pass is left booked', false === wp_next_scheduled( 'vergeml_ai_run_tick' ) );

$bg_described = 0;

foreach ( $bg_made as $bg_id ) {
    if ( vergeml_index_get( $bg_id ) ) {
        $bg_described++;
    }
}

bg_check( 'every seeded file has an index row', 7 === $bg_described, $bg_described . ' of 7' );


bg_say( "\nE  stopping actually stops\n" );

$bg_made_b = bg_seed( 5, 'b' );
$bg_made   = array_merge( $bg_made, $bg_made_b );

vergeml_ai_run_start( 'unindexed', false );
bg_check( 'a second run started', false !== wp_next_scheduled( 'vergeml_ai_run_tick' ) );

vergeml_ai_run_stop( '' );
$bg_stopped = vergeml_ai_run_state();

bg_check( 'it is no longer active', empty( $bg_stopped['active'] ) );
bg_check( 'and the booked pass is gone', false === wp_next_scheduled( 'vergeml_ai_run_tick' ) );

// A pass that fires anyway -- cron already had it queued -- must do nothing.
$bg_before_stray = vergeml_ai_run_state();
vergeml_ai_run_tick();
$bg_after_stray = vergeml_ai_run_state();
bg_check(
    'a stray pass after stopping describes nothing',
    (int) $bg_after_stray['described'] === (int) $bg_before_stray['described'],
    $bg_after_stray['described'] . ' vs ' . $bg_before_stray['described']
);


bg_say( "\nF  a refused licence ends the run instead of stubbing the library\n" );

vergeml_ai_run_start( 'unindexed', false );

/*
 *  Demo mode off and no key: every remaining file now fails with a fatal
 *  code. The run must end and say so -- grinding on would write an error
 *  stub over every file left in the backlog.
 */
bg_settings( array( 'mock' => 0, 'license_key' => '' ) );

vergeml_ai_run_tick();

$bg_fatal = vergeml_ai_run_state();

bg_check( 'the run stopped', empty( $bg_fatal['active'] ) );
bg_check( 'and it recorded why', '' !== (string) $bg_fatal['stopped'], $bg_fatal['stopped'] );
bg_check( 'no cron pass is left booked', false === wp_next_scheduled( 'vergeml_ai_run_tick' ) );

$bg_unstubbed = 0;

foreach ( $bg_made_b as $bg_id ) {
    if ( ! vergeml_index_get( $bg_id ) ) {
        $bg_unstubbed++;
    }
}

bg_check(
    'the rest of the backlog was left alone, not stubbed',
    $bg_unstubbed > 0,
    $bg_unstubbed . ' of ' . count( $bg_made_b ) . ' untouched'
);


bg_say( "\nG  a pass that died half-way is picked up again\n" );

/*
 *  What php-fpm leaves behind when it kills a pass: cron had already taken
 *  the event off the schedule, the run is still active, and the lock is
 *  whatever is left of the pass's last heartbeat. Nothing books the next
 *  pass, because the pass that would have is dead.
 *
 *  Core's own cron lock is held for the section, so starting the run does
 *  not spawn a real pass that finishes the three files before the checks.
 */
bg_settings( array( 'mock' => 1 ) );
set_transient( 'doing_cron', sprintf( '%.22F', microtime( true ) ) );

$bg_made_c = bg_seed( 3, 'c' );
$bg_made   = array_merge( $bg_made, $bg_made_c );

vergeml_ai_run_start( 'unindexed', false );
wp_unschedule_event( wp_next_scheduled( 'vergeml_ai_run_tick' ), 'vergeml_ai_run_tick' );
set_transient( 'vergeml_ai_run_lock', time(), 2 * MINUTE_IN_SECONDS );

$bg_warm = vergeml_ai_run_revive();
bg_check( 'while the lock is still warm nothing is re-booked -- a living pass books its own', false === $bg_warm && false === wp_next_scheduled( 'vergeml_ai_run_tick' ) );

// The heartbeat lapsed: the pass is dead.
delete_transient( 'vergeml_ai_run_lock' );

$bg_revived = vergeml_ai_run_revive();
bg_check( 'once it lapses, the run is booked again', true === $bg_revived && false !== wp_next_scheduled( 'vergeml_ai_run_tick' ) );

// The status the screen polls does the same, so a watched run recovers in seconds.
wp_unschedule_event( wp_next_scheduled( 'vergeml_ai_run_tick' ), 'vergeml_ai_run_tick' );
$bg_polled = vergeml_ai_run_payload();
bg_check( 'the status the screen polls books it too', false !== wp_next_scheduled( 'vergeml_ai_run_tick' ) && null !== $bg_polled['next'] );

vergeml_ai_run_stop( '' );
bg_check( 'a stopped run stays stopped', false === vergeml_ai_run_revive() && false === wp_next_scheduled( 'vergeml_ai_run_tick' ) );

delete_transient( 'doing_cron' );


/*
 *  G  the sweep gate (S11, S12). Nothing re-describes a library by itself:
 *  the only way is the dashboard's button with its credit count.
 *
 *  G1 (S11): a model change describes nothing. The library is stale on the
 *  prompt hash alone (core/ai.php, vergeml_ai_pending 'stale'), never on the
 *  model, so a describer switched in the service -- or one picture escalated
 *  to a stronger model -- leaves every row where it is. Planted: two rows on
 *  another model and version, the newest in the library, on the current
 *  prompt. Mutation: judge the stale set on the model too (pass the stamp's
 *  model to vergeml_index_stale in vergeml_ai_pending) -> G1 red (the whole
 *  library pending, a run started).
 *
 *  G2, G3 (S12, Nathan 2026-09-17): a prompt change describes nothing either.
 *  Until S12 a run's end, and a describe step that landed under a new hash,
 *  started a 'stale' run over the whole library on their own (reason
 *  'prompt_changed') -- every customer's credits, no press. Now the count
 *  sits on the button and waits. Planted: the newest row on another prompt
 *  hash, so every real row is stale. G2 drives a run to its end; G3 drives
 *  one describe step with the hash moving underneath it (the row planted
 *  from the alt-text write, which sits between the step's two stamp reads).
 *  Mutations: the run's-end sweep back (core/ai-background.php) -> G2 red;
 *  the step's auto-start back (core/ai.php) -> G3 red.
 */
bg_say( "\nG  neither a model change nor a prompt change sweeps by itself\n" );

/*
 *  The nudge a started run posts to wp-cron.php is answered here and never
 *  sent: under the mutations this section exists for, the sweep starts a run
 *  over the whole library, and on 2026-09-17 one tick got in before the stop
 *  below and wrote a mock row over a real picture's description. A suite
 *  that can start a run holds the wire -- twice: the nudge is declined, and
 *  whatever core's spawn_cron() still posts is answered here.
 */
function bg_no_cron( $pre, $args, $url ) {
    return false !== strpos( (string) $url, 'wp-cron.php' ) ? array( 'response' => array( 'code' => 200 ), 'body' => '', 'headers' => array() ) : $pre;
}
add_filter( 'pre_http_request', 'bg_no_cron', 1, 3 );
add_filter( 'vergeml_ai_run_should_nudge', '__return_false' );

/** A row on another model, and optionally another prompt, newer than every
 *  real row -- so the stamp reads it. Never 'mock': the stamp skips those. */
function bg_plant_newest( $id, $prompt_hash, $ahead ) {
    vergeml_index_set( (int) $id, array(
        'caption'       => 'seeded on another model',
        'kind'          => 'photo',
        'filing'        => wp_json_encode( array( 'object' => 'zzbgthing', 'audience' => '' ) ),
        'embedding'     => array( 1.0, 0.0, 0.0, 0.0 ),
        'model'         => 'zz-other-model',
        'model_version' => 'zz-other-v9',
        'prompt_hash'   => (string) $prompt_hash,
        'error'         => '',
        'described_at'  => gmdate( 'Y-m-d H:i:s', time() + (int) $ahead ),
    ) );
}

/** Seeds files, runs a whole run over them and reads what it left behind:
 *  the state and the booking the moment it ended, then stops everything
 *  before a stray tick could find it. */
function bg_run_to_end( &$made, $tag ) {
    $seeded = bg_seed( 2, $tag );
    $made   = array_merge( $made, $seeded );
    vergeml_ai_run_stop( '' );
    delete_option( 'vergeml_ai_run' );
    wp_clear_scheduled_hook( 'vergeml_ai_run_tick' );
    $started = vergeml_ai_run_start( 'unindexed', false );
    $rounds  = 0;
    // Only the run this started is ticked. A 'stale' run the end of it
    // starts on its own (the defect under test) is left where it is, or a
    // tick would mock over the real library -- the 2026-09-17 accident.
    while ( $rounds < 30 && ! is_wp_error( $started ) ) {
        $now = vergeml_ai_run_state();
        if ( empty( $now['active'] ) || 'unindexed' !== (string) $now['scope'] ) {
            break;
        }
        vergeml_ai_run_tick();
        $rounds++;
    }
    $after = vergeml_ai_run_state();
    $found = array(
        'started' => ! is_wp_error( $started ),
        'rounds'  => $rounds,
        'active'  => ! empty( $after['active'] ),
        'scope'   => (string) $after['scope'],
        'reason'  => isset( $after['reason'] ) ? (string) $after['reason'] : '',
        'booked'  => false !== wp_next_scheduled( 'vergeml_ai_run_tick' ),
    );
    set_transient( 'vergeml_ai_run_lock', 1, MINUTE_IN_SECONDS );
    vergeml_ai_run_stop( '' );
    delete_option( 'vergeml_ai_run' );
    wp_clear_scheduled_hook( 'vergeml_ai_run_tick' );
    delete_transient( 'vergeml_ai_run_lock' );
    return $found;
}

$bg_stamp_real = vergeml_index_current_stamp();
$bg_model_made = bg_seed( 2, 'm' );
$bg_made       = array_merge( $bg_made, $bg_model_made );
foreach ( $bg_model_made as $bg_id ) {
    bg_plant_newest( $bg_id, $bg_stamp_real['prompt_hash'], 5 );
}
$bg_stamp_now = vergeml_index_current_stamp();
$bg_stale     = (int) vergeml_ai_pending_count( 'stale' );
$bg_g1        = bg_run_to_end( $bg_made, 'g1' );
bg_check(
    sprintf( 'G1 the newest row is on another model (%s) and the same prompt: nothing is stale, and a finished run starts no sweep', $bg_stamp_now['model'] ),
    '' !== (string) $bg_stamp_real['prompt_hash'] && 'zz-other-model' === (string) $bg_stamp_now['model'] && 0 === $bg_stale && $bg_g1['started'] && ! $bg_g1['active'] && ! $bg_g1['booked'],
    json_encode( array( 'stamp' => $bg_stamp_now['model'], 'stale' => $bg_stale, 'run' => $bg_g1 ) )
);

// The newest row on another prompt: every real row is stale now, which is
// the number the dashboard's button carries.
$bg_prompt_made = bg_seed( 1, 'p' );
$bg_made        = array_merge( $bg_made, $bg_prompt_made );
bg_plant_newest( $bg_prompt_made[0], 'zz-other-prompt-1', 10 );
$bg_stale_before = (int) vergeml_ai_pending_count( 'stale' );
$bg_g2           = bg_run_to_end( $bg_made, 'g2' );
$bg_stale_after  = (int) vergeml_ai_pending_count( 'stale' );
bg_check(
    'G2 the newest row is on another prompt: the library is stale, and a finished run still starts no sweep -- the count waits on the button',
    $bg_stale_before > 0 && $bg_g2['started'] && ! $bg_g2['active'] && ! $bg_g2['booked'] && $bg_stale_after >= $bg_stale_before,
    json_encode( array( 'stale' => array( $bg_stale_before, $bg_stale_after ), 'run' => $bg_g2 ) )
);

// The hash moves under a describe step: the alt-text write of the first
// described file plants a newer row on yet another prompt, between the
// step's stamp before and its stamp after.
$bg_step_made = bg_seed( 1, 's' );
$bg_hash_made = bg_seed( 1, 'h' );
$bg_made      = array_merge( $bg_made, $bg_step_made, $bg_hash_made );
$bg_hash_id   = $bg_hash_made[0];
$bg_step_hook = function ( $meta_id, $object_id, $meta_key ) use ( $bg_hash_id ) {
    if ( '_wp_attachment_image_alt' === $meta_key ) {
        bg_plant_newest( $bg_hash_id, 'zz-other-prompt-2', 15 );
    }
};
add_action( 'added_post_meta', $bg_step_hook, 10, 3 );
vergeml_ai_run_stop( '' );
delete_option( 'vergeml_ai_run' );
wp_clear_scheduled_hook( 'vergeml_ai_run_tick' );
set_transient( 'vergeml_ai_run_lock', 1, MINUTE_IN_SECONDS );
$bg_hash_before = vergeml_index_current_stamp();
$bg_step        = vergeml_ai_index_step( 'unindexed', 1, true );
$bg_hash_after  = vergeml_index_current_stamp();
$bg_g3          = vergeml_ai_run_state();
$bg_g3_booked   = false !== wp_next_scheduled( 'vergeml_ai_run_tick' );
remove_action( 'added_post_meta', $bg_step_hook, 10 );
vergeml_ai_run_stop( '' );
delete_option( 'vergeml_ai_run' );
wp_clear_scheduled_hook( 'vergeml_ai_run_tick' );
delete_transient( 'vergeml_ai_run_lock' );
bg_check(
    'G3 the prompt hash moves under a describe step: the step starts no sweep either',
    'zz-other-prompt-1' === (string) $bg_hash_before['prompt_hash'] && 'zz-other-prompt-2' === (string) $bg_hash_after['prompt_hash'] && 1 === count( $bg_step['described'] ) && empty( $bg_g3['active'] ) && ! $bg_g3_booked,
    json_encode( array( 'hash' => array( $bg_hash_before['prompt_hash'], $bg_hash_after['prompt_hash'] ), 'described' => count( $bg_step['described'] ), 'active' => ! empty( $bg_g3['active'] ), 'reason' => isset( $bg_g3['reason'] ) ? $bg_g3['reason'] : '', 'booked' => $bg_g3_booked ) )
);

remove_filter( 'vergeml_ai_run_should_nudge', '__return_false' );
remove_filter( 'pre_http_request', 'bg_no_cron', 1 );


bg_say( "\ntidying up\n" );

vergeml_ai_run_stop( '' );
delete_option( 'vergeml_ai_run' );
delete_transient( 'vergeml_ai_run_lock' );

foreach ( $bg_made as $bg_id ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( $wpdb->vergeml_ai_index, array( 'attachment_id' => $bg_id ), array( '%d' ) );
    wp_delete_post( $bg_id, true );
}

update_option( 'vergeml_ai', $bg_before_settings, false );

$bg_left = 0;

foreach ( $bg_made as $bg_id ) {
    if ( get_post( $bg_id ) ) {
        $bg_left++;
    }
}

bg_check( 'the seeded files are gone', 0 === $bg_left, $bg_left . ' left behind' );
bg_check( 'the settings are back as they were', get_option( 'vergeml_ai', array() ) === $bg_before_settings );

bg_say( sprintf( "\n%d/%d passed\n", $GLOBALS['bg_pass'], $GLOBALS['bg_pass'] + $GLOBALS['bg_fail'] ) );

@file_put_contents( __DIR__ . '/background-last-run.txt', $GLOBALS['bg_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['bg_fail'] > 0 ) {
    exit( 1 );
}
