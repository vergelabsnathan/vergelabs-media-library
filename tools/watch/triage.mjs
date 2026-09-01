/*
 *  Triage: turns the watch's report (and the stage's results, when there are
 *  any) into what happens next.
 *
 *    green   -> readme's "Tested up to" (core) or the verified-against note
 *               (a plugin) is edited and staged. Nothing else. No human.
 *    yellow  -> a plan, written by a model through OpenRouter from the changelog
 *               and the files the contract names, opened as a GitHub issue.
 *    red     -> the same issue, plus the fix step (fix.sh) when the run has the
 *               tokens for it. The issue says when it does not.
 *
 *  Every yellow and red also lands in tools/watch/known-issues.json, which the
 *  plugin's Get help screen and the support agent read later.
 *
 *      node tools/watch/triage.mjs report.json [stage.json] [--dry-run]
 *
 *  Needs: gh (authenticated) for issues; OPENROUTER_API_KEY for plans. Without
 *  the key the issue is opened with the facts and no plan, and says so.
 */
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname( fileURLToPath( import.meta.url ) );
const ROOT = join( HERE, '..', '..' );
const KNOWN = join( HERE, 'known-issues.json' );
const CONTRACT = JSON.parse( readFileSync( join( HERE, 'contract.json' ), 'utf8' ) );

const argv = process.argv.slice( 2 );
const DRY = argv.includes( '--dry-run' );
const [ reportPath, stagePath ] = argv.filter( ( a ) => ! a.startsWith( '--' ) );
if ( ! reportPath ) { console.error( 'usage: triage.mjs report.json [stage.json] [--dry-run]' ); process.exit( 1 ); }

const report = JSON.parse( readFileSync( reportPath, 'utf8' ) );
const stage = stagePath && existsSync( stagePath ) ? JSON.parse( readFileSync( stagePath, 'utf8' ) ) : [];
const stageFor = ( key ) => stage.find( ( s ) => s.key === key );

const MODEL = process.env.WATCH_MODEL ?? 'anthropic/claude-sonnet-5';
const OPENROUTER = process.env.OPENROUTER_API_KEY ?? '';

/* --- helpers ----------------------------------------------------------------- */

const sh = ( cmd, args, opts = {} ) => execFileSync( cmd, args, { encoding: 'utf8', cwd: ROOT, ...opts } ).trim();

function readUsedIn( dep ) {
	// The functions around each usedIn line, so the plan writer reads our side
	// of the contract and not only theirs.
	const out = [];
	const seen = new Set();
	for ( const s of dep.symbols ) {
		const [ file, line ] = String( s.usedIn ).split( ':' );
		if ( ! file || seen.has( file + line ) ) continue;
		seen.add( file + line );
		const p = join( ROOT, file );
		if ( ! existsSync( p ) ) continue;
		const lines = readFileSync( p, 'utf8' ).split( '\n' );
		const at = Math.max( 0, ( parseInt( line, 10 ) || 1 ) - 15 );
		out.push( `// ${ file }:${ line }\n` + lines.slice( at, at + 40 ).join( '\n' ) );
	}
	return out.join( '\n\n' ).slice( 0, 12000 );
}

async function writePlan( d, dep, stageResult ) {
	if ( ! OPENROUTER ) return null;
	const facts = [
		`Dependency: ${ dep.name } (${ d.key }), now ${ d.version }${ d.onBox ? `, box has ${ d.onBox }` : '' }.`,
		`Verdict: ${ d.verdict } -- ${ d.reason }.`,
		d.contract?.missing?.length ? `Missing symbols:\n${ d.contract.missing.map( ( m ) => `  - ${ m.kind } ${ m.value } (relied on in ${ m.usedIn })` ).join( '\n' ) }` : '',
		stageResult ? `Stage run on the box (${ stageResult.passed } passed, ${ stageResult.failed } failed):\n${ ( stageResult.output ?? '' ).slice( -6000 ) }` : 'No stage run.',
		d.changelog ? `Changelog excerpt:\n${ d.changelog.slice( 0, 3500 ) }` : '',
	].filter( Boolean ).join( '\n\n' );

	const system = 'You write implementation plans for a WordPress plugin (PHP 7.4 floor, no build step, ES5 JS). '
		+ 'Write in the repo\'s plan format: ## Problem (concrete, from the code), ## Decisions taken, ## Out of scope, '
		+ '## Context (files), ## Tasks (ordered, each naming its file), ## Validation strategy, ## Risks. '
		+ 'Name only files and symbols that appear in the material. Never invent a hook. If the material does not '
		+ 'show a break, say so plainly and propose the smallest verification instead of a change. Under 120 lines.';
	const user = `${ facts }\n\nOur side of the contract:\n\n${ readUsedIn( dep ) }`;

	const r = await fetch( 'https://openrouter.ai/api/v1/chat/completions', {
		method: 'POST',
		headers: { Authorization: `Bearer ${ OPENROUTER }`, 'Content-Type': 'application/json', 'HTTP-Referer': 'https://vergelabsmedia.com', 'X-Title': 'vergelabs-watch' },
		body: JSON.stringify( { model: MODEL, max_tokens: 3000, messages: [ { role: 'system', content: system }, { role: 'user', content: user } ] } ),
	} );
	if ( ! r.ok ) throw new Error( `OpenRouter HTTP ${ r.status }: ${ ( await r.text() ).slice( 0, 200 ) }` );
	const j = await r.json();
	return j.choices?.[ 0 ]?.message?.content ?? null;
}

function issueBody( d, dep, plan, stageResult ) {
	const lines = [
		`**${ dep.name }** moved to \`${ d.version }\`${ d.onBox ? ` (the box has \`${ d.onBox }\`)` : '' }.`,
		'',
		`Verdict: **${ d.verdict }** — ${ d.reason }`,
		'',
	];
	if ( d.contract ) {
		lines.push( '### Contract' );
		if ( d.contract.missing?.length ) for ( const m of d.contract.missing ) lines.push( `- GONE \`${ m.kind }\` \`${ m.value }\` — relied on in \`${ m.usedIn }\`` );
		else if ( d.contract.skipped ) lines.push( `- ${ d.contract.skipped }` );
		else lines.push( `- intact: ${ d.contract.found } symbols found in ${ d.contract.scanned } files` );
		if ( d.contract.unverifiable?.length ) lines.push( `- not verifiable by grep: ${ d.contract.unverifiable.join( ', ' ) }` );
		lines.push( '' );
	}
	if ( stageResult ) {
		lines.push( `### Stage (upd.46.225.66.194.nip.io)`, `${ stageResult.passed } passed, ${ stageResult.failed } failed`, '', '```', ( stageResult.output ?? '' ).slice( -4000 ), '```', '' );
	}
	if ( d.changelog ) lines.push( '### Changelog', '', '```', d.changelog.slice( 0, 2500 ), '```', '' );
	lines.push( '### Plan', '', plan ?? '_No OPENROUTER_API_KEY in this run, so no plan was written. The facts above are the input; run `node tools/watch/triage.mjs` locally with the key to generate one._', '' );
	if ( d.verdict === 'red' ) lines.push( '---', process.env.CLAUDE_CODE_OAUTH_TOKEN ? 'A fix branch is being opened by `tools/watch/fix.sh`.' : '_No `CLAUDE_CODE_OAUTH_TOKEN` in this run, so no fix branch was opened. Run `bash tools/watch/fix.sh <this issue number>` locally._' );
	lines.push( '', `<sub>Opened by the watch. Contract: \`tools/watch/contract.json\`. Run: ${ process.env.GITHUB_SERVER_URL ?? '' }${ process.env.GITHUB_REPOSITORY ? '/' + process.env.GITHUB_REPOSITORY + '/actions/runs/' + process.env.GITHUB_RUN_ID : '(local)' }</sub>` );
	return lines.join( '\n' );
}

function openIssue( title, body, labels ) {
	if ( DRY ) { console.log( `\n[dry-run] would open issue: ${ title }\n\n${ body }\n` ); return 'dry-run'; }
	for ( const l of labels ) { try { sh( 'gh', [ 'label', 'create', l, '--force', '--color', l.endsWith( 'red' ) ? 'B60205' : l.endsWith( 'yellow' ) ? 'FBCA04' : '0E8A16' ] ); } catch { /* exists */ } }
	return sh( 'gh', [ 'issue', 'create', '--title', title, '--body', body, '--label', labels.join( ',' ) ] );
}

function recordKnownIssue( d, dep, issueUrl ) {
	const known = existsSync( KNOWN ) ? JSON.parse( readFileSync( KNOWN, 'utf8' ) ) : { _: 'Written by tools/watch/triage.mjs. Read by the plugin\'s Get help screen and the support agent.', issues: [] };
	known.issues = known.issues.filter( ( k ) => ! ( k.dependency === d.key && k.version === d.version ) );
	known.issues.unshift( {
		dependency: d.key,
		name: dep.name,
		slug: dep.source.installed ?? dep.source.slug ?? d.key,
		version: d.version,
		severity: d.verdict,
		summary: d.reason,
		affects: ( d.contract?.missing ?? [] ).map( ( m ) => m.usedIn ),
		workaround: null,
		status: 'open',
		issue: issueUrl,
		found: new Date().toISOString().slice( 0, 10 ),
	} );
	if ( ! DRY ) writeFileSync( KNOWN, JSON.stringify( known, null, 2 ) + '\n' );
}

function greenEdit( d, dep ) {
	const readme = join( ROOT, 'readme.txt' );
	let text = readFileSync( readme, 'utf8' );
	if ( d.key === 'core' ) {
		// Only ever forward, and only when the three version lines agree (gate 3).
		const main = readFileSync( join( ROOT, 'vergelabs-media-library.php' ), 'utf8' );
		const v1 = main.match( /^Version:\s*(\S+)/m )?.[ 1 ], v2 = main.match( /VERGEML_VERSION',\s*'([^']+)'/ )?.[ 1 ], v3 = text.match( /^Stable tag:\s*(\S+)/m )?.[ 1 ];
		if ( ! ( v1 && v1 === v2 && v2 === v3 ) ) { console.log( `  green edit refused: version lines disagree (${ v1 } / ${ v2 } / ${ v3 })` ); return false; }
		const cur = text.match( /^Tested up to:\s*(\S+)/m )?.[ 1 ];
		const to = d.version.split( '.' ).slice( 0, 2 ).join( '.' );
		if ( ! cur || cur === to || cur.localeCompare( to, undefined, { numeric: true } ) > 0 ) return false;
		text = text.replace( /^Tested up to:.*$/m, `Tested up to: ${ to }` );
	} else {
		const marker = '<!-- watch:verified -->';
		const line = `* ${ dep.name } ${ d.version } — contract intact${ stageFor( d.key ) ? ', stage suites passed' : '' } (${ new Date().toISOString().slice( 0, 10 ) })`;
		if ( ! text.includes( marker ) ) return false; // the readme has no verified-against list yet; nothing to edit
		text = text.replace( marker, `${ marker }\n${ line }` );
	}
	if ( DRY ) { console.log( `  [dry-run] would edit readme.txt for ${ d.key } ${ d.version }` ); return true; }
	writeFileSync( readme, text );
	sh( 'git', [ 'add', 'readme.txt' ] );
	return true;
}

/* --- run ----------------------------------------------------------------------- */

const acted = [];
for ( const d of report.dependencies ) {
	if ( ! [ 'green', 'yellow', 'red' ].includes( d.verdict ) ) continue;
	const dep = CONTRACT.dependencies[ d.key ];
	const stageResult = stageFor( d.key );
	// A failed stage run turns any verdict red: the proof beats the grep.
	if ( stageResult && stageResult.failed > 0 && d.verdict !== 'red' ) { d.verdict = 'red'; d.reason = `stage: ${ stageResult.failed } step(s) failed`; }

	if ( d.verdict === 'green' ) {
		const edited = greenEdit( d, dep );
		acted.push( { key: d.key, verdict: 'green', edited } );
		console.log( `  green  ${ d.key } ${ d.version } ${ edited ? '(readme edited)' : '' }` );
		continue;
	}

	let plan = null;
	try { plan = await writePlan( d, dep, stageResult ); } catch ( e ) { console.log( `  plan failed for ${ d.key }: ${ e.message }` ); }
	const title = `${ d.verdict === 'red' ? 'Broke' : 'Check' }: ${ dep.name } ${ d.version } — ${ d.reason.slice( 0, 80 ) }`;
	const url = openIssue( title, issueBody( d, dep, plan, stageResult ), [ 'watch', `watch:${ d.verdict }` ] );
	recordKnownIssue( d, dep, url );
	acted.push( { key: d.key, verdict: d.verdict, issue: url } );
	console.log( `  ${ d.verdict.padEnd( 6 ) } ${ d.key } ${ d.version } -> ${ url }` );
}

if ( ! acted.length ) console.log( '  nothing moved that needs a verdict' );
writeFileSync( join( HERE, '.cache', 'triage.json' ), JSON.stringify( acted, null, 2 ) );
