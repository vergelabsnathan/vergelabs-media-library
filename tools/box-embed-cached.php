<?php
/**
 *  Is the vector in the cache the vector the service gives today?
 *
 *      wp eval-file tools/box-embed-cached.php --allow-root
 *
 *  tools/box-embed-stable.php asks the service the same phrase twice and
 *  compares the two answers. That measures whether the service is repeatable
 *  in a moment, and on 2026-09-08 it said yes. It does not measure the thing
 *  the baseline actually depends on, which is whether the vector sitting in
 *  the cache -- fetched at some earlier moment, under some earlier build of
 *  the service -- is still what the service would say now. A cache written on
 *  Tuesday and a service answering on Wednesday is exactly the comparison
 *  nobody made, and every phrase vector on the box was written in one
 *  five-minute window on 8 September.
 *
 *  So: read what is cached, ask the service directly, and compare. The
 *  transient is never deleted and never written -- the request goes out by
 *  hand rather than through vergeml_meaning_vector(), so the cache the
 *  baseline rests on is left exactly as it was found.
 *
 *  A few /embed calls. No describe pass, no credits.
 *
 *  Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_ai_settings' ) ) {
    echo "core/ai.php is not loaded\n";
    return;
}

$settings = vergeml_ai_settings();
$licence  = vergeml_ai_unseal( $settings['license_key'] );

if ( '' === $licence ) {
    echo "no licence on this site\n";
    return;
}

/**
 *  One /embed call, straight out, touching no cache in either direction.
 */
function vgml_drift_fresh( $text, $licence ) {

    $response = wp_remote_post(
        vergeml_ai_service_url() . '/embed',
        array(
            'timeout'   => 20,
            'headers'   => array( 'Content-Type' => 'application/json' ),
            'sslverify' => true,
            'body'      => wp_json_encode( array(
                'license_key' => $licence,
                'site'        => home_url(),
                'text'        => $text,
            ) ),
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response->get_error_message();
    }
    if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        return 'HTTP ' . wp_remote_retrieve_response_code( $response );
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( ! is_array( $data ) || empty( $data['embedding'] ) || ! is_array( $data['embedding'] ) ) {
        return 'no embedding in the answer';
    }

    return array_map( 'floatval', $data['embedding'] );
}

/*
 *  The phrases that actually decide a score: every folder's own profile text,
 *  and the short class phrases the matcher compares picture against folder
 *  with. Both go through the same cache and the same service.
 */
$taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';
$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
$terms    = is_wp_error( $terms ) ? array() : array_map( 'intval', $terms );
sort( $terms );

$phrases = array();

foreach ( array_slice( $terms, 0, 6 ) as $tid ) {
    $p = get_term_meta( $tid, '_vergeml_profile', true );
    if ( is_array( $p ) && ! empty( $p['text'] ) ) {
        $phrases[] = array( 'folder ' . $tid, (string) $p['text'] );
    }
}

foreach ( array( 'building', 'signage', 'crosswalk', 'skyline', 'architecture', 'landscape' ) as $c ) {
    $phrases[] = array( 'class', $c );
}

printf( "%-12s %-8s %-8s %-12s %-12s %s\n", 'what', 'cached', 'fresh', 'max |diff|', 'cosine', 'phrase' );

$worst = 0.0;
$same  = 0;
$moved = 0;
$cold  = 0;

foreach ( $phrases as $row ) {

    list( $label, $text ) = $row;

    $slot   = 'vergeml_qv2_' . md5( strtolower( trim( $text ) ) );
    $cached = get_transient( $slot );

    if ( ! is_array( $cached ) ) {
        printf( "%-12s %-8s %-8s %-12s %-12s %s\n", $label, 'none', '-', '-', '-', vgml_drift_snip( $text ) );
        $cold++;
        continue;
    }

    $fresh = vgml_drift_fresh( trim( $text ), $licence );

    if ( ! is_array( $fresh ) ) {
        printf( "%-12s %-8s %-8s %-12s %-12s %s\n", $label, substr( md5( wp_json_encode( $cached ) ), 0, 6 ), 'ERR', $fresh, '-', vgml_drift_snip( $text ) );
        continue;
    }

    $max = 0.0;
    $n   = min( count( $cached ), count( $fresh ) );
    for ( $i = 0; $i < $n; $i++ ) {
        $d = abs( (float) $cached[ $i ] - (float) $fresh[ $i ] );
        if ( $d > $max ) {
            $max = $d;
        }
    }

    $cos = (float) vergeml_meaning_similarity( $cached, $fresh );

    if ( $max > 0.0 ) {
        $moved++;
    } else {
        $same++;
    }
    if ( $max > $worst ) {
        $worst = $max;
    }

    printf(
        "%-12s %-8s %-8s %-12s %-12s %s\n",
        $label,
        substr( md5( wp_json_encode( $cached ) ), 0, 6 ),
        substr( md5( wp_json_encode( $fresh ) ), 0, 6 ),
        sprintf( '%.3e', $max ),
        sprintf( '%.9f', $cos ),
        vgml_drift_snip( $text )
    );
}

function vgml_drift_snip( $text ) {
    $text = (string) $text;
    return mb_strlen( $text ) > 46 ? mb_substr( $text, 0, 43 ) . '...' : $text;
}

printf( "\nidentical %d, moved %d, not cached %d\n", $same, $moved, $cold );
printf( "largest single dimension difference: %.6e\n", $worst );

// Nothing above wrote a transient. Prove it rather than say it.
$after = (int) $GLOBALS['wpdb']->get_var(
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- a probe, a fixed pattern.
    "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE '\_transient\_vergeml\_qv2\_%'"
);
printf( "phrase vectors still cached: %d\n", $after );
