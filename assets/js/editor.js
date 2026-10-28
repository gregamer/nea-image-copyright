( function ( wp ) {
	'use strict';

	if ( window.neaImageCopyrightEditorLoaded ) {
		return;
	}
	window.neaImageCopyrightEditorLoaded = true;

	var hooks = wp.hooks;
	var compose = wp.compose;
	var element = wp.element;
	var components = wp.components;
	var blockEditor = wp.blockEditor;
	var data = wp.data;
	var createHigherOrderComponent = compose.createHigherOrderComponent;
	var createElement = element.createElement;
	var Fragment = element.Fragment;
	var useState = element.useState;
	var useEffect = element.useEffect;
	var useRef = element.useRef;
	var createPortal = element.createPortal;
	var TextControl = components.TextControl;
	var InspectorControls = blockEditor.InspectorControls;
	var useSelect = data.useSelect;
	var useDispatch = data.useDispatch;

	var META_KEY =
		( window.neaImageCopyright && window.neaImageCopyright.metaKey ) ||
		'_nea_image_author';
	var LABEL =
		( window.neaImageCopyright && window.neaImageCopyright.label ) || 'Avtor';

	var SUPPORTED_BLOCKS = {
		'core/image': true,
		'core/media-text': true,
	};

	/**
	 * Find the Alternative text control in the block inspector sidebar.
	 */
	function findAltControlRoot() {
		var sidebar =
			document.querySelector( '.interface-complementary-area' ) ||
			document.querySelector( '.edit-post-sidebar' ) ||
			document.querySelector( '.editor-sidebar' );

		if ( ! sidebar ) {
			return null;
		}

		var links = sidebar.querySelectorAll( 'a[href*="w3.org/WAI"]' );
		for ( var i = 0; i < links.length; i++ ) {
			var href = links[ i ].getAttribute( 'href' ) || '';
			if ( href.indexOf( 'alt' ) === -1 && href.indexOf( 'text-alternatives' ) === -1 ) {
				continue;
			}
			var candidate =
				links[ i ].closest( '.components-base-control' ) ||
				links[ i ].closest( '.components-tools-panel-item' ) ||
				links[ i ].closest( '.components-panel__row' );
			if ( candidate ) {
				return candidate;
			}
		}

		var labels = sidebar.querySelectorAll( 'label' );
		for ( var j = 0; j < labels.length; j++ ) {
			var text = ( labels[ j ].textContent || '' ).toLowerCase();
			if (
				text.indexOf( 'alternative' ) !== -1 ||
				text.indexOf( 'alternativ' ) !== -1 ||
				text.trim() === 'alt'
			) {
				var root =
					labels[ j ].closest( '.components-base-control' ) ||
					labels[ j ].closest( '.components-tools-panel-item' );
				if ( root ) {
					return root;
				}
			}
		}

		return null;
	}

	function removeOrphanPortals( keepNode ) {
		var nodes = document.querySelectorAll( '.nea-image-author-portal' );
		for ( var i = 0; i < nodes.length; i++ ) {
			if ( keepNode && nodes[ i ] === keepNode ) {
				continue;
			}
			if ( nodes[ i ].parentNode ) {
				nodes[ i ].parentNode.removeChild( nodes[ i ] );
			}
		}
	}

	function AuthorField( props ) {
		var attachmentId = props.attachmentId;
		var portalTarget = props.portalTarget;
		var placement = props.placement;
		var saveTimeout = useRef( null );

		var meta = useSelect(
			function ( select ) {
				if ( ! attachmentId ) {
					return '';
				}
				var record = select( 'core' ).getEditedEntityRecord(
					'postType',
					'attachment',
					attachmentId
				);
				if ( record && typeof record.meta === 'object' && record.meta ) {
					return record.meta[ META_KEY ] || '';
				}
				var entity = select( 'core' ).getEntityRecord(
					'postType',
					'attachment',
					attachmentId
				);
				if ( entity && entity.meta ) {
					return entity.meta[ META_KEY ] || '';
				}
				return '';
			},
			[ attachmentId ]
		);

		var isResolving = useSelect(
			function ( select ) {
				if ( ! attachmentId ) {
					return false;
				}
				return select( 'core' ).isResolving( 'getEntityRecord', [
					'postType',
					'attachment',
					attachmentId,
				] );
			},
			[ attachmentId ]
		);

		var editEntityRecord = useDispatch( 'core' ).editEntityRecord;
		var saveEditedEntityRecord = useDispatch( 'core' ).saveEditedEntityRecord;

		var localValue = typeof meta === 'string' ? meta : '';

		function onChange( value ) {
			if ( ! attachmentId ) {
				return;
			}

			var store = data.select( 'core' );
			var edited = store.getEditedEntityRecord(
				'postType',
				'attachment',
				attachmentId
			);
			var entity = store.getEntityRecord(
				'postType',
				'attachment',
				attachmentId
			);
			var currentMeta =
				( edited && edited.meta ) || ( entity && entity.meta ) || {};
			var nextMeta = Object.assign( {}, currentMeta );
			nextMeta[ META_KEY ] = value;

			editEntityRecord( 'postType', 'attachment', attachmentId, {
				meta: nextMeta,
			} );

			if ( saveTimeout.current ) {
				clearTimeout( saveTimeout.current );
			}

			saveTimeout.current = setTimeout( function () {
				saveEditedEntityRecord( 'postType', 'attachment', attachmentId );
			}, 600 );
		}

		useEffect( function () {
			return function () {
				if ( saveTimeout.current ) {
					clearTimeout( saveTimeout.current );
				}
			};
		}, [] );

		if ( placement === 'pending' ) {
			return null;
		}

		var control = createElement( TextControl, {
			label: LABEL,
			value: localValue,
			onChange: onChange,
			disabled: isResolving && localValue === '',
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
		} );

		var wrapped = createElement(
			'div',
			{
				className: 'nea-image-author-control',
				style: { marginBottom: '16px' },
			},
			control
		);

		if ( placement === 'portal' && portalTarget ) {
			return createPortal( wrapped, portalTarget );
		}

		return createElement(
			InspectorControls,
			null,
			createElement(
				'div',
				{
					className: 'nea-image-author-control',
					style: { padding: '0 16px 16px' },
				},
				control
			)
		);
	}

	function AuthorFieldHost( props ) {
		var attachmentId = props.attachmentId;
		var portalElRef = useRef( null );
		var state = useState( { placement: 'pending', portalTarget: null } );
		var placementState = state[ 0 ];
		var setPlacementState = state[ 1 ];

		useEffect(
			function () {
				var cancelled = false;
				var observer = null;
				var attempts = 0;

				function ensurePortalNode( altRoot ) {
					if ( ! altRoot || ! altRoot.parentNode ) {
						return null;
					}

					if (
						portalElRef.current &&
						portalElRef.current.parentNode === altRoot.parentNode &&
						portalElRef.current.nextSibling === altRoot
					) {
						removeOrphanPortals( portalElRef.current );
						return portalElRef.current;
					}

					if ( portalElRef.current && portalElRef.current.parentNode ) {
						portalElRef.current.parentNode.removeChild( portalElRef.current );
					}

					removeOrphanPortals( null );

					var node = document.createElement( 'div' );
					node.className = 'nea-image-author-portal';
					altRoot.parentNode.insertBefore( node, altRoot );
					portalElRef.current = node;
					return node;
				}

				function tryPlace() {
					if ( cancelled ) {
						return false;
					}
					var altRoot = findAltControlRoot();
					if ( altRoot ) {
						var node = ensurePortalNode( altRoot );
						if ( node ) {
							setPlacementState( {
								placement: 'portal',
								portalTarget: node,
							} );
							return true;
						}
					}
					return false;
				}

				if ( ! tryPlace() ) {
					var interval = setInterval( function () {
						attempts += 1;
						if ( tryPlace() ) {
							clearInterval( interval );
							return;
						}
						if ( attempts > 20 ) {
							clearInterval( interval );
							if ( ! cancelled ) {
								setPlacementState( {
									placement: 'fallback',
									portalTarget: null,
								} );
							}
						}
					}, 200 );
				}

				var sidebar =
					document.querySelector( '.interface-complementary-area' ) ||
					document.querySelector( '.edit-post-sidebar' ) ||
					document.querySelector( '.editor-sidebar' );

				if ( sidebar && window.MutationObserver ) {
					observer = new MutationObserver( function () {
						tryPlace();
					} );
					observer.observe( sidebar, { childList: true, subtree: true } );
				}

				return function () {
					cancelled = true;
					if ( observer ) {
						observer.disconnect();
					}
					if ( portalElRef.current && portalElRef.current.parentNode ) {
						portalElRef.current.parentNode.removeChild( portalElRef.current );
					}
					portalElRef.current = null;
					removeOrphanPortals( null );
				};
			},
			[ attachmentId ]
		);

		return createElement( AuthorField, {
			attachmentId: attachmentId,
			portalTarget: placementState.portalTarget,
			placement: placementState.placement,
		} );
	}

	var withImageAuthor = createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( ! SUPPORTED_BLOCKS[ props.name ] ) {
				return createElement( BlockEdit, props );
			}

			var attachmentId = 0;
			if ( props.name === 'core/image' && props.attributes && props.attributes.id ) {
				attachmentId = props.attributes.id;
			} else if (
				props.name === 'core/media-text' &&
				props.attributes &&
				props.attributes.mediaId
			) {
				attachmentId = props.attributes.mediaId;
			}

			var showAuthor = props.isSelected && attachmentId;

			return createElement(
				Fragment,
				null,
				createElement( BlockEdit, props ),
				showAuthor
					? createElement( AuthorFieldHost, { attachmentId: attachmentId } )
					: null
			);
		};
	}, 'withNeaImageAuthor' );

	hooks.addFilter(
		'editor.BlockEdit',
		'nea-image-copyright/with-image-author',
		withImageAuthor
	);
} )( window.wp );
