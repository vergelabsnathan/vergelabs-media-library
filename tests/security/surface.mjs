/*
 *  The surface list is the code's, not a memory of it.
 *
 *      node tests/security/surface.mjs
 *
 *  Phase 5.1 of plans/four-yesses.md asks for one table of every way into the
 *  plugin, "generated from the code rather than remembered, and regenerated in
 *  CI so it cannot drift". This is the regeneration. It runs
 *  tools/security-surface.mjs in --check mode and goes red when the committed
 *  docs/security-surface.md no longer matches what the source says -- which is
 *  what happens the first time somebody adds a route and does not run the tool.
 *
 *  It also holds the floor under the surface: the counts cannot silently fall
 *  (a route that stops being seen is a route that stops being audited), no
 *  entry point may be nopriv, and the two entries whose capability the reader
 *  could not find are named here by hand, so a third one turns this red rather
 *  than joining a column of dashes nobody reads.
 *
 *  Nothing here reaches a site, a box or a model. No PHP, no network, no cost.
 *
 *  Mutation checks run against this suite on 2026-09-10, all five red at the row
 *  named and green again once reverted:
 *    1. a route added to core/rest-folders.php without regenerating the doc
 *       → "docs/security-surface.md matches the code" FAILs.
 *    2. 'wp_ajax_vergeml_rest' changed to 'wp_ajax_nopriv_vergeml_rest'
 *       → "no entry point is open to visitors who are not logged in" FAILs.
 *    3. vergeml_can_manage_folders() made `return true`
 *       → "every REST endpoint asks a capability question" FAILs.
 *    4. the sanitize callback dropped from register_setting( 'mime-types', … )
 *       → "every registered setting names a sanitize callback" FAILs.
 *    5. the main menu's capability changed from manage_categories to read
 *       → "no admin screen is registered with a capability every logged-in user
 *         has" FAILs.
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const ROOT = path.resolve( HERE, '..', '..' );
const TOOL = path.join( ROOT, 'tools', 'security-surface.mjs' );
const DOC = path.join( ROOT, 'docs', 'security-surface.md' );

const results = [];

function check( what, ok, detail ) {
	results.push( { ok, what } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' }  ${ what }${ ok || ! detail ? '' : `  -- ${ detail }` }` );
}

console.log( '\nthe security surface, against the code it was read from\n' );


/* ------------------------------------------------- the doc is the code's */

let drift = null;

try {
	execFileSync( process.execPath, [ TOOL, '--check' ], { cwd: ROOT, stdio: 'pipe' } );
} catch ( e ) {
	drift = String( e.stdout ?? '' ) + String( e.stderr ?? '' );
}

check(
	'docs/security-surface.md matches the code — run node tools/security-surface.mjs if not',
	null === drift,
	( drift ?? '' ).trim()
);

check( 'docs/security-surface.md is committed', fs.existsSync( DOC ), 'the file is missing' );


/* ------------------------------------------------------- the floor beneath it */

const model = JSON.parse( execFileSync( process.execPath, [ TOOL, '--json' ], { cwd: ROOT, maxBuffer: 64 * 1024 * 1024 } ).toString() );
const c = model.counted;

/*
 *  Floors, not equalities. A new route is ordinary work; a route the generator
 *  stopped seeing is a hole in the audit, and that is the direction this guards.
 *  The numbers are the ones read on 2026-09-10.
 */
const FLOOR = {
	restEndpoints: 76,
	restRoutes: 69,
	ajax: 7,
	adminPost: 11,
	cron: 4,
	screens: 13,
	frontend: 2,
	settings: 7,
	metaRest: 2,
	inputHooks: 36,
};

for ( const [ key, least ] of Object.entries( FLOOR ) ) {
	check( `${ key }: ${ c[ key ] } found, never fewer than ${ least }`, c[ key ] >= least, `found ${ c[ key ] }` );
}

check(
	'no entry point is open to visitors who are not logged in',
	0 === c.ajaxNopriv && 0 === c.adminPostNopriv,
	`${ c.ajaxNopriv } nopriv AJAX, ${ c.adminPostNopriv } nopriv admin_post`
);


/* ----------------------------------------- every row answers the first question */

/*
 *  Two entries carry no capability of their own, and both are right to:
 *
 *    vergeml_rest            the transport bridge. It verifies the wp_rest nonce
 *                            and hands the request to rest_do_request(), so the
 *                            real route's permission callback decides. A
 *                            capability here would be a second, weaker gate.
 *    vergeml-admin-notice-dismiss  dismisses a notice for the caller, in the
 *                            caller's own user meta. Nonce-checked; there is no
 *                            object belonging to anyone else to reach.
 *
 *  Named, so a third arrival is a failure rather than a dash in a column.
 */
const CAPLESS_BY_DESIGN = new Set( [ 'vergeml_rest', 'vergeml-admin-notice-dismiss' ] );

const caplessRest = model.rest.filter( ( r ) => ! r.judged.caps.length );
check(
	'every REST endpoint asks a capability question',
	0 === caplessRest.length,
	caplessRest.map( ( r ) => `${ r.route } ${ r.methods } (${ r.rel }:${ r.line })` ).join( ', ' )
);

const caplessAction = [ ...model.ajax, ...model.adminPost ].filter( ( r ) => ! r.judged.caps.length );
check(
	'the only entries without a capability are the two that are right not to have one',
	caplessAction.every( ( r ) => CAPLESS_BY_DESIGN.has( r.action ) ),
	caplessAction.map( ( r ) => `${ r.action } (${ r.rel }:${ r.line })` ).join( ', ' )
);

/*
 *  A nonce answers "did they mean to", and that question is only worth asking of
 *  something that changes state. One entry changes nothing:
 *
 *    tb_load_editor   our callback on Themify's own ajax action, at priority 9.
 *                     It enqueues the tree's stylesheet and script behind
 *                     `upload_files` and returns. There is nothing to forge: the
 *                     worst a forged request achieves is that a logged-in
 *                     uploader's builder loads a stylesheet it already has.
 *
 *  Checked two ways round, so neither half can rot: everything that changes
 *  something has a nonce, and everything without a nonce changes nothing.
 */
const NONCELESS_BY_DESIGN = new Set( [ 'tb_load_editor' ] );

const actions = [ ...model.ajax, ...model.adminPost ];

const mutatesUnguarded = actions.filter( ( r ) => r.judged.mutates.length && ! r.judged.nonces.length );
check(
	'every AJAX action and admin_post handler that changes something checks a nonce',
	0 === mutatesUnguarded.length,
	mutatesUnguarded.map( ( r ) => `${ r.action } writes ${ r.judged.mutates.join( '/' ) } (${ r.rel }:${ r.line })` ).join( ', ' )
);

const noNonce = actions.filter( ( r ) => ! r.judged.nonces.length );
check(
	'the only entry without a nonce is the one that changes nothing',
	noNonce.every( ( r ) => NONCELESS_BY_DESIGN.has( r.action ) && 0 === r.judged.mutates.length ),
	noNonce.map( ( r ) => `${ r.action } (${ r.rel }:${ r.line })` ).join( ', ' )
);

/*
 *  A screen registered with a capability weaker than `upload_files` would put one
 *  of these pages in front of a subscriber. `read` is the capability every
 *  logged-in user has, and no screen of ours may be registered with it.
 */
const weakScreens = model.screens.filter( ( s ) => /^(read|exist)$/.test( s.cap ) );
check(
	'no admin screen is registered with a capability every logged-in user has',
	0 === weakScreens.length,
	weakScreens.map( ( s ) => `${ s.slug } (${ s.cap }, ${ s.rel }:${ s.line })` ).join( ', ' )
);

/*
 *  A setting saved through options.php with no sanitize callback is stored as it
 *  arrived. All seven name a validator; this is the guard against the eighth.
 */
const unsanitised = model.settings.filter( ( s ) => ! s.sanitize );
check(
	'every registered setting names a sanitize callback',
	0 === unsanitised.length,
	unsanitised.map( ( s ) => `${ s.option } (${ s.rel }:${ s.line })` ).join( ', ' )
);

/* ------------------------------ the role suite's expectation is still the code's */

/*
 *  tests/security/roles.php runs on the box with only itself shipped, so it has
 *  to carry its three expectation lists as PHP. Two copies of the same fact is
 *  how a suite ends up asserting last month's routes, so the PHP copy is parsed
 *  back out here and compared with what the permission callbacks say now. A
 *  route whose gate changes and whose list does not goes red locally, before
 *  anything is deployed.
 */
const ROLES_PHP = path.join( HERE, 'roles.php' );
const rolesSrc = fs.readFileSync( ROLES_PHP, 'utf8' );

/** One `$r_name = array( 'a', 'b' );` block, as a list of strings. */
function phpList( name ) {
	const at = rolesSrc.indexOf( `$${ name } = array(` );
	if ( at === -1 ) return null;
	const close = rolesSrc.indexOf( ');', at );
	return [ ...rolesSrc.slice( at, close ).matchAll( /'((?:[^'\\]|\\.)*)'/g ) ]
		.map( ( m ) => m[ 1 ].replace( /\\\\/g, '\\' ) )
		.sort();
}

for ( const [ phpName, modelKey ] of [
	[ 'r_author_may', 'authorMay' ],
	[ 'r_object_scoped', 'objectScoped' ],
	[ 'r_admin_only', 'adminOnly' ],
] ) {
	const have = phpList( phpName );
	const want = model.roleLists[ modelKey ];
	const same = have && have.length === want.length && have.every( ( v, i ) => v === want[ i ] );
	check(
		`roles.php $${ phpName } matches the ${ want.length } endpoints the gates actually describe`,
		same,
		have ? `PHP has ${ have.length }: only in PHP [${ have.filter( ( v ) => ! want.includes( v ) ) }], only in code [${ want.filter( ( v ) => ! have.includes( v ) ) }]` : 'the array was not found in roles.php'
	);
}

/*
 *  A cron hook runs with nobody logged in. A capability question inside one is
 *  answered by whatever user cron happens to be, which is nobody, so a feature
 *  that depends on one is a feature that quietly stops working.
 */
for ( const hook of model.cron ) {
	check( `cron hook ${ hook.hook } is answered by something`, hook.answers.length > 0, 'nothing is listening' );
}

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
