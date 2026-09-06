import { test, expect, open } from './fixtures.mjs';

/*
 *  The import screen, walked.
 *
 *      IMPORT_WALK=1 npx playwright test --config tests/ui/playwright.config.mjs import.spec
 *
 *  Off by default, because the walk moves real pictures into real folders and
 *  takes them out again. Every other spec in here may run on any push; this one
 *  changes the library for the length of a test and puts it back, so it is
 *  asked for by name.
 *
 *  It spends nothing. Nothing on this screen reaches a model: the importer
 *  reads another plugin's tables and writes terms.
 *
 *  It walks whatever FileBird holds on the site and skips when FileBird is
 *  empty: a browser cannot write another plugin's tables, so the tree comes
 *  from tools/box-filebird-fixture.sh, which is the tree the card was drawn
 *  against.
 *
 *  What it proves, in the order the person meets it:
 *
 *    - every source the importer reads is named, whether or not it has
 *      anything here, and the ones with nothing carry no button;
 *    - the outcome line's numbers are what the import then does. This is the
 *      one that matters: the card states the outcome before the press, so a
 *      plan that disagrees with the run is a lie on a button;
 *    - the done line is the tree that came out of it;
 *    - the undo puts back exactly what it took.
 */

const WALK = process.env.IMPORT_WALK === '1';

test.describe( 'the import screen', () => {

	test.skip( ! WALK, 'IMPORT_WALK=1 to run: it imports and undoes on the real library.' );

	test( 'every source is named, and only the ones with folders can be pressed', async ( { page } ) => {

		await open( page, 'media-import-folders' );
		await expect( page.locator( '#vgml-import-app .vgml-srcs' ).first() ).toBeVisible( { timeout: 30000 } );

		const sources = await page.evaluate( () =>
			wp.apiFetch( { path: '/vergeml/v1/import', method: 'POST', data: { action: 'found' } } )
		);

		const plugins = sources.sources.filter( ( s ) => s.key !== 'csv' );

		expect( plugins.length, 'the seven plugin sources are all returned, empty or not' ).toBe( 7 );
		expect( plugins.filter( ( s ) => ! s.folders ).length, 'the ones with nothing are returned too' ).toBeGreaterThan( 0 );

		// The line under the title is the library's own numbers.
		await expect( page.locator( '#vgml-import-facts' ) ).toHaveText(
			/^[\d,.]+ files · [\d,.]+ pictures · [\d,.]+ folders? · [\d,.]+ in no folder$/
		);

		// A card each for what was found; the rest are one line under them.
		const cards = page.locator( '.vgml-import-sources .vgml-src' );
		const withFolders = plugins.filter( ( s ) => s.folders > 0 );

		await expect( cards ).toHaveCount( withFolders.length );

		if ( withFolders.length ) {
			// Every card that is drawn has a button, and it says its number.
			await expect( cards.first().locator( '.vgml-src-go' ) ).toHaveText( /^Import [\d,.]+ folders$/ );
			await expect( cards.first().locator( '.vgml-src-does' ) ).toContainText( 'filed into' );
		}

		const also = page.locator( '.vgml-srcs-also' );
		await expect( also ).toBeVisible();
		await expect( also ).toContainText( 'Also read, with no folders on this site' );
		await expect( also ).toContainText( 'whether or not the plugin is still switched on' );
		// The line names them; none of them carries a control.
		await expect( also.locator( 'button' ) ).toHaveCount( 0 );

		for ( const source of plugins.filter( ( s ) => ! s.folders ) ) {
			await expect( also ).toContainText( source.name );
		}

		// The spreadsheet is a card of its own, and writing one out is its own section.
		await expect( page.locator( '.vgml-import-file-in .vgml-kicker' ) ).toHaveText( 'From a spreadsheet' );
		await expect( page.locator( '.vgml-import-file-out .vgml-kicker' ) ).toHaveText( 'Write out your folders' );
		// No preview step anywhere on the screen.
		await expect( page.locator( 'text=Preview import' ) ).toHaveCount( 0 );
	} );

	test( 'the outcome line is what the import does, and the undo puts it back', async ( { page } ) => {

		await open( page, 'media-import-folders' );
		await expect( page.locator( '#vgml-import-app .vgml-srcs' ).first() ).toBeVisible( { timeout: 30000 } );

		const before = await page.evaluate( () =>
			wp.apiFetch( { path: '/vergeml/v1/import', method: 'POST', data: { action: 'found' } } )
		);

		const filebird = ( before.sources || [] ).filter( ( s ) => s.key === 'filebird' && s.folders > 0 )[ 0 ];

		test.skip( ! filebird, 'no FileBird folders on this site: run tools/box-filebird-fixture.sh first.' );

		/*
		 *  The log keeps the last five imports and drops the oldest to make
		 *  room, and the undo goes with the record. So a walk that runs against
		 *  a full log destroys somebody's undo -- it took one off this box on
		 *  2026-09-06 before this guard existed, and the record cannot be put
		 *  back. Nothing here may cost a site an undo, so it stops instead.
		 */
		const log = await page.evaluate( () =>
			wp.apiFetch( { path: '/vergeml/v1/import', method: 'POST', data: { action: 'history' } } )
		);

		test.skip( ( log.history || [] ).length >= 5,
			'the import log is full: this walk would push the oldest import off it and take its undo with it.' );

		const card = page.locator( '.vgml-src[data-source="filebird"]' );

		await expect( card.locator( '.vgml-src-has' ) ).toHaveText(
			`${ Number( filebird.folders ).toLocaleString() } folders · ${ Number( filebird.files ).toLocaleString() } files`
		);
		await expect( card.locator( '.vgml-src-go' ) ).toHaveText(
			`Import ${ Number( filebird.folders ).toLocaleString() } folders`
		);

		const said = await card.locator( '.vgml-src-does' ).innerText();

		/*
		 *  The numbers on the card, read off the card rather than off the
		 *  response: what is compared with the import is what a person read.
		 *
		 *  A number starts with a digit. /[\d,.]+/ also matches the comma
		 *  between the clauses and the full stop at the end, and the last of
		 *  those read back as 0 -- which is how this line first went red
		 *  against an import that had done exactly what the card promised.
		 */
		const numbers = said.match( /\d[\d,.]*/g ).map( ( x ) => Number( x.replace( /[,.]/g, '' ) ) );

		expect( numbers.length, said ).toBeGreaterThanOrEqual( 2 );

		await card.locator( '.vgml-src-go' ).click();

		// The button carries its own progress and nothing else moves.
		await expect( card.locator( '.vgml-src-go' ) ).toHaveText( /^Importing · [\d,.]+ of [\d,.]+ files$/ );

		// The done line, where the person was looking.
		await expect( card.locator( '.vgml-src-has' ) ).toHaveText(
			/^[\d,.]+ folders made · [\d,.]+ files filed$/, { timeout: 120000 }
		);
		await expect( card.locator( '.vgml-src-does' ) ).toHaveText(
			/^[\d,.]+ folders now, [\d,.]+ files in no folder\.$/
		);

		const done = await card.locator( '.vgml-src-has' ).innerText();
		const made = done.match( /\d[\d,.]*/g ).map( ( x ) => Number( x.replace( /[,.]/g, '' ) ) );

		// The card said N new folders before the press; the import made N.
		expect( made[ 0 ], `the card promised ${ numbers[ 0 ] } new folders and made ${ made[ 0 ] }` ).toBe( numbers[ 0 ] );

		// And the files: the third number in the outcome line, or the second
		// when nothing merged and the sentence has no merge clause.
		expect( made[ 1 ], `the card promised ${ numbers[ numbers.length - 1 ] } files and filed ${ made[ 1 ] }` )
			.toBe( numbers[ numbers.length - 1 ] );

		// The undo says how much it removes, and is the same control the
		// history row carries afterwards.
		const undo = card.locator( '.vgml-src-act button' );
		await expect( undo ).toHaveText( `Undo · ${ made[ 0 ].toLocaleString() } folders` );

		const afterImport = await page.evaluate( () =>
			wp.apiFetch( { path: '/vergeml/v1/import', method: 'POST', data: { action: 'found' } } )
		);

		expect( afterImport.facts.folders, 'the tree grew by what the card said it made' )
			.toBe( before.facts.folders + made[ 0 ] );

		// The history names the source and dates the row.
		await expect( page.locator( '.vgml-import-recent .vgml-kicker' ) ).toHaveText( 'Imports you have made' );

		await undo.click();

		await expect( page.locator( '.vgml-import-good' ) ).toHaveText(
			/^[\d,.]+ folders removed · [\d,.]+ files back where they were$/, { timeout: 120000 }
		);
		await expect( card.locator( '.vgml-src-go' ) ).toHaveText(
			`Import ${ Number( filebird.folders ).toLocaleString() } folders`, { timeout: 30000 }
		);

		const after = await page.evaluate( () =>
			wp.apiFetch( { path: '/vergeml/v1/import', method: 'POST', data: { action: 'found' } } )
		);

		expect( after.facts.folders, 'the library is the size it was' ).toBe( before.facts.folders );
		expect( after.facts.unfiled, 'and the same files are in no folder' ).toBe( before.facts.unfiled );

		// And the record of what was imported before this ran is untouched.
		const logAfter = await page.evaluate( () =>
			wp.apiFetch( { path: '/vergeml/v1/import', method: 'POST', data: { action: 'history' } } )
		);

		expect( ( logAfter.history || [] ).length, 'the imports on record are the ones that were there' )
			.toBe( ( log.history || [] ).length );

		// The source was never touched: that is the whole safety argument.
		expect( after.sources.filter( ( s ) => s.key === 'filebird' )[ 0 ].folders ).toBe( filebird.folders );
		expect( after.sources.filter( ( s ) => s.key === 'filebird' )[ 0 ].files ).toBe( filebird.files );
	} );

	test( 'the history is named and dated, and its undo says what it removes', async ( { page } ) => {

		await open( page, 'media-import-folders' );
		await expect( page.locator( '#vgml-import-app .vgml-srcs' ).first() ).toBeVisible( { timeout: 30000 } );

		const history = await page.evaluate( () =>
			wp.apiFetch( { path: '/vergeml/v1/import', method: 'POST', data: { action: 'history' } } )
		);

		test.skip( ! ( history.history || [] ).length, 'nothing has been imported on this site yet.' );

		const rows = page.locator( '.vgml-import-history' );

		await expect( rows ).toHaveCount( history.history.length );

		const first = rows.first();

		// A name, not a source key.
		await expect( first.locator( '.vgml-import-history-what b' ) ).not.toHaveText( 'csv' );
		await expect( first.locator( '.vgml-import-history-what' ) ).toHaveText( /· [\d,.]+ folders, [\d,.]+ files$/ );
		// A date, not "4d".
		await expect( first.locator( '.vgml-import-history-when' ) ).toHaveText( /^\d{1,2} \w+, \d{1,2}[:.]\d{2}/ );
		await expect( first.locator( '.vgml-import-undo' ) ).toHaveText( /^Undo · [\d,.]+ folders$/ );
	} );
} );
