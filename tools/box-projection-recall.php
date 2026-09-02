<?php
/*
 *  Does the short vector find the same pictures the long one would?
 *
 *  Run through `wp eval-file` on a library that has really been described.
 *  For each probe picture it takes the ten nearest neighbours by the whole
 *  embedding -- the answer we would give with unlimited time, and therefore
 *  the only ground truth available -- and asks how many of those ten each
 *  projection puts in its own top ten.
 *
 *  Nothing here writes. It reads embeddings and prints two numbers.
 */

function vgml_t_nrm( $v ) {
    $s = 0.0;
    foreach ( $v as $x ) { $s += (float) $x * (float) $x; }
    $l = sqrt( $s );
    if ( $l <= 0 ) { return array_map( 'floatval', $v ); }
    $o = array();
    foreach ( $v as $x ) { $o[] = (float) $x / $l; }
    return $o;
}

/** The projection as it shipped in 3.9.1 through 3.16.0. */
function vgml_t_band( $v, $d = 64 ) {
    $v = array_values( $v ); $L = count( $v ); $o = array();
    for ( $i = 0; $i < $d; $i++ ) {
        $f = (int) floor( $i * $L / $d ); $t = (int) floor( ( $i + 1 ) * $L / $d );
        if ( $t <= $f ) { $t = $f + 1; }
        $s = 0.0;
        for ( $j = $f; $j < $t && $j < $L; $j++ ) { $s += (float) $v[ $j ]; }
        $o[] = $s / ( $t - $f );
    }
    return vgml_t_nrm( $o );
}

function vgml_t_dot( $a, $b ) {
    $s = 0.0; $n = min( count( $a ), count( $b ) );
    for ( $i = 0; $i < $n; $i++ ) { $s += $a[ $i ] * $b[ $i ]; }
    return $s;
}

global $wpdb;
$table = $wpdb->prefix . 'vergeml_ai_index';

$rows = $wpdb->get_results(
    "SELECT attachment_id, embedding FROM {$table}
      WHERE error = '' AND embedding IS NOT NULL LIMIT 900",
    ARRAY_A
);

if ( count( $rows ) < 30 ) {
    echo "only " . count( $rows ) . " described pictures here -- not enough to measure\n";
    return;
}

$full = array(); $band = array(); $hash = array();

foreach ( $rows as $r ) {
    $id = (int) $r['attachment_id'];
    $v  = vergeml_index_vector_out( $r['embedding'] );
    if ( ! is_array( $v ) || count( $v ) < 64 ) { continue; }
    $full[ $id ] = vgml_t_nrm( $v );
    $band[ $id ] = vgml_t_band( $v );
    $hash[ $id ] = vergeml_organize_project( $v, 64 );   // the new one, from the plugin
}

$ids = array_keys( $full );
echo count( $ids ) . " described pictures, " . count( reset( $full ) ) . " dimensions each\n\n";

$probes = array_slice( $ids, 0, 60 );

/*
 *  The question the fix actually rests on.
 *
 *  Search no longer answers with the projection: it uses it to choose a
 *  shortlist and then re-scores that shortlist on the whole embeddings. So
 *  the projection does not have to rank well -- it only has to not lose the
 *  right pictures before the pass that can rank them properly.
 *
 *  Recall at ten says how good its own ordering is. Recall at the shortlist
 *  depth says whether re-ranking can recover what its ordering got wrong.
 */
$depths = array( 10, 25, 50, 100 );
$found  = array_fill_keys( $depths, 0 );
$total  = 0;

foreach ( $probes as $p ) {

    $rank = function ( $space, $k ) use ( $p, $ids ) {
        $s = array();
        foreach ( $ids as $q ) {
            if ( $q === $p ) { continue; }
            $s[ $q ] = vgml_t_dot( $space[ $p ], $space[ $q ] );
        }
        arsort( $s );
        return array_slice( array_keys( $s ), 0, $k );
    };

    $truth = $rank( $full, 10 );
    $total += count( $truth );

    foreach ( $depths as $k ) {
        $found[ $k ] += count( array_intersect( $truth, $rank( $band, $k ) ) );
    }
}

printf( "Do the 10 genuinely closest survive into the shortlist that gets re-scored?

" );
foreach ( $depths as $k ) {
    printf( "  projection top %-3d   keeps %5.1f%% of them
", $k, 100 * $found[ $k ] / $total );
}
printf( "
(the library here is %d pictures, so a 400-deep shortlist is all of it)

", count( $ids ) );

$probes = array_slice( $ids, 0, 60 );
$hitB = 0; $hitH = 0; $n = 0;

foreach ( $probes as $p ) {

    $rank = function ( $space ) use ( $p, $ids ) {
        $s = array();
        foreach ( $ids as $q ) {
            if ( $q === $p ) { continue; }
            $s[ $q ] = vgml_t_dot( $space[ $p ], $space[ $q ] );
        }
        arsort( $s );
        return array_slice( array_keys( $s ), 0, 10 );
    };

    $truth = $rank( $full );
    $hitB += count( array_intersect( $truth, $rank( $band ) ) );
    $hitH += count( array_intersect( $truth, $rank( $hash ) ) );
    $n++;
}

printf( "Of the 10 genuinely closest pictures, how many each projection finds:\n\n" );
printf( "  band averaging (old, shipped)   %5.1f of 10\n", $hitB / $n );
printf( "  feature hashing (new)           %5.1f of 10\n", $hitH / $n );
printf( "\n%d probes over %d pictures.\n", $n, count( $ids ) );
