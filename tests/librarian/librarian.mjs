/*
 *  The Librarian screen, end to end, in Playground.
 *
 *      node tests/librarian/librarian.mjs http://127.0.0.1:8899
 *
 *  Playground and not the box, deliberately. This suite applies a batch and
 *  then undoes it, which means it creates folders in a real taxonomy and
 *  assigns real files to them -- fine on a library that is thrown away when
 *  the process exits, and not something to do to the box the other suites
 *  measure against.
 *
 *  It drives the date scheme rather than the subject one. The subject tree
 *  needs a finished organise run, which needs embeddings, which needs the AI
 *  service; the date scheme needs nothing but post_date, so the whole review
 *  -> apply -> undo path can be exercised on a library that has never been
 *  described. What it proves is the machinery, and the machinery is the same
 *  for both.
 *
 *  Use 127.0.0.1, never localhost: WordPress builds its own URLs from siteurl
 *  and the other name fails every nonce.
 */
import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://127.0.0.1:8899';

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? `  -- ${ detail }` : '' }` );
};

const browser = await chromium.launch();
const page = await ( await browser.newContext( { viewport: { width: 1500, height: 1000 } } ) ).newPage();
page.setDefaultTimeout( 90000 );

const errors = [];
page.on( 'pageerror', ( e ) => {
	if ( ! e.message.includes( 'isImageFile' ) ) {
		errors.push( e.message.slice( 0, 140 ) );
	}
} );

// Playground's blueprint carries `login: true`, so the first admin request
// arrives signed in and there is no form to fill.
await page.goto( `${ BASE }/wp-admin/admin.php?page=media-librarian`, { waitUntil: 'domcontentloaded' } );

/*
 *  Waited for, never slept for. The screen asks three endpoints before it
 *  knows which rung to draw, and PHP-wasm spends seconds booting WordPress on
 *  every one of them -- so a fixed pause here is a suite that passes on a
 *  fast machine and reports the screen empty on a slow one.
 */
const settled = () => page.waitForSelector(
	'#vgml-lib-stage .vgml-lib-rung, #vgml-lib-stage .vgml-lib-scheme',
	{ timeout: 120000 },
);

await settled();

const call = ( path, options ) =>
	page.evaluate(
		async ( [ p, o ] ) =>
			await window.wp.apiFetch( {
				path: p,
				method: o.method || 'GET',
				data: o.data,
			} ).catch( ( e ) => ( { error: e.message || String( e ) } ) ),
		[ path, options || {} ],
	);

/* --- the page and the ladder ------------------------------------------------- */

console.log( '\nthe screen' );

check( 'the page renders its three regions', await page.evaluate( () =>
	!! document.getElementById( 'vgml-lib-stage' ) &&
	!! document.getElementById( 'vgml-lib-review' ) &&
	!! document.getElementById( 'vgml-lib-history' ) ) );

/*
 *  Whichever rung this library is on, it must be one that can be climbed from
 *  here. A screen that said "run the duplicate scan first" and stopped is the
 *  dead end this state machine exists to avoid, and the failure would look
 *  identical at any depth of the ladder -- so the assertion is about the rung
 *  being actionable rather than about which rung it happens to be.
 *
 *  Which rung applies is a property of the library, and this suite is run
 *  more than once against the same Playground. The refusal path that only
 *  exists on a never-scanned library is asserted in the PHP suite, where the
 *  state can be set rather than hoped for.
 */
const rung = await page.evaluate( () => {
	const card = document.querySelector( '#vgml-lib-stage .vgml-lib-rung h2' );
	return card ? card.textContent.trim() : '(the chooser)';
} );

check( 'the ladder opens on a rung that can be climbed here', rung.length > 0, rung );

check( 'the rung offers its own action rather than pointing elsewhere',
	await page.evaluate( () =>
		!! document.querySelector( '#vgml-lib-stage .vgml-lib-rung button' ) ||
		!! document.querySelector( '#vgml-lib-stage .vgml-lib-scheme button' ) ) );

/* --- the schemes endpoint ---------------------------------------------------- */

console.log( '\nthe schemes' );

const schemes = await call( '/vergeml/v1/librarian-schemes' );

check( 'both schemes are offered', Array.isArray( schemes.schemes ) && schemes.schemes.length === 2,
	( schemes.schemes || [] ).map( ( s ) => s.id ).join( ', ' ) );

const dateScheme = ( schemes.schemes || [] ).find( ( s ) => s.id === 'datetype' );

check( 'the date scheme is ready without an AI index', dateScheme && dateScheme.ready === true );

check( 'the subject scheme says it is not ready rather than pretending',
	( schemes.schemes || [] ).find( ( s ) => s.id === 'subject' )?.ready === false );

const full = await call( '/vergeml/v1/librarian-schemes?scheme=datetype' );

check( 'naming a scheme returns its whole tree', Array.isArray( full.tree ) && full.tree.length > 0,
	`${ ( full.tree || [] ).length } branches` );

const branchesWithMembers = ( full.tree || [] ).filter( ( b ) => b.members && b.members.length );

check( 'every branch carries members with a reason',
	branchesWithMembers.length > 0 && branchesWithMembers.every( ( b ) =>
		b.members.every( ( m ) => typeof m.why === 'string' && m.why.length > 0 ) ) );

/* --- the first rung ----------------------------------------------------------- */

console.log( '\nclimbing the first rung' );

// Driven through the endpoint rather than the button, so the suite does not
// depend on which rung the screen happened to be showing when it loaded.
await page.evaluate( async () => {
	let cursor = 0;
	let reset = true;
	for ( let i = 0; i < 400; i++ ) {
		const r = await window.wp.apiFetch( {
			path: '/vergeml/v1/health-scan',
			method: 'POST',
			data: { cursor: cursor, reset: reset },
		} );
		reset = false;
		cursor = r.cursor;
		if ( r.done ) {
			return r;
		}
	}
} );

await page.reload( { waitUntil: 'domcontentloaded' } );
await settled();

const rung2 = await page.evaluate( () => {
	const card = document.querySelector( '#vgml-lib-stage .vgml-lib-rung h2' );
	return card ? card.textContent.trim() : '(the chooser)';
} );

/*
 *  Whatever rung it was on, it is past the scan now -- and the screen has to
 *  say so. A ladder that kept offering a step somebody has just completed is
 *  a ladder nobody gets off.
 */
check( 'the scan rung is behind us once the scan has run',
	rung2 !== 'Read the library first', `${ rung } -> ${ rung2 }` );

check( 'and the date scheme is now offered without climbing further',
	await page.evaluate( () => !! document.querySelector( '#vgml-lib-stage .vgml-lib-skip button' ) ) );

/* --- something to file -------------------------------------------------------- */

console.log( '\nsomething to file' );

/*
 *  Playground's blueprint files all eight sample images, and a library with
 *  nothing unfiled is a library Apply is right to do nothing to. So the suite
 *  makes the state it needs: half the files are taken out of their folders
 *  through the plugin's own assign endpoint, which is exactly how a person
 *  would empty them.
 *
 *  Undo puts the library back to this state, not to the blueprint's -- which
 *  is why the snapshot the apply section compares against is taken after it.
 */
const media = await call( '/wp/v2/media?per_page=100&_fields=id' );

const toEmpty = ( media || [] ).map( ( m ) => m.id ).slice( 0, 4 );

const emptied = await call( '/vergeml/v1/assign', {
	method: 'POST',
	data: {
		taxonomy: schemes.taxonomy,
		attachments: toEmpty,
		add: [],
		mode: 'move',
	},
} );

check( 'files can be emptied out of their folders to make work',
	! emptied.error && toEmpty.length === 4, emptied.error || `${ toEmpty.length } unfiled` );

/* --- the review -------------------------------------------------------------- */

console.log( '\nthe review' );

await page.evaluate( () => {
	document.querySelector( '#vgml-lib-stage .vgml-lib-skip button' ).click();
} );

await page.waitForSelector( '#vgml-lib-review .vgml-lib-branch', { timeout: 120000 } );
await page.waitForFunction(
	() => document.querySelectorAll( '#vgml-lib-review .vgml-lib-thumb img' ).length > 0,
	null,
	{ timeout: 120000 },
);

const cards = await page.evaluate( () =>
	[ ...document.querySelectorAll( '#vgml-lib-review .vgml-lib-branch' ) ].map( ( node ) => ( {
		label: node.querySelector( '.vgml-lib-branch-name' ).value,
		size: node.querySelector( '.vgml-lib-branch-size' ).textContent,
		checked: node.querySelector( 'input[type=checkbox]' ).checked,
		thumbs: node.querySelectorAll( '.vgml-lib-thumb' ).length,
		painted: node.querySelectorAll( '.vgml-lib-thumb img' ).length,
		uncertain: node.className.indexOf( 'is-uncertain' ) >= 0,
	} ) ) );

check( 'a card per branch', cards.length === branchesWithMembers.length,
	`${ cards.length } cards for ${ branchesWithMembers.length } branches` );

check( 'each card names its folder and its count',
	cards.every( ( c ) => c.label.length > 0 && /\d/.test( c.size ) ),
	cards.map( ( c ) => `${ c.label } (${ c.size })` ).join( ' · ' ) );

check( 'the folder name is editable in place',
	await page.evaluate( () => {
		const input = document.querySelector( '#vgml-lib-review .vgml-lib-branch-name' );
		return input && 'INPUT' === input.tagName && ! input.disabled;
	} ) );

check( 'unflagged branches start checked',
	cards.filter( ( c ) => ! c.uncertain ).every( ( c ) => c.checked ) );

check( 'thumbs are drawn for each branch', cards.every( ( c ) => c.thumbs > 0 ),
	cards.map( ( c ) => c.thumbs ).join( ',' ) );

check( 'and at least some of them loaded a real image',
	cards.some( ( c ) => c.painted > 0 ),
	`${ cards.reduce( ( n, c ) => n + c.painted, 0 ) } painted` );

/*
 *  The thumbs must cost one request per hundred ids, not one per branch. This
 *  is the N+1 the server-side query budgets exist to prevent, and moving it
 *  into the browser would not make it cheaper.
 */
const mediaCalls = [];
page.on( 'request', ( req ) => {
	if ( req.url().includes( '/wp/v2/media' ) && req.url().includes( 'include=' ) ) {
		mediaCalls.push( req.url() );
	}
} );

await page.evaluate( () => {
	document.querySelector( '#vgml-lib-review .vgml-lib-branch input[type=checkbox]' ).click();
	document.querySelector( '#vgml-lib-review .vgml-lib-branch input[type=checkbox]' ).click();
} );
await page.waitForTimeout( 1500 );

check( 'redrawing does not fetch thumbs per branch', mediaCalls.length <= 2,
	`${ mediaCalls.length } batched media requests` );

/* --- the pre-flight ---------------------------------------------------------- */

console.log( '\nthe pre-flight' );

await page.waitForSelector( '#vgml-lib-preflight .vgml-lib-apply', { timeout: 120000 } );

check( 'the panel offers Apply once it has something to count',
	await page.evaluate( () => {
		const go = document.querySelector( '#vgml-lib-preflight .vgml-lib-apply' );
		return !! go && ! go.disabled;
	} ) );

check( 'and says what applying would cost, honestly',
	await page.evaluate( () =>
		document.querySelector( '#vgml-lib-preflight .vgml-lib-credits' ).textContent.indexOf( 'mock' ) >= 0 ) );

const counted = await call( '/vergeml/v1/librarian-preflight', {
	method: 'POST',
	data: { scheme: 'datetype', run_id: 0, branches: [] },
} );

check( 'the pre-flight counts once the scan has run',
	! counted.error && typeof counted.unfiled === 'number',
	counted.error || `${ counted.unfiled } to file, ${ counted.filed } already filed` );

check( 'it counts folders to create and folders to reuse',
	! counted.error && counted.folders && typeof counted.folders.create === 'number',
	counted.error ? '' : `${ counted.folders.create } created, ${ counted.folders.reuse } reused` );

check( 'credits are 0 and say they are mock',
	! counted.error && counted.credits.cost === 0 && counted.credits.mode === 'mock' );

/* --- apply ------------------------------------------------------------------- */

console.log( '\napply' );

const taxonomy = schemes.taxonomy;

const before = await call( `/vergeml/v1/tree?taxonomy=${ taxonomy }` );

const chosen = ( full.tree || [] )
	.filter( ( b ) => b.members && b.members.length && b.key !== 'needs-a-look' && ! b.capped )
	.map( ( b ) => ( { key: b.key, label: b.label, enabled: true } ) );

// Asked with exactly the branches Apply is about to be given, so the promise
// and the act are the same question.
const promised = await call( '/vergeml/v1/librarian-preflight', {
	method: 'POST',
	data: { scheme: 'datetype', run_id: 0, branches: chosen },
} );

let apply = await call( '/vergeml/v1/librarian-apply-step', {
	method: 'POST',
	data: { scheme: 'datetype', run_id: 0, branches: chosen },
} );

check( 'a batch is created', ! apply.error && apply.batch_id > 0, apply.error || `batch ${ apply.batch_id }` );

const batchId = apply.batch_id;
let steps = 0;

while ( ! apply.error && apply.status === 'running' && steps < 200 ) {
	apply = await call( '/vergeml/v1/librarian-apply-step', {
		method: 'POST',
		data: { batch_id: batchId },
	} );
	steps++;
}

check( 'the batch finishes', ! apply.error && apply.status === 'done',
	apply.error || `${ apply.status } after ${ steps } steps, ${ apply.done } filed, ${ apply.skipped } skipped` );

const after = await call( `/vergeml/v1/tree?taxonomy=${ taxonomy }` );

check( 'exactly the folders the pre-flight promised appeared',
	( after.nodes || [] ).length - ( before.nodes || [] ).length === promised.folders.create,
	`${ ( before.nodes || [] ).length } -> ${ ( after.nodes || [] ).length }, ` +
		`${ promised.folders.create } promised, ${ promised.folders.reuse } reused` );

check( 'and the unfiled count fell by what was filed',
	before.unassigned - after.unassigned === apply.done,
	`${ before.unassigned } -> ${ after.unassigned }, ${ apply.done } filed` );

/*
 *  The counts on the folders have to be the counts in the proposal. A tree
 *  that showed the right folders with the wrong numbers in them would look
 *  correct and be useless.
 */
const expected = {};
chosen.forEach( ( c ) => {
	const branch = full.tree.find( ( b ) => b.key === c.key );
	const leaf = branch.path[ branch.path.length - 1 ];
	// Only the members that had no folder: the rest are skipped, which is the
	// behaviour, so counting them here would be asserting a bug.
	const filed = branch.members.filter( ( m ) => toEmpty.indexOf( m.id ) >= 0 ).length;
	expected[ leaf ] = ( expected[ leaf ] || 0 ) + filed;
} );

const mismatched = Object.keys( expected ).filter( ( name ) => {
	const node = ( after.nodes || [] ).find( ( n ) => n.name === name );
	return ! node || node.count !== expected[ name ];
} );

check( 'each folder holds what the proposal said it would', mismatched.length === 0,
	mismatched.length ? `wrong: ${ mismatched.join( ', ' ) }` : `${ Object.keys( expected ).length } folders checked` );

/* --- the history ------------------------------------------------------------- */

console.log( '\nthe history' );

await page.reload( { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '#vgml-lib-history .vgml-lib-batch', { timeout: 60000 } );

const row = await page.evaluate( () => {
	const node = document.querySelector( '#vgml-lib-history .vgml-lib-batch' );
	return {
		text: node.textContent,
		undo: !! node.querySelector( 'button' ),
	};
} );

check( 'the batch appears in the history', row.text.length > 0, row.text.slice( 0, 80 ) );
check( 'with an undo button on it', row.undo );

/* --- undo -------------------------------------------------------------------- */

console.log( '\nundo' );

let undo = await call( '/vergeml/v1/librarian-undo-step', {
	method: 'POST',
	data: { batch_id: batchId },
} );

steps = 0;

while ( ! undo.error && undo.status !== 'undone' && steps < 200 ) {
	undo = await call( '/vergeml/v1/librarian-undo-step', {
		method: 'POST',
		data: { batch_id: batchId },
	} );
	steps++;
}

check( 'undo completes', ! undo.error && undo.status === 'undone',
	undo.error || `${ undo.undone } unfiled, ${ undo.folders_removed } folders removed` );

const back = await call( `/vergeml/v1/tree?taxonomy=${ taxonomy }` );

check( 'the folders it created are gone again',
	( back.nodes || [] ).length === ( before.nodes || [] ).length,
	`${ ( after.nodes || [] ).length } -> ${ ( back.nodes || [] ).length }, was ${ ( before.nodes || [] ).length }` );

check( 'and every file is back where it was',
	back.unassigned === before.unassigned,
	`${ back.unassigned } unfiled, was ${ before.unassigned }` );

check( 'the report says what it did rather than only that it finished',
	typeof undo.skipped_touched === 'number' && typeof undo.folders_kept === 'number',
	`${ undo.skipped_touched } left alone, ${ undo.folders_kept } folders kept` );

/*
 *  The report is on the screen, not only in the payload. What undo did NOT do
 *  is the part worth reading, and a number that only exists in JSON is a
 *  number nobody sees.
 */
await page.reload( { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '#vgml-lib-history .vgml-lib-batch', { timeout: 60000 } );

const status = await page.evaluate( () =>
	document.querySelector( '#vgml-lib-history .vgml-lib-batch-status' ).textContent.trim() );

check( 'the history shows the batch as undone', status === 'undone', status );

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
