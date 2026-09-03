<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  One screen with a nav, instead of nine pages that look like settings.
 *
 *  The reference is WP Rocket, chosen by Nathan, and what it actually does is
 *  structural rather than decorative. Read from its own `wpr-admin.css`:
 *
 *      .wpr-wrap    { margin: 0 0 0 -20px }   -- flush to the admin menu
 *      .wpr-body    { display: flex }
 *      .wpr-Header  { flex: 0 0 225px }       -- a fixed nav column
 *      .wpr-Content { flex: 1 1 auto; background:#fff; padding: 32px 24px }
 *      .wpr-sectionHeader { border-bottom: 1px solid …; padding-bottom: 24px }
 *
 *  That is the whole trick: kill WordPress's gutter, put a nav down the left,
 *  and separate sections with a rule instead of wrapping each one in a box. It
 *  stops reading as a settings page and starts reading as an application.
 *
 *  The colours are ours, not Rocket's. Its cool grey and orange belong to it;
 *  ours is the warm set already on vergelabsmedia.com and the legal pages, so
 *  plugin and site look like one company.
 *
 *  ## Why this hooks rather than edits nine functions
 *
 *  Every screen already renders its own `.wrap`. Opening the shell on
 *  `in_admin_header` and closing it on `admin_footer` wraps all of them at
 *  once, so no render function had to be touched and a screen added later is
 *  inside the shell without anybody remembering to put it there.
 */


/** The nav column, matching Rocket's 225px. */
const VERGEML_SHELL_NAV = 225;


/**
 *  Whether the screen being rendered is one of ours.
 *
 *  Asked of the submenu WordPress actually registered rather than a list kept
 *  here, so a page added tomorrow appears in the nav by existing, and a page
 *  the current user cannot see is absent from both.
 */
function vergeml_shell_pages() {

    /*
     *  An explicit list, not $submenu.
     *
     *  It was read from $submenu so a page added later would appear by itself.
     *  Then the sidebar copy of that same menu was removed -- WordPress was
     *  drawing an identical nine-item list right beside this one -- and
     *  $submenu is now empty, so the nav has to say what it contains.
     *
     *  Grouped, because eight flat items is a list and eight grouped items is
     *  a menu. The three settings screens are the ones nobody opens twice.
     */
    $pages = array(
        array(
            'slug'  => VERGEML_MENU,
            'icon'  => 'dashboard',
            'sub'   => __( 'Where things stand', 'vergelabs-media-library' ),
            'label' => __( 'Dashboard', 'vergelabs-media-library' ),
            'cap'   => 'manage_categories',
        ),
        array(
            'slug'  => 'media-librarian',
            'icon'  => 'librarian',
            'sub'   => __( 'Put unfiled files into folders', 'vergelabs-media-library' ),
            'label' => __( 'Sort into folders', 'vergelabs-media-library' ),
            'cap'   => 'manage_categories',
        ),
        array(
            'slug'  => 'media-ai',
            'icon'  => 'ai',
            'sub'   => __( 'Describe, alt text, credits', 'vergelabs-media-library' ),
            'label' => __( 'AI', 'vergelabs-media-library' ),
            'cap'   => 'manage_categories',
        ),
        array(
            'slug'  => 'media-health',
            'icon'  => 'duplicates',
            'sub'   => __( 'Copies, and space they take', 'vergelabs-media-library' ),
            'label' => __( 'Duplicates', 'vergelabs-media-library' ),
            'cap'   => 'manage_categories',
        ),
        array(
            'slug'  => 'media-import-folders',
            'icon'  => 'import',
            'sub'   => __( 'From another plugin, or a CSV', 'vergelabs-media-library' ),
            'label' => __( 'Import folders', 'vergelabs-media-library' ),
            'cap'   => 'manage_categories',
        ),
        array(
            'slug'  => 'media-licence',
            'icon'  => 'key',
            'sub'   => __( 'Connection, key and credits', 'vergelabs-media-library' ),
            'label' => __( 'Licence', 'vergelabs-media-library' ),
            'cap'   => 'manage_options',
            'group' => 'settings',
        ),
        array(
            'slug'  => 'media-taxonomies',
            'icon'  => 'folders',
            'sub'   => __( 'Which categories act as folders', 'vergelabs-media-library' ),
            'label' => __( 'Folders and categories', 'vergelabs-media-library' ),
            'cap'   => 'manage_options',
            'group' => 'settings',
        ),
        array(
            'slug'  => 'media-library',
            'icon'  => 'sliders',
            'sub'   => __( 'Ordering, filters and uploads', 'vergelabs-media-library' ),
            'label' => __( 'Library settings', 'vergelabs-media-library' ),
            'cap'   => 'manage_options',
            'group' => 'settings',
        ),
        array(
            'slug'  => 'mime-types',
            'icon'  => 'file',
            'sub'   => __( 'What may be uploaded', 'vergelabs-media-library' ),
            'label' => __( 'File types', 'vergelabs-media-library' ),
            'cap'   => 'manage_options',
            'group' => 'settings',
        ),
    );

    $out = array();

    foreach ( $pages as $page ) {

        if ( ! current_user_can( $page['cap'] ) ) {
            continue;
        }

        // Every entry here is one of our own screens. The one link that leaves
        // the plugin -- the folders on upload.php -- is deliberately not in
        // this list; it is the button below the nav, see vergeml_shell_leave().
        $page['url'] = admin_url( 'admin.php?page=' . $page['slug'] );
        $page['group'] = isset( $page['group'] ) ? $page['group'] : '';
        $page['icon']  = isset( $page['icon'] ) ? $page['icon'] : '';
        $page['sub']   = isset( $page['sub'] ) ? $page['sub'] : '';

        $out[] = $page;
    }

    return $out;
}


/** The slug of the screen being rendered, or '' when it is not ours. */
function vergeml_shell_current() {

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which screen is open, not acting on it.
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

    if ( '' === $page ) {
        return '';
    }

    foreach ( vergeml_shell_pages() as $known ) {
        if ( $known['slug'] === $page ) {
            return $page;
        }
    }

    return '';
}


/**
 *  Whether the shell was opened, so the footer knows whether to close it.
 *  A stray closing tag on somebody else's screen would break their layout.
 */
function vergeml_shell_open_state( $set = null ) {

    static $open = false;

    if ( null !== $set ) {
        $open = (bool) $set;
    }

    return $open;
}


add_action( 'in_admin_header', 'vergeml_shell_open', 100 );

function vergeml_shell_open() {

    $current = vergeml_shell_current();

    if ( '' === $current ) {
        return;
    }

    $pages = vergeml_shell_pages();

    if ( empty( $pages ) ) {
        return;
    }

    vergeml_shell_open_state( true );

    ?>
    <div class="vgml-shell">
        <div class="vgml-shell-body">

            <nav class="vgml-shell-nav" aria-label="<?php esc_attr_e( 'VergeLabs Library', 'vergelabs-media-library' ); ?>">

                <div class="vgml-shell-brand">
                    <span class="vgml-shell-mark" aria-hidden="true"><?php echo vergeml_shell_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a literal SVG below. ?></span>
                    <span class="vgml-shell-name"><?php esc_html_e( 'VergeLabs Library', 'vergelabs-media-library' ); ?></span>
                </div>

                <ul class="vgml-shell-list">
                    <?php $group = ''; ?>
                    <?php foreach ( $pages as $page ) : ?>
                        <?php if ( 'settings' === $page['group'] && 'settings' !== $group ) : ?>
                            <li class="vgml-shell-group"><?php esc_html_e( 'Settings', 'vergelabs-media-library' ); ?></li>
                        <?php endif; ?>
                        <?php $group = $page['group']; ?>
                        <li>
                            <a
                                href="<?php echo esc_url( $page['url'] ); ?>"
                                class="vgml-shell-item<?php echo $page['slug'] === $current ? ' is-on' : ''; ?>"
                                <?php echo $page['slug'] === $current ? ' aria-current="page"' : ''; ?>
                            >
                                <span class="vgml-shell-ico"><?php
                                    echo function_exists( 'vergeml_icon' ) ? vergeml_icon( $page['icon'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG, see core/icons.php.
                                ?></span>
                                <span class="vgml-shell-text">
                                    <span class="vgml-shell-label"><?php echo esc_html( $page['label'] ); ?></span>
                                    <?php if ( '' !== $page['sub'] ) : ?>
                                        <span class="vgml-shell-sub"><?php echo esc_html( $page['sub'] ); ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php
                /*
                 *  The way out, below the nav rather than inside it.
                 *
                 *  The folders live on upload.php, so this link leaves the
                 *  plugin. Sat in the nav it read as a tenth screen of ours
                 *  and the jump to a WordPress page was a surprise every
                 *  time. A button under the list is still findable -- which
                 *  is why it was added -- without claiming to be a tab.
                 */
                echo vergeml_shell_leave(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped below.
                ?>

                <?php
                /*
                 *  What is left in the account.
                 *
                 *  This is a product somebody pays per picture for and the
                 *  balance appeared nowhere at all -- not on the dashboard,
                 *  not in the nav, nowhere. The only way to find out was to
                 *  start a run and read the sentence above the button.
                 *
                 *  Costs nothing to show: the service returns the balance with
                 *  every description and it has been stored in an option ever
                 *  since, precisely so that "how many credits are left" never
                 *  needs a request of its own.
                 */
                echo vergeml_shell_credits(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped below.
                ?>

                <p class="vgml-shell-version">
                    <?php
                    printf(
                        /* translators: %s: the plugin's version number. */
                        esc_html__( 'version %s', 'vergelabs-media-library' ),
                        esc_html( VERGEML_VERSION )
                    );
                    ?>
                </p>
            </nav>

            <main class="vgml-shell-content">
    <?php
}


/**
 *  vergeml_shell_leave
 *
 *  The button under the nav that opens the folders on the Media screen.
 *
 *  It is marked as leaving: the arrow and the "on the Media screen" line both
 *  say so before the click rather than after it. Somebody who has just filed
 *  five hundred files still needs a route back to what they made -- that was
 *  always the point -- but a route out of a plugin should not be dressed as a
 *  page of it.
 */

function vergeml_shell_leave() {

    if ( ! current_user_can( 'upload_files' ) ) {
        return '';
    }

    $icon = function_exists( 'vergeml_icon' ) ? vergeml_icon( 'folders' ) : '';

    /*
     *  One line, not two.
     *
     *  This was first built with a label and a sub-label, which is the exact
     *  shape of every row in the nav above it -- so it went on reading as a
     *  tenth menu item no matter where it sat. A button is one line of text
     *  with button chrome around it, and the difference is the whole point.
     */
    return '<div class="vgml-shell-out">'
        . sprintf(
            '<a class="vgml-shell-leave" href="%1$s">'
                . '<span class="vgml-shell-leave-ico" aria-hidden="true">%2$s</span>'
                . '<span class="vgml-shell-leave-label">%3$s</span>'
                . '<span class="vgml-shell-leave-arrow" aria-hidden="true">&#8599;</span>'
            . '</a>',
            esc_url( admin_url( 'upload.php' ) ),
            $icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG, see core/icons.php.
            esc_html__( 'Open your folders', 'vergelabs-media-library' )
        )
        . '<p class="vgml-shell-out-note">'
        . esc_html__( 'Opens the Media screen', 'vergelabs-media-library' )
        . '</p></div>';
}


/**
 *  The credit balance for the nav, or nothing at all.
 *
 *  Nothing when there is no licence: a free install has no balance, and a
 *  row reading "0 credits" would be an invitation to worry about a number
 *  that does not apply.
 *
 *  The figure is whatever the service last said, and it says when that was.
 *  A balance with no date on it is a balance somebody trusts for longer than
 *  they should -- it is exact at the end of a run and a week stale otherwise.
 */

function vergeml_shell_credits() {

    if ( ! function_exists( 'vergeml_ai_settings' ) ) {
        return '';
    }

    $settings = vergeml_ai_settings();

    if ( '' === (string) vergeml_ai_unseal( $settings['license_key'] ) ) {
        return '';
    }

    // Same reasoning as the dashboard: a number in the chrome that disagrees
    // with the account is worse than no number in the chrome.
    $stored = function_exists( 'vergeml_ai_refresh_credits' )
        ? array( 'remaining' => vergeml_ai_refresh_credits() )
        : get_option( 'vergeml_ai_credits', array() );

    if ( ! is_array( $stored ) || ! isset( $stored['remaining'] ) || null === $stored['remaining'] ) {
        return '';
    }

    $left = (int) $stored['remaining'];
    $when = isset( $stored['time'] ) ? (int) $stored['time'] : 0;

    $url = function_exists( 'vergeml_shell_url' ) ? vergeml_shell_url( 'media-licence' ) : admin_url( 'admin.php?page=media-licence' );

    /*
     *  "as of five minutes ago" was said whether or not anybody had managed to
     *  ask in the last five minutes. Every failure to reach the service left
     *  the figure untouched and the sentence unchanged, so a site whose
     *  licence had been replaced showed a confident, current-looking balance
     *  that had not been confirmed in weeks.
     */
    $at = function_exists( 'vergeml_ai_credits_state' )
        ? vergeml_ai_credits_state()
        : array( 'state' => 'ok', 'stale' => false );

    if ( 'rejected' === $at['state'] ) {
        $note = __( 'not connected', 'vergelabs-media-library' );
    } elseif ( $when > 0 ) {
        $note = sprintf(
            /* translators: %s: how long ago the balance was checked, e.g. "5 mins". */
            __( 'as of %s ago', 'vergelabs-media-library' ),
            human_time_diff( $when, time() )
        );
    } else {
        $note = __( 'not checked yet', 'vergelabs-media-library' );
    }

    /*
     *  Under a hundred is the point at which a run will not finish, so it is
     *  worth a colour. Not an alarm -- nothing is broken, and a red badge for
     *  a thing that is merely finite is how people learn to ignore badges.
     */
    $low = $left < 100 ? ' is-low' : '';

    // A balance nobody could confirm is dimmed rather than coloured: it is not
    // an alarm, it is a number to trust less.
    if ( 'rejected' === $at['state'] || ! empty( $at['stale'] ) ) {
        $low .= ' is-unconfirmed';
    }

    return sprintf(
        '<a class="vgml-shell-credits%1$s" href="%2$s"><span class="vgml-shell-credits-n">%3$s</span><span class="vgml-shell-credits-l">%4$s</span><span class="vgml-shell-credits-w">%5$s</span></a>',
        esc_attr( $low ),
        esc_url( $url ),
        esc_html( number_format_i18n( $left ) ),
        esc_html( _n( 'credit left', 'credits left', $left, 'vergelabs-media-library' ) ),
        esc_html( $note )
    );
}


/**
 *  Other people's notices, off our screens.
 *
 *  The sort flow is a five step process, and the first thing above step one
 *  was another plugin's banner telling somebody to go and create their first
 *  folder somewhere else. A screen that walks a person through a decision
 *  cannot also be a noticeboard.
 *
 *  WordPress's own notices stay: an update, a failed cron, a permissions
 *  problem are all things somebody has to see wherever they are standing.
 *  What goes is everything registered by another plugin.
 */

add_action( 'in_admin_header', 'vergeml_shell_quiet', 1 );

function vergeml_shell_quiet() {

    if ( '' === vergeml_shell_current() ) {
        return;
    }

    remove_all_actions( 'admin_notices' );
    remove_all_actions( 'all_admin_notices' );
}


add_action( 'admin_footer', 'vergeml_shell_close', 1 );

function vergeml_shell_close() {

    if ( ! vergeml_shell_open_state() ) {
        return;
    }

    echo '</main></div></div>';
}


/**
 *  The brand mark, in the nav.
 *
 *  The same shard fan as vergeml_menu_icon(), in the ink colour rather than
 *  white -- this one sits on a light ground. Reused geometry rather than a
 *  second drawing of it: two marks that differ slightly is worse than one.
 */
function vergeml_shell_mark() {

    return '<svg viewBox="0 0 20 20" width="20" height="20" focusable="false">'
        . '<g fill="currentColor">'
        . '<path d="M19 3 L7.5 -2 V20 L19 20 Z" opacity=".45"/>'
        . '<path d="M17.2 5.7 L5.5 1.2 V20 L17.2 20 Z" opacity=".6"/>'
        . '<path d="M15.5 8.5 L3.75 4.5 V20 L15.5 20 Z" opacity=".75"/>'
        . '<path d="M14 11.2 L2.25 7.7 V20 L14 20 Z" opacity=".9"/>'
        . '<path d="M12.75 14 L1 11 V20 L12.75 20 Z"/>'
        . '</g></svg>';
}


add_action( 'admin_enqueue_scripts', 'vergeml_shell_assets', 20 );

function vergeml_shell_assets() {

    if ( '' === vergeml_shell_current() ) {
        return;
    }

    wp_enqueue_style(
        'vergeml-admin-shell',
        plugins_url( 'css/vergeml-shell.css', VERGEML_FILE ),
        array(),
        vergeml_asset_ver( 'css/vergeml-shell.css' )
    );
}


/* ============================================== the grammar of a page body */

/*
 *  Every screen wrote its own markup and so every screen was a different
 *  shape. These are the pieces all nine are rebuilt from -- head, card, row,
 *  figures, actions -- so that a heading is the same heading everywhere and a
 *  control lands in the same place on every screen.
 *
 *  Each returns a string rather than echoing, because half the callers are
 *  building output to hand somewhere else and a function that only echoes
 *  forces them into output buffering.
 *
 *  Callers pass plain text; escaping happens here. Anything genuinely markup
 *  -- a control, a button -- is named $html and is the caller's to have
 *  escaped already.
 */

/**
 *  The title, the one-line explanation, and at most one action.
 *
 *  The lede is capped at a sentence by the stylesheet rather than by trust:
 *  the reason these screens did not scan is that every one of them opened
 *  with a paragraph.
 */
function vergeml_pg_head( $title, $lede = '', $action_html = '' ) {

    $out = '<div class="vgml-pg-head"><div class="vgml-pg-head-text">'
        . '<h1 class="vgml-pg-title">' . esc_html( $title ) . '</h1>';

    if ( '' !== (string) $lede ) {
        $out .= '<p class="vgml-pg-lede">' . esc_html( $lede ) . '</p>';
    }

    $out .= '</div>';

    if ( '' !== (string) $action_html ) {
        $out .= '<div class="vgml-pg-head-action">' . $action_html . '</div>';
    }

    return $out . '</div>';
}


/**
 *  Open a card. $args takes 'note' (a line under the title), 'action_html'
 *  (one button, top right) and 'rows' (true when the body is nothing but
 *  vergeml_pg_row() calls, which supply their own padding).
 */
function vergeml_pg_card_open( $title, $args = array() ) {

    $args = array_merge(
        array( 'note' => '', 'action_html' => '', 'rows' => false ),
        (array) $args
    );

    $out = '<section class="vgml-pg-card">';

    if ( '' !== (string) $title ) {

        $out .= '<div class="vgml-pg-card-head"><h2 class="vgml-pg-card-title">'
            . esc_html( $title );

        if ( '' !== (string) $args['note'] ) {
            $out .= '<span class="vgml-pg-card-note">' . esc_html( $args['note'] ) . '</span>';
        }

        $out .= '</h2>';

        if ( '' !== (string) $args['action_html'] ) {
            $out .= '<div class="vgml-pg-card-action">' . $args['action_html'] . '</div>';
        }

        $out .= '</div>';
    }

    return $out . '<div class="vgml-pg-card-body' . ( $args['rows'] ? ' is-rows' : '' ) . '">';
}


function vergeml_pg_card_close() {
    return '</div></section>';
}


/**
 *  One setting: what it is on the left, the thing that changes it on the
 *  right. $stacked for a control too wide to sit in a column -- a textarea,
 *  a table -- which drops it under the label instead of squeezing it.
 */
function vergeml_pg_row( $label, $help, $control_html, $stacked = false ) {

    $out = '<div class="vgml-pg-row' . ( $stacked ? ' is-stacked' : '' ) . '">'
        . '<div class="vgml-pg-row-text">'
        . '<span class="vgml-pg-row-label">' . esc_html( $label ) . '</span>';

    if ( '' !== (string) $help ) {
        $out .= '<span class="vgml-pg-row-help">' . esc_html( $help ) . '</span>';
    }

    $out .= '</div><div class="vgml-pg-row-control">' . $control_html . '</div></div>';

    return $out;
}


/**
 *  The figures a screen is answering with, in a strip across the top of a
 *  card. Each is array( 'n' => 412, 'l' => 'not in a folder', 'lead' => true ),
 *  and 'lead' marks the one the screen is actually about.
 */
function vergeml_pg_figures( $figures ) {

    if ( empty( $figures ) ) {
        return '';
    }

    $out = '<div class="vgml-pg-figures">';

    foreach ( (array) $figures as $figure ) {

        $lead = ! empty( $figure['lead'] ) ? ' is-lead' : '';

        $out .= '<div class="vgml-pg-figure' . $lead . '">'
            . '<span class="vgml-pg-figure-n">' . esc_html( $figure['n'] ) . '</span>'
            . '<span class="vgml-pg-figure-l">' . esc_html( $figure['l'] ) . '</span>'
            . '</div>';
    }

    return $out . '</div>';
}


/**
 *  The foot of a card. One primary button and whatever else is plain; the
 *  note is the caveat that belongs next to the button rather than three
 *  paragraphs above it.
 */
function vergeml_pg_actions( $buttons_html, $note = '' ) {

    $out = '<div class="vgml-pg-actions">';

    if ( '' !== (string) $note ) {
        $out .= '<span class="vgml-pg-actions-note">' . esc_html( $note ) . '</span>';
    }

    return $out . $buttons_html . '</div>';
}


/** What a card says when it has nothing to show, instead of being blank. */
function vergeml_pg_empty( $title, $text = '' ) {

    $out = '<div class="vgml-pg-empty"><span class="vgml-pg-empty-title">'
        . esc_html( $title ) . '</span>';

    if ( '' !== (string) $text ) {
        $out .= esc_html( $text );
    }

    return $out . '</div>';
}
