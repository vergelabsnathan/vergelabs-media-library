import { test, expect } from './fixtures.mjs';

/*
 *  The media library, walked as one person doing everything on it.
 *
 *      MODES_WALK=1 pnpm test:ui modes.spec
 *
 *  The table this follows is docs/superpowers/specs/2026-09-06-grid-list-modes.md:
 *  open, select, bulk move, drag, folder filter, counts, search and the
 *  keyboard. Every row of it found two surfaces that disagreed on 2026-09-06
 *  -- Unfiled showing every file in the list, a moved row that stayed, a
 *  search that dropped the folder, a tree highlighting a folder the table was
 *  not showing.
 *
 *  The walk itself is grid's since 3.14; list mode has no tree to walk. What
 *  it has instead -- the folder filter and Move to folder -- has its own two
 *  tests at the foot of this file, and the rows have a geometry gate.
 *
 *  Spends nothing: no describe run, no planner call, the Folders screen is
 *  never opened. It uploads one picture of its own (a canvas JPEG), makes two
 *  folders of its own, and deletes all three at the end whatever happened. No
 *  picture of the library is touched. Skipped unless asked for, because it
 *  writes into the library while it runs.
 */

const TAX = 'media_category';
const NS = '/vergeml/v1';

const api = ( page, path, data, method ) => page.evaluate(
	( a ) => wp.apiFetch( a.data || a.method ? { path: a.path, method: a.method || 'POST', data: a.data } : { path: a.path } ),
	{ path, data, method }
);

const clearNotices = ( page ) => page.evaluate( () =>
	document.querySelectorAll( '.notice, .updated, .error, #njt-FileBird-review' ).forEach( ( n ) => n.remove() ) );

/**
 *  The panel remembers being folded, and folded it is a 44px rail with one
 *  control in it and no tree. A walk that needs the tree opens it first.
 */
async function unfold( page ) {
	if ( await page.locator( '.vgml-tree.is-collapsed' ).count() ) {
		await page.click( '.vgml-fold' );
		await page.waitForTimeout( 800 );
	}
}

/**
 *  Open the library in one mode and wait until the files are there.
 *
 *  The tree is waited for in grid mode only. Since 3.14 there is none in list
 *  mode: it took 316px of a table that had 763px for thirteen columns and
 *  every row was 1,960px tall. The folders are a dropdown in WordPress's own
 *  filter bar there instead -- docs/superpowers/specs/2026-09-07-list-view.md.
 */
async function openMode( page, mode, extra = '' ) {
	await page.goto( `/wp-admin/upload.php?mode=${ mode }${ extra }`, { waitUntil: 'domcontentloaded' } );
	if ( mode === 'grid' ) {
		await page.waitForSelector( '.vgml-tree', { timeout: 30000 } );
		await unfold( page );
		await page.waitForSelector( '.vgml-tree .vgml-node[data-id="0"]', { timeout: 30000 } );
	}
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


test.describe( 'grid mode', () => {

	/*
	 *  Grid's, and only grid's, since 3.14. It clicks folders in the tree,
	 *  drags rows onto it and opens the dialog its M key owns, and list mode
	 *  has none of those any more -- a tree taking 316px from a table left
	 *  every row 1,960px tall, so the folder filter is a dropdown in core's
	 *  own bar there and files move by a bulk action. What list mode does
	 *  instead is walked by the two tests below, which run on every push.
	 *  docs/superpowers/specs/2026-09-07-list-view.md.
	 */
	const mode = 'grid';

	test.skip( ! process.env.MODES_WALK, 'MODES_WALK=1 to walk the grid on a picture and two folders of its own' );


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
		await api( page, `${ NS }/assign`, { taxonomy: TAX, attachments: [ fileId ], add: [ folders.A ], mode: 'move' } );

		try {
			/* ------------------------------------------ 10: arrival */
			await test.step( 'arrival: the remembered folder is what the files show', async () => {
				await api( page, `${ NS }/state`, { taxonomy: TAX, selected: folders.A } );
				await openMode( page, mode );
				await expect.poll( () => shownIds( page, mode ), { timeout: 20000, message: 'the files are the remembered folder\'s' } ).toEqual( [ fileId ] );
				expect( await marked( page ), 'the tree marks the remembered folder' ).toBe( folders.A );
			} );

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
				await expect.poll( () => shownIds( page, mode ), { timeout: 15000, message: 'the search within the folder finds the picture' } ).toEqual( [ fileId ] );
				expect( await marked( page ), 'the tree still marks the folder' ).toBe( folders.A );
				const props = await page.evaluate( ( tax ) => { const p = wp.media.frames.browse.state().get( 'library' ).props; return [ p.get( tax ), p.get( 'search' ) ]; }, TAX );
				expect( props[ 0 ] ).toBe( folders.A );
				expect( props[ 1 ] ).toBe( title );
				await page.fill( '#media-search-input', '' );
				await expect.poll( () => shownIds( page, mode ), { timeout: 15000 } ).toEqual( [ fileId ] );

			} );

			/* ----------------------------------------- 1, 20: open */
			await test.step( 'open a file: the same modal, with arrows that walk the files; Escape closes it', async () => {
				await clickFolder( page, mode, 0 );
				await expect.poll( () => shownIds( page, mode ).then( ( ids ) => ids.length ), { timeout: 20000 } ).toBeGreaterThan( 1 );
				const ids = await shownIds( page, mode );
				await page.hover( `.attachments-browser .attachment[data-id="${ ids[ 0 ] }"]` );
				await page.click( `.attachments-browser .attachment[data-id="${ ids[ 0 ] }"] .edit`, { force: true } );

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
				await page.keyboard.press( 'Escape' );

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
			// The spec restores what it wrote: its picture, its folders and its selection.
			await page.evaluate( ( id ) => wp.apiFetch( { path: `/wp/v2/media/${ id }?force=true`, method: 'DELETE' } ).catch( () => null ), fileId );
			for ( const k of [ 'A', 'B' ] ) {
				await api( page, `${ NS }/folder`, { taxonomy: TAX, action: 'delete', id: folders[ k ] } ).catch( () => null );
			}
			await api( page, `${ NS }/state`, { taxonomy: TAX, selected: 0 } ).catch( () => null );
		}
	} );
} );


/*
 *  How tall a row is.
 *
 *  Everything above asserts what is on the screen and never a geometry, so
 *  the suite walked this screen and passed all through the days a row was
 *  1,960px tall and a screenshot of the media list was 28,800px long. The
 *  cause is width: a row is as tall as the tallest thing in it that is still
 *  in the flow, and core's own .row-actions -- hidden with position:
 *  relative, so still in the flow -- wraps to 1,153px in a 42px column and
 *  sets the height of every row on the page.
 *
 *  Two numbers, because this screen has two owners.
 *
 *  A hundred pixels is what a row costs when the only columns on it are
 *  WordPress's and ours -- which is what a person gets on a normal library,
 *  and it is the whole of what this plugin is answerable for. The suite hides
 *  the other plugins' columns first, through core's own Screen Options
 *  request, exactly as a person would.
 *
 *  Three hundred is not a standard. It is the alarm on the screen as it
 *  ships: ours off, as they are by default, and every other plugin's column
 *  on -- which on the box is eight of them, with FileBird Pro's pane beside
 *  the table. 231px measured on 2026-09-07. A third party adding one more
 *  column will not trip it, and the tree coming back as a permanent 316px
 *  gutter -- which is what made a row 1,960px -- will.
 *
 *  Turning our four on AND eight of somebody else's is a thirteen-column
 *  table and nobody's default. Screen Options is WordPress's answer to that,
 *  and ours are the four it starts with switched off.
 *
 *  Both modes, because the mode is remembered per person: somebody whose last
 *  visit was the grid opens the list with the grid's state behind them. Both
 *  column sets under the first number, because our four are off by default and
 *  a person who turns all four on has to get a readable row as well.
 *
 *  Reads the screen and puts the column preference back. Spends nothing.
 */

/** Ours, and what a row may cost when only ours and WordPress's are on. */
const OURS = [ `taxonomy-${ TAX }`, 'taxonomy-colour', 'taxonomy-audience', 'vergeml_used', 'vgmlpro_source' ];
const ROW_CEILING = 100;

/*
 *  Everything WordPress itself puts on the media list. `parent` is in it
 *  because core still offers it on a site where smart folders have not
 *  replaced it with "Used on".
 */
const CORE_COLUMNS = [ 'cb', 'title', 'author', 'date', 'parent', 'comments' ];
const ROW_ALARM = 300;

/** Core's own Screen Options request, as a person's tick would send it. */
const setHidden = ( page, hidden ) => page.evaluate( ( h ) => new Promise( ( r ) =>
	jQuery.post( window.ajaxurl, {
		action: 'hidden-columns',
		hidden: h.join( ',' ),
		screenoptionnonce: document.getElementById( 'screenoptionnonce' ).value,
		page: 'upload',
	}, r ) ), hidden );

/**
 *  Every row on the list with its height, tallest first -- and the picture
 *  each row is, which is the half that was missing.
 *
 *  The row carries its title because this measures whatever WordPress puts on
 *  page one, and page one is the twenty newest attachments by date. So a
 *  suite that stops before its teardown and leaves fixtures in the library
 *  puts its own pictures at the top of what this measures, and the alarm then
 *  reports a screen nobody ships. That happened: `ROW_ALARM` read 337px on
 *  2026-09-08 and passed an hour later on the same build, and eight `zz`
 *  fixture pictures dated 2026-09-08 09:00 were sitting among the real ones
 *  in between. The newest real picture on the box is dated 2026-09-01.
 *
 *  There is no point asking which cell is tall: they are table cells, so
 *  every one of them is exactly the row's height. The row's identity is what
 *  tells you whether you are looking at the library or at debris.
 */
const rowHeights = ( page ) => page.evaluate( () => Array.from( document.querySelectorAll( '#the-list > tr' ) )
	.map( ( r ) => ( {
		id: r.id,
		h: Math.round( r.getBoundingClientRect().height ),
		title: ( ( r.querySelector( '.column-title a, .column-title strong' ) || {} ).textContent || '' ).trim().slice( 0, 40 ),
	} ) )
	.sort( ( a, b ) => b.h - a.h ) );

/**
 *  The table's widths and File's share as the body class says it (story
 *  4.4: File is the leftover's column, behind a share class PHP chose at
 *  load; story 4.5: the script decides which share from the head cells and
 *  puts the floor on only when File would be narrower without it, so a
 *  Screen Options tick is followed). And where a press 40px into the first
 *  row lands, which is what the drag suites make: on the checkbox label it
 *  is another plugin's drag.
 */
const readWidths = ( page ) => page.evaluate( () => {
	const table = document.querySelector( '.wp-list-table' );
	const row = document.querySelector( '#the-list > tr[id^="post-"]' );
	if ( ! table || ! row ) {
		return { checkbox: 0, table: 0, file: 0, share: 0, floor: false, pressOnTitle: false, empty: true };
	}
	const a = row.getBoundingClientRect();
	const pressed = document.elementFromPoint( a.x + 40, a.y + a.height / 2 );
	const share = document.body.classList.contains( 'vgml-file-share-30' ) ? 30 : document.body.classList.contains( 'vgml-file-share-40' ) ? 40 : 0;
	return {
		checkbox: Math.round( table.querySelector( 'thead .check-column' ).getBoundingClientRect().width ),
		table: Math.round( table.getBoundingClientRect().width ),
		file: Math.round( table.querySelector( 'thead .column-title' ).getBoundingClientRect().width ),
		share,
		floor: share > 0,
		pressOnTitle: !! ( pressed && pressed.closest( '.column-title' ) ),
	};
} );

/**
 *  Every column checkbox in the open Screen Options panel, and what a press
 *  at its centre would land on. Until 4.0.3 the panel opened under the
 *  list-mode tree (z-index 2 against the fixed panel's 3): on a 4.0.2 site
 *  three of the four checkboxes answered with a folder row, and this suite
 *  was green because the columns it ticks sit right of the tree. Each box is
 *  scrolled to the viewport's centre first -- at the top edge it is under
 *  the fixed admin bar, and off-screen elementFromPoint answers null; both
 *  would fail for the wrong reason. `hit` names whatever was there instead,
 *  so another plugin's pane reads as that plugin's.
 */
const checkboxesReachable = ( page ) => page.evaluate( () => Array.from( document.querySelectorAll( '#adv-settings .hide-column-tog' ) ).map( ( box ) => {
	box.scrollIntoView( { block: 'center' } );
	const r = box.getBoundingClientRect();
	const at = document.elementFromPoint( r.x + r.width / 2, r.y + r.height / 2 );
	return {
		id: box.id,
		ok: at === box,
		hit: at ? at.tagName.toLowerCase() + ( at.id ? '#' + at.id : '' ) + ( at.className && 'string' === typeof at.className ? '.' + at.className.trim().split( /\s+/ ).join( '.' ) : '' ) : 'nothing (off screen)',
	};
} ) );

const openList = async ( page ) => {
	await page.goto( '/wp-admin/upload.php?mode=list', { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '#the-list tr[id^="post-"]', { timeout: 30000 } );
	await page.waitForTimeout( 1500 );
	await clearNotices( page );
};

/** Core's filter choices wait behind the Filter chip (S10.6); a test that picks one opens the card first. */
const openFilters = async ( page ) => {
	const chip = page.locator( '.vgml-listbar .vgml-filter-chip' );
	if ( await chip.count() && ! await page.locator( '.vgml-filter-card select.vgml-folder-filter' ).isVisible() ) {
		await chip.click();
	}
};

for ( const mode of [ 'grid', 'list' ] ) {

	test( `the rows on the media list, arriving in ${ mode }`, async ( { page } ) => {

		test.setTimeout( 240000 );
		await page.setViewportSize( { width: 1600, height: 900 } );

		// The mode under test first: it is remembered, so this is the state
		// the list is opened from.
		await page.goto( `/wp-admin/upload.php?mode=${ mode }`, { waitUntil: 'domcontentloaded' } );
		await page.waitForTimeout( 2500 );

		await openList( page );

		/*
		 *  The folder panel is on this screen -- Nathan's ruling of 2026-09-10,
		 *  js/vergeml-tree.js isMediaList() -- and the table answers the width
		 *  it loses by scrolling sideways in its own box, never the page. On
		 *  2026-09-14 File's nowrap lines held the column at 759px and the page
		 *  scrolled 193px at 1440: three columns off the right edge.
		 */
		const room = await page.evaluate( () => ( {
			table: Math.round( document.querySelector( '.wp-list-table' ).getBoundingClientRect().width ),
			body: Math.round( document.querySelector( '#wpbody-content' ).getBoundingClientRect().width ),
			trees: document.querySelectorAll( '.vgml-tree' ).length,
			page: document.documentElement.scrollWidth,
			window: window.innerWidth,
			file: Math.round( document.querySelector( '.wp-list-table th#title' ).getBoundingClientRect().width ),
			checkbox: Math.round( document.querySelector( '.wp-list-table thead .check-column' ).getBoundingClientRect().width ),
		} ) );

		expect( room.trees, 'the folder panel is on the list screen too' ).toBe( 1 );
		expect(
			room.page,
			`the page does not scroll sideways (page ${ room.page }px, window ${ room.window }px)`
		).toBeLessThanOrEqual( room.window );
		/*
		 *  File is a share of the table, never its nowrap width (759px on
		 *  2026-09-14, three columns off the right edge -- the sideways scroll
		 *  above is that failure's own mark). Since story 4.4 File is the
		 *  column that takes the table's leftover, so it can be wider than
		 *  half; what it cannot be is the whole table less the checkbox.
		 */
		expect( room.file, `File is a share of the table, not its own width (${ room.file }px of ${ room.table }px)` ).toBeLessThan( room.table - room.checkbox - 200 );
		expect( room.checkbox, `the checkbox column is ${ room.checkbox }px wide on arrival` ).toBeLessThan( 60 );

		const columns = await page.$$eval( '.wp-list-table thead th[id]', ( ths ) =>
			ths.map( ( th ) => ( { id: th.id, hidden: th.classList.contains( 'hidden' ) } ) ) );
		const every = columns.map( ( c ) => c.id );
		const before = columns.filter( ( c ) => c.hidden ).map( ( c ) => c.id );
		const ours = every.filter( ( id ) => OURS.includes( id ) );
		const theirs = every.filter( ( id ) => ! OURS.includes( id ) && ! CORE_COLUMNS.includes( id ) );

		expect( ours.length, 'our columns are on this screen to switch on' ).toBeGreaterThan( 0 );

		try {
			/*
			 *  The fourth set is the one that showed the checkbox swallowing
			 *  the table's slack (story 4.4, 2026-09-21): with two of ours on
			 *  and nothing else, every column had a width that summed to 82%,
			 *  and Chrome's fixed layout gave the other 18% to the only
			 *  fixed-length column -- the checkbox, 137px of 982 -- whose
			 *  label core stretches over the whole cell. Beside FileBird that
			 *  label is FileBird's drag handle, and a single row dragged into
			 *  a folder filed nothing. Five of ours (set two) sum past 100%
			 *  and never showed it.
			 */
			/*
			 *  `share` is the class the set must leave on the body -- 0 for
			 *  none: none where File takes the leftover on its own (core's
			 *  three; two of ours: 82% sized, File auto ≈ 55%), 40 where the
			 *  sized columns leave File below its share among ours only (five
			 *  of ours: 69%, File auto ≈ 28% < 40%). With every other plugin's
			 *  column on it depends on how they size theirs, so that set
			 *  asserts only the consequence: File at or above its share when
			 *  a class is on. Two of ours (no Pro column, no colour or audience
			 *  terms: Playground, a free site) leave File at ≈ 51%, above the
			 *  share, so the class is rightly off there and set two expects
			 *  none; three or four of ours is a geometry nobody measured, so
			 *  there set two asserts only the consequence, like set three
			 *  (2026-09-21; before that the 40 was the box's geometry and the
			 *  test could not run anywhere else).
			 */
			const sets = [
				[ 'ours off, and the other plugins\' columns off', ROW_CEILING, theirs.concat( ours ), 0 ],
				[ `all ${ ours.length } of ours on, the other plugins' columns off`, ROW_CEILING, theirs, 5 === ours.length ? 40 : ( ours.length <= 2 ? 0 : null ) ],
				[ 'as the screen ships, with every other plugin\'s column on', ROW_ALARM, ours, null ],
				[ 'two of ours on, the other plugins\' columns off', ROW_CEILING, theirs.concat( ours.slice( 2 ) ), 0 ],
			];

			for ( const [ what, ceiling, hidden, share ] of sets ) {

				await setHidden( page, Array.from( new Set( hidden ) ) );
				await openList( page );

				const on = await page.$$eval( '.wp-list-table thead th[id]', ( ths ) =>
					ths.filter( ( th ) => ! th.classList.contains( 'hidden' ) ).map( ( th ) => th.id ) );
				const rows = await rowHeights( page );
				/*
				 *  The head cell sets the column in a fixed table; core sizes it
				 *  2.5em. And the press the drag suites make -- 40px into the
				 *  first row, where the thumbnail is -- must land on the title
				 *  cell: on the checkbox label it is another plugin's drag.
				 */
				const width = await readWidths( page );
				const checkbox = width.checkbox;

				expect( rows.length, 'the list has rows to measure' ).toBeGreaterThan( 0 );
				expect( width.pressOnTitle, `${ what }, arriving in ${ mode }: a press 40px into the first row lands on the title cell, not the checkbox label` ).toBe( true );
				if ( null !== share ) {
					expect( width.share, `${ what }, arriving in ${ mode }: the body carries ${ width.share ? `vgml-file-share-${ width.share }` : 'no share class' }, expected ${ share ? `vgml-file-share-${ share }` : 'none' } (File ${ width.file }px of ${ width.table }px)` ).toBe( share );
				}
				if ( width.floor ) {
					// Within two points: percentages that sum past 100% are scaled by the browser (five of ours: 109%).
					expect( width.file, `${ what }, arriving in ${ mode }: with the class on, File holds its ${ width.share }% share (${ width.file }px of ${ width.table }px)` ).toBeGreaterThanOrEqual( Math.floor( width.table * ( width.share - 2 ) / 100 ) );
				}

				/*
				 *  Said on a pass as well as a failure. Two phases recorded
				 *  this alarm as red, then read a later green as a repair,
				 *  because a green printed nothing and there was nothing to
				 *  compare the two runs with. A number nobody can compare is
				 *  a number nobody reads.
				 */
				console.log(
					`      ${ mode } · ${ what }\n` +
					`        tallest ${ rows[ 0 ].h }px of ${ rows.length } rows, ceiling ${ ceiling }; the checkbox column ${ checkbox }px\n` +
					`        that row is ${ rows[ 0 ].id } "${ rows[ 0 ].title }"\n` +
					`        ${ on.length } columns on: ${ on.join( ', ' ) }`
				);

				expect(
					checkbox,
					`${ what }, arriving in ${ mode }: the checkbox column is ${ checkbox }px wide -- the table's slack has landed on it ` +
					`(core sizes it 2.5em; its label covers the cell, and beside FileBird that label is the drag handle)`
				).toBeLessThan( 60 );

				expect(
					rows[ 0 ].h,
					`${ what }, arriving in ${ mode }: the tallest of ${ rows.length } rows is ${ rows[ 0 ].h }px ` +
					`over ${ on.length } columns (${ on.join( ', ' ) }). ` +
					`The row is ${ rows[ 0 ].id } "${ rows[ 0 ].title }" — page one is the twenty newest ` +
					`attachments, so check it is a real picture and not a fixture a suite left behind`
				).toBeLessThanOrEqual( ceiling );
			}

			/*
			 *  A Screen Options tick, no reload (story 4.5). From the last set
			 *  -- two of ours on, the floor off -- every other plugin's column
			 *  is ticked on: File would share the leftover with their unsized
			 *  columns, so the 30% floor must go on in the frame after the
			 *  tick; ticked off again, File is the leftover's column and the
			 *  floor comes off. Before 4.5 the decision was made once at load.
			 */
			/*
			 *  The tab itself first, as a person clicks it: from 2026-09-10 to
			 *  09-21 the tree's positioned .wrap painted over core's Screen
			 *  Options and Help tabs and neither could be clicked on this screen
			 *  (a Playwright click waited four minutes on the wrap).
			 */
			await page.click( '#show-settings-link', { timeout: 10000 } );
			await expect( page.locator( '#adv-settings' ), 'Screen Options opens on the list screen beside the tree' ).toBeVisible();

			// And the panel it opens is over the tree, not under it (4.0.3).
			const boxes = await checkboxesReachable( page );
			const covered = boxes.filter( ( b ) => ! b.ok );
			console.log( `      ${ mode } · ${ boxes.length - covered.length }/${ boxes.length } checkboxes reachable` );
			expect( boxes.length, 'the open Screen Options panel lists column checkboxes' ).toBeGreaterThan( 0 );
			expect(
				covered.map( ( b ) => `${ b.id } → ${ b.hit }` ),
				`a press at the centre of every column checkbox lands on the checkbox (${ covered.length } of ${ boxes.length } covered)`
			).toEqual( [] );

			test.skip( ! theirs.length, "no third-party column on this site: the live tick is not exercised here" );
			for ( const id of theirs ) {
				await page.check( `[id="${ id }-hide"]` );
			}
			await page.waitForTimeout( 800 );
			const shown = await readWidths( page );
			console.log( `      ${ mode } · ticked on live: ${ theirs.join( ', ' ) }\n        share ${ shown.share }%, File ${ shown.file }px of ${ shown.table }px, the checkbox ${ shown.checkbox }px` );
			expect( shown.share, `ticking ${ theirs.length } other plugin's column(s) on, no reload: the 30% floor follows (File ${ shown.file }px of ${ shown.table }px)` ).toBe( 30 );
			// Within two points, as above: two of ours and three of theirs sum past 100%.
			expect( shown.file, `with the floor on, File holds 30% (${ shown.file }px of ${ shown.table }px)` ).toBeGreaterThanOrEqual( Math.floor( shown.table * 0.28 ) );
			expect( shown.checkbox, `the checkbox column after the tick is ${ shown.checkbox }px` ).toBeLessThan( 60 );

			for ( const id of theirs ) {
				await page.uncheck( `[id="${ id }-hide"]` );
			}
			await page.waitForTimeout( 800 );
			const hidden = await readWidths( page );
			console.log( `      ${ mode } · ticked off again: share ${ hidden.share }%, File ${ hidden.file }px of ${ hidden.table }px` );
			expect( hidden.share, `ticked off again, no reload: File is the leftover's column, the floor off (File ${ hidden.file }px of ${ hidden.table }px)` ).toBe( 0 );
			expect( hidden.file, `File takes the leftover (${ hidden.file }px of ${ hidden.table }px)` ).toBeGreaterThanOrEqual( Math.floor( hidden.table * 0.4 ) );
		} finally {
			await openList( page ).catch( () => null );
			await setHidden( page, before ).catch( () => null );
		}
	} );
}


/*
 *  The folder filter, which is what list mode has instead of the tree.
 *
 *  WordPress has filtered a list table by a hierarchical taxonomy this way
 *  since the Posts screen had categories: a dropdown beside All dates and a
 *  Filter button, submitted as a GET. So the folder ends up in the URL, which
 *  is what makes it survive the browser's own Back button -- the thing a
 *  drawer or a tree click does not give you.
 *
 *  Read-only: it filters and goes back, and writes nothing.
 */

test( 'the folder filter: Unfiled and every folder with its count, and the count is what comes back', async ( { page } ) => {

	test.setTimeout( 180000 );
	await page.setViewportSize( { width: 1600, height: 900 } );
	await openList( page );

	const bar = await page.evaluate( () => Array.from( document.querySelectorAll( 'select.vgml-folder-filter option' ) )
		.map( ( o ) => ( { value: o.value, text: o.text.replace( / /g, ' ' ).trim(), level: o.className } ) ) );

	expect( bar.length, 'the dropdown is on the screen' ).toBeGreaterThan( 2 );
	expect( bar[ 0 ].text, 'nothing chosen reads "All folders"' ).toBe( 'All folders' );
	expect( bar[ 1 ].text, 'Unfiled is second, with its count' ).toMatch( /^Unfiled \(\d[\d,.]*\)$/ );
	expect( bar[ 1 ].value ).toBe( 'not_in' );

	// Every folder carries a count, and children are indented the way core's
	// own category dropdown indents them.
	for ( const option of bar.slice( 2 ) ) {
		expect( option.text, `"${ option.text }" carries its count` ).toMatch( /\(\d[\d,.]*\)$/ );
	}
	expect( bar.filter( ( o ) => /^level-[1-9]/.test( o.level ) ).length, 'the tree is in it: children are indented' ).toBeGreaterThan( 0 );

	/* A folder with a manageable count, filtered for. */
	const count = ( o ) => Number( ( o.text.match( /\((\d[\d,.]*)\)$/ ) || [ 0, '0' ] )[ 1 ].replace( /[,.]/g, '' ) );
	const pick = bar.slice( 2 ).find( ( o ) => count( o ) > 0 && count( o ) < 200 );

	expect( pick, 'there is a folder with files in it to filter for' ).toBeTruthy();

	await openFilters( page );
	await page.selectOption( 'select.vgml-folder-filter', pick.value );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#post-query-submit' ) ] );
	await page.waitForTimeout( 1500 );
	await clearNotices( page );

	const shown = await page.evaluate( () => ( {
		url: location.search,
		items: Number( ( ( document.querySelector( '.displaying-num' ) || {} ).textContent || '0' ).replace( /\D/g, '' ) ),
		chosen: document.querySelector( 'select.vgml-folder-filter' ).value,
		// Another folder plugin's control in the same bar. FileBird's is `fbv`.
		foreign: !! document.querySelector( 'select[name="fbv"], select[name="folder"], select[name="wcp_folder"]' ),
	} ) );

	expect( shown.chosen, 'the dropdown reads the folder that is showing' ).toBe( pick.value );
	expect( shown.url, 'the folder is in the URL, which is what a bookmark keeps' ).toContain( `${ TAX }=${ pick.value }` );
	expect( shown.items, 'the folder narrows the list' ).toBeGreaterThan( 0 );
	expect( shown.items, 'and never returns more than the folder holds' ).toBeLessThanOrEqual( count( pick ) );

	/*
	 *  The count is the number of rows only while nothing else is filtering
	 *  the same list.
	 *
	 *  The box runs FileBird Pro, which puts its own folder control in this
	 *  bar and narrows the list with it: a folder holding 45 attachments --
	 *  all of them ordinary, no children, checked in the database -- came back
	 *  as 42 rows with FileBird's own "all folders" and 28 without. That is
	 *  the collision our neighbour notice warns about in those words, and it
	 *  is not something our count can be right about. On a library with one
	 *  folder plugin the two numbers are the same, and that is asserted.
	 */
	if ( ! shown.foreign ) {
		expect( shown.items, `"${ pick.text }" says ${ count( pick ) } and the list returns that many` ).toBe( count( pick ) );
	} else {
		console.log( `  another folder plugin is filtering this list, so "${ pick.text }" returned ${ shown.items } rows; the count is not asserted` );
	}

	/* Unfiled, and its count. */
	await openList( page );
	await openFilters( page );
	await page.selectOption( 'select.vgml-folder-filter', 'not_in' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#post-query-submit' ) ] );
	await page.waitForTimeout( 1500 );
	await clearNotices( page );

	const unfiled = await page.evaluate( () => ( {
		items: Number( ( ( document.querySelector( '.displaying-num' ) || {} ).textContent || '0' ).replace( /\D/g, '' ) ),
		chosen: document.querySelector( 'select.vgml-folder-filter' ).value,
	} ) );

	expect( unfiled.chosen, 'the dropdown still reads Unfiled' ).toBe( 'not_in' );
	// Held to whichever answer the library gives: a fully filed library offers Unfiled (0) and returns nothing (the tech site, 2026-09-17).
	if ( count( bar[ 1 ] ) > 0 ) {
		expect( unfiled.items, 'Unfiled returns files, and never more than it offers' ).toBeGreaterThan( 0 );
	}
	expect( unfiled.items ).toBeLessThanOrEqual( count( bar[ 1 ] ) );

	if ( ! shown.foreign ) {
		expect( unfiled.items, 'Unfiled returns the number it offers' ).toBe( count( bar[ 1 ] ) );
	}

	/* The back button: core's mechanism, so the list that was there comes back. */
	await page.goBack( { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 2500 );
	const back = await page.evaluate( () => ( {
		url: location.search,
		items: Number( ( ( document.querySelector( '.displaying-num' ) || {} ).textContent || '0' ).replace( /\D/g, '' ) ),
	} ) );

	expect( back.url, 'Back leaves the filter behind' ).not.toContain( `${ TAX }=not_in` );
	expect( back.items, 'and the whole library is on screen again' ).toBeGreaterThan( unfiled.items );
} );


/*
 *  The list opens on the pictures (S10.6; Nathan, 2026-09-16, on ms2: "I
 *  don't want to scroll past all kinds of filters -- bad UX; and the sidebar
 *  is too short"). Measured before: at 1280×800 the first row sat at 453 px
 *  under core's two filter rows, the search row and the bulk-actions row; the
 *  panel was 677 px in an 800 px viewport, the folders after the Filters and
 *  AI folders groups. The mock of 2026-09-17 (list-on-the-pictures): one row
 *  above the pictures -- the folder chip, search, a Filter chip whose card
 *  holds core's choices -- the view switch and the pages at its right; the
 *  panel the viewport's height, sticky, the tree first, the two groups
 *  closed below it; bulk actions only while a row is checked.
 *
 *  The box carries other plugins' notices above the form (a torture site),
 *  so the first row is measured with the notices' height taken off: what a
 *  site without them would show. Mutation: the panel's sticky rule dropped
 *  (css/vergeml-tree.css, body.vgml-mode-list.vgml-has-tree .vgml-tree) ->
 *  the height row red.
 */

test( 'the list opens on the pictures: one row above them, the panel the viewport\'s height with the tree first', async ( { page } ) => {

	test.setTimeout( 180000 );
	await page.setViewportSize( { width: 1280, height: 800 } );
	await page.goto( '/wp-admin/upload.php?mode=list', { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '#the-list tr[id^="post-"]', { timeout: 30000 } );
	await page.waitForTimeout( 1500 );
	await unfold( page );
	await page.waitForTimeout( 500 );

	const geometry = await page.evaluate( () => {
		const box = ( el ) => el ? el.getBoundingClientRect() : null;
		const form = document.querySelector( '#posts-filter' );
		const firstRow = box( document.querySelector( '#the-list tr[id^="post-"]' ) );
		// Every notice above the form, whoever printed it: taken off the first row's height.
		let notices = 0;
		document.querySelectorAll( '#wpbody-content .notice, #wpbody-content .updated, #wpbody-content .error' ).forEach( ( n ) => {
			const b = n.getBoundingClientRect();
			if ( b.height && form && b.top < form.getBoundingClientRect().top ) {
				notices += b.height + parseFloat( getComputedStyle( n ).marginTop || 0 ) + parseFloat( getComputedStyle( n ).marginBottom || 0 );
			}
		} );
		const panel = document.querySelector( '.vgml-tree' );
		const list = panel && panel.querySelector( '.vgml-list' );
		const rows = list ? Array.from( list.querySelectorAll( '.vgml-node' ) ).filter( ( n ) => n.offsetHeight ) : [];
		const cls = ( n ) => ( n.classList.contains( 'vgml-filters-head' ) ? ( n.classList.contains( 'vgml-ai-head' ) ? 'ai' : 'filters' ) : ( n.classList.contains( 'vgml-pseudo' ) ? ( n.classList.contains( 'vgml-smart' ) ? 'smart' : 'pseudo' ) : 'folder' ) );
		const order = rows.map( cls );
		const firstFolder = rows.find( ( n ) => 'folder' === cls( n ) );
		const bar = document.querySelector( '#posts-filter .vgml-listbar' );
		const barRows = bar ? Math.round( bar.getBoundingClientRect().height ) : null;
		const filterItems = document.querySelector( '.wp-filter .filter-items, .vgml-filter-card .filter-items' );
		return {
			firstRowTop: firstRow ? Math.round( firstRow.top - notices ) : null,
			notices: Math.round( notices ),
			panel: panel ? { top: Math.round( box( panel ).top ), h: Math.round( box( panel ).height ), position: getComputedStyle( panel ).position } : null,
			viewport: innerHeight,
			order: order.join( ',' ),
			firstFolderVisible: !! ( firstFolder && list && box( firstFolder ).top >= box( list ).top && box( firstFolder ).bottom <= box( list ).bottom ),
			groupsClosed: 0 === rows.filter( ( n ) => 'smart' === cls( n ) ).length,
			bar: barRows,
			filtersHidden: !! filterItems && ! filterItems.offsetHeight,
			bulkHidden: ! document.querySelector( '#bulk-action-selector-top' ) || ! document.querySelector( '#bulk-action-selector-top' ).offsetHeight,
			pagesInBar: !! ( bar && bar.querySelector( '.tablenav-pages' ) ),
			searchInBar: !! ( bar && bar.querySelector( '.search-form, .search-box, input[type="search"]' ) ),
		};
	} );
	console.log( `      list at 1280×800: first row at ${ geometry.firstRowTop }px (${ geometry.notices }px of notices taken off), panel ${ geometry.panel && geometry.panel.h }px ${ geometry.panel && geometry.panel.position } in ${ geometry.viewport }, rows ${ geometry.order }` );

	expect( geometry.firstRowTop, 'the first picture within 240 px of the top with nothing set' ).toBeLessThanOrEqual( 240 );
	expect( [ 'sticky', 'fixed' ].includes( geometry.panel && geometry.panel.position ), `the panel stays beside the list as the page scrolls (${ geometry.panel && geometry.panel.position })` ).toBe( true );
	expect( geometry.panel && geometry.panel.top + geometry.panel.h, 'the panel reaches the viewport\'s foot' ).toBeGreaterThanOrEqual( geometry.viewport - 24 );
	expect( geometry.order, 'the tree first: pseudo rows, then folders, then the two groups' ).toMatch( /^(pseudo,)+(folder,)+filters(,ai)?$/ );
	expect( geometry.groupsClosed, 'Filters and AI folders closed' ).toBe( true );
	expect( geometry.firstFolderVisible, 'the first folder is on screen without scrolling' ).toBe( true );
	expect( geometry.searchInBar && geometry.pagesInBar, 'search and the pages are in the one row' ).toBe( true );
	expect( geometry.filtersHidden, 'core\'s filter choices wait behind the Filter chip' ).toBe( true );
	expect( geometry.bulkHidden, 'bulk actions wait for a checked row' ).toBe( true );

	// The Filter chip opens the card with core's choices; a checked row brings the bulk actions.
	await page.click( '.vgml-listbar .vgml-filter-chip' );
	await expect( page.locator( '.vgml-filter-card select.vgml-folder-filter' ) ).toBeVisible();
	await page.click( '.vgml-listbar .vgml-filter-chip' );
	await expect( page.locator( '.vgml-filter-card' ) ).toBeHidden();
	await page.check( '#the-list tr[id^="post-"] input[name="media[]"] >> nth=0' );
	await expect( page.locator( '#bulk-action-selector-top' ) ).toBeVisible();
	await page.uncheck( '#the-list tr[id^="post-"] input[name="media[]"] >> nth=0' );
	await expect( page.locator( '#bulk-action-selector-top' ) ).toBeHidden();
	await page.screenshot( { path: 'tests/ui/shots/list-on-the-pictures-1280x800.png' } );
} );


/*
 *  Move to folder..., in Bulk actions -- what replaces dragging a row onto
 *  the tree, in the idiom a list table already has.
 *
 *  Gated, because it moves real files. It uses files that are in no folder
 *  and puts them back in no folder, and it makes and deletes a folder of its
 *  own, so what it leaves behind is what it found.
 */

test( 'Move to folder: only the ticked rows move, and the notice says how many went where', async ( { page } ) => {

	test.skip( ! process.env.MODES_WALK, 'MODES_WALK=1 to move three real files into a folder of its own and put them back' );
	test.setTimeout( 240000 );
	await page.setViewportSize( { width: 1600, height: 900 } );

	const NAME = 'P9 list move';
	const unfiledList = async () => {
		await page.goto( `/wp-admin/upload.php?mode=list&${ TAX }=not_in`, { waitUntil: 'domcontentloaded' } );
		await page.waitForSelector( '#the-list tr[id^="post-"]', { timeout: 30000 } );
		await page.waitForTimeout( 1200 );
		await clearNotices( page );
	};
	const folders = ( id ) => api( page, `/wp/v2/media/${ id }?_fields=id,${ TAX }` ).then( ( m ) => m[ TAX ] || [] );

	await unfiledList();

	const ids = await page.evaluate( () => Array.from( document.querySelectorAll( '#the-list tr[id^="post-"]' ) )
		.slice( 0, 4 ).map( ( r ) => Number( r.id.replace( 'post-', '' ) ) ) );

	expect( ids.length, 'there are four unfiled files to walk on' ).toBe( 4 );

	const move = ids.slice( 0, 3 );
	const leave = ids[ 3 ];
	const made = Number( ( await api( page, `${ NS }/folder`, { taxonomy: TAX, action: 'create', name: NAME } ) ).id );

	try {
		await unfiledList();

		const group = await page.evaluate( () => {
			const g = document.querySelector( '#bulk-action-selector-top optgroup' );
			return g ? { label: g.label, options: g.children.length } : null;
		} );

		expect( group, 'the bulk menu has a folder group' ).toBeTruthy();
		expect( group.label ).toBe( 'Move to folder…' );
		expect( group.options, 'every folder is in it' ).toBeGreaterThan( 0 );

		for ( const id of move ) {
			await page.check( `#the-list input[name="media[]"][value="${ id }"]` );
		}

		await page.selectOption( '#bulk-action-selector-top', `vergeml-move-${ made }` );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#doaction' ) ] );
		await page.waitForTimeout( 2000 );

		await expect( page.locator( '.notice', { hasText: 'moved to' } ) )
			.toContainText( `3 files moved to ${ NAME }.` );

		for ( const id of move ) {
			expect( await folders( id ), `file ${ id } is in that folder and nowhere else` ).toEqual( [ made ] );
		}
		expect( await folders( leave ), 'the row that was not ticked did not move' ).toEqual( [] );

	} finally {
		for ( const id of ids ) {
			await api( page, `${ NS }/assign`, { taxonomy: TAX, attachments: [ id ], add: [], mode: 'move' } ).catch( () => null );
		}
		// By name, so a copy another plugin made of it goes too.
		const tree = await api( page, `${ NS }/tree?taxonomy=${ TAX }` ).catch( () => ( { nodes: [] } ) );
		for ( const n of ( tree.nodes || [] ).filter( ( n ) => n.name === NAME ) ) {
			await api( page, `${ NS }/folder`, { taxonomy: TAX, action: 'delete', id: n.id } ).catch( () => null );
		}
	}
} );


/*
 *  Nothing covers the library.
 *
 *  The grid screen was pinned to the viewport by a rule inherited from the
 *  fork -- `position: absolute; top: 0; bottom: 0` on the wrap -- with core's
 *  frame filling it from 50px down, so every admin notice printed inside the
 *  wrap was painted over. Eight of them on the box, 863px, and the tiles began
 *  under the third. It shipped that way for as long as the fork has existed
 *  and nothing ever said so, because the suite asserted what was on the screen
 *  and never what was on top of what.
 *
 *  So: no notice, ours or anyone's, may sit over the tiles, the tree or the
 *  table. Geometry, in both modes, on every push.
 */

for ( const mode of [ 'grid', 'list' ] ) {

	test( `nothing covers the library, in ${ mode }`, async ( { page } ) => {

		test.setTimeout( 120000 );
		await page.setViewportSize( { width: 1600, height: 900 } );

		await page.goto( `/wp-admin/upload.php?mode=${ mode }`, { waitUntil: 'domcontentloaded' } );
		await page.waitForSelector( mode === 'grid' ? '.media-frame' : '#the-list', { timeout: 30000 } );
		await page.waitForTimeout( 5000 );

		const covered = await page.evaluate( () => {

			const box = ( el ) => {
				const r = el.getBoundingClientRect();
				return { x: r.x, y: r.y + window.scrollY, w: r.width, h: r.height };
			};
			const seen = ( el ) => {
				const s = getComputedStyle( el );
				const r = el.getBoundingClientRect();
				return r.width > 4 && r.height > 4 && s.display !== 'none' && s.visibility !== 'hidden' && Number( s.opacity ) > 0.05;
			};
			const over = ( a, b ) => Math.min( a.x + a.w, b.x + b.w ) - Math.max( a.x, b.x ) > 2
				&& Math.min( a.y + a.h, b.y + b.h ) - Math.max( a.y, b.y ) > 2;

			// A notice inside the shelf is clipped by it, so the shelf is what
			// is on the screen rather than each notice in it.
			const shelf = document.getElementById( 'vgml-notices' );
			const notices = Array.from( document.querySelectorAll( '#wpbody-content .notice, #wpbody-content .updated, #wpbody-content .error, #wpbody-content .update-nag' ) )
				.filter( ( el ) => ! ( shelf && shelf.contains( el ) ) )
				.concat( shelf ? [ shelf ] : [] )
				.filter( seen );

			const library = [ '.media-frame', '.vgml-tree', '.wp-list-table' ]
				.map( ( sel ) => ( { sel, el: document.querySelector( sel ) } ) )
				.filter( ( x ) => x.el && seen( x.el ) );

			const bad = [];

			notices.forEach( ( n ) => {
				library.forEach( ( l ) => {
					if ( n.contains( l.el ) || l.el.contains( n ) ) return;
					if ( over( box( n ), box( l.el ) ) ) {
						bad.push( `${ ( n.className || n.id || 'notice' ).toString().split( ' ' )[ 0 ] } over ${ l.sel }` );
					}
				} );
			} );

			return { bad, notices: notices.length, library: library.map( ( l ) => l.sel ) };
		} );

		expect( covered.library.length, 'the library is on the screen to be covered' ).toBeGreaterThan( 0 );
		expect(
			covered.bad,
			`${ covered.notices } notice(s) on this screen; nothing may sit over ${ covered.library.join( ', ' ) }`
		).toEqual( [] );
	} );
}


/*
 *  "Why is it here", where most people meet a picture.
 *
 *  The field is kept off `query-attachments` because that listing runs
 *  attachment_fields_to_edit once per item -- 5.7 queries a picture, measured
 *  -- and the grid's modal renders from that same response, so until now the
 *  answer stopped at the attachment's own screen. The modal asks a read-only
 *  route for the one picture somebody opened instead.
 *
 *  Both halves are asserted here, because either alone can pass while the
 *  thing is broken: the modal says what the record says, and the listing is
 *  still carrying none of it. Read-only -- it opens a picture that already has
 *  a record and writes nothing.
 */

test( 'the grid modal says why a picture is here, and the listing still does not', async ( { page } ) => {

	test.setTimeout( 240000 );
	await page.setViewportSize( { width: 1600, height: 1000 } );

	await openMode( page, 'grid' );

	/*
	 *  Waited for rather than assumed: openMode returns when the browser is on
	 *  the screen, and on a busy box the tiles arrive after it. A read taken
	 *  too early sees an empty library, which would pass the listing check
	 *  below by having nothing in it to carry.
	 */
	await expect.poll( () => shownIds( page, 'grid' ).then( ( a ) => a.length ), {
		timeout: 30000,
		message: 'the grid fills with pictures',
	} ).toBeGreaterThan( 0 );

	/*
	 *  The listing's own response, read back off the models it filled. The
	 *  guard this phase must not undo is what keeps the field out of it, and a
	 *  listing that carries the markup has cost every visitor those queries
	 *  whether or not anybody opened a picture. Counted per picture, so the
	 *  number of pictures it was true of is part of the answer.
	 */
	const listing = await page.evaluate( () => {
		const models = wp.media.frames.browse.state().get( 'library' ).models;
		return {
			seen: models.length,
			carrying: models.filter( ( m ) => ( ( m.get( 'compat' ) || {} ).item || '' ).includes( 'vgml-why-facts' ) ).length,
		};
	} );

	expect( listing.seen, 'the listing filled with pictures to check' ).toBeGreaterThan( 0 );
	expect( listing.carrying, `not one of ${ listing.seen } listed pictures carries why-is-it-here markup` ).toBe( 0 );

	const ids = await shownIds( page, 'grid' );
	const subject = ids[ 0 ];

	/*
	 *  The route answers, and the listing never asked it.
	 *
	 *  Both halves matter: an answer proves the route is registered and this
	 *  person may read it, and a listing that asked eighty times would have
	 *  moved the cost off the server and onto the network instead of removing
	 *  it. Counted from the browser, which is where the requests are.
	 */
	const answer = await api( page, `${ NS }/librarian-why/${ subject }` );

	expect( answer.id, 'the route answers for the picture it was asked about' ).toBe( subject );
	expect( Array.isArray( answer.lines ), 'and answers in lines' ).toBe( true );
	expect( answer.label, 'under the same label the attachment screen uses' ).toBeTruthy();
	// The word on the picture (spec §3): one of the three, or nothing to claim.
	expect( [ '', 'sure', 'likely', 'by you' ], `confidence is a word the pill knows, got "${ answer.confidence }"` ).toContain( answer.confidence );

	// Counted from here on, so the question this suite just asked itself is
	// not mistaken for one the screen asked.
	const asked = [];
	page.on( 'request', ( r ) => r.url().includes( '/librarian-why/' ) && asked.push( r.url() ) );

	await openMode( page, 'grid' );

	// The same wait, for the same reason: a listing that has not arrived has
	// not asked anything either, and would pass this by doing nothing at all.
	await expect.poll( () => shownIds( page, 'grid' ).then( ( a ) => a.length ), {
		timeout: 30000,
		message: 'the grid fills with pictures again',
	} ).toBeGreaterThan( 0 );

	expect( asked, 'the listing asks nothing of the route' ).toEqual( [] );

	/*
	 *  A record, painted in the modal.
	 *
	 *  The lines are fulfilled rather than filed, and that is deliberate: the
	 *  reader's own answer is proved against real rows in
	 *  tests/tree/filing-trail.php, which builds all four outcomes and removes
	 *  them again. What is proved here is the half only a browser can show --
	 *  that the modal asks when a picture is opened and paints what came back
	 *  -- and it is proved the same way Phase 5 proved a refused request,
	 *  which needs no state on the box and cannot rot with the library.
	 */
	const LINES = [
		'In Architecture · scored 0.81',
		'Ahead of Landscape at 0.44 · by 0.37',
		'Filed 9 September · batch 23',
	];

	await page.route( `**/librarian-why/${ subject }**`, ( route ) => route.fulfill( {
		status: 200,
		contentType: 'application/json',
		body: JSON.stringify( { id: subject, label: answer.label, lines: LINES, confidence: 'sure', word: 'sure', term: 'Architecture' } ),
	} ) );

	await page.goto( `/wp-admin/upload.php?item=${ subject }`, { waitUntil: 'domcontentloaded' } );

	const frame = page.locator( '.edit-attachment-frame' );
	await expect( frame, 'the modal opens on that picture' ).toBeVisible( { timeout: 30000 } );
	await expect( frame.locator( '.vgml-why .vgml-why-facts li' ).first() ).toBeVisible( { timeout: 30000 } );

	expect( asked.length, 'and asks for the one picture it opened' ).toBeGreaterThan( 0 );
	expect( ( await frame.locator( '.vgml-why .name' ).innerText() ).startsWith( answer.label ), 'the label first' ).toBe( true );
	expect( await frame.locator( '.vgml-why-facts li' ).allInnerTexts(), 'line for line, what the record said' ).toEqual( LINES );
	// The word beside the label, as a pill (B.5): sure here, tinted as the Folders screen tints it.
	await expect( frame.locator( '.vgml-why .vgml-why-word' ) ).toHaveText( 'sure' );
	await expect( frame.locator( '.vgml-why .vgml-why-word' ) ).toHaveClass( /is-sure/ );

	// The modal, with the answer scrolled to. A full-page shot of this screen
	// photographs the panel's first screenful and calls it evidence.
	await frame.locator( '.vgml-why' ).scrollIntoViewIfNeeded();
	await page.waitForTimeout( 500 );
	await frame.screenshot( { path: 'tests/ui/shots/why-here-modal.png' } );

	/*
	 *  And a picture the record has never heard of shows no section at all --
	 *  not an empty list under a heading, which would be a claim that there is
	 *  something to read.
	 */
	await page.unroute( `**/librarian-why/${ subject }**` );
	await page.route( `**/librarian-why/${ subject }**`, ( route ) => route.fulfill( {
		status: 200,
		contentType: 'application/json',
		body: JSON.stringify( { id: subject, label: answer.label, lines: [] } ),
	} ) );

	await page.goto( `/wp-admin/upload.php?item=${ subject }`, { waitUntil: 'domcontentloaded' } );
	await expect( frame ).toBeVisible( { timeout: 30000 } );
	await expect( frame.locator( '.attachment-info' ).first() ).toBeVisible( { timeout: 30000 } );
	await page.waitForTimeout( 3000 );

	expect( await frame.locator( '.vgml-why' ).count(), 'no record, no section' ).toBe( 0 );
} );
