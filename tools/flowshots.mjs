/*
 *  Look at the sort flow, at both widths, at whichever step the library is on.
 *
 *      node tools/flowshots.mjs http://46.225.66.194 admin <password>
 *
 *  tools/shots.mjs takes one picture of each screen at one width. This screen
 *  has two presentations of the same position -- a rail where there is room
 *  for it and a single line where there is not -- and a bug in either is
 *  invisible in a screenshot of the other.
 *
 *  Writes into tools/shots/flow-*.png.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const OUT = path.join( ROOT, 'tools', 'shots' );

const BASE = process.argv[ 2 ] ?? 'http://46.225.66.194';
const USER = process.argv[ 3 ] ?? 'admin';
const PASS = process.argv[ 4 ] ?? '';

const WIDTHS = [
	[ 'wide', 1600 ],
	[ 'narrow', 1000 ],
];

fs.mkdirSync( OUT, { recursive: true } );

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1600, height: 1000 } } );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );

if ( page.url().includes( 'wp-login' ) ) {
	console.error( 'login failed' );
	await browser.close();
	process.exit( 1 );
}

for ( const [ label, width ] of WIDTHS ) {

	await page.setViewportSize( { width, height: 1000 } );
	await page.goto( `${ BASE }/wp-admin/admin.php?page=media-librarian`, { waitUntil: 'networkidle' } );

	// The screen draws itself from three endpoints; networkidle is not enough
	// on its own because the steps are appended after the last of them lands.
	await page.waitForSelector( '#vgml-lib-stage .vgml-flow-card, #vgml-lib-stage .vgml-lib-chooser', { timeout: 20000 } ).catch( () => {} );

	const file = path.join( OUT, `flow-${ label }.png` );
	await page.screenshot( { path: file, fullPage: true } );

	// What the rail actually says, so a run that cannot be looked at still
	// reports something.
	const steps = await page.$$eval( '.vgml-flow-step-row', ( rows ) =>
		rows.map( ( r ) => r.textContent.replace( /\s+/g, ' ' ).trim() )
	).catch( () => [] );

	const line = await page.$eval( '.vgml-flow-line', ( n ) => n.textContent.replace( /\s+/g, ' ' ).trim() ).catch( () => '' );
	const head = await page.$eval( '#vgml-lib-stage h2', ( n ) => n.textContent.trim() ).catch( () => '' );

	console.log( `\n${ label } (${ width }px)  ->  ${ path.relative( ROOT, file ) }` );
	console.log( `  heading: ${ head }` );
	console.log( `  line:    ${ line || '(hidden)' }` );
	steps.forEach( ( s ) => console.log( `  step:    ${ s }` ) );
}

await browser.close();
