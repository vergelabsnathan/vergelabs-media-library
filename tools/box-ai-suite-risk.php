<?php
/**
 *  Which three pictures the `ai` suite's before-hook un-describes, and whether
 *  any of them is in the tech truth set.
 *
 *      node tools/box-eval.mjs tools/box-ai-suite-risk.php
 *
 *  The hook takes the first three attachments the same get_posts() call gives,
 *  deletes their index rows and their alt, and the suite re-describes them with
 *  mock on. A mock description on a truth-marked picture would move the SCORE
 *  line the card gates on, so the ids are read here before anything runs.
 *
 *  Reads only. Lives in tools/, which is export-ignored.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => 3, 'fields' => 'ids' ) );

foreach ( (array) $ids as $id ) {
    $file = get_post_meta( (int) $id, '_wp_attached_file', true );
    printf( "%d\t%s\n", (int) $id, (string) $file );
}
