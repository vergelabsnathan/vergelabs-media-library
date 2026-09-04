import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  The guide, walked with a real browser on the box, with a screenshot of
 *  each screen kept as an artifact. The proposal step calls the planner,
 *  so it is given time; nothing here applies.
 */

test( 'the guide walks from the library to shaping', async ( { page } ) => {
	test.setTimeout( 180000 );
	const problems = [];
	page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );

	await open( page, SCREEN.guide );

	// Start clean, whatever an earlier session left behind.
	await page.evaluate( () => wp.apiFetch( { path: '/vergeml/v1/guide/session', method: 'POST', data: { session: { state: 'library', draft: { folders: [], tags: [] } } } } ) );
	await page.reload( { waitUntil: 'domcontentloaded' } );

	await expect( page.locator( '.vgml-guide-tile' ).first() ).toBeVisible( { timeout: 30000 } );
	await page.screenshot( { path: 'test-results/guide-1-library.png', fullPage: true } );

	await page.fill( '.vgml-guide-goal textarea', 'a fashion and lifestyle shop' );
	await page.click( '.vgml-guide-confirm .button-primary' );
	await expect( page.locator( '.vgml-guide-proposal' ).first() ).toBeVisible( { timeout: 120000 } );
	await page.screenshot( { path: 'test-results/guide-2-proposal.png', fullPage: true } );

	await page.click( '.vgml-guide-proposal .button-primary' );
	await expect( page.locator( '.vgml-guide-chat' ) ).toBeVisible( { timeout: 30000 } );
	await page.screenshot( { path: 'test-results/guide-3-shaping.png', fullPage: true } );

	await page.fill( '.vgml-guide-compose input', 'I want shoes split by size, colour and brand.' );
	await page.click( '.vgml-guide-compose .button-primary' );
	await expect( page.locator( '.vgml-msg.is-thinking' ) ).toBeVisible( { timeout: 15000 } );
	await expect( page.locator( '.vgml-msg.is-thinking' ) ).toHaveCount( 0, { timeout: 90000 } );
	await page.screenshot( { path: 'test-results/guide-3b-turn.png', fullPage: true } );

	await page.click( '.vgml-guide-treepane .vgml-guide-confirm .button-primary' );
	await expect( page.locator( 'h1' ) ).toHaveText( /before anything moves/, { timeout: 30000 } );
	await page.screenshot( { path: 'test-results/guide-4-review.png', fullPage: true } );

	expect( problems, problems.join( '\n' ) ).toEqual( [] );
} );
