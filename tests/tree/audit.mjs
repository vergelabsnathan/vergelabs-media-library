/*
 *  Drive the tree the way a person does, and complain about what a person would.
 *
 *      node tests/tree/audit.mjs http://185.229.224.239 admin VgmlTest7pass
 *
 *  This exists because Nathan kept finding the bugs. Every one of them -- the
 *  flicker, the dead Unfiled target, the page reloading on every folder click --
 *  was reachable in thirty seconds of clicking, and nothing here was clicking.
 *  The other suites assert that features work; this one asks whether using the
 *  thing is unpleasant, which is a different question and the one that was
 *  going unasked.
 *
 *  It fails on the sort of thing you notice rather than the sort of thing you
 *  test: full page loads during ordinary use, network chatter per click, work
 *  done per keystroke, and rows that jump.
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
// An explicit context, because a second page has to share the login and a
// page made by browser.newPage() owns a context that refuses to make more.
const ctx = await browser.newContext( { viewport: { width: 1500, height: 950 } } );
const page = await ctx.newPage();
page.setDefaultTimeout( 90000 );

let loads = 0;
page.on( 'load', () => loads++ );

const calls = [];
page.on( 'request', ( r ) => {
	const u = r.url();
	if ( u.includes( '/vergeml/v1/' ) || u.includes( '/wp-json/wp/v2/media' ) || r.resourceType() === 'document' ) {
		calls.push( r.resourceType() === 'document' ? 'PAGE' : r.method() + ' ' + u.split( '/vergeml/v1/' )[ 1 ]?.split( '?' )[ 0 ] );
	}
} );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 2500 );

/* ---------------------------------------------------------------- list view */

console.log( '\nlist view' );

await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'load' } );
await page.waitForFunction( () => document.querySelectorAll( '.vgml-node' ).length > 3, { timeout: 60000 } );
await page.waitForTimeout( 1200 );

/*
 *  The tree must be on the screen the moment the page is, with nothing fetched.
 *  It used to render empty and then call the endpoint, so every visit showed a
 *  blank panel and then a pop.
 */
{
	// Same context, or it is not logged in and measures a login screen.
	const fresh = await ctx.newPage();
	fresh.setDefaultTimeout( 90000 );

	const treeCalls = [];
	fresh.on( 'request', ( r ) => {
		if ( r.url().includes( '/vergeml/v1/tree' ) ) { treeCalls.push( r.url() ); }
	} );

	await fresh.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );

	// Read it as early as the browser will let us, not after a settle.
	const atFirstPaint = await fresh.evaluate( () => document.querySelectorAll( '.vgml-node' ).length );
	await fresh.waitForTimeout( 4000 );
	const later = await fresh.evaluate( () => document.querySelectorAll( '.vgml-node' ).length );

	check( 'the tree is drawn at first paint', atFirstPaint > 3, `${ atFirstPaint } rows at DOMContentLoaded` );
	check( 'opening the library fetches no tree', treeCalls.length === 0, `${ treeCalls.length } requests` );
	check( 'and it does not change afterwards', later > 3, `${ later } rows` );

	await fresh.close();
}

// Sitting still must cost nothing.
loads = 0; calls.length = 0;
await page.waitForTimeout( 6000 );
check( 'idle: no page loads', loads === 0, `${ loads } loads` );
check( 'idle: no network chatter', calls.length === 0, JSON.stringify( calls ) );

// Clicking folders is the main thing anyone does. It must not reload the page.
loads = 0; calls.length = 0;
const clicked = [];

/*
 *  Re-queried every time rather than held: the tree repaints as the window moves
 *  and the table is replaced on each click, so a handle taken once goes stale and
 *  the run dies with "not attached to the DOM" -- which says nothing about the
 *  product.
 */
for ( let i = 0; i < 3; i++ ) {
	const rows = await page.$$( '.vgml-node:not(.vgml-pseudo) .vgml-row' );
	if ( ! rows[ i ] ) { break; }

	const was = await page.evaluate( () => ( document.querySelector( '.tablenav .displaying-num' ) || {} ).textContent || '' );
	clicked.push( await rows[ i ].evaluate( ( el ) => ( el.querySelector( '.vgml-name' ) || {} ).textContent ) );

	await rows[ i ].click();

	/*
	 *  Wait for the swap to land rather than sleeping a guessed number of
	 *  milliseconds. Fetching a filtered page of a 20,000-item library takes two
	 *  to three seconds on this box, and a fixed wait read the old table and
	 *  reported a filter that had not applied yet.
	 */
	await page.waitForFunction(
		( prev ) => {
			const el = document.querySelector( '.tablenav .displaying-num' );
			return el && el.textContent !== prev;
		},
		was,
		{ timeout: 25000 }
	).catch( () => {} );
}

check( 'three folder clicks: no full page loads', loads === 0, `${ loads } loads` );
check( 'three folder clicks: no document fetches', ! calls.includes( 'PAGE' ), JSON.stringify( calls.slice( 0, 6 ) ) );
check( 'the tree survived the clicks', ( await page.$$( '.vgml-node' ) ).length > 3 );

// The table must actually have changed, or the swap is a lie.
const filtered = await page.evaluate( () => {
	const el = document.querySelector( '.tablenav .displaying-num' );
	return el ? el.textContent.trim() : null;
} );
check( 'the table reflects the folder', !! filtered && ! /20,000/.test( filtered ),
	`${ filtered || 'no count shown' } after clicking ${ clicked.join( ' -> ' ) }` );

/*
 *  Search for a folder that is actually there.
 *
 *  This used to type "Folder 12", which only meant anything against a fixture of
 *  two thousand folders called Folder N. Against a library that looks like a real
 *  one it matched nothing and reported the search as broken -- a test failing
 *  because the world changed shape, not because the code did.
 */
const before = await page.$$( '.vgml-node' );

const needle = await page.evaluate( () => {
	const name = document.querySelector( '.vgml-tree .vgml-name' );
	return name ? name.textContent.trim().slice( 0, 4 ) : '';
} );

// Typing in the search must not hit the network at all.
calls.length = 0;
await page.click( '.vgml-search' );
await page.keyboard.type( needle, { delay: 60 } );
await page.waitForTimeout( 900 );
check( 'searching is local: no requests', calls.length === 0, JSON.stringify( calls ) );

const found = await page.$$( '.vgml-node' );
check( 'searching narrows the tree', found.length > 0 && found.length <= before.length,
	`"${ needle }": ${ found.length } of ${ before.length } shown` );

await page.fill( '.vgml-search', '' );
await page.waitForTimeout( 600 );

// Scrolling a long tree must not thrash.
const scrollWork = await page.evaluate( async () => {
	const list = document.querySelector( '.vgml-list' );
	let paints = 0;
	const obs = new MutationObserver( () => paints++ );
	obs.observe( list, { childList: true } );
	for ( let i = 0; i < 10; i++ ) {
		list.scrollTop += 240;
		await new Promise( ( r ) => setTimeout( r, 60 ) );
	}
	await new Promise( ( r ) => setTimeout( r, 300 ) );
	obs.disconnect();
	return { paints, rows: document.querySelectorAll( '.vgml-node' ).length };
} );
// Incremental painting touches the list per step; wiping and rebuilding shows up
// as an order of magnitude more mutations than steps.
check( 'scrolling paints incrementally', scrollWork.paints < 120, `${ scrollWork.paints } mutations over 10 steps` );
check( 'scrolling keeps the window small', scrollWork.rows < 80, `${ scrollWork.rows } rows` );

/* ---------------------------------------------------------------- grid view */

console.log( '\ngrid view' );

await page.goto( `${ BASE }/wp-admin/upload.php?mode=grid`, { waitUntil: 'load' } );
await page.waitForFunction( () => document.querySelectorAll( '.vgml-node' ).length > 3, { timeout: 60000 } );
await page.waitForTimeout( 4000 );

loads = 0; calls.length = 0;
for ( let i = 0; i < 2; i++ ) {
	const rows = await page.$$( '.vgml-node:not(.vgml-pseudo) .vgml-row' );
	if ( ! rows[ i ] ) { break; }
	await rows[ i ].click();
	await page.waitForTimeout( 2200 );
}
check( 'grid: clicking folders never reloads', loads === 0, `${ loads } loads` );
check( 'grid: the tree is still there', ( await page.$$( '.vgml-node' ) ).length > 3 );

/* ------------------------------------------------------------- the details */

console.log( '\nthe details a person notices' );

await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'load' } );
await page.waitForFunction( () => document.querySelectorAll( '.vgml-node' ).length > 3, { timeout: 60000 } );
await page.waitForTimeout( 1200 );

const look = await page.evaluate( () => {
	const rows = [ ...document.querySelectorAll( '.vgml-node .vgml-row' ) ];
	const heights = [ ...new Set( rows.map( ( r ) => Math.round( r.getBoundingClientRect().height ) ) ) ];
	const names = [ ...document.querySelectorAll( '.vgml-name' ) ].map( ( n ) => Math.round( n.getBoundingClientRect().x ) );
	const icons = [ ...document.querySelectorAll( '.vgml-icon' ) ].map( ( n ) => Math.round( n.getBoundingClientRect().x ) );
	const overlap = rows.some( ( r ) => {
		const i = r.querySelector( '.vgml-icon' );
		const n = r.querySelector( '.vgml-name' );
		if ( ! i || ! n ) return false;
		return i.getBoundingClientRect().right > n.getBoundingClientRect().left + 1;
	} );
	return {
		heights,
		topLevelNamesAligned: new Set( names.slice( 0, 2 ) ).size === 1,
		iconsAligned: new Set( icons.slice( 0, 2 ) ).size === 1,
		overlap,
		panelWidth: Math.round( document.querySelector( '.vgml-tree' ).getBoundingClientRect().width ),
		hScroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
	};
} );

check( 'rows are a uniform height', look.heights.length === 1, look.heights.join( ', ' ) );
check( 'nothing overlaps the folder name', look.overlap === false );
check( 'the panel has a sane width', look.panelWidth >= 180 && look.panelWidth <= 640, `${ look.panelWidth }px` );
check( 'no horizontal scrollbar', look.hScroll <= 2, `${ look.hScroll }px` );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
