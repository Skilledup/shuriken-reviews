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
    const { useState, useEffect, useMemo, useCallback } = wp.element;
    const { __ } = wp.i18n;
    const { useSelect, useDispatch } = wp.data;

    const STORE_NAME = window.SHURIKEN_STORE_NAME || 'shuriken-reviews';
    const {
        makeErrorHandler,
        makeErrorDismissers,
        useSearchHandler,
        titleTagOptions,
        calculateAverage,
        renderRatingPreview,
        ratingTypeOptions,
        getScaleRange,
        getRatingType,
        buildColorSettings,
        areTypesCompatible,
        iconStar,
        iconTriangleAlert
    } = window.ShurikenBlockHelpers;

    registerBlockType('shuriken-reviews/rating', {
        icon: iconStar(24),
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { ratingId, titleTag, anchorTag, accentColor, starColor, postContext, hideTitle, buttonColor } = attributes;

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
            const [newRatingType, setNewRatingType] = useState('stars');
            const [newRatingScale, setNewRatingScale] = useState(5);
            const [editRatingType, setEditRatingType] = useState('stars');
            const [editRatingScale, setEditRatingScale] = useState(5);
            const [newRatingDescription, setNewRatingDescription] = useState('');
            const [editRatingDescription, setEditRatingDescription] = useState('');

            // Search state
            const [searchTerm, setSearchTerm] = useState('');

            // ---- Build CSS variables for accent / star colour ----
            var cssVars = {};
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
            const error = localError;

            // Error handling helpers (shared)
            const handleApiError = makeErrorHandler(setLocalError, setLastFailedAction);
            const { retryLastAction: _retry, dismissError } = makeErrorDismissers(setLocalError, setLastFailedAction, clearError);
            function retryLastAction() { _retry(lastFailedAction); }

            // Debounced search handler (shared)
            const handleSearchChange = useSearchHandler(searchRatings, 'all', 20);

            // Track search term for option building
            const handleSearchWithTerm = useCallback(function (term) {
                setSearchTerm(term);
                handleSearchChange(term);
            }, [handleSearchChange]);

            // Fetch selected rating and initial data on mount
            useEffect(function () {
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
                    requestData.rating_type = newRatingType;
                    var scaleRange = getScaleRange(newRatingType);
                    requestData.scale = Math.max(scaleRange.min, Math.min(scaleRange.max, newRatingScale));
                    if (newRatingParentId) {
                        requestData.parent_id = parseInt(newRatingParentId, 10);
                        requestData.effect_type = newRatingEffectType;
                    }
                    requestData.display_only = newRatingDisplayOnly;
                }

                requestData.label_description = newRatingDescription;

                storeCreateRating(requestData)
                    .then(function (data) {
                        setAttributes({ ratingId: parseInt(data.id, 10) });
                        setNewRatingName('');
                        setNewRatingMirrorOf('');
                        setNewRatingParentId('');
                        setNewRatingEffectType('positive');
                        setNewRatingDisplayOnly(false);
                        setNewRatingType('stars');
                        setNewRatingScale(5);
                        setNewRatingDescription('');
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
                setEditRatingType(selectedRating.rating_type || 'stars');
                setEditRatingScale(parseInt(selectedRating.scale, 10) || 5);
                setEditRatingDescription(selectedRating.label_description || '');
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
                    requestData.rating_type = editRatingType;
                    var scaleRange = getScaleRange(editRatingType);
                    requestData.scale = Math.max(scaleRange.min, Math.min(scaleRange.max, editRatingScale));
                    if (editRatingParentId) {
                        requestData.parent_id = parseInt(editRatingParentId, 10);
                        requestData.effect_type = editRatingEffectType;
                        requestData.display_only = false;
                    } else {
                        requestData.parent_id = 0;
                        requestData.display_only = editRatingDisplayOnly;
                    }
                }

                requestData.label_description = editRatingDescription;

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

            // Type-aware preview elements
            var preview = selectedRating ? renderRatingPreview(selectedRating, wp.element.createElement) : [null, null];

            // Loading state
            var loading = isLoadingRating || (ratingId && !selectedRating && !localError && !storeError);

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
                        }),
                        wp.element.createElement(CheckboxControl, {
                            label: __('Per-post voting', 'shuriken-reviews'),
                            checked: postContext,
                            onChange: function (value) {
                                setAttributes({ postContext: value });
                            },
                            help: __('When enabled, votes are counted separately for each post/page this block appears on.', 'shuriken-reviews')
                        }),
                        wp.element.createElement(CheckboxControl, {
                            label: __('Hide title & description', 'shuriken-reviews'),
                            checked: hideTitle,
                            onChange: function (value) {
                                setAttributes({ hideTitle: value });
                            },
                            help: __('Hide the rating title and description. Useful in Query Loop layouts.', 'shuriken-reviews')
                        })
                    ),
                    // Colors Panel (type-aware)
                    wp.element.createElement(PanelColorSettings, {
                        title: __('Colors', 'shuriken-reviews'),
                        initialOpen: false,
                        colorSettings: (function () {
                                var ratingType = selectedRating ? getRatingType(selectedRating) : 'stars';
                                var isNumeric = ratingType === 'numeric';
                                return buildColorSettings({
                                    ratingType: ratingType,
                                    accentColor: accentColor,
                                    starColor: starColor,
                                    buttonColor: buttonColor || undefined,
                                    setAccent: function (value) { setAttributes({ accentColor: value || '' }); },
                                    setStar: function (value) { setAttributes({ starColor: value || '' }); },
                                    setButton: isNumeric ? function (value) { setAttributes({ buttonColor: value || '' }); } : undefined
                                });
                            })()
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
                            setNewRatingType('stars');
                            setNewRatingScale(5);
                            setNewRatingDescription('');
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
                    wp.element.createElement(TextControl, {
                        label: __('Description', 'shuriken-reviews'),
                        value: newRatingDescription,
                        onChange: setNewRatingDescription,
                        placeholder: __('Optional description beneath rating title', 'shuriken-reviews'),
                        help: __('Optional text displayed beneath the rating title.', 'shuriken-reviews')
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
                        label: __('Rating Type', 'shuriken-reviews'),
                        value: newRatingType,
                        options: ratingTypeOptions,
                        onChange: function (value) {
                            setNewRatingType(value);
                            var range = getScaleRange(value);
                            if (newRatingScale < range.min || newRatingScale > range.max) {
                                setNewRatingScale(range.min === range.max ? range.min : 5);
                            }
                        },
                        help: __('Choose how users will rate this item.', 'shuriken-reviews')
                    }),
                    !newRatingMirrorOf && (newRatingType === 'stars' || newRatingType === 'numeric') && wp.element.createElement(TextControl, {
                        label: __('Scale', 'shuriken-reviews'),
                        type: 'number',
                        value: String(newRatingScale),
                        onChange: function (value) {
                            var range = getScaleRange(newRatingType);
                            var num = parseInt(value, 10);
                            if (!isNaN(num)) {
                                setNewRatingScale(Math.max(range.min, Math.min(range.max, num)));
                            }
                        },
                        help: newRatingType === 'stars'
                            ? __('Number of stars (2–10).', 'shuriken-reviews')
                            : __('Maximum slider value (2–100).', 'shuriken-reviews')
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
                            { label: __('Positive — Higher votes improve parent rating', 'shuriken-reviews'), value: 'positive' },
                            { label: __('Negative — Higher votes lower parent rating', 'shuriken-reviews'), value: 'negative' }
                        ],
                        onChange: setNewRatingEffectType,
                        help: __('Negative is useful for aspects like "Difficulty" or "Price" where higher values are worse.', 'shuriken-reviews')
                    }),
                    // Type-compatibility warning for sub-ratings
                    !newRatingMirrorOf && newRatingParentId && (function () {
                        var parent = parentRatings.find(function (r) { return String(r.id) === newRatingParentId; });
                        if (parent && !areTypesCompatible(parent.rating_type || 'stars', newRatingType)) {
                            return wp.element.createElement(Notice, {
                                status: 'warning',
                                isDismissible: false,
                                style: { marginBottom: '12px' }
                            }, __('This sub-rating type is incompatible with the parent\'s type. Mixing star/numeric types with like/dislike/approval types produces incorrect aggregated scores.', 'shuriken-reviews'));
                        }
                        return null;
                    })(),
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
                    wp.element.createElement(TextControl, {
                        label: __('Description', 'shuriken-reviews'),
                        value: editRatingDescription,
                        onChange: setEditRatingDescription,
                        placeholder: __('Optional description beneath rating title', 'shuriken-reviews'),
                        help: __('Optional text displayed beneath the rating title.', 'shuriken-reviews')
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
                        label: __('Rating Type', 'shuriken-reviews'),
                        value: editRatingType,
                        options: ratingTypeOptions,
                        onChange: function (value) {
                            setEditRatingType(value);
                            var range = getScaleRange(value);
                            if (editRatingScale < range.min || editRatingScale > range.max) {
                                setEditRatingScale(range.min === range.max ? range.min : 5);
                            }
                        },
                        disabled: (parseInt(selectedRating && selectedRating.total_votes, 10) || 0) > 0,
                        help: (parseInt(selectedRating && selectedRating.total_votes, 10) || 0) > 0
                            ? __('Type cannot be changed after votes have been cast.', 'shuriken-reviews')
                            : __('Choose how users will rate this item.', 'shuriken-reviews')
                    }),
                    !editRatingMirrorOf && (editRatingType === 'stars' || editRatingType === 'numeric') && wp.element.createElement(TextControl, {
                        label: __('Scale', 'shuriken-reviews'),
                        type: 'number',
                        value: String(editRatingScale),
                        onChange: function (value) {
                            var range = getScaleRange(editRatingType);
                            var num = parseInt(value, 10);
                            if (!isNaN(num)) {
                                setEditRatingScale(Math.max(range.min, Math.min(range.max, num)));
                            }
                        },
                        disabled: (parseInt(selectedRating && selectedRating.total_votes, 10) || 0) > 0,
                        help: (parseInt(selectedRating && selectedRating.total_votes, 10) || 0) > 0
                            ? __('Scale cannot be changed after votes have been cast.', 'shuriken-reviews')
                            : editRatingType === 'stars'
                                ? __('Number of stars (2–10).', 'shuriken-reviews')
                                : __('Maximum slider value (2–100).', 'shuriken-reviews')
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
                            { label: __('Positive — Higher votes improve parent rating', 'shuriken-reviews'), value: 'positive' },
                            { label: __('Negative — Higher votes lower parent rating', 'shuriken-reviews'), value: 'negative' }
                        ],
                        onChange: setEditRatingEffectType,
                        help: __('Negative is useful for aspects like "Difficulty" or "Price" where higher values are worse.', 'shuriken-reviews')
                    }),
                    // Type-compatibility warning for edit modal
                    !editRatingMirrorOf && editRatingParentId && (function () {
                        var parent = parentRatings.find(function (r) { return String(r.id) === editRatingParentId; });
                        if (parent && !areTypesCompatible(parent.rating_type || 'stars', editRatingType)) {
                            return wp.element.createElement(Notice, {
                                status: 'warning',
                                isDismissible: false,
                                style: { marginBottom: '12px' }
                            }, __('This sub-rating type is incompatible with the parent\'s type. Mixing star/numeric types with like/dislike/approval types produces incorrect aggregated scores.', 'shuriken-reviews'));
                        }
                        return null;
                    })(),
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
        save: function () { return null; }
    });
})(window.wp);
