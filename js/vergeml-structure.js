/*
 *  A structure somebody already has, read from a paste.
 *
 *  One folder per line, the full path with ">" between levels:
 *
 *      Hardware
 *      Hardware > Phones
 *      Hardware > Components > Chips
 *
 *  The reading, and there is only one (docs/superpowers/specs/2026-09-10-folders-ways-in.md,
 *  the 2026-09-12 decision): a line is split on ">"; each segment is trimmed;
 *  empty segments are dropped; the segments are the path from the top.
 *  Leading whitespace is ignored, never read as depth. Every folder on a path
 *  is made if the tree does not have it, so one line can make three. The same
 *  path twice is one folder. A name is matched to an existing folder
 *  case-insensitively at that exact place in the tree, and reused.
 *
 *  Refused, and said out loud: a line with no name, a line deeper than five
 *  levels, more than five hundred folders in one paste. A blank line is
 *  skipped. A line with no ">" is a top-level folder, not an error.
 *
 *  This file decides nothing about pictures. It turns text into the same
 *  draft shape the conversation builds (js/vergeml-tree-view.js); the dry run
 *  and the Move take it from there, exactly as they take a conversation's.
 *
 *  No DOM, no WordPress: tests/tree/structure.mjs loads it from disk.
 */
( function () {
	'use strict';

	var MAX_LEVELS = 5;
	var MAX_FOLDERS = 500;

	var DEFAULT_L10N = {
		/* translators: %s: a line number */
		noName: 'Line %s: no name',
		/* translators: 1: a line number, 2: the most levels allowed */
		tooDeep: 'Line %1$s: deeper than %2$s levels',
		/* translators: 1: folders in the paste, 2: the most one paste can hold */
		tooMany: '%1$s folders; %2$s is the most one paste can hold'
	};

	function sprintf( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s ).replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			var at = pos ? parseInt( pos, 10 ) - 1 : i++;
			return args[ at ] === undefined ? '' : String( args[ at ] );
		} );
	}

	function lower( s ) {
		return String( s || '' ).toLowerCase().trim();
	}

	/*
	 *  A key for a folder the paste makes: the server keeps [A-Za-z0-9:_-.]
	 *  of a key and drops the rest, so the path itself cannot be the key
	 *  ("Café" and "Caf" would collide). A hash of the lowercased path is
	 *  stable across re-reads of the same text, which is what keeps a row in
	 *  place while the person types the line beneath it.
	 */
	function keyFor( path ) {
		var s = path.join( ' ' );
		var h = 5381;
		for ( var i = 0; i < s.length; i++ ) {
			h = ( ( h * 33 ) ^ s.charCodeAt( i ) ) >>> 0;
		}
		return 'p' + h.toString( 36 ) + path.length;
	}

	/** Live folders by lowercased path, "hardware/phones" -> node. */
	function liveByPath( nodes ) {
		var byId = {};
		( nodes || [] ).forEach( function ( n ) { byId[ n.id ] = n; } );
		var out = {};
		( nodes || [] ).forEach( function ( n ) {
			var names = [];
			var walk = n;
			var guard = 0;
			while ( walk && guard++ < 64 ) {
				names.unshift( lower( walk.name ) );
				walk = byId[ walk.parent ];
			}
			out[ names.join( '/' ) ] = n;
		} );
		return out;
	}

	/**
	 *  Read a paste.
	 *
	 *  @param {string} text   What is in the box.
	 *  @param {Array}  nodes  The live folders ({ id, parent, name }), for reuse.
	 *  @param {Object} l10n   The refusal strings, translated; English by default.
	 *  @return {{ folders: Array, count: number, levels: number, reused: number, refusals: Array, lines: number }}
	 *          folders: [ { key, term_id, name, parent, path, depth, reused } ], parents before children.
	 *          refusals: [ { line, why: 'empty' | 'deep' | 'cap', text } ].
	 */
	function parse( text, nodes, l10n ) {
		var say = {};
		Object.keys( DEFAULT_L10N ).forEach( function ( k ) {
			say[ k ] = ( l10n && l10n[ k ] ) || DEFAULT_L10N[ k ];
		} );

		var live = liveByPath( nodes );
		var folders = [];
		var byPath = {};
		var refusals = [];
		var levels = 0;
		var reused = 0;
		var rawLines = String( text || '' ).split( /\r?\n/ );

		rawLines.forEach( function ( raw, i ) {
			var line = i + 1;
			if ( '' === raw.trim() ) {
				return;
			}
			var segments = raw.split( '>' ).map( function ( s ) {
				return s.replace( /\//g, '-' ).trim();
			} ).filter( Boolean );

			if ( ! segments.length ) {
				refusals.push( { line: line, why: 'empty', text: sprintf( say.noName, line ) } );
				return;
			}
			if ( segments.length > MAX_LEVELS ) {
				refusals.push( { line: line, why: 'deep', text: sprintf( say.tooDeep, line, MAX_LEVELS ) } );
				return;
			}

			var path = [];
			var parentKey = '';
			segments.forEach( function ( name ) {
				path = path.concat( [ name ] );
				var lookup = path.map( lower ).join( '/' );
				var found = byPath[ lookup ];
				if ( ! found ) {
					var node = live[ lookup ] || null;
					if ( node ) {
						// The folder's own spelling, not the typed one.
						path[ path.length - 1 ] = node.name;
					}
					found = {
						key: node ? 't' + node.id : keyFor( path.map( lower ) ),
						term_id: node ? node.id : null,
						name: node ? node.name : name,
						parent: parentKey,
						path: path.slice(),
						depth: path.length,
						reused: !! node
					};
					byPath[ lookup ] = found;
					folders.push( found );
					if ( node ) {
						reused++;
					}
					if ( path.length > levels ) {
						levels = path.length;
					}
				}
				parentKey = found.key;
			} );
		} );

		if ( folders.length > MAX_FOLDERS ) {
			refusals.push( { line: 0, why: 'cap', text: sprintf( say.tooMany, folders.length, MAX_FOLDERS ) } );
		}

		return {
			folders: folders,
			count: folders.length,
			levels: levels,
			reused: reused,
			refusals: refusals,
			lines: rawLines.length
		};
	}

	/**
	 *  The draft the paste describes, over the live tree: every live folder
	 *  kept as it is, and the folders the paste adds. A pasted path that
	 *  exists is the live folder itself, not a second one.
	 *
	 *  Shape as js/vergeml-tree-view.js and core/guide.php take it.
	 */
	function toDraft( parsed, nodes ) {
		var folders = ( nodes || [] ).map( function ( n ) {
			return { key: 't' + n.id, term_id: n.id, name: n.name, parent: n.parent ? 't' + n.parent : '' };
		} );
		parsed.folders.forEach( function ( f ) {
			if ( f.reused ) {
				return;
			}
			folders.push( {
				key: f.key,
				term_id: null,
				name: f.name,
				parent: f.parent,
				count: null,
				matches: '',
				classes: [],
				kinds: [],
				audience: '',
				by: ''
			} );
		} );
		return { folders: folders, gone: {}, tags: [], origin: 'talk', rule: null };
	}

	/**
	 *  Only what the paste names, for the preview beside the box: the reused
	 *  live folders as the model, the paste as the draft, so a folder that
	 *  exists reads as itself and a new one wears the tree's own "new" mark.
	 */
	function toPreview( parsed, nodes ) {
		var keep = {};
		parsed.folders.forEach( function ( f ) {
			if ( f.term_id ) {
				keep[ f.term_id ] = true;
			}
		} );
		var live = ( nodes || [] ).filter( function ( n ) { return keep[ n.id ]; } );
		return {
			nodes: live,
			draft: {
				folders: parsed.folders.map( function ( f ) {
					return { key: f.key, term_id: f.term_id, name: f.name, parent: f.parent, count: null };
				} ),
				gone: {}
			}
		};
	}

	window.vergemlStructure = {
		version: 1,
		MAX_LEVELS: MAX_LEVELS,
		MAX_FOLDERS: MAX_FOLDERS,
		parse: parse,
		toDraft: toDraft,
		toPreview: toPreview
	};
}() );
