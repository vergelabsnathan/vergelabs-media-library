/*
 *  Right-to-left stylesheets, generated and committed.
 *
 *      node tools/rtl.mjs          # write css/<name>-rtl.css for every sheet below
 *      node tools/rtl.mjs --check  # exit 1 if any committed -rtl.css is stale
 *
 *  The plugin ships no build step, and this is not one: it is a developer
 *  tool that writes files which are committed like any other source. Running
 *  it is part of touching a stylesheet, and --check is in the validation gate
 *  so a forgotten run fails loudly instead of shipping a left-to-right
 *  Librarian to an Arabic site.
 *
 *  WordPress swaps <name>.css for <name>-rtl.css by itself on RTL locales once
 *  wp_style_add_data( handle, 'rtl', 'replace' ) is set -- see
 *  vergeml_rtl_styles() in the main plugin file. The three sheets that already
 *  had hand-written RTL files are left to their authors.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import rtlcss from 'rtlcss';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const CHECK = process.argv.includes( '--check' );

const SHEETS = [
	'vergeml-admin',
	'vergeml-shell',
	'vergeml-journey',
	'vergeml-librarian',
	'vergeml-media-list',
	'vergeml-gallery',
	'vergeml-folders',
	'vergeml-tree-view',
];

let stale = 0;

for ( const name of SHEETS ) {
	const src = path.join( ROOT, 'css', `${ name }.css` );
	const out = path.join( ROOT, 'css', `${ name }-rtl.css` );
	const generated = `/* Generated from ${ name }.css by tools/rtl.mjs -- do not edit; edit the source and rerun. */\n` + rtlcss.process( fs.readFileSync( src, 'utf8' ) );

	if ( CHECK ) {
		const current = fs.existsSync( out ) ? fs.readFileSync( out, 'utf8' ) : '';
		if ( current !== generated ) {
			console.log( `  stale  css/${ name }-rtl.css` );
			stale++;
		}
		continue;
	}

	fs.writeFileSync( out, generated );
	console.log( `  wrote  css/${ name }-rtl.css  (${ generated.length } bytes)` );
}

if ( CHECK ) {
	console.log( stale ? `\n  ${ stale } RTL sheet(s) behind their source -- run node tools/rtl.mjs` : '  rtl   up to date' );
	process.exit( stale ? 1 : 0 );
}
