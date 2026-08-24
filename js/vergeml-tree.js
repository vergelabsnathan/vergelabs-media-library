/*
 *  The folder tree.
 *
 *  Plain JavaScript on purpose. FileBird ships 1.6MB of React for this and the
 *  whole argument of this plugin is that it does not need to.
 *
 *  Two rules govern everything below.
 *
 *  Attach, never replace. The tree drives the media library through the filter
 *  props the library already has -- library.props.set( taxonomy, id ) -- which
 *  is the same path the dropdown filter used. Nothing here overrides a core
 *  view. Premio's Folders replaces AttachmentsBrowser outright and that is why
 *  it breaks whenever WordPress moves.
 *
 *  ARIA from the first line, not later. A tree is one of the few widgets with a
 *  genuinely prescribed keyboard contract, and retrofitting roles and focus
 *  management onto a working mouse implementation costs more than building with
 *  them.
 */

( function () {
	'use strict';

	if ( typeof window.vergemlTree === 'undefined' ) {
		return;
	}

	var cfg = window.vergemlTree;
	var l10n = cfg.l10n;
	var apiFetch = window.wp && window.wp.apiFetch;

	if ( ! apiFetch ) {
		return;
	}

	var state = {
		taxonomy: cfg.taxonomies[ 0 ] ? cfg.taxonomies[ 0 ].name : '',
		nodes: [],
		byId: {},
		children: {},
		unassigned: 0,
		open: {},
		selected: 0,
		filter: '',
		skin: ( cfg.state && cfg.state.skin ) || 'native',
		density: ( cfg.state && cfg.state.density ) || 'comfortable',
		width: ( cfg.state && cfg.state.width ) || 240,
		lastUndo: null
	};

	( ( cfg.state && cfg.state.open ) || [] ).forEach( function ( id ) {
		state.open[ id ] = true;
	} );
	state.selected = ( cfg.state && cfg.state.selected ) || 0;

	var root = null;
	var listEl = null;
	var searchEl = null;
	var toastEl = null;

	/* ---------------------------------------------------------------- utils */

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( k ) {
				if ( k === 'class' ) {
					node.className = attrs[ k ];
				} else if ( attrs[ k ] !== null && attrs[ k ] !== undefined ) {
					node.setAttribute( k, attrs[ k ] );
				}
			} );
		}
		if ( text !== undefined && text !== null ) {
			node.appendChild( document.createTextNode( text ) );
		}
		return node;
	}

	function sprintf( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s ).replace( /%(\d+\$)?[sd]/g, function ( m, pos ) {
			return pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ];
		} );
	}

	/* ------------------------------------------------------------- the data */

	function index() {
		state.byId = {};
		state.children = {};
		state.nodes.forEach( function ( n ) {
			state.byId[ n.id ] = n;
			( state.children[ n.parent ] = state.children[ n.parent ] || [] ).push( n );
		} );
		Object.keys( state.children ).forEach( function ( p ) {
			state.children[ p ].sort( function ( a, b ) {
				return a.order - b.order || a.name.localeCompare( b.name );
			} );
		} );
	}

	function load() {
		return apiFetch( {
			path: '/vergeml/v1/tree?taxonomy=' + encodeURIComponent( state.taxonomy )
		} ).then( function ( data ) {
			state.nodes = data.nodes || [];
			state.unassigned = data.unassigned || 0;
			if ( data.state ) {
				state.skin = data.state.skin || state.skin;
				state.density = data.state.density || state.density;
			}
			index();
			render();
		} );
	}

	function persist( patch ) {
		// Fire and forget: a preference failing to save is not worth interrupting
		// somebody's work over, and the next change will try again.
		patch.taxonomy = state.taxonomy;
		apiFetch( { path: '/vergeml/v1/state', method: 'POST', data: patch } ).catch( function () {} );
	}

	/*
	 *  Descendant-inclusive totals, in one pass.
	 *
	 *  Clicking a folder shows everything beneath it, so a folder reading 0 that
	 *  opens full of files is a bug report. Computed bottom-up over the child map
	 *  rather than by asking each node to walk its own subtree, which is what
	 *  FileBird does and is why theirs is quadratic on a deep tree.
	 */
	function totals() {
		var out = {};
		var order = [];

		function walk( id, depth ) {
			( state.children[ id ] || [] ).forEach( function ( n ) {
				order.push( n );
				walk( n.id, depth + 1 );
			} );
		}
		walk( 0, 0 );

		for ( var i = order.length - 1; i >= 0; i-- ) {
			var n = order[ i ];
			var sum = n.count;
			( state.children[ n.id ] || [] ).forEach( function ( c ) {
				sum += out[ c.id ];
			} );
			out[ n.id ] = sum;
		}
		return out;
	}

	/* ------------------------------------------------------------ filtering */

	function matches( node ) {
		if ( ! state.filter ) {
			return true;
		}
		return node.name.toLowerCase().indexOf( state.filter ) !== -1;
	}

	// A folder stays visible when it matches, or when anything beneath it does --
	// otherwise searching for a leaf hides the path that leads to it.
	function visible( node ) {
		if ( matches( node ) ) {
			return true;
		}
		var kids = state.children[ node.id ] || [];
		for ( var i = 0; i < kids.length; i++ ) {
			if ( visible( kids[ i ] ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------- the view */

	/*
	 *  Flatten first, then draw only the window that is on screen.
	 *
	 *  The tree used to be nested <ul>s, which is the obvious shape and the wrong
	 *  one: two thousand folders meant 12,764 DOM nodes and 103ms on every toggle,
	 *  every search keystroke and every skin change -- to paint 54,660px of rows
	 *  into a 630px panel. Ninety-seven per cent of that work was never seen.
	 *
	 *  A flat list carrying aria-level is an equally valid tree to a screen reader,
	 *  and it makes windowing arithmetic: rows are a uniform height, so the slice in
	 *  view can be calculated. Two spacer rows keep the scrollbar honest.
	 *
	 *  Below the threshold everything is drawn. Windowing costs a scroll handler and
	 *  a repaint on scroll, and a fifty-folder library should not pay for a
	 *  two-thousand-folder problem.
	 */
	var VIRTUALISE_ABOVE = 500;
	var OVERSCAN = 8;

	// Uniform, but the height depends on skin and density -- so it is read from the
	// element rather than assumed.
	function rowHeight() {
		var v = parseInt( getComputedStyle( root ).getPropertyValue( '--vgml-row' ), 10 );
		return v > 0 ? v : 30;
	}

	function flatten() {
		var out = [];
		var total = totals();

		out.push( { pseudo: 'all', label: l10n.all, count: null, depth: 0, id: 0 } );
		out.push( { pseudo: 'unassigned', label: l10n.unassigned, count: state.unassigned, depth: 0, id: -1 } );

		( function walk( parentId, depth ) {
			var siblings = ( state.children[ parentId ] || [] ).filter( visible );

			siblings.forEach( function ( node, i ) {
				var kids = ( state.children[ node.id ] || [] ).filter( visible );
				// While searching, every branch on the way to a match is open.
				var open = state.filter ? true : !! state.open[ node.id ];

				out.push( {
					node: node,
					depth: depth,
					id: node.id,
					total: total[ node.id ],
					kids: kids.length,
					open: open,
					posinset: i + 1,
					setsize: siblings.length
				} );

				if ( kids.length && open ) {
					walk( node.id, depth + 1 );
				}
			} );
		} )( 0, 0 );

		// The row being typed into sits where the folder will end up, so the name
		// is entered in the position it will occupy rather than in a dialog with no
		// relationship to the tree.
		if ( creatingUnder >= 0 ) {
			var at = out.length;
			var depth = 0;

			if ( creatingUnder > 0 ) {
				for ( var k = 0; k < out.length; k++ ) {
					if ( out[ k ].id === creatingUnder ) {
						depth = out[ k ].depth + 1;
						at = k + 1;
						break;
					}
				}
			}

			out.splice( at, 0, { creating: true, depth: depth, id: -2 } );
		}

		return out;
	}

	var flat = [];
	var windowed = false;

	function render() {
		if ( ! listEl ) {
			return;
		}

		flat = flatten();
		windowed = flat.length > VIRTUALISE_ABOVE;

		root.setAttribute( 'data-skin', state.skin );
		root.setAttribute( 'data-density', state.density );

		paint();
	}

	/*
	 *  Painting keeps the rows that are already right.
	 *
	 *  The first version emptied the list and rebuilt it on every scroll frame,
	 *  which is why the tree flickered: every row was destroyed and recreated
	 *  sixty times a second, so anything under the pointer blinked, hover was lost
	 *  and the whole panel visibly churned. Correct output, unusable to look at.
	 *
	 *  Now only the difference is applied -- rows leaving the window are removed,
	 *  rows entering are inserted, and everything in between is left alone. And
	 *  when scrolling has not moved the window at all, nothing happens.
	 */
	var painted = { first: -1, last: -1, key: '' };

	function windowRange() {
		var h = rowHeight();

		if ( ! windowed ) {
			return { first: 0, last: flat.length, h: h };
		}

		var top = listEl.scrollTop;
		var view = listEl.clientHeight || 400;

		return {
			first: Math.max( 0, Math.floor( top / h ) - OVERSCAN ),
			last: Math.min( flat.length, Math.ceil( ( top + view ) / h ) + OVERSCAN ),
			h: h
		};
	}

	function paint( force ) {
		var r = windowRange();

		// A key that changes whenever the rows themselves would look different,
		// so a scroll that reveals nothing new costs nothing.
		var key = state.skin + '|' + state.density + '|' + state.selected + '|' + flat.length + '|' + state.filter;

		if ( ! force && r.first === painted.first && r.last === painted.last && key === painted.key ) {
			return;
		}

		if ( force || key !== painted.key ) {
			listEl.innerHTML = '';
			painted.first = -1;
			painted.last = -1;
		}

		painted.key = key;

		if ( painted.first === -1 ) {
			fillWindow( r );
			return;
		}

		trim( r );
		grow( r );

		painted.first = r.first;
		painted.last = r.last;
	}

	function rowFor( i ) {
		var entry = flat[ i ];

		// The row being renamed becomes the input, in place.
		if ( ! entry.pseudo && editing === entry.id ) {
			var editRow = editorRow( entry.depth, entry.node.name, function ( name ) {
				var id = entry.id;
				editing = 0;
				if ( name && name !== entry.node.name ) {
					folder( { action: 'rename', id: id, name: name } );
				} else {
					paint( true );
				}
			} );
			editRow.setAttribute( 'data-i', i );
			return editRow;
		}

		if ( entry.creating ) {
			var newRow = editorRow( entry.depth, '', function ( name ) {
				var parent = creatingUnder;
				creatingUnder = -1;
				if ( name ) {
					folder( { action: 'create', name: name, parent: parent } );
				} else {
					render();
				}
			} );
			newRow.setAttribute( 'data-i', i );
			return newRow;
		}

		var node = entry.pseudo ? pseudoRow( entry ) : nodeRow( entry );
		node.setAttribute( 'data-i', i );
		return node;
	}

	/*
	 *  Named for what it does, and named differently from the shell builder --
	 *  both were called build(), function declarations hoist, and the later one
	 *  silently replaced this. Painting then called the shell builder, which
	 *  returns a fresh panel and appends nothing, so the tree drew zero rows with
	 *  no error anywhere.
	 */
	function fillWindow( r ) {
		listEl.innerHTML = '';

		listEl.appendChild( topSpacer( r.first * r.h ) );

		for ( var i = r.first; i < r.last; i++ ) {
			listEl.appendChild( rowFor( i ) );
		}

		listEl.appendChild( bottomSpacer( ( flat.length - r.last ) * r.h ) );

		if ( state.filter && flat.length <= 2 ) {
			listEl.appendChild( el( 'li', { class: 'vgml-empty', role: 'none' }, l10n.nothingFound ) );
		}

		painted.first = r.first;
		painted.last = r.last;
	}

	// Drop the rows that have left the window, from whichever end they left.
	function trim( r ) {
		var i;

		for ( i = painted.first; i < Math.min( r.first, painted.last ); i++ ) {
			var goneTop = listEl.querySelector( '[data-i="' + i + '"]' );
			if ( goneTop ) { listEl.removeChild( goneTop ); }
		}

		for ( i = Math.max( r.last, painted.first ); i < painted.last; i++ ) {
			var goneBottom = listEl.querySelector( '[data-i="' + i + '"]' );
			if ( goneBottom ) { listEl.removeChild( goneBottom ); }
		}
	}

	// Add the rows that have entered it, and resize the spacers to match.
	function grow( r ) {
		var top = listEl.querySelector( '.vgml-spacer-top' );
		var bottom = listEl.querySelector( '.vgml-spacer-bottom' );
		var i;

		for ( i = Math.min( painted.first - 1, r.last - 1 ); i >= r.first; i-- ) {
			if ( ! listEl.querySelector( '[data-i="' + i + '"]' ) ) {
				listEl.insertBefore( rowFor( i ), top.nextSibling );
			}
		}

		for ( i = Math.max( painted.last, r.first ); i < r.last; i++ ) {
			if ( ! listEl.querySelector( '[data-i="' + i + '"]' ) ) {
				listEl.insertBefore( rowFor( i ), bottom );
			}
		}

		top.style.height = ( r.first * r.h ) + 'px';
		bottom.style.height = ( ( flat.length - r.last ) * r.h ) + 'px';
	}

	// Two named spacers, always present, so their heights can be adjusted in place
	// rather than the pair being rebuilt with the rows.
	function topSpacer( px ) {
		return el( 'li', { class: 'vgml-spacer vgml-spacer-top', role: 'none', 'aria-hidden': 'true', style: 'height:' + px + 'px' } );
	}

	function bottomSpacer( px ) {
		return el( 'li', { class: 'vgml-spacer vgml-spacer-bottom', role: 'none', 'aria-hidden': 'true', style: 'height:' + px + 'px' } );
	}

	function nodeRow( entry ) {
		var node = entry.node;

		var item = el( 'li', {
			class: 'vgml-node' + ( state.selected === node.id ? ' is-selected' : '' ),
			role: 'treeitem',
			'aria-level': entry.depth + 1,
			'aria-posinset': entry.posinset,
			'aria-setsize': entry.setsize,
			'aria-selected': state.selected === node.id ? 'true' : 'false',
			'data-id': node.id,
			tabindex: '-1'
		} );

		if ( entry.kids ) {
			item.setAttribute( 'aria-expanded', entry.open ? 'true' : 'false' );
		}

		var row = el( 'div', { class: 'vgml-row', style: '--vgml-indent:' + ( entry.depth * 14 + 8 ) + 'px' } );

		var twist = el( 'button', {
			class: 'vgml-twist' + ( entry.kids ? '' : ' is-leaf' ),
			type: 'button',
			tabindex: '-1',
			'aria-hidden': 'true'
		} );
		twist.innerHTML = entry.kids ? ( entry.open ? '&#9662;' : '&#9656;' ) : '';
		twist.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			toggle( node.id );
		} );
		row.appendChild( twist );

		// `total` is descendant-inclusive, so a parent whose own count is zero but
		// whose children hold files still reads as full -- which is what the user sees
		// when they click it.
		row.appendChild( folderIcon( node.color, entry.open && entry.kids, ! entry.total ) );

		row.appendChild( el( 'span', { class: 'vgml-name' }, node.name ) );

		// A pill, not a bare number: it reads as a badge belonging to the row
		// rather than as a second column of text competing with the name.
		if ( entry.total ) {
			row.appendChild( el( 'span', { class: 'vgml-count' }, String( entry.total ) ) );
		}

		row.addEventListener( 'click', function () {
			if ( justDragged ) {
				return;
			}
			select( node.id );
			selectForEditing( node.id );
		} );

		// Renaming where the name is: the standard gesture, and it means the
		// toolbar button is a discoverable second route rather than the only one.
		row.addEventListener( 'dblclick', function ( e ) {
			e.preventDefault();
			if ( cfg.canManage ) { rename( node ); }
		} );

		dropTarget( row, node.id );

		if ( cfg.canManage ) {
			dragSource( row, node.id );
		}

		item.appendChild( row );
		return item;
	}

	function pseudoRow( entry ) {
		var key = entry.pseudo;
		var selected = ( key === 'all' && ! state.selected ) || ( key === 'unassigned' && state.selected === -1 );

		var item = el( 'li', {
			class: 'vgml-node vgml-pseudo' + ( selected ? ' is-selected' : '' ),
			role: 'treeitem',
			'aria-level': '1',
			'aria-selected': selected ? 'true' : 'false',
			'data-id': key === 'all' ? '0' : '-1',
			tabindex: '-1'
		} );

		var row = el( 'div', { class: 'vgml-row' } );
		row.appendChild( el( 'span', { class: 'vgml-twist is-leaf', 'aria-hidden': 'true' } ) );

		var mark = el( 'span', { class: 'vgml-icon is-pseudo', 'aria-hidden': 'true' } );
		mark.innerHTML = key === 'all'
			? '<svg viewBox="0 0 16 16" width="15" height="15"><path d="M2 3h12v2H2zM2 7h12v2H2zM2 11h12v2H2z"/></svg>'
			: '<svg viewBox="0 0 16 16" width="15" height="15"><path d="M8 1.5 14.5 14h-13L8 1.5Zm0 4.2-3 5.8h6l-3-5.8Z"/></svg>';
		row.appendChild( mark );

		row.appendChild( el( 'span', { class: 'vgml-name' }, entry.label ) );

		if ( entry.count !== null && entry.count ) {
			row.appendChild( el( 'span', { class: 'vgml-count' }, String( entry.count ) ) );
		}

		row.addEventListener( 'click', function () {
			if ( justDragged ) {
				return;
			}
			select( key === 'all' ? 0 : -1 );
			selectForEditing( 0 );
		} );

		// Dropping onto "Unfiled" takes a file out of every folder -- the only way
		// to unfile something by dragging.
		if ( key === 'unassigned' ) {
			unfileTarget( row );
		}

		item.appendChild( row );
		return item;
	}

	function toggle( id ) {
		state.open[ id ] = ! state.open[ id ];
		render();
		persist( { open: Object.keys( state.open ).filter( function ( k ) { return state.open[ k ]; } ).map( Number ) } );
	}

	/* --------------------------------------------------------- the library */

	/*
	 *  Where the library's filter props actually live.
	 *
	 *  Not one place, which is the point of looking rather than assuming. This
	 *  plugin's own grid view stores its frame as wp.media.frames.browse and does
	 *  NOT set wp.media.frame, so code that reaches for wp.media.frame finds
	 *  nothing on exactly the screen the tree is built for. Core's modal does set
	 *  it. Both are checked, and a frame that keeps its library on the state
	 *  rather than on itself is handled too.
	 *
	 *  Returns null on the list screen, where there is no JS library at all and
	 *  filtering is a page load.
	 */
	function libraryProps() {

		if ( ! window.wp || ! wp.media ) {
			return null;
		}

		var frames = [];

		if ( wp.media.frames ) {
			if ( wp.media.frames.browse ) { frames.push( wp.media.frames.browse ); }
			if ( wp.media.frames.edit ) { frames.push( wp.media.frames.edit ); }
		}
		if ( wp.media.frame ) { frames.push( wp.media.frame ); }

		for ( var i = 0; i < frames.length; i++ ) {

			var f = frames[ i ];

			if ( f && f.library && f.library.props ) {
				return f.library.props;
			}

			if ( f && typeof f.state === 'function' ) {
				var s = f.state();
				var lib = s && typeof s.get === 'function' ? s.get( 'library' ) : null;
				if ( lib && lib.props ) {
					return lib.props;
				}
			}
		}

		return null;
	}

	/*
	 *  Filtering goes through the props the media library already uses. The
	 *  dropdown set exactly these; the tree sets the same ones. There is one
	 *  query path and the tree is not it.
	 */
	function select( id ) {
		state.selected = id;
		render();
		persist( { selected: id > 0 ? id : 0 } );

		var props = {};

		if ( id === -1 ) {
			props[ state.taxonomy ] = null;
			props.uncategorized = 1;
		} else if ( id === 0 ) {
			props[ state.taxonomy ] = null;
			props.uncategorized = null;
		} else {
			props[ state.taxonomy ] = id;
			props.uncategorized = null;
		}

		var lib = libraryProps();

		if ( lib ) {
			lib.set( props );
			return;
		}

		// List view has no JS library to talk to, so it reloads with the filter
		// in the query string -- the same one the dropdown produced.
		var url = new URL( window.location.href );
		if ( id > 0 ) {
			url.searchParams.set( state.taxonomy, id );
			url.searchParams.delete( 'uncategorized' );
		} else if ( id === -1 ) {
			url.searchParams.delete( state.taxonomy );
			url.searchParams.set( 'uncategorized', '1' );
		} else {
			url.searchParams.delete( state.taxonomy );
			url.searchParams.delete( 'uncategorized' );
		}
		if ( url.href !== window.location.href ) {
			window.location.href = url.href;
		}
	}

	/* ------------------------------------------------------------- keyboard */

	function rows() {
		return Array.prototype.slice.call( listEl.querySelectorAll( '[role="treeitem"]' ) );
	}

	function focusRow( item ) {
		rows().forEach( function ( r ) { r.setAttribute( 'tabindex', '-1' ); } );
		item.setAttribute( 'tabindex', '0' );
		item.focus();
	}

	/*
	 *  Navigation walks the model, not the DOM.
	 *
	 *  With windowing on, the page holds about forty rows out of two thousand, so
	 *  "the next element" runs out at the edge of the window and the arrow keys
	 *  stop. The flattened list is the real order; restoreFocus scrolls whatever it
	 *  lands on into existence.
	 */
	function indexOfId( id ) {
		for ( var i = 0; i < flat.length; i++ ) {
			if ( flat[ i ].id === id ) { return i; }
		}
		return -1;
	}

	function onKey( e ) {
		var current = document.activeElement;

		if ( ! current || ! current.getAttribute || current.getAttribute( 'role' ) !== 'treeitem' ) {
			return;
		}

		var id = parseInt( current.getAttribute( 'data-id' ), 10 );
		var i = indexOfId( id );

		if ( i === -1 ) {
			return;
		}

		switch ( e.key ) {
			case 'ArrowDown':
				e.preventDefault();
				if ( flat[ i + 1 ] ) { restoreFocus( flat[ i + 1 ].id ); }
				break;
			case 'ArrowUp':
				e.preventDefault();
				if ( flat[ i - 1 ] ) { restoreFocus( flat[ i - 1 ].id ); }
				break;
			case 'ArrowRight':
				e.preventDefault();
				if ( current.getAttribute( 'aria-expanded' ) === 'false' ) {
					toggle( id );
					restoreFocus( id );
				} else if ( flat[ i + 1 ] ) {
					restoreFocus( flat[ i + 1 ].id );
				}
				break;
			case 'ArrowLeft':
				e.preventDefault();
				if ( current.getAttribute( 'aria-expanded' ) === 'true' ) {
					toggle( id );
					restoreFocus( id );
				} else {
					var node = state.byId[ id ];
					if ( node && node.parent ) { restoreFocus( node.parent ); }
				}
				break;
			case 'Home':
				e.preventDefault();
				if ( flat[ 0 ] ) { restoreFocus( flat[ 0 ].id ); }
				break;
			case 'End':
				e.preventDefault();
				if ( flat.length ) { restoreFocus( flat[ flat.length - 1 ].id ); }
				break;
			case 'Enter':
			case ' ':
				e.preventDefault();
				select( id );
				restoreFocus( id );
				break;
			case 'ContextMenu':
				e.preventDefault();
				if ( cfg.canManage && state.byId[ id ] ) { menu( state.byId[ id ] ); }
				break;
			case 'F2':
				e.preventDefault();
				if ( cfg.canManage && state.byId[ id ] ) { rename( state.byId[ id ] ); }
				break;
			default:
				break;
		}
	}

	/*
	 *  Focus is restored by id, never by holding on to an element: render()
	 *  rebuilds the list, and once windowing is on the row may not be in the page
	 *  at all -- a folder eight hundred rows down exists in the model and nowhere
	 *  in the DOM. So the list is scrolled to where that row belongs, repainted,
	 *  and only then focused. Without this, keyboard navigation stops dead at the
	 *  edge of the window, which is the failure that makes people stop trusting
	 *  the keyboard.
	 */
	function restoreFocus( id ) {

		var next = listEl.querySelector( '[data-id="' + id + '"]' );

		if ( ! next && windowed ) {

			var at = -1;
			for ( var i = 0; i < flat.length; i++ ) {
				if ( flat[ i ].id === id ) { at = i; break; }
			}

			if ( at !== -1 ) {
				var h = rowHeight();
				listEl.scrollTop = Math.max( 0, ( at * h ) - ( listEl.clientHeight / 2 ) );
				paint();
				next = listEl.querySelector( '[data-id="' + id + '"]' );
			}
		}

		if ( next ) {
			focusRow( next );
		}
	}

	/* ------------------------------------------------------------ dragging */

	var dragging = null;

	function selectionIds() {
		// Same reason as libraryProps: the selection lives on whichever frame this
		// screen actually built, and on the grid that is not wp.media.frame.
		var frames = [];
		if ( window.wp && wp.media ) {
			if ( wp.media.frames && wp.media.frames.browse ) { frames.push( wp.media.frames.browse ); }
			if ( wp.media.frame ) { frames.push( wp.media.frame ); }
		}
		for ( var i = 0; i < frames.length; i++ ) {
			var f = frames[ i ];
			if ( f && typeof f.state === 'function' ) {
				var sel = f.state().get( 'selection' );
				if ( sel && sel.length ) {
					return sel.map( function ( m ) { return m.id; } );
				}
			}
		}
		var checked = document.querySelectorAll( '#the-list input[name="media[]"]:checked' );
		return Array.prototype.slice.call( checked ).map( function ( c ) { return parseInt( c.value, 10 ); } );
	}

	/*
	 *  Which folder is being dragged, if any.
	 *
	 *  Kept here rather than read from the DataTransfer, because a drop handler
	 *  cannot read custom data during dragover -- only on drop -- and the whole
	 *  point of knowing early is to refuse an illegal target before the user lets
	 *  go. The DataTransfer still carries it, so a drop that arrives without this
	 *  (another window, a reload mid-drag) is treated as a file drag rather than
	 *  silently reparenting the wrong thing.
	 */
	var draggingFolder = 0;

	/*
	 *  A drag ends with a click, and the click must not also select.
	 *
	 *  jQuery UI does not swallow the click that follows a drag, so releasing over
	 *  a folder both dropped onto it and selected it -- and in list view selecting
	 *  navigates, so every folder drag reloaded the page. The tree looked like it
	 *  had emptied itself.
	 */
	var justDragged = 0;

	/*
	 *  Raised when a drag STARTS, not when it stops.
	 *
	 *  revert: 'invalid' defers jQuery UI's stop callback until after the revert
	 *  animation, so a flag set there is set ~150ms too late -- the click has
	 *  already fired and the folder has already been selected. In list view that
	 *  selection is a page load, so every folder drag reloaded the page and the
	 *  tree appeared to wipe itself.
	 */
	function dragBegan() {
		justDragged = 1;
	}

	function dragEnded() {
		// Cleared a beat after the click that follows mouseup would have fired.
		window.setTimeout( function () { justDragged = 0; }, 300 );
	}

	/*
	 *  Files are dragged with jQuery UI, not the HTML5 drag API.
	 *
	 *  The first version used HTML5 dragstart/dragover/drop. It worked and it felt
	 *  wrong, and the reason is the mechanism rather than the polish: the browser
	 *  owns the drag image and will not let go of it, there is no distance
	 *  threshold so a slightly-moved click becomes a drag, dropEffect behaves
	 *  differently per browser, and none of it works on touch.
	 *
	 *  jQuery UI is plain pointer events with a helper element we own -- which is
	 *  what makes a proper floating tile with a count possible -- plus a distance
	 *  threshold, a hover class on the target, and revert on a missed drop. Both
	 *  it and jQuery ship with WordPress and core's media grid already uses them,
	 *  so nothing is added to the page.
	 */
	function wireFileDragging() {

		var $ = window.jQuery;

		if ( ! $ || ! $.fn || ! $.fn.draggable ) {
			return; // No jQuery UI: the tree still filters, it just cannot be dragged onto.
		}

		/*
		 *  Delegated, because both screens replace their contents as the library
		 *  loads and paginates -- anything bound to the items themselves is bound
		 *  to elements that will shortly not exist. `.draggable()` is applied the
		 *  first time an item is touched and then left alone.
		 */
		$( document ).on( 'mouseenter', '.attachment, #the-list tr', function () {

			var $item = $( this );

			if ( $item.data( 'vgml-drag' ) ) {
				return;
			}
			$item.data( 'vgml-drag', true );

			$item.draggable( {
				addClasses: false,
				appendTo: 'body',
				cursorAt: { top: 12, left: 12 },
				// 6px before a drag begins, so clicking a file stays a click.
				distance: 6,
				revert: 'invalid',
				revertDuration: 150,
				scroll: false,
				zIndex: 100000,
				helper: function () {
					var ids = idsForDrag( fileId( this ) );
					dragging = ids;
					return $( '<div class="vgml-drag-helper"></div>' )
						.attr( 'data-count', ids.length )
						.text( ids.length === 1 ? l10n.oneFile : sprintf( l10n.manyFiles, ids.length ) );
				},
				start: function () {
					document.body.classList.add( 'vgml-dragging-files' );
				},
				stop: function () {
					document.body.classList.remove( 'vgml-dragging-files' );
					dragging = null;
				}
			} );
		} );
	}

	/*
	 *  Dragging a file that is part of the selection moves the whole selection.
	 *  Dragging one that is not moves only it, and clears the selection -- picking
	 *  up something you did not select means you changed your mind about what you
	 *  were acting on.
	 */
	function idsForDrag( id ) {

		if ( ! id ) {
			return [];
		}

		var selected = selectionIds();

		if ( selected.indexOf( id ) !== -1 ) {
			return selected;
		}

		clearLibrarySelection();
		return [ id ];
	}

	function fileElement( target ) {
		if ( ! target || ! target.closest ) {
			return null;
		}
		// Grid: an attachment tile. List: a row in the media table.
		return target.closest( '.attachment' ) || target.closest( '#the-list tr' );
	}

	function fileId( item ) {
		if ( item.getAttribute( 'data-id' ) ) {
			return parseInt( item.getAttribute( 'data-id' ), 10 );
		}
		// List rows are id="post-123"; fall back to the row's own checkbox.
		var fromId = ( item.id || '' ).match( /post-(\d+)/ );
		if ( fromId ) {
			return parseInt( fromId[ 1 ], 10 );
		}
		var box = item.querySelector( 'input[name="media[]"]' );
		return box ? parseInt( box.value, 10 ) : 0;
	}

	function clearLibrarySelection() {
		var props = null;
		if ( window.wp && wp.media && wp.media.frames && wp.media.frames.browse ) {
			try {
				props = wp.media.frames.browse.state().get( 'selection' );
			} catch ( err ) { props = null; }
		}
		if ( props && props.reset ) {
			props.reset();
		}
		Array.prototype.forEach.call(
			document.querySelectorAll( '#the-list input[name="media[]"]:checked' ),
			function ( c ) { c.checked = false; }
		);
	}

	/*
	 *  "Unfiled" as a drop target.
	 *
	 *  Dragging a file there empties its folders, which is the only way to unfile
	 *  something by dragging -- otherwise the only route out of a folder is to
	 *  open the file and clear the terms by hand.
	 */
	function unfileTarget( row ) {

		var $ = window.jQuery;

		if ( ! $ || ! $.fn || ! $.fn.droppable ) {
			return;
		}

		/*
		 *  Left on the HTML5 API when everything else moved to jQuery UI, so it
		 *  silently did nothing -- the one route out of a folder by dragging, and
		 *  it was dead. Nothing pointed at it because no test covered it.
		 */
		$( row ).droppable( {
			addClasses: false,
			tolerance: 'pointer',
			hoverClass: 'is-drop',
			drop: function () {

				if ( draggingFolder ) {
					return; // folders are not unfiled; they are deleted or moved
				}

				var ids = ( dragging && dragging.length ) ? dragging : selectionIds();

				if ( ! ids.length ) {
					return;
				}

				// 'move' with nothing to add empties the taxonomy for those files.
				assign( ids, null, true );
				dragging = null;
			}
		} );
	}

	function dropTarget( row, termId ) {

		var $ = window.jQuery;

		if ( ! $ || ! $.fn || ! $.fn.droppable ) {
			return;
		}

		$( row ).droppable( {
			addClasses: false,
			tolerance: 'pointer',
			hoverClass: 'is-drop',
			over: function ( e ) {
				if ( draggingFolder && ! canReparent( draggingFolder, termId ) ) {
					row.classList.remove( 'is-drop' );
					row.classList.add( 'is-refused' );
				}
			},
			out: function () {
				row.classList.remove( 'is-refused' );
			},
			drop: function ( e, ui ) {

				row.classList.remove( 'is-drop' );
				row.classList.remove( 'is-refused' );

				// A folder being dragged onto another folder: re-parent it.
				if ( draggingFolder ) {
					var moving = draggingFolder;
					draggingFolder = 0;
					if ( canReparent( moving, termId ) ) {
						folderMove( moving, termId );
					}
					return;
				}

				var ids = ( dragging && dragging.length ) ? dragging : selectionIds();

				if ( ! ids.length ) {
					return;
				}

				// Ctrl or Alt turns an add into a move. Read from the original event,
				// because jQuery UI hands us the mouse event that ended the drag.
				var move = !! ( e && ( e.ctrlKey || e.altKey ) );

				assign( ids, termId, move );
				dragging = null;
			}
		} );
	}

	/*
	 *  A folder may not be dropped into itself or into anything below it.
	 *
	 *  The endpoint refuses this too, and that is the check that matters -- but
	 *  refusing it here as well means the user never gets to make the gesture. The
	 *  consequence of letting it through is a branch detached from the tree with
	 *  no screen that shows it, so it is worth saying no twice.
	 */
	function canReparent( id, parent ) {

		if ( ! id || id === parent ) {
			return false;
		}

		var walk = parent;
		var guard = 0;

		while ( walk && guard++ < 1000 ) {
			if ( walk === id ) {
				return false;
			}
			var node = state.byId[ walk ];
			walk = node ? node.parent : 0;
		}

		return true;
	}

	function folderMove( id, parent ) {
		folder( { action: 'move', id: id, parent: parent } );
	}

	/*
	 *  Folder rows are drag sources too, so the tree can be rearranged by hand.
	 *  Same mechanism as the files, so both drags feel identical rather than one
	 *  behaving like the browser's and one like ours.
	 */
	function dragSource( row, termId ) {

		var $ = window.jQuery;

		if ( ! $ || ! $.fn || ! $.fn.draggable ) {
			return;
		}

		$( row ).draggable( {
			addClasses: false,
			appendTo: 'body',
			cursorAt: { top: 12, left: 12 },
			distance: 6,
			revert: 'invalid',
			revertDuration: 150,
			scroll: false,
			zIndex: 100000,
			helper: function () {
				draggingFolder = termId;
				var node = state.byId[ termId ];
				return $( '<div class="vgml-drag-helper is-folder"></div>' )
					.text( node ? node.name : '' );
			},
			start: function () {
				row.classList.add( 'is-dragging' );
				document.body.classList.add( 'vgml-dragging-folder' );
				dragBegan();
			},
			stop: function () {
				row.classList.remove( 'is-dragging' );
				document.body.classList.remove( 'vgml-dragging-folder' );
				draggingFolder = 0;
				dragEnded();
			}
		} );
	}

	function assign( ids, termId, move ) {
		apiFetch( {
			path: '/vergeml/v1/assign',
			method: 'POST',
			data: {
				taxonomy: state.taxonomy,
				attachments: ids,
				// A null term means "no folder", which with mode 'move' is unfiling.
				add: termId ? [ termId ] : [],
				mode: move ? 'move' : 'add'
			}
		} ).then( function ( res ) {
			state.lastUndo = res.undo;
			applyCounts( res );
			toast( sprintf( l10n.undoAssigned, ( res.changed || [] ).length ), res.undo ? undo : null );
		} ).catch( function () {
			toast( l10n.failed, null );
		} );
	}

	/*
	 *  Update the counts the server just sent, instead of refetching the tree.
	 *
	 *  Every drop used to call load(), which pulls the whole tree back -- 185KB at
	 *  two thousand folders -- and repaints from scratch. That is the jolt after a
	 *  drag: a network round trip and a full rebuild to change two numbers. The
	 *  assign response already carries the fresh counts, so nothing needs fetching
	 *  and only the rows whose numbers moved are touched.
	 */
	function applyCounts( res ) {

		var counts = res.counts || {};

		state.nodes.forEach( function ( n ) {
			if ( Object.prototype.hasOwnProperty.call( counts, n.id ) ) {
				n.count = counts[ n.id ];
			}
		} );

		if ( typeof res.unassigned === 'number' ) {
			state.unassigned = res.unassigned;
		}

		index();
		flat = flatten();
		paint( true );
	}

	function undo() {
		if ( ! state.lastUndo ) {
			return;
		}
		var payload = state.lastUndo;
		state.lastUndo = null;
		apiFetch( {
			path: '/vergeml/v1/assign',
			method: 'POST',
			data: { taxonomy: payload.taxonomy, batch: payload.batch }
		} ).then( function ( res ) {
			applyCounts( res );
			toast( l10n.undone, null );
		} ).catch( function () {
			toast( l10n.failed, null );
		} );
	}

	/* --------------------------------------------------------------- toast */

	var toastTimer = null;

	function toast( message, action ) {
		if ( ! toastEl ) {
			return;
		}
		clearTimeout( toastTimer );
		toastEl.innerHTML = '';
		toastEl.appendChild( el( 'span', {}, message ) );
		if ( action ) {
			var b = el( 'button', { type: 'button', class: 'vgml-undo' }, l10n.undo );
			b.addEventListener( 'click', function () {
				clearTimeout( toastTimer );
				toastEl.classList.remove( 'is-shown' );
				action();
			} );
			toastEl.appendChild( b );
		}
		toastEl.classList.add( 'is-shown' );
		toastTimer = setTimeout( function () {
			toastEl.classList.remove( 'is-shown' );
			state.lastUndo = null;
		}, 10000 );
	}

	/* ------------------------------------------------------------- folders */

	function folder( data ) {
		data.taxonomy = state.taxonomy;
		return apiFetch( { path: '/vergeml/v1/folder', method: 'POST', data: data } ).then( function ( res ) {
			state.nodes = res.nodes || [];
			state.unassigned = res.unassigned || 0;
			index();
			render();
			return res;
		} ).catch( function ( err ) {
			toast( ( err && err.message ) || l10n.failed, null );
		} );
	}

	/*
	 *  Naming a folder happens in the row itself.
	 *
	 *  This used to be window.prompt(), and a browser prompt is the single loudest
	 *  signal that something is a prototype: it is grey, it is centred on the
	 *  screen far from the thing it is about, it cannot be styled, and it blocks
	 *  the page. Renaming in place is also simply better -- the name is edited
	 *  where the name lives.
	 */
	var editing = 0;      // id of the folder being renamed
	var creatingUnder = -1; // parent id for a new folder, -1 when not creating

	function rename( node ) {
		editing = node.id;
		creatingUnder = -1;
		paint( true );
		focusEditor();
	}

	function startCreate( parentId ) {
		creatingUnder = parentId;
		editing = 0;
		// A new folder appears under its parent, so open the parent first.
		if ( parentId ) {
			state.open[ parentId ] = true;
		}
		render();
		focusEditor();
	}

	function focusEditor() {
		window.setTimeout( function () {
			var input = listEl.querySelector( '.vgml-editor' );
			if ( input ) {
				input.focus();
				input.select();
			}
		}, 0 );
	}

	function cancelEditing() {
		editing = 0;
		creatingUnder = -1;
		paint( true );
	}

	// The input a row turns into, used for both renaming and creating.
	function editorRow( depth, value, commit ) {

		var item = el( 'li', { class: 'vgml-node vgml-editing', role: 'none' } );
		var row = el( 'div', { class: 'vgml-row', style: '--vgml-indent:' + ( depth * 14 + 8 ) + 'px' } );

		row.appendChild( el( 'span', { class: 'vgml-twist is-leaf', 'aria-hidden': 'true' } ) );
		row.appendChild( folderIcon( '' ) );

		var input = el( 'input', {
			class: 'vgml-editor',
			type: 'text',
			value: value,
			'aria-label': l10n.namePrompt,
			maxlength: '200'
		} );
		input.value = value;

		input.addEventListener( 'keydown', function ( e ) {
			e.stopPropagation(); // the tree's arrow-key handler is not wanted here
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				commit( input.value.trim() );
			} else if ( e.key === 'Escape' ) {
				e.preventDefault();
				cancelEditing();
			}
		} );

		// Clicking away accepts, which is what every file manager does.
		input.addEventListener( 'blur', function () {
			commit( input.value.trim() );
		} );

		row.appendChild( input );
		item.appendChild( row );
		return item;
	}

	/*
	 *  Deleting asks in the panel, not in a browser dialog.
	 *
	 *  The wording matters more than the styling here: "delete folder" reads as
	 *  "delete my photos" to anyone who has not thought about it, and the whole
	 *  reason folders are terms is that it is not true. So the question says so,
	 *  in the place the folder is.
	 */
	function confirmDelete( node ) {

		var kids = ( state.children[ node.id ] || [] ).length;

		var box = el( 'div', { class: 'vgml-confirm', role: 'alertdialog', 'aria-label': l10n.delete } );
		box.appendChild( el( 'p', { class: 'vgml-confirm-text' },
			kids ? sprintf( l10n.deleteConfirm, node.name, kids ) : sprintf( l10n.deleteSimple, node.name ) ) );

		var actions = el( 'div', { class: 'vgml-confirm-actions' } );

		var no = el( 'button', { type: 'button', class: 'button button-small' }, l10n.cancel );
		no.addEventListener( 'click', function () { box.remove(); } );

		var yes = el( 'button', { type: 'button', class: 'button button-small vgml-danger' }, l10n.delete );
		yes.addEventListener( 'click', function () {
			box.remove();
			folder( { action: 'delete', id: node.id } ).then( function () {
				if ( state.selected === node.id ) {
					select( 0 );
				}
			} );
		} );

		actions.appendChild( no );
		actions.appendChild( yes );
		box.appendChild( actions );

		var existing = root.querySelector( '.vgml-confirm' );
		if ( existing ) { existing.remove(); }

		root.appendChild( box );
		yes.focus();
	}

	function menu( node ) {
		// Kept as the keyboard route to the same actions the toolbar offers.
		selectForEditing( node.id );
	}

	/* ----------------------------------------------------------- the shell */

	/*
	 *  The folder mark.
	 *
	 *  Two planes, not one shape. A back plane -- the tab and the body behind --
	 *  and a front plane over it. The front carries the folder's colour and the
	 *  back is the same colour darkened, which is what makes a 16px mark read as an
	 *  object with a lid rather than a coloured blob. One flat fill at this size
	 *  loses its silhouette against a busy row; two planes keep an internal edge.
	 *
	 *  It is drawn once, in one path pair, and everything it can say it says by
	 *  changing those two planes:
	 *
	 *    closed / open   the front plane slides down and skews, so an open branch
	 *                    is legible from the icon alone rather than only from the
	 *                    twisty at the far left
	 *    colour          per folder, ours. FileBird has three, globally, behind a
	 *                    licence; here every folder can differ and the mark is
	 *                    where that lives
	 *    empty           no front plane at all, and the back drops to a hairline
	 *                    outline. An empty folder should not shout as loudly as a
	 *                    full one, and on a two-thousand-row tree that difference
	 *                    is what makes the filled ones findable
	 *
	 *  Inline SVG, sized in a 20x16 box: no request, no sprite, no icon font, and
	 *  it inherits currentColor so the skins keep working without touching this.
	 *
	 *  The geometry is deliberately WordPress's: 1.5 corner radii and the same
	 *  optical weight as Dashicons, so it sits beside core's own icons without
	 *  looking like it came from somewhere else.
	 */
	function folderIcon( color, open, empty ) {

		var span = el( 'span', {
			class: 'vgml-icon' + ( empty ? ' is-empty' : '' ) + ( open ? ' is-open' : '' ),
			'aria-hidden': 'true'
		} );

		// The back plane: tab plus body. Always present -- it is the silhouette.
		var back = '<path class="vgml-f-back" d="M0 2.5A1.5 1.5 0 0 1 1.5 1h5.2a1.5 1.5 0 0 1 1.06.44L9.5 3h7A1.5 1.5 0 0 1 18 4.5v9A1.5 1.5 0 0 1 16.5 15h-15A1.5 1.5 0 0 1 0 13.5v-11Z"/>';

		// The front plane. Closed it sits square over the body; open it slides down
		// and skews, which is the whole open/closed cue at this size.
		var front = open
			? '<path class="vgml-f-front" d="M4.3 6.6h14.2a1.2 1.2 0 0 1 1.16 1.52l-1.63 5.9A1.5 1.5 0 0 1 16.58 15H1.6a1.2 1.2 0 0 1-1.16-1.52l1.63-5.9A1.5 1.5 0 0 1 3.5 6.6h.8Z"/>'
			: '<path class="vgml-f-front" d="M1.4 6.6h15.2a1.4 1.4 0 0 1 1.4 1.4v5.5A1.5 1.5 0 0 1 16.5 15h-15A1.5 1.5 0 0 1 0 13.5V8a1.4 1.4 0 0 1 1.4-1.4Z"/>';

		span.innerHTML = '<svg viewBox="0 0 20 16" width="17" height="14">' +
			back + ( empty ? '' : front ) + '</svg>';

		if ( color ) {
			span.style.color = color;
		}

		return span;
	}

	/*
	 *  The toolbar: what can be done, visible before you need it.
	 *
	 *  Rename and Delete stay disabled until a folder is picked, which is how the
	 *  panel says "these apply to a folder" without a word of explanation. The
	 *  previous build hid every one of these behind a kebab that appeared on
	 *  hover, so the screen never told you they existed.
	 */
	var toolbarEls = null;
	var editTarget = 0;

	function buildToolbar() {

		var bar = el( 'div', { class: 'vgml-tools' } );

		var renameBtn = el( 'button', { type: 'button', class: 'button button-small', disabled: 'disabled' }, l10n.rename );
		renameBtn.addEventListener( 'click', function () {
			var node = state.byId[ editTarget ];
			if ( node ) { rename( node ); }
		} );

		var deleteBtn = el( 'button', { type: 'button', class: 'button button-small', disabled: 'disabled' }, l10n.delete );
		deleteBtn.addEventListener( 'click', function () {
			var node = state.byId[ editTarget ];
			if ( node ) { confirmDelete( node ); }
		} );

		var more = el( 'button', { type: 'button', class: 'button button-small vgml-more', 'aria-label': l10n.skin, 'aria-haspopup': 'true' } );
		more.innerHTML = '&#8943;';
		more.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			toggleOverflow( more );
		} );

		bar.appendChild( renameBtn );
		bar.appendChild( deleteBtn );
		bar.appendChild( more );

		toolbarEls = { rename: renameBtn, remove: deleteBtn };
		return bar;
	}

	// Picking a folder is also what arms the toolbar.
	function selectForEditing( id ) {
		editTarget = id > 0 ? id : 0;
		if ( toolbarEls ) {
			var off = ! editTarget;
			toolbarEls.rename.disabled = off;
			toolbarEls.remove.disabled = off;
		}
	}

	function toggleOverflow( anchor ) {

		var open = root.querySelector( '.vgml-overflow' );
		if ( open ) {
			open.remove();
			return;
		}

		var menuEl = el( 'div', { class: 'vgml-overflow', role: 'menu' } );

		menuEl.appendChild( el( 'p', { class: 'vgml-overflow-head' }, l10n.skin ) );

		[ 'native', 'classic', 'minimal', 'contrast' ].forEach( function ( s ) {
			var label = l10n[ 'skin' + s.charAt( 0 ).toUpperCase() + s.slice( 1 ) ] || s;
			var b = el( 'button', {
				type: 'button',
				class: 'vgml-overflow-item' + ( state.skin === s ? ' is-on' : '' ),
				role: 'menuitemradio',
				'aria-checked': state.skin === s ? 'true' : 'false'
			}, label );
			b.addEventListener( 'click', function () {
				state.skin = s;
				render();
				persist( { skin: s } );
				menuEl.remove();
			} );
			menuEl.appendChild( b );
		} );

		var compact = state.density === 'compact';
		var d = el( 'button', {
			type: 'button',
			class: 'vgml-overflow-item' + ( compact ? ' is-on' : '' ),
			role: 'menuitemcheckbox',
			'aria-checked': compact ? 'true' : 'false'
		}, l10n.compact );
		d.addEventListener( 'click', function () {
			state.density = compact ? 'comfortable' : 'compact';
			render();
			persist( { density: state.density } );
			menuEl.remove();
		} );
		menuEl.appendChild( d );

		root.appendChild( menuEl );

		// Dismiss on the next click anywhere else.
		window.setTimeout( function () {
			document.addEventListener( 'click', function away() {
				menuEl.remove();
				document.removeEventListener( 'click', away );
			} );
		}, 0 );

		anchor.setAttribute( 'aria-expanded', 'true' );
	}

	function build() {
		root = el( 'div', { class: 'vgml-tree', 'data-skin': state.skin, 'data-density': state.density } );
		root.style.setProperty( '--vgml-accent', cfg.accent );
		root.style.width = state.width + 'px';

		/*
		 *  Header, then a toolbar, then the search, then the tree.
		 *
		 *  The shape is deliberately the one every file manager uses, because it is
		 *  the one people already know: what this is, what I can do to it, how I
		 *  find something, and then the thing itself. The previous version had a
		 *  title and a `+` in a bordered square, and every action hidden behind a
		 *  kebab that only appeared on hover -- which meant nothing on the screen
		 *  told you what was possible.
		 */
		var head = el( 'div', { class: 'vgml-head' } );

		if ( cfg.taxonomies.length > 1 ) {
			var pick = el( 'select', { class: 'vgml-tax', 'aria-label': l10n.folders } );
			cfg.taxonomies.forEach( function ( t ) {
				var o = el( 'option', { value: t.name }, t.label );
				if ( t.name === state.taxonomy ) {
					o.setAttribute( 'selected', 'selected' );
				}
				pick.appendChild( o );
			} );
			pick.addEventListener( 'change', function () {
				state.taxonomy = pick.value;
				state.selected = 0;
				editing = 0;
				creatingUnder = -1;
				load();
			} );
			head.appendChild( pick );
		} else {
			head.appendChild( el( 'span', { class: 'vgml-title' }, cfg.taxonomies[ 0 ].label ) );
		}

		if ( cfg.canManage ) {
			var add = el( 'button', { type: 'button', class: 'button button-primary button-small vgml-new' }, l10n.newFolder );
			add.addEventListener( 'click', function () {
				startCreate( 0 );
			} );
			head.appendChild( add );
		}

		root.appendChild( head );

		if ( cfg.canManage ) {
			root.appendChild( buildToolbar() );
		}

		var find = el( 'div', { class: 'vgml-find' } );
		searchEl = el( 'input', { type: 'search', class: 'vgml-search', placeholder: l10n.search, 'aria-label': l10n.search } );
		searchEl.addEventListener( 'input', function () {
			state.filter = searchEl.value.toLowerCase();
			render();
		} );
		find.appendChild( searchEl );
		root.appendChild( find );

		listEl = el( 'ul', { class: 'vgml-list', role: 'tree' } );
		listEl.addEventListener( 'keydown', onKey );

		/*
		 *  Repaint the window as it scrolls, on the next frame rather than on every
		 *  scroll event -- the browser fires those far faster than it paints, and
		 *  doing the work per event is how a scroll starts to stutter.
		 */
		var pending = false;
		listEl.addEventListener( 'scroll', function () {
			if ( ! windowed || pending ) {
				return;
			}
			pending = true;
			window.requestAnimationFrame( function () {
				pending = false;
				paint();
			} );
		} );
		root.appendChild( listEl );

		/*
		 *  The skin and density controls are gone from the panel.
		 *
		 *  Four unlabelled coloured circles sitting under the tree were the most
		 *  obviously bolted-on thing on the screen: no labels, no active state, and
		 *  permanently present for a choice made once. They live in the overflow
		 *  menu now, where a once-a-year setting belongs.
		 */

		toastEl = el( 'div', { class: 'vgml-toast', role: 'status', 'aria-live': 'polite' } );
		root.appendChild( toastEl );

		var grip = el( 'div', { class: 'vgml-grip', role: 'separator', 'aria-orientation': 'vertical' } );
		grip.addEventListener( 'mousedown', function ( e ) {
			e.preventDefault();
			var startX = e.clientX;
			var startW = root.offsetWidth;
			function move( ev ) {
				var delta = ev.clientX - startX;
				// In RTL the panel grows as the pointer moves the other way.
				if ( document.documentElement.getAttribute( 'dir' ) === 'rtl' ) {
					delta = -delta;
				}
				var w = Math.max( 160, Math.min( 640, startW + delta ) );
				state.width = w;
				setPanelWidth( w );
			}
			function up() {
				document.removeEventListener( 'mousemove', move );
				document.removeEventListener( 'mouseup', up );
				persist( { width: state.width } );
			}
			document.addEventListener( 'mousemove', move );
			document.addEventListener( 'mouseup', up );
		} );
		root.appendChild( grip );

		return root;
	}

	/*
	 *  Where the panel goes.
	 *
	 *  Inserted beside whichever container this screen actually has, and the page
	 *  is marked so the stylesheet can lay it out. Nothing core owns is replaced
	 *  or reordered -- if none of these exist, the tree simply does not appear,
	 *  which is the correct failure for a screen that has changed shape.
	 */
	function mount() {
		var host = document.querySelector( hostSelector() );
		var wrap = document.querySelector( '.wrap' );

		if ( ! host || ! wrap ) {
			return false;
		}

		/*
		 *  Inserted, never moved.
		 *
		 *  The first version put the panel and the media frame inside a flex
		 *  wrapper, which meant reparenting the frame. wp.media had already bound
		 *  to that element, so moving it destroyed the frame outright: the grid
		 *  rendered zero attachments and wp.media.frame was undefined. The plugin
		 *  broke the media library it exists to improve, and it did it by breaking
		 *  the one rule written at the top of this file.
		 *
		 *  So nothing of core's is touched. The panel is added as a child of the
		 *  wrap, the wrap is given a padded strip in CSS, and the panel sits in
		 *  the strip. Absolutely positioned children -- which is what the grid's
		 *  frame is -- resolve against the padding box, so the frame moves over on
		 *  its own without a single property of core's being overridden.
		 */
		wrap.insertBefore( build(), wrap.firstChild );

		document.body.classList.add( 'vgml-has-tree' );

		/*
		 *  Grid or list is a fact about the page, not about which element happened
		 *  to be found first. Deciding it from `host` meant that whenever the list
		 *  table turned up alongside the frame -- which it did in RTL -- the grid
		 *  class was never added, none of the grid rules applied, and the frame
		 *  covered the panel completely.
		 */
		var frame = isGridScreen() ? host : null;

		if ( frame ) {
			document.body.classList.add( 'vgml-mode-grid' );

			/*
			 *  Line the panel up with the frame rather than with the top of the
			 *  wrap, which is where the page heading lives -- the panel was sitting
			 *  on top of "Media Library". Measured rather than hard-coded, because
			 *  that offset is core's heading and it changes. Re-measured shortly
			 *  after, because the frame is still settling when this first runs and
			 *  an early read gives zero.
			 */
			alignToFrame( frame, wrap );
			setTimeout( function () { alignToFrame( frame, wrap ); }, 1200 );
		}

		setPanelWidth( state.width );
		wireFileDragging();

		return true;
	}

	/*
	 *  Grid view builds its frame asynchronously, so the container the panel sits
	 *  beside frequently does not exist yet when this script runs. Waited for,
	 *  with a ceiling: if the screen has changed shape enough that neither
	 *  container ever appears, the tree stays away rather than inventing somewhere
	 *  to put itself.
	 *
	 *  A bounded wait for a container to exist is not the same thing as FileBird's
	 *  ten MutationObservers -- those run for the life of the page because they
	 *  are re-imposing state core keeps overwriting. This one stops.
	 */
	/*
	 *  Which screen this is comes from the URL, not from whichever container turns
	 *  up first.
	 *
	 *  Racing the two selectors looked fine and was wrong: on the grid screen a
	 *  list table can exist before the media frame is built, so the race was
	 *  sometimes won by the wrong element -- the grid class was never added, none
	 *  of the grid layout applied, and the frame sat on top of the panel. It went
	 *  unnoticed because the race usually went the other way; RTL was simply slow
	 *  enough to lose it every time.
	 */
	function isGridScreen() {
		return /[?&]mode=grid/.test( window.location.search ) ||
			document.body.classList.contains( 'eml-grid' );
	}

	function hostSelector() {
		return isGridScreen() ? '.media-frame' : '.wp-list-table';
	}

	function whenHostExists( done ) {
		var tries = 0;

		( function look() {
			if ( document.querySelector( hostSelector() ) ) {
				done( true );
				return;
			}
			if ( ++tries > 40 ) { // ~10s
				done( false );
				return;
			}
			setTimeout( look, 250 );
		} )();
	}

	// One place that sets the width, because in grid the frame's inset has to
	// match it or the two overlap again.
	function setPanelWidth( w ) {
		root.style.width = w + 'px';
		document.body.style.setProperty( '--vgml-panel-w', w + 'px' );
	}

	function alignToFrame( frame, wrap ) {
		var top = frame.getBoundingClientRect().top - wrap.getBoundingClientRect().top;
		if ( top > 0 ) {
			document.body.style.setProperty( '--vgml-panel-top', Math.round( top ) + 'px' );
		}
	}

	function start() {
		whenHostExists( function ( found ) {
			if ( found ) {
				begin();
			}
		} );
	}

	function begin() {
		if ( ! mount() ) {
			return;
		}
		load();

		// Grid view builds itself asynchronously; if the frame arrives after we
		// do, re-select so the library reflects the folder already highlighted.
		if ( state.selected > 0 && window.wp && wp.media ) {
			setTimeout( function () { select( state.selected ); }, 500 );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
