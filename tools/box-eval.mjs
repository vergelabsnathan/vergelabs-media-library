/*
 *  Run one PHP file on the box through wp eval-file, on either library.
 *
 *      node tools/box-eval.mjs tools/box-ask-split.php              (the tech library, /var/www/wp)
 *      node tools/box-eval.mjs tools/box-ask-split.php --site shop  (the shop, /var/www/ms2 as www-data)
 *
 *  The file is copied fresh, run, and removed. Environment for the script goes
 *  through --env NAME=value (repeatable). Prints what the script prints.
 *  --copy local:remote (repeatable) puts another file on the box first and
 *  removes it after -- a truth file for tools/box-truth-score.php:
 *
 *      node tools/box-eval.mjs tools/box-truth-score.php --copy tests/tree/truth-tech.json:/tmp/vgml-truth.json --env VGML_TRUTH=/tmp/vgml-truth.json
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';
import os from 'node:os';

const SITES = {
	tech: { wp: '/var/www/wp', url: '', as: '' },
	shop: { wp: '/var/www/ms2', url: 'http://ms2.46.225.66.194.nip.io', as: 'www-data' },
};
const BOX = { host: '46.225.66.194', key: path.join( os.homedir(), '.ssh', 'hetzner_vgml' ) };

const args = process.argv.slice( 2 );
const file = args.find( ( a ) => ! a.startsWith( '--' ) );
const site = SITES[ args.includes( '--site' ) ? args[ args.indexOf( '--site' ) + 1 ] : 'tech' ];
const env  = [];
const copies = [];
for ( let i = 0; i < args.length; i++ ) {
	if ( '--env' === args[ i ] ) {
		env.push( args[ ++i ] );
	} else if ( '--copy' === args[ i ] ) {
		const [ local, remoteTo ] = args[ ++i ].split( ':' );
		copies.push( { local, remote: remoteTo } );
	}
}

if ( ! file || ! site ) {
	console.error( 'usage: node tools/box-eval.mjs <file.php> [--site tech|shop] [--env NAME=value]' );
	process.exit( 1 );
}

const remote = `/tmp/vgml-eval-${ path.basename( file ) }`;
const sshArgs = [ '-i', BOX.key, '-o', 'StrictHostKeyChecking=no', '-o', 'ConnectTimeout=15' ];

execFileSync( 'scp', [ ...sshArgs, path.resolve( file ), `root@${ BOX.host }:${ remote }` ], { stdio: 'pipe' } );
for ( const c of copies ) {
	execFileSync( 'scp', [ ...sshArgs, path.resolve( c.local ), `root@${ BOX.host }:${ c.remote }` ], { stdio: 'pipe' } );
	if ( site.as ) {
		execFileSync( 'ssh', [ ...sshArgs, `root@${ BOX.host }`, `chmod a+r ${ c.remote }` ], { stdio: 'pipe' } );
	}
}

const wp = ( site.as ? `sudo -u ${ site.as } env ${ env.join( ' ' ) } wp` : `env ${ env.join( ' ' ) } wp` ) + ( site.url ? ` --url=${ site.url }` : '' );
const script = `
	set -e
	cd ${ site.wp }
	${ wp } eval-file ${ remote } --user=1 --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
	rm -f ${ remote } ${ copies.map( ( c ) => c.remote ).join( ' ' ) }
`;

try {
	process.stdout.write( execFileSync( 'ssh', [ ...sshArgs, `root@${ BOX.host }`, 'bash -s' ], { input: script, stdio: 'pipe', maxBuffer: 32 * 1024 * 1024 } ).toString() );
} catch ( err ) {
	console.error( ( err.stderr || err.stdout || '' ).toString().trim() || err.message );
	process.exit( 1 );
}
