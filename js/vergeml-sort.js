/*
 *  Sort into folders: one surface.
 *
 *  Say what you want the folders to be, or shape the proposal by hand; the
 *  tree on screen is always the thing the one button at the end would make.
 *  The four steps are a status strip, not four screens. State is the guided
 *  session (core/guide.php): the draft, the turns, the apply.
 *
 *  wp.element without JSX, so the plugin keeps having no build step.
 */
( function () {
	var wp = window.wp;
	if ( ! wp || ! wp.element || ! wp.apiFetch ) {
		return;
	}
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var cfg = window.vgmlSort || {};

	function n( x ) {
		return ( typeof x === 'number' ? x : 0 ).toLocaleString();
	}

	function api( method, route, data ) {
		var o = { path: '/' + ( cfg.ns || 'vergeml/v1' ) + '/' + route, method: method };
		if ( data ) {
			o.data = data;
		}
		return wp.apiFetch( o );
	}

	/* ------------------------------------------------------------ pieces */

	function Steps( p ) {
		return el( 'div', { className: 'vgml-sort-steps' }, p.steps.map( function ( s, i ) {
			return el( 'div', { key: i, className: 'vgml-sort-step is-' + s.state },
				el( 'i', null, String( i + 1 ) ),
				el( 'div', { className: 'vgml-sort-step-text' },
					el( 'b', null, s.title ),
					el( 'span', null, s.sub )
				)
			);
		} ) );
	}

	function Tree( p ) {
		var byParent = {};
		p.folders.forEach( function ( f ) {
			var key = f.parent || '';
			( byParent[ key ] = byParent[ key ] || [] ).push( f );
		} );
		var walk = function ( parent, depth ) {
			var out = [];
			( byParent[ parent ] || [] ).forEach( function ( f ) {
				if ( f.aside ) {
					return;
				}
				out.push( el( 'div', { key: parent + '/' + f.name, className: 'vgml-sort-row' + ( depth ? ' is-child' : '' ), style: { paddingInlineStart: ( depth * 26 ) + 'px' } },
					el( 'input', {
						className: 'vgml-sort-name',
						value: f.name,
						size: Math.max( 4, Math.min( 40, f.name.length + 1 ) ),
						'aria-label': __( 'Folder name', 'vergelabs-media-library' ),
						onChange: function ( e ) { p.onEdit( 'typing', f, e.target.value ); },
						onBlur: function ( e ) { p.onEdit( 'rename', f, e.target.value ); },
						onKeyDown: function ( e ) { if ( e.key === 'Enter' ) { e.target.blur(); } },
					} ),
					el( 'span', { className: 'vgml-sort-count' }, f.count > 0 ? n( f.count ) : '–' ),
					el( 'span', { className: 'vgml-sort-desc', title: f.matches || '' }, f.matches || '' ),
					el( 'button', { type: 'button', className: 'vgml-sort-aside', title: __( 'Its pictures stay unfiled — restore any time', 'vergelabs-media-library' ), onClick: function () { p.onEdit( 'aside', f ); } },
						el( 'span', null, '✕' ), __( 'set aside', 'vergelabs-media-library' ) )
				) );
				out = out.concat( walk( f.name, depth + 1 ) );
			} );
			return out;
		};
		return el( 'div', { className: 'vgml-sort-tree' },
			walk( '', 0 ),
			el( 'div', { className: 'vgml-sort-row is-unfiled' },
				el( 'span', { className: 'vgml-sort-name-static' }, __( 'No folder', 'vergelabs-media-library' ) ),
				el( 'span', { className: 'vgml-sort-count' }, sprintf( __( 'about %s', 'vergelabs-media-library' ), n( p.unfiled ) ) ),
				el( 'span', { className: 'vgml-sort-desc' }, __( 'estimate — the run lists why, picture by picture', 'vergelabs-media-library' ) )
			),
			el( 'div', { className: 'vgml-sort-addrow' },
				el( 'button', { type: 'button', className: 'vgml-btn vgml-btn-ghost', onClick: function () { p.onEdit( 'add', null ); } }, __( '+ Add a top-level folder', 'vergelabs-media-library' ) )
			)
		);
	}

	function Tags( p ) {
		if ( ! p.tags || ! p.tags.length ) {
			return null;
		}
		return el( 'div', { className: 'vgml-sort-tags' },
			el( 'h6', { className: 'vgml-kicker' }, __( 'Tags, to filter by', 'vergelabs-media-library' ) ),
			p.tags.map( function ( t ) {
				return el( 'span', { key: t.name, className: 'vgml-tag' }, t.name + ( t.values && t.values.length ? ' · ' + t.values.slice( 0, 4 ).join( ', ' ) : '' ) );
			} )
		);
	}

	/* --------------------------------------------------------------- app */

	function Sort() {
		var ss = useState( null ), session = ss[ 0 ], setSession = ss[ 1 ];
		var bb = useState( '' ), busy = bb[ 0 ], setBusy = bb[ 1 ];
		var ee = useState( '' ), error = ee[ 0 ], setError = ee[ 1 ];
		var tt = useState( '' ), text = tt[ 0 ], setText = tt[ 1 ];
		var nn = useState( null ), note = nn[ 0 ], setNote = nn[ 1 ];
		var rr = useState( null ), report = rr[ 0 ], setReport = rr[ 1 ];
		var root = document.getElementById( 'vgml-sort' );
		var described = parseInt( root.getAttribute( 'data-described' ) || '0', 10 );
		var proposedOnce = useRef( false );

		var fail = function ( err ) {
			setBusy( '' );
			setError( ( err && err.message ) || __( 'That did not go through. Try again.', 'vergelabs-media-library' ) );
		};

		var save = function ( patch ) {
			return api( 'POST', 'guide/session', { session: patch } ).then( function ( s ) { setSession( s ); return s; } );
		};

		useEffect( function () {
			api( 'GET', 'guide/session' ).then( setSession ).catch( function () { setError( __( 'Could not load the session.', 'vergelabs-media-library' ) ); } );
		}, [] );

		// The folders as they are now, so the draft can be shown as a change to them.
		useEffect( function () {
			if ( ! session || described === 0 || ( session.summary && typeof session.summary.unfiled === 'number' ) ) {
				return;
			}
			api( 'POST', 'guide/summary', {} ).then( function ( s ) {
				setSession( function ( cur ) { return Object.assign( {}, cur, { summary: s } ); } );
			} ).catch( function () {} );
		}, [ session && session.summary ? 1 : 0, described ] );

		/*
		 *  The first proposal, made once per session: the planner reads the
		 *  library and drafts a tree, so there is something to say "no, like
		 *  this" to. A session that already has a draft is left alone.
		 */
		useEffect( function () {
			if ( ! session || described === 0 || proposedOnce.current ) {
				return;
			}
			var hasDraft = session.draft && session.draft.folders && session.draft.folders.length > 0;
			if ( hasDraft || session.state === 'applying' || session.state === 'done' ) {
				return;
			}
			proposedOnce.current = true;
			if ( session.proposals && session.proposals.length ) {
				save( { state: 'shaping', draft: session.proposals[ 0 ].tree } ).catch( fail );
				return;
			}
			setBusy( 'propose' );
			api( 'POST', 'propose', {} ).then( function ( r ) {
				var first = r.proposals && r.proposals[ 0 ] ? r.proposals[ 0 ].tree : { folders: [], tags: [] };
				return save( { state: 'shaping', draft: first } );
			} ).then( function () { setBusy( '' ); } ).catch( fail );
		}, [ session, described ] );

		// While a run is going, keep the line honest.
		useEffect( function () {
			if ( ! session || session.state !== 'applying' ) {
				return;
			}
			var timer = window.setInterval( function () {
				api( 'GET', 'guide/progress' ).then( function ( r ) {
					setReport( r );
					if ( ! r.running ) {
						window.clearInterval( timer );
						setSession( function ( cur ) { return Object.assign( {}, cur, { state: 'done' } ); } );
					}
				} ).catch( function () { window.clearInterval( timer ); } );
			}, 2500 );
			return function () { window.clearInterval( timer ); };
		}, [ session && session.state ] );

		var turn = function ( input ) {
			setBusy( 'turn' );
			setError( '' );
			return api( 'POST', 'guide/turn', input ).then( function ( r ) {
				setNote( { text: r.message, choices: r.choices || [] } );
				setSession( function ( cur ) {
					var next = Object.assign( {}, cur, { state: 'shaping', assistant_turns: r.assistant_turns } );
					if ( r.draft ) {
						next.draft = r.draft;
					}
					return next;
				} );
				setBusy( '' );
			} ).catch( fail );
		};

		var send = function ( t ) {
			var said = String( t || text ).trim();
			if ( ! said || busy ) {
				return;
			}
			setText( '' );
			setNote( { text: sprintf( __( 'Got it — “%s”. Working out what that looks like…', 'vergelabs-media-library' ), said ), choices: [] } );
			turn( { text: said } );
		};

		if ( error && ! session ) {
			return el( 'p', { className: 'vgml-sort-error' }, error );
		}
		if ( ! session ) {
			return el( 'p', { className: 'vgml-note' }, __( 'Loading…', 'vergelabs-media-library' ) );
		}

		var draft = session.draft || { folders: [], tags: [] };
		var folders = draft.folders || [];
		var live = folders.filter( function ( f ) { return ! f.aside; } );
		var asideN = folders.length - live.length;
		var placed = Math.min( described, live.reduce( function ( acc, f ) { return acc + ( f.count || 0 ); }, 0 ) );
		var unfiled = Math.max( 0, described - placed );
		var hasDraft = live.length > 0;
		var applying = session.state === 'applying';
		var done = session.state === 'done';
		var capped = ( session.assistant_turns || 0 ) >= ( cfg.cap || 25 );
		var proposed = !! ( session.proposals && session.proposals.length );

		/*
		 *  The draft against the folders that exist. Kept, added, removed --
		 *  and removed is the one that has to be said out loud, because Move
		 *  deletes every folder the draft does not name.
		 */
		var lower = function ( s ) { return String( s || '' ).toLowerCase(); };
		var current = ( session.summary && session.summary.folders ) || [];
		var nowUnfiled = session.summary && typeof session.summary.unfiled === 'number' ? session.summary.unfiled : null;
		var draftNames = live.map( function ( f ) { return lower( f.name ); } );
		var currentNames = current.map( function ( c ) { return lower( c.name ); } );
		var removed = current.filter( function ( c ) { return draftNames.indexOf( lower( c.name ) ) < 0; } );
		var added = live.filter( function ( f ) { return currentNames.indexOf( lower( f.name ) ) < 0; } );
		var kept = live.length - added.length;
		var removedPictures = removed.reduce( function ( acc, c ) { return acc + ( c.count || 0 ); }, 0 );

		var steps = [
			{ title: __( 'What you have', 'vergelabs-media-library' ), state: described > 0 ? 'done' : 'now',
				sub: described > 0 ? sprintf( __( 'Done — %1$s pictures read, %2$s folders today', 'vergelabs-media-library' ), n( described ), n( current.length ) ) : __( 'Describe the library first', 'vergelabs-media-library' ) },
			{ title: __( 'A first proposal', 'vergelabs-media-library' ), state: hasDraft ? 'done' : ( described > 0 ? 'now' : 'later' ),
				sub: hasDraft
					? ( proposed ? sprintf( __( 'Done — %s folders proposed', 'vergelabs-media-library' ), n( live.length ) ) : sprintf( __( 'A draft of %s folders', 'vergelabs-media-library' ), n( live.length ) ) )
					: ( busy === 'propose' ? __( 'Reading the library…', 'vergelabs-media-library' ) : __( 'Say what you want, or wait for a suggestion', 'vergelabs-media-library' ) ) },
			{ title: __( 'Shape it together', 'vergelabs-media-library' ), state: hasDraft && ! applying && ! done ? 'now' : ( applying || done ? 'done' : 'later' ),
				sub: __( 'You are here — say it, or set folders aside', 'vergelabs-media-library' ) },
			{ title: __( 'Apply', 'vergelabs-media-library' ), state: applying || done ? 'now' : 'later',
				sub: done ? __( 'Done — undo is one click for 24 hours', 'vergelabs-media-library' ) : ( applying ? __( 'Moving pictures…', 'vergelabs-media-library' ) : __( 'Nothing moves until you press Move', 'vergelabs-media-library' ) ) },
		];

		var onEdit = function ( action, folder, value ) {
			var next = folders.slice();
			var words = '';
			if ( action === 'typing' ) {
				next = next.map( function ( f ) { return f === folder ? Object.assign( {}, f, { name: value, original: f.original === undefined ? f.name : f.original } ) : f; } );
				setSession( Object.assign( {}, session, { draft: Object.assign( {}, draft, { folders: next } ) } ) );
				return;
			}
			if ( action === 'rename' ) {
				var from = folder.original !== undefined ? folder.original : folder.name;
				var to = String( value || '' ).trim().replace( /\//g, '-' );
				if ( ! to || to === from ) {
					if ( ! to ) {
						next = next.map( function ( f ) { return f === folder ? Object.assign( {}, f, { name: from, original: undefined } ) : f; } );
						setSession( Object.assign( {}, session, { draft: Object.assign( {}, draft, { folders: next } ) } ) );
					}
					return;
				}
				next = next.map( function ( f ) {
					if ( f === folder ) { return Object.assign( {}, f, { name: to, original: undefined } ); }
					if ( f.parent === from ) { return Object.assign( {}, f, { parent: to } ); }
					return f;
				} );
				words = sprintf( __( 'renamed %1$s to %2$s', 'vergelabs-media-library' ), from, to );
			}
			if ( action === 'aside' ) {
				var names = [ folder.name ];
				var grew = true;
				while ( grew ) {
					grew = false;
					next.forEach( function ( f ) { if ( names.indexOf( f.parent ) >= 0 && names.indexOf( f.name ) < 0 ) { names.push( f.name ); grew = true; } } );
				}
				next = next.map( function ( f ) { return names.indexOf( f.name ) >= 0 ? Object.assign( {}, f, { aside: true } ) : f; } );
			}
			if ( action === 'restore' ) {
				next = next.map( function ( f ) { return Object.assign( {}, f, { aside: false } ); } );
			}
			if ( action === 'add' ) {
				var name = window.prompt( __( 'Folder name', 'vergelabs-media-library' ) );
				if ( ! name || ! name.trim() ) {
					return;
				}
				name = name.trim().replace( /\//g, '-' );
				next.push( { name: name, parent: '', matches: '', classes: [], kinds: [], audience: '', count: 0, aside: false } );
				words = sprintf( __( 'added %s at the top level', 'vergelabs-media-library' ), name );
			}
			setError( '' );
			save( { state: 'shaping', draft: Object.assign( {}, draft, { folders: next } ) } ).then( function () {
				if ( words && ! capped ) {
					turn( { edit: words } );
				}
			} ).catch( fail );
		};

		var apply = function () {
			setBusy( 'apply' );
			setError( '' );
			api( 'POST', 'guide/apply', {} ).then( function ( r ) {
				setReport( r );
				setSession( function ( cur ) { return Object.assign( {}, cur, { state: r.running ? 'applying' : 'done' } ); } );
				setBusy( '' );
			} ).catch( fail );
		};

		var undo = function () {
			setBusy( 'undo' );
			api( 'POST', 'folders-undo', {} ).then( function ( r ) {
				setNote( { text: ( r && r.message ) || __( 'Put back.', 'vergelabs-media-library' ), choices: [] } );
				return save( { state: 'shaping' } );
			} ).then( function () { setReport( null ); setBusy( '' ); } ).catch( fail );
		};

		var chips = [
			__( 'Sort these into Apparel, with Women and Men under it', 'vergelabs-media-library' ),
			__( 'Group them by what room they belong in', 'vergelabs-media-library' ),
			__( 'I don’t want Nature — split Buildings into Modern and Classic instead', 'vergelabs-media-library' ),
		];

		var pct = report && report.total ? Math.round( 100 * ( report.seen || 0 ) / report.total ) : 0;

		return el( Fragment, null,
			el( Steps, { steps: steps } ),
			described === 0
				? el( 'div', { className: 'vgml-sort-empty' },
					el( 'h4', { className: 'vgml-h4' }, __( 'Describe the library first', 'vergelabs-media-library' ) ),
					el( 'p', { className: 'vgml-note' }, __( 'Folders are worked out from the AI descriptions. Run a describe on the AI screen, then come back.', 'vergelabs-media-library' ) ),
					el( 'a', { className: 'button button-primary', href: cfg.aiUrl }, __( 'Go to AI', 'vergelabs-media-library' ) ) )
				: el( 'div', { className: 'vgml-cols' },
					el( 'div', { className: 'vgml-cols-main' },

						el( 'h4', { className: 'vgml-h4' }, __( 'Tell it what you want the folders to be', 'vergelabs-media-library' ) ),
						el( 'p', { className: 'vgml-note' }, __( 'Say it the way you would say it to a person. Talking costs no credits — the pictures have already been looked at — and the proposal below updates before anything moves.', 'vergelabs-media-library' ) ),
						el( 'form', { className: 'vgml-sort-command', onSubmit: function ( e ) { e.preventDefault(); send(); } },
							el( 'input', {
								type: 'text',
								className: 'vgml-input',
								value: text,
								placeholder: capped ? __( 'The turns for this session are used up; shape the tree by hand.', 'vergelabs-media-library' ) : __( 'Say what you want the folders to be…', 'vergelabs-media-library' ),
								onChange: function ( e ) { setText( e.target.value ); },
								disabled: !! busy || capped || applying,
							} ),
							el( 'button', { type: 'submit', className: 'button', disabled: !! busy || capped || applying || ! text.trim() }, __( 'Send', 'vergelabs-media-library' ) )
						),
						el( 'div', { className: 'vgml-sort-chips' }, chips.map( function ( c ) {
							return el( 'button', { key: c, type: 'button', className: 'vgml-tag vgml-tag-outline', disabled: !! busy || capped, onClick: function () { setText( c ); } }, c );
						} ) ),
						note ? el( 'div', { className: 'vgml-banner vgml-sort-note' },
							el( 'div', { className: 'vgml-sort-note-text' }, note.text,
								note.choices && note.choices.length ? el( 'div', { className: 'vgml-sort-chips' }, note.choices.map( function ( c ) {
									return el( 'button', { key: c, type: 'button', className: 'vgml-tag vgml-tag-outline', disabled: !! busy, onClick: function () { turn( { choice: c } ); } }, c );
								} ) ) : null ) ) : null,
						el( 'p', { className: 'vgml-sort-cap' }, sprintf( __( '%1$s of %2$s turns used', 'vergelabs-media-library' ), session.assistant_turns || 0, cfg.cap || 25 ) ),

						el( 'div', { className: 'vgml-sort-handhead' },
							el( 'h4', { className: 'vgml-h4' }, __( 'Or shape it by hand', 'vergelabs-media-library' ) ),
							el( 'span', { className: 'vgml-muted' }, sprintf( __( '%1$s folders · about %2$s of %3$s pictures would get a place', 'vergelabs-media-library' ), n( live.length ), n( placed ), n( described ) ) )
						),
						el( 'p', { className: 'vgml-note' }, __( 'The structure, before anything moves. Counts are estimates from the catalogue; set a folder aside and they follow. Undo is one click for 24 hours.', 'vergelabs-media-library' ) ),
						busy === 'propose' && ! hasDraft
							? el( 'p', { className: 'vgml-sort-waiting' }, __( 'Reading the library and drafting a first proposal…', 'vergelabs-media-library' ) )
							: el( Tree, { folders: folders, unfiled: unfiled, onEdit: onEdit } ),
						asideN > 0 ? el( 'div', { className: 'vgml-sort-asidenote' },
							el( 'span', { className: 'vgml-accent-text' }, asideN === 1
								? __( '1 folder set aside — its pictures stay unfiled.', 'vergelabs-media-library' )
								: sprintf( __( '%s folders set aside — their pictures stay unfiled.', 'vergelabs-media-library' ), n( asideN ) ) ),
							el( 'button', { type: 'button', className: 'vgml-btn vgml-btn-ghost', onClick: function () { onEdit( 'restore' ); } }, __( 'Restore all', 'vergelabs-media-library' ) ) ) : null,
						el( Tags, { tags: draft.tags } ),
						hasDraft && ! done ? el( 'div', { className: 'vgml-sort-diff' },
							el( 'h6', { className: 'vgml-kicker' }, __( 'What Move would change', 'vergelabs-media-library' ) ),
							el( 'p', { className: 'vgml-sort-diff-line' },
								sprintf( __( 'Keeps %1$s of your folders, adds %2$s, removes %3$s.', 'vergelabs-media-library' ), n( kept ), n( added.length ), n( removed.length ) ) ),
							removed.length ? el( 'p', { className: 'vgml-sort-diff-removed' },
								el( 'b', null, __( 'Removed: ', 'vergelabs-media-library' ) ),
								removed.map( function ( c ) { return c.name + ( c.count ? ' (' + n( c.count ) + ')' : '' ); } ).join( ', ' ),
								' — ',
								sprintf( __( 'the %s pictures in them are re-filed where they fit, or left in no folder. Rename a folder in the draft to its current name to keep it.', 'vergelabs-media-library' ), n( removedPictures ) ) ) : null
						) : null,
						error ? el( 'p', { className: 'vgml-sort-error' }, error ) : null,

						done
							? el( 'div', { className: 'vgml-banner vgml-sort-done' },
								el( 'div', { className: 'vgml-sort-note-text' }, el( 'b', null, ( report && report.message ) || __( 'Moved the pictures into folders.', 'vergelabs-media-library' ) ), ' ', __( 'Undo is one click for 24 hours.', 'vergelabs-media-library' ) ),
								el( 'button', { type: 'button', className: 'button', disabled: !! busy, onClick: undo }, __( 'Undo', 'vergelabs-media-library' ) ) )
							: applying
								? el( 'div', { className: 'vgml-sort-progress' },
									el( 'div', { className: 'vgml-sort-bar', role: 'progressbar', 'aria-valuenow': pct, 'aria-valuemin': 0, 'aria-valuemax': 100 }, el( 'i', { style: { width: pct + '%' } } ) ),
									el( 'p', { className: 'vgml-note' }, ( report && report.message ) || __( 'Starting…', 'vergelabs-media-library' ) ),
									el( 'p', { className: 'vgml-note' }, __( 'You can leave this page; it carries on.', 'vergelabs-media-library' ) ) )
								: el( 'div', { className: 'vgml-sort-apply' },
									el( 'button', { type: 'button', className: 'button button-primary', disabled: ! hasDraft || !! busy, onClick: apply },
										removed.length
											? sprintf( __( 'Move about %1$s pictures and remove %2$s folders', 'vergelabs-media-library' ), n( placed ), n( removed.length ) )
											: sprintf( __( 'Move about %s pictures into folders', 'vergelabs-media-library' ), n( placed ) ) ),
									el( 'span', { className: 'vgml-muted' }, __( 'Step 4 — the only step that moves anything. Undo for 24 hours.', 'vergelabs-media-library' ) ) ),
						done ? el( 'p', { className: 'vgml-sort-again' },
							el( 'a', { className: 'button', href: cfg.libraryUrl }, __( 'See the folders ↗', 'vergelabs-media-library' ) ),
							el( 'button', { type: 'button', className: 'vgml-btn vgml-btn-ghost', onClick: function () { api( 'POST', 'guide/session', { session: { state: 'library', draft: { folders: [], tags: [] } } } ).then( function () { window.location.reload(); } ).catch( fail ); } }, __( 'Start a new session', 'vergelabs-media-library' ) ) ) : null
					),
					el( 'div', { className: 'vgml-cols-rail' },
						el( 'div', { className: 'vgml-rail-block' },
							el( 'h6', { className: 'vgml-kicker' }, __( 'The plan right now', 'vergelabs-media-library' ) ),
							el( 'div', { className: 'vgml-rail-row' }, el( 'span', null, __( 'Folders today', 'vergelabs-media-library' ) ), el( 'b', null, n( current.length ) ) ),
							nowUnfiled !== null ? el( 'div', { className: 'vgml-rail-row' }, el( 'span', null, __( 'In no folder today', 'vergelabs-media-library' ) ), el( 'b', null, n( nowUnfiled ) ) ) : null,
							el( 'div', { className: 'vgml-rail-row' }, el( 'span', null, __( 'Folders after Move', 'vergelabs-media-library' ) ), el( 'b', null, n( live.length ) ) ),
							el( 'div', { className: 'vgml-rail-row' }, el( 'span', null, __( 'Placed after Move (estimate)', 'vergelabs-media-library' ) ), el( 'b', null, sprintf( __( 'about %1$s of %2$s', 'vergelabs-media-library' ), n( placed ), n( described ) ) ) ),
							removed.length ? el( 'div', { className: 'vgml-rail-row' }, el( 'span', null, __( 'Folders removed', 'vergelabs-media-library' ) ), el( 'b', { className: 'vgml-accent-text' }, n( removed.length ) ) ) : null,
							el( 'p', { className: 'vgml-note' }, __( 'Nothing moves until you press the button at the end — and undo is one click for 24 hours. Folders are ordinary WordPress categories; they survive this plugin.', 'vergelabs-media-library' ) )
						),
						el( 'div', { className: 'vgml-rail-block' },
							el( 'h6', { className: 'vgml-kicker' }, __( 'Prefer a spreadsheet?', 'vergelabs-media-library' ) ),
							el( 'p', { className: 'vgml-note' }, __( 'Export the structure as a CSV, change it there, and read it back.', 'vergelabs-media-library' ) ),
							el( 'a', { className: 'vgml-btn vgml-btn-ghost vgml-sort-raillink', href: cfg.importUrl }, __( 'Import folders →', 'vergelabs-media-library' ) )
						)
					)
				)
		);
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.getElementById( 'vgml-sort' );
		if ( root ) {
			wp.element.render( el( Sort ), root );
		}
	} );
}() );
