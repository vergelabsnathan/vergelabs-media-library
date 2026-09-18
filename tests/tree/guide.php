<?php
/**
 *  The Folders screen's server side (core/guide.php).
 *
 *      wp eval-file tests/tree/guide.php --allow-root
 *
 *  Gate 5: the page's first paint. The Sort screen it replaced cost 1 query
 *  to render and made one REST request (1 query) before it painted, measured
 *  on the box on 2026-09-05; this screen paints from the data that came with
 *  the page, so its render may cost what that render, that request and the
 *  tree it did not draw (the /tree budget of 7) cost together: nine.
 *
 *  Then the pieces that decide what a Move does: a draft made safe, the four
 *  rules, the plan the re-filing takes (a renamed folder keeps its id; a
 *  removed folder's pictures fall back to the folder that absorbed it), the
 *  done line, and the cap.
 *
 *  Mutation check run against this suite on 2026-09-05: with the cap check
 *  removed from vergeml_guide_turn_apply(), E2 goes red (and E4 with it,
 *  since the turn that should have been refused is then counted): 28/30.
 *
 *  Reads the library and writes nothing that stays: the session option is
 *  put back as it was found.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_folders_boot' ) || ! function_exists( 'vergeml_guide_rule' ) ) {
    echo "core/guide.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

wp_set_current_user( 1 );

$GLOBALS['g_pass'] = 0;
$GLOBALS['g_fail'] = 0;

function g_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['g_pass']++;
    } else {
        $GLOBALS['g_fail']++;
    }
    echo sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

$g_was = get_option( VERGEML_GUIDE_OPTION );

/* ------------------------------------------------------ A  Gate 5: the paint */

echo "\nA  Gate 5: the page paints from what came with it\n\n";

global $wpdb;
$g_before = $wpdb->num_queries;
$g_boot   = vergeml_folders_boot();
$g_cost   = $wpdb->num_queries - $g_before;
// Nine on 2026-09-05 (render 1 + the request 1 + the tree 7); eleven measured on 2026-09-15 with the rail's steps (images, not described, without alt, the fill's state and its unfiled count); twelve with Step 4's own count (the pictures whose catalogue alt the button writes).
g_check( 'A1 the boot data costs at most thirteen queries (twelve as measured on 2026-09-15 with the rail\'s steps and Step 4\'s count; one more since S17\'s rail on a site that sells, 7d13048: the on_products count)', $g_cost <= 13, $g_cost . ' queries' );
g_check( 'A2 it carries the tree, the session and the stamp', isset( $g_boot['nodes'], $g_boot['session'], $g_boot['version'], $g_boot['facts'] ) && is_array( $g_boot['nodes'] ) );
$g_before = $wpdb->num_queries;
ob_start();
vergeml_folders_page();
$g_html = ob_get_clean();
$g_more = $wpdb->num_queries - $g_before;
g_check( 'A3 the page itself adds no query to that', 0 === $g_more, $g_more . ' more' );
g_check( 'A4 the head pills, the rail and the root are in the page', false !== strpos( $g_html, 'vgml-folders-facts' ) && false !== strpos( $g_html, 'class="g-rail"' ) && false !== strpos( $g_html, 'id="vgml-folders"' ) );
g_check( 'A5 the head says pictures, described, folders as three pills', 3 === preg_match_all( '/<span class="g-pill" data-fact="(images|described|folders)"><b>[\d,.]+<\/b> (pictures|described|folders)<\/span>/', $g_html ), wp_strip_all_tags( substr( $g_html, strpos( $g_html, 'vgml-folders-facts' ), 400 ) ) );
g_check( 'A6 the rail is five steps and every one is a button', 5 === preg_match_all( '/<button type="button" class="g-step" data-step="(describe|tree|fill|alt|rename)">/', $g_html ), (string) preg_match_all( '/class="g-step"/', $g_html ) . ' steps' );

/* ------------------------------------------------------ B  a draft, made safe */

echo "\nB  a draft from the browser, made safe\n\n";

$g_nodes = $g_boot['nodes'];
$g_first = $g_nodes ? $g_nodes[0] : array( 'id' => 0, 'name' => 'none', 'parent' => 0 );
$g_draft = vergeml_guide_clean_draft( array(
    'folders' => array(
        array( 'key' => 't' . $g_first['id'], 'term_id' => $g_first['id'], 'name' => 'Renamed / by hand', 'parent' => '', 'by' => 'you' ),
        array( 'key' => 'new1', 'term_id' => null, 'name' => 'Made new', 'parent' => 'nowhere', 'count' => 12, 'classes' => array( 'probe' ), 'kinds' => array( 'photo' ) ),
        array( 'key' => 'bad key!', 'name' => 'Key cleaned', 'parent' => 't' . $g_first['id'] ),
    ),
    'gone'    => array( '77' => 'new1', '78' => 'nowhere' ),
    'tags'    => array( array( 'name' => 'Colour', 'values' => array( 'tan', '' ) ) ),
    'origin'  => 'rule',
    'rule'    => array( 'id' => 'kind', 'options' => array( 'scope' => 'everything' ) ),
) );
g_check( 'B1 a slash in a name becomes a dash', 'Renamed - by hand' === $g_draft['folders'][0]['name'], $g_draft['folders'][0]['name'] );
g_check( 'B2 a parent key that names nothing becomes the top level', '' === $g_draft['folders'][1]['parent'] );
g_check( 'B3 a key is letters, digits and punctuation only', 'badkey' === $g_draft['folders'][2]['key'], $g_draft['folders'][2]['key'] );
g_check( 'B4 gone keeps a known destination and drops an unknown one', 'new1' === $g_draft['gone'][77] && '' === $g_draft['gone'][78] );
g_check( 'B5 a tag rides along without its empty values', array( 'tan' ) === $g_draft['tags'][0]['values'] );
g_check( 'B6 a rule outside its closed list falls to its default', 'rule' === $g_draft['origin'] && 'unfiled' === $g_draft['rule']['options']['scope'] );

/* -------------------------------------------------------- C  the plan for Move */

echo "\nC  the plan the re-filing takes\n\n";

$g_plan = vergeml_guide_apply_plan( array(
    'folders' => array(
        array( 'key' => 'a', 'term_id' => null, 'name' => 'Child', 'parent' => 'p', 'matches' => '', 'classes' => array(), 'kinds' => array(), 'audience' => '' ),
        array( 'key' => 'p', 'term_id' => null, 'name' => 'Parent', 'parent' => '', 'matches' => '', 'classes' => array(), 'kinds' => array(), 'audience' => '' ),
        array( 'key' => 't' . $g_first['id'], 'term_id' => $g_first['id'], 'name' => 'Kept and renamed', 'parent' => '', 'matches' => 'm', 'classes' => array( 'x' ), 'kinds' => array( 'photo' ), 'audience' => '' ),
    ),
    'gone'    => array( '77' => 'a', '78' => '' ),
    'tags'    => array(),
    'origin'  => 'talk',
    'rule'    => null,
) );
$g_names = is_wp_error( $g_plan ) ? array() : array_map( function ( $f ) { return $f['name']; }, $g_plan['folders'] );
$g_kept  = is_wp_error( $g_plan ) ? null : $g_plan['folders'][ array_search( 'Kept and renamed', $g_names, true ) ];
g_check( 'C1 parents come before children, whatever the draft\'s order', ! is_wp_error( $g_plan ) && array_search( 'Parent', $g_names, true ) < array_search( 'Child', $g_names, true ) && 'Parent' === $g_plan['folders'][ array_search( 'Child', $g_names, true ) ]['parent'], implode( ', ', $g_names ) );
g_check( 'C2 a folder that exists is addressed by its term id', $g_kept && (int) $g_first['id'] === (int) $g_kept['term_id'] );
g_check( 'C3 a removed folder\'s pictures fall back to the folder that took them, keyed as the re-filing keys folders', ! is_wp_error( $g_plan ) && isset( $g_plan['opts']['fallback'][77] ) && vergeml_talk_key( 'Parent', 'Child' ) === $g_plan['opts']['fallback'][77] && ! isset( $g_plan['opts']['fallback'][78] ) );
g_check( 'C4 a conversation draft carries no assignment: the evidence files it', ! is_wp_error( $g_plan ) && array() === $g_plan['opts']['assign'] );
$g_empty = vergeml_guide_apply_plan( array( 'folders' => array(), 'gone' => array(), 'origin' => 'talk', 'rule' => null ) );
g_check( 'C5 an empty draft is refused', is_wp_error( $g_empty ) );

/* ---------------------------------------------------------------- D  the rules */

echo "\nD  the rules\n\n";

$g_tax  = vergeml_librarian_taxonomy();
$g_live = count( $g_nodes );
$g_kind = vergeml_guide_rule( 'kind', array( 'scope' => 'unfiled' ) );
g_check( 'D1 by kind: every live folder is kept, by id', ! is_wp_error( $g_kind ) && count( array_filter( $g_kind['draft']['folders'], function ( $f ) { return ! empty( $f['term_id'] ); } ) ) === $g_live );
g_check( 'D2 by kind: the folders it makes are new, and the pictures it moves are assigned to them', ! is_wp_error( $g_kind ) && $g_kind['made'] >= 0 && count( $g_kind['assign'] ) === (int) $g_kind['move'] && ( 0 === $g_kind['move'] || count( array_unique( array_values( $g_kind['assign'] ) ) ) <= $g_kind['made'] + $g_live ), sprintf( '%d made, %d move', $g_kind['made'], $g_kind['move'] ) );
g_check( 'D3 by kind: the preview leads with the folders, then the pictures, then today\'s folders', ! is_wp_error( $g_kind ) && ! empty( $g_kind['preview'][0]['strong'] ) && 'Today\'s folders unchanged' === end( $g_kind['preview'] )['text'], wp_json_encode( array_map( function ( $l ) { return $l['text']; }, $g_kind['preview'] ) ) );
$g_all = vergeml_guide_rule( 'kind', array( 'scope' => 'all' ) );
g_check( 'D4 by kind, every picture: every live folder goes, with where its pictures land', ! is_wp_error( $g_all ) && count( $g_all['draft']['gone'] ) === $g_live && 0 === count( array_filter( $g_all['draft']['folders'], function ( $f ) { return ! empty( $f['term_id'] ); } ) ) );
$g_date = vergeml_guide_rule( 'date', array( 'source' => 'upload', 'levels' => 'ym', 'scope' => 'unfiled' ) );
g_check( 'D5 by month and year: a year folder with a month under it', ! is_wp_error( $g_date ) && ( 0 === $g_date['move'] || ( $g_date['made'] >= 2 && count( array_filter( $g_date['draft']['folders'], function ( $f ) { return empty( $f['term_id'] ) && '' !== $f['parent']; } ) ) >= 1 ) ), sprintf( '%d made, %d move', $g_date['made'], $g_date['move'] ) );
$g_subj = vergeml_guide_rule( 'subject', array( 'min' => 10, 'levels' => 'one', 'scope' => 'unfiled' ) );
g_check( 'D6 by subject: no folder under the smallest size', ! is_wp_error( $g_subj ) && ( 0 === $g_subj['made'] || min( array_map( function ( $f ) { return (int) $f['count']; }, array_filter( $g_subj['draft']['folders'], function ( $f ) { return empty( $f['term_id'] ); } ) ) ) >= 10 ), sprintf( '%d made, %d move', $g_subj['made'], $g_subj['move'] ) );
$g_fit = vergeml_guide_rule( 'fit', array( 'rest' => 'stay', 'sure' => 'sure' ) );
g_check( 'D7 into today\'s folders makes none', ! is_wp_error( $g_fit ) && 0 === (int) $g_fit['made'], sprintf( '%d move; %s', $g_fit['move'], wp_json_encode( array_map( function ( $l ) { return $l['text']; }, $g_fit['preview'] ) ) ) );
g_check( 'D8 a rule is deterministic: asked twice, the same answer', ! is_wp_error( $g_kind ) && wp_json_encode( $g_kind['draft'] ) === wp_json_encode( vergeml_guide_rule( 'kind', array( 'scope' => 'unfiled' ) )['draft'] ) );
$g_bad = vergeml_guide_rule( 'nosuch', array() );
g_check( 'D9 no such rule is refused', is_wp_error( $g_bad ) );

/* ------------------------------------------------- E  the done line, the cap */

echo "\nE  the done line and the cap\n\n";

$g_s          = vergeml_guide_fresh();
$g_s['apply'] = array( 'started_at' => time(), 'running' => true, 'stopped' => false );
$g_s['draft'] = array( 'folders' => array(), 'gone' => array(), 'origin' => 'talk', 'rule' => null );
$g_out = vergeml_guide_progress_out( $g_s, array( 'running' => false, 'moved' => 354, 'folders' => 12, 'total' => 641, 'removed' => 0, 'until' => time() + DAY_IN_SECONDS, 'stopped' => false ) );
$g_line = $g_s['turns'] ? end( $g_s['turns'] ) : null;
g_check( 'E1 when the run ends, one line in the conversation: moved, stayed, undo until', $g_line && 'moved' === $g_line['kind'] && false !== strpos( $g_line['text'], '354 pictures moved into 12 folders' ) && false !== strpos( $g_line['text'], '287 stayed where they were' ) && false !== strpos( $g_line['text'], 'Undo until' ) && null === $g_s['draft'] && null === $g_s['apply'], $g_line ? str_replace( "\n", ' | ', $g_line['text'] ) : 'no line' );

$g_s = vergeml_guide_fresh();
$g_s['assistant_turns'] = VERGEML_GUIDE_TURN_CAP;
vergeml_guide_save( $g_s );
$g_req = new WP_REST_Request( 'POST', '/vergeml/v1/guide/turn' );
$g_req->set_body_params( array( 'say' => array( 'text' => 'one more', 'choices' => array() ) ) );
$g_res = rest_do_request( $g_req );
g_check( 'E2 at the cap a further assistant turn is refused', 429 === $g_res->get_status(), (string) $g_res->get_status() );
$g_req = new WP_REST_Request( 'POST', '/vergeml/v1/guide/turn' );
$g_req->set_body_params( array( 'said' => array( 'kind' => 'edit', 'text' => 'Moved Boots under Shoes' ) ) );
$g_res = rest_do_request( $g_req );
g_check( 'E3 but a hand edit still writes its line', 200 === $g_res->get_status() && 'edit' === end( $g_res->get_data()['turns'] )['kind'] );
$g_req = new WP_REST_Request( 'POST', '/vergeml/v1/guide/turn' );
$g_req->set_body_params( array( 'said' => array( 'kind' => 'rule', 'rule' => 'kind', 'text' => 'By kind: 4 folders, 265 pictures' ) ) );
rest_do_request( $g_req );
$g_req->set_body_params( array( 'said' => array( 'kind' => 'rule', 'rule' => 'kind', 'text' => 'By kind: 4 folders, 641 pictures' ) ) );
$g_res = rest_do_request( $g_req );
$g_turns = $g_res->get_data()['turns'];
g_check( 'E4 a rule applied over the same rule\'s line replaces it', 2 === count( $g_turns ) && 'By kind: 4 folders, 641 pictures' === end( $g_turns )['text'], count( $g_turns ) . ' turns' );

/* ------------------------------------------------ F  the dry run at any shape */

/*
 *  S10.3. pairs = pictures × folders; above 250,000 the turn answers at once
 *  with a pending fit and books a job; the job writes the fit back only if
 *  the draft is still the one asked about. Driven here with a draft of 260
 *  folders over this site's described pictures (1,000 on the box: 260,000
 *  pairs, over the cap). The service is answered by a stub -- a one-hot
 *  vector per folder text -- so the job spends nothing and the vectors it
 *  caches are removed after. Mutation: the deferral removed
 *  (vergeml_guide_fit_defers always false) -> F2 red: the turn works the
 *  fit in the request, and answers counted or unknown, never pending.
 */
echo "\nF  the dry run at any shape (S10.3)\n\n";

g_check( 'F1 the rule, pure: 1,000 × 500 defers, 626 × 319 does not (the shop\'s 199,694 in 15 s), 500 × 500 sits on the cap and does not', vergeml_guide_fit_defers( 1000, 500 ) && ! vergeml_guide_fit_defers( 626, 319 ) && ! vergeml_guide_fit_defers( 500, 500 ) );

$GLOBALS['g_texts']  = array();
$GLOBALS['g_embeds'] = 0; // Every /embed request answered here, counted: the apply must not make one per folder (S15).
function g_answer( $pre, $args, $url ) {
    // The text model's service (S18): a dry count must never ask it. Counted, and down.
    if ( false !== strpos( $url, '/v1/file' ) ) {
        $GLOBALS['g_files']++;
        return array( 'response' => array( 'code' => 502 ), 'body' => '', 'headers' => array() );
    }
    if ( false === strpos( $url, '/embed' ) ) {
        return $pre;
    }
    $GLOBALS['g_embeds']++;
    $body  = isset( $args['body'] ) ? json_decode( (string) $args['body'], true ) : array();
    $texts = isset( $body['texts'] ) ? (array) $body['texts'] : ( isset( $body['text'] ) ? array( $body['text'] ) : array() );
    $out   = array();
    foreach ( $texts as $t ) {
        $t = strtolower( trim( (string) $t ) );
        if ( ! isset( $GLOBALS['g_texts'][ $t ] ) ) {
            $GLOBALS['g_texts'][ $t ] = count( $GLOBALS['g_texts'] ) + 1;
        }
        $v = array_fill( 0, 64, 0.0 );
        $v[ $GLOBALS['g_texts'][ $t ] % 64 ] = 1.0;
        $out[] = $v;
    }
    $data = isset( $body['texts'] ) ? array( 'embeddings' => $out ) : array( 'embedding' => $out[0] );
    return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $data ), 'headers' => array() );
}
add_filter( 'pre_http_request', 'g_answer', 1, 3 );
$GLOBALS['g_files'] = 0;

/*
 *  The site's own wp-cron.php, answered here and never sent (S14). F6 books
 *  a fit job and spawns cron; sent for real, the box ran that job -- 1,000
 *  pictures against 200 folders, up to 240 s -- beside F7's own fit in the
 *  poll, which starved past its 20 s budget and settled unknown (48 s where
 *  4 s alone). Section G leans on the same answer for the fill's spawn.
 *  Mutation: this filter added only at G, as before -> F7 red.
 */
function g_answer_cron( $pre, $args, $url ) {
    return false !== strpos( $url, 'wp-cron.php' ) ? array( 'response' => array( 'code' => 200 ), 'body' => '', 'headers' => array() ) : $pre;
}
add_filter( 'pre_http_request', 'g_answer_cron', 1, 3 );

$g_described = vergeml_guide_described_count();
$g_big = array( 'folders' => array(), 'gone' => array(), 'origin' => 'talk', 'rule' => null );
for ( $i = 1; $i <= 260; $i++ ) {
    $g_big['folders'][] = array( 'key' => 'zzfit' . $i, 'term_id' => null, 'name' => 'zzFit ' . $i, 'parent' => '', 'count' => 7, 'classes' => array( 'zzfitword' . $i ), 'kinds' => array( 'photo' ) );
}
vergeml_guide_save( vergeml_guide_fresh() );
wp_clear_scheduled_hook( VERGEML_GUIDE_FIT_HOOK );
$g_t0  = microtime( true );
$g_req = new WP_REST_Request( 'POST', '/vergeml/v1/guide/turn' );
$g_req->set_body_params( array( 'draft' => $g_big ) );
$g_res = rest_do_request( $g_req );
$g_ms  = ( microtime( true ) - $g_t0 ) * 1000;
$g_fit = $g_res->get_data()['fit'] ?? null;
$g_row = $g_res->get_data()['draft']['folders'][0];
$g_cnt = array_key_exists( 'count', $g_row ) ? $g_row['count'] : 'unset';
g_check( sprintf( 'F2 a turn with %d × 260 pairs answers at once: counted false, pending true, the two numbers, no count on a folder, the job booked (%.0f ms)', $g_described, $g_ms ), 200 === $g_res->get_status() && is_array( $g_fit ) && false === $g_fit['counted'] && ! empty( $g_fit['pending'] ) && 260 === (int) $g_fit['folders'] && $g_described === (int) $g_fit['pictures'] && null === $g_cnt && false !== wp_next_scheduled( VERGEML_GUIDE_FIT_HOOK ) && $g_ms < 5000, json_encode( array( 'status' => $g_res->get_status(), 'fit' => is_array( $g_fit ) ? array_intersect_key( $g_fit, array_flip( array( 'counted', 'pending', 'pictures', 'folders' ) ) ) : $g_fit, 'count' => $g_cnt, 'booked' => wp_next_scheduled( VERGEML_GUIDE_FIT_HOOK ) ) ) );
g_check( 'F3 the pending fit\'s one line says what is being counted', is_array( $g_fit ) && isset( $g_fit['preview'][0]['text'] ) && 0 === strpos( $g_fit['preview'][0]['text'], 'Counting ' ) && false !== strpos( $g_fit['preview'][0]['text'], '260 folders' ), is_array( $g_fit ) && isset( $g_fit['preview'][0]['text'] ) ? $g_fit['preview'][0]['text'] : '-' );

$g_t0 = microtime( true );
vergeml_guide_fit_event();
$g_s   = ( microtime( true ) - $g_t0 );
$g_now = vergeml_guide_session();
$g_fit = $g_now['fit'];
g_check( sprintf( 'F4 the job counts it: counted true, looked = the described count, every folder carries a count (%.1f s, %d queries so far)', $g_s, $wpdb->num_queries ), is_array( $g_fit ) && ! empty( $g_fit['counted'] ) && empty( $g_fit['pending'] ) && $g_described === (int) $g_fit['looked'] && 260 === count( $g_fit['counts'] ) && null !== $g_now['draft']['folders'][0]['count'], json_encode( is_array( $g_fit ) ? array_intersect_key( $g_fit, array_flip( array( 'counted', 'pending', 'looked', 'move' ) ) ) : $g_fit ) );
// The dry count is the rules' alone (S18 task 6): the model's service is never asked on a count, and the tally says so.
g_check( 'F4b the count asked the text model nothing, and its tally says rules_only', 0 === $GLOBALS['g_files'] && is_array( $g_fit ) && isset( $g_fit['tally']['rules_only'] ) && true === $g_fit['tally']['rules_only'], json_encode( array( 'files' => $GLOBALS['g_files'], 'rules_only' => isset( $g_fit['tally']['rules_only'] ) ? $g_fit['tally']['rules_only'] : null ) ) );

// The draft moved on while a job ran: its answer is not written over the newer tree.
$g_now['fit'] = vergeml_guide_fit_pending( $g_described, 260, 'not-this-draft' );
vergeml_guide_save( $g_now );
vergeml_guide_fit_event();
g_check( 'F5 a job whose draft has moved on writes nothing: the fit stays pending for the newer draft\'s own turn', ! empty( vergeml_guide_session()['fit']['pending'] ) );

// A shape the request may hold, but a request whose budget ran out (a planner call ahead of it, 2026-09-16): the job takes it, never "unknown".
$g_mid = array( 'folders' => array_slice( $g_big['folders'], 0, 200 ), 'gone' => array(), 'origin' => 'talk', 'rule' => null );
add_filter( 'vergeml_guide_fit_budget', 'g_tiny_budget' );
function g_tiny_budget() {
    return 1;
}
vergeml_guide_save( vergeml_guide_fresh() );
wp_clear_scheduled_hook( VERGEML_GUIDE_FIT_HOOK );
$g_req = new WP_REST_Request( 'POST', '/vergeml/v1/guide/turn' );
$g_req->set_body_params( array( 'draft' => $g_mid ) );
$g_res = rest_do_request( $g_req );
$g_fit = $g_res->get_data()['fit'] ?? null;
remove_filter( 'vergeml_guide_fit_budget', 'g_tiny_budget' );
g_check( sprintf( 'F6 %d × 200 pairs (under the cap) with a one-second budget: the request gives up and books the job -- pending, never unknown', $g_described ), is_array( $g_fit ) && ! empty( $g_fit['pending'] ) && false !== wp_next_scheduled( VERGEML_GUIDE_FIT_HOOK ), json_encode( is_array( $g_fit ) ? array_intersect_key( $g_fit, array_flip( array( 'counted', 'pending' ) ) ) : $g_fit ) );

/*
 *  S11 review. A host whose cron never runs (DISABLE_WP_CRON, a blocked
 *  loopback) left a pending fit pending for ever: the poll re-booked the
 *  job and the row said "Counting" until the page was closed. Now the poll
 *  counts its revives; on the third with no job started it takes the fit
 *  itself when the shape fits a request, and settles it as unknown -- the
 *  honest line the screen showed before S10.3 -- when it does not.
 *  Mutation: the give-up removed (the revive only ever re-books) -> F7 red.
 */
$g_small = array( 'folders' => array_slice( $g_big['folders'], 0, 50 ), 'gone' => array(), 'origin' => 'talk', 'rule' => null );
$g_s          = vergeml_guide_fresh();
$g_s['draft'] = vergeml_guide_clean_draft( $g_small );
$g_s['fit']   = vergeml_guide_fit_pending( $g_described, 50, vergeml_guide_draft_hash( $g_s['draft'] ) );
$g_s['fit']['revived'] = 2;
vergeml_guide_save( $g_s );
wp_clear_scheduled_hook( VERGEML_GUIDE_FIT_HOOK );
wp_schedule_single_event( time() - 30, VERGEML_GUIDE_FIT_HOOK );
delete_transient( VERGEML_GUIDE_FIT_LOCK );
$g_res = rest_do_request( new WP_REST_Request( 'GET', '/vergeml/v1/guide/progress' ) );
$g_fit = vergeml_guide_session()['fit'];
g_check( sprintf( 'F7 the third poll on a job cron never started takes it: %d × 50 counted in the poll\'s request, no job left booked', $g_described ), 200 === $g_res->get_status() && is_array( $g_fit ) && ! empty( $g_fit['counted'] ) && empty( $g_fit['pending'] ) && 50 === count( $g_fit['counts'] ) && false === wp_next_scheduled( VERGEML_GUIDE_FIT_HOOK ), json_encode( is_array( $g_fit ) ? array_intersect_key( $g_fit, array_flip( array( 'counted', 'pending', 'revived' ) ) ) : $g_fit ) );

$g_s          = vergeml_guide_fresh();
$g_s['draft'] = vergeml_guide_clean_draft( $g_big );
$g_s['fit']   = vergeml_guide_fit_pending( $g_described, 260, vergeml_guide_draft_hash( $g_s['draft'] ) );
$g_s['fit']['revived'] = 2;
vergeml_guide_save( $g_s );
wp_clear_scheduled_hook( VERGEML_GUIDE_FIT_HOOK );
wp_schedule_single_event( time() - 30, VERGEML_GUIDE_FIT_HOOK );
rest_do_request( new WP_REST_Request( 'GET', '/vergeml/v1/guide/progress' ) );
$g_fit = vergeml_guide_session()['fit'];
g_check( 'F8 the same on a shape no request holds settles as unknown: counted false, not pending, the "not worked out" line', is_array( $g_fit ) && empty( $g_fit['counted'] ) && empty( $g_fit['pending'] ) && isset( $g_fit['preview'][0]['text'] ) && 'The counts are not worked out yet' === $g_fit['preview'][0]['text'], json_encode( is_array( $g_fit ) ? array( 'counted' => $g_fit['counted'], 'pending' => ! empty( $g_fit['pending'] ), 'line' => isset( $g_fit['preview'][0]['text'] ) ? $g_fit['preview'][0]['text'] : null ) : $g_fit ) );

/* ------------------------------------------------ G  a fill after a fill */

/*
 *  The run's end clears the draft (the tree is the library now), and until
 *  2026-09-16 the next press handed that empty draft to the plan and was
 *  refused ("Nothing moved."). A confirmed tree with no draft fills the live
 *  folders. Driven through the route on a confirmed session with no draft;
 *  the run it starts is stopped in the same breath, its event cleared, its
 *  spawn answered here (never sent), and the fill's options put back -- no
 *  pass runs and nothing moves. Mutation: the live-folders block removed from
 *  vergeml_guide_rest_apply -> G1 red (400, "Nothing moved").
 */
echo "\nG  a fill after a fill: a confirmed tree with no draft fills the live folders\n\n";

$g_state_was = get_option( VERGEML_TALK_STATE );
$g_undo_was  = get_option( VERGEML_TALK_UNDO );
$g_hook_was  = wp_next_scheduled( VERGEML_TALK_HOOK );
$g_s         = vergeml_guide_fresh();
$g_s['tree'] = 'confirmed';
$g_s['draft'] = null;
vergeml_guide_save( $g_s );
// The second text's cache emptied for every live folder, so a loop that still asks has to ask (the transients live a week; an earlier apply had warmed them).
foreach ( get_terms( array( 'taxonomy' => vergeml_librarian_taxonomy(), 'hide_empty' => false ) ) as $g_t ) {
    $g_p = vergeml_filing_profile( (int) $g_t->term_id, vergeml_librarian_taxonomy() );
    delete_transient( vergeml_meaning_slot( trim( vergeml_term_name( $g_t ) . '. ' . ( is_array( $g_p ) ? $g_p['matches'] : '' ) ) ) );
}
$GLOBALS['g_embeds'] = 0;
$g_res   = rest_do_request( new WP_REST_Request( 'POST', '/vergeml/v1/guide/apply' ) );
$g_embeds = (int) $GLOBALS['g_embeds'];
$g_data  = $g_res->get_data();
$g_state = get_option( VERGEML_TALK_STATE );
$g_live  = count( vergeml_folders_nodes( vergeml_librarian_taxonomy() ) );
vergeml_talk_refile_stop();
wp_clear_scheduled_hook( VERGEML_TALK_HOOK );
if ( false !== $g_hook_was ) {
    wp_schedule_single_event( (int) $g_hook_was, VERGEML_TALK_HOOK );
}
delete_transient( VERGEML_TALK_PASS_LOCK );
delete_transient( VERGEML_TALK_BEAT );
foreach ( array( VERGEML_TALK_STATE => $g_state_was, VERGEML_TALK_UNDO => $g_undo_was ) as $g_opt => $g_v ) {
    if ( false === $g_v ) {
        delete_option( $g_opt );
    } else {
        update_option( $g_opt, $g_v, false );
    }
}
remove_filter( 'pre_http_request', 'g_answer_cron', 1 );
g_check( sprintf( 'G1 apply on a confirmed tree with no draft starts a run over the %d live folders (200, running, nothing made), never "Nothing moved"', $g_live ), 200 === $g_res->get_status() && isset( $g_data['report']['running'] ) && true === $g_data['report']['running'] && is_array( $g_state ) && $g_live === count( (array) $g_state['ids'] ) && empty( $g_state['remove'] ) && 0 === (int) $g_state['seen'], json_encode( array( 'status' => $g_res->get_status(), 'message' => isset( $g_data['message'] ) ? $g_data['message'] : null, 'running' => isset( $g_data['report']['running'] ) ? $g_data['report']['running'] : null, 'ids' => is_array( $g_state ) ? count( (array) $g_state['ids'] ) : null, 'seen' => is_array( $g_state ) ? $g_state['seen'] : null ) ) );

/*
 *  The apply asked the service for one vector per folder -- "name. matches",
 *  a text the paste's dry run never prefetched -- for a state field nothing
 *  reads (S15): on HEMA's 292 folders that was ~150 of the request's 188 s
 *  with the button saying "Filling" and no number. The profiles the seed
 *  builds carry the vector the run files by, already cached by the dry run.
 *  Mutation: the per-folder vector loop put back -> G2 red (one request a folder).
 */
g_check( sprintf( 'G2 the apply makes no /embed request of its own: %d during the apply over %d folders (one a folder before)', $g_embeds, $g_live ), 0 === $g_embeds, (string) $g_embeds );

remove_filter( 'pre_http_request', 'g_answer', 1 );
foreach ( array_keys( $GLOBALS['g_texts'] ) as $g_text ) {
    delete_transient( vergeml_meaning_slot( $g_text ) );
}
wp_clear_scheduled_hook( VERGEML_GUIDE_FIT_HOOK );
delete_transient( VERGEML_GUIDE_FIT_LOCK );

/* ------------------------------------------------------------------ put back */

if ( false === $g_was ) {
    delete_option( VERGEML_GUIDE_OPTION );
} else {
    update_option( VERGEML_GUIDE_OPTION, $g_was, false );
}
g_check( 'the session is as it was found', ( false === $g_was && false === get_option( VERGEML_GUIDE_OPTION ) ) || ( false !== $g_was && get_option( VERGEML_GUIDE_OPTION ) === $g_was ) );

echo sprintf( "\n%d/%d passed\n", $GLOBALS['g_pass'], $GLOBALS['g_pass'] + $GLOBALS['g_fail'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
if ( $GLOBALS['g_fail'] > 0 ) {
    exit( 1 );
}
