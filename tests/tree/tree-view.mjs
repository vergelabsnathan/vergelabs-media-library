/*
 *  The shared tree component: the draft overlay by term id, the two states,
 *  the find box, the fold rule, and the rows the media library's panel draws.
 *
 *      node tests/tree/tree-view.mjs
 *
 *  Runs against tests/tree/tree-view.html from disk -- no WordPress, no site,
 *  no box. The component takes its data as arguments, so what is tested here
 *  is exactly what both surfaces get. Screenshots of the two states go to
 *  tests/tree/shots/tree-view-{changes,all}.png.
 *
 *  Mutation checks that have been run against this suite: rebase() with the
 *  "deleted live folder becomes a new folder" line removed goes red at D2;
 *  the fold rule disabled goes red at C3.
 */

import { chromium } from 'playwright';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const HARNESS = pathToFileURL( path.join( HERE, 'tree-view.html' ) ).href;

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? '  -- ' + detail : '' }` );
};

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 940, height: 1200 } } );
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( HARNESS );
await page.waitForFunction( () => window.harness && window.harness.view );

const texts = ( sel ) => page.$$eval( sel, ( els ) => els.map( ( e ) => e.textContent.trim() ) );

/* ---------------------------------------------------- A  the library's rows */

console.log( '\nA  the rows the media library panel draws\n' );

const libRows = await page.$$eval( '#library > li.vgml-node', ( lis ) => lis.map( ( li ) => ( {
	id: li.getAttribute( 'data-id' ),
	level: li.getAttribute( 'aria-level' ),
	children: Array.from( li.querySelector( '.vgml-row' ).children ).map( ( c ) => c.className.split( ' ' )[ 0 ] ),
	count: li.querySelector( '.vgml-count' ) ? li.querySelector( '.vgml-count' ).textContent : null,
	expanded: li.getAttribute( 'aria-expanded' ),
} ) ) );

check( 'eleven top-level rows with every branch closed', 11 === libRows.length, `${ libRows.length } rows` );
check( 'a row is twist, icon, name, count and nothing else',
	libRows.every( ( r ) => [ 'vgml-twist', 'vgml-icon', 'vgml-name', 'vgml-count' ].join() === r.children.join() ),
	JSON.stringify( libRows[ 0 ].children ) );
check( 'the count is the branch total, unformatted, as the panel has always shown it',
	'3697' === libRows[ 0 ].count, `Apparel reads ${ libRows[ 0 ].count }` );
check( 'a branch says whether it is open', 'false' === libRows[ 0 ].expanded && null === libRows.find( ( r ) => '16' === r.id ).expanded );

await page.click( '#library > li[data-id="1"] .vgml-twist' );
const afterOpen = await page.$$eval( '#library > li.vgml-node', ( lis ) => lis.map( ( li ) => li.getAttribute( 'data-id' ) ) );
check( 'the twist opens the branch and its children follow in order', afterOpen.slice( 0, 3 ).join() === '1,9,2' || afterOpen.slice( 0, 3 ).join() === '1,2,9', afterOpen.slice( 0, 4 ).join() );

/* ---------------------------------------------------- B  the Changes state */

console.log( '\nB  a draft, changes first\n' );

await page.evaluate( () => window.harness.withDraft() );

const summary = await page.evaluate( () => window.harness.view.summary() );
check( 'the summary counts folders now and after', 30 === summary.now && 26 === summary.after, JSON.stringify( summary ) );
check( 'seven changes: one absorbing folder, four removed, one grown, one moved, one renamed', 8 === summary.changes, `${ summary.changes } changes` );

const mode = await page.getAttribute( '#folders .vgml-list', 'data-mode' );
check( 'Changes is the default while a draft has changes', 'changes' === mode, mode );

const states = await texts( '#folders .vgml-tv-state' );
check( 'the switch says how many of each', 'Changes 8' === states[ 0 ] && 'All 30' === states[ 1 ], states.join( ' | ' ) );

const paths = await texts( '#folders .vgml-tv-path' );
check( 'changes are grouped under their parent path, top level last',
	paths.join( '|' ) === 'Apparel / Women|Landscape and nature|Workspace|Top level', paths.join( ' | ' ) );

const gone = await texts( '#folders .vgml-node.is-gone .vgml-name' );
check( 'the four removed folders are struck through', gone.join( '|' ) === 'Meadow and blossom|Mountains and mist|Piers and sunsets|Rural farmland', gone.join( ' | ' ) );

const subs = await texts( '#folders .vgml-tv-sub' );
check( 'a removed folder says where its pictures go, on the folder itself',
	subs.includes( 'removed · 39 pictures go to Landscape and nature' ), subs.join( ' | ' ) );
check( 'a moved folder says where it came from and who moved it',
	subs.includes( 'moved from Objects, by you' ), subs.join( ' | ' ) );
check( 'a renamed folder says what it was', subs.includes( 'renamed from Shoes, by you' ), subs.join( ' | ' ) );

const portrait = await page.$eval( '#folders .vgml-node[data-key="t29"] .vgml-count', ( e ) => e.textContent );
check( 'a grown folder shows its count after Move and what it was', /^127/.test( portrait ) && /was 18$/.test( portrait ), portrait );
const landscape = await page.$eval( '#folders .vgml-node[data-key="t20"]', ( e ) => e.className + ' ' + e.querySelector( '.vgml-count' ).textContent );
check( 'the absorbing folder is a change in ink, 152 was 91', /is-change/.test( landscape ) && /152.*was 91/.test( landscape ), landscape );

const noNew = await page.$$eval( '#folders .vgml-node.is-new', ( els ) => els.length );
check( 'nothing is "new": every draft folder exists today', 0 === noNew, `${ noNew } new` );

/* ------------------------------------------------ C  the All folders state */

console.log( '\nC  the whole tree by branch\n' );

await page.click( '#folders .vgml-tv-state[data-mode="all"]' );

const top = await page.$$eval( '#folders .vgml-node[aria-level="1"]', ( lis ) => lis.map( ( li ) => ( {
	key: li.getAttribute( 'data-key' ),
	open: li.getAttribute( 'aria-expanded' ),
	meta: li.querySelector( '.vgml-meta' ) ? li.querySelector( '.vgml-meta' ).textContent : '',
	mark: li.querySelector( '.vgml-mark' ) ? li.querySelector( '.vgml-mark' ).textContent : '',
	count: li.querySelector( '.vgml-count' ) ? li.querySelector( '.vgml-count' ).textContent : '',
} ) ) );

const byKey = Object.fromEntries( top.map( ( t ) => [ t.key, t ] ) );
check( 'the top level is all there, no folding at the root', 11 === top.length, `${ top.length } rows` );
check( 'a branch holding a change opens by itself', 'true' === byKey.t1.open && 'true' === byKey.t20.open && 'true' === byKey.t30.open, JSON.stringify( [ byKey.t1.open, byKey.t20.open, byKey.t30.open ] ) );
check( 'a branch without a change stays collapsed, with its folder count and branch total',
	'false' === byKey.t13.open && '2 folders' === byKey.t13.meta && '68' === byKey.t13.count, JSON.stringify( byKey.t13 ) );
check( 'Objects, which lost a child, is collapsed with one folder below and no mark',
	'false' === byKey.t26.open && '1 folder' === byKey.t26.meta && '' === byKey.t26.mark, JSON.stringify( byKey.t26 ) );

const women = await page.$$eval( '#folders .vgml-node[aria-level="3"], #folders .vgml-node.vgml-tv-more', ( lis ) => lis.map( ( li ) => li.querySelector( '.vgml-name' ).textContent.trim() ) );
check( 'inside Women the renamed folder shows and the five unchanged siblings fold into one row',
	women.includes( 'Shoes and boots' ) && women.includes( '5 more folders, unchanged' ) && ! women.includes( 'Dresses' ), women.join( ' | ' ) );
const foldTotal = await page.$eval( '#folders .vgml-tv-more .vgml-count', ( e ) => e.textContent );
check( 'the fold carries the pictures it hides', '1,482' === foldTotal, foldTotal );

await page.click( '#folders .vgml-tv-more .vgml-row' );
const unfolded = await page.$$eval( '#folders .vgml-node[aria-level="3"]', ( lis ) => lis.map( ( li ) => li.querySelector( '.vgml-name' ).textContent.trim() ) );
check( 'opening the fold shows the siblings', unfolded.includes( 'Dresses' ) && unfolded.includes( 'Knitwear' ) && 6 === unfolded.length, unfolded.join( ' | ' ) );

await page.click( '#folders .vgml-node[data-key="t20"] > .vgml-row .vgml-twist' );
const landscapeClosed = await page.$eval( '#folders .vgml-node[data-key="t20"]', ( li ) => ( {
	open: li.getAttribute( 'aria-expanded' ),
	mark: li.querySelector( '.vgml-mark' ) ? li.querySelector( '.vgml-mark' ).textContent : '',
	meta: li.querySelector( '.vgml-meta' ) ? li.querySelector( '.vgml-meta' ).textContent : '',
} ) );
check( 'a collapsed branch holding changes carries the mark', 'false' === landscapeClosed.open && '4 changes' === landscapeClosed.mark && '4 folders' === landscapeClosed.meta, JSON.stringify( landscapeClosed ) );
await page.click( '#folders .vgml-node[data-key="t20"] > .vgml-row .vgml-twist' );

const findShown = await page.$eval( '#folders .vgml-tv-find', ( e ) => ! e.hidden );
check( 'a find box appears past twenty folders', findShown );
await page.fill( '#folders .vgml-tv-search', 'sneak' );
const found = await page.$$eval( '#folders .vgml-node[data-key]', ( lis ) => lis.map( ( li ) => li.querySelector( '.vgml-name' ).textContent.trim() ) );
check( 'finding shows the match and the path to it, nothing else', found.join( '|' ) === 'Apparel|Men|Sneakers', found.join( ' | ' ) );
await page.fill( '#folders .vgml-tv-search', 'zzz' );
const nothing = await texts( '#folders .vgml-empty' );
check( 'no match says so', nothing.join() === 'No folder matches', nothing.join() );
await page.fill( '#folders .vgml-tv-search', '' );

const fewer = await page.evaluate( () => {
	const h = window.harness;
	const small = h.clone().filter( ( n ) => n.id <= 20 );
	const v = h.tv.create( { surface: 'folders', root: document.createElement( 'div' ), nodes: small } );
	v.setDraft( h.tv.fromLive( small ) );
	return { rows: small.length, hidden: v.findEl.hidden };
} );
check( 'and not before twenty', 20 === fewer.rows && true === fewer.hidden, JSON.stringify( fewer ) );

/* ---------------------------------------- D  the draft carried by term id */

console.log( '\nD  the draft survives the library changing under it\n' );

const rebased = await page.evaluate( () => {
	const h = window.harness;
	const v = h.view;
	const next = h.clone();
	next.find( ( n ) => 20 === n.id ).name = 'Landscapes';            // renamed in the library, untouched by the draft
	next.find( ( n ) => 3 === n.id ).name = 'Footwear';               // renamed in the library, renamed by the draft too
	next.find( ( n ) => 16 === n.id ).parent = 17;                    // reparented in the library, untouched by the draft
	const without = next.filter( ( n ) => 29 !== n.id );              // Portrait deleted in the library, kept by the draft
	without.push( { id: 31, parent: 0, name: 'Video stills', slug: 'video-stills', count: 4, color: '', order: 0, private: null } ); // made in the library since
	v.setTree( without );
	const o = v.overlay;
	const d = v.getDraft();
	return {
		landscape: { name: o.rows.t20.name, status: o.rows.t20.status, renamedFrom: o.rows.t20.renamedFrom || '' },
		shoes: { name: o.rows.t3.name, status: o.rows.t3.status, renamedFrom: o.rows.t3.renamedFrom || '' },
		bikes: { parent: d.folders.find( ( f ) => 't16' === f.key ).parent, status: o.rows.t16.status },
		portrait: { term_id: d.folders.find( ( f ) => 't29' === f.key ).term_id, status: o.rows.t29.status, name: o.rows.t29.name },
		video: o.rows.t31 ? { status: o.rows.t31.status, parent: o.rows.t31.parent } : null,
		removed: Object.values( o.rows ).filter( ( r ) => 'removed' === r.status ).map( ( r ) => r.name ),
		changes: o.changes,
	};
} );

check( 'D1 a rename in the library reaches a draft folder the draft did not rename -- no removal plus addition',
	'Landscapes' === rebased.landscape.name && '' === rebased.landscape.renamedFrom && 'changed' === rebased.landscape.status, JSON.stringify( rebased.landscape ) );
check( 'D1b a draft rename stays through a library rename, and now says what it was',
	'Shoes and boots' === rebased.shoes.name && 'Footwear' === rebased.shoes.renamedFrom, JSON.stringify( rebased.shoes ) );
check( 'D1c a reparent in the library reaches a folder the draft did not move',
	't17' === rebased.bikes.parent && 'same' === rebased.bikes.status, JSON.stringify( rebased.bikes ) );
check( 'D2 a draft folder whose live folder was deleted becomes a new folder, name and place kept',
	null === rebased.portrait.term_id && 'added' === rebased.portrait.status && 'Portrait' === rebased.portrait.name, JSON.stringify( rebased.portrait ) );
check( 'D3 a folder made in the library since is adopted as kept, not shown as removed',
	rebased.video && 'same' === rebased.video.status && '' === rebased.video.parent, JSON.stringify( rebased.video ) );
check( 'D4 the four the draft removed are still the only removals',
	4 === rebased.removed.length && ! rebased.removed.includes( 'Video stills' ), rebased.removed.join( ' | ' ) );

const newTag = await page.$$eval( '#folders .vgml-node.is-new .vgml-tag', ( els ) => els.map( ( e ) => e.textContent ) );
check( 'and the screen marks it new', newTag.length >= 1 && 'new' === newTag[ 0 ], newTag.join() );

/* ------------------------------------------------------ E  hand edits */

console.log( '\nE  hand edits on a copy of the draft\n' );

const edits = await page.evaluate( () => {
	const h = window.harness;
	const d0 = h.mockDraft();
	const removed = h.tv.applyEdit( d0, { type: 'remove', key: 't26', to: 't30' } );
	const cycle = h.tv.applyEdit( d0, { type: 'reparent', key: 't1', parent: 't2' } );
	const added = h.tv.applyEdit( d0, { type: 'add', name: 'Diagrams', parent: '' } );
	return {
		phonesParent: removed.folders.find( ( f ) => 't27' === f.key ).parent,
		objectsGone: removed.gone[ 26 ],
		originalUntouched: d0.folders.some( ( f ) => 't26' === f.key ),
		apparelParent: cycle.folders.find( ( f ) => 't1' === f.key ).parent,
		addedNew: added.folders.filter( ( f ) => null === f.term_id && 'Diagrams' === f.name ).length,
	};
} );
check( 'removing a folder lifts its children to its parent and records where its pictures go',
	'' === edits.phonesParent && 't30' === edits.objectsGone && true === edits.originalUntouched, JSON.stringify( edits ) );
check( 'a folder cannot be dropped inside its own sub-folder', 't2' !== edits.apparelParent, edits.apparelParent );
check( 'an added folder has no term id', 1 === edits.addedNew );

await page.evaluate( () => window.harness.withDraft() );
await page.dblclick( '#folders .vgml-node[data-key="t29"] .vgml-name' );
await page.fill( '#folders .vgml-editor', 'People' );
await page.keyboard.press( 'Enter' );
const renamed = await page.evaluate( () => ( { edits: window.harness.edits.slice( -1 ), name: window.harness.view.overlay.rows.t29.name } ) );
check( 'renaming in place emits one edit and no prompt', 'rename' === renamed.edits[ 0 ].type && 'People' === renamed.edits[ 0 ].to && 'People' === renamed.name, JSON.stringify( renamed ) );

/* ----------------------------------------------------------- F  pictures */

console.log( '\nF  the two states, for the record\n' );

await page.evaluate( () => window.harness.withDraft() );
await page.click( '#folders .vgml-tv-state[data-mode="changes"]' );
await page.waitForTimeout( 300 );
await page.screenshot( { path: path.join( HERE, 'shots', 'tree-view-changes.png' ), fullPage: true } );
await page.click( '#folders .vgml-tv-state[data-mode="all"]' );
await page.waitForTimeout( 300 );
await page.hover( '#folders .vgml-node[data-key="t20"] > .vgml-row' );
await page.waitForTimeout( 200 );
const hover = await texts( '#folders .vgml-tv-hover li' );
check( 'hover on a changed folder: pictures after Move and where they come from',
	hover[ 0 ] === '152 pictures after Move' && /61 from Meadow and blossom, Mountains and mist/.test( hover[ 1 ] || '' ), hover.join( ' | ' ) );
await page.screenshot( { path: path.join( HERE, 'shots', 'tree-view-all.png' ), fullPage: true } );
check( 'screenshots written', true, 'tests/tree/shots/tree-view-{changes,all}.png' );

check( 'no JavaScript errors on the page', 0 === errors.length, errors.join( ' / ' ) );

await browser.close();

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
