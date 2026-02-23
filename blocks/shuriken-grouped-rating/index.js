/**
 * Shuriken Reviews — Grouped Rating Block (v2)
 *
 * Re-designed with style presets and simplified settings.
 * The heavy visual design is handled entirely by CSS presets
 * (is-style-gradient, is-style-minimal, etc.).
 *
 * Settings:
 *  - ratingId      : which parent rating to show
 *  - titleTag      : heading tag for the parent title
 *  - anchorTag     : optional anchor ID
 *  - accentColor   : single accent color driving the preset's colour scheme
 *  - starColor     : active star colour
 *  - childLayout   : "grid" (default) or "list"
 *
 * @package Shuriken_Reviews
 * @since 2.0.0
 */

(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, InspectorControls, PanelColorSettings } = wp.blockEditor;
    const {
        PanelBody,
        TextControl,
        Button,
        Spinner,
        Modal,
        ComboboxControl,
        SelectControl,
        CheckboxControl,
        Notice,
        __experimentalDivider: Divider
    } = wp.components;
    const { useState, useEffect, useMemo, useRef, useCallback } = wp.element;
    const { __ } = wp.i18n;
    const { useSelect, useDispatch } = wp.data;

    // Store name constant
    const STORE_NAME = 'shuriken-reviews';

    registerBlockType('shuriken-reviews/grouped-rating', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const {
                ratingId,
                titleTag,
                anchorTag,
                accentColor,
                starColor,
                childLayout
            } = attributes;

            // ---- Local UI state ----
            const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
            const [isEditModalOpen, setIsEditModalOpen] = useState(false);
            const [isManageChildrenModalOpen, setIsManageChildrenModalOpen] = useState(false);
            const [newParentName, setNewParentName] = useState('');
            const [newParentDisplayOnly, setNewParentDisplayOnly] = useState(true);
            const [editParentName, setEditParentName] = useState('');
            const [editParentDisplayOnly, setEditParentDisplayOnly] = useState(true);
            const [childrenToManage, setChildrenToManage] = useState([]);
            const [childrenLocalEdits, setChildrenLocalEdits] = useState({});
            const [newChildName, setNewChildName] = useState('');
            const [newChildEffectType, setNewChildEffectType] = useState('positive');
            const [creating, setCreating] = useState(false);
            const [updating, setUpdating] = useState(false);
            const [managingChildren, setManagingChildren] = useState(false);
            const [savingChildren, setSavingChildren] = useState(false);
            const [error, setError] = useState(null);
            const [lastFailedAction, setLastFailedAction] = useState(null);

            // Search state for AJAX dropdown
            const [searchTerm, setSearchTerm] = useState('');
            const searchTimeoutRef = useRef(null);

            // ---- Build CSS variables for accent/star colour ----
            var cssVars = {};
            if (accentColor) {
                cssVars['--shuriken-user-accent'] = accentColor;
            }
            if (starColor) {
                cssVars['--shuriken-user-star-color'] = starColor;
            }

            var layoutClass = childLayout === 'list' ? ' is-layout-list' : '';

            // ---- Shared store helpers (must be declared before blockProps) ----
            const {
                fetchRating,
                searchRatings,
                fetchParentRatings,
                fetchChildRatings,
                createRating,
                updateRating,
                deleteRating
            } = useDispatch(STORE_NAME);

            const {
                selectedRating,
                allRatingsById,
                parentRatings,
                searchResults,
                isSearching,
                isLoadingParents,
                storeError
            } = useSelect(function (select) {
                var store = select(STORE_NAME);
                return {
                    selectedRating: ratingId ? store.getRating(ratingId) : null,
                    allRatingsById: store.getRatingsById ? store.getRatingsById() : {},
                    parentRatings: store.getParentRatings(),
                    searchResults: store.getSearchResults(),
                    isSearching: store.isSearching(),
                    isLoadingParents: store.isLoadingParents(),
                    storeError: store.getLastError ? store.getLastError() : null
                };
            }, [ratingId]);

            // Add shuriken-rating-group to blockProps so that is-style-* and
            // shuriken-rating-group end up on the SAME element — matching
            // the frontend HTML structure and making preset CSS work.
            var previewClass = (ratingId && selectedRating)
                ? ' shuriken-rating-group'
                : '';

            const blockProps = useBlockProps({
                className: 'shuriken-grouped-rating-block-editor' + previewClass + layoutClass,
                style: cssVars
            });

            const loading = isLoadingParents && parentRatings.length === 0;

            // ---- Error handling ----
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
                setError(errorMessage);
                setLastFailedAction(action);
            }

            function retryLastAction() {
                setError(null);
                if (lastFailedAction) {
                    lastFailedAction();
                    setLastFailedAction(null);
                }
            }

            function dismissError() {
                setError(null);
                setLastFailedAction(null);
            }

            // ---- Search ----
            const handleSearchChange = useCallback(function (value) {
                setSearchTerm(value);
                if (searchTimeoutRef.current) {
                    clearTimeout(searchTimeoutRef.current);
                }
                searchTimeoutRef.current = setTimeout(function () {
                    if (value && value.trim().length > 0) {
                        searchRatings(value.trim(), 'parents', 20);
                    }
                }, 300);
            }, [searchRatings]);

            // ---- Data fetching ----
            useEffect(function () {
                fetchParentRatings();
                if (ratingId) {
                    fetchRating(ratingId);
                    fetchChildRatings(ratingId);
                }
                return function () {
                    if (searchTimeoutRef.current) {
                        clearTimeout(searchTimeoutRef.current);
                    }
                };
            }, []);

            useEffect(function () {
                if (ratingId) {
                    fetchChildRatings(ratingId);
                }
            }, [ratingId]);

            // ---- Child ratings from store ----
            const childRatings = useMemo(function () {
                if (!ratingId) return [];
                var children = [];
                var ratingsObj = allRatingsById || {};
                Object.values(ratingsObj).forEach(function (r) {
                    if (r && r.parent_id && parseInt(r.parent_id, 10) === parseInt(ratingId, 10)) {
                        children.push(r);
                    }
                });
                return children;
            }, [ratingId, allRatingsById]);

            // ---- CRUD helpers (unchanged logic) ----
            function createNewParentRating() {
                if (!newParentName.trim() || creating) return;
                setCreating(true);
                createRating({ name: newParentName, display_only: newParentDisplayOnly })
                    .then(function (data) {
                        setAttributes({ ratingId: parseInt(data.id, 10) });
                        fetchParentRatings();
                        setNewParentName('');
                        setNewParentDisplayOnly(true);
                        setIsCreateModalOpen(false);
                        setCreating(false);
                        setError(null);
                    })
                    .catch(function (err) {
                        setCreating(false);
                        handleApiError(err, createNewParentRating);
                    });
            }

            function openEditParentModal() {
                if (!selectedRating) return;
                setEditParentName(selectedRating.name || '');
                var dv = selectedRating.display_only;
                setEditParentDisplayOnly(dv === true || dv === 'true' || parseInt(dv, 10) === 1);
                setIsEditModalOpen(true);
            }

            function updateParentRating() {
                if (!editParentName.trim() || updating || !selectedRating) return;
                setUpdating(true);
                updateRating(selectedRating.id, { name: editParentName, display_only: editParentDisplayOnly })
                    .then(function () {
                        fetchParentRatings();
                        setIsEditModalOpen(false);
                        setUpdating(false);
                        setError(null);
                    })
                    .catch(function (err) {
                        setUpdating(false);
                        handleApiError(err, updateParentRating);
                    });
            }

            function openManageChildrenModal() {
                if (!selectedRating) return;
                setChildrenToManage(childRatings.slice());
                setChildrenLocalEdits({});
                setIsManageChildrenModalOpen(true);
            }

            function addNewChild() {
                if (!newChildName.trim() || managingChildren || !selectedRating) return;
                setManagingChildren(true);
                createRating({
                    name: newChildName,
                    parent_id: parseInt(selectedRating.id, 10),
                    effect_type: newChildEffectType,
                    display_only: false
                })
                    .then(function (data) {
                        setChildrenToManage(function (prev) { return [data].concat(prev); });
                        setNewChildName('');
                        setNewChildEffectType('positive');
                        setManagingChildren(false);
                        setError(null);
                    })
                    .catch(function (err) {
                        setManagingChildren(false);
                        handleApiError(err, addNewChild);
                    });
            }

            function updateChildLocally(childId, updates) {
                setChildrenLocalEdits(function (prev) {
                    var existing = prev[childId] || {};
                    var merged = {};
                    for (var k in existing) merged[k] = existing[k];
                    for (var k in updates) merged[k] = updates[k];
                    var next = {};
                    for (var k in prev) next[k] = prev[k];
                    next[childId] = merged;
                    return next;
                });
            }

            function applyChildrenEdits() {
                var editIds = Object.keys(childrenLocalEdits);
                if (editIds.length === 0) return;
                setSavingChildren(true);
                setError(null);
                Promise.all(editIds.map(function (id) {
                    return updateRating(id, childrenLocalEdits[id]);
                }))
                    .then(function (results) {
                        setChildrenToManage(function (prev) {
                            var updated = prev.slice();
                            results.forEach(function (data) {
                                var idx = updated.findIndex(function (r) {
                                    return parseInt(r.id, 10) === parseInt(data.id, 10);
                                });
                                if (idx !== -1) updated[idx] = data;
                            });
                            return updated;
                        });
                        setChildrenLocalEdits({});
                        setSavingChildren(false);
                        setError(null);
                    })
                    .catch(function (err) {
                        setSavingChildren(false);
                        handleApiError(err, applyChildrenEdits);
                    });
            }

            function deleteChild(childId) {
                if (!confirm(__('Are you sure you want to delete this sub-rating?', 'shuriken-reviews'))) return;
                var retry = function () { deleteChild(childId); };
                deleteRating(childId)
                    .then(function () {
                        setChildrenToManage(function (prev) {
                            return prev.filter(function (r) { return parseInt(r.id, 10) !== parseInt(childId, 10); });
                        });
                        setError(null);
                    })
                    .catch(function (err) { handleApiError(err, retry); });
            }

            // Keyboard handlers
            function handleCreateKeyDown(e) { if (e.key === 'Enter') { e.preventDefault(); createNewParentRating(); } }
            function handleEditKeyDown(e) { if (e.key === 'Enter') { e.preventDefault(); updateParentRating(); } }
            function handleChildKeyDown(e) { if (e.key === 'Enter') { e.preventDefault(); addNewChild(); } }

            // ---- Dropdown options ----
            var ratingOptions = useMemo(function () {
                var options = [];
                var seen = {};
                if (selectedRating && selectedRating.id) {
                    seen[selectedRating.id] = true;
                    options.push({ label: selectedRating.name + ' (ID: ' + selectedRating.id + ')', value: String(selectedRating.id) });
                }
                if (searchTerm && searchTerm.trim().length > 0) {
                    (Array.isArray(searchResults) ? searchResults : []).forEach(function (r) {
                        if (r && r.id && !seen[r.id]) {
                            seen[r.id] = true;
                            options.push({ label: r.name + ' (ID: ' + r.id + ')', value: String(r.id) });
                        }
                    });
                }
                return options;
            }, [searchResults, selectedRating, searchTerm]);

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

            function calculateAverage(rating) {
                var tv = parseInt(rating.total_votes, 10) || 0;
                if (tv > 0) return Math.round((parseInt(rating.total_rating, 10) / tv) * 10) / 10;
                return 0;
            }

            // ===================================================================
            // Render
            // ===================================================================
            return wp.element.createElement(
                wp.element.Fragment,
                null,

                // --- Inspector Controls ---
                wp.element.createElement(
                    InspectorControls,
                    null,

                    // Panel 1 — Rating Selection
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Grouped Rating Settings', 'shuriken-reviews'), initialOpen: true },
                        loading
                            ? wp.element.createElement(Spinner, null)
                            : wp.element.createElement(
                                wp.element.Fragment,
                                null,
                                wp.element.createElement(ComboboxControl, {
                                    label: __('Select Parent Rating', 'shuriken-reviews'),
                                    value: ratingId ? String(ratingId) : '',
                                    options: ratingOptions,
                                    onChange: function (value) {
                                        setAttributes({ ratingId: value ? parseInt(value, 10) : 0 });
                                        if (value) fetchRating(parseInt(value, 10));
                                    },
                                    onFilterValueChange: handleSearchChange,
                                    placeholder: isSearching
                                        ? __('Searching...', 'shuriken-reviews')
                                        : __('Search parent ratings...', 'shuriken-reviews')
                                }),
                                wp.element.createElement(
                                    'div',
                                    { style: { display: 'flex', gap: '8px', marginTop: '12px', marginBottom: '16px', flexWrap: 'wrap' } },
                                    wp.element.createElement(Button, {
                                        variant: 'secondary',
                                        onClick: function () { setIsCreateModalOpen(true); }
                                    }, __('Create New', 'shuriken-reviews')),
                                    selectedRating && wp.element.createElement(Button, {
                                        variant: 'secondary',
                                        onClick: openEditParentModal
                                    }, __('Edit Parent', 'shuriken-reviews')),
                                    selectedRating && wp.element.createElement(Button, {
                                        variant: 'primary',
                                        onClick: openManageChildrenModal
                                    }, __('Manage Sub-Ratings', 'shuriken-reviews'))
                                )
                            ),
                        wp.element.createElement(ComboboxControl, {
                            label: __('Title Tag', 'shuriken-reviews'),
                            value: titleTag,
                            options: titleTagOptions,
                            onChange: function (value) { setAttributes({ titleTag: value || 'h2' }); },
                            onFilterValueChange: function () {}
                        }),
                        wp.element.createElement(TextControl, {
                            label: __('Anchor ID', 'shuriken-reviews'),
                            value: anchorTag,
                            onChange: function (value) { setAttributes({ anchorTag: value }); },
                            help: __('Optional anchor ID for linking to this rating group.', 'shuriken-reviews')
                        })
                    ),

                    // Panel 2 — Layout
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Layout', 'shuriken-reviews'), initialOpen: false },
                        wp.element.createElement(SelectControl, {
                            label: __('Child Ratings Layout', 'shuriken-reviews'),
                            value: childLayout || 'grid',
                            options: [
                                { label: __('Grid (cards)', 'shuriken-reviews'), value: 'grid' },
                                { label: __('List (stacked rows)', 'shuriken-reviews'), value: 'list' }
                            ],
                            onChange: function (value) { setAttributes({ childLayout: value }); },
                            help: __('Grid shows children as cards in columns. List shows them as full-width rows.', 'shuriken-reviews')
                        })
                    ),

                    // Panel 3 — Colors (simple: accent + star)
                    wp.element.createElement(
                        PanelColorSettings,
                        {
                            title: __('Colors', 'shuriken-reviews'),
                            initialOpen: false,
                            colorSettings: [
                                {
                                    label: __('Accent Color', 'shuriken-reviews'),
                                    value: accentColor || undefined,
                                    onChange: function (value) { setAttributes({ accentColor: value || '' }); }
                                },
                                {
                                    label: __('Star Color', 'shuriken-reviews'),
                                    value: starColor || undefined,
                                    onChange: function (value) { setAttributes({ starColor: value || '' }); }
                                }
                            ]
                        }
                    )
                ),

                // --- Create Modal ---
                isCreateModalOpen && wp.element.createElement(
                    Modal,
                    {
                        title: __('Create New Parent Rating', 'shuriken-reviews'),
                        onRequestClose: function () {
                            setIsCreateModalOpen(false);
                            setNewParentName('');
                            setNewParentDisplayOnly(true);
                        },
                        style: { width: '500px' }
                    },
                    wp.element.createElement(TextControl, {
                        label: __('Parent Rating Name', 'shuriken-reviews'),
                        value: newParentName,
                        onChange: setNewParentName,
                        onKeyDown: handleCreateKeyDown,
                        placeholder: __('e.g., Overall Product Quality', 'shuriken-reviews'),
                        help: __('This will be the main rating that groups sub-ratings.', 'shuriken-reviews')
                    }),
                    wp.element.createElement(CheckboxControl, {
                        label: __('Display Only (No Direct Voting)', 'shuriken-reviews'),
                        checked: newParentDisplayOnly,
                        onChange: setNewParentDisplayOnly,
                        help: __('When enabled, users can only vote on sub-ratings. The parent shows the calculated average.', 'shuriken-reviews')
                    }),
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', display: 'flex', gap: '8px', justifyContent: 'flex-end' } },
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            onClick: function () {
                                setIsCreateModalOpen(false);
                                setNewParentName('');
                                setNewParentDisplayOnly(true);
                            }
                        }, __('Cancel', 'shuriken-reviews')),
                        wp.element.createElement(Button, {
                            variant: 'primary',
                            onClick: createNewParentRating,
                            isBusy: creating,
                            disabled: creating || !newParentName.trim()
                        }, __('Create', 'shuriken-reviews'))
                    )
                ),

                // --- Edit Modal ---
                isEditModalOpen && wp.element.createElement(
                    Modal,
                    {
                        title: __('Edit Parent Rating', 'shuriken-reviews'),
                        onRequestClose: function () { setIsEditModalOpen(false); },
                        style: { width: '500px' }
                    },
                    wp.element.createElement(TextControl, {
                        label: __('Parent Rating Name', 'shuriken-reviews'),
                        value: editParentName,
                        onChange: setEditParentName,
                        onKeyDown: handleEditKeyDown,
                        placeholder: __('Enter rating name...', 'shuriken-reviews')
                    }),
                    wp.element.createElement(CheckboxControl, {
                        label: __('Display Only (No Direct Voting)', 'shuriken-reviews'),
                        checked: editParentDisplayOnly,
                        onChange: setEditParentDisplayOnly,
                        help: __('When enabled, users can only vote on sub-ratings. The parent shows the calculated average.', 'shuriken-reviews')
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
                            onClick: updateParentRating,
                            isBusy: updating,
                            disabled: updating || !editParentName.trim()
                        }, __('Update', 'shuriken-reviews'))
                    )
                ),

                // --- Manage Children Modal ---
                isManageChildrenModalOpen && wp.element.createElement(
                    Modal,
                    {
                        title: __('Manage Sub-Ratings', 'shuriken-reviews'),
                        onRequestClose: function () {
                            var hasUnsaved = Object.keys(childrenLocalEdits).length > 0;
                            if (hasUnsaved && !confirm(__('You have unsaved changes. Close without saving?', 'shuriken-reviews'))) return;
                            setIsManageChildrenModalOpen(false);
                            setNewChildName('');
                            setNewChildEffectType('positive');
                            setChildrenLocalEdits({});
                        },
                        style: { width: '600px', maxWidth: '90vw' },
                        className: 'shuriken-manage-children-modal'
                    },
                    selectedRating && wp.element.createElement(
                        'div',
                        { style: { marginBottom: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                        wp.element.createElement('p', { style: { fontSize: '14px', color: '#666', marginTop: 0, marginBottom: 0 } },
                            __('Managing sub-ratings for: ', 'shuriken-reviews') + selectedRating.name
                        ),
                        Object.keys(childrenLocalEdits).length > 0 && wp.element.createElement(
                            'span',
                            { style: { fontSize: '12px', color: '#667eea', fontWeight: '500', backgroundColor: '#f0f4ff', padding: '4px 8px', borderRadius: '4px' } },
                            Object.keys(childrenLocalEdits).length + ' ' + __('unsaved', 'shuriken-reviews')
                        )
                    ),
                    wp.element.createElement(
                        'div',
                        { style: { marginBottom: '20px', padding: '16px', backgroundColor: '#f0f0f0', borderRadius: '4px' } },
                        wp.element.createElement('h4', { style: { marginTop: 0, fontSize: '14px' } }, __('Add New Sub-Rating', 'shuriken-reviews')),
                        wp.element.createElement(TextControl, {
                            label: __('Sub-Rating Name', 'shuriken-reviews'),
                            value: newChildName,
                            onChange: setNewChildName,
                            onKeyDown: handleChildKeyDown,
                            placeholder: __('e.g., Build Quality, Features, Value for Money', 'shuriken-reviews')
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
                        wp.element.createElement(
                            'div',
                            { style: { display: 'flex', justifyContent: 'flex-end', marginTop: '12px' } },
                            wp.element.createElement(Button, {
                                variant: 'primary',
                                onClick: addNewChild,
                                isBusy: managingChildren,
                                disabled: managingChildren || !newChildName.trim()
                            }, __('Add Sub-Rating', 'shuriken-reviews'))
                        )
                    ),
                    Divider && wp.element.createElement(Divider, null),
                    wp.element.createElement('h4', { style: { fontSize: '14px', marginBottom: '12px' } },
                        __('Existing Sub-Ratings', 'shuriken-reviews') + ' (' + childrenToManage.length + ')'
                    ),
                    childrenToManage.length === 0 && wp.element.createElement(
                        'p',
                        { style: { color: '#666', fontStyle: 'italic' } },
                        __('No sub-ratings yet. Add one above.', 'shuriken-reviews')
                    ),
                    childrenToManage.map(function (child) {
                        return wp.element.createElement(
                            'div',
                            {
                                key: child.id,
                                style: {
                                    padding: '12px',
                                    marginBottom: '12px',
                                    border: childrenLocalEdits[child.id] ? '2px solid #667eea' : '1px solid #ddd',
                                    borderRadius: '4px',
                                    backgroundColor: childrenLocalEdits[child.id] ? '#f0f4ff' : '#fff'
                                }
                            },
                            wp.element.createElement(
                                'div',
                                { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '8px' } },
                                wp.element.createElement('strong', { style: { fontSize: '14px' } }, child.name),
                                wp.element.createElement(Button, {
                                    variant: 'tertiary',
                                    isDestructive: true,
                                    onClick: function () { deleteChild(child.id); },
                                    style: { padding: '4px 8px', fontSize: '12px' }
                                }, __('Delete', 'shuriken-reviews'))
                            ),
                            wp.element.createElement(
                                'div',
                                { style: { display: 'grid', gap: '8px' } },
                                wp.element.createElement(TextControl, {
                                    label: __('Name', 'shuriken-reviews'),
                                    value: (childrenLocalEdits[child.id] && childrenLocalEdits[child.id].name) || child.name,
                                    onChange: function (value) { updateChildLocally(child.id, { name: value }); },
                                    style: { marginBottom: 0 }
                                }),
                                wp.element.createElement(SelectControl, {
                                    label: __('Effect Type', 'shuriken-reviews'),
                                    value: (childrenLocalEdits[child.id] && childrenLocalEdits[child.id].effect_type) || child.effect_type || 'positive',
                                    options: [
                                        { label: __('Positive', 'shuriken-reviews'), value: 'positive' },
                                        { label: __('Negative', 'shuriken-reviews'), value: 'negative' }
                                    ],
                                    onChange: function (value) { updateChildLocally(child.id, { effect_type: value }); }
                                })
                            ),
                            wp.element.createElement(
                                'div',
                                { style: { fontSize: '12px', color: '#666', marginTop: '8px' } },
                                __('Votes: ', 'shuriken-reviews') + (child.total_votes || 0) + ' | ' +
                                __('Average: ', 'shuriken-reviews') + calculateAverage(child) + '/5'
                            )
                        );
                    }),
                    Object.keys(childrenLocalEdits).length > 0 && wp.element.createElement(
                        Notice,
                        { status: 'info', isDismissible: false, style: { marginTop: '16px' } },
                        __('You have unsaved changes. Click "Apply Changes" to save.', 'shuriken-reviews')
                    ),
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '20px', display: 'flex', gap: '8px', justifyContent: 'flex-end' } },
                        Object.keys(childrenLocalEdits).length > 0 && wp.element.createElement(Button, {
                            variant: 'primary',
                            onClick: applyChildrenEdits,
                            isBusy: savingChildren,
                            disabled: savingChildren
                        }, __('Apply Changes', 'shuriken-reviews')),
                        wp.element.createElement(Button, {
                            variant: Object.keys(childrenLocalEdits).length > 0 ? 'secondary' : 'primary',
                            onClick: function () {
                                var hasUnsaved = Object.keys(childrenLocalEdits).length > 0;
                                if (hasUnsaved && !confirm(__('You have unsaved changes. Close without saving?', 'shuriken-reviews'))) return;
                                setIsManageChildrenModalOpen(false);
                                setNewChildName('');
                                setNewChildEffectType('positive');
                                setChildrenLocalEdits({});
                            },
                            disabled: savingChildren
                        }, __('Close', 'shuriken-reviews'))
                    )
                ),

                // --- Block Preview ---
                wp.element.createElement(
                    'div',
                    blockProps,
                    error && wp.element.createElement(
                        Notice,
                        {
                            status: 'error',
                            onRemove: dismissError,
                            isDismissible: true,
                            actions: lastFailedAction ? [{ label: __('Retry', 'shuriken-reviews'), onClick: retryLastAction }] : []
                        },
                        error
                    ),
                    loading
                        ? wp.element.createElement(Spinner, null)
                        : !ratingId
                            ? wp.element.createElement(
                                'div',
                                { className: 'shuriken-grouped-rating-placeholder' },
                                wp.element.createElement('span', { className: 'dashicons dashicons-networking' }),
                                wp.element.createElement('p', null, __('Select a parent rating to display the group.', 'shuriken-reviews'))
                            )
                            : selectedRating
                                ? wp.element.createElement(
                                    wp.element.Fragment,
                                    null,
                                    // Parent rating — rendered directly inside blockProps
                                    // (blockProps already has .shuriken-rating-group)
                                    wp.element.createElement(
                                        'div',
                                        { className: 'shuriken-rating parent-rating' + (selectedRating.display_only ? ' display-only' : '') },
                                        wp.element.createElement(
                                            'div',
                                            { className: 'shuriken-rating-wrapper' },
                                            wp.element.createElement(
                                                titleTag,
                                                { className: 'rating-title' },
                                                selectedRating.name
                                            ),
                                            wp.element.createElement(
                                                'div',
                                                { className: 'stars display-only-stars' },
                                                [1, 2, 3, 4, 5].map(function (i) {
                                                    var isActive = i <= calculateAverage(selectedRating);
                                                    return wp.element.createElement(
                                                        'span',
                                                        { key: i, className: 'star' + (isActive ? ' active' : '') },
                                                        '\u2605'
                                                    );
                                                })
                                            ),
                                            wp.element.createElement(
                                                'div',
                                                { className: 'rating-stats' },
                                                __('Average:', 'shuriken-reviews') + ' ' + calculateAverage(selectedRating) + '/5 (' + (selectedRating.total_votes || 0) + ' ' + __('votes', 'shuriken-reviews') + ')'
                                            )
                                        )
                                    ),
                                    // Child ratings
                                    childRatings.length > 0 && wp.element.createElement(
                                        'div',
                                        { className: 'shuriken-child-ratings' },
                                        childRatings.map(function (child) {
                                            return wp.element.createElement(
                                                'div',
                                                { key: child.id, className: 'shuriken-rating child-rating' },
                                                wp.element.createElement(
                                                    'div',
                                                    { className: 'shuriken-rating-wrapper' },
                                                    wp.element.createElement(
                                                        'h4',
                                                        { className: 'rating-title' },
                                                        child.name
                                                    ),
                                                    wp.element.createElement(
                                                        'div',
                                                        { className: 'stars display-only-stars' },
                                                        [1, 2, 3, 4, 5].map(function (i) {
                                                            var isActive = i <= calculateAverage(child);
                                                            return wp.element.createElement(
                                                                'span',
                                                                { key: i, className: 'star' + (isActive ? ' active' : '') },
                                                                '\u2605'
                                                            );
                                                        })
                                                    ),
                                                    wp.element.createElement(
                                                        'div',
                                                        { className: 'rating-stats' },
                                                        calculateAverage(child) + '/5'
                                                    )
                                                )
                                            );
                                        })
                                    )
                                )
                                : wp.element.createElement(
                                    'div',
                                    { className: 'shuriken-grouped-rating-placeholder' },
                                    wp.element.createElement('span', { className: 'dashicons dashicons-warning' }),
                                    wp.element.createElement('p', null, __('Rating not found. It may have been deleted.', 'shuriken-reviews'))
                                )
                )
            );
        },

        save: function () {
            // Dynamic block — rendered on server
            return null;
        }
    });
})(window.wp);
