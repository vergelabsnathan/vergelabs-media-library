import { test, expect } from './fixtures.mjs';

/*
 *  Grid and list, walked as one person doing the same things in both.
 *
 *      MODES_WALK=1 pnpm test:ui modes.spec
 *
 *  The table this follows is docs/superpowers/specs/2026-09-06-grid-list-modes.md:
 *  open, select, bulk move, drag, folder filter, counts, search, sort and the
 *  keyboard, each asserted under ?mode=grid and under ?mode=list. Every row
 *  found two surfaces that disagreed on 2026-09-06 -- Unfiled showing every
 *  file in the list, a moved row that stayed, a search that dropped the
 *  folder, a tree highlighting a folder the table was not showing.
 *
 *  Spends nothing: no describe run, no planner call, the Folders screen is
 *  never opened. Each mode uploads one picture of its own (a canvas JPEG),
 *  makes two folders of its own and deletes all three at the end whatever
 *  happened. No picture of the library is touched. Skipped unless asked for,
 *  because it writes into the library while it runs.
 *
 *  The box's list carries a dozen third-party columns beside the tree and
 *  collapses into towers; the walk hides them for its own admin through
 *  core's Screen Options request, as a person would, and puts them back.
 */

const TAX = 'media_category';
const NS = '/vergeml/v1';
const KEEP_COLUMNS = [ 'cb', 'title', 'taxonomy-' + TAX, 'date', 'vergeml_used' ];

const api = ( page, path, data, method ) => page.evaluate(
	( a ) => wp.apiFetch( a.data || a.method ? { path: a.path, method: a.method || 'POST', data: a.data } : { path: a.path } ),
	{ path, data, method }
);

const clearNotices = ( page ) => page.evaluate( () =>
	document.querySelectorAll( '.notice, .updated, .error, #njt-FileBird-review' ).forEach( ( n ) => n.remove() ) );

/** Open the library in one mode and wait until the tree and the files are there. */
async function openMode( page, mode, extra = '' ) {
	await page.goto( `/wp-admin/upload.php?mode=${ mode }${ extra }`, { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '.vgml-tree .vgml-node[data-id="0"]', { timeout: 30000 } );
	await page.waitForSelector( mode === 'grid' ? '.attachments-browser' : '#the-list', { timeout: 30000 } );
	await page.waitForTimeout( 1200 );
	await clearNotices( page );
}

/** The ids of the files on screen, in either mode. */
const shownIds = ( page, mode ) => page.evaluate( ( m ) => m === 'grid'
	? Array.from( document.querySelectorAll( '.attachments-browser .attachment[data-id]' ) ).map( ( t ) => Number( t.getAttribute( 'data-id' ) ) )
	: Array.from( document.querySelectorAll( '#the-list tr[id^="post-"]' ) ).map( ( tr ) => Number( tr.id.replace( 'post-', '' ) ) ), mode );

/** The data-id of the tree row marked as the current one. */
const marked = ( page ) => page.evaluate( () => {
	const n = document.querySelector( '.vgml-tree .vgml-node.is-selected' );
	return n ? Number( n.getAttribute( 'data-id' ) ) : null;
} );

/** A folder's count in the tree; no count drawn means none. */
const treeCount = ( page, id ) => page.evaluate( ( i ) => {
	const c = document.querySelector( `.vgml-tree .vgml-node[data-id="${ i }"] .vgml-count` );
	return c ? Number( ( c.textContent.match( /\d+/ ) || [ 0 ] )[ 0 ] ) : 0;
}, id );

/** How many files are selected, in either mode. */
const selectedCount = ( page, mode ) => page.evaluate( ( m ) => m === 'grid'
	? wp.media.frames.browse.state().get( 'selection' ).length
	: document.querySelectorAll( '#the-list input[name="media[]"]:checked' ).length, mode );

/**
 *  Click a folder in the tree and wait for the files to answer: the grid's
 *  query-attachments request, the list's fetch of upload.php. Read before
 *  that and the previous folder's files are what is read.
 */
const clickFolder = async ( page, mode, id ) => {
	const answered = mode === 'grid'
		? page.waitForResponse( ( r ) => /admin-ajax\.php/.test( r.url() ) && /query-attachments/.test( r.request().postData() || '' ), { timeout: 20000 } ).catch( () => null )
		: page.waitForResponse( ( r ) => /upload\.php/.test( r.url() ), { timeout: 20000 } ).catch( () => null );
	await page.click( `.vgml-tree .vgml-node[data-id="${ id }"] .vgml-row .vgml-name` );
	await answered;
	if ( mode === 'list' ) {
		await page.waitForFunction( () => ! document.querySelector( '.wp-list-table.vgml-busy' ) );
	}
	await page.waitForTimeout( 500 );
};

/** The filter bar's folder dropdown and its kind dropdown, whichever mode drew them. */
const barValues = ( page ) => page.evaluate( ( tax ) => ( {
	folder: ( document.querySelector( `select[name="${ tax }"], #media-attachment-${ tax }-filters` ) || {} ).value,
	kind: ( document.querySelector( 'select[name="attachment-filter"], #media-attachment-filters' ) || {} ).value,
} ), TAX );

/**
 *  A drag the way a hand does it: press on the file, move past the threshold,
 *  move onto the folder, release. With ctrl held on release the drop adds.
 */
async function dragFileTo( page, mode, fileId, folderId, { ctrl = false } = {} ) {
	const from = mode === 'grid'
		? page.locator( `.attachments-browser .attachment[data-id="${ fileId }"]` )
		: page.locator( `#the-list tr#post-${ fileId } th.column-title` );
	const a = await from.boundingBox();
	const b = await page.locator( `.vgml-tree .vgml-node[data-id="${ folderId }"] .vgml-row` ).boundingBox();
	expect( a, 'the file is on screen' ).not.toBeNull();
	expect( b, 'the folder is on screen' ).not.toBeNull();
	const x = a.x + 30;
	const y = a.y + Math.min( 40, a.height / 2 );
	await page.mouse.move( x, y );
	await page.mouse.down();
	await page.mouse.move( x + 30, y, { steps: 5 } );
	await page.mouse.move( b.x + b.width / 2, b.y + b.height / 2, { steps: 12 } );
	await page.mouse.move( b.x + b.width / 2 + 2, b.y + b.height / 2, { steps: 2 } );
	if ( ctrl ) {
		await page.keyboard.down( 'Control' );
	}
	await page.mouse.up();
	if ( ctrl ) {
		await page.keyboard.up( 'Control' );
	}
	await page.waitForTimeout( 600 );
}

/** The last toast, and its Undo pressed. */
async function undo( page ) {
	await expect( page.locator( '.vgml-toast.is-shown .vgml-undo' ) ).toBeVisible( { timeout: 10000 } );
	await page.click( '.vgml-toast.is-shown .vgml-undo' );
	await page.waitForTimeout( 800 );
}

/** Select one file for a bulk action: a tick in the list, Bulk select in the grid. */
async function selectFile( page, mode, id ) {
	if ( mode === 'grid' ) {
		if ( ! ( await page.locator( '.media-frame.mode-select' ).count() ) ) {
			await page.click( '.select-mode-toggle-button' );
			await page.waitForTimeout( 300 );
		}
		await page.click( `.attachments-browser .attachment[data-id="${ id }"]` );
	} else {
		await page.check( `#the-list input[name="media[]"][value="${ id }"]` );
	}
	// M is read from the page, not from a field or a tile.
	await page.click( 'h1.wp-heading-inline' );
}

for ( const mode of [ 'grid', 'list' ] ) {

	test.describe( `${ mode } mode`, () => {

		test.skip( ! process.env.MODES_WALK, 'MODES_WALK=1 to walk both modes on a picture and two folders of their own' );

		test( `every action, in ${ mode }`, async ( { page } ) => {

			test.setTimeout( 420000 );
			await page.setViewportSize( { width: 1500, height: 950 } );

			const problems = [];
			const foreign = [];
			/*
			 *  Ours and core's are failures; another plugin's script throwing in
			 *  the same modal is that plugin's (Elementor's AI button throws on
			 *  any attachment modal whose frame is not its own) and is printed.
			 */
			page.on( 'pageerror', ( e ) => {
				const where = ( ( e.stack || '' ).match( /https?:\/\/\S+\.js/ ) || [ '' ] )[ 0 ];
				if ( where && ! /vergelabs-media-library|wp-includes|wp-admin/.test( where ) ) {
					foreign.push( `${ e.message } @ ${ where }` );
					return;
				}
				problems.push( `javascript: ${ e.message } @ ${ where }` );
			} );
			const assigns = [];
			page.on( 'request', ( r ) => {
				if ( r.method() === 'POST' && /vergeml\/v1\/assign/.test( r.url() ) ) {
					try { assigns.push( JSON.parse( r.postData() || '{}' ) ); } catch { /* not json */ }
				}
			} );

			await openMode( page, mode );

			/* ---------------------------------------------------- fixtures */
			const title = `P7 modes walk ${ mode }`;
			const made = await page.evaluate( async ( t ) => {
				const c = document.createElement( 'canvas' );
				c.width = 640;
				c.height = 420;
				const g = c.getContext( '2d' );
				g.fillStyle = '#d9e4f5';
				g.fillRect( 0, 0, 640, 420 );
				g.fillStyle = '#2e5aa8';
				g.fillRect( 80, 60, 480, 300 );
				const blob = await new Promise( ( r ) => c.toBlob( r, 'image/jpeg', 0.85 ) );
				const body = new FormData();
				body.append( 'file', blob, `vgml-p7-modes-${ t.split( ' ' ).pop() }.jpg` );
				body.append( 'title', t );
				const r = await wp.apiFetch( { path: '/wp/v2/media', method: 'POST', body } );
				return { id: r.id };
			}, title );
			const fileId = made.id;

			const names = { A: `P7 modes A ${ mode }`, B: `P7 modes B ${ mode }` };
			const folders = {};
			for ( const k of [ 'A', 'B' ] ) {
				const tree = await api( page, `${ NS }/tree?taxonomy=${ TAX }` );
				for ( const n of tree.nodes || [] ) {
					if ( n.name === names[ k ] ) {
						await api( page, `${ NS }/folder`, { taxonomy: TAX, action: 'delete', id: n.id } );
					}
				}
				const r = await api( page, `${ NS }/folder`, { taxonomy: TAX, action: 'create', name: names[ k ] } );
				folders[ k ] = Number( r.id );
			}
			const tree = await api( page, `${ NS }/tree?taxonomy=${ TAX }` );
			const slug = { A: tree.nodes.find( ( n ) => n.id === folders.A ).slug, B: tree.nodes.find( ( n ) => n.id === folders.B ).slug };
			await api( page, `${ NS }/assign`, { taxonomy: TAX, attachments: [ fileId ], add: [ folders.A ], mode: 'move' } );

			let hiddenBefore = null;
			const hideColumns = async ( hidden ) => page.evaluate( ( h ) => new Promise( ( r ) =>
				jQuery.post( window.ajaxurl, { action: 'hidden-columns', hidden: h.join( ',' ), screenoptionnonce: document.getElementById( 'screenoptionnonce' ).value, page: 'upload' }, r ) ), hidden );

			try {
				if ( mode === 'list' ) {
					await openMode( page, 'list' );
					const columns = await page.$$eval( '.wp-list-table thead th[id]', ( ths ) => ths.map( ( th ) => ( { id: th.id, hidden: th.classList.contains( 'hidden' ) } ) ) );
					hiddenBefore = columns.filter( ( c ) => c.hidden ).map( ( c ) => c.id );
					await hideColumns( columns.map( ( c ) => c.id ).filter( ( id ) => ! KEEP_COLUMNS.includes( id ) ) );
				}

				/* ------------------------------------------ 10: arrival */
				await test.step( 'arrival: the remembered folder is what the files show', async () => {
					await api( page, `${ NS }/state`, { taxonomy: TAX, selected: folders.A } );
					await openMode( page, mode );
					await expect.poll( () => shownIds( page, mode ), { timeout: 20000, message: 'the files are the remembered folder\'s' } ).toEqual( [ fileId ] );
					expect( await marked( page ), 'the tree marks the remembered folder' ).toBe( folders.A );
					if ( mode === 'list' ) {
						expect( page.url(), 'the list URL names the folder' ).toContain( `${ TAX }=${ slug.A }` );
					}
				} );

				if ( mode === 'list' ) {
					await test.step( 'arrival by URL: the tree follows the folder the URL names', async () => {
						await openMode( page, 'list', `&${ TAX }=${ slug.B }` );
						expect( await marked( page ), 'the tree marks the folder in the URL' ).toBe( folders.B );
						expect( await shownIds( page, 'list' ), 'the empty folder shows nothing' ).toEqual( [] );
						expect( ( await barValues( page ) ).folder, 'the dropdown reads that folder' ).toBe( String( folders.B ) );
						await openMode( page, 'list' );
					} );
				}

				/* ---------------------------- 8, 9, 11: filter, bar, crumbs */
				await test.step( 'folder, Unfiled: the files, the dropdowns and the crumbs agree with the tree', async () => {
					await clickFolder( page, mode, folders.A );
					await expect.poll( () => shownIds( page, mode ), { timeout: 15000 } ).toEqual( [ fileId ] );
					await expect( page.locator( '.vgml-crumbs' ) ).toContainText( names.A );
					expect( ( await barValues( page ) ).folder, 'the folder dropdown reads the folder' ).toBe( String( folders.A ) );

					await clickFolder( page, mode, -1 );
					await expect.poll( () => shownIds( page, mode ).then( ( ids ) => ids.length ), { timeout: 20000 } ).toBeGreaterThan( 0 );
					const unfiled = await treeCount( page, -1 );
					expect( unfiled, 'the tree counts the unfiled' ).toBeGreaterThan( 0 );
					const ids = ( await shownIds( page, mode ) ).slice( 0, 5 );
					for ( const id of ids ) {
						const m = await api( page, `/wp/v2/media/${ id }?_fields=id,${ TAX }` );
						expect( m[ TAX ] || [], `file ${ id } shown under Unfiled has no folder` ).toEqual( [] );
					}
					if ( mode === 'list' ) {
						const items = await page.locator( '.tablenav.top .displaying-num' ).innerText();
						expect( Number( items.replace( /\D/g, '' ) ), '"N items" is the tree\'s Unfiled count' ).toBe( unfiled );
					}
					expect( ( await barValues( page ) ).kind, 'the kind dropdown reads All Uncategorized' ).toBe( 'uncategorized' );
					expect( await marked( page ) ).toBe( -1 );

					await clickFolder( page, mode, folders.A );
					await expect.poll( () => shownIds( page, mode ), { timeout: 15000 } ).toEqual( [ fileId ] );
					const bar = await barValues( page );
					expect( bar.folder ).toBe( String( folders.A ) );
					expect( [ 'all', '' ], 'the kind dropdown is back on everything' ).toContain( bar.kind );
				} );

				/* ---------------------------------------------- 15: search */
				await test.step( 'search keeps the folder', async () => {
					await page.fill( '#media-search-input', title );
					if ( mode === 'list' ) {
						await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.keyboard.press( 'Enter' ) ] );
						await page.waitForSelector( '#the-list' );
						await page.waitForTimeout( 1200 );
						await clearNotices( page );
						expect( page.url(), 'the search URL carries the folder' ).toMatch( new RegExp( `${ TAX }=(${ folders.A }|${ slug.A })` ) );
						expect( page.url() ).toContain( 's=' );
					}
					await expect.poll( () => shownIds( page, mode ), { timeout: 15000, message: 'the search within the folder finds the picture' } ).toEqual( [ fileId ] );
					expect( await marked( page ), 'the tree still marks the folder' ).toBe( folders.A );
					if ( mode === 'grid' ) {
						const props = await page.evaluate( ( tax ) => { const p = wp.media.frames.browse.state().get( 'library' ).props; return [ p.get( tax ), p.get( 'search' ) ]; }, TAX );
						expect( props[ 0 ] ).toBe( folders.A );
						expect( props[ 1 ] ).toBe( title );
						await page.fill( '#media-search-input', '' );
						await expect.poll( () => shownIds( page, mode ), { timeout: 15000 } ).toEqual( [ fileId ] );
					}
				} );

				/* --------------------------------------------- 17: order */
				if ( mode === 'list' ) {
					await test.step( 'sorting by a column keeps the folder', async () => {
						await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( 'thead th#title a' ) ] );
						await page.waitForSelector( '#the-list' );
						await page.waitForTimeout( 1200 );
						await clearNotices( page );
						expect( page.url() ).toContain( 'orderby=title' );
						expect( page.url() ).toMatch( new RegExp( `${ TAX }=(${ folders.A }|${ slug.A })` ) );
						expect( await marked( page ) ).toBe( folders.A );
						expect( await shownIds( page, 'list' ) ).toEqual( [ fileId ] );
					} );
					await openMode( page, 'list' );
				}

				/* ----------------------------------------- 1, 20: open */
				await test.step( 'open a file: the same modal, with arrows that walk the files; Escape closes it', async () => {
					await clickFolder( page, mode, 0 );
					await expect.poll( () => shownIds( page, mode ).then( ( ids ) => ids.length ), { timeout: 20000 } ).toBeGreaterThan( 1 );
					const ids = await shownIds( page, mode );
					if ( mode === 'grid' ) {
						await page.hover( `.attachments-browser .attachment[data-id="${ ids[ 0 ] }"]` );
						await page.click( `.attachments-browser .attachment[data-id="${ ids[ 0 ] }"] .edit`, { force: true } );
					} else {
						await page.click( `#the-list tr#post-${ ids[ 0 ] } .column-title strong > a` );
					}
					const frame = page.locator( '.edit-attachment-frame' );
					await expect( frame ).toBeVisible( { timeout: 20000 } );
					const first = await frame.locator( '.attachment-details .filename' ).innerText();
					const next = frame.locator( '.edit-media-header .right' );
					await expect( next, 'the next arrow is there and live' ).toBeEnabled();
					await next.click();
					await expect.poll( () => frame.locator( '.attachment-details .filename' ).innerText(), { timeout: 15000, message: 'the arrow shows the next file' } ).not.toBe( first );
					await page.keyboard.press( 'Escape' );
					await expect( frame ).toBeHidden( { timeout: 10000 } );
				} );

				/* ---------------------------------------- 2, 21: select */
				await test.step( 'select two files, and a third from the keyboard', async () => {
					const ids = await shownIds( page, mode );
					if ( mode === 'grid' ) {
						await page.click( '.select-mode-toggle-button' );
						await page.click( `.attachments-browser .attachment[data-id="${ ids[ 0 ] }"]` );
						await page.click( `.attachments-browser .attachment[data-id="${ ids[ 1 ] }"]` );
						expect( await selectedCount( page, 'grid' ) ).toBe( 2 );
						await page.focus( `.attachments-browser .attachment[data-id="${ ids[ 2 ] }"]` );
						await page.keyboard.press( 'Enter' );
						expect( await selectedCount( page, 'grid' ), 'Enter selects the focused tile' ).toBe( 3 );
						await page.keyboard.press( 'Escape' );
						await expect( page.locator( '.media-frame.mode-select' ), 'Escape leaves Bulk select' ).toHaveCount( 0 );
						await page.evaluate( () => wp.media.frames.browse.state().get( 'selection' ).reset() );
					} else {
						await page.check( `#the-list input[name="media[]"][value="${ ids[ 0 ] }"]` );
						await page.check( `#the-list input[name="media[]"][value="${ ids[ 1 ] }"]` );
						expect( await selectedCount( page, 'list' ) ).toBe( 2 );
						await page.focus( `#the-list input[name="media[]"][value="${ ids[ 2 ] }"]` );
						await page.keyboard.press( 'Space' );
						expect( await selectedCount( page, 'list' ), 'Space ticks the focused box' ).toBe( 3 );
						await page.evaluate( () => document.querySelectorAll( '#the-list input[name="media[]"]:checked' ).forEach( ( c ) => { c.checked = false; } ) );
					}
				} );

				/* ------------------------- 3, 4, 6, 13, 19: M, the dialog, undo */
				await test.step( 'M moves the selection; the file leaves the view; counts, selection and Undo follow', async () => {
					await clickFolder( page, mode, folders.A );
					await expect.poll( () => shownIds( page, mode ), { timeout: 15000 } ).toEqual( [ fileId ] );
					await selectFile( page, mode, fileId );
					expect( await selectedCount( page, mode ) ).toBe( 1 );

					await page.keyboard.press( 'm' );
					await expect( page.locator( '.vgml-move' ) ).toBeVisible();
					await page.keyboard.press( 'Escape' );
					await expect( page.locator( '.vgml-move' ), 'Escape closes the dialog' ).toHaveCount( 0 );

					await page.click( 'h1.wp-heading-inline' );
					await page.keyboard.press( 'm' );
					await expect( page.locator( '.vgml-move' ) ).toBeVisible();
					await expect( page.locator( '.vgml-move-head' ) ).toHaveText( '1 selected — choose a folder' );
					await page.fill( '.vgml-move input[type="search"]', names.B );
					await expect( page.locator( '.vgml-move-list li' ) ).toHaveCount( 1 );
					const before = assigns.length;
					await page.keyboard.press( 'Enter' );
					await expect.poll( () => assigns.length, { timeout: 10000 } ).toBe( before + 1 );
					expect( assigns[ before ] ).toMatchObject( { mode: 'move', add: [ folders.B ], attachments: [ fileId ] } );
					await expect( page.locator( '.vgml-toast.is-shown' ) ).toContainText( '1 file filed' );

					await expect.poll( () => shownIds( page, mode ), { timeout: 20000, message: 'the moved file leaves the folder\'s view' } ).toEqual( [] );
					await expect.poll( () => treeCount( page, folders.B ), { timeout: 20000 } ).toBe( 1 );
					expect( await treeCount( page, folders.A ) ).toBe( 0 );
					expect( await selectedCount( page, mode ), 'nothing is selected once the file has left the view' ).toBe( 0 );

					await undo( page );
					await expect.poll( () => shownIds( page, mode ), { timeout: 20000, message: 'Undo brings the file back' } ).toEqual( [ fileId ] );
					await expect.poll( () => treeCount( page, folders.A ), { timeout: 20000 } ).toBe( 1 );
					expect( await treeCount( page, folders.B ) ).toBe( 0 );
					if ( mode === 'grid' ) {
						await page.keyboard.press( 'Escape' );
					}
				} );

				/* ---------------------------------------- 6, 7: dragging */
				await test.step( 'drag to a folder moves; Ctrl-drag adds; drag to Unfiled unfiles; each undone', async () => {
					await clickFolder( page, mode, folders.A );
					await expect.poll( () => shownIds( page, mode ), { timeout: 15000 } ).toEqual( [ fileId ] );

					let before = assigns.length;
					await dragFileTo( page, mode, fileId, folders.B );
					await expect.poll( () => assigns.length, { timeout: 10000, message: 'the drop filed the picture' } ).toBe( before + 1 );
					expect( assigns[ before ] ).toMatchObject( { mode: 'move', add: [ folders.B ] } );
					await expect.poll( () => shownIds( page, mode ), { timeout: 20000, message: 'the dragged file leaves the folder\'s view' } ).toEqual( [] );
					await expect.poll( () => treeCount( page, folders.B ), { timeout: 20000 } ).toBe( 1 );
					await undo( page );
					await expect.poll( () => shownIds( page, mode ), { timeout: 20000 } ).toEqual( [ fileId ] );

					before = assigns.length;
					await dragFileTo( page, mode, fileId, folders.B, { ctrl: true } );
					await expect.poll( () => assigns.length, { timeout: 10000 } ).toBe( before + 1 );
					expect( assigns[ before ], 'Ctrl-drag adds to a second folder' ).toMatchObject( { mode: 'add', add: [ folders.B ] } );
					await expect.poll( () => treeCount( page, folders.B ), { timeout: 20000 } ).toBe( 1 );
					expect( await treeCount( page, folders.A ), 'the file stays in the first folder' ).toBe( 1 );
					expect( await shownIds( page, mode ), 'and stays on screen' ).toEqual( [ fileId ] );
					await undo( page );
					await expect.poll( () => treeCount( page, folders.B ), { timeout: 20000 } ).toBe( 0 );

					before = assigns.length;
					const unfiledBefore = await treeCount( page, -1 );
					await dragFileTo( page, mode, fileId, -1 );
					await expect.poll( () => assigns.length, { timeout: 10000 } ).toBe( before + 1 );
					expect( assigns[ before ], 'a drop on Unfiled empties the folders' ).toMatchObject( { mode: 'move', add: [] } );
					await expect.poll( () => shownIds( page, mode ), { timeout: 20000 } ).toEqual( [] );
					await expect.poll( () => treeCount( page, -1 ), { timeout: 20000 } ).toBe( unfiledBefore + 1 );
					await undo( page );
					await expect.poll( () => shownIds( page, mode ), { timeout: 20000 } ).toEqual( [ fileId ] );
					await expect.poll( () => treeCount( page, -1 ), { timeout: 20000 } ).toBe( unfiledBefore );
				} );

				/* ------------------------------------- 18: the tree's keys */
				await test.step( 'the tree from the keyboard: arrows move, Enter filters', async () => {
					await clickFolder( page, mode, 0 );
					await expect.poll( () => shownIds( page, mode ).then( ( ids ) => ids.length ), { timeout: 20000 } ).toBeGreaterThan( 1 );
					await page.focus( `.vgml-tree .vgml-node[data-id="${ folders.A }"]` );
					await page.keyboard.press( 'ArrowDown' );
					const after = await page.evaluate( () => document.activeElement.getAttribute( 'data-id' ) );
					expect( Number( after ), 'ArrowDown moves to the next row' ).not.toBe( folders.A );
					await page.keyboard.press( 'ArrowUp' );
					expect( await page.evaluate( () => Number( document.activeElement.getAttribute( 'data-id' ) ) ) ).toBe( folders.A );
					await page.keyboard.press( 'Enter' );
					await expect.poll( () => shownIds( page, mode ), { timeout: 15000, message: 'Enter shows the folder' } ).toEqual( [ fileId ] );
					expect( await marked( page ) ).toBe( folders.A );
				} );

				await page.screenshot( { path: `tests/ui/shots/modes-${ mode }.png`, fullPage: false } );
				if ( foreign.length ) {
					console.log( "  other plugins' scripts threw " + foreign.length + " time(s) in " + mode + ":\n    " + [ ...new Set( foreign ) ].join( "\n    " ) );
				}
				expect( problems, problems.join( '\n' ) ).toEqual( [] );

			} finally {
				// The spec restores what it wrote: its picture, its folders, its admin's columns and selection.
				await page.evaluate( ( id ) => wp.apiFetch( { path: `/wp/v2/media/${ id }?force=true`, method: 'DELETE' } ).catch( () => null ), fileId );
				for ( const k of [ 'A', 'B' ] ) {
					await api( page, `${ NS }/folder`, { taxonomy: TAX, action: 'delete', id: folders[ k ] } ).catch( () => null );
				}
				await api( page, `${ NS }/state`, { taxonomy: TAX, selected: 0 } ).catch( () => null );
				if ( hiddenBefore !== null ) {
					await hideColumns( hiddenBefore ).catch( () => null );
				}
			}
		} );
	} );
}
