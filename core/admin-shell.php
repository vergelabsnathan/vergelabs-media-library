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

    global $submenu;

    if ( ! isset( $submenu[ VERGEML_MENU ] ) || ! is_array( $submenu[ VERGEML_MENU ] ) ) {
        return array();
    }

    $pages = array();

    foreach ( $submenu[ VERGEML_MENU ] as $item ) {

        if ( empty( $item[2] ) || empty( $item[0] ) ) {
            continue;
        }

        $slug = (string) $item[2];

        $pages[] = array(
            // The label carries markup on some core menus (update counts and
            // the like); the nav wants the words.
            'label' => wp_strip_all_tags( (string) $item[0] ),
            'slug'  => $slug,
            'url'   => vergeml_shell_url( $slug ),
        );
    }

    return $pages;
}


/**
 *  Where a submenu entry actually lives.
 *
 *  Asked of WordPress rather than assembled here. These screens hang off a
 *  top-level plugin page, so they are `admin.php?page=<slug>` -- the first
 *  version of this guessed `upload.php?page=<slug>`, which is where they used
 *  to live, and every nav link but one answered 403. menu_page_url() knows,
 *  because it is the function that registered them.
 */
function vergeml_shell_url( $slug ) {

    if ( false !== strpos( $slug, '.php' ) ) {
        return admin_url( $slug );
    }

    $url = menu_page_url( $slug, false );

    return $url ? $url : admin_url( 'admin.php?page=' . $slug );
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
                    <?php foreach ( $pages as $page ) : ?>
                        <li>
                            <a
                                href="<?php echo esc_url( $page['url'] ); ?>"
                                class="vgml-shell-item<?php echo $page['slug'] === $current ? ' is-on' : ''; ?>"
                                <?php echo $page['slug'] === $current ? ' aria-current="page"' : ''; ?>
                            ><?php echo esc_html( $page['label'] ); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>

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
