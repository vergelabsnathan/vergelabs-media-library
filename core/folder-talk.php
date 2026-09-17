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

/**
 *  How many described files one slice of a re-filing job reads. A slice is
 *  scored whole before any of it moves, and the heartbeat (S10.0) beats only
 *  while it moves: at 500 the screen read 55, then 501, then done
 *  (2026-09-16). A hundred steps every few seconds and costs one query more
 *  per hundred pictures.
 */
const VERGEML_TALK_SLICE = 100;

/** How many a single pass gets through before leaving the rest to the next. */
const VERGEML_TALK_PASS = 5000;

/** Seconds a background pass may spend before handing back to cron. */
const VERGEML_TALK_BUDGET = 15.0;

/*
 *  A fill that cannot stall (S10.2). Cron's tick is the run's engine; the
 *  screen's poll is its guarantee: an active run whose event is this many
 *  seconds past due is run for one short pass inside the poll's own request,
 *  a slice small enough to answer inside the request, and booked again. The
 *  pass lock keeps a tick that does arrive from working the same slice.
 */
const VERGEML_TALK_STALL      = 10;
const VERGEML_TALK_KICK_SLICE = 50;
const VERGEML_TALK_KICK_BUDGET = 10.0;
const VERGEML_TALK_PASS_LOCK  = 'vergeml_talk_passing';

/** The counts so far, written every two seconds inside a pass, read by the poll (S10.0). */
const VERGEML_TALK_BEAT = 'vergeml_talk_beat';

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
			$parent = vergeml_term_name( $by_id[ (int) $term->parent ] );
		}

		$out[] = array(
			'term_id' => (int) $term->term_id,
			'name'    => vergeml_term_name( $term ),
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

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT caption, filing FROM {$wpdb->vergeml_ai_index}
		  WHERE error = '' AND caption != ''
	   ORDER BY attachment_id ASC
		  LIMIT %d",
		VERGEML_TALK_SCAN
	), ARRAY_A );
	// phpcs:enable

	$out = array();

	foreach ( (array) $rows as $i => $row ) {
		if ( 0 === $i % $every ) {
			// The caption, and the describer's object beside it ("server rack; computer hardware"): the words the planner must answer in (C.4).
			$f      = json_decode( (string) $row['filing'], true );
			$object = is_array( $f ) && isset( $f['object'] ) ? trim( (string) $f['object'] ) : '';
			$out[]  = mb_substr( (string) $row['caption'], 0, 130 ) . ( '' !== $object ? ' [object: ' . mb_substr( $object, 0, 60 ) . ']' : '' );
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
				// The describer's own words with counts: what the plan's classes must be spelled in (C.4).
				'terms'       => function_exists( 'vergeml_filing_vocabulary' ) ? vergeml_filing_vocabulary() : array(),
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
 *  The profile a folder is filed against, from what the tree says about it.
 *
 *  The same seed for a folder the Move makes, renames or keeps by name: the
 *  draft's classes, kinds, audience and matching phrase. That is what the
 *  preview scored (vergeml_guide_draft_profile mirrors this build), so the
 *  run scores the same. A folder the draft says nothing about keeps the
 *  profile it has; a rule's Move (assign) files nothing by evidence and seeds
 *  nothing.
 */
function vergeml_talk_seed_profile( $term_id, $taxonomy, $f, $assign ) {
	if ( $assign || ! function_exists( 'vergeml_filing_profile_build' ) || empty( $f['classes'] ) ) {
		return;
	}
	$term = get_term( (int) $term_id, $taxonomy );
	if ( $term && ! is_wp_error( $term ) ) {
		vergeml_filing_profile_build( $term, $taxonomy, $f );
	}
}


/**
 *  Do it.
 *
 *  Terms first, then the re-filing. In that order because a picture cannot be
 *  put into a folder that does not exist yet, and because a failure half way
 *  through leaves folders with nothing in them rather than pictures pointing
 *  at nothing.
 *
 * @param array $folders The proposed folders. One that exists today may carry
 *                       'term_id': it is then renamed or re-parented in place
 *                       rather than matched by name, so a rename stays a rename.
 * @param array $tags    From vergeml_guide_make_tags().
 * @param array $opts    'assign'   attachment id => folder key: file exactly these
 *                                  pictures there and touch no other (a rule).
 *                                  Without it, every described picture is filed
 *                                  by evidence (core/filing.php).
 *                       'fallback' removed term id => folder key: where the
 *                                  pictures of a folder that goes end up when
 *                                  the evidence says nothing.
 *                       'reasons'  attachment id => [ why, score, runner_up,
 *                                  runner_score, nearest ]: what the matcher
 *                                  said about each picture the rule judged, so
 *                                  the move can record it and a picture the
 *                                  rule would not place can be recorded as
 *                                  exactly that. `nearest` is the folder a
 *                                  refusal was about and is optional -- a
 *                                  tuple packed before it shipped has four.
 * @return array|WP_Error What happened.
 */
function vergeml_talk_apply( $folders, $tags = array(), $opts = array() ) {

	global $wpdb;

	$opts     = is_array( $opts ) ? $opts : array();
	$assign   = isset( $opts['assign'] ) && is_array( $opts['assign'] ) ? $opts['assign'] : array();
	$fallback = isset( $opts['fallback'] ) && is_array( $opts['fallback'] ) ? $opts['fallback'] : array();
	$reasons  = isset( $opts['reasons'] ) && is_array( $opts['reasons'] ) ? $opts['reasons'] : array();

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
	$by_name  = array();
	$made_ids = array();

	foreach ( $order as $f ) {

		$parent_id  = 0;
		$parent_key = mb_strtolower( $f['parent'] );

		if ( '' !== $f['parent'] && isset( $by_name[ $parent_key ] ) ) {
			$parent_id = $by_name[ $parent_key ];
		}

		$key = vergeml_talk_key( $f['parent'], $f['name'] );

		/*
		 *  A folder that exists is addressed by its id, not its name: the
		 *  Folders screen keys its draft by term id, so "Boots" renamed to
		 *  "Footwear" is that folder with a new name, not a folder gone and a
		 *  folder made. Renamed or re-parented here, before anything is filed.
		 */
		$live = ! empty( $f['term_id'] ) ? get_term( (int) $f['term_id'], $taxonomy ) : null;

		if ( $live instanceof WP_Term ) {
			$patch = array();
			if ( vergeml_term_name( $live ) !== (string) $f['name'] ) {
				$patch['name'] = (string) $f['name'];
			}
			if ( (int) $live->parent !== (int) $parent_id && (int) $live->term_id !== (int) $parent_id ) {
				$patch['parent'] = (int) $parent_id;
			}
			if ( $patch ) {
				wp_update_term( (int) $live->term_id, $taxonomy, $patch );
			}
			// Kept, renamed or moved alike: what the draft says it is for is what it is matched against, as the preview had it.
			vergeml_talk_seed_profile( (int) $live->term_id, $taxonomy, $f, $assign );
			$ids[ $key ] = (int) $live->term_id;
			if ( ! isset( $by_name[ mb_strtolower( $f['name'] ) ] ) ) {
				$by_name[ mb_strtolower( $f['name'] ) ] = (int) $live->term_id;
			}
			continue;
		}

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
			vergeml_talk_seed_profile( (int) $existing->term_id, $taxonomy, $f, $assign );
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
			$made_ids[]  = (int) $made['term_id'];
			vergeml_talk_seed_profile( (int) $made['term_id'], $taxonomy, $f, $assign );
			if ( ! isset( $by_name[ mb_strtolower( $f['name'] ) ] ) ) {
				$by_name[ mb_strtolower( $f['name'] ) ] = (int) $made['term_id'];
			}
		}
	}
	$made = $made_ids;

	if ( ! $ids ) {
		return new WP_Error( 'no_terms', __( 'None of those folders could be created.', 'vergelabs-media-library' ) );
	}

	// -------------------------------------------------------- the vectors

	/*
	 *  Only the evidence path needs them. A rule says outright which picture
	 *  goes where, and a folder called "2026 / August" has no meaning a
	 *  vector could carry.
	 */
	$vectors = array();

	foreach ( $assign ? array() : $folders as $f ) {

		$key = vergeml_talk_key( $f['parent'], $f['name'] );

		if ( ! isset( $ids[ $key ] ) ) {
			continue;
		}

		$vector = vergeml_talk_vector( $f );

		if ( is_array( $vector ) && $vector ) {
			$vectors[ $key ] = $vector;
		}
	}

	if ( ! $vectors && ! $assign ) {
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

	/*
	 *  What goes: every folder that exists and that the tree did not claim --
	 *  by id, or by the name-and-parent lookup above, which lands in $ids
	 *  either way. A folder the draft keeps is in $ids; a folder it does not
	 *  name is not.
	 */
	$remove = array();
	$keep   = array_map( 'intval', array_values( $ids ) );

	foreach ( $before['terms'] as $term ) {
		if ( in_array( (int) $term['term_id'], $keep, true ) ) {
			continue;
		}
		// A locked folder (To sort, a folder somebody locked) is not the Move's to delete: unlock it first.
		if ( defined( 'VERGEML_FILING_LOCKED' ) && get_term_meta( (int) $term['term_id'], VERGEML_FILING_LOCKED, true ) ) {
			continue;
		}
		$remove[] = (int) $term['term_id'];
	}

	// Where a removed folder's pictures land when the evidence says nothing, and the rule's own assignments, both as term ids.
	$fallback_ids = array();
	foreach ( $fallback as $tid => $key ) {
		if ( in_array( (int) $tid, $remove, true ) && isset( $ids[ $key ] ) ) {
			$fallback_ids[ (int) $tid ] = (int) $ids[ $key ];
		}
	}
	$assign_ids = array();
	foreach ( $assign as $attachment => $key ) {
		if ( isset( $ids[ $key ] ) ) {
			$assign_ids[ (int) $attachment ] = (int) $ids[ $key ];
		}
	}
	if ( $assign && ! $assign_ids ) {
		return new WP_Error( 'empty', __( 'Nothing to apply.', 'vergelabs-media-library' ) );
	}

	/*
	 *  Everything that sits in a folder about to go is written into the undo
	 *  record now, before a single term is touched. Deleting a term takes its
	 *  memberships with it, and the pass below only records the pictures it
	 *  moves -- so a picture the pass left alone in a folder that was then
	 *  deleted had nowhere to go back to. Twenty-one folders and their
	 *  contents went that way once; undo brought back the names, empty.
	 */
	foreach ( $remove as $tid ) {
		$members = get_objects_in_term( $tid, $taxonomy );
		foreach ( is_wp_error( $members ) ? array() : (array) $members as $oid ) {
			$oid = (int) $oid;
			if ( isset( $before['files'][ $oid ] ) ) {
				continue;
			}
			$was = wp_get_object_terms( $oid, $taxonomy, array( 'fields' => 'ids' ) );
			$before['files'][ $oid ] = is_wp_error( $was ) ? array() : array_map( 'intval', $was );
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
	// The folders this Move made, so undo can take them away again once they are empty; and how long undo is offered.
	$before['made']  = $made;
	$before['until'] = time() + DAY_IN_SECONDS;

	update_option( VERGEML_TALK_UNDO, $before, false );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
	$total = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index}
		  WHERE error = '' AND embedding IS NOT NULL"
	);
	// phpcs:enable

	$state = array(
		'active'   => true,
		'taxonomy' => $taxonomy,
		'ids'      => $ids,
		'vectors'  => $vectors,
		'assign'   => $assign_ids,
		'fallback' => $fallback_ids,
		'reasons'  => $reasons,
		/*
		 *  No planner call, here or in the passes. The run files against the
		 *  profiles this request just seeded from the draft (vergeml_talk_seed_profile),
		 *  which are the profiles the preview scored -- one filing path. Until
		 *  2026-09-14 the first pass re-profiled every folder through the
		 *  planner and the number on the button (816) was not the number that
		 *  happened (487). Profiling is Step 2's, when a tree is confirmed.
		 */
		'tally'    => function_exists( 'vergeml_filing_tally_fresh' ) ? vergeml_filing_tally_fresh() : array(),
		// What the fill could not place, and what it placed in a parent because two children tied: the questions are made from these when the run ends.
		'residue'  => array(),
		'siblings' => array(),
		'either'   => array(),
		'questions' => array(),
		'names'    => array(),
		'asked'    => array(),
		'after'    => 0,
		'moved'    => 0,
		'skipped'  => 0,
		'seen'     => 0,
		'total'    => $total,
		'counts'   => array(),
		'by_term'  => array(),
		'tags'     => $tag_map,
		'tagged'   => 0,
		'until'    => $before['until'],
		/*
		 *  Held until the end. Deleting a folder while pictures that belong in
		 *  it have not been looked at yet would drop them out of every folder --
		 *  the one way this screen could lose somebody's filing rather than
		 *  change it.
		 */
		'remove'   => $remove,
		'started'  => time(),
		'ticked'   => time(),
	);

	delete_transient( VERGEML_TALK_BEAT ); // An older run's heartbeat never reads as this one's.
	update_option( VERGEML_TALK_STATE, $state, false );

	// The answer is "running, nothing seen yet"; the passes are cron's, and
	// the screen polls them. A Move answers in the time it takes to make the
	// folders, however large the library.
	vergeml_talk_refile_schedule();

	return vergeml_talk_report( $state );
}


/**
 *  Work through as much of the re-filing as the time allows.
 *
 * @param float    $deadline  When to stop and leave the rest to the next pass.
 * @param int|null $slice_cap A smaller slice than the filter's, for a pass run inside a poll (S10.2).
 * @return array The state as it now stands.
 */
function vergeml_talk_refile_run( $deadline, $slice_cap = null ) {

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
	 *  One pass at a time. A tick and a poll's pass (S10.2) that both read
	 *  `after` would both work the same slice and count it twice. The lock
	 *  outlives the longest pass (a slice of 500 at the box's five a second)
	 *  and is dropped as the pass ends, so a pass php-fpm killed holds
	 *  nothing up for more than two minutes.
	 */
	if ( get_transient( VERGEML_TALK_PASS_LOCK ) ) {
		return $state;
	}
	set_transient( VERGEML_TALK_PASS_LOCK, time(), 120 );

	// A Move already in flight across the deploy that added these.
	foreach ( array( 'residue' => array(), 'siblings' => array(), 'either' => array(), 'questions' => array(), 'names' => array(), 'asked' => array(), 'tally' => vergeml_filing_tally_fresh() ) as $k => $fresh ) {
		if ( ! isset( $state[ $k ] ) || ! is_array( $state[ $k ] ) ) {
			$state[ $k ] = $fresh;
		}
	}

	/*
	 *  What each picture was in, collected in memory and written once when the
	 *  pass ends. Appending to the undo record slice by slice would rewrite an
	 *  option megabytes long a few hundred times over a large library, which
	 *  costs more than the re-filing it is recording.
	 */
	$undo = array();
	$pass = 0;
	$beat = microtime( true );

	/*
	 *  What this pass did and why, for the librarian's own record.
	 *
	 *  This is the pass that files most of the pictures on most sites, and
	 *  until now it wrote nothing down at all: the matcher worked out a score,
	 *  a runner-up and a word for every picture, acted on them, and dropped
	 *  them. So "picture 1779 is in Architecture" was recoverable and "because
	 *  it scored 0.81 against 0.44" was not.
	 *
	 *  Collected in memory and written once at the end of the pass, for the
	 *  same reason the undo record is: a pass is a few hundred pictures, and
	 *  an insert per picture is a few hundred queries where one will do.
	 */
	$trail = array();

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
	if ( $slice_cap ) {
		$slice  = min( $slice, max( 1, (int) $slice_cap ) );
		$budget = min( $budget, $slice );
	}

	do {
		// Round 2 (S10.7) looks only at what round 1 left unplaced.
		$only = ! empty( $state['round_ids'] )
			? ' AND i.attachment_id IN (' . implode( ',', array_map( 'intval', (array) $state['round_ids'] ) ) . ')'
			: '';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table; the ids are cast to int.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT i.attachment_id, i.embedding, i.kind, i.filing, i.tags, i.prompt_hash, i.model_version, pm.meta_value AS placed_by,
			        ( SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
			            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			            JOIN {$wpdb->termmeta} tm ON tm.term_id = tt.term_id AND tm.meta_key = %s AND tm.meta_value = '1'
			           WHERE tr.object_id = i.attachment_id AND tt.taxonomy = %s ) AS in_locked
			   FROM {$wpdb->vergeml_ai_index} i
			   LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = i.attachment_id AND pm.meta_key = %s
			  WHERE i.error = '' AND i.embedding IS NOT NULL AND i.attachment_id > %d{$only}
		   ORDER BY i.attachment_id ASC
			  LIMIT %d",
			VERGEML_FILING_LOCKED,
			$taxonomy,
			VERGEML_FILING_PLACED_BY,
			(int) $state['after'],
			$slice
		), ARRAY_A );
		// phpcs:enable

		/*
		 *  Filed by evidence (core/filing.php): the picture's kind and
		 *  audience gate the folders, its object class is matched against
		 *  theirs, and the vector only breaks ties. The picks for the slice
		 *  come from the one function the preview counts with, against the
		 *  profiles this Move seeded, so the number on the button is the
		 *  number that happens.
		 */
		if ( empty( $state['assign'] ) && ! isset( $profiles ) ) {
			$profiles = vergeml_filing_profiles( array_values( (array) $state['ids'] ), $taxonomy );
		}
		$picks = empty( $state['assign'] ) ? vergeml_filing_count( $profiles, (array) $rows ) : null;

		foreach ( (array) $rows as $row ) {

			$attachment = (int) $row['attachment_id'];

			// Ordered by id and remembered, so a pass that stops halfway leaves a
			// place to carry on from rather than a sample to take again.
			$state['after'] = $attachment;
			$state['seen']  = (int) $state['seen'] + 1;

			/*
			 *  A heartbeat every two seconds (S10.0). The state is written once
			 *  a pass, and on the shop a pass is the whole fill -- 626 pictures
			 *  in 27 s -- so the screen polled a zero for half a minute, said
			 *  "nothing moved", and then was done (Nathan, 2026-09-16). What
			 *  the poll reads while a pass runs is this: the counts so far.
			 */
			if ( microtime( true ) - $beat >= 2 ) {
				$beat = microtime( true );
				set_transient( VERGEML_TALK_BEAT, array( 'seen' => (int) $state['seen'], 'moved' => (int) $state['moved'], 'by_term' => $state['by_term'], 'tally' => $state['tally'], 'ticked' => time() ), 300 );
			}

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
			 *  A rule said outright where each picture goes. Those pictures
			 *  go there; every other picture is left exactly as it is -- no
			 *  evidence, no eviction, no guess.
			 */
			if ( ! empty( $state['assign'] ) ) {
				if ( ! isset( $state['assign'][ $attachment ] ) ) {
					/*
					 *  The rule looked at this picture and would not place
					 *  it. That is a row with no folder in it -- the only
					 *  record anywhere of a picture the evidence had nothing
					 *  to say about, and the answer to the question this
					 *  plugin could not answer at all before.
					 */
					if ( isset( $state['reasons'][ $attachment ] ) ) {
						$trail[] = array( $attachment, 0, vergeml_talk_reason( $state['reasons'][ $attachment ], $row ) );
					}
					continue;
				}
				$to  = (int) $state['assign'][ $attachment ];
				$was = wp_get_object_terms( $attachment, $taxonomy, array( 'fields' => 'ids' ) );
				$undo[ $attachment ] = is_wp_error( $was ) ? array() : array_map( 'intval', $was );
				wp_set_object_terms( $attachment, array( $to ), $taxonomy, false );
				$state['by_term'][ $to ] = isset( $state['by_term'][ $to ] ) ? (int) $state['by_term'][ $to ] + 1 : 1;
				$state['moved'] = (int) $state['moved'] + 1;
				$trail[]        = array( $attachment, $to, vergeml_talk_reason( isset( $state['reasons'][ $attachment ] ) ? $state['reasons'][ $attachment ] : null, $row ) );
				continue;
			}

			$facts = vergeml_filing_facts( $row );
			$pick  = $picks['picks'][ $attachment ];

			// Placed by hand, or in a locked folder: not the fill's to move, evict or ask about. Looked at, kept, no row.
			if ( vergeml_filing_kept( $pick ) ) {
				vergeml_filing_tally( $state['tally'], $pick );
				/*
				 *  The one way a hand-placed picture could still lose its folder:
				 *  the folder itself goes with this Move. It follows that folder's
				 *  pictures to the one that absorbed it, still the user's, rather
				 *  than dropping out of every folder when the term is deleted.
				 */
				if ( 'placed' === $pick['why'] && ! empty( $state['fallback'] ) ) {
					$in = wp_get_object_terms( $attachment, $taxonomy, array( 'fields' => 'ids' ) );
					$in = is_wp_error( $in ) ? array() : array_map( 'intval', $in );
					foreach ( $in as $tid ) {
						if ( isset( $state['fallback'][ $tid ] ) ) {
							$to = (int) $state['fallback'][ $tid ];
							$undo[ $attachment ] = $in;
							wp_set_object_terms( $attachment, array( $to ), $taxonomy, false );
							$state['by_term'][ $to ] = isset( $state['by_term'][ $to ] ) ? (int) $state['by_term'][ $to ] + 1 : 1;
							$state['moved'] = (int) $state['moved'] + 1;
							$trail[]        = array( $attachment, $to, vergeml_talk_reason( array( 'placed', 0.0, 0, 0.0, 0 ), $row ) );
							break;
						}
					}
				}
				continue;
			}

			/*
			 *  A picture in a folder that goes, with nowhere the evidence
			 *  would send it, follows the folder's own pictures to the place
			 *  the tree said they go -- the parent that absorbed it. It never
			 *  simply drops out of every folder because its folder went.
			 */
			if ( ! $pick['term_id'] && ! empty( $state['fallback'] ) ) {
				$in = wp_get_object_terms( $attachment, $taxonomy, array( 'fields' => 'ids' ) );
				foreach ( is_wp_error( $in ) ? array() : array_map( 'intval', $in ) as $tid ) {
					if ( isset( $state['fallback'][ $tid ] ) ) {
						$pick['term_id'] = (int) $state['fallback'][ $tid ];
						$pick['outcome'] = 'fits';
						break;
					}
				}
			}

			vergeml_filing_tally( $state['tally'], $pick );

			if ( ! $pick['term_id'] ) {
				$state['skipped'] = (int) $state['skipped'] + 1;
				$why              = isset( $pick['why'] ) ? $pick['why'] : 'floor';
				$state['unfiled'][ $why ] = isset( $state['unfiled'][ $why ] ) ? (int) $state['unfiled'][ $why ] + 1 : 1;
				// What this round left: round 2 looks at these again, over what the folders hold by then (S10.7).
				$state['leftover'][] = $attachment;
				if ( vergeml_filing_is_either( $pick ) ) {
					/*
					 *  Too close to call between two folders that are not siblings:
					 *  one either/or question per pair, each picture remembered with
					 *  its own best of the two so "split" can file it there.
					 */
					$two = array_map( 'intval', (array) $pick['children'] );
					$key = min( $two ) . ':' . max( $two );
					$state['either'][ $key ]['ids'][ $attachment ] = $two[0];
					$state['either'][ $key ]['children'][ $two[0] ] = isset( $state['either'][ $key ]['children'][ $two[0] ] ) ? $state['either'][ $key ]['children'][ $two[0] ] + 1 : 1;
					$state['either'][ $key ]['children'][ $two[1] ] = isset( $state['either'][ $key ]['children'][ $two[1] ] ) ? $state['either'][ $key ]['children'][ $two[1] ] : 0;
				} else {
					// The residue: grouped and asked about when the run ends, never left in no folder. With the folder it came closest to, for "Put in X".
					$state['residue'][ $attachment ] = isset( $pick['nearest'] ) ? (int) $pick['nearest'] : 0;
				}

				// Left alone, and now on the record as left alone: the word,
				// the score it did reach, and the folder it could not beat.
				// Round 2 looks at a leftover again and writes only when it places it (S10.7): "still nothing" is round 1's row.
				if ( empty( $state['round'] ) || 2 !== (int) $state['round'] ) {
					$trail[] = array(
						$attachment,
						0,
						vergeml_talk_reason( array( $why, $pick['score'], $pick['runner_up'], $pick['runner_score'], isset( $pick['nearest'] ) ? $pick['nearest'] : 0 ), $row ),
					);
				}
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
			$state['by_term'][ (int) $pick['term_id'] ] = isset( $state['by_term'][ (int) $pick['term_id'] ] )
				? (int) $state['by_term'][ (int) $pick['term_id'] ] + 1
				: 1;

			$state['moved'] = (int) $state['moved'] + 1;

			/*
			 *  Two children tied and the parent took it. Remembered per parent,
			 *  with the child that came first, so one question can ask about
			 *  the whole group and "split" can file each by its own best.
			 */
			if ( 'siblings' === $pick['outcome'] ) {
				$parent = (int) $pick['parent_id'];
				$state['siblings'][ $parent ]['ids'][ $attachment ] = (int) $pick['children'][0];
				foreach ( (array) $pick['children'] as $child ) {
					$state['siblings'][ $parent ]['children'][ (int) $child ] = isset( $state['siblings'][ $parent ]['children'][ (int) $child ] ) ? $state['siblings'][ $parent ]['children'][ (int) $child ] + 1 : 1;
				}
			}

			/*
			 *  The word is the matcher's own, even here: a picture that got a
			 *  folder because the one it was in went away carries 'floor' and
			 *  the score it actually reached, not a tidier 'ok' that nothing
			 *  computed.
			 */
			$trail[] = array(
				$attachment,
				(int) $pick['term_id'],
				vergeml_talk_reason( array( $pick['why'], $pick['score'], $pick['runner_up'], $pick['runner_score'], isset( $pick['nearest'] ) ? $pick['nearest'] : 0 ), $row ),
			);
		}

		$pass += count( (array) $rows );

		if ( count( (array) $rows ) < $slice ) {
			/*
			 *  The fill learns from its own placements (S10.7). A round that
			 *  moved pictures and left others unplaced is followed by one more:
			 *  the folders now hold what round 1 put there, a folder of three
			 *  or more is read over its members (vergeml_filing_profiles), and
			 *  the leftovers get a second look against that. Only the
			 *  leftovers -- what round 1 placed stays placed -- and only once:
			 *  a third round would read the same folders. Nothing is
			 *  re-described between rounds. The tally gives the leftovers back
			 *  before round 2 counts them again, so a picture is counted once.
			 */
			$round = isset( $state['round'] ) ? (int) $state['round'] : 1;
			$state['rounds'][ $round ] = (int) $state['moved'];
			if ( 1 === $round && empty( $state['assign'] ) && (int) $state['moved'] > 0 && ! empty( $state['leftover'] ) ) {
				$left = array_values( array_unique( array_map( 'intval', (array) $state['leftover'] ) ) );
				$n    = count( $left );
				$state['round']     = 2;
				$state['round_ids'] = $left;
				$state['leftover']  = array();
				$state['after']     = 0;
				$state['seen']      = max( 0, (int) $state['seen'] - $n );
				$state['skipped']   = max( 0, (int) $state['skipped'] - $n );
				$state['residue']   = array();
				$state['either']    = array();
				foreach ( array( 'floor', 'margin', 'gated' ) as $why ) {
					unset( $state['unfiled'][ $why ] );
				}
				$state['tally']['looked']  = max( 0, (int) $state['tally']['looked'] - $n );
				$state['tally']['nothing'] = max( 0, (int) $state['tally']['nothing'] - $n );
				$state['tally']['either']  = 0;
				$state['tally']['why']     = array( 'floor' => 0, 'margin' => 0, 'gated' => 0 );
				unset( $profiles ); // Read again over what the folders hold now.
				continue;
			}
			// Unfinished here means out of time for the names: the run stays active and the next pass finds no rows and finishes.
			vergeml_talk_refile_finish( $state, $deadline );
			break;
		}
	} while ( $pass < $budget && microtime( true ) < $deadline );

	// A pass that leaves the job running has still moved pictures: the folders in every open tree fill as they land.
	if ( ! empty( $state['active'] ) && $pass > 0 && function_exists( 'vergeml_folders_moved' ) ) {
		vergeml_folders_moved( 'chunk' );
	}

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

	// When this pass last wrote: the screen reads "nothing moved for 48 s" off it, and the poll's kick reads a stall (S10.0, S10.2).
	$state['ticked'] = time();
	update_option( VERGEML_TALK_STATE, $state, false );
	delete_transient( VERGEML_TALK_PASS_LOCK );
	delete_transient( VERGEML_TALK_BEAT );

	vergeml_talk_trail_write( $trail );

	return $state;
}


/**
 *  A reason, in the shape the move row keeps it.
 *
 *  $packed is [ why, score, runner_up, runner_score ] as the matcher gave it
 *  -- straight from vergeml_filing_pick() here, or carried from the rule that
 *  ran before the Move. Without one, the folder came from a rule that groups
 *  by tag, kind or date: nothing scored that placement, so it is a 'plan' and
 *  the score columns stay empty rather than saying zero.
 *
 *  Either way the index row's prompt and model come along, because they are
 *  what the picture was judged on at that moment, and they are the step that
 *  joins a move to the description it rests on.
 */

function vergeml_talk_reason( $packed, $row ) {

	$reason = array(
		'prompt_hash'   => isset( $row['prompt_hash'] ) ? (string) $row['prompt_hash'] : '',
		'model_version' => isset( $row['model_version'] ) ? (string) $row['model_version'] : '',
	);

	if ( ! is_array( $packed ) || ! isset( $packed[0], $packed[1], $packed[2], $packed[3] ) ) {
		$reason['why'] = 'plan';
		return $reason;
	}

	$reason['why']          = (string) $packed[0];
	$reason['score']        = (float) $packed[1];
	$reason['runner_up']    = (int) $packed[2];
	$reason['runner_score'] = (float) $packed[3];

	// The folder it nearly went to, on a refusal. Optional: a tuple packed by a
	// plan already in flight across a deploy has four entries and no fifth.
	if ( isset( $packed[4] ) ) {
		$reason['nearest'] = (int) $packed[4];
	}

	return $reason;
}


/**
 *  The pass's moves and abstentions, into the librarian's record.
 *
 *  One batch a day for this scheme, the same way the suggestions and the
 *  spoken commands get theirs, and it is only asked for when there is a first
 *  row to put in it -- a pass that moved nothing and judged nothing writes no
 *  batch and no rows.
 *
 *  Best effort on purpose: this records what happened, it does not decide
 *  anything, and a site where the librarian's tables are missing must go on
 *  filing pictures exactly as it did before.
 */

function vergeml_talk_trail_write( $trail ) {

	if ( ! $trail || ! function_exists( 'vergeml_autofile_batch' ) || ! function_exists( 'vergeml_librarian_moves_insert' ) ) {
		return;
	}

	$batch_id = vergeml_autofile_batch( 'refile' );

	if ( is_wp_error( $batch_id ) ) {
		return;
	}

	$moves = array();

	foreach ( $trail as $t ) {
		$moves[] = array( (int) $batch_id, (int) $t[0], (int) $t[1], 0, $t[2] );
	}

	vergeml_librarian_moves_insert( $moves );

	/*
	 *  Which batches this Move's rows are in, kept beside the undo record.
	 *
	 *  The undo restores terms from that record and has no other way to find
	 *  the rows it just reversed: a picture can have older rows from earlier
	 *  passes, and marking those would say an undo reversed a move it never
	 *  touched. A large pass runs over several cron slices and writes to the
	 *  same day's batch each time, so this is a set rather than a value.
	 */
	$before = get_option( VERGEML_TALK_UNDO );

	if ( is_array( $before ) ) {

		$batches   = isset( $before['batches'] ) ? array_map( 'intval', (array) $before['batches'] ) : array();
		$batches[] = (int) $batch_id;

		$before['batches'] = array_values( array_unique( $batches ) );

		update_option( VERGEML_TALK_UNDO, $before, false );
	}
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
 *  The questions first, then the deletes (S11 review): naming a residue
 *  group is a 20 s service call each, and the pass that reaches the end can
 *  be a poll's kick inside the browser's request. Names are asked in the
 *  time the pass has left; a finish that runs out returns false with the run
 *  still active, and the next pass -- cron's or the next poll's -- carries
 *  on from the names already kept. The terms go only once the questions are
 *  built, so a request killed mid-way has deleted nothing.
 *
 * @param array $state    Taken by reference so the caller writes it once.
 * @param float $deadline The pass's own; the naming stops at it.
 * @return bool Whether the run is finished.
 */
function vergeml_talk_refile_finish( &$state, $deadline = null ) {

	$taxonomy = (string) $state['taxonomy'];

	// What the fill could not decide, as questions -- few, grouped, named.
	$questions = vergeml_talk_questions_build( $state, $deadline );
	if ( null === $questions ) {
		return false;
	}
	$state['questions'] = $questions;

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

	// The Move is complete: every open surface re-reads the tree and its counts.
	if ( function_exists( 'vergeml_folders_moved' ) ) {
		vergeml_folders_moved( 'refile' );
	}
	return true;
}


/** Ask WordPress to carry on, and do not wait for a visitor to make it. */
function vergeml_talk_refile_schedule() {

	if ( ! wp_next_scheduled( VERGEML_TALK_HOOK ) ) {
		wp_schedule_single_event( time(), VERGEML_TALK_HOOK );
	}

	/*
	 *  WP-Cron fires on page loads, so on a site nobody is browsing a job that
	 *  says it is still going simply stops. The describe run learned that the
	 *  hard way; re-filing makes the same promise and needs the same nudge --
	 *  and, since 2026-09-16, the same nudge exactly (core/ai-background.php,
	 *  vergeml_ai_run_nudge). Until then this posted a fresh key without
	 *  taking cron's lock, which wp-cron.php refuses on line one of its lock
	 *  check: on the box's shop site the first tick's own spawn held the lock
	 *  under a key no arriving request carried, every later post was turned
	 *  away, and the fill stood for four minutes (C.5, S10.2).
	 *
	 *  Outside a cron run core's spawn_cron() takes the lock and posts; inside
	 *  a tick it refuses outright, so the next request is chained the way
	 *  core's own is: the lock re-taken under a new key, and that key posted.
	 */
	if ( ! defined( 'DOING_CRON' ) ) {
		spawn_cron();
		return;
	}

	$key = sprintf( '%.22F', microtime( true ) );
	set_transient( 'doing_cron', $key );

	// A loopback to this site's own wp-cron.php, on the rule core's spawn_cron()
	// uses: unverified unless the owner turns https_local_ssl_verify on.
	wp_remote_post( add_query_arg( 'doing_wp_cron', $key, site_url( 'wp-cron.php' ) ), array(
		'timeout'   => 0.01,
		'blocking'  => false,
		'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		'headers'   => array( 'Cache-Control' => 'no-cache' ),
	) );
}


/**
 *  The poll's guarantee (S10.2): an active run whose event cron has not
 *  honoured for VERGEML_TALK_STALL seconds is run here, one short pass, and
 *  booked again. The run's progress never depends on a chained spawn
 *  arriving; a cron that works is never second-guessed, because its event
 *  is taken off the schedule the moment it fires.
 *
 * @param array $state The run as the poll found it.
 * @return array The state after the pass, or as it was.
 */
function vergeml_talk_refile_kick( $state ) {

	$next = wp_next_scheduled( VERGEML_TALK_HOOK );
	if ( false === $next || $next > time() - VERGEML_TALK_STALL || get_transient( VERGEML_TALK_PASS_LOCK ) ) {
		return $state;
	}

	$state = vergeml_talk_refile_run( microtime( true ) + VERGEML_TALK_KICK_BUDGET, VERGEML_TALK_KICK_SLICE );

	if ( ! empty( $state['active'] ) ) {
		/*
		 *  Booked again, already late: a cron that has come back takes it on
		 *  the nudge, and the next poll takes it if cron has not -- the run
		 *  goes on at the poll's pace, never at a stuck lock's.
		 */
		wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
		wp_schedule_single_event( time() - VERGEML_TALK_STALL, VERGEML_TALK_HOOK );
		vergeml_talk_refile_schedule();
	}

	return $state;
}


add_action( VERGEML_TALK_HOOK, 'vergeml_talk_refile_event' );

function vergeml_talk_refile_event() {

	/*
	 *  Another pass holds the slice (a poll's kick, S10.2). Booked a stall's
	 *  length out and not posted: booked at time() and chained, this tick met
	 *  the lock again at once and spun a loopback a second for as long as the
	 *  lock stood -- ten seconds behind a kick, two minutes behind a pass
	 *  php-fpm killed (S11 review). The pass that holds the lock books the
	 *  run on when it ends; this booking is for the case it never does.
	 */
	if ( get_transient( VERGEML_TALK_PASS_LOCK ) ) {
		wp_schedule_single_event( time() + VERGEML_TALK_STALL, VERGEML_TALK_HOOK );
		return;
	}

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

	// The counts a running pass has reached since the state was last written (the heartbeat, S10.0).
	if ( ! empty( $state['active'] ) ) {
		$beat = get_transient( VERGEML_TALK_BEAT );
		if ( is_array( $beat ) && (int) $beat['seen'] > (int) ( isset( $state['seen'] ) ? $state['seen'] : 0 ) ) {
			$state = array_merge( $state, $beat );
		}
	}

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

	$by_term = isset( $state['by_term'] ) ? (array) $state['by_term'] : array();

	return array(
		'moved'     => $moved,
		'skipped'   => isset( $state['skipped'] ) ? (int) $state['skipped'] : 0,
		// Pictures looked at and left where they were, whatever the reason.
		'stayed'    => max( 0, $seen - $moved ),
		'folders'   => max( count( $counts ), count( $by_term ) ),
		'tagged'    => $tagged,
		'removed'   => isset( $state['removed'] ) ? (int) $state['removed'] : 0,
		'counts'    => $counts,
		// Pictures landed so far, by term id: what the tree fills its rows from while a Move runs.
		'by_term'   => $by_term,
		'running'   => $running,
		'stopped'   => ! empty( $state['stopped'] ),
		'seen'      => $seen,
		'total'     => $total,
		'remaining' => max( 0, $total - $seen ),
		'unfiled'   => isset( $state['unfiled'] ) ? (array) $state['unfiled'] : array(),
		// The outcomes, as the Fill step shows them: fits / siblings / nothing, sure / likely, kept.
		'tally'     => isset( $state['tally'] ) && is_array( $state['tally'] ) ? $state['tally'] : vergeml_filing_tally_fresh(),
		// How many questions the run left open; the questions themselves are /guide/questions.
		'questions' => isset( $state['questions'] ) ? count( array_filter( (array) $state['questions'], function ( $q ) { return empty( $q['answered'] ); } ) ) : 0,
		'until'     => isset( $state['until'] ) ? (int) $state['until'] : 0,
		// The rounds (S10.7): round => pictures placed by its end; round 2 re-reads the folders over what round 1 put in them.
		'round'     => isset( $state['round'] ) ? (int) $state['round'] : 1,
		'rounds'    => isset( $state['rounds'] ) && is_array( $state['rounds'] ) ? array_map( 'intval', $state['rounds'] ) : array(),
		'started'   => isset( $state['started'] ) ? (int) $state['started'] : 0,
		// When a pass last wrote: the screen's stall line counts from here (S10.0).
		'ticked'    => isset( $state['ticked'] ) ? (int) $state['ticked'] : ( isset( $state['started'] ) ? (int) $state['started'] : 0 ),
		'now'       => time(),
		'message'   => $message,
	);
}


/**
 *  Stop a Move where it is.
 *
 *  What has moved stays moved, and stays undoable; what has not been looked
 *  at is left where it was; the folders the Move was going to remove at the
 *  end are not removed, because the pictures in them have not all been
 *  re-filed. Says so in the same report the poll reads.
 */
function vergeml_talk_refile_stop() {

	$state = get_option( VERGEML_TALK_STATE );

	if ( ! is_array( $state ) ) {
		return array( 'running' => false, 'seen' => 0, 'total' => 0, 'remaining' => 0, 'moved' => 0 );
	}

	if ( ! empty( $state['active'] ) ) {
		$state['active']  = false;
		$state['stopped'] = true;
		$state['remove']  = array();
		update_option( VERGEML_TALK_STATE, $state, false );
		delete_transient( VERGEML_TALK_BEAT );
		wp_clear_scheduled_hook( VERGEML_TALK_HOOK );

		if ( function_exists( 'vergeml_folders_moved' ) ) {
			vergeml_folders_moved( 'stop' );
		}
	}

	return vergeml_talk_report( $state );
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
		/* translators: 1: how many pictures were re-filed, 2: how many folders they went into */
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

	// And a job whose event cron has left standing is run here, one pass (S10.2).
	if ( ! empty( $state['active'] ) ) {
		$state = vergeml_talk_refile_kick( $state );
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

	// Offered for a day. After that the library has moved on and putting it back would undo more than the Move.
	if ( ! empty( $before['until'] ) && time() > (int) $before['until'] ) {
		delete_option( VERGEML_TALK_UNDO );
		return new WP_Error( 'expired', __( 'The day to undo this has passed.', 'vergelabs-media-library' ) );
	}

	$taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';

	if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'no_taxonomy', __( 'No folders are set up on this site.', 'vergelabs-media-library' ) );
	}

	/*
	 *  The folders that existed, back first, so files have somewhere to go --
	 *  parents before children, and each child under the parent it had. The
	 *  record carries the parent's name; recreating everything at the top
	 *  level flattened a tree once and left the shape to be rebuilt by hand.
	 */
	$ids     = array();
	$by_name = array();
	$terms   = (array) $before['terms'];
	usort( $terms, function ( $a, $b ) {
		return ( '' === (string) ( isset( $a['parent'] ) ? $a['parent'] : '' ) ? 0 : 1 ) - ( '' === (string) ( isset( $b['parent'] ) ? $b['parent'] : '' ) ? 0 : 1 );
	} );

	foreach ( $terms as $term ) {

		$parent_name = (string) ( isset( $term['parent'] ) ? $term['parent'] : '' );
		$parent_id   = '' !== $parent_name && isset( $by_name[ mb_strtolower( $parent_name ) ] ) ? $by_name[ mb_strtolower( $parent_name ) ] : 0;

		/*
		 *  A folder the Move renamed or moved is still there under its id:
		 *  it goes back to its old name and place rather than being made
		 *  again beside itself.
		 */
		$still = get_term( (int) $term['term_id'], $taxonomy );
		if ( $still instanceof WP_Term ) {
			$patch = array();
			if ( vergeml_term_name( $still ) !== (string) $term['name'] ) {
				$patch['name'] = (string) $term['name'];
			}
			if ( (int) $still->parent !== (int) $parent_id && (int) $still->term_id !== (int) $parent_id ) {
				$patch['parent'] = (int) $parent_id;
			}
			if ( $patch ) {
				wp_update_term( (int) $still->term_id, $taxonomy, $patch );
			}
			$ids[ (int) $term['term_id'] ] = (int) $still->term_id;
			$by_name[ mb_strtolower( $term['name'] ) ] = (int) $still->term_id;
			continue;
		}

		$found = get_terms( array(
			'taxonomy'   => $taxonomy,
			'name'       => $term['name'],
			'parent'     => $parent_id,
			'hide_empty' => false,
			'number'     => 1,
		) );
		$existing = ( ! is_wp_error( $found ) && $found ) ? $found[0] : null;

		if ( null !== $existing ) {
			$ids[ (int) $term['term_id'] ] = (int) $existing->term_id;
			$by_name[ mb_strtolower( $term['name'] ) ] = (int) $existing->term_id;
			continue;
		}

		$made = wp_insert_term( $term['name'], $taxonomy, array( 'parent' => $parent_id ) );

		if ( is_wp_error( $made ) && 'term_exists' === $made->get_error_code() && $parent_id > 0 ) {
			$made = wp_insert_term( $term['name'], $taxonomy, array( 'parent' => $parent_id, 'slug' => sanitize_title( $parent_name . '-' . $term['name'] ) ) );
		}

		if ( ! is_wp_error( $made ) && isset( $made['term_id'] ) ) {
			$ids[ (int) $term['term_id'] ] = (int) $made['term_id'];
			$by_name[ mb_strtolower( $term['name'] ) ] = (int) $made['term_id'];
		}
	}

	$put      = 0;
	$restored = array();

	foreach ( (array) $before['files'] as $attachment => $terms ) {

		$back = array();

		foreach ( (array) $terms as $old ) {
			if ( isset( $ids[ (int) $old ] ) ) {
				$back[] = $ids[ (int) $old ];
			}
		}

		wp_set_object_terms( (int) $attachment, $back, $taxonomy, false );
		$put++;
		$restored[] = (int) $attachment;
	}

	// An answer's "new folder" or "put in" marked its pictures as the user's; that goes back with them.
	foreach ( (array) ( isset( $before['placed'] ) ? $before['placed'] : array() ) as $attachment ) {
		delete_post_meta( (int) $attachment, VERGEML_FILING_PLACED_BY );
	}

	/*
	 *  And the record says so.
	 *
	 *  Putting the terms back without marking the rows left the table asserting
	 *  placements that had just been reversed -- the one correction in this
	 *  work, and it corrects a record rather than a decision. Only the rows
	 *  this Move's own passes wrote, only the pictures actually put back, and
	 *  never an abstention: nothing moved there, so nothing was reversed.
	 */
	$batches = isset( $before['batches'] ) ? (array) $before['batches'] : array();

	$marked = function_exists( 'vergeml_librarian_moves_undone' )
		? vergeml_librarian_moves_undone( $batches, $restored )
		: 0;

	// And who pressed it, beside what it did.
	if ( function_exists( 'vergeml_librarian_batches_undone_by' ) ) {
		vergeml_librarian_batches_undone_by( $batches, (int) get_current_user_id() );
	}

	// The tags the guide made go with the folders; deleting a term takes it off every picture.
	if ( ! empty( $before['tags'] ) && function_exists( 'vergeml_guide_unmake_tags' ) ) {
		vergeml_guide_unmake_tags( (array) $before['tags'] );
	}

	/*
	 *  The folders the Move made, taken away again -- only those, and only
	 *  once the pictures are back where they were and the folder holds
	 *  nothing. A folder somebody has since put a picture in stays.
	 */
	$unmade = 0;
	foreach ( (array) ( isset( $before['made'] ) ? $before['made'] : array() ) as $tid ) {
		$term = get_term( (int) $tid, $taxonomy );
		if ( ! ( $term instanceof WP_Term ) ) {
			continue;
		}
		$held = get_objects_in_term( (int) $tid, $taxonomy );
		$kids = get_terms( array( 'taxonomy' => $taxonomy, 'parent' => (int) $tid, 'hide_empty' => false, 'fields' => 'ids', 'number' => 1 ) );
		if ( ( is_wp_error( $held ) || ! $held ) && ( is_wp_error( $kids ) || ! $kids ) ) {
			wp_delete_term( (int) $tid, $taxonomy );
			$unmade++;
		}
	}

	delete_option( VERGEML_TALK_UNDO );

	/*
	 *  The questions were about a fill that is now put back: an answer to one
	 *  would move pictures the undo just restored. Closed with the Move, and
	 *  the folders the answers made are no longer "new".
	 */
	$state = get_option( VERGEML_TALK_STATE );
	if ( is_array( $state ) && ( ! empty( $state['questions'] ) || ! empty( $state['made_by_answer'] ) ) ) {
		$state['questions']      = array();
		$state['made_by_answer'] = array();
		update_option( VERGEML_TALK_STATE, $state, false );
	}

	if ( function_exists( 'vergeml_folders_moved' ) ) {
		vergeml_folders_moved( 'undo' );
	}

	return array(
		'restored' => $put,
		'unmade'   => $unmade,
		'marked'   => $marked,
		'message'  => sprintf(
			/* translators: %s: how many pictures went back. */
			_n( '%s picture put back.', '%s pictures put back.', $put, 'vergelabs-media-library' ),
			number_format_i18n( $put )
		),
	);
}


/** Whether a Move can still be undone, and until when. */
function vergeml_talk_undo_available() {

	$before = get_option( VERGEML_TALK_UNDO );

	if ( ! is_array( $before ) || empty( $before['terms'] ) ) {
		return array( 'available' => false, 'until' => 0 );
	}

	$until = isset( $before['until'] ) ? (int) $before['until'] : 0;

	if ( $until > 0 && time() > $until ) {
		return array( 'available' => false, 'until' => $until );
	}

	return array( 'available' => true, 'until' => $until );
}


/* ---------------------------------------------------------- the questions */

/*
 *  What the fill could not decide, asked about -- few, grouped, named.
 *
 *  A Move used to end with "513 in no folder" and a sentence about why. Now
 *  it ends with questions: one per parent that took pictures two of its
 *  children tied over ("232 fit both Server racks and Cooling"), one per
 *  pair of folders that are not siblings and tied ("12 pictures: Hardware or
 *  Server racks?"), one per group of the residue ("17 look like robot arms",
 *  "7 mixed, mostly coffee machines"), one for the small groups together and
 *  one for whatever has no class ("18 with nothing to go on"). Every answer is a click,
 *  and every answer leaves the pictures in a folder: "leave them" is To
 *  sort, a real folder, never nothing.
 */

/**
 *  Built once, when the run ends (vergeml_talk_refile_finish). The residue's
 *  facts are read back off the index; each photo group asked about gets a
 *  name from one metered call, cached in the state by its members so the same
 *  group is never named twice; a kind group (screenshots, diagrams) is named
 *  for its kind; "put in" offers the folder most of a group came closest to.
 *
 *  Each group is asked once a run ('asked', written into the state before
 *  the call, so a request killed mid-call does not ask again either), and
 *  only while the pass has time: past the deadline the questions are not
 *  built and null comes back, the names so far kept for the next pass.
 *
 * @param array      $state
 * @param float|null $deadline
 * @return array|null The questions, or null when the pass ran out of time before every name was asked.
 */
function vergeml_talk_questions_build( &$state, $deadline = null ) {

	global $wpdb;

	$taxonomy = (string) $state['taxonomy'];
	$siblings = isset( $state['siblings'] ) ? (array) $state['siblings'] : array();
	$either   = isset( $state['either'] ) ? (array) $state['either'] : array();

	/*
	 *  attachment => the folder it came closest to. A run in flight across
	 *  the deploy that added the nearest kept a plain list of ids; read as
	 *  such, with no folder near any of them.
	 */
	$raw     = isset( $state['residue'] ) ? (array) $state['residue'] : array();
	$is_list = array_keys( $raw ) === range( 0, count( $raw ) - 1 );
	$near_by = array();
	foreach ( $raw as $k => $v ) {
		if ( $is_list ) {
			$near_by[ (int) $v ] = 0;
		} else {
			$near_by[ (int) $k ] = (int) $v;
		}
	}
	$residue = array_keys( $near_by );

	$facts    = array();
	$captions = array();
	foreach ( array_chunk( $residue, 500 ) as $chunk ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- this plugin's own table; ids are integers.
		foreach ( (array) $wpdb->get_results( "SELECT attachment_id, embedding, kind, filing, caption FROM {$wpdb->vergeml_ai_index} WHERE attachment_id IN (" . implode( ',', array_map( 'intval', $chunk ) ) . ')', ARRAY_A ) as $row ) {
			$facts[ (int) $row['attachment_id'] ]    = vergeml_filing_facts( $row );
			$captions[ (int) $row['attachment_id'] ] = (string) $row['caption'];
		}
	}

	$groups = vergeml_filing_residue_groups( $facts );

	$profiles = $groups && function_exists( 'vergeml_filing_profiles' ) ? vergeml_filing_profiles( array_values( (array) $state['ids'] ), $taxonomy ) : array();
	$names    = array();
	$nearest  = array();
	if ( ! isset( $state['names'] ) || ! is_array( $state['names'] ) ) {
		$state['names'] = array();
	}
	if ( ! isset( $state['asked'] ) || ! is_array( $state['asked'] ) ) {
		$state['asked'] = array();
	}

	foreach ( $groups as $i => $g ) {
		if ( ! empty( $g['unreadable'] ) || ! empty( $g['more'] ) ) {
			continue;
		}
		$nearest[ $i ] = vergeml_filing_group_nearest( $g, $near_by, $profiles, $facts );
		if ( 'photo' !== $g['kind'] ) {
			$names[ $i ] = vergeml_filing_class_name( vergeml_talk_plural( $g['kind'] ) ); // "Screenshots": the kind is the name, no call.
			continue;
		}
		$key = md5( implode( ',', $g['ids'] ) );
		if ( ! isset( $state['names'][ $key ] ) && empty( $state['asked'][ $key ] ) ) {
			if ( null !== $deadline && microtime( true ) > $deadline ) {
				return null;
			}
			$state['asked'][ $key ] = true;
			update_option( VERGEML_TALK_STATE, $state, false );
			$sample = array();
			foreach ( array_slice( $g['ids'], 0, VERGEML_FILING_SAMPLE ) as $id ) {
				if ( isset( $captions[ $id ] ) && '' !== $captions[ $id ] ) {
					$sample[] = mb_substr( $captions[ $id ], 0, 160 );
				}
			}
			$name = vergeml_talk_name_group( array_keys( (array) $g['classes'] ), $sample );
			if ( null !== $name ) {
				$state['names'][ $key ] = $name; // Cached only when a name came back: a failed call is asked again next time, never remembered as a blank.
			}
		}
		if ( isset( $state['names'][ $key ] ) ) {
			$names[ $i ] = $state['names'][ $key ];
		}
	}

	return vergeml_filing_questions( $groups, $siblings, $names, $nearest, $either );
}

/**
 *  One name for a group, from the service: metered like an embed, no
 *  credit. Null when the service cannot answer; the class word stands in.
 */
function vergeml_talk_name_group( $objects, $captions ) {

	if ( ! function_exists( 'vergeml_ai_settings' ) || ! function_exists( 'vergeml_ai_unseal' ) ) {
		return null;
	}
	$settings = vergeml_ai_settings();
	$licence  = vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
	if ( '' === $licence ) {
		return null;
	}

	$response = wp_remote_post(
		vergeml_ai_service_url() . '/name-group',
		array(
			'timeout'   => 20,
			'headers'   => array( 'Content-Type' => 'application/json' ),
			'sslverify' => true,
			'body'      => wp_json_encode( array(
				'license_key' => $licence,
				'site'        => home_url(),
				'objects'     => array_values( array_map( 'strval', (array) $objects ) ),
				'captions'    => array_values( array_map( 'strval', (array) $captions ) ),
			) ),
		)
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$name = is_array( $data ) && isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
	return '' === $name ? null : $name;
}

/**
 *  The question's sentence and its answers' labels, verbatim from the spec
 *  (2026-09-14, §2 Step 3). Numbers and folder names filled in; nothing else
 *  said.
 */
function vergeml_talk_question_text( $q, $taxonomy ) {

	$name = function ( $tid ) use ( $taxonomy ) {
		$t = $tid ? get_term( (int) $tid, $taxonomy ) : null;
		return $t instanceof WP_Term ? vergeml_term_name( $t ) : '';
	};
	$n = number_format_i18n( (int) $q['count'] );

	$labels = array(
		'keep-parent' => sprintf( /* translators: %s: the parent folder */ __( 'Keep them in %s', 'vergelabs-media-library' ), $name( $q['term_id'] ) ),
		'split'       => __( 'Split them by best score', 'vergelabs-media-library' ),
		'new-folder'  => sprintf( /* translators: %s: the folder to make */ __( 'New folder %s', 'vergelabs-media-library' ), (string) $q['name'] ),
		'leave'       => __( 'Leave them', 'vergelabs-media-library' ),
		'show-me'     => in_array( $q['kind'], array( 'siblings', 'either' ), true ) ? __( 'Let me look', 'vergelabs-media-library' ) : __( 'Show me', 'vergelabs-media-library' ),
	);

	$answers = array();
	foreach ( (array) $q['answers'] as $a ) {
		if ( 0 === strpos( $a, 'put-in:' ) ) {
			/* translators: %s: an existing folder */
			$answers[ $a ] = sprintf( __( 'Put in %s', 'vergelabs-media-library' ), $name( (int) substr( $a, 7 ) ) );
		} elseif ( isset( $labels[ $a ] ) ) {
			$answers[ $a ] = $labels[ $a ];
		}
	}

	if ( 'siblings' === $q['kind'] ) {
		$kids = vergeml_talk_folder_names( (array) $q['children'], $taxonomy );
		/* translators: 1: pictures, 2: one folder, 3: its sibling */
		$text = sprintf( _n( '%1$s picture fits both %2$s and %3$s.', '%1$s pictures fit both %2$s and %3$s.', (int) $q['count'], 'vergelabs-media-library' ), $n, isset( $kids[0] ) ? $kids[0] : '', isset( $kids[1] ) ? $kids[1] : '' );
	} elseif ( 'either' === $q['kind'] && ! empty( $q['pairs'] ) ) {
		// The folded card (S10.5): every picture on it has its own two folders, said on its thumbnail.
		/* translators: %s: pictures */
		$text = sprintf( __( '%s pictures each fit two folders.', 'vergelabs-media-library' ), $n );
	} elseif ( 'either' === $q['kind'] ) {
		$kids = vergeml_talk_folder_names( (array) $q['children'], $taxonomy );
		/* translators: 1: pictures, 2: one folder, 3: another folder, not its sibling */
		$text = sprintf( _n( '%1$s picture: %2$s or %3$s?', '%1$s pictures: %2$s or %3$s?', (int) $q['count'], 'vergelabs-media-library' ), $n, isset( $kids[0] ) ? $kids[0] : '', isset( $kids[1] ) ? $kids[1] : '' );
	} elseif ( ! empty( $q['unreadable'] ) ) {
		/* translators: %s: pictures */
		$text = sprintf( __( '%s with nothing to go on', 'vergelabs-media-library' ), $n );
	} elseif ( ! empty( $q['more'] ) ) {
		/* translators: %s: pictures */
		$text = sprintf( __( '%s more, in small groups', 'vergelabs-media-library' ), $n );
	} elseif ( isset( $q['share'] ) && (float) $q['share'] < VERGEML_FILING_GROUP_PURE ) {
		/* translators: 1: pictures, 2: what most of them look like, plural ("robot arms") */
		$text = sprintf( __( '%1$s mixed, mostly %2$s', 'vergelabs-media-library' ), $n, vergeml_talk_plural( (string) $q['class'] ) );
	} else {
		/* translators: 1: pictures, 2: what they look like, plural ("robot arms") */
		$text = sprintf( __( '%1$s look like %2$s', 'vergelabs-media-library' ), $n, vergeml_talk_plural( (string) $q['class'] ) );
	}

	return array( 'text' => $text, 'answers' => $answers );
}

/**
 *  Two folders as a question names them: the leaf, or the path when the two
 *  share a leaf (vergeml_filing_question_names, S10.5).
 *
 * @param int[]  $term_ids
 * @param string $taxonomy
 * @return string[]
 */
function vergeml_talk_folder_names( $term_ids, $taxonomy ) {
	$folders = array();
	foreach ( (array) $term_ids as $tid ) {
		$t = $tid ? get_term( (int) $tid, $taxonomy ) : null;
		if ( ! ( $t instanceof WP_Term ) ) {
			$folders[] = array( 'name' => '', 'path' => '' );
			continue;
		}
		$path = array( vergeml_term_name( $t ) );
		foreach ( get_ancestors( (int) $t->term_id, $taxonomy, 'taxonomy' ) as $aid ) {
			$a = get_term( (int) $aid, $taxonomy );
			if ( $a instanceof WP_Term ) {
				array_unshift( $path, vergeml_term_name( $a ) );
			}
		}
		$folders[] = array( 'name' => vergeml_term_name( $t ), 'path' => implode( ' › ', $path ) );
	}
	return vergeml_filing_question_names( $folders );
}

/** "A or B" for one picture on the folded either/or card: its own two folders, told apart by their paths when they share a leaf. */
function vergeml_talk_pair_label( $pair, $taxonomy ) {
	$two = vergeml_talk_folder_names( array_slice( (array) $pair, 0, 2 ), $taxonomy );
	/* translators: 1: one folder, 2: another folder */
	return sprintf( __( '%1$s or %2$s', 'vergelabs-media-library' ), isset( $two[0] ) ? $two[0] : '', isset( $two[1] ) ? $two[1] : '' );
}

/** "robot arm" -> "robot arms"; good enough for a class word, which is a noun. */
function vergeml_talk_plural( $word ) {
	$w = trim( (string) $word );
	if ( '' === $w || 's' === mb_substr( $w, -1 ) ) {
		return $w;
	}
	if ( preg_match( '/(sh|ch|x|z)$/u', $w ) ) {
		return $w . 'es';
	}
	if ( preg_match( '/[^aeiou]y$/u', $w ) ) {
		return mb_substr( $w, 0, -1 ) . 'ies';
	}
	return $w . 's';
}

/** The open and answered questions, as the screen reads them: sentence, labelled answers, a sample with thumbnails. */
function vergeml_talk_questions() {

	$state     = get_option( VERGEML_TALK_STATE );
	$taxonomy  = is_array( $state ) && isset( $state['taxonomy'] ) ? (string) $state['taxonomy'] : '';
	$questions = is_array( $state ) && isset( $state['questions'] ) ? (array) $state['questions'] : array();
	$out       = array();

	// Every sample's post and meta in two queries, not two per thumbnail: 38 questions carry 300 of them.
	$ids = array();
	foreach ( $questions as $q ) {
		foreach ( (array) $q['sample'] as $id ) {
			$ids[] = (int) $id;
		}
	}
	if ( $ids ) {
		_prime_post_caches( array_values( array_unique( $ids ) ), false, true );
	}

	foreach ( $questions as $q ) {
		$words  = vergeml_talk_question_text( $q, $taxonomy );
		$sample = array();
		foreach ( (array) $q['sample'] as $id ) {
			$one = array( 'id' => (int) $id, 'thumb' => (string) wp_get_attachment_image_url( (int) $id, 'thumbnail' ) );
			if ( isset( $q['pairs'][ (int) $id ] ) ) {
				$one['pair'] = vergeml_talk_pair_label( $q['pairs'][ (int) $id ], $taxonomy );
			}
			$sample[] = $one;
		}
		$out[] = array(
			'id'       => (string) $q['id'],
			'kind'     => (string) $q['kind'],
			'count'    => (int) $q['count'],
			'name'     => (string) $q['name'],
			'term_id'  => (int) $q['term_id'],
			'text'     => $words['text'],
			'answers'  => $words['answers'],
			'sample'   => $sample,
			'answered' => isset( $q['answered'] ) ? (string) $q['answered'] : '',
			'result'   => isset( $q['result'] ) ? (array) $q['result'] : null,
		);
	}

	return $out;
}

/** The folders the answers made, by term id -- the ones the done tree marks new. Only the ones that still exist. */
function vergeml_talk_made_by_answer() {

	$state = get_option( VERGEML_TALK_STATE );
	$made  = is_array( $state ) && isset( $state['made_by_answer'] ) ? array_map( 'intval', (array) $state['made_by_answer'] ) : array();
	$tax   = is_array( $state ) && isset( $state['taxonomy'] ) ? (string) $state['taxonomy'] : '';

	return array_values( array_filter( array_unique( $made ), function ( $tid ) use ( $tax ) {
		return $tid > 0 && '' !== $tax && get_term( $tid, $tax ) instanceof WP_Term;
	} ) );
}

/**
 *  Where the fill stands: open questions, pictures in no folder, and whether
 *  the step is done -- which is both at zero, and nothing less.
 */
function vergeml_talk_fill_status() {

	global $wpdb;

	$state    = get_option( VERGEML_TALK_STATE );
	$taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
	$open     = 0;
	foreach ( is_array( $state ) && isset( $state['questions'] ) ? (array) $state['questions'] : array() as $q ) {
		if ( empty( $q['answered'] ) ) {
			$open++;
		}
	}

	$unfiled = 0;
	if ( '' !== $taxonomy && isset( $wpdb->vergeml_ai_index ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- this plugin's own table.
		$unfiled = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} i WHERE i.error = '' AND i.embedding IS NOT NULL AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 WHERE tr.object_id = i.attachment_id AND tt.taxonomy = %s )",
			$taxonomy
		) );
	}

	return array(
		'open'    => $open,
		'unfiled' => $unfiled,
		'running' => is_array( $state ) && ! empty( $state['active'] ),
		'done'    => 0 === $open && 0 === $unfiled && ! ( is_array( $state ) && ! empty( $state['active'] ) ),
	);
}

/**
 *  The To sort folder: made on first use, locked, so the fill never files
 *  into it or out of it. A person can always move things out by hand.
 */
function vergeml_talk_to_sort( $taxonomy ) {

	$term = get_term_by( 'slug', VERGEML_FILING_TO_SORT_SLUG, $taxonomy );
	if ( $term instanceof WP_Term ) {
		update_term_meta( (int) $term->term_id, VERGEML_FILING_LOCKED, 1 );
		return (int) $term->term_id;
	}
	$made = wp_insert_term( __( 'To sort', 'vergelabs-media-library' ), $taxonomy, array( 'slug' => VERGEML_FILING_TO_SORT_SLUG ) );
	if ( is_wp_error( $made ) || ! isset( $made['term_id'] ) ) {
		return 0;
	}
	update_term_meta( (int) $made['term_id'], VERGEML_FILING_LOCKED, 1 );
	return (int) $made['term_id'];
}

/**
 *  One answer, carried out.
 *
 *  The plan is vergeml_filing_answer_plan()'s; this makes the folder it
 *  names, resolves To sort, moves the pictures, marks the ones the user
 *  chose a folder for as theirs, and writes each move into the undo record
 *  and the trail -- so Undo covers the whole step, answers included.
 *
 *  @return array|WP_Error { id, answer, moved, made (term id), term_id, show (ids), line }
 */
function vergeml_talk_answer( $id, $answer ) {

	$state = get_option( VERGEML_TALK_STATE );
	if ( ! is_array( $state ) || empty( $state['questions'] ) ) {
		return new WP_Error( 'no_questions', __( 'There are no questions to answer.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
	}
	if ( ! empty( $state['active'] ) ) {
		return new WP_Error( 'running', __( 'The fill is still running.', 'vergelabs-media-library' ), array( 'status' => 409 ) );
	}

	$at = null;
	foreach ( (array) $state['questions'] as $i => $q ) {
		if ( (string) $q['id'] === (string) $id ) {
			$at = $i;
			break;
		}
	}
	if ( null === $at ) {
		return new WP_Error( 'no_question', __( 'No such question.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
	}
	$q = $state['questions'][ $at ];
	if ( ! empty( $q['answered'] ) ) {
		return new WP_Error( 'answered', __( 'That question is answered.', 'vergelabs-media-library' ), array( 'status' => 409 ) );
	}

	$plan = vergeml_filing_answer_plan( $q, $answer );
	if ( null === $plan ) {
		return new WP_Error( 'bad_answer', __( 'That is not one of the answers.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
	}

	/*
	 *  The fill's own word never outranks the person's (S11 review). Split
	 *  and keep-parent move by the question's map, drawn when the run ended;
	 *  a picture they dragged into a folder since (placed_by = user) stays
	 *  where they put it and keeps "by you". Put-in and a new folder are
	 *  their own choice over the same pictures, and move them.
	 */
	if ( 'answer' === $plan['placed_by'] ) {
		foreach ( array_keys( (array) $plan['moves'] ) as $id ) {
			if ( 'user' === get_post_meta( (int) $id, VERGEML_FILING_PLACED_BY, true ) ) {
				unset( $plan['moves'][ $id ] );
			}
		}
	}

	$taxonomy = (string) $state['taxonomy'];
	$made     = 0;
	$to_sort  = 0;

	if ( null !== $plan['make'] ) {
		$found = get_terms( array( 'taxonomy' => $taxonomy, 'name' => $plan['make'], 'parent' => 0, 'hide_empty' => false, 'number' => 1 ) );
		if ( ! is_wp_error( $found ) && $found ) {
			$made = (int) $found[0]->term_id;
		} else {
			$new = wp_insert_term( $plan['make'], $taxonomy, array( 'parent' => 0 ) );
			if ( is_wp_error( $new ) || ! isset( $new['term_id'] ) ) {
				return new WP_Error( 'no_folder', __( 'The folder could not be made.', 'vergelabs-media-library' ), array( 'status' => 500 ) );
			}
			$made = (int) $new['term_id'];
			$state['made_by_answer'][] = $made;
		}
	}
	if ( in_array( 'to-sort', array_values( (array) $plan['moves'] ), true ) ) {
		$had     = get_term_by( 'slug', VERGEML_FILING_TO_SORT_SLUG, $taxonomy ) instanceof WP_Term;
		$to_sort = vergeml_talk_to_sort( $taxonomy );
		if ( ! $to_sort ) {
			return new WP_Error( 'no_folder', __( 'The To sort folder could not be made.', 'vergelabs-media-library' ), array( 'status' => 500 ) );
		}
		// Made by this answer: the tree marks it new with the folders the other answers made. Never unmade by undo -- it is where leave puts things.
		if ( ! $had ) {
			$state['made_by_answer'][] = $to_sort;
		}
	}
	foreach ( (array) $plan['moves'] as $tid ) {
		if ( is_int( $tid ) && ! ( get_term( $tid, $taxonomy ) instanceof WP_Term ) ) {
			return new WP_Error( 'no_folder', __( 'That folder is gone.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
		}
	}

	/*
	 *  The moves, as one batch: the pictures' folders read in one query, term
	 *  counting deferred to the end and the count caches flushed once after
	 *  it. Until 2026-09-16 every picture counted its terms and flushed the
	 *  caches on its own -- ~600 queries for the 61 towers (S6b, seam 3).
	 */
	$undo   = array();
	$trail  = array();
	$moved  = 0;
	$landed = 0;
	$ids    = array_map( 'intval', array_keys( (array) $plan['moves'] ) );
	$before = array_fill_keys( $ids, array() );
	if ( $ids ) {
		$rel = wp_get_object_terms( $ids, $taxonomy, array( 'fields' => 'all_with_object_id' ) );
		foreach ( is_wp_error( $rel ) ? array() : $rel as $t ) {
			$before[ (int) $t->object_id ][] = (int) $t->term_id;
		}
	}
	wp_defer_term_counting( true );
	remove_action( 'set_object_terms', 'vergeml_folder_flush_counts', 10 );
	remove_action( 'deleted_term_relationships', 'vergeml_folder_flush_counts', 10 );
	foreach ( (array) $plan['moves'] as $attachment => $to ) {
		$attachment = (int) $attachment;
		$to         = 'new' === $to ? $made : ( 'to-sort' === $to ? $to_sort : (int) $to );
		$landed     = $to;
		$undo[ $attachment ] = $before[ $attachment ];
		wp_set_object_terms( $attachment, array( $to ), $taxonomy, false );
		if ( $plan['placed_by'] ) {
			update_post_meta( $attachment, VERGEML_FILING_PLACED_BY, true === $plan['placed_by'] ? 'user' : (string) $plan['placed_by'] );
		}
		$moved++;
		$trail[] = array( $attachment, $to, array( 'why' => true === $plan['placed_by'] ? 'user' : 'answer', 'prompt_hash' => '', 'model_version' => '' ) );
	}
	add_action( 'set_object_terms', 'vergeml_folder_flush_counts', 10, 0 );
	add_action( 'deleted_term_relationships', 'vergeml_folder_flush_counts', 10, 0 );
	wp_defer_term_counting( false );
	if ( $moved && function_exists( 'vergeml_folder_flush_counts' ) ) {
		vergeml_folder_flush_counts();
	}

	if ( $undo ) {
		$before = get_option( VERGEML_TALK_UNDO );
		if ( is_array( $before ) ) {
			$before['files'] = ( isset( $before['files'] ) ? (array) $before['files'] : array() ) + $undo;
			if ( $made && ! empty( $state['made_by_answer'] ) && in_array( $made, (array) $state['made_by_answer'], true ) ) {
				$before['made'][] = $made;
			}
			if ( $plan['placed_by'] ) {
				$before['placed'] = array_values( array_unique( array_merge( isset( $before['placed'] ) ? (array) $before['placed'] : array(), array_keys( $undo ) ) ) );
			}
			update_option( VERGEML_TALK_UNDO, $before, false );
		}
		vergeml_talk_trail_write( $trail );
	}

	// 'placed': how many are the person's own after this answer (the "by you" word), for the card's result line.
	$result = array( 'moved' => $moved, 'term_id' => $landed, 'made' => $made, 'placed' => true === $plan['placed_by'] ? $moved : 0 );
	if ( $plan['answered'] ) {
		$state['questions'][ $at ]['answered'] = (string) $answer;
		$state['questions'][ $at ]['result']   = $result;
	}
	update_option( VERGEML_TALK_STATE, $state, false );

	if ( $moved && function_exists( 'vergeml_folders_moved' ) ) {
		vergeml_folders_moved( 'answer' );
	}

	return array(
		'id'      => (string) $q['id'],
		'answer'  => (string) $answer,
		'moved'   => $moved,
		'made'    => $made,
		'term_id' => $landed,
		'placed'  => $result['placed'],
		'show'    => $plan['show'],
		// The folded either/or card's pictures each carry their own two folders (S10.5), for the strip "Show me" opens.
		'pairs'   => isset( $q['pairs'] ) && is_array( $q['pairs'] ) ? $q['pairs'] : null,
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


