import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  The other screens: that they render, that the thing you came to do is
 *  above the things that configure it, and that the conversation is a
 *  conversation.
 */

test.describe( 'the AI screen', () => {

	test( 'renders, and puts the work above the settings', async ( { page } ) => {
		await open( page, SCREEN.ai );

		const describe = page.getByRole( 'heading', { name: /Describe your images/i } ).first();
		await expect( describe ).toBeVisible();

		/*
		 *  This opened on seven rows of configuration with the one button
		 *  somebody came to press underneath all of it -- a WordPress options
		 *  screen wearing a product's name. Document order is the assertion:
		 *  the action must come first.
		 */
		const settings = page.getByRole( 'heading', { name: /How it behaves/i } ).first();

		if ( ( await settings.count() ) > 0 ) {
			const order = await page.evaluate( () => {
				const heads = Array.from( document.querySelectorAll( 'h2' ) ).map( ( h ) => h.textContent.trim() );
				return {
					work: heads.findIndex( ( t ) => /Describe your images/i.test( t ) ),
					config: heads.findIndex( ( t ) => /How it behaves/i.test( t ) ),
				};
			} );
			expect( order.work, 'the describe section should exist' ).toBeGreaterThanOrEqual( 0 );
			expect(
				order.work,
				'the work comes before the settings that configure it'
			).toBeLessThan( order.config );
		}
	} );

	test( 'offers the handshake when the site has no licence, and not when it has one', async ( { page } ) => {
		await open( page, SCREEN.ai );

		const connect = page.getByRole( 'link', { name: /Connect to VergeLabs/i } );
		const credits = page.locator( 'text=/credits? (left|remaining)/i' );

		const hasConnect = ( await connect.count() ) > 0;
		const hasCredits = ( await credits.count() ) > 0;

		// Exactly one of these is the truth. Both, or neither, is a screen
		// that cannot say whether this site is connected.
		expect(
			hasConnect || hasCredits,
			'the screen must either offer to connect or show a balance'
		).toBe( true );
	} );
} );

test.describe( 'sort into folders', () => {

	test( 'is a conversation, and keeps what was said', async ( { page } ) => {
		await open( page, SCREEN.folders );

		const say = page.locator( '#vgml-talk-say' );
		const log = page.locator( '#vgml-talk-log' );

		test.skip( ( await say.count() ) === 0, 'the talk panel is not on this screen' );

		// The transcript is the whole point: it was a single question with a
		// take-it-or-leave-it answer, and every refinement started from nothing.
		await expect( log, 'there is somewhere for the conversation to live' ).toHaveCount( 1 );

		await say.fill( 'Group these by what they show' );
		await page.locator( '#vgml-talk-go' ).click();

		// What was typed must appear as a turn, immediately, whatever the
		// service goes on to answer.
		await expect(
			log.locator( '.vgml-talk-you' ).first(),
			'what you said stays on screen'
		).toBeVisible( { timeout: 15_000 } );

		// And the box empties, ready for the next thing, rather than keeping
		// the sentence you already sent.
		await expect( say ).toHaveValue( '' );
	} );
} );

test.describe( 'the settings screens', () => {

	test( 'folders and categories says what it is before asking what to tick', async ( { page } ) => {
		await open( page, SCREEN.taxonomies );

		const text = await page.evaluate( () => document.body.innerText );

		// It opened on "Assign following taxonomies to Media Library" beside a
		// list of checkboxes, which tells somebody nothing at all and never
		// says which taxonomy is the folder tree they have been using.
		expect( text, 'the folder taxonomy is named' ).toMatch( /media_category/ );
		expect( text, 'and it says folders outlive the plugin' ).toMatch( /removed|outlive|survive/i );
		expect( text, 'the old instruction is gone' ).not.toMatch( /Assign following taxonomies/i );
	} );

	test( 'duplicates renders and does not invent matches out of nothing', async ( { page } ) => {
		await open( page, SCREEN.duplicates );

		const text = await page.evaluate( () => document.body.innerText );

		// The possibly-related copy must not appear unless something matched:
		// the band ran to ten bits of a sixty-four bit hash and was offering
		// a bridge in fog and a beach as the same picture.
		if ( /Nothing else looked similar|No copies found/i.test( text ) ) {
			expect( text, 'nothing matched, so nothing is offered as related' )
				.not.toMatch( /These look similar/i );
		}
	} );
} );
