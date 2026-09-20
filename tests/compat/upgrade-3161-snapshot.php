<?php
/**
 *  What a site holds, by literal SQL, before and after a plugin swap.
 *
 *      VGML_SNAP=/root/vgml-upg.json wp eval-file tests/compat/upgrade-3161-snapshot.php --allow-root                 # freeze
 *      VGML_SNAP=/root/vgml-upg.json VGML_COMPARE=1 wp eval-file tests/compat/upgrade-3161-snapshot.php --allow-root  # compare
 *
 *  Never through the plugin's own readers -- a snapshot computed by the code
 *  under test deleted a hundred real alt texts on the box once
 *  (tools/box-shop-untree.php carries the same rule). Every column a customer
 *  could lose: the folders and their meta, every relationship, every alt text,
 *  the attachment meta (file, sizes, placed-by, autonomy -- by hash), both
 *  librarian tables in the columns 3.16.1 had, the index in all its columns
 *  (embedding and projection by hash), the attachments, the Folders session
 *  and the two settings options. The compare
 *  prints each difference and ends with `N differences`; upgrade-3161.php
 *  reads the same file and repeats the comparison as checks of its own.
 *
 *  Works under either plugin version: it names tables by prefix and asks the
 *  plugin nothing. `moves` is read in its schema-3 columns so the two new
 *  columns 4.0.0 adds do not count as a difference.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$p    = $wpdb->prefix;
$file = getenv( 'VGML_SNAP' ) ?: '/root/vgml-upg.json';

function snap_rows( $sql ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $sql, ARRAY_A );
	if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
		echo '  ' . $wpdb->last_error . "
";
		echo "  query failed: $sql\n";
		exit( 1 );
	}
	return array_map( 'array_values', $rows );
}

/** Key order inside a serialized option is not something a customer can lose. */
function snap_norm( $v ) {
	if ( is_array( $v ) ) {
		ksort( $v );
		foreach ( $v as $k => $x ) {
			$v[ $k ] = snap_norm( $x );
		}
	}
	return $v;
}

/** sha256 over "sha256  path" per file, sorted: what sha256sum would print, hashed. */
function snap_plugin_digest( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return 'absent';
	}
	$lines = array();
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() ) {
			$lines[] = hash_file( 'sha256', $f->getPathname() ) . '  ' . str_replace( '\\', '/', substr( $f->getPathname(), strlen( $dir ) + 1 ) );
		}
	}
	sort( $lines, SORT_STRING );
	return hash( 'sha256', implode( "\n", $lines ) . "\n" ) . ' (' . count( $lines ) . ' files)';
}

const VGML_SNAP_TABLES = array( 'terms', 'termmeta', 'rels', 'alts', 'postmeta', 'batches', 'moves', 'index', 'posts' );

$state = array(
	'terms'    => snap_rows( "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.parent, tt.description, tt.count FROM {$p}terms t JOIN {$p}term_taxonomy tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'media_category' ORDER BY t.term_id" ),
	'termmeta' => snap_rows( "SELECT tm.meta_id, tm.term_id, tm.meta_key, tm.meta_value FROM {$p}termmeta tm JOIN {$p}term_taxonomy tt ON tt.term_id = tm.term_id WHERE tt.taxonomy = 'media_category' ORDER BY tm.term_id, tm.meta_key, tm.meta_id" ),
	'rels'     => snap_rows( "SELECT tr.object_id, tt.term_id FROM {$p}term_relationships tr JOIN {$p}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = 'media_category' ORDER BY tr.object_id, tt.term_id" ),
	'alts'     => snap_rows( "SELECT post_id, meta_value FROM {$p}postmeta WHERE meta_key = '_wp_attachment_image_alt' ORDER BY post_id" ),
	'postmeta' => snap_rows( "SELECT post_id, meta_key, MD5(meta_value) FROM {$p}postmeta WHERE meta_key IN ('_wp_attached_file', '_wp_attachment_metadata', '_vergeml_placed_by', '_vergeml_autonomy') ORDER BY post_id, meta_key" ),
	'batches'  => snap_rows( "SELECT batch_id, run_id, scheme, status, step_cursor, done_n, skip_n, params, reason, user_id, approved_at, created_at FROM {$p}vergeml_librarian_batches ORDER BY batch_id" ),
	'moves'    => snap_rows( "SELECT move_id, batch_id, attachment_id, term_id, term_created, undone, why, score, runner_up, runner_score, prompt_hash, model_version, nearest FROM {$p}vergeml_librarian_moves ORDER BY move_id" ),
	'index'    => snap_rows( "SELECT attachment_id, caption, alt, title, tags, kind, has_people, has_text, document_type, filing, orientation, MD5(embedding), embedding_dims, MD5(projection), model, model_version, prompt_hash, locked, error, described_at FROM {$p}vergeml_ai_index ORDER BY attachment_id" ),
	'posts'    => snap_rows( "SELECT ID, post_title, post_name, post_excerpt, post_content, guid, post_mime_type, post_parent, post_status FROM {$p}posts WHERE post_type = 'attachment' ORDER BY ID" ),
	'options'  => array(
		'guide'      => (array) get_option( 'vergeml_guide_session', array() ),
		'ai'         => (array) get_option( 'vergeml_ai', array() ),
		'taxonomies' => (array) get_option( 'vergeml_taxonomies', array() ),
	),
);
$log = WP_CONTENT_DIR . '/debug.log';
$state['debug_log_lines'] = file_exists( $log ) ? count( file( $log ) ) : 0;
// The two options the upgrade is supposed to change are recorded, not compared.
$state['moving'] = array(
	'vergeml_version'   => get_option( 'vergeml_version' ),
	'vergeml_librarian' => get_option( 'vergeml_librarian' ),
);
// Which bytes were running: sha256 over "sha256  path" per file of the plugin
// directory, sorted -- the same line sha256sum would print. Recorded, not
// compared: the swap is supposed to change it, and the box legs otherwise
// never say which archive they ran against (Epic 1 retro, A-3).
$state['plugin_digest'] = snap_plugin_digest( WP_PLUGIN_DIR . '/vergelabs-media-library' );

if ( ! getenv( 'VGML_COMPARE' ) ) {
	if ( false === file_put_contents( $file, wp_json_encode( $state, JSON_PRETTY_PRINT ) ) ) {
		echo "could not write $file
";
		exit( 1 );
	}
	foreach ( VGML_SNAP_TABLES as $k ) {
		echo str_pad( $k, 10 ) . count( $state[ $k ] ) . "\n";
	}
	echo 'moving    ' . wp_json_encode( $state['moving'] ) . "\n";
	echo 'plugin    ' . $state['plugin_digest'] . "\n";
	echo 'debug.log ' . $state['debug_log_lines'] . " lines\n";
	echo "frozen to $file\n";
	return;
}

$before = json_decode( (string) file_get_contents( $file ), true );
if ( ! is_array( $before ) || ! isset( $before['options'] ) ) {
	echo "no usable snapshot at $file\n";
	exit( 1 );
}

$diffs = 0;
foreach ( VGML_SNAP_TABLES as $k ) {
	// Counted, not set-compared: a lost duplicate row is a difference too.
	$a = array_count_values( array_map( 'wp_json_encode', (array) ( $before[ $k ] ?? array() ) ) );
	$b = array_count_values( array_map( 'wp_json_encode', $state[ $k ] ) );
	foreach ( $a as $row => $n ) {
		if ( ( $b[ $row ] ?? 0 ) < $n ) {
			echo "  $k gone:  $row\n";
			$diffs += $n - ( $b[ $row ] ?? 0 );
		}
	}
	foreach ( $b as $row => $n ) {
		if ( ( $a[ $row ] ?? 0 ) < $n ) {
			echo "  $k new:   $row\n";
			$diffs += $n - ( $a[ $row ] ?? 0 );
		}
	}
}
// An option may gain keys on upgrade (4.0.0 merges the fresh shape into the
// session); what it had must read the same. updated_at is the screen's own.
foreach ( array( 'guide', 'ai', 'taxonomies' ) as $opt ) {
	foreach ( (array) ( $before['options'][ $opt ] ?? array() ) as $k => $v ) {
		if ( 'updated_at' === $k ) {
			continue;
		}
		if ( ! array_key_exists( $k, $state['options'][ $opt ] ) || wp_json_encode( snap_norm( $state['options'][ $opt ][ $k ] ) ) !== wp_json_encode( snap_norm( $v ) ) ) {
			echo "  option $opt key changed: $k\n    before " . wp_json_encode( $v ) . "\n    after  " . wp_json_encode( $state['options'][ $opt ][ $k ] ?? null ) . "\n";
			$diffs++;
		}
	}
}
echo 'moving before ' . wp_json_encode( $before['moving'] ) . "\n";
echo 'moving after  ' . wp_json_encode( $state['moving'] ) . "\n";
echo 'plugin before ' . ( $before['plugin_digest'] ?? 'not recorded (snapshot predates A-3)' ) . "\n";
echo 'plugin after  ' . $state['plugin_digest'] . "\n";
echo "$diffs differences\n";
