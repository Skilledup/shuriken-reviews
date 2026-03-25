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

    // Expose on global namespace so both blocks can import without a bundler
    window.ShurikenBlockHelpers = {
        formatApiError:      formatApiError,
        makeErrorHandler:    makeErrorHandler,
        makeErrorDismissers: makeErrorDismissers,
        useSearchHandler:    useSearchHandler,
        titleTagOptions:     titleTagOptions,
        calculateAverage:    calculateAverage
    };
})(window.wp);
