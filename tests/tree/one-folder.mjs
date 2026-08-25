/*
 *  The "one folder per file" setting.
 *
 *      node tests/tree/one-folder.mjs http://185.229.224.239 admin VgmlTest7pass
 *
 *  A plain drag always moves -- that is what thirty years of file managers
 *  taught everyone a drag does. Ctrl-drag adds to a second folder, the thing
 *  neither FileBird nor Folders can do, on the modifier that has always meant
 *  "copy". This setting is for people who want the single-folder promise kept
 *  absolutely: with it on, Ctrl is ignored and every drop is a move.
 *
 *  The setting is flipped through its own checkbox on Settings > Media
 *  Taxonomies rather than by writing the option directly: a checkbox that saves
 *  nothing and an option that nothing reads look identical from the outside, and
 *  going through the screen exercises both halves. Then it drags, and reads the
 *  file's terms back.
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
const ctx = await browser.newContext( { viewport: { width: 1500, height: 950 } } );
const page = await ctx.newPage();
page.setDefaultTimeout( 90000 );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 2500 );

const SETTINGS = `${ BASE }/wp-admin/options-general.php?page=media-taxonomies`;
const BOX = 'input[name="vergeml_tax_options[one_folder_per_file]"][type="checkbox"]';

async function setOnePerFile( on ) {
	await page.goto( SETTINGS, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 900 );

	const box = await page.$( BOX );
	if ( ! box ) {
		return false;
	}

	if ( ( await box.isChecked() ) !== on ) {
		await box.click();
	}

	// This screen has several save buttons; this is the one for the options box.
	const save = await page.$( '#eml-submit-tax-settings' );
	if ( ! save ) {
		return false;
	}

	await save.click();
	await page.waitForTimeout( 2500 );

	const after = await page.$( BOX );
	return after ? ( await after.isChecked() ) === on : false;
}

async function openLibrary() {
	await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
	await page.waitForFunction( () => document.querySelectorAll( '.vgml-node' ).length > 3, { timeout: 60000 } );
	await page.waitForTimeout( 800 );
}

const api = ( path, data ) => page.evaluate(
	( d ) => window.wp.apiFetch( d.data ? { path: d.path, method: 'POST', data: d.data } : { path: d.path } ),
	{ path, data }
);

await openLibrary();

async function makeFolder( name ) {
	const tree = await api( '/vergeml/v1/tree?taxonomy=media_category' );
	for ( const n of tree.nodes || [] ) {
		if ( n.name === name ) {
			await api( '/vergeml/v1/folder', { taxonomy: 'media_category', action: 'delete', id: n.id } );
		}
	}
	return ( await api( '/vergeml/v1/folder', { taxonomy: 'media_category', action: 'create', name } ) ).id;
}

const A = await makeFolder( 'OneFolder A' );
const B = await makeFolder( 'OneFolder B' );
const fileId = await page.$eval( '#the-list input[name="media[]"]', ( c ) => parseInt( c.value, 10 ) );
check( 'fixture ready', !! A && !! B && Number.isInteger( fileId ), `${ A }, ${ B }, file ${ fileId }` );

const termsOf = () => page.evaluate( async ( i ) => {
	const r = await window.wp.apiFetch( { path: `/wp/v2/media/${ i }?_fields=media_category` } );
	return ( r.media_category || [] ).slice().sort();
}, fileId );

const setTerms = ( t ) => api( '/vergeml/v1/assign', {
	taxonomy: 'media_category', attachments: [ fileId ], add: t, mode: 'move',
} );

async function reveal() {
	await page.fill( '.vgml-search', '' );
	await page.waitForTimeout( 200 );
	await page.fill( '.vgml-search', 'OneFolder' );
	await page.waitForTimeout( 800 );
}

async function dragOnto( termId, ctrl = false ) {
	const from = await page.$( `#post-${ fileId }` );
	const to = await page.$( `.vgml-node[data-id="${ termId }"] .vgml-row` );
	if ( ! from || ! to ) {
		return 'missing row';
	}
	const a = await from.boundingBox();
	const c = await to.boundingBox();
	await page.mouse.move( a.x + 40, a.y + a.height / 2 );
	await page.mouse.down();
	await page.mouse.move( a.x + 60, a.y + a.height / 2, { steps: 4 } );
	await page.mouse.move( c.x + c.width / 2, c.y + c.height / 2, { steps: 12 } );
	if ( ctrl ) { await page.keyboard.down( 'Control' ); }
	await page.mouse.up();
	if ( ctrl ) { await page.keyboard.up( 'Control' ); }
	await page.waitForTimeout( 1800 );
	return null;
}

console.log( '\noff (the default): ctrl-drag may add a second folder' );

check( 'the setting saves as off', await setOnePerFile( false ) );
await setTerms( [ A ] );
await openLibrary();
await reveal();

let err = await dragOnto( B, true );
check( 'the drag landed', ! err, err || '' );

const both = await termsOf();
check( 'the file is in two folders', both.length === 2, `in ${ both.length }` );

console.log( '\non: ctrl is ignored and the same drag moves' );

check( 'the setting saves as on', await setOnePerFile( true ) );
await setTerms( [ A ] );
await openLibrary();
await reveal();

err = await dragOnto( B, true );
check( 'the drag landed', ! err, err || '' );

const one = await termsOf();
check( 'the file is in exactly one folder', one.length === 1, `in ${ one.length }` );
check( 'and it is the folder it was dropped on', one[ 0 ] === B );

// Put it back, or the next run starts somewhere other than the default.
check( 'the setting saves as off again', await setOnePerFile( false ) );

await setTerms( [] );
for ( const id of [ A, B ] ) {
	await api( '/vergeml/v1/folder', { taxonomy: 'media_category', action: 'delete', id } );
}

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
