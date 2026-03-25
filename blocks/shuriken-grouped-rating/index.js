/**
 * Shuriken Reviews — Grouped Rating Block (v2.1)
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
 *  - mirrorId      : mirror of parent rating to display (0 = use original)
 *  - subRatings    : ordered array of { id, mirrorId, visible } controlling child display
 *
 * @package Shuriken_Reviews
 * @since 2.1.0
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
        Icon,
        __experimentalDivider: Divider
    } = wp.components;
    const { useState, useEffect, useMemo, useRef, useCallback } = wp.element;
    const { __ } = wp.i18n;
    const { useSelect, useDispatch } = wp.data;

    // Store name constant (match single rating block's fallback pattern)
    const STORE_NAME = window.SHURIKEN_STORE_NAME || 'shuriken-reviews';

    registerBlockType('shuriken-reviews/grouped-rating', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const {
                ratingId,
                titleTag,
                anchorTag,
                accentColor,
                starColor,
                childLayout,
                mirrorId,
                subRatings
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

            // Drag state for sub-rating reorder
            const [dragIndex, setDragIndex] = useState(null);
            const [dragOverIndex, setDragOverIndex] = useState(null);

            // Mirror management state
            const [newRatingIsMirror, setNewRatingIsMirror] = useState(false);
            const [newMirrorSourceId, setNewMirrorSourceId] = useState(0);
            const [newMirrorName, setNewMirrorName] = useState('');
            const [creatingMirror, setCreatingMirror] = useState(false);
            const [newChildMirrorNames, setNewChildMirrorNames] = useState({});
            const [creatingChildMirrorId, setCreatingChildMirrorId] = useState(null);

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
                fetchMirrorableRatings,
                fetchChildRatings,
                fetchMirrorsForRating,
                invalidateMirrorsCache,
                createRating,
                updateRating,
                deleteRating,
                clearError
            } = useDispatch(STORE_NAME);

            const {
                selectedRating,
                allRatingsById,
                parentRatings,
                mirrorableRatings,
                isLoadingMirrorable,
                searchResults,
                isSearching,
                isLoadingParents,
                storeError,
                parentMirrors,
                parentMirrorsLoading
            } = useSelect(function (select) {
                var store = select(STORE_NAME);
                return {
                    selectedRating: ratingId ? store.getRating(ratingId) : null,
                    allRatingsById: store.getRatingsById ? store.getRatingsById() : {},
                    parentRatings: store.getParentRatings(),
                    mirrorableRatings: store.getMirrorableRatings(),
                    isLoadingMirrorable: store.isLoadingMirrorable(),
                    searchResults: store.getSearchResults(),
                    isSearching: store.isSearching(),
                    isLoadingParents: store.isLoadingParents(),
                    storeError: store.getLastError ? store.getLastError() : null,
                    parentMirrors: ratingId ? store.getMirrorsForRating(ratingId) : null,
                    parentMirrorsLoading: ratingId ? store.isLoadingMirrors(ratingId) : false
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

            // Combined error state (local UI errors + store-level errors)
            const combinedError = error || storeError;

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
                clearError();
                if (lastFailedAction) {
                    lastFailedAction();
                    setLastFailedAction(null);
                }
            }

            function dismissError() {
                setError(null);
                clearError();
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
                fetchMirrorableRatings();
                if (ratingId) {
                    fetchRating(ratingId);
                    fetchChildRatings(ratingId);
                    fetchMirrorsForRating(ratingId);
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
                    fetchMirrorsForRating(ratingId);
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

            // ---- Fetch mirrors for each child rating ----
            useEffect(function () {
                if (childRatings.length > 0) {
                    childRatings.forEach(function (child) {
                        fetchMirrorsForRating(child.id);
                    });
                }
            }, [childRatings.length]);

            // ---- Get mirrors for child ratings from store ----
            const childMirrorsMap = useSelect(function (select) {
                var store = select(STORE_NAME);
                var map = {};
                childRatings.forEach(function (child) {
                    map[child.id] = store.getMirrorsForRating(child.id);
                });
                return map;
            }, [childRatings]);

            // ---- Auto-populate subRatings from child list when empty ----
            useEffect(function () {
                if (!ratingId || childRatings.length === 0) return;
                var currentSubRatings = subRatings || [];

                if (currentSubRatings.length === 0) {
                    // First time — seed from all children
                    var seeded = childRatings.map(function (child) {
                        return { id: parseInt(child.id, 10), mirrorId: 0, visible: true };
                    });
                    setAttributes({ subRatings: seeded });
                } else {
                    // Merge — add any new children not yet in subRatings
                    var existingIds = {};
                    currentSubRatings.forEach(function (sr) { existingIds[sr.id] = true; });
                    var newEntries = [];
                    childRatings.forEach(function (child) {
                        var cid = parseInt(child.id, 10);
                        if (!existingIds[cid]) {
                            newEntries.push({ id: cid, mirrorId: 0, visible: true });
                        }
                    });
                    if (newEntries.length > 0) {
                        setAttributes({ subRatings: currentSubRatings.concat(newEntries) });
                    }
                }
            }, [ratingId, childRatings.length]);

            // ---- SubRatings helpers ----
            function updateSubRating(subId, updates) {
                var updated = (subRatings || []).map(function (sr) {
                    if (sr.id === subId) {
                        var merged = {};
                        for (var k in sr) merged[k] = sr[k];
                        for (var k in updates) merged[k] = updates[k];
                        return merged;
                    }
                    return sr;
                });
                setAttributes({ subRatings: updated });
            }

            function moveSubRating(fromIndex, toIndex) {
                var arr = (subRatings || []).slice();
                if (fromIndex < 0 || fromIndex >= arr.length || toIndex < 0 || toIndex >= arr.length) return;
                var item = arr.splice(fromIndex, 1)[0];
                arr.splice(toIndex, 0, item);
                setAttributes({ subRatings: arr });
            }

            function resetSubRatings() {
                setAttributes({ subRatings: [] });
            }

            // ---- Drag handlers for sub-rating reorder ----
            function handleDragStart(e, index) {
                setDragIndex(index);
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(index));
            }

            function handleDragOver(e, index) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                setDragOverIndex(index);
            }

            function handleDragEnd() {
                if (dragIndex !== null && dragOverIndex !== null && dragIndex !== dragOverIndex) {
                    moveSubRating(dragIndex, dragOverIndex);
                }
                setDragIndex(null);
                setDragOverIndex(null);
            }

            function handleDrop(e) {
                e.preventDefault();
                handleDragEnd();
            }

            // ---- Ordered child ratings for preview (respects subRatings order + visibility) ----
            const orderedVisibleChildren = useMemo(function () {
                var currentSubRatings = subRatings || [];
                if (currentSubRatings.length === 0) return childRatings;

                var result = [];
                currentSubRatings.forEach(function (sr) {
                    if (!sr.visible) return;
                    var displayId = sr.mirrorId || sr.id;
                    var rating = allRatingsById[displayId] || allRatingsById[sr.id];
                    if (rating) {
                        result.push(rating);
                    }
                });
                return result;
            }, [subRatings, childRatings, allRatingsById]);

            // ---- Parent display rating (mirror or original) ----
            const parentDisplayRating = useMemo(function () {
                if (!selectedRating) return null;
                if (mirrorId && allRatingsById[mirrorId]) {
                    return allRatingsById[mirrorId];
                }
                return selectedRating;
            }, [selectedRating, mirrorId, allRatingsById]);

            // Fetch mirror rating data if mirrorId is set
            useEffect(function () {
                if (mirrorId) {
                    fetchRating(mirrorId);
                }
            }, [mirrorId]);

            // ---- CRUD helpers ----
            function createNewParentRating() {
                if (!newParentName.trim() || creating) return;

                // Mirror creation mode
                if (newRatingIsMirror) {
                    if (!newMirrorSourceId) return;
                    setCreating(true);
                    createRating({ name: newParentName, mirror_of: newMirrorSourceId })
                        .then(function (data) {
                            invalidateMirrorsCache(newMirrorSourceId);
                            fetchMirrorsForRating(newMirrorSourceId);
                            setNewParentName('');
                            setNewRatingIsMirror(false);
                            setNewMirrorSourceId(0);
                            setIsCreateModalOpen(false);
                            setCreating(false);
                            setError(null);
                        })
                        .catch(function (err) {
                            setCreating(false);
                            handleApiError(err, createNewParentRating);
                        });
                    return;
                }

                // Normal parent creation
                setCreating(true);
                createRating({ name: newParentName, display_only: newParentDisplayOnly })
                    .then(function (data) {
                        setAttributes({ ratingId: parseInt(data.id, 10), mirrorId: 0, subRatings: [] });
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
                        // Also remove from subRatings attribute
                        var updated = (subRatings || []).filter(function (sr) { return sr.id !== parseInt(childId, 10); });
                        setAttributes({ subRatings: updated });
                        setError(null);
                    })
                    .catch(function (err) { handleApiError(err, retry); });
            }

            // ---- Mirror CRUD helpers ----
            function createMirrorForParent() {
                if (!newMirrorName.trim() || creatingMirror || !selectedRating) return;
                setCreatingMirror(true);
                var sourceId = parseInt(selectedRating.id, 10);
                createRating({ name: newMirrorName, mirror_of: sourceId })
                    .then(function () {
                        invalidateMirrorsCache(sourceId);
                        fetchMirrorsForRating(sourceId);
                        setNewMirrorName('');
                        setCreatingMirror(false);
                        setError(null);
                    })
                    .catch(function (err) {
                        setCreatingMirror(false);
                        handleApiError(err, createMirrorForParent);
                    });
            }

            function deleteMirror(mirrorIdToDelete, sourceId) {
                if (!confirm(__('Are you sure you want to delete this mirror?', 'shuriken-reviews'))) return;
                var retry = function () { deleteMirror(mirrorIdToDelete, sourceId); };
                deleteRating(mirrorIdToDelete)
                    .then(function () {
                        invalidateMirrorsCache(sourceId);
                        fetchMirrorsForRating(sourceId);
                        // Reset parent mirror attribute if it was the deleted one
                        if (parseInt(mirrorIdToDelete, 10) === mirrorId) {
                            setAttributes({ mirrorId: 0 });
                        }
                        // Reset any sub-rating mirrors that referenced the deleted mirror
                        var updatedSR = (subRatings || []).map(function (sr) {
                            if (sr.mirrorId === parseInt(mirrorIdToDelete, 10)) {
                                return { id: sr.id, mirrorId: 0, visible: sr.visible };
                            }
                            return sr;
                        });
                        setAttributes({ subRatings: updatedSR });
                        setError(null);
                    })
                    .catch(function (err) { handleApiError(err, retry); });
            }

            function createMirrorForChild(childId) {
                var name = (newChildMirrorNames[childId] || '').trim();
                if (!name || creatingChildMirrorId) return;
                setCreatingChildMirrorId(parseInt(childId, 10));
                createRating({ name: name, mirror_of: parseInt(childId, 10) })
                    .then(function () {
                        invalidateMirrorsCache(parseInt(childId, 10));
                        fetchMirrorsForRating(parseInt(childId, 10));
                        setNewChildMirrorNames(function (prev) {
                            var next = {};
                            for (var k in prev) next[k] = prev[k];
                            delete next[childId];
                            return next;
                        });
                        setCreatingChildMirrorId(null);
                        setError(null);
                    })
                    .catch(function (err) {
                        setCreatingChildMirrorId(null);
                        handleApiError(err, function () { createMirrorForChild(childId); });
                    });
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

            // ---- Parent mirror options ----
            var parentMirrorOptions = useMemo(function () {
                var opts = [{ label: __('None (use original)', 'shuriken-reviews'), value: '0' }];
                if (Array.isArray(parentMirrors)) {
                    parentMirrors.forEach(function (m) {
                        opts.push({ label: m.name + ' (ID: ' + m.id + ')', value: String(m.id) });
                    });
                }
                return opts;
            }, [parentMirrors]);

            // ---- Mirrorable options (for Create Modal mirror source picker) ----
            var mirrorableOptions = useMemo(function () {
                var options = [];
                (Array.isArray(mirrorableRatings) ? mirrorableRatings : []).forEach(function (r) {
                    if (r && r.id) {
                        var label = r.name + ' (ID: ' + r.id + ')';
                        if (r.parent_id) label = '  ↳ ' + label;
                        options.push({ label: label, value: String(r.id) });
                    }
                });
                return options;
            }, [mirrorableRatings]);

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
                                        var newId = value ? parseInt(value, 10) : 0;
                                        setAttributes({ ratingId: newId, mirrorId: 0, subRatings: [] });
                                        if (newId) {
                                            fetchRating(newId);
                                            fetchMirrorsForRating(newId);
                                        }
                                    },
                                    onFilterValueChange: handleSearchChange,
                                    placeholder: isSearching
                                        ? __('Searching...', 'shuriken-reviews')
                                        : __('Search parent ratings...', 'shuriken-reviews')
                                }),

                                // Parent mirror selector
                                ratingId && selectedRating && wp.element.createElement(SelectControl, {
                                    label: __('Parent Mirror', 'shuriken-reviews'),
                                    value: String(mirrorId || 0),
                                    options: parentMirrorOptions,
                                    onChange: function (value) {
                                        var newMirrorId = parseInt(value, 10) || 0;
                                        setAttributes({ mirrorId: newMirrorId });
                                    },
                                    help: parentMirrorsLoading
                                        ? __('Loading mirrors...', 'shuriken-reviews')
                                        : (Array.isArray(parentMirrors) && parentMirrors.length === 0)
                                            ? __('No mirrors exist for this rating.', 'shuriken-reviews')
                                            : __('Display a mirror\'s name instead of the original parent.', 'shuriken-reviews')
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

                    // Panel 2 — Sub-Ratings Visibility & Order
                    ratingId && selectedRating && (subRatings || []).length > 0 && wp.element.createElement(
                        PanelBody,
                        { title: __('Sub-Ratings Display', 'shuriken-reviews'), initialOpen: false },
                        wp.element.createElement('p', { style: { fontSize: '12px', color: '#666', marginTop: 0 } },
                            __('Drag to reorder, toggle visibility, and optionally pick a mirror for each sub-rating.', 'shuriken-reviews')
                        ),
                        (subRatings || []).map(function (sr, index) {
                            var childRating = allRatingsById[sr.id];
                            var childName = childRating ? childRating.name : __('Loading...', 'shuriken-reviews');
                            var mirrors = childMirrorsMap[sr.id];
                            var hasMirrors = Array.isArray(mirrors) && mirrors.length > 0;
                            var isDragging = dragIndex === index;
                            var isDragOver = dragOverIndex === index;

                            return wp.element.createElement(
                                'div',
                                {
                                    key: sr.id,
                                    className: 'shuriken-sub-rating-row' + (isDragging ? ' is-dragging' : '') + (isDragOver ? ' is-drag-over' : '') + (!sr.visible ? ' is-hidden' : ''),
                                    draggable: true,
                                    onDragStart: function (e) { handleDragStart(e, index); },
                                    onDragOver: function (e) { handleDragOver(e, index); },
                                    onDragEnd: handleDragEnd,
                                    onDrop: handleDrop
                                },
                                wp.element.createElement(
                                    'div',
                                    { className: 'shuriken-sub-rating-row-header' },
                                    wp.element.createElement(
                                        'span',
                                        { className: 'shuriken-drag-handle', title: __('Drag to reorder', 'shuriken-reviews') },
                                        wp.element.createElement(Icon, { icon: 'menu' })
                                    ),
                                    wp.element.createElement('span', { className: 'shuriken-sub-rating-name' }, childName),
                                    wp.element.createElement(Button, {
                                        icon: sr.visible ? 'visibility' : 'hidden',
                                        label: sr.visible ? __('Hide', 'shuriken-reviews') : __('Show', 'shuriken-reviews'),
                                        onClick: function () { updateSubRating(sr.id, { visible: !sr.visible }); },
                                        className: 'shuriken-visibility-toggle' + (sr.visible ? '' : ' is-hidden-icon')
                                    })
                                ),
                                sr.visible && hasMirrors && wp.element.createElement(SelectControl, {
                                    value: String(sr.mirrorId || 0),
                                    options: [{ label: __('Original', 'shuriken-reviews'), value: '0' }].concat(
                                        mirrors.map(function (m) {
                                            return { label: m.name + ' (ID: ' + m.id + ')', value: String(m.id) };
                                        })
                                    ),
                                    onChange: function (value) { updateSubRating(sr.id, { mirrorId: parseInt(value, 10) || 0 }); },
                                    className: 'shuriken-sub-mirror-select'
                                })
                            );
                        }),
                        wp.element.createElement(
                            'div',
                            { style: { marginTop: '12px', display: 'flex', justifyContent: 'flex-end' } },
                            wp.element.createElement(Button, {
                                variant: 'tertiary',
                                isDestructive: true,
                                onClick: resetSubRatings,
                                style: { fontSize: '12px' }
                            }, __('Reset to Defaults', 'shuriken-reviews'))
                        )
                    ),

                    // Panel 3 — Layout
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

                    // Panel 4 — Colors (simple: accent + star)
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
                        title: newRatingIsMirror
                            ? __('Create New Mirror', 'shuriken-reviews')
                            : __('Create New Parent Rating', 'shuriken-reviews'),
                        onRequestClose: function () {
                            setIsCreateModalOpen(false);
                            setNewParentName('');
                            setNewParentDisplayOnly(true);
                            setNewRatingIsMirror(false);
                            setNewMirrorSourceId(0);
                        },
                        style: { width: '500px' }
                    },
                    wp.element.createElement(CheckboxControl, {
                        label: __('Create as Mirror of Existing Rating', 'shuriken-reviews'),
                        checked: newRatingIsMirror,
                        onChange: function (val) {
                            setNewRatingIsMirror(val);
                            if (!val) setNewMirrorSourceId(0);
                        },
                        help: newRatingIsMirror
                            ? __('The new rating will share vote data with the source rating.', 'shuriken-reviews')
                            : __('Enable to create a mirror instead of an independent parent.', 'shuriken-reviews')
                    }),
                    newRatingIsMirror && wp.element.createElement(ComboboxControl, {
                        label: __('Source Rating', 'shuriken-reviews'),
                        value: newMirrorSourceId ? String(newMirrorSourceId) : '',
                        options: mirrorableOptions,
                        onChange: function (value) {
                            setNewMirrorSourceId(value ? parseInt(value, 10) : 0);
                        },
                        onFilterValueChange: function () {},
                        placeholder: isLoadingMirrorable
                            ? __('Loading...', 'shuriken-reviews')
                            : __('Search ratings...', 'shuriken-reviews')
                    }),
                    wp.element.createElement(TextControl, {
                        label: newRatingIsMirror
                            ? __('Mirror Name', 'shuriken-reviews')
                            : __('Parent Rating Name', 'shuriken-reviews'),
                        value: newParentName,
                        onChange: setNewParentName,
                        onKeyDown: handleCreateKeyDown,
                        placeholder: newRatingIsMirror
                            ? __('e.g., Product Quality (Page 2)', 'shuriken-reviews')
                            : __('e.g., Overall Product Quality', 'shuriken-reviews'),
                        help: newRatingIsMirror
                            ? __('A display name for this mirror.', 'shuriken-reviews')
                            : __('This will be the main rating that groups sub-ratings.', 'shuriken-reviews')
                    }),
                    !newRatingIsMirror && wp.element.createElement(CheckboxControl, {
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
                                setNewRatingIsMirror(false);
                                setNewMirrorSourceId(0);
                            }
                        }, __('Cancel', 'shuriken-reviews')),
                        wp.element.createElement(Button, {
                            variant: 'primary',
                            onClick: createNewParentRating,
                            isBusy: creating,
                            disabled: creating || !newParentName.trim() || (newRatingIsMirror && !newMirrorSourceId)
                        }, newRatingIsMirror
                            ? __('Create Mirror', 'shuriken-reviews')
                            : __('Create', 'shuriken-reviews'))
                    )
                ),

                // --- Edit Modal ---
                isEditModalOpen && wp.element.createElement(
                    Modal,
                    {
                        title: __('Edit Parent Rating', 'shuriken-reviews'),
                        onRequestClose: function () {
                            setIsEditModalOpen(false);
                            setNewMirrorName('');
                        },
                        style: { width: '550px' }
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
                            onClick: function () {
                                setIsEditModalOpen(false);
                                setNewMirrorName('');
                            }
                        }, __('Cancel', 'shuriken-reviews')),
                        wp.element.createElement(Button, {
                            variant: 'primary',
                            onClick: updateParentRating,
                            isBusy: updating,
                            disabled: updating || !editParentName.trim()
                        }, __('Update', 'shuriken-reviews'))
                    ),

                    // --- Mirrors management section ---
                    Divider && wp.element.createElement(Divider, null),
                    wp.element.createElement('h4', { style: { fontSize: '14px', marginBottom: '12px', marginTop: '8px' } },
                        __('Mirrors', 'shuriken-reviews') +
                        (Array.isArray(parentMirrors) ? ' (' + parentMirrors.length + ')' : '')
                    ),
                    parentMirrorsLoading && wp.element.createElement(Spinner, null),
                    Array.isArray(parentMirrors) && parentMirrors.length === 0 && !parentMirrorsLoading && wp.element.createElement(
                        'p',
                        { style: { color: '#666', fontStyle: 'italic', fontSize: '13px', margin: '0 0 12px 0' } },
                        __('No mirrors yet. Create one below.', 'shuriken-reviews')
                    ),
                    Array.isArray(parentMirrors) && parentMirrors.map(function (m) {
                        return wp.element.createElement(
                            'div',
                            {
                                key: m.id,
                                style: {
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    padding: '8px 12px',
                                    marginBottom: '8px',
                                    border: '1px solid #ddd',
                                    borderRadius: '4px',
                                    backgroundColor: '#f9f9f9'
                                }
                            },
                            wp.element.createElement('span', { style: { fontSize: '13px' } }, m.name + ' (ID: ' + m.id + ')'),
                            wp.element.createElement(Button, {
                                variant: 'tertiary',
                                isDestructive: true,
                                onClick: function () { deleteMirror(m.id, parseInt(selectedRating.id, 10)); },
                                style: { padding: '4px 8px', fontSize: '12px' }
                            }, __('Delete', 'shuriken-reviews'))
                        );
                    }),
                    wp.element.createElement(
                        'div',
                        { style: { display: 'flex', gap: '8px', alignItems: 'flex-end', marginTop: '8px' } },
                        wp.element.createElement(
                            'div',
                            { style: { flex: 1 } },
                            wp.element.createElement(TextControl, {
                                label: __('New Mirror Name', 'shuriken-reviews'),
                                value: newMirrorName,
                                onChange: setNewMirrorName,
                                onKeyDown: function (e) {
                                    if (e.key === 'Enter') { e.preventDefault(); createMirrorForParent(); }
                                },
                                placeholder: __('e.g., Mirror for Page X', 'shuriken-reviews'),
                                style: { marginBottom: 0 }
                            })
                        ),
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            onClick: createMirrorForParent,
                            isBusy: creatingMirror,
                            disabled: creatingMirror || !newMirrorName.trim(),
                            style: { marginBottom: '8px' }
                        }, __('Create Mirror', 'shuriken-reviews'))
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
                            setNewChildMirrorNames({});
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
                            ),
                            // --- Mirrors for this child ---
                            (function () {
                                var cMirrors = childMirrorsMap[child.id];
                                var cHasMirrors = Array.isArray(cMirrors);
                                var cMirrorName = newChildMirrorNames[child.id] || '';
                                return wp.element.createElement(
                                    'div',
                                    { style: { marginTop: '12px', paddingTop: '12px', borderTop: '1px solid #eee' } },
                                    wp.element.createElement('h5', { style: { fontSize: '12px', fontWeight: '600', margin: '0 0 8px 0', color: '#555' } },
                                        __('Mirrors', 'shuriken-reviews') + (cHasMirrors ? ' (' + cMirrors.length + ')' : '')
                                    ),
                                    !cHasMirrors && wp.element.createElement(
                                        'p',
                                        { style: { color: '#999', fontSize: '12px', fontStyle: 'italic', margin: 0 } },
                                        __('Loading...', 'shuriken-reviews')
                                    ),
                                    cHasMirrors && cMirrors.length === 0 && wp.element.createElement(
                                        'p',
                                        { style: { color: '#999', fontSize: '12px', fontStyle: 'italic', margin: '0 0 8px 0' } },
                                        __('No mirrors.', 'shuriken-reviews')
                                    ),
                                    cHasMirrors && cMirrors.map(function (cm) {
                                        return wp.element.createElement(
                                            'div',
                                            {
                                                key: cm.id,
                                                style: {
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'space-between',
                                                    padding: '4px 8px',
                                                    marginBottom: '4px',
                                                    backgroundColor: '#f0f0f0',
                                                    borderRadius: '3px',
                                                    fontSize: '12px'
                                                }
                                            },
                                            wp.element.createElement('span', null, cm.name + ' (#' + cm.id + ')'),
                                            wp.element.createElement(Button, {
                                                variant: 'tertiary',
                                                isDestructive: true,
                                                onClick: function () { deleteMirror(cm.id, parseInt(child.id, 10)); },
                                                style: { padding: '2px 6px', fontSize: '11px', minHeight: 'auto' }
                                            }, __('Delete', 'shuriken-reviews'))
                                        );
                                    }),
                                    wp.element.createElement(
                                        'div',
                                        { style: { display: 'flex', gap: '6px', alignItems: 'flex-end', marginTop: '6px' } },
                                        wp.element.createElement(
                                            'div',
                                            { style: { flex: 1 } },
                                            wp.element.createElement(TextControl, {
                                                value: cMirrorName,
                                                onChange: function (value) {
                                                    setNewChildMirrorNames(function (prev) {
                                                        var next = {};
                                                        for (var k in prev) next[k] = prev[k];
                                                        next[child.id] = value;
                                                        return next;
                                                    });
                                                },
                                                onKeyDown: function (e) {
                                                    if (e.key === 'Enter') { e.preventDefault(); createMirrorForChild(child.id); }
                                                },
                                                placeholder: __('Mirror name...', 'shuriken-reviews'),
                                                style: { marginBottom: 0 }
                                            })
                                        ),
                                        wp.element.createElement(Button, {
                                            variant: 'secondary',
                                            onClick: function () { createMirrorForChild(child.id); },
                                            isBusy: creatingChildMirrorId === parseInt(child.id, 10),
                                            disabled: creatingChildMirrorId !== null || !cMirrorName.trim(),
                                            isSmall: true,
                                            style: { marginBottom: '8px' }
                                        }, __('Add', 'shuriken-reviews'))
                                    )
                                );
                            })()
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
                                setNewChildMirrorNames({});
                            },
                            disabled: savingChildren
                        }, __('Close', 'shuriken-reviews'))
                    )
                ),

                // --- Block Preview ---
                wp.element.createElement(
                    'div',
                    blockProps,
                    combinedError && wp.element.createElement(
                        Notice,
                        {
                            status: 'error',
                            onRemove: dismissError,
                            isDismissible: true,
                            actions: lastFailedAction ? [{ label: __('Retry', 'shuriken-reviews'), onClick: retryLastAction }] : []
                        },
                        combinedError
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
                                        { className: 'shuriken-rating parent-rating' + (parentDisplayRating.display_only ? ' display-only' : '') },
                                        wp.element.createElement(
                                            'div',
                                            { className: 'shuriken-rating-wrapper' },
                                            wp.element.createElement(
                                                titleTag,
                                                { className: 'rating-title' },
                                                parentDisplayRating.name
                                            ),
                                            wp.element.createElement(
                                                'div',
                                                { className: 'stars display-only-stars' },
                                                [1, 2, 3, 4, 5].map(function (i) {
                                                    var isActive = i <= calculateAverage(parentDisplayRating);
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
                                                __('Average:', 'shuriken-reviews') + ' ' + calculateAverage(parentDisplayRating) + '/5 (' + (parentDisplayRating.total_votes || 0) + ' ' + __('votes', 'shuriken-reviews') + ')'
                                            )
                                        )
                                    ),
                                    // Child ratings — using ordered visible children
                                    orderedVisibleChildren.length > 0 && wp.element.createElement(
                                        'div',
                                        { className: 'shuriken-child-ratings' },
                                        orderedVisibleChildren.map(function (child) {
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
