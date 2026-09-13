/*
 *  The paste reader: one folder per line, the full path with ">" between
 *  levels, read into the same draft the conversation builds.
 *
 *      node tests/tree/structure.mjs
 *
 *  Runs against tests/tree/structure.html from disk -- no WordPress, no site,
 *  no box. What is proven is the spec's own line
 *  (docs/superpowers/specs/2026-09-10-folders-ways-in.md, "How it is proven"):
 *  a paste of paths in any order, with a repeated path and a missing parent,
 *  produces the one tree the paths describe; an indented paste is read as
 *  top-level lines, never as depth; 501 folders are refused, and it says why.
 *
 *  Mutation checks that have been run against this suite: leading whitespace
 *  read as depth goes red at B2; the 500 cap removed goes red at C3; a name
 *  matched case-sensitively goes red at D1.
 */

import { chromium } from 'playwright';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const HARNESS = pathToFileURL( path.join( HERE, 'structure.html' ) ).href;

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? '  -- ' + detail : '' }` );
};

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 560, height: 900 } } );
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( HARNESS );
await page.waitForFunction( () => window.harness && window.harness.view );

const parse = ( text ) => page.evaluate( ( t ) => {
	const p = window.harness.structure.parse( t, window.harness.nodes );
	return { ...p, paths: p.folders.map( ( f ) => f.path.join( ' > ' ) ), keys: p.folders.map( ( f ) => f.key ), parents: p.folders.map( ( f ) => f.parent ) };
}, text );

/* -------------------------------------------- A  one tree from the paths */

console.log( '\nA  one tree from the paths, whatever their order\n' );

const TREE = 'Hardware > Phones\nHardware > Components > Chips\nData centres > Cooling\nHardware\nRobotics';
const a = await parse( TREE );

check( 'A1 every folder on a path is made once: 5 lines make 7 folders', 7 === a.count, `${ a.count }: ${ a.paths.join( ' | ' ) }` );
check( 'A2 a parent that is not listed is created, before its child',
	a.paths.indexOf( 'Hardware' ) < a.paths.indexOf( 'Hardware > Phones' ) && a.paths.includes( 'Hardware > Components' ) && a.paths.includes( 'Data centres' ),
	a.paths.join( ' | ' ) );
check( 'A3 a child points at its parent\'s key', a.parents[ a.paths.indexOf( 'Hardware > Components > Chips' ) ] === a.keys[ a.paths.indexOf( 'Hardware > Components' ) ], JSON.stringify( a.parents ) );
check( 'A4 the depth is counted from the paths: 3 levels', 3 === a.levels, `${ a.levels }` );
check( 'A5 a line with no ">" is a top-level folder, not an error', a.paths.includes( 'Robotics' ) && 0 === a.refusals.length && '' === a.parents[ a.paths.indexOf( 'Robotics' ) ] );

const shuffled = await parse( 'Robotics\nData centres > Cooling\nHardware > Components > Chips\nHardware > Phones\nHardware' );
check( 'A6 the same paths in another order are the same seven folders with the same keys',
	7 === shuffled.count && shuffled.keys.slice().sort().join() === a.keys.slice().sort().join(), shuffled.keys.join( ' ' ) );

const twice = await parse( TREE + '\nHardware > Phones\nhardware > phones\n\n   \n' );
check( 'A7 the same path twice is one folder, case-insensitively; blank lines are skipped', 7 === twice.count && 0 === twice.refusals.length, `${ twice.count } folders, ${ twice.refusals.length } refusals` );

/* ----------------------------------------------- B  whitespace is not depth */

console.log( '\nB  indentation is not read\n' );

const indented = await parse( 'Hardware\n\tPhones\n    Laptops\n\t\tChips' );
check( 'B1 an indented paste is four top-level lines', 4 === indented.count && 1 === indented.levels, `${ indented.count } folders, ${ indented.levels } level(s)` );
check( 'B2 none of them has a parent', indented.parents.every( ( p ) => '' === p ), JSON.stringify( indented.parents ) );

const spaced = await parse( '  Hardware  >   Phones  \nHardware >> Laptops\n> Energy' );
check( 'B3 segments are trimmed and empty segments dropped: "Hardware >> Laptops" is Hardware > Laptops, "> Energy" is Energy',
	spaced.paths.includes( 'Hardware > Phones' ) && spaced.paths.includes( 'Hardware > Laptops' ) && spaced.paths.includes( 'Energy' ) && 0 === spaced.refusals.length,
	spaced.paths.join( ' | ' ) );

const slashes = await parse( 'Music > AC/DC' );
check( 'B4 a slash in a name is not a level; it becomes a dash, as the server would make it', slashes.paths.includes( 'Music > AC-DC' ) && 2 === slashes.count, slashes.paths.join( ' | ' ) );

/* -------------------------------------------------------- C  the refusals */

console.log( '\nC  what is refused, and said out loud\n' );

const empty = await parse( 'Hardware\n>\n > > \nSpace' );
check( 'C1 a line of nothing but ">" has no name, and says which line',
	2 === empty.refusals.length && 'Line 2: no name' === empty.refusals[ 0 ].text && 'Line 3: no name' === empty.refusals[ 1 ].text,
	empty.refusals.map( ( r ) => r.text ).join( ' | ' ) );
check( 'C1b the lines around it are still read', 2 === empty.count, `${ empty.count }` );

const deep = await parse( 'A > B > C > D > E\nA > B > C > D > E > F' );
check( 'C2 five levels are allowed; the sixth is refused by line',
	5 === deep.count && 1 === deep.refusals.length && 'Line 2: deeper than 5 levels' === deep.refusals[ 0 ].text,
	deep.refusals.map( ( r ) => r.text ).join( ' | ' ) );

const many = Array.from( { length: 501 }, ( _, i ) => `Folder ${ i + 1 }` ).join( '\n' );
const capped = await parse( many );
check( 'C3 501 folders are refused, with the reason',
	501 === capped.count && 1 === capped.refusals.length && '501 folders; 500 is the most one paste can hold' === capped.refusals[ 0 ].text,
	capped.refusals.map( ( r ) => r.text ).join( ' | ' ) );
const fiveHundred = await parse( Array.from( { length: 500 }, ( _, i ) => `Folder ${ i + 1 }` ).join( '\n' ) );
check( 'C3b 500 is not', 500 === fiveHundred.count && 0 === fiveHundred.refusals.length );

/* ------------------------------------------------- D  reuse of what exists */

console.log( '\nD  a folder that exists is reused, at that place in the tree\n' );

const reuse = await parse( 'objects > phones and gadgets\nObjects > Cameras\nPhones and gadgets' );
const byPath = Object.fromEntries( reuse.folders.map( ( f ) => [ f.path.join( ' > ' ), f ] ) );
check( 'D1 "objects > phones and gadgets" is the live folder, case-insensitively, with its id and its own name',
	byPath[ 'Objects > Phones and gadgets' ] && 27 === byPath[ 'Objects > Phones and gadgets' ].term_id && 't27' === byPath[ 'Objects > Phones and gadgets' ].key,
	JSON.stringify( Object.keys( byPath ) ) );
check( 'D2 Objects itself is reused on the way', byPath.Objects && 26 === byPath.Objects.term_id );
check( 'D3 a new child of a live folder is new, under the live folder\'s key', byPath[ 'Objects > Cameras' ] && null === byPath[ 'Objects > Cameras' ].term_id && 't26' === byPath[ 'Objects > Cameras' ].parent );
check( 'D4 the same name at the top level is a different place, so a new folder', byPath[ 'Phones and gadgets' ] && null === byPath[ 'Phones and gadgets' ].term_id );
check( 'D5 the count says what exists: 2 of 4', 4 === reuse.count && 2 === reuse.reused, `${ reuse.reused } of ${ reuse.count }` );

/* -------------------------------------------------- E  the draft it makes */

console.log( '\nE  the draft, over the live tree\n' );

const draft = await page.evaluate( ( t ) => {
	const h = window.harness;
	const p = h.structure.parse( t, h.nodes );
	const d = h.structure.toDraft( p, h.nodes );
	const o = h.tv.overlay( new h.tv.Model( h.nodes ), h.tv.normaliseDraft( d ) );
	return {
		folders: d.folders.length,
		live: d.folders.filter( ( f ) => f.term_id ).length,
		added: Object.values( o.rows ).filter( ( r ) => 'added' === r.status ).map( ( r ) => r.name ),
		removed: Object.values( o.rows ).filter( ( r ) => 'removed' === r.status ).length,
		changed: Object.values( o.rows ).filter( ( r ) => 'changed' === r.status ).length,
		origin: d.origin,
		gone: Object.keys( d.gone ).length,
	};
}, 'Objects > Cameras\nEnergy > Solar' );
check( 'E1 every live folder is kept and every new one added: 8 live + 3 new', 11 === draft.folders && 8 === draft.live, JSON.stringify( draft ) );
check( 'E2 the tree marks exactly the new ones, removes nothing, changes nothing else',
	draft.added.slice().sort().join( '|' ) === 'Cameras|Energy|Solar' && 0 === draft.removed && 0 === draft.changed, JSON.stringify( draft ) );
check( 'E3 the draft is the conversation\'s shape: origin talk, nothing gone', 'talk' === draft.origin && 0 === draft.gone );

/* --------------------------------------------- F  the preview beside the box */

console.log( '\nF  the preview is the tree\'s rows\n' );

const shown = await page.evaluate( ( t ) => {
	const p = window.harness.show( t );
	const rows = Array.from( document.querySelectorAll( '#preview .vgml-node[data-key]' ) ).map( ( li ) => ( {
		name: li.querySelector( '.vgml-name' ).firstChild.textContent,
		level: li.getAttribute( 'aria-level' ),
		isNew: li.classList.contains( 'is-new' ),
		open: li.getAttribute( 'aria-expanded' ),
		count: li.querySelector( '.vgml-count' ) ? li.querySelector( '.vgml-count' ).textContent : null,
		row: !! li.querySelector( ':scope > .vgml-row' ),
	} ) );
	return { count: p.count, rows, bullets: document.querySelectorAll( '#preview li:not(.vgml-node)' ).length };
}, 'Objects > Phones and gadgets\nObjects > Cameras\nEnergy > Solar\nEnergy > Wind' );
check( 'F1 every folder is a .vgml-row inside a .vgml-node, and nothing is a bullet', 6 === shown.rows.length && shown.rows.every( ( r ) => r.row ) && 0 === shown.bullets, `${ shown.rows.length } rows, ${ shown.bullets } bullets` );
check( 'F2 every branch is open and nothing is folded', shown.rows.filter( ( r ) => 'true' === r.open ).length === 2 && 6 === shown.rows.length, JSON.stringify( shown.rows.map( ( r ) => [ r.name, r.open ] ) ) );
check( 'F3 a folder that exists reads as itself; a new one wears the new mark',
	shown.rows.find( ( r ) => 'Phones and gadgets' === r.name ) && ! shown.rows.find( ( r ) => 'Phones and gadgets' === r.name ).isNew && shown.rows.find( ( r ) => 'Cameras' === r.name ).isNew && shown.rows.find( ( r ) => 'Energy' === r.name ).isNew,
	JSON.stringify( shown.rows.map( ( r ) => [ r.name, r.isNew ] ) ) );
check( 'F4 no row carries a count: nothing has been counted', shown.rows.every( ( r ) => null === r.count ), JSON.stringify( shown.rows.map( ( r ) => r.count ) ) );
check( 'F5 the levels are the paths\' levels', '2' === shown.rows.find( ( r ) => 'Cameras' === r.name ).level && '1' === shown.rows.find( ( r ) => 'Energy' === r.name ).level );

await page.screenshot( { path: path.join( HERE, 'shots', 'structure-preview.png' ), fullPage: true } );
check( 'screenshot written', true, 'tests/tree/shots/structure-preview.png' );

check( 'no JavaScript errors on the page', 0 === errors.length, errors.join( ' / ' ) );

await browser.close();

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
