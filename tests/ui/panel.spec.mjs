import { test, expect } from './fixtures.mjs';

/*
 *  The folders panel is on the media library in BOTH views.
 *
 *  It was absent from list mode by decision until 2026-09-10 -- measured, and
 *  wrong for the product: Nathan asked three times for the folders to be
 *  visible there. Nothing tested list mode's panel, which is why its absence
 *  survived three reports. This is that test.
 *
 *  Run against the box, which is where both views are real:
 *      npx playwright test --config tests/ui/playwright.config.mjs panel.spec
 */

const look = async ( page, mode ) => {
	await page.goto( `/wp-admin/upload.php?mode=${ mode }`, { waitUntil: 'domcontentloaded', timeout: 120000 } );

	// The panel is built after the host element exists, which in grid means
	// after wp.media has drawn its frame.
	await expect( page.locator( '.vgml-tree' ), `the folders panel is on the ${ mode } screen` )
		.toBeVisible( { timeout: 45000 } );

	return page.evaluate( () => {
		const root = document.querySelector( '.vgml-tree' );
		const box = root.getBoundingClientRect();
		return {
			width: Math.round( box.width ),
			onScreen: box.width > 0 && box.height > 0 && box.left >= 0,
			fold: document.querySelectorAll( '.vgml-fold' ).length,
			modeClass: document.body.className.split( /\s+/ ).filter( ( c ) => c.startsWith( 'vgml-mode' ) ),
		};
	} );
};

for ( const mode of [ 'list', 'grid' ] ) {

	test( `the folders panel is on the media library in ${ mode } view`, async ( { page } ) => {
		test.setTimeout( 240000 );
		await page.setViewportSize( { width: 1600, height: 1000 } );

		const seen = await look( page, mode );
		console.log( `  ${ mode }: ${ JSON.stringify( seen ) }` );

		expect( seen.onScreen, 'and it is actually on the screen, not off to one side' ).toBe( true );
		expect( seen.width, 'at a width somebody can read' ).toBeGreaterThan( 100 );
		expect( seen.fold, 'with the control that folds it' ).toBeGreaterThan( 0 );

		await page.screenshot( { path: `tests/ui/shots/panel-${ mode }.png` } );
	} );
}

test( 'the list table keeps its columns beside the panel', async ( { page } ) => {
	test.setTimeout( 240000 );
	await page.setViewportSize( { width: 1600, height: 1000 } );

	await look( page, 'list' );

	/*
	 *  The measurement that kept the panel off this screen: thirteen columns
	 *  crushed into what was left, the median row 1,960px tall. The table now
	 *  scrolls sideways instead, so no row should be anywhere near that.
	 */
	const rows = await page.evaluate( () => {
		const trs = [ ...document.querySelectorAll( '.wp-list-table tbody tr' ) ].slice( 0, 25 );
		const heights = trs.map( ( tr ) => Math.round( tr.getBoundingClientRect().height ) ).sort( ( a, b ) => a - b );
		return { counted: heights.length, median: heights[ Math.floor( heights.length / 2 ) ] ?? 0, tallest: heights[ heights.length - 1 ] ?? 0 };
	} );

	console.log( `  list rows: ${ JSON.stringify( rows ) }` );
	expect( rows.counted, 'there are rows to measure' ).toBeGreaterThan( 0 );
	expect( rows.median, 'a row is a row, not a paragraph' ).toBeLessThan( 400 );
} );
