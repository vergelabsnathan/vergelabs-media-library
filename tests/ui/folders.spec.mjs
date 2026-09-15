import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  Folders: five steps, one tree, one primary.
 *
 *  Walked on the box. Without GUIDE_WALK=1 nothing here asks the service for
 *  a turn and nothing moves a file: the session is planted through the same
 *  routes the screen uses, the tree and the dry run answer from the database
 *  (one embed per new folder path, cached a week), and the screenshots are of
 *  the resting screen. With GUIDE_WALK=1 "Propose folders" is pressed and the
 *  stream stopped (two planner calls' worth: the token and the turn).
 *
 *  Screenshots go to tests/ui/shots/, not test-results/: Playwright empties
 *  test-results/ at the start of every run, and the shots are evidence that
 *  must outlive the next run.
 *
 *  The session that was on the box is read first and put back at the end,
 *  whatever the tests did: a planted draft once stayed behind, somebody
 *  pressed Move on it, and twenty-one real folders went. The confirm test
 *  plants a draft of new folders only, with their classes, so /guide/confirm
 *  asks the planner about nothing and seeds no profile onto a real folder.
 */

const WALK = process.env.GUIDE_WALK === '1';

/*
 *  Gate 5, as measured on the box on 2026-09-05 before this screen replaced
 *  the Sort screen: the page's own render cost 1 query, and one REST request
 *  (the session, 1 query) stood between the page and its first paint, which
 *  came at 12.2 s. This screen paints from the data that came with the page,
 *  so nothing stands between them; the page's render is asserted on the box
 *  by tests/tree/guide.php against the same measurement.
 */
const TODAY = { restRequests: 1, restQueries: 1 };

/*
 *  The word budget (spec §3): ≤ 80 words on screen per step, counted as
 *  tokens of innerText that carry a letter or a digit, with the tree
 *  component (.vgml-tv) left out -- the tree is the person's own data and a
 *  twenty-folder tree alone is past 80. tools/shoot-mock.mjs --words counts
 *  the mocks the same way.
 */
const BUDGET = 80;
const SIZES = [ [ 1600, 1000 ], [ 1280, 800 ] ];

const NS = '/vergeml/v1';

let found = null;

async function getSession( page ) {
	return page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/session` } ), NS );
}

async function reset( page ) {
	await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/session`, method: 'POST', data: { reset: true } } ), NS );
}

/** The session as the routes take it back: every turn in one write, the draft in another. */
async function restore( page ) {
	if ( found === null ) {
		return;
	}
	if ( ! /wp-admin/.test( page.url() ) ) {
		await open( page, SCREEN.dashboard );
	}
	// A tree the tests confirmed is opened again before anything is written over it.
	await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/unconfirm`, method: 'POST' } ).catch( () => null ), NS );
	await reset( page );
	const s = found.session;
	const turns = ( s.turns || [] ).map( ( t ) => t.role === 'assistant'
		? { say: { text: t.text, choices: t.choices || [], kind: t.kind } }
		: { said: { kind: t.kind, text: t.text, rule: t.rule } } );
	if ( turns.length ) {
		await page.evaluate( ( [ ns, turns ] ) => wp.apiFetch( { path: `${ ns }/guide/turn`, method: 'POST', data: { turns } } ), [ NS, turns ] );
	}
	if ( s.draft ) {
		await page.evaluate( ( [ ns, draft ] ) => wp.apiFetch( { path: `${ ns }/guide/session`, method: 'POST', data: { draft } } ), [ NS, s.draft ] );
	}
	if ( s.tree === 'confirmed' ) {
		await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/confirm`, method: 'POST' } ).catch( () => null ), NS );
	}
}

async function remember( page ) {
	await open( page, SCREEN.dashboard );
	if ( found === null ) {
		found = await getSession( page );
	}
}

/** A conversation at its cap, so a hand edit writes its line without asking the service, and a draft over the live folders. */
async function plant( page, withDraft ) {
	await reset( page );
	const boot = await getSession( page );
	const turns = [];
	for ( let i = 0; i < boot.session.cap; i++ ) {
		turns.push( {
			said: { kind: 'said', text: i === 0 ? 'By subject. Merge the small landscape folders.' : `Turn ${ i + 1 }` },
			say: { text: i === 0 ? [ 'In the draft:', '- **Landscape and nature** takes the four small folders', '- Portrait takes every portrait', 'Keep Workspace as one folder, or split desks from phones?' ].join( '\n' ) : `Reply ${ i + 1 }`, choices: i === 0 ? [ 'Keep as one', 'Split desks and phones' ] : [] },
		} );
	}
	await page.evaluate( ( [ ns, turns ] ) => wp.apiFetch( { path: `${ ns }/guide/turn`, method: 'POST', data: { turns } } ), [ NS, turns ] );
	if ( ! withDraft ) {
		return boot;
	}
	await page.evaluate( ( [ ns, draft ] ) => wp.apiFetch( { path: `${ ns }/guide/session`, method: 'POST', data: { draft } } ), [ NS, liveDraft( boot.nodes ) ] );
	return boot;
}

/** The live folders as a draft, the first top-level one renamed by hand, plus one folder the draft makes. */
function liveDraft( nodes ) {
	const folders = nodes.map( ( n ) => ( { key: 't' + n.id, term_id: n.id, name: n.name, parent: n.parent ? 't' + n.parent : '' } ) );
	const first = folders.find( ( f ) => f.parent === '' );
	first.name = first.name + ' (draft)';
	first.by = 'you';
	folders.push( { key: 'probe1', term_id: null, name: 'Draft probe', parent: '', count: 12, matches: 'a probe', classes: [ 'probe' ], kinds: [ 'photo' ], audience: '' } );
	return { folders, gone: {}, tags: [], origin: 'talk', rule: null };
}

/** The parent whose branch holds the most pictures: what the placeholder names. */
function largestParent( nodes ) {
	const kids = {};
	nodes.forEach( ( n ) => { ( kids[ n.parent ] = kids[ n.parent ] || [] ).push( n ); } );
	const total = ( n ) => n.count + ( kids[ n.id ] || [] ).reduce( ( s, k ) => s + total( k ), 0 );
	return nodes.filter( ( n ) => kids[ n.id ] ).sort( ( a, b ) => total( b ) - total( a ) )[ 0 ];
}

const WORDS = ( el ) => {
	const count = ( t ) => String( t || '' ).split( /\s+/ ).filter( ( x ) => /[\p{L}\p{N}]/u.test( x ) ).length;
	const all = count( el.innerText );
	const clone = el.cloneNode( true );
	clone.querySelectorAll( '.vgml-tv' ).forEach( ( t ) => t.remove() );
	el.parentNode.insertBefore( clone, el.nextSibling );
	const without = count( clone.innerText );
	clone.remove();
	return { with: all, without };
};

async function ready( page ) {
	await expect( page.locator( '.vgml-folders.is-ready' ) ).toBeVisible( { timeout: 30000 } );
}

test.describe( 'the Folders screen', () => {

	test.afterEach( async ( { page } ) => {
		await restore( page );
	} );

	/*
	 *  Opening the page asks no model for anything. Until 2026-09-15 a
	 *  described library with an empty session opened the conversation by
	 *  itself: a planner call, ten describes' worth, on every visit that
	 *  found the session empty. Now a proposal is a button with its cost.
	 *
	 *  Mutation: put `turn_( { open: true }, null )` back at the end of
	 *  js/vergeml-folders.js and the model-route assertion goes red.
	 */
	test( 'opens on the session\'s step, paints from the page, and asks no model route', async ( { page } ) => {
		const problems = [];
		page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );

		await remember( page );
		await reset( page );

		const rest = [];
		const model = [];
		let painted = false;
		page.on( 'response', ( r ) => {
			if ( ! painted && /\/vergeml\/v1\//.test( r.url() ) ) {
				rest.push( { url: r.url(), queries: Number( r.headers()[ 'x-vgml-queries' ] || 0 ) } );
			}
		} );
		page.on( 'request', ( r ) => {
			if ( /\/guide\/(turn|token|stream|propose)(\?|$)/.test( r.url() ) && 'POST' === r.method() ) {
				model.push( r.url() );
			}
		} );

		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );
		painted = true;

		expect( rest.length, `REST requests before first paint: ${ rest.map( ( r ) => r.url ).join( ', ' ) }` ).toBeLessThanOrEqual( TODAY.restRequests );
		expect( rest.reduce( ( n, r ) => n + r.queries, 0 ), 'their queries' ).toBeLessThanOrEqual( TODAY.restQueries );

		// The head: three pills. The rail: five steps, every one a button, Tree current on a described library.
		await expect( page.locator( '.vgml-folders-facts .g-pill' ) ).toHaveText( [ /^\d[\d,.]* pictures$/, /^\d[\d,.]* described$/, /^\d+ folders$/ ] );
		await expect( page.locator( '.g-rail .g-step' ) ).toHaveText( [ 'Describe', 'Tree', 'Fill', 'Alt text', 'Rename' ] );
		expect( await page.$$eval( '.g-rail .g-step', ( els ) => els.every( ( e ) => 'BUTTON' === e.tagName && ! e.disabled ) ), 'every step is a button, none disabled' ).toBe( true );
		await expect( page.locator( '.g-step.is-current' ) ).toHaveText( 'Tree' );
		await expect( page.locator( '.g-step[data-step="describe"]' ) ).toHaveClass( /is-done/ );
		await expect( page.locator( '#vgml-folders' ) ).toHaveAttribute( 'data-step', 'tree' );

		// Nothing was proposed and nothing asked: the button says what a proposal costs.
		await page.waitForTimeout( 2500 );
		expect( model, 'no model route fired on load' ).toEqual( [] );
		await expect( page.locator( '.vgml-propose-btn' ) ).toHaveText( 'Propose folders' );
		await expect( page.locator( '.g-card[data-card="tree"] .g-move .g-pill' ) ).toHaveText( '10 credits' );
		await expect( page.locator( '.g-card[data-card="tree"] .vgml-confirm-btn' ) ).toHaveText( 'This is my tree' );
		await expect( page.locator( '.g-card[data-card="tree"] .g-quiet' ).last() ).toHaveText( 'Skip' );
		expect( ( await getSession( page ) ).session.turns, 'the session is still empty' ).toEqual( [] );

		// The tree with pills (B.3): every count a pill, a small parent's children as chips.
		const counts = await page.$$eval( '.g-tree .vgml-count', ( els ) => els.map( ( e ) => e.className ) );
		expect( counts.length ).toBeGreaterThan( 0 );
		expect( counts.every( ( c ) => /\bg-pill\b/.test( c ) ), `every count is a pill: ${ counts.join( ' | ' ) }` ).toBe( true );
		await expect( page.locator( '.g-tree' ) ).toHaveAttribute( 'data-siblings', 'row' );

		await page.screenshot( { path: 'tests/ui/shots/folders-empty-session.png' } );
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );

	/*
	 *  The change line. Its placeholder is built from the tree on screen,
	 *  never fixed text; "Paste or upload a list" is a button, and the upload
	 *  is read here in the browser -- a .txt as the paste, a .csv row as one
	 *  path -- into the same parser. A .pdf is refused in one line: nothing
	 *  in the plugin reads one.
	 */
	test( 'the placeholder names the largest parent; a .txt or .csv uploads into the draft; a .pdf is refused', async ( { page } ) => {
		test.setTimeout( 180_000 );
		await remember( page );
		const boot = await plant( page, true );
		const parent = largestParent( boot.nodes );

		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );

		const input = page.locator( '.g-change .vgml-composer-text' );
		await expect( input ).toHaveAttribute( 'data-placeholder-from', 'tree' );
		// At the cap the composer's label is the cap's; the tree's placeholder is what the app built.
		const built = await page.evaluate( () => document.querySelector( '.g-change .vgml-composer-text' ).getAttribute( 'aria-label' ) );
		expect( built, 'the placeholder is built from the tree' ).toContain( `split ${ parent.name.replace( / \(draft\)$/, '' ) }` );
		await expect( page.locator( '.vgml-startover' ) ).toBeVisible();

		// The draft over the tree: the folder it makes wears the yellow pill, the renamed one its line.
		await expect( page.locator( '.g-tree .vgml-node.is-new .vgml-tag' ) ).toHaveText( 'new' );
		await expect( page.locator( '.g-tree .vgml-node.is-new .vgml-tag' ) ).toHaveClass( /\bg-pill\b.*\bis-new\b|\bis-new\b.*\bg-pill\b/ );
		await expect( page.locator( '.vgml-tv-sub' ).filter( { hasText: 'renamed from' } ) ).toContainText( ', by you' );

		// The way in: a button, expanded on press.
		const wayIn = page.locator( '.g-chip.is-way-in' );
		await expect( wayIn ).toHaveText( 'Paste or upload a list' );
		await expect( page.locator( '.vgml-paste' ) ).toBeHidden();
		await wayIn.click();
		await expect( wayIn ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( page.locator( '.vgml-paste' ) ).toBeVisible();

		expect( boot.nodes.some( ( n ) => /upload probe|csv probe/i.test( n.name ) ), 'the box holds no folder named Upload probe or Csv probe' ).toBe( false );
		const newBefore = await page.locator( '.g-tree .vgml-node.is-new' ).count();

		// A .txt: the paste, line for line.
		await page.locator( '.vgml-paste-file' ).setInputFiles( { name: 'tree.txt', mimeType: 'text/plain', buffer: Buffer.from( 'Upload probe > Alpha\nUpload probe > Beta\n' ) } );
		await expect( page.locator( '.vgml-paste-area' ) ).toHaveValue( 'Upload probe > Alpha\nUpload probe > Beta\n' );
		await expect( page.locator( '.vgml-read-line .g-pill' ).first() ).toHaveText( '3 folders' );
		await expect( page.locator( '.g-tree .vgml-node.is-new .vgml-name' ).filter( { hasText: 'Upload probe' } ) ).toHaveCount( 1 );
		// Its two leaves are chips under it, new, on this surface.
		await expect( page.locator( '.g-tree .vgml-tv-sibs .vgml-sib.is-new' ) ).toHaveText( [ 'Alpha', 'Beta' ] );
		await expect( page.locator( '.vgml-paste-refused' ) ).toBeHidden();

		// Until the dry run answers the confirm waits; then it is offered, and the session holds the paste.
		const confirm = page.locator( '.g-card[data-card="tree"] .vgml-confirm-btn' );
		await expect( confirm ).toBeDisabled();
		await expect( confirm ).toBeEnabled( { timeout: 90000 } );
		const s = await getSession( page );
		expect( s.session.draft.folders.filter( ( f ) => ! f.term_id ).map( ( f ) => f.name ).sort() ).toEqual( [ 'Alpha', 'Beta', 'Upload probe' ] );
		expect( s.session.fit, 'the turn route ran the dry run over the upload' ).not.toBeNull();
		if ( s.session.fit.counted ) {
			await expect( page.locator( '.g-card[data-card="tree"] .g-card-head .g-pill' ) ).toHaveText( [ /^\d+ folders$/, /^\d[\d,.]* placed$/, /^\d[\d,.]* stay unfiled$/ ] );
		}
		await page.screenshot( { path: 'tests/ui/shots/folders-upload.png' } );

		// A .csv: each row's cells are one path's levels.
		await page.locator( '.vgml-paste-file' ).setInputFiles( { name: 'tree.csv', mimeType: 'text/csv', buffer: Buffer.from( '"Csv probe","Gamma"\nCsv probe;Delta\n' ) } );
		await expect( page.locator( '.vgml-paste-area' ) ).toHaveValue( 'Csv probe > Gamma\nCsv probe > Delta' );
		await expect( page.locator( '.g-tree .vgml-node.is-new .vgml-name' ).filter( { hasText: 'Csv probe' } ) ).toHaveCount( 1 );

		// A .pdf: one line, and the draft as it was.
		await page.locator( '.vgml-paste-file' ).setInputFiles( { name: 'tree.pdf', mimeType: 'application/pdf', buffer: Buffer.from( '%PDF-1.4' ) } );
		await expect( page.locator( '.vgml-paste-refused li' ) ).toHaveText( [ 'tree.pdf is not a .txt or .csv file' ] );
		await expect( page.locator( '.vgml-paste-area' ) ).toHaveValue( 'Csv probe > Gamma\nCsv probe > Delta' );
		await expect( page.locator( '.g-tree .vgml-node.is-new .vgml-name' ).filter( { hasText: 'Csv probe' } ) ).toHaveCount( 1 );
		expect( newBefore ).toBeGreaterThanOrEqual( 1 );
		await page.screenshot( { path: 'tests/ui/shots/folders-upload-refused.png' } );
	} );

	/*
	 *  "This is my tree" (A.3, /guide/confirm): the rail moves to Fill, the
	 *  tree refuses every edit until Unconfirm. Skip is the other way past
	 *  the step: Fill, with the tree still open. The draft here is new folders
	 *  only, with classes, so the confirm asks the planner about nothing and
	 *  seeds no profile onto a real folder (see the file's header).
	 */
	test( 'confirm advances the rail and locks the tree; Unconfirm opens it; Skip leaves it unconfirmed', async ( { page } ) => {
		await remember( page );
		await plant( page, false );
		const draft = {
			folders: [
				{ key: 'c1', term_id: null, name: 'Confirm probe', parent: '', classes: [ 'probe' ], kinds: [ 'photo' ], audience: '', matches: 'a probe' },
				{ key: 'c2', term_id: null, name: 'Alpha', parent: 'c1', classes: [ 'probe alpha' ], kinds: [ 'photo' ], audience: '', matches: 'a probe' },
			],
			gone: {}, tags: [], origin: 'talk', rule: null,
		};
		await page.evaluate( ( [ ns, draft ] ) => wp.apiFetch( { path: `${ ns }/guide/session`, method: 'POST', data: { draft } } ), [ NS, draft ] );

		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );
		await expect( page.locator( '#vgml-folders' ) ).toHaveAttribute( 'data-step', 'tree' );

		// Skip first: Fill current, the tree not confirmed, and Fill's own button is the confirm.
		await page.locator( '.g-card[data-card="tree"] .g-quiet' ).filter( { hasText: 'Skip' } ).click();
		await expect( page.locator( '.g-step.is-current' ) ).toHaveText( 'Fill' );
		await expect( page.locator( '.g-step[data-step="tree"]' ) ).not.toHaveClass( /is-done/ );
		expect( ( await getSession( page ) ).session.tree ).toBe( 'editing' );
		await expect( page.locator( '.g-card[data-card="fill"] .vgml-confirm-btn' ) ).toHaveText( 'This is my tree' );
		await page.screenshot( { path: 'tests/ui/shots/folders-fill-skipped.png' } );

		// Back, and confirm.
		await page.locator( '.g-step[data-step="tree"]' ).click();
		await expect( page.locator( '.g-step.is-current' ) ).toHaveText( 'Tree' );
		const r = await Promise.all( [
			page.waitForResponse( ( res ) => /\/guide\/confirm/.test( res.url() ) ),
			page.locator( '.g-card[data-card="tree"] .vgml-confirm-btn' ).click(),
		] );
		expect( r[ 0 ].status(), 'confirm answered' ).toBe( 200 );
		expect( ( await r[ 0 ].json() ).profiled, 'the planner was asked about nothing' ).toBe( 0 );
		await expect( page.locator( '.g-step.is-current' ) ).toHaveText( 'Fill' );
		await expect( page.locator( '.g-step[data-step="tree"]' ) ).toHaveClass( /is-done/ );
		await expect( page.locator( '.vgml-move-btn' ) ).toHaveText( /^Fill \d[\d,.]* pictures?$/ );
		await expect( page.locator( '.vgml-move-btn' ) ).toBeEnabled();
		expect( ( await getSession( page ) ).session.tree ).toBe( 'confirmed' );

		// Locked: a rule, a turn and a draft are refused with 409; the change line is gone from the Tree step.
		const status = await page.evaluate( ( ns ) => Promise.all( [
			wp.apiFetch( { path: `${ ns }/guide/rule`, method: 'POST', data: { rule: 'kind', options: {} } } ).then( () => 200, ( e ) => e.data && e.data.status ),
			wp.apiFetch( { path: `${ ns }/guide/turn`, method: 'POST', data: { said: { kind: 'said', text: 'x' } } } ).then( () => 200, ( e ) => e.data && e.data.status ),
			wp.apiFetch( { path: `${ ns }/guide/session`, method: 'POST', data: { draft: { folders: [], gone: {}, tags: [], origin: 'talk', rule: null } } } ).then( () => 200, ( e ) => e.data && e.data.status ),
		] ), NS );
		expect( status, 'rule, turn and draft answer 409 on a confirmed tree' ).toEqual( [ 409, 409, 409 ] );
		await page.locator( '.g-step[data-step="tree"]' ).click();
		await expect( page.locator( '.g-change' ) ).toBeHidden();
		await expect( page.locator( '.g-card[data-card="tree"] .vgml-btn-primary' ) ).toHaveText( 'Next: Fill' );
		await page.screenshot( { path: 'tests/ui/shots/folders-confirmed.png' } );

		// Unconfirm: 200, editing again, the change line back.
		const u = await Promise.all( [
			page.waitForResponse( ( res ) => /\/guide\/unconfirm/.test( res.url() ) ),
			page.locator( '.g-card[data-card="tree"] .g-quiet' ).filter( { hasText: 'Unconfirm' } ).click(),
		] );
		expect( u[ 0 ].status() ).toBe( 200 );
		await expect( page.locator( '.g-change' ) ).toBeVisible();
		await expect( page.locator( '.g-step[data-step="tree"]' ) ).not.toHaveClass( /is-done/ );
		expect( ( await getSession( page ) ).session.tree ).toBe( 'editing' );
	} );

	/*
	 *  The budget, per step, at the two sizes the spec names, on a planted
	 *  session at its cap. Screenshots of every step beside the mocks'
	 *  (docs/superpowers/mocks/shots/2026-09-15-*).
	 */
	test( 'every step is within 80 words at 1600×1000 and 1280×800', async ( { page } ) => {
		await remember( page );
		await plant( page, true );

		for ( const [ width, height ] of SIZES ) {
			await page.setViewportSize( { width, height } );
			await open( page, SCREEN.folders );
			await ready( page );
			for ( const step of [ 'describe', 'tree', 'fill', 'alt', 'rename' ] ) {
				await page.evaluate( ( s ) => window.vgmlFoldersApp.setStep( s ), step );
				await expect( page.locator( '#vgml-folders' ) ).toHaveAttribute( 'data-step', step );
				const words = await page.$eval( '.wrap.vgml-librarian', WORDS );
				expect( words.without, `${ step } at ${ width }×${ height }: ${ words.without } words without the tree (${ words.with } with it)` ).toBeLessThanOrEqual( BUDGET );
				const doc = await page.evaluate( () => ( { w: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth } ) );
				expect( doc.w, `${ step } at ${ width }×${ height }: no horizontal scroll` ).toBeLessThanOrEqual( doc.cw );
				await page.screenshot( { path: `tests/ui/shots/folders-${ step }-${ width }x${ height }.png` } );
			}
		}
	} );

	/*
	 *  The model is asked for a "count" per folder in the tree shape and it
	 *  answers with arithmetic nothing performed -- "Illustrations (23),
	 *  Screenshots (6)" on this box on 4 September 2026, for a library nobody
	 *  had counted. The turn route runs vergeml_filing_pick() over the draft
	 *  before it hands it back, and this asserts the only thing that makes
	 *  that worth doing: every number on the screen is that run's -- the two
	 *  pills in the card head, the pill on every row and every chip.
	 */
	test( 'every number on the draft is the dry run\'s: the head pills, the rows, the chips', async ( { page } ) => {
		await remember( page );
		const boot = await plant( page, true );

		const r = await page.evaluate(
			( [ ns, draft ] ) => wp.apiFetch( { path: `${ ns }/guide/turn`, method: 'POST', data: { draft } } ),
			[ NS, liveDraft( boot.nodes ) ]
		);

		expect( r.fit, 'the turn route answers with the matcher\'s own run' ).not.toBeNull();
		expect( r.fit.looked ).toBeGreaterThan( 0 );
		for ( const f of r.draft.folders ) {
			expect( r.fit.counts, `the dry run has a number for ${ f.name }` ).toHaveProperty( f.key );
			expect( f.count, `${ f.name } carries the dry run's number` ).toBe( r.fit.counts[ f.key ] );
		}
		const unplaced = r.fit.unfiled.floor + r.fit.unfiled.margin + r.fit.unfiled.gated;
		expect( unplaced ).toBeLessThanOrEqual( r.fit.looked );

		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );

		// The head: folders, placed, stay unfiled -- the run's numbers, as pills.
		const head = page.locator( '.g-card[data-card="tree"] .g-card-head .g-pill' );
		await expect( head ).toHaveText( [ `${ r.draft.folders.length } folders`, `${ ( r.fit.looked - unplaced ).toLocaleString( 'en-US' ) } placed`, `${ unplaced.toLocaleString( 'en-US' ) } stay unfiled` ] );

		// The placeholder: the largest parent, and the biggest group the run would not place.
		const built = await page.evaluate( () => document.querySelector( '.g-change .vgml-composer-text' ).getAttribute( 'aria-label' ) );
		if ( r.fit.residue ) {
			expect( built ).toContain( `add ${ r.fit.residue.class }` );
		}

		// Every painted number, by key: a row reads for itself, a closed branch or a parent over chips for the branch.
		const kids = {};
		r.draft.folders.forEach( ( f ) => { ( kids[ f.parent || '' ] = kids[ f.parent || '' ] || [] ).push( f.key ); } );
		const subtree = ( key ) => ( r.fit.counts[ key ] || 0 ) + ( kids[ key ] || [] ).reduce( ( sum, k ) => sum + subtree( k ), 0 );

		const painted = await page.evaluate( () => {
			const out = {};
			const num = ( pill ) => {
				const was = pill.querySelector( '.vgml-was' );
				return Number( ( was ? pill.textContent.replace( was.textContent, '' ) : pill.textContent ).replace( /[^\d]/g, '' ) );
			};
			document.querySelectorAll( '.g-tree .vgml-node[data-key]:not(.vgml-tv-more)' ).forEach( ( row ) => {
				const pill = row.querySelector( ':scope > .vgml-row > .vgml-count' );
				if ( pill ) {
					out[ row.getAttribute( 'data-key' ) ] = { n: num( pill ), open: row.getAttribute( 'aria-expanded' ), chips: !! ( row.nextElementSibling && row.nextElementSibling.classList.contains( 'vgml-tv-sibs' ) ) };
				}
			} );
			document.querySelectorAll( '.g-tree .vgml-sib[data-key]' ).forEach( ( chip ) => {
				const pill = chip.querySelector( '.vgml-count' );
				if ( pill ) {
					out[ chip.getAttribute( 'data-key' ) ] = { n: num( pill ), open: null, chips: false, chip: true };
				}
			} );
			return out;
		} );
		expect( Object.keys( painted ).length, 'the draft paints numbers at all' ).toBeGreaterThan( 0 );
		for ( const [ key, seen ] of Object.entries( painted ) ) {
			const branch = 'false' === seen.open || seen.chips;
			const want = branch ? subtree( key ) : ( r.fit.counts[ key ] || 0 );
			expect( seen.n, `the number on ${ key }${ seen.chip ? ' (a chip)' : '' } is the dry run's` ).toBe( want );
		}
		expect( painted.probe1, 'the folder the draft makes carries a number' ).toBeTruthy();
		expect( painted.probe1.n ).toBe( r.fit.counts.probe1 );

		await page.screenshot( { path: 'tests/ui/shots/folders-dry-run.png', fullPage: true } );
	} );

	test( 'a hand edit is one line under the input, and survives a reload by id', async ( { page } ) => {
		await remember( page );
		await plant( page, false );
		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );

		// Rename a top-level leaf in place: a leaf under a small parent is a chip here, and a branch's first click toggles it.
		const first = page.locator( '.g-tree .vgml-node[data-key][aria-level="1"]:not([aria-expanded])' ).first();
		const name = await first.locator( '.vgml-name' ).innerText();
		await first.locator( '.vgml-name' ).dblclick();
		await page.locator( '.vgml-editor' ).fill( name + ' renamed' );
		await page.keyboard.press( 'Enter' );

		await expect( page.locator( '.g-change .vgml-msg.is-edit' ).last() ).toContainText( `Renamed ${ name } to ${ name } renamed` );
		await expect( page.locator( '.g-change .vgml-msg.is-edit' ).last() ).toBeVisible();
		await expect( page.locator( '.vgml-tv-sub' ).filter( { hasText: `renamed from ${ name }` } ) ).toContainText( ', by you' );

		await expect.poll( async () => {
			const s = await getSession( page );
			const renamed = s.session.draft && s.session.draft.folders.find( ( f ) => f.name === name + ' renamed' );
			return renamed ? renamed.term_id : 0;
		}, { message: 'the renamed folder keeps its term id in the persisted draft', timeout: 20000 } ).toBeGreaterThan( 0 );
		expect( ( await getSession( page ) ).session.turns.at( -1 ).kind ).toBe( 'edit' );

		await page.reload( { waitUntil: 'domcontentloaded' } );
		await ready( page );
		await expect( page.locator( '.g-change .vgml-msg.is-edit' ).last() ).toContainText( 'Renamed' );
		await expect( page.locator( '.g-tree .vgml-node.is-change .vgml-name' ).first() ).toContainText( name + ' renamed' );
	} );

	test( 'the old guide address lands here', async ( { page } ) => {
		await remember( page );
		await plant( page, false );
		await page.goto( '/wp-admin/admin.php?page=media-guide', { waitUntil: 'domcontentloaded' } );
		await expect( page ).toHaveURL( /page=media-librarian/ );
	} );

	test( 'walk: Propose folders streams a proposal, and Stop stops it', async ( { page } ) => {
		test.skip( ! WALK, 'GUIDE_WALK=1 spends planner calls on the box' );
		test.setTimeout( 300_000 );
		console.log( '  cost: the token and the opening turn are two planner calls, ten describes\' worth each' );

		await remember( page );
		await reset( page );
		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );

		await page.locator( '.vgml-propose-btn' ).click();
		await expect( page.locator( '.vgml-send.is-stop' ), 'send became Stop while the reply streams' ).toBeVisible( { timeout: 60000 } );
		await page.waitForFunction( () => ( document.querySelector( '.vgml-msg.is-streaming .vgml-msg-body' ) || {} ).textContent.length > 20, null, { timeout: 60000 } );
		await page.locator( '.vgml-send.is-stop' ).click();
		await expect( page.locator( '.vgml-send' ) ).not.toHaveClass( /is-stop/ );
		await expect( page.locator( '.vgml-msg.is-note' ) ).toContainText( 'Stopped' );

		const said = await page.locator( '.vgml-msg.is-assistant .vgml-msg-body' ).first().innerText();
		console.log( '  the assistant said:', JSON.stringify( said ) );
		expect( said, 'the model claims nothing has happened to the library' )
			.not.toMatch( /\b(routed|already (in|filed)|nothing is left unfiled|are now (in|filed))\b/i );
		await page.screenshot( { path: 'tests/ui/shots/folders-proposed.png' } );
	} );
} );
