<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  Bulk categorise from the media list.
 *
 *  Filing fifty images one at a time is the reason people give up on
 *  organising a media library. This adds two entries to the list view's Bulk
 *  actions menu, plus a term to apply them to.
 *
 *  Built entirely on core's own bulk-action hooks. WordPress collects the
 *  selection, checks the bulk-media nonce, and hands us the ids; we do the term
 *  work and hand back a redirect. Nothing in the list table is replaced, which
 *  is why this keeps working when the table changes.
 *
 *  Grid view is not covered: core has no bulk-action mechanism there, and
 *  reaching it would mean patching media views again.
 *
 *  @since    2.10
 */


/**
 *  vergeml_bulk_term_choices
 *
 *  Every term of every taxonomy assigned to media, grouped by taxonomy.
 *  Returns an empty array when there is nothing to assign, which switches the
 *  whole feature off rather than showing an empty menu.
 */

function vergeml_bulk_term_choices() {

    $taxonomies = vergeml_assignable_media_taxonomies();
    $choices    = array();

    foreach ( $taxonomies as $taxonomy ) {

        if ( ! $taxonomy->show_ui )
            continue;

        $terms = get_terms( array(
            'taxonomy'   => $taxonomy->name,
            'hide_empty' => false,
            'number'     => 200,
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) )
            continue;

        $choices[ $taxonomy->name ] = array(
            'label' => $taxonomy->labels->name,
            'terms' => $terms,
        );
    }

    return $choices;
}


/**
 *  vergeml_bulk_actions
 *
 *  Two actions rather than one per term: a site with two hundred categories
 *  would otherwise get a two hundred item menu. Which term is picked from the
 *  select rendered beside it.
 */

add_filter( 'bulk_actions-upload', 'vergeml_bulk_actions' );

function vergeml_bulk_actions( $actions ) {

    if ( empty( vergeml_bulk_term_choices() ) )
        return $actions;

    $actions['vergeml-add-term']    = __( 'Add to selected term', 'vergelabs-media-library' );
    $actions['vergeml-remove-term'] = __( 'Remove from selected term', 'vergelabs-media-library' );

    return $actions;
}


/**
 *  vergeml_bulk_term_select
 *
 *  The term the two actions above apply to. Rendered into the list table's own
 *  form, so it posts along with the selection and core's nonce.
 */

add_action( 'restrict_manage_posts', 'vergeml_bulk_term_select', 20, 2 );

function vergeml_bulk_term_select( $post_type, $which ) {

    if ( 'attachment' !== $post_type )
        return;

    /*
     *  The media list table is not the posts list table. Its extra_tablenav()
     *  returns unless $which is 'bar', so a 'top' check here renders nothing at
     *  all. Rather than swap one magic string for another, render once per
     *  request and let the id stay unique wherever core decides to call this.
     */

    static $rendered = false;

    if ( $rendered )
        return;

    $rendered = true;

    $choices = vergeml_bulk_term_choices();

    if ( empty( $choices ) )
        return;

    ?>
    <label for="vergeml_bulk_term" class="screen-reader-text"><?php esc_html_e( 'Term to add or remove in bulk', 'vergelabs-media-library' ); ?></label>
    <select name="vergeml_bulk_term" id="vergeml_bulk_term">
        <option value=""><?php esc_html_e( '— Term for bulk actions —', 'vergelabs-media-library' ); ?></option>
        <?php foreach ( $choices as $taxonomy => $group ) : ?>
            <optgroup label="<?php echo esc_attr( $group['label'] ); ?>">
                <?php foreach ( $group['terms'] as $term ) : ?>
                    <option value="<?php echo esc_attr( $taxonomy . ':' . $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
    <?php
}


/**
 *  vergeml_handle_bulk_terms
 *
 *  Core has already verified the bulk-media nonce and gathered the selection
 *  before this runs. Capability is still checked per item, because the ability
 *  to reach this screen is not the ability to edit every file on it.
 */

add_filter( 'handle_bulk_actions-upload', 'vergeml_handle_bulk_terms', 10, 3 );

function vergeml_handle_bulk_terms( $location, $doaction, $post_ids ) {

    if ( 'vergeml-add-term' !== $doaction && 'vergeml-remove-term' !== $doaction )
        return $location;

    // Core's upload.php checked this before calling the filter; said again here so the handler stands on its own.
    check_admin_referer( 'bulk-media' );

    $location = remove_query_arg( array( 'vergeml_done', 'vergeml_skipped', 'vergeml_term', 'vergeml_op' ), $location );

    $raw = isset( $_REQUEST['vergeml_bulk_term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['vergeml_bulk_term'] ) ) : '';

    if ( '' === $raw || false === strpos( $raw, ':' ) )
        return add_query_arg( 'vergeml_op', 'noterm', $location );

    list( $taxonomy, $term_id ) = array_pad( explode( ':', $raw, 2 ), 2, '' );

    $taxonomy = sanitize_key( $taxonomy );
    $term_id  = absint( $term_id );
    $term     = $term_id ? get_term( $term_id, $taxonomy ) : null;

    if ( ! $term || is_wp_error( $term ) || ! in_array( $taxonomy, get_object_taxonomies( 'attachment' ), true ) || ( function_exists( 'vergeml_private_is_hidden' ) && vergeml_private_is_hidden( $term_id ) ) )
        return add_query_arg( 'vergeml_op', 'noterm', $location );

    // Core's own rule for setting terms on a post.
    $tax_object = get_taxonomy( $taxonomy );

    if ( ! $tax_object || ! current_user_can( $tax_object->cap->assign_terms ) )
        return add_query_arg( 'vergeml_op', 'noterm', $location );

    $done    = 0;
    $skipped = 0;

    foreach ( (array) $post_ids as $post_id ) {

        $post_id = absint( $post_id );

        if ( ! $post_id || 'attachment' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            $skipped++;
            continue;
        }

        if ( 'vergeml-add-term' === $doaction ) {
            // append, so a bulk add never wipes terms someone set by hand
            $result = wp_set_object_terms( $post_id, array( $term->term_id ), $taxonomy, true );
        }
        else {
            $result = wp_remove_object_terms( $post_id, array( $term->term_id ), $taxonomy );
        }

        if ( is_wp_error( $result ) ) {
            $skipped++;
            continue;
        }

        $done++;
    }

    $location = add_query_arg(
        array(
            'vergeml_op'      => 'vergeml-add-term' === $doaction ? 'added' : 'removed',
            'vergeml_done'    => $done,
            'vergeml_skipped' => $skipped,
            /*
             *  Encoded here on purpose. add_query_arg() does not encode its
             *  values -- build_query() passes $urlencode = false -- so a term
             *  named "Client work & co" would end the query string at the
             *  ampersand and arrive truncated. PHP decodes it once out of
             *  $_GET, so the read side needs no decode of its own.
             */
            'vergeml_term'    => rawurlencode( $term->name ),
        ),
        $location
    );

    return $location;
}


/**
 *  vergeml_bulk_terms_notice
 *
 *  Says what happened, including what did not. A silent partial success is
 *  worse than a number that does not match what was selected.
 */

add_action( 'admin_notices', 'vergeml_bulk_terms_notice' );

function vergeml_bulk_terms_notice() {

    $screen = get_current_screen();

    if ( ! $screen || 'upload' !== $screen->id )
        return;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading our own redirect result to display it.
    $op = isset( $_GET['vergeml_op'] ) ? sanitize_key( wp_unslash( $_GET['vergeml_op'] ) ) : '';

    if ( '' === $op )
        return;

    if ( 'noterm' === $op ) {
        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            esc_html__( 'Pick a term next to the Bulk actions menu first, then apply the action again.', 'vergelabs-media-library' )
        );
        return;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading our own redirect result to display it.
    $done    = isset( $_GET['vergeml_done'] ) ? absint( $_GET['vergeml_done'] ) : 0;
    $skipped = isset( $_GET['vergeml_skipped'] ) ? absint( $_GET['vergeml_skipped'] ) : 0;
    $term    = isset( $_GET['vergeml_term'] ) ? sanitize_text_field( wp_unslash( $_GET['vergeml_term'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $message = 'added' === $op
        /* translators: 1: number of media items, 2: term name */
        ? sprintf( _n( '%1$s item added to %2$s.', '%1$s items added to %2$s.', $done, 'vergelabs-media-library' ), number_format_i18n( $done ), $term )
        /* translators: 1: number of media items, 2: term name */
        : sprintf( _n( '%1$s item removed from %2$s.', '%1$s items removed from %2$s.', $done, 'vergelabs-media-library' ), number_format_i18n( $done ), $term );

    if ( $skipped ) {
        $message .= ' ' . sprintf(
            /* translators: %s: number of media items */
            _n( '%s was skipped because you cannot edit it.', '%s were skipped because you cannot edit them.', $skipped, 'vergelabs-media-library' ),
            number_format_i18n( $skipped )
        );
    }

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html( $message )
    );
}
