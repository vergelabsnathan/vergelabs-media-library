/*
 *  One tree, two surfaces.
 *
 *  The media library's folder panel (js/vergeml-tree.js) and the Folders
 *  screen draw the same folders from the same rows built here: the model that
 *  indexes the terms, the walk that flattens them into rows, and the row
 *  markup itself -- twist, icon, name, count. The panel adds its own pseudo
 *  rows, drag and drop, menus and windowing around these; the Folders screen
 *  adds the draft.
 *
 *  The draft is keyed by term id. A draft folder that exists today carries its
 *  term_id; a folder the draft would make has term_id null and a client key.
 *  Nothing here matches by name, which is what made the old Sort screen read a
 *  rename in the library as a removal plus an addition. When the live tree
 *  changes under a draft -- the folders version stamp says so -- the draft is
 *  rebased: an edit the draft made stays, a field it did not touch follows the
 *  library, a folder the library deleted becomes a new folder, a folder the
 *  library made since is adopted as kept.
 *
 *  Two states past a handful of folders, a switch at the head:
 *
 *    Changes      only the folders that change, grouped under their parent
 *                 path. The default while a draft has changes.
 *    All folders  the whole tree by branch: top level collapsed, a branch
 *                 holding a change opens by itself, a collapsed branch holding
 *                 changes carries "N changes", and inside an open branch the
 *                 siblings that do not change fold into one row.
 *
 *  A find box appears past twenty folders. Plain JavaScript, no build step,
 *  as everything else in this plugin.
 */

( function () {
	'use strict';

	/* ---------------------------------------------------------------- utils */

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( k ) {
				if ( attrs[ k ] === null || attrs[ k ] === undefined || attrs[ k ] === false ) {
					return;
				}
				node.setAttribute( k, String( attrs[ k ] ) );
			} );
		}
		if ( text !== undefined && text !== null ) {
			node.textContent = String( text );
		}
		return node;
	}

	function sprintf( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s ).replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			var at = pos ? parseInt( pos, 10 ) - 1 : i++;
			return args[ at ] === undefined ? '' : String( args[ at ] );
		} );
	}

	function fmt( n ) {
		n = Number( n ) || 0;
		try {
			return n.toLocaleString();
		} catch ( e ) {
			return String( n );
		}
	}

	function plural( l10n, one, many, n ) {
		return 1 === n ? sprintf( l10n[ one ], fmt( n ) ) : sprintf( l10n[ many ], fmt( n ) );
	}

	var DEFAULT_L10N = {
		changes: 'Changes',
		all: 'All',
		states: 'Which folders to show',
		find: 'Find a folder',
		topLevel: 'Top level',
		newTag: 'new',
		was: 'was %s',
		removedTo: 'removed · %1$s pictures go to %2$s',
		removedNowhere: 'removed · %s pictures go to no folder',
		movedFrom: 'moved from %s',
		movedFromTop: 'moved from the top level',
		byYou: ', by you',
		renamedFrom: 'renamed from %s',
		folder1: '1 folder',
		folderN: '%s folders',
		change1: '1 change',
		changeN: '%s changes',
		more1: '1 more folder, unchanged',
		moreN: '%s more folders, unchanged',
		afterMove: '%s pictures after Move',
		fromFolders: '%1$s from %2$s',
		ofN: 'of %s',
		ofNLeft: 'of %s left',
		noChanges: 'No changes yet',
		nothingFound: 'No folder matches',
		rename: 'Rename',
		remove: 'Remove from the draft'
	};

	/* --------------------------------------------------------------- glyphs */

	function chevron() {
		return '<svg viewBox="0 0 12 12" width="12" height="12" fill="none">'
			+ '<path d="M4.2 2.4 L8 6 L4.2 9.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	/*
	 *  Flat. One silhouette, one colour, no simulated depth. Closed is a single
	 *  solid rounded folder; open is a thin band of the back sheet above a
	 *  tilted front flap with a real gap between them, so the geometry says
	 *  "open" without a second tone. Empty folders keep the outline treatment
	 *  from the stylesheet.
	 */
	function folderIcon( color, open, empty ) {

		var span = el( 'span', {
			class: 'vgml-icon' + ( empty ? ' is-empty' : '' ) + ( open ? ' is-open' : '' ),
			'aria-hidden': 'true'
		} );

		var closedShape = 'M1 4.4a2.2 2.2 0 0 1 2.2-2.2h4a2.2 2.2 0 0 1 1.68.78l.9 1.06h7A2.2 2.2 0 0 1 19 6.24v6.56A2.2 2.2 0 0 1 16.8 15H3.2A2.2 2.2 0 0 1 1 12.8V4.4Z';
		var openBack = 'M1 4.4a2.2 2.2 0 0 1 2.2-2.2h4a2.2 2.2 0 0 1 1.68.78l.9 1.06h7A2.2 2.2 0 0 1 19 6.24v0.36H1V4.4Z';
		var openFlap = 'M4.75 8h13.5a1.45 1.45 0 0 1 1.4 1.84l-0.95 3.5A2.2 2.2 0 0 1 16.58 15H2.75a1.45 1.45 0 0 1-1.4-1.84l0.95-3.5A2.2 2.2 0 0 1 4.42 8h0.33Z';

		var showOpen = open && ! empty;
		span.innerHTML = '<svg viewBox="0 0 20 16" width="20" height="16">' +
			'<path class="vgml-f-back" d="' + ( showOpen ? openBack : closedShape ) + '"/>' +
			( showOpen ? '<path class="vgml-f-front" d="' + openFlap + '"/>' : '' ) + '</svg>';

		if ( color ) {
			span.style.color = color;
		}

		return span;
	}

	/* ------------------------------------------------------------ the model */

	/*
	 *  The live tree: the nodes the endpoint returns, indexed by id and by
	 *  parent, siblings in the order the panel has always shown them --
	 *  the remembered order first, then the name.
	 */
	function Model( nodes ) {
		this.nodes = [];
		this.byId = {};
		this.children = {};
		this.index( nodes || [] );
	}

	Model.prototype.index = function ( nodes ) {
		var byId = {};
		var children = {};
		this.nodes = nodes;
		nodes.forEach( function ( n ) {
			byId[ n.id ] = n;
			( children[ n.parent ] = children[ n.parent ] || [] ).push( n );
		} );
		Object.keys( children ).forEach( function ( p ) {
			children[ p ].sort( function ( a, b ) {
				return ( a.order || 0 ) - ( b.order || 0 ) || String( a.name ).localeCompare( String( b.name ) );
			} );
		} );
		this.byId = byId;
		this.children = children;
		return this;
	};

	/*
	 *  Descendant-inclusive totals, in one pass, bottom up over the child map.
	 *  Clicking a folder shows everything beneath it, so a folder reading 0
	 *  that opens full of files is a bug report.
	 */
	Model.prototype.totals = function () {
		var out = {};
		var order = [];
		var children = this.children;

		( function walk( id ) {
			( children[ id ] || [] ).forEach( function ( n ) {
				order.push( n );
				walk( n.id );
			} );
		} )( 0 );

		for ( var i = order.length - 1; i >= 0; i-- ) {
			var n = order[ i ];
			var sum = n.count || 0;
			( children[ n.id ] || [] ).forEach( function ( c ) {
				sum += out[ c.id ];
			} );
			out[ n.id ] = sum;
		}
		return out;
	};

	Model.prototype.matches = function ( node, filter ) {
		if ( ! filter ) {
			return true;
		}
		return String( node.name ).toLowerCase().indexOf( filter ) !== -1;
	};

	// A folder stays visible when it matches, or when anything beneath it does --
	// otherwise searching for a leaf hides the path that leads to it.
	Model.prototype.visible = function ( node, filter ) {
		if ( this.matches( node, filter ) ) {
			return true;
		}
		var kids = this.children[ node.id ] || [];
		for ( var i = 0; i < kids.length; i++ ) {
			if ( this.visible( kids[ i ], filter ) ) {
				return true;
			}
		}
		return false;
	};

	/*
	 *  The live rows, flattened: what the panel draws under its pseudo rows.
	 *  opts.open is the map of open ids; while searching every branch on the
	 *  way to a match is open.
	 */
	Model.prototype.entries = function ( opts ) {
		var self = this;
		var filter = ( opts && opts.filter ) || '';
		var openMap = ( opts && opts.open ) || {};
		var total = this.totals();
		var out = [];

		( function walk( parentId, depth ) {
			var siblings = ( self.children[ parentId ] || [] ).filter( function ( n ) {
				return self.visible( n, filter );
			} );

			siblings.forEach( function ( node, i ) {
				var kids = ( self.children[ node.id ] || [] ).filter( function ( n ) {
					return self.visible( n, filter );
				} );
				var open = filter ? true : !! openMap[ node.id ];

				out.push( {
					node: node,
					name: node.name,
					depth: depth,
					id: node.id,
					key: 't' + node.id,
					total: total[ node.id ],
					kids: kids.length,
					open: open,
					posinset: i + 1,
					setsize: siblings.length,
					status: 'same'
				} );

				if ( kids.length && open ) {
					walk( node.id, depth + 1 );
				}
			} );
		} )( 0, 0 );

		return out;
	};

	Model.prototype.pathNames = function ( id ) {
		var names = [];
		var guard = 0;
		var node = this.byId[ id ];
		while ( node && guard++ < 64 ) {
			names.unshift( node.name );
			node = this.byId[ node.parent ];
		}
		return names;
	};

	/* ------------------------------------------------------------ the draft */

	/*
	 *  draft = { folders: [ { key, term_id, name, parent, count, by, from, samples } ], gone: { term_id: key } }
	 *
	 *  key      a client key; 't' + term_id for a folder that exists, anything
	 *           unique for one that does not.
	 *  parent   the parent's key, '' at the top level.
	 *  count    pictures in the folder itself after Move; absent means unchanged.
	 *  gone     where the pictures of a live folder the draft drops end up.
	 */

	var uid = 0;

	function newKey() {
		uid++;
		return 'n' + Date.now().toString( 36 ) + uid;
	}

	function keyForTerm( draft, id ) {
		id = Number( id ) || 0;
		if ( ! id ) {
			return '';
		}
		for ( var i = 0; i < draft.folders.length; i++ ) {
			if ( Number( draft.folders[ i ].term_id ) === id ) {
				return draft.folders[ i ].key;
			}
		}
		return 't' + id;
	}

	/** Keys and parent keys filled in, on a copy. */
	function normaliseDraft( draft ) {
		var out = { folders: [], gone: {} };
		if ( ! draft ) {
			return null;
		}
		( draft.folders || [] ).forEach( function ( f ) {
			var g = {};
			Object.keys( f ).forEach( function ( k ) { g[ k ] = f[ k ]; } );
			g.term_id = f.term_id ? Number( f.term_id ) : null;
			g.key = f.key || ( g.term_id ? 't' + g.term_id : newKey() );
			g.parent = f.parent === undefined || f.parent === null ? '' : f.parent;
			out.folders.push( g );
		} );
		out.folders.forEach( function ( f ) {
			// A numeric parent is a term id; it becomes that folder's key.
			if ( typeof f.parent === 'number' ) {
				f.parent = keyForTerm( out, f.parent );
			}
		} );
		Object.keys( draft.gone || {} ).forEach( function ( id ) {
			out.gone[ id ] = draft.gone[ id ];
		} );
		return out;
	}

	/** Every live folder, kept as it is. */
	function fromLive( nodes ) {
		return {
			folders: ( nodes || [] ).map( function ( n ) {
				return { key: 't' + n.id, term_id: n.id, name: n.name, parent: n.parent ? 't' + n.parent : '' };
			} ),
			gone: {}
		};
	}

	function isUnder( draft, key, ancestorKey ) {
		var byKey = {};
		draft.folders.forEach( function ( f ) { byKey[ f.key ] = f; } );
		var walk = byKey[ key ];
		var guard = 0;
		while ( walk && guard++ < 64 ) {
			if ( walk.key === ancestorKey ) {
				return true;
			}
			walk = byKey[ walk.parent ];
		}
		return false;
	}

	/*
	 *  One hand edit, applied to a copy of the draft. The Folders screen turns
	 *  the same edit into a line in the conversation.
	 */
	function applyEdit( draft, edit ) {
		var next = normaliseDraft( draft || { folders: [] } );
		var f = null;
		next.folders.forEach( function ( g ) {
			if ( g.key === edit.key ) {
				f = g;
			}
		} );

		switch ( edit.type ) {

			case 'rename':
				if ( f && edit.to ) {
					f.name = String( edit.to ).replace( /\//g, '-' ).trim() || f.name;
					f.by = edit.by || f.by;
				}
				break;

			case 'reparent':
				if ( f && edit.parent !== f.key && ! ( edit.parent && isUnder( next, edit.parent, f.key ) ) ) {
					f.parent = edit.parent || '';
					f.by = edit.by || f.by;
				}
				break;

			case 'remove':
				if ( f ) {
					next.folders.forEach( function ( g ) {
						if ( g.parent === f.key ) {
							g.parent = f.parent;
						}
					} );
					next.folders = next.folders.filter( function ( g ) { return g !== f; } );
					if ( f.term_id && edit.to ) {
						next.gone[ f.term_id ] = edit.to;
					}
				}
				break;

			case 'add':
				next.folders.push( {
					key: edit.key || newKey(),
					term_id: null,
					name: String( edit.name || '' ).replace( /\//g, '-' ).trim(),
					parent: edit.parent || '',
					count: edit.count || 0,
					by: edit.by || ''
				} );
				break;
		}

		return next;
	}

	/*
	 *  The draft carried onto a changed tree.
	 *
	 *  By id: a field the draft edited stays; a field it left alone follows
	 *  the library; a folder the library deleted becomes a new folder with the
	 *  same name and place; a folder the library made since the last look is
	 *  adopted as kept -- it was never this draft's to remove.
	 */
	function rebase( draft, before, after ) {
		var out = normaliseDraft( draft );
		if ( ! out ) {
			return null;
		}
		var known = {};

		out.folders.forEach( function ( f ) {
			if ( ! f.term_id ) {
				return;
			}
			var was = before.byId[ f.term_id ];
			var now = after.byId[ f.term_id ];

			if ( ! now ) {
				f.term_id = null;
				return;
			}
			known[ f.term_id ] = true;

			if ( was ) {
				if ( f.name === was.name && now.name !== was.name ) {
					f.name = now.name;
				}
				var parentWas = keyForTerm( out, was.parent );
				if ( f.parent === parentWas && now.parent !== was.parent ) {
					f.parent = keyForTerm( out, now.parent );
				}
			}
		} );

		after.nodes.forEach( function ( n ) {
			if ( known[ n.id ] || before.byId[ n.id ] || out.gone[ n.id ] ) {
				return;
			}
			out.folders.push( { key: 't' + n.id, term_id: n.id, name: n.name, parent: keyForTerm( out, n.parent ) } );
		} );

		Object.keys( out.gone ).forEach( function ( id ) {
			if ( ! after.byId[ id ] ) {
				delete out.gone[ id ];
			}
		} );

		return out;
	}

	/* ---------------------------------------------------------- the overlay */

	/*
	 *  Draft against live, as rows keyed by client key.
	 *
	 *  status  same | added | changed | removed
	 *  after   pictures in and below the folder after Move
	 *  was     pictures in and below the live folder now, when it differs
	 */
	function overlay( model, draft ) {
		var liveTotals = model.totals();
		var rows = {};
		var byTerm = {};
		var order = [];

		draft.folders.forEach( function ( f ) {
			var live = f.term_id ? model.byId[ f.term_id ] || null : null;
			rows[ f.key ] = {
				key: f.key,
				term_id: live ? live.id : null,
				name: f.name,
				parent: f.parent || '',
				own: f.count !== undefined && f.count !== null ? Number( f.count ) : ( live ? live.count || 0 : 0 ),
				live: live,
				node: live,
				status: 'same',
				by: f.by || '',
				from: f.from || [],
				samples: f.samples || [],
				order: live ? live.order || 0 : 0
			};
			if ( live ) {
				byTerm[ live.id ] = f.key;
			}
			order.push( f.key );
		} );

		model.nodes.forEach( function ( n ) {
			if ( byTerm[ n.id ] ) {
				return;
			}
			var key = 't' + n.id;
			if ( rows[ key ] ) {
				key = 'gone' + n.id;
			}
			rows[ key ] = {
				key: key,
				term_id: n.id,
				name: n.name,
				parent: n.parent ? ( byTerm[ n.parent ] || 't' + n.parent ) : '',
				own: 0,
				live: n,
				node: n,
				status: 'removed',
				to: draft.gone && draft.gone[ n.id ] ? draft.gone[ n.id ] : '',
				was: liveTotals[ n.id ],
				by: '',
				from: [],
				samples: [],
				order: n.order || 0
			};
			order.push( key );
		} );

		var children = {};
		order.forEach( function ( key ) {
			var row = rows[ key ];
			// An orphan -- a parent key that names nothing -- sits at the top.
			if ( row.parent && ! rows[ row.parent ] ) {
				row.parent = '';
			}
			( children[ row.parent ] = children[ row.parent ] || [] ).push( row );
		} );
		Object.keys( children ).forEach( function ( p ) {
			children[ p ].sort( function ( a, b ) {
				return a.order - b.order || String( a.name ).localeCompare( String( b.name ) );
			} );
		} );

		// Totals after Move, bottom up. A removed folder holds nothing afterwards.
		var after = {};
		var below = {};
		( function sum( key ) {
			var total = rows[ key ] ? rows[ key ].own : 0;
			var count = 0;
			( children[ key ] || [] ).forEach( function ( c ) {
				total += sum( c.key );
				count += 1 + below[ c.key ];
			} );
			if ( key ) {
				after[ key ] = total;
			}
			below[ key ] = count;
			return total;
		} )( '' );

		/*
		 *  Two numbers per folder, as the approved mock has them: the folder's
		 *  own pictures on an open row or a leaf, the whole branch on a
		 *  collapsed row. A change of count is a change of the folder's own
		 *  pictures -- a parent whose child moves in is not itself changed.
		 */
		order.forEach( function ( key ) {
			var row = rows[ key ];
			row.after = after[ key ];
			row.foldersBelow = below[ key ];
			row.liveOwn = row.live ? row.live.count || 0 : 0;
			row.liveTotal = row.live ? liveTotals[ row.live.id ] : 0;
			if ( 'removed' === row.status ) {
				row.was = row.liveTotal;
				return;
			}
			if ( ! row.live ) {
				row.status = 'added';
				return;
			}
			row.renamedFrom = row.name !== row.live.name ? row.live.name : '';
			var liveParentKey = row.live.parent ? ( byTerm[ row.live.parent ] || 't' + row.live.parent ) : '';
			row.movedFrom = row.parent !== liveParentKey
				? ( row.live.parent && model.byId[ row.live.parent ] ? model.byId[ row.live.parent ].name : '' )
				: null;
			if ( row.renamedFrom || row.movedFrom !== null || row.own !== row.liveOwn ) {
				row.status = 'changed';
			}
		} );

		var changesUnderMemo = {};
		function changesUnder( key ) {
			if ( changesUnderMemo[ key ] !== undefined ) {
				return changesUnderMemo[ key ];
			}
			var n = rows[ key ] && 'same' !== rows[ key ].status ? 1 : 0;
			( children[ key ] || [] ).forEach( function ( c ) {
				n += changesUnder( c.key );
			} );
			changesUnderMemo[ key ] = n;
			return n;
		}

		var changes = 0;
		order.forEach( function ( key ) {
			if ( 'same' !== rows[ key ].status ) {
				changes++;
			}
		} );

		return {
			rows: rows,
			children: children,
			order: order,
			changes: changes,
			now: model.nodes.length,
			after: draft.folders.length,
			changesUnder: changesUnder,
			pathOf: function ( key ) {
				var names = [];
				var guard = 0;
				var walk = rows[ key ];
				while ( walk && guard++ < 64 ) {
					names.unshift( walk.name );
					walk = rows[ walk.parent ];
				}
				return names;
			}
		};
	}

	/* ------------------------------------------------------------- the view */

	function TreeView( opts ) {
		opts = opts || {};
		this.surface = opts.surface || 'library';
		this.root = opts.root || null;
		this.l10n = {};
		var self = this;
		Object.keys( DEFAULT_L10N ).forEach( function ( k ) {
			self.l10n[ k ] = ( opts.l10n && opts.l10n[ k ] ) || DEFAULT_L10N[ k ];
		} );
		this.indent = opts.indent || { step: 19, base: 8 };
		this.editable = !! opts.editable;
		this.onEdit = opts.onEdit || function () {};
		this.onToggle = opts.onToggle || null;
		this.onHover = opts.onHover || null;
		this.model = opts.model || new Model( opts.nodes || [] );
		this.draft = null;
		this.mode = 'all';
		this.modeChosen = false;
		this.filter = '';
		this.openOverride = {};
		this.unfolded = {};
		this.seen = null;
		this.overlay = null;
		this.progress = null;
		this.listEl = null;
		this.headEl = null;
		this.findEl = null;
		this.editing = '';

		if ( this.root ) {
			this.root.classList.add( 'vgml-tv' );
			this.root.setAttribute( 'data-surface', this.surface );
			if ( opts.accent ) {
				this.root.style.setProperty( '--vgml-accent', opts.accent );
			}
		}
		if ( opts.draft ) {
			this.setDraft( opts.draft );
		}
	}

	TreeView.prototype.setTree = function ( nodes ) {
		var before = this.model;
		var after = new Model( nodes || [] );
		if ( this.draft ) {
			this.draft = rebase( this.draft, before, after );
		}
		this.model = after;
		this.refresh();
		return this;
	};

	TreeView.prototype.setDraft = function ( draft ) {
		this.draft = normaliseDraft( draft );
		if ( ! this.draft ) {
			this.mode = 'all';
			this.modeChosen = false;
		}
		this.refresh();
		return this;
	};

	TreeView.prototype.getDraft = function () {
		return this.draft;
	};

	TreeView.prototype.setMode = function ( mode, chosen ) {
		this.mode = 'changes' === mode ? 'changes' : 'all';
		this.modeChosen = chosen !== false;
		this.render();
		return this;
	};

	TreeView.prototype.setFilter = function ( text ) {
		this.filter = String( text || '' ).toLowerCase();
		this.render();
		return this;
	};

	TreeView.prototype.refresh = function () {
		this.overlay = this.draft ? overlay( this.model, this.draft ) : null;
		if ( ! this.modeChosen ) {
			this.mode = this.overlay && this.overlay.changes > 0 ? 'changes' : 'all';
		}
		this.render();
	};

	/*
	 *  now, after, changes -- and moving: the pictures a Move would put
	 *  somewhere new, read off the rows as the approved mock has it: what a
	 *  folder gains over what it holds today, summed. A folder that only
	 *  moves or is renamed moves no pictures.
	 */
	TreeView.prototype.summary = function () {
		var o = this.overlay;
		var moving = 0;
		if ( o ) {
			o.order.forEach( function ( key ) {
				var row = o.rows[ key ];
				if ( 'removed' !== row.status && row.own > row.liveOwn ) {
					moving += row.own - row.liveOwn;
				}
			} );
		}
		return {
			now: this.model.nodes.length,
			after: o ? o.after : this.model.nodes.length,
			changes: o ? o.changes : 0,
			moving: moving
		};
	};

	/*
	 *  While a Move runs: pictures landed so far by term id. A row with an
	 *  entry reads "128 of 152" with a fill beneath it; null clears it.
	 */
	TreeView.prototype.setProgress = function ( byTerm ) {
		this.progress = byTerm && typeof byTerm === 'object' ? byTerm : null;
		this.render();
		return this;
	};

	TreeView.prototype.isOpen = function ( key ) {
		if ( this.filter ) {
			return true;
		}
		if ( this.openOverride[ key ] !== undefined ) {
			return this.openOverride[ key ];
		}
		return !! ( this.overlay && this.overlay.changesUnder( key ) > 0 );
	};

	TreeView.prototype.toggle = function ( key ) {
		this.openOverride[ key ] = ! this.isOpen( key );
		if ( this.onToggle ) {
			this.onToggle( key, this.openOverride[ key ] );
		}
		this.render();
	};

	TreeView.prototype.unfold = function ( parentKey ) {
		this.unfolded[ parentKey ] = true;
		this.render();
	};

	/* --- the rows of the Folders surface --- */

	TreeView.prototype.visibleRow = function ( row ) {
		var self = this;
		if ( ! this.filter ) {
			return true;
		}
		if ( String( row.name ).toLowerCase().indexOf( this.filter ) !== -1 ) {
			return true;
		}
		return ( this.overlay.children[ row.key ] || [] ).some( function ( c ) {
			return self.visibleRow( c );
		} );
	};

	TreeView.prototype.subFor = function ( row ) {
		var l10n = this.l10n;
		var o = this.overlay;
		if ( 'removed' === row.status ) {
			var to = row.to && o.rows[ row.to ] ? o.rows[ row.to ].name : '';
			return to
				? sprintf( l10n.removedTo, fmt( row.was ), to )
				: sprintf( l10n.removedNowhere, fmt( row.was ) );
		}
		if ( row.movedFrom !== null && row.movedFrom !== undefined ) {
			return ( row.movedFrom ? sprintf( l10n.movedFrom, row.movedFrom ) : l10n.movedFromTop )
				+ ( 'you' === row.by ? l10n.byYou : '' );
		}
		if ( row.renamedFrom ) {
			return sprintf( l10n.renamedFrom, row.renamedFrom ) + ( 'you' === row.by ? l10n.byYou : '' );
		}
		return '';
	};

	TreeView.prototype.entryFor = function ( row, depth, i, n ) {
		var o = this.overlay;
		var kids = ( o.children[ row.key ] || [] );
		var open = kids.length ? this.isOpen( row.key ) : false;
		var under = o.changesUnder( row.key ) - ( 'same' !== row.status ? 1 : 0 );
		var collapsed = kids.length > 0 && ! open;
		var shown = 'removed' === row.status ? row.liveTotal : ( collapsed ? row.after : row.own );
		var was = 'removed' === row.status ? null : ( collapsed ? row.liveTotal : row.liveOwn );
		return {
			key: row.key,
			id: row.term_id || 0,
			node: row.live,
			row: row,
			name: row.name,
			depth: depth,
			total: row.after,
			shown: shown,
			was: row.live && was !== shown ? was : null,
			kids: kids.length,
			open: open,
			posinset: i + 1,
			setsize: n,
			status: row.status,
			meta: kids.length && ! open ? plural( this.l10n, 'folder1', 'folderN', row.foldersBelow ) : '',
			mark: kids.length && ! open && under > 0 ? plural( this.l10n, 'change1', 'changeN', under ) : '',
			sub: this.subFor( row )
		};
	};

	TreeView.prototype.entries = function () {
		var self = this;
		var o = this.overlay;
		var out = [];

		if ( ! o ) {
			var openMap = {};
			Object.keys( this.openOverride ).forEach( function ( key ) {
				if ( self.openOverride[ key ] ) {
					openMap[ key.replace( /^t/, '' ) ] = true;
				}
			} );
			return this.model.entries( { open: openMap, filter: this.filter } );
		}

		if ( 'changes' === this.mode ) {
			var groups = {};
			var names = [];
			o.order.forEach( function ( key ) {
				var row = o.rows[ key ];
				if ( 'same' === row.status || ! self.visibleRow( row ) ) {
					return;
				}
				if ( self.filter && String( row.name ).toLowerCase().indexOf( self.filter ) === -1 ) {
					return;
				}
				var path = o.pathOf( row.parent ).join( ' / ' ) || self.l10n.topLevel;
				if ( ! groups[ path ] ) {
					groups[ path ] = [];
					names.push( path );
				}
				groups[ path ].push( row );
			} );
			names.sort( function ( a, b ) {
				if ( a === self.l10n.topLevel ) { return 1; }
				if ( b === self.l10n.topLevel ) { return -1; }
				return a.localeCompare( b );
			} );
			names.forEach( function ( path ) {
				out.push( { path: path, key: 'path:' + path, depth: 0 } );
				groups[ path ].forEach( function ( row, i ) {
					var entry = self.entryFor( row, 0, i, groups[ path ].length );
					entry.kids = 0;
					entry.open = false;
					entry.meta = '';
					entry.mark = '';
					out.push( entry );
					if ( entry.sub ) {
						out.push( { line: entry.sub, key: row.key + ':sub', depth: 0, status: row.status } );
					}
				} );
			} );
			return out;
		}

		( function walk( parentKey, depth ) {
			var kids = ( o.children[ parentKey ] || [] ).filter( function ( r ) {
				return self.visibleRow( r );
			} );
			var shown = kids;
			var folded = [];

			/*
			 *  Inside an open branch that holds a change, the siblings that
			 *  do not change fold into one row -- the leaves, that is. A
			 *  branch that does not change stays, collapsed, because it is
			 *  the way into the rest of the tree (the mock keeps Kids under
			 *  Apparel and folds the leaves under Women). Never at the top
			 *  level, never while searching, and not once somebody has
			 *  opened the fold.
			 */
			var holdsChange = kids.some( function ( r ) { return o.changesUnder( r.key ) > 0; } );
			var isLeaf = function ( r ) { return ! ( o.children[ r.key ] || [] ).length; };
			if ( depth > 0 && holdsChange && ! self.filter && ! self.unfolded[ parentKey ] ) {
				shown = kids.filter( function ( r ) { return o.changesUnder( r.key ) > 0 || ! isLeaf( r ); } );
				folded = kids.filter( function ( r ) { return 0 === o.changesUnder( r.key ) && isLeaf( r ); } );
			}

			shown.forEach( function ( row, i ) {
				var entry = self.entryFor( row, depth, i, shown.length + ( folded.length ? 1 : 0 ) );
				out.push( entry );
				if ( entry.sub ) {
					out.push( { line: entry.sub, key: row.key + ':sub', depth: depth, status: row.status } );
				}
				if ( entry.kids && entry.open ) {
					walk( row.key, depth + 1 );
				}
			} );

			if ( folded.length ) {
				var total = 0;
				folded.forEach( function ( r ) { total += r.after; } );
				out.push( {
					fold: { parent: parentKey, count: folded.length, total: total },
					key: parentKey + ':more',
					depth: depth,
					posinset: shown.length + 1,
					setsize: shown.length + 1
				} );
			}
		} )( '', 0 );

		return out;
	};

	/* --- the shared row --- */

	/*
	 *  The one row both surfaces draw. hooks.selected marks it; hooks.onToggle
	 *  is what the twist does. The panel decorates what comes back -- privacy
	 *  tag, drop target, drag source, menus -- and the Folders surface adds
	 *  the draft's marks.
	 */
	TreeView.prototype.folderRow = function ( entry, hooks ) {
		var self = this;
		var l10n = this.l10n;
		var folders = 'folders' === this.surface;
		var status = entry.status || 'same';
		hooks = hooks || {};

		var item = el( 'li', {
			class: 'vgml-node'
				+ ( hooks.selected ? ' is-selected' : '' )
				+ ( 'added' === status ? ' is-change is-new' : '' )
				+ ( 'changed' === status ? ' is-change' : '' )
				+ ( 'removed' === status ? ' is-gone' : '' ),
			role: 'treeitem',
			'aria-level': entry.depth + 1,
			'aria-posinset': entry.posinset,
			'aria-setsize': entry.setsize,
			'aria-selected': hooks.selected ? 'true' : 'false',
			'data-id': entry.id,
			'data-key': folders ? entry.key : null,
			tabindex: '-1'
		} );

		if ( entry.kids ) {
			item.setAttribute( 'aria-expanded', entry.open ? 'true' : 'false' );
		}

		var row = el( 'div', { class: 'vgml-row', style: '--vgml-indent:' + ( entry.depth * this.indent.step + this.indent.base ) + 'px' } );

		var twist = el( 'button', {
			class: 'vgml-twist' + ( entry.kids ? '' : ' is-leaf' ),
			type: 'button',
			tabindex: '-1',
			'aria-hidden': 'true'
		} );
		twist.innerHTML = entry.kids ? chevron() : '';
		if ( entry.open ) {
			twist.classList.add( 'is-open' );
		}
		twist.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			if ( hooks.onToggle ) {
				hooks.onToggle( folders ? entry.key : entry.id );
			}
		} );
		row.appendChild( twist );

		if ( folders ) {
			var handle = el( 'span', { class: 'vgml-handle', 'aria-hidden': 'true', draggable: this.editable && 'removed' !== status ? 'true' : null }, '⋮⋮' );
			row.appendChild( handle );
		}

		// `total` is descendant-inclusive, so a parent whose own count is zero
		// but whose children hold files still reads as full.
		row.appendChild( folderIcon( entry.node ? entry.node.color : '', entry.open && entry.kids, ! entry.total ) );

		var name = el( 'span', { class: 'vgml-name' }, entry.name );
		if ( folders && 'added' === status ) {
			name.appendChild( el( 'span', { class: 'vgml-tag' }, l10n.newTag ) );
		}
		row.appendChild( name );

		if ( folders && entry.meta ) {
			row.appendChild( el( 'span', { class: 'vgml-meta' }, entry.meta ) );
		}
		if ( folders && entry.mark ) {
			row.appendChild( el( 'span', { class: 'vgml-mark' }, entry.mark ) );
		}

		var count = null;
		if ( folders ) {
			var shown = entry.shown !== undefined ? entry.shown : entry.total;
			// By the draft's key first (a folder the Move makes has no term id until it does), then by term id.
			var landed = null;
			if ( this.progress ) {
				if ( this.progress[ entry.key ] !== undefined ) {
					landed = Number( this.progress[ entry.key ] );
				} else if ( entry.id && this.progress[ entry.id ] !== undefined ) {
					landed = Number( this.progress[ entry.id ] );
				}
			}
			if ( null !== landed && 'removed' !== status ) {
				// Moving: what has landed, of what will.
				count = el( 'span', { class: 'vgml-count' }, fmt( landed ) );
				count.appendChild( el( 'span', { class: 'vgml-was' }, sprintf( l10n.ofN, fmt( Math.max( landed, shown ) ) ) ) );
				row.appendChild( count );
				var fill = el( 'span', { class: 'vgml-fill', 'aria-hidden': 'true' } );
				fill.appendChild( el( 'i', { style: 'width:' + Math.min( 100, Math.round( 100 * landed / Math.max( 1, Math.max( landed, shown ) ) ) ) + '%' } ) );
				row.appendChild( fill );
			} else if ( shown || 'same' !== status ) {
				count = el( 'span', { class: 'vgml-count' }, fmt( shown ) );
				if ( entry.was !== null && entry.was !== undefined ) {
					count.appendChild( el( 'span', { class: 'vgml-was' }, sprintf( l10n.was, fmt( entry.was ) ) ) );
				}
				row.appendChild( count );
			}
		} else if ( entry.total ) {
			// A pill, not a bare number, and the number as it is: the panel has
			// always shown it unformatted.
			count = el( 'span', { class: 'vgml-count' }, String( entry.total ) );
			row.appendChild( count );
		}

		item.appendChild( row );

		if ( folders ) {
			this.wireFoldersRow( item, row, name, entry );
		}

		return { item: item, row: row, name: name, count: count };
	};

	/* --- interactions on the Folders surface --- */

	TreeView.prototype.wireFoldersRow = function ( item, row, name, entry ) {
		var self = this;
		var status = entry.status || 'same';

		row.addEventListener( 'click', function () {
			if ( self.editing ) {
				return;
			}
			if ( entry.kids ) {
				self.toggle( entry.key );
			}
		} );

		if ( this.editable && 'removed' !== status ) {
			name.addEventListener( 'dblclick', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				self.startRename( item, row, name, entry );
			} );
		}

		if ( this.editable ) {
			var handle = row.querySelector( '.vgml-handle' );
			if ( handle && 'removed' !== status ) {
				handle.addEventListener( 'dragstart', function ( e ) {
					e.dataTransfer.effectAllowed = 'move';
					try { e.dataTransfer.setData( 'text/plain', entry.key ); } catch ( err ) { /* IE */ }
					self.dragKey = entry.key;
					item.classList.add( 'is-dragging' );
				} );
				handle.addEventListener( 'dragend', function () {
					self.dragKey = '';
					item.classList.remove( 'is-dragging' );
					self.clearDrop();
				} );
			}
			if ( 'removed' !== status ) {
				row.addEventListener( 'dragover', function ( e ) {
					if ( ! self.dragKey || self.dragKey === entry.key || isUnder( self.draft, entry.key, self.dragKey ) ) {
						return;
					}
					e.preventDefault();
					e.dataTransfer.dropEffect = 'move';
					self.clearDrop();
					row.classList.add( 'is-drop' );
				} );
				row.addEventListener( 'dragleave', function () {
					row.classList.remove( 'is-drop' );
				} );
				row.addEventListener( 'drop', function ( e ) {
					if ( ! self.dragKey || self.dragKey === entry.key ) {
						return;
					}
					e.preventDefault();
					e.stopPropagation();
					var key = self.dragKey;
					self.dragKey = '';
					self.clearDrop();
					self.onEdit( { type: 'reparent', key: key, parent: entry.key, by: 'you' } );
				} );
			}
		}

		if ( 'same' !== status || ( entry.row && entry.row.samples && entry.row.samples.length ) ) {
			row.addEventListener( 'mouseenter', function () { self.showHover( item, entry ); } );
			row.addEventListener( 'mouseleave', function () { self.hideHover( item ); } );
			item.addEventListener( 'focus', function () { self.showHover( item, entry ); } );
			item.addEventListener( 'blur', function () { self.hideHover( item ); } );
		}
	};

	TreeView.prototype.clearDrop = function () {
		if ( ! this.listEl ) {
			return;
		}
		Array.prototype.forEach.call( this.listEl.querySelectorAll( '.vgml-row.is-drop' ), function ( r ) {
			r.classList.remove( 'is-drop' );
		} );
		this.listEl.classList.remove( 'is-drop' );
	};

	TreeView.prototype.startRename = function ( item, row, name, entry ) {
		var self = this;
		if ( this.editing ) {
			return;
		}
		this.editing = entry.key;
		var input = el( 'input', { type: 'text', class: 'vgml-editor', value: entry.name, 'aria-label': this.l10n.rename } );
		var done = false;

		function finish( commit ) {
			if ( done ) {
				return;
			}
			done = true;
			self.editing = '';
			var to = input.value.trim();
			if ( commit && to && to !== entry.name ) {
				self.onEdit( { type: 'rename', key: entry.key, from: entry.name, to: to, by: 'you' } );
			} else {
				self.render();
			}
		}

		input.addEventListener( 'keydown', function ( e ) {
			e.stopPropagation();
			if ( 'Enter' === e.key ) { e.preventDefault(); finish( true ); }
			if ( 'Escape' === e.key ) { e.preventDefault(); finish( false ); }
		} );
		input.addEventListener( 'blur', function () { finish( true ); } );
		input.addEventListener( 'click', function ( e ) { e.stopPropagation(); } );
		input.addEventListener( 'dblclick', function ( e ) { e.stopPropagation(); } );

		row.replaceChild( input, name );
		input.focus();
		input.select();
	};

	/*
	 *  Hover a folder: pictures after Move, where they come from, three of them.
	 *  Only for rows the draft changes; the rest have nothing to add.
	 */
	TreeView.prototype.showHover = function ( item, entry ) {
		var self = this;
		var l10n = this.l10n;
		var row = entry.row;
		if ( ! row || item.querySelector( '.vgml-tv-hover' ) ) {
			return;
		}
		if ( this.onHover && false === this.onHover( entry ) ) {
			return;
		}
		var card = el( 'div', { class: 'vgml-tv-hover', role: 'tooltip' } );
		var facts = el( 'ul', { class: 'vgml-facts' } );

		if ( 'removed' === row.status ) {
			facts.appendChild( el( 'li', null, entry.sub ) );
		} else {
			facts.appendChild( el( 'li', null, sprintf( l10n.afterMove, fmt( row.after ) ) ) );
			if ( row.from && row.from.length ) {
				var n = 0;
				var names = [];
				row.from.forEach( function ( f ) {
					n += Number( f.count ) || 0;
					var from = f.key && self.overlay.rows[ f.key ] ? self.overlay.rows[ f.key ]
						: ( f.term_id && self.model.byId[ f.term_id ] ? self.model.byId[ f.term_id ] : null );
					names.push( from ? from.name : ( f.name || '' ) );
				} );
				facts.appendChild( el( 'li', null, sprintf( l10n.fromFolders, fmt( n ), names.filter( Boolean ).join( ', ' ) ) ) );
			}
			if ( entry.sub ) {
				facts.appendChild( el( 'li', null, entry.sub ) );
			}
		}
		card.appendChild( facts );

		if ( row.samples && row.samples.length ) {
			var thumbs = el( 'div', { class: 'vgml-tv-thumbs' } );
			row.samples.slice( 0, 3 ).forEach( function ( src ) {
				thumbs.appendChild( el( 'img', { src: src, alt: '', loading: 'lazy' } ) );
			} );
			card.appendChild( thumbs );
		}

		item.appendChild( card );
	};

	TreeView.prototype.hideHover = function ( item ) {
		var card = item.querySelector( '.vgml-tv-hover' );
		if ( card ) {
			item.removeChild( card );
		}
	};

	/* --- keyboard, Folders surface --- */

	TreeView.prototype.onKey = function ( e ) {
		var self = this;
		var current = document.activeElement;
		if ( ! current || ! current.classList || ! current.classList.contains( 'vgml-node' ) || this.editing ) {
			return;
		}
		var rows = Array.prototype.slice.call( this.listEl.querySelectorAll( '.vgml-node[data-key]' ) );
		var at = rows.indexOf( current );
		var key = current.getAttribute( 'data-key' );
		var entry = this.lastEntries.filter( function ( en ) { return en.key === key; } )[ 0 ];

		function focusRow( r ) {
			if ( ! r ) {
				return;
			}
			rows.forEach( function ( x ) { x.setAttribute( 'tabindex', '-1' ); } );
			r.setAttribute( 'tabindex', '0' );
			r.focus();
		}

		switch ( e.key ) {
			case 'ArrowDown': e.preventDefault(); focusRow( rows[ at + 1 ] ); break;
			case 'ArrowUp': e.preventDefault(); focusRow( rows[ at - 1 ] ); break;
			case 'Home': e.preventDefault(); focusRow( rows[ 0 ] ); break;
			case 'End': e.preventDefault(); focusRow( rows[ rows.length - 1 ] ); break;
			case 'ArrowRight':
				e.preventDefault();
				if ( entry && entry.kids && ! entry.open ) { this.toggle( key ); this.focusKey( key ); }
				else if ( entry && entry.kids ) { focusRow( rows[ at + 1 ] ); }
				break;
			case 'ArrowLeft':
				e.preventDefault();
				if ( entry && entry.kids && entry.open ) { this.toggle( key ); this.focusKey( key ); }
				else if ( entry && entry.row && entry.row.parent ) { this.focusKey( entry.row.parent ); }
				break;
			case 'Enter':
			case ' ':
				if ( entry && entry.kids ) { e.preventDefault(); this.toggle( key ); this.focusKey( key ); }
				break;
			case 'F2':
				if ( this.editable && entry && 'removed' !== entry.status ) {
					e.preventDefault();
					this.startRename( current, current.querySelector( '.vgml-row' ), current.querySelector( '.vgml-name' ), entry );
				}
				break;
			case 'Delete':
			case 'Backspace':
				if ( this.editable && entry && 'removed' !== entry.status ) {
					e.preventDefault();
					this.onEdit( { type: 'remove', key: key, by: 'you' } );
				}
				break;
		}
	};

	TreeView.prototype.focusKey = function ( key ) {
		if ( ! this.listEl ) {
			return;
		}
		var rows = this.listEl.querySelectorAll( '.vgml-node[data-key]' );
		Array.prototype.forEach.call( rows, function ( r ) {
			var mine = r.getAttribute( 'data-key' ) === key;
			r.setAttribute( 'tabindex', mine ? '0' : '-1' );
			if ( mine ) {
				r.focus();
			}
		} );
	};

	/* --- painting the Folders surface --- */

	TreeView.prototype.buildHead = function () {
		var self = this;
		var l10n = this.l10n;
		var head = el( 'div', { class: 'vgml-tv-head' } );

		var sw = el( 'div', { class: 'vgml-tv-switch', role: 'group', 'aria-label': l10n.states } );
		[ 'changes', 'all' ].forEach( function ( mode ) {
			var b = el( 'button', { type: 'button', class: 'vgml-tv-state', 'data-mode': mode, 'aria-pressed': 'false' } );
			b.addEventListener( 'click', function () {
				self.setMode( mode, true );
			} );
			sw.appendChild( b );
		} );
		head.appendChild( sw );

		var find = el( 'div', { class: 'vgml-tv-find' } );
		var input = el( 'input', { type: 'search', class: 'vgml-tv-search', placeholder: l10n.find, 'aria-label': l10n.find } );
		input.addEventListener( 'input', function () {
			self.setFilter( input.value );
		} );
		find.appendChild( input );
		head.appendChild( find );

		this.switchEl = sw;
		this.findEl = find;
		this.findInput = input;
		return head;
	};

	TreeView.prototype.paintHead = function () {
		var l10n = this.l10n;
		var s = this.summary();
		var hasDraft = !! this.overlay;
		var self = this;

		this.switchEl.hidden = ! hasDraft;
		Array.prototype.forEach.call( this.switchEl.querySelectorAll( '.vgml-tv-state' ), function ( b ) {
			var mode = b.getAttribute( 'data-mode' );
			b.textContent = ( 'changes' === mode ? l10n.changes : l10n.all ) + ' ' + fmt( 'changes' === mode ? s.changes : s.now );
			b.setAttribute( 'aria-pressed', self.mode === mode ? 'true' : 'false' );
		} );

		this.findEl.hidden = this.model.nodes.length <= 20 && ! this.filter;
	};

	TreeView.prototype.render = function () {
		var self = this;
		if ( ! this.root ) {
			return;
		}
		if ( ! this.listEl ) {
			this.headEl = this.buildHead();
			this.root.appendChild( this.headEl );
			this.listEl = el( 'ul', { class: 'vgml-list', role: 'tree' } );
			this.listEl.addEventListener( 'keydown', function ( e ) { self.onKey( e ); } );
			if ( this.editable ) {
				this.listEl.addEventListener( 'dragover', function ( e ) {
					if ( ! self.dragKey || e.target !== self.listEl ) {
						return;
					}
					e.preventDefault();
					self.clearDrop();
					self.listEl.classList.add( 'is-drop' );
				} );
				this.listEl.addEventListener( 'drop', function ( e ) {
					if ( ! self.dragKey || e.target !== self.listEl ) {
						return;
					}
					e.preventDefault();
					var key = self.dragKey;
					self.dragKey = '';
					self.clearDrop();
					self.onEdit( { type: 'reparent', key: key, parent: '', by: 'you' } );
				} );
			}
			this.root.appendChild( this.listEl );
		}

		this.paintHead();

		var entries = this.entries();
		this.lastEntries = entries;
		var first = null === this.seen;
		var seen = this.seen || {};
		var next = {};
		var focused = document.activeElement && this.listEl.contains( document.activeElement )
			? document.activeElement.getAttribute( 'data-key' ) : null;

		this.listEl.innerHTML = '';
		this.listEl.setAttribute( 'data-mode', this.overlay ? this.mode : 'all' );

		entries.forEach( function ( entry ) {
			var li;
			if ( entry.path ) {
				li = el( 'li', { class: 'vgml-tv-path', role: 'presentation' }, entry.path );
			} else if ( entry.line ) {
				// The line under a removed or moved folder, not a folder itself.
				li = el( 'li', { class: 'vgml-tv-sub' + ( 'removed' === entry.status ? ' is-gone' : ' is-moved' ), role: 'none',
					style: '--vgml-indent:' + ( entry.depth * self.indent.step + self.indent.base ) + 'px' }, entry.line );
			} else if ( entry.fold ) {
				li = el( 'li', { class: 'vgml-node vgml-tv-more', role: 'treeitem', 'aria-level': entry.depth + 1,
					'aria-posinset': entry.posinset, 'aria-setsize': entry.setsize, tabindex: '-1', 'data-key': entry.key } );
				var more = el( 'div', { class: 'vgml-row', style: '--vgml-indent:' + ( entry.depth * self.indent.step + self.indent.base ) + 'px' } );
				more.appendChild( el( 'span', { class: 'vgml-twist is-leaf', 'aria-hidden': 'true' } ) );
				more.appendChild( el( 'span', { class: 'vgml-name' }, plural( self.l10n, 'more1', 'moreN', entry.fold.count ) ) );
				more.appendChild( el( 'span', { class: 'vgml-count' }, fmt( entry.fold.total ) ) );
				more.addEventListener( 'click', function () { self.unfold( entry.fold.parent ); } );
				li.appendChild( more );
			} else {
				li = self.folderRow( entry, {
					onToggle: function ( key ) { self.toggle( key ); }
				} ).item;
				if ( ! first && ! seen[ entry.key ] ) {
					li.classList.add( 'is-entering' );
				}
			}
			next[ entry.key ] = true;
			self.listEl.appendChild( li );
		} );

		if ( ! entries.length ) {
			this.listEl.appendChild( el( 'li', { class: 'vgml-empty', role: 'none' },
				this.filter ? this.l10n.nothingFound : this.l10n.noChanges ) );
		}

		this.seen = next;

		var rows = this.listEl.querySelectorAll( '.vgml-node[data-key]' );
		if ( rows.length ) {
			var target = null;
			if ( focused ) {
				Array.prototype.forEach.call( rows, function ( r ) {
					if ( r.getAttribute( 'data-key' ) === focused ) {
						target = r;
					}
				} );
			}
			( target || rows[ 0 ] ).setAttribute( 'tabindex', '0' );
			if ( target && focused ) {
				target.focus();
			}
		}
	};

	/* ------------------------------------------------------------- the poll */

	/*
	 *  The folders version stamp, watched.
	 *
	 *  One small GET every five seconds while the tab is visible, one more the
	 *  moment it becomes visible again, none while it is hidden. The first
	 *  answer is the baseline; every different answer after it is a change and
	 *  onChange is told. A surface that wrote to the tree itself records the
	 *  version the write returned with known(), so its own change does not
	 *  cost it a reload.
	 */
	function watchVersion( opts ) {
		opts = opts || {};
		var path = opts.path || '/vergeml/v1/folders/version';
		var every = opts.every || 5000;
		var known = typeof opts.version === 'number' ? opts.version : null;
		var inflight = false;
		var stopped = false;
		var timer = null;

		function fetchVersion() {
			if ( opts.fetch ) {
				return opts.fetch();
			}
			if ( ! window.wp || ! window.wp.apiFetch ) {
				return Promise.reject( new Error( 'no apiFetch' ) );
			}
			return window.wp.apiFetch( { path: path } ).then( function ( r ) {
				return r && typeof r.version === 'number' ? r.version : null;
			} );
		}

		function tick() {
			if ( stopped || inflight || document.hidden ) {
				return;
			}
			inflight = true;
			fetchVersion().then( function ( v ) {
				inflight = false;
				if ( null === v || undefined === v ) {
					return;
				}
				if ( null === known ) {
					known = v;
					return;
				}
				if ( v !== known ) {
					known = v;
					if ( opts.onChange ) {
						opts.onChange( v );
					}
				}
			}, function () {
				inflight = false;
			} );
		}

		function onVisible() {
			if ( ! document.hidden ) {
				tick();
			}
		}

		document.addEventListener( 'visibilitychange', onVisible );
		timer = window.setInterval( tick, every );
		tick();

		return {
			known: function ( v ) {
				if ( typeof v === 'number' ) {
					known = v;
				}
			},
			current: function () {
				return known;
			},
			tick: tick,
			stop: function () {
				stopped = true;
				window.clearInterval( timer );
				document.removeEventListener( 'visibilitychange', onVisible );
			}
		};
	}

	/* --------------------------------------------------------------- export */

	window.vergemlTreeView = {
		version: 1,
		create: function ( opts ) { return new TreeView( opts ); },
		Model: Model,
		fromLive: fromLive,
		applyEdit: applyEdit,
		rebase: rebase,
		overlay: overlay,
		normaliseDraft: normaliseDraft,
		chevron: chevron,
		folderIcon: folderIcon,
		watchVersion: watchVersion,
		el: el,
		sprintf: sprintf,
		fmt: fmt
	};
} )();
