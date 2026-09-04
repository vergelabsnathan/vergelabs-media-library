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
 *  Re-filing costs no credits. Every described picture already carries the
 *  vector the search box compares against; a new folder needs one vector for
 *  its own name, and then it is arithmetic. Nothing is described again and
 *  nothing is charged for twice.
 *
 *  It does cost time, which this used to deny. Comparing every picture against
 *  every folder is a few seconds for five thousand and half a minute for a
 *  hundred thousand, so a job larger than one pass finishes in the background
 *  and remembers where it got to. It used to stop at five thousand and say it
 *  was done.
 *
 *  Nothing here deletes a picture. A folder that goes away is a term that goes
 *  away; the files in it are re-filed, not removed.
 *
 * @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** How many described files to sample when showing the model the library. */
const VERGEML_TALK_SCAN = 5000;

/** How many described files one slice of a re-filing job reads. */
const VERGEML_TALK_SLICE = 500;

/** How many a single pass gets through before leaving the rest to the next. */
const VERGEML_TALK_PASS = 5000;

/** Seconds a background pass may spend before handing back to cron. */
const VERGEML_TALK_BUDGET = 15.0;

/** Where a re-filing job remembers what it has done. */
const VERGEML_TALK_STATE = 'vergeml_talk_refile';

/** The cron hook that carries an unfinished re-filing job on. */
const VERGEML_TALK_HOOK = 'vergeml_talk_refile_event';

/** Below this, a picture matches nothing well enough and stays where it is. */
const VERGEML_TALK_FLOOR = 0.16;

/** What we remember so the whole thing can be put back. */
const VERGEML_TALK_UNDO = 'vergeml_talk_undo';


/**
 *  Where a folder sits, as a key.
 *
 *  Two folders may share a name when they hang from different parents -- Jeans
 *  under Men and Jeans under Women are different folders -- so anything that
 *  maps a proposed folder to a term has to say which one it means.
 *
 * @param string $parent The parent's name, or '' for a top-level folder.
 * @param string $name   The folder's own name.
 * @return string
 */
function vergeml_talk_key( $parent, $name ) {

	$lower = function ( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s ) : strtolower( (string) $s );
	};

	return $lower( $parent ) . '>' . $lower( $name );
}


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


/** How many described pictures the grouping looks at, and how many groups. */
const VERGEML_TALK_GROUP_SAMPLE = 600;
const VERGEML_TALK_GROUPS = 10;

/**
 *  What share of described pictures say who they are for.
 *
 *  Product photography almost never does -- a boot on a white background is
 *  nobody's -- and the planner proposing Men / Women branches over such a
 *  library makes folders that can only be filled by guessing. It is told the
 *  number and asked not to.
 */
function vergeml_talk_audience_share() {
	global $wpdb;
	if ( ! isset( $wpdb->vergeml_ai_index ) ) {
		return 0.0;
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND filing IS NOT NULL AND filing <> ''" );
	$with  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND filing IS NOT NULL AND filing LIKE '%\"audience\":\"%' AND filing NOT LIKE '%\"audience\":\"\"%'" );
	// phpcs:enable
	return $total > 0 ? round( $with / $total, 3 ) : 0.0;
}

/**
 *  The groups this library actually falls into.
 *
 *  The service was being asked what folders a library needs while seeing forty
 *  captions, which is not enough to answer from and so gets answered from what
 *  photo libraries generally contain -- that is where a Screenshots folder
 *  comes from on a library with no screenshots in it. Nothing in the picture
 *  data suggested it; the model filled a gap.
 *
 *  So the gap gets filled here instead, with something only this site knows:
 *  the pictures clustered by their own embeddings, each group's size, and a
 *  few captions from the middle of it. "About 212 of these look like each
 *  other, and three of them are boots" is a fact about this library. Forty
 *  captions are a sample of one.
 *
 *  Sampled and rough on purpose. This runs while somebody waits, so it reads a
 *  few hundred rows rather than the whole table and stops after a handful of
 *  passes -- it is deciding what to tell a model about, not filing anything.
 *
 * @return array<int,array{size:int,captions:string[]}>
 */
function vergeml_talk_groups() {

	global $wpdb;

	if ( ! isset( $wpdb->vergeml_ai_index ) || ! function_exists( 'vergeml_meaning_similarity' ) ) {
		return array();
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
	$total = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index}
		  WHERE error = '' AND embedding IS NOT NULL AND caption != ''"
	);

	if ( $total < 12 ) {
		return array(); // Too few to have a shape worth reporting.
	}

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT embedding, caption
		   FROM {$wpdb->vergeml_ai_index}
		  WHERE error = '' AND embedding IS NOT NULL AND caption != ''
	   ORDER BY attachment_id ASC
		  LIMIT %d",
		VERGEML_TALK_GROUP_SAMPLE
	), ARRAY_A );
	// phpcs:enable

	$vectors  = array();
	$captions = array();

	foreach ( (array) $rows as $row ) {
		$v = vergeml_index_vector_out( $row['embedding'] );
		if ( is_array( $v ) && $v ) {
			$vectors[]  = $v;
			$captions[] = (string) $row['caption'];
		}
	}

	$n = count( $vectors );

	if ( $n < 12 ) {
		return array();
	}

	$k = (int) min( VERGEML_TALK_GROUPS, max( 2, floor( $n / 8 ) ) );

	/*
	 *  Seeds spread evenly through the sample rather than picked at random.
	 *  Random seeds make the same library answer differently on two consecutive
	 *  turns of one conversation, and a person who says "no, not like that"
	 *  should not have the ground move under them for an unrelated reason.
	 */
	$centroids = array();

	for ( $i = 0; $i < $k; $i++ ) {
		$centroids[] = $vectors[ (int) floor( $i * $n / $k ) ];
	}

	$members = array_fill( 0, $n, 0 );

	for ( $pass = 0; $pass < 6; $pass++ ) {

		$moved = false;

		foreach ( $vectors as $i => $vector ) {

			$best  = 0;
			$score = -2.0;

			foreach ( $centroids as $c => $centroid ) {
				$here = vergeml_meaning_similarity( $centroid, $vector );
				if ( $here > $score ) {
					$score = $here;
					$best  = $c;
				}
			}

			if ( $members[ $i ] !== $best ) {
				$members[ $i ] = $best;
				$moved = true;
			}
		}

		if ( ! $moved ) {
			break; // Settled; more passes would change nothing.
		}

		for ( $c = 0; $c < $k; $c++ ) {

			$sum   = array();
			$count = 0;

			foreach ( $members as $i => $m ) {
				if ( $m !== $c ) {
					continue;
				}
				foreach ( $vectors[ $i ] as $d => $value ) {
					$sum[ $d ] = isset( $sum[ $d ] ) ? $sum[ $d ] + $value : $value;
				}
				$count++;
			}

			if ( $count > 0 ) {
				foreach ( $sum as $d => $value ) {
					$sum[ $d ] = $value / $count;
				}
				$centroids[ $c ] = vergeml_organize_normalise( $sum );
			}
		}
	}

	$out = array();

	for ( $c = 0; $c < $k; $c++ ) {

		$mine = array();

		foreach ( $members as $i => $m ) {
			if ( $m === $c ) {
				$mine[ $i ] = vergeml_meaning_similarity( $centroids[ $c ], $vectors[ $i ] );
			}
		}

		if ( count( $mine ) < 3 ) {
			continue; // Three pictures is not a group, it is a coincidence.
		}

		// The most typical of the group, not the first three found: a caption
		// from the edge of a cluster describes the edge, not the cluster.
		arsort( $mine );

		$picked = array();

		foreach ( array_slice( array_keys( $mine ), 0, 5 ) as $i ) {
			$picked[] = mb_substr( $captions[ $i ], 0, 160 );
		}

		$out[] = array(
			// Scaled back up to the library, because the sample is a sample and
			// "about 212" is the number that decides whether a folder is worth
			// making. Saying 60 of a 600-file sample would understate it.
			'size'     => (int) round( count( $mine ) * $total / $n ),
			'captions' => $picked,
		);
	}

	usort( $out, function ( $a, $b ) {
		return $b['size'] - $a['size'];
	} );

	return $out;
}


/**
 *  Ask the service what folders this sentence means.
 *
 * @param string $instruction What the user typed.
 * @return array|WP_Error { folders: array, note: string }
 */
function vergeml_talk_propose( $instruction, $history = array(), $mode = 'literal' ) {

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
				'groups'      => vergeml_talk_groups(),
				'audience_share' => vergeml_talk_audience_share(),
				'mode'        => 'suggested' === $mode ? 'suggested' : 'literal',
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

		if ( 'planner_daily_cap' === $reason ) {
			return new WP_Error( 'capped', __( 'The planner has answered as often as it can for this site today. It is back tomorrow; until then the folders can still be shaped by hand under Media Categories.', 'vergelabs-media-library' ) );
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
			// What the matcher files by (see core/filing.php); absent from older plans.
			'classes'  => isset( $f['classes'] ) && is_array( $f['classes'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $f['classes'] ) ) ) : array(),
			'kinds'    => isset( $f['kinds'] ) && is_array( $f['kinds'] ) ? array_values( array_filter( array_map( 'sanitize_key', $f['kinds'] ) ) ) : array(),
			'audience' => isset( $f['audience'] ) ? sanitize_text_field( (string) $f['audience'] ) : '',
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
function vergeml_talk_apply( $folders, $tags = array() ) {

	global $wpdb;

	$taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';

	if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'no_taxonomy', __( 'No folders are set up on this site.', 'vergelabs-media-library' ) );
	}

	if ( ! is_array( $folders ) || ! $folders ) {
		return new WP_Error( 'empty', __( 'Nothing to apply.', 'vergelabs-media-library' ) );
	}

	/*
	 *  A name that is a path -- "Apparel / Men / Shoes" -- is one folder under
	 *  two, never one folder with slashes in it. An older planner answered
	 *  paths as names and the plugin made them literally; those folders are
	 *  read as paths by the matcher, but no new ones are made that way.
	 */
	$split = array();
	$known = array();
	foreach ( $folders as $f ) {
		$known[ mb_strtolower( $f['name'] ) ] = true;
	}
	foreach ( $folders as $f ) {
		$parts = preg_split( '/\s*\/\s*/u', (string) $f['name'] );
		$parts = array_values( array_filter( array_map( 'trim', (array) $parts ), 'strlen' ) );
		if ( count( $parts ) < 2 ) {
			$split[] = $f;
			continue;
		}
		$parent = (string) $f['parent'];
		foreach ( array_slice( $parts, 0, -1 ) as $segment ) {
			if ( ! isset( $known[ mb_strtolower( $segment ) ] ) ) {
				$known[ mb_strtolower( $segment ) ] = true;
				$split[] = array( 'name' => $segment, 'parent' => $parent, 'matches' => '', 'classes' => array(), 'kinds' => array(), 'audience' => '' );
			}
			$parent = $segment;
		}
		$f['name']   = (string) end( $parts );
		$f['parent'] = $parent;
		$split[]     = $f;
	}
	$folders = $split;

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

	/*
	 *  Folders are keyed by where they sit, not by what they are called.
	 *
	 *  Keying on the name alone silently deleted half of any tree that repeated
	 *  one: asked for Apparel with Men and Women under it, and Jeans, Shirts
	 *  and Shoes under each, Men kept its three and Women got none -- the
	 *  second Jeans overwrote the first in this map, so both branches pointed
	 *  at one term and only one parent could own it. Two folders may share a
	 *  name when they hang from different parents. That is what a tree is, and
	 *  WordPress allows it.
	 *
	 *  $by_name stays for resolving a parent, because the service names a
	 *  parent by name and nothing deeper is available to disambiguate with.
	 */
	$by_name = array();

	foreach ( $order as $f ) {

		$parent_id  = 0;
		$parent_key = mb_strtolower( $f['parent'] );

		if ( '' !== $f['parent'] && isset( $by_name[ $parent_key ] ) ) {
			$parent_id = $by_name[ $parent_key ];
		}

		$key = vergeml_talk_key( $f['parent'], $f['name'] );

		/*
		 *  Matched on the name AND the parent. get_term_by( 'name', ... )
		 *  returns whichever term happens to carry that name anywhere in the
		 *  tree, so re-filing into Women / Jeans would have found Men / Jeans
		 *  and moved it, rather than making the folder that was asked for.
		 */
		$found = get_terms( array(
			'taxonomy'   => $taxonomy,
			'name'       => $f['name'],
			'parent'     => $parent_id,
			'hide_empty' => false,
			'number'     => 1,
		) );

		$existing = ( ! is_wp_error( $found ) && $found ) ? $found[0] : null;

		if ( null !== $existing ) {
			$ids[ $key ] = (int) $existing->term_id;
			if ( ! isset( $by_name[ mb_strtolower( $f['name'] ) ] ) ) {
				$by_name[ mb_strtolower( $f['name'] ) ] = (int) $existing->term_id;
			}
			continue;
		}

		$made = wp_insert_term( $f['name'], $taxonomy, array( 'parent' => $parent_id ) );

		/*
		 *  A name that already exists under a different parent comes back as
		 *  term_exists rather than an insert, and WordPress hands the clashing
		 *  id back in the error. Reusing it would be the same collision from
		 *  the other direction, so it is made unique by its parent instead --
		 *  which is what somebody asking for Jeans under both Men and Women
		 *  meant, and what they will see on the tree.
		 */
		if ( is_wp_error( $made ) && 'term_exists' === $made->get_error_code() && $parent_id > 0 ) {
			$made = wp_insert_term( $f['name'], $taxonomy, array(
				'parent' => $parent_id,
				'slug'   => sanitize_title( $f['parent'] . '-' . $f['name'] ),
			) );
		}

		if ( ! is_wp_error( $made ) && isset( $made['term_id'] ) ) {
			$ids[ $key ] = (int) $made['term_id'];
			// The profile the matcher files against, from what the plan said.
			if ( function_exists( 'vergeml_filing_profile_build' ) ) {
				$term_obj = get_term( (int) $made['term_id'], $taxonomy );
				if ( $term_obj && ! is_wp_error( $term_obj ) ) {
					vergeml_filing_profile_build( $term_obj, $taxonomy, $f );
				}
			}
			if ( ! isset( $by_name[ mb_strtolower( $f['name'] ) ] ) ) {
				$by_name[ mb_strtolower( $f['name'] ) ] = (int) $made['term_id'];
			}
		}
	}

	if ( ! $ids ) {
		return new WP_Error( 'no_terms', __( 'None of those folders could be created.', 'vergelabs-media-library' ) );
	}

	// -------------------------------------------------------- the vectors

	$vectors = array();

	foreach ( $folders as $f ) {

		$key = vergeml_talk_key( $f['parent'], $f['name'] );

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

	/*
	 *  Started here, finished in the background.
	 *
	 *  This re-filed in one pass under a LIMIT of five thousand, with no ORDER BY
	 *  and nothing to carry on with. On a library of twenty thousand that filed
	 *  five thousand pictures, said it was done, and left the other fifteen
	 *  thousand exactly where they were -- and because nothing recorded where the
	 *  pass had reached, running it again re-examined whichever arbitrary five
	 *  thousand MySQL happened to hand back. The file header promised that
	 *  reorganising ten thousand pictures cost the same as reorganising ten. It
	 *  did not: it silently did a fraction of the work and reported success.
	 *
	 *  So the folders are created here and the re-filing becomes a job that
	 *  remembers where it got to. One pass runs now, so a library smaller than a
	 *  pass is simply finished when the button comes back; anything larger
	 *  continues on cron, in slices, from the last picture it filed.
	 */

	$remove = array();
	$want   = array();

	foreach ( $folders as $f ) {
		$want[ mb_strtolower( $f['name'] ) ] = true;
	}

	foreach ( $before['terms'] as $term ) {
		if ( ! isset( $want[ mb_strtolower( $term['name'] ) ] ) ) {
			$remove[] = (int) $term['term_id'];
		}
	}

	/*
	 *  Tags ride along: the guide's second axis, made by vergeml_guide_make_tags()
	 *  and put on pictures in the same pass, from the catalogue record. Undo
	 *  keeps the record of what was made so it can take exactly that back.
	 */
	$tag_map = array();
	foreach ( (array) $tags as $entry ) {
		foreach ( (array) $entry['terms'] as $term_id => $term ) {
			$tag_map[ (string) $entry['taxonomy'] ][ (int) $term_id ] = (array) $term['needles'];
		}
	}
	if ( $tags ) {
		$before['tags'] = $tags;
	}

	update_option( VERGEML_TALK_UNDO, $before, false );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
	$total = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index}
		  WHERE error = '' AND embedding IS NOT NULL"
	);
	// phpcs:enable

	/*
	 *  Folders that were already here know only their names. One planner call
	 *  tells the matcher what each of them takes (core/filing.php), so a coat
	 *  reaches Apparel and a logo stays out of Men. Best effort: without it the
	 *  name-derived profiles still file, only more cautiously.
	 */
	if ( function_exists( 'vergeml_filing_profile_existing' ) ) {
		vergeml_filing_profile_existing( $taxonomy );
	}

	update_option( VERGEML_TALK_STATE, array(
		'active'   => true,
		'taxonomy' => $taxonomy,
		'ids'      => $ids,
		'vectors'  => $vectors,
		'after'    => 0,
		'moved'    => 0,
		'skipped'  => 0,
		'seen'     => 0,
		'total'    => $total,
		'counts'   => array(),
		'tags'     => $tag_map,
		'tagged'   => 0,
		/*
		 *  Held until the end. Deleting a folder while pictures that belong in
		 *  it have not been looked at yet would drop them out of every folder --
		 *  the one way this screen could lose somebody's filing rather than
		 *  change it.
		 */
		'remove'   => $remove,
		'started'  => time(),
	), false );

	$state = vergeml_talk_refile_run( microtime( true ) + 5.0 );

	if ( ! empty( $state['active'] ) ) {
		vergeml_talk_refile_schedule();
	}

	return vergeml_talk_report( $state );
}


/**
 *  Work through as much of the re-filing as the time allows.
 *
 * @param float $deadline When to stop and leave the rest to the next pass.
 * @return array The state as it now stands.
 */
function vergeml_talk_refile_run( $deadline ) {

	global $wpdb;

	$state = get_option( VERGEML_TALK_STATE );

	if ( ! is_array( $state ) || empty( $state['active'] ) ) {
		return is_array( $state ) ? $state : array( 'active' => false );
	}

	$taxonomy = (string) $state['taxonomy'];

	if ( ! taxonomy_exists( $taxonomy ) ) {
		$state['active'] = false;
		update_option( VERGEML_TALK_STATE, $state, false );
		return $state;
	}

	/*
	 *  What each picture was in, collected in memory and written once when the
	 *  pass ends. Appending to the undo record slice by slice would rewrite an
	 *  option megabytes long a few hundred times over a large library, which
	 *  costs more than the re-filing it is recording.
	 */
	$undo = array();
	$pass = 0;

	/*
	 *  Filterable, and not only for the test that drives them.
	 *
	 *  A host with a short execution limit wants smaller slices, and a box with
	 *  room wants larger ones. They are also the only way to make a library of
	 *  two hundred take more than one pass, and a resumption that is never
	 *  exercised is a resumption nobody has established works -- which is how
	 *  this stopped at five thousand for as long as it did.
	 */
	$slice  = max( 1, (int) apply_filters( 'vergeml_talk_slice', VERGEML_TALK_SLICE ) );
	$budget = max( 1, (int) apply_filters( 'vergeml_talk_pass', VERGEML_TALK_PASS ) );

	do {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT attachment_id, embedding, kind, filing, tags
			   FROM {$wpdb->vergeml_ai_index}
			  WHERE error = '' AND embedding IS NOT NULL AND attachment_id > %d
		   ORDER BY attachment_id ASC
			  LIMIT %d",
			(int) $state['after'],
			$slice
		), ARRAY_A );
		// phpcs:enable

		foreach ( (array) $rows as $row ) {

			$attachment = (int) $row['attachment_id'];

			// Ordered by id and remembered, so a pass that stops halfway leaves a
			// place to carry on from rather than a sample to take again.
			$state['after'] = $attachment;
			$state['seen']  = (int) $state['seen'] + 1;

			// The second axis: terms whose value the record names, added, never replacing what is there.
			if ( ! empty( $state['tags'] ) ) {
				$hit = false;
				foreach ( vergeml_talk_tag_row( $row, (array) $state['tags'] ) as $tag_tax => $term_ids ) {
					wp_set_object_terms( $attachment, $term_ids, $tag_tax, true );
					$hit = true;
				}
				if ( $hit ) {
					$state['tagged'] = (int) $state['tagged'] + 1;
				}
			}

			/*
			 *  Filed by evidence (core/filing.php): the picture's kind and
			 *  audience gate the folders, its object class is matched against
			 *  theirs, and the vector only breaks ties. What clears neither the
			 *  floor nor the margin stays where it is and is counted as such.
			 */
			if ( ! isset( $profiles ) ) {
				$profiles = vergeml_filing_profiles( array_values( (array) $state['ids'] ), $taxonomy );
			}
			$facts = vergeml_filing_facts( $row );
			$pick  = vergeml_filing_pick( $facts, $profiles );

			if ( ! $pick['term_id'] ) {
				$state['skipped'] = (int) $state['skipped'] + 1;
				$why              = isset( $pick['why'] ) ? $pick['why'] : 'floor';
				$state['unfiled'][ $why ] = isset( $state['unfiled'][ $why ] ) ? (int) $state['unfiled'][ $why ] + 1 : 1;
				/*
				 *  Nothing fits well enough, so it is left where it is -- unless
				 *  where it is fails a gate. A logo sitting in Men is not "no
				 *  evidence", it is evidence against, and out it comes.
				 */
				$was = wp_get_object_terms( $attachment, $taxonomy, array( 'fields' => 'ids' ) );
				$was = is_wp_error( $was ) ? array() : array_map( 'intval', $was );
				$out = array();
				foreach ( $was as $tid ) {
					// Gated out of it, or plainly not matching it (a misfit, not an unknown).
					// A misfit is only called on a folder the planner has described; a name alone is too thin to evict on.
					if ( isset( $pick['gated'][ $tid ] ) || ( $facts['classes'] && isset( $pick['scores'][ $tid ], $profiles[ $tid ] ) && 'plan' === $profiles[ $tid ]['source'] && $pick['scores'][ $tid ] < VERGEML_FILING_MISFIT ) ) {
						$out[] = $tid;
					}
				}
				if ( $out ) {
					$undo[ $attachment ] = $was;
					wp_set_object_terms( $attachment, array_values( array_diff( $was, $out ) ), $taxonomy, false );
					$state['unfiled']['evicted'] = isset( $state['unfiled']['evicted'] ) ? (int) $state['unfiled']['evicted'] + 1 : 1;
				}
				continue;
			}

			$best = array_search( (int) $pick['term_id'], array_map( 'intval', (array) $state['ids'] ), true );

			$was = wp_get_object_terms( $attachment, $taxonomy, array( 'fields' => 'ids' ) );
			$undo[ $attachment ] = is_wp_error( $was ) ? array() : array_map( 'intval', $was );

			wp_set_object_terms( $attachment, array( (int) $pick['term_id'] ), $taxonomy, false );

			$state['counts'][ $best ] = isset( $state['counts'][ $best ] )
				? (int) $state['counts'][ $best ] + 1
				: 1;

			$state['moved'] = (int) $state['moved'] + 1;
		}

		$pass += count( (array) $rows );

		if ( count( (array) $rows ) < $slice ) {
			vergeml_talk_refile_finish( $state );
			break;
		}
	} while ( $pass < $budget && microtime( true ) < $deadline );

	if ( $undo ) {

		$before = get_option( VERGEML_TALK_UNDO );

		if ( is_array( $before ) ) {
			/*
			 *  The earliest record of a file wins. The union keeps what is
			 *  already stored, which is where the file sat before any of this
			 *  began -- and that, not where the last pass found it, is what undo
			 *  has to put it back to.
			 */
			$before['files'] = ( isset( $before['files'] ) ? (array) $before['files'] : array() ) + $undo;
			update_option( VERGEML_TALK_UNDO, $before, false );
		}
	}

	update_option( VERGEML_TALK_STATE, $state, false );

	return $state;
}


/**
 *  Which tag terms a picture's record names.
 *
 *  The haystack is what the describer wrote down: the eight tags and the
 *  filing fields (object, material, colour, setting, style, season, details).
 *  A value matches as a whole word, singular or plural, so "tan" is not found
 *  in "tangerine" and "boot" still finds "boots". Nothing is inferred: a
 *  picture whose record does not say red is not red here.
 *
 * @param array $row  A catalogue row with filing and tags.
 * @param array $tags taxonomy => term_id => needles.
 * @return array taxonomy => term ids.
 */
function vergeml_talk_tag_row( $row, $tags ) {

	$filing = isset( $row['filing'] ) ? json_decode( (string) $row['filing'], true ) : null;
	$filing = is_array( $filing ) ? $filing : array();
	$parts  = function_exists( 'vergeml_index_tags_out' ) ? vergeml_index_tags_out( isset( $row['tags'] ) ? $row['tags'] : '' ) : array();

	foreach ( array( 'object', 'material', 'colour', 'setting', 'style', 'season', 'details' ) as $field ) {
		if ( ! empty( $filing[ $field ] ) && is_string( $filing[ $field ] ) ) {
			$parts[] = $filing[ $field ];
		}
	}

	if ( ! $parts ) {
		return array();
	}

	$hay = ' ' . mb_strtolower( implode( ' | ', $parts ) ) . ' ';
	$out = array();

	foreach ( $tags as $taxonomy => $terms ) {
		foreach ( (array) $terms as $term_id => $needles ) {
			foreach ( (array) $needles as $needle ) {
				$needle = trim( (string) $needle );
				if ( '' === $needle ) {
					continue;
				}
				if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $needle, '/' ) . '(?:s|es)?(?![\p{L}\p{N}])/u', $hay ) ) {
					$out[ (string) $taxonomy ][] = (int) $term_id;
					break;
				}
			}
		}
	}

	return $out;
}


/**
 *  The folders that go, once every picture has been looked at.
 *
 * @param array $state Taken by reference so the caller writes it once.
 */
function vergeml_talk_refile_finish( &$state ) {

	$taxonomy = (string) $state['taxonomy'];

	foreach ( (array) $state['remove'] as $term_id ) {

		/*
		 *  The term goes; the files do not. Anything that was in it has been
		 *  re-filed by now, which is the reason this waited, and wp_delete_term
		 *  only unhooks the relationship. There is no path from here to
		 *  deleting a picture.
		 */
		wp_delete_term( (int) $term_id, $taxonomy );
	}

	$state['removed'] = count( (array) $state['remove'] );
	$state['remove']  = array();
	$state['active']  = false;
}


/** Ask WordPress to carry on, and do not wait for a visitor to make it. */
function vergeml_talk_refile_schedule() {

	if ( ! wp_next_scheduled( VERGEML_TALK_HOOK ) ) {
		wp_schedule_single_event( time(), VERGEML_TALK_HOOK );
	}

	/*
	 *  WP-Cron fires on page loads, so on a site nobody is browsing a job that
	 *  says it is still going simply stops. The describe run learned that the
	 *  hard way; re-filing makes the same promise and needs the same nudge.
	 */
	$url = add_query_arg( 'doing_wp_cron', sprintf( '%.22F', microtime( true ) ), site_url( 'wp-cron.php' ) );

	wp_remote_post( $url, array(
		'timeout'   => 0.01,
		'blocking'  => false,
		'sslverify' => false,
	) );
}


add_action( VERGEML_TALK_HOOK, 'vergeml_talk_refile_event' );

function vergeml_talk_refile_event() {

	$state = vergeml_talk_refile_run( microtime( true ) + VERGEML_TALK_BUDGET );

	if ( ! empty( $state['active'] ) ) {
		vergeml_talk_refile_schedule();
	}
}


/**
 *  Where the re-filing has got to, in the words the screen uses.
 *
 * @param array $state
 * @return array
 */
function vergeml_talk_report( $state ) {

	$moved   = isset( $state['moved'] ) ? (int) $state['moved'] : 0;
	$counts  = isset( $state['counts'] ) ? (array) $state['counts'] : array();
	$total   = isset( $state['total'] ) ? (int) $state['total'] : 0;
	$seen    = isset( $state['seen'] ) ? (int) $state['seen'] : 0;
	$running = ! empty( $state['active'] );
	$tagged  = isset( $state['tagged'] ) ? (int) $state['tagged'] : 0;

	$message = $running
		? sprintf(
			/* translators: 1: pictures looked at so far, 2: pictures in total. */
			__( 'Re-filing — %1$s of %2$s pictures so far. You can leave this page; it carries on.', 'vergelabs-media-library' ),
			number_format_i18n( $seen ),
			number_format_i18n( $total )
		)
		: vergeml_talk_outcome_sentence( $moved, count( $counts ), isset( $state['unfiled'] ) ? (array) $state['unfiled'] : array() );

	if ( ! $running && $tagged ) {
		/* translators: %s: a number of pictures */
		$message .= ' ' . sprintf( _n( '%s picture was tagged.', '%s pictures were tagged.', $tagged, 'vergelabs-media-library' ), number_format_i18n( $tagged ) );
	}

	return array(
		'moved'     => $moved,
		'skipped'   => isset( $state['skipped'] ) ? (int) $state['skipped'] : 0,
		'folders'   => count( $counts ),
		'tagged'    => $tagged,
		'removed'   => isset( $state['removed'] ) ? (int) $state['removed'] : 0,
		'counts'    => $counts,
		'running'   => $running,
		'seen'      => $seen,
		'total'     => $total,
		'remaining' => max( 0, $total - $seen ),
		'unfiled'   => isset( $state['unfiled'] ) ? (array) $state['unfiled'] : array(),
		'message'   => $message,
	);
}

/**
 *  What happened, in one sentence that also says what did not.
 *
 *  "204 pictures re-filed into 3 folders" was the whole story before, and the
 *  missing half was the half that mattered: the ones left where they were,
 *  and why. A run that files everything is a run that guessed.
 */
function vergeml_talk_outcome_sentence( $moved, $folders, $unfiled ) {
	$head = sprintf(
		_n( '%1$s picture re-filed into %2$s folders.', '%1$s pictures re-filed into %2$s folders.', $moved, 'vergelabs-media-library' ),
		number_format_i18n( $moved ),
		number_format_i18n( $folders )
	);
	$parts = array();
	$floor = isset( $unfiled['floor'] ) ? (int) $unfiled['floor'] : 0;
	$close = isset( $unfiled['margin'] ) ? (int) $unfiled['margin'] : 0;
	$gated = isset( $unfiled['gated'] ) ? (int) $unfiled['gated'] : 0;
	$out   = isset( $unfiled['evicted'] ) ? (int) $unfiled['evicted'] : 0;
	if ( $floor ) {
		/* translators: %s: a number of pictures */
		$parts[] = sprintf( _n( '%s did not fit any folder', '%s did not fit any folder', $floor, 'vergelabs-media-library' ), number_format_i18n( $floor ) );
	}
	if ( $close ) {
		/* translators: %s: a number of pictures */
		$parts[] = sprintf( _n( '%s was too close to call between two folders', '%s were too close to call between two folders', $close, 'vergelabs-media-library' ), number_format_i18n( $close ) );
	}
	if ( $gated ) {
		/* translators: %s: a number of pictures */
		$parts[] = sprintf( _n( '%s was the wrong kind for every folder (a logo, a screenshot)', '%s were the wrong kind for every folder (logos, screenshots)', $gated, 'vergelabs-media-library' ), number_format_i18n( $gated ) );
	}
	if ( $out ) {
		/* translators: %s: a number of pictures */
		$parts[] = sprintf( _n( '%s was taken out of a folder it did not belong in', '%s were taken out of folders they did not belong in', $out, 'vergelabs-media-library' ), number_format_i18n( $out ) );
	}
	if ( ! $parts ) {
		return $head;
	}
	/* translators: 1: the re-filed sentence, 2: what was left alone */
	return sprintf( __( '%1$s Left where they were: %2$s.', 'vergelabs-media-library' ), $head, implode( '; ', $parts ) );
}


/** What the screen polls while a re-filing job is still going. */
function vergeml_talk_progress() {

	$state = get_option( VERGEML_TALK_STATE );

	if ( ! is_array( $state ) ) {
		return array( 'running' => false, 'seen' => 0, 'total' => 0, 'remaining' => 0, 'moved' => 0 );
	}

	/*
	 *  A job whose cron never ran is not a job in progress, whatever the option
	 *  says. Rather than report movement that is not happening -- the exact lie
	 *  the describe run used to tell -- the poll gives it a push.
	 */
	if ( ! empty( $state['active'] ) && ! wp_next_scheduled( VERGEML_TALK_HOOK ) ) {
		vergeml_talk_refile_schedule();
	}

	return vergeml_talk_report( $state );
}


/**
 *  Put it back exactly as it was.
 *
 * @return array|WP_Error
 */
function vergeml_talk_undo() {

	/*
	 *  Stop the job before putting anything back.
	 *
	 *  A large re-filing run continues on cron, so undoing one while it is
	 *  still going would have two passes fighting over the same pictures --
	 *  this one restoring a folder and the next slice moving it out again --
	 *  and the result would depend on which finished last. Whatever has been
	 *  filed so far is recorded and gets undone; the rest is simply never
	 *  filed.
	 */
	$state = get_option( VERGEML_TALK_STATE );

	if ( is_array( $state ) && ! empty( $state['active'] ) ) {
		$state['active'] = false;
		update_option( VERGEML_TALK_STATE, $state, false );
		wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
	}

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

	// The tags the guide made go with the folders; deleting a term takes it off every picture.
	if ( ! empty( $before['tags'] ) && function_exists( 'vergeml_guide_unmake_tags' ) ) {
		vergeml_guide_unmake_tags( (array) $before['tags'] );
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
			/*
			 *  Which tree. The screen asks for the literal one, draws it, then
			 *  asks again for the suggestion, so the slower answer does not
			 *  hold up the one that was actually requested.
			 */
			'mode'        => array( 'type' => 'string', 'required' => false ),
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

	/*
	 *  Where a re-filing job has got to.
	 *
	 *  Read-only and cheap, because the screen asks every couple of seconds
	 *  while a large library is being worked through.
	 */
	register_rest_route( VERGEML_REST_NS, '/folders-progress', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => function () {
			return rest_ensure_response( vergeml_talk_progress() );
		},
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

	$result = vergeml_talk_propose(
		(string) $request->get_param( 'instruction' ),
		$said,
		(string) $request->get_param( 'mode' )
	);

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
			// What the matcher files by (see core/filing.php); absent from older plans.
			'classes'  => isset( $f['classes'] ) && is_array( $f['classes'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $f['classes'] ) ) ) : array(),
			'kinds'    => isset( $f['kinds'] ) && is_array( $f['kinds'] ) ? array_values( array_filter( array_map( 'sanitize_key', $f['kinds'] ) ) ) : array(),
			'audience' => isset( $f['audience'] ) ? sanitize_text_field( (string) $f['audience'] ) : '',
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
/*
 *  The card is no longer hooked on to the Sort screen: the September 2026
 *  redesign merged the chat and the guide into one surface (js/vergeml-sort.js,
 *  driven by core/guide.php). The routes below stay; the transcript UI is kept
 *  for anything that still calls it.
 */
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
			<p class="vgml-talk-guide-link">
				<?php esc_html_e( 'Not sure where to start?', 'vergelabs-media-library' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=media-guide' ) ); ?>"><?php esc_html_e( 'Sort with a guide', 'vergelabs-media-library' ); ?></a>
				<?php esc_html_e( '— it reads the library first, proposes two structures, and asks before it moves anything.', 'vergelabs-media-library' ); ?>
			</p>
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
			'thinking2' => __( 'Looking at what is actually in your library…', 'vergelabs-media-library' ),
			'suggestion' => __( 'Or, going by what is actually in your library:', 'vergelabs-media-library' ),
	) );
}
