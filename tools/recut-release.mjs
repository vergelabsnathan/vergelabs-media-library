/*
 *  Re-cut a release zip without the entries that should never have shipped.
 *
 *      node tools/recut-release.mjs <in.zip> --out <dir> --drop tickets/ --drop pnpm-lock.yaml
 *
 *  Every other entry is kept byte for byte, in the archive's own order, and
 *  written by the same deterministic writer deploy.mjs uses (tools/lib/zip.mjs).
 *  The output is named the way the shelf names its files --
 *  `<slug>-<version>-<sha256 prefix>.zip`, twelve hex characters, the slug
 *  being the archive's top directory and the version read from the main
 *  plugin file's header -- and an existing file is never overwritten (AD-1).
 *  Prints what was dropped, what was kept, the digest and the path.
 *
 *  Written for the 3.16.1 rollback target (Epic 1 retro, A-2): the build
 *  customers downloaded from 2026-09-09 to 09-19 carried tickets/*.md and
 *  pnpm-lock.yaml; the target is that build's code without them. The proof
 *  of a re-cut is `diff -r` of the two unpacked archives: nothing differs
 *  outside the dropped paths.
 */
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { readZipEntries, writeZip } from './lib/zip.mjs';

const argv = process.argv.slice( 2 );
const valueOf = ( flag ) => argv.includes( flag ) ? argv[ argv.indexOf( flag ) + 1 ] : undefined;
const input = argv.find( ( a ) => ! a.startsWith( '--' ) && a.endsWith( '.zip' ) && a !== valueOf( '--out' ) );
const outDir = valueOf( '--out' );
const drop = argv.flatMap( ( a, i ) => '--drop' === a ? [ argv[ i + 1 ] ] : [] );

if ( ! input || ! fs.existsSync( input ) || ! outDir || outDir.startsWith( '--' ) || ! drop.length || drop.some( ( d ) => ! d || d.startsWith( '--' ) ) ) {
	console.error( 'usage: node tools/recut-release.mjs <in.zip> --out <dir> --drop <path-under-the-slug> [--drop …]' );
	process.exit( 2 );
}

const entries = readZipEntries( input );
const slug = entries[ 0 ][ 0 ].split( '/' )[ 0 ];
const under = ( name ) => name.startsWith( `${ slug }/` ) ? name.slice( slug.length + 1 ) : name;

const main = entries.find( ( [ name ] ) => name === `${ slug }/${ slug }.php` );
const version = main && ( main[ 1 ].toString( 'utf8' ).match( /^\s*\*?\s*Version:\s*([^\s]+)/m ) || [] )[ 1 ];
if ( ! version ) {
	console.error( `no Version: header in ${ slug }/${ slug }.php` );
	process.exit( 1 );
}

// A path is dropped when it is the name itself or lies under it as a
// directory -- `tickets` never takes `tickets-notes.md` with it.
const dropped = [];
const kept = entries.filter( ( [ name ] ) => {
	const rel = under( name );
	const gone = drop.some( ( d ) => rel === d || rel === d.replace( /\/$/, '' ) || rel.startsWith( d.endsWith( '/' ) ? d : `${ d }/` ) );
	if ( gone ) {
		dropped.push( rel );
	}
	return ! gone;
} );

const tmp = path.join( outDir, `.recut-${ process.pid }.zip` );
fs.mkdirSync( outDir, { recursive: true } );
writeZip( tmp, kept );
const sha = createHash( 'sha256' ).update( fs.readFileSync( tmp ) ).digest( 'hex' );
const out = path.join( outDir, `${ slug }-${ version }-${ sha.slice( 0, 12 ) }.zip` );

if ( fs.existsSync( out ) ) {
	fs.unlinkSync( tmp );
	console.log( `  already on disk, left alone: ${ out }` );
} else {
	fs.renameSync( tmp, out );
}

console.log( `  in       ${ path.basename( input ) }  ${ entries.length } entries  sha256 ${ createHash( 'sha256' ).update( fs.readFileSync( input ) ).digest( 'hex' ).slice( 0, 12 ) }` );
console.log( `  dropped  ${ dropped.length }: ${ dropped.join( ', ' ) }` );
console.log( `  kept     ${ kept.length } entries, ${ slug } ${ version }` );
console.log( `  out      ${ out }` );
console.log( `  sha256   ${ sha }` );
