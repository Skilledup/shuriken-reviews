jQuery(document).ready(function($) {
    'use strict';

    /**
     * Frequently reused DOM selectors. Centralized to avoid hardcoding the
     * same class strings across dozens of call sites.
     */
    const SELECTORS = {
        rating: '.shuriken-rating',
        stats:  '.rating-stats'
    };

    /**
     * Timing constants (milliseconds). Centralized so display/refresh timings
     * stay consistent and are tweakable in one place.
     */
    const TIMEOUTS = {
        fade:        300,   // smoothHtml crossfade duration
        buttonPulse: 1500,  // Binary vote button "voted" highlight
        thankYou:    3000,  // Brief thank-you / transient message duration
        feedback:    4000,  // Error/result message display duration
    };

    /**
     * Safely apply WordPress frontend JS filters if wp.hooks is available.
     */
    const applyFilters = (hookName, val, ...args) => {
        if (typeof window.wp?.hooks?.applyFilters === 'function') {
            return window.wp.hooks.applyFilters(hookName, val, ...args);
        }
        return val;
    };

    /**
     * Safely trigger WordPress frontend JS actions if wp.hooks is available.
     */
    const doAction = (hookName, ...args) => {
        if (typeof window.wp?.hooks?.doAction === 'function') {
            window.wp.hooks.doAction(hookName, ...args);
        }
    };

    /**
     * Smoothly crossfade inner HTML to prevent abrupt layout jumps when
     * feedback / stats text changes.
     *
     * Uses a CSS-class-driven opacity fade-out → swap content → fade-in.
     * The transition is defined in the stylesheet (.shuriken-fading),
     * which is more reliable across browsers than inline style transitions.
     */
    $.fn.smoothHtml = function(html) {
        return this.each(function() {
            const $el = $(this);

            // Cancel any pending swap
            let timer = $el.data('shuriken-swap-timer');
            if (timer) {
                clearTimeout(timer);
                $el.data('shuriken-swap-timer', null);
                $el.removeClass('shuriken-fading');
            }

            // If the content is identical, skip
            if ($el.html() === html) return;

            // Fade out via CSS class
            $el.addClass('shuriken-fading');

            // After the CSS fade-out completes, swap content and fade back in
            timer = setTimeout(function() {
                $el.html(html);
                $el.removeClass('shuriken-fading');
                $el.data('shuriken-swap-timer', null);
            }, TIMEOUTS.fade);

            $el.data('shuriken-swap-timer', timer);
        });
    };

    let isFetchingFreshData = false;

    /**
     * Update star display based on average rating
     * The average should be in the DISPLAY scale (e.g., 8 for 8/10 stars)
     * 
     * @param {jQuery} $rating - The rating container element
     * @param {number} scaledAverage - The average in display scale (matches max_stars)
     */
    const updateStars = ($rating, scaledAverage) => {
        $rating.find('.star').each(function() {
            if ($(this).data('value') <= scaledAverage) {
                $(this).addClass('active');
            } else {
                $(this).removeClass('active');
            }
        });
    };

    /**
     * Reset stars to show the current average (reads from scaled-average data attr set by server-rendered HTML)
     */
    const resetStars = ($rating) => {
        const $stats = $rating.find(SELECTORS.stats);
        const scaledAverage = parseFloat($stats.data('scaled-average')) || 0;
        updateStars($rating, scaledAverage);
    };

    /**
     * SSR freshness window (seconds). Pages older than this trigger a stats REST refresh.
     */
    const getSsrFreshThreshold = () => parseInt(shurikenReviews.ssr_fresh_threshold, 10) || 30;

    /**
     * Whether embedded SSR stats are still fresh (uncached / just-rendered page).
     */
    const isSsrFresh = () => {
        const renderedAt = parseInt(shurikenReviews.ssr_rendered_at, 10);
        if (!renderedAt) {
            return false;
        }
        return (Date.now() / 1000 - renderedAt) < getSsrFreshThreshold();
    };

    /**
     * Per-block view data for add-ons (PHP: shuriken_block_view_data filter).
     */
    const getBlockViewData = (ratingId, $rating) => {
        const map = window.shurikenBlockViewData;
        const data = map?.[String(ratingId)] ?? null;
        return applyFilters('shurikenBlockViewData', data, ratingId, $rating);
    };

    /**
     * Attach filtered block view data to a rating element for add-on scripts.
     */
    const initBlockViewData = ($rating) => {
        const ratingId = $rating.data('id');
        if (!ratingId) {
            return null;
        }
        const data = getBlockViewData(ratingId, $rating);
        if (data) {
            $rating.data('shuriken-block-view', data);
        }
        return data;
    };

    /**
     * Collect on-page ratings grouped by voting context for batched stats requests.
     */
    const collectContextGroups = () => {
        const contextGroups = {};

        $(SELECTORS.rating).each(function() {
            const id = $(this).data('id');
            const ctxId = $(this).data('context-id') || '';
            const ctxType = $(this).data('context-type') || '';
            const key = `${ctxId}:${ctxType}`;

            if (!id) {
                return;
            }

            if (!contextGroups[key]) {
                contextGroups[key] = { ids: [], contextId: ctxId, contextType: ctxType };
            }
            if (contextGroups[key].ids.indexOf(id) === -1) {
                contextGroups[key].ids.push(id);
            }
        });

        return contextGroups;
    };

    /**
     * Widgets for one rating inside one voting context.
     * Mirrors share the source rating's data-id, so every copy in the context is included.
     */
    const widgetsForRating = (ratingId, contextId, contextType) => {
        const id = parseInt(ratingId, 10);
        if (!id) {
            return $();
        }

        let selector = `${SELECTORS.rating}[data-id="${id}"]`;
        if (contextId) {
            selector += `[data-context-id="${contextId}"][data-context-type="${contextType}"]`;
        } else {
            selector += ':not([data-context-id])';
        }
        return $(selector);
    };

    /**
     * Average line used by star and numeric widgets.
     */
    const formatAverage = (scaledAverage, maxStars, totalVotes) => {
        return shurikenReviews.i18n.averageRating
            .replace('%1$s', scaledAverage)
            .replace('%2$s', maxStars)
            .replace('%3$s', totalVotes);
    };

    /**
     * Paint one widget from a stats payload.
     *
     * @param {jQuery} $rating Widget root.
     * @param {Object} stats   average, display_average, total_votes, total_rating.
     * @param {string} textMode `html`, `smooth`, or `skip` for the stats sentence.
     */
    const paintRating = ($rating, stats, textMode) => {
        const ratingType = $rating.data('rating-type') || 'stars';
        const $statsEl = $rating.find(SELECTORS.stats);
        const maxStars = parseInt($rating.data('max-stars'), 10) || 5;
        const totalVotes = parseInt(stats.total_votes, 10) || 0;
        let totalRating = parseFloat(stats.total_rating);
        if (Number.isNaN(totalRating)) {
            totalRating = Math.round(totalVotes * (parseFloat(stats.average) || 0));
        }
        const likeCount = Math.round(totalRating);
        const scaledAverage = parseFloat(stats.display_average);
        const displayAverage = Number.isNaN(scaledAverage) ? (parseFloat(stats.average) || 0) : scaledAverage;

        if ($statsEl.length) {
            if (stats.average !== undefined && stats.average !== null) {
                $statsEl.data('average', stats.average);
            }
            $statsEl.data('scaled-average', displayAverage);
        }

        if (ratingType === 'like_dislike') {
            const pct = totalVotes > 0 ? Math.round((likeCount / totalVotes) * 100) : 0;
            $rating.find('.shuriken-like-count').text(likeCount);
            $rating.find('.shuriken-dislike-count').text(totalVotes - likeCount);
            $rating.find('.shuriken-approval-pct').text(`${pct}%`);
            const $voteSummary = $rating.find('.shuriken-vote-summary');
            if ($voteSummary.length) {
                $voteSummary.text(`(${totalVotes} ${shurikenReviews.i18n.votes})`);
            }
        } else if (ratingType === 'approval') {
            $rating.find('.shuriken-upvote-count').text(totalVotes);
        } else if (ratingType === 'numeric') {
            const text = formatAverage(displayAverage, maxStars, totalVotes);
            if (textMode === 'smooth') {
                $statsEl.smoothHtml(text);
            } else if (textMode !== 'skip') {
                $statsEl.html(text);
            }
            $rating.find('.shuriken-numeric-value').text(Math.round(displayAverage * 10) / 10);
            $rating.find('.shuriken-slider-value').text(Math.max(1, Math.round(displayAverage)));
        } else {
            const text = formatAverage(displayAverage, maxStars, totalVotes);
            if (textMode === 'smooth') {
                $statsEl.smoothHtml(text);
            } else if (textMode !== 'skip') {
                $statsEl.html(text);
            }
            updateStars($rating, displayAverage);
        }

        $rating.removeClass('shuriken-refreshing');
    };

    /**
     * Map a vote response onto the stats shape used by paintRating.
     */
    const voteStats = (data, isParent) => {
        if (isParent) {
            return {
                average: data.parent_average,
                display_average: data.parent_scaled_average ?? data.parent_average,
                total_votes: data.parent_total_votes,
                total_rating: data.parent_total_rating,
            };
        }
        return {
            average: data.new_average,
            display_average: data.new_scaled_average ?? data.new_average,
            total_votes: data.new_total_votes,
            total_rating: data.new_total_rating,
        };
    };

    /**
     * Update every same-context copy of the voted rating and its parent.
     * The clicked star/numeric widget keeps its thank-you sentence.
     */
    const syncVoteWidgets = ($clicked, data) => {
        const contextId = $clicked.data('context-id') || '';
        const contextType = $clicked.data('context-type') || '';
        const ratingType = $clicked.data('rating-type') || 'stars';
        const keepsThankYou = ratingType !== 'like_dislike' && ratingType !== 'approval';

        widgetsForRating($clicked.data('id'), contextId, contextType).each(function() {
            const $match = $(this);
            const skipText = keepsThankYou && $match.is($clicked);
            paintRating($match, voteStats(data, false), skipText ? 'skip' : 'smooth');
        });

        if (!data.parent_id) {
            return;
        }

        widgetsForRating(data.parent_id, contextId, contextType).each(function() {
            paintRating($(this), voteStats(data, true), 'smooth');
        });
    };

    /**
     * Apply batched stats REST response to matching rating widgets.
     */
    const applyStats = (statsResponse, ctxId, ctxType) => {
        $.each(statsResponse, function(ratingId, stats) {
            widgetsForRating(ratingId, ctxId, ctxType).each(function() {
                paintRating($(this), stats, 'html');
            });
        });
    };

    /**
     * Fetch fresh rating stats from REST (batched per context group).
     */
    const fetchFreshStats = (contextGroups) => {
        const groupKeys = Object.keys(contextGroups);
        if (groupKeys.length === 0) {
            isFetchingFreshData = false;
            return;
        }

        let completedRequests = 0;
        const totalRequests = groupKeys.length;

        const onRequestComplete = () => {
            completedRequests++;
            if (completedRequests >= totalRequests) {
                isFetchingFreshData = false;
                $(SELECTORS.rating + '.shuriken-refreshing').removeClass('shuriken-refreshing');
            }
        };

        $.each(contextGroups, function(key, group) {
            const requestData = { ids: group.ids.join(',') };
            if (group.contextId) {
                requestData.context_id = group.contextId;
                requestData.context_type = group.contextType;
            }

            $.ajax({
                url: shurikenReviews.rest_url + 'shuriken-reviews/v1/ratings/stats',
                type: 'GET',
                cache: false,
                data: requestData,
                success: function(statsResponse) {
                    applyStats(statsResponse, group.contextId, group.contextType);
                },
                error: function(xhr, status, error) {
                    console.error('Failed to fetch fresh rating stats:', error);
                    $(SELECTORS.rating).removeClass('shuriken-refreshing');
                },
                complete: onRequestComplete
            });
        });
    };

    /**
     * Fetch a fresh nonce (always — required for CDN / full-page cache compatibility).
     */
    const fetchFreshNonce = () => $.ajax({
        url: shurikenReviews.rest_url + 'shuriken-reviews/v1/nonce',
        type: 'GET',
        cache: false,
    });

    /**
     * Smart client fetch: always refresh nonce; refresh stats only when SSR is stale.
     *
     * @param {Object} options
     * @param {boolean} [options.forceStatsRefresh=false] Force stats REST (bfcache restore).
     */
    const refreshClientData = (options = {}) => {
        if (isFetchingFreshData) {
            return;
        }

        const contextGroups = collectContextGroups();
        if (Object.keys(contextGroups).length === 0) {
            return;
        }

        const forceStatsRefresh = options.forceStatsRefresh === true;
        const shouldRefreshStats = forceStatsRefresh || !isSsrFresh();

        isFetchingFreshData = true;

        if (shouldRefreshStats) {
            $(SELECTORS.rating).addClass('shuriken-refreshing');
        }

        fetchFreshNonce()
            .done(function(nonceResponse) {
                if (nonceResponse?.nonce) {
                    shurikenReviews.nonce = nonceResponse.nonce;
                    if (nonceResponse.logged_in !== undefined) {
                        shurikenReviews.logged_in = nonceResponse.logged_in;
                    }
                    if (nonceResponse.allow_guest_voting !== undefined) {
                        shurikenReviews.allow_guest_voting = nonceResponse.allow_guest_voting;
                    }
                }

                if (shouldRefreshStats) {
                    fetchFreshStats(contextGroups);
                } else {
                    isFetchingFreshData = false;
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Failed to fetch fresh nonce:', error);
                isFetchingFreshData = false;
                $(SELECTORS.rating).removeClass('shuriken-refreshing');
            });
    };

    
    /**
     * Update the --fill-pct custom property on a range slider so the
     * track pseudo-elements can paint a filled-portion gradient.
     * Works cross-browser (Chromium, Firefox, Safari).
     */
    const updateSliderFill = ($slider) => {
        const min = parseFloat($slider.attr('min')) || 0;
        const max = parseFloat($slider.attr('max')) || 100;
        const val = parseFloat($slider.val()) || 0;
        const pct = ((val - min) / (max - min)) * 100;
        $slider[0].style.setProperty('--fill-pct', `${pct}%`);
    };

    // Run on initial page load and after WP Interactivity Router client-side navigation.
    // Uses data-shuriken-init to skip already-initialized elements so re-runs are safe.
    const shurikenInit = (options = {}) => {
        $(SELECTORS.rating + ':not([data-shuriken-init])').each(function() {
            const $rating = $(this);
            $rating.attr('data-shuriken-init', '1');

            initBlockViewData($rating);

            const ratingType = $rating.data('rating-type') || 'stars';
            // Skip non-star types for star initialization
            if (ratingType === 'like_dislike' || ratingType === 'approval' || ratingType === 'numeric') {
                return;
            }

            const $stats = $rating.find(SELECTORS.stats);
            const maxStars = parseInt($rating.data('max-stars')) || 5;

            // Use scaled-average if available, otherwise calculate from normalized average
            let scaledAverage = parseFloat($stats.data('scaled-average'));
            if (isNaN(scaledAverage)) {
                const normalizedAverage = parseFloat($stats.data('average')) || 0;
                scaledAverage = (normalizedAverage / 5) * maxStars;
            }

            updateStars($rating, scaledAverage);
        });

        // Paint the filled track for any new sliders (guard prevents redundant repaints)
        $('.shuriken-slider:not([data-shuriken-fill-init])').each(function() {
            $(this).attr('data-shuriken-fill-init', '1');
            updateSliderFill($(this));
        });

        refreshClientData(options);
    };

    shurikenInit();

    // Re-fetch stats after bfcache restore (nonce and SSR stats may be stale).
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            shurikenInit({ forceStatsRefresh: true });
        }
    });

    // Only add hover effects to votable ratings (not display-only)
    $(document).on('mouseenter', '.shuriken-rating:not(.display-only) .star', function() {
        const $rating = $(this).closest('.shuriken-rating');
        $rating.data('hovering', true);
        const value = $(this).data('value');
        $(this).parent().find('.star').each(function() {
            if ($(this).data('value') <= value) {
                $(this).addClass('active');
            } else {
                $(this).removeClass('active');
            }
        });
    });
    $(document).on('mouseleave', '.shuriken-rating:not(.display-only) .star', function() {
        const $rating = $(this).closest('.shuriken-rating');
        $rating.data('hovering', false);
        resetStars($rating);
    });

    /**
     * Submit a rating vote
     */
    const submitRating = ($rating, value, retryCount) => {
        retryCount = retryCount || 0;
        
        const $stars = $rating.find('.stars');
        const ratingId = $rating.data('id');
        const maxStars = parseInt($rating.data('max-stars')) || 5;
        const contextId = $rating.data('context-id') || '';
        const contextType = $rating.data('context-type') || '';
        let originalText = $rating.find(SELECTORS.stats).html();
        $rating.addClass('shuriken-loading');

        let postData = {
            action: 'submit_rating',
            rating_id: ratingId,
            rating_value: value,
            max_stars: maxStars,
            nonce: shurikenReviews.nonce
        };
        if (contextId && contextType) {
            postData.context_id = contextId;
            postData.context_type = contextType;
        }

        // Apply filters
        postData = applyFilters('shurikenVoteRequest', postData, $rating, value);
        
        $.ajax({
            url: shurikenReviews.ajaxurl,
            type: 'POST',
            data: postData,
            success: function(response) {
                if (response.success) {
                    doAction('shurikenVoteSuccess', response, $rating, value);
                    // Show translated success feedback
                    $rating.find(SELECTORS.stats).smoothHtml(shurikenReviews.i18n.thankYou);
                    // Highlight selected stars
                    $stars.find('.star').each(function() {
                        if ($(this).data('value') <= value) {
                            $(this).addClass('active');
                        } else {
                            $(this).removeClass('active');
                        }
                    });

                    const displayAverage = response.data.new_scaled_average ?? response.data.new_average;
                    const displayMaxStars = response.data.max_stars || parseInt($rating.data('max-stars'), 10) || 5;
                    originalText = formatAverage(displayAverage, displayMaxStars, response.data.new_total_votes);
                    syncVoteWidgets($rating, response.data);
                } else {
                    // Check if it's a nonce error and we haven't retried yet
                    if (response.data && typeof response.data === 'string' && 
                        response.data.toLowerCase().indexOf('nonce') !== -1 && retryCount === 0) {
                        // Keep stars disabled during nonce refresh + retry
                        $stars.css('pointer-events', 'none');
                        // Fetch fresh nonce and retry (public endpoint - no auth needed)
                        $.ajax({
                            url: shurikenReviews.rest_url + 'shuriken-reviews/v1/nonce',
                            type: 'GET',
                            cache: false,
                            success: function(nonceResponse) {
                                if (nonceResponse?.nonce) {
                                    shurikenReviews.nonce = nonceResponse.nonce;
                                    // Retry the rating submission
                                    submitRating($rating, value, 1);
                                } else {
                                    $rating.find(SELECTORS.stats).smoothHtml(
                                        shurikenReviews.i18n.error.replace('%s', response.data)
                                    );
                                    setTimeout(function() {
                                        $rating.find(SELECTORS.stats).smoothHtml(originalText);
                                        $stars.css('pointer-events', 'auto');
                                    }, TIMEOUTS.feedback);
                                }
                            },
                            error: function() {
                                $rating.find(SELECTORS.stats).smoothHtml(
                                    shurikenReviews.i18n.error.replace('%s', response.data)
                                );
                                setTimeout(function() {
                                    $rating.find(SELECTORS.stats).smoothHtml(originalText);
                                    $stars.css('pointer-events', 'auto');
                                }, TIMEOUTS.feedback);
                            }
                        });
                        return; // Don't re-enable stars yet, wait for retry
                    }
                    
                    $rating.find(SELECTORS.stats).smoothHtml(
                        shurikenReviews.i18n.error.replace('%s', response.data)
                    );
                }
                setTimeout(function() {
                    $rating.find(SELECTORS.stats).smoothHtml(originalText);
                    updateStars($rating, parseFloat($rating.find(SELECTORS.stats).data('scaled-average')) || 0);
                }, TIMEOUTS.thankYou);
            },
            error: function(xhr, status, error) {
                console.error('Rating submission error:', error);
                
                // Check if it's a nonce error and we haven't retried yet
                if (xhr.responseJSON?.data &&
                    typeof xhr.responseJSON.data === 'string' &&
                    xhr.responseJSON.data.toLowerCase().indexOf('nonce') !== -1 &&
                    retryCount === 0) {
                    // Keep stars disabled during nonce refresh + retry
                    $stars.css('pointer-events', 'none');
                    // Fetch fresh nonce and retry (public endpoint - no auth needed)
                    $.ajax({
                        url: shurikenReviews.rest_url + 'shuriken-reviews/v1/nonce',
                        type: 'GET',
                        cache: false,
                        success: function(nonceResponse) {
                            if (nonceResponse?.nonce) {
                                shurikenReviews.nonce = nonceResponse.nonce;
                                // Retry the rating submission
                                submitRating($rating, value, 1);
                            } else {
                                if (xhr.responseJSON?.data) {
                                    $rating.find(SELECTORS.stats).smoothHtml(
                                        shurikenReviews.i18n.error.replace('%s', xhr.responseJSON.data)
                                    );
                                } else {
                                    $rating.find(SELECTORS.stats).smoothHtml(shurikenReviews.i18n.genericError);
                                }
                                setTimeout(function() {
                                    $rating.find(SELECTORS.stats).smoothHtml(originalText);
                                    $stars.css('pointer-events', 'auto');
                                }, TIMEOUTS.feedback);
                            }
                        },
                        error: function() {
                            if (xhr.responseJSON?.data) {
                                $rating.find(SELECTORS.stats).smoothHtml(
                                    shurikenReviews.i18n.error.replace('%s', xhr.responseJSON.data)
                                );
                            } else {
                                $rating.find(SELECTORS.stats).smoothHtml(shurikenReviews.i18n.genericError);
                            }
                            setTimeout(function() {
                                $rating.find(SELECTORS.stats).smoothHtml(originalText);
                                $stars.css('pointer-events', 'auto');
                            }, TIMEOUTS.feedback);
                        }
                    });
                    return; // Don't re-enable stars yet, wait for retry
                }
                
                if (xhr.responseJSON?.data) {
                    $rating.find(SELECTORS.stats).smoothHtml(
                        shurikenReviews.i18n.error.replace('%s', xhr.responseJSON.data)
                    );
                } else {
                    $rating.find(SELECTORS.stats).smoothHtml(shurikenReviews.i18n.genericError);
                }
                setTimeout(function() {
                    $rating.find(SELECTORS.stats).smoothHtml(originalText);
                }, TIMEOUTS.feedback);
            },
            complete: function() {
                $rating.removeClass('shuriken-loading');
                $stars.css('pointer-events', 'auto');
            }
        });
    };

    // Only allow clicking on votable ratings (not display-only)
    $(document).on('click', '.shuriken-rating:not(.display-only) .star', function(e) {
        e.preventDefault();
        const $rating = $(this).closest('.shuriken-rating');
        const $stars = $rating.find('.stars');
        const value = $(this).data('value');

        // Check if voting is allowed (logged in or guest voting enabled)
        const isLoggedIn = shurikenReviews.logged_in === true || shurikenReviews.logged_in === "1" || shurikenReviews.logged_in === 1;
        const guestVotingAllowed = shurikenReviews.allow_guest_voting === true || shurikenReviews.allow_guest_voting === "1" || shurikenReviews.allow_guest_voting === 1;
        
        if (!isLoggedIn && !guestVotingAllowed) {
            const loginUrl = `${shurikenReviews.login_url}?redirect_to=${encodeURIComponent(window.location.href)}`;
            
            // Add login message using translated string
            if (!$rating.find('.login-message').length) {
                $rating.find(SELECTORS.stats).after(
                    `<div class="login-message">[${shurikenReviews.i18n.pleaseLogin.replace('%s', loginUrl)}]</div>`
                );
            }

            // Show login message
            $rating.find('.login-message').show();

            return;
        }

        // Display user's vote on stars
        $stars.find('.star').each(function() {
            if ($(this).data('value') <= value) {
                $(this).addClass('active');
            } else {
                $(this).removeClass('active');
            }
        });
        
        // Disable stars while processing
        $stars.css('pointer-events', 'none');

        // Submit the rating
        submitRating($rating, value, 0);
    });

    // Only allow keyboard navigation on votable ratings (not display-only)
    $(document).on('keydown', '.shuriken-rating:not(.display-only) .star', function(e) {
        // Handle Enter or Space key press
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).click();
        }
    });

    /**
     * Submit a like/dislike or approval vote
     */
    const submitBinaryRating = ($rating, value, retryCount) => {
        retryCount = retryCount || 0;

        const ratingId = $rating.data('id');
        const ratingType = $rating.data('rating-type');
        const contextId = $rating.data('context-id') || '';
        const contextType = $rating.data('context-type') || '';
        const $feedback = $rating.find('.shuriken-feedback');

        const showFeedback = (msg, duration) => {
            $feedback.smoothHtml(msg);
            setTimeout(() => { $feedback.smoothHtml(''); }, duration || TIMEOUTS.thankYou);
        };

        const showError = (response, xhr) => {
            let msg;
            if (response && typeof response.data === 'string') {
                msg = shurikenReviews.i18n.error.replace('%s', response.data);
            } else if (typeof xhr?.responseJSON?.data === 'string') {
                msg = shurikenReviews.i18n.error.replace('%s', xhr.responseJSON.data);
            } else {
                msg = shurikenReviews.i18n.genericError;
            }
            showFeedback(msg, TIMEOUTS.feedback);
        };

        // Pulse the widget while AJAX is in-flight
        $rating.addClass('shuriken-loading');

        let postData = {
            action: 'submit_rating',
            rating_id: ratingId,
            rating_value: value,
            max_stars: 1,
            nonce: shurikenReviews.nonce
        };
        if (contextId && contextType) {
            postData.context_id = contextId;
            postData.context_type = contextType;
        }

        // Apply filters
        postData = applyFilters('shurikenVoteRequest', postData, $rating, value);

        $.ajax({
            url: shurikenReviews.ajaxurl,
            type: 'POST',
            data: postData,
            success: function(response) {
                if (response.success) {
                    doAction('shurikenVoteSuccess', response, $rating, value);
                    syncVoteWidgets($rating, response.data);

                    // Brief visual feedback on the button
                    $rating.find('.shuriken-btn').addClass('shuriken-voted');
                    setTimeout(function() {
                        $rating.find('.shuriken-btn').removeClass('shuriken-voted');
                    }, TIMEOUTS.buttonPulse);

                    // Show thank-you message then clear
                    showFeedback(shurikenReviews.i18n.thankYou, TIMEOUTS.thankYou);
                } else {
                    // Handle nonce retry for binary ratings
                    if (response.data && typeof response.data === 'string' &&
                        response.data.toLowerCase().indexOf('nonce') !== -1 && retryCount === 0) {
                        $.ajax({
                            url: shurikenReviews.rest_url + 'shuriken-reviews/v1/nonce',
                            type: 'GET',
                            cache: false,
                            success: function(nonceResponse) {
                                if (nonceResponse?.nonce) {
                                    shurikenReviews.nonce = nonceResponse.nonce;
                                    submitBinaryRating($rating, value, 1);
                                } else {
                                    showError(response, null);
                                }
                            },
                            error: function() {
                                showError(response, null);
                            }
                        });
                        return;
                    }
                    showError(response, null);
                }
            },
            error: function(xhr, status, error) {
                // Handle nonce retry for AJAX-level errors
                if (xhr.responseJSON?.data &&
                    typeof xhr.responseJSON.data === 'string' &&
                    xhr.responseJSON.data.toLowerCase().indexOf('nonce') !== -1 &&
                    retryCount === 0) {
                    $.ajax({
                        url: shurikenReviews.rest_url + 'shuriken-reviews/v1/nonce',
                        type: 'GET',
                        cache: false,
                        success: function(nonceResponse) {
                            if (nonceResponse?.nonce) {
                                shurikenReviews.nonce = nonceResponse.nonce;
                                submitBinaryRating($rating, value, 1);
                            } else {
                                showError(null, xhr);
                            }
                        },
                        error: function() {
                            showError(null, xhr);
                        }
                    });
                    return;
                }
                showError(null, xhr);
            },
            complete: function() {
                $rating.removeClass('shuriken-loading');
                $rating.find('.shuriken-btn').css('pointer-events', 'auto');
            }
        });
    };

    // Like/Dislike button click handler
    $(document).on('click', '.shuriken-rating:not(.display-only) .shuriken-like-btn, .shuriken-rating:not(.display-only) .shuriken-dislike-btn', function(e) {
        e.preventDefault();
        const $rating = $(this).closest('.shuriken-rating');
        const value = parseInt($(this).data('value'));

        // Check if voting is allowed
        const isLoggedIn = shurikenReviews.logged_in === true || shurikenReviews.logged_in === "1" || shurikenReviews.logged_in === 1;
        const guestVotingAllowed = shurikenReviews.allow_guest_voting === true || shurikenReviews.allow_guest_voting === "1" || shurikenReviews.allow_guest_voting === 1;
        
        if (!isLoggedIn && !guestVotingAllowed) {
            const loginUrl = `${shurikenReviews.login_url}?redirect_to=${encodeURIComponent(window.location.href)}`;
            if (!$rating.find('.login-message').length) {
                $rating.find('.shuriken-like-dislike').after(
                    `<div class="login-message">[${shurikenReviews.i18n.pleaseLogin.replace('%s', loginUrl)}]</div>`
                );
            }
            $rating.find('.login-message').show();
            return;
        }

        // Disable buttons while processing
        $rating.find('.shuriken-btn').css('pointer-events', 'none');
        
        submitBinaryRating($rating, value, 0);
    });

    // Numeric slider: live value update on input + fill track
    $(document).on('input', '.shuriken-rating:not(.display-only) .shuriken-slider', function() {
        const $s = $(this);
        $s.closest('.shuriken-numeric').find('.shuriken-slider-value').text($s.val());
        updateSliderFill($s);
    });

    // Numeric slider: submit button click handler
    $(document).on('click', '.shuriken-rating:not(.display-only) .shuriken-slider-submit', function(e) {
        e.preventDefault();
        const $rating = $(this).closest('.shuriken-rating');
        const $slider = $rating.find('.shuriken-slider');
        const value = parseInt($slider.val());

        // Check if voting is allowed
        const isLoggedIn = shurikenReviews.logged_in === true || shurikenReviews.logged_in === "1" || shurikenReviews.logged_in === 1;
        const guestVotingAllowed = shurikenReviews.allow_guest_voting === true || shurikenReviews.allow_guest_voting === "1" || shurikenReviews.allow_guest_voting === 1;
        
        if (!isLoggedIn && !guestVotingAllowed) {
            const loginUrl = `${shurikenReviews.login_url}?redirect_to=${encodeURIComponent(window.location.href)}`;
            if (!$rating.find('.login-message').length) {
                $rating.find('.shuriken-numeric').after(
                    `<div class="login-message">[${shurikenReviews.i18n.pleaseLogin.replace('%s', loginUrl)}]</div>`
                );
            }
            $rating.find('.login-message').show();
            return;
        }

        // Disable slider and button while processing
        $slider.prop('disabled', true);
        $(this).prop('disabled', true);
        
        submitRating($rating, value, 0);
        
        // Re-enable after a delay
        const $btn = $(this);
        setTimeout(function() {
            $slider.prop('disabled', false);
            $btn.prop('disabled', false);
        }, TIMEOUTS.feedback);
    });

    // Approval (upvote) button click handler
    $(document).on('click', '.shuriken-rating:not(.display-only) .shuriken-upvote-btn', function(e) {
        e.preventDefault();
        const $rating = $(this).closest('.shuriken-rating');

        // Check if voting is allowed
        const isLoggedIn = shurikenReviews.logged_in === true || shurikenReviews.logged_in === "1" || shurikenReviews.logged_in === 1;
        const guestVotingAllowed = shurikenReviews.allow_guest_voting === true || shurikenReviews.allow_guest_voting === "1" || shurikenReviews.allow_guest_voting === 1;
        
        if (!isLoggedIn && !guestVotingAllowed) {
            const loginUrl = `${shurikenReviews.login_url}?redirect_to=${encodeURIComponent(window.location.href)}`;
            if (!$rating.find('.login-message').length) {
                $rating.find('.shuriken-approval').after(
                    `<div class="login-message">[${shurikenReviews.i18n.pleaseLogin.replace('%s', loginUrl)}]</div>`
                );
            }
            $rating.find('.login-message').show();
            return;
        }

        // Disable button while processing
        $rating.find('.shuriken-btn').css('pointer-events', 'none');
        
        submitBinaryRating($rating, 1, 0);
    });

    // Re-initialize after WP Interactivity Router client-side navigation.
    document.addEventListener('wp-js-interactivity:navigated', function() {
        shurikenInit();
    });
});
