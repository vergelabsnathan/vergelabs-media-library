/*
 *  The import screen, clicked rather than called.
 *
 *      node tests/tree/import-ui.mjs http://185.229.224.239 admin VgmlTest7pass
 *
 *  tests/tree/import.php proves the engine. This proves the thing somebody
 *  actually uses: that the screen finds the other plugin, says what it will do
 *  before doing it, survives sixteen thousand files without timing out, and can
 *  take it back.
 *
 *  It runs against the real FileBird tables on the test box, so the numbers on
 *  screen are numbers from another plugin's data.
 */

import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://185.229.224.239';
const USER = process.argv[ 3 ] ?? 'admin';
const PASS = process.argv[ 4 ] ?? 'VgmlTest7pass';

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? '  -- ' + detail : '' }` );
};

const browser = await chromium.launch();
const ctx = await browser.newContext( { viewport: { width: 1500, height: 950 } } );
const page = await ctx.newPage();
page.setDefaultTimeout( 120000 );

const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 2500 );

const SCREEN = `${ BASE }/wp-admin/options-general.php?page=media-import-folders`;

await page.goto( SCREEN, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-import-card', { timeout: 60000 } );

/*
 *  Undo anything still imported before starting.
 *
 *  An import that has already happened is correctly a no-op the second time --
 *  nothing new to create, nothing new to file -- so a run that inherits one from
 *  a previous run reports zero of everything and the assertions read that as a
 *  broken importer. The test has to make its own work.
 */
const cleared = await page.evaluate( async () => {
	const call = ( data ) => window.wp.apiFetch( { path: '/vergeml/v1/import', method: 'POST', data } );
	const { history } = await call( { action: 'history' } );
	let undone = 0;

	for ( const entry of history || [] ) {
		let r = await call( { action: 'undo', id: entry.id } );
		let guard = 0;
		while ( ! r.complete && r.resume && guard++ < 5000 ) {
			r = await call( { action: 'undo', id: entry.id, resume: r.resume } );
		}
		undone++;
	}
	return undone;
} );

if ( cleared ) {
	console.log( `  (undid ${ cleared } earlier import${ cleared === 1 ? '' : 's' } first)` );
	await page.reload( { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '.vgml-import-card', { timeout: 60000 } );
}

/* --- what it found ------------------------------------------------------ */

console.log( '\nwhat it found' );

const found = await page.evaluate( () =>
	[ ...document.querySelectorAll( '.vgml-import-card' ) ].map( ( c ) => ( {
		name: ( c.querySelector( 'h2' ) || {} ).textContent,
		summary: ( c.querySelector( '.vgml-import-found' ) || {} ).textContent,
	} ) ) );

const filebird = found.find( ( f ) => /FileBird/i.test( f.name || '' ) );
check( 'FileBird was detected', !! filebird, found.map( ( f ) => f.name ).join( ', ' ) );
check( 'it reports real numbers', !! filebird && /\d/.test( filebird.summary ), filebird ? filebird.summary : '' );

/* --- the preview -------------------------------------------------------- */

console.log( '\nthe preview' );

const beforeTerms = await page.evaluate( async () => {
	const t = await window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category' } );
	return ( t.nodes || [] ).length;
} );

await page.click( '.vgml-import-card:has(h2:text-is("FileBird")) button.button-primary' );
await page.waitForSelector( '.vgml-import-plan', { timeout: 60000 } );

const planText = await page.textContent( '.vgml-import-plan-line' );
check( 'it says what it will do', !! planText && /\d/.test( planText ), planText.trim() );

const afterPreview = await page.evaluate( async () => {
	const t = await window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category' } );
	return ( t.nodes || [] ).length;
} );
check( 'the preview changed nothing', afterPreview === beforeTerms, `${ beforeTerms } -> ${ afterPreview }` );

/* --- the import --------------------------------------------------------- */

console.log( '\nthe import' );

const t0 = Date.now();
await page.click( '.vgml-import-plan button.button-primary' );

/*
 *  The bar has to actually move, or the chunk loop is not running.
 *
 *  Sampled on the bar's own value rather than after a fixed wait: the first pass
 *  creates every folder before it files anything, so how long it takes to reach
 *  a first percentage is a property of the data, not something to guess at.
 */
await page.waitForSelector( '.vgml-import-bar', { timeout: 30000 } );

const width = () => page.evaluate( () => {
	const fill = document.querySelector( '.vgml-import-fill' );
	return fill ? parseInt( fill.style.width, 10 ) || 0 : null;
} );

await page.waitForFunction( () => {
	const fill = document.querySelector( '.vgml-import-fill' );
	return fill && parseInt( fill.style.width, 10 ) > 0;
}, null, { timeout: 60000 } );

const first = await width();
await page.waitForFunction( ( w ) => {
	const fill = document.querySelector( '.vgml-import-fill' );
	return ! fill || parseInt( fill.style.width, 10 ) > w;
}, first, { timeout: 60000 } );
const second = await width();

check( 'progress is real, not a spinner', first > 0 && ( second === null || second > first ),
	`${ first }% -> ${ second === null ? 'done' : second + '%' }` );

await page.waitForSelector( '.vgml-import-good', { timeout: 180000 } );
const took = Math.round( ( Date.now() - t0 ) / 1000 );

check( 'the import finished', true, `${ took }s` );

/*
 *  The numbers in the result have to add up to the folders that were found.
 *  They did not: folders the import created came back counted a second time as
 *  "merged into folders you already have" on every pass after the first, so a
 *  200-folder import reported 399 folders.
 */
const resultLine = ( await page.textContent( '.vgml-import-good + .description, .notice .description' ) || '' ).trim();
const nums = resultLine.replace( /,/g, '' ).match( /\d+/g ) || [];
const createdN = Number( nums[ 0 ] || 0 );
const mergedN = /merged/.test( resultLine ) ? Number( nums[ 1 ] || 0 ) : 0;
check( 'the result adds up', createdN + mergedN === 200, `${ createdN } + ${ mergedN } | ${ resultLine }` );

const imported = await page.evaluate( async () => {
	const t = await window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category' } );
	return ( t.nodes || [] ).length;
} );
check( 'folders arrived', imported > beforeTerms, `${ beforeTerms } -> ${ imported }` );

/* --- undo --------------------------------------------------------------- */

console.log( '\nundo' );

await page.waitForSelector( '.vgml-import-history', { timeout: 30000 } );
const historyRows = await page.locator( '.vgml-import-history' ).count();
check( 'the import is listed in history', historyRows > 0, `${ historyRows } listed` );

/*
 *  Wait for the history row to go, not for a success marker: the import's own
 *  "Imported." notice is still on screen at this point, so waiting for
 *  .vgml-import-good matches instantly and proves nothing about the undo.
 */
const u0 = Date.now();
await page.click( '.vgml-import-history button' );
await page.waitForFunction(
	( n ) => document.querySelectorAll( '.vgml-import-history' ).length < n,
	historyRows,
	{ timeout: 180000 }
);
check( 'undo finished', true, `${ Math.round( ( Date.now() - u0 ) / 1000 ) }s` );

const afterUndo = await page.evaluate( async () => {
	const t = await window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category' } );
	return ( t.nodes || [] ).length;
} );
check( 'undo removed what it added', afterUndo === beforeTerms, `${ imported } -> ${ afterUndo }, expected ${ beforeTerms }` );

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
