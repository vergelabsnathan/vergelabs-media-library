/*
 *  The 3.16.1 -> 4.0.0 upgrade walk in Playground: the smoke, not the proof.
 *
 *      node tests/compat/upgrade-3161.spec.mjs                  # from both 3.16.1 builds on the shelf
 *      node tests/compat/upgrade-3161.spec.mjs --old b787a3bb   # from one, by its hash prefix
 *
 *  Two 3.16.1s exist and both are walked (Epic 1 retro, A-2): the rollback
 *  target -- the code customers downloaded from 2026-09-09 to 09-19, re-cut
 *  without the six entries that leaked (b787a3bb6a20) -- and git's 3.16.1,
 *  the clean dist re-cut (7f2a4fe9bee9), which differs from the served code
 *  in 22 files. Both come from the service's shelf, pinned by hash, so a
 *  different build cannot stand in for either silently.
 *
 *  One run-blueprint per old side: the 3.16.1 archive installed through the installPlugin
 *  step (not mounted -- the upgrader cannot replace a mount), activated; the fixture built under it
 *  (tests/compat/upgrade-3161-fixture.php); the state frozen by literal SQL
 *  (upgrade-3161-snapshot.php); then 4.0.0 installed over it through
 *  Plugin_Upgrader with overwrite_package -- the same object `wp plugin
 *  install --force` and the Plugins -> Upload -> Replace screen use; then an
 *  admin-context boot (which is what runs the migration), the compare and
 *  the suite (upgrade-3161.php). The new side is the working tree's own zip
 *  on PHP 8.5, so a deprecation the tree ships is caught here first.
 *
 *  Playground is SQLite behind a translation layer, which is why AD-5 makes
 *  the box the proof: dbDelta here is not dbDelta on MySQL. What this run
 *  does catch is a fatal, a wrong function name, a session that does not
 *  merge, a lock left behind -- cheaply, before the box.
 *
 *  Output comes back through a mounted directory, because Playground prints
 *  nothing a runPHP step says (pro/tools/verify.mjs learned that first).
 */
import { execSync, spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { PLAYGROUND_CLI } from '../../tools/lib/playground.mjs';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const SHELF = path.resolve( ROOT, '..', 'service', 'public', 'releases' );
// The new side is the working tree, zipped by deploy.mjs --zip (playground/…),
// so a fix in the tree is what the smoke swaps in -- not a hand-cut archive.
const NEW = path.join( ROOT, 'playground', 'vergelabs-media-library.zip' );

// The old sides, each pinned by its full sha256; the file name carries the
// prefix, as every file on the shelf does (docs/runbooks/rollback.md).
const OLD_SIDES = [
	{ label: 'the rollback target (served 3.16.1 code, re-cut clean)', sha256: 'b787a3bb6a202d40c85ba20a47e1d382c4354611da77e8e196b965f8cf8b7742' },
	{ label: "git's 3.16.1 (the clean dist re-cut)", sha256: '7f2a4fe9bee98b249243b4188252a628437d1b8d9059a18aa8807196984b389a' },
].map( ( s ) => ( { ...s, zip: path.join( SHELF, `vergelabs-media-library-3.16.1-${ s.sha256.slice( 0, 12 ) }.zip` ) } ) );

const argv = process.argv.slice( 2 );
const pick = argv.includes( '--old' ) ? argv[ argv.indexOf( '--old' ) + 1 ] || '' : '';
const sides = argv.includes( '--old' ) ? OLD_SIDES.filter( ( s ) => pick && s.sha256.startsWith( pick ) ) : OLD_SIDES;
if ( ! sides.length ) {
	console.log( `--old ${ pick || '(nothing)' }: no old side with that hash prefix. Known: ${ OLD_SIDES.map( ( s ) => s.sha256.slice( 0, 12 ) ).join( ', ' ) }` );
	process.exit( 2 );
}

try {
	execSync( 'node tools/deploy.mjs --zip', { cwd: ROOT, stdio: 'inherit' } );
} catch ( e ) {
	console.log( 'could not build the tree\'s zip' );
	process.exit( 1 );
}
for ( const z of [ NEW, ...sides.map( ( s ) => s.zip ) ] ) {
	if ( ! fs.existsSync( z ) ) {
		console.log( `missing ${ z }` );
		process.exit( 2 );
	}
}
for ( const s of sides ) {
	const sha = createHash( 'sha256' ).update( fs.readFileSync( s.zip ) ).digest( 'hex' );
	if ( sha !== s.sha256 ) {
		console.log( `${ path.basename( s.zip ) } is not the build it is named after: sha256 ${ sha.slice( 0, 12 ) }` );
		process.exit( 2 );
	}
}

// Twenty pictures, made here: a valid 1x1 PNG under twenty names, so the
// smoke depends on nothing outside the repo (tests/**/shots is ignored). The
// mock describer reads the file name, not the pixels, and names are what the
// snapshot compares.
const PNG = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64' );
const NAMES = [ 'red-hoodie', 'blue-jeans', 'summer-dress', 'leather-boots', 'wool-scarf', 'team-photo', 'portrait-anna', 'portrait-ben', 'crowd-market', 'family-picnic',
	'harbour-dawn', 'mountain-pass', 'old-town-square', 'beach-evening', 'forest-path', 'invoice-scan', 'floor-plan', 'logo-mark', 'chart-q3', 'whiteboard-notes' ];

const OUT = '/wordpress/wp-content/vgml-out';
const php = ( name, env, body ) => ( {
	step: 'runPHP',
	code: `<?php error_reporting( E_ALL ); ini_set( 'display_errors', '1' ); define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', true );\n`
		+ Object.entries( env ).map( ( [ k, v ] ) => `putenv( '${ k }=${ v }' );` ).join( ' ' ) + '\n'
		+ `ob_start( function ( $b ) { file_put_contents( '${ OUT }/${ name }.txt', $b, FILE_APPEND ); return $b; } );\n`
		+ `register_shutdown_function( function () { $e = error_get_last(); if ( $e ) { file_put_contents( '${ OUT }/${ name }.txt', "\\nLAST ERROR: " . json_encode( $e ) . "\\n", FILE_APPEND ); } } );\n`
		+ body,
} );

const blueprint = {
	preferredVersions: { php: '8.5', wp: 'latest' },
	steps: [
		{ step: 'installPlugin', pluginData: { resource: 'vfs', path: '/dist/old.zip' }, options: { activate: true } },
		php( '1-fixture', { VGML_PICTURES: '/pics' }, `require '/wordpress/wp-load.php'; require '/wordpress/wp-content/vgml-compat/upgrade-3161-fixture.php';` ),
		php( '2-snapshot', { VGML_SNAP: `${ OUT }/snap.json` }, `require '/wordpress/wp-load.php'; require '/wordpress/wp-content/vgml-compat/upgrade-3161-snapshot.php';` ),
		php( '3-install', {}, `define( 'WP_ADMIN', true ); require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
$u = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$r = $u->install( '/dist/new.zip', array( 'overwrite_package' => true ) );
echo 'install: ' . var_export( $r, true ) . ' result: ' . var_export( $u->result, true ) . ' messages: ' . wp_json_encode( $u->skin->get_upgrade_messages() ) . "\\n";
echo 'active after: ' . ( is_plugin_active( 'vergelabs-media-library/vergelabs-media-library.php' ) ? 'yes' : 'no' ) . "\\n";
echo 'header: ' . get_plugin_data( WP_PLUGIN_DIR . '/vergelabs-media-library/vergelabs-media-library.php', false, false )['Version'] . "\\n";` ),
		php( '4-compare', { VGML_SNAP: `${ OUT }/snap.json`, VGML_COMPARE: '1' }, `define( 'WP_ADMIN', true ); require '/wordpress/wp-load.php'; echo 'provisioned to ' . get_option( 'vergeml_version' ) . "\n"; require '/wordpress/wp-content/vgml-compat/upgrade-3161-snapshot.php';` ),
		php( '5-suite', { VGML_SNAP: `${ OUT }/snap.json`, VGML_SMOKE: '1' }, `define( 'WP_ADMIN', true ); require '/wordpress/wp-load.php'; require '/wordpress/wp-content/vgml-compat/upgrade-3161.php';` ),
	],
};

/**
 *  One walk from one old side: its own scratch directory (plain ASCII -- the
 *  repo's path carries a character cmd.exe mangles, tools/matrix.mjs), the
 *  pictures, the suite files, the blueprint, one Playground. Resolves true
 *  for green; a red run keeps its directory for reading.
 */
function walk( side ) {
	return new Promise( ( resolve ) => {
		const work = fs.mkdtempSync( path.join( os.tmpdir(), 'vgml-upg-' ) );
		const out = path.join( work, 'out' );
		fs.mkdirSync( out );
		fs.copyFileSync( side.zip, path.join( work, 'old.zip' ) );
		fs.copyFileSync( NEW, path.join( work, 'new.zip' ) );

		const pics = path.join( work, 'pics' );
		fs.mkdirSync( pics );
		NAMES.forEach( ( n, i ) => fs.writeFileSync( path.join( pics, `${ String( i + 1 ).padStart( 2, '0' ) }-${ n }.png` ), PNG ) );

		const compat = path.join( work, 'compat' );
		fs.mkdirSync( compat );
		for ( const f of [ 'upgrade-3161-fixture.php', 'upgrade-3161-snapshot.php', 'upgrade-3161.php' ] ) {
			fs.copyFileSync( path.join( ROOT, 'tests', 'compat', f ), path.join( compat, f ) );
		}

		const bp = path.join( work, 'blueprint.json' );
		fs.writeFileSync( bp, JSON.stringify( blueprint, null, '\t' ) );

		const args = [ PLAYGROUND_CLI, 'run-blueprint', '--blueprint', bp,
			'--mount-dir', work, '/dist',
			'--mount-dir', pics, '/pics',
			'--mount-dir', compat, '/wordpress/wp-content/vgml-compat',
			'--mount-dir', out, OUT,
		];
		console.log( `\n════════ old side: ${ side.label }  ${ path.basename( side.zip ) }` );
		console.log( `  work: ${ work }` );
		const started = Date.now();
		const p = spawn( 'npx', args, { stdio: [ 'ignore', 'pipe', 'pipe' ], shell: 'win32' === process.platform, env: { ...process.env, MSYS_NO_PATHCONV: '1' } } );
		let log = '';
		p.stdout.on( 'data', ( c ) => ( log += c ) );
		p.stderr.on( 'data', ( c ) => ( log += c ) );
		p.on( 'error', ( e ) => {
			console.log( `  playground could not start: ${ e.message }\n  kept: ${ work }` );
			resolve( false );
		} );
		p.on( 'close', ( code ) => {
			fs.writeFileSync( path.join( work, 'playground.log' ), log );
			console.log( `  playground exited ${ code } in ${ Math.round( ( Date.now() - started ) / 1000 ) }s` );
			let ok = 0 === code;
			// A stage that bailed says so in its own words; those words are red here.
			const BAIL = /Fatal error|Uncaught|FAIL |LAST ERROR|fewer than twenty|no pictures at|described \d+ of 20|filed \d+ of 6|query failed|could not write|no usable snapshot|the folder taxonomy is/;
			for ( const f of fs.readdirSync( out ).filter( ( f ) => f.endsWith( '.txt' ) ).sort() ) {
				const text = fs.readFileSync( path.join( out, f ), 'utf8' );
				console.log( `\n──── ${ f }\n${ text.trim() }` );
				if ( BAIL.test( text ) ) {
					ok = false;
				}
			}
			// The swap itself: the upgrader said true, the plugin stayed active, the header moved.
			const inst = fs.existsSync( path.join( out, '3-install.txt' ) ) ? fs.readFileSync( path.join( out, '3-install.txt' ), 'utf8' ) : '';
			if ( ! /install: true/.test( inst ) || ! /active after: yes/.test( inst ) || ! /header: 4\./.test( inst ) ) {
				console.log( '  the swap did not report install: true / active after: yes / header: 4.x' );
				ok = false;
			}
			const suite = fs.existsSync( path.join( out, '5-suite.txt' ) ) ? fs.readFileSync( path.join( out, '5-suite.txt' ), 'utf8' ) : '';
			const m = suite.match( /(\d+)\/(\d+) passed/ );
			if ( ! m || m[ 1 ] !== m[ 2 ] || '0' === m[ 2 ] ) {
				ok = false;
			}
			const cmp = fs.existsSync( path.join( out, '4-compare.txt' ) ) ? fs.readFileSync( path.join( out, '4-compare.txt' ), 'utf8' ) : '';
			if ( ! /\b0 differences/.test( cmp ) ) {
				ok = false;
			}
			console.log( `\n  ${ ok ? 'SMOKE GREEN' : 'SMOKE RED' } from ${ side.sha256.slice( 0, 12 ) } — ${ m ? m[ 0 ] : 'no passed line' }; ${ ( cmp.match( /\d+ differences/ ) || [ 'no compare line' ] )[ 0 ] }` );
			// A green run leaves nothing behind; a red one keeps its work directory for reading.
			if ( ok ) {
				fs.rmSync( work, { recursive: true, force: true } );
			} else {
				console.log( `  kept: ${ work }` );
			}
			resolve( ok );
		} );
	} );
}

const verdicts = [];
for ( const side of sides ) {
	verdicts.push( [ side, await walk( side ) ] );
}
console.log( '\n════════ smoke' );
for ( const [ side, ok ] of verdicts ) {
	console.log( `  ${ ok ? 'GREEN' : 'RED  ' }  ${ side.sha256.slice( 0, 12 ) }  ${ side.label }` );
}
process.exit( verdicts.every( ( [ , ok ] ) => ok ) ? 0 : 1 );
