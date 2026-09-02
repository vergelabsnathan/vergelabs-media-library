import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  The dashboard.
 *
 *  Two things shipped from this screen that no lint could see: every action
 *  button did its work and then said nothing at all, because the results were
 *  printed through admin_notices and the plugin's own shell deletes those on
 *  its own screens; and the library score read full marks directly above a
 *  card saying work remained, because 379 of 380 rounds to 25 out of 25.
 */

test.describe( 'the dashboard', () => {

	test( 'renders without PHP falling over', async ( { page } ) => {
		await open( page, SCREEN.dashboard );
		await expect( page.locator( '.vgml-dash' ) ).toBeVisible();
	} );

	test( 'a score never claims finished while a card says work remains', async ( { page } ) => {
		await open( page, SCREEN.dashboard );

		/*
		 *  Read from the elements that hold the numbers, not by pattern
		 *  matching the prose around them. The first version of this regexed
		 *  innerText, matched nothing, and skipped itself green -- which is
		 *  exactly how a suite lies about what it covers.
		 */
		const parts = await page.$$eval( '.vgml-score-parts li', ( items ) =>
			items.map( ( li ) => {
				const label = li.querySelector( '.vgml-score-label' );
				const pts = li.querySelector( '.vgml-score-pts' );
				const raw = pts === null ? '' : pts.textContent.trim();
				const halves = raw.split( '/' );
				return {
					label: label === null ? '' : label.textContent.trim(),
					points: Number( halves[ 0 ] ),
					weight: Number( halves[ 1 ] ),
				};
			} )
		);

		expect(
			parts.length,
			'the score panel must be on the dashboard; if it is not, this test checks nothing'
		).toBeGreaterThan( 0 );

		const text = await page.evaluate( () => document.body.innerText );
		const part = ( name ) => parts.find( ( p ) => p.label === name );

		const unfiled = /(\d[\d,]*)\s+files?\s+(?:is|are)\s+in\s+no\s+folder/i.exec( text );
		if ( unfiled !== null && Number( unfiled[ 1 ].replace( /,/g, '' ) ) > 0 ) {
			const filed = part( 'Filed' );
			expect( filed, 'a Filed score should be shown' ).toBeTruthy();
			expect(
				filed.points,
				`"${ unfiled[ 0 ] }" is on this page, so Filed must not read full marks`
			).toBeLessThan( filed.weight );
		}

		const undescribed = /have not looked at\s+(\d[\d,]*)/i.exec( text );
		if ( undescribed !== null && Number( undescribed[ 1 ].replace( /,/g, '' ) ) > 0 ) {
			const described = part( 'Described' );
			expect( described, 'a Described score should be shown' ).toBeTruthy();
			expect(
				described.points,
				'pictures are still to describe, so Described must not read full marks'
			).toBeLessThan( described.weight );
		}

		for ( const p of parts ) {
			expect( p.points, `${ p.label } cannot score above its own weight` )
				.toBeLessThanOrEqual( p.weight );
			expect( Number.isFinite( p.points ), `${ p.label } must print a number` ).toBe( true );
		}
	} );

	test( 'the title rewrite says what it did', async ( { page } ) => {
		await open( page, SCREEN.dashboard );

		const button = page.getByRole( 'link', { name: /Rewrite the titles/i } );

		/*
		 *  One of two things must be true: work is offered, or the card says
		 *  there is none. Neither appearing means the card did not render, and
		 *  skipping on that would hide the failure rather than report it.
		 */
		if ( ( await button.count() ) === 0 ) {
			await expect(
				page.locator( 'text=/Every title/i' ).first(),
				'with no rewrite offered, the card must say the titles are done'
			).toBeVisible();
			return;
		}

		await button.first().click();
		await page.waitForLoadState( 'domcontentloaded' );

		/*
		 *  The exact failure: it worked, redirected back with the count in the
		 *  query string, and nothing on earth read it. A button that does its
		 *  job silently is indistinguishable from one that does nothing.
		 */
		expect( page.url(), 'the result should come back in the URL' ).toMatch( /vgml_renamed=/ );
		await expect(
			page.locator( '.notice' ).filter( { hasText: /title|rewritten|needed rewriting/i } ).first(),
			'and the screen must say what happened'
		).toBeVisible();
	} );

	test( 'the counts on the cards are numbers, not placeholders', async ( { page } ) => {
		await open( page, SCREEN.dashboard );
		const text = await page.evaluate( () => document.body.innerText );

		expect( text, 'no unresolved template markers' ).not.toMatch( /%[sd]\b|\{\{|\[Company\]/ );
		expect( text, 'no raw PHP notice leaked into the page' ).not.toMatch( /Warning:|Notice:|Deprecated:/ );
	} );
} );
