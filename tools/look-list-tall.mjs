/*
 *  What, inside a row, is actually tall.
 *
 *      UI_USER=… UI_PASS=… node tools/look-list-tall.mjs
 *
 *  Four readings have now failed to explain a 1,500px row: no column is tall
 *  on its own, taking columns away barely moves it, a width floor does not fix
 *  it, and giving the File column 34% widens it to 285px and leaves the row at
 *  1,459px. So stop reasoning about columns and ask the row directly: every
 *  element inside it over 100px tall, named, with how it is positioned.
 *
 *  Read-only.
 */

import { chromium } from 'playwright';
import fs from 'node:fs';

const BASE = process.env.UI_BASE ?? 'http://46.225.66.194';
const USER = process.env.UI_USER ?? 'admin';
const PASS = process.env.UI_PASS ?? 'password';

const browser = await chromium.launch();
const ctx = await browser.newContext( { viewport: { width: 1600, height: 900 } } );
const page = await ctx.newPage();
page.setDefaultTimeout( 60000 );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 400 );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );

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

await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 3500 );

const look = () => page.evaluate( () => {

	const row = document.querySelector( '#the-list > tr' );
	if ( ! row ) return null;

	const tall = [];

	for ( const el of row.querySelectorAll( '*' ) ) {
		const r = el.getBoundingClientRect();
		if ( r.height < 100 ) continue;
		const s = getComputedStyle( el );
		tall.push( {
			tag: el.tagName.toLowerCase(),
			cls: ( el.className || '' ).toString().split( ' ' ).slice( 0, 3 ).join( '.' ),
			h: Math.round( r.height ),
			w: Math.round( r.width ),
			x: Math.round( r.x ),
			position: s.position,
			left: s.left,
			inFlow: s.position !== 'absolute' && s.position !== 'fixed',
			text: el.innerText ? el.innerText.trim().replace( /\s+/g, ' ' ).slice( 0, 40 ) : '',
		} );
	}

	// The row's own cells, and what each holds that is in flow.
	const cells = Array.from( row.children ).map( ( td ) => {
		const kids = Array.from( td.children ).map( ( k ) => {
			const r = k.getBoundingClientRect();
			const s = getComputedStyle( k );
			return {
				cls: ( k.className || '' ).toString().split( ' ' )[ 0 ] || k.tagName.toLowerCase(),
				h: Math.round( r.height ),
				position: s.position,
				left: s.left,
			};
		} );
		return {
			cls: td.className.split( ' ' )[ 0 ],
			w: Math.round( td.getBoundingClientRect().width ),
			kids: kids.filter( ( k ) => k.h > 40 ),
		};
	} );

	return { rowHeight: Math.round( row.getBoundingClientRect().height ), tall, cells };
} );

const before = await look();

console.log( `row is ${ before.rowHeight }px\n` );
console.log( 'everything in the row over 100px tall:' );
for ( const t of before.tall ) {
	console.log( `  ${ ( t.tag + '.' + t.cls ).padEnd( 34 ).slice( 0, 34 ) } ${ String( t.h ).padStart( 5 ) }px  w=${ String( t.w ).padStart( 4 ) }  x=${ String( t.x ).padStart( 8 ) }  ${ t.position }${ t.left !== 'auto' ? ' left:' + t.left : '' }${ t.inFlow ? '  IN FLOW' : '' }` );
}

console.log( '\ncells, and what they hold over 40px:' );
for ( const c of before.cells ) {
	if ( ! c.kids.length ) continue;
	console.log( `  ${ c.cls.padEnd( 26 ) } w=${ String( c.w ).padStart( 4 ) }  ${ c.kids.map( ( k ) => `${ k.cls } ${ k.h }px ${ k.position }${ k.left !== 'auto' ? '/' + k.left : '' }` ).join( ' · ' ) }` );
}

/* And the one candidate that follows from it. */
await page.addStyleTag( { content: `.wp-list-table .row-actions { position: absolute !important; }` } );
await page.waitForTimeout( 800 );

const after = await page.evaluate( () => {
	const rows = Array.from( document.querySelectorAll( '#the-list > tr' ) ).map( ( r ) => Math.round( r.getBoundingClientRect().height ) );
	const s = rows.slice().sort( ( a, b ) => a - b );
	return { median: s[ Math.floor( s.length / 2 ) ], tallest: Math.max( ...rows ), page: document.documentElement.scrollHeight };
} );

console.log( `\nwith .row-actions taken out of the flow: median row ${ after.median }px, tallest ${ after.tallest }px, page ${ after.page }px` );

await page.screenshot( { path: 'test-results/tall-after.png' } );
fs.writeFileSync( 'test-results/look-list-tall.json', JSON.stringify( { before, after }, null, '\t' ) );

await browser.close();
