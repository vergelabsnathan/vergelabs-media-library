/*
 *  The multisite suite, on the box's real network.
 *
 *      node tools/multisite.mjs
 *
 *  A second WordPress on the test box -- /var/www/ms, served as
 *  http://ms.46.225.66.194.nip.io, converted to a network with WP-CLI, its
 *  plugin directory a symlink to the deployed copy -- runs
 *  tests/multisite/network.php through `wp eval-file`: network activation, a
 *  new site provisioned at creation, the network settings save, the inherited
 *  licence, the watchdog's network notice, site deletion dropping tables, and
 *  the real uninstall.php with the network wipe on. Real MySQL, real WP-CLI,
 *  no HTTP acrobatics. Exits 1 on any FAIL line.
 *
 *  Playground was tried first and refused: a WordPress network will not run
 *  on a custom port, and on port 80 Playground's own server and multisite
 *  shim answered every request with a redirect to itself.
 *
 *  Deploy first (node tools/deploy.mjs --box): the network runs the same
 *  files as the main test site. The suite ends with the plugin uninstalled
 *  on the network and switches it back on, so the fixture stays usable.
 */
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const BOX = '46.225.66.194';
const KEY = path.join( os.homedir(), '.ssh', 'hetzner_vgml' );
const SSH = [ '-i', KEY, '-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null', '-o', 'LogLevel=ERROR', `root@${ BOX }` ];
const MS = '/var/www/ms';
const URL = 'http://ms.46.225.66.194.nip.io';

function run( cmd, args, opts = {} ) {
	const r = spawnSync( cmd, args, { encoding: 'utf8', ...opts } );
	return { code: r.status, out: ( r.stdout || '' ) + ( r.stderr || '' ) };
}

console.log( `\n  multisite on ${ URL }\n` );

const up = run( 'scp', [ '-q', ...SSH.slice( 0, -1 ), path.join( ROOT, 'tests', 'multisite', 'network.php' ), `root@${ BOX }:/tmp/vgml-network.php` ] );
if ( up.code !== 0 ) {
	console.log( '  could not copy the suite to the box:\n' + up.out );
	process.exit( 1 );
}

const wp = `cd ${ MS } && sudo -u www-data wp`;
const suite = run( 'ssh', [ ...SSH, `${ wp } eval-file /tmp/vgml-network.php --url=${ URL } --allow-root 2>&1 | grep -v Deprecated; ${ wp } plugin activate vergelabs-media-library --network --allow-root >/dev/null 2>&1; rm -f /tmp/vgml-network.php /tmp/results.txt` ] );

const lines = suite.out.split( /\r?\n/ ).filter( ( l ) => /^(PASS|FAIL)\s/.test( l ) || /\d+ FAILED|ALL PASSED/.test( l ) );

if ( ! lines.length ) {
	console.log( '  no results came back; output was:\n' );
	console.log( suite.out.split( /\r?\n/ ).slice( -30 ).join( '\n' ) );
	process.exit( 1 );
}

for ( const l of lines ) console.log( '  ' + l );
console.log( '' );
process.exit( lines.some( ( l ) => l.startsWith( 'FAIL' ) ) ? 1 : 0 );
