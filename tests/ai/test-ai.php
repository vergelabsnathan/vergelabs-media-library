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
    vergeml_index_delete( $id );
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

$row = vergeml_index_get( $images[0] );
ai_check( 'the description is stored in the index', is_array( $row ) && ! empty( $row['caption'] ) );
ai_check( 'with its tags as an array', is_array( $row['tags'] ) && count( $row['tags'] ) > 0, wp_json_encode( $row['tags'] ) );
ai_check( 'and stamped with what produced it',
    'mock' === $row['model'] && '' !== $row['model_version'] && '' !== $row['prompt_hash'],
    "{$row['model']} {$row['model_version']} {$row['prompt_hash']}" );
ai_check( 'orientation was worked out without a model',
    in_array( $row['orientation'], array( 'landscape', 'portrait', 'square' ), true ), $row['orientation'] );
ai_check( 'nothing was written to the old postmeta key', '' === (string) get_post_meta( $images[0], '_vergeml_ai', true ) );
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
vergeml_index_set( $probe, array(
    'caption'      => 'zzqxunique lighthouse at dusk',
    'alt'          => 'x',
    'tags'         => array( 'zzqxunique' ),
    'title'        => 'x',
    'described_at' => current_time( 'mysql', true ),
) );

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

echo "\nthe sealed licence\n";

$sealed = vergeml_ai_seal( 'vgml_test_key_123' );
ai_check( 'sealing produces a v1 blob, not the key', 0 === strpos( $sealed, 'v1:' ) && false === strpos( $sealed, 'test_key' ) );
ai_check( 'and it unseals to the exact key', 'vgml_test_key_123' === vergeml_ai_unseal( $sealed ) );
ai_check( 'garbage does not unseal', '' === vergeml_ai_unseal( 'v1:not-a-blob' ) && '' === vergeml_ai_unseal( 'plaintext' ) );
ai_check( 'the service url is pinned to https', 0 === strpos( vergeml_ai_service_url(), 'https://' ) );

echo "\nguard rails\n";

/*
 *  post_status matters here. Attachments are 'inherit' and get_posts defaults
 *  to 'publish', so this asked for something that cannot exist and reported
 *  "no pdf on box, skipped" on a box with fifteen of them -- a check that
 *  passed by never running, which is worse than one that fails.
 */
$doc = get_posts( array(
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => 'application/pdf',
    'posts_per_page' => 1,
    'fields'         => 'ids',
) );

if ( $doc ) {
    $r = vergeml_ai_describe( $doc[0] );
    ai_check( 'non-images are refused politely', is_wp_error( $r ) && 'vergeml_ai_not_image' === $r->get_error_code(),
        is_wp_error( $r ) ? $r->get_error_code() : 'described a pdf' );
} else {
    // Not a pass. A box with no PDF cannot answer this, and saying so is the
    // honest result.
    ai_check( 'non-images are refused politely', false, 'NO PDF ON THE BOX -- seed one, this check did not run' );
}

$status_counts = array(
    'unindexed'   => count( vergeml_ai_pending( 'unindexed' ) ),
    'missing_alt' => count( vergeml_ai_pending( 'missing-alt' ) ),
);
ai_check( 'pending pools are countable', $status_counts['unindexed'] >= 0 && $status_counts['missing_alt'] >= 0,
    wp_json_encode( $status_counts ) );

echo "\nthe index itself\n";

// The migration: a legacy blob, and the walk that copies it in.
$legacy_id = $images[1];
vergeml_index_delete( $legacy_id );
update_post_meta( $legacy_id, '_vergeml_ai', array(
    'caption' => 'zzlegacy caption from postmeta',
    'alt'     => 'zzlegacy alt',
    'tags'    => array( 'zzlegacy', 'carried', 'over' ),
    'title'   => 'ZZ Legacy Title',
    'model'   => 'old-model',
    'time'    => 1750000000,
) );

$cursor = 0;
$steps  = 0;
do {
    $migration = vergeml_index_migrate_step( $cursor );
    $cursor    = $migration['cursor'];
    $steps++;
} while ( ! $migration['done'] && $steps < 200 );

$carried = vergeml_index_get( $legacy_id );

ai_check( 'the migration finishes', ! empty( $migration['done'] ), "{$steps} steps" );
ai_check( 'a postmeta description is carried into the table',
    is_array( $carried ) && 'zzlegacy caption from postmeta' === $carried['caption'] );
ai_check( 'with its tags and its model',
    array( 'zzlegacy', 'carried', 'over' ) === $carried['tags'] && 'old-model' === $carried['model'],
    wp_json_encode( $carried['tags'] ) );
ai_check( 'and its timestamp, as a date rather than the epoch',
    '2025-06-15' === substr( (string) $carried['described_at'], 0, 10 ), (string) $carried['described_at'] );
ai_check( 'the old postmeta is left exactly where it was',
    is_array( get_post_meta( $legacy_id, '_vergeml_ai', true ) ) );

$again = vergeml_index_migrate_step( 0 );
ai_check( 'migrating again moves nothing', 0 === (int) $again['moved'] && ! empty( $again['done'] ) );

// Edit protection: the field somebody wrote by hand is not painted over.
vergeml_index_set( $legacy_id, array( 'alt' => 'generated alt' ), true );
update_post_meta( $legacy_id, '_wp_attachment_image_alt', 'A human wrote this' );

$locked = vergeml_index_get( $legacy_id );
ai_check( 'editing alt text locks that field', in_array( 'alt', $locked['locked'], true ), wp_json_encode( $locked['locked'] ) );

vergeml_index_set( $legacy_id, array( 'alt' => 'the pipeline trying again' ) );
ai_check( 'and a later run does not overwrite it',
    'generated alt' === vergeml_index_get( $legacy_id )['alt'],
    vergeml_index_get( $legacy_id )['alt'] );

ai_check( 'unless it is told to explicitly',
    vergeml_index_set( $legacy_id, array( 'alt' => 'forced' ), true )
    && 'forced' === vergeml_index_get( $legacy_id )['alt'] );

// The pipeline's own writes must not read as somebody typing.
vergeml_index_delete( $legacy_id );
vergeml_index_set( $legacy_id, array( 'caption' => 'x' ) );
vergeml_index_writing( true );
update_post_meta( $legacy_id, '_wp_attachment_image_alt', 'written by the pipeline' );
vergeml_index_writing( false );
ai_check( 'the pipeline filling alt in does not lock it',
    ! in_array( 'alt', vergeml_index_get( $legacy_id )['locked'], true ) );

// Embeddings: no service produces one yet, but the storage has to survive a
// round trip or the phase that needs it starts by debugging this.
$vector = array( 0.5, -0.25, 0.125, 1.0 );
vergeml_index_set( $legacy_id, array( 'embedding' => $vector ) );
$back = vergeml_index_get( $legacy_id );
ai_check( 'an embedding survives the round trip', $vector === $back['embedding'], wp_json_encode( $back['embedding'] ) );
ai_check( 'and its dimensions are recorded', 4 === (int) $back['embedding_dims'] );

// A deleted file takes its row with it.
$doomed = wp_insert_post( array( 'post_title' => 'zzdoomed', 'post_type' => 'attachment', 'post_status' => 'inherit' ) );
vergeml_index_set( $doomed, array( 'caption' => 'gone soon' ) );
wp_delete_attachment( $doomed, true );
ai_check( 'deleting a file deletes its description', null === vergeml_index_get( $doomed ) );

// tidy the probe marker and the migration fixture
vergeml_index_delete( $probe );
delete_post_meta( $legacy_id, '_vergeml_ai' );
delete_post_meta( $legacy_id, '_wp_attachment_image_alt' );
vergeml_index_delete( $legacy_id );

printf( '%d/%d passed' . PHP_EOL, $GLOBALS['vgml_pass'], $GLOBALS['vgml_pass'] + $GLOBALS['vgml_fail'] );
if ( $GLOBALS['vgml_fail'] > 0 ) {
    exit( 1 );
}
