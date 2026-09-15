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
check( 'a find box appears from ten folders', findShown );
await page.fill( '#folders .vgml-tv-search', 'sneak' );
const found = await page.$$eval( '#folders .vgml-node[data-key]', ( lis ) => lis.map( ( li ) => li.querySelector( '.vgml-name' ).textContent.trim() ) );
check( 'finding shows the match and the path to it, nothing else', found.join( '|' ) === 'Apparel|Men|Sneakers', found.join( ' | ' ) );
await page.fill( '#folders .vgml-tv-search', 'zzz' );
const nothing = await texts( '#folders .vgml-empty' );
check( 'no match says so', nothing.join() === 'No folder matches', nothing.join() );
await page.fill( '#folders .vgml-tv-search', '' );

const fewer = await page.evaluate( () => {
	const h = window.harness;
	const small = h.clone().filter( ( n ) => n.id <= 9 );
	const v = h.tv.create( { surface: 'folders', root: document.createElement( 'div' ), nodes: small } );
	v.setDraft( h.tv.fromLive( small ) );
	return { rows: small.length, hidden: v.findEl.hidden };
} );
check( 'and not under ten', 9 === fewer.rows && true === fewer.hidden, JSON.stringify( fewer ) );

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

// The add takes a path from its parent in the paste's grammar: what exists at that place is reused, the rest is made.
const pathAdd = await page.evaluate( () => {
	const h = window.harness;
	const d0 = h.mockDraft();
	const d1 = h.tv.applyEdit( d0, { type: 'add', parent: 't1', name: 'women > Rooftop > Terrace', by: 'you' } );
	const women = d1.folders.filter( ( f ) => 't1' === f.parent && 'women' === f.name.toLowerCase() );
	const rooftop = d1.folders.find( ( f ) => 'Rooftop' === f.name );
	const terrace = d1.folders.find( ( f ) => 'Terrace' === f.name );
	return {
		made: d1.folders.length - d0.folders.length,
		women: women.length,
		rooftopUnder: rooftop ? rooftop.parent : '',
		terraceUnder: terrace && rooftop ? terrace.parent === rooftop.key : false,
		terraceNew: terrace ? null === terrace.term_id : false,
	};
} );
check( 'an add with a path reuses the folder that exists and makes the rest under it', 2 === pathAdd.made && 1 === pathAdd.women && 't2' === pathAdd.rooftopUnder && pathAdd.terraceUnder && pathAdd.terraceNew, JSON.stringify( pathAdd ) );

// On screen: + on a row opens an editor under it; Enter is one `add` edit; the row is there, new, and its parent is open.
await page.evaluate( () => window.harness.withDraft() );
await page.hover( '#folders .vgml-node[data-key="t13"] .vgml-row' );
await page.click( '#folders .vgml-node[data-key="t13"] .vgml-add' );
const editorUnder = await page.evaluate( () => {
	const ed = document.querySelector( '#folders .vgml-node.is-adding' );
	const prev = ed && ed.previousElementSibling;
	return { there: !! ed, level: ed ? ed.getAttribute( 'aria-level' ) : '', afterArchitectureBranch: !! ( prev && ( 't15' === prev.getAttribute( 'data-key' ) || 't13' === prev.getAttribute( 'data-key' ) || prev.classList.contains( 'vgml-tv-sibs' ) ) ), focused: document.activeElement && document.activeElement.classList.contains( 'vgml-editor' ) };
} );
check( 'the + on a row opens an empty editor under that branch, one level deeper, focused', editorUnder.there && '2' === editorUnder.level && editorUnder.afterArchitectureBranch && editorUnder.focused, JSON.stringify( editorUnder ) );
await page.fill( '#folders .vgml-node.is-adding .vgml-editor', 'Bridges' );
await page.keyboard.press( 'Enter' );
const addedRow = await page.evaluate( () => {
	const h = window.harness;
	const last = h.edits[ h.edits.length - 1 ];
	const row = [ ...document.querySelectorAll( '#folders .vgml-node' ) ].find( ( n ) => 'Bridges' === ( n.querySelector( '.vgml-name' ) || {} ).textContent );
	const chip = [ ...document.querySelectorAll( '#folders .vgml-sib' ) ].find( ( c ) => c.textContent.indexOf( 'Bridges' ) === 0 );
	return { edit: last, asRow: !! row, rowNew: !! ( row && row.classList.contains( 'is-new' ) ), asChip: !! chip, chipNew: !! ( chip && chip.classList.contains( 'is-new' ) ), editors: document.querySelectorAll( '#folders .vgml-editor' ).length };
} );
check( 'Enter emits one add edit under that parent and the folder shows, new, with no editor left', addedRow.edit && 'add' === addedRow.edit.type && 't13' === addedRow.edit.parent && 'Bridges' === addedRow.edit.name && ( ( addedRow.asRow && addedRow.rowNew ) || ( addedRow.asChip && addedRow.chipNew ) ) && 0 === addedRow.editors, JSON.stringify( addedRow ) );
const editsBefore = await page.evaluate( () => window.harness.edits.length );
await page.click( '#folders .vgml-tv-add .vgml-add-top' );
const footOpened = await page.evaluate( () => ( { editors: document.querySelectorAll( '#folders .vgml-editor' ).length, level: ( document.querySelector( '#folders .vgml-node.is-adding' ) || { getAttribute: () => '' } ).getAttribute( 'aria-level' ) } ) );
await page.keyboard.press( 'Escape' );
const escaped = await page.evaluate( () => ( { editors: document.querySelectorAll( '#folders .vgml-editor' ).length, edits: window.harness.edits.length, foot: !! document.querySelector( '#folders .vgml-tv-add .vgml-add-top' ) } ) );
check( 'New folder at the foot opens a top-level editor; Escape drops it and emits nothing', 1 === footOpened.editors && '1' === footOpened.level && 0 === escaped.editors && escaped.foot && escaped.edits === editsBefore, JSON.stringify( { footOpened, escaped, editsBefore } ) );

// A chip is a folder too (the Folders screen's `siblings: true`): + on it goes one deeper and the chip becomes a row with its child; × on a row or a chip removes.
await page.evaluate( () => {
	const h = window.harness;
	const small = h.nodes.filter( ( n ) => [ 13, 14, 15, 16, 26, 27, 28 ].includes( n.id ) );
	const root = document.createElement( 'div' );
	root.id = 'chips';
	document.body.appendChild( root );
	h.chipEdits = [];
	h.chipView = h.tv.create( { surface: 'folders', root, nodes: small, siblings: true, openAll: true, editable: true, onEdit: ( edit ) => { h.chipEdits.push( edit ); h.chipView.setDraft( h.tv.applyEdit( h.chipView.getDraft(), edit ) ); } } );
	h.chipView.setMode( 'all', true );
	h.chipView.setDraft( h.tv.fromLive( small ) );
} );
const chipBefore = await page.evaluate( () => {
	const chip = document.querySelector( '#chips .vgml-sib[data-key="t14"]' );
	return { isChip: !! chip, hasAdd: !! ( chip && chip.querySelector( '.vgml-add' ) ), hasRemove: !! ( chip && chip.querySelector( '.vgml-remove' ) ) };
} );
await page.hover( '#chips .vgml-sib[data-key="t14"]' );
await page.click( '#chips .vgml-sib[data-key="t14"] .vgml-add' );
const chipEditor = await page.evaluate( () => {
	const ed = document.querySelector( '#chips .vgml-node.is-adding' );
	return { there: !! ed, level: ed ? ed.getAttribute( 'aria-level' ) : '', afterSibs: !! ( ed && ed.previousElementSibling && ed.previousElementSibling.classList.contains( 'vgml-tv-sibs' ) ) };
} );
await page.fill( '#chips .vgml-node.is-adding .vgml-editor', 'Facades' );
await page.keyboard.press( 'Enter' );
const deeper = await page.evaluate( () => {
	const h = window.harness;
	const last = h.chipEdits[ h.chipEdits.length - 1 ];
	const t14 = document.querySelector( '#chips .vgml-node[data-key="t14"]' );
	const fac = h.chipView.getDraft().folders.find( ( f ) => 'Facades' === f.name );
	return { edit: last, t14IsRow: !! t14, t14Level: t14 ? t14.getAttribute( 'aria-level' ) : '', facadesUnder: fac ? fac.parent : '', facadesShown: [ ...document.querySelectorAll( '#chips .vgml-name, #chips .vgml-sib' ) ].some( ( n ) => /^Facades/.test( n.textContent ) ) };
} );
check( 'the + on a chip opens an editor after the chips line, one level deeper; Enter makes the folder under the chip, which becomes a row', chipBefore.isChip && chipBefore.hasAdd && chipBefore.hasRemove && chipEditor.there && '3' === chipEditor.level && chipEditor.afterSibs && 'add' === deeper.edit.type && 't14' === deeper.edit.parent && deeper.t14IsRow && '2' === deeper.t14Level && 't14' === deeper.facadesUnder && deeper.facadesShown, JSON.stringify( { chipBefore, chipEditor, deeper } ) );
const leafLine = await page.evaluate( () => {
	// Bikes and cycling (t16) is a top-level leaf: nothing folds under it, so nothing is drawn under it.
	const leaf = document.querySelector( '#chips .vgml-node[data-key="t16"]' );
	const next = leaf && leaf.nextElementSibling;
	return { there: !! leaf, nextIsChipsLine: !! ( next && next.classList.contains( 'vgml-tv-sibs' ) ), emptyLines: [ ...document.querySelectorAll( '#chips .vgml-tv-sibs' ) ].filter( ( li ) => ! li.querySelector( '.vgml-sib' ) ).length };
} );
check( 'a leaf row has no chips line under it (no empty line, no second rule)', leafLine.there && ! leafLine.nextIsChipsLine && 0 === leafLine.emptyLines, JSON.stringify( leafLine ) );
await page.hover( '#chips .vgml-sib[data-key="t27"]' );
await page.click( '#chips .vgml-sib[data-key="t27"] .vgml-remove' );
const chipRemoved = await page.evaluate( () => {
	const h = window.harness;
	const last = h.chipEdits[ h.chipEdits.length - 1 ];
	return { edit: last, gone: ! h.chipView.getDraft().folders.some( ( f ) => 't27' === f.key ) };
} );
check( 'the × on a chip emits one remove edit for that folder', 'remove' === chipRemoved.edit.type && 't27' === chipRemoved.edit.key && chipRemoved.gone, JSON.stringify( chipRemoved ) );
await page.evaluate( () => document.getElementById( 'chips' ).remove() );

// Closed by default (no openAll): a closed parent says how many folders sit under it, at any depth; open, it says nothing; the find box shows from ten folders.
const closed = await page.evaluate( () => {
	const h = window.harness;
	const root = document.createElement( 'div' );
	root.id = 'closed';
	document.body.appendChild( root );
	const v = h.tv.create( { surface: 'folders', root, nodes: h.nodes, siblings: true, editable: true, onEdit: () => {} } );
	v.setMode( 'all', true );
	v.setDraft( h.tv.fromLive( h.nodes ) );
	const text = ( key ) => { const s = root.querySelector( `.vgml-node[data-key="${ key }"] .vgml-meta` ); return s ? s.textContent : null; };
	const out = { apparelClosed: root.querySelector( '.vgml-node[data-key="t1"]' ).getAttribute( 'aria-expanded' ), apparel: text( 't1' ), architecture: text( 't13' ), leaf: text( 't16' ), rowsShown: root.querySelectorAll( '.vgml-node[data-key]' ).length, find: ! v.findEl.hidden };
	v.toggle( 't1' );
	out.apparelOpen = text( 't1' );
	out.womenClosed = text( 't2' );
	// A draft that changes something under a closed parent opens that parent by itself.
	v.setDraft( h.tv.applyEdit( h.tv.fromLive( h.nodes ), { type: 'add', parent: 't26', name: 'Lamps', by: 'you' } ) );
	out.objectsOpen = root.querySelector( '.vgml-node[data-key="t26"]' ).getAttribute( 'aria-expanded' );
	out.lampsShown = [ ...root.querySelectorAll( '.vgml-name, .vgml-sib' ) ].some( ( n ) => /^Lamps/.test( n.textContent ) );
	// A proposal's counts grow under every parent; that is a change on the row (was N), not a reason to open it.
	const grown = h.tv.fromLive( h.nodes );
	grown.folders.forEach( ( f ) => { if ( 't14' === f.key ) { f.count = 99; } } );
	v.openOverride = {};
	v.setDraft( grown );
	const arch = root.querySelector( '.vgml-node[data-key="t13"]' );
	out.grownStaysClosed = arch.getAttribute( 'aria-expanded' );
	out.grownCount = ( arch.querySelector( '.vgml-count' ) || {} ).textContent;
	const small = h.tv.create( { surface: 'folders', root: document.createElement( 'div' ), nodes: h.nodes.slice( 0, 5 ) } );
	small.setDraft( h.tv.fromLive( h.nodes.slice( 0, 5 ) ) );
	out.findSmall = ! small.findEl.hidden;
	// The live tree with no draft at all (the box before a proposal): the same count on a closed parent.
	const liveRoot = document.createElement( 'div' );
	document.body.appendChild( liveRoot );
	h.tv.create( { surface: 'folders', root: liveRoot, nodes: h.nodes, siblings: true } ).render();
	const liveMeta = liveRoot.querySelector( '.vgml-node[data-key="t1"] .vgml-meta' );
	out.liveApparel = liveMeta ? liveMeta.textContent : null;
	liveRoot.remove();
	root.remove();
	return out;
} );
check( 'closed by default: a closed parent carries its folder count at any depth, an open one does not, top level only shows',
	'false' === closed.apparelClosed && '11 folders' === closed.apparel && '2 folders' === closed.architecture && null === closed.leaf && 11 === closed.rowsShown && null === closed.apparelOpen && '6 folders' === closed.womenClosed, JSON.stringify( closed ) );
check( 'a change of shape under a closed parent opens it, a change of count does not; the find box shows from ten folders and not under', 'true' === closed.objectsOpen && closed.lampsShown && 'false' === closed.grownStaysClosed && /^136/.test( closed.grownCount || '' ) && closed.find && ! closed.findSmall && '11 folders' === closed.liveApparel, JSON.stringify( closed ) );
await page.evaluate( () => window.harness.withDraft() );

const editsBeforeRemove = await page.evaluate( () => window.harness.edits.length );
await page.hover( '#folders .vgml-node[data-key="t16"] .vgml-row' );
await page.click( '#folders .vgml-node[data-key="t16"] .vgml-remove' );
const removed = await page.evaluate( () => {
	const h = window.harness;
	const last = h.edits[ h.edits.length - 1 ];
	return { edits: h.edits.length, edit: last, gone: !! h.view.getDraft().gone[ 16 ] || ! h.view.getDraft().folders.some( ( f ) => 't16' === f.key ), label: ( document.querySelector( '#folders .vgml-node[data-key="t13"] .vgml-remove' ) || {} ).getAttribute( 'aria-label' ) };
} );
check( 'the × on a row emits one remove edit for that folder, and says which', removed.edits === editsBeforeRemove + 1 && 'remove' === removed.edit.type && 't16' === removed.edit.key && removed.gone && 'Remove Architecture from the draft' === removed.label, JSON.stringify( removed ) );

// Core's rule from wp-admin/css/common.css, which the harness page does not load: the component has to beat it.
await page.addStyleTag( { content: '[role="treeitem"] span[aria-hidden] { position: absolute; }' } );
const handle = await page.evaluate( () => {
	const row = document.querySelector( '#folders .vgml-node[aria-expanded] .vgml-row' );
	const h = row.querySelector( '.vgml-handle' ), t = row.querySelector( '.vgml-twist' );
	const hb = h.getBoundingClientRect(), tb = t.getBoundingClientRect();
	return { handlePos: getComputedStyle( h ).position, overlap: hb.left < tb.right && tb.left < hb.right, handleRight: hb.left >= tb.right };
} );
check( 'the drag handle sits in the flow after the chevron, not on top of it (core\'s span[aria-hidden] rule countered)', 'static' === handle.handlePos && ! handle.overlap && handle.handleRight, JSON.stringify( handle ) );

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

/* --------------------------------------------- G  pills, and the sibling row */

/*
 *  B.3 (every-picture-a-home): on the Folders surface every count is a pill
 *  and "new" is the brand-yellow pill; with `siblings: true` a parent with up
 *  to three leaf children shows them as chips on one line. The library's
 *  rows are untouched: twist, icon, name, count, and no chip anywhere.
 *
 *  Mutation: drop 'g-pill' from the count's class in folderRow() and G1 goes
 *  red; drop the `sibs` branch from render() and G3 goes red.
 */

console.log( '\nG  pills on every count, chips for a small parent\'s children\n' );

await page.evaluate( () => window.harness.withDraft() );
await page.click( '#folders .vgml-tv-state[data-mode="all"]' );
const pillClasses = await page.$$eval( '#folders .vgml-count', ( els ) => els.map( ( e ) => e.className ) );
check( 'G1 every count on the Folders surface is a .g-pill', pillClasses.length > 0 && pillClasses.every( ( c ) => /\bg-pill\b/.test( c ) ), `${ pillClasses.length } counts, ${ pillClasses.filter( ( c ) => ! /\bg-pill\b/.test( c ) ).length } bare` );
const tagClass = await page.evaluate( () => {
	const h = window.harness;
	const root = document.createElement( 'div' );
	document.body.appendChild( root );
	const v = h.tv.create( { surface: 'folders', root, nodes: h.nodes } );
	v.setDraft( h.tv.applyEdit( h.tv.fromLive( h.nodes ), { type: 'add', name: 'Diagrams', parent: '' } ) );
	const tag = root.querySelector( '.vgml-node.is-new .vgml-tag' );
	const out = { cls: tag ? tag.className : '', inName: !! ( tag && tag.closest( '.vgml-name' ) ), text: tag ? tag.textContent : '' };
	root.remove();
	return out;
} );
check( 'G2 "new" is the yellow pill beside the name, not inside it', /\bg-pill\b/.test( tagClass.cls ) && /\bis-new\b/.test( tagClass.cls ) && ! tagClass.inName && 'new' === tagClass.text, JSON.stringify( tagClass ) );

const sibs = await page.evaluate( () => {
	const h = window.harness;
	const small = h.nodes.filter( ( n ) => [ 13, 14, 15, 26, 27, 28 ].includes( n.id ) ); // Architecture (2 leaves), Objects (2 leaves)
	const root = document.createElement( 'div' );
	document.body.appendChild( root );
	const v = h.tv.create( { surface: 'folders', root, nodes: small, siblings: true, openAll: true } );
	v.setDraft( h.tv.fromLive( small ) );
	const out = {
		attr: root.getAttribute( 'data-siblings' ),
		rows: Array.from( root.querySelectorAll( '.vgml-node[data-key]' ) ).map( ( r ) => r.getAttribute( 'data-key' ) ),
		chips: Array.from( root.querySelectorAll( '.vgml-tv-sibs .vgml-sib' ) ).map( ( c ) => c.textContent.trim() ),
		chipPills: Array.from( root.querySelectorAll( '.vgml-sib .vgml-count' ) ).map( ( c ) => c.className ),
		parentCount: root.querySelector( '.vgml-node[data-key="t13"] .vgml-count' ).textContent,
		ariaOnChips: root.querySelectorAll( '.vgml-tv-sibs [role="treeitem"], .vgml-tv-sibs [aria-level]' ).length,
	};
	root.remove();
	// The same tree without the option: rows, no chips.
	const root2 = document.createElement( 'div' );
	document.body.appendChild( root2 );
	const v2 = h.tv.create( { surface: 'folders', root: root2, nodes: small, openAll: true } );
	v2.setDraft( h.tv.fromLive( small ) );
	out.plainRows = root2.querySelectorAll( '.vgml-node[data-key]' ).length;
	out.plainChips = root2.querySelectorAll( '.vgml-tv-sibs' ).length;
	root2.remove();
	return out;
} );
check( 'G3 with siblings on, a parent of two leaves shows them as chips and not as rows',
	'row' === sibs.attr && sibs.rows.join() === 't13,t26' && sibs.chips.slice().sort().join( '|' ) === 'City architecture details31|Edison bulb lighting14|Manhattan skyline11|Phones and gadgets10', JSON.stringify( sibs ) );
check( 'G4 each chip carries its count as a pill, and the parent reads for the branch', sibs.chipPills.length === 4 && sibs.chipPills.every( ( c ) => /\bg-pill\b/.test( c ) ) && '68' === sibs.parentCount, JSON.stringify( { pills: sibs.chipPills, parent: sibs.parentCount } ) );
check( 'G5 a chip is not a row: no treeitem, no aria-level on it', 0 === sibs.ariaOnChips );
check( 'G6 without the option the same tree is six rows and no chips', 6 === sibs.plainRows && 0 === sibs.plainChips, `${ sibs.plainRows } rows, ${ sibs.plainChips } chip rows` );
const libAfter = await page.$$eval( '#library > li.vgml-node', ( lis ) => lis.map( ( li ) => Array.from( li.querySelector( '.vgml-row' ).children ).map( ( c ) => c.className ).join() ) );
check( 'G7 the library\'s rows are as they were: twist, icon, name, count -- no pill class, no chip', libAfter.every( ( r ) => /^vgml-twist[^,]*,vgml-icon[^,]*,vgml-name,vgml-count$/.test( r ) ), libAfter[ 0 ] );

check( 'no JavaScript errors on the page', 0 === errors.length, errors.join( ' / ' ) );

await browser.close();

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
