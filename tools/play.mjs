/*
 *  Playground, against the working tree.
 *
 *      node tools/play.mjs              # boot, mounted on this checkout
 *      node tools/play.mjs --port 8899
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
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const SLUG = 'vergelabs-media-library';
const MOUNT = `/wordpress/wp-content/plugins/${ SLUG }`;

const argv = process.argv.slice( 2 );

function flag( name, fallback ) {
	const at = argv.indexOf( name );
	return at >= 0 && argv[ at + 1 ] ? argv[ at + 1 ] : fallback;
}

const PORT = flag( '--port', '8899' );

const blueprint = JSON.parse(
	fs.readFileSync( path.join( ROOT, 'playground', 'blueprint.json' ), 'utf8' )
);

blueprint.steps = blueprint.steps.map( ( step ) =>
	step.step === 'installPlugin'
		? { step: 'activatePlugin', pluginPath: MOUNT }
		: step
);

const tmp = path.join( os.tmpdir(), `vgml-blueprint-${ process.pid }.json` );
fs.writeFileSync( tmp, JSON.stringify( blueprint, null, '\t' ) );

console.log( `\n  mounting ${ ROOT }` );
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
		'--mount-dir', ROOT, MOUNT ],
	{ stdio: 'inherit', shell: true, env: { ...process.env, MSYS_NO_PATHCONV: '1' } }
);

const clean = () => {
	try {
		fs.rmSync( tmp, { force: true } );
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
