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
/**
 *  Forget the dashboard's numbers, because something just changed them.
 *
 *  Called from the writes that move the figures -- an index row written, a
 *  title rewritten -- so the minute-long cache below never shows somebody the
 *  count from before the thing they just did.
 */
function vergeml_journey_touch() {
    delete_transient( 'vergeml_journey_facts' );
    delete_transient( 'vergeml_rename_pending_count' );
    delete_transient( 'vergeml_file_pending_count' );
}


function vergeml_journey_facts() {

    // Per site, for the same reason as every request cache in this plugin:
    // a network job that switches blogs must not see the last site's figures.
    static $facts = array();

    $blog = get_current_blog_id();

    if ( isset( $facts[ $blog ] ) ) {
        return $facts[ $blog ];
    }

    /*
     *  Sixty seconds, because these are counts over the whole library.
     *
     *  Measured at 50,000 files on 31-08-2026: two seconds and fourteen
     *  statements to produce them, on every load of every plugin screen. The
     *  numbers on a dashboard do not need to be true to the second; they need
     *  to be true to the last thing the person did, which vergeml_journey_touch()
     *  sees to. Everyone else gets last minute's answer in a millisecond.
     */
    $cached = get_transient( 'vergeml_journey_facts' );

    if ( is_array( $cached ) && isset( $cached['images'] ) ) {
        $facts[ $blog ] = $cached;
        return $facts[ $blog ];
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
    $stale = 0;

    if ( isset( $wpdb->vergeml_ai_index ) ) {

        /*
         *  Two numbers, one query.
         *
         *  The budget for this screen is twelve and it is already at twelve,
         *  so "how many were written under an older prompt" had to come out of
         *  the query that was already counting descriptions rather than on top
         *  of it. The subselect is the newest row's fingerprint -- the same
         *  thing vergeml_index_current_stamp() reads.
         */
        /*
         *  The newest fingerprint first, on its own, then the count against
         *  it. As one statement with the subselect inline it cost 785ms on
         *  20,000 index rows; the planner would not treat the subselect as the
         *  constant it is. Two statements, the first answered from the
         *  described_at index, and the whole thing is a scan with a compare.
         */
        $current = (string) $wpdb->get_var(
            "SELECT prompt_hash FROM {$wpdb->vergeml_ai_index}
              WHERE error = '' AND described_at IS NOT NULL AND model <> 'mock'
              ORDER BY described_at DESC, attachment_id DESC LIMIT 1"
        );

        $counts = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS described,
                    SUM( CASE WHEN prompt_hash IS NULL OR prompt_hash <> %s THEN 1 ELSE 0 END ) AS stale
               FROM {$wpdb->vergeml_ai_index}
              WHERE error = ''",
            $current
        ), ARRAY_A );

        $described   = isset( $counts['described'] ) ? (int) $counts['described'] : 0;
        $stale       = isset( $counts['stale'] ) ? (int) $counts['stale'] : 0;
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
     *
     *  attachment_id is the tiebreaker, and it is not decoration.
     *  described_at is stamped with current_time( 'mysql' ), which resolves to
     *  the second, so a bulk run lands hundreds of rows on the same value.
     *  Ordering on described_at alone leaves those ties to the index, which
     *  hands back the lowest ids of that second -- the oldest uploads in the
     *  batch, the same eight every time, never the file just described.
     *  vergeml_index_current_stamp() has always broken the tie this way; this
     *  query was the one place that did not.
     */
    $recent = array();

    if ( isset( $wpdb->vergeml_ai_index ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $recent = $wpdb->get_results(
            "SELECT attachment_id, caption, kind FROM {$wpdb->vergeml_ai_index}
              WHERE error = '' AND caption <> '' AND described_at IS NOT NULL
              ORDER BY described_at DESC, attachment_id DESC LIMIT 8",
            ARRAY_A
        );
        // phpcs:enable
        $recent = is_array( $recent ) ? $recent : array();
    }

    $facts[ $blog ] = array(
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
        'stale'       => $stale,
        'unused'      => isset( $smart['unused'] ) ? (int) $smart['unused'] : null,
        'large'       => isset( $smart['large'] ) ? (int) $smart['large'] : null,
        'recent'      => $recent,
    );

    set_transient( 'vergeml_journey_facts', $facts[ $blog ], MINUTE_IN_SECONDS );

    return $facts[ $blog ];
}


/**
 *  vergeml_journey_score
 *
 *  One number for "how is this library doing", out of a hundred.
 *
 *  Four things, weighted by how much they actually cost somebody. Alt text
 *  weighs most because its absence is the only one with a legal and an
 *  accessibility consequence; duplicates weigh least because they cost disk
 *  and nothing else.
 *
 *  Every part is computed from figures already on the screen, so nobody has to
 *  wonder where the number came from -- the parts are shown under it. A score
 *  whose derivation is hidden is a score nobody trusts and nobody acts on.
 */
function vergeml_journey_score() {

    $f = vergeml_journey_facts();

    /*
     *  Nothing to score.
     *
     *  Every share was "1 when the total is 0", which is defensible per part
     *  and absurd in the sum: an empty library scored 85 out of 100 -- full
     *  marks for alt text on no pictures. A number nobody can act on is worse
     *  than no number, so an empty library gets none.
     */
    if ( 0 === $f['files'] ) {
        return array( 'score' => null, 'parts' => array() );
    }

    $share = function ( $good, $all ) {
        return $all > 0 ? min( 1, max( 0, $good / $all ) ) : 1;
    };

    $parts = array(
        array(
            'label'  => __( 'Alt text', 'vergelabs-media-library' ),
            'weight' => 35,
            'share'  => $share( $f['images'] - $f['no_alt'], $f['images'] ),
            'url'    => vergeml_journey_url( 'media-ai' ),
        ),
        array(
            'label'  => __( 'Described', 'vergelabs-media-library' ),
            'weight' => 25,
            'share'  => $share( $f['described'], $f['images'] ),
            'url'    => vergeml_journey_url( 'media-ai' ),
        ),
        array(
            'label'  => __( 'Filed', 'vergelabs-media-library' ),
            'weight' => 25,
            'share'  => $share( $f['files'] - $f['unfiled'], $f['files'] ),
            'url'    => vergeml_journey_url( 'media-librarian' ),
        ),
        array(
            'label'  => __( 'Checked for copies', 'vergelabs-media-library' ),
            'weight' => 15,
            // Binary: the scan has run, or it has not.
            'share'  => ( function_exists( 'vergeml_health_state' ) && ! empty( vergeml_health_state()['finished'] ) ) ? 1 : 0,
            'url'    => vergeml_journey_url( 'media-health' ),
        ),
    );

    $score = 0;

    foreach ( $parts as $at => $part ) {
        $parts[ $at ]['points'] = (int) round( $part['share'] * $part['weight'] );
        $score += $parts[ $at ]['points'];
    }

    return array( 'score' => (int) round( $score ), 'parts' => $parts );
}


/**
 *  The second, sterner half of renaming: the file on disk.
 *
 *  Kept apart from the title rename rather than folded into it, because they
 *  are not the same risk wearing two labels. A title is a field in a database
 *  and changing it breaks nothing. A filename is a URL, and every <img src>
 *  already written into a page points at the old one.
 *
 *  So it is offered second, worded plainly about what it will and will not
 *  reach, and it says nothing at all until the scan that makes it safe has
 *  finished -- without that scan we do not know what points at the file, and
 *  the offer would be a guess dressed as a feature.
 */

function vergeml_journey_file_rename() {

    if ( ! function_exists( 'vergeml_file_pending' ) || ! current_user_can( 'manage_options' ) ) {
        return null;
    }

    // Off until the renamer rewrites everything it moves -- see core/rename-file.php.
    if ( ! ( defined( 'VERGEML_FILE_RENAME' ) && VERGEML_FILE_RENAME ) ) {
        return null;
    }

    $scanned = function_exists( 'vergeml_smart_scan_state' )
        && ! empty( vergeml_smart_scan_state()['finished'] );

    if ( ! $scanned ) {
        return array(
            'blocked' => __( 'Renaming the files themselves needs the usage scan finished first — that is how we know which pages point at each file, so we can update them in the same go.', 'vergelabs-media-library' ),
        );
    }

    // Bounded and remembered -- see vergeml_file_pending_count(). Counting by
    // listing walked every described file and touched the disk for each.
    $pending = function_exists( 'vergeml_file_pending_count' ) ? vergeml_file_pending_count() : array( 'n' => count( vergeml_file_pending() ), 'more' => false );
    $n       = (int) $pending['n'];

    if ( 0 === $n ) {
        return null;
    }

    $shown = number_format_i18n( $n ) . ( ! empty( $pending['more'] ) ? '+' : '' );

    return array(
        'count' => sprintf(
            /* translators: %s: how many files could be renamed on disk. */
            _n(
                '%s file could also be renamed on disk — “vgml-fx-real-498.jpg” to “red-synthesizer-with-controls.jpg”.',
                '%s files could also be renamed on disk — “vgml-fx-real-498.jpg” to “red-synthesizer-with-controls.jpg”.',
                $n,
                'vergelabs-media-library'
            ),
            $shown
        ),
        'note'  => __( 'We move the file and every size of it, and update every page we can see pointing at it. What we cannot reach is a link written into a theme file or a stylesheet, or a page somebody has cached. One click puts it all back.', 'vergelabs-media-library' ),
        'go'    => __( 'Rename the files too', 'vergelabs-media-library' ),
        'url'   => wp_nonce_url( admin_url( 'admin-post.php?action=vergeml_do_file_rename' ), 'vergeml_do_file_rename' ),
    );
}


add_action( 'admin_post_vergeml_do_file_rename', 'vergeml_journey_do_file_rename' );

function vergeml_journey_do_file_rename() {

    // Moves files and edits other posts to match. Closer to a migration than
    // to editing a caption, and the capability says so.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You cannot do that.', 'vergelabs-media-library' ) );
    }

    check_admin_referer( 'vergeml_do_file_rename' );

    $done = function_exists( 'vergeml_file_rename_many' )
        ? count( vergeml_file_rename_many( vergeml_file_pending( 200 ) ) )
        : 0;

    wp_safe_redirect( add_query_arg( 'vgml_files_renamed', $done, vergeml_journey_url( VERGEML_MENU ) ) );
    exit;
}


add_action( 'admin_post_vergeml_undo_file_rename', 'vergeml_journey_undo_file_rename' );

function vergeml_journey_undo_file_rename() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You cannot do that.', 'vergelabs-media-library' ) );
    }

    check_admin_referer( 'vergeml_undo_file_rename' );

    $back = function_exists( 'vergeml_file_undo' ) ? count( vergeml_file_undo() ) : 0;

    wp_safe_redirect( add_query_arg( 'vgml_files_back', $back, vergeml_journey_url( VERGEML_MENU ) ) );
    exit;
}


add_action( 'admin_notices', 'vergeml_journey_file_notice' );

function vergeml_journey_file_notice() {

    if ( isset( $_GET['vgml_files_renamed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $n = (int) $_GET['vgml_files_renamed']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
            esc_html(
                sprintf(
                    /* translators: %s: how many files were renamed on disk. */
                    _n( '%s file renamed, and the pages pointing at it updated.', '%s files renamed, and the pages pointing at them updated.', $n, 'vergelabs-media-library' ),
                    number_format_i18n( $n )
                )
            ),
            esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vergeml_undo_file_rename' ), 'vergeml_undo_file_rename' ) ),
            esc_html__( 'Put the old filenames back', 'vergelabs-media-library' )
        );

        return;
    }

    if ( ! isset( $_GET['vgml_files_back'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $n = (int) $_GET['vgml_files_back']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html(
            sprintf(
                /* translators: %s: how many files got their old filename back. */
                _n( '%s file has its old filename back.', '%s files have their old filenames back.', $n, 'vergelabs-media-library' ),
                number_format_i18n( $n )
            )
        )
    );
}


/*
 *  A screen of ours, by slug.
 *
 *  Through menu_page_url() where the shell can give it, because a URL built by
 *  hand has 403'd twice in this plugin's history -- WordPress checks the page
 *  against the registered submenu, and a string that merely looks right is not
 *  the same as one the menu knows about.
 */
function vergeml_journey_url( $page ) {
    return function_exists( 'vergeml_shell_url' )
        ? vergeml_shell_url( $page )
        : admin_url( 'admin.php?page=' . $page );
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

    /*
     *  Nothing uploaded yet.
     *
     *  Everything below this reports on files, so on an empty library the whole
     *  page was a list of things that had already been achieved by not having
     *  any. One stage, one job.
     */
    if ( 0 === $f['files'] ) {

        /*
         *  Through the filter, like every other return from this function.
         *
         *  The first version returned the array directly and skipped
         *  apply_filters() -- which killed the extension point on any empty
         *  library, and with it every test that injects its own stages. The
         *  suite went from 23/23 to 12/23 the moment the library was emptied,
         *  which is the whole reason it injects rather than reading reality.
         */
        return apply_filters( 'vergeml_journey_stages', array(
            array(
                'id'     => 'upload',
                'title'  => __( 'Add some files', 'vergelabs-media-library' ),
                'done'   => false,
                'text'   => __( 'There is nothing in your media library yet. Upload some pictures and this page will tell you what is worth doing with them — describing them, sorting them into folders, finding the copies.', 'vergelabs-media-library' ),
                'action' => __( 'Upload files', 'vergelabs-media-library' ),
                'url'    => admin_url( 'media-new.php' ),
            ),
        ) );
    }

    $stages[] = array(
        'id'    => 'library',
        'title' => __( 'Your library', 'vergelabs-media-library' ),
        'done'  => true,
        'text'  => sprintf(
            /* translators: 1: files, 2: folders. */
            __( 'You have %1$s files, sorted into %2$s folders.', 'vergelabs-media-library' ),
            number_format_i18n( $f['files'] ),
            number_format_i18n( $f['folders'] )
        ),
        'action' => __( 'Open the library', 'vergelabs-media-library' ),
        'url'    => admin_url( 'upload.php' ),
    );

    /* --------------------------------------------------------- the way in */

    if ( function_exists( 'vergeml_ai_settings' ) ) {

        if ( $f['licensed'] ) {
            $text = null === $f['credits']
                ? __( 'You are set up. Looking at one picture costs one credit, and your balance appears here after the first batch.', 'vergelabs-media-library' )
                : sprintf(
                    /* translators: %s: credits remaining. */
                    __( 'You are set up, with %s credits left. Looking at one picture costs one credit.', 'vergelabs-media-library' ),
                    number_format_i18n( $f['credits'] )
                );
        } elseif ( $f['demo'] ) {
            $text = __( 'You are in demo mode. Nothing is sent anywhere and nothing is charged — but the descriptions you see are invented from file names rather than from the pictures, so do not judge the quality by them. Add a licence key when you want it to really look.', 'vergelabs-media-library' );
        } else {
            $text = __( 'This plugin can look at each of your pictures and write down what is in them, which is what makes everything else here work. Try it free in demo mode first, or add a licence key to start for real.', 'vergelabs-media-library' );
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

    /* ----------------------------------------------------- the describing */

    if ( function_exists( 'vergeml_ai_pending' ) ) {

        $blocked = ( ! $f['licensed'] && ! $f['demo'] )
            ? __( 'Sort out the step above first — we cannot look at anything until you turn on demo mode or add a key.', 'vergelabs-media-library' )
            : '';

        if ( 0 === $f['images'] ) {
            $text = __( 'There are no pictures here yet. Upload some and this page will have something to say.', 'vergelabs-media-library' );
        } elseif ( 0 === $f['undescribed'] ) {
            $text = sprintf(
                /* translators: %s: number of images described. */
                __( 'We have looked at all %s of your pictures and written down what is in them. Searching your media now finds a photo by what it shows, not just by its file name.', 'vergelabs-media-library' ),
                number_format_i18n( $f['described'] )
            );
        } else {
            $text = sprintf(
                /* translators: 1: images, 2: credits it will cost. */
                /* translators: 1: how many pictures, 2: the same number as credits. */
                _n(
                    'We have not looked at %1$s of your pictures yet. When we do, we write down what is in it — so you can find a photo by typing “red bicycle” instead of scrolling, and so the rest of this page has something to work with. It costs %2$s credit, and you can close this tab while it runs.',
                    'We have not looked at %1$s of your pictures yet. When we do, we write down what is in each one — so you can find a photo by typing “red bicycle” instead of scrolling, and so the rest of this page has something to work with. It costs %2$s credits, one a picture, and you can close this tab while it runs.',
                    (int) $f['undescribed'],
                    'vergelabs-media-library'
                ),
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
                ? __( 'We write this from what we saw in the picture, and we have not looked at any of them yet. Do the step above first.', 'vergelabs-media-library' )
                : '',
            'text'    => 0 === $f['no_alt']
                ? __( 'Every picture has alt text — the line a blind visitor’s screen reader reads out, and the one Google reads too. Nothing to do here.', 'vergelabs-media-library' )
                : sprintf(
                    /* translators: %s: images with no alt text. */
                    __( '%s of your pictures have no alt text. That is the line a blind visitor’s screen reader reads out loud instead of showing the picture, and Google reads it as well. We can write it from what we already saw in each one — only where the box is empty, and never over anything you wrote yourself.', 'vergelabs-media-library' ),
                    number_format_i18n( $f['no_alt'] )
                ),
            'action'  => __( 'Fill them in', 'vergelabs-media-library' ),
            'url'     => vergeml_journey_url( 'media-ai' ),
        );
    }

    /* ------------------------------------------------------- the copies */

    /*
     *  After describing, and never the next thing.
     *
     *  This sat third, so somebody who had just uploaded ten photographs was
     *  told the first thing to do was scan them for duplicates -- which is
     *  nobody's reason for uploading, and on files that arrived a minute ago
     *  finds nothing.
     *
     *  It was placed there because sorting needs it. But sorting starts the
     *  scan itself (see vergeml_librarian_card_text), so it was never a step
     *  somebody had to take first -- only one they could take early. It is
     *  worth doing on a library with years in it, and it is an aside.
     */

    if ( function_exists( 'vergeml_health_state' ) ) {

        $health = vergeml_health_state();
        $done   = ! empty( $health['finished'] );

        $stages[] = array(
            'id'     => 'duplicates',
            'aside'  => true,
            'title'  => __( 'Duplicate files', 'vergelabs-media-library' ),
            'done'   => $done,
            'text'   => $done
                ? sprintf(
                    /* translators: %s: number of files used on no page. */
                    __( 'We compared all your files. %s of them are not used on any page or post — you are storing pictures nobody sees. Open this to see which, and how much space they take.', 'vergelabs-media-library' ),
                    number_format_i18n( null === $f['unused'] ? 0 : $f['unused'] )
                )
                : sprintf(
                    /* translators: %s: number of files in the library. */
                    __( 'Most libraries have the same photo in them two or three times — uploaded twice, or saved again at a different size. We compare all %s of your files and show you the copies. It is free, nothing leaves your site, and nothing is deleted or changed.', 'vergelabs-media-library' ),
                    number_format_i18n( $f['files'] )
                ),
            'action' => $done ? __( 'See the report', 'vergelabs-media-library' ) : __( 'Scan the library', 'vergelabs-media-library' ),
            'url'    => vergeml_journey_url( 'media-health' ),
        );
    }

    /* -------------------------------------------------------- the filing */

    if ( function_exists( 'vergeml_librarian_stage' ) ) {

        $stage = vergeml_librarian_stage();

        $blocked = '';
        $loose   = number_format_i18n( $f['unfiled'] );

        /*
         *  Said in full, every time.
         *
         *  This used to borrow vergeml_librarian_card_text(), which reads "a
         *  proposal is waiting, look it over branch by branch, apply it in one
         *  go" -- every word of which assumes you already know what the
         *  Librarian is, what a branch is and what was proposed. It also never
         *  mentioned how many files it was about.
         */
        if ( 'unscanned' === $stage ) {

            /*
             *  Not blocked. This screen starts the duplicate check itself, so
             *  telling somebody to go and do it first was inventing a step
             *  that the software takes for them.
             */
            $text = sprintf(
                /* translators: %s: number of files in no folder. */
                __( '%s of your files are in no folder. We will compare them for copies first, so the same photo does not end up in two folders, and then work out where each one goes.', 'vergelabs-media-library' ),
                $loose
            );

        } elseif ( 'unindexed' === $stage ) {

            $blocked = __( 'To sort them by what is in the pictures, we have to look at them first — the step above. Sorting them by date and file type instead needs nothing and works right now.', 'vergelabs-media-library' );

            $text = sprintf(
                /* translators: %s: number of files in no folder. */
                __( '%s of your files are in no folder at all.', 'vergelabs-media-library' ),
                $loose
            );

        } elseif ( 'ready' === $stage ) {

            $text = sprintf(
                /* translators: %s: number of files in no folder. */
                __( '%s of your files are sitting in one big pile with no folder. We have already worked out a set of folders for them. You will see every folder we want to make and exactly which pictures go in each one — nothing moves until you say so, and one click puts it all back if you change your mind.', 'vergelabs-media-library' ),
                $loose
            );

        } else {

            $text = sprintf(
                /* translators: %s: number of files in no folder. */
                __( '%s of your files are sitting in one big pile with no folder. We can work out folders for them — grouped by what is in the pictures, or just by date and file type, which costs nothing. You see the whole plan and approve it before anything moves.', 'vergelabs-media-library' ),
                $loose
            );
        }

        $stages[] = array(
            'id'      => 'file',
            'title'   => __( 'Unfiled files', 'vergelabs-media-library' ),
            'done'    => 0 === $f['unfiled'],
            'blocked' => $blocked,
            'text'    => 0 === $f['unfiled']
                ? __( 'Every file is in a folder. Nothing is loose.', 'vergelabs-media-library' )
                : $text,
            'action'  => 'ready' === $stage
                ? __( 'See where they would go', 'vergelabs-media-library' )
                : __( 'Sort them into folders', 'vergelabs-media-library' ),
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
            'text'   => __( 'Already sorted your media with FileBird, Premio Folders, WP Media Folder, HappyFiles, Wicked Folders or Real Media Library? We can copy those folders over so you do not start again. The other plugin keeps everything exactly as it is, you see what will happen before it happens, and it can all be undone.', 'vergelabs-media-library' ),
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


/**
 *  What can be done with this library, right now.
 *
 *  This screen and the sort screen had grown into two answers to the same
 *  question. The dashboard listed stages -- "Do this next", "Also worth doing"
 *  -- and the sort screen listed four things you could do, with different
 *  words for the same work, and neither knew the other existed. Somebody
 *  wanting alt text found the button behind a screen named after folders,
 *  having first been told something different by the front page.
 *
 *  So there is one list, and it is here, because this is the screen the plugin
 *  opens on. Two of the four act in place -- alt text and names are free and
 *  instant, and sending somebody to another screen to press a second button
 *  would be the handoff this is removing. The other two lead somewhere because
 *  they genuinely are somewhere: a folder proposal is a document to read, and
 *  the duplicate report is a screen of its own.
 *
 *  Built from vergeml_journey_facts(), which the page has already read, so the
 *  whole list costs no queries beyond the two counts below.
 */

function vergeml_journey_todo() {

    $f    = vergeml_journey_facts();
    $todo = array();

    /* ---------------------------------------------------------- alt text */

    $alt_ready = function_exists( 'vergeml_ai_alt_pending' ) ? count( vergeml_ai_alt_pending() ) : 0;

    $todo[] = array(
        'id'    => 'alt',
        'title' => __( 'Alt text', 'vergelabs-media-library' ),
        'note'  => __( 'The line a screen reader reads out instead of showing the picture, and the line Google reads. It was written when we looked at your pictures, so putting it on the files costs nothing.', 'vergelabs-media-library' ),
        'n'     => $alt_ready,
        'count' => sprintf(
            /* translators: %s: how many pictures are waiting for their alt text. */
            _n( '%s picture is waiting for it', '%s pictures are waiting for it', $alt_ready, 'vergelabs-media-library' ),
            number_format_i18n( $alt_ready )
        ),
        'go'    => __( 'Write the alt text', 'vergelabs-media-library' ),
        'url'   => wp_nonce_url( admin_url( 'admin-post.php?action=vergeml_do_alt' ), 'vergeml_do_alt' ),
        'done'  => 0 === (int) $f['no_alt']
            ? __( 'Every picture has alt text.', 'vergelabs-media-library' )
            : __( 'Nothing waiting — the rest have not been looked at yet.', 'vergelabs-media-library' ),
    );

    /* ------------------------------------------------------------- names */

    /*
     *  Two different jobs, and the card used to headline the wrong one.
     *
     *  The title in WordPress and the file name on disk are renamed by separate
     *  actions, and on a library that has been described once the first is
     *  nearly always finished while the second has not started. So the card
     *  counted 13 titles, said "13 files could be named after what they show",
     *  and put the 492 files still called pexels-ealfro-13849192-scaled.jpg in
     *  a line underneath -- with copy about "Photo 498" that was describing the
     *  number it was not showing.
     *
     *  Worse was the done state: those last 13 titles would have turned the
     *  card into "Every file is named after what it shows" over five hundred
     *  files whose names are still the photographer's upload.
     *
     *  So the count leads with whatever is actually outstanding, and each
     *  number says which of the two it is.
     */
    /*
     *  Counted, never listed. count( vergeml_rename_pending() ) walked every
     *  described file with two queries each -- 101,615 queries and 54 seconds
     *  on a 50,000-file library, measured 31-08-2026 -- to print "13".
     */
    $names = function_exists( 'vergeml_rename_pending_count' ) ? vergeml_rename_pending_count() : 0;

    // The on-disk renamer is switched off until it is whole; while it is,
    // the screen speaks only about titles and promises nothing about files.
    $file_feature = defined( 'VERGEML_FILE_RENAME' ) && VERGEML_FILE_RENAME;
    $files        = $file_feature && function_exists( 'vergeml_file_pending_count' ) ? (int) vergeml_file_pending_count()['n'] : 0;

    $todo[] = array(
        'id'    => 'names',
        'title' => __( 'File names', 'vergelabs-media-library' ),
        'note'  => __( '“Photo 498” tells nobody anything. We can name each file after what is in it — “Red Synthesizer with Controls” — from the same look. The title in WordPress and the file on disk are two separate changes. Anything you named yourself is left alone, and one click puts the old names back.', 'vergelabs-media-library' ),
        'n'     => $names,
        'count' => sprintf(
            /* translators: %s: how many titles could be rewritten. */
            _n( '%s title could be written from what the picture shows', '%s titles could be written from what the pictures show', $names, 'vergelabs-media-library' ),
            number_format_i18n( $names )
        ),
        'go'    => __( 'Rewrite the titles', 'vergelabs-media-library' ),
        'url'   => wp_nonce_url( admin_url( 'admin-post.php?action=vergeml_do_rename' ), 'vergeml_do_rename' ),

        /*
         *  Only true when the files on disk are done too, which is the whole
         *  point of the fix -- "every file is named" while 492 are not is the
         *  one sentence this screen must never say.
         */
        'done'  => ! $file_feature
            ? __( 'Every title says what the picture shows.', 'vergelabs-media-library' )
            : ( 0 === $files
                ? __( 'Every title and every file name says what the picture shows.', 'vergelabs-media-library' )
                : __( 'Every title is written. The files on disk are still named as they were uploaded.', 'vergelabs-media-library' ) ),

        'more'  => vergeml_journey_file_rename(),
    );

    /* ----------------------------------------------------------- folders */

    $todo[] = array(
        'id'    => 'folders',
        'title' => __( 'Folders', 'vergelabs-media-library' ),
        'note'  => __( 'Group the pictures into folders by what they have in common, or by when they were uploaded. You read the whole list and approve it before a single file moves.', 'vergelabs-media-library' ),
        'n'     => (int) $f['unfiled'],
        'count' => sprintf(
            /* translators: %s: how many files are in no folder. */
            _n( '%s file is in no folder', '%s files are in no folder', (int) $f['unfiled'], 'vergelabs-media-library' ),
            number_format_i18n( $f['unfiled'] )
        ),
        'go'    => __( 'Work out the folders', 'vergelabs-media-library' ),
        'url'   => vergeml_journey_url( 'media-librarian' ),
        'done'  => __( 'Everything is in a folder.', 'vergelabs-media-library' ),
    );

    /* ------------------------------------------------------------ copies */

    $copies = 0;
    $wasted = 0;

    if ( function_exists( 'vergeml_health_report' ) ) {

        $report = vergeml_health_report();
        $wasted = isset( $report['wasted'] ) ? (int) $report['wasted'] : 0;

        foreach ( (array) ( isset( $report['duplicates'] ) ? $report['duplicates'] : array() ) as $group ) {
            // A group of three copies is two files too many, not three.
            $files   = isset( $group['files'] ) ? $group['files'] : $group;
            $copies += max( 0, count( (array) $files ) - 1 );
        }
    }

    $todo[] = array(
        'id'    => 'copies',
        'title' => __( 'Copies', 'vergelabs-media-library' ),
        'note'  => __( 'The same picture uploaded twice, or saved again at a different size. Nothing is deleted without you.', 'vergelabs-media-library' ),
        'n'     => $copies,
        'count' => sprintf(
            /* translators: 1: how many files are copies, 2: the disk they take, e.g. "9 MB". */
            _n( '%1$s file is a copy of another · %2$s of disk', '%1$s files are copies of others · %2$s of disk', $copies, 'vergelabs-media-library' ),
            number_format_i18n( $copies ),
            size_format( $wasted )
        ),
        'go'    => __( 'Look at the copies', 'vergelabs-media-library' ),
        'url'   => vergeml_journey_url( 'media-health' ),
        'done'  => __( 'No copies found.', 'vergelabs-media-library' ),
    );

    return apply_filters( 'vergeml_journey_todo', $todo );
}


/* -------------------------------------------- doing the two free ones here */

/*
 *  Both are instant and free, so they happen where they are offered. Sending
 *  somebody to a second screen to press a second button for work that takes a
 *  database write is the handoff this whole change is about.
 */

add_action( 'admin_post_vergeml_do_alt', 'vergeml_journey_do_alt' );

function vergeml_journey_do_alt() {

    if ( ! current_user_can( 'upload_files' ) ) {
        wp_die( esc_html__( 'You cannot do that.', 'vergelabs-media-library' ) );
    }

    check_admin_referer( 'vergeml_do_alt' );

    $wrote = function_exists( 'vergeml_ai_apply_alt' ) ? vergeml_ai_apply_alt( 0 ) : 0;

    wp_safe_redirect( add_query_arg( 'vgml_alt_written', (int) $wrote, vergeml_journey_url( VERGEML_MENU ) ) );
    exit;
}


add_action( 'admin_post_vergeml_do_rename', 'vergeml_journey_do_rename' );

function vergeml_journey_do_rename() {

    if ( ! current_user_can( 'upload_files' ) ) {
        wp_die( esc_html__( 'You cannot do that.', 'vergelabs-media-library' ) );
    }

    check_admin_referer( 'vergeml_do_rename' );

    $done = function_exists( 'vergeml_rename_apply' ) && function_exists( 'vergeml_rename_pending' )
        ? count( vergeml_rename_apply( vergeml_rename_pending() ) )
        : 0;

    wp_safe_redirect( add_query_arg( 'vgml_renamed', $done, vergeml_journey_url( VERGEML_MENU ) ) );
    exit;
}


add_action( 'admin_notices', 'vergeml_journey_alt_notice' );

function vergeml_journey_alt_notice() {

    if ( ! isset( $_GET['vgml_alt_written'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $n = (int) $_GET['vgml_alt_written']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html(
            sprintf(
                /* translators: %s: how many pictures got alt text. */
                _n( '%s picture now has alt text.', '%s pictures now have alt text.', $n, 'vergelabs-media-library' ),
                number_format_i18n( $n )
            )
        )
    );
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
        array( 'n' => $f['files'],   'label' => __( 'files', 'vergelabs-media-library' ),   'url' => 0 === $f['files'] ? admin_url( 'media-new.php' ) : admin_url( 'upload.php' ) ),
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

        <?php
        echo vergeml_pg_head( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Your media library', 'vergelabs-media-library' ),
            __( 'Where things stand, and what to do next.', 'vergelabs-media-library' )
        );
        ?>

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

        <div class="vgml-dash-cols">
        <div class="vgml-dash-main">

        <?php
        /*
         *  Before anything else can be offered, the pictures have to have been
         *  looked at. That is the one genuinely sequential thing here and it
         *  keeps the shape it had -- one card, one button.
         */
        /*
         *  Two independent questions, and they were one.
         *
         *  "Is there anything left to look at" gated the whole list of things
         *  you can do -- so a library of five hundred and twelve pictures with
         *  one straggler showed nothing but "Start here", and the alt text,
         *  the names and the folders all vanished behind a single file. One
         *  upload was enough to do it.
         *
         *  The card is about the remainder. The list is about what has been
         *  found. A library can be both at once, and usually is.
         */
        $remaining = (int) $f['undescribed'] > 0;
        $anything  = (int) $f['described'] > 0;
        ?>

        <?php if ( $next && $remaining ) : ?>
        <div class="vgml-next">
            <p class="vgml-next-eyebrow"><?php esc_html_e( 'Start here', 'vergelabs-media-library' ); ?></p>
            <h2><?php echo esc_html( $next['title'] ); ?></h2>
            <p class="vgml-next-text"><?php echo esc_html( $next['text'] ); ?></p>
            <?php if ( $next['action'] && $next['url'] ) : ?>
                <a class="button button-primary" href="<?php echo esc_url( $next['url'] ); ?>"><?php echo esc_html( $next['action'] ); ?></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ( $anything ) : ?>
        <div class="vgml-do-list">
            <h2 class="vgml-do-head"><?php esc_html_e( 'What you can do now', 'vergelabs-media-library' ); ?></h2>
            <?php foreach ( vergeml_journey_todo() as $item ) : ?>
                <?php
                /*
                 *  Title, number and note are one block, and the action is the
                 *  other. They used to be five siblings, which meant the card
                 *  could only be laid out by stacking them in one grid cell and
                 *  pushing each down by a fixed margin -- an arrangement that
                 *  came apart the moment a title wrapped to two lines.
                 */
                ?>
                <div class="vgml-do">
                    <div class="vgml-do-text">
                        <h3 class="vgml-do-title"><?php echo esc_html( $item['title'] ); ?></h3>
                        <?php if ( $item['n'] > 0 ) : ?>
                            <p class="vgml-do-line"><strong class="vgml-do-count"><?php echo esc_html( $item['count'] ); ?></strong></p>
                        <?php else : ?>
                            <p class="vgml-do-line is-done"><?php echo esc_html( $item['done'] ); ?></p>
                        <?php endif; ?>
                        <p class="vgml-do-note"><?php echo esc_html( $item['note'] ); ?></p>
                    </div>

                    <?php if ( $item['n'] > 0 ) : ?>
                        <p class="vgml-flow-actions">
                            <a class="button button-primary" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['go'] ); ?></a>
                        </p>
                    <?php endif; ?>

                    <?php if ( ! empty( $item['more'] ) ) : ?>
                        <div class="vgml-do-more">
                            <?php if ( ! empty( $item['more']['blocked'] ) ) : ?>
                                <p class="vgml-do-blocked"><?php echo esc_html( $item['more']['blocked'] ); ?></p>
                            <?php else : ?>
                                <div class="vgml-do-text">
                                    <p class="vgml-do-more-count"><?php echo esc_html( $item['more']['count'] ); ?></p>
                                    <p class="vgml-do-note"><?php echo esc_html( $item['more']['note'] ); ?></p>
                                </div>
                                <p class="vgml-flow-actions">
                                    <a class="button" href="<?php echo esc_url( $item['more']['url'] ); ?>"><?php echo esc_html( $item['more']['go'] ); ?></a>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php
        /*
         *  The seam the old home screen had, kept: the usage-statistics
         *  opt-in card hangs off it (core/instrument.php), and when the
         *  dashboard became this list the card quietly stopped rendering
         *  anywhere. A consent switch that cannot be reached is not consent.
         */
        if ( has_action( 'vergeml_admin_home_cards' ) ) : ?>
        <div class="vgml-home-cards">
            <?php do_action( 'vergeml_admin_home_cards' ); ?>
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
         *  What is left that the list above does not cover.
         *
         *  It used to be every unfinished stage, which is now the same four
         *  things said twice in two vocabularies -- so anything the four
         *  already speak for is dropped, and what remains is the odd job:
         *  importing folders from another plugin, files nothing links to.
         */
        $covered = array( 'library', 'alt', 'describe', 'duplicates', 'filing', 'folders' );
        $waiting = array();

        foreach ( $stages as $stage ) {

            if ( 'now' === $stage['state'] || 'done' === $stage['state'] ) {
                continue;
            }

            if ( in_array( $stage['id'], $covered, true ) ) {
                continue;
            }

            $waiting[] = $stage;
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

        </div><!-- /main -->

        <?php
        /*
         *  The rail.
         *
         *  Two things the dashboard was missing: a number saying how the
         *  library is doing rather than only how big it is, and buttons that
         *  DO something. Every button on the old screen navigated somewhere
         *  else, which makes a dashboard a table of contents.
         */
        $scored = vergeml_journey_score();
        ?>
        <aside class="vgml-dash-rail">

            <div class="vgml-rail-card vgml-scorecard">
                <h2><?php esc_html_e( 'Library score', 'vergelabs-media-library' ); ?></h2>

                <?php if ( null === $scored['score'] ) : ?>
                    <p class="vgml-score-none"><?php esc_html_e( 'Nothing to score until there are files in the library.', 'vergelabs-media-library' ); ?></p>
                <?php else : ?>
                    <p class="vgml-score-n"><?php echo esc_html( number_format_i18n( $scored['score'] ) ); ?><span>/100</span></p>
                <?php endif; ?>

                <ul class="vgml-score-parts">
                    <?php foreach ( $scored['parts'] as $part ) : ?>
                        <li>
                            <a href="<?php echo esc_url( $part['url'] ); ?>">
                                <span class="vgml-score-label"><?php echo esc_html( $part['label'] ); ?></span>
                                <span class="vgml-score-pts"><?php
                                    printf( '%d/%d', (int) $part['points'], (int) $part['weight'] );
                                ?></span>
                            </a>
                            <span class="vgml-score-bar"><span style="width:<?php echo esc_attr( (int) round( $part['share'] * 100 ) ); ?>%"></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="vgml-rail-card">
                <h2><?php esc_html_e( 'Quick actions', 'vergelabs-media-library' ); ?></h2>

                <?php
                $actions = array();

                if ( $f['undescribed'] > 0 ) {
                    $actions[] = array(
                        'id'   => 'describe',
                        'icon' => 'play',
                        'label' => sprintf(
                            /* translators: %s: number of images. */
                            __( 'Describe %s images', 'vergelabs-media-library' ),
                            number_format_i18n( $f['undescribed'] )
                        ),
                        'note' => __( 'Runs in the background — you can leave this page.', 'vergelabs-media-library' ),
                    );
                }

                if ( $f['no_alt'] > 0 ) {
                    $actions[] = array(
                        'id'   => 'alt',
                        'icon' => 'alt',
                        'label' => sprintf(
                            /* translators: %s: number of images with no alt text. */
                            __( 'Write %s missing alt texts', 'vergelabs-media-library' ),
                            number_format_i18n( $f['no_alt'] )
                        ),
                        'note' => __( 'Only where alt is empty. Never over your own words.', 'vergelabs-media-library' ),
                    );
                }

                /*
                 *  Descriptions written before the site profile or the prompt
                 *  changed. Worth offering, and worth saying what it costs --
                 *  this is the one quick action that spends money.
                 */
                if ( ! empty( $f['stale'] ) && $f['stale'] > 0 && $f['undescribed'] < 1 ) {
                    $actions[] = array(
                        'id'    => 'stale',
                        'icon'  => 'ai',
                        'label' => sprintf(
                            /* translators: %s: number of images. */
                            __( 'Re-describe %s images', 'vergelabs-media-library' ),
                            number_format_i18n( $f['stale'] )
                        ),
                        'note'  => sprintf(
                            /* translators: %s: number of credits. */
                            __( 'Written before your settings changed. Costs %s credits.', 'vergelabs-media-library' ),
                            number_format_i18n( $f['stale'] )
                        ),
                    );
                }

                if ( function_exists( 'vergeml_health_state' ) && empty( vergeml_health_state()['finished'] ) ) {
                    $actions[] = array(
                        'id'    => 'scan',
                        'icon'  => 'search',
                        'label' => __( 'Scan for duplicate files', 'vergelabs-media-library' ),
                        'note'  => __( 'Free, sends nothing anywhere, changes nothing.', 'vergelabs-media-library' ),
                        'href'  => vergeml_journey_url( 'media-health' ),
                    );
                }

                if ( $f['folders'] > 0 ) {
                    $actions[] = array(
                        'id'    => 'export',
                        'icon'  => 'download',
                        'label' => __( 'Export folders as CSV', 'vergelabs-media-library' ),
                        'note'  => __( 'Your whole structure, in a spreadsheet.', 'vergelabs-media-library' ),
                        'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=vergeml_export_csv&taxonomy=media_category' ), 'vergeml_export_csv' ),
                    );
                }

                if ( empty( $actions ) ) {
                    $actions[] = array(
                        'id'    => 'upload',
                        'icon'  => 'play',
                        'label' => __( 'Upload some files', 'vergelabs-media-library' ),
                        'note'  => __( 'Nothing to do here until there are files.', 'vergelabs-media-library' ),
                        'href'  => admin_url( 'media-new.php' ),
                    );
                }
                ?>

                <ul class="vgml-quick">
                    <?php foreach ( $actions as $act ) : ?>
                        <li>
                            <?php if ( ! empty( $act['href'] ) ) : ?>
                                <a class="vgml-quick-do" href="<?php echo esc_url( $act['href'] ); ?>">
                            <?php else : ?>
                                <button type="button" class="vgml-quick-do" data-do="<?php echo esc_attr( $act['id'] ); ?>">
                            <?php endif; ?>

                                <span class="vgml-quick-ico"><?php
                                    echo function_exists( 'vergeml_icon' ) ? vergeml_icon( $act['icon'], 18 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG.
                                ?></span>
                                <span class="vgml-quick-text">
                                    <span class="vgml-quick-label"><?php echo esc_html( $act['label'] ); ?></span>
                                    <span class="vgml-quick-note"><?php echo esc_html( $act['note'] ); ?></span>
                                </span>

                            <?php if ( ! empty( $act['href'] ) ) : ?>
                                </a>
                            <?php else : ?>
                                </button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p class="vgml-quick-said" id="vgml-quick-said" role="status"></p>
            </div>

        </aside>
        </div><!-- /cols -->

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

    wp_enqueue_script(
        'vergeml-journey',
        plugins_url( 'js/vergeml-journey.js', VERGEML_FILE ),
        array( 'wp-api-fetch' ),
        vergeml_asset_ver( 'js/vergeml-journey.js' ),
        true
    );

    wp_localize_script( 'vergeml-journey', 'vergemlJourney', array(
        'starting' => __( 'Starting…', 'vergelabs-media-library' ),
        /* translators: 1: images done, 2: images in the run. */
        'running'  => __( 'Running — %1$d of %2$d done. You can leave this page.', 'vergelabs-media-library' ),
        'finished' => __( 'Finished. Refreshing the numbers…', 'vergelabs-media-library' ),
        'stopped'  => __( 'Stopped:', 'vergelabs-media-library' ),
        'failed'   => __( 'That did not start. Check the licence on the AI screen.', 'vergelabs-media-library' ),
        'confirmStale' => __( 'Re-describing spends one credit per image. Continue?', 'vergelabs-media-library' ),
    ) );
}
