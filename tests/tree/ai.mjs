/*
 *  The AI screen and its effects, end to end with the mock provider.
 *
 *      node tests/tree/ai.mjs http://46.225.66.194 admin VgmlTest7pass
 */
import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://46.225.66.194';
const USER = process.argv[ 3 ] ?? process.env.UI_USER ?? 'admin';
const PASS = process.argv[ 4 ] ?? process.env.UI_PASS ?? 'VgmlTest7pass';

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? `  -- ${ detail }` : '' }` );
};

const browser = await chromium.launch();
const page = await ( await browser.newContext( { viewport: { width: 1500, height: 900 } } ) ).newPage();
page.setDefaultTimeout( 90000 );

const errors = [];
page.on( 'pageerror', ( e ) => {
	if ( ! e.message.includes( 'isImageFile' ) ) {
		errors.push( e.message.slice( 0, 120 ) );
	}
} );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 2000 );

/* --- the screen -------------------------------------------------------------- */

console.log( '\nthe AI screen' );

await page.goto( `${ BASE }/wp-admin/admin.php?page=media-ai`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '#vgml-ai-run', { timeout: 30000 } );
await page.waitForTimeout( 1500 );

check( 'the page renders with both actions', await page.evaluate( () =>
	!! document.getElementById( 'vgml-ai-run' ) && !! document.getElementById( 'vgml-ai-alt' ) ) );

const counts = await page.evaluate( () => document.getElementById( 'vgml-ai-counts' ).textContent );
check( 'status counts load', /\d+ pictures/.test( counts ), counts );

// mock on, through the settings endpoint (no key needed, nothing spent).
// The two flags go back to what they were at the end: a run that left mock
// on turned every later describe on the box into a mock one, and a library
// of a thousand real descriptions into a thousand hashes (2026-09-11).
const settingsBefore = await page.evaluate( async () =>
	( await window.wp.apiFetch( { path: '/vergeml/v1/ai-status' } ) ).settings );
await page.evaluate( async () => {
	await window.wp.apiFetch( { path: '/vergeml/v1/ai-settings', method: 'POST', data: { mock: 1, enrich_search: 1 } } );
} );

/* --- the describe loop -------------------------------------------------------- */

console.log( '\nthe describe loop' );

const before = await page.evaluate( async () =>
	await window.wp.apiFetch( { path: '/vergeml/v1/ai-status' } ) );

await page.click( '#vgml-ai-run' );

// the loop is done when the note says so. run() clears the note and only
// finish() writes it, so a non-empty note is the end of the run whichever
// line it ends on; the completed line is "N pictures described · K failed ·
// HH:MM" (js/vergeml-ai.js), and a stop, a stall or an error reads otherwise.
await page.waitForFunction( () =>
	document.getElementById( 'vgml-ai-note' ).textContent.trim() !== '', null, { timeout: 180000 } );

const note = await page.evaluate( () => document.getElementById( 'vgml-ai-note' ).textContent.trim() );
check( 'the run finished rather than stopping or failing', /^[\d.,]+ pictures? described · [\d.,]+ failed · \S+/.test( note ), note );

const after = await page.evaluate( async () =>
	await window.wp.apiFetch( { path: '/vergeml/v1/ai-status' } ) );

check( 'every image got described', after.unindexed === 0, `${ before.unindexed } -> ${ after.unindexed }` );
check( 'descriptions were stored', after.indexed >= before.indexed && after.indexed > 0, `${ after.indexed } indexed` );
// "Describe new images" only touches the pictures with no description, and
// each of those gets its alt text on the way. The rest of the library is not
// this run's to fill -- on the reset fixture 997 described pictures carry no
// alt text, and that number is not a failure of the describe loop.
check( 'missing alt text was filled on the way, for every picture described', after.missing_alt === before.missing_alt - before.unindexed, `${ before.missing_alt } -> ${ after.missing_alt }, ${ before.unindexed } described` );
check( 'the log shows captions', await page.evaluate( () =>
	document.querySelectorAll( '#vgml-ai-log li' ).length > 0 ) );

/* --- search finds what pictures show ------------------------------------------ */

console.log( '\nsearch' );

await page.goto( `${ BASE }/wp-admin/upload.php?mode=grid`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );
await page.waitForTimeout( 2000 );
await page.locator( '.vgml-tree .vgml-node[data-id="0"] .vgml-row' ).click();
await page.waitForTimeout( 1500 );

// mock captions all start with "Mock caption describing" -- a word from that
// phrase appears in no filename, so a hit proves the caption matched. The
// answer is read from the query the search sends, not from the tiles after a
// fixed sleep: the grid clears while it re-queries, and on a thousand
// pictures the re-query outlasts any sleep short enough to be a test.
const [ searched ] = await Promise.all( [
	page.waitForResponse( ( r ) =>
		r.url().includes( 'admin-ajax.php' ) && /query-attachments/.test( r.request().postData() || '' ) && /describing/.test( r.request().postData() || '' ), { timeout: 60000 } ),
	page.fill( '.media-toolbar .search', 'describing' ),
] );
const found = await searched.json();
const hits = Array.isArray( found.data ) ? found.data.length : 0;
check( 'a caption-only word fills the grid', hits > 0, `${ hits } results` );
await page.waitForFunction( () =>
	document.querySelectorAll( '.attachments-browser .attachments .attachment' ).length > 0, null, { timeout: 30000 } );

/* --- the smart folder agrees --------------------------------------------------- */

console.log( '\nthe smart folder' );

await page.reload( { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );
await page.waitForTimeout( 2000 );

const smartBadge = await page.evaluate( () => {
	const row = [ ...document.querySelectorAll( '.vgml-tree .vgml-name' ) ]
		.find( ( n ) => n.textContent.trim() === 'Missing alt text' );
	if ( ! row ) return null;
	const badge = row.closest( '.vgml-row' ).querySelector( '.vgml-count, .vgml-smart-scan' );
	return badge ? badge.textContent.trim() : '0';
} );
// the folder's badge and the status endpoint count the same pictures: an
// empty or absent badge is a zero, anything else is the number.
const badgeCount = null === smartBadge || '' === smartBadge ? 0 : Number( smartBadge.replace( /[^\d]/g, '' ) );
check( 'Missing alt text agrees with the status', badgeCount === after.missing_alt, `badge ${ String( smartBadge ) }, status ${ after.missing_alt }` );

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

await page.evaluate( async ( was ) => {
	await window.wp.apiFetch( { path: '/vergeml/v1/ai-settings', method: 'POST', data: { mock: was.mock, enrich_search: was.enrich_search } } );
}, settingsBefore );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
