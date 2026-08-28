<?php
/**
 *  Test fixtures that look like a media library somebody actually uses.
 *
 *      wp eval-file tools/fixtures.php realistic --allow-root
 *      wp eval-file tools/fixtures.php scale --allow-root
 *      wp eval-file tools/fixtures.php wipe --allow-root
 *
 *  The first fixture on this box was twenty thousand attachments whose files did
 *  not exist. It was useless twice over: nothing looked like a real library, and
 *  every missing thumbnail fell through nginx's try_files into a full WordPress
 *  boot, so eighty tiles meant eighty page loads queued behind five PHP workers.
 *  A folder took eighteen seconds to open and none of it was the plugin -- the
 *  query underneath was eight milliseconds.
 *
 *  So: real files, real thumbnails, real names, a folder structure of the shape
 *  an agency actually builds.
 *
 *  'scale' still exists, because virtualisation and the four-queries-flat claim
 *  need thousands of folders to mean anything. It writes real files too.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mode = 'realistic';

foreach ( (array) $args as $arg ) {
	if ( in_array( $arg, array( 'realistic', 'scale', 'wipe', 'filebird' ), true ) ) {
		$mode = $arg;
	}
}

wp_set_current_user( 1 );

$taxonomy = 'media_category';

/* ------------------------------------------------------------------ tools */

/**
 *  A real JPEG, drawn rather than copied.
 *
 *  Every image is a different colour with its own name on it, so a grid of them
 *  is something you can actually navigate -- twenty identical grey squares tell
 *  you nothing about whether a filter worked.
 */
function vgml_fixture_image( $path, $label, $width, $height, $hue ) {

	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return false;
	}

	$im = imagecreatetruecolor( $width, $height );

	// A flat band of colour with a darker footer, so thumbnails stay legible
	// when they are 150px wide.
	list( $r, $g, $b ) = vgml_fixture_rgb( $hue, 0.55, 0.78 );
	imagefilledrectangle( $im, 0, 0, $width, $height, imagecolorallocate( $im, $r, $g, $b ) );

	list( $r2, $g2, $b2 ) = vgml_fixture_rgb( $hue, 0.60, 0.42 );
	imagefilledrectangle( $im, 0, (int) ( $height * 0.72 ), $width, $height, imagecolorallocate( $im, $r2, $g2, $b2 ) );

	$white = imagecolorallocate( $im, 255, 255, 255 );

	$text = substr( $label, 0, 44 );
	$size = 5;
	$tw   = imagefontwidth( $size ) * strlen( $text );

	imagestring( $im, $size, (int) ( ( $width - $tw ) / 2 ), (int) ( $height * 0.80 ), $text, $white );

	$dims = $width . ' x ' . $height;
	$dw   = imagefontwidth( 3 ) * strlen( $dims );
	imagestring( $im, 3, (int) ( ( $width - $dw ) / 2 ), (int) ( $height * 0.86 ), $dims, $white );

	$ok = imagejpeg( $im, $path, 82 );
	imagedestroy( $im );

	return $ok;
}

function vgml_fixture_rgb( $h, $s, $v ) {

	$i = floor( $h * 6 );
	$f = $h * 6 - $i;
	$p = $v * ( 1 - $s );
	$q = $v * ( 1 - $f * $s );
	$t = $v * ( 1 - ( 1 - $f ) * $s );

	switch ( $i % 6 ) {
		case 0: $r = $v; $g = $t; $b = $p; break;
		case 1: $r = $q; $g = $v; $b = $p; break;
		case 2: $r = $p; $g = $v; $b = $t; break;
		case 3: $r = $p; $g = $q; $b = $v; break;
		case 4: $r = $t; $g = $p; $b = $v; break;
		default: $r = $v; $g = $p; $b = $q;
	}

	return array( (int) ( $r * 255 ), (int) ( $g * 255 ), (int) ( $b * 255 ) );
}


function vgml_fixture_attach( $name, $label, $width, $height, $hue, $thumbs = true ) {

	$uploads = wp_upload_dir();

	if ( ! empty( $uploads['error'] ) ) {
		return 0;
	}

	$file = trailingslashit( $uploads['path'] ) . $name;

	if ( ! vgml_fixture_image( $file, $label, $width, $height, $hue ) ) {
		return 0;
	}

	$id = wp_insert_attachment( array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => $label,
		'post_content'   => '',
		'post_status'    => 'inherit',
	), $file );

	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}

	if ( $thumbs ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	} else {
		// Enough for the grid to render without generating five more files each.
		wp_update_attachment_metadata( $id, array( 'width' => $width, 'height' => $height, 'file' => _wp_relative_upload_path( $file ), 'sizes' => array() ) );
	}

	return (int) $id;
}


/**
 *  A document.
 *
 *  The seeded library was all JPEGs, so three things could not be tested on it:
 *  the AI layer's refusal to describe a non-image, the health report's exact
 *  md5 grouping -- whose honest demonstration is a re-uploaded document rather
 *  than a photograph -- and the mime-family breakdown in the stats snapshot.
 *
 *  The box did have PDFs. They were uploaded by hand, and they vanished.
 *  Anything a suite relies on has to be something a script puts there.
 *
 *  No wp_generate_attachment_metadata on purpose: nothing needs a thumbnail of
 *  a document, and asking for one is how seeding fails on a box without
 *  Imagick.
 */

function vgml_fixture_document( $name, $label, $seed = '' ) {

	$uploads = wp_upload_dir();

	if ( ! empty( $uploads['error'] ) ) {
		return 0;
	}

	$file = trailingslashit( $uploads['path'] ) . $name;

	/*
	 *  The seed is what decides whether two of these are the same file, and it
	 *  is separate from the label on purpose: a re-upload carries the same
	 *  bytes under a different name, which is exactly the case the duplicates
	 *  report exists to find. Without it every document here was byte-identical
	 *  and the report correctly called all three of them copies.
	 */
	$seed = '' === $seed ? $label : $seed;

	$objects = array(
		"1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
		"2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
		"3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>\nendobj\n",
	);

	// In the header, not among the objects: a comment is not an object, and
	// counting it as one would put the xref table one entry out.
	$pdf     = "%PDF-1.4\n% seed: " . $seed . "\n";
	$offsets = array();

	foreach ( $objects as $object ) {
		$offsets[] = strlen( $pdf );
		$pdf      .= $object;
	}

	$start = strlen( $pdf );
	$pdf  .= 'xref' . "\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";

	foreach ( $offsets as $offset ) {
		$pdf .= sprintf( "%010d 00000 n \n", $offset );
	}

	$pdf .= 'trailer' . "\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $start . "\n%%EOF\n";

	if ( false === file_put_contents( $file, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents
		return 0;
	}

	$id = wp_insert_attachment( array(
		'post_mime_type' => 'application/pdf',
		'post_title'     => $label,
		'post_content'   => '',
		'post_status'    => 'inherit',
	), $file );

	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}

	wp_update_attachment_metadata( $id, array( 'file' => _wp_relative_upload_path( $file ) ) );

	return (int) $id;
}


function vgml_fixture_folder( $name, $parent, $taxonomy, $colour = '' ) {

	$existing = term_exists( $name, $taxonomy, $parent );

	if ( $existing ) {
		return (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
	}

	$made = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );

	if ( is_wp_error( $made ) ) {
		return 0;
	}

	$id = (int) $made['term_id'];

	if ( $colour ) {
		update_term_meta( $id, 'vergeml_color', $colour );
	}

	return $id;
}


/**
 *  Everything this script has ever made, and nothing else.
 *
 *  Recognised by name, so a folder somebody created by hand while looking at the
 *  box is never in the list. Deleting somebody's real folders to tidy up a test
 *  fixture is not a trade worth making.
 */
function vgml_fixture_wipe( $taxonomy ) {

	$removed = array( 'files' => 0, 'folders' => 0 );

	$patterns = array( '/^image-\d+$/', '/^image-\d+\.jpg$/', '/^vgml-fx-/' );

	$files = get_posts( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	foreach ( $files as $id ) {

		$name = get_post_field( 'post_name', $id );
		$file = basename( (string) get_attached_file( $id ) );

		$mine = false;

		/*
		 *  The suites seed attachments titled "zz something" -- bare post rows
		 *  with an image mime and no file behind them -- and only some of them
		 *  clean up after themselves. The terms were already swept below by
		 *  this prefix; the files were not, so they accumulated at about
		 *  nineteen per full battery.
		 *
		 *  They are not harmless litter. `vergeml_ai_pending( 'missing-alt' )`
		 *  selects on the stored mime, so it hands them to a describer that
		 *  refuses anything wp_attachment_is_image() calls false -- the backlog
		 *  never drains, and every count drawn over the library drifts with it.
		 */
		if ( 0 === strpos( (string) get_post_field( 'post_title', $id ), 'zz ' ) ) {
			$mine = true;
		}

		foreach ( $patterns as $pattern ) {

			if ( $mine ) {
				break;
			}

			if ( preg_match( $pattern, $name ) || preg_match( $pattern, $file ) ) {
				$mine = true;
				break;
			}
		}

		if ( $mine ) {
			wp_delete_attachment( $id, true );
			$removed['files']++;
		}
	}

	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

	foreach ( (array) $terms as $term ) {
		if ( preg_match( '/^(Folder|FB Folder) \d+$/', $term->name ) || preg_match( '/^(zz|ord|probe|pf)/', $term->name ) ) {
			wp_delete_term( $term->term_id, $taxonomy );
			$removed['folders']++;
		}
	}

	return $removed;
}


/* ------------------------------------------------------------------ modes */

wp_defer_term_counting( true );

if ( 'wipe' === $mode ) {

	$gone = vgml_fixture_wipe( $taxonomy );
	printf( "wiped %d files and %d folders\n", $gone['files'], $gone['folders'] );

} elseif ( 'realistic' === $mode ) {

	$gone = vgml_fixture_wipe( $taxonomy );
	printf( "cleared %d old files and %d old folders\n", $gone['files'], $gone['folders'] );

	/*
	 *  The shape an agency actually builds: a few top-level areas, two levels
	 *  under the busy ones, and a long tail of things filed nowhere.
	 */
	$tree = array(
		'Brand'    => array( 'colour' => '#3858e9', 'children' => array( 'Logos', 'Icons', 'Typography' ) ),
		'Products' => array( 'colour' => '#2f8f45', 'children' => array( 'Autumn 2026', 'Spring 2026', 'Packshots' ) ),
		'Blog'     => array( 'colour' => '#e07d10', 'children' => array( 'Headers', 'Inline' ) ),
		'Team'     => array( 'colour' => '#7c3aed', 'children' => array( 'Portraits', 'Office' ) ),
		'Events'   => array( 'colour' => '#0f7c8c', 'children' => array( 'Conference 2026', 'Launch Night' ) ),
		'Press'    => array( 'colour' => '#a4286a', 'children' => array() ),
		'Archive'  => array( 'colour' => '', 'children' => array( '2025', '2024' ) ),
	);

	$subjects = array(
		'Logos'           => array( 'wordmark-primary', 'wordmark-reversed', 'monogram-dark', 'monogram-light', 'lockup-horizontal' ),
		'Icons'           => array( 'icon-basket', 'icon-account', 'icon-search', 'icon-delivery' ),
		'Typography'      => array( 'type-specimen-display', 'type-specimen-body' ),
		'Autumn 2026'     => array( 'navy-leather-boots-01', 'navy-leather-boots-02', 'wool-coat-charcoal', 'wool-coat-detail', 'scarf-oatmeal', 'gloves-tan' ),
		'Spring 2026'     => array( 'linen-shirt-white', 'linen-shirt-folded', 'canvas-tote-natural', 'sandals-cork' ),
		'Packshots'       => array( 'packshot-boots-side', 'packshot-boots-top', 'packshot-coat-front', 'packshot-tote-flat', 'packshot-shirt-hanger' ),
		'Headers'         => array( 'header-how-we-choose-leather', 'header-inside-the-workshop', 'header-autumn-lookbook' ),
		'Inline'          => array( 'inline-stitching-detail', 'inline-leather-swatches', 'inline-workshop-bench', 'inline-pattern-cutting' ),
		'Portraits'       => array( 'portrait-anna-lead-designer', 'portrait-marcus-workshop', 'portrait-priya-studio' ),
		'Office'          => array( 'office-studio-wide', 'office-meeting-room' ),
		'Conference 2026' => array( 'conf-keynote-stage', 'conf-audience', 'conf-stand-detail', 'conf-team-photo' ),
		'Launch Night'    => array( 'launch-window-display', 'launch-guests', 'launch-cocktails' ),
		'Press'           => array( 'press-kit-cover', 'press-founder-quote-card', 'press-product-grid' ),
		'2025'            => array( 'archive-2025-campaign', 'archive-2025-catalogue' ),
		'2024'            => array( 'archive-2024-campaign' ),
	);

	$sizes = array(
		array( 1600, 1067 ),
		array( 1200, 800 ),
		array( 1000, 1000 ),
		array( 900, 1350 ),
	);

	$folders = array();
	$hue     = 0.02;
	$made    = 0;

	foreach ( $tree as $top => $spec ) {

		$parent_id = vgml_fixture_folder( $top, 0, $taxonomy, $spec['colour'] );
		$folders[ $top ] = $parent_id;

		$targets = $spec['children'] ? $spec['children'] : array( $top );

		foreach ( $spec['children'] as $child ) {
			$folders[ $child ] = vgml_fixture_folder( $child, $parent_id, $taxonomy );
		}

		foreach ( $targets as $target ) {

			$names = isset( $subjects[ $target ] ) ? $subjects[ $target ] : array();

			foreach ( $names as $i => $subject ) {

				$size = $sizes[ ( $made + $i ) % count( $sizes ) ];
				$hue  = fmod( $hue + 0.137, 1.0 );

				$id = vgml_fixture_attach(
					'vgml-fx-' . $subject . '.jpg',
					ucwords( str_replace( '-', ' ', $subject ) ),
					$size[0],
					$size[1],
					$hue
				);

				if ( $id ) {
					wp_set_object_terms( $id, array( (int) $folders[ $target ] ), $taxonomy, false );
					$made++;
				}
			}
		}
	}

	/*
	 *  Files in more than one folder, because that is the thing this plugin can
	 *  do that FileBird and Folders cannot -- and a demo library where every file
	 *  sits in exactly one folder never shows it.
	 */
	$crossfiled = 0;

	foreach ( array( 'packshot-boots-side', 'header-autumn-lookbook', 'portrait-anna-lead-designer' ) as $subject ) {

		/*
		 *  Found by title, not by slug. WordPress derives an attachment's slug
		 *  from its title rather than its filename, so looking for the filename
		 *  matched nothing and every cross-filed image quietly was not.
		 */
		$found = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'title'          => ucwords( str_replace( '-', ' ', $subject ) ),
		) );

		if ( $found && isset( $folders['Press'] ) ) {
			wp_set_object_terms( (int) $found[0], array( (int) $folders['Press'] ), $taxonomy, true );
			$crossfiled++;
		}
	}

	// A realistic amount of stuff nobody has filed yet.
	$unfiled = 0;

	foreach ( array( 'IMG_4821', 'IMG_4822', 'IMG_4823', 'screenshot-2026-08-14', 'scan-invoice-0912', 'DSC_0071', 'DSC_0072' ) as $i => $subject ) {
		$size = $sizes[ $i % count( $sizes ) ];
		$hue  = fmod( $hue + 0.137, 1.0 );
		if ( vgml_fixture_attach( 'vgml-fx-' . $subject . '.jpg', $subject, $size[0], $size[1], $hue ) ) {
			$unfiled++;
		}
	}

	/*
	 *  Documents, including the same one twice.
	 *
	 *  A library of nothing but photographs cannot exercise the parts that care
	 *  what a file is: describing refuses non-images, the duplicates report's
	 *  exact md5 grouping is best demonstrated on a re-uploaded document, and
	 *  the stats snapshot counts mime families. The pair is byte-identical on
	 *  purpose -- that is the finding the health page exists to make.
	 */
	$documents = 0;

	foreach ( array(
		array( 'vgml-fx-invoice-0912.pdf', 'Invoice 0912', 'invoice-0912' ),
		// Same bytes, different name: the re-upload the report should catch.
		array( 'vgml-fx-invoice-0912-1.pdf', 'Invoice 0912 (re-uploaded)', 'invoice-0912' ),
		// And one that is genuinely its own document, so the report has
		// something to correctly leave alone.
		array( 'vgml-fx-brand-guidelines.pdf', 'Brand guidelines', 'brand-guidelines' ),
	) as $document ) {
		if ( vgml_fixture_document( $document[0], $document[1], $document[2] ) ) {
			$documents++;
		}
	}

	printf( "built %d folders, %d filed images, %d cross-filed, %d unfiled, %d documents\n",
		count( $folders ), $made, $crossfiled, $unfiled, $documents );

} elseif ( 'filebird' === $mode ) {

	/*
	 *  A FileBird library to import from, in FileBird's own tables.
	 *
	 *  The importer tests are only worth anything against data this plugin did
	 *  not write, and they join their rows against wp_posts -- so a fixture whose
	 *  attachment ids no longer exist imports the folders and none of the files,
	 *  correctly, and reads as a broken importer. Rebuilt from whatever is in the
	 *  library now.
	 */
	global $wpdb;

	$folders_table = $wpdb->prefix . 'fbv';
	$links_table   = $wpdb->prefix . 'fbv_attachment_folder';

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- another plugin's tables, in a fixture script.

	$wpdb->query( "CREATE TABLE IF NOT EXISTS {$folders_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL DEFAULT '',
		parent bigint(20) unsigned NOT NULL DEFAULT 0,
		type tinyint(1) NOT NULL DEFAULT 0,
		ord int(11) NOT NULL DEFAULT 0,
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY (id)
	)" );

	$wpdb->query( "CREATE TABLE IF NOT EXISTS {$links_table} (
		folder_id bigint(20) unsigned NOT NULL DEFAULT 0,
		attachment_id bigint(20) unsigned NOT NULL DEFAULT 0
	)" );

	$wpdb->query( "TRUNCATE TABLE {$folders_table}" );
	$wpdb->query( "TRUNCATE TABLE {$links_table}" );

	$shape = array(
		'Client Work'   => array( 'Acme Rebrand', 'Northwind Site' ),
		'Stock Photos'  => array( 'Interiors', 'Landscapes' ),
		'Screenshots'   => array(),
		'Old Campaigns' => array( '2024', '2023' ),
	);

	$made = array();

	foreach ( $shape as $top => $children ) {

		$wpdb->insert( $folders_table, array( 'name' => 'FB ' . $top, 'parent' => 0, 'type' => 0, 'ord' => count( $made ) ) );
		$parent = (int) $wpdb->insert_id;
		$made[] = $parent;

		foreach ( $children as $i => $child ) {
			$wpdb->insert( $folders_table, array( 'name' => 'FB ' . $child, 'parent' => $parent, 'type' => 0, 'ord' => $i ) );
			$made[] = (int) $wpdb->insert_id;
		}
	}

	$files = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids' ) );

	$linked = 0;

	foreach ( $files as $i => $id ) {
		// FileBird holds a file in exactly one folder; that is the model.
		$wpdb->insert( $links_table, array( 'folder_id' => $made[ $i % count( $made ) ], 'attachment_id' => (int) $id ) );
		$linked++;
	}

	// phpcs:enable

	printf( "FileBird fixture: %d folders, %d files linked
", count( $made ), $linked );

} elseif ( 'scale' === $mode ) {

	/*
	 *  Thousands of folders, for the claims that only mean something at scale --
	 *  windowed rendering and four queries flat. Files are real but thumbnails
	 *  are skipped: five extra JPEGs per image is twenty thousand files nobody
	 *  looks at, and the grid renders from the full size perfectly well.
	 */
	$folders = 800;
	$files   = 1200;

	$ids = array();

	for ( $i = 0; $i < $folders; $i++ ) {
		$parent = ( $i > 0 && 0 === $i % 7 ) ? $ids[ (int) ( $i / 7 ) - 1 ] : 0;
		$ids[]  = vgml_fixture_folder( 'Folder ' . $i, $parent, $taxonomy );
	}

	$made = 0;

	for ( $i = 0; $i < $files; $i++ ) {

		$id = vgml_fixture_attach( 'vgml-fx-scale-' . $i . '.jpg', 'Scale ' . $i, 800, 600, fmod( $i * 0.137, 1.0 ), false );

		if ( $id ) {
			wp_set_object_terms( $id, array( $ids[ $i % count( $ids ) ] ), $taxonomy, false );
			$made++;
		}
	}

	printf( "built %d folders and %d files\n", count( $ids ), $made );
}

wp_defer_term_counting( false );

$total = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );

printf( "\nlibrary now: %d folders, %d attachments\n\n",
	is_wp_error( $total ) ? 0 : count( $total ),
	(int) wp_count_posts( 'attachment' )->inherit
);
