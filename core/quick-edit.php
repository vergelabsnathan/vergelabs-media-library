<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  Editing a file without leaving the list.
 *
 *  Changing one picture's alt text meant opening its own screen, changing a
 *  field, saving, and going back — a full page load each way, per file. On a
 *  library where somebody is fixing twenty of them that is forty page loads to
 *  type twenty sentences, and it is why the plugin and the library feel like
 *  two places rather than one.
 *
 *  WordPress already has this pattern and people already know it: Quick Edit,
 *  on the posts list. The media list is the one screen core never gave it to.
 *  So this is not a new idea being introduced, it is a familiar one arriving
 *  where it was missing — which is the difference between a feature somebody
 *  has to learn and one they simply use.
 *
 *  Two fields, deliberately. The title and the alt text are what our own
 *  screens talk about and what somebody is in the list to fix. Captions,
 *  descriptions and the rest stay on the file's own screen, where there is
 *  room for them.
 *
 *  What is edited here is locked, and that is the point: core/ai-index.php
 *  watches both fields and marks them as a person's the moment they are saved
 *  by hand. Nothing this plugin writes will paint over them afterwards. That
 *  is why the save deliberately does NOT set the pipeline's writing flag --
 *  the one place in the codebase where not setting it is the correct thing to
 *  do.
 */

add_filter( 'media_row_actions', 'vergeml_quick_edit_action', 9, 2 );

function vergeml_quick_edit_action( $actions, $post ) {

    if ( ! current_user_can( 'edit_post', $post->ID ) ) {
        return $actions;
    }

    $alt = (string) get_post_meta( $post->ID, '_wp_attachment_image_alt', true );

    /*
     *  The values ride on the link rather than being fetched when it is
     *  clicked. They are already on this page -- the list has just rendered
     *  them -- and a request per click to ask the server what it just sent is
     *  a round trip for nothing.
     */
    $actions = array_merge(
        array(
            'vergeml_quick' => sprintf(
                '<a href="#" class="vgml-quick-edit" data-id="%d" data-title="%s" data-alt="%s">%s</a>',
                (int) $post->ID,
                esc_attr( $post->post_title ),
                esc_attr( $alt ),
                esc_html__( 'Quick edit', 'vergelabs-media-library' )
            ),
        ),
        $actions
    );

    return $actions;
}


/**
 *  The form, once, at the foot of the page.
 *
 *  One template the script clones into whichever row was clicked, rather than
 *  a form per row: a list of forty files would otherwise carry forty hidden
 *  forms, which is forty times the markup for the one somebody opens.
 */

add_action( 'admin_footer-upload.php', 'vergeml_quick_edit_template' );

function vergeml_quick_edit_template() {

    if ( ! current_user_can( 'upload_files' ) ) {
        return;
    }

    ?>
    <template id="vgml-quick-template">
        <tr class="vgml-quick-row">
            <td colspan="20">
                <div class="vgml-quick">

                    <p class="vgml-quick-field">
                        <label for="vgml-quick-title"><?php esc_html_e( 'Title', 'vergelabs-media-library' ); ?></label>
                        <input type="text" id="vgml-quick-title" class="vgml-quick-title" value="">
                    </p>

                    <p class="vgml-quick-field">
                        <label for="vgml-quick-alt"><?php esc_html_e( 'Alt text', 'vergelabs-media-library' ); ?></label>
                        <textarea id="vgml-quick-alt" class="vgml-quick-alt" rows="2"></textarea>
                        <span class="vgml-quick-help"><?php esc_html_e( 'What a screen reader reads out instead of showing the picture. Saving here marks it as yours, and nothing this plugin writes will replace it.', 'vergelabs-media-library' ); ?></span>
                    </p>

                    <p class="vgml-quick-actions">
                        <button type="button" class="button button-primary vgml-quick-save"><?php esc_html_e( 'Save', 'vergelabs-media-library' ); ?></button>
                        <button type="button" class="button vgml-quick-cancel"><?php esc_html_e( 'Cancel', 'vergelabs-media-library' ); ?></button>
                        <span class="vgml-quick-note"></span>
                    </p>

                </div>
            </td>
        </tr>
    </template>
    <?php
}


add_action( 'admin_enqueue_scripts', 'vergeml_quick_edit_assets' );

function vergeml_quick_edit_assets( $hook ) {

    if ( 'upload.php' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'vergeml-quick-edit',
        plugins_url( 'js/vergeml-quick-edit.js', VERGEML_FILE ),
        array( 'wp-api-fetch' ),
        vergeml_asset_ver( 'js/vergeml-quick-edit.js' ),
        true
    );

    wp_localize_script( 'vergeml-quick-edit', 'vergemlQuick', array(
        'saving' => __( 'Saving…', 'vergelabs-media-library' ),
        'saved'  => __( 'Saved.', 'vergelabs-media-library' ),
        'failed' => __( 'That did not save. Try the file’s own screen.', 'vergelabs-media-library' ),
        'yours'  => __( 'You wrote this', 'vergelabs-media-library' ),
    ) );
}


/* ------------------------------------------------------------------ the API */

add_action( 'rest_api_init', 'vergeml_quick_edit_route' );

function vergeml_quick_edit_route() {

    register_rest_route( VERGEML_REST_NS, '/file/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'vergeml_quick_edit_save',
        'permission_callback' => function ( WP_REST_Request $request ) {
            return current_user_can( 'edit_post', (int) $request['id'] );
        },
        'args'                => array(
            'title' => array( 'type' => 'string' ),
            'alt'   => array( 'type' => 'string' ),
        ),
    ) );
}


function vergeml_quick_edit_save( WP_REST_Request $request ) {

    $id   = (int) $request['id'];
    $post = get_post( $id );

    if ( ! $post || 'attachment' !== $post->post_type ) {
        return new WP_Error( 'vergeml_not_a_file', __( 'That is not a file in the library.', 'vergelabs-media-library' ), array( 'status' => 404 ) );
    }

    /*
     *  No writing flag. Everywhere else in this plugin the flag is set so the
     *  index does not mistake a pipeline write for a person typing; here a
     *  person IS typing, and the lock that follows is exactly what should
     *  happen. Setting it would quietly hand their sentence back to the next
     *  describe run.
     */
    if ( null !== $request->get_param( 'title' ) ) {

        $title = sanitize_text_field( (string) $request->get_param( 'title' ) );

        if ( $title !== $post->post_title ) {
            wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
        }
    }

    if ( null !== $request->get_param( 'alt' ) ) {

        $alt = sanitize_text_field( (string) $request->get_param( 'alt' ) );

        // Emptying it is how somebody hands the field back, and the index
        // treats it that way -- so it is stored rather than skipped.
        update_post_meta( $id, '_wp_attachment_image_alt', $alt );
    }

    $fresh = get_post( $id );

    return rest_ensure_response( array(
        'id'    => $id,
        'title' => $fresh->post_title,
        'alt'   => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
    ) );
}
