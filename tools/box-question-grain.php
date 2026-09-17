<?php
/**
 *  The questions' grain, read off a site's last fill (S10.5). Read-only.
 *
 *      node tools/box-eval.mjs tools/box-question-grain.php --site shop
 *
 *  Off the fill state as it stands: the either/or pairs by how many pictures
 *  each holds, the cards the old rule made of them (one a pair) and the cards
 *  the fold makes (one a pair of two or more, one for every pair of one), and
 *  how many pairs name two folders of one leaf. Writes nothing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$state  = get_option( VERGEML_TALK_STATE );
$either = is_array( $state ) && isset( $state['either'] ) ? (array) $state['either'] : array();
$tax    = is_array( $state ) && isset( $state['taxonomy'] ) ? (string) $state['taxonomy'] : '';

$singles  = 0;
$many     = 0;
$sameLeaf = 0;
foreach ( $either as $key => $e ) {
    $n = count( (array) ( isset( $e['ids'] ) ? $e['ids'] : array() ) );
    if ( 1 === $n ) {
        $singles++;
    } elseif ( $n > 1 ) {
        $many++;
    }
    $two   = array_map( 'intval', explode( ':', (string) $key ) );
    $names = array();
    foreach ( $two as $tid ) {
        $t       = get_term( $tid, $tax );
        $names[] = $t instanceof WP_Term ? mb_strtolower( vergeml_term_name( $t ) ) : (string) $tid;
    }
    if ( 2 === count( $names ) && $names[0] === $names[1] ) {
        $sameLeaf++;
    }
}

echo wp_json_encode( array(
    'pairs'        => count( $either ),
    'of_one'       => $singles,
    'of_more'      => $many,
    'cards_before' => count( $either ),
    'cards_after'  => $many + ( $singles > 1 ? 1 : $singles ),
    'same_leaf'    => $sameLeaf,
    'questions'    => is_array( $state ) && isset( $state['questions'] ) ? count( (array) $state['questions'] ) : 0,
) ) . "\n";
