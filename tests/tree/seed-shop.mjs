/*
 *  The second library's seed agrees with its own catalogue.
 *
 *      node tests/tree/seed-shop.mjs
 *
 *  tools/box-seed-shop.php fetches product photos by subject and says, for
 *  each subject, the leaf of tools/box-seed-shop-tree.txt the shop would keep
 *  it in. That column is the sheet's "right answer" for the second library
 *  (every-picture-a-home C.5), so a subject that names a folder the tree does
 *  not have, or a parent instead of a leaf, would mark the engine wrong for a
 *  fault of the fixture. Reads both files from disk; no site, no box.
 *
 *  Mutation check run against this suite: "Shoes > Sports > Running" changed
 *  to "Shoes > Sports > Runners" in the seed goes red at the leaf row.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const ROOT = path.resolve( HERE, '..', '..' );

const tree = fs.readFileSync( path.join( ROOT, 'tools', 'box-seed-shop-tree.txt' ), 'utf8' )
	.split( /\r?\n/ ).map( ( l ) => l.trim() ).filter( Boolean );
const seed = fs.readFileSync( path.join( ROOT, 'tools', 'box-seed-shop.php' ), 'utf8' );

/** query => path, read off the PHP table. */
const subjects = [ ...seed.matchAll( /^\s*'([^']+)'\s*=>\s*'([^']+)',\s*$/gm ) ]
	.map( ( m ) => [ m[ 1 ], m[ 2 ] ] )
	.filter( ( [ , p ] ) => p.includes( ' > ' ) );

const set = new Set( tree );
const leaves = new Set( tree.filter( ( p ) => ! tree.some( ( q ) => q !== p && q.startsWith( p + ' > ' ) ) ) );

const results = [];
function check( what, ok, detail = '' ) {
	results.push( ok );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' }  ${ what }${ ok || ! detail ? '' : `  -- ${ detail }` }` );
}

console.log( `\nthe shop seed against its catalogue (${ tree.length } folders, ${ leaves.size } leaves, ${ subjects.length } subjects)\n` );

check( 'the seed has subjects', subjects.length >= 60, `${ subjects.length } found` );
check( 'the catalogue is the shape the plan asks for: 300+ folders, 3 levels', tree.length >= 300 && Math.max( ...tree.map( ( p ) => p.split( ' > ' ).length ) ) === 3, `${ tree.length } folders` );
check( 'every parent named in a path is its own line', tree.every( ( p ) => {
	const parts = p.split( ' > ' );
	return parts.length === 1 || set.has( parts.slice( 0, -1 ).join( ' > ' ) );
} ) );
check( 'no folder twice', new Set( tree ).size === tree.length );

const missing = subjects.filter( ( [ , p ] ) => ! set.has( p ) );
check( 'every subject names a folder in the tree', missing.length === 0, missing.map( ( [ q, p ] ) => `${ q } -> ${ p }` ).join( ', ' ) );

const notLeaf = subjects.filter( ( [ , p ] ) => set.has( p ) && ! leaves.has( p ) );
check( 'every subject names a leaf, never a parent', notLeaf.length === 0, notLeaf.map( ( [ q, p ] ) => `${ q } -> ${ p }` ).join( ', ' ) );

const twice = subjects.map( ( [ q ] ) => q ).filter( ( q, i, a ) => a.indexOf( q ) !== i );
check( 'no subject searched twice', twice.length === 0, twice.join( ', ' ) );

/*
 *  The collisions are the point of this shape: the same leaf name under
 *  more than one parent, so a picture that says "sneakers" has to be told
 *  apart by its parent or asked about. They must stay in the tree.
 */
const byLeaf = {};
tree.forEach( ( p ) => { const leaf = p.split( ' > ' ).pop(); ( byLeaf[ leaf ] = byLeaf[ leaf ] || [] ).push( p ); } );
const shared = Object.entries( byLeaf ).filter( ( [ , ps ] ) => ps.length > 1 );
check( 'the catalogue keeps its shared leaf names (the 1/k test)', shared.length >= 8, `${ shared.length } shared: ${ shared.map( ( [ l ] ) => l ).join( ', ' ) }` );

const passed = results.filter( Boolean ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
