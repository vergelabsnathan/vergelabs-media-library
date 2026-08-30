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

        $page['url']   = admin_url( 'admin.php?page=' . $page['slug'] );
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
