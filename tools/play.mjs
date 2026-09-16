/*
 *  Playground, against the working tree.
 *
 *      node tools/play.mjs              # boot, mounted on this checkout
 *      node tools/play.mjs --port 8899
 *      node tools/play.mjs --plant 30 --perf   # the Fill step's questions planted, REST timing headers on
 *
 *  There are two ways to put this plugin into Playground and they are not
 *  interchangeable.
 *
 *  `playground/blueprint.json` installs the committed zip from the repo's raw
 *  URL. That is the shareable link -- somebody opens it and gets the plugin
 *  without a checkout -- and it can only ever be as current as the last
 *  `node tools/deploy.mjs`.
 *
 *  Locally that step is worse than useless: installPlugin and --mount-dir
 *  collide ("Device or resource busy"), because the mount is already sitting
 *  where the install wants to write. And installing a zip means testing the
 *  zip, not the file just edited -- which is exactly how a session went by
 *  rebuilding a nav item that had been correct the whole time.
 *
 *  So this takes the same blueprint, swaps installPlugin for activatePlugin
 *  against the mount, and leaves everything else -- the demo folders, the
 *  sample images, the second author -- exactly as the shared link has it.
 *  One blueprint, so the two cannot drift.
 *
 *  Browse 127.0.0.1, never localhost: WordPress builds URLs from siteurl, and
 *  the other name fails every nonce with "the link you followed has expired".
 */
import { spawn, execSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

let ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const SLUG = 'vergelabs-media-library';
const MOUNT = `/wordpress/wp-content/plugins/${ SLUG }`;

const argv = process.argv.slice( 2 );

function flag( name, fallback ) {
	const at = argv.indexOf( name );
	return at >= 0 && argv[ at + 1 ] ? argv[ at + 1 ] : fallback;
}

const PORT = flag( '--port', '8899' );

/*
 *  On Windows the checkout lives under a path with spaces and an emoji, and
 *  cmd.exe splits the one and mangles the other on the way to Playground
 *  ("Host path does not exist"). The volume's 8.3 short name for the same
 *  directory is plain ASCII with no spaces, and points at the same files.
 */
if ( process.platform === 'win32' ) {
	try {
		const short = execSync( `for %I in ("${ ROOT }") do @echo %~sI`, { shell: 'cmd.exe', encoding: 'utf8' } ).trim().split( /\r?\n/ ).pop();
		if ( short && ! /\s/.test( short ) ) {
			ROOT = short;
		}
	} catch ( e ) { /* keep the long path */ }
}

const blueprint = JSON.parse(
	fs.readFileSync( path.join( ROOT, 'playground', 'blueprint.json' ), 'utf8' )
);

blueprint.steps = blueprint.steps.map( ( step ) =>
	step.step === 'installPlugin'
		? { step: 'activatePlugin', pluginPath: MOUNT }
		: step
);

/*
 *  --plant [n]: the Fill step's questions, planted at boot the way
 *  tests/ui/fill-fixture.php plants them on the box over SSH -- Playground
 *  has no SSH, and no described library, so the fixture makes fake described
 *  pictures first (VGML_SEED_FAKE). n questions, the first with 50 pictures:
 *  what folders.spec times on this site, the plugin's own cost of an answer.
 */
const PLANT = argv.includes( '--plant' ) ? Number( flag( '--plant', '30' ) ) || 30 : 0;
if ( PLANT ) {
	blueprint.steps.push( {
		step: 'runPHP',
		code: `<?php require_once '/wordpress/wp-load.php'; putenv( 'VGML_MODE=plant' ); putenv( 'VGML_COUNT=${ PLANT }' ); putenv( 'VGML_Q1=50' ); putenv( 'VGML_SEED_FAKE=1' ); require '${ MOUNT }/tests/ui/fill-fixture.php';`,
	} );
}

/*
 *  --perf: tests/perf/mu-perf.php as a must-use plugin, so every REST answer
 *  carries X-Vgml-Queries and X-Vgml-Handler-Ms here as it does on the box.
 *  Alone in a folder of its own: the other mu-*.php beside it change what
 *  the plugin does.
 */
const muDir = path.join( os.tmpdir(), `vgml-mu-${ process.pid }` );
if ( argv.includes( '--perf' ) ) {
	fs.mkdirSync( muDir, { recursive: true } );
	fs.copyFileSync( path.join( ROOT, 'tests', 'perf', 'mu-perf.php' ), path.join( muDir, 'mu-perf.php' ) );
}

const tmp = path.join( os.tmpdir(), `vgml-blueprint-${ process.pid }.json` );
fs.writeFileSync( tmp, JSON.stringify( blueprint, null, '\t' ) );

console.log( `\n  mounting ${ ROOT }` );
if ( PLANT ) {
	console.log( `  planting ${ PLANT } questions on fake described pictures` );
}
console.log( `  open http://127.0.0.1:${ PORT }  -- not localhost\n` );

/*
 *  MSYS_NO_PATHCONV stops Git Bash rewriting /wordpress/... into
 *  C:/Program Files/Git/wordpress/... on the way past.
 */
const child = spawn(
	'npx',
	[ '@wp-playground/cli', 'server',
		'--port', PORT,
		'--blueprint', tmp,
		'--mount-dir', ROOT, MOUNT,
		...( argv.includes( '--perf' ) ? [ '--mount-dir', muDir, '/wordpress/wp-content/mu-plugins' ] : [] ) ],
	{ stdio: 'inherit', shell: true, env: { ...process.env, MSYS_NO_PATHCONV: '1' } }
);

const clean = () => {
	try {
		fs.rmSync( tmp, { force: true } );
		fs.rmSync( muDir, { recursive: true, force: true } );
	} catch {}
};

child.on( 'exit', ( code ) => {
	clean();
	process.exit( code ?? 0 );
} );

process.on( 'SIGINT', () => {
	clean();
	child.kill( 'SIGINT' );
} );
