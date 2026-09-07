<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  The media list, in list mode.
 *
 *  Measured on 2026-09-06 at 1600 x 900 with thirteen columns and FileBird
 *  Pro's pane beside ours: the table had 763px for thirteen columns, the File
 *  column was 29px wide, and the median row was 1,960px tall. A screenshot of
 *  the screen was 28,800px long.
 *
 *  The cause is width, not the column count. A row is as tall as the tallest
 *  thing in it that is still in the flow, and in a 29px column everything
 *  wraps at a character a line. Taking all four of our columns away changed
 *  the row by nothing; taking FileBird's pane away as well still left 348px.
 *  The whole diagnosis is in
 *  docs/superpowers/specs/2026-09-06-list-view-diagnosis.md and the design
 *  that follows from it in docs/superpowers/specs/2026-09-07-list-view.md.
 *
 *  So this file stops us taking width from a table we do not own:
 *
 *    - our columns come off the default set and stay in Screen Options;
 *    - what they said moves under the filename in core's own File cell;
 *    - the folder filter is a dropdown in core's filter bar;
 *    - files move by a bulk action, the way a list table moves anything.
 *
 *  Nothing here reaches a model and nothing here spends credits.
 *
 *  @since    3.14
 */


/* -------------------------------------------------------------- columns */

/**
 *  vergeml_list_our_columns
 *
 *  Every column this plugin and its pro half put on the media list: one per
 *  taxonomy we registered with a list column, plus Used on and Alt text.
 *
 *  On a normal library that is four -- Media Categories, Colour, Used on,
 *  Alt text -- and this is the one place that decides which they are.
 */

function vergeml_list_our_columns() {

    $ours = array(
        'vergeml_used',   // core/smart-folders.php -- "Used on"
        'vgmlpro_source', // pro/includes/provenance.php -- "Alt text"
    );

    foreach ( (array) get_option( 'vergeml_taxonomies', array() ) as $taxonomy => $args ) {

        if ( ! empty( $args['show_admin_column'] ) ) {
            $ours[] = 'taxonomy-' . sanitize_key( $taxonomy );
        }
    }

    return $ours;
}


/**
 *  Off the default set, not gone.
 *
 *  `default_hidden_columns` is read only when somebody has never touched
 *  Screen Options on this screen, so a person who turned a column on keeps
 *  it, and a person who wants one back has it in the same menu as all the
 *  others. What the columns said is on the row anyway, under the filename.
 */

add_filter( 'default_hidden_columns', 'vergeml_list_hidden_columns', 10, 2 );

function vergeml_list_hidden_columns( $hidden, $screen ) {

    if ( ! $screen || 'upload' !== $screen->id ) {
        return $hidden;
    }

    return array_values( array_unique( array_merge( (array) $hidden, vergeml_list_our_columns() ) ) );
}


/* ------------------------------------------------- the line under the name */

/**
 *  vergeml_list_row_meta
 *
 *  The file name, its folder and its size as one quiet line, keyed by
 *  attachment id, for the files on this page of the list.
 *
 *  Core's File cell already prints the file name in `p.filename`; this is
 *  that line with the folder and the size on the end of it, which is why it
 *  costs the table no width and no other plugin's columns can crush it.
 */

function vergeml_list_row_meta() {

    $posts = isset( $GLOBALS['wp_query'] ) ? (array) $GLOBALS['wp_query']->posts : array();

    if ( ! $posts ) {
        return array();
    }

    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';
    $meta     = array();

    foreach ( $posts as $post ) {

        $id   = (int) $post->ID;
        $file = get_attached_file( $id );
        $name = $file ? wp_basename( $file ) : '';

        if ( '' === $name ) {
            continue;
        }

        // The size index the smart scan keeps, or the file itself when the
        // scan has not reached this one yet.
        $bytes = (int) get_post_meta( $id, '_vergeml_filesize', true );

        if ( ! $bytes && $file && file_exists( $file ) ) {
            $bytes = (int) filesize( $file );
        }

        $size   = size_format( $bytes );
        $folder = '';

        if ( $taxonomy ) {

            $terms = get_the_terms( $id, $taxonomy );

            if ( $terms && ! is_wp_error( $terms ) ) {
                $folder = vergeml_list_folder_name( $terms[0]->name );
            }
        }

        $meta[ (string) $id ] = '' !== $folder
            /* translators: 1: file name, 2: folder name, 3: file size. */
            ? sprintf( __( '%1$s · %2$s · %3$s', 'vergelabs-media-library' ), $name, $folder, $size )
            /* translators: 1: file name, 2: file size. */
            : sprintf( __( '%1$s · %2$s', 'vergelabs-media-library' ), $name, $size );
    }

    return $meta;
}


/**
 *  The screen's own stylesheet and the one script that writes the line in.
 *
 *  Core renders `p.filename` inside `column_title()` and offers no hook
 *  between the file name and the row actions, so the line is written into
 *  that paragraph rather than added under it: same element, same one line,
 *  no second row of text and no change to core's markup. Without the script
 *  the row still reads as WordPress draws it.
 *
 *  The list table has already run by the time this fires -- upload.php calls
 *  prepare_items() before admin-header.php -- so the rows on screen are the
 *  rows in $wp_query here.
 */

add_action( 'admin_enqueue_scripts', 'vergeml_list_assets' );

function vergeml_list_assets( $hook ) {

    if ( 'upload.php' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'vergeml-media-list',
        plugins_url( 'css/vergeml-media-list.css', VERGEML_FILE ),
        array(),
        vergeml_asset_ver( 'css/vergeml-media-list.css' )
    );

    /*
     *  A cell of ours is never the reason a row is tall.
     *
     *  Written here rather than in the stylesheet because two of the four are
     *  named after taxonomies somebody made up -- `taxonomy-media_category`
     *  on one library, `taxonomy-colour` on the next -- and this is the one
     *  place that knows which columns are ours. The full value goes on the
     *  cell's title in js/vergeml-media-list.js, so an ellipsis never hides
     *  something with no way to read it.
     */
    $cells = array();

    foreach ( vergeml_list_our_columns() as $column ) {
        $cells[] = '.wp-list-table.media td.column-' . $column;
    }

    /*
     *  And a cell of ours never takes more room than it needs.
     *
     *  The table is laid out fixed, so a column without a width takes an equal
     *  share of whatever is left -- which meant that with our four switched on
     *  and nobody else's columns there, each of ours was as wide as the File
     *  column itself, 157px, and the title wrapped beside its own thumbnail
     *  into a 149px row. Nine per cent is about what they had on a crowded
     *  table anyway; it is a ceiling, not a claim. Measured 2026-09-07 on the
     *  box: File 157px -> 318px, tallest row 149px -> 98px.
     */
    $css = implode( ",\n", $cells ) . " {\n\twhite-space: nowrap;\n\toverflow: hidden;\n\ttext-overflow: ellipsis;\n}";

    /*
     *  A width is a floor as well as a ceiling in a fixed table, so it is
     *  worth nine per cent each only while there is room to give. On a table
     *  already carrying eight other plugins' columns it takes 36% away from
     *  them and they tower instead -- measured 2026-09-07 on the box, a row
     *  went from 1,698px to 2,103px. Counted here rather than in the
     *  stylesheet because CSS counts the columns that are hidden too.
     */
    $screen  = get_current_screen();
    $visible = $screen
        ? array_diff( array_keys( (array) get_column_headers( $screen ) ), (array) get_hidden_columns( $screen ) )
        : array();

    if ( $visible && count( $visible ) <= 8 ) {

        $headings = array();

        foreach ( vergeml_list_our_columns() as $column ) {
            $headings[] = '.wp-list-table.media .column-' . $column;
        }

        $css .= "\n\n" . implode( ",\n", $headings ) . " {\n\twidth: 9%;\n}";
    }

    wp_add_inline_style( 'vergeml-media-list', $css );

    wp_enqueue_script(
        'vergeml-media-list',
        plugins_url( 'js/vergeml-media-list.js', VERGEML_FILE ),
        array(),
        vergeml_asset_ver( 'js/vergeml-media-list.js' ),
        true
    );

    wp_localize_script( 'vergeml-media-list', 'vergemlList', array(
        'rows'    => vergeml_list_row_meta(),
        'columns' => vergeml_list_our_columns(),
    ) );
}


/* --------------------------------------------------------- the folder filter */

/**
 *  vergeml_list_is_list_mode
 *
 *  The media library, in list mode. The same test taxonomies.php makes, and
 *  the same reason: a screen that is not this one must be left alone.
 */

function vergeml_list_is_list_mode() {

    global $current_screen;

    if ( ! isset( $current_screen ) || 'upload' !== $current_screen->base ) {
        return false;
    }

    /*
     *  The mode in the request first, and the remembered one only when the
     *  request does not say.
     *
     *  The remembered mode alone was wrong on a library nobody had switched
     *  yet: `get_user_option( 'media_library_mode' )` is unset until somebody
     *  chooses, so it fell back to 'grid' while `?mode=list` was drawing the
     *  list table in front of them -- and the folder dropdown and Move to
     *  folder were both missing. Found on a clean WordPress on 2026-09-07;
     *  every test until then ran as a person who had already been to list
     *  mode, which is exactly the person this bug cannot happen to.
     */
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which of core's own two views is on screen.
    $asked = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : '';

    if ( 'list' === $asked || 'grid' === $asked ) {
        return 'list' === $asked;
    }

    return 'list' === ( get_user_option( 'media_library_mode' ) ? get_user_option( 'media_library_mode' ) : 'grid' );
}


/**
 *  vergeml_Walker_FolderDropdown
 *
 *  A folder and its count, children indented the way core's own category
 *  dropdown indents them -- three non-breaking spaces a level.
 *
 *  The count is the one the filter will actually return: it rolls child
 *  folders up when the library is set to include them, which is the same
 *  reckoning vergeml_backend_parse_tax_query() applies to the query.
 */

/**
 *  vergeml_list_folder_name
 *
 *  A folder's name as a person typed it.
 *
 *  Term names are stored with their entities, so "Client work & co" comes back
 *  as "Client work &amp; co". Core's own category dropdown prints the name
 *  unescaped and gets away with it; escaping it again would put "&amp;amp;" on
 *  the screen. Decoded once and escaped once, which is right either way.
 */

function vergeml_list_folder_name( $name ) {
    return html_entity_decode( (string) $name, ENT_QUOTES, get_bloginfo( 'charset' ) );
}


class vergeml_Walker_FolderDropdown extends Walker_CategoryDropdown {

    function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {

        $pad   = str_repeat( '&nbsp;', $depth * 3 );
        $count = vergeml_get_media_term_count( $category->term_id, $category->term_taxonomy_id );

        $output .= "\t<option class=\"level-$depth\" value=\"" . esc_attr( $category->term_id ) . "\"";

        // Type-juggling causes false matches, so everything is compared as a string.
        if ( (string) $category->term_id === (string) $args['selected'] ) {
            $output .= ' selected="selected"';
        }

        $output .= '>' . $pad . esc_html( sprintf(
            /* translators: 1: folder name, 2: how many files are in it. */
            __( '%1$s (%2$s)', 'vergelabs-media-library' ),
            vergeml_list_folder_name( $category->name ),
            number_format_i18n( $count )
        ) ) . "</option>\n";
    }
}


/**
 *  All folders, in core's own filter bar.
 *
 *  WordPress has filtered a list table by a hierarchical taxonomy this way
 *  since the Posts screen had categories: a dropdown beside All dates and a
 *  Filter button, submitted as a GET. That is the whole mechanism -- the
 *  folder ends up in the URL, so a filtered list can be bookmarked, shared,
 *  and walked back to with the browser's own Back button.
 *
 *  Unfiled is second because it is the one people go looking for. Its value
 *  is `not_in`, which vergeml_backend_parse_tax_query() already reads as
 *  "holds no folder" -- the same answer the tree's Unfiled row gives.
 *
 *  The generic per-taxonomy dropdown in core/taxonomies.php skips the folder
 *  taxonomy so that this is the only folder control in the bar.
 */

add_action( 'restrict_manage_posts', 'vergeml_list_folder_filter', 10, 2 );

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a read-only, shareable filter URL, like every other dropdown in this bar.

function vergeml_list_folder_filter( $post_type, $which ) {

    if ( 'attachment' !== $post_type || ! vergeml_list_is_list_mode() ) {
        return;
    }

    /*
     *  The media list table is not the posts list table: its extra_tablenav()
     *  returns unless $which is 'bar', so a 'top' check draws nothing at all.
     *  Once per request, as core/bulk-terms.php does for the same reason.
     */
    static $rendered = false;

    if ( $rendered ) {
        return;
    }

    $rendered = true;

    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';

    if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
        return;
    }

    global $wp_query;

    // The two spellings of Unfiled: this dropdown's, and the folder tree's URL.
    $unfiled = ( isset( $_REQUEST[ $taxonomy ] ) && 'not_in' === $_REQUEST[ $taxonomy ] )
        || ( isset( $_REQUEST['attachment-filter'] ) && 'uncategorized' === $_REQUEST['attachment-filter'] )
        || ! empty( $_REQUEST['uncategorized'] );

    $selected = $unfiled
        ? 'not_in'
        : ( isset( $wp_query->query[ $taxonomy ] ) ? $wp_query->query[ $taxonomy ] : 0 );

    printf(
        '<label for="%1$s" class="screen-reader-text">%2$s</label>',
        esc_attr( $taxonomy ),
        esc_html__( 'All folders', 'vergelabs-media-library' )
    );

    wp_dropdown_categories( array(
        'show_option_all'   => __( 'All folders', 'vergelabs-media-library' ),
        'show_option_none'  => sprintf(
            /* translators: %s: how many files are in no folder. */
            __( 'Unfiled (%s)', 'vergelabs-media-library' ),
            number_format_i18n( vergeml_count_unassigned( $taxonomy ) )
        ),
        'option_none_value' => 'not_in',
        'taxonomy'          => $taxonomy,
        'name'              => $taxonomy,
        'id'                => $taxonomy,
        'orderby'           => 'name',
        'selected'          => $selected,
        'hierarchical'      => true,
        'hide_empty'        => false,
        'hide_if_empty'     => true,
        'class'             => 'attachment-filters vgml-folder-filter',
        'walker'            => new vergeml_Walker_FolderDropdown(),
    ) );
}

// phpcs:enable WordPress.Security.NonceVerification.Recommended


/* ------------------------------------------------------------ moving files */

/**
 *  vergeml_list_folder_count
 *
 *  A folder's count, worked out once a request.
 *
 *  Both controls on this screen show it -- the filter dropdown and the bulk
 *  menu -- and the count query is deliberately uncached, so without this a
 *  library with thirty folders would run the same sixty queries twice.
 */

function vergeml_list_folder_count( $term ) {

    static $counts = array();

    $id = (int) $term->term_id;

    if ( ! isset( $counts[ $id ] ) ) {
        $counts[ $id ] = (int) vergeml_get_media_term_count( $id, $term->term_taxonomy_id );
    }

    return $counts[ $id ];
}


/**
 *  vergeml_list_folder_options
 *
 *  Every folder as a bulk-action option, parents before their children and
 *  children indented the three non-breaking spaces a level that core's own
 *  category dropdown uses.
 */

function vergeml_list_folder_options( $taxonomy ) {

    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'orderby'    => 'name',
    ) );

    if ( is_wp_error( $terms ) || ! $terms ) {
        return array();
    }

    $children = array();

    foreach ( $terms as $term ) {
        $children[ (int) $term->parent ][] = $term;
    }

    return vergeml_list_folder_branch( $children, 0, 0 );
}


function vergeml_list_folder_branch( $children, $parent, $depth ) {

    $out = array();

    if ( empty( $children[ $parent ] ) ) {
        return $out;
    }

    foreach ( $children[ $parent ] as $term ) {

        $out[ 'vergeml-move-' . (int) $term->term_id ] = str_repeat( '&nbsp;', $depth * 3 ) . esc_html( sprintf(
            /* translators: 1: folder name, 2: how many files are in it. */
            __( '%1$s (%2$s)', 'vergelabs-media-library' ),
            vergeml_list_folder_name( $term->name ),
            number_format_i18n( vergeml_list_folder_count( $term ) )
        ) );

        $out += vergeml_list_folder_branch( $children, (int) $term->term_id, $depth + 1 );
    }

    return $out;
}


/**
 *  Move to folder…, in Bulk actions.
 *
 *  This is how a WordPress list table moves things, and it is what replaces
 *  dragging a row onto the tree -- there is no tree in list mode to drop on.
 *  Grid mode keeps both.
 *
 *  A group rather than one action and a second dropdown beside it: core has
 *  taken an array here since 5.6 and renders it as an optgroup, so the folder
 *  and the action are one choice and the bar gains no control.
 */

add_filter( 'bulk_actions-upload', 'vergeml_list_move_action' );

function vergeml_list_move_action( $actions ) {

    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';

    if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
        return $actions;
    }

    $folders = vergeml_list_folder_options( $taxonomy );

    if ( ! $folders ) {
        return $actions;
    }

    $actions[ __( 'Move to folder…', 'vergelabs-media-library' ) ] = $folders;

    return $actions;
}


/**
 *  vergeml_list_handle_move
 *
 *  Core has checked the bulk-media nonce and gathered the ticked rows before
 *  this runs, so only what was ticked is here. The capability is still asked
 *  per file: reaching this screen is not the right to edit everything on it.
 *
 *  A move replaces the folders a file is in rather than adding one, which is
 *  the same reckoning the tree's own move makes.
 */

add_filter( 'handle_bulk_actions-upload', 'vergeml_list_handle_move', 10, 3 );

function vergeml_list_handle_move( $location, $doaction, $post_ids ) {

    if ( ! preg_match( '/^vergeml-move-([0-9]+)$/', (string) $doaction, $found ) ) {
        return $location;
    }

    $taxonomy = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';
    $term     = $taxonomy ? get_term( (int) $found[1], $taxonomy ) : null;

    if ( ! $term instanceof WP_Term ) {
        return $location;
    }

    $moved = 0;

    wp_defer_term_counting( true );

    foreach ( (array) $post_ids as $post_id ) {

        $post_id = absint( $post_id );

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            continue;
        }

        $result = wp_set_object_terms( $post_id, array( (int) $term->term_id ), $taxonomy, false );

        if ( is_wp_error( $result ) ) {
            continue;
        }

        $moved++;
    }

    wp_defer_term_counting( false );

    wp_cache_delete( 'vergeml_unassigned_' . $taxonomy, 'vergeml' );

    return add_query_arg(
        array(
            'vgml_moved' => $moved,
            /*
             *  Encoded here on purpose: add_query_arg() does not encode its
             *  values, so a folder called "Client work & co" would end the
             *  query string at the ampersand. PHP decodes it once out of
             *  $_GET, so the read side needs no decode of its own.
             */
            'vgml_to'    => rawurlencode( vergeml_list_folder_name( $term->name ) ),
        ),
        remove_query_arg( array( 'vgml_moved', 'vgml_to' ), $location )
    );
}


/**
 *  What happened, in the words the screen uses for it.
 */

add_action( 'admin_notices', 'vergeml_list_move_notice' );

function vergeml_list_move_notice() {

    $screen = get_current_screen();

    if ( ! $screen || 'upload' !== $screen->id ) {
        return;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading our own redirect result to display it.
    if ( ! isset( $_GET['vgml_moved'] ) ) {
        return;
    }

    $moved = absint( $_GET['vgml_moved'] );
    $to    = isset( $_GET['vgml_to'] ) ? sanitize_text_field( wp_unslash( $_GET['vgml_to'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html( sprintf(
            /* translators: 1: how many files were moved, 2: the folder they went to. */
            _n( '%1$s file moved to %2$s.', '%1$s files moved to %2$s.', $moved, 'vergelabs-media-library' ),
            number_format_i18n( $moved ),
            $to
        ) )
    );
}
