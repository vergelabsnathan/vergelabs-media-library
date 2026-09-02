<?php
/*
 *  Does a re-filing job get through the whole library?
 *
 *  The bug this guards was invisible from the screen: re-filing stopped at
 *  five thousand pictures, reported success, and left the rest where they
 *  were -- so "it sorted my library" and "most of my library is unsorted"
 *  were both true at once and nothing said so.
 *
 *  This drives the job the way cron drives it, with a slice size small enough
 *  that a library of a couple of hundred needs several passes, and checks that
 *  every described picture was looked at rather than the first slice of them.
 *
 *  It re-files for real, and puts it back at the end.
 */

global $wpdb;

$taxonomy = vergeml_librarian_taxonomy();
$table    = $wpdb->prefix . 'vergeml_ai_index';

$total = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$table} WHERE error = '' AND embedding IS NOT NULL"
);

if ( $total < 20 ) {
    echo "only {$total} described pictures here -- not enough to show batching\n";
    return;
}

/*
 *  Forced small, so this library needs several passes.
 *
 *  The first version of this ran with the shipping sizes against 205
 *  pictures, finished in the opening pass, and printed PASS -- proving that
 *  re-filing works when it never has to resume, which is the one case that
 *  was never broken. Resumption is the whole change; a check that cannot
 *  reach it is a check that cannot fail.
 */
add_filter( 'vergeml_talk_slice', function () { return 25; } );
add_filter( 'vergeml_talk_pass',  function () { return 50; } );

printf( "%d described pictures, slices of 25, 50 per pass -- so this must resume\n\n", $total );

$folders = array(
    array( 'name' => 'Apparel',   'parent' => '', 'matches' => 'clothing, shoes, bags worn or displayed' ),
    array( 'name' => 'Places',    'parent' => '', 'matches' => 'buildings, streets, interiors and landscapes' ),
    array( 'name' => 'Objects',   'parent' => '', 'matches' => 'tools, devices and things on a surface' ),
);

$started = microtime( true );
$result  = vergeml_talk_apply( $folders );

if ( is_wp_error( $result ) ) {
    printf( "apply failed: %s -- %s\n", $result->get_error_code(), $result->get_error_message() );
    return;
}

printf( "first pass returned in %.1fs: seen %d of %d, running=%s\n",
    microtime( true ) - $started,
    (int) $result['seen'],
    (int) $result['total'],
    ! empty( $result['running'] ) ? 'yes' : 'no'
);

/*
 *  Now be cron. Each turn is a fresh call into the same entry point the
 *  scheduled event uses, so this exercises resumption from the stored state
 *  rather than a loop that happens to keep its variables.
 */
$turns = 0;

while ( $turns < 200 ) {

    $state = get_option( VERGEML_TALK_STATE );

    if ( ! is_array( $state ) || empty( $state['active'] ) ) {
        break;
    }

    vergeml_talk_refile_run( microtime( true ) + 5.0 );
    $turns++;
}

$final = vergeml_talk_report( get_option( VERGEML_TALK_STATE ) );

printf( "\nafter %d more pass(es): seen %d of %d, moved %d, skipped %d\n",
    $turns,
    (int) $final['seen'],
    (int) $final['total'],
    (int) $final['moved'],
    (int) $final['skipped']
);

$seen    = (int) $final['seen'];
$covered = $seen >= $total;

printf( "\n%s the job actually had to resume (%d further pass(es), more than one needed)\n",
    $turns > 0 ? 'PASS:' : 'FAIL:',
    $turns
);

printf( "%s every described picture was looked at (%d of %d)\n",
    $covered ? 'PASS:' : 'FAIL:',
    $seen,
    $total
);

printf( "%s moved and skipped account for everything seen (%d + %d = %d)\n",
    ( (int) $final['moved'] + (int) $final['skipped'] ) === $seen ? 'PASS:' : 'FAIL:',
    (int) $final['moved'],
    (int) $final['skipped'],
    (int) $final['moved'] + (int) $final['skipped']
);

// What actually landed in the taxonomy, which is the only thing a customer sees.
foreach ( array( 'Apparel', 'Places', 'Objects' ) as $name ) {
    $term = get_term_by( 'name', $name, $taxonomy );
    printf( "  %-10s %s files\n", $name, $term && ! is_wp_error( $term ) ? (int) $term->count : 'missing' );
}

// ------------------------------------------------------------------ put it back

$undone = vergeml_talk_undo();

printf( "\n%s\n", is_wp_error( $undone )
    ? 'undo failed: ' . $undone->get_error_message()
    : $undone['message'] );
