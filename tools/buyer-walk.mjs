/*
 *  The buyer walk, service side: story 2.1 of plans/suite-readiness.md.
 *
 *      node tools/buyer-walk.mjs buy    [--email you+tag@example.com] [--plan single] [--code WALK0920] [--dry]
 *      node tools/buyer-walk.mjs final
 *
 *  One headed Chromium with a profile that persists between the two stages
 *  (the walk's session cookie lives there and nowhere else). The script
 *  drives every page; Nathan's fingers touch only the card fields, Pay, and
 *  the password at registration.
 *
 *  buy:   /cart → /checkout; prints the form's own figure and WAITS for the
 *         payment to land on /order (Nathan presses Pay after his go); reads
 *         the key off the order page (masked in the shot, written to
 *         walk-key.txt in the scratch dir for the site-side tool, deleted by
 *         the restore); opens registration for the walk email and WAITS until
 *         the account page shows the Pro download; captures the zip.
 *  final: /account credits and invoices shots, our PDF for the first invoice,
 *         then "cancel at period end" through the page, the reply recorded.
 *
 *  Every line printed is evidence; nothing printed is the key or a secret.
 */
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const SITE = process.env.VGML_SITE || 'https://vergelabsmedia.com';
const OUT = path.join( ROOT, 'docs', 'superpowers', 'mocks', 'shots' );
// Outside the repo: the key file, the zip, the PDF and the browser profile.
const SCRATCH = process.env.WALK_DIR || path.join( os.tmpdir(), 'vgml-walk' );
const STAMP = new Date().toISOString().slice( 0, 10 );
const KEY_FILE = path.join( SCRATCH, 'walk-key.txt' );
const STATE_FILE = path.join( SCRATCH, 'walk-state.json' );

const args = process.argv.slice( 2 );
const stage = args[ 0 ];
const opt = ( name, fallback ) => {
	const i = args.indexOf( `--${ name }` );
	return -1 === i ? fallback : args[ i + 1 ];
};
const EMAIL = opt( 'email', 'nathan+buyer-0920@vergelabs.nl' );
const PLAN = opt( 'plan', 'single' );
// A discount code, applied by the cart from its query string (Nathan's call
// of 2026-09-20: the walk pays the code's price, not the list price).
const CODE = ( opt( 'code', '' ) || '' ).trim().toUpperCase();

mkdirSync( SCRATCH, { recursive: true } );
mkdirSync( OUT, { recursive: true } );

const state = existsSync( STATE_FILE ) ? JSON.parse( readFileSync( STATE_FILE, 'utf8' ) ) : {};
const save = () => writeFileSync( STATE_FILE, JSON.stringify( state, null, 2 ) );
const say = ( line ) => console.log( `${ new Date().toISOString().slice( 11, 19 ) }  ${ line }` );
const shot = async ( page, name ) => {
	const file = path.join( OUT, `${ STAMP }-buyer-walk-${ name }.png` );
	await page.screenshot( { path: file, fullPage: true } );
	say( `shot ${ path.basename( file ) }` );
	return file;
};
const mask = ( key ) => `${ key.slice( 0, 9 ) }…${ key.slice( -4 ) }`;

/** Wait until `test(page)` is true on any open tab, polling; the human is doing something meanwhile. */
async function waitForHuman( context, label, test, minutes ) {
	say( `WAITING (${ label }) — up to ${ minutes } min` );
	const until = Date.now() + minutes * 60_000;
	while ( Date.now() < until ) {
		for ( const p of context.pages() ) {
			try {
				if ( await test( p ) ) return p;
			} catch {}
		}
		await new Promise( ( r ) => setTimeout( r, 2000 ) );
	}
	throw new Error( `gave up waiting: ${ label }` );
}

const context = await chromium.launchPersistentContext( path.join( SCRATCH, 'profile' ), {
	headless: false,
	viewport: { width: 1440, height: 900 },
	acceptDownloads: true,
} );
const page = context.pages()[ 0 ] || await context.newPage();

try {
	if ( 'buy' === stage ) {
		await page.goto( `${ SITE }/cart?plan=${ PLAN }${ CODE ? '&code=' + encodeURIComponent( CODE ) : '' }`, { waitUntil: 'networkidle' } );
		await page.fill( '#cart-email', EMAIL );
		await page.waitForTimeout( 1500 );
		if ( CODE ) {
			const cart = ( await page.locator( 'main, body' ).first().innerText() ).replace( /\s+/g, ' ' );
			const preview = cart.match( new RegExp( `[^.]{0,80}${ CODE }[^.]{0,120}` ) );
			say( `cart with code ${ CODE }: ${ preview ? preview[ 0 ].trim() : '(the code is not mentioned on the cart)' }` );
		}
		await shot( page, '01-cart' );
		await Promise.all( [ page.waitForURL( /\/checkout/ ), page.click( 'text=Continue to checkout' ) ] );

		// The form's own figure: the Pay button reads "Pay €39.00" once the
		// intent is prepared and the element mounted.
		const pay = page.locator( 'button.vg-btn-wide' );
		await pay.filter( { hasText: /^Pay / } ).waitFor( { timeout: 60_000 } );
		const figure = ( await pay.innerText() ).trim();
		const summary = ( await page.locator( 'dl.vg-dl' ).innerText() ).replace( /\s+/g, ' ' ).trim();
		await shot( page, '02-checkout' );
		say( `FIGURE: ${ figure }` );
		say( `summary: ${ summary }` );
		say( `receipt and licence go to ${ EMAIL }` );
		if ( args.includes( '--dry' ) ) {
			say( 'dry run: stopping before Pay (an abandoned checkout; nothing charged)' );
			await context.close();
			process.exit( 0 );
		}

		// The stop point. Nothing moves until the human presses Pay on the
		// figure above; the script only watches for the order page.
		const orderPage = await waitForHuman( context, 'card typed and Pay pressed by Nathan', async ( p ) => p.url().includes( '/order' ), 30 );
		const url = new URL( orderPage.url() );
		state.payment_intent = url.searchParams.get( 'payment_intent' );
		state.redirect_status = url.searchParams.get( 'redirect_status' );
		say( `order page: payment_intent ${ state.payment_intent }, redirect_status ${ state.redirect_status }` );

		// "fulfilled" means the webhook wrote the licence row; the key shows then.
		await orderPage.locator( '.vg-key' ).waitFor( { timeout: 90_000 } );
		const key = ( await orderPage.locator( '.vg-key' ).innerText() ).trim();
		const dl = ( await orderPage.locator( 'dl.vg-dl' ).innerText() ).replace( /\s+/g, ' ' ).trim();
		const lead = ( await orderPage.locator( 'p.vg-lead' ).innerText() ).trim();
		writeFileSync( KEY_FILE, key + '\n' );
		state.key_prefix = mask( key );
		state.email = EMAIL;
		save();
		say( `order: ${ lead }` );
		say( `order: ${ dl }` );
		say( `key on the page: ${ mask( key ) } (${ key.length } chars, written to walk-key.txt)` );
		await orderPage.evaluate( ( m ) => { document.querySelector( '.vg-key' ).textContent = m; }, mask( key ) );
		await shot( orderPage, '03-order' );

		// Registration is the customer's: the password is typed by Nathan, the
		// verification link pasted into this window. The script waits for the
		// account page to show the download.
		await orderPage.goto( `${ SITE }/account?mode=register`, { waitUntil: 'networkidle' } );
		const emailBox = orderPage.locator( 'input[type="email"]' ).first();
		if ( await emailBox.count() ) await emailBox.fill( EMAIL );
		say( `registration form opened for ${ EMAIL }; Nathan types the password and creates the account, then pastes the verification link into this window` );
		const acct = await waitForHuman( context, 'account registered, verified and showing the Pro download', async ( p ) =>
			p.url().includes( '/account' ) && ( await p.getByText( /Download Pro .* \(zip\)/ ).count() ) > 0, 30 );
		await acct.waitForTimeout( 1000 );
		await shot( acct, '04-account-overview' );

		const link = acct.getByText( /Download Pro .* \(zip\)/ ).first();
		const label = ( await link.innerText() ).trim();
		say( `account shows: ${ label }` );
		const [ download ] = await Promise.all( [ acct.waitForEvent( 'download', { timeout: 60_000 } ), link.click() ] );
		const zip = path.join( SCRATCH, 'walk-pro.zip' );
		await download.saveAs( zip );
		const bytes = readFileSync( zip );
		const sha = createHash( 'sha256' ).update( bytes ).digest( 'hex' );
		state.zip = zip;
		state.zip_sha256 = sha;
		state.zip_bytes = bytes.length;
		save();
		say( `download: ${ download.suggestedFilename() } → ${ bytes.length } bytes, sha256 ${ sha.slice( 0, 12 ) }…` );
		await shot( acct, '05-account-licence' );
	} else if ( 'final' === stage ) {
		await page.goto( `${ SITE }/account`, { waitUntil: 'networkidle' } );
		if ( ( await page.getByText( /Download Pro .* \(zip\)/ ).count() ) === 0 ) {
			throw new Error( 'the walk session is gone: sign in again in this window and re-run final' );
		}
		const text = ( await page.locator( 'main, body' ).first().innerText() ).replace( /\s+/g, ' ' );
		const credits = text.match( /CREDITS LEFT\s*([\d,.\-]+)/i );
		say( `account overview: ${ credits ? 'CREDITS LEFT ' + credits[ 1 ] : '(no credits tile found)' }` );
		await shot( page, '06-account-final-overview' );

		// Credit activity on the overview; invoices under Billing.
		const readPanel = async ( heading ) => {
			const h = page.getByRole( 'heading', { name: heading } ).first();
			if ( ! ( await h.count() ) ) return say( `${ heading }: heading not on the page` );
			await h.scrollIntoViewIfNeeded();
			const section = h.locator( 'xpath=ancestor::section[1]' );
			const body = ( await ( ( await section.count() ) ? section : h ).innerText() ).replace( /\s+/g, ' ' ).trim();
			say( `${ heading }: ${ body.slice( 0, 500 ) }` );
		};
		await readPanel( 'Credit activity' );
		await shot( page, '07-credit-activity' );
		await page.goto( `${ SITE }/account#billing`, { waitUntil: 'networkidle' } );
		await page.waitForTimeout( 1500 );
		await readPanel( 'Invoices' );
		await shot( page, '08-invoices' );

		// Our own PDF for the first invoice, fetched with the walk's session.
		const href = await page.locator( 'a[href*="/api/invoice?"]' ).first().getAttribute( 'href', { timeout: 5000 } ).catch( () => null );
		if ( href ) {
			const res = await context.request.get( new URL( href, SITE ).toString() );
			const pdf = path.join( SCRATCH, 'walk-invoice.pdf' );
			writeFileSync( pdf, Buffer.from( await res.body() ) );
			state.invoice_href = href.replace( /licence=[^&]+/, 'licence=…' );
			say( `our PDF: ${ res.status() } ${ res.headers()[ 'content-type' ] } ${ statSync( pdf ).size } bytes → ${ pdf }` );
		} else {
			say( 'our PDF: no /api/invoice link on the page' );
		}

		// Cancel at period end -- the customer's path, /api/licence/plan through
		// the page. The page's own reply is the evidence.
		await page.goto( `${ SITE }/account#overview`, { waitUntil: 'networkidle' } );
		await page.waitForTimeout( 1500 );
		const cancelBtn = page.locator( 'button.vg-quiet', { hasText: 'Cancel subscription' } ).first();
		if ( await cancelBtn.count() ) {
			await cancelBtn.scrollIntoViewIfNeeded();
			await cancelBtn.click();
			await page.waitForTimeout( 800 );
			const question = ( await page.locator( '.vg-confirm' ).innerText().catch( () => '' ) ).replace( /\s+/g, ' ' ).trim();
			say( `confirm step: ${ question }` );
			await page.locator( 'button', { hasText: 'Yes, cancel it' } ).first().click();
			await page.waitForTimeout( 3000 );
			const after = ( await page.locator( 'main, body' ).first().innerText() ).replace( /\s+/g, ' ' );
			const sentence = after.match( /(Cancelled\.[^.]*\.|This subscription is set to end on [^.]*\.[^.]*\.)/g ) || [];
			say( `cancel at period end: ${ sentence.join( ' | ' ) || '(no cancellation sentence found)' }` );
			await shot( page, '09-cancel-at-period-end' );
		} else {
			say( 'cancel: no "Cancel subscription" button on the page' );
		}
		save();
	} else {
		console.error( 'stage: buy | final' );
		process.exitCode = 2;
	}
} catch ( e ) {
	say( `FAIL ${ e.message }` );
	process.exitCode = 1;
} finally {
	await context.close();
}
