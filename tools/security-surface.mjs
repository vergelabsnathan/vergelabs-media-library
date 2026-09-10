/**
 *  The security surface, read out of the code.
 *
 *      node tools/security-surface.mjs            # write docs/security-surface.md
 *      node tools/security-surface.mjs --check    # fail if the file has drifted
 *      node tools/security-surface.mjs --json     # the model, for other tools
 *
 *  Phase 5.1 of plans/four-yesses.md asks for one table holding every way into
 *  this plugin: REST route, AJAX action, admin_post handler, cron hook, a meta
 *  field core exposes over REST on our behalf, and any filter or action whose
 *  callback reads the request. Remembered lists go stale the week they are
 *  written, so this reads the source instead and the suite regenerates it, which
 *  means a new route that nobody documented turns the gate red.
 *
 *  Only what ships is scanned. The file set is `git ls-files` minus the same
 *  SKIP lists tools/deploy.mjs uses, so tests/ and tools/ -- which never run on
 *  a site -- cannot pad the surface or hide a hole in it.
 *
 *  What the analyser can and cannot see, stated plainly because a reader of the
 *  table is entitled to know:
 *
 *    - It resolves a callback by name, by closure, and through a `$can`-style
 *      closure variable assigned in the same function.
 *    - It follows our own `vergeml_*` callees three deep, so a capability check
 *      one helper down is found and attributed to the helper it lives in.
 *    - It does not evaluate PHP. A capability assembled from a variable, or a
 *      check behind a condition, is reported as the text it found. Every `none`
 *      in the capability column is a row a person has to read.
 *
 *  @since 3.14
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const OUT = path.join( ROOT, 'docs', 'security-surface.md' );

const CHECK = process.argv.includes( '--check' );
const JSON_ONLY = process.argv.includes( '--json' );

/* Mirrors tools/deploy.mjs. A file that does not ship is not a way in. */
const SKIP = new Set( [
	'.git', '.github', '.claude', 'node_modules', 'playground',
	'tests', 'plans', 'tickets', 'docs', 'tools', 'research', 'dist',
	'.release-assets', 'assets', 'test-results',
] );


/* ------------------------------------------------------------- the payload */

function shippedPhp() {

	const tracked = execFileSync( 'git', [ 'ls-files', '-z' ], { cwd: ROOT, maxBuffer: 32 * 1024 * 1024 } )
		.toString()
		.split( '\0' )
		.filter( Boolean );

	if ( ! tracked.length ) {
		throw new Error( 'git listed no files; the surface cannot be trusted' );
	}

	return tracked
		.filter( ( rel ) => rel.endsWith( '.php' ) )
		.filter( ( rel ) => ! SKIP.has( rel.split( '/' )[ 0 ] ) )
		.filter( ( rel ) => fs.existsSync( path.join( ROOT, rel ) ) )
		.sort();
}


/* ------------------------------------------------------- a string-aware eye */

/**
 *  Comments out, everything else kept in place.
 *
 *  Same length as the input, so an offset found in the blanked copy is the same
 *  offset in the original and line numbers stay honest.
 */
function blankComments( src ) {

	let out = '';
	let i = 0;

	while ( i < src.length ) {

		const c = src[ i ];
		const two = src.slice( i, i + 2 );

		if ( c === "'" || c === '"' ) {
			const quote = c;
			let j = i + 1;
			while ( j < src.length ) {
				if ( src[ j ] === '\\' ) { j += 2; continue; }
				if ( src[ j ] === quote ) { j++; break; }
				j++;
			}
			out += src.slice( i, j );
			i = j;
			continue;
		}

		if ( two === '/*' ) {
			const end = src.indexOf( '*/', i + 2 );
			const stop = end === -1 ? src.length : end + 2;
			out += src.slice( i, stop ).replace( /[^\n]/g, ' ' );
			i = stop;
			continue;
		}

		if ( two === '//' || c === '#' ) {
			const end = src.indexOf( '\n', i );
			const stop = end === -1 ? src.length : end;
			out += ' '.repeat( stop - i );
			i = stop;
			continue;
		}

		out += c;
		i++;
	}

	return out;
}

/** The text between the parenthesis at `open` and its partner. */
function args( src, open ) {

	let depth = 0;
	let i = open;

	while ( i < src.length ) {

		const c = src[ i ];

		if ( c === "'" || c === '"' ) {
			const quote = c;
			i++;
			while ( i < src.length ) {
				if ( src[ i ] === '\\' ) { i += 2; continue; }
				if ( src[ i ] === quote ) { i++; break; }
				i++;
			}
			continue;
		}

		if ( c === '(' ) depth++;
		if ( c === ')' ) {
			depth--;
			if ( depth === 0 ) return src.slice( open + 1, i );
		}
		i++;
	}

	return '';
}

/** The text of the brace-delimited block starting at or after `from`. */
function block( src, from ) {

	const open = src.indexOf( '{', from );
	if ( open === -1 ) return '';

	let depth = 0;
	let i = open;

	while ( i < src.length ) {

		const c = src[ i ];

		if ( c === "'" || c === '"' ) {
			const quote = c;
			i++;
			while ( i < src.length ) {
				if ( src[ i ] === '\\' ) { i += 2; continue; }
				if ( src[ i ] === quote ) { i++; break; }
				i++;
			}
			continue;
		}

		if ( c === '{' ) depth++;
		if ( c === '}' ) {
			depth--;
			if ( depth === 0 ) return src.slice( open, i + 1 );
		}
		i++;
	}

	return src.slice( open );
}

const lineAt = ( src, at ) => src.slice( 0, at ).split( '\n' ).length;

/** Split an argument list on top-level commas. */
function commas( text ) {

	const out = [];
	let depth = 0;
	let start = 0;
	let i = 0;

	while ( i < text.length ) {

		const c = text[ i ];

		if ( c === "'" || c === '"' ) {
			const quote = c;
			i++;
			while ( i < text.length ) {
				if ( text[ i ] === '\\' ) { i += 2; continue; }
				if ( text[ i ] === quote ) { i++; break; }
				i++;
			}
			continue;
		}

		if ( c === '(' || c === '[' ) depth++;
		if ( c === ')' || c === ']' ) depth--;

		if ( c === ',' && depth === 0 ) {
			out.push( text.slice( start, i ) );
			start = i + 1;
		}
		i++;
	}

	out.push( text.slice( start ) );

	return out.map( ( s ) => s.trim() ).filter( ( s ) => s !== '' );
}

/**
 *  One value, from the start of `text` to wherever it ends.
 *
 *  Ends at a comma at depth zero, or at the closing paren of the array it sits
 *  in -- `'permission_callback' => $may )` is the last pair in a one-line config
 *  and has no trailing comma, which is how two routes first read as having no
 *  capability at all when they have one.
 */
function scalar( text ) {

	let depth = 0;
	let i = 0;

	while ( i < text.length ) {

		const c = text[ i ];

		if ( c === "'" || c === '"' ) {
			const quote = c;
			i++;
			while ( i < text.length ) {
				if ( text[ i ] === '\\' ) { i += 2; continue; }
				if ( text[ i ] === quote ) { i++; break; }
				i++;
			}
			continue;
		}

		if ( c === '(' || c === '[' ) depth++;
		if ( c === ')' || c === ']' ) {
			if ( depth === 0 ) return text.slice( 0, i ).trim();
			depth--;
		}
		if ( c === ',' && depth === 0 ) return text.slice( 0, i ).trim();
		i++;
	}

	return text.trim();
}

const unquote = ( s ) => {
	const t = s.trim();
	const m = t.match( /^'([^']*)'$/ ) || t.match( /^"([^"]*)"$/ );
	return m ? m[ 1 ] : null;
};


/* ------------------------------------------------------------ the code model */

const files = shippedPhp();
const src = new Map();      // rel -> blanked source
const consts = new Map();   // NAME -> value
const funcs = new Map();    // name -> { rel, line, body }

for ( const rel of files ) {
	src.set( rel, blankComments( fs.readFileSync( path.join( ROOT, rel ), 'utf8' ) ) );
}

/**
 *  The byte ranges of class bodies in a file.
 *
 *  A `function` inside one is a method, not a way in, and treating them as
 *  globals reported four Walker subclasses as redeclared functions. Indentation
 *  is no help here: most of the main file's functions are indented too.
 */
function classRanges( text ) {

	const out = [];

	for ( const m of text.matchAll( /\b(?:final\s+|abstract\s+)?(?:class|trait|interface)\s+[A-Za-z_][A-Za-z0-9_]*/g ) ) {
		const body = block( text, m.index );
		if ( ! body ) continue;
		const open = text.indexOf( '{', m.index );
		out.push( [ open, open + body.length ] );
	}

	return out;
}

const inClass = ( ranges, at ) => ranges.some( ( [ from, to ] ) => at > from && at < to );

const classes = new Map();

for ( const [ rel, text ] of src ) {
	classes.set( rel, classRanges( text ) );
}

for ( const [ rel, text ] of src ) {

	for ( const m of text.matchAll( /\bconst\s+([A-Z_][A-Z0-9_]*)\s*=\s*('[^']*'|"[^"]*"|[0-9]+)\s*;/g ) ) {
		consts.set( m[ 1 ], unquote( m[ 2 ] ) ?? m[ 2 ] );
	}

	for ( const m of text.matchAll( /\bdefine\s*\(\s*'([A-Z_][A-Z0-9_]*)'\s*,\s*('[^']*'|"[^"]*"|true|false|[0-9]+)/g ) ) {
		if ( ! consts.has( m[ 1 ] ) ) consts.set( m[ 1 ], unquote( m[ 2 ] ) ?? m[ 2 ] );
	}

	for ( const m of text.matchAll( /^[ \t]*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/gm ) ) {
		const name = m[ 1 ];
		if ( inClass( classes.get( rel ), m.index ) ) continue;
		if ( funcs.has( name ) ) continue;          // first definition wins; duplicates are reported below
		funcs.set( name, { rel, line: lineAt( text, m.index ), body: block( text, m.index ) } );
	}
}

const resolve = ( token ) => {
	const lit = unquote( token );
	if ( lit !== null ) return lit;
	const bare = token.trim().replace( /^\\/, '' );
	if ( consts.has( bare ) ) return consts.get( bare );
	return null;
};


/* ------------------------------------------------------------- what a body does */

const CAP_RE = /\b(current_user_can|user_can|current_user_can_for_blog)\s*\(/g;
const NONCE_RE = /\b(check_ajax_referer|check_admin_referer|wp_verify_nonce)\s*\(/g;

/**
 *  Every capability question in a body, with the object it was asked about.
 *
 *  The argument list is read with the same string-aware walker the rest of this
 *  file uses, not a regex: `current_user_can( 'edit_post', (int) $id )` holds a
 *  cast, and a regex that stops at the first `)` reports the object as `(int`.
 */
function capsIn( text, where ) {

	const out = [];

	for ( const m of text.matchAll( CAP_RE ) ) {
		const parts = commas( args( text, m.index + m[ 0 ].length - 1 ) );
		if ( ! parts.length ) continue;
		const cap = resolve( parts[ 0 ] ) ?? parts[ 0 ];
		const object = parts.length > 1 ? parts.slice( 1 ).join( ', ' ).replace( /\s+/g, ' ' ) : null;
		out.push( { cap, object, where } );
	}

	return out;
}

const WRITES = [
	[ /\$wpdb->(insert|update|replace|delete)\s*\(/, 'db write' ],
	[ /\$wpdb->(query|get_var|get_row|get_results|get_col|prepare)\s*\(/, 'db query' ],
	[ /\bwp_(insert|update)_(post|attachment|term)\s*\(/, 'post/term write' ],
	[ /\bwp_delete_(post|attachment|term|file)\s*\(/, 'delete' ],
	[ /\bwp_set_object_terms\s*\(|\bwp_(add|remove)_object_terms\s*\(/, 'term assign' ],
	[ /\b(update|add|delete)_(post|term|user|option|site_option)_?meta\s*\(|\bupdate_option\s*\(|\bdelete_option\s*\(|\bupdate_user_meta\s*\(/, 'meta/option write' ],
	[ /\b(rename|unlink|copy|file_put_contents|mkdir|rmdir)\s*\(/, 'filesystem' ],
	[ /\bwp_remote_(get|post|request|head)\s*\(/, 'outbound http' ],
	[ /\bwp_schedule_(single_)?event\s*\(/, 'schedules cron' ],
	[ /\bwp_(set_current_user|set_auth_cookie)\s*\(/, 'auth' ],
];

const READS = [
	[ /\$_POST\b/, '$_POST' ],
	[ /\$_GET\b/, '$_GET' ],
	[ /\$_REQUEST\b/, '$_REQUEST' ],
	[ /\$_FILES\b/, '$_FILES' ],
	[ /\$_SERVER\b/, '$_SERVER' ],
	[ /\$_COOKIE\b/, '$_COOKIE' ],
	[ /php:\/\/input/, 'php://input' ],
	[ /\$request->get_(param|params|json_params|body_params|query_params|file_params)\s*\(/, '$request' ],
];

/**
 *  Read a body, then the bodies of our own functions it calls, three deep.
 *
 *  Three is not arbitrary: route handler -> helper -> query builder is the shape
 *  in this codebase, and a fourth hop adds noise without adding findings. The
 *  depth a fact was found at is kept, so the table can say a capability is
 *  checked in the handler rather than somewhere below it.
 */
function analyse( body, startedIn, depth = 3, seen = new Set() ) {

	const found = { caps: [], nonces: [], writes: new Set(), reads: new Set(), callees: new Set(), sql: [] };

	const walk = ( text, where, left ) => {

		found.caps.push( ...capsIn( text, where ) );

		for ( const m of text.matchAll( NONCE_RE ) ) {
			found.nonces.push( { fn: m[ 1 ], where } );
		}

		for ( const [ re, label ] of WRITES ) if ( re.test( text ) ) found.writes.add( label );
		for ( const [ re, label ] of READS ) if ( re.test( text ) ) found.reads.add( label );

		if ( left <= 0 ) return;

		for ( const m of text.matchAll( /\b(vergeml_[A-Za-z0-9_]*)\s*\(/g ) ) {
			const name = m[ 1 ];
			if ( seen.has( name ) || ! funcs.has( name ) ) continue;
			seen.add( name );
			found.callees.add( name );
			walk( funcs.get( name ).body, name, left - 1 );
		}
	};

	walk( body, startedIn, depth );

	return found;
}

/** A callback token -> a readable name and a body to analyse. */
function callback( token, enclosing ) {

	const t = ( token || '' ).trim();

	const named = unquote( t );
	if ( named && funcs.has( named ) ) {
		const f = funcs.get( named );
		return { name: named, body: f.body, at: `${ f.rel }:${ f.line }` };
	}
	if ( named ) return { name: named, body: '', at: 'not found in the shipped tree' };

	if ( /^(static\s+)?(function|fn)\s*\(/.test( t ) ) {
		return { name: 'closure', body: t, at: null };
	}

	/* A `$can`-style closure assigned earlier in the same function. */
	const v = t.match( /^\$([A-Za-z_][A-Za-z0-9_]*)$/ );
	if ( v && enclosing ) {
		const re = new RegExp( `\\$${ v[ 1 ] }\\s*=\\s*(static\\s+)?function\\s*\\(` );
		const hit = enclosing.body.match( re );
		if ( hit ) {
			return { name: `$${ v[ 1 ] }`, body: block( enclosing.body, hit.index ), at: null };
		}
	}

	if ( /^(array|\[)/.test( t ) ) return { name: t.replace( /\s+/g, ' ' ).slice( 0, 60 ), body: '', at: null };

	return { name: t.replace( /\s+/g, ' ' ).slice( 0, 60 ) || '(none)', body: '', at: null };
}

/** Which function a byte offset falls inside. */
function enclosingOf( rel, at ) {
	const text = src.get( rel );
	let best = null;
	for ( const m of text.matchAll( /^[ \t]*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/gm ) ) {
		if ( m.index <= at ) best = m[ 1 ]; else break;
	}
	return best && funcs.has( best ) ? funcs.get( best ) : null;
}

const METHODS = {
	'WP_REST_Server::READABLE': 'GET',
	'WP_REST_Server::CREATABLE': 'POST',
	'WP_REST_Server::EDITABLE': 'POST, PUT, PATCH',
	'WP_REST_Server::DELETABLE': 'DELETE',
	'WP_REST_Server::ALLMETHODS': 'GET, POST, PUT, PATCH, DELETE',
};

const methodOf = ( token ) => {
	const t = ( token || '' ).trim();
	const parts = commas( t.replace( /['"]/g, '' ).replace( /\|/g, ',' ) );
	return parts.map( ( p ) => METHODS[ p.trim() ] ?? p.trim() ).join( ', ' ) || '?';
};

/** The keys of an `args` => array( ... ) map, and which are required. */
function argKeys( text ) {
	if ( ! text ) return [];
	const inner = text.replace( /^\s*array\s*\(/, '' ).replace( /^\s*\[/, '' );
	const out = [];
	for ( const part of commas( inner.replace( /\)\s*$/, '' ).replace( /\]\s*$/, '' ) ) ) {
		const m = part.match( /^'([^']+)'\s*=>/ ) || part.match( /^"([^"]+)"\s*=>/ );
		if ( m ) out.push( /'required'\s*=>\s*true/.test( part ) ? `${ m[ 1 ] }*` : m[ 1 ] );
	}
	return out;
}

/** One `array( 'methods' => ..., 'callback' => ... )` config. */
function endpointConfig( text, enclosing ) {

	const grab = ( key ) => {
		const m = text.match( new RegExp( `'${ key }'\\s*=>\\s*` ) );
		if ( ! m ) return null;
		const from = m.index + m[ 0 ].length;
		const rest = text.slice( from );
		if ( /^(array\s*\(|\[)/.test( rest ) ) {
			const open = rest.indexOf( rest.startsWith( '[' ) ? '[' : '(' );
			return rest.slice( 0, open + args( rest.replace( /\[/g, '(' ).replace( /\]/g, ')' ), open ).length + 2 );
		}
		if ( /^(static\s+)?function\s*\(/.test( rest ) ) {
			const head = rest.indexOf( '(' );
			const sig = args( rest, head );
			return rest.slice( 0, head + sig.length + 2 ) + block( rest, head + sig.length + 2 );
		}
		return scalar( rest );
	};

	return {
		methods: methodOf( grab( 'methods' ) ),
		callback: callback( grab( 'callback' ), enclosing ),
		permission: callback( grab( 'permission_callback' ), enclosing ),
		args: argKeys( grab( 'args' ) ),
	};
}


/* ----------------------------------------------------------------- the entries */

const rest = [];
const ajax = [];
const adminPost = [];
const cron = [];
const metaRest = [];
const inputHooks = [];
const screens = [];
const frontend = [];
const settings = [];
const duplicateFns = [];

{
	const seenFn = new Map();
	for ( const [ rel, text ] of src ) {
		for ( const m of text.matchAll( /^[ \t]*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/gm ) ) {
			const key = m[ 1 ];
			if ( inClass( classes.get( rel ), m.index ) ) continue;
			const at = `${ rel }:${ lineAt( text, m.index ) }`;
			if ( seenFn.has( key ) ) duplicateFns.push( { name: key, first: seenFn.get( key ), again: at } );
			else seenFn.set( key, at );
		}
	}
}

for ( const [ rel, text ] of src ) {

	/* --- REST ------------------------------------------------------------- */
	for ( const m of text.matchAll( /\bregister_rest_route\s*\(/g ) ) {

		const open = m.index + m[ 0 ].length - 1;
		const list = commas( args( text, open ) );
		const line = lineAt( text, m.index );
		const enclosing = enclosingOf( rel, m.index );

		const ns = resolve( list[ 0 ] ?? '' ) ?? ( list[ 0 ] ?? '?' );
		const route = resolve( list[ 1 ] ?? '' ) ?? ( list[ 1 ] ?? '?' );
		const body = list.slice( 2 ).join( ',' );

		/*
		 *  A route is registered either with one config or with a list of them.
		 *  `array( array( 'methods' ... ), array( 'methods' ... ) )` is two
		 *  endpoints on one route, and counting the call instead of the configs
		 *  is how a surface gets under-reported.
		 */
		const inner = body.replace( /^\s*array\s*\(/, '' ).replace( /\)\s*$/, '' );
		const parts = commas( inner );
		const configs = parts.filter( ( p ) => /^array\s*\(|^\[/.test( p ) && /'methods'\s*=>/.test( p ) );

		const each = configs.length ? configs : [ body ];

		for ( const cfg of each ) {
			rest.push( { rel, line, route: `/${ ns }${ route }`, ...endpointConfig( cfg, enclosing ) } );
		}
	}

	/* --- AJAX and admin_post --------------------------------------------- */
	for ( const m of text.matchAll( /\badd_action\s*\(/g ) ) {

		const open = m.index + m[ 0 ].length - 1;
		const list = commas( args( text, open ) );
		const hook = resolve( list[ 0 ] ?? '' );
		if ( ! hook ) continue;

		const line = lineAt( text, m.index );
		const enclosing = enclosingOf( rel, m.index );
		const cb = callback( list[ 1 ] ?? '', enclosing );

		if ( hook.startsWith( 'wp_ajax_' ) ) {
			ajax.push( {
				rel, line,
				action: hook.replace( /^wp_ajax_(nopriv_)?/, '' ),
				nopriv: hook.startsWith( 'wp_ajax_nopriv_' ),
				callback: cb,
			} );
			continue;
		}

		if ( hook.startsWith( 'admin_post_' ) ) {
			adminPost.push( {
				rel, line,
				action: hook.replace( /^admin_post_(nopriv_)?/, '' ),
				nopriv: hook.startsWith( 'admin_post_nopriv_' ),
				callback: cb,
			} );
		}
	}

	/* --- admin screens ---------------------------------------------------- */

	/*
	 *  A screen is a way in. It answers a GET, most of them read `$_GET`, and the
	 *  capability in the menu registration is the only thing between the URL and
	 *  the render callback -- WordPress checks it, but only the one that was
	 *  declared here.
	 */
	for ( const m of text.matchAll( /\badd_(menu_page|submenu_page|options_page|media_page|management_page)\s*\(/g ) ) {

		const list = commas( args( text, m.index + m[ 0 ].length - 1 ) );
		const kind = m[ 1 ];
		const enclosing = enclosingOf( rel, m.index );

		/* add_submenu_page( parent, title, title, cap, slug, cb ); the others drop the parent. */
		const off = kind === 'submenu_page' ? 1 : 0;
		const cap = resolve( list[ off + 2 ] ?? '' ) ?? ( list[ off + 2 ] ?? '?' ).trim();
		const slug = resolve( list[ off + 3 ] ?? '' ) ?? ( list[ off + 3 ] ?? '?' ).trim();
		const cb = callback( list[ off + 4 ] ?? '', enclosing );
		const seen = analyse( cb.body || '', cb.name );

		screens.push( {
			rel, line: lineAt( text, m.index ), kind, cap, slug,
			callback: cb.name, at: cb.at,
			reads: [ ...seen.reads ], writes: [ ...seen.writes ],
			nonces: [ ...new Set( seen.nonces.map( ( n ) => n.fn ) ) ],
			caps: [ ...new Set( seen.caps.map( ( c ) => ( c.object ? `${ c.cap }(${ c.object })` : c.cap ) ) ) ],
		} );
	}

	/* --- the front end: a shortcode, and a block's render callback -------- */

	/*
	 *  These run for a visitor who is not logged in, with attributes that came
	 *  out of post content. No capability applies and none should; what matters
	 *  is that an attribute is cast before it reaches a query, and that a private
	 *  folder does not become a public gallery.
	 */
	for ( const m of text.matchAll( /\badd_shortcode\s*\(/g ) ) {
		const list = commas( args( text, m.index + m[ 0 ].length - 1 ) );
		const cb = callback( list[ 1 ] ?? '', enclosingOf( rel, m.index ) );
		const seen = analyse( cb.body || '', cb.name );
		frontend.push( {
			rel, line: lineAt( text, m.index ), kind: 'shortcode',
			name: resolve( list[ 0 ] ?? '' ) ?? '?', callback: cb.name, at: cb.at,
			reads: [ ...seen.reads ], writes: [ ...seen.writes ],
		} );
	}

	for ( const m of text.matchAll( /\bregister_block_type\s*\(/g ) ) {
		const list = commas( args( text, m.index + m[ 0 ].length - 1 ) );
		const body = list.slice( 1 ).join( ',' );
		const render = body.match( /'render_callback'\s*=>\s*/ );
		if ( ! render ) continue;
		const cb = callback( scalar( body.slice( render.index + render[ 0 ].length ) ), enclosingOf( rel, m.index ) );
		const seen = analyse( cb.body || '', cb.name );
		frontend.push( {
			rel, line: lineAt( text, m.index ), kind: 'block',
			name: resolve( list[ 0 ] ?? '' ) ?? '?', callback: cb.name, at: cb.at,
			reads: [ ...seen.reads ], writes: [ ...seen.writes ],
		} );
	}

	/* --- registered settings --------------------------------------------- */

	/*
	 *  A registered setting is saved by `options.php`, not by us: core checks the
	 *  option group's capability and the nonce, then calls the sanitize callback.
	 *  A setting registered without one is saved as it arrived.
	 */
	for ( const m of text.matchAll( /\bregister_setting\s*\(/g ) ) {

		const list = commas( args( text, m.index + m[ 0 ].length - 1 ) );
		const third = ( list[ 2 ] ?? '' ).trim();

		/*
		 *  Two signatures, and this plugin uses the older one. Modern:
		 *  register_setting( group, option, array( 'sanitize_callback' => ... ) ).
		 *  Legacy, still supported: the third argument *is* the callback. Reading
		 *  only the array form reported all seven settings as unsanitised when
		 *  every one of them names a validator -- a false finding, and the exact
		 *  kind this phase is supposed to stop shipping.
		 */
		let sanitize = null;

		if ( /^(array\s*\(|\[)/.test( third ) ) {
			const hit = third.match( /'sanitize_callback'\s*=>\s*/ );
			if ( hit ) sanitize = scalar( third.slice( hit.index + hit[ 0 ].length ) ).replace( /\s+/g, ' ' );
		} else if ( third ) {
			sanitize = third.replace( /\s+/g, ' ' );
		}

		settings.push( {
			rel, line: lineAt( text, m.index ),
			group: resolve( list[ 0 ] ?? '' ) ?? ( list[ 0 ] ?? '?' ).trim(),
			option: resolve( list[ 1 ] ?? '' ) ?? ( list[ 1 ] ?? '?' ).trim(),
			sanitize,
		} );
	}

	/* --- meta core exposes over REST for us ------------------------------- */
	for ( const m of text.matchAll( /\bregister_(term|post)_meta\s*\(/g ) ) {

		const open = m.index + m[ 0 ].length - 1;
		const list = commas( args( text, open ) );
		const body = list.slice( 2 ).join( ',' );
		if ( ! /'show_in_rest'\s*=>\s*(true|array|\[)/.test( body ) ) continue;

		const enclosing = enclosingOf( rel, m.index );
		const authMatch = body.match( /'auth_callback'\s*=>\s*/ );

		metaRest.push( {
			rel, line: lineAt( text, m.index ),
			kind: m[ 1 ],
			key: resolve( list[ 1 ] ?? '' ) ?? ( list[ 1 ] ?? '?' ),
			sanitize: ( body.match( /'sanitize_callback'\s*=>\s*('[^']*'|[^,)]+)/ ) ?? [] )[ 1 ]?.trim() ?? null,
			auth: authMatch
				? callback( body.slice( authMatch.index + authMatch[ 0 ].length ).startsWith( 'function' )
					? 'function (' + args( body, body.indexOf( '(', authMatch.index + authMatch[ 0 ].length ) ) + ')' + block( body, authMatch.index )
					: commas( body.slice( authMatch.index + authMatch[ 0 ].length ) )[ 0 ], enclosing )
				: { name: '(none -- core default)', body: '', at: null },
		} );
	}
}

/* --- cron hooks: a hook that is scheduled, and what answers it ------------ */
{
	const scheduled = new Map();   // hook -> where it is booked
	for ( const [ rel, text ] of src ) {
		for ( const m of text.matchAll( /\bwp_schedule_(single_)?event\s*\(/g ) ) {
			const list = commas( args( text, m.index + m[ 0 ].length - 1 ) );
			const token = ( m[ 1 ] ? list[ 1 ] : list[ 2 ] ) ?? '';
			const hook = resolve( token ) ?? token.trim();
			if ( hook ) scheduled.set( hook, `${ rel }:${ lineAt( text, m.index ) }` );
		}
		for ( const m of text.matchAll( /\bwp_next_scheduled\s*\(/g ) ) {
			const list = commas( args( text, m.index + m[ 0 ].length - 1 ) );
			const hook = resolve( list[ 0 ] ?? '' ) ?? ( list[ 0 ] ?? '' ).trim();
			if ( hook && ! scheduled.has( hook ) ) scheduled.set( hook, `${ rel }:${ lineAt( text, m.index ) }` );
		}
	}

	for ( const [ hook, booked ] of scheduled ) {
		const answers = [];
		for ( const [ rel, text ] of src ) {
			for ( const m of text.matchAll( /\badd_action\s*\(/g ) ) {
				const list = commas( args( text, m.index + m[ 0 ].length - 1 ) );
				const h = resolve( list[ 0 ] ?? '' ) ?? ( list[ 0 ] ?? '' ).trim();
				if ( h !== hook ) continue;
				answers.push( { ...callback( list[ 1 ] ?? '', enclosingOf( rel, m.index ) ), rel, line: lineAt( text, m.index ) } );
			}
		}
		cron.push( { hook, booked, answers } );
	}
}

/* --- filters and actions whose callback reads the request ----------------- */
{
	const OURS = /^vergeml_/;
	for ( const [ rel, text ] of src ) {
		for ( const m of text.matchAll( /\badd_(filter|action)\s*\(/g ) ) {

			const list = commas( args( text, m.index + m[ 0 ].length - 1 ) );
			const hook = resolve( list[ 0 ] ?? '' );
			if ( ! hook ) continue;
			if ( hook.startsWith( 'wp_ajax_' ) || hook.startsWith( 'admin_post_' ) ) continue;

			const name = unquote( list[ 1 ] ?? '' );
			if ( ! name || ! OURS.test( name ) || ! funcs.has( name ) ) continue;

			const f = funcs.get( name );
			const reads = READS.filter( ( [ re ] ) => re.test( f.body ) ).map( ( [ , label ] ) => label );
			if ( ! reads.length ) continue;

			const seen = analyse( f.body, name );
			inputHooks.push( {
				rel, line: lineAt( text, m.index ),
				hook, kind: m[ 1 ], callback: name, at: `${ f.rel }:${ f.line }`,
				reads, writes: [ ...seen.writes ],
				caps: seen.caps, nonces: seen.nonces,
			} );
		}
	}
}


/* ------------------------------------------------------------------- judgement */

/**
 *  The three questions 5.2 asks of every row, answered as far as reading can.
 *
 *  `object` is the one that matters: a capability with no second argument is a
 *  site-wide yes, and a route that acts on an id the caller supplied wants a
 *  capability asked about *that* id. Nothing here decides a row is safe -- it
 *  sorts the rows a person must read to the top.
 */
function verdict( entry, analysed, opts = {} ) {

	const caps = analysed.caps;
	const own = caps.filter( ( c ) => c.where === ( entry.callback?.name ?? entry.name ) );
	const scoped = caps.filter( ( c ) => c.object );
	const writes = [ ...analysed.writes ];

	/*
	 *  `db query` is a SELECT, and a SELECT is not a state change. Lumping the
	 *  two together made a handler that only enqueues a script look like one that
	 *  writes, and briefly turned a correct design -- assets hooked onto another
	 *  plugin's ajax action, behind `upload_files`, with nothing to forge -- into
	 *  a missing-nonce finding. What needs a nonce is what changes something.
	 */
	const mutates = writes.filter( ( w ) => w !== 'db query' );
	const destructive = mutates.some( ( w ) => /delete|filesystem|db write|term assign|post\/term write|meta\/option write/.test( w ) );

	const notes = [];

	if ( ! caps.length ) notes.push( 'NO CAPABILITY FOUND' );
	if ( caps.length && ! scoped.length && destructive && opts.takesId ) notes.push( 'unscoped capability on an id from the request' );
	if ( opts.needsNonce && mutates.length && ! analysed.nonces.length ) notes.push( 'NO NONCE' );
	if ( ! mutates.length ) notes.push( 'changes nothing' );

	return {
		caps: caps.map( ( c ) => ( c.object ? `${ c.cap }(${ c.object })` : c.cap ) ),
		capsWhere: [ ...new Set( caps.map( ( c ) => c.where ) ) ],
		ownCaps: own.length,
		scoped: scoped.length,
		nonces: [ ...new Set( analysed.nonces.map( ( n ) => n.fn ) ) ],
		writes,
		mutates,
		reads: [ ...analysed.reads ],
		notes,
	};
}

const ID_ARG = /(^|_)(id|ids|attachment|attachments|post|posts|term|terms|folder|folders|parent|target|source)s?(\*)?$/i;

const restRows = rest.map( ( r ) => {
	const a = analyse( ( r.permission.body || '' ) + '\n' + ( r.callback.body || '' ), r.callback.name );
	const permOnly = analyse( r.permission.body || '', r.permission.name );
	const takesId = r.args.some( ( k ) => ID_ARG.test( k.replace( /\*$/, '' ) ) ) || /get_param\s*\(\s*'[^']*(id|ids)'/.test( r.callback.body || '' );
	return { ...r, takesId, judged: verdict( r, a, { takesId } ), permCaps: permOnly.caps, body: undefined };
} );

const ajaxRows = ajax.map( ( r ) => {
	const a = analyse( r.callback.body || '', r.callback.name );
	return { ...r, judged: verdict( r, a, { needsNonce: true, takesId: true } ) };
} );

const postRows = adminPost.map( ( r ) => {
	const a = analyse( r.callback.body || '', r.callback.name );
	return { ...r, judged: verdict( r, a, { needsNonce: true, takesId: true } ) };
} );

const cronRows = cron.map( ( c ) => ( {
	...c,
	answers: c.answers.map( ( ans ) => {
		const a = analyse( ans.body || '', ans.name );
		return { ...ans, writes: [ ...a.writes ], reads: [ ...a.reads ], body: undefined };
	} ),
} ) );

const metaRows = metaRest.map( ( m ) => {
	const a = analyse( m.auth.body || '', m.auth.name );
	return { ...m, caps: a.caps.map( ( c ) => ( c.object ? `${ c.cap }(${ c.object })` : c.cap ) ) };
} );


/* ------------------------------------------------------------------ the table */

const esc = ( s ) => String( s ?? '' ).replace( /\|/g, '\\|' ).replace( /\n/g, ' ' );
const list = ( a ) => ( a && a.length ? a.join( ', ' ) : '—' );
const flag = ( notes ) => ( notes.filter( ( n ) => n === n.toUpperCase() && /[A-Z]/.test( n ) ).length ? '**read me**' : '' );

const counted = {
	files: files.length,
	restCalls: new Set( rest.map( ( r ) => `${ r.rel }:${ r.line }` ) ).size,
	restEndpoints: rest.length,
	restRoutes: new Set( rest.map( ( r ) => r.route ) ).size,
	ajax: ajax.length,
	ajaxNopriv: ajax.filter( ( a ) => a.nopriv ).length,
	adminPost: adminPost.length,
	adminPostNopriv: adminPost.filter( ( a ) => a.nopriv ).length,
	cron: cron.length,
	metaRest: metaRest.length,
	inputHooks: inputHooks.length,
	screens: screens.length,
	frontend: frontend.length,
	settings: settings.length,
	settingsUnsanitised: settings.filter( ( s ) => ! s.sanitize ).length,
	noCapability: restRows.filter( ( r ) => ! r.judged.caps.length ).length
		+ ajaxRows.filter( ( r ) => ! r.judged.caps.length ).length
		+ postRows.filter( ( r ) => ! r.judged.caps.length ).length,
	noNonce: [ ...ajaxRows, ...postRows ].filter( ( r ) => r.judged.mutates.length && ! r.judged.nonces.length ).length,
	unscopedOnId: restRows.filter( ( r ) => r.judged.notes.includes( 'unscoped capability on an id from the request' ) ).length,
};

function markdown() {

	const L = [];

	L.push( '# The security surface' );
	L.push( '' );
	L.push( '**Generated. Do not edit.** `node tools/security-surface.mjs` writes this file;' );
	L.push( '`node tools/security-surface.mjs --check` fails when the code has moved and this' );
	L.push( 'file has not, which is what keeps a new route from arriving undocumented.' );
	L.push( '' );
	L.push( `Read from ${ counted.files } shipped PHP files — \`git ls-files\` minus the directories` );
	L.push( 'tools/deploy.mjs refuses to ship, so nothing in `tests/` or `tools/` is counted as a' );
	L.push( 'way in, because none of it reaches a site.' );
	L.push( '' );
	L.push( 'Phase 5.1 of `plans/four-yesses.md`. The three questions of 5.2 — **can this caller' );
	L.push( 'do this at all**, **may they do it to _this_ object**, **did they mean to** — are the' );
	L.push( 'capability, object and nonce columns. A row marked **read me** is one the generator' );
	L.push( 'could not answer; it is not yet a finding and it is not yet safe.' );
	L.push( '' );

	L.push( '## The count' );
	L.push( '' );
	L.push( '| | count |' );
	L.push( '|---|---|' );
	L.push( `| \`register_rest_route\` calls | ${ counted.restCalls } |` );
	L.push( `| REST routes | ${ counted.restRoutes } |` );
	L.push( `| REST **endpoints** (route × method config) | **${ counted.restEndpoints }** |` );
	L.push( `| AJAX actions | ${ counted.ajax }${ counted.ajaxNopriv ? ` (${ counted.ajaxNopriv } nopriv)` : ' (0 nopriv)' } |` );
	L.push( `| \`admin_post\` handlers | ${ counted.adminPost }${ counted.adminPostNopriv ? ` (${ counted.adminPostNopriv } nopriv)` : ' (0 nopriv)' } |` );
	L.push( `| cron hooks | ${ counted.cron } |` );
	L.push( `| admin screens | ${ counted.screens } |` );
	L.push( `| front-end entries (shortcode, block render) | ${ counted.frontend } |` );
	L.push( `| registered settings | ${ counted.settings }${ counted.settingsUnsanitised ? ` (**${ counted.settingsUnsanitised } with no sanitize callback**)` : '' } |` );
	L.push( `| meta fields core exposes over REST | ${ counted.metaRest } |` );
	L.push( `| filters/actions whose callback reads the request | ${ counted.inputHooks } |` );
	L.push( `| entries with no capability the reader could find | ${ counted.noCapability } |` );
	L.push( `| AJAX/admin_post entries that change something with no nonce check | ${ counted.noNonce } |` );
	L.push( `| REST entries with an unscoped capability over an id from the request | ${ counted.unscopedOnId } |` );
	L.push( '' );

	L.push( '## REST endpoints' );
	L.push( '' );
	L.push( 'Capability is what `current_user_can` was called with, in the permission callback or' );
	L.push( 'in the handler beneath it; a name in brackets is the object it was asked about.' );
	L.push( 'Anything without brackets is a site-wide yes.' );
	L.push( '' );
	L.push( '| route | method | permission callback | capability | object-scoped | reads | writes | id from request | where |' );
	L.push( '|---|---|---|---|---|---|---|---|---|' );

	for ( const r of [ ...restRows ].sort( ( a, b ) => a.route.localeCompare( b.route ) || a.methods.localeCompare( b.methods ) ) ) {
		L.push( '| ' + [
			`\`${ esc( r.route ) }\``,
			esc( r.methods ),
			esc( r.permission.name ),
			list( r.judged.caps.map( esc ) ),
			r.judged.scoped ? `yes (${ r.judged.scoped })` : 'no',
			list( [ ...r.args.map( ( k ) => `\`${ esc( k ) }\`` ), ...r.judged.reads.map( esc ) ] ),
			list( r.judged.writes.map( esc ) ),
			r.takesId ? 'yes' : 'no',
			`${ r.rel }:${ r.line }`,
		].join( ' | ' ) + ' |' );
	}

	L.push( '' );
	L.push( '`*` on an argument name means the route declares it required.' );
	L.push( '' );

	const flagged = restRows.filter( ( r ) => r.judged.notes.some( ( n ) => /[A-Z]{3}/.test( n ) ) || r.judged.notes.includes( 'unscoped capability on an id from the request' ) );
	if ( flagged.length ) {
		L.push( '### REST rows a person must read' );
		L.push( '' );
		L.push( '| route | method | why | where |' );
		L.push( '|---|---|---|---|' );
		for ( const r of flagged ) {
			L.push( `| \`${ esc( r.route ) }\` | ${ esc( r.methods ) } | ${ esc( r.judged.notes.filter( ( n ) => n !== 'read only' ).join( '; ' ) ) } | ${ r.rel }:${ r.line } |` );
		}
		L.push( '' );
	}

	L.push( '## AJAX actions' );
	L.push( '' );
	L.push( 'Every one of these answers on `admin-ajax.php`. A `wp_ajax_` action requires a logged-in' );
	L.push( 'user and nothing more; `wp_ajax_nopriv_` requires nothing at all.' );
	L.push( '' );
	L.push( '| action | nopriv | callback | capability | nonce | reads | writes | where |' );
	L.push( '|---|---|---|---|---|---|---|---|' );
	for ( const r of ajaxRows ) {
		L.push( '| ' + [
			`\`${ esc( r.action ) }\``,
			r.nopriv ? '**yes**' : 'no',
			esc( r.callback.name ),
			list( r.judged.caps.map( esc ) ),
			list( r.judged.nonces.map( esc ) ),
			list( r.judged.reads.map( esc ) ),
			list( r.judged.writes.map( esc ) ),
			`${ r.rel }:${ r.line }`,
		].join( ' | ' ) + ' |' );
	}
	L.push( '' );

	L.push( '## admin_post handlers' );
	L.push( '' );
	L.push( 'A form post to `admin-post.php`. These carry no REST permission callback, so the' );
	L.push( 'capability and the nonce are the whole of the gate.' );
	L.push( '' );
	L.push( '| action | nopriv | callback | capability | nonce | reads | writes | where |' );
	L.push( '|---|---|---|---|---|---|---|---|' );
	for ( const r of postRows ) {
		L.push( '| ' + [
			`\`${ esc( r.action ) }\``,
			r.nopriv ? '**yes**' : 'no',
			esc( r.callback.name ),
			list( r.judged.caps.map( esc ) ),
			list( r.judged.nonces.map( esc ) ),
			list( r.judged.reads.map( esc ) ),
			list( r.judged.writes.map( esc ) ),
			`${ r.rel }:${ r.line }`,
		].join( ' | ' ) + ' |' );
	}
	L.push( '' );

	L.push( '## Cron hooks' );
	L.push( '' );
	L.push( 'A cron hook runs with no user. Nothing inside one may depend on a capability, and' );
	L.push( 'anything it spends has to be decided before it is booked.' );
	L.push( '' );
	L.push( '| hook | booked at | answered by | writes | reads |' );
	L.push( '|---|---|---|---|---|' );
	for ( const c of cronRows ) {
		const a = c.answers[ 0 ];
		L.push( `| \`${ esc( c.hook ) }\` | ${ c.booked } | ${ c.answers.length ? c.answers.map( ( x ) => `${ esc( x.name ) } (${ x.at ?? `${ x.rel }:${ x.line }` })` ).join( ', ' ) : '**nothing**' } | ${ list( ( a?.writes ?? [] ).map( esc ) ) } | ${ list( ( a?.reads ?? [] ).map( esc ) ) } |` );
	}
	L.push( '' );

	L.push( '## Admin screens' );
	L.push( '' );
	L.push( 'The capability in the column is the one the menu was registered with, and it is the' );
	L.push( 'whole of what WordPress checks before the callback runs. A screen that then reads' );
	L.push( '`$_GET` and acts on it needs its own nonce; a screen that only renders does not.' );
	L.push( '' );
	L.push( '| slug | registered as | capability | callback | reads | writes | nonce | where |' );
	L.push( '|---|---|---|---|---|---|---|---|' );
	for ( const s of [ ...screens ].sort( ( a, b ) => String( a.slug ).localeCompare( String( b.slug ) ) ) ) {
		L.push( '| ' + [
			`\`${ esc( s.slug ) }\``,
			`add_${ s.kind }`,
			esc( s.cap ),
			`${ esc( s.callback ) }${ s.at ? ` (${ s.at })` : '' }`,
			list( s.reads.map( esc ) ),
			list( s.writes.filter( ( w ) => w !== 'db query' ).map( esc ) ),
			list( s.nonces.map( esc ) ),
			`${ s.rel }:${ s.line }`,
		].join( ' | ' ) + ' |' );
	}
	L.push( '' );

	L.push( '## The front end' );
	L.push( '' );
	L.push( 'These run for a visitor who is not logged in, on attributes that came out of post' );
	L.push( 'content. No capability applies and none should. What matters is that an attribute is' );
	L.push( 'cast before it reaches a query, and that a private folder does not become a public' );
	L.push( 'gallery.' );
	L.push( '' );
	L.push( '| name | kind | render callback | reads | writes | where |' );
	L.push( '|---|---|---|---|---|---|' );
	for ( const f of frontend ) {
		L.push( `| \`${ esc( f.name ) }\` | ${ f.kind } | ${ esc( f.callback ) }${ f.at ? ` (${ f.at })` : '' } | ${ list( f.reads.map( esc ) ) } | ${ list( f.writes.filter( ( w ) => w !== 'db query' ).map( esc ) ) } | ${ f.rel }:${ f.line } |` );
	}
	L.push( '' );

	L.push( '## Registered settings' );
	L.push( '' );
	L.push( 'Saved by `options.php`, which checks the option group\'s capability and nonce itself' );
	L.push( 'and then calls the sanitize callback. A setting with no sanitize callback is stored' );
	L.push( 'as it arrived.' );
	L.push( '' );
	L.push( '| option | group | sanitize callback | where |' );
	L.push( '|---|---|---|---|' );
	for ( const s of settings ) {
		L.push( `| \`${ esc( s.option ) }\` | ${ esc( s.group ) } | ${ s.sanitize ? esc( s.sanitize ) : '**none**' } | ${ s.rel }:${ s.line } |` );
	}
	L.push( '' );

	L.push( '## Meta fields core exposes over REST' );
	L.push( '' );
	L.push( 'Registered with `show_in_rest`, which means core accepts writes to them on its own' );
	L.push( 'routes — `/wp/v2/media`, `/wp/v2/media_category` — without any route of ours being' );
	L.push( 'involved. The `auth_callback` is the gate, and its default is `edit_posts`.' );
	L.push( '' );
	L.push( '| key | on | sanitize | auth callback | capability | where |' );
	L.push( '|---|---|---|---|---|---|' );
	for ( const m of metaRows ) {
		L.push( `| \`${ esc( m.key ) }\` | ${ m.kind } meta | ${ esc( m.sanitize ?? '—' ) } | ${ esc( m.auth.name ) } | ${ list( m.caps.map( esc ) ) } | ${ m.rel }:${ m.line } |` );
	}
	L.push( '' );

	L.push( '## Filters and actions that act on the request' );
	L.push( '' );
	L.push( 'Not an endpoint, and that is the point: these run on somebody else\'s request, so the' );
	L.push( 'capability gate belongs to whatever screen they fire on, not to them.' );
	L.push( '' );
	L.push( '| hook | kind | callback | reads | writes | capability | nonce | where |' );
	L.push( '|---|---|---|---|---|---|---|---|' );
	for ( const h of inputHooks.sort( ( a, b ) => a.hook.localeCompare( b.hook ) ) ) {
		L.push( '| ' + [
			`\`${ esc( h.hook ) }\``,
			h.kind,
			esc( h.callback ),
			list( h.reads.map( esc ) ),
			list( h.writes.map( esc ) ),
			list( [ ...new Set( h.caps.map( ( c ) => ( c.object ? `${ c.cap }(${ c.object })` : c.cap ) ) ) ].map( esc ) ),
			list( [ ...new Set( h.nonces.map( ( n ) => n.fn ) ) ].map( esc ) ),
			`${ h.rel }:${ h.line }`,
		].join( ' | ' ) + ' |' );
	}
	L.push( '' );

	if ( duplicateFns.length ) {
		L.push( '## Functions defined more than once in the shipped tree' );
		L.push( '' );
		L.push( 'Only the first is analysed above. A second definition of the same name is a fatal' );
		L.push( 'error in PHP unless it is guarded, so each of these is either guarded or a bug.' );
		L.push( '' );
		L.push( '| function | first | again |' );
		L.push( '|---|---|---|' );
		for ( const d of duplicateFns ) L.push( `| \`${ esc( d.name ) }\` | ${ d.first } | ${ d.again } |` );
		L.push( '' );
	}

	return L.join( '\n' ) + '\n';
}

/* --------------------------------------------- who the gates let through */

/**
 *  The three lists tests/security/roles.php asserts against, derived from the
 *  permission callbacks rather than typed out.
 *
 *  The suite has to carry them as PHP -- it runs on the box with only itself
 *  shipped -- so they exist in two places by necessity. tests/security/surface.mjs
 *  parses the PHP copy and compares it with this, which is what stops the two
 *  from drifting apart and the suite from asserting last month's routes.
 */
const roleLists = ( () => {

	const key = ( r ) => `${ r.route } ${ r.methods }`;
	const gate = ( r ) => [ ...new Set( r.permCaps.map( ( c ) => c.cap ) ) ].sort().join( '+' );

	const authorMay = [];
	const objectScoped = [];
	const adminOnly = [];

	for ( const r of restRows ) {
		const g = gate( r );
		if ( g === 'edit_post' ) objectScoped.push( key( r ) );
		else if ( /manage_options|manage_network_options|delete_posts/.test( g ) ) adminOnly.push( key( r ) );
		else if ( g === 'upload_files' || g === 'edit_posts+upload_files' ) authorMay.push( key( r ) );
	}

	const sorted = ( a ) => [ ...a ].sort();

	return { authorMay: sorted( authorMay ), objectScoped: sorted( objectScoped ), adminOnly: sorted( adminOnly ) };
} )();

const model = { counted, roleLists, rest: restRows, ajax: ajaxRows, adminPost: postRows, cron: cronRows, meta: metaRows, screens, frontend, settings, inputHooks, duplicateFns };

if ( JSON_ONLY ) {
	process.stdout.write( JSON.stringify( model, ( k, v ) => ( k === 'body' ? undefined : v ), '\t' ) + '\n' );
	process.exit( 0 );
}

const text = markdown();

if ( CHECK ) {
	const have = fs.existsSync( OUT ) ? fs.readFileSync( OUT, 'utf8' ) : '';
	if ( have === text ) {
		console.log( `  pass  docs/security-surface.md matches the code — ${ counted.restEndpoints } REST endpoints, ${ counted.ajax } AJAX actions, ${ counted.adminPost } admin_post handlers, ${ counted.cron } cron hooks` );
		process.exit( 0 );
	}
	console.log( '  FAIL  docs/security-surface.md has drifted from the code. Run: node tools/security-surface.mjs' );
	process.exit( 1 );
}

fs.mkdirSync( path.dirname( OUT ), { recursive: true } );
fs.writeFileSync( OUT, text );

console.log( `  wrote docs/security-surface.md` );
console.log( `        ${ counted.restEndpoints } REST endpoints on ${ counted.restRoutes } routes (${ counted.restCalls } register_rest_route calls)` );
console.log( `        ${ counted.ajax } AJAX actions, ${ counted.adminPost } admin_post handlers, ${ counted.cron } cron hooks` );
console.log( `        ${ counted.screens } admin screens, ${ counted.frontend } front-end entries, ${ counted.settings } settings, ${ counted.metaRest } REST-exposed meta fields, ${ counted.inputHooks } input-reading hooks` );
console.log( `        ${ counted.noCapability } entries with no capability found, ${ counted.noNonce } without a nonce, ${ counted.unscopedOnId } unscoped over a request id` );
