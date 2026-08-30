<?php

if ( ! defined( 'ABSPATH' ) )
    exit;


/**
 *  One home for the plugin's screens.
 *
 *  They were scattered across four entries under Settings -- Media Library, Media
 *  Taxonomies, MIME Types, Import Folders -- which is where a plugin puts things
 *  when nobody has decided where they go. Nothing named the plugin, nothing said
 *  the four screens were related, and finding the folder settings meant knowing
 *  they were called "Media Taxonomies".
 *
 *  A top-level menu with the screens under it, and a home page that says what is
 *  here. The page slugs do not change, so everything registered against them
 *  keeps working; only the parent moves, and the old Settings URLs are redirected
 *  rather than left to 404.
 *
 *  @since 3.2
 */

/*
 *  Defined in the main plugin file, not here.
 *
 *  This file only loads in the admin, and import-ui.php -- which names the menu
 *  as its parent -- loads on every request. A constant that exists on some
 *  requests and not others is a fatal error waiting for whichever one gets it
 *  wrong.
 */


/**
 *  Registered before the screens themselves, which hang off it.
 *
 *  options-pages.php runs at 12 and import-ui.php at 20, so 9 puts the parent in
 *  place before either asks for it. A submenu whose parent does not exist yet is
 *  silently dropped.
 */

add_action( 'admin_menu', 'vergeml_admin_menu', 9 );

function vergeml_admin_menu() {

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_categories' ) ) {
        return;
    }

    add_menu_page(
        __( 'VergeLabs Library', 'vergelabs-media-library' ),
        __( 'VergeLabs Library', 'vergelabs-media-library' ),
        'manage_categories',
        VERGEML_MENU,
        // The dashboard IS the plugin's home. It used to be a card menu called
        // Overview, with a second home screen called Start above it -- two
        // front doors to the same house.
        'vergeml_journey_screen',
        vergeml_menu_icon(),
        /*
         *  Below the content cluster, above Appearance -- its own product,
         *  not a sibling squeezed against the stock Media item.
         */
        58
    );

    /*
     *  The sidebar submenu is hidden, not removed.
     *
     *  The plugin draws its own nav inside the page (core/admin-shell.php) and
     *  WordPress was drawing an identical nine-item list in the black sidebar
     *  beside it. Two menus, same screens, side by side.
     *
     *  The first attempt at this called remove_submenu_page() on each entry,
     *  which broke every one of them: user_can_access_admin_page() looks the
     *  page up in $submenu to decide whether you may open it, so removing the
     *  entry answers 403. The entries stay; the sidebar simply does not draw
     *  them.
     */
    add_action( 'admin_head', 'vergeml_hide_submenu' );
}


function vergeml_hide_submenu() {

    printf(
        '<style>#adminmenu .toplevel_page_%s .wp-submenu{display:none}</style>',
        esc_attr( VERGEML_MENU )
    );
}


/**
 *  vergeml_menu_icon
 *
 *  The menu mark, as a data URI.
 *
 *  The same two-plane folder the tree draws, flattened to one colour because the
 *  admin menu paints its icons with a CSS filter and a two-tone mark comes out as
 *  mud. Inline rather than a file: one fewer request, and it cannot go missing.
 */

function vergeml_menu_icon() {

    /*
     *  The VergeLabs shard fan, white, as a data URI. The same slat geometry
     *  the brand uses, scaled to the 20px menu box; white because the menu is
     *  dark and the item should read as itself, not as another grey tool.
     */
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
        . '<g fill="#ffffff">'
        . '<path d="M19 3 L7.5 -2 V20 L19 20 Z" opacity=".45"/>'
        . '<path d="M17.2 5.7 L5.5 1.2 V20 L17.2 20 Z" opacity=".6"/>'
        . '<path d="M15.5 8.5 L3.75 4.5 V20 L15.5 20 Z" opacity=".75"/>'
        . '<path d="M14 11.2 L2.25 7.7 V20 L14 20 Z" opacity=".9"/>'
        . '<path d="M12.75 14 L1 11 V20 L12.75 20 Z"/>'
        . '</g></svg>';

    return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}


/**
 *  The old addresses still work.
 *
 *  These screens lived under Settings for years and people have them bookmarked,
 *  linked from their own notes, and open in a tab. Moving a menu is not a reason
 *  to break a URL.
 */

/*
 *  admin_page_access_denied, not admin_init.
 *
 *  Once a screen is no longer registered under Settings, WordPress refuses the
 *  request with a 403 before admin_init ever runs -- so a redirect hooked there
 *  never fires and the old bookmark gets "Sorry, you are not allowed to access
 *  this page", which is both wrong and alarming. This action is the moment just
 *  before that refusal.
 */

add_action( 'admin_page_access_denied', 'vergeml_admin_menu_redirects' );
add_action( 'admin_init', 'vergeml_admin_menu_redirects' );

function vergeml_admin_menu_redirects() {

    global $pagenow;

    if ( 'options-general.php' !== $pagenow ) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading which screen was asked for, not acting on it.
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

    $moved = array( 'media-library', 'media-taxonomies', 'mime-types', 'media-import-folders' );

    if ( ! in_array( $page, $moved, true ) ) {
        return;
    }

    wp_safe_redirect( admin_url( 'admin.php?page=' . $page ), 301 );
    exit;
}


/**
 *  vergeml_admin_home
 *
 *  What is here, and where to go next.
 *
 *  Not a dashboard: a plugin's home screen exists to answer "where is the thing I
 *  came for", and every number on it is a number somebody has to keep true. Four
 *  cards, the version, and the two counts that are cheap and worth knowing.
 */

function vergeml_admin_home() {

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }

    $taxonomies = function_exists( 'vergeml_tree_taxonomies' ) ? vergeml_tree_taxonomies() : array();
    $folders    = 0;

    foreach ( $taxonomies as $taxonomy ) {
        $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
        $folders += is_wp_error( $terms ) ? 0 : count( $terms );
    }

    $files = (int) wp_count_posts( 'attachment' )->inherit;

    $cards = array(
        array(
            'page'  => 'media-taxonomies',
            'title' => __( 'Folders and categories', 'vergelabs-media-library' ),
            'text'  => __( 'Which taxonomies act as folders, what they are called, and which post types they apply to.', 'vergelabs-media-library' ),
            'cap'   => 'manage_options',
        ),
        array(
            'page'  => 'media-import-folders',
            'title' => __( 'Import folders', 'vergelabs-media-library' ),
            'text'  => __( 'Bring your folders over from FileBird, Premio Folders, WP Media Folder, HappyFiles, Wicked Folders or Real Media Library. Nothing is taken from them, and it can be undone.', 'vergelabs-media-library' ),
            'cap'   => 'manage_categories',
        ),
        array(
            'page'  => 'media-library',
            'title' => __( 'Library settings', 'vergelabs-media-library' ),
            'text'  => __( 'Ordering, filters, what the grid and the list show, and how uploads are filed.', 'vergelabs-media-library' ),
            'cap'   => 'manage_options',
        ),
        array(
            'page'  => 'media-ai',
            'title' => __( 'AI', 'vergelabs-media-library' ),
            'text'  => __( 'Describe your images once; search finds what pictures show, and missing alt text fills itself in.', 'vergelabs-media-library' ),
            'cap'   => 'manage_categories',
        ),
        array(
            'page'  => 'media-librarian',
            'title' => __( 'Sort into folders', 'vergelabs-media-library' ),
            // The card says where this library actually is in the ladder, so
            // the home screen answers "can I use this yet" without a click.
            'text'  => function_exists( 'vergeml_librarian_card_text' )
                ? vergeml_librarian_card_text()
                : __( 'See the folders this library would get, change them, apply them — and put it all back with one click.', 'vergelabs-media-library' ),
            'cap'   => 'manage_categories',
        ),
        array(
            'page'  => 'media-health',
            'title' => __( 'Library health', 'vergelabs-media-library' ),
            'text'  => __( 'Which files are copies of each other — the same upload twice, or one photo exported again at another size — and how much space they take. Reads only.', 'vergelabs-media-library' ),
            'cap'   => 'manage_categories',
        ),
        array(
            'page'  => 'mime-types',
            'title' => __( 'File types', 'vergelabs-media-library' ),
            'text'  => __( 'Which file types may be uploaded, and how they are grouped in the library filters.', 'vergelabs-media-library' ),
            'cap'   => 'manage_options',
        ),
    );

    ?>
    <div class="wrap vgml-home">

        <div class="vgml-home-head">
            <h1><?php esc_html_e( 'Media Library', 'vergelabs-media-library' ); ?></h1>
            <span class="vgml-home-version"><?php echo esc_html( VERGEML_VERSION ); ?></span>
            <p class="vgml-home-counts">
                <?php
                printf(
                    /* translators: 1: number of folders, 2: number of files. */
                    esc_html__( '%1$s folders, %2$s files', 'vergelabs-media-library' ),
                    esc_html( number_format_i18n( $folders ) ),
                    esc_html( number_format_i18n( $files ) )
                );
                ?>
            </p>
        </div>

        <div class="vgml-home-cards">
            <?php foreach ( $cards as $card ) : ?>
                <?php if ( ! current_user_can( $card['cap'] ) ) { continue; } ?>
                <a class="vgml-home-card" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $card['page'] ) ); ?>">
                    <h2><?php echo esc_html( $card['title'] ); ?></h2>
                    <p><?php echo esc_html( $card['text'] ); ?></p>
                </a>
            <?php endforeach; ?>
        </div>

        <?php
        /*
         *  Anything that belongs on the home screen but is not a way in to
         *  another screen. core/instrument.php puts its opt-in card here --
         *  a question asked once, in the one place a plugin's own settings
         *  are expected to live.
         */
        do_action( 'vergeml_admin_home_cards' );
        ?>

        <p class="vgml-home-foot">
            <?php
            printf(
                /* translators: %s: link to the media library. */
                esc_html__( 'The folder tree itself lives on the %s.', 'vergelabs-media-library' ),
                '<a href="' . esc_url( admin_url( 'upload.php' ) ) . '">' . esc_html__( 'media library screen', 'vergelabs-media-library' ) . '</a>'
            );
            ?>
        </p>

    </div>
    <?php
}


add_action( 'admin_enqueue_scripts', 'vergeml_admin_home_styles' );

function vergeml_admin_home_styles( $hook ) {

    if ( 'toplevel_page_' . VERGEML_MENU !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'vergeml-admin',
        plugins_url( 'css/vergeml-admin.css', VERGEML_FILE ),
        array(),
        VERGEML_VERSION
    );
}
