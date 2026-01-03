(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, Button, Spinner, Modal, ComboboxControl, SelectControl, CheckboxControl, Notice, __experimentalDivider: Divider } = wp.components;
    const { useState, useEffect, useMemo, useRef } = wp.element;
    const { __ } = wp.i18n;
    const apiFetch = wp.apiFetch;

    registerBlockType('shuriken-reviews/grouped-rating', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { ratingId, titleTag, anchorTag } = attributes;
            
            const [ratings, setRatings] = useState([]);
            const [parentRatings, setParentRatings] = useState([]);
            const [loading, setLoading] = useState(true);
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
            const hasFetched = useRef(false);

            const blockProps = useBlockProps({
                className: 'shuriken-grouped-rating-block-editor'
            });

            // Error handling helper
            function handleApiError(err, action) {
                console.error('Shuriken Reviews API Error:', err);
                
                var errorMessage = __('An unexpected error occurred.', 'shuriken-reviews');
                
                // Parse REST API error response
                if (err.message) {
                    errorMessage = err.message;
                } else if (err.data && err.data.message) {
                    errorMessage = err.data.message;
                } else if (typeof err === 'string') {
                    errorMessage = err;
                }
                
                // Map common error codes to user-friendly messages
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

            // Fetch available ratings
            function fetchRatings() {
                setLoading(true);
                setError(null);
                
                Promise.all([
                    apiFetch({ path: '/shuriken-reviews/v1/ratings', method: 'GET' }),
                    apiFetch({ path: '/shuriken-reviews/v1/ratings/parents', method: 'GET' })
                ])
                    .then(function (results) {
                        var ratingsData = results[0];
                        var parentsData = results[1];
                        
                        setRatings(Array.isArray(ratingsData) ? ratingsData : []);
                        setParentRatings(Array.isArray(parentsData) ? parentsData : []);
                        setLoading(false);
                    })
                    .catch(function (err) {
                        setRatings([]);
                        setParentRatings([]);
                        setLoading(false);
                        handleApiError(err, fetchRatings);
                    });
            }

            // Fetch available ratings only once
            useEffect(function () {
                if (hasFetched.current) {
                    return;
                }
                hasFetched.current = true;
                fetchRatings();
            }, []);

            // Find selected rating using useMemo for efficiency
            const selectedRating = useMemo(function () {
                if (!ratingId || ratings.length === 0) {
                    return null;
                }
                return ratings.find(function (r) {
                    return parseInt(r.id, 10) === parseInt(ratingId, 10);
                }) || null;
            }, [ratingId, ratings]);

            // Find child ratings
            const childRatings = useMemo(function () {
                if (!ratingId || ratings.length === 0) {
                    return [];
                }
                return ratings.filter(function (r) {
                    return parseInt(r.parent_id, 10) === parseInt(ratingId, 10);
                });
            }, [ratingId, ratings]);

            function createNewParentRating() {
                if (!newParentName.trim() || creating) {
                    return;
                }

                setCreating(true);
                
                var requestData = { 
                    name: newParentName,
                    display_only: newParentDisplayOnly
                };
                
                apiFetch({
                    path: '/shuriken-reviews/v1/ratings',
                    method: 'POST',
                    data: requestData
                })
                    .then(function (data) {
                        setRatings(function (prev) {
                            return [data].concat(prev);
                        });
                        setParentRatings(function (prev) {
                            return [data].concat(prev);
                        });
                        setAttributes({ ratingId: parseInt(data.id, 10) });
                        // Reset form
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
                if (!selectedRating) {
                    return;
                }
                setEditParentName(selectedRating.name || '');
                var displayOnlyValue = selectedRating.display_only;
                var isDisplayOnly = displayOnlyValue === true || displayOnlyValue === 'true' || parseInt(displayOnlyValue, 10) === 1;
                setEditParentDisplayOnly(isDisplayOnly);
                setIsEditModalOpen(true);
            }

            function updateParentRating() {
                if (!editParentName.trim() || updating || !selectedRating) {
                    return;
                }

                setUpdating(true);
                
                var requestData = {
                    name: editParentName,
                    display_only: editParentDisplayOnly
                };
                
                apiFetch({
                    path: '/shuriken-reviews/v1/ratings/' + selectedRating.id,
                    method: 'PUT',
                    data: requestData
                })
                    .then(function (data) {
                        setRatings(function (prev) {
                            return prev.map(function (r) {
                                return parseInt(r.id, 10) === parseInt(data.id, 10) ? data : r;
                            });
                        });
                        setParentRatings(function (prev) {
                            return prev.map(function (r) {
                                return parseInt(r.id, 10) === parseInt(data.id, 10) ? data : r;
                            });
                        });
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
                if (!selectedRating) {
                    return;
                }
                setChildrenToManage(childRatings.slice());
                setChildrenLocalEdits({});
                setIsManageChildrenModalOpen(true);
            }

            function addNewChild() {
                if (!newChildName.trim() || managingChildren || !selectedRating) {
                    return;
                }

                setManagingChildren(true);
                
                var requestData = {
                    name: newChildName,
                    parent_id: parseInt(selectedRating.id, 10),
                    effect_type: newChildEffectType,
                    display_only: false
                };
                
                apiFetch({
                    path: '/shuriken-reviews/v1/ratings',
                    method: 'POST',
                    data: requestData
                })
                    .then(function (data) {
                        setRatings(function (prev) {
                            return [data].concat(prev);
                        });
                        setChildrenToManage(function (prev) {
                            return [data].concat(prev);
                        });
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

            // Update child in local state (not saved to server yet)
            function updateChildLocally(childId, updates) {
                setChildrenLocalEdits(function (prev) {
                    var existing = prev[childId] || {};
                    var merged = {};
                    for (var key in existing) {
                        merged[key] = existing[key];
                    }
                    for (var key in updates) {
                        merged[key] = updates[key];
                    }
                    var newEdits = {};
                    for (var key in prev) {
                        newEdits[key] = prev[key];
                    }
                    newEdits[childId] = merged;
                    return newEdits;
                });
            }

            // Apply all pending child edits to server
            function applyChildrenEdits() {
                var editIds = Object.keys(childrenLocalEdits);
                if (editIds.length === 0) {
                    return;
                }

                setSavingChildren(true);
                setError(null);

                // Update all edited children in sequence
                var updatePromises = editIds.map(function (childId) {
                    return apiFetch({
                        path: '/shuriken-reviews/v1/ratings/' + childId,
                        method: 'PUT',
                        data: childrenLocalEdits[childId]
                    });
                });

                Promise.all(updatePromises)
                    .then(function (results) {
                        // Update main ratings list
                        setRatings(function (prev) {
                            var updated = prev.slice();
                            results.forEach(function (data) {
                                var idx = updated.findIndex(function (r) {
                                    return parseInt(r.id, 10) === parseInt(data.id, 10);
                                });
                                if (idx !== -1) {
                                    updated[idx] = data;
                                }
                            });
                            return updated;
                        });
                        // Update children list
                        setChildrenToManage(function (prev) {
                            var updated = prev.slice();
                            results.forEach(function (data) {
                                var idx = updated.findIndex(function (r) {
                                    return parseInt(r.id, 10) === parseInt(data.id, 10);
                                });
                                if (idx !== -1) {
                                    updated[idx] = data;
                                }
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
                if (!confirm(__('Are you sure you want to delete this sub-rating?', 'shuriken-reviews'))) {
                    return;
                }

                var retryAction = function() { deleteChild(childId); };

                apiFetch({
                    path: '/shuriken-reviews/v1/ratings/' + childId,
                    method: 'DELETE'
                })
                    .then(function () {
                        setRatings(function (prev) {
                            return prev.filter(function (r) {
                                return parseInt(r.id, 10) !== parseInt(childId, 10);
                            });
                        });
                        setChildrenToManage(function (prev) {
                            return prev.filter(function (r) {
                                return parseInt(r.id, 10) !== parseInt(childId, 10);
                            });
                        });
                        setError(null);
                    })
                    .catch(function (err) {
                        handleApiError(err, retryAction);
                    });
            }

            // Handle Enter key in the modals
            function handleCreateKeyDown(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    createNewParentRating();
                }
            }

            function handleEditKeyDown(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    updateParentRating();
                }
            }

            function handleChildKeyDown(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addNewChild();
                }
            }

            // Build parent rating options for ComboboxControl
            var ratingOptions = parentRatings.map(function (rating) {
                return {
                    label: rating.name + ' (ID: ' + rating.id + ')',
                    value: String(rating.id)
                };
            });

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
            function calculateAverage(rating) {
                var totalVotes = parseInt(rating.total_votes, 10) || 0;
                if (totalVotes > 0) {
                    return Math.round((parseInt(rating.total_rating, 10) / totalVotes) * 10) / 10;
                }
                return 0;
            }

            return wp.element.createElement(
                wp.element.Fragment,
                null,
                wp.element.createElement(
                    InspectorControls,
                    null,
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
                                    },
                                    onFilterValueChange: function () {},
                                    placeholder: __('Search parent ratings...', 'shuriken-reviews')
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
                            help: __('Optional anchor ID for linking to this rating group.', 'shuriken-reviews')
                        })
                    )
                ),
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
                isEditModalOpen && wp.element.createElement(
                    Modal,
                    {
                        title: __('Edit Parent Rating', 'shuriken-reviews'),
                        onRequestClose: function () { 
                            setIsEditModalOpen(false);
                        },
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
                            onClick: function () { 
                                setIsEditModalOpen(false);
                            }
                        }, __('Cancel', 'shuriken-reviews')),
                        wp.element.createElement(Button, {
                            variant: 'primary',
                            onClick: updateParentRating,
                            isBusy: updating,
                            disabled: updating || !editParentName.trim()
                        }, __('Update', 'shuriken-reviews'))
                    )
                ),
                isManageChildrenModalOpen && wp.element.createElement(
                    Modal,
                    {
                        title: __('Manage Sub-Ratings', 'shuriken-reviews'),
                        onRequestClose: function () {
                            var hasUnsaved = Object.keys(childrenLocalEdits).length > 0;
                            if (hasUnsaved && !confirm(__('You have unsaved changes. Close without saving?', 'shuriken-reviews'))) {
                                return;
                            }
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
                                    onChange: function (value) {
                                        updateChildLocally(child.id, { name: value });
                                    },
                                    style: { marginBottom: 0 }
                                }),
                                wp.element.createElement(SelectControl, {
                                    label: __('Effect Type', 'shuriken-reviews'),
                                    value: (childrenLocalEdits[child.id] && childrenLocalEdits[child.id].effect_type) || child.effect_type || 'positive',
                                    options: [
                                        { label: __('Positive', 'shuriken-reviews'), value: 'positive' },
                                        { label: __('Negative', 'shuriken-reviews'), value: 'negative' }
                                    ],
                                    onChange: function (value) {
                                        updateChildLocally(child.id, { effect_type: value });
                                    }
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
                                if (hasUnsaved && !confirm(__('You have unsaved changes. Close without saving?', 'shuriken-reviews'))) {
                                    return;
                                }
                                setIsManageChildrenModalOpen(false);
                                setNewChildName('');
                                setNewChildEffectType('positive');
                                setChildrenLocalEdits({});
                            },
                            disabled: savingChildren
                        }, __('Close', 'shuriken-reviews'))
                    )
                ),
                wp.element.createElement(
                    'div',
                    blockProps,
                    error && wp.element.createElement(
                        Notice,
                        {
                            status: 'error',
                            onRemove: dismissError,
                            isDismissible: true,
                            actions: lastFailedAction ? [
                                {
                                    label: __('Retry', 'shuriken-reviews'),
                                    onClick: retryLastAction
                                }
                            ] : []
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
                                    'div',
                                    { className: 'shuriken-rating-group' },
                                    wp.element.createElement(
                                        'div',
                                        { className: 'shuriken-rating-wrapper parent-rating' },
                                        wp.element.createElement(
                                            titleTag,
                                            { className: 'rating-title' },
                                            selectedRating.name
                                        ),
                                        wp.element.createElement(
                                            'div',
                                            { className: 'stars' },
                                            [1, 2, 3, 4, 5].map(function (i) {
                                                return wp.element.createElement(
                                                    'span',
                                                    {
                                                        key: i,
                                                        className: 'star' + (i <= calculateAverage(selectedRating) ? ' active' : '')
                                                    },
                                                    '★'
                                                );
                                            })
                                        ),
                                        wp.element.createElement(
                                            'div',
                                            { className: 'rating-stats' },
                                            __('Average:', 'shuriken-reviews') + ' ' + calculateAverage(selectedRating) + '/5 (' + (selectedRating.total_votes || 0) + ' ' + __('votes', 'shuriken-reviews') + ')'
                                        )
                                    ),
                                    childRatings.length > 0 && wp.element.createElement(
                                        'div',
                                        { className: 'shuriken-child-ratings' },
                                        childRatings.map(function(child) {
                                            return wp.element.createElement(
                                                'div',
                                                { key: child.id, className: 'shuriken-rating-wrapper child-rating' },
                                                wp.element.createElement(
                                                    'h4',
                                                    { className: 'rating-title' },
                                                    child.name
                                                ),
                                                wp.element.createElement(
                                                    'div',
                                                    { className: 'stars' },
                                                    [1, 2, 3, 4, 5].map(function (i) {
                                                        return wp.element.createElement(
                                                            'span',
                                                            {
                                                                key: i,
                                                                className: 'star' + (i <= calculateAverage(child) ? ' active' : '')
                                                            },
                                                            '★'
                                                        );
                                                    })
                                                ),
                                                wp.element.createElement(
                                                    'div',
                                                    { className: 'rating-stats' },
                                                    calculateAverage(child) + '/5'
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
            // Dynamic block - rendered on server
            return null;
        }
    });
})(window.wp);
