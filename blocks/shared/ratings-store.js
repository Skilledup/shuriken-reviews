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
        // Loading states
        isSearching: false,
        isLoadingParents: false,
        isLoadingMirrorable: false,
        isLoadingRating: {},
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
    };

    // Thunks - async action creators that use dispatch
    // WordPress @wordpress/data thunks receive { select, dispatch, registry }
    const thunks = {
        fetchRating: function(ratingId) {
            return function(args) {
                if (!ratingId) {
                    return Promise.resolve(null);
                }

                // Check cache first using registry's select
                var cached = args.select.getRating(ratingId);
                if (cached) {
                    return Promise.resolve(cached);
                }

                args.dispatch.setIsLoadingRating(ratingId, true);

                return apiFetch({
                    path: '/shuriken-reviews/v1/ratings/' + ratingId,
                    method: 'GET',
                }).then(function(rating) {
                    args.dispatch.setRating(rating);
                    args.dispatch.setIsLoadingRating(ratingId, false);
                    return rating;
                }).catch(function(error) {
                    console.error('fetchRating error:', error);
                    args.dispatch.setError(error.message || 'Failed to fetch rating');
                    args.dispatch.setIsLoadingRating(ratingId, false);
                    return null;
                });
            };
        },

        searchRatings: function(searchTerm, type, limit) {
            type = type || 'all';
            limit = limit || 20;

            return function(args) {
                // Don't make API call for empty search term
                if (!searchTerm || searchTerm.trim().length === 0) {
                    args.dispatch.setSearchResults([]);
                    return Promise.resolve([]);
                }

                args.dispatch.setIsSearching(true);
                args.dispatch.clearError();

                var path = '/shuriken-reviews/v1/ratings/search?q=' + encodeURIComponent(searchTerm.trim()) + '&type=' + type + '&limit=' + limit;
                
                return apiFetch({
                    path: path,
                    method: 'GET',
                }).then(function(results) {
                    var resultsArray = Array.isArray(results) ? results : [];
                    args.dispatch.setRatings(resultsArray);
                    args.dispatch.setSearchResults(resultsArray);
                    args.dispatch.setIsSearching(false);
                    return resultsArray;
                }).catch(function(error) {
                    console.error('searchRatings error:', error);
                    args.dispatch.setError(error.message || 'Search failed');
                    args.dispatch.setSearchResults([]);
                    args.dispatch.setIsSearching(false);
                    return [];
                });
            };
        },

        fetchParentRatings: function() {
            return function(args) {
                // Check if already fetched
                if (args.select.areParentsFetched()) {
                    return Promise.resolve(args.select.getParentRatings());
                }

                args.dispatch.setIsLoadingParents(true);

                return apiFetch({
                    path: '/shuriken-reviews/v1/ratings/parents',
                    method: 'GET',
                }).then(function(ratings) {
                    var ratingsArray = Array.isArray(ratings) ? ratings : [];
                    args.dispatch.setParentRatings(ratingsArray);
                    args.dispatch.setRatings(ratingsArray);
                    args.dispatch.setParentsFetched(true);
                    args.dispatch.setIsLoadingParents(false);
                    return ratingsArray;
                }).catch(function(error) {
                    console.error('fetchParentRatings error:', error);
                    args.dispatch.setError(error.message || 'Failed to fetch parent ratings');
                    args.dispatch.setIsLoadingParents(false);
                    return [];
                });
            };
        },

        fetchMirrorableRatings: function() {
            return function(args) {
                // Check if already fetched
                if (args.select.areMirrorableFetched()) {
                    return Promise.resolve(args.select.getMirrorableRatings());
                }

                args.dispatch.setIsLoadingMirrorable(true);

                return apiFetch({
                    path: '/shuriken-reviews/v1/ratings/mirrorable',
                    method: 'GET',
                }).then(function(ratings) {
                    var ratingsArray = Array.isArray(ratings) ? ratings : [];
                    args.dispatch.setMirrorableRatings(ratingsArray);
                    args.dispatch.setRatings(ratingsArray);
                    args.dispatch.setMirrorableFetched(true);
                    args.dispatch.setIsLoadingMirrorable(false);
                    return ratingsArray;
                }).catch(function(error) {
                    console.error('fetchMirrorableRatings error:', error);
                    args.dispatch.setError(error.message || 'Failed to fetch mirrorable ratings');
                    args.dispatch.setIsLoadingMirrorable(false);
                    return [];
                });
            };
        },

        fetchChildRatings: function(parentId) {
            return function(args) {
                if (!parentId) {
                    return Promise.resolve([]);
                }

                return apiFetch({
                    path: '/shuriken-reviews/v1/ratings/' + parentId + '/children',
                    method: 'GET',
                }).then(function(ratings) {
                    var ratingsArray = Array.isArray(ratings) ? ratings : [];
                    // Add children to the ratings cache
                    args.dispatch.setRatings(ratingsArray);
                    return ratingsArray;
                }).catch(function(error) {
                    console.error('fetchChildRatings error:', error);
                    args.dispatch.setError(error.message || 'Failed to fetch child ratings');
                    return [];
                });
            };
        },

        createRating: function(ratingData) {
            return function(args) {
                return apiFetch({
                    path: '/shuriken-reviews/v1/ratings',
                    method: 'POST',
                    data: ratingData,
                }).then(function(newRating) {
                    args.dispatch.setRating(newRating);

                    // Update parent/mirrorable lists if applicable
                    if (!newRating.parent_id && !newRating.mirror_of) {
                        args.dispatch.addToParentRatings(newRating);
                    }
                    if (!newRating.mirror_of) {
                        args.dispatch.addToMirrorableRatings(newRating);
                    }

                    return newRating;
                }).catch(function(error) {
                    console.error('createRating error:', error);
                    args.dispatch.setError(error.message || 'Failed to create rating');
                    throw error;
                });
            };
        },

        updateRating: function(ratingId, ratingData) {
            return function(args) {
                return apiFetch({
                    path: '/shuriken-reviews/v1/ratings/' + ratingId,
                    method: 'PUT',
                    data: ratingData,
                }).then(function(updatedRating) {
                    args.dispatch.setRating(updatedRating);
                    args.dispatch.updateRatingInLists(updatedRating);
                    return updatedRating;
                }).catch(function(error) {
                    console.error('updateRating error:', error);
                    args.dispatch.setError(error.message || 'Failed to update rating');
                    throw error;
                });
            };
        },

        deleteRating: function(ratingId) {
            return function(args) {
                return apiFetch({
                    path: '/shuriken-reviews/v1/ratings/' + ratingId,
                    method: 'DELETE',
                }).then(function() {
                    args.dispatch.removeFromLists(ratingId);
                    return true;
                }).catch(function(error) {
                    console.error('deleteRating error:', error);
                    args.dispatch.setError(error.message || 'Failed to delete rating');
                    throw error;
                });
            };
        },
    };

    // Combine simple actions and thunks
    var allActions = Object.assign({}, actions, thunks);

    // Reducer
    function reducer(state = DEFAULT_STATE, action) {
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
                ratingsArray.forEach(function(rating) {
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
                    const existsInParents = state.parentRatings.some(function(r) {
                        return parseInt(r.id, 10) === parseInt(rating.id, 10);
                    });
                    if (existsInParents) {
                        newParentRatings = state.parentRatings.map(function(r) {
                            return parseInt(r.id, 10) === parseInt(rating.id, 10) ? rating : r;
                        });
                    } else {
                        newParentRatings = [rating].concat(state.parentRatings);
                    }
                } else {
                    // Should not be in parents list
                    newParentRatings = state.parentRatings.filter(function(r) {
                        return parseInt(r.id, 10) !== parseInt(rating.id, 10);
                    });
                }

                // Handle mirrorable ratings list
                if (!rating.mirror_of) {
                    // Should be in mirrorable list
                    const existsInMirrorable = state.mirrorableRatings.some(function(r) {
                        return parseInt(r.id, 10) === parseInt(rating.id, 10);
                    });
                    if (existsInMirrorable) {
                        newMirrorableRatings = state.mirrorableRatings.map(function(r) {
                            return parseInt(r.id, 10) === parseInt(rating.id, 10) ? rating : r;
                        });
                    } else {
                        newMirrorableRatings = [rating].concat(state.mirrorableRatings);
                    }
                } else {
                    // Should not be in mirrorable list
                    newMirrorableRatings = state.mirrorableRatings.filter(function(r) {
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
                    parentRatings: state.parentRatings.filter(function(r) {
                        return parseInt(r.id, 10) !== idToRemove;
                    }),
                    mirrorableRatings: state.mirrorableRatings.filter(function(r) {
                        return parseInt(r.id, 10) !== idToRemove;
                    }),
                    searchResults: state.searchResults.filter(function(r) {
                        return parseInt(r.id, 10) !== idToRemove;
                    }),
                };

            default:
                return state;
        }
    }

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
    };

    // Create and register the store using createReduxStore (supports thunks natively)
    var store = createReduxStore(STORE_NAME, {
        reducer: reducer,
        actions: allActions,
        selectors: selectors,
    });

    register(store);

    // Export store name for use in blocks
    window.SHURIKEN_STORE_NAME = STORE_NAME;

})(window.wp);
