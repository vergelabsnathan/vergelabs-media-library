/*
 *  The five-minute script: what a person does in their first five minutes.
 *
 *      node tests/compat/five-minutes.mjs http://127.0.0.1:8931
 *
 *  Run once per cell by tools/matrix.mjs, the same steps every time, so a
 *  cell's ✗ names a step and not a mood. In order: make a folder; upload
 *  three images; drag one into the folder; filter the grid by that folder;
 *  select the other two and move them in one drag; deactivate and delete the
 *  plugin. A step that does not reach its assertion is a FAIL with the step
 *  named. A JS error from this plugin and a fatal in the debug log are steps
 *  of their own. The last line printed is RESULT {json}, which the runner reads.
 *
 *  Environment:
 *    VGML_USER / VGML_PASS       sign in through wp-login.php (the box). Without
 *                                them the site is a Playground that logged in
 *                                by itself.
 *    VGML_MATRIX_PROBE=1         tests/compat/matrix-probe.php is on the site
 *                                (a Playground): the debug log and the state
 *                                after uninstall are read through it.
 *    VGML_MATRIX_NO_UNINSTALL=1  skip the last step and remove what the run
 *                                made instead. The box: deactivating any
 *                                plugin there fatals in core's FTP class.
 *    VGML_MATRIX_LOCALE=ar       assert the site speaks that locale; for ar,
 *                                also that the RTL sheets are the ones loaded.
 *    VGML_MATRIX_MUTATE=1        the mutation check: the folder count asserted
 *                                after "make a folder" is wrong by one, so
 *                                every cell must go ✗ at that step.
 */
import zlib from 'node:zlib';
import { chromium } from 'playwright';

const BASE = ( process.argv[ 2 ] ?? 'http://127.0.0.1:9400' ).replace( /\/$/, '' );
const SLUG = 'vergelabs-media-library';
const USER = process.env.VGML_USER || '';
const PASS = process.env.VGML_PASS || '';
const PROBE = '1' === process.env.VGML_MATRIX_PROBE;
const NO_UNINSTALL = '1' === process.env.VGML_MATRIX_NO_UNINSTALL;
const LOCALE = process.env.VGML_MATRIX_LOCALE || '';
const MUTATE = '1' === process.env.VGML_MATRIX_MUTATE;
const FOLDER = 'Matrix run';

const steps = [];
const record = ( name, ok, detail = '' ) => {
	steps.push( { name, ok, detail: String( detail ) } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? `  -- ${ detail }` : '' }` );
};

// One step: its own try, so a throw is a FAIL with the step's name, not the end of the run.
async function step( name, fn ) {
	try {
		const r = await fn();
		record( name, r.ok, r.detail );
		return r.ok;
	} catch ( e ) {
		record( name, false, String( e && e.message ? e.message : e ).split( '\n' )[ 0 ].slice( 0, 160 ) );
		return false;
	}
}

/*
 *  A literal PNG, written rather than drawn: GD is not in the Playground
 *  build, and a real upload of a real image is the point of the step.
 */
function png( w, h, r, g, b ) {
	const raw = Buffer.alloc( ( w * 3 + 1 ) * h );
	for ( let y = 0; y < h; y++ ) {
		const row = y * ( w * 3 + 1 );
		raw[ row ] = 0;
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
	ihdr[ 8 ] = 8; // bit depth
	ihdr[ 9 ] = 2; // truecolour
	return Buffer.concat( [
		Buffer.from( [ 0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a ] ),
		chunk( 'IHDR', ihdr ),
		chunk( 'IDAT', zlib.deflateSync( raw ) ),
		chunk( 'IEND', Buffer.alloc( 0 ) ),
	] );
}

const browser = await chromium.launch();
const page = await ( await browser.newContext( { viewport: { width: 1500, height: 950 } } ) ).newPage();
page.setDefaultTimeout( 120000 );
page.on( 'dialog', ( d ) => d.accept() );

// Errors attributed by source, as tests/compat/smoke.js does: a companion
// plugin shouting about its own licence is not this plugin's failure.
const jsErrors = [];
const OURS = /vergelabs-media-library|vergeml-|eml-/i;
const noteError = ( text, url ) => jsErrors.push( { text: String( text ).slice( 0, 600 ), url: url || '', screen: ( () => { try { return new URL( page.url() ).pathname + new URL( page.url() ).search; } catch { return '?'; } } )(), ours: OURS.test( url || '' ) || OURS.test( String( text ) ) } );
page.on( 'pageerror', ( e ) => noteError( e && e.stack ? e.stack.split( '\n' ).slice( 0, 2 ).join( ' ' ) : e, ( e && e.stack ) || '' ) );
page.on( 'console', ( m ) => { if ( 'error' === m.type() ) noteError( m.text(), ( m.location() || {} ).url ); } );

// A fatal on any screen visited is the cell's fatal, whether or not a debug log exists.
// Counted once the site is up: a Playground answers 502 while it boots.
const fatals = [];
let live = false;
page.on( 'response', async ( res ) => {
	if ( live && res.request().isNavigationRequest() && res.status() >= 500 ) {
		fatals.push( `HTTP ${ res.status() } on ${ new URL( res.url() ).pathname }` );
	}
} );
async function screenSaysFatal() {
	const text = await page.evaluate( () => document.body ? document.body.innerText.slice( 0, 4000 ) : '' );
	if ( /There has been a critical error|Fatal error/i.test( text ) ) {
		fatals.push( `"${ text.match( /There has been a critical error|Fatal error[^\n]{0,80}/i )[ 0 ] }" on ${ new URL( page.url() ).pathname }` );
	}
}

let nonce = '';
async function rest( method, route, body, headers = {} ) {
	// ?rest_route= is the form that needs no pretty permalinks; the route's own
	// query has to follow it with & or WordPress reads it as part of the route.
	const [ routePath, routeQuery ] = route.split( '?' );
	const url = `${ BASE }/?rest_route=${ encodeURI( routePath ) }${ routeQuery ? `&${ routeQuery }` : '' }`;
	const opts = { headers: { 'X-WP-Nonce': nonce, ...headers } };
	if ( body !== undefined ) {
		opts.data = body;
	}
	const res = 'GET' === method ? await page.request.get( url, opts ) : await page.request.fetch( url, { method, ...opts } );
	const text = await res.text();
	let json = null;
	try { json = JSON.parse( text ); } catch { /* not json */ }
	return { status: res.status(), json, text };
}

const tree = async () => ( await rest( 'GET', '/vergeml/v1/tree?taxonomy=media_category' ) ).json;
/*
 *  Read until the write shows, up to six seconds. Playground serves requests
 *  from several workers over one SQLite file and a read straight after a
 *  write has come back stale (WP 6.5 / PHP 8.5, 2026-09-11: the drag's file
 *  read as in no folder, and the folder counted three a step later). A file
 *  that never shows the folder still fails the step.
 */
const termsOf = async ( id, want = 0 ) => {
	let terms = [];
	for ( let i = 0; i < 12; i++ ) {
		const r = await rest( 'GET', `/wp/v2/media/${ id }?_fields=media_category` );
		terms = ( r.json && r.json.media_category ) || [];
		if ( ! want || terms.includes( want ) ) {
			break;
		}
		await page.waitForTimeout( 500 );
	}
	return terms;
};
const probe = async () => {
	const res = await page.request.get( `${ BASE }/?vgml_matrix=state` );
	return await res.json();
};

let seen = { lang: '', dir: '', rtlSheets: 0, sheets: 0 };
async function openLibrary( query = 'mode=list' ) {
	await page.goto( `${ BASE }/wp-admin/upload.php?${ query }`, { waitUntil: 'domcontentloaded' } );
	await screenSaysFatal();
	// A companion's first-visit redirect (Premio Folders sends the first admin
	// request to its own settings page) is followed once, as a person would.
	if ( ! page.url().includes( 'upload.php' ) ) {
		await page.goto( `${ BASE }/wp-admin/upload.php?${ query }`, { waitUntil: 'domcontentloaded' } );
		await screenSaysFatal();
	}
	await page.waitForSelector( '.vgml-tree', { timeout: 60000 } );
	await page.waitForTimeout( 800 );
	seen = await page.evaluate( () => ( {
		lang: document.documentElement.lang || '',
		dir: document.dir || document.documentElement.dir || '',
		sheets: document.querySelectorAll( 'link[rel="stylesheet"][href*="vergeml"], link[rel="stylesheet"][href*="eml-"]' ).length,
		rtlSheets: document.querySelectorAll( 'link[rel="stylesheet"][href*="-rtl.css"]' ).length,
	} ) );
	nonce = await page.evaluate( () => ( window.wpApiSettings && window.wpApiSettings.nonce ) || ( window.wp && window.wp.apiFetch && window.wp.apiFetch.nonceMiddleware && window.wp.apiFetch.nonceMiddleware.nonce ) || '' );
}

/*
 *  A drag the way a hand does it (tests/tree/drag.mjs): jQuery UI wants a move
 *  past its threshold before it starts, and a move over the target to register
 *  the hover. The intermediate steps are the point, not decoration.
 */
async function drag( fromSel, toSel ) {
	const from = await page.$( fromSel );
	const to = await page.$( toSel );
	if ( ! from || ! to ) {
		return { error: from ? 'no folder row to drop on' : 'no file row to drag' };
	}
	const a = await from.boundingBox();
	const b = await to.boundingBox();
	if ( ! a || ! b ) {
		return { error: 'a row is not on screen' };
	}
	await page.mouse.move( a.x + 40, a.y + a.height / 2 );
	await page.mouse.down();
	await page.mouse.move( a.x + 60, a.y + a.height / 2, { steps: 4 } );
	await page.mouse.move( b.x + b.width / 2, b.y + b.height / 2, { steps: 12 } );
	const hovering = await to.evaluate( ( el ) => el.classList.contains( 'is-drop' ) );
	await page.mouse.up();
	await page.waitForTimeout( 1500 );
	return { hovering };
}

let folderId = 0;
let folderSlug = '';
let site = null; // what the probe says the site is: version, PHP, locale
let foldersBefore = 0;
const ids = [];

console.log( `\n  five minutes on ${ BASE }${ LOCALE ? ` (${ LOCALE })` : '' }${ MUTATE ? '  MUTATED: the folder count asserted is wrong by one' : '' }\n` );

try {
	await step( 'the site answers and the plugin is on', async () => {
		if ( USER ) {
			await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
			await page.fill( '#user_login', USER );
			await page.fill( '#user_pass', PASS );
			await page.click( '#wp-submit' );
			await page.waitForTimeout( 2000 );
		}
		/*
		 *  A Playground takes minutes to boot; until then the port refuses or
		 *  answers 502. Wait for a signed-in admin screen, not for the absence
		 *  of an error. The first request is /wp-admin/ with no query string:
		 *  Playground's auto-login signs in the first request it sees and
		 *  loses the sign-in when that request carries a query (measured
		 *  2026-09-11 -- upload.php?mode=list first lands on wp-login.php
		 *  every time; plugins.php first, as tools/uninstall-walk.mjs does,
		 *  signs in every time).
		 */
		const deadline = Date.now() + 600000;
		let last = '';
		let answered = 0; // when the site first answered at all
		while ( Date.now() < deadline ) {
			try {
				const res = await page.goto( `${ BASE }/wp-admin/`, { waitUntil: 'domcontentloaded' } );
				last = `${ res ? res.status() : '-' } ${ page.url() }`;
				if ( res && res.ok() ) {
					answered = answered || Date.now();
					// Up, but still the login form a minute and a half later: not booting, not signed in.
					if ( page.url().includes( 'wp-login.php' ) && Date.now() - answered > 90000 ) {
						return { ok: false, detail: `the site answers but did not sign us in: ${ page.url() }` };
					}
				}
				if ( res && res.ok() && await page.$( '#wpadminbar' ) ) {
					await screenSaysFatal();
					let lib = await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
					await screenSaysFatal();
					if ( ! page.url().includes( 'upload.php' ) ) {
						// Sent elsewhere by a companion's welcome screen: once more.
						lib = await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
						await screenSaysFatal();
					}
					if ( lib && lib.ok() && await page.$( '.vgml-tree' ) ) {
						live = true;
						await openLibrary();
						return { ok: true, detail: `library screen, lang=${ seen.lang || '?' }` };
					}
					// Signed in, screen loaded, no tree: the plugin is not running here, or
					// something on the screen stopped it. Say which, as far as the screen tells.
					const mine = jsErrors.filter( ( e ) => e.ours );
					const shape = await page.evaluate( () => ( {
						body: document.body.className.split( ' ' ).filter( ( c ) => ! /^(wp-|branch-|version-|admin-|locale-|no-|js|svg|auto-fold|sticky|folded|is-)/.test( c ) ).slice( 0, 6 ).join( ' ' ),
						list: !! document.querySelector( '#the-list' ),
						ours: document.querySelectorAll( 'link[href*="vergeml"], script[src*="vergeml"]' ).length,
					} ) );
					const row = await page.goto( `${ BASE }/wp-admin/plugins.php`, { waitUntil: 'domcontentloaded' } );
					const active = row && row.ok() && await page.$( `#deactivate-${ SLUG }` );
					const why = `${ mine.length } JS error(s) of ours${ mine[ 0 ] ? ` (${ mine[ 0 ].text.replace( /\s+/g, ' ' ).slice( 0, 90 ) })` : '' }; ${ shape.ours } of our assets on the page; list table ${ shape.list ? 'present' : 'absent' }; body classes ${ shape.body || 'none of note' }`;
					return { ok: false, detail: active ? `the plugin is active but the library screen has no tree -- ${ why }` : `the plugin is not active (${ fatals[ 0 ] || 'no fatal seen' })` };
				}
			} catch { /* still booting */ }
			await page.waitForTimeout( 5000 );
		}
		return { ok: false, detail: `nothing usable within ten minutes; last answer ${ last }` };
	} ) || ( () => { throw new Error( 'stop' ); } )();

	if ( PROBE ) {
		try {
			const s = await probe();
			site = { wp: s.wp, php: s.php, locale: s.locale, rtl: s.rtl };
			console.log( `  site: WordPress ${ s.wp } · PHP ${ s.php } · ${ s.locale }${ s.rtl ? ' · rtl' : '' }` );
		} catch { /* no probe answer: the versions stay as asked */ }
	}

	await step( 'make a folder', async () => {
		let t = await tree();
		if ( ! t || ! Array.isArray( t.nodes ) ) {
			return { ok: false, detail: `the tree endpoint answered ${ JSON.stringify( t ).slice( 0, 120 ) }` };
		}
		// Idempotent on a site that keeps state (the box): a run that died half way must not poison this one.
		for ( const n of t.nodes ) {
			if ( n.name === FOLDER ) {
				await rest( 'POST', '/vergeml/v1/folder', { taxonomy: 'media_category', action: 'delete', id: n.id } );
			}
		}
		t = await tree();
		foldersBefore = t.nodes.length;
		const made = await rest( 'POST', '/vergeml/v1/folder', { taxonomy: 'media_category', action: 'create', name: FOLDER } );
		folderId = made.json && made.json.id ? Number( made.json.id ) : 0;
		const after = await tree();
		const node = after.nodes.find( ( n ) => n.id === folderId );
		folderSlug = node ? node.slug : '';
		const expected = foldersBefore + 1 + ( MUTATE ? 1 : 0 );
		const ok = folderId > 0 && !! node && after.nodes.length === expected;
		return { ok, detail: ok ? `"${ FOLDER }" is folder ${ folderId }, ${ after.nodes.length } folders` : `create answered ${ made.status }; ${ after.nodes.length } folders, expected ${ expected }` };
	} );

	await step( 'upload three images', async () => {
		const colours = [ [ 176, 104, 62 ], [ 92, 118, 92 ], [ 31, 56, 100 ] ];
		const failed = [];
		for ( let i = 0; i < 3; i++ ) {
			// A Buffer goes as the raw body; the two headers make WordPress read it as a file.
			const r = await rest( 'POST', '/wp/v2/media', png( 64, 40, ...colours[ i ] ), {
				'Content-Type': 'image/png',
				'Content-Disposition': `attachment; filename="matrix-${ i + 1 }.png"`,
			} );
			if ( 201 === r.status && r.json && r.json.id ) {
				ids.push( Number( r.json.id ) );
			} else {
				failed.push( `${ r.status } ${ ( r.text || '' ).slice( 0, 80 ) }` );
			}
		}
		return { ok: 3 === ids.length, detail: 3 === ids.length ? `attachments ${ ids.join( ', ' ) }` : `${ ids.length } of 3 uploaded; ${ failed[ 0 ] || '' }` };
	} );

	await step( 'drag one into the folder', async () => {
		await openLibrary();
		const r = await drag( `#post-${ ids[ 0 ] } .column-title, #post-${ ids[ 0 ] }`, `.vgml-node[data-id="${ folderId }"] .vgml-row` );
		if ( r.error ) {
			return { ok: false, detail: r.error };
		}
		const terms = await termsOf( ids[ 0 ], folderId );
		const ok = terms.includes( folderId );
		return { ok, detail: `${ r.hovering ? 'the folder lit up' : 'the folder did not light up' }; file ${ ids[ 0 ] } is in [${ terms.join( ',' ) }]` };
	} );

	await step( 'filter the grid by the folder', async () => {
		await openLibrary( 'mode=grid' );
		await page.waitForSelector( '.attachments-browser', { timeout: 60000 } );
		const node = await page.$( `.vgml-node[data-id="${ folderId }"] .vgml-row` );
		if ( ! node ) {
			return { ok: false, detail: 'the folder is not in the tree beside the grid' };
		}
		await node.click();
		let shown = [];
		for ( let i = 0; i < 30; i++ ) {
			await page.waitForTimeout( 500 );
			shown = await page.$$eval( '.attachments-browser .attachments .attachment', ( els ) => els.map( ( e ) => Number( e.getAttribute( 'data-id' ) ) ) );
			if ( 1 === shown.length && shown[ 0 ] === ids[ 0 ] ) {
				break;
			}
		}
		const ok = 1 === shown.length && shown[ 0 ] === ids[ 0 ];
		return { ok, detail: ok ? `the grid shows file ${ ids[ 0 ] } alone` : `the grid shows ${ shown.length } files [${ shown.slice( 0, 5 ).join( ',' ) }], expected [${ ids[ 0 ] }]` };
	} );

	await step( 'select two files and move them in one drag', async () => {
		await openLibrary();
		// The tree remembers the folder the grid step chose and the list comes
		// back filtered to it; a person clicks All files to see everything.
		const all = await page.$( '.vgml-node[data-id="0"] .vgml-row' );
		if ( all ) {
			await all.click();
		}
		for ( const id of ids.slice( 1 ) ) {
			await page.waitForSelector( `#post-${ id }`, { timeout: 30000 } );
		}
		for ( const id of ids.slice( 1 ) ) {
			await page.check( `#cb-select-${ id }` );
		}
		const r = await drag( `#post-${ ids[ 1 ] } .column-title, #post-${ ids[ 1 ] }`, `.vgml-node[data-id="${ folderId }"] .vgml-row` );
		if ( r.error ) {
			return { ok: false, detail: r.error };
		}
		const inFolder = [];
		for ( const id of ids.slice( 1 ) ) {
			if ( ( await termsOf( id, folderId ) ).includes( folderId ) ) {
				inFolder.push( id );
			}
		}
		const t = await tree();
		const node = ( t.nodes || [] ).find( ( n ) => n.id === folderId );
		const count = node ? Number( node.count ) : -1;
		const ok = 2 === inFolder.length && 3 === count;
		return { ok, detail: `${ inFolder.length } of 2 moved; the folder counts ${ count }` };
	} );

	if ( LOCALE ) {
		await step( `the site speaks ${ LOCALE }`, async () => {
			const lang = seen.lang.replace( '-', '_' );
			const okLang = lang === LOCALE || lang.split( '_' )[ 0 ] === LOCALE.split( '_' )[ 0 ];
			if ( 'ar' !== LOCALE ) {
				return { ok: okLang, detail: `html lang=${ seen.lang || '?' }` };
			}
			const ok = okLang && 'rtl' === seen.dir && seen.rtlSheets > 0;
			return { ok, detail: `html lang=${ seen.lang || '?' } dir=${ seen.dir || '?' }; ${ seen.rtlSheets } RTL sheet(s) of ${ seen.sheets } of ours` };
		} );
	}

	if ( NO_UNINSTALL ) {
		await step( 'remove what the run made (uninstall not run on this site)', async () => {
			let gone = 0;
			let refused = '';
			for ( const id of ids ) {
				// POST with the override header: the box's nginx answers 405 to DELETE.
				const r = await rest( 'POST', `/wp/v2/media/${ id }?force=true`, undefined, { 'X-HTTP-Method-Override': 'DELETE' } );
				if ( 200 === r.status ) {
					gone++;
				} else if ( ! refused ) {
					refused = `; DELETE answered ${ r.status } ${ ( r.text || '' ).replace( /\s+/g, ' ' ).slice( 0, 100 ) }`;
				}
			}
			await rest( 'POST', '/vergeml/v1/folder', { taxonomy: 'media_category', action: 'delete', id: folderId } );
			const t = await tree();
			const ok = gone === ids.length && t.nodes.length === foldersBefore;
			return { ok, detail: `${ gone } of ${ ids.length } files removed; ${ t.nodes.length } folders (was ${ foldersBefore })${ refused }` };
		} );
	} else {
		await step( 'deactivate and delete the plugin', async () => {
			// The route tools/uninstall-walk.mjs takes: deactivate, then follow
			// the Delete link to its confirmation form and submit it -- that is
			// what runs uninstall.php and then removes the files.
			await page.goto( `${ BASE }/wp-admin/plugins.php`, { waitUntil: 'domcontentloaded' } );
			await page.click( `#deactivate-${ SLUG }` );
			await page.waitForSelector( `#delete-${ SLUG }`, { timeout: 60000 } );
			const href = await page.getAttribute( `#delete-${ SLUG }`, 'href' );
			await page.goto( new URL( href, `${ BASE }/wp-admin/` ).toString(), { waitUntil: 'domcontentloaded' } );
			await page.click( 'input[type="submit"][name="submit"]' );
			await page.waitForLoadState( 'domcontentloaded' );
			await page.waitForTimeout( 1500 );
			await screenSaysFatal();
			const row = await page.$( `tr[data-slug="${ SLUG }"]` );
			if ( ! PROBE ) {
				return { ok: ! row, detail: row ? 'the plugin row is still on the Plugins screen' : 'the plugin row is gone' };
			}
			const s = await probe();
			const term = s.terms.find( ( t ) => t.id === folderId );
			const filed = ids.filter( ( id ) => ( s.links[ id ] || [] ).includes( folderId ) ).length;
			const kept = ids.filter( ( id ) => s.attachments.includes( id ) ).length;
			const ok = ! row && ! s.plugin_active && !! term && 3 === filed && 3 === kept;
			return { ok, detail: `${ s.plugin_active ? 'still active' : 'gone' }; folder ${ term ? 'survives' : 'LOST' }; ${ filed } of 3 files keep it; ${ kept } of 3 attachments remain` };
		} );
	}

	await step( 'no JS error from this plugin', async () => {
		const mine = jsErrors.filter( ( e ) => e.ours );
		const theirs = jsErrors.filter( ( e ) => ! e.ours );
		// The file and line, not the origin: "vergeml-tree.js:2071" says where, "http://127.0.0.1:8930/wp-content/plugins/..." says nothing.
		const where = ( e ) => e.text.replace( /\s+/g, ' ' ).replace( /https?:\/\/[^\s)]*\/([^\s\/)]+\.js(?::\d+)*)(?::\d+)?/g, '$1' ).replace( /\?ver=[^\s:)]+/g, '' ).slice( 0, 140 );
		const distinct = [ ...new Map( mine.map( ( e ) => [ `${ where( e ) }@${ e.screen }`, e ] ) ).values() ];
		return { ok: 0 === mine.length, detail: mine.length ? distinct.slice( 0, 2 ).map( ( e ) => `${ where( e ) } on ${ e.screen }` ).join( ' | ' ) : `${ theirs.length } error(s) from other code, not counted` };
	} );

	await step( 'no fatal, and nothing of ours in the debug log', async () => {
		let lines = [];
		if ( PROBE ) {
			lines = ( await probe() ).log || [];
		}
		// A WordPress database error spans lines: the header, then the query. The
		// header is the line before the one that names us, so it comes along.
		const withHeader = ( i ) => {
			let j = i;
			while ( j > 0 && ! /^\[\d/.test( lines[ j ] ) ) {
				j--;
			}
			return ( j === i ? lines[ i ] : `${ lines[ j ] } ... ${ lines[ i ] }` ).replace( /\s+/g, ' ' );
		};
		const fatalLines = lines.map( ( l, i ) => /PHP Fatal|Uncaught/i.test( l ) ? withHeader( i ) : '' ).filter( Boolean );
		const ours = lines.map( ( l, i ) => /vergeml|vergelabs-media-library/i.test( l ) && ! /PHP Fatal|Uncaught/i.test( l ) ? withHeader( i ) : '' ).filter( Boolean );
		const ok = 0 === fatals.length && 0 === fatalLines.length && 0 === ours.length;
		const first = fatals[ 0 ] || fatalLines[ 0 ] || ours[ 0 ] || '';
		return { ok, detail: ok ? ( PROBE ? `${ lines.length } log line(s), none ours` : 'no fatal on any screen visited (no debug log on this site)' ) : first.slice( 0, 260 ) };
	} );
} catch ( e ) {
	if ( 'stop' !== e.message ) {
		record( 'the run completed', false, String( e.message ).slice( 0, 160 ) );
	}
} finally {
	await browser.close();
}

const failed = steps.filter( ( s ) => ! s.ok );
console.log( `\n  ${ steps.length - failed.length }/${ steps.length } steps\n` );
console.log( 'RESULT ' + JSON.stringify( {
	ok: 0 === failed.length,
	site,
	steps,
	failed: failed.length ? `${ failed[ 0 ].name }: ${ failed[ 0 ].detail }` : '',
} ) );
process.exit( failed.length ? 1 : 0 );
