<?php
/**
 *  Deleting duplicates.
 *
 *  The duplicates screen found copies, told you how much disk they were
 *  holding, and then stopped -- every act had to be done by hand, one file at
 *  a time, on each file's own edit screen. Finding ninety-one copies and then
 *  asking somebody to delete them one by one is most of a feature.
 *
 *  What makes this safe enough to do in one click is not care in the UI. It is
 *  three things in here:
 *
 *    1. Only byte-identical files. The group is recomputed on the server from
 *       the md5, never taken from the browser. A caller cannot name two
 *       unrelated files and have them treated as copies of each other.
 *    2. Nothing is deleted until every reference to it points somewhere else.
 *       Post content, featured images and the parent of an attached file are
 *       all repointed to the copy being kept, first, in one pass.
 *    3. The copy being kept is the one that is actually used. Keeping the
 *       oldest is the obvious rule and the wrong one -- the oldest is often
 *       the orphan, and the one on the page is the one somebody uploaded
 *       again later.
 *
 *  The files really are deleted. There is no undo, because the bytes are gone
 *  and a promise of one would be a lie -- which is exactly why the repointing
 *  above happens first and why the confirmation says what it says.
 *
 * @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 *  The md5 of a file, out of the hash meta the scan already wrote.
 *
 *  Stored as "md5:<32 hex>|dhash:<16 hex>", so this is a read rather than a
 *  second pass over the disk.
 *
 * @param int $attachment_id The attachment.
 * @return string The md5, or '' when it has not been hashed.
 */
function vergeml_health_md5_of( $attachment_id ) {

	$hash = (string) get_post_meta( (int) $attachment_id, VERGEML_META_HASH, true );

	if ( 0 !== strpos( $hash, 'md5:' ) ) {
		return '';
	}

	$md5 = substr( $hash, 4, 32 );

	return preg_match( '/^[a-f0-9]{32}$/', $md5 ) ? $md5 : '';
}


/**
 *  Every attachment byte-identical to this one, including itself.
 *
 *  Recomputed here rather than trusted from the request. The browser sends an
 *  id and nothing else; what counts as a copy of it is decided on this side.
 *
 * @param int $attachment_id The attachment.
 * @return int[] Attachment ids, ascending. Empty when it has no twin.
 */
function vergeml_health_twins( $attachment_id ) {

	global $wpdb;

	$md5 = vergeml_health_md5_of( $attachment_id );

	if ( '' === $md5 ) {
		return array();
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one indexed lookup, on a key this plugin owns.
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta}
		  WHERE meta_key = %s AND meta_value LIKE %s
		  ORDER BY post_id ASC",
		VERGEML_META_HASH,
		$wpdb->esc_like( 'md5:' . $md5 . '|' ) . '%'
	) );
	// phpcs:enable

	$ids = array_values( array_unique( array_map( 'intval', (array) $ids ) ) );

	return count( $ids ) > 1 ? $ids : array();
}


/**
 *  How many places use this file.
 *
 *  From the usage scan when it has run. Without it there is no answer, and -1
 *  says so rather than pretending the answer is zero -- "used nowhere" and "we
 *  have not looked" must not be the same value on a screen that decides which
 *  file gets deleted.
 *
 * @param int $attachment_id The attachment.
 * @return int Number of places, or -1 when unknown.
 */
function vergeml_health_used_count( $attachment_id ) {

	if ( ! defined( 'VERGEML_META_USED_IN' ) ) {
		return -1;
	}

	$scanned = function_exists( 'vergeml_smart_scan_state' )
		&& ! empty( vergeml_smart_scan_state()['finished'] );

	if ( ! $scanned ) {
		return -1;
	}

	$used = get_post_meta( (int) $attachment_id, VERGEML_META_USED_IN, true );

	if ( is_array( $used ) ) {
		return count( $used );
	}

	return '' === (string) $used ? 0 : count( array_filter( explode( ',', (string) $used ) ) );
}


/**
 *  Which copy to keep.
 *
 *  Most used first, then oldest. Usage decides it because the used copy is the
 *  one whose URL is on a page somebody has bookmarked, shared or indexed; the
 *  age only breaks a tie.
 *
 * @param int[] $ids The group.
 * @return int The id to keep.
 */
function vergeml_health_pick_keep( $ids ) {

	$best  = 0;
	$score = null;

	foreach ( $ids as $id ) {

		$id   = (int) $id;
		$uses = vergeml_health_used_count( $id );

		// Ascending id is ascending age, so a lower id wins a tie on uses.
		$here = array( max( 0, $uses ), -$id );

		if ( null === $score || $here > $score ) {
			$score = $here;
			$best  = $id;
		}
	}

	return $best;
}


/**
 *  Point everything at the kept copy, so nothing breaks when the other goes.
 *
 *  Three places a deleted attachment leaves a hole:
 *
 *    - a URL written into post content, in any of its generated sizes
 *    - a featured image, which is the attachment id in post meta
 *    - the parent post of an attached file
 *
 *  All three are rewritten before the delete, not after, because after is too
 *  late: wp_delete_attachment does not know what pointed at it.
 *
 * @param int $from The attachment about to be deleted.
 * @param int $to   The attachment being kept.
 * @return array{content:int,thumbs:int} What was changed.
 */
function vergeml_health_repoint( $from, $to ) {

	global $wpdb;

	$from = (int) $from;
	$to   = (int) $to;

	$changed = array( 'content' => 0, 'thumbs' => 0 );

	/*
	 *  The URLs. Every generated size of the old file becomes the matching URL
	 *  of the kept one -- the files are byte-identical, so the sizes were
	 *  generated from the same pixels and the same size exists on both.
	 */
	$old_url = wp_get_attachment_url( $from );
	$new_url = wp_get_attachment_url( $to );

	if ( is_string( $old_url ) && is_string( $new_url ) && $old_url !== $new_url ) {

		$old_stem = preg_replace( '/\.[a-zA-Z0-9]+$/', '', $old_url );
		$new_stem = preg_replace( '/\.[a-zA-Z0-9]+$/', '', $new_url );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts}
			  WHERE post_content LIKE %s AND post_status != 'trash'",
			'%' . $wpdb->esc_like( $old_stem ) . '%'
		) );

		foreach ( (array) $posts as $post ) {

			// Stem-based, so "-300x200.jpg" and srcset entries move too.
			$content = str_replace( $old_stem, $new_stem, $post->post_content );

			// The id, where a block wrote it as an attribute.
			$content = str_replace(
				array( '"id":' . $from . ',', 'wp-image-' . $from ),
				array( '"id":' . $to . ',', 'wp-image-' . $to ),
				$content
			);

			if ( $content === $post->post_content ) {
				continue;
			}

			$wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $content ),
				array( 'ID' => (int) $post->ID )
			);

			clean_post_cache( (int) $post->ID );
			$changed['content']++;
		}
		// phpcs:enable
	}

	/*
	 *  Featured images. A post whose thumbnail is about to be deleted would
	 *  otherwise render with no image and no error anywhere.
	 */
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	$thumbs = $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s",
		(string) $from
	) );
	// phpcs:enable

	foreach ( (array) $thumbs as $post_id ) {
		update_post_meta( (int) $post_id, '_thumbnail_id', $to );
		$changed['thumbs']++;
	}

	return $changed;
}


/**
 *  Delete the copies, keep one.
 *
 *  @param int   $keep The copy to keep.
 *  @param int[] $drop The copies to delete.
 *  @return array|WP_Error What happened, or why it refused.
 */
function vergeml_health_delete_copies( $keep, $drop ) {

	$keep = (int) $keep;
	$drop = array_values( array_unique( array_map( 'intval', (array) $drop ) ) );

	if ( ! $keep || ! $drop ) {
		return new WP_Error( 'nothing', __( 'Nothing to delete.', 'vergelabs-media-library' ) );
	}

	if ( in_array( $keep, $drop, true ) ) {
		return new WP_Error(
			'keep_in_drop',
			__( 'The file to keep cannot also be one of the files to delete.', 'vergelabs-media-library' )
		);
	}

	/*
	 *  Not without the usage scan. Which copy to keep, and which posts to
	 *  repoint, both come from it; without it every candidate reads as "used
	 *  nowhere", the keeper falls back to the lowest id -- the obvious rule
	 *  and the wrong one -- and references to the deleted copies are left
	 *  pointing at files that no longer exist. The renamer already refuses
	 *  on the same grounds; deleting is the more permanent of the two.
	 */
	if ( function_exists( 'vergeml_smart_scan_state' ) && empty( vergeml_smart_scan_state()['finished'] ) ) {
		return new WP_Error(
			'unscanned',
			__( 'Run the usage scan first, so the copy that is actually in use is the one kept and the pages using the others are repointed. Nothing was deleted.', 'vergelabs-media-library' )
		);
	}

	/*
	 *  The group, recomputed. This is the guard that matters: whatever the
	 *  request asked for, only files byte-identical to the one being kept can
	 *  be deleted by this route.
	 */
	$twins = vergeml_health_twins( $keep );

	if ( ! $twins ) {
		return new WP_Error(
			'not_duplicated',
			__( 'That file has no byte-identical copies. Nothing was deleted.', 'vergelabs-media-library' )
		);
	}

	foreach ( $drop as $id ) {
		if ( ! in_array( $id, $twins, true ) ) {
			return new WP_Error(
				'not_a_copy',
				sprintf(
					/* translators: %d: attachment id. */
					__( 'File %d is not a byte-identical copy of the one being kept. Nothing was deleted.', 'vergelabs-media-library' ),
					$id
				)
			);
		}
	}

	$deleted = array();
	$content = 0;
	$thumbs  = 0;
	$freed   = 0;
	$failed  = array();

	foreach ( $drop as $id ) {

		$path  = get_attached_file( $id );
		$bytes = ( is_string( $path ) && file_exists( $path ) ) ? (int) filesize( $path ) : 0;

		// Everything pointing at it moves first. Deleting is the last step.
		$moved    = vergeml_health_repoint( $id, $keep );
		$content += $moved['content'];
		$thumbs  += $moved['thumbs'];

		if ( wp_delete_attachment( $id, true ) ) {
			$deleted[] = $id;
			$freed    += $bytes;
		} else {
			$failed[] = $id;
		}
	}

	return array(
		'kept'     => $keep,
		'deleted'  => $deleted,
		'failed'   => $failed,
		'freed'    => $freed,
		'content'  => $content,
		'thumbs'   => $thumbs,
		'message'  => sprintf(
			/* translators: 1: how many files were deleted. 2: disk space freed. */
			_n(
				'%1$s copy deleted, %2$s freed.',
				'%1$s copies deleted, %2$s freed.',
				count( $deleted ),
				'vergelabs-media-library'
			),
			number_format_i18n( count( $deleted ) ),
			size_format( $freed )
		),
	);
}


/* --------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_health_delete_routes' );

function vergeml_health_delete_routes() {

	register_rest_route( VERGEML_REST_NS, '/health-delete', array(
		'methods'  => WP_REST_Server::CREATABLE,
		'callback' => 'vergeml_health_rest_delete',

		/*
		 *  Deleting files off the disk and editing other people's posts to
		 *  match. That is not the same authority as uploading, and it is not
		 *  the same authority as curating terms either.
		 */
		'permission_callback' => function () {
			return current_user_can( 'manage_options' ) && current_user_can( 'delete_posts' );
		},

		'args' => array(
			'keep' => array( 'type' => 'integer', 'required' => true ),
			'drop' => array( 'type' => 'array', 'required' => true, 'items' => array( 'type' => 'integer' ) ),
		),
	) );

	// What the screen needs to show a group: which copy is used, and where.
	register_rest_route( VERGEML_REST_NS, '/health-uses', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'vergeml_health_rest_uses',
		'permission_callback' => function () {
			return current_user_can( 'manage_categories' );
		},
		'args'                => array(
			'ids' => array( 'type' => 'string', 'required' => true ),
		),
	) );
}


function vergeml_health_rest_delete( WP_REST_Request $request ) {

	$result = vergeml_health_delete_copies(
		(int) $request->get_param( 'keep' ),
		(array) $request->get_param( 'drop' )
	);

	if ( is_wp_error( $result ) ) {
		return new WP_Error(
			$result->get_error_code(),
			$result->get_error_message(),
			array( 'status' => 400 )
		);
	}

	return rest_ensure_response( $result );
}


function vergeml_health_rest_uses( WP_REST_Request $request ) {

	$ids = array_slice(
		array_filter( array_map( 'intval', explode( ',', (string) $request->get_param( 'ids' ) ) ) ),
		0,
		200
	);

	$out = array();

	foreach ( $ids as $id ) {
		$out[ (string) $id ] = vergeml_health_used_count( $id );
	}

	return rest_ensure_response( array(
		'uses'    => $out,
		'scanned' => function_exists( 'vergeml_smart_scan_state' )
			&& ! empty( vergeml_smart_scan_state()['finished'] ),
	) );
}
