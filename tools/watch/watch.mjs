/*
 *  The watch.
 *
 *  Two lists, one run:
 *
 *  - rivals: the plugins we compete with, polled on wordpress.org, their new
 *    builds hashed file by file so the report says "twelve files changed, four
 *    in the folder tree" rather than "there is a new version";
 *  - dependencies (tools/watch/contract.json): WordPress core, PHP and every
 *    plugin or theme this plugin integrates with. A moved dependency is
 *    downloaded when a zip exists and grepped for every symbol we rely on. A
 *    missing symbol is a proven break, hours before anyone could reproduce it.
 *
 *  The box's stage site is the primary signal for dependencies: WordPress's own
 *  updater reports a new version for anything installed there, paid plugins
 *  included, licence or not. wordpress.org and changelog pages fill in the rest.
 *
 *      node tools/watch/watch.mjs                 check and report
 *      node tools/watch/watch.mjs --seed          record current state, report nothing
 *      node tools/watch/watch.mjs --json          machine-readable, for triage
 *      node tools/watch/watch.mjs --pretend elementor=3.99.0   force a "moved" to exercise the wiring
 *      node tools/watch/watch.mjs --no-box        skip the ssh to the box
 *
 *  State lives in tools/watch/state.json and is committed, so the history of what
 *  they shipped is in git alongside what we shipped.
 *
 *  Only Node built-ins plus the platform's unzip (PowerShell on Windows, unzip on
 *  Linux, where the nightly Action runs). Nothing to install.
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync, rmSync, readdirSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as sources from './sources.mjs';
import { check as contractCheck } from './contract-check.mjs';

const HERE = dirname( fileURLToPath( import.meta.url ) );
const STATE = join( HERE, 'state.json' );
const CACHE = join( HERE, '.cache' );
const CONTRACT = JSON.parse( readFileSync( join( HERE, 'contract.json' ), 'utf8' ) );

const argv = process.argv.slice( 2 );
const SEED = argv.includes( '--seed' );
const JSON_OUT = argv.includes( '--json' );
const NO_BOX = argv.includes( '--no-box' );
const PRETEND = Object.fromEntries( argv.flatMap( ( a, i ) => a === '--pretend' && argv[ i + 1 ] ? [ argv[ i + 1 ].split( '=' ) ] : [] ) );

/*
 *  Why each rival is watched. A slug with no reason does not belong here -- the
 *  list gets long and then nobody reads the report.
 */
const RIVALS = [
	{ slug: 'filebird', why: 'market leader; custom tables, React' },
	{ slug: 'folders', why: 'Premio; native terms, the substrate we also chose' },
	{ slug: 'enhanced-media-library', why: 'upstream. If it revives, our fork story changes' },
	{ slug: 'real-media-library-lite', why: 'devowl.io; T3 importer target' },
	{ slug: 'media-library-organizer', why: 'WP Zinc; smaller but same pitch' },
	{ slug: 'enable-media-replace', why: '600k installs for one feature Folders Pro also sells' },
];

/* Files whose changes matter more than the rest, so the report can lead with them. */
const INTERESTING = [ /folder/i, /tree/i, /taxonom/i, /rest|api/i, /model/i, /migrat|upgrade|install/i, /media/i, /attachment/i ];

/* --- file manifest ------------------------------------------------------- */

async function download( url, to ) {
	const r = await fetch( url, { headers: { 'User-Agent': 'vergelabs-watch' } } );
	if ( ! r.ok ) throw new Error( `download HTTP ${ r.status }` );
	writeFileSync( to, Buffer.from( await r.arrayBuffer() ) );
}

function unzip( zip, dest ) {
	rmSync( dest, { recursive: true, force: true } );
	if ( process.platform === 'win32' ) {
		execFileSync( 'powershell', [ '-NoProfile', '-Command', `Expand-Archive -Path '${ zip }' -DestinationPath '${ dest }' -Force` ], { stdio: 'pipe' } );
	} else {
		mkdirSync( dest, { recursive: true } );
		execFileSync( 'unzip', [ '-oq', zip, '-d', dest ], { stdio: 'pipe' } );
	}
}

function manifest( root ) {
	const out = {};
	const walk = ( dir ) => {
		for ( const name of readdirSync( dir ) ) {
			const p = join( dir, name );
			const s = statSync( p );
			if ( s.isDirectory() ) { walk( p ); continue; }
			const rel = relative( root, p ).replace( /\\/g, '/' );
			out[ rel ] = createHash( 'sha1' ).update( readFileSync( p ) ).digest( 'hex' ).slice( 0, 12 );
		}
	};
	walk( root );
	return out;
}

function diffManifests( before, after ) {
	const added = [], removed = [], changed = [];
	for ( const f of Object.keys( after ) ) {
		if ( ! ( f in before ) ) added.push( f );
		else if ( before[ f ] !== after[ f ] ) changed.push( f );
	}
	for ( const f of Object.keys( before ) ) if ( ! ( f in after ) ) removed.push( f );
	return { added, removed, changed };
}

const interesting = ( files ) => files.filter( ( f ) => INTERESTING.some( ( re ) => re.test( f ) ) );

/* Download a zip, unpack it, hand the directory to fn, clean up. */
async function withUnpacked( slug, version, url, fn ) {
	const zip = join( CACHE, `${ slug }-${ version }.zip` );
	const dir = join( CACHE, `${ slug }-${ version }` );
	await download( url, zip );
	unzip( zip, dir );
	try { return await fn( dir ); } finally {
		rmSync( dir, { recursive: true, force: true } );
		rmSync( zip, { force: true } );
	}
}

/* --- run ----------------------------------------------------------------- */

const state = existsSync( STATE ) ? JSON.parse( readFileSync( STATE, 'utf8' ) ) : {};
state.plugins ??= {};      // rivals, by slug (kept under the old key so history reads on)
state.dependencies ??= {}; // by contract key
mkdirSync( CACHE, { recursive: true } );

const report = { rivals: [], dependencies: [], errors: [] };

/* rivals */
for ( const { slug, why } of RIVALS ) {
	let info;
	try { info = await sources.plugin( slug ); } catch ( e ) { report.errors.push( { slug, error: e.message } ); continue; }
	const prev = state.plugins[ slug ];
	const moved = ! prev || prev.version !== info.version;
	const entry = { slug, why, kind: 'rival', version: info.version, updated: info.updated, installs: info.installs, rating: info.rating, moved };
	let manifestNew = prev?.manifest;
	if ( moved ) {
		try {
			manifestNew = await withUnpacked( slug, info.version, info.download, ( dir ) => manifest( dir ) );
			if ( prev?.manifest ) {
				const d = diffManifests( prev.manifest, manifestNew );
				entry.files = { added: d.added.length, removed: d.removed.length, changed: d.changed.length, interesting: interesting( [ ...d.added, ...d.changed ] ).slice( 0, 25 ) };
			}
			entry.changelog = info.changelog;
		} catch ( e ) { entry.diffError = e.message; }
	}
	report.rivals.push( entry );
	state.plugins[ slug ] = { version: info.version, updated: info.updated, installs: info.installs, rating: info.rating, tested: info.tested, requires_php: info.requires_php, manifest: manifestNew, seen: new Date().toISOString() };
}

/* the box */
let box = null;
if ( ! NO_BOX ) {
	try { box = await sources.installed(); } catch ( e ) { report.errors.push( { slug: 'box', error: e.message } ); }
}

/* dependencies */
for ( const [ key, dep ] of Object.entries( CONTRACT.dependencies ) ) {
	const src = dep.source;
	const prev = state.dependencies[ key ];
	const entry = { key, name: dep.name, kind: dep.kind, sourceType: src.type, moved: false };

	try {
		if ( src.type === 'core' ) {
			const c = await sources.core();
			entry.version = c.version;
			entry.prerelease = c.prerelease;
			entry.download = c.download;
			entry.prereleaseDownload = c.prereleaseDownload;
			if ( box?.core?.version ) entry.onBox = box.core.version;
		} else if ( src.type === 'php' ) {
			const p = await sources.php();
			entry.version = Object.values( p ).sort().pop();
			entry.minors = p;
		} else {
			// The stage site first, then wordpress.org, then a changelog page.
			const onBox = src.installed && box ? box.byName[ src.installed ] : null;
			if ( onBox ) {
				entry.onBox = onBox.version;
				entry.version = onBox.update ?? onBox.version;
				entry.updateAvailableOnBox = onBox.update;
			}
			if ( src.type === 'wporg' ) {
				const info = await sources.plugin( src.slug );
				entry.version = info.version; // wordpress.org is authoritative for a free plugin
				entry.download = info.download;
				entry.changelog = info.changelog;
				entry.tested = info.tested;
				entry.requires_php = info.requires_php;
			} else if ( ! onBox && src.type === 'changelog' ) {
				const info = await sources.changelogPage( src.url, src.regex );
				entry.version = info.version;
				entry.changelogOnly = true;
			} else if ( ! onBox && src.type === 'none' ) {
				entry.version = null;
				entry.note = 'not installed anywhere we can see; contract only';
			}
		}
	} catch ( e ) {
		entry.error = e.message;
		report.errors.push( { slug: key, error: e.message } );
		report.dependencies.push( entry );
		continue;
	}

	if ( PRETEND[ key ] ) { entry.version = PRETEND[ key ]; entry.pretend = true; }

	// First sight is a baseline, not a move: the contract is checked so the
	// report shows where we stand, but nothing is triaged off it. --pretend is
	// always a move, so the wiring can be exercised without a real release.
	entry.firstSight = ! prev;
	entry.moved = !! entry.pretend || ( !! entry.version && !! prev && prev.version !== entry.version );
	entry.prereleaseMoved = !! entry.prerelease && !! prev && prev.prerelease !== entry.prerelease;

	// Contract check on anything moved (or first seen) that we can get a zip of.
	if ( ( entry.moved || entry.prereleaseMoved || entry.firstSight ) && dep.symbols.length ) {
		const url = entry.pretend ? entry.download : ( entry.moved ? entry.download : entry.prereleaseDownload );
		if ( url ) {
			try {
				entry.contract = await withUnpacked( key, entry.version, url, ( dir ) => {
					const r = contractCheck( dir, dep );
					return { scanned: r.scanned, found: r.found.length, missing: r.missing, unverifiable: r.unverifiable.map( ( u ) => u.value ) };
				} );
			} catch ( e ) { entry.contractError = e.message; }
		} else {
			entry.contract = { skipped: 'no zip without a licence; the stage leg is the proof' };
		}
	}

	report.dependencies.push( entry );
	state.dependencies[ key ] = { version: entry.version ?? prev?.version ?? null, prerelease: entry.prerelease ?? null, onBox: entry.onBox ?? null, seen: new Date().toISOString() };
}

if ( ! Object.keys( PRETEND ).length ) writeFileSync( STATE, JSON.stringify( state, null, 2 ) + '\n' );

/* --- verdicts -------------------------------------------------------------- */

function latestSection( changelog ) {
	// Headings look like "4.2.4 - 2026-08-31" or "= 4.2.4 =" or "4.2.4"; the
	// section ends where the next one starts.
	const lines = changelog.split( '\n' );
	const isHeading = ( l ) => /^\s*=?\s*v?\d+\.\d+(?:\.\d+)*\b/.test( l ) && l.trim().length < 80;
	const start = lines.findIndex( isHeading );
	if ( start === -1 ) return changelog.slice( 0, 1500 );
	const rest = lines.slice( start + 1 );
	const end = rest.findIndex( isHeading );
	return lines.slice( start, end === -1 ? undefined : start + 1 + end ).join( '\n' );
}

for ( const d of report.dependencies ) {
	if ( d.error ) { d.verdict = 'error'; continue; }
	if ( d.firstSight && ! d.moved ) { d.verdict = 'baseline'; d.reason = d.contract?.missing?.length ? `baseline, but ${ d.contract.missing.length } symbol(s) already missing: ` + d.contract.missing.map( ( m ) => m.value ).join( ', ' ) : 'baseline recorded'; continue; }
	if ( ! d.moved && ! d.prereleaseMoved ) { d.verdict = 'quiet'; continue; }
	if ( d.contract?.missing?.length ) { d.verdict = 'red'; d.reason = `${ d.contract.missing.length } symbol(s) gone: ` + d.contract.missing.map( ( m ) => m.value ).join( ', ' ); continue; }
	// Only the newest release's own section of the changelog: the text
	// wordpress.org returns covers many versions, and a word in last year's
	// entry says nothing about this one.
	const latest = latestSection( d.changelog ?? '' ).toLowerCase();
	const touches = [ 'media', 'attachment', 'upload', 'taxonom', 'rest api', 'deprecat', 'breaking', 'removed', 'hook', 'wp.media' ].filter( ( w ) => latest.includes( w ) );
	if ( touches.length ) { d.verdict = 'yellow'; d.reason = 'changelog mentions ' + touches.join( ', ' ); continue; }
	if ( d.contract?.skipped ) { d.verdict = 'yellow'; d.reason = d.contract.skipped; continue; }
	d.verdict = 'green';
	d.reason = d.contract ? `contract intact (${ d.contract.found } symbols found)` : 'nothing to check';
}

/* --- output ---------------------------------------------------------------- */

if ( JSON_OUT ) {
	console.log( JSON.stringify( report, null, 2 ) );
	process.exit( report.errors.length ? 3 : 0 );
}

const FLAG = { quiet: '   ', baseline: ' ==', green: ' ok', yellow: ' ! ', red: ' XX', error: ' ? ' };
console.log( '\nThe watch\n' );
console.log( '  dependencies' );
for ( const d of report.dependencies ) {
	const where = d.onBox ? `box ${ d.onBox }` : ( d.changelogOnly ? 'changelog' : ( d.sourceType === 'none' ? '-' : d.sourceType ) );
	console.log( `${ FLAG[ d.verdict ] } ${ d.key.padEnd( 28 ) } ${ String( d.version ?? '-' ).padEnd( 12 ) } ${ where.padEnd( 14 ) } ${ d.reason ?? d.error ?? ( d.prerelease ? `(${ d.prerelease } open)` : '' ) }` );
}
console.log( '\n  rivals' );
for ( const r of report.rivals ) {
	console.log( `${ r.moved ? ' **' : '   ' } ${ r.slug.padEnd( 28 ) } v${ String( r.version ).padEnd( 10 ) } ${ String( r.installs ?? '-' ).padStart( 9 ) } installs  ${ r.updated ?? '' }` );
	if ( r.moved && r.files ) {
		console.log( `      ${ r.files.changed } changed, ${ r.files.added } added, ${ r.files.removed } removed` );
		for ( const f of r.files.interesting ) console.log( `        ${ f }` );
	}
}
for ( const e of report.errors ) console.log( `\n  !  ${ e.slug }: ${ e.error }` );
console.log( SEED ? '\nbaseline recorded\n' : '' );
process.exit( report.errors.length ? 3 : 0 );
