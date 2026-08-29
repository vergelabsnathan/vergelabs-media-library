<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 *  Where you are, and what to do next.
 *
 *  Every feature in this plugin works. None of them says what has to happen
 *  before it will do anything useful, so a person presses "Fix missing alt
 *  text" on a library nothing has described and gets nothing, with no
 *  explanation. The order was only ever in the developers' heads.
 *
 *  core/librarian.php already solved this for itself:
 *  vergeml_librarian_stage() returns unscanned / unindexed / unproposed /
 *  ready by asking the health state and the proposal count, and turns each one
 *  into a sentence somebody can act on. This is that idea, for all of them,
 *  with a screen.
 *
 *  ## Rules this screen keeps
 *
 *  - **Exactly one stage says "do this next".** Everything before it is done,
 *    everything after it waits. A list where three things are equally urgent
 *    is the screen we already had.
 *  - **A stage that cannot run says why, and names what has to happen first.**
 *    Never a disabled button with no explanation.
 *  - **Cost is stated before it is spent**, in files and in credits.
 *  - **Nothing here is a wizard.** Every screen stays reachable; this says
 *    which one is worth opening now.
 *
 *  Every read is guarded with function_exists(): in safe mode the feature
 *  files are not loaded, and a stage whose feature is switched off simply is
 *  not in the list.
 */


/** Loaded once per request. Several stages ask the same questions. */
function vergeml_journey_facts() {

    static $facts = null;

    if ( null !== $facts ) {
        return $facts;
    }

    global $wpdb;

    $mime = $wpdb->esc_like( 'image/' ) . '%';

    /*
     *  Counted, not listed. vergeml_ai_pending() returns ids, which is right
     *  for a run and wrong for a dashboard -- on twenty thousand images it
     *  reads twenty thousand rows to print one number.
     */
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $images = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE %s",
        $mime
    ) );

    $described = 0;
    $undescribed = $images;

    if ( isset( $wpdb->vergeml_ai_index ) ) {
        $described = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->vergeml_ai_index} WHERE error = ''" );
        $undescribed = max( 0, $images - $described );
    }

    $no_alt = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
          LEFT JOIN {$wpdb->postmeta} alt ON alt.post_id = p.ID AND alt.meta_key = '_wp_attachment_image_alt'
         WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE %s
           AND ( alt.meta_id IS NULL OR alt.meta_value = '' )",
        $mime
    ) );
    // phpcs:enable

    $folders = 0;

    if ( function_exists( 'vergeml_tree_taxonomies' ) ) {
        foreach ( vergeml_tree_taxonomies() as $taxonomy ) {
            $count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
            $folders += is_wp_error( $count ) ? 0 : (int) $count;
        }
    }

    $settings = function_exists( 'vergeml_ai_settings' ) ? vergeml_ai_settings() : array();
    $credits  = get_option( 'vergeml_ai_credits', array() );

    /*
     *  The smart-folder counts come back as one UNION, so unused, large and
     *  unattached cost a single query between them rather than three.
     */
    $smart = function_exists( 'vergeml_smart_counts' ) ? vergeml_smart_counts() : array();

    $unfiled = 0;

    if ( function_exists( 'vergeml_count_unassigned' ) && function_exists( 'vergeml_tree_taxonomies' ) ) {
        $trees = vergeml_tree_taxonomies();
        if ( ! empty( $trees[0] ) ) {
            $unfiled = (int) vergeml_count_unassigned( $trees[0] );
        }
    }

    /*
     *  Eight real pictures with what the model said about them. A dashboard
     *  that shows the library beats one that only counts it -- this is the row
     *  that proves the thing works rather than asserting it.
     */
    $recent = array();

    if ( isset( $wpdb->vergeml_ai_index ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $recent = $wpdb->get_results(
            "SELECT attachment_id, caption, kind FROM {$wpdb->vergeml_ai_index}
              WHERE error = '' AND caption <> ''
              ORDER BY described_at DESC LIMIT 8",
            ARRAY_A
        );
        // phpcs:enable
        $recent = is_array( $recent ) ? $recent : array();
    }

    $facts = array(
        'images'      => $images,
        'described'   => $described,
        'undescribed' => $undescribed,
        'no_alt'      => $no_alt,
        'folders'     => $folders,
        'files'       => (int) wp_count_posts( 'attachment' )->inherit,
        'demo'        => ! empty( $settings['mock'] ) || defined( 'VERGEML_AI_MOCK' ),
        'licensed'    => function_exists( 'vergeml_ai_unseal' ) && ! empty( $settings['license_key'] )
            && '' !== vergeml_ai_unseal( $settings['license_key'] ),
        'credits'     => isset( $credits['remaining'] ) ? (int) $credits['remaining'] : null,
        'unfiled'     => $unfiled,
        'unused'      => isset( $smart['unused'] ) ? (int) $smart['unused'] : null,
        'large'       => isset( $smart['large'] ) ? (int) $smart['large'] : null,
        'recent'      => $recent,
    );

    return $facts;
}


function vergeml_journey_url( $page ) {
    return admin_url( 'admin.php?page=' . $page );
}


/**
 *  vergeml_journey_stages
 *
 *  The ordered list, before the model decides which one is "now". Each stage
 *  declares only whether it is DONE and whether it is BLOCKED; nothing here
 *  decides urgency, because urgency is a property of the list and not of a row.
 */
function vergeml_journey_stages() {

    $f = vergeml_journey_facts();
    $stages = array();

    /* ------------------------------------------------------ what you have */

    $stages[] = array(
        'id'    => 'library',
        'title' => __( 'Your library', 'vergelabs-media-library' ),
        'done'  => true,
        'text'  => sprintf(
            /* translators: 1: files, 2: folders, 3: images with no alt text. */
            __( '%1$s files in %2$s folders. %3$s images have no alt text.', 'vergelabs-media-library' ),
            number_format_i18n( $f['files'] ),
            number_format_i18n( $f['folders'] ),
            number_format_i18n( $f['no_alt'] )
        ),
        'action' => __( 'Open the library', 'vergelabs-media-library' ),
        'url'    => admin_url( 'upload.php' ),
    );

    /* --------------------------------------------------------- the way in */

    if ( function_exists( 'vergeml_ai_settings' ) ) {

        if ( $f['licensed'] ) {
            $text = null === $f['credits']
                ? __( 'A licence is connected. Your credit balance shows after the first run.', 'vergelabs-media-library' )
                : sprintf(
                    /* translators: %s: credits remaining. */
                    __( 'A licence is connected, with %s credits left.', 'vergelabs-media-library' ),
                    number_format_i18n( $f['credits'] )
                );
        } elseif ( $f['demo'] ) {
            $text = __( 'Demo mode is on, so you can try everything free. Descriptions are invented on this server from file names — nothing is sent anywhere and nothing is charged. The captions are not real.', 'vergelabs-media-library' );
        } else {
            $text = __( 'Turn on demo mode to try the whole thing for nothing — no account, no key, and nothing leaves your site. Or enter a licence key to describe your images for real.', 'vergelabs-media-library' );
        }

        $stages[] = array(
            'id'     => 'access',
            'title'  => __( 'Licence', 'vergelabs-media-library' ),
            'done'   => $f['licensed'] || $f['demo'],
            'text'   => $text,
            'action' => ( $f['licensed'] || $f['demo'] )
                ? __( 'Change this', 'vergelabs-media-library' )
                : __( 'Start here', 'vergelabs-media-library' ),
            'url'    => vergeml_journey_url( 'media-ai' ),
        );
    }

    /* ------------------------------------------------------- the copies */

    if ( function_exists( 'vergeml_health_state' ) ) {

        $health = vergeml_health_state();
        $done   = ! empty( $health['finished'] );

        $stages[] = array(
            'id'     => 'duplicates',
            'title'  => __( 'Duplicate files', 'vergelabs-media-library' ),
            'done'   => $done,
            'text'   => $done
                ? __( 'The library has been read. Duplicates and unused files are known, and the Librarian can use them.', 'vergelabs-media-library' )
                : __( 'Reads every file once to find which are copies of each other. Free, nothing is sent anywhere, and it changes nothing — but the Librarian needs it before it can propose anything.', 'vergelabs-media-library' ),
            'action' => $done ? __( 'See the report', 'vergelabs-media-library' ) : __( 'Scan the library', 'vergelabs-media-library' ),
            'url'    => vergeml_journey_url( 'media-health' ),
        );
    }

    /* ----------------------------------------------------- the describing */

    if ( function_exists( 'vergeml_ai_pending' ) ) {

        $blocked = ( ! $f['licensed'] && ! $f['demo'] )
            ? __( 'Turn on demo mode or enter a licence key first — the stage above.', 'vergelabs-media-library' )
            : '';

        if ( 0 === $f['images'] ) {
            $text = __( 'There are no images in this library yet.', 'vergelabs-media-library' );
        } elseif ( 0 === $f['undescribed'] ) {
            $text = sprintf(
                /* translators: %s: number of images described. */
                __( 'All %s images are described. Search finds what your pictures show, and the folder tree has grown a group built from them.', 'vergelabs-media-library' ),
                number_format_i18n( $f['described'] )
            );
        } else {
            $text = sprintf(
                /* translators: 1: images still to describe, 2: credits it will cost. */
                __( '%1$s images still to describe. That would use %2$s credits — one per image. You can watch it here or let it run in the background with the tab closed.', 'vergelabs-media-library' ),
                number_format_i18n( $f['undescribed'] ),
                number_format_i18n( $f['undescribed'] )
            );
        }

        $stages[] = array(
            'id'      => 'describe',
            'title'   => __( 'Describe your images', 'vergelabs-media-library' ),
            'done'    => $f['images'] > 0 && 0 === $f['undescribed'],
            'blocked' => $blocked,
            'text'    => $text,
            'action'  => __( 'Describe them', 'vergelabs-media-library' ),
            'url'     => vergeml_journey_url( 'media-ai' ),
        );

        /* -------------------------------------------------------- the alt */

        $stages[] = array(
            'id'      => 'alt',
            'title'   => __( 'Missing alt text', 'vergelabs-media-library' ),
            'done'    => 0 === $f['no_alt'],
            'blocked' => ( 0 === $f['described'] && ! $f['demo'] && ! $f['licensed'] )
                ? __( 'Nothing is described yet, and alt text is written from the description.', 'vergelabs-media-library' )
                : '',
            'text'    => 0 === $f['no_alt']
                ? __( 'Every image has alt text. Screen readers and search engines can both read this library.', 'vergelabs-media-library' )
                : sprintf(
                    /* translators: %s: images with no alt text. */
                    __( '%s images have none. It is written from the description, only where alt is empty, and never over anything you wrote yourself — and everything it writes can be taken back out again.', 'vergelabs-media-library' ),
                    number_format_i18n( $f['no_alt'] )
                ),
            'action'  => __( 'Fill them in', 'vergelabs-media-library' ),
            'url'     => vergeml_journey_url( 'media-ai' ),
        );
    }

    /* -------------------------------------------------------- the filing */

    if ( function_exists( 'vergeml_librarian_stage' ) ) {

        $stage = vergeml_librarian_stage();

        $blocked = '';

        if ( 'unscanned' === $stage ) {
            $blocked = __( 'The duplicate scan has to run first — the stage above.', 'vergelabs-media-library' );
        } elseif ( 'unindexed' === $stage ) {
            $blocked = __( 'Your images need describing before it can group them by subject. Filing by date and file type needs nothing and is available now.', 'vergelabs-media-library' );
        }

        $stages[] = array(
            'id'      => 'file',
            'title'   => __( 'Unfiled files', 'vergelabs-media-library' ),
            'done'    => false,
            'blocked' => $blocked,
            'text'    => function_exists( 'vergeml_librarian_card_text' )
                ? vergeml_librarian_card_text()
                : __( 'See the folders this library would get, change them, apply them — and put it all back with one click.', 'vergelabs-media-library' ),
            'action'  => __( 'Open the Librarian', 'vergelabs-media-library' ),
            'url'     => vergeml_journey_url( 'media-librarian' ),
        );
    }

    /* ------------------------------------------------------- the side door */

    if ( function_exists( 'vergeml_import_read' ) ) {
        $stages[] = array(
            'id'     => 'import',
            'title'  => __( 'Import folders', 'vergelabs-media-library' ),
            'aside'  => true,
            'done'   => false,
            'text'   => __( 'Bring them over from FileBird, Premio Folders, WP Media Folder, HappyFiles, Wicked Folders or Real Media Library — or from a spreadsheet. You see what it will do before it does it, and it can be undone.', 'vergelabs-media-library' ),
            'action' => __( 'Import folders', 'vergelabs-media-library' ),
            'url'    => vergeml_journey_url( 'media-import-folders' ),
        );
    }

    /**
     *  Filter the journey.
     *
     *  @since 3.9.0
     *
     *  @param array $stages  ordered; see the docblock at the top of this file.
     */
    return apply_filters( 'vergeml_journey_stages', $stages );
}


/**
 *  vergeml_journey
 *
 *  Decides state. Exactly one stage is 'now' -- the first that is neither done
 *  nor blocked -- everything before it is 'done' or 'blocked', everything after
 *  it is 'later'. A list where three rows are equally urgent is the screen this
 *  replaces.
 *
 *  Asides are never 'now': they are things you may want, not things you are
 *  waiting on.
 */
function vergeml_journey() {

    $stages = vergeml_journey_stages();
    $found  = false;
    $out    = array();

    foreach ( $stages as $stage ) {

        $stage = wp_parse_args( $stage, array(
            'id'      => '',
            'title'   => '',
            'text'    => '',
            'action'  => '',
            'url'     => '',
            'done'    => false,
            'blocked' => '',
            'aside'   => false,
        ) );

        if ( ! empty( $stage['aside'] ) ) {
            $stage['state'] = 'aside';
        } elseif ( ! empty( $stage['done'] ) ) {
            $stage['state'] = 'done';
        } elseif ( '' !== $stage['blocked'] ) {
            $stage['state'] = 'blocked';
        } elseif ( ! $found ) {
            $stage['state'] = 'now';
            $found = true;
        } else {
            $stage['state'] = 'later';
        }

        $out[] = $stage;
    }

    return $out;
}


function vergeml_journey_state_word( $state ) {

    $words = array(
        'done'    => __( 'Done', 'vergelabs-media-library' ),
        'now'     => __( 'Do this next', 'vergelabs-media-library' ),
        'blocked' => __( 'Not yet', 'vergelabs-media-library' ),
        'later'   => __( 'Later', 'vergelabs-media-library' ),
        'aside'   => __( 'Any time', 'vergelabs-media-library' ),
    );

    return isset( $words[ $state ] ) ? $words[ $state ] : '';
}


/* ----------------------------------------------------------------- the menu */

/*
 *  No menu registration here any more.
 *
 *  This screen is the plugin's top-level page -- core/admin-menu.php points
 *  add_menu_page() straight at vergeml_journey_screen(). It used to be a
 *  submenu called "Start" sitting above a second home screen called
 *  "Overview", which was two front doors to the same house.
 */


/**
 *  A number is worth a paragraph.
 *
 *  The first version of this screen was seven rows of prose with a button
 *  under each, which is a settings page with different words. This one leads
 *  with figures, shows how much of the library is covered as a bar rather than
 *  as a sentence, puts the single next action in one place, and then shows
 *  eight actual pictures with what the model said about them -- because a
 *  dashboard that shows the library beats one that only counts it.
 *
 *  Deliberately not used: ring gauges, numbered circles joined by a line, and
 *  cards with a coloured top border and an icon in a rounded square. The bar
 *  geometry is lifted from .vgml-import-bar, which this plugin already draws
 *  during an import, so the progress in both places is the same shape.
 */
function vergeml_journey_screen() {

    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'vergelabs-media-library' ) );
    }

    $f      = vergeml_journey_facts();
    $stages = vergeml_journey();

    $next = null;

    foreach ( $stages as $stage ) {
        if ( 'now' === $stage['state'] ) {
            $next = $stage;
        }
    }

    $pct = function ( $part, $whole ) {
        return $whole > 0 ? (int) round( ( $part / $whole ) * 100 ) : 100;
    };

    $described_pct = $pct( $f['described'], $f['images'] );
    $alt_pct       = $pct( $f['images'] - $f['no_alt'], $f['images'] );

    $figures = array(
        array( 'n' => $f['files'],   'label' => __( 'files', 'vergelabs-media-library' ),   'url' => admin_url( 'upload.php' ) ),
        array( 'n' => $f['folders'], 'label' => __( 'folders', 'vergelabs-media-library' ), 'url' => admin_url( 'upload.php' ) ),
        array( 'n' => $f['unfiled'], 'label' => __( 'unfiled', 'vergelabs-media-library' ), 'url' => vergeml_journey_url( 'media-librarian' ), 'warn' => $f['unfiled'] > 0 ),
        array( 'n' => $f['no_alt'],  'label' => __( 'no alt text', 'vergelabs-media-library' ), 'url' => vergeml_journey_url( 'media-ai' ), 'warn' => $f['no_alt'] > 0 ),
    );

    if ( null !== $f['unused'] ) {
        $figures[] = array( 'n' => $f['unused'], 'label' => __( 'used nowhere', 'vergelabs-media-library' ), 'url' => vergeml_journey_url( 'media-health' ), 'warn' => $f['unused'] > 0 );
    }

    if ( null !== $f['credits'] ) {
        $figures[] = array( 'n' => $f['credits'], 'label' => __( 'credits left', 'vergelabs-media-library' ), 'url' => vergeml_journey_url( 'media-ai' ) );
    }

    ?>
    <div class="wrap vgml-dash">

        <div class="vgml-home-head">
            <h1><?php esc_html_e( 'Your media library', 'vergelabs-media-library' ); ?></h1>
        </div>

        <!-- the numbers -->
        <div class="vgml-figures">
            <?php foreach ( $figures as $fig ) : ?>
                <a class="vgml-figure<?php echo ! empty( $fig['warn'] ) ? ' is-warn' : ''; ?>" href="<?php echo esc_url( $fig['url'] ); ?>">
                    <span class="vgml-figure-n"><?php echo esc_html( number_format_i18n( $fig['n'] ) ); ?></span>
                    <span class="vgml-figure-l"><?php echo esc_html( $fig['label'] ); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ( $f['images'] > 0 ) : ?>
        <!-- how far along, as a shape rather than a sentence -->
        <div class="vgml-meters">
            <?php
            $meters = array(
                array(
                    'label' => __( 'Described', 'vergelabs-media-library' ),
                    'pct'   => $described_pct,
                    'note'  => sprintf(
                        /* translators: 1: images described, 2: images in total. */
                        __( '%1$s of %2$s', 'vergelabs-media-library' ),
                        number_format_i18n( $f['described'] ),
                        number_format_i18n( $f['images'] )
                    ),
                ),
                array(
                    'label' => __( 'Alt text', 'vergelabs-media-library' ),
                    'pct'   => $alt_pct,
                    'note'  => sprintf(
                        /* translators: 1: images with alt text, 2: images in total. */
                        __( '%1$s of %2$s', 'vergelabs-media-library' ),
                        number_format_i18n( $f['images'] - $f['no_alt'] ),
                        number_format_i18n( $f['images'] )
                    ),
                ),
            );
            ?>
            <?php foreach ( $meters as $m ) : ?>
                <div class="vgml-meter">
                    <div class="vgml-meter-top">
                        <span class="vgml-meter-label"><?php echo esc_html( $m['label'] ); ?></span>
                        <span class="vgml-meter-note"><?php echo esc_html( $m['note'] ); ?></span>
                    </div>
                    <div class="vgml-import-bar">
                        <div class="vgml-import-fill" style="width:<?php echo esc_attr( $m['pct'] ); ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( $next ) : ?>
        <!-- the one thing to do -->
        <div class="vgml-next">
            <p class="vgml-next-eyebrow"><?php esc_html_e( 'Do this next', 'vergelabs-media-library' ); ?></p>
            <h2><?php echo esc_html( $next['title'] ); ?></h2>
            <p class="vgml-next-text"><?php echo esc_html( $next['text'] ); ?></p>
            <?php if ( $next['action'] && $next['url'] ) : ?>
                <a class="button button-primary" href="<?php echo esc_url( $next['url'] ); ?>"><?php echo esc_html( $next['action'] ); ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $f['recent'] ) ) : ?>
        <!-- the library itself, and what the model saw in it -->
        <div class="vgml-seen">
            <div class="vgml-seen-head">
                <h2><?php esc_html_e( 'Recently described', 'vergelabs-media-library' ); ?></h2>
                <a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'Open the library', 'vergelabs-media-library' ); ?></a>
            </div>
            <ul class="vgml-seen-strip">
                <?php foreach ( $f['recent'] as $shot ) : ?>
                    <?php
                    $shot_id  = (int) $shot['attachment_id'];
                    $shot_src = wp_get_attachment_image_url( $shot_id, 'thumbnail' );

                    if ( ! $shot_src ) {
                        continue;
                    }
                    ?>
                    <li class="vgml-seen-item">
                        <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $shot_id . '&action=edit' ) ); ?>">
                            <img src="<?php echo esc_url( $shot_src ); ?>" alt="" loading="lazy" width="150" height="150">
                            <span class="vgml-seen-kind"><?php echo esc_html( $shot['kind'] ); ?></span>
                            <span class="vgml-seen-caption"><?php echo esc_html( $shot['caption'] ); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php
        /*
         *  Only what is still outstanding.
         *
         *  This listed every stage including the finished ones, so on a set-up
         *  library it was five rows saying "Done" -- a section whose entire
         *  content was the absence of anything to do. Finished work is already
         *  visible in the figures and the bars above.
         */
        $waiting = array();

        foreach ( $stages as $stage ) {
            if ( 'now' !== $stage['state'] && 'library' !== $stage['id'] && 'done' !== $stage['state'] ) {
                $waiting[] = $stage;
            }
        }
        ?>
        <?php if ( ! empty( $waiting ) ) : ?>
        <div class="vgml-rest">
            <h2><?php esc_html_e( 'Also worth doing', 'vergelabs-media-library' ); ?></h2>
            <ul class="vgml-rest-list">
                <?php foreach ( $waiting as $stage ) : ?>
                    <li class="vgml-rest-row is-<?php echo esc_attr( $stage['state'] ); ?>">
                        <a href="<?php echo esc_url( $stage['url'] ); ?>"><?php echo esc_html( $stage['title'] ); ?></a>
                        <span class="vgml-rest-state"><?php
                            echo esc_html( 'blocked' === $stage['state'] ? $stage['blocked'] : vergeml_journey_state_word( $stage['state'] ) );
                        ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </div>
    <?php
}


add_action( 'admin_enqueue_scripts', 'vergeml_journey_assets', 22 );

function vergeml_journey_assets( $hook ) {

    if ( ! defined( 'VERGEML_MENU' ) || 'toplevel_page_' . VERGEML_MENU !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'vergeml-journey',
        plugins_url( 'css/vergeml-journey.css', VERGEML_FILE ),
        array(),
        vergeml_asset_ver( 'css/vergeml-journey.css' )
    );
}
