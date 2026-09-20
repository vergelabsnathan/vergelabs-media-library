/*
 *  One way to run the browser suites, and only one at a time.
 *
 *  The suites mutate the site they test: t0 assigns files to folders and undoes
 *  it, the watchdog breaks a plugin file and repairs it, the health suites
 *  scan and rescan. Each is correct on a site nobody else is touching, and
 *  each produces failures indistinguishable from a real regression when two
 *  runs overlap -- a wrong folder count, an undo whose token no longer
 *  matches, a report drawn from the previous run's numbers.
 *
 *  That cost most of a session once. The fix is not to make every suite
 *  reentrant; it is to make overlapping runs impossible and to run the suites
 *  in sequence.
 *
 *      node tools/verify.mjs                     # every suite it can reach
 *      node tools/verify.mjs health ai           # only those
 *      node tools/verify.mjs --base http://<box> --playground http://127.0.0.1:8899
 *
 *  Two environments, and the suites are not interchangeable between them. The
 *  tree and watchdog suites want Playground -- the watchdog one breaks a
 *  plugin file on purpose, which is not something to do to a box holding
 *  anything you care about. The rest want real MySQL. A suite whose
 *  environment is not answering is reported as SKIPPED, never as passed.
 */
import { spawn, execSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const LOCK = path.join( ROOT, '.verify.lock' );

/*
 *  The checkout as Playground can mount it. On Windows the path has spaces
 *  and an emoji, and cmd.exe splits the one and mangles the other on the way
 *  to the CLI; the volume's 8.3 short name is plain ASCII for the same files
 *  (the same trick tools/play.mjs uses).
 */
let MOUNT_ROOT = ROOT;
if ( 'win32' === process.platform ) {
	try {
		const short = execSync( `for %I in ("${ ROOT }") do @echo %~sI`, { shell: 'cmd.exe', encoding: 'utf8' } ).trim().split( /\r?\n/ ).pop();
		if ( short && ! /\s/.test( short ) ) {
			MOUNT_ROOT = short;
		}
	} catch ( e ) { /* keep the long path */ }
}

const argv = process.argv.slice( 2 );

function flag( name, fallback ) {
	const at = argv.indexOf( name );
	return at >= 0 ? argv[ at + 1 ] : fallback;
}

const BASE = flag( '--base', 'http://46.225.66.194' );
const PLAYGROUND = flag( '--playground', 'http://127.0.0.1:8899' );

/*
 *  The values that follow a flag are not suite names. Without this the first
 *  bare argument was dropped whenever a flag was absent -- indexOf returns -1,
 *  -1 + 1 is 0 -- and `verify nosuch` quietly ran everything.
 */
const taken = new Set();
for ( const name of [ '--base', '--playground' ] ) {
	const at = argv.indexOf( name );
	if ( at >= 0 ) {
		taken.add( at );
		taken.add( at + 1 );
	}
}

const only = argv.filter( ( a, i ) => ! taken.has( i ) && ! a.startsWith( '--' ) );

/*
 *  Order matters. The watchdog runs last because it deliberately drives the
 *  site into safe mode, and a suite that starts while the features are off
 *  reports every one of them missing.
 */
/*
 *  `before` is a suite's precondition, run for it rather than written in a
 *  document and forgotten. smart.mjs tests the first-run experience, and there
 *  is deliberately no endpoint to un-scan a library -- that is not something
 *  the interface should offer -- so the state has to be set from outside the
 *  browser. It used to live as a line in CLAUDE.md, which meant the suite
 *  failed with a JSON blob whenever somebody forgot.
 */
const SUITES = [
	{ name: 'tree', file: 'tests/tree/t0-endpoints.js', env: 'playground' },
	/*
	 *  The shared tree component, against a page loaded from disk: the draft
	 *  overlay by term id, the two states, the fold rule. No site needed, so
	 *  env 'local' -- nothing to reach before it runs.
	 */
	{ name: 'tree-view', file: 'tests/tree/tree-view.mjs', env: 'local' },
	/*
	 *  The conversation component's own chips (js/vergeml-talk.js), against
	 *  a page loaded from disk: a choice the model offers sends a turn when
	 *  pressed. Dead from 2026-09-06 to 09-18 with no suite loading the file.
	 */
	{ name: 'talk-chips', file: 'tests/tree/talk-chips.mjs', env: 'local' },
	/*
	 *  The Folders screen on a site that sells, against a page loaded from
	 *  disk: the Tree step's categories button, and the press behind it as a
	 *  paste. S19's walk of the real shop found there was no such way in.
	 */
	{ name: 'folders-shop', file: 'tests/tree/folders-shop.mjs', env: 'local' },
	/*
	 *  The paste reader (js/vergeml-structure.js) and the preview it feeds,
	 *  against a page loaded from disk: one reading of a paste, the three
	 *  refusals, reuse of what exists. env 'local'.
	 */
	{ name: 'structure', file: 'tests/tree/structure.mjs', env: 'local' },
	/*
	 *  The copy standard, over the strings Phase 4 struck. Reads the source
	 *  from disk, so env 'local'. Copy rots back: "Please try again" is the
	 *  first thing typed when somebody adds the next error message.
	 */
	{ name: 'copy', file: 'tests/tree/copy.mjs', env: 'local' },
	/*
	 *  The second library's seed against its catalogue (every-picture-a-home
	 *  C.5): every subject names a leaf of the shop tree, the tree keeps its
	 *  shape and its shared leaf names. Two files read from disk; env 'local'.
	 */
	{ name: 'seed-shop', file: 'tests/tree/seed-shop.mjs', env: 'local' },
	/*
	 *  The security surface, regenerated from the source and compared with the
	 *  committed docs/security-surface.md. env 'local': it reads PHP as text and
	 *  reaches nothing. Phase 5.1 asked for a list that cannot drift, and a list
	 *  only cannot drift if something regenerates it -- this is that something.
	 */
	{ name: 'surface', file: 'tests/security/surface.mjs', env: 'local' },
	/*
	 *  All 202 database calls, classified one at a time, with the eleven read by
	 *  hand keyed to a hash of their own SQL so a reason cannot outlive the query
	 *  it was written about. env 'local': PHP as text, nothing reached.
	 */
	{ name: 'db-calls', file: 'tests/security/db-calls.mjs', env: 'local' },
	/*
	 *  Every outbound request names a host decided by a constant, an owner's
	 *  define() or one of those two -- never by a request, an option or a post.
	 *  The register is docs/security-hosts.md, one row per wp_remote_* site; a
	 *  call without a row, or an sslverify => false anywhere, goes red. env
	 *  'local': PHP as text, nothing reached. Phase 5.6.
	 */
	{ name: 'hosts', file: 'tests/security/hosts.mjs', env: 'local' },
	/*
	 *  Every HTML sink on both sides of the wire. Plugin Check covers the PHP half;
	 *  this one exists for the other half, where a folder name arrives as JSON and
	 *  a script puts it in the page with no esc_html() in sight.
	 */
	{ name: 'escaping', file: 'tests/security/escaping.mjs', env: 'local' },
	/*
	 *  Every bare eml-/vergeml- call in the scripts names something a script
	 *  defines. Sixteen did not, for however long a rename had been in the tree,
	 *  and four destructive buttons silently did nothing. env 'local'.
	 */
	{ name: 'globals', file: 'tests/security/globals.mjs', env: 'local' },
	/*
	 *  The filing engine on fixtures: the matcher's three outcomes and the
	 *  count both the preview and the run read, then the residue grouped and
	 *  the answers applied to an in-memory map. Pure PHP over arrays -- no
	 *  WordPress, no database -- so it runs here, through Playground's PHP
	 *  with the checkout mounted (runPhpLocal), and never on the box.
	 */
	{ name: 'filing', files: [ 'tests/filing/pick.php', 'tests/filing/residue.php' ], env: 'local', php: 'wasm' },
	/*
	 *  Confirm, lock, sticky (A.3): a hand move survives a fill, a locked
	 *  folder keeps its pictures and gains none, a confirmed tree answers 409.
	 *  The box: it stores a packed embedding and drives a real pass over its
	 *  own six pictures. Every service call is answered in the suite; nothing
	 *  is spent. Makes and removes its own pictures and folders.
	 */
	{ name: 'sticky', file: 'tests/filing/sticky.php', env: 'box', php: true },
	/*
	 *  The walk, by script (A.4): confirm -> fill -> every question answered
	 *  -> 0 in no folder, then undone. The box, and the whole library: the
	 *  matcher needs the real index. Spends nothing (a fixture of the last
	 *  tree; the group names are metered). The library is as it was after.
	 */
	{ name: 'fill-walk', file: 'tools/box-fill-walk.php', env: 'box', php: true },
	/*
	 *  The Delete All Data button opens its confirmation and Cancel closes it.
	 *  Playground, never the box: the button deletes everything if the dialog is
	 *  ever answered yes, and a test that can only do that to a throwaway site is
	 *  one that can be run without thinking.
	 */
	{ name: 'dialogs', file: 'tests/security/dialogs.mjs', env: 'playground' },
	/*
	 *  Every route's permission callback, as an anonymous visitor, a subscriber,
	 *  an author and an editor. Real MySQL and real roles, so the box: it creates
	 *  three users and two attachment rows to ask the object question with, and
	 *  deletes all five. It calls the gates and never the handlers, so none of
	 *  the 76 endpoints runs and nothing is written or spent.
	 */
	{ name: 'roles', file: 'tests/security/roles.php', env: 'box', php: true },
	/*
	 *  Nothing outside uploads is renamed, packed, read out or deleted: three
	 *  poisoned attachment rows (a traversal, an absolute path, a symlink out)
	 *  driven through the renamer, the folder archive, the describe payload,
	 *  the settings import and WordPress's own delete, with the target's hash
	 *  taken after each. The box, not Playground: a symlink needs a real
	 *  filesystem. Creates and removes its own files and rows only. Phase 5.5.
	 */
	{ name: 'paths', file: 'tests/security/paths.php', env: 'box', php: true },
	/*
	 *  A 3.16.1 site upgraded in place to 4.0.0 kept everything: runs on the
	 *  upgrade fixture (/var/www/upg, built by tests/compat/upgrade-3161-fixture.php
	 *  under 3.16.1 and frozen by upgrade-3161-snapshot.php), not the tech site.
	 *  `wp` names the WordPress it runs in; VGML_SNAP the frozen state. Story 1.2
	 *  of plans/suite-readiness.md.
	 */
	{ name: 'upgrade-3161', file: 'tests/compat/upgrade-3161.php', also: [ 'tests/compat/upgrade-3161-snapshot.php' ], env: 'box', php: true, wp: '/var/www/upg', vars: { VGML_SNAP: '/root/vgml-upg.json' } },
	/*
	 *  The same walk in Playground on PHP 8.5 against the working tree's own
	 *  zip -- the smoke, run before the box. Builds playground/*.zip itself.
	 */
	{ name: 'upgrade-3161-smoke', file: 'tests/compat/upgrade-3161.spec.mjs', env: 'local' },
	/*
	 *  Pro 1.0.2 on free 4.0.0, on MySQL: the Pro repo's compat-free suite run
	 *  on the upgrade fixture with the Pro 1.0.2 archive installed and active
	 *  (tools/box-upgrade-site.sh plugin <zip>). The proof is the archives leg
	 *  in Playground (pro/tools/verify.mjs compat-free); this is the same file
	 *  on a real database. Needs VGMLPRO_SEATS_KEY in the environment -- the
	 *  one-seat test licence, never the site's own; the suite hands the seat
	 *  back. Without the key, or with Pro inactive on the fixture (how story
	 *  1.3 leaves it), the suite exits 2 and is reported SKIPPED, not failed.
	 */
	{ name: 'compat-free-upg', file: '../pro/tests/compat-free.php', env: 'box', php: true, wp: '/var/www/upg', vars: { VGMLPRO_SEATS_KEY: ( process.env.VGMLPRO_SEATS_KEY || '' ).trim(), VGMLPRO_COMPAT_ARCHIVES: '1' } },
	/*
	 *  The licence key at rest, in logs and in responses: a canary key planted
	 *  through the settings route, then the tables, every GET route as an
	 *  administrator, a describe pass against a stand-in service, PHP's log,
	 *  the support ticket and the counts snapshot read back for it. The box:
	 *  real options table, real debug.log, a real image to build the payload
	 *  from. pre_http_request answers every request, so nothing is spent; the
	 *  options it writes are put back from a shutdown function. Phase 5.7.
	 */
	{ name: 'secrets', file: 'tests/security/secrets.php', env: 'box', php: true },
	// The folders version stamp and its route, including the one-query budget.
	{ name: 'folders-version', file: 'tests/tree/folders-version.php', env: 'box', php: true },
	{ name: 'guide', file: 'tests/tree/guide.php', env: 'box', php: true },
	/*
	 *  Why a picture went where it went, from the matcher's answer to the row
	 *  that keeps it. The box, not Playground: a FLOAT that has to come back
	 *  null, six columns dbDelta has to add to a table with rows already in
	 *  it, and an ALTER that puts them back. SQLite answers a different
	 *  question about all three.
	 */
	{ name: 'filing-trail', file: 'tests/tree/filing-trail.php', env: 'box', php: true },
	// The brief as a conversation: the free opener, the held test, adopting. Stands in for the service; spends nothing.
	{ name: 'brief', file: 'tests/tree/brief.php', env: 'box', php: true },
	// Try a query: both searches for one phrase, each hit with its why. One embed call, not charged.
	{ name: 'search-try', file: 'tests/tree/search-try.php', env: 'box', php: true },
	// Look-alikes: keep this one (rewrite, then set aside), keep both, undo. Walked on two copies it makes and removes; spends nothing.
	{ name: 'health-keep', file: 'tests/tree/health-keep.php', env: 'box', php: true },
	/*
	 *  The AI folders, in two halves for the usual reason. The PHP one is
	 *  about the join and the counts and wants real MySQL -- Playground's
	 *  SQLite layer does not maintain $wpdb->num_queries, so the budget half
	 *  of it reports itself skipped there rather than passing on a zero. The
	 *  browser one is about whether the group can be used.
	 */
	{ name: 'ai-folders', file: 'tests/tree/ai-folders.php', env: 'box', php: true },
	{ name: 'ai-folders-ui', file: 'tests/tree/ai-folders.mjs', env: 'playground' },
	/*
	 *  Filing by itself. The box: since the matcher reads the picture's
	 *  classes and vector off the index row (core/filing.php, 5 Sept), the
	 *  fixture stores both, and a packed-float embedding is an INSERT the
	 *  Playground's SQLite layer refuses. It was labelled playground while
	 *  the vectors came through a seam, and ran on the box regardless --
	 *  every PHP suite ships over SSH.
	 */
	{ name: 'auto-file', file: 'tests/tree/auto-file.php', env: 'box', php: true },
	/*
	 *  Spoken commands. Mostly a suite about refusals, so it needs nothing
	 *  a real database has that Playground does not.
	 */
	{ name: 'say', file: 'tests/tree/nl-commands.php', env: 'playground', php: true },
	/*
	 *  Setting aside. Mostly assertions that a file survived, so it needs
	 *  nothing a real database has that Playground does not.
	 */
	{ name: 'quarantine', file: 'tests/tree/quarantine.php', env: 'playground', php: true },
	{ name: 'utilities', file: 'tests/tree/utilities.php', env: 'playground', php: true },
	{ name: 'health', file: 'tests/tree/health.mjs', env: 'box' },
	/*
	 *  The background run, before the on-screen one: the describe hold keeps
	 *  a file just described from being described again for ten minutes, so
	 *  the three files the `ai` suite resets and re-describes would otherwise
	 *  sit in this suite's backlog as "left pending" for the wrong reason.
	 */
	{
		name: 'ai-background',
		file: 'tests/ai/background.php',
		env: 'box',
		php: true,
		// A file described in the last ten minutes is held from being described
		// again (the credit-loop guard). Suites that reset files and expect them
		// re-described inside that window would count the held ones as a backlog
		// that never clears, so the hold is lifted first.
		before: `wp eval 'global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE \\"%transient%vergeml_ai_recent%\\"" );'`,
	},
	{
		name: 'ai',
		file: 'tests/tree/ai.mjs',
		env: 'box',
		// "Describe new images" needs images that are new. Once the library is
		// fully described the suite has nothing to do and fails saying so --
		// which is true, and useless. Three files go back to undescribed, and
		// the ten-minute hold on them is lifted (see ai-background).
		before: `wp eval 'global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE \\"%transient%vergeml_ai_recent%\\"" ); foreach ( get_posts( array( "post_type" => "attachment", "post_status" => "inherit", "post_mime_type" => "image", "posts_per_page" => 3, "fields" => "ids" ) ) as $i ) { vergeml_index_delete( $i ); delete_post_meta( $i, "_wp_attachment_image_alt" ); }'`,
	},
	{
		name: 'smart',
		file: 'tests/tree/smart.mjs',
		env: 'box',
		before: 'wp option delete vergeml_smart_scan',
	},
	/*
	 *  Folders as a file. The assertion that matters is the round trip -- export
	 *  a tree, delete it, import the file, compare -- so it needs a real library
	 *  with folders already in it to prove the import merges into them rather
	 *  than duplicating them. That is the box.
	 */
	{ name: 'csv', file: 'tests/import/csv.php', env: 'box', php: true },
	/*
	 *  Folders only one person sees. On the box because it needs real users
	 *  and a real term query -- the whole feature is what get_terms returns
	 *  for somebody who is not the owner.
	 */
	{ name: 'private-folders', file: 'tests/tree/private-folders.php', env: 'box', php: true },
	/*
	 *  The dashboard. Its figures are counts over the whole library and its
	 *  states are forced through the facts filter, so it wants the box's
	 *  real MySQL and its real library; Playground's SQLite does not keep
	 *  $wpdb->num_queries, which the budget half of it reads.
	 */
	{ name: 'journey', file: 'tests/tree/journey.php', env: 'box', php: true },
	/*
	 *  The counts a site shares. The sender is proven against a mocked
	 *  pre_http_request, so it spends nothing and reaches nothing; it wants
	 *  the box for the same reason the dashboard does.
	 */
	{ name: 'counts', file: 'tests/tree/counts.php', env: 'box', php: true },
	/*
	 *  Two languages. Reports itself SKIPPED when Polylang is not active, and
	 *  again on the run that switches folder translation on -- Polylang decides
	 *  which taxonomies are translated during init, so the setting only bites
	 *  on the next request.
	 *
	 *  Polylang is left DEACTIVATED on the box between runs, deliberately. It
	 *  costs one query on every request, which pushes the tree to 8 and
	 *  health-report to 6 and makes Gate 5 read as a regression that is not
	 *  ours. To run this suite:
	 *
	 *      wp plugin activate polylang
	 *      wp eval-file .../tests/compat/polylang.php   # twice, see above
	 *      wp plugin deactivate polylang
	 */
	{ name: 'polylang', file: 'tests/compat/polylang.php', env: 'box', php: true },
	/*
	 *  A PHP suite rather than a browser one, because what it tests has no
	 *  screen yet: the tree is data, and the phase that renders it is the next
	 *  one. It runs where the other PHP suites run -- shipped to the box and
	 *  handed to wp eval-file -- and reports the same way, by exiting non-zero.
	 */
	{ name: 'organize', file: 'tests/organize/test-organize.php', env: 'box', php: true },
	/*
	 *  What the folders end up called. Separate from the suite above because it
	 *  is about one function and the answer is readable prose -- a run whose
	 *  folders are "Account Basket" and "Anna Catalogue" passes every structural
	 *  assertion the clusterer makes and is still unusable.
	 */
	{ name: 'naming', file: 'tests/organize/naming.php', env: 'box', php: true },
	/*
	 *  The words, against the list of words docs/voice.md forbids. A rule that
	 *  lived only in a markdown table for months while the plugin shipped a
	 *  button reading "Propose a tree".
	 */
	{ name: 'voice', file: 'tests/copy/voice.php', env: 'box', php: true },
	/*
	 *  Naming files after what is in them. Two of its three claims are about
	 *  restraint -- never over a name somebody wrote, and never locking the
	 *  file against being renamed again on the way past.
	 */
	{ name: 'rename', file: 'tests/rename/rename.php', env: 'box', php: true },
	/*
	 *  Renaming the file on disk. The box, because it moves real files beside
	 *  their generated sizes and rewrites real post content -- and because a
	 *  rename that half-works is a broken image on somebody's page.
	 */
	{ name: 'rename-files', file: 'tests/rename/files.php', env: 'box', php: true },
	/*
	 *  Two suites for the Librarian, and they answer different questions. The
	 *  PHP one is about what applying and undoing actually do to the database
	 *  -- assignments, folders, the moves log -- and needs real MySQL. The
	 *  browser one is about whether the screen can be used, and runs in
	 *  Playground where a batch can be applied to a throwaway library.
	 */
	{ name: 'librarian', file: 'tests/librarian/test-librarian.php', env: 'box', php: true },
	/*
	 *  Gate 7's own regression guard: drop both tables and prove the lazy
	 *  install puts them back. Separate from the suite above because it is
	 *  destructive about the schema rather than about rows, and because it is
	 *  the one librarian check that also runs without a box --
	 *  tests/librarian/gate7-blueprint.json drives it through Playground.
	 */
	{ name: 'librarian-schema', file: 'tests/librarian/gate7-schema.php', env: 'box', php: true },
	{ name: 'watchdog', file: 'tests/watchdog/recovery.js', env: 'playground' },
];

/*
 *  Which box, and how to reach it -- derived from --base rather than fixed.
 *
 *  This used to be one hardcoded string pointing at Kamatera. --base moved the
 *  HTTP suites and nothing else, so pointing the runner at a second box ran the
 *  browser suites against the new one while every PHP suite was shipped over
 *  SSH to the old one and reported under the new one's name. Two boxes, one
 *  set of results, no way to tell which had answered.
 *
 *  It also silently broke the preconditions: `wp option delete
 *  vergeml_smart_scan` ran on Kamatera while tests/tree/smart.mjs tested
 *  Hetzner, so the suite failed on a badge the precondition had cleared
 *  somewhere else entirely.
 *
 *  A box therefore has to be listed here to be usable, key included. Adding
 *  one is three lines; guessing a key path from a hostname is how the next
 *  quiet mismatch would arrive.
 */
const BOXES = {
	//  Hetzner CX33, Nuremberg. Ubuntu 26.04 / PHP 8.5 / MariaDB 11.8 -- ahead of
	//  what most users run, deliberately: that is where the next forward-compat
	//  bug shows up first, and it found one within a minute of existing.
	//  Kamatera was retired 29-08-2026.
	'46.225.66.194': { key: '~/.ssh/hetzner_vgml', wp: '/var/www/wp' },
};

const BOX_HOST = BASE.replace( /^https?:\/\//, '' ).replace( /\/.*$/, '' );
const BOX = BOXES[ BOX_HOST ];

if ( ! BOX ) {
	console.error( `\n  --base points at ${ BOX_HOST }, which is not a box this runner knows how to reach.` );
	console.error( '  PHP suites are shipped over SSH, so it needs the key. Add it to BOXES in this file.\n' );
	process.exit( 1 );
}

const SSH = `ssh -i ${ BOX.key } -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null root@${ BOX_HOST }`;

function precondition( suite ) {
	return new Promise( ( resolve ) => {

		if ( ! suite.before || 'box' !== suite.env ) {
			return resolve( true );
		}

		console.log( `  precondition: ${ suite.before }` );

		const child = spawn(
			SSH.split( ' ' )[ 0 ],
			[ ...SSH.split( ' ' ).slice( 1 ), `cd ${ suite.wp || BOX.wp } && ${ suite.before } --allow-root` ],
			{ stdio: 'ignore' }
		);

		// A precondition that cannot run is worth saying out loud, but it is
		// the suite's own failure that decides the result.
		child.on( 'close', ( code ) => {
			if ( 0 !== code ) {
				console.log( `  precondition exited ${ code } — the suite may fail for that reason` );
			}
			resolve( true );
		} );
		child.on( 'error', () => {
			console.log( '  precondition could not run (no ssh?)' );
			resolve( true );
		} );
	} );
}

const baseFor = ( suite ) => ( 'playground' === suite.env ? PLAYGROUND : ( 'local' === suite.env ? '' : BASE ) );

async function reachable( url ) {
	try {
		const c = new AbortController();
		const t = setTimeout( () => c.abort(), 8000 );
		await fetch( url, { signal: c.signal, redirect: 'manual' } );
		clearTimeout( t );
		return true;
	} catch ( e ) {
		return false;
	}
}

function takeLock() {
	try {
		// wx: fails if it already exists, which is the whole point.
		fs.writeFileSync( LOCK, String( process.pid ), { flag: 'wx' } );
		return true;
	} catch ( e ) {
		if ( 'EEXIST' !== e.code ) throw e;
	}

	const holder = Number( fs.readFileSync( LOCK, 'utf8' ) );

	/*
	 *  A lock left behind by a run that was killed. Signal 0 asks "is this pid
	 *  alive" without touching it -- but EPERM means alive and not ours, which
	 *  is still alive. Only ESRCH, no such process, makes the lock litter.
	 */
	try {
		process.kill( holder, 0 );
	} catch ( e ) {
		if ( 'ESRCH' === e.code ) {
			console.log( `  clearing a stale lock from pid ${ holder }` );
			fs.unlinkSync( LOCK );
			return takeLock();
		}
	}

	console.error( `\nAnother verify run is in progress (pid ${ holder }).` );
	console.error( 'Wait for it, or stop it — two runs against one site produce failures that\n' +
		'look exactly like regressions.\n' );
	return false;
}

/*
 *  A PHP suite runs on the box, not here: there is no PHP binary locally, and
 *  the point of these is to exercise the plugin inside a real WordPress
 *  against real MySQL. Shipped fresh every run rather than relying on whatever
 *  the last deploy left in place -- a suite that silently tests an older copy
 *  of itself is worse than one that does not run.
 */
/**
 *  How many checks a PHP suite says it ran, from its own summary line.
 *
 *  Every one of them ends with "N/M passed", where M is the total. Returns that
 *  M, or null when no such line was printed at all -- which is its own kind of
 *  silence and treated as one. The last match wins, because a suite is free to
 *  print progress totals on the way through.
 */
function countedChecks( output ) {

	const found = String( output ).match( /(\d+)\s*\/\s*(\d+)\s+passed/g );

	if ( ! found || ! found.length ) {
		return null;
	}

	const last = found[ found.length - 1 ].match( /(\d+)\s*\/\s*(\d+)/ );

	return last ? Number( last[ 2 ] ) : null;
}


function runPhp( suite ) {
	return new Promise( ( resolve ) => {

		const remote = `/tmp/${ path.basename( suite.file ) }`;
		const args = SSH.split( ' ' );

		// A suite may bring companions (`also`) -- a compare script it includes --
		// which land beside it under /tmp with their own basenames.
		const companions = ( suite.also || [] ).map( ( f ) => path.join( ROOT, f ) );
		const copy = spawn(
			'scp',
			[ ...args.slice( 1, args.length - 1 ), path.join( ROOT, suite.file ), ...companions, `${ args[ args.length - 1 ] }:/tmp/` ],
			{ stdio: 'inherit' }
		);

		copy.on( 'error', () => resolve( 1 ) );

		copy.on( 'close', ( code ) => {

			if ( 0 !== code ) {
				console.log( '  could not copy the suite to the box' );
				return resolve( code ?? 1 );
			}

			/*
			 *  Piped rather than inherited, so this runner can read what the
			 *  suite said as well as what it exited with -- see countedChecks()
			 *  below for why that turned out to matter. Each chunk is echoed
			 *  the moment it arrives: buffering to the end would make a suite
			 *  that takes a minute look like one that has hung.
			 */
			const child = spawn(
				args[ 0 ],
				[ ...args.slice( 1 ), `cd ${ suite.wp || BOX.wp } && ${ Object.entries( suite.vars || {} ).map( ( [ k, v ] ) => `${ k }='${ String( v ).replace( /'/g, "'\''" ) }'` ).join( ' ' ) } wp eval-file ${ remote } --allow-root` ],
				{ stdio: [ 'ignore', 'pipe', 'pipe' ] }
			);

			let said = '';

			child.stdout.on( 'data', ( chunk ) => {
				said += chunk;
				process.stdout.write( chunk );
			} );

			child.stderr.on( 'data', ( chunk ) => {
				process.stderr.write( chunk );
			} );

			child.on( 'error', () => resolve( 1 ) );

			child.on( 'close', ( c ) => {

				const code = c ?? 1;

				if ( 0 !== code ) {
					return resolve( code );
				}

				/*
				 *  A suite that exits 0 having counted nothing has not passed.
				 *
				 *  On 28-08-2026 four suites did exactly that for as long as
				 *  they had existed: `wp eval-file` evaluates a file inside a
				 *  function, so their top-level counters were locals and the
				 *  `global` in their check helper bound to an empty pair that
				 *  nothing incremented. Every summary read "0/0 passed", the
				 *  terminating exit(1) could never fire, and this runner --
				 *  which then saw only an exit code -- called all four passed.
				 *
				 *  Same reasoning the validate skill already applies to gate 1:
				 *  a count of zero is a failure, not a pass, because a gate
				 *  that checks nothing prints exactly what a clean one does.
				 */
				const counted = countedChecks( said );

				if ( null === counted ) {
					console.log( '\n  FAILED — the suite printed no "N/M passed" line, so there is nothing to trust here' );
					return resolve( 1 );
				}

				if ( 0 === counted ) {
					console.log( '\n  FAILED — the suite reported 0 checks. It exited 0 without asserting anything;' );
					console.log( '           check its counters are $GLOBALS and not `global`.' );
					return resolve( 1 );
				}

				resolve( 0 );
			} );
		} );
	} );
}

/*
 *  A PHP suite with no site in it, run here.
 *
 *  There is no PHP binary on this machine and the box is the wrong place for a
 *  suite that reaches no database: a fixture that fails there fails after a
 *  copy over SSH and a WordPress boot, for nothing. Playground's PHP is
 *  already on this machine (tools/play.mjs), and `run-blueprint` with a runPHP
 *  step is a PHP interpreter with the checkout mounted at /plugin. What the
 *  suite prints is captured to a file on a second mount, because the CLI shows
 *  no stdout when it is told not to install WordPress.
 *
 *  Passed only when the suite's own "N/M passed" line says every row passed
 *  and the process exited 0 -- the same two facts runPhp() reads off the box.
 */
function runPhpLocal( suite ) {
	return new Promise( ( resolve ) => {

		const files = suite.files ?? [ suite.file ];
		const outDir = fs.mkdtempSync( path.join( os.tmpdir(), 'vgml-verify-' ) );
		const results = [];

		const one = ( i ) => {

			if ( i >= files.length ) {
				fs.rmSync( outDir, { recursive: true, force: true } );
				return resolve( results.every( ( r ) => 0 === r ) ? 0 : 1 );
			}

			const file = files[ i ];
			const out = path.join( outDir, 'result.txt' );
			const blueprint = path.join( outDir, 'blueprint.json' );

			fs.rmSync( out, { force: true } );
			fs.writeFileSync( blueprint, JSON.stringify( { steps: [ {
				step: 'runPHP',
				// Whatever the suite prints, fatal errors included, reaches the file: the shutdown function runs after exit().
				code: `<?php ob_start(); register_shutdown_function( function () { file_put_contents( '/out/result.txt', ob_get_clean() ); } ); require '/plugin/${ file }';`,
			} ] } ) );

			console.log( `\n  ${ file }` );

			const child = spawn(
				'npx',
				[ '-y', '@wp-playground/cli', 'run-blueprint',
					'--blueprint', blueprint,
					'--mount-dir', MOUNT_ROOT, '/plugin',
					'--mount-dir', outDir, '/out',
					'--wordpress-install-mode', 'do-not-attempt-installing',
					'--verbosity', 'quiet' ],
				{ cwd: ROOT, stdio: [ 'ignore', 'ignore', 'inherit' ], shell: true, env: { ...process.env, MSYS_NO_PATHCONV: '1' } }
			);

			child.on( 'error', () => { results.push( 1 ); one( i + 1 ); } );

			child.on( 'close', ( c ) => {

				const said = fs.existsSync( out ) ? fs.readFileSync( out, 'utf8' ) : '';
				process.stdout.write( said );

				const found = String( said ).match( /(\d+)\s*\/\s*(\d+)\s+passed/g );
				const last = found && found.length ? found[ found.length - 1 ].match( /(\d+)\s*\/\s*(\d+)/ ) : null;
				const pass = last ? Number( last[ 1 ] ) : null;
				const total = last ? Number( last[ 2 ] ) : null;

				if ( null === total ) {
					console.log( '\n  FAILED — the suite printed no "N/M passed" line, so there is nothing to trust here' );
					results.push( 1 );
				} else if ( 0 === total ) {
					console.log( '\n  FAILED — the suite reported 0 checks' );
					results.push( 1 );
				} else if ( pass !== total || 0 !== ( c ?? 1 ) ) {
					console.log( `\n  FAILED — ${ pass }/${ total }, exit ${ c }` );
					results.push( 1 );
				} else {
					results.push( 0 );
				}

				one( i + 1 );
			} );
		};

		one( 0 );
	} );
}

function run( suite ) {

	console.log( `\n──────── ${ suite.name }  (${ suite.files ? suite.files.join( ', ' ) : suite.file } → ${ 'wasm' === suite.php ? 'php-wasm' : baseFor( suite ) })` );

	if ( 'wasm' === suite.php ) {
		return runPhpLocal( suite );
	}

	if ( suite.php ) {
		return runPhp( suite );
	}

	return new Promise( ( resolve ) => {
		/*
		 *  Browser suites take base, user, password as positional arguments and
		 *  default to the original box admin. That account's password is not
		 *  what it was, so the gate passes VGML_USER / VGML_PASS through when
		 *  they are set -- the same pair every flow:* script reads. Box suites
		 *  only: a Playground suite handed the box's throwaway admin tries a
		 *  login that does not exist there (dialogs, 2026-09-12), and the
		 *  blueprint's own admin/password is the account it wants.
		 */
		const creds = 'playground' !== suite.env && process.env.VGML_USER && process.env.VGML_PASS ? [ process.env.VGML_USER, process.env.VGML_PASS ] : [];
		const child = spawn( process.execPath, [ path.join( ROOT, suite.file ), baseFor( suite ), ...creds ], {
			cwd: ROOT,
			stdio: 'inherit',
		} );
		child.on( 'close', ( code ) => resolve( code ?? 1 ) );
	} );
}

if ( ! takeLock() ) {
	process.exit( 3 );
}

// Every exit path drops the lock, or the next run inherits a lie.
const release = () => {
	try {
		if ( fs.existsSync( LOCK ) && Number( fs.readFileSync( LOCK, 'utf8' ) ) === process.pid ) {
			fs.unlinkSync( LOCK );
		}
	} catch ( e ) { /* nothing useful to do while exiting */ }
};

process.on( 'exit', release );
process.on( 'SIGINT', () => process.exit( 130 ) );
process.on( 'SIGTERM', () => process.exit( 143 ) );

const chosen = only.length ? SUITES.filter( ( s ) => only.includes( s.name ) ) : SUITES;

if ( ! chosen.length ) {
	console.error( `No suite matched. Known: ${ SUITES.map( ( s ) => s.name ).join( ', ' ) }` );
	process.exit( 2 );
}

console.log( `\nverify` );
console.log( `  box        ${ BASE }` );
console.log( `  playground ${ PLAYGROUND }` );
console.log( `  ${ chosen.length } suite(s), one at a time` );

const failed = [];
const skipped = [];
const passed = [];

for ( const suite of chosen ) {

	if ( 'local' !== suite.env && ! ( await reachable( baseFor( suite ) ) ) ) {
		console.log( `\n──────── ${ suite.name }  SKIPPED — ${ baseFor( suite ) } is not answering` );
		skipped.push( suite.name );
		continue;
	}

	await precondition( suite );

	const code = await run( suite );

	/*
	 *  Exit 2 is a suite saying "not here" rather than "broken".
	 *
	 *  tests/tree/ai-folders.mjs seeds through a blueprint of its own and
	 *  checks it is talking to that site before asserting anything, because a
	 *  Playground booted from another blueprint would otherwise give it
	 *  thirteen confident failures about folders nobody seeded. This runner
	 *  only ever points a suite at one Playground, so that check fires on
	 *  every battery -- and reporting it as FAILED buried a real signal under
	 *  a known one. Skipped is the honest word, and it is already this file's
	 *  convention for its own exit codes.
	 */
	if ( 0 === code ) {
		passed.push( suite.name );
	} else if ( 2 === code ) {
		console.log( `  SKIPPED — the suite says why in its own line above (not its site, no key, Pro inactive)` );
		skipped.push( suite.name );
	} else {
		failed.push( `${ suite.name } (exit ${ code })` );
	}
}

console.log( '\n════════ summary' );
console.log( `  passed   ${ passed.length ? passed.join( ', ' ) : 'none' }` );
if ( skipped.length ) {
	// Loudly, because a skipped suite proves nothing and the whole point of
	// this file is that a run's output means what it says.
	console.log( `  SKIPPED  ${ skipped.join( ', ' ) }  — these did NOT run` );
}
if ( failed.length ) {
	console.log( `  FAILED   ${ failed.join( ', ' ) }` );
}

/*
 *  Exit 1 failed, exit 2 skipped, exit 0 only when everything asked for
 *  actually ran and passed. A green exit code that covered three suites out of
 *  five is the same lie as a test that passes by never running -- and
 *  --allow-skips makes saying so a deliberate act.
 */
if ( failed.length ) {
	process.exit( 1 );
}

if ( skipped.length && ! argv.includes( '--allow-skips' ) ) {
	console.log( '\n  exit 2: suites were skipped. Pass --allow-skips if that is expected.' );
	process.exit( 2 );
}

process.exit( 0 );
