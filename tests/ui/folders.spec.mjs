import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  Sorting into folders, as a conversation.
 *
 *  This screen carried two things that proposed folders -- a step-by-step
 *  wizard and a chat window -- and showed both at once, each with its own set
 *  of folders on screen and nothing to say which one the Do it button meant.
 *  You could ask for Apparel with Women and Men under it, watch the
 *  conversation agree, and still be reading the wizard's original suggestions
 *  directly above it.
 *
 *  Nothing here sends a message to the service. Asking it to plan a library
 *  costs time and a request on every push, and none of what broke needs a
 *  real reply to catch: the failures were in what the page shows before and
 *  around the answer.
 */

test.describe( 'the sort into folders screen', () => {

	test( 'leads with the conversation, not the wizard', async ( { page } ) => {
		await open( page, SCREEN.folders );

		const talk = page.locator( '#vgml-talk' );
		const flow = page.locator( '#vgml-lib-flow' );

		await expect( talk, 'the chat window is the thing this screen is for' ).toBeVisible();
		await expect( flow, 'the step flow is still offered' ).toHaveCount( 1 );

		/*
		 *  Document order decides which one a person meets first, and the chat
		 *  used to be underneath. Comparing positions rather than reading the
		 *  markup means this still holds if the layout is rebuilt.
		 */
		const order = await page.evaluate( () => {
			const t = document.getElementById( 'vgml-talk' );
			const f = document.getElementById( 'vgml-lib-flow' );
			if ( t === null || f === null ) return null;
			return t.compareDocumentPosition( f ) & Node.DOCUMENT_POSITION_FOLLOWING ? 'talk first' : 'flow first';
		} );

		expect( order, 'the conversation should come before the step flow' ).toBe( 'talk first' );
	} );

	test( 'offers openers, and takes them away once you have started', async ( { page } ) => {
		await open( page, SCREEN.folders );

		const empty = page.locator( '#vgml-talk-empty' );
		const chips = empty.locator( '.vgml-talk-chip' );

		await expect( empty, 'an empty chat window needs to say what it understands' ).toBeVisible();
		expect( await chips.count(), 'there should be openers to press' ).toBeGreaterThan( 0 );

		/*
		 *  Typed, not sent: this drives the same code the send path runs
		 *  through without asking the service to plan anything. What is being
		 *  checked is that starting a conversation rearranges the screen.
		 */
		await page.evaluate( () => {
			const say = document.getElementById( 'vgml-talk-say' );
			say.value = 'Sort these into Apparel, with Women and Men under it';
			document.getElementById( 'vgml-talk-go' ).click();
		} );

		await expect(
			empty,
			'the openers must leave when there is a conversation to read instead'
		).toBeHidden( { timeout: 10_000 } );

		await expect(
			page.locator( '#vgml-lib-flow' ),
			'the wizard must fold away rather than sit above the chat proposing different folders'
		).toBeHidden( { timeout: 10_000 } );

		await expect(
			page.locator( '#vgml-lib-fold' ),
			'and the page must say where it went'
		).toBeVisible();
	} );

	test( 'can bring the wizard back', async ( { page } ) => {
		await open( page, SCREEN.folders );

		await page.evaluate( () => {
			const say = document.getElementById( 'vgml-talk-say' );
			say.value = 'group them by room';
			document.getElementById( 'vgml-talk-go' ).click();
		} );

		const fold = page.locator( '#vgml-lib-fold' );
		await expect( fold ).toBeVisible( { timeout: 10_000 } );

		await page.locator( '#vgml-lib-unfold' ).click();

		await expect(
			page.locator( '#vgml-lib-flow' ),
			'folding the wizard away must not be a one-way door'
		).toBeVisible();
	} );

	test( 'the composer stays with the conversation', async ( { page } ) => {
		await open( page, SCREEN.folders );

		/*
		 *  The transcript, the box and the status line were three separate
		 *  elements on the page, so the box drifted further from the
		 *  conversation with every reply. One panel is what keeps them
		 *  together; if the box escapes it, this screen is back to being a
		 *  form with a log bolted on.
		 */
		const inside = await page.evaluate( () => {
			const panel = document.querySelector( '.vgml-talk-panel' );
			const say = document.getElementById( 'vgml-talk-say' );
			const log = document.getElementById( 'vgml-talk-log' );
			if ( panel === null || say === null || log === null ) return null;
			return panel.contains( say ) && panel.contains( log );
		} );

		expect( inside, 'the box you type in and the transcript belong in one frame' ).toBe( true );
	} );

	test( 'every string on this screen is translatable', async ( { page } ) => {
		await open( page, SCREEN.folders );

		/*
		 *  These were localised under an 'l10n' key the script never read, so
		 *  every lookup missed and every word came from the English fallback
		 *  baked into the JavaScript -- on a translated site too, with nothing
		 *  raised to say so. A shape check is the only way this is visible
		 *  from outside, since both paths render the same English words.
		 */
		const strings = await page.evaluate( () => window.vergemlTalk || null );

		expect( strings, 'the script must be handed its strings' ).not.toBeNull();
		expect(
			strings.l10n,
			'nesting these under l10n is what made every one of them unreachable'
		).toBeUndefined();

		for ( const key of [ 'thinking', 'apply', 'failed', 'refine', 'noFolders' ] ) {
			expect( typeof strings[ key ], `${ key } must be readable where the script looks` ).toBe( 'string' );
			expect( strings[ key ].length, `${ key } must not be empty` ).toBeGreaterThan( 0 );
		}
	} );
} );
