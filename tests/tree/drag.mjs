/*
 *  Dragging files onto folders, and taking it back.
 *
 *      node tests/tree/drag.mjs http://185.229.224.239 admin VgmlTest7pass
 *
 *  HTML5 drag-and-drop cannot be driven by synthetic mouse events, so each drop
 *  is dispatched as the real event sequence with a DataTransfer the page can
 *  read. That is what the browser does; the handlers cannot tell the difference.
 *
 *  What is actually being tested is the thing the endpoint tests cannot reach:
 *  that the interface sends the right request. Ctrl must mean move, a plain drag
 *  must mean add, and the undo toast must put back exactly what the drag did --
 *  the assign endpoint already guarantees the inverse is honest, but only if the
 *  browser hands it back unchanged.
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

const seen = [];
page.on( 'request', ( r ) => {
	if ( r.url().indexOf( '/vergeml/v1/assign' ) !== -1 && r.method() === 'POST' ) {
		try { seen.push( JSON.parse( r.postData() || '{}' ) ); } catch { /* not json */ }
	}
} );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForLoadState( 'domcontentloaded' );

await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 5000 );

check( 'the tree is there', !! ( await page.$( '.vgml-tree' ) ) );

/*
 *  Two folders that are not in the seed, made through the interface, so this test
 *  never touches the 20,000-file fixture the benchmarks depend on.
 */
async function makeFolder( name ) {
	return page.evaluate( async ( n ) => {
		// Clear a leftover of the same name first. A run that failed part-way makes
		// every later run fail on a duplicate name -- tidying at the end is not
		// enough, because the end is exactly what did not happen.
		const tree = await window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category' } );
		for ( const node of tree.nodes || [] ) {
			if ( node.name === n ) {
				await window.wp.apiFetch( {
					path: '/vergeml/v1/folder',
					method: 'POST',
					data: { taxonomy: 'media_category', action: 'delete', id: node.id },
				} );
			}
		}
		const res = await window.wp.apiFetch( {
			path: '/vergeml/v1/folder',
			method: 'POST',
			data: { taxonomy: 'media_category', action: 'create', name: n },
		} );
		return res.id;
	}, name );
}

const A = await makeFolder( 'Drag Target A' );
const B = await makeFolder( 'Drag Target B' );
check( 'two folders made through the API', !! A && !! B, `${ A }, ${ B }` );

// Made behind the tree's back, so it has not drawn them. Reload rather than
// reaching into its internals -- this test should only touch what a user can.
await page.reload( { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 5000 );

// A file to drag: the first row in the list, whatever it is.
const fileId = await page.$eval( '#the-list input[name="media[]"]', ( c ) => parseInt( c.value, 10 ) );
check( 'a file to drag', Number.isInteger( fileId ), String( fileId ) );

const termsOf = ( id ) => page.evaluate( async ( i ) => {
	const r = await window.wp.apiFetch( { path: `/wp/v2/media/${ i }?_fields=media_category` } );
	return ( r.media_category || [] ).slice().sort();
}, id );

async function setTerms( id, terms ) {
	await page.evaluate( async ( d ) => {
		await window.wp.apiFetch( {
			path: '/vergeml/v1/assign',
			method: 'POST',
			data: { taxonomy: 'media_category', attachments: [ d.id ], add: d.terms, mode: 'move' },
		} );
	}, { id, terms } );
}

/*
 *  A real HTML5 drop. Playwright's dragTo drives mouse events, which the drag
 *  API ignores -- so the events are dispatched directly with a DataTransfer,
 *  exactly as the browser would.
 */
async function dropOnFolder( termId, { ctrl = false } = {} ) {
	await page.evaluate( ( d ) => {
		const row = document.querySelector( `.vgml-node[data-id="${ d.termId }"] .vgml-row` );
		if ( ! row ) throw new Error( 'no row for ' + d.termId );
		const dt = new DataTransfer();
		const opts = { bubbles: true, cancelable: true, dataTransfer: dt, ctrlKey: d.ctrl };
		row.dispatchEvent( new DragEvent( 'dragover', opts ) );
		row.dispatchEvent( new DragEvent( 'drop', opts ) );
	}, { termId, ctrl } );
	await page.waitForTimeout( 1200 );
}

// The tree drags whatever is selected in the library, so tick the row's checkbox.
await page.click( '#the-list input[name="media[]"]' );

console.log( '\n1. a plain drag adds' );
await setTerms( fileId, [] );
seen.length = 0;
await dropOnFolder( A );
check( 'it called assign', seen.length === 1, JSON.stringify( seen[ 0 ] || {} ).slice( 0, 90 ) );
check( 'in add mode', seen[ 0 ] && seen[ 0 ].mode === 'add', seen[ 0 ] && seen[ 0 ].mode );
check( 'the file is in A', ( await termsOf( fileId ) ).indexOf( A ) !== -1 );

console.log( '\n2. a second plain drag leaves it in both' );
seen.length = 0;
await dropOnFolder( B );
const both = await termsOf( fileId );
check( 'the file is in two folders', both.length === 2, `in ${ both.length }` );

console.log( '\n3. ctrl-drag moves instead of adding' );
seen.length = 0;
await dropOnFolder( A, { ctrl: true } );
check( 'in move mode', seen[ 0 ] && seen[ 0 ].mode === 'move', seen[ 0 ] && seen[ 0 ].mode );
const after = await termsOf( fileId );
check( 'the file is now in exactly one folder', after.length === 1, `in ${ after.length }` );
check( 'and it is the one dropped on', after[ 0 ] === A );

console.log( '\n4. the undo toast puts back what the drag did' );
await setTerms( fileId, [ B ] );  // it was in B before the drag
seen.length = 0;
await dropOnFolder( A );
const toastShown = await page.$( '.vgml-toast.is-shown' );
check( 'a toast appeared', !! toastShown );

const undoBtn = await page.$( '.vgml-toast.is-shown .vgml-undo' );
check( 'with an undo button', !! undoBtn );

if ( undoBtn ) {
	await undoBtn.click();
	await page.waitForTimeout( 1500 );
	const restored = await termsOf( fileId );
	check( 'undo restored the exact prior folders', restored.length === 1 && restored[ 0 ] === B,
		`in ${ restored.length }: ${ restored.join( ',' ) }` );
	check( 'undo was sent as a batch', seen.some( ( s ) => !! s.batch ) );
}

console.log( '\n5. dropping on a folder the file is already in changes nothing' );
await setTerms( fileId, [ A ] );
seen.length = 0;
await dropOnFolder( A );
const same = await termsOf( fileId );
check( 'still in exactly that one folder', same.length === 1 && same[ 0 ] === A, `in ${ same.length }` );

// Tidy: the fixture must be left as the benchmarks expect it.
await page.evaluate( async ( d ) => {
	await window.wp.apiFetch( {
		path: '/vergeml/v1/assign',
		method: 'POST',
		data: { taxonomy: 'media_category', attachments: [ d.fileId ], add: [], mode: 'move' },
	} );
	for ( const id of [ d.A, d.B ] ) {
		await window.wp.apiFetch( {
			path: '/vergeml/v1/folder',
			method: 'POST',
			data: { taxonomy: 'media_category', action: 'delete', id },
		} );
	}
}, { fileId, A, B } );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
