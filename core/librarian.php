<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  The Librarian: review a proposed tree, apply it, take it back.
 *
 *  Phase 2 produced a proposal and stored it as data. Nothing read it, and
 *  nothing could act on it -- which meant the one thing the feature is for,
 *  showing somebody the folders their own library would get and letting them
 *  say yes, did not exist. This is that half.
 *
 *  Three things shape everything below.
 *
 *  1. **Undo is the feature, not the apology.** Applying writes one row per
 *     assignment it makes, with a flag saying whether this batch created the
 *     folder as well. Undo walks that log backwards and removes only what it
 *     finds there: a file the user has moved since is skipped and counted, a
 *     folder that has picked up manual content is kept and reported. Nothing
 *     is inferred from the proposal, because the proposal is what was asked
 *     for and the log is what actually happened -- and only one of those is
 *     safe to reverse.
 *
 *  2. **Only the unfiled.** A file that already sits in a folder is left
 *     alone and counted as skipped. Existing folders and existing assignments
 *     are never modified, so the worst case of pressing Apply on a library
 *     somebody has already organised by hand is that nothing happens.
 *
 *  3. **A chunk at a time, resumable, pausable.** The same shape as the
 *     importer and the scans: shared hosts time out at thirty seconds, the
 *     browser drives the loop, and the chunk size is the valve. A batch that
 *     is interrupted is `paused` with its exact progress rather than rolled
 *     back, because a half-applied batch that can be resumed or undone is
 *     recoverable and an automatic rollback of work somebody watched happen
 *     is not.
 *
 *  Two schemes are offered: the subject tree from the latest finished
 *  organise run, and a date/type scheme built here from post_date and the
 *  index's `kind`. The second costs no model call and no credit, which makes
 *  it the one a library with no AI index can still use.
 *
 *  @since 3.3
 */


const VERGEML_LIBRARIAN_BATCHES = 'vergeml_librarian_batches';
const VERGEML_LIBRARIAN_MOVES   = 'vergeml_librarian_moves';
const VERGEML_LIBRARIAN_VERSION = 1;
const VERGEML_LIBRARIAN_OPTION  = 'vergeml_librarian';

/*
 *  Files per step, and the floor it may be narrowed to.
 *
 *  Twenty-five rather than the importer's five hundred because each one is a
 *  term assignment rather than a row copy, and because this loop is watched:
 *  a step that returns in a second is a progress bar that moves, and a step
 *  that returns in ten is a page somebody reloads.
 */
const VERGEML_LIBRARIAN_CHUNK     = 25;
const VERGEML_LIBRARIAN_CHUNK_MIN = 5;

// What one step aims to take. Well under the thirty seconds shared hosting
// allows, because the aim is measured from the last step and the next one has
// to be wrong by a wide margin before it hurts.
const VERGEML_LIBRARIAN_STEP_MS = 3500;

// Batches kept per site, with their moves. The same rule as organise runs.
const VERGEML_LIBRARIAN_KEEP = 10;

// Thumbnails a branch shows on the review screen. Six is what fits a card
// without the card becoming a gallery.
const VERGEML_LIBRARIAN_SAMPLES = 6;


/**
 *  Both tables, registered on $wpdb the way core registers its own, and for
 *  the reason ai-index and organize do it: a name interpolated from a helper
 *  is a string the static analysis cannot follow, so every query built that
 *  way reads to Plugin Check as an unprepared one.
 */

vergeml_librarian_register_tables();

function vergeml_librarian_register_tables() {

    global $wpdb;

    $wpdb->vergeml_librarian_batches = $wpdb->prefix . VERGEML_LIBRARIAN_BATCHES;
    $wpdb->vergeml_librarian_moves   = $wpdb->prefix . VERGEML_LIBRARIAN_MOVES;

    foreach ( array( VERGEML_LIBRARIAN_BATCHES, VERGEML_LIBRARIAN_MOVES ) as $table ) {
        if ( ! in_array( $table, $wpdb->tables, true ) ) {
            $wpdb->tables[] = $table;
        }
    }
}


function vergeml_librarian_batches_table() {
    global $wpdb;
    return $wpdb->vergeml_librarian_batches;
}


function vergeml_librarian_moves_table() {
    global $wpdb;
    return $wpdb->vergeml_librarian_moves;
}


function vergeml_librarian_state() {
    $state = get_option( VERGEML_LIBRARIAN_OPTION, array() );
    return is_array( $state ) ? $state : array();
}


/**
 *  The taxonomy Apply targets.
 *
 *  The first media taxonomy the tree knows about, and only that one. A site
 *  with several must not see assignments leak into the others: one folder per
 *  file is a promise about one taxonomy, and honouring it across two would
 *  mean deciding which of somebody's own taxonomies is the real one.
 */

function vergeml_librarian_taxonomy() {

    $taxonomies = function_exists( 'vergeml_tree_taxonomies' ) ? vergeml_tree_taxonomies() : array();

    $primary = $taxonomies ? (string) $taxonomies[0] : '';

    return (string) apply_filters( 'vergeml_librarian_taxonomy', $primary );
}


/* ------------------------------------------------------------------ schema */

/**
 *  vergeml_librarian_install
 *
 *  Two tables: one row per batch, one row per assignment it made. Safe to
 *  call on every load -- dbDelta compares and issues only the difference.
 *
 *  The progress column is `step_cursor` and not `cursor` because CURSOR is a
 *  reserved word: MariaDB refuses the CREATE outright and dbDelta reports
 *  nothing when it does. organize.php learned this the same way. The REST
 *  field is still called `cursor`; only the column had to move.
 */

function vergeml_librarian_install() {

    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $batches = vergeml_librarian_batches_table();
    $moves   = vergeml_librarian_moves_table();
    $collate = $wpdb->get_charset_collate();

    /*
     *  dbDelta parses these rather than executing them, and is fussy in ways
     *  that fail silently: two spaces after PRIMARY KEY, KEY rather than
     *  INDEX, one field per line. TEXT columns carry no DEFAULT -- MySQL
     *  before 8.0.13 refuses one, and this plugin's floor is much older.
     */
    $sql = "CREATE TABLE {$batches} (
        batch_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        run_id bigint(20) unsigned NOT NULL DEFAULT 0,
        scheme varchar(32) NOT NULL DEFAULT '',
        status varchar(16) NOT NULL DEFAULT 'running',
        step_cursor int(10) unsigned NOT NULL DEFAULT 0,
        done_n int(10) unsigned NOT NULL DEFAULT 0,
        skip_n int(10) unsigned NOT NULL DEFAULT 0,
        params longtext NULL,
        reason varchar(191) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (batch_id),
        KEY status (status),
        KEY created_at (created_at)
    ) {$collate};";

    dbDelta( $sql );

    /*
     *  One row per assignment this plugin made, which is the whole basis of
     *  undo. `term_created` says whether the folder came with it -- a folder
     *  that already existed is one the user owns, and undo must never delete
     *  it however empty it ends up.
     */
    $sql = "CREATE TABLE {$moves} (
        move_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        batch_id bigint(20) unsigned NOT NULL DEFAULT 0,
        attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
        term_id bigint(20) unsigned NOT NULL DEFAULT 0,
        term_created tinyint(1) NOT NULL DEFAULT 0,
        undone tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY  (move_id),
        KEY batch_id (batch_id),
        KEY batch_undone (batch_id,undone),
        KEY attachment_id (attachment_id)
    ) {$collate};";

    dbDelta( $sql );

    $state           = vergeml_librarian_state();
    $state['schema'] = VERGEML_LIBRARIAN_VERSION;

    update_option( VERGEML_LIBRARIAN_OPTION, $state, false );
}


/**
 *  Hooked rather than called, so safe mode -- which skips every file in this
 *  folder -- simply means nobody answers, and the activation does not fatal
 *  on a function that was never defined.
 */

add_action( 'vergeml_activate', 'vergeml_librarian_install' );


/**
 *  Both tables, asked of the database rather than of the option.
 *
 *  One query for the pair -- the prefix plus the shared stem matches exactly
 *  these two -- because this is asked when a batch is created and the step
 *  loop after it has a budget to keep.
 */

function vergeml_librarian_tables_exist() {

    global $wpdb;

    $like = $wpdb->esc_like( $wpdb->prefix . 'vergeml_librarian_' ) . '%';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $found = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

    return in_array( vergeml_librarian_batches_table(), $found, true )
        && in_array( vergeml_librarian_moves_table(), $found, true );
}


/**
 *  A site that upgrades without ever visiting an admin screen never fires the
 *  activation hook, and the first thing it would do here is write to a table
 *  that does not exist. So the schema is also checked from the one endpoint
 *  that creates rows -- once, when a batch is created, never on the steps
 *  that follow, because those have a query budget and this would spend it.
 *
 *  The option alone is not enough to decide that, for ai-index.php's reason:
 *  a table dropped by hand, by a host's migration or by a half-restored
 *  backup leaves the option still saying the schema is current, and trusting
 *  it there means the next Apply writes into a table that is not there. So
 *  the database is asked as well.
 */

function vergeml_librarian_maybe_install() {

    $state = vergeml_librarian_state();

    if ( empty( $state['schema'] ) || VERGEML_LIBRARIAN_VERSION !== (int) $state['schema'] ) {
        vergeml_librarian_install();
        return;
    }

    if ( ! vergeml_librarian_tables_exist() ) {
        vergeml_librarian_install();
    }
}


add_action( 'admin_init', 'vergeml_librarian_housekeeping' );

function vergeml_librarian_housekeeping() {

    if ( wp_doing_ajax() ) {
        return;
    }

    vergeml_librarian_maybe_install();

    $state = vergeml_librarian_state();

    /*
     *  Pruning lives here rather than on a step, for organize's reason: the
     *  queries that find and drop the eleventh-oldest batch would come out of
     *  somebody's step budget, and dropping old rows is housekeeping.
     */
    $last = isset( $state['pruned'] ) ? (int) $state['pruned'] : 0;

    if ( time() - $last < DAY_IN_SECONDS ) {
        return;
    }

    vergeml_librarian_prune();

    $state['pruned'] = time();

    update_option( VERGEML_LIBRARIAN_OPTION, $state, false );
}


/* ------------------------------------------------------------ batch storage */

function vergeml_librarian_batch_get( $batch_id ) {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table; there is no core API for it.
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->vergeml_librarian_batches} WHERE batch_id = %d",
        (int) $batch_id
    ), ARRAY_A );
    // phpcs:enable

    return $row ? vergeml_librarian_batch_out( $row ) : null;
}


function vergeml_librarian_batch_out( $row ) {

    $params = json_decode( (string) $row['params'], true );

    return array(
        'batch_id'   => (int) $row['batch_id'],
        'run_id'     => (int) $row['run_id'],
        'scheme'     => (string) $row['scheme'],
        'status'     => (string) $row['status'],
        'cursor'     => (int) $row['step_cursor'],
        'done'       => (int) $row['done_n'],
        'skipped'    => (int) $row['skip_n'],
        'params'     => is_array( $params ) ? $params : array(),
        'reason'     => (string) $row['reason'],
        'created_at' => (string) $row['created_at'],
        'updated_at' => (string) $row['updated_at'],
    );
}


/**
 *  vergeml_librarian_batch_save
 *
 *  One query. A prepared UPDATE rather than $wpdb->update() for organize's
 *  reason: $wpdb->update() asks the table for its column charsets before it
 *  writes -- SHOW FULL COLUMNS, a query like any other -- and a step's budget
 *  cannot carry an introspection it does not need.
 */

function vergeml_librarian_batch_save( $batch ) {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return false !== $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->vergeml_librarian_batches}
            SET status = %s, step_cursor = %d, done_n = %d, skip_n = %d,
                params = %s, reason = %s, updated_at = %s
          WHERE batch_id = %d",
        (string) $batch['status'],
        (int) $batch['cursor'],
        (int) $batch['done'],
        (int) $batch['skipped'],
        wp_json_encode( $batch['params'] ),
        vergeml_librarian_reason( $batch['reason'] ),
        current_time( 'mysql', true ),
        (int) $batch['batch_id']
    ) );
    // phpcs:enable
}


/**
 *  A pause cause, cut to what the column holds.
 *
 *  Trimmed here rather than left to MySQL: a silent truncation on the way in
 *  is how a reason turns into half a sentence nobody can act on, and the
 *  reasons this carries are short by construction anyway.
 */

function vergeml_librarian_reason( $reason ) {

    $reason = trim( (string) $reason );

    if ( '' === $reason ) {
        return '';
    }

    // 188 plus the ellipsis: the column is 191, and a reason that reaches it
    // should say so rather than end mid-word.
    return mb_strlen( $reason ) > 191 ? mb_substr( $reason, 0, 188 ) . '…' : $reason;
}


/* -------------------------------------------------------------- the schemes */

/**
 *  vergeml_librarian_scheme_datetype
 *
 *  Year, then month, from post_date -- with the index's `kind` on each file's
 *  reason line so the folder says what it holds as well as when it arrived.
 *
 *  One query, whatever the library's size, and no model call: this is the
 *  scheme a site with no AI index can still use, and the one that costs
 *  nothing to offer. Deterministic by construction -- the rows come back in
 *  attachment id order and every grouping decision below is arithmetic on
 *  that order, so two calls produce the same tree and the suite says so.
 *
 *  Months smaller than a folder is worth fold up into their year, for the
 *  reason organize folds small clusters: eleven folders of two files each is
 *  not an organised library, it is the same mess with more clicking.
 */

function vergeml_librarian_scheme_datetype() {

    global $wpdb;

    $limits = function_exists( 'vergeml_organize_limits' )
        ? vergeml_organize_limits()
        : array( 'min_branch' => 5 );

    $min = max( 1, (int) $limits['min_branch'] );

    /*
     *  One pass. The join is LEFT because the index is optional here: a
     *  library that has never been described still has dates, and a scheme
     *  that needed the index would not be the fallback it exists to be.
     */
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $wpdb->get_results(
        "SELECT p.ID AS id,
                YEAR( p.post_date ) AS y,
                MONTH( p.post_date ) AS m,
                p.post_mime_type AS mime,
                i.kind AS kind
           FROM {$wpdb->posts} p
           LEFT JOIN {$wpdb->vergeml_ai_index} i ON i.attachment_id = p.ID
          WHERE p.post_type = 'attachment'
          ORDER BY p.ID ASC",
        ARRAY_A
    );
    // phpcs:enable

    if ( ! $rows ) {
        return array();
    }

    // Grouped by year then month, in the order the rows arrived, so the tree
    // is a property of the library rather than of PHP's hashing.
    $years = array();

    foreach ( $rows as $row ) {

        $year  = (int) $row['y'];
        $month = (int) $row['m'];

        if ( ! isset( $years[ $year ] ) ) {
            $years[ $year ] = array();
        }

        if ( ! isset( $years[ $year ][ $month ] ) ) {
            $years[ $year ][ $month ] = array();
        }

        $years[ $year ][ $month ][] = array(
            'id'   => (int) $row['id'],
            'kind' => vergeml_librarian_kind( $row['kind'], $row['mime'] ),
        );
    }

    krsort( $years, SORT_NUMERIC ); // newest year first, which is what people look for

    $branches = array();

    foreach ( $years as $year => $months ) {

        ksort( $months, SORT_NUMERIC );

        $label   = (string) $year;
        $folded  = array();
        $kept    = array();

        foreach ( $months as $month => $files ) {
            if ( count( $files ) < $min ) {
                foreach ( $files as $file ) {
                    $folded[] = array( 'file' => $file, 'month' => $month );
                }
            } else {
                $kept[ $month ] = $files;
            }
        }

        $members = array();

        foreach ( $folded as $entry ) {
            $members[] = vergeml_librarian_datetype_member(
                $entry['file'],
                $entry['month'],
                (int) $year,
                true
            );
        }

        $total = count( $folded );

        foreach ( $kept as $files ) {
            $total += count( $files );
        }

        $branches[] = array(
            'key'     => $label,
            'label'   => $label,
            'path'    => array( $label ),
            'depth'   => 0,
            'parent'  => '',
            'size'    => count( $members ),
            'total'   => $total,
            'members' => $members,
            'capped'  => false,
            'reason'  => sprintf(
                /* translators: %s: a year, e.g. "2026". */
                __( 'Everything uploaded in %s. Months too small to be a folder of their own sit here rather than alone.', 'vergelabs-media-library' ),
                $label
            ),
        );

        foreach ( $kept as $month => $files ) {

            $name = vergeml_librarian_month_name( $month );

            $rows_out = array();

            foreach ( $files as $file ) {
                $rows_out[] = vergeml_librarian_datetype_member( $file, $month, (int) $year, false );
            }

            $branches[] = array(
                'key'     => $label . ' / ' . $name,
                'label'   => $name,
                'path'    => array( $label, $name ),
                'depth'   => 1,
                'parent'  => $label,
                'size'    => count( $rows_out ),
                'total'   => count( $rows_out ),
                'members' => $rows_out,
                'capped'  => false,
                'reason'  => sprintf(
                    /* translators: 1: a month name, 2: a year, 3: how many files. */
                    __( 'The %1$d files uploaded in %2$s %3$s.', 'vergelabs-media-library' ),
                    count( $rows_out ),
                    $name,
                    $label
                ),
            );
        }
    }

    /*
     *  The same shape organize emits, down to the agreement buckets, so the
     *  review screen renders both schemes with one code path. The distances
     *  are null here because there is no centre to be far from -- a date is
     *  not a guess -- and the buckets come out empty, which is the honest
     *  answer rather than a confidence figure invented for symmetry.
     */
    foreach ( $branches as $i => $branch ) {
        $branches[ $i ]['agreement'] = function_exists( 'vergeml_organize_agreement' )
            ? vergeml_organize_agreement( $branch['members'] )
            : array( 'close' => 0, 'mid' => 0, 'far' => 0 );
    }

    return function_exists( 'vergeml_organize_hydrate' )
        ? vergeml_organize_hydrate( $branches )
        : $branches;
}


function vergeml_librarian_datetype_member( $file, $month, $year, $folded ) {

    $name = vergeml_librarian_month_name( $month );

    if ( $folded ) {
        $why = sprintf(
            /* translators: 1: month and year, e.g. "March 2026", 2: what kind of file it is. */
            __( 'Uploaded %1$s · %2$s — filed under the year, because that month held too few files to be a folder.', 'vergelabs-media-library' ),
            $name . ' ' . $year,
            $file['kind']
        );
    } else {
        $why = sprintf(
            /* translators: 1: month and year, e.g. "March 2026", 2: what kind of file it is. */
            __( 'Uploaded %1$s · %2$s', 'vergelabs-media-library' ),
            $name . ' ' . $year,
            $file['kind']
        );
    }

    return array(
        'id'       => (int) $file['id'],
        'distance' => null,
        'why'      => $why,
    );
}


/**
 *  What a file is, in one word.
 *
 *  The index's answer when there is one, and the top half of the mime type
 *  when there is not -- a library nobody has described still knows the
 *  difference between an image and a PDF, and saying "photo" about a file
 *  nothing has looked at would be the kind of confident wrong answer this
 *  plugin exists not to give.
 */

function vergeml_librarian_kind( $kind, $mime ) {

    $kind = trim( (string) $kind );

    if ( '' !== $kind ) {
        return $kind;
    }

    $mime = (string) $mime;
    $at   = strpos( $mime, '/' );

    return false === $at ? __( 'file', 'vergelabs-media-library' ) : substr( $mime, 0, $at );
}


/**
 *  A month's name in the site's language, without a date object per file.
 */

function vergeml_librarian_month_name( $month ) {

    global $wp_locale;

    $month = max( 1, min( 12, (int) $month ) );

    if ( $wp_locale instanceof WP_Locale ) {
        return $wp_locale->get_month( $month );
    }

    return gmdate( 'F', gmmktime( 0, 0, 0, $month, 1, 2000 ) );
}


/**
 *  The newest finished organise run, or null.
 *
 *  Deliberately not vergeml_organize_run_latest(), which returns the newest
 *  run whatever state it is in: a run that is still clustering has no tree to
 *  review, and offering it as a scheme would open the review screen on an
 *  empty proposal.
 */

function vergeml_librarian_latest_done_run() {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row(
        "SELECT * FROM {$wpdb->vergeml_organize_runs} WHERE status = 'done' ORDER BY run_id DESC LIMIT 1",
        ARRAY_A
    );
    // phpcs:enable

    return $row ? vergeml_organize_row_out( $row ) : null;
}


/**
 *  Whether a branch starts unchecked on the review screen.
 *
 *  "Needs a look" and anything the depth cap forced together are the two
 *  places the proposal is least sure of itself, so they are the two the
 *  person is asked about rather than told.
 */

function vergeml_librarian_flagged( $branch ) {

    if ( isset( $branch['key'] ) && 'needs-a-look' === $branch['key'] ) {
        return true;
    }

    return ! empty( $branch['capped'] );
}


/**
 *  The tree behind a scheme id.
 */

function vergeml_librarian_scheme_tree( $scheme, $run_id = 0 ) {

    if ( 'datetype' === $scheme ) {
        return vergeml_librarian_scheme_datetype();
    }

    if ( 'subject' !== $scheme ) {
        return array();
    }

    $run = $run_id > 0 ? vergeml_organize_run_get( $run_id ) : vergeml_librarian_latest_done_run();

    return ( $run && ! empty( $run['tree'] ) ) ? $run['tree'] : array();
}


/* ---------------------------------------------------------------- the gate */

/**
 *  vergeml_librarian_gate
 *
 *  Where the paid add-on will check credits, and where the free plugin says
 *  yes.
 *
 *  A hook rather than a stub with an if in it: Pro hangs its check here and
 *  the free plugin ships with it open, so there is exactly one place the
 *  answer comes from and no build of this file knows about licensing.
 *
 *  A refusal pauses the batch with its reason. It never fails it -- a batch
 *  that ran out of credit halfway is not a broken batch, it is a batch
 *  waiting, and the work it already did stays applied and undoable.
 */

function vergeml_librarian_gate( $context = array() ) {

    $verdict = apply_filters(
        'vergeml_librarian_gate',
        array( 'allow' => true, 'reason' => '' ),
        $context
    );

    if ( ! is_array( $verdict ) ) {
        return array( 'allow' => true, 'reason' => '' );
    }

    return array(
        'allow'  => ! isset( $verdict['allow'] ) || (bool) $verdict['allow'],
        'reason' => isset( $verdict['reason'] ) ? (string) $verdict['reason'] : '',
    );
}


/**
 *  What applying would cost, as the gate sees it.
 *
 *  Zero and honest about being zero. The service is not live, so a figure
 *  here would be a number somebody made up, and this screen's whole argument
 *  is that its numbers are counted.
 */

function vergeml_librarian_credits( $context = array() ) {

    $gate = vergeml_librarian_gate( $context );

    return array(
        'cost'   => 0,
        'mode'   => 'mock',
        'allow'  => $gate['allow'],
        'reason' => $gate['reason'],
    );
}


/* ------------------------------------------------------------- the pre-flight */

/**
 *  vergeml_librarian_preflight
 *
 *  What pressing Apply would do, counted.
 *
 *  Built on organize's quote, which refuses rather than estimating when the
 *  duplicate scan has not run -- the same refusal is passed straight through,
 *  because a screen that quietly downgraded to an estimate would be exactly
 *  the thing the quote exists to prevent.
 */

function vergeml_librarian_preflight( $scheme, $run_id, $branches ) {

    $quote = vergeml_organize_quote();

    if ( is_wp_error( $quote ) ) {
        return $quote;
    }

    $taxonomy = vergeml_librarian_taxonomy();

    if ( '' === $taxonomy ) {
        return new WP_Error(
            'vergeml_librarian_no_taxonomy',
            __( 'You have no folders switched on yet, so there is nowhere to put anything.', 'vergelabs-media-library' ),
            array( 'status' => 409 )
        );
    }

    $plan = vergeml_librarian_plan( $scheme, $run_id, $branches, $taxonomy );

    if ( is_wp_error( $plan ) ) {
        return $plan;
    }

    $ids = array();

    foreach ( $plan['work'] as $pair ) {
        $ids[] = (int) $pair[0];
    }

    $filed = vergeml_librarian_filed( $ids, $taxonomy );

    $unfiled = count( $ids ) - count( $filed );

    $create = 0;
    $reuse  = 0;

    foreach ( $plan['terms'] as $term ) {
        if ( $term['id'] > 0 ) {
            $reuse++;
        } else {
            $create++;
        }
    }

    $state = vergeml_librarian_state();
    $rate  = isset( $state['rate_ms'] ) ? (float) $state['rate_ms'] : 0.0;

    /*
     *  The estimate is organize's: extrapolated from work actually measured,
     *  and honest about not being known when nothing has been measured yet.
     *  The rate comes from the last batch this site ran, so the first Apply
     *  says "not known" and every one after it says a number that came from
     *  this server rather than from a table in a plan.
     */
    $estimate = $rate > 0
        ? vergeml_organize_estimate( 1, $rate, $unfiled )
        : vergeml_organize_estimate( 0, 0.0, $unfiled );

    return array(
        'scheme'     => (string) $scheme,
        'run_id'     => (int) $plan['run_id'],
        'taxonomy'   => $taxonomy,
        'branches'   => count( $plan['branches'] ),
        'files'      => count( $ids ),
        'unfiled'    => $unfiled,
        'filed'      => count( $filed ),
        'folders'    => array( 'create' => $create, 'reuse' => $reuse ),
        'estimate'   => $estimate,
        'credits'    => vergeml_librarian_credits( array(
            'scheme' => $scheme,
            'files'  => $unfiled,
        ) ),
        'quote'      => $quote,
    );
}


/**
 *  Which of these files already sit in a folder.
 *
 *  One query for the whole set rather than a membership read per file: the
 *  pre-flight is allowed six queries and the review screen may hand it every
 *  id in the library.
 */

function vergeml_librarian_filed( $ids, $taxonomy ) {

    global $wpdb;

    $ids = array_values( array_unique( array_map( 'intval', (array) $ids ) ) );

    if ( ! $ids ) {
        return array();
    }

    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    $found = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT tr.object_id
           FROM {$wpdb->term_relationships} tr
           JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
          WHERE tt.taxonomy = %s AND tr.object_id IN ( $placeholders )",
        array_merge( array( $taxonomy ), $ids )
    ) );
    // phpcs:enable

    return array_map( 'intval', (array) $found );
}


/* ------------------------------------------------------------------ the plan */

/**
 *  vergeml_librarian_plan
 *
 *  A tree plus the person's edits, resolved into the work a batch does.
 *
 *  Everything the review screen can say -- which branches are in, what they
 *  were renamed to, which were refused -- is settled here, once, at batch
 *  creation. A step then reads a list and does not have to know what a branch
 *  is, which is what keeps its query count flat.
 *
 *  Two queries at most: the run (for the subject scheme) or the library (for
 *  the date one), and the existing folders this plan would land in.
 */

function vergeml_librarian_plan( $scheme, $run_id, $branches, $taxonomy ) {

    $scheme = in_array( $scheme, array( 'subject', 'datetype' ), true ) ? $scheme : 'subject';

    if ( 'subject' === $scheme && $run_id <= 0 ) {
        $run = vergeml_librarian_latest_done_run();
        if ( $run ) {
            $run_id = (int) $run['run_id'];
        }
    }

    $tree = vergeml_librarian_scheme_tree( $scheme, $run_id );

    if ( ! $tree ) {
        return new WP_Error(
            'vergeml_librarian_no_tree',
            'subject' === $scheme
                ? __( 'There is no finished proposal to apply. Run the organiser first.', 'vergelabs-media-library' )
                : __( 'This library has no files to file.', 'vergelabs-media-library' ),
            array( 'status' => 409 )
        );
    }

    /*
     *  The screen's edits, by branch key. A branch the caller said nothing
     *  about takes the default: in, unless it is one of the two kinds the
     *  proposal is least sure about.
     */
    $edits = array();

    foreach ( (array) $branches as $edit ) {

        if ( ! is_array( $edit ) || empty( $edit['key'] ) ) {
            continue;
        }

        $edits[ (string) $edit['key'] ] = array(
            'label'   => isset( $edit['label'] ) ? (string) $edit['label'] : '',
            'enabled' => ! isset( $edit['enabled'] ) || (bool) $edit['enabled'],
        );
    }

    $chosen = array();
    $work   = array();

    foreach ( $tree as $branch ) {

        $key = isset( $branch['key'] ) ? (string) $branch['key'] : '';

        if ( '' === $key || empty( $branch['members'] ) ) {
            continue;
        }

        $edit = isset( $edits[ $key ] ) ? $edits[ $key ] : null;

        if ( $edit ) {
            if ( ! $edit['enabled'] ) {
                continue; // "not this one": its members stay unfiled
            }
        } elseif ( vergeml_librarian_flagged( $branch ) ) {
            continue; // flagged branches are opted into, never defaulted in
        }

        $label = ( $edit && '' !== $edit['label'] ) ? $edit['label'] : (string) $branch['label'];

        $path = isset( $branch['path'] ) ? array_map( 'strval', (array) $branch['path'] ) : array( $label );

        // The rename is the folder's name, so it replaces the last step of
        // the path -- the ancestors keep the names their own branches gave
        // them, whether or not those branches are in this batch.
        if ( $path ) {
            $path[ count( $path ) - 1 ] = $label;
        } else {
            $path = array( $label );
        }

        $path = vergeml_librarian_clean_path( $path );

        if ( ! $path ) {
            continue;
        }

        $index = count( $chosen );

        $chosen[] = array(
            'key'   => $key,
            'label' => $path[ count( $path ) - 1 ],
            'path'  => $path,
        );

        foreach ( $branch['members'] as $member ) {
            if ( isset( $member['id'] ) ) {
                $work[] = array( (int) $member['id'], $index );
            }
        }
    }

    if ( ! $work ) {
        return new WP_Error(
            'vergeml_librarian_nothing_chosen',
            __( 'Nothing was chosen to file.', 'vergelabs-media-library' ),
            array( 'status' => 400 )
        );
    }

    return array(
        'scheme'   => $scheme,
        'run_id'   => (int) $run_id,
        'taxonomy' => $taxonomy,
        'branches' => $chosen,
        'terms'    => vergeml_librarian_resolve_terms( $chosen, $taxonomy ),
        'work'     => $work,
    );
}


/**
 *  A branch label, made into something a taxonomy will accept.
 *
 *  Clustering produces labels from tags, and tags come from a model: they can
 *  arrive with markup in them, as whitespace, or as an emoji. Sanitised, but
 *  never sanitised into nothing -- a folder called "" is worse than a folder
 *  with an odd name, so anything that empties falls back to a name that says
 *  what it is.
 */

function vergeml_librarian_clean_path( $path ) {

    $out = array();

    foreach ( (array) $path as $step ) {

        $name = sanitize_text_field( (string) $step );
        $name = trim( preg_replace( '/\s+/u', ' ', $name ) );

        if ( '' === $name ) {
            $name = __( 'Untitled folder', 'vergelabs-media-library' );
        }

        // Term names are stored in a 200-character column; a label that long
        // is a bug upstream, but it must not become a failed insert here.
        if ( mb_strlen( $name ) > 190 ) {
            $name = mb_substr( $name, 0, 190 );
        }

        $out[] = $name;
    }

    return $out;
}


/**
 *  vergeml_librarian_resolve_terms
 *
 *  Every folder this plan touches, and whether it already exists.
 *
 *  Existing folders are reused -- the decision, and the reason there is no
 *  suffixing anywhere in this file. A name that is already there at the same
 *  level is the user's folder, this batch adds to it, and the moves log says
 *  the folder was not created so undo will never remove it.
 *
 *  One query: every term in the taxonomy, name and parent, which is the same
 *  read the folder tree does on every page load.
 */

function vergeml_librarian_resolve_terms( $branches, $taxonomy ) {

    $existing = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        // Names and parents are all this needs, and priming the meta for
        // every folder on the site is a query the pre-flight would spend for
        // nothing.
        'update_term_meta_cache' => false,
    ) );

    $by_parent = array();

    if ( ! is_wp_error( $existing ) ) {
        foreach ( $existing as $term ) {
            $by_parent[ (int) $term->parent ][ vergeml_librarian_name_key( $term->name ) ] = (int) $term->term_id;
        }
    }

    $terms = array();

    foreach ( $branches as $branch ) {

        $parent  = 0;
        $walked  = array();

        /*
         *  Once an ancestor is one this plan will have to create, nothing
         *  below it can already exist -- and looking it up anyway is how a
         *  folder gets reused that merely shares a name with one somewhere
         *  else in the tree. "2026 / March" under a year that is not there
         *  yet must not resolve to a top-level "March" somebody already has.
         */
        $pending = false;

        foreach ( $branch['path'] as $step ) {

            $walked[] = $step;
            $key      = implode( ' / ', $walked );

            if ( isset( $terms[ $key ] ) ) {
                $parent  = (int) $terms[ $key ]['id'];
                $pending = $pending || $parent <= 0;
                continue;
            }

            $name_key = vergeml_librarian_name_key( $step );

            $found = ( ! $pending && isset( $by_parent[ $parent ][ $name_key ] ) )
                ? (int) $by_parent[ $parent ][ $name_key ]
                : 0;

            $terms[ $key ] = array(
                'name'    => $step,
                'parent'  => $parent,
                'id'      => $found,
                'created' => 0,
            );

            $parent  = $found;
            $pending = $pending || $found <= 0;
        }
    }

    return $terms;
}


/**
 *  Names compared the way a person would compare them: case and surrounding
 *  space are not a difference. wp_insert_term takes the same view, so a plan
 *  that thought otherwise would ask for a folder the database then refuses as
 *  a duplicate.
 */

function vergeml_librarian_name_key( $name ) {
    return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $name ) ) : strtolower( trim( (string) $name ) );
}


/* ----------------------------------------------------------------- applying */

/**
 *  vergeml_librarian_batch_create
 *
 *  The plan, resolved and written down. Two queries beyond the plan's own:
 *  none, and the insert.
 */

function vergeml_librarian_batch_create( $scheme, $run_id, $branches ) {

    global $wpdb;

    vergeml_librarian_maybe_install();

    $taxonomy = vergeml_librarian_taxonomy();

    if ( '' === $taxonomy ) {
        return new WP_Error(
            'vergeml_librarian_no_taxonomy',
            __( 'No media taxonomy is switched on, so there is nowhere to file anything.', 'vergelabs-media-library' ),
            array( 'status' => 409 )
        );
    }

    $plan = vergeml_librarian_plan( $scheme, $run_id, $branches, $taxonomy );

    if ( is_wp_error( $plan ) ) {
        return $plan;
    }

    $gate = vergeml_librarian_gate( array(
        'scheme' => $plan['scheme'],
        'files'  => count( $plan['work'] ),
        'stage'  => 'create',
    ) );

    /*
     *  The files that already have a folder are dropped here rather than
     *  skipped one at a time later, and it buys two things.
     *
     *  The first is the promise: a folder is never created for a branch whose
     *  files all turn out to be filed already, because that branch is not in
     *  the work list at all.
     *
     *  The second is the budget. Creating the folders a chunk happened to
     *  need made a step cost more the more branches it spanned -- 20 branches
     *  cost 198 queries against 123 for the same 80 files in 2 branches,
     *  measured, which is the shape of an N+1 even though nothing loops over
     *  a file. Knowing the whole work list up front means the folders can be
     *  made in a phase of their own, and every filing step after that is
     *  filing and nothing else.
     */
    $ids = array();

    foreach ( $plan['work'] as $pair ) {
        $ids[] = (int) $pair[0];
    }

    $filed = array_flip( vergeml_librarian_filed( $ids, $taxonomy ) );

    $work    = array();
    $wanted  = array();
    $skipped = 0;

    foreach ( $plan['work'] as $pair ) {

        if ( isset( $filed[ (int) $pair[0] ] ) ) {
            $skipped++;
            continue;
        }

        $work[] = $pair;

        $branch = $plan['branches'][ (int) $pair[1] ];

        $wanted[ implode( ' / ', $branch['path'] ) ] = true;
    }

    // Ancestors before the folders under them, so a term is never inserted
    // beneath a parent that does not exist yet.
    $folders = vergeml_librarian_folder_order( array_keys( $wanted ), $plan['terms'] );

    $params = array(
        'taxonomy' => $taxonomy,
        'branches' => $plan['branches'],
        'terms'    => $plan['terms'],
        'work'     => $work,
        'n'        => count( $work ),
        'folders'  => $folders,
        'folder_n' => 0,
        'phase'    => $folders ? 'folders' : 'files',
        'chunk'    => vergeml_librarian_chunk( 0.0 ),
        'timing'   => array( 'ms' => 0.0, 'n' => 0 ),
        'undo'     => array( 'cursor' => 0, 'undone' => 0, 'touched' => 0, 'removed' => 0, 'kept' => 0 ),
    );

    $now = current_time( 'mysql', true );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->insert(
        vergeml_librarian_batches_table(),
        array(
            'run_id'      => (int) $plan['run_id'],
            'scheme'      => (string) $plan['scheme'],
            'status'      => $gate['allow'] ? 'running' : 'paused',
            'step_cursor' => 0,
            'done_n'      => 0,
            'skip_n'      => $skipped,
            'params'      => wp_json_encode( $params ),
            'reason'      => vergeml_librarian_reason( $gate['allow'] ? '' : $gate['reason'] ),
            'created_at'  => $now,
            'updated_at'  => $now,
        ),
        array( '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
    );
    // phpcs:enable

    return array(
        'batch_id'   => (int) $wpdb->insert_id,
        'run_id'     => (int) $plan['run_id'],
        'scheme'     => (string) $plan['scheme'],
        'status'     => $gate['allow'] ? 'running' : 'paused',
        'cursor'     => 0,
        'done'       => 0,
        'skipped'    => $skipped,
        'params'     => $params,
        'reason'     => $gate['allow'] ? '' : $gate['reason'],
        'created_at' => $now,
        'updated_at' => $now,
    );
}


/**
 *  Every folder the work needs, ancestors first.
 *
 *  A branch two levels down needs its parent, and its parent may not be a
 *  branch anybody chose -- so the list is walked out of the resolved paths
 *  rather than out of the branches, and sorted by depth so a parent is always
 *  made before the folder that goes inside it.
 */

function vergeml_librarian_folder_order( $keys, $terms ) {

    $needed = array();

    foreach ( $keys as $key ) {

        $walked = array();

        foreach ( explode( ' / ', (string) $key ) as $step ) {
            $walked[] = $step;
            $needed[ implode( ' / ', $walked ) ] = true;
        }
    }

    $order = array();

    foreach ( array_keys( $needed ) as $key ) {
        // A folder that is already there needs no making, and putting it in
        // the list would only cost a lookup to discover that.
        if ( empty( $terms[ $key ]['id'] ) ) {
            $order[] = $key;
        }
    }

    usort( $order, 'vergeml_librarian_by_depth_asc' );

    return $order;
}


function vergeml_librarian_by_depth_asc( $a, $b ) {

    $depth_a = substr_count( (string) $a, ' / ' );
    $depth_b = substr_count( (string) $b, ' / ' );

    if ( $depth_a !== $depth_b ) {
        return $depth_a - $depth_b;
    }

    return strcmp( (string) $a, (string) $b );
}


/**
 *  How many files the next step takes.
 *
 *  Measured rather than chosen: the constant is a guess about somebody else's
 *  server, and the rate this host actually managed is not. Narrowed only --
 *  a fast host does not get a bigger chunk, because the browser is watching
 *  and a step that returns quickly is the point.
 */

function vergeml_librarian_chunk( $per_item_ms ) {

    $chunk = max( VERGEML_LIBRARIAN_CHUNK_MIN, (int) apply_filters( 'vergeml_librarian_chunk', VERGEML_LIBRARIAN_CHUNK ) );

    if ( $per_item_ms <= 0 ) {
        return $chunk;
    }

    $fits = (int) floor( VERGEML_LIBRARIAN_STEP_MS / $per_item_ms );

    return max( VERGEML_LIBRARIAN_CHUNK_MIN, min( $chunk, $fits ) );
}


/**
 *  vergeml_librarian_apply_step
 *
 *  One chunk of one batch: create the folders this chunk needs, file the
 *  files that are not filed already, and write down every assignment made.
 *
 *  Flat by construction. The work list was resolved when the batch was
 *  created, so a step reads a row, slices an array, and spends the rest of
 *  its budget on the files themselves -- it never walks the tree, never
 *  counts the branches, and costs the same on the fortieth step as on the
 *  first.
 */

function vergeml_librarian_apply_step( $batch_id ) {

    $started = microtime( true );

    $batch = vergeml_librarian_batch_get( $batch_id );

    if ( ! $batch ) {
        return new WP_Error( 'vergeml_librarian_no_batch', __( 'That batch is not on record.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    if ( in_array( $batch['status'], array( 'done', 'undone', 'undoing' ), true ) ) {
        return vergeml_librarian_report( $batch, $started );
    }

    $params = $batch['params'];
    $work   = isset( $params['work'] ) ? (array) $params['work'] : array();
    $total  = count( $work );

    /*
     *  The gate, at the top of every step and not only at the start. A credit
     *  balance can run out mid-batch, and the answer to that is to stop with
     *  a reason rather than to keep going because permission was granted a
     *  minute ago.
     */
    $gate = vergeml_librarian_gate( array(
        'scheme'   => $batch['scheme'],
        'batch_id' => $batch['batch_id'],
        'files'    => $total - (int) $batch['cursor'],
        'stage'    => 'step',
    ) );

    if ( ! $gate['allow'] ) {
        $batch['status'] = 'paused';
        $batch['reason'] = $gate['reason'];
        vergeml_librarian_batch_save( $batch );
        return vergeml_librarian_report( $batch, $started );
    }

    if ( 'paused' === $batch['status'] ) {
        // Resuming is what asking for a step on a paused batch means.
        $batch['status'] = 'running';
        $batch['reason'] = '';
    }

    $cursor = (int) $batch['cursor'];

    $phase = isset( $params['phase'] ) ? (string) $params['phase'] : 'files';

    if ( 'folders' !== $phase && $cursor >= $total ) {
        $batch['status'] = 'done';
        vergeml_librarian_batch_save( $batch );
        return vergeml_librarian_report( $batch, $started );
    }

    $taxonomy = isset( $params['taxonomy'] ) ? (string) $params['taxonomy'] : vergeml_librarian_taxonomy();
    $chunk    = vergeml_librarian_chunk( vergeml_librarian_rate( $params ) );

    /*
     *  The folders first, in a phase of their own.
     *
     *  Making them here rather than inside the filing loop is what keeps a
     *  filing step's cost flat: it costs the same whether the batch has two
     *  branches or two hundred, and the same on its fortieth step as on its
     *  first. This phase is bounded too -- at most a chunk of folders a step
     *  -- so no single request has to make an unbounded number of them.
     */
    if ( isset( $params['phase'] ) && 'folders' === $params['phase'] ) {

        $made = 0;

        while ( $params['folder_n'] < count( $params['folders'] ) && $made < $chunk ) {

            $key = (string) $params['folders'][ $params['folder_n'] ];

            vergeml_librarian_term_for( $key, $params, $taxonomy );

            $params['folder_n'] = (int) $params['folder_n'] + 1;
            $made++;
        }

        if ( $params['folder_n'] >= count( $params['folders'] ) ) {
            $params['phase'] = 'files';
        }

        $batch['params'] = $params;
        $batch['status'] = 'running';

        vergeml_librarian_batch_save( $batch );

        return vergeml_librarian_report( $batch, $started );
    }

    $slice = array_slice( $work, $cursor, $chunk );

    $ids = array();

    foreach ( $slice as $pair ) {
        $ids[] = (int) $pair[0];
    }

    /*
     *  Both caches primed in one go, so the loop below asks the database
     *  nothing it could have been told once. Without this every file costs a
     *  membership read of its own and the budget goes from two a file to
     *  four.
     */
    if ( $ids ) {
        update_object_term_cache( $ids, 'attachment' );
    }

    $moves = array();
    $done  = (int) $batch['done'];
    $skip  = (int) $batch['skipped'];

    wp_defer_term_counting( true );

    foreach ( $slice as $pair ) {

        $attachment_id = (int) $pair[0];
        $branch_index  = (int) $pair[1];

        if ( ! isset( $params['branches'][ $branch_index ] ) ) {
            $skip++;
            continue;
        }

        $branch = $params['branches'][ $branch_index ];
        $key    = implode( ' / ', $branch['path'] );

        $current = get_object_term_cache( $attachment_id, $taxonomy );

        if ( false === $current ) {
            $current = wp_get_object_terms( $attachment_id, $taxonomy, array( 'fields' => 'all_with_object_id' ) );
            $current = is_wp_error( $current ) ? array() : $current;
        }

        // Already filed: left exactly as it is, and counted. This is the
        // promise the whole feature rests on -- Apply adds folders to files
        // that have none, and touches nothing else.
        if ( ! empty( $current ) ) {
            $skip++;
            continue;
        }

        /*
         *  Looked up, never made. Every folder this batch needs was created
         *  in the phase before this one, and a step that could still insert a
         *  term is a step whose cost depends on how many branches its chunk
         *  happened to span.
         */
        $term_id = isset( $params['terms'][ $key ]['id'] ) ? (int) $params['terms'][ $key ]['id'] : 0;

        if ( ! $term_id ) {
            $skip++;
            continue;
        }

        $set = wp_set_object_terms( $attachment_id, array( (int) $term_id ), $taxonomy, false );

        if ( is_wp_error( $set ) ) {
            $skip++;
            continue;
        }

        $moves[] = array(
            (int) $batch['batch_id'],
            $attachment_id,
            (int) $term_id,
            (int) $params['terms'][ $key ]['created'],
        );

        $done++;
    }

    wp_defer_term_counting( false );

    if ( $moves ) {
        vergeml_librarian_moves_insert( $moves );
    }

    wp_cache_delete( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );

    $cursor += count( $slice );

    $elapsed = ( microtime( true ) - $started ) * 1000;

    $params['timing']['ms'] = (float) $params['timing']['ms'] + $elapsed;
    $params['timing']['n']  = (int) $params['timing']['n'] + count( $slice );
    $params['chunk']        = $chunk;

    $batch['params']  = $params;
    $batch['cursor']  = $cursor;
    $batch['done']    = $done;
    $batch['skipped'] = $skip;
    $batch['status']  = $cursor >= $total ? 'done' : 'running';

    vergeml_librarian_batch_save( $batch );

    if ( 'done' === $batch['status'] ) {
        vergeml_librarian_remember_rate( $params );
    }

    return vergeml_librarian_report( $batch, $started );
}


/**
 *  The folder a branch files into, created on the way if it is not there.
 *
 *  Ancestors first, because a term cannot be inserted under a parent that
 *  does not exist yet. Only the levels this chunk actually needs are created,
 *  so a batch of forty branches does not pay for forty folders on its first
 *  step -- which is what keeps a step's cost flat in the number of branches.
 */

function vergeml_librarian_term_for( $key, &$params, $taxonomy ) {

    if ( ! isset( $params['terms'][ $key ] ) ) {
        return 0;
    }

    if ( $params['terms'][ $key ]['id'] > 0 ) {
        return (int) $params['terms'][ $key ]['id'];
    }

    $parent_key = '';
    $at         = strrpos( $key, ' / ' );

    if ( false !== $at ) {
        $parent_key = substr( $key, 0, $at );
    }

    $parent = 0;

    if ( '' !== $parent_key ) {
        $parent = vergeml_librarian_term_for( $parent_key, $params, $taxonomy );

        if ( ! $parent ) {
            return 0;
        }
    }

    $name = (string) $params['terms'][ $key ]['name'];

    $term = wp_insert_term( $name, $taxonomy, array( 'parent' => (int) $parent ) );

    if ( is_wp_error( $term ) ) {

        /*
         *  The name is taken at this level. That is not a failure: the
         *  decision is that a collision reuses the folder somebody already
         *  has, so this batch adds to it and the moves log records that the
         *  folder was not created here -- which is what stops undo removing
         *  a folder it did not make.
         */
        $data = $term->get_error_data();

        $existing = 0;

        if ( is_array( $data ) && isset( $data['term_id'] ) ) {
            $existing = (int) $data['term_id'];
        } elseif ( is_numeric( $data ) ) {
            $existing = (int) $data;
        }

        if ( ! $existing ) {
            return 0;
        }

        $params['terms'][ $key ]['id']      = $existing;
        $params['terms'][ $key ]['created'] = 0;

        return $existing;
    }

    $params['terms'][ $key ]['id']      = (int) $term['term_id'];
    $params['terms'][ $key ]['created'] = 1;

    return (int) $term['term_id'];
}


/**
 *  One INSERT for the whole chunk.
 *
 *  Twenty-five inserts would be twenty-five queries against a budget that has
 *  to hold two per file for the assignment itself. The rows are small and
 *  identical in shape, which is exactly the case a multi-row insert is for.
 */

function vergeml_librarian_moves_insert( $moves ) {

    global $wpdb;

    $rows   = array();
    $values = array();

    foreach ( $moves as $move ) {
        $rows[]   = '(%d, %d, %d, %d, 0)';
        $values[] = (int) $move[0];
        $values[] = (int) $move[1];
        $values[] = (int) $move[2];
        $values[] = (int) $move[3];
    }

    /*
     *  The placeholder list is interpolated and the values still go through
     *  prepare, which is the same shape every IN clause in this plugin uses.
     *
     *  Handing prepare() a query it had been given in a variable is not: the
     *  sniffs cannot follow a string that was built elsewhere, so it reads as
     *  an unprepared query and Plugin Check refuses it -- and Plugin Check is
     *  the submission gate.
     */
    $placeholders = implode( ', ', $rows );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$wpdb->vergeml_librarian_moves}
             ( batch_id, attachment_id, term_id, term_created, undone )
         VALUES {$placeholders}",
        $values
    ) );
    // phpcs:enable
}


function vergeml_librarian_rate( $params ) {

    $n = isset( $params['timing']['n'] ) ? (int) $params['timing']['n'] : 0;

    if ( $n <= 0 ) {
        return 0.0;
    }

    return (float) $params['timing']['ms'] / $n;
}


/**
 *  What this host managed, kept for the next batch's pre-flight.
 *
 *  The estimate on the review screen has to come from somewhere, and the only
 *  honest somewhere is the last time this server did this work.
 */

function vergeml_librarian_remember_rate( $params ) {

    $rate = vergeml_librarian_rate( $params );

    if ( $rate <= 0 ) {
        return;
    }

    $state            = vergeml_librarian_state();
    $state['rate_ms'] = round( $rate, 4 );

    update_option( VERGEML_LIBRARIAN_OPTION, $state, false );
}


/* -------------------------------------------------------------------- undo */

/**
 *  vergeml_librarian_undo_step
 *
 *  One chunk of a batch, taken back.
 *
 *  Newest first, and only what the log says this batch did. Three things it
 *  refuses to do, each of them a way a naive undo destroys work:
 *
 *  - a file the user has moved since keeps its new folder, and is counted;
 *  - a file that has gone is stepped over rather than fataled on;
 *  - a folder this batch created is deleted only if it is empty afterwards,
 *    and a folder that was already there is never deleted at all.
 */

function vergeml_librarian_undo_step( $batch_id ) {

    $started = microtime( true );

    $batch = vergeml_librarian_batch_get( $batch_id );

    if ( ! $batch ) {
        return new WP_Error( 'vergeml_librarian_no_batch', __( 'That batch is not on record.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    if ( 'undone' === $batch['status'] ) {
        return vergeml_librarian_undo_report( $batch, 0, $started );
    }

    $params   = $batch['params'];
    $taxonomy = isset( $params['taxonomy'] ) ? (string) $params['taxonomy'] : vergeml_librarian_taxonomy();
    $undo     = isset( $params['undo'] ) ? (array) $params['undo'] : array( 'undone' => 0, 'touched' => 0, 'removed' => 0, 'kept' => 0 );

    $batch['status'] = 'undoing';

    $chunk = vergeml_librarian_chunk( vergeml_librarian_rate( $params ) );

    $rows = vergeml_librarian_moves_pending( (int) $batch['batch_id'], $chunk );

    if ( ! $rows ) {

        $undo = vergeml_librarian_undo_folders( $params, $taxonomy, $undo );

        $batch['status'] = 'undone';
        $params['undo']  = $undo;
        $batch['params'] = $params;

        vergeml_librarian_batch_save( $batch );

        wp_cache_delete( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );

        return vergeml_librarian_undo_report( $batch, 0, $started );
    }

    $ids = array();

    foreach ( $rows as $row ) {
        $ids[] = (int) $row['attachment_id'];
    }

    update_object_term_cache( $ids, 'attachment' );

    $done_ids = array();

    wp_defer_term_counting( true );

    foreach ( $rows as $row ) {

        $attachment_id = (int) $row['attachment_id'];
        $term_id       = (int) $row['term_id'];

        $done_ids[] = (int) $row['move_id'];

        $current = get_object_term_cache( $attachment_id, $taxonomy );

        if ( false === $current ) {
            $current = wp_get_object_terms( $attachment_id, $taxonomy, array( 'fields' => 'all_with_object_id' ) );
            $current = is_wp_error( $current ) ? array() : $current;
        }

        $held = array();

        foreach ( (array) $current as $term ) {
            $held[] = (int) $term->term_id;
        }

        // Moved, or gone. Either way this is not ours to take back, and the
        // person is told rather than the difference being quietly absorbed.
        if ( ! in_array( $term_id, $held, true ) ) {
            $undo['touched'] = (int) $undo['touched'] + 1;
            continue;
        }

        wp_remove_object_terms( $attachment_id, array( $term_id ), $taxonomy );

        $undo['undone'] = (int) $undo['undone'] + 1;
    }

    wp_defer_term_counting( false );

    if ( $done_ids ) {
        vergeml_librarian_moves_mark( $done_ids );
    }

    wp_cache_delete( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );

    $params['undo']  = $undo;
    $batch['params'] = $params;

    vergeml_librarian_batch_save( $batch );

    return vergeml_librarian_undo_report( $batch, count( $rows ), $started );
}


/**
 *  The folders this batch created, removed if they are empty afterwards.
 *
 *  Once, on the step that finds nothing left to take back, rather than per
 *  chunk -- and over the batch's own record of what it created rather than
 *  over the folders the moves happen to name. Those are different sets, and
 *  the difference is a leak: a branch two levels deep makes its parent as
 *  well, no move ever names that parent, and a sweep driven by the moves
 *  leaves it behind empty for ever.
 *
 *  Deepest first, so a parent is judged after the children that would have
 *  kept it alive have already gone.
 *
 *  A folder that has picked up content since -- a manual drag, an upload
 *  filed into it -- is kept and counted, because deleting it would take
 *  somebody else's work with it. So is one that still has children: those are
 *  folders too, and they are not this batch's to judge.
 */

function vergeml_librarian_undo_folders( $params, $taxonomy, $undo ) {

    $terms = isset( $params['terms'] ) ? (array) $params['terms'] : array();

    $mine = array();

    foreach ( $terms as $key => $term ) {
        if ( ! empty( $term['created'] ) && ! empty( $term['id'] ) ) {
            $mine[ (string) $key ] = (int) $term['id'];
        }
    }

    if ( ! $mine ) {
        return $undo;
    }

    // By depth, deepest first. The key is the path, so its depth is how many
    // separators it carries.
    $keys = array_keys( $mine );

    usort( $keys, 'vergeml_librarian_by_depth' );

    $held = vergeml_librarian_term_objects( array_values( $mine ), $taxonomy );

    foreach ( $keys as $key ) {

        $term_id = $mine[ $key ];

        if ( ! get_term( $term_id, $taxonomy ) instanceof WP_Term ) {
            continue;
        }

        $children = get_term_children( $term_id, $taxonomy );
        $children = is_wp_error( $children ) ? array() : $children;

        $in_it = isset( $held[ $term_id ] ) ? (int) $held[ $term_id ] : 0;

        if ( $in_it > 0 || $children ) {
            $undo['kept'] = (int) $undo['kept'] + 1;
            continue;
        }

        wp_delete_term( $term_id, $taxonomy );

        $undo['removed'] = (int) $undo['removed'] + 1;
    }

    return $undo;
}


function vergeml_librarian_by_depth( $a, $b ) {

    $depth_a = substr_count( (string) $a, ' / ' );
    $depth_b = substr_count( (string) $b, ' / ' );

    if ( $depth_a !== $depth_b ) {
        return $depth_b - $depth_a;
    }

    return strcmp( (string) $b, (string) $a );
}


/**
 *  The moves still to take back, newest first.
 */

function vergeml_librarian_moves_pending( $batch_id, $limit ) {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT move_id, attachment_id, term_id, term_created
           FROM {$wpdb->vergeml_librarian_moves}
          WHERE batch_id = %d AND undone = 0
          ORDER BY move_id DESC
          LIMIT %d",
        (int) $batch_id,
        (int) $limit
    ), ARRAY_A );
    // phpcs:enable
}


/**
 *  How many things are in these folders, asked of the database.
 *
 *  Not $term->count, and this is not fussiness: on this plugin's own test box
 *  a term holding three attachments reported a count of zero, because the
 *  cached figure is maintained by a callback that does not always run for
 *  attachments and is stale whenever it does not. Undo deletes folders. A
 *  destructive decision taken from a cached number that can be wrong is how
 *  somebody's folder disappears with their files' assignments inside it --
 *  which is the exact failure this whole feature exists to make impossible.
 *
 *  One query for the whole chunk, so asking honestly costs nothing.
 */

function vergeml_librarian_term_objects( $term_ids, $taxonomy ) {

    global $wpdb;

    $term_ids = array_values( array_unique( array_map( 'intval', (array) $term_ids ) ) );

    if ( ! $term_ids ) {
        return array();
    }

    $placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    $rows = (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT tt.term_id AS term_id, COUNT( tr.object_id ) AS held
           FROM {$wpdb->term_taxonomy} tt
           LEFT JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
          WHERE tt.taxonomy = %s AND tt.term_id IN ( $placeholders )
          GROUP BY tt.term_id",
        array_merge( array( $taxonomy ), $term_ids )
    ), ARRAY_A );
    // phpcs:enable

    $out = array();

    foreach ( $rows as $row ) {
        $out[ (int) $row['term_id'] ] = (int) $row['held'];
    }

    return $out;
}


function vergeml_librarian_moves_mark( $move_ids ) {

    global $wpdb;

    $move_ids = array_map( 'intval', (array) $move_ids );

    if ( ! $move_ids ) {
        return;
    }

    $placeholders = implode( ',', array_fill( 0, count( $move_ids ), '%d' ) );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->vergeml_librarian_moves} SET undone = 1 WHERE move_id IN ( $placeholders )",
        $move_ids
    ) );
    // phpcs:enable
}


/* ----------------------------------------------------------------- reports */

function vergeml_librarian_report( $batch, $started ) {

    $params    = $batch['params'];
    $total     = isset( $params['n'] ) ? (int) $params['n'] : 0;
    $remaining = max( 0, $total - (int) $batch['cursor'] );

    return array(
        'batch_id'  => (int) $batch['batch_id'],
        'run_id'    => (int) $batch['run_id'],
        'scheme'    => (string) $batch['scheme'],
        'status'    => (string) $batch['status'],
        'done'      => (int) $batch['done'],
        'skipped'   => (int) $batch['skipped'],
        'cursor'    => (int) $batch['cursor'],
        'n'         => $total,
        'remaining' => $remaining,
        'phase'     => isset( $params['phase'] ) ? (string) $params['phase'] : 'files',
        'folders'   => array(
            'made'   => isset( $params['folder_n'] ) ? (int) $params['folder_n'] : 0,
            'needed' => isset( $params['folders'] ) ? count( $params['folders'] ) : 0,
        ),
        'chunk'     => isset( $params['chunk'] ) ? (int) $params['chunk'] : VERGEML_LIBRARIAN_CHUNK,
        'reason'    => (string) $batch['reason'],
        'step_ms'   => round( ( microtime( true ) - $started ) * 1000, 1 ),
        'estimate'  => vergeml_organize_estimate(
            isset( $params['timing']['n'] ) ? (int) $params['timing']['n'] : 0,
            isset( $params['timing']['ms'] ) ? (float) $params['timing']['ms'] : 0.0,
            $remaining
        ),
    );
}


function vergeml_librarian_undo_report( $batch, $walked, $started ) {

    $params = $batch['params'];
    $undo   = isset( $params['undo'] ) ? (array) $params['undo'] : array();

    $undone  = isset( $undo['undone'] ) ? (int) $undo['undone'] : 0;
    $touched = isset( $undo['touched'] ) ? (int) $undo['touched'] : 0;

    $total = isset( $params['n'] ) ? (int) $params['n'] : 0;

    return array(
        'batch_id'        => (int) $batch['batch_id'],
        'status'          => (string) $batch['status'],
        'undone'          => $undone,
        'skipped_touched' => $touched,
        'folders_removed' => isset( $undo['removed'] ) ? (int) $undo['removed'] : 0,
        'folders_kept'    => isset( $undo['kept'] ) ? (int) $undo['kept'] : 0,
        'remaining'       => max( 0, (int) $batch['done'] - $undone - $touched ),
        'n'               => $total,
        'walked'          => (int) $walked,
        'step_ms'         => round( ( microtime( true ) - $started ) * 1000, 1 ),
    );
}


/**
 *  vergeml_librarian_batches
 *
 *  The last few batches, for the history list and its Undo buttons. One
 *  query: every count the list shows is already a column on the row.
 */

function vergeml_librarian_batches( $limit = VERGEML_LIBRARIAN_KEEP ) {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT batch_id, run_id, scheme, status, step_cursor, done_n, skip_n, reason, created_at, updated_at
           FROM {$wpdb->vergeml_librarian_batches}
          ORDER BY batch_id DESC
          LIMIT %d",
        (int) $limit
    ), ARRAY_A );
    // phpcs:enable

    $out = array();

    foreach ( $rows as $row ) {
        $out[] = array(
            'batch_id'   => (int) $row['batch_id'],
            'run_id'     => (int) $row['run_id'],
            'scheme'     => (string) $row['scheme'],
            'status'     => (string) $row['status'],
            'cursor'     => (int) $row['step_cursor'],
            'done'       => (int) $row['done_n'],
            'skipped'    => (int) $row['skip_n'],
            'reason'     => (string) $row['reason'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        );
    }

    return $out;
}


/* ----------------------------------------------------------------- pruning */

/**
 *  vergeml_librarian_prune
 *
 *  Keep the last few batches and their moves; drop the rest.
 *
 *  The only destructive act in this file that is not somebody pressing Undo,
 *  and it touches exactly two tables -- both of them ours. Never posts, never
 *  terms, never a relationship: a pruned batch is a batch that can no longer
 *  be undone, which is a smaller loss than a batch whose undo deleted a
 *  folder somebody had started using.
 */

function vergeml_librarian_prune( $keep = VERGEML_LIBRARIAN_KEEP ) {

    global $wpdb;

    $keep = max( 1, (int) $keep );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $cutoff = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT batch_id FROM {$wpdb->vergeml_librarian_batches} ORDER BY batch_id DESC LIMIT %d, 1",
        $keep
    ) );

    if ( $cutoff <= 0 ) {
        return 0;
    }

    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->vergeml_librarian_moves} WHERE batch_id <= %d",
        $cutoff
    ) );

    return (int) $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->vergeml_librarian_batches} WHERE batch_id <= %d",
        $cutoff
    ) );
    // phpcs:enable
}


/* --------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_librarian_routes' );

function vergeml_librarian_routes() {

    $can = function () {
        return current_user_can( 'manage_categories' );
    };

    register_rest_route( VERGEML_REST_NS, '/librarian-schemes', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_librarian_rest_schemes',
        'args'                => array(
            // Naming one asks for its whole tree as well as its summary: the
            // review screen needs the members, and the date scheme has no run
            // row to fetch them from.
            'scheme' => array( 'type' => 'string', 'default' => '' ),
        ),
    ) );

    /*
     *  Readable, and creatable as well.
     *
     *  It is a read -- it changes nothing — but its input is the whole review
     *  screen: every branch, its rename and whether it is in. Forty of those
     *  in a query string is a URL long enough for a proxy to refuse, so the
     *  screen POSTs the same question and gets the same answer.
     */
    register_rest_route( VERGEML_REST_NS, '/librarian-preflight', array(
        'methods'             => 'GET, POST',
        'permission_callback' => $can,
        'callback'            => 'vergeml_librarian_rest_preflight',
        'args'                => array(
            'scheme'   => array( 'type' => 'string', 'default' => 'subject' ),
            'run_id'   => array( 'type' => 'integer', 'default' => 0 ),
            'branches' => array( 'default' => array() ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/librarian-apply-step', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_librarian_rest_apply',
        'args'                => array(
            'batch_id' => array( 'type' => 'integer', 'default' => 0 ),
            'scheme'   => array( 'type' => 'string', 'default' => 'subject' ),
            'run_id'   => array( 'type' => 'integer', 'default' => 0 ),
            'branches' => array( 'default' => array() ),
        ),
    ) );

    /*
     *  Its own endpoint for the reason organize-cancel is one: a pause that
     *  had to wait for the step it was pausing would not be a pause.
     */
    register_rest_route( VERGEML_REST_NS, '/librarian-pause', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_librarian_rest_pause',
        'args'                => array(
            'batch_id' => array( 'type' => 'integer', 'required' => true ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/librarian-undo-step', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_librarian_rest_undo',
        'args'                => array(
            'batch_id' => array( 'type' => 'integer', 'required' => true ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/librarian-batches', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => $can,
        'callback'            => 'vergeml_librarian_rest_batches',
    ) );
}


/**
 *  Both schemes, as much of them as was asked for.
 *
 *  A summary each -- what the top folders would be called and how big they
 *  are -- so the chooser can show two cards without loading two whole trees,
 *  and the full tree only for the one somebody picked.
 */

function vergeml_librarian_rest_schemes( WP_REST_Request $request ) {

    $wanted = (string) $request->get_param( 'scheme' );

    $run = vergeml_librarian_latest_done_run();

    $subject = ( $run && ! empty( $run['tree'] ) ) ? $run['tree'] : array();

    $datetype = ( 'datetype' === $wanted || ! $subject ) ? vergeml_librarian_scheme_datetype() : null;

    $out = array(
        'taxonomy' => vergeml_librarian_taxonomy(),
        'schemes'  => array(
            array(
                'id'      => 'subject',
                'label'   => __( 'By subject', 'vergelabs-media-library' ),
                'note'    => __( 'What the pictures are of, grouped by how they look and named from what they share. Needs a finished proposal.', 'vergelabs-media-library' ),
                'run_id'  => $run ? (int) $run['run_id'] : null,
                'ready'   => (bool) $subject,
                'total'   => vergeml_librarian_scheme_total( $subject ),
                'top'     => vergeml_librarian_scheme_top( $subject ),
            ),
            array(
                'id'      => 'datetype',
                'label'   => __( 'By date', 'vergelabs-media-library' ),
                'note'    => __( 'Year and month, from when each file was uploaded, with what kind of file it is on every line. No model call, no credit.', 'vergelabs-media-library' ),
                'run_id'  => null,
                'ready'   => true,
                'total'   => null === $datetype ? null : vergeml_librarian_scheme_total( $datetype ),
                'top'     => null === $datetype ? array() : vergeml_librarian_scheme_top( $datetype ),
            ),
        ),
    );

    if ( 'subject' === $wanted ) {
        $out['tree']   = $subject;
        $out['scheme'] = 'subject';
        $out['run_id'] = $run ? (int) $run['run_id'] : 0;
    } elseif ( 'datetype' === $wanted ) {
        $out['tree']   = null === $datetype ? vergeml_librarian_scheme_datetype() : $datetype;
        $out['scheme'] = 'datetype';
        $out['run_id'] = 0;
    }

    return rest_ensure_response( $out );
}


function vergeml_librarian_scheme_total( $tree ) {

    $total = 0;

    foreach ( (array) $tree as $branch ) {
        $total += isset( $branch['size'] ) ? (int) $branch['size'] : 0;
    }

    return $total;
}


/**
 *  The handful of folders a scheme card shows. Label and size only -- a card
 *  that listed every branch would be the review screen with none of its
 *  controls.
 */

function vergeml_librarian_scheme_top( $tree, $take = 5 ) {

    $top = array();

    foreach ( (array) $tree as $branch ) {

        if ( count( $top ) >= $take ) {
            break;
        }

        if ( empty( $branch['size'] ) ) {
            continue;
        }

        $top[] = array(
            'key'     => isset( $branch['key'] ) ? (string) $branch['key'] : '',
            'label'   => isset( $branch['label'] ) ? (string) $branch['label'] : '',
            'size'    => (int) $branch['size'],
            'flagged' => vergeml_librarian_flagged( $branch ),
        );
    }

    return $top;
}


/**
 *  The branches a caller named, however they were sent.
 *
 *  A POST carries them as an array; a GET can only carry them as a string, so
 *  a JSON one is accepted too. Both end up as the same list, and neither is
 *  trusted further than key, label and a boolean.
 */

function vergeml_librarian_branch_arg( $value ) {

    if ( is_string( $value ) ) {
        $decoded = json_decode( $value, true );
        $value   = is_array( $decoded ) ? $decoded : array();
    }

    return is_array( $value ) ? $value : array();
}


function vergeml_librarian_rest_preflight( WP_REST_Request $request ) {

    $preflight = vergeml_librarian_preflight(
        (string) $request->get_param( 'scheme' ),
        (int) $request->get_param( 'run_id' ),
        vergeml_librarian_branch_arg( $request->get_param( 'branches' ) )
    );

    return is_wp_error( $preflight ) ? $preflight : rest_ensure_response( $preflight );
}


function vergeml_librarian_rest_apply( WP_REST_Request $request ) {

    $batch_id = (int) $request->get_param( 'batch_id' );

    if ( $batch_id <= 0 ) {

        $batch = vergeml_librarian_batch_create(
            (string) $request->get_param( 'scheme' ),
            (int) $request->get_param( 'run_id' ),
            vergeml_librarian_branch_arg( $request->get_param( 'branches' ) )
        );

        if ( is_wp_error( $batch ) ) {
            return $batch;
        }

        $batch_id = (int) $batch['batch_id'];

        // A batch the gate refused is created and paused, not stepped: the
        // reason is what the screen has to show, and stepping it would only
        // ask the same question again.
        if ( 'paused' === $batch['status'] ) {
            return rest_ensure_response( vergeml_librarian_report( $batch, microtime( true ) ) );
        }
    }

    $result = vergeml_librarian_apply_step( $batch_id );

    return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
}


function vergeml_librarian_rest_pause( WP_REST_Request $request ) {

    $batch = vergeml_librarian_batch_get( (int) $request->get_param( 'batch_id' ) );

    if ( ! $batch ) {
        return new WP_Error( 'vergeml_librarian_no_batch', __( 'That batch is not on record.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    if ( 'running' === $batch['status'] ) {
        $batch['status'] = 'paused';
        $batch['reason'] = __( 'Paused here.', 'vergelabs-media-library' );
        vergeml_librarian_batch_save( $batch );
    }

    return rest_ensure_response( vergeml_librarian_report( $batch, microtime( true ) ) );
}


function vergeml_librarian_rest_undo( WP_REST_Request $request ) {

    $result = vergeml_librarian_undo_step( (int) $request->get_param( 'batch_id' ) );

    return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
}


function vergeml_librarian_rest_batches() {
    return rest_ensure_response( array( 'batches' => vergeml_librarian_batches() ) );
}
/* ------------------------------------------------------------------- screen */

/**
 *  Where this library is on the ladder.
 *
 *  Four states, and each of them is a thing the screen can start in place
 *  rather than a message pointing at another page. The home card says which
 *  one applies so somebody can see whether there is anything to do here
 *  before clicking.
 */

function vergeml_librarian_stage() {

    $health = function_exists( 'vergeml_health_state' ) ? vergeml_health_state() : array();

    if ( empty( $health['finished'] ) ) {
        return 'unscanned';
    }

    $run = vergeml_librarian_latest_done_run();

    if ( $run && ! empty( $run['tree'] ) ) {
        return 'ready';
    }

    return function_exists( 'vergeml_organize_count' ) && vergeml_organize_count() > 0
        ? 'unproposed'
        : 'unindexed';
}


function vergeml_librarian_card_text() {

    $stage = vergeml_librarian_stage();

    if ( 'unscanned' === $stage ) {
        return __( 'See the folders this library would get. The duplicate scan has to run first — the Librarian starts it for you.', 'vergelabs-media-library' );
    }

    if ( 'unindexed' === $stage ) {
        return __( 'See the folders this library would get. Your files need describing first — the Librarian starts that for you.', 'vergelabs-media-library' );
    }

    if ( 'unproposed' === $stage ) {
        return __( 'Your files are described. Propose a folder tree, look it over, and apply it — or file by date instead, which costs nothing.', 'vergelabs-media-library' );
    }

    return __( 'A proposal is waiting. Look it over branch by branch, apply it in one go, and put it all back with one click if you regret it.', 'vergelabs-media-library' );
}


add_action( 'admin_menu', 'vergeml_librarian_menu', 13 );

function vergeml_librarian_menu() {

    if ( ! defined( 'VERGEML_MENU' ) ) {
        return;
    }

    add_submenu_page(
        VERGEML_MENU,
        __( 'Librarian', 'vergelabs-media-library' ),
        __( 'Librarian', 'vergelabs-media-library' ),
        'manage_categories',
        'media-librarian',
        'vergeml_librarian_page'
    );
}


add_action( 'admin_enqueue_scripts', 'vergeml_librarian_assets' );

function vergeml_librarian_assets( $hook ) {

    if ( false === strpos( (string) $hook, 'media-librarian' ) ) {
        return;
    }

    wp_enqueue_script(
        'vergeml-librarian',
        plugins_url( 'js/vergeml-librarian.js', VERGEML_FILE ),
        array( 'wp-api-fetch' ),
        vergeml_asset_ver( 'js/vergeml-librarian.js' ),
        true
    );

    wp_enqueue_style(
        'vergeml-admin',
        plugins_url( 'css/vergeml-admin.css', VERGEML_FILE ),
        array(),
        vergeml_asset_ver( 'css/vergeml-admin.css' )
    );

    wp_enqueue_style(
        'vergeml-librarian',
        plugins_url( 'css/vergeml-librarian.css', VERGEML_FILE ),
        array( 'vergeml-admin' ),
        vergeml_asset_ver( 'css/vergeml-librarian.css' )
    );

    wp_localize_script( 'vergeml-librarian', 'vergemlLibrarian', array(
        'stage'   => vergeml_librarian_stage(),
        'samples' => VERGEML_LIBRARIAN_SAMPLES,
        'l10n'    => array(
            /* the ladder */
            'ladderScan'      => __( 'Read the library first', 'vergelabs-media-library' ),
            'ladderScanNote'  => __( 'Reading every file once tells the Librarian which files are copies of each other, so nothing is described — or counted — twice. It changes nothing.', 'vergelabs-media-library' ),
            'ladderScanGo'    => __( 'Scan the library', 'vergelabs-media-library' ),
            'ladderIndex'     => __( 'Describe the pictures', 'vergelabs-media-library' ),
            'ladderIndexNote' => __( 'The subject tree is built from what the pictures show, so they have to be described before there is anything to group. Filing by date needs none of this.', 'vergelabs-media-library' ),
            'ladderIndexGo'   => __( 'Describe them', 'vergelabs-media-library' ),
            'ladderRun'       => __( 'Propose a tree', 'vergelabs-media-library' ),
            'ladderRunNote'   => __( 'Group the described files into folders. Nothing is filed by proposing — the proposal is a document you look at.', 'vergelabs-media-library' ),
            'ladderRunGo'     => __( 'Propose a tree', 'vergelabs-media-library' ),
            'ladderCancel'    => __( 'Stop', 'vergelabs-media-library' ),
            'skipToDate'      => __( 'Or file by date instead — no describing, no credits.', 'vergelabs-media-library' ),

            /* the chooser */
            'chooser'         => __( 'How should we sort them?', 'vergelabs-media-library' ),
            'choose'          => __( 'See the plan', 'vergelabs-media-library' ),
            'notReady'        => __( 'Not available yet', 'vergelabs-media-library' ),
            /* translators: %s: how many files a scheme would file. */
            'schemeTotal'     => __( '%s files', 'vergelabs-media-library' ),

            /* the review */
            'review'          => __( 'The proposed folders', 'vergelabs-media-library' ),
            'back'            => __( 'Choose a different scheme', 'vergelabs-media-library' ),
            /* translators: %s: number of files in a folder. */
            'branchSize'      => __( '%s files', 'vergelabs-media-library' ),
            'rename'          => __( 'Folder name', 'vergelabs-media-library' ),
            'refuse'          => __( 'Not this one', 'vergelabs-media-library' ),
            'flagged'         => __( 'Worth a look before you agree to this one.', 'vergelabs-media-library' ),
            'capped'          => __( 'This one hit the depth limit, so it holds more than it otherwise would.', 'vergelabs-media-library' ),
            'agreement'       => __( 'How closely the files in this folder resemble each other', 'vergelabs-media-library' ),
            'close'           => __( 'close', 'vergelabs-media-library' ),
            'mid'             => __( 'middling', 'vergelabs-media-library' ),
            'far'             => __( 'loose', 'vergelabs-media-library' ),

            /* the pre-flight */
            'preflight'       => __( 'What Apply would do', 'vergelabs-media-library' ),
            /* translators: 1: files to be filed, 2: files left alone. */
            'preflightFiles'  => __( '%1$s files filed · %2$s left alone because they are already in a folder', 'vergelabs-media-library' ),
            /* translators: 1: folders created, 2: existing folders added to. */
            'preflightFolders' => __( '%1$s folders created · %2$s existing folders added to', 'vergelabs-media-library' ),
            /* translators: %s: a duration, e.g. "about 2 minutes". */
            'preflightTime'   => __( 'About %s', 'vergelabs-media-library' ),
            'preflightNoTime' => __( 'How long this takes is not known until the first files have gone through.', 'vergelabs-media-library' ),
            'credits'         => __( 'Costs 0 (mock) — the service is not live, so nothing is spent and nothing is guessed at.', 'vergelabs-media-library' ),
            'apply'           => __( 'Apply', 'vergelabs-media-library' ),
            'applyNothing'    => __( 'Nothing is selected.', 'vergelabs-media-library' ),

            /* translators: 1: files with no folder yet, 2: how many folders exist. */
            'counts'          => __( '%1$s files to file · %2$s folders', 'vergelabs-media-library' ),

            /* applying */
            'applying'        => __( 'Filing…', 'vergelabs-media-library' ),
            'pause'           => __( 'Pause', 'vergelabs-media-library' ),
            'resume'          => __( 'Resume', 'vergelabs-media-library' ),
            'paused'          => __( 'Paused.', 'vergelabs-media-library' ),
            /* translators: 1: files filed, 2: files skipped. */
            'applied'         => __( 'Done. %1$s files filed, %2$s left alone.', 'vergelabs-media-library' ),
            /* translators: %s: number of files still to go. */
            'remaining'       => __( '%s to go', 'vergelabs-media-library' ),

            /* undo */
            'history'         => __( 'What you have already sorted', 'vergelabs-media-library' ),
            /* translators: 1: files sorted, 2: the scheme, 3: files left alone. */
            'batchLine'       => __( 'Sorted %1$s files %2$s. %3$s were left where they were.', 'vergelabs-media-library' ),
            'schemeDate'      => __( 'by date and file type', 'vergelabs-media-library' ),
            'schemeSubject'   => __( 'by what is in the pictures', 'vergelabs-media-library' ),
            'wasUndone'       => __( 'Undone', 'vergelabs-media-library' ),
            'justNow'         => __( 'just now', 'vergelabs-media-library' ),
            /* translators: %s: a number of minutes. */
            'minsAgo'         => __( '%s minutes ago', 'vergelabs-media-library' ),
            /* translators: %s: a number of hours. */
            'hoursAgo'        => __( '%s hours ago', 'vergelabs-media-library' ),
            /* translators: %s: a number of days. */
            'daysAgo'         => __( '%s days ago', 'vergelabs-media-library' ),
            'noHistory'       => __( 'Nothing has been applied yet.', 'vergelabs-media-library' ),
            'undo'            => __( 'Undo', 'vergelabs-media-library' ),
            /* translators: 1: files filed by a batch, 2: files it left alone. */
            'batchCount'      => __( '%1$s filed · %2$s left alone', 'vergelabs-media-library' ),
            'undoing'         => __( 'Putting it back…', 'vergelabs-media-library' ),
            /* translators: 1: assignments removed, 2: folders removed. */
            'undone'          => __( 'Put back. %1$s files unfiled, %2$s folders removed.', 'vergelabs-media-library' ),
            /* translators: %s: number of files the user has moved since. */
            'undoTouched'     => __( '%s files were left as they are — you have moved or deleted them since, and they are yours now.', 'vergelabs-media-library' ),
            /* translators: %s: number of folders kept. */
            'undoKept'        => __( '%s folders were kept because other files have been put in them since.', 'vergelabs-media-library' ),

            'failed'          => __( 'That did not work, and nothing was changed.', 'vergelabs-media-library' ),
        ),
    ) );
}


function vergeml_librarian_page() {

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }

    /*
     *  The accent of whichever admin colour scheme this user picked, handed to
     *  the stylesheet as a custom property. The folder tree has done this since
     *  it shipped; this screen hardcoded WordPress's default blue instead, so
     *  it was the one screen that did not belong to the admin it sat in.
     */
    /*
     *  Our accent, not WordPress's.
     *
     *  This used to take the colour from the admin's own scheme, on the
     *  reasoning that a screen should belong to the admin it sits in. That was
     *  right when this was one page among WordPress's. It is wrong now the
     *  plugin has its own shell in its own palette: the result was a Librarian
     *  with blue buttons sitting inside a rust-coloured application.
     *
     *  Empty means "use whatever .vgml-shell already defines", which is the
     *  one place the palette lives.
     */
    $accent = '';

    ?>
    <div class="wrap vgml-home vgml-librarian"<?php echo '' !== $accent ? ' style="--vgml-accent: ' . esc_attr( $accent ) . '"' : ''; ?>>

        <div class="vgml-home-head">
            <h1><?php esc_html_e( 'Librarian', 'vergelabs-media-library' ); ?></h1>
            <p class="vgml-home-counts" id="vgml-lib-counts"><?php esc_html_e( 'Loading…', 'vergelabs-media-library' ); ?></p>
        </div>

        <?php
        /*
         *  One container, four states, drawn by the script from endpoints that
         *  already exist. Nothing is rendered here, because a server-rendered
         *  first state and a script-rendered second one is two answers to the
         *  same question that can disagree.
         */
        ?>
        <div id="vgml-lib-stage"></div>
        <div id="vgml-lib-review"></div>
        <div id="vgml-lib-history"></div>

        <?php
        /*
         *  Where the other filing tools go.
         *
         *  "File what is still loose" and "Say what you want" both put files
         *  into folders, and both were on the AI screen -- next to the licence
         *  key and the credit balance, which they have nothing to do with. The
         *  AI screen was six unrelated sections stacked; this is where two of
         *  them belong.
         */
        do_action( 'vergeml_librarian_page_cards' );
        ?>

    </div>
    <?php
}
