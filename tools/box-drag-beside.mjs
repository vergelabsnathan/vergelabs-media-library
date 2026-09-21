/*
 *  The tree drag spec on the box's subdirectory network, beside a companion.
 *
 *      node tools/box-drag-beside.mjs                  # our plugin alone
 *      node tools/box-drag-beside.mjs --with filebird  # FileBird linked in for the run
 *      node tools/box-drag-beside.mjs --spec reparent  # tests/tree/reparent.mjs (folder drags) instead
 *
 *  tests/tree/drag.mjs takes the first file on the list and clears its
 *  folders, so it must never point at the tech library. This runs it on
 *  /var/www/ms the way tools/matrix.mjs runBox() runs a cell: a throwaway
 *  network administrator, the companion linked in from the box's own copy
 *  and switched on for the run, mock on with the vergeml_ai option put back
 *  in the finally. One 146-byte PNG is uploaded first so the spec has a file
 *  of its own to drag, and two scratch folders so the tree the spec waits for
 *  (more than three nodes) is there on a network that keeps none. The file,
 *  the scratch folders, the link and the user are gone after; the spec
 *  deletes its own two folders.
 *
 *  Story 4.4 (2026-09-21): beside FileBird 6.5.8 the single-row drag filed
 *  nothing, and drag.mjs alone could only be run on the tech site.
 */
import { spawnSync } from 'node:child_process';
import crypto from 'node:crypto';
import os from 'node:os';
import path from 'node:path';
import zlib from 'node:zlib';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const BOX = '46.225.66.194';
const URL_MS = `http://ms.${ BOX }.nip.io`;
const DIR = '/var/www/ms';
const SSH = [ '-i', path.join( os.homedir(), '.ssh', 'hetzner_vgml' ), '-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null', '-o', 'LogLevel=ERROR', `root@${ BOX }` ];
const ssh = ( cmd ) => spawnSync( 'ssh', [ ...SSH, cmd ], { encoding: 'utf8' } );

const argv = process.argv.slice( 2 );
const withAt = argv.indexOf( '--with' );
const companion = withAt >= 0 ? argv[ withAt + 1 ] || '' : '';
const specAt = argv.indexOf( '--spec' );
const spec = specAt >= 0 ? argv[ specAt + 1 ] || '' : 'drag';
if ( 'drag' !== spec && 'reparent' !== spec ) {
	console.error( `--spec takes drag or reparent, not "${ spec }"` );
	process.exit( 2 );
}
if ( withAt >= 0 && 'filebird' !== companion ) {
	console.error( companion ? `only filebird is on the box to link in, not "${ companion }"` : '--with needs a slug: --with filebird' );
	process.exit( 2 );
}

const user = 'vgmldrag'; // a-z0-9 only on a network
const pass = crypto.randomBytes( 12 ).toString( 'base64url' );
const wp = `cd ${ DIR } && sudo -u www-data wp`;
const site = `--url=${ URL_MS } --allow-root`;
const plugins = `${ DIR }/wp-content/plugins`;
const linkIn = companion ? `ln -sfn /var/www/wp/wp-content/plugins/${ companion } ${ plugins }/${ companion }; ${ wp } plugin activate ${ companion } ${ site } 2>&1 | grep -v Deprecated;` : '';
const linkOut = companion ? `${ wp } plugin deactivate ${ companion } ${ site } >/dev/null 2>&1; unlink ${ plugins }/${ companion };` : '';

// A literal PNG, as tests/compat/five-minutes.mjs writes it.
function png( w, h, r, g, b ) {
	const raw = Buffer.alloc( ( w * 3 + 1 ) * h );
	for ( let y = 0; y < h; y++ ) {
		const row = y * ( w * 3 + 1 );
		for ( let x = 0; x < w; x++ ) {
			raw[ row + 1 + x * 3 ] = r;
			raw[ row + 2 + x * 3 ] = g;
			raw[ row + 3 + x * 3 ] = b;
		}
	}
	const chunk = ( type, data ) => {
		const len = Buffer.alloc( 4 );
		len.writeUInt32BE( data.length );
		const body = Buffer.concat( [ Buffer.from( type, 'latin1' ), data ] );
		const crc = Buffer.alloc( 4 );
		crc.writeUInt32BE( zlib.crc32( body ) );
		return Buffer.concat( [ len, body, crc ] );
	};
	const ihdr = Buffer.alloc( 13 );
	ihdr.writeUInt32BE( w, 0 );
	ihdr.writeUInt32BE( h, 4 );
	ihdr[ 8 ] = 8;
	ihdr[ 9 ] = 2;
	return Buffer.concat( [ Buffer.from( [ 0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a ] ), chunk( 'IHDR', ihdr ), chunk( 'IDAT', zlib.deflateSync( raw ) ), chunk( 'IEND', Buffer.alloc( 0 ) ) ] );
}

// The user, the companion, the option snapshot -- the runner's prep, one ssh.
const snapshot = `echo "SNAP $( ${ wp } option get vergeml_ai --format=json ${ site } 2>/tmp/vgml-snap-err | base64 -w0 )"; echo "SNAPERR $( base64 -w0 < /tmp/vgml-snap-err )"; rm -f /tmp/vgml-snap-err;`;
const made = ssh( `${ wp } user delete ${ user } --network --yes --allow-root >/dev/null 2>&1; ${ wp } user create ${ user } ${ user }@invalid.test --role=administrator --user_pass='${ pass }' --allow-root 2>&1 | grep -v Deprecated; ${ wp } user set-role ${ user } administrator ${ site } 2>&1 | grep -v Deprecated; ${ linkIn } ${ snapshot }` );
if ( 0 !== made.status || ! /Success/.test( made.stdout ) ) {
	if ( linkOut ) {
		ssh( linkOut );
	}
	console.error( `could not make an administrator on ${ DIR }: ${ ( made.stdout + made.stderr ).trim().slice( -200 ) }` );
	process.exit( 1 );
}
const b64 = ( name ) => ( made.stdout.match( new RegExp( `^${ name } (\\S*)$`, 'm' ) ) || [ , '' ] )[ 1 ];
const decode = ( s ) => Buffer.from( s, 'base64' ).toString( 'utf8' );
const snap = b64( 'SNAP' );
const snapErr = decode( b64( 'SNAPERR' ) ).replace( /^.*Deprecated.*$/mg, '' ).trim();
const cleanup = () => ssh( `${ linkOut } ${ wp } user delete ${ user } --network --yes --allow-root >/dev/null 2>&1` );
if ( ! snap && ! /Does it exist\?/.test( snapErr ) ) {
	cleanup();
	console.error( `could not read vergeml_ai on ${ DIR } (nothing written): ${ snapErr.slice( -160 ) || 'no output' }` );
	process.exit( 1 );
}
let saved = {};
if ( snap ) {
	try {
		saved = JSON.parse( decode( snap ) );
	} catch {
		cleanup();
		console.error( `vergeml_ai on ${ DIR } did not read as JSON (nothing written)` );
		process.exit( 1 );
	}
}
const write = ( b ) => `cd ${ DIR } && echo ${ b } | base64 -d | sudo -u www-data wp option update vergeml_ai --format=json ${ site }`;
const restore = snap ? `${ write( snap ) } >/dev/null 2>&1;` : `${ wp } option delete vergeml_ai ${ site } >/dev/null 2>&1;`;
const on = ssh( `${ write( Buffer.from( JSON.stringify( { ...saved, mock: 1 } ) ).toString( 'base64' ) ) } 2>&1 | grep -v Deprecated; ${ wp } option get vergeml_ai --format=json ${ site } 2>/dev/null` );
if ( ! /"mock":1/.test( on.stdout ) ) {
	ssh( `${ restore } ${ linkOut } ${ wp } user delete ${ user } --network --yes --allow-root >/dev/null 2>&1` );
	console.error( `could not switch mock on in ${ DIR }: ${ on.stdout.trim().slice( -160 ) }` );
	process.exit( 1 );
}

console.log( `\n  ${ spec }.mjs on ${ URL_MS } beside ${ companion || 'nothing' }\n` );

// From here every exit goes through the finally: the box is prepared now.
let browser = null;
let page = null;
let nonce = '';
async function rest( method, route, body, headers = {} ) {
	const [ p, q ] = route.split( '?' );
	const url = `${ URL_MS }/?rest_route=${ encodeURI( p ) }${ q ? `&${ q }` : '' }`;
	const opts = { headers: { 'X-WP-Nonce': nonce, ...headers } };
	if ( body !== undefined ) {
		opts.data = body;
	}
	const res = 'GET' === method ? await page.request.get( url, opts ) : await page.request.fetch( url, { method, ...opts } );
	let json = null;
	try { json = JSON.parse( await res.text() ); } catch { /* not json */ }
	return { status: res.status(), json };
}
const folder = ( action, extra ) => rest( 'POST', '/vergeml/v1/folder', { taxonomy: 'media_category', action, ...extra } );

let fileId = 0;
const scratch = [];
let exit = 1;
try {
	browser = await chromium.launch();
	page = await ( await browser.newContext() ).newPage();
	page.setDefaultTimeout( 60000 );
	await page.goto( `${ URL_MS }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', pass );
	await page.click( '#wp-submit' );
	await page.waitForTimeout( 2000 );
	await page.goto( `${ URL_MS }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
	if ( page.url().includes( 'wp-login.php' ) ) {
		throw new Error( `the network did not sign ${ user } in` );
	}
	await page.waitForSelector( '.vgml-tree' );
	nonce = await page.evaluate( () => ( window.wpApiSettings && window.wpApiSettings.nonce ) || '' );

	const tree = ( await rest( 'GET', '/vergeml/v1/tree?taxonomy=media_category' ) ).json;
	for ( const n of tree.nodes || [] ) {
		if ( /^Drag (Scratch|Target)/.test( n.name ) ) {
			await folder( 'delete', { id: n.id } ); // a run that died half way
		}
	}
	for ( const name of [ 'Drag Scratch 1', 'Drag Scratch 2' ] ) {
		const r = await folder( 'create', { name } );
		if ( ! r.json || ! r.json.id ) {
			throw new Error( `"${ name }" answered ${ r.status }` );
		}
		scratch.push( Number( r.json.id ) );
	}
	const up = await rest( 'POST', '/wp/v2/media', png( 64, 40, 92, 118, 92 ), { 'Content-Type': 'image/png', 'Content-Disposition': 'attachment; filename="drag-beside.png"' } );
	fileId = Number( up.json && up.json.id );
	if ( ! fileId ) {
		throw new Error( `the upload answered ${ up.status }` );
	}
	console.log( `  file ${ fileId }, scratch folders ${ scratch.join( ', ' ) }` );

	const run = spawnSync( process.execPath, [ path.join( ROOT, 'tests', 'tree', `${ spec }.mjs` ), URL_MS, user, pass ], { cwd: ROOT, encoding: 'utf8', stdio: 'inherit' } );
	exit = run.status ?? 1;

	/*
	 *  One press drag.mjs never makes: on the checkbox label, left of the box.
	 *  Beside FileBird that label is FileBird's handle, so the drag is
	 *  FileBird's and our folder must neither light nor file (story 4.5: it
	 *  lit and filed nothing). Alone, our own row instance takes the press
	 *  and the folder does both. Either way a drag must be under way while
	 *  the pointer is over the folder -- a press that starts nothing is also
	 *  "not lit, not filed" -- so the drag's helper is read there and it has
	 *  to be the companion's (beside one) or ours (alone). drag.mjs leaves
	 *  the file unfiled; it is unfiled here again in case it did not.
	 */
	const unfiled = ( terms ) => ! terms.includes( scratch[ 0 ] );
	const termsOf = async () => ( ( ( await rest( 'GET', `/wp/v2/media/${ fileId }?_fields=media_category` ) ).json || {} ).media_category ) || [];
	const label = 'drag' !== spec ? null : await ( async () => {
		if ( ! unfiled( await termsOf() ) ) {
			await rest( 'POST', '/vergeml/v1/assign', { taxonomy: 'media_category', attachments: [ fileId ], add: [], mode: 'move' } );
		}
		await page.goto( `${ URL_MS }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
		await page.waitForSelector( `#post-${ fileId } .check-column label` );
		await page.waitForTimeout( 800 );
		const from = await page.$( `#post-${ fileId } .check-column` );
		const to = await page.$( `.vgml-node[data-id="${ scratch[ 0 ] }"] .vgml-row` );
		if ( ! from || ! to ) {
			return { error: from ? 'no scratch folder row' : 'no checkbox cell' };
		}
		const a = await from.boundingBox();
		const b = await to.boundingBox();
		const pressed = await page.evaluate( ( [ x, y ] ) => { const el = document.elementFromPoint( x, y ); return el ? el.tagName.toLowerCase() + ( el.className && 'string' === typeof el.className ? '.' + el.className.trim().split( /\s+/ ).join( '.' ) : '' ) : 'nothing'; }, [ a.x + 4, a.y + a.height / 2 ] );
		await page.mouse.move( a.x + 4, a.y + a.height / 2 );
		await page.mouse.down();
		await page.mouse.move( a.x + 64, a.y + a.height / 2, { steps: 6 } );
		await page.mouse.move( b.x + b.width / 2, b.y + b.height / 2, { steps: 12 } );
		const over = await page.evaluate( ( sel ) => {
			const $ = window.jQuery;
			const cur = $ && $.ui && $.ui.ddmanager && $.ui.ddmanager.current;
			return {
				lit: document.querySelector( sel ).classList.contains( 'is-drop' ),
				helper: cur && cur.helper ? cur.helper[ 0 ].className : 'none',
			};
		}, `.vgml-node[data-id="${ scratch[ 0 ] }"] .vgml-row` );
		await page.mouse.up();
		let filed = false;
		for ( let t = 0; t < 8 && ! filed; t++ ) {
			await page.waitForTimeout( 500 );
			filed = ! unfiled( await termsOf() );
		}
		if ( filed ) {
			await rest( 'POST', '/vergeml/v1/assign', { taxonomy: 'media_category', attachments: [ fileId ], add: [], mode: 'move' } );
		}
		return { pressed, lit: over.lit, helper: over.helper, filed };
	} )();
	if ( label ) {
		const want = ! companion;
		const ours = /\bvgml-drag-helper\b/.test( label.helper || '' );
		const underWay = 'none' !== label.helper && ours === want;
		const ok = ! label.error && underWay && label.lit === want && label.filed === want;
		console.log( `  ${ ok ? 'ok  ' : 'FAIL' } a drag from the checkbox label ${ companion ? `beside ${ companion } is ${ companion }'s: our folder stays dark and files nothing` : 'alone is ours: the folder lights and files' }  -- ${ label.error || `pressed on ${ label.pressed }; over the folder the drag's helper was ${ label.helper }; lit ${ label.lit }, filed ${ label.filed }` }` );
		if ( ! ok ) {
			exit = 1;
		}
	}
} catch ( e ) {
	console.error( `  stopped: ${ e && e.message ? e.message : e }` );
} finally {
	// The REST sweep may fail with the browser (a crash, a lost sign-in); the ssh line below runs regardless.
	try {
		if ( fileId ) {
			await rest( 'DELETE', `/wp/v2/media/${ fileId }?force=true` );
		}
		const tree = ( await rest( 'GET', '/vergeml/v1/tree?taxonomy=media_category' ) ).json;
		for ( const n of ( tree && tree.nodes ) || [] ) {
			if ( /^Drag (Scratch|Target)/.test( n.name ) ) {
				await folder( 'delete', { id: n.id } );
			}
		}
	} catch ( e ) {
		console.error( `  the sweep of the file and folders failed: ${ e && e.message ? e.message : e }` );
		exit = 1;
	}
	if ( browser ) {
		await browser.close().catch( () => null );
	}
	const back = ssh( `${ restore } ${ linkOut } ${ wp } user delete ${ user } --network --yes --allow-root >/dev/null 2>&1; echo "BACK $( ${ wp } option get vergeml_ai --format=json ${ site } 2>/dev/null | base64 -w0 )"; ls ${ plugins }` );
	const after = ( back.stdout.match( /^BACK (\S*)$/m ) || [ , '' ] )[ 1 ];
	const same = after === snap;
	const listing = back.stdout.replace( /^BACK.*$/m, '' ).trim().split( /\s+/ );
	const linked = !! companion && listing.includes( companion );
	console.log( `  ${ same ? 'ok  ' : 'FAIL' } the site's vergeml_ai option is as it was  -- ${ same ? ( snap ? `restored: ${ decode( snap ).slice( 0, 80 ) }` : 'absent, as before' ) : `found ${ after ? decode( after ).slice( 0, 80 ) : 'absent' }` }` );
	console.log( `  ${ linked ? 'FAIL' : 'ok  ' } plugins on ${ DIR }: ${ listing.join( ' ' ) }${ linked ? ` -- ${ companion } is still linked in` : '' }` );
	if ( ! same || linked ) {
		exit = 1;
	}
}
process.exit( exit );
