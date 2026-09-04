<?php
if ( ! defined( 'ABSPATH' ) )
    exit;

/**
 *  Get help.
 *
 *  One screen that carries the facts support needs before anyone types a
 *  word: the system report the plugin already builds, the known problems that
 *  match what this site runs (from the nightly watch, tools/watch/known-issues.json),
 *  and a question box whose "send" includes the report -- with the person's
 *  consent, and with the list of what is sent printed beside the tick box.
 *
 *  This file loads OUTSIDE the safe-mode guard, so a site whose plugin has
 *  been put into safe mode can still ask for help. That is why it is small
 *  and dull: no table, no REST route, no scripts of its own, one outbound
 *  request on a click.
 *
 *  @since 3.16
 */

if ( ! defined( 'VERGEML_KNOWN_ISSUES_URL' ) ) {
    define( 'VERGEML_KNOWN_ISSUES_URL', 'https://raw.githubusercontent.com/vergelabsnathan/vergelabs-media-library/main/tools/watch/known-issues.json' );
}

add_action( 'admin_menu', 'vergeml_help_menu', 60 );

function vergeml_help_menu() {

    if ( ! defined( 'VERGEML_MENU' ) ) {
        return;
    }

    add_submenu_page(
        VERGEML_MENU,
        __( 'Get help', 'vergelabs-media-library' ),
        __( 'Get help', 'vergelabs-media-library' ),
        'manage_options',
        'media-help',
        'vergeml_help_page'
    );
}


/* ------------------------------------------------------------ known issues */

/**
 *  The feed, cached for twelve hours. A failure to fetch is remembered for an
 *  hour so an admin screen never waits on GitHub twice in a row.
 */
function vergeml_help_known_issues() {

    $cached = get_transient( 'vergeml_known_issues' );

    if ( is_array( $cached ) ) {
        return $cached;
    }

    $response = wp_remote_get( VERGEML_KNOWN_ISSUES_URL, array( 'timeout' => 6 ) );

    if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
        set_transient( 'vergeml_known_issues', array(), HOUR_IN_SECONDS );
        return array();
    }

    $data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    $issues = is_array( $data ) && isset( $data['issues'] ) && is_array( $data['issues'] ) ? $data['issues'] : array();

    set_transient( 'vergeml_known_issues', $issues, 12 * HOUR_IN_SECONDS );

    return $issues;
}

/**
 *  What this site runs, keyed the way the feed keys it: a plugin by its
 *  directory slug, a theme by its name, WordPress as "core".
 */
function vergeml_help_installed() {

    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $installed = array( 'core' => get_bloginfo( 'version' ) );

    $active = (array) get_option( 'active_plugins', array() );

    if ( is_multisite() ) {
        $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
    }

    foreach ( $active as $file ) {
        $data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
        $slug = dirname( $file );
        $installed[ '.' === $slug ? basename( $file, '.php' ) : $slug ] = (string) $data['Version'];
    }

    $theme = wp_get_theme();
    $installed[ $theme->get( 'Name' ) ] = (string) $theme->get( 'Version' );
    $installed[ $theme->get_stylesheet() ] = (string) $theme->get( 'Version' );

    if ( $theme->parent() ) {
        $installed[ $theme->parent()->get( 'Name' ) ] = (string) $theme->parent()->get( 'Version' );
    }

    return $installed;
}

/**
 *  The issues that apply here: same slug, and either the same version or no
 *  version on the issue. Newest first, as the feed is written.
 */
function vergeml_help_matching_issues() {

    $installed = vergeml_help_installed();
    $matches   = array();

    foreach ( vergeml_help_known_issues() as $issue ) {

        if ( empty( $issue['slug'] ) || ! isset( $installed[ $issue['slug'] ] ) ) {
            continue;
        }

        if ( ! empty( $issue['version'] ) && (string) $issue['version'] !== $installed[ $issue['slug'] ] ) {
            continue;
        }

        $matches[] = $issue;
    }

    return $matches;
}


/* ---------------------------------------------------------------- sending */

/**
 *  A free install has no licence key; this token ties its tickets together
 *  on the other side without identifying anyone. Random, per site, made once.
 */
function vergeml_help_site_token() {

    $token = get_option( 'vergeml_support_token', '' );

    if ( '' === $token ) {
        $token = wp_generate_password( 32, false, false );
        add_option( 'vergeml_support_token', $token, '', 'no' );
    }

    return $token;
}

function vergeml_help_service_url() {

    if ( function_exists( 'vergeml_ai_service_url' ) ) {
        return vergeml_ai_service_url();
    }

    // Safe mode: core/ai.php is not loaded. Same rule as there -- https, or the default.
    $url = defined( 'VERGEML_AI_SERVICE' ) ? VERGEML_AI_SERVICE : 'https://ai.vergelabs.nl/v1';

    return 0 === strpos( $url, 'https://' ) ? untrailingslashit( $url ) : 'https://ai.vergelabs.nl/v1';
}

add_action( 'admin_post_vergeml_help_send', 'vergeml_help_send' );

function vergeml_help_send() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }

    check_admin_referer( 'vergeml_help_send' );

    $back = admin_url( 'admin.php?page=media-help' );

    $question = isset( $_POST['vergeml_question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['vergeml_question'] ) ) : '';
    $consent  = ! empty( $_POST['vergeml_consent'] );
    $email    = isset( $_POST['vergeml_email'] ) ? sanitize_email( wp_unslash( $_POST['vergeml_email'] ) ) : '';

    if ( '' === trim( $question ) ) {
        wp_safe_redirect( add_query_arg( 'vgml-help', 'empty', $back ) );
        exit;
    }

    if ( ! $consent ) {
        wp_safe_redirect( add_query_arg( 'vgml-help', 'consent', $back ) );
        exit;
    }

    $body = array(
        'site'         => home_url( '/' ),
        'site_token'   => vergeml_help_site_token(),
        'question'     => $question,
        'known_issues' => array_map( function ( $issue ) {
            return array(
                'dependency' => isset( $issue['dependency'] ) ? $issue['dependency'] : '',
                'name'       => isset( $issue['name'] ) ? $issue['name'] : '',
                'version'    => isset( $issue['version'] ) ? $issue['version'] : '',
                'summary'    => isset( $issue['summary'] ) ? $issue['summary'] : '',
                'issue'      => isset( $issue['issue'] ) ? $issue['issue'] : '',
            );
        }, vergeml_help_matching_issues() ),
    );

    if ( '' !== $email ) {
        $body['email'] = $email;
    }

    if ( function_exists( 'vergeml_system_report_text' ) ) {
        $body['report'] = array(
            'text' => vergeml_system_report_text(),
            'data' => vergeml_system_report_data(),
        );
    }

    // The licence key identifies the customer on the other side. Only when the
    // AI layer is loaded (not in safe mode) and a key is set.
    if ( function_exists( 'vergeml_ai_settings' ) && function_exists( 'vergeml_ai_unseal' ) ) {
        $settings = vergeml_ai_settings();
        $key      = isset( $settings['license_key'] ) ? vergeml_ai_unseal( $settings['license_key'] ) : '';
        if ( '' !== $key ) {
            $body['key'] = $key;
        }
    }

    $response = wp_remote_post( vergeml_help_service_url() . '/support/ticket', array(
        'timeout' => 20,
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( $body ),
    ) );

    $code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
    $json = is_wp_error( $response ) ? array() : json_decode( (string) wp_remote_retrieve_body( $response ), true );

    if ( 200 === $code && is_array( $json ) && ! empty( $json['ok'] ) ) {
        wp_safe_redirect( add_query_arg( array( 'vgml-help' => 'sent', 'ticket' => (int) $json['id'] ), $back ) );
        exit;
    }

    $why = is_wp_error( $response ) ? $response->get_error_message() : ( isset( $json['error'] ) ? (string) $json['error'] : 'HTTP ' . $code );

    wp_safe_redirect( add_query_arg( array( 'vgml-help' => 'failed', 'why' => rawurlencode( substr( $why, 0, 80 ) ) ), $back ) );
    exit;
}


/* ----------------------------------------------------------------- screen */

function vergeml_help_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }

    $matches = vergeml_help_matching_issues();
    $safe    = function_exists( 'vergeml_safe_mode' ) && vergeml_safe_mode();
    $report  = function_exists( 'vergeml_system_report_text' ) ? vergeml_system_report_text() : '';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading which notice to show, not acting.
    $state  = isset( $_GET['vgml-help'] ) ? sanitize_key( $_GET['vgml-help'] ) : '';
    $ticket = isset( $_GET['ticket'] ) ? (int) $_GET['ticket'] : 0;
    $why    = isset( $_GET['why'] ) ? sanitize_text_field( wp_unslash( $_GET['why'] ) ) : '';
    // phpcs:enable
    ?>
    <div class="wrap vgml-help">
        <?php
        if ( function_exists( 'vergeml_pg_head' ) ) {
            echo vergeml_pg_head( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
                __( 'Get help', 'vergelabs-media-library' ),
                __( 'What this site runs, what is already known about it, and a way to ask that carries the facts with it.', 'vergelabs-media-library' )
            );
        } else {
            echo '<h1>' . esc_html__( 'Get help', 'vergelabs-media-library' ) . '</h1>';
        }

        if ( 'sent' === $state ) : ?>
            <div class="notice notice-success"><p><?php
                /* translators: %d: ticket number */
                echo esc_html( sprintf( __( 'Sent. Your question is ticket #%d; the reply comes to the address you gave, or to the site administrator’s address.', 'vergelabs-media-library' ), $ticket ) );
            ?></p></div>
        <?php elseif ( 'failed' === $state ) : ?>
            <div class="notice notice-error"><p><?php
                /* translators: %s: the reason */
                echo esc_html( sprintf( __( 'That did not send (%s). Copy the report below and mail it to support@vergelabsmedia.com with your question.', 'vergelabs-media-library' ), $why ) );
            ?></p></div>
        <?php elseif ( 'consent' === $state ) : ?>
            <div class="notice notice-warning"><p><?php esc_html_e( 'Tick the box to send the report with your question — without it we can only guess.', 'vergelabs-media-library' ); ?></p></div>
        <?php elseif ( 'empty' === $state ) : ?>
            <div class="notice notice-warning"><p><?php esc_html_e( 'Write the question first.', 'vergelabs-media-library' ); ?></p></div>
        <?php endif; ?>

        <?php if ( $safe ) : ?>
            <div class="notice notice-error inline"><p><?php esc_html_e( 'This plugin is in safe mode on this site: something in it failed twice within an hour and it switched itself off to keep the site up. That is exactly the kind of thing to send below.', 'vergelabs-media-library' ); ?></p></div>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Known problems that match this site', 'vergelabs-media-library' ); ?></h2>
        <?php if ( $matches ) : ?>
            <ul class="vgml-help-issues">
            <?php foreach ( $matches as $issue ) : ?>
                <li>
                    <strong><?php echo esc_html( ( isset( $issue['name'] ) ? $issue['name'] : '' ) . ' ' . ( isset( $issue['version'] ) ? $issue['version'] : '' ) ); ?></strong>
                    — <?php echo esc_html( isset( $issue['summary'] ) ? $issue['summary'] : '' ); ?>
                    <?php if ( ! empty( $issue['workaround'] ) ) : ?>
                        <br><em><?php echo esc_html( $issue['workaround'] ); ?></em>
                    <?php endif; ?>
                    <?php if ( ! empty( $issue['status'] ) ) : ?>
                        <span class="description">(<?php echo esc_html( $issue['status'] ); ?>)</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p class="description"><?php esc_html_e( 'None. Nothing the nightly check has found applies to the versions this site runs.', 'vergelabs-media-library' ); ?></p>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Ask', 'vergelabs-media-library' ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'vergeml_help_send' ); ?>
            <input type="hidden" name="action" value="vergeml_help_send">
            <p>
                <label for="vergeml_question"><strong><?php esc_html_e( 'What happened, and what did you expect?', 'vergelabs-media-library' ); ?></strong></label><br>
                <textarea id="vergeml_question" name="vergeml_question" rows="6" class="large-text" required></textarea>
            </p>
            <p>
                <label for="vergeml_email"><?php esc_html_e( 'Where should the answer go?', 'vergelabs-media-library' ); ?></label><br>
                <input type="email" id="vergeml_email" name="vergeml_email" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
            </p>
            <p>
                <label>
                    <input type="checkbox" name="vergeml_consent" value="1">
                    <?php esc_html_e( 'Send the system report below with my question.', 'vergelabs-media-library' ); ?>
                </label><br>
                <span class="description"><?php esc_html_e( 'It contains: plugin and WordPress versions, PHP and database versions, the active theme and plugins with their versions, upload settings, and whether this plugin is in safe mode. It does not contain your licence key, any user’s name or address, or anything from wp-config beyond the two debug flags. Reports are deleted 90 days after the ticket closes.', 'vergelabs-media-library' ); ?></span>
            </p>
            <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'vergelabs-media-library' ); ?></button></p>
        </form>

        <h2><?php esc_html_e( 'System report', 'vergelabs-media-library' ); ?></h2>
        <?php if ( '' !== $report ) : ?>
            <p class="description"><?php esc_html_e( 'The same text the button sends. Copy it into a forum post or an email if you would rather not use the button.', 'vergelabs-media-library' ); ?></p>
            <textarea readonly class="large-text code" rows="18" onclick="this.select()"><?php echo esc_textarea( $report ); ?></textarea>
        <?php else : ?>
            <p class="description"><?php esc_html_e( 'The report cannot be built while the plugin is in safe mode; the versions above and the safe-mode notice are what to send.', 'vergelabs-media-library' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}


/* ----------------------------------------------------------- the dashboard */

add_action( 'vergeml_admin_home_cards', 'vergeml_help_card', 30 );

function vergeml_help_card() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $matches = vergeml_help_matching_issues();
    ?>
    <div class="vgml-help-card vgml-foot-row">
        <div class="vgml-foot-label"><?php esc_html_e( 'Get help', 'vergelabs-media-library' ); ?></div>
        <div class="vgml-foot-text"><?php
            if ( $matches ) {
                /* translators: %d: number of known issues */
                echo esc_html( sprintf( _n( 'One known problem matches what this site runs.', '%d known problems match what this site runs.', count( $matches ), 'vergelabs-media-library' ), count( $matches ) ) );
            } else {
                esc_html_e( 'Nothing known is wrong with the versions this site runs. If something is, ask with the report attached.', 'vergelabs-media-library' );
            }
        ?></div>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=media-help' ) ); ?>"><?php esc_html_e( 'Open Get help', 'vergelabs-media-library' ); ?></a>
    </div>
    <?php
}
