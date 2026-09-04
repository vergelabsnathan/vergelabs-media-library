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
		await page.waitForTimeout( 800 );
		await page.screenshot( { path: `test-results/shot-${ name }.png`, fullPage: true } );
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );
}
