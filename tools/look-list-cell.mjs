/*
 *  Which cell makes the row tall.
 *
 *      UI_USER=… UI_PASS=… node tools/look-list-cell.mjs
 *
 *  A width floor of 1,840px still leaves a 1,365px row, so the height is not
 *  the File column being squeezed -- something in the row is tall on its own.
 *  Every cell in a table row stretches to the row's height, so asking a cell
 *  how tall it is tells you nothing. This takes each column away in turn and
 *  measures what the row does without it.
 *
 *  Read-only: injected stylesheets, gone on the next load.
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
		i,
		id: th.id || th.className.split( ' ' )[ 0 ],
		text: th.innerText.trim().split( '\n' )[ 0 ],
	} ) );

	const rowsOf = () => Array.from( document.querySelectorAll( '#the-list > tr' ) )
		.map( ( r ) => Math.round( r.getBoundingClientRect().height ) );

	const median = ( a ) => a.length ? a.slice().sort( ( x, y ) => x - y )[ Math.floor( a.length / 2 ) ] : 0;

	const style = document.createElement( 'style' );
	document.head.appendChild( style );

	const before = median( rowsOf() );

	const out = [];

	for ( const c of columns ) {
		// nth-child is 1-based and counts every cell, which is what we want.
		style.textContent = `.wp-list-table tr > *:nth-child(${ c.i + 1 }) { display: none !important; }`;
		out.push( { ...c, withoutIt: median( rowsOf() ) } );
	}

	// And what one cell actually holds, measured with the row's height taken
	// out of the equation by looking at the text it carries.
	style.textContent = '';

	const first = document.querySelector( '#the-list > tr' );
	const contents = first ? Array.from( first.children ).map( ( td, i ) => ( {
		i,
		cls: td.className.split( ' ' )[ 0 ],
		chars: td.innerText.trim().length,
		nodes: td.querySelectorAll( '*' ).length,
		sample: td.innerText.trim().replace( /\s+/g, ' ' ).slice( 0, 60 ),
	} ) ) : [];

	style.remove();

	return { before, out, contents };
} );

console.log( `row now: ${ report.before }px\n` );
console.log( 'column removed                  row becomes' );

for ( const c of report.out ) {
	const flag = c.withoutIt < report.before / 2 ? '   <-- this one' : '';
	console.log( `  ${ c.text.padEnd( 24 ).slice( 0, 24 ) }  ${ String( c.withoutIt ).padStart( 6 ) }px${ flag }` );
}

console.log( '\nwhat the first row carries in each cell:' );
for ( const c of report.contents ) {
	console.log( `  ${ ( c.cls || '?' ).padEnd( 26 ) } ${ String( c.chars ).padStart( 5 ) } chars, ${ String( c.nodes ).padStart( 4 ) } nodes  ${ c.sample }` );
}

fs.writeFileSync( 'test-results/look-list-cell.json', JSON.stringify( report, null, '\t' ) );

await browser.close();
