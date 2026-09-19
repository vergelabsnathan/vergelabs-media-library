<?php
/**
 *  The state a 3.16.1 customer has, built by 3.16.1's own code.
 *
 *  Runs under the 3.16.1 plugin, before the swap to 4.0.0:
 *
 *      VGML_PICTURES=/tmp/vgml-upg-pics wp eval-file tests/compat/upgrade-3161-fixture.php --allow-root
 *
 *  Mock mode on (no service, no licence, no credit), twenty pictures imported
 *  from the directory in VGML_PICTURES, three folders, every picture described
 *  by the mock, six of them filed through vergeml_autofile_file( …, 'accepted' )
 *  -- the accept-a-suggestion path, which writes a librarian batch and a move
 *  per picture -- and a Folders session saved by vergeml_guide_save(). Prints
 *  the counts; upgrade-3161-snapshot.php freezes them by literal SQL.
 *
 *  Idempotent enough: a second run imports nothing if twenty attachments
 *  already exist, and files nothing already filed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['fx'] = array();
function fx( $k, $v ) {
	$GLOBALS['fx'][ $k ] = $v;
	echo str_pad( $k, 14 ) . $v . "\n";
}

$version = defined( 'VERGEML_VERSION' ) ? VERGEML_VERSION : 'unknown';
fx( 'plugin', $version );
if ( 0 !== strpos( $version, '3.16.' ) ) {
	echo "not 3.16.x -- this fixture is for the version before the swap\n";
	exit( 1 );
}

$settings         = vergeml_ai_settings();
$settings['mock'] = 1;
update_option( 'vergeml_ai', $settings );
fx( 'mock', vergeml_ai_ready() ? 'on, ready' : 'on, NOT ready' );

$tax = vergeml_librarian_taxonomy();
fx( 'taxonomy', $tax );
if ( 'media_category' !== $tax ) {
	echo "the folder taxonomy is '$tax'; the snapshot and the suite read media_category\n";
	exit( 1 );
}

$have = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
if ( count( $have ) < 20 ) {
	$dir   = getenv( 'VGML_PICTURES' ) ?: '/tmp/vgml-upg-pics';
	if ( ! is_dir( $dir ) ) {
		echo "no pictures at $dir\n";
		exit( 1 );
	}
	$files = array_values( array_filter( (array) scandir( $dir ), function ( $f ) {
		return (bool) preg_match( '/\.(jpe?g|png)$/i', $f );
	} ) );
	$files = array_map( function ( $f ) use ( $dir ) {
		return rtrim( $dir, '/' ) . '/' . $f;
	}, $files );
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	foreach ( array_slice( $files, 0, 20 - count( $have ) ) as $f ) {
		$tmp = wp_tempnam( basename( $f ) );
		copy( $f, $tmp );
		$id = media_handle_sideload( array( 'name' => basename( $f ), 'tmp_name' => $tmp ), 0 );
		if ( ! is_wp_error( $id ) ) {
			$have[] = (int) $id;
		} else {
			echo '  sideload: ' . $id->get_error_message() . "\n";
		}
	}
}
$have = array_map( 'intval', $have );
sort( $have );
fx( 'pictures', count( $have ) );
if ( count( $have ) < 20 ) {
	echo "fewer than twenty pictures\n";
	exit( 1 );
}

$folders = array();
foreach ( array( 'Products', 'People', 'Places' ) as $name ) {
	$t = term_exists( $name, $tax );
	if ( ! $t ) {
		$t = wp_insert_term( $name, $tax );
	}
	if ( is_wp_error( $t ) ) {
		echo '  folder: ' . $t->get_error_message() . "\n";
		exit( 1 );
	}
	$folders[] = (int) ( is_array( $t ) ? $t['term_id'] : $t );
}
fx( 'folders', implode( ',', $folders ) );

$out       = vergeml_ai_describe_many( $have );
$described = 0;
foreach ( $out as $id => $d ) {
	if ( ! is_wp_error( $d ) ) {
		vergeml_ai_index_store( $id, $d, true );
		$described++;
	}
}
fx( 'described', $described );
if ( 20 !== $described ) {
	echo "described $described of 20\n";
	exit( 1 );
}

$filed = 0;
foreach ( array_slice( $have, 0, 6 ) as $i => $id ) {
	$already = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
	if ( ! is_wp_error( $already ) && $already ) {
		$filed++;
		continue;
	}
	$r = vergeml_autofile_file( $id, $folders[ $i % 3 ], 'accepted', 'looks like it' );
	if ( ! is_wp_error( $r ) ) {
		$filed++;
	} else {
		echo '  autofile: ' . $r->get_error_message() . "\n";
	}
}
fx( 'filed', $filed );
if ( 6 !== $filed ) {
	echo "filed $filed of 6\n";
	exit( 1 );
}

// A session with something in it, so the merge on the other side has
// something to lose: two turns, a draft naming the fixture's folders, a
// summary. The keys are 3.16.1's own (vergeml_guide_fresh at 348c841) and the
// draft goes through 3.16.1's own cleaner, so its folders have the shape a
// real session stores -- key, term_id, name, parent, classes, kinds. A draft
// of bare strings is not something 3.16.1 ever writes (and 4.0.0's Folders
// screen answers 500 on one, core/guide.php:1255 -- on the record, not this
// story's).
$session = vergeml_guide_fresh();
$session['turns'] = array(
	array( 'role' => 'user', 'text' => 'Products, People and Places' ),
	array( 'role' => 'assistant', 'text' => 'Three folders, then.' ),
);
$session['assistant_turns'] = 1;
$session['summary']         = 'A small library: products, people, places.';
$session['summary_key']     = 'fixture-3161';
$draft_in = array( 'folders' => array() );
foreach ( array( 'Products', 'People', 'Places' ) as $i => $name ) {
	$draft_in['folders'][] = array( 'key' => 'f' . ( $i + 1 ), 'term_id' => $folders[ $i ], 'name' => $name, 'parent' => '', 'classes' => array( strtolower( $name ) ), 'kinds' => array( 'photo' ) );
}
$session['draft'] = vergeml_guide_clean_draft( $draft_in );
if ( ! is_array( $session['draft'] ) || 3 !== count( $session['draft']['folders'] ) ) {
	echo "the cleaner did not accept the draft\n";
	exit( 1 );
}
vergeml_guide_save( $session );
$s = get_option( VERGEML_GUIDE_OPTION );
fx( 'session', is_array( $s ) ? 'v' . $s['version'] . ( isset( $s['tree'] ) ? ' with tree' : ' no tree key' ) : 'none' );

global $wpdb;
fx( 'batches', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}vergeml_librarian_batches" ) );
fx( 'moves', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}vergeml_librarian_moves" ) );
$lib = get_option( 'vergeml_librarian' );
fx( 'schema', is_array( $lib ) && isset( $lib['schema'] ) ? $lib['schema'] : 'none' );
fx( 'version opt', get_option( 'vergeml_version' ) );
