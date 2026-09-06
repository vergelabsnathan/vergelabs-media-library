/*
 *  The media library in list mode, measured.
 *
 *      UI_USER=… UI_PASS=… node tools/look-list.mjs
 *
 *  Written on 2026-09-06 because the list arrives with rows two thousand
 *  pixels tall and a screenshot of it is 28,000px long, and an impression of
 *  a screenshot is not a diagnosis. It answers four questions with numbers:
 *
 *    1. where the width goes -- every pane that takes horizontal room, named;
 *    2. what the fold does to it, and whether the fold is remembered;
 *    3. how tall a row is, and which columns are on;
 *    4. whether the row height is the column count or the width, by hiding
 *       columns in the page and measuring again.
 *
 *  Read-only. The one thing it presses is the panel's own collapse control,
 *  and the columns are hidden with a stylesheet injected into the page, which
 *  touches nothing on the site and is gone on the next load. Screenshots and
 *  a JSON dump land in test-results/.
 */

import { chromium } from 'playwright';
import fs from 'node:fs';

const BASE = process.env.UI_BASE ?? 'http://46.225.66.194';
const USER = process.env.UI_USER ?? 'admin';
const PASS = process.env.UI_PASS ?? 'password';
const WIDTH = Number( process.env.UI_WIDTH ?? 1600 );

const browser = await chromium.launch();
const ctx = await browser.newContext( { viewport: { width: WIDTH, height: 900 } } );
const page = await ctx.newPage();
page.setDefaultTimeout( 60000 );

const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

/* --- in ------------------------------------------------------------- */

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 400 );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );

/*
 *  Jetpack's brute-force protection, left without an API key, falls back to a
 *  sum on the login form and answers 401 to any login that does not carry it.
 *  Same as tests/ui/fixtures.mjs; a script that skips it lands back on the
 *  login page and every later selector times out on nothing.
 */
const puzzle = page.locator( 'input[name="jetpack_protect_num"]' );

if ( await puzzle.count() ) {
	const asked = await page.locator( 'label[for="jetpack_protect_answer"]' ).innerText();
	const sum = /(\d+)\D+(\d+)/.exec( asked.replace( / /g, ' ' ) );
	if ( sum ) {
		await puzzle.fill( String( Number( sum[ 1 ] ) + Number( sum[ 2 ] ) ) );
	}
}

await Promise.all( [
	page.waitForNavigation( { waitUntil: 'domcontentloaded' } ).catch( () => null ),
	page.click( '#wp-submit' ),
] );

if ( /wp-login/.test( page.url() ) ) {
	throw new Error( `could not sign in to ${ BASE } as "${ USER }"` );
}

const open = async () => {
	await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 3500 );
};

/* --- the measurements ------------------------------------------------ */

const measure = ( name ) => page.evaluate( ( label ) => {

	const box = ( sel ) => {
		const el = document.querySelector( sel );
		if ( ! el ) return null;
		const r = el.getBoundingClientRect();
		return { x: Math.round( r.x ), w: Math.round( r.width ), h: Math.round( r.height ) };
	};

	const wrap = document.querySelector( '.wrap' );
	const head = document.querySelector( '.wp-list-table thead tr' );
	const shown = head ? Array.from( head.children ).filter( ( th ) => getComputedStyle( th ).display !== 'none' ) : [];
	const rows = Array.from( document.querySelectorAll( '#the-list > tr' ) )
		.map( ( r ) => Math.round( r.getBoundingClientRect().height ) );

	const notices = Array.from( document.querySelectorAll( '.notice, .updated, .error' ) )
		.map( ( el ) => ( { h: Math.round( el.getBoundingClientRect().height ), text: el.innerText.trim().replace( /\s+/g, ' ' ).slice( 0, 46 ) } ) )
		.filter( ( x ) => x.h > 0 );

	return {
		name: label,
		window: window.innerWidth,
		bodyClass: document.body.className.split( ' ' ).filter( ( c ) => /vgml|fold/.test( c ) ).join( ' ' ),
		treeClass: document.querySelector( '.vgml-tree' )?.className || '',
		adminMenu: box( '#adminmenuwrap' ),
		filebird: box( '#filebird-root' ),
		wpbodyContent: box( '#wpbody-content' ),
		wrapPadding: wrap ? getComputedStyle( wrap ).paddingLeft : '',
		tree: box( '.vgml-tree' ),
		table: box( '.wp-list-table' ),
		columns: shown.map( ( th ) => ( {
			id: th.id || th.className.split( ' ' )[ 0 ],
			w: Math.round( th.getBoundingClientRect().width ),
			text: th.innerText.trim().split( '\n' )[ 0 ],
		} ) ),
		medianRow: rows.length ? rows.slice().sort( ( a, b ) => a - b )[ Math.floor( rows.length / 2 ) ] : 0,
		tallestRow: Math.max( 0, ...rows ),
		pageHeight: document.documentElement.scrollHeight,
		noticeHeight: notices.reduce( ( a, b ) => a + b.h, 0 ),
		notices,
	};
}, name );

const out = [];
const shot = ( name ) => page.screenshot( { path: `test-results/look-list-${ name }.png` } );

await open();
out.push( await measure( 'as it arrives' ) );
await shot( '1-arrives' );
await page.screenshot( { path: 'test-results/look-list-1-arrives-full.png', fullPage: true } );

const fold = page.locator( 'button.vgml-fold' );

if ( await fold.count() ) {

	await fold.click();
	await page.waitForTimeout( 1200 );
	out.push( await measure( 'after one press of the fold' ) );
	await shot( '2-folded-once' );

	await fold.click();
	await page.waitForTimeout( 1200 );
	out.push( await measure( 'after a second press' ) );
	await shot( '3-folded-twice' );

	// The fold is remembered, so what comes back is the state that was saved.
	await open();
	out.push( await measure( 'after a reload' ) );
	await shot( '4-reloaded' );
}

/*
 *  The column count against the width, decided rather than argued: keep the
 *  tick, the file, its folder and its date, and measure the same rows again.
 */
await page.addStyleTag( { content: `
	.wp-list-table th:not(#cb):not(#title):not(#taxonomy-media_category):not(#date),
	.wp-list-table td:not(.check-column):not(.title):not(.taxonomy-media_category):not(.date) { display: none !important; }
` } );
await page.waitForTimeout( 800 );

out.push( await measure( 'four columns, same width' ) );
await shot( '5-four-columns' );

fs.writeFileSync( 'test-results/look-list.json', JSON.stringify( { out, errors }, null, '\t' ) );

for ( const m of out ) {
	console.log(
		`${ m.name.padEnd( 28 ) } table=${ String( m.table?.w ?? 0 ).padStart( 5 ) }px  columns=${ String( m.columns.length ).padStart( 2 ) }` +
		`  File=${ String( m.columns.find( ( c ) => c.id === 'title' )?.w ?? 0 ).padStart( 4 ) }px` +
		`  row=${ String( m.medianRow ).padStart( 5 ) }px  page=${ String( m.pageHeight ).padStart( 6 ) }px  ${ m.treeClass }`
	);
}

const first = out[ 0 ];

console.log( `\nwidth at ${ first.window }px: admin menu ${ first.adminMenu?.w ?? 0 }` +
	` + FileBird ${ first.filebird?.w ?? 0 } + our gutter ${ first.wrapPadding } → table ${ first.table?.w ?? 0 }` );
console.log( `notices above the table: ${ first.noticeHeight }px over ${ first.notices.length }` );
console.log( `columns: ${ first.columns.map( ( c ) => `${ c.text } ${ c.w }` ).join( ' · ' ) }` );

if ( errors.length ) {
	console.log( `\njavascript errors: ${ errors.join( ' | ' ) }` );
}

await browser.close();
