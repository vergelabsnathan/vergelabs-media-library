/*
 *  One way to run the browser suites, and only one at a time.
 *
 *  The suites mutate the site they test: t0 assigns files to folders and undoes
 *  it, the watchdog breaks a plugin file and repairs it, the health suites
 *  scan and rescan. Each is correct on a site nobody else is touching, and
 *  each produces failures indistinguishable from a real regression when two
 *  runs overlap -- a wrong folder count, an undo whose token no longer
 *  matches, a report drawn from the previous run's numbers.
 *
 *  That cost most of a session once. The fix is not to make every suite
 *  reentrant; it is to make overlapping runs impossible and to run the suites
 *  in sequence.
 *
 *      node tools/verify.mjs                     # every suite it can reach
 *      node tools/verify.mjs health ai           # only those
 *      node tools/verify.mjs --base http://<box> --playground http://127.0.0.1:8899
 *
 *  Two environments, and the suites are not interchangeable between them. The
 *  tree and watchdog suites want Playground -- the watchdog one breaks a
 *  plugin file on purpose, which is not something to do to a box holding
 *  anything you care about. The rest want real MySQL. A suite whose
 *  environment is not answering is reported as SKIPPED, never as passed.
 */
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const LOCK = path.join( ROOT, '.verify.lock' );

const argv = process.argv.slice( 2 );

function flag( name, fallback ) {
	const at = argv.indexOf( name );
	return at >= 0 ? argv[ at + 1 ] : fallback;
}

const BASE = flag( '--base', 'http://185.229.224.239' );
const PLAYGROUND = flag( '--playground', 'http://127.0.0.1:8899' );

/*
 *  The values that follow a flag are not suite names. Without this the first
 *  bare argument was dropped whenever a flag was absent -- indexOf returns -1,
 *  -1 + 1 is 0 -- and `verify nosuch` quietly ran everything.
 */
const taken = new Set();
for ( const name of [ '--base', '--playground' ] ) {
	const at = argv.indexOf( name );
	if ( at >= 0 ) {
		taken.add( at );
		taken.add( at + 1 );
	}
}

const only = argv.filter( ( a, i ) => ! taken.has( i ) && ! a.startsWith( '--' ) );

/*
 *  Order matters. The watchdog runs last because it deliberately drives the
 *  site into safe mode, and a suite that starts while the features are off
 *  reports every one of them missing.
 */
/*
 *  `before` is a suite's precondition, run for it rather than written in a
 *  document and forgotten. smart.mjs tests the first-run experience, and there
 *  is deliberately no endpoint to un-scan a library -- that is not something
 *  the interface should offer -- so the state has to be set from outside the
 *  browser. It used to live as a line in CLAUDE.md, which meant the suite
 *  failed with a JSON blob whenever somebody forgot.
 */
const SUITES = [
	{ name: 'tree', file: 'tests/tree/t0-endpoints.js', env: 'playground' },
	/*
	 *  The AI folders, in two halves for the usual reason. The PHP one is
	 *  about the join and the counts and wants real MySQL -- Playground's
	 *  SQLite layer does not maintain $wpdb->num_queries, so the budget half
	 *  of it reports itself skipped there rather than passing on a zero. The
	 *  browser one is about whether the group can be used.
	 */
	{ name: 'ai-folders', file: 'tests/tree/ai-folders.php', env: 'box', php: true },
	{ name: 'ai-folders-ui', file: 'tests/tree/ai-folders.mjs', env: 'playground' },
	/*
	 *  Filing by itself. Playground, not the box, because its vectors come
	 *  through the seam rather than the index -- SQLite refuses an INSERT
	 *  carrying packed floats, so the storage half is proven where storage
	 *  works and the judgement half is proven here.
	 */
	{ name: 'auto-file', file: 'tests/tree/auto-file.php', env: 'playground', php: true },
	{ name: 'health', file: 'tests/tree/health.mjs', env: 'box' },
	{
		name: 'ai',
		file: 'tests/tree/ai.mjs',
		env: 'box',
		// "Describe new images" needs images that are new. Once the library is
		// fully described the suite has nothing to do and fails saying so --
		// which is true, and useless. Three files go back to undescribed.
		before: `wp eval 'foreach ( get_posts( array( "post_type" => "attachment", "post_status" => "inherit", "post_mime_type" => "image", "posts_per_page" => 3, "fields" => "ids" ) ) as $i ) { vergeml_index_delete( $i ); delete_post_meta( $i, "_wp_attachment_image_alt" ); }'`,
	},
	{
		name: 'smart',
		file: 'tests/tree/smart.mjs',
		env: 'box',
		before: 'wp option delete vergeml_smart_scan',
	},
	/*
	 *  A PHP suite rather than a browser one, because what it tests has no
	 *  screen yet: the tree is data, and the phase that renders it is the next
	 *  one. It runs where the other PHP suites run -- shipped to the box and
	 *  handed to wp eval-file -- and reports the same way, by exiting non-zero.
	 */
	{ name: 'organize', file: 'tests/organize/test-organize.php', env: 'box', php: true },
	/*
	 *  Two suites for the Librarian, and they answer different questions. The
	 *  PHP one is about what applying and undoing actually do to the database
	 *  -- assignments, folders, the moves log -- and needs real MySQL. The
	 *  browser one is about whether the screen can be used, and runs in
	 *  Playground where a batch can be applied to a throwaway library.
	 */
	{ name: 'librarian', file: 'tests/librarian/test-librarian.php', env: 'box', php: true },
	/*
	 *  Gate 7's own regression guard: drop both tables and prove the lazy
	 *  install puts them back. Separate from the suite above because it is
	 *  destructive about the schema rather than about rows, and because it is
	 *  the one librarian check that also runs without a box --
	 *  tests/librarian/gate7-blueprint.json drives it through Playground.
	 */
	{ name: 'librarian-schema', file: 'tests/librarian/gate7-schema.php', env: 'box', php: true },
	{ name: 'librarian-ui', file: 'tests/librarian/librarian.mjs', env: 'playground' },
	{ name: 'watchdog', file: 'tests/watchdog/recovery.js', env: 'playground' },
];

const SSH = 'ssh -i ~/.ssh/kamatera_vgml -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null root@185.229.224.239';

function precondition( suite ) {
	return new Promise( ( resolve ) => {

		if ( ! suite.before || 'box' !== suite.env ) {
			return resolve( true );
		}

		console.log( `  precondition: ${ suite.before }` );

		const child = spawn(
			SSH.split( ' ' )[ 0 ],
			[ ...SSH.split( ' ' ).slice( 1 ), `cd /var/www/wp && ${ suite.before } --allow-root` ],
			{ stdio: 'ignore' }
		);

		// A precondition that cannot run is worth saying out loud, but it is
		// the suite's own failure that decides the result.
		child.on( 'close', ( code ) => {
			if ( 0 !== code ) {
				console.log( `  precondition exited ${ code } — the suite may fail for that reason` );
			}
			resolve( true );
		} );
		child.on( 'error', () => {
			console.log( '  precondition could not run (no ssh?)' );
			resolve( true );
		} );
	} );
}

const baseFor = ( suite ) => ( 'playground' === suite.env ? PLAYGROUND : BASE );

async function reachable( url ) {
	try {
		const c = new AbortController();
		const t = setTimeout( () => c.abort(), 8000 );
		await fetch( url, { signal: c.signal, redirect: 'manual' } );
		clearTimeout( t );
		return true;
	} catch ( e ) {
		return false;
	}
}

function takeLock() {
	try {
		// wx: fails if it already exists, which is the whole point.
		fs.writeFileSync( LOCK, String( process.pid ), { flag: 'wx' } );
		return true;
	} catch ( e ) {
		if ( 'EEXIST' !== e.code ) throw e;
	}

	const holder = Number( fs.readFileSync( LOCK, 'utf8' ) );

	/*
	 *  A lock left behind by a run that was killed. Signal 0 asks "is this pid
	 *  alive" without touching it -- but EPERM means alive and not ours, which
	 *  is still alive. Only ESRCH, no such process, makes the lock litter.
	 */
	try {
		process.kill( holder, 0 );
	} catch ( e ) {
		if ( 'ESRCH' === e.code ) {
			console.log( `  clearing a stale lock from pid ${ holder }` );
			fs.unlinkSync( LOCK );
			return takeLock();
		}
	}

	console.error( `\nAnother verify run is in progress (pid ${ holder }).` );
	console.error( 'Wait for it, or stop it — two runs against one site produce failures that\n' +
		'look exactly like regressions.\n' );
	return false;
}

/*
 *  A PHP suite runs on the box, not here: there is no PHP binary locally, and
 *  the point of these is to exercise the plugin inside a real WordPress
 *  against real MySQL. Shipped fresh every run rather than relying on whatever
 *  the last deploy left in place -- a suite that silently tests an older copy
 *  of itself is worse than one that does not run.
 */
function runPhp( suite ) {
	return new Promise( ( resolve ) => {

		const remote = `/tmp/${ path.basename( suite.file ) }`;
		const args = SSH.split( ' ' );

		const copy = spawn(
			'scp',
			[ ...args.slice( 1, args.length - 1 ), path.join( ROOT, suite.file ), `${ args[ args.length - 1 ] }:${ remote }` ],
			{ stdio: 'inherit' }
		);

		copy.on( 'error', () => resolve( 1 ) );

		copy.on( 'close', ( code ) => {

			if ( 0 !== code ) {
				console.log( '  could not copy the suite to the box' );
				return resolve( code ?? 1 );
			}

			const child = spawn(
				args[ 0 ],
				[ ...args.slice( 1 ), `cd /var/www/wp && wp eval-file ${ remote } --allow-root` ],
				{ stdio: 'inherit' }
			);

			child.on( 'error', () => resolve( 1 ) );
			child.on( 'close', ( c ) => resolve( c ?? 1 ) );
		} );
	} );
}

function run( suite ) {

	console.log( `\n──────── ${ suite.name }  (${ suite.file } → ${ baseFor( suite ) })` );

	if ( suite.php ) {
		return runPhp( suite );
	}

	return new Promise( ( resolve ) => {
		const child = spawn( process.execPath, [ path.join( ROOT, suite.file ), baseFor( suite ) ], {
			cwd: ROOT,
			stdio: 'inherit',
		} );
		child.on( 'close', ( code ) => resolve( code ?? 1 ) );
	} );
}

if ( ! takeLock() ) {
	process.exit( 3 );
}

// Every exit path drops the lock, or the next run inherits a lie.
const release = () => {
	try {
		if ( fs.existsSync( LOCK ) && Number( fs.readFileSync( LOCK, 'utf8' ) ) === process.pid ) {
			fs.unlinkSync( LOCK );
		}
	} catch ( e ) { /* nothing useful to do while exiting */ }
};

process.on( 'exit', release );
process.on( 'SIGINT', () => process.exit( 130 ) );
process.on( 'SIGTERM', () => process.exit( 143 ) );

const chosen = only.length ? SUITES.filter( ( s ) => only.includes( s.name ) ) : SUITES;

if ( ! chosen.length ) {
	console.error( `No suite matched. Known: ${ SUITES.map( ( s ) => s.name ).join( ', ' ) }` );
	process.exit( 2 );
}

console.log( `\nverify` );
console.log( `  box        ${ BASE }` );
console.log( `  playground ${ PLAYGROUND }` );
console.log( `  ${ chosen.length } suite(s), one at a time` );

const failed = [];
const skipped = [];
const passed = [];

for ( const suite of chosen ) {

	if ( ! ( await reachable( baseFor( suite ) ) ) ) {
		console.log( `\n──────── ${ suite.name }  SKIPPED — ${ baseFor( suite ) } is not answering` );
		skipped.push( suite.name );
		continue;
	}

	await precondition( suite );

	const code = await run( suite );

	if ( 0 === code ) {
		passed.push( suite.name );
	} else {
		failed.push( `${ suite.name } (exit ${ code })` );
	}
}

console.log( '\n════════ summary' );
console.log( `  passed   ${ passed.length ? passed.join( ', ' ) : 'none' }` );
if ( skipped.length ) {
	// Loudly, because a skipped suite proves nothing and the whole point of
	// this file is that a run's output means what it says.
	console.log( `  SKIPPED  ${ skipped.join( ', ' ) }  — these did NOT run` );
}
if ( failed.length ) {
	console.log( `  FAILED   ${ failed.join( ', ' ) }` );
}

/*
 *  Exit 1 failed, exit 2 skipped, exit 0 only when everything asked for
 *  actually ran and passed. A green exit code that covered three suites out of
 *  five is the same lie as a test that passes by never running -- and
 *  --allow-skips makes saying so a deliberate act.
 */
if ( failed.length ) {
	process.exit( 1 );
}

if ( skipped.length && ! argv.includes( '--allow-skips' ) ) {
	console.log( '\n  exit 2: suites were skipped. Pass --allow-skips if that is expected.' );
	process.exit( 2 );
}

process.exit( 0 );
