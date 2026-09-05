<?php
/**
 *  Folders: one conversation, one tree, one Move.
 *
 *  The screen (js/vergeml-folders.js) streams its conversation straight from
 *  the service with a short-lived token this file mints with the licence key;
 *  the browser never holds the key. Each finished turn comes back here and is
 *  persisted in the session, keyed by term id, so a reload shows the same
 *  conversation and the same draft. The Rules tab is the four deterministic
 *  ways to build the same draft without a model. Move goes through the
 *  resumable re-filing in core/folder-talk.php, and its undo.
 *
 *  Design: docs/superpowers/specs/2026-09-05-folders-screen-design.md.
 *
 *  @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const VERGEML_GUIDE_OPTION      = 'vergeml_guide_session';
const VERGEML_GUIDE_TURN_CAP    = 25;
const VERGEML_GUIDE_SAMPLE      = 2000;
/** A token this close to its end is minted again rather than handed out. */
const VERGEML_GUIDE_TOKEN_SLACK = 120;
/** A rule's answer is kept this long for the same library and the same folders. */
const VERGEML_GUIDE_RULE_CACHE  = 600;


/* --------------------------------------------------------------- the page */

/*
 *  The guide's own page is gone; its address lands on Folders.
 *
 *  On admin_menu, late, not admin_init: wp-admin/menu.php refuses an
 *  unregistered ?page= before admin_init ever fires, so a redirect hooked
 *  there never ran. Inside admin_menu the check has not happened yet.
 */
add_action( 'admin_menu', 'vergeml_guide_redirect', 999 );

function vergeml_guide_redirect() {
    if ( isset( $_GET['page'] ) && 'media-guide' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, which screen this is.
        wp_safe_redirect( admin_url( 'admin.php?page=media-librarian' ) );
        exit;
    }
}

add_action( 'admin_menu', 'vergeml_folders_menu', 13 );

function vergeml_folders_menu() {

    if ( ! defined( 'VERGEML_MENU' ) ) {
        return;
    }

    add_submenu_page(
        VERGEML_MENU,
        __( 'Folders', 'vergelabs-media-library' ),
        __( 'Folders', 'vergelabs-media-library' ),
        'manage_categories',
        'media-librarian',
        'vergeml_folders_page'
    );
}

add_action( 'admin_enqueue_scripts', 'vergeml_folders_assets' );

function vergeml_folders_assets( $hook ) {

    if ( false === strpos( (string) $hook, 'media-librarian' ) ) {
        return;
    }

    $boot = vergeml_folders_boot();

    wp_enqueue_style( 'vergeml-tree-view', plugins_url( 'css/vergeml-tree-view.css', VERGEML_FILE ), array(), vergeml_asset_ver( 'css/vergeml-tree-view.css' ) );
    wp_style_add_data( 'vergeml-tree-view', 'rtl', 'replace' );
    wp_enqueue_style( 'vergeml-folders', plugins_url( 'css/vergeml-folders.css', VERGEML_FILE ), array( 'vergeml-tree-view' ), vergeml_asset_ver( 'css/vergeml-folders.css' ) );
    wp_style_add_data( 'vergeml-folders', 'rtl', 'replace' );

    wp_enqueue_script( 'vergeml-tree-view', plugins_url( 'js/vergeml-tree-view.js', VERGEML_FILE ), array(), vergeml_asset_ver( 'js/vergeml-tree-view.js' ), true );
    wp_enqueue_script( 'vergeml-folders', plugins_url( 'js/vergeml-folders.js', VERGEML_FILE ), array( 'wp-api-fetch', 'wp-i18n', 'vergeml-tree-view' ), vergeml_asset_ver( 'js/vergeml-folders.js' ), true );
    wp_set_script_translations( 'vergeml-folders', 'vergelabs-media-library' );

    /*
     *  Everything the first paint needs travels with the page: the session,
     *  the tree and the stamp. No request stands between the page and its
     *  first paint (Gate 5, tests/ui/folders.spec.mjs).
     */
    wp_localize_script( 'vergeml-folders', 'vgmlFolders', array(
        'ns'        => VERGEML_REST_NS,
        'cap'       => VERGEML_GUIDE_TURN_CAP,
        'described' => (int) $boot['facts']['pictures'],
        'licensed'  => (bool) $boot['licensed'],
        'taxonomy'  => (string) $boot['taxonomy'],
        'session'   => $boot['session'],
        'nodes'     => $boot['nodes'],
        'version'   => (int) $boot['version'],
        'facts'     => $boot['facts'],
        'undo'      => $boot['undo'],
        'aiUrl'     => admin_url( 'admin.php?page=media-ai' ),
        'licenceUrl'=> admin_url( 'admin.php?page=media-licence' ),
        'walk'      => (bool) apply_filters( 'vergeml_folders_walk', false ),
    ) );
}

/**
 *  What the page and its script both need, computed once per request.
 *
 *  Queries: the facts (one), the terms (one, plus their meta), the session
 *  (one option, not autoloaded), the version stamp (one).
 */
function vergeml_folders_boot() {

    static $boot = null;

    if ( null !== $boot ) {
        return $boot;
    }

    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
    $nodes    = vergeml_folders_nodes( $taxonomy );
    $facts    = vergeml_folders_facts( $taxonomy, count( $nodes ) );
    $session  = vergeml_guide_session();
    $settings = function_exists( 'vergeml_ai_settings' ) ? vergeml_ai_settings() : array();

    $boot = array(
        'taxonomy' => $taxonomy,
        'nodes'    => $nodes,
        'version'  => function_exists( 'vergeml_folders_version' ) ? vergeml_folders_version() : 0,
        'facts'    => $facts,
        'session'  => vergeml_guide_session_out( $session ),
        'undo'     => function_exists( 'vergeml_talk_undo_available' ) ? vergeml_talk_undo_available() : array( 'available' => false, 'until' => 0 ),
        'licensed' => function_exists( 'vergeml_ai_unseal' ) && '' !== (string) ( isset( $settings['license_key'] ) ? vergeml_ai_unseal( $settings['license_key'] ) : '' ),
    );

    return $boot;
}

/** The tree as the /tree route sends it, without the request. */
function vergeml_folders_nodes( $taxonomy ) {

    if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
        return array();
    }

    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

    if ( is_wp_error( $terms ) ) {
        return array();
    }

    $nodes = array();

    foreach ( $terms as $term ) {
        $order   = defined( 'VERGEML_TERM_ORDER' ) ? get_term_meta( $term->term_id, VERGEML_TERM_ORDER, true ) : '';
        $nodes[] = array(
            'id'     => (int) $term->term_id,
            'parent' => (int) $term->parent,
            'name'   => (string) $term->name,
            'slug'   => (string) $term->slug,
            'count'  => (int) $term->count,
            'color'  => defined( 'VERGEML_TERM_COLOR' ) ? (string) get_term_meta( $term->term_id, VERGEML_TERM_COLOR, true ) : '',
            'order'  => '' === $order ? 0 : (int) $order,
        );
    }

    return $nodes;
}

/**
 *  The line under the title: pictures, folders, in no folder, described when.
 *  One query for the three numbers about pictures; the folder count comes
 *  from the terms already read.
 */
function vergeml_folders_facts( $taxonomy, $folders ) {

    global $wpdb;

    $out = array( 'pictures' => 0, 'folders' => (int) $folders, 'unfiled' => 0, 'described_at' => '' );

    if ( ! isset( $wpdb->vergeml_ai_index ) ) {
        return $out;
    }

    $t = $wpdb->vergeml_ai_index;

    if ( '' !== $taxonomy ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS n, MAX(i.described_at) AS last,
                    SUM( CASE WHEN NOT EXISTS (
                        SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                         WHERE tr.object_id = i.attachment_id AND tt.taxonomy = %s ) THEN 1 ELSE 0 END ) AS unfiled
               FROM {$t} i WHERE i.error = '' AND i.embedding IS NOT NULL",
            $taxonomy
        ), ARRAY_A );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
        $row = $wpdb->get_row( "SELECT COUNT(*) AS n, MAX(described_at) AS last, COUNT(*) AS unfiled FROM {$t} WHERE error = '' AND embedding IS NOT NULL", ARRAY_A );
    }

    if ( is_array( $row ) ) {
        $out['pictures']     = (int) $row['n'];
        $out['unfiled']      = (int) $row['unfiled'];
        $out['described_at'] = (string) $row['last'];
    }

    return $out;
}

/** "today 13:52", "yesterday 13:52", "3 September 13:52". */
function vergeml_folders_when( $ts ) {

    $ts = (int) $ts;
    if ( $ts <= 0 ) {
        return '';
    }
    $day   = wp_date( 'Y-m-d', $ts );
    $time  = wp_date( get_option( 'time_format', 'H:i' ), $ts );
    $today = wp_date( 'Y-m-d' );

    if ( $day === $today ) {
        /* translators: %s: a time, e.g. 13:52 */
        return sprintf( __( 'today %s', 'vergelabs-media-library' ), $time );
    }
    if ( $day === wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) ) {
        /* translators: %s: a time, e.g. 13:52 */
        return sprintf( __( 'yesterday %s', 'vergelabs-media-library' ), $time );
    }
    if ( $day === wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) ) {
        /* translators: %s: a time, e.g. 13:52 */
        return sprintf( __( 'tomorrow %s', 'vergelabs-media-library' ), $time );
    }
    return wp_date( 'j F', $ts ) . ' ' . $time;
}

function vergeml_folders_facts_line( $facts ) {

    $parts = array();

    if ( (int) $facts['pictures'] > 0 ) {
        /* translators: %s: a number of pictures */
        $parts[] = sprintf( _n( '%s picture', '%s pictures', (int) $facts['pictures'], 'vergelabs-media-library' ), number_format_i18n( (int) $facts['pictures'] ) );
    } else {
        $parts[] = __( 'No pictures described yet', 'vergelabs-media-library' );
    }
    /* translators: %s: a number of folders */
    $parts[] = sprintf( _n( '%s folder', '%s folders', (int) $facts['folders'], 'vergelabs-media-library' ), number_format_i18n( (int) $facts['folders'] ) );
    if ( (int) $facts['pictures'] > 0 ) {
        /* translators: %s: a number of pictures */
        $parts[] = sprintf( __( '%s in no folder', 'vergelabs-media-library' ), number_format_i18n( (int) $facts['unfiled'] ) );
        $when    = '' !== (string) $facts['described_at'] ? vergeml_folders_when( strtotime( (string) $facts['described_at'] . ' UTC' ) ) : '';
        if ( '' !== $when ) {
            /* translators: %s: when, e.g. "yesterday 13:52" */
            $parts[] = sprintf( __( 'described %s', 'vergelabs-media-library' ), $when );
        }
    }

    return implode( ' · ', $parts );
}

function vergeml_folders_page() {

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }

    $boot = vergeml_folders_boot();

    ?>
    <div class="wrap vgml-home vgml-librarian">
        <?php
        echo vergeml_pg_head( __( 'Folders', 'vergelabs-media-library' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
        ?>
        <p class="vgml-folders-facts"><?php echo esc_html( vergeml_folders_facts_line( $boot['facts'] ) ); ?></p>
        <div id="vgml-folders" class="vgml-folders" data-described="<?php echo esc_attr( (string) $boot['facts']['pictures'] ); ?>"></div>
    </div>
    <?php
}

/** How many pictures are described; the conversation only opens on a described library. */
function vergeml_guide_described_count() {
    global $wpdb;
    if ( ! isset( $wpdb->vergeml_ai_index ) ) {
        return 0;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL" );
}


/* ------------------------------------------------------------ the session */

/*
 *  One option, not autoloaded. Version 2 is the Folders screen's shape: the
 *  conversation as turns, the draft keyed by term id, the token the browser
 *  streams with, the Move in progress. An older session is dropped, not
 *  migrated: it named folders by name, which is what this replaces.
 */
function vergeml_guide_fresh() {
    return array(
        'version'         => 2,
        'started_at'      => time(),
        'updated_at'      => time(),
        'summary'         => null,
        'summary_key'     => '',
        'draft'           => null,
        'turns'           => array(),
        'assistant_turns' => 0,
        'token'           => null,
        'apply'           => null,
    );
}

function vergeml_guide_session() {
    $s = get_option( VERGEML_GUIDE_OPTION );
    return is_array( $s ) && isset( $s['version'] ) && (int) $s['version'] >= 2 ? array_merge( vergeml_guide_fresh(), $s ) : vergeml_guide_fresh();
}

function vergeml_guide_save( $session ) {
    $session['updated_at'] = time();
    // Bounded: the last sixty turns. The cap is on assistant turns; the count is kept apart from the list.
    $session['turns'] = array_slice( (array) ( isset( $session['turns'] ) ? $session['turns'] : array() ), -60 );
    update_option( VERGEML_GUIDE_OPTION, $session, false );
    return $session;
}

/** What the browser gets of the session: never the token's secret parts beyond the token itself, never the summary. */
function vergeml_guide_session_out( $s ) {
    return array(
        'turns'           => array_values( (array) $s['turns'] ),
        'draft'           => $s['draft'],
        'assistant_turns' => (int) $s['assistant_turns'],
        'cap'             => VERGEML_GUIDE_TURN_CAP,
        'apply'           => $s['apply'],
    );
}

function vergeml_guide_turn_add( &$s, $role, $kind, $text, $extra = array() ) {
    $turn = array_merge( array(
        'role' => 'assistant' === $role ? 'assistant' : 'user',
        'kind' => $kind,
        'text' => (string) $text,
        'at'   => time(),
    ), $extra );
    $s['turns'][] = $turn;
    return $turn;
}

/**
 *  A draft from the browser, made safe. Keys are client strings; a term id
 *  is a number or null; a parent is a key or ''; a name never carries a
 *  slash. Everything else the apply reads is text.
 */
function vergeml_guide_clean_draft( $in ) {

    if ( ! is_array( $in ) || ! isset( $in['folders'] ) || ! is_array( $in['folders'] ) ) {
        return null;
    }

    $key = function ( $k ) {
        return preg_replace( '/[^A-Za-z0-9:_\-.]/', '', (string) $k );
    };

    $out  = array( 'folders' => array(), 'gone' => array(), 'tags' => array(), 'origin' => 'talk', 'rule' => null );
    $keys = array();

    // The second axis the assistant proposes as tags rides along for the apply; the tree does not draw it.
    foreach ( (array) ( isset( $in['tags'] ) ? $in['tags'] : array() ) as $t ) {
        if ( is_array( $t ) && ! empty( $t['name'] ) ) {
            $out['tags'][] = array(
                'name'   => sanitize_text_field( (string) $t['name'] ),
                'values' => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( isset( $t['values'] ) ? $t['values'] : array() ) ) ) ),
            );
        }
    }

    foreach ( $in['folders'] as $f ) {
        if ( ! is_array( $f ) || '' === trim( (string) ( isset( $f['name'] ) ? $f['name'] : '' ) ) ) {
            continue;
        }
        $k = $key( isset( $f['key'] ) ? $f['key'] : '' );
        if ( '' === $k || isset( $keys[ $k ] ) ) {
            continue;
        }
        $keys[ $k ]       = true;
        $out['folders'][] = array(
            'key'      => $k,
            'term_id'  => ! empty( $f['term_id'] ) ? (int) $f['term_id'] : null,
            'name'     => str_replace( '/', '-', sanitize_text_field( (string) $f['name'] ) ),
            'parent'   => $key( isset( $f['parent'] ) ? $f['parent'] : '' ),
            'count'    => isset( $f['count'] ) && null !== $f['count'] && '' !== $f['count'] ? max( 0, (int) $f['count'] ) : null,
            'matches'  => sanitize_text_field( (string) ( isset( $f['matches'] ) ? $f['matches'] : '' ) ),
            'classes'  => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( isset( $f['classes'] ) ? $f['classes'] : array() ) ) ) ),
            'kinds'    => array_values( array_filter( array_map( 'sanitize_key', (array) ( isset( $f['kinds'] ) ? $f['kinds'] : array() ) ) ) ),
            'audience' => sanitize_text_field( (string) ( isset( $f['audience'] ) ? $f['audience'] : '' ) ),
            'by'       => isset( $f['by'] ) && 'you' === $f['by'] ? 'you' : '',
        );
    }
    foreach ( $out['folders'] as &$f ) {
        if ( '' !== $f['parent'] && ! isset( $keys[ $f['parent'] ] ) ) {
            $f['parent'] = '';
        }
    }
    unset( $f );
    foreach ( (array) ( isset( $in['gone'] ) ? $in['gone'] : array() ) as $tid => $to ) {
        $tid = (int) $tid;
        $to  = $key( $to );
        if ( $tid > 0 ) {
            $out['gone'][ $tid ] = isset( $keys[ $to ] ) ? $to : '';
        }
    }
    if ( isset( $in['origin'] ) && 'rule' === $in['origin'] && isset( $in['rule'] ) && is_array( $in['rule'] ) ) {
        $rule = vergeml_guide_rule_args( isset( $in['rule']['id'] ) ? $in['rule']['id'] : '', isset( $in['rule']['options'] ) ? $in['rule']['options'] : array() );
        if ( $rule ) {
            $out['origin'] = 'rule';
            $out['rule']   = $rule;
        }
    }

    return $out;
}


/* ------------------------------------------------------------ the summary */

/**
 *  What the describer saw, from what already exists: SQL over the catalogue
 *  and the groups the chat computes. Never the full records into a model.
 *  Sent to the service with the token request and again with every turn;
 *  the token is bound to its hash.
 */
function vergeml_guide_summary() {
    global $wpdb;
    $t = $wpdb->vergeml_ai_index;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $total      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND embedding IS NOT NULL" );
    $last       = (string) $wpdb->get_var( "SELECT MAX(described_at) FROM {$t} WHERE error = ''" );
    $kinds      = $wpdb->get_results( "SELECT kind, COUNT(*) AS n FROM {$t} WHERE error = '' AND embedding IS NOT NULL GROUP BY kind", ARRAY_A );
    // "audience":"" is the empty answer; only a real value counts.
    $n_audience = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND filing LIKE '%\"audience\":\"%' AND filing NOT LIKE '%\"audience\":\"\"%'" );
    $n_brand    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND ( filing LIKE '%\"brand\":\"_%' OR tags REGEXP '(^|,)[[:space:]]*(apple|samsung|dell|lg|nike|adidas|sony|hp|lenovo|asus|logitech|canon|nikon)' )" );
    $n_size     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND ( caption REGEXP '[0-9]+(\\\\.[0-9]+)? ?(inch|\"|cm|mm)' OR filing REGEXP '[0-9]+(\\\\.[0-9]+)? ?(inch|\"|cm|mm)' )" );
    $rows       = $wpdb->get_results( $wpdb->prepare( "SELECT filing FROM {$t} WHERE error = '' AND filing IS NOT NULL AND filing <> '' ORDER BY described_at DESC LIMIT %d", VERGEML_GUIDE_SAMPLE ), ARRAY_A );
    // phpcs:enable

    $groups = array();
    foreach ( (array) vergeml_talk_groups() as $g ) {
        $groups[] = array( 'size' => (int) $g['size'], 'captions' => array_map( function ( $c ) { return mb_substr( (string) $c, 0, 110 ); }, array_slice( (array) $g['captions'], 0, 2 ) ) );
    }

    // The top object classes across a sample, scaled to the library.
    $classes = array();
    foreach ( (array) $rows as $r ) {
        $f     = json_decode( (string) $r['filing'], true );
        $parts = vergeml_filing_classes_of_object( is_array( $f ) && isset( $f['object'] ) ? $f['object'] : '' );
        $class = isset( $parts[1] ) ? $parts[1] : ( isset( $parts[0] ) ? $parts[0] : '' );
        if ( '' !== $class ) {
            $classes[ $class ] = ( isset( $classes[ $class ] ) ? $classes[ $class ] : 0 ) + 1;
        }
    }
    arsort( $classes );
    $scale = count( $rows ) > 0 ? $total / count( $rows ) : 1;
    $top   = array();
    foreach ( array_slice( $classes, 0, 24, true ) as $c => $n ) {
        $top[] = array( 'class' => $c, 'count' => (int) round( $n * $scale ) );
    }

    // The folders that exist, with their ids: the model is asked to carry the id of a folder it keeps.
    $folders = array();
    foreach ( (array) vergeml_talk_current() as $c ) {
        $folders[] = array(
            'id'     => (int) $c['term_id'],
            'name'   => (string) $c['name'],
            'parent' => (string) ( isset( $c['parent'] ) ? $c['parent'] : '' ),
            'count'  => (int) ( isset( $c['count'] ) ? $c['count'] : 0 ),
        );
    }
    $by_kind = array();
    foreach ( (array) $kinds as $k ) {
        $by_kind[ '' === (string) $k['kind'] ? 'photo' : (string) $k['kind'] ] = (int) $k['n'];
    }

    $tax     = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';
    $unfiled = '' !== $tax ? (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$t} i WHERE i.error = '' AND i.embedding IS NOT NULL AND NOT EXISTS (
            SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE tr.object_id = i.attachment_id AND tt.taxonomy = %s )",
        $tax
    ) ) : $total; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.

    return array(
        'total'        => $total,
        'unfiled'      => $unfiled,
        'described_at' => $last,
        'folders'      => $folders,
        'groups'       => $groups,
        'classes'      => $top,
        'evidence'     => array(
            'brand'    => $total ? round( $n_brand / $total, 2 ) : 0,
            'size'     => $total ? round( $n_size / $total, 2 ) : 0,
            'audience' => $total ? round( $n_audience / $total, 2 ) : 0,
            'kinds'    => $by_kind,
        ),
        'samples'      => vergeml_talk_samples(),
    );
}

/**
 *  The session's summary, refreshed when the library or the folders changed
 *  since it was taken. Two cheap counts decide; the summary itself is not.
 */
function vergeml_guide_summary_fresh( &$s ) {

    $described = vergeml_guide_described_count();
    $tax       = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
    $nterms    = '' !== $tax && taxonomy_exists( $tax ) ? (int) wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) : 0;
    $key       = $described . ':' . $nterms . ':' . ( function_exists( 'vergeml_folders_version' ) ? vergeml_folders_version() : 0 );

    if ( ! is_array( $s['summary'] ) || (string) $s['summary_key'] !== $key ) {
        $s['summary']     = vergeml_guide_summary();
        $s['summary_key'] = $key;
        return true;
    }
    return false;
}


/* -------------------------------------------------------------- the token */

/**
 *  Where the browser streams from. The service the plugin talks to
 *  server-side may sit on the loopback of this very host (the test box does);
 *  a browser cannot reach that, and the token has to be minted where it will
 *  be presented, so both go to the same public address.
 */
function vergeml_guide_stream_url() {

    if ( defined( 'VERGEML_AI_STREAM' ) && is_string( VERGEML_AI_STREAM ) && '' !== VERGEML_AI_STREAM ) {
        return untrailingslashit( VERGEML_AI_STREAM );
    }
    $url = vergeml_ai_service_url();
    if ( preg_match( '#^https?://(127\.0\.0\.1|localhost|\[::1\])(:|/|$)#i', $url ) ) {
        return 'https://ai.vergelabs.nl/v1';
    }
    return $url;
}

/**
 *  A token for the browser: the cached one while it lasts and the summary it
 *  was minted for is still the session's, else a new one from the service.
 *  Minting is metered like a turn, so it is not done on every visit.
 *
 *  @return array|WP_Error { token, expires_at, summary, current, stream }
 */
function vergeml_guide_token() {

    if ( ! function_exists( 'vergeml_ai_settings' ) || ! function_exists( 'vergeml_ai_unseal' ) ) {
        return new WP_Error( 'no_licence', __( 'Connect a licence on the Licence screen first.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }
    $settings = vergeml_ai_settings();
    $licence  = vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
    if ( '' === $licence ) {
        return new WP_Error( 'no_licence', __( 'Connect a licence on the Licence screen first.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $s       = vergeml_guide_session();
    $changed = vergeml_guide_summary_fresh( $s );
    $stamp   = md5( wp_json_encode( $s['summary'] ) );
    $cached  = is_array( $s['token'] ) ? $s['token'] : null;

    if ( $cached && (string) $cached['stamp'] === $stamp && (int) $cached['expires_at'] - time() > VERGEML_GUIDE_TOKEN_SLACK ) {
        if ( $changed ) {
            vergeml_guide_save( $s );
        }
        return vergeml_guide_token_out( $s );
    }

    $response = wp_remote_post(
        vergeml_guide_stream_url() . '/guide/session',
        array(
            'timeout'   => 25,
            'headers'   => array( 'Content-Type' => 'application/json' ),
            'sslverify' => true,
            'body'      => wp_json_encode( array( 'license_key' => $licence, 'site' => home_url(), 'summary' => $s['summary'] ) ),
        )
    );
    if ( is_wp_error( $response ) ) {
        vergeml_guide_save( $s );
        return new WP_Error( 'unreachable', __( 'The service did not answer. The draft is safe. Try again in a minute.', 'vergelabs-media-library' ), array( 'status' => 502 ) );
    }
    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( 200 !== $code || ! is_array( $data ) || empty( $data['token'] ) ) {
        vergeml_guide_save( $s );
        $why = is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : 'HTTP ' . $code;
        if ( 429 === $code ) {
            $msg = __( 'The day\'s limit of turns for this site is used. Tomorrow it resets.', 'vergelabs-media-library' );
        } elseif ( 'not_entitled' === $why || 'not_found' === $why || 'site_not_activated' === $why ) {
            $msg = __( 'The licence does not cover this site. Check the Licence screen.', 'vergelabs-media-library' );
        } else {
            /* translators: %s: the service's error code */
            $msg = sprintf( __( 'The service answered: %s', 'vergelabs-media-library' ), $why );
        }
        return new WP_Error( 'service_' . $code, $msg, array( 'status' => 502 ) );
    }

    $s['token'] = array(
        'token'      => (string) $data['token'],
        'expires_at' => isset( $data['expires_at'] ) ? (int) $data['expires_at'] : time() + HOUR_IN_SECONDS,
        'stamp'      => $stamp,
    );
    vergeml_guide_save( $s );

    return vergeml_guide_token_out( $s );
}

function vergeml_guide_token_out( $s ) {
    return array(
        'token'      => (string) $s['token']['token'],
        'expires_at' => (int) $s['token']['expires_at'],
        'summary'    => $s['summary'],
        'current'    => isset( $s['summary']['folders'] ) ? array_values( (array) $s['summary']['folders'] ) : array(),
        'stream'     => vergeml_guide_stream_url(),
    );
}


/* --------------------------------------------------------------- routes */

add_action( 'rest_api_init', 'vergeml_guide_routes' );

function vergeml_guide_routes() {
    $may = function () {
        return current_user_can( 'manage_categories' );
    };
    register_rest_route( VERGEML_REST_NS, '/guide/session', array(
        array( 'methods' => WP_REST_Server::READABLE, 'callback' => 'vergeml_guide_rest_session', 'permission_callback' => $may ),
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'vergeml_guide_rest_session',
            'permission_callback' => $may,
            'args'                => array(
                'draft' => array( 'required' => false ),
                'reset' => array( 'type' => 'boolean', 'required' => false ),
            ),
        ),
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/token', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_guide_rest_token',
        'permission_callback' => $may,
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/turn', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_guide_rest_turn',
        'permission_callback' => $may,
        'args'                => array(
            'said'  => array( 'type' => 'object', 'required' => false ),
            'say'   => array( 'type' => 'object', 'required' => false ),
            'draft' => array( 'required' => false ),
            // Several turns in one write, in order: what a suite plants and puts back.
            'turns' => array( 'type' => 'array', 'required' => false ),
        ),
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/rules', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'vergeml_guide_rest_rules',
        'permission_callback' => $may,
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/rule', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_guide_rest_rule',
        'permission_callback' => $may,
        'args'                => array(
            'rule'    => array( 'type' => 'string', 'required' => true ),
            'options' => array( 'type' => 'object', 'required' => false ),
        ),
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/apply', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_guide_rest_apply',
        'permission_callback' => $may,
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/progress', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'vergeml_guide_rest_progress',
        'permission_callback' => $may,
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/stop', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_guide_rest_stop',
        'permission_callback' => $may,
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/undo', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_guide_rest_undo',
        'permission_callback' => $may,
    ) );
}

function vergeml_guide_rest_session( WP_REST_Request $request ) {

    if ( 'POST' === $request->get_method() ) {
        if ( $request->get_param( 'reset' ) ) {
            $fresh = vergeml_guide_save( vergeml_guide_fresh() );
            return rest_ensure_response( vergeml_guide_session_out( $fresh ) );
        }
        $s = vergeml_guide_session();
        if ( null !== $request->get_param( 'draft' ) ) {
            $s['draft'] = vergeml_guide_clean_draft( $request->get_param( 'draft' ) );
        }
        vergeml_guide_save( $s );
        return rest_ensure_response( vergeml_guide_session_out( $s ) );
    }

    $boot = vergeml_folders_boot();
    return rest_ensure_response( array(
        'session' => $boot['session'],
        'nodes'   => $boot['nodes'],
        'version' => $boot['version'],
        'facts'   => $boot['facts'],
        'undo'    => $boot['undo'],
    ) );
}

function vergeml_guide_rest_token( WP_REST_Request $request ) {
    $out = vergeml_guide_token();
    return is_wp_error( $out ) ? $out : rest_ensure_response( $out );
}

/**
 *  A finished turn, or half of one: what the person said (kind said, choice,
 *  edit or rule), what the assistant answered, the draft as it now stands.
 *  A rule applied over the same rule's last line replaces it: the options
 *  changed, the line reads the outcome.
 */
function vergeml_guide_rest_turn( WP_REST_Request $request ) {

    $s     = vergeml_guide_session();
    $turns = $request->get_param( 'turns' );
    $turns = is_array( $turns ) && $turns
        ? array_values( array_filter( $turns, 'is_array' ) )
        : array( array( 'said' => $request->get_param( 'said' ), 'say' => $request->get_param( 'say' ) ) );

    foreach ( $turns as $turn ) {
        $r = vergeml_guide_turn_apply( $s, isset( $turn['said'] ) ? $turn['said'] : null, isset( $turn['say'] ) ? $turn['say'] : null );
        if ( is_wp_error( $r ) ) {
            vergeml_guide_save( $s );
            return $r;
        }
    }

    if ( null !== $request->get_param( 'draft' ) ) {
        $s['draft'] = vergeml_guide_clean_draft( $request->get_param( 'draft' ) );
    }

    vergeml_guide_save( $s );

    return rest_ensure_response( vergeml_guide_session_out( $s ) );
}

/** One turn into the session: what was said, what was answered, either or both. */
function vergeml_guide_turn_apply( &$s, $said, $say ) {

    if ( is_array( $said ) && '' !== trim( (string) ( isset( $said['text'] ) ? $said['text'] : '' ) ) ) {
        $kind = isset( $said['kind'] ) && in_array( (string) $said['kind'], array( 'said', 'choice', 'edit', 'rule' ), true ) ? (string) $said['kind'] : 'said';
        $text = sanitize_textarea_field( (string) $said['text'] );
        $rule = 'rule' === $kind ? sanitize_key( (string) ( isset( $said['rule'] ) ? $said['rule'] : '' ) ) : '';
        $last = $s['turns'] ? $s['turns'][ count( $s['turns'] ) - 1 ] : null;
        if ( 'rule' === $kind && is_array( $last ) && 'rule' === $last['kind'] && isset( $last['rule'] ) && $last['rule'] === $rule ) {
            array_pop( $s['turns'] );
        }
        vergeml_guide_turn_add( $s, 'user', $kind, $text, 'rule' === $kind ? array( 'rule' => $rule ) : array() );
    }

    if ( is_array( $say ) && '' !== trim( (string) ( isset( $say['text'] ) ? $say['text'] : '' ) ) ) {
        if ( (int) $s['assistant_turns'] >= VERGEML_GUIDE_TURN_CAP ) {
            return new WP_Error( 'cap', __( 'Every turn of this conversation is used.', 'vergelabs-media-library' ), array( 'status' => 429 ) );
        }
        $choices = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( isset( $say['choices'] ) ? $say['choices'] : array() ) ) ) );
        $kind    = isset( $say['kind'] ) && 'moved' === $say['kind'] ? 'moved' : 'say';
        vergeml_guide_turn_add( $s, 'assistant', $kind, sanitize_textarea_field( (string) $say['text'] ), array( 'choices' => array_slice( $choices, 0, 3 ) ) );
        // A Move's own line is not a turn of the assistant's.
        if ( 'say' === $kind ) {
            $s['assistant_turns'] = (int) $s['assistant_turns'] + 1;
        }
    }

    return true;
}


/* --------------------------------------------------------------- the tags */

/**
 *  The draft's tags, made real: one flat media taxonomy per tag, one term per
 *  value. A tree nests one way, and the assistant turns the second and third
 *  axes ("by colour and brand") into these. The pictures are assigned in the
 *  same re-filing pass that files the folders (folder-talk.php), from the
 *  catalogue record: a term is put on a picture whose record names it.
 *
 *  A taxonomy that already carries the name is reused, and so is an existing
 *  term; nothing is made twice, and undo removes only what this made.
 *
 *  @param array $tags From the draft: [ { name, values: [] } ].
 *  @return array [ { taxonomy, created, terms: { term_id: { needles: [], created } } } ]
 */
function vergeml_guide_make_tags( $tags ) {
    $made = array();
    foreach ( (array) $tags as $t ) {
        if ( ! is_array( $t ) || '' === trim( (string) $t['name'] ) ) {
            continue;
        }
        $name   = trim( (string) $t['name'] );
        $values = array_values( array_unique( array_filter( array_map( 'trim', (array) ( isset( $t['values'] ) ? $t['values'] : array() ) ), 'strlen' ) ) );
        if ( ! $values ) {
            continue;
        }
        $tax = vergeml_guide_tag_taxonomy( $name );
        if ( ! $tax ) {
            continue;
        }
        $entry = array( 'taxonomy' => $tax['taxonomy'], 'name' => $name, 'created' => $tax['created'], 'terms' => array() );
        foreach ( $values as $value ) {
            $exists = term_exists( $value, $tax['taxonomy'] );
            if ( is_array( $exists ) && ! empty( $exists['term_id'] ) ) {
                $entry['terms'][ (int) $exists['term_id'] ] = array( 'needles' => array( mb_strtolower( $value ) ), 'created' => false );
                continue;
            }
            $ins = wp_insert_term( $value, $tax['taxonomy'] );
            if ( ! is_wp_error( $ins ) && isset( $ins['term_id'] ) ) {
                $entry['terms'][ (int) $ins['term_id'] ] = array( 'needles' => array( mb_strtolower( $value ) ), 'created' => true );
            }
        }
        if ( $entry['terms'] ) {
            $made[] = $entry;
        }
    }
    return $made;
}

/**
 *  The taxonomy for a tag name: an existing media taxonomy with that label,
 *  or a new flat one registered now so terms can go in this request.
 *
 *  @return array{taxonomy:string,created:bool}|null Null when the key is taken by something that is not a media taxonomy.
 */
function vergeml_guide_tag_taxonomy( $name ) {
    $all = get_option( 'vergeml_taxonomies', array() );
    $all = is_array( $all ) ? $all : array();

    foreach ( $all as $key => $params ) {
        if ( empty( $params['eml_media'] ) || ! taxonomy_exists( $key ) ) {
            continue;
        }
        $label = isset( $params['labels']['name'] ) ? (string) $params['labels']['name'] : '';
        $one   = isset( $params['labels']['singular_name'] ) ? (string) $params['labels']['singular_name'] : '';
        if ( 0 === strcasecmp( $label, $name ) || 0 === strcasecmp( $one, $name ) ) {
            return array( 'taxonomy' => (string) $key, 'created' => false );
        }
    }

    // A taxonomy key is 32 characters of [a-z0-9_]. Core's own names are not ours to take.
    $key = trim( substr( sanitize_key( str_replace( array( ' ', '-' ), '_', $name ) ), 0, 32 ), '_' );
    if ( '' === $key ) {
        return null;
    }
    if ( taxonomy_exists( $key ) || isset( $all[ $key ] ) ) {
        $key = 'vgml_' . substr( $key, 0, 27 );
        if ( taxonomy_exists( $key ) || isset( $all[ $key ] ) ) {
            return null;
        }
    }

    $labels = array(
        'name'          => $name,
        'singular_name' => $name,
        'menu_name'     => $name,
        /* translators: %s: the tag's name, e.g. Colour */
        'all_items'     => sprintf( __( 'All %s', 'vergelabs-media-library' ), $name ),
        /* translators: %s: the tag's name, e.g. Colour */
        'edit_item'     => sprintf( __( 'Edit %s', 'vergelabs-media-library' ), $name ),
        /* translators: %s: the tag's name, e.g. Colour */
        'view_item'     => sprintf( __( 'View %s', 'vergelabs-media-library' ), $name ),
        /* translators: %s: the tag's name, e.g. Colour */
        'update_item'   => sprintf( __( 'Update %s', 'vergelabs-media-library' ), $name ),
        /* translators: %s: the tag's name, e.g. Colour */
        'add_new_item'  => sprintf( __( 'Add New %s', 'vergelabs-media-library' ), $name ),
        /* translators: %s: the tag's name, e.g. Colour */
        'new_item_name' => sprintf( __( 'New %s Name', 'vergelabs-media-library' ), $name ),
        'parent_item'   => '',
        /* translators: %s: the tag's name, e.g. Colour */
        'search_items'  => sprintf( __( 'Search %s', 'vergelabs-media-library' ), $name ),
    );

    $all[ $key ] = array(
        'assigned'                  => 1,
        'eml_media'                 => 1,
        'labels'                    => $labels,
        'hierarchical'              => 0,
        'show_admin_column'         => 1,
        'admin_filter'              => 1,
        'media_uploader_filter'     => 1,
        'media_popup_taxonomy_edit' => 0,
        'sort'                      => 0,
        'show_in_rest'              => 1,
        'rewrite'                   => array( 'slug' => $key, 'with_front' => 1 ),
    );
    update_option( 'vergeml_taxonomies', $all );

    // The same registration vergeml_on_init() will do on the next load, done now so terms can be inserted in this request.
    register_taxonomy( $key, 'attachment', array(
        'labels'                => $labels,
        'public'                => false,
        'show_ui'               => true,
        'show_admin_column'     => true,
        'hierarchical'          => false,
        'update_count_callback' => 'vergeml_update_attachment_term_count',
        'sort'                  => false,
        'show_in_rest'          => true,
        'query_var'             => $key,
        'rewrite'               => false,
    ) );
    register_taxonomy_for_object_type( $key, 'attachment' );

    return array( 'taxonomy' => $key, 'created' => true );
}

/**
 *  Takes back what vergeml_guide_make_tags() made: the terms it created, and
 *  the taxonomy when that was new too. Existing ones are left alone.
 */
function vergeml_guide_unmake_tags( $made ) {
    $all = get_option( 'vergeml_taxonomies', array() );
    $all = is_array( $all ) ? $all : array();
    $changed = false;
    foreach ( (array) $made as $entry ) {
        $tax = (string) $entry['taxonomy'];
        if ( ! taxonomy_exists( $tax ) ) {
            continue;
        }
        foreach ( (array) $entry['terms'] as $term_id => $term ) {
            if ( ! empty( $term['created'] ) ) {
                wp_delete_term( (int) $term_id, $tax );
            }
        }
        if ( ! empty( $entry['created'] ) && isset( $all[ $tax ] ) ) {
            unset( $all[ $tax ] );
            $changed = true;
        }
    }
    if ( $changed ) {
        update_option( 'vergeml_taxonomies', $all );
    }
}


/* ---------------------------------------------------------------- the Move */

/**
 *  The draft as the re-filing takes it: folders named by name and parent
 *  name, parents before children, each with the term id it stands for; the
 *  assignments of a rule and the fallbacks of the folders that go, keyed
 *  the way the re-filing keys folders.
 *
 *  @return array|WP_Error { folders, opts }
 */
function vergeml_guide_apply_plan( $draft ) {

    if ( ! is_array( $draft ) || empty( $draft['folders'] ) ) {
        return new WP_Error( 'empty', __( 'The draft has no folders.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $by_key = array();
    foreach ( $draft['folders'] as $f ) {
        $by_key[ $f['key'] ] = $f;
    }

    // Parents before children: depth first, then the draft's own order.
    $depth = function ( $key ) use ( $by_key ) {
        $d = 0;
        $guard = 0;
        while ( isset( $by_key[ $key ] ) && '' !== $by_key[ $key ]['parent'] && $guard++ < 64 ) {
            $d++;
            $key = $by_key[ $key ]['parent'];
        }
        return $d;
    };
    $ordered = $draft['folders'];
    $index   = 0;
    foreach ( $ordered as &$f ) {
        $f['_depth'] = $depth( $f['key'] );
        $f['_index'] = $index++;
    }
    unset( $f );
    usort( $ordered, function ( $a, $b ) {
        return $a['_depth'] - $b['_depth'] ?: $a['_index'] - $b['_index'];
    } );

    $folders  = array();
    $talk_key = array();
    foreach ( $ordered as $f ) {
        $parent_name = '' !== $f['parent'] && isset( $by_key[ $f['parent'] ] ) ? (string) $by_key[ $f['parent'] ]['name'] : '';
        $folders[]   = array(
            'term_id'  => ! empty( $f['term_id'] ) ? (int) $f['term_id'] : 0,
            'name'     => (string) $f['name'],
            'parent'   => $parent_name,
            'matches'  => (string) $f['matches'],
            'classes'  => (array) $f['classes'],
            'kinds'    => (array) $f['kinds'],
            'audience' => (string) $f['audience'],
        );
        $talk_key[ $f['key'] ] = vergeml_talk_key( $parent_name, (string) $f['name'] );
    }

    $opts = array( 'assign' => array(), 'fallback' => array() );

    foreach ( (array) $draft['gone'] as $tid => $to ) {
        if ( '' !== $to && isset( $talk_key[ $to ] ) ) {
            $opts['fallback'][ (int) $tid ] = $talk_key[ $to ];
        }
    }

    if ( 'rule' === $draft['origin'] && is_array( $draft['rule'] ) ) {
        $rule = vergeml_guide_rule( $draft['rule']['id'], $draft['rule']['options'] );
        if ( is_wp_error( $rule ) ) {
            return $rule;
        }
        foreach ( $rule['assign'] as $attachment => $key ) {
            if ( isset( $talk_key[ $key ] ) ) {
                $opts['assign'][ (int) $attachment ] = $talk_key[ $key ];
            }
        }
        if ( ! $opts['assign'] ) {
            return new WP_Error( 'empty', __( 'The rule moves no pictures.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
        }
    }

    return array( 'folders' => $folders, 'opts' => $opts );
}

function vergeml_guide_rest_apply( WP_REST_Request $request ) {

    $s = vergeml_guide_session();

    if ( is_array( $s['apply'] ) && ! empty( $s['apply']['running'] ) ) {
        return rest_ensure_response( vergeml_guide_progress_out( $s ) );
    }

    $plan = vergeml_guide_apply_plan( $s['draft'] );
    if ( is_wp_error( $plan ) ) {
        return $plan;
    }

    $tags = 'talk' === $s['draft']['origin'] && ! empty( $s['draft']['tags'] ) ? vergeml_guide_make_tags( $s['draft']['tags'] ) : array();
    $r    = vergeml_talk_apply( $plan['folders'], $tags, $plan['opts'] );
    if ( is_wp_error( $r ) ) {
        vergeml_guide_unmake_tags( $tags );
        return new WP_Error( $r->get_error_code(), $r->get_error_message(), array( 'status' => 400 ) );
    }

    $s['apply'] = array( 'started_at' => time(), 'running' => true, 'stopped' => false );
    vergeml_guide_save( $s );

    return rest_ensure_response( vergeml_guide_progress_out( $s ) );
}

/**
 *  The report the poll reads, and the moment the run ends: one line into
 *  the conversation, the draft cleared (the tree is the library now), undo
 *  offered for a day.
 */
function vergeml_guide_progress_out( &$s, $report = null ) {

    if ( null === $report ) {
        $report = vergeml_talk_progress();
    }

    if ( is_array( $s['apply'] ) && ! empty( $s['apply']['running'] ) && empty( $report['running'] ) ) {
        $s['apply']  = null;
        $s['draft']  = null;
        $until       = ! empty( $report['until'] ) ? vergeml_folders_when( (int) $report['until'] ) : '';
        $lines       = array();
        if ( ! empty( $report['stopped'] ) ) {
            /* translators: 1: pictures moved, 2: folders */
            $lines[] = sprintf( _n( '%1$s picture moved into %2$s folders before Stop', '%1$s pictures moved into %2$s folders before Stop', (int) $report['moved'], 'vergelabs-media-library' ), number_format_i18n( (int) $report['moved'] ), number_format_i18n( (int) $report['folders'] ) );
        } else {
            /* translators: 1: pictures moved, 2: folders */
            $lines[] = sprintf( _n( '%1$s picture moved into %2$s folders', '%1$s pictures moved into %2$s folders', (int) $report['moved'], 'vergelabs-media-library' ), number_format_i18n( (int) $report['moved'] ), number_format_i18n( (int) $report['folders'] ) );
        }
        $stayed = max( 0, (int) $report['total'] - (int) $report['moved'] );
        /* translators: %s: pictures that did not move */
        $lines[] = sprintf( __( '%s stayed where they were', 'vergelabs-media-library' ), number_format_i18n( $stayed ) );
        if ( ! empty( $report['removed'] ) ) {
            /* translators: %s: folders removed */
            $lines[] = sprintf( _n( '%s folder removed', '%s folders removed', (int) $report['removed'], 'vergelabs-media-library' ), number_format_i18n( (int) $report['removed'] ) );
        }
        if ( '' !== $until ) {
            /* translators: %s: when undo ends, e.g. "tomorrow 14:20" */
            $lines[] = sprintf( __( 'Undo until %s', 'vergelabs-media-library' ), $until );
        }
        vergeml_guide_turn_add( $s, 'assistant', 'moved', '- ' . implode( "\n- ", $lines ) );
        vergeml_guide_save( $s );
    }

    // What has landed, by the draft's own keys: a folder the Move made has a term id only now.
    $report['by_key'] = vergeml_guide_landed_by_key( $s['draft'], isset( $report['by_term'] ) ? (array) $report['by_term'] : array() );

    return array(
        'report'  => $report,
        'session' => vergeml_guide_session_out( $s ),
        'undo'    => vergeml_talk_undo_available(),
        'version' => function_exists( 'vergeml_folders_version' ) ? vergeml_folders_version() : 0,
    );
}

/**
 *  Pictures landed per draft folder while a Move runs. The re-filing keys
 *  folders by parent name and name and remembers the term id it gave each
 *  (the state's ids); the draft keys them by client key. This joins the two.
 */
function vergeml_guide_landed_by_key( $draft, $by_term ) {

    if ( ! is_array( $draft ) || empty( $draft['folders'] ) || ! $by_term ) {
        return array();
    }
    $state = get_option( VERGEML_TALK_STATE );
    $ids   = is_array( $state ) && isset( $state['ids'] ) ? (array) $state['ids'] : array();
    $names = array();
    foreach ( $draft['folders'] as $f ) {
        $names[ $f['key'] ] = $f['name'];
    }
    $out = array();
    foreach ( $draft['folders'] as $f ) {
        $tid = ! empty( $f['term_id'] ) ? (int) $f['term_id'] : 0;
        if ( ! $tid ) {
            $parent = '' !== $f['parent'] && isset( $names[ $f['parent'] ] ) ? $names[ $f['parent'] ] : '';
            $key    = vergeml_talk_key( $parent, $f['name'] );
            $tid    = isset( $ids[ $key ] ) ? (int) $ids[ $key ] : 0;
        }
        if ( $tid && isset( $by_term[ $tid ] ) ) {
            $out[ $f['key'] ] = (int) $by_term[ $tid ];
        }
    }
    return $out;
}

function vergeml_guide_rest_progress( WP_REST_Request $request ) {
    $s = vergeml_guide_session();
    return rest_ensure_response( vergeml_guide_progress_out( $s ) );
}

function vergeml_guide_rest_stop( WP_REST_Request $request ) {
    $s = vergeml_guide_session();
    if ( is_array( $s['apply'] ) ) {
        $s['apply']['stopped'] = true;
    }
    return rest_ensure_response( vergeml_guide_progress_out( $s, vergeml_talk_refile_stop() ) );
}

function vergeml_guide_rest_undo( WP_REST_Request $request ) {

    $s = vergeml_guide_session();
    $r = vergeml_talk_undo();
    if ( is_wp_error( $r ) ) {
        return new WP_Error( $r->get_error_code(), $r->get_error_message(), array( 'status' => 400 ) );
    }

    $lines = array();
    /* translators: %s: pictures put back */
    $lines[] = sprintf( _n( '%s picture put back', '%s pictures put back', (int) $r['restored'], 'vergelabs-media-library' ), number_format_i18n( (int) $r['restored'] ) );
    if ( ! empty( $r['unmade'] ) ) {
        /* translators: %s: folders the Move made, now removed again */
        $lines[] = sprintf( _n( '%s folder the Move made is gone again', '%s folders the Move made are gone again', (int) $r['unmade'], 'vergelabs-media-library' ), number_format_i18n( (int) $r['unmade'] ) );
    }
    vergeml_guide_turn_add( $s, 'assistant', 'moved', '- ' . implode( "\n- ", $lines ) );
    $s['apply'] = null;
    vergeml_guide_save( $s );

    return rest_ensure_response( array(
        'session' => vergeml_guide_session_out( $s ),
        'undo'    => vergeml_talk_undo_available(),
        'version' => function_exists( 'vergeml_folders_version' ) ? vergeml_folders_version() : 0,
    ) );
}


/* --------------------------------------------------------------- the rules */

/**
 *  The four rules and their options, each option a closed list, so what is
 *  stored with a draft is exactly one of these and nothing else.
 */
function vergeml_guide_rule_args( $id, $options ) {

    $id      = sanitize_key( (string) $id );
    $options = is_array( $options ) ? $options : array();
    $pick    = function ( $key, $allowed, $default ) use ( $options ) {
        $v = isset( $options[ $key ] ) ? (string) $options[ $key ] : '';
        return in_array( $v, $allowed, true ) ? $v : $default;
    };

    switch ( $id ) {
        case 'kind':
            return array( 'id' => 'kind', 'options' => array( 'scope' => $pick( 'scope', array( 'unfiled', 'all' ), 'unfiled' ) ) );
        case 'date':
            return array( 'id' => 'date', 'options' => array(
                'source' => $pick( 'source', array( 'upload', 'taken' ), 'upload' ),
                'levels' => $pick( 'levels', array( 'ym', 'month' ), 'ym' ),
                'scope'  => $pick( 'scope', array( 'unfiled', 'all' ), 'unfiled' ),
            ) );
        case 'subject':
            return array( 'id' => 'subject', 'options' => array(
                'min'    => max( 1, min( 500, isset( $options['min'] ) ? (int) $options['min'] : 10 ) ),
                'levels' => $pick( 'levels', array( 'one', 'two' ), 'one' ),
                'scope'  => $pick( 'scope', array( 'unfiled', 'all' ), 'unfiled' ),
            ) );
        case 'fit':
            return array( 'id' => 'fit', 'options' => array(
                'rest' => $pick( 'rest', array( 'stay', 'unsorted' ), 'stay' ),
                'sure' => $pick( 'sure', array( 'sure', 'close' ), 'sure' ),
            ) );
    }
    return null;
}

/**
 *  The described pictures a rule looks at: every one, or only those in no
 *  folder, with what each rule needs to know about them. One query.
 *
 *  @param string $scope  'unfiled' | 'all'
 *  @param array  $need   any of 'filing', 'date', 'terms'
 */
function vergeml_guide_rule_rows( $taxonomy, $scope, $need = array() ) {

    global $wpdb;

    $t      = $wpdb->vergeml_ai_index;
    $select = 'i.attachment_id, i.kind';
    $join   = '';
    $group  = '';

    if ( in_array( 'filing', $need, true ) ) {
        $select .= ', i.filing';
    }
    if ( in_array( 'date', $need, true ) ) {
        $select .= ', p.post_date';
        $join   .= " JOIN {$wpdb->posts} p ON p.ID = i.attachment_id";
    }
    if ( in_array( 'terms', $need, true ) ) {
        $select .= ', GROUP_CONCAT( tt.term_id ) AS in_terms';
        $join   .= $wpdb->prepare( " LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = i.attachment_id
                     LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s", $taxonomy );
        $group   = ' GROUP BY i.attachment_id';
    }

    $where = "i.error = '' AND i.embedding IS NOT NULL";
    if ( 'unfiled' === $scope ) {
        $where .= $wpdb->prepare( " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} x ON x.term_taxonomy_id = r.term_taxonomy_id WHERE r.object_id = i.attachment_id AND x.taxonomy = %s )", $taxonomy );
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- this plugin's own table; the parts are prepared above.
    return (array) $wpdb->get_results( "SELECT {$select} FROM {$t} i{$join} WHERE {$where}{$group} ORDER BY i.attachment_id ASC", ARRAY_A );
}

/** Live folders by lowercased path ("apparel/women") and by id. */
function vergeml_guide_live_index( $taxonomy ) {

    $nodes = vergeml_folders_nodes( $taxonomy );
    $by_id = array();
    foreach ( $nodes as $n ) {
        $by_id[ $n['id'] ] = $n;
    }
    $path = function ( $id ) use ( $by_id ) {
        $names = array();
        $guard = 0;
        while ( isset( $by_id[ $id ] ) && $guard++ < 64 ) {
            array_unshift( $names, mb_strtolower( $by_id[ $id ]['name'] ) );
            $id = $by_id[ $id ]['parent'];
        }
        return implode( '/', $names );
    };
    $by_path = array();
    foreach ( $nodes as $n ) {
        $by_path[ $path( $n['id'] ) ] = $n['id'];
    }
    return array( 'nodes' => $nodes, 'by_id' => $by_id, 'by_path' => $by_path );
}

/** The kinds as folder names. */
function vergeml_guide_kind_names() {
    return array(
        'photo'        => __( 'Photos', 'vergelabs-media-library' ),
        'illustration' => __( 'Illustrations', 'vergelabs-media-library' ),
        'screenshot'   => __( 'Screenshots', 'vergelabs-media-library' ),
        'diagram'      => __( 'Diagrams', 'vergelabs-media-library' ),
        'document'     => __( 'Documents', 'vergelabs-media-library' ),
        'logo'         => __( 'Logos', 'vergelabs-media-library' ),
    );
}

/**
 *  The four rules with the folder count each would make on its default
 *  options, for the number beside each. Cached for the library and folders
 *  as they are.
 */
function vergeml_guide_rules_all() {

    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
    $version  = function_exists( 'vergeml_folders_version' ) ? vergeml_folders_version() : 0;
    $facts    = vergeml_folders_facts( $taxonomy, 0 );
    $ckey     = 'vergeml_guide_rules_' . md5( $taxonomy . ':' . $version . ':' . $facts['pictures'] . ':' . $facts['unfiled'] );
    $cached   = get_transient( $ckey );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    $out = array( 'unfiled' => (int) $facts['unfiled'], 'pictures' => (int) $facts['pictures'], 'rules' => array() );

    foreach ( array( 'kind', 'date', 'subject', 'fit' ) as $id ) {
        $args = vergeml_guide_rule_args( $id, array() );
        $r    = 'fit' === $id ? null : vergeml_guide_rule( $id, $args['options'] );
        $out['rules'][] = array(
            'id'      => $id,
            'folders' => 'fit' === $id ? 0 : ( is_wp_error( $r ) ? 0 : (int) $r['made'] ),
        );
    }

    set_transient( $ckey, $out, VERGEML_GUIDE_RULE_CACHE );
    return $out;
}

/**
 *  One rule, computed: the draft it makes (keyed by term id where a folder
 *  exists), the preview lines, and the assignments the Move will use.
 *
 *  @return array|WP_Error { draft, preview: [ { text, strong } ], made, move, assign: { attachment: key } }
 */
function vergeml_guide_rule( $id, $options ) {

    $args = vergeml_guide_rule_args( $id, $options );
    if ( ! $args ) {
        return new WP_Error( 'no_rule', __( 'No such rule.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }
    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
    if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
        return new WP_Error( 'no_taxonomy', __( 'No folders are set up on this site.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }

    $version = function_exists( 'vergeml_folders_version' ) ? vergeml_folders_version() : 0;
    $ckey    = 'vergeml_guide_rule_' . md5( $taxonomy . ':' . $version . ':' . vergeml_guide_described_count() . ':' . wp_json_encode( $args ) );
    $cached  = get_transient( $ckey );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    switch ( $args['id'] ) {
        case 'kind':
            $out = vergeml_guide_rule_kind( $taxonomy, $args['options'] );
            break;
        case 'date':
            $out = vergeml_guide_rule_date( $taxonomy, $args['options'] );
            break;
        case 'subject':
            $out = vergeml_guide_rule_subject( $taxonomy, $args['options'] );
            break;
        default:
            $out = vergeml_guide_rule_fit( $taxonomy, $args['options'] );
    }

    if ( is_array( $out ) ) {
        $out['draft']['origin'] = 'rule';
        $out['draft']['rule']   = $args;
        set_transient( $ckey, $out, VERGEML_GUIDE_RULE_CACHE );
    }
    return $out;
}

/**
 *  From the groups a rule made to a draft: the live folders kept (or all
 *  gone, on the "every picture moves" scope), one folder per group, reused
 *  where a folder already sits at that path, counts after Move, and the
 *  assignment of every picture that moves.
 *
 *  @param array $groups  key => { path: [ names ], members: [ attachment ids ] }
 *  @param array $rows    the rows the rule read, with in_terms when scope is all
 */
function vergeml_guide_rule_draft( $taxonomy, $scope, $groups, $rows ) {

    $live    = vergeml_guide_live_index( $taxonomy );
    $folders = array();
    $keys    = array();
    $assign  = array();
    $land    = array();
    $left    = array();
    $made    = 0;

    if ( 'unfiled' === $scope ) {
        foreach ( $live['nodes'] as $n ) {
            $folders[ 't' . $n['id'] ] = array( 'key' => 't' . $n['id'], 'term_id' => (int) $n['id'], 'name' => $n['name'], 'parent' => $n['parent'] ? 't' . $n['parent'] : '', 'count' => null, 'matches' => '', 'classes' => array(), 'kinds' => array(), 'audience' => '', 'by' => '' );
        }
    }

    foreach ( $groups as $gkey => $g ) {
        // Each segment of the path becomes a folder; an existing folder at that path is reused.
        $parent_key = '';
        $path       = array();
        foreach ( $g['path'] as $i => $name ) {
            $path[]   = mb_strtolower( $name );
            $lookup   = implode( '/', $path );
            $reuse    = 'unfiled' === $scope && isset( $live['by_path'][ $lookup ] ) ? (int) $live['by_path'][ $lookup ] : 0;
            $key      = $reuse ? 't' . $reuse : 'r:' . $gkey . ( $i + 1 < count( $g['path'] ) ? ':' . ( $i + 1 ) : '' );
            if ( ! isset( $folders[ $key ] ) ) {
                $folders[ $key ] = array( 'key' => $key, 'term_id' => $reuse ? $reuse : null, 'name' => $name, 'parent' => $parent_key, 'count' => $reuse ? null : 0, 'matches' => '', 'classes' => array(), 'kinds' => array(), 'audience' => '', 'by' => '' );
                if ( ! $reuse ) {
                    $made++;
                }
            }
            $parent_key = $key;
        }
        foreach ( $g['members'] as $attachment ) {
            $assign[ (int) $attachment ] = $parent_key;
            $land[ $parent_key ]         = isset( $land[ $parent_key ] ) ? $land[ $parent_key ] + 1 : 1;
        }
    }

    // Counts after Move: what lands, and what leaves a kept folder.
    foreach ( $rows as $r ) {
        if ( ! isset( $assign[ (int) $r['attachment_id'] ] ) || empty( $r['in_terms'] ) ) {
            continue;
        }
        foreach ( explode( ',', (string) $r['in_terms'] ) as $tid ) {
            $left[ (int) $tid ] = isset( $left[ (int) $tid ] ) ? $left[ (int) $tid ] + 1 : 1;
        }
    }
    foreach ( $folders as $key => &$f ) {
        $landed = isset( $land[ $key ] ) ? $land[ $key ] : 0;
        if ( $f['term_id'] ) {
            $gone = isset( $left[ $f['term_id'] ] ) ? $left[ $f['term_id'] ] : 0;
            if ( $landed || $gone ) {
                $f['count'] = max( 0, (int) $live['by_id'][ $f['term_id'] ]['count'] + $landed - $gone );
            }
        } else {
            $f['count'] = $landed;
        }
    }
    unset( $f );

    // On the whole-library scope every live folder goes; its pictures go where most of them land.
    $gone = array();
    if ( 'all' === $scope ) {
        $dest = array();
        foreach ( $rows as $r ) {
            if ( empty( $r['in_terms'] ) || ! isset( $assign[ (int) $r['attachment_id'] ] ) ) {
                continue;
            }
            foreach ( explode( ',', (string) $r['in_terms'] ) as $tid ) {
                $to = $assign[ (int) $r['attachment_id'] ];
                $dest[ (int) $tid ][ $to ] = isset( $dest[ (int) $tid ][ $to ] ) ? $dest[ (int) $tid ][ $to ] + 1 : 1;
            }
        }
        foreach ( $live['nodes'] as $n ) {
            $to = '';
            if ( isset( $dest[ $n['id'] ] ) ) {
                arsort( $dest[ $n['id'] ] );
                $to = (string) key( $dest[ $n['id'] ] );
            }
            $gone[ $n['id'] ] = $to;
        }
    }

    return array(
        'draft'  => array( 'folders' => array_values( $folders ), 'gone' => $gone, 'origin' => 'rule', 'rule' => null ),
        'assign' => $assign,
        'made'   => $made,
        'move'   => count( $assign ),
    );
}

/** The two lines every rule's preview ends with: what moves, what happens to today's folders. */
function vergeml_guide_rule_tail( $scope, $stay, $why, $today ) {

    $lines = array();
    if ( $stay > 0 ) {
        /* translators: 1: pictures, 2: why they stay */
        $lines[] = array( 'text' => sprintf( __( '%1$s stay unfiled: %2$s', 'vergelabs-media-library' ), number_format_i18n( $stay ), $why ) );
    }
    if ( 'all' === $scope ) {
        /* translators: %s: folders today */
        $lines[] = array( 'text' => sprintf( _n( 'Today\'s %s folder is removed', 'Today\'s %s folders are removed', $today, 'vergelabs-media-library' ), number_format_i18n( $today ) ) );
    } else {
        $lines[] = array( 'text' => __( 'Today\'s folders unchanged', 'vergelabs-media-library' ) );
    }
    return $lines;
}

function vergeml_guide_rule_kind( $taxonomy, $o ) {

    $rows   = vergeml_guide_rule_rows( $taxonomy, $o['scope'], array( 'terms' ) );
    $names  = vergeml_guide_kind_names();
    $groups = array();
    $stay   = 0;

    foreach ( $rows as $r ) {
        $kind = (string) $r['kind'];
        if ( ! isset( $names[ $kind ] ) ) {
            $stay++;
            continue;
        }
        if ( ! isset( $groups[ 'kind:' . $kind ] ) ) {
            $groups[ 'kind:' . $kind ] = array( 'path' => array( $names[ $kind ] ), 'members' => array() );
        }
        $groups[ 'kind:' . $kind ]['members'][] = (int) $r['attachment_id'];
    }
    uasort( $groups, function ( $a, $b ) { return count( $b['members'] ) - count( $a['members'] ); } );

    $out   = vergeml_guide_rule_draft( $taxonomy, $o['scope'], $groups, $rows );
    $parts = array();
    foreach ( $groups as $g ) {
        $parts[] = $g['path'][0] . ' ' . number_format_i18n( count( $g['members'] ) );
    }
    $lines   = array();
    /* translators: 1: new folders, 2: their names with counts */
    $lines[] = array( 'text' => sprintf( _n( '%1$s new folder: %2$s', '%1$s new folders: %2$s', (int) $out['made'], 'vergelabs-media-library' ), number_format_i18n( (int) $out['made'] ), implode( ', ', $parts ) ), 'strong' => true );
    /* translators: %s: pictures that move */
    $lines[] = array( 'text' => sprintf( _n( '%s picture moves', '%s pictures move', (int) $out['move'], 'vergelabs-media-library' ), number_format_i18n( (int) $out['move'] ) ) );
    $out['preview'] = array_merge( $lines, vergeml_guide_rule_tail( $o['scope'], $stay, __( 'no kind', 'vergelabs-media-library' ), count( vergeml_folders_nodes( $taxonomy ) ) ) );
    return $out;
}

function vergeml_guide_rule_date( $taxonomy, $o ) {

    $rows = vergeml_guide_rule_rows( $taxonomy, $o['scope'], array( 'date', 'terms' ) );

    // The camera's date, when asked for and present; the upload date otherwise.
    if ( 'taken' === $o['source'] && $rows ) {
        update_postmeta_cache( array_map( function ( $r ) { return (int) $r['attachment_id']; }, $rows ) );
    }

    $groups = array();
    $folded = 0;
    foreach ( $rows as $r ) {
        $ts = strtotime( (string) $r['post_date'] );
        if ( 'taken' === $o['source'] ) {
            $meta  = get_post_meta( (int) $r['attachment_id'], '_wp_attachment_metadata', true );
            $taken = is_array( $meta ) && isset( $meta['image_meta']['created_timestamp'] ) ? (int) $meta['image_meta']['created_timestamp'] : 0;
            if ( $taken > 0 ) {
                $ts = $taken;
            } else {
                $folded++;
            }
        }
        $year  = (int) wp_date( 'Y', $ts );
        $month = (int) wp_date( 'n', $ts );
        $key   = sprintf( 'date:%04d-%02d', $year, $month );
        if ( ! isset( $groups[ $key ] ) ) {
            $groups[ $key ] = array(
                'path'    => 'ym' === $o['levels'] ? array( (string) $year, vergeml_librarian_month_name( $month ) ) : array( sprintf( '%04d-%02d', $year, $month ) ),
                'members' => array(),
            );
        }
        $groups[ $key ]['members'][] = (int) $r['attachment_id'];
    }
    krsort( $groups );

    $out   = vergeml_guide_rule_draft( $taxonomy, $o['scope'], $groups, $rows );
    $names = array();
    foreach ( array_slice( $groups, 0, 6, true ) as $g ) {
        $names[] = implode( ' / ', $g['path'] );
    }
    if ( count( $groups ) > 6 ) {
        $names[] = '…';
    }
    $lines   = array();
    /* translators: 1: new folders, 2: their names */
    $lines[] = array( 'text' => sprintf( _n( '%1$s new folder: %2$s', '%1$s new folders: %2$s', (int) $out['made'], 'vergelabs-media-library' ), number_format_i18n( (int) $out['made'] ), implode( ', ', $names ) ), 'strong' => true );
    /* translators: %s: pictures that move */
    $lines[] = array( 'text' => sprintf( _n( '%s picture moves', '%s pictures move', (int) $out['move'], 'vergelabs-media-library' ), number_format_i18n( (int) $out['move'] ) ) );
    if ( $folded > 0 ) {
        /* translators: %s: pictures without a camera date */
        $lines[] = array( 'text' => sprintf( __( '%s have no camera date and use the upload date', 'vergelabs-media-library' ), number_format_i18n( $folded ) ) );
    }
    $out['preview'] = array_merge( $lines, vergeml_guide_rule_tail( $o['scope'], 0, '', count( vergeml_folders_nodes( $taxonomy ) ) ) );
    return $out;
}

function vergeml_guide_rule_subject( $taxonomy, $o ) {

    $rows = vergeml_guide_rule_rows( $taxonomy, $o['scope'], array( 'filing', 'terms' ) );
    $min  = (int) $o['min'];

    // Subject is the general class the describer wrote ("platform sneaker; footwear" -> footwear); the specific one is the second level.
    $by_general = array();
    $none       = 0;
    foreach ( $rows as $r ) {
        $f       = json_decode( (string) $r['filing'], true );
        $parts   = vergeml_filing_classes_of_object( is_array( $f ) && isset( $f['object'] ) ? $f['object'] : '' );
        $general = isset( $parts[1] ) ? $parts[1] : ( isset( $parts[0] ) ? $parts[0] : '' );
        $special = isset( $parts[1] ) ? $parts[0] : '';
        if ( '' === $general ) {
            $none++;
            continue;
        }
        $by_general[ $general ]['all'][]                 = (int) $r['attachment_id'];
        $by_general[ $general ]['by'][ $special ][]      = (int) $r['attachment_id'];
    }

    $groups = array();
    $small  = 0;
    foreach ( $by_general as $general => $g ) {
        if ( count( $g['all'] ) < $min ) {
            $small += count( $g['all'] );
            continue;
        }
        $gname = ucfirst( $general );
        $gkey  = 'subject:' . sanitize_title( $general );
        if ( 'two' === $o['levels'] ) {
            $rest = array();
            foreach ( $g['by'] as $special => $members ) {
                if ( '' !== $special && count( $members ) >= $min ) {
                    $groups[ $gkey . ':' . sanitize_title( $special ) ] = array( 'path' => array( $gname, ucfirst( $special ) ), 'members' => $members );
                } else {
                    $rest = array_merge( $rest, $members );
                }
            }
            if ( $rest ) {
                $groups[ $gkey ] = array( 'path' => array( $gname ), 'members' => $rest );
            } elseif ( ! isset( $groups[ $gkey ] ) ) {
                $groups[ $gkey ] = array( 'path' => array( $gname ), 'members' => array() );
            }
        } else {
            $groups[ $gkey ] = array( 'path' => array( $gname ), 'members' => $g['all'] );
        }
    }
    uasort( $groups, function ( $a, $b ) { return count( $b['members'] ) - count( $a['members'] ); } );

    $out   = vergeml_guide_rule_draft( $taxonomy, $o['scope'], $groups, $rows );
    $parts = array();
    foreach ( array_slice( $groups, 0, 8, true ) as $g ) {
        $parts[] = implode( ' / ', $g['path'] ) . ' ' . number_format_i18n( count( $g['members'] ) );
    }
    if ( count( $groups ) > 8 ) {
        $parts[] = '…';
    }
    $lines   = array();
    /* translators: 1: folders, 2: their names with counts */
    $lines[] = array( 'text' => sprintf( _n( '%1$s folder: %2$s', '%1$s folders: %2$s', (int) $out['made'], 'vergelabs-media-library' ), number_format_i18n( (int) $out['made'] ), implode( ', ', $parts ) ), 'strong' => true );
    /* translators: %s: pictures that move */
    $lines[] = array( 'text' => sprintf( _n( '%s picture moves', '%s pictures move', (int) $out['move'], 'vergelabs-media-library' ), number_format_i18n( (int) $out['move'] ) ) );
    /* translators: %s: the smallest folder */
    $why = sprintf( __( 'subject under %s', 'vergelabs-media-library' ), number_format_i18n( $min ) );
    $out['preview'] = array_merge( $lines, vergeml_guide_rule_tail( $o['scope'], $small + $none, $none && ! $small ? __( 'no subject', 'vergelabs-media-library' ) : $why, count( vergeml_folders_nodes( $taxonomy ) ) ) );
    return $out;
}

/**
 *  Into today's folders: the evidence matcher (core/filing.php) run dry over
 *  the unfiled pictures against the folders that exist. Nothing new is made
 *  unless the rest is asked into an Unsorted folder.
 */
function vergeml_guide_rule_fit( $taxonomy, $o ) {

    $rows  = vergeml_guide_rule_rows( $taxonomy, 'unfiled', array( 'filing' ) );
    $live  = vergeml_guide_live_index( $taxonomy );
    $ids   = array_map( function ( $n ) { return (int) $n['id']; }, $live['nodes'] );
    $why   = array( 'floor' => 0, 'margin' => 0, 'gated' => 0 );
    $picks = array();

    if ( $ids && $rows && function_exists( 'vergeml_filing_profiles' ) ) {
        global $wpdb;
        // The matcher wants the vector too; read it for the unfiled rows only.
        $vectors  = array();
        $chunks   = array_chunk( array_map( function ( $r ) { return (int) $r['attachment_id']; }, $rows ), 500 );
        foreach ( $chunks as $chunk ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table; ids are integers.
            foreach ( (array) $wpdb->get_results( "SELECT attachment_id, embedding, tags FROM {$wpdb->vergeml_ai_index} WHERE attachment_id IN (" . implode( ',', $chunk ) . ')', ARRAY_A ) as $v ) {
                $vectors[ (int) $v['attachment_id'] ] = $v;
            }
        }
        $profiles = vergeml_filing_profiles( $ids, $taxonomy );
        foreach ( $rows as $r ) {
            $row   = array_merge( $r, isset( $vectors[ (int) $r['attachment_id'] ] ) ? $vectors[ (int) $r['attachment_id'] ] : array( 'embedding' => null, 'tags' => '' ) );
            $facts = vergeml_filing_facts( $row );
            $pick  = vergeml_filing_pick( $facts, $profiles );
            if ( $pick['term_id'] ) {
                $picks[ (int) $r['attachment_id'] ] = (int) $pick['term_id'];
            } elseif ( 'margin' === $pick['why'] && 'close' === $o['sure'] && ! empty( $pick['nearest'] ) ) {
                $picks[ (int) $r['attachment_id'] ] = (int) $pick['nearest'];
            } else {
                $w = isset( $why[ $pick['why'] ] ) ? $pick['why'] : 'floor';
                $why[ $w ]++;
            }
        }
    } else {
        $why['floor'] = count( $rows );
    }

    $groups = array();
    foreach ( $picks as $attachment => $tid ) {
        if ( ! isset( $live['by_id'][ $tid ] ) ) {
            continue;
        }
        $gkey = 'fit:' . $tid;
        if ( ! isset( $groups[ $gkey ] ) ) {
            // The live path, so the draft builder reuses the folder rather than making one.
            $names = array();
            $walk  = $tid;
            $guard = 0;
            while ( isset( $live['by_id'][ $walk ] ) && $guard++ < 64 ) {
                array_unshift( $names, $live['by_id'][ $walk ]['name'] );
                $walk = $live['by_id'][ $walk ]['parent'];
            }
            $groups[ $gkey ] = array( 'path' => $names, 'members' => array() );
        }
        $groups[ $gkey ]['members'][] = (int) $attachment;
    }
    $rest = $why['floor'] + $why['margin'] + $why['gated'];
    if ( 'unsorted' === $o['rest'] && $rest > 0 ) {
        $groups['unsorted'] = array( 'path' => array( __( 'Unsorted', 'vergelabs-media-library' ) ), 'members' => array() );
        foreach ( $rows as $r ) {
            if ( ! isset( $picks[ (int) $r['attachment_id'] ] ) ) {
                $groups['unsorted']['members'][] = (int) $r['attachment_id'];
            }
        }
    }

    $out   = vergeml_guide_rule_draft( $taxonomy, 'unfiled', $groups, $rows );
    $lines = array();
    $into  = count( $picks ) ? count( array_unique( array_values( $picks ) ) ) : 0;
    /* translators: 1: pictures that move, 2: folders they go to */
    $lines[] = array( 'text' => count( $picks ) ? sprintf( _n( '%1$s picture moves into %2$s folders', '%1$s pictures move into %2$s folders', count( $picks ), 'vergelabs-media-library' ), number_format_i18n( count( $picks ) ), number_format_i18n( $into ) ) : __( '0 pictures move', 'vergelabs-media-library' ), 'strong' => true );
    if ( $why['floor'] ) {
        /* translators: %s: pictures */
        $lines[] = array( 'text' => sprintf( __( '%s score below the floor', 'vergelabs-media-library' ), number_format_i18n( $why['floor'] ) ) );
    }
    if ( $why['margin'] ) {
        /* translators: %s: pictures */
        $lines[] = array( 'text' => sprintf( __( '%s too close to call', 'vergelabs-media-library' ), number_format_i18n( $why['margin'] ) ) );
    }
    if ( $why['gated'] ) {
        /* translators: %s: pictures */
        $lines[] = array( 'text' => sprintf( __( '%s the wrong kind', 'vergelabs-media-library' ), number_format_i18n( $why['gated'] ) ) );
    }
    if ( 'unsorted' === $o['rest'] && $rest > 0 ) {
        /* translators: %s: pictures */
        $lines[] = array( 'text' => sprintf( __( '%s go to Unsorted', 'vergelabs-media-library' ), number_format_i18n( $rest ) ) );
    }
    if ( ! count( $picks ) && $rows ) {
        $lines[] = array( 'text' => __( 'Today\'s folders do not describe the unfiled pictures. Use the conversation, or a rule that makes folders.', 'vergelabs-media-library' ) );
    }
    $out['preview'] = $lines;
    return $out;
}

function vergeml_guide_rest_rules( WP_REST_Request $request ) {
    return rest_ensure_response( vergeml_guide_rules_all() );
}

/**
 *  A rule, computed and adopted: the draft becomes the rule's, with one line
 *  in the conversation saying so. The assignments stay on the server; the
 *  Move recomputes them from the rule and its options.
 */
function vergeml_guide_rest_rule( WP_REST_Request $request ) {

    $r = vergeml_guide_rule( (string) $request->get_param( 'rule' ), (array) $request->get_param( 'options' ) );
    if ( is_wp_error( $r ) ) {
        return $r;
    }

    return rest_ensure_response( array(
        'draft'   => $r['draft'],
        'preview' => $r['preview'],
        'made'    => (int) $r['made'],
        'move'    => (int) $r['move'],
    ) );
}
