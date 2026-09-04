import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  Sort into folders: one surface.
 *
 *  The chat and the guide were two doors to one job; the September 2026
 *  redesign merged them. What is checked here is the shape of the surface
 *  before and around any answer from the service: the status strip, the
 *  command bar, the tree, the one button. Nothing here asks the planner for
 *  a proposal -- a session with a draft is planted first, so the page has a
 *  tree to show without a model call on every push.
 */

const DRAFT = {
	folders: [
		{ name: 'Apparel', parent: '', matches: 'clothing', classes: [ 'apparel' ], kinds: [ 'photo' ], audience: '', count: 30 },
		{ name: 'Women', parent: 'Apparel', matches: 'worn by women', classes: [ 'apparel' ], kinds: [ 'photo' ], audience: 'women', count: 12 },
		{ name: 'Landscapes', parent: '', matches: 'outdoor scenery', classes: [ 'landscape' ], kinds: [ 'photo' ], audience: '', count: 75 },
	],
	tags: [ { name: 'Colour', values: [ 'tan', 'red' ] } ],
};

/*
 *  The planted draft is test data on a live site. It once stayed behind,
 *  somebody pressed Move on it, and twenty-one real folders went. So the
 *  session that was there is read first and put back after every test,
 *  whatever the test did.
 */
let saved = null;

async function plant( page ) {
	if ( saved === null ) {
		saved = await page.evaluate( () => wp.apiFetch( { path: '/vergeml/v1/guide/session' } ) );
	}
	await page.evaluate( ( draft ) => wp.apiFetch( { path: '/vergeml/v1/guide/session', method: 'POST', data: { session: { state: 'shaping', draft } } } ), DRAFT );
	await page.reload( { waitUntil: 'domcontentloaded' } );
}

async function restore( page ) {
	if ( saved === null ) {
		return;
	}
	const s = saved;
	const draft = s.draft && s.draft.folders && s.draft.folders.length ? s.draft : { folders: [], tags: [] };
	// Back to the first screen wipes the session; anything else is patched over the planted one.
	await page.evaluate( ( arg ) => wp.apiFetch( { path: '/vergeml/v1/guide/session', method: 'POST', data: { session: { state: 'library', draft: { folders: [], tags: [] } } } } )
		.then( () => arg.state === 'library' ? null : wp.apiFetch( { path: '/vergeml/v1/guide/session', method: 'POST', data: { session: { state: arg.state, draft: arg.draft, goal: arg.goal } } } ) ),
		{ state: s.state || 'library', draft, goal: s.goal || '' } );
}

test.describe( 'the sort into folders screen', () => {

	test.afterEach( async ( { page } ) => {
		await restore( page );
	} );

	test( 'shows the four steps, the command bar and the tree', async ( { page } ) => {
		const problems = [];
		page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );

		await open( page, SCREEN.folders );
		await plant( page );

		await expect( page.locator( '.vgml-sort-step' ), 'four steps in the strip' ).toHaveCount( 4 );
		await expect( page.locator( '.vgml-sort-command input' ), 'the command bar' ).toBeVisible();
		await expect( page.locator( '.vgml-sort-command button' ), 'Send is disabled while the box is empty' ).toBeDisabled();
		await expect( page.locator( '.vgml-sort-row input.vgml-sort-name' ), 'the draft tree' ).toHaveCount( 3 );
		await expect( page.locator( '.vgml-sort-apply .button-primary' ) ).toContainText( 'Move' );

		await page.screenshot( { path: 'test-results/sort-1.png', fullPage: true } );
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );

	test( 'setting a folder aside takes its children with it and the counts follow', async ( { page } ) => {
		await open( page, SCREEN.folders );
		await plant( page );

		const before = await page.locator( '.vgml-rail-row b' ).first().textContent();
		expect( before.trim() ).toBe( '3' );

		await page.locator( '.vgml-sort-row' ).filter( { has: page.locator( 'input[value="Apparel"]' ) } ).locator( '.vgml-sort-aside' ).click();

		await expect( page.locator( '.vgml-sort-row input.vgml-sort-name' ), 'Apparel and Women are gone from the tree' ).toHaveCount( 1 );
		await expect( page.locator( '.vgml-rail-row b' ).first() ).toHaveText( '1' );
		await expect( page.locator( '.vgml-sort-asidenote' ) ).toContainText( 'set aside' );

		await page.locator( '.vgml-sort-asidenote button' ).click();
		await expect( page.locator( '.vgml-sort-row input.vgml-sort-name' ), 'restore all brings them back' ).toHaveCount( 3 );
	} );

	test( 'typing enables Send, and a chip fills the box', async ( { page } ) => {
		await open( page, SCREEN.folders );
		await plant( page );

		await page.locator( '.vgml-sort-chips .vgml-tag-outline' ).first().click();
		await expect( page.locator( '.vgml-sort-command input' ) ).toHaveValue( /Apparel/ );
		await expect( page.locator( '.vgml-sort-command button' ) ).toBeEnabled();
	} );

	test( 'the old guide address lands here', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=media-guide', { waitUntil: 'domcontentloaded' } );
		await expect( page ).toHaveURL( /page=media-librarian/ );
	} );
} );
