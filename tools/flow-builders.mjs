/*
 *  Is the folder tree inside a page builder's media modal?
 *
 *      VGML_USER=admin VGML_PASS=... node tools/flow-builders.mjs <label> <editor-url> [<click-selector>]
 *
 *  One builder per run: the caller activates the builder, hands over the URL
 *  of its editor for a probe page, and this logs in, opens that editor, waits
 *  for it to settle, dismisses whatever welcome overlay it throws up, and
 *  answers three questions -- is our tree script on the page, does a media
 *  modal open, and is the tree standing in its Media Library tab.
 *
 *  The modal is opened through wp.media itself unless a click selector is
 *  given: every builder opens WordPress's own modal, and the tree attaches to
 *  wp.media.view.Modal's open() whoever calls it, so a synthetic open on the
 *  builder's page proves the same thing as the builder's own button -- that
 *  the script is loaded and wired on this screen.
 */
import { chromium } from 'playwright';

const [ , , LABEL = 'builder', URL_ = '', CLICK = '' ] = process.argv;
const BASE = ( process.env.VGML_BASE || 'http://46.225.66.194' ).replace( /\/$/, '' );
const USER = process.env.VGML_USER || '';
const PASS = process.env.VGML_PASS || '';
if ( ! USER || ! PASS || ! URL_ ) { console.error( 'usage: VGML_USER= VGML_PASS= node tools/flow-builders.mjs <label> <url> [click-selector]' ); process.exit( 1 ); }

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1500, height: 950 } } );
page.setDefaultTimeout( 8000 );
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( ( e && e.message ) || String( e ) ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER ); await page.fill( '#user_pass', PASS );
await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#wp-submit' ) ] );

const target = URL_.startsWith( 'http' ) ? URL_ : `${ BASE }${ URL_ }`;
await page.goto( target, { waitUntil: 'domcontentloaded', timeout: 45000 } ).catch( () => {} );
await page.waitForTimeout( 9000 );

// Welcome tours and pattern pickers sit on top of everything.
for ( const sel of [ '.components-modal__header button[aria-label="Close"]', '.dialog-close-button', '.elementor-templates-modal__header__close', '[aria-label="Close"]' ] ) {
	const el = page.locator( sel ).first();
	if ( await el.count() ) { await el.click( { timeout: 1500 } ).catch( () => {} ); }
}
await page.keyboard.press( 'Escape' ).catch( () => {} );
await page.waitForTimeout( 800 );

// The builder may live in the top window or in a frame; look everywhere.
const frames = page.frames();
let scriptWhere = 'absent';
let host = page.mainFrame();
for ( const f of frames ) {
	const has = await f.evaluate( () => !! document.querySelector( 'script[src*="vergeml-tree"]' ) || typeof window.vergemlTree !== 'undefined' ).catch( () => false );
	if ( has ) { scriptWhere = f === page.mainFrame() ? 'top window' : ( 'frame ' + ( f.name() || f.url().slice( 0, 60 ) ) ); host = f; break; }
}

let modal = { opened: false, treeInModal: false, folderRows: 0 };
if ( CLICK ) {
	await page.locator( CLICK ).first().click( { timeout: 8000 } ).catch( () => {} );
} else {
	await host.evaluate( () => { try { const fr = window.wp && window.wp.media ? window.wp.media( { title: 'probe', multiple: false } ) : null; if ( fr ) { fr.open(); } } catch ( e ) {} } ).catch( () => {} );
}
await page.waitForTimeout( 2500 );
await host.locator( '.media-modal .media-menu-item:has-text("Media Library"), .media-modal #menu-item-browse' ).first().click( { timeout: 4000 } ).catch( () => {} );
await page.waitForTimeout( 3500 );
modal = await host.evaluate( () => ( {
	opened: !! document.querySelector( '.media-modal' ),
	treeInModal: !! document.querySelector( '.vgml-in-modal, .media-modal .vgml-tree' ),
	folderRows: document.querySelectorAll( '.vgml-in-modal .vgml-row, .media-modal .vgml-row' ).length,
} ) ).catch( () => modal );

if ( process.env.VGML_SHOT ) { await page.screenshot( { path: process.env.VGML_SHOT } ).catch( () => {} ); }

const ok = scriptWhere !== 'absent' && modal.treeInModal;
console.log( `  ${ LABEL.padEnd( 16 ) } script: ${ scriptWhere.padEnd( 14 ) } modal: ${ modal.opened ? 'open ' : 'none ' } tree: ${ modal.treeInModal ? 'yes' : 'no ' } rows: ${ String( modal.folderRows ).padStart( 3 ) }  ${ ok ? 'OK' : 'FAIL' }${ errors.length ? '  errors: ' + errors[ 0 ].slice( 0, 80 ) : '' }` );
await browser.close();
process.exit( ok ? 0 : 1 );
