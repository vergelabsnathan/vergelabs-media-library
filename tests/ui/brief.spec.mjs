import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  The AI screen's two new tabs, driven.
 *
 *  Without BRIEF_WALK=1 nothing here asks the service for a model call: the
 *  Search tab's query is a word search and one embed the service does not
 *  charge, and the How it describes tab opens on an opener the plugin built.
 *  With BRIEF_WALK=1 the walk sends one message (a token and a turn, ten
 *  credits each on the service's meter), presses "Test on 5 pictures" (five
 *  credits, said in the log line before it runs), sees the five before/after
 *  rows, and discards the draft. It never uses the brief: that would
 *  re-describe the library. The session it found is put back to the opener.
 */

const WALK = process.env.BRIEF_WALK === '1';
const HOW = `${ SCREEN.ai }&tab=how`;
const SEARCH = `${ SCREEN.ai }&tab=search`;

test.describe( 'the AI screen', () => {

	test( 'Search: a query tried says what each hit matched on', async ( { page } ) => {
		const problems = [];
		page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await open( page, SEARCH );

		await page.fill( '#vgml-search-q', 'cosy winter evening' );
		await page.click( '#vgml-search-go' );

		const head = page.locator( '.vgml-hits-head li' );
		await expect( head.first() ).toContainText( /^By word: \d/, { timeout: 30000 } );
		await expect( head.nth( 1 ) ).toContainText( /^By meaning: /, { timeout: 30000 } );
		// Three words: every one has to match, and the line says how many carry each.
		await expect( head.first() ).toContainText( /every word has to match: "cosy" in \d+, "winter" in \d+, "evening" in \d+/ );
		const hits = page.locator( '.vgml-hit' );
		expect( await hits.count(), 'hits by word or by meaning' ).toBeGreaterThan( 0 );
		await expect( hits.first().locator( '.vgml-hit-w' ) ).toContainText( /No word from the query|only|, / );
		await page.screenshot( { path: 'test-results/shot-ai-search-query.png', fullPage: true } );
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );

	test( 'How it describes: opens on the free opener, with the brief and no control until there is a draft', async ( { page } ) => {
		const problems = [];
		page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );
		const service = [];
		page.on( 'request', ( r ) => {
			if ( /\/(brief|guide)\/(stream|session|token)$/.test( r.url() ) ) {
				service.push( r.url() );
			}
		} );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await open( page, HOW );

		await expect( page.locator( '.vgml-brief-talk .vgml-msg.is-assistant' ).first() ).toContainText( /described with the brief on the right|No brief yet/ );
		await expect( page.locator( '#vgml-brief-turns' ) ).toHaveText( /^\d+ of 25 turns$/ );
		await expect( page.locator( '.vgml-brief-panel .vgml-brief-block' ) ).toBeVisible();
		const session = await page.evaluate( () => wp.apiFetch( { path: '/vergeml/v1/brief/session' } ) );
		if ( ! session.session.draft ) {
			await expect( page.locator( '#vgml-brief-adopt' ) ).toHaveCount( 0 );
			await expect( page.locator( '#vgml-brief-test' ) ).toHaveCount( 0 );
		}
		await page.waitForTimeout( 1500 );
		expect( service, 'opening the tab costs nothing' ).toEqual( [] );
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );

	test( 'walk: a message streams the reply and the draft, Test on 5 pictures holds five rows, Discard drops the draft', async ( { page } ) => {
		test.skip( ! WALK, 'BRIEF_WALK=1 spends about 25 credits on the box: a token, one turn, five describes' );
		const problems = [];
		page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await open( page, HOW );

		// From an empty session, so the walk reads the same every time; the opener is written back by the boot.
		await page.evaluate( () => wp.apiFetch( { path: '/vergeml/v1/brief/session', method: 'POST', data: { reset: true } } ) );
		await open( page, HOW );

		await page.fill( '.vgml-composer-text', 'No. A stock photo library for our Dutch design studio: landscapes, city architecture, workspaces, studio portraits, and a small apparel line under our own name, Verge. No other brands.' );
		await page.keyboard.press( 'Enter' );
		await expect( page.locator( '.vgml-msg.is-user' ).last() ).toContainText( 'Dutch design studio' );
		await expect( page.locator( '.vgml-send.is-stop' ), 'send became Stop while the reply streams' ).toBeVisible( { timeout: 60000 } );
		await expect( page.locator( '.vgml-msg.is-streaming' ) ).toHaveCount( 0, { timeout: 120000 } );
		await expect( page.locator( '.vgml-msg.is-note' ) ).toHaveCount( 0 );
		await expect( page.locator( '#vgml-brief-turns' ) ).toHaveText( '1 of 25 turns' );

		// The draft landed on the right, in ink, with the controls that say their cost.
		await expect( page.locator( '.vgml-brief-panel .vgml-kicker' ).first() ).toHaveText( 'The brief · draft' );
		await expect( page.locator( '.vgml-brief-block .vgml-facts li' ).first() ).not.toBeEmpty();
		await expect( page.locator( '.vgml-brief-panel .vgml-kicker' ).nth( 1 ) ).toHaveText( /^\d+ of 500 characters$/ );
		await expect( page.locator( '#vgml-brief-adopt' ) ).toHaveText( /^Re-describe \d[\d,.]* pictures with this brief$/ );
		await expect( page.locator( '#vgml-brief-test' ) ).toHaveText( 'Test on 5 pictures · 5 credits' );
		await page.screenshot( { path: 'test-results/shot-ai-how-draft.png', fullPage: true } );

		// Five credits, said in the log before it runs; the answers held, nothing written.
		await page.click( '#vgml-brief-test' );
		await expect( page.locator( '.vgml-msg.is-user.is-test' ).last() ).toContainText( 'Test on 5 pictures · 5 credits' );
		await expect( page.locator( '#vgml-brief-test' ) ).toHaveText( 'Testing 5 pictures' );
		await expect( page.locator( '.vgml-msg.is-assistant.is-test' ) ).toBeVisible( { timeout: 180000 } );
		await expect( page.locator( '.vgml-diff' ) ).toHaveCount( 5 );
		await expect( page.locator( '.vgml-msg.is-assistant.is-test' ) ).toContainText( 'Nothing is written until the brief is used' );
		await expect( page.locator( '#vgml-brief-test' ) ).toHaveText( 'Test on 5 pictures · 5 credits' );
		await page.screenshot( { path: 'test-results/shot-ai-how-tested.png', fullPage: true } );

		// Discard: the draft goes, the controls with it, the line says so.
		await page.click( '#vgml-brief-discard' );
		await expect( page.locator( '#vgml-brief-adopt' ) ).toHaveCount( 0 );
		await expect( page.locator( '.vgml-msg.is-user.is-discard' ).last() ).toContainText( 'Discarded the draft' );

		// Back to the opener, which is what an empty session boots to.
		await page.evaluate( () => wp.apiFetch( { path: '/vergeml/v1/brief/session', method: 'POST', data: { reset: true } } ) );
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );
} );
