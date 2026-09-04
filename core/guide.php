<?php
/**
 *  Guided sorting: the session, the summary, the estimates, the routes.
 *
 *  Four screens converge on a folder tree in conversation with an assistant
 *  that only ever edits the draft. The library is touched once, on the last
 *  click, through the same apply and re-filing every other path uses.
 *
 *  @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const VERGEML_GUIDE_OPTION   = 'vergeml_guide_session';
const VERGEML_GUIDE_TURN_CAP = 25;
const VERGEML_GUIDE_SAMPLE   = 2000;


/* --------------------------------------------------------------- the page */

/*
 *  The guide's own page is gone: the September 2026 redesign merged it with
 *  Sort into folders into one surface (js/vergeml-sort.js), driven by this
 *  file's session and routes. The old address still lands somewhere.
 */
/*
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

function vergeml_guide_page() {
    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }
    echo '<div class="wrap vgml-guide-wrap"><div id="vgml-guide" class="vgml-guide" data-described="'
        . esc_attr( (string) vergeml_guide_described_count() ) . '"></div></div>';
}

add_filter( 'admin_body_class', 'vergeml_guide_body_class' );

function vergeml_guide_body_class( $classes ) {
    if ( isset( $_GET['page'] ) && 'media-guide' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, which screen this is.
        $classes .= ' vgml-guide-screen';
    }
    return $classes;
}

add_action( 'admin_enqueue_scripts', 'vergeml_guide_assets' );

function vergeml_guide_assets( $hook ) {
    if ( false === strpos( (string) $hook, 'media-guide' ) ) {
        return;
    }
    $base = plugin_dir_url( dirname( __FILE__ ) );
    $ver  = defined( 'VERGEML_VERSION' ) ? VERGEML_VERSION : '1';
    wp_enqueue_style( 'vergeml-guide', $base . 'css/vergeml-guide.css', array(), $ver );
    wp_style_add_data( 'vergeml-guide', 'rtl', 'replace' );
    wp_enqueue_script( 'vergeml-guide', $base . 'js/vergeml-guide.js', array( 'wp-element', 'wp-api-fetch', 'wp-i18n' ), $ver, true );
    wp_localize_script( 'vergeml-guide', 'vgmlGuide', array(
        'ns'         => VERGEML_REST_NS,
        'cap'        => VERGEML_GUIDE_TURN_CAP,
        'foldersUrl' => admin_url( 'admin.php?page=media-taxonomies' ),
        'aiUrl'      => admin_url( 'admin.php?page=media-ai' ),
    ) );
}

/** How many pictures are described; the guide only opens on a described library. */
function vergeml_guide_described_count() {
    global $wpdb;
    if ( ! isset( $wpdb->vergeml_ai_index ) ) {
        return 0;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL" );
}


/* ------------------------------------------------------------ the session */

function vergeml_guide_fresh() {
    return array(
        'version'         => 1,
        'state'           => 'library',
        'started_at'      => time(),
        'updated_at'      => time(),
        'goal'            => '',
        'summary'         => null,
        'proposals'       => array(),
        'draft'           => array( 'version' => 0, 'folders' => array(), 'tags' => array() ),
        'history'         => array(),
        'turns'           => array(),
        'assistant_turns' => 0,
        'apply'           => null,
    );
}

function vergeml_guide_session() {
    $s = get_option( VERGEML_GUIDE_OPTION );
    return is_array( $s ) && isset( $s['state'] ) ? $s : vergeml_guide_fresh();
}

function vergeml_guide_save( $session ) {
    $session['updated_at'] = time();
    // Bounded: the last ten drafts, the last sixty turns.
    $session['history'] = array_slice( (array) ( isset( $session['history'] ) ? $session['history'] : array() ), -10 );
    $session['turns']   = array_slice( (array) ( isset( $session['turns'] ) ? $session['turns'] : array() ), -60 );
    update_option( VERGEML_GUIDE_OPTION, $session, false );
    return $session;
}

/** A tree from the client or the service, made safe. A name never carries a slash. */
function vergeml_guide_clean_tree( $tree ) {
    $tree = is_array( $tree ) ? $tree : array();
    $out  = array( 'version' => isset( $tree['version'] ) ? (int) $tree['version'] : 0, 'folders' => array(), 'tags' => array() );
    foreach ( (array) ( isset( $tree['folders'] ) ? $tree['folders'] : array() ) as $f ) {
        if ( ! is_array( $f ) || empty( $f['name'] ) ) {
            continue;
        }
        $out['folders'][] = array(
            'name'     => str_replace( '/', '-', sanitize_text_field( (string) $f['name'] ) ),
            'parent'   => sanitize_text_field( (string) ( isset( $f['parent'] ) ? $f['parent'] : '' ) ),
            'matches'  => sanitize_text_field( (string) ( isset( $f['matches'] ) ? $f['matches'] : '' ) ),
            'classes'  => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( isset( $f['classes'] ) ? $f['classes'] : array() ) ) ) ),
            'kinds'    => array_values( array_filter( array_map( 'sanitize_key', (array) ( isset( $f['kinds'] ) ? $f['kinds'] : array() ) ) ) ),
            'audience' => sanitize_text_field( (string) ( isset( $f['audience'] ) ? $f['audience'] : '' ) ),
            'count'    => isset( $f['count'] ) ? (int) $f['count'] : 0,
            // Set aside on the Sort screen: kept in the draft, left out of the apply.
            'aside'    => ! empty( $f['aside'] ),
        );
    }
    foreach ( (array) ( isset( $tree['tags'] ) ? $tree['tags'] : array() ) as $t ) {
        if ( is_array( $t ) && ! empty( $t['name'] ) ) {
            $out['tags'][] = array(
                'name'   => sanitize_text_field( (string) $t['name'] ),
                'values' => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( isset( $t['values'] ) ? $t['values'] : array() ) ) ) ),
            );
        }
    }
    return $out;
}


/* ------------------------------------------------------------ the summary */

/**
 *  What the describer saw, from what already exists: SQL over the catalogue
 *  and the groups the chat computes. Never the full records into a model.
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

    $folders = array();
    foreach ( (array) vergeml_talk_current() as $c ) {
        $folders[] = array(
            'name'   => (string) $c['name'],
            'parent' => (string) ( isset( $c['parent'] ) ? $c['parent'] : '' ),
            'count'  => (int) ( isset( $c['count'] ) ? $c['count'] : 0 ),
        );
    }
    $by_kind = array();
    foreach ( (array) $kinds as $k ) {
        $by_kind[ '' === (string) $k['kind'] ? 'photo' : (string) $k['kind'] ] = (int) $k['n'];
    }

    // Described pictures in no folder right now: the one real "unfiled" the
    // Sort screen can say, beside the draft's estimate.
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


/* ----------------------------------------------------------- the estimate */

/**
 *  Real counts for a proposed tree, without embeddings: the share of a
 *  sample of catalogue records whose class words match the folder's classes
 *  (exact, plural, substring), scaled to the library. Deterministic and fast.
 *  A folder with a kind list only counts pictures of those kinds.
 */
function vergeml_guide_estimate( $folders ) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT filing, kind FROM {$wpdb->vergeml_ai_index} WHERE error = '' AND embedding IS NOT NULL ORDER BY described_at DESC LIMIT %d", VERGEML_GUIDE_SAMPLE ), ARRAY_A );
    $total = vergeml_guide_described_count();
    $scale = count( $rows ) > 0 ? $total / count( $rows ) : 1;

    $facts = array();
    foreach ( (array) $rows as $r ) {
        $f       = json_decode( (string) $r['filing'], true );
        $facts[] = array(
            'classes' => vergeml_filing_classes_of_object( is_array( $f ) && isset( $f['object'] ) ? $f['object'] : '' ),
            'kind'    => '' === (string) $r['kind'] ? 'photo' : (string) $r['kind'],
        );
    }

    /*
     *  One picture, one folder. Counting each folder on its own let a valley
     *  count for Landscape, Mountains and Piers at once and the tree claimed
     *  1,169 of 641 pictures. Each sample record now goes to the most specific
     *  folder that matches it -- deepest path first, then fewest classes --
     *  so the counts partition the library and add up to at most the total.
     */
    $depth = array();
    $names = array();
    foreach ( $folders as $i => $f ) {
        $names[ mb_strtolower( (string) $f['name'] ) ] = $i;
    }
    foreach ( $folders as $i => $f ) {
        $d = 0;
        $p = mb_strtolower( (string) ( isset( $f['parent'] ) ? $f['parent'] : '' ) );
        while ( '' !== $p && isset( $names[ $p ] ) && $d < 8 ) {
            $d++;
            $p = mb_strtolower( (string) ( isset( $folders[ $names[ $p ] ]['parent'] ) ? $folders[ $names[ $p ] ]['parent'] : '' ) );
        }
        $depth[ $i ] = $d;
    }
    $matches = function ( $pc, $fc ) {
        if ( $pc === $fc || rtrim( $pc, 's' ) === rtrim( $fc, 's' ) ) {
            return true;
        }
        $words = array_map( function ( $w ) { return rtrim( $w, 's' ); }, explode( ' ', $pc ) );
        return in_array( rtrim( $fc, 's' ), $words, true );
    };
    $counts = array_fill( 0, count( $folders ), 0 );
    foreach ( $facts as $fact ) {
        $best = -1;
        foreach ( $folders as $i => $f ) {
            $classes = array_map( 'mb_strtolower', (array) ( isset( $f['classes'] ) ? $f['classes'] : array() ) );
            if ( ! $classes ) {
                $classes = array( mb_strtolower( (string) $f['name'] ) );
            }
            $kinds = (array) ( isset( $f['kinds'] ) ? $f['kinds'] : array() );
            if ( $kinds && ! in_array( $fact['kind'], $kinds, true ) ) {
                continue;
            }
            $hit = false;
            foreach ( $fact['classes'] as $pc ) {
                foreach ( $classes as $fc ) {
                    if ( $matches( $pc, $fc ) ) {
                        $hit = true;
                        break 2;
                    }
                }
            }
            if ( ! $hit ) {
                continue;
            }
            if ( -1 === $best
                || $depth[ $i ] > $depth[ $best ]
                || ( $depth[ $i ] === $depth[ $best ] && count( (array) $f['classes'] ) < count( (array) $folders[ $best ]['classes'] ) ) ) {
                $best = $i;
            }
        }
        if ( $best >= 0 ) {
            $counts[ $best ]++;
        }
    }
    foreach ( $folders as $i => &$folder ) {
        $folder['count'] = (int) round( $counts[ $i ] * $scale );
    }
    unset( $folder );
    return $folders;
}


/* ----------------------------------------------------------- the service */

function vergeml_guide_call( $mode, $payload ) {
    $settings = vergeml_ai_settings();
    $licence  = vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
    if ( '' === $licence ) {
        return new WP_Error( 'no_licence', __( 'Connect a licence on the Licence tab first.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }
    $response = wp_remote_post(
        vergeml_ai_service_url() . '/guide',
        array(
            'timeout'   => 110,
            'headers'   => array( 'Content-Type' => 'application/json' ),
            'sslverify' => true,
            'body'      => wp_json_encode( array_merge( array( 'license_key' => $licence, 'site' => home_url(), 'mode' => $mode ), $payload ) ),
        )
    );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'unreachable', __( 'Could not reach the service. Your draft is safe; try again in a moment.', 'vergelabs-media-library' ), array( 'status' => 502 ) );
    }
    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( 200 !== $code || ! is_array( $data ) ) {
        $why = is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : 'HTTP ' . $code;
        if ( 'could_not_answer' === $why ) {
            $msg = __( 'I did not follow that. Say it another way.', 'vergelabs-media-library' );
        } elseif ( 'provider_busy' === $why ) {
            $msg = __( 'The assistant is busy right now. Your draft is safe; try again in a minute.', 'vergelabs-media-library' );
        } else {
            /* translators: %s: the service's error code */
            $msg = sprintf( __( 'The service answered: %s', 'vergelabs-media-library' ), $why );
        }
        return new WP_Error( 'service_' . $code, $msg, array( 'status' => 502 ) );
    }
    return $data;
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
            'args'                => array( 'session' => array( 'type' => 'object', 'required' => true ) ),
        ),
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/summary', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_guide_rest_summary',
        'permission_callback' => $may,
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/propose', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_guide_rest_propose',
        'permission_callback' => $may,
        'args'                => array( 'goal' => array( 'type' => 'string', 'required' => false ) ),
    ) );
    register_rest_route( VERGEML_REST_NS, '/guide/turn', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_guide_rest_turn',
        'permission_callback' => $may,
        'args'                => array(
            'text'   => array( 'type' => 'string', 'required' => false ),
            'choice' => array( 'type' => 'string', 'required' => false ),
            'edit'   => array( 'type' => 'string', 'required' => false ),
            'draft'  => array( 'type' => 'object', 'required' => false ),
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
}

function vergeml_guide_rest_session( WP_REST_Request $request ) {
    if ( 'POST' === $request->get_method() ) {
        $in      = (array) $request->get_param( 'session' );
        $cur     = vergeml_guide_session();
        $allowed = array( 'library', 'proposal', 'shaping', 'review', 'applying', 'done' );
        // Back to the first screen means a new session: nothing of the old one is kept.
        if ( 'library' === (string) ( isset( $in['state'] ) ? $in['state'] : '' ) && 'library' !== $cur['state'] ) {
            return rest_ensure_response( vergeml_guide_save( vergeml_guide_fresh() ) );
        }
        if ( isset( $in['state'] ) && in_array( (string) $in['state'], $allowed, true ) ) {
            $cur['state'] = (string) $in['state'];
        }
        if ( isset( $in['goal'] ) ) {
            $cur['goal'] = sanitize_textarea_field( (string) $in['goal'] );
        }
        if ( isset( $in['draft'] ) ) {
            $draft = vergeml_guide_clean_tree( $in['draft'] );
            $was   = $cur['draft'];
            $draft['version'] = (int) $was['version'];
            if ( wp_json_encode( $draft ) !== wp_json_encode( $was ) ) {
                $cur['history'][] = array( 'version' => (int) $was['version'], 'draft' => $was );
                $draft['version'] = (int) $was['version'] + 1;
                $cur['draft']     = $draft;
            }
        }
        // assistant_turns and apply stay the server's.
        return rest_ensure_response( vergeml_guide_save( $cur ) );
    }
    return rest_ensure_response( vergeml_guide_session() );
}

function vergeml_guide_rest_summary( WP_REST_Request $request ) {
    $s         = vergeml_guide_session();
    $described = vergeml_guide_described_count();
    if ( ! is_array( $s['summary'] ) || (int) ( isset( $s['summary']['total'] ) ? $s['summary']['total'] : -1 ) !== $described ) {
        $s['summary'] = vergeml_guide_summary();
        $s            = vergeml_guide_save( $s );
    }
    return rest_ensure_response( $s['summary'] );
}

function vergeml_guide_rest_propose( WP_REST_Request $request ) {
    $s = vergeml_guide_session();
    if ( null !== $request->get_param( 'goal' ) ) {
        $s['goal'] = sanitize_textarea_field( (string) $request->get_param( 'goal' ) );
    }
    if ( ! is_array( $s['summary'] ) ) {
        $s['summary'] = vergeml_guide_summary();
    }
    $data = vergeml_guide_call( 'propose', array(
        'summary' => $s['summary'],
        'goal'    => $s['goal'],
        'current' => $s['summary']['folders'],
    ) );
    if ( is_wp_error( $data ) ) {
        vergeml_guide_save( $s );
        return $data;
    }
    $proposals = array();
    foreach ( (array) ( isset( $data['proposals'] ) ? $data['proposals'] : array() ) as $p ) {
        $tree            = vergeml_guide_clean_tree( isset( $p['tree'] ) ? $p['tree'] : array() );
        $tree['folders'] = vergeml_guide_estimate( $tree['folders'] );
        $proposals[]     = array( 'name' => sanitize_text_field( (string) ( isset( $p['name'] ) ? $p['name'] : '' ) ), 'tree' => $tree );
    }
    $s['proposals'] = $proposals;
    $s['state']     = 'proposal';
    vergeml_guide_save( $s );
    return rest_ensure_response( array( 'proposals' => $proposals ) );
}

function vergeml_guide_rest_turn( WP_REST_Request $request ) {
    $s = vergeml_guide_session();
    if ( (int) $s['assistant_turns'] >= VERGEML_GUIDE_TURN_CAP ) {
        return new WP_Error(
            'cap',
            sprintf( __( 'This session has used its %d turns. You can still shape the tree by hand and file it.', 'vergelabs-media-library' ), VERGEML_GUIDE_TURN_CAP ),
            array( 'status' => 429 )
        );
    }
    if ( null !== $request->get_param( 'draft' ) ) {
        $draft            = vergeml_guide_clean_tree( $request->get_param( 'draft' ) );
        $draft['version'] = (int) $s['draft']['version'];
        $s['draft']       = $draft;
    }
    $input = array();
    foreach ( array( 'text', 'choice', 'edit' ) as $k ) {
        $v = $request->get_param( $k );
        if ( null !== $v && '' !== trim( (string) $v ) ) {
            $input[ $k ] = sanitize_textarea_field( (string) $v );
        }
    }
    if ( ! $input ) {
        return new WP_Error( 'empty', __( 'Say something first.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }
    if ( isset( $input['text'] ) ) {
        $said = $input['text'];
    } elseif ( isset( $input['choice'] ) ) {
        $said = $input['choice'];
    } else {
        /* translators: %s: what the owner did to the draft, e.g. "renamed Monitors to Displays" */
        $said = sprintf( __( 'I %s', 'vergelabs-media-library' ), $input['edit'] );
    }
    $s['turns'][] = array( 'role' => 'user', 'text' => $said, 'at' => time() );

    if ( ! is_array( $s['summary'] ) ) {
        $s['summary'] = vergeml_guide_summary();
    }
    $data = vergeml_guide_call( 'turn', array(
        'summary' => $s['summary'],
        'goal'    => $s['goal'],
        'current' => (array) ( isset( $s['summary']['folders'] ) ? $s['summary']['folders'] : array() ),
        'draft'   => array(
            'folders' => array_values( array_filter( (array) $s['draft']['folders'], function ( $f ) { return empty( $f['aside'] ); } ) ),
            'tags'    => $s['draft']['tags'],
        ),
        'turns'   => array_slice( $s['turns'], -20 ),
        'input'   => $input,
    ) );
    if ( is_wp_error( $data ) ) {
        vergeml_guide_save( $s );
        return $data;
    }
    $answer = array(
        'message' => sanitize_textarea_field( (string) ( isset( $data['message'] ) ? $data['message'] : '' ) ),
        'choices' => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( isset( $data['choices'] ) ? $data['choices'] : array() ) ) ) ),
    );
    if ( isset( $data['draft'] ) && is_array( $data['draft'] ) ) {
        $draft            = vergeml_guide_clean_tree( $data['draft'] );
        $draft['folders'] = vergeml_guide_estimate( $draft['folders'] );
        $s['history'][]   = array( 'version' => (int) $s['draft']['version'], 'draft' => $s['draft'] );
        $draft['version'] = (int) $s['draft']['version'] + 1;
        $s['draft']       = $draft;
        $answer['draft']  = $draft;
    }
    $s['turns'][]         = array( 'role' => 'assistant', 'text' => $answer['message'], 'choices' => $answer['choices'], 'at' => time() );
    $s['assistant_turns'] = (int) $s['assistant_turns'] + 1;
    $s['state']           = 'shaping';
    vergeml_guide_save( $s );
    $answer['assistant_turns'] = $s['assistant_turns'];
    $answer['draft_version']   = (int) $s['draft']['version'];
    return rest_ensure_response( $answer );
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

function vergeml_guide_rest_apply( WP_REST_Request $request ) {
    $s       = vergeml_guide_session();
    $folders = array();
    foreach ( (array) $s['draft']['folders'] as $f ) {
        if ( ! empty( $f['aside'] ) ) {
            continue;
        }
        $folders[] = array(
            'name'     => $f['name'],
            'parent'   => $f['parent'],
            'matches'  => $f['matches'],
            'classes'  => $f['classes'],
            'kinds'    => $f['kinds'],
            'audience' => $f['audience'],
        );
    }
    if ( ! $folders ) {
        return new WP_Error( 'empty', __( 'The draft has no folders.', 'vergelabs-media-library' ), array( 'status' => 400 ) );
    }
    $tags = vergeml_guide_make_tags( $s['draft']['tags'] );
    $r    = vergeml_talk_apply( $folders, $tags );
    if ( is_wp_error( $r ) ) {
        vergeml_guide_unmake_tags( $tags );
        return $r;
    }
    $s['state'] = 'applying';
    $s['apply'] = array( 'started_at' => time() );
    vergeml_guide_save( $s );
    return rest_ensure_response( vergeml_talk_progress() );
}

function vergeml_guide_rest_progress( WP_REST_Request $request ) {
    $report = vergeml_talk_progress();
    $s      = vergeml_guide_session();
    if ( 'applying' === $s['state'] && empty( $report['running'] ) ) {
        $s['state'] = 'done';
        vergeml_guide_save( $s );
    }
    $report['state'] = $s['state'];
    return rest_ensure_response( $report );
}
