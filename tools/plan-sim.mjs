/*
 *  The planner after the model, offline (spec-tree-planner story 8, 2026-09-28).
 *
 *      node tools/plan-sim.mjs <export.json> prompt <out-dir>
 *      node tools/plan-sim.mjs <export.json> score <answer.json> [<answer.json> ...] [--each]
 *
 *  export.json is tools/box-plan-export.php's: every described picture with
 *  its label, vector, the folders it sits in and its truth.
 *
 *    prompt  the inventory as core/plan-tree.php counts it and the service's
 *            prompt for it (service lib/plan-tree.ts planTreePrompt, verbatim
 *            at a3d3eea), written as system.txt and user.txt, so an answer can
 *            be had from any model without a paid call.
 *    score   answers in the service's schema ({folders: [{name, parent,
 *            labels}]}) through what follows the model: assignmentOf and
 *            applyRules (service), the tightest kept and left-out labels
 *            placed at 0.5 (vergeml_plan_choose), then scored twice against
 *            the truth -- as the plan (each picture in its label's folder),
 *            and as the site after the fill (vergeml_plan_draft's near-copy
 *            mapping onto the existing folders, each picture moved to its
 *            label's folder unless a person placed it; a picture whose label
 *            is not in the map stays where it is, where the matcher would
 *            decide it).
 *
 *  Scored as tools/tree-lab.mjs scores. No network.
 *
 *    --gain  with score: after each answer's fill, the never-worse guard
 *            (vergeml_plan_gain) on the same pictures, now and after: the
 *            plugin's leave-one-out and average link, and the verdict
 *            (vergeml_plan_gain_better); beside them story 9's first measure
 *            (To-sort pictures in the after centre) and story 8's (a picture
 *            counted towards its own centre). The box gave the same numbers
 *            through the real PHP.
 */
import fs from 'node:fs';
import path from 'node:path';

const argv = process.argv.slice( 2 );
const [ exportFile, mode, ...rest ] = argv.filter( ( a, i ) => ! a.startsWith( '--' ) && '--map' !== argv[ i - 1 ] );
const data = JSON.parse( fs.readFileSync( exportFile, 'utf8' ) );
const all = data.pictures.slice().sort( ( a, b ) => a.id - b.id );
const labelled = all.filter( ( p ) => p.labelled );
const PLACE = 0.5;
const MIN_PICTURES = 5;
const MAX_DEPTH = 3;
// --existing: the site's folder paths (To sort left out) go with the prompt, so the plan can build around them.
const existing = argv.includes( '--existing' )
	? [ ...new Set( all.flatMap( ( p ) => p.folders ).flatMap( ( f ) => f.split( ' > ' ).map( ( _, i, a ) => a.slice( 0, i + 1 ).join( ' > ' ) ) ) ) ].filter( ( f ) => 'To sort' !== f ).sort()
	: [];

/* ------------------------------------------------ the inventory (plugin) */

const by = {};
for ( const p of all ) {
	if ( '' === p.label ) {
		continue;
	}
	( by[ p.label ] = by[ p.label ] || { label: p.label, count: 0 } ).count++;
}
const cmp = ( a, b ) => ( a < b ? -1 : a > b ? 1 : 0 );
const labels = Object.values( by ).sort( ( a, b ) => b.count - a.count || cmp( a.label, b.label ) ).map( ( l, i ) => ( { id: 'l' + i, ...l } ) );
const idOf = Object.fromEntries( labels.map( ( l ) => [ l.label, l.id ] ) );
const counts = Object.fromEntries( labels.map( ( l ) => [ l.id, l.count ] ) );

/* -------------------------------------------- the service (lib/plan-tree.ts) */

function planTreePrompt() {
	const system =
		'You design the folder tree of a media library from what is in it. You get every distinct description label in the library -- the main object; its broader class; [kind] when the picture is not a photo -- with how many pictures carry it and, where known, their audience. '
		+ ( existing.length ? 'You also get the folders the library already has. ' : '' )
		+ 'Merge labels that mean the same thing, then arrange folders as a tree. Rules:\n'
		+ ( existing.length ? '0. Build around the existing folders: where labels fit one, use its exact name and place, and keep its pictures together rather than splitting it. Add folders only for what none of them holds; leave out an existing folder nothing fits.\n' : '' )
		+ '1. A folder needs at least 3 pictures behind it (count the labels you put in it). A label too rare for a folder of its own goes into its broader folder.\n'
		+ '2. At most three levels. A parent has two to twelve children; never a parent with a single child. A folder of more than 20 pictures whose labels fall into clear kinds gets those kinds as children, down to the third level.\n'
		+ '3. Every folder name is unique in the whole tree, a plain label of at most three words, in the language of the labels, sentence case, no slash.\n'
		+ '4. Split by audience (men, women, kids) only where the counts support it on both sides.\n'
		+ '5. Kinds (logo, screenshot, diagram, document, illustration) get a folder of their own kind only when they have enough pictures; otherwise they sit with their subject.\n'
		+ '6. A label that fits no folder of a sensible tree is listed in no folder: it stays unfiled. Never force it.\n'
		+ 'Answer JSON only, no reasoning before it: {"folders": [{"name": string, "parent": string, "labels": ["l0", "l7", ...]}]} -- "parent" is the exact name of another folder or ""; "labels" lists the ids of the labels whose pictures belong in THIS folder (its most specific fit).';
	const lines = labels.map( ( l ) => `${ l.id }: ${ l.label } — ${ l.count }` );
	const pictures = labels.reduce( ( s, l ) => s + l.count, 0 );
	const head = existing.length ? `Existing folders (${ existing.length }):\n${ existing.join( '\n' ) }\n\n` : '';
	return { system, user: `${ head }Labels (${ labels.length }, ${ pictures } pictures):\n${ lines.join( '\n' ) }` };
}

function assignmentOf( answer ) {
	const known = new Set( labels.map( ( l ) => l.id ) );
	const byName = new Map();
	for ( const f of answer.folders ) {
		const name = f.name.replace( /\//g, ' ' ).trim();
		if ( '' !== name && ! byName.has( name.toLowerCase() ) ) {
			byName.set( name.toLowerCase(), { name, parent: f.parent.trim() } );
		}
	}
	const pathOf = ( key ) => {
		const seen = new Set();
		const parts = [];
		let at = key;
		while ( undefined !== at && ! seen.has( at ) ) {
			seen.add( at );
			const f = byName.get( at );
			if ( undefined === f ) {
				break;
			}
			parts.unshift( f.name );
			at = '' === f.parent ? undefined : f.parent.toLowerCase();
		}
		return parts.join( ' > ' );
	};
	const out = {};
	for ( const f of answer.folders ) {
		const p = pathOf( f.name.replace( /\//g, ' ' ).trim().toLowerCase() );
		if ( '' === p ) {
			continue;
		}
		for ( const id of f.labels ) {
			if ( known.has( id ) && ! ( id in out ) ) {
				out[ id ] = p;
			}
		}
	}
	for ( const l of labels ) {
		if ( ! ( l.id in out ) ) {
			out[ l.id ] = '';
		}
	}
	return out;
}

function applyRules( assign, min = MIN_PICTURES ) {
	const a = {};
	for ( const [ id, p ] of Object.entries( assign ) ) {
		a[ id ] = '' === p ? '' : p.split( ' > ' ).slice( 0, MAX_DEPTH ).join( ' > ' );
	}
	for ( let pass = 0; pass < 4 * Object.keys( a ).length + 10; pass++ ) {
		const direct = new Map();
		for ( const [ id, p ] of Object.entries( a ) ) {
			if ( '' !== p ) {
				direct.set( p, ( direct.get( p ) ?? 0 ) + ( counts[ id ] ?? 0 ) );
			}
		}
		const every = new Set();
		for ( const p of direct.keys() ) {
			const parts = p.split( ' > ' );
			for ( let i = 1; i <= parts.length; i++ ) {
				every.add( parts.slice( 0, i ).join( ' > ' ) );
			}
		}
		const total = ( f ) => [ ...direct ].filter( ( [ g ] ) => g === f || g.startsWith( f + ' > ' ) ).reduce( ( s, [ , n ] ) => s + n, 0 );
		const kids = ( f ) => [ ...every ].filter( ( g ) => g.startsWith( f + ' > ' ) && ! g.slice( f.length + 3 ).includes( ' > ' ) );
		const move = ( from, to ) => {
			for ( const id of Object.keys( a ) ) {
				if ( a[ id ] === from || a[ id ].startsWith( from + ' > ' ) ) {
					a[ id ] = to( a[ id ] );
				}
			}
		};
		let changed = false;
		for ( const f of [ ...every ].sort( ( x, y ) => y.split( ' > ' ).length - x.split( ' > ' ).length || ( x < y ? -1 : 1 ) ) ) {
			const up = f.includes( ' > ' ) ? f.slice( 0, f.lastIndexOf( ' > ' ) ) : '';
			if ( total( f ) < min ) {
				move( f, () => up );
				changed = true;
				break;
			}
			const k = kids( f );
			if ( 1 === k.length ) {
				const only = k[ 0 ];
				move( only, ( p ) => f + p.slice( only.length ) );
				changed = true;
				break;
			}
		}
		if ( ! changed ) {
			break;
		}
	}
	return a;
}

/* -------------------------------------------- the plugin (core/plan-tree.php) */

const unit = ( v ) => {
	const n = Math.sqrt( v.reduce( ( s, x ) => s + x * x, 0 ) );
	return n > 0 ? v.map( ( x ) => x / n ) : v;
};
const add = ( a, b ) => a.map( ( x, i ) => x + b[ i ] );
const dot = ( a, b ) => a.reduce( ( s, x, i ) => s + x * b[ i ], 0 );
const dims = ( () => {
	const c = {};
	all.forEach( ( p ) => { c[ p.vector.length ] = ( c[ p.vector.length ] || 0 ) + 1; } );
	return Number( Object.entries( c ).sort( ( a, b ) => b[ 1 ] - a[ 1 ] )[ 0 ][ 0 ] );
} )();
const sums = {};
for ( const p of all ) {
	const id = idOf[ p.label ];
	if ( ! id || p.vector.length !== dims ) {
		continue;
	}
	const v = unit( p.vector );
	sums[ id ] = sums[ id ] ? add( sums[ id ], v ) : v;
}

// vergeml_plan_choose: per tree, place the left-out labels at PLACE, then the tightest is kept.
function choose( trees ) {
	let best = null;
	trees.forEach( ( assign, index ) => {
		const folderOf = { ...assign };
		const members = {};
		for ( const [ id, p ] of Object.entries( assign ) ) {
			if ( '' !== p ) {
				( members[ p ] = members[ p ] || [] ).push( id );
			}
		}
		const centre = {};
		for ( const [ p, ids ] of Object.entries( members ) ) {
			const s = ids.filter( ( id ) => sums[ id ] ).reduce( ( acc, id ) => ( acc ? add( acc, sums[ id ] ) : sums[ id ] ), null );
			if ( s ) {
				centre[ p ] = unit( s );
			}
		}
		let placed = 0;
		for ( const [ id, p ] of Object.entries( assign ) ) {
			if ( '' !== p || ! sums[ id ] ) {
				continue;
			}
			const v = unit( sums[ id ] );
			let to = '', top = -1;
			for ( const [ f, c ] of Object.entries( centre ) ) {
				const x = dot( v, c );
				if ( x > top ) {
					top = x;
					to = f;
				}
			}
			if ( '' !== to && top >= PLACE ) {
				folderOf[ id ] = to;
				placed++;
			}
		}
		const groups = {};
		for ( const [ id, p ] of Object.entries( folderOf ) ) {
			if ( '' !== p ) {
				( groups[ p ] = groups[ p ] || [] ).push( id );
			}
		}
		let length = 0, n = 0;
		for ( const ids of Object.values( groups ) ) {
			const s = ids.filter( ( id ) => sums[ id ] ).reduce( ( acc, id ) => ( acc ? add( acc, sums[ id ] ) : sums[ id ] ), null );
			ids.filter( ( id ) => sums[ id ] ).forEach( ( id ) => { n += counts[ id ] || 0; } );
			if ( s ) {
				length += Math.sqrt( dot( s, s ) );
			}
		}
		const tightness = n > 0 ? length / n : 0;
		if ( null === best || tightness > best.tightness ) {
			best = { folderOf, index, placed, tightness };
		}
	} );
	return best;
}

// vergeml_plan_draft then the fill: planned folder -> existing folder where it already holds half its pictures (or has its name); each picture to its label's folder.
function fill( folderOf ) {
	const TO_SORT = 'to sort';
	const existing = new Set();
	for ( const p of all ) {
		for ( const f of p.folders ) {
			const parts = f.split( ' > ' );
			for ( let i = 1; i <= parts.length; i++ ) {
				existing.add( parts.slice( 0, i ).join( ' > ' ) );
			}
		}
	}
	const byName = {};
	[ ...existing ].forEach( ( f ) => { byName[ f.split( ' > ' ).pop().toLowerCase() ] = byName[ f.split( ' > ' ).pop().toLowerCase() ] || f; } );
	const labelTerms = {};
	for ( const p of all ) {
		if ( ! idOf[ p.label ] ) {
			continue;
		}
		for ( const f of p.folders ) {
			const t = ( labelTerms[ idOf[ p.label ] ] = labelTerms[ idOf[ p.label ] ] || {} );
			t[ f ] = ( t[ f ] || 0 ) + 1;
		}
	}
	const planned = {};
	for ( const [ id, p ] of Object.entries( folderOf ) ) {
		if ( '' === p ) {
			continue;
		}
		const parts = p.split( ' > ' );
		for ( let i = 1; i <= parts.length; i++ ) {
			planned[ parts.slice( 0, i ).join( ' > ' ) ] = planned[ parts.slice( 0, i ).join( ' > ' ) ] || [];
		}
		planned[ p ].push( id );
	}
	// Pictures per existing folder, for --map mutual: the existing folder must be at least half this planned folder's too.
	const size = {};
	for ( const p of all ) {
		if ( idOf[ p.label ] ) {
			p.folders.forEach( ( f ) => { size[ f ] = ( size[ f ] || 0 ) + 1; } );
		}
	}
	const how = String( argv.includes( '--map' ) ? argv[ argv.indexOf( '--map' ) + 1 ] : 'half' );
	const target = {};
	const mapped = {};
	for ( const p of Object.keys( planned ).sort() ) {
		let total = 0;
		const hits = {};
		for ( const id of planned[ p ] ) {
			total += counts[ id ] || 0;
			for ( const [ f, n ] of Object.entries( labelTerms[ id ] || {} ) ) {
				hits[ f ] = ( hits[ f ] || 0 ) + n;
			}
		}
		let onto = '', best = 0;
		for ( const [ f, n ] of Object.entries( hits ) ) {
			if ( TO_SORT !== f.toLowerCase() && n > best ) {
				best = n;
				onto = f;
			}
		}
		const ok = 'none' === how ? false : 'mutual' === how ? total > 0 && 2 * best >= total && 2 * best >= size[ onto ] : total > 0 && 2 * best >= total;
		if ( ok ) {
			target[ p ] = onto;
			mapped[ p ] = 'half';
			continue;
		}
		const name = p.split( ' > ' ).pop().toLowerCase();
		if ( byName[ name ] && 'none' !== how ) {
			target[ p ] = byName[ name ];
			mapped[ p ] = 'name';
			continue;
		}
		target[ p ] = null;
	}
	const finalPath = ( p ) => {
		if ( target[ p ] ) {
			return target[ p ];
		}
		const up = p.includes( ' > ' ) ? p.slice( 0, p.lastIndexOf( ' > ' ) ) : '';
		return ( up ? finalPath( up ) + ' > ' : '' ) + p.split( ' > ' ).pop();
	};
	const site = {};
	for ( const pic of all ) {
		const deepest = pic.folders.slice().sort( ( a, b ) => b.split( ' > ' ).length - a.split( ' > ' ).length || cmp( a, b ) )[ 0 ] || '';
		const id = idOf[ pic.label ];
		const to = id && folderOf[ id ] ? finalPath( folderOf[ id ] ) : '';
		site[ pic.id ] = pic.placed_by || ! to ? deepest : to;
	}
	return { site, mapped, target };
}

/* ------------------------------------ the never-worse guard (vergeml_plan_gain) */

const GAIN = 0.02; // VERGEML_PLAN_GAIN
const deepestOf = ( pic ) => pic.folders.slice().sort( ( a, b ) => b.split( ' > ' ).length - a.split( ' > ' ).length || cmp( a, b ) )[ 0 ] || '';

// vergeml_plan_gain_folder: each measured picture's cosine to the rest of its folder, from the sums.
function gainFolder( sk, nk, sa ) {
	const cross = dot( sk, sa );
	const rest = ( nk * dot( sa, sa ) - 2 * cross + nk ) / nk;
	return rest > 1e-12 ? ( cross - nk ) / Math.sqrt( rest ) : 0;
}

// Average link: each measured picture's mean cosine to the other members of its folder, from the sums (vergeml_plan_gain_link).
const linkFolder = ( s, n ) => ( n > 1 ? ( dot( s, s ) - n ) / ( n - 1 ) : 0 );

// The pictures already in a folder (To sort left out), now and where the fill puts them. Both sides measure only these pictures: a To-sort picture joining a folder shapes neither side (story 9's review).
function guard( site ) {
	const clean = ( f ) => ( f && 'to sort' !== f.toLowerCase() ? f : '' );
	const now = {}, nowN = {}, kept = {}, keptN = {}, every = {};
	let n = 0;
	for ( const pic of all ) {
		if ( pic.vector.length !== dims ) {
			continue;
		}
		const v = unit( pic.vector );
		const was = clean( deepestOf( pic ) );
		const then = clean( site[ pic.id ] );
		if ( then ) {
			every[ then ] = every[ then ] ? add( every[ then ], v ) : v;
		}
		if ( ! was ) {
			continue;
		}
		n++;
		now[ was ] = now[ was ] ? add( now[ was ], v ) : v;
		nowN[ was ] = ( nowN[ was ] || 0 ) + 1;
		if ( then ) {
			kept[ then ] = kept[ then ] ? add( kept[ then ], v ) : v;
			keptN[ then ] = ( keptN[ then ] || 0 ) + 1;
		}
	}
	const sum = ( o, f ) => Object.entries( o ).reduce( ( s, [ k, x ] ) => s + f( k, x ), 0 ) / Math.max( 1, n );
	return {
		before: sum( now, ( k, x ) => gainFolder( x, nowN[ k ], x ) ),
		after: sum( kept, ( k, x ) => gainFolder( x, keptN[ k ], x ) ),
		linkBefore: sum( now, ( k, x ) => linkFolder( x, nowN[ k ] ) ),
		linkAfter: sum( kept, ( k, x ) => linkFolder( x, keptN[ k ] ) ),
		after9: sum( kept, ( k, x ) => gainFolder( x, keptN[ k ], every[ k ] ) ),
		before8: sum( now, ( k, x ) => Math.sqrt( dot( x, x ) ) ),
		after8: sum( kept, ( k, x ) => dot( x, unit( every[ k ] ) ) ),
	};
}

const offered = ( g ) => g.after >= g.before + GAIN && g.linkAfter >= g.linkBefore;

function printGuard( g ) {
	const d = g.after - g.before, l = g.linkAfter - g.linkBefore;
	const sg = ( x ) => `${ x >= 0 ? '+' : '' }${ x.toFixed( 4 ) }`;
	console.log( `    guard ${ g.before.toFixed( 4 ) } -> ${ g.after.toFixed( 4 ) } (${ sg( d ) }), link ${ g.linkBefore.toFixed( 4 ) } -> ${ g.linkAfter.toFixed( 4 ) } (${ sg( l ) }) ${ offered( g ) ? 'OFFERED' : 'kept' } · story 9's first measure -> ${ g.after9.toFixed( 4 ) } · story 8's ${ g.before8.toFixed( 3 ) } -> ${ g.after8.toFixed( 3 ) }` );
}

/* ---------------------------------------------------------- the score */

function score( assign, label ) {
	const pics = labelled;
	const pairs = ( level ) => {
		const cut = ( s ) => ( s ? ( 'top' === level ? s.split( ' > ' )[ 0 ] : s ) : '' );
		const cell = {}, rowT = {}, rowP = {};
		for ( const p of pics ) {
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
		const P = tp / Math.max( 1, Object.values( rowP ).reduce( ( s, n ) => s + c2( n ), 0 ) );
		const R = tp / Math.max( 1, Object.values( rowT ).reduce( ( s, n ) => s + c2( n ), 0 ) );
		return { P, R, F: ( 2 * P * R ) / Math.max( 1e-9, P + R ) };
	};
	const leaf = pairs( 'leaf' );
	const inFolder = {};
	for ( const p of pics ) {
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
	pics.forEach( ( p ) => { if ( p.truth ) { truthSize[ p.truth ] = ( truthSize[ p.truth ] || 0 ) + 1; } } );
	const big = Object.entries( truthSize ).filter( ( [ , n ] ) => n >= 5 );
	const got = big.filter( ( [ t, n ] ) => Object.values( majority ).some( ( m ) => m.best === t && m.n >= n / 2 && m.n >= m.size / 2 ) ).map( ( [ t ] ) => t );
	const pc = ( x ) => `${ Math.round( 100 * x ) }%`;
	console.log( `${ label.padEnd( 22 ) } F1 leaf ${ pc( leaf.F ) } · purity ${ pc( pure / Math.max( 1, placed ) ) } · recovered ${ got.length }/${ big.length } · unfiled ${ pc( ( pics.length - placed ) / pics.length ) } · folders ${ Object.keys( inFolder ).length }` );
	if ( argv.includes( '--each' ) ) {
		console.log( `    missed: ${ big.filter( ( [ t ] ) => ! got.includes( t ) ).map( ( [ t, n ] ) => `${ t } (${ n })` ).join( ', ' ) }` );
	}
	return { recovered: got.length, purity: pure / Math.max( 1, placed ) };
}

const planAssign = ( folderOf ) => Object.fromEntries( all.map( ( p ) => [ p.id, idOf[ p.label ] ? folderOf[ idOf[ p.label ] ] || '' : '' ] ) );

if ( 'prompt' === mode ) {
	const dir = rest[ 0 ];
	fs.mkdirSync( dir, { recursive: true } );
	const { system, user } = planTreePrompt();
	fs.writeFileSync( path.join( dir, 'system.txt' ), system );
	fs.writeFileSync( path.join( dir, 'user.txt' ), user );
	console.log( `${ labels.length } labels, ${ all.length } pictures -> ${ dir }` );
} else if ( 'score' === mode ) {
	const trees = rest.map( ( f ) => applyRules( assignmentOf( JSON.parse( fs.readFileSync( f, 'utf8' ) ) ) ) );
	const before = {};
	all.forEach( ( p ) => { before[ p.id ] = p.folders.slice().sort( ( a, b ) => b.split( ' > ' ).length - a.split( ' > ' ).length || cmp( a, b ) )[ 0 ] || ''; } );
	score( before, 'the site now' );
	trees.forEach( ( t, i ) => {
		const one = choose( [ t ] );
		score( planAssign( one.folderOf ), `${ path.basename( rest[ i ] ) } plan` );
		if ( argv.includes( '--each' ) || argv.includes( '--gain' ) ) {
			const filled = fill( one.folderOf ).site;
			score( filled, `${ path.basename( rest[ i ] ) } filled` );
			if ( argv.includes( '--gain' ) ) {
				printGuard( guard( filled ) );
			}
		}
	} );
	const kept = choose( trees );
	console.log( `kept ${ path.basename( rest[ kept.index ] ) }, tightness ${ kept.tightness.toFixed( 3 ) }, ${ kept.placed } labels placed` );
	score( planAssign( kept.folderOf ), 'kept, plan' );
	const f = fill( kept.folderOf );
	score( f.site, 'kept, filled' );
	if ( argv.includes( '--gain' ) ) {
		printGuard( guard( f.site ) );
	}
	if ( argv.includes( '--each' ) ) {
		for ( const [ p, t ] of Object.entries( f.target ) ) {
			console.log( `    ${ p } -> ${ t ? `${ t } (${ f.mapped[ p ] })` : 'new' }` );
		}
	}
}
