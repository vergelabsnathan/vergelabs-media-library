<?php
/**
 *  A 3.16.1 site upgraded in place to 4.0.0 kept everything.
 *
 *  Runs under 4.0.0, after `wp plugin install <4.0.0 zip> --force` over a
 *  site that upgrade-3161-fixture.php built under 3.16.1 and
 *  upgrade-3161-snapshot.php froze:
 *
 *      VGML_SNAP=/tmp/vgml-upg.json wp eval-file tests/compat/upgrade-3161.php --allow-root
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

// The upgrade runs on plugins_loaded in admin, cron or CLI; wp-cli counts, so
// this process has already provisioned if the option was behind.
up( 'the plugin is 4.0.0', '4.0.0' === VERGEML_VERSION, VERGEML_VERSION );
up( 'vergeml_version moved to 4.0.0', '4.0.0' === get_option( 'vergeml_version' ), (string) get_option( 'vergeml_version' ) );

$lib = get_option( 'vergeml_librarian' );
up( 'librarian schema is 4', is_array( $lib ) && 4 === (int) $lib['schema'], is_array( $lib ) ? (string) $lib['schema'] : 'none' );
up( 'the code says 4 too', 4 === VERGEML_LIBRARIAN_VERSION );

// phpcs:disable WordPress.DB.DirectDatabaseQuery
$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$p}vergeml_librarian_moves", 0 );
up( 'moves carries source', in_array( 'source', $cols, true ) );
up( 'moves carries hit', in_array( 'hit', $cols, true ) );
up( 'moves kept its old columns', ! array_diff( array( 'move_id', 'batch_id', 'attachment_id', 'term_id', 'why', 'nearest' ), $cols ) );

$snap = json_decode( (string) file_get_contents( getenv( 'VGML_SNAP' ) ?: '/tmp/vgml-upg.json' ), true );
up( 'the snapshot is there', is_array( $snap ) && isset( $snap['moves'] ) );
$old_moves = is_array( $snap ) ? count( $snap['moves'] ) : 0;
up( 'the snapshot had at least five moves', $old_moves >= 5, (string) $old_moves );
up( 'and at least one batch', is_array( $snap ) && count( $snap['batches'] ) >= 1 );
$now_moves = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vergeml_librarian_moves" );
up( 'every old move row is still there', $now_moves === $old_moves, "$now_moves of $old_moves" );
$new_cols_empty = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vergeml_librarian_moves WHERE source = '' AND hit = ''" );
up( 'old rows have the new columns at their defaults', $new_cols_empty === $old_moves, (string) $new_cols_empty );

up( 'twenty pictures', 20 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}posts WHERE post_type = 'attachment'" ) );
up( 'three folders', 3 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}term_taxonomy WHERE taxonomy = 'media_category'" ) );
$rels = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}term_relationships tr JOIN {$p}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'media_category'" );
up( 'six pictures still filed', 6 === $rels, (string) $rels );
up( 'the folder counts add up to six', 6 === (int) $wpdb->get_var( "SELECT SUM(count) FROM {$p}term_taxonomy WHERE taxonomy = 'media_category'" ) );
// Playground's SQLite keeps no packed embedding through $wpdb->insert, so the
// index is empty there; the row count is a box-only check.
if ( ! getenv( 'VGML_SMOKE' ) ) {
	up( 'twenty index rows, all mock', 20 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vergeml_ai_index WHERE model = 'mock'" ) );
}

$s = vergeml_guide_session();
up( 'the Folders session reads through 4.0.0', is_array( $s ) && 2 === (int) $s['version'] );
up( 'and its tree is editing', is_array( $s ) && 'editing' === $s['tree'], is_array( $s ) ? (string) $s['tree'] : 'none' );
$raw = get_option( 'vergeml_guide_session' );
up( 'the stored session was 3.16.1\'s (no tree key of its own, or editing)', is_array( $raw ) && ( ! isset( $raw['tree'] ) || 'editing' === $raw['tree'] ) );

up( 'no upgrade lock left', false === get_option( 'vergeml_upgrading' ) );
up( 'mock mode survived', ! empty( vergeml_ai_settings()['mock'] ) );

// Only what was written after the snapshot: the fixture stage's own notices
// (Playground's SQLite refuses the packed embedding) are not the upgrade's.
$log   = WP_CONTENT_DIR . '/debug.log';
$since = is_array( $snap ) && isset( $snap['debug_log_lines'] ) ? (int) $snap['debug_log_lines'] : 0;
$lines = file_exists( $log ) ? array_slice( file( $log ), $since ) : array();
$mine  = preg_grep( '#vergelabs-media-library/#', $lines );
up( 'debug.log carries no line from the plugin since the snapshot', ! $mine, $mine ? trim( reset( $mine ) ) : count( $lines ) . ' new lines, none ours' );

// One admin request through the real front door, as the customer's browser
// would make it. Playground cannot reach itself over HTTP, so the smoke sets
// VGML_SMOKE and this one check is not counted there.
if ( ! getenv( 'VGML_SMOKE' ) ) {
	$r = wp_remote_get( admin_url( 'upload.php' ), array( 'timeout' => 30, 'sslverify' => false ) );
	$code = is_wp_error( $r ) ? $r->get_error_message() : (string) wp_remote_retrieve_response_code( $r );
	up( 'wp-admin/upload.php answers (302 to login or 200)', in_array( $code, array( '200', '302' ), true ), $code );
}

$total = $GLOBALS['up_pass'] + $GLOBALS['up_fail'];
echo "\n{$GLOBALS['up_pass']}/{$total} passed\n";
if ( $GLOBALS['up_fail'] ) {
	exit( 1 );
}
