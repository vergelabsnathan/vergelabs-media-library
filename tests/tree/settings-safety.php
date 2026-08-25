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

/* --- the form that carried nothing --------------------------------------- */

/*
 *  The one that actually broke a real install.
 *
 *  vergeml_taxonomies and vergeml_tax_options live in the same settings group,
 *  and the Media Taxonomies screen posts them from two separate forms. WordPress
 *  saves every option in a group whenever any form in it is submitted, handing
 *  null to the ones the form did not carry -- so saving the options box wiped the
 *  taxonomies, and saving the taxonomies box wiped the options.
 *
 *  Tick one checkbox, press Save, and the folder tree is gone: hierarchical off,
 *  show_in_rest off, post types cleared, every term still in the database and
 *  nothing on any screen able to show them.
 */
unset( $_POST['vergeml_taxonomies'] );
unset( $_POST['vergeml_tax_options'] );

$kept = vergeml_taxonomies_validate( null );

t( 'a form that did not carry the taxonomies keeps them', count( (array) $kept ) === count( (array) $before ),
	count( (array) $kept ) . ' of ' . count( (array) $before ) . ' kept' );
t( 'including this one', ! empty( $kept[ $tax ] ) );
t( 'with hierarchical intact', ! empty( $kept[ $tax ]['hierarchical'] ),
	var_export( $kept[ $tax ]['hierarchical'] ?? null, true ) );
t( 'and show_in_rest intact', ! empty( $kept[ $tax ]['show_in_rest'] ),
	var_export( $kept[ $tax ]['show_in_rest'] ?? null, true ) );

$opts_before = get_option( 'vergeml_tax_options', array() );
$opts_kept   = vergeml_tax_options_validate( null );

t( 'and the same for the options in that group',
	count( (array) $opts_kept ) === count( (array) $opts_before ),
	count( (array) $opts_kept ) . ' of ' . count( (array) $opts_before ) . ' kept' );

/* --- the form that carried only part of it -------------------------------- */

/*
 *  The one that actually broke this install, twice.
 *
 *  The Media Taxonomies screen only gave a taxonomy its full editor when it was
 *  attached to attachments and nothing else. The moment folders were turned on
 *  for posts, our own taxonomy failed that test, dropped out of the editor, and
 *  reappeared lower down among other people's taxonomies -- where the form posts
 *  eml_media and four checkboxes and nothing more.
 *
 *  The sanitiser read that as a media taxonomy being saved with hierarchical,
 *  show_in_rest, sort and post types all absent, and switched them off. One Save
 *  and the folder tree was gone from every screen, with every folder still in the
 *  database and nothing to say why.
 *
 *  Two guards now: the editor recognises its own taxonomies by eml_media, and a
 *  form that carries the whole taxonomy says so, so a partial one cannot rewrite
 *  what it never showed.
 */
$partial = array( $tax => array(
	'eml_media'             => 1,
	'assigned'              => 1,
	'admin_filter'          => 1,
	'media_uploader_filter' => 1,
) );

$_POST['vergeml_taxonomies'] = $partial;
$survived = vergeml_taxonomies_validate( $partial );
unset( $_POST['vergeml_taxonomies'] );

t( 'a partial save keeps hierarchical', ! empty( $survived[ $tax ]['hierarchical'] ),
	var_export( $survived[ $tax ]['hierarchical'] ?? null, true ) );
t( 'and show_in_rest', ! empty( $survived[ $tax ]['show_in_rest'] ),
	var_export( $survived[ $tax ]['show_in_rest'] ?? null, true ) );
t( 'and the post types', isset( $survived[ $tax ]['post_types'] ) &&
	$survived[ $tax ]['post_types'] === ( $before[ $tax ]['post_types'] ?? array() ),
	wp_json_encode( $survived[ $tax ]['post_types'] ?? null ) );
t( 'and the labels', ! empty( $survived[ $tax ]['labels']['name'] ) );

// The full editor must still be able to turn things off, or the guard is a lock.
$full = $partial;
$full[ $tax ]['_full']        = 1;
$full[ $tax ]['hierarchical'] = 1;

$_POST['vergeml_taxonomies'] = $full;
$written = vergeml_taxonomies_validate( $full );
unset( $_POST['vergeml_taxonomies'] );

t( 'the full editor still writes what it was given', 1 === (int) $written[ $tax ]['hierarchical'] );
t( 'and can still switch something off', empty( $written[ $tax ]['show_in_rest'] ),
	var_export( $written[ $tax ]['show_in_rest'] ?? null, true ) );
t( 'the marker never reaches storage', ! isset( $written[ $tax ]['_full'] ) );

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
