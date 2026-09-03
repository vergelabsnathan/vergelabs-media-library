<?php
/**
 *  The Licence screen.
 *
 *  One place for the thing that used to be a row inside the AI screen: which
 *  licence this site uses, what is on it, and the two ways to change it --
 *  the handshake with vergelabsmedia.com, or a pasted key. A setting somebody
 *  touches once, on its own tab, where the settings live.
 *
 *  @package VergeLabs_Media_Library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'vergeml_licence_menu', 22 );

function vergeml_licence_menu() {
    add_submenu_page(
        VERGEML_MENU,
        __( 'Licence', 'vergelabs-media-library' ),
        __( 'Licence', 'vergelabs-media-library' ),
        'manage_options',
        'media-licence',
        'vergeml_licence_page'
    );
}


/* ------------------------------------------------------------ saving a key */

add_action( 'admin_post_vergeml_licence_save', 'vergeml_licence_save' );

function vergeml_licence_save() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to do that.', 'vergelabs-media-library' ) );
    }
    check_admin_referer( 'vergeml_licence_save' );

    $settings = get_option( 'vergeml_ai', array() );
    $settings = is_array( $settings ) ? $settings : array();
    $result   = 'unchanged';

    if ( ! empty( $_POST['vergeml_licence_remove'] ) ) {
        $old = vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
        if ( '' !== $old ) {
            // Give the seat back on the service; best effort.
            wp_remote_post(
                vergeml_ai_service_url() . '/licence',
                array(
                    'timeout' => 8,
                    'headers' => array( 'Content-Type' => 'application/json' ),
                    'body'    => wp_json_encode( array( 'key' => $old, 'site' => home_url(), 'action' => 'deactivate' ) ),
                )
            );
        }
        $settings['license_key'] = '';
        update_option( 'vergeml_ai', $settings, false );
        delete_option( 'vergeml_ai_credits' );
        $result = 'removed';
    } else {
        $key = isset( $_POST['vergeml_licence_key'] ) ? sanitize_text_field( wp_unslash( $_POST['vergeml_licence_key'] ) ) : '';
        if ( '' !== $key ) {
            $settings['license_key'] = vergeml_ai_seal( $key );
            update_option( 'vergeml_ai', $settings, false );
            $check  = vergeml_ai_refresh_credits( true );
            $state  = function_exists( 'vergeml_ai_credits_state' ) ? vergeml_ai_credits_state() : 'ok';
            $result = ( null === $check && 'rejected' === $state ) ? 'rejected' : 'saved';
        }
    }

    wp_safe_redirect( admin_url( 'admin.php?page=media-licence&vergeml_licence=' . $result ) );
    exit;
}


/* ------------------------------------------------------------- the screen */

function vergeml_licence_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $settings  = vergeml_ai_settings();
    $key       = vergeml_ai_unseal( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
    $connected = '' !== $key;
    $locked    = ! empty( $settings['network_locked'] );
    $network   = ! empty( $settings['from_network'] );

    // Fresh numbers every time this screen opens: it is the one place they matter.
    if ( $connected ) {
        vergeml_ai_refresh_credits( true );
    }
    $credits = get_option( 'vergeml_ai_credits', array() );
    $credits = is_array( $credits ) ? $credits : array();
    $state   = function_exists( 'vergeml_ai_credits_state' ) ? vergeml_ai_credits_state() : 'ok';
    $left    = isset( $credits['remaining'] ) ? (int) $credits['remaining'] : null;
    $plan    = isset( $credits['plan'] ) ? (string) $credits['plan'] : '';
    $connect = function_exists( 'vergeml_connect_start_url' ) ? vergeml_connect_start_url() : '';

    $plans = array(
        'single'   => __( 'Single site', 'vergelabs-media-library' ),
        'five'     => __( 'Five sites', 'vergelabs-media-library' ),
        'agency'   => __( 'Agency', 'vergelabs-media-library' ),
        'lifetime' => __( 'Lifetime', 'vergelabs-media-library' ),
        'credits'  => __( 'Credits', 'vergelabs-media-library' ),
    );

    if ( $connected ) {
        if ( 'rejected' === $state ) {
            $chip = '<span class="vgml-home-counts vgml-is-bad">' . esc_html__( 'Licence not recognised', 'vergelabs-media-library' ) . '</span>';
        } elseif ( 'unreachable' === $state ) {
            $chip = '<span class="vgml-home-counts vgml-is-dim">' . esc_html__( 'Connected · service not reached', 'vergelabs-media-library' ) . '</span>';
        } else {
            $chip = '<span class="vgml-home-counts">' . esc_html( sprintf(
                /* translators: 1: the last four characters of the key, 2: credits left */
                __( 'Connected · licence …%1$s · %2$s credits', 'vergelabs-media-library' ),
                substr( $key, -4 ),
                null === $left ? '—' : number_format_i18n( $left )
            ) ) . '</span>';
        }
    } else {
        $chip = '<span class="vgml-home-counts vgml-is-dim">' . esc_html__( 'Not connected', 'vergelabs-media-library' ) . '</span>';
    }

    ?>
    <div class="wrap vgml-home vgml-licence">

        <?php
        echo vergeml_pg_head( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Licence', 'vergelabs-media-library' ),
            __( 'This site\'s connection to VergeLabs. Images go to the service only to be described; nothing else leaves your site.', 'vergelabs-media-library' ),
            $chip
        );

        if ( isset( $_GET['vergeml_licence'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a notice after a redirect, no action taken.
            $r = sanitize_key( wp_unslash( $_GET['vergeml_licence'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $m = array(
                'saved'    => array( 'success', __( 'Licence saved. This site is connected.', 'vergelabs-media-library' ) ),
                'rejected' => array( 'error', __( 'That key was not recognised by the service. It is saved, but nothing can be described with it until it is.', 'vergelabs-media-library' ) ),
                'removed'  => array( 'success', __( 'The licence was removed from this site.', 'vergelabs-media-library' ) ),
            );
            if ( isset( $m[ $r ] ) ) {
                printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $m[ $r ][0] ), esc_html( $m[ $r ][1] ) );
            }
        }

        // The handshake's own notices (connected / could not verify), and the
        // invitation when there is no key at all.
        if ( function_exists( 'vergeml_connect_banner' ) ) {
            vergeml_connect_banner();
        }

        echo vergeml_pg_card_open( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Connection', 'vergelabs-media-library' ),
            array(
                'action_html' => '<a class="button" href="https://vergelabsmedia.com/account" target="_blank" rel="noopener">'
                    . esc_html__( 'Your account', 'vergelabs-media-library' ) . '</a> '
                    . '<a class="button" href="https://vergelabsmedia.com/#pricing" target="_blank" rel="noopener">'
                    . esc_html__( 'Get credits', 'vergelabs-media-library' ) . '</a>',
                'rows'        => true,
            )
        );

        if ( $connected ) {
            echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                __( 'Licence', 'vergelabs-media-library' ),
                $network
                    ? __( 'Set by the network administrator for every site.', 'vergelabs-media-library' )
                    : __( 'The last four characters match what your account page shows.', 'vergelabs-media-library' ),
                '<code>…' . esc_html( substr( $key, -4 ) ) . '</code>'
            );
            echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                __( 'Plan', 'vergelabs-media-library' ),
                '',
                esc_html( isset( $plans[ $plan ] ) ? $plans[ $plan ] : ( '' === $plan ? '—' : ucfirst( $plan ) ) )
            );
            echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                __( 'Credits', 'vergelabs-media-library' ),
                function_exists( 'vergeml_ai_credits_note' ) ? (string) vergeml_ai_credits_note() : __( 'One credit describes one image.', 'vergelabs-media-library' ),
                '<strong>' . esc_html( null === $left ? '—' : number_format_i18n( $left ) ) . '</strong>'
            );
        }

        if ( ! $locked ) {
            echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                $connected ? __( 'Change licence', 'vergelabs-media-library' ) : __( 'Connect', 'vergelabs-media-library' ),
                $connected
                    ? __( 'Sign in at vergelabsmedia.com, pick another licence, and you are brought straight back. The old licence gets its seat back.', 'vergelabs-media-library' )
                    : __( 'One button: sign in at vergelabsmedia.com, pick a licence, and you are sent straight back with it in place. No key to copy.', 'vergelabs-media-library' ),
                '' !== $connect
                    ? '<a class="button' . ( $connected ? '' : ' button-primary' ) . '" href="' . esc_url( $connect ) . '">'
                        . esc_html( $connected ? __( 'Connect a different licence', 'vergelabs-media-library' ) : __( 'Connect to VergeLabs', 'vergelabs-media-library' ) ) . '</a>'
                    : ''
            );
        }

        echo vergeml_pg_card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup.

        if ( ! $locked ) :
            ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="vergeml_licence_save">
                <?php wp_nonce_field( 'vergeml_licence_save' ); ?>
                <?php
                echo vergeml_pg_card_open( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                    __( 'Or paste a key', 'vergelabs-media-library' ),
                    array(
                        'note' => __( 'From the licence tab of your account at vergelabsmedia.com.', 'vergelabs-media-library' ),
                        'rows' => true,
                    )
                );
                echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                    __( 'Licence key', 'vergelabs-media-library' ),
                    '',
                    '<input type="text" name="vergeml_licence_key" class="regular-text" autocomplete="off" spellcheck="false" placeholder="'
                        . esc_attr( $connected ? '•••••••• (saved)' : 'VGML-…' ) . '">'
                );
                $buttons = '<button type="submit" class="button button-primary">' . esc_html__( 'Save key', 'vergelabs-media-library' ) . '</button>';
                if ( $connected && ! $network ) {
                    $buttons .= ' <button type="submit" name="vergeml_licence_remove" value="1" class="button vgml-button-quiet" onclick="return window.confirm(' . esc_attr( wp_json_encode( __( 'Remove the licence from this site? Nothing can be described until one is connected again.', 'vergelabs-media-library' ) ) ) . ');">'
                        . esc_html__( 'Remove from this site', 'vergelabs-media-library' ) . '</button>';
                }
                echo vergeml_pg_actions( $buttons ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
                echo vergeml_pg_card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup.
                ?>
            </form>
            <?php
        else :
            echo vergeml_pg_card_open( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                __( 'Set for the network', 'vergelabs-media-library' ),
                array( 'note' => __( 'The network administrator set one licence for every site and locked it. Change it under Network Admin → Media.', 'vergelabs-media-library' ) )
            );
            echo vergeml_pg_card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup.
        endif;
        ?>

    </div>
    <?php
}
