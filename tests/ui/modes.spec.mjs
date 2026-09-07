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
const OURS = [ `taxonomy-${ TAX }`, 'taxonomy-colour', 'vergeml_used', 'vgmlpro_source' ];
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

/** Every row on the list with its height, tallest first. */
const rowHeights = ( page ) => page.evaluate( () => Array.from( document.querySelectorAll( '#the-list > tr' ) )
	.map( ( r ) => ( { id: r.id, h: Math.round( r.getBoundingClientRect().height ) } ) )
	.sort( ( a, b ) => b.h - a.h ) );

const openList = async ( page ) => {
	await page.goto( '/wp-admin/upload.php?mode=list', { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '#the-list tr[id^="post-"]', { timeout: 30000 } );
	await page.waitForTimeout( 1500 );
	await clearNotices( page );
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
		 *  Nothing of ours takes a gutter. `.wrap` used to be padded 316px to
		 *  make room for the tree, and the tree was absolutely positioned in
		 *  the padding -- unconditionally, on a screen where FileBird Pro had
		 *  already taken 319px of its own.
		 */
		const room = await page.evaluate( () => ( {
			table: Math.round( document.querySelector( '.wp-list-table' ).getBoundingClientRect().width ),
			body: Math.round( document.querySelector( '#wpbody-content' ).getBoundingClientRect().width ),
			trees: document.querySelectorAll( '.vgml-tree' ).length,
		} ) );

		expect( room.trees, 'no folder panel in list mode' ).toBe( 0 );
		expect(
			room.body - room.table,
			`the table has the content column (content ${ room.body }px, table ${ room.table }px)`
		).toBeLessThan( 48 );

		const columns = await page.$$eval( '.wp-list-table thead th[id]', ( ths ) =>
			ths.map( ( th ) => ( { id: th.id, hidden: th.classList.contains( 'hidden' ) } ) ) );
		const every = columns.map( ( c ) => c.id );
		const before = columns.filter( ( c ) => c.hidden ).map( ( c ) => c.id );
		const ours = every.filter( ( id ) => OURS.includes( id ) );
		const theirs = every.filter( ( id ) => ! OURS.includes( id ) && ! CORE_COLUMNS.includes( id ) );

		expect( ours.length, 'our columns are on this screen to switch on' ).toBeGreaterThan( 0 );

		try {
			const sets = [
				[ 'ours off, and the other plugins\' columns off', ROW_CEILING, theirs.concat( ours ) ],
				[ 'all four of ours on, the other plugins\' columns off', ROW_CEILING, theirs ],
				[ 'as the screen ships, with every other plugin\'s column on', ROW_ALARM, ours ],
			];

			for ( const [ what, ceiling, hidden ] of sets ) {

				await setHidden( page, Array.from( new Set( hidden ) ) );
				await openList( page );

				const on = await page.$$eval( '.wp-list-table thead th[id]', ( ths ) =>
					ths.filter( ( th ) => ! th.classList.contains( 'hidden' ) ).map( ( th ) => th.id ) );
				const rows = await rowHeights( page );

				expect( rows.length, 'the list has rows to measure' ).toBeGreaterThan( 0 );
				expect(
					rows[ 0 ].h,
					`${ what }, arriving in ${ mode }: the tallest of ${ rows.length } rows is ${ rows[ 0 ].h }px over ${ on.length } columns (${ on.join( ', ' ) })`
				).toBeLessThanOrEqual( ceiling );
			}
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
	await page.selectOption( 'select.vgml-folder-filter', 'not_in' );
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#post-query-submit' ) ] );
	await page.waitForTimeout( 1500 );
	await clearNotices( page );

	const unfiled = await page.evaluate( () => ( {
		items: Number( ( ( document.querySelector( '.displaying-num' ) || {} ).textContent || '0' ).replace( /\D/g, '' ) ),
		chosen: document.querySelector( 'select.vgml-folder-filter' ).value,
	} ) );

	expect( unfiled.chosen, 'the dropdown still reads Unfiled' ).toBe( 'not_in' );
	expect( unfiled.items, 'Unfiled returns files, and never more than it offers' ).toBeGreaterThan( 0 );
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
