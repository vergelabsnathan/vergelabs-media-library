/**
 *  Does the box still file every picture where the baseline says it does?
 *
 *      node tools/filing-baseline-check.mjs
 *      node tools/filing-baseline-check.mjs --file run.txt   (a run already taken)
 *      node tools/filing-baseline-check.mjs --box 46.225.66.194
 *
 *  This is the gate `plans/traces.md` ends on: run the same filing pass over
 *  the same pictures before and after the work, and the placements must be
 *  identical. Until now that comparison was made by eye against
 *  tests/tree/filing-baseline.txt, and by eye it was read as a byte diff --
 *  which is why 108 rows of fourth-decimal noise was recorded twice as an
 *  unexplained drift and nobody could act on it.
 *
 *  Measured on 2026-09-09, the noise has a source and a size. A folder is
 *  scored partly on how alike two class phrases are, and a class phrase's
 *  vector is fetched from the service and cached. The service is OpenAI's
 *  text-embedding-3-small, and it does not answer a repeated question with
 *  the same 512 floats every time: asked six times, `skyline` gave two
 *  different vectors and so did `headshot`, differing by ~1.8e-4 in a single
 *  dimension. So a re-fetched phrase moves a score in the fourth decimal, and
 *  a cache that expires guarantees re-fetches.
 *
 *  What that means for this gate: a score is reproducible to about a
 *  thousandth and no further, and a *placement* is reproducible exactly --
 *  the floor is 0.55, the margin 0.08 and the depth tie-break 0.03, which are
 *  two to three orders of magnitude above the noise. So the assertion is the
 *  placement, and the score is a canary with a stated band rather than a
 *  string compare that fails on arithmetic nobody controls.
 *
 *  A picture whose score sits within the noise of the floor, the margin or
 *  the tie-break can still change its mind, and one did: 2817 read `margin`
 *  on 8 September and reads `ok` again today, its score moving by exactly
 *  0.030000 -- the depth tie-break flipping. Those are reported by name
 *  rather than tolerated, because a placement that changes is the thing this
 *  gate exists to catch.
 *
 *  It writes nothing, with one exception. `--retake "<reason>"` replaces the
 *  baseline with today's run and writes the reason into it as a comment --
 *  the engine changed on purpose (every-picture-a-home C.1, 2026-09-16) and
 *  the old expectation is no longer the truth. A gate that rewrites its own
 *  expectation silently is not a gate; one that does so with a stated reason
 *  in the record is how the record stays honest.
 *
 *  Two bands since 2026-09-16. The score band above, per picture. And the
 *  outcome band: the tally of a first fill over the library (fits, sure,
 *  likely, siblings, nothing by floor / margin / either / gated), which fails
 *  when any outcome moves by more than OUTCOME_BAND of the pictures. A prompt
 *  change in the service, a planner change, a describer change: each must
 *  leave this green or re-take it with the reason.
 */

import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const BASELINE = path.join( HERE, '..', 'tests', 'tree', 'filing-baseline.txt' );

const BOXES = {
	'46.225.66.194': { key: '~/.ssh/hetzner_vgml', wp: '/var/www/wp' },
};

/*
 *  Two bands, and they are different questions.
 *
 *  SCORE_BAND is how far a score may move on the same inputs before it is
 *  news. The largest single-dimension difference the service gives back for
 *  one phrase is ~1.8e-4; the largest whole-score drift measured across 641
 *  pictures on 2026-09-09 was 4.11e-4. A thousandth is over twice that and
 *  still thirty times below the tie-break it must not reach.
 */
const SCORE_BAND = 0.001;

/** How far an outcome's count may move, as a share of the pictures, before the fill is doing something else. */
const OUTCOME_BAND = 0.03;

const flag = ( name, fallback = null ) => {
	const i = process.argv.indexOf( name );
	return i > -1 && process.argv[ i + 1 ] ? process.argv[ i + 1 ] : fallback;
};

const BOX_HOST = flag( '--box', '46.225.66.194' );
const RETAKE = flag( '--retake' );

function ssh( box, script ) {
	try {
		return execFileSync( 'ssh', [
			'-i', box.key.replace( /^~/, os.homedir() ),
			'-o', 'StrictHostKeyChecking=no',
			'-o', 'ConnectTimeout=15',
			`root@${ BOX_HOST }`, 'bash -s',
		], { input: script, stdio: 'pipe', maxBuffer: 32 * 1024 * 1024 } ).toString();
	} catch ( err ) {
		const said = ( err.stderr || err.stdout || '' ).toString().trim();
		throw new Error( said || err.message );
	}
}

function scp( box, from, to ) {
	execFileSync( 'scp', [
		'-i', box.key.replace( /^~/, os.homedir() ),
		'-o', 'StrictHostKeyChecking=no',
		from, `root@${ BOX_HOST }:${ to }`,
	], { stdio: 'pipe' } );
}

/** The pass, run read-only on the box. Moves nothing and spends nothing. */
function runOnBox() {

	const box = BOXES[ BOX_HOST ];

	if ( ! box ) {
		throw new Error( `no key on file for ${ BOX_HOST }` );
	}

	scp( box, path.join( HERE, 'box-filing-baseline.php' ), '/tmp/vgml-filing-baseline.php' );

	return ssh( box, `
		set -e
		cd ${ box.wp }
		wp eval-file /tmp/vgml-filing-baseline.php --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:" || true
		rm -f /tmp/vgml-filing-baseline.php
	` );
}

/** attachment id -> { term, why, score, runner, runnerScore }. Comments dropped. */
function parse( text, what ) {

	const rows = new Map();

	for ( const line of text.split( /\r?\n/ ) ) {

		if ( line.startsWith( '#' ) || line.trim() === '' ) {
			continue;
		}

		const f = line.split( '\t' );

		if ( f.length !== 6 ) {
			continue;
		}

		rows.set( Number( f[ 0 ] ), {
			term: Number( f[ 1 ] ),
			why: f[ 2 ],
			score: Number( f[ 3 ] ),
			runner: Number( f[ 4 ] ),
			runnerScore: Number( f[ 5 ] ),
		} );
	}

	if ( rows.size === 0 ) {
		throw new Error( `${ what } has no rows in it` );
	}

	return rows;
}

/** The "# tally looked N fits N …" line as { looked, fits, … }, or null when the file predates it. */
function tally( text ) {
	const line = text.split( /\r?\n/ ).find( ( l ) => l.startsWith( '# tally ' ) );
	if ( ! line ) {
		return null;
	}
	const out = {};
	const parts = line.slice( '# tally '.length ).trim().split( /\s+/ );
	for ( let i = 0; i + 1 < parts.length; i += 2 ) {
		out[ parts[ i ] ] = Number( parts[ i + 1 ] );
	}
	return out.looked ? out : null;
}

const wasText = fs.readFileSync( BASELINE, 'utf8' );
const nowText = flag( '--file' ) ? fs.readFileSync( flag( '--file' ), 'utf8' ) : runOnBox();

if ( RETAKE ) {
	const stamp = new Date().toISOString().slice( 0, 10 );
	const lines = nowText.split( /\r?\n/ );
	lines.splice( 1, 0, `# re-taken ${ stamp }: ${ RETAKE }` );
	fs.writeFileSync( BASELINE, lines.join( '\n' ) );
	const t = tally( nowText );
	console.log( `\n  filing baseline re-taken (${ stamp }): ${ RETAKE }` );
	console.log( `  ${ t ? Object.entries( t ).map( ( [ k, v ] ) => `${ k } ${ v }` ).join( ' · ' ) : 'no tally line in the run' }\n` );
	process.exit( 0 );
}

const was = parse( wasText, 'the baseline' );
const now = parse( nowText, 'the run' );

const missing = [ ...was.keys() ].filter( ( id ) => ! now.has( id ) );
const extra = [ ...now.keys() ].filter( ( id ) => ! was.has( id ) );

const placements = [];
const overBand = [];
let worstScore = 0;
let worstRunner = 0;
let moved = 0;

for ( const [ id, a ] of was ) {

	const b = now.get( id );

	if ( ! b ) {
		continue;
	}

	if ( a.term !== b.term || a.why !== b.why ) {
		placements.push( { id, a, b } );
	}

	const ds = Math.abs( a.score - b.score );
	const dr = Math.abs( a.runnerScore - b.runnerScore );

	if ( ds > 0 || dr > 0 || a.runner !== b.runner ) {
		moved++;
	}

	worstScore = Math.max( worstScore, ds );
	worstRunner = Math.max( worstRunner, dr );

	if ( ds > SCORE_BAND || dr > SCORE_BAND ) {
		overBand.push( { id, ds, dr } );
	}
}

const n = was.size;

console.log( `\n  filing baseline · ${ n } pictures · ${ flag( '--file' ) ? flag( '--file' ) : BOX_HOST }\n` );
console.log( `  rows whose numbers moved at all            ${ moved }` );
console.log( `  largest move in a winning score            ${ worstScore.toExponential( 3 ) }` );
console.log( `  largest move in a runner-up's score        ${ worstRunner.toExponential( 3 ) }` );
console.log( `  the band a score may move in               ${ SCORE_BAND }\n` );

let bad = 0;

if ( missing.length || extra.length ) {
	bad++;
	console.log( `  FAIL  the library is not the one the baseline was taken over` );
	console.log( `        ${ missing.length } picture${ missing.length === 1 ? '' : 's' } gone, ${ extra.length } new` );
	if ( missing.length ) {
		console.log( `        gone: ${ missing.slice( 0, 12 ).join( ', ' ) }${ missing.length > 12 ? ' …' : '' }` );
	}
	if ( extra.length ) {
		console.log( `        new:  ${ extra.slice( 0, 12 ).join( ', ' ) }${ extra.length > 12 ? ' …' : '' }` );
	}
} else {
	console.log( `  ok    every picture in the baseline is still in the library` );
}

if ( placements.length ) {
	bad++;
	console.log( `\n  FAIL  ${ placements.length } picture${ placements.length === 1 ? '' : 's' } would be filed somewhere else now` );
	for ( const { id, a, b } of placements.slice( 0, 20 ) ) {
		console.log(
			`        ${ id }  ${ a.why } in ${ a.term } (${ a.score.toFixed( 6 ) })` +
			`  ->  ${ b.why } in ${ b.term } (${ b.score.toFixed( 6 ) })`
		);
	}
	if ( placements.length > 20 ) {
		console.log( `        … and ${ placements.length - 20 } more` );
	}
} else {
	console.log( `  ok    every picture would be filed exactly where it was` );
}

if ( overBand.length ) {
	bad++;
	console.log( `\n  FAIL  ${ overBand.length } score${ overBand.length === 1 ? '' : 's' } moved further than the service's own noise explains` );
	for ( const { id, ds, dr } of overBand.slice( 0, 20 ) ) {
		console.log( `        ${ id }  score ${ ds.toExponential( 3 ) }, runner-up ${ dr.toExponential( 3 ) }` );
	}
} else {
	console.log( `  ok    every score is within ${ SCORE_BAND } of the baseline` );
}

// The outcome band: what the fill does, in counts.
const tWas = tally( wasText );
const tNow = tally( nowText );
const KEYS = [ 'fits', 'sure', 'likely', 'siblings', 'nothing', 'floor', 'margin', 'either', 'gated', 'kept' ];
if ( ! tWas || ! tNow ) {
	bad++;
	console.log( `\n  FAIL  ${ ! tWas ? 'the baseline' : 'the run' } has no outcome tally — re-take it with a reason (--retake "…")` );
} else {
	const moved = KEYS.filter( ( k ) => Math.abs( ( tNow[ k ] || 0 ) - ( tWas[ k ] || 0 ) ) > OUTCOME_BAND * tWas.looked );
	const line = ( t ) => KEYS.map( ( k ) => `${ k } ${ t[ k ] || 0 }` ).join( ' · ' );
	if ( moved.length ) {
		bad++;
		console.log( `\n  FAIL  the fill does something else now: ${ moved.join( ', ' ) } moved by more than ${ OUTCOME_BAND * 100 } % of ${ tWas.looked }` );
	} else {
		console.log( `\n  ok    every outcome within ${ OUTCOME_BAND * 100 } % of the baseline's count` );
	}
	console.log( `        was  ${ line( tWas ) }` );
	console.log( `        now  ${ line( tNow ) }` );
}

console.log( '' );

if ( bad ) {
	console.log( `  ${ bad } of 4 checks failed\n` );
	process.exit( 1 );
}

console.log( `  4 of 4 checks passed — the placements are identical and the fill does the same\n` );
