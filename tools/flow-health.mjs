/*
 *  Drive the Duplicates screen the way a person does, and fail if it does not
 *  finish.
 *
 *      VGML_USER=admin VGML_PASS=... node tools/flow-health.mjs [http://46.225.66.194]
 *      pnpm flow:health
 *
 *  Signs in to wp-admin in headless Chromium, opens Duplicates, presses Scan,
 *  and watches the REST calls and the page's own errors until the report is
 *  drawn or something gives up.
 *
 *  Exists because on 31-08-2026 the scan completed -- twenty-one steps, every
 *  one a 200 -- and the screen said "Comparing…" for ever: the report crashed
 *  while drawing the first deletable set. Rendering the page empty (the
 *  render smoke) passed; PHP linted; the JS parsed. Only pressing the button
 *  on a library with a duplicate in it found it. So this presses the button.
 *
 *  Credentials come from the environment, never from this file: it is
 *  committed, and the box is on the public internet.
 */
import { chromium } from 'playwright';

const BASE = ( process.argv[ 2 ] || 'http://46.225.66.194' ).replace( /\/$/, '' );
const USER = process.env.VGML_USER || '';
const PASS = process.env.VGML_PASS || '';

if ( ! USER || ! PASS ) {
	console.error( '\n  set VGML_USER and VGML_PASS to an administrator on the box\n' );
	process.exit( 1 );
}

const browser = await chromium.launch();
const page = await browser.newPage();

const calls = [];
const errors = [];
page.on( 'response', async ( r ) => {
	if ( ! r.url().includes( '/vergeml/v1/health' ) ) return;
	let body = '';
	try { body = ( await r.text() ).slice( 0, 160 ).replace( /\s+/g, ' ' ); } catch {}
	calls.push( { status: r.status(), path: r.url().replace( BASE, '' ).split( '?' )[ 0 ], body } );
} );
page.on( 'requestfailed', ( r ) => { if ( r.url().includes( '/vergeml/v1/health' ) ) calls.push( { status: 0, path: r.url().replace( BASE, '' ), body: r.failure()?.errorText || 'failed' } ); } );
page.on( 'pageerror', ( e ) => errors.push( e.message ) );
page.on( 'console', ( m ) => { if ( m.type() === 'error' ) errors.push( m.text() ); } );

// wp-admin never reaches networkidle (heartbeat), so wait for the DOM only.
await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 30000 } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 30000 } ), page.click( '#wp-submit' ) ] );

await page.goto( `${ BASE }/wp-admin/admin.php?page=media-health`, { waitUntil: 'domcontentloaded', timeout: 30000 } );
await page.waitForSelector( '#vgml-health-scan', { timeout: 15000 } );

const reportsBefore = calls.filter( ( c ) => c.path.endsWith( 'health-report' ) ).length;
await page.click( '#vgml-health-scan' );

/*
 *  Finished means: a scan step answered done:true and a report was fetched
 *  after it. The button label is not used -- it reads "Scan again" as soon
 *  as any report exists, including the one from before the click.
 */
const started = Date.now();
let finished = false;
let note = '';
while ( Date.now() - started < 180000 ) {
	await page.waitForTimeout( 2000 );
	note = ( await page.locator( '#vgml-health-note' ).textContent().catch( () => '' ) ) || '';
	const done = calls.some( ( c ) => c.path.endsWith( 'health-scan' ) && /"done":true/.test( c.body ) );
	const reported = calls.filter( ( c ) => c.path.endsWith( 'health-report' ) ).length > reportsBefore;
	if ( done && reported ) { finished = true; break; }
	if ( /did not work/i.test( note ) ) break;
	if ( calls.some( ( c ) => c.status >= 400 || c.status === 0 ) ) break;
}

const groups = await page.locator( '.vgml-health-group' ).count();
const steps = calls.filter( ( c ) => c.path.endsWith( 'health-scan' ) ).length;
const bad = calls.filter( ( c ) => c.status >= 400 || c.status === 0 );

console.log( `\n  ${ finished ? 'ok     ' : 'FAILED ' } scan ${ steps } steps in ${ Math.round( ( Date.now() - started ) / 1000 ) }s · report drew ${ groups } sets · note "${ note.trim().slice( 0, 60 ) }"` );
for ( const c of bad.slice( 0, 5 ) ) console.log( `         ${ c.status } ${ c.path }  ${ c.body }` );
for ( const e of errors.slice( 0, 5 ) ) console.log( `         page error: ${ e.slice( 0, 200 ) }` );
console.log( '' );

await browser.close();
process.exit( finished && errors.length === 0 && bad.length === 0 ? 0 : 1 );
