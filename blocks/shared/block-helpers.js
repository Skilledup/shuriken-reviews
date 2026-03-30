/**
 * Shuriken Reviews — Shared Block Helpers
 *
 * Utilities used by both the single rating and grouped rating blocks
 * to eliminate duplicated error-handling, search, and UI logic.
 *
 * @package Shuriken_Reviews
 * @since   2.2.0
 */

(function (wp) {
    'use strict';

    var __ = wp.i18n.__;
    var useCallback = wp.element.useCallback;
    var useRef      = wp.element.useRef;
    var useEffect   = wp.element.useEffect;

    /**
     * Translate an API error object into a human-readable string.
     *
     * @param {Object|string} err - Error from apiFetch or store thunk.
     * @return {string} Localised error message.
     */
    function formatApiError(err) {
        var msg = __('An unexpected error occurred.', 'shuriken-reviews');

        if (err.message) {
            msg = err.message;
        } else if (err.data && err.data.message) {
            msg = err.data.message;
        } else if (typeof err === 'string') {
            msg = err;
        }

        if (err.code === 'rest_forbidden' || err.code === 'rest_cookie_invalid_nonce') {
            msg = __('Permission denied. Please refresh the page and try again.', 'shuriken-reviews');
        } else if (err.code === 'rest_no_route') {
            msg = __('API endpoint not found. Please ensure the plugin is properly installed.', 'shuriken-reviews');
        } else if (err.status === 429 || err.code === 'rate_limit_exceeded') {
            msg = __('Too many requests. Please wait a moment and try again.', 'shuriken-reviews');
        } else if (err.status === 404 || err.code === 'not_found') {
            msg = __('The requested resource was not found.', 'shuriken-reviews');
        } else if (err.status === 500 || err.code === 'internal_server_error') {
            msg = __('Server error. Please try again later.', 'shuriken-reviews');
        }

        return msg;
    }

    /**
     * Build an error handler bound to local state setters.
     *
     * @param {Function} setError           - setState for the error message.
     * @param {Function} setLastFailedAction - setState for the retry callback.
     * @return {Function} handleApiError(err, retryAction)
     */
    function makeErrorHandler(setError, setLastFailedAction) {
        return function handleApiError(err, retryAction) {
            console.error('Shuriken Reviews API Error:', err);
            setError(formatApiError(err));
            setLastFailedAction(retryAction);
        };
    }

    /**
     * Build retry / dismiss helpers bound to local state setters + store clearError.
     *
     * @param {Function} setError           - setState for the error message.
     * @param {Function} setLastFailedAction - setState for the retry callback.
     * @param {Function} clearError          - Store dispatch to clear global error.
     * @return {{ retryLastAction: Function, dismissError: Function }}
     */
    function makeErrorDismissers(setError, setLastFailedAction, clearError) {
        function retryLastAction(lastFailedAction) {
            setError(null);
            clearError();
            if (lastFailedAction) {
                lastFailedAction();
            }
            setLastFailedAction(null);
        }

        function dismissError() {
            setError(null);
            clearError();
            setLastFailedAction(null);
        }

        return { retryLastAction: retryLastAction, dismissError: dismissError };
    }

    /**
     * Custom hook: debounced search with automatic cleanup.
     *
     * @param {Function} searchFn - Store dispatch: searchRatings(term, type, limit)
     * @param {string}   type     - Search type filter ('all', 'parents', etc.)
     * @param {number}   limit    - Max results.
     * @param {number}   delay    - Debounce delay in ms (default 300).
     * @return {Function} handleSearchChange(term) — safe to pass as onFilterValueChange.
     */
    function useSearchHandler(searchFn, type, limit, delay) {
        type  = type  || 'all';
        limit = limit || 20;
        delay = delay || 300;

        var timeoutRef = useRef(null);

        // Cleanup on unmount
        useEffect(function () {
            return function () {
                if (timeoutRef.current) {
                    clearTimeout(timeoutRef.current);
                }
            };
        }, []);

        return useCallback(function (term) {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }

            if (term && term.trim().length > 0) {
                timeoutRef.current = setTimeout(function () {
                    searchFn(term.trim(), type, limit);
                }, delay);
            }
        }, [searchFn, type, limit, delay]);
    }

    /**
     * Title tag options shared by both blocks.
     */
    var titleTagOptions = [
        { label: 'H1', value: 'h1' },
        { label: 'H2', value: 'h2' },
        { label: 'H3', value: 'h3' },
        { label: 'H4', value: 'h4' },
        { label: 'H5', value: 'h5' },
        { label: 'H6', value: 'h6' },
        { label: 'DIV', value: 'div' },
        { label: 'P',   value: 'p' },
        { label: 'SPAN', value: 'span' }
    ];

    /**
     * Calculate rounded average for a rating object.
     *
     * @param {Object} rating - { total_votes, total_rating }
     * @return {number} Average rounded to one decimal (0 if no votes).
     */
    function calculateAverage(rating) {
        var tv = parseInt(rating.total_votes, 10) || 0;
        if (tv > 0) {
            return Math.round((parseInt(rating.total_rating, 10) / tv) * 10) / 10;
        }
        return 0;
    }

    /**
     * Rating type options shared by both blocks.
     */
    var ratingTypeOptions = [
        { label: __('Stars', 'shuriken-reviews'), value: 'stars' },
        { label: __('Like / Dislike', 'shuriken-reviews'), value: 'like_dislike' },
        { label: __('Numeric Slider', 'shuriken-reviews'), value: 'numeric' },
        { label: __('Approval (Upvote)', 'shuriken-reviews'), value: 'approval' }
    ];

    /**
     * Get valid scale range for a rating type.
     *
     * @param {string} ratingType
     * @return {{ min: number, max: number }}
     */
    function getScaleRange(ratingType) {
        if (ratingType === 'numeric') return { min: 2, max: 100 };
        if (ratingType === 'stars')   return { min: 2, max: 10 };
        return { min: 5, max: 5 };
    }

    /**
     * Get the rating type from a rating object, defaulting to 'stars'.
     *
     * @param {Object} rating
     * @return {string}
     */
    function getRatingType(rating) {
        return (rating && rating.rating_type) ? rating.rating_type : 'stars';
    }

    /**
     * Get the scale from a rating object, defaulting to 5.
     *
     * @param {Object} rating
     * @return {number}
     */
    function getRatingScale(rating) {
        return (rating && rating.scale) ? parseInt(rating.scale, 10) : 5;
    }

    /**
     * Calculate the scaled average (convert from normalized 1–5 to custom scale).
     *
     * @param {Object} rating
     * @return {number}
     */
    function calculateScaledAverage(rating) {
        var avg   = calculateAverage(rating);
        var scale = getRatingScale(rating);
        return Math.round((avg / 5) * scale * 10) / 10;
    }

    /**
     * Render type-aware rating widget and stats for the editor preview.
     *
     * @param {Object}   rating - Rating object from store.
     * @param {Function} h      - wp.element.createElement.
     * @return {Array} [widgetElement, statsElement]
     */
    function renderRatingPreview(rating, h) {
        var type        = getRatingType(rating);
        var scale       = getRatingScale(rating);
        var totalVotes  = parseInt(rating.total_votes, 10)  || 0;
        var totalRating = parseInt(rating.total_rating, 10) || 0;

        if (type === 'like_dislike') {
            var likes    = totalRating;
            var dislikes = Math.max(0, totalVotes - totalRating);
            return [
                h('div', { className: 'shuriken-like-dislike display-only-stars' },
                    h('span', { className: 'shuriken-btn shuriken-like-btn' },
                        h('span', { className: 'shuriken-thumb' }, '\uD83D\uDC4D'),
                        h('span', { className: 'shuriken-count shuriken-like-count' }, String(likes))
                    ),
                    h('span', { className: 'shuriken-btn shuriken-dislike-btn' },
                        h('span', { className: 'shuriken-thumb' }, '\uD83D\uDC4E'),
                        h('span', { className: 'shuriken-count shuriken-dislike-count' }, String(dislikes))
                    )
                ),
                h('div', { className: 'rating-stats' },
                    likes + ' ' + __('likes', 'shuriken-reviews') + ' \u00B7 ' + dislikes + ' ' + __('dislikes', 'shuriken-reviews')
                )
            ];
        }

        if (type === 'approval') {
            return [
                h('div', { className: 'shuriken-approval display-only-stars' },
                    h('span', { className: 'shuriken-btn shuriken-upvote-btn' },
                        h('span', { className: 'shuriken-thumb' }, '\u25B2'),
                        h('span', { className: 'shuriken-count shuriken-upvote-count' }, String(totalVotes))
                    )
                ),
                h('div', { className: 'rating-stats' },
                    totalVotes + ' ' + __('upvotes', 'shuriken-reviews')
                )
            ];
        }

        if (type === 'numeric') {
            var numAvg = calculateScaledAverage(rating);
            return [
                h('div', { className: 'shuriken-numeric display-only-stars' },
                    h('span', { className: 'shuriken-numeric-display' },
                        h('span', { className: 'shuriken-numeric-value' }, String(numAvg)),
                        h('span', { className: 'shuriken-slider-max' }, ' / ' + scale)
                    )
                ),
                h('div', { className: 'rating-stats' },
                    __('Average:', 'shuriken-reviews') + ' ' + numAvg + '/' + scale + ' (' + totalVotes + ' ' + __('votes', 'shuriken-reviews') + ')'
                )
            ];
        }

        // Default: stars
        var starAvg = calculateScaledAverage(rating);
        var starEls = [];
        for (var i = 1; i <= scale; i++) {
            starEls.push(h('span', {
                key: i,
                className: 'star' + (i <= starAvg ? ' active' : '')
            }, '\u2605'));
        }
        return [
            h('div', { className: 'stars display-only-stars' }, starEls),
            h('div', { className: 'rating-stats' },
                __('Average:', 'shuriken-reviews') + ' ' + starAvg + '/' + scale + ' (' + totalVotes + ' ' + __('votes', 'shuriken-reviews') + ')'
            )
        ];
    }

    /**
     * Format a compact stats string for card headers etc.
     *
     * @param {Object} rating
     * @return {string}
     */
    function formatCompactStats(rating) {
        var type        = getRatingType(rating);
        var totalVotes  = parseInt(rating.total_votes, 10)  || 0;
        var totalRating = parseInt(rating.total_rating, 10) || 0;

        if (type === 'like_dislike') {
            return '\uD83D\uDC4D ' + totalRating + ' / \uD83D\uDC4E ' + Math.max(0, totalVotes - totalRating);
        }
        if (type === 'approval') {
            return '\u25B2 ' + totalVotes;
        }

        var scale     = getRatingScale(rating);
        var scaledAvg = calculateScaledAverage(rating);
        return scaledAvg + '/' + scale + ' (' + totalVotes + ' ' + __('votes', 'shuriken-reviews') + ')';
    }

    /**
     * Get the type class for a rating type.
     * "continuous" types (stars, numeric) normalize votes to 1–5.
     * "binary" types (like_dislike, approval) store 0 or 1 per vote.
     *
     * @param {string} ratingType
     * @return {string} 'continuous' or 'binary'
     */
    function getTypeClass(ratingType) {
        if (ratingType === 'like_dislike' || ratingType === 'approval') {
            return 'binary';
        }
        return 'continuous';
    }

    /**
     * Check whether a child rating type is compatible with a parent rating type
     * for score aggregation purposes.
     *
     * Mixing binary (like_dislike, approval) with continuous (stars, numeric)
     * produces mathematically incorrect aggregated scores.
     *
     * @param {string} parentType
     * @param {string} childType
     * @return {boolean}
     */
    function areTypesCompatible(parentType, childType) {
        return getTypeClass(parentType) === getTypeClass(childType);
    }

    /**
     * Check whether the "widget color" setting (star/slider color) is
     * relevant for a given rating type.
     *
     * @param {string} ratingType
     * @return {boolean}
     */
    function hasWidgetColor(ratingType) {
        return ratingType === 'stars' || ratingType === 'numeric';
    }

    /**
     * Get type-appropriate label for the widget colour picker.
     *
     * @param {string} ratingType
     * @return {string}
     */
    function getWidgetColorLabel(ratingType) {
        if (ratingType === 'numeric') {
            return __('Slider Color', 'shuriken-reviews');
        }
        return __('Star Color', 'shuriken-reviews');
    }

    /**
     * Build the color settings array for a rating's PanelColorSettings.
     *
     * @param {Object}   opts
     * @param {string}   opts.ratingType  - Current rating type.
     * @param {string}   opts.accentColor - Current accent color value.
     * @param {string}   opts.starColor   - Current star/widget color value.
     * @param {Function} opts.setAccent   - onChange for accent.
     * @param {Function} opts.setStar     - onChange for star/widget color.
     * @return {Array} colorSettings array for PanelColorSettings.
     */
    function buildColorSettings(opts) {
        var settings = [
            {
                value: opts.accentColor,
                onChange: opts.setAccent,
                label: __('Accent Color', 'shuriken-reviews')
            }
        ];

        if (hasWidgetColor(opts.ratingType)) {
            settings.push({
                value: opts.starColor,
                onChange: opts.setStar,
                label: getWidgetColorLabel(opts.ratingType)
            });
        }

        return settings;
    }

    // Expose on global namespace so both blocks can import without a bundler
    window.ShurikenBlockHelpers = {
        formatApiError:         formatApiError,
        makeErrorHandler:       makeErrorHandler,
        makeErrorDismissers:    makeErrorDismissers,
        useSearchHandler:       useSearchHandler,
        titleTagOptions:        titleTagOptions,
        calculateAverage:       calculateAverage,
        ratingTypeOptions:      ratingTypeOptions,
        getScaleRange:          getScaleRange,
        getRatingType:          getRatingType,
        getRatingScale:         getRatingScale,
        calculateScaledAverage: calculateScaledAverage,
        renderRatingPreview:    renderRatingPreview,
        formatCompactStats:     formatCompactStats,
        hasWidgetColor:         hasWidgetColor,
        getWidgetColorLabel:    getWidgetColorLabel,
        buildColorSettings:     buildColorSettings,
        getTypeClass:           getTypeClass,
        areTypesCompatible:     areTypesCompatible
    };
})(window.wp);
