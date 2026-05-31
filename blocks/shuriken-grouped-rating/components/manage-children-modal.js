/**
 * Shuriken Reviews — Manage Sub-Ratings modal.
 *
 * Extracted from the grouped-rating edit() render. All required state and
 * handlers are supplied through the `ctx` object.
 *
 * @package Shuriken_Reviews
 */

import { createElement, Fragment } from '@wordpress/element';
import {
    TextControl,
    Button,
    Spinner,
    Modal,
    SelectControl,
    CheckboxControl,
    Notice,
    __experimentalDivider as Divider
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const wp = { element: { createElement, Fragment } };
const {
    renderRatingTypeScaleFields,
    getRatingType,
    areTypesCompatible,
    formatCompactStats
} = window.ShurikenBlockHelpers;

/**
 * @param {Object} ctx - Grouped-rating edit() scope passthrough.
 * @return {Object|null} Modal element or null when closed.
 */
export default function ManageChildrenModal(ctx) {
    if (!ctx.isManageChildrenModalOpen) {
        return null;
    }

    const {
        setIsManageChildrenModalOpen, setNewChildName, setNewChildEffectType,
        setNewChildType, setNewChildScale, setNewChildDisplayOnly, setNewChildDescription,
        setChildrenLocalEdits, setNewChildMirrorNames, setEditingMirrorNames, setSavingMirrorId,
        selectedRating, childrenLocalEdits,
        newChildName, handleChildKeyDown,
        newChildDescription, newChildEffectType,
        newChildType, newChildScale, newChildDisplayOnly,
        addNewChild, managingChildren,
        childrenToManage, deleteChild, updateChildLocally,
        childMirrorsMap, newChildMirrorNames,
        editingMirrorNames, savingMirrorId, creatingChildMirrorId,
        createMirrorForChild, saveMirrorName, cancelEditMirror,
        startEditMirror, deleteMirror, updateEditingMirrorName,
        applyChildrenEdits, savingChildren
    } = ctx;

    return wp.element.createElement(
        Modal,
        {
            title: __('Manage Sub-Ratings', 'shuriken-reviews'),
            onRequestClose: () => {
                const hasUnsaved = Object.keys(childrenLocalEdits).length > 0;
                if (hasUnsaved && !confirm(__('You have unsaved changes. Close without saving?', 'shuriken-reviews'))) return;
                setIsManageChildrenModalOpen(false);
                setNewChildName('');
                setNewChildEffectType('positive');
                setNewChildType('stars');
                setNewChildScale(5);
                setNewChildDisplayOnly(false);
                setNewChildDescription('');
                setChildrenLocalEdits({});
                setNewChildMirrorNames({});
                setEditingMirrorNames({});
                setSavingMirrorId(null);
            },
            className: 'shuriken-modal shuriken-manage-children-modal'
        },

        // --- Header info ---
        selectedRating && wp.element.createElement(
            'div',
            { className: 'shuriken-modal-header-info' },
            wp.element.createElement('p', null,
                __('Managing sub-ratings for: ', 'shuriken-reviews'),
                wp.element.createElement('strong', null, selectedRating.name)
            ),
            Object.keys(childrenLocalEdits).length > 0 && wp.element.createElement(
                'span',
                { className: 'shuriken-badge is-info' },
                `${Object.keys(childrenLocalEdits).length} ${__('unsaved', 'shuriken-reviews')}`
            )
        ),

        // --- Add New Sub-Rating section ---
        wp.element.createElement(
            'div',
            { className: 'shuriken-modal-section shuriken-create-section' },
            wp.element.createElement(
                'div',
                { className: 'shuriken-section-header' },
                wp.element.createElement('h4', null, __('Add New Sub-Rating', 'shuriken-reviews'))
            ),
            wp.element.createElement(TextControl, {
                label: __('Sub-Rating Name', 'shuriken-reviews'),
                value: newChildName,
                onChange: setNewChildName,
                onKeyDown: handleChildKeyDown,
                placeholder: __('e.g., Build Quality, Features, Value for Money', 'shuriken-reviews')
            }),
            wp.element.createElement(TextControl, {
                label: __('Description', 'shuriken-reviews'),
                value: newChildDescription,
                onChange: setNewChildDescription,
                placeholder: __('Optional description beneath rating name', 'shuriken-reviews'),
                help: __('Optional text displayed beneath the rating name.', 'shuriken-reviews')
            }),
            wp.element.createElement(SelectControl, {
                label: __('Effect on Parent', 'shuriken-reviews'),
                value: newChildEffectType,
                options: [
                    { label: __('Positive — Higher votes improve parent rating', 'shuriken-reviews'), value: 'positive' },
                    { label: __('Negative — Higher votes lower parent rating', 'shuriken-reviews'), value: 'negative' }
                ],
                onChange: setNewChildEffectType,
                help: __('Negative is useful for aspects like "Difficulty" or "Price" where higher values are worse.', 'shuriken-reviews')
            }),
            renderRatingTypeScaleFields({
                type: newChildType,
                scale: newChildScale,
                setType: setNewChildType,
                setScale: setNewChildScale,
                typeHelp: null,
                scaleHelpMode: 'range',
                showScaleMinMax: true
            }),
            // Type-compatibility warning for new sub-rating
            selectedRating && !areTypesCompatible(getRatingType(selectedRating), newChildType) && wp.element.createElement(Notice, {
                status: 'warning',
                isDismissible: false,
                style: { marginBottom: '12px' }
            }, __('This sub-rating type is incompatible with the parent\'s type. Mixing star/numeric types with like/dislike/approval types produces incorrect aggregated scores.', 'shuriken-reviews')),
            wp.element.createElement(CheckboxControl, {
                label: __('Display Only (No Direct Voting)', 'shuriken-reviews'),
                checked: newChildDisplayOnly,
                onChange: setNewChildDisplayOnly,
                help: __('When enabled, this sub-rating cannot be voted on directly.', 'shuriken-reviews')
            }),
            wp.element.createElement(
                'div',
                { className: 'shuriken-modal-actions' },
                wp.element.createElement(Button, {
                    variant: 'primary',
                    onClick: addNewChild,
                    isBusy: managingChildren,
                    disabled: managingChildren || !newChildName.trim()
                }, __('Add Sub-Rating', 'shuriken-reviews'))
            )
        ),

        // --- Existing Sub-Ratings ---
        Divider && wp.element.createElement(Divider, null),
        wp.element.createElement(
            'div',
            { className: 'shuriken-modal-section' },
            wp.element.createElement(
                'div',
                { className: 'shuriken-section-header' },
                wp.element.createElement('h4', null, __('Existing Sub-Ratings', 'shuriken-reviews')),
                wp.element.createElement('span', { className: 'shuriken-badge' }, childrenToManage.length)
            ),
            childrenToManage.length === 0 && wp.element.createElement(
                'p',
                { className: 'shuriken-empty-message' },
                __('No sub-ratings yet. Add one above.', 'shuriken-reviews')
            ),
            childrenToManage.map((child) => {
                const hasEdits = !!childrenLocalEdits[child.id];
                return wp.element.createElement(
                    'div',
                    {
                        key: child.id,
                        className: `shuriken-child-card${hasEdits ? ' is-modified' : ''}`
                    },
                    // Child header row
                    wp.element.createElement(
                        'div',
                        { className: 'shuriken-child-card-header' },
                        wp.element.createElement('strong', null, child.name),
                        wp.element.createElement(
                            'div',
                            { className: 'shuriken-child-card-meta' },
                            wp.element.createElement('span', { className: 'shuriken-stat-text' },
                                formatCompactStats(child)
                            ),
                            wp.element.createElement(Button, {
                                icon: 'trash',
                                label: __('Delete Sub-Rating', 'shuriken-reviews'),
                                isSmall: true,
                                isDestructive: true,
                                onClick: () => { deleteChild(child.id); }
                            })
                        )
                    ),
                    // Edit fields
                    wp.element.createElement(
                        'div',
                        { className: 'shuriken-child-card-fields' },
                        wp.element.createElement(TextControl, {
                            label: __('Name', 'shuriken-reviews'),
                            value: (hasEdits && childrenLocalEdits[child.id].name) || child.name,
                            onChange: (value) => { updateChildLocally(child.id, { name: value }); }
                        }),
                        wp.element.createElement(SelectControl, {
                            label: __('Effect Type', 'shuriken-reviews'),
                            value: (hasEdits && childrenLocalEdits[child.id].effect_type) || child.effect_type || 'positive',
                            options: [
                                { label: __('Positive — Higher votes improve parent rating', 'shuriken-reviews'), value: 'positive' },
                                { label: __('Negative — Higher votes lower parent rating', 'shuriken-reviews'), value: 'negative' }
                            ],
                            onChange: (value) => { updateChildLocally(child.id, { effect_type: value }); }
                        }),
                        (() => {
                            const childType = (hasEdits && childrenLocalEdits[child.id].rating_type) || child.rating_type || 'stars';
                            const childScale = (hasEdits && childrenLocalEdits[child.id].scale !== undefined) ? childrenLocalEdits[child.id].scale : (child.scale || 5);
                            const childDisplayOnly = (hasEdits && childrenLocalEdits[child.id].display_only !== undefined) ? childrenLocalEdits[child.id].display_only : (child.display_only || false);
                            const childHasVotes = parseInt(child.total_votes, 10) > 0;
                            return wp.element.createElement(
                                wp.element.Fragment,
                                null,
                                renderRatingTypeScaleFields({
                                    type: childType,
                                    scale: childScale,
                                    setType: (val) => { updateChildLocally(child.id, { rating_type: val }); },
                                    setScale: (val) => { updateChildLocally(child.id, { scale: val }); },
                                    disabled: childHasVotes,
                                    typeHelp: childHasVotes
                                        ? __('Rating type cannot be changed after votes are cast.', 'shuriken-reviews')
                                        : null,
                                    scaleHelp: childHasVotes
                                        ? __('Scale cannot be changed after votes have been cast.', 'shuriken-reviews')
                                        : undefined,
                                    scaleHelpMode: 'range',
                                    showScaleMinMax: true
                                }),
                                wp.element.createElement(CheckboxControl, {
                                    label: __('Display Only (No Direct Voting)', 'shuriken-reviews'),
                                    checked: !!childDisplayOnly,
                                    onChange: (val) => { updateChildLocally(child.id, { display_only: val }); },
                                    help: __('When enabled, this sub-rating cannot be voted on directly.', 'shuriken-reviews')
                                }),
                                // Type-compatibility warning for existing sub-ratings
                                selectedRating && !areTypesCompatible(getRatingType(selectedRating), childType) && wp.element.createElement(Notice, {
                                    status: 'warning',
                                    isDismissible: false,
                                    style: { marginBottom: '8px' }
                                }, __('This sub-rating type is incompatible with the parent\'s type. Aggregated scores may be incorrect.', 'shuriken-reviews'))
                            );
                        })()
                    ),
                    // --- Mirrors for this child ---
                    (() => {
                        const cMirrors = childMirrorsMap[child.id];
                        const cHasMirrors = Array.isArray(cMirrors);
                        const cMirrorName = newChildMirrorNames[child.id] || '';
                        return wp.element.createElement(
                            'div',
                            { className: 'shuriken-child-mirrors-section' },
                            wp.element.createElement(
                                'div',
                                { className: 'shuriken-section-header' },
                                wp.element.createElement('h5', null, __('Mirrors', 'shuriken-reviews')),
                                cHasMirrors && wp.element.createElement('span', { className: 'shuriken-badge' }, cMirrors.length)
                            ),
                            !cHasMirrors && wp.element.createElement(
                                'div',
                                { className: 'shuriken-loading-row' },
                                wp.element.createElement(Spinner, null)
                            ),
                            cHasMirrors && cMirrors.length === 0 && wp.element.createElement(
                                'p',
                                { className: 'shuriken-empty-message' },
                                __('No mirrors.', 'shuriken-reviews')
                            ),
                            cHasMirrors && cMirrors.map((cm) => {
                                const isEditing = editingMirrorNames.hasOwnProperty(cm.id);
                                const isSaving = savingMirrorId === parseInt(cm.id, 10);

                                if (isEditing) {
                                    return wp.element.createElement(
                                        'div',
                                        { key: cm.id, className: 'shuriken-mirror-card is-editing' },
                                        wp.element.createElement(TextControl, {
                                            value: editingMirrorNames[cm.id],
                                            onChange: (val) => { updateEditingMirrorName(cm.id, val); },
                                            onKeyDown: (e) => {
                                                if (e.key === 'Enter') { e.preventDefault(); saveMirrorName(cm.id, parseInt(child.id, 10)); }
                                                if (e.key === 'Escape') { cancelEditMirror(cm.id); }
                                            },
                                            className: 'shuriken-mirror-edit-input'
                                        }),
                                        wp.element.createElement(
                                            'div',
                                            { className: 'shuriken-mirror-card-actions' },
                                            wp.element.createElement(Button, {
                                                variant: 'primary',
                                                isSmall: true,
                                                onClick: () => { saveMirrorName(cm.id, parseInt(child.id, 10)); },
                                                isBusy: isSaving,
                                                disabled: isSaving || !(editingMirrorNames[cm.id] || '').trim()
                                            }, __('Save', 'shuriken-reviews')),
                                            wp.element.createElement(Button, {
                                                variant: 'tertiary',
                                                isSmall: true,
                                                onClick: () => { cancelEditMirror(cm.id); },
                                                disabled: isSaving
                                            }, __('Cancel', 'shuriken-reviews'))
                                        )
                                    );
                                }

                                return wp.element.createElement(
                                    'div',
                                    { key: cm.id, className: 'shuriken-mirror-card' },
                                    wp.element.createElement('span', { className: 'shuriken-mirror-name' },
                                        cm.name,
                                        wp.element.createElement('span', { className: 'shuriken-id-badge' }, `#${cm.id}`)
                                    ),
                                    wp.element.createElement(
                                        'div',
                                        { className: 'shuriken-mirror-card-actions' },
                                        wp.element.createElement(Button, {
                                            icon: 'edit',
                                            label: __('Rename', 'shuriken-reviews'),
                                            isSmall: true,
                                            onClick: () => { startEditMirror(cm.id, cm.name); }
                                        }),
                                        wp.element.createElement(Button, {
                                            icon: 'trash',
                                            label: __('Delete', 'shuriken-reviews'),
                                            isSmall: true,
                                            isDestructive: true,
                                            onClick: () => { deleteMirror(cm.id, parseInt(child.id, 10)); }
                                        })
                                    )
                                );
                            }),
                            // Create new child mirror inline
                            wp.element.createElement(
                                'div',
                                { className: 'shuriken-inline-create' },
                                wp.element.createElement(TextControl, {
                                    value: cMirrorName,
                                    onChange: (value) => {
                                        setNewChildMirrorNames((prev) => {
                                            const next = {};
                                            for (const k in prev) next[k] = prev[k];
                                            next[child.id] = value;
                                            return next;
                                        });
                                    },
                                    onKeyDown: (e) => {
                                        if (e.key === 'Enter') { e.preventDefault(); createMirrorForChild(child.id); }
                                    },
                                    placeholder: __('New mirror name...', 'shuriken-reviews'),
                                    className: 'shuriken-inline-create-input'
                                }),
                                wp.element.createElement(Button, {
                                    variant: 'secondary',
                                    isSmall: true,
                                    onClick: () => { createMirrorForChild(child.id); },
                                    isBusy: creatingChildMirrorId === parseInt(child.id, 10),
                                    disabled: creatingChildMirrorId !== null || !cMirrorName.trim()
                                }, __('Add', 'shuriken-reviews'))
                            )
                        );
                    })()
                );
            }),

            // Unsaved changes notice
            Object.keys(childrenLocalEdits).length > 0 && wp.element.createElement(
                Notice,
                { status: 'info', isDismissible: false, className: 'shuriken-unsaved-notice' },
                __('You have unsaved changes. Click "Apply Changes" to save.', 'shuriken-reviews')
            ),

            // Footer actions
            wp.element.createElement(
                'div',
                { className: 'shuriken-modal-actions' },
                Object.keys(childrenLocalEdits).length > 0 && wp.element.createElement(Button, {
                    variant: 'primary',
                    onClick: applyChildrenEdits,
                    isBusy: savingChildren,
                    disabled: savingChildren
                }, __('Apply Changes', 'shuriken-reviews')),
                wp.element.createElement(Button, {
                    variant: Object.keys(childrenLocalEdits).length > 0 ? 'secondary' : 'primary',
                    onClick: () => {
                        const hasUnsaved = Object.keys(childrenLocalEdits).length > 0;
                        if (hasUnsaved && !confirm(__('You have unsaved changes. Close without saving?', 'shuriken-reviews'))) return;
                        setIsManageChildrenModalOpen(false);
                        setNewChildName('');
                        setNewChildEffectType('positive');
                        setChildrenLocalEdits({});
                        setNewChildMirrorNames({});
                        setEditingMirrorNames({});
                    },
                    disabled: savingChildren
                }, __('Close', 'shuriken-reviews'))
            )
        )
    );
}
