import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  The guide's first screen, on every push. It costs nothing but SQL, so
 *  unlike the full walk it can run each time; the screenshot is the thing
 *  to look at when the screen is being argued about.
 */

test( 'the guide opens inside the shell and says what the library holds', async ( { page } ) => {
	const problems = [];
	page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );

	await open( page, SCREEN.guide );

	// The shell's navigation stays: the guide is a screen, not a takeover.
	await expect( page.locator( '.vgml-shell-nav' ), 'the shell navigation must stay visible on the guide' ).toBeVisible();

	// Whatever an earlier session left, look at screen 1.
	await page.evaluate( () => wp.apiFetch( { path: '/vergeml/v1/guide/session', method: 'POST', data: { session: { state: 'library', draft: { folders: [], tags: [] } } } } ) );
	await page.reload( { waitUntil: 'domcontentloaded' } );

	const what = page.locator( '.vgml-guide-what .vgml-guide-list li' );
	await expect( what.first(), 'the screen lists what the pictures are' ).toBeVisible( { timeout: 30000 } );
	await expect( page.locator( '.vgml-guide-today' ) ).toBeVisible();
	await expect( page.locator( '.vgml-guide-goal textarea' ) ).toBeVisible();

	await page.screenshot( { path: 'test-results/guide-1-library.png', fullPage: true } );

	expect( problems, problems.join( '\n' ) ).toEqual( [] );
} );
