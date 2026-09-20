<?php
/**
 *  Get help: what the Send button posts, and what it never posts.
 *
 *      node tools/verify.mjs get-help          # Playground, WordPress booted, the checkout mounted
 *      wp eval-file tests/security/get-help.php --allow-root
 *
 *  Story 3.2 (FR10): the licence key leaves the support ticket. The handler
 *  is run twice against a stand-in service -- once as a free install, once
 *  with a key of the real shape sealed into the settings -- and the body of
 *  the one request it makes is read back. A licensed install sends the key's
 *  last four characters as `licence` and the site; a free install sends
 *  neither; the key itself appears nowhere.
 *
 *  ## No request leaves this suite
 *
 *  pre_http_request answers every call: the known-issues feed with an empty
 *  list, /support/ticket with ok. Nothing reaches GitHub or the service.
 *
 *  ## What it touches, and puts back
 *
 *  vergeml_ai and vergeml_support_token are read before and written back at
 *  the end, from a shutdown function; the vergeml_known_issues transient too.
 *  The canary key is never printed: a failing row names a count.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

foreach ( array( 'vergeml_help_send', 'vergeml_ai_seal', 'vergeml_ai_unseal', 'vergeml_ai_settings' ) as $gh_fn ) {
    if ( ! function_exists( $gh_fn ) ) {
        echo "the plugin is not loaded, or is in safe mode: $gh_fn is missing\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit( 1 );
    }
}

/*
 *  $GLOBALS, not `global`: wp eval-file evaluates this file inside a function,
 *  and so does the Playground runner (a require inside a runPHP step).
 */
$GLOBALS['gh_pass']   = 0;
$GLOBALS['gh_fail']   = 0;
$GLOBALS['gh_calls']  = array();
$GLOBALS['gh_before'] = array();
$GLOBALS['gh_done']   = false;

// The real shape: VGML- and 34 of 0-9A-Z. The last four are the prefix the account page shows.
$GLOBALS['gh_key'] = 'VGML-' . strtoupper( wp_generate_password( 30, false, false ) ) . 'Q7ZK';

function gh_options() {
    return array( 'vergeml_ai', 'vergeml_support_token' );
}

function gh_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['gh_pass']++;
    } else {
        $GLOBALS['gh_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/** How often the key occurs in a string: a count, never the text. */
function gh_hits( $text ) {
    return substr_count( strtoupper( (string) $text ), $GLOBALS['gh_key'] );
}

function gh_catch( $pre, $args, $url ) {

    $GLOBALS['gh_calls'][] = array( 'url' => $url, 'args' => $args );

    $path = (string) wp_parse_url( $url, PHP_URL_PATH );
    $body = array( 'ok' => true );

    if ( '/support/ticket' === substr( $path, -15 ) ) {
        $body = array( 'ok' => true, 'id' => 7 );
    } elseif ( 'known-issues.json' === substr( $path, -17 ) ) {
        $body = array( 'issues' => array() );
    }

    return array(
        'headers'  => array(),
        'body'     => wp_json_encode( $body ),
        'response' => array( 'code' => 200, 'message' => 'OK' ),
        'cookies'  => array(),
        'filename' => null,
    );
}

/** A redirect is the end of the handler; thrown instead of followed, so the suite gets control back. */
function gh_no_redirect( $location ) {
    throw new RuntimeException( 'redirect:' . $location );
}

function gh_restore() {

    if ( $GLOBALS['gh_done'] ) {
        return;
    }
    $GLOBALS['gh_done'] = true;

    remove_filter( 'pre_http_request', 'gh_catch', 1 );
    remove_filter( 'wp_redirect', 'gh_no_redirect', 1 );

    foreach ( gh_options() as $name ) {
        if ( ! array_key_exists( $name, $GLOBALS['gh_before'] ) ) {
            continue;
        }
        if ( false === $GLOBALS['gh_before'][ $name ] ) {
            delete_option( $name );
        } else {
            update_option( $name, $GLOBALS['gh_before'][ $name ], false );
        }
    }

    if ( array_key_exists( 'transient', $GLOBALS['gh_before'] ) ) {
        if ( false === $GLOBALS['gh_before']['transient'] ) {
            delete_transient( 'vergeml_known_issues' );
        } else {
            set_transient( 'vergeml_known_issues', $GLOBALS['gh_before']['transient'], 12 * HOUR_IN_SECONDS );
        }
    }

    unset( $_POST['vergeml_question'], $_POST['vergeml_consent'], $_POST['vergeml_email'], $_REQUEST['_wpnonce'] );
}

/**
 *  One press of Send. Returns the redirect the handler ended on and the
 *  decoded body of the ticket request it made (null when it made none).
 */
function gh_send( $question ) {

    $before = count( $GLOBALS['gh_calls'] );

    $_POST['vergeml_question'] = $question;
    $_POST['vergeml_consent']  = '1';
    $_POST['vergeml_email']    = 'help@example.test';
    $_REQUEST['_wpnonce']      = wp_create_nonce( 'vergeml_help_send' );

    add_filter( 'wp_redirect', 'gh_no_redirect', 1 );

    $where = '';
    try {
        vergeml_help_send();
    } catch ( RuntimeException $e ) {
        $where = $e->getMessage();
    }

    remove_filter( 'wp_redirect', 'gh_no_redirect', 1 );
    unset( $_POST['vergeml_question'], $_POST['vergeml_consent'], $_POST['vergeml_email'], $_REQUEST['_wpnonce'] );

    $tickets = array();
    foreach ( array_slice( $GLOBALS['gh_calls'], $before ) as $c ) {
        if ( '/support/ticket' === substr( (string) wp_parse_url( $c['url'], PHP_URL_PATH ), -15 ) ) {
            $tickets[] = $c;
        }
    }

    return array(
        'where'   => $where,
        'tickets' => count( $tickets ),
        'raw'     => $tickets ? (string) $tickets[0]['args']['body'] : '',
        'body'    => $tickets ? json_decode( (string) $tickets[0]['args']['body'], true ) : null,
    );
}


/* ------------------------------------------------------------- the stage */

$gh_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID', 'orderby' => 'ID', 'order' => 'ASC' ) );
if ( empty( $gh_admins ) ) {
    echo "no administrator on this site to press Send as\n";
    exit( 1 );
}
wp_set_current_user( (int) $gh_admins[0] );

foreach ( gh_options() as $gh_name ) {
    $GLOBALS['gh_before'][ $gh_name ] = get_option( $gh_name, false );
}
$GLOBALS['gh_before']['transient'] = get_transient( 'vergeml_known_issues' );

register_shutdown_function( 'gh_restore' );
add_filter( 'pre_http_request', 'gh_catch', 1, 3 );

echo "\nget-help: what Send posts\n\n";


/* ---------------------------------------------------- A. a free install */

echo "A  a free install: no key set\n";

$gh_ai = get_option( 'vergeml_ai', array() );
$gh_ai = is_array( $gh_ai ) ? $gh_ai : array();
unset( $gh_ai['license_key'] );
update_option( 'vergeml_ai', $gh_ai, false );

gh_check( 'the settings read as no key', '' === vergeml_ai_unseal( vergeml_ai_settings()['license_key'] ) );

$gh_free = gh_send( 'zz vgml get-help suite: free install, not a real ticket' );

gh_check( 'the handler ran to its redirect and the stand-in took the ticket', false !== strpos( $gh_free['where'], 'vgml-help=sent' ), $gh_free['where'] );
gh_check( 'one ticket request was made', 1 === $gh_free['tickets'] && is_array( $gh_free['body'] ), (string) $gh_free['tickets'] );
gh_check( 'it carries site, site_token, question and email', is_array( $gh_free['body'] ) && isset( $gh_free['body']['site'], $gh_free['body']['site_token'], $gh_free['body']['question'], $gh_free['body']['email'] ) );
gh_check( 'the site is home_url()', is_array( $gh_free['body'] ) && home_url( '/' ) === $gh_free['body']['site'] );
gh_check( 'no key field', is_array( $gh_free['body'] ) && ! array_key_exists( 'key', $gh_free['body'] ) );
gh_check( 'no license_key field', is_array( $gh_free['body'] ) && ! array_key_exists( 'license_key', $gh_free['body'] ) );
gh_check( 'no licence field either: a free install identifies itself by its site token alone', is_array( $gh_free['body'] ) && ! array_key_exists( 'licence', $gh_free['body'] ) );


/* ------------------------------------------------ B. a licensed install */

echo "\nB  a licensed install: a key of the real shape, sealed\n";

$gh_ai['license_key'] = vergeml_ai_seal( $GLOBALS['gh_key'] );
update_option( 'vergeml_ai', $gh_ai, false );

gh_check( 'the settings unseal to the planted key', vergeml_ai_unseal( vergeml_ai_settings()['license_key'] ) === $GLOBALS['gh_key'] );

$gh_paid = gh_send( 'zz vgml get-help suite: licensed install, not a real ticket' );

gh_check( 'the handler ran to its redirect and the stand-in took the ticket', false !== strpos( $gh_paid['where'], 'vgml-help=sent' ), $gh_paid['where'] );
gh_check( 'one ticket request was made', 1 === $gh_paid['tickets'] && is_array( $gh_paid['body'] ), (string) $gh_paid['tickets'] );
gh_check( 'no key field', is_array( $gh_paid['body'] ) && ! array_key_exists( 'key', $gh_paid['body'] ) );
gh_check( 'no license_key field', is_array( $gh_paid['body'] ) && ! array_key_exists( 'license_key', $gh_paid['body'] ) );
gh_check( 'a licence field, 4 characters', is_array( $gh_paid['body'] ) && isset( $gh_paid['body']['licence'] ) && is_string( $gh_paid['body']['licence'] ) && 4 === strlen( $gh_paid['body']['licence'] ), is_array( $gh_paid['body'] ) && isset( $gh_paid['body']['licence'] ) ? (string) strlen( (string) $gh_paid['body']['licence'] ) . ' chars' : 'absent' );
gh_check( 'it is the last four characters of the key, the shape the account page shows', is_array( $gh_paid['body'] ) && isset( $gh_paid['body']['licence'] ) && substr( $GLOBALS['gh_key'], -4 ) === $gh_paid['body']['licence'] );
gh_check( 'the key occurs nowhere in the raw body', 0 === gh_hits( $gh_paid['raw'] ), gh_hits( $gh_paid['raw'] ) . ' occurrence(s)' );
gh_check( 'the site is sent beside it', is_array( $gh_paid['body'] ) && home_url( '/' ) === $gh_paid['body']['site'] );

// What else the ticket carries is not this story's to change: the same keys as the free body, plus licence.
$gh_free_keys = is_array( $gh_free['body'] ) ? array_keys( $gh_free['body'] ) : array();
$gh_paid_keys = is_array( $gh_paid['body'] ) ? array_diff( array_keys( $gh_paid['body'] ), array( 'licence' ) ) : array();
sort( $gh_free_keys );
sort( $gh_paid_keys );
gh_check( 'apart from licence, the licensed body has the same fields as the free one', $gh_free_keys === $gh_paid_keys, implode( ',', $gh_free_keys ) . ' vs ' . implode( ',', $gh_paid_keys ) );


/* ------------------------------------------- C. every request that left */

echo "\nC  every request the run tried to send\n";

$gh_bad = array();
foreach ( $GLOBALS['gh_calls'] as $gh_c ) {
    $gh_raw = isset( $gh_c['args']['body'] ) ? ( is_string( $gh_c['args']['body'] ) ? $gh_c['args']['body'] : wp_json_encode( $gh_c['args']['body'] ) ) : '';
    if ( gh_hits( $gh_c['url'] ) || gh_hits( wp_json_encode( isset( $gh_c['args']['headers'] ) ? $gh_c['args']['headers'] : array() ) ) || gh_hits( $gh_raw ) ) {
        $gh_bad[] = (string) wp_parse_url( $gh_c['url'], PHP_URL_PATH );
    }
}
gh_check( count( $GLOBALS['gh_calls'] ) . ' requests caught; the key is in none of them, in no URL and no header', empty( $gh_bad ), implode( ', ', $gh_bad ) );


/* ------------------------------------------------------------ D. put back */

echo "\nD  put back\n";

gh_restore();

foreach ( gh_options() as $gh_name ) {
    gh_check( $gh_name . ' is as it was', get_option( $gh_name, false ) === $GLOBALS['gh_before'][ $gh_name ] );
}
gh_check( 'the planted key is not the licence any more', vergeml_ai_unseal( vergeml_ai_settings()['license_key'] ) !== $GLOBALS['gh_key'] );

printf( "\n%d/%d passed\n\n", $GLOBALS['gh_pass'], $GLOBALS['gh_pass'] + $GLOBALS['gh_fail'] );

exit( $GLOBALS['gh_fail'] > 0 ? 1 : 0 );
