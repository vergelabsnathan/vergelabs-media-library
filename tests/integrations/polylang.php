<?php
/**
 *  Polylang, live on the box, with polylang-pro (or polylang) active:
 *
 *      wp plugin activate polylang-pro
 *      wp eval-file tests/integrations/polylang.php
 *      wp plugin deactivate polylang-pro
 *
 *  Creates the languages it needs, translates attachment 1779 the way the
 *  media screen's "+" does, and expects the copy to be in the same folders.
 *  The copy is removed after, with the shared file kept on disk.
 */

$GLOBALS['vergeml_fail'] = 0;
$GLOBALS['vergeml_n']    = 0;

function vergeml_check( $name, $ok, $detail = '' ) {
    $GLOBALS['vergeml_n']++;
    if ( ! $ok ) {
        $GLOBALS['vergeml_fail']++;
    }
    echo ( $ok ? '  ok   ' : '  FAIL ' ) . $name . ( '' !== $detail ? '  -- ' . $detail : '' ) . "\n";
}

$att = 1779;

echo "\n[polylang]\n";
vergeml_check( 'Polylang is the plugin that is loaded', function_exists( 'PLL' ) && defined( 'POLYLANG_VERSION' ), defined( 'POLYLANG_VERSION' ) ? POLYLANG_VERSION : 'not loaded' );

$model = PLL()->model;

foreach ( array( 'en' => array( 'name' => 'English', 'locale' => 'en_US', 'rtl' => 0, 'term_group' => 0, 'flag' => 'us' ), 'nl' => array( 'name' => 'Nederlands', 'locale' => 'nl_NL', 'rtl' => 0, 'term_group' => 1, 'flag' => 'nl' ) ) as $slug => $lang ) {
    if ( ! $model->get_language( $slug ) ) {
        $lang['slug'] = $slug;
        $model->add_language( $lang );
    }
}
$model->clean_languages_cache();
vergeml_check( 'two languages exist', $model->get_language( 'en' ) && $model->get_language( 'nl' ) );

/* --- the folders are not split per language ----------------------------------- */

vergeml_check( 'vergeml_folder is not a translated taxonomy', ! $model->is_translated_taxonomy( 'vergeml_folder' ) );

// With media translation switched on, Polylang registers these two on
// attachments at init. The switch is a settings-screen option; this puts the
// taxonomies where that option would put them, so the tree's answer is real.
register_taxonomy_for_object_type( 'language', 'attachment' );
register_taxonomy_for_object_type( 'post_translations', 'attachment' );
vergeml_check( 'the tree does not draw Polylang\'s language taxonomy as folders',
    ! array_intersect( array( 'language', 'post_translations' ), vergeml_tree_taxonomies() ), implode( ',', vergeml_tree_taxonomies() ) );

/* --- a translated picture keeps its folders ----------------------------------- */

$folders = wp_get_object_terms( $att, 'vergeml_folder', array( 'fields' => 'ids' ) );
if ( empty( $folders ) ) {
    $term = get_terms( array( 'taxonomy' => 'vergeml_folder', 'hide_empty' => false, 'number' => 1, 'fields' => 'ids' ) );
    wp_set_object_terms( $att, array( (int) $term[0] ), 'vergeml_folder' );
    $folders = wp_get_object_terms( $att, 'vergeml_folder', array( 'fields' => 'ids' ) );
}
sort( $folders );
vergeml_check( 'the original sits in at least one folder', ! empty( $folders ), implode( ',', $folders ) );

pll_set_post_language( $att, 'en' );

$tr_id = (int) $model->post->create_media_translation( $att, 'nl' );
vergeml_check( 'Polylang created the Dutch copy', $tr_id > 0 && $tr_id !== $att, 'id ' . $tr_id );
vergeml_check( 'the copy shares the file', get_attached_file( $tr_id, true ) === get_attached_file( $att, true ) );

$tr_folders = wp_get_object_terms( $tr_id, 'vergeml_folder', array( 'fields' => 'ids' ) );
sort( $tr_folders );
vergeml_check( 'the copy is in the same folders', $tr_folders === $folders, implode( ',', $tr_folders ) . ' vs ' . implode( ',', $folders ) );

/* --- tidy up, keeping the file -------------------------------------------------- */

add_filter( 'wp_delete_file', '__return_empty_string' );
wp_delete_attachment( $tr_id, true );
remove_filter( 'wp_delete_file', '__return_empty_string' );
vergeml_check( 'the shared file survived removing the copy', file_exists( get_attached_file( $att, true ) ) );

echo "\n" . ( $GLOBALS['vergeml_n'] - $GLOBALS['vergeml_fail'] ) . '/' . $GLOBALS['vergeml_n'] . " passed\n";
if ( $GLOBALS['vergeml_fail'] ) {
    exit( 1 );
}
