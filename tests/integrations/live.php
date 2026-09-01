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

function vgml_check( $name, $ok, $detail = '' ) {
    $GLOBALS['vgml_n']++;
    if ( ! $ok ) {
        $GLOBALS['vgml_fail']++;
    }
    echo ( $ok ? '  ok   ' : '  FAIL ' ) . $name . ( '' !== $detail ? '  -- ' . $detail : '' ) . "\n";
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
        $product = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'VGML check deck' ) );
        $cat     = wp_insert_term( 'Skateboard decks', 'product_cat' );
        $cat_id  = is_wp_error( $cat ) ? (int) $cat->get_error_data( 'term_exists' ) : (int) $cat['term_id'];
        wp_set_object_terms( $product, array( $cat_id ), 'product_cat' );
        update_post_meta( $product, '_product_image_gallery', '1778,1775' );
        wp_update_post( array( 'ID' => 1778, 'post_parent' => $product ) );
        clean_post_cache( 1778 );

        $ctx = vergeml_ai_context( 1778 );
        vgml_check( 'the product title reaches the describe context', isset( $ctx['post_title'] ) && 'VGML check deck' === $ctx['post_title'] );
        vgml_check( 'the product categories reach the describe context', isset( $ctx['product_categories'] ) && false !== strpos( $ctx['product_categories'], 'Skateboard decks' ), isset( $ctx['product_categories'] ) ? $ctx['product_categories'] : '(none)' );

        $changed = vergeml_health_repoint( 1775, 1774 );
        $gallery = get_post_meta( $product, '_product_image_gallery', true );
        vgml_check( 'deleting a duplicate repoints the product gallery', '1778,1774' === $gallery, $gallery . ' / ' . wp_json_encode( $changed ) );

        wp_update_post( array( 'ID' => 1778, 'post_parent' => 0 ) );
        wp_delete_post( $product, true );
        wp_delete_term( $cat_id, 'product_cat' );
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

echo "\n" . ( $GLOBALS['vgml_n'] - $GLOBALS['vgml_fail'] ) . '/' . $GLOBALS['vgml_n'] . " passed\n";
if ( $GLOBALS['vgml_fail'] ) {
    exit( 1 );
}
