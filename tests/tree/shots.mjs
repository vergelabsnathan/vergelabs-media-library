/*
 *  Screenshots of the tree, and the assertions that go with them.
 *
 *      node tests/tree/shots.mjs http://185.229.224.239 admin VgmlTest7pass
 *
 *  Nathan asked for these explicitly as the gate for T1, and he was right to:
 *  every visual bug in this project so far -- the clipped hero, the popup that
 *  rendered nothing -- was found by eye and missed by assertions. So this both
 *  looks and checks: it fails on a console error or a missing tree, and it
 *  leaves images behind for the things no assertion catches.
 *
 *  Grid and list, light and dark, LTR and RTL, and each of the four skins.
 */

import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';

const BASE = process.argv[ 2 ] ?? 'http://185.229.224.239';
const USER = process.argv[ 3 ] ?? 'admin';
const PASS = process.argv[ 4 ] ?? 'VgmlTest7pass';

const OUT = join( process.cwd(), 'tests', 'tree', 'shots' );
mkdirSync( OUT, { recursive: true } );

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? '  -- ' + detail : '' }` );
};

const browser = await chromium.launch();

async function session( { dark, rtl } ) {
	const ctx = await browser.newContext( {
		viewport: { width: 1500, height: 950 },
		colorScheme: dark ? 'dark' : 'light',
	} );
	const page = await ctx.newPage();

	const errors = [];
	// The stack matters: "reading 'serialize'" names nothing on its own.
	page.on( 'pageerror', ( e ) => {
		const where = String( e.stack || '' ).split( /?
/ ).slice( 1, 4 ).join( ' <- ' );
		errors.push( String( e.message ) + ( where ? '  @ ' + where : '' ) );
	} );
	page.on( 'console', ( m ) => {
		if ( m.type() !== 'error' ) return;
		// The generic message; the specific URL is captured by the response hook.
		if ( /Failed to load resource/.test( m.text() ) ) return;
		errors.push( m.text() );
	} );
	/*
	 *  A bare "404 Not Found" in the console names nothing, so record the URL.
	 *
	 *  Uploads are excluded: the 20,000-file fixture was seeded straight into the
	 *  database and has no image files behind it, so every thumbnail 404s. That is
	 *  the fixture's doing, not the plugin's, and letting it fail the run would
	 *  train us to ignore this assertion -- which is how a real error gets through.
	 */
	const noise = ( url ) => /\/wp-content\/uploads\//.test( url );
	page.on( 'requestfailed', ( r ) => { if ( ! noise( r.url() ) ) errors.push( 'failed: ' + r.url() ); } );
	page.on( 'response', ( r ) => {
		if ( r.status() >= 400 && ! noise( r.url() ) ) errors.push( r.status() + ' ' + r.url() );
	} );
	page.on( 'console', ( m ) => {
		if ( m.type() === 'error' && /Failed to load resource/.test( m.text() ) ) return; // covered above, with the URL
	} );

	await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', USER );
	await page.fill( '#user_pass', PASS );
	await page.click( '#wp-submit' );
	await page.waitForLoadState( 'domcontentloaded' );

	/*
	 *  RTL is switched by actually changing the admin language, not by forcing a
	 *  dir attribute.
	 *
	 *  Forcing it looked like it worked and was measuring nothing: WordPress ships
	 *  separate RTL stylesheets for the whole admin, and with the LTR ones still
	 *  loaded the page reported 129,000px of horizontal overflow that had nothing
	 *  to do with this plugin. A test that fails for the wrong reason is worse
	 *  than no test, because the next person fixes the wrong thing.
	 */
	await page.goto( `${ BASE }/wp-admin/profile.php`, { waitUntil: 'domcontentloaded' } );
	const picker = await page.$( '#locale' );
	if ( picker ) {
		// English (United States) is the empty value in WordPress's locale select,
		// not 'en_US'. Selecting 'en_US' silently failed, so every LTR run was
		// screenshotting a Hebrew admin and the labels said otherwise.
		await page.selectOption( '#locale', rtl ? 'he_IL' : '' ).catch( () => {} );
		await page.click( '#submit' ).catch( () => {} );
		await page.waitForLoadState( 'domcontentloaded' );
	}

	return { page, ctx, errors };
}

for ( const mode of [ 'list', 'grid' ] ) {
	for ( const dark of [ false, true ] ) {
		for ( const rtl of [ false, true ] ) {

			const tag = `${ mode }-${ dark ? 'dark' : 'light' }-${ rtl ? 'rtl' : 'ltr' }`;
			const { page, ctx, errors } = await session( { dark, rtl } );

			/*
			 *  Errors are only counted from here on. Signing in and switching the
			 *  admin language are setup, not the thing under test, and profile.php
			 *  raises one of core's own -- which was being blamed on the tree.
			 */
			errors.length = 0;

			await page.goto( `${ BASE }/wp-admin/upload.php?mode=${ mode }`, { waitUntil: 'domcontentloaded' } );
			await page.waitForTimeout( 6000 ); // 20,000 attachments; the grid is not instant

			/*
			 *  Skin is per-user and persists, so the skin section at the end of a
			 *  previous run left every later screenshot in high contrast. Reset to
			 *  the default before shooting, or these images document whatever the
			 *  last test happened to click.
			 */
			const nativeSwatch = await page.$( '.vgml-skin[data-skin="native"]' );
			if ( nativeSwatch ) {
				await nativeSwatch.click();
				await page.waitForTimeout( 300 );
			}

			const tree = await page.$( '.vgml-tree' );
			check( `${ tag }: the tree is on the page`, !! tree );

			if ( tree ) {
				const nodes = await page.$$eval( '.vgml-node', ( n ) => n.length );
				check( `${ tag }: it drew folders`, nodes > 2, `${ nodes } rows` );

				// The panel must not be squeezed to nothing by the table beside it.
				const box = await tree.boundingBox();
				check( `${ tag }: the panel has real width`, box && box.width >= 150, box ? `${ Math.round( box.width ) }px` : 'no box' );

				// And it must not push the page into a horizontal scroll.
				const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
				check( `${ tag }: no horizontal overflow`, overflow <= 2, `${ overflow }px` );
			}

			check( `${ tag }: no console errors`, errors.length === 0, errors.slice( 0, 2 ).join( ' | ' ) );

			await page.screenshot( { path: join( OUT, `${ tag }.png` ), fullPage: false } );
			await ctx.close();
		}
	}
}

// The four skins, in list view where the tree is easiest to read.
{
	const { page, ctx } = await session( { dark: false, rtl: false } );
	await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 3000 );

	for ( const skin of [ 'native', 'classic', 'minimal', 'contrast' ] ) {
		const btn = await page.$( `.vgml-skin[data-skin="${ skin }"]` );
		if ( ! btn ) {
			check( `skin ${ skin }: the swatch exists`, false );
			continue;
		}
		await btn.click();
		await page.waitForTimeout( 400 );
		const applied = await page.$eval( '.vgml-tree', ( t ) => t.getAttribute( 'data-skin' ) );
		check( `skin ${ skin }: applied`, applied === skin, applied );

		const tree = await page.$( '.vgml-tree' );
		await tree.screenshot( { path: join( OUT, `skin-${ skin }.png` ) } );
	}

	await ctx.close();
}

await browser.close();

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed` );
console.log( `screenshots in ${ OUT }\n` );
process.exit( bad ? 1 : 0 );
