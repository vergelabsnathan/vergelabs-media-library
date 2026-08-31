/*
 *  Render every admin screen on the box, and fail if any of them will not.
 *
 *      node tools/smoke.mjs
 *      node tools/smoke.mjs --box 46.225.66.194
 *
 *  Ships tools/smoke-screens.php to the box and runs it under wp eval-file.
 *  The PHP does the work and decides the verdict; this only carries the file
 *  over, strips the noise WP Rocket prints on every CLI call, and passes the
 *  exit code back so `pnpm smoke` can stop a chain.
 *
 *  The box, not Playground: the settings screens read real options and the
 *  MIME screen renders a hundred-kilobyte table, and the point is to draw the
 *  page the way a person's request draws it.
 */
import { execFileSync, spawnSync } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const SMOKE = path.join( ROOT, 'tools', 'smoke-screens.php' );

const argv = process.argv.slice( 2 );

function flagValue( name, fallback ) {
	const at = argv.indexOf( name );
	if ( at < 0 ) {
		return fallback;
	}
	const next = argv[ at + 1 ];
	return next && ! next.startsWith( '--' ) ? next : fallback;
}

// The same map deploy.mjs and verify.mjs keep. A box has to be listed here.
const BOXES = {
	'46.225.66.194': { key: '~/.ssh/hetzner_vgml', wp: '/var/www/wp' },
};

const HOST = flagValue( '--box', '46.225.66.194' );
const box = BOXES[ HOST ];

if ( ! box ) {
	console.error( `\n  ${ HOST } is not a box this script knows. Add it to BOXES.\n` );
	process.exit( 1 );
}

const key = box.key.replace( /^~/, os.homedir() );
const common = [ '-i', key, '-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=15' ];

try {
	execFileSync( 'scp', [ ...common, '-q', SMOKE, `root@${ HOST }:/tmp/vgml-smoke-screens.php` ], { stdio: 'pipe' } );
} catch ( err ) {
	console.error( `\n  could not copy the smoke file to ${ HOST }: ${ ( err.stderr || err.message ).toString().trim() }\n` );
	process.exit( 1 );
}

/*
 *  The script goes over stdin so nothing here is re-quoted on the way through
 *  Windows and the remote shell -- the same lesson deploy.mjs learned.
 */
const remote = `
	cd ${ box.wp }
	sudo -u www-data wp eval-file /tmp/vgml-smoke-screens.php --allow-root 2>&1 \\
		| grep -v 'Deprecated:' | grep -v '^$'
	exit \${PIPESTATUS[0]}
`;

const run = spawnSync( 'ssh', [ ...common, `root@${ HOST }`, 'bash -s' ], {
	input: remote,
	encoding: 'utf8',
	maxBuffer: 16 * 1024 * 1024,
} );

console.log( '' );
process.stdout.write( run.stdout || '' );

if ( run.stderr && run.stderr.trim() ) {
	process.stderr.write( run.stderr );
}

console.log( '' );
process.exit( run.status === null ? 1 : run.status );
