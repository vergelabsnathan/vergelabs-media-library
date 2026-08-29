<?php
/**
 *  Folders when the site speaks two languages.
 *
 *  Polylang and WPML both translate taxonomies natively, which is the whole
 *  reason folders were built on a taxonomy rather than on custom tables:
 *  FileBird needs a `Support/WPML.php` to keep its own tables in step, and we
 *  need whatever this suite says we need and nothing more.
 *
 *  So the question is not "does it work" but "what happens", and it has one
 *  sharp edge. Once a taxonomy is translatable, Polylang filters `get_terms()`
 *  by the current language. A folder tree that silently showed only half of
 *  somebody's folders -- with no indication that the other half exists -- would
 *  be the worst possible outcome, and it would look exactly like working
 *  software.
 *
 *      wp eval-file tests/compat/polylang.php --allow-root
 *
 *  Configures Polylang if it has no languages yet, seeds under zzpll, and puts
 *  the settings back. Skips cleanly, and says so, when Polylang is not active.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vergeml_tree_taxonomies' ) ) {
    echo "the plugin is not loaded -- inactive, or safe mode?\n";
    exit( 1 );
}

if ( ! function_exists( 'PLL' ) || ! PLL() ) {
    /*
     *  Exit 2, not 1. tools/verify.mjs reads 2 as SKIPPED, which is the honest
     *  answer when the thing under test is not installed -- reporting a pass
     *  would be a lie and reporting a failure would be noise.
     */
    echo "\nPolylang is not active on this site, so there is nothing to check.\n";
    echo "  wp plugin install polylang --activate\n";
    exit( 2 );
}

global $wpdb;

$GLOBALS['pl_pass'] = 0;
$GLOBALS['pl_fail'] = 0;
$GLOBALS['pl_log']  = '';

function pl_say( $line ) {
    $GLOBALS['pl_log'] .= $line;
    echo $line; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function pl_check( $label, $ok, $note = '' ) {
    if ( $ok ) {
        $GLOBALS['pl_pass']++;
    } else {
        $GLOBALS['pl_fail']++;
    }
    pl_say( sprintf( "  %s  %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, '' === $note ? '' : '  -- ' . $note ) );
}


pl_say( "\nfolders in two languages\n\n" );

$pl_tax      = 'media_category';
$pl_before   = get_option( 'polylang' );
$pl_made     = array();
$pl_added    = array();


/* --------------------------------------------------------------- setting up */

pl_say( "A  a bilingual site\n" );

$pl_model = PLL()->model;
$pl_langs = $pl_model->get_languages_list();

if ( count( $pl_langs ) < 2 ) {

    foreach ( array(
        array( 'name' => 'English', 'slug' => 'en', 'locale' => 'en_US', 'rtl' => 0, 'term_group' => 0, 'flag' => 'us' ),
        array( 'name' => 'Nederlands', 'slug' => 'nl', 'locale' => 'nl_NL', 'rtl' => 0, 'term_group' => 1, 'flag' => 'nl' ),
    ) as $pl_new ) {

        $pl_done = $pl_model->languages->add( $pl_new );

        if ( is_wp_error( $pl_done ) ) {
            pl_say( '  could not add ' . $pl_new['slug'] . ': ' . $pl_done->get_error_message() . "\n" );
        } else {
            $pl_added[] = $pl_new['slug'];
        }
    }

    $pl_model->languages->clean_cache();
    $pl_langs = $pl_model->get_languages_list();
}

pl_check( 'the site has two languages', count( $pl_langs ) >= 2, count( $pl_langs ) . ' configured' );

if ( count( $pl_langs ) < 2 ) {
    pl_say( "\nwithout two languages there is nothing to test.\n" );
    pl_say( sprintf( "\n%d/%d passed\n", $GLOBALS['pl_pass'], $GLOBALS['pl_pass'] + $GLOBALS['pl_fail'] ) );
    exit( 1 );
}

/*
 *  The setting that actually matters. Until media_category is in this list,
 *  Polylang is installed but is not touching folders at all, and a suite run
 *  in that state proves nothing -- which is exactly what the first run of this
 *  did, reporting everything green while Polylang was inert.
 *
 *  It cannot be switched on and used in the same request. Polylang decides
 *  which taxonomies are translated during `init`, long before wp eval-file
 *  gets a turn, so writing the option now takes effect on the NEXT request.
 *  Same shape as pro/tests/api-base.php and for the same reason: some things
 *  are read once per process and a test that pretends otherwise is testing
 *  its own setup.
 */
if ( ! PLL()->model->is_translated_taxonomy( $pl_tax ) ) {

    $pl_opts = get_option( 'polylang' );

    $pl_opts['taxonomies'] = array_values( array_unique( array_merge(
        isset( $pl_opts['taxonomies'] ) ? (array) $pl_opts['taxonomies'] : array(),
        array( $pl_tax )
    ) ) );

    update_option( 'polylang', $pl_opts );

    pl_say( "\n  Folders were not a translated taxonomy yet. That is now switched on,\n" );
    pl_say( "  but Polylang reads it at init, so it takes effect next request.\n" );
    pl_say( "  Run this file once more.\n" );

    // Exit 2 is SKIPPED to the runner, which is what this is: nothing was
    // asserted, and saying "passed" would be the lie this suite exists to
    // avoid.
    exit( 2 );
}

pl_check( 'media_category is a translated taxonomy', true, 'Polylang is filtering folders, so the checks below mean something' );


/* -------------------------------------------------------- folders per language */

pl_say( "\nB  a folder in each language\n" );

foreach ( array( 'en' => 'zzpll English', 'nl' => 'zzpll Nederlands' ) as $pl_slug => $pl_name ) {

    $pl_term = wp_insert_term( $pl_name, $pl_tax );

    if ( is_wp_error( $pl_term ) ) {
        pl_check( 'seeded ' . $pl_name, false, $pl_term->get_error_message() );
        continue;
    }

    $pl_id = (int) $pl_term['term_id'];
    $pl_made[ $pl_slug ] = $pl_id;

    // Polylang stores a term's language as a term of its own; this is its API
    // for saying which.
    PLL()->model->term->set_language( $pl_id, $pl_slug );
}

pl_check( 'two folders seeded, one per language', 2 === count( $pl_made ), wp_json_encode( $pl_made ) );

if ( 2 !== count( $pl_made ) ) {
    pl_say( sprintf( "\n%d/%d passed\n", $GLOBALS['pl_pass'], $GLOBALS['pl_pass'] + $GLOBALS['pl_fail'] ) );
    exit( 1 );
}

pl_check(
    'each folder knows its language',
    'en' === PLL()->model->term->get_language( $pl_made['en'] )->slug
        && 'nl' === PLL()->model->term->get_language( $pl_made['nl'] )->slug
);


/* ------------------------------------------------------------------ the tree */

pl_say( "\nC  what the folder tree shows\n" );

/*
 *  The assertion this file exists for.
 *
 *  vergeml_tree_terms() is what the /tree endpoint reads, and if Polylang's
 *  get_terms filter reaches it, a Dutch editor sees only the Dutch folders and
 *  has no way of knowing the English ones exist. Files filed into the folders
 *  they cannot see appear to have vanished.
 */
$pl_all = get_terms( array( 'taxonomy' => $pl_tax, 'hide_empty' => false, 'fields' => 'ids' ) );
$pl_all = is_wp_error( $pl_all ) ? array() : array_map( 'intval', $pl_all );

$pl_sees_both = in_array( $pl_made['en'], $pl_all, true ) && in_array( $pl_made['nl'], $pl_all, true );

pl_check(
    'an unfiltered get_terms sees both languages',
    $pl_sees_both,
    $pl_sees_both ? '' : 'admin-side get_terms is language-filtered: ' . count( $pl_all ) . ' terms'
);

/*
 *  That check is the endpoint's check, not a proxy for it.
 *
 *  vergeml_rest_tree() reads its folders with a plain
 *  get_terms( taxonomy, hide_empty => false ) and no language argument of any
 *  kind -- there is no separate reader to test. So whatever Polylang does to
 *  get_terms is precisely what the panel shows, and asserting on get_terms
 *  above is asserting on the tree.
 */
pl_check(
    'the tree endpoint has no reader of its own to diverge from it',
    false !== strpos( (string) file_get_contents( dirname( __DIR__, 2 ) . '/core/rest-tree.php' ), "\$terms = get_terms( array(" ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    'if this fails, vergeml_rest_tree() grew its own term query and this suite is now testing the wrong thing'
);


/* -------------------------------------------------- filing across a language */

pl_say( "\nD  filing a file into a folder in the other language\n" );

$pl_file = wp_insert_post( array(
    'post_title'     => 'zzpll-img',
    'post_name'      => 'zzpll-img',
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'post_mime_type' => 'image/jpeg',
    'guid'           => 'http://example.test/zzpll-img.jpg',
) );

$pl_file = ( $pl_file && ! is_wp_error( $pl_file ) ) ? (int) $pl_file : 0;

pl_check( 'a file to move about', $pl_file > 0 );

if ( $pl_file > 0 ) {

    // Into both, which is legal here: a file may live in more than one folder,
    // and nothing about that changes because the folders differ in language.
    wp_set_object_terms( $pl_file, array( $pl_made['en'], $pl_made['nl'] ), $pl_tax );

    $pl_on = wp_get_object_terms( $pl_file, $pl_tax, array( 'fields' => 'ids' ) );
    $pl_on = is_wp_error( $pl_on ) ? array() : array_map( 'intval', $pl_on );

    pl_check( 'it is in both folders', 2 === count( $pl_on ), count( $pl_on ) . ' folders' );

    // The count badge is what a user reads, so it has to be right in both.
    $pl_en = get_term( $pl_made['en'], $pl_tax );
    $pl_nl = get_term( $pl_made['nl'], $pl_tax );

    pl_check(
        'both folders count it',
        ! is_wp_error( $pl_en ) && ! is_wp_error( $pl_nl ) && 1 === (int) $pl_en->count && 1 === (int) $pl_nl->count,
        ( is_wp_error( $pl_en ) ? '?' : $pl_en->count ) . ' / ' . ( is_wp_error( $pl_nl ) ? '?' : $pl_nl->count )
    );
}


/* --------------------------------------------------------- export round trip */

pl_say( "\nE  the CSV still round-trips\n" );

if ( function_exists( 'vergeml_csv_export_rows' ) ) {

    $pl_rows = vergeml_csv_export_rows( $pl_tax );
    $pl_out  = is_wp_error( $pl_rows ) ? array() : wp_list_pluck( $pl_rows, 0 );

    pl_check(
        'both languages are written out',
        in_array( 'zzpll English', $pl_out, true ) && in_array( 'zzpll Nederlands', $pl_out, true ),
        is_wp_error( $pl_rows ) ? $pl_rows->get_error_message() : count( $pl_out ) . ' rows'
    );
} else {
    pl_check( 'the CSV exporter exists to be checked', false, 'core/import-csv.php not loaded' );
}


/* ------------------------------------------------------------------ tidying */

pl_say( "\ntidying up\n" );

if ( $pl_file > 0 ) {
    wp_delete_post( $pl_file, true );
}

foreach ( $pl_made as $pl_id ) {
    wp_delete_term( $pl_id, $pl_tax );
}

update_option( 'polylang', $pl_before );

pl_check( 'the seeded folders are gone', ! get_term( $pl_made['en'], $pl_tax ) || is_wp_error( get_term( $pl_made['en'], $pl_tax ) ) );
pl_check( 'the Polylang settings are back as they were', get_option( 'polylang' ) === $pl_before );

if ( ! empty( $pl_added ) ) {
    pl_say( '  note: languages ' . implode( ', ', $pl_added ) . " were added to this site and were left in place.\n" );
}

pl_say( sprintf( "\n%d/%d passed\n", $GLOBALS['pl_pass'], $GLOBALS['pl_pass'] + $GLOBALS['pl_fail'] ) );

@file_put_contents( __DIR__ . '/polylang-last-run.txt', $GLOBALS['pl_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

if ( $GLOBALS['pl_fail'] > 0 ) {
    exit( 1 );
}
