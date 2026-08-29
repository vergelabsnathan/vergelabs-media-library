/*
 *  The tree keeps up without a reload.
 *
 *      node tests/tree/live.mjs http://46.225.66.194 admin VgmlTest7pass
 *
 *  paint() skips its work when nothing about the rows would look different, and
 *  it decided that by counting them. So a change that alters a row without
 *  altering how many rows there are was written to the database, answered by the
 *  server, stored in the browser's copy of the tree -- and then not drawn. It
 *  turned up on the next reload, which reads as "it didn't work".
 *
 *  Picking a colour is the honest test of that, and it goes first. Filing a file
 *  and renaming both happen to rebuild the tree for their own reasons, so they
 *  reach the screen either way and prove nothing about the repaint; they are here
 *  because they are worth checking, not because they guard it.
 *
 *  The file drag goes last on purpose. It needs wp.media's grid, which on a
 *  library this size can take the better part of a minute, and a slow grid must
 *  not be able to hide a repaint failure behind it.
 */

import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://46.225.66.194';
const USER = process.argv[ 3 ] ?? 'admin';
const PASS = process.argv[ 4 ] ?? 'VgmlTest7pass';

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? '  -- ' + detail : '' }` );
};

const browser = await chromium.launch();
const ctx = await browser.newContext( { viewport: { width: 1500, height: 950 } } );
const page = await ctx.newPage();
page.setDefaultTimeout( 60000 );

const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 2500 );

const call = ( data ) => page.evaluate( ( d ) => window.wp.apiFetch( {
	path: '/vergeml/v1/folder',
	method: 'POST',
	data: Object.assign( { taxonomy: 'media_category' }, d ),
} ), data );

/*
 *  Admin notices are removed before anything is clicked. Other plugins on this
 *  box put a banner across the top of the media screen, and an overlay that
 *  intercepts a click makes a test fail for a reason that has nothing to do with
 *  what it is testing.
 */
const clearNotices = () => page.evaluate( () => {
	document.querySelectorAll( '.notice, .e-notice, .update-nag' ).forEach( ( n ) => n.remove() );
} );

const stamp = 'zzlive' + Math.floor( Math.random() * 9000 + 1000 );

await page.goto( `${ BASE }/wp-admin/upload.php?mode=grid`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );

const target = ( await call( { action: 'create', name: `${ stamp } target` } ) ).id;

const show = async () => {
	await page.goto( `${ BASE }/wp-admin/upload.php?mode=grid`, { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );
	await clearNotices();
	await page.fill( '.vgml-search', stamp );
	await page.waitForTimeout( 900 );
};

// One reload here, so the tree learns the folder exists. Nothing after this
// point may need another one.
await show();

const row = () => page.locator( `.vgml-tree .vgml-node[data-id="${ target }"]` ).first();

const onScreen = () => page.evaluate( ( id ) => {
	const li = document.querySelector( `.vgml-tree .vgml-node[data-id="${ id }"]` );
	if ( ! li ) {
		return null;
	}
	const count = li.querySelector( '.vgml-count' );
	// The colour lives on the icon span, which the paths inherit -- so that is
	// what has to change, not a fill attribute.
	const icon = li.querySelector( '.vgml-icon' );
	return {
		name: ( li.querySelector( '.vgml-name' ) || {} ).textContent || '',
		count: count ? count.textContent : null,
		icon: icon ? getComputedStyle( icon ).color : '',
	};
}, target );

const start = await onScreen();
check( 'the folder is on screen', !! start, start ? start.name : 'missing' );
check( 'and holds nothing yet', start && start.count === null, start ? String( start.count ) : '' );

/* --- a colour, through the control --------------------------------------- */

console.log( '\nrecoloured' );

const iconBefore = start ? start.icon : '';

await clearNotices();
await row().locator( '.vgml-row' ).click();
await page.waitForTimeout( 300 );

const colourBtn = page.locator( '.vgml-tree .vgml-color' ).first();
check( 'there is a colour control', ( await colourBtn.count() ) > 0 );
check( 'and picking a folder arms it', ! ( await colourBtn.isDisabled() ) );

await colourBtn.click();
await page.waitForSelector( '.vgml-swatches', { timeout: 10000 } );

const swatches = await page.locator( '.vgml-swatch' ).count();
check( 'it offers the whole palette', swatches === 18, `${ swatches } swatches` );

const labels = await page.evaluate( () =>
	[ ...document.querySelectorAll( '.vgml-swatch' ) ].map( ( s ) => s.getAttribute( 'aria-label' ) ) );
check( 'every colour is named', labels.every( ( l ) => l && l.length > 1 ), labels.slice( 0, 3 ).join( ', ' ) + '…' );

// The second swatch is the first real colour; the first is "no colour".
await page.locator( '.vgml-swatch' ).nth( 1 ).click();

await page.waitForFunction( ( args ) => {
	const li = document.querySelector( `.vgml-tree .vgml-node[data-id="${ args.id }"]` );
	const icon = li && li.querySelector( '.vgml-icon' );
	return icon && getComputedStyle( icon ).color !== args.was;
}, { id: target, was: iconBefore }, { timeout: 15000 } ).catch( () => {} );

const coloured = await onScreen();
check( 'the colour appears without a reload', coloured && coloured.icon !== iconBefore,
	`${ iconBefore } -> ${ coloured ? coloured.icon : 'gone' }` );

const stored = await page.evaluate( async ( id ) => {
	const t = await window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category' } );
	const n = ( t.nodes || [] ).find( ( n ) => n.id === id );
	return n ? n.color : null;
}, target );
check( 'and it was saved', !! stored, stored || 'nothing stored' );

/* --- renamed -------------------------------------------------------------- */

console.log( '\nrenamed' );

await clearNotices();
await page.evaluate( ( id ) => {
	const li = document.querySelector( `.vgml-tree .vgml-node[data-id="${ id }"]` );
	if ( li ) {
		li.querySelector( '.vgml-row' ).dispatchEvent( new MouseEvent( 'dblclick', { bubbles: true } ) );
	}
}, target );
await page.waitForTimeout( 400 );

const input = page.locator( '.vgml-tree .vgml-row input' ).first();

if ( await input.count() ) {
	await input.fill( `${ stamp } renamed` );
	await input.press( 'Enter' );
}

await page.waitForFunction( ( id ) => {
	const li = document.querySelector( `.vgml-tree .vgml-node[data-id="${ id }"]` );
	const name = li && li.querySelector( '.vgml-name' );
	return name && name.textContent.indexOf( 'renamed' ) !== -1;
}, target, { timeout: 20000 } ).catch( () => {} );

const renamed = await onScreen();
check( 'the new name appears without a reload', renamed && renamed.name.indexOf( 'renamed' ) !== -1,
	renamed ? renamed.name : 'gone' );

/* --- a file dragged in ---------------------------------------------------- */

console.log( '\na file dragged in' );

/*
 *  Back to "All files" first. Picking the folder for the colour test also
 *  filtered the library to it, and it is empty -- so waiting for an attachment
 *  would be waiting for a file that is correctly not there. The two pseudo rows
 *  survive the search filter, so the folder stays on screen for the drop.
 */
await clearNotices();
await page.locator( '.vgml-tree .vgml-row', { hasText: 'All files' } ).first().click();

// A slow grid is reported as a slow grid, not as a failure of the tree.
const grid = await page.waitForSelector( '.attachment', { timeout: 90000 } ).catch( () => null );
check( 'the media grid loaded', !! grid, grid ? '' : 'no attachments after 90s' );

if ( grid ) {

	const a = await page.locator( '.attachment' ).first().boundingBox();
	const b = await row().locator( '.vgml-row' ).boundingBox();

	check( 'a file and the folder are both reachable', !! a && !! b );

	if ( a && b ) {
		await page.mouse.move( a.x + 40, a.y + 40 );
		await page.mouse.down();
		await page.mouse.move( a.x + 60, a.y + 40, { steps: 4 } ); // past the threshold
		await page.mouse.move( b.x + b.width / 2, b.y + b.height / 2, { steps: 12 } );
		await page.mouse.up();

		await page.waitForFunction( ( id ) => {
			const li = document.querySelector( `.vgml-tree .vgml-node[data-id="${ id }"]` );
			return li && li.querySelector( '.vgml-count' );
		}, target, { timeout: 20000 } ).catch( () => {} );

		const filed = await onScreen();
		check( 'the count appears without a reload', filed && filed.count === '1',
			filed ? String( filed.count ) : 'none' );
	}
}

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

/*
 *  Leave nothing selected.
 *
 *  A selection that outlives its folder used to filter the library to a term
 *  that no longer existed, so the next visit came up empty -- a bug the server
 *  now heals, but a test has no business leaving the account in that state.
 */
await call( { action: 'delete', id: target } );
await page.evaluate( () => window.wp.apiFetch( {
	path: '/vergeml/v1/state',
	method: 'POST',
	data: { taxonomy: 'media_category', selected: 0 },
} ).catch( () => {} ) );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
