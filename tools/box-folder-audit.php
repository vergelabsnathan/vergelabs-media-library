<?php
/*
 *  How well do the pictures fit the folders they were put in? Read-only.
 *  Uses the vectors the last re-filing kept in its state option, so the
 *  scores are exactly the ones the filing saw.
 */
global $wpdb;
$state = get_option( VERGEML_TALK_STATE );
if ( ! is_array( $state ) || empty( $state['vectors'] ) ) { echo "no re-filing state on this site\n"; return; }
$tax = (string) $state['taxonomy'];
$names = array();
foreach ( (array) $state['ids'] as $key => $tid ) { $t = get_term( (int) $tid, $tax ); $names[ $key ] = $t && ! is_wp_error( $t ) ? ( $t->parent ? get_term( $t->parent, $tax )->name . ' / ' : '' ) . $t->name : "#$tid"; }
$by_tid = array_flip( array_map( 'intval', (array) $state['ids'] ) );

$rows = $wpdb->get_results( "SELECT attachment_id, embedding, filing FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL", ARRAY_A );
$per = array(); $all = array(); $weak = array(); $cases = array();
$pat = '/bike|bicycle|phone|wallet|heart|logo/i';
foreach ( $rows as $r ) {
    $v = vergeml_index_vector_out( $r['embedding'] );
    $terms = wp_get_object_terms( (int) $r['attachment_id'], $tax, array( 'fields' => 'ids' ) );
    $terms = is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
    $assigned = ''; foreach ( $terms as $tid ) { if ( isset( $by_tid[ $tid ] ) ) { $assigned = $by_tid[ $tid ]; break; } }
    $best = ''; $bs = -1; $own = null; $second = ''; $ss = -1;
    foreach ( (array) $state['vectors'] as $key => $fv ) {
        $s = vergeml_meaning_similarity( $fv, $v );
        if ( $key === $assigned ) { $own = $s; }
        if ( $s > $bs ) { $second = $best; $ss = $bs; $best = $key; $bs = $s; } elseif ( $s > $ss ) { $second = $key; $ss = $s; }
    }
    $all[] = $bs;
    if ( '' !== $assigned ) { $per[ $assigned ][] = $own; }
    $title = get_the_title( (int) $r['attachment_id'] );
    $f = json_decode( (string) $r['filing'], true ); $obj = is_array( $f ) ? ( $f['object'] ?? '' ) : '';
    if ( '' !== $assigned && $own < 0.30 ) { $weak[] = array( $own, $title, $names[ $assigned ], $obj ); }
    if ( preg_match( $pat, $title . ' ' . $obj ) ) { $cases[] = array( $title, $obj, '' === $assigned ? '(none)' : $names[ $assigned ], $own, $names[ $best ] ?? $best, $bs, $names[ $second ] ?? $second, $ss ); }
}
sort( $all );
$q = function ( $p ) use ( $all ) { return $all ? round( $all[ (int) floor( ( count( $all ) - 1 ) * $p ) ], 3 ) : 0; };
printf( "pictures scored: %d   best-folder score  p10 %.3f  p50 %.3f  p90 %.3f  (floor %.2f)\n", count( $all ), $q( .1 ), $q( .5 ), $q( .9 ), VERGEML_TALK_FLOOR );
echo "\n=== folders: members, mean and lowest score of a member to its own folder\n";
foreach ( $names as $key => $n ) { $s = $per[ $key ] ?? array(); printf( "  %-36s %4d   mean %s   min %s\n", $n, count( $s ), $s ? sprintf( '%.3f', array_sum( $s ) / count( $s ) ) : '-', $s ? sprintf( '%.3f', min( $s ) ) : '-' ); }
echo "\n=== the cases named (title | object | filed in @score | best @score | runner-up @score)\n";
foreach ( array_slice( $cases, 0, 24 ) as $c ) { printf( "  %-32s | %-26s | %s @%.3f | %s @%.3f | %s @%.3f\n", mb_substr( $c[0], 0, 32 ), mb_substr( $c[1], 0, 26 ), $c[2], (float) $c[3], $c[4], $c[5], $c[6], $c[7] ); }
usort( $weak, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
printf( "\n=== weakest fits (own-folder score under 0.30): %d of %d filed\n", count( $weak ), count( $rows ) );
foreach ( array_slice( $weak, 0, 16 ) as $w ) { printf( "  %.3f  %-32s -> %-30s (%s)\n", $w[0], mb_substr( $w[1], 0, 32 ), $w[2], mb_substr( $w[3], 0, 24 ) ); }
