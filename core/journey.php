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
        return apply_filters( 'vergeml_journey_facts', $facts[ $blog ] );
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
        return apply_filters( 'vergeml_journey_facts', $facts[ $blog ] );
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

    /*
     *  Folders are the folder taxonomy's terms, and only those. Summing every
     *  media taxonomy counted a Colour tag set as six folders, so the
     *  dashboard said 29 where the tree showed 23.
     */
    $folders = 0;
    $folder_tax = function_exists( 'vergeml_librarian_taxonomy' ) ? vergeml_librarian_taxonomy() : 'media_category';

    if ( '' !== $folder_tax && taxonomy_exists( $folder_tax ) ) {
        $count   = wp_count_terms( array( 'taxonomy' => $folder_tax, 'hide_empty' => false ) );
        $folders = is_wp_error( $count ) ? 0 : (int) $count;
    }

    $settings = function_exists( 'vergeml_ai_settings' ) ? vergeml_ai_settings() : array();
    /*
     *  Asked for, not remembered.
     *
     *  This read the cached option straight, so it showed whatever the last
     *  describe run happened to leave behind -- which is how the plugin came
     *  to say 20,467 while the account said 26,000: it had never seen the
     *  purchase. The refresh is cached for a few minutes of its own, so this
     *  costs nothing on a page load.
     */
    $credits  = function_exists( 'vergeml_ai_refresh_credits' )
        ? array( 'remaining' => vergeml_ai_refresh_credits() )
        : get_option( 'vergeml_ai_credits', array() );

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

    /**
     *  Filter the dashboard's figures.
     *
     *  The seam the suite uses to put the dashboard into a state the live
     *  library is not in -- no key, nothing described, 268 unfiled -- without
     *  touching a row of it. Applied on every return, cached or not.
     *
     *  @since 3.16.2
     *
     *  @param array $facts  see the keys above.
     */
    return apply_filters( 'vergeml_journey_facts', $facts[ $blog ] );
}


/**
 *  vergeml_journey_progress
 *
 *  Four rows, each its own count: how many are done of how many there are,
 *  and the one action that moves the number. No total, no weights.
 *
 *  This was a weighted score out of a hundred. An empty library scored 85,
 *  379 of 380 filed rounded to full marks above a card reading "1 file is in
 *  no folder", and the weights were an opinion nobody had asked for. A count
 *  cannot be argued with: 412 of 641 have alt text, and here is the button
 *  that writes the rest.
 *
 *  A row at M of M is finished and offers nothing. Every number is a figure
 *  the screen already has; nothing here reads the database.
 */
function vergeml_journey_progress() {

    $f = vergeml_journey_facts();

    if ( 0 === $f['files'] ) {
        return array();
    }

    /*
     *  The copy scan counts files, not pictures. While it runs, its cursor is
     *  how far it has got; finished, every file was compared.
     */
    $health  = function_exists( 'vergeml_health_state' ) ? vergeml_health_state() : array();
    $checked = ! empty( $health['finished'] )
        ? $f['files']
        : ( isset( $health['cursor'] ) ? (int) $health['cursor'] : 0 );

    $rows = array(
        array(
            'id'     => 'alt',
            'label'  => __( 'Alt text', 'vergelabs-media-library' ),
            'n'      => $f['images'] - $f['no_alt'],
            'm'      => $f['images'],
            'action' => __( 'Write alt text', 'vergelabs-media-library' ),
            'url'    => vergeml_journey_url( 'media-ai' ),
        ),
        array(
            'id'     => 'described',
            'label'  => __( 'Described', 'vergelabs-media-library' ),
            'n'      => $f['described'],
            'm'      => $f['images'],
            'action' => __( 'Describe the rest', 'vergelabs-media-library' ),
            'url'    => vergeml_journey_url( 'media-ai' ),
        ),
        array(
            'id'     => 'filed',
            'label'  => __( 'Filed', 'vergelabs-media-library' ),
            'n'      => $f['files'] - $f['unfiled'],
            'm'      => $f['files'],
            'action' => __( 'Put files in folders', 'vergelabs-media-library' ),
            'url'    => vergeml_journey_url( 'media-librarian' ),
        ),
        array(
            'id'     => 'checked',
            'label'  => __( 'Checked for copies', 'vergelabs-media-library' ),
            'n'      => $checked,
            'm'      => $f['files'],
            'action' => __( 'Check for copies', 'vergelabs-media-library' ),
            'url'    => vergeml_journey_url( 'media-health' ),
        ),
    );

    foreach ( $rows as $at => $row ) {
        $m = max( 0, (int) $row['m'] );
        $n = min( $m, max( 0, (int) $row['n'] ) );

        $rows[ $at ]['n'] = $n;
        $rows[ $at ]['m'] = $m;

        // Finished offers nothing: a row at M of M has no action.
        if ( $n >= $m ) {
            $rows[ $at ]['action'] = '';
            $rows[ $at ]['url']    = '';
        }
    }

    return $rows;
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
            $text = __( 'This plugin can look at each of your pictures and write down what is in them, which is what makes everything else here work. Add a licence key to start, or switch on demo mode first.', 'vergelabs-media-library' );
        }

        $stages[] = array(
            'id'     => 'access',
            'title'  => __( 'Licence', 'vergelabs-media-library' ),
            'done'   => $f['licensed'] || $f['demo'],
            'text'   => $text,
            'action' => ( $f['licensed'] || $f['demo'] )
                ? __( 'Change this', 'vergelabs-media-library' )
                : __( 'Start here', 'vergelabs-media-library' ),
            'url'    => vergeml_journey_url( 'media-licence' ),
        );
    }

    /* ----------------------------------------------------- the describing */

    if ( function_exists( 'vergeml_ai_pending' ) ) {

        $blocked = ( ! $f['licensed'] && ! $f['demo'] )
            ? __( 'Sort out the step above first — we cannot look at anything until you turn on demo mode or add a key.', 'vergelabs-media-library' )
            : '';

        $stages[] = array(
            'id'      => 'describe',
            'title'   => __( 'Describe your images', 'vergelabs-media-library' ),
            'done'    => $f['images'] > 0 && 0 === $f['undescribed'],
            'blocked' => $blocked,
            'text'    => vergeml_journey_describe_text( $f ),
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
 *  What describing will do and cost, from the figures. The describe stage
 *  and the to-do row say the same thing, so it is written once.
 */
function vergeml_journey_describe_text( $f ) {

    if ( 0 === $f['images'] ) {
        return __( 'There are no pictures here yet. Upload some and this page will have something to say.', 'vergelabs-media-library' );
    }

    if ( 0 === $f['undescribed'] ) {
        return sprintf(
            /* translators: %s: number of images described. */
            __( 'We have looked at all %s of your pictures and written down what is in them. Searching your media now finds a photo by what it shows, not just by its file name.', 'vergelabs-media-library' ),
            number_format_i18n( $f['described'] )
        );
    }

    return sprintf(
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

    /* -------------------------------------------------------- describing */

    /*
     *  First, because everything under it is written from what the model
     *  saw. Without a key and with demo mode off it cannot run; the row then
     *  says so once, in place of its count, rather than offering a button
     *  that does nothing.
     */
    if ( function_exists( 'vergeml_ai_pending' ) ) {
        $todo[] = array(
            'id'      => 'describe',
            'title'   => __( 'Describe your images', 'vergelabs-media-library' ),
            'note'    => vergeml_journey_describe_text( $f ),
            'kind'    => 'primary',
            'n'       => (int) $f['undescribed'],
            'go'      => __( 'Describe them', 'vergelabs-media-library' ),
            'url'     => vergeml_journey_url( 'media-ai' ),
            'blocked' => ( ! $f['licensed'] && ! $f['demo'] )
                ? __( 'Add a licence key or switch on demo mode first.', 'vergelabs-media-library' )
                : '',
        );
    }

    /* ---------------------------------------------------------- alt text */

    // Nothing can be waiting when every picture has alt text; the list is
    // not read for a number the facts already know is zero.
    $alt_ready = ( 0 === (int) $f['no_alt'] || ! function_exists( 'vergeml_ai_alt_pending' ) )
        ? 0
        : count( vergeml_ai_alt_pending() );

    $todo[] = array(
        'id'    => 'alt',
        'title' => __( 'Pictures waiting for alt text', 'vergelabs-media-library' ),
        'note'  => __( 'The line a screen reader reads out. It was written when we looked at your pictures — putting it on costs nothing.', 'vergelabs-media-library' ),
        'kind'  => 'primary',
        'n'     => $alt_ready,
        'go'    => __( 'Write the alt text', 'vergelabs-media-library' ),
        'url'   => wp_nonce_url( admin_url( 'admin-post.php?action=vergeml_do_alt' ), 'vergeml_do_alt' ),
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

    // How many pictures have never been looked at. A title cannot be written
    // from one of those, which is why the count above is not the library.
    $undescribed = function_exists( 'vergeml_ai_pending_count' ) ? (int) vergeml_ai_pending_count( 'unindexed' ) : 0;

    // The on-disk renamer is switched off until it is whole; while it is,
    // the screen speaks only about titles and promises nothing about files.
    $file_feature = defined( 'VERGEML_FILE_RENAME' ) && VERGEML_FILE_RENAME;
    $files        = $file_feature && function_exists( 'vergeml_file_pending_count' ) ? (int) vergeml_file_pending_count()['n'] : 0;

    $todo[] = array(
        'id'    => 'names',
        'title' => __( 'Titles that say nothing', 'vergelabs-media-library' ),
        'note'  => __( '“Photo 498” tells nobody anything. Each file can be named after what is in it, from the same look we already took. Anything you named yourself is left alone.', 'vergelabs-media-library' ),
        'kind'  => 'secondary',
        'n'     => $names,
        'go'    => __( 'Rename from descriptions', 'vergelabs-media-library' ),
        'url'   => wp_nonce_url( admin_url( 'admin-post.php?action=vergeml_do_rename' ), 'vergeml_do_rename' ),
        'more'  => vergeml_journey_file_rename(),
    );

    /* ----------------------------------------------------------- folders */

    /*
     *  Files, not folders. The number is the title, and the action says
     *  what happens to the files: "Work out the folders" answered a question
     *  about files with a word about folders. The link is the Sort screen
     *  today; the Folders screen takes it over when it lands.
     */
    $todo[] = array(
        'id'    => 'folders',
        'title' => sprintf(
            /* translators: %s: how many files are in no folder. */
            _n( '%s file in no folder', '%s files in no folder', (int) $f['unfiled'], 'vergelabs-media-library' ),
            number_format_i18n( $f['unfiled'] )
        ),
        'note'  => __( 'Group them by what they have in common, or by when they were uploaded. You approve the whole plan before a single file moves.', 'vergelabs-media-library' ),
        'kind'  => 'secondary',
        'n'     => (int) $f['unfiled'],
        'go'    => __( 'Put them in folders', 'vergelabs-media-library' ),
        'url'   => vergeml_journey_url( 'media-librarian' ),
        // The title carries the number, so the row does not print it twice.
        'count_in_title' => true,
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

    // Look-alike sets: the groups shown plus the ones the report cut off.
    $sets = 0;
    if ( isset( $report ) && is_array( $report ) && isset( $report['related'] ) && is_array( $report['related'] ) ) {
        $sets = count( (array) ( isset( $report['related']['groups'] ) ? $report['related']['groups'] : array() ) )
            + (int) ( isset( $report['related']['more'] ) ? $report['related']['more'] : 0 );
    }

    $todo[] = array(
        'id'    => 'copies',
        'title' => $copies > 0
            ? __( 'Exact copies, and look-alike sets', 'vergelabs-media-library' )
            : ( $sets > 0 ? __( 'Look-alike sets, no exact copies', 'vergelabs-media-library' ) : __( 'Copies', 'vergelabs-media-library' ) ),
        'note'  => $wasted > 0
            ? sprintf(
                /* translators: %s: an amount of disk, e.g. 2.3 MB */
                __( 'Nothing is deleted without you. Keeping one of each frees up to %s.', 'vergelabs-media-library' ),
                size_format( $wasted, 1 )
            )
            : __( 'The same picture uploaded twice, or saved again at a different size. Nothing is deleted without you.', 'vergelabs-media-library' ),
        'kind'  => 'ghost',
        'n'     => max( $copies, $sets ),
        'go'    => __( 'Review the sets', 'vergelabs-media-library' ),
        'url'   => vergeml_journey_url( 'media-health' ),
    );

    /*
     *  Only rows with something behind them. A row reading "0 pictures are
     *  waiting" is a sentence about work that does not exist; the section
     *  hides itself when nothing is left. A blocked row keeps its place as
     *  long as its count is real -- the blocker is what it has to say.
     */
    $rows = array();

    foreach ( $todo as $item ) {

        $item = wp_parse_args( $item, array( 'blocked' => '', 'more' => null, 'count_in_title' => false ) );

        if ( (int) $item['n'] > 0 ) {
            $rows[] = $item;
        }
    }

    /**
     *  Filter the to-do rows.
     *
     *  @since 3.9.0
     *
     *  @param array $rows  ordered; every row has something to do.
     */
    return apply_filters( 'vergeml_journey_todo', $rows );
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


/**
 *  What just happened, said on the screen it happened on.
 *
 *  Every action here redirects back with a count in the query string, and the
 *  counts were read by admin_notices handlers -- which the shell removes on
 *  its own screens, deliberately. So pressing a button did the work and then
 *  said nothing at all, which is indistinguishable from a button that does
 *  not work. One of them, the title rewrite, had no handler reading it in the
 *  first place.
 */
function vergeml_journey_results() {

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading a result the redirect wrote, not acting.
    $said = array();

    if ( isset( $_GET['vgml_renamed'] ) ) {
        $n      = max( 0, (int) $_GET['vgml_renamed'] );
        $said[] = 0 === $n
            ? __( 'No titles needed rewriting. A title is only written from a picture we have already looked at.', 'vergelabs-media-library' )
            : sprintf(
                /* translators: %s: how many titles were rewritten. */
                _n( '%s title rewritten from what the picture shows.', '%s titles rewritten from what the pictures show.', $n, 'vergelabs-media-library' ),
                number_format_i18n( $n )
            );
    }

    if ( isset( $_GET['vgml_alt_written'] ) ) {
        $n      = max( 0, (int) $_GET['vgml_alt_written'] );
        $said[] = sprintf(
            /* translators: %s: how many pictures got alt text. */
            _n( '%s picture now has alt text.', '%s pictures now have alt text.', $n, 'vergelabs-media-library' ),
            number_format_i18n( $n )
        );
    }

    if ( isset( $_GET['vgml_files_renamed'] ) ) {
        $n      = max( 0, (int) $_GET['vgml_files_renamed'] );
        $said[] = sprintf(
            /* translators: %s: how many files were renamed on disk. */
            _n( '%s file renamed, and the pages pointing at it updated.', '%s files renamed, and the pages pointing at them updated.', $n, 'vergelabs-media-library' ),
            number_format_i18n( $n )
        );
    }

    if ( isset( $_GET['vgml_files_back'] ) ) {
        $n      = max( 0, (int) $_GET['vgml_files_back'] );
        $said[] = sprintf(
            /* translators: %s: how many file names were put back. */
            _n( '%s file name put back.', '%s file names put back.', $n, 'vergelabs-media-library' ),
            number_format_i18n( $n )
        );
    }
    // phpcs:enable

    foreach ( $said as $line ) {
        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html( $line )
        );
    }
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

        <?php vergeml_journey_results(); ?>


        <?php
        /*
         *  The header's right-hand meta: connected, and to which licence.
         *  Told in four characters, the same four the account page shows.
         */
        $meta = '';
        if ( ! empty( $f['licensed'] ) && function_exists( 'vergeml_ai_settings' ) && function_exists( 'vergeml_ai_unseal' ) ) {
            $key = (string) vergeml_ai_unseal( vergeml_ai_settings()['license_key'] );
            if ( '' !== $key ) {
                /* translators: %s: the last four characters of the licence key */
                $meta = '<span class="vgml-dash-meta">' . esc_html( sprintf( __( 'Connected · licence …%s', 'vergelabs-media-library' ), substr( $key, -4 ) ) ) . '</span>';
            }
        }

        echo vergeml_pg_head( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
            __( 'Your media library', 'vergelabs-media-library' ),
            __( 'Where things stand, and what to do next.', 'vergelabs-media-library' ),
            $meta
        );
        ?>

        <?php
        /*
         *  The stat band. Only a cell that opens somewhere is a link, and it
         *  says so with an arrow; files, folders and "used nowhere" are
         *  numbers, not doors (design handoff, item 9).
         */
        $band = array(
            array( 'n' => $f['files'],   'l' => __( 'files', 'vergelabs-media-library' ) ),
            array( 'n' => $f['folders'], 'l' => __( 'folders', 'vergelabs-media-library' ) ),
            array( 'n' => $f['unfiled'], 'l' => __( 'unfiled', 'vergelabs-media-library' ), 'url' => vergeml_journey_url( 'media-librarian' ) ),
            array( 'n' => $f['no_alt'],  'l' => __( 'no alt text', 'vergelabs-media-library' ), 'url' => vergeml_journey_url( 'media-ai' ) ),
        );
        if ( null !== $f['unused'] ) {
            $band[] = array( 'n' => $f['unused'], 'l' => __( 'used nowhere', 'vergelabs-media-library' ), 'tip' => __( 'Files not used in any post, page or product yet.', 'vergelabs-media-library' ) );
        }
        if ( null !== $f['credits'] ) {
            $band[] = array( 'n' => $f['credits'], 'l' => __( 'credits left', 'vergelabs-media-library' ), 'url' => vergeml_journey_url( 'media-licence' ) );
        }
        ?>
        <div class="vgml-band">
            <?php foreach ( $band as $cell ) : ?>
                <?php if ( ! empty( $cell['url'] ) ) : ?>
                    <a class="vgml-band-cell" href="<?php echo esc_url( $cell['url'] ); ?>">
                        <span class="vgml-band-n"><?php echo esc_html( number_format_i18n( $cell['n'] ) ); ?></span>
                        <span class="vgml-band-l"><?php echo esc_html( $cell['l'] ); ?> →</span>
                    </a>
                <?php else : ?>
                    <div class="vgml-band-cell"<?php echo ! empty( $cell['tip'] ) ? ' title="' . esc_attr( $cell['tip'] ) . '"' : ''; ?>>
                        <span class="vgml-band-n"><?php echo esc_html( number_format_i18n( $cell['n'] ) ); ?></span>
                        <span class="vgml-band-l"><?php echo esc_html( $cell['l'] ); ?></span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="vgml-cols vgml-dash-cols">
        <div class="vgml-cols-main vgml-dash-main">

        <?php
        /*
         *  One list. The describe card used to sit above it as a card of its
         *  own, gated on "anything left to describe", and the list under it
         *  on "anything described yet" -- two gates that between them hid the
         *  whole screen behind a single straggling upload. The rows come from
         *  vergeml_journey_todo(), which returns only what has something to do.
         */
        $todo = vergeml_journey_todo();
        ?>

        <?php if ( ! empty( $todo ) ) : ?>
        <h6 class="vgml-kicker"><?php esc_html_e( 'What to do next', 'vergelabs-media-library' ); ?></h6>
        <div class="vgml-do-list">

            <?php foreach ( $todo as $item ) : ?>
                <?php
                /*
                 *  One row: the count, the title and the line under it, and
                 *  exactly one action on the right (design handoff, item 6).
                 *  Primary, secondary or ghost is the item's own weight. A
                 *  blocked row has no count and no button: the blocker is its
                 *  line. A title that carries the number does not repeat it.
                 */
                $kind    = isset( $item['kind'] ) ? $item['kind'] : 'secondary';
                $class   = 'primary' === $kind ? 'button button-primary' : ( 'ghost' === $kind ? 'vgml-btn vgml-btn-ghost' : 'button' );
                $blocked = '' !== (string) $item['blocked'];
                ?>
                <div class="vgml-do<?php echo $blocked ? ' is-blocked' : ''; ?>" data-todo="<?php echo esc_attr( $item['id'] ); ?>">
                    <div class="vgml-do-n"><?php
                        if ( ! $blocked && empty( $item['count_in_title'] ) ) {
                            echo esc_html( number_format_i18n( (int) $item['n'] ) );
                        }
                    ?></div>
                    <div class="vgml-do-text">
                        <div class="vgml-do-title"><?php echo esc_html( $item['title'] ); ?></div>
                        <div class="vgml-do-note"><?php echo esc_html( $blocked ? $item['blocked'] : $item['note'] ); ?></div>
                        <?php if ( ! $blocked && ! empty( $item['more'] ) ) : ?>
                            <?php if ( ! empty( $item['more']['blocked'] ) ) : ?>
                                <div class="vgml-do-more"><?php echo esc_html( $item['more']['blocked'] ); ?></div>
                            <?php else : ?>
                                <div class="vgml-do-more">
                                    <?php echo esc_html( $item['more']['count'] ); ?>
                                    <a href="<?php echo esc_url( $item['more']['url'] ); ?>"><?php echo esc_html( $item['more']['go'] ); ?></a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ( ! $blocked ) : ?>
                        <a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['go'] ); ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

        </div>
        <?php endif; ?>

        <?php if ( ! empty( $f['recent'] ) ) : ?>
        <!-- the library itself, and what the model saw in it -->
        <div class="vgml-seen">
            <div class="vgml-seen-head">
                <h6 class="vgml-kicker"><?php esc_html_e( 'Recently described', 'vergelabs-media-library' ); ?></h6>
                <a class="vgml-btn vgml-btn-ghost" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'Open the library ↗', 'vergelabs-media-library' ); ?></a>
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
        <?php
        /*
         *  The foot: the consent row for size counts (core/instrument.php),
         *  the Get help row (core/get-help.php), and the version line. The
         *  hook is kept because a consent switch that cannot be reached is
         *  not consent.
         */
        ?>
        <div class="vgml-dash-foot">
            <?php do_action( 'vergeml_admin_home_cards' ); ?>
            <p class="vgml-dash-version"><?php
                $bits = array( sprintf( /* translators: %s: version */ __( 'Version %s', 'vergelabs-media-library' ), VERGEML_VERSION ) );
                if ( '' !== $meta ) {
                    $bits[] = sprintf( /* translators: %s: last four of the licence */ __( 'Licence …%s', 'vergelabs-media-library' ), substr( $key, -4 ) );
                    $bits[] = __( 'Connected', 'vergelabs-media-library' );
                }
                echo esc_html( implode( ' · ', $bits ) );
            ?></p>
        </div>

        </div><!-- /main -->

        <?php
        /*
         *  The rail.
         *
         *  Four counts, each with the button that moves it, and then buttons
         *  that DO something. Every button on the old screen navigated
         *  somewhere else, which makes a dashboard a table of contents.
         */
        $progress = vergeml_journey_progress();
        ?>
        <aside class="vgml-cols-rail vgml-dash-rail">

            <?php if ( ! empty( $progress ) ) : ?>
            <div class="vgml-rail-block vgml-progress">
                <ul class="vgml-progress-rows">
                    <?php foreach ( $progress as $row ) : ?>
                        <?php
                        /*
                         *  Label, "N of M", the bar at N/M (the import bar's
                         *  geometry, so progress is the same shape on every
                         *  screen), and one action link. Nothing else: no
                         *  sentence under it, no percentage, no total.
                         */
                        $full = $row['n'] >= $row['m'];
                        $at   = $row['m'] > 0 ? round( ( $row['n'] / $row['m'] ) * 100, 1 ) : 100;
                        ?>
                        <li class="vgml-progress-row<?php echo $full ? ' is-full' : ''; ?>" data-progress="<?php echo esc_attr( $row['id'] ); ?>">
                            <div class="vgml-progress-head">
                                <span class="vgml-progress-label"><?php echo esc_html( $row['label'] ); ?></span>
                                <span class="vgml-progress-count"><?php
                                    echo esc_html( sprintf(
                                        /* translators: 1: how many are done, 2: how many there are. */
                                        __( '%1$s of %2$s', 'vergelabs-media-library' ),
                                        number_format_i18n( $row['n'] ),
                                        number_format_i18n( $row['m'] )
                                    ) );
                                ?></span>
                            </div>
                            <div class="vgml-import-bar"><div class="vgml-import-fill" style="width:<?php echo esc_attr( $at ); ?>%"></div></div>
                            <?php if ( '' !== $row['action'] ) : ?>
                                <a class="vgml-progress-action" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['action'] ); ?> →</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="vgml-rail-block">
                <h6 class="vgml-kicker"><?php esc_html_e( 'Quick actions', 'vergelabs-media-library' ); ?></h6>

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

            <?php if ( ! empty( $waiting ) ) : ?>
            <div class="vgml-rail-block vgml-rest">
                <h6 class="vgml-kicker"><?php esc_html_e( 'Also worth doing', 'vergelabs-media-library' ); ?></h6>
                <ul class="vgml-rest-list">
                    <?php foreach ( $waiting as $stage ) : ?>
                        <li class="vgml-rest-row is-<?php echo esc_attr( $stage['state'] ); ?>">
                            <a href="<?php echo esc_url( $stage['url'] ); ?>">
                                <span class="vgml-rest-title"><?php echo esc_html( $stage['title'] ); ?> →</span>
                                <span class="vgml-rest-state"><?php
                                    echo esc_html( 'blocked' === $stage['state'] ? $stage['blocked'] : ( isset( $stage['text'] ) ? $stage['text'] : vergeml_journey_state_word( $stage['state'] ) ) );
                                ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

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
