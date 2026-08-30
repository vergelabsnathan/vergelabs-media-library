<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  A proposed tree, stored as data.
 *
 *  Phase 1 gave every image a caption, four enum attributes and an embedding,
 *  and nothing read any of it. This is the first thing that does: it clusters
 *  the stored vectors into a folder structure, names each branch from the tags
 *  its members share, writes one line of reason per file, and persists the
 *  whole thing as a row.
 *
 *  It proposes. It never writes a taxonomy term and never moves a file --
 *  applying is Phase 3, behind the undo log. The only rows this file writes are
 *  its own.
 *
 *  Three things the spike (tools/organize-spike.php) measured, and which shape
 *  everything below:
 *
 *  1. **Seeding dominated.** k-means++ farthest-point seeding is O(n·k²) and
 *     cost 36.6 of the 46.7 seconds at ten thousand files. So it runs over a
 *     deterministic sample capped at VERGEML_ORGANIZE_SEED_SAMPLE points --
 *     every point is still assigned, but the quadratic search never sees more
 *     than two thousand.
 *  2. **Dimensions cost linearly, and memory is the harder wall.** Two thousand
 *     vectors at 768 dimensions is 39MB as PHP arrays; ten thousand would be
 *     around 196MB, against the 128MB a lot of shared hosting allows. So
 *     clustering runs on a projection down to VERGEML_ORGANIZE_DIMS, fixed and
 *     seedless so two runs -- on two different sites -- stay comparable.
 *  3. **A single up-front k produces a catch-all.** At k = sqrt(n/2) the test
 *     library put 35 of 58 files in one branch and named it after an arbitrary
 *     member. So k is not chosen: the library is split into a handful of
 *     groups and any group still too big is split again. The only number
 *     anyone sets is how big a folder is allowed to get.
 *
 *  Determinism is load-bearing. Two runs over the same library must produce
 *  the same tree or "runs produce diffs" means nothing, because every diff
 *  would be noise. There is no rand() here, no shuffle(), and no iteration
 *  over an order that depends on hashing: points are walked in attachment_id
 *  order and ties break to the lower id.
 *
 *  @since 3.3
 */


const VERGEML_ORGANIZE_TABLE   = 'vergeml_organize_runs';
const VERGEML_ORGANIZE_VERSION = 1;
const VERGEML_ORGANIZE_OPTION  = 'vergeml_organize';

/*
 *  The projection target. Forced by measurement rather than taste: at 768
 *  dimensions a ten-thousand-file library is nine minutes of arithmetic and
 *  ~196MB of PHP arrays, and this plugin's premise is shared hosting.
 */
const VERGEML_ORGANIZE_DIMS = 64;

// The quadratic part of seeding only ever sees this many points.
const VERGEML_ORGANIZE_SEED_SAMPLE = 2000;

// Split a branch above this; never make one below it; never go deeper than
// this. Nobody wants Products -> Boots -> Navy -> Left-facing.
const VERGEML_ORGANIZE_MAX_BRANCH = 50;
const VERGEML_ORGANIZE_MIN_BRANCH = 5;
const VERGEML_ORGANIZE_MAX_DEPTH  = 3;

// How many groups one split produces. Five or six is a number a person can
// take in at once, which is the entire justification for it.
const VERGEML_ORGANIZE_WIDTH = 6;

// A member this many times the branch's median distance from the centre is not
// really in the branch. It goes to "Needs a look" rather than being forced.
const VERGEML_ORGANIZE_OUTLIER = 3.0;

/*
 *  How much of a branch has to carry a tag before it can be the branch's name.
 *
 *  Half. A name is a claim about the whole folder, so a word a minority of the
 *  files carry is not one -- see vergeml_organize_label for what happened
 *  without this.
 */
const VERGEML_ORGANIZE_NAME_SHARE = 0.5;

/*
 *  How much of the LIBRARY a tag can be on and still be a folder name.
 *
 *  Two fifths. Above that it is not describing this folder, it is describing
 *  the library -- and a folder called what everything is called tells the
 *  reader nothing.
 *
 *  On five hundred photographs "photo" was on nearly all of them, and it won
 *  six folders: "Photo and workspace", "Photo and plant", "Photo and food",
 *  and three called simply "Photo". Each was a perfectly good cluster --
 *  desks, cacti, fruit -- wearing the one word they had in common with
 *  everything else in the library.
 */
const VERGEML_ORGANIZE_NAME_CEILING = 0.4;

const VERGEML_ORGANIZE_ITERATIONS = 25;

// Runs kept per site. Task 14, and the only destructive act in this phase --
// its own rows, never media.
const VERGEML_ORGANIZE_KEEP = 10;

/*
 *  Where a step starts, and what it aims at. The batch is adjusted from the
 *  rate actually measured on this host: shared hosts time out at thirty
 *  seconds and the browser drives the loop, so the step size is the safety
 *  valve and a constant chosen here would be a guess about somebody else's
 *  server.
 */
const VERGEML_ORGANIZE_BATCH    = 200;
const VERGEML_ORGANIZE_BATCH_MIN = 50;
const VERGEML_ORGANIZE_BATCH_MAX = 2000;
const VERGEML_ORGANIZE_STEP_MS  = 2500;

/*
 *  Bytes of PHP array per stored float, measured rather than derived: a float
 *  is 8 bytes, but a packed PHP array costs a 16-byte zval per slot plus the
 *  array's own header, and 10,000 x 64 measured 13MB -- 20.3 bytes a float.
 *  Rounded up, because refusing to start is recoverable and dying halfway is
 *  not.
 */
const VERGEML_ORGANIZE_BYTES_PER_FLOAT = 24;


/**
 *  The table, registered on $wpdb the way core registers its own, and for the
 *  same reason ai-index does it: a name interpolated from a helper is a string
 *  the static analysis cannot follow, so every query built that way reads to
 *  Plugin Check as an unprepared one.
 */

vergeml_organize_register_table();

function vergeml_organize_register_table() {

    global $wpdb;

    $wpdb->vergeml_organize_runs = $wpdb->prefix . VERGEML_ORGANIZE_TABLE;

    if ( ! in_array( VERGEML_ORGANIZE_TABLE, $wpdb->tables, true ) ) {
        $wpdb->tables[] = VERGEML_ORGANIZE_TABLE;
    }
}


function vergeml_organize_table() {
    global $wpdb;
    return $wpdb->vergeml_organize_runs;
}


function vergeml_organize_state() {
    $state = get_option( VERGEML_ORGANIZE_OPTION, array() );
    return is_array( $state ) ? $state : array();
}


/**
 *  The thresholds, filterable rather than baked in.
 *
 *  "No folder holds more than about fifty files" is a sentence that can be
 *  explained to a customer and changed by one. k = sqrt(n/2) is not.
 */

function vergeml_organize_limits() {

    return array(
        'dims'       => max( 4, (int) apply_filters( 'vergeml_organize_dims', VERGEML_ORGANIZE_DIMS ) ),
        'max_branch' => max( 2, (int) apply_filters( 'vergeml_organize_max_branch', VERGEML_ORGANIZE_MAX_BRANCH ) ),
        'min_branch' => max( 1, (int) apply_filters( 'vergeml_organize_min_branch', VERGEML_ORGANIZE_MIN_BRANCH ) ),
        'max_depth'  => max( 1, (int) apply_filters( 'vergeml_organize_max_depth', VERGEML_ORGANIZE_MAX_DEPTH ) ),
        'width'      => max( 2, (int) apply_filters( 'vergeml_organize_width', VERGEML_ORGANIZE_WIDTH ) ),
        'outlier'    => (float) apply_filters( 'vergeml_organize_outlier', VERGEML_ORGANIZE_OUTLIER ),
    );
}


/* ------------------------------------------------------------------ schema */

/**
 *  vergeml_organize_install
 *
 *  One row per run, the tree as JSON in it. Safe to call on every load:
 *  dbDelta issues only the difference.
 *
 *  The load cursor is `load_cursor` and not `cursor` because CURSOR is a
 *  reserved word -- MariaDB refuses the CREATE outright, and dbDelta reports
 *  nothing when it does. The REST field is still called `cursor`; only the
 *  column had to move.
 */

function vergeml_organize_install() {

    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table   = vergeml_organize_table();
    $collate = $wpdb->get_charset_collate();

    /*
     *  dbDelta parses this rather than executing it, and is fussy in ways that
     *  fail silently: two spaces after PRIMARY KEY, KEY rather than INDEX, one
     *  field per line. TEXT columns carry no DEFAULT -- MySQL before 8.0.13
     *  refuses one, and this plugin's floor is much older than that.
     */
    $sql = "CREATE TABLE {$table} (
        run_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        parent_run_id bigint(20) unsigned NOT NULL DEFAULT 0,
        status varchar(16) NOT NULL DEFAULT 'running',
        k smallint(5) unsigned NOT NULL DEFAULT 0,
        n int(10) unsigned NOT NULL DEFAULT 0,
        load_cursor bigint(20) unsigned NOT NULL DEFAULT 0,
        tree longtext NULL,
        params longtext NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (run_id),
        KEY status (status),
        KEY parent_run_id (parent_run_id),
        KEY created_at (created_at)
    ) {$collate};";

    dbDelta( $sql );

    $state           = vergeml_organize_state();
    $state['schema'] = VERGEML_ORGANIZE_VERSION;

    update_option( VERGEML_ORGANIZE_OPTION, $state, false );
}


/**
 *  Hooked rather than called, so safe mode -- which skips every file in this
 *  folder -- simply means nobody answers, and the activation does not fatal on
 *  a function that was never defined.
 */

add_action( 'vergeml_activate', 'vergeml_organize_install' );


/**
 *  A site that upgrades without ever visiting an admin screen never fires the
 *  activation hook. This is the same lazy guard ai-index uses for its
 *  migration: one option read on admin screens, and it stops answering as soon
 *  as the schema is recorded.
 *
 *  Deliberately not in the step endpoint. That endpoint has a query budget of
 *  four and every check here would spend one of them.
 */

add_action( 'admin_init', 'vergeml_organize_housekeeping' );

function vergeml_organize_housekeeping() {

    if ( wp_doing_ajax() ) {
        return;
    }

    $state = vergeml_organize_state();

    if ( empty( $state['schema'] ) || VERGEML_ORGANIZE_VERSION !== (int) $state['schema'] ) {
        vergeml_organize_install();
        $state = vergeml_organize_state();
    }

    /*
     *  Pruning lives here rather than on the step endpoint, and the reason is
     *  the budget. A step is allowed four queries; creating a run already
     *  spends three, and the two that find and drop the eleventh-oldest run
     *  would put it over. Dropping old rows is housekeeping, not part of
     *  anybody's step, so it happens on an admin screen and at most once a
     *  day -- a tree for ten thousand files is a large blob, but it is not an
     *  urgent one.
     */
    $last = isset( $state['pruned'] ) ? (int) $state['pruned'] : 0;

    if ( time() - $last < DAY_IN_SECONDS ) {
        return;
    }

    vergeml_organize_prune();

    $state['pruned'] = time();

    update_option( VERGEML_ORGANIZE_OPTION, $state, false );
}


/* -------------------------------------------------------------- run storage */

/**
 *  vergeml_organize_run_get
 *
 *  One run, with tree and params already decoded. One query.
 */

function vergeml_organize_run_get( $run_id ) {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table; there is no core API for it.
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->vergeml_organize_runs} WHERE run_id = %d",
        (int) $run_id
    ), ARRAY_A );
    // phpcs:enable

    return $row ? vergeml_organize_row_out( $row ) : null;
}


/**
 *  vergeml_organize_run_latest
 *
 *  The newest run, whatever state it is in. The Phase-3 screen's landing read.
 */

function vergeml_organize_run_latest() {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $row = $wpdb->get_row(
        "SELECT * FROM {$wpdb->vergeml_organize_runs} ORDER BY run_id DESC LIMIT 1",
        ARRAY_A
    );
    // phpcs:enable

    return $row ? vergeml_organize_row_out( $row ) : null;
}


function vergeml_organize_row_out( $row ) {

    $tree   = json_decode( (string) $row['tree'], true );
    $params = json_decode( (string) $row['params'], true );

    return array(
        'run_id'        => (int) $row['run_id'],
        'parent_run_id' => (int) $row['parent_run_id'],
        'status'        => (string) $row['status'],
        'k'             => (int) $row['k'],
        'n'             => (int) $row['n'],
        'cursor'        => (int) $row['load_cursor'],
        'tree'          => is_array( $tree ) ? $tree : array(),
        'params'        => is_array( $params ) ? $params : array(),
        'created_at'    => (string) $row['created_at'],
        'updated_at'    => (string) $row['updated_at'],
    );
}


/**
 *  vergeml_organize_run_create
 *
 *  Two queries: how many vectors there are to work with, and the row.
 *
 *  The count is taken here rather than in the first working step because `n`
 *  is what every progress figure and every estimate divides by, and a step
 *  that had to count as well as work would spend its whole query budget
 *  saying so.
 */

function vergeml_organize_run_create( $args ) {

    global $wpdb;

    $limits = vergeml_organize_limits();
    $scope  = isset( $args['scope'] ) && is_array( $args['scope'] ) ? array_values( array_map( 'intval', $args['scope'] ) ) : array();

    $n = $scope ? count( $scope ) : vergeml_organize_count();

    /*
     *  The memory arithmetic, before anything is loaded. files x dims x the
     *  measured cost of a float in a PHP array, against this host's own limit.
     *  If it will not fit the projection goes further down; if it still will
     *  not, the run is refused with a reason rather than started and killed
     *  halfway through.
     */
    $dims = vergeml_organize_fit_dims( $n, $limits['dims'] );

    $params = array(
        'phase'    => 0 === $dims ? 'failed' : 'load',
        'dims'     => $dims ? $dims : $limits['dims'],
        'limits'   => $limits,
        // Filterable like the thresholds, and for the same reason: the number
        // that decides how long one request runs is exactly the number a host
        // with an unusual timeout needs to be able to change.
        'batch'    => max( VERGEML_ORGANIZE_BATCH_MIN, (int) apply_filters( 'vergeml_organize_batch', VERGEML_ORGANIZE_BATCH ) ),
        'scope'    => $scope,
        'refine'   => isset( $args['refine'] ) && is_array( $args['refine'] ) ? $args['refine'] : array(),
        'carry'    => isset( $args['carry'] ) && is_array( $args['carry'] ) ? $args['carry'] : array(),
        'work'     => array(),
        'jobs'     => array(),
        'branches' => array(),
        'outliers' => array(),
        'loaded'   => 0,
        'timing'   => array( 'load_ms' => 0.0, 'load_n' => 0, 'cluster_ms' => 0.0, 'cluster_n' => 0 ),
        'memory'   => vergeml_organize_memory( $n, $dims ? $dims : $limits['dims'] ),
    );

    if ( 0 === $dims ) {
        $params['error'] = __( 'This library needs more memory than this server allows. Raise the PHP memory limit, or organise a smaller selection.', 'vergelabs-media-library' );
    }

    $now = current_time( 'mysql', true );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->insert(
        vergeml_organize_table(),
        array(
            'parent_run_id' => isset( $args['parent_run_id'] ) ? (int) $args['parent_run_id'] : 0,
            'status'        => 0 === $dims ? 'failed' : 'running',
            'k'             => 0,
            'n'             => $n,
            'load_cursor'   => 0,
            'tree'          => wp_json_encode( array() ),
            'params'        => wp_json_encode( $params ),
            'created_at'    => $now,
            'updated_at'    => $now,
        ),
        array( '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
    );
    // phpcs:enable

    return array(
        'run_id'        => (int) $wpdb->insert_id,
        'parent_run_id' => isset( $args['parent_run_id'] ) ? (int) $args['parent_run_id'] : 0,
        'status'        => 0 === $dims ? 'failed' : 'running',
        'k'             => 0,
        'n'             => $n,
        'cursor'        => 0,
        'tree'          => array(),
        'params'        => $params,
        'created_at'    => $now,
        'updated_at'    => $now,
    );
}


/**
 *  vergeml_organize_run_save
 *
 *  One query. Everything the step changed goes back in a single UPDATE,
 *  because the budget for a step is four and two of them are already spent
 *  reading the row and loading a batch.
 */

function vergeml_organize_run_save( $run ) {

    global $wpdb;

    /*
     *  A prepared UPDATE rather than $wpdb->update(), and the reason is the
     *  budget rather than taste. $wpdb->update() asks the table for its column
     *  charsets before it writes -- SHOW FULL COLUMNS, once per request, and a
     *  query like any other. It put a step at four and the cancel endpoint at
     *  three against a budget of two. Every value below still goes through
     *  prepare; only the introspection is gone.
     */
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return false !== $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->vergeml_organize_runs}
            SET status = %s, k = %d, n = %d, load_cursor = %d,
                tree = %s, params = %s, updated_at = %s
          WHERE run_id = %d",
        (string) $run['status'],
        (int) $run['k'],
        (int) $run['n'],
        (int) $run['cursor'],
        wp_json_encode( $run['tree'] ),
        wp_json_encode( $run['params'] ),
        current_time( 'mysql', true ),
        (int) $run['run_id']
    ) );
    // phpcs:enable
}


/**
 *  vergeml_organize_count
 *
 *  How many files have a vector at all. One query, and the denominator for
 *  every progress figure this feature reports.
 */

function vergeml_organize_count() {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE embedding IS NOT NULL"
    );
    // phpcs:enable
}


/* ------------------------------------------------------------------ memory */

/**
 *  vergeml_organize_memory
 *
 *  What this run will cost in PHP arrays, against what this host allows.
 *
 *  Nothing here is called "big". A file count is a poor stand-in for the thing
 *  that matters -- three thousand files on a slow shared host is a worse
 *  experience than fifteen thousand on decent hardware -- so the question
 *  asked is arithmetic about this server, not a threshold chosen elsewhere.
 *
 *  Budgeted against half the limit, because WordPress, the theme and every
 *  other plugin also have to fit in it.
 */

function vergeml_organize_memory( $n, $dims ) {

    $limit = vergeml_organize_memory_limit();

    // The projected set, the centroids and the assignment array. The source
    // vectors are never all resident: they arrive in chunks and are projected
    // on the way in.
    $need = ( (float) $n * (float) $dims * VERGEML_ORGANIZE_BYTES_PER_FLOAT )
          + ( (float) $n * 220 );

    $budget = $limit > 0 ? $limit / 2 : 0;

    return array(
        'files'  => (int) $n,
        'dims'   => (int) $dims,
        'need'   => (int) round( $need ),
        'limit'  => (int) $limit,
        'budget' => (int) round( $budget ),
        // No limit at all is not a promise that it fits, but it is not this
        // function's place to refuse a host that was told to allow anything.
        'fits'   => $limit <= 0 || $need <= $budget,
    );
}


function vergeml_organize_memory_limit() {

    $raw = trim( (string) ini_get( 'memory_limit' ) );

    if ( '' === $raw || '-1' === $raw ) {
        return 0;
    }

    $unit  = strtolower( substr( $raw, -1 ) );
    $value = (float) $raw;

    if ( 'g' === $unit ) {
        $value *= 1024 * 1024 * 1024;
    } elseif ( 'm' === $unit ) {
        $value *= 1024 * 1024;
    } elseif ( 'k' === $unit ) {
        $value *= 1024;
    }

    return (int) $value;
}


/**
 *  vergeml_organize_fit_dims
 *
 *  The widest projection this library fits in. Halves until it does, and
 *  returns 0 when even the narrowest will not -- which is a refusal, not a
 *  smaller run: a tree built on eight dimensions would be a tree about
 *  nothing.
 */

function vergeml_organize_fit_dims( $n, $dims ) {

    $dims = max( 4, (int) $dims );

    while ( $dims >= 16 ) {

        $memory = vergeml_organize_memory( $n, $dims );

        if ( $memory['fits'] ) {
            return $dims;
        }

        $dims = (int) floor( $dims / 2 );
    }

    return 0;
}


/* ----------------------------------------------------------------- loading */

/**
 *  vergeml_organize_vectors
 *
 *  One chunk of the library, in id order, one query.
 *
 *  Id order is not cosmetic: it is what makes the seeding reproducible. A
 *  clustering whose result depends on the order rows came back in is one that
 *  changes when somebody uploads a file.
 *
 *  Resumable by construction -- the cursor is the last id looked at, so
 *  resuming is a comparison rather than an OFFSET that grows with the library.
 */

function vergeml_organize_vectors( $after = 0, $limit = 0, $scope = array() ) {

    global $wpdb;

    $cap   = $limit > 0 ? (int) $limit : PHP_INT_MAX;
    $scope = array_values( array_filter( array_map( 'intval', (array) $scope ) ) );

    if ( $scope ) {

        $placeholders = implode( ',', array_fill( 0, count( $scope ), '%d' ) );

        $params   = $scope;
        $params[] = (int) $after;
        $params[] = $cap;

        /*
         *  $placeholders is a string of %d markers matching count( $scope ),
         *  and every value travels through prepare. The sniffs cannot count a
         *  dynamic list.
         */
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT attachment_id, embedding, embedding_dims, tags, kind, document_type
               FROM {$wpdb->vergeml_ai_index}
              WHERE embedding IS NOT NULL AND attachment_id IN ( $placeholders ) AND attachment_id > %d
              ORDER BY attachment_id ASC
              LIMIT %d",
            $params
        ), ARRAY_A );
        // phpcs:enable

    } else {

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT attachment_id, embedding, embedding_dims, tags, kind, document_type
               FROM {$wpdb->vergeml_ai_index}
              WHERE embedding IS NOT NULL AND attachment_id > %d
              ORDER BY attachment_id ASC
              LIMIT %d",
            (int) $after,
            $cap
        ), ARRAY_A );
        // phpcs:enable
    }

    $out = array();

    foreach ( (array) $rows as $row ) {

        $vector = vergeml_index_vector_out( $row['embedding'] );

        if ( ! is_array( $vector ) || ! $vector ) {
            continue;
        }

        $out[] = array(
            'id'     => (int) $row['attachment_id'],
            'vector' => $vector,
            'tags'   => vergeml_index_tags_out( $row['tags'] ),
            'kind'   => (string) $row['kind'],
            'doc'    => (string) $row['document_type'],
        );
    }

    return $out;
}


/* -------------------------------------------------------------- projection */

/**
 *  vergeml_organize_project
 *
 *  A long vector down to a short one, by averaging fixed contiguous bands.
 *
 *  Fixed and seedless on purpose. A per-site random projection -- the usual
 *  Johnson-Lindenstrauss trick -- would make a recorded run from one site
 *  meaningless on another, and the fixtures this phase produces are the mock
 *  the Phase-3 screen is built against. Band averaging has no seed to get
 *  wrong, costs one pass, and keeps neighbours near each other, which is the
 *  only property clustering asks of it.
 *
 *  Re-normalised to unit length, so distances stay comparable between a
 *  768-dimension source and a 1536-dimension one.
 */

function vergeml_organize_project( $vector, $dims = VERGEML_ORGANIZE_DIMS ) {

    $vector = array_values( (array) $vector );
    $length = count( $vector );
    $dims   = max( 1, (int) $dims );

    if ( 0 === $length ) {
        return array();
    }

    if ( $length <= $dims ) {
        // Already at or below the target: nothing to average, only to
        // normalise. Padding it out to $dims would invent components.
        return vergeml_organize_normalise( $vector );
    }

    $out = array();

    for ( $i = 0; $i < $dims; $i++ ) {

        // Integer band edges, so every source component lands in exactly one
        // band and the last band always reaches the end.
        $from = (int) floor( $i * $length / $dims );
        $to   = (int) floor( ( $i + 1 ) * $length / $dims );

        if ( $to <= $from ) {
            $to = $from + 1;
        }

        $sum = 0.0;

        for ( $j = $from; $j < $to && $j < $length; $j++ ) {
            $sum += (float) $vector[ $j ];
        }

        $out[] = $sum / ( $to - $from );
    }

    return vergeml_organize_normalise( $out );
}


function vergeml_organize_normalise( $vector ) {

    $sum = 0.0;

    foreach ( $vector as $value ) {
        $sum += (float) $value * (float) $value;
    }

    $length = sqrt( $sum );

    if ( $length <= 0 ) {
        return array_map( 'floatval', $vector );
    }

    $out = array();

    foreach ( $vector as $value ) {
        // Rounded, so the same vector projects to the same bytes on a machine
        // with a different floating-point mood. Determinism is the property
        // everything after this depends on.
        $out[] = round( (float) $value / $length, 6 );
    }

    return $out;
}


/* -------------------------------------------------------------- clustering */

/**
 *  Squared euclidean distance. Squared because comparing them is all anything
 *  here does, and the square root is a per-comparison cost for nothing.
 */

function vergeml_organize_distance( $a, $b ) {

    $sum = 0.0;

    foreach ( $a as $i => $value ) {
        $d    = $value - $b[ $i ];
        $sum += $d * $d;
    }

    return $sum;
}


/**
 *  vergeml_organize_sample
 *
 *  A deterministic subset, capped. Every ceil(n/cap)-th point in id order, so
 *  the same library always yields the same sample and a run recorded today
 *  can be compared with one recorded next week.
 */

function vergeml_organize_sample( $indices, $cap = VERGEML_ORGANIZE_SEED_SAMPLE ) {

    $indices = array_values( $indices );
    $count   = count( $indices );
    $cap     = max( 1, (int) $cap );

    if ( $count <= $cap ) {
        return $indices;
    }

    $stride = (int) ceil( $count / $cap );
    $out    = array();

    for ( $i = 0; $i < $count; $i += $stride ) {
        $out[] = $indices[ $i ];
    }

    return $out;
}


/**
 *  vergeml_organize_seed
 *
 *  k-means++ seeding with the randomness taken out.
 *
 *  The real algorithm picks each next centroid at random, weighted by distance
 *  from the nearest existing one. Weighted-random is what makes repeated runs
 *  differ, so this takes the farthest point instead -- the same idea, minus
 *  the die roll.
 *
 *  It is also the part that did not scale: O(n·k²), and 36.6 of the 46.7
 *  seconds at ten thousand files. The caller hands in a sample rather than the
 *  library, so the quadratic search is bounded whatever the library does.
 */

function vergeml_organize_seed( $sample, $k, $vectors ) {

    $sample = array_values( $sample );

    if ( ! $sample ) {
        return array();
    }

    $centroids = array( $vectors[ $sample[0] ] );

    while ( count( $centroids ) < $k ) {

        $best      = null;
        $best_dist = -1.0;

        foreach ( $sample as $index ) {

            $nearest = INF;

            foreach ( $centroids as $centroid ) {
                $d = vergeml_organize_distance( $vectors[ $index ], $centroid );
                if ( $d < $nearest ) {
                    $nearest = $d;
                }
            }

            // Strictly greater, so ties go to the lower attachment id -- the
            // sample is in id order, and a tie broken by iteration order is a
            // tie broken by chance.
            if ( $nearest > $best_dist ) {
                $best_dist = $nearest;
                $best      = $index;
            }
        }

        if ( null === $best || $best_dist <= 0 ) {
            break; // fewer distinct points than clusters asked for
        }

        $centroids[] = $vectors[ $best ];
    }

    return $centroids;
}


/**
 *  vergeml_organize_kmeans
 *
 *  Lloyd iterations over a set of member indices, with a cap and an early exit
 *  when nothing moves. Ported from the spike, which is already correct here.
 *
 *  An empty cluster keeps its centroid rather than collapsing to the origin:
 *  the origin is a point no vector is near, so a collapsed cluster stays empty
 *  for ever and the split silently produces fewer branches than asked for.
 */

function vergeml_organize_kmeans( $members, $k, $vectors ) {

    $members = array_values( $members );
    $count   = count( $members );

    if ( 0 === $count ) {
        return array( 'assignment' => array(), 'centroids' => array(), 'iterations' => 0 );
    }

    $k         = max( 1, min( (int) $k, $count ) );
    $centroids = vergeml_organize_seed( vergeml_organize_sample( $members ), $k, $vectors );
    $k         = count( $centroids );

    if ( 0 === $k ) {
        return array( 'assignment' => array(), 'centroids' => array(), 'iterations' => 0 );
    }

    $assignment = array_fill( 0, $count, -1 );
    $iterations = 0;
    $dims       = count( $vectors[ $members[0] ] );

    for ( $iteration = 0; $iteration < VERGEML_ORGANIZE_ITERATIONS; $iteration++ ) {

        $iterations = $iteration + 1;
        $moved      = 0;

        foreach ( $members as $i => $index ) {

            $best      = 0;
            $best_dist = INF;

            foreach ( $centroids as $c => $centroid ) {
                $d = vergeml_organize_distance( $vectors[ $index ], $centroid );
                if ( $d < $best_dist ) {
                    $best_dist = $d;
                    $best      = $c;
                }
            }

            if ( $assignment[ $i ] !== $best ) {
                $assignment[ $i ] = $best;
                $moved++;
            }
        }

        if ( 0 === $moved ) {
            break; // settled; running further would only cost time
        }

        $sums   = array_fill( 0, $k, array_fill( 0, $dims, 0.0 ) );
        $counts = array_fill( 0, $k, 0 );

        foreach ( $members as $i => $index ) {
            $c = $assignment[ $i ];
            $counts[ $c ]++;
            foreach ( $vectors[ $index ] as $d => $value ) {
                $sums[ $c ][ $d ] += $value;
            }
        }

        foreach ( $sums as $c => $sum ) {
            if ( 0 === $counts[ $c ] ) {
                continue;
            }
            foreach ( $sum as $d => $value ) {
                $centroids[ $c ][ $d ] = $value / $counts[ $c ];
            }
        }
    }

    return array( 'assignment' => $assignment, 'centroids' => $centroids, 'iterations' => $iterations );
}


/* ----------------------------------------------------------------- labelling */

/**
 *  vergeml_organize_label
 *
 *  What to call a branch.
 *
 *  One word: the thing most of the files in it actually are. No model call --
 *  a name is a summary of data already held, and paying per folder to be told
 *  "photos" is a poor trade.
 *
 *  This used to staple the top two tags together and title-case the result,
 *  which produced "Account Basket", "Anna Catalogue" and "Boots Cover" on a
 *  real library. Two faults, and the second is the one that hurt:
 *
 *  1.  Two tags with a space between them is not a name. English does not
 *      work that way, and Title Case made every folder read as somebody's
 *      surname.
 *
 *  2.  The score was share x (1/spread), so a tag ONE file carried could win
 *      the whole folder as long as no other file in the library had it --
 *      rarity was rewarded without any floor on how much of the branch the
 *      word described. A word on 1 file in 34 beat a word on all 34.
 *
 *  So: only tags that at least half the branch carries are candidates at all,
 *  and among those, share decides, with rarity elsewhere as a nudge rather
 *  than a multiplier that spans orders of magnitude. Rarity still matters --
 *  it is what stops every folder being called "photo" -- but it can no longer
 *  overrule what the folder is.
 *
 *  A second word is added only when a sibling took the first (see
 *  vergeml_organize_distinct_labels), and it is joined with "and", because
 *  "Skincare and cosmetics" is a name and "Skincare Cosmetics" is not.
 */

function vergeml_organize_label( $members, $global, $points, $library = 0 ) {

    $counts = array();

    foreach ( $members as $index ) {
        foreach ( array_unique( $points[ $index ]['tags'] ) as $tag ) {

            if ( ! vergeml_organize_nameable( $tag ) ) {
                continue;
            }

            $counts[ $tag ] = isset( $counts[ $tag ] ) ? $counts[ $tag ] + 1 : 1;
        }
    }

    $total = max( 1, count( $members ) );
    $score = array();

    foreach ( $counts as $tag => $count ) {

        $share = $count / $total;

        // Not most of the branch, so not the branch's name.
        if ( $share < VERGEML_ORGANIZE_NAME_SHARE ) {
            continue;
        }

        $spread = max( 1, isset( $global[ $tag ] ) ? (int) $global[ $tag ] : 1 );

        /*
         *  A word most of the library carries names nothing. Checked against
         *  the library rather than against this branch, because the two
         *  questions are different: "do these files share it" is what makes a
         *  name true, "does everything else share it too" is what makes a name
         *  useless.
         */
        if ( $library > 0 && ( $spread / $library ) > VERGEML_ORGANIZE_NAME_CEILING ) {
            continue;
        }

        // Share decides; being rare elsewhere breaks the ties, and can at most
        // double a tag's score rather than multiplying it by the library size.
        $score[ $tag ] = $share * ( 1 + 1 / $spread );
    }

    $words = array_slice( vergeml_organize_rank( $score ), 0, 1 );

    if ( ! $words ) {

        // Nothing shared: fall back to what the files are rather than
        // inventing a name for them.
        $kinds = array();

        foreach ( $members as $index ) {
            if ( '' !== $points[ $index ]['kind'] ) {
                $kinds[ $points[ $index ]['kind'] ] = true;
            }
        }

        $kinds = array_slice( array_keys( $kinds ), 0, 2 );

        return $kinds ? ucfirst( implode( ' and ', $kinds ) ) : __( 'Unsorted', 'vergelabs-media-library' );
    }

    return vergeml_organize_name_case( $words[0] );
}


/**
 *  A tag as a folder name.
 *
 *  Sentence case, not Title Case. The tags are lowercase phrases -- "beauty
 *  products", "circuit board" -- and capitalising every word turns them into
 *  proper nouns: "Beauty Products" reads as a company, "Beauty products" reads
 *  as a shelf. Acronyms the model returns in capitals are left alone.
 */

function vergeml_organize_name_case( $tag ) {

    $tag = trim( (string) $tag );

    if ( '' === $tag ) {
        return '';
    }

    $first = vergeml_organize_substr( $tag, 0, 1 );

    // Already capitalised, or an acronym: leave it as the model wrote it.
    if ( $first === vergeml_organize_upper( $first ) ) {
        return $tag;
    }

    return vergeml_organize_upper( $first ) . vergeml_organize_substr( $tag, 1 );
}


/** mb_* where the host has it, so an accented first letter is not cut in half. */
function vergeml_organize_upper( $text ) {
    return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
}

/** @see vergeml_organize_upper */
function vergeml_organize_substr( $text, $start, $length = null ) {

    if ( function_exists( 'mb_substr' ) ) {
        return null === $length ? mb_substr( $text, $start, null, 'UTF-8' ) : mb_substr( $text, $start, $length, 'UTF-8' );
    }

    return null === $length ? substr( $text, $start ) : substr( $text, $start, $length );
}


/**
 *  vergeml_organize_distinct_labels
 *
 *  Names for the branches of one split, none of them the same as another.
 *
 *  Scoring each cluster on its own produced six sibling folders all called
 *  "Boats Harbour", because twenty files that share three tags go on sharing
 *  them however they are cut. Six folders with one name is not a tree anybody
 *  can use, and it is the same failure as the catch-all: a confident name that
 *  tells the reader nothing.
 *
 *  So a collision is resolved by looking further down each cluster's own
 *  ranked tags for something its rivals do not have, and joining it with
 *  "and" -- "Skincare and cosmetics", which is a name, rather than "Skincare
 *  Cosmetics", which is two words in a trenchcoat. Where there is nothing --
 *  which is the honest outcome when the members really are indistinguishable
 *  by tag -- it falls back to a numeral, because at that point the only true
 *  thing left to say is that this is the second one.
 */

function vergeml_organize_distinct_labels( $clusters, $global, $points, $ancestors = array(), &$registry = null, $library = 0 ) {

    $labels = array();
    $ranked = array();

    foreach ( $clusters as $c => $members ) {
        $labels[ $c ] = vergeml_organize_label( $members, $global, $points, $library );
        $ranked[ $c ] = vergeml_organize_shared_tags( $members, $points, 8 );
    }

    /*
     *  The names already on the way here count as taken. Splitting a branch
     *  re-scores the same members against the same tags, so without this the
     *  child scores its parent's name and the tree grows "Body Canvas / Body
     *  Canvas" -- a folder inside a folder of the same name, which reads as a
     *  bug whether or not it is one.
     */
    /*
     *  Taken, across the whole tree rather than across this split.
     *
     *  Scoping this to one split produced a tree with "Landscape and nature /
     *  Nature" beside "Landscape / Nature" -- two folders called the same
     *  thing, neither of them a sibling of the other, so neither pass ever
     *  saw the collision. The registry is passed down the recursion so a name
     *  used anywhere is used everywhere.
     */
    if ( ! is_array( $registry ) ) {
        $registry = array();
    }

    $taken = &$registry;

    foreach ( (array) $ancestors as $name ) {
        $taken[ vergeml_organize_name_key( $name ) ] = true;
    }

    /*
     *  What the folders above are already called, word by word.
     *
     *  "Landscape and nature / Nature" says nothing the parent did not
     *  already say. A child has to add a word, or it is not a subdivision of
     *  anything -- it is the same folder one level down.
     */
    $inherited = array();

    foreach ( (array) $ancestors as $name ) {
        foreach ( vergeml_organize_name_words( $name ) as $word ) {
            $inherited[ $word ] = true;
        }
    }

    foreach ( $labels as $c => $label ) {

        $key = vergeml_organize_name_key( $label );

        // Free, and it says something the parent did not.
        if ( ! isset( $taken[ $key ] ) && ! vergeml_organize_name_echoes( $label, $inherited ) ) {
            $taken[ $key ] = true;
            continue;
        }

        $resolved = '';

        foreach ( $ranked[ $c ] as $tag ) {

            /*
             *  A word from neither the name nor any folder above it. Without
             *  the second half, a branch under "Landscape" resolved to
             *  "Nature and landscape" -- which is the parent's word again, in
             *  a different order, and read as a third opinion about the same
             *  pile of photographs.
             */
            if ( false !== stripos( $label, $tag ) || vergeml_organize_name_echoes( $tag, $inherited ) ) {
                continue;
            }

            $candidate = isset( $taken[ vergeml_organize_name_key( $label ) ] )
                ? $label . ' and ' . $tag
                : vergeml_organize_name_case( $tag );

            if ( ! isset( $taken[ vergeml_organize_name_key( $candidate ) ] ) ) {
                $resolved = $candidate;
                break;
            }
        }

        if ( '' === $resolved ) {
            $n = 2;
            while ( isset( $taken[ vergeml_organize_name_key( $label . ' ' . $n ) ] ) ) {
                $n++;
            }
            $resolved = $label . ' ' . $n;
        }

        $taken[ vergeml_organize_name_key( $resolved ) ] = true;
        $labels[ $c ]                                    = $resolved;
    }

    return $labels;
}


/**
 *  Two names that are the same name.
 *
 *  "Landscape and nature" and "Nature and landscape" are one folder written
 *  two ways, and the tree shipped both. Comparing the sorted set of words
 *  rather than the string is what makes them collide -- along with case, and
 *  the joining word, which carries no meaning of its own.
 */

function vergeml_organize_name_key( $name ) {

    $words = vergeml_organize_name_words( $name );

    sort( $words );

    return implode( ' ', $words );
}


/** The meaningful words in a name, lowercased, without the joiners. */
function vergeml_organize_name_words( $name ) {

    $words = preg_split( '/[^\p{L}\p{N}]+/u', vergeml_organize_lower( (string) $name ), -1, PREG_SPLIT_NO_EMPTY );

    if ( ! $words ) {
        return array();
    }

    $out = array();

    foreach ( $words as $word ) {

        // Joiners are punctuation with letters in. Dropping them is what makes
        // "landscape and nature" and "nature landscape" the same key.
        if ( in_array( $word, array( 'and', 'or', 'the', 'a', 'of', 'in', 'with' ), true ) ) {
            continue;
        }

        $out[ $word ] = true;
    }

    return array_keys( $out );
}


/** Whether a name says only what the folders above it already said. */
function vergeml_organize_name_echoes( $name, $inherited ) {

    $words = vergeml_organize_name_words( $name );

    if ( ! $words ) {
        return false;
    }

    foreach ( $words as $word ) {
        if ( ! isset( $inherited[ $word ] ) ) {
            return false; // it adds something
        }
    }

    return true;
}


/** @see vergeml_organize_upper */
function vergeml_organize_lower( $text ) {
    return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
}


/**
 *  Whether a tag can be a folder name.
 *
 *  Scoring by "common inside, rare outside" has one pathology, and the first
 *  run on the test library found it: a tag nothing else shares scores highest
 *  precisely *because* nothing else shares it. So the branches came back
 *  called "0071 0072" and "08 2026" -- fragments of filenames and dates,
 *  perfectly discriminating and completely useless as folder names.
 *
 *  Rarity alone should not win. A tag with no letters in it names nothing, and
 *  a single character is not a word.
 */

function vergeml_organize_nameable( $tag ) {

    $tag = trim( (string) $tag );

    if ( strlen( $tag ) < 2 ) {
        return false;
    }

    return (bool) preg_match( '/[a-z]{2}/i', $tag );
}


/**
 *  The tags each one is shared with, for the reason line. Ordered by how many
 *  members share them, so the line names what actually held the branch
 *  together.
 */

function vergeml_organize_shared_tags( $members, $points, $take = 3 ) {

    $counts = array();

    foreach ( $members as $index ) {
        foreach ( array_unique( $points[ $index ]['tags'] ) as $tag ) {

            if ( ! vergeml_organize_nameable( $tag ) ) {
                continue;
            }

            $counts[ $tag ] = isset( $counts[ $tag ] ) ? $counts[ $tag ] + 1 : 1;
        }
    }

    return array_slice( vergeml_organize_rank( $counts ), 0, $take );
}


/**
 *  Keys of a score map, highest first, ties broken by the key itself.
 *
 *  Not arsort(). PHP's sorts were only made stable in 8.0, and this plugin's
 *  floor is 7.4 -- so on the hosts this is written for, two tags with the same
 *  score come back in whatever order the engine felt like, and a folder gets a
 *  different name on a different machine. Determinism is the property every
 *  claim about diffs in this file rests on, so the tiebreak is written out.
 */

function vergeml_organize_rank( $scores ) {

    $keys = array_keys( $scores );

    usort( $keys, function ( $a, $b ) use ( $scores ) {

        if ( $scores[ $a ] === $scores[ $b ] ) {
            return strcmp( (string) $a, (string) $b );
        }

        return $scores[ $a ] < $scores[ $b ] ? 1 : -1;
    } );

    return $keys;
}


/* ------------------------------------------------------------------- steps */

/**
 *  vergeml_organize_step
 *
 *  One unit of work, and never more than one.
 *
 *  The shape this plugin already uses everywhere: a POST the caller loops,
 *  carrying a cursor, exactly like vergeml_health_scan_step() and
 *  vergeml_ai_index_step(). No cron, no background worker, nothing that needs
 *  anything a shared host does not have.
 *
 *  Query budget is four and this holds to it:
 *
 *    - creating a run  : count + insert                 = 2
 *    - a loading step  : read + one batch + write       = 3
 *    - a cluster step  : read + write                   = 2
 *    - the finish step : read + prime samples + write   = 4
 *
 *  None of them moves with the library size or with the number of steps
 *  already taken.
 */

function vergeml_organize_step( $args = array() ) {

    $started = microtime( true );

    if ( empty( $args['run_id'] ) ) {
        $run = vergeml_organize_run_create( $args );
        return vergeml_organize_report( $run, $started );
    }

    $run = vergeml_organize_run_get( (int) $args['run_id'] );

    if ( ! $run ) {
        return new WP_Error( 'vergeml_organize_no_run', __( 'That organise run does not exist.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    /*
     *  The cancel flag, read before anything else is done.
     *
     *  A cancel that has to wait for the step it is cancelling is not a
     *  cancel, so the cancel endpoint writes the status and this is where the
     *  step notices. Whatever had been built stays: a partial tree is still
     *  worth showing.
     */
    if ( 'running' !== $run['status'] ) {
        return vergeml_organize_report( $run, $started );
    }

    $params = $run['params'];
    $phase  = isset( $params['phase'] ) ? $params['phase'] : 'load';

    if ( 'load' === $phase ) {
        $run = vergeml_organize_step_load( $run, $started );
    } elseif ( 'cluster' === $phase ) {
        $run = vergeml_organize_step_cluster( $run, $started );
    } else {
        $run = vergeml_organize_step_finish( $run, $started );
    }

    vergeml_organize_run_save( $run );

    return vergeml_organize_report( $run, $started );
}


/**
 *  The loading phase: one batch of vectors, projected on the way in and kept
 *  packed.
 *
 *  Packed rather than as PHP arrays because this is what survives between
 *  requests: ten thousand projected vectors are 2.5MB of float32 and 13MB as
 *  arrays, and only one of those is a sensible thing to write to a column
 *  twenty times.
 */

function vergeml_organize_step_load( $run, $started ) {

    $params = $run['params'];
    $dims   = (int) $params['dims'];
    $batch  = max( VERGEML_ORGANIZE_BATCH_MIN, min( VERGEML_ORGANIZE_BATCH_MAX, (int) $params['batch'] ) );

    $rows = vergeml_organize_vectors( (int) $run['cursor'], $batch, $params['scope'] );

    $packed = '';
    $ids    = array();
    $tags   = array();
    $kinds  = array();
    $docs   = array();

    foreach ( $rows as $row ) {

        foreach ( vergeml_organize_project( $row['vector'], $dims ) as $value ) {
            $packed .= pack( 'f', $value );
        }

        $ids[]   = $row['id'];
        $tags[]  = $row['tags'];
        $kinds[] = $row['kind'];
        $docs[]  = $row['doc'];

        $run['cursor'] = max( (int) $run['cursor'], (int) $row['id'] );
    }

    if ( $ids ) {
        $params['work'][] = array(
            'v'     => base64_encode( $packed ),
            'ids'   => $ids,
            'tags'  => $tags,
            'kinds' => $kinds,
            'docs'  => $docs,
        );
    }

    $params['loaded'] += count( $ids );

    $elapsed = ( microtime( true ) - $started ) * 1000;

    $params['timing']['load_ms'] += $elapsed;
    $params['timing']['load_n']  += count( $ids );

    /*
     *  The next batch is sized from the rate this host just demonstrated, not
     *  from a constant chosen on a laptop. Ten seconds of real work on their
     *  server beats any number written here.
     */
    if ( count( $ids ) > 0 && $elapsed > 0 ) {
        $per            = $elapsed / count( $ids );
        $params['batch'] = (int) max( VERGEML_ORGANIZE_BATCH_MIN, min( VERGEML_ORGANIZE_BATCH_MAX, floor( VERGEML_ORGANIZE_STEP_MS / max( 0.01, $per ) ) ) );
    }

    // A short batch means the walk reached the end. The count is what the
    // query returned, so a library that shrank mid-run still terminates.
    if ( count( $rows ) < $batch ) {

        $params['phase'] = 'cluster';
        $run['n']        = (int) $params['loaded'];

        // The whole library is the first job. Everything after it is a
        // shrinking pile.
        $params['jobs'] = $params['loaded'] > 0
            ? array( array( 'members' => range( 0, $params['loaded'] - 1 ), 'depth' => 0, 'parent' => '' ) )
            : array();
    }

    $run['params'] = $params;

    return $run;
}


/**
 *  One split.
 *
 *  k is not chosen. The library is cut into a handful of groups, and any group
 *  still bigger than a folder should be is cut again -- which is why this is a
 *  queue rather than a recursion: each job is one bounded piece of work, and a
 *  step does exactly one of them.
 */

function vergeml_organize_step_cluster( $run, $started ) {

    $params = $run['params'];
    $limits = $params['limits'];

    $points  = vergeml_organize_work_points( $params['work'] );
    $vectors = vergeml_organize_work_vectors( $params['work'], (int) $params['dims'] );

    $job = array_shift( $params['jobs'] );

    if ( null === $job ) {
        $params['phase'] = 'finish';
        $run['params']   = $params;
        return $run;
    }

    $members = array_values( array_map( 'intval', $job['members'] ) );
    $depth   = (int) $job['depth'];
    $parent  = (string) $job['parent'];

    $global = vergeml_organize_global_tags( $points );

    $k      = min( $limits['width'], count( $members ) );
    $result = vergeml_organize_kmeans( $members, $k, $vectors );

    $clusters = array();

    foreach ( $members as $i => $index ) {
        $clusters[ $result['assignment'][ $i ] ][] = $index;
    }

    ksort( $clusters );

    // Named together rather than one at a time, so no two branches of this
    // split come out with the same name -- nor the same name as a folder they
    // sit inside.
    if ( ! isset( $params['names'] ) || ! is_array( $params['names'] ) ) {
        $params['names'] = array();
    }

    $labels = vergeml_organize_distinct_labels(
        $clusters,
        $global,
        $points,
        $parent ? $params['branches'][ $parent ]['path'] : array(),
        $params['names'],
        count( $points )
    );

    foreach ( $clusters as $c => $cluster_members ) {

        $centroid = $result['centroids'][ $c ];

        /*
         *  Too small to be a folder: the members fold into the branch above
         *  rather than becoming a folder of three.
         *
         *  Only where there *is* a branch above. At the top level the parent
         *  is the library itself, and folding into it means leaving the files
         *  unfiled -- which sends every small top-level group to "Needs a
         *  look". The first run did exactly that: nine of fifty-eight files,
         *  most of them coherent little groups rather than oddballs. That is
         *  the catch-all under another name, which the plan names as the thing
         *  this branch must not become, so a top-level cluster is a branch
         *  however small and "Needs a look" keeps one meaning: too far from
         *  everything to belong anywhere.
         */
        if ( count( $cluster_members ) < $limits['min_branch'] && count( $clusters ) > 1 && '' !== $parent ) {

            foreach ( $cluster_members as $index ) {
                $params['branches'][ $parent ]['members'][] = vergeml_organize_member(
                    $index,
                    $centroid,
                    $vectors,
                    $params['branches'][ $parent ]['label'],
                    vergeml_organize_shared_tags( $cluster_members, $points ),
                    true
                );
            }

            $params['branches'][ $parent ]['size'] = count( $params['branches'][ $parent ]['members'] );

            continue;
        }

        $label = $labels[ $c ];
        $path  = $parent ? array_merge( $params['branches'][ $parent ]['path'], array( $label ) ) : array( $label );
        $key   = implode( ' / ', $path );

        /*
         *  Siblings are already named apart, but two different splits can
         *  still arrive at the same path -- and a branch key that is not
         *  unique means the second branch silently overwrites the first.
         */
        $suffix = 2;
        while ( isset( $params['branches'][ $key ] ) ) {
            $key = implode( ' / ', $path ) . ' (' . $suffix . ')';
            $suffix++;
        }

        /*
         *  `count( $clusters ) > 1` is not belt and braces. A pile of
         *  near-identical vectors -- a stock-photo dump, the shape the plan
         *  names as a risk -- comes back as one cluster however often it is
         *  asked, so splitting it again would nest the same members under the
         *  same label until the depth cap. It stops here instead, flagged, and
         *  says so.
         */
        $split = count( $cluster_members ) > $limits['max_branch']
              && ( $depth + 1 ) < $limits['max_depth']
              && count( $clusters ) > 1;

        $params['branches'][ $key ] = array(
            'key'     => $key,
            'label'   => $label,
            'path'    => $path,
            'depth'   => $depth,
            'parent'  => $parent,
            'size'    => 0,
            'total'   => count( $cluster_members ),
            'members' => array(),
            /*
             *  A branch that stopped because it ran out of depth rather than
             *  because it was small enough is flagged. Phase 3 shows it as
             *  needing attention: a folder the plugin is not confident about
             *  should not look like one it is.
             */
            'capped'  => ! $split && count( $cluster_members ) > $limits['max_branch'],
            'reason'  => '',
        );

        if ( $split ) {
            $params['jobs'][] = array( 'members' => $cluster_members, 'depth' => $depth + 1, 'parent' => $key );
            continue;
        }

        // A leaf. This is where a member's distance from the centre is final,
        // so it is the only place outliers are worth judging -- doing it at
        // every level would strip the same file three times.
        $shared    = vergeml_organize_shared_tags( $cluster_members, $points );
        $distances = array();

        foreach ( $cluster_members as $index ) {
            $distances[ $index ] = sqrt( vergeml_organize_distance( $vectors[ $index ], $centroid ) );
        }

        $cutoff = vergeml_organize_cutoff( $distances, $limits['outlier'] );

        foreach ( $cluster_members as $index ) {

            if ( $cutoff > 0 && $distances[ $index ] > $cutoff ) {
                $params['outliers'][] = $index;
                continue;
            }

            $params['branches'][ $key ]['members'][] = vergeml_organize_member( $index, $centroid, $vectors, $label, $shared, false );
        }

        $params['branches'][ $key ]['size']  = count( $params['branches'][ $key ]['members'] );
        $params['branches'][ $key ]['total'] = $params['branches'][ $key ]['size'];

        $params['branches'][ $key ]['reason'] = sprintf(
            /* translators: 1: number of files, 2: comma-separated list of tags. */
            _n(
                '%1$d file whose description clusters here; named for %2$s, which the rest of the library mostly does not share.',
                '%1$d files whose descriptions cluster together; named for %2$s, which the rest of the library mostly does not share.',
                count( $params['branches'][ $key ]['members'] ),
                'vergelabs-media-library'
            ),
            count( $params['branches'][ $key ]['members'] ),
            $shared ? implode( ', ', $shared ) : __( 'what the files are', 'vergelabs-media-library' )
        );
    }

    if ( ! $params['jobs'] ) {
        $params['phase'] = 'finish';
    }

    $params['timing']['cluster_ms'] += ( microtime( true ) - $started ) * 1000;
    $params['timing']['cluster_n']  += count( $members );

    $run['params'] = $params;

    return $run;
}


/**
 *  The last step: one "Needs a look", the branches in an order that is a
 *  property of the result, and enough of each branch hydrated for a screen to
 *  draw it.
 */

function vergeml_organize_step_finish( $run, $started ) {

    $params = $run['params'];

    $points = vergeml_organize_work_points( $params['work'] );

    $branches = array_values( $params['branches'] );

    /*
     *  Containers -- branches whose members all went to children -- carry the
     *  subtree count, so a screen can show a folder's weight without walking
     *  it.
     *
     *  Walked up the parent chain rather than up the path, because two
     *  branches can score the same label and their keys are then distinguished
     *  by a suffix the path does not carry. Summing by path would credit both
     *  of them with each other's files.
     */
    $totals = array();

    foreach ( $branches as $branch ) {

        $key   = $branch['key'];
        $guard = 0;

        while ( '' !== $key && $guard++ < 32 ) {
            $totals[ $key ] = isset( $totals[ $key ] ) ? $totals[ $key ] + $branch['size'] : $branch['size'];
            $key = isset( $params['branches'][ $key ]['parent'] ) ? (string) $params['branches'][ $key ]['parent'] : '';
        }
    }

    foreach ( $branches as $i => $branch ) {
        $branches[ $i ]['total'] = isset( $totals[ $branch['key'] ] ) ? $totals[ $branch['key'] ] : $branch['size'];
    }

    /*
     *  Exactly one "Needs a look", collected from every level.
     *
     *  The first attempt produced three, one per depth, which is worse than
     *  not having the idea at all: a person reading three folders with the
     *  same name learns nothing except that the plugin is confused.
     */
    $outliers = array_values( array_unique( array_map( 'intval', $params['outliers'] ) ) );
    sort( $outliers );

    $stray = array();

    foreach ( $outliers as $index ) {
        $stray[] = array(
            'id'       => $points[ $index ]['id'],
            'distance' => null,
            'why'      => __( 'Sits far from every group the library forms — worth a look rather than a guess.', 'vergelabs-media-library' ),
        );
    }

    // Emitted whether or not it holds anything: Phase 3 renders this branch
    // and a screen that has to cope with the key sometimes being absent is a
    // screen with a bug waiting in it.
    $branches[] = array(
        'key'     => 'needs-a-look',
        'label'   => __( 'Needs a look', 'vergelabs-media-library' ),
        'path'    => array( __( 'Needs a look', 'vergelabs-media-library' ) ),
        'depth'   => 0,
        'parent'  => '',
        'size'    => count( $stray ),
        'total'   => count( $stray ),
        'members' => $stray,
        'capped'  => false,
        'reason'  => __( 'Every library has files that fit nowhere. Forcing them into a category is how folders get confident, wrong names, so they are gathered here instead.', 'vergelabs-media-library' ),
    );

    /*
     *  A refine run re-clusters the branches it was asked about and copies the
     *  rest across untouched. It is still true that it never re-clusters the
     *  whole library to change one folder -- but the thing it produces is a
     *  whole proposal, because a diff against a fragment would report every
     *  file it did not look at as deleted.
     */
    if ( ! empty( $params['carry'] ) ) {

        $keys = array();

        foreach ( $branches as $branch ) {
            $keys[ $branch['key'] ] = true;
        }

        foreach ( $params['carry'] as $carried ) {

            $suffix = 2;

            while ( isset( $keys[ $carried['key'] ] ) ) {
                $carried['key'] = $carried['path'] ? implode( ' / ', $carried['path'] ) . ' (' . $suffix . ')' : 'carried-' . $suffix;
                $suffix++;
            }

            $keys[ $carried['key'] ] = true;
            $branches[]              = $carried;
        }
    }

    // Biggest first, then by label, so the order is a property of the result
    // rather than of the loop that produced it.
    usort( $branches, 'vergeml_organize_by_size' );

    // How tightly a branch agrees with itself, which Phase 3 renders beside
    // the count. Three buckets rather than a number: a distribution is a
    // shape, and a single figure would be read as a confidence score, which
    // this is not.
    foreach ( $branches as $i => $branch ) {
        $branches[ $i ]['agreement'] = vergeml_organize_agreement( $branch['members'] );
    }

    $branches = vergeml_organize_hydrate( $branches );

    $run['tree']   = $branches;
    $run['k']      = count( $branches );
    $run['status'] = 'done';

    /*
     *  On a finished run `n` is how many files the proposal covers, which for
     *  a refine run is the ones it clustered plus the ones it carried. During
     *  the load it was how many there were to load, because that is what a
     *  progress bar divides by; here it settles into the one meaning a reader
     *  of a stored run would expect.
     */
    $covered = 0;

    foreach ( $branches as $branch ) {
        $covered += (int) $branch['size'];
    }

    $run['n'] = $covered;

    $params['phase'] = 'done';
    $params['took']  = array(
        'load_ms'    => round( $params['timing']['load_ms'], 1 ),
        'cluster_ms' => round( $params['timing']['cluster_ms'], 1 ),
    );

    // The working vectors were scratch, not a result. A finished run holds its
    // tree and nothing else -- ten runs of ten thousand files would otherwise
    // be tens of megabytes of vectors nobody will read again.
    $params['work']     = array();
    $params['branches'] = array();
    $params['outliers'] = array();
    $params['jobs']     = array();
    $params['carry']    = array();

    $run['params'] = $params;

    return $run;
}


/**
 *  Biggest first, then by label, then by key.
 *
 *  The third comparison is not decoration: two branches can score the same
 *  label and the same size, and usort on PHP 7.4 is not stable, so without a
 *  total order the same tree comes back in a different order on a different
 *  host.
 */

function vergeml_organize_by_size( $a, $b ) {

    if ( $a['size'] !== $b['size'] ) {
        return $b['size'] - $a['size'];
    }

    $label = strcmp( $a['label'], $b['label'] );

    return 0 !== $label ? $label : strcmp( $a['key'], $b['key'] );
}


/**
 *  vergeml_organize_member
 *
 *  One assignment, with the line that says why it went where it went.
 *
 *  Stored rather than generated on read, because a tree nobody can interrogate
 *  is a tree nobody will accept, and a reason computed at render time is a
 *  reason that can disagree with the tree it explains.
 */

function vergeml_organize_member( $index, $centroid, $vectors, $label, $shared, $folded ) {

    global $vergeml_organize_points;

    $distance = round( sqrt( vergeml_organize_distance( $vectors[ $index ], $centroid ) ), 4 );

    if ( $folded ) {
        $why = sprintf(
            /* translators: 1: folder name, 2: distance from the group's centre. */
            __( 'Filed under “%1$s” — its own group was too small to be a folder (%2$.2f from the centre).', 'vergelabs-media-library' ),
            $label,
            $distance
        );
    } elseif ( $shared ) {
        $why = sprintf(
            /* translators: 1: folder name, 2: distance from the group's centre, 3: comma-separated tags. */
            __( 'Filed under “%1$s” — %2$.2f from the centre; shares %3$s.', 'vergelabs-media-library' ),
            $label,
            $distance,
            implode( ', ', $shared )
        );
    } else {
        $why = sprintf(
            /* translators: 1: folder name, 2: distance from the group's centre. */
            __( 'Filed under “%1$s” — %2$.2f from the centre; grouped by what the pictures look like rather than by a shared tag.', 'vergelabs-media-library' ),
            $label,
            $distance
        );
    }

    return array(
        'id'       => $vergeml_organize_points[ $index ]['id'],
        'distance' => $distance,
        'why'      => $why,
    );
}


/**
 *  A member this far from the centre is not really in the branch.
 *
 *  Measured against the branch's own median rather than an absolute number:
 *  a tight branch of product shots and a loose one of holiday photos have
 *  entirely different idea of "far", and a constant would call half of one and
 *  none of the other an outlier.
 */

function vergeml_organize_cutoff( $distances, $factor ) {

    $values = array_values( $distances );

    if ( count( $values ) < 4 ) {
        return 0.0; // too few to have a shape to be outside of
    }

    sort( $values );

    $median = $values[ (int) floor( count( $values ) / 2 ) ];

    return $median > 0 ? $median * (float) $factor : 0.0;
}


/**
 *  How tightly a branch agrees with itself, as a shape rather than a score.
 */

function vergeml_organize_agreement( $members ) {

    $distances = array();

    foreach ( $members as $member ) {
        if ( null !== $member['distance'] ) {
            $distances[] = (float) $member['distance'];
        }
    }

    if ( ! $distances ) {
        return array( 'close' => 0, 'mid' => 0, 'far' => 0 );
    }

    $max = max( $distances );

    if ( $max <= 0 ) {
        return array( 'close' => count( $distances ), 'mid' => 0, 'far' => 0 );
    }

    $out = array( 'close' => 0, 'mid' => 0, 'far' => 0 );

    foreach ( $distances as $distance ) {
        $share = $distance / $max;
        if ( $share <= 0.34 ) {
            $out['close']++;
        } elseif ( $share <= 0.67 ) {
            $out['mid']++;
        } else {
            $out['far']++;
        }
    }

    return $out;
}


/**
 *  vergeml_organize_hydrate
 *
 *  Three sample files per branch, with a title and a thumbnail, stored with
 *  the tree.
 *
 *  Two queries for the whole run, taken here rather than in the read endpoint:
 *  the ids are capped at three a branch, so this is bounded by the number of
 *  branches, and the read path stays at one query however large the tree is.
 */

function vergeml_organize_hydrate( $branches ) {

    $ids = array();

    foreach ( $branches as $branch ) {
        // Nearest the centre first, because the most typical member of a
        // folder is the one to show as its thumbnail.
        foreach ( array_slice( $branch['members'], 0, 3 ) as $member ) {
            $ids[] = (int) $member['id'];
        }
    }

    $ids = array_values( array_unique( $ids ) );

    if ( ! $ids ) {
        return $branches;
    }

    _prime_post_caches( $ids, false, true );

    $uploads = wp_get_upload_dir();

    foreach ( $branches as $i => $branch ) {

        $samples = array();

        foreach ( array_slice( $branch['members'], 0, 3 ) as $member ) {

            $id = (int) $member['id'];

            /*
             *  Asked of the cache the prime just filled, not of get_post().
             *
             *  Priming cannot cache a post that does not exist, and get_post()
             *  does not remember a miss -- so every id whose attachment has
             *  gone costs its own query, every time. With three samples a
             *  branch and a hundred branches that turned the finishing step
             *  into three hundred and twenty queries against a budget of four.
             *  An index row can outlive its file, so this is not hypothetical.
             */
            if ( ! wp_cache_get( $id, 'posts' ) ) {
                continue;
            }

            // Cached by the prime above, so this costs nothing.
            $post = get_post( $id );

            if ( ! $post ) {
                continue;
            }

            $relative = (string) get_post_meta( $id, '_wp_attached_file', true );
            $meta     = wp_get_attachment_metadata( $id );
            $thumb    = '';

            if ( is_array( $meta ) && ! empty( $meta['sizes']['thumbnail']['file'] ) && '' !== $relative ) {
                $thumb = trailingslashit( $uploads['baseurl'] ) . trailingslashit( dirname( $relative ) ) . $meta['sizes']['thumbnail']['file'];
            } elseif ( '' !== $relative ) {
                $thumb = trailingslashit( $uploads['baseurl'] ) . $relative;
            }

            $samples[] = array(
                'id'    => $id,
                'title' => (string) $post->post_title,
                'thumb' => $thumb,
            );
        }

        $branches[ $i ]['samples'] = $samples;
    }

    return $branches;
}


/* ------------------------------------------------------- the working store */

/*
 *  The points are read by vergeml_organize_member(), which is called from
 *  inside two loops and would otherwise have to be handed the whole set on
 *  every call. A global rather than a parameter for exactly that reason, and
 *  it is written in one place.
 */
$GLOBALS['vergeml_organize_points'] = array();


/**
 *  vergeml_organize_work_points
 *
 *  The per-file metadata the labelling and the reasons need: id, tags, kind,
 *  document type. Not the vectors -- those come back packed from the function
 *  below, because holding both as PHP arrays would double the one figure this
 *  phase is actually constrained by.
 */

function vergeml_organize_work_points( $work ) {

    $points = array();

    foreach ( (array) $work as $chunk ) {
        foreach ( $chunk['ids'] as $i => $id ) {
            $points[] = array(
                'id'   => (int) $id,
                'tags' => isset( $chunk['tags'][ $i ] ) ? (array) $chunk['tags'][ $i ] : array(),
                'kind' => isset( $chunk['kinds'][ $i ] ) ? (string) $chunk['kinds'][ $i ] : '',
                'doc'  => isset( $chunk['docs'][ $i ] ) ? (string) $chunk['docs'][ $i ] : '',
            );
        }
    }

    $GLOBALS['vergeml_organize_points'] = $points;

    return $points;
}


function vergeml_organize_work_vectors( $work, $dims ) {

    $vectors = array();
    $dims    = max( 1, (int) $dims );

    foreach ( (array) $work as $chunk ) {

        $raw = base64_decode( $chunk['v'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- this plugin's own packed floats, written by the step before.

        // One unpack for the whole chunk rather than one per file. At ten
        // thousand files the difference is ten thousand calls into the
        // extension against twenty, and this runs on every cluster step.
        $floats = unpack( 'f*', $raw );

        if ( ! $floats ) {
            continue;
        }

        foreach ( array_chunk( array_values( $floats ), $dims ) as $vector ) {
            if ( count( $vector ) === $dims ) {
                $vectors[] = $vector;
            }
        }
    }

    return $vectors;
}


function vergeml_organize_global_tags( $points ) {

    $global = array();

    foreach ( $points as $point ) {
        foreach ( array_unique( $point['tags'] ) as $tag ) {
            $global[ $tag ] = isset( $global[ $tag ] ) ? $global[ $tag ] + 1 : 1;
        }
    }

    return $global;
}


/* ---------------------------------------------------------------- estimates */

/**
 *  vergeml_organize_estimate
 *
 *  How long the rest of this will take, extrapolated from work actually
 *  performed on this host.
 *
 *  Time is measured, not predicted. Ten seconds of real work on their server
 *  beats any constant that could be written here, and every step returns an
 *  updated figure -- so the number shown gets better as it goes rather than
 *  being a guess fixed at the start.
 */

function vergeml_organize_estimate( $done, $elapsed_ms, $remaining ) {

    $done      = max( 0, (int) $done );
    $remaining = max( 0, (int) $remaining );

    if ( $done <= 0 || $elapsed_ms <= 0 ) {
        return array(
            'known'        => false,
            'per_item_ms'  => 0.0,
            'remaining_ms' => 0,
        );
    }

    $per = (float) $elapsed_ms / $done;

    return array(
        'known'        => true,
        'per_item_ms'  => round( $per, 4 ),
        'remaining_ms' => (int) round( $per * $remaining ),
    );
}


/**
 *  What one step tells its caller. Progress, what is left, an estimate that
 *  improves, and the partial tree -- so a caller that stops still has
 *  something to show.
 */

function vergeml_organize_report( $run, $started ) {

    $params    = $run['params'];
    $phase     = isset( $params['phase'] ) ? $params['phase'] : 'load';
    $done      = in_array( $run['status'], array( 'done', 'cancelled', 'failed' ), true );
    $remaining = 0;

    if ( 'load' === $phase ) {
        $remaining = max( 0, (int) $run['n'] - (int) $params['loaded'] );
    } elseif ( 'cluster' === $phase ) {
        $remaining = count( $params['jobs'] );
    }

    $estimate = vergeml_organize_estimate(
        (int) $params['timing']['load_n'],
        (float) $params['timing']['load_ms'],
        'load' === $phase ? $remaining : 0
    );

    return array(
        'run_id'       => (int) $run['run_id'],
        'status'       => (string) $run['status'],
        'phase'        => $phase,
        'done'         => $done,
        'n'            => (int) $run['n'],
        'loaded'       => (int) $params['loaded'],
        'remaining'    => $remaining,
        'cursor'       => (int) $run['cursor'],
        'batch'        => (int) $params['batch'],
        'step_ms'      => round( ( microtime( true ) - $started ) * 1000, 1 ),
        'estimate'     => $estimate,
        'memory'       => isset( $params['memory'] ) ? $params['memory'] : array(),
        'error'        => isset( $params['error'] ) ? $params['error'] : '',
        'partial_tree' => vergeml_organize_summary( $run ),
    );
}


/**
 *  The tree without the per-file rows: a step that returned every assignment
 *  would answer a poll with a megabyte.
 */

function vergeml_organize_summary( $run ) {

    $out = array();

    if ( $run['tree'] ) {
        foreach ( $run['tree'] as $branch ) {
            $out[] = vergeml_organize_branch_summary( $branch );
        }
        return $out;
    }

    foreach ( (array) $run['params']['branches'] as $branch ) {
        $out[] = vergeml_organize_branch_summary( $branch );
    }

    return $out;
}


function vergeml_organize_branch_summary( $branch ) {

    return array(
        'label'  => $branch['label'],
        'path'   => $branch['path'],
        'size'   => (int) $branch['size'],
        'total'  => isset( $branch['total'] ) ? (int) $branch['total'] : (int) $branch['size'],
        'capped' => ! empty( $branch['capped'] ),
    );
}


/* ------------------------------------------------------------------ diffing */

/**
 *  vergeml_organize_diff
 *
 *  What changed between two runs: branches added, removed and renamed, and the
 *  files that moved between them.
 *
 *  Computed from the stored trees, never by re-running. Two runs of the same
 *  library are byte-identical by construction, so a diff that showed anything
 *  would be a bug -- and a diff computed by re-clustering could not tell the
 *  difference between a real change and a warm cache.
 */

function vergeml_organize_diff( $a, $b ) {

    $left  = is_array( $a ) ? $a : vergeml_organize_run_get( (int) $a );
    $right = is_array( $b ) ? $b : vergeml_organize_run_get( (int) $b );

    if ( ! $left || ! $right ) {
        return new WP_Error( 'vergeml_organize_no_run', __( 'One of those runs does not exist.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    $before = vergeml_organize_placement( $left['tree'] );
    $after  = vergeml_organize_placement( $right['tree'] );

    $matched = array();
    $renamed = array();
    $added   = array();
    $removed = array();

    /*
     *  Branches are matched by who is in them, not by what they are called.
     *  A branch that kept its members and changed its name is a rename; one
     *  that kept its name and changed its members is two different branches
     *  wearing the same label, and calling that "unchanged" would hide the
     *  only thing worth seeing.
     */
    foreach ( $before['branches'] as $key => $members ) {

        /*
         *  An empty branch shares no members with anything, so overlap can
         *  never match it -- and "Needs a look" is emitted on every run and is
         *  empty whenever nothing needed one. Without this, the diff of a run
         *  against itself reported that branch as removed and added at the
         *  same time, which is the one answer a self-diff must never give.
         */
        if ( ! $members ) {

            if ( isset( $after['branches'][ $key ] ) && ! $after['branches'][ $key ] && ! isset( $matched[ $key ] ) ) {
                $matched[ $key ] = $key;
            } else {
                $removed[] = $key;
            }

            continue;
        }

        $best   = '';
        $best_j = 0.0;

        foreach ( $after['branches'] as $other => $other_members ) {

            if ( isset( $matched[ $other ] ) ) {
                continue;
            }

            $shared = count( array_intersect( $members, $other_members ) );

            if ( 0 === $shared ) {
                continue;
            }

            $union = count( array_unique( array_merge( $members, $other_members ) ) );
            $j     = $union > 0 ? $shared / $union : 0.0;

            // Ties break to the lower key, so the diff is a property of the
            // trees and not of the order two arrays happened to be built in.
            if ( $j > $best_j || ( $j === $best_j && '' !== $best && strcmp( $other, $best ) < 0 ) ) {
                $best_j = $j;
                $best   = $other;
            }
        }

        if ( '' === $best || $best_j < 0.5 ) {
            $removed[] = $key;
            continue;
        }

        $matched[ $best ] = $key;

        if ( $best !== $key ) {
            $renamed[] = array( 'from' => $key, 'to' => $best, 'overlap' => round( $best_j, 3 ) );
        }
    }

    foreach ( array_keys( $after['branches'] ) as $key ) {
        if ( ! isset( $matched[ $key ] ) ) {
            $added[] = $key;
        }
    }

    $moved = array();

    foreach ( $before['files'] as $id => $key ) {

        if ( ! isset( $after['files'][ $id ] ) ) {
            continue; // gone from the library, not moved within it
        }

        $now = $after['files'][ $id ];

        // A file whose branch was renamed did not move; its folder did.
        if ( $now === $key || ( isset( $matched[ $now ] ) && $matched[ $now ] === $key ) ) {
            continue;
        }

        $moved[] = array( 'id' => (int) $id, 'from' => $key, 'to' => $now );
    }

    sort( $added );
    sort( $removed );

    return array(
        'a'       => is_array( $a ) ? 0 : (int) $a,
        'b'       => is_array( $b ) ? 0 : (int) $b,
        'added'   => $added,
        'removed' => $removed,
        'renamed' => $renamed,
        'moved'   => $moved,
        'same'    => ! $added && ! $removed && ! $renamed && ! $moved,
    );
}


function vergeml_organize_placement( $tree ) {

    $branches = array();
    $files    = array();

    foreach ( (array) $tree as $branch ) {

        /*
         *  The branch's own key, not its path. Two sibling branches can share
         *  a path -- they are told apart by the suffix on the key -- and
         *  keying this by path silently merged them, so a split that produced
         *  six folders read here as one and the diff reported that nothing had
         *  moved.
         */
        $key = isset( $branch['key'] ) && '' !== $branch['key'] ? (string) $branch['key'] : implode( ' / ', $branch['path'] );
        $ids = array();

        foreach ( $branch['members'] as $member ) {
            $ids[] = (int) $member['id'];
            $files[ (int) $member['id'] ] = $key;
        }

        sort( $ids );

        $branches[ $key ] = $ids;
    }

    ksort( $branches );
    ksort( $files );

    return array( 'branches' => $branches, 'files' => $files );
}


/* ------------------------------------------------------------------ pruning */

/**
 *  vergeml_organize_prune
 *
 *  Keep the last ten runs; delete the rest.
 *
 *  The one destructive act in this phase, and deliberately the narrowest one
 *  that could be written: a DELETE against this file's own table, keyed on
 *  run_id, with no path from here to posts or postmeta. A tree for ten
 *  thousand files is a large blob and ten of them is why this exists.
 */

function vergeml_organize_prune( $keep = VERGEML_ORGANIZE_KEEP ) {

    global $wpdb;

    $keep = max( 1, (int) $keep );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $cutoff = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT run_id FROM {$wpdb->vergeml_organize_runs} ORDER BY run_id DESC LIMIT %d, 1",
        $keep
    ) );

    if ( $cutoff <= 0 ) {
        return 0;
    }

    return (int) $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->vergeml_organize_runs} WHERE run_id <= %d",
        $cutoff
    ) );
    // phpcs:enable
}


/* ------------------------------------------------------------------- quote */

/**
 *  vergeml_organize_quote
 *
 *  What a first organise run would cost, counted rather than estimated.
 *
 *  The duplicate scan runs before the first describe run and its result is the
 *  quote: it costs nothing -- no service call, no credit, just hashing files
 *  on disk -- and running it first means the number shown is counted. This
 *  refuses rather than quoting a figure it cannot stand behind.
 *
 *  It is also the strongest thing a free tier can do: tell somebody something
 *  true and useful about their own library before asking for money.
 */

function vergeml_organize_quote() {

    global $wpdb;

    $state = function_exists( 'vergeml_health_state' ) ? vergeml_health_state() : array();

    if ( empty( $state['finished'] ) ) {
        return new WP_Error(
            'vergeml_organize_unscanned',
            __( 'Run the duplicate scan first. Until it has, any number here would be an estimate rather than a count.', 'vergelabs-media-library' ),
            array( 'status' => 409 )
        );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $images = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE %s",
        $wpdb->esc_like( 'image/' ) . '%'
    ) );

    $described = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND described_at IS NOT NULL" );
    // phpcs:enable

    /*
     *  Every copy after the first in a byte-identical group. Describing the
     *  second copy of a file costs a credit to be told what the first one
     *  already said.
     */
    $copies = 0;
    $copy_ids = array();

    foreach ( vergeml_health_exact_groups() as $group ) {
        $rest = array_slice( $group, 1 );
        $copies += count( $rest );
        foreach ( $rest as $id ) {
            $copy_ids[] = (int) $id;
        }
    }

    // A copy that is already described is not skipped twice. Counting it in
    // both figures would make the top line on a screen whose whole job is to
    // be believed smaller than the truth.
    $copies_described = 0;

    if ( $copy_ids ) {

        $placeholders = implode( ',', array_fill( 0, count( $copy_ids ), '%d' ) );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $copies_described = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index}
              WHERE error = '' AND described_at IS NOT NULL AND attachment_id IN ( $placeholders )",
            $copy_ids
        ) );
        // phpcs:enable
    }

    $skipped     = $described + max( 0, $copies - $copies_described );
    $to_describe = max( 0, $images - $skipped );

    $limits = vergeml_organize_limits();

    return array(
        'scanned'      => true,
        'files'        => $images,
        'duplicates'   => $copies,
        'described'    => $described,
        'skipped'      => $skipped,
        'to_describe'  => $to_describe,
        /*
         *  One credit a description, and the embedding is not a second one:
         *  a first organise run embeds the whole library and describes only
         *  enough files per branch to name it. Tens of descriptions rather
         *  than thousands.
         */
        'credits'      => $to_describe,
        'memory'       => vergeml_organize_memory( $images, $limits['dims'] ),
        'fits'         => 0 !== vergeml_organize_fit_dims( $images, $limits['dims'] ),
    );
}


/* --------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_organize_routes' );

function vergeml_organize_routes() {

    register_rest_route( VERGEML_REST_NS, '/organize-step', array(
        'methods'             => WP_REST_Server::CREATABLE,
        // Proposing a tree writes nothing but this feature's own rows, and it
        // is the same curation bar as the scans rather than the one that
        // governs uploading.
        'permission_callback' => function () {
            return current_user_can( 'manage_categories' );
        },
        'callback'            => 'vergeml_organize_rest_step',
        'args'                => array(
            'run_id'        => array( 'type' => 'integer', 'default' => 0 ),
            'parent_run_id' => array( 'type' => 'integer', 'default' => 0 ),
            'refine'        => array( 'type' => 'object', 'default' => array() ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/organize-cancel', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => function () {
            return current_user_can( 'manage_categories' );
        },
        'callback'            => 'vergeml_organize_rest_cancel',
        'args'                => array(
            'run_id' => array( 'type' => 'integer', 'required' => true ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/organize-run', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => function () {
            return current_user_can( 'manage_categories' );
        },
        'callback'            => 'vergeml_organize_rest_run',
        'args'                => array(
            'run_id'  => array( 'type' => 'integer', 'default' => 0 ),
            'compare' => array( 'type' => 'integer', 'default' => 0 ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/organize-quote', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => function () {
            return current_user_can( 'manage_categories' );
        },
        'callback'            => 'vergeml_organize_rest_quote',
    ) );
}


function vergeml_organize_rest_step( WP_REST_Request $request ) {

    $refine = $request->get_param( 'refine' );
    $parent = (int) $request->get_param( 'parent_run_id' );
    $run_id = (int) $request->get_param( 'run_id' );

    $args = array(
        'run_id'        => $run_id,
        'parent_run_id' => $parent,
        'refine'        => is_array( $refine ) ? $refine : array(),
    );

    /*
     *  Per-branch regeneration: a run that names a parent and a branch to
     *  split re-clusters that branch's members and nothing else. Re-running
     *  the whole library to change one folder is what makes a review screen
     *  unusable.
     */
    if ( ! $run_id && $parent > 0 ) {

        $plan = vergeml_organize_refine_plan( $parent, $args['refine'] );

        if ( is_wp_error( $plan ) ) {
            return $plan;
        }

        $args['scope'] = $plan['scope'];
        $args['carry'] = $plan['carry'];
    }

    $result = vergeml_organize_step( $args );

    return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
}


/**
 *  The ids a refine run works on.
 *
 *  Three verbs, deliberately, and no parser: `split` re-clusters a branch,
 *  `merge` folds a branch and its children back into one pile to be cut again,
 *  `keep` leaves it alone. A vocabulary this small is one a screen can offer
 *  as buttons, which is the point.
 */

function vergeml_organize_refine_plan( $parent_run_id, $refine ) {

    $parent = vergeml_organize_run_get( (int) $parent_run_id );

    if ( ! $parent ) {
        return new WP_Error( 'vergeml_organize_no_run', __( 'That organise run does not exist.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    $wanted = array();

    foreach ( (array) $refine as $branch => $verb ) {
        // `keep` is in the vocabulary because a screen needs a word for "leave
        // this one alone", but it names the default, so nothing here acts on
        // it.
        if ( in_array( $verb, array( 'split', 'merge' ), true ) ) {
            $wanted[ (string) $branch ] = $verb;
        }
    }

    if ( ! $wanted ) {
        return new WP_Error(
            'vergeml_organize_no_refine',
            __( 'Name at least one folder to split or merge.', 'vergelabs-media-library' ),
            array( 'status' => 400 )
        );
    }

    $ids   = array();
    $carry = array();

    foreach ( $parent['tree'] as $branch ) {

        $key  = implode( ' / ', $branch['path'] );
        $take = false;

        foreach ( $wanted as $name => $verb ) {
            // `merge` reaches the branch and everything under it, so the
            // subtree comes back as one pile to be cut again; `split` takes
            // the branch itself.
            if ( $key === $name || ( 'merge' === $verb && 0 === strpos( $key, $name . ' / ' ) ) ) {
                $take = true;
                break;
            }
        }

        if ( ! $take ) {
            $carry[] = $branch;
            continue;
        }

        foreach ( $branch['members'] as $member ) {
            $ids[] = (int) $member['id'];
        }
    }

    $ids = array_values( array_unique( $ids ) );
    sort( $ids );

    if ( ! $ids ) {
        return new WP_Error(
            'vergeml_organize_empty_refine',
            __( 'Those folders hold no files.', 'vergelabs-media-library' ),
            array( 'status' => 400 )
        );
    }

    /*
     *  The old "Needs a look" is not carried across. It is rebuilt from what
     *  this run finds, and copying the previous one would let a file sit in it
     *  twice -- once carried, once re-judged.
     */
    $kept = array();

    foreach ( $carry as $branch ) {
        if ( 'needs-a-look' !== $branch['key'] ) {
            $kept[] = $branch;
        }
    }

    return array( 'scope' => $ids, 'carry' => $kept );
}


/**
 *  Cancel is its own endpoint because a cancel that has to wait for the step
 *  it is cancelling is not a cancel. Two queries: read the row, write the
 *  flag.
 */

function vergeml_organize_rest_cancel( WP_REST_Request $request ) {

    global $wpdb;

    $run_id = (int) $request->get_param( 'run_id' );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $status = $wpdb->get_var( $wpdb->prepare(
        "SELECT status FROM {$wpdb->vergeml_organize_runs} WHERE run_id = %d",
        $run_id
    ) );
    // phpcs:enable

    if ( null === $status ) {
        return new WP_Error( 'vergeml_organize_no_run', __( 'That organise run does not exist.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    if ( 'running' !== $status ) {
        return rest_ensure_response( array( 'run_id' => $run_id, 'status' => (string) $status, 'cancelled' => false ) );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->vergeml_organize_runs} SET status = 'cancelled', updated_at = %s WHERE run_id = %d",
        current_time( 'mysql', true ),
        $run_id
    ) );
    // phpcs:enable

    return rest_ensure_response( array( 'run_id' => $run_id, 'status' => 'cancelled', 'cancelled' => true ) );
}


/**
 *  One run by id, or the latest. One query -- the samples were hydrated when
 *  the run finished, so the read path does not pay for them again, and a tree
 *  of ten thousand files costs the same as a tree of ten.
 */

function vergeml_organize_rest_run( WP_REST_Request $request ) {

    $run_id = (int) $request->get_param( 'run_id' );
    $run    = $run_id > 0 ? vergeml_organize_run_get( $run_id ) : vergeml_organize_run_latest();

    if ( ! $run ) {
        return new WP_Error( 'vergeml_organize_no_run', __( 'No organise run has been made yet.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    $out = array(
        'run_id'        => $run['run_id'],
        'parent_run_id' => $run['parent_run_id'],
        'status'        => $run['status'],
        'phase'         => isset( $run['params']['phase'] ) ? $run['params']['phase'] : '',
        'k'             => $run['k'],
        'n'             => $run['n'],
        'created_at'    => $run['created_at'],
        'updated_at'    => $run['updated_at'],
        'tree'          => $run['tree'] ? $run['tree'] : vergeml_organize_summary( $run ),
        'complete'      => (bool) $run['tree'],
        'took'          => isset( $run['params']['took'] ) ? $run['params']['took'] : array(),
    );

    $compare = (int) $request->get_param( 'compare' );

    if ( $compare > 0 ) {

        $diff = vergeml_organize_diff( $compare, $run['run_id'] );

        if ( is_wp_error( $diff ) ) {
            return $diff;
        }

        $out['diff'] = $diff;
    }

    return rest_ensure_response( $out );
}


function vergeml_organize_rest_quote() {

    $quote = vergeml_organize_quote();

    return is_wp_error( $quote ) ? $quote : rest_ensure_response( $quote );
}
