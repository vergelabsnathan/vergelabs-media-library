/*
 *  Open WPBakery's backend editor and see whether the folder tree is there.
 *
 *      VGML_USER=admin VGML_PASS=... node tools/flow-wpbakery.mjs [http://46.225.66.194]
 *
 *  The market leader's longest-running builder complaint: the tree works in
 *  the media library and is missing -- or intermittent -- inside the WPBakery
 *  modal. This opens a new page, switches to the backend editor, opens the
 *  media modal the way a person inserting an image does, and reports whether
 *  the tree came with it.
 */
import { chromium } from 'playwright';

const BASE = ( process.argv[ 2 ] || 'http://46.225.66.194' ).replace( /\/$/, '' );
const USER = process.env.VGML_USER || '';
const PASS = process.env.VGML_PASS || '';
if ( ! USER || ! PASS ) { console.error( 'set VGML_USER / VGML_PASS' ); process.exit( 1 ); }

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1500, height: 950 } } );
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( ( e && e.message ) || String( e ) ) );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', USER ); await page.fill( '#user_pass', PASS );
await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#wp-submit' ) ] );

await page.goto( `${ BASE }/wp-admin/post-new.php?post_type=page`, { waitUntil: 'domcontentloaded' } );
await page.waitForTimeout( 3000 );

// A fresh page greets you with the "Choose a pattern" overlay, which sits on
// top of everything and swallows every click until it is dismissed.
const patternClose = page.locator( '.components-modal__header button[aria-label="Close"], .components-modal__frame button[aria-label="Close"]' ).first();
if ( await patternClose.count() ) {
	await patternClose.click( { timeout: 3000 } ).catch( () => {} );
	await page.waitForTimeout( 800 );
}
await page.keyboard.press( 'Escape' ).catch( () => {} );
await page.waitForTimeout( 500 );

const treeScript = await page.evaluate( () => !! document.querySelector( 'script[src*="vergeml-tree"]' ) || typeof window.vergemlTree !== 'undefined' );

/*
 *  A fresh page opens in Gutenberg with WPBakery's "switch to composer" prompt
 *  on top. Clicking it reloads the screen as the classic editor with the
 *  WPBakery backend canvas -- the screen the complaint is about.
 */
let vcState = 'not found';
const vcSwitch = page.locator( '.wpb_switch-to-composer' ).first();
if ( await page.locator( '.vc_navbar .vc_add-element-button, #vc_navbar' ).count() && ! await vcSwitch.count() ) {
	vcState = 'already open';
} else if ( await vcSwitch.count() ) {
	try {
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 20000 } ).catch( () => {} ),
			vcSwitch.click( { timeout: 5000 } ),
		] );
		await page.waitForTimeout( 4000 );
		vcState = ( await page.locator( '#vc_navbar, .vc_navbar' ).count() ) ? 'opened' : 'clicked, no navbar';
	} catch ( e ) { vcState = 'present, click failed'; }
}

/*
 *  The real road, exactly as a person inserts an image on this screen: pick a
 *  canvas, add a Single Image element, click its "Add image" param -- WPBakery
 *  opens wp.media on the Upload Files tab, where a folder tree rightly has
 *  nothing to filter -- then switch to the Media Library tab, where it must be.
 */
let modal = { opened: false };
try {
	const blank = page.getByText( 'Blank page canvas', { exact: false } ).first();
	if ( await blank.count() ) { await blank.click( { timeout: 5000 } ).catch( () => {} ); await page.waitForTimeout( 2500 ); }
	await page.locator( '#vc_add-new-element, .vc_add-element-button' ).first().click( { timeout: 8000 } );
	await page.waitForTimeout( 2000 );
	await page.locator( '[data-element="vc_single_image"]' ).first().click( { timeout: 8000 } );
	await page.waitForTimeout( 3000 );
	await page.locator( '.vc_ui-panel-window .gallery_widget_add_images' ).first().click( { timeout: 8000 } );
	await page.waitForTimeout( 3000 );
	await page.locator( '.media-modal .media-menu-item:has-text("Media Library"), .media-modal #menu-item-browse' ).first().click( { timeout: 8000 } );
	await page.waitForTimeout( 4000 );
	modal = await page.evaluate( () => ( {
		opened: !! document.querySelector( '.media-modal' ),
		treeInModal: !! document.querySelector( '.vgml-in-modal, .media-modal .vgml-tree' ),
		folderRows: document.querySelectorAll( '.vgml-in-modal .vgml-row, .media-modal .vgml-row' ).length,
	} ) );
} catch ( e ) {
	modal.error = String( e ).slice( 0, 160 );
}

console.log( `\n  page loads              vergemlTree present: ${ treeScript }` );
console.log( `  WPBakery editor         ${ vcState }` );
console.log( `  media modal             opened=${ modal.opened }  tree in modal=${ modal.treeInModal ?? '-' }  folder rows=${ modal.folderRows ?? '-' }` );
console.log( `  page errors             ${ errors.length ? errors.slice( 0, 3 ).join( ' | ' ).slice( 0, 200 ) : 'none' }\n` );
await browser.close();
process.exit( treeScript && modal.opened && modal.treeInModal ? 0 : 1 );
