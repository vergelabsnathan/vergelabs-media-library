<?php
/**
 *  Telling the plugin what folders you want.
 *
 *  The proposed structure was take-it-or-leave-it: approve the whole tree, or
 *  approve it and then rename and move things by hand afterwards. What people
 *  actually want to say is a sentence -- "drop nature, I want buildings, split
 *  into modern and classic, and residential and office" -- and have the
 *  structure change to match.
 *
 *  Two steps, and the split between them is the point:
 *
 *    Propose   the sentence and the current tree go to the service, which
 *              returns the folders to end up with. Nothing has changed yet.
 *              You read the difference and either accept it or say something
 *              else.
 *
 *    Apply     the folders are created, renamed and removed, and every
 *              described picture is re-filed into whichever new folder its
 *              meaning is closest to.
 *
 *  Re-filing costs nothing. Every described picture already carries the vector
 *  the search box compares against; a new folder needs one vector for its own
 *  name, and then it is arithmetic. So reorganising ten thousand pictures
 *  costs the same as reorganising ten: nothing, and no picture is looked at
 *  twice.
 *
 *  Nothing here deletes a picture. A folder that goes away is a term that goes
 *  away; the files in it are re-filed, not removed.
 *
 * @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** How many described files to re-file in one pass. */
const VERGEML_TALK_SCAN = 5000;

/** Below this, a picture matches nothing well enough and stays where it is. */
const VERGEML_TALK_FLOOR = 0.16;

/** What we remember so the whole thing can be put back. */
const VERGEML_TALK_UNDO = 'vergeml_talk_undo';


/**
 *  The folders as they are, with counts.
 *
 * @return array<int,array{name:string,parent:string,count:int,term_id:int}>
 */
function vergeml_talk_current() {

	$taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';

	if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
	) );

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$by_id = array();

	foreach ( $terms as $term ) {
		$by_id[ (int) $term->term_id ] = $term;
	}

	$out = array();

	foreach ( $terms as $term ) {

		$parent = '';

		if ( $term->parent && isset( $by_id[ (int) $term->parent ] ) ) {
			$parent = (string) $by_id[ (int) $term->parent ]->name;
		}

		$out[] = array(
			'term_id' => (int) $term->term_id,
			'name'    => (string) $term->name,
			'parent'  => $parent,
			'count'   => (int) $term->count,
		);
	}

	return $out;
}


/**
 *  A sample of what the library actually contains.
 *
 *  Sent with the instruction so the model is proposing folders for these
 *  pictures rather than for photographs in general. Spread across the table
 *  rather than taken off the top, because the newest fifty files are usually
 *  one upload of one subject.
 *
 * @param int $limit How many captions to take.
 * @return string[]
 */
function vergeml_talk_samples( $limit = 40 ) {

	global $wpdb;

	if ( ! isset( $wpdb->vergeml_ai_index ) ) {
		return array();
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
	$total = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND caption != ''"
	);

	if ( $total < 1 ) {
		return array();
	}

	$every = max( 1, (int) floor( $total / max( 1, (int) $limit ) ) );

	$rows = $wpdb->get_col( $wpdb->prepare(
		"SELECT caption FROM {$wpdb->vergeml_ai_index}
		  WHERE error = '' AND caption != ''
	   ORDER BY attachment_id ASC
		  LIMIT %d",
		VERGEML_TALK_SCAN
	) );
	// phpcs:enable

	$out = array();

	foreach ( (array) $rows as $i => $caption ) {
		if ( 0 === $i % $every ) {
			$out[] = mb_substr( (string) $caption, 0, 160 );
		}
		if ( count( $out ) >= (int) $limit ) {
			break;
		}
	}

	return $out;
}


/**
 *  Ask the service what folders this sentence means.
 *
 * @param string $instruction What the user typed.
 * @return array|WP_Error { folders: array, note: string }
 */
function vergeml_talk_propose( $instruction, $history = array() ) {

	$instruction = trim( (string) $instruction );

	if ( '' === $instruction ) {
		return new WP_Error( 'empty', __( 'Say what you want the folders to be.', 'vergelabs-media-library' ) );
	}

	if ( ! function_exists( 'vergeml_ai_settings' ) ) {
		return new WP_Error( 'no_ai', __( 'The AI features are not available on this site.', 'vergelabs-media-library' ) );
	}

	$settings = vergeml_ai_settings();
	$licence  = vergeml_ai_unseal( $settings['license_key'] );

	if ( '' === $licence ) {
		return new WP_Error(
			'no_licence',
			__( 'This needs a licence key. Add yours under AI, and it costs no credits to use.', 'vergelabs-media-library' )
		);
	}

	$current = array();

	foreach ( vergeml_talk_current() as $f ) {
		$current[] = array( 'name' => $f['name'], 'parent' => $f['parent'], 'count' => $f['count'] );
	}

	$response = wp_remote_post(
		vergeml_ai_service_url() . '/folders',
		array(
			// Longer than a search: this is a person waiting on one answer,
			// having pressed a button, with a spinner in front of them.
			'timeout'   => 90,
			'headers'   => array( 'Content-Type' => 'application/json' ),
			'sslverify' => true,
			'body'      => wp_json_encode( array(
				'license_key' => $licence,
				'site'        => home_url(),
				'instruction' => $instruction,
				'history'     => $history,
				'current'     => $current,
				'samples'     => vergeml_talk_samples(),
			) ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'unreachable', __( 'Could not reach the service. Try again.', 'vergelabs-media-library' ) );
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 200 !== $code || ! is_array( $data ) || empty( $data['folders'] ) ) {

		$reason = is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : '';

		if ( 'not_entitled' === $reason || 'site_not_activated' === $reason || 'not_found' === $reason ) {
			return new WP_Error( 'licence', __( 'That licence is not active on this site. Check it under AI.', 'vergelabs-media-library' ) );
		}

		return new WP_Error( 'failed', __( 'That did not work, and nothing was changed. Try saying it differently.', 'vergelabs-media-library' ) );
	}

	$folders = array();

	foreach ( (array) $data['folders'] as $f ) {

		if ( ! is_array( $f ) || empty( $f['name'] ) ) {
			continue;
		}

		$folders[] = array(
			'name'    => sanitize_text_field( (string) $f['name'] ),
			'parent'  => isset( $f['parent'] ) ? sanitize_text_field( (string) $f['parent'] ) : '',
			'matches' => isset( $f['matches'] ) ? sanitize_text_field( (string) $f['matches'] ) : '',
		);
	}

	if ( ! $folders ) {
		return new WP_Error( 'failed', __( 'That did not come back with any folders. Try saying it differently.', 'vergelabs-media-library' ) );
	}

	return array(
		'folders' => $folders,
		'note'    => isset( $data['note'] ) ? sanitize_text_field( (string) $data['note'] ) : '',
		'diff'    => vergeml_talk_diff( $folders ),
	);
}


/**
 *  What would change, in the three words a person needs.
 *
 *  Kept, added, removed. Compared on the name because the name is what the
 *  user reads and what they typed; matching on anything else would call a
 *  rename a delete-and-create, which is not what it looks like from outside.
 *
 * @param array $folders The proposed folders.
 * @return array{kept:string[],added:string[],removed:array<int,array{name:string,count:int}>}
 */
function vergeml_talk_diff( $folders ) {

	$now  = array();
	$want = array();

	foreach ( vergeml_talk_current() as $f ) {
		$now[ mb_strtolower( $f['name'] ) ] = $f;
	}

	foreach ( $folders as $f ) {
		$want[ mb_strtolower( $f['name'] ) ] = $f;
	}

	$kept    = array();
	$added   = array();
	$removed = array();

	foreach ( $want as $key => $f ) {
		if ( isset( $now[ $key ] ) ) {
			$kept[] = $f['name'];
		} else {
			$added[] = $f['name'];
		}
	}

	foreach ( $now as $key => $f ) {
		if ( ! isset( $want[ $key ] ) ) {
			$removed[] = array( 'name' => $f['name'], 'count' => $f['count'] );
		}
	}

	return array( 'kept' => $kept, 'added' => $added, 'removed' => $removed );
}


/**
 *  A vector for a folder, from its name and what belongs in it.
 *
 *  One embedding per folder, not per picture. This is the whole reason
 *  re-filing is free.
 *
 * @param array $folder name and matches.
 * @return array|null The projected vector.
 */
function vergeml_talk_vector( $folder ) {

	if ( ! function_exists( 'vergeml_meaning_vector' ) ) {
		return null;
	}

	$text = trim( $folder['name'] . '. ' . $folder['matches'] );

	return vergeml_meaning_vector( $text );
}


/**
 *  Do it.
 *
 *  Terms first, then the re-filing. In that order because a picture cannot be
 *  put into a folder that does not exist yet, and because a failure half way
 *  through leaves folders with nothing in them rather than pictures pointing
 *  at nothing.
 *
 * @param array $folders The proposed folders.
 * @return array|WP_Error What happened.
 */
function vergeml_talk_apply( $folders ) {

	global $wpdb;

	$taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';

	if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'no_taxonomy', __( 'No folders are set up on this site.', 'vergelabs-media-library' ) );
	}

	if ( ! is_array( $folders ) || ! $folders ) {
		return new WP_Error( 'empty', __( 'Nothing to apply.', 'vergelabs-media-library' ) );
	}

	/*
	 *  Everything needed to put it back: which files were in which folders,
	 *  and which folders existed. Written before the first change.
	 */
	$before = array( 'terms' => vergeml_talk_current(), 'files' => array() );

	// ---------------------------------------------------------- the terms

	$ids   = array();
	$order = array();

	// Parents first, so a child can name one that already exists.
	foreach ( $folders as $f ) {
		if ( '' === $f['parent'] ) {
			$order[] = $f;
		}
	}
	foreach ( $folders as $f ) {
		if ( '' !== $f['parent'] ) {
			$order[] = $f;
		}
	}

	foreach ( $order as $f ) {

		$parent_id = 0;

		if ( '' !== $f['parent'] && isset( $ids[ mb_strtolower( $f['parent'] ) ] ) ) {
			$parent_id = $ids[ mb_strtolower( $f['parent'] ) ];
		}

		$existing = get_term_by( 'name', $f['name'], $taxonomy );

		if ( $existing && ! is_wp_error( $existing ) ) {

			if ( (int) $existing->parent !== $parent_id ) {
				wp_update_term( (int) $existing->term_id, $taxonomy, array( 'parent' => $parent_id ) );
			}

			$ids[ mb_strtolower( $f['name'] ) ] = (int) $existing->term_id;
			continue;
		}

		$made = wp_insert_term( $f['name'], $taxonomy, array( 'parent' => $parent_id ) );

		if ( ! is_wp_error( $made ) && isset( $made['term_id'] ) ) {
			$ids[ mb_strtolower( $f['name'] ) ] = (int) $made['term_id'];
		}
	}

	if ( ! $ids ) {
		return new WP_Error( 'no_terms', __( 'None of those folders could be created.', 'vergelabs-media-library' ) );
	}

	// -------------------------------------------------------- the vectors

	$vectors = array();

	foreach ( $folders as $f ) {

		$key = mb_strtolower( $f['name'] );

		if ( ! isset( $ids[ $key ] ) ) {
			continue;
		}

		$vector = vergeml_talk_vector( $f );

		if ( is_array( $vector ) && $vector ) {
			$vectors[ $key ] = $vector;
		}
	}

	if ( ! $vectors ) {
		return new WP_Error(
			'no_vectors',
			__( 'The folders were created, but we could not reach the service to work out what goes in them. Try again in a moment.', 'vergelabs-media-library' )
		);
	}

	// ------------------------------------------------------- the re-filing

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT attachment_id, embedding
		   FROM {$wpdb->vergeml_ai_index}
		  WHERE error = '' AND embedding IS NOT NULL
		  LIMIT %d",
		VERGEML_TALK_SCAN
	), ARRAY_A );
	// phpcs:enable

	$moved   = 0;
	$skipped = 0;
	$counts  = array();

	foreach ( (array) $rows as $row ) {

		$attachment = (int) $row['attachment_id'];

		/*
		 *  The whole embedding, because it is already here.
		 *
		 *  This projected 1536 dimensions down to 64 by averaging each run of
		 *  twenty-four adjacent components -- and adjacent components of an
		 *  embedding are arbitrary independent directions, not neighbours that
		 *  mean similar things, so averaging them is closer to discarding the
		 *  vector than compressing it. That is how a Footwear folder came to
		 *  hold a bicycle, a couch and some flowers.
		 *
		 *  The projection exists so search does not unpack every row on every
		 *  query. Re-filing already has the row unpacked in front of it, so it
		 *  pays nothing to be right.
		 */
		$vector = vergeml_index_vector_out( $row['embedding'] );

		$best  = '';
		$score = VERGEML_TALK_FLOOR;

		foreach ( $vectors as $key => $folder_vector ) {

			$here = vergeml_meaning_similarity( $folder_vector, $vector );

			if ( $here > $score ) {
				$score = $here;
				$best  = $key;
			}
		}

		if ( '' === $best ) {
			$skipped++;
			continue; // Nothing fits well enough. Leave it where it is.
		}

		// What it was in, so this can be undone.
		$was = wp_get_object_terms( $attachment, $taxonomy, array( 'fields' => 'ids' ) );
		$before['files'][ $attachment ] = is_wp_error( $was ) ? array() : array_map( 'intval', $was );

		wp_set_object_terms( $attachment, array( $ids[ $best ] ), $taxonomy, false );

		$counts[ $best ] = isset( $counts[ $best ] ) ? $counts[ $best ] + 1 : 1;
		$moved++;
	}

	// ------------------------------------------------- the folders that went

	$want    = array();
	$removed = 0;

	foreach ( $folders as $f ) {
		$want[ mb_strtolower( $f['name'] ) ] = true;
	}

	foreach ( $before['terms'] as $term ) {

		if ( isset( $want[ mb_strtolower( $term['name'] ) ] ) ) {
			continue;
		}

		/*
		 *  The term goes; the files do not. Anything that was in it has
		 *  already been re-filed above, and wp_delete_term only unhooks the
		 *  relationship -- there is no path from here to deleting a picture.
		 */
		wp_delete_term( (int) $term['term_id'], $taxonomy );
		$removed++;
	}

	update_option( VERGEML_TALK_UNDO, $before, false );

	return array(
		'moved'   => $moved,
		'skipped' => $skipped,
		'folders' => count( $ids ),
		'removed' => $removed,
		'counts'  => $counts,
		'message' => sprintf(
			/* translators: 1: files moved, 2: how many folders they went into. */
			_n(
				'%1$s picture re-filed into %2$s folders.',
				'%1$s pictures re-filed into %2$s folders.',
				$moved,
				'vergelabs-media-library'
			),
			number_format_i18n( $moved ),
			number_format_i18n( count( $counts ) )
		),
	);
}


/**
 *  Put it back exactly as it was.
 *
 * @return array|WP_Error
 */
function vergeml_talk_undo() {

	$before = get_option( VERGEML_TALK_UNDO );

	if ( ! is_array( $before ) || empty( $before['terms'] ) ) {
		return new WP_Error( 'nothing', __( 'There is nothing to undo.', 'vergelabs-media-library' ) );
	}

	$taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';

	if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'no_taxonomy', __( 'No folders are set up on this site.', 'vergelabs-media-library' ) );
	}

	// The folders that existed, back first, so files have somewhere to go.
	$ids = array();

	foreach ( $before['terms'] as $term ) {

		$existing = get_term_by( 'name', $term['name'], $taxonomy );

		if ( $existing && ! is_wp_error( $existing ) ) {
			$ids[ (int) $term['term_id'] ] = (int) $existing->term_id;
			continue;
		}

		$made = wp_insert_term( $term['name'], $taxonomy );

		if ( ! is_wp_error( $made ) && isset( $made['term_id'] ) ) {
			$ids[ (int) $term['term_id'] ] = (int) $made['term_id'];
		}
	}

	$put = 0;

	foreach ( (array) $before['files'] as $attachment => $terms ) {

		$back = array();

		foreach ( (array) $terms as $old ) {
			if ( isset( $ids[ (int) $old ] ) ) {
				$back[] = $ids[ (int) $old ];
			}
		}

		wp_set_object_terms( (int) $attachment, $back, $taxonomy, false );
		$put++;
	}

	delete_option( VERGEML_TALK_UNDO );

	return array(
		'restored' => $put,
		'message'  => sprintf(
			/* translators: %s: how many pictures went back. */
			_n( '%s picture put back.', '%s pictures put back.', $put, 'vergelabs-media-library' ),
			number_format_i18n( $put )
		),
	);
}


/* --------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_talk_routes' );

function vergeml_talk_routes() {

	$may = function () {
		return current_user_can( 'manage_categories' );
	};

	register_rest_route( VERGEML_REST_NS, '/folders-propose', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'vergeml_talk_rest_propose',
		'permission_callback' => $may,
		'args'                => array(
			'instruction' => array( 'type' => 'string', 'required' => true ),
			// What has already been said, so a refinement refines rather than
			// planning the library again from nothing.
			'history'     => array( 'type' => 'array', 'required' => false ),
		),
	) );

	register_rest_route( VERGEML_REST_NS, '/folders-apply', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'vergeml_talk_rest_apply',
		'permission_callback' => $may,
		'args'                => array(
			'folders' => array( 'type' => 'array', 'required' => true ),
			'plan_id' => array( 'type' => 'string', 'required' => true ),
		),
	) );

	register_rest_route( VERGEML_REST_NS, '/folders-undo', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'vergeml_talk_rest_undo',
		'permission_callback' => $may,
	) );
}


/** A WP_Error with a status, so the browser gets the message rather than a 500. */
function vergeml_talk_fail( $error ) {
	return new WP_Error( $error->get_error_code(), $error->get_error_message(), array( 'status' => 400 ) );
}


/*
 *  Two presses, and the second has to be about what the first showed.
 *
 *  Apply deletes every folder that is not in the list it is given. The
 *  proposal screen shows exactly what that means before the button -- but a
 *  request is not a screen, and an apply call assembled by hand, or replayed
 *  with a different list, would delete without anyone having read anything.
 *  So propose hands out a plan id bound to the folders it showed and to the
 *  person it showed them to, for a quarter of an hour, once. Apply must
 *  present the id and the same folders, or it is refused as stale.
 */

/** The shape of a plan that matters for "is this what was shown": names,
 *  parents, matches -- in order. */
function vergeml_talk_plan_hash( $folders ) {

	$flat = array();

	// mbstring is optional in PHP; without it the hash still has to agree
	// with itself on both sides of the round trip.
	$lower = function ( $s ) {
		$s = trim( (string) $s );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
	};

	foreach ( (array) $folders as $f ) {
		if ( ! is_array( $f ) || empty( $f['name'] ) ) {
			continue;
		}
		$flat[] = array(
			$lower( $f['name'] ),
			$lower( isset( $f['parent'] ) ? $f['parent'] : '' ),
			$lower( isset( $f['matches'] ) ? $f['matches'] : '' ),
		);
	}

	return hash( 'sha256', wp_json_encode( $flat ) );
}


function vergeml_talk_rest_propose( WP_REST_Request $request ) {

	$history = $request->get_param( 'history' );
	$history = is_array( $history ) ? $history : array();

	$said = array();
	foreach ( array_slice( $history, -12 ) as $turn ) {
		if ( ! is_array( $turn ) || ! isset( $turn['text'] ) ) {
			continue;
		}
		$said[] = array(
			'role' => ( isset( $turn['role'] ) && 'assistant' === $turn['role'] ) ? 'assistant' : 'user',
			'text' => (string) substr( (string) $turn['text'], 0, 400 ),
		);
	}

	$result = vergeml_talk_propose( (string) $request->get_param( 'instruction' ), $said );

	if ( is_wp_error( $result ) ) {
		return vergeml_talk_fail( $result );
	}

	$plan_id = wp_generate_password( 24, false, false );

	set_transient( 'vergeml_talk_plan_' . $plan_id, array(
		'hash' => vergeml_talk_plan_hash( isset( $result['folders'] ) ? $result['folders'] : array() ),
		'user' => get_current_user_id(),
	), 15 * MINUTE_IN_SECONDS );

	$result['plan_id'] = $plan_id;

	return rest_ensure_response( $result );
}


function vergeml_talk_rest_apply( WP_REST_Request $request ) {

	$folders = (array) $request->get_param( 'folders' );
	$clean   = array();

	foreach ( $folders as $f ) {

		if ( ! is_array( $f ) || empty( $f['name'] ) ) {
			continue;
		}

		$clean[] = array(
			'name'    => sanitize_text_field( (string) $f['name'] ),
			'parent'  => isset( $f['parent'] ) ? sanitize_text_field( (string) $f['parent'] ) : '',
			'matches' => isset( $f['matches'] ) ? sanitize_text_field( (string) $f['matches'] ) : '',
		);
	}

	$plan_id = preg_replace( '/[^A-Za-z0-9]/', '', (string) $request->get_param( 'plan_id' ) );
	$plan    = '' !== $plan_id ? get_transient( 'vergeml_talk_plan_' . $plan_id ) : false;

	if ( ! is_array( $plan )
	     || (int) $plan['user'] !== get_current_user_id()
	     || ! hash_equals( (string) $plan['hash'], vergeml_talk_plan_hash( $clean ) ) ) {
		return new WP_Error(
			'vergeml_talk_plan_stale',
			__( 'Those folders have expired or are not the ones that were shown. Ask again, read what would change, and then apply.', 'vergelabs-media-library' ),
			array( 'status' => 409 )
		);
	}

	// One press per plan.
	delete_transient( 'vergeml_talk_plan_' . $plan_id );

	$result = vergeml_talk_apply( $clean );

	return is_wp_error( $result ) ? vergeml_talk_fail( $result ) : rest_ensure_response( $result );
}


function vergeml_talk_rest_undo() {

	$result = vergeml_talk_undo();

	return is_wp_error( $result ) ? vergeml_talk_fail( $result ) : rest_ensure_response( $result );
}


/* ---------------------------------------------------------------- the screen */

/*
 *  Rendered into the Sort screen above the fold rather than in the block
 *  that appears once a run has finished. Changing your folders is what
 *  somebody wants when they already have folders they do not like -- and that
 *  library never reaches the last step, so the after-flow block would hide
 *  this from exactly the people it is for.
 */
add_action( 'vergeml_librarian_page_top', 'vergeml_talk_card' );

function vergeml_talk_card() {

	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	/*
	 *  Openers, offered once.
	 *
	 *  An empty chat window is the hardest thing in this plugin to start
	 *  using: nothing on screen says what it will understand, so people type
	 *  one cautious word and get something disappointing back. These say what
	 *  a good first sentence looks like -- and they are part of the empty
	 *  state, so they leave with it the moment the conversation starts rather
	 *  than sitting under a transcript still suggesting how to begin.
	 */
	$openers = array(
		__( 'Sort these into Apparel, with Women and Men under it', 'vergelabs-media-library' ),
		__( 'Group them by what room they belong in', 'vergelabs-media-library' ),
		__( 'I don’t want Nature — split Buildings into Modern and Classic instead', 'vergelabs-media-library' ),
	);

	?>
	<div class="vgml-talk" id="vgml-talk">

		<div class="vgml-talk-head">
			<h2><?php esc_html_e( 'Tell it what you want the folders to be', 'vergelabs-media-library' ); ?></h2>
			<p><?php esc_html_e( 'Say it the way you would say it to a person. Nothing moves until you press Do it, and talking costs no credits — the pictures have already been looked at.', 'vergelabs-media-library' ); ?></p>
		</div>

		<div class="vgml-talk-panel">

			<div class="vgml-talk-empty" id="vgml-talk-empty">
				<p class="vgml-talk-empty-lead"><?php esc_html_e( 'Try starting with one of these:', 'vergelabs-media-library' ); ?></p>
				<?php foreach ( $openers as $opener ) : ?>
					<button type="button" class="vgml-talk-chip"><?php echo esc_html( $opener ); ?></button>
				<?php endforeach; ?>
			</div>

			<div
				id="vgml-talk-log"
				class="vgml-talk-log"
				role="log"
				aria-live="polite"
				aria-label="<?php esc_attr_e( 'What you have asked for, and what was proposed', 'vergelabs-media-library' ); ?>"></div>

			<div class="vgml-talk-compose">

				<label class="screen-reader-text" for="vgml-talk-say">
					<?php esc_html_e( 'What you want the folders to be', 'vergelabs-media-library' ); ?>
				</label>

				<textarea
					id="vgml-talk-say"
					class="vgml-talk-say"
					rows="1"
					placeholder="<?php esc_attr_e( 'Say what you want the folders to be…', 'vergelabs-media-library' ); ?>"></textarea>

				<button type="button" class="button button-primary vgml-talk-send" id="vgml-talk-go">
					<?php esc_html_e( 'Send', 'vergelabs-media-library' ); ?>
				</button>

			</div>

			<p class="vgml-talk-note" id="vgml-talk-note" aria-live="polite"></p>

		</div>

	</div>
	<?php
}


add_action( 'admin_enqueue_scripts', 'vergeml_talk_assets' );

function vergeml_talk_assets( $hook ) {

	if ( false === strpos( (string) $hook, 'media-librarian' ) ) {
		return;
	}

	wp_enqueue_script(
		'vergeml-folder-talk',
		plugins_url( 'js/vergeml-folder-talk.js', VERGEML_FILE ),
		array( 'wp-api-fetch' ),
		vergeml_asset_ver( 'js/vergeml-folder-talk.js' ),
		true
	);

	/*
	 *  Flat, because that is how the script reads it.
	 *
	 *  These sat under an 'l10n' key while vergeml-folder-talk.js looked them
	 *  up directly on the object, so every single one missed and every string
	 *  on this screen came from the English fallback baked into the script --
	 *  including on a translated site, where nothing here was translatable at
	 *  all and no error was raised to say so.
	 */
	wp_localize_script( 'vergeml-folder-talk', 'vergemlTalk', array(
			'thinking'  => __( 'Working out what you mean…', 'vergelabs-media-library' ),
			'empty'     => __( 'Say what you want the folders to be.', 'vergelabs-media-library' ),
			'failed'    => __( 'That did not work, and nothing was changed.', 'vergelabs-media-library' ),
			'proposed'  => __( 'This is what you would end up with', 'vergelabs-media-library' ),
			'keeping'   => __( 'Keeping', 'vergelabs-media-library' ),
			'adding'    => __( 'New', 'vergelabs-media-library' ),
			'removing'  => __( 'Going away', 'vergelabs-media-library' ),
			/* translators: %s: how many files are in the folder being removed. */
			'held'      => __( '%s files — they are re-filed, not deleted', 'vergelabs-media-library' ),
			'apply'     => __( 'Do it', 'vergelabs-media-library' ),
			'applying'  => __( 'Re-filing…', 'vergelabs-media-library' ),
			'cancel'    => __( 'No, leave it', 'vergelabs-media-library' ),
			'undo'      => __( 'Undo this', 'vergelabs-media-library' ),
			'undoing'   => __( 'Putting it back…', 'vergelabs-media-library' ),
			/* translators: %s: how many pictures matched nothing. */
			'skipped'   => __( '%s pictures matched none of these well enough and were left where they were.', 'vergelabs-media-library' ),
			'nothing'   => __( 'Nothing would change.', 'vergelabs-media-library' ),
			'refine'    => __( 'Or say what to change about it.', 'vergelabs-media-library' ),
			'applied'   => __( 'Done — the folders are as you asked.', 'vergelabs-media-library' ),
			'noFolders' => __( 'That would leave you with no folders at all, so nothing has been changed.', 'vergelabs-media-library' ),
	) );
}
