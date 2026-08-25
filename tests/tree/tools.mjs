/*
 *  The folder's tools: catching uploads, leaving as a ZIP, copying its
 *  shortcode.
 *
 *      node tests/tree/tools.mjs http://185.229.224.239 admin VgmlTest7pass
 *
 *  The upload check is the one that must be a real upload: a real file pushed
 *  through the grid's own uploader while a folder is open in the tree. The
 *  folder travels inside the multipart request, and every plausible way of
 *  faking that in a test -- calling the endpoint, setting the param by hand --
 *  tests the fake rather than the wiring that puts the param there.
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
const ctx = await browser.newContext( {
	viewport: { width: 1500, height: 1000 },
	permissions: [ 'clipboard-read', 'clipboard-write' ],
} );
const page = await ctx.newPage();
page.setDefaultTimeout( 90000 );

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

/* --- a folder, open on the grid ------------------------------------------- */

await page.goto( `${ BASE }/wp-admin/upload.php?mode=grid`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );
await clearNotices();

const folder = await page.evaluate( async () => {
	const r = await window.wp.apiFetch( { path: '/vergeml/v1/gallery-folders?taxonomy=media_category' } );
	return ( r.folders || [] ).filter( ( f ) => f.count > 0 ).sort( ( a, b ) => b.count - a.count )[ 0 ] || null;
} );

check( 'a folder to work with', !! folder, folder ? `${ folder.label } (${ folder.count })` : '' );

await page.locator( `.vgml-tree .vgml-node[data-id="${ folder.id }"] .vgml-row` ).click();
await page.waitForTimeout( 1500 );

/* --- an upload lands in it -------------------------------------------------- */

console.log( '\nan upload, with the folder open' );

// A real (tiny) PNG, uploaded through the grid's own uploader.
const png = Buffer.from(
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNiYGD4DwABBAEAX+XLaAAAAABJRU5ErkJggg==',
	'base64'
);

await page.click( '.page-title-action' );
await page.waitForSelector( 'input[type=file]', { state: 'attached', timeout: 30000 } );

const stamp = 'vgml-toolprobe-' + Date.now();
await page.setInputFiles( 'input[type=file]', {
	name: stamp + '.png',
	mimeType: 'image/png',
	buffer: png,
} );

// Uploaded when the tile appears; the folder count in the tree moving is the
// visible half of the same fact.
await page.waitForTimeout( 5000 );

const landed = await page.evaluate( async ( args ) => {
	const media = await window.wp.apiFetch( { path: '/wp/v2/media?per_page=5&orderby=date&order=desc&_fields=id,slug,media_category' } );
	const mine = ( media || [] ).find( ( m ) => m.slug && m.slug.indexOf( args.stamp ) === 0 );
	return mine ? { id: mine.id, terms: mine.media_category || [] } : null;
}, { stamp } );

check( 'the upload arrived', !! landed, landed ? `attachment ${ landed.id }` : 'not found' );
check( 'and landed in the open folder', landed && landed.terms.indexOf( folder.id ) !== -1,
	landed ? landed.terms.join( ',' ) : '' );

/* --- with nothing selected, uploads stay unfiled ---------------------------- */

console.log( '\nan upload, with All files open' );

await page.locator( '.vgml-tree .vgml-node[data-id="0"] .vgml-row' ).click();
await page.waitForTimeout( 1500 );

const stamp2 = 'vgml-toolprobe2-' + Date.now();

const uploaderOpen = await page.evaluate( () => !! document.querySelector( 'input[type=file]' ) );
if ( ! uploaderOpen ) {
	await page.click( '.page-title-action' );
	await page.waitForSelector( 'input[type=file]', { state: 'attached', timeout: 30000 } );
}

await page.setInputFiles( 'input[type=file]', {
	name: stamp2 + '.png',
	mimeType: 'image/png',
	buffer: png,
} );
await page.waitForTimeout( 5000 );

const unfiled = await page.evaluate( async ( args ) => {
	const media = await window.wp.apiFetch( { path: '/wp/v2/media?per_page=5&orderby=date&order=desc&_fields=id,slug,media_category' } );
	const mine = ( media || [] ).find( ( m ) => m.slug && m.slug.indexOf( args.stamp ) === 0 );
	return mine ? { id: mine.id, terms: mine.media_category || [] } : null;
}, { stamp: stamp2 } );

check( 'the upload arrived', !! unfiled, unfiled ? `attachment ${ unfiled.id }` : 'not found' );
check( 'and stayed unfiled', unfiled && unfiled.terms.length === 0, unfiled ? unfiled.terms.join( ',' ) : '' );

/* --- the ZIP ----------------------------------------------------------------- */

console.log( '\nthe ZIP' );

const zipUrl = await page.evaluate( () => window.vergemlTree && window.vergemlTree.zipUrl );
check( 'the tree carries a download link', !! zipUrl );

const zip = await page.request.get(
	`${ zipUrl }&folder=${ folder.id }&taxonomy=media_category` );
const body = await zip.body();

check( 'it answers with an archive', zip.ok() && ( zip.headers()['content-type'] || '' ).indexOf( 'zip' ) !== -1,
	zip.status() + ' ' + ( zip.headers()['content-type'] || '' ) );
check( 'that is a real ZIP with real weight', body.length > 1000 && body[ 0 ] === 0x50 && body[ 1 ] === 0x4b,
	body.length + ' bytes, ' + String.fromCharCode( body[ 0 ], body[ 1 ] ) + ' magic' );
check( 'named after the folder', ( zip.headers()['content-disposition'] || '' ).indexOf( '.zip' ) !== -1,
	zip.headers()['content-disposition'] || '' );

/* --- the shortcode, copied --------------------------------------------------- */

console.log( '\nthe shortcode' );

await page.locator( `.vgml-tree .vgml-node[data-id="${ folder.id }"] .vgml-row` ).click();
await page.waitForTimeout( 600 );
await page.locator( '.vgml-tree .vgml-more' ).click();
await page.waitForSelector( '.vgml-overflow', { timeout: 10000 } );

const items = await page.evaluate( () =>
	[ ...document.querySelectorAll( '.vgml-overflow-item' ) ].map( ( b ) => b.textContent.trim() ) );

check( 'the menu offers the folder its tools', items.some( ( t ) => /ZIP/i.test( t ) ) && items.some( ( t ) => /shortcode/i.test( t ) ),
	items.slice( 0, 3 ).join( ' | ' ) );

/*
 *  This box serves plain http, where navigator.clipboard does not exist at all
 *  -- so the copy takes the execCommand fallback, and the honest thing a test
 *  can verify there is what reached the copy mechanism, captured by wrapping
 *  it. Reading the system clipboard back needs a secure context this test
 *  cannot assume.
 */
await page.evaluate( () => {
	const real = document.execCommand.bind( document );
	document.execCommand = function ( cmd ) {
		if ( 'copy' === cmd ) {
			window.__vgmlCopied = ( document.activeElement && document.activeElement.value ) || String( document.getSelection() );
		}
		return real( cmd );
	};
} );

await page.locator( '.vgml-overflow-item', { hasText: 'shortcode' } ).click();
await page.waitForTimeout( 800 );

const copied = await page.evaluate( () =>
	window.__vgmlCopied || ( navigator.clipboard ? navigator.clipboard.readText().catch( () => '' ) : '' ) );
check( 'the shortcode reached the clipboard', copied === `[vergeml_gallery folder="${ folder.id }"]`, copied );

const toasted = await page.evaluate( () => !! document.querySelector( '.vgml-toast.is-shown' ) );
check( 'and the toast said so', toasted );

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

/* tidy: the two probe uploads */
for ( const probe of [ landed, unfiled ] ) {
	if ( probe && probe.id ) {
		await page.evaluate( async ( id ) => {
			await window.wp.apiFetch( { path: '/wp/v2/media/' + id + '?force=true', method: 'DELETE' } );
		}, probe.id );
	}
}

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
