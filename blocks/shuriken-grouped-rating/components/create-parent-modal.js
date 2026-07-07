/**
 * Shuriken Reviews — Create Parent Rating modal.
 *
 * Extracted from the grouped-rating edit() render for readability.
 * Receives every piece of state/behaviour it needs through a single
 * `ctx` object so it has no hidden coupling to the parent closure.
 *
 * @package
 */

import { createElement, Fragment } from '@wordpress/element';
import {
	TextControl,
	Button,
	Modal,
	ComboboxControl,
	CheckboxControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const wp = { element: { createElement, Fragment } };
const { renderRatingTypeScaleFields } = window.ShurikenBlockHelpers;

/**
 * @param {Object} ctx - Grouped-rating edit() scope passthrough.
 * @return {Object|null} Modal element or null when closed.
 */
export default function CreateParentModal( ctx ) {
	if ( ! ctx.isCreateModalOpen ) {
		return null;
	}

	const {
		newRatingIsMirror,
		setNewRatingIsMirror,
		newMirrorSourceId,
		setNewMirrorSourceId,
		mirrorableOptions,
		isLoadingMirrorable,
		newParentName,
		setNewParentName,
		handleCreateKeyDown,
		newParentDescription,
		setNewParentDescription,
		newParentDisplayOnly,
		setNewParentDisplayOnly,
		newParentType,
		setNewParentType,
		newParentScale,
		setNewParentScale,
		setIsCreateModalOpen,
		createNewParentRating,
		creating,
	} = ctx;

	return wp.element.createElement(
		Modal,
		{
			title: newRatingIsMirror
				? __( 'Create New Mirror', 'shuriken-reviews' )
				: __( 'Create New Parent Rating', 'shuriken-reviews' ),
			onRequestClose: () => {
				setIsCreateModalOpen( false );
				setNewParentName( '' );
				setNewParentDisplayOnly( true );
				setNewParentType( 'stars' );
				setNewParentScale( 5 );
				setNewParentDescription( '' );
				setNewRatingIsMirror( false );
				setNewMirrorSourceId( 0 );
			},
			className: 'shuriken-modal shuriken-create-modal',
		},
		wp.element.createElement(
			'div',
			{ className: 'shuriken-modal-section' },
			wp.element.createElement( CheckboxControl, {
				label: __(
					'Create as Mirror of Existing Rating',
					'shuriken-reviews'
				),
				checked: newRatingIsMirror,
				onChange: ( val ) => {
					setNewRatingIsMirror( val );
					if ( ! val ) {
						setNewMirrorSourceId( 0 );
					}
				},
				help: newRatingIsMirror
					? __(
							'The new rating will share vote data with the source rating.',
							'shuriken-reviews'
					  )
					: __(
							'Enable to create a mirror instead of an independent parent.',
							'shuriken-reviews'
					  ),
			} ),
			newRatingIsMirror &&
				wp.element.createElement( ComboboxControl, {
					label: __( 'Source Rating', 'shuriken-reviews' ),
					value: newMirrorSourceId ? String( newMirrorSourceId ) : '',
					options: mirrorableOptions,
					onChange: ( value ) => {
						setNewMirrorSourceId(
							value ? parseInt( value, 10 ) : 0
						);
					},
					onFilterValueChange: () => {},
					placeholder: isLoadingMirrorable
						? __( 'Loading…', 'shuriken-reviews' )
						: __( 'Search ratings…', 'shuriken-reviews' ),
				} ),
			wp.element.createElement( TextControl, {
				label: newRatingIsMirror
					? __( 'Mirror Name', 'shuriken-reviews' )
					: __( 'Parent Rating Name', 'shuriken-reviews' ),
				value: newParentName,
				onChange: setNewParentName,
				onKeyDown: handleCreateKeyDown,
				placeholder: newRatingIsMirror
					? __( 'e.g., Product Quality (Page 2)', 'shuriken-reviews' )
					: __( 'e.g., Overall Product Quality', 'shuriken-reviews' ),
				help: newRatingIsMirror
					? __(
							'A display name for this mirror.',
							'shuriken-reviews'
					  )
					: __(
							'This will be the main rating that groups sub-ratings.',
							'shuriken-reviews'
					  ),
			} ),
			! newRatingIsMirror &&
				wp.element.createElement( TextControl, {
					label: __( 'Description', 'shuriken-reviews' ),
					value: newParentDescription,
					onChange: setNewParentDescription,
					placeholder: __(
						'Optional description beneath rating name',
						'shuriken-reviews'
					),
					help: __(
						'Optional text displayed beneath the rating name.',
						'shuriken-reviews'
					),
				} ),
			! newRatingIsMirror &&
				wp.element.createElement( CheckboxControl, {
					label: __(
						'Display Only (No Direct Voting)',
						'shuriken-reviews'
					),
					checked: newParentDisplayOnly,
					onChange: setNewParentDisplayOnly,
					help: __(
						'When enabled, users can only vote on sub-ratings. The parent shows the calculated average.',
						'shuriken-reviews'
					),
				} ),
			! newRatingIsMirror &&
				renderRatingTypeScaleFields( {
					type: newParentType,
					scale: newParentScale,
					setType: setNewParentType,
					setScale: setNewParentScale,
				} ),
			wp.element.createElement(
				'div',
				{ className: 'shuriken-modal-actions' },
				wp.element.createElement(
					Button,
					{
						variant: 'secondary',
						onClick: () => {
							setIsCreateModalOpen( false );
							setNewParentName( '' );
							setNewParentDisplayOnly( true );
							setNewParentType( 'stars' );
							setNewParentScale( 5 );
							setNewParentDescription( '' );
							setNewRatingIsMirror( false );
							setNewMirrorSourceId( 0 );
						},
					},
					__( 'Cancel', 'shuriken-reviews' )
				),
				wp.element.createElement(
					Button,
					{
						variant: 'primary',
						onClick: createNewParentRating,
						isBusy: creating,
						disabled:
							creating ||
							! newParentName.trim() ||
							( newRatingIsMirror && ! newMirrorSourceId ),
					},
					newRatingIsMirror
						? __( 'Create Mirror', 'shuriken-reviews' )
						: __( 'Create', 'shuriken-reviews' )
				)
			)
		)
	);
}
