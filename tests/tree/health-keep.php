<?php
/**
 *  Look-alikes: keep this one, keep both (core/health-keep.php).
 *
 *      wp eval-file tests/tree/health-keep.php --allow-root
 *
 *  Walked on copies, never on the library's own pictures. Two new files are
 *  made from one of the library's JPEGs -- flipped and cropped so they
 *  resemble nothing already there, the second then re-saved smaller so the
 *  two resemble each other and only each other -- and a draft post that
 *  shows the second. The suite refuses to go on unless the scan pairs
 *  exactly those two, so a keep can never reach a real picture.
 *
 *  What it holds the route to: a keep on a used file rewrites the page to the
 *  kept file (URL at the nearest size, block id, image class, featured image)
 *  and only then sets the other aside; on an unused file it sets it aside
 *  straight away; a use the rewrite cannot reach (the site's own settings, a
 *  serialised layout whose length would change) leaves the file where it is
 *  and says so; undo puts the page and the marks back and takes the file out
 *  of set-aside; keep both retires the pair from the report and undo brings
 *  it back; a file the scan does not pair with the kept one is refused before
 *  anything is written.
 *
 *  Everything made here is removed at the end: the two files, the post, the
 *  pair's kept key, the undo records. The usage-scan state is not touched;
 *  the marks the suite needs are written on its own two files and go with
 *  them.
 *
 *  Mutation checks run against this suite on 2026-09-06: with the
 *  verification dropped from vergeml_health_rewrite_source() (return true),
 *  E2 goes red; with the set-aside exclusion dropped from
 *  vergeml_health_report(), C7 goes red.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_health_keep' ) || ! function_exists( 'vergeml_quarantine_add' ) || ! function_exists( 'vergeml_health_report' ) ) {
    echo "core/health-keep.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/image.php';

wp_set_current_user( 1 );

$GLOBALS['hk_pass'] = 0;
$GLOBALS['hk_fail'] = 0;

function hk_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['hk_pass']++;
    } else {
        $GLOBALS['hk_fail']++;
    }
    echo sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function hk_finish() {
    $total = $GLOBALS['hk_pass'] + $GLOBALS['hk_fail'];
    echo sprintf( "\n%d/%d passed\n", $GLOBALS['hk_pass'], $total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit( $GLOBALS['hk_fail'] ? 1 : 0 );
}

/** The look-alike set holding this id, as the report draws it, or null. */
function hk_set_of( $id ) {
    $report = vergeml_health_report();
    foreach ( $report['related']['groups'] as $group ) {
        foreach ( $group['items'] as $item ) {
            if ( (int) $item['id'] === (int) $id ) {
                return $group;
            }
        }
    }
    return null;
}

function hk_ids( $group ) {
    $ids = array();
    foreach ( $group['items'] as $item ) {
        $ids[] = (int) $item['id'];
    }
    sort( $ids );
    return $ids;
}

/** One picture on disk, from a GD image. */
function hk_make_file( $image, $name, $quality, $title ) {

    ob_start();
    imagejpeg( $image, null, $quality );
    $bits = ob_get_clean();

    $upload = wp_upload_bits( $name, null, $bits );

    if ( ! empty( $upload['error'] ) ) {
        return 0;
    }

    $id = wp_insert_attachment( array(
        'post_mime_type' => 'image/jpeg',
        'post_title'     => $title,
        'post_status'    => 'inherit',
    ), $upload['file'] );

    if ( ! $id || is_wp_error( $id ) ) {
        return 0;
    }

    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );

    // The upload hook hashed the file before its sizes existed; hash it as
    // the scan would now that they do.
    update_post_meta( $id, VERGEML_META_HASH, vergeml_health_hash_file( $id ) );

    return (int) $id;
}

/* --- A. preconditions --------------------------------------------------- */

echo "\nA. the two scans the route needs\n";

$hk_health = vergeml_health_state();
$hk_usage  = vergeml_smart_scan_state();

hk_check( 'A1 the library has been compared', ! empty( $hk_health['finished'] ) );
hk_check( 'A2 usage has been scanned', ! empty( $hk_usage['finished'] ) );

if ( empty( $hk_health['finished'] ) || empty( $hk_usage['finished'] ) ) {
    echo "  both scans have to have run on this site before the walk can start\n";
    hk_finish();
}

/* --- B. the copies ------------------------------------------------------ */

echo "\nB. two copies that resemble each other and nothing else\n";

global $wpdb;

$hk_source = 0;
$hk_rows   = $wpdb->get_results(
    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type = 'image/jpeg' ORDER BY ID DESC LIMIT 200"
);

foreach ( (array) $hk_rows as $hk_row ) {
    $hk_meta = wp_get_attachment_metadata( (int) $hk_row->ID );
    $hk_path = get_attached_file( (int) $hk_row->ID );
    if ( is_array( $hk_meta ) && ! empty( $hk_meta['width'] ) && (int) $hk_meta['width'] >= 1000 && $hk_path && file_exists( $hk_path ) ) {
        $hk_source = (int) $hk_row->ID;
        break;
    }
}

hk_check( 'B1 a library JPEG at least 1000 wide to start from', $hk_source > 0, $hk_source ? wp_basename( (string) get_attached_file( $hk_source ) ) : 'none' );

if ( ! $hk_source ) {
    hk_finish();
}

$hk_gd = imagecreatefromjpeg( get_attached_file( $hk_source ) );

// Flipped and cropped to the middle: the light falls the other way and the
// frame is another frame, so the hash is nobody's in the library.
imageflip( $hk_gd, IMG_FLIP_HORIZONTAL );
$hk_w  = imagesx( $hk_gd );
$hk_h  = imagesy( $hk_gd );
$hk_gd = imagecrop( $hk_gd, array( 'x' => (int) ( $hk_w * 0.2 ), 'y' => (int) ( $hk_h * 0.2 ), 'width' => (int) ( $hk_w * 0.6 ), 'height' => (int) ( $hk_h * 0.6 ) ) );

$hk_a = hk_make_file( $hk_gd, 'vgml-p6-walk-one.jpg', 88, 'Phase 6 walk A' );

// The same picture, smaller and saved rougher: alike to the scan, other bytes.
$hk_small = imagescale( $hk_gd, (int) ( imagesx( $hk_gd ) * 0.75 ) );
$hk_b     = hk_make_file( $hk_small, 'vgml-p6-walk-b.jpg', 70, 'Phase 6 walk B' );

imagedestroy( $hk_gd );
imagedestroy( $hk_small );

hk_check( 'B2 two files made', $hk_a > 0 && $hk_b > 0, "$hk_a, $hk_b" );

/*
 *  Leaves nothing behind, whatever happened. Takes its ids as arguments:
 *  wp eval-file runs this file inside a function, so `global` here would
 *  bind to nothing and the copies would stay on the site (which is what
 *  happened on the first run, 2026-09-06).
 */
function hk_cleanup( $a, $b, $post ) {

    if ( $post ) {
        wp_delete_post( (int) $post, true );
    }
    foreach ( array( $a, $b ) as $id ) {
        if ( $id ) {
            vergeml_quarantine_release( (int) $id );
            wp_delete_attachment( (int) $id, true );
        }
    }
    if ( $a && $b ) {
        vergeml_health_retire( array( $a, $b ), true );
    }
    $records = get_option( VERGEML_HEALTH_KEEP_UNDO, array() );
    if ( is_array( $records ) ) {
        foreach ( $records as $token => $record ) {
            if ( isset( $record['keep'] ) && in_array( (int) $record['keep'], array( (int) $a, (int) $b ), true ) ) {
                unset( $records[ $token ] );
            }
        }
        vergeml_health_keep_undo_save( $records );
    }
}

$hk_post = 0;

if ( ! $hk_a || ! $hk_b ) {
    hk_cleanup( $hk_a, $hk_b, 0 );
    hk_finish();
}

hk_check( 'B3 the scan calls the two alike', vergeml_health_alike( $hk_a, $hk_b ) );

$hk_set = hk_set_of( $hk_a );

hk_check( 'B4 the report pairs exactly the two, and nothing of the library', $hk_set && hk_ids( $hk_set ) === array( min( $hk_a, $hk_b ), max( $hk_a, $hk_b ) ),
    $hk_set ? implode( ',', hk_ids( $hk_set ) ) : 'no set' );

if ( ! $hk_set || hk_ids( $hk_set ) !== array( min( $hk_a, $hk_b ), max( $hk_a, $hk_b ) ) ) {
    echo "  the copies touched a real picture; nothing is kept on that\n";
    hk_cleanup( $hk_a, $hk_b, 0 );
    hk_finish();
}

$hk_item_b = null;
foreach ( $hk_set['items'] as $hk_item ) {
    if ( (int) $hk_item['id'] === $hk_b ) {
        $hk_item_b = $hk_item;
    }
}

hk_check( 'B5 a card side carries the four facts', $hk_item_b && $hk_item_b['width'] > 0 && $hk_item_b['height'] > 0 && '' !== $hk_item_b['date'] && isset( $hk_item_b['folder'] ) && is_array( $hk_item_b['used_in'] ),
    $hk_item_b ? "{$hk_item_b['width']}×{$hk_item_b['height']} · {$hk_item_b['date']}" : '' );

/* --- C. keep this one, the other used on a page ------------------------- */

echo "\nC. keep this one while a page shows the other\n";

$hk_url_b  = wp_get_attachment_url( $hk_b );
$hk_url_a  = wp_get_attachment_url( $hk_a );
$hk_meta_b = wp_get_attachment_metadata( $hk_b );
$hk_mid_b  = ! empty( $hk_meta_b['sizes']['medium']['file'] ) ? trailingslashit( dirname( $hk_url_b ) ) . $hk_meta_b['sizes']['medium']['file'] : $hk_url_b;

$hk_content = '<!-- wp:image {"id":' . $hk_b . ',"sizeSlug":"full"} -->'
    . '<figure class="wp-block-image size-full"><img src="' . $hk_url_b . '" alt="" class="wp-image-' . $hk_b . '" srcset="' . $hk_mid_b . ' 300w, ' . $hk_url_b . ' 900w"/></figure>'
    . '<!-- /wp:image --><p>Phase 6 walk.</p>';

$hk_post = wp_insert_post( array(
    'post_title'   => 'Phase 6 walk',
    'post_status'  => 'draft',
    'post_type'    => 'post',
    'post_content' => $hk_content,
) );

update_post_meta( $hk_post, '_thumbnail_id', $hk_b );

// The usage index, as the scan would have written it for this post.
update_post_meta( $hk_b, VERGEML_META_USED_IN, (string) $hk_post );
update_post_meta( $hk_b, VERGEML_META_UNUSED, '0' );

$hk_set = hk_set_of( $hk_a );
$hk_use = null;
foreach ( $hk_set['items'] as $hk_item ) {
    if ( (int) $hk_item['id'] === $hk_b ) {
        $hk_use = $hk_item['used_in'];
    }
}

hk_check( 'C1 the card names the page the file is used on', is_array( $hk_use ) && 1 === count( $hk_use ) && 'Phase 6 walk' === $hk_use[0]['title'] && 'Post' === $hk_use[0]['type'],
    is_array( $hk_use ) ? wp_json_encode( $hk_use ) : '' );

$hk_r = vergeml_health_keep( $hk_a, array( $hk_b ) );

hk_check( 'C2 the keep answers', ! is_wp_error( $hk_r ), is_wp_error( $hk_r ) ? $hk_r->get_error_message() : $hk_r['line'] );

if ( is_wp_error( $hk_r ) ) {
    hk_cleanup( $hk_a, $hk_b, $hk_post );
    hk_finish();
}

clean_post_cache( $hk_post );
$hk_after = (string) get_post_field( 'post_content', $hk_post );

hk_check( 'C3 the page now shows the kept file at its full size', false !== strpos( $hk_after, $hk_url_a ) && false === strpos( $hk_after, $hk_url_b ) );
hk_check( 'C4 the block id and image class moved with it', false !== strpos( $hk_after, '"id":' . $hk_a . ',' ) && false !== strpos( $hk_after, 'wp-image-' . $hk_a ) && false === strpos( $hk_after, 'wp-image-' . $hk_b ) );
hk_check( 'C5 the sized copy went to the kept file\'s nearest size', false === strpos( $hk_after, wp_basename( $hk_mid_b ) ) && preg_match( '#/vgml-p6-walk-one(-\d+x\d+)?\.jpg 300w#', $hk_after ) === 1 );
hk_check( 'C6 the featured image is the kept file', (int) get_post_thumbnail_id( $hk_post ) === $hk_a );
hk_check( 'C7 the other is set aside and the set is off the report', vergeml_quarantine_has( $hk_b ) && ! vergeml_quarantine_has( $hk_a ) && null === hk_set_of( $hk_a ) );
hk_check( 'C8 the answer names the page, the file set aside and the days', 1 === count( $hk_r['pages'] ) && 'Phase 6 walk' === $hk_r['pages'][0]['title'] && array( $hk_b ) === wp_list_pluck( $hk_r['aside'], 'id' )
    && false !== strpos( $hk_r['line'], 'Phase 6 walk now shows it' ) && false !== strpos( $hk_r['line'], 'vgml-p6-walk-b.jpg set aside for 30 days' ), $hk_r['line'] );
hk_check( 'C9 the marks moved: the kept file is used on the page, the other on nothing', (string) get_post_meta( $hk_a, VERGEML_META_USED_IN, true ) === (string) $hk_post && '' === (string) get_post_meta( $hk_b, VERGEML_META_USED_IN, true ) && '1' === (string) get_post_meta( $hk_b, VERGEML_META_UNUSED, true ) );
hk_check( 'C10 the scan\'s own reading no longer finds the other on the page', ! vergeml_health_still_uses( $hk_post, $hk_b ) && vergeml_health_still_uses( $hk_post, $hk_a ) );

$hk_u = vergeml_health_keep_undo( $hk_r['undo']['token'] );

clean_post_cache( $hk_post );

hk_check( 'C11 undo puts the page back as it was', ! is_wp_error( $hk_u ) && (string) get_post_field( 'post_content', $hk_post ) === $hk_content && (int) get_post_thumbnail_id( $hk_post ) === $hk_b,
    is_wp_error( $hk_u ) ? $hk_u->get_error_message() : $hk_u['line'] );
hk_check( 'C12 undo takes the file back and the set returns', ! vergeml_quarantine_has( $hk_b ) && null !== hk_set_of( $hk_a ) );
hk_check( 'C13 undo puts the marks back', (string) get_post_meta( $hk_b, VERGEML_META_USED_IN, true ) === (string) $hk_post && '' === (string) get_post_meta( $hk_a, VERGEML_META_USED_IN, true ) );

$hk_again = vergeml_health_keep_undo( $hk_r['undo']['token'] );
hk_check( 'C14 an undo is spent once', is_wp_error( $hk_again ) && 'gone' === $hk_again->get_error_code() );

/* --- D. keep this one, the other used nowhere --------------------------- */

echo "\nD. keep this one when nothing shows the other\n";

wp_update_post( array( 'ID' => $hk_post, 'post_content' => '<p>Phase 6 walk, no picture.</p>' ) );
delete_post_meta( $hk_post, '_thumbnail_id' );
delete_post_meta( $hk_b, VERGEML_META_USED_IN );
update_post_meta( $hk_b, VERGEML_META_UNUSED, '1' );

$hk_r = vergeml_health_keep( $hk_a, array( $hk_b ) );

hk_check( 'D1 the other is set aside straight away, no page named', ! is_wp_error( $hk_r ) && vergeml_quarantine_has( $hk_b ) && array() === $hk_r['pages'] && false === strpos( $hk_r['line'], 'shows' ),
    is_wp_error( $hk_r ) ? $hk_r->get_error_message() : $hk_r['line'] );
hk_check( 'D2 the reason on the file names the kept one', false !== strpos( (string) get_post_meta( $hk_b, VERGEML_QUARANTINE_REASON, true ), 'vgml-p6-walk-one.jpg' ) );

if ( ! is_wp_error( $hk_r ) ) {
    $hk_u = vergeml_health_keep_undo( $hk_r['undo']['token'] );
}

hk_check( 'D3 undo takes it back', ! vergeml_quarantine_has( $hk_b ) && null !== hk_set_of( $hk_a ) );

/* --- E. a use the rewrite cannot reach ---------------------------------- */

echo "\nE. a use that cannot be rewritten leaves the file where it is\n";

// The site's own settings: the logo, a widget. Not rewritten, by design.
update_post_meta( $hk_b, VERGEML_META_USED_IN, '0' );
update_post_meta( $hk_b, VERGEML_META_UNUSED, '0' );

$hk_r = vergeml_health_keep( $hk_a, array( $hk_b ) );

hk_check( 'E1 used by the site itself: it stays, and the answer says so', ! is_wp_error( $hk_r ) && ! vergeml_quarantine_has( $hk_b ) && array() === $hk_r['aside'] && 1 === count( $hk_r['stays'] )
    && false !== strpos( $hk_r['line'], 'vgml-p6-walk-b.jpg stays: 1 use in Site settings could not be rewritten' ),
    is_wp_error( $hk_r ) ? $hk_r->get_error_message() : $hk_r['line'] );

if ( ! is_wp_error( $hk_r ) ) {
    vergeml_health_keep_undo( $hk_r['undo']['token'] );
}

// A serialised layout whose text length would change: the row is left
// alone, the scan still finds the file in it, the file stays.
update_post_meta( $hk_post, '_vgml_p6_layout', array( 'hero' => $hk_url_b ) );
update_post_meta( $hk_b, VERGEML_META_USED_IN, (string) $hk_post );

$hk_r = vergeml_health_keep( $hk_a, array( $hk_b ) );

hk_check( 'E2 a serialised layout is not rewritten, so the file stays', ! is_wp_error( $hk_r ) && ! vergeml_quarantine_has( $hk_b ) && array() === $hk_r['aside']
    && false !== strpos( $hk_r['line'], 'stays: 1 use in Phase 6 walk could not be rewritten' )
    && $hk_url_b === get_post_meta( $hk_post, '_vgml_p6_layout', true )['hero'],
    is_wp_error( $hk_r ) ? $hk_r->get_error_message() : $hk_r['line'] );

if ( ! is_wp_error( $hk_r ) ) {
    vergeml_health_keep_undo( $hk_r['undo']['token'] );
}

delete_post_meta( $hk_post, '_vgml_p6_layout' );
delete_post_meta( $hk_b, VERGEML_META_USED_IN );
update_post_meta( $hk_b, VERGEML_META_UNUSED, '1' );

/* --- F. keep both ------------------------------------------------------- */

echo "\nF. keep both\n";

$hk_n = vergeml_health_retire( array( $hk_a, $hk_b ) );

hk_check( 'F1 the pair is retired and off the report', 1 === $hk_n && null === hk_set_of( $hk_a ) );
hk_check( 'F2 neither file changed', ! vergeml_quarantine_has( $hk_a ) && ! vergeml_quarantine_has( $hk_b ) );

$hk_n = vergeml_health_retire( array( $hk_a, $hk_b ), true );

hk_check( 'F3 undo brings the set back', 1 === $hk_n && null !== hk_set_of( $hk_a ) );

/* --- G. what the route refuses ------------------------------------------ */

echo "\nG. refusals, before anything is written\n";

$hk_r = vergeml_health_keep( $hk_a, array( $hk_source ) );
hk_check( 'G1 a file the scan does not pair with the kept one is refused', is_wp_error( $hk_r ) && 'not_alike' === $hk_r->get_error_code() && ! vergeml_quarantine_has( $hk_source ) );

$hk_r = vergeml_health_keep( $hk_a, array( $hk_a ) );
hk_check( 'G2 a file cannot be kept and set aside at once', is_wp_error( $hk_r ) && 'keep_in_drop' === $hk_r->get_error_code() );

$hk_r = vergeml_health_keep( $hk_a, array() );
hk_check( 'G3 nothing to set aside is refused', is_wp_error( $hk_r ) && 'nothing' === $hk_r->get_error_code() );

/* --- H. nothing left behind --------------------------------------------- */

echo "\nH. the copies go\n";

hk_cleanup( $hk_a, $hk_b, $hk_post );

$hk_records = get_option( VERGEML_HEALTH_KEEP_UNDO, array() );
$hk_left    = false;
foreach ( (array) $hk_records as $hk_record ) {
    if ( isset( $hk_record['keep'] ) && in_array( (int) $hk_record['keep'], array( $hk_a, $hk_b ), true ) ) {
        $hk_left = true;
    }
}

hk_check( 'H1 the two files, the post, the pair key and the records are gone',
    null === get_post( $hk_a ) && null === get_post( $hk_b ) && null === get_post( $hk_post ) && ! $hk_left
    && ! isset( vergeml_health_kept_pairs()[ vergeml_health_pair_key( $hk_a, $hk_b ) ] ) );

hk_finish();
