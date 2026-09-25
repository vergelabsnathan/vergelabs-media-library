<?php
/*
 *  The offer, for a site without a licence (spec G1), and its free try (G3).
 *
 *  Where an AI action used to fail with "Configure an AI endpoint and key
 *  first", a site with no key sees its own number and two ways on: try it free
 *  on 25 pictures, or buy exactly the credits its library needs. The try asks
 *  the service for a trial key (POST /api/trial: one per site and per email,
 *  none on local or test hosts), stores it sealed the way a pasted key is
 *  stored, and comes back to the screen with 25 credits and the alt text run
 *  started.
 *
 *  Mock: docs/superpowers/mocks/2026-09-24-free-to-paid-offer.html.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** What the free try gives; the service holds the real number. */
define( 'VERGEML_TRIAL_PICTURES', 25 );

/** Whether the service would give this site a free try: not on local or test hosts. */
function vergeml_offer_trial_possible() {
    $host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
    $ok   = '' !== $host && ! preg_match( '/^(localhost|127\.|10\.|192\.168\.)|\.(local|test|localhost|example|invalid)$|(^|\.)playground\.wordpress\.net$|^\d+\.\d+\.\d+\.\d+$/i', $host );
    // A walk on a local Playground shows the button to press it; the service still refuses the host.
    return (bool) apply_filters( 'vergeml_offer_trial_possible', $ok );
}

/** Whether the offer replaces the AI actions on this request. Never in demo mode: that runs locally and stays usable. */
function vergeml_offer_applies() {
    $settings = function_exists( 'vergeml_ai_settings' ) ? vergeml_ai_settings() : array();
    $demo     = ! empty( $settings['mock'] ) || defined( 'VERGEML_AI_MOCK' );
    return ! $demo && function_exists( 'vergeml_connect_has_key' ) && ! vergeml_connect_has_key() && current_user_can( 'manage_options' );
}

/** The offer box, in the place of the Describe section on the AI screen. */
function vergeml_offer_box( $missing ) {
    $missing = (int) $missing;
    // The service sells 500 credits at least. No price is fetched: the plugin
    // contacts nobody before the user asks it to, and the cart names the price.
    $credits = max( 500, $missing );
    $try     = vergeml_offer_trial_possible();
    $admin   = wp_get_current_user();
    ?>
    <section class="vgml-ai-sec vgml-offer" id="vgml-offer">
        <div id="vgml-offer-start-panel">
            <h2 class="vgml-kicker"><?php esc_html_e( 'Describe', 'vergelabs-media-library' ); ?></h2>
            <ul class="vgml-facts">
                <?php if ( $missing > 0 ) : ?>
                    <?php /* translators: %s: a number of pictures */ ?>
                    <li class="vgml-offer-head"><?php echo esc_html( sprintf( _n( '%s picture on this site has no alt text', '%s pictures on this site have no alt text', $missing, 'vergelabs-media-library' ), number_format_i18n( $missing ) ) ); ?></li>
                <?php else : ?>
                    <li class="vgml-offer-head"><?php esc_html_e( 'No licence on this site yet', 'vergelabs-media-library' ); ?></li>
                <?php endif; ?>
                <li><?php esc_html_e( 'Alt text is written from what the picture shows and the page it is on', 'vergelabs-media-library' ); ?></li>
                <li><?php esc_html_e( 'Only empty alt text is filled · what is there stays', 'vergelabs-media-library' ); ?></li>
            </ul>
            <div class="vgml-ai-buttons">
                <?php if ( $try ) : ?>
                    <?php /* translators: %s: a number of pictures */ ?>
                    <button type="button" class="vgml-btn vgml-btn-primary" id="vgml-offer-try"><?php echo esc_html( sprintf( __( 'Try it free on %s pictures', 'vergelabs-media-library' ), number_format_i18n( VERGEML_TRIAL_PICTURES ) ) ); ?></button>
                <?php endif; ?>
                <?php if ( $missing > 0 ) : ?>
                    <a class="vgml-btn<?php echo $try ? '' : ' vgml-btn-primary'; ?>" target="_blank" rel="noopener" href="<?php echo esc_url( vergeml_buy_url( '/cart?plan=credits&credits=' . $credits, 'ai' ) ); ?>"><?php
                        echo esc_html( $credits > $missing
                            /* translators: %s: a number of credits */
                            ? sprintf( __( 'Buy %s credits ↗', 'vergelabs-media-library' ), number_format_i18n( $credits ) )
                            /* translators: %s: a number of pictures */
                            : sprintf( __( 'Fill all %s ↗', 'vergelabs-media-library' ), number_format_i18n( $missing ) ) );
                    ?></a>
                <?php endif; ?>
            </div>
            <p class="vgml-note"><?php
                $bits = array();
                if ( $try ) {
                    /* translators: %s: a number of pictures */
                    $bits[] = sprintf( __( 'Free: your email, %s pictures on this site, no card.', 'vergelabs-media-library' ), number_format_i18n( VERGEML_TRIAL_PICTURES ) );
                }
                if ( $missing > 0 ) {
                    /* translators: %s: a number of credits */
                    $bits[] = sprintf( __( 'The rest: %s credits, one per picture, VAT at checkout.', 'vergelabs-media-library' ), number_format_i18n( $credits ) );
                }
                echo esc_html( implode( ' ', $bits ) );
            ?></p>
        </div>

        <?php if ( $try ) : ?>
        <div id="vgml-offer-form" hidden>
            <h2 class="vgml-kicker"><?php esc_html_e( 'Try it free', 'vergelabs-media-library' ); ?></h2>
            <ul class="vgml-facts">
                <?php /* translators: %s: a number of pictures */ ?>
                <li><?php echo esc_html( sprintf( __( '%s pictures on this site, described now, alt text filled where it is empty', 'vergelabs-media-library' ), number_format_i18n( VERGEML_TRIAL_PICTURES ) ) ); ?></li>
                <li><?php esc_html_e( 'A free key is made for this site and connected here by itself', 'vergelabs-media-library' ); ?></li>
            </ul>
            <div class="vgml-offer-row">
                <input type="email" id="vgml-offer-email" class="regular-text" autocomplete="email" value="<?php echo esc_attr( $admin->user_email ); ?>" placeholder="you@yoursite.com">
                <?php /* translators: %s: a number of pictures */ ?>
                <button type="button" class="vgml-btn vgml-btn-primary" id="vgml-offer-go"><?php echo esc_html( sprintf( __( 'Start · %s free', 'vergelabs-media-library' ), number_format_i18n( VERGEML_TRIAL_PICTURES ) ) ); ?></button>
            </div>
            <p class="vgml-note" id="vgml-offer-msg"><?php esc_html_e( 'One free try per site and per email. No card.', 'vergelabs-media-library' ); ?></p>
        </div>
        <script>
        ( function () {
            var tryBtn = document.getElementById( 'vgml-offer-try' ),
                start  = document.getElementById( 'vgml-offer-start-panel' ),
                form   = document.getElementById( 'vgml-offer-form' ),
                go     = document.getElementById( 'vgml-offer-go' ),
                email  = document.getElementById( 'vgml-offer-email' ),
                msg    = document.getElementById( 'vgml-offer-msg' );
            if ( ! tryBtn ) { return; }
            tryBtn.addEventListener( 'click', function () { start.hidden = true; form.hidden = false; email.focus(); } );
            go.addEventListener( 'click', function () {
                go.disabled = true;
                go.textContent = <?php echo wp_json_encode( __( 'Making your key…', 'vergelabs-media-library' ) ); ?>;
                var body = new URLSearchParams( { action: 'vergeml_trial', _ajax_nonce: <?php echo wp_json_encode( wp_create_nonce( 'vergeml_trial' ) ); ?>, email: email.value } );
                fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
                    .then( function ( r ) { return r.json(); } )
                    .then( function ( r ) {
                        if ( r && r.success ) { location.href = r.data.next; return; }
                        msg.textContent = ( r && r.data && r.data.message ) || <?php echo wp_json_encode( __( 'That did not work. Try again in a minute.', 'vergelabs-media-library' ) ); ?>;
                        go.disabled = false;
                        go.textContent = <?php echo wp_json_encode( sprintf( __( 'Start · %s free', 'vergelabs-media-library' ), number_format_i18n( VERGEML_TRIAL_PICTURES ) ) ); ?>;
                    } )
                    .catch( function () {
                        msg.textContent = <?php echo wp_json_encode( __( 'The service did not answer. Try again in a minute.', 'vergelabs-media-library' ) ); ?>;
                        go.disabled = false;
                    } );
            } );
        } )();
        </script>
        <?php endif; ?>
    </section>
    <?php
}

/** The Credits rail block for a site without a licence. */
function vergeml_offer_rail() {
    ?>
    <div class="vgml-rail-block">
        <h6 class="vgml-kicker"><?php esc_html_e( 'Credits', 'vergelabs-media-library' ); ?></h6>
        <p class="vgml-ai-num vgml-ai-credits-n">0</p>
        <ul class="vgml-facts">
            <li><?php esc_html_e( 'One credit describes one picture', 'vergelabs-media-library' ); ?></li>
            <?php if ( vergeml_offer_trial_possible() ) : ?>
                <?php /* translators: %s: a number of pictures */ ?>
                <li><?php echo esc_html( sprintf( __( '%s free to try on this site', 'vergelabs-media-library' ), number_format_i18n( VERGEML_TRIAL_PICTURES ) ) ); ?></li>
            <?php endif; ?>
        </ul>
        <div class="vgml-ai-buttons">
            <a class="vgml-btn" href="<?php echo esc_url( vergeml_buy_url( '/pricing', 'ai' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See prices ↗', 'vergelabs-media-library' ); ?></a>
        </div>
    </div>
    <?php
}

/*
 *  The free try, server side. The key comes back from the service already
 *  activated on this site; it is sealed and stored exactly as the Connect
 *  handshake stores one, and the balance is fetched so the screen shows 25.
 */
add_action( 'wp_ajax_vergeml_trial', 'vergeml_offer_ajax_trial' );

function vergeml_offer_ajax_trial() {
    check_ajax_referer( 'vergeml_trial' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Only an administrator can start the free try.', 'vergelabs-media-library' ) ), 403 );
    }
    if ( vergeml_connect_has_key() ) {
        wp_send_json_error( array( 'message' => __( 'This site already has a licence.', 'vergelabs-media-library' ) ), 409 );
    }
    $email = sanitize_email( wp_unslash( isset( $_POST['email'] ) ? $_POST['email'] : '' ) );
    if ( '' === $email || ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => __( 'That email address does not look right.', 'vergelabs-media-library' ) ), 400 );
    }

    $r = wp_remote_post(
        vergeml_connect_base() . '/api/trial',
        array(
            'timeout' => 15,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array( 'email' => $email, 'site' => home_url() ) ),
        )
    );
    if ( is_wp_error( $r ) ) {
        wp_send_json_error( array( 'message' => __( 'The service did not answer. Try again in a minute.', 'vergelabs-media-library' ) ), 502 );
    }
    $body = json_decode( wp_remote_retrieve_body( $r ), true );
    $code = (int) wp_remote_retrieve_response_code( $r );

    if ( 200 !== $code || empty( $body['key'] ) ) {
        $why = isset( $body['error'] ) ? (string) $body['error'] : '';
        $messages = array(
            'trial_used'    => __( 'This site has had its free try already. Credits are on the prices page.', 'vergelabs-media-library' ),
            'email_used'    => __( 'This email has had its free try already, on another site.', 'vergelabs-media-library' ),
            'no_trial_here' => __( 'Free tries are not available on local or test sites.', 'vergelabs-media-library' ),
            'busy'          => __( 'Too many free tries today. Try again tomorrow.', 'vergelabs-media-library' ),
            'invalid_email' => __( 'That email address does not look right.', 'vergelabs-media-library' ),
        );
        wp_send_json_error( array( 'message' => isset( $messages[ $why ] ) ? $messages[ $why ] : __( 'That did not work. Try again in a minute.', 'vergelabs-media-library' ) ), $code ? $code : 502 );
    }

    $settings                = get_option( 'vergeml_ai', array() );
    $settings                = is_array( $settings ) ? $settings : array();
    $settings['license_key'] = vergeml_ai_seal( sanitize_text_field( (string) $body['key'] ) );
    update_option( 'vergeml_ai', $settings, false );

    if ( function_exists( 'vergeml_ai_refresh_credits' ) ) {
        vergeml_ai_refresh_credits( true );
    }

    wp_send_json_success( array( 'next' => add_query_arg( 'vgml_trial', '1', admin_url( 'admin.php?page=media-ai' ) ) ) );
}
