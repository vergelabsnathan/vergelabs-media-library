<?php
/**
 *  Library health: the hashes, the two lists, the honest wording, the opt-in.
 *
 *  Run on the box:  wp eval-file /tmp/test-health.php --allow-root
 *
 *  The suite seeds its own images rather than using the fixture corpus. The
 *  fixtures are near-uniform generated pictures, which a 9x8 perceptual
 *  reduction cannot tell apart from each other -- true of the fixtures, not of
 *  a real library, and useless as a test of whether the reduction works.
 */

$GLOBALS['vgml_pass'] = 0;
$GLOBALS['vgml_fail'] = 0;

function h_check( $name, $ok, $detail = '' ) {
    if ( $ok ) {
        $GLOBALS['vgml_pass']++;
        echo "  ok   {$name}" . ( $detail ? "  -- {$detail}" : '' ) . "\n";
    } else {
        $GLOBALS['vgml_fail']++;
        echo "  FAIL {$name}" . ( $detail ? "  -- {$detail}" : '' ) . "\n";
    }
}

$uploads = wp_get_upload_dir();
$made    = array();

/**
 *  A picture with something in it. Each of the 9x8 cells the hash samples gets
 *  its own grey from a fixed seed, so the hash is high-entropy and repeatable
 *  -- a flat or gently graded image hashes to nearly all zeros and matches
 *  every other flat image, which is a property of flat images and not a bug.
 */
function h_make_image( $path, $width, $height, $seed ) {

    $image = imagecreatetruecolor( $width, $height );
    $cellw = (int) ceil( $width / 9 );
    $cellh = (int) ceil( $height / 8 );

    mt_srand( $seed );

    for ( $y = 0; $y < 8; $y++ ) {
        for ( $x = 0; $x < 9; $x++ ) {
            $tone  = mt_rand( 0, 255 );
            $color = imagecolorallocate( $image, $tone, $tone, $tone );
            imagefilledrectangle( $image, $x * $cellw, $y * $cellh, ( $x + 1 ) * $cellw, ( $y + 1 ) * $cellh, $color );
        }
    }

    imagejpeg( $image, $path, 92 );
    imagedestroy( $image );
}

function h_resize( $source, $path, $scale ) {

    $src    = imagecreatefromjpeg( $source );
    $width  = (int) ( imagesx( $src ) * $scale );
    $height = (int) ( imagesy( $src ) * $scale );
    $out    = imagecreatetruecolor( $width, $height );

    imagecopyresampled( $out, $src, 0, 0, 0, 0, $width, $height, imagesx( $src ), imagesy( $src ) );
    imagejpeg( $out, $path, 78 );
    imagedestroy( $src );
    imagedestroy( $out );
}

function h_attach( $path, $mime ) {

    $id = wp_insert_attachment( array(
        'post_title'     => 'zzhealth ' . wp_basename( $path ),
        'post_mime_type' => $mime,
        'post_status'    => 'inherit',
    ), $path );

    if ( 0 === strpos( $mime, 'image/' ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
    }

    $GLOBALS['vgml_made'][] = $id;

    return $id;
}

$GLOBALS['vgml_made'] = array();

$dir = trailingslashit( $uploads['basedir'] ) . 'zzhealth';
wp_mkdir_p( $dir );

echo "\nseeding\n";

$a_path = $dir . '/zzhealth-a.jpg';
$b_path = $dir . '/zzhealth-b.jpg';
$c_path = $dir . '/zzhealth-c.jpg';
$d_path = $dir . '/zzhealth-d.jpg';
$t_path = $dir . '/zzhealth-tiny.jpg';
$g_path = $dir . '/zzhealth-anim.gif';
$s_path = $dir . '/zzhealth-mark.svg';

h_make_image( $a_path, 720, 640, 4242 );
copy( $a_path, $b_path );                 // byte-identical re-upload
h_resize( $a_path, $c_path, 0.5 );        // the same picture, exported smaller
h_make_image( $d_path, 720, 640, 909090 ); // something else entirely
h_make_image( $t_path, 48, 48, 4242 );     // under the 64px floor

/*
 *  An animated GIF, for the skip rule that exists because the reduction would
 *  otherwise judge the whole file on whichever frame happened to be first.
 *
 *  GD cannot write one, so a single-frame GIF gets the graphic control blocks
 *  a second frame would bring. The logical screen descriptor is the first ten
 *  bytes and is left alone, so the file still reports its real size -- which
 *  matters, because a file the size check rejects never reaches this rule.
 */
$frame = imagecreatetruecolor( 100, 100 );
imagefilledrectangle( $frame, 0, 0, 100, 100, imagecolorallocate( $frame, 10, 200, 10 ) );
ob_start();
imagegif( $frame );
$gif_one = ob_get_clean();
imagedestroy( $frame );

$still_path = $dir . '/zzhealth-still.gif';
file_put_contents( $still_path, $gif_one );

$marker = "\x00\x21\xF9\x04\x00\x00\x00\x00";
file_put_contents( $g_path, substr( $gif_one, 0, 10 ) . $marker . $marker . substr( $gif_one, 10 ) );

file_put_contents( $s_path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>' );

$a = h_attach( $a_path, 'image/jpeg' );
$b = h_attach( $b_path, 'image/jpeg' );
$c = h_attach( $c_path, 'image/jpeg' );
$d = h_attach( $d_path, 'image/jpeg' );
$t = h_attach( $t_path, 'image/jpeg' );
$g = h_attach( $g_path, 'image/gif' );
$s = h_attach( $s_path, 'image/svg+xml' );

h_check( 'seven files seeded', 7 === count( $GLOBALS['vgml_made'] ), implode( ',', $GLOBALS['vgml_made'] ) );

echo "\nhashing\n";

foreach ( $GLOBALS['vgml_made'] as $id ) {
    update_post_meta( $id, VERGEML_META_HASH, vergeml_health_hash_file( $id ) );
}

$parse = function ( $id ) {
    $raw = (string) get_post_meta( $id, VERGEML_META_HASH, true );
    $bits = explode( '|', $raw );
    return array(
        'md5'   => substr( isset( $bits[0] ) ? $bits[0] : '', 4 ),
        'dhash' => substr( isset( $bits[1] ) ? $bits[1] : '', 6 ),
    );
};

$ha = $parse( $a );
$hb = $parse( $b );
$hc = $parse( $c );
$hd = $parse( $d );

h_check( 'the stored shape is md5 + dhash', 32 === strlen( $ha['md5'] ) && 16 === strlen( $ha['dhash'] ),
    'md5 ' . strlen( $ha['md5'] ) . ', dhash ' . strlen( $ha['dhash'] ) );
h_check( 'identical bytes hash identically', $ha['md5'] === $hb['md5'] );
h_check( 'a resized copy does not', $ha['md5'] !== $hc['md5'] );
h_check( 'the picture hash is not all one value', '0000000000000000' !== $ha['dhash'], $ha['dhash'] );

$near_ac = vergeml_health_hamming( $ha['dhash'], $hc['dhash'] );
$far_ad  = vergeml_health_hamming( $ha['dhash'], $hd['dhash'] );

h_check( 'the resize is within the duplicate threshold', $near_ac <= VERGEML_HEALTH_NEAR, "distance {$near_ac}" );
h_check( 'an unrelated picture is well outside it', $far_ad > VERGEML_HEALTH_LOOSE, "distance {$far_ad}" );

echo "\nskip rules\n";

h_check( 'an image under 64px gets no picture hash', '' === $parse( $t )['dhash'] );
h_check( 'but it still gets an md5', 32 === strlen( $parse( $t )['md5'] ) );
h_check( 'an animated gif is skipped', '' === $parse( $g )['dhash'] );
h_check( 'an svg is skipped', '' === $parse( $s )['dhash'] );
h_check( 'the frame counter tells the two GIFs apart',
    vergeml_health_gif_animated( $g_path ) && ! vergeml_health_gif_animated( $still_path ) );
h_check( 'the skip reasons name themselves',
    'too-small' === vergeml_health_skip_dhash( $t, get_attached_file( $t ) )
    && 'animated' === vergeml_health_skip_dhash( $g, get_attached_file( $g ) )
    && 'svg' === vergeml_health_skip_dhash( $s, get_attached_file( $s ) )
    && '' === vergeml_health_skip_dhash( $a, get_attached_file( $a ) ) );

$gone = h_attach( $dir . '/zzhealth-missing.jpg', 'image/jpeg' );
h_check( 'a file that is not on disk hashes to nothing',
    'md5:|dhash:' === vergeml_health_hash_file( $gone ) );

echo "\nthe report\n";

$report = vergeml_health_report();

$group_with = function ( $groups, $id ) {
    foreach ( $groups as $group ) {
        foreach ( $group['items'] as $item ) {
            if ( (int) $item['id'] === (int) $id ) {
                return $group;
            }
        }
    }
    return null;
};

$dupe_group = $group_with( $report['duplicates']['groups'], $a );
$ids        = $dupe_group ? wp_list_pluck( $dupe_group['items'], 'id' ) : array();

h_check( 'the seeded copies are reported together', null !== $dupe_group,
    $dupe_group ? implode( ',', $ids ) : 'no group' );
h_check( 'the identical re-upload is in it', in_array( $b, $ids, true ) );
h_check( 'so is the resize', in_array( $c, $ids, true ) );
h_check( 'the unrelated picture is not', ! in_array( $d, $ids, true ) );
h_check( 'and neither is anything skipped',
    ! in_array( $t, $ids, true ) && ! in_array( $g, $ids, true ) && ! in_array( $s, $ids, true ) );

if ( $dupe_group ) {
    $sizes  = array();
    foreach ( $dupe_group['items'] as $item ) {
        $sizes[] = (int) $item['bytes'];
    }
    h_check( 'wasted bytes is everything but the largest copy',
        (int) $dupe_group['wasted'] === array_sum( $sizes ) - max( $sizes ),
        $dupe_group['wasted'] . ' of ' . array_sum( $sizes ) );
    h_check( 'every item carries a thumbnail and a size',
        '' !== $dupe_group['items'][0]['thumb'] && $dupe_group['items'][0]['bytes'] > 0 );
}

$in_both = array();
foreach ( $report['duplicates']['groups'] as $group ) {
    foreach ( $group['items'] as $item ) {
        $in_both[ (int) $item['id'] ] = true;
    }
}
$overlap = 0;
foreach ( $report['related']['groups'] as $group ) {
    foreach ( $group['items'] as $item ) {
        if ( isset( $in_both[ (int) $item['id'] ] ) ) {
            $overlap++;
        }
    }
}
h_check( 'the two lists share no file', 0 === $overlap, "{$overlap} in both" );

h_check( 'the totals are the two lists added up',
    (int) $report['wasted'] === (int) $report['duplicates']['wasted'] + (int) $report['related']['wasted'] );

// Every group is a group of copies of each other, not a chain of resemblances.
$not_clique = 0;
foreach ( $report['duplicates']['groups'] as $group ) {
    $hashes = array();
    foreach ( $group['items'] as $item ) {
        $h = $parse( $item['id'] );
        if ( 16 === strlen( $h['dhash'] ) ) {
            $hashes[] = $h['dhash'];
        } elseif ( 32 === strlen( $h['md5'] ) ) {
            $hashes[] = null; // grouped on bytes, not on pixels
        }
    }
    $real = array_values( array_filter( $hashes ) );
    for ( $i = 0; $i < count( $real ); $i++ ) {
        for ( $j = $i + 1; $j < count( $real ); $j++ ) {
            if ( vergeml_health_hamming( $real[ $i ], $real[ $j ] ) > VERGEML_HEALTH_NEAR ) {
                $not_clique++;
            }
        }
    }
}
h_check( 'no group is a chain of resemblances', 0 === $not_clique, "{$not_clique} pairs beyond the threshold" );

echo "\nthe scan\n";

vergeml_health_reset();

h_check( 'a reset empties the progress option', array() === vergeml_health_state() );
h_check( 'and puts every file back in the backlog',
    in_array( $a, vergeml_health_backlog(), true ) );

$cursor = 0;
$steps  = 0;

do {
    $step = vergeml_health_scan_step( $cursor );
    $cursor = $step['cursor'];
    $steps++;
} while ( ! $step['done'] && $steps < 400 );

h_check( 'the scan finishes', ! empty( $step['done'] ), "{$steps} steps" );
h_check( 'and nothing is left', 0 === (int) $step['remaining'] );
h_check( 'the finished stamp is written', ! empty( vergeml_health_state()['finished'] ) );
h_check( 'a step never exceeds its batch', VERGEML_HEALTH_BATCH >= (int) $step['hashed'] );

$after = $parse( $a );
h_check( 'rescanning reproduces the same hash', $after['md5'] === $ha['md5'] && $after['dhash'] === $ha['dhash'] );

$again = vergeml_health_scan_step( 0 );
h_check( 'and scanning again finds nothing to do', 0 === (int) $again['hashed'] && ! empty( $again['done'] ) );

$report_two = vergeml_health_report();
h_check( 'the report is stable across a rescan',
    count( $report_two['duplicates']['groups'] ) === count( $report['duplicates']['groups'] )
    && (int) $report_two['wasted'] === (int) $report['wasted'],
    $report['wasted'] . ' -> ' . $report_two['wasted'] );

echo "\nwhat the scan does not claim\n";

$saved_scan = get_option( VERGEML_SCAN_OPTION );
update_option( VERGEML_SCAN_OPTION, array( 'finished' => time() ), false );

delete_post_meta( $d, VERGEML_META_USED_IN );
update_post_meta( $d, VERGEML_META_UNUSED, '1' );

$fields = vergeml_used_in_field( array(), get_post( $d ) );
$html   = isset( $fields['vergeml_used_in']['html'] ) ? $fields['vergeml_used_in']['html'] : '';

h_check( 'the empty state names what was searched',
    false !== strpos( $html, 'post content, builder layouts, widgets and site settings' ), wp_strip_all_tags( $html ) );
h_check( 'and says what was not',
    false !== strpos( $html, 'not scanned' ) || false !== strpos( $html, 'are not scanned' ) );
h_check( 'it no longer says nothing was found',
    false === strpos( $html, 'Nothing found' ) );

h_check( 'the empty state is a pill, not a paragraph of italics',
    false !== strpos( $html, 'vgml-used-pill is-none' ) && false === strpos( $html, '<em>' ) );

// A file that is used: one pill per place, each one a link to that place.
$host = wp_insert_post( array(
    'post_title'   => 'zzhealth host page',
    'post_status'  => 'publish',
    'post_type'    => 'page',
    'post_content' => 'placeholder',
) );

update_post_meta( $a, VERGEML_META_USED_IN, $host . ',0' );
update_post_meta( $a, VERGEML_META_UNUSED, '0' );

// The CLI has no current user, and "can this person edit that post" is what
// decides whether a pill is a link at all.
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( $admin ? (int) $admin[0] : 1 );

$used = vergeml_used_in_field( array(), get_post( $a ) );
$used_html = isset( $used['vergeml_used_in']['html'] ) ? $used['vergeml_used_in']['html'] : '';

h_check( 'a used file links to where it is used',
    false !== strpos( $used_html, 'post.php?post=' . $host )
    && false !== strpos( $used_html, 'zzhealth host page' ), wp_strip_all_tags( $used_html ) );

h_check( 'the link is a pill',
    false !== strpos( $used_html, '<a class="vgml-used-pill"' ) );

h_check( 'and it says what kind of thing it is',
    false !== strpos( $used_html, 'vgml-used-type' ) );

h_check( 'site settings is shown but is not a link',
    false !== strpos( $used_html, 'vgml-used-pill is-site' )
    && false === strpos( $used_html, '<a class="vgml-used-pill is-site"' ) );

$pills = substr_count( $used_html, 'vgml-used-pill' );
h_check( 'one pill per place, not a comma-separated run', 2 === $pills, "{$pills} pills" );

h_check( 'no pill is a link to nowhere',
    false === strpos( $used_html, 'href=""' ) );

// Someone who cannot edit the referencing post still gets told where the file
// is used -- as a fact rather than as a link that would refuse them.
wp_set_current_user( 0 );
$flat = vergeml_used_in_field( array(), get_post( $a ) );
$flat_html = isset( $flat['vergeml_used_in']['html'] ) ? $flat['vergeml_used_in']['html'] : '';

h_check( 'without edit rights the place is named but not linked',
    false !== strpos( $flat_html, 'zzhealth host page' )
    && false === strpos( $flat_html, '<a class="vgml-used-pill"' )
    && false === strpos( $flat_html, 'href=""' ), wp_strip_all_tags( $flat_html ) );

wp_delete_post( $host, true );
delete_post_meta( $a, VERGEML_META_USED_IN );

if ( false === $saved_scan ) {
    delete_option( VERGEML_SCAN_OPTION );
} else {
    update_option( VERGEML_SCAN_OPTION, $saved_scan, false );
}

echo "\nthe opt-in\n";

$saved_stats = get_option( VERGEML_STATS_OPTION );
delete_option( VERGEML_STATS_OPTION );

h_check( 'off by default', ! vergeml_stats_opted() );
h_check( 'and nothing is stored while it is off', array() === vergeml_stats_state()['snapshot'] );

$refused = vergeml_stats_refresh();
h_check( 'a refresh while off collects nothing', array() === $refused['snapshot'] );

update_option( VERGEML_STATS_OPTION, array( 'opted' => 1, 'snapshot' => array(), 'time' => 0 ), false );
$state = vergeml_stats_refresh( true );
$snap  = $state['snapshot'];

h_check( 'switched on, a snapshot appears', ! empty( $snap ) );
h_check( 'it has exactly the eight fields',
    array( 'attachments', 'depth', 'folders', 'locale', 'mimes', 'php', 'recent', 'wp' ) === ( function ( $s ) {
        $k = array_keys( $s );
        sort( $k );
        return $k;
    } )( $snap ),
    implode( ',', array_keys( $snap ) ) );

h_check( 'the counts are numbers',
    is_int( $snap['attachments'] ) && is_int( $snap['folders'] ) && is_int( $snap['depth'] ) && is_int( $snap['recent'] ),
    "{$snap['attachments']} files, {$snap['folders']} folders, depth {$snap['depth']}, {$snap['recent']} recent" );

h_check( 'the mime breakdown is families and counts',
    is_array( $snap['mimes'] ) && ( ! $snap['mimes'] || is_int( reset( $snap['mimes'] ) ) ),
    wp_json_encode( $snap['mimes'] ) );

/*
 *  The rule the whole card rests on: nothing in the snapshot came out of the
 *  database as text somebody wrote. Versions and the locale are the only
 *  strings, and they are the site's software, not its content.
 */
$strings = array();
array_walk_recursive( $snap, function ( $value ) use ( &$strings ) {
    if ( is_string( $value ) ) {
        $strings[] = $value;
    }
} );

$allowed = array( $snap['wp'], $snap['php'], $snap['locale'] );
h_check( 'the only strings are versions and the locale',
    array() === array_diff( $strings, $allowed ), implode( ' | ', $strings ) );

$keys = array_keys( $snap['mimes'] );
h_check( 'even the mime keys are sanitised',
    $keys === array_map( 'sanitize_key', $keys ), implode( ',', $keys ) );

update_option( VERGEML_STATS_OPTION, array( 'opted' => 0, 'snapshot' => array(), 'time' => 0 ), false );
h_check( 'switching off forgets what was collected', array() === vergeml_stats_state()['snapshot'] );

if ( false === $saved_stats ) {
    delete_option( VERGEML_STATS_OPTION );
} else {
    update_option( VERGEML_STATS_OPTION, $saved_stats, false );
}

echo "\nthe options nobody asked to change\n";

update_option( 'vergeml_health', array( 'cursor' => 7, 'finished' => 0, 'time' => 123 ), false );
update_option( 'vergeml_stats', array( 'opted' => 1, 'snapshot' => array( 'attachments' => 5 ), 'time' => 456 ), false );

vergeml_set_options();

$health_after = get_option( 'vergeml_health' );
$stats_after  = get_option( 'vergeml_stats' );

h_check( 'vergeml_set_options leaves vergeml_health alone',
    7 === (int) $health_after['cursor'] && 123 === (int) $health_after['time'] );
h_check( 'and leaves vergeml_stats alone',
    1 === (int) $stats_after['opted'] && 456 === (int) $stats_after['time'] );

echo "\ntidying up\n";

foreach ( $GLOBALS['vgml_made'] as $id ) {
    wp_delete_attachment( $id, true );
}

foreach ( array( $a_path, $b_path, $c_path, $d_path, $t_path, $g_path, $s_path, $still_path ) as $path ) {
    if ( file_exists( $path ) ) {
        unlink( $path );
    }
}

delete_option( 'vergeml_health' );
delete_option( 'vergeml_stats' );

h_check( 'the seeded files are gone', null === get_post( $a ) );

printf( '%d/%d passed' . PHP_EOL, $GLOBALS['vgml_pass'], $GLOBALS['vgml_pass'] + $GLOBALS['vgml_fail'] );
if ( $GLOBALS['vgml_fail'] > 0 ) {
    exit( 1 );
}
