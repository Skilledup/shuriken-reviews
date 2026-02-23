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

(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, InspectorControls, PanelColorSettings } = wp.blockEditor;
    const { PanelBody, TextControl, Button, Spinner, Modal, ComboboxControl, SelectControl, CheckboxControl, Notice } = wp.components;
    const { useState, useEffect, useMemo, useRef, useCallback } = wp.element;
    const { __ } = wp.i18n;
    const { useSelect, useDispatch } = wp.data;

    const STORE_NAME = window.SHURIKEN_STORE_NAME || 'shuriken-reviews';

    registerBlockType('shuriken-reviews/rating', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { ratingId, titleTag, anchorTag, accentColor, starColor } = attributes;

            // Local UI state
            const [isModalOpen, setIsModalOpen] = useState(false);
            const [isEditModalOpen, setIsEditModalOpen] = useState(false);
            const [newRatingName, setNewRatingName] = useState('');
            const [newRatingMirrorOf, setNewRatingMirrorOf] = useState('');
            const [newRatingParentId, setNewRatingParentId] = useState('');
            const [newRatingEffectType, setNewRatingEffectType] = useState('positive');
            const [newRatingDisplayOnly, setNewRatingDisplayOnly] = useState(false);
            const [editRatingName, setEditRatingName] = useState('');
            const [editRatingMirrorOf, setEditRatingMirrorOf] = useState('');
            const [editRatingParentId, setEditRatingParentId] = useState('');
            const [editRatingEffectType, setEditRatingEffectType] = useState('positive');
            const [editRatingDisplayOnly, setEditRatingDisplayOnly] = useState(false);
            const [creating, setCreating] = useState(false);
            const [updating, setUpdating] = useState(false);
            const [localError, setLocalError] = useState(null);
            const [lastFailedAction, setLastFailedAction] = useState(null);

            // Search state
            const [searchTerm, setSearchTerm] = useState('');
            const searchTimeoutRef = useRef(null);
            const initialFetchDone = useRef(false);

            // ---- Build CSS variables for accent / star colour ----
            var cssVars = {};
            if (accentColor) {
                cssVars['--shuriken-user-accent'] = accentColor;
            }
            if (starColor) {
                cssVars['--shuriken-user-star-color'] = starColor;
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
            } = useSelect(function (select) {
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
            var previewClass = (ratingId && selectedRating)
                ? ' shuriken-rating'
                : '';

            const blockProps = useBlockProps({
                className: 'shuriken-rating-block-editor' + previewClass,
                style: cssVars
            });

            // Combined error state
            const error = localError || storeError;

            // Error handling helper
            function handleApiError(err, action) {
                console.error('Shuriken Reviews API Error:', err);

                var errorMessage = __('An unexpected error occurred.', 'shuriken-reviews');

                if (err.message) {
                    errorMessage = err.message;
                } else if (err.data && err.data.message) {
                    errorMessage = err.data.message;
                } else if (typeof err === 'string') {
                    errorMessage = err;
                }

                if (err.code === 'rest_forbidden' || err.code === 'rest_cookie_invalid_nonce') {
                    errorMessage = __('Permission denied. Please refresh the page and try again.', 'shuriken-reviews');
                } else if (err.code === 'rest_no_route') {
                    errorMessage = __('API endpoint not found. Please ensure the plugin is properly installed.', 'shuriken-reviews');
                } else if (err.status === 429 || err.code === 'rate_limit_exceeded') {
                    errorMessage = __('Too many requests. Please wait a moment and try again.', 'shuriken-reviews');
                } else if (err.status === 404 || err.code === 'not_found') {
                    errorMessage = __('The requested resource was not found.', 'shuriken-reviews');
                } else if (err.status === 500 || err.code === 'internal_server_error') {
                    errorMessage = __('Server error. Please try again later.', 'shuriken-reviews');
                }

                setLocalError(errorMessage);
                setLastFailedAction(action);
            }

            function retryLastAction() {
                setLocalError(null);
                clearError();
                if (lastFailedAction) {
                    lastFailedAction();
                    setLastFailedAction(null);
                }
            }

            function dismissError() {
                setLocalError(null);
                clearError();
                setLastFailedAction(null);
            }

            // Fetch selected rating and initial data on mount
            useEffect(function () {
                if (initialFetchDone.current) {
                    return;
                }
                initialFetchDone.current = true;

                if (ratingId && !selectedRating) {
                    fetchRating(ratingId);
                }

                fetchParentRatings();
                fetchMirrorableRatings();
            }, []);

            // Fetch rating when ratingId changes
            useEffect(function () {
                if (ratingId && !selectedRating) {
                    fetchRating(ratingId);
                }
            }, [ratingId]);

            // Debounced search handler
            const handleSearchChange = useCallback(function (term) {
                setSearchTerm(term);

                if (searchTimeoutRef.current) {
                    clearTimeout(searchTimeoutRef.current);
                }

                if (term && term.trim().length > 0) {
                    searchTimeoutRef.current = setTimeout(function () {
                        searchRatings(term.trim(), 'all', 20);
                    }, 300);
                }
            }, [searchRatings]);

            // Cleanup timeout on unmount
            useEffect(function () {
                return function () {
                    if (searchTimeoutRef.current) {
                        clearTimeout(searchTimeoutRef.current);
                    }
                };
            }, []);

            // Create new rating
            function createNewRating() {
                if (!newRatingName.trim() || creating) {
                    return;
                }

                setCreating(true);
                setLocalError(null);

                var requestData = { name: newRatingName };

                if (newRatingMirrorOf) {
                    requestData.mirror_of = parseInt(newRatingMirrorOf, 10);
                } else {
                    if (newRatingParentId) {
                        requestData.parent_id = parseInt(newRatingParentId, 10);
                        requestData.effect_type = newRatingEffectType;
                    }
                    requestData.display_only = newRatingDisplayOnly;
                }

                storeCreateRating(requestData)
                    .then(function (data) {
                        setAttributes({ ratingId: parseInt(data.id, 10) });
                        setNewRatingName('');
                        setNewRatingMirrorOf('');
                        setNewRatingParentId('');
                        setNewRatingEffectType('positive');
                        setNewRatingDisplayOnly(false);
                        setIsModalOpen(false);
                        setCreating(false);
                    })
                    .catch(function (err) {
                        setCreating(false);
                        handleApiError(err, createNewRating);
                    });
            }

            // Open edit modal with current rating data
            function openEditModal() {
                if (!selectedRating) {
                    return;
                }
                setEditRatingName(selectedRating.name || '');
                setEditRatingMirrorOf(selectedRating.mirror_of ? String(selectedRating.mirror_of) : '');
                setEditRatingParentId(selectedRating.parent_id ? String(selectedRating.parent_id) : '');
                setEditRatingEffectType(selectedRating.effect_type || 'positive');
                var displayOnlyValue = selectedRating.display_only;
                var isDisplayOnly = displayOnlyValue === true || displayOnlyValue === 'true' || parseInt(displayOnlyValue, 10) === 1;
                setEditRatingDisplayOnly(isDisplayOnly);
                setIsEditModalOpen(true);
            }

            // Update existing rating
            function updateRatingFn() {
                if (!editRatingName.trim() || updating || !selectedRating) {
                    return;
                }

                setUpdating(true);
                setLocalError(null);

                var requestData = { name: editRatingName };

                if (editRatingMirrorOf) {
                    requestData.mirror_of = parseInt(editRatingMirrorOf, 10);
                    requestData.parent_id = 0;
                    requestData.display_only = false;
                } else {
                    requestData.mirror_of = 0;
                    if (editRatingParentId) {
                        requestData.parent_id = parseInt(editRatingParentId, 10);
                        requestData.effect_type = editRatingEffectType;
                        requestData.display_only = false;
                    } else {
                        requestData.parent_id = 0;
                        requestData.display_only = editRatingDisplayOnly;
                    }
                }

                storeUpdateRating(selectedRating.id, requestData)
                    .then(function () {
                        setIsEditModalOpen(false);
                        setUpdating(false);
                    })
                    .catch(function (err) {
                        setUpdating(false);
                        handleApiError(err, updateRatingFn);
                    });
            }

            // Handle Enter key in modals
            function handleKeyDown(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    createNewRating();
                }
            }

            function handleEditKeyDown(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    updateRatingFn();
                }
            }

            // Build rating options for ComboboxControl
            var ratingOptions = useMemo(function () {
                var options = [];
                var seenIds = {};

                if (selectedRating && selectedRating.id) {
                    seenIds[selectedRating.id] = true;
                    options.push({
                        label: selectedRating.name + ' (ID: ' + selectedRating.id + ')',
                        value: String(selectedRating.id)
                    });
                }

                if (searchTerm && searchTerm.trim().length > 0) {
                    var results = Array.isArray(searchResults) ? searchResults : [];
                    results.forEach(function (rating) {
                        if (rating && rating.id && !seenIds[rating.id]) {
                            seenIds[rating.id] = true;
                            options.push({
                                label: rating.name + ' (ID: ' + rating.id + ')',
                                value: String(rating.id)
                            });
                        }
                    });
                }

                return options;
            }, [searchResults, selectedRating, searchTerm]);

            // Title tag options
            var titleTagOptions = [
                { label: 'H1', value: 'h1' },
                { label: 'H2', value: 'h2' },
                { label: 'H3', value: 'h3' },
                { label: 'H4', value: 'h4' },
                { label: 'H5', value: 'h5' },
                { label: 'H6', value: 'h6' },
                { label: 'DIV', value: 'div' },
                { label: 'P', value: 'p' },
                { label: 'SPAN', value: 'span' }
            ];

            // Calculate average for preview
            var average = 0;
            var totalVotes = 0;
            if (selectedRating) {
                totalVotes = parseInt(selectedRating.total_votes, 10) || 0;
                if (totalVotes > 0) {
                    average = Math.round((parseInt(selectedRating.total_rating, 10) / totalVotes) * 10) / 10;
                }
            }

            // Loading state
            var loading = isLoadingRating || (ratingId && !selectedRating && !error);

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
                            onChange: function (value) {
                                setAttributes({ ratingId: value ? parseInt(value, 10) : 0 });
                            },
                            onFilterValueChange: handleSearchChange,
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
                                onClick: function () { setIsModalOpen(true); }
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
                            onChange: function (value) {
                                setAttributes({ titleTag: value || 'h2' });
                            },
                            onFilterValueChange: function () {}
                        }),
                        wp.element.createElement(TextControl, {
                            label: __('Anchor ID', 'shuriken-reviews'),
                            value: anchorTag,
                            onChange: function (value) {
                                setAttributes({ anchorTag: value });
                            },
                            help: __('Optional anchor ID for linking to this rating.', 'shuriken-reviews')
                        })
                    ),
                    // Colors Panel
                    wp.element.createElement(PanelColorSettings, {
                        title: __('Colors', 'shuriken-reviews'),
                        initialOpen: false,
                        colorSettings: [
                            {
                                value: accentColor,
                                onChange: function (value) { setAttributes({ accentColor: value || '' }); },
                                label: __('Accent Color', 'shuriken-reviews')
                            },
                            {
                                value: starColor,
                                onChange: function (value) { setAttributes({ starColor: value || '' }); },
                                label: __('Star Color', 'shuriken-reviews')
                            }
                        ]
                    })
                ),
                // Create Rating Modal
                isModalOpen && wp.element.createElement(
                    Modal,
                    {
                        title: __('Create New Rating', 'shuriken-reviews'),
                        onRequestClose: function () {
                            setIsModalOpen(false);
                            setNewRatingName('');
                            setNewRatingMirrorOf('');
                            setNewRatingParentId('');
                            setNewRatingEffectType('positive');
                            setNewRatingDisplayOnly(false);
                        },
                        style: { width: '500px' }
                    },
                    wp.element.createElement(TextControl, {
                        label: __('Rating Name', 'shuriken-reviews'),
                        value: newRatingName,
                        onChange: setNewRatingName,
                        onKeyDown: handleKeyDown,
                        placeholder: __('Enter rating name...', 'shuriken-reviews'),
                        help: __('Enter a descriptive name for this rating.', 'shuriken-reviews')
                    }),
                    wp.element.createElement(SelectControl, {
                        label: __('Mirror of', 'shuriken-reviews'),
                        value: newRatingMirrorOf,
                        options: [{ label: __(' Not a Mirror ', 'shuriken-reviews'), value: '' }].concat(
                            mirrorableRatings.map(function (r) {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: function (value) {
                            setNewRatingMirrorOf(value);
                            if (value) {
                                setNewRatingParentId('');
                                setNewRatingDisplayOnly(false);
                            }
                        },
                        help: __('Mirrors share vote data with another rating.', 'shuriken-reviews')
                    }),
                    !newRatingMirrorOf && wp.element.createElement(SelectControl, {
                        label: __('Parent Rating', 'shuriken-reviews'),
                        value: newRatingParentId,
                        options: [{ label: __(' None ', 'shuriken-reviews'), value: '' }].concat(
                            parentRatings.map(function (r) {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: setNewRatingParentId,
                        help: __('Sub-ratings contribute to parent score.', 'shuriken-reviews')
                    }),
                    !newRatingMirrorOf && newRatingParentId && wp.element.createElement(SelectControl, {
                        label: __('Effect on Parent', 'shuriken-reviews'),
                        value: newRatingEffectType,
                        options: [
                            { label: __('Positive', 'shuriken-reviews'), value: 'positive' },
                            { label: __('Negative', 'shuriken-reviews'), value: 'negative' }
                        ],
                        onChange: setNewRatingEffectType
                    }),
                    !newRatingMirrorOf && !newRatingParentId && wp.element.createElement(CheckboxControl, {
                        label: __('Display Only', 'shuriken-reviews'),
                        checked: newRatingDisplayOnly,
                        onChange: setNewRatingDisplayOnly,
                        help: __('No direct voting allowed.', 'shuriken-reviews')
                    }),
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', display: 'flex', gap: '8px', justifyContent: 'flex-end' } },
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            onClick: function () { setIsModalOpen(false); }
                        }, __('Cancel', 'shuriken-reviews')),
                        wp.element.createElement(Button, {
                            variant: 'primary',
                            onClick: createNewRating,
                            isBusy: creating,
                            disabled: creating || !newRatingName.trim()
                        }, __('Create', 'shuriken-reviews'))
                    )
                ),
                // Edit Rating Modal
                isEditModalOpen && wp.element.createElement(
                    Modal,
                    {
                        title: __('Edit Rating', 'shuriken-reviews'),
                        onRequestClose: function () { setIsEditModalOpen(false); },
                        style: { width: '500px' }
                    },
                    wp.element.createElement(TextControl, {
                        label: __('Rating Name', 'shuriken-reviews'),
                        value: editRatingName,
                        onChange: setEditRatingName,
                        onKeyDown: handleEditKeyDown
                    }),
                    wp.element.createElement(SelectControl, {
                        label: __('Mirror of', 'shuriken-reviews'),
                        value: editRatingMirrorOf,
                        options: [{ label: __(' Not a Mirror ', 'shuriken-reviews'), value: '' }].concat(
                            mirrorableRatings.filter(function (r) {
                                return parseInt(r.id, 10) !== parseInt(ratingId, 10);
                            }).map(function (r) {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: function (value) {
                            setEditRatingMirrorOf(value);
                            if (value) {
                                setEditRatingParentId('');
                                setEditRatingDisplayOnly(false);
                            }
                        }
                    }),
                    !editRatingMirrorOf && wp.element.createElement(SelectControl, {
                        label: __('Parent Rating', 'shuriken-reviews'),
                        value: editRatingParentId,
                        options: [{ label: __(' None ', 'shuriken-reviews'), value: '' }].concat(
                            parentRatings.filter(function (r) {
                                return parseInt(r.id, 10) !== parseInt(ratingId, 10);
                            }).map(function (r) {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: setEditRatingParentId
                    }),
                    !editRatingMirrorOf && editRatingParentId && wp.element.createElement(SelectControl, {
                        label: __('Effect on Parent', 'shuriken-reviews'),
                        value: editRatingEffectType,
                        options: [
                            { label: __('Positive', 'shuriken-reviews'), value: 'positive' },
                            { label: __('Negative', 'shuriken-reviews'), value: 'negative' }
                        ],
                        onChange: setEditRatingEffectType
                    }),
                    !editRatingMirrorOf && !editRatingParentId && wp.element.createElement(CheckboxControl, {
                        label: __('Display Only', 'shuriken-reviews'),
                        checked: editRatingDisplayOnly,
                        onChange: setEditRatingDisplayOnly
                    }),
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', display: 'flex', gap: '8px', justifyContent: 'flex-end' } },
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            onClick: function () { setIsEditModalOpen(false); }
                        }, __('Cancel', 'shuriken-reviews')),
                        wp.element.createElement(Button, {
                            variant: 'primary',
                            onClick: updateRatingFn,
                            isBusy: updating,
                            disabled: updating || !editRatingName.trim()
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
                                wp.element.createElement('span', { className: 'dashicons dashicons-star-filled' }),
                                wp.element.createElement('p', null, __('Select a rating from the sidebar or create a new one.', 'shuriken-reviews'))
                            )
                            : selectedRating
                                ? wp.element.createElement(
                                    // Render wrapper directly inside blockProps (no extra .shuriken-rating div)
                                    'div',
                                    { className: 'shuriken-rating-wrapper' },
                                    wp.element.createElement(titleTag, { className: 'rating-title' }, selectedRating.name),
                                    wp.element.createElement(
                                        'div',
                                        { className: 'stars' },
                                        [1, 2, 3, 4, 5].map(function (i) {
                                            return wp.element.createElement('span', {
                                                key: i,
                                                className: 'star' + (i <= average ? ' active' : '')
                                            }, '\u2605');
                                        })
                                    ),
                                    wp.element.createElement('div', { className: 'rating-stats' },
                                        __('Average:', 'shuriken-reviews') + ' ' + average + '/5 (' + totalVotes + ' ' + __('votes', 'shuriken-reviews') + ')'
                                    )
                                )
                                : wp.element.createElement(
                                    'div',
                                    { className: 'shuriken-rating-placeholder' },
                                    wp.element.createElement('span', { className: 'dashicons dashicons-warning' }),
                                    wp.element.createElement('p', null, __('Rating not found.', 'shuriken-reviews'))
                                )
                )
            );
        },
        save: function () { return null; }
    });
})(window.wp);
