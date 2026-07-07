/**
 * Shuriken Reviews — Edit Parent Rating modal (with mirror management).
 *
 * Extracted from the grouped-rating edit() render. All required state and
 * handlers are supplied through the `ctx` object.
 *
 * @package
 */

import { createElement, Fragment } from '@wordpress/element';
import {
	TextControl,
	Button,
	Spinner,
	Modal,
	CheckboxControl,
	Divider,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const wp = { element: { createElement, Fragment } };
const { renderRatingTypeScaleFields } = window.ShurikenBlockHelpers;

/**
 * @param {Object} ctx - Grouped-rating edit() scope passthrough.
 * @return {Object|null} Modal element or null when closed.
 */
export default function EditParentModal( ctx ) {
	if ( ! ctx.isEditModalOpen ) {
		return null;
	}

	const {
		setIsEditModalOpen,
		setNewMirrorName,
		setEditingMirrorNames,
		setSavingMirrorId,
		editParentName,
		setEditParentName,
		handleEditKeyDown,
		editParentDescription,
		setEditParentDescription,
		editParentDisplayOnly,
		setEditParentDisplayOnly,
		editParentType,
		setEditParentType,
		editParentScale,
		setEditParentScale,
		selectedRating,
		updateParentRating,
		updating,
		parentMirrors,
		parentMirrorsLoading,
		editingMirrorNames,
		savingMirrorId,
		updateEditingMirrorName,
		saveMirrorName,
		cancelEditMirror,
		startEditMirror,
		deleteMirror,
		newMirrorName,
		createMirrorForParent,
		creatingMirror,
	} = ctx;

	return wp.element.createElement(
		Modal,
		{
			title: __( 'Edit Parent Rating', 'shuriken-reviews' ),
			onRequestClose: () => {
				setIsEditModalOpen( false );
				setNewMirrorName( '' );
				setEditingMirrorNames( {} );
				setSavingMirrorId( null );
			},
			className: 'shuriken-modal shuriken-edit-modal',
		},
		// --- Parent fields section ---
		wp.element.createElement(
			'div',
			{ className: 'shuriken-modal-section' },
			wp.element.createElement( TextControl, {
				label: __( 'Parent Rating Name', 'shuriken-reviews' ),
				value: editParentName,
				onChange: setEditParentName,
				onKeyDown: handleEditKeyDown,
				placeholder: __( 'Enter rating name…', 'shuriken-reviews' ),
			} ),
			wp.element.createElement( TextControl, {
				label: __( 'Description', 'shuriken-reviews' ),
				value: editParentDescription,
				onChange: setEditParentDescription,
				placeholder: __(
					'Optional description beneath rating name',
					'shuriken-reviews'
				),
				help: __(
					'Optional text displayed beneath the rating name.',
					'shuriken-reviews'
				),
			} ),
			wp.element.createElement( CheckboxControl, {
				label: __(
					'Display Only (No Direct Voting)',
					'shuriken-reviews'
				),
				checked: editParentDisplayOnly,
				onChange: setEditParentDisplayOnly,
				help: __(
					'When enabled, users can only vote on sub-ratings.',
					'shuriken-reviews'
				),
			} ),
			renderRatingTypeScaleFields( {
				type: editParentType,
				scale: editParentScale,
				setType: setEditParentType,
				setScale: setEditParentScale,
				disabled:
					selectedRating &&
					parseInt( selectedRating.total_votes, 10 ) > 0,
				typeHelp:
					selectedRating &&
					parseInt( selectedRating.total_votes, 10 ) > 0
						? __(
								'Rating type cannot be changed after votes are cast.',
								'shuriken-reviews'
						  )
						: null,
				scaleHelp:
					selectedRating &&
					parseInt( selectedRating.total_votes, 10 ) > 0
						? __(
								'Scale cannot be changed after votes have been cast.',
								'shuriken-reviews'
						  )
						: undefined,
				scaleHelpMode: 'range',
				showScaleMinMax: true,
			} ),
			wp.element.createElement(
				'div',
				{ className: 'shuriken-modal-actions' },
				wp.element.createElement(
					Button,
					{
						variant: 'secondary',
						onClick: () => {
							setIsEditModalOpen( false );
							setNewMirrorName( '' );
							setEditingMirrorNames( {} );
						},
					},
					__( 'Cancel', 'shuriken-reviews' )
				),
				wp.element.createElement(
					Button,
					{
						variant: 'primary',
						onClick: updateParentRating,
						isBusy: updating,
						disabled: updating || ! editParentName.trim(),
					},
					__( 'Update', 'shuriken-reviews' )
				)
			)
		),

		// --- Mirrors management section ---
		Divider && wp.element.createElement( Divider, null ),
		wp.element.createElement(
			'div',
			{ className: 'shuriken-modal-section' },
			wp.element.createElement(
				'div',
				{ className: 'shuriken-section-header' },
				wp.element.createElement(
					'h4',
					null,
					__( 'Mirrors', 'shuriken-reviews' )
				),
				Array.isArray( parentMirrors ) &&
					wp.element.createElement(
						'span',
						{ className: 'shuriken-badge' },
						parentMirrors.length
					)
			),
			parentMirrorsLoading &&
				wp.element.createElement(
					'div',
					{ className: 'shuriken-loading-row' },
					wp.element.createElement( Spinner, null )
				),
			Array.isArray( parentMirrors ) &&
				parentMirrors.length === 0 &&
				! parentMirrorsLoading &&
				wp.element.createElement(
					'p',
					{ className: 'shuriken-empty-message' },
					__(
						'No mirrors yet. Create one below.',
						'shuriken-reviews'
					)
				),
			// Mirror list items
			Array.isArray( parentMirrors ) &&
				parentMirrors.map( ( m ) => {
					const isEditing = editingMirrorNames.hasOwnProperty( m.id );
					const isSaving = savingMirrorId === parseInt( m.id, 10 );

					if ( isEditing ) {
						return wp.element.createElement(
							'div',
							{
								key: m.id,
								className: 'shuriken-mirror-card is-editing',
							},
							wp.element.createElement( TextControl, {
								value: editingMirrorNames[ m.id ],
								onChange: ( val ) => {
									updateEditingMirrorName( m.id, val );
								},
								onKeyDown: ( e ) => {
									if ( e.key === 'Enter' ) {
										e.preventDefault();
										saveMirrorName(
											m.id,
											parseInt( selectedRating.id, 10 )
										);
									}
									if ( e.key === 'Escape' ) {
										cancelEditMirror( m.id );
									}
								},
								className: 'shuriken-mirror-edit-input',
							} ),
							wp.element.createElement(
								'div',
								{ className: 'shuriken-mirror-card-actions' },
								wp.element.createElement(
									Button,
									{
										variant: 'primary',
										isSmall: true,
										onClick: () => {
											saveMirrorName(
												m.id,
												parseInt(
													selectedRating.id,
													10
												)
											);
										},
										isBusy: isSaving,
										disabled:
											isSaving ||
											! (
												editingMirrorNames[ m.id ] || ''
											).trim(),
									},
									__( 'Save', 'shuriken-reviews' )
								),
								wp.element.createElement(
									Button,
									{
										variant: 'tertiary',
										isSmall: true,
										onClick: () => {
											cancelEditMirror( m.id );
										},
										disabled: isSaving,
									},
									__( 'Cancel', 'shuriken-reviews' )
								)
							)
						);
					}

					return wp.element.createElement(
						'div',
						{ key: m.id, className: 'shuriken-mirror-card' },
						wp.element.createElement(
							'span',
							{ className: 'shuriken-mirror-name' },
							m.name,
							wp.element.createElement(
								'span',
								{ className: 'shuriken-id-badge' },
								`#${ m.id }`
							)
						),
						wp.element.createElement(
							'div',
							{ className: 'shuriken-mirror-card-actions' },
							wp.element.createElement( Button, {
								icon: 'edit',
								label: __( 'Rename', 'shuriken-reviews' ),
								isSmall: true,
								onClick: () => {
									startEditMirror( m.id, m.name );
								},
							} ),
							wp.element.createElement( Button, {
								icon: 'trash',
								label: __( 'Delete', 'shuriken-reviews' ),
								isSmall: true,
								isDestructive: true,
								onClick: () => {
									deleteMirror(
										m.id,
										parseInt( selectedRating.id, 10 )
									);
								},
							} )
						)
					);
				} ),
			// Create new mirror inline
			wp.element.createElement(
				'div',
				{ className: 'shuriken-inline-create' },
				wp.element.createElement( TextControl, {
					value: newMirrorName,
					onChange: setNewMirrorName,
					onKeyDown: ( e ) => {
						if ( e.key === 'Enter' ) {
							e.preventDefault();
							createMirrorForParent();
						}
					},
					placeholder: __( 'New mirror name…', 'shuriken-reviews' ),
					className: 'shuriken-inline-create-input',
				} ),
				wp.element.createElement(
					Button,
					{
						variant: 'secondary',
						isSmall: true,
						onClick: createMirrorForParent,
						isBusy: creatingMirror,
						disabled: creatingMirror || ! newMirrorName.trim(),
					},
					__( 'Add', 'shuriken-reviews' )
				)
			)
		)
	);
}
