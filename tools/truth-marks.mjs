/*
 *  The truth page's marks into the answer key (S17).
 *
 *      node tools/truth-marks.mjs <marks.json> <page.html> [out.json]
 *
 *  marks.json is the page's own store as the ArtifactData tool saves it
 *  ({ marks: { id: termId | 0 | 'skip' } }); page.html the page that was
 *  marked (its folder list and the fill's picks are in its data block); the
 *  answer key (default tests/tree/truth-tech.json) is id => folder path, ''
 *  for no folder, a skipped picture left out. A picture not marked counts
 *  as the fill's pick being right -- the page pre-set it and the person
 *  passed it by.
 */
import fs from 'node:fs';

const [ , , marksFile, pageFile, outFile = 'tests/tree/truth-tech.json' ] = process.argv;
const doc = JSON.parse( fs.readFileSync( marksFile, 'utf8' ) );
const marks = doc.marks || {};
const html = fs.readFileSync( pageFile, 'utf8' );
const m = html.match( /<script id="data" type="application\/json">([\s\S]*?)<\/script>/ );
if ( ! m ) {
	throw new Error( 'no data block in the page' );
}
const data = JSON.parse( m[ 1 ].split( '<\\/' ).join( '</' ) );
const out = {};
let changed = 0, none = 0, skipped = 0;
for ( const c of data.cards ) {
	const v = marks[ c.id ] !== undefined ? marks[ c.id ] : c.pick;
	if ( v === 'skip' ) {
		skipped++;
		continue;
	}
	if ( marks[ c.id ] !== undefined && String( v ) !== String( c.pick ) ) {
		changed++;
	}
	if ( ! v ) {
		none++;
	}
	out[ c.id ] = v ? ( data.folders[ v ] || '' ) : '';
}
fs.writeFileSync( outFile, JSON.stringify( out, null, 1 ) + '\n' );
console.log( `${ outFile }: ${ Object.keys( out ).length } pictures · ${ changed } corrected · ${ none } no folder · ${ skipped } skipped` );
