import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  The dashboard.
 *
 *  Two things shipped from this screen that no lint could see: every action
 *  button did its work and then said nothing at all, because the results were
 *  printed through admin_notices and the plugin's own shell deletes those on
 *  its own screens; and the library score read full marks directly above a
 *  card saying work remained, because 379 of 380 rounds to 25 out of 25.
 *
 *  The score is gone since 3.16.2. What stands in its place is four rows,
 *  each its own count, and the rule here is that no row can say more than
 *  its count: no total, no percentage, and no action once it is finished.
 */

/** The four progress rows, read from the elements that hold the numbers. */
async function progressRows( page ) {
	return page.$$eval( '.vgml-progress-row', ( items ) =>
		items.map( ( li ) => {
			const label = li.querySelector( '.vgml-progress-label' );
			const count = li.querySelector( '.vgml-progress-count' );
			const raw = count === null ? '' : count.textContent.trim();
			const halves = raw.split( /\s+of\s+/ );
			const num = ( s ) => Number( String( s ).replace( /[,.\s]/g, '' ) );
			const fill = li.querySelector( '.vgml-import-fill' );
			return {
				label: label === null ? '' : label.textContent.trim(),
				count: raw,
				n: num( halves[ 0 ] ),
				m: num( halves[ 1 ] ),
				fill: fill === null ? null : parseFloat( fill.style.width ),
				actions: li.querySelectorAll( 'a' ).length,
				text: li.textContent,
			};
		} )
	);
}

test.describe( 'the dashboard', () => {

	test( 'renders without PHP falling over', async ( { page } ) => {
		await open( page, SCREEN.dashboard );
		await expect( page.locator( '.vgml-dash' ) ).toBeVisible();
	} );

	test( 'four progress rows, each its own count, and no total', async ( { page } ) => {
		await open( page, SCREEN.dashboard );

		const rows = await progressRows( page );

		expect(
			rows.map( ( r ) => r.label ),
			'the four rows, in this order'
		).toEqual( [ 'Alt text', 'Described', 'Filed', 'Checked for copies' ] );

		// The score, in any of its old shapes.
		expect( await page.locator( '.vgml-scorecard, .vgml-score-n, .vgml-score-parts' ).count() ).toBe( 0 );
		const block = await page.locator( '.vgml-progress' ).innerText();
		expect( block, 'no "/100" anywhere near the rows' ).not.toMatch( /\/\s*100/ );

		for ( const r of rows ) {
			expect( r.count, `${ r.label } prints "N of M"` ).toMatch( /^\d[\d,.]*\s+of\s+\d[\d,.]*$/ );
			expect( r.n, `${ r.label }: N cannot exceed M` ).toBeLessThanOrEqual( r.m );
			expect( r.fill, `${ r.label }: the bar is at N/M` ).toBeCloseTo( r.m > 0 ? ( r.n / r.m ) * 100 : 100, 0 );
			if ( r.n === r.m ) {
				expect( r.actions, `${ r.label } is finished and offers nothing` ).toBe( 0 );
			} else {
				expect( r.actions, `${ r.label } has one action` ).toBe( 1 );
			}
			// A row carries its own two numbers and nothing else: no
			// percentage, no points, no weight, no total under it.
			const digits = r.text.replace( r.count, '' ).match( /\d/g );
			expect( digits, `${ r.label } prints no number beyond "N of M"` ).toBeNull();
		}

		/*
		 *  The exact bug: a sentence on the page says files are in no folder,
		 *  so the Filed row must not read finished.
		 */
		const text = await page.evaluate( () => document.body.innerText );
		const unfiled = /(\d[\d,]*)\s+files?\s+in\s+no\s+folder/i.exec( text );
		if ( unfiled !== null && Number( unfiled[ 1 ].replace( /,/g, '' ) ) > 0 ) {
			const filed = rows.find( ( r ) => r.label === 'Filed' );
			expect( filed.n, `"${ unfiled[ 0 ] }" is on this page, so Filed must not read full` ).toBeLessThan( filed.m );
		}
	} );

	test( 'a to-do row only when there is something to do', async ( { page } ) => {
		await open( page, SCREEN.dashboard );

		const rows = await page.$$eval( '.vgml-do', ( items ) =>
			items.map( ( row ) => {
				const n = row.querySelector( '.vgml-do-n' );
				const title = row.querySelector( '.vgml-do-title' );
				return {
					id: row.getAttribute( 'data-todo' ),
					blocked: row.classList.contains( 'is-blocked' ),
					n: n === null ? '' : n.textContent.trim(),
					title: title === null ? '' : title.textContent.trim(),
					buttons: row.querySelectorAll( 'a' ).length,
				};
			} )
		);

		for ( const r of rows ) {
			if ( r.blocked ) {
				expect( r.n, `${ r.id } is blocked: no count` ).toBe( '' );
				expect( r.buttons, `${ r.id } is blocked: no button` ).toBe( 0 );
				continue;
			}
			// The count is in the number column or in the title; either way it is above zero.
			const shown = r.n !== '' ? r.n : ( /^(\d[\d,.]*)/.exec( r.title ) || [ '', '' ] )[ 1 ];
			expect( Number( shown.replace( /[,.]/g, '' ) ), `${ r.id } shows a count above zero` ).toBeGreaterThan( 0 );
			expect( r.buttons, `${ r.id } has one action` ).toBeGreaterThan( 0 );
		}

		// Files, not folders.
		const folders = rows.find( ( r ) => r.id === 'folders' );
		if ( folders !== undefined ) {
			expect( folders.title ).toMatch( /^\d[\d,.]*\s+files?\s+in\s+no\s+folder$/ );
			await expect( page.locator( '.vgml-do[data-todo="folders"] a' ) ).toHaveText( 'Put them in folders' );
		}
		expect( await page.evaluate( () => document.body.innerText ) ).not.toMatch( /Work out the folders/ );
	} );

	test( 'the title rewrite says what it did', async ( { page } ) => {
		await open( page, SCREEN.dashboard );

		const button = page.locator( '.vgml-do[data-todo="names"] a' ).filter( { hasText: /Rename from descriptions/i } );

		/*
		 *  One of two things must be true: work is offered, or there is no
		 *  row because there is none. A row with nothing behind it is the
		 *  thing this screen no longer shows.
		 */
		if ( ( await button.count() ) === 0 ) {
			await expect(
				page.locator( '.vgml-do[data-todo="names"]' ),
				'with no rewrite offered, there is no titles row at all'
			).toHaveCount( 0 );
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
