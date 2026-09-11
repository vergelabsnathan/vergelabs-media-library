/*
 *  The compatibility matrix.
 *
 *      node tools/matrix.mjs                          # every cell, then the table
 *      node tools/matrix.mjs --cell wp=6.5,php=7.4    # one cell; its row is replaced
 *      node tools/matrix.mjs --list                   # the cells and their keys
 *      VGML_MATRIX_MUTATE=1 node tools/matrix.mjs --cell wp=7.1,php=8.2   # must go ✗
 *
 *  One command boots each cell, installs the release zip
 *  (playground/vergelabs-media-library.zip -- rebuild it with
 *  `node tools/deploy.mjs --zip` first, or the matrix tests last week's code),
 *  runs tests/compat/five-minutes.mjs against it and writes one row. The rows
 *  live in tests/compat/matrix-results.json and are rendered into
 *  docs/compatibility.md between the matrix markers, dated, with the command.
 *  Exits non-zero when any cell of the run is ✗, and names them, so it is a
 *  gate.
 *
 *  Cells:
 *    WordPress 6.5, 7.0, 7.1 × PHP 7.4, 8.2, 8.5 -- Playground, single site.
 *    Language nl_NL and ar on the base cell (7.1 / 8.2); ar also runs
 *      `node tools/rtl.mjs --check`.
 *    Alongside FileBird, Premio Folders (wordpress.org), Enhanced Media Library
 *      2.9.4 and Polylang Pro, each on the base cell.
 *    Multisite subdirectory -- the box's second WordPress at /var/www/ms, the
 *      one tools/multisite.mjs reaches, with a throwaway administrator for
 *      the run and the uninstall step left out (deactivating any plugin on
 *      the box fatals in core's FTP class). Multisite subdomain is not
 *      provisioned and is written as ✗ saying so.
 *
 *  Not wp-env, not Docker: Playground boots in seconds and Docker Desktop
 *  crashed five times in one run. Never the box's main site (/var/www/wp).
 */
import { spawn, spawnSync, execSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const SESSION = path.resolve( ROOT, '..' );
const SLUG = 'vergelabs-media-library';
const ZIP = path.join( ROOT, 'playground', `${ SLUG }.zip` );
const RESULTS = path.join( ROOT, 'tests', 'compat', 'matrix-results.json' );
const DOC = path.join( ROOT, 'docs', 'compatibility.md' );
const PROBE = path.join( ROOT, 'tests', 'compat', 'matrix-probe.php' );
const SCRIPT = path.join( ROOT, 'tests', 'compat', 'five-minutes.mjs' );

const BASE_WP = '7.1';
const BASE_PHP = '8.2';

const BOX = '46.225.66.194';
const MS_URL = `http://ms.${ BOX }.nip.io`;
const MS_DIR = '/var/www/ms';
const SSH = [ '-i', path.join( os.homedir(), '.ssh', 'hetzner_vgml' ), '-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null', '-o', 'LogLevel=ERROR', `root@${ BOX }` ];

const COMPANIONS = {
	// Not booted: FileBird's own media query (HAVING FIND_IN_SET over GROUP_CONCAT) does
	// not run on Playground's SQLite, so its list is empty there whatever we do. Measured
	// 2026-09-11; the same cell on the box's MariaDB is 9/9, and that row is the answer.
	'filebird': { label: 'FileBird', zip: path.join( SESSION, 'Pluginexamples', 'filebird.zip' ), dir: 'filebird', skip: 'not run on Playground: FileBird\'s own FIND_IN_SET query does not run on SQLite (its list is empty there with or without us); see the MariaDB row' },
	'folders': { label: 'Premio Folders (wordpress.org)', wporg: 'folders' },
	'enhanced-media-library': { label: 'Enhanced Media Library 2.9.4', path: path.join( SESSION, 'research', 'enhanced-media-library.2.9.4', 'enhanced-media-library' ), dir: 'enhanced-media-library' },
	'polylang-pro': { label: 'Polylang Pro 3.8.7', zip: path.join( SESSION, 'Pluginexamples', 'ropA76DFP9HM-polylang-pro.zip' ), dir: 'polylang-pro' },
	// Not a Playground companion: the box's own inactive copy, linked into the network for one run.
	'filebird-box': { label: 'FileBird 6.5.8 (MariaDB)' },
};

/*
 *  What Playground is asked for, per WordPress version. "7.1" resolves to the
 *  newest 7.1.x zip wordpress.org offers -- on 2026-09-11 that was 7.1.1-RC1,
 *  which then ran an automatic core update during boot and lost the login.
 *  The current branch is asked for as "latest" (the release); the row records
 *  the version the probe actually saw, so the table never says 7.1 over an RC.
 */
const ASK = { '6.5': '6.5', '7.0': '7.0', '7.1': 'latest' };

const cells = [];
for ( const wp of [ '6.5', '7.0', '7.1' ] ) {
	for ( const php of [ '7.4', '8.2', '8.5' ] ) {
		cells.push( { key: `wp=${ wp },php=${ php }`, wp, php, shape: 'single', lang: 'en_US', with: '' } );
	}
}
for ( const lang of [ 'nl_NL', 'ar' ] ) {
	cells.push( { key: `lang=${ lang }`, wp: BASE_WP, php: BASE_PHP, shape: 'single', lang, with: '' } );
}
for ( const name of Object.keys( COMPANIONS ) ) {
	if ( ! COMPANIONS[ name ].zip && ! COMPANIONS[ name ].wporg && ! COMPANIONS[ name ].path ) {
		continue; // a box-only companion has its own cell below
	}
	cells.push( { key: `with=${ name }`, wp: BASE_WP, php: BASE_PHP, shape: 'single', lang: 'en_US', with: name } );
}
/*
 *  The box's two networks. /var/www/ms is the subdirectory network
 *  tools/multisite.mjs reaches; /var/www/ms2 is the subdomain network
 *  tools/box-ms2-provision.sh made on 2026-09-11, run against its sub-site
 *  two.ms2… so a subdomain is what is tested. The FileBird row on MariaDB
 *  links the box's own inactive FileBird into ms for the run and unlinks it
 *  after -- Playground's SQLite refuses FileBird's own FIND_IN_SET query, so
 *  that row can only be answered on a real database.
 */
cells.push( { key: 'shape=multisite-subdirectory', wp: '7.1', php: '8.5', shape: 'multisite, subdirectory (the box)', lang: 'en_US', with: '', box: true, dir: MS_DIR, url: MS_URL } );
cells.push( { key: 'shape=multisite-subdomain', wp: '7.1', php: '8.5', shape: 'multisite, subdomain (the box, sub-site)', lang: 'en_US', with: '', box: true, dir: '/var/www/ms2', url: `http://two.ms2.${ BOX }.nip.io` } );
cells.push( { key: 'with=filebird,shape=multisite-subdirectory', wp: '7.1', php: '8.5', shape: 'multisite, subdirectory (the box)', lang: 'en_US', with: 'filebird-box', box: true, dir: MS_DIR, url: MS_URL, link: { from: '/var/www/wp/wp-content/plugins/filebird', slug: 'filebird' } } );

const argv = process.argv.slice( 2 );
const only = argv.includes( '--cell' ) ? argv[ argv.indexOf( '--cell' ) + 1 ] : '';
const MUTATE = '1' === process.env.VGML_MATRIX_MUTATE;

if ( argv.includes( '--list' ) ) {
	cells.forEach( ( c ) => console.log( `  ${ c.key.padEnd( 30 ) } WP ${ c.wp } · PHP ${ c.php } · ${ c.shape } · ${ c.lang } · ${ c.with ? COMPANIONS[ c.with ].label : 'nothing' }` ) );
	process.exit( 0 );
}

const chosen = only ? cells.filter( ( c ) => c.key === only ) : cells;
if ( ! chosen.length ) {
	console.error( `no cell "${ only }" -- node tools/matrix.mjs --list names them` );
	process.exit( 2 );
}

if ( ! fs.existsSync( ZIP ) ) {
	console.error( `no release zip at ${ ZIP } -- node tools/deploy.mjs --zip` );
	process.exit( 2 );
}
const zipDigest = crypto.createHash( 'sha256' ).update( fs.readFileSync( ZIP ) ).digest( 'hex' ).slice( 0, 12 );

const today = new Date().toISOString().slice( 0, 10 );
console.log( `\n  matrix · ${ chosen.length } cell(s) · release zip ${ path.relative( ROOT, ZIP ) } (${ zipDigest })${ MUTATE ? '  MUTATED' : '' }\n` );

let port = 8930;

function stop( child ) {
	try {
		if ( 'win32' === process.platform ) {
			execSync( `taskkill /pid ${ child.pid } /T /F`, { stdio: 'ignore' } );
		} else {
			child.kill( 'SIGTERM' );
		}
	} catch { /* already gone */ }
}

/*
 *  Relative paths only, from inside the work directory: Windows tar reads
 *  "C:" as a host name, and the tar cmd.exe finds first is GNU tar, which
 *  cannot read a zip at all -- so Windows unpacks with PowerShell.
 */
function unzip( work, zip, into ) {
	fs.mkdirSync( path.join( work, into ), { recursive: true } );
	fs.copyFileSync( zip, path.join( work, `${ into }.zip` ) );
	const cmd = 'win32' === process.platform
		? `powershell -NoProfile -Command "Expand-Archive -LiteralPath ${ into }.zip -DestinationPath ${ into } -Force"`
		: `unzip -q ${ into }.zip -d ${ into }`;
	execSync( cmd, { cwd: work, stdio: 'inherit' } );
	return path.join( work, into );
}

// Not fs.cpSync: on Node 22.17 it kills the process outright (exit 127, no
// message) when the source sits under the repo's own path; this walk does not.
function copyTree( from, to ) {
	fs.mkdirSync( to, { recursive: true } );
	for ( const entry of fs.readdirSync( from, { withFileTypes: true } ) ) {
		const a = path.join( from, entry.name );
		const b = path.join( to, entry.name );
		if ( entry.isDirectory() ) {
			copyTree( a, b );
		} else if ( entry.isFile() ) {
			fs.copyFileSync( a, b );
		}
	}
}

/*
 *  The five-minute script as a child, its lines echoed, its RESULT line kept.
 */
function fiveMinutes( base, env ) {
	return new Promise( ( resolve ) => {
		const child = spawn( process.execPath, [ SCRIPT, base ], { env: { ...process.env, ...env }, stdio: [ 'ignore', 'pipe', 'inherit' ] } );
		let out = '';
		child.stdout.on( 'data', ( d ) => {
			const text = d.toString();
			out += text;
			process.stdout.write( text.split( '\n' ).filter( ( l ) => ! l.startsWith( 'RESULT ' ) ).map( ( l ) => ( l ? `    ${ l }` : l ) ).join( '\n' ) );
		} );
		child.on( 'close', () => {
			const line = out.split( /\r?\n/ ).find( ( l ) => l.startsWith( 'RESULT ' ) );
			try {
				resolve( JSON.parse( line.slice( 7 ) ) );
			} catch {
				resolve( { ok: false, steps: [], failed: `the script did not finish: ${ out.trim().split( /\r?\n/ ).pop() || 'no output' }` } );
			}
		} );
	} );
}

async function runPlayground( cell ) {
	const work = fs.mkdtempSync( path.join( os.tmpdir(), 'vgml-matrix-' ) );
	const thisPort = port++;
	const base = `http://127.0.0.1:${ thisPort }`;
	const mounts = [];
	const steps = [
		// A writeFile step, not a mount: mounting over mu-plugins hides Playground's own login.
		{ step: 'writeFile', path: '/wordpress/wp-content/mu-plugins/vgml-matrix-probe.php', data: fs.readFileSync( PROBE, 'utf8' ) },
	];

	try {
		const plugin = path.join( unzip( work, ZIP, 'plugin' ), SLUG );
		if ( ! fs.existsSync( path.join( plugin, `${ SLUG }.php` ) ) ) {
			throw new Error( `the zip did not unpack to ${ SLUG }/${ SLUG }.php` );
		}
		mounts.push( [ plugin, `/wordpress/wp-content/plugins/${ SLUG }` ] );

		if ( 'en_US' !== cell.lang ) {
			steps.push( { step: 'setSiteLanguage', language: cell.lang } );
		}

		// The companion goes on first: a person who has FileBird installs this beside it.
		if ( cell.with ) {
			const c = COMPANIONS[ cell.with ];
			if ( c.wporg ) {
				steps.push( { step: 'installPlugin', pluginData: { resource: 'wordpress.org/plugins', slug: c.wporg }, options: { activate: true } } );
			} else {
				// Copied into the work directory either way: the CLI is spawned through
				// cmd.exe, and the repo's own path carries a character cmd.exe mangles --
				// mounted from there, the CLI never started and the log stayed empty.
				let dir;
				if ( c.zip ) {
					dir = path.join( unzip( work, c.zip, 'with' ), c.dir );
				} else {
					dir = path.join( work, 'with', c.dir );
					copyTree( c.path, dir );
				}
				mounts.push( [ dir, `/wordpress/wp-content/plugins/${ c.dir }` ] );
				steps.push( { step: 'activatePlugin', pluginPath: `/wordpress/wp-content/plugins/${ c.dir }` } );
			}
		}

		steps.push( { step: 'activatePlugin', pluginPath: `/wordpress/wp-content/plugins/${ SLUG }` } );

		const blueprint = path.join( work, 'blueprint.json' );
		fs.writeFileSync( blueprint, JSON.stringify( {
			landingPage: '/wp-admin/upload.php?mode=list',
			preferredVersions: { php: cell.php, wp: ASK[ cell.wp ] || cell.wp },
			login: true,
			steps,
		}, null, '	' ) );

		const log = fs.openSync( path.join( work, 'playground.log' ), 'w' );
		/*
		 *  Every constant on the command line, none in the blueprint.
		 *
		 *  The CLI serves requests from six workers and a blueprint's
		 *  defineWpConfigConsts -- the login step's own constant included --
		 *  is defined only in the worker that ran the blueprint. Five requests
		 *  in six then reach WordPress with no WP_DEBUG and no auto-login, and
		 *  the browser lands on wp-login.php?reauth=1 (traced hop by hop on
		 *  2026-09-11; --workers 1 also cures it, --define does without the
		 *  slowdown). tools/uninstall-walk.mjs gets away with the blueprint
		 *  because its wait loop retries until a request lands on the right
		 *  worker. Automatic updates off: the cell has to test the version it
		 *  names -- "7.1" pulled a 7.1.1-RC1 that updated itself mid-boot.
		 */
		const args = [ '@wp-playground/cli', 'server', '--port', String( thisPort ), '--php', cell.php, '--wp', ASK[ cell.wp ] || cell.wp, '--blueprint', blueprint,
			'--define', 'PLAYGROUND_AUTO_LOGIN_AS_USER', 'admin',
			'--define-bool', 'WP_DEBUG', 'true', '--define-bool', 'WP_DEBUG_LOG', 'true', '--define-bool', 'WP_DEBUG_DISPLAY', 'false',
			'--define-bool', 'AUTOMATIC_UPDATER_DISABLED', 'true', '--define-bool', 'WP_AUTO_UPDATE_CORE', 'false' ];
		for ( const [ from, to ] of mounts ) {
			args.push( '--mount-dir', from, to );
		}
		// MSYS_NO_PATHCONV: Git Bash would rewrite /wordpress/... into a Windows path.
		const child = spawn( 'npx', args, { stdio: [ 'ignore', log, log ], shell: true, env: { ...process.env, MSYS_NO_PATHCONV: '1' } } );

		const env = { VGML_MATRIX_PROBE: '1', VGML_MATRIX_LOCALE: 'en_US' === cell.lang ? '' : cell.lang, VGML_MATRIX_MUTATE: MUTATE ? '1' : '' };
		let result;
		try {
			result = await fiveMinutes( base, env );
		} finally {
			stop( child );
			fs.closeSync( log );
		}

		// The versions the site reported, not the ones asked for.
		if ( result.site && result.site.wp ) {
			cell.wp = result.site.wp;
			cell.php = String( result.site.php ).split( '.' ).slice( 0, 2 ).join( '.' );
		}

		if ( ! result.ok && /did not finish|nothing usable|did not sign/.test( result.failed ) ) {
			const tail = fs.readFileSync( path.join( work, 'playground.log' ), 'utf8' ).trim().split( /\r?\n/ ).slice( -6 );
			console.log( '    playground said:\n' + tail.map( ( l ) => `      ${ l.slice( 0, 160 ) }` ).join( '\n' ) );
		}

		// The Arabic cell is where the hand-kept RTL sheets show: a stale one is that cell's ✗.
		if ( 'ar' === cell.lang ) {
			const rtl = spawnSync( process.execPath, [ path.join( ROOT, 'tools', 'rtl.mjs' ), '--check' ], { encoding: 'utf8' } );
			const ok = 0 === rtl.status;
			result.steps.push( { name: 'the RTL sheets are current (tools/rtl.mjs --check)', ok, detail: ok ? 'up to date' : ( rtl.stdout || '' ).trim().split( /\r?\n/ ).pop() } );
			console.log( `    ${ ok ? 'ok  ' : 'FAIL' } the RTL sheets are current (tools/rtl.mjs --check)` );
			if ( ! ok ) {
				result.ok = false;
				result.failed = result.failed || `RTL sheets: ${ rtl.stdout.trim().split( /\r?\n/ ).pop() }`;
			}
		}
		return result;
	} finally {
		try { fs.rmSync( work, { recursive: true, force: true } ); } catch { /* a handle may still be open on Windows */ }
	}
}

/*
 *  The box's network, through the same ssh the multisite suite uses. A
 *  throwaway administrator exists for the run and is removed after; the
 *  script deletes what it uploaded and the folder it made.
 */
async function runBox( cell ) {
	const user = 'vgmlmatrix'; // no hyphen: a network allows only a-z and 0-9 in a username
	const pass = crypto.randomBytes( 12 ).toString( 'base64url' );
	const wp = `cd ${ cell.dir } && sudo -u www-data wp`;
	const ssh = ( cmd ) => spawnSync( 'ssh', [ ...SSH, cmd ], { encoding: 'utf8' } );
	// A network user is made on the main site; a sub-site needs the role set on it as well.
	const role = `${ wp } user set-role ${ user } administrator --url=${ cell.url } --allow-root 2>&1 | grep -v Deprecated;`;
	// The companion, when there is one: linked in and switched on for this run only,
	// switched off and unlinked after -- through WP-CLI, never the Plugins screen.
	const plugins = `${ cell.dir }/wp-content/plugins`;
	const linkIn = cell.link ? `ln -sfn ${ cell.link.from } ${ plugins }/${ cell.link.slug }; ${ wp } plugin activate ${ cell.link.slug } --url=${ cell.url } --allow-root 2>&1 | grep -v Deprecated;` : '';
	const linkOut = cell.link ? `${ wp } plugin deactivate ${ cell.link.slug } --url=${ cell.url } --allow-root >/dev/null 2>&1; unlink ${ plugins }/${ cell.link.slug };` : '';

	const made = ssh( `${ wp } user delete ${ user } --network --yes --allow-root >/dev/null 2>&1; ${ wp } user create ${ user } ${ user }@invalid.test --role=administrator --user_pass='${ pass }' --allow-root 2>&1 | grep -v Deprecated; ${ role } ${ linkIn } ${ wp } core version --allow-root; php -r 'echo PHP_VERSION;'` );
	const lines = made.stdout.trim().split( /\r?\n/ );
	if ( 0 !== made.status || ! /Success/.test( made.stdout ) ) {
		if ( linkOut ) {
			ssh( linkOut );
		}
		return { ok: false, steps: [], failed: `could not make an administrator on ${ cell.dir }: ${ made.stdout.trim().slice( -160 ) || made.stderr.trim().slice( -160 ) }` };
	}
	cell.wp = lines[ lines.length - 2 ] || cell.wp;
	cell.php = ( lines[ lines.length - 1 ] || cell.php ).split( '.' ).slice( 0, 2 ).join( '.' );

	try {
		return await fiveMinutes( cell.url, { VGML_USER: user, VGML_PASS: pass, VGML_MATRIX_NO_UNINSTALL: '1', VGML_MATRIX_PROBE: '', VGML_MATRIX_LOCALE: '', VGML_MATRIX_MUTATE: MUTATE ? '1' : '' } );
	} finally {
		ssh( `${ linkOut } ${ wp } user delete ${ user } --network --yes --allow-root >/dev/null 2>&1` );
	}
}

const stored = fs.existsSync( RESULTS ) ? JSON.parse( fs.readFileSync( RESULTS, 'utf8' ) ) : { fullRun: '', command: '', zip: '', cells: {} };
const failing = [];

for ( const cell of chosen ) {
	const started = Date.now();
	console.log( `\n  ▸ ${ cell.key }  ·  WP ${ cell.wp } · PHP ${ cell.php } · ${ cell.shape } · ${ cell.lang } · ${ cell.with ? COMPANIONS[ cell.with ].label : 'nothing' }` );

	let result;
	const skip = cell.with && COMPANIONS[ cell.with ].skip;
	if ( skip ) {
		// Recorded, not counted: the row says why it cannot run here.
		result = { ok: true, skipped: skip, steps: [], failed: '' };
		console.log( `    — ${ skip }` );
	} else if ( cell.box ) {
		result = await runBox( cell );
	} else {
		result = await runPlayground( cell );
	}

	const seconds = Math.round( ( Date.now() - started ) / 1000 );
	const passed = result.steps.filter( ( s ) => s.ok ).length;
	const note = cell.box ? ' · uninstall not run on the box' : '';
	stored.cells[ cell.key ] = {
		wp: cell.wp, php: cell.php, shape: cell.shape, lang: cell.lang,
		with: cell.with ? COMPANIONS[ cell.with ].label : 'nothing',
		ok: result.ok,
		skipped: result.skipped || '',
		step: result.skipped ? result.skipped : ( result.ok ? `all ${ result.steps.length } steps${ note }` : result.failed ),
		steps: result.steps,
		ran: today,
		seconds,
	};
	console.log( `  ${ result.ok ? '✓' : '✗' } ${ cell.key } · ${ passed }/${ result.steps.length } · ${ seconds }s` );
	if ( ! result.ok ) {
		failing.push( `${ cell.key } (${ result.failed })` );
	}
	// Written after every cell, so a run that dies keeps the rows it earned.
	if ( ! only ) {
		stored.fullRun = today;
		stored.command = 'node tools/matrix.mjs';
		stored.zip = zipDigest;
	}
	if ( ! MUTATE ) {
		fs.writeFileSync( RESULTS, JSON.stringify( stored, null, '	' ) + '\n' );
		writeDoc( stored );
	}
}

/*
 *  The table in docs/compatibility.md, between the markers, rendered from the
 *  results file every time so a one-cell rerun replaces one row and leaves
 *  the rest as they were.
 */
function writeDoc( data ) {
	const esc = ( s ) => String( s ).replace( /\|/g, '\\|' ).replace( /\r?\n/g, ' ' );
	const rows = cells.map( ( c ) => {
		const r = data.cells[ c.key ];
		if ( ! r ) {
			return `| ${ c.wp } | ${ c.php } | ${ c.shape } | ${ c.lang } | ${ c.with ? COMPANIONS[ c.with ].label : 'nothing' } | — | not run |`;
		}
		const rerun = r.ran !== data.fullRun ? ` (rerun ${ r.ran })` : '';
		return `| ${ r.wp } | ${ r.php } | ${ r.shape } | ${ r.lang } | ${ r.with } | ${ r.skipped ? '—' : ( r.ok ? '✓' : '✗' ) } | ${ esc( r.step ) }${ rerun } |`;
	} );
	const block = [
		'<!-- matrix:start -->',
		`## The matrix — ${ data.fullRun || today }`,
		'',
		`Produced ${ data.fullRun || today } by \`${ data.command || 'node tools/matrix.mjs --cell …' }\`: the release zip`,
		`(\`playground/vergelabs-media-library.zip\`, sha256 ${ data.zip || zipDigest }…) installed on every cell`,
		'and `tests/compat/five-minutes.mjs` run against it — make a folder, upload three',
		'images, drag one in, filter the grid by the folder, select two and move them in one',
		'drag, deactivate and delete. Any JS error from this plugin, any fatal, and anything',
		'of ours in the debug log is a ✗. A ✗ names the step. One cell reruns with',
		'`node tools/matrix.mjs --cell <key>` (`--list` names the keys); a rerun date on a',
		'row means that row is newer than the run above it.',
		'',
		'| WordPress | PHP | Shape | Language | Alongside | Result | Step |',
		'|---|---|---|---|---|---|---|',
		...rows,
		'',
		'The nine version cells are Playground (SQLite, no GD); the language and',
		'companion cells run on WordPress 7.1 / PHP 8.2 there. The multisite cells are the',
		'box\'s two networks — `/var/www/ms` (subdirectory) and `/var/www/ms2` (subdomain,',
		'tested on its sub-site `two.`) — real MariaDB, `WP_DEBUG` off, the uninstall step',
		'left out because deleting through the box\'s Plugins screen fatals in core\'s FTP',
		'class. FileBird on MariaDB is the box\'s own copy, linked into `/var/www/ms` for',
		'the run; Playground\'s SQLite refuses FileBird\'s own `FIND_IN_SET` query, which is',
		'what the Playground FileBird row shows.',
		'<!-- matrix:end -->',
	].join( '\n' );

	let doc = fs.readFileSync( DOC, 'utf8' );
	if ( /<!-- matrix:start -->[\s\S]*<!-- matrix:end -->/.test( doc ) ) {
		doc = doc.replace( /<!-- matrix:start -->[\s\S]*<!-- matrix:end -->/, block );
	} else {
		// Above the 2026-08-20 section: after the title, before anything else.
		doc = doc.replace( /^(# Compatibility\r?\n)/, `$1\n${ block }\n` );
	}
	fs.writeFileSync( DOC, doc );
}

console.log( '' );
if ( failing.length ) {
	console.log( `  ${ failing.length } of ${ chosen.length } cell(s) ✗:` );
	failing.forEach( ( f ) => console.log( `    ${ f }` ) );
	console.log( '' );
	process.exit( 1 );
}
console.log( `  ${ chosen.length } of ${ chosen.length } cell(s) ✓\n` );
process.exit( 0 );
