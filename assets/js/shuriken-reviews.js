jQuery(document).ready(function($) {
    'use strict';

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
     * Reset stars to show the current average
     * Uses scaled-average if available, falls back to converting from normalized average
     */
    function resetStars($rating) {
        const $stats = $rating.find('.rating-stats');
        let scaledAverage = parseFloat($stats.data('scaled-average'));
        
        // If no scaled average, calculate from normalized average
        if (isNaN(scaledAverage)) {
            const normalizedAverage = parseFloat($stats.data('average')) || 0;
            const maxStars = parseInt($rating.data('max-stars')) || 5;
            scaledAverage = (normalizedAverage / 5) * maxStars;
        }
        
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
        
        $allRatings.each(function() {
            const id = $(this).data('id');
            if (id && ratingIds.indexOf(id) === -1) {
                ratingIds.push(id);
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
                
                // Fetch fresh rating stats (public endpoint - no auth needed)
                $.ajax({
                    url: shurikenReviews.rest_url + 'shuriken-reviews/v1/ratings/stats',
                    type: 'GET',
                    cache: false, // Important: bypass browser cache
                    data: {
                        ids: ratingIds.join(',')
                    },
                    // Note: Don't send X-WP-Nonce header - this is a public endpoint
                    success: function(statsResponse) {
                        // Update each rating with fresh data
                        $.each(statsResponse, function(ratingId, stats) {
                            const $ratings = $('.shuriken-rating[data-id="' + ratingId + '"]');
                            $ratings.each(function() {
                                const $rating = $(this);
                                const ratingType = $rating.data('rating-type') || 'stars';
                                const $statsEl = $rating.find('.rating-stats');
                                const maxStars = parseInt($rating.data('max-stars')) || 5;
                                
                                if (ratingType === 'like_dislike') {
                                    // Update like/dislike counts
                                    const totalVotes = parseInt(stats.total_votes) || 0;
                                    const totalRating = parseInt(stats.total_rating) || 0;
                                    $rating.find('.shuriken-like-count').text(totalRating);
                                    $rating.find('.shuriken-dislike-count').text(totalVotes - totalRating);
                                } else if (ratingType === 'approval') {
                                    // Update upvote count
                                    $rating.find('.shuriken-upvote-count').text(stats.total_votes);
                                } else if (ratingType === 'numeric') {
                                    // Numeric slider: calculate scaled average
                                    const normalizedAverage = parseFloat(stats.average) || 0;
                                    let scaledAverage = (normalizedAverage / 5) * maxStars;
                                    scaledAverage = Math.round(scaledAverage * 10) / 10;
                                    
                                    $statsEl.data('average', stats.average);
                                    $statsEl.data('scaled-average', scaledAverage);
                                    
                                    const text = shurikenReviews.i18n.averageRating
                                        .replace('%1$s', scaledAverage)
                                        .replace('%2$s', maxStars)
                                        .replace('%3$s', stats.total_votes);
                                    $statsEl.html(text);
                                    
                                    // Update slider value display
                                    $rating.find('.shuriken-numeric-value, .shuriken-slider-value').text(Math.round(scaledAverage));
                                } else {
                                    // Stars/numeric: calculate scaled average from normalized (1-5 scale)
                                    const normalizedAverage = parseFloat(stats.average) || 0;
                                    let scaledAverage = (normalizedAverage / 5) * maxStars;
                                    scaledAverage = Math.round(scaledAverage * 10) / 10; // Round to 1 decimal
                                    
                                    // Update data attributes
                                    $statsEl.data('average', stats.average);
                                    $statsEl.data('scaled-average', scaledAverage);
                                    
                                    // Update displayed text with scaled values
                                    const text = shurikenReviews.i18n.averageRating
                                        .replace('%1$s', scaledAverage)
                                        .replace('%2$s', maxStars)
                                        .replace('%3$s', stats.total_votes);
                                    $statsEl.html(text);
                                    
                                    // Update stars using scaled average
                                    updateStars($rating, scaledAverage);
                                }
                                
                                // Remove refreshing state with slight delay for smooth transition
                                $rating.removeClass('shuriken-refreshing');
                            });
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error('Failed to fetch fresh rating stats:', error);
                        // Remove refreshing state on error too
                        $('.shuriken-rating').removeClass('shuriken-refreshing');
                    },
                    complete: function() {
                        isFetchingFreshData = false;
                        // Ensure refreshing state is removed even if no data returned
                        $('.shuriken-rating.shuriken-refreshing').removeClass('shuriken-refreshing');
                    }
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
        let originalText = $rating.find('.rating-stats').html();
        
        $.ajax({
            url: shurikenReviews.ajaxurl,
            type: 'POST',
            data: {
                action: 'submit_rating',
                rating_id: ratingId,
                rating_value: value,
                max_stars: maxStars,
                nonce: shurikenReviews.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show translated success feedback
                    $rating.find('.rating-stats').html(shurikenReviews.i18n.thankYou);
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
                            $parentRating.find('.rating-stats').html(parentText);
                            // Update stars based on normalized average (1-5 scale)
                            updateStars($parentRating, response.data.parent_average);
                        }
                    }
                } else {
                    // Check if it's a nonce error and we haven't retried yet
                    if (response.data && typeof response.data === 'string' && 
                        response.data.toLowerCase().indexOf('nonce') !== -1 && retryCount === 0) {
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
                                    $rating.find('.rating-stats').html(
                                        shurikenReviews.i18n.error.replace('%s', response.data)
                                    );
                                    setTimeout(function() {
                                        $rating.find('.rating-stats').html(originalText);
                                        $stars.css('pointer-events', 'auto');
                                    }, 4000);
                                }
                            },
                            error: function() {
                                $rating.find('.rating-stats').html(
                                    shurikenReviews.i18n.error.replace('%s', response.data)
                                );
                                setTimeout(function() {
                                    $rating.find('.rating-stats').html(originalText);
                                    $stars.css('pointer-events', 'auto');
                                }, 4000);
                            }
                        });
                        return; // Don't re-enable stars yet, wait for retry
                    }
                    
                    $rating.find('.rating-stats').html(
                        shurikenReviews.i18n.error.replace('%s', response.data)
                    );
                }
                setTimeout(function() {
                    $rating.find('.rating-stats').html(originalText);
                }, 4000);
            },
            error: function(xhr, status, error) {
                console.error('Rating submission error:', error);
                
                // Check if it's a nonce error and we haven't retried yet
                if (xhr.responseJSON && xhr.responseJSON.data && 
                    typeof xhr.responseJSON.data === 'string' && 
                    xhr.responseJSON.data.toLowerCase().indexOf('nonce') !== -1 && 
                    retryCount === 0) {
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
                                    $rating.find('.rating-stats').html(
                                        shurikenReviews.i18n.error.replace('%s', xhr.responseJSON.data)
                                    );
                                } else {
                                    $rating.find('.rating-stats').html(shurikenReviews.i18n.genericError);
                                }
                                setTimeout(function() {
                                    $rating.find('.rating-stats').html(originalText);
                                    $stars.css('pointer-events', 'auto');
                                }, 4000);
                            }
                        },
                        error: function() {
                            if (xhr.responseJSON && xhr.responseJSON.data) {
                                $rating.find('.rating-stats').html(
                                    shurikenReviews.i18n.error.replace('%s', xhr.responseJSON.data)
                                );
                            } else {
                                $rating.find('.rating-stats').html(shurikenReviews.i18n.genericError);
                            }
                            setTimeout(function() {
                                $rating.find('.rating-stats').html(originalText);
                                $stars.css('pointer-events', 'auto');
                            }, 4000);
                        }
                    });
                    return; // Don't re-enable stars yet, wait for retry
                }
                
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    $rating.find('.rating-stats').html(
                        shurikenReviews.i18n.error.replace('%s', xhr.responseJSON.data)
                    );
                } else {
                    $rating.find('.rating-stats').html(shurikenReviews.i18n.genericError);
                }
                setTimeout(function() {
                    $rating.find('.rating-stats').html(originalText);
                }, 4000);
            },
            complete: function() {
                // Re-enable stars (only if we're not retrying)
                if (retryCount > 0 || !$rating.data('retrying')) {
                    $stars.css('pointer-events', 'auto');
                }
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
        
        $.ajax({
            url: shurikenReviews.ajaxurl,
            type: 'POST',
            data: {
                action: 'submit_rating',
                rating_id: ratingId,
                rating_value: value,
                max_stars: 1,
                nonce: shurikenReviews.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (ratingType === 'like_dislike') {
                        const likes = response.data.new_total_votes > 0 
                            ? Math.round((response.data.new_average) * response.data.new_total_votes / (response.data.new_average > 0 ? 1 : 1))
                            : 0;
                        // total_rating stores like count directly for like_dislike
                        // Server returns new_average which is total_rating/total_votes
                        // We need: likes = total_rating, dislikes = total_votes - total_rating
                        // But the response gives normalized_average. Let's use the raw counts from response
                        // Actually new_scaled_average is the approval %, total_votes is total_votes
                        // For like_dislike, the AJAX response sends rating_type, we can compute from total_votes and new_scaled_average
                        const totalVotes = response.data.new_total_votes;
                        const approvalPct = response.data.new_scaled_average; // This is actually likes/(likes+dislikes)*100
                        const likeCount = Math.round(totalVotes * approvalPct / 100);
                        const dislikeCount = totalVotes - likeCount;
                        
                        $rating.find('.shuriken-like-count').text(likeCount);
                        $rating.find('.shuriken-dislike-count').text(dislikeCount);
                    } else if (ratingType === 'approval') {
                        $rating.find('.shuriken-upvote-count').text(response.data.new_total_votes);
                    }
                    
                    // Brief visual feedback
                    $rating.find('.shuriken-btn').addClass('shuriken-voted');
                    setTimeout(function() {
                        $rating.find('.shuriken-btn').removeClass('shuriken-voted');
                    }, 1500);
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
                                }
                            }
                        });
                        return;
                    }
                    console.error('Binary rating error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Binary rating submission error:', error);
            },
            complete: function() {
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

    // Numeric slider: live value update on input
    $('.shuriken-rating:not(.display-only) .shuriken-slider').on('input', function() {
        const value = $(this).val();
        $(this).closest('.shuriken-numeric').find('.shuriken-slider-value').text(value);
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
