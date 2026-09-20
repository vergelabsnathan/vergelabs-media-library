<?php
/**
 *  Secrets: the licence key at rest, in logs and in responses.
 *
 *      wp eval-file tests/security/secrets.php --allow-root
 *
 *  Phase 5.7 of plans/four-yesses.md. A known key is planted through the
 *  settings route, then everything that could repeat it is made to run and
 *  read back: the database, every GET route as an administrator, the describe
 *  path against a stand-in service, PHP's error log, the support ticket, the
 *  counts snapshot. The key may appear in exactly one place: as the top-level
 *  `license_key` / `key` field of a request body to the service, which is how
 *  every /v1 call but the support ticket authenticates (docs/security-hosts.md
 *  decides where those go; the ticket sends the key's last four characters,
 *  story 3.2). Anywhere else is a leak.
 *
 *  ## The canary
 *
 *  `VGML-CANARY` + 23 random characters -- the shape of a real key
 *  (`VGML-` + 34 of 0-9A-Z), so a grep for the real shape finds it too. It is
 *  never printed: a failing check names the place and a count, never the text.
 *
 *  ## No request leaves this suite
 *
 *  pre_http_request answers every call and records it. A describe pass is
 *  driven with mock off so the real request is built (with the key in it) and
 *  answered here, once with a 500 and once with a description; the licence
 *  check is answered once with a 403 and once with a balance; the token mint
 *  answers with a sentinel token, so the guide token's travels are checked
 *  under a second marker. No credit is spent; nothing reaches the service.
 *
 *  ## What it touches, and puts back
 *
 *  The options vergeml_ai, vergeml_ai_credits, vergeml_guide_session,
 *  vergeml_brief_session, vergeml_stats and vergeml_support_token are read
 *  before and written back verbatim at the end -- and from a shutdown function,
 *  so a fatal halfway cannot leave the canary in a site's settings. One
 *  temporary error log of its own under the system temp dir, removed. Nothing
 *  else is written; no rows are created.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

foreach ( array( 'vergeml_ai_seal', 'vergeml_ai_unseal', 'vergeml_ai_describe', 'vergeml_ai_refresh_credits', 'vergeml_help_send', 'vergeml_system_report_text', 'vergeml_stats_snapshot', 'vergeml_stats_send', 'vergeml_guide_token', 'vergeml_brief_token' ) as $s_fn ) {
    if ( ! function_exists( $s_fn ) ) {
        echo "the plugin is not loaded, or is in safe mode: $s_fn is missing\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit( 1 );
    }
}

/*
 *  $GLOBALS, not `global`: wp eval-file evaluates this file inside a function.
 *  See tests/security/roles.php for the full reason.
 */
$GLOBALS['s_pass']   = 0;
$GLOBALS['s_fail']   = 0;
$GLOBALS['s_calls']  = array();
$GLOBALS['s_before'] = array();
$GLOBALS['s_done']   = false;

$GLOBALS['s_canary']   = 'VGML-CANARY' . strtoupper( wp_generate_password( 23, false, false ) );
$GLOBALS['s_sentinel'] = 'TOKENSENTINEL' . strtoupper( wp_generate_password( 16, false, false ) );

/** The options this run may write, each read before and put back after. A function, not a const: wp eval-file runs this inside one. */
function s_options() {
    return array( 'vergeml_ai', 'vergeml_ai_credits', 'vergeml_guide_session', 'vergeml_brief_session', 'vergeml_stats', 'vergeml_support_token' );
}

function s_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['s_pass']++;
    } else {
        $GLOBALS['s_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 *  How often the canary occurs in a string -- the count, so nothing is ever
 *  echoed. Case-folded, so a lower-cased copy counts. The marker is the whole
 *  prefix: "CANARY" alone matched an Elementor transient's release-channel word.
 */
function s_hits( $text ) {
    return substr_count( strtoupper( (string) $text ), 'VGML-CANARY' );
}

function s_token_hits( $text ) {
    return substr_count( (string) $text, 'TOKENSENTINEL' );
}

/**
 *  Every outgoing request is answered here and remembered. The answer depends
 *  on the path, and on how often it was asked, so both the failing and the
 *  succeeding branch of each caller run.
 */
function s_catch( $pre, $args, $url ) {

    $GLOBALS['s_calls'][] = array( 'url' => $url, 'args' => $args );

    $path  = (string) wp_parse_url( $url, PHP_URL_PATH );
    $times = 0;
    foreach ( $GLOBALS['s_calls'] as $c ) {
        if ( (string) wp_parse_url( $c['url'], PHP_URL_PATH ) === $path ) {
            $times++;
        }
    }

    $code = 200;
    $body = array();

    if ( '/describe' === substr( $path, -9 ) ) {
        if ( 1 === $times ) {
            $code = 500;
            $body = array( 'error' => 'stand-in: first answer fails on purpose' );
        } else {
            $body = array(
                'caption'           => 'zz vgml secrets stand-in caption',
                'alt'               => 'zz vgml secrets stand-in alt',
                'tags'              => array( 'stand-in' ),
                'title'             => 'stand-in',
                'model'             => 'stand-in',
                'credits'           => array( 'remaining' => 123 ),
                'credits_remaining' => 123,
            );
        }
    } elseif ( '/licence' === substr( $path, -8 ) ) {
        if ( 1 === $times ) {
            $code = 403;
            $body = array( 'error' => 'not_found' );
        } else {
            $body = array( 'credits_remaining' => 123, 'plan' => 'credits' );
        }
    } elseif ( '/guide/session' === substr( $path, -14 ) ) {
        $body = array( 'token' => $GLOBALS['s_sentinel'], 'expires_at' => time() + HOUR_IN_SECONDS, 'summary_hash' => 'stand-in' );
    } elseif ( '/support/ticket' === substr( $path, -15 ) ) {
        $body = array( 'ok' => true, 'id' => 7 );
    } else {
        $body = array( 'ok' => true );
    }

    return array(
        'headers'  => array(),
        'body'     => wp_json_encode( $body ),
        'response' => array( 'code' => $code, 'message' => 200 === $code ? 'OK' : 'Error' ),
        'cookies'  => array(),
        'filename' => null,
    );
}

/** A redirect is the end of an admin_post handler; thrown instead of followed, so the suite gets control back. */
function s_no_redirect( $location ) {
    throw new RuntimeException( 'redirect:' . $location );
}

/** A different install's salt, for the one check that opens the seal with the wrong one. */
function s_other_salt( $salt, $scheme ) {
    return 'auth' === $scheme ? 'zz-vgml-secrets-some-other-install-' . $scheme : $salt;
}

/** Everything put back as it was found, whatever happened in between. */
function s_restore() {

    if ( $GLOBALS['s_done'] ) {
        return;
    }
    $GLOBALS['s_done'] = true;

    remove_filter( 'pre_http_request', 's_catch', 1 );
    remove_filter( 'wp_redirect', 's_no_redirect', 1 );
    remove_filter( 'salt', 's_other_salt', 10 );

    foreach ( s_options() as $name ) {
        if ( ! array_key_exists( $name, $GLOBALS['s_before'] ) ) {
            continue;
        }
        if ( false === $GLOBALS['s_before'][ $name ] ) {
            delete_option( $name );
        } else {
            update_option( $name, $GLOBALS['s_before'][ $name ], false );
        }
    }

    if ( ! empty( $GLOBALS['s_own_log'] ) && file_exists( $GLOBALS['s_own_log'] ) ) {
        @unlink( $GLOBALS['s_own_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
    }
    if ( isset( $GLOBALS['s_prev_log'] ) ) {
        ini_set( 'error_log', (string) $GLOBALS['s_prev_log'] ); // phpcs:ignore WordPress.PHP.IniSet.Risky
    }

    unset( $_POST['vergeml_question'], $_POST['vergeml_consent'], $_POST['vergeml_email'], $_REQUEST['_wpnonce'] );
}

/** Rows anywhere in the tables this plugin writes whose text carries the marker. Names and counts only. */
function s_rows_with( $marker ) {

    global $wpdb;

    $found = array();

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $like = '%' . $wpdb->esc_like( $marker ) . '%';

    $n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", $like ) );
    if ( $n ) {
        $found[] = 'options:' . $n . ' (' . implode( ',', $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE %s", $like ) ) ) . ')';
    }
    foreach ( array( 'postmeta' => 'meta_value', 'termmeta' => 'meta_value', 'usermeta' => 'meta_value', 'posts' => 'post_content' ) as $table => $column ) {
        $n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->$table} WHERE {$column} LIKE %s", $like ) );
        if ( $n ) {
            $found[] = $table . ':' . $n;
        }
    }
    if ( ! empty( $wpdb->vergeml_ai_index ) ) {
        $cols = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->vergeml_ai_index}", ARRAY_A );
        $text = array();
        foreach ( (array) $cols as $col ) {
            if ( preg_match( '/char|text|blob|json/i', (string) $col['Type'] ) ) {
                $text[] = "`{$col['Field']}` LIKE %s";
            }
        }
        if ( $text ) {
            $n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE " . implode( ' OR ', $text ), array_fill( 0, count( $text ), $like ) ) );
            if ( $n ) {
                $found[] = 'vergeml_ai_index:' . $n;
            }
        }
    }
    // phpcs:enable

    return $found;
}

/** One REST call as the current user; the body as the client would receive it. */
function s_rest( $method, $route, $params = array() ) {
    $request = new WP_REST_Request( $method, $route );
    foreach ( $params as $k => $v ) {
        $request->set_param( $k, $v );
    }
    $response = rest_do_request( $request );
    return array(
        'status' => (int) $response->get_status(),
        'body'   => (string) wp_json_encode( rest_get_server()->response_to_data( $response, false ) ),
    );
}


/* ------------------------------------------------------------- the stage */

$s_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID', 'orderby' => 'ID', 'order' => 'ASC' ) );
if ( empty( $s_admins ) ) {
    echo "no administrator on this site to call the routes as\n";
    exit( 1 );
}
wp_set_current_user( (int) $s_admins[0] );

foreach ( s_options() as $s_name ) {
    $GLOBALS['s_before'][ $s_name ] = get_option( $s_name, false );
}

register_shutdown_function( 's_restore' );
add_filter( 'pre_http_request', 's_catch', 1, 3 );

// PHP's own log, for this process only: whatever the run makes PHP complain
// about lands here, and the file is read back and removed at the end.
$GLOBALS['s_own_log']  = get_temp_dir() . 'vgml-secrets-' . wp_generate_password( 8, false, false ) . '.log';
$GLOBALS['s_prev_log'] = ini_set( 'error_log', $GLOBALS['s_own_log'] ); // phpcs:ignore WordPress.PHP.IniSet.Risky
ini_set( 'log_errors', '1' ); // phpcs:ignore WordPress.PHP.IniSet.Risky

// The site's debug log too, if it keeps one: only the bytes written from here on are read.
$s_site_log  = ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? ( true === WP_DEBUG_LOG ? WP_CONTENT_DIR . '/debug.log' : (string) WP_DEBUG_LOG ) : '';
$s_site_mark = ( '' !== $s_site_log && file_exists( $s_site_log ) ) ? filesize( $s_site_log ) : 0;

echo "\nsecrets: at rest, in logs, in responses\n\n";


/* ------------------------------------------------------------ A. the seal */

echo "A  the seal\n";

$s_key    = $GLOBALS['s_canary'];
$s_sealed = vergeml_ai_seal( $s_key );

s_check( 'the sealed form starts with v1:', 0 === strpos( $s_sealed, 'v1:' ) );
s_check( 'the sealed form does not contain the key', 0 === s_hits( $s_sealed ) );

$s_blob = base64_decode( substr( $s_sealed, 3 ), true );
s_check( 'the blob is iv(12) + tag(16) + ciphertext, the ciphertext as long as the key', false !== $s_blob && strlen( $s_blob ) === 28 + strlen( $s_key ), false === $s_blob ? 'not base64' : (string) strlen( $s_blob ) );
s_check( 'the raw blob does not contain the key either', 0 === s_hits( (string) $s_blob ) );
s_check( 'unseal() gives the key back', vergeml_ai_unseal( $s_sealed ) === $s_key );
s_check( 'sealing the same key twice gives two different blobs (random iv)', vergeml_ai_seal( $s_key ) !== $s_sealed );

/*
 *  The derivation, re-done here rather than read: the key that opens the blob
 *  is sha256 over wp_salt( 'auth' ), and nothing else does. If the seal ever
 *  changes how it derives the key this row says so.
 */
$s_derived = hash( 'sha256', wp_salt( 'auth' ), true );
$s_opened  = openssl_decrypt( substr( $s_blob, 28 ), 'aes-256-gcm', $s_derived, OPENSSL_RAW_DATA, substr( $s_blob, 0, 12 ), substr( $s_blob, 12, 16 ) );
s_check( "the key is sha256( wp_salt( 'auth' ) ): re-derived here, it opens the blob", $s_opened === $s_key );

$s_tampered = $s_blob;
$s_tampered[ strlen( $s_tampered ) - 1 ] = chr( ord( $s_tampered[ strlen( $s_tampered ) - 1 ] ) ^ 1 );
s_check( 'one flipped byte and the seal does not open (gcm tag)', '' === vergeml_ai_unseal( 'v1:' . base64_encode( $s_tampered ) ) );

add_filter( 'salt', 's_other_salt', 10, 2 );
$s_other = vergeml_ai_unseal( $s_sealed );
$s_other_sealed = vergeml_ai_seal( $s_key );
remove_filter( 'salt', 's_other_salt', 10 );
s_check( "under another install's auth salt the same blob reads as no key", '' === $s_other );
s_check( 'and a blob sealed under that salt does not open here', '' === vergeml_ai_unseal( $s_other_sealed ) );

/*
 *  The premise: wp_salt( 'auth' ) is AUTH_KEY . AUTH_SALT from wp-config.php.
 *  When those constants are missing WordPress generates a salt and stores it in
 *  the options table -- in which case a copied database carries the salt with
 *  it and the seal protects nothing. So the constants have to be there.
 */
$s_placeholder = 'put your unique phrase here';
s_check( 'AUTH_KEY and AUTH_SALT are defined in wp-config.php and are not the placeholder', defined( 'AUTH_KEY' ) && defined( 'AUTH_SALT' ) && $s_placeholder !== AUTH_KEY && $s_placeholder !== AUTH_SALT && '' !== AUTH_KEY && '' !== AUTH_SALT );
s_check( 'no generated auth salt sits in the database (the constants won)', false === get_site_option( 'auth_key' ) && false === get_site_option( 'auth_salt' ) );


/* -------------------------------------------------- B. planted, at rest */

echo "\nB  the key planted through the settings route, and where it is not\n";

$s_saved = s_rest( 'POST', '/' . VERGEML_REST_NS . '/ai-settings', array( 'license_key' => $s_key ) );
s_check( 'POST /ai-settings as an administrator answers 200', 200 === $s_saved['status'], (string) $s_saved['status'] );
s_check( 'its body (the status) does not carry the key', 0 === s_hits( $s_saved['body'] ) );
s_check( 'the body says has_license true and nothing more about it', false !== strpos( $s_saved['body'], '"has_license":true' ) );

$s_stored = get_option( 'vergeml_ai', array() );
$s_stored_key = is_array( $s_stored ) && isset( $s_stored['license_key'] ) ? (string) $s_stored['license_key'] : '';
s_check( 'the option holds a v1: blob', 0 === strpos( $s_stored_key, 'v1:' ) );
s_check( 'the blob is not the key', $s_stored_key !== $s_key && 0 === s_hits( $s_stored_key ) );
s_check( 'vergeml_ai_unseal( get_option() ) is what gives the key back', vergeml_ai_unseal( $s_stored_key ) === $s_key );
s_check( 'get_option() alone does not: the serialized row has no trace of it', 0 === s_hits( maybe_serialize( $s_stored ) ) );

$s_rows = s_rows_with( 'VGML-CANARY' );
s_check( 'no row in options, postmeta, termmeta, usermeta, posts or the ai index carries the key', empty( $s_rows ), implode( ' ', $s_rows ) );


/* ---------------------------------------- C. every GET route, as admin */

echo "\nC  every readable route, called as an administrator\n";

$s_routes  = rest_get_server()->get_routes();
$s_prefix  = '/' . VERGEML_REST_NS . '/';
$s_called  = 0;
$s_skipped = array();

// Two routes need a taxonomy to answer at all; the rest run with their defaults.
$s_supply = array(
    $s_prefix . 'state' => array( 'taxonomy' => 'media_category' ),
    $s_prefix . 'tree'  => array( 'taxonomy' => 'media_category' ),
);

/*
 *  The two token routes hand the browser a token by design -- a short-lived
 *  bearer the service minted for this licence, this site and this summary. The
 *  stand-in service answered with the sentinel, so the body must carry the
 *  sentinel and must not carry the licence key it was minted with. Minted
 *  first, so the loop below can catch the token surfacing anywhere else.
 */
foreach ( array( 'guide/token', 'brief/token' ) as $s_t ) {
    $s_out = s_rest( 'POST', $s_prefix . $s_t );
    s_check( sprintf( 'POST %s → %d, the token yes, the licence key no', $s_t, $s_out['status'] ), 200 === $s_out['status'] && 1 <= s_token_hits( $s_out['body'] ) && 0 === s_hits( $s_out['body'] ) );
}

$s_guide = get_option( 'vergeml_guide_session' );
s_check(
    'the guide token is cached in the session option, in the clear, for less than an hour and a bit',
    is_array( $s_guide ) && isset( $s_guide['token']['token'] ) && 1 === s_token_hits( $s_guide['token']['token'] )
        && (int) $s_guide['token']['expires_at'] - time() <= HOUR_IN_SECONDS + MINUTE_IN_SECONDS
);

foreach ( $s_routes as $s_route => $s_handlers ) {

    if ( 0 !== strpos( $s_route, $s_prefix ) || false !== strpos( $s_route, '(?P<' ) ) {
        continue;
    }

    $s_get = null;
    foreach ( $s_handlers as $s_h ) {
        if ( ! empty( $s_h['methods']['GET'] ) ) {
            $s_get = $s_h;
            break;
        }
    }
    if ( ! $s_get ) {
        continue;
    }

    $s_needs = array();
    foreach ( (array) ( isset( $s_get['args'] ) ? $s_get['args'] : array() ) as $s_arg => $s_spec ) {
        if ( ! empty( $s_spec['required'] ) && ! isset( $s_supply[ $s_route ][ $s_arg ] ) ) {
            $s_needs[] = $s_arg;
        }
    }
    if ( $s_needs ) {
        $s_skipped[] = substr( $s_route, strlen( $s_prefix ) ) . ' (needs ' . implode( ', ', $s_needs ) . ')';
        continue;
    }

    $s_out = s_rest( 'GET', $s_route, isset( $s_supply[ $s_route ] ) ? $s_supply[ $s_route ] : array() );
    $s_called++;
    s_check(
        sprintf( 'GET %s → %d, no key in the body', substr( $s_route, strlen( $s_prefix ) ), $s_out['status'] ),
        0 === s_hits( $s_out['body'] ) && 0 === s_token_hits( $s_out['body'] ),
        s_hits( $s_out['body'] ) ? s_hits( $s_out['body'] ) . ' occurrence(s)' : ''
    );
}

s_check( 'at least fifteen GET routes were called', $s_called >= 15, (string) $s_called );
if ( $s_skipped ) {
    echo '      not called, an argument is required: ' . implode( '; ', $s_skipped ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}


/* ----------------------------------------------- D. a describe pass, logged */

echo "\nD  a describe pass against the stand-in, and what PHP logged\n";

$s_images = array();
foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => 40, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC' ) ) as $s_id ) {
    $s_path = get_attached_file( $s_id );
    if ( $s_path && file_exists( $s_path ) ) {
        $s_images[] = (int) $s_id;
    }
    if ( count( $s_images ) >= 2 ) {
        break;
    }
}
s_check( 'two image attachments with files on disk to describe', 2 === count( $s_images ), (string) count( $s_images ) );

$s_ai = get_option( 'vergeml_ai', array() );
$s_ai = is_array( $s_ai ) ? $s_ai : array();
$s_mock_was = ! empty( $s_ai['mock'] );

// With mock on, describe() never builds a request. Both halves are wanted, so
// mock is switched on here rather than assumed: the box has run in real mode
// since the buyer walk, and a suite that reads the site's setting fails the
// "mock on" half and then spends the stand-in's one 500 on it (2026-09-20).
$s_ai['mock'] = 1;
update_option( 'vergeml_ai', $s_ai, false );
$s_mock_out = vergeml_ai_describe( $s_images[0] );
s_check( 'mock on: describe() answers without a request', ! is_wp_error( $s_mock_out ) && 0 === count( array_filter( $GLOBALS['s_calls'], function ( $c ) { return '/describe' === substr( (string) wp_parse_url( $c['url'], PHP_URL_PATH ), -9 ); } ) ) );

$s_ai['mock'] = 0;
update_option( 'vergeml_ai', $s_ai, false );

$s_first = vergeml_ai_describe( $s_images[0] );
s_check( 'mock off, the stand-in answers 500: describe() returns WP_Error vergeml_ai_service_500', is_wp_error( $s_first ) && 'vergeml_ai_service_500' === $s_first->get_error_code(), is_wp_error( $s_first ) ? $s_first->get_error_code() : 'array' );
s_check( 'the error message does not carry the key', ! is_wp_error( $s_first ) || 0 === s_hits( $s_first->get_error_message() ) );

$s_second = vergeml_ai_describe( $s_images[1] );
s_check( 'the stand-in answers a description: describe() returns one', is_array( $s_second ) && isset( $s_second['caption'] ), is_wp_error( $s_second ) ? $s_second->get_error_code() : '' );
s_check( 'the description carries no key', 0 === s_hits( wp_json_encode( $s_second ) ) );

$s_describes = array_values( array_filter( $GLOBALS['s_calls'], function ( $c ) { return '/describe' === substr( (string) wp_parse_url( $c['url'], PHP_URL_PATH ), -9 ); } ) );
s_check( 'two describe requests were built and caught', 2 === count( $s_describes ), (string) count( $s_describes ) );

// The licence check, forced twice: the stand-in answers the first /licence
// call of the run with 403 (wherever it came from) and every later one with a
// balance, so both branches have run by the time the second returns.
vergeml_ai_refresh_credits( true );
$s_credits = vergeml_ai_refresh_credits( true );
s_check( 'the forced licence check took the stand-in balance', 123 === $s_credits, var_export( $s_credits, true ) );
s_check( 'the credits option carries no key after a rejected and an accepted check', 0 === s_hits( maybe_serialize( get_option( 'vergeml_ai_credits' ) ) ) );

$s_ai['mock'] = $s_mock_was ? 1 : 0;
update_option( 'vergeml_ai', $s_ai, false );

$s_own = file_exists( $GLOBALS['s_own_log'] ) ? (string) file_get_contents( $GLOBALS['s_own_log'] ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
s_check( 'this process\'s error log carries no key and no token (' . strlen( $s_own ) . ' bytes)', 0 === s_hits( $s_own ) && 0 === s_token_hits( $s_own ) );

if ( '' !== $s_site_log && file_exists( $s_site_log ) ) {
    clearstatcache();
    $s_grown = max( 0, filesize( $s_site_log ) - $s_site_mark );
    $s_tail  = $s_grown ? (string) file_get_contents( $s_site_log, false, null, $s_site_mark, $s_grown ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    s_check( 'the site\'s debug.log grew by ' . $s_grown . ' bytes during the run, none of them the key', 0 === s_hits( $s_tail ) && 0 === s_token_hits( $s_tail ) );
} else {
    echo "      (WP_DEBUG_LOG is off on this site; only this process's log was read)\n";
}


/* ------------------------------------------------ E. the support ticket */

echo "\nE  the support ticket and the system report\n";

$s_report_text = vergeml_system_report_text();
$s_report_data = function_exists( 'vergeml_system_report_data' ) ? wp_json_encode( vergeml_system_report_data() ) : '';
s_check( 'the system report text carries no key', 0 === s_hits( $s_report_text ) && 0 === s_token_hits( $s_report_text ) );
s_check( 'the system report data carries no key', 0 === s_hits( $s_report_data ) && 0 === s_token_hits( $s_report_data ) );

$_POST['vergeml_question'] = 'zz vgml secrets suite: not a real ticket';
$_POST['vergeml_consent']  = '1';
$_REQUEST['_wpnonce']      = wp_create_nonce( 'vergeml_help_send' );
add_filter( 'wp_redirect', 's_no_redirect', 1 );

$s_where = '';
try {
    vergeml_help_send();
} catch ( RuntimeException $e ) {
    $s_where = $e->getMessage();
}
remove_filter( 'wp_redirect', 's_no_redirect', 1 );
unset( $_POST['vergeml_question'], $_POST['vergeml_consent'], $_REQUEST['_wpnonce'] );

s_check( 'the handler ran to its redirect and the stand-in took the ticket', false !== strpos( $s_where, 'vgml-help=sent' ), $s_where );

$s_tickets = array_values( array_filter( $GLOBALS['s_calls'], function ( $c ) { return '/support/ticket' === substr( (string) wp_parse_url( $c['url'], PHP_URL_PATH ), -15 ); } ) );
$s_ticket  = $s_tickets ? json_decode( (string) $s_tickets[0]['args']['body'], true ) : null;
s_check( 'one ticket body was caught', 1 === count( $s_tickets ) && is_array( $s_ticket ) );
// Story 3.2 (FR10): a ticket is the one /v1 call that does not authenticate
// with the key. It names the licence by its last four characters and the site.
s_check( 'it carries the key\'s last four characters as `licence`, not the key', is_array( $s_ticket ) && ! isset( $s_ticket['key'] ) && isset( $s_ticket['licence'] ) && substr( $s_key, -4 ) === $s_ticket['licence'] );
s_check( 'and nothing in it -- question, report, known issues -- carries the key', 0 === s_hits( wp_json_encode( $s_ticket ) ) && 0 === s_token_hits( wp_json_encode( $s_ticket ) ) );


/* ------------------------------------------------- F. the counts snapshot */

echo "\nF  the counts a site shares\n";

$s_snap = vergeml_stats_snapshot();
s_check( 'the snapshot carries no key', 0 === s_hits( wp_json_encode( $s_snap ) ) && 0 === s_token_hits( wp_json_encode( $s_snap ) ) );
s_check( 'vergeml_stats_send() posts it and the stand-in takes it', true === vergeml_stats_send( $s_snap ) );

$s_counts = array_values( array_filter( $GLOBALS['s_calls'], function ( $c ) { return '/counts' === substr( (string) wp_parse_url( $c['url'], PHP_URL_PATH ), -7 ); } ) );
$s_count  = $s_counts ? json_decode( (string) $s_counts[0]['args']['body'], true ) : null;
s_check( 'it authenticates with the key as its top-level `license_key` field', is_array( $s_count ) && isset( $s_count['license_key'] ) && $s_count['license_key'] === $s_key );
unset( $s_count['license_key'] );
s_check( 'and the rest of the body is the snapshot, key-free', 0 === s_hits( wp_json_encode( $s_count ) ) );


/* ------------------------------------- G. every request that was caught */

echo "\nG  every request the run tried to send\n";

$s_bad = array();
foreach ( $GLOBALS['s_calls'] as $s_c ) {
    $s_path = (string) wp_parse_url( $s_c['url'], PHP_URL_PATH );
    $s_raw  = isset( $s_c['args']['body'] ) ? ( is_string( $s_c['args']['body'] ) ? $s_c['args']['body'] : wp_json_encode( $s_c['args']['body'] ) ) : '';
    $s_json = json_decode( $s_raw, true );
    $s_auth = 0;
    foreach ( array( 'license_key', 'key' ) as $s_f ) {
        if ( is_array( $s_json ) && isset( $s_json[ $s_f ] ) && $s_json[ $s_f ] === $s_key ) {
            $s_auth++;
        }
    }
    if ( s_hits( $s_c['url'] ) || s_hits( wp_json_encode( isset( $s_c['args']['headers'] ) ? $s_c['args']['headers'] : array() ) ) || s_hits( $s_raw ) !== $s_auth ) {
        $s_bad[] = $s_path;
    }
}
s_check( count( $GLOBALS['s_calls'] ) . ' requests caught; in each the key is the one auth field of a JSON body, never in the URL or a header', empty( $s_bad ), implode( ', ', $s_bad ) );
/*
 *  Where they were bound: the service (vergeml_ai_service_url()), the stream
 *  host the token mint uses (vergeml_guide_stream_url()), or this site's own
 *  wp-cron.php -- the loopback nudge docs/security-hosts.md lists, which some
 *  GET routes schedule and which carries nothing but the cron lock key.
 */
$s_elsewhere = array();
foreach ( $GLOBALS['s_calls'] as $s_c ) {
    $s_u = (string) $s_c['url'];
    if ( 0 === strpos( $s_u, vergeml_ai_service_url() ) || 0 === strpos( $s_u, vergeml_guide_stream_url() ) ) {
        continue;
    }
    if ( 0 === strpos( $s_u, site_url() ) && false !== strpos( $s_u, 'wp-cron.php' ) ) {
        continue;
    }
    $s_elsewhere[] = (string) wp_parse_url( $s_u, PHP_URL_HOST ) . (string) wp_parse_url( $s_u, PHP_URL_PATH );
}
s_check( 'every request was bound for the service, the stream host or this site\'s own wp-cron.php', empty( $s_elsewhere ), implode( ', ', array_unique( $s_elsewhere ) ) );


/* ---------------------------------------------------------- H. put back */

echo "\nH  put back\n";

s_restore();

foreach ( s_options() as $s_name ) {
    s_check( $s_name . ' is as it was', get_option( $s_name, false ) === $GLOBALS['s_before'][ $s_name ] );
}

$s_after = get_option( 'vergeml_ai', array() );
s_check( 'the canary is not the licence any more', vergeml_ai_unseal( is_array( $s_after ) && isset( $s_after['license_key'] ) ? $s_after['license_key'] : '' ) !== $s_key );

$s_rows = array_merge( s_rows_with( 'VGML-CANARY' ), s_rows_with( 'TOKENSENTINEL' ) );
s_check( 'no row anywhere carries the canary or the sentinel', empty( $s_rows ), implode( ' ', $s_rows ) );
s_check( 'the temporary log is gone', ! file_exists( $GLOBALS['s_own_log'] ) );

printf( "\n%d/%d passed\n\n", $GLOBALS['s_pass'], $GLOBALS['s_pass'] + $GLOBALS['s_fail'] );

exit( $GLOBALS['s_fail'] > 0 ? 1 : 0 );
