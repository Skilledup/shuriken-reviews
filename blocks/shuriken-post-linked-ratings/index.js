/**
 * Shuriken Reviews - Post Linked Ratings Block
 *
 * Dynamic block for site editor templates. Displays all ratings linked
 * to the current post via the post meta box. Server-side rendered on
 * the front end; shows a live preview in the editor.
 *
 * @package Shuriken_Reviews
 * @since 1.12.4
 */

(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps } = wp.blockEditor;
    const { Placeholder, Spinner } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const { useState, useEffect } = wp.element;
    const { __ } = wp.i18n;
    const { useEntityProp } = wp.coreData;

    const STORE_NAME = window.SHURIKEN_STORE_NAME || 'shuriken-reviews';
    const { renderRatingPreview } = window.ShurikenBlockHelpers;

    registerBlockType('shuriken-reviews/post-linked-ratings', {
        edit: function Edit({ context }) {
            const blockProps = useBlockProps({ className: 'shuriken-post-ratings' });
            const postType = context.postType;
            const postId = context.postId;

            // Try to read the linked rating IDs from post meta
            const [meta] = useEntityProp('postType', postType, 'meta', postId);
            const ratingIds = (meta && meta._shuriken_rating_ids) || [];
            const ids = Array.isArray(ratingIds) ? ratingIds.map(Number).filter(Boolean) : [];

            // Also check if we're in a template context (no specific postId)
            const isTemplate = !postId;

            // Fetch ratings from the store
            const { fetchRating } = useDispatch(STORE_NAME);

            useEffect(function () {
                ids.forEach(function (id) {
                    fetchRating(id);
                });
            }, [ids.join(',')]);

            const ratings = useSelect(function (select) {
                var store = select(STORE_NAME);
                return ids.map(function (id) {
                    return store.getRating(id);
                });
            }, [ids.join(',')]);

            var isLoading = ids.length > 0 && ratings.some(function (r) { return !r; });

            // Template context — generic placeholder
            if (isTemplate) {
                return wp.element.createElement(
                    'div',
                    blockProps,
                    wp.element.createElement(
                        Placeholder,
                        {
                            icon: 'star-filled',
                            label: __('Shuriken Post Linked Ratings', 'shuriken-reviews'),
                        },
                        wp.element.createElement(
                            'p',
                            { style: { textAlign: 'center', margin: 0 } },
                            __('Displays ratings linked to the current post.', 'shuriken-reviews')
                        )
                    )
                );
            }

            // No ratings linked
            if (ids.length === 0) {
                return wp.element.createElement(
                    'div',
                    blockProps,
                    wp.element.createElement(
                        Placeholder,
                        {
                            icon: 'star-filled',
                            label: __('Shuriken Post Linked Ratings', 'shuriken-reviews'),
                        },
                        wp.element.createElement(
                            'p',
                            { style: { textAlign: 'center', margin: 0 } },
                            __('No ratings linked to this post. Use the Shuriken Ratings meta box to link ratings.', 'shuriken-reviews')
                        )
                    )
                );
            }

            // Loading
            if (isLoading) {
                return wp.element.createElement(
                    'div',
                    blockProps,
                    wp.element.createElement(
                        Placeholder,
                        {
                            icon: 'star-filled',
                            label: __('Shuriken Post Linked Ratings', 'shuriken-reviews'),
                        },
                        wp.element.createElement(Spinner, null)
                    )
                );
            }

            // Render live preview of each linked rating
            var previews = ratings.filter(Boolean).map(function (rating) {
                var preview = renderRatingPreview(rating, wp.element.createElement);
                return wp.element.createElement(
                    'div',
                    { key: rating.id, className: 'shuriken-rating' },
                    wp.element.createElement('h2', { className: 'rating-title' }, rating.name),
                    preview[0],
                    preview[1]
                );
            });

            return wp.element.createElement('div', blockProps, previews);
        },
    });
})(window.wp);
