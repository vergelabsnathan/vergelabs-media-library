<?php
/**
 *  Look-alikes: keep this one, keep both.
 *
 *  The Duplicates screen has two lists. Byte-identical copies are deleted by
 *  core/health-delete.php, because deleting one of those loses nothing. The
 *  other list -- files a 64-bit picture hash thinks are alike -- had no
 *  follow-up at all: a guess never gets a delete button, and so the person
 *  was left with twenty-two sets and nothing to press.
 *
 *  This file is the follow-up, and it is built on two things that already
 *  exist rather than on a delete:
 *
 *    - **Set aside** (core/quarantine.php). "Keep this one" does not delete
 *      the other; it marks it. The file stays on disk and at its URL for
 *      thirty days, is out of the media library, and comes back with one
 *      press. A guess that turns out wrong costs a click, not a picture.
 *
 *    - **The usage scan** (core/smart-folders.php). Nothing is set aside
 *      while a page shows it unless that page is rewritten to the kept
 *      file first, and "rewritten" is not taken on trust: after the rewrite
 *      the scan's own extractor reads the page again, and only when it no
 *      longer finds the file is the file set aside. A use the rewrite cannot
 *      reach (a serialised builder layout whose text length would change, or
 *      the site's own settings) leaves the file where it is, and the answer
 *      says which use and where.
 *
 *  Unlike core/health-delete.php's repoint, the two files here are not the
 *  same bytes: their generated sizes differ. Every URL of the file that goes
 *  is mapped to the kept file's nearest size by width, so a page that showed
 *  a 1024-wide copy shows the kept file's 1024-wide copy, not a 404.
 *
 *  "Keep both" writes the pair into a kept list the report skips from then
 *  on. Both actions keep an undo for a day; undoing puts the page content
 *  back only while it still reads as this file wrote it.
 *
 * @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Pair keys retired by Keep both: array( 'a-b' => time ). */
const VERGEML_HEALTH_KEPT = 'vergeml_health_kept';

/** The last keeps, by token, for a day. */
const VERGEML_HEALTH_KEEP_UNDO = 'vergeml_health_keep_undo';


/* ----------------------------------------------------------------- the pair */

function vergeml_health_pair_key( $a, $b ) {

	$a = (int) $a;
	$b = (int) $b;

	return $a < $b ? $a . '-' . $b : $b . '-' . $a;
}


/**
 *  Whether the scan would call these two alike: the same bytes, or picture
 *  hashes within the loose band. Recomputed from the stored hashes, never
 *  taken from the request -- whatever the browser names, only files the scan
 *  itself pairs with the kept one can be set aside by this route.
 */
function vergeml_health_alike( $a, $b ) {

	$ha = (string) get_post_meta( (int) $a, VERGEML_META_HASH, true );
	$hb = (string) get_post_meta( (int) $b, VERGEML_META_HASH, true );

	if ( 0 !== strpos( $ha, 'md5:' ) || 0 !== strpos( $hb, 'md5:' ) ) {
		return false;
	}

	if ( substr( $ha, 4, 32 ) === substr( $hb, 4, 32 ) ) {
		return true;
	}

	$da = 59 === strlen( $ha ) ? substr( $ha, 43 ) : '';
	$db = 59 === strlen( $hb ) ? substr( $hb, 43 ) : '';

	if ( ! vergeml_health_hash_usable( $da ) || ! vergeml_health_hash_usable( $db ) ) {
		return false;
	}

	return vergeml_health_hamming( $da, $db ) <= VERGEML_HEALTH_LOOSE;
}


/* ------------------------------------------------------------- kept pairs */

function vergeml_health_kept_pairs() {

	$kept = get_option( VERGEML_HEALTH_KEPT, array() );

	return is_array( $kept ) ? $kept : array();
}


/**
 *  Retire every pair among these ids, or bring them back.
 *
 *  A set of four is six pairs; all six go, so the set cannot re-form from a
 *  subset. Returns how many pair keys changed.
 */
function vergeml_health_retire( $ids, $undo = false ) {

	$ids  = array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
	$kept = vergeml_health_kept_pairs();
	$n    = 0;

	$count = count( $ids );

	for ( $i = 0; $i < $count; $i++ ) {
		for ( $j = $i + 1; $j < $count; $j++ ) {

			$key = vergeml_health_pair_key( $ids[ $i ], $ids[ $j ] );

			if ( $undo ) {
				if ( isset( $kept[ $key ] ) ) {
					unset( $kept[ $key ] );
					$n++;
				}
			} elseif ( ! isset( $kept[ $key ] ) ) {
				$kept[ $key ] = time();
				$n++;
			}
		}
	}

	if ( $n ) {
		update_option( VERGEML_HEALTH_KEPT, $kept, false );
	}

	return $n;
}


/** Ids set aside, so the look-alike list can leave them out. */
function vergeml_health_aside_ids() {

	global $wpdb;

	if ( ! defined( 'VERGEML_QUARANTINE_META' ) ) {
		return array();
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one indexed read on a key this plugin owns.
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
		VERGEML_QUARANTINE_META
	) );
	// phpcs:enable

	return array_map( 'intval', (array) $ids );
}


/* ------------------------------------------------------------- where used */

/** Whether the usage scan has run, so "used nowhere" means something. */
function vergeml_health_uses_scanned() {

	return function_exists( 'vergeml_smart_scan_state' )
		&& ! empty( vergeml_smart_scan_state()['finished'] );
}


/**
 *  Where a file is used, as the attachment screen's "Used in" field words
 *  it: a post with its title, type and edit link, or id 0 for the site's
 *  own settings (the logo, widgets, the customiser).
 *
 * @return array[] Each array( id, title, type, edit ). Empty when unused or unscanned.
 */
function vergeml_health_uses_of( $attachment_id ) {

	if ( ! vergeml_health_uses_scanned() ) {
		return array();
	}

	$raw = (string) get_post_meta( (int) $attachment_id, VERGEML_META_USED_IN, true );

	if ( '' === $raw ) {
		return array();
	}

	$out = array();

	foreach ( array_unique( array_map( 'intval', explode( ',', $raw ) ) ) as $source ) {

		if ( 0 === $source ) {
			$out[] = array(
				'id'    => 0,
				'title' => __( 'Site settings', 'vergelabs-media-library' ),
				'type'  => '',
				'edit'  => '',
			);
			continue;
		}

		$title = get_the_title( $source );

		if ( '' === $title ) {
			continue; // Deleted since the scan.
		}

		$type  = get_post_type_object( get_post_type( $source ) );
		$edit  = get_edit_post_link( $source, 'raw' );
		$out[] = array(
			'id'    => $source,
			'title' => $title,
			'type'  => ( $type && isset( $type->labels->singular_name ) ) ? $type->labels->singular_name : '',
			'edit'  => $edit ? $edit : '',
		);
	}

	return $out;
}


/* ------------------------------------------------------------ the rewrite */

/**
 *  Every URL a file answers to, with its width: the full file and each
 *  generated size. The width is what the mapping below matches on.
 */
function vergeml_health_size_urls( $attachment_id ) {

	$attachment_id = (int) $attachment_id;
	$full          = wp_get_attachment_url( $attachment_id );

	if ( ! is_string( $full ) || '' === $full ) {
		return array();
	}

	$meta = wp_get_attachment_metadata( $attachment_id );
	$out  = array(
		array( 'url' => $full, 'width' => isset( $meta['width'] ) ? (int) $meta['width'] : 0, 'full' => true ),
	);

	$dir = trailingslashit( dirname( $full ) );

	if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
		foreach ( (array) $meta['sizes'] as $size ) {
			if ( ! empty( $size['file'] ) ) {
				$out[] = array( 'url' => $dir . $size['file'], 'width' => isset( $size['width'] ) ? (int) $size['width'] : 0, 'full' => false );
			}
		}
	}

	// The original of a scaled upload, when WordPress kept one.
	if ( is_array( $meta ) && ! empty( $meta['original_image'] ) ) {
		$out[] = array( 'url' => $dir . $meta['original_image'], 'width' => isset( $meta['width'] ) ? (int) $meta['width'] : 0, 'full' => true );
	}

	return $out;
}


/**
 *  Old URL => new URL. The full file maps to the full file; a generated size
 *  maps to the kept file's size nearest in width, falling back to its full
 *  file. Longest old URL first, so "photo-300x200.jpg" is replaced before
 *  "photo.jpg" could eat its front.
 */
function vergeml_health_url_map( $from, $to ) {

	$olds = vergeml_health_size_urls( $from );
	$news = vergeml_health_size_urls( $to );

	if ( ! $olds || ! $news ) {
		return array();
	}

	$new_full = $news[0]['url'];
	$map      = array();

	foreach ( $olds as $old ) {

		if ( $old['full'] ) {
			$map[ $old['url'] ] = $new_full;
			continue;
		}

		$best = $new_full;
		$gap  = null;

		foreach ( $news as $candidate ) {
			$d = abs( (int) $candidate['width'] - (int) $old['width'] );
			if ( null === $gap || $d < $gap ) {
				$gap  = $d;
				$best = $candidate['url'];
			}
		}

		$map[ $old['url'] ] = $best;
	}

	uksort( $map, function ( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	} );

	return $map;
}


/**
 *  One text with every reference to the old file moved to the kept one: the
 *  URLs in both their plain and JSON-escaped forms, the block attribute id,
 *  the image class.
 */
function vergeml_health_rewrite_text( $text, $map, $from, $to ) {

	$text = (string) $text;

	foreach ( $map as $old => $new ) {
		if ( false !== strpos( $text, $old ) ) {
			$text = str_replace( $old, $new, $text );
		}
		$old_json = str_replace( '/', '\/', $old );
		if ( false !== strpos( $text, $old_json ) ) {
			$text = str_replace( $old_json, str_replace( '/', '\/', $new ), $text );
		}
	}

	return vergeml_health_repoint_ids( $text, $from, $to );
}


/**
 *  Whether a post still refers to the file, by the scan's own reading of it:
 *  its content, every meta value, its featured image, and the file's parent.
 */
function vergeml_health_still_uses( $source_id, $attachment_id ) {

	global $wpdb;

	$source_id     = (int) $source_id;
	$attachment_id = (int) $attachment_id;

	clean_post_cache( $source_id );

	$blob = (string) get_post_field( 'post_content', $source_id );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reading back what was just written, uncached on purpose.
	$values = $wpdb->get_col( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key <> '_thumbnail_id'",
		$source_id
	) );
	// phpcs:enable

	foreach ( (array) $values as $value ) {
		$blob .= "\n" . $value;
	}

	// With its slash, as the scan passes it: the extractor takes what follows
	// the base as the file's path, and a leading slash resolves to nothing.
	$uploads = wp_get_upload_dir();
	$refs    = vergeml_refs_in( $blob, trailingslashit( $uploads['baseurl'] ) );

	if ( in_array( $attachment_id, array_map( 'intval', (array) $refs ), true ) ) {
		return true;
	}

	if ( (int) get_post_thumbnail_id( $source_id ) === $attachment_id ) {
		return true;
	}

	return (int) get_post_field( 'post_parent', $attachment_id ) === $source_id;
}


/**
 *  Rewrite one post from the file that goes to the file that stays, and say
 *  whether the scan would now call it rewritten. Everything changed goes into
 *  $record with what it was, so undo can put it back.
 */
function vergeml_health_rewrite_source( $source_id, $from, $to, $map, &$record ) {

	global $wpdb;

	$source_id = (int) $source_id;
	$from      = (int) $from;
	$to        = (int) $to;

	if ( $source_id <= 0 ) {
		return false; // The site's own settings are not rewritten.
	}

	$post = get_post( $source_id );

	if ( ! $post ) {
		return false;
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the same direct writes core/health-delete.php makes, recorded for undo.

	$content = vergeml_health_rewrite_text( $post->post_content, $map, $from, $to );

	if ( $content !== $post->post_content ) {
		$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $source_id ) );
		clean_post_cache( $source_id );
		$record['posts'][] = array( 'id' => $source_id, 'before' => $post->post_content, 'after' => $content );
	}

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
		$source_id
	) );

	foreach ( (array) $rows as $row ) {

		if ( '_thumbnail_id' === $row->meta_key ) {
			if ( (int) $row->meta_value === $from ) {
				update_post_meta( $source_id, '_thumbnail_id', $to );
				$record['thumbs'][] = array( 'post_id' => $source_id, 'from' => $from, 'to' => $to );
			}
			continue;
		}

		$value = vergeml_health_rewrite_text( $row->meta_value, $map, $from, $to );

		if ( $value === (string) $row->meta_value ) {
			continue;
		}

		// Serialised data carries string lengths; a URL of another length
		// would corrupt it. The row keeps the old file, and the file stays.
		if ( is_serialized( (string) $row->meta_value ) && strlen( $value ) !== strlen( (string) $row->meta_value ) ) {
			continue;
		}

		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $value ), array( 'meta_id' => (int) $row->meta_id ) );
		$record['metas'][] = array( 'meta_id' => (int) $row->meta_id, 'post_id' => $source_id, 'before' => (string) $row->meta_value, 'after' => $value );
	}

	wp_cache_delete( $source_id, 'post_meta' );

	// Attached to this post: the kept file takes the place, the other floats free.
	if ( (int) get_post_field( 'post_parent', $from ) === $source_id ) {
		$wpdb->update( $wpdb->posts, array( 'post_parent' => 0 ), array( 'ID' => $from ) );
		clean_post_cache( $from );
		$record['parents'][] = array( 'id' => $from, 'before' => $source_id );

		if ( 0 === (int) get_post_field( 'post_parent', $to ) ) {
			$wpdb->update( $wpdb->posts, array( 'post_parent' => $source_id ), array( 'ID' => $to ) );
			clean_post_cache( $to );
			$record['parents'][] = array( 'id' => $to, 'before' => 0 );
		}
	}

	// phpcs:enable

	return ! vergeml_health_still_uses( $source_id, $from );
}


/* ------------------------------------------------------------- the actions */

function vergeml_health_keep_undo_records() {

	$records = get_option( VERGEML_HEALTH_KEEP_UNDO, array() );

	if ( ! is_array( $records ) ) {
		return array();
	}

	// A day, and the last twenty: a record carries page content.
	foreach ( $records as $token => $record ) {
		if ( empty( $record['until'] ) || time() > (int) $record['until'] ) {
			unset( $records[ $token ] );
		}
	}

	return array_slice( $records, -20, 20, true );
}


function vergeml_health_keep_undo_save( $records ) {

	if ( $records ) {
		update_option( VERGEML_HEALTH_KEEP_UNDO, $records, false );
	} else {
		delete_option( VERGEML_HEALTH_KEEP_UNDO );
	}
}


/** The used-in mark on a file, with what it was recorded for undo. */
function vergeml_health_mark_uses( $attachment_id, $sources, &$record ) {

	$attachment_id = (int) $attachment_id;

	$record['used'][] = array(
		'id'      => $attachment_id,
		'used_in' => (string) get_post_meta( $attachment_id, VERGEML_META_USED_IN, true ),
		'unused'  => (string) get_post_meta( $attachment_id, VERGEML_META_UNUSED, true ),
	);

	$sources = array_values( array_unique( array_map( 'intval', (array) $sources ) ) );

	update_post_meta( $attachment_id, VERGEML_META_UNUSED, $sources ? '0' : '1' );

	if ( $sources ) {
		update_post_meta( $attachment_id, VERGEML_META_USED_IN, implode( ',', $sources ) );
	} else {
		delete_post_meta( $attachment_id, VERGEML_META_USED_IN );
	}
}


/**
 *  Keep one file of a look-alike set: rewrite every page that shows the
 *  others to it, set the others aside, and keep a record for a day.
 *
 * @param int   $keep The file that stays.
 * @param int[] $drop The files that go aside.
 * @return array|WP_Error What happened, or why it refused.
 */
function vergeml_health_keep( $keep, $drop ) {

	$keep = (int) $keep;
	$drop = array_values( array_unique( array_filter( array_map( 'intval', (array) $drop ) ) ) );

	if ( ! $keep || ! $drop ) {
		return new WP_Error( 'nothing', __( 'Nothing to set aside.', 'vergelabs-media-library' ) );
	}

	if ( in_array( $keep, $drop, true ) ) {
		return new WP_Error( 'keep_in_drop', __( 'The file to keep cannot also be one of the files to set aside.', 'vergelabs-media-library' ) );
	}

	if ( ! function_exists( 'vergeml_quarantine_add' ) ) {
		return new WP_Error( 'no_aside', __( 'Set aside is not available on this site.', 'vergelabs-media-library' ) );
	}

	/*
	 *  Not without the usage scan: which pages to rewrite comes from it, and
	 *  without it every file reads as used nowhere -- a file on the home page
	 *  would be set aside with nothing rewritten.
	 */
	if ( ! vergeml_health_uses_scanned() ) {
		return new WP_Error( 'unscanned', __( 'Usage not scanned. Which pages show a picture decides what is rewritten; nothing was set aside.', 'vergelabs-media-library' ) );
	}

	if ( 'attachment' !== get_post_type( $keep ) ) {
		return new WP_Error( 'not_a_file', __( 'That is not a media file.', 'vergelabs-media-library' ) );
	}

	foreach ( $drop as $id ) {
		if ( ! vergeml_health_alike( $keep, $id ) ) {
			return new WP_Error(
				'not_alike',
				sprintf(
					/* translators: %d: attachment id. */
					__( 'File %d is not a look-alike of the one being kept. Nothing was set aside.', 'vergelabs-media-library' ),
					$id
				)
			);
		}
	}

	$keep_name = wp_basename( (string) get_attached_file( $keep ) );
	$token     = wp_generate_password( 12, false );

	$record = array(
		'token'   => $token,
		'time'    => time(),
		'until'   => time() + DAY_IN_SECONDS,
		'keep'    => $keep,
		'aside'   => array(),
		'posts'   => array(),
		'metas'   => array(),
		'thumbs'  => array(),
		'parents' => array(),
		'used'    => array(),
	);

	$aside     = array();
	$stays     = array();
	$pages     = array();
	$keep_uses = array();

	foreach ( vergeml_health_uses_of( $keep ) as $use ) {
		$keep_uses[] = (int) $use['id'];
	}

	foreach ( $drop as $id ) {

		$name = wp_basename( (string) get_attached_file( $id ) );
		$map  = vergeml_health_url_map( $id, $keep );
		$left = array();

		foreach ( vergeml_health_uses_of( $id ) as $use ) {

			$done = $map && vergeml_health_rewrite_source( (int) $use['id'], $id, $keep, $map, $record );

			if ( $done ) {
				$pages[ (int) $use['id'] ] = $use;
				$keep_uses[]               = (int) $use['id'];
			} else {
				$left[] = $use;
			}
		}

		if ( $left ) {
			// Still shown somewhere the rewrite could not reach: it stays.
			$stays[] = array( 'id' => $id, 'name' => $name, 'left' => $left );
			if ( count( $left ) < count( vergeml_health_uses_of( $id ) ) ) {
				$left_ids = array();
				foreach ( $left as $use ) {
					$left_ids[] = (int) $use['id'];
				}
				vergeml_health_mark_uses( $id, $left_ids, $record );
			}
			continue;
		}

		vergeml_quarantine_add(
			$id,
			sprintf(
				/* translators: 1: the kept file's name, 2: a date. */
				__( 'Look-alike of %1$s, kept on %2$s', 'vergelabs-media-library' ),
				$keep_name,
				wp_date( 'j F Y' )
			)
		);

		vergeml_health_mark_uses( $id, array(), $record );

		$record['aside'][] = $id;
		$aside[]           = array( 'id' => $id, 'name' => $name );
	}

	if ( $pages ) {
		vergeml_health_mark_uses( $keep, $keep_uses, $record );
	}

	$records           = vergeml_health_keep_undo_records();
	$records[ $token ] = $record;
	vergeml_health_keep_undo_save( $records );

	return array(
		'kept'      => $keep,
		'kept_name' => $keep_name,
		'aside'     => $aside,
		'stays'     => $stays,
		'pages'     => array_values( $pages ),
		'undo'      => array( 'token' => $token, 'until' => (int) $record['until'] ),
		'line'      => vergeml_health_keep_line( $keep_name, $aside, $stays, array_values( $pages ) ),
	);
}


/** The one line that stands where the set was. */
function vergeml_health_keep_line( $keep_name, $aside, $stays, $pages ) {

	$parts = array(
		/* translators: %s: a file name. */
		sprintf( __( '%s kept', 'vergelabs-media-library' ), $keep_name ),
	);

	if ( $pages ) {
		$titles = array();
		foreach ( $pages as $page ) {
			$titles[] = $page['title'];
		}
		if ( count( $titles ) <= 2 ) {
			$parts[] = sprintf(
				/* translators: %s: one or two page titles. */
				_n( '%s now shows it', '%s now show it', count( $titles ), 'vergelabs-media-library' ),
				implode( __( ' and ', 'vergelabs-media-library' ), $titles )
			);
		} else {
			/* translators: %s: how many pages. */
			$parts[] = sprintf( __( '%s pages now show it', 'vergelabs-media-library' ), number_format_i18n( count( $titles ) ) );
		}
	}

	if ( $aside ) {
		$names = array();
		foreach ( $aside as $file ) {
			$names[] = $file['name'];
		}
		$parts[] = sprintf(
			/* translators: 1: file names, 2: how many days. */
			__( '%1$s set aside for %2$d days', 'vergelabs-media-library' ),
			count( $names ) <= 3
				? implode( ', ', $names )
				/* translators: %s: how many files. */
				: sprintf( __( '%s files', 'vergelabs-media-library' ), number_format_i18n( count( $names ) ) ),
			defined( 'VERGEML_QUARANTINE_DAYS' ) ? VERGEML_QUARANTINE_DAYS : 30
		);
	}

	foreach ( $stays as $stay ) {
		$where = array();
		foreach ( $stay['left'] as $use ) {
			$where[] = $use['title'];
		}
		$parts[] = sprintf(
			/* translators: 1: a file name, 2: how many uses, 3: where. */
			_n( '%1$s stays: %2$s use in %3$s could not be rewritten', '%1$s stays: %2$s uses in %3$s could not be rewritten', count( $where ), 'vergelabs-media-library' ),
			$stay['name'],
			number_format_i18n( count( $where ) ),
			implode( ', ', $where )
		);
	}

	return implode( ' · ', $parts );
}


/**
 *  Put a keep back: the files out of set-aside, the pages as they were --
 *  while they still read as this file wrote them.
 */
function vergeml_health_keep_undo( $token ) {

	global $wpdb;

	$records = vergeml_health_keep_undo_records();
	$token   = (string) $token;

	if ( '' === $token || empty( $records[ $token ] ) ) {
		return new WP_Error( 'gone', __( 'That undo has expired.', 'vergelabs-media-library' ) );
	}

	$record = $records[ $token ];
	$back   = 0;
	$kept   = 0;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the inverse of the writes above.

	foreach ( (array) $record['posts'] as $change ) {
		clean_post_cache( (int) $change['id'] );
		if ( (string) get_post_field( 'post_content', (int) $change['id'] ) === (string) $change['after'] ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $change['before'] ), array( 'ID' => (int) $change['id'] ) );
			clean_post_cache( (int) $change['id'] );
			$back++;
		} else {
			$kept++; // Edited since; left as the person left it.
		}
	}

	foreach ( (array) $record['metas'] as $change ) {
		$now = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", (int) $change['meta_id'] ) );
		if ( (string) $now === (string) $change['after'] ) {
			$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $change['before'] ), array( 'meta_id' => (int) $change['meta_id'] ) );
			wp_cache_delete( (int) $change['post_id'], 'post_meta' );
		}
	}

	foreach ( (array) $record['thumbs'] as $change ) {
		if ( (int) get_post_thumbnail_id( (int) $change['post_id'] ) === (int) $change['to'] ) {
			update_post_meta( (int) $change['post_id'], '_thumbnail_id', (int) $change['from'] );
		}
	}

	foreach ( (array) $record['parents'] as $change ) {
		$wpdb->update( $wpdb->posts, array( 'post_parent' => (int) $change['before'] ), array( 'ID' => (int) $change['id'] ) );
		clean_post_cache( (int) $change['id'] );
	}

	foreach ( (array) $record['used'] as $change ) {
		update_post_meta( (int) $change['id'], VERGEML_META_UNUSED, $change['unused'] );
		if ( '' !== $change['used_in'] ) {
			update_post_meta( (int) $change['id'], VERGEML_META_USED_IN, $change['used_in'] );
		} else {
			delete_post_meta( (int) $change['id'], VERGEML_META_USED_IN );
		}
	}

	// phpcs:enable

	$names = array();

	foreach ( (array) $record['aside'] as $id ) {
		vergeml_quarantine_release( (int) $id );
		$names[] = wp_basename( (string) get_attached_file( (int) $id ) );
	}

	unset( $records[ $token ] );
	vergeml_health_keep_undo_save( $records );

	$parts = array( __( 'Undone', 'vergelabs-media-library' ) );

	if ( $names ) {
		/* translators: %s: file names. */
		$parts[] = sprintf( _n( '%s back in the library', '%s back in the library', count( $names ), 'vergelabs-media-library' ), implode( ', ', $names ) );
	}
	if ( $back ) {
		/* translators: %s: how many pages. */
		$parts[] = sprintf( _n( '%s page as it was', '%s pages as they were', $back, 'vergelabs-media-library' ), number_format_i18n( $back ) );
	}
	if ( $kept ) {
		/* translators: %s: how many pages. */
		$parts[] = sprintf( _n( '%s page edited since, left as it is', '%s pages edited since, left as they are', $kept, 'vergelabs-media-library' ), number_format_i18n( $kept ) );
	}

	return array(
		'undone' => true,
		'back'   => $back,
		'line'   => implode( ' · ', $parts ),
	);
}


/* --------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_health_keep_routes' );

function vergeml_health_keep_routes() {

	// Rewriting other people's pages and taking files out of the library:
	// the same authority as the delete route, minus the deleting.
	$can = function () {
		return current_user_can( 'manage_options' );
	};

	register_rest_route( VERGEML_REST_NS, '/health-keep', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'vergeml_health_rest_keep',
		'permission_callback' => $can,
		'args'                => array(
			'keep' => array( 'type' => 'integer', 'required' => true ),
			'drop' => array( 'type' => 'array', 'required' => true, 'items' => array( 'type' => 'integer' ) ),
		),
	) );

	register_rest_route( VERGEML_REST_NS, '/health-keep-undo', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'vergeml_health_rest_keep_undo',
		'permission_callback' => $can,
		'args'                => array(
			'token' => array( 'type' => 'string', 'required' => true ),
		),
	) );

	register_rest_route( VERGEML_REST_NS, '/health-retire', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'vergeml_health_rest_retire',
		'permission_callback' => $can,
		'args'                => array(
			'ids'  => array( 'type' => 'array', 'required' => true, 'items' => array( 'type' => 'integer' ) ),
			'undo' => array( 'type' => 'boolean', 'default' => false ),
		),
	) );
}


function vergeml_health_rest_keep( WP_REST_Request $request ) {

	$result = vergeml_health_keep( (int) $request->get_param( 'keep' ), (array) $request->get_param( 'drop' ) );

	if ( is_wp_error( $result ) ) {
		return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
	}

	return rest_ensure_response( $result );
}


function vergeml_health_rest_keep_undo( WP_REST_Request $request ) {

	$result = vergeml_health_keep_undo( (string) $request->get_param( 'token' ) );

	if ( is_wp_error( $result ) ) {
		return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
	}

	return rest_ensure_response( $result );
}


function vergeml_health_rest_retire( WP_REST_Request $request ) {

	$ids  = array_map( 'intval', (array) $request->get_param( 'ids' ) );
	$undo = (bool) $request->get_param( 'undo' );

	if ( count( array_filter( $ids ) ) < 2 ) {
		return new WP_Error( 'nothing', __( 'A set is at least two files.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
	}

	$n = vergeml_health_retire( $ids, $undo );

	return rest_ensure_response( array(
		'changed' => $n,
		'line'    => $undo
			? __( 'Undone · the set is back on the next report', 'vergelabs-media-library' )
			: ( 2 === count( $ids )
				? __( 'Both kept · not shown again', 'vergelabs-media-library' )
				/* translators: %s: how many files. */
				: sprintf( __( 'All %s kept · not shown again', 'vergelabs-media-library' ), number_format_i18n( count( $ids ) ) ) ),
	) );
}
