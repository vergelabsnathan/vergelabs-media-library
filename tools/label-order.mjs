/*
 *  How far a label order already groups the answer (spec-tree-planner story 5, 2026-09-29).
 *
 *      node tools/label-order.mjs <export.json> [<orders-out.json>]
 *
 *  export.json is tools/box-plan-export.php's. The labels are counted as
 *  core/plan-tree.php counts them, and four orders are laid out: the
 *  plugin's (count, then text), the tree lab's (count, then first picture),
 *  by broader class, and a vector chain (from the most carried label, each
 *  step to the nearest unvisited label by its pictures' mean vector). Each
 *  gets the share of neighbouring labels whose pictures sit mostly in the
 *  same truth folder. On the shop the picture ids run in truth order, so the
 *  lab's order handed the model the grouping: 67 % against the plugin's 17 %.
 *  The optional output holds each order as label ids, for a paid run. No network.
 */
import fs from 'node:fs';

const [ exportFile, out ] = process.argv.slice( 2 );
const data = JSON.parse( fs.readFileSync( exportFile, 'utf8' ) );
const cmp = ( a, b ) => ( a < b ? -1 : a > b ? 1 : 0 );

const by = {};
for ( const p of data.pictures ) {
	if ( '' !== p.label ) {
		( by[ p.label ] = by[ p.label ] || { label: p.label, count: 0, pictures: [] } ).count++;
		by[ p.label ].pictures.push( p );
	}
}
const labels = Object.values( by ).sort( ( a, b ) => b.count - a.count || cmp( a.label, b.label ) ).map( ( l, i ) => ( { id: 'l' + i, ...l } ) );

const truthOf = {};
for ( const l of labels ) {
	const t = {};
	l.pictures.forEach( ( p ) => { t[ p.truth || '-' ] = ( t[ p.truth || '-' ] || 0 ) + 1; } );
	truthOf[ l.id ] = Object.entries( t ).sort( ( a, b ) => b[ 1 ] - a[ 1 ] )[ 0 ][ 0 ];
}
const unit = ( v ) => { const n = Math.hypot( ...v ) || 1; return v.map( ( x ) => x / n ); };
const cos = ( a, b ) => a.reduce( ( s, x, i ) => s + x * b[ i ], 0 );
const vec = {};
for ( const l of labels ) {
	const vs = l.pictures.filter( ( p ) => p.vector && p.vector.length ).map( ( p ) => unit( p.vector ) );
	if ( vs.length ) {
		vec[ l.id ] = unit( vs.reduce( ( s, v ) => s.map( ( x, i ) => x + v[ i ] ) ) );
	}
}

const orders = { plugin: labels.slice() };
const first = {};
data.pictures.forEach( ( p ) => { if ( p.label && ( undefined === first[ p.label ] || p.id < first[ p.label ] ) ) { first[ p.label ] = p.id; } } );
orders.lab = labels.slice().sort( ( a, b ) => b.count - a.count || first[ a.label ] - first[ b.label ] );
const cls = ( l ) => ( l.label.split( ';' )[ 1 ] ?? '' ).replace( /\[.*\]/, '' ).trim();
const size = {};
labels.forEach( ( l ) => { size[ cls( l ) ] = ( size[ cls( l ) ] || 0 ) + l.count; } );
orders.class = labels.slice().sort( ( a, b ) => size[ cls( b ) ] - size[ cls( a ) ] || cmp( cls( a ), cls( b ) ) || b.count - a.count || cmp( a.label, b.label ) );
const left = new Set( labels.filter( ( l ) => vec[ l.id ] ).map( ( l ) => l.id ) );
const chain = [];
for ( let at = labels[ 0 ].id; at; ) {
	left.delete( at );
	chain.push( at );
	let next = null, top = -2;
	for ( const id of left ) {
		const c = cos( vec[ at ], vec[ id ] );
		if ( c > top ) {
			top = c;
			next = id;
		}
	}
	at = next;
}
const byId = Object.fromEntries( labels.map( ( l ) => [ l.id, l ] ) );
orders.chain = chain.map( ( id ) => byId[ id ] ).concat( labels.filter( ( l ) => ! vec[ l.id ] ) );

for ( const [ name, o ] of Object.entries( orders ) ) {
	let same = 0;
	for ( let i = 1; i < o.length; i++ ) {
		if ( '-' !== truthOf[ o[ i ].id ] && truthOf[ o[ i ].id ] === truthOf[ o[ i - 1 ].id ] ) {
			same++;
		}
	}
	console.log( `${ name.padEnd( 7 ) } neighbours in the same truth folder ${ Math.round( ( 100 * same ) / Math.max( 1, o.length - 1 ) ) }%` );
}
if ( out ) {
	fs.writeFileSync( out, JSON.stringify( Object.fromEntries( Object.entries( orders ).map( ( [ k, o ] ) => [ k, o.map( ( l ) => l.id ) ] ) ) ) );
}
