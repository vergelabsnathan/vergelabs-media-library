/*
 *  Candidate rules for the list, measured against each other.
 *
 *      UI_USER=… UI_PASS=… node tools/look-list-fix.mjs
 *
 *  Established already: the row height is the tallest cell, and the tallest
 *  cell is whichever holds the most text in the fewest pixels. With thirteen
 *  columns in 763px most of them are under 45px, so a cell holding a sentence
 *  wraps at a character or two a line and the row grows to two thousand
 *  pixels. No single column causes it -- taking any one away still leaves
 *  1,455-1,780px -- and a width floor alone does not fix it either: 1,840px
 *  still gives a 1,365px row.
 *
 *  So this asks the two questions a fix has to answer:
 *
 *    - how much of it is ours? (core + our columns, nothing else)
 *    - what makes our part of it incapable of growing a row? (cells that
 *      cannot wrap, and three of our four columns off by default)
 *
 *  Read-only: injected stylesheets, gone on the next load.
 */

import { chromium } from 'playwright';
import fs from 'node:fs';

const BASE = process.env.UI_BASE ?? 'http://46.225.66.194';
const USER = process.env.UI_USER ?? 'admin';
const PASS = process.env.UI_PASS ?? 'password';

/* Columns other plugins put on this screen. */
const THEIRS = '#fb_folder, #fb_filesize, #qode-optimizer, #seopress_alt_text, #aioseo-details';
const THEIRS_CELLS = '.fb_folder, .fb_filesize, .qode-optimizer, .seopress_alt_text, .aioseo-details';

/* Ours beyond the folder. */
const OURS_EXTRA = '#taxonomy-colour, #vergeml_used, #vgmlpro_source';
const OURS_EXTRA_CELLS = '.taxonomy-colour, .vergeml_used, .vgmlpro_source';

/* All of ours: a cell of ours may never be the reason a row is tall. */
const OURS_CELLS = '.taxonomy-media_category, .taxonomy-colour, .vergeml_used, .vgmlpro_source';

const NOWRAP = `
	.wp-list-table td:is(${ OURS_CELLS }) {
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		max-width: 0;
	}
`;

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

const stats = () => page.evaluate( () => {
	const rows = Array.from( document.querySelectorAll( '#the-list > tr' ) )
		.map( ( r ) => Math.round( r.getBoundingClientRect().height ) );
	const head = document.querySelector( '.wp-list-table thead tr' );
	const shown = head ? Array.from( head.children ).filter( ( th ) => getComputedStyle( th ).display !== 'none' ) : [];
	const table = document.querySelector( '.wp-list-table' );
	return {
		tableW: table ? Math.round( table.getBoundingClientRect().width ) : 0,
		columns: shown.length,
		titleW: Math.round( document.getElementById( 'title' )?.getBoundingClientRect().width || 0 ),
		median: rows.length ? rows.slice().sort( ( a, b ) => a - b )[ Math.floor( rows.length / 2 ) ] : 0,
		tallest: Math.max( 0, ...rows ),
		page: document.documentElement.scrollHeight,
	};
} );

const hide = ( sel, cells ) => `.wp-list-table th:is(${ sel }), .wp-list-table td:is(${ cells }) { display: none !important; }`;
const floor = ( px ) => `#posts-filter { overflow-x: auto; } #posts-filter .wp-list-table { min-width: ${ px }px; }`;

const CASES = [
	[ 'as it is today', '' ],
	[ 'core + ours, no other plugins', hide( THEIRS, THEIRS_CELLS ) ],
	[ '…and our three extras off', hide( THEIRS, THEIRS_CELLS ) + hide( OURS_EXTRA, OURS_EXTRA_CELLS ) ],
	[ 'everything, our cells cannot wrap', NOWRAP ],
	[ '…and our three extras off', NOWRAP + hide( OURS_EXTRA, OURS_EXTRA_CELLS ) ],
	[ '…and a floor of 1240 that scrolls', NOWRAP + hide( OURS_EXTRA, OURS_EXTRA_CELLS ) + floor( 1240 ) ],
	[ '…and the panel folded as well', NOWRAP + hide( OURS_EXTRA, OURS_EXTRA_CELLS ) + floor( 1240 ) ],
];

const out = [];
let tag = null;

for ( const [ name, css ] of CASES ) {

	if ( tag ) {
		await tag.evaluate( ( el ) => el.remove() );
		tag = null;
	}

	if ( name === '…and the panel folded as well' ) {
		const fold = page.locator( 'button.vgml-fold' );
		if ( await fold.count() ) {
			await fold.click();
			await page.waitForTimeout( 1000 );
		}
	}

	if ( css ) {
		tag = await page.addStyleTag( { content: css } );
	}

	await page.waitForTimeout( 800 );
	out.push( { name, ...await stats() } );

	if ( /floor of 1240|folded as well/.test( name ) ) {
		await page.screenshot( { path: `test-results/fix-${ out.length }.png` } );
	}
}

fs.writeFileSync( 'test-results/look-list-fix.json', JSON.stringify( out, null, '\t' ) );

console.log( 'case                                    table  cols  File   median row   page' );
for ( const o of out ) {
	console.log(
		`  ${ o.name.padEnd( 36 ).slice( 0, 36 ) } ${ String( o.tableW ).padStart( 5 ) }  ${ String( o.columns ).padStart( 4 ) }  ` +
		`${ String( o.titleW ).padStart( 4 ) }px  ${ String( o.median ).padStart( 6 ) }px  ${ String( o.page ).padStart( 7 ) }px`
	);
}

await browser.close();
