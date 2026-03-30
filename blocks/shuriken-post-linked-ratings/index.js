/**
 * Shuriken Reviews - Post Linked Ratings Block
 *
 * Dynamic block for site editor templates. Displays all ratings linked
 * to the current post via the post meta box. Server-side rendered.
 *
 * @package Shuriken_Reviews
 * @since 1.12.4
 */

(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps } = wp.blockEditor;
    const { Placeholder, Spinner } = wp.components;
    const { useSelect } = wp.data;
    const { __ } = wp.i18n;
    const { useEntityProp } = wp.coreData;

    registerBlockType('shuriken-reviews/post-linked-ratings', {
        edit: function Edit({ context }) {
            const blockProps = useBlockProps();
            const postType = context.postType;
            const postId = context.postId;

            // Try to read the linked rating IDs from post meta
            const [meta] = useEntityProp('postType', postType, 'meta', postId);
            const ratingIds = (meta && meta._shuriken_rating_ids) || [];
            const count = Array.isArray(ratingIds) ? ratingIds.length : 0;

            // Also check if we're in a template context (no specific postId)
            const isTemplate = !postId;

            return wp.element.createElement(
                'div',
                blockProps,
                wp.element.createElement(
                    Placeholder,
                    {
                        icon: 'star-filled',
                        label: __('Post Linked Ratings', 'shuriken-reviews'),
                    },
                    wp.element.createElement(
                        'p',
                        { style: { textAlign: 'center', margin: 0 } },
                        isTemplate
                            ? __('Displays ratings linked to the current post.', 'shuriken-reviews')
                            : (count > 0
                                ? count + ' ' + (count === 1
                                    ? __('linked rating', 'shuriken-reviews')
                                    : __('linked ratings', 'shuriken-reviews'))
                                : __('No ratings linked to this post.', 'shuriken-reviews')
                            )
                    )
                )
            );
        },
    });
})(window.wp);
