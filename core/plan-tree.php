<?php
/**
 *  The bottom-up planner (spec-tree-planner, story 3): every described
 *  picture's label, counted here into an inventory of distinct labels, sent
 *  to the service's /plan-tree, which arranges them five times and keeps the
 *  answer the others agree with most. What comes back becomes the Folders
 *  screen's draft, with every existing folder kept where it is.
 *
 *  The price is counted on this site from the inventory, with no service
 *  call, and is the same sum the service charges (lib/plan-tree.ts planPrice).
 *
 *  The service asks five runs at once and can take longer than a proxy holds a
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
        $filing  = json_decode( (string) $r['filing'], true );
        $classes = vergeml_filing_classes_of_object( is_array( $filing ) && isset( $filing['object'] ) ? $filing['object'] : '' );
        if ( ! $classes ) {
            ++$unlabelled;
            continue;
        }
        $kind  = sanitize_key( (string) $r['kind'] );
        $kind  = '' === $kind ? 'photo' : $kind;
        $label = $classes[0] . ( isset( $classes[1] ) ? '; ' . $classes[1] : '' ) . ( 'photo' === $kind ? '' : ' [' . $kind . ']' );
        if ( ! isset( $by[ $label ] ) ) {
            $by[ $label ] = array( 'label' => $label, 'class' => $classes[0], 'kind' => $kind, 'count' => 0, 'audience' => array() );
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
        $s['draft'] = vergeml_plan_draft( $result['folders'], $inv['labels'] );
        $taxonomy   = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : '';
        if ( '' !== $taxonomy ) {
            vergeml_guide_fit_take( $s, $taxonomy );
        }
        $s['plan'] = array(
            'state'   => 'done',
            'charged' => isset( $result['charged'] ) ? (int) $result['charged'] : 0,
            'runs'    => isset( $result['runs'] ) ? $result['runs'] : null,
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
