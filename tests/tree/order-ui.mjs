/*
 *  Arranging folders by dragging them.
 *
 *      node tests/tree/order-ui.mjs http://46.225.66.194 admin VgmlTest7pass
 *
 *  tests/tree/order.php proves the endpoint. This proves the gesture, and it
 *  drives a real mouse -- press, move, release -- rather than dispatching events
 *  at rows. Synthesised events once passed fifteen assertions over a drag that
 *  did not exist, because nothing in the library was draggable at all and firing
 *  a 'drop' at a folder proved only that the handler ran.
 *
 *  Three drops, because they are three different code paths: the top edge, the
 *  bottom edge, and an edge in a branch the folder does not belong to -- which
 *  re-parents and positions in one gesture.
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

const open = async () => {
	await page.goto( `${ BASE }/wp-admin/upload.php`, { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );
};

await open();

/* --- two branches of our own, so nothing depends on what is there -------- */

const stamp = 'zzord' + Math.floor( Math.random() * 9000 + 1000 );

const call = ( data ) => page.evaluate( ( d ) => window.wp.apiFetch( {
	path: '/vergeml/v1/folder',
	method: 'POST',
	data: Object.assign( { taxonomy: 'media_category' }, d ),
} ), data );

const aaa = ( await call( { action: 'create', name: `${ stamp } aaa` } ) ).id;
const bbb = ( await call( { action: 'create', name: `${ stamp } bbb` } ) ).id;

const kid = {};
for ( const k of [ 'k1', 'k2', 'k3' ] ) {
	kid[ k ] = ( await call( { action: 'create', name: `${ stamp } ${ k }`, parent: aaa } ) ).id;
}
kid.k9 = ( await call( { action: 'create', name: `${ stamp } k9`, parent: bbb } ) ).id;

check( 'two branches to arrange', !! aaa && !! bbb && Object.keys( kid ).length === 4 );

/*
 *  Reload before looking for them. They were made through the endpoint directly
 *  rather than through the tree's own create, so the tree is still holding the
 *  node list it booted with and has no idea they exist.
 */
const show = async () => {
	await open();
	await page.fill( '.vgml-search', stamp );
	await page.waitForTimeout( 900 );
};

await show();

const names = () => page.evaluate( ( stamp ) =>
	[ ...document.querySelectorAll( '.vgml-tree .vgml-name' ) ]
		.map( ( n ) => n.textContent || '' )
		.filter( ( t ) => /\sk\d$/.test( t ) && t.startsWith( stamp ) )
		.map( ( t ) => t.slice( -2 ) ), stamp );

const parentOf = async ( id ) => page.evaluate( async ( id ) => {
	const t = await window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category' } );
	const n = ( t.nodes || [] ).find( ( n ) => n.id === id );
	return n ? n.parent : null;
}, id );

check( 'they start alphabetically', ( await names() ).join() === 'k1,k2,k3,k9', ( await names() ).join( ' < ' ) );

/* --- the drag ------------------------------------------------------------ */

const rowFor = ( label ) =>
	page.locator( `.vgml-tree .vgml-node:has(.vgml-name:text-is("${ stamp } ${ label }")) .vgml-row` ).first();

/*
 *  edge: 'before' aims at the top of the target row, 'after' at the bottom,
 *  'into' at the middle. The zones are the outer thirds, so a row about 28px
 *  tall leaves roughly 9px to hit -- aiming 3px in is inside it and 3px from
 *  the far side is inside the other.
 */
async function dragOnto( from, target, edge ) {

	const a = await rowFor( from ).boundingBox();
	const b = await rowFor( target ).boundingBox();

	if ( ! a || ! b ) {
		return null;
	}

	const y = edge === 'before' ? b.y + 3
		: edge === 'after' ? b.y + b.height - 3
			: b.y + b.height / 2;

	await page.mouse.move( a.x + 40, a.y + a.height / 2 );
	await page.mouse.down();
	// Past jQuery UI's distance threshold before aiming, or the drag never starts.
	await page.mouse.move( a.x + 60, a.y + a.height / 2 - 10, { steps: 4 } );
	await page.mouse.move( b.x + 40, y, { steps: 8 } );
	await page.waitForTimeout( 250 );

	const line = await page.evaluate( () => {
		const el = document.querySelector( '.vgml-row.is-before, .vgml-row.is-after' );
		return el ? ( el.classList.contains( 'is-before' ) ? 'before' : 'after' ) : null;
	} );

	await page.mouse.up();
	await page.waitForTimeout( 1500 );

	return line;
}

/* --- the top edge -------------------------------------------------------- */

console.log( '\nthe top edge' );

let line = await dragOnto( 'k3', 'k1', 'before' );
check( 'a line shows above the target', line === 'before', line || 'no line' );

let now = await names();
check( 'it landed above it', now.join() === 'k3,k1,k2,k9', now.join( ' < ' ) );

/* --- the bottom edge ----------------------------------------------------- */

console.log( '\nthe bottom edge' );

line = await dragOnto( 'k1', 'k2', 'after' );
check( 'a line shows below the target', line === 'after', line || 'no line' );

now = await names();
check( 'it landed below it', now.join() === 'k3,k2,k1,k9', now.join( ' < ' ) );

/* --- from another branch, in one gesture --------------------------------- */

console.log( '\nfrom another branch' );

check( 'it starts somewhere else', ( await parentOf( kid.k9 ) ) === bbb );

line = await dragOnto( 'k9', 'k3', 'after' );
check( 'a line shows there too', line === 'after', line || 'no line' );

check( 'it changed branch', ( await parentOf( kid.k9 ) ) === aaa, String( await parentOf( kid.k9 ) ) );

now = await names();
check( 'and landed where the line was', now.join() === 'k3,k9,k2,k1', now.join( ' < ' ) );

/* --- a folder dropped on itself ------------------------------------------ */

console.log( '\nnonsense drops' );

const settled = ( await names() ).join();
await dragOnto( 'k2', 'k2', 'into' );
check( 'dropping a folder on itself does nothing', ( await names() ).join() === settled, ( await names() ).join( ' < ' ) );

await dragOnto( 'k3', 'k9', 'into' );
check( 'dropping a folder into a folder still works', ( await parentOf( kid.k3 ) ) === kid.k9,
	`k3 parent is ${ await parentOf( kid.k3 ) }, k9 is ${ kid.k9 }` );

/* --- and from the keyboard ----------------------------------------------- */

console.log( '\nfrom the keyboard' );

const before = ( await names() ).join();

// Focus the row itself, the way arrow navigation leaves it, then Alt+Up.
const moved = await page.evaluate( ( id ) => {
	const li = document.querySelector( `.vgml-tree .vgml-node[data-id="${ id }"]` );
	if ( ! li ) { return false; }
	li.focus();
	return document.activeElement === li;
}, kid.k2 );

check( 'a folder row takes focus', moved );

await page.keyboard.press( 'Alt+ArrowUp' );

await page.waitForFunction( ( was ) => {
	const now = [ ...document.querySelectorAll( '.vgml-tree .vgml-name' ) ]
		.map( ( n ) => n.textContent || '' )
		.filter( ( t ) => /\sk\d$/.test( t ) )
		.map( ( t ) => t.slice( -2 ) ).join();
	return now !== was;
}, before, { timeout: 20000 } ).catch( () => {} );

const afterKey = ( await names() ).join();
check( 'Alt+Up moves it one place', afterKey !== before, `${ before } -> ${ afterKey }` );

await page.keyboard.press( 'Alt+ArrowDown' );

await page.waitForFunction( ( was ) => {
	const now = [ ...document.querySelectorAll( '.vgml-tree .vgml-name' ) ]
		.map( ( n ) => n.textContent || '' )
		.filter( ( t ) => /\sk\d$/.test( t ) )
		.map( ( t ) => t.slice( -2 ) ).join();
	return now !== was;
}, afterKey, { timeout: 20000 } ).catch( () => {} );

check( 'and Alt+Down puts it back', ( await names() ).join() === before,
	`${ afterKey } -> ${ ( await names() ).join() }, expected ${ before }` );

/* --- and it is still that way after a reload ----------------------------- */

console.log( '\nafter a reload' );

await show();

const reloaded = await names();
check( 'the arrangement survived', reloaded.join() === 'k3,k9,k2,k1' || reloaded.join() === 'k9,k3,k2,k1',
	reloaded.join( ' < ' ) );

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

/* tidy */
for ( const id of [ kid.k1, kid.k2, kid.k3, kid.k9, aaa, bbb ] ) {
	await call( { action: 'delete', id } );
}

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
