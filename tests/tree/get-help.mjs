/*
 *  The Get help screen, end to end against a real site and the real service.
 *
 *      node tests/tree/get-help.mjs http://46.225.66.194 vgml-smoke <password>
 *
 *  It sends one real ticket (question prefixed "[gate]") -- that is the point:
 *  a support path that is only ever tested against a mock is the one that
 *  fails on the day somebody needs it.
 */
import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://46.225.66.194';
const USER = process.argv[ 3 ] ?? 'admin';
const PASS = process.argv[ 4 ] ?? '';

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( ok );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? `  -- ${ detail }` : '' }` );
};

const browser = await chromium.launch();
const page = await ( await browser.newContext( { viewport: { width: 1400, height: 1000 } } ) ).newPage();
page.setDefaultTimeout( 60000 );
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message.slice( 0, 120 ) ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 1500 );

console.log( '\nthe way in' );
await page.goto( `${ BASE }/wp-admin/admin.php?page=vergelabs-media`, { waitUntil: 'domcontentloaded' } );
check( 'the dashboard offers Get help', await page.locator( 'a[href*="page=media-help"]' ).count() > 0 );

console.log( '\nthe screen' );
await page.goto( `${ BASE }/wp-admin/admin.php?page=media-help`, { waitUntil: 'domcontentloaded' } );
check( 'it renders', await page.locator( '.vgml-help' ).count() === 1 );
check( 'the known-problems section is there', /Known problems that match this site/.test( await page.textContent( '.vgml-help' ) ) );
check( 'the report is on the screen and names this WordPress', await page.evaluate( () => {
	const t = document.querySelector( '.vgml-help textarea[readonly]' );
	return !! t && /### WordPress/.test( t.value ) && /### Active plugins/.test( t.value );
} ) );
check( 'the report does not carry a licence key', await page.evaluate( () => {
	const t = document.querySelector( '.vgml-help textarea[readonly]' );
	return !! t && ! /v1:/.test( t.value ) && ! /license_key|licence key/i.test( t.value );
} ) );
check( 'the consent line says what is sent and what is not', await page.evaluate( () => {
	const text = document.querySelector( '.vgml-help form' ).textContent;
	return /active theme and plugins/.test( text ) && /does not contain your licence key/.test( text );
} ) );

console.log( '\nwithout consent' );
await page.fill( '#vergeml_question', '[gate] consent check -- should not send' );
await page.click( '.vgml-help button[type=submit]' );
await page.waitForLoadState( 'domcontentloaded' );
check( 'it refuses to send and says why', /vgml-help=consent/.test( page.url() ) && /Tick the box/.test( await page.textContent( '.vgml-help' ) ) );

console.log( '\nwith consent' );
await page.fill( '#vergeml_question', `[gate] Get help wiring check ${ new Date().toISOString() }` );
await page.check( 'input[name=vergeml_consent]' );
await page.click( '.vgml-help button[type=submit]' );
await page.waitForLoadState( 'domcontentloaded' );
const url = page.url();
const m = url.match( /ticket=(\d+)/ );
check( 'it sends and comes back with a ticket number', /vgml-help=sent/.test( url ) && !! m, url.slice( -60 ) );
check( 'the success notice names the ticket', !! m && new RegExp( `#${ m[ 1 ] }` ).test( await page.textContent( '.vgml-help' ) ) );

check( 'no javascript errors', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

await browser.close();
const bad = results.filter( ( x ) => ! x ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed${ m ? `  (ticket #${ m[ 1 ] })` : '' }\n` );
process.exit( bad ? 1 : 0 );
