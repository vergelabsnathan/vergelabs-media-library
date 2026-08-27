/*
 *  The AI folder group, in a browser.
 *
 *      node tests/tree/ai-folders.mjs http://127.0.0.1:8899
 *
 *  Boot Playground against tests/tree/ai-folders-ui-blueprint.json, which
 *  seeds nine described files and four undescribed ones -- so the group has
 *  rows, some kinds are empty, and the described total is smaller than the
 *  library.
 *
 *  What it proves is what the PHP suite cannot: that the panel draws the
 *  group, that the empty kinds are absent from it, that the honesty pair is
 *  on the heading, that selecting a row filters the library, and that the
 *  group folds and stays folded.
 *
 *  127.0.0.1, never localhost: WordPress builds its own URLs from siteurl and
 *  the other name fails every nonce.
 */
import { chromium } from 'playwright';

const BASE = process.argv[ 2 ] ?? 'http://127.0.0.1:8899';

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? `  -- ${ detail }` : '' }` );
};

const browser = await chromium.launch();
const page = await ( await browser.newContext( { viewport: { width: 1500, height: 1100 } } ) ).newPage();
page.setDefaultTimeout( 120000 );

const errors = [];
page.on( 'pageerror', ( e ) => {
	if ( ! e.message.includes( 'isImageFile' ) ) {
		errors.push( e.message.slice( 0, 160 ) );
	}
} );

await page.goto( `${ BASE }/wp-admin/upload.php`, { waitUntil: 'domcontentloaded' } );

/*
 *  Attached, not visible.
 *
 *  The panel can be folded -- it is a stored per-user preference -- and a
 *  folded panel still has its rows in the DOM. Waiting for visibility makes
 *  this suite fail on a site somebody once collapsed the tree on, which is a
 *  failure about a preference and not about the code.
 */
await page.waitForSelector( '.vgml-node', { state: 'attached', timeout: 120000 } );

/*
 *  Is this even our site?
 *
 *  Nothing about a port number says whose Playground answers on it. Anyone
 *  running a second project with a Playground of its own -- which is the
 *  normal way to work -- can have something entirely unrelated on 8899, and
 *  without this check the suite tests that instead and reports failures about
 *  a plugin nobody was asking about. Worse, it could pass.
 *
 *  So: the blueprint seeds thirteen attachments with titles nothing else
 *  would have. If they are not here, this is somebody else's WordPress, and
 *  the suite says so and stops rather than guessing.
 */
const fingerprint = await page.evaluate( async () => {
	const res = await window.wp?.apiFetch?.( {
		path: '/wp/v2/media?per_page=100&search=ui%20seed&_fields=id,title',
	} ).catch( () => null );
	return {
		hasApi: !! window.wp?.apiFetch,
		seeded: Array.isArray( res ) ? res.length : -1,
	};
} );

if ( ! fingerprint.hasApi || fingerprint.seeded < 9 ) {
	console.error(
		`\nThis is not the site this suite seeds.\n\n` +
		`  ${ BASE } answered, but it does not hold the nine described files that\n` +
		`  tests/tree/ai-folders-ui-blueprint.json creates (found ${ fingerprint.seeded }).\n\n` +
		`  Something else is on this port -- another project's Playground, or a\n` +
		`  Playground booted from a different blueprint. Boot this one:\n\n` +
		`    npx @wp-playground/cli server --port 8899 --php=8.3 --wp=latest \\\n` +
		`      --mount-dir "<repo>" /wordpress/wp-content/plugins/vergelabs-media-library \\\n` +
		`      --blueprint=tests/tree/ai-folders-ui-blueprint.json\n\n` +
		`  or point this suite somewhere else:  node tests/tree/ai-folders.mjs http://127.0.0.1:<port>\n`
	);
	await browser.close();
	process.exit( 2 );
}

/*
 *  Its own precondition, performed rather than written down.
 *
 *  Whether the group is open is a stored per-user preference, and this suite
 *  folds it on purpose before it finishes. Without putting it back first, the
 *  second run of the suite starts folded and every assertion about the rows
 *  fails -- a failure about the previous run, not about the code. The same
 *  trap smart.mjs hit, which is why tools/verify.mjs grew preconditions at
 *  all; this one can do its own, because there is an endpoint for it.
 */
await page.evaluate( async () => {
	await window.wp.apiFetch( {
		path: '/vergeml/v1/state',
		method: 'POST',
		data: { taxonomy: 'media_category', aiOpen: 1, filtersOpen: 1 },
	} ).catch( () => null );
} );

await page.reload( { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-node', { state: 'attached', timeout: 120000 } );

const heads = await page.$$eval( '.vgml-ai-head .vgml-filters-label', ( els ) => els.map( ( e ) => e.textContent.trim() ) );

check( 'the AI group has its own heading', 1 === heads.length, heads.join( ' / ' ) || 'none found' );

const partial = await page.$eval( '.vgml-ai-partial', ( e ) => e.textContent.trim() ).catch( () => '' );

check( 'the heading says how much of the library it is drawn from', '9/13' === partial, partial || 'absent' );

const rows = await page.$$eval( '.vgml-smart[data-smart^="ai-"]', ( els ) =>
	els.map( ( e ) => ( {
		key: e.getAttribute( 'data-smart' ),
		text: e.textContent.trim(),
	} ) ) );

const keys = rows.map( ( r ) => r.key );

check( 'the seeded kinds are rows', keys.includes( 'ai-kind-photo' ) && keys.includes( 'ai-kind-screenshot' ) );

check( 'the empty kinds are not rows',
	! keys.includes( 'ai-kind-diagram' ) && ! keys.includes( 'ai-doc-receipt' ),
	keys.join( ', ' ) );

check( 'the document types that exist are rows',
	keys.includes( 'ai-doc-invoice' ) && keys.includes( 'ai-doc-contract' ) );

check( 'no ladder row, because something has been described',
	0 === ( await page.$$( '.vgml-ai-ladder' ) ).length );

/*
 *  Selecting. The row filters the library and the real-folder selection is
 *  cleared -- one selection, one meaning, which is the decision this is
 *  pinning.
 */
/*
 *  In list mode deliberately. The grid applies the filter through wp.media's
 *  own props and leaves the address bar alone -- correct there, and how the
 *  five original smart folders have always behaved -- so the bookmarkable URL
 *  this phase decided on only exists on the list screen. Testing it in grid
 *  mode would be testing the wrong surface and failing for it.
 */
await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-smart[data-smart="ai-kind-screenshot"]', { state: 'attached', timeout: 120000 } );

await page.click( '.vgml-smart[data-smart="ai-kind-screenshot"] .vgml-row' );

await page.waitForFunction(
	() => document.querySelector( '.vgml-smart[data-smart="ai-kind-screenshot"]' )?.classList.contains( 'is-selected' ),
	{ timeout: 60000 },
);

check( 'clicking a row selects it', true );

const otherSelected = await page.$$eval( '.vgml-node.is-selected', ( els ) => els.length );

check( 'and nothing else is selected at the same time', 1 === otherSelected, `${ otherSelected } selected` );

/*
 *  Waited for, not read straight away: the list screen swaps its table over
 *  fetch and only then pushes the address, so reading location immediately
 *  after the click reads the address of the page before it.
 */
const bookmarked = await page
	.waitForFunction( () => window.location.search.indexOf( 'vgml_smart=ai-kind-screenshot' ) > -1, { timeout: 60000 } )
	.then( () => true )
	.catch( () => false );

check( 'the list view carries a bookmarkable key', bookmarked, page.url() );

/*
 *  And the point of all of it: the library in front of somebody is actually
 *  the two screenshots. A selected row over an unfiltered table would pass
 *  every check above it and be useless.
 */
const listed = await page.$$eval( '.wp-list-table tbody tr', ( trs ) =>
	trs.filter( ( tr ) => ! tr.classList.contains( 'no-items' ) ).length );

check( 'and the list really is filtered to those two files', 2 === listed, `${ listed } rows` );

/*
 *  Folding. The state is per user and persisted, so it has to survive a
 *  reload -- a group that reopens itself every visit is a group somebody
 *  closes every visit.
 */
await page.click( '.vgml-ai-head .vgml-row' );

await page.waitForFunction(
	() => 0 === document.querySelectorAll( '.vgml-smart[data-smart^="ai-"]' ).length,
	{ timeout: 60000 },
);

check( 'the group folds', true );

await page.waitForTimeout( 1500 );
await page.goto( `${ BASE }/wp-admin/upload.php`, { waitUntil: 'domcontentloaded' } );
await page.waitForSelector( '.vgml-node', { timeout: 120000 } );

const stillFolded = 0 === ( await page.$$( '.vgml-smart[data-smart^="ai-"]' ) ).length;

check( 'and stays folded after a reload', stillFolded );

check( 'no javascript errors', 0 === errors.length, errors.join( ' | ' ) );

await page.screenshot( { path: 'tests/tree/shots/ai-folders.png', fullPage: false } ).catch( () => {} );

await browser.close();

const failed = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - failed }/${ results.length } passed` );
process.exit( failed ? 1 : 0 );
