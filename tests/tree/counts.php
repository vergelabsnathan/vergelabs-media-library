<?php
/**
 *  The counts a site shares, and what it never shares.
 *
 *  Three claims. Off, nothing leaves the site. On, exactly the snapshot
 *  leaves it -- nine keys, integers and versions, the locale the only other
 *  string -- once a day, with the licence key and the site, to /v1/counts.
 *  And the switch lives in Library settings, not on the dashboard.
 *
 *      wp eval-file tests/tree/counts.php --allow-root
 *
 *  No request leaves this suite: pre_http_request answers every one and
 *  records it. The option is put back as it was found.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_stats_snapshot' ) || ! function_exists( 'vergeml_stats_refresh' ) ) {
    echo "core/instrument.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

$GLOBALS['ct_pass'] = 0;
$GLOBALS['ct_fail'] = 0;
$GLOBALS['ct_log']  = '';
$GLOBALS['ct_sent'] = array();

function ct_say( $line ) {
    $GLOBALS['ct_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function ct_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['ct_pass']++;
    } else {
        $GLOBALS['ct_fail']++;
    }
    ct_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

/** Every outgoing request is answered here and remembered. */
function ct_catch( $pre, $args, $url ) {
    $GLOBALS['ct_sent'][] = array( 'url' => $url, 'args' => $args );
    return array(
        'headers'  => array(),
        'body'     => '{"ok":true}',
        'response' => array( 'code' => 200, 'message' => 'OK' ),
        'cookies'  => array(),
        'filename' => null,
    );
}

/** The option as it was, whatever this suite did to it. */
function ct_restore() {
    if ( empty( $GLOBALS['ct_restored'] ) ) {
        $GLOBALS['ct_restored'] = true;
        if ( null === $GLOBALS['ct_before'] ) {
            delete_option( VERGEML_STATS_OPTION );
        } else {
            update_option( VERGEML_STATS_OPTION, $GLOBALS['ct_before'], false );
        }
        remove_filter( 'pre_http_request', 'ct_catch', 1 );
    }
}

/** A screen rendered as an administrator, the user put back after. */
function ct_render( $fn ) {
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    $was    = get_current_user_id();
    if ( ! empty( $admins ) ) {
        wp_set_current_user( (int) $admins[0] );
    }
    ob_start();
    call_user_func( $fn );
    $html = ob_get_clean();
    wp_set_current_user( $was );
    return $html;
}

$GLOBALS['ct_before']   = get_option( VERGEML_STATS_OPTION, null );
$GLOBALS['ct_restored'] = false;
register_shutdown_function( 'ct_restore' );
add_filter( 'pre_http_request', 'ct_catch', 1, 3 );


ct_say( "\nthe counts a site shares\n\n" );


/* ------------------------------------------------------------ the snapshot */

ct_say( "A  the snapshot: nine keys, numbers and versions\n" );

$ct_snap = vergeml_stats_snapshot();
$ct_keys = array( 'attachments', 'mimes', 'folders', 'depth', 'recent', 'plugin', 'wp', 'php', 'locale' );
$ct_versions = array( 'plugin', 'wp', 'php', 'locale' );

ct_check( 'exactly the nine keys, in order', $ct_keys === array_keys( $ct_snap ), implode( ',', array_keys( $ct_snap ) ) );

$ct_ints = true;
foreach ( array( 'attachments', 'folders', 'depth', 'recent' ) as $ct_k ) {
    if ( ! isset( $ct_snap[ $ct_k ] ) || ! is_int( $ct_snap[ $ct_k ] ) || $ct_snap[ $ct_k ] < 0 ) {
        $ct_ints = false;
    }
}
ct_check( 'the four counts are whole numbers', $ct_ints );

$ct_families_ok = is_array( $ct_snap['mimes'] );
foreach ( (array) $ct_snap['mimes'] as $ct_family => $ct_n ) {
    if ( ! preg_match( '/^[a-z0-9_-]{1,24}$/', (string) $ct_family ) || ! is_int( $ct_n ) ) {
        $ct_families_ok = false;
    }
}
ct_check( 'mime families are keys a type has, each with a count', $ct_families_ok, wp_json_encode( $ct_snap['mimes'] ) );

ct_check(
    'the versions are this site\'s software, not a row from the database',
    VERGEML_VERSION === $ct_snap['plugin'] && get_bloginfo( 'version' ) === $ct_snap['wp'] && PHP_VERSION === $ct_snap['php'],
    $ct_snap['plugin'] . ' / ' . $ct_snap['wp'] . ' / ' . $ct_snap['php']
);
ct_check( 'the locale is the one other string', get_locale() === $ct_snap['locale'], $ct_snap['locale'] );

$ct_stray = array();
foreach ( $ct_snap as $ct_k => $ct_v ) {
    if ( is_string( $ct_v ) && ! in_array( $ct_k, $ct_versions, true ) ) {
        $ct_stray[] = $ct_k;
    }
    if ( is_array( $ct_v ) ) {
        foreach ( $ct_v as $ct_inner ) {
            if ( ! is_int( $ct_inner ) ) {
                $ct_stray[] = $ct_k . '[]';
            }
        }
    }
}
ct_check( 'no other string anywhere in it', empty( $ct_stray ), implode( ',', $ct_stray ) );

/*
 *  A folder name never appears. Real names from this library are searched
 *  for in the encoded snapshot; short ones are skipped because "image" or
 *  "video" could be a folder as well as a mime family.
 */
$ct_taxes = vergeml_stats_taxonomies();
$ct_names = array();
if ( ! empty( $ct_taxes ) ) {
    $ct_terms = get_terms( array( 'taxonomy' => $ct_taxes[0], 'hide_empty' => false, 'number' => 40 ) );
    foreach ( (array) $ct_terms as $ct_term ) {
        if ( is_object( $ct_term ) && strlen( $ct_term->name ) >= 6 ) {
            $ct_names[] = $ct_term->name;
        }
    }
}
$ct_encoded = wp_json_encode( $ct_snap );
$ct_leaked  = array();
foreach ( $ct_names as $ct_name ) {
    if ( false !== stripos( $ct_encoded, $ct_name ) ) {
        $ct_leaked[] = $ct_name;
    }
}
ct_check( 'no folder name of this library is in it', empty( $ct_leaked ), count( $ct_names ) . ' names checked' . ( empty( $ct_leaked ) ? '' : '; found ' . implode( ',', $ct_leaked ) ) );


/* ------------------------------------------------------------- opted off */

ct_say( "\nB  off, nothing leaves the site\n" );

delete_option( VERGEML_STATS_OPTION );
ct_check( 'off by default', empty( vergeml_stats_state()['opted'] ) );

$GLOBALS['ct_sent'] = array();
vergeml_stats_refresh( true );
vergeml_stats_maybe_refresh();
ct_check( 'opted off, a forced refresh sends nothing', 0 === count( $GLOBALS['ct_sent'] ), count( $GLOBALS['ct_sent'] ) . ' requests' );
ct_check( 'and takes no snapshot', array() === vergeml_stats_state()['snapshot'] );


/* -------------------------------------------------------------- opted on */

ct_say( "\nC  on, exactly the snapshot, once a day\n" );

update_option( VERGEML_STATS_OPTION, array( 'opted' => 1, 'snapshot' => array(), 'time' => 0, 'sent' => 0 ), false );
$GLOBALS['ct_sent'] = array();

$ct_state = vergeml_stats_refresh();

$ct_key = function_exists( 'vergeml_ai_unseal' ) && function_exists( 'vergeml_ai_settings' )
    ? vergeml_ai_unseal( vergeml_ai_settings()['license_key'] )
    : '';

if ( '' === $ct_key ) {

    ct_check( 'no key: nothing to send with, so nothing is sent', 0 === count( $GLOBALS['ct_sent'] ), count( $GLOBALS['ct_sent'] ) . ' requests' );
    ct_check( 'the snapshot is still taken and kept', ! empty( $ct_state['snapshot'] ) && $ct_state['time'] > 0 );
    ct_say( "  (the rest of C needs a licence key on this site)\n" );

} else {

    ct_check( 'one request', 1 === count( $GLOBALS['ct_sent'] ), count( $GLOBALS['ct_sent'] ) . ' requests' );

    $ct_req  = isset( $GLOBALS['ct_sent'][0] ) ? $GLOBALS['ct_sent'][0] : array( 'url' => '', 'args' => array() );
    $ct_body = isset( $ct_req['args']['body'] ) ? json_decode( (string) $ct_req['args']['body'], true ) : null;
    $ct_body = is_array( $ct_body ) ? $ct_body : array();

    ct_check( 'to /counts on the service, as every /v1 call', vergeml_ai_service_url() . '/counts' === $ct_req['url'], $ct_req['url'] );
    ct_check( 'as JSON', isset( $ct_req['args']['headers']['Content-Type'] ) && 'application/json' === $ct_req['args']['headers']['Content-Type'] );
    ct_check( 'the body is the key, the site and the counts, nothing beside them', array( 'license_key', 'site', 'counts' ) === array_keys( $ct_body ), implode( ',', array_keys( $ct_body ) ) );
    ct_check( 'the key is the licence key, opened', $ct_key === ( isset( $ct_body['license_key'] ) ? $ct_body['license_key'] : null ) );
    ct_check( 'the site is this site', home_url() === ( isset( $ct_body['site'] ) ? $ct_body['site'] : null ) );

    $ct_counts = isset( $ct_body['counts'] ) && is_array( $ct_body['counts'] ) ? $ct_body['counts'] : array();
    ct_check( 'the counts carry exactly the snapshot\'s keys', array_keys( $ct_snap ) === array_keys( $ct_counts ), implode( ',', array_keys( $ct_counts ) ) );
    ct_check( 'and exactly what the builder returned', $ct_state['snapshot'] === $ct_counts );

    $ct_stray = array();
    foreach ( $ct_counts as $ct_k => $ct_v ) {
        if ( is_string( $ct_v ) && ! in_array( $ct_k, $ct_versions, true ) ) {
            $ct_stray[] = $ct_k;
        }
        if ( is_array( $ct_v ) ) {
            foreach ( $ct_v as $ct_inner ) {
                if ( ! is_int( $ct_inner ) ) {
                    $ct_stray[] = $ct_k . '[]';
                }
            }
        }
    }
    ct_check( 'no value in the counts is a string but the versions and the locale', empty( $ct_stray ), implode( ',', $ct_stray ) );
    ct_check( 'nothing a person wrote: no folder name in the body', 0 === count( array_filter( $ct_names, function ( $n ) use ( $ct_req ) {
        return false !== stripos( (string) $ct_req['args']['body'], $n );
    } ) ) );
    ct_check( 'the send is stamped', (int) $ct_state['sent'] > 0 );

    $GLOBALS['ct_sent'] = array();
    vergeml_stats_refresh();
    vergeml_stats_maybe_refresh();
    ct_check( 'not again the same day', 0 === count( $GLOBALS['ct_sent'] ), count( $GLOBALS['ct_sent'] ) . ' requests' );
}

// The switch off forgets what was kept.
update_option( VERGEML_STATS_OPTION, array( 'opted' => 1, 'snapshot' => $ct_snap, 'time' => time(), 'sent' => time() ), false );
if ( function_exists( 'vergeml_stats_rest_opt' ) ) {
    $ct_off = new WP_REST_Request( 'POST', '/vergeml/v1/stats-opt' );
    $ct_off->set_param( 'opted', false );
    $GLOBALS['ct_sent'] = array();
    $ct_answer = vergeml_stats_rest_opt( $ct_off );
    $ct_data   = $ct_answer instanceof WP_REST_Response ? $ct_answer->get_data() : array();
    ct_check( 'switching off forgets the snapshot and sends nothing', empty( $ct_data['opted'] ) && array() === $ct_data['snapshot'] && 0 === count( $GLOBALS['ct_sent'] ) );
}


/* ------------------------------------------------------------- the screens */

ct_say( "\nD  the switch is in Library settings, not on the dashboard\n" );

ct_check( 'the dashboard card is gone', ! function_exists( 'vergeml_stats_card' ) && false === has_action( 'vergeml_admin_home_cards', 'vergeml_stats_card' ) );

if ( function_exists( 'vergeml_journey_screen' ) ) {
    $ct_dash = ct_render( 'vergeml_journey_screen' );
    ct_check( 'the dashboard draws no counts switch', false === strpos( $ct_dash, 'vgml-stats-opt' ) );
    ct_check( 'and none of the card\'s copy', false === strpos( $ct_dash, 'Size counts' ) && false === strpos( $ct_dash, 'Keep these numbers' ) && false === strpos( $ct_dash, 'anonymous counts' ) );
}

if ( ! function_exists( 'vergeml_print_media_library_options' ) ) {
    // The settings screens load only in the admin context, which wp-cli is
    // not. The switch's presence there is proven where a browser is:
    // tests/ui/shots.spec.mjs (library) and tests/tree/health.mjs.
    ct_say( "  (Library settings renders only in the admin context; tests/ui/shots.spec.mjs and tests/tree/health.mjs prove the switch there)\n" );
} else {

    if ( ! function_exists( 'submit_button' ) ) {
        require_once ABSPATH . 'wp-admin/includes/template.php';
    }

    $ct_lib = ct_render( 'vergeml_print_media_library_options' );

    ct_check( 'Library settings has the section', false !== strpos( $ct_lib, 'Share library counts' ) );
    ct_check( 'with the switch', false !== strpos( $ct_lib, 'id="vgml-stats-opt"' ) && false !== strpos( $ct_lib, '>Send the counts<' ) || false !== strpos( $ct_lib, ' Send the counts</label>' ) );
    ct_check( 'off, as found', false === strpos( $ct_lib, "id=\"vgml-stats-opt\" checked" ) );
    ct_check( 'the three lines, as a list with the mark', false !== strpos( $ct_lib, 'class="vgml-facts"' )
        && false !== strpos( $ct_lib, 'Once a day: files, folders, how deep they nest' )
        && false !== strpos( $ct_lib, 'Plugin, WordPress and PHP versions, and the site language' )
        && false !== strpos( $ct_lib, 'Never a file name, a title, a folder name or a picture' ) );
    ct_check( 'and none of the old card\'s copy', false === strpos( $ct_lib, 'Size counts' ) && false === strpos( $ct_lib, 'Keep these numbers' ) );
}


ct_restore();

ct_say( sprintf( "\n%d/%d passed\n", $GLOBALS['ct_pass'], $GLOBALS['ct_pass'] + $GLOBALS['ct_fail'] ) );

@file_put_contents( __DIR__ . '/counts-last-run.txt', $GLOBALS['ct_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['ct_fail'] > 0 ) {
    exit( 1 );
}
