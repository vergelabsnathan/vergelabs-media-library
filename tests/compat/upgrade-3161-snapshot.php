<?php
/**
 *  What a site holds, by literal SQL, before and after a plugin swap.
 *
 *      VGML_SNAP=/tmp/vgml-upg.json wp eval-file tests/compat/upgrade-3161-snapshot.php --allow-root            # freeze
 *      VGML_SNAP=/tmp/vgml-upg.json VGML_COMPARE=1 wp eval-file tests/compat/upgrade-3161-snapshot.php --allow-root   # compare
 *
 *  Never through the plugin's own readers -- a snapshot computed by the code
 *  under test deleted a hundred real alt texts on the box once
 *  (tools/box-shop-untree.php carries the same rule). Six tables and three
 *  options, as rows; the compare prints each difference and ends with
 *  `N differences`, which is the line the suite reads.
 *
 *  Works under either plugin version: it names tables by prefix and asks
 *  the plugin nothing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$p    = $wpdb->prefix;
$file = getenv( 'VGML_SNAP' ) ?: '/tmp/vgml-upg.json';

function snap_rows( $sql ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return array_map( 'array_values', (array) $wpdb->get_results( $sql, ARRAY_A ) );
}

$state = array(
	'terms'    => snap_rows( "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.parent, tt.count FROM {$p}terms t JOIN {$p}term_taxonomy tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'media_category' ORDER BY t.term_id" ),
	'rels'     => snap_rows( "SELECT tr.object_id, tt.term_id FROM {$p}term_relationships tr JOIN {$p}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'media_category' ORDER BY tr.object_id, tt.term_id" ),
	'batches'  => snap_rows( "SELECT batch_id, scheme, status, created_at FROM {$p}vergeml_librarian_batches ORDER BY batch_id" ),
	'moves'    => snap_rows( "SELECT move_id, batch_id, attachment_id, term_id FROM {$p}vergeml_librarian_moves ORDER BY move_id" ),
	'index'    => snap_rows( "SELECT attachment_id, model, alt FROM {$p}vergeml_ai_index ORDER BY attachment_id" ),
	'posts'    => snap_rows( "SELECT ID, post_title FROM {$p}posts WHERE post_type = 'attachment' ORDER BY ID" ),
	'options'  => array(
		'guide' => (array) get_option( 'vergeml_guide_session', array() ),
	),
);
// The two options the upgrade is supposed to change are recorded, not compared.
$log = WP_CONTENT_DIR . '/debug.log';
$state['debug_log_lines'] = file_exists( $log ) ? count( file( $log ) ) : 0;
$state['moving'] = array(
	'vergeml_version'   => get_option( 'vergeml_version' ),
	'vergeml_librarian' => get_option( 'vergeml_librarian' ),
);

if ( ! getenv( 'VGML_COMPARE' ) ) {
	file_put_contents( $file, wp_json_encode( $state, JSON_PRETTY_PRINT ) );
	foreach ( array( 'terms', 'rels', 'batches', 'moves', 'index', 'posts' ) as $k ) {
		echo str_pad( $k, 10 ) . count( $state[ $k ] ) . "\n";
	}
	echo 'moving    ' . wp_json_encode( $state['moving'] ) . "\n";
	echo "frozen to $file\n";
	return;
}

$before = json_decode( (string) file_get_contents( $file ), true );
if ( ! is_array( $before ) ) {
	echo "no snapshot at $file\n";
	exit( 1 );
}

$diffs = 0;
foreach ( array( 'terms', 'rels', 'batches', 'moves', 'index', 'posts' ) as $k ) {
	$a = array_map( 'wp_json_encode', $before[ $k ] );
	$b = array_map( 'wp_json_encode', $state[ $k ] );
	foreach ( array_diff( $a, $b ) as $row ) {
		echo "  $k gone:  $row\n";
		$diffs++;
	}
	foreach ( array_diff( $b, $a ) as $row ) {
		echo "  $k new:   $row\n";
		$diffs++;
	}
}
// The session may gain keys on upgrade (4.0.0 merges the fresh shape in); what it had must stay.
foreach ( $before['options']['guide'] as $k => $v ) {
	if ( 'updated_at' === $k ) {
		continue;
	}
	if ( ! array_key_exists( $k, $state['options']['guide'] ) || wp_json_encode( $state['options']['guide'][ $k ] ) !== wp_json_encode( $v ) ) {
		echo "  guide key changed: $k\n";
		$diffs++;
	}
}
echo 'moving before ' . wp_json_encode( $before['moving'] ) . "\n";
echo 'moving after  ' . wp_json_encode( $state['moving'] ) . "\n";
echo "$diffs differences\n";
