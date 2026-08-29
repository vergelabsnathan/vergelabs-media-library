/*
 *  Look at the screens.
 *
 *  Everything in this repo that checks the admin reads markup: status codes,
 *  the presence of a class, the text inside a tag. None of that can tell you a
 *  screen is unusable, and a screen was shipped that was.
 *
 *      node tools/shots.mjs http://46.225.66.194 admin <password>
 *
 *  Writes one PNG per screen into tools/shots/.
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

const SCREENS = [
	[ 'dashboard', 'admin.php?page=vergelabs-media' ],
	[ 'ai', 'admin.php?page=media-ai' ],
	[ 'librarian', 'admin.php?page=media-librarian' ],
	[ 'health', 'admin.php?page=media-health' ],
	[ 'import', 'admin.php?page=media-import-folders' ],
	[ 'settings-library', 'admin.php?page=media-library' ],
	[ 'media-grid', 'upload.php?mode=grid' ],
];

fs.mkdirSync( OUT, { recursive: true } );

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );

if ( page.url().includes( 'wp-login' ) ) {
	console.error( 'login failed' );
	await browser.close();
	process.exit( 1 );
}

for ( const [ name, url ] of SCREENS ) {
	await page.goto( `${ BASE }/wp-admin/${ url }`, { waitUntil: 'networkidle' } );
	// The screens fetch their own state; give the paint a moment to settle so
	// the shot is the screen rather than its spinner.
	await page.waitForTimeout( 2500 );

	const file = path.join( OUT, `${ name }.png` );
	await page.screenshot( { path: file, fullPage: true } );

	const h = await page.evaluate( () => document.body.scrollHeight );
	console.log( `${ name.padEnd( 18 ) } ${ h }px  ${ file }` );
}

await browser.close();
