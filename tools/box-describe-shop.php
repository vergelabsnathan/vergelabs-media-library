<?php
/**
 *  Describe the shop library, and wait for it.
 *
 *      scp tools/box-describe-shop.php root@box:/tmp/vgml-describe-shop.php
 *      VGML_N=500 bash tools/box-describe-shop.sh        (on the box, in the background)
 *
 *  The second library of every-picture-a-home C.5, on the box's second
 *  network. Every undescribed picture goes through the shipping describe
 *  path -- vergeml_ai_index_step(), the same call the background run makes
 *  per tick -- ten at a time from this process, so the pass is watched from
 *  one log and stops at VGML_N or when nothing is pending. No wp-cron, no
 *  nudge: the run state option is left alone, so the AI screen shows no run.
 *
 *  SPENDS. One credit a picture on the box's licence, and the model's cost
 *  behind it (~EUR 0.0048 a picture, 500 ~ EUR 2.40). Run only with Nathan's
 *  yes, said with the number.
 *
 *  Sets the site profile first when it is empty, as a shop would on its
 *  settings screen: the describer reads it for wording and subject.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$want  = max( 1, (int) ( getenv( 'VGML_N' ) ? getenv( 'VGML_N' ) : 500 ) );
$table = vergeml_index_table();

if ( ! vergeml_ai_ready() ) {
    echo "not connected: no licence key on this site\n";
    return;
}

$settings = get_option( 'vergeml_ai', array() );
if ( empty( $settings['site_profile'] ) ) {
    $settings['site_profile'] = 'An online department store. Product photographs across clothing, shoes, bags, jewellery, beauty, electronics, home, kitchen, food and drink, sports, toys, garden, books and music, pets, tools and cars. Name the product plainly: the kind of thing it is, then what kind of that.';
    update_option( 'vergeml_ai', $settings, false );
    echo "site profile set (was empty)\n";
}

$pending = vergeml_ai_pending_count( 'unindexed' );
$credits = vergeml_ai_refresh_credits( true );
printf( "service %s · credits %s · pending %d · will describe up to %d\n\n", vergeml_ai_service_url(), null === $credits ? '?' : $credits, $pending, $want );

$started   = time();
$described = 0;
$failed    = 0;
$said      = array();

while ( $described + $failed < $want && time() - $started < 90 * 60 ) {

    $step = vergeml_ai_index_step( 'unindexed', min( 10, $want - $described - $failed ), false );

    if ( is_wp_error( $step ) ) {
        printf( "  %5ds  STOP %s\n", time() - $started, $step->get_error_message() );
        break;
    }

    // The step answers the way the background tick reads it: done rows, error rows ({id, error, fatal}), what is still pending.
    $got   = count( (array) $step['described'] );
    $bad   = count( (array) $step['errors'] );
    $fatal = false;

    $described += $got;
    $failed    += $bad;

    foreach ( (array) $step['errors'] as $e ) {
        $fatal = $fatal || ! empty( $e['fatal'] );
        if ( count( $said ) < 8 ) {
            $said[] = (int) $e['id'] . ': ' . $e['error'];
        }
    }

    printf( "  %5ds  described %4d  failed %3d  remaining %4d%s\n", time() - $started, $described, $failed, (int) $step['remaining'], ! empty( $step['held'] ) ? '  held ' . (int) $step['held'] : '' );

    if ( $fatal ) {
        echo "  STOP: the service refused for a reason that will not change (credits or the key)\n";
        break;
    }

    if ( 0 === (int) $step['remaining'] || ( 0 === $got && 0 === $bad ) ) {
        break;
    }
}

$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE error = '' AND embedding IS NOT NULL" );
$kinds = (array) $wpdb->get_results( "SELECT kind, COUNT(*) n FROM {$table} WHERE error = '' GROUP BY kind ORDER BY n DESC", ARRAY_A );

printf( "\ndone after %ds: described %d, failed %d; %d rows with an embedding; credits %s\n", time() - $started, $described, $failed, $rows, (string) vergeml_ai_refresh_credits( true ) );
foreach ( $kinds as $k ) {
    printf( "  kind %-12s %d\n", $k['kind'], (int) $k['n'] );
}
foreach ( $said as $line ) {
    echo "  error ", $line, "\n";
}
