<?php
/*
 *  CAP-4 (spec-tree-planner story 6) on a real library, read-only: what
 *  vergeml_plan_facts() actually tells the Tree step about the audience
 *  question, from the site's own described pictures and product categories.
 *
 *      node tools/box-eval.mjs tools/box-audience-check.php --site shop
 *      node tools/box-eval.mjs tools/box-audience-check.php
 *
 *  Outbound HTTP is refused for the whole run, so this can never reach the
 *  AI service -- vergeml_plan_facts() and vergeml_plan_inventory() read the
 *  library's own table and (with WooCommerce) its product_cat terms, both
 *  already on the box, nothing more.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'pre_http_request', function () {
    return new WP_Error( 'blocked', 'box-audience-check makes no outbound call' );
}, PHP_INT_MAX );

$facts = vergeml_plan_facts();
$inv   = vergeml_plan_inventory();

echo wp_json_encode( array(
    'site'              => home_url(),
    'labels'            => $facts['labels'],
    'pictures'          => $inv['pictures'],
    'audience_share'    => vergeml_plan_audience_share( $inv['labels'] ),
    'ask_threshold'     => VERGEML_PLAN_AUDIENCE_ASK,
    'product_evidence'  => $facts['audience_evidence'],
    'audience_ask'      => $facts['audience_ask'],
), JSON_PRETTY_PRINT ) . "\n";
