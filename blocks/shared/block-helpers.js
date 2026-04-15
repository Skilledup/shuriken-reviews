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

    const __ = wp.i18n.__;
    const h  = wp.element.createElement;
    const useCallback = wp.element.useCallback;
    const useRef      = wp.element.useRef;
    const useEffect   = wp.element.useEffect;

    /* ── Lucide SVG icon helpers ─────────────────────────────────────── */

    const ICON_DEFAULTS = {
        xmlns: 'http://www.w3.org/2000/svg',
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 2,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        className: 'shuriken-icon'
    };

    const svgIcon = (size, paths) => {
        const attrs = { ...ICON_DEFAULTS, width: size, height: size };
        return h('svg', attrs, paths);
    };

    const iconStar = (size) => {
        return svgIcon(size || 24, h('polygon', { points: '12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2' }));
    }

    const iconThumbsUp = (size) => {
        return svgIcon(size || 18, [
            h('path', { key: 'p', d: 'M7 10v12' }),
            h('path', { key: 'h', d: 'M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z' })
        ]);
    }

    const iconThumbsDown = (size) => {
        return svgIcon(size || 18, [
            h('path', { key: 'p', d: 'M17 14V2' }),
            h('path', { key: 'h', d: 'M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z' })
        ]);
    }

    const iconChevronUp = (size) => {
        return svgIcon(size || 14, h('path', { d: 'm18 15-6-6-6 6' }));
    }

    const iconTriangleAlert = (size) => {
        return svgIcon(size || 24, [
            h('path', { key: 'p', d: 'm21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3' }),
            h('line', { key: 'l1', x1: '12', x2: '12', y1: '9', y2: '13' }),
            h('line', { key: 'l2', x1: '12', x2: '12.01', y1: '17', y2: '17' })
        ]);
    }

    const iconShare2 = (size) => {
        return svgIcon(size || 24, [
            h('circle', { key: 'c1', cx: '18', cy: '5', r: '3' }),
            h('circle', { key: 'c2', cx: '6', cy: '12', r: '3' }),
            h('circle', { key: 'c3', cx: '18', cy: '19', r: '3' }),
            h('line', { key: 'l1', x1: '8.59', x2: '15.42', y1: '13.51', y2: '17.49' }),
            h('line', { key: 'l2', x1: '15.41', x2: '8.59', y1: '6.51', y2: '10.49' })
        ]);
    }

    /**
     * Translate an API error object into a human-readable string.
     *
     * @param {Object|string} err - Error from apiFetch or store thunk.
     * @return {string} Localised error message.
     */
    const formatApiError = (err) => {
        let msg = __('An unexpected error occurred.', 'shuriken-reviews');

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
     * Error codes that indicate a non-retryable validation failure.
     * Retry would produce the same error, so the Retry button is suppressed.
     */
    const NON_RETRYABLE_CODES = [
        'validation_rating_type_invalid',
        'validation_name_invalid',
        'validation_scale_invalid',
        'rest_forbidden'
    ];

    /**
     * Build an error-handler callback bound to local state setters.
     *
     * @param {Function} setError           - setState for the error message.
     * @param {Function} setLastFailedAction - setState for the retry callback.
     * @return {Function} handleApiError(err, retryAction)
     */
    const makeErrorHandler = (setError, setLastFailedAction) => {
        return (err, retryAction) => {
            console.error('Shuriken Reviews API Error:', err);
            setError(formatApiError(err));
            // Only offer retry for transient / retryable errors
            const code = (err && err.code) ? err.code : '';
            if (NON_RETRYABLE_CODES.indexOf(code) === -1) {
                setLastFailedAction(retryAction);
            } else {
                setLastFailedAction(null);
            }
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
    const makeErrorDismissers = (setError, setLastFailedAction, clearError) => {
        const retryLastAction = (lastFailedAction) => {
            setError(null);
            clearError();
            if (lastFailedAction) {
                lastFailedAction();
            }
            setLastFailedAction(null);
        };

        const dismissError = () => {
            setError(null);
            clearError();
            setLastFailedAction(null);
        };

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
    const useSearchHandler = (searchFn, type, limit, delay) => {
        type  = type  || 'all';
        limit = limit || 20;
        delay = delay || 300;

        const timeoutRef = useRef(null);

        // Cleanup on unmount
        useEffect( () => {
            return () => {
                if (timeoutRef.current) {
                    clearTimeout(timeoutRef.current);
                }
            };
        }, []);

        return useCallback( (term) => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }

            if (term && term.trim().length > 0) {
                timeoutRef.current = setTimeout( () => {
                    searchFn(term.trim(), type, limit);
                }, delay);
            }
        }, [searchFn, type, limit, delay]);
    };

    /**
     * Title tag options shared by both blocks.
     */
    const titleTagOptions = [
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
    const calculateAverage = (rating) => {
        const tv = parseInt(rating.total_votes, 10) || 0;
        if (tv > 0) {
            return Math.round((parseFloat(rating.total_rating) / tv) * 100) / 100;
        }
        return 0;
    }

    /**
     * Rating type options shared by both blocks.
     */
    const ratingTypeOptions = [
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
    const getScaleRange = (ratingType) => {
        if (ratingType === 'numeric') return { min: 2, max: 100 };
        if (ratingType === 'stars')   return { min: 2, max: 10 };
        return { min: 1, max: 1, fixed: true };
    }

    /**
     * Get the rating type from a rating object, defaulting to 'stars'.
     *
     * @param {Object} rating
     * @return {string}
     */
    const getRatingType = (rating) => {
        return (rating && rating.rating_type) ? rating.rating_type : 'stars';
    }

    /**
     * Get the scale from a rating object, defaulting to 5.
     *
     * @param {Object} rating
     * @return {number}
     */
    const getRatingScale = (rating) => {
        return (rating && rating.scale) ? parseInt(rating.scale, 10) : 5;
    }

    /**
     * Calculate the scaled average (convert from normalized 1–5 to custom scale).
     *
     * @param {Object} rating
     * @return {number}
     */
    const calculateScaledAverage = (rating) => {
        return parseFloat(rating.display_average) || 0;
    }

    /**
     * Render type-aware rating widget and stats for the editor preview.
     *
     * @param {Object}   rating - Rating object from store.
     * @param {Function} h      - wp.element.createElement.
     * @return {Array} [widgetElement, statsElement]
     */
    const renderRatingPreview = (rating, h) => {
        const type        = getRatingType(rating);
        const scale       = getRatingScale(rating);
        const totalVotes  = parseInt(rating.total_votes, 10)  || 0;
        const totalRating = parseFloat(rating.total_rating) || 0;

        if (type === 'like_dislike') {
            const likes    = totalRating;
            const dislikes = Math.max(0, totalVotes - totalRating);
            return [
                h('div', { className: 'shuriken-like-dislike display-only-stars' },
                    h('span', { className: 'shuriken-btn shuriken-like-btn' },
                        h('span', { className: 'shuriken-thumb' }, iconThumbsUp(18)),
                        h('span', { className: 'shuriken-count shuriken-like-count' }, String(likes))
                    ),
                    h('span', { className: 'shuriken-btn shuriken-dislike-btn' },
                        h('span', { className: 'shuriken-thumb' }, iconThumbsDown(18)),
                        h('span', { className: 'shuriken-count shuriken-dislike-count' }, String(dislikes))
                    )
                ),
                null
            ];
        }

        if (type === 'approval') {
            return [
                h('div', { className: 'shuriken-approval display-only-stars' },
                    h('span', { className: 'shuriken-btn shuriken-upvote-btn' },
                        h('span', { className: 'shuriken-thumb' }, iconChevronUp(14)),
                        h('span', { className: 'shuriken-count shuriken-upvote-count' }, String(totalVotes))
                    )
                ),
                null
            ];
        }

        if (type === 'numeric') {
            const numAvg    = calculateScaledAverage(rating);
            const isDisplayOnly = rating.display_only == 1 || rating.is_display_only == 1;
            const sliderVal = numAvg > 0 ? Math.round(numAvg) : Math.round(scale / 2);

            // Display-only numeric: simplified readout matching PHP output
            if (isDisplayOnly) {
                const displayVal = numAvg > 0 ? (Math.round(numAvg * 10) / 10) : 0;
                return [
                    h('div', { className: 'shuriken-numeric display-only-stars' },
                        h('span', { className: 'shuriken-numeric-display' },
                            h('span', { className: 'shuriken-numeric-value' }, String(displayVal)),
                            h('span', { className: 'shuriken-slider-max' }, `/ ${scale}`)
                        )
                    ),
                    h('div', { className: 'rating-stats' },
                        `${__('Average:', 'shuriken-reviews')} ${numAvg}/${scale} (${totalVotes} ${__('votes', 'shuriken-reviews')})`
                    )
                ];
            }

            return [
                h('div', { className: 'shuriken-numeric' },
                    h('input', {
                        type: 'range',
                        className: 'shuriken-slider',
                        min: '1',
                        max: String(scale),
                        value: String(sliderVal),
                        step: '1',
                        readOnly: true,
                        disabled: true
                    }),
                    h('span', { className: 'shuriken-slider-value' }, String(sliderVal)),
                    h('span', { className: 'shuriken-slider-max' }, `/ ${scale}`),
                    h('button', {
                        type: 'button',
                        className: 'shuriken-slider-submit',
                        disabled: true
                    }, __('Rate', 'shuriken-reviews'))
                ),
                h('div', { className: 'rating-stats' },
                    `${__('Average:', 'shuriken-reviews')} ${numAvg}/${scale} (${totalVotes} ${__('votes', 'shuriken-reviews')})`
                )
            ];
        }

        // Default: stars
        const starAvg = calculateScaledAverage(rating);
        const starEls = [];
        for (let i = 1; i <= scale; i++) {
            starEls.push(h('span', {
                key: i,
                className: `star${i <= starAvg ? ' active' : ''}`
            }, iconStar(24)));
        }
        return [
            h('div', { className: 'stars display-only-stars' }, starEls),
            h('div', { className: 'rating-stats' },
                `${__('Average:', 'shuriken-reviews')} ${starAvg}/${scale} (${totalVotes} ${__('votes', 'shuriken-reviews')})`
            )
        ];
    };

    /**
     * Format a compact stats string for card headers etc.
     *
     * @param {Object} rating
     * @return {string}
     */
    const formatCompactStats = (rating) => {
        const type        = getRatingType(rating);
        const totalVotes  = parseInt(rating.total_votes, 10)  || 0;
        const totalRating = parseFloat(rating.total_rating) || 0;

        if (type === 'like_dislike') {
            return `+${totalRating} / -${Math.max(0, totalVotes - totalRating)}`;
        }
        if (type === 'approval') {
            return `${totalVotes} ${__('votes', 'shuriken-reviews')}`;
        }

        const scale     = getRatingScale(rating);
        const scaledAvg = calculateScaledAverage(rating);
        return `${scaledAvg}/${scale} (${totalVotes} ${__('votes', 'shuriken-reviews')})`;
    }

    /**
     * Get the type class for a rating type.
     * "continuous" types (stars, numeric) normalize votes to 1–5.
     * "binary" types (like_dislike, approval) store 0 or 1 per vote.
     *
     * @param {string} ratingType
     * @return {string} 'continuous' or 'binary'
     */
    const getTypeClass = (ratingType) => {
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
    const areTypesCompatible = (parentType, childType) => {
        return getTypeClass(parentType) === getTypeClass(childType);
    }

    /**
     * Check whether the "widget color" setting (star/slider color) is
     * relevant for a given rating type.
     *
     * @param {string} ratingType
     * @return {boolean}
     */
    const hasWidgetColor = (ratingType) => {
        return ratingType === 'stars' || ratingType === 'numeric';
    }

    /**
     * Get type-appropriate label for the widget colour picker.
     *
     * @param {string} ratingType
     * @return {string}
     */
    const getWidgetColorLabel = (ratingType) => {
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
    const buildColorSettings = (opts) => {
        const settings = [
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

        if (opts.setButton && opts.ratingType === 'numeric') {
            settings.push({
                value: opts.buttonColor,
                onChange: opts.setButton,
                label: __('Button Color', 'shuriken-reviews')
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
        areTypesCompatible:     areTypesCompatible,
        iconStar:               iconStar,
        iconTriangleAlert:      iconTriangleAlert,
        iconShare2:             iconShare2
    };
})(window.wp);
