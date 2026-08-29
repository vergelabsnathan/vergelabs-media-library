/*
 *  Dragging a folder onto another folder.
 *
 *      node tests/tree/reparent.mjs http://46.225.66.194 admin VgmlTest7pass
 *
 *  The case that matters is the refusal. Dropping a folder into its own
 *  sub-folder detaches the branch: the terms exist, the files in them exist, and
 *  none of it appears anywhere again. The endpoint refuses it, but a gesture the
 *  user is allowed to complete and which then fails is still a bad interface --
 *  so the tree refuses it during dragover, before the drop, and this checks the
 *  request was never sent at all.
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
const page = await browser.newPage( { viewport: { width: 1500, height: 950 } } );

const calls = [];
page.on( 'request', ( r ) => {
	if ( r.url().indexOf( '/vergeml/v1/folder' ) !== -1 && r.method() === 'POST' ) {
		try { calls.push( JSON.parse( r.postData() || '{}' ) ); } catch { /* not json */ }
	}
} );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForLoadState( 'domcontentloaded' );
await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 5000 );

const api = ( data ) => page.evaluate( ( d ) =>
	window.wp.apiFetch( { path: '/vergeml/v1/folder', method: 'POST', data: d } ), data );

// Root > Child > Grand, made fresh, cleaned at the start in case a run died.
async function fixture() {
	const tree = await page.evaluate( () => window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category' } ) );
	for ( const n of tree.nodes || [] ) {
		if ( /^RP / .test( n.name ) ) {
			await api( { taxonomy: 'media_category', action: 'delete', id: n.id } ).catch( () => {} );
		}
	}
	const root = ( await api( { taxonomy: 'media_category', action: 'create', name: 'RP Root' } ) ).id;
	const child = ( await api( { taxonomy: 'media_category', action: 'create', name: 'RP Child', parent: root } ) ).id;
	const grand = ( await api( { taxonomy: 'media_category', action: 'create', name: 'RP Grand', parent: child } ) ).id;
	const other = ( await api( { taxonomy: 'media_category', action: 'create', name: 'RP Other' } ) ).id;
	return { root, child, grand, other };
}

const ids = await fixture();
check( 'fixture built three deep', !! ids.grand, JSON.stringify( ids ) );

await page.reload( { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 5000 );

/*
 *  Every row has to be in the DOM before it can be dragged, and with two thousand
 *  seeded folders these four are buried in closed branches. Searching is the
 *  cheapest way to surface them: the tree opens every branch on the way to a
 *  match while a filter is active, which is exactly what is needed here.
 */
async function revealFixture() {
	// Cleared first: filling with the value already in the box is a no-op in some
	// paths, and then the tree never re-filters and the fixture stays buried.
	await page.fill( '.vgml-search', '' );
	await page.waitForTimeout( 200 );
	await page.fill( '.vgml-search', 'RP ' );
	await page.waitForTimeout( 900 );
	// Prove it worked rather than assuming; a silent miss here reads as a product
	// bug three assertions later.
	const shown = await page.evaluate( () => document.querySelectorAll( '.vgml-node:not(.vgml-pseudo)' ).length );
	if ( shown < 3 ) {
		check( 'the fixture is visible in the tree', false, `${ shown } folders shown` );
	}
}
await revealFixture();

/*
 *  A folder drag, with a real mouse.
 *
 *  This dispatched DragEvents until the drag layer moved to jQuery UI, which does
 *  not listen for them. Worse than merely failing: the three refusal cases went on
 *  passing, because "nothing was sent" is trivially true when nothing happens at
 *  all. Three green assertions over a mechanism that was no longer there.
 *
 *  jQuery UI drags on mouse events, so the mouse is what drives it -- press, move
 *  past the 6px threshold, move onto the target, release.
 */
async function dragFolderOnto( fromId, toId ) {

	const from = await page.$( `.vgml-node[data-id="${ fromId }"] .vgml-row` );
	const to = await page.$( `.vgml-node[data-id="${ toId }"] .vgml-row` );

	if ( ! from || ! to ) {
		const present = await page.evaluate( () =>
			[ ...document.querySelectorAll( '.vgml-node[data-id]' ) ]
				.map( ( n ) => n.getAttribute( 'data-id' ) + ':' + ( n.querySelector( '.vgml-name' ) || {} ).textContent )
				.slice( 0, 12 ) );
		return { error: 'missing row ' + ( from ? toId : fromId ) + ' -- present: ' + present.join( ', ' ) };
	}

	const a = await from.boundingBox();
	const b = await to.boundingBox();

	if ( ! a || ! b ) {
		return { error: 'a row is off screen' };
	}

	await page.mouse.move( a.x + 60, a.y + a.height / 2 );
	await page.mouse.down();
	await page.mouse.move( a.x + 80, a.y + a.height / 2, { steps: 4 } );
	await page.mouse.move( b.x + b.width / 2, b.y + b.height / 2, { steps: 12 } );

	// Read the target's state while the pointer is still on it.
	const refused = await to.evaluate( ( el ) => el.classList.contains( 'is-refused' ) );
	const accepted = await to.evaluate( ( el ) => el.classList.contains( 'is-drop' ) );

	await page.mouse.up();
	await page.waitForTimeout( 1500 );

	return { refused, accepted };
}

const parentOf = ( id ) => page.evaluate( async ( i ) => {
	const t = await window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category' } );
	const n = ( t.nodes || [] ).find( ( x ) => x.id === i );
	return n ? n.parent : null;
}, id );

console.log( '\n1. a legal move' );
calls.length = 0;
const moved = await dragFolderOnto( ids.grand, ids.other );
await page.waitForTimeout( 1500 );
check( 'the target accepted it', moved && moved.accepted === true, JSON.stringify( moved ) );
check( 'the move was sent', calls.some( ( c ) => c.action === 'move' ), ( moved && moved.error ) || JSON.stringify( calls[ 0 ] || {} ).slice( 0, 80 ) );
check( 'the folder moved', ( await parentOf( ids.grand ) ) === ids.other );

console.log( '\n2. into itself' );
calls.length = 0;
let r = await dragFolderOnto( ids.root, ids.root );
/*
 *  No hover class here, and that is correct rather than a gap: the dragged row
 *  and the target row are the same element, and jQuery UI never treats a
 *  draggable as its own droppable, so nothing fires. The guarantee is that
 *  nothing is sent -- which is what is asserted below.
 */
check( 'it was not accepted', r.accepted === false, JSON.stringify( r ) );
await page.waitForTimeout( 800 );
check( 'nothing was sent', calls.length === 0, `${ calls.length } calls` );

console.log( '\n3. into its own child' );
calls.length = 0;
r = await dragFolderOnto( ids.root, ids.child );
check( 'refused while hovering it', r.refused === true && r.accepted === false, r.error || JSON.stringify( r ) );
await page.waitForTimeout( 800 );
check( 'nothing was sent', calls.length === 0, `${ calls.length } calls` );
check( 'the root is still at the top', ( await parentOf( ids.root ) ) === 0 );

console.log( '\n4. into a deeper descendant' );
// Put grand back under child so there is a two-level descendant again.
await api( { taxonomy: 'media_category', action: 'move', id: ids.grand, parent: ids.child } );
await page.reload( { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 5000 );
await revealFixture();

calls.length = 0;
r = await dragFolderOnto( ids.root, ids.grand );
check( 'refused while hovering it', r.refused === true && r.accepted === false, r.error || JSON.stringify( r ) );
await page.waitForTimeout( 800 );
check( 'nothing was sent', calls.length === 0, `${ calls.length } calls` );
check( 'the tree is intact', ( await parentOf( ids.grand ) ) === ids.child && ( await parentOf( ids.root ) ) === 0 );

// Tidy.
for ( const id of [ ids.grand, ids.child, ids.root, ids.other ] ) {
	await api( { taxonomy: 'media_category', action: 'delete', id } ).catch( () => {} );
}

await browser.close();

const bad = results.filter( ( x ) => ! x.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
