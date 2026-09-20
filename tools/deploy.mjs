/*
 *  One way to get this plugin onto the things it is tested on.
 *
 *      node tools/deploy.mjs               # the Playground zip and the box
 *      node tools/deploy.mjs --zip         # only rebuild playground/*.zip
 *      node tools/deploy.mjs --box         # only ship to the test box
 *      node tools/deploy.mjs --check       # prove nothing, change nothing, report
 *      node tools/deploy.mjs --check --zip # the same, the zip only
 *      node tools/deploy.mjs --box 46.225.66.194
 *
 *  Written on 31-08-2026, after a session spent rebuilding a nav item that was
 *  already correct.
 *
 *  Three copies of this plugin existed and none of them agreed. The source in
 *  the repo had the change; playground/vergelabs-media-library.zip was ten days
 *  old and did not even contain core/admin-shell.php; the box at 46.225.66.194
 *  was running the previous day's files. Every screenshot and every "it is
 *  still the same" came from one of the two stale ones, and there was nothing
 *  anywhere that could have said so.
 *
 *  So the rule this file exists to enforce: a deploy is not finished when the
 *  copy is made. It is finished when the destination has been asked what it is
 *  running and has answered with the digest of what was sent. Nothing here
 *  reports success from an exit code -- see verifyBox(), which re-hashes every
 *  shipped file on the far end and compares. `docs/testing.md` has the same
 *  rule for the suites; this is it for the files underneath them.
 */
import { execFileSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { crc32, readZipIndex, writeZip } from './lib/zip.mjs';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const SLUG = 'vergelabs-media-library';
const ZIP = path.join( ROOT, 'playground', `${ SLUG }.zip` );

const argv = process.argv.slice( 2 );

function flagValue( name, fallback ) {
	const at = argv.indexOf( name );
	if ( at < 0 ) {
		return fallback;
	}
	const next = argv[ at + 1 ];
	return next && ! next.startsWith( '--' ) ? next : fallback;
}

const CHECK = argv.includes( '--check' );
const ONLY_ZIP = argv.includes( '--zip' );
const ONLY_BOX = argv.includes( '--box' );
// --check alone checks both; --check --zip checks only the zip (the
// archive-hygiene suite asks that, with no box in reach).
const DO_ZIP = ONLY_ZIP || ! ONLY_BOX;
const DO_BOX = ONLY_BOX || ! ONLY_ZIP;

/*
 *  The same map verify.mjs keeps, and for the same reason: a box it does not
 *  know is refused loudly rather than quietly written to. Somewhere this plugin
 *  is copied onto is somewhere its files can be destroyed.
 */
const BOXES = {
	//  Hetzner CX33, Nuremberg. Ubuntu 26.04 / PHP 8.5 / MariaDB 11.8.
	'46.225.66.194': { key: '~/.ssh/hetzner_vgml', wp: '/var/www/wp' },
};

const BOX_HOST = flagValue( '--box', '46.225.66.194' );

/*
 *  What a running plugin needs, and nothing else.
 *
 *  Shipping tests/ and plans/ to a public box puts the fixtures and the
 *  unreleased roadmap on a webserver. node_modules is 40MB of things that
 *  never execute in PHP.
 *
 *  These two lists serve only the walk below, for a folder with no git in it.
 *  With git, the one ship list is `.gitattributes` (export-ignore): the same
 *  list `git archive` cuts the release from. The two lists disagreed for a
 *  week -- `_bmad`, `_bmad-output`, `.harness` and `AGENTS.md` were in the
 *  archive's list and not here, so the Playground zip and the box carried 32
 *  files no customer receives (Epic 1 retro, A-1).
 */
const SKIP = new Set( [
	'.git', '.github', '.claude', '.harness', 'node_modules', 'playground',
	'tests', 'plans', 'tickets', 'docs', 'tools', 'research', 'dist', 'site',
	'_bmad', '_bmad-output',
	// wordpress.org screenshots live in the SVN assets/ directory, not in
	// the plugin; shipping them puts 315KB of PNG on every install.
	'.release-assets', 'assets',
] );

// Dotfiles and repo furniture. Plugin Check flags every hidden file it finds
// in a zip, and none of these does anything on a site. The manifest is added
// to the payload separately, by name, so it is not walked here.
const SKIP_FILE = new Set( [
	'.verify.lock', 'package-lock.json', 'package.json', 'pnpm-lock.yaml', 'CLAUDE.md', 'AGENTS.md',
	'.wp-env.json', '.gitignore', '.gitattributes', '.deploy-manifest',
] );


/* ------------------------------------------------------------- the payload */

/**
 *  Which of these paths `.gitattributes` marks export-ignore.
 *
 *  git sets the attribute on the entry the pattern names -- `/tools` marks
 *  `tools`, not `tools/verify.mjs` -- and `git archive` skips the subtree
 *  because it meets the directory first. So every ancestor of every file is
 *  asked as well, in one call.
 */
function exportIgnored( files ) {

	const ask = new Set();
	for ( const rel of files ) {
		const parts = rel.split( '/' );
		for ( let i = 1; i <= parts.length; i++ ) {
			ask.add( parts.slice( 0, i ).join( '/' ) );
		}
	}

	const answer = execFileSync( 'git', [ 'check-attr', '-z', '--stdin', 'export-ignore' ], {
		cwd: ROOT,
		input: [ ...ask ].join( '\0' ) + '\0',
		maxBuffer: 32 * 1024 * 1024,
	} ).toString().split( '\0' );

	// -z output is path, attribute, value, repeated.
	const ignored = new Set();
	for ( let i = 0; i + 2 < answer.length; i += 3 ) {
		if ( 'set' === answer[ i + 2 ] ) {
			ignored.add( answer[ i ] );
		}
	}

	return ( rel ) => {
		const parts = rel.split( '/' );
		for ( let i = 1; i <= parts.length; i++ ) {
			if ( ignored.has( parts.slice( 0, i ).join( '/' ) ) ) {
				return true;
			}
		}
		return false;
	};
}


/**
 *  Every file that ships, relative to the plugin root, sorted.
 *
 *  What git tracks, minus what `.gitattributes` export-ignores -- the same
 *  answer `git archive` gives, read from the working tree so a fix in the
 *  tree is what ships. Not what happens to be in the folder.
 *
 *  This used to walk the filesystem, and a walk ships whatever is lying
 *  around. On 2026-09-07 the zip held twenty files nobody had committed: a
 *  dozen screenshots in `test-results/` and, worse, a scratch folder from a
 *  local tool with a session token in it. Every one of them was in
 *  `.gitignore` already; the walk simply never asked. Plugin Check flags
 *  hidden files in a zip, so the tooling was also going to fail the listing
 *  for a reason nothing on the screen explained.
 *
 *  If git cannot answer -- an export with no repository -- the walk is still
 *  there, and it says so rather than shipping silently.
 */
function payload() {

	try {
		const tracked = execFileSync( 'git', [ 'ls-files', '-z' ], { cwd: ROOT, maxBuffer: 32 * 1024 * 1024 } )
			.toString()
			.split( '\0' )
			.filter( Boolean );

		if ( tracked.length ) {
			const ignored = exportIgnored( tracked );
			return tracked
				.filter( ( rel ) => ! ignored( rel ) )
				.filter( ( rel ) => fs.existsSync( path.join( ROOT, rel ) ) )
				.sort();
		}
	} catch {
		console.log( '  note  git could not list the tracked files; falling back to walking the folder' );
	}

	const out = [];

	( function walk( dir ) {
		for ( const entry of fs.readdirSync( dir, { withFileTypes: true } ) ) {

			if ( entry.isDirectory() ) {
				if ( ! SKIP.has( entry.name ) ) {
					walk( path.join( dir, entry.name ) );
				}
				continue;
			}

			const rel = path.relative( ROOT, path.join( dir, entry.name ) ).split( path.sep ).join( '/' );

			if ( ! SKIP_FILE.has( rel ) ) {
				out.push( rel );
			}
		}
	} )( ROOT );

	return out.sort();
}


function sha256( file ) {
	return crypto.createHash( 'sha256' ).update( fs.readFileSync( file ) ).digest( 'hex' );
}


/** path + digest per line: what the far end is asked to prove it holds. */
function manifest( files ) {
	return files.map( ( rel ) => `${ sha256( path.join( ROOT, rel ) ) }  ${ rel }` ).join( '\n' ) + '\n';
}


function digestOf( text ) {
	return crypto.createHash( 'sha256' ).update( text ).digest( 'hex' ).slice( 0, 12 );
}


function head() {
	try {
		return execFileSync( 'git', [ 'rev-parse', '--short', 'HEAD' ], { cwd: ROOT } ).toString().trim();
	} catch {
		return 'nogit';
	}
}


function dirty() {
	try {
		return execFileSync( 'git', [ 'status', '--porcelain' ], { cwd: ROOT } ).toString().trim() !== '';
	} catch {
		return false;
	}
}


/* ----------------------------------------------------------------- zipping */

/*
 *  The zip writer and the central-directory reader live in tools/lib/zip.mjs
 *  since 2026-09-20 (they were written here; that file's header keeps the
 *  reason a packaging dependency was refused). Same bytes, one copy.
 */


/* ------------------------------------------------------------ the zip file */

/*
 *  playground/blueprint.json installs this over the network from the repo's raw
 *  URL, so the file committed here is what every person opening the Playground
 *  link runs. It was ten days behind the source and nothing said so, which is
 *  why staleness is now an error rather than an observation.
 */
/*
 *  No manifest in this one.
 *
 *  It used to carry `.deploy-manifest`, and Plugin Check refuses a zip with a
 *  hidden file in it: "Hidden files are not permitted", one ERROR, which is
 *  the wordpress.org submission gate saying no. The manifest exists so the box
 *  can prove it holds what was sent (see the payload below, which still
 *  carries it); nothing installing this zip ever reads it.
 */
function zipEntries( files ) {
	return files.map( ( rel ) => [ `${ SLUG }/${ rel }`, fs.readFileSync( path.join( ROOT, rel ) ) ] );
}


function buildZip( files ) {

	fs.mkdirSync( path.dirname( ZIP ), { recursive: true } );
	writeZip( ZIP, zipEntries( files ) );

	return fs.statSync( ZIP ).size;
}


/**
 *  Whether the committed zip holds exactly this payload.
 *
 *  Compared on CRC per entry rather than on a manifest read out of it: the
 *  manifest could be right while a file beside it was not, and the central
 *  directory already carries a checksum of every entry.
 */
function zipMatches( files ) {

	const have = readZipIndex( ZIP );

	if ( have === null ) {
		return { ok: false, why: 'no readable zip' };
	}

	const want = new Map( zipEntries( files ).map( ( [ name, body ] ) => [ name, crc32( body ) ] ) );

	for ( const [ name, sum ] of want ) {
		if ( ! have.has( name ) ) {
			return { ok: false, why: `missing ${ name.replace( `${ SLUG }/`, '' ) }` };
		}
		if ( have.get( name ) !== sum ) {
			return { ok: false, why: `${ name.replace( `${ SLUG }/`, '' ) } differs` };
		}
	}

	for ( const name of have.keys() ) {
		if ( ! want.has( name ) ) {
			return { ok: false, why: `holds a file no longer shipped: ${ name.replace( `${ SLUG }/`, '' ) }` };
		}
	}

	return { ok: true };
}


/* --------------------------------------------------------------- the box */

/*
 *  The remote script goes in over stdin, not as an argument.
 *
 *  Passed as an argv entry it is re-quoted twice -- once by Node's Windows
 *  command-line builder and once by the remote shell -- and a script with
 *  newlines in it arrives mangled. `bash -s` reads it verbatim, and stderr is
 *  captured so a failure says what the far end actually complained about
 *  rather than only which command exited non-zero.
 */
function ssh( box, script ) {
	try {
		return execFileSync( 'ssh', [
			'-i', box.key.replace( /^~/, os.homedir() ),
			'-o', 'StrictHostKeyChecking=no',
			'-o', 'ConnectTimeout=15',
			`root@${ BOX_HOST }`, 'bash -s',
		], { input: script, stdio: 'pipe', maxBuffer: 32 * 1024 * 1024 } ).toString();
	} catch ( err ) {
		// Both streams: php -l's complaint is on stdout, and a "Command failed: ssh …" line says nothing (2026-09-16).
		const said = [ err.stdout, err.stderr ].map( ( s ) => ( s || '' ).toString().trim() ).filter( Boolean ).join( '\n' );
		throw new Error( said || err.message );
	}
}


function scp( box, from, to ) {
	execFileSync( 'scp', [
		'-i', box.key.replace( /^~/, os.homedir() ),
		'-o', 'StrictHostKeyChecking=no',
		from, `root@${ BOX_HOST }:${ to }`,
	], { stdio: 'pipe' } );
}


/*
 *  Ask the box what it is running.
 *
 *  Not "did the copy exit zero" -- it always does. Every shipped file is
 *  re-hashed on the far end and compared against the manifest that went with
 *  it, so "deployed" means the bytes match and nothing else.
 *
 *  And the manifest itself is compared to the one this working tree makes
 *  right now (S21). Without that, --check only proved the box still held what
 *  the last deploy sent it: on 2026-09-19 it answered "box up to date" over a
 *  working tree with three changed files the box had never seen, which is the
 *  exact reassurance this file exists to refuse to give. A box whose files
 *  match an old manifest is a box that is behind, not a box that is current.
 */
function verifyBox( box, want ) {

	const dir = `${ box.wp }/wp-content/plugins/${ SLUG }`;

	const out = ssh( box, `
		set -e
		cd ${ dir } 2>/dev/null || { echo 'MISSING_DIR'; exit 0; }
		[ -f .deploy-manifest ] || { echo 'NO_MANIFEST'; exit 0; }
		echo "MANIFEST $( sha256sum .deploy-manifest | cut -c1-12 )"
		sha256sum -c .deploy-manifest --quiet 2>&1 | head -20
		echo "CHECKED $( wc -l < .deploy-manifest )"
	` ).trim();

	if ( out.includes( 'MISSING_DIR' ) ) {
		return { ok: false, why: 'the plugin directory does not exist on the box' };
	}

	if ( out.includes( 'NO_MANIFEST' ) ) {
		return { ok: false, why: 'no .deploy-manifest -- this copy predates deploy.mjs' };
	}

	const bad = out.split( '\n' ).filter( ( l ) => l.includes( 'FAILED' ) || l.includes( 'differ' ) );
	const checked = ( out.match( /CHECKED (\d+)/ ) || [ , '0' ] )[ 1 ];
	const there = ( out.match( /MANIFEST ([0-9a-f]+)/ ) || [ , '' ] )[ 1 ];

	if ( want && there !== want ) {
		return { ok: false, why: `the box holds another build (its manifest is ${ there || 'unreadable' }, this tree's is ${ want })` };
	}

	return bad.length
		? { ok: false, why: `${ bad.length } file(s) on the box differ`, detail: bad.slice( 0, 6 ) }
		: { ok: true, checked: Number( checked ) };
}


function deployBox( box, files, mf ) {

	const dir = `${ box.wp }/wp-content/plugins/${ SLUG }`;

	const staging = fs.mkdtempSync( path.join( os.tmpdir(), 'vgml-box-' ) );
	const bundle = path.join( staging, 'payload.zip' );

	/*
	 *  Unprefixed names: this one unpacks *into* the plugin directory, where
	 *  the Playground zip carries the plugin folder itself.
	 */
	writeZip( bundle, [
		...files.map( ( rel ) => [ rel, fs.readFileSync( path.join( ROOT, rel ) ) ] ),
		[ '.deploy-manifest', Buffer.from( mf, 'utf8' ) ],
	] );

	scp( box, bundle, '/tmp/vgml-payload.zip' );

	/*
	 *  The gate. Every PHP file is parsed on the box's own PHP before a byte
	 *  of it is written over the live plugin. A stray comma once took every
	 *  page on the box down for seven minutes; php -l would have said so in
	 *  one second. Output is printed, never hidden -- that was the other half
	 *  of that mistake.
	 */
	console.log( ssh( box, `
		set -e
		rm -rf /tmp/vgml-stage && mkdir -p /tmp/vgml-stage && cd /tmp/vgml-stage
		unzip -o -q /tmp/vgml-payload.zip
		bad=0
		while IFS= read -r f; do
			out=$( php -l "$f" 2>&1 ) || { echo "$out"; bad=1; }
		done < <( find . -name '*.php' )
		cd / && rm -rf /tmp/vgml-stage
		if [ "$bad" != "0" ]; then echo "php -l failed; nothing deployed"; rm -f /tmp/vgml-payload.zip; exit 1; fi
		echo "php -l: every file parses"
	` ).trim() );

	/*
	 *  A copy of what is there before anything is written over it. The box
	 *  holds a WordPress somebody is looking at; a bad deploy should cost a
	 *  restore, not an afternoon.
	 */
	ssh( box, `
		set -e
		D=${ dir }
		[ -d "$D" ] && cp -a "$D" /root/vgml-backup-$( date +%Y%m%d-%H%M%S )
		mkdir -p "$D"
		cd "$D"
		#  What the payload is about to write, so anything else can go.
		unzip -Z1 /tmp/vgml-payload.zip | sed 's|/$||' | LC_ALL=C sort -u > /tmp/vgml-manifest.txt
		unzip -o -q /tmp/vgml-payload.zip
		#
		#  A deploy REPLACES the plugin; it does not add to it.
		#
		#  Overlaying and never removing let 1,853 files accumulate in a public
		#  plugin directory on 2026-09-10 -- tools/, tests/, docs/, plans/,
		#  node_modules/, test-results/ -- none of which the release ships. Two
		#  costs, and the second is the serious one: Plugin Check read 1,045
		#  errors that were almost all dev leftovers, so the submission number
		#  was unmeasurable; and tools/*.php, which take environment variables
		#  and write to the database, were sitting under a webserver.
		#
		#  Anything in the directory that is not in the payload is removed.
		find . -type f | sed 's|^\./||' | LC_ALL=C sort -u > /tmp/vgml-present.txt
		LC_ALL=C comm -23 /tmp/vgml-present.txt /tmp/vgml-manifest.txt > /tmp/vgml-stray.txt
		strays=$( wc -l < /tmp/vgml-stray.txt )
		if [ "$strays" -gt 0 ]; then
			echo "removing $strays file(s) the release does not ship"
			while IFS= read -r f; do rm -f "./$f"; done < /tmp/vgml-stray.txt
			find . -type d -empty -delete
		fi
		rm -f /tmp/vgml-manifest.txt /tmp/vgml-present.txt /tmp/vgml-stray.txt
		chown -R www-data:www-data "$D"
		rm -f /tmp/vgml-payload.zip
		#  The zip carries no modification times, so every file lands stamped
		#  1980-01-01 -- and vergeml_asset_ver() builds its cache-busting
		#  string out of exactly that. Deploy after deploy produced the same
		#  string, so browsers kept the JavaScript they already had and a
		#  deployed fix stayed invisible to the person looking at the screen.
		#  Stamping them now is what makes a deploy visible.
		find "$D" -type f -exec touch {} +
	` );

	/*
	 *  PHP holds compiled files in opcache, so the new bytes on disk are not
	 *  necessarily the code being run. Clearing it is the difference between
	 *  deploying and appearing to deploy.
	 */
	ssh( box, `
		cd ${ box.wp }
		php -r 'function_exists("opcache_reset") && opcache_reset();' >/dev/null 2>&1 || true
		for s in $( systemctl list-units --type=service --no-legend 'php*-fpm*' | awk '{print $1}' ); do
			systemctl reload "$s" >/dev/null 2>&1 || true
		done
	` );

	fs.rmSync( staging, { recursive: true, force: true } );
}


/* ------------------------------------------------------------------- main */

const files = payload();
const mf = manifest( files );
const want = digestOf( mf );

console.log( `\n  ${ files.length } files  ·  ${ head() }${ dirty() ? ' (uncommitted changes)' : '' }  ·  digest ${ want }\n` );

let failed = false;

if ( DO_ZIP ) {

	const before = zipMatches( files );

	if ( CHECK ) {
		console.log( before.ok ? '  zip   up to date' : `  zip   STALE -- ${ before.why }` );
		failed = failed || ! before.ok;
	} else if ( before.ok ) {
		console.log( '  zip   already current, left alone' );
	} else {
		const size = buildZip( files );
		const after = zipMatches( files );
		console.log( after.ok
			? `  zip   rebuilt and verified  ${ ( size / 1024 ).toFixed( 0 ) }KB  ${ path.relative( ROOT, ZIP ) }`
			: `  zip   REBUILT BUT DOES NOT VERIFY -- ${ after.why }` );
		failed = failed || ! after.ok;
	}
}

if ( DO_BOX ) {

	const box = BOXES[ BOX_HOST ];

	if ( ! box ) {
		console.error( `\n  ${ BOX_HOST } is not a box this script knows.` );
		console.error( '  Deploying overwrites a plugin directory, so the host has to be listed in BOXES first.\n' );
		process.exit( 1 );
	}

	try {
		if ( CHECK ) {
			const v = verifyBox( box, want );
			console.log( v.ok
				? `  box   up to date  (${ v.checked } files verified)`
				: `  box   STALE -- ${ v.why }` );
			failed = failed || ! v.ok;
		} else {
			deployBox( box, files, mf );
			const v = verifyBox( box, want );
			console.log( v.ok
				? `  box   deployed and verified  (${ v.checked } files re-hashed on ${ BOX_HOST })`
				: `  box   DEPLOY DID NOT VERIFY -- ${ v.why }` );
			if ( v.detail ) {
				v.detail.forEach( ( d ) => console.log( `          ${ d }` ) );
			}
			failed = failed || ! v.ok;
		}
	} catch ( err ) {
		console.log( `  box   unreachable -- ${ String( err.message || err ).split( '\n' )[ 0 ] }` );
		failed = true;
	}
}

if ( DO_ZIP && ! CHECK && ! failed ) {
	console.log( '\n  the zip is what the Playground link serves -- commit it, or the link stays behind' );
}

console.log( '' );
process.exit( failed ? 1 : 0 );
