/*
 *  A folder, shown as a gallery.
 *
 *      node tests/tree/gallery.mjs http://185.229.224.239 admin VgmlTest7pass
 *
 *  The query behind this is old -- Enhanced Media Library's `[gallery
 *  media_category="press"]` has worked for years. What it never had was a way to
 *  reach it: you had to know the shortcode existed, know the folder's slug, and
 *  switch on a setting that ships off. The block is the same idea with the
 *  knowing removed.
 *
 *  The last check is the one that matters. A folder gallery is not a list of
 *  files, it is a folder: put an image in the folder and every page showing that
 *  gallery has it, with nothing re-edited. WordPress's own gallery block freezes
 *  a list of ids at the moment you insert it. If that property is broken this is
 *  just a slower way to make an ordinary gallery.
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
const ctx = await browser.newContext( { viewport: { width: 1500, height: 1000 } } );
const page = await ctx.newPage();
page.setDefaultTimeout( 90000 );

const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 2500 );

/* --- a folder with images in it ------------------------------------------ */

await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );

const folder = await page.evaluate( async () => {
	const r = await window.wp.apiFetch( { path: '/vergeml/v1/gallery-folders?taxonomy=media_category' } );
	const withImages = ( r.folders || [] ).filter( ( f ) => f.count > 1 ).sort( ( a, b ) => b.count - a.count )[ 0 ];
	return withImages || null;
} );

check( 'a folder with images', !! folder, folder ? `${ folder.label } (${ folder.count })` : 'none' );

if ( ! folder ) {
	console.log( `\n${ results.filter( ( r ) => r.ok ).length }/${ results.length } passed\n` );
	await browser.close();
	process.exit( 1 );
}

check( 'the folder list gives a readable path', /\S/.test( folder.label ), folder.label );

/* --- the block, in the editor -------------------------------------------- */

console.log( '\nin the editor' );

await page.goto( `${ BASE }/wp-admin/post-new.php?post_type=post`, { waitUntil: 'domcontentloaded' } );
await page.waitForFunction( () => window.wp && wp.data && wp.data.select( 'core/block-editor' ), null, { timeout: 90000 } );
await page.waitForTimeout( 2500 );

const registered = await page.evaluate( () => !! wp.blocks.getBlockType( 'vergelabs/folder-gallery' ) );
check( 'the block is registered in the editor', registered );

const inspector = await page.evaluate( () => {
	const b = wp.blocks.getBlockType( 'vergelabs/folder-gallery' );
	return b ? { title: b.title, category: b.category, attrs: Object.keys( b.attributes || {} ).length } : null;
} );

check( 'it is named and filed under media', inspector && inspector.category === 'media', inspector ? `${ inspector.title } / ${ inspector.category }` : '' );
check( 'with its settings', inspector && inspector.attrs >= 7, inspector ? `${ inspector.attrs } attributes` : '' );

/*
 *  The preview the editor draws is ServerSideRender, which asks the block
 *  renderer endpoint. Asking it directly tests the same thing without fighting
 *  whatever else is installed -- Divi replaces the content of a new page with a
 *  placeholder of its own, so driving the editor's save path here measures the
 *  other plugin.
 */
const previewed = await page.evaluate( async ( id ) => {
	/*
	 *  A plain slash in the route, not %2F. The encoded form does not match the
	 *  route pattern and comes back rest_no_route -- which looks exactly like a
	 *  block that does not render.
	 */
	const path = '/wp/v2/block-renderer/vergelabs/folder-gallery'
		+ '?context=edit'
		+ '&attributes[folder]=' + id
		+ '&attributes[columns]=3'
		+ '&attributes[size]=medium';
	try {
		const r = await window.wp.apiFetch( { path } );
		return ( r && r.rendered ) || '';
	} catch ( e ) {
		return 'ERROR: ' + e.message;
	}
}, folder.id );

const previewImages = ( previewed.match( /<img /g ) || [] ).length;

check( 'the editor preview renders the real gallery', previewImages === folder.count,
	`${ previewImages } of ${ folder.count }` );
check( 'using core gallery markup', previewed.indexOf( 'wp-block-gallery' ) !== -1 );

/* --- on the front of the site --------------------------------------------- */

console.log( '\npublished' );

/*
 *  Published through the REST API with the block comment written out, which is
 *  exactly what the editor saves. Going through the editor's own save here would
 *  be testing whichever page builder happens to be active.
 */
const made = await page.evaluate( async ( args ) => {
	const content = '<!-- wp:vergelabs/folder-gallery {"folder":' + args.id + ',"columns":3,"size":"medium"} /-->';
	const post = await window.wp.apiFetch( {
		path: '/wp/v2/posts',
		method: 'POST',
		// A unique title, so a leftover from an earlier run cannot shadow this
		// one's permalink -- which is exactly what happened the first time.
		data: { title: 'Folder gallery test ' + args.stamp, status: 'publish', content: content },
	} );
	return { id: post.id, link: post.link };
}, { id: folder.id, stamp: Date.now() } );

check( 'a page was published with the block', !! made.id, made.link || '' );

await page.goto( made.link, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 1000 );

const front = await page.evaluate( () => ( {
	gallery: !! document.querySelector( '.wp-block-gallery.vgml-folder-gallery' ),
	images: document.querySelectorAll( '.vgml-folder-gallery img' ).length,
	columns: ( ( document.querySelector( '.vgml-folder-gallery' ) || {} ).className || '' ).match( /columns-(\d)/ ),
} ) );

check( 'the gallery is on the page', front.gallery );
check( 'with every image in the folder', front.images === folder.count, `${ front.images } of ${ folder.count }` );
check( 'and the columns asked for', front.columns && front.columns[ 1 ] === '3', front.columns ? front.columns[ 0 ] : 'none' );

/* --- the point of the whole thing ----------------------------------------- */

console.log( '\nit stays a folder' );

await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );

const added = await page.evaluate( async ( id ) => {
	const media = await window.wp.apiFetch( { path: '/wp/v2/media?per_page=100&_fields=id,media_category' } );
	const outsider = ( media || [] ).find( ( m ) => ! ( m.media_category || [] ).includes( id ) );

	if ( ! outsider ) {
		return 0;
	}

	await window.wp.apiFetch( {
		path: '/vergeml/v1/assign',
		method: 'POST',
		data: { taxonomy: 'media_category', attachments: [ outsider.id ], add: [ id ], mode: 'add' },
	} );

	return outsider.id;
}, folder.id );

check( 'an image was added to the folder', !! added, added ? `attachment ${ added }` : 'none available' );

if ( added ) {

	await page.goto( made.link, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 1200 );

	const after = await page.evaluate( () => document.querySelectorAll( '.vgml-folder-gallery img' ).length );

	check( 'the published page shows it, with nothing re-edited', after === front.images + 1,
		`${ front.images } -> ${ after }` );

	// put it back
	await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );
	await page.evaluate( async ( args ) => {
		await window.wp.apiFetch( {
			path: '/vergeml/v1/assign',
			method: 'POST',
			data: { taxonomy: 'media_category', attachments: [ args.id ], remove: [ args.folder ] },
		} );
	}, { id: added, folder: folder.id } );
}

/* tidy: the published page was only ever a fixture */
if ( made && made.id ) {
	await page.evaluate( async ( id ) => {
		await window.wp.apiFetch( { path: '/wp/v2/posts/' + id + '?force=true', method: 'DELETE' } );
	}, made.id );
}

check( 'no javascript errors from the block', ! errors.some( ( e ) => /vergeml|folder-gallery/i.test( e ) ),
	errors.slice( 0, 2 ).join( ' | ' ) );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
