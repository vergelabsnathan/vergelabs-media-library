/*
 *  The buyer walk, site side, on the MySQL fixture upg: story 2.1.
 *
 *      node tools/buyer-walk-site.mjs snapshot   # before anything: the picture's three rows dumped by literal SQL to the run's directory on the box
 *      node tools/buyer-walk-site.mjs install    # the zip the buyer downloaded → the box → sha256 → wp plugin install --force --activate
 *      node tools/buyer-walk-site.mjs connect    # the key on Pro's Licence screen as vgmls22 (Playwright), a shot, then verify check
 *      node tools/buyer-walk-site.mjs describe   # "Describe with AI" on one picture: alt/caption/provenance by SQL, the credit line
 *      node tools/buyer-walk-site.mjs restore    # Disconnect on the Licence screen, Pro inactive, the picture's three rows put back, diffed
 *
 *  Reads the key from WALK_DIR/walk-key.txt (written by tools/buyer-walk.mjs
 *  buy); the key is never printed. One directory on the box per walk,
 *  /root/$WALK_RUN (walk-0920 unless set), holds the zip, the script and the
 *  snapshot pic<N>-before.sql that `snapshot` writes and `restore` reads
 *  (mariadb-dump --hex-blob of the wp_posts, wp_postmeta and
 *  wp_vergeml_ai_index rows, the same command both ways so the diff compares
 *  rows, not formatting). Mirrors tools/upg-pro-shots.mjs.
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
// One directory on the box per walk, holding the zip, the script and the snapshot: WALK_RUN=walk-0921.
const WALK = `/root/${ process.env.WALK_RUN || 'walk-0920' }`;
const sshArgs = [ '-i', BOX.key, '-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null', '-o', 'LogLevel=ERROR', '-o', 'ConnectTimeout=15' ];

const stage = process.argv[ 2 ];
const say = ( line ) => console.log( `${ new Date().toISOString().slice( 11, 19 ) }  ${ line }` );

function box( script ) {
	return execFileSync( 'ssh', [ ...sshArgs, `root@${ BOX.host }`, 'bash -s' ], { input: script, stdio: 'pipe' } ).toString();
}
function wpEval( php ) {
	return box( `cd ${ BOX.wp } && wp eval '${ php.replace( /'/g, "'\\''" ) }' --allow-root 2>&1 | { grep -v '^Deprecated:' || true; }` ).trim();
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
const logLines = () => box( `wc -l < ${ BOX.wp }/wp-content/debug.log 2>/dev/null || echo 0` ).trim();

// The three rows as one dump, --hex-blob so the packed embedding survives; the
// snapshot and the after-dump are this same command, so a diff means rows.
const DB_ENV = `cd ${ BOX.wp } && DB=$(wp config get DB_NAME --allow-root 2>/dev/null | tail -1) && U=$(wp config get DB_USER --allow-root 2>/dev/null | tail -1) && export MYSQL_PWD=$(wp config get DB_PASSWORD --allow-root 2>/dev/null | tail -1)`;
const DUMP = `D="mariadb-dump -u $U --compact --no-create-info --skip-extended-insert --order-by-primary --hex-blob $DB" && { $D wp_posts --where="ID=${ PIC }"; $D wp_postmeta --where="post_id=${ PIC }"; $D wp_vergeml_ai_index --where="attachment_id=${ PIC }"; }`;
const BEFORE = `${ WALK }/pic${ PIC }-before.sql`;

async function verify( key, action ) {
	const res = await fetch( `${ SERVICE }/api/licence/verify`, {
		method: 'POST',
		headers: { 'content-type': 'application/json' },
		body: JSON.stringify( { key, site: BASE, action } ),
	} );
	const body = await res.json();
	return { line: `${ res.status } ${ JSON.stringify( body ) }`, body };
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
	if ( 'snapshot' === stage ) {
		// Refused over an existing one: a snapshot taken after the describe would be the wrong baseline.
		if ( 'yes' === box( `test -e ${ BEFORE } && echo yes || echo no` ).trim() ) throw new Error( `${ BEFORE } exists already; a new run needs its own WALK_RUN` );
		const out = box( `mkdir -p ${ WALK } && ${ DB_ENV } && ${ DUMP } > ${ BEFORE } && echo "$(grep -c '^INSERT' ${ BEFORE }) rows, sha256 $(sha256sum ${ BEFORE } | cut -c1-12)"` ).trim();
		say( `snapshot ${ BEFORE }: ${ out }` );
		const rows = pictureRows();
		say( `picture ${ PIC }: alt "${ rows.alt }" · caption "${ rows.caption }" · provenance ${ rows.provenance || '(none)' } · index ${ rows.index } · ${ rows.metaCount } meta rows` );
	} else if ( 'install' === stage ) {
		const zip = path.join( SCRATCH, 'walk-pro.zip' );
		if ( ! existsSync( zip ) ) throw new Error( `no zip at ${ zip }` );
		box( `mkdir -p ${ WALK }` );
		execFileSync( 'scp', [ ...sshArgs, zip, `root@${ BOX.host }:${ WALK }/walk-pro.zip` ], { stdio: 'pipe' } );
		execFileSync( 'scp', [ ...sshArgs, path.join( ROOT, 'tools', 'box-upgrade-site.sh' ), `root@${ BOX.host }:${ WALK }/box-upgrade-site.sh` ], { stdio: 'pipe' } );
		say( `zip on the box: ${ box( `sha256sum ${ WALK }/walk-pro.zip && stat -c %s ${ WALK }/walk-pro.zip` ).replace( /\s+/g, ' ' ).trim() }` );
		say( box( `bash ${ WALK }/box-upgrade-site.sh plugin ${ WALK }/walk-pro.zip 2>&1 | grep -v '^Deprecated' | tail -4` ).trim().replace( /\n/g, '\n          ' ) );
		say( `plugins: ${ wp( 'plugin list --fields=name,status,version --format=csv' ).split( '\n' ).filter( ( l ) => l.includes( 'vergelabs' ) ).join( ' · ' ) }` );
	} else if ( 'connect' === stage ) {
		const key = readKey();
		say( `before: ${ ( await verify( key, 'check' ) ).line }` );
		const browser = await chromium.launch();
		try {
			const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
			await login( page );
			await page.goto( `${ BASE }/wp-admin/options-general.php?page=vgmlpro-licence`, { waitUntil: 'networkidle' } );
			await shot( page, '10-licence-empty' );
			await page.fill( '#vgmlpro_key', key );
			await Promise.all( [ page.waitForNavigation(), page.click( 'button[name="vgmlpro_action"][value="connect"]' ) ] );
			await page.waitForTimeout( 800 );
			const notice = ( await page.locator( '.notice' ).allInnerTexts() ).join( ' | ' ).replace( /\s+/g, ' ' ).trim();
			const connected = ( await page.getByText( 'Connected', { exact: true } ).count() ) > 0;
			const body = ( await page.locator( '.wrap' ).innerText() ).replace( /\s+/g, ' ' ).split( key ).join( mask( key ) );
			await page.evaluate( ( [ k, m ] ) => { document.body.innerHTML = document.body.innerHTML.split( k ).join( m ); }, [ key, mask( key ) ] );
			await shot( page, '11-licence-connected' );
			say( `licence screen: ${ connected ? 'Connected' : 'NO Connected label' } · notice: ${ notice || '(none)' }` );
			say( `licence screen text: ${ body.slice( 0, 500 ) }` );
		} finally {
			await browser.close();
		}
		say( `after: ${ ( await verify( key, 'check' ) ).line }` );
		say( `key option: ${ wpEval( `$k = get_option( "vgmlpro_licence_key", "" ); echo $k === "" ? "absent" : "present (" . strlen( $k ) . " chars)";` ) } · state: ${ wpEval( `echo wp_json_encode( get_option( "vgmlpro_licence_state", false ) );` ).replace( /"key[^,}]*/g, '"key":…' ).slice( 0, 300 ) }` );
	} else if ( 'describe' === stage ) {
		const key = readKey();
		const before = pictureRows();
		say( `picture ${ PIC } before: alt "${ before.alt }" · caption "${ before.caption }" · provenance ${ before.provenance || '(none)' } · index ${ before.index } · ${ before.metaCount } meta rows` );
		// One describe, one credit: a picture Pro has already written is not described again.
		if ( before.provenance ) throw new Error( `picture ${ PIC } already carries Pro's provenance; the walk describes once` );
		const creditsBefore = await verify( key, 'check' );
		say( `credits before: ${ creditsBefore.line }` );
		const logBefore = logLines();

		const browser = await chromium.launch();
		const uid = wpEval( `echo get_user_by( "login", "vgmls22" )->ID;` );
		const hadHidden = wpEval( `echo wp_json_encode( get_user_option( "manageuploadcolumnshidden", ${ uid } ) );` );
		let cell = '(not read)';
		let altCell = '(not read)';
		try {
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
			wpEval( `update_user_option( ${ uid }, "manageuploadcolumnshidden", array_values( array_diff( vergeml_list_our_columns(), array( "vgmlpro_source" ) ) ), true );` );
			await page.goto( `${ BASE }/wp-admin/upload.php?mode=list`, { waitUntil: 'networkidle' } );
			cell = ( await page.locator( `#post-${ PIC } td.vgmlpro_source` ).innerText().catch( () => '(no cell)' ) ).replace( /\s+/g, ' ' ).trim();
			altCell = ( await page.locator( `#post-${ PIC }` ).innerText() ).replace( /\s+/g, ' ' ).trim();
			await page.locator( `#post-${ PIC }` ).scrollIntoViewIfNeeded();
			await shot( page, '12-media-list-described', false );
			await page.goto( `${ BASE }/wp-admin/post.php?post=${ PIC }&action=edit`, { waitUntil: 'networkidle' } );
			await shot( page, '13-attachment-described' );
		} finally {
			// The column option and the browser, whatever happened above.
			const restoreHidden = 'false' === hadHidden || 'null' === hadHidden
				? `delete_user_option( ${ uid }, "manageuploadcolumnshidden", true );`
				: `update_user_option( ${ uid }, "manageuploadcolumnshidden", json_decode( '${ hadHidden }', true ), true );`;
			say( wpEval( `${ restoreHidden } echo "column option put back";` ) );
			await browser.close();
		}

		const after = pictureRows();
		say( `Pro's cell: ${ cell }` );
		say( `row: ${ altCell.slice( 0, 300 ) }` );
		say( `picture ${ PIC } after: alt "${ after.alt }" · caption "${ after.caption }" · provenance ${ after.provenance || '(none)' } · index ${ after.index } · ${ after.metaCount } meta rows` );
		const creditsAfter = await verify( key, 'check' );
		say( `credits after: ${ creditsAfter.line }` );
		const logAfter = logLines();
		say( `debug.log: ${ logBefore } → ${ logAfter } lines${ logAfter !== logBefore ? '\n' + box( `tail -n +$(( ${ logBefore } + 1 )) ${ BOX.wp }/wp-content/debug.log | head -20` ) : '' }` );
		// The stage's own verdict: a new alt, and exactly one credit gone.
		const spent = Number( creditsBefore.body.credits_remaining ) - Number( creditsAfter.body.credits_remaining );
		const wrote = after.alt !== before.alt && ! /^Mock alt for/.test( after.alt );
		say( `verdict: alt ${ wrote ? 'changed' : 'NOT changed' } · credits ${ creditsBefore.body.credits_remaining } → ${ creditsAfter.body.credits_remaining } (${ 1 === spent ? '−1' : 'NOT −1: ' + spent })` );
		if ( ! wrote || 1 !== spent ) process.exitCode = 1;
	} else if ( 'restore' === stage ) {
		const key = existsSync( KEY_FILE ) ? readKey() : null;
		// The snapshot first: nothing is deleted until the file to put back from is there.
		if ( 'yes' !== box( `test -s ${ BEFORE } && echo yes || echo no` ).trim() ) throw new Error( `no snapshot at ${ BEFORE }; nothing touched` );
		const logBefore = logLines();
		// 1. The seat back the way a buyer frees it: Disconnect on the Licence screen. The
		//    handler tells the server first, then forgets the key and state (settings.php).
		const proActive = wp( 'plugin list --fields=name,status --format=csv' ).split( '\n' ).some( ( l ) => l.startsWith( `${ PRO },active` ) );
		const keyPresent = 'present' === wpEval( `echo get_option( "vgmlpro_licence_key", "" ) === "" ? "absent" : "present";` );
		if ( proActive && keyPresent ) {
			const browser = await chromium.launch();
			try {
				const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
				await login( page );
				await page.goto( `${ BASE }/wp-admin/options-general.php?page=vgmlpro-licence`, { waitUntil: 'networkidle' } );
				await Promise.all( [ page.waitForNavigation(), page.click( 'button[name="vgmlpro_action"][value="disconnect"]' ) ] );
				await page.waitForTimeout( 800 );
				const notice = ( await page.locator( '.notice' ).allInnerTexts() ).join( ' | ' ).replace( /\s+/g, ' ' ).trim();
				say( `Disconnect pressed → ${ new URL( page.url() ).searchParams.get( 'vgmlpro' ) || '(no vgmlpro flag)' } · notice: ${ notice || '(none)' }` );
				await shot( page, '14-licence-disconnected' );
			} finally {
				await browser.close();
			}
		} else {
			say( `Disconnect skipped: Pro ${ proActive ? 'active' : 'inactive' }, key ${ keyPresent ? 'present' : 'absent' }` );
		}
		// The seat is judged by the service, never by what the site believes.
		if ( key ) {
			const { line, body } = await verify( key, 'check' );
			say( `verify after: ${ line }` );
			if ( 0 !== Number( body.sites_used ) ) say( `SEAT STILL HELD: sites_used ${ body.sites_used }` );
		} else {
			say( 'verify after: no key file, the seat is not checked here' );
		}
		say( `key and state: ${ wpEval( `delete_option( "vgmlpro_licence_key" ); delete_option( "vgmlpro_licence_state" ); delete_transient( "vgmlpro_check_failed" ); echo get_option( "vgmlpro_licence_key", false ) === false ? "absent" : "STILL PRESENT";` ) }` );
		// 2. Pro inactive again, with no key for its hook to release (A-11).
		say( `Pro: ${ wp( `plugin deactivate ${ PRO }` ).split( '\n' ).pop() } → ${ wp( 'plugin list --fields=name,status,version --format=csv' ).split( '\n' ).filter( ( l ) => l.startsWith( PRO ) ).join( '' ) }` );
		// 3. The picture's three rows from the literal-SQL snapshot; then the same dump again, diffed.
		//    The diff runs outside the chain so its lines are printed either way.
		const restore = `${ DB_ENV } \\
  && mariadb -u $U $DB -e "DELETE FROM wp_posts WHERE ID=${ PIC }; DELETE FROM wp_postmeta WHERE post_id=${ PIC }; DELETE FROM wp_vergeml_ai_index WHERE attachment_id=${ PIC };" \\
  && mariadb -u $U $DB < ${ BEFORE } \\
  && ${ DUMP } > ${ WALK }/pic${ PIC }-after.sql \\
  && { diff ${ BEFORE } ${ WALK }/pic${ PIC }-after.sql && echo "0 differences" || echo "DIFFERENCES (rc $?)"; }`;
		say( `rows put back: ${ box( restore ).trim().split( '\n' ).slice( -12 ).join( '\n          ' ) }` );
		const rows = pictureRows();
		say( `picture ${ PIC } now: alt "${ rows.alt }" · caption "${ rows.caption }" · provenance ${ rows.provenance || '(none)' } · ${ rows.metaCount } meta rows` );
		say( `vergeml_ai: ${ wp( 'option get vergeml_ai --format=json' ) }` );
		const logAfter = logLines();
		say( `debug.log: ${ logBefore } → ${ logAfter } lines; new Pro lines: ${ logAfter === logBefore ? 0 : box( `tail -n +$(( ${ logBefore } + 1 )) ${ BOX.wp }/wp-content/debug.log | grep -c "${ PRO }" || true` ).trim() }` );
		if ( existsSync( KEY_FILE ) ) {
			unlinkSync( KEY_FILE );
			say( 'walk-key.txt deleted' );
		}
	} else {
		console.error( 'stage: snapshot | install | connect | describe | restore' );
		process.exitCode = 2;
	}
} catch ( e ) {
	// A failed box command: its own output, not the ssh argv.
	const out = [ e.stdout, e.stderr ].filter( Boolean ).map( String ).join( '\n' ).trim();
	say( `FAIL ${ e.stdout || e.stderr ? `box command failed (exit ${ e.status })\n${ out.slice( -1500 ) }` : e.message }` );
	process.exitCode = 1;
}
