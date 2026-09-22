<?php
/**
 *  Live integration check, run on the box with the plugin under test active:
 *
 *      wp eval-file tests/integrations/live.php <yoast|rankmath|seopress|aioseo|woo|acf> [describe]
 *
 *  Each SEO plugin is checked the same way: a keyphrase is written the way that
 *  plugin writes it, and the page context, the describe request body, the
 *  filename lead and the page-gap query must all see it. Nothing here is
 *  mocked -- the plugin is the real one, the table is its real table.
 *
 *  Fixtures: probe page 104131 and attachment 1779 (Gothic Cathedral Interior),
 *  whose parent is pointed at the page for the run and restored after. The
 *  "describe" flag spends one real credit through attachment 1775.
 *
 *  All six on the box, one plugin active at a time (they are installed there,
 *  inactive; see the hetzner notes):
 *
 *      scp tests/integrations/live.php root@46.225.66.194:/tmp/vgml-live.php
 *      ssh root@46.225.66.194 'cd /var/www/wp && W="sudo -u www-data wp --allow-root"; \
 *        run(){ $W plugin activate $1; $W eval-file /tmp/vgml-live.php $2 $3; $W plugin deactivate $1; }; \
 *        run wordpress-seo yoast describe; run seo-by-rank-math rankmath; run wp-seopress seopress; \
 *        run all-in-one-seo-pack aioseo; run woocommerce woo; run advanced-custom-fields acf'
 */

$GLOBALS['vgml_fail'] = 0;
$GLOBALS['vgml_n']    = 0;
$GLOBALS['vgml_skip'] = array();

function vgml_check( $name, $ok, $detail = '' ) {
    $GLOBALS['vgml_n']++;
    if ( ! $ok ) {
        $GLOBALS['vgml_fail']++;
    }
    echo ( $ok ? '  ok   ' : '  FAIL ' ) . $name . ( '' !== $detail ? '  -- ' . $detail : '' ) . "\n";
}

/** A check this site cannot run: said, counted apart, never a pass and never a fail. */
function vgml_skip( $name, $why ) {
    $GLOBALS['vgml_skip'][] = $why;
    echo '  skip ' . $name . '  -- ' . $why . "\n";
}

$which    = isset( $args[0] ) ? $args[0] : '';
$describe = isset( $args[1] ) && 'describe' === $args[1];
$page     = 104131;
$att      = 1779;

global $wpdb;

$keep_parent = (int) get_post_field( 'post_parent', $att );
wp_update_post( array( 'ID' => $att, 'post_parent' => $page ) );
clean_post_cache( $att );

$seo_meta = array(
    '_yoast_wpseo_focuskw', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskeywords',
    'rank_math_focus_keyword', 'rank_math_description',
    '_seopress_analysis_target_kw', '_seopress_titles_desc',
);
foreach ( $seo_meta as $k ) {
    delete_post_meta( $page, $k );
}

echo "\n[" . $which . "]\n";

switch ( $which ) {

    case 'yoast':
        vgml_check( 'Yoast is the plugin that is loaded', defined( 'WPSEO_VERSION' ), defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : 'not loaded' );
        update_post_meta( $page, '_yoast_wpseo_focuskw', 'gothic cathedral' );
        update_post_meta( $page, '_yoast_wpseo_metadesc', 'A walk through the nave of a gothic cathedral.' );
        update_post_meta( $page, '_yoast_wpseo_focuskeywords', wp_json_encode( array( array( 'keyword' => 'ribbed vaults', 'score' => 70 ), array( 'keyword' => 'pointed arches', 'score' => 60 ) ) ) );
        $expect_related = 'ribbed vaults, pointed arches';
        break;

    case 'rankmath':
        vgml_check( 'Rank Math is the plugin that is loaded', defined( 'RANK_MATH_VERSION' ), defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : 'not loaded' );
        update_post_meta( $page, 'rank_math_focus_keyword', 'gothic cathedral,ribbed vaults' );
        update_post_meta( $page, 'rank_math_description', 'A walk through the nave of a gothic cathedral.' );
        $expect_related = 'ribbed vaults';
        break;

    case 'seopress':
        vgml_check( 'SEOPress is the plugin that is loaded', defined( 'SEOPRESS_VERSION' ), defined( 'SEOPRESS_VERSION' ) ? SEOPRESS_VERSION : 'not loaded' );
        update_post_meta( $page, '_seopress_analysis_target_kw', 'gothic cathedral,pointed arches' );
        update_post_meta( $page, '_seopress_titles_desc', 'A walk through the nave of a gothic cathedral.' );
        $expect_related = 'pointed arches';
        break;

    case 'aioseo':
        vgml_check( 'AIOSEO is the plugin that is loaded', defined( 'AIOSEO_VERSION' ), defined( 'AIOSEO_VERSION' ) ? AIOSEO_VERSION : 'not loaded' );
        $table = $wpdb->prefix . 'aioseo_posts';
        vgml_check( 'its posts table exists', $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
        $wpdb->delete( $table, array( 'post_id' => $page ) );
        $wpdb->insert( $table, array(
            'post_id'     => $page,
            'keyphrases'  => wp_json_encode( array( 'focus' => array( 'keyphrase' => 'gothic cathedral', 'score' => 80 ), 'additional' => array() ) ),
            'description' => 'A walk through the nave of a gothic cathedral.',
            'created'     => current_time( 'mysql' ),
            'updated'     => current_time( 'mysql' ),
        ) );
        $expect_related = '';
        break;

    case 'woo':
        vgml_check( 'WooCommerce is the plugin that is loaded', class_exists( 'WooCommerce' ) );
        /*
         *  Its own pictures (S16): the library's 1778, 1775 and 1774 went
         *  with the seed, so the case makes three attachments with index
         *  rows -- a featured image, a gallery image, and a copy for the
         *  repoint -- and deletes them at the end. vergeml_index_set is
         *  what the describer writes; the vector is a fixture's.
         */
        $mk = function ( $title, $object ) {
            $id = wp_insert_post( array( 'post_title' => 'VGML check ' . $title, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/png' ) );
            vergeml_index_set( (int) $id, array( 'caption' => 'seeded', 'kind' => 'photo', 'filing' => wp_json_encode( array( 'object' => $object, 'audience' => '' ) ), 'embedding' => array( 1.0, 0.0, 0.0, 0.0 ), 'model' => 'zz-test', 'model_version' => 'zz-model-7', 'prompt_hash' => 'zzhash0123456789', 'error' => '', 'described_at' => gmdate( 'Y-m-d H:i:s' ) ) );
            return (int) $id;
        };
        $feat = $mk( 'featured', 'vgmlcheckdeck; zzthing' );
        $gal  = $mk( 'gallery', 'vgmlcheckdeck; zzthing' );
        $copy = $mk( 'copy', 'vgmlcheckdeck; zzthing' );
        $on_before = (int) vergeml_folders_on_products( true );

        $product = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'VGML check deck' ) );
        $cat     = wp_insert_term( 'Skateboard decks', 'product_cat' );
        $cat_id  = is_wp_error( $cat ) ? (int) $cat->get_error_data( 'term_exists' ) : (int) $cat['term_id'];
        wp_set_object_terms( $product, array( $cat_id ), 'product_cat' );
        update_post_meta( $product, '_thumbnail_id', (string) $feat );
        update_post_meta( $product, '_product_image_gallery', $feat . ',' . $gal );
        wp_update_post( array( 'ID' => $feat, 'post_parent' => $product ) );
        clean_post_cache( $feat );

        /*
         *  The "on products" pill counts pictures, not picture-product pairs
         *  (S19, the real shop: 36 on products beside 33 pictures -- the
         *  featured image of one product sat in another's gallery, and
         *  WooCommerce's own importer puts a product's featured image in its
         *  gallery too). Two pictures here, one of them featured and in the
         *  gallery: two more, not three. Mutation: the count as the sum of
         *  featured and gallery entries -> red.
         */
        // Fresh: the count is kept for the request, and this one changed products.
        $on_after = (int) vergeml_folders_on_products( true );
        vgml_check( 'the on-products count grows by the two pictures, not by the three places they sit', 2 === $on_after - $on_before, "$on_before -> $on_after" );

        $ctx = vergeml_ai_context( $feat );
        vgml_check( 'the product title reaches the describe context', isset( $ctx['post_title'] ) && 'VGML check deck' === $ctx['post_title'] );
        vgml_check( 'the product categories reach the describe context', isset( $ctx['product_categories'] ) && false !== strpos( $ctx['product_categories'], 'Skateboard decks' ), isset( $ctx['product_categories'] ) ? $ctx['product_categories'] : '(none)' );

        /*
         *  File by the product (S10.8): a folder named like the category,
         *  the featured image and the gallery image, the rows read as the
         *  fill reads them, and the fill's own count -- dry, nothing moves
         *  on this library -- puts both in that folder by the product, sure;
         *  a second count says the same. The folder goes at the end.
         *  Mutation: the product path removed (vergeml_filing_product_folders
         *  returning its rows unchanged) -> both fall to the matcher, red.
         */
        $tax    = vergeml_librarian_taxonomy();
        $folder = wp_insert_term( 'Skateboard decks', $tax );
        $folder = is_wp_error( $folder ) ? (int) $folder->get_error_data( 'term_exists' ) : (int) $folder['term_id'];
        $ids    = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ) );
        $ids    = is_wp_error( $ids ) ? array( $folder ) : array_map( 'intval', $ids );
        sort( $ids );
        $profiles = vergeml_filing_profiles( $ids, $tax );
        $words    = vergeml_filing_words_sql( 'i' );
        $psql     = vergeml_filing_product_sql( 'i' );
        $rows     = (array) $wpdb->get_results( $wpdb->prepare( "SELECT i.attachment_id, i.embedding, i.kind, i.filing, {$words['select']}, {$psql['select']} FROM {$wpdb->vergeml_ai_index} i {$words['join']} {$psql['join']} WHERE i.attachment_id IN (%d, %d, %d) AND i.error = '' AND i.embedding IS NOT NULL ORDER BY i.attachment_id", $feat, $gal, $copy ), ARRAY_A );
        $by_id    = array();
        foreach ( $rows as $r ) {
            $by_id[ (int) $r['attachment_id'] ] = (int) $r['product_id'];
        }
        vgml_check( 'the rows carry the product: the featured (parent too) and the gallery image say it, the copy says none', 3 === count( $rows ) && $by_id[ $feat ] === (int) $product && $by_id[ $gal ] === (int) $product && 0 === $by_id[ $copy ], wp_json_encode( $by_id ) );
        /*
         *  A folder takes part in filing only with a profile, and a profile
         *  needs the folder name's vector from the service. The watch's stage
         *  is a staging clone the licence is not activated on (the service
         *  answers 403 site_not_activated, 2026-09-22), so a folder made here
         *  has none: the two checks below cannot run there and are said to be
         *  skipped, not failed. They ran red on the stage for WooCommerce
         *  11.0.1 as much as 11.1.1; on the production-typed tech site they
         *  pass.
         */
        if ( ! isset( $profiles[ $folder ] ) ) {
            $why = 'this site cannot make a folder vector (' . wp_get_environment_type() . ', ' . ( is_array( vergeml_meaning_vector( 'Folder: Skateboard decks' ) ) ? 'vector made now -- look again' : 'no vector from the service' ) . ')';
            vgml_skip( 'the count puts both in Skateboard decks by the product', $why );
            vgml_skip( 'a second count says the same', $why );
        } else {
            $rows  = vergeml_filing_product_folders( $rows, $profiles );
            $first = vergeml_filing_count( $profiles, $rows )['picks'];
            vgml_check( 'the count puts both in Skateboard decks by the product, sure; the copy, saying the same word, is nobody\'s and lands nowhere', isset( $first[ $feat ], $first[ $gal ], $first[ $copy ] ) && $folder === (int) $first[ $feat ]['term_id'] && $folder === (int) $first[ $gal ]['term_id'] && 'product' === $first[ $feat ]['why'] && 'product' === $first[ $gal ]['why'] && 'sure' === $first[ $feat ]['confidence'] && 0 === (int) $first[ $copy ]['term_id'], wp_json_encode( array( isset( $first[ $feat ] ) ? array( $first[ $feat ]['term_id'], $first[ $feat ]['why'] ) : null, isset( $first[ $gal ] ) ? array( $first[ $gal ]['term_id'], $first[ $gal ]['why'] ) : null, isset( $first[ $copy ] ) ? array( $first[ $copy ]['term_id'], $first[ $copy ]['why'] ) : null ) ) );
            $again = vergeml_filing_count( $profiles, $rows )['picks'];
            vgml_check( 'a second count says the same', isset( $again[ $feat ], $again[ $gal ] ) && $again[ $feat ]['term_id'] === $first[ $feat ]['term_id'] && $again[ $gal ]['term_id'] === $first[ $gal ]['term_id'] && $again[ $feat ]['why'] === $first[ $feat ]['why'] );
        }
        wp_delete_term( $folder, $tax );
        vgml_check( 'the folder is gone again', null === get_term( $folder, $tax ) );

        $changed = vergeml_health_repoint( $gal, $copy );
        $gallery = get_post_meta( $product, '_product_image_gallery', true );
        vgml_check( 'deleting a duplicate repoints the product gallery', $feat . ',' . $copy === $gallery, $gallery . ' / ' . wp_json_encode( $changed ) );

        wp_delete_post( $product, true );
        wp_delete_term( $cat_id, 'product_cat' );
        foreach ( array( $feat, $gal, $copy ) as $id ) {
            $wpdb->delete( $wpdb->vergeml_ai_index, array( 'attachment_id' => $id ), array( '%d' ) );
            wp_delete_post( $id, true );
        }
        $cat_left = get_term( $cat_id, 'product_cat' );
        vgml_check( 'the pictures, the product and the category are gone', ! get_post( $feat ) && ! get_post( $gal ) && ! get_post( $copy ) && ! get_post( $product ) && ( null === $cat_left || is_wp_error( $cat_left ) ) );
        break;

    case 'acf':
        vgml_check( 'ACF is the plugin that is loaded', class_exists( 'ACF' ) );
        // A bare-id image field, the way ACF stores one.
        update_post_meta( $page, 'vgml_hero', '1775' );
        update_post_meta( $page, '_vgml_hero', 'field_vgmlhero' );
        // And a URL-return field, the other way ACF stores one.
        update_post_meta( $page, 'vgml_hero_url', wp_get_attachment_url( 1775 ) );
        $changed = vergeml_health_repoint( 1775, 1774 );
        $bare    = get_post_meta( $page, 'vgml_hero', true );
        $url     = get_post_meta( $page, 'vgml_hero_url', true );
        vgml_check( 'a URL-return ACF field follows the surviving copy', $url === wp_get_attachment_url( 1774 ), $url );
        echo '  note ' . ( '1774' === $bare ? 'a bare-id ACF field was repointed' : 'a bare-id ACF field is NOT repointed (still ' . $bare . ') -- documented limit in health-delete.php' ) . "\n";
        delete_post_meta( $page, 'vgml_hero' );
        delete_post_meta( $page, '_vgml_hero' );
        delete_post_meta( $page, 'vgml_hero_url' );
        break;

    default:
        echo "unknown target\n";
}

if ( in_array( $which, array( 'yoast', 'rankmath', 'seopress', 'aioseo' ), true ) ) {

    wp_cache_delete( $page, 'post_meta' );

    $pc = vergeml_seo_page_context( $page );
    vgml_check( 'the page context carries the focus keyphrase', isset( $pc['keyphrase'] ) && 'gothic cathedral' === $pc['keyphrase'], wp_json_encode( $pc ) );
    vgml_check( 'and the meta description', isset( $pc['description'] ) && false !== strpos( $pc['description'], 'nave' ) );
    if ( '' !== $expect_related ) {
        vgml_check( 'and the related keyphrases', isset( $pc['related'] ) && $expect_related === $pc['related'], isset( $pc['related'] ) ? $pc['related'] : '(none)' );
    }

    $ctx = vergeml_ai_context( $att );
    vgml_check( 'the attachment context inherits page_keyphrase', isset( $ctx['page_keyphrase'] ) && 'gothic cathedral' === $ctx['page_keyphrase'] );

    $req  = vergeml_ai_describe_request( $att );
    $body = is_array( $req ) && isset( $req['body'] ) ? ( is_string( $req['body'] ) ? $req['body'] : wp_json_encode( $req['body'] ) ) : wp_json_encode( $req );
    vgml_check( 'the describe request body says the keyphrase', false !== strpos( $body, 'gothic cathedral' ) );
    vgml_check( 'and does not leak the licence key', false === strpos( $body, 'v1:' ) );

    $lead = vergeml_seo_lead_slug( $att, 'ribbed-vaults', array( 'alt' => 'The ribbed vaults of a gothic cathedral nave', 'caption' => '', 'title' => '', 'tags' => array() ) );
    vgml_check( 'the keyphrase leads the filename when the model said it', 'gothic-cathedral-ribbed-vaults' === $lead, $lead );
    $lead = vergeml_seo_lead_slug( $att, 'ribbed-vaults', array( 'alt' => 'Stone vaults above a nave', 'caption' => '', 'title' => '', 'tags' => array() ) );
    vgml_check( 'and stays out when the model did not', 'ribbed-vaults' === $lead, $lead );

    // The gap is "on a page with a keyphrase, without alt text" -- so the alt
    // comes off for the question and goes back after.
    // The count is cached for a minute for the AI screen; each question here
    // wants the live answer.
    $keep_alt = (string) get_post_meta( $att, '_wp_attachment_image_alt', true );
    delete_post_meta( $att, '_wp_attachment_image_alt' );
    delete_transient( 'vergeml_seo_gap_count' );
    $gap = vergeml_seo_gap_count();
    $ids = array_map( 'intval', vergeml_seo_gap_ids( 500 ) );
    vgml_check( 'the page-gap query finds the image without alt on the keyphrase page', $gap >= 1 && in_array( $att, $ids, true ), 'gap=' . $gap );
    if ( '' !== $keep_alt ) {
        update_post_meta( $att, '_wp_attachment_image_alt', $keep_alt );
    }
    delete_transient( 'vergeml_seo_gap_count' );
    $gap_after = vergeml_seo_gap_count();
    vgml_check( 'and stops counting it once it has alt text', '' === $keep_alt || $gap_after === $gap - 1, 'gap=' . $gap_after );

    if ( $describe ) {
        // The service refuses the same image twice in a row (409), so the
        // real call goes through a second picture parented to the page.
        $second      = 1775;
        $keep_second = (int) get_post_field( 'post_parent', $second );
        wp_update_post( array( 'ID' => $second, 'post_parent' => $page ) );
        clean_post_cache( $second );
        delete_transient( 'vergeml_ai_recent' );
        $r = vergeml_ai_describe( $second );
        vgml_check( 'a real describe with page context succeeds', ! is_wp_error( $r ) && ! empty( $r['caption'] ), is_wp_error( $r ) ? $r->get_error_message() : $r['model'] . ' | ' . $r['caption'] );
        wp_update_post( array( 'ID' => $second, 'post_parent' => $keep_second ) );
    }

    foreach ( $seo_meta as $k ) {
        delete_post_meta( $page, $k );
    }
    if ( 'aioseo' === $which ) {
        $wpdb->delete( $wpdb->prefix . 'aioseo_posts', array( 'post_id' => $page ) );
    }
}

wp_update_post( array( 'ID' => $att, 'post_parent' => $keep_parent ) );

$skipped = $GLOBALS['vgml_skip'] ? ', ' . count( $GLOBALS['vgml_skip'] ) . ' skipped: ' . implode( '; ', array_unique( $GLOBALS['vgml_skip'] ) ) : '';
echo "\n" . ( $GLOBALS['vgml_n'] - $GLOBALS['vgml_fail'] ) . '/' . $GLOBALS['vgml_n'] . " passed{$skipped}\n";
if ( $GLOBALS['vgml_fail'] ) {
    exit( 1 );
}
