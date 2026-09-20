/*
 *  Pro on free 4.0.0, on the MySQL upgrade fixture: three screens as vgmls22.
 *
 *      VGMLPRO_SEATS_KEY=... node tools/upg-pro-shots.mjs
 *
 *  Pro must already be installed and active on /var/www/upg (tools/
 *  box-upgrade-site.sh plugin <zip>). Activates the one-seat test licence
 *  there, shows the Alt text column for the admin (4.0.0 keeps our columns
 *  off the default set; a person turns it on in Screen Options -- this sets
 *  the same user option), logs in with Playwright, records each screen's
 *  status and writes the PNGs into docs/superpowers/mocks/shots, then always
 *  deactivates the licence, clears the key and puts the column option back.
 *  Exits 1 unless all three screens answered 200. Nothing printed contains a
 *  key. Story 1.3 of plans/suite-readiness.md.
 */
import { execFileSync } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

process.env.MSYS_NO_PATHCONV = '1';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const BOX = { host: '46.225.66.194', key: path.join( os.homedir(), '.ssh', 'hetzner_vgml' ), wp: '/var/www/upg' };
const BASE = 'http://upg.46.225.66.194.nip.io';
const OUT = path.join( ROOT, 'docs', 'superpowers', 'mocks', 'shots' );
const STAMP = new Date().toISOString().slice( 0, 10 );
const KEY = ( process.env.VGMLPRO_SEATS_KEY || '' ).trim().toUpperCase();
const sshArgs = [ '-i', BOX.key, '-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null', '-o', 'LogLevel=ERROR', '-o', 'ConnectTimeout=15' ];

if ( ! /^VGML-[A-Z0-9]+$/.test( KEY ) ) {
	console.error( 'VGMLPRO_SEATS_KEY is not set (or not a key).' );
	process.exit( 1 );
}

function box( script ) {
	return execFileSync( 'ssh', [ ...sshArgs, `root@${ BOX.host }`, 'bash -s' ], { input: script, stdio: 'pipe' } ).toString();
}

// wp eval with the key in the process environment only; the code reads getenv.
function wpEval( php, env = {} ) {
	const prefix = Object.entries( env ).map( ( [ k, v ] ) => `${ k }='${ v }'` ).join( ' ' );
	// Single-quoted for bash, so $s and $wpdb reach PHP untouched.
	return box( `cd ${ BOX.wp } && ${ prefix } wp eval '${ php.replace( /'/g, "'\\''" ) }' --allow-root 2>&1 | { grep -v '^Deprecated:' || true; }` ).trim();
}

const pass = box( 'cat /root/.upg-admin-pass' ).trim();
const uid = wpEval( `echo get_user_by( "login", "vgmls22" )->ID;` );
const described = wpEval( `global $wpdb; echo (int) $wpdb->get_var( "SELECT attachment_id FROM {$wpdb->prefix}vergeml_ai_index WHERE alt <> '' ORDER BY attachment_id LIMIT 1" );` );

console.log( `admin uid ${ uid }, a described picture: ${ described }` );

const hadHidden = wpEval( `echo wp_json_encode( get_user_option( "manageuploadcolumnshidden", ${ uid } ) );` );

const activate = wpEval(
	`update_option( "vgmlpro_licence_key", strtoupper( trim( getenv( "K" ) ) ) ); delete_option( "vgmlpro_licence_state" ); $s = vgmlpro_refresh( "activate" ); echo ( $s["valid"] ? "activated" : "refused: " . $s["reason"] ) . " " . $s["sites_used"] . "/" . $s["sites_allowed"];`,
	{ K: KEY }
);
console.log( `licence: ${ activate }` );

let code = 1;
try {
	if ( ! activate.startsWith( 'activated' ) ) {
		throw new Error( 'the licence did not activate' );
	}

	wpEval( `update_user_option( ${ uid }, "manageuploadcolumnshidden", array(), true );` );

	const browser = await chromium.launch();
	const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
	await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'vgmls22' );
	await page.fill( '#user_pass', pass );
	await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
	if ( page.url().includes( 'wp-login' ) ) {
		throw new Error( 'login failed' );
	}

	const screens = [
		[ 'licence', 'options-general.php?page=vgmlpro-licence', async () => ( await page.locator( 'text=Connected' ).count() ) > 0 ? 'Connected shown' : 'no Connected label' ],
		[ 'media-list', 'upload.php?mode=list', async () => {
			const th = page.locator( 'th#vgmlpro_source' );
			const visible = ( await th.count() ) > 0 && await th.isVisible();
			const cells = await page.locator( 'td.vgmlpro_source' ).allInnerTexts();
			// Pro's own label for AI-written, unedited text (provenance.php).
			const ours = cells.filter( ( t ) => /We wrote/.test( t ) ).length;
			return `Alt text column ${ visible ? 'visible' : 'NOT visible' }, ${ cells.length } cells, ${ ours } say "We wrote"`;
		} ],
		[ 'attachment', `post.php?post=${ described }&action=edit`, async () => ( await page.locator( '#attachment_alt' ).inputValue() ) ? 'alt text present' : 'no alt text' ],
	];

	let ok = true;
	for ( const [ name, url, look ] of screens ) {
		const response = await page.goto( `${ BASE }/wp-admin/${ url }`, { waitUntil: 'networkidle' } );
		await page.waitForTimeout( 1200 );
		const file = path.join( OUT, `${ STAMP }-pro-on-4-0-0-${ name }.png` );
		await page.screenshot( { path: file, fullPage: 'media-list' !== name } );
		const note = await look();
		console.log( `${ name.padEnd( 12 ) } ${ response.status() }  ${ note }  ${ path.basename( file ) }` );
		ok = ok && 200 === response.status();
	}
	await browser.close();
	code = ok ? 0 : 1;
} catch ( e ) {
	console.error( e.message );
} finally {
	const deactivate = wpEval(
		`$s = vgmlpro_refresh( "deactivate" ); echo ( $s["valid"] ? "released" : "answer: " . $s["reason"] ) . " " . $s["sites_used"] . "/" . $s["sites_allowed"]; update_option( "vgmlpro_licence_key", "" ); delete_option( "vgmlpro_licence_state" ); delete_transient( "vgmlpro_check_failed" );`
	);
	console.log( `licence: ${ deactivate }; key cleared` );
	const restore = 'false' === hadHidden || 'null' === hadHidden
		? `delete_user_option( ${ uid }, "manageuploadcolumnshidden", true );`
		: `update_user_option( ${ uid }, "manageuploadcolumnshidden", json_decode( '${ hadHidden }', true ), true );`;
	wpEval( restore );
	console.log( `column option put back (was ${ hadHidden })` );
}
process.exit( code );
