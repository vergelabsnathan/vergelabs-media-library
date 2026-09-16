<?php
/**
 *  A shop's media library, seeded from Wikimedia Commons.
 *
 *  The second library of every-picture-a-home C.5: a different shape on
 *  purpose. The tech library is a thousand photographs over twenty folders;
 *  this one is five hundred product photographs under a department store's
 *  catalogue of three hundred folders, three levels deep, most of them empty
 *  -- the tree a shop already has before anyone photographs anything. The
 *  catalogue is tools/box-seed-shop-tree.txt, uploaded on the Folders
 *  screen as a real shop would; the subjects below each name the leaf the
 *  shop would keep that picture in, which is the sheet's right answer.
 *  tests/tree/seed-shop.mjs holds the two files to each other.
 *
 *  Runs on the box's second network, never on the tech library's site:
 *
 *      scp tools/box-seed-shop.php root@box:/tmp/vgml-seed-shop.php
 *      VGML_SEED_N=500 bash tools/box-seed-shop.sh
 *
 *  Same manners as tools/box-seed-technews.php: a 1600px render, a real
 *  User-Agent, a pause between calls, a wait when Commons says slow down.
 *  Skips a file whose name is already in the library, so it can be run again.
 *
 *  Spends nothing. No describe, no embedding, no model call. The describe
 *  pass is a separate step with its own cost, said before it is spent.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$want = max( 1, (int) ( getenv( 'VGML_SEED_N' ) ? getenv( 'VGML_SEED_N' ) : 500 ) );

add_filter( 'http_headers_useragent', function () {
    return SEED_AGENT;
}, 99 );

/*
 *  What a department store photographs, by search term, and the leaf of the
 *  catalogue it goes in. Collisions are deliberate and match the tree's:
 *  sneakers sit under Men, Women and Kids, helmets under Cycling and
 *  Motorbikes, backpacks under Bags and Camping. The leaf named here is the
 *  one a shop would pick for a photograph with nothing else to go on.
 */
$subjects = array(
    'running shoes'              => 'Shoes > Sports > Running',
    'sneakers'                   => 'Shoes > Men > Sneakers',
    'leather boots'              => 'Shoes > Men > Boots',
    'high heels shoes'           => 'Shoes > Women > Heels',
    'sandals'                    => 'Shoes > Women > Sandals',
    'hiking boots'               => 'Shoes > Sports > Hiking boots',
    'denim jeans'                => 'Clothing > Men > Jeans',
    'wool sweater'               => 'Clothing > Men > Sweaters',
    'leather jacket'             => 'Clothing > Men > Jackets',
    'summer dress'               => 'Clothing > Women > Dresses',
    'necktie'                    => 'Clothing > Accessories > Ties',
    'baseball cap'               => 'Clothing > Accessories > Hats',
    'wool scarf'                 => 'Clothing > Accessories > Scarves',
    'sunglasses'                 => 'Clothing > Accessories > Sunglasses',
    'leather handbag'            => 'Bags & Luggage > Handbags',
    'backpack'                   => 'Bags & Luggage > Backpacks',
    'suitcase luggage'           => 'Bags & Luggage > Suitcases',
    'leather wallet'             => 'Bags & Luggage > Wallets',
    'wristwatch'                 => 'Jewellery & Watches > Watches > Men',
    'smartwatch'                 => 'Jewellery & Watches > Watches > Smart watches',
    'gold ring jewelry'          => 'Jewellery & Watches > Rings',
    'pearl necklace'             => 'Jewellery & Watches > Necklaces',
    'earrings'                   => 'Jewellery & Watches > Earrings',
    'perfume bottle'             => 'Beauty > Fragrance > Perfume',
    'lipstick'                   => 'Beauty > Make-up > Lipstick',
    'nail polish bottle'         => 'Beauty > Make-up > Nail polish',
    'hair dryer'                 => 'Beauty > Hair > Hair dryers',
    'smartphone'                 => 'Electronics > Phones > Smartphones',
    'laptop computer'            => 'Electronics > Computers > Laptops',
    'computer keyboard'          => 'Electronics > Computers > Keyboards',
    'headphones'                 => 'Electronics > Audio > Headphones',
    'loudspeaker'                => 'Electronics > Audio > Speakers',
    'turntable record player'    => 'Electronics > Audio > Turntables',
    'digital camera'             => 'Electronics > Cameras > Digital cameras',
    'camera lens'                => 'Electronics > Cameras > Lenses',
    'camera tripod'              => 'Electronics > Cameras > Tripods',
    'game controller'            => 'Electronics > Gaming > Controllers',
    'sofa couch'                 => 'Home > Furniture > Sofas',
    'armchair'                   => 'Home > Furniture > Armchairs',
    'dining table'               => 'Home > Furniture > Dining tables',
    'bookcase'                   => 'Home > Furniture > Bookcases',
    'table lamp'                 => 'Home > Lighting > Table lamps',
    'wall clock'                 => 'Home > Decoration > Clocks',
    'flower vase'                => 'Home > Decoration > Vases',
    'wall mirror'                => 'Home > Decoration > Mirrors',
    'frying pan'                 => 'Kitchen > Cookware > Frying pans',
    'kitchen knife'              => 'Kitchen > Knives & Boards > Kitchen knives',
    'cutting board'              => 'Kitchen > Knives & Boards > Cutting boards',
    'espresso machine'           => 'Kitchen > Appliances > Coffee machines',
    'electric kettle'            => 'Kitchen > Appliances > Kettles',
    'toaster'                    => 'Kitchen > Appliances > Toasters',
    'coffee mug'                 => 'Kitchen > Tableware > Mugs',
    'wine glass'                 => 'Kitchen > Tableware > Glasses',
    'wine bottle'                => 'Food & Drink > Wine & Spirits > Red wine',
    'whisky bottle'              => 'Food & Drink > Wine & Spirits > Whisky',
    'beer bottle'                => 'Food & Drink > Wine & Spirits > Beer',
    'cheese wheel'               => 'Food & Drink > Cheese & Deli > Cheese',
    'chocolate bar'              => 'Food & Drink > Sweets > Chocolate',
    'olive oil bottle'           => 'Food & Drink > Pantry > Olive oil',
    'honey jar'                  => 'Food & Drink > Pantry > Honey',
    'coffee beans'               => 'Food & Drink > Coffee & Tea > Coffee beans',
    'bicycle'                    => 'Sports & Outdoors > Cycling > Bicycles',
    'bicycle helmet'             => 'Sports & Outdoors > Cycling > Helmets',
    'dumbbells'                  => 'Sports & Outdoors > Fitness > Dumbbells',
    'tennis racket'              => 'Sports & Outdoors > Ball sports > Tennis rackets',
    'football ball'              => 'Sports & Outdoors > Ball sports > Footballs',
    'surfboard'                  => 'Sports & Outdoors > Water sports > Surfboards',
    'camping tent'               => 'Sports & Outdoors > Camping > Tents',
    'skateboard'                 => 'Sports & Outdoors > Skateboarding > Decks',
    'skis'                       => 'Sports & Outdoors > Winter sports > Skis',
    'teddy bear'                 => 'Toys & Games > Toys > Teddy bears',
    'lego bricks'                => 'Toys & Games > Toys > Building bricks',
    'chess set'                  => 'Toys & Games > Games > Chess sets',
    'jigsaw puzzle'              => 'Toys & Games > Toys > Puzzles',
    'lawn mower'                 => 'Garden > Tools > Lawn mowers',
    'houseplant pot'             => 'Garden > Plants > Houseplants',
    'barbecue grill'             => 'Garden > Furniture > Barbecues',
    'acoustic guitar'            => 'Books & Music > Instruments > Acoustic guitars',
    'electric guitar'            => 'Books & Music > Instruments > Electric guitars',
    'vinyl record'               => 'Books & Music > Vinyl records',
    'dog collar leash'           => 'Pets > Dogs > Leads and collars',
    'cat tree'                   => 'Pets > Cats > Cat trees',
    'aquarium fish tank'         => 'Pets > Aquarium > Fish tanks',
    'cordless drill'             => 'Tools & DIY > Power tools > Drills',
    'hammer tool'                => 'Tools & DIY > Hand tools > Hammers',
    'toolbox'                    => 'Tools & DIY > Hand tools > Toolboxes',
    'car tire'                   => 'Cars & Bikes > Car parts > Tyres',
    'motorcycle helmet'          => 'Cars & Bikes > Motorbikes > Helmets',
);

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

        if ( 429 !== $code && ( $code < 500 || 0 === $code ) && ! is_wp_error( $res ) ) {
            return $res;
        }

        sleep( min( 30, 3 * $attempt * $attempt ) );
    }

    return $res;
}

$api  = 'https://commons.wikimedia.org/w/api.php';
/*
 *  How many a subject may take, and how many the search asks for. Without
 *  the cap the first run took eleven a subject and reached 500 at the 48th
 *  of 88: eight departments with nothing in them. VGML_SEED_PER overrides,
 *  for a top-up over the subjects the first run never reached.
 */
$cap  = max( 1, (int) ( getenv( 'VGML_SEED_PER' ) ? getenv( 'VGML_SEED_PER' ) : ceil( $want / count( $subjects ) ) ) );
$per  = $cap + 6;
$seen = array();
$made = 0;

global $wpdb;
foreach ( (array) $wpdb->get_col( "SELECT guid FROM {$wpdb->posts} WHERE post_type='attachment'" ) as $guid ) {
    $seen[ strtolower( basename( (string) $guid ) ) ] = true;
}

printf( "seeding up to %d pictures across %d subjects, at most %d each, on %s\n\n", $want, count( $subjects ), $cap, home_url() );

foreach ( $subjects as $query => $leaf ) {

    if ( $made >= $want ) {
        break;
    }

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

    if ( is_wp_error( $res ) ) {
        printf( "  %-28s Commons: %s\n", $query, $res->get_error_message() );
        continue;
    }
    if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
        printf( "  %-28s Commons: HTTP %d\n", $query, (int) wp_remote_retrieve_response_code( $res ) );
        continue;
    }

    $body  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
    $pages = isset( $body['query']['pages'] ) ? (array) $body['query']['pages'] : array();
    $took  = 0;

    $skip = array( 'no imageinfo' => 0, 'mime' => 0, 'small' => 0, 'no url' => 0, 'seen' => 0, 'download' => 0, 'sideload' => 0 );
    $said = '';

    if ( ! $pages ) {
        printf( "  %-28s %-44s no results%s\n", $query, $leaf,
            isset( $body['error']['info'] ) ? ' -- ' . $body['error']['info'] : '' );
        sleep( 1 );
        continue;
    }

    foreach ( $pages as $page ) {

        if ( $made >= $want || $took >= $cap ) {
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

        if ( (int) ( isset( $info['width'] ) ? $info['width'] : 0 ) < 900 ) {
            $skip['small']++;
            continue;
        }

        $src = (string) ( isset( $info['thumburl'] ) ? $info['thumburl'] : ( isset( $info['url'] ) ? $info['url'] : '' ) );
        if ( '' === $src ) {
            $skip['no url']++;
            continue;
        }

        // The path, not the URL: Commons hangs tracking parameters off its file URLs.
        $path = (string) wp_parse_url( (string) $info['url'], PHP_URL_PATH );
        $name = sanitize_file_name( urldecode( basename( '' !== $path ? $path : (string) $info['url'] ) ) );
        if ( '' === $name || isset( $seen[ strtolower( $name ) ] ) ) {
            $skip['seen']++;
            continue;
        }
        $seen[ strtolower( $name ) ] = true;

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

        $title = trim( preg_replace( '/\.(jpe?g|png)$/i', '', str_replace( array( '_', '-' ), ' ', $name ) ) );
        $title = trim( preg_replace( '/\s+/', ' ', $title ) );
        wp_update_post( array( 'ID' => (int) $id, 'post_title' => ucfirst( mb_substr( $title, 0, 90 ) ) ) );

        // The subject it was fetched for: the sheet reads the right answer off this, never off the picture's folder.
        update_post_meta( (int) $id, '_vergeml_seed_leaf', $leaf );

        $made++;
        $took++;
    }

    $why = array();
    foreach ( $skip as $reason => $n ) {
        if ( $n ) {
            $why[] = $reason . ' ' . $n;
        }
    }

    printf( "  %-28s %-44s %3d taken  (%d/%d)%s%s\n", $query, $leaf, $took, $made, $want,
        $why ? '   skipped: ' . implode( ', ', $why ) : '',
        '' !== $said ? '  [' . mb_substr( $said, 0, 70 ) . ']' : ''
    );
    sleep( 1 );
}

printf( "\n%d pictures in the library now\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'" ) );
printf( "%d rows in the describe index (nothing was described)\n", (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . vergeml_index_table() ) );
