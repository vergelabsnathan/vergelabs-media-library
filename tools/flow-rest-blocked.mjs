/*
 *  Block the REST API the way a security plugin does, and see whether the
 *  folders survive it.
 *
 *      VGML_USER=admin VGML_PASS=... node tools/flow-rest-blocked.mjs [http://46.225.66.194]
 *
 *  Both market leaders answer a blocked API by drawing "no folders", which
 *  reads as data loss. This opens the media library with /wp-json/ answering
 *  403 to everything and reports: did the tree draw, how many folders, did
 *  the fallback road announce itself, and did a folder click still filter
 *  the grid. The blocking itself is done by the caller (an mu-plugin on the
 *  box); this only looks.
 */
import { chromium } from 'playwright';

const BASE = ( process.argv[ 2 ] || 'http://46.225.66.194' ).replace( /\/$/, '' );
const USER = process.env.VGML_USER || '';
const PASS = process.env.VGML_PASS || '';
if ( ! USER || ! PASS ) { console.error( 'set VGML_USER / VGML_PASS' ); process.exit( 1 ); }

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1400, height: 900 } } );
const rest = [];
const bridge = [];
const errors = [];
page.on( 'response', ( r ) => {
	const u = r.url();
	if ( u.includes( '/wp-json/' ) || u.includes( 'rest_route=' ) ) rest.push( r.status() + ' ' + u.replace( BASE, '' ).split( '?' )[ 0 ] );
	if ( u.includes( 'admin-ajax.php?action=vergeml_rest' ) ) bridge.push( r.status() );
} );
page.on( 'pageerror', ( e ) => errors.push( ( e && e.message ) || String( e ) ) );
// A rejected promise carrying a plain object shows up as "Object"; say what was in it.
await page.addInitScript( () => {
	window.addEventListener( 'unhandledrejection', ( ev ) => {
		let r = ev.reason;
		try { r = typeof r === 'object' ? JSON.stringify( r ).slice( 0, 200 ) : String( r ); } catch { r = String( r ); }
		console.error( 'UNHANDLED ' + r );
	} );
} );
page.on( 'console', ( m ) => { if ( m.type() === 'error' ) errors.push( 'console: ' + m.text().slice( 0, 160 ) ); } );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER ); await page.fill( '#user_pass', PASS );
await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#wp-submit' ) ] );

await page.goto( `${ BASE }/wp-admin/upload.php?mode=grid`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree', { timeout: 20000 } );
await page.waitForTimeout( 4000 );

const folders = await page.locator( '.vgml-tree .vgml-node' ).count();
const strip = ( await page.locator( '.vgml-transport' ).first().textContent().catch( () => '' ) ) || '';
const stripClass = ( await page.locator( '.vgml-transport' ).first().getAttribute( 'class' ).catch( () => '' ) ) || '';

// Click a folder and see whether the grid narrows (that request goes through REST too).
const before = await page.locator( '.attachments .attachment' ).count();
let clicked = 'no visible folder row';
try {
	// A folder that holds files, when the scale fixture is present; else any visible one.
	let row = page.locator( '.vgml-tree .vgml-row:visible', { hasText: 'vgml-scale-0001' } ).first();
	if ( ! ( await row.count() ) ) row = page.locator( '.vgml-tree .vgml-row:visible' ).nth( 1 );
	if ( await row.count() ) { await row.click( { timeout: 5000 } ); clicked = 'clicked ' + ( ( await row.textContent() ) || '' ).trim().slice( 0, 30 ); await page.waitForTimeout( 3000 ); }
} catch ( e ) { clicked = 'click failed: ' + String( e.message ).split( '\n' )[ 0 ].slice( 0, 80 ); }
const after = await page.locator( '.attachments .attachment' ).count();
console.log( `  folder click       ${ clicked }` );
// The strip may only appear once a request has actually taken the fallback road.
await page.waitForTimeout( 2000 );
const stripLate = ( await page.locator( '.vgml-transport' ).first().textContent().catch( () => '' ) ) || '';
const stripLateClass = ( await page.locator( '.vgml-transport' ).first().getAttribute( 'class' ).catch( () => '' ) ) || '';
console.log( `  strip after click  ${ stripLateClass.includes( 'is-bridged' ) ? 'bridged' : stripLateClass.includes( 'is-failed' ) ? 'FAILED' : 'none' }  "${ stripLate.trim().slice( 0, 90 ) }"` );

console.log( `\n  folders drawn      ${ folders }` );
console.log( `  REST responses     ${ rest.length ? rest.join( ' ' ) : 'none' }` );
console.log( `  bridge responses   ${ bridge.length ? bridge.join( ' ' ) : 'none' }` );
console.log( `  strip              ${ stripClass.includes( 'is-bridged' ) ? 'bridged' : stripClass.includes( 'is-failed' ) ? 'FAILED' : 'none' }  "${ strip.trim().slice( 0, 90 ) }"` );
console.log( `  grid before/after  ${ before } / ${ after }` );
console.log( `  page errors        ${ errors.length ? errors.slice( 0, 3 ).join( ' | ' ).slice( 0, 200 ) : 'none' }\n` );
await browser.close();
process.exit( folders > 0 && ! stripClass.includes( 'is-failed' ) && errors.length === 0 ? 0 : 1 );
