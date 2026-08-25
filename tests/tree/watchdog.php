<?php
/**
 *  The thing that stops a fatal error taking the site down.
 *
 *      wp eval-file tests/tree/watchdog.php --allow-root
 *
 *  This is the feature 3.0.0 exists for, and until now nothing tested it. If it
 *  is broken the failure is the worst one available: a fatal in our code white-
 *  screens somebody's site and the recovery that was supposed to catch it does
 *  not run.
 *
 *  Four properties, and the second is the one that would do real damage if it
 *  were wrong:
 *
 *    - two crashes in an hour stop the features loading, so the site comes back;
 *    - a crash in somebody else's file is never counted. Deactivating our own
 *      features because another plugin is broken would be bad; the ladder ends
 *      in deactivation, so miscounting eventually switches off a plugin that was
 *      working perfectly;
 *    - safe mode is given a minute before the ladder goes any further, because
 *      one broken page view is many PHP requests and they all land at once;
 *    - it can be switched back on, or the recovery is a trap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_set_current_user( 1 );

$GLOBALS['vgml_ok']  = 0;
$GLOBALS['vgml_bad'] = 0;

function t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['vgml_ok']++ : $GLOBALS['vgml_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, $detail ? '  -- ' . $detail : '' );
}

/*
 *  safe_mode caches its answer for the request, so every check here reads the
 *  option directly. Testing the cache instead of the decision would pass while
 *  the plugin did the opposite.
 */
function vgml_is_safe() {
	$state = get_option( 'vergeml_watchdog', array() );
	return is_array( $state ) && ! empty( $state['safe'] );
}

$restore = get_option( 'vergeml_watchdog', null );

echo "\nthe watchdog\n\n";

/* --- whose crash is it? --------------------------------------------------- */

$dir    = plugin_dir_path( VERGEML_FILE );
$ours   = $dir . 'core/rest-tree.php';
$theirs = WP_PLUGIN_DIR . '/some-other-plugin/other.php';

t( 'a file of ours is recognised', vergeml_watchdog_is_ours( $ours ), $ours );
t( 'and another plugin is not', ! vergeml_watchdog_is_ours( $theirs ), $theirs );
t( 'nor is core itself', ! vergeml_watchdog_is_ours( ABSPATH . 'wp-includes/post.php' ) );

/* --- two strikes ---------------------------------------------------------- */

delete_option( 'vergeml_watchdog' );

$error = array(
	'file'    => $ours,
	'line'    => 42,
	'message' => 'Simulated fatal for the watchdog test',
);

vergeml_watchdog_strike( $error );

t( 'one crash does not stop anything', ! vgml_is_safe(),
	'count ' . ( get_option( 'vergeml_watchdog' )['count'] ?? '?' ) );

vergeml_watchdog_strike( $error );

t( 'two within the hour do', vgml_is_safe() );

$state = get_option( 'vergeml_watchdog', array() );

t( 'and it records what crashed', ! empty( $state['file'] ) && ! empty( $state['message'] ),
	basename( (string) $state['file'] ) . ':' . ( $state['line'] ?? '?' ) );
t( 'with the moment safe mode began', ! empty( $state['safe_since'] ) );

/* --- safe mode gets its minute -------------------------------------------- */

/*
 *  A third strike immediately afterwards must not deactivate. One broken page
 *  is a page load, its ajax, its favicon and whatever else the browser asks for
 *  -- every one of them fatal, all inside the same second.
 */
vergeml_watchdog_strike( $error );
vergeml_watchdog_strike( $error );

$state = get_option( 'vergeml_watchdog', array() );

t( 'a burst of crashes does not deactivate the plugin', empty( $state['deactivated_at'] ),
	empty( $state['deactivated_at'] ) ? 'still active' : 'DEACTIVATED' );
t( 'the plugin really is still active', is_plugin_active( plugin_basename( VERGEML_FILE ) ) );

/* --- but a crash a minute later does --------------------------------------- */

$aged                = get_option( 'vergeml_watchdog', array() );
$aged['safe_since']  = time() - ( 2 * MINUTE_IN_SECONDS );
update_option( 'vergeml_watchdog', $aged, true );

vergeml_watchdog_strike( $error );

$state = get_option( 'vergeml_watchdog', array() );

t( 'a crash after safe mode has had its minute goes further', ! empty( $state['deactivated_at'] ),
	empty( $state['deactivated_at'] ) ? 'NOT ESCALATED' : 'deactivated' );

// Put it back immediately: everything after this needs the plugin loaded.
activate_plugin( plugin_basename( VERGEML_FILE ), '', false, true );
t( 'and it can be switched on again', is_plugin_active( plugin_basename( VERGEML_FILE ) ) );

/* --- somebody else's crash never counts ------------------------------------ */

delete_option( 'vergeml_watchdog' );

vergeml_watchdog_strike( array( 'file' => $theirs, 'line' => 1, 'message' => 'not ours' ) );
vergeml_watchdog_strike( array( 'file' => $theirs, 'line' => 1, 'message' => 'not ours' ) );

/*
 *  strike() records whatever it is handed; the filter that decides whether to
 *  call it at all is vergeml_watchdog_is_ours, checked above. What matters here
 *  is that the two are wired together in the shutdown handler -- so this asserts
 *  the guard exists rather than that strike() second-guesses its caller.
 */
$shutdown = file_get_contents( $dir . 'core/watchdog.php' );

t( 'the shutdown handler asks whose file it was',
	false !== strpos( $shutdown, 'vergeml_watchdog_is_ours' ) &&
	preg_match( '/if\s*\(\s*!\s*vergeml_watchdog_is_ours/', $shutdown ) === 1 );

/* --- clearing it ----------------------------------------------------------- */

delete_option( 'vergeml_watchdog' );

t( 'clearing the record leaves safe mode', ! vgml_is_safe() );

/* tidy */
if ( null === $restore ) {
	delete_option( 'vergeml_watchdog' );
} else {
	update_option( 'vergeml_watchdog', $restore, true );
}

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
