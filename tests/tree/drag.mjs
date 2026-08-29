/*
 *  Dragging files onto folders, with a real mouse.
 *
 *      node tests/tree/drag.mjs http://46.225.66.194 admin VgmlTest7pass
 *
 *  Rewritten when the drag layer moved from the HTML5 API to jQuery UI. The old
 *  version dispatched DragEvents, which jQuery UI does not listen for -- so it
 *  would have passed against a feature that no longer existed, which is exactly
 *  the failure it was written to stop.
 *
 *  jQuery UI drags on mouse events, so this drives the mouse: press on the file,
 *  move past the 6px threshold, move onto the folder, release. Nothing synthetic
 *  about it beyond the coordinates.
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
page.setDefaultTimeout( 90000 );

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
await page.waitForTimeout( 3000 );

const api = ( path, data ) => page.evaluate( ( d ) =>
	window.wp.apiFetch( d.data ? { path: d.path, method: 'POST', data: d.data } : { path: d.path } ),
	{ path, data } );

async function openLibrary() {
	await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
	await page.waitForFunction( () => document.querySelectorAll( '.vgml-node' ).length > 3, { timeout: 60000 } );
	await page.waitForTimeout( 800 );
}

await openLibrary();
check( 'the tree is there', !! ( await page.$( '.vgml-tree' ) ) );
check( 'jQuery UI draggable is loaded', await page.evaluate( () => !! ( window.jQuery && window.jQuery.fn && window.jQuery.fn.draggable ) ) );

// Two folders of our own, so the 20,000-file fixture the benchmarks rely on is
// never touched. Cleared first: a run that died half way must not poison the next.
async function makeFolder( name ) {
	const tree = await api( '/vergeml/v1/tree?taxonomy=media_category' );
	for ( const n of tree.nodes || [] ) {
		if ( n.name === name ) {
			await api( '/vergeml/v1/folder', { taxonomy: 'media_category', action: 'delete', id: n.id } );
		}
	}
	const made = await api( '/vergeml/v1/folder', { taxonomy: 'media_category', action: 'create', name } );
	return made.id;
}

const A = await makeFolder( 'Drag Target A' );
const B = await makeFolder( 'Drag Target B' );
check( 'two folders to aim at', !! A && !! B, `${ A }, ${ B }` );

await openLibrary();

const fileId = await page.$eval( '#the-list input[name="media[]"]', ( c ) => parseInt( c.value, 10 ) );
check( 'a file to drag', Number.isInteger( fileId ), String( fileId ) );

const termsOf = ( id ) => page.evaluate( async ( i ) => {
	const r = await window.wp.apiFetch( { path: `/wp/v2/media/${ i }?_fields=media_category` } );
	return ( r.media_category || [] ).slice().sort();
}, id );

const setTerms = ( id, terms ) => api( '/vergeml/v1/assign', {
	taxonomy: 'media_category', attachments: [ id ], add: terms, mode: 'move',
} );

// Reveal a folder by searching for it: with two thousand seeded folders these two
// are far below the window the tree actually renders.
async function reveal( text ) {
	await page.fill( '.vgml-search', text );
	await page.waitForTimeout( 600 );
}

/*
 *  A drag the way a hand does it. The intermediate moves matter: jQuery UI needs
 *  to see movement past its distance threshold before it starts a drag at all,
 *  and the droppable needs a move while over the target to register the hover.
 */
async function dragFileTo( termId, { ctrl = false } = {} ) {

	const from = await page.$( `#post-${ fileId } .column-title, #post-${ fileId }` );
	const to = await page.$( `.vgml-node[data-id="${ termId }"] .vgml-row` );

	if ( ! from || ! to ) {
		return { error: from ? 'no folder row' : 'no file row' };
	}

	const a = await from.boundingBox();
	const b = await to.boundingBox();

	if ( ! a || ! b ) {
		return { error: 'a row is not on screen' };
	}

	await page.mouse.move( a.x + 40, a.y + a.height / 2 );
	await page.mouse.down();
	await page.mouse.move( a.x + 60, a.y + a.height / 2, { steps: 4 } ); // past the threshold
	await page.mouse.move( b.x + b.width / 2, b.y + b.height / 2, { steps: 12 } );

	const hovering = await to.evaluate( ( el ) => el.classList.contains( 'is-drop' ) );

	if ( ctrl ) {
		await page.keyboard.down( 'Control' );
	}
	await page.mouse.up();
	if ( ctrl ) {
		await page.keyboard.up( 'Control' );
	}

	await page.waitForTimeout( 1500 );
	return { hovering };
}

console.log( '\n1. a plain drag moves, like every file manager' );
await setTerms( fileId, [] );
await reveal( 'Drag Target' );
seen.length = 0;

let r = await dragFileTo( A );
check( 'the drag started and reached the folder', ! r.error, r.error || '' );
check( 'the folder showed it was a target', r.hovering === true );
check( 'assign was called', seen.length >= 1, JSON.stringify( seen[ 0 ] || {} ).slice( 0, 80 ) );
check( 'in move mode', seen[ 0 ] && seen[ 0 ].mode === 'move', seen[ 0 ] && seen[ 0 ].mode );
check( 'the file is in A', ( await termsOf( fileId ) ).indexOf( A ) !== -1 );

console.log( '\n2. a second plain drag moves it on -- no copies left behind' );
seen.length = 0;
await dragFileTo( B );
const both = await termsOf( fileId );
check( 'the file is in exactly one folder', both.length === 1, `in ${ both.length }` );
check( 'and it is the new one', both[ 0 ] === B );

console.log( '\n3. ctrl-drag adds to a second folder -- the modifier that means copy' );
seen.length = 0;
await dragFileTo( A, { ctrl: true } );
check( 'in add mode', seen[ 0 ] && seen[ 0 ].mode === 'add', seen[ 0 ] && seen[ 0 ].mode );
const after = await termsOf( fileId );
check( 'the file is in two folders', after.length === 2, `in ${ after.length }` );
check( 'the old folder kept it', after.indexOf( B ) !== -1 );

console.log( '\n4. the undo toast puts back what the drag did' );
await setTerms( fileId, [ B ] );
await openLibrary();
await reveal( 'Drag Target' );
seen.length = 0;

await dragFileTo( A );
check( 'a toast appeared', !! ( await page.$( '.vgml-toast.is-shown' ) ) );

const undoBtn = await page.$( '.vgml-toast.is-shown .vgml-undo' );
check( 'with an undo button', !! undoBtn );

if ( undoBtn ) {
	await undoBtn.click();
	await page.waitForTimeout( 1800 );
	const restored = await termsOf( fileId );
	check( 'undo restored the exact prior folders', restored.length === 1 && restored[ 0 ] === B,
		`in ${ restored.length }: ${ restored.join( ',' ) }` );
	check( 'undo went back as a batch', seen.some( ( s ) => !! s.batch ) );
}

console.log( '\n5. dropping on Unfiled takes it out of every folder' );
await setTerms( fileId, [ A, B ] );
await openLibrary();
await reveal( 'Drag Target' );
seen.length = 0;

/*
 *  This one had no coverage and was dead: it was left on the HTML5 drag API when
 *  everything else moved to jQuery UI, so the only way to unfile something by
 *  dragging silently did nothing.
 */
{
	const from = await page.$( `#post-${ fileId }` );
	// an empty Unfiled row is hidden now; All files unfiles too and always exists
	const to = await page.$( '.vgml-node[data-id="-1"]:not(.vgml-hidden-unfiled) .vgml-row' ) ||
		await page.$( '.vgml-node[data-id="0"] .vgml-row' );

	if ( ! from || ! to ) {
		check( 'the Unfiled row is a drop target', false, from ? 'no Unfiled row' : 'no file row' );
	} else {
		const a = await from.boundingBox();
		const b = await to.boundingBox();
		await page.mouse.move( a.x + 40, a.y + a.height / 2 );
		await page.mouse.down();
		await page.mouse.move( a.x + 60, a.y + a.height / 2, { steps: 4 } );
		await page.mouse.move( b.x + b.width / 2, b.y + b.height / 2, { steps: 12 } );
		const hovering = await to.evaluate( ( el ) => el.classList.contains( 'is-drop' ) );
		await page.mouse.up();
		await page.waitForTimeout( 1500 );

		check( 'Unfiled showed it was a target', hovering === true );
		check( 'it called assign', seen.length >= 1, JSON.stringify( seen[ 0 ] || {} ).slice( 0, 80 ) );
		check( 'the file is in no folders', ( await termsOf( fileId ) ).length === 0 );
	}
}

// Leave the fixture as the benchmarks expect it.
await setTerms( fileId, [] );
for ( const id of [ A, B ] ) {
	await api( '/vergeml/v1/folder', { taxonomy: 'media_category', action: 'delete', id } );
}

await browser.close();

const bad = results.filter( ( x ) => ! x.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
