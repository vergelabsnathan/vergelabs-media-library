<?php
/**
 *  A partial settings write must not delete everybody's folders.
 *
 *      wp eval-file tests/tree/settings-safety.php --allow-root
 *
 *  Two things used to line up badly.
 *
 *  The settings sanitiser rebuilds each taxonomy from the input it is handed,
 *  which is correct for the settings form -- an unticked checkbox submits nothing
 *  and has to come out as 0 -- and wrong for every other caller. Anything writing
 *  the option without carrying the labels through dropped them.
 *
 *  And registration required a name and a singular name, skipping the taxonomy
 *  silently without them. So a write that omitted the labels unregistered the
 *  taxonomy: no folder tree, no filters, no Media Categories screen, and every
 *  term still sitting in the database. From the outside every folder on the site
 *  had vanished, with nothing to say why.
 *
 *  Either fix alone would do. Both are here because the second is what stops it
 *  being catastrophic and the first is what stops it happening.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_set_current_user( 1 );

$tax = 'media_category';

$GLOBALS['vgml_ok']  = 0;
$GLOBALS['vgml_bad'] = 0;

function t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['vgml_ok']++ : $GLOBALS['vgml_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, $detail ? '  -- ' . $detail : '' );
}

echo "\na partial settings write\n\n";

$before = get_option( 'vergeml_taxonomies', array() );

t( 'the taxonomy starts registered', taxonomy_exists( $tax ) );
t( 'and has labels on file', ! empty( $before[ $tax ]['labels']['name'] ),
	isset( $before[ $tax ]['labels']['name'] ) ? $before[ $tax ]['labels']['name'] : 'none' );

/* --- a write that forgets the labels ------------------------------------- */

$partial = $before;
unset( $partial[ $tax ]['labels'] );
unset( $partial[ $tax ]['hierarchical'] );

/*
 *  The sanitiser is called directly rather than through update_option.
 *
 *  register_setting only runs in an admin request, so on the command line
 *  update_option writes straight past the filter -- which would make this look
 *  like a test of the sanitiser while testing nothing at all.
 */
$after = vergeml_taxonomies_validate( $partial );

t( 'the labels survived it', ! empty( $after[ $tax ]['labels']['name'] ),
	isset( $after[ $tax ]['labels']['name'] ) ? $after[ $tax ]['labels']['name'] : 'GONE' );
t( 'and they are the ones that were there', $after[ $tax ]['labels']['name'] === $before[ $tax ]['labels']['name'] );

update_option( 'vergeml_taxonomies', $after );
vergeml_on_init();

t( 'the taxonomy is still registered', taxonomy_exists( $tax ) );
t( 'and still on attachments', in_array( 'attachment', (array) get_taxonomy( $tax )->object_type, true ) );
t( 'so the tree still has something to show', in_array( $tax, vergeml_tree_taxonomies(), true ) );

/* --- labels genuinely absent --------------------------------------------- */

/*
 *  Past the sanitiser this time, which is the state a database restore or an
 *  older version can leave behind. Registration has to cope on its own.
 */
$stripped = $after;
unset( $stripped[ $tax ]['labels'] );

remove_all_filters( 'sanitize_option_vergeml_taxonomies' );
update_option( 'vergeml_taxonomies', $stripped );

$raw = get_option( 'vergeml_taxonomies', array() );
t( 'the labels really are gone now', empty( $raw[ $tax ]['labels'] ) );

vergeml_on_init();

t( 'it registers anyway', taxonomy_exists( $tax ),
	taxonomy_exists( $tax ) ? get_taxonomy( $tax )->labels->name : 'NOT REGISTERED' );
t( 'with a name made from the slug', taxonomy_exists( $tax ) && '' !== get_taxonomy( $tax )->labels->name,
	taxonomy_exists( $tax ) ? get_taxonomy( $tax )->labels->name : '' );
t( 'and the folders are still reachable', in_array( $tax, vergeml_tree_taxonomies(), true ) );

$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ) );
t( 'with every term where it was', ! is_wp_error( $terms ) && count( $terms ) > 0,
	is_wp_error( $terms ) ? $terms->get_error_code() : count( $terms ) . ' terms' );

/* --- put it back --------------------------------------------------------- */

update_option( 'vergeml_taxonomies', $before );
vergeml_on_init();

t( 'the real settings are back', taxonomy_exists( $tax ) &&
	get_taxonomy( $tax )->labels->name === $before[ $tax ]['labels']['name'],
	taxonomy_exists( $tax ) ? get_taxonomy( $tax )->labels->name : 'gone' );

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
