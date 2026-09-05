/*
 *  Folders: one conversation, one tree, one Move.
 *
 *  Two columns. On the left, a segmented switch between two ways of building
 *  the same draft: the conversation, streamed word by word straight from the
 *  service with a token core/guide.php mints, and the Rules -- four
 *  deterministic ways that cost no model call. On the right, the shared tree
 *  (js/vergeml-tree-view.js) drawing today's folders with the draft laid over
 *  them, and the one button that moves pictures, in its three states.
 *
 *  Everything the first paint needs came with the page (vgmlFolders): no
 *  request stands between the page and the tree. The session persists each
 *  finished turn and the draft, keyed by term id, so a reload shows the same
 *  conversation and the same tree.
 *
 *  Plain JavaScript, no build step, as everything else in this plugin.
 */
( function () {
	'use strict';

	var cfg = window.vgmlFolders || {};
	var TV = window.vergemlTreeView;
	var wp = window.wp;
	if ( ! TV || ! wp || ! wp.apiFetch || ! wp.i18n ) {
		return;
	}
	var __ = wp.i18n.__;
	var _n = wp.i18n._n;
	var sprintf = wp.i18n.sprintf;
	var el = TV.el;
	var fmt = TV.fmt;

	function api( method, route, data ) {
		var o = { path: '/' + ( cfg.ns || 'vergeml/v1' ) + '/' + route, method: method };
		if ( data ) {
			o.data = data;
		}
		return wp.apiFetch( o );
	}

	function lower( s ) {
		return String( s || '' ).toLowerCase().trim();
	}

	/* ------------------------------------------------------------- state */

	var state = {
		session: cfg.session || { turns: [], draft: null, assistant_turns: 0, cap: cfg.cap || 25, apply: null },
		nodes: cfg.nodes || [],
		version: cfg.version || 0,
		undo: cfg.undo || { available: false, until: 0 },
		method: 'talk',
		token: null,
		stream: null,
		rules: null,
		rule: null,
		preview: [],
		moving: null,
		note: ''
	};
	var described = ( cfg.described || 0 ) > 0;
	var licensed = !! cfg.licensed;
	var queue = Promise.resolve();

	/** Writes to the session go one after another, in the order they happened. */
	function persist( route, body ) {
		queue = queue.then( function () {
			return api( 'POST', route, body ).then( function ( r ) {
				if ( r && r.turns ) {
					state.session.turns = r.turns;
					state.session.assistant_turns = r.assistant_turns;
				}
				return r;
			} );
		}, function () {} );
		return queue;
	}

	function capped() {
		return ( state.session.assistant_turns || 0 ) >= ( state.session.cap || cfg.cap || 25 );
	}

	function canTalk() {
		return described && licensed && ! capped() && ! ( state.session.apply && state.session.apply.running );
	}

	/* --------------------------------------------------------------- DOM */

	var root = document.getElementById( 'vgml-folders' );
	if ( ! root ) {
		return;
	}

	var dom = {};

	function build() {
		var cols = el( 'div', { class: 'vgml-folders-cols' } );

		// Left: the method, the conversation, the composer; or the rules.
		var left = el( 'div', { class: 'vgml-folders-left' } );
		var method = el( 'div', { class: 'vgml-method' } );
		var seg = el( 'div', { class: 'vgml-seg', role: 'tablist', 'aria-label': __( 'How to build the tree', 'vergelabs-media-library' ) } );
		dom.tabTalk = el( 'button', { type: 'button', role: 'tab', class: 'vgml-seg-tab', 'data-method': 'talk', 'aria-selected': 'true' }, __( 'Conversation', 'vergelabs-media-library' ) );
		dom.tabRules = el( 'button', { type: 'button', role: 'tab', class: 'vgml-seg-tab', 'data-method': 'rules', 'aria-selected': 'false' }, __( 'Rules', 'vergelabs-media-library' ) );
		seg.appendChild( dom.tabTalk );
		seg.appendChild( dom.tabRules );
		dom.tabTalk.addEventListener( 'click', function () { setMethod( 'talk' ); } );
		dom.tabRules.addEventListener( 'click', function () { setMethod( 'rules' ); } );
		method.appendChild( seg );
		dom.kicker = el( 'span', { class: 'vgml-kicker vgml-method-kicker' } );
		method.appendChild( dom.kicker );
		left.appendChild( method );

		dom.conv = el( 'div', { class: 'vgml-conv', role: 'log', 'aria-live': 'polite' } );
		left.appendChild( dom.conv );

		dom.composer = el( 'div', { class: 'vgml-composer' } );
		dom.text = el( 'textarea', { class: 'vgml-composer-text', rows: '1', placeholder: __( 'Describe the change', 'vergelabs-media-library' ), 'aria-label': __( 'Describe the change', 'vergelabs-media-library' ) } );
		dom.composer.appendChild( dom.text );
		var bar = el( 'div', { class: 'vgml-composer-bar' } );
		dom.hint = el( 'small', { class: 'vgml-composer-hint' } );
		bar.appendChild( dom.hint );
		dom.send = el( 'button', { type: 'button', class: 'vgml-send', 'aria-label': __( 'Send', 'vergelabs-media-library' ) } );
		dom.send.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg><i></i>';
		bar.appendChild( dom.send );
		dom.composer.appendChild( bar );
		left.appendChild( dom.composer );

		dom.text.addEventListener( 'input', growComposer );
		dom.text.addEventListener( 'keydown', function ( e ) {
			if ( 'Enter' === e.key && ! e.shiftKey ) {
				e.preventDefault();
				send();
			}
		} );
		dom.send.addEventListener( 'click', function () {
			if ( state.stream ) {
				stop();
			} else {
				send();
			}
		} );

		dom.rules = el( 'div', { class: 'vgml-rules', role: 'radiogroup', 'aria-label': __( 'Rules', 'vergelabs-media-library' ), hidden: 'hidden' } );
		left.appendChild( dom.rules );
		dom.preview = el( 'ul', { class: 'vgml-facts vgml-preview', hidden: 'hidden' } );
		left.appendChild( dom.preview );

		// Right: the tree and Move.
		var right = el( 'div', { class: 'vgml-folders-right' } );
		dom.treeKicker = el( 'h2', { class: 'vgml-kicker vgml-tree-kicker' } );
		dom.tree = el( 'div', { class: 'vgml-folders-tree' } );
		right.appendChild( dom.tree );
		var move = el( 'div', { class: 'vgml-folders-move' } );
		dom.move = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-primary vgml-move-btn' } );
		dom.stop = el( 'button', { type: 'button', class: 'vgml-btn vgml-move-stop', hidden: 'hidden' }, __( 'Stop', 'vergelabs-media-library' ) );
		dom.undo = el( 'button', { type: 'button', class: 'vgml-btn vgml-move-undo', hidden: 'hidden' } );
		move.appendChild( dom.move );
		move.appendChild( dom.stop );
		move.appendChild( dom.undo );
		right.appendChild( move );
		dom.move.addEventListener( 'click', onMove );
		dom.stop.addEventListener( 'click', onStop );
		dom.undo.addEventListener( 'click', onUndo );

		cols.appendChild( left );
		cols.appendChild( right );
		root.appendChild( cols );
	}

	function growComposer() {
		dom.text.style.height = 'auto';
		dom.text.style.height = Math.min( 240, dom.text.scrollHeight ) + 'px';
	}

	/* ------------------------------------------------------------ the tree */

	var view = null;
	var watcher = null;

	function treeL10n() {
		return {
			changes: __( 'Changes', 'vergelabs-media-library' ),
			all: __( 'All', 'vergelabs-media-library' ),
			states: __( 'Which folders to show', 'vergelabs-media-library' ),
			find: __( 'Find a folder', 'vergelabs-media-library' ),
			topLevel: __( 'Top level', 'vergelabs-media-library' ),
			newTag: __( 'new', 'vergelabs-media-library' ),
			/* translators: %s: a number of pictures */
			was: __( 'was %s', 'vergelabs-media-library' ),
			/* translators: 1: pictures, 2: a folder name */
			removedTo: __( 'removed · %1$s pictures go to %2$s', 'vergelabs-media-library' ),
			/* translators: %s: pictures */
			removedNowhere: __( 'removed · %s pictures go to no folder', 'vergelabs-media-library' ),
			/* translators: %s: a folder name */
			movedFrom: __( 'moved from %s', 'vergelabs-media-library' ),
			movedFromTop: __( 'moved from the top level', 'vergelabs-media-library' ),
			byYou: __( ', by you', 'vergelabs-media-library' ),
			/* translators: %s: the old name */
			renamedFrom: __( 'renamed from %s', 'vergelabs-media-library' ),
			folder1: __( '1 folder', 'vergelabs-media-library' ),
			/* translators: %s: folders */
			folderN: __( '%s folders', 'vergelabs-media-library' ),
			change1: __( '1 change', 'vergelabs-media-library' ),
			/* translators: %s: changes */
			changeN: __( '%s changes', 'vergelabs-media-library' ),
			more1: __( '1 more folder, unchanged', 'vergelabs-media-library' ),
			/* translators: %s: folders */
			moreN: __( '%s more folders, unchanged', 'vergelabs-media-library' ),
			/* translators: %s: pictures */
			afterMove: __( '%s pictures after Move', 'vergelabs-media-library' ),
			/* translators: 1: pictures, 2: folder names */
			fromFolders: __( '%1$s from %2$s', 'vergelabs-media-library' ),
			/* translators: %s: pictures */
			ofN: __( 'of %s', 'vergelabs-media-library' ),
			noChanges: __( 'No changes yet', 'vergelabs-media-library' ),
			nothingFound: __( 'No folder matches', 'vergelabs-media-library' ),
			rename: __( 'Rename', 'vergelabs-media-library' ),
			remove: __( 'Remove from the draft', 'vergelabs-media-library' )
		};
	}

	function makeTree() {
		view = TV.create( {
			surface: 'folders',
			root: dom.tree,
			nodes: state.nodes,
			indent: { step: 22, base: 0 },
			editable: true,
			l10n: treeL10n(),
			onEdit: onHandEdit
		} );
		if ( state.session.draft ) {
			view.setDraft( state.session.draft );
		} else {
			view.render();
		}
		// The kicker sits at the head of the tree, before the two-state switch.
		var head = dom.tree.querySelector( '.vgml-tv-head' );
		if ( head ) {
			head.insertBefore( dom.treeKicker, head.firstChild );
		}
		watcher = TV.watchVersion( {
			path: '/' + ( cfg.ns || 'vergeml/v1' ) + '/folders/version',
			version: state.version,
			onChange: function ( v ) {
				state.version = v;
				// While a Move runs the folders are half-made; the tree is re-read once, when it ends.
				if ( state.session.apply && state.session.apply.running ) {
					return;
				}
				refreshTree();
			}
		} );
	}

	function refreshTree() {
		return api( 'GET', 'tree?taxonomy=' + encodeURIComponent( cfg.taxonomy || 'media_category' ) ).then( function ( r ) {
			state.nodes = ( r && r.nodes ) || [];
			view.setTree( state.nodes );
			renderTreeHead();
			renderMove();
			var draft = view.getDraft();
			if ( draft && state.session.draft ) {
				state.session.draft = withOrigin( draft, state.session.draft );
				persistDraft( state.session.draft );
			}
		} ).catch( function () {} );
	}

	function renderTreeHead() {
		var s = view.summary();
		dom.treeKicker.textContent = view.getDraft()
			/* translators: 1: folders now, 2: folders after Move */
			? sprintf( __( 'Folders · %1$s now, %2$s after Move', 'vergelabs-media-library' ), fmt( s.now ), fmt( s.after ) )
			/* translators: %s: folders */
			: sprintf( __( 'Folders · %s', 'vergelabs-media-library' ), fmt( s.now ) );
	}

	/** The component's draft plus what the session remembers about where it came from. */
	function withOrigin( draft, prev ) {
		var out = { folders: draft.folders, gone: draft.gone, tags: ( prev && prev.tags ) || [], origin: ( prev && prev.origin ) || 'talk', rule: ( prev && prev.rule ) || null };
		return out;
	}

	var persistTimer = null;
	function persistDraft( draft ) {
		state.session.draft = draft;
		window.clearTimeout( persistTimer );
		persistTimer = window.setTimeout( function () {
			persist( 'guide/session', { draft: draft } );
		}, 250 );
	}

	function setDraft( draft, quiet ) {
		state.session.draft = draft;
		view.setDraft( draft );
		renderTreeHead();
		renderMove();
		if ( ! quiet ) {
			persistDraft( draft );
		}
	}

	/* ----------------------------------------------- the model's tree, keyed */

	/*
	 *  The model speaks names; the draft is keyed by term id. A folder that
	 *  carries the id it was given is that folder; one that lost its id is
	 *  matched by its exact path; what is left is new. A live folder the
	 *  reply does not name is gone, its pictures going to the nearest kept
	 *  ancestor -- the parent that absorbed it -- or to no folder.
	 */
	function resolveTree( tree ) {
		var live = new TV.Model( state.nodes );
		var byPath = {};
		state.nodes.forEach( function ( n ) {
			byPath[ live.pathNames( n.id ).map( lower ).join( '/' ) ] = n.id;
		} );
		var folders = ( tree && tree.folders ) || [];
		var prev = state.session.draft && state.session.draft.folders ? state.session.draft.folders : [];
		var byName = {};
		folders.forEach( function ( f, i ) {
			var n = lower( f.name );
			if ( byName[ n ] === undefined ) {
				byName[ n ] = i;
			}
		} );
		function pathOf( i ) {
			var names = [];
			var guard = 0;
			var at = i;
			while ( at !== undefined && guard++ < 64 ) {
				names.unshift( lower( folders[ at ].name ) );
				var p = lower( folders[ at ].parent );
				at = p && byName[ p ] !== undefined && byName[ p ] !== at ? byName[ p ] : undefined;
			}
			return names.join( '/' );
		}
		var used = {};
		var keys = [];
		folders.forEach( function ( f, i ) {
			var id = Number( f.id ) || 0;
			if ( ! id || ! live.byId[ id ] || used[ id ] ) {
				id = 0;
				var path = pathOf( i );
				if ( byPath[ path ] && ! used[ byPath[ path ] ] ) {
					id = byPath[ path ];
				}
			}
			if ( id ) {
				used[ id ] = true;
			}
			var key = id ? 't' + id : '';
			if ( ! key ) {
				prev.forEach( function ( g ) {
					if ( ! key && ! g.term_id && lower( g.name ) === lower( f.name ) ) {
						key = g.key;
					}
				} );
			}
			keys[ i ] = { key: key || ( 'n' + Date.now().toString( 36 ) + i ), term_id: id || null };
		} );
		var out = { folders: [], gone: {}, tags: ( tree && tree.tags ) || [], origin: 'talk', rule: null };
		folders.forEach( function ( f, i ) {
			var p = lower( f.parent );
			var pi = p && byName[ p ] !== undefined && byName[ p ] !== i ? byName[ p ] : -1;
			var count = f.count !== undefined && f.count !== null ? Number( f.count ) : null;
			var node = keys[ i ].term_id ? live.byId[ keys[ i ].term_id ] : null;
			if ( node && ( null === count || count === ( node.count || 0 ) ) ) {
				count = null;
			}
			out.folders.push( {
				key: keys[ i ].key,
				term_id: keys[ i ].term_id,
				name: String( f.name || '' ).replace( /\//g, '-' ),
				parent: pi >= 0 ? keys[ pi ].key : '',
				count: count,
				matches: f.matches || '',
				classes: f.classes || [],
				kinds: f.kinds || [],
				audience: f.audience || '',
				by: ''
			} );
		} );
		state.nodes.forEach( function ( n ) {
			if ( used[ n.id ] ) {
				return;
			}
			var at = n.parent;
			var guard = 0;
			while ( at && ! used[ at ] && live.byId[ at ] && guard++ < 64 ) {
				at = live.byId[ at ].parent;
			}
			out.gone[ n.id ] = at && used[ at ] ? 't' + at : '';
		} );
		return out;
	}

	/** The draft as the service takes it: names, parent names, and the id of every folder that exists. */
	function treeForService() {
		var draft = view.getDraft() || TV.fromLive( state.nodes );
		var byKey = {};
		draft.folders.forEach( function ( f ) { byKey[ f.key ] = f; } );
		return {
			folders: draft.folders.map( function ( f ) {
				var out = {
					name: f.name,
					parent: f.parent && byKey[ f.parent ] ? byKey[ f.parent ].name : '',
					matches: f.matches || '',
					classes: f.classes || [],
					kinds: f.kinds || [],
					audience: f.audience || ''
				};
				if ( f.term_id ) {
					out.id = f.term_id;
				}
				if ( f.count !== undefined && f.count !== null ) {
					out.count = f.count;
				}
				return out;
			} ),
			tags: ( state.session.draft && state.session.draft.tags ) || []
		};
	}

	/* ---------------------------------------------------- the conversation */

	function who( turn ) {
		if ( 'assistant' === turn.role ) {
			return __( 'Assistant', 'vergelabs-media-library' );
		}
		if ( 'edit' === turn.kind ) {
			return __( 'You · edited the tree', 'vergelabs-media-library' );
		}
		if ( 'rule' === turn.kind ) {
			return __( 'You · applied a rule', 'vergelabs-media-library' );
		}
		return __( 'You', 'vergelabs-media-library' );
	}

	/** Text with **bold** into nodes, without ever handing the model's words to innerHTML. */
	function inline( target, text ) {
		var parts = String( text ).split( '**' );
		parts.forEach( function ( part, i ) {
			if ( ! part ) {
				return;
			}
			target.appendChild( i % 2 ? el( 'b', null, part ) : document.createTextNode( part ) );
		} );
	}

	/** "- " lines become the brand-mark list; the rest are paragraphs. */
	function renderSay( target, text ) {
		target.innerHTML = '';
		var lines = String( text || '' ).split( /\r?\n/ );
		var list = null;
		lines.forEach( function ( line ) {
			var t = line.trim();
			if ( ! t ) {
				list = null;
				return;
			}
			var m = /^[-•*]\s+(.*)$/.exec( t );
			if ( m ) {
				if ( ! list ) {
					list = el( 'ul', { class: 'vgml-facts' } );
					target.appendChild( list );
				}
				var li = el( 'li' );
				inline( li, m[ 1 ] );
				list.appendChild( li );
				return;
			}
			list = null;
			var p = el( 'p' );
			inline( p, t );
			target.appendChild( p );
		} );
	}

	function messageEl( turn, last ) {
		var msg = el( 'div', { class: 'vgml-msg is-' + ( 'assistant' === turn.role ? 'assistant' : 'user' ) + ( turn.kind ? ' is-' + turn.kind : '' ) } );
		msg.appendChild( el( 'span', { class: 'vgml-msg-who' }, who( turn ) ) );
		var body = el( 'div', { class: 'vgml-msg-body' } );
		renderSay( body, turn.text );
		msg.appendChild( body );
		if ( last && 'assistant' === turn.role && turn.choices && turn.choices.length && canTalk() ) {
			var chips = el( 'div', { class: 'vgml-chips' } );
			turn.choices.slice( 0, 3 ).forEach( function ( c ) {
				var b = el( 'button', { type: 'button', class: 'vgml-chip' }, c );
				b.addEventListener( 'click', function () {
					turn_( { choice: c }, { kind: 'choice', text: c } );
				} );
				chips.appendChild( b );
			} );
			msg.appendChild( chips );
		}
		return msg;
	}

	function renderConversation() {
		dom.conv.innerHTML = '';
		var turns = state.session.turns || [];
		var lastAssistant = -1;
		turns.forEach( function ( t, i ) {
			if ( 'assistant' === t.role ) {
				lastAssistant = i;
			}
		} );
		if ( ! described ) {
			var none = el( 'div', { class: 'vgml-msg is-note' } );
			var facts = el( 'ul', { class: 'vgml-facts' } );
			facts.appendChild( el( 'li', null, __( 'No pictures described yet', 'vergelabs-media-library' ) ) );
			facts.appendChild( el( 'li', null, __( 'Folders are worked out from the descriptions', 'vergelabs-media-library' ) ) );
			none.appendChild( facts );
			none.appendChild( el( 'a', { class: 'vgml-btn', href: cfg.aiUrl || '#' }, __( 'Describe the pictures', 'vergelabs-media-library' ) ) );
			dom.conv.appendChild( none );
		} else if ( ! licensed ) {
			var nol = el( 'div', { class: 'vgml-msg is-note' } );
			var l = el( 'ul', { class: 'vgml-facts' } );
			l.appendChild( el( 'li', null, __( 'No licence connected', 'vergelabs-media-library' ) ) );
			l.appendChild( el( 'li', null, __( 'Rules work without one. The conversation needs one.', 'vergelabs-media-library' ) ) );
			nol.appendChild( l );
			nol.appendChild( el( 'a', { class: 'vgml-btn', href: cfg.licenceUrl || '#' }, __( 'Connect a licence', 'vergelabs-media-library' ) ) );
			dom.conv.appendChild( nol );
		}
		turns.forEach( function ( t, i ) {
			dom.conv.appendChild( messageEl( t, i === lastAssistant && i === turns.length - 1 ) );
		} );
		if ( state.stream ) {
			dom.conv.appendChild( state.stream.el );
		}
		if ( state.note ) {
			var note = el( 'div', { class: 'vgml-msg is-note' } );
			var facts2 = el( 'ul', { class: 'vgml-facts' } );
			facts2.appendChild( el( 'li', null, state.note ) );
			note.appendChild( facts2 );
			dom.conv.appendChild( note );
		}
		renderComposer();
	}

	function renderComposer() {
		var streaming = !! state.stream;
		var cap = state.session.cap || cfg.cap || 25;
		var used = state.session.assistant_turns || 0;
		dom.kicker.textContent = 'rules' === state.method
			? __( 'Uses no credits', 'vergelabs-media-library' )
			/* translators: 1: turns used, 2: the cap */
			: sprintf( __( '%1$s of %2$s turns', 'vergelabs-media-library' ), fmt( used ), fmt( cap ) );
		dom.send.classList.toggle( 'is-stop', streaming );
		dom.send.setAttribute( 'aria-label', streaming ? __( 'Stop', 'vergelabs-media-library' ) : __( 'Send', 'vergelabs-media-library' ) );
		dom.hint.innerHTML = '';
		if ( capped() ) {
			/* translators: 1: turns used, 2: the cap */
			dom.text.placeholder = sprintf( __( '%1$s of %2$s turns used. Edit the tree by hand, or start over.', 'vergelabs-media-library' ), fmt( used ), fmt( cap ) );
			dom.text.disabled = true;
			dom.send.disabled = true;
			var over = el( 'button', { type: 'button', class: 'vgml-btn vgml-btn-ghost vgml-startover' }, __( 'Start over', 'vergelabs-media-library' ) );
			over.addEventListener( 'click', startOver );
			dom.hint.appendChild( over );
			return;
		}
		dom.text.placeholder = __( 'Describe the change', 'vergelabs-media-library' );
		dom.text.disabled = ! canTalk() || streaming;
		dom.send.disabled = ! canTalk() && ! streaming;
		dom.hint.textContent = __( 'Enter sends · Shift + Enter for a new line', 'vergelabs-media-library' );
	}

	function startOver() {
		if ( state.stream ) {
			stop();
		}
		persist( 'guide/session', { reset: true } ).then( function ( r ) {
			state.session = r || { turns: [], draft: null, assistant_turns: 0, cap: cfg.cap, apply: null };
			state.note = '';
			view.setDraft( null );
			renderTreeHead();
			renderMove();
			renderConversation();
			if ( canTalk() ) {
				turn_( { open: true }, null );
			}
		} );
	}

	function setMethod( m ) {
		state.method = 'rules' === m ? 'rules' : 'talk';
		dom.tabTalk.setAttribute( 'aria-selected', 'talk' === state.method ? 'true' : 'false' );
		dom.tabRules.setAttribute( 'aria-selected', 'rules' === state.method ? 'true' : 'false' );
		dom.conv.hidden = 'rules' === state.method;
		dom.composer.hidden = 'rules' === state.method;
		dom.rules.hidden = 'talk' === state.method;
		dom.preview.hidden = 'talk' === state.method || ! state.preview.length;
		renderComposer();
		if ( 'rules' === state.method && ! state.rules ) {
			loadRules();
		}
	}

	/* --------------------------------------------------------- the token */

	function ensureToken( force ) {
		if ( ! force && state.token && state.token.expires_at * 1000 - Date.now() > 60000 ) {
			return Promise.resolve( state.token );
		}
		return api( 'POST', 'guide/token' ).then( function ( t ) {
			state.token = t;
			return t;
		} );
	}

	/* --------------------------------------------------------- a turn */

	function send() {
		var text = dom.text.value.trim();
		if ( ! text || ! canTalk() || state.stream ) {
			return;
		}
		dom.text.value = '';
		growComposer();
		turn_( { text: text }, { kind: 'said', text: text } );
	}

	/**
	 *  One streamed turn. What the person said goes into the conversation and
	 *  the session first; the reply streams into a message of its own, word
	 *  by word, the tree lands when the block does, and the finished turn is
	 *  persisted with the draft. Stop keeps the words that arrived.
	 */
	function turn_( input, said ) {
		if ( state.stream || ! canTalk() ) {
			return;
		}
		state.note = '';
		if ( said ) {
			state.session.turns.push( { role: 'user', kind: said.kind, text: said.text, rule: said.rule, at: Math.floor( Date.now() / 1000 ) } );
			persist( 'guide/turn', { said: said } );
		}
		var msg = el( 'div', { class: 'vgml-msg is-assistant is-streaming' } );
		msg.appendChild( el( 'span', { class: 'vgml-msg-who' }, __( 'Assistant', 'vergelabs-media-library' ) ) );
		var body = el( 'div', { class: 'vgml-msg-body' } );
		msg.appendChild( body );
		var controller = new AbortController();
		state.stream = { controller: controller, el: msg, body: body, text: '', tree: null, choices: [], done: false, paint: null };
		renderConversation();
		msg.scrollIntoView( { block: 'nearest' } );

		var paint = function () {
			if ( state.stream && state.stream.paint ) {
				return;
			}
			state.stream.paint = window.requestAnimationFrame( function () {
				if ( ! state.stream ) {
					return;
				}
				state.stream.paint = null;
				renderSay( body, state.stream.text || '…' );
			} );
		};
		renderSay( body, '…' );

		var attempt = function ( force ) {
			return ensureToken( force ).then( function ( token ) {
				return window.fetch( token.stream + '/guide/stream', {
					method: 'POST',
					mode: 'cors',
					signal: controller.signal,
					headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + token.token },
					body: JSON.stringify( {
						conversation: ( state.session.turns || [] ).map( function ( t ) { return { role: t.role, text: t.text }; } ),
						tree: treeForService(),
						input: input,
						summary: token.summary,
						current: token.current
					} )
				} );
			} );
		};

		attempt( false ).then( function ( res ) {
			if ( 401 === res.status ) {
				return attempt( true );
			}
			return res;
		} ).then( function ( res ) {
			if ( ! res.ok ) {
				return res.json().catch( function () { return {}; } ).then( function ( j ) {
					throw new Error( serviceWhy( res.status, j ) );
				} );
			}
			return readEvents( res, onEvent );
		} ).then( function () {
			finish( false );
		} ).catch( function ( err ) {
			if ( err && 'AbortError' === err.name ) {
				finish( true );
				return;
			}
			state.note = ( err && err.message ) || __( 'That did not go through. The draft is safe. Try again.', 'vergelabs-media-library' );
			finish( false );
		} );

		function onEvent( type, data ) {
			if ( ! state.stream ) {
				return;
			}
			if ( 'say' === type ) {
				state.stream.text += data.text || '';
				paint();
			} else if ( 'tree' === type ) {
				state.stream.tree = resolveTree( data.tree );
				setDraft( state.stream.tree, true );
			} else if ( 'done' === type ) {
				state.stream.choices = data.choices || [];
				state.stream.done = true;
			} else if ( 'error' === type ) {
				state.note = serviceWhy( 0, data );
			}
		}

		function finish( stopped ) {
			var s = state.stream;
			if ( ! s ) {
				return;
			}
			if ( s.paint ) {
				window.cancelAnimationFrame( s.paint );
			}
			state.stream = null;
			var text = s.text.trim();
			var body = {};
			if ( text ) {
				body.say = { text: text, choices: s.done && ! stopped ? s.choices : [] };
				state.session.turns.push( { role: 'assistant', kind: 'say', text: text, choices: body.say.choices, at: Math.floor( Date.now() / 1000 ) } );
				state.session.assistant_turns = ( state.session.assistant_turns || 0 ) + 1;
			}
			if ( s.tree ) {
				body.draft = s.tree;
				state.session.draft = s.tree;
			}
			if ( body.say || body.draft ) {
				persist( 'guide/turn', body ).then( function () { renderConversation(); } );
			}
			if ( stopped && ! state.note ) {
				state.note = __( 'Stopped. What arrived stays; the draft is as it was.', 'vergelabs-media-library' );
			}
			renderConversation();
			renderMove();
		}
	}

	function serviceWhy( status, j ) {
		var code = ( j && ( j.code || j.error ) ) || '';
		if ( 'provider_busy' === code ) {
			/* translators: %s: seconds */
			return sprintf( __( 'The assistant is busy. Try again in %s seconds.', 'vergelabs-media-library' ), fmt( j.retry_after || 60 ) );
		}
		if ( 'turn_cap' === code ) {
			return __( 'Every turn of this conversation is used. Edit the tree by hand, or start over.', 'vergelabs-media-library' );
		}
		if ( 'bad_tree' === code ) {
			return __( 'The tree did not come through. The words stayed; the draft is as it was.', 'vergelabs-media-library' );
		}
		if ( 'bad_token' === code ) {
			return __( 'The session token was refused. Reload the page.', 'vergelabs-media-library' );
		}
		if ( 429 === status ) {
			return __( 'The day\'s limit of turns for this site is used. Tomorrow it resets.', 'vergelabs-media-library' );
		}
		return __( 'That did not go through. The draft is safe. Try again.', 'vergelabs-media-library' );
	}

	/** Server-sent events off a fetch body: "event: x\ndata: {...}\n\n". */
	function readEvents( res, onEvent ) {
		var reader = res.body.getReader();
		var decoder = new TextDecoder();
		var buffer = '';
		function pump() {
			return reader.read().then( function ( r ) {
				if ( r.done ) {
					return;
				}
				buffer += decoder.decode( r.value, { stream: true } );
				var blocks = buffer.split( /\r?\n\r?\n/ );
				buffer = blocks.pop();
				blocks.forEach( function ( block ) {
					var type = 'message';
					var data = '';
					block.split( /\r?\n/ ).forEach( function ( line ) {
						if ( 0 === line.indexOf( 'event:' ) ) {
							type = line.slice( 6 ).trim();
						} else if ( 0 === line.indexOf( 'data:' ) ) {
							data += line.slice( 5 ).trim();
						}
					} );
					var parsed = {};
					try {
						parsed = data ? JSON.parse( data ) : {};
					} catch ( e ) {
						parsed = {};
					}
					onEvent( type, parsed );
				} );
				return pump();
			} );
		}
		return pump();
	}

	function stop() {
		if ( state.stream ) {
			state.stream.controller.abort();
		}
	}

	/* ------------------------------------------------------- hand edits */

	function nameOf( draft, key ) {
		var name = '';
		draft.folders.forEach( function ( f ) {
			if ( f.key === key ) {
				name = f.name;
			}
		} );
		return name;
	}

	function onHandEdit( edit ) {
		var draft = view.getDraft() || TV.fromLive( state.nodes );
		var line = '';
		if ( 'remove' === edit.type ) {
			draft.folders.forEach( function ( f ) {
				if ( f.key === edit.key ) {
					edit.to = f.parent || '';
				}
			} );
			/* translators: %s: a folder name */
			line = sprintf( __( 'Removed %s', 'vergelabs-media-library' ), nameOf( draft, edit.key ) );
		} else if ( 'rename' === edit.type ) {
			/* translators: 1: the old name, 2: the new name */
			line = sprintf( __( 'Renamed %1$s to %2$s', 'vergelabs-media-library' ), edit.from, edit.to );
		} else if ( 'reparent' === edit.type ) {
			line = edit.parent
				/* translators: 1: a folder name, 2: its new parent */
				? sprintf( __( 'Moved %1$s under %2$s', 'vergelabs-media-library' ), nameOf( draft, edit.key ), nameOf( draft, edit.parent ) )
				/* translators: %s: a folder name */
				: sprintf( __( 'Moved %s to the top level', 'vergelabs-media-library' ), nameOf( draft, edit.key ) );
		}
		var next = withOrigin( TV.applyEdit( draft, edit ), state.session.draft );
		setDraft( next );
		if ( ! line ) {
			return;
		}
		if ( canTalk() && ! state.stream ) {
			turn_( { edit: line }, { kind: 'edit', text: line } );
		} else {
			state.session.turns.push( { role: 'user', kind: 'edit', text: line, at: Math.floor( Date.now() / 1000 ) } );
			persist( 'guide/turn', { said: { kind: 'edit', text: line } } );
			renderConversation();
		}
	}

	/* ------------------------------------------------------------- rules */

	var RULES = [
		{ id: 'kind', label: __( 'By kind', 'vergelabs-media-library' ), desc: __( 'One folder per kind of picture: photos, illustrations, screenshots, diagrams.', 'vergelabs-media-library' ) },
		{ id: 'date', label: __( 'By month and year', 'vergelabs-media-library' ), desc: __( 'A folder per year, a subfolder per month, by upload date.', 'vergelabs-media-library' ) },
		{ id: 'subject', label: __( 'By subject', 'vergelabs-media-library' ), desc: '' },
		{ id: 'fit', label: __( 'Into today\'s folders', 'vergelabs-media-library' ), desc: __( 'No new folders. Each unfiled picture goes to the existing folder it fits. The rest stay unfiled.', 'vergelabs-media-library' ) }
	];

	function ruleDefaults( id ) {
		switch ( id ) {
			case 'kind': return { scope: 'unfiled' };
			case 'date': return { source: 'upload', levels: 'ym', scope: 'unfiled' };
			case 'subject': return { min: 10, levels: 'one', scope: 'unfiled' };
			default: return { rest: 'stay', sure: 'sure' };
		}
	}

	function loadRules() {
		api( 'GET', 'guide/rules' ).then( function ( r ) {
			state.rules = r;
			var d = state.session.draft;
			if ( d && 'rule' === d.origin && d.rule && ! state.rule ) {
				state.rule = { id: d.rule.id, options: d.rule.options };
				applyRule( true );
			}
			renderRules();
		} ).catch( function () {
			state.rules = { rules: [], unfiled: 0, pictures: 0 };
			renderRules();
		} );
	}

	function radio( name, value, label, checked, onPick ) {
		var lab = el( 'label', { class: 'vgml-check vgml-radio' } );
		var input = el( 'input', { type: 'radio', name: name, value: value } );
		input.checked = !! checked;
		input.addEventListener( 'change', function () { onPick( value ); } );
		lab.appendChild( input );
		lab.appendChild( el( 'span', null, label ) );
		return lab;
	}

	function scopeRadios( o, set ) {
		var wrap = el( 'div', { class: 'vgml-radios' } );
		var unfiled = state.rules ? state.rules.unfiled : 0;
		var now = state.nodes.length;
		/* translators: 1: unfiled pictures, 2: folders today */
		wrap.appendChild( radio( 'vgml-scope', 'unfiled', sprintf( __( 'Move only the %1$s unfiled pictures. Today\'s %2$s folders stay.', 'vergelabs-media-library' ), fmt( unfiled ), fmt( now ) ), 'unfiled' === o.scope, function ( v ) { set( 'scope', v ); } ) );
		/* translators: %s: folders today */
		wrap.appendChild( radio( 'vgml-scope', 'all', sprintf( __( 'Move every picture. Today\'s %s folders are removed.', 'vergelabs-media-library' ), fmt( now ) ), 'all' === o.scope, function ( v ) { set( 'scope', v ); } ) );
		return wrap;
	}

	function field( label, control, note ) {
		var f = el( 'div', { class: 'vgml-field' } );
		f.appendChild( el( 'label', { class: 'vgml-field-label' }, label ) );
		f.appendChild( control );
		if ( note ) {
			f.appendChild( note );
		}
		return f;
	}

	function ruleOptions( id, o, set ) {
		var opts = el( 'div', { class: 'vgml-rule-opts' } );
		var now = new Date();
		var year = String( now.getFullYear() );
		var month = now.toLocaleString( undefined, { month: 'long' } );
		var ym = year + '-' + ( '0' + ( now.getMonth() + 1 ) ).slice( -2 );
		if ( 'date' === id ) {
			var sel = el( 'select', { class: 'vgml-input vgml-select' } );
			sel.appendChild( el( 'option', { value: 'upload' }, __( 'Upload date', 'vergelabs-media-library' ) ) );
			sel.appendChild( el( 'option', { value: 'taken' }, __( 'Date taken', 'vergelabs-media-library' ) ) );
			sel.value = o.source;
			sel.addEventListener( 'change', function () { set( 'source', sel.value ); } );
			var note = el( 'ul', { class: 'vgml-facts vgml-facts-note' } );
			note.appendChild( el( 'li', null, __( 'Or: date taken, from the camera', 'vergelabs-media-library' ) ) );
			note.appendChild( el( 'li', null, __( 'Pictures without one use the upload date', 'vergelabs-media-library' ) ) );
			opts.appendChild( field( __( 'Date', 'vergelabs-media-library' ), sel, note ) );
			var lv = el( 'div', { class: 'vgml-radios' } );
			/* translators: 1: a year, 2: a month */
			lv.appendChild( radio( 'vgml-levels', 'ym', sprintf( __( 'Year, then month: %1$s / %2$s', 'vergelabs-media-library' ), year, month ), 'ym' === o.levels, function ( v ) { set( 'levels', v ); } ) );
			/* translators: %s: a year-month, e.g. 2026-08 */
			lv.appendChild( radio( 'vgml-levels', 'month', sprintf( __( 'One folder per month: %s', 'vergelabs-media-library' ), ym ), 'month' === o.levels, function ( v ) { set( 'levels', v ); } ) );
			opts.appendChild( field( __( 'Levels', 'vergelabs-media-library' ), lv ) );
		}
		if ( 'subject' === id ) {
			var step = el( 'div', { class: 'vgml-step' } );
			var minus = el( 'button', { type: 'button', 'aria-label': __( 'Smaller', 'vergelabs-media-library' ) }, '−' );
			var n = el( 'b', null, fmt( o.min ) );
			var plus = el( 'button', { type: 'button', 'aria-label': __( 'Larger', 'vergelabs-media-library' ) }, '+' );
			minus.addEventListener( 'click', function () { set( 'min', Math.max( 1, o.min - ( o.min > 10 ? 5 : 1 ) ) ); } );
			plus.addEventListener( 'click', function () { set( 'min', Math.min( 500, o.min + ( o.min >= 10 ? 5 : 1 ) ) ); } );
			step.appendChild( minus );
			step.appendChild( n );
			step.appendChild( plus );
			opts.appendChild( field( __( 'Smallest folder', 'vergelabs-media-library' ), step, el( 'p', { class: 'vgml-note' }, __( 'Subjects with fewer pictures than this stay unfiled.', 'vergelabs-media-library' ) ) ) );
			var lv2 = el( 'div', { class: 'vgml-radios' } );
			lv2.appendChild( radio( 'vgml-levels', 'one', __( 'One level: Landscape', 'vergelabs-media-library' ), 'one' === o.levels, function ( v ) { set( 'levels', v ); } ) );
			lv2.appendChild( radio( 'vgml-levels', 'two', __( 'Two levels: Landscape / Mountains', 'vergelabs-media-library' ), 'two' === o.levels, function ( v ) { set( 'levels', v ); } ) );
			opts.appendChild( field( __( 'Levels', 'vergelabs-media-library' ), lv2 ) );
		}
		if ( 'fit' === id ) {
			var rest = el( 'div', { class: 'vgml-radios' } );
			rest.appendChild( radio( 'vgml-rest', 'stay', __( 'It stays unfiled.', 'vergelabs-media-library' ), 'stay' === o.rest, function ( v ) { set( 'rest', v ); } ) );
			rest.appendChild( radio( 'vgml-rest', 'unsorted', __( 'It goes to a folder named Unsorted.', 'vergelabs-media-library' ), 'unsorted' === o.rest, function ( v ) { set( 'rest', v ); } ) );
			opts.appendChild( field( __( 'When a picture fits no folder', 'vergelabs-media-library' ), rest ) );
			var sure = el( 'div', { class: 'vgml-radios' } );
			sure.appendChild( radio( 'vgml-sure', 'sure', __( 'Only sure matches.', 'vergelabs-media-library' ), 'sure' === o.sure, function ( v ) { set( 'sure', v ); } ) );
			sure.appendChild( radio( 'vgml-sure', 'close', __( 'Close calls too. More pictures move, some to the wrong folder.', 'vergelabs-media-library' ), 'close' === o.sure, function ( v ) { set( 'sure', v ); } ) );
			opts.appendChild( field( __( 'How sure', 'vergelabs-media-library' ), sure ) );
		} else {
			opts.appendChild( scopeRadios( o, set ) );
		}
		return opts;
	}

	function renderRules() {
		dom.rules.innerHTML = '';
		var counts = {};
		( ( state.rules && state.rules.rules ) || [] ).forEach( function ( r ) { counts[ r.id ] = r.folders; } );
		RULES.forEach( function ( r ) {
			var on = state.rule && state.rule.id === r.id;
			var row = el( 'div', { class: 'vgml-rule-row' + ( on ? ' is-on' : '' ) } );
			var pick = el( 'button', { type: 'button', class: 'vgml-rule-pick', role: 'radio', 'aria-checked': on ? 'true' : 'false', 'aria-label': r.label } );
			pick.appendChild( el( 'span', { class: 'vgml-rule-dot', 'aria-hidden': 'true' } ) );
			pick.addEventListener( 'click', function () { pickRule( r.id ); } );
			row.appendChild( pick );
			var body = el( 'div', { class: 'vgml-rule-body' } );
			var title = el( 'button', { type: 'button', class: 'vgml-rule-title' }, r.label );
			title.addEventListener( 'click', function () { pickRule( r.id ); } );
			body.appendChild( title );
			var desc = r.desc;
			if ( 'subject' === r.id ) {
				var min = on ? state.rule.options.min : 10;
				/* translators: %s: the smallest folder */
				desc = sprintf( __( 'A folder per subject from the catalogue. Subjects with fewer than %s pictures stay unfiled.', 'vergelabs-media-library' ), fmt( min ) );
			}
			body.appendChild( el( 'div', { class: 'vgml-rule-desc' }, desc ) );
			if ( on ) {
				body.appendChild( ruleOptions( r.id, state.rule.options, setRuleOption ) );
			}
			row.appendChild( body );
			var n = 'fit' === r.id
				? ( on && 'unsorted' === state.rule.options.rest ? __( '1 new', 'vergelabs-media-library' ) : __( '0 new', 'vergelabs-media-library' ) )
				/* translators: %s: folders */
				: sprintf( _n( '%s folder', '%s folders', counts[ r.id ] || 0, 'vergelabs-media-library' ), fmt( counts[ r.id ] || 0 ) );
			row.appendChild( el( 'span', { class: 'vgml-rule-n' }, n ) );
			dom.rules.appendChild( row );
		} );
		renderPreview();
	}

	function renderPreview() {
		dom.preview.innerHTML = '';
		( state.preview || [] ).forEach( function ( line ) {
			var li = el( 'li' );
			if ( line.strong ) {
				var parts = String( line.text ).split( ': ' );
				li.appendChild( el( 'b', null, parts.shift() ) );
				if ( parts.length ) {
					li.appendChild( document.createTextNode( ': ' + parts.join( ': ' ) ) );
				}
			} else {
				li.textContent = line.text;
			}
			dom.preview.appendChild( li );
		} );
		dom.preview.hidden = 'talk' === state.method || ! state.preview.length;
	}

	function pickRule( id ) {
		if ( state.rule && state.rule.id === id ) {
			return;
		}
		state.rule = { id: id, options: ruleDefaults( id ) };
		renderRules();
		applyRule( false );
	}

	var ruleTimer = null;
	function setRuleOption( k, v ) {
		if ( ! state.rule ) {
			return;
		}
		state.rule.options[ k ] = v;
		renderRules();
		window.clearTimeout( ruleTimer );
		ruleTimer = window.setTimeout( function () { applyRule( false ); }, 150 );
	}

	/**
	 *  A rule, applied to the draft: the tree and Move answer as the option
	 *  changes, and one line in the conversation says what was applied, so
	 *  the conversation stays the whole history of the tree.
	 */
	function applyRule( quiet ) {
		var rule = state.rule;
		if ( ! rule ) {
			return;
		}
		dom.rules.classList.add( 'is-busy' );
		api( 'POST', 'guide/rule', { rule: rule.id, options: rule.options } ).then( function ( r ) {
			if ( ! state.rule || state.rule.id !== rule.id ) {
				return;
			}
			dom.rules.classList.remove( 'is-busy' );
			state.preview = r.preview || [];
			var draft = r.draft;
			draft.tags = [];
			setDraft( draft, quiet );
			if ( quiet ) {
				renderPreview();
				return;
			}
			var label = '';
			RULES.forEach( function ( x ) { if ( x.id === rule.id ) { label = x.label; } } );
			/* translators: 1: the rule, 2: folders, 3: pictures */
			var line = sprintf( __( '%1$s: %2$s folders, %3$s pictures', 'vergelabs-media-library' ), label, fmt( r.made ), fmt( r.move ) );
			var turns = state.session.turns;
			var last = turns[ turns.length - 1 ];
			if ( last && 'rule' === last.kind && last.rule === rule.id ) {
				turns.pop();
			}
			turns.push( { role: 'user', kind: 'rule', rule: rule.id, text: line, at: Math.floor( Date.now() / 1000 ) } );
			persist( 'guide/turn', { said: { kind: 'rule', rule: rule.id, text: line }, draft: draft } );
			renderPreview();
			renderConversation();
		} ).catch( function ( err ) {
			dom.rules.classList.remove( 'is-busy' );
			state.preview = [ { text: ( err && err.message ) || __( 'That did not go through. Try again.', 'vergelabs-media-library' ) } ];
			renderPreview();
		} );
	}

	/* -------------------------------------------------------------- Move */

	function untilText( ts ) {
		if ( ! ts ) {
			return '';
		}
		var d = new Date( ts * 1000 );
		var now = new Date();
		var time = d.toLocaleTimeString( undefined, { hour: '2-digit', minute: '2-digit' } );
		var day = d.toDateString();
		var tomorrow = new Date( now.getTime() + 86400000 ).toDateString();
		if ( day === now.toDateString() ) {
			/* translators: %s: a time */
			return sprintf( __( 'today %s', 'vergelabs-media-library' ), time );
		}
		if ( day === tomorrow ) {
			/* translators: %s: a time */
			return sprintf( __( 'tomorrow %s', 'vergelabs-media-library' ), time );
		}
		return d.toLocaleDateString( undefined, { day: 'numeric', month: 'long' } ) + ' ' + time;
	}

	function renderMove() {
		var s = view ? view.summary() : { changes: 0, moving: 0 };
		var apply = state.session.apply;
		var moving = apply && apply.running;
		dom.stop.hidden = ! moving;
		root.setAttribute( 'data-state', moving ? 'moving' : ( state.undo.available && ! s.changes ? 'done' : 'resting' ) );

		if ( moving ) {
			var r = state.moving || {};
			var goal = Math.max( Number( r.moved ) || 0, state.movingGoal || 0 );
			/* translators: 1: pictures moved so far, 2: pictures to move */
			dom.move.textContent = sprintf( __( 'Moving %1$s of %2$s', 'vergelabs-media-library' ), fmt( r.moved || 0 ), fmt( goal ) );
			dom.move.disabled = true;
			dom.undo.hidden = true;
			return;
		}

		if ( s.changes > 0 && described ) {
			/* translators: %s: pictures */
			dom.move.textContent = sprintf( _n( 'Move %s picture', 'Move %s pictures', s.moving, 'vergelabs-media-library' ), fmt( s.moving ) );
			dom.move.disabled = false;
			dom.move.hidden = false;
		} else {
			dom.move.textContent = __( 'Move · no changes yet', 'vergelabs-media-library' );
			dom.move.disabled = true;
			dom.move.hidden = state.undo.available;
		}

		if ( state.undo.available ) {
			dom.undo.textContent = state.undo.until
				/* translators: %s: when undo ends */
				? sprintf( __( 'Undo until %s', 'vergelabs-media-library' ), untilText( state.undo.until ) )
				: __( 'Undo the last Move', 'vergelabs-media-library' );
			dom.undo.hidden = false;
			dom.undo.disabled = false;
		} else {
			dom.undo.hidden = true;
		}
	}

	function onMove() {
		if ( ! view.getDraft() || dom.move.disabled ) {
			return;
		}
		if ( state.stream ) {
			stop();
		}
		state.movingGoal = view.summary().moving;
		dom.move.disabled = true;
		api( 'POST', 'guide/apply' ).then( function ( r ) {
			took( r );
		} ).catch( function ( err ) {
			state.note = ( err && err.message ) || __( 'That did not go through. Nothing moved.', 'vergelabs-media-library' );
			renderConversation();
			renderMove();
		} );
	}

	var pollTimer = null;

	/** A progress answer, whether the run is still going or just ended. */
	function took( r ) {
		if ( ! r ) {
			return;
		}
		state.session = r.session || state.session;
		state.undo = r.undo || state.undo;
		state.moving = r.report || null;
		if ( r.version ) {
			state.version = r.version;
			if ( watcher ) {
				watcher.known( r.version );
			}
		}
		if ( r.report && r.report.running ) {
			view.setProgress( r.report.by_key || r.report.by_term || {} );
			renderMove();
			window.clearTimeout( pollTimer );
			pollTimer = window.setTimeout( function () {
				api( 'GET', 'guide/progress' ).then( took ).catch( function () {} );
			}, 2000 );
			return;
		}
		// Done, or stopped: the tree is the library now; the line is in the conversation.
		window.clearTimeout( pollTimer );
		view.setProgress( null );
		view.setDraft( null );
		state.session.draft = null;
		state.preview = [];
		state.rule = null;
		refreshTree().then( function () {
			renderTreeHead();
			renderMove();
			renderConversation();
			if ( 'rules' === state.method ) {
				state.rules = null;
				loadRules();
			}
		} );
	}

	function onStop() {
		dom.stop.disabled = true;
		api( 'POST', 'guide/stop' ).then( function ( r ) {
			dom.stop.disabled = false;
			took( r );
		} ).catch( function () { dom.stop.disabled = false; } );
	}

	function onUndo() {
		dom.undo.disabled = true;
		api( 'POST', 'guide/undo' ).then( function ( r ) {
			took( { session: r.session, undo: r.undo, version: r.version, report: null } );
		} ).catch( function ( err ) {
			dom.undo.disabled = false;
			state.note = ( err && err.message ) || __( 'That did not go through.', 'vergelabs-media-library' );
			renderConversation();
		} );
	}

	/* --------------------------------------------------------------- go */

	build();
	makeTree();
	renderTreeHead();
	renderConversation();
	renderMove();
	setMethod( state.session.draft && 'rule' === state.session.draft.origin && ! ( state.session.turns || [] ).length ? 'rules' : 'talk' );
	root.classList.add( 'is-ready' );

	// A Move still running from before this page loaded carries on being watched.
	if ( state.session.apply && state.session.apply.running ) {
		api( 'GET', 'guide/progress' ).then( took ).catch( function () {} );
	}

	// The conversation opens by itself on a described library that has not spoken yet.
	if ( canTalk() && ! ( state.session.turns || [] ).length && ! state.session.draft ) {
		turn_( { open: true }, null );
	}

	window.vgmlFoldersApp = { state: state, view: function () { return view; }, stop: stop };
}() );
