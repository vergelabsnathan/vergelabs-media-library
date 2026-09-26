/*
 *  The tree lab: folder trees proposed offline and scored against the truth (2026-09-26).
 *
 *      node tools/tree-lab.mjs <export.json> baseline <summary.json> [run]
 *      node tools/tree-lab.mjs <export.json> bottomup [run]
 *      node tools/tree-lab.mjs <export.json> score <tree.json> [--min N] [--file]
 *
 *  export.json is tools/box-filing-export.php's (every labelled picture with
 *  its describer record and its truth folder); summary.json is
 *  tools/box-guide-summary.php's (what the Folders screen's planner reads).
 *
 *    baseline   today's planner: the guide's open turn (service lib/guide.ts
 *               guideRules + treeShape, lib/guide-stream.ts streamPrompt,
 *               verbatim at service 80f6a4f), from scratch, on the summary.
 *               Its pictures are then filed by the model (the says prompt).
 *    bottomup   every picture's classes, as distinct phrases with counts, go
 *               to the model, which merges them into a vocabulary and
 *               arranges that as a tree; every picture lands in its phrase's
 *               folder. Then the rules, in code: minimum pictures, one-child
 *               parents collapse, depth three.
 *
 *  Scored per picture against the truth, names ignored: pair precision /
 *  recall / F1 (two pictures together in the truth and in the tree), at the
 *  leaf and at the top level; purity; truth folders of at least five
 *  recovered; leftover; folders, depth, same names. Through OpenRouter (key
 *  from ../../service/.env.local, never printed); answers cached per run in
 *  the OS temp dir, so a re-score is free.
 */
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import crypto from 'node:crypto';

const require_hash = ( s ) => crypto.createHash( 'md5' ).update( s ).digest( 'hex' ).slice( 0, 10 );

const argv = process.argv.slice( 2 );
const flag = ( n, d ) => ( argv.includes( n ) ? argv[ argv.indexOf( n ) + 1 ] : d );
const [ exportFile, mode, a3, a4 ] = argv.filter( ( a, i ) => ! a.startsWith( '--' ) && '--min' !== argv[ i - 1 ] );
const MIN = Number( flag( '--min', 5 ) );
const MODEL = 'anthropic/claude-sonnet-5';
const data = JSON.parse( fs.readFileSync( exportFile, 'utf8' ) );
const pictures = data.pictures.slice().sort( ( a, b ) => a.id - b.id );
const base = path.basename( exportFile, '.json' );
const cacheDir = path.join( os.tmpdir(), 'vgml-tree-lab' );
fs.mkdirSync( cacheDir, { recursive: true } );

const env = fs.readFileSync( path.resolve( '../../service/.env.local' ), 'utf8' );
const key = ( env.match( /^OPENROUTER_API_KEY=(.+)$/m ) || [] )[ 1 ]?.trim().replace( /^["']|["']$/g, '' );
let spent = 0;
async function ask( system, user, maxTokens = 8000, temperature, prefill = '' ) {
	const messages = [ { role: 'system', content: system }, { role: 'user', content: user } ];
	const body = { model: MODEL, max_tokens: maxTokens, usage: { include: true }, reasoning: { enabled: false }, messages };
	if ( prefill ) {
		body.response_format = { type: 'json_schema', json_schema: { name: 'tree', strict: true, schema: prefill } };
	}
	if ( temperature !== undefined ) {
		body.temperature = temperature;
	}
	const res = await fetch( 'https://openrouter.ai/api/v1/chat/completions', { method: 'POST', headers: { Authorization: `Bearer ${ key }`, 'Content-Type': 'application/json' }, body: JSON.stringify( body ) } );
	if ( ! res.ok ) {
		throw new Error( `${ res.status } ${ await res.text() }` );
	}
	const j = await res.json();
	spent += ( j.usage && j.usage.cost ) || 0;
	if ( ! j.choices[ 0 ].message.content ) {
		throw new Error( 'empty answer, finish ' + j.choices[ 0 ].finish_reason + ', usage ' + JSON.stringify( j.usage ) );
	}
	return String( j.choices[ 0 ].message.content );
}
const cached = async ( name, fn ) => {
	const f = path.join( cacheDir, name );
	if ( fs.existsSync( f ) ) {
		return JSON.parse( fs.readFileSync( f, 'utf8' ) );
	}
	const v = await fn();
	fs.writeFileSync( f, JSON.stringify( v, null, 1 ) );
	return v;
};

const classes = ( p ) => [ ...new Set( String( ( p.filing || {} ).object || '' ).toLowerCase().split( /\s*[;,]\s*/ ).map( ( s ) => s.trim() ).filter( Boolean ) ) ];
const norm = ( s ) => String( s || '' ).toLowerCase().replace( /\s*>\s*/g, ' > ' ).trim();

/* ---------------------------------------------------------------- trees */

// {folders:[{name,parent}]} -> path per folder name (first of a name wins, as the plugin resolves parents today).
function paths( folders ) {
	const byName = {};
	for ( const f of folders ) {
		if ( ! ( f.name in byName ) ) {
			byName[ f.name ] = f;
		}
	}
	const pathOf = ( f, g = 0 ) => ( f.parent && byName[ f.parent ] && g < 8 ? pathOf( byName[ f.parent ], g + 1 ) + ' > ' : '' ) + f.name;
	return folders.map( ( f ) => pathOf( f ) );
}

/* ------------------------------------------------------------ baseline */

const guideRules =
	'You help the owner of a WordPress media library arrive at a folder structure that fits their site. '
	+ 'You reason from the evidence you are given (the library summary: groups, counts, what share of pictures name a brand, a size, an audience) and from what the owner says. Rules:\n'
	+ '1. A folder tree nests one way. When the owner wants two or three axes (by size, colour and brand), choose ONE axis as folders on the evidence -- the one named most often and grouping most evenly -- propose the others as tags, show the consequence, and ask which they meant.\n'
	+ '2. A folder needs pictures behind it. Use the group sizes and the evidence shares; do not propose a folder the catalogue cannot fill, and when asked for one, say the number ("only 8% name a colour; that folder would be mostly empty").\n'
	+ '3. Split by audience (men, women, kids) only where the audience share supports it; say the number.\n'
	+ '4. Kinds are gates: logos, screenshots, diagrams, documents get folders of their own kind or none.\n'
	+ '5. Keep the names the owner gave. Never rename silently. Never put a slash in a name; nesting is expressed only through "parent".\n'
	+ '6. When something is unclear, ask ONE question with two to four concrete choices rather than guess. When a request contradicts the evidence, say the evidence and ask; do not refuse.\n'
	+ '7. Answer the question behind the words. "I run a tech blog" means folders follow topics and publishing, not dates.\n'
	+ 'You never act on the library. You only change the draft. The owner files it when they confirm.\n'
	+ 'Nothing you describe has happened. Speak about the draft, never about the library. Never say done, placed, routed, or that nothing is left unfiled. If the owner asks whether the pictures are in folders, the answer is that the draft is ready and filing it is what moves them. Give no counts of your own; the screen beside you carries the real ones.';
const treeShape =
	'A folder is {"name": string, "parent": string, "matches": string, "classes": string[], "kinds": string[], "audience": string, "count": number}. '
	+ '"name" is a label, sentence case, never a path. "parent" is the exact name of another folder in the same list or "". '
	+ '"matches" is a short visual phrase of what belongs there. "classes" are the object classes a picture would carry ("footwear", "monitor"), two to five, most important first. '
	+ '"kinds" from photo, illustration, screenshot, document, diagram, logo. "audience" is "men", "women", "kids" or "". "count" is your estimate from the evidence. '
	+ 'A folder that exists today carries "id", a number: keep that id on that folder whatever you rename it to or move it under, never give it to another folder, and give a new folder no id. '
	+ 'A tree is {"folders": Folder[], "tags": [{"name": string, "values": string[]}]}.';
const FENCE = '```';

async function baseline( summaryFile, run ) {
	const summary = JSON.parse( fs.readFileSync( summaryFile, 'utf8' ) );
	summary.folders = []; // From scratch: the planner proposes a tree for an unfiled library.
	summary.unfiled = summary.total;
	const system = `${ guideRules }\n\n${ treeShape }\n\n`
		+ 'Reply in two parts. First, what you say to the owner: short, specific, names folders. '
		+ 'Where there is more than one fact, one fact per line, each line starting with "- ". At most one question, at the end. No JSON in this part. '
		+ `Then, on its own line, a fenced block that starts with ${ FENCE }tree and ends with ${ FENCE }, holding JSON `
		+ '{"folders": Folder[], "tags": [{"name": string, "values": string[]}], "choices": string[]}: '
		+ 'the WHOLE tree as it should be after this turn (never a diff; send it even when nothing changed), '
		+ 'and in "choices" two or three short answers to your question, or [] when you asked none. Nothing after the closing fence.';
	const user = `Library summary (JSON):\n${ JSON.stringify( summary ) }\n\nThere are no folders yet.\n\nThe owner's goal: (not stated)\n\nThe current draft (JSON):\n${ JSON.stringify( { folders: [], tags: [] } ) }\n\nThe conversation so far:\n(none)\n\nThe owner has just opened the conversation. In two short lines say what you read in the library -- the counts that matter -- then ask one question about how they want the folders.`;
	const text = await cached( `${ base }-baseline-${ run }.json`, () => ask( system, user, 6000 ) );
	let tree = JSON.parse( text.match( /```tree\s*([\s\S]*?)```/ )[ 1 ] );
	if ( ! tree.folders.length && tree.choices && tree.choices.length ) {
		// The open turn asks before it proposes (measured 3 of 3): the owner takes its own first choice, as the screen's first chip.
		const said = text.split( FENCE )[ 0 ].trim();
		const user2 = user.split( '\n\nThe conversation so far:' )[ 0 ].replace( /The current draft \(JSON\):\n.*$/s, `The current draft (JSON):\n${ JSON.stringify( { folders: [], tags: [] } ) }` )
			+ `\n\nThe conversation so far:\nYou: ${ said }\n\nThe owner chose: "${ tree.choices[ 0 ] }".`;
		const text2 = await cached( `${ base }-baseline-${ run }-turn2.json`, () => ask( system, user2, 6000 ) );
		tree = JSON.parse( text2.match( /```tree\s*([\s\S]*?)```/ )[ 1 ] );
	}
	return { folders: tree.folders.map( ( f ) => ( { name: f.name, parent: f.parent || '' } ) ), assign: null, said: text.split( '```' )[ 0 ].trim() };
}

/* ------------------------------------------------------------ bottom-up */

const TREE_SCHEMA = { type: 'object', additionalProperties: false, required: [ 'folders' ], properties: { folders: { type: 'array', items: { type: 'object', additionalProperties: false, required: [ 'name', 'parent', 'phrases' ], properties: { name: { type: 'string' }, parent: { type: 'string' }, phrases: { type: 'array', items: { type: 'string' } } } } } } };

async function bottomup( run ) {
	// Every picture as its phrase: first class; second class; kind when not a photo. Audience counted per phrase.
	const phrases = {};
	for ( const p of pictures ) {
		const c = classes( p );
		const kind = p.kind && 'photo' !== p.kind ? ` [${ p.kind }]` : '';
		const k = `${ c[ 0 ] || '?' }; ${ c[ 1 ] || '' }${ kind }`;
		const x = ( phrases[ k ] = phrases[ k ] || { n: 0, aud: {} } );
		x.n++;
		const a = ( ( p.filing || {} ).audience || '' ).toLowerCase();
		if ( a ) {
			x.aud[ a ] = ( x.aud[ a ] || 0 ) + 1;
		}
	}
	const list = Object.entries( phrases ).sort( ( a, b ) => b[ 1 ].n - a[ 1 ].n );
	const ids = {};
	const lines = list.map( ( [ k, x ], i ) => {
		ids[ `p${ i }` ] = k;
		const aud = Object.entries( x.aud ).map( ( [ a, n ] ) => `${ a } ${ n }` ).join( ', ' );
		return `p${ i }: ${ k } — ${ x.n }${ aud ? ` (${ aud })` : '' }`;
	} );
	const system = 'You design the folder tree of a media library from what is in it. You get every distinct description phrase in the library -- the main object; its broader class; [kind] when the picture is not a photo -- with how many pictures carry it and, where known, their audience. '
		+ 'Merge phrases that mean the same thing, then arrange folders as a tree. Rules:\n'
		+ '1. A folder needs at least 3 pictures behind it (count the phrases you put in it). A phrase too rare for a folder of its own goes into its broader folder.\n'
		+ '2. At most three levels. A parent has two to twelve children; never a parent with a single child. A folder of more than 20 pictures whose phrases fall into clear kinds gets those kinds as children, down to the third level.\n'
		+ '3. Every folder name is unique in the whole tree, a plain label of at most three words, in the language of the phrases, sentence case, no slash.\n'
		+ '4. Split by audience (men, women, kids) only where the counts support it on both sides.\n'
		+ '5. Kinds (logo, screenshot, diagram, document, illustration) get a folder of their own kind only when they have enough pictures; otherwise they sit with their subject.\n'
		+ '6. A phrase that fits no folder of a sensible tree maps to null: it stays unfiled. Never force it.\n'
		+ 'Answer JSON only, no reasoning before it: {"folders": [{"name": string, "parent": string, "phrases": ["p0", "p7", ...]}]} -- "parent" is the exact name of another folder or ""; "phrases" lists the ids of the phrases whose pictures belong in THIS folder (its most specific fit). A phrase that belongs nowhere is listed in no folder.';
	const text = await cached( `${ base }-bottomup-${ run }.json`, () => ask( system, `Phrases (${ list.length }, ${ pictures.length } pictures):\n${ lines.join( '\n' ) }`, 16000, 0, TREE_SCHEMA ) );
	const j = JSON.parse( text.slice( 0, text.lastIndexOf( '}' ) + 1 ) );
	j.assign = {};
	j.folders.forEach( ( f ) => ( f.phrases || [] ).forEach( ( id ) => { j.assign[ id ] = f.name; } ) );
	const folders = j.folders.map( ( f ) => ( { name: f.name, parent: f.parent || '' } ) );
	const ps = paths( folders );
	const pathOfName = {};
	folders.forEach( ( f, i ) => { if ( ! ( f.name in pathOfName ) ) { pathOfName[ f.name ] = ps[ i ]; } } );
	const assign = {};
	for ( const p of pictures ) {
		const c = classes( p );
		const kind = p.kind && 'photo' !== p.kind ? ` [${ p.kind }]` : '';
		const k = `${ c[ 0 ] || '?' }; ${ c[ 1 ] || '' }${ kind }`;
		const id = Object.keys( ids ).find( ( x ) => ids[ x ] === k );
		const name = j.assign[ id ];
		assign[ p.id ] = name && pathOfName[ name ] ? pathOfName[ name ] : '';
	}
	return { folders, assign };
}

/* --------------------------------------------------- the rules, in code */

// Minimum pictures (a small leaf hands its pictures to its parent, or to the leftover), one-child parents collapse, depth three.
function rules( assign, min ) {
	const a = { ...assign };
	for ( const id in a ) {
		const parts = a[ id ] ? a[ id ].split( ' > ' ) : [];
		a[ id ] = parts.slice( 0, 3 ).join( ' > ' );
	}
	for ( let pass = 0; pass < 10; pass++ ) {
		const direct = {};
		for ( const id in a ) {
			if ( a[ id ] ) {
				direct[ a[ id ] ] = ( direct[ a[ id ] ] || 0 ) + 1;
			}
		}
		const all = new Set( Object.keys( direct ) );
		for ( const f of Object.keys( direct ) ) {
			const parts = f.split( ' > ' );
			for ( let i = 1; i < parts.length; i++ ) {
				all.add( parts.slice( 0, i ).join( ' > ' ) );
			}
		}
		const total = ( f ) => Object.entries( direct ).filter( ( [ g ] ) => g === f || g.startsWith( f + ' > ' ) ).reduce( ( s, [ , n ] ) => s + n, 0 );
		const kids = ( f ) => [ ...all ].filter( ( g ) => g.startsWith( f + ' > ' ) && ! g.slice( f.length + 3 ).includes( ' > ' ) );
		let changed = false;
		for ( const f of all ) {
			const up = f.includes( ' > ' ) ? f.slice( 0, f.lastIndexOf( ' > ' ) ) : '';
			if ( total( f ) < min ) {
				for ( const id in a ) {
					if ( a[ id ] === f || a[ id ].startsWith( f + ' > ' ) ) {
						a[ id ] = up;
						changed = true;
					}
				}
			} else if ( 1 === kids( f ).length ) {
				const only = kids( f )[ 0 ];
				for ( const id in a ) {
					if ( a[ id ] === only || a[ id ].startsWith( only + ' > ' ) ) {
						a[ id ] = f + a[ id ].slice( only.length );
						changed = true;
					}
				}
			}
		}
		if ( ! changed ) {
			break;
		}
	}
	return a;
}

/* ---------------------------------------------- filing into a tree (says) */

async function fileInto( folderPaths, tag ) {
	const system = 'You file pictures into a media library\'s folders. You are given the folder tree (one path per line, "Parent > Child") and a list of pictures, each with a short description written by a vision model: the main object; its class; then a one-sentence caption. For each picture answer the ONE folder path it belongs in, copied exactly from the tree, or null when no folder of this tree fits it (a picture that is about something the tree has no folder for belongs nowhere -- do not force it into the nearest folder). Prefer the most specific folder that is right; a parent only when no child fits. Answer with a JSON object mapping the picture id to the path or null, and nothing else.';
	const tree = [ ...new Set( folderPaths ) ].sort();
	const known = new Map( tree.map( ( t ) => [ norm( t ), t ] ) );
	const treeKey = require_hash( tree.join( '\n' ) );
	const out = await cached( `${ base }-file-${ tag }-${ treeKey }.json`, async () => {
		const answers = {};
		const batches = [];
		for ( let i = 0; i < pictures.length; i += 40 ) {
			batches.push( pictures.slice( i, i + 40 ) );
		}
		for ( let i = 0; i < batches.length; i += 2 ) {
			await Promise.all( batches.slice( i, i + 2 ).map( async ( b ) => {
				const list = b.map( ( p ) => `${ p.id }: ${ classes( p ).join( '; ' ) }${ p.caption ? ` — ${ p.caption }` : '' }` ).join( '\n' );
				const t = await ask( system, `Folders:\n${ tree.join( '\n' ) }\n\nPictures:\n${ list }`, 4096, 0 );
				Object.assign( answers, JSON.parse( t.match( /\{[\s\S]*\}/ )[ 0 ] ) );
			} ) );
		}
		return answers;
	} );
	const assign = {};
	for ( const p of pictures ) {
		const g = out[ p.id ];
		assign[ p.id ] = g && known.has( norm( g ) ) ? known.get( norm( g ) ) : '';
	}
	return assign;
}

/* --------------------------------------------------------------- score */

function score( assign, label ) {
	const pairs = ( level ) => {
		const cut = ( s ) => ( s ? ( 'top' === level ? s.split( ' > ' )[ 0 ] : s ) : '' );
		const cell = {}, rowT = {}, rowP = {};
		for ( const p of pictures ) {
			const t = cut( p.truth ), g = cut( assign[ p.id ] );
			if ( t ) {
				rowT[ t ] = ( rowT[ t ] || 0 ) + 1;
			}
			if ( g ) {
				rowP[ g ] = ( rowP[ g ] || 0 ) + 1;
			}
			if ( t && g ) {
				cell[ t + '\u0000' + g ] = ( cell[ t + '\u0000' + g ] || 0 ) + 1;
			}
		}
		const c2 = ( n ) => ( n * ( n - 1 ) ) / 2;
		const tp = Object.values( cell ).reduce( ( s, n ) => s + c2( n ), 0 );
		const pp = Object.values( rowP ).reduce( ( s, n ) => s + c2( n ), 0 );
		const tt = Object.values( rowT ).reduce( ( s, n ) => s + c2( n ), 0 );
		const P = tp / Math.max( 1, pp ), R = tp / Math.max( 1, tt );
		return { P, R, F: ( 2 * P * R ) / Math.max( 1e-9, P + R ) };
	};
	const leaf = pairs( 'leaf' ), top = pairs( 'top' );
	const inFolder = {};
	for ( const p of pictures ) {
		if ( assign[ p.id ] ) {
			( inFolder[ assign[ p.id ] ] = inFolder[ assign[ p.id ] ] || [] ).push( p );
		}
	}
	let pure = 0, placed = 0;
	const majority = {};
	for ( const [ f, ps ] of Object.entries( inFolder ) ) {
		const t = {};
		ps.forEach( ( p ) => { t[ p.truth || '(none)' ] = ( t[ p.truth || '(none)' ] || 0 ) + 1; } );
		const [ best, n ] = Object.entries( t ).sort( ( a, b ) => b[ 1 ] - a[ 1 ] )[ 0 ];
		majority[ f ] = { best, n, size: ps.length };
		pure += n;
		placed += ps.length;
	}
	const truthSize = {};
	pictures.forEach( ( p ) => { if ( p.truth ) { truthSize[ p.truth ] = ( truthSize[ p.truth ] || 0 ) + 1; } } );
	const big = Object.entries( truthSize ).filter( ( [ , n ] ) => n >= 5 );
	const recovered = big.filter( ( [ t, n ] ) => Object.values( majority ).some( ( m ) => m.best === t && m.n >= n / 2 && m.n >= m.size / 2 ) ).length;
	const nowhere = pictures.filter( ( p ) => ! p.truth );
	const folderList = Object.keys( inFolder );
	const leafNames = folderList.map( ( f ) => f.split( ' > ' ).pop().toLowerCase() );
	const same = leafNames.length - new Set( leafNames ).size;
	const pc = ( x ) => `${ Math.round( 100 * x ) }%`;
	console.log( `${ label.padEnd( 26 ) } F1 leaf ${ pc( leaf.F ) } (P ${ pc( leaf.P ) } R ${ pc( leaf.R ) }) · F1 top ${ pc( top.F ) } · purity ${ pc( pure / Math.max( 1, placed ) ) } · recovered ${ recovered }/${ big.length } · leftover ${ pc( ( pictures.length - placed ) / pictures.length ) }${ nowhere.length ? ` · belongs-nowhere placed ${ nowhere.filter( ( p ) => assign[ p.id ] ).length }/${ nowhere.length }` : '' } · folders ${ folderList.length } · depth ${ Math.max( 0, ...folderList.map( ( f ) => f.split( ' > ' ).length ) ) } · same names ${ same }` );
}

/* ---------------------------------------------------------------- main */

if ( 'baseline' === mode ) {
	const t = await baseline( a3, a4 || '1' );
	const ps = paths( t.folders );
	fs.writeFileSync( path.join( cacheDir, `${ base }-baseline-${ a4 || '1' }-tree.json` ), JSON.stringify( t, null, 1 ) );
	console.log( `baseline run ${ a4 || '1' }: ${ t.folders.length } folders proposed · top: ${ t.folders.filter( ( f ) => ! f.parent ).map( ( f ) => f.name ).join( ', ' ) }` );
	const filed = await fileInto( ps, `baseline-${ a4 || '1' }` );
	score( filed, 'baseline, filed by model' );
	score( rules( filed, MIN ), `baseline + rules (min ${ MIN })` );
} else if ( 'bottomup' === mode ) {
	const t = await bottomup( a3 || '1' );
	fs.writeFileSync( path.join( cacheDir, `${ base }-bottomup-${ a3 || '1' }-tree.json` ), JSON.stringify( t, null, 1 ) );
	console.log( `bottom-up run ${ a3 || '1' }: ${ t.folders.length } folders proposed · top: ${ t.folders.filter( ( f ) => ! f.parent ).map( ( f ) => f.name ).join( ', ' ) }` );
	score( t.assign, 'bottom-up, by phrase' );
	for ( const m of [ 3, 5, 8 ] ) {
		score( rules( t.assign, m ), `bottom-up + rules (min ${ m })` );
	}
	if ( argv.includes( '--file' ) ) {
		const ps = [ ...new Set( Object.values( rules( t.assign, MIN ) ).filter( Boolean ) ) ];
		const all = new Set();
		ps.forEach( ( p ) => { const parts = p.split( ' > ' ); for ( let i = 1; i <= parts.length; i++ ) { all.add( parts.slice( 0, i ).join( ' > ' ) ); } } );
		score( await fileInto( [ ...all ], `bottomup-${ a3 || '1' }-min${ MIN }` ), `bottom-up tree, filed by model` );
	}
} else if ( 'truth' === mode ) {
	const t = {};
	pictures.forEach( ( p ) => { t[ p.id ] = p.truth || ''; } );
	score( t, 'the truth itself' );
}
console.error( `spent $${ spent.toFixed( 2 ) }` );
