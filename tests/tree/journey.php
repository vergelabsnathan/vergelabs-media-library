<?php
/**
 *  Where you are, and what to do next.
 *
 *  The claim worth defending is narrow: **exactly one stage ever says "do this
 *  next"**, and a stage that cannot run says which one has to happen first. A
 *  list where three rows are equally urgent is the screen this replaced.
 *
 *  Since 3.16.2 three more: the rail shows four counts and never a total; a
 *  to-do row exists only when there is something to do; and the folders row
 *  talks about files. Those are reached through the vergeml_journey_facts
 *  filter, for the same reason the stages are: the live library is in one
 *  state and the screen has to be right in all of them.
 *
 *      wp eval-file tests/tree/journey.php --allow-root
 *
 *  The ordering is tested through the vergeml_journey_stages filter with made
 *  up stages, so every combination can be reached without touching the real
 *  library -- the alternative is deleting somebody's index rows to see what a
 *  blocked stage looks like. The live library is then checked separately, and
 *  lightly, for the things only reality can answer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_journey' ) ) {
    echo "core/journey.php is not loaded -- plugin inactive, or safe mode?\n";
    exit( 1 );
}

$GLOBALS['jn_pass'] = 0;
$GLOBALS['jn_fail'] = 0;
$GLOBALS['jn_log']  = '';

function jn_say( $line ) {
    $GLOBALS['jn_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function jn_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['jn_pass']++;
    } else {
        $GLOBALS['jn_fail']++;
    }
    jn_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}

/** Replaces the real stages with a made-up list for one call. */
function jn_with( $stages ) {
    $GLOBALS['jn_fake'] = $stages;
    add_filter( 'vergeml_journey_stages', 'jn_fake_stages', 99 );
    $out = vergeml_journey();
    remove_filter( 'vergeml_journey_stages', 'jn_fake_stages', 99 );
    return $out;
}

function jn_fake_stages() {
    return $GLOBALS['jn_fake'];
}

function jn_states( $journey ) {
    $out = array();
    foreach ( $journey as $s ) {
        $out[] = $s['state'];
    }
    return $out;
}

function jn_nows( $journey ) {
    $n = 0;
    foreach ( $journey as $s ) {
        if ( 'now' === $s['state'] ) {
            $n++;
        }
    }
    return $n;
}

function jn_stage( $id, $done = false, $blocked = '', $aside = false ) {
    return array(
        'id'      => $id,
        'title'   => $id,
        'text'    => 'x',
        'done'    => $done,
        'blocked' => $blocked,
        'aside'   => $aside,
    );
}


jn_say( "\nwhere you are, and what to do next\n\n" );


/* ------------------------------------------------------------ the ordering */

jn_say( "A  exactly one stage says 'do this next'\n" );

$jn_cases = array(
    'nothing done'          => array( jn_stage( 'a' ), jn_stage( 'b' ), jn_stage( 'c' ) ),
    'first done'            => array( jn_stage( 'a', true ), jn_stage( 'b' ), jn_stage( 'c' ) ),
    'all but last done'     => array( jn_stage( 'a', true ), jn_stage( 'b', true ), jn_stage( 'c' ) ),
    'a blocked one between' => array( jn_stage( 'a', true ), jn_stage( 'b', false, 'do a first' ), jn_stage( 'c' ) ),
    'first is blocked'      => array( jn_stage( 'a', false, 'not yet' ), jn_stage( 'b' ), jn_stage( 'c' ) ),
    'everything blocked'    => array( jn_stage( 'a', false, 'no' ), jn_stage( 'b', false, 'no' ) ),
    'one stage only'        => array( jn_stage( 'a' ) ),
);

foreach ( $jn_cases as $jn_label => $jn_stages ) {

    $jn_j = jn_with( $jn_stages );
    $jn_n = jn_nows( $jn_j );

    // Everything blocked is the one case with no 'now', and that is correct:
    // there is genuinely nothing to do next.
    $jn_want = 'everything blocked' === $jn_label ? 0 : 1;

    jn_check(
        $jn_label,
        $jn_want === $jn_n,
        $jn_n . ' marked now (' . implode( ',', jn_states( $jn_j ) ) . ')'
    );
}

jn_check(
    'all done means nothing is next',
    0 === jn_nows( jn_with( array( jn_stage( 'a', true ), jn_stage( 'b', true ) ) ) )
);


/* -------------------------------------------------------------- the states */

jn_say( "\nB  what each row is told to say\n" );

$jn_j = jn_with( array(
    jn_stage( 'a', true ),
    jn_stage( 'b', false, 'do a first' ),
    jn_stage( 'c' ),
    jn_stage( 'd' ),
) );

jn_check( 'a finished stage reads done', 'done' === $jn_j[0]['state'] );
jn_check( 'a stage that cannot run reads blocked', 'blocked' === $jn_j[1]['state'] );
jn_check( 'and it keeps the reason it cannot run', 'do a first' === $jn_j[1]['blocked'] );
jn_check( 'the first runnable stage is the one to do', 'now' === $jn_j[2]['state'] );
jn_check( 'everything after it waits', 'later' === $jn_j[3]['state'] );

/*
 *  A blocked stage must not be silently skipped past. The screen's whole
 *  argument is that a person is told WHY, so a blocked row with an empty
 *  reason would be the old disabled button with extra steps.
 */
$jn_blocked_with_no_reason = jn_with( array( jn_stage( 'a', false, '' ) ) );
jn_check(
    'a stage with no reason is runnable, not blocked',
    'now' === $jn_blocked_with_no_reason[0]['state'],
    'blocked is decided by having a reason, so a reason can never be missing'
);


/* --------------------------------------------------------------- the aside */

jn_say( "\nC  a side door is not a step\n" );

$jn_j = jn_with( array( jn_stage( 'a' ), jn_stage( 'side', false, '', true ), jn_stage( 'b' ) ) );

jn_check( 'an aside is never the next thing', 'aside' === $jn_j[1]['state'] );
jn_check( 'and it does not consume the one "now"', 'now' === $jn_j[0]['state'] && 'later' === $jn_j[2]['state'], implode( ',', jn_states( $jn_j ) ) );

$jn_only_aside = jn_with( array( jn_stage( 'side', false, '', true ) ) );
jn_check( 'a list of nothing but asides has no next step', 0 === jn_nows( $jn_only_aside ) );


/* ---------------------------------------------------- the real library */

jn_say( "\nD  against this library as it actually is\n" );

$jn_real = vergeml_journey();

jn_check( 'the journey has stages', count( $jn_real ) > 0, count( $jn_real ) . ' stages' );
jn_check( 'at most one of them is next', jn_nows( $jn_real ) <= 1, jn_nows( $jn_real ) . ' marked now' );

$jn_missing = 0;
$jn_unworded = 0;

foreach ( $jn_real as $jn_s ) {

    if ( '' === trim( $jn_s['title'] ) || '' === trim( $jn_s['text'] ) ) {
        $jn_missing++;
    }

    if ( '' === vergeml_journey_state_word( $jn_s['state'] ) ) {
        $jn_unworded++;
    }
}

jn_check( 'every stage has a title and a sentence', 0 === $jn_missing, $jn_missing . ' without' );
jn_check( 'every state has a word for it', 0 === $jn_unworded, $jn_unworded . ' without' );

/*
 *  The budget. This screen reads the state of five features, and a dashboard
 *  that is the slowest page in the plugin is a dashboard nobody opens.
 */
$jn_before = $GLOBALS['wpdb']->num_queries;
vergeml_journey_facts();
$jn_cached = $GLOBALS['wpdb']->num_queries - $jn_before;

jn_check( 'the facts are gathered once per request, not per stage', 0 === $jn_cached, $jn_cached . ' queries on a second call' );


/* ------------------------------------------------------- the cost is stated */

jn_say( "\nE  what a run will cost, before it runs\n" );

$jn_facts = vergeml_journey_facts();
$jn_describe = null;

foreach ( $jn_real as $jn_s ) {
    if ( 'describe' === $jn_s['id'] ) {
        $jn_describe = $jn_s;
    }
}

if ( 0 === $jn_facts['files'] ) {

    /*
     *  An empty library has one stage and no cost to state. Asserted rather
     *  than skipped, because "nothing here" is a state somebody sees on their
     *  first day and it was wrong until 30-08-2026 -- an empty library scored
     *  85 out of 100.
     */
    jn_check( 'an empty library offers exactly one thing to do', 1 === count( $jn_real ), count( $jn_real ) . ' stages' );
    jn_check( 'and that thing is uploading files', 'upload' === $jn_real[0]['id'], $jn_real[0]['id'] );
    jn_check( 'and it has no progress rows', array() === vergeml_journey_progress(), 'a library with no files cannot be 85/100' );

} elseif ( null === $jn_describe ) {
    jn_check( 'the describing stage exists', false, 'core/ai.php not loaded?' );
} elseif ( $jn_facts['undescribed'] > 0 ) {
    jn_check(
        'the number of credits is named before anything is spent',
        false !== strpos( $jn_describe['text'], number_format_i18n( $jn_facts['undescribed'] ) ),
        $jn_describe['text']
    );
} else {
    jn_check(
        'with nothing left to describe it says so rather than offering a run',
        false !== strpos( $jn_describe['text'], number_format_i18n( $jn_facts['described'] ) ),
        $jn_describe['text']
    );
}

/* ---------------------------------------------------- four rows, no total */

jn_say( "\nF  four progress rows, each its own count, no total\n" );

/** Overrides the dashboard's figures for one call. */
function jn_with_facts( $over, $fn ) {
    $GLOBALS['jn_facts_over'] = $over;
    add_filter( 'vergeml_journey_facts', 'jn_fake_facts', 99 );
    $out = call_user_func( $fn );
    remove_filter( 'vergeml_journey_facts', 'jn_fake_facts', 99 );
    return $out;
}

function jn_fake_facts( $f ) {
    return array_merge( $f, $GLOBALS['jn_facts_over'] );
}

/** The dashboard, rendered as an administrator, and the user put back. */
function jn_render() {
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    $was    = get_current_user_id();
    if ( ! empty( $admins ) ) {
        wp_set_current_user( (int) $admins[0] );
    }
    ob_start();
    vergeml_journey_screen();
    $html = ob_get_clean();
    wp_set_current_user( $was );
    return $html;
}

/** The text between two markers, the second looked for after the first. */
function jn_between( $html, $from, $to ) {
    $a = strpos( $html, $from );
    if ( false === $a ) {
        return '';
    }
    $b = strpos( $html, $to, $a + strlen( $from ) );
    return false === $b ? substr( $html, $a ) : substr( $html, $a, $b - $a );
}

function jn_ids( $rows ) {
    $out = array();
    foreach ( $rows as $r ) {
        $out[] = $r['id'];
    }
    return $out;
}

function jn_row( $rows, $id ) {
    foreach ( $rows as $r ) {
        if ( $id === $r['id'] ) {
            return $r;
        }
    }
    return null;
}

jn_check( 'the score is gone', ! function_exists( 'vergeml_journey_score' ) );

$jn_rows = vergeml_journey_progress();

if ( 0 === $jn_facts['files'] ) {

    jn_check( 'an empty library has no rows to fill', array() === $jn_rows );

} else {

    jn_check( 'four rows', 4 === count( $jn_rows ), count( $jn_rows ) . ' rows' );

    $jn_labels = array();
    foreach ( $jn_rows as $jn_r ) {
        $jn_labels[] = $jn_r['label'];
    }
    jn_check(
        'in this order: Alt text, Described, Filed, Checked for copies',
        array( 'Alt text', 'Described', 'Filed', 'Checked for copies' ) === $jn_labels,
        implode( ', ', $jn_labels )
    );

    $jn_over = 0;
    $jn_keys = array();
    foreach ( $jn_rows as $jn_r ) {
        if ( $jn_r['n'] > $jn_r['m'] ) {
            $jn_over++;
        }
        foreach ( array( 'score', 'points', 'weight', 'share', 'total', 'pct' ) as $jn_k ) {
            if ( array_key_exists( $jn_k, $jn_r ) ) {
                $jn_keys[] = $jn_k;
            }
        }
    }
    jn_check( 'no row counts past its total', 0 === $jn_over );
    jn_check( 'no row carries points, a weight or a share', empty( $jn_keys ), implode( ',', $jn_keys ) );

    // Forced through the facts: every picture has alt text.
    $jn_full = jn_with_facts( array( 'no_alt' => 0 ), 'vergeml_journey_progress' );
    jn_check(
        'a row at M of M has no action',
        $jn_full[0]['n'] === $jn_full[0]['m'] && '' === $jn_full[0]['action'] && '' === $jn_full[0]['url'],
        $jn_full[0]['n'] . ' of ' . $jn_full[0]['m'] . ' -> "' . $jn_full[0]['action'] . '"'
    );

    if ( $jn_facts['images'] > 5 ) {
        $jn_half = jn_with_facts( array( 'no_alt' => 5 ), 'vergeml_journey_progress' );
        jn_check(
            'a row short of M keeps its one action',
            $jn_half[0]['n'] < $jn_half[0]['m'] && 'Write alt text' === $jn_half[0]['action'] && '' !== $jn_half[0]['url'],
            $jn_half[0]['n'] . ' of ' . $jn_half[0]['m'] . ' -> "' . $jn_half[0]['action'] . '"'
        );
    }

    /*
     *  What is drawn. The block is read from its own markup up to the next
     *  rail block, so a total printed anywhere in it -- a sum, a percentage,
     *  "/100" -- is a digit the four counts do not account for.
     */
    $jn_html  = jn_render();
    $jn_block = jn_between( $jn_html, 'class="vgml-rail-block vgml-progress"', 'Quick actions' );

    jn_check( 'the rail draws the four rows', 4 === preg_match_all( '/<li class="vgml-progress-row/', $jn_block ), preg_match_all( '/<li class="vgml-progress-row/', $jn_block ) . ' drawn' );
    jn_check( 'nothing of the score is drawn', false === strpos( $jn_html, 'vgml-score' ) && false === strpos( $jn_html, '/100' ) );
    jn_check( 'each row prints "N of M"', 4 === preg_match_all( '/<span class="vgml-progress-count">\d[\d,.]* of \d[\d,.]*<\/span>/', $jn_block ) );
    jn_check( 'each row has a bar', 4 === substr_count( $jn_block, 'class="vgml-import-fill"' ) );

    $jn_text = wp_strip_all_tags( $jn_block );
    jn_check( 'exactly four counts in the block, so a total in the same shape cannot hide', 4 === preg_match_all( '/\d[\d,.]*\s+of\s+\d[\d,.]*/', $jn_text ), preg_match_all( '/\d[\d,.]*\s+of\s+\d[\d,.]*/', $jn_text ) . ' counts' );
    $jn_text = preg_replace( '/\d[\d,.]*\s+of\s+\d[\d,.]*/', '', $jn_text );
    jn_check(
        'no number beyond the four counts: no total, no percentage, no sentence',
        ! preg_match( '/\d/', $jn_text ),
        trim( preg_replace( '/\s+/', ' ', $jn_text ) )
    );

    $jn_full_html = jn_with_facts( array( 'no_alt' => 0 ), 'jn_render' );
    $jn_alt_row   = jn_between( $jn_full_html, 'data-progress="alt"', '</li>' );
    jn_check( 'a finished row is drawn without an action link', '' !== $jn_alt_row && false === strpos( $jn_alt_row, '<a ' ) );
}

$jn_before = $GLOBALS['wpdb']->num_queries;
vergeml_journey_progress();
$jn_cost = $GLOBALS['wpdb']->num_queries - $jn_before;
jn_check( 'the rows cost no query of their own', 0 === $jn_cost, $jn_cost . ' queries' );


/* --------------------------------- rows only when there is something to do */

jn_say( "\nG  to-do rows only when there is something to do\n" );

$jn_canon = array( 'describe', 'alt', 'names', 'folders', 'copies' );

$jn_todo = vergeml_journey_todo();
$jn_zero = 0;
foreach ( $jn_todo as $jn_r ) {
    if ( (int) $jn_r['n'] <= 0 ) {
        $jn_zero++;
    }
}
jn_check( 'no row with a count of zero', 0 === $jn_zero, implode( ',', jn_ids( $jn_todo ) ) . ' (' . $jn_zero . ' at zero)' );
jn_check( 'the order of rows is unchanged', array_values( array_intersect( $jn_canon, jn_ids( $jn_todo ) ) ) === jn_ids( $jn_todo ), implode( ',', jn_ids( $jn_todo ) ) );

$jn_t = jn_with_facts( array( 'no_alt' => 0 ), 'vergeml_journey_todo' );
jn_check( 'with alt text complete, no alt-text row', null === jn_row( $jn_t, 'alt' ), implode( ',', jn_ids( $jn_t ) ) );

$jn_t = jn_with_facts( array( 'licensed' => false, 'demo' => false, 'undescribed' => 5 ), 'vergeml_journey_todo' );
$jn_d = array();
foreach ( $jn_t as $jn_r ) {
    if ( 'describe' === $jn_r['id'] ) {
        $jn_d[] = $jn_r;
    }
}
jn_check( 'with no key and demo off, the describe row is present once', 1 === count( $jn_d ), count( $jn_d ) . ' describe rows' );
jn_check(
    'and its line names the blocker',
    ! empty( $jn_d ) && 'Add a licence key or switch on demo mode first.' === $jn_d[0]['blocked'],
    empty( $jn_d ) ? 'no row' : $jn_d[0]['blocked']
);
jn_check( 'it is still the first row', ! empty( $jn_t ) && 'describe' === $jn_t[0]['id'] );

$jn_t = jn_with_facts( array( 'licensed' => true, 'demo' => false, 'undescribed' => 5 ), 'vergeml_journey_todo' );
jn_check( 'a key unblocks it', null !== jn_row( $jn_t, 'describe' ) && '' === jn_row( $jn_t, 'describe' )['blocked'] );

$jn_t = jn_with_facts( array( 'licensed' => false, 'demo' => true, 'undescribed' => 5 ), 'vergeml_journey_todo' );
jn_check( 'so does demo mode', null !== jn_row( $jn_t, 'describe' ) && '' === jn_row( $jn_t, 'describe' )['blocked'] );

$jn_t = jn_with_facts( array( 'licensed' => false, 'demo' => false, 'undescribed' => 0 ), 'vergeml_journey_todo' );
jn_check( 'nothing to describe, no describe row -- blocked or not', null === jn_row( $jn_t, 'describe' ) );

// Drawn: no count, no button, the blocker as its line.
$jn_html = jn_with_facts( array( 'licensed' => false, 'demo' => false, 'undescribed' => 5 ), 'jn_render' );
$jn_desc = jn_between( $jn_html, 'data-todo="describe"', 'data-todo="' );
if ( '' === $jn_desc ) {
    $jn_desc = jn_between( $jn_html, 'data-todo="describe"', '</div>\n\n        </div>' );
}
jn_check( 'the blocked row is drawn once', 1 === substr_count( $jn_html, 'data-todo="describe"' ) );
jn_check( 'without a count', false !== strpos( $jn_desc, '<div class="vgml-do-n"></div>' ) );
jn_check( 'without a button', false === strpos( $jn_desc, '<a ' ) );
jn_check( 'with the blocker as its line', false !== strpos( $jn_desc, 'Add a licence key or switch on demo mode first.' ) );

$jn_html = jn_with_facts( array( 'no_alt' => 0, 'undescribed' => 0, 'unfiled' => 0 ), 'jn_render' );
if ( null === jn_row( jn_with_facts( array( 'no_alt' => 0, 'undescribed' => 0, 'unfiled' => 0 ), 'vergeml_journey_todo' ), 'copies' ) ) {
    jn_check( 'with nothing to do, the section is not drawn', false === strpos( $jn_html, 'What to do next' ) && false === strpos( $jn_html, 'class="vgml-do ' ) );
} else {
    jn_check( 'rows are drawn only for what is left', false === strpos( $jn_html, 'data-todo="folders"' ) && false === strpos( $jn_html, 'data-todo="alt"' ) );
}


/* -------------------------------------------------------- files, not folders */

jn_say( "\nH  files, not folders\n" );

$jn_t  = jn_with_facts( array( 'unfiled' => 268 ), 'vergeml_journey_todo' );
$jn_fo = jn_row( $jn_t, 'folders' );

jn_check( 'with 268 unfiled, the folders row shows 268', null !== $jn_fo && 268 === (int) $jn_fo['n'], null === $jn_fo ? 'no row' : $jn_fo['n'] );
jn_check( 'its title is "268 files in no folder"', null !== $jn_fo && '268 files in no folder' === $jn_fo['title'], null === $jn_fo ? 'no row' : $jn_fo['title'] );
jn_check( 'its action is "Put them in folders"', null !== $jn_fo && 'Put them in folders' === $jn_fo['go'], null === $jn_fo ? 'no row' : $jn_fo['go'] );
jn_check( 'the action does not mention folders as the thing to work out', null !== $jn_fo && false === stripos( $jn_fo['go'], 'work out' ) );

$jn_one = jn_row( jn_with_facts( array( 'unfiled' => 1 ), 'vergeml_journey_todo' ), 'folders' );
jn_check( 'one file, singular', null !== $jn_one && '1 file in no folder' === $jn_one['title'], null === $jn_one ? 'no row' : $jn_one['title'] );
jn_check( 'no unfiled files, no row', null === jn_row( jn_with_facts( array( 'unfiled' => 0 ), 'vergeml_journey_todo' ), 'folders' ) );

$jn_html = jn_with_facts( array( 'unfiled' => 268 ), 'jn_render' );
$jn_fold = jn_between( $jn_html, 'data-todo="folders"', 'data-todo="' );
if ( '' === $jn_fold ) {
    $jn_fold = jn_between( $jn_html, 'data-todo="folders"', 'vgml-seen' );
}
jn_check( '"Work out the folders" is nowhere on the screen', false === strpos( $jn_html, 'Work out the folders' ) );
jn_check( 'the number is in the title, not printed twice', false !== strpos( $jn_fold, '<div class="vgml-do-n"></div>' ) && false !== strpos( $jn_fold, '268 files in no folder' ) );
jn_check( 'and the button says what happens to the files', false !== strpos( $jn_fold, '>Put them in folders</a>' ) );


jn_say( sprintf( "\n%d/%d passed\n", $GLOBALS['jn_pass'], $GLOBALS['jn_pass'] + $GLOBALS['jn_fail'] ) );

@file_put_contents( __DIR__ . '/journey-last-run.txt', $GLOBALS['jn_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['jn_fail'] > 0 ) {
    exit( 1 );
}
