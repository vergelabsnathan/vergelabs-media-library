<?php
/**
 *  Eight at a time, and copies described once.
 *
 *      wp eval-file tests/ai/parallel.php --allow-root
 *
 *  Two claims, and both were false before this suite existed.
 *
 *  The first is speed. The loop that filled a backlog was `foreach ( $ids as
 *  $id ) { describe( $id ); }` -- one request, wait for a model, next. Nearly
 *  all of that is waiting, so five hundred pictures took half an hour of a
 *  browser tab doing nothing.
 *
 *  The second is a promise already on the screen. Step one of the sort flow
 *  says checking for copies "stops the same photo being described twice, and
 *  paid for twice". Nothing in the describing path had ever read the hashes
 *  that scan computes, so the sentence was true of the scan and false of the
 *  product.
 *
 *  Runs in mock, so it costs nothing. What mock cannot show is the wall-clock
 *  win -- that needs real calls to a real model, and it is measured separately
 *  rather than asserted here on a stopwatch that would be measuring localhost.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_ai_describe_many' ) ) {
    echo "core/ai.php is not loaded, or predates the parallel path\n";
    exit( 1 );
}

$GLOBALS['pl_pass'] = 0;
$GLOBALS['pl_fail'] = 0;
$GLOBALS['pl_log']  = '';

function pl_say( $line ) {
    $GLOBALS['pl_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function pl_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['pl_pass']++;
    } else {
        $GLOBALS['pl_fail']++;
    }
    pl_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

/** A small real JPEG on disk, attached, so the whole path is exercised. */
function pl_make( $name, $hue ) {

    $uploads = wp_upload_dir();
    $path    = trailingslashit( $uploads['path'] ) . $name;

    $im = imagecreatetruecolor( 320, 240 );
    imagefilledrectangle( $im, 0, 0, 320, 240, imagecolorallocate( $im, (int) ( 200 * $hue ), 90, 140 ) );
    imagejpeg( $im, $path, 70 );
    imagedestroy( $im );

    $id = wp_insert_attachment( array(
        'post_mime_type' => 'image/jpeg',
        'post_title'     => 'Parallel suite ' . $name,
        'post_status'    => 'inherit',
    ), $path );

    wp_update_attachment_metadata( $id, array(
        'width'  => 320,
        'height' => 240,
        'file'   => _wp_relative_upload_path( $path ),
        'sizes'  => array(),
    ) );

    return (int) $id;
}

wp_set_current_user( 1 );

$pl_settings      = vergeml_ai_settings();
$pl_was_mock      = ! empty( $pl_settings['mock'] );
$pl_settings_save = get_option( 'vergeml_ai', array() );

// Nothing here spends anything.
$pl_on = $pl_settings_save;
$pl_on['mock'] = 1;
update_option( 'vergeml_ai', $pl_on );

pl_say( "\neight at a time, and copies described once\n\n" );


/* ------------------------------------------------------------- the group */

pl_say( "A  a group comes back whole\n" );

$pl_ids = array();

foreach ( range( 1, 5 ) as $pl_i ) {
    $pl_ids[] = pl_make( 'vgml-parallel-' . $pl_i . '-' . wp_generate_password( 6, false ) . '.jpg', $pl_i / 5 );
}

$pl_answers = vergeml_ai_describe_many( $pl_ids );

pl_check( 'one answer per file asked about', count( $pl_answers ) === count( $pl_ids ), count( $pl_answers ) . ' for ' . count( $pl_ids ) );

$pl_keyed = true;
$pl_good  = 0;

foreach ( $pl_ids as $pl_id ) {

    if ( ! array_key_exists( $pl_id, $pl_answers ) ) {
        $pl_keyed = false;
        continue;
    }

    if ( ! is_wp_error( $pl_answers[ $pl_id ] ) && ! empty( $pl_answers[ $pl_id ]['caption'] ) ) {
        $pl_good++;
    }
}

/*
 *  Keyed by attachment id, not by position. request_multiple() preserves the
 *  keys it was given and the whole design rests on that -- answers matched to
 *  the wrong file would write one picture's caption onto another and nothing
 *  downstream could ever notice.
 */
pl_check( 'every answer is filed under the id it belongs to', $pl_keyed );
pl_check( 'all of them described', 5 === $pl_good, $pl_good . ' of 5' );

$pl_empty = vergeml_ai_describe_many( array() );
pl_check( 'asking about nothing answers nothing', array() === $pl_empty );

/*
 *  A file that cannot be read must come back as its own error rather than
 *  taking the group down with it.
 */
$pl_ghost = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'no file', 'post_mime_type' => 'image/jpeg' ) );
$pl_mixed = vergeml_ai_describe_many( array( $pl_ids[0], $pl_ghost ) );

pl_check(
    'one unreadable file does not spoil the group',
    isset( $pl_mixed[ $pl_ids[0] ] ) && ! is_wp_error( $pl_mixed[ $pl_ids[0] ] ) && isset( $pl_mixed[ $pl_ghost ] ),
    'the good one still came back'
);

wp_delete_post( $pl_ghost, true );


/* -------------------------------------------------------------- the twins */

pl_say( "\nB  the same picture is described once\n" );

if ( ! function_exists( 'vergeml_ai_twin' ) || ! defined( 'VERGEML_META_HASH' ) ) {

    pl_check( 'the twin lookup exists', false, 'core/health.php not loaded?' );

} else {

    /*
     *  Two files, one hash: a re-upload of the same image, which is what the
     *  health scan detects and what this is for.
     *
     *  The first has to be described INTO THE INDEX, not merely described.
     *  describe_many() returns answers; storing them is the step's job. Without
     *  this the twin lookup correctly found nothing, because as far as the
     *  database was concerned nothing had ever been described.
     */
    $pl_a = $pl_ids[0];

    $pl_first = vergeml_ai_describe( $pl_a );

    if ( ! is_wp_error( $pl_first ) ) {
        vergeml_index_writing( true );
        vergeml_index_set( $pl_a, array(
            'caption'      => $pl_first['caption'],
            'alt'          => $pl_first['alt'],
            'tags'         => $pl_first['tags'],
            'title'        => $pl_first['title'],
            'kind'         => $pl_first['kind'],
            'model'        => $pl_first['model'],
            'prompt_hash'  => $pl_first['prompt_hash'],
            'error'        => '',
            'described_at' => current_time( 'mysql', true ),
        ) );
        vergeml_index_writing( false );
    }

    pl_check( 'the first of the pair is in the index', null !== vergeml_index_get( $pl_a ) );
    $pl_b = pl_make( 'vgml-parallel-twin-' . wp_generate_password( 6, false ) . '.jpg', 0.5 );

    $pl_hash = 'ffee00112233aabb';

    update_post_meta( $pl_a, VERGEML_META_HASH, $pl_hash );
    update_post_meta( $pl_b, VERGEML_META_HASH, $pl_hash );

    pl_check( 'a described file is found as the twin of its copy', $pl_a === vergeml_ai_twin( $pl_b ), 'found ' . vergeml_ai_twin( $pl_b ) );

    /*
     *  And not the other way round while only one of them is described --
     *  otherwise two undescribed copies would each wait for the other.
     */
    vergeml_index_delete( $pl_b );
    pl_check( 'an undescribed copy is not offered as a twin', 0 === vergeml_ai_twin( $pl_a ), 'found ' . vergeml_ai_twin( $pl_a ) );

    // The fill itself.
    $pl_source = vergeml_index_get( $pl_a );

    pl_check( 'filling a copy from its twin reports success', true === vergeml_ai_fill_from_twin( $pl_b, $pl_a, true ) );

    $pl_filled = vergeml_index_get( $pl_b );

    pl_check( 'the copy now has the same caption', $pl_filled && $pl_filled['caption'] === $pl_source['caption'], $pl_filled ? $pl_filled['caption'] : '(nothing)' );
    pl_check( 'and the same tags', $pl_filled && $pl_filled['tags'] === $pl_source['tags'] );

    /*
     *  prompt_hash comes across too. A file filled from a twin has to go stale
     *  at the same moment as the one it came from; without this it would look
     *  freshly described by a prompt that never saw it.
     */
    pl_check( 'and the stamp of whatever actually described it', $pl_filled && $pl_filled['prompt_hash'] === $pl_source['prompt_hash'] );
    pl_check( 'and alt text on the file itself', '' !== (string) get_post_meta( $pl_b, '_wp_attachment_image_alt', true ) );

    /*
     *  The point of all of it: the step must not ask the service about a file
     *  it can fill for nothing.
     */
    vergeml_index_delete( $pl_b );
    delete_post_meta( $pl_b, '_wp_attachment_image_alt' );

    $pl_before = count( vergeml_ai_pending( 'unindexed' ) );
    $pl_step   = vergeml_ai_index_step( 'unindexed', 24, true );

    $pl_twinned = 0;

    foreach ( $pl_step['described'] as $pl_row ) {
        if ( ! empty( $pl_row['twin'] ) ) {
            $pl_twinned++;
        }
    }

    pl_check(
        'a step fills copies from their twin instead of paying for them',
        $pl_twinned > 0,
        $pl_twinned . ' of ' . count( $pl_step['described'] ) . ' in this step cost nothing'
    );

    wp_delete_attachment( $pl_b, true );
}


/* ------------------------------------------------------------ the ceiling */

pl_say( "\nC  how many go out together\n" );

pl_check( 'eight by default', 8 === vergeml_ai_parallel(), (string) vergeml_ai_parallel() );

add_filter( 'vergeml_ai_parallel', function () { return 200; } );
pl_check( 'and a site cannot ask for two hundred', 16 === vergeml_ai_parallel(), (string) vergeml_ai_parallel() );
remove_all_filters( 'vergeml_ai_parallel' );

add_filter( 'vergeml_ai_parallel', function () { return 0; } );
pl_check( 'nor for none', 1 === vergeml_ai_parallel(), (string) vergeml_ai_parallel() );
remove_all_filters( 'vergeml_ai_parallel' );


/* -------------------------------------------------------------- tidy up */

foreach ( $pl_ids as $pl_id ) {
    wp_delete_attachment( $pl_id, true );
}

update_option( 'vergeml_ai', $pl_settings_save );

pl_say( sprintf( "\n%d/%d passed\n", $GLOBALS['pl_pass'], $GLOBALS['pl_pass'] + $GLOBALS['pl_fail'] ) );

@file_put_contents( __DIR__ . '/parallel-last-run.txt', $GLOBALS['pl_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['pl_fail'] > 0 ) {
    exit( 1 );
}
