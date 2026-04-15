/**
 * Shuriken Reviews - Shared Ratings Data Store
 * 
 * This module provides a centralized data store for rating data using @wordpress/data.
 * All rating blocks share this store to prevent duplicate API calls and ensure
 * consistent data across the editor.
 * 
 * @package Shuriken_Reviews
 * @since 1.9.0
 */

(function(wp) {
    const { createReduxStore, register, select, dispatch } = wp.data;
    const apiFetch = wp.apiFetch;

    const STORE_NAME = 'shuriken-reviews';

    // =========================================================================
    // In-flight request deduplication
    // =========================================================================
    // When multiple blocks mount at the same time they all dispatch the same
    // thunks before the store state (e.g. parentsFetched) has been updated.
    // This map ensures only one network request is ever in-flight per key.
    const _inflight = {};

    /**
     * Validate that an ID is a positive integer (or numeric string).
     */
    const isValidId = (id) => {
        const n = Number(id);
        return Number.isInteger(n) && n > 0;
    };

    /**
     * Return the existing promise if a request for `key` is already in-flight,
     * otherwise call `fn()`, store its promise, and clean up when it settles.
     */
    const dedup = (key, fn) => {
        if (_inflight[key]) {
            return _inflight[key];
        }
        const p = fn().then((result) => {
            delete _inflight[key];
            return result;
        }).catch((err) => {
            delete _inflight[key];
            throw err;
        });
        _inflight[key] = p;
        return p;
    };

    // =========================================================================
    // Batched fetchRating scheduler
    // =========================================================================
    // Instead of firing N individual /ratings/{id} requests when N blocks mount
    // simultaneously, we collect IDs for a microtask tick and fire a single
    // /ratings/batch?ids=... request.
    let _batchQueue = [];       // Array of { id, resolve, reject }
    let _batchTimer = null;

    const scheduleBatchFetch = (ratingId, args) => {
        return new Promise((resolve, reject) => {
            _batchQueue.push({ id: ratingId, resolve: resolve, reject: reject });
            if (!_batchTimer) {
                _batchTimer = setTimeout(() => {
                    flushBatchFetch(args);
                }, 0);
            }
        });
    };

    const flushBatchFetch = (args) => {
        const queue = _batchQueue;
        _batchQueue = [];
        _batchTimer = null;

        if (queue.length === 0) return;

        // Deduplicate IDs
        const idMap = {};
        queue.forEach((item) => {
            if (!idMap[item.id]) idMap[item.id] = [];
            idMap[item.id].push(item);
        });
        const uniqueIds = Object.keys(idMap).map(Number);

        // Single request for 1 ID, batch for multiple
        let fetchPromise;
        if (uniqueIds.length === 1) {
            fetchPromise = apiFetch({
                path: `/shuriken-reviews/v1/ratings/${uniqueIds[0]}`,
                method: 'GET',
            }).then((rating) => {
                return [rating];
            });
        } else {
            fetchPromise = apiFetch({
                path: `/shuriken-reviews/v1/ratings/batch?ids=${uniqueIds.join(',')}`,
                method: 'GET',
            }).then((ratings) => {
                return Array.isArray(ratings) ? ratings : [];
            });
        }

        fetchPromise.then((ratings) => {
            const byId = {};
            ratings.forEach((r) => { if (r && r.id) byId[r.id] = r; });
            args.dispatch.setRatings(ratings);
            uniqueIds.forEach((id) => {
                args.dispatch.setIsLoadingRating(id, false);
                const items = idMap[id];
                items.forEach((item) => {
                    item.resolve(byId[id] || null);
                });
            });
        }).catch((error) => {
            console.error('batch fetchRating error:', error);
            uniqueIds.forEach((id) => {
                args.dispatch.setIsLoadingRating(id, false);
                const items = idMap[id];
                items.forEach((item) => {
                    item.resolve(null);
                });
            });
        });
    };

    // Default state
    const DEFAULT_STATE = {
        // Cache of all fetched ratings by ID
        ratingsById: {},
        // Search results (temporary, refreshed on each search)
        searchResults: [],
        // Parent ratings cache
        parentRatings: [],
        // Mirrorable ratings cache
        mirrorableRatings: [],
        // Mirrors cache keyed by source rating ID
        mirrorsById: {},
        // Loading states
        isSearching: false,
        isLoadingParents: false,
        isLoadingMirrorable: false,
        isLoadingRating: {},
        isLoadingMirrors: {},
        // Flags to track if initial data has been fetched
        parentsFetched: false,
        mirrorableFetched: false,
        // Error state
        lastError: null,
    };

    // Action types
    const ACTIONS = {
        SET_RATING: 'SET_RATING',
        SET_RATINGS: 'SET_RATINGS',
        SET_SEARCH_RESULTS: 'SET_SEARCH_RESULTS',
        SET_PARENT_RATINGS: 'SET_PARENT_RATINGS',
        SET_MIRRORABLE_RATINGS: 'SET_MIRRORABLE_RATINGS',
        SET_IS_SEARCHING: 'SET_IS_SEARCHING',
        SET_IS_LOADING_PARENTS: 'SET_IS_LOADING_PARENTS',
        SET_IS_LOADING_MIRRORABLE: 'SET_IS_LOADING_MIRRORABLE',
        SET_IS_LOADING_RATING: 'SET_IS_LOADING_RATING',
        SET_PARENTS_FETCHED: 'SET_PARENTS_FETCHED',
        SET_MIRRORABLE_FETCHED: 'SET_MIRRORABLE_FETCHED',
        SET_ERROR: 'SET_ERROR',
        CLEAR_ERROR: 'CLEAR_ERROR',
        ADD_TO_PARENT_RATINGS: 'ADD_TO_PARENT_RATINGS',
        ADD_TO_MIRRORABLE_RATINGS: 'ADD_TO_MIRRORABLE_RATINGS',
        UPDATE_RATING_IN_LISTS: 'UPDATE_RATING_IN_LISTS',
        REMOVE_FROM_LISTS: 'REMOVE_FROM_LISTS',
        SET_MIRRORS_FOR_RATING: 'SET_MIRRORS_FOR_RATING',
        SET_IS_LOADING_MIRRORS: 'SET_IS_LOADING_MIRRORS',
        INVALIDATE_MIRRORS_CACHE: 'INVALIDATE_MIRRORS_CACHE',
    };

    // Simple action creators (synchronous only)
    const actions = {
        setRating(rating) {
            return { type: ACTIONS.SET_RATING, rating };
        },
        setRatings(ratings) {
            return { type: ACTIONS.SET_RATINGS, ratings };
        },
        setSearchResults(results) {
            return { type: ACTIONS.SET_SEARCH_RESULTS, results };
        },
        setParentRatings(ratings) {
            return { type: ACTIONS.SET_PARENT_RATINGS, ratings };
        },
        setMirrorableRatings(ratings) {
            return { type: ACTIONS.SET_MIRRORABLE_RATINGS, ratings };
        },
        setIsSearching(isSearching) {
            return { type: ACTIONS.SET_IS_SEARCHING, isSearching };
        },
        setIsLoadingParents(isLoading) {
            return { type: ACTIONS.SET_IS_LOADING_PARENTS, isLoading };
        },
        setIsLoadingMirrorable(isLoading) {
            return { type: ACTIONS.SET_IS_LOADING_MIRRORABLE, isLoading };
        },
        setIsLoadingRating(ratingId, isLoading) {
            return { type: ACTIONS.SET_IS_LOADING_RATING, ratingId, isLoading };
        },
        setParentsFetched(fetched) {
            return { type: ACTIONS.SET_PARENTS_FETCHED, fetched };
        },
        setMirrorableFetched(fetched) {
            return { type: ACTIONS.SET_MIRRORABLE_FETCHED, fetched };
        },
        setError(error) {
            return { type: ACTIONS.SET_ERROR, error };
        },
        clearError() {
            return { type: ACTIONS.CLEAR_ERROR };
        },
        addToParentRatings(rating) {
            return { type: ACTIONS.ADD_TO_PARENT_RATINGS, rating };
        },
        addToMirrorableRatings(rating) {
            return { type: ACTIONS.ADD_TO_MIRRORABLE_RATINGS, rating };
        },
        updateRatingInLists(rating) {
            return { type: ACTIONS.UPDATE_RATING_IN_LISTS, rating };
        },
        removeFromLists(ratingId) {
            return { type: ACTIONS.REMOVE_FROM_LISTS, ratingId };
        },
        setMirrorsForRating(ratingId, mirrors) {
            return { type: ACTIONS.SET_MIRRORS_FOR_RATING, ratingId, mirrors };
        },
        setIsLoadingMirrors(ratingId, isLoading) {
            return { type: ACTIONS.SET_IS_LOADING_MIRRORS, ratingId, isLoading };
        },
        invalidateMirrorsCache(ratingId) {
            return { type: ACTIONS.INVALIDATE_MIRRORS_CACHE, ratingId };
        },
    };

    // Thunks - async action creators that use dispatch
    // WordPress @wordpress/data thunks receive { select, dispatch, registry }
    const thunks = {
        fetchRating: (ratingId) => (args) => {
            if (!isValidId(ratingId)) {
                return Promise.resolve(null);
            }

            // Check cache first
            const cached = args.select.getRating(ratingId);
            if (cached) {
                return Promise.resolve(cached);
            }

            // Deduplicate: if this ID is already queued or in-flight, piggyback
            return dedup(`rating-${ratingId}`, () => {
                args.dispatch.setIsLoadingRating(ratingId, true);
                return scheduleBatchFetch(ratingId, args);
            });
        },

        searchRatings: (searchTerm, type = 'all', limit = 20) => (args) => {
            // Don't make API call for empty search term
            if (!searchTerm || searchTerm.trim().length === 0) {
                args.dispatch.setSearchResults([]);
                return Promise.resolve([]);
            }

            args.dispatch.setIsSearching(true);
            args.dispatch.clearError();

            const path = `/shuriken-reviews/v1/ratings/search?q=${encodeURIComponent(searchTerm.trim())}&type=${type}&limit=${limit}`;
            
            return apiFetch({
                path: path,
                method: 'GET',
            }).then((results) => {
                const resultsArray = Array.isArray(results) ? results : [];
                args.dispatch.setRatings(resultsArray);
                args.dispatch.setSearchResults(resultsArray);
                args.dispatch.setIsSearching(false);
                return resultsArray;
            }).catch((error) => {
                console.error('searchRatings error:', error);
                // Only log search errors — do not set store-level error
                // to avoid propagating to every block instance.
                args.dispatch.setSearchResults([]);
                args.dispatch.setIsSearching(false);
                return [];
            });
        },

        fetchParentRatings: () => (args) => {
            // Check if already fetched
            if (args.select.areParentsFetched()) {
                return Promise.resolve(args.select.getParentRatings());
            }

            // Deduplicate: all blocks share the same in-flight request
            return dedup('parents', () => {
                args.dispatch.setIsLoadingParents(true);

                return apiFetch({
                    path: '/shuriken-reviews/v1/ratings/parents',
                    method: 'GET',
                }).then((ratings) => {
                    const ratingsArray = Array.isArray(ratings) ? ratings : [];
                    args.dispatch.setParentRatings(ratingsArray);
                    args.dispatch.setRatings(ratingsArray);
                    args.dispatch.setParentsFetched(true);
                    args.dispatch.setIsLoadingParents(false);
                    return ratingsArray;
                }).catch((error) => {
                    console.error('fetchParentRatings error:', error);
                    args.dispatch.setError(error.message || 'Failed to fetch parent ratings');
                    args.dispatch.setIsLoadingParents(false);
                    return [];
                });
            });
        },

        fetchMirrorableRatings: () => (args) => {
            // Check if already fetched
            if (args.select.areMirrorableFetched()) {
                return Promise.resolve(args.select.getMirrorableRatings());
            }

            // Deduplicate: all blocks share the same in-flight request
            return dedup('mirrorable', () => {
                args.dispatch.setIsLoadingMirrorable(true);

                return apiFetch({
                    path: '/shuriken-reviews/v1/ratings/mirrorable',
                    method: 'GET',
                }).then((ratings) => {
                    const ratingsArray = Array.isArray(ratings) ? ratings : [];
                    args.dispatch.setMirrorableRatings(ratingsArray);
                    args.dispatch.setRatings(ratingsArray);
                    args.dispatch.setMirrorableFetched(true);
                    args.dispatch.setIsLoadingMirrorable(false);
                    return ratingsArray;
                }).catch((error) => {
                    console.error('fetchMirrorableRatings error:', error);
                    args.dispatch.setError(error.message || 'Failed to fetch mirrorable ratings');
                    args.dispatch.setIsLoadingMirrorable(false);
                    return [];
                });
            });
        },

        fetchChildRatings: (parentId) => (args) => {
            if (!isValidId(parentId)) {
                return Promise.resolve([]);
            }

            return dedup(`children-${parentId}`, () => {
                return apiFetch({
                    path: `/shuriken-reviews/v1/ratings/${parentId}/children`,
                    method: 'GET',
                }).then((ratings) => {
                    const ratingsArray = Array.isArray(ratings) ? ratings : [];
                    // Add children to the ratings cache
                    args.dispatch.setRatings(ratingsArray);
                    return ratingsArray;
                }).catch((error) => {
                    console.error('fetchChildRatings error:', error);
                    args.dispatch.setError(error.message || 'Failed to fetch child ratings');
                    return [];
                });
            });
        },

        createRating: (ratingData) => (args) => {
            return apiFetch({
                path: '/shuriken-reviews/v1/ratings',
                method: 'POST',
                data: ratingData,
            }).then((newRating) => {
                args.dispatch.setRating(newRating);

                // Update parent/mirrorable lists if applicable
                if (!newRating.parent_id && !newRating.mirror_of) {
                    args.dispatch.addToParentRatings(newRating);
                }
                if (!newRating.mirror_of) {
                    args.dispatch.addToMirrorableRatings(newRating);
                }

                return newRating;
            }).catch((error) => {
                console.error('createRating error:', error);
                args.dispatch.setError(error.message || 'Failed to create rating');
                throw error;
            });
        },

        updateRating: (ratingId, ratingData) => (args) => {
            if (!isValidId(ratingId)) {
                return Promise.reject(new Error('Invalid rating ID'));
            }

            return apiFetch({
                path: `/shuriken-reviews/v1/ratings/${ratingId}`,
                method: 'PUT',
                data: ratingData,
            }).then((updatedRating) => {
                args.dispatch.setRating(updatedRating);
                args.dispatch.updateRatingInLists(updatedRating);
                return updatedRating;
            }).catch((error) => {
                console.error('updateRating error:', error);
                args.dispatch.setError(error.message || 'Failed to update rating');
                throw error;
            });
        },

        deleteRating: (ratingId) => (args) => {
            if (!isValidId(ratingId)) {
                return Promise.reject(new Error('Invalid rating ID'));
            }

            return apiFetch({
                path: `/shuriken-reviews/v1/ratings/${ratingId}`,
                method: 'DELETE',
            }).then(() => {
                args.dispatch.removeFromLists(ratingId);
                return true;
            }).catch((error) => {
                console.error('deleteRating error:', error);
                args.dispatch.setError(error.message || 'Failed to delete rating');
                throw error;
            });
        },

        /**
         * Batch-fetch multiple ratings by ID in a single API call.
         * Skips IDs already in cache. Returns array of fetched ratings.
         */
        fetchRatingsBatch: (ids) => (args) => {
            if (!ids || !ids.length) {
                return Promise.resolve([]);
            }

            // Filter out IDs already in cache
            const missing = ids.filter((id) => {
                return !args.select.getRating(id);
            });

            if (missing.length === 0) {
                return Promise.resolve([]);
            }

            return apiFetch({
                path: `/shuriken-reviews/v1/ratings/batch?ids=${missing.join(',')}`,
                method: 'GET',
            }).then((ratings) => {
                const arr = Array.isArray(ratings) ? ratings : [];
                args.dispatch.setRatings(arr);
                return arr;
            }).catch((error) => {
                // Batch endpoint may not exist yet (new install / stale cache).
                // Fall back to individual fetches so mirrors still load.
                console.warn('fetchRatingsBatch unavailable, falling back to individual fetches:', error.code || error.message);
                missing.forEach((id) => {
                    args.dispatch(thunks.fetchRating(id));
                });
                return [];
            });
        },

        fetchMirrorsForRating: (ratingId) => (args) => {
            if (!isValidId(ratingId)) {
                return Promise.resolve([]);
            }

            // Check cache first
            const cached = args.select.getMirrorsForRating(ratingId);
            if (cached !== null) {
                return Promise.resolve(cached);
            }

            return dedup(`mirrors-${ratingId}`, () => {
                args.dispatch.setIsLoadingMirrors(ratingId, true);

                return apiFetch({
                    path: `/shuriken-reviews/v1/ratings/${ratingId}/mirrors`,
                    method: 'GET',
                }).then((mirrors) => {
                    const mirrorsArray = Array.isArray(mirrors) ? mirrors : [];
                    // Also cache each mirror in ratingsById
                    args.dispatch.setRatings(mirrorsArray);
                    args.dispatch.setMirrorsForRating(ratingId, mirrorsArray);
                    args.dispatch.setIsLoadingMirrors(ratingId, false);
                    return mirrorsArray;
                }).catch((error) => {
                    // Mirrors endpoint may return invalid JSON on stale cache / new install.
                    // Don't set a store-level error — mirrors are non-critical for the block.
                    // Cache empty array so the request isn't retried every render.
                    console.warn(`fetchMirrorsForRating failed for ${ratingId}:`, error.code || error.message);
                    args.dispatch.setMirrorsForRating(ratingId, []);
                    args.dispatch.setIsLoadingMirrors(ratingId, false);
                    return [];
                });
            });
        },
    };

    // Combine simple actions and thunks
    const allActions = Object.assign({}, actions, thunks);

    // Reducer
    const reducer = (state = DEFAULT_STATE, action) => {
        switch (action.type) {
            case ACTIONS.SET_RATING:
                return {
                    ...state,
                    ratingsById: {
                        ...state.ratingsById,
                        [action.rating.id]: action.rating,
                    },
                };

            case ACTIONS.SET_RATINGS:
                const newRatingsById = { ...state.ratingsById };
                // Safely handle the ratings array
                const ratingsArray = Array.isArray(action.ratings) ? action.ratings : [];
                ratingsArray.forEach((rating) => {
                    if (rating && rating.id) {
                        newRatingsById[rating.id] = rating;
                    }
                });
                return {
                    ...state,
                    ratingsById: newRatingsById,
                };

            case ACTIONS.SET_SEARCH_RESULTS:
                return {
                    ...state,
                    searchResults: Array.isArray(action.results) ? action.results : [],
                };

            case ACTIONS.SET_PARENT_RATINGS:
                return {
                    ...state,
                    parentRatings: Array.isArray(action.ratings) ? action.ratings : [],
                };

            case ACTIONS.SET_MIRRORABLE_RATINGS:
                return {
                    ...state,
                    mirrorableRatings: Array.isArray(action.ratings) ? action.ratings : [],
                };

            case ACTIONS.SET_IS_SEARCHING:
                return {
                    ...state,
                    isSearching: action.isSearching,
                };

            case ACTIONS.SET_IS_LOADING_PARENTS:
                return {
                    ...state,
                    isLoadingParents: action.isLoading,
                };

            case ACTIONS.SET_IS_LOADING_MIRRORABLE:
                return {
                    ...state,
                    isLoadingMirrorable: action.isLoading,
                };

            case ACTIONS.SET_IS_LOADING_RATING:
                return {
                    ...state,
                    isLoadingRating: {
                        ...state.isLoadingRating,
                        [action.ratingId]: action.isLoading,
                    },
                };

            case ACTIONS.SET_PARENTS_FETCHED:
                return {
                    ...state,
                    parentsFetched: action.fetched,
                };

            case ACTIONS.SET_MIRRORABLE_FETCHED:
                return {
                    ...state,
                    mirrorableFetched: action.fetched,
                };

            case ACTIONS.SET_ERROR:
                return {
                    ...state,
                    lastError: action.error,
                };

            case ACTIONS.CLEAR_ERROR:
                return {
                    ...state,
                    lastError: null,
                };

            case ACTIONS.ADD_TO_PARENT_RATINGS:
                return {
                    ...state,
                    parentRatings: [action.rating].concat(state.parentRatings),
                };

            case ACTIONS.ADD_TO_MIRRORABLE_RATINGS:
                return {
                    ...state,
                    mirrorableRatings: [action.rating].concat(state.mirrorableRatings),
                };

            case ACTIONS.UPDATE_RATING_IN_LISTS:
                const rating = action.rating;
                let newParentRatings = state.parentRatings;
                let newMirrorableRatings = state.mirrorableRatings;

                // Handle parent ratings list
                if (!rating.parent_id && !rating.mirror_of) {
                    // Should be in parents list
                    const existsInParents = state.parentRatings.some((r) => {
                        return parseInt(r.id, 10) === parseInt(rating.id, 10);
                    });
                    if (existsInParents) {
                        newParentRatings = state.parentRatings.map((r) => {
                            return parseInt(r.id, 10) === parseInt(rating.id, 10) ? rating : r;
                        });
                    } else {
                        newParentRatings = [rating].concat(state.parentRatings);
                    }
                } else {
                    // Should not be in parents list
                    newParentRatings = state.parentRatings.filter((r) => {
                        return parseInt(r.id, 10) !== parseInt(rating.id, 10);
                    });
                }

                // Handle mirrorable ratings list
                if (!rating.mirror_of) {
                    // Should be in mirrorable list
                    const existsInMirrorable = state.mirrorableRatings.some((r) => {
                        return parseInt(r.id, 10) === parseInt(rating.id, 10);
                    });
                    if (existsInMirrorable) {
                        newMirrorableRatings = state.mirrorableRatings.map((r) => {
                            return parseInt(r.id, 10) === parseInt(rating.id, 10) ? rating : r;
                        });
                    } else {
                        newMirrorableRatings = [rating].concat(state.mirrorableRatings);
                    }
                } else {
                    // Should not be in mirrorable list
                    newMirrorableRatings = state.mirrorableRatings.filter((r) => {
                        return parseInt(r.id, 10) !== parseInt(rating.id, 10);
                    });
                }

                return {
                    ...state,
                    parentRatings: newParentRatings,
                    mirrorableRatings: newMirrorableRatings,
                };

            case ACTIONS.REMOVE_FROM_LISTS:
                const idToRemove = parseInt(action.ratingId, 10);
                const newById = { ...state.ratingsById };
                delete newById[idToRemove];

                return {
                    ...state,
                    ratingsById: newById,
                    parentRatings: state.parentRatings.filter((r) => {
                        return parseInt(r.id, 10) !== idToRemove;
                    }),
                    mirrorableRatings: state.mirrorableRatings.filter((r) => {
                        return parseInt(r.id, 10) !== idToRemove;
                    }),
                    searchResults: state.searchResults.filter((r) => {
                        return parseInt(r.id, 10) !== idToRemove;
                    }),
                };

            case ACTIONS.SET_MIRRORS_FOR_RATING:
                return {
                    ...state,
                    mirrorsById: {
                        ...state.mirrorsById,
                        [action.ratingId]: Array.isArray(action.mirrors) ? action.mirrors : [],
                    },
                };

            case ACTIONS.SET_IS_LOADING_MIRRORS:
                return {
                    ...state,
                    isLoadingMirrors: {
                        ...state.isLoadingMirrors,
                        [action.ratingId]: action.isLoading,
                    },
                };

            case ACTIONS.INVALIDATE_MIRRORS_CACHE:
                const clearedMirrors = { ...state.mirrorsById };
                delete clearedMirrors[action.ratingId];
                return {
                    ...state,
                    mirrorsById: clearedMirrors,
                };

            default:
                return state;
        }
    };

    // Selectors
    const selectors = {
        getRating(state, ratingId) {
            return state.ratingsById[ratingId] || null;
        },
        getRatingsById(state) {
            return state.ratingsById || {};
        },
        getSearchResults(state) {
            return Array.isArray(state.searchResults) ? state.searchResults : [];
        },
        getParentRatings(state) {
            return Array.isArray(state.parentRatings) ? state.parentRatings : [];
        },
        getMirrorableRatings(state) {
            return Array.isArray(state.mirrorableRatings) ? state.mirrorableRatings : [];
        },
        isSearching(state) {
            return state.isSearching || false;
        },
        isLoadingParents(state) {
            return state.isLoadingParents || false;
        },
        isLoadingMirrorable(state) {
            return state.isLoadingMirrorable || false;
        },
        isLoadingRating(state, ratingId) {
            return state.isLoadingRating[ratingId] || false;
        },
        areParentsFetched(state) {
            return state.parentsFetched || false;
        },
        areMirrorableFetched(state) {
            return state.mirrorableFetched || false;
        },
        getLastError(state) {
            return state.lastError;
        },
        getMirrorsForRating(state, ratingId) {
            // Returns null if not yet fetched (distinguishes from empty array)
            if (state.mirrorsById.hasOwnProperty(ratingId)) {
                return state.mirrorsById[ratingId];
            }
            return null;
        },
        isLoadingMirrors(state, ratingId) {
            return (state.isLoadingMirrors && state.isLoadingMirrors[ratingId]) || false;
        },
    };

    // Create and register the store using createReduxStore (supports thunks natively)
    const store = createReduxStore(STORE_NAME, {
        reducer: reducer,
        actions: allActions,
        selectors: selectors,
    });

    register(store);

    // Export store name for use in blocks
    window.SHURIKEN_STORE_NAME = STORE_NAME;

})(window.wp);
