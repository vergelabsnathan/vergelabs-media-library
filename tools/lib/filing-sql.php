<?php
/**
 *  The SELECT and JOIN fragments the box tools read the index with. Not shipped:
 *  the plugin's own readers write these columns out as literal SQL. Loaded by a
 *  tool with require_once; tools/box-eval.mjs copies this file to the box.
 */

/**
 *  The SELECT and JOIN fragments a reader adds so a row carries the picture's
 *  file, title and alt (S10.9); the index table is aliased i. The alt counts
 *  only when a person wrote it: the describer's own alt (the index keeps it,
 *  and the AI screen counts "alt equals the model's" the same way) is a
 *  sentence naming everything in the scene, and on the tech library, where
 *  every alt is the model's, it doubled the ties (margin 62 -> 162 against
 *  104 from the file and title alone, 2026-09-17).
 */
function vergeml_filing_words_sql() {
    global $wpdb;
    return array(
        'select' => "wp_p.post_title AS title, wp_f.meta_value AS file, CASE WHEN wp_a.meta_value = i.alt THEN '' ELSE wp_a.meta_value END AS alt",
        'join'   => "LEFT JOIN {$wpdb->posts} wp_p ON wp_p.ID = i.attachment_id
           LEFT JOIN {$wpdb->postmeta} wp_f ON wp_f.post_id = i.attachment_id AND wp_f.meta_key = '_wp_attached_file'
           LEFT JOIN {$wpdb->postmeta} wp_a ON wp_a.post_id = i.attachment_id AND wp_a.meta_key = '_wp_attachment_image_alt'",
    );
}

/**
 *  The SELECT and JOIN fragments a reader adds so a row carries the product
 *  the picture belongs to (S10.8): the product it is the featured image of
 *  (_thumbnail_id), the one whose gallery lists it (_product_image_gallery),
 *  or the one it was uploaded to (post_parent) -- the first that is a
 *  product, as product_id, else NULL. Each is one correlated lookup that
 *  narrows on the meta key first, so a picture in no shop costs three index
 *  reads. Without WooCommerce's product type there is nothing to join: the
 *  column is NULL and the join empty.
 */
function vergeml_filing_product_sql() {
    global $wpdb;
    if ( ! function_exists( 'post_type_exists' ) || ! post_type_exists( 'product' ) ) {
        return array( 'select' => 'NULL AS product_id', 'join' => '' );
    }
    return array(
        'select' => "COALESCE(
            ( SELECT vp1.ID FROM {$wpdb->postmeta} vm1 JOIN {$wpdb->posts} vp1 ON vp1.ID = vm1.post_id AND vp1.post_type = 'product'
               WHERE vm1.meta_key = '_thumbnail_id' AND vm1.meta_value = CAST(i.attachment_id AS CHAR) LIMIT 1 ),
            ( SELECT vp2.ID FROM {$wpdb->postmeta} vm2 JOIN {$wpdb->posts} vp2 ON vp2.ID = vm2.post_id AND vp2.post_type = 'product'
               WHERE vm2.meta_key = '_product_image_gallery' AND CONCAT( ',', vm2.meta_value, ',' ) LIKE CONCAT( '%,', i.attachment_id, ',%' ) LIMIT 1 ),
            ( SELECT vp3.ID FROM {$wpdb->posts} va3 JOIN {$wpdb->posts} vp3 ON vp3.ID = va3.post_parent AND vp3.post_type = 'product'
               WHERE va3.ID = i.attachment_id LIMIT 1 )
          ) AS product_id",
        'join'   => '',
    );
}
