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
 *      npx @wp-playground/cli@3.1.54 server --port 8907 --php=8.3 --wp=latest \
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
 *
 *  **It also has to prove the checks ran.** Until 2026-09-30 this read the
 *  screen's text and nothing else, and the screen said "No errors found" for
 *  4.1.0 while the wordpress.org review found 62 unescaped outputs. Under
 *  php-wasm every check built on PHPCS (late_escaping, i18n_usage, prefixing,
 *  enqueued_resources, direct_db, setting_sanitization, ...) answered its AJAX
 *  call with HTTP 400 -- "file_put_contents(): Exclusive locks are not
 *  supported for this stream" from PHPCS's Reporter.php -- and the screen
 *  simply left those checks out. The blueprint now patches that LOCK_EX away,
 *  and this script watches every per-check reply: a check that did not answer
 *  success is a failed run (exit 2), never a clean one.
 */
import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://127.0.0.1:8907';
const SLUG = process.argv[ 3 ] ?? 'vergelabs-media-library';

const browser = await chromium.launch();
const page = await ( await browser.newContext( { viewport: { width: 1500, height: 1200 } } ) ).newPage();
page.setDefaultTimeout( 240000 );

// One entry per check the screen ran: its name, and whether it answered success.
const checkReplies = [];
let expected = [];
page.on( 'response', async ( r ) => {
	const body = r.request().postData() || '';
	if ( r.url().includes( 'admin-ajax.php' ) && body.includes( 'plugin_check_get_checks_to_run' ) ) {
		try {
			expected = JSON.parse( await r.text() ).data.checks || [];
		} catch ( e ) { /* stays empty, and an empty list fails the run */ }
		return;
	}
	if ( ! r.url().includes( 'admin-ajax.php' ) || ! body.includes( 'plugin_check_run_checks' ) ) {
		return;
	}
	const name = ( body.match( /name="checks\[\]"\s+([a-z0-9_]+)/ ) || [] )[ 1 ] || '(unnamed)';
	let json = null;
	try {
		json = JSON.parse( await r.text() );
	} catch ( e ) { /* not JSON: a fatal page, counted as not run */ }
	checkReplies.push( { name, ok: !! ( json && json.success ), why: json && ! json.success ? JSON.stringify( json.data ).slice( 0, 300 ) : ( json ? '' : `HTTP ${ r.status() }, not JSON` ) } );
} );

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

// --experimental: the checks Plugin Check ships before they are on by default; the review team runs ahead of us.
if ( process.argv.includes( '--experimental' ) ) {
	const ticked = await page.evaluate( () => {
		const box = Array.from( document.querySelectorAll( 'input[type="checkbox"]' ) ).find( ( b ) => /experimental/i.test( b.name + ' ' + b.id ) );
		if ( box && ! box.checked ) {
			box.click();
		}
		return box ? box.name || box.id : null;
	} );
	if ( ! ticked ) {
		console.error( 'asked for --experimental, but the form has no experimental checkbox' );
		await browser.close();
		process.exit( 2 );
	}
	console.log( `experimental checks on (${ ticked })` );
}

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

	// Done is every asked-for check answered and the screen saying so -- not the word "complete" anywhere on the page.
	const done = expected.length > 0 && checkReplies.length >= expected.length
		&& await page.evaluate( () => /checks complete/i.test( document.body.innerText ) );

	if ( done && stable >= 3 ) {
		break;
	}
	if ( stable >= 150 ) {
		break;
	}
}

console.log( '\n----- Plugin Check output -----\n' );
console.log( text.trim() );
console.log( '\n----- end -----' );

const failed = checkReplies.filter( ( c ) => ! c.ok );
console.log( `\n${ expected.length } checks asked for, ${ checkReplies.length } ran, ${ checkReplies.length - failed.length } answered success` );
for ( const c of failed ) {
	console.log( `  NOT RUN  ${ c.name }: ${ c.why }` );
}
for ( const name of expected.filter( ( n ) => ! checkReplies.some( ( c ) => c.name === n ) ) ) {
	console.log( `  NO REPLY ${ name }` );
}

const clean = /no errors found/i.test( text ) && ! /\bERROR\b|\bWARNING\b/i.test( text );

await page.screenshot( { path: 'tools/.plugin-check.png', fullPage: true } );

await browser.close();

if ( 0 === expected.length || failed.length || checkReplies.length < expected.length ) {
	console.log( 'FAILED RUN -- a check that did not run has reported nothing, which is not the same as clean.' );
	process.exit( 2 );
}

process.exit( clean ? 0 : 1 );
