<?php
/*
 *  Re-describe every picture under the current prompt, and wait for it.
 *
 *  The 'stale' scope compares each row's prompt_hash against the hash of the
 *  most recently described row. Until one picture has been described under
 *  the new prompt, that stamp is the OLD hash and nothing is stale -- so the
 *  run has nothing to do. The seed below fixes that: one row is put back to
 *  undescribed, described through the shipping code path, and becomes the
 *  newest row. Every other row is then stale by definition and the normal
 *  background run does the rest.
 *
 *  Spends one credit per picture. Writes for real.
 */

global $wpdb;
$table = $wpdb->prefix . 'vergeml_ai_index';

$before = vergeml_index_current_stamp();
printf( "stamp before:  %s\n", '' === $before['prompt_hash'] ? '(none)' : substr( $before['prompt_hash'], 0, 12 ) . '…' );

$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE error = '' AND model <> 'mock' AND embedding IS NOT NULL" );
printf( "described rows now: %d\n", $total );

// ------------------------------------------------------------------- seed

$seed = (int) $wpdb->get_var(
    "SELECT attachment_id FROM {$table}
      WHERE error = '' AND model <> 'mock' AND embedding IS NOT NULL
   ORDER BY attachment_id ASC LIMIT 1"
);

if ( $seed < 1 ) { echo "nothing to seed with\n"; return; }

$wpdb->update( $table, array( 'described_at' => null, 'embedding' => null, 'projection' => null, 'prompt_hash' => '' ), array( 'attachment_id' => $seed ) );

$t = microtime( true );
$step = vergeml_ai_index_step( 'unindexed', 1, false );
printf( "seed %d described in %.1fs: %s\n", $seed, microtime( true ) - $t,
    is_wp_error( $step ) ? 'ERROR ' . $step->get_error_message() : wp_json_encode( array_intersect_key( (array) $step, array_flip( array( 'described', 'failed', 'remaining' ) ) ) ) );

$after = vergeml_index_current_stamp();
printf( "stamp after:   %s\n", '' === $after['prompt_hash'] ? '(none)' : substr( $after['prompt_hash'], 0, 12 ) . '…' );

if ( $after['prompt_hash'] === $before['prompt_hash'] ) {
    echo "STOP: the prompt hash did not change, so the service is still describing under the old prompt. Nothing else will be re-described.\n";
    return;
}

$stale = vergeml_ai_pending_count( 'stale' );
printf( "rows now stale: %d\n\n", $stale );

// -------------------------------------------------------------------- run

$state = vergeml_ai_run_start( 'stale', false );
if ( is_wp_error( $state ) ) { echo 'could not start: ' . $state->get_error_message() . "\n"; return; }

printf( "run started: total %d\n", (int) $state['total'] );

$started = time();
$last    = -1;

while ( time() - $started < 45 * 60 ) {

    if ( function_exists( 'vergeml_ai_run_nudge' ) ) { vergeml_ai_run_nudge(); }
    sleep( 30 );

    $s = vergeml_ai_run_state();
    $done = (int) $s['described'] + (int) $s['failed'];

    if ( $done !== $last ) {
        printf( "  %5ds  described %4d  failed %3d  remaining %4d%s\n",
            time() - $started, (int) $s['described'], (int) $s['failed'], (int) $s['remaining'],
            '' !== (string) ( $s['stopped'] ?? '' ) ? '  stopped: ' . $s['stopped'] : '' );
        $last = $done;
    }

    if ( empty( $s['active'] ) ) { break; }
}

$s = vergeml_ai_run_state();
printf( "\nfinished after %ds: described %d, failed %d, remaining %d, active=%s\n",
    time() - $started, (int) $s['described'], (int) $s['failed'], (int) $s['remaining'], empty( $s['active'] ) ? 'no' : 'YES (still running)' );

$new = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$table} WHERE error = '' AND model <> 'mock' AND prompt_hash = %s", $after['prompt_hash'] ) );
printf( "rows on the new prompt: %d of %d\n", $new, $total );
