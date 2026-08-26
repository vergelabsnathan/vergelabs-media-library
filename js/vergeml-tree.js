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
		width: ( cfg.state && cfg.state.width ) || 300,
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

	/*
	 *  Bumped every time the node list is rebuilt.
	 *
	 *  paint() skips the work when nothing about the rows would look different,
	 *  and it decided that by counting them. Anything that changes a row without
	 *  changing how many there are -- an arrangement, a rename, a colour, a count
	 *  going up after files are filed -- therefore reached the screen only on the
	 *  next reload. The number of rows is not the same question as whether the
	 *  rows changed.
	 */
	var revision = 0;

	function index() {
		revision++;
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

	/*
	 *  Every request that comes back with counts has to say which list it is for,
	 *  or a post screen refreshes itself with the media library's numbers.
	 */
	function postTypeQuery() {
		return ( cfg.postType && 'attachment' !== cfg.postType )
			? '&post_type=' + encodeURIComponent( cfg.postType )
			: '';
	}

	function forThisList( data ) {
		if ( cfg.postType && 'attachment' !== cfg.postType ) {
			data.post_type = cfg.postType;
		}
		return data;
	}

	/*
	 *  Which smart folder is showing, or ''. Kept beside state.selected rather
	 *  than inside it because they are different kinds of answer: a folder is a
	 *  term, a smart folder is a question, and only one of the two filters the
	 *  library at a time.
	 */
	function load() {
		return apiFetch( {
			path: '/vergeml/v1/tree?taxonomy=' + encodeURIComponent( state.taxonomy ) + postTypeQuery()
		} ).then( function ( data ) {
			state.nodes = data.nodes || [];
			state.unassigned = data.unassigned || 0;
			state.smart = data.smart || state.smart || [];
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

		/*
		 *  The smart folders: rows whose contents are a question. They live in
		 *  the same top group as All files and Unfiled because all of them are
		 *  views of the library rather than places in it.
		 */
		( state.smart || [] ).forEach( function ( sf, i ) {
			out.push( {
				pseudo: 'smart',
				smart: sf,
				label: sf.label,
				count: sf.count,
				depth: 0,
				id: -100 - i
			} );
		} );

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
		var key = state.skin + '|' + state.density + '|' + state.selected + '|' + ( state.smartSelected || '' ) + '|' + flat.length + '|' + state.filter + '|' + revision;

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

		var row = el( 'div', { class: 'vgml-row', style: '--vgml-indent:' + ( entry.depth * 19 + 8 ) + 'px' } );

		var twist = el( 'button', {
			class: 'vgml-twist' + ( entry.kids ? '' : ' is-leaf' ),
			type: 'button',
			tabindex: '-1',
			'aria-hidden': 'true'
		} );
		twist.innerHTML = entry.kids ? chevron() : '';
		if ( entry.open ) { twist.classList.add( 'is-open' ); }
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

	/*
	 *  The two rows that are not folders.
	 *
	 *  "Unfiled" borrows the folder silhouette and shows it hollow -- the same
	 *  shape as everything below it, holding nothing, which is exactly what it
	 *  means. It used to be a solid triangle, which reads as a warning.
	 *
	 *  "All files" is four tiles: the library itself rather than a folder. It was
	 *  three stacked bars, which is a menu button.
	 *
	 *  Shared, because the panel and the modal drew these separately and the
	 *  modal drew nothing at all -- an empty span where the mark should be.
	 */
	function pseudoIcon( key ) {

		if ( 'unassigned' === key ) {
			var hollow = folderIcon( '', false, true );
			hollow.classList.add( 'is-pseudo' );
			return hollow;
		}

		var span = el( 'span', { class: 'vgml-icon is-pseudo', 'aria-hidden': 'true' } );

		span.innerHTML = '<svg viewBox="0 0 20 16" width="20" height="16">' +
			'<rect x="0.5" y="1" width="8.5" height="6" rx="1.2"/>' +
			'<rect x="11" y="1" width="8.5" height="6" rx="1.2"/>' +
			'<rect x="0.5" y="9" width="8.5" height="6" rx="1.2"/>' +
			'<rect x="11" y="9" width="8.5" height="6" rx="1.2"/></svg>';

		return span;
	}

	function pseudoRow( entry ) {
		var key = entry.pseudo;

		if ( 'smart' === key ) {
			return smartRow( entry );
		}

		var selected = ( key === 'all' && ! state.selected && ! state.smartSelected )
			|| ( key === 'unassigned' && state.selected === -1 );

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

		row.appendChild( pseudoIcon( key ) );

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

	/*
	 *  A row whose contents are a question.
	 *
	 *  A never-scanned one shows "Scan" where its count would be, because "we
	 *  have not looked" and "there are none" are different answers and a zero
	 *  would be a lie. Clicking it runs the scan in place, progress in the row,
	 *  then filters -- one gesture from "unknown" to "here they are".
	 */
	function smartRow( entry ) {

		var sf = entry.smart;
		var selected = state.smartSelected === sf.key;

		var item = el( 'li', {
			class: 'vgml-node vgml-pseudo vgml-smart' + ( selected ? ' is-selected' : '' ),
			role: 'treeitem',
			'aria-level': '1',
			'aria-selected': selected ? 'true' : 'false',
			'data-smart': sf.key,
			tabindex: '-1'
		} );

		var row = el( 'div', { class: 'vgml-row' } );
		row.appendChild( el( 'span', { class: 'vgml-twist is-leaf', 'aria-hidden': 'true' } ) );

		var mark = el( 'span', { class: 'vgml-icon is-pseudo', 'aria-hidden': 'true' } );
		mark.innerHTML = '<svg viewBox="0 0 20 16" width="20" height="16">'
			+ '<path d="M1.5 1h17a1 1 0 0 1 .78 1.63L13 9.6V14a1 1 0 0 1-.55.9l-4 2A1 1 0 0 1 7 16v-6.4L.72 2.63A1 1 0 0 1 1.5 1Z" transform="scale(0.95) translate(0.5,-0.5)"/></svg>';
		row.appendChild( mark );

		row.appendChild( el( 'span', { class: 'vgml-name' }, sf.label ) );

		if ( sf._progress ) {
			row.appendChild( el( 'span', { class: 'vgml-count vgml-smart-progress' }, sf._progress ) );
		} else if ( null === sf.count || undefined === sf.count ) {
			row.appendChild( el( 'span', { class: 'vgml-count vgml-smart-scan' }, l10n.smartScan ) );
		} else if ( sf.count ) {
			row.appendChild( el( 'span', { class: 'vgml-count' }, String( sf.count ) ) );
		}

		row.addEventListener( 'click', function () {
			if ( justDragged || sf._progress ) {
				return;
			}
			if ( null === sf.count || undefined === sf.count ) {
				runSmartScan( sf );
			} else {
				smartSelect( sf.key );
			}
		} );

		item.appendChild( row );
		return item;
	}

	function smartSelect( key ) {

		state.smartSelected = key;
		state.selected = 0;
		render();

		var props = { vergeml_smart: key, uncategorized: null };
		props[ state.taxonomy ] = null;

		var lib = libraryProps();

		if ( lib ) {
			lib.set( props );
			return;
		}

		var url = new URL( window.location.href );
		url.searchParams.set( 'vgml_smart', key );
		url.searchParams.delete( state.taxonomy );
		url.searchParams.delete( unfiledVar() );
		url.searchParams.delete( 'paged' );
		swapTable( url.href );
	}

	function runSmartScan( sf ) {

		function step( resume ) {

			var data = resume ? { resume: resume } : {};

			apiFetch( { path: '/vergeml/v1/smart-scan', method: 'POST', data: data } ).then( function ( res ) {

				if ( ! res.complete && res.resume ) {
					sf._progress = ( 1 === res.phase ? l10n.scanPosts : l10n.scanFiles )
						.replace( '%1$s', res.done ).replace( '%2$s', res.total );
					index();
					render();
					step( res.resume );
					return;
				}

				// The finished scan hands back every count, so all five rows
				// become real numbers at once.
				delete sf._progress;

				( state.smart || [] ).forEach( function ( row ) {
					if ( res.counts && undefined !== res.counts[ row.key ] ) {
						row.count = res.counts[ row.key ];
					}
				} );

				index();
				render();
				smartSelect( sf.key );

			} ).catch( function () {
				delete sf._progress;
				index();
				render();
				toast( l10n.failed, null );
			} );
		}

		sf._progress = '…';
		index();
		render();
		step( null );
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
	/*
	 *  Where an upload should land: the folder currently open, on whichever
	 *  surface is doing the uploading. The library screen sets it from its
	 *  selection; the modal from its own filter. Zero means "nowhere", and an
	 *  upload then arrives exactly as it always has.
	 */
	var uploadTarget = 0;

	/*
	 *  A drop on a folder ROW files into that folder for that one batch,
	 *  whatever the grid is currently showing. Set at drop time, read by
	 *  BeforeUpload, cleared when the queue drains.
	 */
	var dropFolder = 0;

	/*
	 *  The folder travels with the upload request itself, added to the
	 *  multipart parameters just before each file goes -- so the server never
	 *  guesses which screen the user was on, and uploads from anywhere else
	 *  carry no folder at all.
	 *
	 *  The prototype is wrapped rather than wp.Uploader.defaults mutated,
	 *  because every uploader deep-copies the defaults when it is built:
	 *  changing them afterwards reaches no uploader that already exists, which
	 *  on the grid is all of them.
	 */
	function armUploaders() {

		var tries = 0;

		( function look() {

			if ( ! ( window.wp && wp.Uploader && wp.Uploader.prototype ) ) {
				if ( ++tries <= 60 ) { window.setTimeout( look, 250 ); }
				return;
			}

			if ( wp.Uploader.prototype.vgmlWrapped ) {
				return;
			}
			wp.Uploader.prototype.vgmlWrapped = true;

			var init = wp.Uploader.prototype.init;

			wp.Uploader.prototype.init = function () {
				if ( init ) { init.apply( this, arguments ); }
				if ( this.uploader && this.uploader.bind ) {
					this.uploader.bind( 'BeforeUpload', function ( up ) {
						var params = up.settings.multipart_params = up.settings.multipart_params || {};
						var target = dropFolder > 0 ? dropFolder : uploadTarget;
						if ( target > 0 ) {
							params.vergeml_folder = target;
							params.vergeml_folder_tax = state.taxonomy;
						} else {
							delete params.vergeml_folder;
							delete params.vergeml_folder_tax;
						}
					} );

					/*
					 *  When the queue finishes inside a folder, make the grid ask
					 *  again. The file was filed on the server after the upload,
					 *  so the browser's copy of it carries no folder -- the
					 *  filtered collection cannot see that it belongs, and a fresh
					 *  upload simply never appeared until a reload. A bumped
					 *  throwaway prop forces the collection to requery the server,
					 *  which does know; and the tree is refetched so the folder's
					 *  own count moves too.
					 */
					this.uploader.bind( 'UploadComplete', function () {

						var filed = uploadTarget > 0 || dropFolder > 0;
						dropFolder = 0;

						if ( ! filed ) {
							return;
						}

						var lib = libraryProps();

						if ( lib ) {
							lib.set( { vergeml_bump: String( Date.now() ) } );
						}

						load();
					} );
				}
			};
		} )();
	}

	/*
	 *  The drag-and-drop overlay names its destination. Files dropped on the
	 *  grid land in the selected folder (BeforeUpload adds the folder to the
	 *  upload), and the overlay is where that promise gets made visible --
	 *  "Drop files to add to (folder)" instead of the stock line, so nobody
	 *  has to drop one and check where it went.
	 */
	function dropHint() {
		var h1 = document.querySelector( '.uploader-window .uploader-editor-title, .uploader-window h1' );
		if ( ! h1 ) {
			return;
		}
		if ( ! h1.getAttribute( 'data-vgml-stock' ) ) {
			h1.setAttribute( 'data-vgml-stock', h1.textContent );
		}
		var node = uploadTarget > 0 && state.byId ? state.byId[ uploadTarget ] : null;
		h1.textContent = node
			? sprintf( l10n.dropInto, node.name )
			: h1.getAttribute( 'data-vgml-stock' );

		if ( uploadBtn ) {
			uploadBtn.title = node ? sprintf( l10n.uploadTo, node.name ) : l10n.uploadUnfiled;
		}
	}

	function select( id ) {
		state.selected = id;
		state.smartSelected = '';
		uploadTarget = id > 0 ? id : 0;
		dropHint();
		render();
		persist( { selected: id > 0 ? id : 0 } );

		var props = { vergeml_smart: null };

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

		/*
		 *  List view has no JS library to talk to, so the filter goes in the query
		 *  string -- the same one the dropdown produced. But it is fetched and the
		 *  table swapped in place rather than navigated to.
		 *
		 *  Navigating was the honest first version and it made the screen feel like
		 *  it was constantly reloading, because it was: every folder click threw the
		 *  whole page away and rebuilt it, tree included. Clicking five folders to
		 *  find something meant five full page loads.
		 *
		 *  Only the table and its navigation are replaced. The tree is not touched,
		 *  so it keeps its scroll position, its open branches and its focus.
		 */
		var url = new URL( window.location.href );

		if ( id > 0 ) {
			/*
			 *  The slug, not the id.
			 *
			 *  WordPress resolves a taxonomy query var by slug, so ?media_category=3
			 *  matches a term whose slug is "3" -- which is to say nothing, silently.
			 *  The plugin now accepts either form on the library screen, but a link
			 *  should still be written the way WordPress writes one.
			 */
			var node = state.byId[ id ];
			url.searchParams.set( state.taxonomy, node && node.slug ? node.slug : id );
			url.searchParams.delete( unfiledVar() );
		} else if ( id === -1 ) {
			url.searchParams.delete( state.taxonomy );
			url.searchParams.set( unfiledVar(), '1' );
		} else {
			url.searchParams.delete( state.taxonomy );
			url.searchParams.delete( unfiledVar() );
		}

		// Paging belongs to the previous folder, and so does a smart filter.
		url.searchParams.delete( 'vgml_smart' );
		url.searchParams.delete( 'paged' );

		if ( url.href === window.location.href ) {
			return;
		}

		swapTable( url.href );
	}

	var swapping = null;

	/*
	 *  "Unfiled" is asked for differently on the two screens.
	 *
	 *  The media library has answered `uncategorized=1` since long before the
	 *  tree existed and links to it are in the wild, so it keeps that spelling. A
	 *  post type's list screen has no such history and gets a query var of ours,
	 *  which nothing else on that screen is going to collide with.
	 */
	function unfiledVar() {
		return ( cfg.postType && 'attachment' !== cfg.postType ) ? 'vgml_unfiled' : 'uncategorized';
	}

	function swapTable( href ) {

		var host = document.querySelector( '.wp-list-table' );

		if ( ! host ) {
			window.location.href = href; // no table to swap: fall back honestly
			return;
		}

		// Clicking through folders quickly must not race: the last click wins.
		var token = {};
		swapping = token;

		host.classList.add( 'vgml-busy' );

		window.fetch( href, { credentials: 'same-origin' } )
			.then( function ( r ) { return r.text(); } )
			.then( function ( html ) {

				if ( swapping !== token ) {
					return; // a later click already took over
				}

				var doc = new DOMParser().parseFromString( html, 'text/html' );
				var next = doc.querySelector( '.wp-list-table' );

				if ( ! next ) {
					window.location.href = href;
					return;
				}

				host.parentNode.replaceChild( next, host );

				// The counts and pager above and below the table belong to the
				// result set, so they move with it.
				var navs = document.querySelectorAll( '.tablenav' );
				var freshNavs = doc.querySelectorAll( '.tablenav' );
				for ( var i = 0; i < navs.length && i < freshNavs.length; i++ ) {
					navs[ i ].parentNode.replaceChild( freshNavs[ i ], navs[ i ] );
				}

				window.history.pushState( {}, '', href );
			} )
			.catch( function () {
				window.location.href = href;
			} )
			.then( function () {
				var t = document.querySelector( '.wp-list-table' );
				if ( t ) {
					t.classList.remove( 'vgml-busy' );
				}
			} );
	}

	/*
	 *  Back and forward have to work: pushState without this leaves the browser
	 *  buttons pointing at URLs that never load anything.
	 */
	window.addEventListener( 'popstate', function () {
		if ( document.querySelector( '.wp-list-table' ) ) {
			swapTable( window.location.href );
		}
	} );

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

	/*
	 *  Move a folder one place among its siblings, from the keyboard.
	 *
	 *  Arranging by dragging is the obvious gesture and it is also the one nobody
	 *  can use without a mouse. The tree is a real ARIA tree with full keyboard
	 *  navigation everywhere else, so leaving one operation mouse-only would make
	 *  it the single thing a keyboard user cannot do -- and it costs a handler.
	 *
	 *  Within siblings only. Alt+Arrow re-parenting would mean a keystroke that
	 *  silently restructures the tree, which is not a thing to do by accident.
	 */
	function nudge( id, delta ) {

		if ( ! cfg.canManage ) {
			return;
		}

		var node = state.byId[ id ];

		if ( ! node ) {
			return;
		}

		var ids = ( state.children[ node.parent ] || [] ).map( function ( n ) {
			return n.id;
		} );

		var at = ids.indexOf( id );
		var to = at + delta;

		if ( at === -1 || to < 0 || to >= ids.length ) {
			return;
		}

		ids.splice( at, 1 );
		ids.splice( to, 0, id );

		folder( { action: 'order', parent: node.parent, ids: ids } ).then( function () {
			// The row is rebuilt by the repaint, so focus is put back by id.
			restoreFocus( id );
		} );
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
				// Alt moves the folder instead of moving to it.
				if ( e.altKey ) { nudge( id, 1 ); break; }
				if ( flat[ i + 1 ] ) { restoreFocus( flat[ i + 1 ].id ); }
				break;
			case 'ArrowUp':
				e.preventDefault();
				if ( e.altKey ) { nudge( id, -1 ); break; }
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
		// media[] on the library screen, post[] on a post type's list screen.
		var checked = document.querySelectorAll( '#the-list input[name="media[]"]:checked, #the-list input[name="post[]"]:checked' );
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
	 *  Where a dragged folder would land on the row under the pointer.
	 *
	 *  'into' re-parents, which is what dropping on a folder has always meant.
	 *  The top and bottom thirds mean "between these two", which is the only way
	 *  to express an order by hand -- without them a tree can be restructured but
	 *  never arranged, and alphabetical order reads as a missing feature rather
	 *  than as a decision.
	 */
	var dropZone = 'into';
	var zoneRow = 0;

	function rowTermId( row ) {
		var item = row && row.closest ? row.closest( '.vgml-node' ) : null;
		return item ? parseInt( item.getAttribute( 'data-id' ), 10 ) || 0 : 0;
	}

	function clearZones() {
		var marked = document.querySelectorAll( '.vgml-row.is-before, .vgml-row.is-after' );
		for ( var i = 0; i < marked.length; i++ ) {
			marked[ i ].classList.remove( 'is-before', 'is-after' );
		}
		zoneRow = 0;
		dropZone = 'into';
	}

	function trackZone( e ) {

		if ( ! draggingFolder ) {
			return;
		}

		var under = document.elementFromPoint( e.clientX, e.clientY );
		var row = under && under.closest ? under.closest( '.vgml-row' ) : null;
		var id = rowTermId( row );

		if ( ! row || ! id || id === draggingFolder ) {
			clearZones();
			return;
		}

		var box = row.getBoundingClientRect();
		var at = box.height ? ( e.clientY - box.top ) / box.height : 0.5;
		var zone = at < 0.3 ? 'before' : ( at > 0.7 ? 'after' : 'into' );

		if ( id === zoneRow && zone === dropZone ) {
			return;
		}

		clearZones();

		zoneRow = id;
		dropZone = zone;

		if ( 'into' === zone ) {
			return;
		}

		// No line where the drop would be refused anyway: a folder cannot join the
		// children of its own descendant.
		var node = state.byId[ id ];
		if ( node && canReparent( draggingFolder, node.parent ) ) {
			row.classList.add( 'before' === zone ? 'is-before' : 'is-after' );
		}
	}


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
		 *  Armed when items appear, not when they are hovered.
		 *
		 *  Hover was the first approach and it is too fragile to keep: a delegated
		 *  mouseenter does not fire for every way a pointer can arrive at an
		 *  element -- moving it in programmatically does not trigger it at all --
		 *  so a file could sit there looking draggable and simply not be. Arming on
		 *  render means the first drag works however the pointer got there.
		 *
		 *  The observer is scoped to the containers the library renders into. Not
		 *  the ten global ones FileBird runs, which exist because they are
		 *  re-imposing state core keeps overwriting; this one arms new rows and
		 *  does nothing else.
		 */
		armDraggables();

		if ( window.MutationObserver ) {

			var watcher = new window.MutationObserver( function () {
				armDraggables();
			} );

			[ '#the-list', '.attachments', '.media-frame-content' ].forEach( function ( sel ) {
				Array.prototype.forEach.call( document.querySelectorAll( sel ), function ( node ) {
					watcher.observe( node, { childList: true, subtree: true } );
				} );
			} );
		}

		// A safety net for anything rendered somewhere unobserved.
		$( document ).on( 'mouseenter mouseover', '.attachment, #the-list tr', function () {
			armOne( $( this ) );
		} );
	}

	function armDraggables() {

		var $ = window.jQuery;

		if ( ! $ || ! $.fn || ! $.fn.draggable ) {
			return;
		}

		/*
		 *  The cells as well as the row.
		 *
		 *  A list row is nearly a hundred pixels tall and its mousedown lands on
		 *  whichever cell is under the pointer, not on the <tr>. Arming only the row
		 *  meant dragging from the thumbnail or the title -- the two places anybody
		 *  actually grabs -- did nothing at all, while dragging from the empty right
		 *  of the row worked. Which is the worst possible way for it to be broken:
		 *  it looks like the feature does not exist.
		 *
		 *  The checkbox column is left alone; jQuery UI cancels on inputs anyway,
		 *  and a drag that starts by ticking a box is not what anyone meant.
		 */
		$( '.attachment, #the-list tr' ).each( function () {
			armOne( $( this ) );
		} );

		$( '#the-list td:not(.check-column)' ).each( function () {
			armOne( $( this ) );
		} );
	}

	function armOne( $item ) {

		var $ = window.jQuery;

		if ( ! $ || ! $.fn || ! $.fn.draggable || ! $item.length || $item.data( 'vgml-drag' ) ) {
			return;
		}

		$item.data( 'vgml-drag', true );

		$item.draggable( {
			addClasses: false,
			appendTo: 'body',
			cursorAt: { top: 12, left: 12 },
			/*
			 *  Fifteen pixels before a drag begins. It was six, and a click on a
			 *  touchpad routinely wanders more than six -- the "click" became an
			 *  aborted drag, the drag swallowed the click, and opening a file's
			 *  details felt broken about one time in five. A drag toward a folder
			 *  travels hundreds of pixels; it can afford to declare itself.
			 */
			distance: 15,
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
				/*
				 *  In Bulk select, clicks toggle selection -- and a click with a
				 *  few pixels of hand movement is still a click to the person
				 *  making it. With every tile armed as a draggable, that movement
				 *  started a drag instead, the drag swallowed the click, and Bulk
				 *  select read as simply broken.
				 *
				 *  But select mode is also where a multi-selection is made, and
				 *  dragging that selection into a folder is the whole point of
				 *  making one. So the line is drawn by what is under the pointer:
				 *  an UNSELECTED tile in select mode is being clicked -- stand
				 *  down and let the click land. A SELECTED tile is being picked
				 *  up -- drag the selection.
				 */
				if ( document.querySelector( '.media-frame.mode-select' ) ) {
					var tile = this.closest ? this.closest( '.attachment' ) : null;
					if ( ! tile || ! tile.classList.contains( 'selected' ) ) {
						return false;
					}
				}
				document.body.classList.add( 'vgml-dragging-files' );
				// Raised on start, not stop: revert:'invalid' defers stop until
				// after the animation, by which time the click has already fired.
				dragBegan();
			},
			stop: function () {
				document.body.classList.remove( 'vgml-dragging-files' );
				dragging = null;
				dragEnded();
			}
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

		// Armed elements can be a grid tile, a list row, or a cell inside one --
		// so walk up to whichever of those carries the id.
		if ( item.closest ) {
			var owner = item.closest( '.attachment, tr[id^="post-"]' );
			if ( owner ) {
				item = owner;
			}
		}

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

				// A folder being dragged onto another folder: re-parent it, or
				// place it beside that folder if the pointer was on an edge.
				if ( draggingFolder ) {

					var moving = draggingFolder;
					var zone = ( termId === zoneRow ) ? dropZone : 'into';

					draggingFolder = 0;
					clearZones();

					if ( 'into' === zone ) {
						if ( canReparent( moving, termId ) ) {
							folderMove( moving, termId );
						}
					} else {
						folderPlace( moving, termId, zone );
					}
					return;
				}

				var ids = ( dragging && dragging.length ) ? dragging : selectionIds();

				if ( ! ids.length ) {
					return;
				}

				/*
				 *  A plain drag MOVES. Ctrl or Alt adds to a second folder.
				 *
				 *  It began the other way round -- plain drag added, because a file
				 *  in several folders is the thing this plugin can do that the
				 *  competitors cannot. And the first person to try it read the add
				 *  as a copy bug: "the original stays in the old folder". Thirty
				 *  years of desktop file managers say plain drag moves and Ctrl
				 *  copies, and a default that fights that reads as broken no matter
				 *  what the toast says afterwards. Multi-folder is still here, on
				 *  exactly the modifier that has always meant "copy".
				 *
				 *  With "one folder per file" on, Ctrl is ignored rather than
				 *  inverted: every drop is a move, which is the promise the setting
				 *  makes. The modifier is read from the original event because
				 *  jQuery UI hands us the mouse event that ended the drag.
				 */
				var move = cfg.onePerFile || ! ( e && ( e.ctrlKey || e.altKey ) );

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
	 *  Put a folder beside another one.
	 *
	 *  The whole sibling list goes to the server rather than a position, because
	 *  a position means nothing without the list it is a position in -- and the
	 *  browser is already holding the list. One request, and it re-parents at the
	 *  same time, so dragging a folder between two others in a different branch
	 *  stays one gesture.
	 */
	function folderPlace( moving, targetId, zone ) {

		var target = state.byId[ targetId ];

		if ( ! target || ! canReparent( moving, target.parent ) ) {
			return;
		}

		var ids = ( state.children[ target.parent ] || [] ).map( function ( n ) {
			return n.id;
		} ).filter( function ( id ) {
			return id !== moving;
		} );

		var at = ids.indexOf( targetId );

		if ( at === -1 ) {
			return;
		}

		ids.splice( 'before' === zone ? at : at + 1, 0, moving );

		folder( { action: 'order', parent: target.parent, ids: ids } );
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
				document.addEventListener( 'mousemove', trackZone );
				dragBegan();
			},
			stop: function () {
				row.classList.remove( 'is-dragging' );
				document.body.classList.remove( 'vgml-dragging-folder' );
				document.removeEventListener( 'mousemove', trackZone );
				clearZones();
				draggingFolder = 0;
				dragEnded();
			}
		} );
	}

	function assign( ids, termId, move ) {
		apiFetch( {
			path: '/vergeml/v1/assign',
			method: 'POST',
			data: forThisList( {
				taxonomy: state.taxonomy,
				attachments: ids,
				// A null term means "no folder", which with mode 'move' is unfiling.
				add: termId ? [ termId ] : [],
				mode: move ? 'move' : 'add'
			} )
		} ).then( function ( res ) {
			state.lastUndo = res.undo;
			applyCounts( res );
			var moved = ( res.changed || [] ).length;
			if ( moved && move ) {
				bumpGrid();
			}
			toast( 1 === moved ? l10n.undoAssignedOne : sprintf( l10n.undoAssigned, moved ),
				res.undo ? undo : null );
		} ).catch( function () {
			toast( l10n.failed, null );
		} );
	}

	/*
	 *  After a move, the grid still shows the file where it no longer is: the
	 *  browser's filtered collection was queried before the file changed
	 *  folders, and nothing tells it to ask again. A bumped throwaway prop
	 *  does -- the same trick the uploader uses -- so the tile leaves the
	 *  moment the drop lands. Only worth a round trip when the view is
	 *  actually filtered; on All files the move changes nothing visible.
	 */
	function bumpGrid() {
		if ( 0 === state.selected && ! state.smartSelected ) {
			return;
		}
		var lib = libraryProps();
		if ( lib ) {
			lib.set( { vergeml_bump: String( Date.now() ) } );
		}
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
			bumpGrid();
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
		forThisList( data );
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
		var row = el( 'div', { class: 'vgml-row', style: '--vgml-indent:' + ( depth * 19 + 8 ) + 'px' } );

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
	/*
	 *  The disclosure chevron, drawn rather than typed.
	 *
	 *  It was a text triangle (&#9656;) at nine-ish pixels, which rendered
	 *  differently on every platform and small everywhere. A stroked chevron is
	 *  the same shape on every machine, big enough to see, and turns with a
	 *  transition instead of being swapped for a different character.
	 */
	function chevron() {
		return '<svg viewBox="0 0 12 12" width="12" height="12" fill="none">'
			+ '<path d="M4.2 2.4 L8 6 L4.2 9.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	function folderIcon( color, open, empty ) {

		var span = el( 'span', {
			class: 'vgml-icon' + ( empty ? ' is-empty' : '' ) + ( open ? ' is-open' : '' ),
			'aria-hidden': 'true'
		} );

		/*
		 *  Flat. One silhouette, one colour, no simulated depth -- the faux-3D
		 *  two-plane fill read as dated next to the rest of the panel. Closed
		 *  is a single solid rounded folder. Open is the flat open-folder
		 *  glyph: a thin band of the back sheet above a tilted front flap,
		 *  separated by a real gap that lets the row background through, so
		 *  the geometry says "open" without a second tone. Empty folders keep
		 *  the outline treatment from the stylesheet.
		 */
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

	/*
	 *  The toolbar: what can be done, visible before you need it.
	 *
	 *  Rename and Delete stay disabled until a folder is picked, which is how the
	 *  panel says "these apply to a folder" without a word of explanation. The
	 *  previous build hid every one of these behind a kebab that appeared on
	 *  hover, so the screen never told you they existed.
	 */
	var toolbarEls = null;
	var uploadBtn = null;
	var editTarget = 0;

	function buildToolbar() {

		var bar = el( 'div', { class: 'vgml-tools' } );

		var renameBtn = el( 'button', { type: 'button', class: 'button button-small', disabled: 'disabled' }, l10n.rename );
		renameBtn.addEventListener( 'click', function () {
			var node = state.byId[ editTarget ];
			if ( node ) { rename( node ); }
		} );

		/*
		 *  Colour has had an endpoint, a palette and a rendered icon since the tree
		 *  was built, and no way for anybody to reach it. A per-folder colour is
		 *  one of the few things this does that FileBird does not -- theirs are
		 *  global and there are three -- so shipping the half with no control was
		 *  the worst of both.
		 */
		var colorBtn = el( 'button', { type: 'button', class: 'button button-small vgml-color', disabled: 'disabled', 'aria-haspopup': 'true' }, l10n.color );
		colorBtn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			if ( editTarget ) { toggleSwatches( colorBtn ); }
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
		bar.appendChild( colorBtn );
		bar.appendChild( deleteBtn );
		bar.appendChild( more );

		toolbarEls = { rename: renameBtn, color: colorBtn, remove: deleteBtn };
		return bar;
	}

	/*
	 *  The eight colours, named.
	 *
	 *  Same popover as the overflow menu rather than a second kind of panel: one
	 *  menu shape in the tree means one set of dismiss rules, one bit of CSS, and
	 *  nothing new to learn.
	 */
	function toggleSwatches( anchor ) {

		var open = root.querySelector( '.vgml-overflow' );
		if ( open ) {
			open.remove();
			return;
		}

		var node = state.byId[ editTarget ];

		if ( ! node ) {
			return;
		}

		var names = [ l10n.colorNone, l10n.colorRed, l10n.colorAmber, l10n.colorOlive,
			l10n.colorGreen, l10n.colorTeal, l10n.colorBlue, l10n.colorViolet, l10n.colorMagenta ];

		var menuEl = el( 'div', { class: 'vgml-overflow', role: 'menu' } );
		menuEl.appendChild( el( 'p', { class: 'vgml-overflow-head' }, l10n.color ) );

		var strip = el( 'div', { class: 'vgml-swatches' } );

		( cfg.palette || [] ).forEach( function ( value, i ) {

			var on = ( node.color || '' ) === value;

			var dot = el( 'button', {
				type: 'button',
				class: 'vgml-swatch' + ( value ? '' : ' is-none' ) + ( on ? ' is-on' : '' ),
				role: 'menuitemradio',
				'aria-checked': on ? 'true' : 'false',
				'aria-label': names[ i ] || value,
				title: names[ i ] || value,
				style: value ? 'color:' + value : ''
			} );

			dot.addEventListener( 'click', function () {
				menuEl.remove();
				folder( { action: 'color', id: node.id, color: value } );
			} );

			strip.appendChild( dot );
		} );

		menuEl.appendChild( strip );
		root.appendChild( menuEl );

		window.setTimeout( function () {
			document.addEventListener( 'click', function away() {
				menuEl.remove();
				document.removeEventListener( 'click', away );
			} );
		}, 0 );

		anchor.setAttribute( 'aria-expanded', 'true' );
	}

	// Picking a folder is also what arms the toolbar.
	function selectForEditing( id ) {
		editTarget = id > 0 ? id : 0;
		if ( toolbarEls ) {
			var off = ! editTarget;
			toolbarEls.rename.disabled = off;
			toolbarEls.color.disabled = off;
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

		/*
		 *  The selected folder's own actions, above the panel settings. Only when
		 *  a folder is picked: a "Download as ZIP" with nothing to download is a
		 *  question, not a menu item.
		 */
		var target = editTarget > 0 ? state.byId[ editTarget ] : null;

		if ( target && cfg.zipUrl ) {

			menuEl.appendChild( el( 'p', { class: 'vgml-overflow-head' }, target.name ) );

			var zip = el( 'button', { type: 'button', class: 'vgml-overflow-item', role: 'menuitem' }, l10n.downloadZip );
			zip.addEventListener( 'click', function () {
				menuEl.remove();
				window.location.href = cfg.zipUrl
					+ '&folder=' + encodeURIComponent( target.id )
					+ '&taxonomy=' + encodeURIComponent( state.taxonomy );
			} );
			menuEl.appendChild( zip );

			var copy = el( 'button', { type: 'button', class: 'vgml-overflow-item', role: 'menuitem' }, l10n.copyShortcode );
			copy.addEventListener( 'click', function () {
				menuEl.remove();
				var code = '[vergeml_gallery folder="' + target.id + '"]';
				var done = function () { toast( l10n.copied, null ); };
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( code ).then( done, done );
				} else {
					// The old route, for admins served over plain http.
					var scratch = el( 'textarea', { style: 'position:fixed;opacity:0' } );
					scratch.value = code;
					document.body.appendChild( scratch );
					scratch.select();
					try { document.execCommand( 'copy' ); } catch ( e ) { /* the toast still says copied; the string is selected */ }
					scratch.remove();
					done();
				}
			} );
			menuEl.appendChild( copy );

			menuEl.appendChild( el( 'hr', { class: 'vgml-overflow-sep' } ) );
		}

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

		/*
		 *  Density is not a fifth skin.
		 *
		 *  Compact sat directly under the four appearance choices with no break, so
		 *  it read as one of them -- and picking it looked like it would replace the
		 *  skin rather than tighten the rows.
		 */
		menuEl.appendChild( el( 'hr', { class: 'vgml-overflow-sep' } ) );
		menuEl.appendChild( el( 'p', { class: 'vgml-overflow-head' }, l10n.density ) );

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

	/*
	 *  Files dragged in from the DESKTOP can land on a folder row, not just on
	 *  the grid. jQuery UI's droppables never see OS drags -- those arrive as
	 *  native dragover/drop events -- so the tree listens for them itself:
	 *  hovering a folder row lights it, dropping queues the files through the
	 *  grid's own uploader with that row as the one-batch destination.
	 */
	/*
	 *  The uploader files travel through. The grid has one already; the list
	 *  screen does not, so the first files to arrive there get a minimal
	 *  wp.Uploader of our own -- same prototype, so the BeforeUpload folder
	 *  params and the UploadComplete refresh come along for free. On the list
	 *  screen the finished queue reloads the page, because the classic table
	 *  is server-rendered and cannot learn about new rows any other way.
	 */
	var ownUploader = null;

	function anyUploader() {

		var frame = ( wp.media && wp.media.frames && wp.media.frames.browse ) || ( wp.media && wp.media.frame );
		var pl = frame && frame.uploader && frame.uploader.uploader && frame.uploader.uploader.uploader;

		if ( pl ) {
			return pl;
		}

		if ( ownUploader ) {
			return ownUploader.uploader;
		}

		if ( ! window.wp || ! wp.Uploader || ! window._wpPluploadSettings ) {
			return null;
		}

		/*
		 *  wp.Uploader's FilesAdded handler reads settings.post.id, which only
		 *  wp_enqueue_media populates -- and the list screen never calls it.
		 *  Without the stub the handler throws mid-queue and the upload never
		 *  starts.
		 */
		if ( wp.media && wp.media.model ) {
			wp.media.model.settings = wp.media.model.settings || {};
			wp.media.model.settings.post = wp.media.model.settings.post || { id: 0 };
		}

		var browse = el( 'button', { type: 'button', class: 'vgml-hidden-browse' } );
		browse.style.display = 'none';
		document.body.appendChild( browse );

		ownUploader = new wp.Uploader( {
			container: document.body,
			browser: browse,
			dropzone: null
		} );

		if ( ownUploader.uploader && ownUploader.uploader.bind ) {
			ownUploader.uploader.bind( 'UploadComplete', function () {
				window.setTimeout( function () {
					window.location.reload();
				}, 600 );
			} );
		}

		return ownUploader.uploader;
	}

	/*
	 *  Files reach plupload only once its runtime exists. The grid's uploader
	 *  has been ready for ages; one we just built is still initialising, and
	 *  addFile before Init drops the files on the floor.
	 */
	function sendFiles( files ) {
		var pl = anyUploader();
		if ( ! pl ) {
			return false;
		}
		var list = Array.prototype.slice.call( files );
		if ( pl.runtime ) {
			pl.addFile( list );
		} else {
			pl.bind( 'Init', function () {
				pl.addFile( list );
			} );
		}
		return true;
	}

	function armFileDrops( host ) {

		/*
		 *  body.vgml-os-drag marks a native file drag in progress; the
		 *  stylesheet uses it to lift the tree above WP's full-screen drop
		 *  overlay for exactly that long. Cleared by drop and by a short
		 *  timer, because dragleave is unreliable when the cursor exits the
		 *  window -- dragover fires continuously, so the timer staying ahead
		 *  of it is the steady state.
		 */
		var osDragTimer = 0;

		document.addEventListener( 'dragover', function ( e ) {
			if ( ! fileDrag( e ) ) {
				return;
			}
			document.body.classList.add( 'vgml-os-drag' );
			window.clearTimeout( osDragTimer );
			osDragTimer = window.setTimeout( function () {
				document.body.classList.remove( 'vgml-os-drag' );
			}, 400 );
		} );

		document.addEventListener( 'drop', function () {
			window.clearTimeout( osDragTimer );
			document.body.classList.remove( 'vgml-os-drag' );
		}, true );

		function fileDrag( e ) {
			var t = e.dataTransfer && e.dataTransfer.types;
			if ( ! t ) {
				return false;
			}
			for ( var i = 0; i < t.length; i++ ) {
				if ( 'Files' === t[ i ] ) {
					return true;
				}
			}
			return false;
		}

		function rowFor( e ) {
			var node = e.target && e.target.closest ? e.target.closest( '.vgml-node' ) : null;
			var id = node ? parseInt( node.getAttribute( 'data-id' ), 10 ) : 0;
			return id > 0 ? node : null;
		}

		function clearMarks() {
			host.querySelectorAll( '.vgml-row.is-drop' ).forEach( function ( r ) {
				r.classList.remove( 'is-drop' );
			} );
		}

		host.addEventListener( 'dragover', function ( e ) {
			if ( ! fileDrag( e ) ) {
				return;
			}
			/*
			 *  Stopped as well as prevented: the tree lives inside the media
			 *  frame, and the frame's own uploader listens for the same
			 *  events. Left to bubble, its dragover fights the dropEffect --
			 *  and its drop would upload the files a second time.
			 */
			e.preventDefault();
			e.stopPropagation();
			var node = rowFor( e );
			clearMarks();
			if ( node ) {
				e.dataTransfer.dropEffect = 'copy';
				node.querySelector( '.vgml-row' ).classList.add( 'is-drop' );
			} else {
				e.dataTransfer.dropEffect = 'none';
			}
		} );

		host.addEventListener( 'dragleave', function ( e ) {
			if ( ! host.contains( e.relatedTarget ) ) {
				clearMarks();
			}
		} );

		host.addEventListener( 'drop', function ( e ) {
			if ( ! fileDrag( e ) ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			clearMarks();

			// stopping propagation also stopped WP's own overlay-hide handler
			var win = document.querySelector( '.uploader-window' );
			if ( win ) {
				win.style.display = 'none';
			}

			var node = rowFor( e );
			var files = e.dataTransfer.files;
			if ( ! node || ! files || ! files.length ) {
				return;
			}
			dropFolder = parseInt( node.getAttribute( 'data-id' ), 10 );
			if ( ! sendFiles( files ) ) {
				dropFolder = 0;
			}
		} );
	}

	function build() {
		root = el( 'div', { class: 'vgml-tree', 'data-skin': state.skin, 'data-density': state.density } );
		root.style.setProperty( '--vgml-accent', cfg.accent );
		root.style.width = state.width + 'px';
		armFileDrops( root );

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
				// inside the folder being looked at; at the root when none is
				startCreate( state.selected > 0 ? state.selected : 0 );
			} );
			head.appendChild( add );
		}

		/*
		 *  The file-picker road into the selected folder, for everyone who
		 *  does not drag. The button feeds a hidden input; the picked files
		 *  go through the grid's own uploader, so the folder assignment, the
		 *  grid refresh and the count updates are the same code the drop
		 *  zone already runs. The tooltip names the destination the way the
		 *  drop overlay does.
		 */
		var pick = el( 'input', { type: 'file', multiple: 'multiple', class: 'vgml-pick' } );
		pick.style.display = 'none';
		pick.addEventListener( 'change', function () {
			if ( ! pick.files || ! pick.files.length ) {
				return;
			}
			dropFolder = state.selected > 0 ? state.selected : 0;
			if ( ! sendFiles( pick.files ) ) {
				dropFolder = 0;
			}
			pick.value = '';
		} );

		uploadBtn = el( 'button', { type: 'button', class: 'button button-small vgml-upload' }, l10n.upload );
		uploadBtn.addEventListener( 'click', function () {
			if ( ! anyUploader() ) {
				// no uploader can exist on this screen -- the classic page is the fallback
				window.location.href = 'media-new.php';
				return;
			}
			pick.click();
		} );
		head.appendChild( uploadBtn );
		head.appendChild( pick );

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

		/*
		 *  Repaint whenever the list changes height.
		 *
		 *  The window is computed from listEl.clientHeight, and painting now happens
		 *  synchronously during mount -- before the browser has laid anything out,
		 *  so the height is nearly zero and the window comes out at nine rows. There
		 *  was no scroll to correct it and the tree simply stayed nine rows long.
		 *
		 *  Also covers the panel being dragged wider, the density changing, and the
		 *  window being resized, none of which fire a scroll either.
		 */
		if ( window.ResizeObserver ) {
			var lastH = 0;
			new window.ResizeObserver( function () {
				var h = listEl.clientHeight;
				if ( Math.abs( h - lastH ) < 4 ) {
					return;
				}
				lastH = h;
				paint( true );
			} ).observe( listEl );
		} else {
			// No observer: catch the common case, which is the first layout.
			window.requestAnimationFrame( function () { paint( true ); } );
		}
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

		/*
		 *  Two surfaces, and they are not the same job.
		 *
		 *  The library screen has one panel that lives as long as the page. The
		 *  modal has a frame that is built, destroyed and rebuilt every time
		 *  somebody opens it, in any of eight flavours, possibly several times on
		 *  one screen -- so it cannot be mounted once and forgotten.
		 */
		if ( cfg.onLibrary ) {
			whenHostExists( function ( found ) {
				if ( found ) {
					begin();
				}
			} );
		}

		armUploaders();

		/*
		 *  Bulk select, made additive. The heritage grid builds its selection
		 *  with `multiple` off, so core's toggleSelection falls through to
		 *  reset([model]) -- every click REPLACED the selection, and no number
		 *  of clicks ever selected two files. In select mode a plain click
		 *  now toggles membership; shift-ranges pass through untouched.
		 */
		if ( window.wp && wp.media && wp.media.view && wp.media.view.Attachment &&
				! wp.media.view.Attachment.prototype.vgmlToggleWrapped ) {

			wp.media.view.Attachment.prototype.vgmlToggleWrapped = true;

			var vgmlToggle = wp.media.view.Attachment.prototype.toggleSelection;

			wp.media.view.Attachment.prototype.toggleSelection = function ( options ) {
				var inSelect = this.controller && this.controller.isModeActive &&
					this.controller.isModeActive( 'select' );
				if ( inSelect && ( ! options || ! options.method ) ) {
					options = options || {};
					options.method = 'toggle';
				}
				return vgmlToggle.call( this, options );
			};
		}

		/*
		 *  The toolbar labels are screen-reader-only now, which leaves the
		 *  search input a naked box. Its own label already says what it is --
		 *  move those words inside as the placeholder.
		 */
		dropHint();

		var searchTries = 0;
		var searchTimer = window.setInterval( function () {
			var searchBox = document.querySelector( '.media-toolbar .search' );
			if ( searchBox && ! searchBox.placeholder ) {
				var searchLabel = searchBox.id && document.querySelector( 'label[for="' + searchBox.id + '"]' );
				if ( searchLabel ) { searchBox.placeholder = searchLabel.textContent.trim(); }
			}
			// keep retrying for a while: the toolbar renders after boot
			if ( ( searchBox && searchBox.placeholder ) || ++searchTries > 20 ) {
				window.clearInterval( searchTimer );
			}
		}, 500 );

		watchModalsWhenReady();
	}

	/*
	 *  wp.media may not exist yet, and one attempt is not enough.
	 *
	 *  On an admin screen our script declares media-views as a dependency, so it
	 *  is always there by the time this runs. Inside a page builder the script
	 *  order belongs to the builder: Elementor's editor had wp.media, and did not
	 *  have it *yet* at DOMContentLoaded. watchModals returned quietly, nothing
	 *  retried, and the tree was missing from every media modal in the builder --
	 *  with the script loaded and the config present, which is the most confusing
	 *  possible way for it to be absent.
	 */
	function watchModalsWhenReady() {

		var tries = 0;

		( function look() {

			if ( window.wp && wp.media && wp.media.view && wp.media.view.Modal ) {
				watchModals();
				return;
			}

			if ( ++tries > 60 ) { // ~15s, then it is genuinely not a media screen
				return;
			}

			window.setTimeout( look, 250 );
		} )();
	}

	/* --------------------------------------------------------- the modal */

	/*
	 *  Attach to whatever media frame appears, whenever it appears.
	 *
	 *  Not a list of screens: a plugin, a block, a metabox or the next release of
	 *  core can open a media modal anywhere, and a list goes stale the moment one
	 *  of them does. wp.media.view.Modal's own open() is the one thing every
	 *  frame goes through, so it is wrapped -- once -- and the tree is built into
	 *  whatever opened.
	 *
	 *  Wrapped rather than replaced: core's open runs first and unmodified, and if
	 *  anything below throws, the modal has already done its job.
	 */
	function watchModals() {

		if ( ! window.wp || ! wp.media || ! wp.media.view || ! wp.media.view.Modal ) {
			return;
		}

		var proto = wp.media.view.Modal.prototype;

		if ( proto.vgmlWrapped ) {
			return;
		}
		proto.vgmlWrapped = true;

		var open = proto.open;

		proto.open = function () {

			var result = open.apply( this, arguments );
			var frame = this.controller;

			var attach = function () {
				try {
					mountInModal( frame );
				} catch ( e ) {
					// A modal that opens without a tree is worth far more than a
					// modal that does not open.
				}
			};

			/*
			 *  Attach whenever a library appears, not once when the modal opens.
			 *
			 *  Opening is the wrong moment: a frame can open on the Upload Files
			 *  tab -- which is what a fresh "Select" frame does -- and there is no
			 *  library to attach to until somebody switches tabs. Trying once and
			 *  giving up meant the tree existed only in whichever context happened
			 *  to open on the library, which is precisely the failure this phase is
			 *  about.
			 *
			 *  content:render:browse is core's own signal that a browser has just
			 *  been built, and it fires again on every tab switch and re-render.
			 */
			if ( frame && typeof frame.on === 'function' ) {

				frame.on( 'content:render:browse', function () {
					window.setTimeout( attach, 40 );
				} );
				frame.on( 'open ready', function () {
					window.setTimeout( attach, 40 );
				} );

				/*
				 *  Take the panel down when the modal closes.
				 *
				 *  wp.media does not destroy a frame on close, it detaches it -- the
				 *  markup stays in the document, and a page where somebody has picked
				 *  an image a dozen times is holding a dozen dead modals. Without
				 *  this, each one keeps a tree of up to three hundred rows: the row
				 *  count climbed 300, 600, 900, 1200 across four opens in the test,
				 *  which is what made it visible.
				 */
				frame.on( 'close', function () {
					try {
						var stale = frame.$el.find( '.vgml-modal-tree' );
						if ( stale && stale.length ) {
							stale.remove();
						}
					} catch ( e ) { /* the frame is already gone */ }
				} );
			}

			window.setTimeout( attach, 60 );

			return result;
		};
	}

	function mountInModal( frame ) {

		if ( ! frame || typeof frame.$el === 'undefined' ) {
			return;
		}

		var content = frame.$el.find( '.media-frame-content' )[ 0 ];

		if ( ! content ) {
			return; // an upload-only or details-only frame; nothing to filter
		}

		var browser = frame.$el.find( '.attachments-browser' )[ 0 ];

		if ( ! browser ) {
			return; // no library on this frame -- image details, audio details
		}

		if ( content.querySelector( '.vgml-modal-tree' ) ) {
			return; // already mounted on this frame
		}

		var panel = buildModalPanel( frame );
		content.insertBefore( panel, content.firstChild );
		content.classList.add( 'vgml-modal-host' );
	}

	/*
	 *  A tree for one modal frame.
	 *
	 *  It shares the folder data -- the folders are the same folders -- but keeps
	 *  its own selection and its own DOM, because a modal is opened to pick a file
	 *  and its filter has nothing to do with what the library screen behind it is
	 *  showing. It also always starts at All files: the folder somebody happened to
	 *  browse an hour ago is rarely the one they want when inserting an image, and
	 *  a modal that opens showing three of twenty thousand files, with no visible
	 *  reason, is a support ticket.
	 *
	 *  No folder editing here, by decision. Renaming and deleting belong on the
	 *  library screen; a delete confirm inside a modal is a dialog inside a dialog.
	 *  Files can still be dragged in, which is the one genuinely useful thing --
	 *  filing something the moment it is uploaded.
	 */
	function modalTree( frame ) {

		var box = el( 'div', { class: 'vgml-tree vgml-in-modal', 'data-skin': state.skin, 'data-density': state.density } );
		box.style.setProperty( '--vgml-accent', cfg.accent );

		// On a screen with no library tree, nothing has loaded the folders yet.
		if ( ! state.nodes.length && cfg.boot && cfg.boot.nodes ) {
			state.nodes = cfg.boot.nodes;
			state.unassigned = cfg.boot.unassigned || 0;
			index();
		}

		var find = el( 'div', { class: 'vgml-find' } );
		var search = el( 'input', {
			type: 'search',
			class: 'vgml-search',
			placeholder: l10n.search,
			'aria-label': l10n.search
		} );
		find.appendChild( search );
		box.appendChild( find );

		var list = el( 'ul', { class: 'vgml-list', role: 'tree', 'aria-label': l10n.folders } );
		box.appendChild( list );

		var chosen = 0;   // always All files when the modal opens
		var filter = '';

		function rowsFor() {
			// flatten() reads state.filter, so it is set for the call and restored:
			// the library screen's own search must not move because a modal opened.
			var was = state.filter;
			state.filter = filter;
			var out = flatten();
			state.filter = was;
			// The modal exists to pick a file, not to audit the library; the
			// smart rows would also start scans from inside a picker.
			return out.filter( function ( entry ) {
				return 'smart' !== entry.pseudo;
			} );
		}

		function draw() {

			var rows = rowsFor();
			list.innerHTML = '';

			/*
			 *  Capped rather than windowed. The modal list is short and scrolls, and
			 *  a scroll-driven window here would need its own observer on an element
			 *  that is destroyed every time the modal closes. Past the cap the search
			 *  box is the way through -- which is what anyone with two thousand
			 *  folders reaches for anyway.
			 */
			var cap = 300;
			var shown = 0;

			rows.forEach( function ( entry ) {

				if ( shown >= cap ) {
					return;
				}
				shown++;

				var isPseudo = !! entry.pseudo;
				var id = entry.id;

				var item = el( 'li', {
					class: 'vgml-node' + ( isPseudo ? ' vgml-pseudo' : '' ) + ( chosen === id ? ' is-selected' : '' ),
					role: 'treeitem',
					'aria-level': entry.depth + 1,
					'aria-selected': chosen === id ? 'true' : 'false',
					'data-id': id,
					tabindex: '-1'
				} );

				var row = el( 'div', { class: 'vgml-row', style: '--vgml-indent:' + ( entry.depth * 19 + 8 ) + 'px' } );

				var twist = el( 'button', {
					class: 'vgml-twist' + ( ! isPseudo && entry.kids ? '' : ' is-leaf' ),
					type: 'button',
					tabindex: '-1',
					'aria-hidden': 'true'
				} );

				if ( ! isPseudo && entry.kids ) {
					item.setAttribute( 'aria-expanded', entry.open ? 'true' : 'false' );
					twist.innerHTML = chevron();
					if ( entry.open ) { twist.classList.add( 'is-open' ); }
					twist.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						state.open[ id ] = ! state.open[ id ];
						draw();
					} );
				}
				row.appendChild( twist );

				row.appendChild( isPseudo
					? pseudoIcon( entry.pseudo )
					: folderIcon( entry.node.color, entry.open && entry.kids, ! entry.total ) );
				// (smart rows never reach here; the modal filters them out)

				row.appendChild( el( 'span', { class: 'vgml-name' }, isPseudo ? entry.label : entry.node.name ) );

				var count = isPseudo ? entry.count : entry.total;
				if ( count ) {
					row.appendChild( el( 'span', { class: 'vgml-count' }, String( count ) ) );
				}

				row.addEventListener( 'click', function () {
					if ( justDragged ) {
						return;
					}
					chosen = id;
					filterFrame( frame, id );
					draw();
				} );

				// Files can be dropped in; folders cannot be dragged out.
				if ( ! isPseudo ) {
					dropTarget( row, id );
				} else if ( entry.pseudo === 'unassigned' ) {
					unfileTarget( row );
				}

				item.appendChild( row );
				list.appendChild( item );
			} );

			if ( shown >= cap ) {
				list.appendChild( el( 'li', { class: 'vgml-empty', role: 'none' },
					sprintf( l10n.moreFolders, rows.length - cap ) ) );
			}
		}

		search.addEventListener( 'input', function () {
			filter = search.value.toLowerCase();
			draw();
		} );

		draw();
		return box;
	}

	/*
	 *  Point one frame's library at a folder.
	 *
	 *  The same props the modal's own dropdown sets, on that frame's own library --
	 *  not the global one. Several modals can exist on a screen and they must not
	 *  filter each other.
	 */
	function filterFrame( frame, id ) {

		// The modal's Upload Files tab files into whichever folder its Media
		// Library tab is showing -- the same rule as the library screen.
		uploadTarget = id > 0 ? id : 0;

		var props = null;

		try {
			var s = frame.state();
			var lib = s && typeof s.get === 'function' ? s.get( 'library' ) : null;
			props = lib && lib.props ? lib.props : null;
		} catch ( e ) {
			props = null;
		}

		if ( ! props ) {
			return;
		}

		var next = {};

		if ( id === -1 ) {
			next[ state.taxonomy ] = null;
			next.uncategorized = 1;
		} else if ( id === 0 ) {
			next[ state.taxonomy ] = null;
			next.uncategorized = null;
		} else {
			next[ state.taxonomy ] = id;
			next.uncategorized = null;
		}

		props.set( next );
	}

	function buildModalPanel( frame ) {

		var wrap = el( 'div', { class: 'vgml-modal-tree' } );

		/*
		 *  A modal is about 700px of usable width, so the panel collapses -- and
		 *  remembers, because someone who wants it out of the way wants it out of
		 *  the way every time.
		 */
		var collapsed = window.localStorage && window.localStorage.getItem( 'vgmlModalCollapsed' ) === '1';

		var toggle = el( 'button', {
			type: 'button',
			class: 'vgml-modal-toggle',
			'aria-expanded': collapsed ? 'false' : 'true'
		} );
		toggle.innerHTML = '<span aria-hidden="true">' + ( collapsed ? '&#9656;' : '&#9666;' ) + '</span>';
		toggle.setAttribute( 'aria-label', l10n.folders );

		toggle.addEventListener( 'click', function () {
			var now = wrap.classList.toggle( 'is-collapsed' );
			toggle.setAttribute( 'aria-expanded', now ? 'false' : 'true' );
			toggle.firstChild.innerHTML = now ? '&#9656;' : '&#9666;';
			try {
				window.localStorage.setItem( 'vgmlModalCollapsed', now ? '1' : '0' );
			} catch ( e ) { /* private browsing */ }
		} );

		if ( collapsed ) {
			wrap.classList.add( 'is-collapsed' );
		}

		wrap.appendChild( toggle );
		wrap.appendChild( modalTree( frame ) );

		return wrap;
	}


	function begin() {
		if ( ! mount() ) {
			return;
		}

		/*
		 *  Painted from what PHP already sent, so opening the library shows the
		 *  tree immediately and never fetches it. The endpoint is still there for
		 *  everything after this first paint.
		 */
		if ( cfg.boot && cfg.boot.nodes ) {
			state.nodes = cfg.boot.nodes;
			state.unassigned = cfg.boot.unassigned || 0;
			state.smart = cfg.boot.smart || [];
			index();
			render();
		} else {
			load();
		}

		// The remembered selection catches uploads from the first moment, not
		// only after the next click.
		uploadTarget = state.selected > 0 ? state.selected : 0;

		/*
		 *  Grid view builds itself asynchronously, so the frame may arrive after we
		 *  do; re-selecting makes the library reflect the folder already
		 *  highlighted in the tree.
		 *
		 *  Only in grid. In list view the server has already rendered the right
		 *  rows from the query string, and re-selecting there started a table swap
		 *  on every single page load -- a pointless fetch that also faded the table
		 *  for its duration.
		 */
		if ( state.selected > 0 && ! document.querySelector( '.wp-list-table' ) && window.wp && wp.media ) {
			setTimeout( function () { select( state.selected ); }, 500 );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
