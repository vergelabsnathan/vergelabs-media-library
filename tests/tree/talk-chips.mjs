/*
 *  The conversation's own chips (js/vergeml-talk.js): the choices the model
 *  offers under its last reply send a turn when pressed.
 *
 *      node tests/tree/talk-chips.mjs
 *
 *  Runs against tests/tree/talk-chips.html from disk -- no WordPress, no
 *  site, no box. Written on the real shop's walk (S19, 2026-09-18): the
 *  planner asked "by kind, or by garment type?" with two chips, and pressing
 *  one threw "turn is not a function" -- messageEl's parameter was named
 *  `turn`, shadowing the turn() function, since the component was cut out
 *  on 2026-09-06 (871e813). No suite had ever loaded the file.
 *
 *  Mutation: the parameter renamed back to `turn` -> A2, A3 red.
 */

import { chromium } from 'playwright';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = path.dirname( fileURLToPath( import.meta.url ) );
const HARNESS = pathToFileURL( path.join( HERE, 'talk-chips.html' ) ).href;

const results = [];
const check = ( name, ok, detail = '' ) => {
	results.push( { name, ok } );
	console.log( `  ${ ok ? 'ok  ' : 'FAIL' } ${ name }${ detail ? '  -- ' + detail : '' }` );
};

const browser = await chromium.launch();
const page = await browser.newPage();
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( HARNESS );
await page.waitForFunction( () => window.harness && window.harness.talk );

console.log( '\nA  the model\'s chips under its last reply\n' );

const chips = await page.$$eval( '.vgml-msg.is-assistant .vgml-chips .vgml-chip', ( els ) => els.map( ( e ) => e.textContent ) );
check( 'A1 the two choices are drawn as chips under the reply', 2 === chips.length && 'Split by garment type' === chips[ 1 ], JSON.stringify( chips ) );

await page.click( '.vgml-chip:nth-child(2)' );
await page.waitForTimeout( 300 );

check( 'A2 pressing a chip throws nothing', 0 === errors.length, errors.join( ' | ' ) );

const h = await page.evaluate( () => ( {
	requests: window.harness.requests,
	persisted: window.harness.persisted,
	minted: window.harness.minted,
	turns: window.harness.session.turns.map( ( t ) => t.role + ':' + ( t.kind || '' ) + ':' + t.text ),
} ) );
check( 'A3 the chip is sent as a choice turn', 1 === h.requests.length && 'Split by garment type' === h.requests[ 0 ].choice, JSON.stringify( h.requests ) );
check( 'A4 the choice is persisted as what the person said', h.persisted.some( ( b ) => b.said && 'choice' === b.said.kind && 'Split by garment type' === b.said.text ), JSON.stringify( h.persisted ) );
check( 'A5 the session log carries the choice as the person\'s turn', h.turns.includes( 'user:choice:Split by garment type' ), h.turns.join( ' / ' ) );
check( 'A6 a token was minted for the stream', 1 === h.minted, `${ h.minted }` );

await browser.close();

const failed = results.filter( ( r ) => ! r.ok ).length;
console.log( `\n${ results.length - failed }/${ results.length } passed${ failed ? `, ${ failed } FAILED` : '' }\n` );
process.exit( failed ? 1 : 0 );
