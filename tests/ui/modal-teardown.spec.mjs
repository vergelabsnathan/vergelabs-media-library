import { test, expect } from './fixtures.mjs';

/*
 *  Reported 2026-09-10: "once an image is opened the sidebar with image
 *  description persists".
 *
 *  We are a suspect by construction. js/vergeml-why-view.js wraps
 *  wp.media.view.Attachment.Details.prototype.render rather than replacing it,
 *  and js/vergeml-media-views.js warns in its own header about what happens to
 *  a view that is wrapped and not cleaned up. So this asks the plain question:
 *  after opening a picture and closing it, is anything of ours -- or of the
 *  modal's -- still on the screen?
 */

test( 'the attachment modal tears down when it is closed', async ( { page } ) => {

	test.setTimeout( 240000 );
	await page.setViewportSize( { width: 1600, height: 1000 } );

	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( `pageerror: ${ e.message }` ) );
	page.on( 'console', ( m ) => m.type() === 'error' && errors.push( `console: ${ m.text() }` ) );

	await page.goto( '/wp-admin/upload.php?mode=grid', { waitUntil: 'domcontentloaded', timeout: 120000 } );

	const tiles = page.locator( '.attachments .attachment' );
	await expect( tiles.first(), 'the grid has pictures' ).toBeVisible( { timeout: 60000 } );

	const before = await page.evaluate( () => ( {
		frames: document.querySelectorAll( '.edit-attachment-frame' ).length,
		details: document.querySelectorAll( '.attachment-details' ).length,
		why: document.querySelectorAll( '.vgml-why' ).length,
	} ) );

	// Open one.
	await tiles.first().click();
	const frame = page.locator( '.edit-attachment-frame' );
	await expect( frame, 'the modal opens' ).toBeVisible( { timeout: 30000 } );
	await page.waitForTimeout( 2500 );

	const open = await page.evaluate( () => ( {
		frames: document.querySelectorAll( '.edit-attachment-frame' ).length,
		details: document.querySelectorAll( '.attachment-details' ).length,
		why: document.querySelectorAll( '.vgml-why' ).length,
	} ) );

	// Close it the way a person does.
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 2000 );

	const closed = await page.evaluate( () => {
		const vis = ( sel ) => [ ...document.querySelectorAll( sel ) ]
			.filter( ( el ) => el.getBoundingClientRect().width > 0 && el.getBoundingClientRect().height > 0 ).length;
		return {
			frames: document.querySelectorAll( '.edit-attachment-frame' ).length,
			framesVisible: vis( '.edit-attachment-frame' ),
			details: document.querySelectorAll( '.attachment-details' ).length,
			detailsVisible: vis( '.attachment-details' ),
			why: document.querySelectorAll( '.vgml-why' ).length,
			whyVisible: vis( '.vgml-why' ),
		};
	} );

	console.log( `\n  before opening: ${ JSON.stringify( before ) }` );
	console.log( `  while open:     ${ JSON.stringify( open ) }` );
	console.log( `  after closing:  ${ JSON.stringify( closed ) }` );
	console.log( errors.length ? `  errors:\n    ${ errors.slice( 0, 5 ).join( '\n    ' ) }` : '  errors: none' );

	await page.screenshot( { path: 'tests/ui/shots/modal-after-close.png' } );

	/*
	 *  Core keeps the frame in the DOM and hides it, which is its own business.
	 *  What must not survive is anything of ours still drawn on the screen.
	 */
	expect( closed.whyVisible, 'nothing of ours is left on screen after the modal closes' ).toBe( 0 );
	expect( closed.detailsVisible, 'the attachment details are not left on screen' ).toBe( 0 );

	// Opening and closing repeatedly must not stack copies up.
	for ( let i = 0; i < 3; i++ ) {
		await tiles.nth( i ).click();
		await expect( frame ).toBeVisible( { timeout: 30000 } );
		await page.waitForTimeout( 900 );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 700 );
	}

	const after = await page.evaluate( () => ( {
		frames: document.querySelectorAll( '.edit-attachment-frame' ).length,
		why: document.querySelectorAll( '.vgml-why' ).length,
	} ) );

	console.log( `  after three more opens: ${ JSON.stringify( after ) }` );

	expect( after.why, 'our section is not stacked up once per open' ).toBeLessThanOrEqual( open.why );
	expect( errors, errors.join( '\n' ) ).toEqual( [] );
} );
