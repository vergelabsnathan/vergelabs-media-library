/*
 *  Every database call stays classified.
 *
 *      node tests/security/db-calls.mjs
 *
 *  Phase 5.3 of plans/four-yesses.md. tools/db-calls.mjs classifies all 202 calls
 *  that reach the server; this runs it and refuses three things:
 *
 *    - an unexplained call. A new query that the prover cannot prove and the
 *      reviewed register does not name goes red here, on the commit that adds it.
 *    - a stale reason. Each hand-read call is keyed by a hash of its own SQL, so
 *      editing one of those queries expires its review and it becomes an
 *      unexplained finding again. A register entry that no longer matches
 *      anything also goes red, because a reason nobody can attach to a call is a
 *      reason that has quietly stopped being checked.
 *    - a helper that stopped casting. The whole classification leans on
 *      vergeml_ids() mapping absint; if it ever stops, several hundred proofs
 *      become wrong at once and nothing else in this repo would notice.
 *
 *  Nothing here reaches a site, a box or a model. It reads PHP as text.
 *
 *  ## Mutation checks
 *
 *  Run on 2026-09-10, each red at the row named and green again once reverted.
 *  Two of the four failed to bite on the first attempt, and both gaps were in
 *  this suite rather than in the plugin — which is the argument for running them:
 *
 *    1. ` AND kind = $kind` appended to core/ai-index.php's prime query
 *       → "no call reaches the server unexplained" FAILs, naming the line and
 *         both unproven expressions, and the register entry reads as stale.
 *         *First attempt passed.* The register matched its reason to an excerpt
 *         of the SQL as a substring, and a query that is added to still contains
 *         the excerpt. The key became a hash of the whole statement.
 *    2. `$one = (int) $one` removed from vergeml_ids()
 *       → "vergeml_ids() still casts every id" FAILs.
 *         *First attempt passed.* This suite read a fixed 600 characters from the
 *         function's start, which overran it by half, and the cast it found
 *         belonged to the next function. It now reads to the closing brace.
 *    3. `COUNT(*)` changed to `COUNT(1)` in one of search-try.php's queries
 *       → "no call reaches the server unexplained" FAILs. One character of SQL
 *         expires that call's review, which is the whole intent of the key.
 *    4. a register entry's fingerprint changed to one nothing has
 *       → "every reason in the reviewed register still matches a call" FAILs, and
 *         the call it used to cover reappears as unexplained.
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const ROOT = path.resolve( HERE, '..', '..' );
const TOOL = path.join( ROOT, 'tools', 'db-calls.mjs' );

const results = [];

function check( what, ok, detail ) {
	results.push( { ok, what } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' }  ${ what }${ ok || ! detail ? '' : `  -- ${ detail }` }` );
}

console.log( '\nevery database call, still classified\n' );


/* ------------------------------------------------------ the doc is the code's */

let drift = null;

try {
	execFileSync( process.execPath, [ TOOL, '--check' ], { cwd: ROOT, stdio: 'pipe' } );
} catch ( e ) {
	drift = ( String( e.stdout ?? '' ) + String( e.stderr ?? '' ) ).trim();
}

check(
	'docs/security-db-calls.md matches the code — run node tools/db-calls.mjs if not',
	null === drift,
	drift ?? ''
);

const model = JSON.parse( execFileSync( process.execPath, [ TOOL, '--json' ], { cwd: ROOT, maxBuffer: 64 * 1024 * 1024 } ).toString() );
const c = model.counted;


/* ------------------------------------------------------------ nothing unexplained */

const unexplained = model.calls.filter( ( call ) => call.klass === 'finding' );

check(
	'no call reaches the server unexplained',
	0 === unexplained.length,
	unexplained.map( ( call ) => `${ call.rel }:${ call.line } could not prove ${ call.unproven.join( ', ' ) }` ).join( ' | ' )
);

/*
 *  A register entry that matches nothing is the quiet failure mode: the query it
 *  described was rewritten or removed, the reason stayed, and a future edit to
 *  that file has one fewer thing watching it.
 */
const stale = [];

for ( const [ rel, entries ] of Object.entries( model.reviewed ) ) {
	for ( const [ hash ] of entries ) {
		const used = model.calls.some( ( call ) => call.rel === rel && call.klass === 'reviewed' && call.fingerprint === hash );
		if ( ! used ) stale.push( `${ rel }: ${ hash }` );
	}
}

check(
	'every reason in the reviewed register still matches a call',
	0 === stale.length,
	stale.join( ' | ' )
);

check(
	`every one of the ${ c.total } calls carries a class`,
	c.total === c.prepared + c.literal + c.tableOnly + c.cast + c.builder + c.reviewed + c.findings,
	`${ c.prepared }+${ c.literal }+${ c.tableOnly }+${ c.cast }+${ c.builder }+${ c.reviewed }+${ c.findings } against ${ c.total }`
);


/* -------------------------------------------------- the helpers it leans on */

/*
 *  vergeml_ids() is named in the proven list, which means the tool treats an
 *  implode of it as a list of integers wherever it appears. Asserted here rather
 *  than believed: a refactor that drops the absint makes several hundred proofs
 *  above wrong in one commit, silently.
 */
const idsSrc = ( () => {
	for ( const rel of [ 'core/rest-folders.php', 'core/rest-tree.php', 'core/utilities.php' ] ) {
		const full = path.join( ROOT, rel );
		if ( ! fs.existsSync( full ) ) continue;
		const text = fs.readFileSync( full, 'utf8' );
		const at = text.search( /function\s+vergeml_ids\s*\(/ );
		if ( at < 0 ) continue;
		/*
		 *  To the closing brace in column one, and not a character further. A fixed
		 *  600-character slice was the first version, and 600 characters overran the
		 *  function by half: the absint() it then found belonged to the *next*
		 *  function, so removing the cast from this one left the row green. A
		 *  mutation check is the only reason that was ever noticed.
		 */
		const close = text.indexOf( '\n}', at );
		return text.slice( at, close < 0 ? text.length : close + 2 );
	}
	return null;
} )();

check( 'vergeml_ids() is where this suite expects to find it', null !== idsSrc, 'not found in the three files searched' );

check(
	'vergeml_ids() still casts every id',
	!! idsSrc && /absint|intval|\(\s*int\s*\)/.test( idsSrc ),
	'no cast in its body'
);


/* ------------------------------------------------------------------- the floor */

/*
 *  Floors, not equalities: new queries are ordinary work. A count that falls is
 *  a call the reader stopped seeing, which is the direction worth guarding.
 */
const FLOOR = { total: 202, prepared: 109, tableOnly: 52, builder: 21 };

for ( const [ key, least ] of Object.entries( FLOOR ) ) {
	check( `${ key }: ${ c[ key ] } found, never fewer than ${ least }`, c[ key ] >= least, `found ${ c[ key ] }` );
}

/*
 *  The reviewed list is a budget, not a floor. Eleven calls are read by hand; a
 *  twelfth means a new query went in that the prover could not follow and
 *  somebody wrote a sentence instead of making it provable. That is sometimes the
 *  right answer, and it should take a deliberate edit to this number to say so.
 */
check(
	`no more than 11 calls are read by hand rather than proven (${ c.reviewed })`,
	c.reviewed <= 11,
	`${ c.reviewed } are`
);

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
