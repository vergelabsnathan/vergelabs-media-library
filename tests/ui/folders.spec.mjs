import { test, expect, open, SCREEN } from './fixtures.mjs';

/*
 *  Folders: one conversation, one tree, one Move.
 *
 *  Walked on the box. Without GUIDE_WALK=1 nothing here asks the service for
 *  a turn and nothing moves a file: the session is planted through the same
 *  routes the screen uses, the tree and the rules answer from the database,
 *  and the screenshots are of the resting screen. With GUIDE_WALK=1 the
 *  conversation opens and is stopped mid-stream (two planner calls' worth:
 *  the token and the turn, ten describes each), a rule is applied and moved
 *  (real pictures, on the box), the moving and done states are screenshotted,
 *  and the Move is undone.
 *
 *  Screenshots go to tests/ui/shots/, not test-results/: Playwright empties
 *  test-results/ at the start of every run, and the walk's screenshots are
 *  evidence that must outlive the next run.
 *
 *  The session that was on the box is read first and put back at the end,
 *  whatever the tests did: a planted draft once stayed behind, somebody
 *  pressed Move on it, and twenty-one real folders went.
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
	const nodes = boot.nodes;
	const folders = nodes.map( ( n ) => ( { key: 't' + n.id, term_id: n.id, name: n.name, parent: n.parent ? 't' + n.parent : '' } ) );
	const first = folders.find( ( f ) => f.parent === '' );
	first.name = first.name + ' (draft)';
	first.by = 'you';
	folders.push( { key: 'probe1', term_id: null, name: 'Draft probe', parent: '', count: 12, matches: 'a probe', classes: [ 'probe' ], kinds: [ 'photo' ], audience: '' } );
	await page.evaluate( ( [ ns, draft ] ) => wp.apiFetch( { path: `${ ns }/guide/session`, method: 'POST', data: { draft } } ), [ NS, { folders, gone: {}, tags: [], origin: 'talk', rule: null } ] );
	return boot;
}

test.describe( 'the Folders screen', () => {

	test.afterEach( async ( { page } ) => {
		await restore( page );
	} );

	test( 'paints from the page, within the budget measured today, and shows the draft over the tree', async ( { page } ) => {
		const problems = [];
		page.on( 'pageerror', ( e ) => problems.push( `javascript: ${ e.message }` ) );

		await open( page, SCREEN.dashboard );
		if ( found === null ) {
			found = await getSession( page );
		}
		await plant( page, true );

		const rest = [];
		let ready = false;
		page.on( 'response', ( r ) => {
			if ( ! ready && /\/vergeml\/v1\//.test( r.url() ) ) {
				rest.push( { url: r.url(), queries: Number( r.headers()[ 'x-vgml-queries' ] || 0 ) } );
			}
		} );
		await page.setViewportSize( { width: 1440, height: 1100 } );
		await open( page, SCREEN.folders );
		await expect( page.locator( '.vgml-folders.is-ready' ) ).toBeVisible( { timeout: 30000 } );
		ready = true;

		expect( rest.length, `REST requests before first paint: ${ rest.map( ( r ) => r.url ).join( ', ' ) }` ).toBeLessThanOrEqual( TODAY.restRequests );
		expect( rest.reduce( ( n, r ) => n + r.queries, 0 ), 'their queries' ).toBeLessThanOrEqual( TODAY.restQueries );

		await expect( page.locator( '.vgml-folders-facts' ) ).toHaveText( /\d[\d,.]* pictures · \d+ folders · \d[\d,.]* in no folder · described / );
		await expect( page.locator( '.vgml-seg-tab[aria-selected="true"]' ) ).toHaveText( 'Conversation' );
		await expect( page.locator( '.vgml-method-kicker' ) ).toHaveText( /^\d+ of \d+ turns$/ );
		await expect( page.locator( '.vgml-msg.is-assistant' ).first() ).toContainText( 'In the draft:' );
		await expect( page.locator( '.vgml-msg.is-assistant .vgml-facts li' ).first() ).toContainText( 'Landscape and nature' );
		// At the cap the composer's own label says so, and offers the way out.
		await expect( page.locator( '.vgml-composer-text' ) ).toHaveAttribute( 'placeholder', /^\d+ of \d+ turns used\. Edit the tree by hand, or start over\.$/ );
		await expect( page.locator( '.vgml-startover' ) ).toBeVisible();

		// The tree: the draft over today's folders, keyed by id, Changes first.
		await expect( page.locator( '.vgml-tree-kicker' ) ).toHaveText( /^Folders · \d+ now, \d+ after Move$/ );
		await expect( page.locator( '.vgml-tv-state[data-mode="changes"]' ) ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( page.locator( '.vgml-node.is-new .vgml-name' ) ).toContainText( 'Draft probe' );
		await expect( page.locator( '.vgml-node.is-change' ).filter( { hasText: '(draft)' } ) ).toHaveCount( 1 );
		await expect( page.locator( '.vgml-tv-sub' ).filter( { hasText: 'renamed from' } ) ).toContainText( ', by you' );
		await expect( page.locator( '.vgml-move-btn' ) ).toHaveText( /^Move \d+ pictures$/ );
		await expect( page.locator( '.vgml-move-btn' ) ).toBeEnabled();

		await page.screenshot( { path: 'tests/ui/shots/folders-resting.png', fullPage: true } );
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );

	test( 'a hand edit is a line in the conversation, and survives a reload by id', async ( { page } ) => {
		await open( page, SCREEN.dashboard );
		if ( found === null ) {
			found = await getSession( page );
		}
		await plant( page, false );
		await open( page, SCREEN.folders );
		await expect( page.locator( '.vgml-folders.is-ready' ) ).toBeVisible( { timeout: 30000 } );

		await expect( page.locator( '.vgml-move-btn' ) ).toHaveText( 'Move · no changes yet' );
		await expect( page.locator( '.vgml-move-btn' ) ).toBeDisabled();

		// Rename the first top-level folder in place: double-click the name, type, Enter.
		const first = page.locator( '.vgml-node[data-key]' ).first();
		const name = await first.locator( '.vgml-name' ).innerText();
		await first.locator( '.vgml-name' ).dblclick();
		await page.locator( '.vgml-editor' ).fill( name + ' renamed' );
		await page.keyboard.press( 'Enter' );

		await expect( page.locator( '.vgml-msg.is-edit' ).last() ).toContainText( `Renamed ${ name } to ${ name } renamed` );
		await expect( page.locator( '.vgml-msg.is-edit .vgml-msg-who' ).last() ).toHaveText( 'You · edited the tree' );
		await expect( page.locator( '.vgml-tv-sub' ).filter( { hasText: `renamed from ${ name }` } ) ).toContainText( ', by you' );
		await expect( page.locator( '.vgml-move-btn' ) ).toHaveText( 'Move 0 pictures' );

		await expect.poll( async () => {
			const s = await getSession( page );
			const renamed = s.session.draft && s.session.draft.folders.find( ( f ) => f.name === name + ' renamed' );
			return renamed ? renamed.term_id : 0;
		}, { message: 'the renamed folder keeps its term id in the persisted draft', timeout: 20000 } ).toBeGreaterThan( 0 );
		expect( ( await getSession( page ) ).session.turns.at( -1 ).kind ).toBe( 'edit' );

		await page.reload( { waitUntil: 'domcontentloaded' } );
		await expect( page.locator( '.vgml-folders.is-ready' ) ).toBeVisible( { timeout: 30000 } );
		await expect( page.locator( '.vgml-msg.is-edit' ).last() ).toContainText( 'Renamed' );
		await expect( page.locator( '.vgml-node.is-change .vgml-name' ).first() ).toContainText( name + ' renamed' );
	} );

	test( 'a rule builds the draft without a model, the tree answers, and one line says so', async ( { page } ) => {
		await open( page, SCREEN.dashboard );
		if ( found === null ) {
			found = await getSession( page );
		}
		await plant( page, false );
		await open( page, SCREEN.folders );
		await expect( page.locator( '.vgml-folders.is-ready' ) ).toBeVisible( { timeout: 30000 } );

		const service = [];
		page.on( 'request', ( r ) => { if ( /\/guide\/(stream|session)$/.test( r.url() ) && ! /vergeml\/v1/.test( r.url() ) ) service.push( r.url() ); } );

		await page.locator( '.vgml-seg-tab[data-method="rules"]' ).click();
		await expect( page.locator( '.vgml-method-kicker' ) ).toHaveText( 'Uses no credits' );
		await expect( page.locator( '.vgml-rule-row' ) ).toHaveCount( 4 );
		await expect( page.locator( '.vgml-rule-n' ).first() ).toHaveText( /^\d+ folders?$/ );

		await page.locator( '.vgml-rule-row' ).first().locator( '.vgml-rule-pick' ).click();
		await expect( page.locator( '.vgml-rule-row.is-on .vgml-rule-title' ) ).toHaveText( 'By kind' );
		await expect( page.locator( '.vgml-rule-row.is-on .vgml-radio' ).first() ).toContainText( /^Move only the \d+ unfiled pictures\. Today's \d+ folders stay\.$/ );
		await expect( page.locator( '.vgml-preview li' ).first() ).toContainText( /new folders?: /, { timeout: 20000 } );
		await expect( page.locator( '.vgml-preview li' ).nth( 1 ) ).toHaveText( /^\d[\d,.]* pictures? moves?$/ );
		await expect( page.locator( '.vgml-preview li' ).last() ).toHaveText( "Today's folders unchanged" );
		await expect( page.locator( '.vgml-node.is-new' ).first() ).toBeVisible();
		await expect( page.locator( '.vgml-move-btn' ) ).toHaveText( /^Move \d[\d,.]* pictures$/ );
		await expect( page.locator( '.vgml-move-btn' ) ).toBeEnabled();

		// The other scope: today's folders go, and the line is replaced, not added.
		await page.locator( '.vgml-rule-row.is-on input[value="all"]' ).check();
		await expect( page.locator( '.vgml-preview li' ).last() ).toHaveText( /^Today's \d+ folders are removed$/, { timeout: 20000 } );
		await expect( page.locator( '.vgml-node.is-gone' ).first() ).toBeVisible();

		await page.screenshot( { path: 'tests/ui/shots/folders-rules.png', fullPage: true } );

		await page.locator( '.vgml-seg-tab[data-method="talk"]' ).click();
		await expect( page.locator( '.vgml-msg.is-rule' ) ).toHaveCount( 1 );
		await expect( page.locator( '.vgml-msg.is-rule .vgml-msg-who' ) ).toHaveText( 'You · applied a rule' );
		await expect( page.locator( '.vgml-msg.is-rule .vgml-msg-body' ) ).toHaveText( /^By kind: \d+ folders, \d[\d,.]* pictures$/ );
		expect( service, 'no call to the service: a rule costs nothing' ).toEqual( [] );

		await expect.poll( async () => JSON.stringify( ( ( await getSession( page ) ).session.draft || {} ).rule ), { timeout: 20000 } ).toBe( JSON.stringify( { id: 'kind', options: { scope: 'all' } } ) );
	} );

	test( 'the old guide address lands here', async ( { page } ) => {
		// Planted first: the screen it lands on opens the conversation by itself on an empty session.
		await open( page, SCREEN.dashboard );
		if ( found === null ) {
			found = await getSession( page );
		}
		await plant( page, false );
		await page.goto( '/wp-admin/admin.php?page=media-guide', { waitUntil: 'domcontentloaded' } );
		await expect( page ).toHaveURL( /page=media-librarian/ );
	} );

	test( 'walk: the conversation opens and streams, Stop stops it, a rule is moved and undone', async ( { page } ) => {
		test.skip( ! WALK, 'GUIDE_WALK=1 spends planner calls and moves real pictures on the box' );
		test.setTimeout( 300_000 );
		console.log( '  cost: the token and the opening turn are two planner calls, ten describes\' worth each' );

		await open( page, SCREEN.dashboard );
		if ( found === null ) {
			found = await getSession( page );
		}
		await reset( page );
		await page.setViewportSize( { width: 1440, height: 1100 } );
		await open( page, SCREEN.folders );
		await expect( page.locator( '.vgml-folders.is-ready' ) ).toBeVisible( { timeout: 30000 } );

		// The opener streams: the send arrow is Stop while it does.
		await expect( page.locator( '.vgml-send.is-stop' ), 'send became Stop while the reply streams' ).toBeVisible( { timeout: 60000 } );
		await expect( page.locator( '.vgml-msg.is-streaming' ) ).toBeVisible();
		await page.waitForFunction( () => ( document.querySelector( '.vgml-msg.is-streaming .vgml-msg-body' ) || {} ).textContent.length > 20, null, { timeout: 60000 } );
		await page.locator( '.vgml-send.is-stop' ).click();
		await expect( page.locator( '.vgml-send' ) ).not.toHaveClass( /is-stop/ );
		await expect( page.locator( '.vgml-msg.is-streaming' ) ).toHaveCount( 0 );
		await expect( page.locator( '.vgml-msg.is-assistant' ).first(), 'what streamed stays' ).not.toBeEmpty();
		await expect( page.locator( '.vgml-msg.is-note' ) ).toContainText( 'Stopped' );
		await expect( page.locator( '.vgml-method-kicker' ) ).toHaveText( '1 of 25 turns' );

		// A rule, then Move: the pictures the rule named go, the tree fills as they land.
		await page.locator( '.vgml-seg-tab[data-method="rules"]' ).click();
		await page.locator( '.vgml-rule-row' ).first().locator( '.vgml-rule-pick' ).click();
		await expect( page.locator( '.vgml-move-btn' ) ).toHaveText( /^Move \d[\d,.]* pictures$/, { timeout: 20000 } );
		const label = await page.locator( '.vgml-move-btn' ).innerText();
		const expected = Number( label.replace( /[^\d]/g, '' ) );
		await page.locator( '.vgml-move-btn' ).click();

		await expect( page.locator( '.vgml-folders[data-state="moving"]' ) ).toBeVisible( { timeout: 30000 } );
		await expect( page.locator( '.vgml-move-btn' ) ).toHaveText( /^Moving \d[\d,.]* of \d[\d,.]*$/ );
		await expect( page.locator( '.vgml-move-stop' ) ).toBeVisible();
		await expect( page.locator( '.vgml-fill' ).first(), 'a folder fills as pictures land' ).toBeVisible( { timeout: 30000 } );
		await page.screenshot( { path: 'tests/ui/shots/folders-moving.png', fullPage: true } );

		await expect( page.locator( '.vgml-folders[data-state="done"]' ) ).toBeVisible( { timeout: 180000 } );
		await expect( page.locator( '.vgml-move-undo' ) ).toHaveText( /^Undo until (today|tomorrow) \d{1,2}:\d{2}/ );
		await expect( page.locator( '.vgml-msg.is-moved .vgml-facts li' ).first() ).toHaveText( new RegExp( `^${ expected.toLocaleString() } pictures moved into \\d+ folders$` ) );
		await expect( page.locator( '.vgml-msg.is-moved .vgml-facts li' ).nth( 1 ) ).toHaveText( /^\d[\d,.]* stayed where they were$/ );
		await expect( page.locator( '.vgml-folders-tree .vgml-node.is-new' ) ).toHaveCount( 0 );
		await page.screenshot( { path: 'tests/ui/shots/folders-done.png', fullPage: true } );

		// Undo: the pictures back, the folders the Move made gone again.
		const before = await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/session` } ).then( ( s ) => s.nodes.length ), NS );
		await page.locator( '.vgml-move-undo' ).click();
		await expect( page.locator( '.vgml-msg.is-moved' ).last() ).toContainText( 'put back', { timeout: 60000 } );
		await expect( page.locator( '.vgml-move-undo' ) ).toBeHidden();
		const after = await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/session` } ).then( ( s ) => s.nodes.length ), NS );
		expect( after, 'the folders the rule made are gone again' ).toBeLessThan( before );
	} );
} );
