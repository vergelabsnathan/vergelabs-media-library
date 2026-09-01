/*
 *  Where the watch gets its versions.
 *
 *    core()               WordPress stable, and the newest beta/RC if one is open
 *    php()                the newest patch of every supported PHP minor
 *    plugin(slug)         wordpress.org plugin info (version, download, changelog)
 *    changelogPage(url, regex)   a version scraped off a public page, for the
 *                         paid plugins that have no API without a licence
 *
 *  Every probe throws when the shape it expects is gone. The watch shows that as
 *  an error line and the workflow fails the run: a probe that quietly returns
 *  the last version it saw is how a break goes unnoticed for a quarter.
 */

import { execFileSync } from 'node:child_process';

const UA = { 'User-Agent': 'Mozilla/5.0 vergelabs-watch (+https://vergelabsmedia.com)' };

async function getJson( url ) {
	const r = await fetch( url, { headers: UA } );
	if ( ! r.ok ) throw new Error( `HTTP ${ r.status } for ${ url }` );
	return r.json();
}

export async function core() {
	const stable = await getJson( 'https://api.wordpress.org/core/version-check/1.7/' );
	const current = stable.offers?.find( ( o ) => o.response === 'upgrade' || o.response === 'latest' )?.current
		?? stable.offers?.[ 0 ]?.current;
	if ( ! current ) throw new Error( 'version-check returned no offers' );

	const beta = await getJson( 'https://api.wordpress.org/core/version-check/1.7/?channel=beta' );
	const pre = ( beta.offers ?? [] )
		.map( ( o ) => o.current )
		.filter( ( v ) => v && /(?:beta|RC)/i.test( v ) && v !== current )
		.sort()
		.pop() ?? null;

	return {
		version: current,
		prerelease: pre,
		download: `https://wordpress.org/wordpress-${ current }.zip`,
		prereleaseDownload: pre ? `https://wordpress.org/wordpress-${ pre }.zip` : null,
	};
}

export async function php() {
	const out = {};
	for ( const major of [ 8 ] ) {
		const j = await getJson( `https://www.php.net/releases/index.php?json&version=${ major }&max=8` );
		for ( const [ v, info ] of Object.entries( j ) ) {
			const minor = v.split( '.' ).slice( 0, 2 ).join( '.' );
			if ( ! out[ minor ] || out[ minor ] < v ) out[ minor ] = v;
			void info;
		}
	}
	if ( ! Object.keys( out ).length ) throw new Error( 'php.net releases returned nothing' );
	return out; // { '8.5': '8.5.4', '8.4': '8.4.12', ... }
}

const stripTags = ( h ) =>
	h.replace( /<li>/g, '\n  - ' ).replace( /<h4>/g, '\n\n' ).replace( /<[^>]+>/g, '' ).replace( /&#8217;/g, "'" ).trim();

export async function plugin( slug ) {
	const url = `https://api.wordpress.org/plugins/info/1.2/?action=plugin_information`
		+ `&request[slug]=${ encodeURIComponent( slug ) }`
		+ `&request[fields][sections]=1&request[fields][active_installs]=1`;
	const j = await getJson( url );
	if ( j.error ) throw new Error( j.error );
	return {
		version: j.version,
		updated: j.last_updated,
		installs: j.active_installs,
		rating: j.rating,
		ratings: j.num_ratings,
		tested: j.tested,
		requires_php: j.requires_php,
		download: j.download_link,
		changelog: stripTags( j.sections?.changelog ?? '' ).slice( 0, 4000 ),
	};
}

export async function changelogPage( url, regex ) {
	const r = await fetch( url, { headers: UA } );
	if ( ! r.ok ) throw new Error( `HTTP ${ r.status } for ${ url }` );
	const html = await r.text();
	const m = html.match( new RegExp( regex, 'i' ) );
	if ( ! m ) throw new Error( `no version matched /${ regex }/ on ${ url } -- check the page by hand` );
	return { version: m[ 1 ], updated: null, installs: null, rating: null, download: null, changelog: '', changelogOnly: true };
}

/*
 *  What the box's stage site already knows. WordPress's own updater reports a
 *  new version for anything installed there -- the paid plugins included,
 *  licence or not -- which makes this the primary signal for WPBakery, Divi,
 *  WP Rocket and Polylang Pro. Throws when the box cannot be reached, and the
 *  watch says so rather than pretending nothing moved.
 */
const homeDir = () => process.env.HOME ?? process.env.USERPROFILE ?? '';

export async function installed( {
	host = process.env.VGML_BOX ?? '46.225.66.194',
	key = process.env.VGML_BOX_KEY ?? `${ homeDir() }/.ssh/hetzner_vgml`,
	site = '/var/www/upd',
} = {} ) {
	const W = 'sudo -u www-data wp --allow-root';
	const cmd = [
		`cd ${ site }`,
		'echo @@PLUGINS', `${ W } plugin list --fields=name,version,update,update_version --format=json 2>/dev/null`,
		'echo', 'echo @@THEMES', `${ W } theme list --fields=name,version,update,update_version --format=json 2>/dev/null`,
		'echo', 'echo @@CORE', `${ W } core check-update --format=json 2>/dev/null`,
		'echo', 'echo @@VERSION', `${ W } core version 2>/dev/null`,
		'echo', 'echo @@END',
	].join( '; ' );

	let raw;
	try {
		raw = execFileSync( 'ssh', [
			'-i', key, '-o', 'StrictHostKeyChecking=no', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=20',
			`root@${ host }`, cmd,
		], { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] } );
	} catch ( e ) {
		throw new Error( `box unreachable: ${ String( e.message ).split( '\n' )[ 0 ].slice( 0, 160 ) }` );
	}

	// wp-cli surrounds its JSON with "Success:" lines and PHP deprecations; a
	// section is the lines between its marker and the next, and its value is
	// the first of those lines that parses.
	const sections = {};
	let current = null;
	for ( const line of raw.split( /\r?\n/ ) ) {
		const m = line.match( /^@@([A-Z]+)\s*$/ );
		if ( m ) { current = m[ 1 ]; sections[ current ] = []; continue; }
		if ( current && line.trim() ) sections[ current ].push( line.trim() );
	}
	const section = ( name ) => {
		if ( ! sections[ name ] ) throw new Error( `box output had no ${ name } section: ` + raw.slice( 0, 200 ) );
		return sections[ name ];
	};
	const jsonLine = ( lines ) => {
		for ( const l of lines ) {
			try { return JSON.parse( l ); } catch { /* not this line */ }
		}
		return null;
	};

	const plugins = jsonLine( section( 'PLUGINS' ) ) ?? [];
	const themes = jsonLine( section( 'THEMES' ) ) ?? [];
	const coreUpdates = jsonLine( section( 'CORE' ) ) ?? []; // "Success: WordPress is at the latest version." parses as nothing
	const coreVersion = section( 'VERSION' ).find( ( l ) => /^\d+\.\d+/.test( l ) ) ?? null;

	const byName = {};
	for ( const p of plugins ) byName[ p.name ] = { kind: 'plugin', version: p.version, update: p.update === 'available' ? p.update_version : null };
	for ( const t of themes ) byName[ t.name ] = { kind: 'theme', version: t.version, update: t.update === 'available' ? t.update_version : null };
	return { byName, core: { version: coreVersion, update: Array.isArray( coreUpdates ) ? coreUpdates[ 0 ]?.version ?? null : null } };
}
