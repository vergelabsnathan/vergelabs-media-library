<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  The AI screen: three tabs.
 *
 *  Describe -- the run, what the model writes and where it goes, credits.
 *  How it describes -- the brief as a conversation (core/brief.php), the
 *  draft on the right, "Test on 5 pictures" with its cost in the label.
 *  Search -- what a search in the media library matches, and a query tried
 *  with the reason for each hit (core/search-try.php).
 *
 *  Approved as docs/superpowers/mocks/2026-09-06-ai-screen.html on
 *  2026-09-06. Tabs across the page, not the Folders switch: that says one
 *  result built two ways; these are three different things. Each tab is a
 *  page load with the tab in the URL, so a link can land on one. Nothing on
 *  this screen is pinned (spec section 3), and its two switches save as
 *  they are changed -- one line under each says so -- rather than through
 *  a save bar beside a brief that has its own control.
 */

function vergeml_ai_tabs() {
    return array(
        'describe' => __( 'Describe', 'vergelabs-media-library' ),
        'how'      => __( 'How it describes', 'vergelabs-media-library' ),
        'search'   => __( 'Search', 'vergelabs-media-library' ),
    );
}

/** The tab the URL names; the brief's tab needs the capability to change settings. */
function vergeml_ai_tab() {
    $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'describe'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab to draw.
    if ( ! array_key_exists( $tab, vergeml_ai_tabs() ) ) {
        return 'describe';
    }
    if ( 'how' === $tab && ! current_user_can( 'manage_options' ) ) {
        return 'describe';
    }
    return $tab;
}

function vergeml_ai_tab_url( $tab ) {
    return admin_url( 'admin.php?page=media-ai' . ( 'describe' === $tab ? '' : '&tab=' . $tab ) );
}

add_action( 'admin_enqueue_scripts', 'vergeml_ai_screen_assets' );

function vergeml_ai_screen_assets( $hook ) {

    if ( false === strpos( (string) $hook, 'media-ai' ) ) {
        return;
    }

    wp_enqueue_style(
        'vergeml-admin',
        plugins_url( 'css/vergeml-admin.css', VERGEML_FILE ),
        array(),
        vergeml_asset_ver( 'css/vergeml-admin.css' )
    );

    $tab = vergeml_ai_tab();

    if ( 'how' === $tab ) {
        if ( function_exists( 'vergeml_brief_assets' ) ) {
            vergeml_brief_assets();
        }
        return;
    }

    wp_enqueue_script(
        'vergeml-ai',
        plugins_url( 'js/vergeml-ai.js', VERGEML_FILE ),
        array( 'wp-api-fetch', 'wp-i18n' ),
        vergeml_asset_ver( 'js/vergeml-ai.js' ),
        true
    );
    wp_set_script_translations( 'vergeml-ai', 'vergelabs-media-library' );
    wp_localize_script( 'vergeml-ai', 'vgmlAi', array(
        'tab'          => $tab,
        'ns'           => VERGEML_REST_NS,
        'canConfigure' => current_user_can( 'manage_options' ),
    ) );
}

/* ------------------------------------------------------------ the numbers */

/**
 *  Every number the screen prints, from the catalogue and the pictures.
 *  Counted, never summed from other screens' figures, so the table on the
 *  Describe tab says what is in the database.
 */
function vergeml_ai_screen_counts() {
    global $wpdb;

    $t = isset( $wpdb->vergeml_ai_index ) ? $wpdb->vergeml_ai_index : '';
    $c = array(
        'images'    => 0,
        'described' => 0,
        'alt_have'  => 0,
        'alt_model' => 0,
        'titled'    => 0,
        'kinds'     => array(),
        'people'    => 0,
        'text'      => 0,
        'filing'    => array(),
        'tags'      => 0,
        'runs'      => array(),
        'last'      => '',
        'new'       => function_exists( 'vergeml_ai_pending_count' ) ? (int) vergeml_ai_pending_count( 'unindexed' ) : 0,
        'missing'   => function_exists( 'vergeml_ai_pending_count' ) ? (int) vergeml_ai_pending_count( 'missing-alt' ) : 0,
        'gap'       => function_exists( 'vergeml_ai_pending_count' ) ? (int) vergeml_ai_pending_count( 'page-gap' ) : 0,
        'stale'     => function_exists( 'vergeml_ai_pending_count' ) ? (int) vergeml_ai_pending_count( 'stale' ) : 0,
    );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- this plugin's own table, and core's.
    $c['images'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type LIKE 'image/%'" );
    // DISTINCT: a picture with two alt rows (an import left duplicates on the box) is one picture with alt text.
    $c['alt_have'] = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt' WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE 'image/%' AND m.meta_value <> ''" );

    if ( '' !== $t ) {
        $c['described'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE error = '' AND embedding IS NOT NULL" );
        $c['alt_model'] = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT i.attachment_id) FROM {$t} i JOIN {$wpdb->postmeta} m ON m.post_id = i.attachment_id AND m.meta_key = '_wp_attachment_image_alt' WHERE i.error = '' AND m.meta_value = i.alt" );
        $c['titled']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} i JOIN {$wpdb->posts} p ON p.ID = i.attachment_id WHERE i.error = '' AND p.post_title = i.title" );
        foreach ( (array) $wpdb->get_results( "SELECT kind, COUNT(*) n FROM {$t} WHERE error = '' GROUP BY kind ORDER BY n DESC", ARRAY_A ) as $row ) {
            $c['kinds'][ '' === (string) $row['kind'] ? 'none' : (string) $row['kind'] ] = (int) $row['n'];
        }
        $flags = $wpdb->get_row( "SELECT SUM(has_people = 1) people, SUM(has_text = 1) txt FROM {$t} WHERE error = ''", ARRAY_A );
        $c['people'] = (int) ( $flags ? $flags['people'] : 0 );
        $c['text']   = (int) ( $flags ? $flags['txt'] : 0 );
        $sums = array();
        foreach ( array( 'object', 'material', 'colour', 'setting', 'style', 'audience', 'season', 'details' ) as $f ) {
            $sums[] = $wpdb->prepare( "SUM(filing LIKE %s AND filing NOT LIKE %s) AS {$f}", '%"' . $f . '":"%', '%"' . $f . '":""%' );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- every fragment above went through prepare(); the field names come from the fixed list, not from input.
        $filing = $wpdb->get_row( 'SELECT ' . implode( ', ', $sums ) . " FROM {$t} WHERE error = ''", ARRAY_A );
        foreach ( (array) $filing as $f => $n ) {
            $c['filing'][ $f ] = (int) $n;
        }
        $c['runs'] = array_map( function ( $r ) {
            return array( 'day' => (string) $r['d'], 'last' => (string) $r['last'], 'n' => (int) $r['n'] );
        }, (array) $wpdb->get_results( "SELECT DATE(described_at) d, MAX(described_at) last, COUNT(*) n FROM {$t} WHERE error = '' AND described_at IS NOT NULL GROUP BY DATE(described_at) ORDER BY d DESC LIMIT 3", ARRAY_A ) );
        $c['last'] = (string) $wpdb->get_var( "SELECT MAX(described_at) FROM {$t} WHERE error = ''" );
    }
    // phpcs:enable

    if ( function_exists( 'vergeml_brief_catalogue' ) ) {
        $cat = vergeml_brief_catalogue();
        $c['tags'] = (int) $cat['tags'];
    }

    return $c;
}

/** "4 September 13:52", from a described_at (UTC) in the site's zone. */
function vergeml_ai_when( $mysql_utc ) {
    if ( '' === (string) $mysql_utc ) {
        return '';
    }
    return get_date_from_gmt( $mysql_utc, __( 'j F H:i', 'vergelabs-media-library' ) );
}

/** The line under the title: the four numbers everything on the screen is about. */
function vergeml_ai_facts_line( $c ) {
    $bits = array(
        /* translators: %s: pictures */
        sprintf( _n( '%s picture', '%s pictures', $c['images'], 'vergelabs-media-library' ), number_format_i18n( $c['images'] ) ),
        /* translators: %s: pictures */
        sprintf( __( '%s described', 'vergelabs-media-library' ), number_format_i18n( $c['described'] ) ),
        /* translators: %s: pictures */
        sprintf( __( '%s with alt text', 'vergelabs-media-library' ), number_format_i18n( $c['alt_have'] ) ),
    );
    if ( '' !== $c['last'] ) {
        /* translators: %s: a date and time */
        $bits[] = sprintf( __( 'last run %s', 'vergelabs-media-library' ), vergeml_ai_when( $c['last'] ) );
    }
    return implode( ' · ', $bits );
}

/* --------------------------------------------------------------- the page */

function vergeml_ai_page() {

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }

    $can_configure = current_user_can( 'manage_options' );
    $tab           = vergeml_ai_tab();
    $counts        = vergeml_ai_screen_counts();

    ?>
    <div class="wrap vgml-home vgml-ai" data-tab="<?php echo esc_attr( $tab ); ?>">

        <?php
        // The licence lives on its own tab. Only the absence of one is said here.
        if ( function_exists( 'vergeml_connect_has_key' ) && ! vergeml_connect_has_key() && $can_configure ) {
            printf(
                '<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__( 'No licence is connected, so nothing can be described yet.', 'vergelabs-media-library' ),
                esc_url( admin_url( 'admin.php?page=media-licence' ) ),
                esc_html__( 'Connect one on the Licence tab', 'vergelabs-media-library' )
            );
        }

        echo vergeml_pg_head( __( 'AI', 'vergelabs-media-library' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
        ?>
        <p class="vgml-ai-factsline" id="vgml-ai-counts"><?php echo esc_html( vergeml_ai_facts_line( $counts ) ); ?></p>

        <nav class="vgml-tabs" role="tablist" aria-label="<?php esc_attr_e( 'AI', 'vergelabs-media-library' ); ?>">
            <?php foreach ( vergeml_ai_tabs() as $slug => $label ) : ?>
                <?php if ( 'how' === $slug && ! $can_configure ) { continue; } ?>
                <a role="tab" class="vgml-tab<?php echo $slug === $tab ? ' is-on' : ''; ?>" aria-selected="<?php echo $slug === $tab ? 'true' : 'false'; ?>" href="<?php echo esc_url( vergeml_ai_tab_url( $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
            <?php endforeach; ?>
        </nav>

        <?php
        if ( 'how' === $tab ) {
            vergeml_ai_tab_how( $counts );
        } elseif ( 'search' === $tab ) {
            vergeml_ai_tab_search( $counts, $can_configure );
        } else {
            vergeml_ai_tab_describe( $counts );
        }
        ?>
    </div>
    <?php
}

/** "object 614 · colour 618 · setting 610" */
function vergeml_ai_counts_line( $counts, $keys ) {
    $bits = array();
    foreach ( $keys as $k ) {
        if ( isset( $counts[ $k ] ) ) {
            $bits[] = $k . ' ' . number_format_i18n( (int) $counts[ $k ] );
        }
    }
    return implode( ' · ', $bits );
}

/* ---------------------------------------------------------------- Describe */

function vergeml_ai_tab_describe( $c ) {

    $on_brief = max( 0, $c['described'] - $c['stale'] );
    $kinds    = array();
    foreach ( $c['kinds'] as $kind => $n ) {
        $kinds[] = $kind . ' ' . number_format_i18n( $n );
    }
    $credits = get_option( 'vergeml_ai_credits', array() );
    $left    = is_array( $credits ) && isset( $credits['remaining'] ) && null !== $credits['remaining'] ? (int) $credits['remaining'] : null;

    ?>
    <div class="vgml-cols vgml-ai-cols">
    <div class="vgml-cols-main">

        <section class="vgml-ai-sec vgml-ai-run">
            <h2 class="vgml-kicker"><?php esc_html_e( 'Describe', 'vergelabs-media-library' ); ?></h2>
            <ul class="vgml-facts vgml-ai-facts">
                <?php /* translators: %s: a number of pictures */ ?>
                <li id="vgml-ai-fact-new"><?php echo esc_html( sprintf( _n( '%s new picture', '%s new pictures', $c['new'], 'vergelabs-media-library' ), number_format_i18n( $c['new'] ) ) ); ?></li>
                <?php /* translators: %s: a number of pictures */ ?>
                <li id="vgml-ai-fact-alt"><?php echo esc_html( sprintf( __( '%s without alt text', 'vergelabs-media-library' ), number_format_i18n( $c['missing'] ) ) ); ?></li>
                <?php /* translators: 1: how many pictures use the brief in use, 2: how many pictures there are */ ?>
                <li id="vgml-ai-fact-brief"><?php echo esc_html( sprintf( __( '%1$s of %2$s on the brief in use', 'vergelabs-media-library' ), number_format_i18n( $on_brief ), number_format_i18n( $c['described'] ) ) ); ?></li>
            </ul>

            <div class="vgml-ai-choice">
                <label class="vgml-check"><input type="radio" name="vgml-ai-where" value="here" checked><span><?php esc_html_e( 'Watch it here', 'vergelabs-media-library' ); ?></span></label>
                <label class="vgml-check"><input type="radio" name="vgml-ai-where" value="background"><span><?php esc_html_e( 'In the background · this tab can be closed', 'vergelabs-media-library' ); ?></span></label>
            </div>

            <div class="vgml-ai-buttons">
                <button type="button" class="vgml-btn vgml-btn-primary" id="vgml-ai-run" data-scope="unindexed" <?php disabled( 0 === $c['new'] ); ?>><?php
                    echo esc_html( $c['new'] > 0
                        /* translators: %s: a number of pictures */
                        ? sprintf( _n( 'Describe %s new picture', 'Describe %s new pictures', $c['new'], 'vergelabs-media-library' ), number_format_i18n( $c['new'] ) )
                        : __( 'Describe · nothing new', 'vergelabs-media-library' ) );
                ?></button>
                <button type="button" class="vgml-btn" id="vgml-ai-alt" data-scope="missing-alt" <?php disabled( 0 === $c['missing'] ); ?>><?php
                    echo esc_html( $c['missing'] > 0
                        /* translators: %s: a number of pictures */
                        ? sprintf( __( 'Alt text for %s', 'vergelabs-media-library' ), number_format_i18n( $c['missing'] ) )
                        : __( 'Alt text · none missing', 'vergelabs-media-library' ) );
                ?></button>
                <?php
                /*
                 *  The pages an SEO plugin is scoring, first. Shown only while
                 *  there is something to fix there.
                 */
                ?>
                <button type="button" class="vgml-btn" id="vgml-ai-page-gap" data-scope="page-gap" <?php echo $c['gap'] > 0 ? '' : 'hidden'; ?>><?php
                    /* translators: %s: a number of pictures */
                    echo esc_html( sprintf( __( 'Alt text on your SEO pages · %s', 'vergelabs-media-library' ), number_format_i18n( $c['gap'] ) ) );
                ?></button>
                <button type="button" class="vgml-btn" id="vgml-ai-stop" hidden><?php esc_html_e( 'Stop', 'vergelabs-media-library' ); ?></button>
                <button type="button" class="vgml-btn" id="vgml-ai-bg-stop" hidden><?php esc_html_e( 'Stop', 'vergelabs-media-library' ); ?></button>
            </div>
            <div class="vgml-import-bar vgml-ai-bar" id="vgml-ai-bar" hidden><div class="vgml-import-fill" id="vgml-ai-fill"></div></div>
            <div class="vgml-import-bar vgml-ai-bar" id="vgml-ai-bg-bar" hidden><div class="vgml-import-fill" id="vgml-ai-bg-fill"></div></div>
            <p class="vgml-ai-line" id="vgml-ai-note"></p>
            <p class="vgml-ai-line" id="vgml-ai-bg-note"></p>
            <ul id="vgml-ai-log" class="vgml-ai-log"></ul>
        </section>

        <section class="vgml-ai-sec">
            <h2 class="vgml-kicker"><?php esc_html_e( 'What is written where', 'vergelabs-media-library' ); ?></h2>
            <ul class="vgml-facts">
                <li><?php esc_html_e( 'The catalogue is this plugin\'s own table in your database', 'vergelabs-media-library' ); ?></li>
                <li><?php esc_html_e( 'On the picture itself: its alt text, only when empty · its title, through Rename on the Dashboard', 'vergelabs-media-library' ); ?></li>
            </ul>
            <table class="vgml-table vgml-ai-table">
                <tr>
                    <th><?php esc_html_e( 'The model writes', 'vergelabs-media-library' ); ?></th>
                    <th><?php esc_html_e( 'It goes to', 'vergelabs-media-library' ); ?></th>
                    <th><?php esc_html_e( 'On this site', 'vergelabs-media-library' ); ?></th>
                </tr>
                <tr>
                    <td><b><?php esc_html_e( 'Alt text', 'vergelabs-media-library' ); ?></b></td>
                    <td><?php esc_html_e( 'The picture\'s alt text field, only when it is empty', 'vergelabs-media-library' ); ?></td>
                    <?php /* translators: 1: how many have alt text, 2: how many there are, 3: how many kept the alt text they already had */ ?>
                    <td class="n"><?php echo esc_html( sprintf( __( '%1$s of %2$s have one · %3$s kept what was there', 'vergelabs-media-library' ), number_format_i18n( $c['alt_have'] ), number_format_i18n( $c['images'] ), number_format_i18n( max( 0, $c['alt_have'] - $c['alt_model'] ) ) ) ); ?></td>
                </tr>
                <tr>
                    <td><b><?php esc_html_e( 'Title', 'vergelabs-media-library' ); ?></b></td>
                    <td><?php esc_html_e( 'The catalogue · Rename puts it on the picture in place of the file name', 'vergelabs-media-library' ); ?></td>
                    <?php /* translators: 1: how many were renamed, 2: how many there are */ ?>
                    <td class="n"><?php echo esc_html( sprintf( __( '%1$s of %2$s renamed', 'vergelabs-media-library' ), number_format_i18n( $c['titled'] ), number_format_i18n( $c['described'] ) ) ); ?></td>
                </tr>
                <tr>
                    <td><b><?php esc_html_e( 'Caption', 'vergelabs-media-library' ); ?></b></td>
                    <td><?php esc_html_e( 'The catalogue · matched by search', 'vergelabs-media-library' ); ?></td>
                    <td class="n"><?php echo esc_html( number_format_i18n( $c['described'] ) ); ?></td>
                </tr>
                <tr>
                    <td><b><?php esc_html_e( 'Tags', 'vergelabs-media-library' ); ?></b></td>
                    <td><?php esc_html_e( 'The catalogue · matched by search', 'vergelabs-media-library' ); ?></td>
                    <?php /* translators: %s: a number of distinct tags */ ?>
                    <td class="n"><?php echo esc_html( sprintf( __( '%s distinct', 'vergelabs-media-library' ), number_format_i18n( $c['tags'] ) ) ); ?></td>
                </tr>
                <tr>
                    <td><b><?php esc_html_e( 'Kind · people · text', 'vergelabs-media-library' ); ?></b></td>
                    <td><?php esc_html_e( 'The catalogue · the Folders rules and the filing gates use them', 'vergelabs-media-library' ); ?></td>
                    <?php /* translators: 1: how many pictures have people in them, 2: how many have text in them */ ?>
                    <td class="n"><?php echo esc_html( implode( ' · ', $kinds ) ); ?><br><?php echo esc_html( sprintf( __( '%1$s with people · %2$s with text', 'vergelabs-media-library' ), number_format_i18n( $c['people'] ), number_format_i18n( $c['text'] ) ) ); ?></td>
                </tr>
                <tr>
                    <td><b><?php esc_html_e( 'Filing', 'vergelabs-media-library' ); ?></b>
                        <ul class="vgml-facts"><li><?php esc_html_e( 'object, material, colour, setting', 'vergelabs-media-library' ); ?></li><li><?php esc_html_e( 'style, audience, season, details', 'vergelabs-media-library' ); ?></li></ul>
                    </td>
                    <td><?php esc_html_e( 'The catalogue · what the Folders screen files by · a field the picture does not show stays empty', 'vergelabs-media-library' ); ?></td>
                    <td class="n"><?php echo esc_html( vergeml_ai_counts_line( $c['filing'], array( 'object', 'colour', 'setting', 'details' ) ) ); ?><br><?php echo esc_html( vergeml_ai_counts_line( $c['filing'], array( 'material', 'style', 'season', 'audience' ) ) ); ?></td>
                </tr>
                <tr>
                    <td><b><?php esc_html_e( 'Meaning', 'vergelabs-media-library' ); ?></b></td>
                    <td><?php esc_html_e( 'The catalogue, as a vector · search by meaning, and the Folders tie-break', 'vergelabs-media-library' ); ?></td>
                    <?php /* translators: 1: how many are described, 2: how many there are */ ?>
                    <td class="n"><?php echo esc_html( sprintf( __( '%1$s of %2$s', 'vergelabs-media-library' ), number_format_i18n( $c['described'] ), number_format_i18n( $c['described'] ) ) ); ?></td>
                </tr>
            </table>
        </section>

        <?php
        /*
         *  Where anything built on top of the descriptions puts its own card,
         *  so a feature in its own file -- switchable off by safe mode -- does
         *  not have to be wired into this page to appear on it.
         */
        do_action( 'vergeml_ai_page_cards' );
        ?>

    </div><!-- /main -->

    <aside class="vgml-cols-rail">
        <div class="vgml-rail-block">
            <h6 class="vgml-kicker"><?php esc_html_e( 'Credits', 'vergelabs-media-library' ); ?></h6>
            <p class="vgml-ai-num vgml-ai-credits-n"><?php echo esc_html( null === $left ? '—' : number_format_i18n( $left ) ); ?></p>
            <ul class="vgml-facts">
                <li><?php esc_html_e( 'One describes one picture', 'vergelabs-media-library' ); ?></li>
                <?php /* translators: %s: a price */ ?>
                <li><?php echo esc_html( sprintf( __( '%s to describe the library again', 'vergelabs-media-library' ), number_format_i18n( $c['images'] ) ) ); ?></li>
            </ul>
            <div class="vgml-ai-buttons">
                <a class="vgml-btn" href="https://vergelabsmedia.com/#pricing" target="_blank" rel="noopener"><?php esc_html_e( 'Get credits ↗', 'vergelabs-media-library' ); ?></a>
                <a class="vgml-btn vgml-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=media-licence' ) ); ?>"><?php esc_html_e( 'Licence →', 'vergelabs-media-library' ); ?></a>
            </div>
        </div>
        <?php if ( $c['runs'] ) : ?>
        <div class="vgml-rail-block">
            <h6 class="vgml-kicker"><?php esc_html_e( 'Runs', 'vergelabs-media-library' ); ?></h6>
            <ul class="vgml-facts">
                <?php foreach ( $c['runs'] as $run ) : ?>
                    <?php /* translators: 1: when the run happened, 2: how many pictures it covered */ ?>
                    <li><?php echo esc_html( sprintf( _n( '%1$s · %2$s picture', '%1$s · %2$s pictures', $run['n'], 'vergelabs-media-library' ), vergeml_ai_when( $run['last'] ), number_format_i18n( $run['n'] ) ) ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <div class="vgml-rail-block">
            <h6 class="vgml-kicker"><?php esc_html_e( 'What leaves your site', 'vergelabs-media-library' ); ?></h6>
            <ul class="vgml-facts">
                <li><?php esc_html_e( 'Each picture goes to the service once, to be described', 'vergelabs-media-library' ); ?></li>
                <li><?php esc_html_e( 'With it: the file name, title, caption, the page it is on, and the brief', 'vergelabs-media-library' ); ?></li>
                <li><?php esc_html_e( 'Nothing else · the catalogue stays in your database', 'vergelabs-media-library' ); ?></li>
            </ul>
        </div>
    </aside>

    </div><!-- /cols -->
    <?php
}

/* --------------------------------------------------------- How it describes */

function vergeml_ai_tab_how( $c ) {
    $settings = function_exists( 'vergeml_ai_settings' ) ? vergeml_ai_settings() : array();
    ?>
    <div class="vgml-ai-talk">
        <div class="vgml-ai-talk-left">
            <div class="vgml-talk-head"><span class="vgml-kicker"><?php esc_html_e( 'Conversation', 'vergelabs-media-library' ); ?></span><span class="vgml-kicker" id="vgml-brief-turns"></span></div>
            <div id="vgml-brief-talk" class="vgml-brief-talk"></div>
        </div>
        <div class="vgml-ai-talk-right">
            <div id="vgml-brief-panel" class="vgml-brief-panel"></div>

            <section class="vgml-ai-sec">
                <h2 class="vgml-kicker"><?php esc_html_e( 'What else the model is told', 'vergelabs-media-library' ); ?></h2>
                <ul class="vgml-facts">
                    <li><?php esc_html_e( 'The rules first: alt text in one sentence, only what is visible, no keywords', 'vergelabs-media-library' ); ?></li>
                    <li><?php esc_html_e( 'The brief after the rules, as background · never as an instruction', 'vergelabs-media-library' ); ?></li>
                    <li><?php esc_html_e( 'With each picture: its file name, title and caption', 'vergelabs-media-library' ); ?></li>
                    <li><?php esc_html_e( 'Alt text, caption and title in English', 'vergelabs-media-library' ); ?></li>
                </ul>
                <label class="vgml-check vgml-ai-switch"><input type="checkbox" id="vgml-ai-page-context" <?php checked( ! empty( $settings['page_context'] ) ); ?>><span><?php esc_html_e( 'Page context: the title of the page a picture is on and, with Yoast, Rank Math, SEOPress or AIOSEO, its focus keyphrase', 'vergelabs-media-library' ); ?></span></label>
                <ul class="vgml-facts"><li id="vgml-ai-page-context-note"><?php esc_html_e( 'Saves as it is changed · applies to the next picture described', 'vergelabs-media-library' ); ?></li></ul>
            </section>
        </div>
    </div>
    <?php
}

/* ------------------------------------------------------------------ Search */

function vergeml_ai_tab_search( $c, $can_configure ) {
    $settings = function_exists( 'vergeml_ai_settings' ) ? vergeml_ai_settings() : array();
    ?>
    <div class="vgml-cols vgml-ai-cols">
    <div class="vgml-cols-main">

        <section class="vgml-ai-sec">
            <h2 class="vgml-kicker"><?php esc_html_e( 'What a search in the media library matches', 'vergelabs-media-library' ); ?></h2>
            <table class="vgml-table vgml-ai-table">
                <tr>
                    <th><?php esc_html_e( 'Looks in', 'vergelabs-media-library' ); ?></th>
                    <th><?php esc_html_e( 'Written by', 'vergelabs-media-library' ); ?></th>
                    <th><?php esc_html_e( 'On this site', 'vergelabs-media-library' ); ?></th>
                </tr>
                <tr>
                    <td><b><?php esc_html_e( 'File name, title, caption, description', 'vergelabs-media-library' ); ?></b></td>
                    <td><?php esc_html_e( 'WordPress, and you', 'vergelabs-media-library' ); ?></td>
                    <?php /* translators: %s: a number of pictures */ ?>
                    <td class="n"><?php echo esc_html( sprintf( _n( '%s picture', '%s pictures', $c['images'], 'vergelabs-media-library' ), number_format_i18n( $c['images'] ) ) ); ?></td>
                </tr>
                <tr>
                    <td><b><?php esc_html_e( 'Caption, tags, title', 'vergelabs-media-library' ); ?></b></td>
                    <td><?php esc_html_e( 'The model, on the Describe tab', 'vergelabs-media-library' ); ?></td>
                    <?php /* translators: 1: how many are described, 2: how many distinct tags they carry */ ?>
                    <td class="n"><?php echo esc_html( sprintf( __( '%1$s described · %2$s distinct tags', 'vergelabs-media-library' ), number_format_i18n( $c['described'] ), number_format_i18n( $c['tags'] ) ) ); ?></td>
                </tr>
                <tr>
                    <td><b><?php esc_html_e( 'Meaning', 'vergelabs-media-library' ); ?></b></td>
                    <td><?php esc_html_e( 'The model\'s vector of each picture · offered as "Search by meaning" beside a word search', 'vergelabs-media-library' ); ?></td>
                    <?php /* translators: 1: how many are described, 2: how many there are */ ?>
                    <td class="n"><?php echo esc_html( sprintf( __( '%1$s of %2$s', 'vergelabs-media-library' ), number_format_i18n( $c['described'] ), number_format_i18n( $c['described'] ) ) ); ?></td>
                </tr>
            </table>
            <?php if ( $can_configure ) : ?>
            <label class="vgml-check vgml-ai-switch"><input type="checkbox" id="vgml-ai-enrich" <?php checked( ! empty( $settings['enrich_search'] ) ); ?>><span><?php esc_html_e( 'Search also matches the caption, tags and title the model wrote', 'vergelabs-media-library' ); ?></span></label>
            <ul class="vgml-facts"><li id="vgml-ai-enrich-note"><?php esc_html_e( 'Saves as it is changed · applies to the next search', 'vergelabs-media-library' ); ?></li></ul>
            <?php endif; ?>
        </section>

        <section class="vgml-ai-sec">
            <h2 class="vgml-kicker"><?php esc_html_e( 'Try a query', 'vergelabs-media-library' ); ?></h2>
            <form class="vgml-ai-query" id="vgml-search-form">
                <input type="search" class="vgml-input" id="vgml-search-q" placeholder="<?php esc_attr_e( 'A word, or a phrase', 'vergelabs-media-library' ); ?>" aria-label="<?php esc_attr_e( 'A word, or a phrase', 'vergelabs-media-library' ); ?>">
                <button type="submit" class="vgml-btn" id="vgml-search-go"><?php esc_html_e( 'Search', 'vergelabs-media-library' ); ?></button>
            </form>
            <div id="vgml-search-out" class="vgml-search-out"></div>
        </section>

    </div><!-- /main -->

    <aside class="vgml-cols-rail">
        <div class="vgml-rail-block">
            <h6 class="vgml-kicker"><?php esc_html_e( 'By word', 'vergelabs-media-library' ); ?></h6>
            <ul class="vgml-facts">
                <li><?php esc_html_e( 'Every word has to match something, in any field', 'vergelabs-media-library' ); ?></li>
                <li><?php esc_html_e( 'Word parts count: "boot" finds "boots" and "bootstrap"', 'vergelabs-media-library' ); ?></li>
                <li><?php esc_html_e( 'The media library shows what it finds by date, newest first', 'vergelabs-media-library' ); ?></li>
            </ul>
        </div>
        <div class="vgml-rail-block">
            <h6 class="vgml-kicker"><?php esc_html_e( 'By meaning', 'vergelabs-media-library' ); ?></h6>
            <ul class="vgml-facts">
                <li><?php esc_html_e( 'Each described picture carries a vector of what it shows', 'vergelabs-media-library' ); ?></li>
                <li><?php esc_html_e( 'The phrase you type gets one from the service · no credit', 'vergelabs-media-library' ); ?></li>
                <?php /* translators: 1: how many pictures are compared, 2: how many there are */ ?>
                <li><?php echo esc_html( sprintf( __( 'Every picture is compared: %1$s of %2$s', 'vergelabs-media-library' ), number_format_i18n( $c['described'] ), number_format_i18n( $c['described'] ) ) ); ?></li>
                <?php /* translators: %s: the lowest score that is shown */ ?>
                <li><?php echo esc_html( sprintf( __( 'Below %s nothing is shown', 'vergelabs-media-library' ), defined( 'VERGEML_MEANING_FLOOR' ) ? number_format_i18n( VERGEML_MEANING_FLOOR, 2 ) : '0.22' ) ); ?></li>
            </ul>
        </div>
    </aside>

    </div><!-- /cols -->
    <?php
}
