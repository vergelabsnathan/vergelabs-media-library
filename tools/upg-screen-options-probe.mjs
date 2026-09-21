/*
 *  Screen Options over the folder panel, probed on the upgrade fixture.
 *
 *      node tools/upg-screen-options-probe.mjs                      # as the fixture is
 *      node tools/upg-screen-options-probe.mjs --css css/vergeml-tree.css   # with this tree's sheet in place, put back after
 *      node tools/upg-screen-options-probe.mjs --restore            # put a kept original back, on its own
 *
 *  Found 2026-09-21 on upg (free 4.0.2): the list screen's Screen Options tab
 *  opened, and the panel it opens sat under the fixed folder tree -- three of
 *  the four column checkboxes answered elementFromPoint with a piece of the
 *  tree. modes.spec had passed because the columns it ticks sit right of the
 *  tree. This reads every checkbox at its centre, as a person's click would,
 *  and prints N/N; then closes the panel and reads the first folder row, so
 *  the lift did not take the tree's clicks with it. One shot into
 *  docs/superpowers/mocks/shots.
 *
 *  --css copies one stylesheet over the fixture's, keeps the original beside
 *  it and puts it back in `finally`; the digest is printed before anything is
 *  copied and compared after. --restore does only the putting back, for an
 *  ssh that died in between. Logs in as vgmls22 with the fixture's own
 *  password; writes nothing to the database.
 */
import { execFileSync } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

process.env.MSYS_NO_PATHCONV = '1';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const BOX = { host: '46.225.66.194', key: path.join( os.homedir(), '.ssh', 'hetzner_vgml' ), wp: '/var/www/upg' };
const BASE = 'http://upg.46.225.66.194.nip.io';
const PLUGIN = `${ BOX.wp }/wp-content/plugins/vergelabs-media-library`;
const OUT = path.join( ROOT, 'docs', 'superpowers', 'mocks', 'shots' );
const STAMP = new Date().toISOString().slice( 0, 10 );
const sshArgs = [ '-i', BOX.key, '-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null', '-o', 'LogLevel=ERROR', '-o', 'ConnectTimeout=15' ];

const argv = process.argv.slice( 2 );
const at = argv.indexOf( '--css' );
const CSS = at >= 0 ? argv[ at + 1 ] : '';
const RESTORE = argv.includes( '--restore' );

function box( script ) {
	return execFileSync( 'ssh', [ ...sshArgs, `root@${ BOX.host }`, 'bash -s' ], { input: script, stdio: 'pipe' } ).toString();
}

// The fixture's copy of the sheet, and the original kept beside it while ours is in place.
const target = () => `${ PLUGIN }/${ CSS ? path.posix.normalize( CSS.replace( /\\/g, '/' ) ) : 'css/vergeml-tree.css' }`;
const kept = () => `${ target() }.probe-orig`;
const digest = ( file ) => box( `sha256sum ${ file } 2>/dev/null | cut -c1-12` ).trim() || 'absent';

function restore() {
	const had = box( `test -f ${ kept() } && echo yes || echo no` ).trim();
	if ( 'no' === had ) {
		console.log( `restore: nothing kept at ${ path.posix.basename( kept() ) }; the fixture's sheet is ${ digest( target() ) }` );
		return true;
	}
	box( `mv -f ${ kept() } ${ target() }` );
	const now = digest( target() );
	console.log( `restore: ${ path.posix.basename( target() ) } put back, sha256 ${ now }` );
	return 'absent' !== now;
}

if ( RESTORE ) {
	process.exit( restore() ? 0 : 1 );
}

const before = digest( target() );
console.log( `fixture ${ path.posix.basename( target() ) } sha256 ${ before } before anything is copied` );

let copied = false;
let ok = false;
try {
	if ( CSS ) {
		const local = path.join( ROOT, CSS );
		box( `cp -f ${ target() } ${ kept() }` );
		copied = true;
		execFileSync( 'scp', [ ...sshArgs, local, `root@${ BOX.host }:${ target() }` ], { stdio: 'pipe' } );
		console.log( `copied ${ CSS } → fixture, sha256 ${ digest( target() ) } (kept the original as ${ path.posix.basename( kept() ) })` );
	}

	const pass = box( 'cat /root/.upg-admin-pass' ).trim();
	const browser = await chromium.launch();
	try {
		const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
		await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
		await page.fill( '#user_login', 'vgmls22' );
		await page.fill( '#user_pass', pass );
		await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
		if ( page.url().includes( 'wp-login' ) ) {
			throw new Error( 'login failed' );
		}

		await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'networkidle' } );
		await page.waitForSelector( '#the-list tr[id^="post-"]', { timeout: 30000 } );
		await page.waitForTimeout( 1200 );
		await page.evaluate( () => document.querySelectorAll( '.notice, .updated, .error' ).forEach( ( n ) => n.remove() ) );

		const layers = await page.evaluate( () => {
			const z = ( sel ) => { const el = document.querySelector( sel ); return el ? getComputedStyle( el ).zIndex : 'none'; };
			return { meta: z( '#screen-meta' ), tree: z( '.vgml-tree' ), position: z( '.vgml-tree' ) && ( document.querySelector( '.vgml-tree' ) ? getComputedStyle( document.querySelector( '.vgml-tree' ) ).position : 'none' ) };
		} );
		console.log( `layers: #screen-meta z ${ layers.meta }, .vgml-tree z ${ layers.tree } (${ layers.position })` );

		await page.click( '#show-settings-link', { timeout: 10000 } );
		await page.waitForSelector( '#adv-settings', { state: 'visible', timeout: 10000 } );
		await page.waitForTimeout( 400 );

		// The same reading modes.spec makes: the centre of every column checkbox.
		const boxes = await page.evaluate( () => Array.from( document.querySelectorAll( '#adv-settings .hide-column-tog' ) ).map( ( box ) => {
			box.scrollIntoView( { block: 'center' } );
			const r = box.getBoundingClientRect();
			const hit = document.elementFromPoint( r.x + r.width / 2, r.y + r.height / 2 );
			const label = ( box.closest( 'label' ) || {} ).textContent || box.id;
			return {
				label: label.trim(),
				ok: hit === box,
				hit: hit ? hit.tagName.toLowerCase() + ( hit.id ? '#' + hit.id : '' ) + ( 'string' === typeof hit.className && hit.className ? '.' + hit.className.trim().split( /\s+/ ).join( '.' ) : '' ) : 'nothing (off screen)',
				x: Math.round( r.x ),
			};
		} ) );
		const file = path.join( OUT, `${ STAMP }-screen-options-over-tree-upg.png` );
		await page.screenshot( { path: file } );
		for ( const b of boxes ) {
			console.log( `  ${ b.ok ? 'ok  ' : 'FAIL' }  ${ b.label.padEnd( 20 ) } x ${ String( b.x ).padStart( 4 ) }  → ${ b.hit }` );
		}
		const reached = boxes.filter( ( b ) => b.ok ).length;
		console.log( `${ reached }/${ boxes.length } checkboxes reachable  ${ path.basename( file ) }` );

		// Closed again, the tree still takes a click where it always did.
		await page.click( '#show-settings-link' );
		await page.waitForSelector( '#adv-settings', { state: 'hidden', timeout: 10000 } );
		await page.waitForTimeout( 300 );
		const row = await page.evaluate( () => {
			// The first row a person sees: the DOM's first .vgml-row can be a folded head with no box.
			const first = Array.from( document.querySelectorAll( '.vgml-tree .vgml-row' ) ).find( ( el ) => el.getBoundingClientRect().height > 0 );
			if ( ! first ) {
				return { found: false };
			}
			const r = first.getBoundingClientRect();
			const hit = document.elementFromPoint( r.x + r.width / 2, r.y + r.height / 2 );
			return { found: true, ok: !! ( hit && hit.closest( '.vgml-row' ) === first ), hit: hit ? hit.tagName.toLowerCase() + '.' + String( hit.className ).trim().split( /\s+/ ).join( '.' ) : 'nothing' };
		} );
		console.log( `${ row.found && row.ok ? 'ok  ' : 'FAIL' }  panel closed: a press on the first folder row lands in the row (${ row.found ? row.hit : 'no row found' })` );

		ok = boxes.length > 0 && reached === boxes.length && row.found && row.ok;
	} finally {
		await browser.close();
	}
} catch ( e ) {
	console.error( e.message );
} finally {
	if ( copied ) {
		const back = restore();
		const after = digest( target() );
		const same = after === before;
		console.log( `${ same && back ? 'ok  ' : 'FAIL' }  fixture sheet after: sha256 ${ after } (before ${ before })` );
		ok = ok && same && back;
	}
}
process.exit( ok ? 0 : 1 );
