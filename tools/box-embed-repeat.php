<?php
/**
 *  Ask the same phrase for the same vector, over and over.
 *
 *      wp eval-file tools/box-embed-repeat.php --allow-root
 *
 *  tools/box-embed-stable.php asked one phrase three times on 2026-09-08, got
 *  one checksum, and the answer was written down as "the service answers the
 *  same phrase with the same 512 floats every time". tools/box-embed-cached.php
 *  then found a phrase whose cached vector and fresh vector differ, so one of
 *  those two things is wrong.
 *
 *  Three calls on one phrase is a small sample of a rare event. This asks
 *  several phrases several times each and counts how many distinct answers
 *  come back, which is the measurement that separates "the service changed
 *  between Tuesday and Wednesday" from "the service does not always give the
 *  same answer".
 *
 *  It touches no transient in either direction: the request goes out by hand,
 *  so the cache the filing baseline rests on is left as it was found.
 *
 *  A few dozen /embed calls. No describe pass, no credits.
 *
 *  Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = vergeml_ai_settings();
$licence  = vergeml_ai_unseal( $settings['license_key'] );

if ( '' === $licence ) {
    echo "no licence on this site\n";
    return;
}

$rounds = 6;

/*
 *  The class phrases of the folders the drift lands on -- 1783 is the runner-up
 *  in most of the rows that moved and holds "signage" -- and some it does not,
 *  as a control.
 */
$phrases = array( 'signage', 'building', 'street', 'crosswalk', 'skyline', 'portrait', 'headshot', 'fruit', 'logo' );

printf( "%d calls a phrase, %d phrases\n\n", $rounds, count( $phrases ) );
printf( "%-12s %-8s %-14s %-14s %s\n", 'phrase', 'answers', 'max |diff|', 'worst cosine', 'checksums' );

$unstable = 0;

foreach ( $phrases as $text ) {

    $seen = array();
    $vecs = array();

    for ( $i = 0; $i < $rounds; $i++ ) {

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

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            continue;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $data ) || empty( $data['embedding'] ) ) {
            continue;
        }

        $v   = array_map( 'floatval', $data['embedding'] );
        $sum = substr( md5( wp_json_encode( $v ) ), 0, 6 );

        $seen[ $sum ] = isset( $seen[ $sum ] ) ? $seen[ $sum ] + 1 : 1;
        $vecs[]       = $v;
    }

    if ( ! $vecs ) {
        printf( "%-12s %-8s\n", $text, 'no answer' );
        continue;
    }

    $max = 0.0;
    $cos = 1.0;
    foreach ( $vecs as $a ) {
        foreach ( $vecs as $b ) {
            $n = min( count( $a ), count( $b ) );
            for ( $i = 0; $i < $n; $i++ ) {
                $d = abs( $a[ $i ] - $b[ $i ] );
                if ( $d > $max ) {
                    $max = $d;
                }
            }
            $c = (float) vergeml_meaning_similarity( $a, $b );
            if ( $c < $cos ) {
                $cos = $c;
            }
        }
    }

    if ( count( $seen ) > 1 ) {
        $unstable++;
    }

    $tally = array();
    foreach ( $seen as $sum => $n ) {
        $tally[] = $sum . '×' . $n;
    }

    printf(
        "%-12s %-8d %-14s %-14s %s\n",
        $text,
        count( $seen ),
        sprintf( '%.3e', $max ),
        sprintf( '%.9f', $cos ),
        implode( ' ', $tally )
    );
}

printf( "\nphrases that gave more than one answer: %d of %d\n", $unstable, count( $phrases ) );

$after = (int) $GLOBALS['wpdb']->get_var(
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- a probe, a fixed pattern.
    "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE '\_transient\_vergeml\_qv2\_%'"
);
printf( "phrase vectors still cached: %d\n", $after );
