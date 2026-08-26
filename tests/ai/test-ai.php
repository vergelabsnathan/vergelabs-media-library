<?php
/**
 *  The AI layer, tested with the mock provider.
 *
 *  Run on the box:  wp eval-file /tmp/test-ai.php --allow-root
 */

define( 'VERGEML_AI_MOCK', true );

// the search module only acts inside the admin; the CLI is not that
if ( ! defined( 'WP_ADMIN' ) ) {
    define( 'WP_ADMIN', true );
}

$GLOBALS['vgml_pass'] = 0;
$GLOBALS['vgml_fail'] = 0;

function ai_check( $name, $ok, $detail = '' ) {
    if ( $ok ) {
        $GLOBALS['vgml_pass']++;
        echo "  ok   {$name}" . ( $detail ? "  -- {$detail}" : '' ) . "\n";
    } else {
        $GLOBALS['vgml_fail']++;
        echo "  FAIL {$name}" . ( $detail ? "  -- {$detail}" : '' ) . "\n";
    }
}

echo "\nthe mock provider\n";

$images = get_posts( array(
    'post_type'      => 'attachment',
    'post_mime_type' => 'image',
    'posts_per_page' => 3,
    'orderby'        => 'ID',
    'order'          => 'ASC',
    'fields'         => 'ids',
) );

ai_check( 'images to work with', count( $images ) >= 3, count( $images ) . ' found' );

// a clean slate for the ones we use
foreach ( $images as $id ) {
    delete_post_meta( $id, '_vergeml_ai' );
}
delete_post_meta( $images[0], '_wp_attachment_image_alt' );
update_post_meta( $images[1], '_wp_attachment_image_alt', 'Hand-written alt that must survive' );

$described = vergeml_ai_describe( $images[0] );
ai_check( 'describe returns a caption', ! is_wp_error( $described ) && ! empty( $described['caption'] ), is_wp_error( $described ) ? $described->get_error_message() : $described['caption'] );
ai_check( 'and alt, tags, title', ! empty( $described['alt'] ) && is_array( $described['tags'] ) && isset( $described['title'] ) );

echo "\nthe index step\n";

$before_pending = count( vergeml_ai_pending( 'unindexed' ) );
$step = vergeml_ai_index_step( 'unindexed', 3, true );

ai_check( 'a step describes up to three files', count( $step['described'] ) === min( 3, $before_pending ), count( $step['described'] ) . ' described' );
ai_check( 'remaining shrinks by the batch', $step['remaining'] === $before_pending - count( $step['described'] ) - count( $step['errors'] ),
    "{$before_pending} -> {$step['remaining']}" );

$meta = get_post_meta( $images[0], '_vergeml_ai', true );
ai_check( 'the description is stored', is_array( $meta ) && ! empty( $meta['caption'] ) );
ai_check( 'described files leave the pending pool', ! in_array( $images[0], vergeml_ai_pending( 'unindexed' ), true ) );

echo "\nalt text\n";

$alt0 = get_post_meta( $images[0], '_wp_attachment_image_alt', true );
$alt1 = get_post_meta( $images[1], '_wp_attachment_image_alt', true );
ai_check( 'empty alt was filled from the description', '' !== $alt0 && false !== strpos( $alt0, 'Mock alt' ), $alt0 );
ai_check( 'hand-written alt was left alone', 'Hand-written alt that must survive' === $alt1 );

$missing_before = count( vergeml_ai_pending( 'missing-alt' ) );
$alt_step = vergeml_ai_index_step( 'missing-alt', 5, true );
$missing_after = count( vergeml_ai_pending( 'missing-alt' ) );
ai_check( 'the missing-alt pass shrinks the pool', $missing_after <= max( 0, $missing_before - count( $alt_step['described'] ) ) || 0 === $missing_before,
    "{$missing_before} -> {$missing_after}" );

echo "\nsearch knows the captions\n";

// the mock caption contains words from the filename; search for a caption-only
// marker instead: write one deliberately
$probe = $images[2];
update_post_meta( $probe, '_vergeml_ai', array( 'caption' => 'zzqxunique lighthouse at dusk', 'alt' => 'x', 'tags' => array( 'zzqxunique' ), 'title' => 'x', 'time' => time() ) );

$q = new WP_Query( array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    's'              => 'zzqxunique',
    'posts_per_page' => 10,
    'fields'         => 'ids',
) );
ai_check( 'a caption-only word finds the file', in_array( $probe, $q->posts, true ), count( $q->posts ) . ' results' );

$settings = vergeml_ai_settings();
$settings['enrich_search'] = 0;
update_option( 'vergeml_ai', $settings, false );

$q2 = new WP_Query( array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    's'              => 'zzqxunique',
    'posts_per_page' => 10,
    'fields'         => 'ids',
) );
ai_check( 'and the toggle turns that off', ! in_array( $probe, $q2->posts, true ), count( $q2->posts ) . ' results' );

$settings['enrich_search'] = 1;
update_option( 'vergeml_ai', $settings, false );

echo "\nguard rails\n";

$doc = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'application/pdf', 'posts_per_page' => 1, 'fields' => 'ids' ) );
if ( $doc ) {
    $r = vergeml_ai_describe( $doc[0] );
    ai_check( 'non-images are refused politely', is_wp_error( $r ) && 'vergeml_ai_not_image' === $r->get_error_code() );
} else {
    ai_check( 'non-images are refused politely', true, 'no pdf on box, skipped' );
}

$status_counts = array(
    'unindexed'   => count( vergeml_ai_pending( 'unindexed' ) ),
    'missing_alt' => count( vergeml_ai_pending( 'missing-alt' ) ),
);
ai_check( 'pending pools are countable', $status_counts['unindexed'] >= 0 && $status_counts['missing_alt'] >= 0,
    wp_json_encode( $status_counts ) );

// tidy the probe marker
delete_post_meta( $probe, '_vergeml_ai' );

printf( '%d/%d passed' . PHP_EOL, $GLOBALS['vgml_pass'], $GLOBALS['vgml_pass'] + $GLOBALS['vgml_fail'] );
if ( $GLOBALS['vgml_fail'] > 0 ) {
    exit( 1 );
}
