/*
 *  The folder tree inside a page builder.
 *
 *      node tests/tree/builders.mjs http://185.229.224.239 admin VgmlTest7pass
 *
 *  Elementor opens WordPress's own media modal to pick an image, so the tree
 *  needs no new interface there -- it needs to be on the page. That was the
 *  whole problem: the Elementor editor is its own screen with its own enqueue
 *  hook, so admin_enqueue_scripts never put the script there at all.
 *
 *  Two separate things get checked, because they failed separately:
 *
 *    - the script and its config reach the editor;
 *    - the tree actually attaches to a frame once a library appears.
 *
 *  The second one needs the Media Library tab. A fresh wp.media frame opens on
 *  Upload Files, where there is no library to attach to -- so a test that opens
 *  a frame and looks immediately reports no tree and blames the plugin.
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
const ctx = await browser.newContext( { viewport: { width: 1600, height: 1000 } } );
const page = await ctx.newPage();
page.setDefaultTimeout( 120000 );

const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER );
await page.fill( '#user_pass', PASS );
await page.click( '#wp-submit' );
await page.waitForTimeout( 2500 );

/* --- find something Elementor can edit ----------------------------------- */

/*
 *  Read the id off the Pages list rather than asking the REST API: the dashboard
 *  this lands on after logging in does not load wp-api-fetch, so wp.apiFetch is
 *  not a function there.
 */
await page.goto( `${ BASE }/wp-admin/edit.php?post_type=page`, { waitUntil: 'domcontentloaded' } );

const target = await page.evaluate( () => {
	const row = document.querySelector( '#the-list tr[id^="post-"]' );
	if ( ! row ) { return null; }
	const m = row.id.match( /post-(\d+)/ );
	return m ? parseInt( m[ 1 ], 10 ) : null;
} );

check( 'a page to open in the builder', !! target, target ? `#${ target }` : 'none' );

if ( ! target ) {
	console.log( `\n${ results.filter( ( r ) => r.ok ).length }/${ results.length } passed\n` );
	await browser.close();
	process.exit( 1 );
}

/* --- the editor ----------------------------------------------------------- */

console.log( '\nElementor' );

await page.goto( `${ BASE }/wp-admin/post.php?post=${ target }&action=elementor`, { waitUntil: 'domcontentloaded' } );

const panel = await page.waitForSelector( '#elementor-panel, .elementor-panel', { timeout: 120000 } ).catch( () => null );
check( 'the builder opened', !! panel );

if ( ! panel ) {
	check( 'Elementor is installed and active', false, 'no editor panel appeared' );
	console.log( `\n${ results.filter( ( r ) => r.ok ).length }/${ results.length } passed\n` );
	await browser.close();
	process.exit( 1 );
}

await page.waitForTimeout( 8000 );

const env = await page.evaluate( () => ( {
	wpMedia: !! ( window.wp && wp.media ),
	config: !! window.vergemlTree,
	script: !! document.querySelector( 'script[src*="vergeml-tree.js"]' ),
	folders: window.vergemlTree && window.vergemlTree.boot ? ( window.vergemlTree.boot.nodes || [] ).length : 0,
} ) );

check( 'wp.media is on the editor screen', env.wpMedia );
check( 'our script reaches it', env.script );
check( 'and its configuration', env.config );
check( 'with the tree already in the page', env.folders > 0, `${ env.folders } folders` );

/*
 *  The modal wrapper has to be in place. It is applied once, to wp.media's own
 *  Modal prototype -- and it used to be attempted exactly once at
 *  DOMContentLoaded, before Elementor had finished loading wp.media, so it gave
 *  up silently and every media modal in the builder came up without a tree.
 */
const wrapped = await page.evaluate( () =>
	!! ( window.wp && wp.media && wp.media.view && wp.media.view.Modal && wp.media.view.Modal.prototype.vgmlWrapped ) );

check( 'the modal wrapper survived the builder loading order', wrapped );

/* --- a real picker -------------------------------------------------------- */

console.log( '\nthe picker' );

const frame = await page.evaluate( async () => {
	const seen = [];
	const f = wp.media( { title: 'probe', multiple: false, library: { type: 'image' } } );

	[ 'open', 'content:create:browse', 'content:render:browse' ].forEach( ( ev ) => f.on( ev, () => seen.push( ev ) ) );

	f.open();
	await new Promise( ( r ) => setTimeout( r, 2000 ) );

	// A fresh frame opens on Upload Files; this is the Media Library tab.
	f.content.mode( 'browse' );
	await new Promise( ( r ) => setTimeout( r, 4000 ) );

	return { seen, mode: f.content && f.content.mode ? f.content.mode() : null };
} );

check( 'the modal opened', frame.seen.indexOf( 'open' ) !== -1, frame.seen.join( ', ' ) );
check( 'and rendered a library', frame.mode === 'browse', String( frame.mode ) );

const inModal = await page.evaluate( () => ( {
	browser: !! document.querySelector( '.media-modal .attachments-browser' ),
	tree: !! document.querySelector( '.media-modal .vgml-tree' ),
	rows: document.querySelectorAll( '.media-modal .vgml-row' ).length,
	search: !! document.querySelector( '.media-modal .vgml-search' ),
} ) );

check( 'the tree is in the picker', inModal.tree );
check( 'with folders in it', inModal.rows > 2, `${ inModal.rows } rows` );
check( 'and its search box', inModal.search );

/* --- filtering from inside the builder ------------------------------------ */

const filtered = await page.evaluate( async () => {
	/*
	 *  A real folder, not "All files" or "Unfiled". Those two carry counts and
	 *  look like folders, but they set a different property on the frame -- so
	 *  picking one and then looking for a folder filter finds nothing and reads
	 *  as a broken filter.
	 */
	const rows = [ ...document.querySelectorAll( '.media-modal .vgml-node[data-id]' ) ];
	const withFiles = rows.find( ( r ) => {
		const id = parseInt( r.getAttribute( 'data-id' ), 10 );
		const c = r.querySelector( '.vgml-count' );
		return id > 0 && c && parseInt( c.textContent, 10 ) > 0;
	} );

	if ( ! withFiles ) {
		return { picked: null };
	}

	const name = ( withFiles.querySelector( '.vgml-name' ) || {} ).textContent;
	withFiles.querySelector( '.vgml-row' ).click();
	await new Promise( ( r ) => setTimeout( r, 3000 ) );

	let props = null;
	try {
		props = wp.media.frames && wp.media.frame
			? wp.media.frame.state().get( 'library' ).props.toJSON().media_category
			: null;
	} catch ( e ) { props = 'unreadable'; }

	return { picked: name, props };
} );

check( 'a folder can be picked in the builder', !! filtered.picked, filtered.picked || 'no folder had files' );

if ( filtered.picked ) {
	check( 'and it filters the picker itself', filtered.props > 0, String( filtered.props ) );
}

await page.screenshot( { path: 'tests/tree/shots/elementor-modal.png' } );

/* --- Divi ----------------------------------------------------------------- */

/*
 *  Divi's visual builder runs on the FRONT END, which is the whole reason it
 *  needs its own entry point: admin_enqueue_scripts never fires there, so a
 *  media plugin can be completely absent inside the builder while working
 *  perfectly everywhere else.
 */
console.log( '\nDivi' );

const diviPage = await page.evaluate( async () => {
	const r = await fetch( '/wp-json/wp/v2/pages?per_page=20&_fields=id,link,slug', { credentials: 'same-origin' } );
	if ( ! r.ok ) { return null; }
	const pages = await r.json();
	const sample = pages.find( ( p ) => p.slug === 'sample-page' );
	return sample ? sample.link : ( pages[ 0 ] ? pages[ 0 ].link : null );
} );

if ( ! diviPage ) {
	check( 'a page to open in Divi', false, 'could not resolve a permalink' );
} else {

	await page.goto( `${ diviPage }${ diviPage.indexOf( '?' ) === -1 ? '?' : '&' }et_fb=1&PageSpeed=off`,
		{ waitUntil: 'domcontentloaded' } );

	const builder = await page.waitForSelector( '#et-fb-app, #et-boc, .et-fb-iframe', { timeout: 120000 } ).catch( () => null );

	check( 'the Divi builder launched', !! builder,
		builder ? '' : 'Divi not installed, or the builder is off for this page' );

	if ( builder ) {

		await page.waitForTimeout( 12000 );

		const divi = await page.evaluate( () => ( {
			config: !! window.vergemlTree,
			script: !! document.querySelector( 'script[src*="vergeml-tree.js"]' ),
			wrapped: !! ( window.wp && wp.media && wp.media.view && wp.media.view.Modal && wp.media.view.Modal.prototype.vgmlWrapped ),
			folders: window.vergemlTree && window.vergemlTree.boot ? ( window.vergemlTree.boot.nodes || [] ).length : 0,
		} ) );

		check( 'the tree reaches the front-end builder', divi.script && divi.config,
			`script ${ divi.script }, config ${ divi.config }` );
		check( 'with its folders', divi.folders > 0, `${ divi.folders } folders` );
		check( 'and the modal wrapper', divi.wrapped );

		await page.evaluate( async () => {
			const f = wp.media( { title: 'probe', multiple: false } );
			f.open();
			await new Promise( ( r ) => setTimeout( r, 2000 ) );
			f.content.mode( 'browse' );
			await new Promise( ( r ) => setTimeout( r, 4000 ) );
		} );

		const diviModal = await page.evaluate( () => ( {
			tree: !! document.querySelector( '.media-modal .vgml-tree' ),
			rows: document.querySelectorAll( '.media-modal .vgml-row' ).length,
		} ) );

		check( 'the tree is in the Divi picker', diviModal.tree );
		check( 'with folders in it', diviModal.rows > 2, `${ diviModal.rows } rows` );

		await page.screenshot( { path: 'tests/tree/shots/divi-modal.png' } );
	}
}

/*
 *  Only our own errors count. Both builders throw plenty of their own on a test
 *  box with no API keys -- Divi alone reports a missing Google Maps callback --
 *  and failing on those would make this test about their configuration.
 */
const ours = errors.filter( ( e ) => /vergeml|vgml/i.test( e ) );
check( 'no javascript errors from the tree', ours.length === 0, ours.slice( 0, 2 ).join( ' | ' ) );

if ( errors.length ) {
	console.log( `  (${ errors.length } errors from the builders themselves, ignored)` );
}

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
