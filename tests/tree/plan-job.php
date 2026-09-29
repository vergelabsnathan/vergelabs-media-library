<?php
/*
 *  The plan's job, its press and its poll (core/plan-tree.php), driven end to
 *  end against stand-ins: the session, the lock and cron are arrays here, the
 *  service is a scripted answer per call, and a host that kills the job
 *  mid-ask is an exception thrown from inside that answer.
 *
 *  Local: no WordPress, no database, no box, no service call.
 *
 *      node tools/verify.mjs plan-job
 *
 *  The mutations it catches: the plan's id kept only in the job, or
 *  'started' never written -> row 1; the poll booking a started job again ->
 *  rows 2, 2b; the refund asked under another id, or not at all -> row 2c;
 *  the message claiming nothing was charged when the refund could not be
 *  asked -> row 3; a started job run a second time by cron -> row 4; a job
 *  cron never started no longer booked again -> row 5; a growth killed
 *  mid-run asking a refund -> row 6; the job saving its whole early read of
 *  the session over a confirm or a turn made meanwhile -> rows 7, 8, 9; a
 *  job overwriting a plan the poll already failed -> row 10.
 */

define( 'ABSPATH', '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'VERGEML_REST_NS', 'vergeml/v1' );

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct( $code = '', $message = '', $data = null ) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }
    public function get_error_code() {
        return $this->code;
    }
    public function get_error_message() {
        return $this->message;
    }
    public function get_error_data() {
        return $this->data;
    }
}
class WP_REST_Request {
    private $p;
    public function __construct( $p = array() ) {
        $this->p = $p;
    }
    public function get_param( $k ) {
        return isset( $this->p[ $k ] ) ? $this->p[ $k ] : null;
    }
}
class PJ_Killed extends Exception {}

function add_action() {}
function __( $t ) {
    return $t;
}
function _n( $one, $many, $n ) {
    return 1 === (int) $n ? $one : $many;
}
function number_format_i18n( $n ) {
    return (string) $n;
}
function is_wp_error( $x ) {
    return $x instanceof WP_Error;
}
function rest_ensure_response( $x ) {
    return $x;
}
function wp_json_encode( $v ) {
    return json_encode( $v );
}
function sanitize_key( $key ) {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_text_field( $t ) {
    return trim( strip_tags( (string) $t ) );
}
function home_url() {
    return 'https://shop.example';
}
function wp_generate_uuid4() {
    $GLOBALS['pj_uuid'] = isset( $GLOBALS['pj_uuid'] ) ? $GLOBALS['pj_uuid'] + 1 : 1;
    return sprintf( '00000000-0000-4000-8000-%012d', $GLOBALS['pj_uuid'] );
}
function get_option( $name, $default = false ) {
    return array_key_exists( $name, $GLOBALS['pj_options'] ) ? $GLOBALS['pj_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
    $GLOBALS['pj_options'][ $name ] = $value;
    return true;
}
function delete_option( $name ) {
    unset( $GLOBALS['pj_options'][ $name ] );
}
function get_transient( $name ) {
    return isset( $GLOBALS['pj_transients'][ $name ] ) ? $GLOBALS['pj_transients'][ $name ] : false;
}
function set_transient( $name, $value, $ttl = 0 ) {
    $GLOBALS['pj_transients'][ $name ] = $value;
    return true;
}
function delete_transient( $name ) {
    unset( $GLOBALS['pj_transients'][ $name ] );
}
function wp_next_scheduled( $hook ) {
    return ! empty( $GLOBALS['pj_scheduled'][ $hook ] );
}
function wp_schedule_single_event( $ts, $hook ) {
    $GLOBALS['pj_scheduled'][ $hook ] = true;
    $GLOBALS['pj_booked']++;
    return true;
}
function spawn_cron() {}
function get_terms( $args ) {
    $terms = $GLOBALS['pj_terms'];
    if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
        $ids = array_map( function ( $t ) { return (int) $t->term_id; }, $terms );
        return isset( $args['include'] ) ? array_values( array_intersect( $ids, (array) $args['include'] ) ) : $ids;
    }
    return $terms;
}
function get_term_meta( $id, $key, $single ) {
    return '';
}
function vergeml_term_name( $t ) {
    return html_entity_decode( (string) $t->name, ENT_QUOTES, 'UTF-8' );
}
function vergeml_librarian_taxonomy() {
    return 'media_category';
}
function vergeml_filing_classes_of_object( $object ) {
    return array_values( array_filter( array_map( 'trim', preg_split( '/\s*[;,]\s*/u', mb_strtolower( trim( (string) $object ) ) ) ), 'strlen' ) );
}
function vergeml_filing_audience_of_picture( $a ) {
    return '';
}
function vergeml_index_vector_out( $e ) {
    return json_decode( (string) $e, true );
}
function vergeml_ai_settings() {
    return array( 'license_key' => 'VGML-KEY' );
}
function vergeml_ai_unseal( $k ) {
    return $k;
}
function vergeml_ai_service_url() {
    return 'https://svc.example/v1';
}

/* The session: guide.php's own reads and writes, over the options above. */
function vergeml_guide_session() {
    $s = get_option( 'pj_session' );
    return array_merge( array( 'turns' => array(), 'draft' => null, 'fit' => null, 'tree' => 'editing', 'plan' => null, 'audience_split' => null ), is_array( $s ) ? $s : array() );
}
function vergeml_guide_save( $s ) {
    update_option( 'pj_session', $s );
    return $s;
}
function vergeml_guide_session_out( $s ) {
    return $s;
}
function vergeml_guide_confirmed_refusal() {
    return new WP_Error( 'confirmed', 'confirmed', array( 'status' => 409 ) );
}
function vergeml_guide_clean_draft( $d ) {
    return $d;
}
// Where the plan's long passes run; a test hooks what the owner does meanwhile in here.
function vergeml_guide_fit_take( &$s, $taxonomy ) {
    $s['fit'] = array( 'counted' => true );
    if ( isset( $GLOBALS['pj_meanwhile'] ) ) {
        call_user_func( $GLOBALS['pj_meanwhile'] );
    }
}
// Every described picture with its folder, as core/guide.php hands them over; empty unless a test files them.
function vergeml_guide_rule_rows( $taxonomy, $scope, $need, $after = 0, $limit = 0 ) {
    if ( empty( $GLOBALS['pj_filed'] ) || $after > 0 ) {
        return array();
    }
    return $GLOBALS['pj_rows'];
}

/* The service: every outbound call recorded, each answered by the test's script for its path. */
function wp_remote_post( $url, $args ) {
    $body                  = json_decode( $args['body'], true );
    $path                  = substr( $url, strlen( vergeml_ai_service_url() ) );
    $GLOBALS['pj_calls'][] = array( 'path' => $path, 'body' => $body );
    $answer                = '/plan-tree' === $path ? $GLOBALS['pj_ask'] : $GLOBALS['pj_refund'];
    return is_callable( $answer ) ? call_user_func( $answer, $body ) : $answer;
}
function wp_remote_retrieve_response_code( $r ) {
    return is_array( $r ) ? $r['response']['code'] : '';
}
function wp_remote_retrieve_body( $r ) {
    return is_array( $r ) ? $r['body'] : '';
}
function pj_answer( $code, $data ) {
    return array( 'response' => array( 'code' => $code ), 'body' => json_encode( $data ) );
}

class PJ_Wpdb {
    public $vergeml_ai_index = 'wp_vergeml_ai_index';
    public $postmeta         = 'wp_postmeta';
    public function prepare( $q ) {
        $args = array_slice( func_get_args(), 1 );
        return vsprintf( str_replace( array( '%d', '%s' ), array( '%d', "'%s'" ), $q ), $args );
    }
    public function get_row( $q ) {
        return array( 'n' => count( $GLOBALS['pj_rows'] ), 'at' => $GLOBALS['pj_stamp'] );
    }
    public function get_results( $q ) {
        return false !== strpos( $q, 'attachment_id > 0 ' ) ? $GLOBALS['pj_rows'] : array();
    }
    public function get_var( $q ) {
        return 0;
    }
    public function get_col( $q ) {
        return array();
    }
}
$GLOBALS['wpdb'] = new PJ_Wpdb();

require dirname( __DIR__, 2 ) . '/core/plan-tree.php';

$GLOBALS['pj_pass'] = 0;
$GLOBALS['pj_fail'] = 0;

function pj_check( $label, $ok, $note = '' ) {
    $GLOBALS[ $ok ? 'pj_pass' : 'pj_fail' ]++;
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note );
}

/* A library of twelve described pictures, two labels of six; Footwear (5) and Kitchen (6) exist. */
function pj_row( $id, $object, $term, $v ) {
    return array( 'attachment_id' => $id, 'kind' => 'photo', 'filing' => json_encode( array( 'object' => $object ) ), 'in_terms' => (string) $term, 'embedding' => json_encode( $v ), 'embedding_dims' => 3 );
}
function pj_reset( $rows = 12 ) {
    $GLOBALS['pj_options']    = array();
    $GLOBALS['pj_transients'] = array();
    $GLOBALS['pj_scheduled']  = array();
    $GLOBALS['pj_booked']     = 0;
    $GLOBALS['pj_calls']      = array();
    $GLOBALS['pj_filed']      = false;
    $GLOBALS['pj_stamp']      = 'stamp-' . microtime( true );
    unset( $GLOBALS['pj_meanwhile'] );
    $GLOBALS['pj_terms'] = array(
        (object) array( 'term_id' => 5, 'name' => 'Footwear', 'parent' => 0, 'slug' => 'footwear' ),
        (object) array( 'term_id' => 6, 'name' => 'Kitchen', 'parent' => 0, 'slug' => 'kitchen' ),
    );
    $GLOBALS['pj_rows'] = array();
    for ( $i = 1; $i <= $rows; $i++ ) {
        $boot                 = $i <= (int) ceil( $rows / 2 );
        $GLOBALS['pj_rows'][] = pj_row( $i, $boot ? 'ankle boot; footwear' : 'kettle; appliance', $boot ? 5 : 6, $boot ? array( 1, 0.1 * $i, 0 ) : array( 0, 1, 0.1 * $i ) );
    }
    $GLOBALS['pj_ask']    = pj_answer( 200, pj_plan_answer() );
    $GLOBALS['pj_refund'] = pj_answer( 200, array( 'refunded' => 10, 'credits_remaining' => 100, 'again' => false ) );
}
function pj_plan_answer( $id = '' ) {
    $folders = array(
        array( 'path' => 'Footwear', 'name' => 'Footwear', 'parent' => '', 'labels' => array( 'l0' ), 'count' => 6 ),
        array( 'path' => 'Kitchen', 'name' => 'Kitchen', 'parent' => '', 'labels' => array( 'l1' ), 'count' => 6 ),
    );
    return array( 'folders' => $folders, 'unfiled' => array(), 'trees' => array( array( 'folders' => $folders, 'unfiled' => array() ) ), 'runs' => array( 'asked' => 15, 'valid' => 15 ), 'charged' => 10, 'plan' => $id, 'credits_remaining' => 90 );
}
function pj_session() {
    return vergeml_guide_session();
}
function pj_paths() {
    return implode( ', ', array_map( function ( $c ) { return $c['path']; }, $GLOBALS['pj_calls'] ) );
}
// The press, with the price the button showed.
function pj_press( $price = null ) {
    $facts = vergeml_plan_facts();
    return vergeml_plan_rest_start( new WP_REST_Request( array( 'price' => null === $price ? $facts['price'] : $price ) ) );
}
// The job, killed by its host inside the ask: the service has the request, the job never gets the answer.
function pj_run_killed() {
    $GLOBALS['pj_ask'] = function () {
        throw new PJ_Killed();
    };
    try {
        vergeml_plan_event();
    } catch ( PJ_Killed $e ) {
        return true;
    }
    return false;
}

/* ------------------------------------------------ H1  a job its host kills */

echo "\nH1  a job its host stops mid-ask is failed and asked back, never booked again\n\n";

pj_reset();
pj_press();
$killed  = pj_run_killed();
$s       = pj_session();
$sent_id = isset( $GLOBALS['pj_calls'][0]['body']['plan'] ) ? $GLOBALS['pj_calls'][0]['body']['plan'] : '';
pj_check( '1. the plan\'s id and a started mark are in the session before the ask, and the ask carries that id', $killed && ! empty( $s['plan']['started'] ) && '' !== $sent_id && $sent_id === $s['plan']['id'], json_encode( $s['plan'] ) );

// The lock outlives the job by its own timeout; once it has gone, the poll judges.
delete_transient( VERGEML_PLAN_LOCK );
$GLOBALS['pj_scheduled'] = array();
$booked_before           = $GLOBALS['pj_booked'];
$GLOBALS['pj_calls']     = array();
$out                     = vergeml_plan_rest_poll();
$s                       = pj_session();
pj_check( '2. the poll does not book a started job again', $booked_before === $GLOBALS['pj_booked'] && empty( $GLOBALS['pj_scheduled'][ VERGEML_PLAN_HOOK ] ), $GLOBALS['pj_booked'] . ' booked' );
pj_check( '2b. it fails the plan, and no second plan is asked for', 'failed' === $s['plan']['state'] && 'failed' === $out['plan']['state'] && false === strpos( pj_paths(), '/plan-tree,' ) && '/plan-tree' !== pj_paths(), pj_paths() );
pj_check( '2c. it asks the plan back by the id the lost ask carried, and says nothing was charged', '/plan-tree/refund' === pj_paths() && $sent_id === $GLOBALS['pj_calls'][0]['body']['plan'] && 'The plan did not come together. Nothing was charged.' === $s['plan']['message'], $s['plan']['message'] );
pj_check( '2d. the balance the refund named is kept for the button', isset( $GLOBALS['pj_options']['vergeml_ai_credits']['remaining'] ) && 100 === $GLOBALS['pj_options']['vergeml_ai_credits']['remaining'] );

pj_reset();
pj_press();
pj_run_killed();
delete_transient( VERGEML_PLAN_LOCK );
$GLOBALS['pj_refund'] = new WP_Error( 'http_request_failed', 'down' );
vergeml_plan_rest_poll();
$s = pj_session();
pj_check( '3. a refund that cannot be asked leaves the honest message: it may have been charged', 'failed' === $s['plan']['state'] && 'The plan did not come back, and it may have been charged. Your balance is on the Licence screen.' === $s['plan']['message'], $s['plan']['message'] );

pj_reset();
pj_press();
pj_run_killed();
delete_transient( VERGEML_PLAN_LOCK );
$GLOBALS['pj_calls'] = array();
vergeml_plan_event();
pj_check( '4. cron running a started job again asks nothing', array() === $GLOBALS['pj_calls'] && 'running' === pj_session()['plan']['state'], pj_paths() );

pj_reset();
pj_press();
$GLOBALS['pj_scheduled'] = array();
$booked_before           = $GLOBALS['pj_booked'];
vergeml_plan_rest_poll();
pj_check( '5. a job cron never started is booked again', $booked_before + 1 === $GLOBALS['pj_booked'] && 'running' === pj_session()['plan']['state'] );

pj_reset();
$s         = pj_session();
$s['plan'] = array( 'state' => 'running', 'at' => time(), 'price' => 0, 'grow' => true, 'id' => wp_generate_uuid4(), 'started' => time() );
vergeml_guide_save( $s );
vergeml_plan_rest_poll();
$s = pj_session();
pj_check( '6. a growth stopped mid-run is failed and asks nothing back: it cost nothing', 'failed' === $s['plan']['state'] && array() === $GLOBALS['pj_calls'] && 'The plan did not finish. Try again.' === $s['plan']['message'], $s['plan']['message'] );

/* ------------------------------------- M3  what the owner does meanwhile */

echo "\nM3  a confirm or an edit made while the plan ran is the owner's, not overwritten\n\n";

function pj_confirm_now() {
    $s         = vergeml_guide_session();
    $s['tree'] = 'confirmed';
    vergeml_guide_save( $s );
}
function pj_refunds() {
    return array_values( array_filter( $GLOBALS['pj_calls'], function ( $c ) { return '/plan-tree/refund' === $c['path']; } ) );
}

pj_reset();
pj_press();
$GLOBALS['pj_ask']       = function ( $body ) { return pj_answer( 200, pj_plan_answer( $body['plan'] ) ); };
$GLOBALS['pj_meanwhile'] = 'pj_confirm_now';
vergeml_plan_event();
$s = pj_session();
$r = pj_refunds();
pj_check( '7. confirmed during the passes after the ask: the confirm stands, the plan is dropped, its credits asked back once', 'confirmed' === $s['tree'] && null === $s['plan'] && null === $s['draft'] && 1 === count( $r ) && $GLOBALS['pj_calls'][0]['body']['plan'] === $r[0]['body']['plan'], json_encode( array( 'tree' => $s['tree'], 'plan' => $s['plan'], 'refunds' => count( $r ) ) ) );

pj_reset();
pj_press();
$GLOBALS['pj_filed']  = true;
$GLOBALS['pj_ask']    = function ( $body ) { return pj_answer( 200, pj_plan_answer( $body['plan'] ) ); };
$GLOBALS['pj_refund'] = function () {
    pj_confirm_now();
    return pj_answer( 200, array( 'refunded' => 10, 'credits_remaining' => 100, 'again' => false ) );
};
vergeml_plan_event();
$s = pj_session();
pj_check( '8. confirmed while a plan the folders beat was being given back: the confirm stands, and it is not asked back twice', 'confirmed' === $s['tree'] && null === $s['plan'] && 1 === count( pj_refunds() ), json_encode( array( 'tree' => $s['tree'], 'plan' => $s['plan'], 'refunds' => count( pj_refunds() ) ) ) );

pj_reset();
pj_press();
$GLOBALS['pj_filed'] = true;
$GLOBALS['pj_ask']   = function ( $body ) { return pj_answer( 200, pj_plan_answer( $body['plan'] ) ); };
vergeml_plan_event();
$s = pj_session();
pj_check( '8b. (the same library left alone: the folders beat the plan, and it is given back)', 'kept' === $s['plan']['state'] && 10 === $s['plan']['refunded'] && 'editing' === $s['tree'], json_encode( $s['plan'] ) );

pj_reset();
pj_press();
$GLOBALS['pj_ask']       = function ( $body ) { return pj_answer( 200, pj_plan_answer( $body['plan'] ) ); };
$GLOBALS['pj_meanwhile'] = function () {
    $s                   = vergeml_guide_session();
    $s['turns'][]        = array( 'role' => 'user', 'text' => 'said meanwhile' );
    $s['audience_split'] = 'no';
    vergeml_guide_save( $s );
};
vergeml_plan_event();
$s = pj_session();
pj_check( '9. a turn and an answer given while the plan ran survive the plan landing', 'done' === $s['plan']['state'] && ! empty( $s['draft']['folders'] ) && 1 === count( $s['turns'] ) && 'no' === $s['audience_split'] && array() === pj_refunds(), json_encode( array( 'state' => $s['plan']['state'], 'turns' => count( $s['turns'] ), 'split' => $s['audience_split'] ) ) );

pj_reset();
pj_press();
$GLOBALS['pj_ask'] = function ( $body ) {
    // The poll failed this plan and asked it back while the job was still reading the library.
    vergeml_plan_put( array( 'state' => 'failed', 'message' => 'lost' ) );
    return pj_answer( 200, pj_plan_answer( $body['plan'] ) );
};
vergeml_plan_event();
$s = pj_session();
pj_check( '10. a plan the poll already failed is not brought back to life by its job', 'failed' === $s['plan']['state'] && null === $s['draft'], json_encode( $s['plan'] ) );

$pj_total = $GLOBALS['pj_pass'] + $GLOBALS['pj_fail'];
printf( "\n%d/%d passed\n", $GLOBALS['pj_pass'], $pj_total );
exit( $GLOBALS['pj_fail'] ? 1 : 0 );
