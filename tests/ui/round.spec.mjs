import { test, expect, open, SCREEN } from './fixtures.mjs';
import { boxFor, boxPhp } from './box.mjs';

/*
 *  The owner's round (every-picture-a-home S11): the Folders screen used the
 *  way Nathan used it on 2026-09-16, every button real, over the library the
 *  box holds.
 *
 *      This is my tree → Fill → an answer → Leave the rest → Fill again →
 *      Unconfirm → × on a word → This is my tree → Fill
 *
 *  Each turn of that afternoon found a state the design assumed would not
 *  recur (the handoff of 2026-09-16 lists eight), and none had a test that
 *  walked the screen the way an owner does. This is that walk, with a shot
 *  at every state (tests/ui/shots/round-*.png) and three things held:
 *
 *    - a fill after a fill runs (the run's end clears the draft; the second
 *      press used to be refused with "Nothing moved.");
 *    - a second fill asks no question about a picture an answer placed (every
 *      fill on the shop asked the same questions again until 2026-09-17);
 *    - a word taken off a folder is gone from its profile after the confirm,
 *      and the fill after it runs.
 *
 *  Three real fills move real pictures and two confirms re-profile real
 *  folders, so the box is snapshotted by literal SQL before (tests/ui/round-
 *  fixture.php) and put back row for row after, whatever the test did.
 *
 *  Cost, said before it runs: 0 credits. The first confirm asks the planner
 *  about the folders the draft says nothing about and whose name is not a
 *  library word (three on the tech tree; the first hundred are free), the
 *  second about none; each fill's end names its residue groups (metered like
 *  an embed, no credit). Under a dollar on the OpenRouter ledger. Only on a
 *  box tests/ui/box.mjs knows; elsewhere it skips.
 */

const BASE = process.env.UI_BASE ?? 'http://46.225.66.194';
const FIXTURE = 'tests/ui/round-fixture.php';
const NS = '/vergeml/v1';
const FILL_TIMEOUT = 300_000;

let snapped = false;

function snapshot() {
	const out = boxPhp( BASE, FIXTURE, { VGML_MODE: 'snapshot' } );
	snapped = true;
	const line = out.trim().split( '\n' ).reverse().find( ( l ) => l.startsWith( '{' ) );
	if ( ! line ) {
		throw new Error( `the fixture said nothing usable:\n${ out }` );
	}
	return JSON.parse( line );
}

function openQuestions() {
	const out = boxPhp( BASE, FIXTURE, { VGML_MODE: 'questions' } );
	const line = out.trim().split( '\n' ).reverse().find( ( l ) => l.startsWith( '{' ) );
	return JSON.parse( line );
}

function restoreBox() {
	if ( ! snapped ) {
		return null;
	}
	snapped = false;
	const out = boxPhp( BASE, FIXTURE, { VGML_MODE: 'restore' } );
	const m = /restored (\{.*\})/.exec( out );
	if ( ! m ) {
		throw new Error( `the fixture did not restore:\n${ out }` );
	}
	return JSON.parse( m[ 1 ] );
}

const shot = ( page, name ) => page.screenshot( { path: `tests/ui/shots/round-${ name }.png` } );

async function ready( page ) {
	await expect( page.locator( '.vgml-folders.is-ready' ) ).toBeVisible( { timeout: 30000 } );
}

const progress = ( page ) => page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/progress` } ), NS );
const session = ( page ) => page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/session` } ), NS );

/** Press Fill, see the run move, and wait for it to end; returns the report at the end. */
async function fill( page, name ) {
	const btn = page.locator( '.g-card[data-card="fill"] .vgml-move-btn' );
	await expect( btn ).toBeEnabled( { timeout: 90000 } );
	const label = await btn.innerText();
	expect( label, 'the button carries its count' ).toMatch( /^Fill \d[\d,.]* pictures?$/ );
	const t0 = Date.now();
	const r = await Promise.all( [
		page.waitForResponse( ( res ) => /\/guide\/apply/.test( res.url() ) ),
		btn.click(),
	] );
	expect( r[ 0 ].status(), `${ name }: apply answered` ).toBe( 200 );
	const started = await r[ 0 ].json();
	expect( started.report.running, `${ name }: the run is running` ).toBe( true );
	// The progress row, with the count moving: the shot is of the run under way.
	await expect( page.locator( '.g-card[data-card="fill"] .g-progress' ) ).toBeVisible( { timeout: 20000 } );
	await page.waitForTimeout( 2500 );
	await shot( page, name + '-running' );
	let last = null;
	await expect.poll( async () => {
		last = ( await progress( page ) ).report;
		return last.running;
	}, { timeout: FILL_TIMEOUT, intervals: [ 2000 ] } ).toBe( false );
	console.log( `      ${ name }: ${ last.seen } looked at, ${ last.moved } placed, ${ last.questions } questions, ${ last.unfiled ? Object.values( last.unfiled ).reduce( ( a, b ) => a + b, 0 ) : 0 } not placed, ${ Math.round( ( Date.now() - t0 ) / 1000 ) } s` );
	expect( last.stopped, `${ name }: not stopped` ).toBeFalsy();
	expect( last.seen, `${ name }: every picture looked at` ).toBe( last.total );
	// The screen has caught up with the end before the next press.
	await expect( page.locator( '.g-card[data-card="fill"] .g-progress' ) ).toBeHidden( { timeout: 20000 } );
	return last;
}

test.describe( 'the owner\'s round', () => {

	test.afterEach( async ( { page } ) => {
		// The page first: a screen still polling would write over what the fixture puts back.
		await page.close();
		const back = restoreBox();
		if ( back ) {
			console.log( `      restored: ${ back.rels } relationships (${ back.rels_was } snapshotted), ${ back.terms } folders (${ back.terms_was })` );
			expect( back.rels, 'every relationship back' ).toBe( back.rels_was );
			expect( back.terms, 'the folders as they were' ).toBe( back.terms_was );
		}
	} );

	test( 'confirm, fill, answer, fill again, unconfirm, take a word off, confirm, fill', async ( { page } ) => {
		test.skip( ! boxFor( BASE ), 'three real fills over the box\'s library, snapshotted and put back over SSH' );
		test.setTimeout( 4 * FILL_TIMEOUT );
		console.log( '  cost: 0 credits; one planner call on the first confirm, none on the second; the group names metered like embeds' );

		const snap = snapshot();
		console.log( `      snapshot: ${ snap.rels } relationships, ${ snap.terms } folders, ${ snap.placed } marks, ${ snap.described } described, tree ${ snap.tree || 'fresh' }` );
		expect( snap.described, 'a described library to fill' ).toBeGreaterThan( 100 );

		// The session: fresh, the live folders as the draft, nothing else said about them (the confirm keeps every profile the draft says nothing about).
		await open( page, SCREEN.dashboard );
		await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/unconfirm`, method: 'POST' } ).catch( () => null ), NS );
		await page.evaluate( ( ns ) => wp.apiFetch( { path: `${ ns }/guide/session`, method: 'POST', data: { reset: true } } ), NS );
		const boot = await session( page );
		const nodes = boot.nodes;
		const draft = {
			folders: nodes.map( ( n ) => ( { key: 't' + n.id, term_id: n.id, name: n.name, parent: n.parent ? 't' + n.parent : '' } ) ),
			gone: {}, tags: [], origin: 'talk', rule: null,
		};
		await page.evaluate( ( [ ns, draft ] ) => wp.apiFetch( { path: `${ ns }/guide/session`, method: 'POST', data: { draft } } ), [ NS, draft ] );

		await page.setViewportSize( { width: 1600, height: 1000 } );
		await open( page, SCREEN.folders );
		await ready( page );
		await expect( page.locator( '#vgml-folders' ) ).toHaveAttribute( 'data-step', 'tree' );
		// The tree is closed by default: the top-level rows are what shows.
		await expect( page.locator( '.g-tree .vgml-node' ) ).toHaveCount( nodes.filter( ( n ) => ! n.parent ).length );
		await shot( page, '01-tree' );

		// 1. This is my tree.
		const confirm = page.locator( '.g-card[data-card="tree"] .vgml-confirm-btn' );
		await expect( confirm ).toBeEnabled( { timeout: 90000 } );
		const c1 = await Promise.all( [ page.waitForResponse( ( res ) => /\/guide\/confirm/.test( res.url() ) ), confirm.click() ] );
		expect( c1[ 0 ].status(), 'confirm answered' ).toBe( 200 );
		const confirmed = await c1[ 0 ].json();
		console.log( `      confirm: ${ confirmed.profiled } folders profiled by the planner` );
		await expect( page.locator( '.g-step.is-current' ) ).toHaveText( 'Fill' );
		await expect( page.locator( '.g-step[data-step="tree"]' ) ).toHaveClass( /is-done/ );
		await shot( page, '02-confirmed' );

		// 2. Fill.
		const first = await fill( page, '03-fill' );
		expect( first.moved, 'the fill placed pictures' ).toBeGreaterThan( 0 );
		const asked = openQuestions();
		expect( asked.running ).toBe( false );
		expect( asked.open.length, 'the fill left a question to answer' ).toBeGreaterThan( 0 );
		await expect( page.locator( '.g-cols' ) ).toHaveClass( /is-asking/ );
		await expect( page.locator( '.g-qs .g-q' ).first() ).toBeVisible();
		await shot( page, '04-asked' );

		// 3. One answer: the first card's own first answer, whatever it is; those pictures are placed by an answer now.
		const card = page.locator( '.g-qs .g-q' ).first();
		const qid = await card.getAttribute( 'data-q' );
		const placed = ( asked.open.find( ( q ) => q.id === qid ) || asked.open[ 0 ] ).ids;
		const answer = card.locator( '.g-answer.is-first' );
		const chosen = await answer.innerText();
		const a = await Promise.all( [ page.waitForResponse( ( res ) => /\/guide\/answer/.test( res.url() ) ), answer.click() ] );
		expect( a[ 0 ].status(), 'the answer went through' ).toBe( 200 );
		const answered = await a[ 0 ].json();
		console.log( `      answered "${ chosen }" on ${ placed.length } pictures (${ qid }): moved ${ answered.result.moved }` );
		await expect( page.locator( '.g-qs .g-q.is-answered' ) ).toHaveCount( 1 );
		await shot( page, '05-answered' );

		// 4. Leave the rest: the step is done, and a confirmed tree keeps its Fill button.
		const l = await Promise.all( [ page.waitForResponse( ( res ) => /\/guide\/answer/.test( res.url() ) ), page.locator( '.g-qs .g-leave-rest' ).click() ] );
		expect( l[ 0 ].status() ).toBe( 200 );
		await expect( page.locator( '.g-cols' ) ).toHaveClass( /is-done/ );
		await expect( page.locator( '.g-card[data-card="fill"] .g-move .vgml-btn-primary' ), 'a confirmed tree can be filled again' ).toHaveText( /^Fill \d[\d,.]* pictures?$/ );
		await shot( page, '06-done' );

		// 5. Fill again: it runs (the draft the run's end cleared is the live folders), and asks nothing about the answered pictures.
		const second = await fill( page, '07-fill-again' );
		expect( second.moved + second.stayed, 'the second fill looked at the library' ).toBe( second.total );
		const again = openQuestions();
		const reAsked = again.open.flatMap( ( q ) => q.ids.filter( ( id ) => placed.includes( id ) ).map( ( id ) => `${ id } in ${ q.id }` ) );
		console.log( `      second fill: ${ again.open.length } questions open, ${ reAsked.length } about an answered picture` );
		expect( reAsked, 'no question about a picture an answer placed' ).toEqual( [] );
		await shot( page, '08-after-second-fill' );

		// 6. Unconfirm, from the Tree step.
		await page.locator( '.g-step[data-step="tree"]' ).click();
		const u = await Promise.all( [
			page.waitForResponse( ( res ) => /\/guide\/unconfirm/.test( res.url() ) ),
			page.locator( '.g-card[data-card="tree"] .g-quiet' ).filter( { hasText: 'Unconfirm' } ).click(),
		] );
		expect( u[ 0 ].status() ).toBe( 200 );
		await expect( page.locator( '.g-step[data-step="tree"]' ) ).not.toHaveClass( /is-done/ );
		expect( ( await session( page ) ).session.tree ).toBe( 'editing' );
		await shot( page, '09-unconfirmed' );

		// 7. × on a word: a top-level folder with two or more words, so the list is never empty (the open case: × on a last word).
		const live = ( await session( page ) ).nodes;
		const target = live.find( ( n ) => ! n.parent && Array.isArray( n.classes ) && n.classes.length >= 2 );
		expect( target, 'a top-level folder with two or more words' ).toBeTruthy();
		const word = target.classes[ 0 ];
		const row = page.locator( `.g-tree .vgml-node[data-key="t${ target.id }"]` );
		await expect( row.locator( `.vgml-class[data-class="${ word }"] .vgml-unclass` ) ).toBeVisible();
		await row.locator( `.vgml-class[data-class="${ word }"] .vgml-unclass` ).click();
		await expect( page.locator( '.g-change .vgml-msg.is-edit' ).last() ).toContainText( `Removed the word ${ word } from ${ target.name }` );
		// The edit reaches the session with the turn that counts it (the paste's path): the draft is null until that answers.
		await expect.poll( async () => {
			const d = ( await session( page ) ).session.draft;
			return d && d.folders ? ( d.folders.find( ( f ) => f.term_id === target.id ) || {} ).classes : null;
		}, { timeout: 90000 } ).toEqual( target.classes.filter( ( c ) => c !== word ) );
		await expect( confirm ).toBeEnabled( { timeout: 90000 } );
		await shot( page, '10-word-removed' );

		// 8. This is my tree, again: the folder's profile is without the word.
		const c2 = await Promise.all( [ page.waitForResponse( ( res ) => /\/guide\/confirm/.test( res.url() ) ), confirm.click() ] );
		expect( c2[ 0 ].status() ).toBe( 200 );
		console.log( `      second confirm: ${ ( await c2[ 0 ].json() ).profiled } folders profiled by the planner` );
		await expect.poll( async () => ( ( await session( page ) ).nodes.find( ( n ) => n.id === target.id ) || {} ).classes, { timeout: 20000 } ).toEqual( target.classes.filter( ( c ) => c !== word ) );
		await expect( page.locator( '.g-step.is-current' ) ).toHaveText( 'Fill' );
		await shot( page, '11-reconfirmed' );

		// 9. Fill, the third time.
		const third = await fill( page, '12-fill-third' );
		expect( third.seen ).toBe( third.total );
		await shot( page, '13-after-third-fill' );
	} );
} );
