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
