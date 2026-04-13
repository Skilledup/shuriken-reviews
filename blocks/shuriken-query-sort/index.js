/**
 * Shuriken Reviews – Query Loop Sort Extension
 *
 * Extends the core Query Loop block (core/query) with a "Shuriken Reviews"
 * inspector panel so editors can sort posts by rating in block themes (FSE).
 *
 * How it works:
 *  1. `shurikenRatingId`, `shurikenOrderBy`, and `shurikenOrder` are stored
 *     inside the existing `query` attribute object on core/query.  Because
 *     core/query declares `"providesContext": { "query": "query" }`, the
 *     values automatically flow to inner blocks via block context.
 *  2. A higher-order component wraps BlockEdit to inject a PanelBody into the
 *     Query Loop inspector controls.
 *  3. On the PHP side the `query_loop_block_query_vars` filter reads these
 *     values from `$block->context['query']` and tags the WP_Query with
 *     `_shuriken_block_sort` so persistent `posts_join` / `posts_orderby`
 *     filters can inject the rating-based ORDER BY.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

(function (wp) {
    'use strict';

    var addFilter         = wp.hooks.addFilter;
    var createHOC         = wp.compose.createHigherOrderComponent;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody         = wp.components.PanelBody;
    var SelectControl     = wp.components.SelectControl;
    var __                = wp.i18n.__;
    var Fragment          = wp.element.Fragment;
    var createElement     = wp.element.createElement;
    var useEffect         = wp.element.useEffect;
    var useSelect         = wp.data.useSelect;
    var useDispatch       = wp.data.useDispatch;

    var STORE_NAME = window.SHURIKEN_STORE_NAME || 'shuriken-reviews';

    // -------------------------------------------------------------------------
    // Inject inspector panel into core/query
    // -------------------------------------------------------------------------
    var withShurikenQuerySortControls = createHOC(function (BlockEdit) {
        return function (props) {
            if (props.name !== 'core/query') {
                return createElement(BlockEdit, props);
            }

            var attributes    = props.attributes;
            var setAttributes = props.setAttributes;
            var query         = attributes.query || {};
            var isInherited   = !!query.inherit;

            var shurikenRatingId = query.shurikenRatingId || 0;
            var shurikenOrderBy  = query.shurikenOrderBy  || 'average';
            var shurikenOrder    = query.shurikenOrder     || 'DESC';

            // Helper: merge into the existing query attribute object.
            function updateQuery(patch) {
                setAttributes({ query: Object.assign({}, query, patch) });
            }

            // Fetch available ratings from store
            var storeResult = useSelect(function (select) {
                var store = select(STORE_NAME);
                if (!store) {
                    return { parentRatings: [], isLoadingParents: false };
                }
                return {
                    parentRatings:    store.getParentRatings    ? store.getParentRatings()    : [],
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

            var orderOptions = [
                { label: __('Descending (highest first)', 'shuriken-reviews'), value: 'DESC' },
                { label: __('Ascending (lowest first)',   'shuriken-reviews'), value: 'ASC'  },
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
                            title:       __('Shuriken Reviews', 'shuriken-reviews'),
                            initialOpen: !!shurikenRatingId,
                        },
                        isInherited
                            ? createElement(
                                'p',
                                { style: { fontStyle: 'italic', color: '#757575' } },
                                __('This query inherits from the template. To sort inherited queries by rating, enable "Sort Archives by Rating" in Shuriken Reviews \u2192 Settings \u2192 General.', 'shuriken-reviews')
                            )
                            : null,
                        !isInherited
                            ? createElement(SelectControl, {
                                label:   __('Sort by Rating', 'shuriken-reviews'),
                                value:   shurikenRatingId,
                                options: ratingOptions,
                                onChange: function (val) {
                                    var id = parseInt(val, 10) || 0;
                                    updateQuery({
                                        shurikenRatingId: id,
                                        shurikenOrderBy:  id ? shurikenOrderBy : '',
                                        shurikenOrder:    id ? shurikenOrder : '',
                                    });
                                },
                                help: isLoadingParents
                                    ? __('Loading ratings…', 'shuriken-reviews')
                                    : __('Overrides the default post order. Unrated posts appear last.', 'shuriken-reviews'),
                            })
                            : null,
                        !isInherited && shurikenRatingId
                            ? createElement(SelectControl, {
                                label:   __('Sort metric', 'shuriken-reviews'),
                                value:   shurikenOrderBy,
                                options: orderByOptions,
                                onChange: function (val) {
                                    updateQuery({ shurikenOrderBy: val });
                                },
                            })
                            : null,
                        !isInherited && shurikenRatingId
                            ? createElement(SelectControl, {
                                label:   __('Direction', 'shuriken-reviews'),
                                value:   shurikenOrder,
                                options: orderOptions,
                                onChange: function (val) {
                                    updateQuery({ shurikenOrder: val });
                                },
                            })
                            : null,
                        !isInherited && shurikenRatingId
                            ? createElement(
                                'p',
                                {
                                    style: {
                                        fontStyle: 'italic',
                                        color: '#757575',
                                        fontSize: '12px',
                                        marginTop: '8px',
                                    },
                                },
                                __('Note: the editor preview may not reflect rating sort order. Save and preview the page to see the correct order.', 'shuriken-reviews')
                            )
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
