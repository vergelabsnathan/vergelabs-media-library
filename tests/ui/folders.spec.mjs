import { test, expect, open, SCREEN } from './fixtures.mjs';
import { boxFor, boxPhp } from './box.mjs';

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

/*
 *  The Fill step's questions live in the talk state option, which no route
 *  writes; Step 4 needs pictures whose file alt is empty and one whose alt
 *  is its own. Both are planted by tests/ui/fill-fixture.php over SSH and
 *  put back the same way (afterEach, whatever the test did). Only on a box
 *  tests/ui/box.mjs knows; on Playground those tests skip.
 */
const BASE = process.env.UI_BASE ?? 'http://46.225.66.194';
const FIXTURE = 'tests/ui/fill-fixture.php';
let planted = false;

function plantOnBox( env ) {
	const out = boxPhp( BASE, FIXTURE, { VGML_MODE: 'plant', ...env } );
	planted = true;
	const line = out.trim().split( '\n' ).reverse().find( ( l ) => l.startsWith( '{' ) );
	if ( ! line ) {
		throw new Error( `the fixture said nothing usable:\n${ out }` );
	}
	return JSON.parse( line );
}

function restoreBox() {
	if ( ! planted ) {
		return;
	}
	planted = false;
	const out = boxPhp( BASE, FIXTURE, { VGML_MODE: 'restore' } );
	if ( ! /restored/.test( out ) ) {
		throw new Error( `the fixture did not restore:\n${ out }` );
	}
}

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
	/*
	 *  Off the Folders screen first, always (every screen is under wp-admin,
	 *  so the old "unless on wp-admin" never left it): the app left open
	 *  polls /guide/progress, and a poll that revives a pending fit reads the
	 *  session, counts for seconds, then saves what it read -- the old turns
	 *  back over the reset below, so the turns posted after it were refused
	 *  at the cap (the upload test, 2026-09-17).
	 */
	await open( page, SCREEN.dashboard );
	// A tree the tests confirmed is opened again before anything is written over it.
	await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/unconfirm`, method: 'POST' } ).catch( () => null ), NS );
	await reset( page );
	const s = found.session;
	/*
	 *  The route takes at most the cap's assistant turns in one write; a
	 *  session found past the cap is a test's own leftover (a restore that
	 *  failed before this one), and posting it back is refused with "Every
	 *  turn of this conversation is used" -- which failed every test after
	 *  it on 2026-09-17. What fits under the cap goes back; the rest is said.
	 */
	const cap = Number( s.cap ) || Infinity;
	let assistant = 0;
	const turns = [];
	for ( const t of s.turns || [] ) {
		if ( t.role === 'assistant' && ++assistant > cap ) {
			break;
		}
		turns.push( t.role === 'assistant'
			? { say: { text: t.text, choices: t.choices || [], kind: t.kind } }
			: { said: { kind: t.kind, text: t.text, rule: t.rule } } );
	}
	if ( turns.length < ( s.turns || [] ).length ) {
		console.log( `      restore: the session found had ${ ( s.turns || [] ).length } turns, past the cap of ${ cap }; ${ turns.length } put back` );
	}
	if ( turns.length ) {
		// A refusal says why (the route's code and message), not "Object".
		await page.evaluate( ( [ ns, turns ] ) => wp.apiFetch( { path: `${ ns }/guide/turn`, method: 'POST', data: { turns } } ).catch( ( e ) => { throw new Error( `restore: ${ turns.length } turns refused: ${ e && e.code } ${ e && e.message }` ); } ), [ NS, turns ] );
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

/** The Folders screen, opened and painted. */
async function open_( page ) {
	await open( page, SCREEN.folders );
	await ready( page );
}

test.describe( 'the Folders screen', () => {

	test.afterEach( async ( { page } ) => {
		await restore( page );
		// The page first: a screen still writing (a press mid-loop) would write over what the fixture puts back.
		await page.close();
		restoreBox();
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
	 *  The progress row (S10.0; the approved mock 2026-09-16-progress-row.html):
	 *  one row under the button, the count and the bar, an estimate from what
	 *  is done, the elapsed time when there is no total, a yellow pill once
	 *  nothing has moved for 30 s. Driven two ways: the confirm pressed for
	 *  real, its route answered here (three batches of sixty, nothing reaches
	 *  the server, no planner call); then the renderer with models of its
	 *  own for the fill's bar, the stall and the open row. Mutations: the
	 *  batch count not passed in onConfirm (total dropped from reading()) ->
	 *  the bar stays open, the width assertion red; STALL_MS made an hour ->
	 *  the stall row red.
	 */
	test( 'the progress row: the confirm counts its batches, the fill its pictures, a stall its seconds', async ( { page } ) => {
		await remember( page );
		await reset( page );
		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open_( page );

		const boot = await getSession( page );
		let left = 180;
		await page.route( /\/guide\/confirm/, async ( route ) => {
			left -= 60;
			await new Promise( ( r ) => setTimeout( r, 1500 ) );
			await route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { session: boot.session, profiled: 0, charged: 0, left: Math.max( 0, left ), version: boot.version } ) } );
		} );
		await page.evaluate( () => { window.vgmlFoldersApp.state.session.profile = { folders: 180, credits: 0 }; } );

		await page.locator( '.g-step[data-step="tree"]' ).click();
		const confirm = page.locator( '.g-card[data-card="tree"] .vgml-confirm-btn' );
		await expect( confirm ).toHaveText( 'This is my tree' );
		await confirm.click();
		const row = page.locator( '.g-progress[data-row="tree"]' );
		await expect( row ).toBeVisible();
		await expect( confirm, 'the button keeps its verb and goes to work' ).toHaveText( 'This is my tree' );
		await expect( confirm ).toHaveClass( /is-working/ );
		await expect( row.locator( '.g-progress-text' ) ).toHaveText( /^Reading folders 0 of 3 batches · \d+ s$/ );
		await expect( row, 'before the first batch the bar is the sliding sliver, not a bar at zero' ).toHaveClass( /is-open/ );
		await expect( row.locator( '.g-progress-text' ) ).toHaveText( /^Reading folders 1 of 3 batches · about \d+ s left$/, { timeout: 5000 } );
		await expect( row ).toHaveAttribute( 'aria-valuenow', '1' );
		const w1 = parseFloat( await row.locator( '.g-progress-fill' ).evaluate( ( e ) => e.style.width ) );
		expect( w1, 'the bar at 1 of 3, creeping towards 2' ).toBeGreaterThanOrEqual( 33.3 );
		expect( w1 ).toBeLessThan( 63.4 );
		await expect( row ).not.toHaveClass( /is-open/ );
		await page.waitForTimeout( 1100 );
		const w2 = parseFloat( await row.locator( '.g-progress-fill' ).evaluate( ( e ) => e.style.width ) );
		expect( w2, 'the bar moves between batches' ).toBeGreaterThan( w1 );
		await expect( row.locator( '.g-progress-text' ) ).toHaveText( /^Reading folders 2 of 3 batches/, { timeout: 5000 } );
		await expect( row, 'hidden when the last batch is read' ).toBeHidden( { timeout: 8000 } );
		await page.unroute( /\/guide\/confirm/ );

		// The renderer with models of its own: the fill's bar, the stall, the open row.
		await page.evaluate( () => window.vgmlFoldersApp.setStep( 'fill' ) );
		const fill = page.locator( '.g-card[data-card="fill"] .g-progress' );
		await page.evaluate( () => window.vgmlFoldersApp.progress( 'fill', { verb: 'Filling', count: '313 of 626 pictures', done: 313, total: 626, since: Date.now() - 60000, ticked: Date.now() } ) );
		await expect( fill ).toBeVisible();
		await expect( fill.locator( '.g-progress-text' ) ).toHaveText( 'Filling 313 of 626 pictures · about 1 min left' );
		const wf = parseFloat( await fill.locator( '.g-progress-fill' ).evaluate( ( e ) => e.style.width ) );
		expect( wf ).toBeGreaterThanOrEqual( 50 );
		expect( wf ).toBeLessThan( 50.3 );
		expect( await fill.evaluate( ( e ) => e.className ) ).toBe( 'g-progress' );

		await page.evaluate( () => window.vgmlFoldersApp.progress( 'fill', { verb: 'Filling', count: '96 of 626 pictures', done: 96, total: 626, since: Date.now() - 90000, ticked: Date.now() - 48000 } ) );
		await expect( fill ).toHaveClass( /is-stalled/ );
		await expect( fill.locator( '.g-pill.is-ask' ) ).toHaveText( 'nothing moved for 48 s' );
		await expect( fill.locator( '.g-pill.is-ask' ), 'the seconds move by themselves' ).toHaveText( 'nothing moved for 49 s', { timeout: 3000 } );
		await expect( fill.locator( '.g-progress-text' ), 'no estimate on a stall' ).toHaveText( 'Filling 96 of 626 pictures' );

		await page.evaluate( () => window.vgmlFoldersApp.progress( 'fill', { verb: 'Counting', count: '1,000 pictures against 500 folders', done: 0, total: 0, since: Date.now() - 12000 } ) );
		await expect( fill ).toHaveClass( /is-open/ );
		await expect( fill ).toHaveAttribute( 'aria-busy', 'true' );
		await expect( fill.locator( '.g-progress-text' ) ).toHaveText( /^Counting 1,000 pictures against 500 folders · 1[23] s$/ );
		await page.screenshot( { path: 'tests/ui/shots/folders-progress-row.png' } );

		/*
		 *  The apply's own request (S15): the folders are being made and seeded
		 *  before the first pass, and until 2026-09-17 the row under the button
		 *  read "Filling 0 pictures so far" for as long as that took (188 s on
		 *  HEMA's 292). Pressed and not yet answered, the row says what the
		 *  request is doing, in the spec's words, with the clock; the pictures'
		 *  count takes over when the first report arrives. Mutation: the
		 *  applying branch removed from renderMove -> red ("Filling 0 pictures").
		 */
		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.session.draft = { folders: Array.from( { length: 292 }, ( _, i ) => ( { key: 'k' + i, name: 'f' + i, parent: '' } ) ) };
			app.state.applying = true;
			app.state.applyingAt = Date.now() - 4000;
			app.state.moving = null;
			app.render();
		} );
		await expect( fill.locator( '.g-progress-text' ), 'the apply pressed, no report yet: making the folders' ).toHaveText( /^Making 292 folders · [45] s$/ );
		await expect( fill ).toHaveClass( /is-open/ );
		await expect( page.locator( '.g-card[data-card="fill"] .vgml-move-btn' ) ).toHaveClass( /is-working/ );
		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.applying = false;
			app.state.session.draft = null;
			app.render();
		} );

		await page.evaluate( () => window.vgmlFoldersApp.progress( 'fill', null ) );
		await expect( fill ).toBeHidden();

		/*
		 *  The run's end into the asking state (S11, the owner's round): the row
		 *  under the button is the run's, and a run that has ended and left
		 *  questions has no row -- until 2026-09-17 renderFill returned before
		 *  the one call that hides it, and "Filling 1,000 of 1,000 · nothing
		 *  moved for 34 s" stood under a finished fill. Mutation: the hide
		 *  removed from renderFill's not-running path -> red.
		 */
		await page.evaluate( () => window.vgmlFoldersApp.progress( 'fill', { verb: 'Filling', count: '1,000 of 1,000 pictures', done: 1000, total: 1000, since: Date.now() - 50000, ticked: Date.now() - 34000 } ) );
		await expect( fill ).toBeVisible();
		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.questions = [ { id: 'r:0', kind: 'residue', count: 5, name: 'Probes', term_id: 0, text: '5 look like probes', answers: { leave: 'Leave them', 'show-me': 'Show me' }, sample: [], answered: '', result: null } ];
			app.state.moving = { running: false, moved: 500, seen: 1000, total: 1000, tally: { sure: 298, likely: 202 } };
			app.render();
		} );
		await expect( page.locator( '.g-cols' ) ).toHaveClass( /is-asking/ );
		await expect( fill, 'a run that ended into questions has no progress row' ).toBeHidden();
		await expect( page.locator( '.g-card[data-card="fill"] .g-pill[data-rounds]' ), 'one round: no rounds pill' ).toHaveCount( 0 );

		// The rounds (S10.7): a run that went twice says so, in the spec's own words; while round 2 runs the tail waits.
		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.moving = { running: false, moved: 640, seen: 1000, total: 1000, round: 2, rounds: { 1: 553, 2: 640 }, tally: { sure: 400, likely: 240, nothing: 113 } };
			app.render();
		} );
		await expect( page.locator( '.g-card[data-card="fill"] .g-pill[data-rounds]' ) ).toHaveText( 'round 1: 553 placed · round 2: 640 · 113 to sort' );
		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.moving = { running: true, moved: 590, seen: 700, total: 1000, round: 2, rounds: { 1: 553 }, tally: { sure: 380, likely: 210, nothing: 60 } };
			app.state.session.applyWas = app.state.session.apply;
			app.state.session.apply = app.state.moving;
			app.render();
		} );
		await expect( page.locator( '.g-card[data-card="fill"] .g-pill[data-rounds]' ) ).toHaveText( 'round 1: 553 placed · round 2: 590' );
		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.session.apply = app.state.session.applyWas;
			delete app.state.session.applyWas;
			app.state.questions = [];
			app.state.moving = null;
			app.render();
		} );
	} );

	/*
	 *  A site that sells (S10.8; the approved mock 2026-09-18-fill-by-product.html).
	 *  The Fill step's pills read by product · by evidence · to sort from the
	 *  report's own tally whenever it counted a product placement, in the
	 *  running state and in the asking state; a tally with none reads as
	 *  before. Driven from the model, as the rounds are. Mutation: byProduct
	 *  made to return false -> red ("312 by product" missing).
	 */
	test( 'the Fill pills on a site that sells: by product · by evidence · to sort, from the tally', async ( { page } ) => {
		await remember( page );
		await reset( page );
		await open_( page );
		await page.evaluate( () => window.vgmlFoldersApp.setStep( 'fill' ) );
		const pills = page.locator( '.g-card[data-card="fill"] .g-pills .g-pill' );

		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.session.applyWas = app.state.session.apply;
			app.state.moving = { running: true, moved: 386, seen: 405, total: 626, tally: { product: 312, fits: 386, siblings: 0, nothing: 19, sure: 380, likely: 6 } };
			app.state.session.apply = app.state.moving;
			app.render();
		} );
		await expect( pills, 'running: three pills, the mock\'s' ).toHaveText( [ '312 by product', '74 by evidence', '19 to sort' ] );

		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.session.apply = app.state.session.applyWas;
			app.state.questions = [ { id: 'r:0', kind: 'residue', count: 5, name: 'Probes', term_id: 0, text: '5 look like probes', answers: { leave: 'Leave them', 'show-me': 'Show me' }, sample: [], answered: '', result: null } ];
			app.state.fill.unfiled = 85;
			app.state.moving = { running: false, moved: 541, seen: 626, total: 626, tally: { product: 412, fits: 541, siblings: 0, nothing: 85, sure: 500, likely: 41 } };
			app.render();
		} );
		await expect( pills, 'ended into a question: by product · by evidence · the question · to sort' ).toHaveText( [ '412 by product', '129 by evidence', '1 question', '85 to sort' ] );

		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.moving = { running: false, moved: 500, seen: 1000, total: 1000, tally: { product: 0, sure: 298, likely: 202 } };
			app.render();
		} );
		await expect( pills.first(), 'no product placement: the pills as before' ).toHaveText( '500 placed' );

		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			delete app.state.session.applyWas;
			app.state.questions = [];
			app.state.moving = null;
			app.state.fill.unfiled = 0;
			app.render();
		} );
	} );

	/*
	 *  The model's two pills (S18, Nathan's ok on the mock 2026-09-18-model-words,
	 *  option A without the word AI, 2026-09-18): after a run, "N in doubt" from the tally's doubt,
	 *  between the questions and the leftovers; before a run, the quiet word
	 *  "estimate" at the end of the dry count's pills, because that count never
	 *  asked the model (tally.rules_only). Neither pill when there is nothing to
	 *  say. Mutation: the doubt pill removed from renderFill -> red.
	 */
	test( 'the Fill pills with the model in: in doubt after a run, estimate on the dry count', async ( { page } ) => {
		await remember( page );
		await reset( page );
		await open_( page );
		await page.evaluate( () => window.vgmlFoldersApp.setStep( 'fill' ) );
		const pills = page.locator( '.g-card[data-card="fill"] .g-pills .g-pill' );

		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.session.applyWas = app.state.session.apply;
			app.state.questions = [ { id: 'r:0', kind: 'residue', count: 5, name: 'Probes', term_id: 0, text: '5 look like probes', answers: { leave: 'Leave them', 'show-me': 'Show me' }, sample: [], answered: '', result: null } ];
			app.state.fill.unfiled = 61;
			app.state.moving = { running: false, moved: 908, seen: 1000, total: 1000, tally: { product: 0, fits: 908, siblings: 0, nothing: 92, sure: 731, likely: 177, agree: 700, doubt: 31 } };
			app.render();
		} );
		await expect( pills, 'ended: placed · sure · likely · question · in doubt · in no folder' ).toHaveText( [ '908 placed', '731 sure', '177 likely', '1 question', '31 in doubt', '61 in no folder' ] );

		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.moving.tally.doubt = 0;
			app.render();
		} );
		await expect( pills, 'no doubts: no pill for them' ).toHaveText( [ '908 placed', '731 sure', '177 likely', '1 question', '61 in no folder' ] );

		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			app.state.session.apply = app.state.session.applyWas;
			app.state.questions = [];
			app.state.moving = null;
			app.state.fill.unfiled = 0;
			// The dry branch reads the tree view's draft: one folder of the draft's own, taken away below.
			app.state.draftWas = app.state.session.draft;
			app.state.session.draft = { folders: [ { key: 'zzpill', name: 'zzPill', parent: '', count: 0 } ] };
			app.view().setDraft( app.state.session.draft );
			app.state.fill.unfiled = 61; // Not done: a done fill shows the library's own counts, not the dry run's.
			app.state.fit = { tally: { fits: 939, siblings: 0, nothing: 61, sure: 800, likely: 139, rules_only: true } };
			app.render();
		} );
		await expect( pills, 'the dry count: would be placed · sure · likely · not placed · estimate' ).toHaveText( [ '939 would be placed', '800 sure', '139 likely', '61 not placed', 'estimate' ] );
		await expect( pills.last() ).toHaveClass( /is-quiet/ );

		await page.evaluate( () => {
			const app = window.vgmlFoldersApp;
			delete app.state.session.applyWas;
			app.state.fit = null;
			app.state.fill.unfiled = 0;
			app.state.session.draft = app.state.draftWas || null;
			delete app.state.draftWas;
			app.view().setDraft( app.state.session.draft );
			app.render();
		} );
	} );

	/*
	 *  The rail turned round on a site that sells: Tree, Fill, Describe, Alt
	 *  text, Rename, the one quiet line under it, and "on products" beside the
	 *  title -- read off a product the fixture makes with one real picture as
	 *  its featured image, and gone with it. The box only: Playground has no
	 *  product type. Mutation: the reorder removed from vergeml_folders_page
	 *  -> red (Describe first).
	 */
	test( 'the rail on a site that sells: Tree first, the line under it, on products in the head', async ( { page } ) => {
		test.skip( ! boxFor( BASE ), 'planted on the box over SSH' );
		await remember( page );
		await reset( page );
		const f = plantOnBox( { VGML_COUNT: 0, VGML_LEFT: 0, VGML_PRODUCT: 1 } );
		test.skip( ! f.product, 'no product type on this site' );
		await open_( page );
		await expect( page.locator( '.g-rail .g-step' ) ).toHaveText( [ 'Tree', 'Fill', 'Describe', 'Alt text', 'Rename' ] );
		await expect( page.locator( '.g-why' ) ).toHaveText( 'This site sells: the products place their pictures first. Describing is for what nothing placed.' );
		await expect( page.locator( '.vgml-folders-facts [data-fact="on_products"]' ) ).toHaveText( '1 on products' );
		await expect( page.locator( '.g-step.is-current' ), 'lands on Tree, not Describe' ).toHaveText( /Tree|Fill/ );
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
			// The Tree step's dry run speaks in the conditional (b88ff00): what a fill of this draft would do.
			await expect( page.locator( '.g-card[data-card="tree"] .g-card-head .g-pill' ) ).toHaveText( [ /^\d+ folders$/, /^\d[\d,.]* would be placed$/, /^\d[\d,.]* would stay unfiled$/ ] );
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
		test.setTimeout( 240_000 );
		await remember( page );
		await plant( page, false );
		if ( boxFor( BASE ) ) {
			// A Fill step with no open question and three pictures to sort: the box carries the walk's thirty questions since 2026-09-16, and with questions open the fill's button is not the confirm.
			plantOnBox( { VGML_COUNT: 0, VGML_LEFT: 3 } );
		}
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

				/*
				 *  S10.4 (2026-09-17): the fold is an icon pair first in the tree's
				 *  head, in the viewport at either size without scrolling; a closed
				 *  parent under the pointer shows its children as chips in the
				 *  viewport, and stays closed.
				 */
				if ( 'tree' === step ) {
					const pair = page.locator( '#vgml-folders .vgml-tv-head > :first-child' );
					await expect( pair ).toHaveClass( /vgml-tv-foldpair/ );
					const box = await pair.boundingBox();
					expect( box && box.y >= 0 && box.y + box.height <= height, `${ width }×${ height }: the fold pair in the viewport (${ JSON.stringify( box ) })` ).toBe( true );
					const closed = page.locator( '#vgml-folders .vgml-node[aria-expanded="false"]' ).first();
					await closed.locator( '> .vgml-row' ).hover();
					const peek = closed.locator( '.vgml-tv-peek .vgml-sib' );
					await expect( peek.first() ).toBeVisible( { timeout: 2000 } );
					const chips = await peek.count();
					const under = await closed.getAttribute( 'aria-expanded' );
					const card = await closed.locator( '.vgml-tv-hover' ).boundingBox();
					expect( chips, `${ width }×${ height }: the closed parent's children as chips` ).toBeGreaterThan( 0 );
					expect( under, 'the row stays closed under the pointer' ).toBe( 'false' );
					expect( card && card.y + card.height <= height, `${ width }×${ height }: the card in the viewport (${ JSON.stringify( card ) })` ).toBe( true );
					await page.screenshot( { path: `tests/ui/shots/folders-tree-peek-${ width }x${ height }.png` } );
					await page.mouse.move( 0, 0 );
				}
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
		// The dry count is the rules' alone (S18): the tally says so, and the head carries no pill for it until the copy exists.
		expect( r.fit.tally.rules_only, 'the dry count says it is rules-only' ).toBe( true );

		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );

		// The head: folders, would be placed, would stay unfiled -- the dry run's numbers, said as a prediction, as pills.
		const head = page.locator( '.g-card[data-card="tree"] .g-card-head .g-pill' );
		await expect( head ).toHaveText( [ `${ r.draft.folders.length } folders`, `${ ( r.fit.looked - unplaced ).toLocaleString( 'en-US' ) } would be placed`, `${ unplaced.toLocaleString( 'en-US' ) } would stay unfiled` ] );

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

	/*
	 *  Click-to-add (Nathan, mid-walk 2026-09-15): the + on a row, the "New
	 *  folder" at the foot. Typed in place; a path reaches deeper. The paste's
	 *  path to the session -- the draft to the turn route for its counts, no
	 *  model asked -- so no POST to token / stream / propose.
	 */
	test( 'a folder is added by clicking: + on a row, a path for deeper, New folder at the foot; no model route', async ( { page } ) => {
		test.setTimeout( 180_000 );
		await remember( page );
		const boot = await plant( page, true );
		expect( boot.nodes.some( ( n ) => /add probe|top probe/i.test( n.name ) ), 'the box holds no folder named Add probe or Top probe' ).toBe( false );
		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );

		const model = [];
		page.on( 'request', ( r ) => {
			if ( 'POST' === r.method() && /\/guide\/(token|stream|propose)(\?|$)/.test( r.url() ) ) {
				model.push( r.url() );
			}
		} );

		// The + on a branch row, shown on hover: an editor one level under it; a path makes two folders, the first under the branch.
		const branch = page.locator( '.g-tree .vgml-node[data-key][aria-level="1"][aria-expanded]' ).first();
		const branchName = ( await branch.locator( '.vgml-name' ).innerText() ).trim();
		await branch.locator( '.vgml-row' ).hover();
		await expect( branch.locator( '.vgml-add' ) ).toBeVisible();
		await expect( branch.locator( '.vgml-add' ) ).toHaveAttribute( 'aria-label', `Add a folder inside ${ branchName }` );
		await branch.locator( '.vgml-add' ).click();
		const editor = page.locator( '.g-tree .vgml-node.is-adding .vgml-editor' );
		await expect( editor ).toBeFocused();
		await expect( page.locator( '.g-tree .vgml-node.is-adding' ) ).toHaveAttribute( 'aria-level', '2' );
		await editor.fill( 'Add probe > Deeper' );
		await page.keyboard.press( 'Enter' );

		await expect( page.locator( '.g-change .vgml-msg.is-edit' ).last() ).toContainText( `Added Deeper under ${ branchName }` );
		const probe = page.locator( '.g-tree .vgml-node.is-new' ).filter( { has: page.locator( '.vgml-name', { hasText: /^Add probe$/ } ) } );
		await expect( probe ).toHaveCount( 1 );
		await expect( probe ).toHaveAttribute( 'aria-level', '2' );
		// Deeper is under Add probe: a row at level 3, or a new chip on Add probe's line.
		await expect( page.locator( '.g-tree .vgml-node[aria-level="3"].is-new .vgml-name, .g-tree .vgml-tv-sibs .vgml-sib.is-new' ).filter( { hasText: 'Deeper' } ) ).toHaveCount( 1 );
		await expect( page.locator( '.g-tree .vgml-editor' ) ).toHaveCount( 0 );

		// The foot: a top-level folder.
		await page.locator( '.g-tree .vgml-tv-add .vgml-add-top' ).click();
		await expect( page.locator( '.g-tree .vgml-node.is-adding' ) ).toHaveAttribute( 'aria-level', '1' );
		await page.locator( '.g-tree .vgml-node.is-adding .vgml-editor' ).fill( 'Top probe' );
		await page.keyboard.press( 'Enter' );
		await expect( page.locator( '.g-tree .vgml-node[aria-level="1"].is-new .vgml-name' ).filter( { hasText: /^Top probe$/ } ) ).toHaveCount( 1 );
		await expect( page.locator( '.g-change .vgml-msg.is-edit' ).last() ).toContainText( 'Added Top probe' );

		// Escape drops an empty one and changes nothing.
		await page.locator( '.g-tree .vgml-tv-add .vgml-add-top' ).click();
		await page.keyboard.press( 'Escape' );
		await expect( page.locator( '.g-tree .vgml-editor' ) ).toHaveCount( 0 );

		// The session holds all three as new folders in their places, the dry run answered, the confirm is offered.
		const confirm = page.locator( '.g-card[data-card="tree"] .vgml-confirm-btn' );
		await expect( confirm ).toBeEnabled( { timeout: 90000 } );
		const s = await getSession( page );
		const byName = Object.fromEntries( s.session.draft.folders.map( ( f ) => [ f.name, f ] ) );
		const branchKey = s.session.draft.folders.find( ( f ) => f.name === branchName ).key;
		expect( byName[ 'Add probe' ] && byName[ 'Add probe' ].term_id, 'Add probe is new' ).toBeNull();
		expect( byName[ 'Add probe' ].parent ).toBe( branchKey );
		expect( byName[ 'Deeper' ].parent ).toBe( byName[ 'Add probe' ].key );
		expect( byName[ 'Top probe' ].parent ).toBe( '' );
		expect( s.session.fit, 'the turn route ran the dry run over the add' ).not.toBeNull();
		expect( model, 'no model route fired' ).toEqual( [] );
		await page.screenshot( { path: 'tests/ui/shots/folders-add-by-click.png' } );
	} );

	test( 'the old guide address lands here', async ( { page } ) => {
		await remember( page );
		await plant( page, false );
		await page.goto( '/wp-admin/admin.php?page=media-guide', { waitUntil: 'domcontentloaded' } );
		await expect( page ).toHaveURL( /page=media-librarian/ );
	} );

	/*
	 *  Step 3 asking, and done (B.4, the approved fill mock). Two questions
	 *  planted on eight real unfiled pictures, every other unfiled picture
	 *  parked in To sort: the page opens on Fill with the two cards to the
	 *  tree's right; New folder makes the folder in the tree and the card says
	 *  so; Leave the rest answers what is left with leave -- 0 open, 0 in no
	 *  folder, the done state. Every number asserted is the fixture's.
	 *
	 *  Mutation: drop `unfiled === 0` from fillDone() in js/vergeml-folders.js
	 *  and the next test (three left unparked) goes red.
	 */
	test( 'the Fill step asks; an answer makes the folder; Leave the rest ends it done', async ( { page } ) => {
		test.skip( ! boxFor( BASE ), 'the questions are planted on the box over SSH' );
		test.setTimeout( 300_000 );
		await remember( page );
		await plant( page, false );
		const f = plantOnBox( { VGML_LEFT: 0 } );
		expect( f.open, 'two questions planted' ).toBe( 2 );
		expect( f.unfiled, 'the questions\' eight pictures are the only ones in no folder' ).toBe( 8 );

		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );

		// Opens on Fill: the questions are the step. The cards arrive after the paint (one read-only request).
		await expect( page.locator( '#vgml-folders' ) ).toHaveAttribute( 'data-step', 'fill' );
		await expect( page.locator( '.g-qs .g-q' ) ).toHaveCount( 2 );
		await expect( page.locator( '.g-cols' ) ).toHaveClass( /is-asking/ );
		await expect( page.locator( '.g-card[data-card="fill"] .g-card-head .g-pill' ) ).toHaveText( [ /^\d[\d,.]* placed$/, '2 questions', '8 in no folder' ] );
		await expect( page.locator( '.g-card[data-card="fill"] .g-card-head .g-pill.is-ask' ) ).toHaveText( '2 questions' );

		// The cards: the sentence, the group's pictures, the answers as buttons with the engine's first and tinted.
		const cards = page.locator( '.g-qs .g-q' );
		await expect( cards.nth( 0 ).locator( '.g-q-text' ) ).toHaveText( '5 look like spec probes' );
		await expect( cards.nth( 0 ).locator( '.g-q-strip img' ) ).toHaveCount( 5 );
		await expect( cards.nth( 0 ).locator( '.g-answer' ) ).toHaveText( [ 'New folder Spec probe', 'Leave them', 'Show me' ] );
		await expect( cards.nth( 0 ).locator( '.g-answer' ).first() ).toHaveClass( /is-first/ );
		await expect( cards.nth( 1 ).locator( '.g-q-text' ) ).toHaveText( '3 with nothing to go on' );
		await expect( cards.nth( 1 ).locator( '.g-q-strip img' ) ).toHaveCount( 3 );
		await expect( cards.nth( 1 ).locator( '.g-answer' ) ).toHaveText( [ 'Leave them', 'Show me' ] );
		await expect( page.locator( '.g-qs .g-leave-rest' ) ).toHaveText( 'Leave the rest' );
		// No thumbnail is a broken image.
		expect( await page.$$eval( '.g-qs .g-q-strip img', ( imgs ) => imgs.every( ( i ) => i.complete && i.naturalWidth > 0 ) ), 'every thumbnail loaded' ).toBe( true );

		// The tree left, the questions right at 1600; one column under 1000px.
		const side = await page.evaluate( () => ( {
			tree: document.querySelector( '.g-card[data-card="fill"]' ).getBoundingClientRect(),
			qs: document.querySelector( '.g-qs' ).getBoundingClientRect(),
		} ) );
		expect( side.qs.left, 'the questions sit to the tree\'s right' ).toBeGreaterThan( side.tree.right );

		for ( const [ width, height ] of SIZES ) {
			await page.setViewportSize( { width, height } );
			await page.waitForTimeout( 300 );
			const words = await page.$eval( '.wrap.vgml-librarian', WORDS );
			console.log( `      asking at ${ width }×${ height }: ${ words.without } words without the tree, ${ words.with } with it` );
			expect( words.without, `asking at ${ width }×${ height }: ${ words.without } words without the tree` ).toBeLessThanOrEqual( BUDGET );
			const doc = await page.evaluate( () => ( { w: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth } ) );
			expect( doc.w, 'no horizontal scroll' ).toBeLessThanOrEqual( doc.cw );
			await page.screenshot( { path: `tests/ui/shots/folders-fill-asking-${ width }x${ height }.png` } );
		}
		await page.setViewportSize( { width: 1600, height: 1000 } );

		// New folder: the tree gains Spec probe, marked new, and the card says what the answer did.
		const a = await Promise.all( [
			page.waitForResponse( ( res ) => /\/guide\/answer/.test( res.url() ) ),
			cards.nth( 0 ).locator( '.g-answer[data-answer="new-folder"]' ).click(),
		] );
		expect( a[ 0 ].status(), 'the answer went through' ).toBe( 200 );
		const answered = await a[ 0 ].json();
		expect( answered.result.moved ).toBe( 5 );
		expect( answered.result.made ).toBeGreaterThan( 0 );
		// The result line, and -- the person chose the folder -- the way to review those five (C.3).
		await expect( page.locator( '.g-qs .g-q.is-answered .g-q-result' ) ).toHaveText( 'Spec probe made · 5 moved · 5 by you · review' );
		expect( await page.locator( '.g-qs .g-q.is-answered .g-q-review' ).getAttribute( 'href' ) ).toMatch( /upload\.php\?mode=list&media_category=by_you$/ );
		await expect( page.locator( '.g-tree .vgml-node.is-new .vgml-name' ).filter( { hasText: 'Spec probe' } ) ).toHaveCount( 1 );
		await expect( page.locator( '.g-tree .vgml-node.is-new' ).filter( { hasText: 'Spec probe' } ).locator( '.vgml-tag' ) ).toHaveText( 'new' );
		await expect( page.locator( '.g-card[data-card="fill"] .g-card-head .g-pill.is-ask' ) ).toHaveText( '1 question' );
		await expect( page.locator( '.g-card[data-card="fill"] .g-card-head .g-pill' ).last() ).toHaveText( '3 in no folder' );
		await expect( page.locator( '.g-qs .g-q:not(.is-answered)' ) ).toHaveCount( 1 );

		// Leave the rest: the three land in To sort, never in nothing -- and the step is done.
		const l = await Promise.all( [
			page.waitForResponse( ( res ) => /\/guide\/answer/.test( res.url() ) ),
			page.locator( '.g-qs .g-leave-rest' ).click(),
		] );
		expect( l[ 0 ].status() ).toBe( 200 );
		const left = await l[ 0 ].json();
		expect( left.result.answered ).toBe( 1 );
		expect( left.result.moved ).toBe( 3 );
		expect( left.status.open ).toBe( 0 );
		expect( left.status.unfiled, '0 in no folder' ).toBe( 0 );

		await expect( page.locator( '.g-cols' ) ).toHaveClass( /is-done/ );
		await expect( page.locator( '.g-qs' ) ).toBeHidden();
		await expect( page.locator( '.g-card[data-card="fill"] .g-card-head .g-pill' ) ).toHaveText( [ /^\d[\d,.]* in folders$/, '0 in no folder' ] );
		await expect( page.locator( '.g-card[data-card="fill"] .g-move .g-quiet' ).filter( { hasText: 'Next: Alt text' } ), 'alt text is the quiet way on, not the only button' ).toBeVisible();
		// The fixture plants the questions on a tree nobody confirmed: done, the step's one primary is the confirm (a fill runs against a confirmed tree and nothing else), and the fill button is not offered.
		await expect( page.locator( '.g-card[data-card="fill"] .g-move .vgml-btn-primary' ), 'an unconfirmed tree offers its confirm, not a second fill' ).toHaveText( 'This is my tree' );
		await expect( page.locator( '.g-card[data-card="fill"] .g-move .vgml-confirm-btn' ) ).toBeEnabled();
		await expect( page.locator( '.g-card[data-card="fill"] .g-move .vgml-move-btn' ) ).toHaveCount( 0 );
		await expect( page.locator( '.g-step[data-step="fill"]' ) ).toHaveClass( /is-done/ );
		await expect( page.locator( '.g-step[data-step="fill"]' ) ).toHaveClass( /is-current/ );
		await expect( page.locator( '.g-tree .vgml-node.is-new .vgml-name' ).filter( { hasText: 'Spec probe' } ) ).toHaveCount( 1 );
		// A done tree is read as totals: every parent closed.
		expect( await page.locator( '.g-tree .vgml-node[aria-expanded="true"]' ).count(), 'no parent open' ).toBe( 0 );
		expect( await page.locator( '.g-tree .vgml-node[aria-expanded="false"]' ).count(), 'the parents are there, closed' ).toBeGreaterThan( 0 );

		for ( const [ width, height ] of SIZES ) {
			await page.setViewportSize( { width, height } );
			await page.waitForTimeout( 300 );
			const words = await page.$eval( '.wrap.vgml-librarian', WORDS );
			console.log( `      done at ${ width }×${ height }: ${ words.without } words without the tree, ${ words.with } with it` );
			expect( words.without, `done at ${ width }×${ height }: ${ words.without } words without the tree` ).toBeLessThanOrEqual( BUDGET );
			await page.screenshot( { path: `tests/ui/shots/folders-fill-done-${ width }x${ height }.png` } );
		}

		// A reload reads the same done state from the page's own data (the tree is not confirmed here, so the page lands on Tree; Fill is one press).
		await page.setViewportSize( { width: 1600, height: 1000 } );
		await page.reload( { waitUntil: 'domcontentloaded' } );
		await ready( page );
		await expect( page.locator( '.g-step[data-step="fill"]' ) ).toHaveClass( /is-done/ );
		await page.locator( '.g-step[data-step="fill"]' ).click();
		await expect( page.locator( '.g-cols' ) ).toHaveClass( /is-done/ );
		await expect( page.locator( '.g-card[data-card="fill"] .g-card-head .g-pill' ) ).toHaveText( [ /^\d[\d,.]* in folders$/, '0 in no folder' ] );
		await expect( page.locator( '.g-tree .vgml-node.is-new .vgml-name' ).filter( { hasText: 'Spec probe' } ) ).toHaveCount( 1 );
	} );

	test( 'no open question and three pictures in no folder is not done', async ( { page } ) => {
		test.skip( ! boxFor( BASE ), 'planted on the box over SSH' );
		test.setTimeout( 300_000 );
		await remember( page );
		await plant( page, false );
		const f = plantOnBox( { VGML_LEFT: 3 } );
		expect( f.unfiled ).toBe( 11 );

		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );
		await expect( page.locator( '.g-qs .g-q' ) ).toHaveCount( 2 );

		const l = await Promise.all( [
			page.waitForResponse( ( res ) => /\/guide\/answer/.test( res.url() ) ),
			page.locator( '.g-qs .g-leave-rest' ).click(),
		] );
		const left = await l[ 0 ].json();
		expect( left.status.open ).toBe( 0 );
		expect( left.status.unfiled ).toBe( 3 );
		expect( left.status.done ).toBe( false );

		await expect( page.locator( '.g-qs' ) ).toBeHidden();
		await expect( page.locator( '.g-card[data-card="fill"] .g-card-head .g-pill' ).last() ).toHaveText( '3 in no folder' );
		await expect( page.locator( '.g-cols' ) ).not.toHaveClass( /is-done/ );
		await expect( page.locator( '.g-card[data-card="fill"] .g-move .vgml-btn-primary' ) ).not.toHaveText( 'Next: Alt text' );
		await expect( page.locator( '.g-step[data-step="fill"]' ) ).not.toHaveClass( /is-done/ );
	} );

	/*
	 *  Step 4 (B.5): the catalogue's alt onto every picture that has none, and
	 *  never onto one that has. Three described pictures with their file alt
	 *  cleared and a fourth given an alt of its own, by the fixture; the press
	 *  writes the three and leaves the fourth word for word. Step 5 is on the
	 *  rail, says Not available yet, and its button is disabled.
	 *
	 *  Mutation: drop `AND ( alt.meta_id IS NULL OR alt.meta_value = '' )` from
	 *  vergeml_ai_alt_pending_from() in core/ai.php -> the fourth's assertion red.
	 */
	test( 'Step 4 writes the catalogue\'s alt onto pictures with none and never overwrites; Step 5 is gated', async ( { page } ) => {
		test.skip( ! boxFor( BASE ), 'planted on the box over SSH' );
		test.setTimeout( 300_000 );
		await remember( page );
		await plant( page, false );
		const f = plantOnBox( { VGML_LEFT: 0, VGML_ALT: 1 } );
		expect( f.alt.cleared.length ).toBe( 3 );
		expect( f.alt.kept ).toBeGreaterThan( 0 );

		const alt = ( id ) => page.evaluate( ( i ) => wp.apiFetch( { path: `/wp/v2/media/${ i }?context=edit` } ).then( ( m ) => m.alt_text ), id );
		const remaining = () => page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/ai-alt` } ).then( ( r ) => r.remaining ), NS );

		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );
		const before = await remaining();
		expect( before, 'the three cleared pictures are pending' ).toBeGreaterThanOrEqual( 3 );
		for ( const id of f.alt.cleared ) {
			expect( await alt( id ) ).toBe( '' );
		}
		expect( await alt( f.alt.kept ) ).toBe( f.alt.kept_alt );

		await page.evaluate( () => window.vgmlFoldersApp.setStep( 'alt' ) );
		await expect( page.locator( '#vgml-folders' ) ).toHaveAttribute( 'data-step', 'alt' );
		await expect( page.locator( '.g-card[data-card="alt"] .g-card-head .g-pill' ).first() ).toHaveText( /^\d[\d,.]* without alt text$/ );
		await expect( page.locator( '.g-card[data-card="alt"] .g-line' ) ).toHaveText( 'Never replaces one you have.' );
		const button = page.locator( '.g-card[data-card="alt"] .vgml-alt-btn' );
		await expect( button ).toHaveText( `Write alt text for ${ before.toLocaleString( 'en-US' ) }` );
		await expect( page.locator( '.g-card[data-card="alt"] .g-move .g-pill' ) ).toHaveText( '0 credits' );
		await page.screenshot( { path: 'tests/ui/shots/folders-alt-planted.png' } );

		const model = [];
		page.on( 'request', ( r ) => {
			if ( 'POST' === r.method() && /\/(ai-index|ai-run|guide\/(turn|token|stream))(\?|$)/.test( r.url() ) ) {
				model.push( r.url() );
			}
		} );
		const w = await Promise.all( [
			page.waitForResponse( ( res ) => /\/ai-alt/.test( res.url() ) && 'POST' === res.request().method() ),
			button.click(),
		] );
		expect( w[ 0 ].status() ).toBe( 200 );
		await expect( page.locator( '.g-card[data-card="alt"] .vgml-alt-btn' ) ).not.toHaveText( /^Write/, { timeout: 60000 } ).catch( () => null );
		await page.waitForFunction( () => ! window.vgmlFoldersApp.state.altWriting, null, { timeout: 90000 } );

		// First the one thing the step must never do: the alt somebody wrote, word for word.
		expect( await alt( f.alt.kept ), 'the alt somebody wrote is untouched' ).toBe( f.alt.kept_alt );
		for ( const id of f.alt.cleared ) {
			expect( await alt( id ), `${ id } carries an alt now` ).not.toBe( '' );
		}
		expect( await remaining(), '0 left to write' ).toBe( 0 );
		expect( model, 'no describe and no model route: a copy from the catalogue' ).toEqual( [] );
		await expect( page.locator( '.g-card[data-card="alt"] .vgml-alt-btn' ) ).toHaveCount( 0 );
		await expect( page.locator( '.g-card[data-card="alt"] .g-move .vgml-btn-primary' ) ).toHaveText( 'Next: Rename' );
		await page.screenshot( { path: 'tests/ui/shots/folders-alt-written.png' } );

		// Step 5: on the rail, gated, and says so.
		await page.locator( '.g-step[data-step="rename"]' ).click();
		await expect( page.locator( '#vgml-folders' ) ).toHaveAttribute( 'data-step', 'rename' );
		await expect( page.locator( '.g-card[data-card="rename"] .g-card-head .g-pill' ) ).toHaveText( 'Not available yet' );
		await expect( page.locator( '.g-card[data-card="rename"] .g-line' ) ).toHaveText( 'Renames a file to what it shows and keeps every link to it working. Coming after the folders.' );
		await expect( page.locator( '.g-card[data-card="rename"] .vgml-btn-primary' ) ).toHaveText( 'Rename files' );
		await expect( page.locator( '.g-card[data-card="rename"] .vgml-btn-primary' ) ).toBeDisabled();
	} );

	/*
	 *  The word on a picture (B.5, spec §3): a pill next to the folder on the
	 *  media list's row, and beside "Why is it here" in the modal. The box
	 *  holds no fill that was not undone, so the fixture marks one filed
	 *  picture as placed by hand: "by you" on its row and from the route.
	 *  (sure / likely come from a fill's own moves rows: proved in
	 *  tests/tree/filing-trail.php's outcomes and the modal test in
	 *  modes.spec.mjs, which fulfils the route with "sure".)
	 */
	test( 'the word on a picture: by you on the list row and from the why route', async ( { page } ) => {
		test.skip( ! boxFor( BASE ), 'planted on the box over SSH' );
		test.setTimeout( 300_000 );
		await remember( page );
		const f = plantOnBox( { VGML_LEFT: 0 } );
		expect( f.word.id, 'a filed picture to mark' ).toBeGreaterThan( 0 );

		const answer = await page.evaluate( ( [ ns, id ] ) => wp.apiFetch( { path: `${ ns }/librarian-why/${ id }` } ), [ NS, f.word.id ] );
		expect( answer.confidence, 'the route says by you' ).toBe( 'by you' );
		expect( answer.word ).toBe( 'by you' );

		await page.setViewportSize( { width: 1600, height: 900 } );
		await page.goto( `/wp-admin/upload.php?mode=list&media_category=${ encodeURIComponent( f.word.folder ) }`, { waitUntil: 'domcontentloaded' } );
		await page.waitForSelector( '#the-list', { timeout: 30000 } );
		const row = page.locator( `#the-list tr#post-${ f.word.id }` );
		await expect( row ).toHaveCount( 1 );
		await expect( row.locator( '.filename .vgml-word' ) ).toHaveText( 'by you' );
		await expect( row.locator( '.filename .vgml-word' ) ).toHaveClass( /is-by-you/ );
		// After the folder, on the one line.
		const line = await row.locator( '.filename' ).innerText();
		expect( line.indexOf( 'by you' ) ).toBeGreaterThan( line.indexOf( ' · ' ) );
		const heights = await row.evaluate( ( tr ) => ( { row: tr.getBoundingClientRect().height, line: tr.querySelector( '.filename' ).getBoundingClientRect().height } ) );
		expect( heights.line, 'the pill adds no second line' ).toBeLessThan( 40 );
		await row.screenshot( { path: 'tests/ui/shots/list-row-by-you.png' } );
	} );

	/*
	 *  One press, one honest answer (C.3). Thirty questions -- planted on the
	 *  box by the fixture, or at boot on Playground (tools/play.mjs --plant 30)
	 *  -- with an either/or card and a small-groups card among them, the
	 *  first on fifty pictures (Nathan's 61 towers). Then:
	 *
	 *    - a 409 from guide/answer lands on the card, not in the hidden change
	 *      line; the buttons come back; the card is the same node;
	 *    - the answer response carries no questions (the screen holds them);
	 *    - the answered card is patched in place, the next card appended, the
	 *      tree rendered once (the rows added to .vgml-list equal the rows on
	 *      it) and its node not re-appended into the slot;
	 *    - "Show me" on fifty pictures shows 48 and says "2 more";
	 *    - the keys: Enter opens the strip, 1 answers;
	 *    - a timing row for this site: the answer's handler ms and queries from
	 *      tests/perf/mu-perf.php (on the box; on Playground with --perf), and
	 *      the wall clock. The box's wall clock is the box's (2.9 s, 36
	 *      plugins); Playground's handler ms is the plugin's own cost.
	 *
	 *  Mutations: put `'questions' => vergeml_talk_questions()` back into the
	 *  answer route -> the no-questions row red; put `dom.slots.fill.appendChild(
	 *  dom.tree )` back unguarded in renderFill -> the re-append row red; drop
	 *  the `quietly` from setNewIds in refreshTree -> the one-render row red;
	 *  drop wp_defer_term_counting from vergeml_talk_answer -> the query row red.
	 */
	test( 'one press, one honest answer: a 409 on the card, a slim response, one tree render, the keys, a timing row', async ( { page } ) => {
		test.setTimeout( 300_000 );
		const box = boxFor( BASE );
		await remember( page );
		await plant( page, false );
		if ( box ) {
			const f = plantOnBox( { VGML_COUNT: 30, VGML_Q1: 50 } );
			expect( f.open, 'thirty questions planted' ).toBe( 30 );
			expect( f.either.length, 'an either/or card among them' ).toBe( 2 );
		}
		const qs = await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/questions` } ), NS );
		const open = ( qs.questions || [] ).filter( ( q ) => ! q.answered );
		test.skip( ! box && open.length !== 30, `Playground: boot it with tools/play.mjs --plant 30 --perf (${ open.length } open questions found)` );
		expect( open.length ).toBe( 30 );
		expect( open[ 0 ].count, 'the first question is the fifty-picture one' ).toBe( 50 );
		const either = open.find( ( q ) => 'either' === q.kind );
		const more = open.find( ( q ) => /more, in small groups$/.test( q.text ) );
		expect( either, 'an either/or question' ).toBeTruthy();
		expect( more, 'a small-groups card' ).toBeTruthy();
		expect( either.text ).toMatch( /^4 pictures: .+ or .+\?$/ );
		expect( Object.values( either.answers ) ).toEqual( [ expect.stringMatching( /^Put in / ), expect.stringMatching( /^Put in / ), 'Split them by best score', 'Leave them', 'Let me look' ] );

		const problems = [];
		page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );
		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open_( page );

		const cards = page.locator( '.g-qs .g-q' );
		await expect( cards ).toHaveCount( 3 );
		await expect( page.locator( '.g-card[data-card="fill"] .g-card-head .g-pill.is-ask' ) ).toHaveText( '30 questions' );
		await expect( page.locator( '.g-qs .g-qs-foot .g-pill' ) ).toHaveText( '27 more' );
		const firstCard = await cards.nth( 0 ).elementHandle();

		// A 409, intercepted: its text on the card, the buttons back, the change line still hidden.
		await page.route( '**/guide/answer**', ( route ) => route.fulfill( { status: 409, contentType: 'application/json', body: JSON.stringify( { code: 'running', message: 'The fill is still running.', data: { status: 409 } } ) } ) );
		await cards.nth( 0 ).locator( '.g-answer[data-answer="new-folder"]' ).click();
		await expect( cards.nth( 0 ).locator( '.g-q-error' ) ).toHaveText( 'The fill is still running.' );
		await expect( cards.nth( 0 ).locator( '.g-answer' ).first() ).toBeEnabled();
		await expect( page.locator( '.g-change' ) ).toBeHidden();
		expect( await page.evaluate( ( n ) => n.isConnected, firstCard ), 'the card that took the error is the same node' ).toBe( true );
		await page.unroute( '**/guide/answer**' );
		await page.screenshot( { path: 'tests/ui/shots/folders-answer-409.png' } );

		// Watched: the tree's list (rows added must equal rows on it: one render) and the fill slot (no re-append of the tree node).
		await page.evaluate( () => {
			const list = document.querySelector( '.g-tree .vgml-list' );
			const slot = document.querySelector( '.g-card[data-card="fill"] .g-tree' ).parentNode;
			window.__vgmlWatch = { added: 0, removed: 0, reappended: 0, batches: 0 };
			new MutationObserver( ( records ) => {
				window.__vgmlWatch.batches++;
				records.forEach( ( r ) => {
					window.__vgmlWatch.added += r.addedNodes.length;
					window.__vgmlWatch.removed += r.removedNodes.length;
				} );
			} ).observe( list, { childList: true } );
			new MutationObserver( ( records ) => {
				records.forEach( ( r ) => {
					Array.prototype.forEach.call( r.addedNodes, ( n ) => {
						if ( n.classList && n.classList.contains( 'g-tree' ) ) {
							window.__vgmlWatch.reappended++;
						}
					} );
				} );
			} ).observe( slot, { childList: true } );
		} );

		// The real answer: fifty pictures into a new folder.
		const [ answered, tree ] = await Promise.all( [
			page.waitForResponse( ( res ) => /\/guide\/answer/.test( res.url() ) ),
			page.waitForResponse( ( res ) => /\/tree\?/.test( res.url() ) && 'GET' === res.request().method() ),
			cards.nth( 0 ).locator( '.g-answer[data-answer="new-folder"]' ).click(),
		] );
		expect( answered.status() ).toBe( 200 );
		const a = await answered.json();
		expect( a.result.moved ).toBe( 50 );
		expect( a.result.placed, 'the person chose the folder: fifty by you' ).toBe( 50 );
		expect( a.questions, 'the answer carries no questions' ).toBeUndefined();
		expect( JSON.stringify( a ) ).not.toContain( '"sample"' );

		await expect( page.locator( '.g-qs .g-q.is-answered .g-q-result' ) ).toContainText( 'Spec probe made · 50 moved' );
		await expect( page.locator( '.g-qs .g-q.is-answered .g-q-review' ) ).toHaveText( '50 by you · review' );
		expect( await page.locator( '.g-qs .g-q.is-answered .g-q-review' ).getAttribute( 'href' ) ).toContain( '=by_you' );
		expect( await page.evaluate( ( n ) => n.isConnected && n.classList.contains( 'is-answered' ), firstCard ), 'the answered card is the same node' ).toBe( true );
		await expect( cards ).toHaveCount( 3 );
		await expect( page.locator( '.g-qs .g-q:not(.is-answered)' ) ).toHaveCount( 2 );
		await expect( page.locator( '.g-card[data-card="fill"] .g-card-head .g-pill.is-ask' ) ).toHaveText( '29 questions' );
		await expect( page.locator( '.g-tree .vgml-node.is-new .vgml-name' ).filter( { hasText: 'Spec probe' } ) ).toHaveCount( 1 );
		await page.waitForTimeout( 800 );
		const watch = await page.evaluate( () => ( { ...window.__vgmlWatch, rows: document.querySelector( '.g-tree .vgml-list' ).children.length } ) );
		expect( watch.reappended, 'the tree node is not re-appended into the slot' ).toBe( 0 );
		expect( watch.added, `one render: rows added (${ watch.added }) equal rows on the list (${ watch.rows })` ).toBe( watch.rows );
		expect( watch.batches, 'one childList batch on .vgml-list' ).toBe( 1 );

		// The timing row for this site.
		const h = ( r, k ) => r.headers()[ k.toLowerCase() ];
		const wall = ( r ) => { const t = r.request().timing(); return t && t.responseEnd > 0 ? Math.round( t.responseEnd - Math.max( 0, t.requestStart ) ) : null; };
		const row = `${ box ? 'box' : 'playground' } · answer (50 pictures, 30 questions): handler ${ h( answered, 'X-Vgml-Handler-Ms' ) ?? '?' } ms · ${ h( answered, 'X-Vgml-Queries' ) ?? '?' } queries · wall ${ wall( answered ) ?? '?' } ms · tree GET: handler ${ h( tree, 'X-Vgml-Handler-Ms' ) ?? '?' } ms · wall ${ wall( tree ) ?? '?' } ms`;
		console.log( `      ${ row }` );
		if ( h( answered, 'X-Vgml-Queries' ) ) {
			/*
			 *  Measured 2026-09-16 on fifty pictures: the plugin's own loop is
			 *  seven queries a picture (term_exists, the terms read, the
			 *  relationship's check and insert, the mark's two), and the box's
			 *  other plugins add four (Jetpack's sync queue, a termmeta read).
			 *  With term counting deferred: 533 in PHP, 593 through REST;
			 *  without it: 729 in PHP. The budget sits between.
			 */
			expect( Number( h( answered, 'X-Vgml-Queries' ) ), 'queries for fifty moves' ).toBeLessThanOrEqual( box ? 50 * 12 + 80 : 50 * 9 + 60 );
		}
		if ( ! box ) {
			/*
			 *  The plugin's own number on Playground is the query count, held
			 *  above; the milliseconds are PHP-wasm's (2026-09-16: 435 queries
			 *  in 2.6 s here, ~6 ms a query, against 591 in 620 ms on the box's
			 *  MySQL). tests/perf/mu-perf.php says the same in its header. The
			 *  plan's 400 ms is a real-database number; it is the box's row
			 *  that answers it, minus the box's own plugins.
			 */
			expect( h( answered, 'X-Vgml-Queries' ), 'Playground boots without --perf: no query count to hold' ).toBeTruthy();
		}

		// "Show me" on the either card from the keyboard: Enter opens the strip; then 1 answers the next card with its first answer.
		const eitherCard = page.locator( `.g-qs .g-q[data-q="${ either.id }"]` );
		if ( await eitherCard.count() ) {
			await eitherCard.focus();
			await page.keyboard.press( 'Enter' );
			await expect( eitherCard.locator( '.g-q-strip' ) ).toHaveClass( /is-open/ );
		}
		const next = page.locator( '.g-qs .g-q:not(.is-answered)' ).first();
		const nextId = await next.getAttribute( 'data-q' );
		const firstAnswer = await next.locator( '.g-answer' ).first().getAttribute( 'data-answer' );
		await next.focus();
		const [ byKey ] = await Promise.all( [
			page.waitForResponse( ( res ) => /\/guide\/answer/.test( res.url() ) ),
			page.keyboard.press( '1' ),
		] );
		expect( ( await byKey.request().postDataJSON() ) ).toMatchObject( { id: nextId, answer: firstAnswer } );
		await expect( page.locator( `.g-qs .g-q[data-q="${ nextId }"]` ) ).toHaveClass( /is-answered/ );

		await page.screenshot( { path: 'tests/ui/shots/folders-answered-in-place.png' } );
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );

	/*
	 *  The strip's cap (C.3): "Show me" on a group over 48 shows 48 and says
	 *  how many it left out. Its own test, so the fifty-picture question is
	 *  still open when the strip is asked for.
	 */
	test( 'Show me on fifty pictures shows 48 and says 2 more', async ( { page } ) => {
		test.skip( ! boxFor( BASE ), 'planted on the box over SSH' );
		test.setTimeout( 300_000 );
		await remember( page );
		await plant( page, false );
		plantOnBox( { VGML_COUNT: 4, VGML_Q1: 50 } );
		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open_( page );
		const first = page.locator( '.g-qs .g-q' ).first();
		await expect( first.locator( '.g-q-text' ) ).toHaveText( '50 look like spec probes' );
		await expect( first.locator( '.g-q-strip img' ) ).toHaveCount( 8 );
		await first.locator( '.g-answer[data-answer="show-me"]' ).click();
		await expect( first.locator( '.g-q-strip' ) ).toHaveClass( /is-open/ );
		await expect( first.locator( '.g-q-strip img' ) ).toHaveCount( 48 );
		await expect( first.locator( '.g-q-strip .g-q-strip-more' ) ).toHaveText( '2 more' );
		await page.screenshot( { path: 'tests/ui/shots/folders-show-me-capped.png' } );
	} );

	/*
	 *  Placed by hand, reviewable (C.3): the media list's folder filter lists
	 *  exactly the pictures that carry the mark -- the fixture's own SQL says
	 *  which, 61 of Nathan's plus the one it marks -- and a picture dragged
	 *  out of its folder (the Unfiled drop's request through /assign) loses
	 *  the mark, so the next fill may judge it again.
	 *
	 *  Mutation: drop the `delete_post_meta` branch in core/rest-tree.php's
	 *  assign -> the cleared-mark row red.
	 */
	test( 'Placed by hand lists exactly the marked pictures; a drag out clears the mark', async ( { page } ) => {
		test.skip( ! boxFor( BASE ), 'planted on the box over SSH' );
		test.setTimeout( 300_000 );
		await remember( page );
		const f = plantOnBox( { VGML_LEFT: 0 } );
		expect( f.word.id, 'a filed picture to mark' ).toBeGreaterThan( 0 );
		expect( f.placed, 'the marked one is among the marked' ).toContain( f.word.id );
		const tax = 'media_category';

		const filtered = async () => {
			await page.goto( `/wp-admin/upload.php?mode=list&${ tax }=by_you`, { waitUntil: 'domcontentloaded' } );
			await page.waitForSelector( '.wp-list-table', { timeout: 30000 } );
			return page.evaluate( ( t ) => ( {
				url: location.href,
				selected: document.querySelector( `select[name="${ t }"]` ).value,
				option: Array.from( document.querySelectorAll( `select[name="${ t }"] option` ) ).map( ( o ) => o.textContent.trim() ),
				items: ( document.querySelector( '.displaying-num' ) || {} ).textContent || '',
				rows: Array.from( document.querySelectorAll( '#the-list tr[id^="post-"]' ) ).map( ( tr ) => ( { id: Number( tr.id.replace( 'post-', '' ) ), word: ( tr.querySelector( '.filename .vgml-word' ) || {} ).textContent || '' } ) ),
			} ), tax );
		};

		await page.setViewportSize( { width: 1600, height: 900 } );
		let seen = await filtered();
		expect( seen.selected, `${ seen.url } · options: ${ seen.option.slice( 0, 4 ).join( ' | ' ) }` ).toBe( 'by_you' );
		expect( seen.option[ 2 ], 'third in the dropdown, after Unfiled' ).toBe( `Placed by hand (${ f.placed.length })` );
		expect( seen.items.replace( /\D/g, '' ), 'the list counts exactly the marked pictures' ).toBe( String( f.placed.length ) );
		expect( seen.rows.length ).toBeGreaterThan( 0 );
		for ( const r of seen.rows ) {
			expect( f.placed, `row ${ r.id } is one the fixture's SQL lists` ).toContain( r.id );
			expect( r.word, `row ${ r.id } wears the word` ).toBe( 'by you' );
		}
		await page.screenshot( { path: 'tests/ui/shots/list-placed-by-hand.png' } );

		// Dragged out: the Unfiled drop's request, and the mark is gone.
		const out = await page.evaluate( ( [ ns, id, t ] ) => wp.apiFetch( { path: `${ ns }/assign`, method: 'POST', data: { taxonomy: t, attachments: [ id ], add: [], mode: 'move' } } ), [ NS, f.word.id, tax ] );
		expect( out ).toBeTruthy();
		const why = await page.evaluate( ( [ ns, id ] ) => wp.apiFetch( { path: `${ ns }/librarian-why/${ id }` } ), [ NS, f.word.id ] );
		expect( why.confidence, 'no longer by you' ).not.toBe( 'by you' );
		seen = await filtered();
		expect( seen.items.replace( /\D/g, '' ), 'one fewer in the filter' ).toBe( String( f.placed.length - 1 ) );
		expect( seen.rows.map( ( r ) => r.id ) ).not.toContain( f.word.id );
	} );

	/*
	 *  The words a folder takes, on the tree (C.4): quiet pills after the name
	 *  (three, then "+n"); × takes a word out of the draft's classes and the
	 *  fit re-runs; "#word" in the row's + editor adds one; the confirm seeds
	 *  the profile from them, and a confirmed row's pills are the stored
	 *  profile's; a confirm that replaced a planned profile keeps the earlier
	 *  one for a day, and Unconfirm offers Restore. On one real folder made
	 *  for the test and deleted after (its profile goes with it).
	 *
	 *  Mutations: drop `f.classes = ...` from applyEdit's 'classes' case ->
	 *  the × row red; drop the VERGEML_FILING_META_PREV write from
	 *  vergeml_filing_profile_build -> the Restore row red.
	 */
	test( 'the words a folder takes: pills from the profile, × removes, #word adds, Unconfirm offers Restore', async ( { page } ) => {
		test.skip( ! boxFor( BASE ), 'seeds a profile onto a real folder (one embed, cached a week) on the box' );
		test.setTimeout( 300_000 );
		await remember( page );
		await plant( page, false );
		const tax = 'media_category';
		const made = Number( ( await page.evaluate( ( [ ns, t ] ) => wp.apiFetch( { path: `${ ns }/folder`, method: 'POST', data: { taxonomy: t, action: 'create', name: 'Class probe' } } ), [ NS, tax ] ) ).id );
		expect( made ).toBeGreaterThan( 0 );
		const draftWith = ( classes ) => page.evaluate( ( [ ns, draft ] ) => wp.apiFetch( { path: `${ ns }/guide/session`, method: 'POST', data: { draft } } ), [ NS, {
			folders: [ { key: 't' + made, term_id: made, name: 'Class probe', parent: '', classes, kinds: [ 'photo' ], audience: '', matches: 'a probe' } ],
			gone: {}, tags: [], origin: 'talk', rule: null,
		} ] );
		const nodeClasses = async () => ( ( await page.evaluate( ( [ ns, t ] ) => wp.apiFetch( { path: `${ ns }/tree?taxonomy=${ t }` } ), [ NS, tax ] ) ).nodes.find( ( n ) => n.id === made ) || {} ).classes;
		const draftClasses = async () => ( ( await getSession( page ) ).session.draft.folders.find( ( f ) => f.term_id === made ) || {} ).classes;
		const row = () => page.locator( '.g-tree .vgml-node[data-key="t' + made + '"]' );
		const pills = () => row().locator( '.vgml-classes .vgml-class' );

		try {
			await draftWith( [ 'probe', 'probe two', 'probe three', 'probe four' ] );
			await page.setViewportSize( { width: 1600, height: 1000 } );
			await open_( page );
			await expect( pills() ).toHaveText( [ 'probe×', 'probe two×', 'probe three×', '+1' ] );

			// × on a word: out of the draft, the line says so, the fit re-runs (the confirm waits for it).
			await row().locator( '.vgml-class[data-class="probe two"] .vgml-unclass' ).click();
			await expect( pills() ).toHaveText( [ 'probe×', 'probe three×', 'probe four×' ] );
			await expect( page.locator( '.g-change .vgml-msg.is-edit' ).last() ).toContainText( 'Removed the word probe two from Class probe' );
			await expect.poll( draftClasses, { timeout: 20000 } ).toEqual( [ 'probe', 'probe three', 'probe four' ] );
			await expect( page.locator( '.g-card[data-card="tree"] .vgml-confirm-btn' ) ).toBeEnabled( { timeout: 90000 } );

			// #word in the row's + editor: a word, not a folder.
			await row().locator( '.vgml-row' ).hover();
			await row().locator( '.vgml-add' ).click();
			await page.locator( '.g-tree .vgml-node.is-adding .vgml-editor' ).fill( '#Probe Five' );
			await page.keyboard.press( 'Enter' );
			await expect( pills() ).toHaveText( [ 'probe×', 'probe three×', 'probe four×', '+1' ] );
			await expect( page.locator( '.g-change .vgml-msg.is-edit' ).last() ).toContainText( 'Added the word probe five to Class probe' );
			await expect.poll( draftClasses, { timeout: 20000 } ).toEqual( [ 'probe', 'probe three', 'probe four', 'probe five' ] );
			expect( ( await getSession( page ) ).session.draft.folders.length, 'no folder was added' ).toBe( 1 );
			await page.screenshot( { path: 'tests/ui/shots/folders-class-pills.png' } );

			// Confirm: the profile is seeded from the draft; the row's pills are the stored profile's, with no ×.
			await expect( page.locator( '.g-card[data-card="tree"] .vgml-confirm-btn' ) ).toBeEnabled( { timeout: 90000 } );
			const c1 = await Promise.all( [ page.waitForResponse( ( res ) => /\/guide\/confirm/.test( res.url() ) ), page.locator( '.g-card[data-card="tree"] .vgml-confirm-btn' ).click() ] );
			expect( c1[ 0 ].status() ).toBe( 200 );
			await expect.poll( nodeClasses, { timeout: 20000 } ).toEqual( [ 'probe', 'probe three', 'probe four', 'probe five' ] );
			await page.locator( '.g-step[data-step="tree"]' ).click();
			await expect( pills() ).toHaveText( [ 'probe', 'probe three', 'probe four', '+1' ] );
			expect( await row().locator( '.vgml-unclass' ).count(), 'a confirmed row has no ×' ).toBe( 0 );

			// A second confirm with other words replaces the profile and keeps the earlier one; Unconfirm offers Restore, which puts it back.
			const u1 = await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/unconfirm`, method: 'POST' } ), NS );
			expect( u1.prev, 'no earlier profile before the second confirm' ).toBe( 0 );
			await draftWith( [ 'probe six' ] );
			await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/confirm`, method: 'POST' } ), NS );
			await expect.poll( nodeClasses, { timeout: 20000 } ).toEqual( [ 'probe six' ] );
			await page.reload( { waitUntil: 'domcontentloaded' } );
			await ready( page );
			await page.locator( '.g-step[data-step="tree"]' ).click();
			await page.locator( '.g-card[data-card="tree"] .g-quiet' ).filter( { hasText: 'Unconfirm' } ).click();
			const restore = page.locator( '.g-card[data-card="tree"] .vgml-restore-profiles' );
			await expect( restore ).toHaveText( 'Restore the earlier classes' );
			const r = await Promise.all( [ page.waitForResponse( ( res ) => /\/guide\/profiles-restore/.test( res.url() ) ), restore.click() ] );
			expect( ( await r[ 0 ].json() ).restored ).toBe( 1 );
			await expect.poll( nodeClasses, { timeout: 20000 } ).toEqual( [ 'probe', 'probe three', 'probe four', 'probe five' ] );
			expect( await draftClasses(), 'the draft carries the restored words, so the next confirm keeps them' ).toEqual( [ 'probe', 'probe three', 'probe four', 'probe five' ] );
			await expect( pills() ).toHaveText( [ 'probe×', 'probe three×', 'probe four×', '+1' ] );
			await page.screenshot( { path: 'tests/ui/shots/folders-class-restore.png' } );
		} finally {
			await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/unconfirm`, method: 'POST' } ).catch( () => null ), NS );
			await page.evaluate( ( [ ns, t, id ] ) => wp.apiFetch( { path: `${ ns }/folder`, method: 'POST', data: { taxonomy: t, action: 'delete', id } } ).catch( () => null ), [ NS, tax, made ] );
		}
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
