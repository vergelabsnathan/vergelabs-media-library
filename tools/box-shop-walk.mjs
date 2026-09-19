/*
 *  The real shop walked as its owner, from an empty tree to a filled library.
 *
 *      UI_PASS=… node tools/box-shop-walk.mjs
 *
 *  The shop is blog 3 of the ms2 network (S19): 27 products, 9 product
 *  categories, 33 pictures, 32 of them on products. The walk is the way in
 *  S20 built -- one press on "Use my N product categories", the confirm, the
 *  fill -- and every step is shot into docs/superpowers/mocks/shots/, because
 *  a screen nobody looked at is a screen nobody tested.
 *
 *  It writes: the tree is made and the pictures are filed, as a shop owner's
 *  press would. Start from an empty tree (the folders removed and the three
 *  Folders options deleted by their constants) or the button is not there to
 *  press. The session admin is the caller's -- tools/box-ui-admin.php --
 *  never a borrowed one.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';

const BASE = 'http://shop.ms2.46.225.66.194.nip.io';
const USER = 'vgmls20';
const PASS = process.env.UI_PASS;
const OUT = 'docs/superpowers/mocks/shots';
const shots = [];

const shot = async ( page, name ) => {
	const file = `${ OUT }/2026-09-19-shop-way-in-${ name }.png`;
	await page.screenshot( { path: file, fullPage: false } );
	shots.push( file );
	console.log( `  shot ${ file }` );
};

const browser = await chromium.launch();
const page = await ( await browser.newContext( { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 } ) ).newPage();
page.on( 'pageerror', ( e ) => console.log( `  page error: ${ e.message }` ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 400 );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
const puzzle = page.locator( 'input[name="jetpack_protect_num"]' );
if ( await puzzle.count() ) {
	const asked = await page.locator( 'label[for="jetpack_protect_answer"]' ).innerText();
	const sum = /(\d+)\D+(\d+)/.exec( asked.replace( / /g, ' ' ) );
	if ( sum ) {
		await puzzle.fill( String( Number( sum[ 1 ] ) + Number( sum[ 2 ] ) ) );
	}
}
await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ).catch( () => null ), page.click( '#wp-submit' ) ] );
console.log( `  signed in: ${ page.url() }` );

await page.goto( `${ BASE }/wp-admin/admin.php?page=media-librarian`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '#vgml-folders .g-card[data-card="tree"]', { state: 'attached', timeout: 20000 } );
await page.waitForTimeout( 800 );
console.log( '  step: ' + await page.getAttribute( '#vgml-folders', 'data-step' ) );
await shot( page, '01-tree-empty' );
if ( 'tree' !== await page.getAttribute( '#vgml-folders', 'data-step' ) ) { await page.click( '.g-rail .g-step[data-step="tree"]' ); await page.waitForTimeout( 600 ); }

const cats = page.locator( '#vgml-folders .g-card[data-card="tree"] .g-move .vgml-cats-btn' );
console.log( `  the button says: ${ JSON.stringify( await cats.textContent() ) }` );

await cats.click();
await page.waitForFunction( () => document.querySelectorAll( '#vgml-folders .g-tree-slot .vgml-node' ).length > 0, null, { timeout: 20000 } );
await page.waitForTimeout( 300 );
await shot( page, '02-pressed' );

// The counts come back from the turn route; the confirm is off until they do.
await page.waitForFunction( () => {
	const b = document.querySelector( '#vgml-folders .vgml-confirm-btn' );
	return b && ! b.disabled;
}, null, { timeout: 30000 } ).catch( () => console.log( '  the confirm stayed off' ) );
await page.waitForTimeout( 500 );
await shot( page, '03-counted' );

const rows = await page.$$eval( '#vgml-folders .g-tree-slot .vgml-node .vgml-name', ( els ) => els.map( ( e ) => e.textContent.trim() ) );
console.log( `  the tree: ${ rows.join( ' | ' ) }` );

await page.click( '#vgml-folders .vgml-confirm-btn' );
await page.waitForTimeout( 1200 );
await shot( page, '04-confirming' );
await page.waitForFunction( () => /Next: Fill/.test( document.querySelector( '#vgml-folders .g-card[data-card="tree"] .g-move' )?.textContent || '' ), null, { timeout: 120000 } ).catch( () => console.log( '  the confirm did not finish in two minutes' ) );
await shot( page, '05-confirmed' );

// The confirm moves the screen to Fill by itself; a rail press is the way back if it did not.
if ( await page.locator( '#vgml-folders .g-card[data-card="fill"]' ).isHidden() ) {
	await page.click( '.g-rail .g-step[data-step="fill"]' );
}
await page.waitForSelector( '#vgml-folders .g-card[data-card="fill"]:not([hidden])', { timeout: 20000 } );
await page.waitForTimeout( 1500 );
await shot( page, '06-fill-step' );

const fill = page.locator( '#vgml-folders .vgml-move-btn' );
console.log( `  the fill button says: ${ JSON.stringify( await fill.textContent() ) }` );
await fill.click();
await page.waitForTimeout( 2500 );
await shot( page, '07-filling' );
await page.waitForFunction( () => ! /Filling|Confirming/.test( document.querySelector( '#vgml-folders .g-card[data-card="fill"]' )?.textContent || '' ), null, { timeout: 180000 } ).catch( () => console.log( '  the fill did not finish in three minutes' ) );
await page.waitForTimeout( 1500 );
await shot( page, '08-filled' );

const pills = await page.$$eval( '#vgml-folders .g-card[data-card="fill"] .g-pill', ( els ) => els.map( ( e ) => e.textContent.replace( /\s+/g, ' ' ).trim() ) );
console.log( `  the fill card: ${ pills.join( ' · ' ) }` );

await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 1500 );
await shot( page, '09-library' );

console.log( `\n  ${ shots.length } shots` );
fs.writeFileSync( 'docs/superpowers/mocks/shots/.walk-s20.json', JSON.stringify( { rows, pills, shots }, null, 1 ) );
await browser.close();
