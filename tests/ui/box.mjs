import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/*
 *  A PHP fixture on the box, from a spec.
 *
 *  The specs plant what they can through the plugin's own routes. What no
 *  route writes -- the talk state's questions, a picture's alt inside the
 *  writing flag -- is planted by a PHP file shipped over SSH and run with
 *  `wp eval-file`, the way tools/verify.mjs runs every PHP suite, and put
 *  back the same way. The box map is verify.mjs's; a base URL that is not a
 *  listed box gets `null`, and the spec that needs one skips.
 */

const BOXES = {
	'46.225.66.194': { key: '~/.ssh/hetzner_vgml', wp: '/var/www/wp' },
};

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..', '..' );

export function boxFor( baseURL ) {
	const host = String( baseURL || '' ).replace( /^https?:\/\//, '' ).replace( /\/.*$/, '' );
	return BOXES[ host ] ? { host, ...BOXES[ host ] } : null;
}

/** Ship `file` (repo-relative) and run it with the given environment; returns stdout, or throws with stderr. */
export function boxPhp( baseURL, file, env = {} ) {
	const box = boxFor( baseURL );
	if ( ! box ) {
		throw new Error( `${ baseURL } is not a box tests/ui/box.mjs knows` );
	}
	const ssh = [ '-i', box.key, '-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null' ];
	const remote = `/tmp/${ path.basename( file ) }`;
	const copy = spawnSync( 'scp', [ ...ssh, path.join( ROOT, file ), `root@${ box.host }:${ remote }` ], { encoding: 'utf8' } );
	if ( copy.status !== 0 ) {
		throw new Error( `could not copy ${ file } to the box: ${ copy.stderr }` );
	}
	const vars = Object.entries( env ).map( ( [ k, v ] ) => `${ k }=${ JSON.stringify( String( v ) ) }` ).join( ' ' );
	const run = spawnSync( 'ssh', [ ...ssh, `root@${ box.host }`, `cd ${ box.wp } && ${ vars } wp eval-file ${ remote } --allow-root --skip-themes 2>&1 | grep -vE '^Deprecated:|zion'; exit \${PIPESTATUS[0]}` ], { encoding: 'utf8' } );
	if ( run.status !== 0 ) {
		throw new Error( `${ file } exited ${ run.status }: ${ run.stdout }${ run.stderr }` );
	}
	return run.stdout;
}
