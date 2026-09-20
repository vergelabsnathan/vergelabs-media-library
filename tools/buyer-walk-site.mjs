/*
 *  The buyer walk, site side, on the MySQL fixture upg: story 2.1.
 *
 *      node tools/buyer-walk-site.mjs install    # the zip the buyer downloaded → the box → sha256 → wp plugin install --force --activate
 *      node tools/buyer-walk-site.mjs connect    # the key on Pro's Licence screen as vgmls22 (Playwright), a shot, then verify check
 *      node tools/buyer-walk-site.mjs describe   # "Describe with AI" on one picture: alt/caption/provenance by SQL, the credit line
 *      node tools/buyer-walk-site.mjs restore    # disconnect, key removed, Pro inactive, the picture's three rows put back, diffed
 *
 *  Reads the key from WALK_DIR/walk-key.txt (written by tools/buyer-walk.mjs
 *  buy); the key is never printed. The snapshot to restore from is
 *  /root/walk-0920/pic<N>-before.sql on the box, taken by literal SQL before
 *  the walk (mariadb-dump --hex-blob of the wp_posts, wp_postmeta and
 *  wp_vergeml_ai_index rows). Mirrors tools/upg-pro-shots.mjs.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, unlinkSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

process.env.MSYS_NO_PATHCONV = '1';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const BOX = { host: '46.225.66.194', key: path.join( os.homedir(), '.ssh', 'hetzner_vgml' ), wp: '/var/www/upg' };
const BASE = 'http://upg.46.225.66.194.nip.io';
const SERVICE = 'https://ai.vergelabs.nl';
const PRO = 'vergelabs-media-library-pro';
const OUT = path.join( ROOT, 'docs', 'superpowers', 'mocks', 'shots' );
const STAMP = new Date().toISOString().slice( 0, 10 );
const SCRATCH = process.env.WALK_DIR || path.join( os.tmpdir(), 'vgml-walk' );
const KEY_FILE = path.join( SCRATCH, 'walk-key.txt' );
const PIC = Number( process.env.WALK_PIC || 23 );
const WALK = '/root/walk-0920';
const sshArgs = [ '-i', BOX.key, '-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null', '-o', 'LogLevel=ERROR', '-o', 'ConnectTimeout=15' ];

const stage = process.argv[ 2 ];
const say = ( line ) => console.log( `${ new Date().toISOString().slice( 11, 19 ) }  ${ line }` );

function box( script ) {
	return execFileSync( 'ssh', [ ...sshArgs, `root@${ BOX.host }`, 'bash -s' ], { input: script, stdio: 'pipe' } ).toString();
}
function wpEval( php, env = {} ) {
	const prefix = Object.entries( env ).map( ( [ k, v ] ) => `${ k }='${ v }'` ).join( ' ' );
	return box( `cd ${ BOX.wp } && ${ prefix } wp eval '${ php.replace( /'/g, "'\\''" ) }' --allow-root 2>&1 | { grep -v '^Deprecated:' || true; }` ).trim();
}
function wp( args ) {
	return box( `cd ${ BOX.wp } && wp ${ args } --allow-root 2>&1 | { grep -v '^Deprecated:' || true; }` ).trim();
}
// Literal SQL, never through the plugin: the walk's own discipline.
function sql( q ) {
	return box( `cd ${ BOX.wp } && wp db query ${ JSON.stringify( q ) } --allow-root --skip-column-names 2>&1 | { grep -v '^Deprecated:' || true; }` ).trim();
}
function readKey() {
	if ( ! existsSync( KEY_FILE ) ) throw new Error( `no key file at ${ KEY_FILE }; run buyer-walk.mjs buy first` );
	const key = readFileSync( KEY_FILE, 'utf8' ).trim().toUpperCase();
	if ( ! /^VGML-[A-Z0-9-]+$/.test( key ) ) throw new Error( 'the key file does not hold a key' );
	return key;
}
const mask = ( key ) => `${ key.slice( 0, 9 ) }…${ key.slice( -4 ) }`;

async function verify( key, action ) {
	const res = await fetch( `${ SERVICE }/api/licence/verify`, {
		method: 'POST',
		headers: { 'content-type': 'application/json' },
		body: JSON.stringify( { key, site: BASE, action } ),
	} );
	const body = await res.json();
	return `${ res.status } ${ JSON.stringify( body ) }`;
}

function pictureRows() {
	return {
		alt: sql( `SELECT meta_value FROM wp_postmeta WHERE post_id=${ PIC } AND meta_key='_wp_attachment_image_alt'` ),
		caption: sql( `SELECT post_excerpt FROM wp_posts WHERE ID=${ PIC }` ),
		provenance: sql( `SELECT meta_key, LEFT(meta_value, 300) FROM wp_postmeta WHERE post_id=${ PIC } AND meta_key LIKE '%vgmlpro%'` ),
		index: sql( `SELECT alt, locked, error, described_at FROM wp_vergeml_ai_index WHERE attachment_id=${ PIC }` ),
		metaCount: sql( `SELECT COUNT(*) FROM wp_postmeta WHERE post_id=${ PIC }` ),
	};
}

async function login( page ) {
	const pass = box( 'cat /root/.upg-admin-pass' ).trim();
	await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'vgmls22' );
	await page.fill( '#user_pass', pass );
	await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
	if ( page.url().includes( 'wp-login' ) ) throw new Error( 'login failed' );
}

const shot = async ( page, name, fullPage = true ) => {
	const file = path.join( OUT, `${ STAMP }-buyer-walk-${ name }.png` );
	await page.screenshot( { path: file, fullPage } );
	say( `shot ${ path.basename( file ) }` );
};

try {
	if ( 'install' === stage ) {
		const zip = path.join( SCRATCH, 'walk-pro.zip' );
		if ( ! existsSync( zip ) ) throw new Error( `no zip at ${ zip }` );
		execFileSync( 'scp', [ ...sshArgs, zip, `root@${ BOX.host }:${ WALK }/walk-pro.zip` ], { stdio: 'pipe' } );
		execFileSync( 'scp', [ ...sshArgs, path.join( ROOT, 'tools', 'box-upgrade-site.sh' ), `root@${ BOX.host }:${ WALK }/box-upgrade-site.sh` ], { stdio: 'pipe' } );
		say( `zip on the box: ${ box( `sha256sum ${ WALK }/walk-pro.zip && stat -c %s ${ WALK }/walk-pro.zip` ).replace( /\s+/g, ' ' ).trim() }` );
		say( box( `bash ${ WALK }/box-upgrade-site.sh plugin ${ WALK }/walk-pro.zip 2>&1 | grep -v '^Deprecated' | tail -4` ).trim().replace( /\n/g, '\n          ' ) );
		say( `plugins: ${ wp( 'plugin list --fields=name,status,version --format=csv' ).split( '\n' ).filter( ( l ) => l.includes( 'vergelabs' ) ).join( ' · ' ) }` );
	} else if ( 'connect' === stage ) {
		const key = readKey();
		say( `before: ${ await verify( key, 'check' ) }` );
		const browser = await chromium.launch();
		const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
		await login( page );
		await page.goto( `${ BASE }/wp-admin/options-general.php?page=vgmlpro-licence`, { waitUntil: 'networkidle' } );
		await shot( page, '10-licence-empty' );
		await page.fill( '#vgmlpro_key', key );
		await Promise.all( [ page.waitForNavigation(), page.click( 'button[name="vgmlpro_action"][value="connect"]' ) ] );
		await page.waitForTimeout( 800 );
		const notice = ( await page.locator( '.notice' ).allInnerTexts() ).join( ' | ' ).replace( /\s+/g, ' ' ).trim();
		const connected = ( await page.getByText( 'Connected', { exact: true } ).count() ) > 0;
		const body = ( await page.locator( '.wrap' ).innerText() ).replace( /\s+/g, ' ' ).replace( key, mask( key ) );
		await page.evaluate( ( [ k, m ] ) => { document.body.innerHTML = document.body.innerHTML.split( k ).join( m ); }, [ key, mask( key ) ] );
		await shot( page, '11-licence-connected' );
		say( `licence screen: ${ connected ? 'Connected' : 'NO Connected label' } · notice: ${ notice || '(none)' }` );
		say( `licence screen text: ${ body.slice( 0, 500 ) }` );
		await browser.close();
		say( `after: ${ await verify( key, 'check' ) }` );
		say( `key option: ${ wpEval( `$k = get_option( "vgmlpro_licence_key", "" ); echo $k === "" ? "absent" : "present (" . strlen( $k ) . " chars)";` ) } · state: ${ wpEval( `echo wp_json_encode( get_option( "vgmlpro_licence_state", false ) );` ).replace( /"key[^,}]*/g, '"key":…' ).slice( 0, 300 ) }` );
	} else if ( 'describe' === stage ) {
		const key = readKey();
		const before = pictureRows();
		say( `picture ${ PIC } before: alt "${ before.alt }" · caption "${ before.caption }" · provenance ${ before.provenance || '(none)' } · index ${ before.index } · ${ before.metaCount } meta rows` );
		say( `credits before: ${ await verify( key, 'check' ) }` );
		const logBefore = box( `wc -l < ${ BOX.wp }/wp-content/debug.log 2>/dev/null || echo 0` ).trim();

		const browser = await chromium.launch();
		const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
		await login( page );
		await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'networkidle' } );
		// The row action's own link, nonce and all, off the picture's row.
		const href = await page.locator( `#post-${ PIC } a[href*="vgmlpro_describe"]` ).first().getAttribute( 'href' );
		if ( ! href ) throw new Error( `no "Describe with AI" action on picture ${ PIC }` );
		say( `row action: ${ href.replace( /_wpnonce=[^&]+/, '_wpnonce=…' ) }` );
		const t0 = Date.now();
		await page.goto( href, { waitUntil: 'networkidle' } );
		const ms = Date.now() - t0;
		const back = new URL( page.url() );
		say( `after ${ ms } ms: ${ back.pathname }?${ back.search.replace( /^\?/, '' ).replace( /_wpnonce=[^&]+/, '' ) }` );
		const notice = ( await page.locator( '.notice' ).allInnerTexts() ).join( ' | ' ).replace( /\s+/g, ' ' ).trim();
		say( `Pro's notice: ${ notice || '(none)' }` );
		await page.waitForTimeout( 800 );
		// The cell for our picture, with the column visible the way Screen Options would show it.
		const uid = wpEval( `echo get_user_by( "login", "vgmls22" )->ID;` );
		const hadHidden = wpEval( `echo wp_json_encode( get_user_option( "manageuploadcolumnshidden", ${ uid } ) );` );
		wpEval( `update_user_option( ${ uid }, "manageuploadcolumnshidden", array_values( array_diff( vergeml_list_our_columns(), array( "vgmlpro_source" ) ) ), true );` );
		await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'networkidle' } );
		const cell = ( await page.locator( `#post-${ PIC } td.vgmlpro_source` ).innerText().catch( () => '(no cell)' ) ).replace( /\s+/g, ' ' ).trim();
		const altCell = ( await page.locator( `#post-${ PIC }` ).innerText() ).replace( /\s+/g, ' ' ).trim();
		await page.locator( `#post-${ PIC }` ).scrollIntoViewIfNeeded();
		await shot( page, '12-media-list-described', false );
		await page.goto( `${ BASE }/wp-admin/post.php?post=${ PIC }&action=edit`, { waitUntil: 'networkidle' } );
		await shot( page, '13-attachment-described' );
		const restoreHidden = 'false' === hadHidden || 'null' === hadHidden
			? `delete_user_option( ${ uid }, "manageuploadcolumnshidden", true );`
			: `update_user_option( ${ uid }, "manageuploadcolumnshidden", json_decode( '${ hadHidden }', true ), true );`;
		wpEval( `${ restoreHidden } echo "column option put back";` );
		await browser.close();

		const after = pictureRows();
		say( `Pro's cell: ${ cell }` );
		say( `row: ${ altCell.slice( 0, 300 ) }` );
		say( `picture ${ PIC } after: alt "${ after.alt }" · caption "${ after.caption }" · provenance ${ after.provenance || '(none)' } · index ${ after.index } · ${ after.metaCount } meta rows` );
		say( `credits after: ${ await verify( key, 'check' ) }` );
		const logAfter = box( `wc -l < ${ BOX.wp }/wp-content/debug.log 2>/dev/null || echo 0` ).trim();
		say( `debug.log: ${ logBefore } → ${ logAfter } lines${ logAfter !== logBefore ? '\n' + box( `tail -n +$(( ${ logBefore } + 1 )) ${ BOX.wp }/wp-content/debug.log | head -20` ) : '' }` );
	} else if ( 'restore' === stage ) {
		const key = existsSync( KEY_FILE ) ? readKey() : null;
		// 1. The seat back through Pro's own call, then the key and state gone.
		if ( key ) {
			say( `deactivate: ${ wpEval( `$s = function_exists( "vgmlpro_refresh" ) ? vgmlpro_refresh( "deactivate" ) : array( "valid" => false, "reason" => "pro not loaded", "sites_used" => "?", "sites_allowed" => "?" ); echo ( $s["valid"] ? "released" : "answer: " . $s["reason"] ) . " " . $s["sites_used"] . "/" . $s["sites_allowed"];` ) }` );
			say( `verify after: ${ await verify( key, 'check' ) }` );
		}
		say( `key and state: ${ wpEval( `delete_option( "vgmlpro_licence_key" ); delete_option( "vgmlpro_licence_state" ); delete_transient( "vgmlpro_check_failed" ); echo get_option( "vgmlpro_licence_key", false ) === false ? "absent" : "STILL PRESENT";` ) }` );
		// 2. Pro inactive again, with no key for its hook to release (A-11).
		say( `Pro: ${ wp( `plugin deactivate ${ PRO }` ).split( '\n' ).pop() } → ${ wp( 'plugin list --fields=name,status,version --format=csv' ).split( '\n' ).filter( ( l ) => l.includes( 'pro' ) ).join( '' ) }` );
		// 3. The picture's three rows from the literal-SQL snapshot; then the same dump again, diffed.
		const restore = `cd ${ BOX.wp } && DB=$(wp config get DB_NAME --allow-root 2>/dev/null | tail -1) && U=$(wp config get DB_USER --allow-root 2>/dev/null | tail -1) && export MYSQL_PWD=$(wp config get DB_PASSWORD --allow-root 2>/dev/null | tail -1) \\
  && mariadb -u $U $DB -e "DELETE FROM wp_posts WHERE ID=${ PIC }; DELETE FROM wp_postmeta WHERE post_id=${ PIC }; DELETE FROM wp_vergeml_ai_index WHERE attachment_id=${ PIC };" \\
  && mariadb -u $U $DB < ${ WALK }/pic${ PIC }-before.sql \\
  && D="mariadb-dump -u $U --compact --no-create-info --skip-extended-insert --order-by-primary --hex-blob $DB" && {
  $D wp_posts --where="ID=${ PIC }"; $D wp_postmeta --where="post_id=${ PIC }"; $D wp_vergeml_ai_index --where="attachment_id=${ PIC }";
} > ${ WALK }/pic${ PIC }-after.sql && diff ${ WALK }/pic${ PIC }-before.sql ${ WALK }/pic${ PIC }-after.sql && echo "0 differences"`;
		say( `rows put back: ${ box( restore ).trim().split( '\n' ).pop() }` );
		const rows = pictureRows();
		say( `picture ${ PIC } now: alt "${ rows.alt }" · caption "${ rows.caption }" · provenance ${ rows.provenance || '(none)' } · ${ rows.metaCount } meta rows` );
		say( `vergeml_ai: ${ wp( 'option get vergeml_ai --format=json' ) }` );
		say( `debug.log: ${ box( `wc -l < ${ BOX.wp }/wp-content/debug.log 2>/dev/null || echo 0` ).trim() } lines; Pro lines today: ${ box( `grep -c "${ PRO }" ${ BOX.wp }/wp-content/debug.log || true` ).trim() }` );
		if ( existsSync( KEY_FILE ) ) {
			unlinkSync( KEY_FILE );
			say( 'walk-key.txt deleted' );
		}
	} else {
		console.error( 'stage: install | connect | describe | restore' );
		process.exitCode = 2;
	}
} catch ( e ) {
	say( `FAIL ${ e.message }` );
	process.exitCode = 1;
}
