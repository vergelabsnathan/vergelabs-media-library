<?php
/*
 *  The planner's suggested tree for this library, through the same functions
 *  the chat uses. Prints the proposal; VGML_APPLY=1 applies it and drives the
 *  re-filing to the end, then prints the outcome sentence the chat would show.
 */

$apply       = '1' === (string) getenv( 'VGML_APPLY' );
$instruction = getenv( 'VGML_INSTRUCTION' );
if ( ! is_string( $instruction ) || '' === trim( $instruction ) ) {
    $instruction = 'Keep the folders I have. Add folders for what is still unfiled -- bikes and cycling, phones and gadgets, cosmetics and personal care, skateboarding, logos and icons -- and anything else with enough pictures behind it. Do not split by men and women unless the pictures show it.';
}

$t0 = microtime( true );
$mode = 'literal' === (string) getenv( 'VGML_MODE' ) ? 'literal' : 'suggested';
$r  = vergeml_talk_propose( $instruction, array(), $mode );
if ( is_wp_error( $r ) ) { echo 'propose failed: ', $r->get_error_message(), "\n"; return; }
printf( "proposal in %.1fs (%s)\nnote: %s\n", microtime( true ) - $t0, $apply ? 'APPLYING' : 'dry run', $r['note'] );
foreach ( $r['folders'] as $f ) {
    printf( "  %-40s classes: %-38s kinds: %-18s audience: %s\n",
        ( '' !== $f['parent'] ? $f['parent'] . ' / ' : '' ) . $f['name'],
        implode( ', ', array_slice( (array) ( $f['classes'] ?? array() ), 0, 4 ) ),
        implode( ',', (array) ( $f['kinds'] ?? array() ) ),
        '' === ( $f['audience'] ?? '' ) ? '-' : $f['audience'] );
}
if ( ! $apply ) { return; }

$t0 = microtime( true );
$a  = vergeml_talk_apply( $r['folders'] );
if ( is_wp_error( $a ) ) { echo 'apply failed: ', $a->get_error_message(), "\n"; return; }
// Drive the resumable re-filing to the end, the way cron would.
for ( $i = 0; $i < 120; $i++ ) {
    wp_cache_delete( VERGEML_TALK_STATE, 'options' );
    $state = get_option( VERGEML_TALK_STATE );
    if ( ! is_array( $state ) || empty( $state['active'] ) ) { break; }
    vergeml_talk_refile_run( time() + 20 );
}
wp_cache_delete( VERGEML_TALK_STATE, 'options' );
$report = vergeml_talk_report( get_option( VERGEML_TALK_STATE ) );
printf( "\napplied in %.1fs\n%s\n", microtime( true ) - $t0, $report['message'] );
echo "counts: ", wp_json_encode( $report['counts'] ), "\n";
