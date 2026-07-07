/**
 * Inspector (sidebar) panels for the Grouped Rating block.
 *
 * Extracted from index.js to keep the editor component lean. Receives every
 * value/handler it needs through a single `ctx` object so it stays decoupled
 * from the edit() closure.
 */
import { createElement, Fragment } from '@wordpress/element';
import { InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	Button,
	Spinner,
	ComboboxControl,
	SelectControl,
	CheckboxControl,
	Icon,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const { getRatingType, buildColorSettings } = window.ShurikenBlockHelpers;

const wp = { element: { createElement, Fragment } };

export default function InspectorPanels( ctx ) {
	const {
		loading,
		mirrorId,
		ratingId,
		ratingOptions,
		allRatingsById,
		setAttributes,
		fetchRating,
		fetchChildRatings,
		fetchMirrorsForRating,
		handleSearchChange,
		isSearching,
		selectedRating,
		openEditParentModal,
		openManageChildrenModal,
		setIsCreateModalOpen,
		titleTag,
		titleTagOptions,
		anchorTag,
		postContext,
		hideTitle,
		subRatings,
		childMirrorsMap,
		dragIndex,
		dragOverIndex,
		handleDragStart,
		handleDragOver,
		handleDragEnd,
		handleDrop,
		updateSubRating,
		resetSubRatings,
		childLayout,
		gap,
		accentColor,
		starColor,
		buttonColor,
		orderedVisibleChildren,
	} = ctx;

	return wp.element.createElement(
		InspectorControls,
		null,

		// Panel 1 — Rating Selection
		wp.element.createElement(
			PanelBody,
			{
				title: __( 'Grouped Rating Settings', 'shuriken-reviews' ),
				initialOpen: true,
			},
			loading
				? wp.element.createElement( Spinner, null )
				: wp.element.createElement(
						wp.element.Fragment,
						null,
						wp.element.createElement( ComboboxControl, {
							label: __(
								'Select Parent Rating',
								'shuriken-reviews'
							),
							value: mirrorId
								? String( mirrorId )
								: ratingId
								? String( ratingId )
								: '',
							options: ratingOptions,
							onChange: ( value ) => {
								const pickedId = value
									? parseInt( value, 10 )
									: 0;
								if ( ! pickedId ) {
									setAttributes( {
										ratingId: 0,
										mirrorId: 0,
										subRatings: [],
									} );
									return;
								}
								// Check if the picked rating is a mirror
								const picked = allRatingsById[ pickedId ];
								if ( picked && picked.mirror_of ) {
									// Mirror selected — resolve to source parent + set mirrorId
									const sourceId = parseInt(
										picked.mirror_of,
										10
									);
									setAttributes( {
										ratingId: sourceId,
										mirrorId: pickedId,
										subRatings: [],
									} );
									fetchRating( sourceId );
									fetchChildRatings( sourceId );
									fetchMirrorsForRating( sourceId );
								} else {
									// Regular parent selected
									setAttributes( {
										ratingId: pickedId,
										mirrorId: 0,
										subRatings: [],
									} );
									fetchRating( pickedId );
									fetchChildRatings( pickedId );
									fetchMirrorsForRating( pickedId );
								}
							},
							onFilterValueChange: handleSearchChange,
							placeholder: isSearching
								? __( 'Searching…', 'shuriken-reviews' )
								: __(
										'Search parent ratings or mirrors…',
										'shuriken-reviews'
								  ),
							help: mirrorId
								? __(
										"A mirror is selected — the block uses the mirror's name but the original parent's sub-ratings.",
										'shuriken-reviews'
								  )
								: null,
						} ),

						isSearching &&
							wp.element.createElement(
								'div',
								{
									style: {
										display: 'flex',
										alignItems: 'center',
										gap: '8px',
										marginTop: '8px',
									},
								},
								wp.element.createElement( Spinner, {
									style: { margin: 0 },
								} ),
								wp.element.createElement(
									'span',
									null,
									__( 'Searching…', 'shuriken-reviews' )
								)
							),

						wp.element.createElement(
							'div',
							{
								style: {
									display: 'flex',
									gap: '8px',
									marginTop: '12px',
									marginBottom: '16px',
									flexWrap: 'wrap',
								},
							},
							wp.element.createElement(
								Button,
								{
									variant: 'secondary',
									onClick: () => {
										setIsCreateModalOpen( true );
									},
								},
								__( 'Create New', 'shuriken-reviews' )
							),
							selectedRating &&
								wp.element.createElement(
									Button,
									{
										variant: 'secondary',
										onClick: openEditParentModal,
									},
									__( 'Edit Parent', 'shuriken-reviews' )
								),
							selectedRating &&
								wp.element.createElement(
									Button,
									{
										variant: 'primary',
										onClick: openManageChildrenModal,
									},
									__(
										'Manage Sub-Ratings',
										'shuriken-reviews'
									)
								)
						)
				  ),
			wp.element.createElement( ComboboxControl, {
				label: __( 'Title Tag', 'shuriken-reviews' ),
				value: titleTag,
				options: titleTagOptions,
				onChange: ( value ) => {
					setAttributes( { titleTag: value || 'h2' } );
				},
				onFilterValueChange: () => {},
			} ),
			wp.element.createElement( TextControl, {
				label: __( 'Anchor ID', 'shuriken-reviews' ),
				value: anchorTag,
				onChange: ( value ) => {
					setAttributes( { anchorTag: value } );
				},
				help: __(
					'Optional anchor ID for linking to this rating group.',
					'shuriken-reviews'
				),
			} ),
			wp.element.createElement( CheckboxControl, {
				label: __( 'Per-post voting', 'shuriken-reviews' ),
				checked: postContext,
				onChange: ( value ) => {
					setAttributes( { postContext: value } );
				},
				help: __(
					'When enabled, votes are counted separately for each post/page this block appears on.',
					'shuriken-reviews'
				),
			} ),
			wp.element.createElement( CheckboxControl, {
				label: __( 'Hide title & description', 'shuriken-reviews' ),
				checked: hideTitle,
				onChange: ( value ) => {
					setAttributes( { hideTitle: value } );
				},
				help: __(
					'Hide rating names and descriptions. Useful in Query Loop layouts.',
					'shuriken-reviews'
				),
			} )
		),

		// Panel 2 — Sub-Ratings Visibility & Order
		ratingId &&
			selectedRating &&
			( subRatings || [] ).length > 0 &&
			wp.element.createElement(
				PanelBody,
				{
					title: __( 'Sub-Ratings Display', 'shuriken-reviews' ),
					initialOpen: false,
				},
				wp.element.createElement(
					'p',
					{
						style: {
							fontSize: '12px',
							color: '#666',
							marginTop: 0,
						},
					},
					__(
						'Drag to reorder, toggle visibility, and optionally pick a mirror for each sub-rating.',
						'shuriken-reviews'
					)
				),
				( subRatings || [] ).map( ( sr, index ) => {
					const childRating = allRatingsById[ sr.id ];
					const childName = childRating
						? childRating.name
						: __( 'Loading…', 'shuriken-reviews' );
					const mirrors = childMirrorsMap[ sr.id ];
					const hasMirrors =
						Array.isArray( mirrors ) && mirrors.length > 0;
					const isDragging = dragIndex === index;
					const isDragOver = dragOverIndex === index;

					return wp.element.createElement(
						'div',
						{
							key: sr.id,
							className: `shuriken-sub-rating-row${
								isDragging ? ' is-dragging' : ''
							}${ isDragOver ? ' is-drag-over' : '' }${
								! sr.visible ? ' is-hidden' : ''
							}`,
							draggable: true,
							onDragStart: ( e ) => {
								handleDragStart( e, index );
							},
							onDragOver: ( e ) => {
								handleDragOver( e, index );
							},
							onDragEnd: handleDragEnd,
							onDrop: handleDrop,
						},
						wp.element.createElement(
							'div',
							{ className: 'shuriken-sub-rating-row-header' },
							wp.element.createElement(
								'span',
								{
									className: 'shuriken-drag-handle',
									title: __(
										'Drag to reorder',
										'shuriken-reviews'
									),
								},
								wp.element.createElement( Icon, {
									icon: 'menu',
								} )
							),
							wp.element.createElement(
								'span',
								{ className: 'shuriken-sub-rating-name' },
								childName
							),
							wp.element.createElement( Button, {
								icon: sr.visible ? 'visibility' : 'hidden',
								label: sr.visible
									? __( 'Hide', 'shuriken-reviews' )
									: __( 'Show', 'shuriken-reviews' ),
								onClick: () => {
									updateSubRating( sr.id, {
										visible: ! sr.visible,
									} );
								},
								className: `shuriken-visibility-toggle${
									sr.visible ? '' : ' is-hidden-icon'
								}`,
							} )
						),
						sr.visible &&
							hasMirrors &&
							wp.element.createElement( SelectControl, {
								value: String( sr.mirrorId || 0 ),
								options: [
									{
										label: __(
											'Original',
											'shuriken-reviews'
										),
										value: '0',
									},
								].concat(
									mirrors.map( ( m ) => {
										return {
											label: `${ m.name } (ID: ${ m.id })`,
											value: String( m.id ),
										};
									} )
								),
								onChange: ( value ) => {
									updateSubRating( sr.id, {
										mirrorId: parseInt( value, 10 ) || 0,
									} );
								},
								className: 'shuriken-sub-mirror-select',
							} )
					);
				} ),
				wp.element.createElement(
					'div',
					{
						style: {
							marginTop: '12px',
							display: 'flex',
							justifyContent: 'flex-end',
						},
					},
					wp.element.createElement(
						Button,
						{
							variant: 'tertiary',
							isDestructive: true,
							onClick: resetSubRatings,
							style: { fontSize: '12px' },
						},
						__( 'Reset to Defaults', 'shuriken-reviews' )
					)
				)
			),

		// Panel 3 — Layout
		wp.element.createElement(
			PanelBody,
			{ title: __( 'Layout', 'shuriken-reviews' ), initialOpen: false },
			wp.element.createElement( SelectControl, {
				label: __( 'Child Ratings Layout', 'shuriken-reviews' ),
				value: childLayout || 'grid',
				options: [
					{
						label: __( 'Grid (cards)', 'shuriken-reviews' ),
						value: 'grid',
					},
					{
						label: __( 'List (stacked rows)', 'shuriken-reviews' ),
						value: 'list',
					},
				],
				onChange: ( value ) => {
					setAttributes( { childLayout: value } );
				},
				help: __(
					'Grid shows children as cards in columns. List shows them as full-width rows.',
					'shuriken-reviews'
				),
			} ),
			wp.element.createElement( TextControl, {
				label: __( 'Gap', 'shuriken-reviews' ),
				value: gap || '',
				placeholder: '24px',
				onChange: ( value ) => {
					setAttributes( { gap: value || '' } );
				},
				help: __(
					'Space between parent and children. Accepts CSS values (e.g. 24px, 2rem).',
					'shuriken-reviews'
				),
			} )
		),

		// Panel 4 — Colors (type-aware: accent + star/slider + button for numeric)
		wp.element.createElement( PanelColorSettings, {
			title: __( 'Colors', 'shuriken-reviews' ),
			initialOpen: false,
			colorSettings: ( () => {
				const parentType = selectedRating
					? getRatingType( selectedRating )
					: 'stars';
				const hasNumeric =
					parentType === 'numeric' ||
					orderedVisibleChildren.some( ( c ) => {
						return getRatingType( c ) === 'numeric';
					} );
				return buildColorSettings( {
					ratingType: hasNumeric ? 'numeric' : parentType,
					accentColor: accentColor || undefined,
					starColor: starColor || undefined,
					buttonColor: buttonColor || undefined,
					setAccent: ( value ) => {
						setAttributes( { accentColor: value || '' } );
					},
					setStar: ( value ) => {
						setAttributes( { starColor: value || '' } );
					},
					setButton: hasNumeric
						? ( value ) => {
								setAttributes( { buttonColor: value || '' } );
						  }
						: undefined,
				} );
			} )(),
		} )
	);
}
