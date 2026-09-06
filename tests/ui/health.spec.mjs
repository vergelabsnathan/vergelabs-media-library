import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  The look-alike card, pressed.
 *
 *      HEALTH_WALK=1 pnpm test:ui health.spec
 *
 *  tests/tree/health-keep.php walks the route -- the rewrite, the set-aside,
 *  the refusals -- on copies it makes on the box. This walks the buttons: two
 *  pictures are drawn in the browser, uploaded, found as one look-alike set
 *  by the scan, and then Keep this one, Undo, Keep both and Undo are pressed
 *  on that set and on nothing else. The set has to be exactly the two
 *  uploads, or nothing is pressed. Both uploads are deleted at the end.
 *
 *  Spends nothing: no describe runs on an upload. Skipped unless asked for,
 *  because it writes two files into the library while it runs.
 */

test.describe( 'the look-alike card', () => {

	test.skip( ! process.env.HEALTH_WALK, 'HEALTH_WALK=1 to press the card on two uploads of its own' );

	test( 'keep this one, undo, keep both, undo', async ( { page } ) => {

		test.setTimeout( 240000 );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await open( page, SCREEN.duplicates );

		const problems = [];
		page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );

		/*
		 *  Two pictures that resemble each other and nothing in the library:
		 *  a field of coloured blocks from a fixed seed, and the same field
		 *  drawn smaller and saved rougher.
		 */
		const made = await page.evaluate( async () => {
			const draw = ( w, h ) => {
				const c = document.createElement( 'canvas' );
				c.width = w;
				c.height = h;
				const g = c.getContext( '2d' );
				let s = 20260906;
				const rnd = () => ( s = ( s * 1103515245 + 12345 ) % 2147483648 ) / 2147483648;
				g.fillStyle = '#e8e2d0';
				g.fillRect( 0, 0, w, h );
				for ( let i = 0; i < 90; i++ ) {
					g.fillStyle = `hsl(${ Math.floor( rnd() * 360 ) } 60% ${ 25 + Math.floor( rnd() * 50 ) }%)`;
					g.fillRect( rnd() * w, rnd() * h, ( 0.05 + rnd() * 0.25 ) * w, ( 0.05 + rnd() * 0.25 ) * h );
				}
				return c;
			};
			const a = draw( 1200, 800 );
			const b = document.createElement( 'canvas' );
			b.width = 900;
			b.height = 600;
			b.getContext( '2d' ).drawImage( a, 0, 0, 900, 600 );

			const blob = ( canvas, q ) => new Promise( ( r ) => canvas.toBlob( r, 'image/jpeg', q ) );
			const upload = async ( canvas, q, name, title ) => {
				const body = new FormData();
				body.append( 'file', await blob( canvas, q ), name );
				body.append( 'title', title );
				const r = await wp.apiFetch( { path: '/wp/v2/media', method: 'POST', body } );
				return { id: r.id, name: r.media_details && r.media_details.file ? r.media_details.file.split( '/' ).pop() : name };
			};
			return {
				a: await upload( a, 0.9, 'vgml-p6-ui-walk-one.jpg', 'Phase 6 UI walk A' ),
				b: await upload( b, 0.7, 'vgml-p6-ui-walk-b.jpg', 'Phase 6 UI walk B' ),
			};
		} );

		const remove = async () => {
			await page.evaluate( async ( ids ) => {
				for ( const id of ids ) {
					await wp.apiFetch( { path: `/wp/v2/media/${ id }?force=true`, method: 'DELETE' } ).catch( () => {} );
				}
			}, [ made.a.id, made.b.id ] );
		};

		try {
			await page.reload( { waitUntil: 'domcontentloaded' } );
			await expect( page.locator( '.vgml-health-list.is-related' ) ).toBeVisible( { timeout: 30000 } );

			// The card holding the first upload, and only the two uploads in it.
			const card = page.locator( '.vgml-pair', { has: page.locator( `.vgml-pair-file:text-is("${ made.a.name }")` ) } );
			await expect( card, 'the two uploads are one look-alike set' ).toHaveCount( 1 );
			const names = await card.locator( '.vgml-pair-file' ).allInnerTexts();
			expect( names.sort(), 'the set is exactly the two uploads, nothing of the library' ).toEqual( [ made.a.name, made.b.name ].sort() );

			const sideA = card.locator( '.vgml-pair-side', { has: page.locator( `.vgml-pair-file:text-is("${ made.a.name }")` ) } );
			await expect( sideA.locator( '.vgml-pair-facts li' ) ).toHaveCount( 4 );
			await expect( sideA.locator( '.vgml-pair-facts li' ).nth( 3 ) ).toHaveText( 'Not used in a post, page, layout or widget' );

			// Keep this one: the card becomes one line, with Undo and the Set aside link.
			// (The line replaces the file names the card was found by, so it is found by its own text.)
			const keep = sideA.locator( '.vgml-pair-keep' );
			await expect( keep ).toHaveText( `Keep this one · ${ made.b.name } set aside` );
			await keep.click();
			const line = page.locator( '.vgml-pair.is-done .vgml-pair-line', { hasText: `${ made.a.name } kept` } );
			await expect( line ).toContainText( `${ made.a.name } kept · ${ made.b.name } set aside for 30 days` );
			await expect( line.locator( '.vgml-pair-undo' ) ).toHaveText( /^Undo until (today|tomorrow) / );
			await expect( line.locator( '.vgml-pair-aside' ) ).toHaveText( 'Set aside ↓' );

			// Set aside ↓ lists the file below, by its title, with the reason naming the kept file.
			await line.locator( '.vgml-pair-aside' ).click();
			await expect( page.locator( '#vgml-quarantine-list' ) ).toContainText( 'Phase 6 UI walk B', { timeout: 20000 } );
			await expect( page.locator( '#vgml-quarantine-list' ) ).toContainText( `Look-alike of ${ made.a.name }, kept on` );

			// Undo: the file is back, and so is the card.
			await line.locator( '.vgml-pair-undo' ).click();
			await expect( page.locator( '.vgml-pair', { has: page.locator( `.vgml-pair-file:text-is("${ made.a.name }")` ) } ).locator( '.vgml-pair-keep' ).first() ).toBeVisible( { timeout: 30000 } );

			// Keep both: one line (the only done card on the page), Undo brings the set back.
			const card2 = page.locator( '.vgml-pair', { has: page.locator( `.vgml-pair-file:text-is("${ made.a.name }")` ) } );
			await card2.locator( '.vgml-pair-both' ).click();
			const both = page.locator( '.vgml-pair.is-done .vgml-pair-line' );
			await expect( both ).toHaveCount( 1 );
			await expect( both ).toContainText( 'Both kept · not shown again' );
			await both.locator( '.vgml-pair-undo' ).click();
			await expect( page.locator( '.vgml-pair', { has: page.locator( `.vgml-pair-file:text-is("${ made.a.name }")` ) } ).locator( '.vgml-pair-both' ) ).toBeVisible( { timeout: 30000 } );

			await page.screenshot( { path: 'test-results/shot-health-walk.png', fullPage: false } );
			expect( problems, problems.join( '\n' ) ).toEqual( [] );
		} finally {
			await remove();
		}
	} );
} );
