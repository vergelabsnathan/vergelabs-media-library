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

		const text = await page.evaluate( () => document.body.innerText );

		/*
		 *  Each part prints as "Label" then "points/weight". Full marks with
		 *  outstanding work is the contradiction: it tells somebody they are
		 *  done on the same screen that tells them they are not.
		 */
		const parts = Array.from( text.matchAll( /(Alt text|Described|Filed|Checked for copies)\s*\n?\s*(\d+)\s*\/\s*(\d+)/g ) )
			.map( ( m ) => ( { label: m[ 1 ], points: Number( m[ 2 ] ), weight: Number( m[ 3 ] ) } ) );

		test.skip( parts.length === 0, 'no score on this library yet' );

		const unfiled = /(\d[\d,]*)\s+files?\s+(?:is|are)\s+in\s+no\s+folder/i.exec( text );
		if ( unfiled !== null && Number( unfiled[ 1 ].replace( /,/g, '' ) ) > 0 ) {
			const filed = parts.find( ( p ) => p.label === 'Filed' );
			expect( filed, 'a Filed score should be shown' ).toBeTruthy();
			expect(
				filed.points,
				`"${ unfiled[ 0 ] }" is on the page, so Filed must not read full marks`
			).toBeLessThan( filed.weight );
		}

		const undescribed = /have not looked at\s+(\d[\d,]*)/i.exec( text );
		if ( undescribed !== null && Number( undescribed[ 1 ].replace( /,/g, '' ) ) > 0 ) {
			const described = parts.find( ( p ) => p.label === 'Described' );
			expect( described, 'a Described score should be shown' ).toBeTruthy();
			expect(
				described.points,
				'pictures are still to describe, so Described must not read full marks'
			).toBeLessThan( described.weight );
		}

		// And no part may exceed its own weight, ever.
		for ( const p of parts ) {
			expect( p.points, `${ p.label } cannot score above its weight` ).toBeLessThanOrEqual( p.weight );
		}
	} );

	test( 'the title rewrite says what it did', async ( { page } ) => {
		await open( page, SCREEN.dashboard );

		const button = page.getByRole( 'link', { name: /Rewrite the titles/i } );
		test.skip( ( await button.count() ) === 0, 'nothing to rewrite on this library' );

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
			'the screen must say what happened'
		).toBeVisible();
	} );

	test( 'the counts on the cards are numbers, not placeholders', async ( { page } ) => {
		await open( page, SCREEN.dashboard );
		const text = await page.evaluate( () => document.body.innerText );

		expect( text, 'no unresolved template markers' ).not.toMatch( /%[sd]\b|\{\{|\[Company\]/ );
		expect( text, 'no raw PHP notice leaked into the page' ).not.toMatch( /Warning:|Notice:|Deprecated:/ );
	} );
} );
