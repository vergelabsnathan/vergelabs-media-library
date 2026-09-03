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
 *  Two scopes, in order. 'stale' covers pictures described under the old
 *  prompt; 'unindexed' covers the ones never described at all, which on this
 *  box is most of the library -- 205 described out of 641. A re-describe that
 *  stopped at 'stale' would leave two thirds of the pictures with no
 *  description and the folders looking exactly as thin as before.
 *
 *  Spends one credit per picture. Writes for real.
 */

global $wpdb;
$table = $wpdb->prefix . 'vergeml_ai_index';

$short = function ( $hash ) {
    return '' === (string) $hash ? '(none)' : substr( (string) $hash, 0, 12 ) . '…';
};

$before = vergeml_index_current_stamp();
printf( "stamp before:  %s\n", $short( $before['prompt_hash'] ) );

$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE error = '' AND model <> 'mock' AND embedding IS NOT NULL" );
printf( "described rows now: %d\n", $total );

// ------------------------------------------------------------------- seed

$seed = (int) $wpdb->get_var(
    "SELECT attachment_id FROM {$table}
      WHERE error = '' AND model <> 'mock' AND embedding IS NOT NULL
   ORDER BY attachment_id ASC LIMIT 1"
);

if ( $seed < 1 ) {
    echo "nothing to seed with\n";
    return;
}

/*
 *  Deleted, not blanked. 'unindexed' is defined as the ABSENCE of an index row
 *  (i.attachment_id IS NULL), so a row with its columns nulled is still a row
 *  and is never selected -- which is exactly what happened on the first
 *  attempt: described 0 in 0.0s, and honestly so.
 */
$wpdb->delete( $table, array( 'attachment_id' => $seed ) );

/*
 *  The 59 pictures that errored last time carry stub rows, so neither scope
 *  would ever look at them again. Their stubs go too: one more attempt under
 *  the new prompt, and a fresh stub if they fail again.
 */
$retry = (int) $wpdb->query( "DELETE FROM {$table} WHERE error <> '' AND model <> 'mock'" );
printf( "errored stubs cleared for retry: %d
", $retry );

$has_col = (bool) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = %s AND column_name = 'filing'",
    $table
) );
printf( "filing column present: %s
", $has_col ? 'yes' : 'NO -- writes will fail' );
if ( ! $has_col ) { return; }

$t    = microtime( true );
$step = vergeml_ai_index_step( 'unindexed', 1, false );

printf(
    "seed %d described in %.1fs: %s\n",
    $seed,
    microtime( true ) - $t,
    is_wp_error( $step )
        ? 'ERROR ' . $step->get_error_message()
        : wp_json_encode( array_intersect_key( (array) $step, array_flip( array( 'described', 'failed', 'remaining' ) ) ) )
);

$after = vergeml_index_current_stamp();
printf( "stamp after:   %s\n", $short( $after['prompt_hash'] ) );

/*
 *  An unchanged stamp is only a problem on a FIRST run. On a sweep the stamp
 *  is already the current prompt, and what matters is whether anything is
 *  still behind it. The first version stopped here on a sweep and left the
 *  96 held pictures exactly where they were.
 */
if ( $after['prompt_hash'] === $before['prompt_hash'] && 0 === vergeml_ai_pending_count( 'stale' ) && 0 === vergeml_ai_pending_count( 'unindexed' ) ) {
    echo "STOP: the prompt hash did not change and nothing is pending -- either the service is still on the old prompt, or there is nothing left to do.
";
    return;
}

printf( "rows now stale: %d\n\n", vergeml_ai_pending_count( 'stale' ) );

// -------------------------------------------------------------------- runs

foreach ( array( 'stale', 'unindexed' ) as $scope ) {

    $state = vergeml_ai_run_start( $scope, false );

    if ( is_wp_error( $state ) ) {
        printf( "%s: %s\n\n", $scope, $state->get_error_message() );
        continue;
    }

    printf( "%s run started: total %d\n", $scope, (int) $state['total'] );

    $started = time();
    $last    = -1;

    while ( time() - $started < 45 * 60 ) {

        if ( function_exists( 'vergeml_ai_run_nudge' ) ) {
            vergeml_ai_run_nudge();
        }

        sleep( 30 );

        wp_cache_delete( VERGEML_AI_RUN_OPTION, 'options' ); $s    = vergeml_ai_run_state();
        $done = (int) $s['described'] + (int) $s['failed'];

        if ( $done !== $last ) {
            printf(
                "  %5ds  described %4d  failed %3d  remaining %4d%s\n",
                time() - $started,
                (int) $s['described'],
                (int) $s['failed'],
                (int) $s['remaining'],
                '' !== (string) ( $s['stopped'] ?? '' ) ? '  stopped: ' . $s['stopped'] : ''
            );
            $last = $done;
        }

        if ( empty( $s['active'] ) ) {
            break;
        }
    }

    wp_cache_delete( VERGEML_AI_RUN_OPTION, 'options' ); $s = vergeml_ai_run_state();

    printf(
        "%s finished after %ds: described %d, failed %d, remaining %d, active=%s\n\n",
        $scope,
        time() - $started,
        (int) $s['described'],
        (int) $s['failed'],
        (int) $s['remaining'],
        empty( $s['active'] ) ? 'no' : 'YES (still running)'
    );
}

// ------------------------------------------------------------------ result

$new = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$table} WHERE error = '' AND model <> 'mock' AND prompt_hash = %s",
    $after['prompt_hash']
) );

$library = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
);

printf( "rows on the new prompt: %d, of %d images in the library\n", $new, $library );
