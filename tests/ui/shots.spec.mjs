import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  One full-page screenshot of every screen of ours, on every push. They
 *  cost a few seconds and they are what gets looked at when a screen is
 *  being redesigned; the assertions here are only that each one rendered.
 */

const SLUGS = {
	dashboard: SCREEN.dashboard,
	sort: SCREEN.folders,
	ai: SCREEN.ai,
	duplicates: SCREEN.duplicates,
	import: 'media-import-folders',
	licence: 'media-licence',
	taxonomies: SCREEN.taxonomies,
	library: 'media-library',
	filetypes: 'mime-types',
};

for ( const [ name, slug ] of Object.entries( SLUGS ) ) {
	test( `screenshot: ${ name }`, async ( { page } ) => {
		const problems = [];
		page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await open( page, slug );
		await expect( page.locator( '.vgml-shell-content' ) ).toBeVisible();
		if ( name === 'sort' ) {
			// The app draws once the session has loaded; a planner call may follow, and is not waited for.
			await expect( page.locator( '.vgml-sort-steps' ) ).toBeVisible( { timeout: 30000 } );
		}
		if ( name === 'dashboard' ) {
			// Four counts in the rail, and nothing of the score that was there.
			await expect( page.locator( '.vgml-progress-row' ) ).toHaveCount( 4 );
			await expect( page.locator( '.vgml-scorecard, .vgml-score-n' ) ).toHaveCount( 0 );
		}
		if ( name === 'ai' ) {
			// Demo mode left this screen for the Licence screen.
			await expect( page.locator( '#vgml-ai-mock' ) ).toHaveCount( 0 );
		}
		if ( name === 'library' ) {
			// Share library counts: the switch and its three lines live here, and nowhere else.
			await expect( page.locator( '#vgml-stats-opt' ) ).toHaveCount( 1 );
			await expect( page.locator( '.vgml-facts li' ) ).toHaveCount( 3 );
			await expect( page.locator( 'body' ) ).toContainText( 'Never a file name, a title, a folder name or a picture' );
		}
		if ( name === 'licence' ) {
			// The switch exists only while no key is present; the band says which state this is.
			const band = await page.locator( '.vgml-status-band' ).innerText();
			await expect( page.locator( '#vgml-demo-mode' ) ).toHaveCount( /Not connected/.test( band ) ? 1 : 0 );
		}
		await page.waitForTimeout( 800 );
		await page.screenshot( { path: `test-results/shot-${ name }.png`, fullPage: true } );
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );
}
