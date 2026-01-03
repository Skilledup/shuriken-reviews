(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, Button, Spinner, Modal, ComboboxControl, SelectControl, CheckboxControl, Notice } = wp.components;
    const { useState, useEffect, useMemo, useRef } = wp.element;
    const { __ } = wp.i18n;
    const apiFetch = wp.apiFetch;

    registerBlockType('shuriken-reviews/rating', {
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { ratingId, titleTag, anchorTag } = attributes;
            
            const [ratings, setRatings] = useState([]);
            const [loading, setLoading] = useState(true);
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
            const [parentRatings, setParentRatings] = useState([]);
            const [mirrorableRatings, setMirrorableRatings] = useState([]);
            const [creating, setCreating] = useState(false);
            const [updating, setUpdating] = useState(false);
            const [error, setError] = useState(null);
            const [lastFailedAction, setLastFailedAction] = useState(null);
            const hasFetched = useRef(false);

            const blockProps = useBlockProps({
                className: 'shuriken-rating-block-editor'
            });

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

            // Fetch ratings
            function fetchRatings() {
                setLoading(true);
                setError(null);
                
                Promise.all([
                    apiFetch({ path: '/shuriken-reviews/v1/ratings', method: 'GET' }),
                    apiFetch({ path: '/shuriken-reviews/v1/ratings/parents', method: 'GET' }),
                    apiFetch({ path: '/shuriken-reviews/v1/ratings/mirrorable', method: 'GET' })
                ])
                    .then(function (results) {
                        var ratingsData = results[0];
                        var parentsData = results[1];
                        var mirrorableData = results[2];
                        
                        setRatings(Array.isArray(ratingsData) ? ratingsData : []);
                        setParentRatings(Array.isArray(parentsData) ? parentsData : []);
                        setMirrorableRatings(Array.isArray(mirrorableData) ? mirrorableData : []);
                        setLoading(false);
                    })
                    .catch(function (err) {
                        setRatings([]);
                        setParentRatings([]);
                        setMirrorableRatings([]);
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

            function createNewRating() {
                if (!newRatingName.trim() || creating) {
                    return;
                }

                setCreating(true);
                
                var requestData = { name: newRatingName };
                
                // Mirror takes precedence over parent
                if (newRatingMirrorOf) {
                    requestData.mirror_of = parseInt(newRatingMirrorOf, 10);
                } else {
                    if (newRatingParentId) {
                        requestData.parent_id = parseInt(newRatingParentId, 10);
                        requestData.effect_type = newRatingEffectType;
                    }
                    requestData.display_only = newRatingDisplayOnly;
                }
                
                apiFetch({
                    path: '/shuriken-reviews/v1/ratings',
                    method: 'POST',
                    data: requestData
                })
                    .then(function (data) {
                        setRatings(function (prev) {
                            return [data].concat(prev);
                        });
                        // Also update parent/mirrorable lists if applicable
                        if (!data.parent_id && !data.mirror_of) {
                            setParentRatings(function (prev) {
                                return [data].concat(prev);
                            });
                        }
                        if (!data.mirror_of) {
                            setMirrorableRatings(function (prev) {
                                return [data].concat(prev);
                            });
                        }
                        setAttributes({ ratingId: parseInt(data.id, 10) });
                        // Reset form
                        setNewRatingName('');
                        setNewRatingMirrorOf('');
                        setNewRatingParentId('');
                        setNewRatingEffectType('positive');
                        setNewRatingDisplayOnly(false);
                        setIsModalOpen(false);
                        setCreating(false);
                        setError(null);
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
                // Normalize display_only (defensive coverage)
                var displayOnlyValue = selectedRating.display_only;
                var isDisplayOnly = displayOnlyValue === true || displayOnlyValue === 'true' || parseInt(displayOnlyValue, 10) === 1;
                setEditRatingDisplayOnly(isDisplayOnly);
                setIsEditModalOpen(true);
            }

            // Update existing rating
            function updateRating() {
                if (!editRatingName.trim() || updating || !selectedRating) {
                    return;
                }

                setUpdating(true);
                
                var requestData = { name: editRatingName };
                
                // Mirror takes precedence over parent
                if (editRatingMirrorOf) {
                    requestData.mirror_of = parseInt(editRatingMirrorOf, 10);
                    requestData.parent_id = 0; // Clear parent if mirror is set
                    requestData.display_only = false;
                } else {
                    requestData.mirror_of = 0; // Clear mirror
                    if (editRatingParentId) {
                        requestData.parent_id = parseInt(editRatingParentId, 10);
                        requestData.effect_type = editRatingEffectType;
                        requestData.display_only = false;
                    } else {
                        requestData.parent_id = 0;
                        requestData.display_only = editRatingDisplayOnly;
                    }
                }
                
                apiFetch({
                    path: '/shuriken-reviews/v1/ratings/' + selectedRating.id,
                    method: 'PUT',
                    data: requestData
                })
                    .then(function (data) {
                        // Update the rating in the local state
                        setRatings(function (prev) {
                            return prev.map(function (r) {
                                return parseInt(r.id, 10) === parseInt(data.id, 10) ? data : r;
                            });
                        });
                        // Update parent/mirrorable lists
                        if (!data.parent_id && !data.mirror_of) {
                            // Add to parents if not already there
                            setParentRatings(function (prev) {
                                var exists = prev.some(function (r) {
                                    return parseInt(r.id, 10) === parseInt(data.id, 10);
                                });
                                if (exists) {
                                    return prev.map(function (r) {
                                        return parseInt(r.id, 10) === parseInt(data.id, 10) ? data : r;
                                    });
                                }
                                return [data].concat(prev);
                            });
                        } else {
                            // Remove from parents if it's now a sub-rating or mirror
                            setParentRatings(function (prev) {
                                return prev.filter(function (r) {
                                    return parseInt(r.id, 10) !== parseInt(data.id, 10);
                                });
                            });
                        }
                        if (!data.mirror_of) {
                            // Update in mirrorable list
                            setMirrorableRatings(function (prev) {
                                var exists = prev.some(function (r) {
                                    return parseInt(r.id, 10) === parseInt(data.id, 10);
                                });
                                if (exists) {
                                    return prev.map(function (r) {
                                        return parseInt(r.id, 10) === parseInt(data.id, 10) ? data : r;
                                    });
                                }
                                return [data].concat(prev);
                            });
                        } else {
                            // Remove from mirrorable if it's now a mirror
                            setMirrorableRatings(function (prev) {
                                return prev.filter(function (r) {
                                    return parseInt(r.id, 10) !== parseInt(data.id, 10);
                                });
                            });
                        }
                        setIsEditModalOpen(false);
                        setUpdating(false);
                        setError(null);
                    })
                    .catch(function (err) {
                        setUpdating(false);
                        handleApiError(err, updateRating);
                    });
            }

            // Handle Enter key in the modal
            function handleKeyDown(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    createNewRating();
                }
            }

            // Handle Enter key in edit modal
            function handleEditKeyDown(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    updateRating();
                }
            }

            // Build rating options for ComboboxControl (searchable)
            var ratingOptions = ratings.map(function (rating) {
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
            var average = 0;
            var totalVotes = 0;
            if (selectedRating) {
                totalVotes = parseInt(selectedRating.total_votes, 10) || 0;
                if (totalVotes > 0) {
                    average = Math.round((parseInt(selectedRating.total_rating, 10) / totalVotes) * 10) / 10;
                }
            }

            return wp.element.createElement(
                wp.element.Fragment,
                null,
                wp.element.createElement(
                    InspectorControls,
                    null,
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Rating Settings', 'shuriken-reviews'), initialOpen: true },
                        loading
                            ? wp.element.createElement(Spinner, null)
                            : wp.element.createElement(
                                wp.element.Fragment,
                                null,
                                wp.element.createElement(ComboboxControl, {
                                    label: __('Select Rating', 'shuriken-reviews'),
                                    value: ratingId ? String(ratingId) : '',
                                    options: ratingOptions,
                                    onChange: function (value) {
                                        setAttributes({ ratingId: value ? parseInt(value, 10) : 0 });
                                    },
                                    onFilterValueChange: function () {},
                                    placeholder: __('Search ratings...', 'shuriken-reviews')
                                }),
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
                            help: __('Optional anchor ID for linking to this rating.', 'shuriken-reviews')
                        })
                    )
                ),
                isModalOpen && wp.element.createElement(
                    Modal,
                    {
                        title: __('Create New Rating', 'shuriken-reviews'),
                        onRequestClose: function () { 
                            setIsModalOpen(false);
                            // Reset form when closing
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
                        help: __('Enter a descriptive name for this rating. This will be displayed to users.', 'shuriken-reviews')
                    }),
                    wp.element.createElement(SelectControl, {
                        label: __('Mirror of', 'shuriken-reviews'),
                        value: newRatingMirrorOf,
                        options: [{ label: __('— Not a Mirror (Original Rating) —', 'shuriken-reviews'), value: '' }].concat(
                            mirrorableRatings.map(function (r) {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: function (value) {
                            setNewRatingMirrorOf(value);
                            // If mirror is selected, clear parent settings
                            if (value) {
                                setNewRatingParentId('');
                                setNewRatingDisplayOnly(false);
                            }
                        },
                        help: __('Select a rating to mirror. Mirrors share the same vote data but have different names/labels.', 'shuriken-reviews')
                    }),
                    !newRatingMirrorOf && wp.element.createElement(SelectControl, {
                        label: __('Parent Rating', 'shuriken-reviews'),
                        value: newRatingParentId,
                        options: [{ label: __('— None (Standalone Rating) —', 'shuriken-reviews'), value: '' }].concat(
                            parentRatings.map(function (r) {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: setNewRatingParentId,
                        help: __('Select a parent to make this a sub-rating. Sub-ratings contribute to their parent\'s score.', 'shuriken-reviews')
                    }),
                    !newRatingMirrorOf && newRatingParentId && wp.element.createElement(SelectControl, {
                        label: __('Effect on Parent', 'shuriken-reviews'),
                        value: newRatingEffectType,
                        options: [
                            { label: __('Positive — Votes add to parent rating', 'shuriken-reviews'), value: 'positive' },
                            { label: __('Negative — Votes subtract from parent rating', 'shuriken-reviews'), value: 'negative' }
                        ],
                        onChange: setNewRatingEffectType,
                        help: __('Positive: Higher votes improve parent score. Negative: Higher votes lower parent score (e.g., "Difficulty" or "Price").', 'shuriken-reviews')
                    }),
                    !newRatingMirrorOf && !newRatingParentId && wp.element.createElement(CheckboxControl, {
                        label: __('Display Only', 'shuriken-reviews'),
                        checked: newRatingDisplayOnly,
                        onChange: setNewRatingDisplayOnly,
                        help: __('Enable this for parent ratings where visitors should only vote via sub-ratings.', 'shuriken-reviews')
                    }),
                    wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', display: 'flex', gap: '8px', justifyContent: 'flex-end' } },
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            onClick: function () { 
                                setIsModalOpen(false);
                                // Reset form
                                setNewRatingName('');
                                setNewRatingMirrorOf('');
                                setNewRatingParentId('');
                                setNewRatingEffectType('positive');
                                setNewRatingDisplayOnly(false);
                            }
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
                        onRequestClose: function () { 
                            setIsEditModalOpen(false);
                        },
                        style: { width: '500px' }
                    },
                    wp.element.createElement(TextControl, {
                        label: __('Rating Name', 'shuriken-reviews'),
                        value: editRatingName,
                        onChange: setEditRatingName,
                        onKeyDown: handleEditKeyDown,
                        placeholder: __('Enter rating name...', 'shuriken-reviews'),
                        help: __('Enter a descriptive name for this rating. This will be displayed to users.', 'shuriken-reviews')
                    }),
                    wp.element.createElement(SelectControl, {
                        label: __('Mirror of', 'shuriken-reviews'),
                        value: editRatingMirrorOf,
                        options: [{ label: __('— Not a Mirror (Original Rating) —', 'shuriken-reviews'), value: '' }].concat(
                            mirrorableRatings.filter(function (r) {
                                // Exclude the current rating from mirrorable options
                                return parseInt(r.id, 10) !== parseInt(ratingId, 10);
                            }).map(function (r) {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: function (value) {
                            setEditRatingMirrorOf(value);
                            // If mirror is selected, clear parent settings
                            if (value) {
                                setEditRatingParentId('');
                                setEditRatingDisplayOnly(false);
                            }
                        },
                        help: __('Select a rating to mirror. Mirrors share the same vote data but have different names/labels.', 'shuriken-reviews')
                    }),
                    !editRatingMirrorOf && wp.element.createElement(SelectControl, {
                        label: __('Parent Rating', 'shuriken-reviews'),
                        value: editRatingParentId,
                        options: [{ label: __('— None (Standalone Rating) —', 'shuriken-reviews'), value: '' }].concat(
                            parentRatings.filter(function (r) {
                                // Exclude the current rating from parent options
                                return parseInt(r.id, 10) !== parseInt(ratingId, 10);
                            }).map(function (r) {
                                return { label: r.name, value: String(r.id) };
                            })
                        ),
                        onChange: setEditRatingParentId,
                        help: __('Select a parent to make this a sub-rating. Sub-ratings contribute to their parent\'s score.', 'shuriken-reviews')
                    }),
                    !editRatingMirrorOf && editRatingParentId && wp.element.createElement(SelectControl, {
                        label: __('Effect on Parent', 'shuriken-reviews'),
                        value: editRatingEffectType,
                        options: [
                            { label: __('Positive — Votes add to parent rating', 'shuriken-reviews'), value: 'positive' },
                            { label: __('Negative — Votes subtract from parent rating', 'shuriken-reviews'), value: 'negative' }
                        ],
                        onChange: setEditRatingEffectType,
                        help: __('Positive: Higher votes improve parent score. Negative: Higher votes lower parent score (e.g., "Difficulty" or "Price").', 'shuriken-reviews')
                    }),
                    !editRatingMirrorOf && !editRatingParentId && wp.element.createElement(CheckboxControl, {
                        label: __('Display Only', 'shuriken-reviews'),
                        checked: editRatingDisplayOnly,
                        onChange: setEditRatingDisplayOnly,
                        help: __('Enable this for parent ratings where visitors should only vote via sub-ratings.', 'shuriken-reviews')
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
                            onClick: updateRating,
                            isBusy: updating,
                            disabled: updating || !editRatingName.trim()
                        }, __('Update', 'shuriken-reviews'))
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
                                { className: 'shuriken-rating-placeholder' },
                                wp.element.createElement('span', { className: 'dashicons dashicons-star-filled' }),
                                wp.element.createElement('p', null, __('Select a rating from the sidebar or create a new one.', 'shuriken-reviews'))
                            )
                            : selectedRating
                                ? wp.element.createElement(
                                    'div',
                                    { className: 'shuriken-rating' },
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
                                            { className: 'stars' },
                                            [1, 2, 3, 4, 5].map(function (i) {
                                                return wp.element.createElement(
                                                    'span',
                                                    {
                                                        key: i,
                                                        className: 'star' + (i <= average ? ' active' : '')
                                                    },
                                                    '★'
                                                );
                                            })
                                        ),
                                        wp.element.createElement(
                                            'div',
                                            { className: 'rating-stats' },
                                            __('Average:', 'shuriken-reviews') + ' ' + average + '/5 (' + totalVotes + ' ' + __('votes', 'shuriken-reviews') + ')'
                                        )
                                    )
                                )
                                : wp.element.createElement(
                                    'div',
                                    { className: 'shuriken-rating-placeholder' },
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
