<?php
/**
 *  Phase 2, question one: does clustering the embeddings produce a tree a
 *  person would recognise as their own library?
 *
 *      wp eval-file tools/organize-spike.php --allow-root
 *      wp eval-file tools/organize-spike.php k=8 --allow-root
 *      wp eval-file tools/organize-spike.php sizes --allow-root
 *
 *  A spike, and deliberately not plugin code: it lives in tools/, which is
 *  export-ignored, so nothing here ships. It answers whether the approach
 *  works before any of it is designed into the plugin, which is the whole
 *  point of doing this before the UI rather than after.
 *
 *  It reads. It creates no folder, moves no file and writes no option.
 *
 *  Three properties matter more than the pretty output:
 *
 *  1. **Deterministic.** Two runs over the same library must produce the same
 *     tree, or "runs produce diffs" is meaningless -- every diff would be
 *     noise. There is no rand() here and no array order that depends on
 *     hashing: centroids are seeded by a fixed rule and everything is sorted
 *     by attachment id.
 *  2. **Bounded.** k-means over n vectors is O(n·k·i). The library is the n
 *     nobody controls, so the cost is reported rather than assumed.
 *  3. **Explainable.** Every assignment carries one line saying why, because
 *     a tree somebody cannot interrogate is a tree they will not accept.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'vergeml_index_table' ) ) {
	echo "The AI index is not loaded. Is the plugin active (and not in safe mode)?\n";
	return;
}

$options = array( 'k' => 0, 'mode' => 'once' );

foreach ( (array) $args as $arg ) {
	if ( 'sizes' === $arg ) {
		$options['mode'] = 'sizes';
	} elseif ( 0 === strpos( $arg, 'k=' ) ) {
		$options['k'] = (int) substr( $arg, 2 );
	}
}


/* ------------------------------------------------------------------ loading */

/**
 *  Every stored vector, once, in id order.
 *
 *  Id order is not cosmetic: it is what makes the seeding below reproducible.
 *  A clustering whose result depends on the order rows came back in is one
 *  that changes when somebody uploads a file.
 */
function vgml_spike_vectors( $limit = 0 ) {

	global $wpdb;

	$sql = 'SELECT attachment_id, embedding, embedding_dims, tags, kind, document_type
	        FROM ' . vergeml_index_table() . '
	        WHERE embedding IS NOT NULL
	        ORDER BY attachment_id ASC';

	if ( $limit > 0 ) {
		$sql .= ' LIMIT ' . (int) $limit;
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $sql, ARRAY_A );
	// phpcs:enable

	$out = array();

	foreach ( (array) $rows as $row ) {

		$vector = vergeml_index_vector_out( $row['embedding'] );

		if ( ! is_array( $vector ) || ! $vector ) {
			continue;
		}

		$out[] = array(
			'id'     => (int) $row['attachment_id'],
			'vector' => $vector,
			'tags'   => vergeml_index_tags_out( $row['tags'] ),
			'kind'   => (string) $row['kind'],
			'doc'    => (string) $row['document_type'],
		);
	}

	return $out;
}


/* --------------------------------------------------------------- clustering */

function vgml_spike_distance( $a, $b ) {

	$sum = 0.0;

	foreach ( $a as $i => $v ) {
		$d    = $v - $b[ $i ];
		$sum += $d * $d;
	}

	return $sum; // squared: comparing them is all anything here does
}


/**
 *  k-means++ seeding with the randomness taken out.
 *
 *  The real algorithm picks each next centroid at random, weighted by distance
 *  from the nearest existing one. Weighted-random is what makes repeated runs
 *  differ, so this takes the farthest point instead -- the same idea, minus
 *  the die roll. Same input, same centroids, every time, on every machine.
 */
function vgml_spike_seed( $points, $k ) {

	$centroids = array( $points[0]['vector'] );

	while ( count( $centroids ) < $k ) {

		$best     = null;
		$bestDist = -1.0;

		foreach ( $points as $point ) {

			$nearest = INF;

			foreach ( $centroids as $centroid ) {
				$d = vgml_spike_distance( $point['vector'], $centroid );
				if ( $d < $nearest ) {
					$nearest = $d;
				}
			}

			// Strictly greater, so ties go to the lower attachment id -- the
			// points are in id order, and a tie broken by iteration order is a
			// tie broken by chance.
			if ( $nearest > $bestDist ) {
				$bestDist = $nearest;
				$best     = $point['vector'];
			}
		}

		if ( null === $best || $bestDist <= 0 ) {
			break; // fewer distinct points than clusters asked for
		}

		$centroids[] = $best;
	}

	return $centroids;
}


function vgml_spike_kmeans( $points, $k, $max_iterations = 25 ) {

	$centroids  = vgml_spike_seed( $points, $k );
	$k          = count( $centroids );
	$assignment = array_fill( 0, count( $points ), -1 );
	$iterations = 0;

	for ( $iteration = 0; $iteration < $max_iterations; $iteration++ ) {

		$iterations = $iteration + 1;
		$moved      = 0;

		foreach ( $points as $i => $point ) {

			$best     = 0;
			$bestDist = INF;

			foreach ( $centroids as $c => $centroid ) {
				$d = vgml_spike_distance( $point['vector'], $centroid );
				if ( $d < $bestDist ) {
					$bestDist = $d;
					$best     = $c;
				}
			}

			if ( $assignment[ $i ] !== $best ) {
				$assignment[ $i ] = $best;
				$moved++;
			}
		}

		if ( 0 === $moved ) {
			break; // settled; running further would only cost time
		}

		$dims  = count( $points[0]['vector'] );
		$sums  = array_fill( 0, $k, array_fill( 0, $dims, 0.0 ) );
		$count = array_fill( 0, $k, 0 );

		foreach ( $points as $i => $point ) {
			$c = $assignment[ $i ];
			$count[ $c ]++;
			foreach ( $point['vector'] as $d => $v ) {
				$sums[ $c ][ $d ] += $v;
			}
		}

		foreach ( $sums as $c => $sum ) {
			if ( 0 === $count[ $c ] ) {
				continue; // an empty cluster keeps its centroid rather than moving to the origin
			}
			foreach ( $sum as $d => $v ) {
				$centroids[ $c ][ $d ] = $v / $count[ $c ];
			}
		}
	}

	return array( 'assignment' => $assignment, 'centroids' => $centroids, 'iterations' => $iterations );
}


/* ----------------------------------------------------------------- labelling */

/**
 *  What to call a cluster.
 *
 *  The tags the members share, in order of how many share them, with the ones
 *  everything has thrown away -- a word that appears in every cluster
 *  distinguishes nothing. No model call: a name is a summary of what is
 *  already stored, and paying per folder to be told "photos" would be a poor
 *  trade.
 */
function vgml_spike_label( $members, $global ) {

	$counts = array();

	foreach ( $members as $member ) {
		foreach ( $member['tags'] as $tag ) {
			$counts[ $tag ] = isset( $counts[ $tag ] ) ? $counts[ $tag ] + 1 : 1;
		}
	}

	$total = count( $members );
	$score = array();

	foreach ( $counts as $tag => $count ) {

		$share  = $count / $total;
		$spread = isset( $global[ $tag ] ) ? $global[ $tag ] : 1;

		// Common inside this cluster, rare across the library.
		$score[ $tag ] = $share * ( 1 / $spread );
	}

	arsort( $score );

	$words = array_slice( array_keys( $score ), 0, 2 );

	if ( ! $words ) {
		// Nothing shared: fall back to what the files are rather than inventing.
		$kinds = array();
		foreach ( $members as $member ) {
			$kinds[ $member['kind'] ] = true;
		}
		return ucfirst( implode( ' and ', array_slice( array_keys( $kinds ), 0, 2 ) ) ?: 'Unsorted' );
	}

	return ucwords( implode( ' ', $words ) );
}


/* --------------------------------------------------------------------- run */

function vgml_spike_run( $k = 0, $limit = 0 ) {

	$started = microtime( true );
	$points  = vgml_spike_vectors( $limit );
	$n       = count( $points );

	if ( $n < 2 ) {
		return array( 'error' => "only {$n} embedded file(s) -- run the AI index first" );
	}

	// A starting point, not a decision: how many folders a library wants is
	// exactly the sort of question the interviews exist to answer.
	$k = $k > 0 ? $k : max( 2, (int) round( sqrt( $n / 2 ) ) );
	$k = min( $k, $n );

	$global = array();
	foreach ( $points as $point ) {
		foreach ( array_unique( $point['tags'] ) as $tag ) {
			$global[ $tag ] = isset( $global[ $tag ] ) ? $global[ $tag ] + 1 : 1;
		}
	}

	$result   = vgml_spike_kmeans( $points, $k );
	$clusters = array();

	foreach ( $points as $i => $point ) {
		$clusters[ $result['assignment'][ $i ] ][] = $point;
	}

	ksort( $clusters );

	$tree = array();

	foreach ( $clusters as $c => $members ) {

		$centroid = $result['centroids'][ $c ];
		$rows     = array();

		foreach ( $members as $member ) {
			$rows[] = array(
				'id'       => $member['id'],
				'distance' => sqrt( vgml_spike_distance( $member['vector'], $centroid ) ),
			);
		}

		// Nearest the centre first: the most typical member of a folder is the
		// one to show as its thumbnail, and the furthest is the one to check.
		usort( $rows, function ( $a, $b ) {
			if ( $a['distance'] === $b['distance'] ) {
				return $a['id'] - $b['id'];
			}
			return $a['distance'] < $b['distance'] ? -1 : 1;
		} );

		$label = vgml_spike_label( $members, $global );

		$tree[] = array(
			'label'   => $label,
			'size'    => count( $members ),
			'members' => $rows,
			'reason'  => sprintf(
				'%d files whose descriptions cluster together; named for the tags they share and the rest of the library does not',
				count( $members )
			),
		);
	}

	// Biggest first, then by label, so the order is a property of the result
	// rather than of the loop.
	usort( $tree, function ( $a, $b ) {
		if ( $a['size'] === $b['size'] ) {
			return strcmp( $a['label'], $b['label'] );
		}
		return $b['size'] - $a['size'];
	} );

	return array(
		'n'          => $n,
		'k'          => $k,
		'iterations' => $result['iterations'],
		'ms'         => round( ( microtime( true ) - $started ) * 1000, 1 ),
		'tree'       => $tree,
	);
}


/**
 *  The tree as one string, so two runs can be compared by eye or by diff.
 *  This is what "runs produce diffs" needs: a stable serialisation.
 */
function vgml_spike_fingerprint( $run ) {

	$lines = array();

	foreach ( $run['tree'] as $branch ) {
		$ids = array();
		foreach ( $branch['members'] as $member ) {
			$ids[] = $member['id'];
		}
		sort( $ids );
		$lines[] = $branch['label'] . ': ' . implode( ',', $ids );
	}

	sort( $lines );

	return implode( "\n", $lines );
}


/* -------------------------------------------------------------------- output */

if ( 'sizes' === $options['mode'] ) {

	// The roadmap asks for three library sizes. The library is what it is, so
	// this takes prefixes of it -- same code, three values of n.
	echo "\nn        k   iters     ms   ms/file\n";

	foreach ( array( 25, 100, 0 ) as $limit ) {

		$run = vgml_spike_run( $options['k'], $limit );

		if ( isset( $run['error'] ) ) {
			echo '  ', $run['error'], "\n";
			continue;
		}

		printf( "%-8d %-3d %-6d %7.1f %8.2f\n",
			$run['n'], $run['k'], $run['iterations'], $run['ms'], $run['ms'] / max( 1, $run['n'] ) );
	}

	echo "\n";
	return;
}

$run = vgml_spike_run( $options['k'] );

if ( isset( $run['error'] ) ) {
	echo $run['error'], "\n";
	return;
}

printf( "\n%d embedded files → %d branches in %d iterations, %.1fms\n\n", $run['n'], $run['k'], $run['iterations'], $run['ms'] );

foreach ( $run['tree'] as $branch ) {

	printf( "  %-28s %3d files\n", $branch['label'], $branch['size'] );

	foreach ( array_slice( $branch['members'], 0, 3 ) as $member ) {
		$file = basename( (string) get_attached_file( $member['id'] ) );
		printf( "      %-42s  %.3f from centre\n", substr( $file, 0, 42 ), $member['distance'] );
	}

	if ( $branch['size'] > 3 ) {
		printf( "      … and %d more\n", $branch['size'] - 3 );
	}
}

// Determinism is the property everything after this depends on, so it is
// checked here rather than asserted in a comment.
$again = vgml_spike_run( $options['k'] );

printf( "\ndeterministic: %s\n\n",
	vgml_spike_fingerprint( $run ) === vgml_spike_fingerprint( $again ) ? 'yes — two runs, identical tree' : 'NO — the tree moved between runs' );
