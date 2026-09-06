<?php
/**
 *  The preview and the import, on the same tree.
 *
 *      wp eval-file tests/tree/import-plan.php --allow-root
 *
 *  vergeml_import_plan() and vergeml_import_run() walk the same folders in the
 *  same order and key each one the same way -- that is the whole safety
 *  argument of the import screen, because the card states what the button will
 *  do before the button is pressed. They disagreed. The run inserts each parent
 *  before its children, so a child's parent is a real term id; the plan cannot
 *  insert, so it holds `new:<source id>` -- and vergeml_import_key() cast the
 *  parent with (int), which turns `new:4` into 0. Every folder whose parent was
 *  about to be created was keyed as though it sat at the top of the tree.
 *
 *  Measured on the box before the fix: the preview said 11 new folders and 3
 *  merged where the import made 12 and merged 2.
 *
 *  Mutation check that has been run against this suite: put `(int)` back on the
 *  parent in vergeml_import_key() and the four rows comparing the plan with the
 *  run go red.
 *
 *  Nothing live is touched. The source is a taxonomy of this suite's own, the
 *  destination is another, and the import log is put back exactly as it was
 *  found -- a sixth import would otherwise push the oldest of the box's five
 *  off the end of the record and take its undo with it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_set_current_user( 1 );

$GLOBALS['vgml_ok']  = 0;
$GLOBALS['vgml_bad'] = 0;

function t( $name, $pass, $detail = '' ) {
	$pass ? $GLOBALS['vgml_ok']++ : $GLOBALS['vgml_bad']++;
	printf( "  %s %s%s\n", $pass ? 'ok  ' : 'FAIL', $name, $detail ? '  -- ' . $detail : '' );
}

function terms_in( $taxonomy ) {
	$t = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
	return is_wp_error( $t ) ? array() : array_map( 'absint', $t );
}

function named( $taxonomy, $name ) {
	$out = array();
	foreach ( terms_in( $taxonomy ) as $id ) {
		$term = get_term( $id, $taxonomy );
		if ( $term instanceof WP_Term && $term->name === $name ) {
			$out[] = $term;
		}
	}
	return $out;
}

const SRC  = 'vgml_ptest_src';
const DEST = 'vgml_ptest_dest';

/*
 *  Two taxonomies of our own. The source is read straight from the tables by
 *  vergeml_import_read_taxonomy(), the way a deactivated plugin's is, so it
 *  needs no UI; the destination has to be one the importer will accept, which
 *  means show_ui -- that is what vergeml_tree_taxonomies() looks at.
 */
register_taxonomy( SRC, 'attachment', array( 'hierarchical' => true, 'public' => false, 'show_ui' => false ) );
register_taxonomy( DEST, 'attachment', array( 'hierarchical' => true, 'public' => false, 'show_ui' => true, 'label' => 'Plan test folders' ) );

echo "\nthe preview and the import, on one tree\n\n";

t( 'the destination is a taxonomy the importer accepts', in_array( DEST, vergeml_tree_taxonomies(), true ) );
t( 'the destination starts empty', count( terms_in( DEST ) ) === 0, count( terms_in( DEST ) ) . ' terms' );

/* --- the source tree ---------------------------------------------------- */

/*
 *  Three levels deep, and a name repeated at two different depths. "Apparel"
 *  sits both at the top and under Products; "2024" sits under each of them.
 *  Under the old key both Apparels and both 2024s collapsed into one slot.
 */
$products = wp_insert_term( 'Products', SRC );
$products = (int) $products['term_id'];

$sub_apparel = wp_insert_term( 'Apparel', SRC, array( 'parent' => $products ) );
$sub_apparel = (int) $sub_apparel['term_id'];

$sub_2024 = wp_insert_term( '2024', SRC, array( 'parent' => $sub_apparel ) );
$sub_2024 = (int) $sub_2024['term_id'];

$top_apparel = wp_insert_term( 'Apparel', SRC, array( 'slug' => 'apparel-top' ) );
$top_apparel = (int) $top_apparel['term_id'];

$top_2024 = wp_insert_term( '2024', SRC, array( 'parent' => $top_apparel, 'slug' => '2024-top' ) );
$top_2024 = (int) $top_2024['term_id'];

t( 'the source holds five folders, three levels deep', count( terms_in( SRC ) ) === 5, count( terms_in( SRC ) ) . ' terms' );

/*
 *  A folder that is already here by the name the source uses at the top. This
 *  is the one the buggy key merged the whole of Products/Apparel into.
 */
$decoy = wp_insert_term( 'Apparel', DEST );
$decoy = (int) $decoy['term_id'];

/* Files, so the plan and the run have assignments to disagree about. */
$files = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 3, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
$files = array_map( 'absint', (array) $files );

t( 'the site has files to file', count( $files ) === 3, count( $files ) . ' found' );

if ( count( $files ) === 3 ) {
	// The same file in two source folders that land in two different folders
	// here: two assignments, not one.
	wp_set_object_terms( $files[0], array( $sub_2024 ), SRC, true );
	wp_set_object_terms( $files[0], array( $top_2024 ), SRC, true );
	wp_set_object_terms( $files[1], array( $products ), SRC, true );
	wp_set_object_terms( $files[2], array( $top_apparel ), SRC, true );
}

add_filter( 'vergeml_import_sources', 'vgml_plan_test_source' );

function vgml_plan_test_source( $sources ) {
	$sources['vgml_ptest'] = array(
		'name'     => 'Plan test',
		'author'   => 'the suite',
		'kind'     => 'taxonomy',
		'taxonomy' => SRC,
	);
	return $sources;
}

$read = vergeml_import_read( 'vgml_ptest' );
t( 'the source reads back', ! is_wp_error( $read ) && count( $read['folders'] ) === 5,
	is_wp_error( $read ) ? $read->get_error_code() : count( $read['folders'] ) . ' folders' );

/* --- what the plan says, and what the run does -------------------------- */

$log_before = get_option( VERGEML_IMPORT_LOG, array() );

$plan = vergeml_import_plan( 'vgml_ptest', DEST );

t( 'the plan ran', ! is_wp_error( $plan ), is_wp_error( $plan ) ? $plan->get_error_code() : '' );

/*
 *  The shape the tree demands, stated rather than derived: Products, its
 *  Apparel, its 2024 and the top Apparel's 2024 are four new folders, and only
 *  the source's own top-level Apparel merges into the one already here. This is
 *  the row that names the cause -- a folder whose parent is about to be made is
 *  not a folder at the top of the tree.
 */
t( 'the plan makes four folders and merges one', 4 === (int) $plan['create'] && 1 === (int) $plan['merge'],
	$plan['create'] . ' new, ' . $plan['merge'] . ' merged' );

t( 'the plan counts four assignments', 4 === (int) $plan['assignments'], (string) $plan['assignments'] );

$run = vergeml_import_run( 'vgml_ptest', DEST );
$passes = 0;

while ( ! is_wp_error( $run ) && empty( $run['complete'] ) && ! empty( $run['resume'] ) && ++$passes < 50 ) {
	$run = vergeml_import_run( 'vgml_ptest', DEST, $run['resume'] );
}

t( 'the import ran to the end', ! is_wp_error( $run ) && ! empty( $run['complete'] ),
	is_wp_error( $run ) ? $run->get_error_code() : '' );

if ( ! is_wp_error( $plan ) && ! is_wp_error( $run ) ) {

	t( 'the plan and the import agree on new folders', (int) $plan['create'] === (int) $run['created'],
		'plan ' . $plan['create'] . ', import ' . $run['created'] );

	t( 'the plan and the import agree on merged folders', (int) $plan['merge'] === (int) $run['merged'],
		'plan ' . $plan['merge'] . ', import ' . $run['merged'] );

	t( 'the plan and the import agree on files filed', (int) $plan['assignments'] === (int) $run['assignments'],
		'plan ' . $plan['assignments'] . ', import ' . $run['assignments'] );

	t( 'the plan and the import agree on how many folders the source has', (int) $plan['folders'] === 5,
		(string) $plan['folders'] );
}

/* --- and the tree that came out of it ----------------------------------- */

$apparels = named( DEST, 'Apparel' );
$twenties = named( DEST, '2024' );

t( 'both Apparels are here, and they are two folders', count( $apparels ) === 2, count( $apparels ) . ' found' );
t( 'both 2024s are here, and they are two folders', count( $twenties ) === 2, count( $twenties ) . ' found' );

$parents = array_map( function ( $term ) { return (int) $term->parent; }, $twenties );
t( 'the two 2024s sit under different parents', count( array_unique( $parents ) ) === 2, implode( '/', $parents ) );

$under_products = 0;
foreach ( $apparels as $term ) {
	if ( (int) $term->parent !== 0 ) {
		$under_products = (int) $term->term_id;
	}
}
t( 'the source Apparel under Products was made, not merged into the one here',
	$under_products > 0 && $under_products !== $decoy, 'term ' . $under_products . ', decoy ' . $decoy );

t( 'the folder that was already here survived', get_term( $decoy, DEST ) instanceof WP_Term );

/* --- put everything back ------------------------------------------------ */

$undo = vergeml_import_undo( $run['id'] );

t( 'the undo ran', ! is_wp_error( $undo ), is_wp_error( $undo ) ? $undo->get_error_code() : '' );
t( 'only the folder that was already here is left', terms_in( DEST ) === array( $decoy ),
	implode( ',', terms_in( DEST ) ) );

// The record of the box's own imports, exactly as it was found.
update_option( VERGEML_IMPORT_LOG, $log_before, false );
t( 'the import log is as it was found', get_option( VERGEML_IMPORT_LOG, array() ) === $log_before,
	count( (array) $log_before ) . ' entries' );

foreach ( terms_in( DEST ) as $id ) {
	wp_delete_term( $id, DEST );
}
foreach ( terms_in( SRC ) as $id ) {
	wp_delete_term( $id, SRC );
}

t( 'the suite left nothing behind', count( terms_in( SRC ) ) === 0 && count( terms_in( DEST ) ) === 0,
	count( terms_in( SRC ) ) . ' source, ' . count( terms_in( DEST ) ) . ' destination' );

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
