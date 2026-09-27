<?php
/**
 *  The bottom-up planner (spec-tree-planner, story 3): every described
 *  picture's label, counted here into an inventory of distinct labels, sent
 *  to the service's /plan-tree, which arranges them fifteen times and sends
 *  back every valid answer. Here, with the pictures' vectors that never leave
 *  the site, the answer whose pictures sit closest together is kept and the
 *  labels the model left out join their nearest folder. That becomes the
 *  Folders screen's draft, with every existing folder kept where it is.
 *
 *  Measured in tools/tree-lab.mjs (improve mode, 2026-09-27): the shop's two
 *  builds recovered 41 and 43 of 47 real folders at 77-80 % purity with 9 %
 *  unfiled; tech 9 of 11 at 81-82 %, 4-6 % unfiled.
 *
 *  The price is counted on this site from the inventory, with no service
 *  call, and is the same sum the service charges (lib/plan-tree.ts planPrice).
 *
 *  The service asks fifteen runs at once and can take longer than a proxy holds a
 *  request open, so the press books a job and the screen polls it.
 *
 *  @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const VERGEML_PLAN_HOOK    = 'vergeml_plan_event';
const VERGEML_PLAN_LOCK    = 'vergeml_planning';
const VERGEML_PLAN_TIMEOUT = 280;
const VERGEML_PLAN_CACHE   = 'vergeml_plan_inventory';
/** A label the model left out joins the nearest folder at this cosine or more; below it, it stays unfiled. Tuned on shop and tech. */
const VERGEML_PLAN_PLACE   = 0.5;

/** Ten credits and six per hundred labels, rounded up: the service's planPrice(). */
function vergeml_plan_price( $labels ) {
    return 10 + (int) ceil( max( 0, (int) $labels ) * 6 / 100 );
}

/**
 *  Every described picture's label, as distinct labels with their counts.
 *  A label is the describer's object and its broader class, and the kind
 *  when the picture is not a photo -- the key tools/tree-lab.mjs measured.
 *  Cached against the described count and the newest description.
 *
 * @return array{labels:array,pictures:int,unlabelled:int}
 */
function vergeml_plan_inventory() {
    global $wpdb;
    $t = $wpdb->vergeml_ai_index;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $stamp = $wpdb->get_row( "SELECT COUNT(*) AS n, MAX(described_at) AS at FROM {$t} WHERE error = '' AND embedding IS NOT NULL", ARRAY_A );
    $key   = md5( wp_json_encode( $stamp ) );
    $held  = get_option( VERGEML_PLAN_CACHE );
    if ( is_array( $held ) && isset( $held['key'] ) && $held['key'] === $key ) {
        return $held['inventory'];
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $rows = $wpdb->get_results( "SELECT kind, filing FROM {$t} WHERE error = '' AND embedding IS NOT NULL", ARRAY_A );

    $by         = array();
    $unlabelled = 0;
    foreach ( (array) $rows as $r ) {
        $filing = json_decode( (string) $r['filing'], true );
        $label  = vergeml_plan_label_of( $r['kind'], $filing );
        if ( '' === $label ) {
            ++$unlabelled;
            continue;
        }
        if ( ! isset( $by[ $label ] ) ) {
            $classes      = vergeml_filing_classes_of_object( $filing['object'] );
            $kind         = sanitize_key( (string) $r['kind'] );
            $by[ $label ] = array( 'label' => $label, 'class' => $classes[0], 'kind' => '' === $kind ? 'photo' : $kind, 'count' => 0, 'audience' => array() );
        }
        ++$by[ $label ]['count'];
        $aud = vergeml_filing_audience_of_picture( is_array( $filing ) && isset( $filing['audience'] ) ? $filing['audience'] : '' );
        if ( in_array( $aud, array( 'men', 'women', 'kids' ), true ) ) {
            $by[ $label ]['audience'][ $aud ] = ( isset( $by[ $label ]['audience'][ $aud ] ) ? $by[ $label ]['audience'][ $aud ] : 0 ) + 1;
        }
    }

    uasort( $by, function ( $a, $b ) {
        return $b['count'] - $a['count'] ?: strcmp( $a['label'], $b['label'] );
    } );
    $labels = array();
    foreach ( array_values( $by ) as $i => $l ) {
        $labels[] = array_merge( array( 'id' => 'l' . $i ), $l );
    }

    $inventory = array( 'labels' => $labels, 'pictures' => count( (array) $rows ), 'unlabelled' => $unlabelled );
    update_option( VERGEML_PLAN_CACHE, array( 'key' => $key, 'inventory' => $inventory ), false );
    return $inventory;
}

/** A picture's label: "object; class", with [kind] when not a photo. '' when the describer named no object. */
function vergeml_plan_label_of( $kind, $filing ) {
    $classes = vergeml_filing_classes_of_object( is_array( $filing ) && isset( $filing['object'] ) ? $filing['object'] : '' );
    if ( ! $classes ) {
        return '';
    }
    $kind = sanitize_key( (string) $kind );
    $kind = '' === $kind ? 'photo' : $kind;
    return $classes[0] . ( isset( $classes[1] ) ? '; ' . $classes[1] : '' ) . ( 'photo' === $kind ? '' : ' [' . $kind . ']' );
}

/**
 *  Each label's pictures as one vector: the sum of their unit vectors, read
 *  in pages so a large library never sits in memory whole. A folder's sum is
 *  its labels' sums added; its length over the folder's pictures is the mean
 *  cosine of a picture to the folder's centre -- how tight the folder is.
 *
 * @return array label id => float[]
 */
function vergeml_plan_label_sums( $labels ) {
    global $wpdb;
    $t     = $wpdb->vergeml_ai_index;
    $id_of = array();
    foreach ( $labels as $l ) {
        $id_of[ $l['label'] ] = $l['id'];
    }
    $sums  = array();
    $after = 0;
    do {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
        $rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id, kind, filing, embedding FROM {$t} WHERE error = '' AND embedding IS NOT NULL AND attachment_id > %d ORDER BY attachment_id ASC LIMIT 500", $after ), ARRAY_A );
        foreach ( $rows as $r ) {
            $after = (int) $r['attachment_id'];
            $label = vergeml_plan_label_of( $r['kind'], json_decode( (string) $r['filing'], true ) );
            $v     = vergeml_index_vector_out( $r['embedding'] );
            if ( '' === $label || ! isset( $id_of[ $label ] ) || ! $v ) {
                continue;
            }
            $v  = vergeml_plan_unit( $v );
            $id = $id_of[ $label ];
            $sums[ $id ] = isset( $sums[ $id ] ) ? vergeml_plan_add( $sums[ $id ], $v ) : $v;
        }
    } while ( 500 === count( $rows ) );
    return $sums;
}

function vergeml_plan_unit( $v ) {
    $n = sqrt( vergeml_plan_dot( $v, $v ) );
    return $n > 0 ? array_map( function ( $x ) use ( $n ) {
        return $x / $n;
    }, $v ) : $v;
}

function vergeml_plan_add( $a, $b ) {
    foreach ( $b as $i => $x ) {
        $a[ $i ] += $x;
    }
    return $a;
}

function vergeml_plan_dot( $a, $b ) {
    $d = 0.0;
    foreach ( $a as $i => $x ) {
        $d += $x * $b[ $i ];
    }
    return $d;
}

/**
 *  Of the service's trees, the one to keep, with the labels it left out
 *  placed. Per tree: each folder's centre from the labels in it; a label left
 *  out joins the folder whose centre is nearest, at VERGEML_PLAN_PLACE or
 *  more; then its tightness, the mean cosine of a picture to its folder's
 *  centre. The tightest is kept -- no truth read, and in the lab it ranked
 *  the runs as their true scores did (shop: 0.770 for 41 of 47, 0.729 for 26).
 *
 * @param array $trees  The service's trees: folders [{path, labels[]}], unfiled [ids].
 * @param array $sums   vergeml_plan_label_sums().
 * @param array $counts Label id => pictures.
 * @return array{folders:array,index:int,placed:int,tightness:float}
 */
function vergeml_plan_choose( $trees, $sums, $counts ) {
    $best = null;
    foreach ( $trees as $index => $tree ) {
        $folders = array();
        $centre  = array();
        foreach ( (array) $tree['folders'] as $f ) {
            $folders[ $f['path'] ] = $f;
            $s                     = null;
            foreach ( (array) $f['labels'] as $id ) {
                if ( isset( $sums[ $id ] ) ) {
                    $s = null === $s ? $sums[ $id ] : vergeml_plan_add( $s, $sums[ $id ] );
                }
            }
            if ( null !== $s ) {
                $centre[ $f['path'] ] = vergeml_plan_unit( $s );
            }
        }
        $placed = 0;
        foreach ( (array) $tree['unfiled'] as $id ) {
            if ( ! isset( $sums[ $id ] ) ) {
                continue;
            }
            $v   = vergeml_plan_unit( $sums[ $id ] );
            $to  = '';
            $top = -1.0;
            foreach ( $centre as $path => $c ) {
                $x = vergeml_plan_dot( $v, $c );
                if ( $x > $top ) {
                    $top = $x;
                    $to  = $path;
                }
            }
            if ( '' !== $to && $top >= VERGEML_PLAN_PLACE ) {
                $folders[ $to ]['labels'][] = $id;
                ++$placed;
            }
        }
        $length = 0.0;
        $n      = 0;
        foreach ( $folders as $f ) {
            $s = null;
            foreach ( (array) $f['labels'] as $id ) {
                if ( isset( $sums[ $id ] ) ) {
                    $s  = null === $s ? $sums[ $id ] : vergeml_plan_add( $s, $sums[ $id ] );
                    $n += isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0;
                }
            }
            if ( null !== $s ) {
                $length += sqrt( vergeml_plan_dot( $s, $s ) );
            }
        }
        $tightness = $n > 0 ? $length / $n : 0.0;
        if ( null === $best || $tightness > $best['tightness'] ) {
            $best = array( 'folders' => array_values( $folders ), 'index' => (int) $index, 'placed' => $placed, 'tightness' => $tightness );
        }
    }
    return $best;
}

/** What the button needs: the price, the label count, and the balance where one is known. */
function vergeml_plan_facts() {
    $inv   = vergeml_plan_inventory();
    $state = function_exists( 'vergeml_ai_credits_state' ) ? vergeml_ai_credits_state() : array( 'remaining' => null );
    return array(
        'labels'  => count( $inv['labels'] ),
        'price'   => vergeml_plan_price( count( $inv['labels'] ) ),
        'balance' => null === $state['remaining'] ? null : (int) $state['remaining'],
    );
}


/* ---------------------------------------------------------------- the job */

/** The press: books the job, once. The session carries its state so a reload keeps polling. */
function vergeml_plan_rest_start() {
    $s = vergeml_guide_session();
    if ( 'confirmed' === $s['tree'] ) {
        return vergeml_guide_confirmed_refusal();
    }
    if ( isset( $s['plan']['state'] ) && 'running' === $s['plan']['state'] ) {
        return rest_ensure_response( vergeml_plan_out( $s ) );
    }
    $facts = vergeml_plan_facts();
    if ( 0 === $facts['labels'] ) {
        return new WP_Error( 'empty', __( 'No picture is described yet.', 'vergelabs-media-library' ), array( 'status' => 409 ) );
    }
    $s['plan'] = array( 'state' => 'running', 'at' => time(), 'price' => $facts['price'] );
    vergeml_guide_save( $s );
    vergeml_plan_schedule();
    return rest_ensure_response( vergeml_plan_out( $s ) );
}

/** The poll: the plan's state and, once it is done, the session with its draft. A job cron never started is booked again. */
function vergeml_plan_rest_poll() {
    $s = vergeml_guide_session();
    if ( isset( $s['plan']['state'] ) && 'running' === $s['plan']['state'] && ! get_transient( VERGEML_PLAN_LOCK ) ) {
        if ( time() - (int) $s['plan']['at'] > VERGEML_PLAN_TIMEOUT + 120 ) {
            $s['plan'] = array( 'state' => 'failed', 'message' => __( 'The plan did not finish. Try again.', 'vergelabs-media-library' ) );
            vergeml_guide_save( $s );
        } elseif ( ! wp_next_scheduled( VERGEML_PLAN_HOOK ) ) {
            vergeml_plan_schedule();
        }
    }
    return rest_ensure_response( vergeml_plan_out( $s ) );
}

function vergeml_plan_out( $s ) {
    return vergeml_guide_session_out( $s );
}

function vergeml_plan_schedule() {
    if ( ! wp_next_scheduled( VERGEML_PLAN_HOOK ) ) {
        wp_schedule_single_event( time(), VERGEML_PLAN_HOOK );
    }
    if ( ! defined( 'DOING_CRON' ) ) {
        spawn_cron();
    }
}

add_action( VERGEML_PLAN_HOOK, 'vergeml_plan_event' );

function vergeml_plan_event() {
    $s = vergeml_guide_session();
    if ( ! isset( $s['plan']['state'] ) || 'running' !== $s['plan']['state'] || get_transient( VERGEML_PLAN_LOCK ) ) {
        return;
    }
    set_transient( VERGEML_PLAN_LOCK, time(), VERGEML_PLAN_TIMEOUT + 60 );
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( VERGEML_PLAN_TIMEOUT + 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- a cron job; refused silently where disallowed.
    }

    $inv    = vergeml_plan_inventory();
    $result = vergeml_plan_ask( $inv['labels'] );

    $s = vergeml_guide_session();
    if ( is_wp_error( $result ) ) {
        $s['plan'] = array( 'state' => 'failed', 'message' => $result->get_error_message() );
    } elseif ( 'confirmed' === $s['tree'] ) {
        // Confirmed while the plan ran: the confirmed tree wins and the plan is dropped.
        $s['plan'] = null;
    } else {
        // An older service sends its one kept tree; a current one every valid tree, to choose among here.
        $trees      = ! empty( $result['trees'] ) ? $result['trees'] : array( array( 'folders' => $result['folders'], 'unfiled' => isset( $result['unfiled'] ) ? $result['unfiled'] : array() ) );
        $chosen     = vergeml_plan_choose( $trees, vergeml_plan_label_sums( $inv['labels'] ), array_column( $inv['labels'], 'count', 'id' ) );
        $s['draft'] = vergeml_plan_draft( $chosen['folders'], $inv['labels'] );
        $taxonomy   = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
        if ( '' !== $taxonomy ) {
            vergeml_guide_fit_take( $s, $taxonomy );
        }
        $s['plan'] = array(
            'state'   => 'done',
            'charged' => isset( $result['charged'] ) ? (int) $result['charged'] : 0,
            'runs'    => isset( $result['runs'] ) ? $result['runs'] : null,
            'kept'    => $chosen['index'],
            'placed'  => $chosen['placed'],
            'left_out'=> (int) $inv['unlabelled'],
        );
        if ( isset( $result['credits_remaining'] ) ) {
            $credits              = get_option( 'vergeml_ai_credits', array() );
            $credits              = is_array( $credits ) ? $credits : array();
            $credits['remaining'] = (int) $result['credits_remaining'];
            $credits['time']      = time();
            update_option( 'vergeml_ai_credits', $credits, false );
        }
    }
    vergeml_guide_save( $s );
    delete_transient( VERGEML_PLAN_LOCK );
}

/** The one outbound call: the labels, their counts and audience counts. Never a picture, a caption or a file name. */
function vergeml_plan_ask( $labels ) {
    $licence = function_exists( 'vergeml_ai_settings' ) ? vergeml_ai_unseal( vergeml_ai_settings()['license_key'] ) : '';
    if ( '' === $licence ) {
        return new WP_Error( 'no_licence', __( 'This needs a licence key. Add yours under AI.', 'vergelabs-media-library' ) );
    }

    $send = array();
    foreach ( $labels as $l ) {
        $send[] = array( 'id' => $l['id'], 'label' => $l['label'], 'count' => $l['count'], 'audience' => (object) $l['audience'] );
    }

    $response = wp_remote_post( vergeml_ai_service_url() . '/plan-tree', array(
        'timeout'   => VERGEML_PLAN_TIMEOUT,
        'headers'   => array( 'Content-Type' => 'application/json' ),
        'sslverify' => true,
        'body'      => wp_json_encode( array( 'license_key' => $licence, 'site' => home_url(), 'labels' => $send ) ),
    ) );
    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'unreachable', __( 'The service could not be reached. Nothing was charged.', 'vergelabs-media-library' ) );
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    $err  = is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : '';

    if ( 200 === $code && is_array( $data ) && ! empty( $data['folders'] ) ) {
        return $data;
    }
    if ( 402 === $code ) {
        /* translators: 1: credits the plan costs, 2: credits left */
        return new WP_Error( 'credits', sprintf( __( 'The plan costs %1$s credits and %2$s are left.', 'vergelabs-media-library' ), (int) ( isset( $data['charge'] ) ? $data['charge'] : 0 ), (int) ( isset( $data['credits_remaining'] ) ? $data['credits_remaining'] : 0 ) ) );
    }
    if ( 'could_not_plan' === $err ) {
        return new WP_Error( 'could_not_plan', __( 'The plan did not come together. Nothing was charged.', 'vergelabs-media-library' ) );
    }
    if ( in_array( $err, array( 'not_entitled', 'not_found', 'site_not_activated', 'bad_key' ), true ) ) {
        return new WP_Error( 'licence', __( 'This site\'s licence is not active. Check it under Licence.', 'vergelabs-media-library' ) );
    }
    if ( 429 === $code ) {
        return new WP_Error( 'capped', __( 'That is today\'s limit of plans. Try again tomorrow.', 'vergelabs-media-library' ) );
    }
    /* translators: %s: the service's error code */
    return new WP_Error( 'failed', sprintf( __( 'The service answered: %s', 'vergelabs-media-library' ), '' !== $err ? $err : (string) $code ) );
}

/**
 *  The service's folders as the screen's draft. Every existing folder is in
 *  it where it stands (a folder left out of a draft reads as removed); a
 *  planned folder with an existing folder's name is that folder, and keeps
 *  its place. The rest are new, under their planned parents. A folder's
 *  classes are its labels' objects, which is what the matcher files by.
 */
function vergeml_plan_draft( $planned, $labels ) {
    $by_id = array();
    foreach ( $labels as $l ) {
        $by_id[ $l['id'] ] = $l;
    }

    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
    $terms    = '' !== $taxonomy ? get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) : array();
    $terms    = is_array( $terms ) ? $terms : array();

    $out     = array( 'folders' => array(), 'gone' => array(), 'tags' => array(), 'origin' => 'talk', 'rule' => null );
    $by_name = array();
    foreach ( $terms as $t ) {
        $by_name[ mb_strtolower( $t->name ) ] = count( $out['folders'] );
        $out['folders'][] = array(
            'key' => 't' . $t->term_id, 'term_id' => (int) $t->term_id, 'name' => $t->name,
            'parent' => $t->parent ? 't' . $t->parent : '', 'count' => null, 'matches' => '',
            'classes' => array(), 'nowords' => false, 'kinds' => array(), 'audience' => '', 'by' => '', 'asked' => false,
        );
    }

    $key_of = array();
    foreach ( (array) $planned as $i => $f ) {
        $name = str_replace( '/', '-', sanitize_text_field( (string) ( isset( $f['name'] ) ? $f['name'] : '' ) ) );
        $path = (string) ( isset( $f['path'] ) ? $f['path'] : '' );
        if ( '' === $name || '' === $path ) {
            continue;
        }
        $classes = array();
        $kinds   = array();
        foreach ( (array) ( isset( $f['labels'] ) ? $f['labels'] : array() ) as $id ) {
            if ( isset( $by_id[ $id ] ) ) {
                $classes[] = $by_id[ $id ]['class'];
                $kinds[]   = $by_id[ $id ]['kind'];
            }
        }
        $classes = array_values( array_unique( $classes ) );
        $kinds   = array_values( array_unique( $kinds ) );

        $n = mb_strtolower( $name );
        if ( isset( $by_name[ $n ] ) ) {
            $at = $by_name[ $n ];
            $out['folders'][ $at ]['classes'] = array_values( array_unique( array_merge( $out['folders'][ $at ]['classes'], $classes ) ) );
            $out['folders'][ $at ]['kinds']   = array_values( array_unique( array_merge( $out['folders'][ $at ]['kinds'], $kinds ) ) );
            $key_of[ $path ] = $out['folders'][ $at ]['key'];
            continue;
        }
        $key_of[ $path ]  = 'p' . $i;
        $by_name[ $n ]    = count( $out['folders'] );
        $out['folders'][] = array(
            'key' => 'p' . $i, 'term_id' => null, 'name' => $name, 'parent' => (string) ( isset( $f['parent'] ) ? $f['parent'] : '' ),
            'count' => null, 'matches' => '', 'classes' => $classes, 'nowords' => false, 'kinds' => $kinds,
            'audience' => '', 'by' => '', 'asked' => false,
        );
    }
    // Parents were paths; now they are keys. A planned parent that is not in the answer leaves the folder at the top.
    foreach ( $out['folders'] as &$f ) {
        if ( null === $f['term_id'] ) {
            $f['parent'] = '' !== $f['parent'] && isset( $key_of[ $f['parent'] ] ) ? $key_of[ $f['parent'] ] : '';
        }
    }
    unset( $f );

    return vergeml_guide_clean_draft( $out );
}

add_action( 'rest_api_init', function () {
    $may = function () {
        return current_user_can( 'manage_categories' );
    };
    register_rest_route( VERGEML_REST_NS, '/guide/plan', array(
        array( 'methods' => WP_REST_Server::READABLE, 'callback' => 'vergeml_plan_rest_poll', 'permission_callback' => $may ),
        array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => 'vergeml_plan_rest_start', 'permission_callback' => $may ),
    ) );
} );
