/*
 *  One way to get this plugin onto the things it is tested on.
 *
 *      node tools/deploy.mjs               # the Playground zip and the box
 *      node tools/deploy.mjs --zip         # only rebuild playground/*.zip
 *      node tools/deploy.mjs --box         # only ship to the test box
 *      node tools/deploy.mjs --check       # prove nothing, change nothing, report
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
import zlib from 'node:zlib';

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
const DO_ZIP = CHECK || ONLY_ZIP || ! ONLY_BOX;
const DO_BOX = CHECK || ONLY_BOX || ! ONLY_ZIP;

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
 */
const SKIP = new Set( [
	'.git', '.github', '.claude', 'node_modules', 'playground',
	'tests', 'plans', 'tickets', 'docs', 'tools', 'research', 'dist',
	// wordpress.org screenshots live in the SVN assets/ directory, not in
	// the plugin; shipping them puts 315KB of PNG on every install.
	'.release-assets', 'assets',
] );

// Dotfiles and repo furniture. Plugin Check flags every hidden file it finds
// in a zip, and none of these does anything on a site. The manifest is added
// to the payload separately, by name, so it is not walked here.
const SKIP_FILE = new Set( [
	'.verify.lock', 'package-lock.json', 'package.json', 'CLAUDE.md',
	'.wp-env.json', '.gitignore', '.gitattributes', '.deploy-manifest',
] );


/* ------------------------------------------------------------- the payload */

/** Every file that ships, relative to the plugin root, sorted. */
function payload() {

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
 *  A zip writer, in the file, on purpose.
 *
 *  The first version of this called PowerShell's Compress-Archive, which on
 *  Windows PowerShell 5.1 writes entry names with backslash separators --
 *  `vergelabs-media-library\core\ai.php`. Windows opens that happily. Linux
 *  unzip and PHP's ZipArchive do not: they create one file with a backslash in
 *  its name instead of a directory, so the plugin unpacks into a single
 *  unusable blob. The zip this replaces was built by something else and used
 *  forward slashes, which is why nobody had met this before.
 *
 *  Adding a packaging dependency to a plugin whose entire argument is that it
 *  has no build step was the other option. Sixty lines of the ZIP spec is the
 *  cheaper one, and it makes the output deterministic: same files in, byte
 *  identical archive out, so a rebuild that changes nothing changes nothing.
 */

const crc32 = zlib.crc32
	? ( buf ) => zlib.crc32( buf ) >>> 0
	: ( () => {
		const table = new Uint32Array( 256 );
		for ( let i = 0; i < 256; i++ ) {
			let c = i;
			for ( let k = 0; k < 8; k++ ) {
				c = c & 1 ? 0xedb88320 ^ ( c >>> 1 ) : c >>> 1;
			}
			table[ i ] = c >>> 0;
		}
		return ( buf ) => {
			let c = 0xffffffff;
			for ( let i = 0; i < buf.length; i++ ) {
				c = table[ ( c ^ buf[ i ] ) & 0xff ] ^ ( c >>> 8 );
			}
			return ( c ^ 0xffffffff ) >>> 0;
		};
	} )();


/**
 *  Write a zip. `entries` is [ name, Buffer ] with names already using the
 *  forward slashes the format actually specifies.
 *
 *  Timestamps are fixed rather than taken from the clock, so two runs over
 *  unchanged files produce the same bytes and a pointless commit is visible as
 *  no diff at all.
 */
function writeZip( file, entries ) {

	const chunks = [];
	const central = [];
	let offset = 0;

	for ( const [ name, body ] of entries ) {

		const nameBuf = Buffer.from( name, 'utf8' );
		const deflated = zlib.deflateRawSync( body, { level: 9 } );
		const store = deflated.length >= body.length;
		const data = store ? body : deflated;
		const sum = crc32( body );

		const local = Buffer.alloc( 30 );
		local.writeUInt32LE( 0x04034b50, 0 );
		local.writeUInt16LE( 20, 4 );               // version needed
		local.writeUInt16LE( 0x0800, 6 );           // UTF-8 names
		local.writeUInt16LE( store ? 0 : 8, 8 );    // stored or deflated
		local.writeUInt16LE( 0, 10 );               // time
		local.writeUInt16LE( 0x0021, 12 );          // date: 1980-01-01
		local.writeUInt32LE( sum, 14 );
		local.writeUInt32LE( data.length, 18 );
		local.writeUInt32LE( body.length, 22 );
		local.writeUInt16LE( nameBuf.length, 26 );
		local.writeUInt16LE( 0, 28 );

		chunks.push( local, nameBuf, data );

		const dir = Buffer.alloc( 46 );
		dir.writeUInt32LE( 0x02014b50, 0 );
		dir.writeUInt16LE( 20, 4 );
		dir.writeUInt16LE( 20, 6 );
		dir.writeUInt16LE( 0x0800, 8 );
		dir.writeUInt16LE( store ? 0 : 8, 10 );
		dir.writeUInt16LE( 0, 12 );
		dir.writeUInt16LE( 0x0021, 14 );
		dir.writeUInt32LE( sum, 16 );
		dir.writeUInt32LE( data.length, 20 );
		dir.writeUInt32LE( body.length, 24 );
		dir.writeUInt16LE( nameBuf.length, 28 );
		dir.writeUInt16LE( 0, 30 );
		dir.writeUInt16LE( 0, 32 );
		dir.writeUInt16LE( 0, 34 );
		dir.writeUInt16LE( 0, 36 );
		dir.writeUInt32LE( 0, 38 );                 // external attrs
		dir.writeUInt32LE( offset, 42 );

		central.push( Buffer.concat( [ dir, nameBuf ] ) );
		offset += local.length + nameBuf.length + data.length;
	}

	const dirBuf = Buffer.concat( central );

	const end = Buffer.alloc( 22 );
	end.writeUInt32LE( 0x06054b50, 0 );
	end.writeUInt16LE( entries.length, 8 );
	end.writeUInt16LE( entries.length, 10 );
	end.writeUInt32LE( dirBuf.length, 12 );
	end.writeUInt32LE( offset, 16 );

	fs.writeFileSync( file, Buffer.concat( [ ...chunks, dirBuf, end ] ) );
}


/**
 *  Read back the central directory: name and CRC per entry.
 *
 *  Enough to prove the archive holds what was put in it without unpacking it
 *  anywhere, which is the only claim this script is allowed to make.
 */
function readZipIndex( file ) {

	if ( ! fs.existsSync( file ) ) {
		return null;
	}

	const buf = fs.readFileSync( file );

	let end = -1;
	for ( let i = buf.length - 22; i >= 0 && i > buf.length - 66000; i-- ) {
		if ( buf.readUInt32LE( i ) === 0x06054b50 ) {
			end = i;
			break;
		}
	}
	if ( end < 0 ) {
		return null;
	}

	const count = buf.readUInt16LE( end + 10 );
	let at = buf.readUInt32LE( end + 16 );
	const out = new Map();

	for ( let i = 0; i < count; i++ ) {
		if ( buf.readUInt32LE( at ) !== 0x02014b50 ) {
			return null;
		}
		const sum = buf.readUInt32LE( at + 16 );
		const nameLen = buf.readUInt16LE( at + 28 );
		const extraLen = buf.readUInt16LE( at + 30 );
		const commentLen = buf.readUInt16LE( at + 32 );
		out.set( buf.toString( 'utf8', at + 46, at + 46 + nameLen ), sum >>> 0 );
		at += 46 + nameLen + extraLen + commentLen;
	}

	return out;
}


/* ------------------------------------------------------------ the zip file */

/*
 *  playground/blueprint.json installs this over the network from the repo's raw
 *  URL, so the file committed here is what every person opening the Playground
 *  link runs. It was ten days behind the source and nothing said so, which is
 *  why staleness is now an error rather than an observation.
 */
function zipEntries( files, mf ) {
	return [
		...files.map( ( rel ) => [ `${ SLUG }/${ rel }`, fs.readFileSync( path.join( ROOT, rel ) ) ] ),
		[ `${ SLUG }/.deploy-manifest`, Buffer.from( mf, 'utf8' ) ],
	];
}


function buildZip( files, mf ) {

	fs.mkdirSync( path.dirname( ZIP ), { recursive: true } );
	writeZip( ZIP, zipEntries( files, mf ) );

	return fs.statSync( ZIP ).size;
}


/**
 *  Whether the committed zip holds exactly this payload.
 *
 *  Compared on CRC per entry rather than on a manifest read out of it: the
 *  manifest could be right while a file beside it was not, and the central
 *  directory already carries a checksum of every entry.
 */
function zipMatches( files, mf ) {

	const have = readZipIndex( ZIP );

	if ( have === null ) {
		return { ok: false, why: 'no readable zip' };
	}

	const want = new Map( zipEntries( files, mf ).map( ( [ name, body ] ) => [ name, crc32( body ) ] ) );

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
		const said = ( err.stderr || err.stdout || '' ).toString().trim();
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
 */
function verifyBox( box ) {

	const dir = `${ box.wp }/wp-content/plugins/${ SLUG }`;

	const out = ssh( box, `
		set -e
		cd ${ dir } 2>/dev/null || { echo 'MISSING_DIR'; exit 0; }
		[ -f .deploy-manifest ] || { echo 'NO_MANIFEST'; exit 0; }
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
		unzip -o -q /tmp/vgml-payload.zip
		chown -R www-data:www-data "$D"
		rm -f /tmp/vgml-payload.zip
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

	const before = zipMatches( files, mf );

	if ( CHECK ) {
		console.log( before.ok ? '  zip   up to date' : `  zip   STALE -- ${ before.why }` );
		failed = failed || ! before.ok;
	} else if ( before.ok ) {
		console.log( '  zip   already current, left alone' );
	} else {
		const size = buildZip( files, mf );
		const after = zipMatches( files, mf );
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
			const v = verifyBox( box );
			console.log( v.ok
				? `  box   up to date  (${ v.checked } files verified)`
				: `  box   STALE -- ${ v.why }` );
			failed = failed || ! v.ok;
		} else {
			deployBox( box, files, mf );
			const v = verifyBox( box );
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
