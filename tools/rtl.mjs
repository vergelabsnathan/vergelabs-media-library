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
	'vergeml-media-list',
	'vergeml-gallery',
	'vergeml-folders',
	'vergeml-talk',
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

/*
 *  And the sheets this file does not generate.
 *
 *  css/eml-admin-media.css has an -rtl.css twin that is kept by hand -- it
 *  came with Enhanced Media Library and was never converted -- and it is not
 *  in SHEETS above. So `--check` answered "up to date" on 2026-09-09 while
 *  that twin was missing a whole block somebody had just added to the source,
 *  and an RTL site would have drawn a list with no indent and no bullet.
 *
 *  Not generated here: converting it wholesale would rewrite rules nobody has
 *  reviewed. Named instead, every run, so the next person editing one of these
 *  is told there is a second file to edit.
 */
const HAND_KEPT = fs.readdirSync( path.join( ROOT, 'css' ) )
	.filter( ( f ) => f.endsWith( '-rtl.css' ) )
	.map( ( f ) => f.replace( /-rtl\.css$/, '' ) )
	.filter( ( name ) => ! SHEETS.includes( name ) );

if ( HAND_KEPT.length ) {
	console.log( `\n  by hand, not generated -- edit both files:` );
	HAND_KEPT.forEach( ( name ) => console.log( `         css/${ name }.css  and  css/${ name }-rtl.css` ) );
}

if ( CHECK ) {
	console.log( stale ? `\n  ${ stale } RTL sheet(s) behind their source -- run node tools/rtl.mjs` : '\n  rtl   up to date (of the generated ones)' );
	process.exit( stale ? 1 : 0 );
}
