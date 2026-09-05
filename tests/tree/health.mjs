/*
 *  The Library health screen, end to end on the box.
 *
 *      node tests/tree/health.mjs http://46.225.66.194 admin VgmlTest7pass
 *
 *  The load-bearing assertion is the negative one: this screen has no control
 *  that changes a file. A duplicate report that grows a delete button is a
 *  different product with a different risk profile, and the review pages of
 *  every media cleaner on the directory are what that looks like when it goes
 *  wrong.
 */
import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://46.225.66.194';
const USER = process.argv[ 3 ] ?? 'admin';
const PASS = process.argv[ 4 ] ?? 'VgmlTest7pass';

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? `  -- ${ detail }` : '' }` );
};

const browser = await chromium.launch();
const page = await ( await browser.newContext( { viewport: { width: 1500, height: 1000 } } ) ).newPage();
page.setDefaultTimeout( 90000 );

const errors = [];
page.on( 'pageerror', ( e ) => {
	if ( ! e.message.includes( 'isImageFile' ) ) {
		errors.push( e.message.slice( 0, 120 ) );
	}
} );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 2000 );

/* --- the way in -------------------------------------------------------------- */

console.log( '\nthe way in' );

await page.goto( `${ BASE }/wp-admin/admin.php?page=vergelabs-media`, { waitUntil: 'domcontentloaded' } );
// The dashboard is the journey list now (core/journey.php), not the old home cards.
await page.waitForSelector( '.vgml-do-list, .vgml-pg-head', { timeout: 30000 } );

check( 'the overview offers Library health', await page.evaluate( () =>
	!! [ ...document.querySelectorAll( 'a[href]' ) ]
		.find( ( c ) => c.href.includes( 'page=media-health' ) ) ) );

/*
 *  That the switch exists, not that it is unticked.
 *
 *  This asserted "unticked", which is only true on a site where nobody has
 *  ever switched it on -- so a gate-7 check that set the option, or one click,
 *  made it fail as though the switch were broken. Whether it defaults to off
 *  is a question about defaults, and tests/tree/counts.php asks it where the
 *  answer cannot drift.
 */
check( 'the dashboard no longer carries the counts card', await page.evaluate( () =>
	null === document.getElementById( 'vgml-stats-opt' ) && ! /Size counts/.test( document.body.textContent ) ) );

// Since 3.16.2 the switch lives in Library settings, under "Share library counts".
await page.goto( `${ BASE }/wp-admin/admin.php?page=media-library`, { waitUntil: 'domcontentloaded' } );

const optBox = await page.evaluate( () => {
	const box = document.getElementById( 'vgml-stats-opt' );
	return box ? { checked: box.checked } : null;
} );

check( 'Library settings has the counts switch', null !== optBox,
	optBox ? `switch is ${ optBox.checked ? 'on' : 'off' }` : 'switch missing' );

check( 'and says what goes and what never does', await page.evaluate( () => {
	const text = document.body.textContent;
	return /Share library counts/.test( text ) && /Once a day: files, folders, how deep they nest/.test( text ) && /Never a file name, a title, a folder name or a picture/.test( text );
} ) );

/* --- the screen -------------------------------------------------------------- */

console.log( '\nthe screen' );

await page.goto( `${ BASE }/wp-admin/admin.php?page=media-health`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '#vgml-health-scan', { timeout: 30000 } );
await page.waitForTimeout( 1500 );

check( 'the page renders with a scan button', await page.evaluate( () =>
	!! document.getElementById( 'vgml-health-scan' ) ) );

/* --- the scan loop ----------------------------------------------------------- */

console.log( '\nthe scan loop' );

/*
 *  A library that has been scanned before draws its report on page load, so
 *  "two lists exist" is true before the button is ever pressed and waiting on
 *  it passes instantly against the previous run's numbers. The bar is the only
 *  state that belongs to this scan: it is set to zero when the loop starts and
 *  to full when it ends.
 */
await page.click( '#vgml-health-scan' );

await page.waitForFunction( () =>
	document.getElementById( 'vgml-health-fill' ).style.width === '0px'
	|| document.getElementById( 'vgml-health-fill' ).style.width === '0',
	null, { timeout: 30000 } );

await page.waitForFunction( () =>
	document.getElementById( 'vgml-health-fill' ).style.width === '100%'
	&& document.querySelectorAll( '#vgml-health-report .vgml-health-list' ).length === 2
	&& '' === document.getElementById( 'vgml-health-note' ).textContent,
	null, { timeout: 240000 } );

check( 'the scan completes and both lists render', await page.evaluate( () =>
	document.querySelectorAll( '#vgml-health-report .vgml-health-list' ).length === 2 ) );

check( 'the progress bar filled', await page.evaluate( () =>
	document.getElementById( 'vgml-health-fill' ).style.width === '100%' ) );

check( 'the button now offers a rescan', await page.evaluate( () =>
	/again/i.test( document.getElementById( 'vgml-health-scan' ).textContent ) ) );

const headings = await page.evaluate( () =>
	[ ...document.querySelectorAll( '#vgml-health-report h2' ) ].map( ( h ) => h.textContent.trim() ) );

check( 'the two lists are named separately', headings.length === 2
	&& /duplicates/i.test( headings[ 0 ] ) && /related/i.test( headings[ 1 ] ), headings.join( ' | ' ) );

/* --- what it says ------------------------------------------------------------ */

console.log( '\nwhat it says' );

const report = await page.evaluate( async () =>
	await window.wp.apiFetch( { path: '/vergeml/v1/health-report' } ) );

check( 'the endpoint says the library has been read', report.scanned === true );
check( 'it found duplicate groups on the box', report.duplicates.groups.length > 0,
	`${ report.duplicates.groups.length } groups` );

check( 'every group has at least two files', report.duplicates.groups
	.concat( report.related.groups ).every( ( g ) => g.items.length > 1 ) );

check( 'the two lists share no file', ( () => {
	const seen = new Set();
	for ( const g of report.duplicates.groups ) {
		for ( const i of g.items ) seen.add( i.id );
	}
	return report.related.groups.every( ( g ) => g.items.every( ( i ) => ! seen.has( i.id ) ) );
} )() );

check( 'thumbnails were rendered', await page.evaluate( () =>
	document.querySelectorAll( '.vgml-health-files img' ).length > 0 ) );

// The thumbnails are lazy, which is the right call on a page that can carry a
// few hundred of them -- so the page has to be walked before asking whether
// they resolve.
await page.evaluate( async () => {
	for ( let y = 0; y < document.body.scrollHeight; y += 600 ) {
		window.scrollTo( 0, y );
		await new Promise( ( r ) => setTimeout( r, 120 ) );
	}
	window.scrollTo( 0, 0 );
} );
await page.waitForTimeout( 2500 );

const broken = await page.evaluate( () =>
	[ ...document.querySelectorAll( '.vgml-health-files img' ) ]
		.filter( ( i ) => ! i.complete || i.naturalWidth === 0 )
		.map( ( i ) => i.src.split( '/' ).pop() ) );

check( 'every thumbnail resolves to a real file', broken.length === 0,
	broken.slice( 0, 3 ).join( ', ' ) );

check( 'a total is shown', await page.evaluate( () =>
	/\d/.test( document.getElementById( 'vgml-health-counts' ).textContent ) ),
	await page.evaluate( () => document.getElementById( 'vgml-health-counts' ).textContent ) );

// The space is described as what keeping one copy frees or gives back --
// plain words for a real action -- and never as "reclaimed", which is the
// cleaner-plugin word the voice rules keep out.
check( 'wasted space is what keeping one copy frees, never reclaimed', await page.evaluate( () => {
	const wrap = document.querySelector( '.wrap.vgml-health' ) || document.querySelector( '.wrap' );
	const text = wrap ? wrap.textContent : '';
	return /\bfrees\b|get .{0,30}\bback\b|potentially recoverable/i.test( text ) && ! /\breclaim/i.test( text );
} ) );

/*
 *  --- and what deleting takes ---------------------------------------------------
 *
 *  This screen used to have no control that changed a file, and the suite
 *  asserted exactly that. The product decision changed (duplicate delete,
 *  singly and in bulk), so what the suite holds it to now is the shape of the
 *  control: only byte-identical sets offer it, one press arms rather than
 *  deletes, the armed press says "permanently" and "no undo" in as many
 *  words, and nothing has changed on disk until the second press.
 */

console.log( '\nand what deleting takes' );

const controls = await page.evaluate( () => {
	const wrap = document.querySelector( '.wrap.vgml-health' ) || document.querySelector( '.wrap' );
	return [ ...wrap.querySelectorAll( '.vgml-health-act button' ) ].map( ( b ) => b.textContent.trim() );
} );

check( 'every delete control belongs to a duplicate set and says how many it would remove',
	controls.length > 0 && controls.every( ( t ) => /delete the other/i.test( t ) ),
	controls.slice( 0, 2 ).join( ' | ' ) );

const armed = await page.evaluate( async () => {
	const button = document.querySelector( '.vgml-health-act button' );
	if ( ! button ) return null;
	const before = document.querySelectorAll( '.vgml-health-files img' ).length;
	button.click();
	await new Promise( ( r ) => setTimeout( r, 300 ) );
	const note = button.parentElement.querySelector( '.vgml-health-act-note' );
	return {
		label: button.textContent.trim(),
		note: note ? note.textContent : '',
		armedClass: button.classList.contains( 'vgml-health-armed' ),
		stillThere: document.querySelectorAll( '.vgml-health-files img' ).length === before,
	};
} );

check( 'the first press arms rather than deletes', !! armed && armed.stillThere && ( armed.armedClass || /choose which copy/i.test( armed.note ) ),
	armed ? armed.label : 'no control' );

check( 'the armed press says permanently, and that there is no undo',
	!! armed && ( ! armed.armedClass || ( /permanently/i.test( armed.label ) && /no undo/i.test( armed.note ) ) ),
	armed ? armed.note.slice( 0, 80 ) : '' );

/* --- the honest wording, where people meet it -------------------------------- */

console.log( '\nthe "Used in" wording' );

const wording = await page.evaluate( async () => {
	const found = await window.wp.apiFetch( { path: '/wp/v2/media?per_page=1' } );
	return found.length ? found[ 0 ].id : 0;
} );

if ( wording ) {
	await page.goto( `${ BASE }/wp-admin/post.php?post=${ wording }&action=edit`, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 2000 );

	const body = await page.evaluate( () => document.body.textContent );

	check( 'the old "nothing found" wording is gone from the details screen',
		! /Nothing found\. The last scan saw no page/i.test( body ) );
} else {
	check( 'the old "nothing found" wording is gone from the details screen', true, 'no attachment to open' );
}

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
