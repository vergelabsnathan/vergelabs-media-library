/*
 *  Pro on free 4.0.0, on the MySQL upgrade fixture: three screens as vgmls22.
 *
 *      VGMLPRO_SEATS_KEY=... node tools/upg-pro-shots.mjs
 *
 *  Pro must be installed on /var/www/upg (tools/box-upgrade-site.sh plugin
 *  <zip>); inactive is fine -- the tool activates it and deactivates it again
 *  at the end, which is how story 1.3 leaves the fixture. It then connects
 *  the one-seat test licence, shows the Alt text column for the admin the
 *  way Screen Options would (4.0.0 keeps our columns off the default set;
 *  the option written is the default hidden list minus that one column),
 *  logs in with Playwright, judges each screen on what it shows and writes
 *  the PNGs into docs/superpowers/mocks/shots. Whatever happens it releases
 *  the seat, puts the licence key and state back as found, puts the column
 *  option back and deactivates Pro if it found it inactive -- and the exit
 *  code is 1 unless every one of those steps said so itself. Nothing printed
 *  contains a key. Story 1.3 of plans/suite-readiness.md.
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
const PRO = 'vergelabs-media-library-pro';
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

function wpPlugin( verb ) {
	return box( `cd ${ BOX.wp } && wp plugin ${ verb } ${ PRO } --allow-root 2>&1 | { grep -v '^Deprecated:' || true; }` ).trim();
}

// A step's own last line is its verdict; the exit code is built from these.
const said = [];
function step( label, out, expect ) {
	const line = out.split( /\r?\n/ ).filter( Boolean ).pop() || '';
	const ok = expect.test( line );
	said.push( ok );
	console.log( `${ ok ? 'ok  ' : 'FAIL' }  ${ label }: ${ line }` );
	return ok;
}

const wasActive = '1' === wpEval( `echo defined( "VGMLPRO_VERSION" ) ? 1 : 0;` );
if ( ! wasActive ) {
	if ( ! step( 'activate Pro (found inactive)', wpPlugin( 'activate' ), /activated/i ) ) {
		process.exit( 1 );
	}
}
if ( '1' !== wpEval( `echo function_exists( "vgmlpro_refresh" ) ? 1 : 0;` ) ) {
	console.error( 'Pro is active but includes/licence.php is not loaded (safe mode?); nothing written.' );
	process.exit( 1 );
}

const pass = box( 'cat /root/.upg-admin-pass' ).trim();
const uid = wpEval( `echo get_user_by( "login", "vgmls22" )->ID;` );
const described = wpEval( `global $wpdb; echo (int) $wpdb->get_var( "SELECT attachment_id FROM {$wpdb->prefix}vergeml_ai_index WHERE alt <> '' ORDER BY attachment_id LIMIT 1" );` );

console.log( `admin uid ${ uid }, a described picture: ${ described }, Pro was ${ wasActive ? 'active' : 'inactive' }` );

// What the fixture held before, put back exactly at the end: JSON, or false.
const hadHidden = wpEval( `echo wp_json_encode( get_user_option( "manageuploadcolumnshidden", ${ uid } ) );` );
const hadKey = wpEval( `echo wp_json_encode( get_option( "vgmlpro_licence_key", false ) );` );
const hadState = wpEval( `echo wp_json_encode( get_option( "vgmlpro_licence_state", false ) );` );

const activate = wpEval(
	`update_option( "vgmlpro_licence_key", strtoupper( trim( getenv( "K" ) ) ) ); delete_option( "vgmlpro_licence_state" ); $s = vgmlpro_refresh( "activate" ); echo ( $s["valid"] ? "activated" : "refused: " . $s["reason"] ) . " " . $s["sites_used"] . "/" . $s["sites_allowed"];`,
	{ K: KEY }
);
console.log( `licence: ${ activate }` );

let screensOk = false;
try {
	if ( ! activate.startsWith( 'activated' ) ) {
		throw new Error( 'the licence did not activate' );
	}

	// The list a customer who turned on Alt text would have: the default
	// hidden set minus that column.
	wpEval( `update_user_option( ${ uid }, "manageuploadcolumnshidden", array_values( array_diff( vergeml_list_our_columns(), array( "vgmlpro_source" ) ) ), true );` );

	const browser = await chromium.launch();
	const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
	await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'vgmls22' );
	await page.fill( '#user_pass', pass );
	await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
	if ( page.url().includes( 'wp-login' ) ) {
		throw new Error( 'login failed' );
	}

	// Each look() answers [ passed, note ]: a 200 alone is not the screen.
	const screens = [
		[ 'licence', 'options-general.php?page=vgmlpro-licence', async () => {
			// Exact: "Disconnected." in a notice also contains the word.
			const connected = ( await page.getByText( 'Connected', { exact: true } ).count() ) > 0;
			return [ connected, connected ? 'Connected shown' : 'no Connected label' ];
		} ],
		[ 'media-list', 'upload.php?mode=list', async () => {
			const th = page.locator( 'th#vgmlpro_source' );
			const visible = ( await th.count() ) > 0 && await th.isVisible();
			const cells = await page.locator( 'td.vgmlpro_source' ).allInnerTexts();
			// Pro's label for AI-written, unedited text (provenance.php); the
			// edited and emptied labels also start with "We wrote".
			const ours = cells.filter( ( t ) => /We wrote this/.test( t ) ).length;
			return [ visible && ours > 0, `Alt text column ${ visible ? 'visible' : 'NOT visible' }, ${ cells.length } cells, ${ ours } say "We wrote this"` ];
		} ],
		[ 'attachment', `post.php?post=${ described }&action=edit`, async () => {
			const alt = await page.locator( '#attachment_alt' ).inputValue();
			return [ '' !== alt, alt ? 'alt text present' : 'no alt text' ];
		} ],
	];

	let ok = true;
	for ( const [ name, url, look ] of screens ) {
		const response = await page.goto( `${ BASE }/wp-admin/${ url }`, { waitUntil: 'networkidle' } );
		await page.waitForTimeout( 1200 );
		const file = path.join( OUT, `${ STAMP }-pro-on-4-0-0-${ name }.png` );
		await page.screenshot( { path: file, fullPage: 'media-list' !== name } );
		const [ passed, note ] = await look();
		// A lost session answers 200 too -- with the login form.
		const stayed = page.url().includes( '/wp-admin/' ) && ! page.url().includes( 'wp-login' );
		console.log( `${ name.padEnd( 12 ) } ${ response.status() }  ${ passed && stayed ? 'ok  ' : 'FAIL' }  ${ note }  ${ path.basename( file ) }` );
		ok = ok && 200 === response.status() && passed && stayed;
	}
	await browser.close();
	screensOk = ok;
} catch ( e ) {
	console.error( e.message );
} finally {
	// Each step says what it did in its last line, and that line is judged;
	// the release first, then the options, then the plugin, each its own eval
	// so one failing does not take the others with it.
	step( 'seat released', wpEval(
		`$s = function_exists( "vgmlpro_refresh" ) ? vgmlpro_refresh( "deactivate" ) : array( "valid" => false, "reason" => "pro not loaded", "sites_used" => "?", "sites_allowed" => "?" ); echo ( $s["valid"] ? "released" : "answer: " . $s["reason"] ) . " " . $s["sites_used"] . "/" . $s["sites_allowed"];`
	), /^released/ );

	const put = ( option, had ) => 'false' === had
		? `delete_option( "${ option }" );`
		: `update_option( "${ option }", json_decode( '${ had.replace( /'/g, "\\'" ) }', true ), false );`;
	step( 'licence key and state put back', wpEval(
		`${ put( 'vgmlpro_licence_key', hadKey ) } ${ put( 'vgmlpro_licence_state', hadState ) } delete_transient( "vgmlpro_check_failed" ); echo "put back (key was " . ( ${ 'false' === hadKey ? 'true' : 'false' } ? "absent" : "present" ) . ")";`
	), /^put back/ );

	const restore = 'false' === hadHidden || 'null' === hadHidden
		? `delete_user_option( ${ uid }, "manageuploadcolumnshidden", true );`
		: `update_user_option( ${ uid }, "manageuploadcolumnshidden", json_decode( '${ hadHidden }', true ), true );`;
	step( 'column option put back', wpEval( `${ restore } echo "put back (was ${ hadHidden.replace( /"/g, '\\"' ) })";` ), /^put back/ );

	if ( ! wasActive ) {
		step( 'Pro deactivated again', wpPlugin( 'deactivate' ), /deactivated/i );
	}
}
process.exit( screensOk && said.every( Boolean ) ? 0 : 1 );
