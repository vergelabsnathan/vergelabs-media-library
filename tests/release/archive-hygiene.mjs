/*
 *  One ship list: what the release archive carries is what the Playground zip
 *  and the box carry, and none of it is repo furniture.
 *
 *      node tests/release/archive-hygiene.mjs
 *
 *  Runs from disk -- no site, no box. `git archive HEAD` is cut the way a
 *  release is (.gitattributes export-ignore); playground/vergelabs-media-library.zip
 *  is what tools/deploy.mjs builds and the public Playground link installs.
 *  The two came from different lists until 2026-09-20, and the 4.0.0 release
 *  archive went out with `.harness/active.json` in it while the Playground zip
 *  and the box carried 32 files from `_bmad*` (Epic 1 retro, A-1). Plugin
 *  Check refuses a hidden file in a zip, and nothing in the battery read an
 *  archive's entry list.
 *
 *  Four things are asserted: no entry of either archive has a path segment
 *  starting with `.` or `_`; no top-level entry is repo furniture by name --
 *  the served 3.16.1 leaked `tickets/` and `pnpm-lock.yaml`, neither hidden,
 *  and both archives now read one list, so a dropped export-ignore line
 *  would otherwise ship in both and still agree (review, 2026-09-20); the
 *  two hold the same set of files; and the Playground zip is the tree's own
 *  (deploy.mjs --check, so a stale zip is red here too rather than only in a
 *  deploy nobody ran).
 *
 *  `git archive HEAD` reads the committed .gitattributes, the Playground zip
 *  the working tree's: on a dirty tree the "same files" line says so, so a
 *  red there names its likely cause before it is read as a leak.
 *
 *  Mutation: add `.harness/x` to the tree and commit it without an
 *  export-ignore line -- the first check names it; remove `/tickets` from
 *  .gitattributes with a tickets/ file committed -- the furniture check does.
 */

import { execFileSync, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { readZipIndex } from '../../tools/lib/zip.mjs';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const SLUG = 'vergelabs-media-library';
const PLAYGROUND_ZIP = path.join( ROOT, 'playground', `${ SLUG }.zip` );

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? '  -- ' + detail : '' }` );
};

const hidden = ( name ) => name.split( '/' ).some( ( seg ) => seg && ( seg.startsWith( '.' ) || seg.startsWith( '_' ) ) );

// Repo furniture by name, none of it hidden: what the SKIP lists in
// deploy.mjs and the export-ignore lines exist to keep out of a customer's
// hands. A new top-level directory of that kind belongs here as well.
const FURNITURE = new Set( [
	'tests', 'tools', 'docs', 'plans', 'tickets', 'research', 'dist', 'site', 'playground', 'node_modules', 'assets',
	'package.json', 'package-lock.json', 'pnpm-lock.yaml', 'CLAUDE.md', 'AGENTS.md',
] );
const furniture = ( name ) => FURNITURE.has( name.split( '/' )[ 0 ] );

// The tree as it is: a dirty tree is the usual reason the two archives differ.
const dirty = execFileSync( 'git', [ 'status', '--porcelain' ], { cwd: ROOT } ).toString().trim();

// Files only: git archive writes a directory entry per folder, deploy.mjs none.
const filesOf = ( index, prefix ) => [ ...index.keys() ]
	.filter( ( n ) => ! n.endsWith( '/' ) )
	.map( ( n ) => n.startsWith( prefix ) ? n.slice( prefix.length ) : n )
	.sort();

const work = fs.mkdtempSync( path.join( os.tmpdir(), 'vgml-archive-' ) );
const releaseZip = path.join( work, 'release.zip' );

execFileSync( 'git', [ 'archive', 'HEAD', '--format=zip', `--prefix=${ SLUG }/`, '-o', releaseZip ], { cwd: ROOT, stdio: 'pipe' } );

const release = readZipIndex( releaseZip );
const playground = readZipIndex( PLAYGROUND_ZIP );

check( 'git archive HEAD is a readable zip', null !== release && release.size > 0, release ? `${ release.size } entries` : 'unreadable' );
check( 'playground/vergelabs-media-library.zip is a readable zip', null !== playground && playground.size > 0, playground ? `${ playground.size } entries` : 'unreadable' );

if ( release && playground ) {

	const releaseFiles = filesOf( release, `${ SLUG }/` );
	const playgroundFiles = filesOf( playground, `${ SLUG }/` );

	const strayRelease = releaseFiles.filter( hidden );
	check( 'no release entry starts with . or _ (any segment)', 0 === strayRelease.length, strayRelease.slice( 0, 5 ).join( ', ' ) || `${ releaseFiles.length } files` );

	const strayPlayground = playgroundFiles.filter( hidden );
	check( 'no Playground zip entry starts with . or _ (any segment)', 0 === strayPlayground.length, strayPlayground.slice( 0, 5 ).join( ', ' ) || `${ playgroundFiles.length } files` );

	const furnitureIn = [ ...new Set( [ ...releaseFiles, ...playgroundFiles ].filter( furniture ).map( ( f ) => f.split( '/' )[ 0 ] ) ) ];
	check( 'no top-level entry of either archive is repo furniture by name', 0 === furnitureIn.length, furnitureIn.join( ', ' ) || `${ FURNITURE.size } names checked` );

	const onlyRelease = releaseFiles.filter( ( f ) => ! playgroundFiles.includes( f ) );
	const onlyPlayground = playgroundFiles.filter( ( f ) => ! releaseFiles.includes( f ) );
	check(
		'the release and the Playground zip hold the same files',
		0 === onlyRelease.length && 0 === onlyPlayground.length,
		onlyRelease.length || onlyPlayground.length
			? `only in the release: ${ onlyRelease.slice( 0, 4 ).join( ', ' ) || 'none' }; only in the Playground zip: ${ onlyPlayground.slice( 0, 4 ).join( ', ' ) || 'none' }${ dirty ? ' -- the tree is dirty (HEAD vs working tree), commit and re-run before reading this as a leak' : '' }`
			: `${ releaseFiles.length } files each${ dirty ? ' (tree dirty)' : '' }`
	);

	check( 'the main plugin file is in both', releaseFiles.includes( `${ SLUG }.php` ) && playgroundFiles.includes( `${ SLUG }.php` ) );
}

// The zip on disk is the tree's own -- deploy.mjs compares CRC per entry.
const deploy = spawnSync( process.execPath, [ path.join( ROOT, 'tools', 'deploy.mjs' ), '--check', '--zip' ], { cwd: ROOT, encoding: 'utf8' } );
const zipLine = ( deploy.stdout || '' ).split( /\r?\n/ ).find( ( l ) => /^\s*zip\s/.test( l ) ) || 'no zip line';
check( 'deploy.mjs --check says the Playground zip is up to date', /zip\s+up to date/.test( zipLine ), zipLine.trim() );

fs.rmSync( work, { recursive: true, force: true } );

const passed = results.filter( ( r ) => r.ok ).length;
console.log( `\n${ passed }/${ results.length } passed\n` );
process.exit( passed === results.length ? 0 : 1 );
