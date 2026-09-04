/* global wp, vgmlGuide */
/*
 *  Guided sorting: four screens that converge on a folder tree.
 *
 *  Written with wp.element (the React WordPress ships) and no JSX, so the
 *  plugin keeps having no build step. One session object, loaded from and
 *  saved to the site; every screen ends in one confirm button whose label
 *  says what happens next. The assistant only ever edits the draft. Filing
 *  is the last click, and it is the same resumable job the chat uses.
 */
( function () {
	'use strict';

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	function api( method, route, data ) {
		var opts = { path: '/' + vgmlGuide.ns + '/guide/' + route, method: method };
		if ( data ) {
			opts.data = data;
		}
		return wp.apiFetch( opts );
	}

	function n( x ) {
		return ( typeof x === 'number' ? x : 0 ).toLocaleString();
	}

	var STEPS = [
		__( 'What you have', 'vergelabs-media-library' ),
		__( 'A first proposal', 'vergelabs-media-library' ),
		__( 'Shape it together', 'vergelabs-media-library' ),
		__( 'Apply', 'vergelabs-media-library' ),
	];
	var STEP_OF = { library: 0, proposal: 1, shaping: 2, review: 2, applying: 3, done: 3 };

	/* ------------------------------------------------------------ pieces */

	function TopBar( p ) {
		return el( 'header', { className: 'vgml-guide-top' },
			el( 'div', { className: 'vgml-guide-steps' }, STEPS.map( function ( s, i ) {
				var cls = 'vgml-guide-step' + ( i === p.step ? ' is-now' : ( i < p.step ? ' is-done' : '' ) );
				return el( 'span', { key: i, className: cls }, el( 'i', null, String( i + 1 ) ), s );
			} ) ),
			el( 'div', { className: 'vgml-guide-meta' }, p.meta || '' )
		);
	}

	function Confirm( p ) {
		return el( 'div', { className: 'vgml-guide-confirm' },
			p.secondary ? el( 'button', { type: 'button', className: 'button', onClick: p.onSecondary, disabled: !! p.busy }, p.secondary ) : null,
			el( 'button', { type: 'button', className: 'button button-primary button-hero', onClick: p.onConfirm, disabled: !! p.busy },
				p.busy ? __( 'One moment…', 'vergelabs-media-library' ) : p.label )
		);
	}

	function Tree( p ) {
		var byParent = {};
		p.folders.forEach( function ( f ) {
			var key = f.parent || '';
			( byParent[ key ] = byParent[ key ] || [] ).push( f );
		} );
		var total = function ( f ) {
			return ( byParent[ f.name ] || [] ).reduce( function ( acc, c ) { return acc + total( c ); }, f.count || 0 );
		};
		var walk = function ( parent, depth ) {
			return ( byParent[ parent ] || [] ).map( function ( f ) {
				var changed = p.changed && p.changed[ f.name ];
				var shown = total( f );
				return el( 'li', { key: parent + '/' + f.name, className: 'vgml-guide-tree-row' + ( changed ? ' is-changed' : '' ) },
					el( 'div', { className: 'vgml-guide-tree-line', style: { paddingInlineStart: ( depth * 20 ) + 'px' } },
						p.editable
							? el( 'input', {
								className: 'vgml-guide-tree-name',
								value: f.name,
								style: { width: Math.min( 36, f.name.length + 2 ) + 'ch' },
								'aria-label': __( 'Folder name', 'vergelabs-media-library' ),
								onChange: function ( e ) { p.onEdit( 'typing', f, e.target.value ); },
								onBlur: function ( e ) { if ( e.target.value !== f.original ) { p.onEdit( 'rename', f, e.target.value ); } },
							} )
							: el( 'span', { className: 'vgml-guide-tree-name' }, f.name ),
						el( 'span', { className: 'vgml-guide-tree-count' }, shown > 0 ? n( shown ) : '' ),
						f.matches ? el( 'span', { className: 'vgml-guide-tree-why', title: f.matches }, f.matches ) : null,
						p.editable ? el( 'span', { className: 'vgml-guide-tree-actions' },
							el( 'button', { type: 'button', className: 'button-link', onClick: function () { p.onEdit( 'add', f ); } }, __( 'add inside', 'vergelabs-media-library' ) ),
							el( 'button', { type: 'button', className: 'button-link', onClick: function () { p.onEdit( 'remove', f ); } }, __( 'remove', 'vergelabs-media-library' ) )
						) : null
					),
					el( 'ul', null, walk( f.name, depth + 1 ) )
				);
			} );
		};
		return el( 'ul', { className: 'vgml-guide-tree' },
			walk( '', 0 ),
			p.editable
				? el( 'li', null, el( 'button', { type: 'button', className: 'button-link vgml-guide-tree-addtop', onClick: function () { p.onEdit( 'add', null ); } }, __( '+ add a top-level folder', 'vergelabs-media-library' ) ) )
				: null
		);
	}

	function Tags( p ) {
		if ( ! p.tags || ! p.tags.length ) {
			return null;
		}
		return el( 'div', { className: 'vgml-guide-tags' },
			el( 'span', { className: 'vgml-guide-label' }, __( 'Tags, to filter by', 'vergelabs-media-library' ) ),
			p.tags.map( function ( t ) {
				return el( 'span', { key: t.name, className: 'vgml-guide-tag' }, t.name + ( t.values && t.values.length ? ' · ' + t.values.slice( 0, 4 ).join( ', ' ) : '' ) );
			} )
		);
	}

	/* ----------------------------------------------------------- screens */

	function Library( p ) {
		var s = p.summary;
		if ( ! s ) {
			return el( 'p', { className: 'vgml-guide-lede' }, __( 'Reading the library…', 'vergelabs-media-library' ) );
		}
		var kinds = s.evidence && s.evidence.kinds ? s.evidence.kinds : {};
		var pct = function ( x ) { return Math.round( ( x || 0 ) * 100 ); };
		var cap = function ( t ) { t = String( t || '' ); return t.charAt( 0 ).toUpperCase() + t.slice( 1 ); };
		var classes = ( s.classes || [] ).slice( 0, 12 );
		var folders = ( s.folders || [] ).slice().sort( function ( a, b ) { return ( b.count || 0 ) - ( a.count || 0 ); } );
		return el( Fragment, null,
			el( 'h1', null, __( 'This library, as the AI sees it', 'vergelabs-media-library' ) ),
			el( 'p', { className: 'vgml-guide-lede' }, sprintf( __( 'The AI has described %s pictures. The proposal on the next screen is built from what it found, which is listed here. Nothing changes on this screen.', 'vergelabs-media-library' ), n( s.total ) ) ),
			el( 'div', { className: 'vgml-guide-cols' },
				el( 'section', { className: 'vgml-guide-what' },
					el( 'h2', null, __( 'What the pictures are', 'vergelabs-media-library' ) ),
					el( 'p', { className: 'vgml-guide-hint' }, __( 'The main thing in each picture, as the AI named it. The twelve most common; counts are estimates.', 'vergelabs-media-library' ) ),
					classes.length
						? el( 'ol', { className: 'vgml-guide-list' }, classes.map( function ( c ) {
							return el( 'li', { key: c.class }, el( 'span', null, cap( c.class ) ), el( 'b', null, n( c.count ) ) );
						} ) )
						: el( 'p', { className: 'vgml-guide-hint' }, __( 'The descriptions have no catalogue record yet; describe the library again to get one.', 'vergelabs-media-library' ) )
				),
				el( 'section', { className: 'vgml-guide-today' },
					el( 'h2', null, __( 'Folders today', 'vergelabs-media-library' ) ),
					el( 'p', { className: 'vgml-guide-hint' }, folders.length
						? sprintf( __( '%s folders, fullest first. The proposal may keep, rename or drop them; you decide before anything moves.', 'vergelabs-media-library' ), n( folders.length ) )
						: __( 'None yet. The proposal starts from a blank tree.', 'vergelabs-media-library' ) ),
					folders.length
						? el( 'ul', { className: 'vgml-guide-list' }, folders.slice( 0, 12 ).map( function ( f ) {
							return el( 'li', { key: ( f.parent || '' ) + '>' + f.name },
								el( 'span', null, f.name, f.parent ? el( 'small', null, __( 'in', 'vergelabs-media-library' ) + ' ' + f.parent ) : null ),
								el( 'b', null, n( f.count ) ) );
						} ) )
						: null,
					folders.length > 12 ? el( 'p', { className: 'vgml-guide-hint' }, sprintf( __( 'and %s more', 'vergelabs-media-library' ), n( folders.length - 12 ) ) ) : null
				)
			),
			el( 'div', { className: 'vgml-guide-evidence' },
				el( 'span', { className: 'vgml-guide-label' }, __( 'What the assistant can go by', 'vergelabs-media-library' ) ),
				el( 'span', null, sprintf( __( '%s%% name a brand', 'vergelabs-media-library' ), pct( s.evidence.brand ) ) ),
				el( 'span', null, sprintf( __( '%s%% name a size', 'vergelabs-media-library' ), pct( s.evidence.size ) ) ),
				el( 'span', null, sprintf( __( '%s%% show who they are for', 'vergelabs-media-library' ), pct( s.evidence.audience ) ) ),
				Object.keys( kinds ).map( function ( k ) { return el( 'span', { key: k }, n( kinds[ k ] ) + ' ' + k ); } )
			),
			el( 'label', { className: 'vgml-guide-goal' },
				__( 'Tell it your goal first (optional)', 'vergelabs-media-library' ),
				el( 'textarea', {
					rows: 2,
					value: p.goal,
					placeholder: __( 'e.g. a tech blog — folders should follow topics, not dates', 'vergelabs-media-library' ),
					onChange: function ( e ) { p.setGoal( e.target.value ); },
				} )
			),
			p.error ? el( 'p', { className: 'vgml-guide-error' }, p.error ) : null,
			el( Confirm, { label: __( 'This is my library, show me a proposal →', 'vergelabs-media-library' ), onConfirm: p.onConfirm, busy: p.busy } )
		);
	}

	function Proposal( p ) {
		if ( ! p.proposals || ! p.proposals.length ) {
			return el( 'p', { className: 'vgml-guide-lede' }, __( 'Drawing two proposals…', 'vergelabs-media-library' ) );
		}
		return el( Fragment, null,
			el( 'h1', null, __( 'Two ways this library could be organised', 'vergelabs-media-library' ) ),
			el( 'p', { className: 'vgml-guide-lede' }, __( 'Counts are estimates from the catalogue. Pick one as a starting point; everything stays adjustable on the next screen.', 'vergelabs-media-library' ) ),
			el( 'div', { className: 'vgml-guide-two' }, p.proposals.map( function ( pr, i ) {
				return el( 'section', { key: i, className: 'vgml-guide-proposal' },
					el( 'h2', null, pr.name ),
					el( Tree, { folders: pr.tree.folders } ),
					el( Tags, { tags: pr.tree.tags } ),
					el( 'p', { className: 'vgml-guide-pick' }, el( 'button', { type: 'button', className: 'button button-primary', disabled: p.busy, onClick: function () { p.onPick( pr.tree ); } },
						i === 0 ? __( 'Start from the first', 'vergelabs-media-library' ) : __( 'Start from the second', 'vergelabs-media-library' ) ) )
				);
			} ) ),
			el( 'p', { className: 'vgml-guide-neither' }, el( 'button', { type: 'button', className: 'button', disabled: p.busy, onClick: function () { p.onPick( null ); } }, __( 'Neither, let me explain', 'vergelabs-media-library' ) ) )
		);
	}

	function Shaping( p ) {
		var st = useState( '' );
		var text = st[ 0 ];
		var setText = st[ 1 ];
		var bottom = useRef( null );
		useEffect( function () {
			if ( bottom.current && bottom.current.scrollIntoView ) {
				bottom.current.scrollIntoView( { block: 'end' } );
			}
		}, [ p.turns.length, p.busy ] );
		var send = function ( payload ) {
			if ( p.busy || p.capped ) {
				return;
			}
			setText( '' );
			p.onTurn( payload );
		};
		var placed = p.draft.folders.reduce( function ( acc, f ) { return acc + ( f.count || 0 ); }, 0 );
		var last = p.turns.length ? p.turns[ p.turns.length - 1 ] : null;
		return el( 'div', { className: 'vgml-guide-shaping' },
			el( 'section', { className: 'vgml-guide-treepane' },
				el( 'h2', null, sprintf( __( 'Version %1$s · %2$s folders · about %3$s pictures placed', 'vergelabs-media-library' ), p.draft.version, p.draft.folders.length, n( placed ) ) ),
				el( 'p', { className: 'vgml-guide-hint' }, __( 'Rename in place, add or remove; the assistant sees every change.', 'vergelabs-media-library' ) ),
				el( Tree, { folders: p.draft.folders, editable: true, changed: p.changed, onEdit: p.onEdit } ),
				el( Tags, { tags: p.draft.tags } ),
				el( Confirm, {
					label: __( 'This is the structure I want →', 'vergelabs-media-library' ),
					onConfirm: p.onConfirm,
					busy: p.busy,
					secondary: p.canUndo ? __( 'Back one version', 'vergelabs-media-library' ) : null,
					onSecondary: p.onUndo,
				} )
			),
			el( 'section', { className: 'vgml-guide-chat', 'aria-live': 'polite' },
				el( 'div', { className: 'vgml-guide-turns' },
					p.turns.length === 0
						? el( 'div', { className: 'vgml-msg is-ai' }, __( 'Tell me how you think about this library, or ask me to change something in the tree. I will say what the evidence supports and ask when I am unsure.', 'vergelabs-media-library' ) )
						: null,
					p.turns.map( function ( t, i ) {
						var isLast = last === t;
						return el( 'div', { key: i, className: 'vgml-msg ' + ( t.role === 'user' ? 'is-me' : 'is-ai' ) },
							t.text,
							t.role === 'assistant' && t.choices && t.choices.length && isLast && ! p.busy
								? el( 'div', { className: 'vgml-chips' }, t.choices.map( function ( c ) {
									return el( 'button', { key: c, type: 'button', className: 'vgml-chip', onClick: function () { send( { choice: c } ); } }, c );
								} ) )
								: null
						);
					} ),
					p.busy ? el( 'div', { className: 'vgml-msg is-ai is-thinking' }, __( 'Thinking…', 'vergelabs-media-library' ) ) : null,
					p.error ? el( 'div', { className: 'vgml-msg is-error' }, p.error ) : null,
					el( 'div', { ref: bottom } )
				),
				el( 'form', { className: 'vgml-guide-compose', onSubmit: function ( e ) { e.preventDefault(); if ( text.trim() ) { send( { text: text.trim() } ); } } },
					el( 'input', {
						type: 'text',
						value: text,
						placeholder: p.capped ? __( 'The turns for this session are used up; shape the tree by hand.', 'vergelabs-media-library' ) : __( 'e.g. monitors by size, colour and brand', 'vergelabs-media-library' ),
						onChange: function ( e ) { setText( e.target.value ); },
						disabled: p.busy || p.capped,
					} ),
					el( 'button', { type: 'submit', className: 'button button-primary', disabled: p.busy || p.capped || ! text.trim() }, __( 'Send', 'vergelabs-media-library' ) )
				),
				el( 'p', { className: 'vgml-guide-cap' }, sprintf( __( '%1$s of %2$s turns used', 'vergelabs-media-library' ), p.used, vgmlGuide.cap ) )
			)
		);
	}

	function Review( p ) {
		var total = p.draft.folders.reduce( function ( acc, f ) { return acc + ( f.count || 0 ); }, 0 );
		return el( Fragment, null,
			el( 'h1', null, __( 'The structure, before anything moves', 'vergelabs-media-library' ) ),
			el( 'p', { className: 'vgml-guide-lede' }, sprintf( __( '%1$s folders. About %2$s of %3$s pictures have a place; the rest stay unfiled, and the run will say why. Undo is one click for 24 hours.', 'vergelabs-media-library' ), p.draft.folders.length, n( total ), n( p.described ) ) ),
			el( Tree, { folders: p.draft.folders } ),
			el( Tags, { tags: p.draft.tags } ),
			p.error ? el( 'p', { className: 'vgml-guide-error' }, p.error ) : null,
			el( Confirm, {
				label: sprintf( __( 'File %s pictures now', 'vergelabs-media-library' ), n( p.described ) ),
				onConfirm: p.onConfirm,
				busy: p.busy,
				secondary: __( 'Back to shaping', 'vergelabs-media-library' ),
				onSecondary: p.onBack,
			} )
		);
	}

	function Apply( p ) {
		var r = p.report || {};
		var pct = r.total ? Math.round( 100 * ( r.seen || 0 ) / r.total ) : 0;
		return el( Fragment, null,
			el( 'h1', null, r.running ? sprintf( __( 'Filing %s pictures', 'vergelabs-media-library' ), n( r.total ) ) : __( 'Done', 'vergelabs-media-library' ) ),
			el( 'div', { className: 'vgml-guide-bar', role: 'progressbar', 'aria-valuenow': pct, 'aria-valuemin': 0, 'aria-valuemax': 100 }, el( 'i', { style: { width: pct + '%' } } ) ),
			el( 'p', { className: 'vgml-guide-lede' }, r.message || __( 'Starting…', 'vergelabs-media-library' ) ),
			r.running
				? el( 'p', { className: 'vgml-guide-hint' }, __( 'You can leave this page; it carries on.', 'vergelabs-media-library' ) )
				: el( 'p', { className: 'vgml-guide-confirm' },
					el( 'a', { className: 'button button-primary button-hero', href: vgmlGuide.foldersUrl }, __( 'See the folders', 'vergelabs-media-library' ) ),
					el( 'button', { type: 'button', className: 'button', onClick: p.onStartOver }, __( 'Start a new session', 'vergelabs-media-library' ) ) )
		);
	}

	/* ------------------------------------------------------------ the app */

	function Guide() {
		var ss = useState( null ), session = ss[ 0 ], setSession = ss[ 1 ];
		var bb = useState( false ), busy = bb[ 0 ], setBusy = bb[ 1 ];
		var ee = useState( '' ), error = ee[ 0 ], setError = ee[ 1 ];
		var rr = useState( null ), report = rr[ 0 ], setReport = rr[ 1 ];
		var cc = useState( {} ), changed = cc[ 0 ], setChanged = cc[ 1 ];
		var root = document.getElementById( 'vgml-guide' );
		var described = parseInt( root.getAttribute( 'data-described' ) || '0', 10 );
		var state = session ? session.state : '';

		useEffect( function () {
			api( 'GET', 'session' ).then( setSession ).catch( function () { setError( __( 'Could not load the session.', 'vergelabs-media-library' ) ); } );
		}, [] );

		useEffect( function () {
			if ( state !== 'library' || ! session || session.summary ) {
				return;
			}
			api( 'POST', 'summary' ).then( function ( s ) {
				setSession( function ( cur ) { return Object.assign( {}, cur, { summary: s } ); } );
			} ).catch( function () { setError( __( 'Could not read the library.', 'vergelabs-media-library' ) ); } );
		}, [ state ] );

		useEffect( function () {
			if ( state !== 'applying' ) {
				return;
			}
			var tick = function () {
				api( 'GET', 'progress' ).then( function ( r ) {
					setReport( r );
					if ( r.state === 'done' ) {
						setSession( function ( cur ) { return Object.assign( {}, cur, { state: 'done' } ); } );
					}
				} ).catch( function () {} );
			};
			tick();
			var t = setInterval( tick, 3000 );
			return function () { clearInterval( t ); };
		}, [ state ] );

		var fail = function ( e ) {
			setError( e && e.message ? e.message : __( 'That did not work. Try again.', 'vergelabs-media-library' ) );
			setBusy( false );
		};

		var save = function ( patch ) {
			var next = Object.assign( {}, session, patch );
			setSession( next );
			return api( 'POST', 'session', { session: next } ).then( function ( saved ) { setSession( saved ); return saved; } );
		};

		var turn = function ( payload, draft ) {
			setBusy( true );
			setError( '' );
			var mine = payload.text || payload.choice || ( payload.edit ? sprintf( __( 'I %s', 'vergelabs-media-library' ), payload.edit ) : '' );
			setSession( function ( cur ) { return Object.assign( {}, cur, { turns: ( cur.turns || [] ).concat( [ { role: 'user', text: mine } ] ) } ); } );
			return api( 'POST', 'turn', Object.assign( { draft: draft || session.draft }, payload ) ).then( function ( a ) {
				var marks = {};
				if ( a.draft ) {
					var before = {};
					( ( draft || session.draft ).folders || [] ).forEach( function ( f ) { before[ f.parent + '/' + f.name ] = f; } );
					a.draft.folders.forEach( function ( f ) {
						var was = before[ f.parent + '/' + f.name ];
						if ( ! was || was.matches !== f.matches ) {
							marks[ f.name ] = true;
						}
					} );
				}
				setChanged( marks );
				setSession( function ( cur ) {
					return Object.assign( {}, cur, {
						draft: a.draft || cur.draft,
						assistant_turns: a.assistant_turns,
						state: 'shaping',
						turns: ( cur.turns || [] ).concat( [ { role: 'assistant', text: a.message, choices: a.choices || [] } ] ),
					} );
				} );
				setBusy( false );
			} ).catch( fail );
		};

		if ( error && ! session ) {
			return el( 'p', { className: 'vgml-guide-error' }, error );
		}
		if ( ! session ) {
			return el( 'p', { className: 'vgml-guide-lede' }, __( 'Loading…', 'vergelabs-media-library' ) );
		}
		if ( described === 0 ) {
			return el( 'div', { className: 'vgml-guide-app' },
				el( TopBar, { step: 0 } ),
				el( 'main', { className: 'vgml-guide-main vgml-guide-empty' },
					el( 'h1', null, __( 'Describe the library first', 'vergelabs-media-library' ) ),
					el( 'p', { className: 'vgml-guide-lede' }, __( 'The guide reasons from the AI descriptions. Run a describe on the AI screen, then come back.', 'vergelabs-media-library' ) ),
					el( 'a', { className: 'button button-primary button-hero', href: vgmlGuide.aiUrl }, __( 'Go to AI', 'vergelabs-media-library' ) )
				)
			);
		}

		var step = STEP_OF[ state ] || 0;
		var body;
		if ( state === 'library' ) {
			body = el( Library, {
				summary: session.summary,
				goal: session.goal || '',
				setGoal: function ( g ) { setSession( Object.assign( {}, session, { goal: g } ) ); },
				busy: busy,
				error: error,
				onConfirm: function () {
					setBusy( true );
					setError( '' );
					save( { goal: session.goal || '' } )
						.then( function () { return api( 'POST', 'propose', { goal: session.goal || '' } ); } )
						.then( function ( r ) {
							setSession( function ( cur ) { return Object.assign( {}, cur, { state: 'proposal', proposals: r.proposals } ); } );
							setBusy( false );
						} )
						.catch( fail );
				},
			} );
		} else if ( state === 'proposal' ) {
			body = el( Proposal, {
				proposals: session.proposals,
				busy: busy,
				onPick: function ( tree ) {
					setBusy( true );
					setError( '' );
					save( { state: 'shaping', draft: tree || { folders: [], tags: [] } } ).then( function () { setBusy( false ); } ).catch( fail );
				},
			} );
		} else if ( state === 'shaping' ) {
			body = el( Shaping, {
				draft: session.draft,
				turns: session.turns || [],
				busy: busy,
				error: error,
				changed: changed,
				used: session.assistant_turns || 0,
				capped: ( session.assistant_turns || 0 ) >= vgmlGuide.cap,
				canUndo: ( session.history || [] ).length > 0,
				onTurn: function ( payload ) { turn( payload ); },
				onEdit: function ( action, folder, value ) {
					var folders = session.draft.folders.slice();
					var words = '';
					if ( action === 'typing' ) {
						folders = folders.map( function ( f ) { return f === folder ? Object.assign( {}, f, { name: value, original: f.original === undefined ? f.name : f.original } ) : f; } );
						setSession( Object.assign( {}, session, { draft: Object.assign( {}, session.draft, { folders: folders } ) } ) );
						return;
					}
					if ( action === 'rename' ) {
						var from = folder.original !== undefined ? folder.original : folder.name;
						if ( ! value.trim() || value === from ) {
							return;
						}
						folders = folders.map( function ( f ) {
							if ( f === folder ) { return Object.assign( {}, f, { name: value.trim(), original: undefined } ); }
							if ( f.parent === from ) { return Object.assign( {}, f, { parent: value.trim() } ); }
							return f;
						} );
						words = sprintf( __( 'renamed %1$s to %2$s', 'vergelabs-media-library' ), from, value.trim() );
					}
					if ( action === 'remove' ) {
						folders = folders.filter( function ( f ) { return f !== folder && f.parent !== folder.name; } );
						words = sprintf( __( 'removed %s', 'vergelabs-media-library' ), folder.name );
					}
					if ( action === 'add' ) {
						var name = window.prompt( __( 'Folder name', 'vergelabs-media-library' ) );
						if ( ! name || ! name.trim() ) {
							return;
						}
						name = name.trim().replace( /\//g, '-' );
						folders.push( { name: name, parent: folder ? folder.name : '', matches: '', classes: [], kinds: [], audience: '', count: 0 } );
						words = folder
							? sprintf( __( 'added %1$s under %2$s', 'vergelabs-media-library' ), name, folder.name )
							: sprintf( __( 'added %s at the top level', 'vergelabs-media-library' ), name );
					}
					var draft = Object.assign( {}, session.draft, { folders: folders } );
					save( { draft: draft } ).then( function ( saved ) {
						if ( words && ( saved.assistant_turns || 0 ) < vgmlGuide.cap ) {
							turn( { edit: words }, saved.draft );
						}
					} ).catch( fail );
				},
				onUndo: function () {
					var h = session.history || [];
					var last = h[ h.length - 1 ];
					if ( last ) {
						save( { draft: last.draft } ).catch( fail );
					}
				},
				onConfirm: function () { save( { state: 'review' } ).catch( fail ); },
			} );
		} else if ( state === 'review' ) {
			body = el( Review, {
				draft: session.draft,
				described: described,
				busy: busy,
				error: error,
				onBack: function () { save( { state: 'shaping' } ).catch( fail ); },
				onConfirm: function () {
					setBusy( true );
					setError( '' );
					api( 'POST', 'apply' ).then( function ( r ) {
						setReport( r );
						setSession( function ( cur ) { return Object.assign( {}, cur, { state: 'applying' } ); } );
						setBusy( false );
					} ).catch( fail );
				},
			} );
		} else {
			body = el( Apply, {
				report: report,
				onStartOver: function () {
					api( 'POST', 'session', { session: { state: 'library', draft: { folders: [], tags: [] } } } ).then( function () { window.location.reload(); } ).catch( fail );
				},
			} );
		}

		var meta = state === 'shaping'
			? sprintf( __( 'version %1$s · %2$s folders', 'vergelabs-media-library' ), session.draft.version, session.draft.folders.length )
			: ( state === 'library' && session.summary ? sprintf( __( '%s pictures described', 'vergelabs-media-library' ), n( session.summary.total ) ) : '' );

		return el( 'div', { className: 'vgml-guide-app' },
			el( TopBar, { step: step, meta: meta } ),
			el( 'main', { className: 'vgml-guide-main' }, body )
		);
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.getElementById( 'vgml-guide' );
		if ( root ) {
			wp.element.render( el( Guide ), root );
		}
	} );
} )();
