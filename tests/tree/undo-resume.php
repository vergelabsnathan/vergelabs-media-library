<?php
/**
 *  An undo that stops partway.
 *
 *      wp eval-file tests/tree/undo-resume.php --allow-root
 *
 *  Undo used to be one unchunked pass that only cleared its record at the very
 *  end, so a run that hit a shared host's execution limit threw away everything
 *  it had done and started from nothing on the next attempt -- an undo that could
 *  never finish on exactly the hosts slow enough to need retrying.
 *
 *  What is proved here is the property that fixes it: a pass that stops keeps
 *  what it did, the record survives so the rest can be picked up, and the folders
 *  are only deleted once every assignment is off them.
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

function count_terms( $tax ) {
	$t = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ) );
	return is_wp_error( $t ) ? 0 : count( $t );
}

function logged( $id ) {
	foreach ( (array) get_option( VERGEML_IMPORT_LOG, array() ) as $entry ) {
		if ( isset( $entry['id'] ) && $entry['id'] === $id ) {
			return true;
		}
	}
	return false;
}

echo "\nan undo that stops partway\n\n";

$before = count_terms( $tax );

/* --- something worth undoing -------------------------------------------- */

$result = vergeml_import_run( 'filebird', $tax );

while ( ! is_wp_error( $result ) && empty( $result['complete'] ) && ! empty( $result['resume'] ) ) {
	$result = vergeml_import_run( 'filebird', $tax, $result['resume'] );
}

t( 'an import to undo', ! is_wp_error( $result ) && ! empty( $result['complete'] ),
	is_wp_error( $result ) ? $result->get_error_code() : $result['assignments'] . ' assignments' );

$id       = $result['id'];
$imported = count_terms( $tax );

t( 'its folders are here', $imported > $before, $before . ' -> ' . $imported );

/* --- one pass, then stop ------------------------------------------------- */

$step = vergeml_import_undo_step( $id );

t( 'one pass does not finish it', ! is_wp_error( $step ) && empty( $step['complete'] ),
	$step['done'] . ' of ' . $step['total'] );
t( 'it says how to carry on', ! empty( $step['resume'] ) );
t( 'it did real work', $step['unassigned'] > 0, $step['unassigned'] . ' assignments removed' );

// The record has to survive, or a pass that dies takes the undo with it.
t( 'the import is still on record', logged( $id ) );

// Folders come last: deleting one early would take assignments with it that the
// record still expects to remove itself.
t( 'no folders are deleted yet', count_terms( $tax ) === $imported, count_terms( $tax ) . ' terms' );
t( 'and none are reported deleted', 0 === $step['removed'] );

/* --- a second pass picks up where the first stopped ---------------------- */

$again = vergeml_import_undo_step( $id, $step['resume'] );

t( 'the next pass carries on rather than restarting', $again['done'] > $step['done'],
	$step['done'] . ' -> ' . $again['done'] );

/* --- and it can be driven to the end ------------------------------------- */

$passes = 0;

while ( ! is_wp_error( $again ) && empty( $again['complete'] ) && ! empty( $again['resume'] ) ) {

	$again = vergeml_import_undo_step( $id, $again['resume'] );

	if ( ++$passes > 10000 ) {
		break;
	}
}

t( 'it finishes', ! is_wp_error( $again ) && ! empty( $again['complete'] ), $passes . ' further passes' );
t( 'every folder it made is gone', count_terms( $tax ) === $before, count_terms( $tax ) . ' terms, expected ' . $before );
t( 'the record is cleared only at the end', ! logged( $id ) );
t( 'a finished undo cannot be run again', is_wp_error( vergeml_import_undo_step( $id ) ) );

printf( "\n%d/%d passed\n\n", $GLOBALS['vgml_ok'], $GLOBALS['vgml_ok'] + $GLOBALS['vgml_bad'] );
exit( $GLOBALS['vgml_bad'] ? 1 : 0 );
