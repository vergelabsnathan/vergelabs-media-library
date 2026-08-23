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

	function render() {
		if ( ! listEl ) {
			return;
		}

		var total = totals();
		listEl.innerHTML = '';
		listEl.setAttribute( 'role', 'tree' );
		listEl.setAttribute( 'aria-label', l10n.folders );

		listEl.appendChild( pseudo( 'all', l10n.all, null ) );
		listEl.appendChild( pseudo( 'unassigned', l10n.unassigned, state.unassigned ) );

		var shown = 0;

		function branch( parentId, depth, into ) {
			( state.children[ parentId ] || [] ).forEach( function ( node ) {
				if ( ! visible( node ) ) {
					return;
				}
				shown++;
				var kids = ( state.children[ node.id ] || [] ).filter( visible );
				// While searching, open everything on the way to a match.
				var open = state.filter ? true : !! state.open[ node.id ];

				var item = el( 'li', {
					class: 'vgml-node' + ( state.selected === node.id ? ' is-selected' : '' ),
					role: 'treeitem',
					'aria-level': depth + 1,
					'aria-selected': state.selected === node.id ? 'true' : 'false',
					'data-id': node.id,
					tabindex: '-1'
				} );

				if ( kids.length ) {
					item.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				}

				var row = el( 'div', { class: 'vgml-row', style: '--vgml-indent:' + ( depth * 14 + 8 ) + 'px' } );

				var twist = el( 'button', {
					class: 'vgml-twist' + ( kids.length ? '' : ' is-leaf' ),
					type: 'button',
					tabindex: '-1',
					'aria-hidden': 'true'
				} );
				twist.innerHTML = kids.length ? ( open ? '&#9662;' : '&#9656;' ) : '';
				twist.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					toggle( node.id );
				} );
				row.appendChild( twist );

				var dot = el( 'span', { class: 'vgml-dot', 'aria-hidden': 'true' } );
				if ( node.color ) {
					dot.style.background = node.color;
				}
				row.appendChild( dot );

				row.appendChild( el( 'span', { class: 'vgml-name' }, node.name ) );
				row.appendChild( el( 'span', { class: 'vgml-count' }, String( total[ node.id ] ) ) );

				if ( cfg.canManage ) {
					var kebab = el( 'button', {
						class: 'vgml-kebab',
						type: 'button',
						tabindex: '-1',
						'aria-label': node.name
					} );
					kebab.innerHTML = '&#8942;';
					kebab.addEventListener( 'click', function ( e ) {
						e.stopPropagation();
						menu( node );
					} );
					row.appendChild( kebab );
				}

				row.addEventListener( 'click', function () {
					select( node.id );
				} );

				dropTarget( row, node.id );

				item.appendChild( row );

				if ( kids.length && open ) {
					var group = el( 'ul', { role: 'group', class: 'vgml-group' } );
					branch( node.id, depth + 1, group );
					item.appendChild( group );
				}

				into.appendChild( item );
			} );
		}

		branch( 0, 0, listEl );

		if ( state.filter && shown === 0 ) {
			listEl.appendChild( el( 'li', { class: 'vgml-empty', role: 'none' }, l10n.nothingFound ) );
		}

		root.setAttribute( 'data-skin', state.skin );
		root.setAttribute( 'data-density', state.density );
	}

	function pseudo( key, label, count ) {
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
		row.appendChild( el( 'span', { class: 'vgml-dot is-none', 'aria-hidden': 'true' } ) );
		row.appendChild( el( 'span', { class: 'vgml-name' }, label ) );
		if ( count !== null ) {
			row.appendChild( el( 'span', { class: 'vgml-count' }, String( count ) ) );
		}
		row.addEventListener( 'click', function () {
			select( key === 'all' ? 0 : -1 );
		} );
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

	function onKey( e ) {
		var all = rows();
		var current = document.activeElement;
		var i = all.indexOf( current );

		if ( i === -1 ) {
			return;
		}

		var id = parseInt( current.getAttribute( 'data-id' ), 10 );

		switch ( e.key ) {
			case 'ArrowDown':
				e.preventDefault();
				if ( all[ i + 1 ] ) { focusRow( all[ i + 1 ] ); }
				break;
			case 'ArrowUp':
				e.preventDefault();
				if ( all[ i - 1 ] ) { focusRow( all[ i - 1 ] ); }
				break;
			case 'ArrowRight':
				e.preventDefault();
				if ( current.getAttribute( 'aria-expanded' ) === 'false' ) {
					toggle( id );
					restoreFocus( id );
				} else if ( all[ i + 1 ] ) {
					focusRow( all[ i + 1 ] );
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
				focusRow( all[ 0 ] );
				break;
			case 'End':
				e.preventDefault();
				focusRow( all[ all.length - 1 ] );
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

	// render() rebuilds the list, so focus has to be put back by id rather than
	// by holding a reference to an element that no longer exists.
	function restoreFocus( id ) {
		var next = listEl.querySelector( '[data-id="' + id + '"]' );
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

	function dropTarget( row, termId ) {
		row.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			row.classList.add( 'is-drop' );
			// Ctrl or Alt turns an add into a move; the pointer says so.
			e.dataTransfer.dropEffect = ( e.ctrlKey || e.altKey ) ? 'move' : 'copy';
		} );
		row.addEventListener( 'dragleave', function () {
			row.classList.remove( 'is-drop' );
		} );
		row.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			row.classList.remove( 'is-drop' );

			var ids = dragging && dragging.length ? dragging : selectionIds();
			if ( ! ids.length ) {
				return;
			}

			assign( ids, termId, e.ctrlKey || e.altKey );
			dragging = null;
		} );
	}

	function assign( ids, termId, move ) {
		apiFetch( {
			path: '/vergeml/v1/assign',
			method: 'POST',
			data: {
				taxonomy: state.taxonomy,
				attachments: ids,
				add: [ termId ],
				mode: move ? 'move' : 'add'
			}
		} ).then( function ( res ) {
			state.lastUndo = res.undo;
			load();
			toast( sprintf( l10n.undoAssigned, ( res.changed || [] ).length ), res.undo ? undo : null );
		} ).catch( function () {
			toast( l10n.failed, null );
		} );
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
		} ).then( function () {
			load();
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

	function rename( node ) {
		var name = window.prompt( sprintf( l10n.renamePrompt, node.name ), node.name );
		if ( name && name !== node.name ) {
			folder( { action: 'rename', id: node.id, name: name } );
		}
	}

	function menu( node ) {
		// Deliberately plain. A bespoke context menu is a second focus trap to
		// get right, and this phase has a keyboard contract to honour first.
		var choice = window.prompt(
			node.name + '\n\n1 = ' + l10n.newFolder +
			'\n2 = ' + l10n.rename +
			'\n3 = ' + l10n.color +
			'\n4 = ' + l10n.delete,
			'1'
		);

		if ( choice === '1' ) {
			var name = window.prompt( l10n.namePrompt, '' );
			if ( name ) {
				folder( { action: 'create', name: name, parent: node.id } );
			}
		} else if ( choice === '2' ) {
			rename( node );
		} else if ( choice === '3' ) {
			var colour = window.prompt( l10n.color + ' (' + cfg.palette.filter( Boolean ).join( ' ' ) + ')', node.color || '' );
			if ( colour !== null ) {
				folder( { action: 'color', id: node.id, color: colour } );
			}
		} else if ( choice === '4' ) {
			var kids = ( state.children[ node.id ] || [] ).length;
			var msg = kids
				? sprintf( l10n.deleteConfirm, node.name, kids )
				: sprintf( l10n.deleteSimple, node.name );
			if ( window.confirm( msg ) ) {
				folder( { action: 'delete', id: node.id } );
			}
		}
	}

	/* ----------------------------------------------------------- the shell */

	function build() {
		root = el( 'div', { class: 'vgml-tree', 'data-skin': state.skin, 'data-density': state.density } );
		root.style.setProperty( '--vgml-accent', cfg.accent );
		root.style.width = state.width + 'px';

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
				load();
			} );
			head.appendChild( pick );
		} else {
			head.appendChild( el( 'span', { class: 'vgml-title' }, cfg.taxonomies[ 0 ].label ) );
		}

		if ( cfg.canManage ) {
			var add = el( 'button', { type: 'button', class: 'vgml-new', 'aria-label': l10n.newFolder }, '+' );
			add.addEventListener( 'click', function () {
				var name = window.prompt( l10n.namePrompt, '' );
				if ( name ) {
					folder( { action: 'create', name: name, parent: 0 } );
				}
			} );
			head.appendChild( add );
		}

		root.appendChild( head );

		searchEl = el( 'input', { type: 'search', class: 'vgml-search', placeholder: l10n.search, 'aria-label': l10n.search } );
		searchEl.addEventListener( 'input', function () {
			state.filter = searchEl.value.toLowerCase();
			render();
		} );
		root.appendChild( searchEl );

		listEl = el( 'ul', { class: 'vgml-list', role: 'tree' } );
		listEl.addEventListener( 'keydown', onKey );
		root.appendChild( listEl );

		var skinBar = el( 'div', { class: 'vgml-skins' } );
		[ 'native', 'classic', 'minimal', 'contrast' ].forEach( function ( s ) {
			var b = el( 'button', { type: 'button', class: 'vgml-skin', 'data-skin': s, title: l10n[ 'skin' + s.charAt( 0 ).toUpperCase() + s.slice( 1 ) ] || s } );
			b.addEventListener( 'click', function () {
				state.skin = s;
				render();
				persist( { skin: s } );
			} );
			skinBar.appendChild( b );
		} );
		var dens = el( 'button', { type: 'button', class: 'vgml-density' } );
		dens.textContent = '≡';
		dens.addEventListener( 'click', function () {
			state.density = state.density === 'compact' ? 'comfortable' : 'compact';
			render();
			persist( { density: state.density } );
		} );
		skinBar.appendChild( dens );
		root.appendChild( skinBar );

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
		var host = document.querySelector( '.wp-list-table' ) || document.querySelector( '.media-frame' );
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

		if ( host.classList.contains( 'media-frame' ) ) {
			document.body.classList.add( 'vgml-mode-grid' );
		}

		setPanelWidth( state.width );

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
	function whenHostExists( done ) {
		var tries = 0;

		( function look() {
			if ( document.querySelector( '.wp-list-table' ) || document.querySelector( '.media-frame' ) ) {
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
