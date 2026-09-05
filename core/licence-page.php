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
            // The seat on the licence, then the balance.
            if ( function_exists( 'vergeml_ai_activate_site' ) ) {
                vergeml_ai_activate_site();
            }
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

    /*
     *  The status band under the head: a tag for the state, then the licence,
     *  the plan and the balance in one line (design handoff, screen 6).
     */
    $plan_name = isset( $plans[ $plan ] ) ? $plans[ $plan ] : ( '' === $plan ? '' : ucfirst( $plan ) );
    if ( $connected ) {
        if ( 'rejected' === $state ) {
            $band = '<span class="vgml-tag">' . esc_html__( 'Not recognised', 'vergelabs-media-library' ) . '</span>'
                . '<span>' . esc_html__( 'The service did not recognise this key.', 'vergelabs-media-library' ) . '</span>';
        } else {
            $band = '<span class="vgml-tag vgml-tag-accent">' . esc_html( 'unreachable' === $state ? __( 'Connected · service not reached', 'vergelabs-media-library' ) : __( 'Connected', 'vergelabs-media-library' ) ) . '</span>'
                . '<span>' . sprintf(
                    /* translators: 1: last four of the key, 2: plan, 3: credits */
                    esc_html__( 'licence %1$s · %2$s · %3$s credits left', 'vergelabs-media-library' ),
                    '<b>…' . esc_html( substr( $key, -4 ) ) . '</b>',
                    esc_html( '' === $plan_name ? '—' : $plan_name ),
                    '<b>' . esc_html( null === $left ? '—' : number_format_i18n( $left ) ) . '</b>'
                ) . '</span>';
        }
    } else {
        $band = '<span class="vgml-tag">' . esc_html__( 'Not connected', 'vergelabs-media-library' ) . '</span>'
            . '<span>' . esc_html__( 'Nothing can be described until a licence is connected.', 'vergelabs-media-library' ) . '</span>';
    }

    ?>
    <div class="wrap vgml-home vgml-licence">

        <?php
        echo vergeml_pg_head( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Licence', 'vergelabs-media-library' ),
            __( 'This site\'s connection to VergeLabs. Images go to the service only to be described; nothing else leaves your site.', 'vergelabs-media-library' ),
            '<a class="button" href="https://vergelabsmedia.com/account" target="_blank" rel="noopener">' . esc_html__( 'Your account ↗', 'vergelabs-media-library' ) . '</a>'
                . '<a class="button button-primary" href="https://vergelabsmedia.com/#pricing" target="_blank" rel="noopener">' . esc_html__( 'Get credits ↗', 'vergelabs-media-library' ) . '</a>'
        );

        echo '<div class="vgml-status-band">' . $band . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.

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

        echo '<section class="vgml-pg-card vgml-licence-rows"><div class="vgml-pg-card-body is-rows">';

        if ( $connected ) {
            echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                __( 'Licence', 'vergelabs-media-library' ),
                '',
                '…' . esc_html( substr( $key, -4 ) ) . ' <span class="vgml-muted">— ' . esc_html( $network
                    ? __( 'set by the network administrator for every site.', 'vergelabs-media-library' )
                    : __( 'the last four characters match what your account page shows.', 'vergelabs-media-library' ) ) . '</span>'
            );
            echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                __( 'Plan', 'vergelabs-media-library' ),
                '',
                esc_html( '' === $plan_name ? '—' : $plan_name )
            );
            echo vergeml_pg_row( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                __( 'Credits', 'vergelabs-media-library' ),
                '',
                esc_html( null === $left ? '—' : number_format_i18n( $left ) ) . ' <span class="vgml-muted">— ' . esc_html( ( 'ok' !== $state && function_exists( 'vergeml_ai_credits_warning' ) && '' !== (string) vergeml_ai_credits_warning() )
                    ? (string) vergeml_ai_credits_warning()
                    : __( 'one credit describes one image.', 'vergelabs-media-library' ) ) . '</span>'
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

        echo '</div></section>';

        if ( ! $locked ) :
            ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="vgml-licence-form" id="vgml-licence-form">
                <input type="hidden" name="action" value="vergeml_licence_save">
                <?php wp_nonce_field( 'vergeml_licence_save' ); ?>

                <div class="vgml-section">
                    <h6 class="vgml-kicker"><?php esc_html_e( 'Or paste a key', 'vergelabs-media-library' ); ?></h6>
                    <p class="vgml-note"><?php esc_html_e( 'From the licence tab of your account at vergelabsmedia.com.', 'vergelabs-media-library' ); ?></p>
                    <div class="vgml-licence-paste">
                        <input type="text" name="vergeml_licence_key" class="vgml-input" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr( $connected ? '•••••••• (saved)' : 'VGML-…' ); ?>">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save key', 'vergelabs-media-library' ); ?></button>
                    </div>
                </div>

                <?php if ( $connected && ! $network ) : ?>
                <?php
                /*
                 *  Removing the key is a two-step confirm inline, not a browser
                 *  dialog (design handoff, item 8): the question sits where
                 *  the button was, with Keep it beside Yes.
                 */
                ?>
                <div class="vgml-licence-remove">
                    <p class="vgml-note"><?php esc_html_e( 'Removing the key stops descriptions on this site. Folders, captions and alt text already written stay exactly where they are.', 'vergelabs-media-library' ); ?></p>
                    <div class="vgml-licence-remove-ask">
                        <button type="button" class="vgml-btn vgml-btn-ghost vgml-licence-remove-open"><?php esc_html_e( 'Remove from this site', 'vergelabs-media-library' ); ?></button>
                        <span class="vgml-licence-remove-confirm" hidden>
                            <b><?php esc_html_e( 'Remove the key from this site?', 'vergelabs-media-library' ); ?></b>
                            <button type="button" class="button vgml-licence-remove-no"><?php esc_html_e( 'Keep it', 'vergelabs-media-library' ); ?></button>
                            <button type="submit" name="vergeml_licence_remove" value="1" class="button vgml-licence-remove-yes"><?php esc_html_e( 'Yes, remove', 'vergelabs-media-library' ); ?></button>
                        </span>
                    </div>
                </div>
                <script>
                ( function () {
                    var box = document.querySelector( '.vgml-licence-remove' );
                    if ( ! box ) { return; }
                    var open = box.querySelector( '.vgml-licence-remove-open' );
                    var ask = box.querySelector( '.vgml-licence-remove-confirm' );
                    var no = box.querySelector( '.vgml-licence-remove-no' );
                    open.addEventListener( 'click', function () { open.hidden = true; ask.hidden = false; } );
                    no.addEventListener( 'click', function () { ask.hidden = true; open.hidden = false; } );
                }() );
                </script>
                <?php endif; ?>
            </form>
            <?php
        else :
            echo vergeml_pg_card_open( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                __( 'Set for the network', 'vergelabs-media-library' ),
                array( 'note' => __( 'The network administrator set one licence for every site and locked it. Change it under Network Admin → Media.', 'vergelabs-media-library' ) )
            );
            echo vergeml_pg_card_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal markup.
        endif;

        if ( ! $connected ) :
            /*
             *  Demo mode, here and nowhere else, and only while there is no
             *  key. Invented captions are the way to see the shape of things
             *  before connecting, which is a question about the licence, not
             *  a setting on the AI screen -- "Try it free" was demo mode in a
             *  strange place. The option written is the same `mock` flag.
             *
             *  With VERGEML_AI_MOCK defined the switch is on and cannot be
             *  moved, and the line under it says why.
             */
            $forced = defined( 'VERGEML_AI_MOCK' );
            $demo   = $forced || ! empty( $settings['mock'] );
            ?>
            <div class="vgml-section vgml-licence-demo">
                <h6 class="vgml-kicker"><?php esc_html_e( 'Demo mode', 'vergelabs-media-library' ); ?></h6>
                <label class="vgml-check">
                    <input type="checkbox" id="vgml-demo-mode"<?php checked( $demo ); disabled( $forced ); ?>>
                    <span><?php esc_html_e( 'Invent captions here. Send nothing, spend nothing.', 'vergelabs-media-library' ); ?></span>
                </label>
                <?php if ( $forced ) : ?>
                    <p class="vgml-note"><?php esc_html_e( 'Demo mode is forced on by VERGEML_AI_MOCK in this site\'s configuration.', 'vergelabs-media-library' ); ?></p>
                <?php endif; ?>
            </div>
            <?php
            vergeml_licence_demo_script();
        endif;
        ?>

    </div>
    <?php
}


/**
 *  The switch saves itself through the AI settings route, which writes only
 *  the flags it is sent. Enqueued from the screen rather than from a hook:
 *  it exists on one screen, in one state of it, and a script queued while the
 *  page renders is printed with the footer.
 */
function vergeml_licence_demo_script() {

    wp_enqueue_script( 'wp-api-fetch' );

    wp_add_inline_script( 'wp-api-fetch', '( function () {
	var box = document.getElementById( "vgml-demo-mode" );
	if ( ! box || box.disabled || ! window.wp || ! window.wp.apiFetch ) {
		return;
	}
	box.addEventListener( "change", function () {
		box.disabled = true;
		window.wp.apiFetch( {
			path: "/vergeml/v1/ai-settings",
			method: "POST",
			data: { mock: box.checked ? 1 : 0 }
		} ).then( function () {
			box.disabled = false;
		} ).catch( function () {
			box.disabled = false;
			box.checked = ! box.checked;
		} );
	} );
} )();' );
}
