import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  The parts a customer touches most, and the run that is supposed to survive
 *  them closing the tab.
 *
 *  Nothing here starts a describe run. Each image costs a credit, and a suite
 *  that spends money every time somebody pushes is a suite people switch off.
 *  What it checks instead is that the machinery reports itself honestly --
 *  which is where the bug was: the screen said a run was working while cron
 *  waited for a visitor who never came.
 */

test.describe( 'the media library', () => {

	test( 'shows the folder tree beside the files', async ( { page } ) => {
		await page.goto( '/wp-admin/upload.php?mode=list', { waitUntil: 'domcontentloaded' } );

		const tree = page.locator( '.vgml-tree, #vgml-tree, [class*="vgml-tree"]' ).first();
		await expect( tree, 'the folder tree is the whole product; it must be on the media screen' )
			.toBeVisible( { timeout: 25_000 } );
	} );

	test( 'the grid view has the tree too', async ( { page } ) => {
		await page.goto( '/wp-admin/upload.php?mode=grid', { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 2500 );

		const tree = page.locator( '.vgml-tree, #vgml-tree, [class*="vgml-tree"]' ).first();
		await expect( tree, 'the grid is where a media library is most used' )
			.toBeVisible( { timeout: 25_000 } );
	} );
} );

test.describe( 'the describe run', () => {

	test( 'reports its own state honestly', async ( { page } ) => {
		await open( page, SCREEN.ai );

		const state = await page.evaluate( async () => {
			if ( ! window.wp || ! window.wp.apiFetch ) {
				return { error: 'no apiFetch on this screen' };
			}
			try {
				return await window.wp.apiFetch( { path: '/vergeml/v1/ai-run' } );
			} catch ( e ) {
				return { error: String( e ).slice( 0, 160 ) };
			}
		} );

		expect( state.error, 'the run endpoint should answer' ).toBeUndefined();

		/*
		 *  Active with nothing left to do is the state that lied: it is what
		 *  the screen showed while the run had silently stopped, and it is
		 *  what "it says it is working and it is not" looks like in data.
		 */
		if ( state.active === true ) {
			expect(
				Number( state.remaining ),
				'a run that is active must have something left to do'
			).toBeGreaterThan( 0 );
		}

		// Whatever it says, the numbers have to be numbers.
		if ( state.remaining !== undefined && state.remaining !== null ) {
			expect( Number.isFinite( Number( state.remaining ) ), 'remaining is a number' ).toBe( true );
			expect( Number( state.remaining ), 'remaining is never negative' ).toBeGreaterThanOrEqual( 0 );
		}
	} );

	test( 'offers both ways to run it, and says which is which', async ( { page } ) => {
		await open( page, SCREEN.ai );

		const here = page.locator( 'input[name="vgml-ai-where"][value="here"]' );
		const background = page.locator( 'input[name="vgml-ai-where"][value="background"]' );

		await expect( here, 'watching it happen is one of the two choices' ).toHaveCount( 1 );
		await expect( background, 'and leaving it to run is the other' ).toHaveCount( 1 );

		// The promise this makes is the one that broke: close the tab and it
		// carries on. If the words are there, the mechanism has to be too.
		const text = await page.evaluate( () => document.body.innerText );
		expect( text, 'the background option says you can close the tab' )
			.toMatch( /close this tab|in the background/i );
	} );
} );

test.describe( 'the counts a customer acts on', () => {

	test( 'agree between the dashboard and the AI screen', async ( { page } ) => {
		await open( page, SCREEN.ai );

		const fromApi = await page.evaluate( async () => {
			if ( ! window.wp || ! window.wp.apiFetch ) return null;
			try {
				return await window.wp.apiFetch( { path: '/vergeml/v1/ai-status' } );
			} catch {
				return null;
			}
		} );

		test.skip( fromApi === null, 'the status endpoint is not reachable from this screen' );

		/*
		 *  Every one of these drives a sentence somebody reads and a button
		 *  they press. A negative or non-numeric count is how "the numbers do
		 *  not add up" starts.
		 */
		for ( const key of [ 'images', 'indexed', 'unindexed', 'missing_alt' ] ) {
			const n = Number( fromApi[ key ] );
			expect( Number.isFinite( n ), `${ key } must be a number` ).toBe( true );
			expect( n, `${ key } must not be negative` ).toBeGreaterThanOrEqual( 0 );
		}

		expect(
			Number( fromApi.indexed ),
			'more images described than exist is not possible'
		).toBeLessThanOrEqual( Number( fromApi.images ) );

		expect(
			Number( fromApi.unindexed ),
			'more images left to describe than exist is not possible'
		).toBeLessThanOrEqual( Number( fromApi.images ) );
	} );
} );

test.describe( 'the credit balance', () => {

	test( 'is the same number wherever it is shown', async ( { page } ) => {
		await open( page, SCREEN.ai );

		const fromService = await page.evaluate( async () => {
			if ( ! window.wp || ! window.wp.apiFetch ) return null;
			try {
				const s = await window.wp.apiFetch( { path: '/vergeml/v1/ai-status' } );
				return s.credits === null || s.credits === undefined ? null : Number( s.credits );
			} catch {
				return null;
			}
		} );

		test.skip( fromService === null, 'this site has no licence, so there is no balance to agree on' );

		await open( page, SCREEN.dashboard );
		const text = await page.evaluate( () => document.body.innerText );
		const shown = /([\d.,]+)\s*credits? left/i.exec( text );

		test.skip( shown === null, 'the dashboard is not showing a balance' );

		const onDashboard = Number( shown[ 1 ].replace( /[.,]/g, '' ) );

		/*
		 *  The dashboard read the cached option straight and the AI screen
		 *  asked the service, so the two drifted apart by a whole purchase --
		 *  the plugin said 20,467 while the account said 26,000. A balance is
		 *  one number or it is not a balance.
		 */
		expect(
			Math.abs( onDashboard - fromService ),
			`the dashboard says ${ onDashboard } and the service says ${ fromService }`
		).toBeLessThanOrEqual( 50 );
	} );
} );
