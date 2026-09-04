<?php
/*
 *  Every described picture, filed by evidence against every folder on the
 *  site (core/filing.php). Dry run by default: prints what would move and
 *  what would stay unfiled, and why. VGML_APPLY=1 applies it.
 */

global $wpdb;
$apply = '1' === (string) getenv( 'VGML_APPLY' );
$tax   = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';

$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
if ( is_wp_error( $terms ) ) { echo $terms->get_error_message(), "\n"; return; }
$ids = array_map( function ( $t ) { return (int) $t->term_id; }, $terms );

// Can this site make a vector at all? The same call the profiles rely on.
$probe = vergeml_meaning_vector( 'shoes' );
printf( "meaning vector for 'shoes': %s\n", is_array( $probe ) ? count( $probe ) . ' dims' : var_export( $probe, true ) );
if ( ! is_array( $probe ) && function_exists( 'vergeml_ai_activate_site' ) ) {
    // A site that holds the key but never took its seat: take it, then try again.
    $act = vergeml_ai_activate_site();
    printf( "  activate: %s\n", is_wp_error( $act ) ? $act->get_error_message() : 'ok' );
    delete_transient( 'vergeml_qv2_' . md5( 'shoes' ) );
    $probe = vergeml_meaning_vector( 'shoes' );
    printf( "  meaning vector after activating: %s\n", is_array( $probe ) ? count( $probe ) . ' dims' : var_export( $probe, true ) );
}
if ( ! is_array( $probe ) ) {
    $s   = vergeml_ai_settings();
    $raw = wp_remote_post( vergeml_ai_service_url() . '/embed', array( 'timeout' => 20, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'license_key' => vergeml_ai_unseal( $s['license_key'] ), 'site' => home_url(), 'text' => 'shoes' ) ) ) );
    printf( "  raw /embed: %s %s\n", is_wp_error( $raw ) ? $raw->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $raw ), is_wp_error( $raw ) ? '' : mb_substr( (string) wp_remote_retrieve_body( $raw ), 0, 200 ) );
}

// VGML_PROFILE=1: ask the planner what each folder takes, and file against that.
if ( '1' === (string) getenv( 'VGML_PROFILE' ) ) {
    $t0 = microtime( true );
    $n  = vergeml_filing_profile_existing( $tax, true );
    printf( "planner profiled %s folders in %.1fs\n", is_wp_error( $n ) ? 'ERROR ' . $n->get_error_message() : $n, microtime( true ) - $t0 );
}

$t0 = microtime( true );
$profiles = vergeml_filing_profiles( $ids, $tax );
printf( "%d folders, %d profiled in %.1fs (%s)\n", count( $ids ), count( $profiles ), microtime( true ) - $t0, $apply ? 'APPLYING' : 'dry run' );
foreach ( $profiles as $tid => $p ) {
    printf( "  %-34s classes: %-40s kinds: %-20s audience: %s\n", implode( ' / ', $p['path'] ), implode( ', ', array_slice( $p['classes'], 0, 4 ) ), implode( ',', $p['kinds'] ), '' === $p['audience'] ? '-' : $p['audience'] );
}

$rows = $wpdb->get_results( "SELECT attachment_id, embedding, kind, filing FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL ORDER BY attachment_id", ARRAY_A );
$t0 = microtime( true );
$name = function ( $tid ) use ( $profiles ) { return isset( $profiles[ $tid ] ) ? implode( ' / ', $profiles[ $tid ]['path'] ) : '(none)'; };

$into = array(); $why = array(); $moves = array(); $stay = array(); $named = array();
$pat = '/bike|bicycle|phone|wallet|heart|logo|chart|sneaker|shoe|jeans|bag|tote/i';
foreach ( $rows as $r ) {
    $id    = (int) $r['attachment_id'];
    $facts = vergeml_filing_facts( $r );
    $pick  = vergeml_filing_pick( $facts, $profiles );
    $cur   = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
    $cur   = is_wp_error( $cur ) ? array() : array_map( 'intval', $cur );
    $from  = $cur ? $name( $cur[0] ) : '(unfiled)';
    $title = mb_substr( get_the_title( $id ), 0, 34 );
    $obj   = implode( '; ', $facts['classes'] );

    if ( $pick['term_id'] ) {
        $into[ $pick['term_id'] ] = ( $into[ $pick['term_id'] ] ?? 0 ) + 1;
        if ( ! in_array( (int) $pick['term_id'], $cur, true ) ) {
            $moves[] = sprintf( "  %-34s [%s|%s] %s -> %s  @%.2f (next %s @%.2f)", $title, $facts['kind'], mb_substr( $obj, 0, 28 ), $from, $name( $pick['term_id'] ), $pick['score'], $name( $pick['runner_up'] ), $pick['runner_score'] );
            if ( $apply ) { wp_set_object_terms( $id, array( (int) $pick['term_id'] ), $tax, false ); }
        }
    } else {
        $why[ $pick['why'] ] = ( $why[ $pick['why'] ] ?? 0 ) + 1;
        // Gated out of the folder it is in: that is evidence it does not belong there, so out it comes.
        $reason = '';
        if ( $cur && isset( $pick['gated'][ $cur[0] ] ) ) { $reason = 'gated: ' . $pick['gated'][ $cur[0] ]; }
        // Or it plainly does not match where it sits: a misfit, not an unknown.
        elseif ( $cur && $facts['classes'] && isset( $pick['scores'][ $cur[0] ], $profiles[ $cur[0] ] ) && 'plan' === $profiles[ $cur[0] ]['source'] && $pick['scores'][ $cur[0] ] < VERGEML_FILING_MISFIT ) { $reason = sprintf( 'misfit @%.2f', $pick['scores'][ $cur[0] ] ); }
        if ( '' !== $reason ) {
            $why['evicted'] = ( $why['evicted'] ?? 0 ) + 1;
            if ( $apply ) { wp_set_object_terms( $id, array(), $tax, false ); }
        }
        $stay[] = sprintf( "  %-34s [%s|%s] %s %s, %s: nearest %s @%.2f, then %s @%.2f", $title, $facts['kind'], mb_substr( $obj, 0, 28 ), $from, '' !== $reason ? 'OUT (' . $reason . ')' : 'stays', $pick['why'], isset( $pick['nearest'] ) ? $name( $pick['nearest'] ) : '-', $pick['score'], $pick['runner_up'] ? $name( $pick['runner_up'] ) : '-', $pick['runner_score'] );
    }
    if ( preg_match( $pat, $title . ' ' . $obj ) ) {
        $named[] = sprintf( "  %-34s [%s|%s] -> %s  (%s @%.2f)", $title, $facts['kind'], mb_substr( $obj, 0, 28 ), $pick['term_id'] ? $name( $pick['term_id'] ) : 'UNFILED', $pick['why'], $pick['score'] );
    }
}
printf( "\n%d pictures scored in %.1fs: %d filed, %d left alone (%s)\n", count( $rows ), microtime( true ) - $t0, array_sum( $into ), array_sum( $why ), wp_json_encode( $why ) );
echo "\n=== where they would go\n";
arsort( $into );
foreach ( $into as $tid => $n ) { printf( "  %4d  %s\n", $n, $name( $tid ) ); }
printf( "\n=== moves (%d), first 40\n", count( $moves ) );
foreach ( array_slice( $moves, 0, 40 ) as $m ) { echo $m, "\n"; }
printf( "\n=== left alone (%d), first 30\n", count( $stay ) );
foreach ( array_slice( $stay, 0, 30 ) as $m ) { echo $m, "\n"; }
printf( "\n=== the cases named (%d)\n", count( $named ) );
foreach ( array_slice( $named, 0, 40 ) as $m ) { echo $m, "\n"; }
