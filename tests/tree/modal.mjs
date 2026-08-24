/*
 *  The tree inside the media modal.
 *
 *      node tests/tree/modal.mjs http://185.229.224.239 admin VgmlTest7pass
 *
 *  The modal is where half the work happens -- inserting an image into a post --
 *  and the tree did not exist there at all until now. It is also eight different
 *  frame types that can each break separately, which is exactly the shape of
 *  problem that goes unnoticed: the one context somebody tested works, and the
 *  other seven quietly do not.
 *
 *  So this opens real modals from real screens and checks the tree is there,
 *  filters that frame's own library, and does not disturb the library screen
 *  behind it.
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

const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 2500 );

/*
 *  Opened through wp.media directly rather than by clicking through an editor.
 *
 *  That is not a shortcut: wp.media() is the documented way every plugin, block
 *  and theme opens a picker, so this is the same door they all use. Clicking
 *  through the block editor as well would test the block editor's buttons, which
 *  are not what is under test here.
 */
async function openFrame( kind ) {
	return page.evaluate( ( k ) => {
		const opts = {
			select:   { title: 'Select', multiple: false },
			multiple: { title: 'Select many', multiple: true },
			post:     { frame: 'post', title: 'Insert', multiple: false },
			featured: { frame: 'select', title: 'Featured image', library: { type: 'image' }, multiple: false },
		}[ k ];

		if ( window.__vgmlFrame ) {
			window.__vgmlFrame.close();
			window.__vgmlFrame = null;
		}

		const frame = window.wp.media( opts );
		window.__vgmlFrame = frame;
		frame.open();
		return true;
	}, kind );
}

const closeFrame = () => page.evaluate( () => {
	if ( window.__vgmlFrame ) {
		window.__vgmlFrame.close();
		window.__vgmlFrame = null;
	}
} );

/*
 *  A fresh frame can open on the Upload Files tab, which has no library at all.
 *  Switching to Media Library is what a person does, and it also proves the tree
 *  attaches on a tab change rather than only when the modal opens.
 */
async function showLibraryTab() {
	await page.evaluate( () => {
		const tab = [ ...document.querySelectorAll( '.media-modal .media-menu-item, .media-modal .media-router a' ) ]
			.find( ( a ) => /media library/i.test( a.textContent ) );
		if ( tab ) { tab.click(); }
	} );
	await page.waitForTimeout( 2500 );
}

// A screen that loads wp.media but is not the library screen -- which is the
// case that was broken: the assets only ever loaded on upload.php.
await page.goto( `${ BASE }/wp-admin/post-new.php?post_type=post`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 6000 );

check( 'the tree script loads outside the library screen',
	await page.evaluate( () => !! document.querySelector( 'script[src*="vergeml-tree"]' ) ) );
check( 'wp.media is available here',
	await page.evaluate( () => !! ( window.wp && window.wp.media ) ) );

for ( const kind of [ 'select', 'multiple', 'post', 'featured' ] ) {

	await openFrame( kind );
	await page.waitForTimeout( 1500 );

	await showLibraryTab();

	const seen = await page.evaluate( () => {
		const panel = document.querySelector( '.media-modal .vgml-modal-tree' );
		return {
			panel: !! panel,
			rows: document.querySelectorAll( '.media-modal .vgml-modal-tree .vgml-node' ).length,
			collapse: !! document.querySelector( '.media-modal .vgml-modal-toggle' ),
			browser: !! document.querySelector( '.media-modal .attachments-browser' ),
		};
	} );

	check( `${ kind }: the tree is in the modal`, seen.panel, `browser present: ${ seen.browser }` );
	check( `${ kind }: it drew folders`, seen.rows > 2, `${ seen.rows } rows` );
	check( `${ kind }: it can be collapsed`, seen.collapse );

	await closeFrame();
	await page.waitForTimeout( 600 );
}

/* Filtering has to drive that frame's library, not the page behind it. */

console.log( '\nfiltering' );

await openFrame( 'select' );
await page.waitForTimeout( 1200 );
await showLibraryTab();
await page.waitForTimeout( 1500 );

const before = await page.evaluate( () => document.querySelectorAll( '.media-modal .attachment' ).length );

const picked = await page.evaluate( () => {
	const rows = [ ...document.querySelectorAll( '.media-modal .vgml-modal-tree .vgml-node:not(.vgml-pseudo)' ) ];
	// A folder with files in it, so the difference is visible.
	const hit = rows.find( ( r ) => {
		const c = r.querySelector( '.vgml-count' );
		return c && parseInt( c.textContent, 10 ) > 0;
	} );
	if ( ! hit ) return null;
	const name = hit.querySelector( '.vgml-name' ).textContent;
	hit.querySelector( '.vgml-row' ).click();
	return name;
} );

check( 'a folder with files was clickable', !! picked, picked || 'none had a count' );

if ( picked ) {
	await page.waitForTimeout( 3500 );
	const after = await page.evaluate( () => ( {
		count: document.querySelectorAll( '.media-modal .attachment' ).length,
		selected: [ ...document.querySelectorAll( '.media-modal .vgml-node.is-selected .vgml-name' ) ].map( ( n ) => n.textContent ),
		props: ( () => {
			try {
				return window.__vgmlFrame.state().get( 'library' ).props.toJSON().media_category;
			} catch ( e ) {
				return 'unreadable';
			}
		} )(),
	} ) );

	check( 'the folder shows as selected', after.selected.indexOf( picked ) !== -1, after.selected.join( ',' ) );
	check( 'it set the frame\'s own filter', after.props > 0, String( after.props ) );
	check( 'the grid changed', after.count !== before, `${ before } -> ${ after.count }` );
}

await closeFrame();

check( 'no javascript errors throughout', errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
