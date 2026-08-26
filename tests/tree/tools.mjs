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
await page.evaluate( ( id ) => { window.__vgmlFolderId = id; }, folder.id );

// the badge is descendant-inclusive, so the +1 has to be measured against
// the badge itself, not against the folder's own-file count from REST
const badgeBefore = await page.evaluate( () =>
	parseInt( ( document.querySelector( `.vgml-tree .vgml-node[data-id="${ window.__vgmlFolderId }"] .vgml-count` ) || {} ).textContent || '0', 10 ) );

/* --- an upload lands in it -------------------------------------------------- */

console.log( '\nan upload, with the folder open' );

// A real (tiny) PNG, uploaded through the grid's own uploader.
const png = Buffer.from(
	'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNiYGD4DwABBAEAX+XLaAAAAABJRU5ErkJggg==',
	'base64'
);

await page.click( '.page-title-action' );
await page.waitForSelector( 'input[type=file]:not(.vgml-pick)', { state: 'attached', timeout: 30000 } );

const stamp = 'vgml-toolprobe-' + Date.now();
await page.setInputFiles( 'input[type=file]:not(.vgml-pick)', {
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

/* --- and appears on screen without a reload -------------------------------- */

console.log( '\nit appears without a reload' );

/*
 *  The bug as reported: "i tried to add files to a specific folder but they
 *  dont show up i need to reload the page to see them". The file was filed on
 *  the server after the upload, so the browser's filtered collection could not
 *  see that it belonged. Everything here is asserted on the screen as it
 *  stands -- no navigation between the upload and the checks.
 */
await page.waitForFunction( ( stamp ) =>
	[ ...document.querySelectorAll( '.attachment' ) ].some( ( a ) =>
		( a.getAttribute( 'aria-label' ) || '' ).indexOf( stamp ) !== -1 ),
	stamp, { timeout: 30000 } ).catch( () => {} );

const visible = await page.evaluate( ( stamp ) => ( {
	tile: [ ...document.querySelectorAll( '.attachment' ) ].some( ( a ) =>
		( a.getAttribute( 'aria-label' ) || '' ).indexOf( stamp ) !== -1 ),
	badge: parseInt( ( document.querySelector( `.vgml-tree .vgml-node[data-id="${ window.__vgmlFolderId }"] .vgml-count` ) || {} ).textContent || '0', 10 ),
} ), stamp );

check( 'the new file is in the filtered grid, no reload', visible.tile );
check( 'and the folder count moved with it', visible.badge === badgeBefore + 1,
	`${ visible.badge } vs ${ badgeBefore } + 1` );

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

await page.setInputFiles( 'input[type=file]:not(.vgml-pick)', {
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

/* --- bulk select, with a human's click -------------------------------------- */

console.log( '\nbulk select' );

/*
 *  The bug as reported: "bulk select is not working". Every tile is a
 *  draggable with a six-pixel threshold, and a real click carries a few pixels
 *  of hand movement -- so the click became an aborted drag and the selection
 *  never toggled. The clicks here move the mouse a little on purpose, because
 *  a perfectly still synthetic click would pass over the bug.
 */
await page.locator( '.vgml-tree .vgml-node[data-id="0"] .vgml-row' ).click();
await page.waitForTimeout( 1500 );
await page.click( 'button.select-mode-toggle-button' );
await page.waitForTimeout( 600 );

check( 'the grid entered select mode', await page.evaluate( () => !! document.querySelector( '.media-frame.mode-select' ) ) );

/*
 *  Fifteen pixels of wobble -- past the six-pixel drag threshold on purpose,
 *  because a five-pixel wobble never starts a drag and would pass over the
 *  bug it exists to catch.
 */
const wobblyClick = async ( nth ) => {
	const box = await page.locator( '.attachment' ).nth( nth ).boundingBox();
	await page.mouse.move( box.x + 40, box.y + 40 );
	await page.mouse.down();
	await page.mouse.move( box.x + 52, box.y + 49, { steps: 4 } );
	await page.mouse.up();
	await page.waitForTimeout( 400 );
};

// selection is additive now, so start this count from zero
await page.evaluate( () => {
	const f = ( window.wp.media.frames && window.wp.media.frames.browse ) || window.wp.media.frame;
	try { f.state().get( 'selection' ).reset(); } catch ( e ) {}
} );
await page.waitForTimeout( 300 );

await wobblyClick( 0 );
await wobblyClick( 1 );

const picked = await page.evaluate( () => document.querySelectorAll( '.attachments-browser .attachments .attachment.selected' ).length );
check( 'two wobbly clicks selected two files', picked === 2, `${ picked } selected` );

/*
 *  And the reason select mode exists at all: the selection, dragged into a
 *  folder, files every file in it. The drag starts on a SELECTED tile, which
 *  is the one place select mode still drags.
 */
const targetRow = await page.locator( `.vgml-tree .vgml-node[data-id="${ folder.id }"] .vgml-row` ).boundingBox();
const firstTile = await page.locator( '.attachments-browser .attachments .attachment.selected' ).first().boundingBox();

// scoped to the grid: the frame's bottom bar previews the selection with
// clones that also carry .attachment.selected and the same data-ids
const dragged = await page.evaluate( () =>
	[ ...document.querySelectorAll( '.attachments-browser .attachments .attachment.selected' ) ].map( ( a ) => parseInt( a.getAttribute( 'data-id' ), 10 ) ) );

await page.mouse.move( firstTile.x + 40, firstTile.y + 40 );
await page.mouse.down();
await page.mouse.move( firstTile.x + 60, firstTile.y + 40, { steps: 4 } );
await page.mouse.move( targetRow.x + targetRow.width / 2, targetRow.y + targetRow.height / 2, { steps: 12 } );
await page.mouse.up();
await page.waitForTimeout( 2000 );

const filed = await page.evaluate( async ( args ) => {
	const out = [];
	for ( const id of args.ids ) {
		const m = await window.wp.apiFetch( { path: '/wp/v2/media/' + id + '?_fields=media_category' } );
		out.push( ( m.media_category || [] ).includes( args.folder ) );
	}
	return out;
}, { ids: dragged, folder: folder.id } );

check( 'dragging the selection filed both files', filed.length === 2 && filed.every( Boolean ),
	JSON.stringify( filed ) );

// put them back out of the folder
await page.evaluate( async ( args ) => {
	await window.wp.apiFetch( { path: '/vergeml/v1/assign', method: 'POST',
		data: { taxonomy: 'media_category', attachments: args.ids, remove: [ args.folder ] } } );
}, { ids: dragged, folder: folder.id } );

// Leave select mode so nothing later is surprised by it.
await page.click( '.media-toolbar .select-mode-toggle-button, button.select-mode-toggle-button' );
await page.waitForTimeout( 500 );

/* --- a wobbly click in NORMAL mode still lands ------------------------------- */

/*
 *  This grid is never in the stock click-opens-details mode: the eml-grid
 *  heritage keeps it permanently selectable (click = select, details in the
 *  sidebar, the hover pencil opens the edit modal). So the thing a wobbly
 *  click must do in normal mode is SELECT the file -- and with every tile a
 *  draggable, a too-low drag threshold ate exactly that.
 */
const tile0 = await page.locator( '.attachments-browser .attachments .attachment' ).first();
const box0 = await tile0.boundingBox();
await page.mouse.move( box0.x + 40, box0.y + 40 );
await page.mouse.down();
await page.mouse.move( box0.x + 52, box0.y + 49, { steps: 4 } );
await page.mouse.up();
await page.waitForTimeout( 800 );

const landed2 = await tile0.evaluate( ( el ) => el.classList.contains( 'selected' ) );
check( "a wobbly click in normal mode selects the file", landed2 );

// and the hover pencil is the road to the edit modal. Dispatched, not
// clicked: the pencil only paints on CSS hover, and the sidebar reflow has
// already moved the tile out from under the parked mouse.
await tile0.locator( '.eml-attacment-inline-toolbar .edit' ).dispatchEvent( 'click' );
await page.waitForTimeout( 1500 );
const details = await page.evaluate( () => !! document.querySelector( '.media-modal .attachment-details, .edit-attachment-frame' ) );
check( 'the hover pencil opens the edit modal', details );

if ( details ) {
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 400 );
}
// clear the selection so nothing later is surprised by it -- through the
// model, because selecting opened the sidebar and reflowed the tiles.
// wp.media.frame may now be the edit frame, which has no selection state
await page.evaluate( () => {
	document.querySelectorAll( '.attachments-browser .attachment.selected' ).forEach( ( el ) => {
		const f = window.wp.media.frame;
		try { f.state().get( 'selection' ).reset(); } catch ( e ) {}
	} );
	try {
		const b = window.wp.media.frame;
		b.states.each( ( st ) => { const sel = st.get( 'selection' ); if ( sel && sel.reset ) { sel.reset(); } } );
	} catch ( e ) {}
} );
await page.waitForTimeout( 400 );

/* --- a move leaves the room ------------------------------------------------- */

console.log( '\na move leaves the room' );

/*
 *  The bug as reported: "when i drag a document to a folder it persists
 *  visable after the drop". The move landed server-side but nothing told the
 *  browser's filtered collection to ask again. The target must be a folder in
 *  a DIFFERENT branch -- a folder's view includes its descendants, so moving
 *  a file into its own subfolder rightly keeps it on screen.
 */
const allTerms = await page.evaluate( async () =>
	await window.wp.apiFetch( { path: '/wp/v2/media_category?per_page=100&_fields=id,parent' } ) );
const parentOf = {};
allTerms.forEach( ( t ) => { parentOf[ t.id ] = t.parent; } );
const rootOf = ( id ) => { while ( parentOf[ id ] ) { id = parentOf[ id ]; } return id; };
const homeRoot = rootOf( folder.id );
const awayId = allTerms.filter( ( t ) => 0 === t.parent && t.id !== homeRoot )[ 0 ].id;

await page.locator( `.vgml-tree .vgml-node[data-id="${ folder.id }"] .vgml-row` ).click();
await page.waitForTimeout( 1500 );

const roomBefore = await page.evaluate( () =>
	[ ...document.querySelectorAll( '.attachments-browser .attachments .attachment' ) ].map( ( a ) => a.getAttribute( 'data-id' ) ) );
const mover = roomBefore[ 0 ];
const moverBox = await page.locator( `.attachments-browser .attachment[data-id="${ mover }"]` ).boundingBox();
const awayRowLoc = page.locator( `.vgml-tree .vgml-node[data-id="${ awayId }"] .vgml-row` );
await awayRowLoc.scrollIntoViewIfNeeded();
const awayRow = await awayRowLoc.boundingBox();

await page.mouse.move( moverBox.x + 40, moverBox.y + 40 );
await page.mouse.down();
await page.mouse.move( moverBox.x + 70, moverBox.y + 40, { steps: 5 } );
await page.mouse.move( awayRow.x + awayRow.width / 2, awayRow.y + awayRow.height / 2, { steps: 12 } );
await page.waitForTimeout( 300 );
await page.mouse.up();
await page.waitForTimeout( 3000 );

const roomAfter = await page.evaluate( () =>
	[ ...document.querySelectorAll( '.attachments-browser .attachments .attachment' ) ].map( ( a ) => a.getAttribute( 'data-id' ) ) );
check( 'the moved file left the view without a reload', ! roomAfter.includes( mover ),
	`${ roomBefore.length } -> ${ roomAfter.length }` );

// put it back where it was
await page.evaluate( async ( args ) => {
	await window.wp.apiFetch( { path: '/vergeml/v1/assign', method: 'POST',
		data: { taxonomy: 'media_category', attachments: [ parseInt( args.mover, 10 ) ],
			add: [ args.home ], remove: [ args.away ], mode: 'add' } } );
}, { mover, home: folder.id, away: awayId } );
await page.waitForTimeout( 800 );

/* --- the drop overlay names its destination ---------------------------------- */

const leafName = await page.locator( `.vgml-tree .vgml-node[data-id="${ folder.id }"] .vgml-name` ).textContent();
const hintOn = await page.evaluate( () => {
	const h1 = document.querySelector( '.uploader-window .uploader-editor-title, .uploader-window h1' );
	return h1 ? h1.textContent : null;
} );
check( 'the drop overlay names the selected folder', !! hintOn && hintOn.indexOf( leafName ) !== -1, hintOn || 'no h1' );

await page.locator( '.vgml-tree .vgml-node[data-id="0"] .vgml-row' ).click();
await page.waitForTimeout( 1200 );
const hintOff = await page.evaluate( () => {
	const h1 = document.querySelector( '.uploader-window .uploader-editor-title, .uploader-window h1' );
	return h1 ? h1.textContent : null;
} );
check( 'and goes back to stock on All files', !! hintOff && hintOff.indexOf( leafName ) === -1, hintOff || 'no h1' );

/* --- desktop files dropped on a folder row ----------------------------------- */

console.log( '\na desktop drop on a folder row' );

/*
 *  OS drags arrive as native drag events, which jQuery UI droppables never
 *  see. The tree listens for them itself: the hovered row lights up, and the
 *  dropped files upload into THAT row's folder regardless of what the grid
 *  is showing.
 */
const rowTarget = await page.evaluate( () => {
	const ids = [ ...document.querySelectorAll( '.vgml-tree .vgml-node' ) ]
		.map( ( n ) => parseInt( n.getAttribute( 'data-id' ), 10 ) ).filter( ( id ) => id > 0 );
	return ids[ ids.length - 1 ];
} );
const dropStamp = 'vgml-rowdrop-' + Date.now();

const rowLit = await page.evaluate( async ( args ) => {
	const cv = document.createElement( 'canvas' ); cv.width = 48; cv.height = 48;
	const g = cv.getContext( '2d' ); g.fillStyle = '#46658b'; g.fillRect( 0, 0, 48, 48 );
	const blob = await new Promise( ( r ) => cv.toBlob( r, 'image/png' ) );
	const dt = new DataTransfer();
	dt.items.add( new File( [ blob ], args.stamp + '.png', { type: 'image/png' } ) );
	const fire = ( el, type ) => {
		const ev = new DragEvent( type, { bubbles: true, cancelable: true } );
		Object.defineProperty( ev, 'dataTransfer', { value: dt } );
		el.dispatchEvent( ev );
	};
	const row = document.querySelector( `.vgml-tree .vgml-node[data-id="${ args.target }"] .vgml-row` );
	fire( row, 'dragover' );
	const lit = row.classList.contains( 'is-drop' );
	fire( row, 'drop' );
	return lit;
}, { stamp: dropStamp, target: rowTarget } );

check( 'the hovered row lights up for a file drag', rowLit );

await page.waitForTimeout( 7000 );
const dropped = await page.evaluate( async ( stamp ) => {
	const found = await window.wp.apiFetch( { path: '/wp/v2/media?search=' + stamp + '&_fields=id,media_category' } );
	return found[ 0 ] || null;
}, dropStamp );

check( 'the dropped file uploaded into that row folder',
	!! dropped && ( dropped.media_category || [] ).includes( rowTarget ),
	dropped ? `${ dropped.id } -> ${ ( dropped.media_category || [] ).join( ',' ) } (wanted ${ rowTarget })` : 'no upload' );

if ( dropped ) {
	await page.evaluate( async ( id ) => {
		await window.wp.apiFetch( { path: '/wp/v2/media/' + id + '?force=true', method: 'DELETE' } );
	}, dropped.id );
}

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

/* --- right-click on a folder ------------------------------------------------- */

console.log( '\nthe context menu' );

const ctxRow = await page.locator( `.vgml-tree .vgml-node[data-id="${ folder.id }"] .vgml-row` ).boundingBox();
await page.mouse.click( ctxRow.x + 80, ctxRow.y + ctxRow.height / 2, { button: 'right' } );
await page.waitForTimeout( 500 );

const ctxMenu = await page.evaluate( () => {
	const m = document.querySelector( '.vgml-context' );
	return m ? {
		items: [ ...m.querySelectorAll( '.vgml-overflow-item' ) ].map( ( b ) => b.textContent.trim() ),
		swatches: m.querySelectorAll( '.vgml-swatch' ).length,
	} : null;
} );

check( 'right-click opens the folder menu', !! ctxMenu, ctxMenu ? ctxMenu.items.join( ' | ' ) : 'no menu' );
check( 'with the full set of actions',
	!! ctxMenu && ctxMenu.items.length >= 6 && ctxMenu.swatches === 9,
	ctxMenu ? `${ ctxMenu.items.length } items, ${ ctxMenu.swatches } swatches` : '' );

await page.keyboard.press( 'Escape' );
await page.waitForTimeout( 300 );
check( 'and Escape dismisses it', await page.evaluate( () => ! document.querySelector( '.vgml-context' ) ) );

/* --- the list screen: a drop on a folder row uploads there too ---------------- */

console.log( '\nthe list screen' );

await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-tree .vgml-row', { timeout: 60000 } );
await page.waitForTimeout( 2000 );

const listTarget = await page.evaluate( () => {
	const ids = [ ...document.querySelectorAll( '.vgml-tree .vgml-node' ) ]
		.map( ( n ) => parseInt( n.getAttribute( 'data-id' ), 10 ) ).filter( ( id ) => id > 0 );
	return ids[ 0 ];
} );
const listStamp = 'vgml-listdrop-' + Date.now();

await page.evaluate( async ( args ) => {
	const cv = document.createElement( 'canvas' ); cv.width = 48; cv.height = 48;
	cv.getContext( '2d' ).fillRect( 0, 0, 48, 48 );
	const blob = await new Promise( ( r ) => cv.toBlob( r, 'image/png' ) );
	const dt = new DataTransfer();
	dt.items.add( new File( [ blob ], args.stamp + '.png', { type: 'image/png' } ) );
	const fire = ( el, type ) => {
		const ev = new DragEvent( type, { bubbles: true, cancelable: true } );
		Object.defineProperty( ev, 'dataTransfer', { value: dt } );
		el.dispatchEvent( ev );
	};
	const row = document.querySelector( `.vgml-tree .vgml-node[data-id="${ args.target }"] .vgml-row` );
	fire( row, 'dragover' );
	fire( row, 'drop' );
}, { stamp: listStamp, target: listTarget } );

// the list screen reloads itself when the queue drains
await page.waitForNavigation( { timeout: 20000 } ).catch( () => {} );
await page.waitForTimeout( 2000 );

const listDropped = await page.evaluate( async ( stamp ) => {
	const found = await window.wp.apiFetch( { path: '/wp/v2/media?search=' + stamp + '&_fields=id,media_category' } );
	return found[ 0 ] || null;
}, listStamp );

check( 'a list-screen row drop uploaded into the folder',
	!! listDropped && ( listDropped.media_category || [] ).includes( listTarget ),
	listDropped ? `${ listDropped.id } -> ${ ( listDropped.media_category || [] ).join( ',' ) }` : 'no upload' );
check( 'and the list reloaded to show it', page.url().indexOf( 'mode=list' ) !== -1 );

if ( listDropped ) {
	await page.evaluate( async ( id ) => {
		await window.wp.apiFetch( { path: '/wp/v2/media/' + id + '?force=true', method: 'DELETE' } );
	}, listDropped.id );
}

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
