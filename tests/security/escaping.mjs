/*
 *  Nothing untrusted reaches an HTML sink unaccounted for.
 *
 *      node tests/security/escaping.mjs
 *
 *  Phase 5.4 of plans/four-yesses.md. Plugin Check reports zero unescaped-output
 *  errors on the clean tree, and that is a PHP answer to a question this plugin
 *  asks mostly in JavaScript: a folder name does not reach the tree, the modal or
 *  the list table through PHP, it arrives as JSON and is put into the page by a
 *  script, where esc_html() does not exist and nothing warns anybody.
 *
 *  So this asserts both sides, and asserts the safe idiom as well as the absence
 *  of the unsafe one:
 *
 *    - every HTML sink in the JavaScript is given a literal, or carries a written
 *      reason keyed to a hash of what it is given.
 *    - every PHP output either escapes, casts, prints through something that
 *      escapes inside itself, or carries the same kind of reason.
 *    - the text sinks vastly outnumber the HTML ones, which is the shape of a
 *      codebase that builds rows with textContent. A fall there is the
 *      regression worth catching early.
 *
 *  Nothing here reaches a site, a box or a model. It reads source as text.
 *
 *  ## Mutation checks
 *
 *  Run on 2026-09-10, each red at the row named and green again once reverted:
 *
 *    1. `mark.innerHTML = entry.node.name` added to js/vergeml-tree.js
 *       → "every JavaScript HTML sink is given a literal or carries a written
 *         reason" FAILs, naming the line and the expression.
 *    2. the folder-name heading changed from `h1.textContent =` to
 *       `h1.innerHTML =` → the same row FAILs, naming
 *       `sprintf( l10n.dropInto, node.name )`. This is the exact stored-XSS shape
 *       the plan describes — a folder name into markup — so it is the mutation
 *       that matters most here, and it is caught by a one-character edit.
 *    3. esc_html() removed from vergeml_pg_head()'s title
 *       → "vergeml_pg_head() still escapes $title" FAILs.
 *       *First attempt passed.* The check asked whether there was an escaper
 *       anywhere in the body, and the esc_html() around the lede two lines below
 *       answered yes. It now names each argument.
 *    4. a reviewed fingerprint changed to one nothing has
 *       → "every reason still matches a sink" FAILs, and the sink it used to
 *         cover reappears as unread.
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const ROOT = path.resolve( HERE, '..', '..' );
const TOOL = path.join( ROOT, 'tools', 'escaping.mjs' );

const results = [];

function check( what, ok, detail ) {
	results.push( { ok, what } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' }  ${ what }${ ok || ! detail ? '' : `  -- ${ detail }` }` );
}

console.log( '\nmarkup built from data we did not write\n' );

let drift = null;

try {
	execFileSync( process.execPath, [ TOOL, '--check' ], { cwd: ROOT, stdio: 'pipe' } );
} catch ( e ) {
	drift = ( String( e.stdout ?? '' ) + String( e.stderr ?? '' ) ).trim();
}

check( 'docs/security-escaping.md matches the code — run node tools/escaping.mjs if not', null === drift, drift ?? '' );

const model = JSON.parse( execFileSync( process.execPath, [ TOOL, '--json' ], { cwd: ROOT, maxBuffer: 64 * 1024 * 1024 } ).toString() );
const c = model.counted;


/* ------------------------------------------------------------- the two sides */

const jsUnread = model.js.filter( ( e ) => ! e.safe && ! e.reviewed );

check(
	'every JavaScript HTML sink is given a literal or carries a written reason',
	0 === jsUnread.length,
	jsUnread.map( ( e ) => `${ e.rel }:${ e.line } ${ e.sink } ← ${ e.expr.slice( 0, 60 ) }` ).join( ' | ' )
);

const phpUnread = model.php.filter( ( e ) => ! e.safe && ! e.reviewed );

check(
	'every PHP output escapes, casts, prints through an escaper, or carries a written reason',
	0 === phpUnread.length,
	phpUnread.map( ( e ) => `${ e.rel }:${ e.line } ← ${ e.expr.slice( 0, 60 ) }` ).join( ' | ' )
);

/*
 *  A reason that matches nothing is a reason that has stopped being checked: the
 *  expression it described was rewritten, and the next edit to that line has one
 *  fewer thing watching it.
 */
const stale = [];

for ( const [ rel, entries ] of Object.entries( model.reviewed ) ) {
	for ( const [ hash ] of entries ) {
		const used = [ ...model.js, ...model.php ].some( ( e ) => e.rel === rel && ! e.safe && e.fingerprint === hash );
		if ( ! used ) stale.push( `${ rel }: ${ hash }` );
	}
}

check( 'every reason still matches a sink', 0 === stale.length, stale.join( ' | ' ) );


/* ---------------------------------------------------- the safe idiom, asserted */

/*
 *  219 text sinks against 48 HTML ones is the shape of a codebase that builds its
 *  rows with textContent. The ratio is the real invariant -- a commit that starts
 *  assembling rows as HTML strings would move it long before any single sink
 *  looked wrong on its own.
 */
check(
	`text sinks still outnumber HTML sinks four to one (${ c.textSinks } against ${ c.jsSinks })`,
	c.textSinks >= c.jsSinks * 4,
	`${ c.textSinks } text, ${ c.jsSinks } HTML`
);

check( `at least 48 HTML sinks are still being read (${ c.jsSinks })`, c.jsSinks >= 48, `found ${ c.jsSinks }` );
check( `at least 179 PHP outputs are still being read (${ c.phpSinks })`, c.phpSinks >= 179, `found ${ c.phpSinks }` );


/* ------------------------------------ the printers the report takes on trust */

/*
 *  The report excuses an output because it goes through one of these. Each was
 *  read once; this is what keeps that reading true. vergeml_pg_head() losing its
 *  esc_html() would silently excuse every screen title in the plugin.
 */
/*
 *  Each argument is named, not just the function. "Is there an escaper in this
 *  body" was the first version of this check, and it passed after esc_html() was
 *  stripped from vergeml_pg_head()'s title, because the esc_html() around its
 *  lede two lines below still matched. A printer that escapes one of its two
 *  arguments is not a printer that escapes.
 */
const PRINTERS = {
	'core/admin-shell.php': [
		[ 'vergeml_pg_head', [ '$title', '$lede' ] ],
		[ 'vergeml_pg_card_open', [ '$title' ] ],
	],
};

for ( const [ rel, names ] of Object.entries( PRINTERS ) ) {

	const text = fs.readFileSync( path.join( ROOT, rel ), 'utf8' );

	for ( const [ name, params ] of names ) {

		const at = text.search( new RegExp( `function\\s+${ name }\\s*\\(` ) );
		const close = at < 0 ? -1 : text.indexOf( '\n}', at );
		const body = at < 0 ? '' : text.slice( at, close < 0 ? text.length : close );

		for ( const param of params ) {
			const escaped = new RegExp( `esc_(html|attr|url|textarea)\\s*\\(\\s*\\${ param }\\b` );
			check(
				`${ name }() still escapes ${ param }`,
				at >= 0 && escaped.test( body ),
				at < 0 ? 'the function was not found' : `no esc_*( ${ param } ) in its body`
			);
		}
	}
}

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
