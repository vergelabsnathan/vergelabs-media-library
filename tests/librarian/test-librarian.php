<?php
/**
 *  The Librarian backend: review, apply, undo.
 *
 *  Run on the box:  wp eval-file /tmp/test-librarian.php --allow-root
 *  Or through the runner:  node tools/verify.mjs librarian
 *
 *  Unlike the organise suite, this one needs real attachments: every
 *  assertion here is about term relationships, and a relationship to a post
 *  that does not exist is not a relationship. So it makes its own -- empty
 *  files with an attachment row each, in a taxonomy of its own, all of it
 *  swept at the end and none of it touching the library the box already has.
 *
 *  A taxonomy of its own is not convenience either. The one-folder-per-file
 *  promise is a promise about the primary media taxonomy, and the way to
 *  prove it does not leak is to register a second one and check nothing
 *  arrives in it.
 *
 *  What this suite does not test is the screen. That is librarian.mjs.
 */

$GLOBALS['vgml_pass'] = 0;
$GLOBALS['vgml_fail'] = 0;

function l_check( $name, $ok, $detail = '' ) {
    if ( $ok ) {
        $GLOBALS['vgml_pass']++;
        echo "  ok   {$name}" . ( $detail ? "  -- {$detail}" : '' ) . "\n";
    } else {
        $GLOBALS['vgml_fail']++;
        echo "  FAIL {$name}" . ( $detail ? "  -- {$detail}" : '' ) . "\n";
    }
}

if ( ! function_exists( 'vergeml_librarian_apply_step' ) ) {
    echo "core/librarian.php is not loaded. Is the plugin active, and not in safe mode?\n";
    exit( 1 );
}

global $wpdb;

$GLOBALS['l_posts']  = array();
$GLOBALS['l_terms']  = array();
$GLOBALS['l_batches'] = array();
$GLOBALS['l_runs']   = array();

/*
 *  Two taxonomies, both ours for the length of this run. The first is what
 *  Apply must target; the second exists so the suite can prove nothing leaks
 *  into it.
 */
const L_TAX   = 'zzlibtax';
const L_OTHER = 'zzlibother';

register_taxonomy( L_TAX, 'attachment', array( 'hierarchical' => true, 'public' => false, 'label' => 'zz librarian' ) );
register_taxonomy( L_OTHER, 'attachment', array( 'hierarchical' => true, 'public' => false, 'label' => 'zz other' ) );

add_filter( 'vergeml_librarian_taxonomy', 'l_taxonomy' );

function l_taxonomy() {
    return L_TAX;
}


/**
 *  One attachment with nothing behind it but a row. The moves log points at
 *  post ids and the undo walk reads their terms; neither cares what the file
 *  is, and creating real images would make the suite slow for no assertion.
 */

function l_attachment( $title, $when = '2026-03-04 10:00:00' ) {

    $id = wp_insert_post( array(
        'post_title'     => 'zz ' . $title,
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'post_mime_type' => 'image/png',
        'post_date'      => $when,
        'post_date_gmt'  => $when,
    ) );

    $GLOBALS['l_posts'][] = (int) $id;

    return (int) $id;
}


/**
 *  A tree in the shape organize emits, without running a clustering pass.
 *  The suite is about applying a tree, and a recorded one is a tree whose
 *  contents the assertions can name.
 */

function l_branch( $label, $ids, $extra = array() ) {

    $members = array();

    foreach ( $ids as $i => $id ) {
        $members[] = array(
            'id'       => (int) $id,
            'distance' => 0.5 + ( $i / 100 ),
            'why'      => 'seeded',
        );
    }

    return array_merge( array(
        'key'       => $label,
        'label'     => $label,
        'path'      => array( $label ),
        'depth'     => 0,
        'parent'    => '',
        'size'      => count( $members ),
        'total'     => count( $members ),
        'members'   => $members,
        'capped'    => false,
        'reason'    => 'seeded branch',
        'agreement' => array( 'close' => 0, 'mid' => 0, 'far' => count( $members ) ),
    ), $extra );
}


/**
 *  A stored run carrying that tree, so the plan can be asked for by run id
 *  the way the screen asks for it.
 */

function l_run( $tree ) {

    global $wpdb;

    $now = current_time( 'mysql', true );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->insert( vergeml_organize_table(), array(
        'parent_run_id' => 0,
        'status'        => 'done',
        'k'             => count( $tree ),
        'n'             => 0,
        'load_cursor'   => 0,
        'tree'          => wp_json_encode( $tree ),
        'params'        => wp_json_encode( array( 'phase' => 'done' ) ),
        'created_at'    => $now,
        'updated_at'    => $now,
    ), array( '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ) );

    $run_id = (int) $wpdb->insert_id;

    $GLOBALS['l_runs'][] = $run_id;

    return $run_id;
}


/**
 *  Drive a batch to a standstill: done, paused, or out of steps.
 */

function l_apply( $args, $cap = 200 ) {

    $result = is_array( $args )
        ? vergeml_librarian_batch_create( $args['scheme'], $args['run_id'], $args['branches'] )
        : null;

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    $batch_id = (int) $result['batch_id'];

    $GLOBALS['l_batches'][] = $batch_id;

    $report = vergeml_librarian_report( $result, microtime( true ) );
    $steps  = 0;

    while ( 'running' === $report['status'] && $steps < $cap ) {
        $report = vergeml_librarian_apply_step( $batch_id );
        if ( is_wp_error( $report ) ) {
            return $report;
        }
        $steps++;
    }

    return $report;
}


function l_undo( $batch_id, $cap = 200 ) {

    $report = vergeml_librarian_undo_step( $batch_id );
    $steps  = 0;

    while ( ! is_wp_error( $report ) && 'undone' !== $report['status'] && $steps < $cap ) {
        $report = vergeml_librarian_undo_step( $batch_id );
        $steps++;
    }

    return $report;
}


function l_terms_of( $id, $taxonomy = L_TAX ) {

    clean_object_term_cache( $id, 'attachment' );

    $terms = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );

    return is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
}


function l_term_named( $name, $taxonomy = L_TAX ) {
    $term = get_term_by( 'name', $name, $taxonomy );
    return $term instanceof WP_Term ? (int) $term->term_id : 0;
}


/* ----------------------------------------------------------------- schema */

echo "\nthe schema\n";

vergeml_librarian_install();

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$has_batches = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( vergeml_librarian_batches_table() ) ) );
$has_moves   = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( vergeml_librarian_moves_table() ) ) );
// phpcs:enable

l_check( 'both tables exist', $has_batches && $has_moves );

l_check( 'the tables are registered on $wpdb',
    isset( $wpdb->vergeml_librarian_batches ) && isset( $wpdb->vergeml_librarian_moves ),
    $wpdb->vergeml_librarian_batches );

/*
 *  The reserved-word trap organize.php hit: a column called `cursor` makes
 *  MariaDB refuse the CREATE, and dbDelta says nothing when it does. The
 *  assertion is that the column that survived is the renamed one.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->vergeml_librarian_batches}" );

l_check( 'the progress column avoided the reserved word',
    in_array( 'step_cursor', $columns, true ) && ! in_array( 'cursor', $columns, true ) );


/* ------------------------------------------------------- the date scheme */

echo "\nthe date/type scheme\n";

$march = array();
for ( $i = 0; $i < 8; $i++ ) {
    $march[] = l_attachment( 'march-' . $i, '2026-03-0' . ( $i + 1 ) . ' 09:00:00' );
}

// Two files in a month of their own: under MIN_BRANCH, so they must fold up
// into their year rather than become a folder of two.
$stray = array(
    l_attachment( 'july-1', '2026-07-01 09:00:00' ),
    l_attachment( 'july-2', '2026-07-02 09:00:00' ),
);

$scheme = vergeml_librarian_scheme_datetype();

l_check( 'the date scheme produces branches', count( $scheme ) > 0, count( $scheme ) . ' branches' );

$again = vergeml_librarian_scheme_datetype();

/*
 *  Determinism, compared on the part that matters. The samples carry
 *  thumbnail URLs built from upload paths, so comparing the whole structure
 *  would be comparing the filesystem; the tree is the assignment.
 */
$fingerprint = function ( $tree ) {
    $lines = array();
    foreach ( $tree as $branch ) {
        $ids = array();
        foreach ( $branch['members'] as $member ) {
            $ids[] = (int) $member['id'];
        }
        sort( $ids );
        $lines[] = implode( ' / ', $branch['path'] ) . ': ' . implode( ',', $ids );
    }
    sort( $lines );
    return implode( "\n", $lines );
};

l_check( 'two calls produce the same tree', $fingerprint( $scheme ) === $fingerprint( $again ) );

$march_branch = null;
$year_branch  = null;

foreach ( $scheme as $branch ) {
    if ( 0 === $branch['depth'] && '2026' === $branch['label'] ) {
        $year_branch = $branch;
    }
    if ( 1 === $branch['depth'] && array( '2026' ) === array_slice( $branch['path'], 0, 1 ) && 8 === $branch['size'] ) {
        $march_branch = $branch;
    }
}

l_check( 'a month with enough files becomes its own folder', null !== $march_branch,
    $march_branch ? implode( ' / ', $march_branch['path'] ) : 'not found' );

$folded_ids = array();

if ( $year_branch ) {
    foreach ( $year_branch['members'] as $member ) {
        $folded_ids[] = (int) $member['id'];
    }
}

l_check( 'a month too small folds into its year',
    in_array( $stray[0], $folded_ids, true ) && in_array( $stray[1], $folded_ids, true ),
    count( $folded_ids ) . ' folded' );

$why = $march_branch ? $march_branch['members'][0]['why'] : '';

l_check( 'every file carries a reason naming the month and the kind',
    false !== strpos( $why, '2026' ) && false !== strpos( $why, 'image' ),
    $why );


/* ------------------------------------------------------------------ apply */

echo "\napplying\n";

$a = array( l_attachment( 'a1' ), l_attachment( 'a2' ), l_attachment( 'a3' ) );
$b = array( l_attachment( 'b1' ), l_attachment( 'b2' ) );

// One file already in a folder of the user's own. It must come out of this
// untouched, and counted.
$mine = wp_insert_term( 'zz Mine', L_TAX );
$mine = is_array( $mine ) ? (int) $mine['term_id'] : 0;
$GLOBALS['l_terms'][] = $mine;

$filed = l_attachment( 'already-filed' );
wp_set_object_terms( $filed, array( $mine ), L_TAX );

$tree = array(
    l_branch( 'zz Alpha', array_merge( $a, array( $filed ) ) ),
    l_branch( 'zz Beta', $b ),
    l_branch( 'zz Capped', array( l_attachment( 'c1' ) ), array( 'capped' => true ) ),
    l_branch( 'needs-a-look', array( l_attachment( 'n1' ) ), array( 'key' => 'needs-a-look' ) ),
);

$run_id = l_run( $tree );

$report = l_apply( array( 'scheme' => 'subject', 'run_id' => $run_id, 'branches' => array() ) );

l_check( 'the batch finishes', ! is_wp_error( $report ) && 'done' === $report['status'],
    is_wp_error( $report ) ? $report->get_error_message() : $report['status'] );

$batch_id = is_wp_error( $report ) ? 0 : (int) $report['batch_id'];

$alpha = l_term_named( 'zz Alpha' );
$beta  = l_term_named( 'zz Beta' );

$GLOBALS['l_terms'][] = $alpha;
$GLOBALS['l_terms'][] = $beta;

l_check( 'the folders were created', $alpha > 0 && $beta > 0, "alpha {$alpha}, beta {$beta}" );

l_check( 'unfiled files were filed',
    array( $alpha ) === l_terms_of( $a[0] ) && array( $alpha ) === l_terms_of( $a[2] ) );

l_check( 'one folder per file', 1 === count( l_terms_of( $a[1] ) ), count( l_terms_of( $a[1] ) ) . ' terms' );

l_check( 'a file that was already filed is left exactly as it was',
    array( $mine ) === l_terms_of( $filed ) );

l_check( 'and it is counted as skipped rather than silently dropped',
    ! is_wp_error( $report ) && $report['skipped'] >= 1, is_wp_error( $report ) ? '' : $report['skipped'] . ' skipped' );

l_check( 'nothing leaked into the other taxonomy',
    array() === l_terms_of( $a[0], L_OTHER ) && array() === l_terms_of( $b[0], L_OTHER ) );

/*
 *  The two kinds of branch the proposal is least sure about start unchecked,
 *  so a caller that said nothing about them must not have had them applied.
 */
l_check( 'a depth-capped branch is not applied unless it is asked for',
    0 === l_term_named( 'zz Capped' ) );

l_check( 'the "needs a look" branch is not applied unless it is asked for',
    0 === l_term_named( 'needs-a-look' ) );

/* ------------------------------------------------------- the moves log */

echo "\nthe moves log\n";

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$moves = (array) $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id = %d ORDER BY move_id ASC",
    $batch_id
), ARRAY_A );
// phpcs:enable

l_check( 'one row per assignment made', count( $moves ) === (int) $report['done'],
    count( $moves ) . ' rows for ' . $report['done'] . ' assignments' );

$created_flags = array();

foreach ( $moves as $move ) {
    $created_flags[ (int) $move['term_id'] ] = (int) $move['term_created'];
}

l_check( 'a folder this batch created is recorded as created',
    isset( $created_flags[ $alpha ] ) && 1 === $created_flags[ $alpha ] );

l_check( 'the log never names a file that was already filed',
    ! in_array( $filed, array_map( function ( $m ) { return (int) $m['attachment_id']; }, $moves ), true ) );


/* -------------------------------------------------------- opting a flag in */

echo "\nopting a flagged branch in\n";

$opt = l_apply( array(
    'scheme'   => 'subject',
    'run_id'   => $run_id,
    'branches' => array(
        array( 'key' => 'zz Alpha', 'enabled' => false ),
        array( 'key' => 'zz Beta', 'enabled' => false ),
        array( 'key' => 'zz Capped', 'enabled' => true ),
        array( 'key' => 'needs-a-look', 'enabled' => false ),
    ),
) );

$capped = l_term_named( 'zz Capped' );
$GLOBALS['l_terms'][] = $capped;

l_check( 'a flagged branch that is asked for is applied', $capped > 0 );

l_check( 'a refused branch leaves its members unfiled',
    array() === l_terms_of( $b[0] ) || array( $beta ) === l_terms_of( $b[0] ),
    'beta was applied by the first batch, not this one' );

if ( ! is_wp_error( $opt ) ) {
    $GLOBALS['l_batches'][] = (int) $opt['batch_id'];
}


/* ------------------------------------------------------------- renaming */

echo "\nrenaming\n";

$r = array( l_attachment( 'r1' ), l_attachment( 'r2' ) );

$rename_run = l_run( array( l_branch( 'zz Original', $r ) ) );

$renamed = l_apply( array(
    'scheme'   => 'subject',
    'run_id'   => $rename_run,
    'branches' => array( array( 'key' => 'zz Original', 'label' => 'zz Renamed', 'enabled' => true ) ),
) );

$new_term = l_term_named( 'zz Renamed' );
$GLOBALS['l_terms'][] = $new_term;

l_check( 'an inline rename is the folder name', $new_term > 0 && 0 === l_term_named( 'zz Original' ) );

l_check( 'and the files went into it', array( $new_term ) === l_terms_of( $r[0] ) );

if ( ! is_wp_error( $renamed ) ) {
    $GLOBALS['l_batches'][] = (int) $renamed['batch_id'];
}


/* ------------------------------------------------------ name collisions */

echo "\nname collisions\n";

$existing = wp_insert_term( 'zz Shared', L_TAX );
$existing = is_array( $existing ) ? (int) $existing['term_id'] : 0;
$GLOBALS['l_terms'][] = $existing;

$theirs = l_attachment( 'theirs' );
wp_set_object_terms( $theirs, array( $existing ), L_TAX );

$ours = array( l_attachment( 'ours-1' ), l_attachment( 'ours-2' ) );

$collide_run = l_run( array( l_branch( 'zz Shared', $ours ) ) );

$collide = l_apply( array( 'scheme' => 'subject', 'run_id' => $collide_run, 'branches' => array() ) );

if ( ! is_wp_error( $collide ) ) {
    $GLOBALS['l_batches'][] = (int) $collide['batch_id'];
}

$shared_terms = get_terms( array( 'taxonomy' => L_TAX, 'hide_empty' => false, 'name' => 'zz Shared' ) );

l_check( 'a name that already exists is reused, never suffixed',
    ! is_wp_error( $shared_terms ) && 1 === count( $shared_terms ),
    is_wp_error( $shared_terms ) ? 'error' : count( $shared_terms ) . ' terms named "zz Shared"' );

l_check( 'the files went into the folder that was already there',
    array( $existing ) === l_terms_of( $ours[0] ) );

l_check( 'the user\'s own file in it was not touched', array( $existing ) === l_terms_of( $theirs ) );

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$reused_flag = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT term_created FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id = %d LIMIT 1",
    is_wp_error( $collide ) ? 0 : (int) $collide['batch_id']
) );
// phpcs:enable

l_check( 'a reused folder is logged as not created', 0 === $reused_flag );


/* ----------------------------------------------------------------- the gate */

echo "\nthe gate\n";

l_check( 'the gate is open by default', vergeml_librarian_gate()['allow'] );

$g = array( l_attachment( 'g1' ), l_attachment( 'g2' ) );
$gate_run = l_run( array( l_branch( 'zz Gated', $g ) ) );

add_filter( 'vergeml_librarian_gate', 'l_deny' );

function l_deny() {
    return array( 'allow' => false, 'reason' => 'zz out of credit' );
}

$denied = l_apply( array( 'scheme' => 'subject', 'run_id' => $gate_run, 'branches' => array() ) );

$gate_batch = is_wp_error( $denied ) ? 0 : (int) $denied['batch_id'];

l_check( 'a gate refusal pauses the batch rather than failing it',
    ! is_wp_error( $denied ) && 'paused' === $denied['status'],
    is_wp_error( $denied ) ? $denied->get_error_message() : $denied['status'] );

l_check( 'and the reason is on the row',
    ! is_wp_error( $denied ) && false !== strpos( $denied['reason'], 'out of credit' ),
    is_wp_error( $denied ) ? '' : $denied['reason'] );

l_check( 'a refused batch filed nothing', array() === l_terms_of( $g[0] ) );

remove_filter( 'vergeml_librarian_gate', 'l_deny' );

$resumed = vergeml_librarian_apply_step( $gate_batch );
$steps   = 0;

while ( ! is_wp_error( $resumed ) && 'running' === $resumed['status'] && $steps++ < 50 ) {
    $resumed = vergeml_librarian_apply_step( $gate_batch );
}

$gated = l_term_named( 'zz Gated' );
$GLOBALS['l_terms'][] = $gated;

l_check( 'the same batch resumes once the gate opens',
    ! is_wp_error( $resumed ) && 'done' === $resumed['status'] && $gated > 0,
    is_wp_error( $resumed ) ? $resumed->get_error_message() : $resumed['status'] );


/* -------------------------------------------------------- pause and resume */

echo "\npause and resume\n";

$p = array();
for ( $i = 0; $i < 40; $i++ ) {
    $p[] = l_attachment( 'p' . $i );
}

$pause_run = l_run( array( l_branch( 'zz Paused', $p ) ) );

$created = vergeml_librarian_batch_create( 'subject', $pause_run, array() );
$pause_batch = (int) $created['batch_id'];
$GLOBALS['l_batches'][] = $pause_batch;

$first = vergeml_librarian_apply_step( $pause_batch );

$mid = vergeml_librarian_batch_get( $pause_batch );
$mid['status'] = 'paused';
$mid['reason'] = 'zz paused by hand';
vergeml_librarian_batch_save( $mid );

$after_pause = vergeml_librarian_batch_get( $pause_batch );

l_check( 'a paused batch keeps its exact progress',
    'paused' === $after_pause['status'] && (int) $after_pause['cursor'] === (int) $first['cursor'],
    'cursor ' . $after_pause['cursor'] . ' of ' . count( $p ) );

$resume = vergeml_librarian_apply_step( $pause_batch );
$steps  = 0;

while ( ! is_wp_error( $resume ) && 'running' === $resume['status'] && $steps++ < 50 ) {
    $resume = vergeml_librarian_apply_step( $pause_batch );
}

$paused_term = l_term_named( 'zz Paused' );
$GLOBALS['l_terms'][] = $paused_term;

l_check( 'resuming finishes the batch without redoing what was done',
    ! is_wp_error( $resume ) && 'done' === $resume['status'] && (int) $resume['done'] === count( $p ),
    is_wp_error( $resume ) ? '' : $resume['done'] . ' of ' . count( $p ) );

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$pause_moves = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id = %d",
    $pause_batch
) );
// phpcs:enable

l_check( 'and no file was filed twice', $pause_moves === count( $p ), $pause_moves . ' moves' );


/* -------------------------------------------------------------------- undo */

echo "\nundo\n";

$u = array( l_attachment( 'u1' ), l_attachment( 'u2' ), l_attachment( 'u3' ), l_attachment( 'u4' ) );

$undo_run = l_run( array( l_branch( 'zz Undone', $u ) ) );

$applied = l_apply( array( 'scheme' => 'subject', 'run_id' => $undo_run, 'branches' => array() ) );

$undo_batch = (int) $applied['batch_id'];
$undo_term  = l_term_named( 'zz Undone' );
$GLOBALS['l_terms'][] = $undo_term;

l_check( 'the batch to undo filed everything', 4 === (int) $applied['done'], $applied['done'] . ' filed' );

/*
 *  The two things that make undo hard, arranged on purpose: one file the
 *  user has moved somewhere else since, and one they have deleted.
 */
$elsewhere = wp_insert_term( 'zz Elsewhere', L_TAX );
$elsewhere = is_array( $elsewhere ) ? (int) $elsewhere['term_id'] : 0;
$GLOBALS['l_terms'][] = $elsewhere;

wp_set_object_terms( $u[0], array( $elsewhere ), L_TAX );

wp_delete_post( $u[3], true );

$undone = l_undo( $undo_batch );

l_check( 'undo completes', ! is_wp_error( $undone ) && 'undone' === $undone['status'],
    is_wp_error( $undone ) ? $undone->get_error_message() : $undone['status'] );

l_check( 'the assignments this batch made are gone',
    array() === l_terms_of( $u[1] ) && array() === l_terms_of( $u[2] ) );

l_check( 'a file the user moved since keeps its new folder',
    array( $elsewhere ) === l_terms_of( $u[0] ) );

l_check( 'and the files it could not take back are reported rather than absorbed',
    ! is_wp_error( $undone ) && $undone['skipped_touched'] >= 2,
    is_wp_error( $undone ) ? '' : $undone['skipped_touched'] . ' left as they are' );

l_check( 'a folder this batch created and then emptied is removed',
    ! is_wp_error( $undone ) && $undone['folders_removed'] >= 1 && 0 === l_term_named( 'zz Undone' ),
    is_wp_error( $undone ) ? '' : $undone['folders_removed'] . ' removed' );

/* ------------------------------------------------- undo keeps what is used */

echo "\nundo keeps a folder somebody has started using\n";

$k = array( l_attachment( 'k1' ), l_attachment( 'k2' ) );

$keep_run = l_run( array( l_branch( 'zz Kept', $k ) ) );

$keep_applied = l_apply( array( 'scheme' => 'subject', 'run_id' => $keep_run, 'branches' => array() ) );

$keep_term = l_term_named( 'zz Kept' );
$GLOBALS['l_terms'][] = $keep_term;

// Somebody drags a file of their own into the new folder before pressing
// Undo. The folder is now theirs, whoever made it.
$theirs_now = l_attachment( 'theirs-now' );
wp_set_object_terms( $theirs_now, array( $keep_term ), L_TAX );

$kept = l_undo( (int) $keep_applied['batch_id'] );

l_check( 'a folder with content that arrived since is kept',
    ! is_wp_error( $kept ) && $kept['folders_kept'] >= 1 && l_term_named( 'zz Kept' ) > 0,
    is_wp_error( $kept )
        ? $kept->get_error_message()
        : $kept['folders_kept'] . ' kept, ' . $kept['folders_removed'] . ' removed' );

l_check( 'and the file somebody put in it is still there',
    array( $keep_term ) === l_terms_of( $theirs_now ) );

/* --------------------------------------------- undo never removes a reuse */

echo "\nundo never removes a folder it did not create\n";

$reuse_undone = l_undo( is_wp_error( $collide ) ? 0 : (int) $collide['batch_id'] );

l_check( 'a reused folder survives the undo of the batch that used it',
    l_term_named( 'zz Shared' ) > 0 );

l_check( 'and only our assignments came out of it',
    array( $existing ) === l_terms_of( $theirs ) && array() === l_terms_of( $ours[0] ) );

l_check( 'no folders were removed by that undo',
    ! is_wp_error( $reuse_undone ) && 0 === (int) $reuse_undone['folders_removed'],
    is_wp_error( $reuse_undone ) ? '' : $reuse_undone['folders_removed'] . ' removed' );


/* ----------------------------------------------------------- the pre-flight */

echo "\nthe pre-flight\n";

$pf_run = l_run( array( l_branch( 'zz Preflight', array( l_attachment( 'pf1' ), l_attachment( 'pf2' ) ) ) ) );

$preflight = vergeml_librarian_preflight( 'subject', $pf_run, array() );

if ( is_wp_error( $preflight ) ) {
    // The quote refuses when the duplicate scan has not run, and passing that
    // refusal through unchanged is the behaviour rather than a failure -- but
    // it means the counting assertions have nothing to run against.
    l_check( 'the pre-flight refuses with the quote\'s own reason when the scan is missing',
        'vergeml_organize_unscanned' === $preflight->get_error_code(),
        $preflight->get_error_message() );
} else {
    l_check( 'the pre-flight counts the files it would file',
        2 === (int) $preflight['unfiled'], $preflight['unfiled'] . ' unfiled' );

    l_check( 'it counts the folders it would create',
        1 === (int) $preflight['folders']['create'], $preflight['folders']['create'] . ' to create' );

    l_check( 'credits are zero and say they are mock',
        0 === (int) $preflight['credits']['cost'] && 'mock' === $preflight['credits']['mode'] );
}


/* ------------------------------------------------------------------ pruning */

echo "\npruning\n";

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$posts_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
$meta_before  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" );
$terms_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" );
$runs_before  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_organize_runs}" );

$batches_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_batches}" );

vergeml_librarian_prune( 2 );

$batches_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_batches}" );

l_check( 'pruning keeps the last few batches and drops the rest',
    $batches_after <= 2 && $batches_after <= $batches_before,
    "{$batches_before} batches, {$batches_after} kept" );

$orphans = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->vergeml_librarian_moves} m
      WHERE NOT EXISTS ( SELECT 1 FROM {$wpdb->vergeml_librarian_batches} b WHERE b.batch_id = m.batch_id )"
);

l_check( 'a pruned batch takes its moves with it', 0 === $orphans, $orphans . ' orphaned moves' );

/*
 *  The one destructive act in this file, and the assertion that it stayed in
 *  its own two tables. Not a spot check: every table a wrong DELETE could
 *  plausibly reach.
 */
$posts_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
$meta_after  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta}" );
$terms_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms}" );
$runs_after  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_organize_runs}" );
// phpcs:enable

l_check( 'pruning touched no media', $posts_after === $posts_before, "{$posts_before} -> {$posts_after}" );
l_check( 'pruning touched no meta', $meta_after === $meta_before, "{$meta_before} -> {$meta_after}" );
l_check( 'pruning touched no terms', $terms_after === $terms_before, "{$terms_before} -> {$terms_after}" );
l_check( 'pruning touched no organise runs', $runs_after === $runs_before, "{$runs_before} -> {$runs_after}" );


/* ----------------------------------------------------------- option safety */

echo "\nthe upgrade path\n";

update_option( VERGEML_LIBRARIAN_OPTION, array( 'schema' => 1, 'probe' => 'kept' ), false );

vergeml_set_options();

$after = get_option( VERGEML_LIBRARIAN_OPTION );

l_check( 'vergeml_set_options leaves vergeml_librarian alone',
    is_array( $after ) && isset( $after['probe'] ) && 'kept' === $after['probe'] );

/*
 *  A site that upgrades without ever visiting wp-admin never fires the
 *  activation hook. The lazy check has to notice and install, or the first
 *  Apply writes to a table that does not exist.
 */
delete_option( VERGEML_LIBRARIAN_OPTION );

vergeml_librarian_maybe_install();

$state = vergeml_librarian_state();

l_check( 'an install that never saw the activation hook still gets the schema',
    isset( $state['schema'] ) && VERGEML_LIBRARIAN_VERSION === (int) $state['schema'] );


/* -------------------------------------------------------------------- tidy */

echo "\ntidying up\n";

foreach ( array_unique( $GLOBALS['l_batches'] ) as $id ) {
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( vergeml_librarian_moves_table(), array( 'batch_id' => (int) $id ), array( '%d' ) );
    $wpdb->delete( vergeml_librarian_batches_table(), array( 'batch_id' => (int) $id ), array( '%d' ) );
    // phpcs:enable
}

foreach ( array_unique( $GLOBALS['l_runs'] ) as $id ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( vergeml_organize_table(), array( 'run_id' => (int) $id ), array( '%d' ) );
}

foreach ( array_unique( $GLOBALS['l_posts'] ) as $id ) {
    if ( get_post( (int) $id ) ) {
        wp_delete_post( (int) $id, true );
    }
}

// Every term this run made, plus anything left in the two throwaway
// taxonomies -- a folder created by a batch whose name the suite never
// learned would otherwise survive it.
foreach ( array( L_TAX, L_OTHER ) as $taxonomy ) {
    $left = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
    if ( ! is_wp_error( $left ) ) {
        foreach ( $left as $term_id ) {
            wp_delete_term( (int) $term_id, $taxonomy );
        }
    }
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$left_posts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE 'zz %' AND post_type = 'attachment'" );

l_check( 'the seeded attachments are gone', 0 === $left_posts, $left_posts . ' left behind' );

$left_terms = get_terms( array( 'taxonomy' => L_TAX, 'hide_empty' => false, 'fields' => 'ids' ) );

l_check( 'the seeded folders are gone',
    is_wp_error( $left_terms ) || 0 === count( $left_terms ),
    is_wp_error( $left_terms ) ? 'taxonomy gone' : count( $left_terms ) . ' left behind' );

delete_option( VERGEML_LIBRARIAN_OPTION );

printf( '%d/%d passed' . PHP_EOL, $GLOBALS['vgml_pass'], $GLOBALS['vgml_pass'] + $GLOBALS['vgml_fail'] );

if ( $GLOBALS['vgml_fail'] > 0 ) {
    exit( 1 );
}
