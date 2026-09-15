/*
 *  Render a mock's boards to PNG, and count their words.
 *
 *      node tools/shoot-mock.mjs docs/superpowers/mocks/<file>.html [outDir]
 *      node tools/shoot-mock.mjs <mock.html> --viewports 1600x1000,1280x800 --words
 *
 *  Every board in a mock carries data-shot="<name>"; each one is shot on its
 *  own so a board can be sent into the conversation rather than a link to a
 *  file Nathan has to open. Two-times scale, because the point of a mock is
 *  that the type can be read.
 *
 *  --viewports shoots the viewport, not the board, at each size, loading the
 *  board alone with ?state=<name> (the mocks since 2026-09-15 hide the other
 *  boards on that). --words counts each board's words the way the suite does
 *  (tests/ui/folders.spec.mjs): tokens of innerText that carry a letter or a
 *  digit, once with the tree component (.vgml-tv) and once without it -- the
 *  tree is the person's own data and grows with their library, so the screen's
 *  budget of 80 is the second number.
 */

import { chromium } from 'playwright';
import path from 'node:path';
import fs from 'node:fs';
import { pathToFileURL } from 'node:url';

const positional = [];
let viewports = null;
let WORDS = false;
for ( let i = 2; i < process.argv.length; i++ ) {
	const a = process.argv[ i ];
	if ( '--viewports' === a ) {
		viewports = process.argv[ ++i ];
	} else if ( '--words' === a ) {
		WORDS = true;
	} else {
		positional.push( a );
	}
}
const file = positional[ 0 ];
const outDir = positional[ 1 ] ?? 'docs/superpowers/mocks/shots';

if ( ! file ) {
	throw new Error( 'usage: node tools/shoot-mock.mjs <mock.html> [outDir] [--viewports WxH,WxH] [--words]' );
}

fs.mkdirSync( outDir, { recursive: true } );

/** The rule, in one place: what the folders suite counts. */
export const WORDS_JS = `( el ) => {
	const count = ( t ) => String( t || '' ).split( /\\s+/ ).filter( ( x ) => /[\\p{L}\\p{N}]/u.test( x ) ).length;
	const all = count( el.innerText );
	const clone = el.cloneNode( true );
	clone.querySelectorAll( '.vgml-tv' ).forEach( ( t ) => t.remove() );
	el.parentNode.insertBefore( clone, el.nextSibling );
	const without = count( clone.innerText );
	clone.remove();
	return { with: all, without };
}`;

const browser = await chromium.launch();
const url = pathToFileURL( path.resolve( file ) ).href;
const stem = path.basename( file, '.html' ).replace( /^\d{4}-\d{2}-\d{2}-/, '' );
const problems = [];

async function boardNames() {
	const page = await browser.newPage();
	await page.goto( url, { waitUntil: 'networkidle' } );
	const names = await page.$$eval( '[data-shot]', ( els ) => els.map( ( e ) => e.getAttribute( 'data-shot' ) ) );
	await page.close();
	return names;
}

if ( viewports ) {
	const sizes = viewports.split( ',' ).map( ( s ) => s.split( 'x' ).map( Number ) );
	for ( const name of await boardNames() ) {
		for ( const [ width, height ] of sizes ) {
			const page = await ( await browser.newContext( { viewport: { width, height }, deviceScaleFactor: 2 } ) ).newPage();
			page.on( 'pageerror', ( e ) => problems.push( e.message ) );
			await page.goto( `${ url }?state=${ name }`, { waitUntil: 'networkidle' } );
			await page.waitForTimeout( 400 );
			const out = `${ outDir }/${ stem }-${ name }-${ width }x${ height }.png`;
			await page.screenshot( { path: out } );
			const words = WORDS ? await page.$eval( '[data-shot]:not([hidden])', new Function( 'return ' + WORDS_JS )() ) : null;
			console.log( `  ${ out }${ words ? `  words ${ words.without } (with the tree ${ words.with })` : '' }` );
			await page.context().close();
		}
	}
} else {
	const page = await ( await browser.newContext( { viewport: { width: 1560, height: 1000 }, deviceScaleFactor: 2 } ) ).newPage();
	page.on( 'pageerror', ( e ) => problems.push( e.message ) );
	await page.goto( url, { waitUntil: 'networkidle' } );
	await page.waitForTimeout( 600 );
	for ( const board of await page.locator( '[data-shot]' ).all() ) {
		const name = await board.getAttribute( 'data-shot' );
		const out = `${ outDir }/${ stem }-${ name }.png`;
		await board.screenshot( { path: out } );
		const words = WORDS ? await board.evaluate( new Function( 'return ' + WORDS_JS )() ) : null;
		console.log( `  ${ out }${ words ? `  words ${ words.without } (with the tree ${ words.with })` : '' }` );
	}
}

if ( problems.length ) {
	console.log( `\njavascript errors: ${ problems.join( ' | ' ) }` );
}

await browser.close();
