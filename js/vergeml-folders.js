/*
 *  Folders: one conversation, one tree, one Move.
 *
 *  Two columns. On the left, a segmented switch between two ways of building
 *  the same draft: the conversation (js/vergeml-talk.js, shared with the AI
 *  screen), streamed word by word straight from the service with a token
 *  core/guide.php mints, and a paste -- one folder per line, the full path
 *  with ">" between levels, read locally (js/vergeml-structure.js) and shown
 *  as a tree before anything is made. On the right, the shared tree
 *  (js/vergeml-tree-view.js) drawing today's folders with the draft laid over
 *  them, and the one button that moves pictures, in its three states.
 *
 *  Either way in produces a draft, never folders: the same draft, over the
 *  same tree, behind the same Move button with the same undo. A paste settles
 *  through the same turn route a conversation's tree does, so every number
 *  beside it is the matcher's dry run and none is the paste's own.
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
	var TALK = window.vergemlTalk;
	var STRUCT = window.vergemlStructure;
	var wp = window.wp;
	if ( ! TV || ! TALK || ! STRUCT || ! wp || ! wp.apiFetch || ! wp.i18n ) {
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

	function escapeHtml( s ) {
		return String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	/* ------------------------------------------------------------- state */

	var state = {
		session: cfg.session || { turns: [], draft: null, assistant_turns: 0, cap: cfg.cap || 25, apply: null },
		nodes: cfg.nodes || [],
		version: cfg.version || 0,
		undo: cfg.undo || { available: false, until: 0 },
		method: 'talk',
		// What vergeml_filing_pick() said about the draft, from the turn that
		// settled it. The session carries it, so a reload reads the same numbers.
		fit: ( cfg.session && cfg.session.fit ) || null,
		// A paste handed to the turn route and not yet answered: the rows carry
		// no count and Move waits, because the only numbers that could be shown
		// are ones nobody has computed.
		pastePending: false,
		pasteSeq: 0,
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
				/*
				 *  The turn route runs the matcher over the draft before it
				 *  hands it back, so this is where the real numbers arrive:
				 *  the count on every folder, and the line for what the run
				 *  would not place -- which is the number an owner needs
				 *  before pressing Move and the one the model cannot write.
				 *
				 *  A session write answers `fit: null`, and that clears the
				 *  lines rather than leaving an answer about an older tree.
				 */
				if ( r && undefined !== r.fit ) {
					tookFit( r );
				}
				return r;
			} );
		}, function () {} );
		return queue;
	}

	function tookFit( r ) {
		/*
		 *  Not while a Move runs. The turn that was in flight when the button
		 *  was pressed answers with the draft it settled, and taking it would
		 *  put back on screen the very draft the Move is in the middle of
		 *  applying -- after took() had cleared it.
		 */
		if ( state.session.apply && state.session.apply.running ) {
			return;
		}
		if ( r.fit && r.draft ) {
			setDraft( r.draft, true );
		}
		// After setDraft, which clears it: the run belongs to the draft it was
		// computed for and to no other.
		state.fit = r.fit || null;
		renderMove();
		renderPreview();
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

		// Left: the method, the conversation, the composer; or the paste.
		var left = el( 'div', { class: 'vgml-folders-left' } );
		var method = el( 'div', { class: 'vgml-method' } );
		var seg = el( 'div', { class: 'vgml-seg', role: 'tablist', 'aria-label': __( 'How to build the tree', 'vergelabs-media-library' ) } );
		dom.tabTalk = el( 'button', { type: 'button', role: 'tab', class: 'vgml-seg-tab', 'data-method': 'talk', 'aria-selected': 'true' }, __( 'Conversation', 'vergelabs-media-library' ) );
		dom.tabPaste = el( 'button', { type: 'button', role: 'tab', class: 'vgml-seg-tab', 'data-method': 'paste', 'aria-selected': 'false' }, __( 'Paste folders', 'vergelabs-media-library' ) );
		seg.appendChild( dom.tabTalk );
		seg.appendChild( dom.tabPaste );
		dom.tabTalk.addEventListener( 'click', function () { setMethod( 'talk' ); } );
		dom.tabPaste.addEventListener( 'click', function () { setMethod( 'paste' ); } );
		method.appendChild( seg );
		dom.kicker = el( 'span', { class: 'vgml-kicker vgml-method-kicker' } );
		method.appendChild( dom.kicker );
		left.appendChild( method );

		talk = TALK.create( talkOpts() );
		left.appendChild( talk.conv );
		left.appendChild( talk.composer );

		left.appendChild( buildPaste() );

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
		/*
		 *  A different draft, so the dry run's answer is about a tree that is
		 *  no longer on screen. Dropped rather than shown against the new one:
		 *  a paste's draft would otherwise wear the conversation's number for as
		 *  long as the round trip takes, and the Move button with it.
		 */
		state.fit = null;
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
			out.folders.push( {
				key: keys[ i ].key,
				term_id: keys[ i ].term_id,
				name: String( f.name || '' ).replace( /\//g, '-' ),
				parent: pi >= 0 ? keys[ pi ].key : '',
				/*
				 *  The reply carries a "count" and it is dropped here.
				 *
				 *  It was the model's estimate and nothing else -- the tree
				 *  shape asks a text model for a number and it answers with
				 *  arithmetic nothing performed. On the box on 4 September
				 *  2026 that read "Illustrations (23), Screenshots (6)" for a
				 *  library nobody had counted. Null means "unchanged" to the
				 *  tree, so a folder reads what it holds today until the turn
				 *  route answers with what vergeml_filing_pick() says.
				 */
				count: null,
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

	/*
	 *  js/vergeml-talk.js draws the turns and the composer and streams a turn
	 *  from the service. This side hands it the token, the request body with
	 *  the tree, and takes the tree block back as the draft.
	 */
	var talk = null;
	var streamTree = null;

	/** The note before the turns, when nothing can be said yet. */
	function leadNote() {
		if ( ! described ) {
			var none = el( 'div', { class: 'vgml-msg is-note' } );
			var facts = el( 'ul', { class: 'vgml-facts' } );
			facts.appendChild( el( 'li', null, __( 'No pictures described yet', 'vergelabs-media-library' ) ) );
			facts.appendChild( el( 'li', null, __( 'Folders are worked out from the descriptions', 'vergelabs-media-library' ) ) );
			none.appendChild( facts );
			none.appendChild( el( 'a', { class: 'vgml-btn', href: cfg.aiUrl || '#' }, __( 'Describe the pictures', 'vergelabs-media-library' ) ) );
			return none;
		}
		if ( ! licensed ) {
			var nol = el( 'div', { class: 'vgml-msg is-note' } );
			var l = el( 'ul', { class: 'vgml-facts' } );
			l.appendChild( el( 'li', null, __( 'No licence connected', 'vergelabs-media-library' ) ) );
			l.appendChild( el( 'li', null, __( 'Rules work without one. The conversation needs one.', 'vergelabs-media-library' ) ) );
			nol.appendChild( l );
			nol.appendChild( el( 'a', { class: 'vgml-btn', href: cfg.licenceUrl || '#' }, __( 'Connect a licence', 'vergelabs-media-library' ) ) );
			return nol;
		}
		return null;
	}

	function talkOpts() {
		return {
			session: state.session,
			cap: cfg.cap || 25,
			canTalk: function () {
				return described && licensed && ! ( state.session.apply && state.session.apply.running );
			},
			mint: function () {
				return api( 'POST', 'guide/token' );
			},
			streamPath: '/guide/stream',
			request: function ( token, input, history ) {
				return { conversation: history, tree: treeForService(), input: input, summary: token.summary, current: token.current };
			},
			persist: function ( body ) {
				return persist( 'guide/turn', body );
			},
			blocks: [ 'tree' ],
			onBlock: function ( type, data ) {
				streamTree = resolveTree( data.tree );
				setDraft( streamTree, true );
			},
			onFinish: function () {
				var extra = null;
				if ( streamTree ) {
					extra = { draft: streamTree };
					state.session.draft = streamTree;
				}
				streamTree = null;
				renderMove();
				return extra;
			},
			onRender: renderKicker,
			lead: leadNote,
			placeholder: __( 'Describe the change', 'vergelabs-media-library' ),
			cappedPlaceholder: function ( used, cap ) {
				/* translators: 1: turns used, 2: the cap */
				return sprintf( __( '%1$s of %2$s turns used. Edit the tree by hand, or start over.', 'vergelabs-media-library' ), used, cap );
			},
			startOver: startOver,
			why: {
				turn_cap: __( 'Every turn of this conversation is used. Edit the tree by hand, or start over.', 'vergelabs-media-library' ),
				bad_block: __( 'The tree did not come through. The words stayed; the draft is as it was.', 'vergelabs-media-library' )
			}
		};
	}

	function renderKicker() {
		/* translators: 1: turns used, 2: the cap */
		dom.kicker.textContent = sprintf( __( '%1$s of %2$s turns', 'vergelabs-media-library' ), fmt( talk.used() ), fmt( talk.cap() ) );
	}

	function renderConversation() {
		talk.render();
	}

	function turn_( input, said ) {
		talk.turn( input, said );
	}

	function stop() {
		talk.stop();
	}

	function startOver() {
		if ( talk.streaming() ) {
			talk.stop();
		}
		persist( 'guide/session', { reset: true } ).then( function ( r ) {
			state.session = r || { turns: [], draft: null, assistant_turns: 0, cap: cfg.cap, apply: null };
			talk.setSession( state.session );
			view.setDraft( null );
			state.pasteSeq++;
			state.pastePending = false;
			dom.pasteArea.value = '';
			readPaste( false );
			renderTreeHead();
			renderMove();
			talk.note( '' );
			if ( canTalk() ) {
				turn_( { open: true }, null );
			}
		} );
	}

	function setMethod( m ) {
		state.method = 'paste' === m ? 'paste' : 'talk';
		dom.tabTalk.setAttribute( 'aria-selected', 'talk' === state.method ? 'true' : 'false' );
		dom.tabPaste.setAttribute( 'aria-selected', 'paste' === state.method ? 'true' : 'false' );
		talk.conv.hidden = 'paste' === state.method;
		talk.composer.hidden = 'paste' === state.method;
		dom.paste.hidden = 'talk' === state.method;
		renderPreview();
		renderKicker();
		talk.renderComposer();
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
		// A paste still being answered is answered about a draft this edit has
		// just changed: that answer is dropped when it comes.
		state.pasteSeq++;
		state.pastePending = false;
		var next = withOrigin( TV.applyEdit( draft, edit ), state.session.draft );
		setDraft( next );
		if ( ! line ) {
			return;
		}
		if ( canTalk() && ! talk.streaming() ) {
			turn_( { edit: line }, { kind: 'edit', text: line } );
		} else {
			talk.pushUser( { kind: 'edit', text: line } );
		}
	}

	/* ------------------------------------------------------------- paste */

	/*
	 *  One text box, one folder per line, the full path with ">" between
	 *  levels. Read as it is typed (js/vergeml-structure.js); beside it the
	 *  tree of what was understood, in the tree component's own rows, and
	 *  under it one line of fact. A paste that reads clean becomes the draft
	 *  on the right and goes to the turn route for its numbers, exactly as a
	 *  conversation's tree does. A paste with a refusal in it is said out loud
	 *  and makes nothing.
	 */
	var previewView = null;
	var pasteTimer = null;
	var settleTimer = null;

	function structL10n() {
		return {
			/* translators: %s: a line number */
			noName: __( 'Line %s: no name', 'vergelabs-media-library' ),
			/* translators: 1: a line number, 2: the most levels allowed */
			tooDeep: __( 'Line %1$s: deeper than %2$s levels', 'vergelabs-media-library' ),
			/* translators: 1: folders in the paste, 2: the most one paste can hold */
			tooMany: __( '%1$s folders; %2$s is the most one paste can hold', 'vergelabs-media-library' )
		};
	}

	function buildPaste() {
		dom.paste = el( 'div', { class: 'vgml-paste', hidden: 'hidden' } );

		var say = el( 'p', { class: 'vgml-method-say' } );
		say.innerHTML = sprintf(
			/* translators: 1: the ">" sign, 2: an example path, "Hardware > Phones" */
			escapeHtml( __( 'One folder per line, the full path with %1$s between levels: %2$s. A parent that is not listed is created. The preview shows what was understood before anything is made.', 'vergelabs-media-library' ) ),
			'<code>&gt;</code>',
			'<code>Hardware &gt; Phones</code>'
		);
		dom.paste.appendChild( say );

		var cols = el( 'div', { class: 'vgml-paste-cols' } );
		dom.pasteArea = el( 'textarea', { class: 'vgml-paste-area', rows: '12', spellcheck: 'false', 'aria-label': __( 'Paste folders', 'vergelabs-media-library' ) } );
		dom.pasteArea.addEventListener( 'input', function () {
			window.clearTimeout( pasteTimer );
			pasteTimer = window.setTimeout( function () { readPaste( true ); }, 200 );
		} );
		cols.appendChild( dom.pasteArea );

		var box = el( 'div', { class: 'vgml-preview-box' } );
		var head = el( 'div', { class: 'vgml-preview-head' } );
		head.appendChild( el( 'span', { class: 'vgml-preview-title' }, __( 'What that makes', 'vergelabs-media-library' ) ) );
		head.appendChild( el( 'span', { class: 'vgml-preview-read' }, __( 'read as paths', 'vergelabs-media-library' ) ) );
		box.appendChild( head );
		dom.pasteTree = el( 'div', { class: 'vgml-paste-tree' } );
		box.appendChild( dom.pasteTree );
		cols.appendChild( box );
		dom.paste.appendChild( cols );

		dom.readLine = el( 'p', { class: 'vgml-read-line', hidden: 'hidden' } );
		dom.paste.appendChild( dom.readLine );
		dom.refused = el( 'ul', { class: 'vgml-facts vgml-paste-refused', hidden: 'hidden' } );
		dom.paste.appendChild( dom.refused );

		previewView = TV.create( {
			surface: 'folders',
			root: dom.pasteTree,
			nodes: [],
			indent: { step: 22, base: 8 },
			editable: false,
			head: false,
			fold: false,
			openAll: true,
			l10n: treeL10n()
		} );
		previewView.setMode( 'all', true );
		// No count on a folder the paste makes: the dry run has not looked yet,
		// and a zero here would be a number nobody computed.
		previewView.setCounted( false );

		return dom.paste;
	}

	function readPaste( andDraft ) {
		var parsed = STRUCT.parse( dom.pasteArea.value, state.nodes, structL10n() );

		var pv = STRUCT.toPreview( parsed, state.nodes );
		previewView.setTree( pv.nodes );
		previewView.setDraft( parsed.count ? pv.draft : null );
		// An empty box shows an empty preview, not the tree's "No changes yet".
		dom.pasteTree.hidden = ! parsed.count;

		dom.readLine.innerHTML = '';
		if ( parsed.count ) {
			/* translators: %s: folders */
			dom.readLine.appendChild( el( 'b', null, sprintf( _n( '%s folder', '%s folders', parsed.count, 'vergelabs-media-library' ), fmt( parsed.count ) ) ) );
			dom.readLine.appendChild( document.createTextNode( ', '
				/* translators: %s: levels */
				+ sprintf( _n( '%s level deep', '%s levels deep', parsed.levels, 'vergelabs-media-library' ), fmt( parsed.levels ) ) + '. '
				+ ( parsed.reused
					/* translators: %s: folders that exist already */
					? sprintf( _n( '%s already exists and will be reused.', '%s already exist and will be reused.', parsed.reused, 'vergelabs-media-library' ), fmt( parsed.reused ) )
					: __( 'None of them exist yet.', 'vergelabs-media-library' ) ) ) );
		}
		dom.readLine.hidden = ! parsed.count;

		dom.refused.innerHTML = '';
		parsed.refusals.forEach( function ( r ) {
			dom.refused.appendChild( el( 'li', null, r.text ) );
		} );
		dom.refused.hidden = ! parsed.refusals.length;

		if ( andDraft && parsed.count && ! parsed.refusals.length ) {
			pasteDraft( STRUCT.toDraft( parsed, state.nodes ) );
		}
	}

	/*
	 *  The paste as the draft. On screen at once, so the tree on the right
	 *  answers as the person types; to the turn route once the typing settles,
	 *  so the numbers that come back are the matcher's. Until they do the rows
	 *  carry no count and Move waits: pressing it before the session holds
	 *  this draft would move pictures into the draft before it.
	 */
	function pasteDraft( draft ) {
		state.pasteSeq++;
		var seq = state.pasteSeq;
		state.pastePending = true;
		setDraft( draft, true );
		renderPreview();

		window.clearTimeout( settleTimer );
		settleTimer = window.setTimeout( function () {
			if ( seq !== state.pasteSeq ) {
				return;
			}
			queue = queue.then( function () {
				return api( 'POST', 'guide/turn', { draft: draft } ).then( function ( r ) {
					if ( seq !== state.pasteSeq ) {
						return;
					}
					state.pastePending = false;
					if ( r && undefined !== r.fit ) {
						tookFit( r );
					} else {
						renderMove();
						renderPreview();
					}
				}, function ( err ) {
					if ( seq !== state.pasteSeq ) {
						return;
					}
					state.pastePending = false;
					dom.refused.appendChild( el( 'li', null, ( err && err.message ) || __( 'That did not go through. Try again.', 'vergelabs-media-library' ) ) );
					dom.refused.hidden = false;
					renderMove();
					renderPreview();
				} );
			}, function () {} );
		}, 600 );
	}

	/* --------------------------------------------------- the dry run's lines */

	function previewLines() {
		return state.pastePending ? [] : ( ( state.fit && state.fit.preview ) || [] );
	}

	/*
	 *  The dry run looked and gave no answer -- it ran past its budget, or it
	 *  could not score at all -- or it has not answered yet. It says so in the
	 *  lines above; here it takes the numbers away with it. A folder shows no
	 *  count, the Move button offers none, and nothing on the draft reads as a
	 *  zero: zero is what the matcher says after looking, and it never
	 *  finished looking.
	 */
	function fitUnknown() {
		return state.pastePending || !! ( state.fit && false === state.fit.counted );
	}

	function syncCounted() {
		if ( view ) {
			view.setCounted( ! fitUnknown() );
		}
	}

	function renderPreview() {
		var lines = previewLines();
		syncCounted();
		dom.preview.innerHTML = '';
		lines.forEach( function ( line ) {
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
		dom.preview.hidden = ! lines.length;
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

	/*
	 *  How many pictures a Move actually moves.
	 *
	 *  The tree can only say what each folder gains, and on a conversation's
	 *  draft that is not the question. The Move puts every placed picture in
	 *  one folder and takes it out of every other, so a library being
	 *  consolidated has folders shrinking all over and almost nothing gained:
	 *  the screen read "Move 4 pictures" beside "241 pictures move into 19
	 *  folders", both about the same draft. The dry run counted them one way,
	 *  against the matcher, so that is the number.
	 */
	function movingCount( s ) {
		if ( fitUnknown() ) {
			return null;
		}
		return state.fit ? Number( state.fit.move ) || 0 : s.moving;
	}

	function renderMove() {
		var s = view ? view.summary() : { changes: 0, moving: 0 };
		syncCounted();
		var apply = state.session.apply;
		var moving = apply && apply.running;
		dom.stop.hidden = ! moving;
		root.setAttribute( 'data-state', moving ? 'moving' : ( state.undo.available && ! s.changes ? 'done' : 'resting' ) );

		if ( moving ) {
			var r = state.moving || {};
			var moved = Number( r.moved ) || 0;
			/*
			 *  The goal, where there is one.
			 *
			 *  movingCount() returns null when the dry run never counted, and
			 *  Math.max against that read the moved count back as the total:
			 *  "Moving 12 of 12", a finish line copied from the runner. A total
			 *  nobody worked out is not shown, exactly as the button's own
			 *  "Move the draft" shows no number for the same reason.
			 */
			var goal = Number( state.movingGoal ) || 0;
			dom.move.textContent = goal
				/* translators: 1: pictures moved so far, 2: pictures to move */
				? sprintf( __( 'Moving %1$s of %2$s', 'vergelabs-media-library' ), fmt( moved ), fmt( Math.max( moved, goal ) ) )
				/* translators: %s: pictures moved so far */
				: sprintf( __( '%s moved so far', 'vergelabs-media-library' ), fmt( moved ) );
			dom.move.disabled = true;
			dom.undo.hidden = true;
			return;
		}

		if ( s.changes > 0 && described ) {
			var n = movingCount( s );
			if ( null === n ) {
				// No count to offer. The button still works -- the draft is
				// filed the same way whether or not the run finished counting,
				// and a cold library must not be locked out of Move -- unless
				// a paste is still on its way to the session, in which case
				// pressing it would move pictures into the draft before it.
				dom.move.textContent = __( 'Move the draft', 'vergelabs-media-library' );
			} else {
				/* translators: %s: pictures */
				dom.move.textContent = sprintf( _n( 'Move %s picture', 'Move %s pictures', n, 'vergelabs-media-library' ), fmt( n ) );
			}
			dom.move.disabled = state.pastePending;
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
		if ( talk.streaming() ) {
			stop();
		}
		state.movingGoal = movingCount( view.summary() );
		dom.move.disabled = true;
		api( 'POST', 'guide/apply' ).then( function ( r ) {
			took( r );
		} ).catch( function ( err ) {
			talk.note( ( err && err.message ) || __( 'That did not go through. Nothing moved.', 'vergelabs-media-library' ) );
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
		state.fit = null;
		state.pasteSeq++;
		state.pastePending = false;
		refreshTree().then( function () {
			renderTreeHead();
			renderMove();
			renderPreview();
			renderConversation();
			// The folders a paste named exist now; the preview reads them as such.
			readPaste( false );
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
			talk.note( ( err && err.message ) || __( 'That did not go through.', 'vergelabs-media-library' ) );
		} );
	}

	/* --------------------------------------------------------------- go */

	build();
	makeTree();
	renderTreeHead();
	renderConversation();
	renderMove();
	setMethod( 'talk' );
	// The thread is a bounded region now; it opens on its latest turn, where the composer is.
	talk.conv.scrollTop = talk.conv.scrollHeight;
	root.classList.add( 'is-ready' );

	// A Move still running from before this page loaded carries on being watched.
	if ( state.session.apply && state.session.apply.running ) {
		api( 'GET', 'guide/progress' ).then( took ).catch( function () {} );
	}

	// The conversation opens by itself on a described library that has not spoken yet.
	if ( canTalk() && ! ( state.session.turns || [] ).length && ! state.session.draft ) {
		turn_( { open: true }, null );
	}

	window.vgmlFoldersApp = { state: state, view: function () { return view; }, preview: function () { return previewView; }, stop: stop };
}() );
