/*
 *  Walk the sort flow the way a person does, and photograph every step.
 *
 *      node tools/flowwalk.mjs http://46.225.66.194 admin <password>
 *
 *  Every other check in this repo drives the endpoints. This one presses the
 *  buttons, which is the only way to find out whether the progress a long step
 *  shows is the progress it is actually making -- a bar wired to the wrong
 *  number is invisible to a test that never looks at the bar.
 *
 *  Five hundred pictures is minutes of describing, so it reports what it sees
 *  as it goes rather than at the end.
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

fs.mkdirSync( OUT, { recursive: true } );

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1600, height: 1000 } } );

page.on( 'pageerror', ( e ) => console.log( `  !! page error: ${ e.message }` ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );

async function shot( name ) {
	await page.screenshot( { path: path.join( OUT, `walk-${ name }.png` ), fullPage: true } );
	console.log( `  shot: walk-${ name }.png` );
}

async function where() {
	const head = await page.$eval( '#vgml-lib-stage h2, #vgml-lib-review h2', ( n ) => n.textContent.trim() ).catch( () => '?' );
	const line = await page.$eval( '.vgml-flow-line', ( n ) => n.textContent.replace( /\s+/g, ' ' ).trim() ).catch( () => '' );
	return `${ line } — ${ head }`;
}

async function go() {

	await page.goto( `${ BASE }/wp-admin/admin.php?page=media-librarian`, { waitUntil: 'networkidle' } );
	await page.waitForSelector( '#vgml-lib-stage h2, #vgml-lib-review h2', { timeout: 30000 } );
	await page.waitForTimeout( 1200 );
}

/* ------------------------------------------------------------- step: describe */

await go();
console.log( `\n${ await where() }` );

const cost = await page.$eval( '.vgml-flow-cost', ( n ) => n.textContent.replace( /\s+/g, ' ' ).trim() ).catch( () => '(none)' );
console.log( `  before pressing: ${ cost }` );
await shot( '1-describe-before' );

const describe = await page.$( 'button:has-text("Start describing")' );

if ( describe ) {

	await describe.click();

	// Watch the figure move. If it never changes, the bar is decorative.
	const seen = [];

	for ( let i = 0; i < 100; i++ ) {

		await page.waitForTimeout( 3000 );

		const now = await page.$eval( '.vgml-flow-count', ( n ) => n.textContent.trim() ).catch( () => '' );
		const eta = await page.$eval( '.vgml-flow-eta', ( n ) => n.textContent.trim() ).catch( () => '' );

		if ( now && now !== seen[ seen.length - 1 ] ) {
			seen.push( now );
			console.log( `  ${ now } ${ eta }` );
		}

		if ( i === 2 ) {
			await shot( '2-describe-running' );
		}

		const gone = await page.$( '.vgml-flow-count' );

		if ( ! gone ) {
			break; // the screen moved on
		}

		const done = await page.$eval( '.vgml-flow-count', ( n ) => n.textContent.trim() ).catch( () => '' );
		const [ a, b ] = done.split( ' of ' ).map( Number );

		if ( a && b && a >= b ) {
			break;
		}
	}

	console.log( `  the figure moved ${ seen.length } times` );
}

/* -------------------------------------------------------------- step: propose */

await go();
console.log( `\n${ await where() }` );
await shot( '3-propose-before' );

const propose = await page.$( 'button:has-text("Work out the folders")' );

if ( propose ) {
	await propose.click();
	await page.waitForTimeout( 2000 );
	await shot( '4-propose-running' );

	for ( let i = 0; i < 120; i++ ) {
		await page.waitForTimeout( 2000 );
		if ( ! ( await page.$( '.vgml-flow-progress:not([hidden])' ) ) ) {
			break;
		}
	}
}

/* --------------------------------------------------------------- step: choose */

await go();
console.log( `\n${ await where() }` );
await shot( '5-choose' );

const use = await page.$( '.vgml-lib-scheme button:has-text("Use this one")' );

if ( use ) {
	await use.click();
	await page.waitForSelector( '.vgml-lib-branch', { timeout: 60000 } ).catch( () => {} );
	await page.waitForTimeout( 2500 );
}

/* --------------------------------------------------------------- step: review */

console.log( `\n${ await where() }` );

const names = await page.$$eval( '.vgml-lib-branch h3, .vgml-lib-branch .vgml-lib-branch-name', ( ns ) =>
	ns.map( ( n ) => n.textContent.replace( /\s+/g, ' ' ).trim() )
).catch( () => [] );

console.log( `  ${ names.length } folders proposed` );
names.slice( 0, 25 ).forEach( ( n ) => console.log( `    ${ n }` ) );

await shot( '6-review' );

await browser.close();
