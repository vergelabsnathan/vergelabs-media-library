/*
 *  The shop's way in (S20): a site that sells starts from its own product
 *  categories.
 *
 *      node tests/tree/folders-shop.mjs
 *
 *  Runs against tests/tree/folders-shop.html from disk -- no WordPress, no
 *  site, no box. S19 walked the real shop and found the Tree step had no way
 *  to make the nine product categories the tree: they were typed into the
 *  composer by hand, and a shop owner would not know to.
 *
 *  The press is a paste: the paths become "Clothing > Hoodies" lines and go
 *  through js/vergeml-structure.js, so the draft, the counting and the held
 *  confirm are what a paste already does. Nothing here asks the model.
 *
 *  Mutation: the categories button drawn whatever the tree (the `! hasTree`
 *  gate dropped) -> B7 red; the press left without STRUCT.parse -> B3–B5 red.
 */

import { chromium } from 'playwright';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const HARNESS = pathToFileURL( path.join( HERE, 'folders-shop.html' ) ).href;

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? '  -- ' + detail : '' }` );
};

const browser = await chromium.launch();
const errors = [];

const open = async ( state ) => {
	const page = await browser.newPage();
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( state ? `${ HARNESS }?state=${ state }` : HARNESS );
	await page.waitForSelector( '#vgml-folders .g-card[data-card="tree"]' );
	return page;
};

console.log( '\nB  the button on a site that sells, and the press behind it\n' );

const page = await open( '' );

const move = '#vgml-folders .g-card[data-card="tree"] .g-move';
const button = `${ move } .vgml-cats-btn`;

const label = await page.textContent( button ).catch( () => null );
check( 'B1 the move row offers the categories, with their count in the label', 'Use my 9 product categories' === label, JSON.stringify( label ) );

const primary = await page.$eval( move, ( row ) => {
	const first = row.querySelector( '.vgml-btn' );
	return first ? { text: first.textContent, primary: first.classList.contains( 'vgml-btn-primary' ) } : null;
} );
check( 'B2 it is the card\'s primary while the tree is empty', primary && primary.primary && 'Use my 9 product categories' === primary.text, JSON.stringify( primary ) );

/*
 *  Seen on the real shop, 2026-09-19: with the library described, Propose
 *  folders draws itself as the primary too whenever there is no tree, so the
 *  card offered two blue buttons side by side. One card, one primary.
 */
const primaries = await page.$$eval( `${ move } .vgml-btn-primary`, ( els ) => els.map( ( e ) => e.textContent ) );
check( 'B2b it is the only primary in the row: Propose folders stands beside it, quiet', 1 === primaries.length, JSON.stringify( primaries ) );

await page.click( button );
await page.waitForFunction( () => document.querySelectorAll( '#vgml-folders .g-tree-slot .vgml-node' ).length > 0, null, { timeout: 5000 } );

check( 'B3 the site is asked for its categories once, at the press', 1 === await page.evaluate( () => window.harness.categories ), String( await page.evaluate( () => window.harness.categories ) ) );

const drawn = await page.$$eval( '#vgml-folders .g-tree-slot .vgml-node .vgml-name', ( els ) => els.map( ( e ) => e.textContent ) );
check( 'B4 one press draws the nine, the six under Clothing among them', 9 === drawn.length && 'Clothing' === drawn[ 0 ] && drawn.includes( 'Hoodies' ) && drawn.includes( 'Music' ), JSON.stringify( drawn ) );

const shape = await page.$$eval( '#vgml-folders .g-tree-slot .vgml-node', ( els ) => els.map( ( e ) => Number( e.getAttribute( 'aria-level' ) ) ) );
check( 'B5 Clothing is the parent: six rows one level deeper', 6 === shape.filter( ( n ) => 2 === n ).length && 3 === shape.filter( ( n ) => 1 === n ).length, JSON.stringify( shape ) );

const pasteShut = await page.$eval( '#vgml-folders .vgml-paste', ( e ) => e.hidden );
check( 'B6 the paste panel stays shut: nothing was refused', true === pasteShut, String( pasteShut ) );

// The counts are the matcher's, as after any paste: the draft goes to the turn route and the confirm waits for it.
await page.waitForFunction( () => window.harness.calls.some( ( c ) => /guide\/turn$/.test( c.path ) ), null, { timeout: 5000 } ).catch( () => {} );
const turn = await page.evaluate( () => window.harness.calls.filter( ( c ) => /guide\/turn$/.test( c.path ) ) );
check( 'B7 the draft settles through the turn route, so its numbers are the matcher\'s', 1 === turn.length && turn[ 0 ].data && turn[ 0 ].data.draft && 9 === turn[ 0 ].data.draft.folders.length, JSON.stringify( turn.map( ( t ) => ( t.data && t.data.draft ? t.data.draft.folders.length : null ) ) ) );

check( 'B8 no model route was asked: no turn of the conversation, no proposal', ! ( await page.evaluate( () => window.harness.calls.some( ( c ) => /guide\/(token|stream|propose)$/.test( c.path ) ) ) ), JSON.stringify( await page.evaluate( () => window.harness.calls.map( ( c ) => c.path ) ) ) );

await page.close();

const withTree = await open( 'tree' );
const gone = await withTree.$$( button );
check( 'B9 with a folder already on the site the button is gone: the primary is the tree\'s own', 0 === gone.length, `${ gone.length } buttons` );
await withTree.close();

check( 'B10 nothing threw', 0 === errors.length, errors.join( ' | ' ) );

await browser.close();

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
