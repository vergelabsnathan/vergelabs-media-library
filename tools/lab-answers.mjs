/*
 *  The tree lab's cached bottom-up answers in the service's schema, so
 *  tools/plan-sim.mjs can run them through what follows the model today
 *  (spec-tree-planner story 5, 2026-09-29).
 *
 *      node tools/lab-answers.mjs <export.json> <cache name> <first>-<last> <out dir>
 *      node tools/lab-answers.mjs shop-all.json shop0927 41-55 lab/
 *      node tools/plan-sim.mjs shop-all.json score lab/a41.json ... lab/a55.json
 *
 *  export.json is tools/box-plan-export.php's. The lab numbered its phrases
 *  p0.. by count over the labelled pictures in id order (tools/tree-lab.mjs
 *  bottomup); a phrase is the plugin's label text, so each p-id becomes the
 *  label id plan-sim gives that text. The export must be of the pictures the
 *  lab asked about: a phrase with no label is counted and dropped. No network.
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const [ exportFile, cacheName, range, outDir ] = process.argv.slice( 2 );
const data = JSON.parse( fs.readFileSync( exportFile, 'utf8' ) );
const all = data.pictures.slice().sort( ( a, b ) => a.id - b.id );

// tools/tree-lab.mjs bottomup: the phrase list, most carried first, ties in picture order.
const classes = ( p ) => [ ...new Set( String( p.object || '' ).toLowerCase().split( /\s*[;,]\s*/ ).map( ( s ) => s.trim() ).filter( Boolean ) ) ];
const phrases = {};
for ( const p of all.filter( ( x ) => x.labelled ) ) {
	const c = classes( p );
	const k = `${ c[ 0 ] || '?' }; ${ c[ 1 ] || '' }${ p.kind && 'photo' !== p.kind ? ` [${ p.kind }]` : '' }`;
	phrases[ k ] = ( phrases[ k ] || 0 ) + 1;
}
const textOf = Object.fromEntries( Object.entries( phrases ).sort( ( a, b ) => b[ 1 ] - a[ 1 ] ).map( ( [ k ], i ) => [ `p${ i }`, k ] ) );

// tools/plan-sim.mjs: the plugin's inventory ids.
const by = {};
for ( const p of all ) {
	if ( '' !== p.label ) {
		by[ p.label ] = ( by[ p.label ] || 0 ) + 1;
	}
}
const cmp = ( a, b ) => ( a < b ? -1 : a > b ? 1 : 0 );
const idOf = Object.fromEntries( Object.entries( by ).sort( ( a, b ) => b[ 1 ] - a[ 1 ] || cmp( a[ 0 ], b[ 0 ] ) ).map( ( [ l ], i ) => [ l, `l${ i }` ] ) );

const [ first, last ] = range.split( '-' ).map( Number );
const cache = path.join( os.tmpdir(), 'vgml-tree-lab' );
fs.mkdirSync( outDir, { recursive: true } );
let written = 0, dropped = 0;
for ( let run = first; run <= last; run++ ) {
	const f = path.join( cache, `${ cacheName }-bottomup-${ run }.json` );
	if ( ! fs.existsSync( f ) ) {
		continue;
	}
	const text = JSON.parse( fs.readFileSync( f, 'utf8' ) );
	const answer = JSON.parse( text.slice( 0, text.lastIndexOf( '}' ) + 1 ) );
	const folders = answer.folders.map( ( fo ) => {
		const labels = ( fo.phrases || [] ).map( ( id ) => idOf[ textOf[ id ] ] );
		dropped += labels.filter( ( id ) => ! id ).length;
		return { name: fo.name, parent: fo.parent || '', labels: labels.filter( Boolean ) };
	} );
	fs.writeFileSync( path.join( outDir, `a${ run }.json` ), JSON.stringify( { folders } ) );
	written++;
}
console.log( `${ written } answers -> ${ outDir }, ${ dropped } phrases with no label` );
