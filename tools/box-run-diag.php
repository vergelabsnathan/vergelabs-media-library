<?php
/*
 *  What happened to the re-describe run. Read-only.
 */
global $wpdb;
$t = $wpdb->prefix . 'vergeml_ai_index';

echo "=== background run state\n";
$s = function_exists( 'vergeml_ai_run_state' ) ? vergeml_ai_run_state() : array();
foreach ( array( 'active', 'scope', 'total', 'described', 'failed', 'remaining', 'started_at', 'stopped' ) as $k ) {
    printf( "  %-11s %s\n", $k . ':', var_export( $s[ $k ] ?? null, true ) );
}
printf( "  cron next:   %s\n", wp_next_scheduled( 'vergeml_ai_run_tick' ) ? 'scheduled' : 'none' );

echo "\n=== error strings on index rows\n";
foreach ( $wpdb->get_results( "SELECT error, COUNT(*) n, MIN(updated_at) first_at, MAX(updated_at) last_at FROM {$t} WHERE model <> 'mock' GROUP BY error ORDER BY n DESC", ARRAY_A ) as $r ) {
    printf( "  %4d  %-40s  %s .. %s\n", (int) $r['n'], '' === $r['error'] ? '(no error)' : $r['error'], $r['first_at'], $r['last_at'] );
}

echo "\n=== do the errored rows still hold their old description?\n";
$r = $wpdb->get_row( "SELECT COUNT(*) n, SUM(caption <> '') with_caption, SUM(embedding IS NOT NULL) with_embedding, SUM(alt <> '') with_alt FROM {$t} WHERE error <> '' AND model <> 'mock'", ARRAY_A );
printf( "  errored: %d   caption kept: %d   embedding kept: %d   alt kept: %d\n", (int) $r['n'], (int) $r['with_caption'], (int) $r['with_embedding'], (int) $r['with_alt'] );

echo "\n=== errored rows by prompt they were last described under\n";
foreach ( $wpdb->get_results( "SELECT prompt_hash, COUNT(*) n FROM {$t} WHERE error <> '' AND model <> 'mock' GROUP BY prompt_hash", ARRAY_A ) as $r ) {
    printf( "  %-16s %d\n", '' === $r['prompt_hash'] ? '(none)' : substr( $r['prompt_hash'], 0, 12 ) . '…', (int) $r['n'] );
}

echo "\n=== the last five rows touched\n";
foreach ( $wpdb->get_results( "SELECT attachment_id, error, LEFT(prompt_hash,12) ph, updated_at, described_at FROM {$t} WHERE model <> 'mock' ORDER BY updated_at DESC LIMIT 5", ARRAY_A ) as $r ) {
    printf( "  #%-6d err=%-28s prompt=%s… updated=%s described=%s\n", (int) $r['attachment_id'], '' === $r['error'] ? '-' : $r['error'], $r['ph'], $r['updated_at'], $r['described_at'] ?? 'NULL' );
}

echo "\n=== credits\n";
printf( "  %s\n", var_export( vergeml_ai_refresh_credits( true ), true ) );

echo "\n=== the rows still on the old prompt\n";
$stamp = vergeml_index_current_stamp();
$old = $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id, LEFT(prompt_hash,12) ph, described_at, error FROM {$t} WHERE model <> 'mock' AND prompt_hash <> %s AND prompt_hash <> '' ORDER BY attachment_id LIMIT 8", $stamp['prompt_hash'] ), ARRAY_A );
printf( "  count: %d   pending('stale') now: %d   on hold (recent): %d\n", (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE model <> 'mock' AND prompt_hash <> %s AND prompt_hash <> ''", $stamp['prompt_hash'] ) ), vergeml_ai_pending_count( 'stale' ), count( vergeml_ai_recently_described() ) );
foreach ( $old as $r ) { printf( "  #%-6d %s… described=%s err=%s\n", (int) $r['attachment_id'], $r['ph'], $r['described_at'], '' === $r['error'] ? '-' : $r['error'] ); }

echo "\n=== the two search misses, in their own words\n";
foreach ( array( 'sofa' => "post_title LIKE '%Sofa%'", 'boots' => "post_title LIKE '%Boot%'" ) as $label => $where ) {
    foreach ( $wpdb->get_results( "SELECT p.ID, p.post_title, i.caption, i.filing FROM {$wpdb->posts} p JOIN {$t} i ON i.attachment_id = p.ID WHERE {$where} AND i.error = '' LIMIT 2", ARRAY_A ) as $r ) {
        $f = json_decode( (string) $r['filing'], true ) ?: array();
        printf( "  [%s] #%d %s\n     caption: %s\n     object=%s | material=%s | colour=%s | setting=%s\n", $label, (int) $r['ID'], $r['post_title'], mb_substr( (string) $r['caption'], 0, 150 ), $f['object'] ?? '', $f['material'] ?? '', $f['colour'] ?? '', $f['setting'] ?? '' );
    }
}
$top = $wpdb->get_row( "SELECT p.ID, p.post_title, i.caption FROM {$wpdb->posts} p JOIN {$t} i ON i.attachment_id = p.ID WHERE p.post_title LIKE '%madrids-photographer%' LIMIT 1", ARRAY_A );
if ( $top ) { printf( "  [top hit for 'leather boots'] #%d %s\n     caption: %s\n", (int) $top['ID'], $top['post_title'], mb_substr( (string) $top['caption'], 0, 150 ) ); }

echo "\n=== folders, like-for-like: titled pictures only (proxy for the original 205)\n";
$rows = $wpdb->get_results( "SELECT i.attachment_id, i.embedding FROM {$t} i JOIN {$wpdb->posts} p ON p.ID = i.attachment_id WHERE i.error = '' AND i.embedding IS NOT NULL AND i.model <> 'mock' AND p.post_title NOT REGEXP '^(gallery|slide|[0-9]+$|Vgml Fx|p-|b-)'", ARRAY_A );
$full = array(); foreach ( $rows as $r ) { $v = vergeml_index_vector_out( $r['embedding'] ); if ( $v ) { $full[ (int) $r['attachment_id'] ] = $v; } }
printf( "  (%d titled pictures)\n", count( $full ) );
foreach ( array( 'Footwear', 'Furniture', 'Bicycles', "Women's apparel", 'Leather', 'Studio shots', 'Winter', 'Interiors' ) as $folder ) {
    $q = vergeml_meaning_vector( $folder ); if ( ! $q ) { continue; }
    $s = array(); foreach ( $full as $id => $v ) { $s[ $id ] = vergeml_meaning_similarity( $q, $v ); } arsort( $s );
    $top = array(); foreach ( array_slice( $s, 0, 5, true ) as $id => $sc ) { $top[] = mb_substr( get_the_title( $id ), 0, 26 ); }
    printf( "  %-16s %s\n", $folder . ':', implode( ' | ', $top ) );
}
