/*
 *  The copy standard, on the strings the pass struck.
 *
 *      node tests/tree/copy.mjs
 *
 *  Reads the source from disk -- no WordPress, no site, no box. Every line
 *  here is a string Phase 4 replaced, listed with the rule it failed
 *  (docs/superpowers/specs/2026-09-05-folders-screen-design.md section 5, and
 *  the table in docs/superpowers/copy/2026-09-05-phase-4-copy.md). The suite
 *  exists because copy rots back: a string like "Please try again" is the
 *  first thing that gets typed when somebody adds the next error message, and
 *  nothing else in this repo would notice.
 *
 *  Mutation check that has been run against this suite: "Saved. Thank you."
 *  put back in core/instrument.php goes red at that row.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const ROOT = path.resolve( HERE, '..', '..' );

/** Struck string, and the rule it failed. */
const STRUCK = [
	[ 'What does this do?', 'a question the person did not ask, read aloud by a screen reader' ],
	[ 'one credit per image', '"image" where the plugin says "picture", and a question the button answers' ],
	[ 'Saved. Thank you.', 'a pleasantry that does not say what is now true' ],
	[ 'Off, and what was collected', 'two facts hung off a conjunction' ],
	[ 'One save for everything on this page.', 'the same note in three shapes' ],
	[ 'Taxonomy will be removed.', 'half of what happens' ],
	[ 'will remain intact in the database', 'one thing said twice, at length' ],
	[ 'Are you still sure?', 'a question after three lines that already said it' ],
	[ 'Please chose other one', '"Please", and "chose" for "choose"' ],
	[ 'taxomonies', 'a typo shipped for years' ],
	[ 'Taxonomy Name cannot be empty', 'an action buried in a conditional' ],
	[ 'cannot be canceled!', 'an exclamation and a question' ],
	[ 'Please fill into all fields', '"Please", and "fill into"' ],
	[ 'Please choose other one', '"Please", and a fragment for a sentence' ],
	[ 'so nothing was changed. Please try again', 'telling them to press the button they can see' ],
	[ 'paste your key by hand', 'two suggestions where one works — kept only in its rewritten form' ],
	[ 'they simply leave this folder', '"simply", and a question for a statement' ],
	[ 'An error has occurred. Please reload', 'names nothing, and two instructions for one action' ],
	/*
	 *  Phase 5, the AI screen (docs/superpowers/copy/2026-09-06-phase-5-copy.md).
	 */
	[ 'one credit describes one image', '"image" where the plugin says "picture"; the rail says it on every screen' ],
	[ 'Describe your images', '"image" where the plugin says "picture", in the name of the feature' ],
	[ 'Descriptions, alt text and search, written from your images.', 'a lede that decorates; the facts line replaced it' ],
	[ 'Each image is shown to the model once.', 'a lede under a kicker; what it said is a row in the table now' ],
	[ 'Describe new images', 'a button without its number' ],
	[ 'Fix missing alt text', 'a button without its number, and "fix" for what it does' ],
	[ 'Run in the background — you can close this tab', '"you can"; the fact is that the tab can be closed' ],
	[ 'Set once — it applies to every run above.', 'a note under a card title, explaining a settings form that is gone' ],
	[ 'Not running.', 'a state with no number, said when nothing was asked' ],
	[ 'Loading…', 'a placeholder where the server had the number' ],
	/*
	 *  Phase 6, the look-alike cards (docs/superpowers/copy/2026-09-06-phase-6-copy.md).
	 */
	[ 'Possibly related', 'a hedge for a heading, and no number' ],
	[ 'we are not confident they are the same picture', 'a note explaining the list instead of stating it; "we"' ],
	[ 'Nothing else looked similar.', 'the list is named look-alikes now' ],
	[ 'Open in the library ↗', 'one control for a set that opened only its first file' ],
	[ 'freed by keeping one of each', 'false once Keep this one sets aside instead of deleting' ],
	/*
	 *  Phase 8, the import cards
	 *  (docs/superpowers/specs/2026-09-06-import-cards.md).
	 */
	[ 'Bring folders over from another plugin', 'a lede describing the screen; the library’s own numbers replaced it' ],
	[ 'Preview import', 'a second press for one act, and a button with no number' ],
	[ 'No folders were found in any other plugin', 'one sentence for seven sources that each say for themselves' ],
	[ 'Recent imports', 'a heading about the list rather than about what was done' ],
	[ 'Undo this import', 'a button without the number it removes' ],
	[ 'Undone. The folders we made are gone', 'a reassurance where the two numbers belong' ],
	[ 'A file, instead of another plugin', 'a heading defined by what it is not' ],
];

/*
 *  Only what ships and what a person reads: PHP and JS the plugin loads.
 *  Not tests, not docs, not the plans -- the struck strings are quoted there
 *  on purpose, and a suite that failed on its own table would be useless.
 */
const LOOK_IN = [ 'core', 'js' ];

const files = [];

for ( const dir of LOOK_IN ) {
	for ( const name of fs.readdirSync( path.join( ROOT, dir ) ) ) {
		if ( /\.(php|js)$/.test( name ) ) {
			files.push( path.join( dir, name ) );
		}
	}
}

const results = [];

function check( what, ok, detail ) {
	results.push( { ok, what } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' }  ${ what }${ ok || ! detail ? '' : `  -- ${ detail }` }` );
}

console.log( `\nthe copy standard, over ${ files.length } files\n` );

const source = files.map( ( f ) => [ f, fs.readFileSync( path.join( ROOT, f ), 'utf8' ) ] );

for ( const [ struck, why ] of STRUCK ) {
	const found = source.filter( ( [ , text ] ) => text.includes( struck ) ).map( ( [ f ] ) => f );

	check( `"${ struck }" is gone — ${ why }`, 0 === found.length, found.join( ', ' ) );
}

/*
 *  The rewritten string that replaced the worst of them, asserted present so
 *  the row above cannot be satisfied by deleting the message altogether.
 */
const KEPT = [
	[ 'core/instrument.php', 'On. The counts go once a day.' ],
	[ 'core/connect.php', 'Paste your key by hand instead.' ],
	[ 'core/tree-ui.php', 'No file is deleted; they leave this folder.' ],
	[ 'core/ai-screen.php', 'Describe · nothing new' ],
	[ 'core/ai-screen.php', 'On the picture itself: its alt text, only when empty' ],
	[ 'core/brief.php', 'Nothing is written until the brief is used' ],
	[ 'core/admin-shell.php', 'one describes one picture' ],
	[ 'core/health.php', 'Look-alikes · %s sets' ],
	[ 'core/health.php', 'Keep this one · %s set aside' ],
	[ 'core/health.php', 'Keep both · not shown again' ],
	[ 'core/health.php', 'held by the extra copies' ],
	[ 'core/health-keep.php', 'could not be rewritten' ],
];

for ( const [ file, text ] of KEPT ) {
	const hit = source.find( ( [ f ] ) => f.split( path.sep ).join( '/' ) === file );

	check( `${ file } says "${ text }"`, !! hit && hit[ 1 ].includes( text ), hit ? 'not found in the file' : 'file not read' );
}

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
