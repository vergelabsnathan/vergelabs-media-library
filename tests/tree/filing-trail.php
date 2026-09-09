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

/*
 *  The schema, before anything measures it.
 *
 *  Not decoration: this suite reads `user_id` off batch rows that exist before
 *  it starts, and the plugin's upgrade runs on a call path rather than on
 *  deploy -- so on a box that has just taken a build, the column is not there
 *  until something creates a batch. tests/tree/auto-file.php and
 *  tests/tree/nl-commands.php open the same way. The upgrade check further
 *  down puts the table back to the old shape on purpose and lets the lazy
 *  install fix it again, which this does not affect.
 */
vergeml_librarian_maybe_install();

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

/*
 *  Every batch that was already here, by id.
 *
 *  A high-water mark was the wrong instrument and this suite used one: the
 *  re-filing cron, an auto-file and a person pressing Move all create batches
 *  above it while the suite runs, and deleting those leaves their move rows
 *  pointing at a batch that is gone. The teardown asks instead which batches
 *  this run's own rows are in, and never touches an id that was already here.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_batch_rows = (array) $wpdb->get_results( "SELECT batch_id, user_id, params FROM {$ft_batches}", ARRAY_A );

$ft_batch_before  = array();
$ft_actor_before  = array();
$ft_params_before = array();

foreach ( $ft_batch_rows as $ft_b ) {
    $ft_batch_before[] = (int) $ft_b['batch_id'];
    $ft_actor_before[ (int) $ft_b['batch_id'] ]  = (int) $ft_b['user_id'];
    /*
     *  And what each of them said about itself.
     *
     *  The pass below writes into whatever refile batch is open for the day,
     *  which on this box is one cron made this morning -- and the undo further
     *  down then stamps that batch with who undid it. That stamp is true of
     *  this run and false of the batch, so it is taken off again in the
     *  teardown. A suite that leaves a real batch claiming a person undid it
     *  is the same class of thing as a suite that deletes one.
     */
    $ft_params_before[ (int) $ft_b['batch_id'] ] = (string) $ft_b['params'];
}

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

/*
 *  Somebody presses it.
 *
 *  The batch a Move creates records who asked for it, and under WP-CLI nobody
 *  is logged in -- so without this the suite would only ever see the 0 that
 *  means cron, and could not tell a working column from a column that is
 *  always 0. Put back to nobody at the end of the actor section below.
 */
wp_set_current_user( 1 );

/*
 *  A clean undo record for the pass to merge into.
 *
 *  vergeml_talk_refile_run() unions what it moved onto whatever is already in
 *  the option, and on the box that option is Nathan's own last Move. The undo
 *  further down runs for real, so it has to be handed a record that names this
 *  suite's pictures and nothing else -- a real undo over a merged record would
 *  put somebody's whole library back. `made` is empty on purpose: this undo
 *  unmakes no folder.
 */
update_option( VERGEML_TALK_UNDO, array(
    'terms' => array(
        array( 'term_id' => (int) $ft_terms['zzTrailA'], 'name' => 'zzTrailA', 'parent' => '' ),
        array( 'term_id' => (int) $ft_terms['zzTrailC'], 'name' => 'zzTrailC', 'parent' => '' ),
    ),
    'files' => array(),
    'made'  => array(),
    'until' => time() + DAY_IN_SECONDS,
), false );

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

// What the pass left for undo, kept so the undo section below can be handed
// exactly this and nothing of anybody else's.
$ft_undo_after = get_option( VERGEML_TALK_UNDO );

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

    /*
     *  The folder it nearly went to. vergeml_filing_pick() returns `nearest`
     *  only when it refused -- on a placement the folder it chose is already
     *  the row -- so the expectation is the pick's own answer either way, and
     *  a placement asserting 0 is as much the point as a refusal asserting a
     *  folder.
     */
    ft_check(
        sprintf( 'the %s row carries the folder it nearly went to', $ft_why ),
        (int) $ft_row['nearest'] === ( isset( $ft_pick['nearest'] ) ? (int) $ft_pick['nearest'] : 0 ),
        sprintf( 'row %d, pick %d', (int) $ft_row['nearest'], isset( $ft_pick['nearest'] ) ? (int) $ft_pick['nearest'] : 0 )
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


/* --------------------------------------------- a reused batch keeps its own */

ft_say( "\nwho approved it\n" );

$ft_pass_batch = 0;

foreach ( $ft_rows as $ft_r ) {
    $ft_pass_batch = (int) $ft_r['batch_id'];
    break;
}

/*
 *  vergeml_autofile_batch() hands out one open batch per scheme per day, so a
 *  pass a person starts often writes into a batch something else already made
 *  -- on this box, a re-filing pass that cron began at 06:35. The actor is the
 *  batch's, not the row's, and the batch belongs to whoever made it: a Move at
 *  ten o'clock does not turn a batch cron opened at six into that person's
 *  doing. So a reused batch keeps the actor it was made with, and the check is
 *  that nothing overwrote it.
 */
if ( in_array( $ft_pass_batch, $ft_batch_before, true ) ) {

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ft_reused = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$ft_batches} WHERE batch_id = %d", $ft_pass_batch ) );

    ft_check(
        'a batch that was already open keeps the actor it was made with',
        $ft_reused === (int) $ft_actor_before[ $ft_pass_batch ],
        sprintf( 'batch %d: was %d, is %d', $ft_pass_batch, (int) $ft_actor_before[ $ft_pass_batch ], $ft_reused )
    );

} else {

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ft_made = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ft_batches} WHERE batch_id = %d", $ft_pass_batch ), ARRAY_A );

    ft_check(
        'the batch this pass made names the person who started it',
        is_array( $ft_made ) && 1 === (int) $ft_made['user_id'] && null !== $ft_made['approved_at'],
        is_array( $ft_made ) ? sprintf( 'user_id %d', (int) $ft_made['user_id'] ) : 'no batch row'
    );
}


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

    /*
     *  And the batch it went into says who did it.
     *
     *  This is the one a person unambiguously caused: a spoken command, typed
     *  by somebody who is logged in. The re-filing batch above may be one cron
     *  opened earlier in the day, which is why the person is proved here.
     */
    $ft_spoken_batch = (int) $ft_hand_row['batch_id'];

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ft_spoken_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$ft_batches} WHERE batch_id = %d",
        $ft_spoken_batch
    ), ARRAY_A );

    if ( in_array( $ft_spoken_batch, $ft_batch_before, true ) ) {

        ft_check(
            'the batch it went into was already open, and kept its own actor',
            is_array( $ft_spoken_row ) && (int) $ft_spoken_row['user_id'] === (int) $ft_actor_before[ $ft_spoken_batch ],
            sprintf( 'batch %d reused', $ft_spoken_batch )
        );

    } else {

        ft_check(
            'the batch it made names the person who pressed it',
            is_array( $ft_spoken_row ) && 1 === (int) $ft_spoken_row['user_id'],
            is_array( $ft_spoken_row ) ? 'user_id ' . (string) $ft_spoken_row['user_id'] : 'no batch row'
        );

        ft_check(
            'and the moment they did',
            is_array( $ft_spoken_row ) && null !== $ft_spoken_row['approved_at'],
            is_array( $ft_spoken_row ) ? 'approved_at ' . ( null === $ft_spoken_row['approved_at'] ? 'NULL' : (string) $ft_spoken_row['approved_at'] ) : 'no batch row'
        );
    }
}

/*
 *  The other reading, and it matters as much: a batch nobody pressed.
 *
 *  Cron, the nightly watch and WP-CLI file with no user, and 0 beside a null
 *  moment has to keep meaning "nobody pressed anything" rather than reading
 *  like a column that failed to fill. Written through the same function that
 *  writes the ones above, with nobody logged in.
 */
wp_set_current_user( 0 );

$ft_nobody = (int) vergeml_autofile_batch( 'auto' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_nobody_row = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$ft_batches} WHERE batch_id = %d",
    $ft_nobody
), ARRAY_A );

if ( in_array( $ft_nobody, $ft_batch_before, true ) ) {

    ft_check(
        'an auto batch was already open, and kept its own actor',
        is_array( $ft_nobody_row ) && (int) $ft_nobody_row['user_id'] === (int) $ft_actor_before[ $ft_nobody ],
        sprintf( 'batch %d reused', $ft_nobody )
    );

} else {

    ft_check(
        'a batch nobody pressed records nobody, not a guess',
        is_array( $ft_nobody_row ) && 0 === (int) $ft_nobody_row['user_id'] && null === $ft_nobody_row['approved_at'],
        is_array( $ft_nobody_row )
            ? sprintf( 'user_id %d, approved_at %s', (int) $ft_nobody_row['user_id'], null === $ft_nobody_row['approved_at'] ? 'NULL' : (string) $ft_nobody_row['approved_at'] )
            : 'no batch row'
    );

    // It carries no move rows, so the teardown's "batches this run caused"
    // cannot see it. Made here, removed here.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( $ft_batches, array( 'batch_id' => $ft_nobody ), array( '%d' ) );
}

// Back to the person, for the undo further down.
wp_set_current_user( 1 );


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

        /*
         *  Both folders, read back out of the record: the one it could not
         *  beat, above, and the one it nearly went to, here. The margin line
         *  itself still names only the runner-up -- the wording that would say
         *  both is Nathan's and is not settled -- so this asserts the values
         *  the reader now hands over, which is what that string will use.
         */
        ft_check(
            sprintf( 'the %s picture names the folder it nearly went to', $ft_why ),
            (int) $ft_read['nearest'] === ( isset( $ft_pick['nearest'] ) ? (int) $ft_pick['nearest'] : 0 )
                && ( 0 === (int) $ft_read['nearest'] ? '' === (string) $ft_read['near'] : '' !== (string) $ft_read['near'] ),
            sprintf( 'read %d "%s", pick %d', (int) $ft_read['nearest'], (string) $ft_read['near'], isset( $ft_pick['nearest'] ) ? (int) $ft_pick['nearest'] : 0 )
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


/* ------------------------------------------ a guide undo says it was undone */

ft_say( "\nand an undo says so\n" );

/*
 *  The one behaviour change in this work, and it corrects a record rather than
 *  a decision.
 *
 *  vergeml_talk_undo() put the terms back and left every row it reversed
 *  saying undone = 0, which is the same value a row that was never undone
 *  carries -- so the table went on asserting placements that had just been
 *  taken back, and vergeml_librarian_why() had to work around it by checking
 *  whether the picture was still in the folder its own row named.
 *
 *  This runs the real undo. It is handed back exactly the record the pass
 *  wrote and nothing of anybody else's -- the option was seeded before the
 *  pass and Nathan's own is restored below -- because an undo over a merged
 *  record would put a whole library back to prove a point about a column.
 */
$ft_moved_id = 0;

foreach ( $ft_rows as $ft_aid => $ft_r ) {
    if ( (int) $ft_r['term_id'] ) {
        $ft_moved_id = (int) $ft_aid;
        break;
    }
}

ft_check( 'the pass placed one picture, which is the one an undo takes back', 0 !== $ft_moved_id, 'no placement to undo' );

update_option( VERGEML_TALK_UNDO, $ft_undo_after, false );

$ft_undone = vergeml_talk_undo();

ft_check(
    'the undo ran and put the picture back',
    is_array( $ft_undone ) && (int) $ft_undone['restored'] >= 1,
    is_wp_error( $ft_undone ) ? $ft_undone->get_error_message() : sprintf( '%d restored', is_array( $ft_undone ) ? (int) $ft_undone['restored'] : 0 )
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_moved_row = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$ft_moves} WHERE attachment_id = %d AND term_id <> 0 ORDER BY move_id DESC LIMIT 1",
    $ft_moved_id
), ARRAY_A );

ft_check(
    'the row that claimed the move is marked undone',
    is_array( $ft_moved_row ) && 1 === (int) $ft_moved_row['undone'],
    is_array( $ft_moved_row ) ? 'undone ' . (string) $ft_moved_row['undone'] : 'no row'
);

/*
 *  And the three that were only looked at are not. An abstention records a
 *  picture that did not move; an undo reverses nothing there, and marking it
 *  would delete the only answer the negative question has.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are cast to int above.
$ft_abstain_marked = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ft_moves} WHERE attachment_id IN ($ft_in) AND term_id = 0 AND undone = 1" );

ft_check( 'the abstentions are left alone -- nothing moved, so nothing was reversed', 0 === $ft_abstain_marked, $ft_abstain_marked . ' marked' );

ft_check(
    'and the reader no longer answers with a placement that was taken back',
    null === vergeml_librarian_why( $ft_moved_id ),
    'still answering'
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_undo_batch = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$ft_batches} WHERE batch_id = %d",
    $ft_pass_batch
), ARRAY_A );

$ft_undo_params = is_array( $ft_undo_batch ) ? json_decode( (string) $ft_undo_batch['params'], true ) : array();

ft_check(
    'the undo names its own actor',
    is_array( $ft_undo_params )
        && isset( $ft_undo_params['undo']['user_id'], $ft_undo_params['undo']['at'] )
        && 1 === (int) $ft_undo_params['undo']['user_id'],
    is_array( $ft_undo_params ) && isset( $ft_undo_params['undo']['user_id'] )
        ? sprintf( 'undone by %d at %s', (int) $ft_undo_params['undo']['user_id'], (string) $ft_undo_params['undo']['at'] )
        : 'no undo actor'
);

/*
 *  And beside it, not over it. `user_id` on the row is who approved the Move;
 *  an undo writing there would lose the one fact the column was added for.
 */
$ft_approver_was = isset( $ft_actor_before[ $ft_pass_batch ] ) ? (int) $ft_actor_before[ $ft_pass_batch ] : 1;

ft_check(
    'and does not write over who approved the Move',
    is_array( $ft_undo_batch ) && (int) $ft_undo_batch['user_id'] === $ft_approver_was,
    is_array( $ft_undo_batch ) ? sprintf( 'approved by %d, was %d', (int) $ft_undo_batch['user_id'], $ft_approver_was ) : 'no batch row'
);

// Nathan's own record back, immediately.
if ( false === $ft_undo_before ) {
    delete_option( VERGEML_TALK_UNDO );
} else {
    update_option( VERGEML_TALK_UNDO, $ft_undo_before, false );
}


/* ------------------------------------------------------ an older site upgrades */

ft_say( "\nan older site upgrades\n" );

$ft_new_columns = array( 'why', 'score', 'runner_up', 'runner_score', 'prompt_hash', 'model_version', 'nearest' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
$ft_have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$ft_moves}" );

$ft_drop = array();

foreach ( $ft_new_columns as $ft_col ) {
    if ( in_array( $ft_col, $ft_have, true ) ) {
        $ft_drop[] = 'DROP COLUMN ' . $ft_col;
    }
}

/*
 *  The option goes back to 1 before the columns come off, not after.
 *
 *  Between the ALTER and the reinstall this box's live moves table has no
 *  reason columns, and every insert that names them fails silently -- the
 *  write path is best effort by design, because a site whose librarian tables
 *  are missing must go on filing pictures. So the window itself is survivable;
 *  what was not survivable was the order. With the option still saying 2, a run
 *  that stopped in that window -- a deploy restarting PHP-FPM under it is the
 *  way it has happened -- left the table at the old shape and
 *  vergeml_librarian_maybe_install() with no reason to ever look again.
 *
 *  Batch 23 on the box is what that costs: a re-filing pass at 06:35:37 on
 *  2026-09-09, two minutes after a deploy, made its batch and wrote none of its
 *  109-row-shaped trail, and nothing said so. Written first, the option is the
 *  repair order: the next batch anything creates calls maybe_install() and puts
 *  the columns back.
 */
$ft_option           = get_option( VERGEML_LIBRARIAN_OPTION, array() );
$ft_option           = is_array( $ft_option ) ? $ft_option : array();
$ft_option['schema'] = 1;
update_option( VERGEML_LIBRARIAN_OPTION, $ft_option, false );

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

vergeml_librarian_maybe_install();

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
$ft_have = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$ft_moves}" );

$ft_missing = array_values( array_diff( $ft_new_columns, $ft_have ) );

ft_check( 'the upgrade puts every reason column back', ! $ft_missing, $ft_missing ? implode( ', ', $ft_missing ) . ' still missing' : '' );

/*
 *  The batches table's pair too. They were dropped by the same DROP above only
 *  if they were there, so this is the same question asked of the other table:
 *  a site that upgrades without ever visiting wp-admin still gets them.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
$ft_bhave = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$ft_batches}" );

$ft_bmissing = array_values( array_diff( array( 'user_id', 'approved_at' ), $ft_bhave ) );

ft_check( 'and the batch knows who approved it', ! $ft_bmissing, $ft_bmissing ? implode( ', ', $ft_bmissing ) . ' missing' : '' );

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

/*
 *  A batch this run did not cause, made while it is running.
 *
 *  This is the re-filing cron, an auto-file or a person pressing Move landing
 *  mid-suite, and it is the case the old teardown got wrong: it deleted every
 *  batch above the id it started at, so a batch like this went and its move
 *  rows were left pointing at nothing. Planted here so the teardown below is
 *  asserted rather than described -- with the old DELETE ... WHERE batch_id >
 *  high-water in place, the two checks after it go red.
 */
$ft_now = current_time( 'mysql', true );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->insert( $ft_batches, array(
    'run_id'      => 0,
    'scheme'      => 'refile',
    'status'      => 'running',
    'step_cursor' => 0,
    'done_n'      => 0,
    'skip_n'      => 0,
    'params'      => wp_json_encode( array( 'source' => 'zz bystander' ) ),
    'reason'      => '',
    'created_at'  => $ft_now,
    'updated_at'  => $ft_now,
), array( '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ) );

$ft_bystander = (int) $wpdb->insert_id;

vergeml_librarian_moves_insert( array( array( $ft_bystander, 999002, 999003, 0, array(
    'why'           => 'ok',
    'score'         => 0.9,
    'runner_up'     => 999004,
    'runner_score'  => 0.1,
    'prompt_hash'   => 'zzbystand',
    'model_version' => 'zz/bystander',
) ) ) );

/*
 *  Which batches this run's own rows are in, asked before those rows go. That
 *  is what "the batches it caused" means, and it is knowable; an id range is
 *  not.
 */
$ft_mine_in = implode( ',', array_map( 'intval', array_unique( $GLOBALS['ft_posts'] ) ) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are cast to int above.
$ft_batch_mine = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT batch_id FROM {$ft_moves} WHERE attachment_id IN ($ft_mine_in)" ) );

$ft_batch_mine[] = 999999;

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

/*
 *  The batches this run caused, and only those.
 *
 *  Three conditions, and each one rules out a batch that is somebody else's:
 *  the id carries a row of this run's, the id was not already here when the
 *  run started, and the batch is empty now that this run's rows are gone. The
 *  last one matters because vergeml_autofile_batch() hands out one open batch
 *  per scheme per day -- a real move landing in the same batch leaves rows in
 *  it, and a batch with rows is a record, not litter.
 */
foreach ( array_unique( $ft_batch_mine ) as $ft_bid ) {

    if ( in_array( (int) $ft_bid, $ft_batch_before, true ) ) {
        continue;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ft_still = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$ft_moves} WHERE batch_id = %d",
        (int) $ft_bid
    ) );

    if ( $ft_still ) {
        continue;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( $ft_batches, array( 'batch_id' => (int) $ft_bid ), array( '%d' ) );
}

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

/*
 *  What the batches that were already here said about themselves, back.
 *
 *  Only the ones this run changed, and each back to the exact string it had.
 */
$ft_params_put = 0;

foreach ( $ft_params_before as $ft_bid => $ft_was ) {

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ft_now_params = $wpdb->get_var( $wpdb->prepare( "SELECT params FROM {$ft_batches} WHERE batch_id = %d", (int) $ft_bid ) );

    if ( null === $ft_now_params || (string) $ft_now_params === $ft_was ) {
        continue;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->update( $ft_batches, array( 'params' => $ft_was ), array( 'batch_id' => (int) $ft_bid ), array( '%s' ), array( '%d' ) );

    $ft_params_put++;
}

ft_say( sprintf( "  put back what %d batch%s said about itself\n", $ft_params_put, 1 === $ft_params_put ? '' : 'es' ) );

$ft_params_wrong = 0;

foreach ( $ft_params_before as $ft_bid => $ft_was ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $ft_now_params = $wpdb->get_var( $wpdb->prepare( "SELECT params FROM {$ft_batches} WHERE batch_id = %d", (int) $ft_bid ) );
    if ( null !== $ft_now_params && (string) $ft_now_params !== $ft_was ) {
        $ft_params_wrong++;
    }
}

ft_check( 'no batch that was already here says anything new about itself', 0 === $ft_params_wrong, $ft_params_wrong . ' changed' );


/* ------------------------------------- and nobody else's record was touched */

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_by_left = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$ft_batches} WHERE batch_id = %d",
    $ft_bystander
) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_by_rows = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$ft_moves} WHERE batch_id = %d",
    $ft_bystander
) );

ft_check(
    'a batch made by something else while this ran is still there',
    1 === $ft_by_left && 1 === $ft_by_rows,
    sprintf( 'batch %d: %d batch row, %d move rows', $ft_bystander, $ft_by_left, $ft_by_rows )
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$ft_batch_after = array_map( 'intval', (array) $wpdb->get_col( "SELECT batch_id FROM {$ft_batches}" ) );

$ft_batch_lost = array_values( array_diff( $ft_batch_before, $ft_batch_after ) );

ft_check(
    'every batch that was already here is still here',
    ! $ft_batch_lost,
    $ft_batch_lost ? implode( ', ', $ft_batch_lost ) . ' deleted' : ''
);

/*
 *  The damage a range delete does is not the missing batch, it is the rows
 *  left behind pointing at it. Asked over the whole table, so it also catches
 *  a batch this suite never heard of.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own tables.
$ft_orphans = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$ft_moves} m
       LEFT JOIN {$ft_batches} b ON b.batch_id = m.batch_id
      WHERE b.batch_id IS NULL"
);

ft_check( 'no move row is left pointing at a batch that is gone', 0 === $ft_orphans, $ft_orphans . ' orphaned' );

// The bystander was this suite's to make, so it is this suite's to remove.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete( $ft_moves, array( 'batch_id' => $ft_bystander ), array( '%d' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete( $ft_batches, array( 'batch_id' => $ft_bystander ), array( '%d' ) );

ft_say( sprintf( "\n%d/%d passed\n", $GLOBALS['ft_pass'], $GLOBALS['ft_pass'] + $GLOBALS['ft_fail'] ) );

ft_report();

if ( $GLOBALS['ft_fail'] > 0 ) {
    exit( 1 );
}
