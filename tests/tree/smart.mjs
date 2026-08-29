/*
 *  Smart folders, on screen.
 *
 *      node tests/tree/smart.mjs http://46.225.66.194 admin VgmlTest7pass
 *
 *  The one flow that matters end to end: a never-scanned row says "Scan" rather
 *  than pretending to a number, one click runs the scan with its progress in
 *  the row, the finished number filters the grid, the same filter works as a
 *  bookmarkable URL in list view, and clicking an ordinary folder afterwards
 *  puts everything back.
 */

import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://46.225.66.194';
const USER = process.argv[ 3 ] ?? 'admin';
const PASS = process.argv[ 4 ] ?? 'VgmlTest7pass';

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? '  -- ' + detail : '' }` );
};

const browser = await chromium.launch();
const ctx = await browser.newContext( { viewport: { width: 1500, height: 1000 } } );
const page = await ctx.newPage();
page.setDefaultTimeout( 90000 );

const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 2500 );

const clearNotices = () => page.evaluate( () => {
	document.querySelectorAll( '.notice, .e-notice, .update-nag' ).forEach( ( n ) => n.remove() );
} );

/* --- before any scan: Scan, not zero --------------------------------------- */

/*
 *  Run `wp option delete vergeml_smart_scan` first: this test exercises the
 *  first-run experience, and there is deliberately no reset endpoint to do it
 *  from the browser -- un-scanning a library is not a thing the interface
 *  should offer.
 */
await page.goto( `${ BASE }/wp-admin/upload.php?mode=grid`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );

// the smart rows live behind the FILTERS toggle now; make sure it is open --
// and seed one missing-alt image, because an AI pass may have filled them all
await page.evaluate( async () => {
	await window.wp.apiFetch( { path: '/vergeml/v1/state', method: 'POST',
		data: { taxonomy: 'media_category', filtersOpen: 1 } } );
	const one = ( await window.wp.apiFetch( { path: '/wp/v2/media?media_type=image&per_page=1&_fields=id' } ) )[ 0 ];
	window.__vgmlAltSeed = one.id;
	await window.wp.apiFetch( { path: '/wp/v2/media/' + one.id, method: 'POST', data: { alt_text: '' } } );
} );
await page.reload( { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );
await clearNotices();
await page.waitForTimeout( 800 );

const rows = await page.evaluate( () =>
	[ ...document.querySelectorAll( '.vgml-tree .vgml-smart' ) ].map( ( r ) => ( {
		key: r.getAttribute( 'data-smart' ),
		badge: ( r.querySelector( '.vgml-count' ) || {} ).textContent || '',
	} ) ) );

/*
 *  The five this file is about, by name rather than by counting the rows.
 *  Counting was right when they were the only smart folders there were; since
 *  then quarantine and the AI group have both registered their own through the
 *  `vergeml_smart_folders` filter, and a bare length made every one of those a
 *  failure of this file. What matters here is that the five core rows survive
 *  whatever else attaches itself.
 */
const CORE = [ 'unused', 'no-alt', 'large', 'unattached', 'recent' ];
const missing = CORE.filter( ( k ) => ! rows.some( ( r ) => r.key === k ) );

check( 'the five core smart folders are in the tree', 0 === missing.length,
	missing.length ? 'missing: ' + missing.join( ',' ) : rows.map( ( r ) => r.key ).join( ',' ) );
check( 'scan-backed ones say Scan, not zero',
	rows.some( ( r ) => r.key === 'unused' && /scan/i.test( r.badge ) ),
	JSON.stringify( rows.find( ( r ) => r.key === 'unused' ) ) );
check( 'live ones already carry numbers',
	rows.some( ( r ) => r.key === 'no-alt' && /^\d+$/.test( r.badge.trim() ) ),
	JSON.stringify( rows.find( ( r ) => r.key === 'no-alt' ) ) );

/* --- one click: scan, then filter ------------------------------------------- */

console.log( '\nthe scan' );

await page.locator( '.vgml-tree .vgml-smart[data-smart="unused"] .vgml-row' ).click();

// The row must go through a progress state and come out the other side a number.
await page.waitForFunction( () => {
	const r = document.querySelector( '.vgml-tree .vgml-smart[data-smart="unused"] .vgml-count' );
	return r && /^\d+$/.test( r.textContent.trim() );
}, null, { timeout: 120000 } );

const unusedCount = await page.evaluate( () =>
	parseInt( document.querySelector( '.vgml-tree .vgml-smart[data-smart="unused"] .vgml-count' ).textContent, 10 ) );

check( 'the scan finished into a real number', unusedCount > 0, String( unusedCount ) );

const largeBadge = await page.evaluate( () =>
	( document.querySelector( '.vgml-tree .vgml-smart[data-smart="large"] .vgml-count' ) || {} ).textContent || '(none)' );
check( 'the other scan-backed row got its number from the same scan',
	'(none)' === largeBadge || /^\d+$/.test( largeBadge.trim() ) && ! /scan/i.test( largeBadge ),
	largeBadge );

// And the grid is now showing exactly that set.
await page.waitForFunction( ( expected ) => {
	const s = document.querySelector( '.media-frame .spinner.is-active' );
	return ! s && document.querySelectorAll( '.attachment' ).length === Math.min( expected, 80 );
}, unusedCount, { timeout: 60000 } ).catch( () => {} );

const tiles = await page.evaluate( () => document.querySelectorAll( '.attachment' ).length );
check( 'the grid filtered to the unused set', tiles === Math.min( unusedCount, 80 ), `${ tiles } tiles for ${ unusedCount }` );

/* --- the live one filters too ------------------------------------------------ */

await page.locator( '.vgml-tree .vgml-smart[data-smart="no-alt"] .vgml-row' ).click();
await page.waitForTimeout( 2500 );

const noAlt = await page.evaluate( () => ( {
	badge: parseInt( document.querySelector( '.vgml-tree .vgml-smart[data-smart="no-alt"] .vgml-count' ).textContent, 10 ),
	tiles: document.querySelectorAll( '.attachment' ).length,
} ) );

check( 'missing-alt filters the grid to its own number', noAlt.tiles === Math.min( noAlt.badge, 80 ),
	`${ noAlt.tiles } tiles for ${ noAlt.badge }` );

/* --- a folder click puts everything back ------------------------------------- */

const folder = await page.evaluate( () => {
	const r = [ ...document.querySelectorAll( '.vgml-tree .vgml-node[data-id]' ) ]
		.map( ( n ) => ( { id: +n.getAttribute( 'data-id' ), count: parseInt( ( n.querySelector( '.vgml-count' ) || {} ).textContent || '0', 10 ) } ) )
		.filter( ( n ) => n.id > 0 && n.count > 0 );
	return r[ 0 ] || null;
} );

await page.locator( `.vgml-tree .vgml-node[data-id="${ folder.id }"] .vgml-row` ).click();
await page.waitForTimeout( 2500 );

const after = await page.evaluate( () => ( {
	tiles: document.querySelectorAll( '.attachment' ).length,
	smartSelected: !! document.querySelector( '.vgml-tree .vgml-smart.is-selected' ),
} ) );

check( 'a folder click clears the smart filter', ! after.smartSelected );
check( 'and the grid shows the folder again', after.tiles === Math.min( folder.count, 80 ),
	`${ after.tiles } tiles for ${ folder.count }` );

/* --- list view: a bookmarkable URL ------------------------------------------- */

console.log( '\nlist view' );

await page.goto( `${ BASE }/wp-admin/upload.php?mode=list&vgml_smart=no-alt`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 1500 );

const list = await page.evaluate( () => {
	const n = document.querySelector( '.tablenav .displaying-num' );
	return n ? parseInt( n.textContent.replace( /[^0-9]/g, '' ), 10 ) : null;
} );

check( 'the same filter works as a plain URL', list === noAlt.badge, `${ list } vs ${ noAlt.badge }` );

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
