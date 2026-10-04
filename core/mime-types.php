<?php

if ( ! defined( 'ABSPATH' ) )
	exit;



/**
 *  vergeml_mimes_validate
 *
 *  @type     callback function
 *  @since    1.0
 *  @created  15/10/13
 */

function vergeml_mimes_validate( $input ) {

    if ( ! $input ) $input = array();


    /*
     *  This is a register_setting() sanitize callback, so it only runs from
     *  options.php, which has already checked the nonce and the capability
     *  before calling it. Re-checking here would be checking the same nonce
     *  twice.
     */

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by options.php before this callback runs.
    if ( isset( $_POST['eml-restore-mime-types-settings'] ) ) {

        add_settings_error(
            'vergeml-mime-types',
            'eml_mime_types_restored',
            __('MIME Types settings restored.', 'vergelabs-media-library'),
            'updated'
        );

        remove_filter( 'upload_mimes', 'vergeml_upload_mimes' );

        $allowed_mimes    = get_allowed_mime_types();
        $input = array();

        foreach ( wp_get_mime_types() as $ext => $type ) {

            $input[$ext] = array(
                'mime'     => $type,
                'singular' => $type,
                'plural'   => $type,
                'filter'   => 0,
                'upload'   => isset( $allowed_mimes[ $ext ] ) && ! vergeml_mime_refused( $ext ) ? 1 : 0
            );
        }

        return $input;
    }


    add_settings_error(
        'vergeml-mime-types',
        'eml_mime_types_saved',
        __('MIME Types settings saved.', 'vergelabs-media-library'),
        'updated'
    );


    $known   = wp_get_mime_types();
    $dropped = array();

    foreach ( $input as $ext => $type ) {

        // Only a type WordPress knows, under the MIME type WordPress gives it.
        if ( ! is_array( $type ) || ! isset( $known[ $ext ] ) ) {
            $dropped[] = (string) $ext;
            unset( $input[ $ext ] );
            continue;
        }

        $upload = isset( $type['upload'] ) && !! $type['upload'];

        if ( $upload && vergeml_mime_refused( $ext ) ) {
            $dropped[] = (string) $ext;
            $upload    = false;
        }

        $input[$ext]['filter'] = isset( $type['filter'] ) && !! $type['filter'] ? 1 : 0;
        $input[$ext]['upload'] = $upload ? 1 : 0;

        $input[$ext]['mime'] = $known[ $ext ];
        $input[$ext]['singular'] = isset( $type['singular'] ) ? sanitize_text_field( $type['singular'] ) : $known[ $ext ];
        $input[$ext]['plural'] = isset( $type['plural'] ) ? sanitize_text_field( $type['plural'] ) : $known[ $ext ];
    }

    if ( $dropped ) {
        add_settings_error(
            'vergeml-mime-types',
            'vergeml_mime_types_dropped',
            sprintf(
                /* translators: %s: comma-separated file extensions */
                __( 'Not allowed, and left out: %s. Only file types WordPress itself knows can be allowed, and never a type that can run code.', 'vergelabs-media-library' ),
                implode( ', ', array_map( 'sanitize_text_field', $dropped ) )
            ),
            'error'
        );
    }

    return $input;
}



/**
 *  vergeml_mime_refused
 *
 *  Extensions no setting may allow for upload, even where WordPress knows
 *  them: code a server or a browser would run. Checked per part of a
 *  combined key such as "htm|html".
 */

function vergeml_mime_refused( $ext ) {

    foreach ( explode( '|', strtolower( (string) $ext ) ) as $part ) {

        if ( preg_match( '/^(php\d*|phtml|pht|phps|phar|cgi|pl|py|sh|bash|asp|aspx|jsp|jspx|htm|html|xhtml|shtml|shtm|svg|svgz|js|mjs|exe|htaccess|swf|com|bat|cmd|msi|dll|jar|class)$/', $part ) ) {
            return true;
        }
    }

    return false;
}



/**
 *  vergeml_mimes_dropped
 *
 *  What the stored settings name that this version will not act on: a type
 *  WordPress does not know (the screen used to let one be added by hand), or
 *  a refused one marked for upload. Shown on the File types screen.
 */

function vergeml_mimes_dropped() {

    $known   = wp_get_mime_types();
    $dropped = array();

    foreach ( (array) get_option( 'vergeml_mimes', array() ) as $ext => $type ) {

        if ( ! isset( $known[ $ext ] ) || ( ! empty( $type['upload'] ) && vergeml_mime_refused( $ext ) ) ) {
            $dropped[] = (string) $ext;
        }
    }

    return $dropped;
}




/**
 *  vergeml_mimes_prepared
 *
 *  The MIME settings, sanitized once per request instead of once per call.
 *
 *  wp_get_mime_types() runs constantly -- several times per attachment in a
 *  REST listing -- and the mime_types filter runs with it. Sanitizing every
 *  configured type on every call put sanitize_mime_type at 38,000 fires for
 *  one hundred media items, worth a third of the whole response time. The
 *  settings do not change mid-request except on their own save, which clears
 *  the memo.
 */

function vergeml_mimes_prepared( $reset = false ) {

    static $prepared = null;

    if ( $reset ) {
        $prepared = null;
        return array();
    }

    if ( null !== $prepared ) {
        return $prepared;
    }

    $prepared = array();
    $known    = wp_get_mime_types();

    // Dropped on read too: a setting saved before 4.1.0 may name anything.
    foreach ( (array) get_option( 'vergeml_mimes', array() ) as $ext => $type_array ) {

        if ( ! isset( $known[ $ext ] ) || vergeml_mime_refused( $ext ) ) {
            continue;
        }

        $prepared[ $ext ] = array(
            'mime'   => $known[ $ext ],
            'upload' => ! empty( $type_array['upload'] ),
        );
    }

    return $prepared;
}

function vergeml_mimes_prepared_reset() {
    vergeml_mimes_prepared( true );
}

add_action( 'update_option_vergeml_mimes', 'vergeml_mimes_prepared_reset' );
add_action( 'add_option_vergeml_mimes', 'vergeml_mimes_prepared_reset' );
add_action( 'delete_option_vergeml_mimes', 'vergeml_mimes_prepared_reset' );



/**
 *  vergeml_post_mime_types
 * 
 *  Mime types to show in a media library filter
 *
 *  @since    1.0
 *  @created  03/08/13
 */

add_filter( 'post_mime_types', 'vergeml_post_mime_types' );

function vergeml_post_mime_types( $post_mime_types ) {

    foreach ( get_option( 'vergeml_mimes', array() ) as $ext => $type_array ) {

        if ( (bool) $type_array['filter'] ) {

            $mime_type = sanitize_mime_type( $type_array['mime'] );

            /*
             *  The singular and plural here are labels the site owner typed on
             *  the MIME Types screen, so they cannot be extracted for
             *  translation and _n_noop() has nothing to look up. Build the same
             *  array _n_noop() would return, so translate_nooped_plural() still
             *  works on it, without pretending the label is a known string.
             */

            $post_mime_types[ $mime_type ] = array(
                esc_html( $type_array['plural'] ),
                sprintf(
                    /* translators: %s: plural name of a file type, for example "Images" */
                    esc_html__( 'Manage %s', 'vergelabs-media-library' ),
                    esc_html( $type_array['plural'] )
                ),
                array(
                    'singular' => esc_html( $type_array['singular'] ) . ' <span class="count">(%s)</span>',
                    'plural'   => esc_html( $type_array['plural'] ) . ' <span class="count">(%s)</span>',
                    'context'  => null,
                    'domain'   => 'vergelabs-media-library',
                )
            );
        }
    }

    return $post_mime_types;
}



/**
 *  vergeml_upload_mimes
 *
 *  Allowed mime types
 *
 *  @since    1.0
 *  @since    2.8.10 modified
 *  @since    2.8.11 re-thought
 * 
 *  @created  03/08/13
 */

add_filter( 'upload_mimes', 'vergeml_upload_mimes' );

function vergeml_upload_mimes( $types ) {

    /*
     *  Settings switch WordPress's own types on and off; they never add one.
     *  vergeml_mimes_prepared() holds only types wp_get_mime_types() knows and
     *  none that vergeml_mime_refused() names, so a refused type is never
     *  added here -- whether core allows it for this user stays core's call.
     */
    foreach ( vergeml_mimes_prepared() as $ext => $entry ) {

        if ( $entry['upload'] ) {
            $types[$ext] = $entry['mime'];
        }
        else {
            unset( $types[$ext] );
        }
    }

    return $types;
}



/**
 *  vergeml_check_filetype_and_ext
 *
 *  Vetting allowed mime types
 *
 *  @since    2.8
 *  @since    2.8.10 removed
 *  @since    2.8.11 completely re-thought to allow font types,
 *                   other file types will be added gradually
 *  @since    2.8.14 compatibility with Divi Builder added
 * 
 *  @created  2020/10
 */

add_filter( 'wp_check_filetype_and_ext', 'vergeml_check_filetype_and_ext', 10, 5 );

function vergeml_check_filetype_and_ext( $types, $file, $filename, $mimes, $real_mime = false ) {

    /*
     * If the type has been set by WP - there is nothing to do
     * If there is no real mime - there is nothing to do
     */
    if ( ! isset( $types['type'] ) || $types['type'] || empty( $real_mime ) ) {
        return $types;
    }


    $wp_filetype = wp_check_filetype( $filename, $mimes );
    $ext         = $wp_filetype['ext'];
    $type        = $wp_filetype['type'];


    if ( ! $type ) {
        return $types;
    }


    $font_types  = array(

        // ttf
        'font/ttf',
        'font/sfnt',
        'application/x-font-ttf',       // @since 2.8.14

        // otf
        'font/otf',
        'application/vnd.ms-opentype',
        'application/x-font-opentype',  // @since 2.8.14

        // woff
        'font/woff',
        'font/woff2',
        'application/font-woff',        // @since 2.8.14
        'application/font-woff2',       // @since 2.8.14

        // general
        'application/octet-stream',     // @since 2.8.12
    );

    $ms_types = array(

        // for now it's for xlsm since finfo_file() returns this as its mime type
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'

    );

    /*
     *  A font's content often reads as a generic type, so a font may pass on
     *  it -- a font extension only. Until 4.1.0 any application/* extension
     *  passed whenever the content read as application/octet-stream.
     */
    if ( in_array( $real_mime, $font_types, true ) ) {
        if ( ! in_array( strtolower( (string) $ext ), array( 'ttf', 'otf', 'woff', 'woff2' ), true ) ) {
            $type = false;
            $ext  = false;
        }
    } else if ( in_array( $real_mime, $ms_types, true ) ) {
        if ( ! str_contains( $type, 'application/vnd.ms-excel' ) ) {
            $type = false;
            $ext  = false;
        }
    } else {
        /*
         * Everything else's assumed to be dangerous because it initially
         * came from wp_check_filetype_and_ext() with the mime type mismatch
         */
        $type = false;
        $ext  = false;
    }

    // The mime type must be allowed.
    if ( $type ) {
        $allowed = get_allowed_mime_types();


        if ( ! in_array( $type, $allowed, true ) ) {
            $type = false;
            $ext  = false;
        }
    }

    $types['type'] = $type;
    $types['ext'] = $ext;

    return $types;
}





// function vergeml_handle_upload_overrides( $overrides, $file ) {
//     return $overrides;
// }




/**
 *  wp_generate_attachment_metadata
 *
 *  Add dimentions for SVG mime type
 *
 *  @type     filter callback
 *  @since    2.8.9
 *  @created  12/2021
 */

add_filter( 'wp_generate_attachment_metadata', function( $metadata, $attachment_id, $context = '' ) {

    if ( get_post_mime_type( $attachment_id ) == 'image/svg+xml' ) {
        $svg_path = get_attached_file( $attachment_id );
        $dimensions = vergeml_svg_dimensions( $svg_path );
        $metadata['width'] = $dimensions->width;
        $metadata['height'] = $dimensions->height;
    }
    return $metadata;

}, 10, 3 );



/**
 *  wp_prepare_attachment_for_js
 *
 *  Pass SVG dimensions to the attachment popup
 *
 *  @type     filter callback
 *  @since    2.8.9
 *  @created  12/2021
 */

add_filter( 'wp_prepare_attachment_for_js', function( $response, $attachment, $meta) {

    if ( $response['mime'] == 'image/svg+xml' && empty( $response['sizes'] ) ) {

        $svg_path = get_attached_file( $attachment->ID );

        // The file on disk only: never fetched back over HTTP.
        if ( ! $svg_path || ! file_exists( $svg_path ) ) {
            return $response;
        }

        $dimensions = vergeml_svg_dimensions( $svg_path );
        $response['sizes'] = array(
            'full' => array(
                'url'         => $response['url'],
                'width'       => $dimensions->width,
                'height'      => $dimensions->height,
                'orientation' => $dimensions->width > $dimensions->height ? 'landscape' : 'portrait'
            )
        );
    }
    return $response;

}, 10, 3 );



/**
 *  vergeml_svg_dimensions
 *
 *  Get SVG dimensions
 *
 *  @since    2.8.9
 *  @created  12/2021
 */

function vergeml_svg_dimensions( $svg ) {

    $svg = simplexml_load_file( $svg );
    $width = 0;
    $height = 0;

    if ( $svg ) {

        $attributes = $svg->attributes();

        if( isset( $attributes->width, $attributes->height ) ) {
            $width = (int) $attributes->width;
            $height = (int) $attributes->height;
        } elseif ( isset( $attributes->viewBox ) ) {
            $sizes = explode( ' ', $attributes->viewBox );
            if( isset( $sizes[2], $sizes[3] ) ) {
                $width = (int) $sizes[2];
                $height = (int) $sizes[3];
            }
        }
    }
    return (object) array( 'width' => $width, 'height' => $height );
}
