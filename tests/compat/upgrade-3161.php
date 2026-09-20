<?php
/**
 *  A 3.16.1 site upgraded in place to 4.0.0 kept everything.
 *
 *  Runs under 4.0.0, after `wp plugin install <4.0.0 zip> --force` over a
 *  site that upgrade-3161-fixture.php built under 3.16.1 and
 *  upgrade-3161-snapshot.php froze:
 *
 *      VGML_SNAP=/root/vgml-upg.json wp eval-file tests/compat/upgrade-3161.php --allow-root
 *
 *  The librarian schema went 3 -> 4 (`source`, `hit` on moves); the guide
 *  session gained a `tree` key (4.0.0 merges a version-2 session with its
 *  fresh shape, core/guide.php:559). Everything else a customer had must read
 *  exactly as the snapshot says. Counters in $GLOBALS: `wp eval-file` runs
 *  this inside a function, and a `global` there binds to nothing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['up_pass'] = 0;
$GLOBALS['up_fail'] = 0;
function up( $name, $ok, $detail = '' ) {
	$GLOBALS[ $ok ? 'up_pass' : 'up_fail' ]++;
	echo ( $ok ? '  ok   ' : '  FAIL ' ) . $name . ( '' === $detail ? '' : "  [$detail]" ) . "\n";
}

global $wpdb;
$p = $wpdb->prefix;

// How long debug.log is as this run starts: on the box the judgement at the
// end covers the lines this run wrote, nothing older (the window used to open
// at the snapshot's count on a fixture that stays up -- 1,100 lines and
// growing, red for good on any other notice; Epic 1 retro, A-5).
$log            = WP_CONTENT_DIR . '/debug.log';
$log_at_start   = file_exists( $log ) ? count( file( $log ) ) : 0;

// The upgrade runs on plugins_loaded in admin, cron or CLI; wp-cli counts, so
// this process has already provisioned if the option was behind.
// Against the running plugin, not a literal: the fixture stays up across
// 4.0.1 and beyond, and a suite pinned to one version would go red on the
// first patch release with nothing regressed.
up( 'the plugin is a 4.x', 0 === strpos( VERGEML_VERSION, '4.' ), VERGEML_VERSION );
up( 'vergeml_version moved to the running version', get_option( 'vergeml_version' ) === VERGEML_VERSION, (string) get_option( 'vergeml_version' ) );

$lib = get_option( 'vergeml_librarian' );
up( 'librarian schema is the code\'s', is_array( $lib ) && VERGEML_LIBRARIAN_VERSION === (int) ( $lib['schema'] ?? 0 ), (string) ( $lib['schema'] ?? 'none' ) );
up( 'and the code\'s is at least 4 (source, hit)', VERGEML_LIBRARIAN_VERSION >= 4, (string) VERGEML_LIBRARIAN_VERSION );

// phpcs:disable WordPress.DB.DirectDatabaseQuery
$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$p}vergeml_librarian_moves", 0 );
up( 'moves carries source', in_array( 'source', $cols, true ) );
up( 'moves carries hit', in_array( 'hit', $cols, true ) );
up( 'moves kept its old columns', ! array_diff( array( 'move_id', 'batch_id', 'attachment_id', 'term_id', 'why', 'nearest' ), $cols ) );

$snap_file = getenv( 'VGML_SNAP' ) ?: '/root/vgml-upg.json';
$snap      = json_decode( (string) file_get_contents( $snap_file ), true );
up( 'the snapshot is there', is_array( $snap ) && isset( $snap['moves'], $snap['batches'], $snap['options'] ), $snap_file );
$old_moves = is_array( $snap ) && isset( $snap['moves'] ) ? count( $snap['moves'] ) : 0;
up( 'the snapshot had at least five moves', $old_moves >= 5, (string) $old_moves );
up( 'and at least one batch', is_array( $snap ) && isset( $snap['batches'] ) && count( $snap['batches'] ) >= 1 );
// Provenance: the baseline must have been frozen under 3.16.1, before the swap.
// A snapshot taken after would compare 4.0.0 with itself and say 0 differences.
$frozen_v = (string) ( $snap['moving']['vergeml_version'] ?? '' );
$frozen_s = (int) ( $snap['moving']['vergeml_librarian']['schema'] ?? 0 );
up( 'the snapshot was frozen under 3.16.1', 0 === strpos( $frozen_v, '3.16.1' ), $frozen_v ?: 'none' );
up( 'with the librarian at schema 3', 3 === $frozen_s, (string) $frozen_s );
$now_moves = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vergeml_librarian_moves" );
up( 'every old move row is still there', $now_moves === $old_moves, "$now_moves of $old_moves" );
$new_cols_empty = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vergeml_librarian_moves WHERE source = '' AND hit = ''" );
up( 'old rows have the new columns at their defaults', $new_cols_empty === $old_moves, (string) $new_cols_empty );

up( 'twenty pictures', 20 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}posts WHERE post_type = 'attachment'" ) );
up( 'three folders', 3 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}term_taxonomy WHERE taxonomy = 'media_category'" ) );
$rels = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}term_relationships tr JOIN {$p}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'media_category'" );
up( 'six pictures still filed', 6 === $rels, (string) $rels );
up( 'the folder counts add up to six', 6 === (int) $wpdb->get_var( "SELECT SUM(count) FROM {$p}term_taxonomy WHERE taxonomy = 'media_category'" ) );

// Row for row, through the same literal-SQL compare the snapshot was frozen
// with: terms and their meta, relationships, alt texts, both librarian tables
// in their schema-3 columns, index rows, attachments, the three options.
$compare = file_exists( __DIR__ . '/upgrade-3161-snapshot.php' ) ? __DIR__ . '/upgrade-3161-snapshot.php' : '/tmp/upgrade-3161-snapshot.php';
up( 'the compare script is beside the suite', file_exists( $compare ), $compare );
if ( file_exists( $compare ) ) {
	putenv( 'VGML_SNAP=' . $snap_file );
	putenv( 'VGML_COMPARE=1' );
	ob_start();
	( function () use ( $compare ) {
		include $compare;
	} )();
	$said = ob_get_clean();
	putenv( 'VGML_COMPARE' );
	$n = preg_match( '/(\d+) differences/', $said, $m ) ? (int) $m[1] : -1;
	up( 'every row the customer had reads the same (0 differences)', 0 === $n, 0 === $n ? '' : trim( $said ) );
}
// Playground's SQLite keeps no packed embedding through $wpdb->insert, so the
// index is empty there; the row count is a box-only check.
if ( ! getenv( 'VGML_SMOKE' ) ) {
	up( 'twenty index rows, all mock', 20 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vergeml_ai_index WHERE model = 'mock'" ) );
}

$s = vergeml_guide_session();
up( 'the Folders session reads through 4.0.0', is_array( $s ) && 2 === (int) $s['version'] );
up( 'and its tree is editing', is_array( $s ) && 'editing' === ( $s['tree'] ?? null ), (string) ( $s['tree'] ?? 'none' ) );
$raw = get_option( 'vergeml_guide_session' );
up( 'the stored session was 3.16.1\'s (no tree key of its own, or editing)', is_array( $raw ) && ( ! isset( $raw['tree'] ) || 'editing' === $raw['tree'] ) );

up( 'no upgrade lock left', false === get_option( 'vergeml_upgrading' ) );
up( 'mock mode survived', ! empty( vergeml_ai_settings()['mock'] ) );

// One admin request through the real front door, as the customer's browser
// would make it. Playground cannot reach itself over HTTP, so the smoke sets
// VGML_SMOKE and this one check is not counted there.
if ( ! getenv( 'VGML_SMOKE' ) ) {
	$r = wp_remote_get( admin_url( 'upload.php' ), array( 'timeout' => 30, 'sslverify' => false ) );
	$code = is_wp_error( $r ) ? $r->get_error_message() : (string) wp_remote_retrieve_response_code( $r );
	up( 'wp-admin/upload.php answers (302 to login or 200)', in_array( $code, array( '200', '302' ), true ), $code );

	// The Folders screen, logged in as the site's first administrator, through
	// the front door: this is where a session 4.0.0 cannot read shows up as a
	// 500 (a hand-shaped draft did exactly that on 2026-09-19).
	$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ) );
	$uid   = $admin ? (int) $admin[0] : 0;
	$exp   = time() + 600;
	$jar   = array(
		new WP_Http_Cookie( array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'auth' ) ) ),
		new WP_Http_Cookie( array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $uid, $exp, 'logged_in' ) ) ),
	);
	$r    = wp_remote_get( admin_url( 'admin.php?page=media-librarian' ), array( 'timeout' => 60, 'sslverify' => false, 'cookies' => $jar ) );
	$code = is_wp_error( $r ) ? $r->get_error_message() : (string) wp_remote_retrieve_response_code( $r );
	$body = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
	up( 'the Folders screen answers 200, logged in', '200' === $code, $code );
	up( 'and it is the Folders screen, not the login form', false !== strpos( $body, 'page=media-librarian' ) && false === strpos( $body, 'id="loginform"' ) );
}

// What this run wrote to debug.log: the CLI boot above and, on the box, the
// two front-door requests. In the smoke the window opens at the snapshot's
// count instead -- there the swap and the admin boot are separate runPHP
// steps before this one, and the fixture stage's own notices (Playground's
// SQLite refuses the packed embedding) are not the upgrade's.
$since = getenv( 'VGML_SMOKE' )
	? ( is_array( $snap ) && isset( $snap['debug_log_lines'] ) ? (int) $snap['debug_log_lines'] : 0 )
	: $log_at_start;
$all   = file_exists( $log ) ? file( $log ) : array();
up( 'debug.log did not shrink since the window opened', count( $all ) >= $since, count( $all ) . ' vs ' . $since . ( getenv( 'VGML_SMOKE' ) ? ' (the snapshot)' : ' (this run)' ) );
$lines = array_slice( $all, $since );
$mine  = preg_grep( '#vergelabs-media-library/#', $lines );
// The one line the 4.0.0 archive is known to write on PHP 8.4+: the
// implicit-nullable parameter of vergeml_ai_rest_status(), fixed in the tree
// on 2026-09-19 and shipping with 4.0.1. Named, not hidden, and only where
// the archive runs: on the box fixture while it is on 4.0.0. The smoke runs
// the tree's own zip, where the line must not appear at all -- a revert of
// the fix turns the smoke red. Retires itself when the fixture moves on.
$allow = ! getenv( 'VGML_SMOKE' ) && '4.0.0' === VERGEML_VERSION;
$known = $allow ? preg_grep( '#vergeml_ai_rest_status\(\): Implicitly marking parameter#', $mine ) : array();
$other = array_diff_key( $mine, $known );
up( 'debug.log carries no unexpected line from the plugin in that window', ! $other, $other ? trim( reset( $other ) ) : ( count( $lines ) . ' new lines' . ( $known ? ', ' . count( $known ) . ' known (4.0.0 implicit-nullable, fixed for 4.0.1)' : ', none ours' ) ) );

$total = $GLOBALS['up_pass'] + $GLOBALS['up_fail'];
echo "\n{$GLOBALS['up_pass']}/{$total} passed\n";
if ( $GLOBALS['up_fail'] ) {
	exit( 1 );
}
