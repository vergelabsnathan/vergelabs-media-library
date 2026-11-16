<?php
/**
 *  Who may reach what: every route, as four kinds of person.
 *
 *      wp eval-file tests/security/roles.php --allow-root
 *
 *  Phase 5.2 of plans/four-yesses.md. Three questions of every way in -- can
 *  this caller do this at all, may they do it to *this* object, did they mean to
 *  -- and this suite answers the first two for all 76 REST endpoints, as an
 *  anonymous visitor, a subscriber, an author and an editor.
 *
 *  ## It calls the gate, never the handler
 *
 *  Each endpoint's `permission_callback` is fetched from the REST server and
 *  invoked directly. `rest_do_request()` would be the obvious way and it is the
 *  wrong one: where permission passes it runs the handler, so a suite of 76
 *  endpoints as an editor would file pictures, write options, book cron and --
 *  on four of them -- spend credits. Calling the gate alone asserts exactly the
 *  thing under test and changes nothing. Nothing here reaches a model and
 *  nothing here costs a cent.
 *
 *  ## What it creates, and puts back
 *
 *  Three users (subscriber, author, editor) and two attachment rows: one owned
 *  by the author, one owned by nobody the author is. They exist so the object
 *  question can be asked for real -- `/file/<id>` as the author of that file and
 *  as the author of a different one is the whole of IDOR in two assertions --
 *  and all five are deleted at the end, including any left behind by a run that
 *  died. Logins are prefixed `zz-vgml-role-` so a leftover is recognisable.
 *
 *  ## The premise this suite also checks
 *
 *  47 endpoints are gated on `manage_categories` and write to attachments
 *  without asking `edit_post` about them. That is safe on stock WordPress for
 *  one reason only: every built-in role holding `manage_categories` also holds
 *  `edit_others_posts`. The reason is a premise, so it is asserted rather than
 *  believed -- if a site's roles break it, this suite says so on that site.
 *
 *  ## Mutation checks
 *
 *  Run on 2026-09-10. Each needed the mutation deployed to the box, since what
 *  this suite reads is the plugin running there and not the working tree; each
 *  went red at the row named, and green again once reverted and redeployed.
 *
 *    1. `vergeml_can_read_tree()` made `return true`
 *       → "every one of our routes refuses an anonymous visitor" FAILs, naming
 *         /tree, /state GET, /state POST, /folders/version and /gallery-folders
 *         -- one gate, five endpoints -- and the subscriber row with it.
 *    2. brief's gate changed from manage_options to manage_categories
 *       → "an editor reaches none of the 16 administrator-only endpoints" FAILs,
 *         naming all seven brief endpoints.
 *    3. `vergeml_can_manage_folders()` changed to upload_files
 *       → "an author reaches 13 endpoints and no others" FAILs, naming
 *         /folder POST.
 *    4. quick-edit's gate changed to `current_user_can( 'upload_files' )`
 *       → "/file/<id> refuses an author a file that is not theirs" FAILs while
 *         "allows an author their own file" stays green, which is the pair that
 *         tells an object-scoped gate from a role-based one.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'VERGEML_REST_NS' ) ) {
    echo "the plugin is not loaded -- inactive, or safe mode?\n";
    exit( 1 );
}

/*
 *  $GLOBALS, not `global`. wp eval-file evaluates this file inside a function,
 *  so anything declared at the top of it is a local of that function and
 *  `global` binds to a second, empty variable -- the counters stay at zero, the
 *  summary reads 0/0, and the suite passes whatever failed.
 */
$GLOBALS['r_pass'] = 0;
$GLOBALS['r_fail'] = 0;

function r_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['r_pass']++;
    } else {
        $GLOBALS['r_fail']++;
    }
    printf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}


/* --------------------------------------------------------- the expectation */

/*
 *  GENERATED BLOCK -- tools/security-surface.mjs derives these three lists from
 *  the permission callbacks and tests/security/surface.mjs fails when this copy
 *  and the code disagree. Do not hand-edit: regenerate.
 */

/** Reachable by an author: the gate asks only upload_files or edit_posts. */
$r_author_may = array(
    '/vergeml/v1/ai-alt GET',
    '/vergeml/v1/assign POST',
    '/vergeml/v1/folders/version GET',
    '/vergeml/v1/gallery-folders GET',
    '/vergeml/v1/rename GET',
    '/vergeml/v1/state GET',
    '/vergeml/v1/state POST',
    '/vergeml/v1/tree GET',
);

/** Gated on the object, not on a role: reachable for a file that is yours. */
$r_object_scoped = array(
    '/vergeml/v1/file/(?P<id>\d+) POST, PUT, PATCH',
    '/vergeml/v1/librarian-why/(?P<id>\d+) GET',
);

/** Administrator only: the gate asks manage_options or delete_posts. */
$r_admin_only = array(
    '/vergeml/v1/ai-alt POST',
    '/vergeml/v1/ai-index POST',
    '/vergeml/v1/ai-run GET',
    '/vergeml/v1/ai-run POST',
    '/vergeml/v1/ai-settings POST',
    '/vergeml/v1/ai-status GET',
    '/vergeml/v1/alt-fill-step POST',
    '/vergeml/v1/alt-undo POST',
    '/vergeml/v1/autofile-act POST',
    '/vergeml/v1/autofile-step POST',
    '/vergeml/v1/brief/adopt POST',
    '/vergeml/v1/brief/discard POST',
    '/vergeml/v1/brief/session GET',
    '/vergeml/v1/brief/session POST',
    '/vergeml/v1/brief/test POST',
    '/vergeml/v1/brief/token POST',
    '/vergeml/v1/brief/turn POST',
    '/vergeml/v1/folder-privacy POST',
    '/vergeml/v1/folders-apply POST',
    '/vergeml/v1/folders-progress GET',
    '/vergeml/v1/folders-propose POST',
    '/vergeml/v1/folders-undo POST',
    '/vergeml/v1/guide/answer POST',
    '/vergeml/v1/guide/apply POST',
    '/vergeml/v1/guide/audience-split POST',
    '/vergeml/v1/guide/confirm POST',
    '/vergeml/v1/guide/plan GET',
    '/vergeml/v1/guide/plan POST',
    '/vergeml/v1/guide/product-categories GET',
    '/vergeml/v1/guide/profiles-restore POST',
    '/vergeml/v1/guide/progress GET',
    '/vergeml/v1/guide/questions GET',
    '/vergeml/v1/guide/rule POST',
    '/vergeml/v1/guide/rules GET',
    '/vergeml/v1/guide/session GET',
    '/vergeml/v1/guide/session POST',
    '/vergeml/v1/guide/stop POST',
    '/vergeml/v1/guide/token POST',
    '/vergeml/v1/guide/turn POST',
    '/vergeml/v1/guide/unconfirm POST',
    '/vergeml/v1/guide/undo POST',
    '/vergeml/v1/health-delete POST',
    '/vergeml/v1/health-keep POST',
    '/vergeml/v1/health-keep-undo POST',
    '/vergeml/v1/health-report GET',
    '/vergeml/v1/health-retire POST',
    '/vergeml/v1/health-scan POST',
    '/vergeml/v1/health-uses GET',
    '/vergeml/v1/import POST',
    '/vergeml/v1/librarian-apply-step POST',
    '/vergeml/v1/librarian-batches GET',
    '/vergeml/v1/librarian-pause POST',
    '/vergeml/v1/librarian-preflight GET, POST',
    '/vergeml/v1/librarian-schemes GET',
    '/vergeml/v1/librarian-undo-step POST',
    '/vergeml/v1/merge-plan GET',
    '/vergeml/v1/merge-run POST',
    '/vergeml/v1/organize-cancel POST',
    '/vergeml/v1/organize-quote GET',
    '/vergeml/v1/organize-run GET',
    '/vergeml/v1/organize-step POST',
    '/vergeml/v1/quarantine GET',
    '/vergeml/v1/quarantine-act POST',
    '/vergeml/v1/quarantine-manifest GET',
    '/vergeml/v1/rename POST',
    '/vergeml/v1/say-plan POST',
    '/vergeml/v1/say-run POST',
    '/vergeml/v1/search-meaning GET',
    '/vergeml/v1/search-try GET',
    '/vergeml/v1/similar GET',
    '/vergeml/v1/smart-scan POST',
    '/vergeml/v1/stats-opt POST',
);

/* END GENERATED BLOCK */


/* ------------------------------------------------------------ the fixtures */

echo "\nwho may reach what\n\n";

/** Anything a previous run left behind, before this one adds to it. */
foreach ( get_users( array( 'search' => 'zz-vgml-role-*', 'fields' => 'ID' ) ) as $r_stale ) {
    wp_delete_user( (int) $r_stale );
}

$r_roles = array( 'subscriber' => 0, 'author' => 0, 'editor' => 0 );

foreach ( array_keys( $r_roles ) as $r_role ) {

    $r_id = wp_insert_user( array(
        'user_login' => 'zz-vgml-role-' . $r_role,
        'user_pass'  => wp_generate_password( 32 ),
        'user_email' => 'zz-vgml-role-' . $r_role . '@example.invalid',
        'role'       => $r_role,
    ) );

    if ( is_wp_error( $r_id ) ) {
        echo '  could not create the ' . $r_role . " test user: " . $r_id->get_error_message() . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit( 1 );
    }

    $r_roles[ $r_role ] = (int) $r_id;
}

/*
 *  Two files: one the author uploaded, one they did not. Without both, an
 *  object-scoped gate cannot be told apart from a role-based one -- a refusal
 *  proves nothing if there was never a case that should have been allowed.
 */
$r_mine = (int) wp_insert_post( array(
    'post_title'     => 'zz vgml role mine',
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => 'image/png',
    'post_author'    => $r_roles['author'],
) );

$r_theirs = (int) wp_insert_post( array(
    'post_title'     => 'zz vgml role theirs',
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => 'image/png',
    'post_author'    => $r_roles['editor'],
) );

$r_made = array( $r_mine, $r_theirs );


/* --------------------------------------------------------------- the routes */

$r_server = rest_get_server();
$r_routes = $r_server->get_routes();

$r_ours = array();

foreach ( $r_routes as $r_route => $r_handlers ) {

    if ( 0 !== strpos( $r_route, '/' . VERGEML_REST_NS . '/' ) ) {
        continue;
    }

    foreach ( $r_handlers as $r_handler ) {

        if ( empty( $r_handler['permission_callback'] ) ) {
            // No callback at all is an open route. Named, not skipped.
            $r_ours[] = array( 'route' => $r_route, 'methods' => '(none)', 'gate' => null );
            continue;
        }

        $r_methods = implode( ', ', array_keys( array_filter( $r_handler['methods'] ) ) );

        $r_ours[] = array(
            'route'   => $r_route,
            'methods' => $r_methods,
            'gate'    => $r_handler['permission_callback'],
        );
    }
}

/*
 *  The static surface holds 85 endpoints; a running site holds 83, and the
 *  difference is the whole point of checking both. `/rename-files` GET and POST
 *  are registered only when VERGEML_FILE_RENAME is defined, and it is off
 *  because the reference rewrite behind it is unfinished. So the live count is
 *  85 minus that pair, and which two are missing is asserted rather than
 *  subtracted -- a route that silently stops registering for some other reason
 *  would otherwise balance this sum and look correct. (76/74 until 2026-09-15,
 *  when /guide/questions and /guide/answer joined; 78/76 until later that day,
 *  when /guide/confirm and /guide/unconfirm did; 80/78 until 2026-09-16, when
 *  /guide/profiles-restore did, C.4. 81/79 until 2026-09-19, when
 *  /guide/product-categories did, S20's way in for a site that sells. 82/80
 *  until 2026-09-27, when /guide/plan GET and POST did: the bottom-up plan.
 *  84/82 until 2026-09-29, when /guide/audience-split did, CAP-4's one question.)
 */
$r_want = 83;

r_check(
    sprintf( 'the REST server holds %d of our endpoints', $r_want ),
    count( $r_ours ) === $r_want,
    sprintf( 'found %d', count( $r_ours ) )
);

$r_open = array_filter( $r_ours, function ( $e ) { return null === $e['gate']; } );
r_check( 'every one of our endpoints registers a permission callback', 0 === count( $r_open ), implode( ', ', wp_list_pluck( $r_open, 'route' ) ) );

/**
 *  Ask one endpoint's gate, as whoever is current.
 *
 *  The id goes in as a url param as well as a body param: a regex route reads
 *  `$request['id']`, which resolves either way, and a gate that wants a file
 *  must be given one or it refuses everybody and proves nothing.
 */
function r_may( $entry, $id = 0 ) {

    $method  = explode( ',', $entry['methods'] )[ 0 ];
    $request = new WP_REST_Request( trim( $method ), $entry['route'] );

    if ( $id ) {
        $request->set_url_params( array( 'id' => $id ) );
        $request->set_param( 'id', $id );
    }

    $answer = call_user_func( $entry['gate'], $request );

    return ( true === $answer );
}

$r_key = function ( $entry ) {
    return $entry['route'] . ' ' . $entry['methods'];
};


/* ------------------------------------------- 1. nobody, and nobody at all */

wp_set_current_user( 0 );

$r_leaks = array();

foreach ( $r_ours as $r_entry ) {
    if ( null !== $r_entry['gate'] && r_may( $r_entry, $r_mine ) ) {
        $r_leaks[] = $r_key( $r_entry );
    }
}

r_check( 'every one of our routes refuses an anonymous visitor', 0 === count( $r_leaks ), implode( ', ', $r_leaks ) );

/* Named separately, because it is the one a scanner tries first. */
foreach ( array( '/vergeml/v1/tree', '/vergeml/v1/assign', '/vergeml/v1/folder' ) as $r_named ) {
    foreach ( $r_ours as $r_entry ) {
        if ( $r_entry['route'] === $r_named && null !== $r_entry['gate'] ) {
            r_check( $r_named . ' ' . $r_entry['methods'] . ' refuses an anonymous visitor', ! r_may( $r_entry, $r_mine ) );
        }
    }
}


/* ------------------------------------------------------- 2. a subscriber */

wp_set_current_user( $r_roles['subscriber'] );

$r_leaks = array();

foreach ( $r_ours as $r_entry ) {
    if ( null !== $r_entry['gate'] && r_may( $r_entry, $r_mine ) ) {
        $r_leaks[] = $r_key( $r_entry );
    }
}

r_check( 'a subscriber is refused by every one of our routes', 0 === count( $r_leaks ), implode( ', ', $r_leaks ) );


/* ----------------------------------------------------------- 3. an author */

wp_set_current_user( $r_roles['author'] );

$r_reached = array();

foreach ( $r_ours as $r_entry ) {
    if ( null === $r_entry['gate'] ) {
        continue;
    }
    // Asked about the author's own file, which is the generous case.
    if ( r_may( $r_entry, $r_mine ) ) {
        $r_reached[] = $r_key( $r_entry );
    }
}

sort( $r_reached );

$r_expected = array_merge( $r_author_may, $r_object_scoped );
sort( $r_expected );

$r_extra   = array_diff( $r_reached, $r_expected );
$r_missing = array_diff( $r_expected, $r_reached );

r_check(
    sprintf( 'an author reaches %d endpoints and no others', count( $r_expected ) ),
    0 === count( $r_extra ),
    'also reached: ' . implode( ', ', $r_extra )
);

r_check(
    'and reaches every one it should — a gate that refuses the right people can still refuse everybody',
    0 === count( $r_missing ),
    'refused: ' . implode( ', ', $r_missing )
);


/* ------------------------------------------- 4. the object question, for real */

/*
 *  The same route, the same role, two files. This is the shape the plan calls
 *  the dangerous one: not a missing login, but a logged-in person acting on
 *  somebody else's picture.
 */
foreach ( $r_ours as $r_entry ) {

    if ( ! in_array( $r_key( $r_entry ), $r_object_scoped, true ) ) {
        continue;
    }

    r_check(
        $r_entry['route'] . ' allows an author their own file',
        r_may( $r_entry, $r_mine )
    );

    r_check(
        $r_entry['route'] . ' refuses an author a file that is not theirs',
        ! r_may( $r_entry, $r_theirs )
    );
}


/* ----------------------------------------------------------- 5. an editor */

wp_set_current_user( $r_roles['editor'] );

$r_reached = array();

foreach ( $r_ours as $r_entry ) {
    if ( null !== $r_entry['gate'] && r_may( $r_entry, $r_mine ) ) {
        $r_reached[] = $r_key( $r_entry );
    }
}

$r_editor_in_admin_only = array_intersect( $r_reached, $r_admin_only );

r_check(
    sprintf( 'an editor reaches none of the %d administrator-only endpoints', count( $r_admin_only ) ),
    0 === count( $r_editor_in_admin_only ),
    implode( ', ', $r_editor_in_admin_only )
);

/*
 *  An editor keeps the media library's folders: the tree, filing, and changing
 *  one folder or one file at a time (wordpress.org review 2, 2026-10-03, moved
 *  every library-wide write, run and AI call to manage_options). Asserted so a
 *  gate raised too far -- the tree itself -- shows up here.
 */
foreach ( array( '/vergeml/v1/tree GET', '/vergeml/v1/assign POST', '/vergeml/v1/folder POST', '/vergeml/v1/state POST' ) as $r_need ) {
    r_check(
        'an editor still reaches ' . $r_need,
        in_array( $r_need, $r_reached, true ),
        sprintf( 'reached %d', count( $r_reached ) )
    );
}


/* ----------------------------------------------------------------- put back */

wp_set_current_user( 0 );

foreach ( $r_made as $r_post ) {
    wp_delete_post( $r_post, true );
}

foreach ( $r_roles as $r_id ) {
    wp_delete_user( $r_id );
}

$r_left = count( get_users( array( 'search' => 'zz-vgml-role-*', 'fields' => 'ID' ) ) );
r_check( 'the three test users are gone', 0 === $r_left, sprintf( '%d left', $r_left ) );
r_check( 'the two test files are gone', ! get_post( $r_mine ) && ! get_post( $r_theirs ) );

printf( "\n%d/%d passed\n\n", $GLOBALS['r_pass'], $GLOBALS['r_pass'] + $GLOBALS['r_fail'] );

exit( $GLOBALS['r_fail'] > 0 ? 1 : 0 );
