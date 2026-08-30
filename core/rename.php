<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  Naming a file after what is in it.
 *
 *  "Photo 498" tells nobody anything. "Red Synthesizer with Controls" is the
 *  same file, findable by search, readable in a list, and useful in the
 *  attachment title WordPress already shows everywhere.
 *
 *  Nothing new is generated for this. Every description already comes back
 *  with a short title beside the caption and the alt text, and the index has
 *  been storing it since the column was added -- 510 of 510 rows on the test
 *  library have one. Nothing had ever read it. This is a feature that was
 *  already paid for.
 *
 *  The TITLE, not the file name. Renaming the file on disk changes the URL,
 *  and every img src, cached page, CDN copy and hard-coded link that already
 *  points at it breaks unless a redirect is written for each one. That is a
 *  different feature with a different risk, and it is not this one.
 *
 *  Three rules, and they are the whole design:
 *
 *   - Never over a title somebody wrote. core/ai-index.php locks the title
 *     field the moment a person edits it, and a locked title is skipped.
 *   - Never silently. It is offered on a selection, per file, or as a run
 *     you start, and never happens on its own.
 *   - Always reversible. The previous title is kept, so one click puts every
 *     one of them back.
 */

/** Where the old title waits, in case somebody wants it back. */
const VERGEML_RENAME_META = '_vergeml_title_before';

/** The option holding the last run, so undo knows what it is undoing. */
const VERGEML_RENAME_OPTION = 'vergeml_rename_last';


/**
 *  Whether this file can be renamed, and what to.
 *
 *  Returns the new title, or '' for any reason it should be left alone. One
 *  function so the count on the button and the work the button does can never
 *  disagree about which files are eligible.
 */

function vergeml_rename_title_for( $attachment_id ) {

    if ( ! function_exists( 'vergeml_index_get' ) ) {
        return '';
    }

    $row = vergeml_index_get( (int) $attachment_id );

    if ( ! $row || '' !== (string) $row['error'] ) {
        return '';
    }

    $title = trim( (string) $row['title'] );

    if ( '' === $title ) {
        return '';
    }

    // Somebody named this themselves. That is the end of it.
    if ( isset( $row['locked'] ) && in_array( 'title', (array) $row['locked'], true ) ) {
        return '';
    }

    $post = get_post( (int) $attachment_id );

    if ( ! $post || $post->post_title === $title ) {
        return '';
    }

    return $title;
}


/**
 *  How many files a run would rename.
 *
 *  Counted rather than estimated, because the number is on a button somebody
 *  presses. One query for the candidates and then the same eligibility test
 *  every other caller uses.
 */

function vergeml_rename_pending( $limit = 0 ) {

    global $wpdb;

    $limit = max( 0, (int) $limit );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- this plugin's own table.
    $ids = $wpdb->get_col(
        "SELECT i.attachment_id
           FROM {$wpdb->vergeml_ai_index} i
     INNER JOIN {$wpdb->posts} p ON p.ID = i.attachment_id
          WHERE i.error = '' AND i.title <> '' AND p.post_title <> i.title
       ORDER BY i.attachment_id ASC"
    );
    // phpcs:enable

    $out = array();

    foreach ( $ids as $id ) {

        if ( '' === vergeml_rename_title_for( $id ) ) {
            continue;
        }

        $out[] = (int) $id;

        if ( $limit > 0 && count( $out ) >= $limit ) {
            break;
        }
    }

    return $out;
}


/**
 *  Rename some files, and remember what they were called.
 *
 *  The writing flag is set around the update for the same reason every other
 *  pipeline write does it: core/ai-index.php watches post titles and treats a
 *  change as a person naming the file. Without this, renaming a file would
 *  immediately lock it against ever being renamed again.
 */

function vergeml_rename_apply( $ids ) {

    $done = array();

    foreach ( (array) $ids as $id ) {

        $id    = (int) $id;
        $title = vergeml_rename_title_for( $id );

        if ( '' === $title ) {
            continue;
        }

        $before = (string) get_post( $id )->post_title;

        if ( function_exists( 'vergeml_index_writing' ) ) {
            vergeml_index_writing( true );
        }

        // Only the first time: renaming twice must not lose the name the file
        // arrived with.
        if ( '' === (string) get_post_meta( $id, VERGEML_RENAME_META, true ) ) {
            update_post_meta( $id, VERGEML_RENAME_META, $before );
        }

        wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );

        if ( function_exists( 'vergeml_index_writing' ) ) {
            vergeml_index_writing( false );
        }

        $done[] = $id;
    }

    if ( $done ) {
        update_option( VERGEML_RENAME_OPTION, array(
            'ids'  => $done,
            'when' => time(),
        ), false );
    }

    return $done;
}


/**
 *  Put them back.
 *
 *  Only the files of the last run, and only where the stored original is still
 *  the thing to go back to. A file somebody has renamed by hand since is left
 *  alone -- undo is for taking back what this did, not for overwriting what
 *  somebody did afterwards.
 */

function vergeml_rename_undo() {

    $last = get_option( VERGEML_RENAME_OPTION, array() );

    if ( ! is_array( $last ) || empty( $last['ids'] ) ) {
        return array();
    }

    $back = array();

    foreach ( (array) $last['ids'] as $id ) {

        $id     = (int) $id;
        $before = (string) get_post_meta( $id, VERGEML_RENAME_META, true );
        $post   = get_post( $id );

        if ( ! $post || '' === $before ) {
            continue;
        }

        $row = function_exists( 'vergeml_index_get' ) ? vergeml_index_get( $id ) : null;

        // Still wearing the name we gave it, or somebody has moved on.
        if ( ! $row || $post->post_title !== (string) $row['title'] ) {
            continue;
        }

        if ( function_exists( 'vergeml_index_writing' ) ) {
            vergeml_index_writing( true );
        }

        wp_update_post( array( 'ID' => $id, 'post_title' => $before ) );
        delete_post_meta( $id, VERGEML_RENAME_META );

        if ( function_exists( 'vergeml_index_writing' ) ) {
            vergeml_index_writing( false );
        }

        $back[] = $id;
    }

    delete_option( VERGEML_RENAME_OPTION );

    return $back;
}


/* --------------------------------------------------------------- the list */

/**
 *  A link on each row, beside Edit and Delete.
 *
 *  Where somebody is already looking at the file name they do not like. Only
 *  on rows where it would do something: an action that greys out or does
 *  nothing is worse than an action that is not there.
 */

add_filter( 'media_row_actions', 'vergeml_rename_row_action', 10, 2 );

function vergeml_rename_row_action( $actions, $post ) {

    if ( ! current_user_can( 'edit_post', $post->ID ) ) {
        return $actions;
    }

    $title = vergeml_rename_title_for( $post->ID );

    if ( '' === $title ) {
        return $actions;
    }

    $url = wp_nonce_url(
        admin_url( 'admin-post.php?action=vergeml_rename&id=' . (int) $post->ID ),
        'vergeml_rename_' . (int) $post->ID
    );

    $actions['vergeml_rename'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url( $url ),
        esc_html(
            sprintf(
                /* translators: %s: the name the file would be given. */
                __( 'Rename to “%s”', 'vergelabs-media-library' ),
                $title
            )
        )
    );

    return $actions;
}


add_action( 'admin_post_vergeml_rename', 'vergeml_rename_handle_one' );

function vergeml_rename_handle_one() {

    $id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

    if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
        wp_die( esc_html__( 'You cannot rename that file.', 'vergelabs-media-library' ) );
    }

    check_admin_referer( 'vergeml_rename_' . $id );

    $done = vergeml_rename_apply( array( $id ) );

    wp_safe_redirect( add_query_arg( 'vgml_renamed', count( $done ), wp_get_referer() ?: admin_url( 'upload.php?mode=list' ) ) );
    exit;
}


/**
 *  And on a selection, which is the way anybody with more than ten files
 *  would want to do it.
 */

add_filter( 'bulk_actions-upload', 'vergeml_rename_bulk_action' );

function vergeml_rename_bulk_action( $actions ) {
    $actions['vergeml_rename'] = __( 'Rename from the picture', 'vergelabs-media-library' );
    return $actions;
}


add_filter( 'handle_bulk_actions-upload', 'vergeml_rename_handle_bulk', 10, 3 );

function vergeml_rename_handle_bulk( $redirect, $action, $ids ) {

    if ( 'vergeml_rename' !== $action ) {
        return $redirect;
    }

    $allowed = array();

    foreach ( (array) $ids as $id ) {
        if ( current_user_can( 'edit_post', (int) $id ) ) {
            $allowed[] = (int) $id;
        }
    }

    return add_query_arg( 'vgml_renamed', count( vergeml_rename_apply( $allowed ) ), $redirect );
}


/**
 *  What happened, and the way back.
 *
 *  The undo lives in the notice rather than on a settings screen, because the
 *  moment somebody wants it is the moment they read the notice.
 */

add_action( 'admin_notices', 'vergeml_rename_notice' );

function vergeml_rename_notice() {

    if ( ! isset( $_GET['vgml_renamed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading a count to display, changing nothing.
        return;
    }

    $n = (int) $_GET['vgml_renamed']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ( $n < 1 ) {
        printf(
            '<div class="notice notice-info is-dismissible"><p>%s</p></div>',
            esc_html__( 'Nothing to rename — those files either have no description yet, or you named them yourself.', 'vergelabs-media-library' )
        );
        return;
    }

    $undo = wp_nonce_url( admin_url( 'admin-post.php?action=vergeml_rename_undo' ), 'vergeml_rename_undo' );

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
        esc_html(
            sprintf(
                /* translators: %s: how many files were renamed. */
                _n( '%s file renamed after what is in it.', '%s files renamed after what is in them.', $n, 'vergelabs-media-library' ),
                number_format_i18n( $n )
            )
        ),
        esc_url( $undo ),
        esc_html__( 'Put the old names back', 'vergelabs-media-library' )
    );
}


add_action( 'admin_post_vergeml_rename_undo', 'vergeml_rename_handle_undo' );

function vergeml_rename_handle_undo() {

    if ( ! current_user_can( 'upload_files' ) ) {
        wp_die( esc_html__( 'You cannot do that.', 'vergelabs-media-library' ) );
    }

    check_admin_referer( 'vergeml_rename_undo' );

    $back = vergeml_rename_undo();

    wp_safe_redirect( add_query_arg( 'vgml_renamed_back', count( $back ), wp_get_referer() ?: admin_url( 'upload.php?mode=list' ) ) );
    exit;
}


add_action( 'admin_notices', 'vergeml_rename_undo_notice' );

function vergeml_rename_undo_notice() {

    if ( ! isset( $_GET['vgml_renamed_back'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $n = (int) $_GET['vgml_renamed_back']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html(
            sprintf(
                /* translators: %s: how many files got their old name back. */
                _n( '%s file has its old name back.', '%s files have their old names back.', $n, 'vergelabs-media-library' ),
                number_format_i18n( $n )
            )
        )
    );
}
