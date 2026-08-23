/*
 *  Dragging a folder onto another folder.
 *
 *      node tests/tree/reparent.mjs http://185.229.224.239 admin VgmlTest7pass
 *
 *  The case that matters is the refusal. Dropping a folder into its own
 *  sub-folder detaches the branch: the terms exist, the files in them exist, and
 *  none of it appears anywhere again. The endpoint refuses it, but a gesture the
 *  user is allowed to complete and which then fails is still a bad interface --
 *  so the tree refuses it during dragover, before the drop, and this checks the
 *  request was never sent at all.
 */

import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://185.229.224.239';
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
	await page.fill( '.vgml-search', 'RP ' );
	await page.waitForTimeout( 800 );
}
await revealFixture();

async function dragFolderOnto( fromId, toId ) {
	return page.evaluate( ( d ) => {
		const from = document.querySelector( `.vgml-node[data-id="${ d.fromId }"] .vgml-row` );
		const to = document.querySelector( `.vgml-node[data-id="${ d.toId }"] .vgml-row` );
		if ( ! from || ! to ) return { error: 'missing row ' + ( from ? d.toId : d.fromId ) };

		const dt = new DataTransfer();
		from.dispatchEvent( new DragEvent( 'dragstart', { bubbles: true, cancelable: true, dataTransfer: dt } ) );

		const over = new DragEvent( 'dragover', { bubbles: true, cancelable: true, dataTransfer: dt } );
		to.dispatchEvent( over );
		const refused = to.classList.contains( 'is-refused' ) || dt.dropEffect === 'none';

		to.dispatchEvent( new DragEvent( 'drop', { bubbles: true, cancelable: true, dataTransfer: dt } ) );
		from.dispatchEvent( new DragEvent( 'dragend', { bubbles: true, cancelable: true, dataTransfer: dt } ) );
		return { refused };
	}, { fromId, toId } );
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
check( 'the move was sent', calls.some( ( c ) => c.action === 'move' ), ( moved && moved.error ) || JSON.stringify( calls[ 0 ] || {} ).slice( 0, 80 ) );
check( 'the folder moved', ( await parentOf( ids.grand ) ) === ids.other );

console.log( '\n2. into itself' );
calls.length = 0;
let r = await dragFolderOnto( ids.root, ids.root );
check( 'refused during dragover', r.refused === true, r.error || '' );
await page.waitForTimeout( 800 );
check( 'nothing was sent', calls.length === 0, `${ calls.length } calls` );

console.log( '\n3. into its own child' );
calls.length = 0;
r = await dragFolderOnto( ids.root, ids.child );
check( 'refused during dragover', r.refused === true, r.error || '' );
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
check( 'refused during dragover', r.refused === true, r.error || '' );
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
