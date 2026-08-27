/*
 *  Gate 6 without a box.
 *
 *  Plugin Check is the wordpress.org submission gate, and the way it is meant
 *  to be run here is against a clean archive on the test VPS. When there is no
 *  VPS to hand this drives the same plugin through its admin screen in
 *  Playground -- the route docs/wordpress-org-submission.md describes, done by
 *  a browser rather than by hand.
 *
 *  Not through WP-CLI: `wp plugin check` crashes php-wasm outright (RuntimeError:
 *  unreachable) part way through the run, so the CLI route is not available in
 *  Playground at all.
 *
 *  Usage -- extract a clean archive first, then boot Playground against it:
 *
 *      git archive HEAD --prefix=vergelabs-media-library/ -o /tmp/clean.tar
 *      tar xf /tmp/clean.tar -C <somewhere>
 *      npx @wp-playground/cli server --port 8907 --php=8.3 --wp=latest \
 *        --mount-dir "<somewhere>\vergelabs-media-library" \
 *          /wordpress/wp-content/plugins/vergelabs-media-library \
 *        --blueprint=tools/plugin-check-blueprint.json
 *      node tools/plugin-check.mjs http://127.0.0.1:8907
 *
 *  Every category is ticked, not the "Plugin Repo" default: the default skips
 *  Security, Performance and Accessibility, which is most of what a reviewer
 *  actually reads.
 *
 *  Exits non-zero when anything is reported.
 */
import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://127.0.0.1:8907';
const SLUG = process.argv[ 3 ] ?? 'vergelabs-media-library';

const browser = await chromium.launch();
const page = await ( await browser.newContext( { viewport: { width: 1500, height: 1200 } } ) ).newPage();
page.setDefaultTimeout( 240000 );

/*
 *  Waits for the site rather than assuming it.
 *
 *  Playground takes minutes to boot -- it downloads WordPress and installs
 *  Plugin Check -- and until it does, the port either refuses the connection
 *  or answers 502. Both are "not ready", and a wait loop that treats anything
 *  other than 502 as ready reads a dead port as a live one and fails with
 *  ERR_CONNECTION_REFUSED a second later. So the waiting lives here, and it
 *  waits for the page, not for the absence of an error.
 */
const deadline = Date.now() + 600000;
let reached = false;

while ( Date.now() < deadline ) {
	try {
		const res = await page.goto( `${ BASE }/wp-admin/tools.php?page=plugin-check`, { waitUntil: 'domcontentloaded' } );
		if ( res && res.ok() ) {
			reached = true;
			break;
		}
	} catch ( e ) {
		// refused, reset, still booting -- all the same answer
	}
	await page.waitForTimeout( 5000 );
}

if ( ! reached ) {
	console.error( `Nothing answered on ${ BASE } within ten minutes. Is Playground booted with tools/plugin-check-blueprint.json?` );
	await browser.close();
	process.exit( 2 );
}

if ( ! page.url().includes( 'page=plugin-check' ) ) {
	console.error( `Plugin Check screen not reachable -- landed on ${ page.url() }` );
	await browser.close();
	process.exit( 2 );
}

/*
 *  The form is small and its markup has moved between releases, so nothing
 *  here is pinned to an id: the plugin is chosen by the option whose value
 *  contains the slug, and every category checkbox is ticked by walking them.
 */
const chosen = await page.evaluate( ( slug ) => {
	const select = document.querySelector( 'select[name="plugin"], #plugin-check__plugins, select' );
	if ( ! select ) {
		return null;
	}
	const option = Array.from( select.options ).find( ( o ) => o.value.includes( slug ) );
	if ( ! option ) {
		return { error: 'not listed', options: Array.from( select.options ).map( ( o ) => o.value ) };
	}
	select.value = option.value;
	select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	return { value: option.value };
}, SLUG );

if ( ! chosen || chosen.error ) {
	console.error( 'Could not pick the plugin:', JSON.stringify( chosen ) );
	await browser.close();
	process.exit( 2 );
}

console.log( `checking ${ chosen.value }` );

const categories = await page.evaluate( () => {
	const boxes = Array.from( document.querySelectorAll( 'input[type="checkbox"]' ) )
		.filter( ( b ) => /categor/i.test( b.name ) || /categor/i.test( b.id ) );
	boxes.forEach( ( b ) => {
		if ( ! b.checked ) {
			b.click();
		}
	} );
	return boxes.map( ( b ) => b.value );
} );

console.log( `categories: ${ categories.join( ', ' ) || '(none found -- defaults stand)' }` );

await page.evaluate( () => {
	const button = document.querySelector( '#plugin-check__submit, input[type="submit"], button[type="submit"]' );
	if ( button ) {
		button.click();
	}
} );

/*
 *  The run is a long series of AJAX calls, each one a whole WordPress boot in
 *  php-wasm. Waited for by watching the results area stop growing rather than
 *  by a fixed timeout, because how long it takes depends on the machine.
 */
const readResults = () =>
	page.evaluate( () => {
		const area = document.querySelector( '#plugin-check__results, .plugin-check__results' )
			|| document.querySelector( '#wpbody-content' );
		return area ? area.innerText : '';
	} );

let text = '';
let stable = 0;

for ( let i = 0; i < 240; i++ ) {
	await page.waitForTimeout( 2000 );
	const next = await readResults();
	if ( next === text && text.length > 0 ) {
		stable++;
	} else {
		stable = 0;
	}
	text = next;

	const done = await page.evaluate( () =>
		/complete|no errors found|checks complete/i.test( document.body.innerText )
	);

	if ( done && stable >= 3 ) {
		break;
	}
	if ( stable >= 15 ) {
		break;
	}
}

console.log( '\n----- Plugin Check output -----\n' );
console.log( text.trim() );
console.log( '\n----- end -----' );

const clean = /no errors found/i.test( text ) && ! /\bERROR\b|\bWARNING\b/i.test( text );

await page.screenshot( { path: 'tools/.plugin-check.png', fullPage: true } );

await browser.close();

process.exit( clean ? 0 : 1 );
