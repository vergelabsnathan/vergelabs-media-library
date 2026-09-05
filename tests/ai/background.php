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
