/*
 *  Every Folders screen, on both libraries, on one page to read.
 *
 *      UI_USER=… UI_PASS=… node tools/box-folders-sheet.mjs
 *
 *  Nathan's pass over the Folders screens (S21): the five rail steps shot on
 *  the tech library (1,000 pictures, no shop) and on the real shop (33
 *  pictures, 27 products, the rail turned round), plus the Folders screen as
 *  it opens on each. The sheet is written beside the shots and names what to
 *  look at on each one, so the pass is scrolling one page rather than
 *  clicking two admin screens.
 *
 *  It presses rail steps and nothing else: no confirm, no fill, no answer. A
 *  screen that needs a state the site is not in is shot as it is and said so.
 *
 *  Lives in tools/, which is export-ignored.
 */
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const USER = process.env.UI_USER || 'vgmls21';
const PASS = process.env.UI_PASS;
const OUT = 'docs/superpowers/mocks/shots';
const DAY = new Date().toISOString().slice( 0, 10 );
const SHEET = `docs/superpowers/mocks/${ DAY }-folders-sheet.html`;

const SITES = [
	{ key: 'tech', name: 'The tech library', base: 'http://46.225.66.194', note: '1,000 pictures, no shop: the rail is Describe · Tree · Fill · Alt text · Rename.' },
	{ key: 'shop', name: 'The real shop', base: 'http://shop.ms2.46.225.66.194.nip.io', note: '33 pictures, 27 products, 9 categories: the rail turns round -- Tree · Fill · Describe.' },
];

const browser = await chromium.launch();
const shots = [];

for ( const site of SITES ) {

	const page = await ( await browser.newContext( { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 } ) ).newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );

	await page.goto( `${ site.base }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 400 );
	await page.fill( '#user_login', USER );
	await page.fill( '#user_pass', PASS );
	// Jetpack's login sum: the fixture answers it or every browser login is a 401 (memory: hetzner-box-fixtures).
	const puzzle = page.locator( 'input[name="jetpack_protect_num"]' );
	if ( await puzzle.count() ) {
		const asked = await page.locator( 'label[for="jetpack_protect_answer"]' ).innerText();
		const sum = /(\d+)\D+(\d+)/.exec( asked.replace( / /g, ' ' ) );
		if ( sum ) {
			await puzzle.fill( String( Number( sum[ 1 ] ) + Number( sum[ 2 ] ) ) );
		}
	}
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ).catch( () => null ), page.click( '#wp-submit' ) ] );

	await page.goto( `${ site.base }/wp-admin/admin.php?page=media-librarian`, { waitUntil: 'domcontentloaded' } );
	await page.waitForSelector( '#vgml-folders .g-card', { state: 'attached', timeout: 30000 } );
	await page.waitForTimeout( 1200 );

	const landed = await page.getAttribute( '#vgml-folders', 'data-step' );
	const order = await page.$$eval( '.g-rail .g-step', ( els ) => els.map( ( e ) => e.getAttribute( 'data-step' ) ) );
	console.log( `  ${ site.key }: lands on ${ landed }, rail ${ order.join( ' · ' ) }` );

	const take = async ( step, label ) => {
		const file = `${ OUT }/${ DAY }-sheet-${ site.key }-${ step }.png`;
		await page.screenshot( { path: file, fullPage: false } );
		shots.push( { site: site.key, siteName: site.name, siteNote: site.note, step, label, file: path.basename( file ), landed: step === landed } );
		console.log( `  shot ${ file }` );
	};

	await take( landed, `As it opens: the ${ landed } step, which is where a returning owner lands.` );

	for ( const step of order ) {
		if ( step === landed ) {
			continue;
		}
		const button = page.locator( `.g-rail .g-step[data-step="${ step }"]` );
		if ( ! ( await button.count() ) ) {
			continue;
		}
		await button.click();
		await page.waitForTimeout( 900 );
		const now = await page.getAttribute( '#vgml-folders', 'data-step' );
		await take( step, now === step ? `The ${ step } step.` : `The rail refused ${ step } and stayed on ${ now }.` );
	}

	if ( errors.length ) {
		console.log( `  page errors on ${ site.key }: ${ errors.join( ' | ' ) }` );
	}
	await page.close();
}

await browser.close();

const esc = ( s ) => String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
const bySite = SITES.map( ( s ) => ( { site: s, rows: shots.filter( ( x ) => x.site === s.key ) } ) );

const html = `<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Folders screens — ${ DAY }</title>
<style>
 :root { --ink: #10131a; --quiet: #5b6472; --line: #e4e7ec; --mark: #2f57ff; --bg: #fff; }
 body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.6 -apple-system, "Segoe UI", system-ui, sans-serif; }
 main { max-width: 1180px; margin: 0 auto; padding: 48px 16px 96px; }
 h1 { font-size: 30px; letter-spacing: -0.02em; margin: 0 0 6px; }
 h2 { font-size: 20px; margin: 56px 0 4px; display: flex; align-items: center; gap: 10px; }
 h2::before { content: ""; width: 9px; height: 9px; background: var(--mark); display: inline-block; }
 p.lede { color: var(--quiet); margin: 0 0 8px; }
 figure { margin: 28px 0 0; }
 figcaption { font-size: 13px; color: var(--quiet); padding: 10px 2px; border-bottom: 1px solid var(--line); }
 figcaption b { color: var(--ink); font-weight: 600; }
 img { width: 100%; display: block; border: 1px solid var(--line); border-radius: 6px; margin-top: 12px; }
</style></head><body><main>
<h1>The Folders screens</h1>
<p class="lede">${ esc( DAY ) } · every rail step on both libraries, shot at 1440×900. Nothing was confirmed, filled or answered to make these.</p>
${ bySite.map( ( g ) => `<h2>${ esc( g.site.name ) }</h2>
<p class="lede">${ esc( g.site.note ) }</p>
${ g.rows.map( ( r ) => `<figure><figcaption><b>${ esc( r.step ) }</b> — ${ esc( r.label ) }</figcaption><img src="shots/${ esc( r.file ) }" alt="${ esc( r.site + ' ' + r.step ) }"></figure>` ).join( '\n' ) }` ).join( '\n' ) }
</main></body></html>
`;

fs.writeFileSync( SHEET, html );
console.log( `\n  ${ shots.length } shots\n  ${ SHEET }` );
