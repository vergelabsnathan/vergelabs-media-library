import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  One full-page screenshot of every screen of ours, on every push. They
 *  cost a few seconds and they are what gets looked at when a screen is
 *  being redesigned; the assertions here are only that each one rendered.
 */

const SLUGS = {
	dashboard: SCREEN.dashboard,
	folders: SCREEN.folders,
	ai: SCREEN.ai,
	'ai-how': `${ SCREEN.ai }&tab=how`,
	'ai-search': `${ SCREEN.ai }&tab=search`,
	duplicates: SCREEN.duplicates,
	import: 'media-import-folders',
	licence: 'media-licence',
	taxonomies: SCREEN.taxonomies,
	library: 'media-library',
	filetypes: 'mime-types',
};

let planted = false;

for ( const [ name, slug ] of Object.entries( SLUGS ) ) {
	test( `screenshot: ${ name }`, async ( { page } ) => {
		const problems = [];
		page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );
		await page.setViewportSize( { width: 1440, height: 900 } );
		if ( name === 'folders' ) {
			/*
			 *  The conversation opens by itself on an empty session, and that
			 *  is a planner call. A screenshot on every push must not spend
			 *  one, so an empty session is given a turn first and emptied
			 *  again after.
			 */
			await open( page, slug );
			const s = await page.evaluate( () => wp.apiFetch( { path: '/vergeml/v1/guide/session' } ) );
			planted = ! ( s.session.turns || [] ).length;
			if ( planted ) {
				await page.evaluate( () => wp.apiFetch( { path: '/vergeml/v1/guide/session', method: 'POST', data: { reset: true } } )
					.then( () => wp.apiFetch( { path: '/vergeml/v1/guide/turn', method: 'POST', data: { said: { kind: 'said', text: 'Folders by subject.' }, say: { text: [ 'In the draft:', '- Nothing yet', 'Folders by subject, by use, or both?' ].join( '\n' ), choices: [ 'By subject', 'By use' ] } } } ) ) );
			}
		}
		// The brief's tab opens with an opener built on the server: no call to the service, none to mint a token.
		const service = [];
		page.on( 'request', ( r ) => {
			if ( /\/(brief|guide)\/(stream|session|token)$/.test( r.url() ) ) {
				service.push( r.url() );
			}
		} );
		await open( page, slug );
		await expect( page.locator( '.vgml-shell-content' ) ).toBeVisible();
		if ( name === 'folders' ) {
			// The screen paints from the data that came with the page; the conversation may open by itself afterwards and is not waited for.
			await expect( page.locator( '.vgml-folders.is-ready' ) ).toBeVisible( { timeout: 30000 } );
			await expect( page.locator( '.vgml-move-btn' ) ).toBeVisible();
		}
		if ( name === 'dashboard' ) {
			// Four counts in the rail, and nothing of the score that was there.
			await expect( page.locator( '.vgml-progress-row' ) ).toHaveCount( 4 );
			await expect( page.locator( '.vgml-scorecard, .vgml-score-n' ) ).toHaveCount( 0 );
		}
		if ( name === 'ai' ) {
			// Demo mode left this screen for the Licence screen.
			await expect( page.locator( '#vgml-ai-mock' ) ).toHaveCount( 0 );
			// Three tabs; the run's button says what it will do, with the number or the reason it cannot.
			await expect( page.locator( '.vgml-tabs .vgml-tab' ) ).toHaveCount( 3 );
			await expect( page.locator( '.vgml-tabs .vgml-tab.is-on' ) ).toHaveText( 'Describe' );
			await expect( page.locator( '#vgml-ai-run' ) ).toHaveText( /^Describe (\d[\d,.]* new pictures?|· nothing new)$/ );
			await expect( page.locator( '#vgml-ai-alt' ) ).toHaveText( /^Alt text (for \d[\d,.]*|· none missing)$/ );
			await expect( page.locator( '.vgml-ai-table tr' ) ).toHaveCount( 8 );
			await expect( page.locator( '#vgml-ai-counts' ) ).toHaveText( /^\d[\d,.]* pictures · \d[\d,.]* described · \d[\d,.]* with alt text/ );
		}
		if ( name === 'ai-how' ) {
			await expect( page.locator( '.vgml-tabs .vgml-tab.is-on' ) ).toHaveText( 'How it describes' );
			// The opener came with the page: facts, then one question, and no chip that costs anything.
			await expect( page.locator( '.vgml-brief-talk .vgml-msg.is-assistant' ).first() ).toContainText( /described with the brief on the right|No brief yet/ );
			await expect( page.locator( '.vgml-brief-talk .vgml-msg.is-assistant' ).first() ).toContainText( '?' );
			await expect( page.locator( '.vgml-composer-text' ) ).toBeVisible();
			await expect( page.locator( '.vgml-brief-panel .vgml-brief-block' ) ).toBeVisible();
			await expect( page.locator( '#vgml-brief-turns' ) ).toHaveText( /^\d+ of \d+ turns$/ );
			await expect( page.locator( '#vgml-ai-page-context' ) ).toHaveCount( 1 );
			await page.waitForTimeout( 1500 );
			expect( service, 'opening the tab costs nothing: no request to the service or for a token' ).toEqual( [] );
		}
		if ( name === 'ai-search' ) {
			await expect( page.locator( '.vgml-tabs .vgml-tab.is-on' ) ).toHaveText( 'Search' );
			await expect( page.locator( '#vgml-search-form' ) ).toBeVisible();
			await expect( page.locator( '.vgml-ai-table tr' ) ).toHaveCount( 4 );
			await expect( page.locator( '#vgml-ai-enrich' ) ).toHaveCount( 1 );
		}
		if ( name === 'duplicates' ) {
			// The report draws after the page; a look-alike set is one card with a side per picture.
			await expect( page.locator( '.vgml-health-list.is-related' ) ).toBeVisible( { timeout: 30000 } );
			const cards = page.locator( '.vgml-pair' );
			if ( await cards.count() ) {
				const first = cards.first();
				await expect( first.locator( '.vgml-pair-side' ) ).toHaveCount( Number( await first.getAttribute( 'data-n' ) ) );
				await expect( first.locator( '.vgml-pair-side' ).first().locator( '.vgml-pair-facts li' ) ).toHaveCount( 4 );
				await expect( first.locator( '.vgml-pair-keep' ).first() ).toHaveText( /^Keep this one · /, { useInnerText: true } );
				await expect( first.locator( '.vgml-pair-foot .vgml-btn' ) ).toHaveCount( 2 );
			}
			await expect( page.locator( '.vgml-health-band' ) ).toContainText( 'held by the extra copies' );
			await expect( page.locator( '#vgml-health-report .vgml-health-open' ) ).toHaveCount( 0 );
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
		if ( name === 'folders' && planted ) {
			// The session was empty when this spec found it; it is empty again.
			await page.evaluate( () => wp.apiFetch( { path: '/vergeml/v1/guide/session', method: 'POST', data: { reset: true } } ) );
			planted = false;
		}
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );
}
