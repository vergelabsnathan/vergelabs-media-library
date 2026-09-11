/*
 *  Uninstall, both ways, on a fresh site with real content.
 *
 *      node tools/uninstall-walk.mjs
 *      VGML_UNINSTALL_MUTATE=1 node tools/uninstall-walk.mjs   # the mutation check: must go red
 *
 *  The whole argument against FileBird is that deleting this plugin does not
 *  cost you your folders. That is a claim, and this is where it is tested:
 *  two Playgrounds in turn, each with three folders (one nested), four
 *  images (three filed), the plugin deactivated and deleted through the
 *  Plugins screen the way a person does it -- which is the path that runs
 *  uninstall.php with WP_UNINSTALL_PLUGIN defined.
 *
 *  Run 1, the default: folders survive as media_category terms, every file
 *  keeps its folder, no attachment goes, the plugin's tables and options are
 *  left for a reinstall; only transients and cron hooks are cleared.
 *  Run 2, "also remove everything": terms, links, options, usermeta and the
 *  four tables gone; attachments and their files untouched. Both: nothing
 *  from this plugin in the debug log.
 *
 *  The plugin is a clean git archive extracted to a temp dir and mounted, so
 *  WordPress deletes a copy and never the checkout. The database is read by
 *  tests/uninstall/probe.php, an mu-plugin mounted beside it, because after
 *  uninstall there is no plugin left to ask and no WP-CLI in a Playground.
 *
 *  Not on the box: deactivating any plugin there fatals in core's FTP class.
 */
import { spawn, execSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const SLUG = 'vergelabs-media-library';
const MUTATE = '1' === process.env.VGML_UNINSTALL_MUTATE;

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? `  -- ${ detail }` : '' }` );
};

const work = fs.mkdtempSync( path.join( os.tmpdir(), 'vgml-uninstall-' ) );

/*
 *  A clean copy of HEAD, not the working tree: the walk proves the plugin
 *  as it would ship, and WordPress deletes this copy at the end of each run.
 */
function freshPlugin( n ) {
	const dir = path.join( work, `run${ n }` );
	fs.mkdirSync( dir, { recursive: true } );
	// Piped, with relative paths only: Windows tar reads "C:" as a host name.
	execSync( `git -C "${ ROOT }" archive HEAD --format=tar --prefix=${ SLUG }/ | tar -x`, { cwd: dir, stdio: 'inherit', shell: true } );

	if ( MUTATE ) {
		// The mutation: the wipe runs whether or not the switch is on. Run 1
		// must then lose its folders, and say so.
		const file = path.join( dir, SLUG, 'uninstall.php' );
		const src = fs.readFileSync( file, 'utf8' );
		const mutated = src.replace( "if ( get_option( 'vergeml_uninstall_wipe' ) ) {", 'if ( true ) {' );
		if ( mutated === src ) {
			throw new Error( 'the mutation found nothing to change in uninstall.php' );
		}
		fs.writeFileSync( file, mutated );
		console.log( '  MUTATED: uninstall.php wipes regardless of the switch' );
	}

	return path.join( dir, SLUG );
}

/*
 *  The probe goes in as a writeFile step, not a mount: Playground keeps its
 *  own mu-plugins (the login among them), and mounting a directory over that
 *  path hides them -- the site then answers plugins.php with the login form.
 */
const spec = JSON.parse( fs.readFileSync( path.join( ROOT, 'tests', 'uninstall', 'blueprint.json' ), 'utf8' ) );
spec.steps.unshift( {
	step: 'writeFile',
	path: '/wordpress/wp-content/mu-plugins/vgml-probe.php',
	data: fs.readFileSync( path.join( ROOT, 'tests', 'uninstall', 'probe.php' ), 'utf8' ),
} );
const blueprint = path.join( work, 'blueprint.json' );
fs.writeFileSync( blueprint, JSON.stringify( spec, null, '	' ) );

function boot( port, pluginDir ) {
	// MSYS_NO_PATHCONV: Git Bash would rewrite /wordpress/... into a Windows path.
	const child = spawn(
		'npx',
		[ '@wp-playground/cli', 'server',
			'--port', String( port ),
			'--blueprint', blueprint,
			'--mount-dir', pluginDir, `/wordpress/wp-content/plugins/${ SLUG }` ],
		{ stdio: [ 'ignore', 'ignore', 'inherit' ], shell: true, env: { ...process.env, MSYS_NO_PATHCONV: '1' } }
	);
	return child;
}

function stop( child ) {
	try {
		if ( 'win32' === process.platform ) {
			execSync( `taskkill /pid ${ child.pid } /T /F`, { stdio: 'ignore' } );
		} else {
			child.kill( 'SIGTERM' );
		}
	} catch {}
}

async function waitFor( page, base ) {
	const deadline = Date.now() + 600000;
	while ( Date.now() < deadline ) {
		try {
			// The plugin's own row, not the URL: the login form's redirect_to
			// carries "plugins.php" as well, and a wait on that lands there.
			const res = await page.goto( `${ base }/wp-admin/plugins.php`, { waitUntil: 'domcontentloaded' } );
			if ( res && res.ok() && await page.$( `#deactivate-${ SLUG }` ) ) {
				return true;
			}
		} catch {}
		await page.waitForTimeout( 5000 );
	}
	return false;
}

async function probe( page, base, action, extra = '' ) {
	const res = await page.request.get( `${ base }/?vgml_probe=${ action }${ extra }` );
	return await res.json();
}

async function run( n, wipe ) {

	const port = 8912 + n;
	const base = `http://127.0.0.1:${ port }`;
	const plugin = freshPlugin( n );

	console.log( `\nrun ${ n }: ${ wipe ? '"also remove everything" on' : 'the default' }  (${ base })` );

	const child = boot( port, plugin );
	const browser = await chromium.launch();
	const page = await ( await browser.newContext( { viewport: { width: 1400, height: 900 } } ) ).newPage();
	page.setDefaultTimeout( 120000 );
	page.on( 'dialog', ( d ) => d.accept() );

	try {
		if ( ! await waitFor( page, base ) ) {
			check( `run ${ n }: the Playground answered`, false, 'nothing on the port within ten minutes' );
			return;
		}

		await probe( page, base, 'set-wipe', `&v=${ wipe ? 1 : 0 }` );
		const before = await probe( page, base, 'state' );

		check( `run ${ n }: the fixture is in place`,
			before.plugin_active && 3 === before.terms.length && 3 === before.links && 4 === before.attachments && 4 === before.files_present && before.transients > 0 && before.options > 0,
			`${ before.terms.length } folders, ${ before.links } filed, ${ before.attachments } attachments, ${ before.options } options, ${ before.transients } transients, tables ${ before.tables.join( '+' ) || 'none' }` );
		check( `run ${ n }: the switch reads ${ wipe ? 'on' : 'off' }`, before.wipe === ( wipe ? '1' : '0' ), `wipe=${ before.wipe }` );

		// Deactivate, then delete, through the Plugins screen. The Delete link
		// is followed as a link rather than clicked: clicked before the ajax
		// script attaches it is a plain link anyway, and the two routes end on
		// different pages. The link's own page is the confirmation form, and
		// submitting it is what runs delete_plugins() -- uninstall.php first,
		// then the files.
		await page.goto( `${ base }/wp-admin/plugins.php`, { waitUntil: 'domcontentloaded' } );
		await page.click( `#deactivate-${ SLUG }` );
		await page.waitForSelector( `#delete-${ SLUG }`, { timeout: 60000 } );
		const href = await page.getAttribute( `#delete-${ SLUG }`, 'href' );
		await page.goto( new URL( href, `${ base }/wp-admin/` ).toString(), { waitUntil: 'domcontentloaded' } );
		await page.click( 'input[type="submit"][name="submit"]' );
		await page.waitForLoadState( 'domcontentloaded' );
		await page.waitForTimeout( 1500 );
		const said = await page.evaluate( () => [ ...document.querySelectorAll( '.notice, #message, .error, .updated' ) ].map( ( n ) => n.textContent.trim() ).join( ' | ' ) );
		if ( process.env.VGML_UNINSTALL_DEBUG ) {
			console.log( '  after delete:', page.url(), '--', said.slice( 0, 200 ) );
		}

		const after = await probe( page, base, 'state' );

		// WordPress empties the directory and then fails to unlink the mount
		// point itself ("Could not fully remove the plugin") -- that last step
		// is the mount, not the plugin. Every file inside has to be gone, and
		// uninstall.php has to have run before the files went.
		const left = fs.existsSync( plugin ) ? fs.readdirSync( plugin ).length : 0;
		check( `run ${ n }: the plugin is gone`, ! after.plugin_active && 0 === left,
			`${ left } entries left in the plugin directory` );

		check( `run ${ n }: no attachment was deleted`, 4 === after.attachments && 4 === after.files_present, `${ after.attachments } attachments, ${ after.files_present } files on disk` );
		check( `run ${ n }: transients and cron hooks are cleared`, 0 === after.transients && 0 === after.cron.length, `${ after.transients } transients, cron ${ after.cron.join( ',' ) || 'none' }` );
		check( `run ${ n }: nothing from this plugin in the debug log`, 0 === after.log_hits.length, after.log_hits.slice( 0, 2 ).join( ' | ' ) );

		if ( ! wipe ) {
			const names = after.terms.map( ( t ) => t.name ).sort().join( ',' );
			const nested = after.terms.find( ( t ) => 'Harbour' === t.name );
			check( 'run 1: the folders survive as media_category terms', 'Harbour,Logos,Photos' === names && nested && nested.parent > 0, `${ names }; Harbour parent ${ nested ? nested.parent : '-' }` );
			check( 'run 1: every filed picture keeps its folder', 3 === after.links, `${ after.links } links` );
			check( 'run 1: the plugin\'s options and tables are left for a reinstall', after.options === before.options && after.tables.join() === before.tables.join(), `${ after.options } of ${ before.options } options, tables ${ after.tables.join( '+' ) || 'none' }` );
		} else {
			check( 'run 2: the folders are gone', 0 === after.terms.length && 0 === after.links, `${ after.terms.length } terms, ${ after.links } links` );
			check( 'run 2: no vergeml option, transient or usermeta remains', 0 === after.options && 0 === after.usermeta, `${ after.options } options, ${ after.usermeta } usermeta` );
			check( 'run 2: no orphan table remains', 0 === after.tables.length, after.tables.join( '+' ) || 'none' );
		}
	} finally {
		await browser.close();
		stop( child );
	}
}

try {
	await run( 1, false );
	if ( ! process.env.VGML_UNINSTALL_ONLY ) {
		await run( 2, true );
	}
} finally {
	try { fs.rmSync( work, { recursive: true, force: true } ); } catch {}
}

const bad = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - bad }/${ results.length } passed\n` );
process.exit( bad ? 1 : 0 );
