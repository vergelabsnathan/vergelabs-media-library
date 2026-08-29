/*
 *  The folder tree on a post type's list screen.
 *
 *      node tests/tree/post-ui.mjs http://46.225.66.194 admin VgmlTest7pass
 *
 *  tests/tree/post-folders.php proves the storage and the counting. This proves
 *  the screen: that the tree is there beside Posts, that it counts posts rather
 *  than files, that clicking a folder filters the list, and that a post can be
 *  dragged into a folder the same way a file can.
 *
 *  The counting is the one worth watching. A folder holds files and posts at the
 *  same time and there is one number stored on it, so a tree that reads the
 *  stored count would show the media library's number above a list of posts --
 *  which looks plausible and is wrong, the worst combination.
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

const clearNotices = () => page.evaluate( () => {
	document.querySelectorAll( '.notice, .e-notice, .update-nag' ).forEach( ( n ) => n.remove() );
} );

const call = ( data ) => page.evaluate( ( d ) => window.wp.apiFetch( {
	path: '/vergeml/v1/folder',
	method: 'POST',
	data: Object.assign( { taxonomy: 'media_category' }, d ),
} ), data );

const stamp = 'zzpost' + Math.floor( Math.random() * 9000 + 1000 );

/* --- the tree is on the Posts screen ------------------------------------- */

console.log( '\nbeside the posts' );

await page.goto( `${ BASE }/wp-admin/edit.php`, { waitUntil: 'domcontentloaded' } );

const tree = await page.waitForSelector( '.vgml-tree', { timeout: 30000 } ).catch( () => null );
check( 'the tree is there', !! tree );

/*
 *  Folders have to be turned on for posts in Media Taxonomies for any of this to
 *  exist. There is no endpoint for that setting, so the test says plainly that it
 *  is off rather than failing twelve times over further down.
 */
if ( ! tree ) {
	check( 'folders are turned on for posts', false,
		'Settings > Media Taxonomies > Media Categories > "Also use for" must include Posts' );
	console.log( `\n${ results.filter( ( r ) => r.ok ).length }/${ results.length } passed\n` );
	await browser.close();
	process.exit( 1 );
}

const rows = await page.locator( '.vgml-tree .vgml-row' ).count();
check( 'and it drew folders', rows > 2, `${ rows } rows` );

const allLabel = await page.evaluate( () =>
	( document.querySelector( '.vgml-tree .vgml-row' ) || {} ).textContent || '' );
check( 'the first row says posts, not files', /post/i.test( allLabel ) && ! /file/i.test( allLabel ),
	allLabel.trim() );

const listTable = await page.locator( '.wp-list-table #the-list tr' ).count();
check( 'the post list is still there', listTable > 0, `${ listTable } rows` );

/* --- a folder of our own ------------------------------------------------- */

const folder = ( await call( { action: 'create', name: `${ stamp } folder` } ) ).id;
check( 'a folder to file into', !! folder );

// Put three posts in it, and one attachment, so the two counts must differ.
const seeded = await page.evaluate( async ( args ) => {
	const posts = await window.wp.apiFetch( { path: '/wp/v2/posts?per_page=3&_fields=id' } );
	const media = await window.wp.apiFetch( { path: '/wp/v2/media?per_page=1&_fields=id' } );

	const ids = posts.map( ( p ) => p.id );

	await window.wp.apiFetch( {
		path: '/vergeml/v1/assign',
		method: 'POST',
		data: { taxonomy: 'media_category', attachments: ids, add: [ args.folder ], mode: 'add' },
	} );

	await window.wp.apiFetch( {
		path: '/vergeml/v1/assign',
		method: 'POST',
		data: { taxonomy: 'media_category', attachments: media.map( ( m ) => m.id ), add: [ args.folder ], mode: 'add' },
	} );

	return { posts: ids, media: media.map( ( m ) => m.id ) };
}, { folder } );

check( 'three posts filed through the endpoint', seeded.posts.length === 3, seeded.posts.join( ',' ) );
check( 'and one file in the same folder', seeded.media.length === 1, seeded.media.join( ',' ) );

/* --- the count is about posts -------------------------------------------- */

console.log( '\nthe count' );

const counts = await page.evaluate( async ( id ) => {
	const asPosts = await window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category&post_type=post' } );
	const asMedia = await window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category' } );
	const find = ( t ) => ( t.nodes || [] ).find( ( n ) => n.id === id );
	return { posts: ( find( asPosts ) || {} ).count, media: ( find( asMedia ) || {} ).count };
}, folder );

check( 'the post tree counts three posts', counts.posts === 3, String( counts.posts ) );
check( 'the media tree counts one file', counts.media === 1, String( counts.media ) );

await page.goto( `${ BASE }/wp-admin/edit.php`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 30000 } );
await clearNotices();
await page.fill( '.vgml-search', stamp );
await page.waitForTimeout( 900 );

const onScreen = await page.evaluate( ( id ) => {
	const li = document.querySelector( `.vgml-tree .vgml-node[data-id="${ id }"]` );
	const c = li && li.querySelector( '.vgml-count' );
	return c ? c.textContent : null;
}, folder );

check( 'and the screen shows the post count', onScreen === '3', onScreen || 'no count' );

/* --- clicking it filters the list ---------------------------------------- */

console.log( '\nfiltering' );

const total = await page.evaluate( () => {
	const n = document.querySelector( '.tablenav .displaying-num' );
	return n ? parseInt( n.textContent.replace( /[^0-9]/g, '' ), 10 ) : null;
} );

await page.locator( `.vgml-tree .vgml-node[data-id="${ folder }"] .vgml-row` ).click();
await page.waitForFunction( ( was ) => {
	const n = document.querySelector( '.tablenav .displaying-num' );
	const now = n ? parseInt( n.textContent.replace( /[^0-9]/g, '' ), 10 ) : null;
	return now !== null && now !== was;
}, total, { timeout: 30000 } ).catch( () => {} );

const filtered = await page.evaluate( () => {
	const n = document.querySelector( '.tablenav .displaying-num' );
	return {
		total: n ? parseInt( n.textContent.replace( /[^0-9]/g, '' ), 10 ) : null,
		url: window.location.search,
	};
} );

check( 'the list narrows to the folder', filtered.total === 3, `${ total } -> ${ filtered.total }` );
check( 'and the URL says which folder', /media_category=/.test( filtered.url ), filtered.url );

/* --- unfiled -------------------------------------------------------------- */

await page.locator( '.vgml-tree .vgml-row', { hasText: 'Unfiled' } ).first().click();
await page.waitForFunction( () => /vgml_unfiled/.test( window.location.search ), null, { timeout: 30000 } ).catch( () => {} );

const unfiled = await page.evaluate( () => {
	const n = document.querySelector( '.tablenav .displaying-num' );
	return {
		total: n ? parseInt( n.textContent.replace( /[^0-9]/g, '' ), 10 ) : null,
		url: window.location.search,
	};
} );

check( 'unfiled asks for it its own way', /vgml_unfiled=1/.test( unfiled.url ), unfiled.url );
check( 'and excludes the three that are filed', unfiled.total === total - 3,
	`${ unfiled.total }, expected ${ total - 3 }` );

/* --- dragging a post into a folder --------------------------------------- */

console.log( '\ndragging a post' );

await page.goto( `${ BASE }/wp-admin/edit.php`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 30000 } );
await clearNotices();
await page.fill( '.vgml-search', stamp );
await page.waitForTimeout( 900 );

const before = await page.evaluate( ( id ) => {
	const li = document.querySelector( `.vgml-tree .vgml-node[data-id="${ id }"]` );
	const c = li && li.querySelector( '.vgml-count' );
	return c ? parseInt( c.textContent, 10 ) : 0;
}, folder );

// A row that is not already in the folder.
const rowBox = await page.evaluate( ( filed ) => {
	const rows = [ ...document.querySelectorAll( '#the-list tr[id^="post-"]' ) ];
	const row = rows.find( ( r ) => ! filed.includes( parseInt( ( r.id.match( /post-(\d+)/ ) || [] )[ 1 ], 10 ) ) );
	if ( ! row ) { return null; }
	const b = row.getBoundingClientRect();
	return { id: parseInt( row.id.match( /post-(\d+)/ )[ 1 ], 10 ), x: b.x, y: b.y, h: b.height };
}, seeded.posts );

check( 'a post not yet in the folder', !! rowBox, rowBox ? String( rowBox.id ) : 'none' );

if ( rowBox ) {

	const target = await page.locator( `.vgml-tree .vgml-node[data-id="${ folder }"] .vgml-row` ).boundingBox();

	await page.mouse.move( rowBox.x + 200, rowBox.y + rowBox.h / 2 );
	await page.mouse.down();
	await page.mouse.move( rowBox.x + 220, rowBox.y + rowBox.h / 2, { steps: 4 } ); // past the threshold
	await page.mouse.move( target.x + target.width / 2, target.y + target.height / 2, { steps: 12 } );
	await page.mouse.up();

	await page.waitForFunction( ( args ) => {
		const li = document.querySelector( `.vgml-tree .vgml-node[data-id="${ args.id }"]` );
		const c = li && li.querySelector( '.vgml-count' );
		return c && parseInt( c.textContent, 10 ) > args.was;
	}, { id: folder, was: before }, { timeout: 20000 } ).catch( () => {} );

	const after = await page.evaluate( ( id ) => {
		const li = document.querySelector( `.vgml-tree .vgml-node[data-id="${ id }"]` );
		const c = li && li.querySelector( '.vgml-count' );
		return c ? parseInt( c.textContent, 10 ) : 0;
	}, folder );

	check( 'the count went up on screen', after === before + 1, `${ before } -> ${ after }` );

	const stored = await page.evaluate( async ( args ) => {
		const p = await window.wp.apiFetch( { path: `/wp/v2/posts/${ args.post }?_fields=media_category` } );
		return p.media_category || [];
	}, { post: rowBox.id } );

	check( 'and the post really is in the folder', stored.indexOf( folder ) !== -1, stored.join( ',' ) );
}

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

/* tidy */
await call( { action: 'delete', id: folder } );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
