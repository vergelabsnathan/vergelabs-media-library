/*
 *  The 3.16.1 -> 4.0.0 upgrade walk in Playground: the smoke, not the proof.
 *
 *      node tests/compat/upgrade-3161.spec.mjs
 *
 *  One run-blueprint: the 3.16.1 archive installed through the installPlugin
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
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );
const DIST = path.resolve( ROOT, '..', 'dist' );
const OLD = path.join( DIST, 'vergelabs-media-library-3.16.1.zip' );
// The new side is the working tree, zipped by deploy.mjs --zip (playground/…),
// so a fix in the tree is what the smoke swaps in -- not a hand-cut archive.
const NEW = path.join( ROOT, 'playground', 'vergelabs-media-library.zip' );

execSync( 'node tools/deploy.mjs --zip', { cwd: ROOT, stdio: 'inherit' } );
for ( const z of [ OLD, NEW ] ) {
	if ( ! fs.existsSync( z ) ) {
		console.log( `missing ${ z }` );
		process.exit( 2 );
	}
}

// A scratch directory in plain ASCII: the repo's own path carries a character
// cmd.exe mangles (tools/matrix.mjs).
const work = fs.mkdtempSync( path.join( os.tmpdir(), 'vgml-upg-' ) );
const out = path.join( work, 'out' );
fs.mkdirSync( out );

fs.copyFileSync( OLD, path.join( work, 'old.zip' ) );
fs.copyFileSync( NEW, path.join( work, 'new.zip' ) );

// Twenty pictures: the repo's own screenshots, copied under plain names.
const pics = path.join( work, 'pics' );
fs.mkdirSync( pics );
const shotsDir = path.join( ROOT, 'tests', 'tree', 'shots' );
const shots = fs.existsSync( shotsDir ) ? fs.readdirSync( shotsDir ).filter( ( f ) => f.endsWith( '.png' ) ).slice( 0, 20 ) : [];
if ( shots.length < 20 ) {
	console.log( `need twenty .png in ${ shotsDir }, found ${ shots.length }` );
	process.exit( 2 );
}
shots.forEach( ( f, i ) => fs.copyFileSync( path.join( ROOT, 'tests', 'tree', 'shots', f ), path.join( pics, `picture-${ String( i + 1 ).padStart( 2, '0' ) }.png` ) ) );

const compat = path.join( work, 'compat' );
fs.mkdirSync( compat );
for ( const f of [ 'upgrade-3161-fixture.php', 'upgrade-3161-snapshot.php', 'upgrade-3161.php' ] ) {
	fs.copyFileSync( path.join( ROOT, 'tests', 'compat', f ), path.join( compat, f ) );
}

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
const bp = path.join( work, 'blueprint.json' );
fs.writeFileSync( bp, JSON.stringify( blueprint, null, '\t' ) );

const args = [ '@wp-playground/cli', 'run-blueprint', '--blueprint', bp,
	'--mount-dir', work, '/dist',
	'--mount-dir', pics, '/pics',
	'--mount-dir', compat, '/wordpress/wp-content/vgml-compat',
	'--mount-dir', out, OUT,
];
console.log( `  work: ${ work }` );
const started = Date.now();
const p = spawn( 'npx', args, { stdio: [ 'ignore', 'pipe', 'pipe' ], shell: 'win32' === process.platform, env: { ...process.env, MSYS_NO_PATHCONV: '1' } } );
let log = '';
p.stdout.on( 'data', ( c ) => ( log += c ) );
p.stderr.on( 'data', ( c ) => ( log += c ) );
p.on( 'close', ( code ) => {
	fs.writeFileSync( path.join( work, 'playground.log' ), log );
	console.log( `  playground exited ${ code } in ${ Math.round( ( Date.now() - started ) / 1000 ) }s` );
	let ok = 0 === code;
	for ( const f of fs.readdirSync( out ).filter( ( f ) => f.endsWith( '.txt' ) ).sort() ) {
		const text = fs.readFileSync( path.join( out, f ), 'utf8' );
		console.log( `\n──── ${ f }\n${ text.trim() }` );
		if ( /Fatal error|Uncaught|FAIL /.test( text ) ) {
			ok = false;
		}
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
	console.log( `\n  ${ ok ? 'SMOKE GREEN' : 'SMOKE RED' } — ${ m ? m[ 0 ] : 'no passed line' }; ${ ( cmp.match( /\d+ differences/ ) || [ 'no compare line' ] )[ 0 ] }` );
	process.exit( ok ? 0 : 1 );
} );
