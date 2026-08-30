<?php
/**
 *  Where you are, and what to do next.
 *
 *  The claim worth defending is narrow: **exactly one stage ever says "do this
 *  next"**, and a stage that cannot run says which one has to happen first. A
 *  list where three rows are equally urgent is the screen this replaced.
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
    jn_check( 'and it is not scored', null === vergeml_journey_score()['score'], 'a library with no files cannot be 85/100' );

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

jn_say( sprintf( "\n%d/%d passed\n", $GLOBALS['jn_pass'], $GLOBALS['jn_pass'] + $GLOBALS['jn_fail'] ) );

@file_put_contents( __DIR__ . '/journey-last-run.txt', $GLOBALS['jn_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['jn_fail'] > 0 ) {
    exit( 1 );
}
