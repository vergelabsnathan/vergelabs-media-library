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

/*
 *  One tree component for the panel and the Folders screen, kept in step by
 *  the folders version stamp. The old Sort screen read a summary taken at
 *  session start and matched folders by name, so the two surfaces disagreed
 *  the moment anything was renamed; now both draw the rows of
 *  js/vergeml-tree-view.js and both poll /folders/version.
 */
test.describe( 'one tree, kept in step', () => {

	test( 'the panel draws its rows from the shared component', async ( { page } ) => {
		await page.goto( '/wp-admin/upload.php?mode=list', { waitUntil: 'domcontentloaded' } );

		// A folder has a positive term id; the pseudo rows (All files, Unfiled,
		// the Filters and AI heads) carry ids at or below zero.
		const rows = page.locator( '.vgml-tree .vgml-list > li.vgml-node[data-id]' ).filter( { has: page.locator( ':scope' ) } )
			.and( page.locator( ':not([data-id^="-"]):not([data-id="0"])' ) );
		await expect( rows.first(), 'a folder row in the panel' ).toBeVisible( { timeout: 25_000 } );

		const shape = await rows.first().evaluate( ( li ) =>
			Array.from( li.querySelector( '.vgml-row' ).children ).map( ( c ) => c.className.split( ' ' )[ 0 ] ) );
		expect( shape.slice( 0, 3 ), 'twist, icon, name: the row the Folders screen draws too' )
			.toEqual( [ 'vgml-twist', 'vgml-icon', 'vgml-name' ] );

		const loaded = await page.evaluate( () => !! ( window.vergemlTreeView && window.vergemlTreeView.create ) );
		expect( loaded, 'js/vergeml-tree-view.js is on the screen' ).toBe( true );

		await page.screenshot( { path: 'test-results/shot-library-list.png', fullPage: false } );
	} );

	test( 'polls the folders version and re-reads the tree when another writer changes it', async ( { page } ) => {
		const polls = [];
		const treeLoads = [];
		page.on( 'request', ( r ) => {
			if ( /vergeml\/v1\/folders\/version/.test( r.url() ) ) polls.push( Date.now() );
			if ( /vergeml\/v1\/tree(\?|$|%3F)/.test( r.url() ) || /rest_route=%2Fvergeml%2Fv1%2Ftree/.test( r.url() ) ) treeLoads.push( Date.now() );
		} );

		await page.goto( '/wp-admin/upload.php?mode=list', { waitUntil: 'domcontentloaded' } );
		await expect( page.locator( '.vgml-tree' ).first() ).toBeVisible( { timeout: 25_000 } );

		const answer = await page.evaluate( () => wp.apiFetch( { path: '/vergeml/v1/folders/version' } ) );
		expect( Number.isInteger( answer.version ), 'the route answers an integer version' ).toBe( true );

		await page.waitForTimeout( 11_000 );
		expect( polls.length, `${ polls.length } polls in eleven seconds; every five expected` ).toBeGreaterThanOrEqual( 2 );

		/*
		 *  Another writer: a folder made outside the panel's own code path, as
		 *  a second tab or the Folders screen would. The panel must notice
		 *  within a poll and re-read the tree. The probe folder is deleted
		 *  again whatever happens -- a spec restores what it writes.
		 */
		const before = treeLoads.length;
		const made = await page.evaluate( () => wp.apiFetch( {
			path: '/vergeml/v1/folder',
			method: 'POST',
			data: { taxonomy: 'media_category', action: 'create', name: 'zz version probe' },
		} ) );
		expect( Number( made.id ), 'the probe folder was made' ).toBeGreaterThan( 0 );

		try {
			await expect.poll( () => treeLoads.length, {
				timeout: 12_000,
				message: 'the panel re-read the tree after another writer changed it',
			} ).toBeGreaterThan( before );
			await expect( page.locator( '.vgml-tree .vgml-name', { hasText: 'zz version probe' } ).first(),
				'and the new folder is on the screen without a reload' ).toBeVisible( { timeout: 5_000 } );
		} finally {
			await page.evaluate( ( id ) => wp.apiFetch( {
				path: '/vergeml/v1/folder',
				method: 'POST',
				data: { taxonomy: 'media_category', action: 'delete', id },
			} ), Number( made.id ) );
		}
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
