<?php
/*
 *  Does a folder plan come back, and does it follow the instruction?
 *
 *  The planning model was reverted once for not answering inside the time the
 *  plugin waits, so the first thing to establish is that it answers at all --
 *  and how much of the 90 seconds it uses, because a plan that lands at 88 is
 *  not one to ship.
 *
 *  The instruction is the one that failed: Apparel with Women and Men under
 *  it. What came back last time was a Footwear folder nobody asked for.
 */

$instruction = 'Sort these into Apparel, with Women and Men as subfolders under it';

printf( "asking: %s\n\n", $instruction );

$started = microtime( true );
$plan    = vergeml_talk_propose( $instruction );
$took    = microtime( true ) - $started;

if ( is_wp_error( $plan ) ) {
    printf( "FAILED after %.1fs: %s -- %s\n", $took, $plan->get_error_code(), $plan->get_error_message() );
    return;
}

printf( "answered in %.1fs (the plugin waits 90)\n\n", $took );

if ( ! empty( $plan['note'] ) ) {
    printf( "it said: %s\n\n", $plan['note'] );
}

printf( "folders proposed:\n" );

foreach ( (array) $plan['folders'] as $f ) {
    printf(
        "  %s%s\n",
        '' !== $f['parent'] ? '    ' . $f['parent'] . ' / ' : '  ',
        $f['name']
    );
}

/*
 *  The instruction named three folders and a shape. Anything else in the list
 *  is the model deciding for itself, which is the whole complaint.
 */
$asked  = array( 'apparel', 'women', 'men' );
$extra  = array();

foreach ( (array) $plan['folders'] as $f ) {
    if ( ! in_array( strtolower( $f['name'] ), $asked, true ) ) {
        $extra[] = $f['name'];
    }
}

printf( "\nasked for 3 folders, got %d\n", count( $plan['folders'] ) );

if ( $extra ) {
    printf( "not asked for: %s\n", implode( ', ', $extra ) );
} else {
    printf( "nothing was invented.\n" );
}
