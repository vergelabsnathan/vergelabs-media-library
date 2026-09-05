/*
 *  Two screenshots a site with a licence cannot give: the Licence screen
 *  with no key (the demo-mode switch present) and the dashboard as a fresh
 *  install sees it. Against Playground, which has no key, mounted on this
 *  checkout by tools/play.mjs.
 *
 *      node tools/play.mjs                      # in another terminal
 *      node tools/shots-nokey.mjs [outdir]      # writes shot-nokey-*.png
 *
 *  Credentials are Playground's defaults (admin / password); nothing here
 *  writes to the site.
 */
import { chromium } from 'playwright';
import path from 'node:path';

const BASE = process.env.UI_BASE ?? 'http://127.0.0.1:8899';
const OUT = process.argv[2] ?? 'test-results';

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );

await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
await page.fill( '#user_login', process.env.UI_USER ?? 'admin' );
await page.fill( '#user_pass', process.env.UI_PASS ?? 'password' );
await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( '#wp-submit' ) ] );

for ( const [ name, slug ] of [ [ 'licence', 'media-licence' ], [ 'dashboard', 'vergelabs-media' ], [ 'library-settings', 'media-library' ] ] ) {
	await page.goto( `${ BASE }/wp-admin/admin.php?page=${ slug }`, { waitUntil: 'domcontentloaded' } );
	await page.waitForTimeout( 1200 );
	const file = path.join( OUT, `shot-nokey-${ name }.png` );
	await page.screenshot( { path: file, fullPage: true } );
	const demo = await page.locator( '#vgml-demo-mode' ).count();
	const stats = await page.locator( '#vgml-stats-opt' ).count();
	console.log( `${ name }: ${ file }  demo-switch=${ demo }  counts-switch=${ stats }` );
}

await browser.close();
