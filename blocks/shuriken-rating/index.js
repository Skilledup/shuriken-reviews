(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl, Button, Spinner, Modal, ComboboxControl, SelectControl, CheckboxControl } = wp.components;
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
            const [newRatingName, setNewRatingName] = useState('');
            const [newRatingMirrorOf, setNewRatingMirrorOf] = useState('');
            const [newRatingParentId, setNewRatingParentId] = useState('');
            const [newRatingEffectType, setNewRatingEffectType] = useState('positive');
            const [newRatingDisplayOnly, setNewRatingDisplayOnly] = useState(false);
            const [parentRatings, setParentRatings] = useState([]);
            const [mirrorableRatings, setMirrorableRatings] = useState([]);
            const [creating, setCreating] = useState(false);
            const hasFetched = useRef(false);

            const blockProps = useBlockProps({
                className: 'shuriken-rating-block-editor'
            });

            // Fetch available ratings only once
            useEffect(function () {
                if (hasFetched.current) {
                    return;
                }
                hasFetched.current = true;
                
                // Fetch all ratings, parent ratings, and mirrorable ratings in parallel
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
                    .catch(function (error) {
                        console.error('Shuriken Reviews: Failed to fetch ratings', error);
                        setRatings([]);
                        setParentRatings([]);
                        setMirrorableRatings([]);
                        setLoading(false);
                    });
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
                    })
                    .catch(function () {
                        setCreating(false);
                    });
            }

            // Handle Enter key in the modal
            function handleKeyDown(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    createNewRating();
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
                                wp.element.createElement(Button, {
                                    variant: 'secondary',
                                    onClick: function () { setIsModalOpen(true); },
                                    style: { marginTop: '12px', marginBottom: '16px' }
                                }, __('Create New Rating', 'shuriken-reviews'))
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
                wp.element.createElement(
                    'div',
                    blockProps,
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
