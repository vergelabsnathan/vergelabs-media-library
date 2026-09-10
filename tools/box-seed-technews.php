<?php
/**
 *  A tech news site's media library, seeded from Wikimedia Commons.
 *
 *  Downloads and imports images across the subjects a technology publication
 *  actually photographs -- data centres, phones, chips, developers, keynotes,
 *  robots, cables, satellites -- so that a describe pass has something real to
 *  be right or wrong about, and folders have something real to sort.
 *
 *  Everything it fetches is freely licensed (Commons), and it takes a 1600px
 *  render rather than the original: a Commons original can be forty megabytes
 *  and no media library on earth is tested by that.
 *
 *      scp tools/box-seed-technews.php root@box:/tmp/vgml-seed.php
 *      VGML_SEED_N=1000 bash tools/box-seed-technews.sh
 *
 *  Idempotent enough to resume: it skips a file whose name is already in the
 *  library, so a run that stops halfway can be run again.
 *
 *  Spends nothing. No describe, no embedding, no model call -- the pictures
 *  arrive raw, which is the point: the journey is tested from where a customer
 *  starts, not from halfway along it.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$want = max( 1, (int) ( getenv( 'VGML_SEED_N' ) ? getenv( 'VGML_SEED_N' ) : 1000 ) );

// download_url() has its own request; give it the same manners.
add_filter( 'http_headers_useragent', function () {
    return SEED_AGENT;
}, 99 );

/*
 *  The beat sheet of a technology publication. Weighted by how often a real
 *  newsroom reaches for each: hardware and people far more than abstractions,
 *  because that is what the matcher has to tell apart.
 */
$subjects = array(
    'data center servers'          => 'Data centres',
    'server rack'                  => 'Data centres',
    'smartphone'                   => 'Phones',
    'mobile phone screen'          => 'Phones',
    'laptop computer'              => 'Computers',
    'desktop computer'             => 'Computers',
    'computer keyboard'            => 'Computers',
    'motherboard'                  => 'Components',
    'graphics card'                => 'Components',
    'integrated circuit'           => 'Components',
    'semiconductor wafer'          => 'Components',
    'printed circuit board'        => 'Components',
    'software developer'           => 'People',
    'programmer computer'          => 'People',
    'conference keynote'           => 'Events',
    'technology conference'        => 'Events',
    'trade fair electronics'       => 'Events',
    'industrial robot'             => 'Robotics',
    'humanoid robot'               => 'Robotics',
    'drone quadcopter'             => 'Robotics',
    'virtual reality headset'      => 'Devices',
    'smartwatch'                   => 'Devices',
    'tablet computer'              => 'Devices',
    'smart speaker'                => 'Devices',
    'network switch'               => 'Networking',
    'fibre optic cable'            => 'Networking',
    'antenna mast telecommunication' => 'Networking',
    'satellite orbit'              => 'Space',
    'rocket launch'                => 'Space',
    'electric car charging'        => 'Energy',
    'solar panel'                  => 'Energy',
    'wind turbine'                 => 'Energy',
    'lithium battery'              => 'Energy',
    'cleanroom semiconductor'      => 'Manufacturing',
    'factory automation'           => 'Manufacturing',
    '3D printer'                   => 'Manufacturing',
    'electronic waste'             => 'Manufacturing',
    'office workspace computer'    => 'Workplaces',
    'startup office'               => 'Workplaces',
    'video game controller'        => 'Gaming',
    'esports arena'                => 'Gaming',
    'quantum computer'             => 'Research',
    'laboratory microscope'        => 'Research',
    'supercomputer'                => 'Research',
);

/*
 *  Wikimedia asks for a User-Agent that says who is calling and where to
 *  complain, and throttles hard when it does not get one -- forty-four quick
 *  searches with a vague agent came back 429 across the board. So: a real
 *  identifier, a pause between calls, and a wait-and-retry when they say to
 *  slow down. Their servers, their rules.
 */
const SEED_AGENT = 'VergeLabsMediaLibrary/3.16.1 (https://vergelabsmedia.com) test-library-seed';

/** One GET, politely: spaced out, and retried when Commons says slow down. */
function vergeml_seed_get( $url, $tries = 4 ) {

    for ( $attempt = 1; $attempt <= $tries; $attempt++ ) {

        $res = wp_remote_get( $url, array(
            'timeout' => 60,
            'headers' => array( 'User-Agent' => SEED_AGENT, 'Accept' => 'application/json' ),
        ) );

        if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
            return $res;
        }

        $code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );

        // 429 and 5xx are worth waiting for; a 404 is not.
        if ( 429 !== $code && ( $code < 500 || 0 === $code ) && ! is_wp_error( $res ) ) {
            return $res;
        }

        sleep( min( 30, 3 * $attempt * $attempt ) );
    }

    return $res;
}

$api  = 'https://commons.wikimedia.org/w/api.php';
$per  = (int) ceil( $want / count( $subjects ) ) + 8;
$seen = array();
$made = 0;

// What is already here, so a second run tops up rather than duplicating.
global $wpdb;
foreach ( (array) $wpdb->get_col( "SELECT guid FROM {$wpdb->posts} WHERE post_type='attachment'" ) as $guid ) {
    $seen[ strtolower( basename( (string) $guid ) ) ] = true;
}

printf( "seeding up to %d pictures across %d subjects, %d each\n\n", $want, count( $subjects ), $per );

foreach ( $subjects as $query => $folderish ) {

    if ( $made >= $want ) {
        break;
    }

    /*
     *  http_build_query, not add_query_arg: add_query_arg encodes what it is
     *  given, so a term encoded on the way in arrives encoded twice and the
     *  search matches nothing.
     */
    $url = $api . '?' . http_build_query(
        array(
            'action'       => 'query',
            'format'       => 'json',
            'generator'    => 'search',
            'gsrsearch'    => 'filetype:bitmap ' . $query,
            'gsrnamespace' => 6,
            'gsrlimit'     => $per,
            'prop'         => 'imageinfo',
            'iiprop'       => 'url|mime|size',
            'iiurlwidth'   => 1600,
        )
    );

    $res = vergeml_seed_get( $url );

    // Said out loud, because "failed" told nobody anything the first time.
    if ( is_wp_error( $res ) ) {
        printf( "  %-32s Commons: %s\n", $query, $res->get_error_message() );
        continue;
    }
    if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
        printf( "  %-32s Commons: HTTP %d\n", $query, (int) wp_remote_retrieve_response_code( $res ) );
        continue;
    }

    $body  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
    $pages = isset( $body['query']['pages'] ) ? (array) $body['query']['pages'] : array();
    $took  = 0;

    /*
     *  Why candidates were turned away, counted. A seeder that reports "0
     *  taken" and nothing else sends you guessing at four different causes;
     *  this says which one it was.
     */
    $skip = array( 'no imageinfo' => 0, 'mime' => 0, 'small' => 0, 'no url' => 0, 'seen' => 0, 'download' => 0, 'sideload' => 0 );
    $said = '';

    if ( ! $pages ) {
        printf( "  %-32s %-16s no results%s\n", $query, $folderish,
            isset( $body['error']['info'] ) ? ' -- ' . $body['error']['info'] : '' );
        sleep( 1 );
        continue;
    }

    foreach ( $pages as $page ) {

        if ( $made >= $want ) {
            break;
        }

        $info = isset( $page['imageinfo'][0] ) ? $page['imageinfo'][0] : null;
        if ( ! is_array( $info ) ) {
            $skip['no imageinfo']++;
            continue;
        }

        $mime = (string) ( isset( $info['mime'] ) ? $info['mime'] : '' );
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
            $skip['mime']++;
            $said = '' === $said ? $mime : $said;
            continue;
        }

        // Big enough to be a photograph rather than an icon or a diagram scan.
        if ( (int) ( isset( $info['width'] ) ? $info['width'] : 0 ) < 900 ) {
            $skip['small']++;
            continue;
        }

        $src = (string) ( isset( $info['thumburl'] ) ? $info['thumburl'] : ( isset( $info['url'] ) ? $info['url'] : '' ) );
        if ( '' === $src ) {
            $skip['no url']++;
            continue;
        }

        /*
         *  The path, not the URL. Commons hangs tracking parameters off its
         *  file URLs now (?utm_source=...), and basename() on the whole thing
         *  glues them to the name -- so the extension stops being .jpg and
         *  WordPress refuses the upload as a file type it does not allow.
         *  Every one of the first nine hundred was turned away for that.
         */
        $path = (string) wp_parse_url( (string) $info['url'], PHP_URL_PATH );
        $name = sanitize_file_name( urldecode( basename( '' !== $path ? $path : (string) $info['url'] ) ) );
        if ( '' === $name || isset( $seen[ strtolower( $name ) ] ) ) {
            $skip['seen']++;
            continue;
        }
        $seen[ strtolower( $name ) ] = true;

        // Paced: a thousand files is a lot to ask of somebody else's servers.
        usleep( 350000 );

        $tmp = download_url( $src, 60 );
        if ( is_wp_error( $tmp ) ) {
            $skip['download']++;
            $said = '' === $said ? $tmp->get_error_message() : $said;
            continue;
        }

        $id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), 0, null );

        if ( is_wp_error( $id ) ) {
            $skip['sideload']++;
            $said = '' === $said ? $id->get_error_message() : $said;
            @unlink( $tmp );
            continue;
        }

        /*
         *  A title a person would have typed, not a Commons filename with
         *  underscores and a licence tag in it. The describe pass reads the
         *  picture, not this -- but a human looking at the library should see
         *  a media library, not a scrape.
         */
        $title = trim( preg_replace( '/\.(jpe?g|png)$/i', '', str_replace( array( '_', '-' ), ' ', $name ) ) );
        $title = trim( preg_replace( '/\s+/', ' ', $title ) );
        wp_update_post( array( 'ID' => (int) $id, 'post_title' => ucfirst( mb_substr( $title, 0, 90 ) ) ) );

        $made++;
        $took++;
    }

    $why = array();
    foreach ( $skip as $reason => $n ) {
        if ( $n ) {
            $why[] = $reason . ' ' . $n;
        }
    }

    printf( "  %-32s %-16s %3d taken  (%d/%d)%s%s\n", $query, $folderish, $took, $made, $want,
        $why ? '   skipped: ' . implode( ', ', $why ) : '',
        '' !== $said ? '  [' . mb_substr( $said, 0, 70 ) . ']' : ''
    );
    sleep( 1 );
}

printf( "\n%d pictures in the library now\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'" ) );
printf( "%d rows in the describe index (nothing was described)\n", (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . vergeml_index_table() ) );
