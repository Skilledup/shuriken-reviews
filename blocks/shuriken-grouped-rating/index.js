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

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Spinner, Notice } from '@wordpress/components';
import { createElement, Fragment, useState, useEffect, useMemo, useCallback, useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';

import CreateParentModal from './components/create-parent-modal';
import EditParentModal from './components/edit-parent-modal';
import ManageChildrenModal from './components/manage-children-modal';
import InspectorPanels from './components/inspector-panels';

// Local element shim so the existing createElement/Fragment call sites
// (wp.element.*) keep working after the ESM migration.
const wp = { element: { createElement, Fragment } };

// Store name constant (match single rating block's fallback pattern)
const STORE_NAME = window.SHURIKEN_STORE_NAME || 'shuriken-reviews';

// Initial shapes for the reducer-backed parent/child forms.
const PARENT_FORM_INIT = { name: '', displayOnly: true, type: 'stars', scale: 5, description: '' };
const CHILD_FORM_INIT = { name: '', effectType: 'positive', type: 'stars', scale: 5, displayOnly: false, description: '' };

/**
 * Reducer backing the create-parent, edit-parent and add-child forms.
 * Consolidates the many related fields into single state objects instead
 * of a dozen separate useState hooks.
 *
 * @param {Object} state  Current form state.
 * @param {Object} action { type: 'SET_FIELD'|'MERGE'|'RESET', ... }.
 * @return {Object} Next form state.
 */
function groupFormReducer(state, action) {
    switch (action.type) {
        case 'SET_FIELD':
            return { ...state, [action.field]: action.value };
        case 'MERGE':
            return { ...state, ...action.payload };
        case 'RESET':
            return action.payload;
        default:
            return state;
    }
}

const {
    useApiErrorHandling,
    useSearchHandler,
    titleTagOptions,
    renderRatingPreview,
    getScaleRange,
    iconShare2,
    iconTriangleAlert
} = window.ShurikenBlockHelpers;

let settings = {
        icon: iconShare2(24),
        edit: (props) => {
            const { attributes, setAttributes } = props;
            const {
                ratingId,
                titleTag,
                anchorTag,
                accentColor,
                starColor,
                childLayout,
                mirrorId,
                subRatings,
                postContext,
                hideTitle,
                gap,
                buttonColor
            } = attributes;

            // ---- Local UI state ----
            const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
            const [isEditModalOpen, setIsEditModalOpen] = useState(false);
            const [isManageChildrenModalOpen, setIsManageChildrenModalOpen] = useState(false);
            const [childrenToManage, setChildrenToManage] = useState([]);
            const [childrenLocalEdits, setChildrenLocalEdits] = useState({});
            // Create / edit parent + add-child form state (reducer-backed; see groupFormReducer)
            const [parentForm, dispatchParent] = useReducer(groupFormReducer, PARENT_FORM_INIT);
            const [editParentForm, dispatchEditParent] = useReducer(groupFormReducer, PARENT_FORM_INIT);
            const [childForm, dispatchChild] = useReducer(groupFormReducer, CHILD_FORM_INIT);
            const setParentField = (field, value) => dispatchParent({ type: 'SET_FIELD', field, value });
            const setEditParentField = (field, value) => dispatchEditParent({ type: 'SET_FIELD', field, value });
            const setChildField = (field, value) => dispatchChild({ type: 'SET_FIELD', field, value });
            const [creating, setCreating] = useState(false);
            const [updating, setUpdating] = useState(false);
            const [managingChildren, setManagingChildren] = useState(false);
            const [savingChildren, setSavingChildren] = useState(false);

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
            // Mirror rename state: { [mirrorId]: newName }
            const [editingMirrorNames, setEditingMirrorNames] = useState({});
            const [savingMirrorId, setSavingMirrorId] = useState(null);

            // Search state for AJAX dropdown
            const [searchTerm, setSearchTerm] = useState('');

            // Track whether initial batch of mirror data has loaded
            const [subMirrorDataReady, setSubMirrorDataReady] = useState(
                !(subRatings || []).some((sr) => sr.mirrorId)
            );

            // ---- Build CSS variables for accent/star colour ----
            const cssVars = {};
            if (accentColor) {
                cssVars['--shuriken-user-accent'] = accentColor;
            }
            if (starColor) {
                cssVars['--shuriken-user-star-color'] = starColor;
            }
            if (gap) {
                cssVars['--shuriken-gap'] = gap;
            }
            if (buttonColor) {
                cssVars['--shuriken-button-color'] = buttonColor;
            }

            const layoutClass = childLayout === 'list' ? ' is-layout-list' : '';

            // ---- Shared store helpers (must be declared before blockProps) ----
            const {
                fetchRating,
                fetchRatingsBatch,
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
            } = useSelect((select) => {
                const store = select(STORE_NAME);
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
            const previewClass = (ratingId && selectedRating)
                ? ' shuriken-rating-group'
                : '';

            const blockProps = useBlockProps({
                className: `shuriken-grouped-rating-block-editor${previewClass}${layoutClass}`,
                style: cssVars
            });

            const loading = (isLoadingParents && parentRatings.length === 0) ||
                // Wait for parent mirror data (lift once mirror fetch finishes)
                (mirrorId && !allRatingsById[mirrorId] && (parentMirrors === null || parentMirrorsLoading)) ||
                // Wait for sub-rating mirror data
                !subMirrorDataReady;

            // ---- Error handling lifecycle (shared hook) ----
            const {
                error,
                setError,
                lastFailedAction,
                handleApiError,
                dismissError,
                retryLastAction
            } = useApiErrorHandling(clearError);

            // Combined error state (local UI errors only — scoped per block)
            const combinedError = error;

            // ---- Search (shared — searches parents + mirrors together) ----
            const _debouncedSearch = useSearchHandler(searchRatings, 'parents_and_mirrors', 20);
            const handleSearchChange = useCallback((value) => {
                setSearchTerm(value);
                _debouncedSearch(value);
            }, [_debouncedSearch]);

            // ---- Data fetching ----
            useEffect(() => {
                fetchParentRatings();
                fetchMirrorableRatings();
                if (ratingId) {
                    fetchRating(ratingId);
                    fetchChildRatings(ratingId);
                    fetchMirrorsForRating(ratingId);
                }
            }, []);

            useEffect(() => {
                if (ratingId) {
                    fetchChildRatings(ratingId);
                    fetchMirrorsForRating(ratingId);
                }
            }, [ratingId]);

            // ---- Child ratings from store ----
            const childRatings = useMemo(() => {
                if (!ratingId) return [];
                const children = [];
                const ratingsObj = allRatingsById || {};
                Object.values(ratingsObj).forEach((r) => {
                    if (r && r.parent_id && parseInt(r.parent_id, 10) === parseInt(ratingId, 10)) {
                        children.push(r);
                    }
                });
                return children;
            }, [ratingId, allRatingsById]);

            // ---- Fetch mirrors for each child rating ----
            useEffect(() => {
                if (childRatings.length > 0) {
                    childRatings.forEach((child) => {
                        fetchMirrorsForRating(child.id);
                    });
                }
            }, [childRatings.length]);

            // ---- Get mirrors for child ratings from store ----
            const childMirrorsMap = useSelect((select) => {
                const store = select(STORE_NAME);
                const map = {};
                childRatings.forEach((child) => {
                    map[child.id] = store.getMirrorsForRating(child.id);
                });
                return map;
            }, [childRatings]);

            // ---- Auto-populate subRatings from child list when empty ----
            useEffect(() => {
                if (!ratingId || childRatings.length === 0) return;
                const currentSubRatings = subRatings || [];

                if (currentSubRatings.length === 0) {
                    // First time — seed from all children
                    const seeded = childRatings.map((child) => {
                        return { id: parseInt(child.id, 10), mirrorId: 0, visible: true };
                    });
                    setAttributes({ subRatings: seeded });
                } else {
                    // Merge — add any new children not yet in subRatings
                    const existingIds = {};
                    currentSubRatings.forEach((sr) => { existingIds[sr.id] = true; });
                    const newEntries = [];
                    childRatings.forEach((child) => {
                        const cid = parseInt(child.id, 10);
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
            const updateSubRating = (subId, updates) => {
                const updated = (subRatings || []).map((sr) => {
                    if (sr.id === subId) {
                        const merged = {};
                        for (const k in sr) merged[k] = sr[k];
                        for (const k in updates) merged[k] = updates[k];
                        return merged;
                    }
                    return sr;
                });
                setAttributes({ subRatings: updated });
            };

            const moveSubRating = (fromIndex, toIndex) => {
                const arr = (subRatings || []).slice();
                if (fromIndex < 0 || fromIndex >= arr.length || toIndex < 0 || toIndex >= arr.length) return;
                const item = arr.splice(fromIndex, 1)[0];
                arr.splice(toIndex, 0, item);
                setAttributes({ subRatings: arr });
            };

            const resetSubRatings = () => {
                setAttributes({ subRatings: [] });
            };

            // ---- Drag handlers for sub-rating reorder ----
            const handleDragStart = (e, index) => {
                setDragIndex(index);
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(index));
            };

            const handleDragOver = (e, index) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                setDragOverIndex(index);
            };

            const handleDragEnd = () => {
                if (dragIndex !== null && dragOverIndex !== null && dragIndex !== dragOverIndex) {
                    moveSubRating(dragIndex, dragOverIndex);
                }
                setDragIndex(null);
                setDragOverIndex(null);
            };

            const handleDrop = (e) => {
                e.preventDefault();
                handleDragEnd();
            };

            // ---- Ordered child ratings for preview (respects subRatings order + visibility) ----
            const orderedVisibleChildren = useMemo(() => {
                const currentSubRatings = subRatings || [];
                if (currentSubRatings.length === 0) return childRatings;

                const result = [];
                currentSubRatings.forEach((sr) => {
                    if (!sr.visible) return;
                    const displayId = sr.mirrorId || sr.id;
                    const rating = allRatingsById[displayId] || allRatingsById[sr.id];
                    if (rating) {
                        result.push(rating);
                    }
                });
                return result;
            }, [subRatings, childRatings, allRatingsById]);

            // ---- Parent display rating (mirror or original) ----
            const parentDisplayRating = useMemo(() => {
                if (!selectedRating) return null;
                if (mirrorId && allRatingsById[mirrorId]) {
                    return allRatingsById[mirrorId];
                }
                return selectedRating;
            }, [selectedRating, mirrorId, allRatingsById]);

            // Batch-fetch all mirror IDs in a single API call on mount.
            // This replaces N individual fetchRating calls with 1 batch request.
            useEffect(() => {
                const ids = [];
                if (mirrorId) ids.push(mirrorId);
                (subRatings || []).forEach((sr) => {
                    if (sr.mirrorId) ids.push(sr.mirrorId);
                });
                if (ids.length === 0) return;
                fetchRatingsBatch(ids).then(() => {
                    setSubMirrorDataReady(true);
                });
            }, []);

            // ---- CRUD helpers ----
            const createNewParentRating = () => {
                if (!parentForm.name.trim() || creating) return;

                // Mirror creation mode
                if (newRatingIsMirror) {
                    if (!newMirrorSourceId) return;
                    setCreating(true);
                    createRating({ name: parentForm.name, mirror_of: newMirrorSourceId })
                        .then((data) => {
                            invalidateMirrorsCache(newMirrorSourceId);
                            fetchMirrorsForRating(newMirrorSourceId);
                            setParentField('name', '');
                            setNewRatingIsMirror(false);
                            setNewMirrorSourceId(0);
                            setIsCreateModalOpen(false);
                            setCreating(false);
                            setError(null);
                        })
                        .catch((err) => {
                            setCreating(false);
                            handleApiError(err, createNewParentRating);
                        });
                    return;
                }

                // Normal parent creation
                setCreating(true);
                const scaleRange = getScaleRange(parentForm.type);
                createRating({
                    name: parentForm.name,
                    display_only: parentForm.displayOnly,
                    rating_type: parentForm.type,
                    scale: Math.max(scaleRange.min, Math.min(scaleRange.max, parentForm.scale)),
                    label_description: parentForm.description
                })
                    .then((data) => {
                        setAttributes({ ratingId: parseInt(data.id, 10), mirrorId: 0, subRatings: [] });
                        fetchParentRatings();
                        dispatchParent({ type: 'RESET', payload: PARENT_FORM_INIT });
                        setIsCreateModalOpen(false);
                        setCreating(false);
                        setError(null);
                    })
                    .catch((err) => {
                        setCreating(false);
                        handleApiError(err, createNewParentRating);
                    });
            };

            const openEditParentModal = () => {
                if (!selectedRating) return;
                const dv = selectedRating.display_only;
                dispatchEditParent({
                    type: 'MERGE',
                    payload: {
                        name: selectedRating.name || '',
                        displayOnly: dv === true || dv === 'true' || parseInt(dv, 10) === 1,
                        type: selectedRating.rating_type || 'stars',
                        scale: parseInt(selectedRating.scale, 10) || 5,
                        description: selectedRating.label_description || ''
                    }
                });
                setIsEditModalOpen(true);
            };

            const updateParentRating = () => {
                if (!editParentForm.name.trim() || updating || !selectedRating) return;
                setUpdating(true);
                const scaleRange = getScaleRange(editParentForm.type);
                updateRating(selectedRating.id, {
                    name: editParentForm.name,
                    display_only: editParentForm.displayOnly,
                    rating_type: editParentForm.type,
                    scale: Math.max(scaleRange.min, Math.min(scaleRange.max, editParentForm.scale)),
                    label_description: editParentForm.description
                })
                    .then(() => {
                        fetchParentRatings();
                        setIsEditModalOpen(false);
                        setUpdating(false);
                        setError(null);
                    })
                    .catch((err) => {
                        setUpdating(false);
                        handleApiError(err, updateParentRating);
                    });
            };

            const openManageChildrenModal = () => {
                if (!selectedRating) return;
                setChildrenToManage(childRatings.slice());
                setChildrenLocalEdits({});
                setIsManageChildrenModalOpen(true);
            };

            const addNewChild = () => {
                if (!childForm.name.trim() || managingChildren || !selectedRating) return;
                setManagingChildren(true);
                const scaleRange = getScaleRange(childForm.type);
                createRating({
                    name: childForm.name,
                    parent_id: parseInt(selectedRating.id, 10),
                    effect_type: childForm.effectType,
                    display_only: childForm.displayOnly,
                    rating_type: childForm.type,
                    scale: Math.max(scaleRange.min, Math.min(scaleRange.max, childForm.scale)),
                    label_description: childForm.description
                })
                    .then((data) => {
                        setChildrenToManage((prev) => { return [data].concat(prev); });
                        dispatchChild({ type: 'RESET', payload: CHILD_FORM_INIT });
                        setManagingChildren(false);
                        setError(null);
                    })
                    .catch((err) => {
                        setManagingChildren(false);
                        handleApiError(err, addNewChild);
                    });
            };

            const updateChildLocally = (childId, updates) => {
                setChildrenLocalEdits((prev) => {
                    const existing = prev[childId] || {};
                    const merged = {};
                    for (const k in existing) merged[k] = existing[k];
                    for (const k in updates) merged[k] = updates[k];
                    const next = {};
                    for (const k in prev) next[k] = prev[k];
                    next[childId] = merged;
                    return next;
                });
            };

            const applyChildrenEdits = () => {
                const editIds = Object.keys(childrenLocalEdits);
                if (editIds.length === 0) return;
                setSavingChildren(true);
                setError(null);
                Promise.all(editIds.map((id) => {
                    return updateRating(id, childrenLocalEdits[id]);
                }))
                    .then((results) => {
                        setChildrenToManage((prev) => {
                            const updated = prev.slice();
                            results.forEach((data) => {
                                const idx = updated.findIndex((r) => {
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
                    .catch((err) => {
                        setSavingChildren(false);
                        handleApiError(err, applyChildrenEdits);
                    });
            };

            const deleteChild = (childId) => {
                if (!confirm(__('Are you sure you want to delete this sub-rating?', 'shuriken-reviews'))) return;
                const retry = () => { deleteChild(childId); };
                deleteRating(childId)
                    .then(() => {
                        setChildrenToManage((prev) => {
                            return prev.filter((r) => parseInt(r.id, 10) !== parseInt(childId, 10));
                        });
                        // Also remove from subRatings attribute
                        const updated = (subRatings || []).filter((sr) => sr.id !== parseInt(childId, 10));
                        setAttributes({ subRatings: updated });
                        setError(null);
                    })
                    .catch((err) => { handleApiError(err, retry); });
            };

            // ---- Mirror CRUD helpers ----
            const createMirrorForParent = () => {
                if (!newMirrorName.trim() || creatingMirror || !selectedRating) return;
                setCreatingMirror(true);
                const sourceId = parseInt(selectedRating.id, 10);
                createRating({ name: newMirrorName, mirror_of: sourceId })
                    .then(() => {
                        invalidateMirrorsCache(sourceId);
                        fetchMirrorsForRating(sourceId);
                        setNewMirrorName('');
                        setCreatingMirror(false);
                        setError(null);
                    })
                    .catch((err) => {
                        setCreatingMirror(false);
                        handleApiError(err, createMirrorForParent);
                    });
            };

            const deleteMirror = (mirrorIdToDelete, sourceId) => {
                if (!confirm(__('Are you sure you want to delete this mirror?', 'shuriken-reviews'))) return;
                const retry = () => { deleteMirror(mirrorIdToDelete, sourceId); };
                deleteRating(mirrorIdToDelete)
                    .then(() => {
                        invalidateMirrorsCache(sourceId);
                        fetchMirrorsForRating(sourceId);
                        // Reset parent mirror attribute if it was the deleted one
                        if (parseInt(mirrorIdToDelete, 10) === mirrorId) {
                            setAttributes({ mirrorId: 0 });
                        }
                        // Reset any sub-rating mirrors that referenced the deleted mirror
                        const updatedSR = (subRatings || []).map((sr) => {
                            if (sr.mirrorId === parseInt(mirrorIdToDelete, 10)) {
                                return { id: sr.id, mirrorId: 0, visible: sr.visible };
                            }
                            return sr;
                        });
                        setAttributes({ subRatings: updatedSR });
                        setError(null);
                    })
                    .catch((err) => { handleApiError(err, retry); });
            };

            const createMirrorForChild = (childId) => {
                const name = (newChildMirrorNames[childId] || '').trim();
                if (!name || creatingChildMirrorId) return;
                setCreatingChildMirrorId(parseInt(childId, 10));
                createRating({ name: name, mirror_of: parseInt(childId, 10) })
                    .then(() => {
                        invalidateMirrorsCache(parseInt(childId, 10));
                        fetchMirrorsForRating(parseInt(childId, 10));
                        setNewChildMirrorNames((prev) => {
                            const next = {};
                            for (const k in prev) next[k] = prev[k];
                            delete next[childId];
                            return next;
                        });
                        setCreatingChildMirrorId(null);
                        setError(null);
                    })
                    .catch((err) => {
                        setCreatingChildMirrorId(null);
                        handleApiError(err, () => { createMirrorForChild(childId); });
                    });
            };

            const startEditMirror = (mId, currentName) => {
                setEditingMirrorNames((prev) => {
                    const next = {};
                    for (const k in prev) next[k] = prev[k];
                    next[mId] = currentName;
                    return next;
                });
            };

            const cancelEditMirror = (mId) => {
                setEditingMirrorNames((prev) => {
                    const next = {};
                    for (const k in prev) {
                        if (String(k) !== String(mId)) next[k] = prev[k];
                    }
                    return next;
                });
            };

            const saveMirrorName = (mId, sourceId) => {
                const newName = (editingMirrorNames[mId] || '').trim();
                if (!newName || savingMirrorId) return;
                setSavingMirrorId(parseInt(mId, 10));
                updateRating(mId, { name: newName })
                    .then(() => {
                        invalidateMirrorsCache(sourceId);
                        fetchMirrorsForRating(sourceId);
                        cancelEditMirror(mId);
                        setSavingMirrorId(null);
                        setError(null);
                    })
                    .catch((err) => {
                        setSavingMirrorId(null);
                        handleApiError(err, () => { saveMirrorName(mId, sourceId); });
                    });
            };

            const updateEditingMirrorName = (mId, value) => {
                setEditingMirrorNames((prev) => {
                    const next = {};
                    for (const k in prev) next[k] = prev[k];
                    next[mId] = value;
                    return next;
                });
            };

            // Keyboard handlers
            const handleCreateKeyDown = (e) => { if (e.key === 'Enter') { e.preventDefault(); createNewParentRating(); } };
            const handleEditKeyDown = (e) => { if (e.key === 'Enter') { e.preventDefault(); updateParentRating(); } };
            const handleChildKeyDown = (e) => { if (e.key === 'Enter') { e.preventDefault(); addNewChild(); } };

            // ---- Dropdown options ----
            // ---- Dropdown options: parents + mirrors of parents in one list ----
            const ratingOptions = useMemo(() => {
                const options = [];
                const seen = {};

                // Always include the currently selected parent + mirror combo
                if (selectedRating && selectedRating.id) {
                    seen[selectedRating.id] = true;
                    options.push({ label: `${selectedRating.name} (ID: ${selectedRating.id})`, value: String(selectedRating.id) });
                }
                if (mirrorId && allRatingsById[mirrorId]) {
                    const mRating = allRatingsById[mirrorId];
                    if (!seen[mRating.id]) {
                        seen[mRating.id] = true;
                        options.push({ label: `${mRating.name} — ${__('Mirror', 'shuriken-reviews')} (ID: ${mRating.id})`, value: String(mRating.id) });
                    }
                }

                // Add search results (comes back with parents + mirrors of parents)
                if (searchTerm && searchTerm.trim().length > 0) {
                    (Array.isArray(searchResults) ? searchResults : []).forEach((r) => {
                        if (r && r.id && !seen[r.id]) {
                            seen[r.id] = true;
                            let label = `${r.name} (ID: ${r.id})`;
                            if (r.mirror_of) {
                                label = `${r.name} — ${__('Mirror', 'shuriken-reviews')} (ID: ${r.id})`;
                            }
                            options.push({ label: label, value: String(r.id) });
                        }
                    });
                }
                return options;
            }, [searchResults, selectedRating, mirrorId, allRatingsById, searchTerm]);

            // ---- Mirrorable options (for Create Modal mirror source picker) ----
            const mirrorableOptions = useMemo(() => {
                const options = [];
                (Array.isArray(mirrorableRatings) ? mirrorableRatings : []).forEach((r) => {
                    if (r && r.id) {
                        let label = `${r.name} (ID: ${r.id})`;
                        if (r.parent_id) label = `  ↳ ${label}`;
                        options.push({ label: label, value: String(r.id) });
                    }
                });
                return options;
            }, [mirrorableRatings]);

            // ===================================================================
            // Render
            // ===================================================================

            // Shared context handed to the extracted modal components. Carries
            // every edit()-local value/handler the modals reference so they
            // stay decoupled from this closure.
            const modalCtx = {
                // Create-parent modal
                isCreateModalOpen, setIsCreateModalOpen,
                newRatingIsMirror, setNewRatingIsMirror,
                newMirrorSourceId, setNewMirrorSourceId,
                mirrorableOptions, isLoadingMirrorable,
                newParentName: parentForm.name, setNewParentName: (v) => setParentField('name', v), handleCreateKeyDown,
                newParentDescription: parentForm.description, setNewParentDescription: (v) => setParentField('description', v),
                newParentDisplayOnly: parentForm.displayOnly, setNewParentDisplayOnly: (v) => setParentField('displayOnly', v),
                newParentType: parentForm.type, setNewParentType: (v) => setParentField('type', v),
                newParentScale: parentForm.scale, setNewParentScale: (v) => setParentField('scale', v),
                createNewParentRating, creating,
                // Edit-parent modal
                isEditModalOpen, setIsEditModalOpen,
                editParentName: editParentForm.name, setEditParentName: (v) => setEditParentField('name', v), handleEditKeyDown,
                editParentDescription: editParentForm.description, setEditParentDescription: (v) => setEditParentField('description', v),
                editParentDisplayOnly: editParentForm.displayOnly, setEditParentDisplayOnly: (v) => setEditParentField('displayOnly', v),
                editParentType: editParentForm.type, setEditParentType: (v) => setEditParentField('type', v),
                editParentScale: editParentForm.scale, setEditParentScale: (v) => setEditParentField('scale', v),
                selectedRating, updateParentRating, updating,
                parentMirrors, parentMirrorsLoading,
                editingMirrorNames, setEditingMirrorNames,
                savingMirrorId, setSavingMirrorId,
                updateEditingMirrorName, saveMirrorName, cancelEditMirror,
                startEditMirror, deleteMirror,
                newMirrorName, setNewMirrorName, createMirrorForParent, creatingMirror,
                // Manage-children modal
                isManageChildrenModalOpen, setIsManageChildrenModalOpen,
                childrenLocalEdits, setChildrenLocalEdits,
                newChildName: childForm.name, setNewChildName: (v) => setChildField('name', v), handleChildKeyDown,
                newChildDescription: childForm.description, setNewChildDescription: (v) => setChildField('description', v),
                newChildEffectType: childForm.effectType, setNewChildEffectType: (v) => setChildField('effectType', v),
                newChildType: childForm.type, setNewChildType: (v) => setChildField('type', v),
                newChildScale: childForm.scale, setNewChildScale: (v) => setChildField('scale', v),
                newChildDisplayOnly: childForm.displayOnly, setNewChildDisplayOnly: (v) => setChildField('displayOnly', v),
                addNewChild, managingChildren,
                childrenToManage, deleteChild, updateChildLocally,
                childMirrorsMap, newChildMirrorNames, setNewChildMirrorNames,
                creatingChildMirrorId, createMirrorForChild,
                applyChildrenEdits, savingChildren
            };

            // Context handed to the extracted Inspector (sidebar) panels.
            const inspectorCtx = {
                loading, mirrorId, ratingId, ratingOptions, allRatingsById,
                setAttributes, fetchRating, fetchChildRatings, fetchMirrorsForRating,
                handleSearchChange, isSearching, selectedRating,
                openEditParentModal, openManageChildrenModal, setIsCreateModalOpen,
                titleTag, titleTagOptions, anchorTag, postContext, hideTitle,
                subRatings, childMirrorsMap, dragIndex, dragOverIndex,
                handleDragStart, handleDragOver, handleDragEnd, handleDrop,
                updateSubRating, resetSubRatings, childLayout, gap,
                accentColor, starColor, buttonColor, orderedVisibleChildren
            };

            return wp.element.createElement(
                wp.element.Fragment,
                null,

                // --- Inspector Controls ---
                InspectorPanels(inspectorCtx),

                // --- Create Modal ---
                CreateParentModal(modalCtx),

                // --- Edit Modal ---
                EditParentModal(modalCtx),

                // --- Manage Children Modal ---
                ManageChildrenModal(modalCtx),

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
                                iconShare2(40),
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
                                        { className: `shuriken-rating parent-rating${parentDisplayRating.display_only ? ' display-only' : ''}` },
                                        wp.element.createElement(
                                            'div',
                                            { className: 'shuriken-rating-wrapper' },
                                            !hideTitle && wp.element.createElement(
                                                titleTag,
                                                { className: 'rating-title' },
                                                parentDisplayRating.name
                                            ),
                                            !hideTitle && parentDisplayRating.label_description && wp.element.createElement('p', { className: 'rating-description' }, parentDisplayRating.label_description),
                                            renderRatingPreview(parentDisplayRating, wp.element.createElement)[0],
                                            renderRatingPreview(parentDisplayRating, wp.element.createElement)[1]
                                        )
                                    ),
                                    // Child ratings — using ordered visible children
                                    orderedVisibleChildren.length > 0 && wp.element.createElement(
                                        'div',
                                        { className: 'shuriken-child-ratings' },
                                        orderedVisibleChildren.map((child) => {
                                            return wp.element.createElement(
                                                'div',
                                                { key: child.id, className: 'shuriken-rating child-rating' },
                                                wp.element.createElement(
                                                    'div',
                                                    { className: 'shuriken-rating-wrapper' },
                                                    !hideTitle && wp.element.createElement(
                                                        'h4',
                                                        { className: 'rating-title' },
                                                        child.name
                                                    ),
                                                    !hideTitle && child.label_description && wp.element.createElement('p', { className: 'rating-description' }, child.label_description),
                                                    renderRatingPreview(child, wp.element.createElement)[0],
                                                    renderRatingPreview(child, wp.element.createElement)[1]
                                                )
                                            );
                                        })
                                    )
                                )
                                : wp.element.createElement(
                                    'div',
                                    { className: 'shuriken-grouped-rating-placeholder' },
                                    iconTriangleAlert(40),
                                    wp.element.createElement('p', null, __('Rating not found. It may have been deleted.', 'shuriken-reviews'))
                                )
                )
            );
        },

        save: () => {
            // Dynamic block — rendered on server
            return null;
        }
    };

    if (typeof window.wp?.hooks?.applyFilters === 'function') {
        settings = window.wp.hooks.applyFilters('shurikenBlockSettings_groupedRating', settings);
    }

    registerBlockType('shuriken-reviews/grouped-rating', settings);

