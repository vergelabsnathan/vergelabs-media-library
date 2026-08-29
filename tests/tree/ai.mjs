/*
 *  The AI screen and its effects, end to end with the mock provider.
 *
 *      node tests/tree/ai.mjs http://46.225.66.194 admin VgmlTest7pass
 */
import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://46.225.66.194';
const USER = process.argv[ 3 ] ?? 'admin';
const PASS = process.argv[ 4 ] ?? 'VgmlTest7pass';

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
check( 'status counts load', /\d+ images/.test( counts ), counts );

// mock on, through the settings endpoint (no key needed, nothing spent)
await page.evaluate( async () => {
	await window.wp.apiFetch( { path: '/vergeml/v1/ai-settings', method: 'POST', data: { mock: 1, enrich_search: 1 } } );
} );

/* --- the describe loop -------------------------------------------------------- */

console.log( '\nthe describe loop' );

const before = await page.evaluate( async () =>
	await window.wp.apiFetch( { path: '/vergeml/v1/ai-status' } ) );

await page.click( '#vgml-ai-run' );

// the loop is done when the note says so
await page.waitForFunction( () =>
	/Done|to go/.test( document.getElementById( 'vgml-ai-note' ).textContent ), null, { timeout: 120000 } );
await page.waitForFunction( () =>
	document.getElementById( 'vgml-ai-note' ).textContent.startsWith( 'Done' ), null, { timeout: 180000 } );

const after = await page.evaluate( async () =>
	await window.wp.apiFetch( { path: '/vergeml/v1/ai-status' } ) );

check( 'every image got described', after.unindexed === 0, `${ before.unindexed } -> ${ after.unindexed }` );
check( 'descriptions were stored', after.indexed >= before.indexed && after.indexed > 0, `${ after.indexed } indexed` );
check( 'missing alt text was filled on the way', after.missing_alt === 0, `${ before.missing_alt } -> ${ after.missing_alt }` );
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
// phrase appears in no filename, so a hit proves the caption matched
await page.fill( '.media-toolbar .search', 'describing' );
await page.waitForTimeout( 3000 );

const hits = await page.evaluate( () =>
	document.querySelectorAll( '.attachments-browser .attachments .attachment' ).length );
check( 'a caption-only word fills the grid', hits > 0, `${ hits } results` );

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
check( 'Missing alt text emptied out', smartBadge === '0' || smartBadge === null || smartBadge === '', String( smartBadge ) );

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
