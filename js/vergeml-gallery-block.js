/*
 *  The folder gallery block, in the editor.
 *
 *  Plain JavaScript with wp.element.createElement rather than JSX, so the plugin
 *  still has no build step -- the same rule the rest of it follows. It is more
 *  verbose to read and it means the file you ship is the file you wrote, which
 *  matters more here than the syntax does.
 *
 *  The preview is ServerSideRender, so what the editor shows is literally what
 *  the front of the site will render. A block that draws its own approximation
 *  in the editor has two renderers to keep in step, and they drift.
 */

( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.blockEditor ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;

	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;

	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var Placeholder = wp.components.Placeholder;
	var Spinner = wp.components.Spinner;

	var ServerSideRender = wp.serverSideRender;

	var cfg = window.vergemlGallery || {};
	var l10n = cfg.l10n || {};

	/*
	 *  Folders are fetched once and shared by every instance of the block.
	 *
	 *  Five galleries on a page is five copies of the same list otherwise, and
	 *  the list changes about as often as somebody makes a folder.
	 */
	var folderCache = null;
	var folderWaiting = null;

	function loadFolders() {

		if ( folderCache ) {
			return Promise.resolve( folderCache );
		}

		if ( folderWaiting ) {
			return folderWaiting;
		}

		folderWaiting = wp.apiFetch( {
			path: '/vergeml/v1/gallery-folders?taxonomy=' + encodeURIComponent( cfg.taxonomy || '' ),
		} ).then( function ( res ) {
			folderCache = ( res && res.folders ) || [];
			return folderCache;
		} ).catch( function () {
			folderCache = [];
			return folderCache;
		} );

		return folderWaiting;
	}

	function Edit( props ) {

		var attributes = props.attributes;
		var setAttributes = props.setAttributes;

		var state = useState( null );
		var folders = state[ 0 ];
		var setFolders = state[ 1 ];

		useEffect( function () {
			loadFolders().then( setFolders );
		}, [] );

		var options = [ { value: 0, label: l10n.choose || 'Choose a folder' } ];

		( folders || [] ).forEach( function ( f ) {
			options.push( {
				value: f.id,
				// The count is the reason somebody picks one folder over another.
				label: f.label + ( f.count ? '  (' + f.count + ')' : '' ),
			} );
		} );

		var controls = el( InspectorControls, {},
			el( PanelBody, { title: l10n.title || 'Folder gallery' },

				el( SelectControl, {
					label: l10n.folder || 'Folder',
					value: attributes.folder,
					options: options,
					onChange: function ( v ) { setAttributes( { folder: parseInt( v, 10 ) || 0 } ); },
				} ),

				el( SelectControl, {
					label: l10n.layout || 'Layout',
					value: attributes.layout,
					options: [
						{ value: 'grid', label: l10n.layoutGrid || 'Grid' },
						{ value: 'carousel', label: l10n.layoutCarousel || 'Carousel' },
					],
					onChange: function ( v ) { setAttributes( { layout: v } ); },
				} ),

				el( ToggleControl, {
					label: l10n.subfolders || 'Include sub-folders',
					checked: !! attributes.children,
					onChange: function ( v ) { setAttributes( { children: !! v } ); },
				} ),

				el( RangeControl, {
					label: l10n.columns || 'Columns',
					value: attributes.columns,
					min: 1,
					max: 8,
					onChange: function ( v ) { setAttributes( { columns: v } ); },
				} ),

				el( RangeControl, {
					label: l10n.limit || 'Maximum images',
					help: l10n.limitHelp,
					value: attributes.limit,
					min: 0,
					max: 100,
					onChange: function ( v ) { setAttributes( { limit: v } ); },
				} ),

				el( SelectControl, {
					label: l10n.order || 'Order',
					value: attributes.orderBy,
					options: [
						{ value: 'name', label: l10n.orderName || 'By name' },
						{ value: 'newest', label: l10n.orderDate || 'Newest first' },
						{ value: 'oldest', label: l10n.orderOldest || 'Oldest first' },
						{ value: 'manual', label: l10n.orderManual || 'The order set in the library' },
					],
					onChange: function ( v ) { setAttributes( { orderBy: v } ); },
				} ),

				el( SelectControl, {
					label: l10n.size || 'Image size',
					value: attributes.size,
					options: cfg.sizes || [ { value: 'large', label: 'Large' } ],
					onChange: function ( v ) { setAttributes( { size: v } ); },
				} ),

				el( SelectControl, {
					label: l10n.linkTo || 'Link to',
					value: attributes.linkTo,
					options: [
						{ value: 'none', label: l10n.linkNone || 'Nothing' },
						{ value: 'lightbox', label: l10n.linkLightbox || 'A lightbox' },
						{ value: 'file', label: l10n.linkFile || 'The image file' },
						{ value: 'post', label: l10n.linkPage || 'The attachment page' },
					],
					onChange: function ( v ) { setAttributes( { linkTo: v } ); },
				} )
			)
		);

		var body;

		if ( null === folders ) {
			body = el( Placeholder, { label: l10n.title }, el( Spinner ) );
		} else if ( ! folders.length ) {
			body = el( Placeholder, { label: l10n.title, instructions: l10n.noFolders } );
		} else if ( ! attributes.folder ) {
			/*
			 *  A prompt rather than an empty rectangle. An unconfigured block that
			 *  renders nothing looks like a broken block.
			 */
			body = el( Placeholder, {
				label: l10n.title,
				instructions: l10n.pick,
			}, el( SelectControl, {
				value: attributes.folder,
				options: options,
				onChange: function ( v ) { setAttributes( { folder: parseInt( v, 10 ) || 0 } ); },
			} ) );
		} else {
			body = el( ServerSideRender, {
				block: 'vergelabs/folder-gallery',
				attributes: attributes,
				// The server sends nothing for an empty folder, which is right on the
				// front of the site and unhelpful here.
				EmptyResponsePlaceholder: function () {
					return el( Placeholder, { label: l10n.title, instructions: l10n.empty } );
				},
			} );
		}

		var blockProps = useBlockProps ? useBlockProps() : {};

		return el( Fragment, {}, controls, el( 'div', blockProps, body ) );
	}

	wp.blocks.registerBlockType( 'vergelabs/folder-gallery', {
		title: l10n.title || 'Folder gallery',
		description: l10n.description,
		icon: 'images-alt2',
		category: 'media',
		keywords: [ 'gallery', 'folder', 'media' ],
		edit: Edit,
		// Rendered on the server, so nothing is saved into the post but the block
		// comment -- which is what keeps the gallery current when the folder changes.
		save: function () { return null; },
	} );

} )( window.wp );
