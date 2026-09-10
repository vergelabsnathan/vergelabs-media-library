/**
 *  Every database call, classified one at a time.
 *
 *      node tools/db-calls.mjs            # write docs/security-db-calls.md
 *      node tools/db-calls.mjs --check    # fail if the file has drifted
 *      node tools/db-calls.mjs --json     # the model, for the suite
 *
 *  Phase 5.3 of plans/four-yesses.md. Each call is **prepared**, **interpolates
 *  only a `$wpdb` table name**, **interpolates only integers it cast itself**, or
 *  **is a finding** -- per call, not per file.
 *
 *  ## Why a tool and not a reading
 *
 *  Twelve of these were read and annotated safe on 2026-09-10, and the plan says
 *  plainly that this is exactly how a real one gets waved through. A reader gets
 *  tired at forty and there are two hundred. So the decision is mechanical and
 *  the same every time, and the burden is reversed: a call is a finding unless
 *  something in the code proves otherwise, and "it looked fine" is not a proof
 *  this tool can express.
 *
 *  ## What counts as proof
 *
 *  For an interpolated expression to be safe it must be one of:
 *
 *    - a `$wpdb` property -- a table name or prefix, which is ours and not the
 *      caller's,
 *    - cast at the point of use: `(int)`, `(float)`, `absint()`, `intval()`,
 *    - a variable whose every assignment inside the enclosing function is itself
 *      one of those, a numeric literal, `count()`, an `implode` of an
 *      `array_map( 'absint' | 'intval', … )`, or a helper on the proven list
 *      below,
 *    - a constant that resolves to a literal in our own source,
 *    - `$wpdb->esc_like()` inside a `prepare()`d `LIKE`.
 *
 *  Anything else is reported, with the expression, for a person to read. The tool
 *  does not decide those; it decides which ones a person still has to.
 *
 *  A `prepare()`d call is not automatically safe either. The format string is
 *  read as well, because `$wpdb->prepare( "… WHERE x = $id" )` is prepared and
 *  still interpolated -- that is the `UnescapedDBParameter` shape, and it is the
 *  one that looks safest in a diff.
 *
 *  @since 3.14
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const OUT = path.join( ROOT, 'docs', 'security-db-calls.md' );

const CHECK = process.argv.includes( '--check' );
const JSON_ONLY = process.argv.includes( '--json' );
const HASHES = process.argv.includes( '--hashes' );

/**
 *  The identity of one query: ten hex of the SHA-1 of its SQL, whitespace
 *  flattened.
 *
 *  Ten characters of hash over the whole statement, not a memorable excerpt of
 *  it. The first version matched an excerpt as a substring, which meant a review
 *  stayed valid when the query was *added to* -- `… IN ($in)` still contains
 *  `… IN ($in)` after ` AND kind = $kind` is appended, so a mutation that
 *  interpolated a fresh variable passed the suite. A hash of the whole thing has
 *  no such hole.
 */
const fingerprint = ( sql ) => createHash( 'sha1' ).update( sql.replace( /\s+/g, ' ' ).trim() ).digest( 'hex' ).slice( 0, 10 );

const SKIP = new Set( [
	'.git', '.github', '.claude', 'node_modules', 'playground',
	'tests', 'plans', 'tickets', 'docs', 'tools', 'research', 'dist',
	'.release-assets', 'assets', 'test-results',
] );

/** Methods that hand SQL to the server. `prepare` is not one: it builds. */
const RUNNERS = [ 'get_var', 'get_results', 'get_col', 'get_row', 'query' ];

/** Methods that build their own SQL from an array, so there is no string to read. */
const BUILDERS = [ 'insert', 'update', 'delete', 'replace' ];

/**
 *  Helpers proven, by reading them, to return only integers or an empty result.
 *
 *  Each is named here with the reason, and tests/security/db-calls.mjs asserts
 *  that each still casts -- a helper that loses its absint() while this list
 *  still trusts it is the quietest way for this whole classification to become
 *  wrong.
 */
/**
 *  Calls read by hand, with the reason, keyed by a fingerprint of their SQL.
 *
 *  The tool proves 191 of 202 on its own. The eleven below assemble their SQL from
 *  fragments across a nested loop or a function boundary, which is further than a
 *  static reader should be trusted to follow, so each was read and the reason
 *  written down.
 *
 *  **The key is the point.** A reason attached to a line number survives the line
 *  changing, and a reason attached to an excerpt survives the query being *added
 *  to* -- the first version of this register matched an excerpt as a substring,
 *  and a mutation that appended ` AND kind = $kind` to one of these queries kept
 *  its review and passed the suite. The key is ten hex of the SHA-1 of the whole
 *  statement, so any edit at all expires the review: the call becomes an
 *  unexplained finding and tests/security/db-calls.mjs goes red.
 *
 *  `node tools/db-calls.mjs --hashes` prints the fingerprint of every call that
 *  needs one.
 */
const REVIEWED = {

	'core/ai-index.php': [
		[ '77b42d1645',
		  "$in is implode of an array_chunk of $ids, and $ids is array_map( 'intval', (array) $ids ) on the function's first line -- integers, every one of them" ],
	],

	'core/ai-screen.php': [
		[ '56dead9d32',
		  'every $sums[] element is its own $wpdb->prepare() fragment, and the column alias after AS comes from a literal list of eight field names in the foreach header above it' ],
	],

	'core/guide.php': [
		[ 'f8e582bd4e',
		  "$chunk comes from array_chunk( array_map( function ( $r ) { return (int) $r['attachment_id']; }, $rows ), 500 ) by way of $chunks -- integers by construction" ],
	],

	'core/search-try.php': [
		[ '36af7892eb',
		  "each $any[] is $wpdb->prepare( \"{$column} LIKE %s\", $like ); $column comes from vergeml_search_try_fields(), a literal array of seven, and the search term goes through esc_like() and a placeholder" ],
		[ '3e7ba748c4',
		  'each $where_all[] is "( " . implode( " OR ", $any ) . " )" over the prepared fragments above, and {$from} interpolates two $wpdb table names and nothing else' ],
		[ '6a95da9eb6',
		  'the same as the row above, restricted to the three columns WordPress itself searches' ],
		[ 'db4c1ee30a',
		  'the rows query behind the same two assembled lists; LIMIT 30 is a literal' ],
	],

	'core/seo-context.php': [
		[ '1f45eb3c01',
		  "vergeml_seo_gap_sql() interpolates its $select argument, and both of its two call sites pass a hardcoded literal; the $keys and $stars lists inside it are array_map( 'esc_sql', ... )" ],
		[ 'ace3ecc40b',
		  'the same function, the other call site, also a hardcoded literal' ],
	],

	'core/smart-folders.php': [
		[ '77f8356ddc',
		  'the interpolated {$exclude} and each appended $branch[\'sql\'] come from two filters, not from a request; both of our own implementations build from $wpdb table names and constants, and every value is bound through prepare(). An extension point that accepts SQL -- see "Two filters that accept SQL" in the doc' ],
		[ '043d77dfa1',
		  'the same $core_sql, read a second time for the extended flag' ],
	],
};

const PROVEN_HELPERS = {
	vergeml_ids: 'maps absint over the array and drops anything falsy',
	absint: 'core',
	intval: 'core',
};


/* ------------------------------------------------------------- the payload */

function shippedPhp() {

	const tracked = execFileSync( 'git', [ 'ls-files', '-z' ], { cwd: ROOT, maxBuffer: 32 * 1024 * 1024 } )
		.toString()
		.split( '\0' )
		.filter( Boolean );

	return tracked
		.filter( ( rel ) => rel.endsWith( '.php' ) )
		.filter( ( rel ) => ! SKIP.has( rel.split( '/' )[ 0 ] ) )
		.filter( ( rel ) => fs.existsSync( path.join( ROOT, rel ) ) )
		.sort();
}


/* ------------------------------------------------------- a string-aware eye */

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

const lineAt = ( src, at ) => src.slice( 0, at ).split( '\n' ).length;


/* ------------------------------------------------------------- the sources */

const files = shippedPhp();
const src = new Map();
const funcs = new Map();
const constants = new Map();

for ( const rel of files ) {
	src.set( rel, blankComments( fs.readFileSync( path.join( ROOT, rel ), 'utf8' ) ) );
}

for ( const [ rel, text ] of src ) {

	for ( const m of text.matchAll( /\bconst\s+([A-Z_][A-Z0-9_]*)\s*=\s*('[^']*'|"[^"]*"|-?[0-9.]+)\s*;/g ) ) {
		constants.set( m[ 1 ], m[ 2 ] );
	}
	for ( const m of text.matchAll( /\bdefine\s*\(\s*'([A-Z_][A-Z0-9_]*)'\s*,\s*('[^']*'|"[^"]*"|-?[0-9.]+|true|false)/g ) ) {
		if ( ! constants.has( m[ 1 ] ) ) constants.set( m[ 1 ], m[ 2 ] );
	}
	for ( const m of text.matchAll( /^[ \t]*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/gm ) ) {
		if ( funcs.has( m[ 1 ] ) ) continue;
		const head = text.indexOf( '(', m.index );
		funcs.set( m[ 1 ], {
			rel,
			line: lineAt( text, m.index ),
			params: args( text, head ),
			body: block( text, head + args( text, head ).length + 2 ),
			start: m.index,
		} );
	}
}

function enclosingOf( rel, at ) {

	const text = src.get( rel );
	let best = null;
	let bestAt = -1;

	for ( const m of text.matchAll( /^[ \t]*function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/gm ) ) {
		if ( m.index <= at ) { best = m[ 1 ]; bestAt = m.index; } else break;
	}

	if ( best ) {
		/*
		 *  Looked up in this file. `funcs` keeps the first definition of a name
		 *  across the whole tree, and four Walker subclasses define start_el(), so
		 *  a name-only lookup can hand back a body from another file entirely --
		 *  and then prove a variable against assignments that are not there.
		 */
		const head = text.slice( bestAt );
		const open = head.indexOf( '(' );
		const body = block( head, open + args( head, open ).length + 2 );
		if ( at <= bestAt + body.length + 200 ) {
			return { rel, line: lineAt( text, bestAt ), params: args( head, open ), body, start: bestAt };
		}
	}

	/*
	 *  Top-level code in a script that is all top level -- uninstall.php -- has no
	 *  enclosing function, and without a scope a variable can never be proven.
	 *  The file is the scope there. It is wider than a function, but the rule that
	 *  one unproven contribution fails the whole call still holds, so a wider
	 *  scope can only ever add an assignment that makes the call a finding.
	 */
	return { rel, line: 0, params: '', body: text, start: 0 };
}


/* ----------------------------------------------- what goes into a SQL string */

/**
 *  Every interpolated or concatenated expression in a piece of SQL.
 *
 *  Three shapes, because PHP offers three and this codebase uses all of them:
 *  `{$x}` and `$x->y` inside a double-quoted string, and `' . $x . '` outside
 *  one. Single-quoted strings interpolate nothing, so only the parts that are
 *  not inside single quotes are searched for braces.
 */
function interpolations( sql ) {

	const found = [];

	/*
	 *  Split the expression into operands at top-level `.` first. Splitting on
	 *  whitespace instead -- which this did at first -- tears
	 *  `implode( ',', $chunk )` into `implode(` and `, $chunk )` and reports both as
	 *  unproven, so a call that is provably a list of integers reads as a finding.
	 *  A dot between two digits is a decimal point, not a concatenation.
	 */
	const operands = [];
	let depth = 0;
	let start = 0;
	let i = 0;

	const digit = ( c ) => c >= '0' && c <= '9';

	while ( i < sql.length ) {

		const c = sql[ i ];

		if ( c === "'" || c === '"' ) {
			const quote = c;
			i++;
			while ( i < sql.length ) {
				if ( sql[ i ] === '\\' ) { i += 2; continue; }
				if ( sql[ i ] === quote ) { i++; break; }
				i++;
			}
			continue;
		}

		if ( c === '(' || c === '[' ) depth++;
		if ( c === ')' || c === ']' ) depth--;

		if ( c === '.' && depth === 0 && ! ( digit( sql[ i - 1 ] ) && digit( sql[ i + 1 ] ) ) ) {
			operands.push( sql.slice( start, i ) );
			start = i + 1;
		}
		i++;
	}

	operands.push( sql.slice( start ) );

	for ( const raw of operands ) {

		const operand = raw.trim();
		if ( '' === operand ) continue;

		/* A single-quoted literal interpolates nothing at all. */
		if ( /^'(?:[^'\\]|\\.)*'$/.test( operand ) ) continue;

		/* A double-quoted literal interpolates what is inside it. */
		if ( /^"(?:[^"\\]|\\.)*"$/.test( operand ) ) {

			const inner = operand.slice( 1, -1 );

			for ( const m of inner.matchAll( /\{(\$[^}]*)\}/g ) ) found.push( m[ 1 ].trim() );

			for ( const m of inner.replace( /\{\$[^}]*\}/g, ' ' ).matchAll( /\$[A-Za-z_][A-Za-z0-9_]*(->[A-Za-z_][A-Za-z0-9_]*|\[[^\]]*\])*/g ) ) {
				found.push( m[ 0 ].trim() );
			}
			continue;
		}

		/* Anything else is an expression concatenated into the SQL. */
		found.push( operand );
	}

	return [ ...new Set( found.map( ( f ) => f.replace( /\s+/g, ' ' ).trim() ).filter( Boolean ) ) ];
}


const TABLE = /^\$wpdb->[A-Za-z_][A-Za-z0-9_]*$/;
const CAST_AT_USE = /^\(\s*(int|integer|float|double)\s*\)/;
const SAFE_CALL = /^(absint|intval|floatval|count|esc_sql)\s*\(/;
const NUMBER = /^-?[0-9]+(\.[0-9]+)?$/;
const PLACEHOLDERS = /^%[dsfF]$/;
const STRING = /^('(?:[^'\\]|\\.)*'|"(?:[^"$\\{]|\\.)*")$/;

/**
 *  Core functions that return an identifier or an integer and never the caller's
 *  text. Short on purpose: each one was read before it was added.
 *
 *  `get_option` is deliberately absent. An option is not request text, but it is
 *  text somebody with the settings screen chose, and an option interpolated as a
 *  SQL identifier is a stored injection with extra steps. Those stay findings.
 */
const SAFE_CORE = {
	_get_meta_table: 'a core table name from _get_meta_table()',
	get_current_user_id: 'the current user id, an integer',
	get_current_blog_id: 'the current blog id, an integer',
	get_locale: 'a core locale string',
};

/** Expressions that build a list of `%d` placeholders rather than values. */
const PLACEHOLDER_BUILD = /array_fill\s*\(|str_repeat\s*\(/;

/**
 *  The end of the statement starting at the front of `text`.
 *
 *  The first `;` that is not inside a string and not inside brackets. Using
 *  indexOf( ';' ) instead -- which this did -- cuts a SQL string in half the
 *  moment the SQL contains a semicolon, and half a string parses as anything at
 *  all. smart-folders.php's $core_sql is 40 lines of SQL and read as unproven
 *  for exactly that reason.
 */
function statement( text ) {

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

		if ( c === '(' || c === '[' || c === '{' ) depth++;
		if ( c === ')' || c === ']' || c === '}' ) depth--;
		if ( c === ';' && depth <= 0 ) return text.slice( 0, i );
		i++;
	}

	return text;
}

/**
 *  The header of every `foreach` whose loop variable is `$name`.
 *
 *  Found by reading each `foreach`'s own parentheses rather than by a regex
 *  reaching forward for `as $name`. A lazy regex anchors on the *earliest*
 *  foreach in the file and swallows everything up to the match, so the thing it
 *  reports as the iterated expression is whatever happened to lie between --
 *  which is why uninstall.php's loop over four string literals read as unproven.
 */
function foreachHeaders( body, name ) {

	const out = [];

	for ( const m of body.matchAll( /\bforeach\s*\(/g ) ) {

		const header = args( body, m.index + m[ 0 ].length - 1 );
		const as = header.match( new RegExp( 'as\\s*(?:\\$[A-Za-z_][A-Za-z0-9_]*\\s*=>\\s*)?\\$' + name + '\\s*$' ) );

		if ( as ) out.push( header.slice( 0, as.index ).trim() );
	}

	return out;
}

/**
 *  Does every interpolation in a piece of SQL-ish text prove itself?
 *
 *  The same question `proves()` asks of one expression, asked of a whole string.
 *  A variable holding a double-quoted string with `{$wpdb->posts}` in it is not a
 *  literal and is not an expression either, and without this it fell through
 *  every rule and read as unproven -- which is how `$from` in search-try.php,
 *  a string interpolating two table names and nothing else, came out a finding.
 */
function provesSql( text, enclosing, depth ) {

	const pieces = interpolations( text );

	if ( ! pieces.length ) return 'a literal, nothing interpolated';

	const reasons = [];

	for ( const piece of pieces ) {
		const why = proves( piece, enclosing, depth + 1 );
		if ( ! why ) return null;
		reasons.push( why );
	}

	return [ ...new Set( reasons ) ].join( ' / ' );
}

/**
 *  Is this one expression provably not the caller's text?
 *
 *  Returns the reason it is safe, or null. Null means a person reads it -- this
 *  function never guesses in the safe direction, and every rule below was added
 *  only after reading the code it describes.
 */
function proves( expr, enclosing, depth = 0 ) {

	const e = expr.trim().replace( /;$/, '' ).replace( /^\(\s*([\s\S]*)\s*\)$/, ( whole, inner ) =>
		/^[^()]*$/.test( inner ) ? inner : whole );

	if ( '' === e ) return 'empty';
	if ( TABLE.test( e ) ) return 'a $wpdb table name';
	if ( /^\$wpdb->(prefix|base_prefix)$/.test( e ) ) return 'the $wpdb prefix';
	if ( /^\$wpdb->get_blog_prefix\s*\(/.test( e ) ) return 'a $wpdb blog prefix';
	if ( NUMBER.test( e ) ) return 'a numeric literal';
	if ( PLACEHOLDERS.test( e ) ) return 'a prepare() placeholder';
	if ( STRING.test( e ) ) return 'a string literal in our own source';
	if ( CAST_AT_USE.test( e ) ) return 'cast at the point of use: ' + e.match( CAST_AT_USE )[ 0 ];
	if ( SAFE_CALL.test( e ) ) return 'wrapped in ' + e.match( SAFE_CALL )[ 1 ] + '()';

	/*
	 *  A prepared fragment, used as a value and concatenated into a larger query.
	 *  Safe when prepare()'s own format string is -- which is the check, because
	 *  prepare() around an already-interpolated string prepares nothing.
	 */
	if ( /^\$wpdb->prepare\s*\(/.test( e ) && depth < 6 ) {
		const format = commas( args( e, e.indexOf( '(' ) ) )[ 0 ] ?? '';
		const why = provesSql( format, enclosing, depth );
		return why ? 'a $wpdb->prepare() fragment whose format string holds only ' + why : null;
	}

	if ( /^\$wpdb->esc_like\s*\(/.test( e ) ) return 'escaped by $wpdb->esc_like()';

	const core = e.match( /^([a-z_][a-z0-9_]*)\s*\(/ );
	if ( core && SAFE_CORE[ core[ 1 ] ] ) return SAFE_CORE[ core[ 1 ] ];

	/* implode over a map of a casting function: the id-list shape. */
	/*
	 *  An empty array, opening a list that is appended to. It contributes nothing
	 *  to the SQL, and without this rule every `$x = array(); $x[] = …` pair --
	 *  which is how every assembled WHERE clause in this plugin is built -- failed
	 *  on its own initialiser.
	 */
	if ( /^(array\s*\(\s*\)|\[\s*\])$/.test( e ) ) return 'an empty array initialiser';

	/*
	 *  Array plumbing: it reorders and filters, it does not introduce text. What
	 *  is inside has to prove itself, and `array_map` has to be mapping something
	 *  that casts.
	 */
	const plumbing = e.match( /^(array_values|array_unique|array_filter|array_slice|array_keys|array_merge|array_chunk|array_map|array_fill)\s*\(/ );
	if ( plumbing && depth < 6 ) {

		const inside = commas( args( e, e.indexOf( '(' ) ) );

		if ( 'array_map' === plumbing[ 1 ] ) {
			const callback = inside[ 0 ] ?? '';
			if ( /^'(absint|intval|floatval)'$/.test( callback.trim() ) ) {
				return 'array_map( ' + callback.trim() + ', ... )';
			}
			if ( /^(static\s+)?function/.test( callback.trim() ) && /return\s*\(\s*(int|float)\s*\)/.test( callback ) ) {
				return 'array_map over a closure that returns a cast';
			}
			return null;
		}

		for ( const one of inside ) {
			const why = proves( one, enclosing, depth + 1 );
			if ( ! why ) return null;
		}

		return plumbing[ 1 ] + '() over ' + inside.length + ' proven argument(s)';
	}

	/* `join` is `implode`. taxonomies.php and options-pages.php use the alias. */
	if ( /^(implode|join)\s*\(/.test( e ) ) {

		const inside = args( e, e.indexOf( '(' ) );
		const mapped = inside.match( /array_map\s*\(\s*'(absint|intval)'/ );

		if ( mapped ) return "implode of array_map( '" + mapped[ 1 ] + "', ... )";
		if ( PLACEHOLDER_BUILD.test( inside ) ) return 'implode of a generated %d placeholder list';

		const parts = commas( inside );
		const last = parts[ parts.length - 1 ];
		if ( last && depth < 5 ) {
			const why = proves( last, enclosing, depth + 1 );
			if ( why ) return 'implode of ' + why;
		}
		return null;
	}

	/* rtrim/trim/substr of something provable is still provable. */
	const wrap = e.match( /^(rtrim|ltrim|trim|substr|sprintf)\s*\(/ );
	if ( wrap && depth < 5 ) {
		const inside = commas( args( e, e.indexOf( '(' ) ) );
		if ( inside.length ) {
			const why = proves( inside[ 0 ], enclosing, depth + 1 );
			if ( why ) return wrap[ 1 ] + '() of ' + why;
		}
		return null;
	}

	/* A constant from our own source that resolves to a literal. */
	const konst = e.match( /^([A-Z_][A-Z0-9_]*)$/ );
	if ( konst && constants.has( konst[ 1 ] ) ) {
		return 'the constant ' + konst[ 1 ] + ' = ' + constants.get( konst[ 1 ] );
	}

	if ( depth >= 5 ) return null;

	/*
	 *  One of our own functions. Provable if every expression it returns is
	 *  provable -- which is how a table-name helper like vergeml_index_table()
	 *  gets recognised as a table name instead of read as a mystery call.
	 */
	const ours = e.match( /^(vergeml_[A-Za-z0-9_]*)\s*\(/ );
	if ( ours && funcs.has( ours[ 1 ] ) ) {

		const fn = funcs.get( ours[ 1 ] );
		const returns = [ ...fn.body.matchAll( /\breturn\b/g ) ];

		if ( ! returns.length ) return null;

		const reasons = [];

		for ( const r of returns ) {
			const upto = statement( fn.body.slice( r.index + 'return'.length ) );
			if ( '' === upto.trim() ) continue;                 // a bare `return;`
			const why = proves( upto, fn, depth + 1 );
			if ( ! why ) return null;
			reasons.push( why );
		}

		return ours[ 1 ] + '() returns only ' + [ ...new Set( reasons ) ].join( ' / ' );
	}

	/*
	 *  A string, or a concatenation of strings and expressions, rather than one
	 *  expression. Asked the same question a whole query is asked -- which is what
	 *  makes a 40-line SQL constant holding two table names and some %s
	 *  placeholders provable, and a ternary arm holding one provable too.
	 */
	if ( /['"]/.test( e ) && depth < 6 ) {
		const why = provesSql( e, enclosing, depth );
		if ( why ) return 'text holding only ' + why;
	}

	/* A ternary: both arms have to prove it. */
	const tern = e.match( /^([\s\S]+?)\?([\s\S]+?):([\s\S]+)$/ );
	if ( tern && ! /\?\s*:/.test( e ) ) {
		const a = proves( tern[ 2 ], enclosing, depth + 1 );
		const b = proves( tern[ 3 ], enclosing, depth + 1 );
		if ( a && b ) return 'both arms of a ternary: ' + a + ' / ' + b;
		return null;
	}

	if ( ! enclosing ) return null;

	/* A plain variable: every assignment to it inside this function must prove it. */
	const varName = e.match( /^\$([A-Za-z_][A-Za-z0-9_]*)$/ );
	if ( ! varName ) return null;

	const name = varName[ 1 ];
	const body = enclosing.body;
	const reasons = [];

	/*
	 *  Plain `=` and appending `.=` both count. Reading only `=` made a variable
	 *  built by appending look like one with no assignment at all -- which this
	 *  tool reports as a finding, so it failed safe, but it failed without
	 *  saying what it had actually seen.
	 */
	/*
	 *  Plain `=`, appending `.=`, and appending `[] =`. Reading only `=` made a
	 *  variable built by appending look like one with no assignment at all, and
	 *  an array of prepared fragments -- the shape every IN clause and every
	 *  assembled WHERE in this plugin uses -- is built entirely with `[] =`.
	 */
	const assigns = [ ...body.matchAll( new RegExp( '\\$' + name + '\\s*(\\[\\s*\\]\\s*=|\\.?=)\\s*(?!=)', 'g' ) ) ];

	/* `foreach ( <something> as $name )` assigns it too. */
	const loops = foreachHeaders( body, name ).map( ( over ) => [ null, over ] );

	if ( ! assigns.length && ! loops.length ) return null;

	for ( const a of assigns ) {
		const from = a.index + a[ 0 ].length;
		const value = statement( body.slice( from ) );
		let why = proves( value, enclosing, depth + 1 );

		/*
		 *  Not one expression but a string, or a concatenation of them. Asked the
		 *  same question a whole query is asked.
		 */
		if ( ! why && /['"]/.test( value ) ) {
			const sqlWhy = provesSql( value, enclosing, depth + 1 );
			if ( sqlWhy ) why = 'a string holding only ' + sqlWhy;
		}

		if ( ! why ) return null;                 // one unproven contribution is enough
		reasons.push( ( '.=' === a[ 1 ] ? 'appended ' : '' ) + why );
	}

	for ( const l of loops ) {

		const over = l[ 1 ].trim();

		/*
		 *  `foreach ( array_chunk( <ints>, n ) as $chunk )` yields arrays of those
		 *  ints, and `array_map( 'intval', … )` or a closure that returns a cast is
		 *  what makes them ints. This is the id-batching shape: three call sites
		 *  chunk a list of attachment ids and interpolate each chunk.
		 */
		const chunked = over.match( /^array_chunk\s*\(/ );
		if ( chunked ) {
			const inner = commas( args( over, over.indexOf( '(' ) ) )[ 0 ] ?? '';
			if ( /array_map\s*\(\s*'(absint|intval)'/.test( inner ) ) {
				reasons.push( "each chunk of an array_map( 'intval', ... )" );
				continue;
			}
			if ( /array_map\s*\(\s*function[\s\S]*return\s*\(\s*int\s*\)/.test( inner ) ) {
				reasons.push( 'each chunk of a map whose closure returns (int)' );
				continue;
			}
			const why = proves( inner, enclosing, depth + 1 );
			if ( why ) { reasons.push( 'each chunk of ' + why ); continue; }
			return null;
		}

		/* Iterating a literal array only ever yields its literals. */
		const literalArray = over.match( /^(array\s*\(|\[)/ );
		if ( ! literalArray ) return null;
		const items = commas( args( over.replace( /^\[/, '(' ), over.indexOf( literalArray[ 0 ].startsWith( '[' ) ? '[' : '(' ) ) );
		for ( const item of items ) {
			const why = proves( item, enclosing, depth + 1 );
			if ( ! why ) return null;
		}
		reasons.push( 'each item of a literal array in our own source' );
	}

	return '$' + name + ' is only ever ' + [ ...new Set( reasons ) ].join( ' / ' );
}


/* ------------------------------------------------------------- the call sites */

const calls = [];

for ( const [ rel, text ] of src ) {

	for ( const m of text.matchAll( /\$wpdb->([A-Za-z_]+)\s*\(/g ) ) {

		const method = m[ 1 ];

		if ( ! RUNNERS.includes( method ) && ! BUILDERS.includes( method ) ) continue;

		const open = m.index + m[ 0 ].length - 1;
		const all = args( text, open );
		const line = lineAt( text, m.index );
		const enclosing = enclosingOf( rel, m.index );

		if ( BUILDERS.includes( method ) ) {
			/*
			 *  insert/update/delete build their own SQL from an array and escape
			 *  every value. The only thing a caller can reach is the table name,
			 *  which is the first argument.
			 */
			const table = commas( all )[ 0 ] ?? '';
			const why = proves( table.replace( /^["']|["']$/g, '' ).trim(), enclosing );
			calls.push( {
				rel, line, method,
				fn: enclosing ? `${ rel.replace( /.*\//, '' ) }:${ enclosing.line }` : '(top level)',
				inFunction: enclosing ? [ ...funcs.entries() ].find( ( [ , f ] ) => f === enclosing )?.[ 0 ] ?? '' : '',
				klass: TABLE.test( table.trim() ) || why ? 'builder' : 'finding',
				sql: `table ${ table.trim() }`,
				proofs: TABLE.test( table.trim() )
					? [ 'values escaped by $wpdb; the table is a $wpdb property' ]
					: ( why ? [ `values escaped by $wpdb; table: ${ why }` ] : [] ),
				unproven: TABLE.test( table.trim() ) || why ? [] : [ table.trim() ],
			} );
			continue;
		}

		const first = commas( all )[ 0 ] ?? '';
		const prepared = /^\$wpdb->prepare\s*\(/.test( first.trim() );

		/*
		 *  For a prepared call the string under examination is prepare()'s own
		 *  format string, not the call's argument: that is where an interpolated
		 *  value hides, and it hides well because the line says "prepare".
		 */
		const sql = prepared
			? ( commas( args( first, first.indexOf( '(' ) ) )[ 0 ] ?? '' )
			: first;

		const interp = interpolations( sql );
		const verdicts = interp.map( ( e ) => [ e, proves( e, enclosing ) ] );
		const unproven = verdicts.filter( ( [ , why ] ) => ! why ).map( ( [ e ] ) => e );

		/*
		 *  A finding the register names, matched on the SQL rather than the line.
		 *  Substring, because the doc truncates long SQL and a reviewer should be
		 *  able to quote the distinctive part rather than the whole statement.
		 */
		const mark = fingerprint( sql );
		const register = REVIEWED[ rel ] ?? [];
		const note = register.find( ( [ hash ] ) => hash === mark );

		let klass;
		if ( unproven.length && note ) klass = 'reviewed';
		else if ( unproven.length ) klass = 'finding';
		else if ( prepared ) klass = 'prepared';
		else if ( ! interp.length ) klass = 'literal';
		else if ( verdicts.every( ( [ , why ] ) => /table name|prefix/.test( why ) ) ) klass = 'table only';
		else klass = 'cast';

		calls.push( {
			rel, line, method, prepared,
			inFunction: enclosing ? [ ...funcs.entries() ].find( ( [ , f ] ) => f === enclosing )?.[ 0 ] ?? '' : '',
			klass,
			sql: sql.replace( /\s+/g, ' ' ).trim().slice( 0, 160 ),
			proofs: verdicts.filter( ( [ , why ] ) => why ).map( ( [ e, why ] ) => `${ e } — ${ why }` ),
			fingerprint: mark,
			reviewed: note ? note[ 1 ] : null,
			unproven,
		} );
	}
}

const counted = {
	files: files.length,
	total: calls.length,
	prepared: calls.filter( ( c ) => c.klass === 'prepared' ).length,
	literal: calls.filter( ( c ) => c.klass === 'literal' ).length,
	tableOnly: calls.filter( ( c ) => c.klass === 'table only' ).length,
	cast: calls.filter( ( c ) => c.klass === 'cast' ).length,
	builder: calls.filter( ( c ) => c.klass === 'builder' ).length,
	reviewed: calls.filter( ( c ) => c.klass === 'reviewed' ).length,
	findings: calls.filter( ( c ) => c.klass === 'finding' ).length,
	registered: Object.values( REVIEWED ).reduce( ( n, a ) => n + a.length, 0 ),
	prepareCalls: [ ...src.values() ].reduce( ( n, t ) => n + [ ...t.matchAll( /\$wpdb->prepare\s*\(/g ) ].length, 0 ),
	wpdbLines: [ ...src.values() ].reduce( ( n, t ) => n + t.split( '\n' ).filter( ( l ) => l.includes( '$wpdb->' ) ).length, 0 ),
};


/* ------------------------------------------------------------------- the doc */

const esc = ( s ) => String( s ?? '' ).replace( /\|/g, '\\|' ).replace( /\n/g, ' ' );

const CLASS_LABEL = {
	prepared: 'prepared',
	literal: 'a literal, nothing interpolated',
	'table only': 'only a `$wpdb` table name',
	cast: 'only integers it cast itself',
	builder: 'built and escaped by `$wpdb`',
	reviewed: 'read by hand — see the reason',
	finding: '**a finding**',
};

function markdown() {

	const L = [];

	L.push( '# Every database call, classified' );
	L.push( '' );
	L.push( '**Generated. Do not edit.** `node tools/db-calls.mjs` writes this file;' );
	L.push( '`node tools/db-calls.mjs --check` fails when the code has moved and this file has' );
	L.push( 'not. Phase 5.3 of `plans/four-yesses.md`.' );
	L.push( '' );
	L.push( 'One row per call, not per file. A call is a **finding** unless something in the' );
	L.push( 'code proves it is not the caller\'s text — the burden is that way round on purpose,' );
	L.push( 'because twelve of these were read and annotated safe on 2026-09-10 and that is' );
	L.push( 'exactly how a real one gets waved through.' );
	L.push( '' );
	L.push( 'A `prepare()`d call is read too. `$wpdb->prepare( "… WHERE x = $id" )` is prepared' );
	L.push( 'and still interpolated, which is the `UnescapedDBParameter` shape and the one that' );
	L.push( 'looks safest in a diff, so what is examined is `prepare()`\'s own format string.' );
	L.push( '' );

	L.push( '## The count' );
	L.push( '' );
	L.push( '| | count |' );
	L.push( '|---|---|' );
	L.push( `| lines mentioning \`$wpdb->\` | ${ counted.wpdbLines } |` );
	L.push( `| \`$wpdb->prepare()\` calls | ${ counted.prepareCalls } |` );
	L.push( `| **calls that reach the server** | **${ counted.total }** |` );
	L.push( `| · prepared | ${ counted.prepared } |` );
	L.push( `| · a literal, nothing interpolated | ${ counted.literal } |` );
	L.push( `| · only a \`$wpdb\` table name | ${ counted.tableOnly } |` );
	L.push( `| · only integers it cast itself | ${ counted.cast } |` );
	L.push( `| · built and escaped by \`$wpdb\` (insert/update/delete) | ${ counted.builder } |` );
	L.push( `| · read by hand, with the reason | ${ counted.reviewed } |` );
	L.push( `| · **findings** | **${ counted.findings }** |` );
	L.push( '' );
	L.push( 'Read over ' + counted.files + ' shipped PHP files.' );
	L.push( '' );

	const findings = calls.filter( ( c ) => c.klass === 'finding' );

	L.push( '## Findings' );
	L.push( '' );

	if ( ! findings.length ) {
		L.push( 'None. Every call that reaches the server is prepared, is a literal, interpolates' );
		L.push( 'only a `$wpdb` table name, interpolates only a value proven integer where it is' );
		L.push( 'written, or is built by `$wpdb` itself.' );
		L.push( '' );
	} else {
		L.push( 'Each row holds an expression the tool could not prove. That is not the same as a' );
		L.push( 'vulnerability — it is the list a person must read, and nothing else needs reading.' );
		L.push( '' );
		L.push( '| where | method | could not prove | SQL |' );
		L.push( '|---|---|---|---|' );
		for ( const c of findings ) {
			L.push( `| ${ c.rel }:${ c.line } | \`${ c.method }\` | \`${ esc( c.unproven.join( '`, `' ) ) }\` | \`${ esc( c.sql ) }\` |` );
		}
		L.push( '' );
	}

	const read = calls.filter( ( c ) => c.klass === 'reviewed' );

	L.push( '## Read by hand' );
	L.push( '' );
	L.push( 'The tool proves ' + ( counted.total - counted.reviewed - counted.findings ) + ' of ' + counted.total + ' calls on its own. These ' + counted.reviewed + ' assemble their' );
	L.push( 'SQL from fragments across a nested loop or a function boundary, which is further' );
	L.push( 'than a static reader should be trusted to follow, so each was read and the reason' );
	L.push( 'written down. Each is keyed by a hash of its own SQL, not by its line, so editing' );
	L.push( 'one of these queries expires its review and turns the suite red.' );
	L.push( '' );
	L.push( '| where | fingerprint | method | why it is safe |' );
	L.push( '|---|---|---|---|' );
	for ( const c of read ) {
		L.push( `| ${ c.rel }:${ c.line } | \`${ c.fingerprint }\` | \`${ c.method }\` | ${ esc( c.reviewed ) } |` );
	}
	L.push( '' );
	L.push( '### Two filters that accept SQL' );
	L.push( '' );
	L.push( '`core/smart-folders.php` builds the counts panel from two filters:' );
	L.push( '' );
	L.push( '- `vergeml_smart_count_exclude` — a string appended to each `WHERE`.' );
	L.push( '- `vergeml_smart_count_branches` — each branch contributing its own `sql` and `args`.' );
	L.push( '' );
	L.push( 'Neither carries request input and neither is reachable by a visitor: a filter can' );
	L.push( 'only be added by code already running on the site, and our own two implementations' );
	L.push( '(`core/quarantine.php`, `core/ai-folders.php`) build from `$wpdb` table names and' );
	L.push( 'constants with every value bound through `prepare()`.' );
	L.push( '' );
	L.push( 'It is still an extension point that accepts SQL, and a third-party plugin could pass' );
	L.push( 'request text into it without realising where it lands. The contract is not documented' );
	L.push( 'anywhere a plugin author would read it. **Found, not done:** say so in the docblock,' );
	L.push( 'and prefer a branch that passes `args` over one that interpolates.' );
	L.push( '' );

	L.push( '## Every call' );
	L.push( '' );
	L.push( 'Sorted by file. The proof column says why the tool is satisfied; where it names a' );
	L.push( 'variable, every assignment to that variable inside the enclosing function was' );
	L.push( 'checked, and one unproven assignment is enough to make the whole call a finding.' );
	L.push( '' );

	let current = '';

	for ( const c of calls ) {

		if ( c.rel !== current ) {
			current = c.rel;
			L.push( '' );
			L.push( `### ${ current }` );
			L.push( '' );
			L.push( '| line | in | method | class | proof |' );
			L.push( '|---|---|---|---|---|' );
		}

		L.push( '| ' + [
			c.line,
			c.inFunction ? `\`${ esc( c.inFunction ) }()\`` : '—',
			`\`${ c.method }\``,
			CLASS_LABEL[ c.klass ],
			c.klass === 'finding' ? `could not prove \`${ esc( c.unproven.join( '`, `' ) ) }\`` : ( c.proofs.length ? esc( c.proofs.join( '; ' ) ) : '—' ),
		].join( ' | ' ) + ' |' );
	}

	L.push( '' );

	L.push( '## The helpers this classification trusts' );
	L.push( '' );
	L.push( 'Named, with the reason. `tests/security/db-calls.mjs` asserts each still casts: a' );
	L.push( 'helper that loses its `absint()` while this list still trusts it is the quietest way' );
	L.push( 'for every row above to become wrong at once.' );
	L.push( '' );
	L.push( '| helper | why it is trusted |' );
	L.push( '|---|---|' );
	for ( const [ name, why ] of Object.entries( PROVEN_HELPERS ) ) {
		L.push( `| \`${ name }()\` | ${ why } |` );
	}
	L.push( '' );

	return L.join( '\n' ) + '\n';
}

const model = { counted, calls, provenHelpers: PROVEN_HELPERS, reviewed: REVIEWED };

if ( JSON_ONLY ) {
	process.stdout.write( JSON.stringify( model, null, '\t' ) + '\n' );
	process.exit( 0 );
}

if ( HASHES ) {
	for ( const call of calls ) {
		if ( ! call.unproven?.length ) continue;
		console.log( `${ call.rel }\t${ call.fingerprint }\t${ call.sql.slice( 0, 90 ) }` );
	}
	process.exit( 0 );
}

const text = markdown();

if ( CHECK ) {
	const have = fs.existsSync( OUT ) ? fs.readFileSync( OUT, 'utf8' ) : '';
	if ( have === text ) {
		console.log( `  pass  docs/security-db-calls.md matches the code — ${ counted.total } calls, ${ counted.findings } findings` );
		process.exit( 0 );
	}
	console.log( '  FAIL  docs/security-db-calls.md has drifted from the code. Run: node tools/db-calls.mjs' );
	process.exit( 1 );
}

fs.mkdirSync( path.dirname( OUT ), { recursive: true } );
fs.writeFileSync( OUT, text );

console.log( '  wrote docs/security-db-calls.md' );
console.log( `        ${ counted.total } calls reach the server, over ${ counted.wpdbLines } lines mentioning $wpdb-> and ${ counted.prepareCalls } prepare() calls` );
console.log( `        prepared ${ counted.prepared } · literal ${ counted.literal } · table only ${ counted.tableOnly } · cast ${ counted.cast } · builder ${ counted.builder }` );
console.log( `        read by hand ${ counted.reviewed } of ${ counted.registered } registered · findings ${ counted.findings }` );
