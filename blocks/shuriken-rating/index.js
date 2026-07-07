/**
 * Shuriken Reviews - Rating Block (v2)
 *
 * Re-designed with style presets and simplified colour settings.
 * Visual design is handled entirely by CSS presets
 * (is-style-classic, is-style-card, etc.).
 *
 * Settings:
 *  - ratingId    : which rating to display
 *  - titleTag    : heading tag for the title
 *  - anchorTag   : optional anchor ID
 *  - accentColor : single accent colour driving preset colour scheme
 *  - starColor   : active star colour
 *
 * @package Shuriken_Reviews
 * @since 2.0.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import { PanelBody, TextControl, Button, Spinner, Modal, ComboboxControl, SelectControl, CheckboxControl, Notice } from '@wordpress/components';
import { createElement, Fragment, useState, useEffect, useMemo, useCallback, useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';

// Local element shim so the existing createElement/Fragment call sites
// (wp.element.*) keep working after the ESM migration.
const wp = { element: { createElement, Fragment } };

const STORE_NAME = window.SHURIKEN_STORE_NAME || 'shuriken-reviews';

// Initial shape shared by the create/edit rating forms.
const RATING_FORM_INIT = {
    name: '',
    mirrorOf: '',
    parentId: '',
    effectType: 'positive',
    displayOnly: false,
    type: 'stars',
    scale: 5,
    description: ''
};

/**
 * Reducer backing the create/edit rating forms. Keeps the many related
 * fields in a single state object instead of a dozen separate useState
 * hooks, and co-locates the field/merge/reset transitions.
 *
 * @param {Object} state  Current form state.
 * @param {Object} action { type: 'SET_FIELD'|'MERGE'|'RESET', ... }.
 * @return {Object} Next form state.
 */
function ratingFormReducer(state, action) {
    switch (action.type) {
        case 'SET_FIELD':
            return { ...state, [action.field]: action.value };
        case 'MERGE':
            return { ...state, ...action.payload };
        case 'RESET':
            return RATING_FORM_INIT;
        default:
            return state;
    }
}

const {
    useApiErrorHandling,
    useSearchHandler,
    titleTagOptions,
    calculateAverage,
    renderRatingPreview,
    getScaleRange,
    renderRatingTypeScaleFields,
    getRatingType,
    buildColorSettings,
    areTypesCompatible,
    iconStar,
    iconTriangleAlert
} = window.ShurikenBlockHelpers;

let settings = {
        icon: iconStar(24),
        edit: (props) => {
            const { attributes, setAttributes } = props;
            const { ratingId, titleTag, anchorTag, accentColor, starColor, postContext, hideTitle, buttonColor } = attributes;

            // Local UI state
            const [isModalOpen, setIsModalOpen] = useState(false);
            const [isEditModalOpen, setIsEditModalOpen] = useState(false);
            const [creating, setCreating] = useState(false);
            const [updating, setUpdating] = useState(false);

            // Create / edit rating form state (reducer-backed; see ratingFormReducer)
            const [createForm, dispatchCreate] = useReducer(ratingFormReducer, RATING_FORM_INIT);
            const [editForm, dispatchEdit] = useReducer(ratingFormReducer, RATING_FORM_INIT);
            const setCreateField = (field, value) => dispatchCreate({ type: 'SET_FIELD', field, value });
            const setEditField = (field, value) => dispatchEdit({ type: 'SET_FIELD', field, value });

            // Search state
            const [searchTerm, setSearchTerm] = useState('');

            // ---- Build CSS variables for accent / star colour ----
            const cssVars = {};
            if (accentColor) {
                cssVars['--shuriken-user-accent'] = accentColor;
            }
            if (starColor) {
                cssVars['--shuriken-user-star-color'] = starColor;
            }
            if (buttonColor) {
                cssVars['--shuriken-button-color'] = buttonColor;
            }

            // ---- Store helpers (MUST be declared BEFORE blockProps) ----
            const {
                fetchRating,
                searchRatings,
                fetchParentRatings,
                fetchMirrorableRatings,
                createRating: storeCreateRating,
                updateRating: storeUpdateRating,
                clearError
            } = useDispatch(STORE_NAME);

            const {
                selectedRating,
                searchResults,
                parentRatings,
                mirrorableRatings,
                isSearching,
                isLoadingRating,
                storeError
            } = useSelect((select) => {
                const store = select(STORE_NAME);
                return {
                    selectedRating: ratingId ? store.getRating(ratingId) : null,
                    searchResults: store.getSearchResults(),
                    parentRatings: store.getParentRatings(),
                    mirrorableRatings: store.getMirrorableRatings(),
                    isSearching: store.isSearching(),
                    isLoadingRating: ratingId ? store.isLoadingRating(ratingId) : false,
                    storeError: store.getLastError(),
                };
            }, [ratingId]);

            // ---- blockProps -- merge .shuriken-rating onto the wrapper
            //      when a rating is selected so is-style-* and .shuriken-rating
            //      live on the same DOM element (matching frontend HTML) ----
            const previewClass = (ratingId && selectedRating)
                ? ' shuriken-rating'
                : '';

            const blockProps = useBlockProps({
                className: `shuriken-rating-block-editor${previewClass}`,
                style: cssVars
            });

            // Error handling lifecycle (shared hook)
            const {
                error,
                setError: setLocalError,
                lastFailedAction,
                handleApiError,
                dismissError,
                retryLastAction
            } = useApiErrorHandling(clearError);

            // Debounced search handler (shared)
            const handleSearchChange = useSearchHandler(searchRatings, 'all', 20);

            // Track search term for option building
            const handleSearchWithTerm = useCallback((term) => {
                setSearchTerm(term);
                handleSearchChange(term);
            }, [handleSearchChange]);

            // Fetch selected rating and initial data on mount
            useEffect(() => {
                if (ratingId && !selectedRating) {
                    fetchRating(ratingId);
                }

                fetchParentRatings();
                fetchMirrorableRatings();
            }, []);

            // Fetch rating when ratingId changes
            useEffect(() => {
                if (ratingId && !selectedRating) {
                    fetchRating(ratingId);
                }
            }, [ratingId]);

            // Create new rating
            const createNewRating = () => {
                if (!createForm.name.trim() || creating) {
                    return;
                }

                setCreating(true);
                setLocalError(null);

                const requestData = { name: createForm.name };

                if (createForm.mirrorOf) {
                    requestData.mirror_of = parseInt(createForm.mirrorOf, 10);
                } else {
                    requestData.rating_type = createForm.type;
                    const scaleRange = getScaleRange(createForm.type);
                    requestData.scale = Math.max(scaleRange.min, Math.min(scaleRange.max, createForm.scale));
                    if (createForm.parentId) {
                        requestData.parent_id = parseInt(createForm.parentId, 10);
                        requestData.effect_type = createForm.effectType;
                    }
                    requestData.display_only = createForm.displayOnly;
                }

                requestData.label_description = createForm.description;

                storeCreateRating(requestData)
                    .then((data) => {
                        setAttributes({ ratingId: parseInt(data.id, 10) });
                        dispatchCreate({ type: 'RESET' });
                        setIsModalOpen(false);
                        setCreating(false);
                    })
                    .catch((err) => {
                        setCreating(false);
                        handleApiError(err, createNewRating);
                    });
            };

            // Open edit modal with current rating data
            const openEditModal = () => {
                if (!selectedRating) {
                    return;
                }
                const displayOnlyValue = selectedRating.display_only;
                const isDisplayOnly = displayOnlyValue === true || displayOnlyValue === 'true' || parseInt(displayOnlyValue, 10) === 1;
                dispatchEdit({
                    type: 'MERGE',
                    payload: {
                        name: selectedRating.name || '',
                        mirrorOf: selectedRating.mirror_of ? String(selectedRating.mirror_of) : '',
                        parentId: selectedRating.parent_id ? String(selectedRating.parent_id) : '',
                        effectType: selectedRating.effect_type || 'positive',
                        displayOnly: isDisplayOnly,
                        type: selectedRating.rating_type || 'stars',
                        scale: parseInt(selectedRating.scale, 10) || 5,
                        description: selectedRating.label_description || ''
                    }
                });
                setIsEditModalOpen(true);
            };

            // Update existing rating
            const updateRatingFn = () => {
                if (!editForm.name.trim() || updating || !selectedRating) {
                    return;
                }

                setUpdating(true);
                setLocalError(null);

                const requestData = { name: editForm.name };

                if (editForm.mirrorOf) {
                    requestData.mirror_of = parseInt(editForm.mirrorOf, 10);
                    requestData.parent_id = 0;
                    requestData.display_only = false;
                } else {
                    requestData.mirror_of = 0;
                    requestData.rating_type = editForm.type;
                    const scaleRange = getScaleRange(editForm.type);
                    requestData.scale = Math.max(scaleRange.min, Math.min(scaleRange.max, editForm.scale));
                    if (editForm.parentId) {
                        requestData.parent_id = parseInt(editForm.parentId, 10);
                        requestData.effect_type = editForm.effectType;
                        requestData.display_only = false;
                    } else {
                        requestData.parent_id = 0;
                        requestData.display_only = editForm.displayOnly;
                    }
                }

                requestData.label_description = editForm.description;

                storeUpdateRating(selectedRating.id, requestData)
                    .then(() => {
                        setIsEditModalOpen(false);
                        setUpdating(false);
                    })
                    .catch((err) => {
                        setUpdating(false);
                        handleApiError(err, updateRatingFn);
                    });
            };

            // Handle Enter key in modals
            const handleKeyDown = (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    createNewRating();
                }
            };

            const handleEditKeyDown = (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    updateRatingFn();
                }
            };

            // Build rating options for ComboboxControl
            const ratingOptions = useMemo(() => {
                const options = [];
                const seenIds = {};

                if (selectedRating && selectedRating.id) {
                    seenIds[selectedRating.id] = true;
                    options.push({
                        label: `${selectedRating.name} (ID: ${selectedRating.id})`,
                        value: String(selectedRating.id)
                    });
                }

                if (searchTerm && searchTerm.trim().length > 0) {
                    const results = Array.isArray(searchResults) ? searchResults : [];
                    results.forEach((rating) => {
                        if (rating && rating.id && !seenIds[rating.id]) {
                            seenIds[rating.id] = true;
                            options.push({
                                label: `${rating.name} (ID: ${rating.id})`,
                                value: String(rating.id)
                            });
                        }
                    });
                }

                return options;
            }, [searchResults, selectedRating, searchTerm]);

            // Type-aware preview elements
            const preview = selectedRating ? renderRatingPreview(selectedRating, wp.element.createElement) : [null, null];

            // Loading state
            const loading = isLoadingRating || (ratingId && !selectedRating && !error && !storeError);

            // ---- Render ----
            return wp.element.createElement(
                wp.element.Fragment,
                null,
                // Inspector Controls
                wp.element.createElement(
                    InspectorControls,
                    null,
                    // Settings Panel
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Settings', 'shuriken-reviews'), initialOpen: true },
                        wp.element.createElement(ComboboxControl, {
                            label: __('Select Rating', 'shuriken-reviews'),
                            value: ratingId ? String(ratingId) : '',
                            options: ratingOptions,
                            onChange: (value) => {
                                setAttributes({ ratingId: value ? parseInt(value, 10) : 0 });
                            },
                            onFilterValueChange: handleSearchWithTerm,
                            placeholder: isSearching
                                ? __('Searching...', 'shuriken-reviews')
                                : __('Type to search ratings...', 'shuriken-reviews')
                        }),
                        isSearching && wp.element.createElement(
                            'div',
                            { style: { display: 'flex', alignItems: 'center', gap: '8px', marginTop: '8px' } },
                            wp.element.createElement(Spinner, { style: { margin: 0 } }),
                            wp.element.createElement('span', null, __('Searching...', 'shuriken-reviews'))
                        ),
                        wp.element.createElement(
                            'div',
                            { style: { display: 'flex', gap: '8px', marginTop: '12px', marginBottom: '16px' } },
                            wp.element.createElement(Button, {
                                variant: 'secondary',
                                onClick: () => { setIsModalOpen(true); }
                            }, __('Create New', 'shuriken-reviews')),
                            selectedRating && wp.element.createElement(Button, {
                                variant: 'secondary',
                                onClick: openEditModal
                            }, __('Edit Selected', 'shuriken-reviews'))
                        ),
                        wp.element.createElement(ComboboxControl, {
                            label: __('Title Tag', 'shuriken-reviews'),
                            value: titleTag,
                            options: titleTagOptions,
                            onChange: (value) => {
                                setAttributes({ titleTag: value || 'h2' });
                            },
                            onFilterValueChange: () => {}
                        }),
                        wp.element.createElement(TextControl, {
                            label: __('Anchor ID', 'shuriken-reviews'),
                            value: anchorTag,
                            onChange: (value) => {
                                setAttributes({ anchorTag: value });
                            },
                            help: __('Optional anchor ID for linking to this rating.', 'shuriken-reviews')
                        }),
                        wp.element.createElement(CheckboxControl, {
                            label: __('Per-post voting', 'shuriken-reviews'),
                            checked: postContext,
                            onChange: (value) => {
                                setAttributes({ postContext: value });
                            },
                            help: __('When enabled, votes are counted separately for each post/page this block appears on.', 'shuriken-reviews')
                        }),
                        wp.element.createElement(CheckboxControl, {
                            label: __('Hide title & description', 'shuriken-reviews'),
                            checked: hideTitle,
                            onChange: (value) => {
                                setAttributes({ hideTitle: value });
                            },
                            help: __('Hide the rating name and description. Useful in Query Loop layouts.', 'shuriken-reviews')
                        })
                    ),
                    // Colors Panel (type-aware)
                    wp.element.createElement(PanelColorSettings, {
                        title: __('Colors', 'shuriken-reviews'),
                        initialOpen: false,
                        colorSettings: (() => {
                                const ratingType = selectedRating ? getRatingType(selectedRating) : 'stars';
                                const isNumeric = ratingType === 'numeric';
                                return buildColorSettings({
                                    ratingType: ratingType,
                                    accentColor: accentColor,
                                    starColor: starColor,
                                    buttonColor: buttonColor || undefined,
                                    setAccent: (value) => { setAttributes({ accentColor: value || '' }); },
                                    setStar: (value) => { setAttributes({ starColor: value || '' }); },
                                    setButton: isNumeric ? (value) => { setAttributes({ buttonColor: value || '' }); } : undefined
                                });
                            })()
                    })
                ),
                // Create Rating Modal
                isModalOpen && wp.element.createElement(
                    Modal,
                    {
                        title: __('Create New Rating', 'shuriken-reviews'),
                        onRequestClose: () => {
                            setIsModalOpen(false);
                            dispatchCreate({ type: 'RESET' });
                        },
                        style: { width: '500px' }
                    },
                    wp.element.createElement(TextControl, {
                        label: __('Rating Name', 'shuriken-reviews'),
                        value: createForm.name,
                        onChange: (value) => setCreateField('name', value),
                        onKeyDown: handleKeyDown,
                        placeholder: __('Enter rating name...', 'shuriken-reviews'),
                        help: __('Enter a descriptive name for this rating.', 'shuriken-reviews')
                    }),
                    wp.element.createElement(TextControl, {
                        label: __('Description', 'shuriken-reviews'),
                        value: createForm.description,
                        onChange: (value) => setCreateField('description', value),
                        placeholder: __('Optional description beneath rating name', 'shuriken-reviews'),
                        help: __('Optional text displayed beneath the rating name.', 'shuriken-reviews')
                    }),
                    wp.element.createElement(SelectControl, {
                        label: __('Mirror of', 'shuriken-reviews'),
                        value: createForm.mirrorOf,
                        options: [{ label: __(' Not a Mirror ', 'shuriken-reviews'), value: '' }].concat(
                            mirrorableRatings.map((r) => {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: (value) => {
                            setCreateField('mirrorOf', value);
                            if (value) {
                                setCreateField('parentId', '');
                                setCreateField('displayOnly', false);
                            }
                        },
                        help: __('Mirrors share vote data with another rating.', 'shuriken-reviews')
                    }),
                    !createForm.mirrorOf && renderRatingTypeScaleFields({
                        type: createForm.type,
                        scale: createForm.scale,
                        setType: (value) => setCreateField('type', value),
                        setScale: (value) => setCreateField('scale', value)
                    }),
                    !createForm.mirrorOf && wp.element.createElement(SelectControl, {
                        label: __('Parent Rating', 'shuriken-reviews'),
                        value: createForm.parentId,
                        options: [{ label: __(' None ', 'shuriken-reviews'), value: '' }].concat(
                            parentRatings.map((r) => {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: (value) => setCreateField('parentId', value),
                        help: __('Sub-ratings contribute to parent score.', 'shuriken-reviews')
                    }),
                    !createForm.mirrorOf && createForm.parentId && wp.element.createElement(SelectControl, {
                        label: __('Effect on Parent', 'shuriken-reviews'),
                        value: createForm.effectType,
                        options: [
                            { label: __('Positive — Higher votes improve parent rating', 'shuriken-reviews'), value: 'positive' },
                            { label: __('Negative — Higher votes lower parent rating', 'shuriken-reviews'), value: 'negative' }
                        ],
                        onChange: (value) => setCreateField('effectType', value),
                        help: __('Negative is useful for aspects like "Difficulty" or "Price" where higher values are worse.', 'shuriken-reviews')
                    }),
                    // Type-compatibility warning for sub-ratings
                    !createForm.mirrorOf && createForm.parentId && (() => {
                        const parent = parentRatings.find((r) => { return String(r.id) === createForm.parentId; });
                        if (parent && !areTypesCompatible(parent.rating_type || 'stars', createForm.type)) {
                            return wp.element.createElement(Notice, {
                                status: 'warning',
                                isDismissible: false,
                                style: { marginBottom: '12px' }
                            }, __('This sub-rating type is incompatible with the parent\'s type. Mixing star/numeric types with like/dislike/approval types produces incorrect aggregated scores.', 'shuriken-reviews'));
                        }
                        return null;
                    })(),
                    !createForm.mirrorOf && !createForm.parentId && wp.element.createElement(CheckboxControl, {
                        label: __('Display Only', 'shuriken-reviews'),
                        checked: createForm.displayOnly,
                        onChange: (value) => setCreateField('displayOnly', value),
                        help: __('No direct voting allowed.', 'shuriken-reviews')
                    }),
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', display: 'flex', gap: '8px', justifyContent: 'flex-end' } },
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            onClick: () => { setIsModalOpen(false); }
                        }, __('Cancel', 'shuriken-reviews')),
                        wp.element.createElement(Button, {
                            variant: 'primary',
                            onClick: createNewRating,
                            isBusy: creating,
                            disabled: creating || !createForm.name.trim()
                        }, __('Create', 'shuriken-reviews'))
                    )
                ),
                // Edit Rating Modal
                isEditModalOpen && wp.element.createElement(
                    Modal,
                    {
                        title: __('Edit Rating', 'shuriken-reviews'),
                        onRequestClose: () => { setIsEditModalOpen(false); },
                        style: { width: '500px' }
                    },
                    wp.element.createElement(TextControl, {
                        label: __('Rating Name', 'shuriken-reviews'),
                        value: editForm.name,
                        onChange: (value) => setEditField('name', value),
                        onKeyDown: handleEditKeyDown
                    }),
                    wp.element.createElement(TextControl, {
                        label: __('Description', 'shuriken-reviews'),
                        value: editForm.description,
                        onChange: (value) => setEditField('description', value),
                        placeholder: __('Optional description beneath rating name', 'shuriken-reviews'),
                        help: __('Optional text displayed beneath the rating name.', 'shuriken-reviews')
                    }),
                    wp.element.createElement(SelectControl, {
                        label: __('Mirror of', 'shuriken-reviews'),
                        value: editForm.mirrorOf,
                        options: [{ label: __(' Not a Mirror ', 'shuriken-reviews'), value: '' }].concat(
                            mirrorableRatings.filter((r) => {
                                return parseInt(r.id, 10) !== parseInt(ratingId, 10);
                            }).map((r) => {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: (value) => {
                            setEditField('mirrorOf', value);
                            if (value) {
                                setEditField('parentId', '');
                                setEditField('displayOnly', false);
                            }
                        }
                    }),
                    !editForm.mirrorOf && renderRatingTypeScaleFields({
                        type: editForm.type,
                        scale: editForm.scale,
                        setType: (value) => setEditField('type', value),
                        setScale: (value) => setEditField('scale', value),
                        disabled: (parseInt(selectedRating && selectedRating.total_votes, 10) || 0) > 0,
                        typeHelp: (parseInt(selectedRating && selectedRating.total_votes, 10) || 0) > 0
                            ? __('Type cannot be changed after votes have been cast.', 'shuriken-reviews')
                            : __('Choose how users will rate this item.', 'shuriken-reviews'),
                        scaleHelp: (parseInt(selectedRating && selectedRating.total_votes, 10) || 0) > 0
                            ? __('Scale cannot be changed after votes have been cast.', 'shuriken-reviews')
                            : undefined
                    }),
                    !editForm.mirrorOf && wp.element.createElement(SelectControl, {
                        label: __('Parent Rating', 'shuriken-reviews'),
                        value: editForm.parentId,
                        options: [{ label: __(' None ', 'shuriken-reviews'), value: '' }].concat(
                            parentRatings.filter((r) => {
                                return parseInt(r.id, 10) !== parseInt(ratingId, 10);
                            }).map((r) => {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: (value) => setEditField('parentId', value)
                    }),
                    !editForm.mirrorOf && editForm.parentId && wp.element.createElement(SelectControl, {
                        label: __('Effect on Parent', 'shuriken-reviews'),
                        value: editForm.effectType,
                        options: [
                            { label: __('Positive — Higher votes improve parent rating', 'shuriken-reviews'), value: 'positive' },
                            { label: __('Negative — Higher votes lower parent rating', 'shuriken-reviews'), value: 'negative' }
                        ],
                        onChange: (value) => setEditField('effectType', value),
                        help: __('Negative is useful for aspects like "Difficulty" or "Price" where higher values are worse.', 'shuriken-reviews')
                    }),
                    // Type-compatibility warning for edit modal
                    !editForm.mirrorOf && editForm.parentId && (() => {
                        const parent = parentRatings.find((r) => { return String(r.id) === editForm.parentId; });
                        if (parent && !areTypesCompatible(parent.rating_type || 'stars', editForm.type)) {
                            return wp.element.createElement(Notice, {
                                status: 'warning',
                                isDismissible: false,
                                style: { marginBottom: '12px' }
                            }, __('This sub-rating type is incompatible with the parent\'s type. Mixing star/numeric types with like/dislike/approval types produces incorrect aggregated scores.', 'shuriken-reviews'));
                        }
                        return null;
                    })(),
                    !editForm.mirrorOf && !editForm.parentId && wp.element.createElement(CheckboxControl, {
                        label: __('Display Only', 'shuriken-reviews'),
                        checked: editForm.displayOnly,
                        onChange: (value) => setEditField('displayOnly', value)
                    }),
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', display: 'flex', gap: '8px', justifyContent: 'flex-end' } },
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            onClick: () => { setIsEditModalOpen(false); }
                        }, __('Cancel', 'shuriken-reviews')),
                        wp.element.createElement(Button, {
                            variant: 'primary',
                            onClick: updateRatingFn,
                            isBusy: updating,
                            disabled: updating || !editForm.name.trim()
                        }, __('Update', 'shuriken-reviews'))
                    )
                ),
                // Block content -- .shuriken-rating is on blockProps, NOT on an inner div
                wp.element.createElement(
                    'div',
                    blockProps,
                    error && wp.element.createElement(
                        Notice,
                        {
                            status: 'error',
                            onRemove: dismissError,
                            isDismissible: true,
                            actions: lastFailedAction ? [{
                                label: __('Retry', 'shuriken-reviews'),
                                onClick: retryLastAction
                            }] : []
                        },
                        error
                    ),
                    loading
                        ? wp.element.createElement(Spinner, null)
                        : !ratingId
                            ? wp.element.createElement(
                                'div',
                                { className: 'shuriken-rating-placeholder' },
                                iconStar(40),
                                wp.element.createElement('p', null, __('Select a rating from the sidebar or create a new one.', 'shuriken-reviews'))
                            )
                            : selectedRating
                                ? wp.element.createElement(
                                    'div',
                                    { className: 'shuriken-rating-wrapper' },
                                    !hideTitle && wp.element.createElement(titleTag, { className: 'rating-title' }, selectedRating.name),
                                    !hideTitle && selectedRating.label_description && wp.element.createElement('p', { className: 'rating-description' }, selectedRating.label_description),
                                    preview[0],
                                    preview[1]
                                )
                                : wp.element.createElement(
                                    'div',
                                    { className: 'shuriken-rating-placeholder' },
                                    iconTriangleAlert(40),
                                    wp.element.createElement('p', null, __('Rating not found.', 'shuriken-reviews'))
                                )
                )
            );
        },
        save: () => null
    };

    if (typeof window.wp?.hooks?.applyFilters === 'function') {
        settings = window.wp.hooks.applyFilters('shurikenBlockSettings_rating', settings);
    }

    registerBlockType('shuriken-reviews/rating', settings);
