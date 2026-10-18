/*
 *  The release zip, installed and exercised the way a reviewer would.
 *
 *      node tools/runtime-checks.mjs [dist/vergelabs-media-library-4.1.0.zip]
 *
 *  Each scenario boots a fresh Playground with WP_DEBUG on, installs the zip
 *  itself through installPlugin (an upload, not a mounted folder), and runs the
 *  phases of one file in tests/runtime/, each as its own request so a later
 *  phase finds the plugin loaded the way WordPress loads it. A scenario passes
 *  when every phase prints "phase: n/n passed" and debug.log stays empty.
 *  Multisite is not here: a network refuses Playground's random port, so it runs on
 *  the box (node tools/multisite.mjs). Red-first, 2026-10-12: the safe-mode scenario
 *  on the round-5 zip ends in the term-count fatal; on 7158315 it passes.
 *  Written for wordpress.org round 6 (2026-10-12): the runtime half of the
 *  activation checklist, which no gate covered before.
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { PLAYGROUND_CLI } from './lib/playground.mjs';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const ZIP = path.resolve( ROOT, process.argv[ 2 ] || 'dist/vergelabs-media-library-4.1.0.zip' );

const LIFE = [ 'activate', 'work', 'cycle', 'delete' ];
const SCENARIOS = [
	{ name: 'PHP floor: every call exists on PHP 7.4 without a WordPress polyfill', file: 'php-floor.php', phases: [ 'scan' ], php: '7.4', wp: '6.5' },
	{ name: 'a parent loop in the terms table, every folder walk, WP 6.5 / PHP 7.4', file: 'term-loop.php', phases: [ 'activate', 'loop' ], php: '7.4', wp: '6.5' },
	{ name: 'lifecycle on WP 6.5 / PHP 7.4', file: 'lifecycle.php', phases: LIFE, php: '7.4', wp: '6.5' },
	{ name: 'lifecycle on latest / PHP 8.3', file: 'lifecycle.php', phases: LIFE, php: '8.3', wp: 'latest' },
	// "Some recent versions of PHP may be stricter" (round 7 email): the newest PHP Playground ships.
	{ name: 'lifecycle on latest / PHP 8.5', file: 'lifecycle.php', phases: LIFE, php: '8.5', wp: 'latest' },
	{ name: 'lifecycle with remove-everything on, latest / PHP 8.3', file: 'lifecycle.php', phases: LIFE, php: '8.3', wp: 'latest', consts: { RT_WIPE: true } },
	{ name: 'safe mode on latest / PHP 8.3', file: 'safe-mode.php', phases: [ 'activate', 'work' ], php: '8.3', wp: 'latest', consts: { VERGEML_SAFE_MODE: true } },
	{ name: 'safe mode on WP 6.5 / PHP 7.4', file: 'safe-mode.php', phases: [ 'activate', 'work' ], php: '7.4', wp: '6.5', consts: { VERGEML_SAFE_MODE: true } },
];

const PHASE_LINE = /^(\w+): (\d+)\/(\d+) passed$/gm;

function run( scenario ) {
	return new Promise( ( resolve ) => {
		// No spaces and no emoji in the mounts: cmd.exe hands them to the CLI intact.
		const work = fs.mkdtempSync( path.join( os.tmpdir(), 'vgml-rt-' ) );
		const zips = path.join( work, 'zips' );
		const checks = path.join( work, 'checks' );
		const out = path.join( work, 'out' );
		for ( const d of [ zips, checks, out ] ) fs.mkdirSync( d );
		fs.copyFileSync( ZIP, path.join( zips, 'plugin.zip' ) );
		for ( const f of fs.readdirSync( path.join( ROOT, 'tests', 'runtime' ) ) ) {
			fs.copyFileSync( path.join( ROOT, 'tests', 'runtime', f ), path.join( checks, f ) );
		}

		const phaseStep = ( phase ) => ( {
			step: 'runPHP',
			code: "<?php define( 'RT_PHASE', '" + phase + "' ); ob_start(); register_shutdown_function( function () { file_put_contents( '/out/result.txt', ob_get_clean(), FILE_APPEND ); } ); require_once '/wordpress/wp-load.php'; file_put_contents( '/out/versions.txt', PHP_VERSION . ' ' . get_bloginfo( 'version' ) ); require '/checks/" + scenario.file + "';",
		} );

		const steps = [
			{ step: 'defineWpConfigConsts', consts: { WP_DEBUG: true, WP_DEBUG_LOG: '/out/debug.log', WP_DEBUG_DISPLAY: false, ...( scenario.consts || {} ) } },
			...( scenario.multisite ? [ { step: 'enableMultisite' } ] : [] ),
			{ step: 'installPlugin', pluginData: { resource: 'vfs', path: '/zips/plugin.zip' }, options: { activate: false } },
			...scenario.phases.map( phaseStep ),
		];
		// run-blueprint ignores --php and --wp and boots the newest of both (8.5 / 7.1 on
		// 2026-10-16); only preferredVersions is honoured, and versions.txt proves it.
		fs.writeFileSync( path.join( work, 'blueprint.json' ), JSON.stringify( { preferredVersions: { php: scenario.php, wp: scenario.wp }, steps } ) );

		const child = spawn( 'npx', [ '-y', PLAYGROUND_CLI, 'run-blueprint', `--php=${ scenario.php }`, `--wp=${ scenario.wp }`,
			'--blueprint', path.join( work, 'blueprint.json' ),
			'--mount-dir', zips, '/zips', '--mount-dir', checks, '/checks', '--mount-dir', out, '/out',
			'--verbosity', 'quiet' ], { shell: true, stdio: [ 'ignore', 'ignore', 'pipe' ], env: { ...process.env, MSYS_NO_PATHCONV: '1' } } );
		let err = '';
		child.stderr.on( 'data', ( d ) => { err += d; } );
		child.on( 'close', () => {
			const said = fs.existsSync( path.join( out, 'result.txt' ) ) ? fs.readFileSync( path.join( out, 'result.txt' ), 'utf8' ) : '';
			const log = fs.existsSync( path.join( out, 'debug.log' ) ) ? fs.readFileSync( path.join( out, 'debug.log' ), 'utf8' ).trim() : '';
			const lines = [ ...said.matchAll( PHASE_LINE ) ];
			const ran = fs.existsSync( path.join( out, 'versions.txt' ) ) ? fs.readFileSync( path.join( out, 'versions.txt' ), 'utf8' ) : '';
			const [ ranPhp = '', ranWp = '' ] = ran.split( ' ' );
			const versionsOk = ranPhp.startsWith( scenario.php + '.' ) && ( 'latest' === scenario.wp || ranWp.startsWith( scenario.wp ) );
			const ok = versionsOk && lines.length === scenario.phases.length && lines.every( ( m ) => m[ 2 ] === m[ 3 ] ) && '' === log;
			console.log( `\n──────── ${ scenario.name }\n  ran on PHP ${ ranPhp || '?' }, WP ${ ranWp || '?' }${ versionsOk ? '' : `  -- FAIL, asked for PHP ${ scenario.php }, WP ${ scenario.wp }` }\n${ said.trim() || '(no output)' }` );
			console.log( `  phases reported: ${ lines.length } of ${ scenario.phases.length }` );
			console.log( `  debug.log: ${ log ? log.split( '\n' ).length + ' line(s)\n' + log.split( '\n' ).slice( 0, 8 ).join( '\n' ) : 'empty' }` );
			if ( lines.length < scenario.phases.length && err ) {
				console.log( err.split( '\n' ).filter( ( l ) => ! /lockWholeFile/.test( l ) ).slice( -10 ).join( '\n' ) );
			}
			resolve( ok );
		} );
	} );
}

if ( ! fs.existsSync( ZIP ) ) {
	console.error( `No zip at ${ ZIP }` );
	process.exit( 2 );
}
console.log( `runtime checks on ${ path.relative( ROOT, ZIP ) }` );
const only = process.env.RT_ONLY ? new RegExp( process.env.RT_ONLY, 'i' ) : null;
const results = [];
for ( const s of SCENARIOS.filter( ( x ) => ! only || only.test( x.name ) ) ) {
	results.push( [ s.name, await run( s ) ] );
}
console.log( '\n════════ summary' );
for ( const [ name, ok ] of results ) console.log( `  ${ ok ? 'passed' : 'FAILED' }  ${ name }` );
process.exit( results.every( ( [ , ok ] ) => ok ) ? 0 : 1 );
