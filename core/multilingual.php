<?php
/**
 *  Polylang and WPML.
 *
 *  Both plugins translate an image by duplicating the attachment row: same
 *  file on disk, a second post in the other language. Neither copies the
 *  attachment's taxonomy terms, so without this file the translated copy of a
 *  filed picture lands in no folder and the editor working in the second
 *  language sees an unsorted library.
 *
 *  Two decisions, and the reason for each:
 *
 *  1. The translation goes into the same folders as the original. A folder is
 *     where a file is kept, not what the file says; "Product shots / Spring"
 *     is the same drawer in every language.
 *
 *  2. The folder taxonomies are not translatable. Polylang asks each plugin
 *     which of its taxonomies should get a language; if the folders did, every
 *     folder would have to exist once per language and the tree would show a
 *     different set of folders depending on the language filter. The moat
 *     is a shared media_category slug (see core/taxonomies.php); a per-language
 *     split of it would break every migration the slug makes possible. WPML
 *     is told the same through wpml-config.xml at the plugin root.
 *
 *  A site that wants folders per language after all can say so with
 *  add_filter( 'vergeml_multilingual_shared_folders', '__return_false' ).
 *
 *  Polylang's own filtering of the media grid by language is left alone: the
 *  tree's counts are library-wide, the grid shows the current language, and
 *  the difference is the number of translations. Redrawing counts per language
 *  would mean a join against Polylang's tables on every tree load, for a
 *  number that is right until the admin bar's language switches.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 *  The folder taxonomies -- the ones the tree draws. Named here so the two
 *  hooks below cannot drift apart on which taxonomies they copy and protect.
 */
function vergeml_multilingual_taxonomies() {
    return function_exists( 'vergeml_tree_taxonomies' ) ? vergeml_tree_taxonomies() : array( 'media_category' );
}

function vergeml_multilingual_shared_folders() {
    return (bool) apply_filters( 'vergeml_multilingual_shared_folders', true );
}

/**
 *  Copies the folder terms of one attachment onto another. Term ids, not names:
 *  the taxonomies are shared across languages (decision 2), so the ids are the
 *  same folders on both sides.
 */
function vergeml_multilingual_copy_folders( $from_id, $to_id ) {

    $from_id = (int) $from_id;
    $to_id   = (int) $to_id;

    if ( $from_id <= 0 || $to_id <= 0 || $from_id === $to_id ) {
        return 0;
    }

    $copied = 0;

    foreach ( vergeml_multilingual_taxonomies() as $taxonomy ) {

        $terms = wp_get_object_terms( $from_id, $taxonomy, array( 'fields' => 'ids' ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }

        $set = wp_set_object_terms( $to_id, array_map( 'intval', $terms ), $taxonomy, false );

        if ( ! is_wp_error( $set ) ) {
            $copied += count( $terms );
        }
    }

    if ( $copied && function_exists( 'vergeml_forget_request_caches' ) ) {
        vergeml_forget_request_caches();
    }

    return $copied;
}

/*
 *  Polylang: fired by PLL_Translated_Post::create_media_translation() after the
 *  copy exists and has its language, for both a manual "+" on the media screen
 *  and Polylang Pro's duplicate-at-upload.
 */
add_action( 'pll_translate_media', 'vergeml_multilingual_pll_translated', 10, 3 );

function vergeml_multilingual_pll_translated( $post_id, $tr_id, $lang ) {
    if ( vergeml_multilingual_shared_folders() ) {
        vergeml_multilingual_copy_folders( $post_id, $tr_id );
    }
}

/*
 *  Polylang asks which taxonomies get a language. The folders say no (decision
 *  2). The second argument is true when Polylang wants the list for its
 *  settings screen, and the answer is the same there: the option must not be
 *  offered, or ticking it splits the tree.
 */
add_filter( 'pll_get_taxonomies', 'vergeml_multilingual_pll_taxonomies', 10, 2 );

function vergeml_multilingual_pll_taxonomies( $taxonomies, $is_settings ) {

    if ( ! vergeml_multilingual_shared_folders() || ! is_array( $taxonomies ) ) {
        return $taxonomies;
    }

    foreach ( vergeml_multilingual_taxonomies() as $taxonomy ) {
        unset( $taxonomies[ $taxonomy ] );
    }

    return $taxonomies;
}

/*
 *  WPML Media Translation: fired after it duplicates an attachment for another
 *  language. The translatability of the taxonomies is declared in
 *  wpml-config.xml, which WPML reads on its own.
 */
add_action( 'wpml_media_create_duplicate_attachment', 'vergeml_multilingual_wpml_duplicated', 10, 2 );

function vergeml_multilingual_wpml_duplicated( $attachment_id, $duplicated_attachment_id ) {
    if ( vergeml_multilingual_shared_folders() ) {
        vergeml_multilingual_copy_folders( $attachment_id, $duplicated_attachment_id );
    }
}
