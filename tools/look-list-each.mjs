/*
 *  What each column contributes to the height of a row, on its own.
 *
 *      UI_USER=… UI_PASS=… node tools/look-list-each.mjs
 *
 *  Taking columns away barely moved the File column and barely moved the row,
 *  which kills the "too many columns" reading. So this shows one column at a
 *  time -- the tick and one other, nothing else -- and measures the row. A
 *  column that gives a 40px row on its own is innocent; one that is still
 *  hundreds of pixels tall with the whole table to itself is the problem.
 *
 *  Read-only: one injected stylesheet, rewritten in place, gone on the next
 *  load.
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

const report = await page.evaluate( () => {

	const head = document.querySelector( '.wp-list-table thead tr' );
	const columns = Array.from( head.children ).map( ( th, i ) => ( {
		i: i + 1,
		text: th.innerText.trim().split( '\n' )[ 0 ] || th.id,
	} ) );

	const style = document.createElement( 'style' );
	document.head.appendChild( style );

	const measure = () => {
		const rows = Array.from( document.querySelectorAll( '#the-list > tr' ) )
			.map( ( r ) => Math.round( r.getBoundingClientRect().height ) );
		const sorted = rows.slice().sort( ( a, b ) => a - b );
		return {
			median: sorted.length ? sorted[ Math.floor( sorted.length / 2 ) ] : 0,
			tallest: Math.max( 0, ...rows ),
			width: Math.round( document.querySelector( '.wp-list-table' ).getBoundingClientRect().width ),
		};
	};

	const out = [];

	for ( const c of columns ) {
		// Only the tick and this one, so the column has the table to itself.
		style.textContent = `.wp-list-table tr > *:not(:nth-child(1)):not(:nth-child(${ c.i })) { display: none !important; }`;
		const m = measure();
		out.push( { ...c, alone: m.median, tallest: m.tallest } );
	}

	style.textContent = '';
	const asIs = measure();

	style.remove();

	return { asIs, out };
} );

console.log( `as it is: median row ${ report.asIs.median }px, table ${ report.asIs.width }px\n` );
console.log( 'with the table to itself          median row   tallest' );

for ( const c of report.out ) {
	const flag = c.alone > 100 ? '   <-- tall on its own' : '';
	console.log( `  ${ c.text.padEnd( 28 ).slice( 0, 28 ) }  ${ String( c.alone ).padStart( 6 ) }px  ${ String( c.tallest ).padStart( 6 ) }px${ flag }` );
}

fs.writeFileSync( 'test-results/look-list-each.json', JSON.stringify( report, null, '\t' ) );

await browser.close();
