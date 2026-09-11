/*
 *  The destructive buttons ask before they act.
 *
 *      node tests/security/dialogs.mjs http://127.0.0.1:8899 [user] [pass]
 *
 *  Complete Cleanup on the settings screen posts a form that deletes every
 *  option, table and term this plugin owns. It is meant to ask first. From some
 *  rename until 2026-09-11 it did not ask and did not act: the click handler
 *  called emlConfirmDialog(), a name nothing defined, and threw. This opens the
 *  screen, presses the button, and asserts that the dialog appears -- and then
 *  presses Cancel, because the point is that the question is asked, not that
 *  the answer is yes.
 *
 *  Playground rather than the box: the button under test deletes data if the
 *  dialog is ever answered yes, and a test that can only ever do that to a
 *  throwaway site is a test that can be run without thinking.
 *
 *  Mutation check run on 2026-09-11: `vergemlConfirmDialog(` at
 *  js/eml-options.js:15 changed back to `emlConfirmDialog(` and the plugin
 *  reloaded → "pressing Delete All Data opens a confirmation" FAILs.
 */

import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://127.0.0.1:8899';
const USER = process.argv[ 3 ] ?? process.env.UI_USER ?? 'admin';
const PASS = process.argv[ 4 ] ?? process.env.UI_PASS ?? 'password';

const results = [];

function check( what, ok, detail ) {
	results.push( { ok, what } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' }  ${ what }${ ok || ! detail ? '' : `  -- ${ detail }` }` );
}

console.log( `\nthe destructive buttons ask first (${ BASE })\n` );

const browser = await chromium.launch();
const page = await ( await browser.newContext( { viewport: { width: 1400, height: 1000 } } ) ).newPage();
page.setDefaultTimeout( 30000 );

const errors = [];
page.on( 'pageerror', ( e ) => errors.push( String( e.message ?? e ) ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 2000 );

/* The trap in every handoff: a timeout on a selector is usually a failed login. */
check( 'logged in', ! page.url().includes( 'wp-login.php' ), `still at ${ page.url() }` );

await page.goto( `${ BASE }/wp-admin/options-general.php?page=eml-settings`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 1500 );

const button = page.locator( '#eml-submit-settings-cleanup' );
check( 'the Delete All Data button is on the settings screen', await button.count() > 0 );

await button.first().click();
await page.waitForTimeout( 1200 );

const dialog = page.locator( '#eml-dialog-modal' );
const shown = await dialog.count() > 0 && await dialog.first().isVisible();

check( 'pressing Delete All Data opens a confirmation', shown, errors.length ? `page errors: ${ errors.join( ' | ' ).slice( 0, 200 ) }` : 'no #eml-dialog-modal appeared' );
/*
 *  Playwright reports a ReferenceError as its bare message -- "emlConfirmDialog
 *  is not defined" -- with no "ReferenceError" in it, so the first version of this
 *  row stayed green while the row above it failed on exactly that error.
 */
check( 'nothing on the screen threw "is not defined"', ! errors.some( ( e ) => /is not defined/.test( e ) ), errors.join( ' | ' ).slice( 0, 200 ) );

/* Cancel, never confirm. Nothing on this site is ours to delete in a test. */
if ( shown ) {
	const cancel = page.locator( '.eml-dialog-modal .ui-dialog-buttonset button' ).last();
	if ( await cancel.count() ) await cancel.click();
	await page.waitForTimeout( 500 );
	check( 'Cancel closes it and nothing was submitted', ! ( await dialog.first().isVisible().catch( () => false ) ) && page.url().includes( 'page=eml-settings' ) );
}

await browser.close();

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
