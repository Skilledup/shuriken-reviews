/**
 * Shuriken Reviews – Query Loop Sort Extension
 *
 * Extends the core Query Loop block (core/query) with a "Sort by Rating"
 * inspector panel, enabling per-block archive sorting in block themes (FSE).
 *
 * How it works:
 *  1. Two custom attributes (`shurikenRatingId`, `shurikenOrderBy`) are added
 *     to the core/query block via the `blocks.registerBlockType` filter so the
 *     editor persists them in the post content.
 *  2. A higher-order component wraps BlockEdit to inject a PanelBody into the
 *     Query Loop inspector controls.
 *  3. On the PHP side the `query_loop_block_query_vars` filter reads these
 *     attributes and adds `_shuriken_block_sort`, `_shuriken_block_rating_id`,
 *     and `_shuriken_block_orderby` to the WP_Query args, which are then picked
 *     up by persistent `posts_join` / `posts_orderby` filters.
 *
 * @package Shuriken_Reviews
 * @since 1.16.0
 */

(function (wp) {
    'use strict';

    var addFilter       = wp.hooks.addFilter;
    var createHOC       = wp.compose.createHigherOrderComponent;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody       = wp.components.PanelBody;
    var SelectControl   = wp.components.SelectControl;
    var __              = wp.i18n.__;
    var Fragment        = wp.element.Fragment;
    var createElement   = wp.element.createElement;
    var useEffect       = wp.element.useEffect;
    var useSelect       = wp.data.useSelect;
    var useDispatch     = wp.data.useDispatch;

    var STORE_NAME = window.SHURIKEN_STORE_NAME || 'shuriken-reviews';

    // -------------------------------------------------------------------------
    // 1. Extend core/query attributes
    // -------------------------------------------------------------------------
    addFilter(
        'blocks.registerBlockType',
        'shuriken-reviews/query-sort-attributes',
        function (settings, name) {
            if (name !== 'core/query') {
                return settings;
            }
            return Object.assign({}, settings, {
                attributes: Object.assign({}, settings.attributes, {
                    shurikenRatingId: { type: 'number', default: 0 },
                    shurikenOrderBy:  { type: 'string', default: '' },
                }),
            });
        }
    );

    // -------------------------------------------------------------------------
    // 2. Inject inspector panel
    // -------------------------------------------------------------------------
    var withShurikenQuerySortControls = createHOC(function (BlockEdit) {
        return function (props) {
            var name       = props.name;
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            if (name !== 'core/query') {
                return createElement(BlockEdit, props);
            }

            var shurikenRatingId = attributes.shurikenRatingId || 0;
            var shurikenOrderBy  = attributes.shurikenOrderBy  || 'average';

            // Fetch parent ratings (top-level, non-mirror ratings) from store
            var storeResult = useSelect(function (select) {
                var store = select(STORE_NAME);
                if (!store) {
                    return { parentRatings: [], isLoadingParents: false };
                }
                return {
                    parentRatings:   store.getParentRatings   ? store.getParentRatings()   : [],
                    isLoadingParents: store.getIsLoadingParents ? store.getIsLoadingParents() : false,
                };
            }, []);

            var parentRatings    = storeResult.parentRatings;
            var isLoadingParents = storeResult.isLoadingParents;

            var dispatch = useDispatch(STORE_NAME);

            useEffect(function () {
                if (dispatch && dispatch.fetchParentRatings) {
                    dispatch.fetchParentRatings();
                }
            }, []);

            var ratingOptions = [
                { label: __('— Disabled —', 'shuriken-reviews'), value: 0 },
            ].concat(
                (parentRatings || []).map(function (r) {
                    return { label: r.name + ' (ID: ' + r.id + ')', value: r.id };
                })
            );

            var orderByOptions = [
                { label: __('Average Rating', 'shuriken-reviews'), value: 'average' },
                { label: __('Total Votes',    'shuriken-reviews'), value: 'votes'   },
            ];

            return createElement(
                Fragment,
                null,
                createElement(BlockEdit, props),
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        {
                            title:       __('Sort by Rating', 'shuriken-reviews'),
                            initialOpen: !!shurikenRatingId,
                        },
                        createElement(SelectControl, {
                            label:    __('Rating', 'shuriken-reviews'),
                            value:    shurikenRatingId,
                            options:  ratingOptions,
                            onChange: function (val) {
                                var id = parseInt(val, 10) || 0;
                                setAttributes({
                                    shurikenRatingId: id,
                                    // Reset orderby when rating is cleared
                                    shurikenOrderBy: id ? shurikenOrderBy : '',
                                });
                            },
                            help: isLoadingParents
                                ? __('Loading ratings…', 'shuriken-reviews')
                                : __('Choose which rating determines post order.', 'shuriken-reviews'),
                        }),
                        shurikenRatingId
                            ? createElement(SelectControl, {
                                label:    __('Order by', 'shuriken-reviews'),
                                value:    shurikenOrderBy,
                                options:  orderByOptions,
                                onChange: function (val) {
                                    setAttributes({ shurikenOrderBy: val });
                                },
                            })
                            : null
                    )
                )
            );
        };
    }, 'withShurikenQuerySortControls');

    addFilter(
        'editor.BlockEdit',
        'shuriken-reviews/query-sort-controls',
        withShurikenQuerySortControls
    );

}(window.wp));
