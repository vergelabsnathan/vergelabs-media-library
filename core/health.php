<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Library health: which files are copies of which.
 *
 *  A media library that has been in use for a few years carries the same photo
 *  several times over -- re-uploaded because nobody could find the first copy,
 *  or exported again at a different size. Nothing in WordPress sees this, and
 *  nothing in this plugin did either, so the first question anybody asks about
 *  their own library ("how much of this is junk?") had no answer.
 *
 *  Two hashes, because there are two kinds of copy. An md5 of the file finds
 *  the byte-identical re-upload. A dHash -- the picture reduced to 9x8 grey
 *  pixels and stored as the 64 answers to "is this pixel brighter than the one
 *  to its right" -- finds the same photograph at a different size, quality or
 *  crop, because that reduction survives all three.
 *
 *  Both are computed by a chunked, resumable scan in exactly the shape of the
 *  usage scan and the importer: the browser calls a step endpoint in a loop and
 *  each call takes the next few files. Hashing is disk I/O, which is the one
 *  thing shared hosting has least of, so it never happens while a page renders.
 *
 *  This screen reads. It does not delete, merge, trash or quarantine anything,
 *  and the wasted-bytes figure is labelled potential for that reason: knowing
 *  which files are copies is a different act from deciding which copy to keep,
 *  and only the first of those is safe to do without asking.
 *
 *  @since 3.2
 */


const VERGEML_META_HASH     = '_vergeml_hash';
const VERGEML_HEALTH_OPTION = 'vergeml_health';

// How many files one step hashes. Small on purpose: each one is a file read,
// and the step size -- not the query count -- is what keeps a big library from
// timing out on a host that gives PHP thirty seconds.
const VERGEML_HEALTH_BATCH = 25;

// Below this many pixels on a side a picture is an icon or a spacer, and every
// one of them looks like every other to a 9x8 reduction.
const VERGEML_HEALTH_MIN_SIDE = 64;

// Hamming distance over the 64-bit dHash. At or under the first number two
// files are the same picture; between the two they are worth a second look.
const VERGEML_HEALTH_NEAR  = 5;

/*
 *  How much variation a perceptual hash needs before it may match anything.
 *
 *  A dhash records whether each pixel is brighter than the one beside it. On a
 *  photograph of sky, fog, a blurred background or a smooth gradient there is
 *  barely any horizontal change at the scale sampled, and the hash comes out
 *  as one byte repeated: f0f0f0f0f0f0f0f0. Every such picture is then within a
 *  few bits of every other such picture, whatever they are of.
 *
 *  Measured on a real library of 512: only 21 files had three or fewer
 *  distinct bytes, and those 21 produced 93 groups between them -- a handful
 *  of near-empty hashes matching each other combinatorially and dragging
 *  unrelated photographs into a list headed "Duplicates", beside a delete
 *  button. One group held five files of which two were genuinely identical
 *  and three were a sky, a forest and a wall.
 *
 *  Four of eight distinct bytes is the floor. Below it the hash carries no
 *  usable information and the honest thing is to say nothing rather than
 *  something confident and wrong. Byte-identical matching is untouched by
 *  this -- an md5 is an md5 however smooth the picture.
 */
const VERGEML_HEALTH_MIN_BYTES = 4;


/**
 *  Whether a perceptual hash has enough variation to be compared at all.
 */

function vergeml_health_hash_usable( $dhash ) {

    $dhash = (string) $dhash;

    if ( 16 !== strlen( $dhash ) ) {
        return false;
    }

    return count( array_unique( str_split( $dhash, 2 ) ) ) >= VERGEML_HEALTH_MIN_BYTES;
}
const VERGEML_HEALTH_LOOSE = 10;

// Groups returned per list. A library with more than this many duplicate groups
// has a bigger problem than a longer page would solve.
const VERGEML_HEALTH_CAP = 200;


/* ------------------------------------------------------------------ hashing */

/**
 *  vergeml_health_state
 *
 *  Where the scan got to: array( cursor, finished, time ).
 */

function vergeml_health_state() {
    $state = get_option( VERGEML_HEALTH_OPTION, array() );
    return is_array( $state ) ? $state : array();
}


/**
 *  vergeml_health_skip_dhash
 *
 *  Why a file gets an md5 but no picture hash. Returns a reason string, or ''
 *  when the picture hash is worth computing.
 *
 *  Every rule here is a case where the 9x8 reduction would answer confidently
 *  and wrongly: an SVG has no pixels for GD to read, an icon smaller than the
 *  reduction itself matches every other icon, and an animated GIF would be
 *  judged on whichever frame happened to be first.
 */

function vergeml_health_skip_dhash( $attachment_id, $file ) {

    $mime = (string) get_post_mime_type( $attachment_id );

    if ( 0 !== strpos( $mime, 'image/' ) ) {
        return 'not-an-image';
    }

    if ( 'image/svg+xml' === $mime || 'image/svg' === $mime ) {
        return 'svg';
    }

    $meta   = wp_get_attachment_metadata( $attachment_id );
    $width  = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
    $height = isset( $meta['height'] ) ? (int) $meta['height'] : 0;

    if ( ! $width || ! $height ) {
        $size = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an unreadable file is a skip, not a warning.
        if ( is_array( $size ) ) {
            $width  = (int) $size[0];
            $height = (int) $size[1];
        }
    }

    if ( $width < VERGEML_HEALTH_MIN_SIDE || $height < VERGEML_HEALTH_MIN_SIDE ) {
        return 'too-small';
    }

    if ( 'image/gif' === $mime && vergeml_health_gif_animated( $file ) ) {
        return 'animated';
    }

    return '';
}


/**
 *  An animated GIF carries one graphic control extension per frame, so more
 *  than one of them means more than one frame. Read in chunks with an overlap,
 *  because the marker can straddle a chunk boundary and a whole GIF does not
 *  belong in memory to answer a yes/no question.
 */

function vergeml_health_gif_animated( $file ) {

    $handle = @fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged -- a byte scan, not a filesystem write.

    if ( ! $handle ) {
        return false;
    }

    $count = 0;
    $tail  = '';

    while ( ! feof( $handle ) ) {

        $chunk = fread( $handle, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread

        if ( false === $chunk || '' === $chunk ) {
            break;
        }

        $count += substr_count( $tail . $chunk, "\x00\x21\xF9\x04" );
        $tail   = substr( $chunk, -3 );

        if ( $count > 1 ) {
            break;
        }
    }

    fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

    return $count > 1;
}


/**
 *  vergeml_health_dhash_source
 *
 *  The smallest file that still shows the picture. The thumbnail first: the
 *  reduction throws away everything above 9x8 anyway, so reading eight
 *  megapixels to produce 64 bits is eight megapixels of shared-hosting disk
 *  spent on nothing.
 */

function vergeml_health_dhash_source( $attachment_id, $original ) {

    $dir = dirname( $original );

    foreach ( array( 'thumbnail', 'medium' ) as $size ) {

        $meta = image_get_intermediate_size( $attachment_id, $size );

        if ( $meta && ! empty( $meta['file'] ) ) {
            $path = $dir . '/' . wp_basename( $meta['file'] );
            if ( file_exists( $path ) ) {
                return $path;
            }
        }
    }

    // No intermediates -- an image uploaded before the sizes existed, or one
    // small enough that WordPress made none. The original will do if it fits.
    if ( file_exists( $original ) && filesize( $original ) < 1500000 ) {
        return $original;
    }

    return '';
}


/**
 *  vergeml_health_dhash
 *
 *  64 bits, as 16 hex characters, or '' if the picture could not be read.
 *
 *  Reduce to 9x8, walk each row comparing every pixel with its right-hand
 *  neighbour, and keep the answers. Brightness ordering between neighbours is
 *  what survives a resize, a re-compression and a modest crop, which is exactly
 *  the set of things that make two files the same photograph and different
 *  bytes.
 */

/**
 *  vergeml_health_free
 *
 *  Free a GD image, on the versions where that means anything.
 *
 *  Up to PHP 7.4 an image is a resource and imagedestroy() is how its memory
 *  comes back — on a shared host hashing a large library, skipping it is the
 *  difference between finishing and hitting the memory limit. From PHP 8.0 it
 *  is a GdImage object freed by the garbage collector, imagedestroy() has done
 *  nothing at all, and **8.5 deprecates it**: a scan of sixty files wrote 545
 *  deprecation lines into debug.log, which is a wordpress.org submission item.
 *
 *  is_resource() tells the two apart without asking the version number: true on
 *  7.4 where the call still matters, false on 8+ where it is noise.
 */

function vergeml_health_free( $image ) {

    if ( is_resource( $image ) ) {
        imagedestroy( $image );
    }
}


function vergeml_health_dhash( $path ) {

    if ( '' === $path || ! function_exists( 'imagecreatetruecolor' ) ) {
        return '';
    }

    $source = vergeml_health_gd_open( $path );

    if ( ! $source ) {
        return '';
    }

    $width  = imagesx( $source );
    $height = imagesy( $source );

    if ( $width < 2 || $height < 1 ) {
        vergeml_health_free( $source );
        return '';
    }

    $small = imagecreatetruecolor( 9, 8 );

    if ( ! $small ) {
        vergeml_health_free( $source );
        return '';
    }

    imagecopyresampled( $small, $source, 0, 0, 0, 0, 9, 8, $width, $height );
    vergeml_health_free( $source );

    $bits = array();

    for ( $y = 0; $y < 8; $y++ ) {

        $previous = null;

        for ( $x = 0; $x < 9; $x++ ) {

            $rgb = imagecolorat( $small, $x, $y );

            // Rec. 601 luma: the eye weights green over red over blue, and a
            // flat average calls two differently-tinted greys identical.
            $grey = ( ( ( $rgb >> 16 ) & 0xFF ) * 299
                + ( ( $rgb >> 8 ) & 0xFF ) * 587
                + ( $rgb & 0xFF ) * 114 ) / 1000;

            if ( null !== $previous ) {
                $bits[] = $previous < $grey ? 1 : 0;
            }

            $previous = $grey;
        }
    }

    vergeml_health_free( $small );

    if ( 64 !== count( $bits ) ) {
        return '';
    }

    // Four bits at a time into hex, rather than one 64-bit integer: PHP on a
    // 32-bit host has no integer that wide, and shared hosting still has them.
    $hex = '';

    for ( $i = 0; $i < 64; $i += 4 ) {
        $nibble = ( $bits[ $i ] << 3 ) | ( $bits[ $i + 1 ] << 2 ) | ( $bits[ $i + 2 ] << 1 ) | $bits[ $i + 3 ];
        $hex   .= dechex( $nibble );
    }

    return $hex;
}


function vergeml_health_gd_open( $path ) {

    $size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an unreadable file returns false and is skipped.

    if ( ! is_array( $size ) ) {
        return false;
    }

    switch ( $size[2] ) {

        case IMAGETYPE_JPEG:
            return function_exists( 'imagecreatefromjpeg' ) ? @imagecreatefromjpeg( $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        case IMAGETYPE_PNG:
            return function_exists( 'imagecreatefrompng' ) ? @imagecreatefrompng( $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        case IMAGETYPE_GIF:
            return function_exists( 'imagecreatefromgif' ) ? @imagecreatefromgif( $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        case IMAGETYPE_BMP:
            return function_exists( 'imagecreatefrombmp' ) ? @imagecreatefrombmp( $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    }

    if ( defined( 'IMAGETYPE_WEBP' ) && IMAGETYPE_WEBP === $size[2] && function_exists( 'imagecreatefromwebp' ) ) {
        return @imagecreatefromwebp( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    }

    return false;
}


/**
 *  vergeml_health_hash_file
 *
 *  One attachment's stored hash: "md5:<hex>|dhash:<hex>".
 *
 *  Either half can be empty and the empty half means something specific. No
 *  md5 means the original is not on disk -- the file is a database row pointing
 *  at nothing, and it can be a copy of nothing. No dhash means the picture hash
 *  was skipped for one of the reasons above; the file still takes part in exact
 *  matching, because a re-uploaded logo is a duplicate whether or not GD can
 *  read it.
 */

function vergeml_health_hash_file( $attachment_id ) {

    $file = get_attached_file( $attachment_id );

    if ( ! $file || ! file_exists( $file ) ) {
        return 'md5:|dhash:';
    }

    $md5 = @md5_file( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an unreadable file is recorded as unhashed, not fatal.
    $md5 = is_string( $md5 ) ? $md5 : '';

    if ( '' !== vergeml_health_skip_dhash( $attachment_id, $file ) ) {
        return 'md5:' . $md5 . '|dhash:';
    }

    $dhash = vergeml_health_dhash( vergeml_health_dhash_source( $attachment_id, $file ) );

    return 'md5:' . $md5 . '|dhash:' . $dhash;
}


/* --------------------------------------------------------------------- scan */

/**
 *  vergeml_health_backlog
 *
 *  Ids still to hash, from a cursor. The backlog is defined by the absence of
 *  the meta rather than by a list held anywhere, which is what makes the scan
 *  resumable without remembering anything: a file that was hashed has a hash,
 *  and a file that has one is not asked again.
 */

function vergeml_health_backlog( $after = 0, $limit = 0 ) {

    global $wpdb;

    $cap = $limit > 0 ? (int) $limit : PHP_INT_MAX;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the backlog is a bounded walk of the tables the scan maintains.
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
          LEFT JOIN {$wpdb->postmeta} h ON h.post_id = p.ID AND h.meta_key = %s
         WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
           AND p.ID > %d
           AND h.meta_id IS NULL
         ORDER BY p.ID ASC
         LIMIT %d",
        VERGEML_META_HASH,
        (int) $after,
        $cap
    ) ) );
    // phpcs:enable
}


/**
 *  vergeml_health_scan_step
 *
 *  One chunk. The cursor is the last attachment id looked at, so resuming is a
 *  single comparison rather than an OFFSET that grows with the library.
 */

function vergeml_health_scan_step( $cursor = 0 ) {

    global $wpdb;

    $cursor = max( 0, (int) $cursor );
    $ids    = vergeml_health_backlog( $cursor, VERGEML_HEALTH_BATCH );

    /*
     *  The whole batch's posts and meta in two statements.
     *
     *  Without it each file costs four queries -- its post row, its meta, and
     *  the read update_post_meta does before it writes -- so a step ran a
     *  hundred queries to hash twenty-five files. Primed, the only statement
     *  left per file is the write itself, which is the budget: one query per
     *  batch item plus a constant.
     */
    if ( $ids ) {
        _prime_post_caches( $ids, false, true );
    }

    foreach ( $ids as $id ) {
        update_post_meta( $id, VERGEML_META_HASH, vergeml_health_hash_file( $id ) );
        $cursor = max( $cursor, (int) $id );
    }

    $remaining = count( vergeml_health_backlog( $cursor ) );
    $done      = count( $ids ) < VERGEML_HEALTH_BATCH && 0 === $remaining;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the denominator for the progress bar.
    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
    );
    // phpcs:enable

    $state = array(
        'cursor'   => $done ? 0 : $cursor,
        'finished' => $done ? time() : 0,
        'time'     => time(),
    );

    update_option( VERGEML_HEALTH_OPTION, $state, false );

    return array(
        'done'      => $done,
        'remaining' => $remaining,
        'cursor'    => $done ? 0 : $cursor,
        'hashed'    => count( $ids ),
        'total'     => $total,
    );
}


/**
 *  A rescan is the absence of every hash. Deleting the metas puts every file
 *  back in the backlog, which is the same state a fresh install is in, so the
 *  scan needs no separate notion of "start again".
 */

function vergeml_health_reset() {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one delete beats a meta call per attachment, and the key is this plugin's own indexed meta.
    $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => VERGEML_META_HASH ) );
    // phpcs:enable

    // The metas went out from under the object cache, so anything holding a
    // copy of them has to be told.
    wp_cache_set_posts_last_changed();
    wp_cache_flush_group( 'post_meta' );

    delete_option( VERGEML_HEALTH_OPTION );
}


/**
 *  A new upload is hashed on arrival once the library has been scanned, for the
 *  same reason the usage scan stamps new files: an index that goes stale the
 *  moment somebody uploads something is an index nobody trusts twice.
 */

add_action( 'add_attachment', 'vergeml_health_stamp_new', 20 );

function vergeml_health_stamp_new( $attachment_id ) {

    $state = vergeml_health_state();

    if ( empty( $state['finished'] ) ) {
        return; // No index yet; the scan will take it with everything else.
    }

    update_post_meta( $attachment_id, VERGEML_META_HASH, vergeml_health_hash_file( $attachment_id ) );
}


/* ------------------------------------------------------------------- report */

/**
 *  vergeml_health_hamming
 *
 *  How many of the 64 bits differ. Both hashes are 16 hex characters, so the
 *  comparison is 16 four-bit lookups rather than any bit arithmetic -- which
 *  keeps it correct on a 32-bit host, where a 64-bit integer does not exist.
 */

function vergeml_health_hamming( $a, $b ) {

    static $ones = array( 0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4 );

    if ( strlen( $a ) !== strlen( $b ) ) {
        return PHP_INT_MAX;
    }

    $distance = 0;

    for ( $i = 0, $len = strlen( $a ); $i < $len; $i++ ) {
        $distance += $ones[ hexdec( $a[ $i ] ) ^ hexdec( $b[ $i ] ) ];
    }

    return $distance;
}


/**
 *  vergeml_health_exact_groups
 *
 *  Files with identical bytes, grouped, in one statement. The md5 sits at a
 *  fixed offset inside the stored value, so the database can group on it
 *  without the hashes ever being loaded into PHP.
 */

function vergeml_health_exact_groups() {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one grouped statement for a report screen.
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT SUBSTRING( meta_value, 5, 32 ) AS h, GROUP_CONCAT( post_id ORDER BY post_id ASC ) AS ids
         FROM {$wpdb->postmeta}
         WHERE meta_key = %s AND meta_value LIKE %s
         GROUP BY h
         HAVING COUNT(*) > 1
         ORDER BY COUNT(*) DESC",
        VERGEML_META_HASH,
        $wpdb->esc_like( 'md5:' ) . '________________________________' . $wpdb->esc_like( '|dhash:' ) . '%'
    ) );
    // phpcs:enable

    $groups = array();

    foreach ( (array) $rows as $row ) {

        $ids = array_values( array_filter( array_map( 'intval', explode( ',', (string) $row->ids ) ) ) );

        if ( count( $ids ) > 1 ) {
            $groups[] = $ids;
        }
    }

    return $groups;
}


/**
 *  vergeml_health_near_pairs
 *
 *  The same picture, different bytes.
 *
 *  Comparing every hash with every other one is a square of the library and
 *  unrunnable past a few thousand files, so the 64 bits are read as four
 *  16-bit bands and only files sharing a band are ever compared. Two near-
 *  identical hashes almost always agree exactly on at least one band, which
 *  turns the square into a handful of small buckets.
 *
 *  Returns array( 'near' => pairs, 'loose' => pairs ). Pairs, not groups: the
 *  clustering happens once in the report, where the byte-identical pairs are
 *  also known, so a file cannot end up in a confident group and a hesitant one
 *  at the same time.
 */

function vergeml_health_near_pairs( $pairs, $exclude ) {

    $bands = array();

    foreach ( $pairs as $id => $dhash ) {

        /*
         *  A hash with almost no variation in it is not compared to anything.
         *
         *  Sky, fog, blur and smooth gradients all hash to one byte repeated,
         *  so every one of them is within a few bits of every other -- and the
         *  banding below then puts them all in the same bucket, where each
         *  gets compared to each. Twenty-one such files on a real library made
         *  ninety-three groups between them.
         */
        if ( ! vergeml_health_hash_usable( $dhash ) ) {
            continue;
        }

        for ( $band = 0; $band < 4; $band++ ) {
            $bands[ $band . ':' . substr( $dhash, $band * 4, 4 ) ][] = $id;
        }
    }

    $seen  = array();
    $near  = array();
    $loose = array();

    foreach ( $bands as $bucket ) {

        $count = count( $bucket );

        if ( $count < 2 ) {
            continue;
        }

        for ( $i = 0; $i < $count; $i++ ) {
            for ( $j = $i + 1; $j < $count; $j++ ) {

                $a = $bucket[ $i ];
                $b = $bucket[ $j ];

                $key = $a < $b ? $a . '-' . $b : $b . '-' . $a;

                if ( isset( $seen[ $key ] ) ) {
                    continue; // The pair shares more than one band.
                }

                $seen[ $key ] = true;

                // Already reported as byte-identical; saying it twice in two
                // lists reads as two problems.
                if ( isset( $exclude[ $key ] ) ) {
                    continue;
                }

                $distance = vergeml_health_hamming( $pairs[ $a ], $pairs[ $b ] );

                if ( $distance <= VERGEML_HEALTH_NEAR ) {
                    $near[] = array( $a, $b );
                } elseif ( $distance <= VERGEML_HEALTH_LOOSE ) {
                    $loose[] = array( $a, $b );
                }
            }
        }
    }

    return array( 'near' => $near, 'loose' => $loose );
}


/**
 *  vergeml_health_cluster
 *
 *  Pairs into groups, where every file in a group matches every other one.
 *
 *  Not transitive closure, which was the first attempt and was wrong: at a
 *  threshold of five bits, A matching B and B matching C says nothing about A
 *  and C, so following the chain merged fifty-eight unrelated files into one
 *  group announced as duplicates. A group here is a clique -- the claim on the
 *  screen is "these are copies of each other", and that is the claim the shape
 *  has to earn.
 *
 *  Greedy, taking the most-connected file first, so the largest true group
 *  forms before a smaller one takes its members. Groups are small in practice
 *  and bounded by how many copies of one picture a library holds.
 */

function vergeml_health_cluster( $pairs ) {

    $edges = array();
    $adj   = array();

    foreach ( $pairs as $pair ) {

        $a = (int) $pair[0];
        $b = (int) $pair[1];

        if ( $a === $b ) {
            continue;
        }

        $key = $a < $b ? $a . '-' . $b : $b . '-' . $a;

        if ( isset( $edges[ $key ] ) ) {
            continue;
        }

        $edges[ $key ] = true;
        $adj[ $a ][]   = $b;
        $adj[ $b ][]   = $a;
    }

    $order = array_keys( $adj );

    usort( $order, function ( $x, $y ) use ( $adj ) {
        return count( $adj[ $y ] ) - count( $adj[ $x ] );
    } );

    $taken = array();
    $out   = array();

    foreach ( $order as $id ) {

        if ( isset( $taken[ $id ] ) ) {
            continue;
        }

        $group = array( $id );

        foreach ( $adj[ $id ] as $candidate ) {

            if ( isset( $taken[ $candidate ] ) ) {
                continue;
            }

            $fits = true;

            foreach ( $group as $member ) {
                $key = $candidate < $member ? $candidate . '-' . $member : $member . '-' . $candidate;
                if ( ! isset( $edges[ $key ] ) ) {
                    $fits = false;
                    break;
                }
            }

            if ( $fits ) {
                $group[] = $candidate;
            }
        }

        // A file whose group did not form is left free to join a later one.
        if ( count( $group ) > 1 ) {
            foreach ( $group as $member ) {
                $taken[ $member ] = true;
            }
            sort( $group );
            $out[] = $group;
        }
    }

    return $out;
}


/**
 *  vergeml_health_dhash_pairs
 *
 *  Every readable picture hash, once. The whole set has to be in memory for
 *  the banding to work at all -- but it is 16 characters and an integer per
 *  file, which is a megabyte at fifty thousand files.
 */

function vergeml_health_dhash_pairs() {

    global $wpdb;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the one meta sweep the report is allowed.
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT post_id, SUBSTRING( meta_value, 44 ) AS d
         FROM {$wpdb->postmeta}
         WHERE meta_key = %s AND CHAR_LENGTH( meta_value ) = 59",
        VERGEML_META_HASH
    ) );
    // phpcs:enable

    $pairs = array();

    foreach ( (array) $rows as $row ) {

        $dhash = (string) $row->d;

        if ( 16 === strlen( $dhash ) ) {
            $pairs[ (int) $row->post_id ] = $dhash;
        }
    }

    return $pairs;
}


/**
 *  vergeml_health_files
 *
 *  Everything the screen needs to draw the ids it is going to show: title,
 *  thumbnail, size on disk. Two statements for the whole set rather than the
 *  helper-per-file that would otherwise make this endpoint's cost a function
 *  of how much is wrong with the library.
 */

function vergeml_health_files( $ids ) {

    global $wpdb;

    $ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

    if ( ! $ids ) {
        return array();
    }

    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

    /*
     *  $placeholders is a string of %d markers matching count( $ids ), and
     *  every value travels through prepare. The sniffs cannot count a dynamic
     *  list, and the first statement's only placeholders live inside it.
     */
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
    $posts = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE ID IN ( $placeholders )",
        $ids
    ) );

    /*
     *  Whole rows, and straight into the post cache.
     *
     *  Three columns were enough for what this function draws, but the edit
     *  link below asks whether the user may edit each file, and that question
     *  fetches the post -- one query per row on a cold request. It measured
     *  four queries locally and seventy over REST, because a scan in the same
     *  process had warmed the cache the endpoint does not get.
     */
    update_post_cache( $posts );

    $params   = $ids;
    $params[] = '_wp_attached_file';
    $params[] = '_wp_attachment_metadata';
    $params[] = VERGEML_META_FILESIZE;

    $metas = $wpdb->get_results( $wpdb->prepare(
        "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
         WHERE post_id IN ( $placeholders ) AND meta_key IN ( %s, %s, %s )",
        $params
    ) );
    // phpcs:enable

    $by_id = array();

    foreach ( (array) $metas as $meta ) {
        $by_id[ (int) $meta->post_id ][ $meta->meta_key ] = $meta->meta_value;
    }

    $uploads = wp_get_upload_dir();
    $out     = array();

    foreach ( (array) $posts as $post ) {

        $id   = (int) $post->ID;
        $meta = isset( $by_id[ $id ] ) ? $by_id[ $id ] : array();

        $relative = isset( $meta['_wp_attached_file'] ) ? (string) $meta['_wp_attached_file'] : '';
        $attached = maybe_unserialize( isset( $meta['_wp_attachment_metadata'] ) ? $meta['_wp_attachment_metadata'] : '' );

        $thumb = '';

        if ( is_array( $attached ) && ! empty( $attached['sizes']['thumbnail']['file'] ) ) {
            $thumb = trailingslashit( $uploads['baseurl'] ) . trailingslashit( dirname( $relative ) ) . $attached['sizes']['thumbnail']['file'];
        } elseif ( '' !== $relative && 0 === strpos( (string) $post->post_mime_type, 'image/' ) ) {
            $thumb = trailingslashit( $uploads['baseurl'] ) . $relative;
        }

        // The size index the usage scan keeps, when it has run; the file
        // itself when it has not. A stat on a capped set is not a query.
        $bytes = isset( $meta[ VERGEML_META_FILESIZE ] ) ? (int) $meta[ VERGEML_META_FILESIZE ] : 0;

        if ( ! $bytes && '' !== $relative ) {
            $path  = trailingslashit( $uploads['basedir'] ) . $relative;
            $bytes = file_exists( $path ) ? (int) filesize( $path ) : 0;
        }

        $out[ $id ] = array(
            'id'    => $id,
            'title' => (string) $post->post_title,
            'name'  => '' !== $relative ? wp_basename( $relative ) : '',
            'mime'  => (string) $post->post_mime_type,
            'thumb' => $thumb,
            'bytes' => $bytes,
            'edit'  => get_edit_post_link( $id, 'raw' ),
        );
    }

    return $out;
}


function vergeml_health_by_size( $a, $b ) {
    return count( $b ) - count( $a );
}


/**
 *  vergeml_health_report
 *
 *  The two lists.
 *
 *  Wasted bytes is everything in a group except its largest member, and it is
 *  called potential everywhere it is shown: which copy to keep is a judgement
 *  about where the file is used, not about which is biggest, and this screen
 *  deliberately does not make it.
 */

function vergeml_health_report() {

    $state = vergeml_health_state();
    $exact = vergeml_health_exact_groups();

    // The byte-identical findings as pairs, so they cluster together with the
    // picture-hash ones, and as a lookup so the picture hash does not report
    // the same two files a second time.
    $exact_pairs = array();
    $seen        = array();

    foreach ( $exact as $group ) {
        $count = count( $group );
        for ( $i = 0; $i < $count; $i++ ) {
            for ( $j = $i + 1; $j < $count; $j++ ) {
                $a             = $group[ $i ];
                $b             = $group[ $j ];
                $exact_pairs[] = array( $a, $b );
                $seen[ $a < $b ? $a . '-' . $b : $b . '-' . $a ] = true;
            }
        }
    }

    $split = vergeml_health_near_pairs( vergeml_health_dhash_pairs(), $seen );

    /*
     *  Duplicates are byte-identical. Nothing else.
     *
     *  Near matches used to be merged in here, so a list headed "Duplicates"
     *  contained files that merely looked alike to a 64-bit hash -- and a
     *  person reading that heading, beside a delete button, is entitled to
     *  take it literally. Two files with the same md5 ARE the same file; two
     *  files five bits apart are a guess, and a guess belongs under "Possibly
     *  related", which already exists and already says so.
     */
    $duplicates = vergeml_health_cluster( $exact_pairs );

    /*
     *  The two lists are disjoint, and that is what keeps the total honest.
     *
     *  A file already named as a duplicate is not named again as "possibly
     *  related" to a third one: its bytes would be counted in both figures,
     *  and the top-line number on a screen whose entire job is to be believed
     *  would be inflated by exactly the files it is most confident about.
     */
    $placed = array();

    foreach ( $duplicates as $group ) {
        foreach ( $group as $id ) {
            $placed[ $id ] = true;
        }
    }

    $loose = array();

    // Everything the hash merely thinks is alike, near and loose together,
    // minus anything already named as byte-identical above.
    foreach ( array_merge( $split['near'], $split['loose'] ) as $pair ) {
        if ( ! isset( $placed[ $pair[0] ] ) && ! isset( $placed[ $pair[1] ] ) ) {
            $loose[] = $pair;
        }
    }

    $related = vergeml_health_cluster( $loose );

    // Biggest groups first, so the cap cuts the tail rather than the findings
    // somebody would have acted on.
    usort( $duplicates, 'vergeml_health_by_size' );
    usort( $related, 'vergeml_health_by_size' );

    $shown_duplicates = array_slice( $duplicates, 0, VERGEML_HEALTH_CAP );
    $shown_related    = array_slice( $related, 0, VERGEML_HEALTH_CAP );

    $ids = array();

    foreach ( array_merge( $shown_duplicates, $shown_related ) as $group ) {
        foreach ( $group as $id ) {
            $ids[] = $id;
        }
    }

    $files = vergeml_health_files( $ids );

    $build = function ( $groups ) use ( $files ) {

        $out    = array();
        $wasted = 0;

        foreach ( $groups as $group ) {

            $items = array();
            $sizes = array();

            foreach ( $group as $id ) {
                if ( isset( $files[ $id ] ) ) {
                    $items[] = $files[ $id ];
                    $sizes[] = (int) $files[ $id ]['bytes'];
                }
            }

            if ( count( $items ) < 2 ) {
                continue; // The other members were deleted since the scan.
            }

            $group_wasted = array_sum( $sizes ) - max( $sizes );
            $wasted      += $group_wasted;

            $out[] = array(
                'items'  => $items,
                'wasted' => $group_wasted,
            );
        }

        return array( 'groups' => $out, 'wasted' => $wasted );
    };

    $built_duplicates = $build( $shown_duplicates );
    $built_related    = $build( $shown_related );

    return array(
        'scanned'    => ! empty( $state['finished'] ),
        'finished'   => isset( $state['finished'] ) ? (int) $state['finished'] : 0,
        'duplicates' => array(
            'groups' => $built_duplicates['groups'],
            'more'   => max( 0, count( $duplicates ) - count( $shown_duplicates ) ),
            'wasted' => $built_duplicates['wasted'],
        ),
        'related'    => array(
            'groups' => $built_related['groups'],
            'more'   => max( 0, count( $related ) - count( $shown_related ) ),
            'wasted' => $built_related['wasted'],
        ),
        'wasted'     => $built_duplicates['wasted'] + $built_related['wasted'],
    );
}


/* --------------------------------------------------------------------- REST */

add_action( 'rest_api_init', 'vergeml_health_routes' );

function vergeml_health_routes() {

    register_rest_route( VERGEML_REST_NS, '/health-scan', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'vergeml_health_rest_scan',
        // Hashing writes meta on every attachment: curation, the same bar as
        // the usage scan rather than the one that governs uploading.
        'permission_callback' => function () {
            return current_user_can( 'manage_categories' );
        },
        'args'                => array(
            'cursor' => array( 'type' => 'integer', 'default' => 0 ),
            'reset'  => array( 'type' => 'boolean', 'default' => false ),
        ),
    ) );

    register_rest_route( VERGEML_REST_NS, '/health-report', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'vergeml_health_rest_report',
        'permission_callback' => function () {
            return current_user_can( 'manage_categories' );
        },
    ) );
}


function vergeml_health_rest_scan( WP_REST_Request $request ) {

    if ( $request->get_param( 'reset' ) ) {
        vergeml_health_reset();
        return rest_ensure_response( vergeml_health_scan_step( 0 ) );
    }

    return rest_ensure_response( vergeml_health_scan_step( (int) $request->get_param( 'cursor' ) ) );
}


function vergeml_health_rest_report() {
    return rest_ensure_response( vergeml_health_report() );
}


/* ------------------------------------------------------------------- screen */

add_action( 'admin_menu', 'vergeml_health_menu', 14 );

function vergeml_health_menu() {

    if ( ! defined( 'VERGEML_MENU' ) ) {
        return;
    }

    add_submenu_page(
        VERGEML_MENU,
        __( 'Library health', 'vergelabs-media-library' ),
        __( 'Library health', 'vergelabs-media-library' ),
        'manage_categories',
        'media-health',
        'vergeml_health_page'
    );
}


add_action( 'admin_enqueue_scripts', 'vergeml_health_assets' );

function vergeml_health_assets( $hook ) {

    if ( false === strpos( (string) $hook, 'media-health' ) ) {
        return;
    }

    wp_enqueue_script(
        'vergeml-health',
        plugins_url( 'js/vergeml-health.js', VERGEML_FILE ),
        array( 'wp-api-fetch' ),
        vergeml_asset_ver( 'js/vergeml-health.js' ),
        true
    );

    wp_enqueue_style(
        'vergeml-admin',
        plugins_url( 'css/vergeml-admin.css', VERGEML_FILE ),
        array(),
        vergeml_asset_ver( 'css/vergeml-admin.css' )
    );

    wp_localize_script( 'vergeml-health', 'vergemlHealth', array(
        'l10n' => array(
            'scan'         => __( 'Scan the library', 'vergelabs-media-library' ),
            'rescan'       => __( 'Scan again', 'vergelabs-media-library' ),
            'scanning'     => __( 'Reading files…', 'vergelabs-media-library' ),
            /* translators: %s: number of files still to read. */
            'remaining'    => __( '%s to go', 'vergelabs-media-library' ),
            'building'     => __( 'Comparing…', 'vergelabs-media-library' ),
            'failed'       => __( 'That did not work, and nothing was changed.', 'vergelabs-media-library' ),
            'never'        => __( 'We have not compared your files yet. Comparing them changes nothing — we only look.', 'vergelabs-media-library' ),
            'duplicates'   => __( 'Duplicates', 'vergelabs-media-library' ),
            'related'      => __( 'Possibly related', 'vergelabs-media-library' ),
            'noDuplicates' => __( 'No duplicates found.', 'vergelabs-media-library' ),
            'noRelated'    => __( 'Nothing else looked similar.', 'vergelabs-media-library' ),
            'dupeNote'     => __( 'Identical files, or the same picture saved at a different size or quality.', 'vergelabs-media-library' ),
            'relatedNote'  => __( 'These look similar, but we are not confident they are the same picture. Worth your own eye before you do anything.', 'vergelabs-media-library' ),
            /* translators: %s: a formatted file size, e.g. "4.2 MB". */
            /* translators: %s: disk space, e.g. "182.7 KB". */
            'groupWasted'  => __( 'Keep one, delete the rest, and you get %s back', 'vergelabs-media-library' ),
            /* translators: 1: number of groups, 2: a formatted file size. */
            /* translators: 1: how many sets, 2: disk space, e.g. "9 MB". */
            'summary'      => __( '%1$s sets of the same picture · keeping one of each frees %2$s', 'vergelabs-media-library' ),
            /* translators: %s: number of further groups not shown. */
            'more'         => __( 'and %s more', 'vergelabs-media-library' ),
            'readOnly'     => __( 'Nothing on this page deletes, moves or changes anything. It shows you what we found; what to do about it is yours, in the media library.', 'vergelabs-media-library' ),
        ),
    ) );
}


function vergeml_health_page() {

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }

    ?>
    <div class="wrap vgml-home vgml-health">

        <div class="vgml-home-head">
            <h1><?php esc_html_e( 'Library health', 'vergelabs-media-library' ); ?></h1>
            <p class="vgml-home-counts" id="vgml-health-counts"><?php esc_html_e( 'Loading…', 'vergelabs-media-library' ); ?></p>
        </div>

        <div class="vgml-ai-card">
            <h2><?php esc_html_e( 'Duplicate files', 'vergelabs-media-library' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Most libraries have the same photo in them two or three times — uploaded twice, or saved again at a different size. We open each file once and compare them. Nothing is changed and nothing is deleted.', 'vergelabs-media-library' ); ?></p>
            <p>
                <button type="button" class="button button-primary" id="vgml-health-scan"><?php esc_html_e( 'Scan the library', 'vergelabs-media-library' ); ?></button>
            </p>
            <div class="vgml-import-bar" id="vgml-health-bar" hidden><div class="vgml-import-fill" id="vgml-health-fill"></div></div>
            <p id="vgml-health-note"></p>
        </div>

        <div id="vgml-health-report"></div>

        <?php
        /*
         *  Setting a file aside is what you do about a duplicate, so it lives
         *  with the duplicates rather than on the AI screen, where it sat
         *  under the licence key for no reason anybody could have explained.
         */
        do_action( 'vergeml_health_page_cards' );
        ?>

    </div>
    <?php
}
