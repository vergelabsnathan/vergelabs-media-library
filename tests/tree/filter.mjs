/*
 *  Filtering the list view, however the URL was written.
 *
 *      node tests/tree/filter.mjs http://46.225.66.194 admin VgmlTest7pass
 *
 *  A taxonomy filter can arrive as a slug -- `?media_category=folder-141`, which
 *  is what a link and the folder tree produce -- or as a term id, which is what
 *  the filter dropdown submits and what core's own term-count links use. Both
 *  have to mean the same thing.
 *
 *  They did not. Whether the id form worked depended on `filter_action`, the name
 *  of the Filter button, being in the URL. So the dropdown worked and the same
 *  URL with the button's name stripped off -- a bookmark, a shared link -- came
 *  back with an empty library, because core resolves the query var by slug, found
 *  no term called "143", and filtered everything out. To the person looking at it
 *  the media had gone.
 *
 *  The four forms are checked against each other rather than against a number, so
 *  this keeps working on any library.
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

// List view: this filter only runs there. Loading it once also sets the mode.
await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 1500 );

const term = await page.evaluate( async () => {
	const t = await window.wp.apiFetch( { path: '/vergeml/v1/tree?taxonomy=media_category' } );
	const n = ( t.nodes || [] ).filter( ( n ) => n.count > 0 ).sort( ( a, b ) => b.count - a.count )[ 0 ];
	return n ? { id: n.id, slug: n.slug, name: n.name, count: n.count } : null;
} );

check( 'a folder with files to filter on', !! term, term ? `${ term.name } (${ term.count })` : 'none' );

const load = async ( qs ) => {
	await page.goto( `${ BASE }/wp-admin/upload.php?mode=list&${ qs }`, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 1200 );
	return page.evaluate( () => {
		const select = document.querySelector( 'select[name="media_category"]' );
		// The row count is capped at the page size, so it says nothing about how
		// big the filtered set is. The total is what distinguishes two filters.
		const shown = document.querySelector( '.tablenav .displaying-num' );
		const total = shown ? parseInt( ( shown.textContent || '' ).replace( /[^0-9]/g, '' ), 10 ) : null;

		return {
			rows: document.querySelectorAll( '.wp-list-table tbody tr:not(.no-items)' ).length,
			total: Number.isFinite( total ) ? total : null,
			empty: !! document.querySelector( '.wp-list-table .no-items' ),
			selected: select ? select.value : null,
		};
	} );
};

/* --- the four ways of naming the same folder ----------------------------- */

console.log( '\nthe same folder, four ways' );

const forms = {
	'id + filter_action': `media_category=${ term.id }&filter_action=Filter`,
	'slug alone': `media_category=${ term.slug }`,
	'id alone': `media_category=${ term.id }`,
	'slug + filter_action': `media_category=${ term.slug }&filter_action=Filter`,
};

const seen = {};

for ( const [ label, qs ] of Object.entries( forms ) ) {
	seen[ label ] = await load( qs );
	check( `${ label }: shows files`, seen[ label ].rows > 0 && ! seen[ label ].empty,
		`${ seen[ label ].rows } rows${ seen[ label ].empty ? ', "no items"' : '' }` );
}

const counts = Object.values( seen ).map( ( s ) => s.total );
check( 'all four filter to the same set', new Set( counts ).size === 1, counts.join( ' / ' ) );
check( 'and it matches the count on the folder', counts[ 0 ] === term.count, `${ counts[ 0 ] } vs ${ term.count }` );

// The dropdown carries term ids, so it must show the folder whichever form the
// URL used -- otherwise the tree and the dropdown disagree about what is on
// screen.
const selections = Object.entries( seen ).map( ( [ l, s ] ) => `${ l }=${ s.selected }` );
check( 'the dropdown shows the folder every time',
	Object.values( seen ).every( ( s ) => String( s.selected ) === String( term.id ) ),
	selections.join( ', ' ) );

/* --- the two options above the folders ----------------------------------- */

console.log( '\nany folder, and none' );

const anyFolder = await load( 'media_category=in' );
const noFolder = await load( 'media_category=not_in' );

check( 'files in some folder', anyFolder.total > 0, `${ anyFolder.total } files` );
check( 'files in no folder', noFolder.total !== null, `${ noFolder.total } files` );
check( 'the two are different sets', anyFolder.total !== noFolder.total,
	`${ anyFolder.total } vs ${ noFolder.total }` );

const unfiled = await load( 'attachment-filter=uncategorized' );
check( 'the unfiled filter agrees with "not in"', unfiled.total === noFolder.total,
	`${ unfiled.total } vs ${ noFolder.total }` );

/* --- nonsense ------------------------------------------------------------- */

console.log( '\nnonsense' );

const nonsense = await load( 'media_category=no-such-folder-anywhere' );
check( 'an unknown folder does not fatal', ! errors.length, errors.slice( 0, 1 ).join( '' ) );
check( 'and does not silently show the whole library', nonsense.total !== anyFolder.total,
	`${ nonsense.total } files` );

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
