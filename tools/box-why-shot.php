<?php
/**
 *  A picture with a real reason on it, left standing long enough to look at.
 *
 *  tests/tree/filing-trail.php builds four pictures, runs the real re-filing
 *  pass over them and tears every one of them down before it exits -- which is
 *  right for a suite and useless for a screenshot. This builds the same
 *  fixture, runs the same pass, and stops: the pictures stay until
 *  tools/box-why-clean.php removes them.
 *
 *  Nothing here is planted. The scores in the row are the ones
 *  vergeml_filing_pick() worked out on this box, from a description this file
 *  wrote and a folder profile it seeded; the only thing it decides is which
 *  pictures the matcher gets to look at.
 *
 *      scp tools/box-why-shot.php root@box:/tmp/vgml-why-shot.php
 *      bash tools/box-why-shot.sh
 *
 *  Then tools/box-why-clean.php, always. It is four attachments and two
 *  folders in somebody's real library until it runs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

if ( ! function_exists( 'vergeml_talk_refile_run' ) || ! function_exists( 'vergeml_librarian_why' ) ) {
    echo "the plugin is not loaded, or is running a build from before the trail\n";
    exit( 1 );
}

$tax = vergeml_librarian_taxonomy();

if ( '' === $tax || ! taxonomy_exists( $tax ) ) {
    echo "no folder taxonomy on this site\n";
    exit( 1 );
}

$vector = array( 1.0, 0.0, 0.0, 0.0 );
$class  = 'zzshotthing';
$terms  = array();

foreach ( array( 'zzShotA', 'zzShotC' ) as $name ) {
    $t = wp_insert_term( $name, $tax );
    if ( is_wp_error( $t ) ) {
        $e = get_term_by( 'name', $name, $tax );
        $terms[ $name ] = $e instanceof WP_Term ? (int) $e->term_id : 0;
    } else {
        $terms[ $name ] = (int) $t['term_id'];
    }
}

if ( ! $terms['zzShotA'] || ! $terms['zzShotC'] ) {
    echo "could not make the two folders\n";
    exit( 1 );
}

$profile = array(
    'version'  => VERGEML_FILING_VERSION,
    'source'   => 'plan',
    'plan'     => array(),
    'built_at' => time(),
    'vector'   => $vector,
    'kinds'    => array( 'photo' ),
);

update_term_meta( $terms['zzShotA'], VERGEML_FILING_META, array_merge( $profile, array(
    'path'     => array( 'zzShotA' ),
    'classes'  => array( $class ),
    'matches'  => '',
    'audience' => '',
) ) );

update_term_meta( $terms['zzShotC'], VERGEML_FILING_META, array_merge( $profile, array(
    'path'     => array( 'zzShotC' ),
    'classes'  => array(),
    'matches'  => $class,
    'audience' => 'women',
) ) );

function vgml_shot_file( $title, $object, $kind, $audience, $vector ) {

    $id = wp_insert_post( array(
        'post_title'     => 'zz shot ' . $title,
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/png',
    ) );

    vergeml_index_set( (int) $id, array(
        'caption'       => 'seeded for a screenshot',
        'kind'          => $kind,
        'filing'        => wp_json_encode( array( 'object' => $object, 'audience' => $audience ) ),
        'embedding'     => $vector,
        'model'         => 'claude-haiku-4-5',
        'model_version' => 'anthropic/claude-haiku-4.5',
        'prompt_hash'   => '6bb3630249008e4e7af22037d8ac7542',
        'error'         => '',
        'described_at'  => gmdate( 'Y-m-d H:i:s' ),
    ) );

    return (int) $id;
}

$files = array(
    'ok'     => vgml_shot_file( 'ok',     $class, 'photo',  '',      $vector ),
    'floor'  => vgml_shot_file( 'floor',  '',     'photo',  '',      $vector ),
    'gated'  => vgml_shot_file( 'gated',  $class, 'zzlogo', '',      $vector ),
    'margin' => vgml_shot_file( 'margin', $class, 'photo',  'women', $vector ),
);

/*
 *  The same guard the suite uses, and for the same reason: the pass reads
 *  every described picture above `after`, and on this box that is six hundred
 *  of somebody's real ones.
 */
$after = min( array_values( $files ) ) - 1;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$reach = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL AND attachment_id > %d",
    $after
) );

if ( 4 !== $reach ) {
    echo "refusing to run the pass: it would reach {$reach} pictures, not 4\n";
    exit( 1 );
}

$state_before = get_option( VERGEML_TALK_STATE );
$undo_before  = get_option( VERGEML_TALK_UNDO );

update_option( VERGEML_TALK_STATE, array(
    'active'   => true,
    'taxonomy' => $tax,
    'ids'      => array( 'a' => $terms['zzShotA'], 'c' => $terms['zzShotC'] ),
    'vectors'  => array(),
    'assign'   => array(),
    'fallback' => array(),
    'reasons'  => array(),
    'after'    => $after,
    'moved'    => 0,
    'skipped'  => 0,
    'seen'     => 0,
    'total'    => 4,
    'counts'   => array(),
    'by_term'  => array(),
    'unfiled'  => array(),
    'tags'     => array(),
    'tagged'   => 0,
    'until'    => time() + DAY_IN_SECONDS,
    'remove'   => array(),
    'started'  => time(),
), false );

$done = vergeml_talk_refile_run( microtime( true ) + 20.0 );

if ( false === $state_before ) {
    delete_option( VERGEML_TALK_STATE );
} else {
    update_option( VERGEML_TALK_STATE, $state_before, false );
}

if ( false === $undo_before ) {
    delete_option( VERGEML_TALK_UNDO );
} else {
    update_option( VERGEML_TALK_UNDO, $undo_before, false );
}

printf( "the pass looked at %d and filed %d\n\n", (int) $done['seen'], (int) $done['moved'] );

foreach ( $files as $why => $id ) {
    $read = vergeml_librarian_why( (int) $id );
    printf( "%-7s attachment %d\n", $why, (int) $id );
    foreach ( (array) ( $read ? $read['lines'] : array() ) as $line ) {
        echo '        ' . $line . "\n";
    }
}

echo "\nrun tools/box-why-clean.php when the screenshot is taken\n";
