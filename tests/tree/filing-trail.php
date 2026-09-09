<?php
/**
 *  The reason a picture moved, and whether it survives the move.
 *
 *  vergeml_filing_pick() has always worked out four things about a picture --
 *  the folder, the score, the folder it beat, and a word for why -- and the
 *  plugin used to act on the first and drop the rest. So the library could say
 *  "1779 is in Architecture" and nothing could say "because it scored 0.81
 *  against 0.44". This is the suite for the columns that keep it.
 *
 *  Four outcomes, four fixtures, and the fixture is built so that each one is
 *  the only answer available:
 *
 *    ok      one folder takes the picture's class outright
 *    floor   the picture has no class at all, so nothing clears 0.55
 *    margin  a second folder describes the same class and lands within 0.08
 *    gated   the picture is a kind neither folder accepts
 *
 *  Every class comparison in it is an exact match or a substring, which are
 *  the two answers vergeml_filing_class_match() gives without asking the
 *  service. Nothing here spends a credit, and a fixture that drifted into
 *  asking would show up as a suite that got slower and a bill that did not
 *  match the run.
 *
 *  It then makes the mutation check possible: each fixture's word is named
 *  here, so a matcher that returns a why disagreeing with what it decided --
 *  'ok' on a picture it refused, 'floor' on one it filed -- fails on the
 *  fixture whose answer it changed rather than passing an equality against
 *  its own wrong answer.
 *
 *      wp eval-file tests/tree/filing-trail.php --allow-root
 *
 *  or through the runner: node tools/verify.mjs filing-trail
 *
 *  The box, not Playground: this is about dbDelta, a FLOAT column that has to
 *  stay null, and an ALTER that puts six columns back on a table that already
 *  has rows. SQLite would answer a different question.
 *
 *  What it touches, and puts back: two folders and four attachments of its
 *  own, the moves rows it wrote, the batches it created, and the two options
 *  the re-filing pass keeps its state in -- snapshotted before the pass and
 *  restored after it, because a suite that leaves a Move looking like it is
 *  running is a suite that gets somebody's library re-filed.
 *
 *  The upgrade check drops the six new columns from the moves table and lets
 *  the lazy install put them back. tests/librarian/gate7-schema.php drops both
 *  tables whole for the same reason; this is the milder version of that, and
 *  it costs the reason on any row written before it ran.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_librarian_move_reason' ) ) {
    echo "core/librarian.php has no vergeml_librarian_move_reason() -- this box is running a build from before the trail shipped\n";
    exit( 1 );
}

if ( ! function_exists( 'vergeml_talk_refile_run' ) || ! function_exists( 'vergeml_filing_pick' ) ) {
    echo "core/folder-talk.php or core/filing.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

global $wpdb;

$GLOBALS['ft_pass'] = 0;
$GLOBALS['ft_fail'] = 0;
$GLOBALS['ft_log']  = '';

/*
 *  $GLOBALS, not `global`. wp eval-file evaluates this file inside a function,
 *  so anything declared at the top of it is a local of that function and never
 *  a global at all -- `global` in the helpers below would bind to a second,
 *  empty pair, the counters would stay at zero however many checks ran, and
 *  the exit(1) at the end could never fire. The suite would report success
 *  whatever failed. tests/tree/auto-file.php and tests/librarian carry the
 *  same note for the same reason.
 */

function ft_say( $line ) {
    $GLOBALS['ft_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}


function ft_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['ft_pass']++;
    } else {
        $GLOBALS['ft_fail']++;
    }
    ft_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}


function ft_report() {
    @file_put_contents( __DIR__ . '/filing-trail-last-run.txt', $GLOBALS['ft_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
}


/** A FLOAT column is single precision, so equality is to five decimals, not to the bit. */
function ft_near( $a, $b ) {
    return abs( (float) $a - (float) $b ) < 0.00001;
}


$ft_tax = vergeml_librarian_taxonomy();

if ( '' === $ft_tax || ! taxonomy_exists( $ft_tax ) ) {
    echo "no folder taxonomy on this site\n";
    exit( 1 );
}

$ft_moves   = $wpdb->vergeml_librarian_moves;
$ft_batches = $wpdb->vergeml_librarian_batches;

ft_say( "\nthe reason a picture moved\n\n" );


/* ------------------------------------------------------------- the fixture */

ft_say( "the fixture\n" );

// Identical vectors everywhere, so the vector contributes the same 0.25 to
// every score and the class match is the only thing that moves a number.
$ft_vector = array( 1.0, 0.0, 0.0, 0.0 );
$ft_class  = 'zztrailthing';

$ft_terms = array();

foreach ( array( 'zzTrailA', 'zzTrailC' ) as $ft_name ) {

    $ft_term = wp_insert_term( $ft_name, $ft_tax );

    if ( is_wp_error( $ft_term ) ) {
        $ft_existing = get_term_by( 'name', $ft_name, $ft_tax );
        $ft_terms[ $ft_name ] = $ft_existing instanceof WP_Term ? (int) $ft_existing->term_id : 0;
    } else {
        $ft_terms[ $ft_name ] = (int) $ft_term['term_id'];
    }
}

ft_check( 'two folders of its own', $ft_terms['zzTrailA'] > 0 && $ft_terms['zzTrailC'] > 0 );

if ( ! $ft_terms['zzTrailA'] || ! $ft_terms['zzTrailC'] ) {
    ft_say( "\n0/1 passed\n" );
    ft_report();
    exit( 1 );
}

/*
 *  Profiles written straight onto the terms rather than built.
 *
 *  vergeml_filing_profile_build() asks the service for a folder's vector, and
 *  this suite is not allowed to spend anything. Seeded at the current version
 *  with a vector already in them, vergeml_filing_profile() hands them back
 *  untouched -- which is also the shape a real plan writes.
 *
 *  A takes the class outright. C only describes it, which is worth 0.9 of a
 *  match rather than 1.0, and lands 0.075 below A: inside the 0.08 margin,
 *  which is what makes the third fixture too close to call. C is also for
 *  women, so it is gated out of every picture that does not say so, and the
 *  first two fixtures are decided by A alone.
 */
$ft_profile = array(
    'version'  => VERGEML_FILING_VERSION,
    'source'   => 'plan',
    'plan'     => array(),
    'built_at' => time(),
    'vector'   => $ft_vector,
    'kinds'    => array( 'photo' ),
);

update_term_meta( $ft_terms['zzTrailA'], VERGEML_FILING_META, array_merge( $ft_profile, array(
    'path'     => array( 'zzTrailA' ),
    'classes'  => array( $ft_class ),
    'matches'  => '',
    'audience' => '',
) ) );

update_term_meta( $ft_terms['zzTrailC'], VERGEML_FILING_META, array_merge( $ft_profile, array(
    'path'     => array( 'zzTrailC' ),
    'classes'  => array(),
    'matches'  => $ft_class,
    'audience' => 'women',
) ) );

$GLOBALS['ft_posts'] = array();

/**
 *  One described picture. $object is what the describer filed it as, and an
 *  empty one is a picture with no class -- which is the honest way to reach
 *  the floor without asking the service what two unrelated words have in
 *  common.
 */
function ft_file( $title, $object, $kind, $audience ) {

    $id = wp_insert_post( array(
        'post_title'     => 'zz trail ' . $title,
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/png',
    ) );

    $GLOBALS['ft_posts'][] = (int) $id;

    vergeml_index_set( (int) $id, array(
        'caption'        => 'seeded',
        'kind'           => $kind,
        'filing'         => wp_json_encode( array( 'object' => $object, 'audience' => $audience ) ),
        // The setter packs it and works out the dimensions; handing it the
        // packed bytes instead stores nothing, and a row with no embedding is
        // a row the re-filing pass cannot see.
        'embedding'      => $GLOBALS['ft_vector_shared'],
        'model'          => 'zz-test',
        'model_version'  => 'zz-model-7',
        'prompt_hash'    => 'zzhash0123456789',
        'error'          => '',
        'described_at'   => gmdate( 'Y-m-d H:i:s' ),
    ) );

    return (int) $id;
}

$GLOBALS['ft_vector_shared'] = $ft_vector;

$ft_files = array(
    'ok'     => ft_file( 'ok',     $ft_class, 'photo',   '' ),
    'floor'  => ft_file( 'floor',  '',        'photo',   '' ),
    'gated'  => ft_file( 'gated',  $ft_class, 'zzlogo',  '' ),
    'margin' => ft_file( 'margin', $ft_class, 'photo',   'women' ),
);

ft_check( 'four described pictures, one per outcome', 4 === count( array_filter( $ft_files ) ) );


/* -------------------------------------------------- what the matcher says */

ft_say( "\nwhat the matcher says\n" );

$ft_profiles = vergeml_filing_profiles( array_values( $ft_terms ), $ft_tax );

$ft_picks = array();

foreach ( $ft_files as $ft_why => $ft_id ) {

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
    $ft_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT attachment_id, embedding, kind, filing, prompt_hash, model_version FROM {$wpdb->vergeml_ai_index} WHERE attachment_id = %d",
        (int) $ft_id
    ), ARRAY_A );

    $ft_picks[ $ft_why ] = vergeml_filing_pick( vergeml_filing_facts( $ft_row ), $ft_profiles );

    ft_check(
        sprintf( 'the %s picture comes back %s', $ft_why, $ft_why ),
        $ft_why === $ft_picks[ $ft_why ]['why'],
        sprintf( 'said %s, scored %.4f', $ft_picks[ $ft_why ]['why'], $ft_picks[ $ft_why ]['score'] )
    );
}

ft_check(
    'the one it placed is the folder that takes the class',
    (int) $ft_picks['ok']['term_id'] === (int) $ft_terms['zzTrailA'],
    'term ' . (int) $ft_picks['ok']['term_id']
);

ft_check(
    'the other three are placed nowhere',
    0 === (int) $ft_picks['floor']['term_id']
        && 0 === (int) $ft_picks['gated']['term_id']
        && 0 === (int) $ft_picks['margin']['term_id']
);

ft_check(
    'the one too close to call names the folder it could not beat',
    (int) $ft_picks['margin']['runner_up'] === (int) $ft_terms['zzTrailC'],
    sprintf( '%.4f against %.4f', $ft_picks['margin']['score'], $ft_picks['margin']['runner_score'] )
);


/* --------------------------------------------------- the pass writes it down */

ft_say( "\nthe pass writes it down\n" );

$ft_state_before = get_option( VERGEML_TALK_STATE );
$ft_undo_before  = get_option( VERGEML_TALK_UNDO );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_batch_high = (int) $wpdb->get_var( "SELECT COALESCE( MAX( batch_id ), 0 ) FROM {$ft_batches}" );

$ft_after = min( array_values( $ft_files ) ) - 1;

/*
 *  The pass reads every described picture above `after`, so the guard is not
 *  optional: on this box that is a library of several hundred, and a run that
 *  reached them would re-file somebody's real folders to prove a point about
 *  a log. It must see this suite's four and nothing else, and if it would see
 *  anything else the suite stops here rather than running.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_reach = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL AND attachment_id > %d",
    $ft_after
) );

ft_check( 'the pass can only reach this suite\'s own pictures', 4 === $ft_reach, $ft_reach . ' in range' );

if ( 4 !== $ft_reach ) {
    ft_say( "\nrefusing to run the pass over pictures this suite did not make\n" );
    ft_say( sprintf( "\n%d/%d passed\n", $GLOBALS['ft_pass'], $GLOBALS['ft_pass'] + $GLOBALS['ft_fail'] ) );
    ft_report();
    exit( 1 );
}

update_option( VERGEML_TALK_STATE, array(
    'active'   => true,
    'taxonomy' => $ft_tax,
    'ids'      => array( 'a' => $ft_terms['zzTrailA'], 'c' => $ft_terms['zzTrailC'] ),
    'vectors'  => array(),
    'assign'   => array(),
    'fallback' => array(),
    'reasons'  => array(),
    'after'    => $ft_after,
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

$ft_done = vergeml_talk_refile_run( microtime( true ) + 20.0 );

ft_check( 'the pass looked at four pictures and no others', 4 === (int) $ft_done['seen'], (int) $ft_done['seen'] . ' seen' );
ft_check( 'it filed the one that fits and left three', 1 === (int) $ft_done['moved'] && 3 === (int) $ft_done['skipped'] );

// Put the screen's own state back before anything else can read it.
if ( false === $ft_state_before ) {
    delete_option( VERGEML_TALK_STATE );
} else {
    update_option( VERGEML_TALK_STATE, $ft_state_before, false );
}

if ( false === $ft_undo_before ) {
    delete_option( VERGEML_TALK_UNDO );
} else {
    update_option( VERGEML_TALK_UNDO, $ft_undo_before, false );
}

$ft_in  = implode( ',', array_map( 'intval', array_values( $ft_files ) ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are cast to int above; this plugin's own table.
$ft_written = (array) $wpdb->get_results( "SELECT * FROM {$ft_moves} WHERE attachment_id IN ($ft_in)", ARRAY_A );

$ft_rows = array();

foreach ( $ft_written as $ft_r ) {
    $ft_rows[ (int) $ft_r['attachment_id'] ] = $ft_r;
}

ft_check( 'every picture the pass looked at has a row', 4 === count( $ft_rows ), count( $ft_rows ) . ' rows' );

foreach ( $ft_files as $ft_why => $ft_id ) {

    $ft_row  = isset( $ft_rows[ $ft_id ] ) ? $ft_rows[ $ft_id ] : null;
    $ft_pick = $ft_picks[ $ft_why ];

    if ( ! $ft_row ) {
        ft_check( sprintf( 'the %s picture has a row', $ft_why ), false );
        continue;
    }

    ft_check(
        sprintf( 'the %s row says %s', $ft_why, $ft_why ),
        $ft_why === (string) $ft_row['why'],
        'said ' . (string) $ft_row['why']
    );

    ft_check(
        sprintf( 'the %s row carries the score the matcher gave it', $ft_why ),
        ft_near( $ft_row['score'], $ft_pick['score'] ),
        sprintf( 'row %.6f, pick %.6f', (float) $ft_row['score'], (float) $ft_pick['score'] )
    );

    ft_check(
        sprintf( 'the %s row names the folder it beat, and that folder\'s score', $ft_why ),
        (int) $ft_row['runner_up'] === (int) $ft_pick['runner_up'] && ft_near( $ft_row['runner_score'], $ft_pick['runner_score'] ),
        sprintf( 'row %d/%.6f, pick %d/%.6f', (int) $ft_row['runner_up'], (float) $ft_row['runner_score'], (int) $ft_pick['runner_up'], (float) $ft_pick['runner_score'] )
    );

    ft_check(
        sprintf( 'the %s row points back at the description it rests on', $ft_why ),
        'zzhash0123456789' === (string) $ft_row['prompt_hash'] && 'zz-model-7' === (string) $ft_row['model_version'],
        sprintf( '%s / %s', (string) $ft_row['prompt_hash'], (string) $ft_row['model_version'] )
    );

    ft_check(
        sprintf( 'the %s row\'s folder is the one the matcher chose', $ft_why ),
        (int) $ft_row['term_id'] === (int) $ft_pick['term_id'],
        sprintf( 'row %d, pick %d', (int) $ft_row['term_id'], (int) $ft_pick['term_id'] )
    );
}

/*
 *  The abstention is the row that did not exist before any of this, and it is
 *  the one the negative question is asked of: a picture the run looked at and
 *  would not place.
 */
$ft_abstained = 0;

foreach ( $ft_rows as $ft_r ) {
    if ( 0 === (int) $ft_r['term_id'] && '' !== (string) $ft_r['why'] ) {
        $ft_abstained++;
    }
}

ft_check( 'three of them are abstentions, written as rows with no folder', 3 === $ft_abstained, $ft_abstained . ' found' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
$ft_negative = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ft_moves} WHERE term_id = 0 AND why <> ''" );

ft_check( 'the negative question has an answer to return', $ft_negative >= 3, $ft_negative . ' pictures the evidence would not place' );


/* -------------------------------------------------------- a person decides */

ft_say( "\na person decides\n" );

$ft_hand_id = ft_file( 'hand', $ft_class, 'photo', '' );

$ft_hand = vergeml_nl_run( array(
    'verb'    => 'move',
    'term_id' => (int) $ft_terms['zzTrailA'],
    'ids'     => array( (int) $ft_hand_id ),
) );

ft_check( 'a spoken move files the picture', is_array( $ft_hand ) && 1 === (int) $ft_hand['done'] );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_hand_row = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$ft_moves} WHERE attachment_id = %d",
    (int) $ft_hand_id
), ARRAY_A );

ft_check( 'it wrote a row', is_array( $ft_hand_row ) );

if ( is_array( $ft_hand_row ) ) {

    ft_check( 'the row says a person decided', 'by hand' === (string) $ft_hand_row['why'], 'said ' . (string) $ft_hand_row['why'] );

    /*
     *  Null, not zero. A zero here would say the matcher looked at this
     *  picture and found nothing to like about the folder, which is a
     *  measurement nobody took: a person named the folder.
     */
    ft_check(
        'and leaves the scores empty rather than saying zero',
        null === $ft_hand_row['score'] && null === $ft_hand_row['runner_score'],
        sprintf( 'score %s, runner %s',
            null === $ft_hand_row['score'] ? 'null' : (string) $ft_hand_row['score'],
            null === $ft_hand_row['runner_score'] ? 'null' : (string) $ft_hand_row['runner_score'] )
    );
}


/* ------------------------------------------------ why is this picture here */

/*
 *  The read, against the write above.
 *
 *  Everything so far proves the reason reaches the table. This proves a person
 *  can get it back out: vergeml_librarian_why() over the same four pictures,
 *  answering with the values vergeml_filing_pick() returned for each of them
 *  and no others. It reconstructs nothing -- if the reader ever computed a
 *  score of its own, these numbers would drift from $ft_picks and this section
 *  is where that shows.
 */

ft_say( "\nwhy is this picture here\n" );

if ( ! function_exists( 'vergeml_librarian_why' ) ) {

    ft_check( 'core/librarian.php can read a placement back', false, 'no vergeml_librarian_why()' );

} else {

    foreach ( $ft_files as $ft_why => $ft_id ) {

        $ft_read = vergeml_librarian_why( (int) $ft_id );
        $ft_pick = $ft_picks[ $ft_why ];

        if ( ! is_array( $ft_read ) ) {
            ft_check( sprintf( 'the %s picture answers', $ft_why ), false, 'nothing came back' );
            continue;
        }

        ft_check(
            sprintf( 'the %s picture answers with the matcher\'s own word', $ft_why ),
            $ft_why === (string) $ft_read['why'],
            'said ' . (string) $ft_read['why']
        );

        ft_check(
            sprintf( 'the %s picture answers with the score the matcher gave it', $ft_why ),
            ft_near( $ft_read['score'], $ft_pick['score'] ),
            sprintf( 'read %.6f, pick %.6f', (float) $ft_read['score'], (float) $ft_pick['score'] )
        );

        ft_check(
            sprintf( 'the %s picture names the folder it could not beat, and that folder\'s score', $ft_why ),
            (int) $ft_read['runner_up'] === (int) $ft_pick['runner_up'] && ft_near( $ft_read['runner_score'], $ft_pick['runner_score'] ),
            sprintf( 'read %d/%.6f, pick %d/%.6f', (int) $ft_read['runner_up'], (float) $ft_read['runner_score'], (int) $ft_pick['runner_up'], (float) $ft_pick['runner_score'] )
        );

        ft_check(
            sprintf( 'the %s picture names the description it rests on', $ft_why ),
            'zz-model-7' === (string) $ft_read['model_version'] && 'zzhash0123456789' === (string) $ft_read['prompt_hash'],
            sprintf( '%s / %s', (string) $ft_read['model_version'], (string) $ft_read['prompt_hash'] )
        );

        // The model and the first of the hash, in a line a person reads.
        ft_check(
            sprintf( 'the %s picture says the model and the prompt in a line', $ft_why ),
            in_array( 'Described by zz-model-7 · prompt zzhash01', $ft_read['lines'], true ),
            implode( ' / ', $ft_read['lines'] )
        );
    }

    /*
     *  The one it placed says where and why, with both scores and the gap
     *  between them; the three it refused say they were left alone, and none of
     *  them claims a folder or a filing.
     */
    $ft_ok = vergeml_librarian_why( (int) $ft_files['ok'] );

    ft_check(
        'the one it filed says the folder it went to',
        is_array( $ft_ok ) && (int) $ft_ok['term_id'] === (int) $ft_terms['zzTrailA'] && 'zzTrailA' === (string) $ft_ok['term'],
        is_array( $ft_ok ) ? sprintf( 'term %d, %s', (int) $ft_ok['term_id'], (string) $ft_ok['term'] ) : 'nothing'
    );

    ft_check(
        'in a line that carries the folder and the score',
        is_array( $ft_ok ) && isset( $ft_ok['lines'][0] )
            && 0 === strpos( $ft_ok['lines'][0], 'In zzTrailA · scored ' ),
        is_array( $ft_ok ) && isset( $ft_ok['lines'][0] ) ? $ft_ok['lines'][0] : 'no line'
    );

    ft_check(
        'and a line for the batch a person approved',
        is_array( $ft_ok ) && (int) $ft_ok['batch_id'] > 0
            && (bool) preg_grep( '/^Filed /', $ft_ok['lines'] ),
        is_array( $ft_ok ) ? 'batch ' . (int) $ft_ok['batch_id'] : 'nothing'
    );

    foreach ( array( 'floor', 'margin', 'gated' ) as $ft_left ) {

        $ft_read = vergeml_librarian_why( (int) $ft_files[ $ft_left ] );

        ft_check(
            sprintf( 'the %s picture reads as one that was left where it was', $ft_left ),
            is_array( $ft_read ) && isset( $ft_read['lines'][0] )
                && 0 === strpos( $ft_read['lines'][0], 'Left where it was · ' ),
            is_array( $ft_read ) && isset( $ft_read['lines'][0] ) ? $ft_read['lines'][0] : 'no line'
        );

        ft_check(
            sprintf( 'the %s picture claims no folder and no filing', $ft_left ),
            is_array( $ft_read ) && 0 === (int) $ft_read['term_id'] && '' === (string) $ft_read['term']
                && ! preg_grep( '/^Filed /', $ft_read['lines'] ),
            is_array( $ft_read ) ? implode( ' / ', $ft_read['lines'] ) : 'nothing'
        );
    }

    // The floor's line says the number it missed and the number it had to clear.
    $ft_floor_read = vergeml_librarian_why( (int) $ft_files['floor'] );

    ft_check(
        'the floor is named as the thing the score did not clear',
        is_array( $ft_floor_read ) && false !== strpos( $ft_floor_read['lines'][0], 'below the floor of ' . number_format_i18n( VERGEML_FILING_FLOOR, 2 ) ),
        is_array( $ft_floor_read ) ? $ft_floor_read['lines'][0] : 'nothing'
    );

    // A person's move says a person made it, and offers no score at all.
    $ft_hand_read = vergeml_librarian_why( (int) $ft_hand_id );

    ft_check(
        'a picture a person moved says so, and gives no score',
        is_array( $ft_hand_read ) && array( 'Put here by hand · nothing scored it' ) === array_slice( $ft_hand_read['lines'], 0, 1 )
            && null === $ft_hand_read['score'],
        is_array( $ft_hand_read ) ? implode( ' / ', $ft_hand_read['lines'] ) : 'nothing'
    );

    /*
     *  And the row that has no reason on it at all -- the shape every move
     *  written before the trail shipped has. It must read as that and not as a
     *  blank: a picture whose record says nothing is a different statement from
     *  a picture nobody has a record of.
     */
    $ft_blank_id = ft_file( 'blank', $ft_class, 'photo', '' );

    wp_set_object_terms( (int) $ft_blank_id, array( (int) $ft_terms['zzTrailA'] ), $ft_tax, false );

    $wpdb->insert(
        $ft_moves,
        array(
            'batch_id'      => 0,
            'attachment_id' => (int) $ft_blank_id,
            'term_id'       => (int) $ft_terms['zzTrailA'],
            'term_created'  => 0,
            'undone'        => 0,
            'why'           => '',
            'runner_up'     => 0,
            'prompt_hash'   => '',
            'model_version' => '',
        ),
        array( '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
    );

    $ft_blank_read = vergeml_librarian_why( (int) $ft_blank_id );

    ft_check(
        'a move made before any of this was recorded reads as exactly that',
        is_array( $ft_blank_read ) && array( 'Moved before the reason was recorded' ) === $ft_blank_read['lines'],
        is_array( $ft_blank_read ) ? implode( ' / ', $ft_blank_read['lines'] ) : 'nothing'
    );

    ft_check(
        'and invents no score to fill the gap',
        is_array( $ft_blank_read ) && null === $ft_blank_read['score'] && null === $ft_blank_read['runner_score'],
        is_array( $ft_blank_read ) ? sprintf( 'score %s', var_export( $ft_blank_read['score'], true ) ) : 'nothing'
    );

    // A picture the record has never heard of says nothing rather than something empty.
    $ft_unknown_id = ft_file( 'unknown', $ft_class, 'photo', '' );

    ft_check(
        'a picture with no row at all answers with nothing',
        null === vergeml_librarian_why( (int) $ft_unknown_id )
    );
}


/* ------------------------------------------------------ an older site upgrades */

ft_say( "\nan older site upgrades\n" );

$ft_new_columns = array( 'why', 'score', 'runner_up', 'runner_score', 'prompt_hash', 'model_version' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
$ft_have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$ft_moves}" );

$ft_drop = array();

foreach ( $ft_new_columns as $ft_col ) {
    if ( in_array( $ft_col, $ft_have, true ) ) {
        $ft_drop[] = 'DROP COLUMN ' . $ft_col;
    }
}

if ( $ft_drop ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column names are this file's own literals.
    $wpdb->query( "ALTER TABLE {$ft_moves} " . implode( ', ', $ft_drop ) );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
$ft_have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$ft_moves}" );

ft_check( 'the table is back to the shape it had before the trail', ! in_array( 'why', $ft_have, true ) );

// A move from that older site: four values, which is all a caller had.
$ft_legacy_id = $ft_files['ok'];

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->insert( $ft_moves, array(
    'batch_id'      => 999999,
    'attachment_id' => (int) $ft_legacy_id,
    'term_id'       => (int) $ft_terms['zzTrailA'],
    'term_created'  => 1,
    'undone'        => 0,
), array( '%d', '%d', '%d', '%d', '%d' ) );

$ft_legacy_row = (int) $wpdb->insert_id;

$ft_option = get_option( VERGEML_LIBRARIAN_OPTION, array() );
$ft_option = is_array( $ft_option ) ? $ft_option : array();
$ft_option['schema'] = 1;
update_option( VERGEML_LIBRARIAN_OPTION, $ft_option, false );

vergeml_librarian_maybe_install();

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
$ft_have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$ft_moves}" );

$ft_missing = array_values( array_diff( $ft_new_columns, $ft_have ) );

ft_check( 'the upgrade puts all six columns back', ! $ft_missing, $ft_missing ? implode( ', ', $ft_missing ) . ' still missing' : '' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_kept = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ft_moves} WHERE move_id = %d", $ft_legacy_row ), ARRAY_A );

ft_check(
    'the move that was already there survived it',
    is_array( $ft_kept )
        && (int) $ft_kept['attachment_id'] === (int) $ft_legacy_id
        && (int) $ft_kept['term_id'] === (int) $ft_terms['zzTrailA']
        && 1 === (int) $ft_kept['term_created']
);

/*
 *  Nothing is backfilled, and this is the check that keeps it that way. An
 *  empty word means the move happened before any of this shipped; a word
 *  invented for it afterwards would be a reason nobody recorded, in a column
 *  whose whole argument is that it was recorded as a byproduct of the work.
 */
ft_check(
    'and its reason is empty, not invented',
    is_array( $ft_kept ) && '' === (string) $ft_kept['why'] && null === $ft_kept['score'],
    is_array( $ft_kept ) ? 'why "' . (string) $ft_kept['why'] . '"' : 'no row'
);

// The old four-element row, which a plan already in flight is still handing over.
vergeml_librarian_moves_insert( array( array( 999999, (int) $ft_legacy_id, (int) $ft_terms['zzTrailC'], 0 ) ) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_four = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$ft_moves} WHERE batch_id = 999999 AND term_id = %d",
    (int) $ft_terms['zzTrailC']
), ARRAY_A );

ft_check(
    'a four-element row still writes, and says nothing rather than failing',
    is_array( $ft_four ) && '' === (string) $ft_four['why'] && null === $ft_four['score']
);


/* ---------------------------------------------------------------- teardown */

ft_say( "\nputting it back\n" );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete( $ft_moves, array( 'batch_id' => 999999 ), array( '%d' ) );

foreach ( array_unique( $GLOBALS['ft_posts'] ) as $ft_id ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( $ft_moves, array( 'attachment_id' => (int) $ft_id ), array( '%d' ) );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( $wpdb->vergeml_ai_index, array( 'attachment_id' => (int) $ft_id ), array( '%d' ) );
    wp_delete_post( (int) $ft_id, true );
}

foreach ( $ft_terms as $ft_term_id ) {
    if ( $ft_term_id ) {
        wp_delete_term( (int) $ft_term_id, $ft_tax );
    }
}

// The batches this run caused, and only those: anything already here is
// somebody else's record and is not this suite's to delete.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( $wpdb->prepare( "DELETE FROM {$ft_batches} WHERE batch_id > %d", $ft_batch_high ) );

$ft_left = 0;

foreach ( array_unique( $GLOBALS['ft_posts'] ) as $ft_id ) {
    if ( get_post( (int) $ft_id ) ) {
        $ft_left++;
    }
}

ft_check( 'the pictures it made are gone', 0 === $ft_left, $ft_left . ' left behind' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are cast to int above.
$ft_rows_left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ft_moves} WHERE attachment_id IN ($ft_in)" );

ft_check( 'the rows it wrote are gone', 0 === $ft_rows_left, $ft_rows_left . ' left behind' );

ft_check(
    'the re-filing state is as it found it',
    ( false === $ft_state_before ? false === get_option( VERGEML_TALK_STATE ) : get_option( VERGEML_TALK_STATE ) === $ft_state_before )
);

ft_say( sprintf( "\n%d/%d passed\n", $GLOBALS['ft_pass'], $GLOBALS['ft_pass'] + $GLOBALS['ft_fail'] ) );

ft_report();

if ( $GLOBALS['ft_fail'] > 0 ) {
    exit( 1 );
}
