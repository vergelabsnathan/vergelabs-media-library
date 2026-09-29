<?php
/*
 *  The library summary the planner reads, as JSON (2026-09-26).
 *
 *      node tools/box-eval.mjs tools/box-guide-summary.php --site shop > summary-shop.json
 *
 *  For tools/tree-lab.mjs: today's planner is replayed offline on exactly
 *  what the Folders screen sends it (vergeml_guide_summary, core/guide.php).
 *  Read-only: nothing moves, nothing is spent.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_set_current_user( 1 );
echo wp_json_encode( vergeml_guide_summary() );
