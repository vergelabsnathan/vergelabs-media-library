/*
 *  Two candidate rules for the list, measured against each other.
 *
 *      UI_USER=… UI_PASS=… node tools/look-list-rules.mjs
 *
 *  The diagnosis says thirteen columns in 763px is what makes a row two
 *  thousand pixels tall. Only four of the thirteen are ours, so before a spec
 *  is written around "fewer of our columns", it has to be measured -- taking
 *  three away leaves ten, and ten in 763px is still 76px a column.
 *
 *  Rule A: our three extras off by default. Everyone else's stay.
 *  Rule B: the table keeps a minimum width and its container scrolls, so no
 *          combination of other plugins' columns can crush the File column.
 *  A+B:    both.
 *
 *  Read-only: every rule is an injected stylesheet, gone on the next load.
 */

import { chromium } from 'playwright';
import fs from 'node:fs';

const BASE = process.env.UI_BASE ?? 'http://46.225.66.194';
const USER = process.env.UI_USER ?? 'admin';
const PASS = process.env.UI_PASS ?? 'password';

const OURS_EXTRA = '#taxonomy-colour, #vergeml_used, #vgmlpro_source';
const OURS_EXTRA_CELLS = '.taxonomy-colour, .vergeml_used, .vgmlpro_source';

const browser = await chromium.launch();

const results = [];

for ( const width of [ 1600, 1280, 1024 ] ) {

	const ctx = await browser.newContext( { viewport: { width, height: 900 } } );
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

	const stats = ( rule ) => page.evaluate( ( name ) => {
		const rows = Array.from( document.querySelectorAll( '#the-list > tr' ) )
			.map( ( r ) => Math.round( r.getBoundingClientRect().height ) );
		const table = document.querySelector( '.wp-list-table' );
		const head = document.querySelector( '.wp-list-table thead tr' );
		const shown = head ? Array.from( head.children ).filter( ( th ) => getComputedStyle( th ).display !== 'none' ) : [];
		const form = document.getElementById( 'posts-filter' );
		return {
			rule: name,
			window: window.innerWidth,
			tableW: table ? Math.round( table.getBoundingClientRect().width ) : 0,
			columns: shown.length,
			titleW: Math.round( shown.find( ( th ) => th.id === 'title' )?.getBoundingClientRect().width || 0 ),
			median: rows.length ? rows.slice().sort( ( a, b ) => a - b )[ Math.floor( rows.length / 2 ) ] : 0,
			tallest: Math.max( 0, ...rows ),
			page: document.documentElement.scrollHeight,
			scrolls: form ? form.scrollWidth > form.clientWidth : false,
		};
	}, rule );

	results.push( await stats( 'as it is' ) );

	// --- rule A -----------------------------------------------------------
	const a = await page.addStyleTag( { content: `
		.wp-list-table th:is(${ OURS_EXTRA }),
		.wp-list-table td:is(${ OURS_EXTRA_CELLS }) { display: none !important; }
	` } );
	await page.waitForTimeout( 700 );
	results.push( await stats( 'A: our three extras off' ) );

	// --- rule A + B -------------------------------------------------------
	const b = await page.addStyleTag( { content: `
		#posts-filter { overflow-x: auto; }
		#posts-filter .wp-list-table { min-width: 1040px; }
	` } );
	await page.waitForTimeout( 700 );
	results.push( await stats( 'A + B: and a floor that scrolls' ) );
	await page.screenshot( { path: `test-results/rules-ab-${ width }.png` } );

	// --- rule B alone -----------------------------------------------------
	await a.evaluate( ( el ) => el.remove() );
	await page.waitForTimeout( 700 );
	results.push( await stats( 'B alone: every column, floor' ) );
	await page.screenshot( { path: `test-results/rules-b-${ width }.png` } );

	await ctx.close();
}

fs.writeFileSync( 'test-results/look-list-rules.json', JSON.stringify( results, null, '\t' ) );

let last = null;
for ( const r of results ) {
	if ( r.window !== last ) {
		console.log( `\n=== window ${ r.window }px ===` );
		last = r.window;
	}
	console.log(
		`  ${ r.rule.padEnd( 30 ) } table=${ String( r.tableW ).padStart( 5 ) }  cols=${ String( r.columns ).padStart( 2 ) }` +
		`  File=${ String( r.titleW ).padStart( 4 ) }px  row=${ String( r.median ).padStart( 5 ) }px` +
		`  page=${ String( r.page ).padStart( 6 ) }px  ${ r.scrolls ? 'scrolls sideways' : '' }`
	);
}

await browser.close();
