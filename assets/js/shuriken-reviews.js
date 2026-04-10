jQuery(document).ready(function($) {
    'use strict';

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
            var $el = $(this);

            // Cancel any pending swap
            var timer = $el.data('shuriken-swap-timer');
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
            }, 300);

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
    function updateStars($rating, scaledAverage) {
        $rating.find('.star').each(function() {
            if ($(this).data('value') <= scaledAverage) {
                $(this).addClass('active');
            } else {
                $(this).removeClass('active');
            }
        });
    }

    /**
     * Reset stars to show the current average (reads from scaled-average data attr set by server-rendered HTML)
     */
    function resetStars($rating) {
        const $stats = $rating.find('.rating-stats');
        const scaledAverage = parseFloat($stats.data('scaled-average')) || 0;
        updateStars($rating, scaledAverage);
    }

    /**
     * Fetch fresh nonce and rating data from server (bypasses cache)
     */
    function fetchFreshData() {
        if (isFetchingFreshData) {
            return;
        }
        
        isFetchingFreshData = true;
        
        // Collect all rating IDs on the page
        const ratingIds = [];
        const $allRatings = $('.shuriken-rating');
        
        // Group ratings by context for batched fetching
        const contextGroups = {};
        
        $allRatings.each(function() {
            const id = $(this).data('id');
            const ctxId = $(this).data('context-id') || '';
            const ctxType = $(this).data('context-type') || '';
            const key = ctxId + ':' + ctxType;
            
            if (id) {
                if (!contextGroups[key]) {
                    contextGroups[key] = { ids: [], contextId: ctxId, contextType: ctxType };
                }
                if (contextGroups[key].ids.indexOf(id) === -1) {
                    contextGroups[key].ids.push(id);
                }
                if (ratingIds.indexOf(id) === -1) {
                    ratingIds.push(id);
                }
            }
        });
        
        if (ratingIds.length === 0) {
            isFetchingFreshData = false;
            return;
        }
        
        // Add refreshing state to show loading feedback
        $allRatings.addClass('shuriken-refreshing');
        
        // Fetch fresh nonce (don't send X-WP-Nonce header - this is a public endpoint)
        $.ajax({
            url: shurikenReviews.rest_url + 'shuriken-reviews/v1/nonce',
            type: 'GET',
            cache: false, // Important: bypass browser cache
            // Note: Don't send X-WP-Nonce header here - the nonce endpoint must work
            // without authentication to handle cached pages with stale nonces
            success: function(nonceResponse) {
                // Update nonce in global object
                if (nonceResponse && nonceResponse.nonce) {
                    shurikenReviews.nonce = nonceResponse.nonce;
                    if (typeof nonceResponse.logged_in !== 'undefined') {
                        shurikenReviews.logged_in = nonceResponse.logged_in;
                    }
                    if (typeof nonceResponse.allow_guest_voting !== 'undefined') {
                        shurikenReviews.allow_guest_voting = nonceResponse.allow_guest_voting;
                    }
                }
                
                // Build stats requests per context group
                const groupKeys = Object.keys(contextGroups);
                let completedRequests = 0;
                const totalRequests = groupKeys.length;
                
                function applyStats(statsResponse, ctxId, ctxType) {
                    $.each(statsResponse, function(ratingId, stats) {
                        // Select only ratings matching this context
                        let selector = '.shuriken-rating[data-id="' + ratingId + '"]';
                        if (ctxId) {
                            selector += '[data-context-id="' + ctxId + '"][data-context-type="' + ctxType + '"]';
                        } else {
                            selector += ':not([data-context-id])';
                        }
                        const $ratings = $(selector);
                        $ratings.each(function() {
                            const $rating = $(this);
                            const ratingType = $rating.data('rating-type') || 'stars';
                            const $statsEl = $rating.find('.rating-stats');
                            const maxStars = parseInt($rating.data('max-stars')) || 5;
                            
                            if (ratingType === 'like_dislike') {
                                const totalVotes = parseInt(stats.total_votes) || 0;
                                const totalRating = parseInt(stats.total_rating) || 0;
                                $rating.find('.shuriken-like-count').text(totalRating);
                                $rating.find('.shuriken-dislike-count').text(totalVotes - totalRating);
                            } else if (ratingType === 'approval') {
                                $rating.find('.shuriken-upvote-count').text(stats.total_votes);
                            } else if (ratingType === 'numeric') {
                                const scaledAverage = parseFloat(stats.display_average) || 0;
                                
                                $statsEl.data('scaled-average', scaledAverage);
                                
                                const text = shurikenReviews.i18n.averageRating
                                    .replace('%1$s', scaledAverage)
                                    .replace('%2$s', maxStars)
                                    .replace('%3$s', stats.total_votes);
                                $statsEl.html(text);
                                
                                $rating.find('.shuriken-numeric-value').text(Math.round(scaledAverage * 10) / 10);
                                $rating.find('.shuriken-slider-value').text(Math.max(1, Math.round(scaledAverage)));
                            } else {
                                const scaledAverage = parseFloat(stats.display_average) || 0;
                                
                                $statsEl.data('scaled-average', scaledAverage);
                                
                                const text = shurikenReviews.i18n.averageRating
                                    .replace('%1$s', scaledAverage)
                                    .replace('%2$s', maxStars)
                                    .replace('%3$s', stats.total_votes);
                                $statsEl.html(text);
                                
                                updateStars($rating, scaledAverage);
                            }
                            
                            $rating.removeClass('shuriken-refreshing');
                        });
                    });
                }
                
                function onRequestComplete() {
                    completedRequests++;
                    if (completedRequests >= totalRequests) {
                        isFetchingFreshData = false;
                        $('.shuriken-rating.shuriken-refreshing').removeClass('shuriken-refreshing');
                    }
                }
                
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
                            $('.shuriken-rating').removeClass('shuriken-refreshing');
                        },
                        complete: onRequestComplete
                    });
                });
            },
            error: function(xhr, status, error) {
                console.error('Failed to fetch fresh nonce:', error);
                isFetchingFreshData = false;
                // Remove refreshing state on error
                $('.shuriken-rating').removeClass('shuriken-refreshing');
            }
        });
    }
    
    // Fetch fresh data on page load (bypasses cache)
    fetchFreshData();

    // Initialize stars based on average rating (only for stars type)
    $('.shuriken-rating').each(function() {
        const $rating = $(this);
        const ratingType = $rating.data('rating-type') || 'stars';
        
        // Skip non-star types for star initialization
        if (ratingType === 'like_dislike' || ratingType === 'approval' || ratingType === 'numeric') {
            return;
        }
        
        const $stats = $rating.find('.rating-stats');
        const maxStars = parseInt($rating.data('max-stars')) || 5;
        
        // Use scaled-average if available, otherwise calculate from normalized average
        let scaledAverage = parseFloat($stats.data('scaled-average'));
        if (isNaN(scaledAverage)) {
            const normalizedAverage = parseFloat($stats.data('average')) || 0;
            scaledAverage = (normalizedAverage / 5) * maxStars;
        }
        
        updateStars($rating, scaledAverage);

        // Update stars periodically; store interval ID for cleanup
        const intervalId = setInterval(function() {
            if (!$rating.data('hovering')) {
                resetStars($rating);
            }
        }, 4000);
        $rating.data('shuriken-interval', intervalId);
    });

    // Clean up setInterval when rating elements are removed from the DOM
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.removedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) return;
                    const $removed = $(node).find('.shuriken-rating').addBack('.shuriken-rating');
                    $removed.each(function() {
                        const id = $(this).data('shuriken-interval');
                        if (id) clearInterval(id);
                    });
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Only add hover effects to votable ratings (not display-only)
    $('.shuriken-rating:not(.display-only) .star').hover(
        function() {
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
        },
        function() {
            const $rating = $(this).closest('.shuriken-rating');
            $rating.data('hovering', false);
            resetStars($rating);
        }
    );

    /**
     * Submit a rating vote
     */
    function submitRating($rating, value, retryCount) {
        retryCount = retryCount || 0;
        
        const $stars = $rating.find('.stars');
        const ratingId = $rating.data('id');
        const maxStars = parseInt($rating.data('max-stars')) || 5;
        const contextId = $rating.data('context-id') || '';
        const contextType = $rating.data('context-type') || '';
        let originalText = $rating.find('.rating-stats').html();
        $rating.addClass('shuriken-loading');

        var postData = {
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
        
        $.ajax({
            url: shurikenReviews.ajaxurl,
            type: 'POST',
            data: postData,
            success: function(response) {
                if (response.success) {
                    // Show translated success feedback
                    $rating.find('.rating-stats').smoothHtml(shurikenReviews.i18n.thankYou);
                    // Highlight selected stars
                    $stars.find('.star').each(function() {
                        if ($(this).data('value') <= value) {
                            $(this).addClass('active');
                        } else {
                            $(this).removeClass('active');
                        }
                    });
                    
                    // Use scaled average and max_stars from response for display
                    const displayAverage = response.data.new_scaled_average || response.data.new_average;
                    const displayMaxStars = response.data.max_stars || 5;
                    
                    // Update text with translated string using numbered placeholders
                    originalText = shurikenReviews.i18n.averageRating
                        .replace('%1$s', displayAverage)
                        .replace('%2$s', displayMaxStars)
                        .replace('%3$s', response.data.new_total_votes);
                    $rating.find('.rating-stats').data('average', response.data.new_average);
                    $rating.find('.rating-stats').data('scaled-average', displayAverage);
                    
                    // If there's a parent rating on the page, update it too
                    if (response.data.parent_id) {
                        const $parentRating = $('.shuriken-rating[data-id="' + response.data.parent_id + '"]');
                        if ($parentRating.length) {
                            const parentDisplayAverage = response.data.parent_scaled_average || response.data.parent_average;
                            const parentMaxStars = response.data.parent_max_stars || 5;
                            const parentText = shurikenReviews.i18n.averageRating
                                .replace('%1$s', parentDisplayAverage)
                                .replace('%2$s', parentMaxStars)
                                .replace('%3$s', response.data.parent_total_votes);
                            $parentRating.find('.rating-stats').data('average', response.data.parent_average);
                            $parentRating.find('.rating-stats').data('scaled-average', parentDisplayAverage);
                            $parentRating.find('.rating-stats').smoothHtml(parentText);
                            // Update stars based on normalized average (1-5 scale)
                            updateStars($parentRating, response.data.parent_average);
                        }
                    }
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
                                if (nonceResponse && nonceResponse.nonce) {
                                    shurikenReviews.nonce = nonceResponse.nonce;
                                    // Retry the rating submission
                                    submitRating($rating, value, 1);
                                } else {
                                    $rating.find('.rating-stats').smoothHtml(
                                        shurikenReviews.i18n.error.replace('%s', response.data)
                                    );
                                    setTimeout(function() {
                                        $rating.find('.rating-stats').smoothHtml(originalText);
                                        $stars.css('pointer-events', 'auto');
                                    }, 4000);
                                }
                            },
                            error: function() {
                                $rating.find('.rating-stats').smoothHtml(
                                    shurikenReviews.i18n.error.replace('%s', response.data)
                                );
                                setTimeout(function() {
                                    $rating.find('.rating-stats').smoothHtml(originalText);
                                    $stars.css('pointer-events', 'auto');
                                }, 4000);
                            }
                        });
                        return; // Don't re-enable stars yet, wait for retry
                    }
                    
                    $rating.find('.rating-stats').smoothHtml(
                        shurikenReviews.i18n.error.replace('%s', response.data)
                    );
                }
                setTimeout(function() {
                    $rating.find('.rating-stats').smoothHtml(originalText);
                }, 4000);
            },
            error: function(xhr, status, error) {
                console.error('Rating submission error:', error);
                
                // Check if it's a nonce error and we haven't retried yet
                if (xhr.responseJSON && xhr.responseJSON.data && 
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
                            if (nonceResponse && nonceResponse.nonce) {
                                shurikenReviews.nonce = nonceResponse.nonce;
                                // Retry the rating submission
                                submitRating($rating, value, 1);
                            } else {
                                if (xhr.responseJSON && xhr.responseJSON.data) {
                                    $rating.find('.rating-stats').smoothHtml(
                                        shurikenReviews.i18n.error.replace('%s', xhr.responseJSON.data)
                                    );
                                } else {
                                    $rating.find('.rating-stats').smoothHtml(shurikenReviews.i18n.genericError);
                                }
                                setTimeout(function() {
                                    $rating.find('.rating-stats').smoothHtml(originalText);
                                    $stars.css('pointer-events', 'auto');
                                }, 4000);
                            }
                        },
                        error: function() {
                            if (xhr.responseJSON && xhr.responseJSON.data) {
                                $rating.find('.rating-stats').smoothHtml(
                                    shurikenReviews.i18n.error.replace('%s', xhr.responseJSON.data)
                                );
                            } else {
                                $rating.find('.rating-stats').smoothHtml(shurikenReviews.i18n.genericError);
                            }
                            setTimeout(function() {
                                $rating.find('.rating-stats').smoothHtml(originalText);
                                $stars.css('pointer-events', 'auto');
                            }, 4000);
                        }
                    });
                    return; // Don't re-enable stars yet, wait for retry
                }
                
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    $rating.find('.rating-stats').smoothHtml(
                        shurikenReviews.i18n.error.replace('%s', xhr.responseJSON.data)
                    );
                } else {
                    $rating.find('.rating-stats').smoothHtml(shurikenReviews.i18n.genericError);
                }
                setTimeout(function() {
                    $rating.find('.rating-stats').smoothHtml(originalText);
                }, 4000);
            },
            complete: function() {
                $rating.removeClass('shuriken-loading');
                $stars.css('pointer-events', 'auto');
            }
        });
    }

    // Only allow clicking on votable ratings (not display-only)
    $('.shuriken-rating:not(.display-only) .star').on('click', function(e) {
        e.preventDefault();
        const $rating = $(this).closest('.shuriken-rating');
        const $stars = $rating.find('.stars');
        const value = $(this).data('value');

        // Check if voting is allowed (logged in or guest voting enabled)
        const isLoggedIn = shurikenReviews.logged_in === true || shurikenReviews.logged_in === "1" || shurikenReviews.logged_in === 1;
        const guestVotingAllowed = shurikenReviews.allow_guest_voting === true || shurikenReviews.allow_guest_voting === "1" || shurikenReviews.allow_guest_voting === 1;
        
        if (!isLoggedIn && !guestVotingAllowed) {
            const loginUrl = shurikenReviews.login_url + '?redirect_to=' + encodeURIComponent(window.location.href);
            
            // Add login message using translated string
            if (!$rating.find('.login-message').length) {
                $rating.find('.rating-stats').after(
                    '<div class="login-message">[' + 
                    shurikenReviews.i18n.pleaseLogin.replace('%s', loginUrl) + 
                    ']</div>'
                );
            }

            // Show login message
            $rating.find('.login-message').show();

            return;
        }
        
        // Disable stars while processing
        $stars.css('pointer-events', 'none');

        // Display user's vote on stars
        $stars.find('.star').each(function() {
            if ($(this).data('value') <= value) {
                $(this).addClass('active');
            } else {
                $(this).removeClass('active');
            }
        });

        // Submit the rating
        submitRating($rating, value, 0);
    });

    // Only allow keyboard navigation on votable ratings (not display-only)
    $('.shuriken-rating:not(.display-only) .star').on('keydown', function(e) {
        // Handle Enter (13) or Space (32) key press
        if (e.which === 13 || e.which === 32) {
            e.preventDefault();
            $(this).click();
        }
    });

    /**
     * Submit a like/dislike or approval vote
     */
    function submitBinaryRating($rating, value, retryCount) {
        retryCount = retryCount || 0;

        const ratingId = $rating.data('id');
        const ratingType = $rating.data('rating-type');
        const contextId = $rating.data('context-id') || '';
        const contextType = $rating.data('context-type') || '';
        const $feedback = $rating.find('.shuriken-feedback');

        function showFeedback(msg, duration) {
            $feedback.smoothHtml(msg);
            setTimeout(function() { $feedback.smoothHtml(''); }, duration || 3000);
        }

        function showError(response, xhr) {
            var msg;
            if (response && response.data && typeof response.data === 'string') {
                msg = shurikenReviews.i18n.error.replace('%s', response.data);
            } else if (xhr && xhr.responseJSON && xhr.responseJSON.data && typeof xhr.responseJSON.data === 'string') {
                msg = shurikenReviews.i18n.error.replace('%s', xhr.responseJSON.data);
            } else {
                msg = shurikenReviews.i18n.genericError;
            }
            showFeedback(msg, 4000);
        }

        // Pulse the widget while AJAX is in-flight
        $rating.addClass('shuriken-loading');

        var postData = {
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

        $.ajax({
            url: shurikenReviews.ajaxurl,
            type: 'POST',
            data: postData,
            success: function(response) {
                if (response.success) {
                    if (ratingType === 'like_dislike') {
                        // new_scaled_average is likes/(likes+dislikes)*100
                        const totalVotes = response.data.new_total_votes;
                        const approvalPct = response.data.new_scaled_average;
                        const likeCount = Math.round(totalVotes * approvalPct / 100);
                        const dislikeCount = totalVotes - likeCount;
                        $rating.find('.shuriken-like-count').text(likeCount);
                        $rating.find('.shuriken-dislike-count').text(dislikeCount);
                    } else if (ratingType === 'approval') {
                        $rating.find('.shuriken-upvote-count').text(response.data.new_total_votes);
                    }

                    // Update parent rating if applicable (display-only parents)
                    if (response.data.parent_id) {
                        const $parentRating = $('.shuriken-rating[data-id="' + response.data.parent_id + '"]');
                        if ($parentRating.length) {
                            const parentType = $parentRating.data('rating-type');
                            if (parentType === 'like_dislike') {
                                // For binary: parent_average = total_rating / total_votes (0-1 range)
                                const pParentPct = response.data.parent_total_votes > 0
                                    ? Math.round(response.data.parent_average * 100)
                                    : 0;
                                const $approvalPct = $parentRating.find('.shuriken-approval-pct');
                                if ($approvalPct.length) {
                                    // Display-only parents show approval percentage summary
                                    $approvalPct.text(pParentPct + '%');
                                } else {
                                    // Non-display-only parents: update like/dislike counts
                                    const pTotalVotes = response.data.parent_total_votes;
                                    const pLikeCount = Math.round(pTotalVotes * pParentPct / 100);
                                    const pDislikeCount = pTotalVotes - pLikeCount;
                                    $parentRating.find('.shuriken-like-count').text(pLikeCount);
                                    $parentRating.find('.shuriken-dislike-count').text(pDislikeCount);
                                }
                                // Update vote summary text
                                const $voteSummary = $parentRating.find('.shuriken-vote-summary');
                                if ($voteSummary.length) {
                                    $voteSummary.text('(' + response.data.parent_total_votes + ' ' + shurikenReviews.i18n.votes + ')');
                                }
                            } else if (parentType === 'approval') {
                                $parentRating.find('.shuriken-upvote-count').text(response.data.parent_total_votes);
                            } else {
                                // Stars/numeric parent — update stars display
                                updateStars($parentRating, response.data.parent_average);
                                const parentDisplayAverage = response.data.parent_scaled_average || response.data.parent_average;
                                const parentMaxStars = response.data.parent_max_stars || 5;
                                const parentText = shurikenReviews.i18n.averageRating
                                    .replace('%1$s', parentDisplayAverage)
                                    .replace('%2$s', parentMaxStars)
                                    .replace('%3$s', response.data.parent_total_votes);
                                $parentRating.find('.rating-stats').smoothHtml(parentText);
                            }
                        }
                    }

                    // Brief visual feedback on the button
                    $rating.find('.shuriken-btn').addClass('shuriken-voted');
                    setTimeout(function() {
                        $rating.find('.shuriken-btn').removeClass('shuriken-voted');
                    }, 1500);

                    // Show thank-you message then clear
                    showFeedback(shurikenReviews.i18n.thankYou, 3000);
                } else {
                    // Handle nonce retry for binary ratings
                    if (response.data && typeof response.data === 'string' &&
                        response.data.toLowerCase().indexOf('nonce') !== -1 && retryCount === 0) {
                        $.ajax({
                            url: shurikenReviews.rest_url + 'shuriken-reviews/v1/nonce',
                            type: 'GET',
                            cache: false,
                            success: function(nonceResponse) {
                                if (nonceResponse && nonceResponse.nonce) {
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
                if (xhr.responseJSON && xhr.responseJSON.data &&
                    typeof xhr.responseJSON.data === 'string' &&
                    xhr.responseJSON.data.toLowerCase().indexOf('nonce') !== -1 &&
                    retryCount === 0) {
                    $.ajax({
                        url: shurikenReviews.rest_url + 'shuriken-reviews/v1/nonce',
                        type: 'GET',
                        cache: false,
                        success: function(nonceResponse) {
                            if (nonceResponse && nonceResponse.nonce) {
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
    }

    // Like/Dislike button click handler
    $('.shuriken-rating:not(.display-only) .shuriken-like-btn, .shuriken-rating:not(.display-only) .shuriken-dislike-btn').on('click', function(e) {
        e.preventDefault();
        const $rating = $(this).closest('.shuriken-rating');
        const value = parseInt($(this).data('value'));

        // Check if voting is allowed
        const isLoggedIn = shurikenReviews.logged_in === true || shurikenReviews.logged_in === "1" || shurikenReviews.logged_in === 1;
        const guestVotingAllowed = shurikenReviews.allow_guest_voting === true || shurikenReviews.allow_guest_voting === "1" || shurikenReviews.allow_guest_voting === 1;
        
        if (!isLoggedIn && !guestVotingAllowed) {
            const loginUrl = shurikenReviews.login_url + '?redirect_to=' + encodeURIComponent(window.location.href);
            if (!$rating.find('.login-message').length) {
                $rating.find('.shuriken-like-dislike').after(
                    '<div class="login-message">[' + 
                    shurikenReviews.i18n.pleaseLogin.replace('%s', loginUrl) + 
                    ']</div>'
                );
            }
            $rating.find('.login-message').show();
            return;
        }
        
        // Disable buttons while processing
        $rating.find('.shuriken-btn').css('pointer-events', 'none');
        
        submitBinaryRating($rating, value, 0);
    });

    /**
     * Update the --fill-pct custom property on a range slider so the
     * track pseudo-elements can paint a filled-portion gradient.
     * Works cross-browser (Chromium, Firefox, Safari).
     */
    function updateSliderFill($slider) {
        var min = parseFloat($slider.attr('min')) || 0;
        var max = parseFloat($slider.attr('max')) || 100;
        var val = parseFloat($slider.val()) || 0;
        var pct = ((val - min) / (max - min)) * 100;
        $slider[0].style.setProperty('--fill-pct', pct + '%');
    }

    // Numeric slider: live value update on input + fill track
    $('.shuriken-rating:not(.display-only) .shuriken-slider').on('input', function() {
        var $s = $(this);
        $s.closest('.shuriken-numeric').find('.shuriken-slider-value').text($s.val());
        updateSliderFill($s);
    });

    // Paint the filled track on page load for every slider
    $('.shuriken-slider').each(function() {
        updateSliderFill($(this));
    });

    // Numeric slider: submit button click handler
    $('.shuriken-rating:not(.display-only) .shuriken-slider-submit').on('click', function(e) {
        e.preventDefault();
        const $rating = $(this).closest('.shuriken-rating');
        const $slider = $rating.find('.shuriken-slider');
        const value = parseInt($slider.val());

        // Check if voting is allowed
        const isLoggedIn = shurikenReviews.logged_in === true || shurikenReviews.logged_in === "1" || shurikenReviews.logged_in === 1;
        const guestVotingAllowed = shurikenReviews.allow_guest_voting === true || shurikenReviews.allow_guest_voting === "1" || shurikenReviews.allow_guest_voting === 1;
        
        if (!isLoggedIn && !guestVotingAllowed) {
            const loginUrl = shurikenReviews.login_url + '?redirect_to=' + encodeURIComponent(window.location.href);
            if (!$rating.find('.login-message').length) {
                $rating.find('.shuriken-numeric').after(
                    '<div class="login-message">[' + 
                    shurikenReviews.i18n.pleaseLogin.replace('%s', loginUrl) + 
                    ']</div>'
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
        }, 4000);
    });

    // Approval (upvote) button click handler
    $('.shuriken-rating:not(.display-only) .shuriken-upvote-btn').on('click', function(e) {
        e.preventDefault();
        const $rating = $(this).closest('.shuriken-rating');

        // Check if voting is allowed
        const isLoggedIn = shurikenReviews.logged_in === true || shurikenReviews.logged_in === "1" || shurikenReviews.logged_in === 1;
        const guestVotingAllowed = shurikenReviews.allow_guest_voting === true || shurikenReviews.allow_guest_voting === "1" || shurikenReviews.allow_guest_voting === 1;
        
        if (!isLoggedIn && !guestVotingAllowed) {
            const loginUrl = shurikenReviews.login_url + '?redirect_to=' + encodeURIComponent(window.location.href);
            if (!$rating.find('.login-message').length) {
                $rating.find('.shuriken-approval').after(
                    '<div class="login-message">[' + 
                    shurikenReviews.i18n.pleaseLogin.replace('%s', loginUrl) + 
                    ']</div>'
                );
            }
            $rating.find('.login-message').show();
            return;
        }
        
        // Disable button while processing
        $rating.find('.shuriken-btn').css('pointer-events', 'none');
        
        submitBinaryRating($rating, 1, 0);
    });
});
