/*
 *  Render a mock's boards to PNG.
 *
 *      node tools/shoot-mock.mjs docs/superpowers/mocks/<file>.html [outDir]
 *
 *  Every board in a mock carries data-shot="<name>"; each one is shot on its
 *  own so a board can be sent into the conversation rather than a link to a
 *  file Nathan has to open. Two-times scale, because the point of a mock is
 *  that the type can be read.
 */

import { chromium } from 'playwright';
import path from 'node:path';
import fs from 'node:fs';
import { pathToFileURL } from 'node:url';

const file = process.argv[ 2 ];
const outDir = process.argv[ 3 ] ?? 'docs/superpowers/mocks/shots';

if ( ! file ) {
	throw new Error( 'usage: node tools/shoot-mock.mjs <mock.html> [outDir]' );
}

fs.mkdirSync( outDir, { recursive: true } );

const browser = await chromium.launch();
const page = await ( await browser.newContext( {
	viewport: { width: 1560, height: 1000 },
	deviceScaleFactor: 2,
} ) ).newPage();

const problems = [];
page.on( 'pageerror', ( e ) => problems.push( e.message ) );

await page.goto( pathToFileURL( path.resolve( file ) ).href, { waitUntil: 'networkidle' } );
await page.waitForTimeout( 600 );

const stem = path.basename( file, '.html' ).replace( /^\d{4}-\d{2}-\d{2}-/, '' );

for ( const board of await page.locator( '[data-shot]' ).all() ) {
	const name = await board.getAttribute( 'data-shot' );
	const out = `${ outDir }/${ stem }-${ name }.png`;
	await board.screenshot( { path: out } );
	console.log( `  ${ out }` );
}

if ( problems.length ) {
	console.log( `\njavascript errors: ${ problems.join( ' | ' ) }` );
}

await browser.close();
