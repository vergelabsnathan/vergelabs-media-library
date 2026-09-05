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
g_check( 'A1 the boot data costs at most nine queries (render 1 + the request 1 + the tree 7, as measured on 2026-09-05)', $g_cost <= 9, $g_cost . ' queries' );
g_check( 'A2 it carries the tree, the session and the stamp', isset( $g_boot['nodes'], $g_boot['session'], $g_boot['version'], $g_boot['facts'] ) && is_array( $g_boot['nodes'] ) );
$g_before = $wpdb->num_queries;
ob_start();
vergeml_folders_page();
$g_html = ob_get_clean();
$g_more = $wpdb->num_queries - $g_before;
g_check( 'A3 the page itself adds no query to that', 0 === $g_more, $g_more . ' more' );
g_check( 'A4 the facts line and the root are in the page', false !== strpos( $g_html, 'vgml-folders-facts' ) && false !== strpos( $g_html, 'id="vgml-folders"' ) );
g_check( 'A5 the facts line says pictures, folders, in no folder', 1 === preg_match( '/pictures · \d+ folders · [\d,.]+ in no folder/', $g_html ) || false !== strpos( $g_html, 'No pictures described yet' ), wp_strip_all_tags( substr( $g_html, strpos( $g_html, 'vgml-folders-facts' ), 160 ) ) );

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
