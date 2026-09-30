/*
 *  PHPCS with the WordPress Coding Standards, on the archive that ships.
 *
 *  Why this exists: Plugin Check in Playground said "No errors found" for
 *  4.1.0 and the wordpress.org review found 62 unescaped outputs. Every
 *  Plugin Check check that is built on PHPCS -- late_escaping, i18n_usage,
 *  prefixing, enqueued_resources, direct_db, setting_sanitization and more --
 *  answers HTTP 400 under php-wasm:
 *
 *      file_put_contents(): Exclusive locks are not supported for this stream
 *      in .../php_codesniffer/src/Reporter.php on line 303
 *
 *  and the Plugin Check screen shows the checks that did run. A canary plugin
 *  that does `echo $_GET['x']` came back with no escaping finding at all.
 *
 *  So this runs PHPCS itself, in the same php-wasm, from source with that one
 *  LOCK_EX removed, against a fresh `git archive` -- never the working tree.
 *  It runs twice: once honouring the plugin's phpcs:ignore comments, once with
 *  --ignore-annotations, because a reviewer reads the code, not our comments.
 *  Then tools/phpcs/markup-scan.php, which reads what EscapeOutput cannot: the
 *  line that builds markup out of __() and variables before a later echo.
 *
 *      node tools/phpcs.mjs                 # HEAD, counts per sniff
 *      node tools/phpcs.mjs --list          # and every finding
 *      node tools/phpcs.mjs --ref <commit>
 *      node tools/phpcs.mjs --worktree      # uncommitted changes too (git stash create)
 *      node tools/phpcs.mjs --parse-only    # just the syntax check, on PHP 7.4 and 8.3
 *      node tools/phpcs.mjs --scan-only     # just markup-scan, every finding listed
 *
 *  Every run also parses every shipped PHP file on PHP 7.4 (the floor) and
 *  8.3 (tools/phpcs/parse-check.php, token_get_all with TOKEN_PARSE).
 *
 *  Exits 1 when anything is reported with annotations ignored.
 */
import { execFileSync, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { PLAYGROUND_CLI } from './lib/playground.mjs';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const argv = process.argv.slice( 2 );
const LIST = argv.includes( '--list' );
const PARSE_ONLY = argv.includes( '--parse-only' );
const SCAN_ONLY = argv.includes( '--scan-only' );
let REF = argv.includes( '--ref' ) ? argv[ argv.indexOf( '--ref' ) + 1 ] : 'HEAD';
if ( argv.includes( '--worktree' ) ) {
	// A commit object of the tracked files as they are on disk; the working tree and the index are not touched.
	REF = execFileSync( 'git', [ 'stash', 'create' ], { cwd: ROOT, encoding: 'utf8' } ).trim() || 'HEAD';
}

const PINS = {
	phpcs: [ 'PHPCSStandards/PHP_CodeSniffer', '3.13.4', 'PHP_CodeSniffer-3.13.4' ],
	wpcs: [ 'WordPress/WordPress-Coding-Standards', '3.2.0', 'WordPress-Coding-Standards-3.2.0' ],
	utils: [ 'PHPCSStandards/PHPCSUtils', '1.1.2', 'PHPCSUtils-1.1.2' ],
	extra: [ 'PHPCSStandards/PHPCSExtra', '1.4.1', 'PHPCSExtra-1.4.1' ],
};

// No spaces and no emoji: cmd.exe hands this to the Playground CLI intact.
const CACHE = path.join( os.tmpdir(), 'vgml-phpcs' );
fs.mkdirSync( CACHE, { recursive: true } );

// bsdtar reads zip and tar alike; GNU tar (first on PATH under Git Bash) takes C: for a host.
const TAR = 'win32' === process.platform ? path.join( process.env.SystemRoot || 'C:\\Windows', 'System32', 'tar.exe' ) : 'tar';

for ( const [ repo, tag, dir ] of Object.values( PINS ) ) {
	if ( fs.existsSync( path.join( CACHE, dir ) ) ) {
		continue;
	}
	const res = await fetch( `https://github.com/${ repo }/archive/refs/tags/${ tag }.tar.gz` );
	if ( ! res.ok ) {
		console.error( `download failed: ${ repo } ${ tag } -> ${ res.status }` );
		process.exit( 2 );
	}
	fs.writeFileSync( path.join( CACHE, `${ dir }.tar.gz` ), Buffer.from( await res.arrayBuffer() ) );
	execFileSync( TAR, [ '-xzf', `${ dir }.tar.gz` ], { cwd: CACHE } );
}

// The one patch: php-wasm has no exclusive locks, and PHPCS takes one per file report.
const reporter = path.join( CACHE, PINS.phpcs[ 2 ], 'src', 'Reporter.php' );
fs.writeFileSync( reporter, fs.readFileSync( reporter, 'utf8' ).replaceAll( '(FILE_APPEND | LOCK_EX)', 'FILE_APPEND' ) );

fs.copyFileSync( path.join( ROOT, 'tools', 'phpcs', 'ruleset.xml' ), path.join( CACHE, 'ruleset.xml' ) );
fs.copyFileSync( path.join( ROOT, 'tools', 'phpcs', 'markup-scan.php' ), path.join( CACHE, 'markup-scan.php' ) );
fs.copyFileSync( path.join( ROOT, 'tools', 'phpcs', 'parse-check.php' ), path.join( CACHE, 'parse-check.php' ) );

// A fresh archive every run: the thing a reviewer downloads, not the thing on disk.
const work = fs.mkdtempSync( path.join( os.tmpdir(), 'vgml-cs-' ) );
execFileSync( 'git', [ 'archive', '--format=tar', '--prefix=vergelabs-media-library/', '-o', path.join( work, 'clean.tar' ), REF ], { cwd: ROOT } );
execFileSync( TAR, [ '-xf', 'clean.tar' ], { cwd: work } );
const commit = execFileSync( 'git', [ 'rev-parse', '--short', REF ], { cwd: ROOT, encoding: 'utf8' } ).trim();
const out = path.join( work, 'out' );
fs.mkdirSync( out );

const installed = Object.values( PINS ).slice( 1 ).map( ( p ) => `/cs/${ p[ 2 ] }` ).join( ',' );

function boot( code, php = '8.3' ) {
	const bp = path.join( work, 'bp.json' );
	fs.writeFileSync( bp, JSON.stringify( { preferredVersions: { php, wp: 'latest' }, steps: [ { step: 'runPHP', code } ] } ) );
	spawnSync( 'npx', [ '-y', PLAYGROUND_CLI, 'run-blueprint', '--blueprint', bp, `--php=${ php }`,
		'--mount-dir', CACHE, '/cs',
		'--mount-dir', path.join( work, 'vergelabs-media-library' ), '/plugin',
		'--mount-dir', out, '/out',
		'--wordpress-install-mode', 'do-not-attempt-installing', '--verbosity', 'quiet' ],
	{ stdio: [ 'ignore', 'ignore', 'ignore' ], shell: true, env: { ...process.env, MSYS_NO_PATHCONV: '1' } } );
}

function phpcs( name, extra ) {
	const args = [ 'phpcs', '--standard=/cs/ruleset.xml', '--runtime-set', 'installed_paths', installed, ...extra,
		'--extensions=php', '-s', '--report-json=/tmp/r.json', '/plugin' ];
	boot( `<?php ini_set( 'memory_limit', '2048M' ); register_shutdown_function( function () { if ( file_exists( '/tmp/r.json' ) ) { copy( '/tmp/r.json', '/out/${ name }.json' ); } } ); $_SERVER['argv'] = $GLOBALS['argv'] = ${ JSON.stringify( args ).replace( /^\[/, 'array(' ).replace( /\]$/, ')' ) }; $_SERVER['argc'] = count( $_SERVER['argv'] ); require '/cs/${ PINS.phpcs[ 2 ] }/bin/phpcs';` );
	const file = path.join( out, `${ name }.json` );
	if ( ! fs.existsSync( file ) ) {
		console.error( `PHPCS (${ name }) wrote no report -- it did not run, which is not the same as clean.` );
		process.exit( 2 );
	}
	const report = JSON.parse( fs.readFileSync( file, 'utf8' ) );
	const findings = [];
	for ( const [ f, v ] of Object.entries( report.files ) ) {
		for ( const m of v.messages ) {
			findings.push( { file: f.replace( '/plugin/', '' ), line: m.line, source: m.source, type: m.type, message: m.message } );
		}
	}
	return { files: Object.keys( report.files ).length, findings };
}

console.log( `archive of ${ commit } (${ REF }), PHPCS ${ PINS.phpcs[ 1 ] }, WPCS ${ PINS.wpcs[ 1 ] }, in php-wasm` );

let parseBroken = 0;
for ( const php of [ '7.4', '8.3' ] ) {
	const file = path.join( out, `parse-${ php }.txt` );
	boot( `<?php ob_start(); register_shutdown_function( function () { file_put_contents( '/out/parse-${ php }.txt', ob_get_clean() ); } ); $argv = array( 'parse', '/plugin' ); require '/cs/parse-check.php';`, php );
	const said = fs.existsSync( file ) ? fs.readFileSync( file, 'utf8' ).trim() : '';
	if ( ! /^parse: \d+ files/m.test( said ) ) {
		console.error( `parse check on PHP ${ php } printed nothing -- it did not run.` );
		process.exit( 2 );
	}
	console.log( said );
	parseBroken += Number( ( said.match( /(\d+) with a syntax error/ ) || [] )[ 1 ] || 0 );
}
if ( PARSE_ONLY ) {
	fs.rmSync( work, { recursive: true, force: true } );
	process.exit( parseBroken ? 1 : 0 );
}

const honoured = SCAN_ONLY ? { files: 0, findings: [] } : phpcs( 'honoured', [] );
const ignored = SCAN_ONLY ? { files: 0, findings: [] } : phpcs( 'ignored', [ '--ignore-annotations' ] );

boot( `<?php ob_start(); register_shutdown_function( function () { file_put_contents( '/out/scan.txt', ob_get_clean() ); } ); $argv = array( 'scan', '/plugin' ); require '/cs/markup-scan.php';` );
const scan = fs.existsSync( path.join( out, 'scan.txt' ) ) ? fs.readFileSync( path.join( out, 'scan.txt' ), 'utf8' ) : '';
if ( ! /VergeML\.Markup totals/.test( scan ) ) {
	console.error( 'markup-scan printed no totals -- it did not run.\n' + scan.slice( 0, 2000 ) );
	process.exit( 2 );
}

const tally = ( list ) => list.reduce( ( acc, f ) => ( acc[ f.source ] = ( acc[ f.source ] || 0 ) + 1, acc ), {} );
const a = tally( honoured.findings );
const b = tally( ignored.findings );
const sources = [ ...new Set( [ ...Object.keys( a ), ...Object.keys( b ) ] ) ].sort();

console.log( `\n${ ignored.files } PHP files read\n` );
console.log( 'sniff'.padEnd( 76 ) + 'annotations honoured   ignored' );
for ( const s of sources ) {
	console.log( s.padEnd( 76 ) + String( a[ s ] || 0 ).padStart( 20 ) + String( b[ s ] || 0 ).padStart( 10 ) );
}
console.log( 'TOTAL'.padEnd( 76 ) + String( honoured.findings.length ).padStart( 20 ) + String( ignored.findings.length ).padStart( 10 ) );

const scanLines = scan.trim().split( '\n' );
console.log( '\n' + scanLines[ scanLines.length - 1 ] );

if ( LIST || SCAN_ONLY ) {
	console.log( '\n--- PHPCS, annotations ignored ---' );
	for ( const f of ignored.findings.sort( ( x, y ) => x.file.localeCompare( y.file ) || x.line - y.line ) ) {
		const honouredToo = honoured.findings.some( ( h ) => h.file === f.file && h.line === f.line && h.source === f.source );
		console.log( `${ f.file }:${ f.line }\t${ f.source }${ honouredToo ? '' : '\t(suppressed by a phpcs comment)' }\t${ f.message.slice( 0, 140 ) }` );
	}
	console.log( '\n--- markup-scan ---' );
	console.log( scan.trim() );
}

fs.rmSync( work, { recursive: true, force: true } );

const markupTotal = [ ...scan.matchAll( /=(\d+)/g ) ].reduce( ( s, m ) => s + Number( m[ 1 ] ), 0 );
process.exit( parseBroken + ignored.findings.length + markupTotal > 0 ? 1 : 0 );
