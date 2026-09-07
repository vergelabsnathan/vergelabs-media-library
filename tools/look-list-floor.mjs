/*
 *  How wide the table has to be before a row is a row.
 *
 *      UI_USER=… UI_PASS=… node tools/look-list-floor.mjs
 *
 *  Taking our three extra columns away changed nothing: the File column stayed
 *  at 29px and the row at 1,960px. So the column *count* is not the cause. In
 *  a table-layout:fixed table the columns that declare a width take it, and
 *  the ones that do not share what is left -- and File declares nothing. On
 *  this box AIOSEO declares 224, FileBird 122 and 102, Date 143, Author 102.
 *  Those are taken first, File absorbs the deficit, and a filename wraps a
 *  character a line.
 *
 *  So the question is not how many columns but how much width, and this sweeps
 *  it: for each table width, how wide is File and how tall is a row. Read-only,
 *  injected stylesheets only.
 */

import { chromium } from 'playwright';
import fs from 'node:fs';

const BASE = process.env.UI_BASE ?? 'http://46.225.66.194';
const USER = process.env.UI_USER ?? 'admin';
const PASS = process.env.UI_PASS ?? 'password';

const FLOORS = [ 0, 1040, 1240, 1440, 1640, 1840 ];

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

/** Every column that declares a width of its own, and what it declares. */
const declared = await page.evaluate( () => {
	const head = document.querySelector( '.wp-list-table thead tr' );
	return Array.from( head.children ).map( ( th ) => {
		const r = th.getBoundingClientRect();
		return {
			id: th.id || th.className.split( ' ' )[ 0 ],
			text: th.innerText.trim().split( '\n' )[ 0 ],
			css: getComputedStyle( th ).width,
			now: Math.round( r.width ),
		};
	} );
} );

const stats = () => page.evaluate( () => {
	const rows = Array.from( document.querySelectorAll( '#the-list > tr' ) )
		.map( ( r ) => Math.round( r.getBoundingClientRect().height ) );
	const table = document.querySelector( '.wp-list-table' );
	const head = document.querySelector( '.wp-list-table thead tr' );
	return {
		tableW: table ? Math.round( table.getBoundingClientRect().width ) : 0,
		titleW: Math.round( document.getElementById( 'title' )?.getBoundingClientRect().width || 0 ),
		columns: head ? Array.from( head.children ).filter( ( th ) => getComputedStyle( th ).display !== 'none' ).length : 0,
		median: rows.length ? rows.slice().sort( ( a, b ) => a - b )[ Math.floor( rows.length / 2 ) ] : 0,
		tallest: Math.max( 0, ...rows ),
		page: document.documentElement.scrollHeight,
	};
} );

const rows = [];
let tag = null;

for ( const floor of FLOORS ) {

	if ( tag ) {
		await tag.evaluate( ( el ) => el.remove() );
		tag = null;
	}

	if ( floor ) {
		tag = await page.addStyleTag( { content: `
			#posts-filter { overflow-x: auto; }
			#posts-filter .wp-list-table { min-width: ${ floor }px; }
		` } );
	}

	await page.waitForTimeout( 800 );
	rows.push( { floor, ...await stats() } );
}

fs.writeFileSync( 'test-results/look-list-floor.json', JSON.stringify( { declared, rows }, null, '\t' ) );

console.log( 'what each column asks for:' );
for ( const d of declared ) {
	console.log( `  ${ d.text.padEnd( 20 ).slice( 0, 20 ) } css=${ d.css.padStart( 8 ) }  now=${ String( d.now ).padStart( 4 ) }px` );
}

console.log( '\nfloor      table  File   median row   page' );
for ( const r of rows ) {
	console.log(
		`  ${ String( r.floor || 'none' ).padStart( 5 ) }  ${ String( r.tableW ).padStart( 6 ) }  ` +
		`${ String( r.titleW ).padStart( 4 ) }px  ${ String( r.median ).padStart( 6 ) }px  ${ String( r.page ).padStart( 7 ) }px`
	);
}

await browser.close();
