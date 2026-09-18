/*
 *  A text model as the matcher, scored against the truth (S17).
 *
 *      node tools/truth-model.mjs docs/truth/2026-09-18-tech-truth.html tests/tree/truth-tech.json [model]
 *
 *  Nathan, marking the truth page (2026-09-18): "the descriptions are
 *  almost always spot on." If the describer's words are that good, the
 *  strongest cheap matcher may be a text call: the folder tree and forty
 *  descriptions per call, no pictures, the model naming a folder or none
 *  for each. This runs that once over the page's pictures and scores it
 *  the way tools/box-truth-score.php scores the rules -- right / broad /
 *  wrong / none, the wrong pairs -- so the two lines sit side by side.
 *  Through OpenRouter (the key read from ../service/.env.local, never
 *  printed); answers cached in the scratchpad per model, so a re-run
 *  costs nothing. Default model: anthropic/claude-haiku-4.5.
 */
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';

const [ , , pageFile, truthFile, model = 'anthropic/claude-haiku-4.5' ] = process.argv;
const BATCH = 40;

const env = fs.readFileSync( path.resolve( '../service/.env.local' ), 'utf8' );
const key = ( env.match( /^OPENROUTER_API_KEY=(.+)$/m ) || [] )[ 1 ]?.trim().replace( /^["']|["']$/g, '' );
if ( ! key ) {
	console.error( 'no OPENROUTER_API_KEY in ../service/.env.local' );
	process.exit( 1 );
}

const html = fs.readFileSync( pageFile, 'utf8' );
const data = JSON.parse( html.match( /<script id="data" type="application\/json">([\s\S]*?)<\/script>/ )[ 1 ].split( '<\\/' ).join( '</' ) );
const truth = JSON.parse( fs.readFileSync( truthFile, 'utf8' ) );
const folders = Object.values( data.folders ).filter( ( p ) => ! /^to sort$/i.test( p ) ).sort(); // To sort is the fill's own residue, never a choice.
const cards = data.cards.filter( ( c ) => truth[ c.id ] !== undefined );

const cacheDir = path.join( os.tmpdir(), 'vgml-truth-model' );
fs.mkdirSync( cacheDir, { recursive: true } );
const cacheFile = path.join( cacheDir, `${ model.replace( /[^a-z0-9.-]/gi, '_' ) }-${ path.basename( pageFile ) }.json` );
let answers = {};
try { answers = JSON.parse( fs.readFileSync( cacheFile, 'utf8' ) ); } catch ( e ) { answers = {}; }

const system = `You file pictures into a media library's folders. You are given the folder tree (one path per line, "Parent > Child") and a list of pictures, each with a short description written by a vision model: the main object; its class; then a one-sentence caption. For each picture answer the ONE folder path it belongs in, copied exactly from the tree, or null when no folder of this tree fits it (a picture that is about something the tree has no folder for belongs nowhere -- do not force it into the nearest folder). Prefer the most specific folder that is right; a parent only when no child fits. Answer with a JSON object mapping the picture id to the path or null, and nothing else.`;

async function ask( batch ) {
	const list = batch.map( ( c ) => `${ c.id }: ${ c.says } — ${ c.caption }` ).join( '\n' );
	const body = {
		model,
		temperature: 0,
		messages: [
			{ role: 'system', content: system },
			{ role: 'user', content: `Folders:\n${ folders.join( '\n' ) }\n\nPictures:\n${ list }` },
		],
	};
	const res = await fetch( 'https://openrouter.ai/api/v1/chat/completions', {
		method: 'POST',
		headers: { Authorization: `Bearer ${ key }`, 'Content-Type': 'application/json' },
		body: JSON.stringify( body ),
	} );
	if ( ! res.ok ) {
		throw new Error( `${ res.status } ${ await res.text() }` );
	}
	const j = await res.json();
	const text = j.choices[ 0 ].message.content;
	const m = text.match( /\{[\s\S]*\}/ );
	if ( ! m ) {
		throw new Error( 'no JSON in the answer: ' + text.slice( 0, 200 ) );
	}
	return { parsed: JSON.parse( m[ 0 ] ), usage: j.usage || {} };
}

let calls = 0, tokens = 0;
for ( let i = 0; i < cards.length; i += BATCH ) {
	const batch = cards.slice( i, i + BATCH ).filter( ( c ) => answers[ c.id ] === undefined );
	if ( ! batch.length ) {
		continue;
	}
	const { parsed, usage } = await ask( batch );
	calls++;
	tokens += ( usage.prompt_tokens || 0 ) + ( usage.completion_tokens || 0 );
	for ( const c of batch ) {
		answers[ c.id ] = parsed[ c.id ] === undefined ? null : parsed[ c.id ];
	}
	fs.writeFileSync( cacheFile, JSON.stringify( answers, null, 1 ) );
}

// The score, as the rules are scored.
const norm = ( p ) => String( p || '' ).toLowerCase().replace( /\s*>\s*/g, ' > ' ).trim();
const known = new Set( folders.map( norm ) );
const bands = { placed: { right: 0, broad: 0, wrong: 0, n: 0 }, none: { right: 0, wrong: 0, n: 0 } };
const pairs = {};
let unknown = 0;
for ( const c of cards ) {
	const want = norm( truth[ c.id ] );
	let got = norm( answers[ c.id ] );
	if ( got && ! known.has( got ) ) {
		unknown++;
		got = '';
	}
	if ( ! got ) {
		bands.none.n++;
		if ( ! want ) { bands.none.right++; } else { bands.none.wrong++; }
		continue;
	}
	bands.placed.n++;
	let fate;
	if ( ! want ) { fate = 'wrong'; } else if ( got === want ) { fate = 'right'; } else if ( want.startsWith( got + ' > ' ) ) { fate = 'broad'; } else { fate = 'wrong'; }
	bands.placed[ fate ]++;
	if ( 'wrong' === fate ) {
		const k = `${ want || '(no folder)' }  ->  ${ got }`;
		pairs[ k ] = ( pairs[ k ] || 0 ) + 1;
	}
}
const right = bands.placed.right + bands.none.right;
console.log( `model ${ model } · ${ calls } calls this run, ${ tokens } tokens · ${ cards.length } pictures${ unknown ? ` · ${ unknown } answers named no folder of the tree (read as none)` : '' }` );
console.log( `placed   ${ String( bands.placed.n ).padStart( 4 ) } · right ${ bands.placed.right } (${ Math.round( 100 * bands.placed.right / Math.max( 1, bands.placed.n ) ) }%) · broad ${ bands.placed.broad } · wrong ${ bands.placed.wrong } (${ Math.round( 100 * bands.placed.wrong / Math.max( 1, bands.placed.n ) ) }%)` );
console.log( `none     ${ String( bands.none.n ).padStart( 4 ) } · right to leave ${ bands.none.right } · should have placed ${ bands.none.wrong }` );
console.log( `SCORE right ${ right } of ${ cards.length } (${ Math.round( 100 * right / cards.length ) }%) · right of placed ${ Math.round( 100 * bands.placed.right / Math.max( 1, bands.placed.n ) ) }%` );
console.log( '\nwrong pairs, most first:' );
for ( const [ k, n ] of Object.entries( pairs ).sort( ( a, b ) => b[ 1 ] - a[ 1 ] ).slice( 0, 15 ) ) {
	console.log( `${ String( n ).padStart( 3 ) }  ${ k }` );
}
