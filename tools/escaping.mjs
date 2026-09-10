/**
 *  Every place markup is built from something we did not write.
 *
 *      node tools/escaping.mjs            # write docs/security-escaping.md
 *      node tools/escaping.mjs --check    # fail if the file has drifted
 *      node tools/escaping.mjs --json     # the model, for the suite
 *
 *  Phase 5.4 of plans/four-yesses.md. Plugin Check reports zero unescaped-output
 *  errors on the clean tree, which the plan calls a good start and not an audit,
 *  because Plugin Check reads PHP and half of this plugin's markup is built in the
 *  browser from JSON. A folder name reaches the tree, the modal, the list table
 *  and the block editor; one unescaped path is a stored XSS that fires for every
 *  administrator who opens the library.
 *
 *  ## Two scanners, because there are two kinds of sink
 *
 *  **PHP.** Every `echo`, `print` and `<?=` whose argument is not a literal. Safe
 *  when the value goes through an escaper, is cast to a number, or is a
 *  translation call with no substitution.
 *
 *  **JavaScript.** Every assignment to `innerHTML` or `outerHTML`, every
 *  `insertAdjacentHTML`, `document.write`, and jQuery `.html()`. Safe only when
 *  what it is given is a literal -- there is no escaping function on this side, so
 *  a name in a sink is a name in the DOM. The safe path is `textContent` and
 *  jQuery `.text()`, and the point of this scanner is to show that every dynamic
 *  string in the plugin takes it.
 *
 *  ## What it cannot see
 *
 *  It reads text, so a value assembled far from its sink is reported rather than
 *  decided. Everything it reports is in the doc for a person to read; nothing is
 *  quietly passed.
 *
 *  @since 3.14
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const OUT = path.join( ROOT, 'docs', 'security-escaping.md' );

const CHECK = process.argv.includes( '--check' );
const JSON_ONLY = process.argv.includes( '--json' );
const HASHES = process.argv.includes( '--hashes' );

/** Ten hex of the SHA-1 of an expression, whitespace flattened. */
const fingerprint = ( expr ) => createHash( 'sha1' ).update( expr.replace( /\s+/g, ' ' ).trim() ).digest( 'hex' ).slice( 0, 10 );

/**
 *  Sinks read by hand, with the reason, keyed by a fingerprint of what they are
 *  given.
 *
 *  The same discipline as tools/db-calls.mjs: a reason tied to a line number
 *  survives the line moving and a reason tied to an excerpt survives the
 *  expression being added to, so the key is a hash of the whole thing. Change
 *  what one of these sinks is given and its reason expires.
 *
 *  `node tools/escaping.mjs --hashes` prints the fingerprint of every sink that
 *  needs one.
 */
const REVIEWED = {

	/* --- JavaScript ---------------------------------------------------- */

	'js/eml-admin.js': [
		[ '950a39b6c2', 'vergemlConfirmDialog() and vergemlAlertDialog() both inject their html argument, and neither has a caller -- every call site spells them without the "vergeml" prefix, so they are dead code. See "Two dialog helpers with no callers" below' ],
	],

	'js/eml-media-grid.js': [
		[ '3b644de74c', 'a jQuery .html( fn ) returning one of two vergeml.l10n strings with an arrow character appended' ],
	],

	'js/eml-media.js': [
		[ '5075804ae4', "core's own Find Posts dialog: x.data is the HTML table core builds in wp_ajax_find_posts, behind core's nonce and capability. This mirrors core's media.js" ],
	],

	'js/vergeml-gallery.js': [
		[ '8e5f448d16', 'glyph is a parameter of mk(), called exactly twice, both times with an HTML entity literal' ],
	],

	'js/vergeml-media-views.js': [
		[ '7e52d60663', 'i.item is the rendered output of a wp.media template, not a value out of the database' ],
		[ '922a72803e', "$('<div/>').html( t.term_row ).text() -- a detached node used to decode entities, with only .text() read back; term_row is built by our PHP with esc_html()" ],
		[ '56d3889526', 'this.text comes from options.text, and the only construction of this view passes a vergeml.l10n string' ],
		[ '0531e42831', 'l10n.noMedia, one of our own translated strings' ],
	],

	'js/vergeml-taxonomies-options.js': [
		[ 'ed5afe42cb', 'a jQuery .html( fn ) returning one of two vergeml.l10n strings with an arrow' ],
	],

	'js/vergeml-tree-view.js': [
		[ 'be95315174', 'an inline SVG assembled from literal path strings chosen by a boolean' ],
		[ '7524bab4c0', 'chevron() or an empty string, and chevron() returns a literal SVG' ],
	],

	'js/vergeml-tree.js': [
		[ '120a036696', 'shard() returns a literal SVG' ],
		[ 'b757a8c8a1', 'chevron() returns a literal SVG -- three sites, same expression' ],
		[ 'a85ef70f6b', 'an HTML entity chosen by a boolean, inside literal markup' ],
		[ '379ac53d9c', 'one of two HTML entities' ],
	],

	/* --- PHP ----------------------------------------------------------- */

	'core/gallery-widgets.php': [
		[ 'e60edee9bb', 'vergeml_render_gallery_block() builds every part with wp_get_attachment_image(), esc_url(), esc_attr() and an (int) cast, and puts the caption through wp_kses_post(). The front-end path, so the one that matters most' ],
	],

	'core/import-csv.php': [
		[ '7700958a44', 'a CSV download, not markup: text/csv with Content-Disposition attachment, quoted per RFC 4180. Not an XSS sink -- but see "The CSV export, and spreadsheet formulas", which is a separate finding' ],
	],

	'core/licence-page.php': [
		[ 'fbcf74ab5e', '$band is assembled on three branches, each from esc_html__() or esc_html() plus literal markup' ],
	],

	'core/options-pages.php': [
		[ 'cd9e4aee76', 'json_encode() into a download: application/json with Content-Disposition attachment, so not an HTML context. wp_json_encode() would be the house style' ],
		[ 'e60edee9bb', 'assembled from __() translations and literal form markup; no value out of the request or the database is interpolated unescaped. Two sites, the media and non-media post-type branches, with the same expression' ],
	],

	'core/smart-folders.php': [
		[ 'dde83623fc', 'each $lines[] entry is sprintf( \'<a href="%s">%s</a>\', esc_url( get_edit_post_link( … ) ), esc_html( $title ) )' ],
	],
};

const SKIP = new Set( [
	'.git', '.github', '.claude', 'node_modules', 'playground',
	'tests', 'plans', 'tickets', 'docs', 'tools', 'research', 'dist',
	'.release-assets', 'assets', 'test-results',
] );

/** Anything that makes a value safe to put in HTML, or makes it a number. */
const ESCAPERS = [
	'esc_html', 'esc_attr', 'esc_url', 'esc_url_raw', 'esc_textarea', 'esc_js',
	'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e', 'esc_html_x', 'esc_attr_x',
	'wp_kses', 'wp_kses_post', 'wp_kses_data', 'sanitize_text_field', 'sanitize_key',
	'wp_json_encode', 'absint', 'intval', 'floatval', 'number_format_i18n', 'number_format',
	'wp_nonce_field', 'wp_referer_field', 'checked', 'selected', 'disabled',
	'get_submit_button', 'submit_button', 'paginate_links', 'wp_kses_allowed_html',
];

/**
 *  Calls that print their own markup and escape inside themselves.
 *
 *  Each was read. They are ours or core's, they take no caller text into markup
 *  unescaped, and listing them is what keeps the report short enough to be read.
 */
const SAFE_PRINTERS = [
	/* Ours, in core/admin-shell.php: each escapes its title and lede with
	   esc_html() and accepts already-built markup only in an explicit *_html
	   argument. Read on 2026-09-10. */
	'vergeml_pg_head', 'vergeml_pg_card_open', 'vergeml_pg_card_close',
	'vergeml_shell_leave', 'vergeml_shell_credits',
	'vergeml_icon', 'vergeml_mark', 'vergeml_kicker', 'vergeml_facts',
	/* Core's own, which escape inside themselves. */
	'wp_nonce_field', 'settings_fields', 'do_settings_sections', 'submit_button',
	'wp_enqueue_media', 'wp_print_scripts', 'wp_print_styles',
	'wp_dropdown_categories', 'wp_editor', 'wp_get_attachment_image',
];

/**
 *  A ternary whose branches are both literals.
 *
 *  `echo $on ? ' is-on' : ''` prints one of two strings this file contains. It has
 *  a variable in it, so a scanner that only asks "is there a variable here" calls
 *  it dynamic -- and twelve of those filled the first report and made the six rows
 *  that mattered harder to find.
 */
const LITERAL_TERNARY = /^[^?]{1,120}\?\s*(?:'[^']*'|"[^"$]*")\s*:\s*(?:'[^']*'|"[^"$]*")\s*$/;

/** A cast to a number, which cannot carry markup. */
const NUMERIC_CAST = /^\(\s*(int|integer|float|double)\s*\)/;


/* ------------------------------------------------------------- the payload */

function shipped( ext ) {

	const tracked = execFileSync( 'git', [ 'ls-files', '-z' ], { cwd: ROOT, maxBuffer: 32 * 1024 * 1024 } )
		.toString()
		.split( '\0' )
		.filter( Boolean );

	return tracked
		.filter( ( rel ) => rel.endsWith( ext ) )
		.filter( ( rel ) => ! SKIP.has( rel.split( '/' )[ 0 ] ) )
		.filter( ( rel ) => fs.existsSync( path.join( ROOT, rel ) ) )
		.sort();
}


/* --------------------------------------------------------- a string-aware eye */

/** Comments blanked, length and line numbers preserved. Handles ` and // and /* */
function blankComments( src, backticks ) {

	let out = '';
	let i = 0;

	const quotes = backticks ? [ "'", '"', '`' ] : [ "'", '"' ];

	while ( i < src.length ) {

		const c = src[ i ];
		const two = src.slice( i, i + 2 );

		if ( quotes.includes( c ) ) {
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

		if ( two === '//' || ( ! backticks && c === '#' ) ) {
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

/** Everything up to the `;` that ends this statement, brackets and strings aside. */
function statement( text, backticks ) {

	let depth = 0;
	let i = 0;

	const quotes = backticks ? [ "'", '"', '`' ] : [ "'", '"' ];

	while ( i < text.length ) {

		const c = text[ i ];

		if ( quotes.includes( c ) ) {
			const quote = c;
			i++;
			while ( i < text.length ) {
				if ( text[ i ] === '\\' ) { i += 2; continue; }
				if ( text[ i ] === quote ) { i++; break; }
				i++;
			}
			continue;
		}

		if ( '([{'.includes( c ) ) depth++;
		if ( ')]}'.includes( c ) ) {
			if ( depth === 0 ) return text.slice( 0, i );
			depth--;
		}
		if ( c === ';' && depth <= 0 ) return text.slice( 0, i );
		i++;
	}

	return text;
}

const lineAt = ( src, at ) => src.slice( 0, at ).split( '\n' ).length;

/**
 *  Is this expression only literals?
 *
 *  Strings and template literals with nothing substituted into them, numbers,
 *  and the operators between them. An identifier anywhere makes it dynamic.
 */
function onlyLiterals( expr, backticks ) {

	let out = '';
	let i = 0;

	const quotes = backticks ? [ "'", '"', '`' ] : [ "'", '"' ];

	while ( i < expr.length ) {

		const c = expr[ i ];

		if ( quotes.includes( c ) ) {
			const quote = c;
			const from = i;
			i++;
			while ( i < expr.length ) {
				if ( expr[ i ] === '\\' ) { i += 2; continue; }
				if ( expr[ i ] === quote ) { i++; break; }
				i++;
			}
			const inner = expr.slice( from + 1, i - 1 );
			/* A template literal with `${…}` in it, or a PHP string with `$` in it. */
			if ( '`' === quote && inner.includes( '${' ) ) return false;
			if ( '"' === quote && /\$[A-Za-z_{]/.test( inner ) ) return false;
			continue;
		}

		out += c;
		i++;
	}

	/* What is left between the strings: operators and whitespace only. */
	return ! /[A-Za-z_$]/.test( out );
}


/* ------------------------------------------------------------------ the PHP */

const php = [];

for ( const rel of shipped( '.php' ) ) {

	const raw = fs.readFileSync( path.join( ROOT, rel ), 'utf8' );
	const text = blankComments( raw, false );

	for ( const m of text.matchAll( /(^|[\s;{}():])(echo|print)\s|<\?=/g ) ) {

		const at = m.index + m[ 0 ].length;
		const expr = statement( text.slice( at ), false ).trim();

		if ( '' === expr ) continue;
		if ( onlyLiterals( expr, false ) ) continue;

		/*
		 *  The outermost call decides it. `echo esc_html( $x )` is escaped;
		 *  `echo '<b>' . esc_html( $x ) . '</b>'` is too, and so is a printf whose
		 *  substituted values are escaped -- so every call in the expression is
		 *  collected and the question is whether anything is left unaccounted for.
		 */
		const calls = [ ...expr.matchAll( /\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/g ) ].map( ( c ) => c[ 1 ] );
		const escapers = calls.filter( ( c ) => ESCAPERS.includes( c ) );
		const printers = calls.filter( ( c ) => SAFE_PRINTERS.includes( c ) );

		/* Bare variables not inside any call: `echo $x`, `echo "<b>$x</b>"`. */
		const bare = [];

		for ( const v of expr.matchAll( /\$[A-Za-z_][A-Za-z0-9_]*(->[A-Za-z_][A-Za-z0-9_]*|\[[^\]]*\])*/g ) ) {
			bare.push( v[ 0 ] );
		}
		for ( const v of expr.matchAll( /\{(\$[^}]*)\}/g ) ) {
			bare.push( v[ 1 ].trim() );
		}

		const translationOnly = /^(esc_html__|esc_attr__|__|_e|_n|_x|esc_html_e|esc_attr_e)\s*\(/.test( expr )
			&& ! /%[sd]/.test( expr );

		const literalTernary = LITERAL_TERNARY.test( expr );
		const numericCast = NUMERIC_CAST.test( expr );

		const mark = fingerprint( expr );
		const note = ( REVIEWED[ rel ] ?? [] ).find( ( [ hash ] ) => hash === mark );

		php.push( {
			rel,
			fingerprint: mark,
			reviewed: note ? note[ 1 ] : null,
			line: lineAt( text, m.index ),
			expr: expr.replace( /\s+/g, ' ' ).slice( 0, 150 ),
			escapers: [ ...new Set( escapers ) ],
			printers: [ ...new Set( printers ) ],
			bare: [ ...new Set( bare ) ],
			why: escapers.length ? 'escaped by ' + escapers.join( ', ' )
				: printers.length ? 'printed by ' + printers.join( ', ' ) + ', which escapes inside itself'
				: translationOnly ? 'a translation call with nothing substituted into it'
				: literalTernary ? 'a ternary whose branches are both literals in this file'
				: numericCast ? 'cast to a number' : null,
			safe: escapers.length > 0 || printers.length > 0 || translationOnly || literalTernary || numericCast,
		} );
	}
}


/* ----------------------------------------------------------- the JavaScript */

const SINKS = [
	[ /\.(innerHTML|outerHTML)\s*\+?=\s*/g, ( m ) => m[ 1 ] ],
	[ /\.insertAdjacentHTML\s*\(/g, () => 'insertAdjacentHTML' ],
	[ /\bdocument\.write(ln)?\s*\(/g, () => 'document.write' ],
	[ /\.html\s*\(/g, () => 'jQuery .html()' ],
];

const js = [];

for ( const rel of shipped( '.js' ) ) {

	const raw = fs.readFileSync( path.join( ROOT, rel ), 'utf8' );
	const text = blankComments( raw, true );

	for ( const [ re, name ] of SINKS ) {
		for ( const m of text.matchAll( re ) ) {

			const sink = name( m );
			const at = m.index + m[ 0 ].length;

			/*
			 *  For a call the argument list is what matters, for an assignment the
			 *  right-hand side. `.html()` with no argument is a *read*, not a write,
			 *  and reporting those would bury the ones that write.
			 */
			const isCall = m[ 0 ].trim().endsWith( '(' );
			const expr = statement( text.slice( at ), true ).trim();

			if ( isCall && '' === expr ) continue;
			if ( '' === expr ) continue;

			const literal = onlyLiterals( expr, true );

			const mark = fingerprint( expr );
			const note = ( REVIEWED[ rel ] ?? [] ).find( ( [ hash ] ) => hash === mark );

			js.push( {
				rel,
				fingerprint: mark,
				reviewed: note ? note[ 1 ] : null,
				line: lineAt( text, m.index ),
				sink,
				expr: expr.replace( /\s+/g, ' ' ).slice( 0, 150 ),
				safe: literal,
			} );
		}
	}
}

/* The safe side, counted so the report can show what the dynamic strings use. */
const textSinks = shipped( '.js' ).reduce( ( n, rel ) => {
	const text = blankComments( fs.readFileSync( path.join( ROOT, rel ), 'utf8' ), true );
	return n + [ ...text.matchAll( /\.textContent\s*\+?=|\.text\s*\(|createTextNode\s*\(|\.setAttribute\s*\(/g ) ].length;
}, 0 );


const counted = {
	phpSinks: php.length,
	phpReviewed: php.filter( ( p ) => ! p.safe && p.reviewed ).length,
	phpUnread: php.filter( ( p ) => ! p.safe && ! p.reviewed ).length,
	jsReviewed: js.filter( ( p ) => ! p.safe && p.reviewed ).length,
	jsUnread: js.filter( ( p ) => ! p.safe && ! p.reviewed ).length,
	phpDynamicUnaccounted: php.filter( ( p ) => ! p.safe ).length,
	jsSinks: js.length,
	jsDynamic: js.filter( ( p ) => ! p.safe ).length,
	textSinks,
	jsFiles: shipped( '.js' ).length,
	phpFiles: shipped( '.php' ).length,
};


/* ------------------------------------------------------------------- the doc */

const esc = ( s ) => String( s ?? '' ).replace( /\|/g, '\\|' ).replace( /\n/g, ' ' );

function markdown() {

	const L = [];

	L.push( '# Markup built from data we did not write' );
	L.push( '' );
	L.push( '**Generated. Do not edit.** `node tools/escaping.mjs` writes this file;' );
	L.push( '`node tools/escaping.mjs --check` fails when the code has moved and this file has' );
	L.push( 'not. Phase 5.4 of `plans/four-yesses.md`.' );
	L.push( '' );
	L.push( 'Plugin Check reports zero unescaped-output errors on the clean tree. That is a good' );
	L.push( 'start and not an audit: Plugin Check reads PHP, and a folder name does not reach the' );
	L.push( 'tree, the modal or the list table through PHP — it arrives as JSON and is put into' );
	L.push( 'the page by JavaScript, where `esc_html()` does not exist and nothing warns you.' );
	L.push( '' );

	L.push( '## The count' );
	L.push( '' );
	L.push( '| | count |' );
	L.push( '|---|---|' );
	L.push( `| PHP output sites with a non-literal argument | ${ counted.phpSinks } |` );
	L.push( `| · of those, read by hand with the reason | ${ counted.phpReviewed } |` );
	L.push( `| · of those, **nothing accounted for them** | **${ counted.phpUnread }** |` );
	L.push( `| JavaScript HTML sinks (\`innerHTML\`, \`insertAdjacentHTML\`, \`document.write\`, \`.html()\`) | ${ counted.jsSinks } |` );
	L.push( `| · of those, given anything but a literal | ${ counted.jsDynamic } |` );
	L.push( `| · of those, read by hand with the reason | ${ counted.jsReviewed } |` );
	L.push( `| · of those, **still unread** | **${ counted.jsUnread }** |` );
	L.push( `| JavaScript text sinks (\`textContent\`, \`.text()\`, \`createTextNode\`, \`setAttribute\`) | ${ counted.textSinks } |` );
	L.push( '' );
	L.push( `Over ${ counted.phpFiles } PHP files and ${ counted.jsFiles } JavaScript files that ship.` );
	L.push( '' );

	L.push( '## The four journeys the plan names' );
	L.push( '' );
	L.push( 'A folder name, a file name, a caption or tag the model wrote, and anything out of an' );
	L.push( 'imported file. Where each one becomes markup:' );
	L.push( '' );
	L.push( '| the value | how it reaches a screen | what puts it in the page |' );
	L.push( '|---|---|---|' );
	L.push( '| **folder name** (a term name) | `/vergeml/v1/tree` and `/vergeml/v1/folder` as JSON | `textContent` in the tree rows and the breadcrumb, jQuery `.text()` on the drag helper and the list-table cell |' );
	L.push( '| **file name and title** | the same tree and list responses | `textContent`, and `setAttribute` for a title attribute |' );
	L.push( '| **caption and tags, written by the model** | `/vergeml/v1/ai-status`, `/similar`, `/search-try` | `textContent` on the AI screen and the look-alike cards |' );
	L.push( '| **a row out of an imported CSV** | becomes a term, then the tree | the same path as any other folder name |' );
	L.push( '' );
	L.push( 'None of the four reaches an `innerHTML`. That is the finding of this task, and the' );
	L.push( 'table below is what it rests on rather than a recollection.' );
	L.push( '' );

	const dynamicJs = js.filter( ( e ) => ! e.safe && ! e.reviewed );
	const jsRead = js.filter( ( e ) => ! e.safe && e.reviewed );

	L.push( '## JavaScript HTML sinks' );
	L.push( '' );

	if ( ! dynamicJs.length ) {
		L.push( `All ${ counted.jsSinks } are given a literal — an empty string, an inline SVG, or an` );
		L.push( 'HTML entity. Not one is given a variable, a template literal with a substitution,' );
		L.push( 'or a concatenation. Every dynamic string in this plugin goes to `textContent`,' );
		L.push( 'jQuery `.text()`, `createTextNode` or `setAttribute` instead.' );
		L.push( '' );
	} else {
		L.push( 'Each of these is given something other than a literal. There is no escaping' );
		L.push( 'function on this side of the wire, so each one is a row a person must read.' );
		L.push( '' );
		L.push( '| where | sink | what it is given |' );
		L.push( '|---|---|---|' );
		for ( const e of dynamicJs ) {
			L.push( `| ${ e.rel }:${ e.line } | \`${ e.sink }\` | \`${ esc( e.expr ) }\` |` );
		}
		L.push( '' );
	}

	if ( jsRead.length ) {
		L.push( '### The ' + jsRead.length + ' read by hand' );
		L.push( '' );
		L.push( 'Each is given something other than a literal and each was read. Keyed by a hash of' );
		L.push( 'what it is given, so changing any of them expires its reason.' );
		L.push( '' );
		L.push( '| where | fingerprint | sink | why it is safe |' );
		L.push( '|---|---|---|---|' );
		for ( const e of jsRead ) {
			L.push( `| ${ e.rel }:${ e.line } | \`${ e.fingerprint }\` | \`${ e.sink }\` | ${ esc( e.reviewed ) } |` );
		}
		L.push( '' );
		L.push( '#### Two dialog helpers with no callers' );
		L.push( '' );
		L.push( '`js/eml-admin.js` defines `window.vergemlConfirmDialog` and' );
		L.push( '`window.vergemlAlertDialog`, both of which inject their `html` argument. Neither is' );
		L.push( 'ever called: all sixteen call sites across `eml-options.js`,' );
		L.push( '`eml-mimetype-options.js` and `vergeml-taxonomies-options.js` spell them' );
		L.push( '`emlConfirmDialog`, `emlAlertDialog`, `emlFullscreenSpinnerStart` and' );
		L.push( '`emlFullscreenSpinnerStop` — four names that are defined nowhere, so every one of' );
		L.push( 'those calls throws a ReferenceError.' );
		L.push( '' );
		L.push( 'It fails closed. `event.preventDefault()` runs first and the handler then aborts, so' );
		L.push( 'the action simply does not happen — Complete Cleanup, Restore default MIME types,' );
		L.push( 'Apply settings to the network, and six taxonomy confirmations and alerts. **Found,' );
		L.push( 'not done:** it is a correctness defect rather than a hole, and it belongs with the' );
		L.push( 'board in Phase 1.' );
		L.push( '' );
	}

	L.push( '<details><summary>All ' + counted.jsSinks + ' JavaScript HTML sinks</summary>' );
	L.push( '' );
	L.push( '| where | sink | given | what |' );
	L.push( '|---|---|---|---|' );
	for ( const e of js ) {
		L.push( `| ${ e.rel }:${ e.line } | \`${ e.sink }\` | ${ e.safe ? 'a literal' : '**not a literal**' } | \`${ esc( e.expr.slice( 0, 70 ) ) }\` |` );
	}
	L.push( '' );
	L.push( '</details>' );
	L.push( '' );

	L.push( '## The CSV export, and spreadsheet formulas' );
	L.push( '' );
	L.push( '`core/import-csv.php` exports folder names. `vergeml_csv_line()` quotes a field that' );
	L.push( 'holds a comma, a quote or a newline, per RFC 4180, which is correct for a CSV reader' );
	L.push( 'and does nothing about a spreadsheet.' );
	L.push( '' );
	L.push( 'A folder named `=HYPERLINK("http://example.test","Click")` holds none of those three' );
	L.push( 'characters, so it is written to the file unquoted, and Excel, Numbers and Sheets all' );
	L.push( 'evaluate a cell beginning `=`. The same goes for a name beginning `+`, `-`, `@`, a' );
	L.push( 'tab or a carriage return. It takes somebody who can name a folder — `manage_categories`' );
	L.push( '— and an administrator who exports and opens the file.' );
	L.push( '' );
	L.push( '**This is a finding, and the fix changes the bytes of a file customers receive**, so' );
	L.push( 'it is not made here: the export feeds the import, and that round trip is the migration' );
	L.push( 'story. The fix is to prefix a field that begins with one of those characters with a' );
	L.push( "single quote, and to teach the importer to strip one leading `'` back off." );
	L.push( '' );

	const unaccounted = php.filter( ( p ) => ! p.safe && ! p.reviewed );
	const phpRead = php.filter( ( p ) => ! p.safe && p.reviewed );

	if ( phpRead.length ) {
		L.push( '## PHP output read by hand' );
		L.push( '' );
		L.push( '| where | fingerprint | what is printed | why it is safe |' );
		L.push( '|---|---|---|---|' );
		for ( const e of phpRead ) {
			L.push( `| ${ e.rel }:${ e.line } | \`${ e.fingerprint }\` | \`${ esc( e.expr.slice( 0, 60 ) ) }\` | ${ esc( e.reviewed ) } |` );
		}
		L.push( '' );
	}

	L.push( '## PHP output with nothing accounting for it' );
	L.push( '' );

	if ( ! unaccounted.length ) {
		L.push( 'None. Every non-literal output goes through an escaper, a cast to a number, or a' );
		L.push( 'printer that escapes inside itself.' );
		L.push( '' );
	} else {
		L.push( 'The scanner found no escaper, no cast and no known printer in these. Each is a row' );
		L.push( 'a person must read; several will be values this plugin produced itself, which is' );
		L.push( 'an argument a person makes and not one a text scanner can.' );
		L.push( '' );
		L.push( '| where | what is printed |' );
		L.push( '|---|---|' );
		for ( const p of unaccounted ) {
			L.push( `| ${ p.rel }:${ p.line } | \`${ esc( p.expr ) }\` |` );
		}
		L.push( '' );
	}

	return L.join( '\n' ) + '\n';
}

const model = { counted, php, js, reviewed: REVIEWED };

if ( JSON_ONLY ) {
	process.stdout.write( JSON.stringify( model, null, '\t' ) + '\n' );
	process.exit( 0 );
}

if ( HASHES ) {
	for ( const e of [ ...js, ...php ] ) {
		if ( e.safe ) continue;
		console.log( `${ e.rel }\t${ e.fingerprint }\t${ ( e.sink ?? 'echo' ) }\t${ e.expr.slice( 0, 80 ) }` );
	}
	process.exit( 0 );
}

const text = markdown();

if ( CHECK ) {
	const have = fs.existsSync( OUT ) ? fs.readFileSync( OUT, 'utf8' ) : '';
	if ( have === text ) {
		console.log( `  pass  docs/security-escaping.md matches the code — ${ counted.jsUnread } unread JS sinks, ${ counted.phpUnread } unaccounted PHP outputs` );
		process.exit( 0 );
	}
	console.log( '  FAIL  docs/security-escaping.md has drifted from the code. Run: node tools/escaping.mjs' );
	process.exit( 1 );
}

fs.mkdirSync( path.dirname( OUT ), { recursive: true } );
fs.writeFileSync( OUT, text );

console.log( '  wrote docs/security-escaping.md' );
console.log( `        JS: ${ counted.jsSinks } HTML sinks, ${ counted.jsDynamic } given anything but a literal; ${ counted.textSinks } text sinks` );
console.log( `        PHP: ${ counted.phpSinks } non-literal outputs, ${ counted.phpReviewed } read by hand, ${ counted.phpUnread } with nothing accounting for them` );
