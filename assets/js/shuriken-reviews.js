jQuery(document).ready(function($) {
    var isFetchingFreshData = false;
    
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
        var $stats = $rating.find('.rating-stats');
        var scaledAverage = parseFloat($stats.data('scaled-average'));
        
        // If no scaled average, calculate from normalized average
        if (isNaN(scaledAverage)) {
            var normalizedAverage = parseFloat($stats.data('average')) || 0;
            var maxStars = parseInt($rating.data('max-stars')) || 5;
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
        var ratingIds = [];
        $('.shuriken-rating').each(function() {
            var id = $(this).data('id');
            if (id && ratingIds.indexOf(id) === -1) {
                ratingIds.push(id);
            }
        });
        
        if (ratingIds.length === 0) {
            isFetchingFreshData = false;
            return;
        }
        
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
                            var $ratings = $('.shuriken-rating[data-id="' + ratingId + '"]');
                            $ratings.each(function() {
                                var $rating = $(this);
                                var $statsEl = $rating.find('.rating-stats');
                                var maxStars = parseInt($rating.data('max-stars')) || 5;
                                
                                // Calculate scaled average from normalized (1-5 scale)
                                var normalizedAverage = parseFloat(stats.average) || 0;
                                var scaledAverage = (normalizedAverage / 5) * maxStars;
                                scaledAverage = Math.round(scaledAverage * 10) / 10; // Round to 1 decimal
                                
                                // Update data attributes
                                $statsEl.data('average', stats.average);
                                $statsEl.data('scaled-average', scaledAverage);
                                
                                // Update displayed text with scaled values
                                var text = shurikenReviews.i18n.averageRating
                                    .replace('%1$s', scaledAverage)
                                    .replace('%2$s', maxStars)
                                    .replace('%3$s', stats.total_votes);
                                $statsEl.html(text);
                                
                                // Update stars using scaled average
                                updateStars($rating, scaledAverage);
                            });
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error('Failed to fetch fresh rating stats:', error);
                    },
                    complete: function() {
                        isFetchingFreshData = false;
                    }
                });
            },
            error: function(xhr, status, error) {
                console.error('Failed to fetch fresh nonce:', error);
                isFetchingFreshData = false;
            }
        });
    }
    
    // Fetch fresh data on page load (bypasses cache)
    fetchFreshData();

    // Initialize stars based on average rating
    $('.shuriken-rating').each(function() {
        var $rating = $(this);
        var $stats = $rating.find('.rating-stats');
        var maxStars = parseInt($rating.data('max-stars')) || 5;
        
        // Use scaled-average if available, otherwise calculate from normalized average
        var scaledAverage = parseFloat($stats.data('scaled-average'));
        if (isNaN(scaledAverage)) {
            var normalizedAverage = parseFloat($stats.data('average')) || 0;
            scaledAverage = (normalizedAverage / 5) * maxStars;
        }
        
        updateStars($rating, scaledAverage);

        // Update stars after 4 seconds
        setInterval(function() {
            if (!$rating.data('hovering')) {
                resetStars($rating);
            }
        }, 4000);
    });

    // Only add hover effects to votable ratings (not display-only)
    $('.shuriken-rating:not(.display-only) .star').hover(
        function() {
            var $rating = $(this).closest('.shuriken-rating');
            $rating.data('hovering', true);
            var value = $(this).data('value');
            $(this).parent().find('.star').each(function() {
                if ($(this).data('value') <= value) {
                    $(this).addClass('active');
                } else {
                    $(this).removeClass('active');
                }
            });
        },
        function() {
            var $rating = $(this).closest('.shuriken-rating');
            $rating.data('hovering', false);
            resetStars($rating);
        }
    );

    /**
     * Submit a rating vote
     */
    function submitRating($rating, value, retryCount) {
        retryCount = retryCount || 0;
        
        var $stars = $rating.find('.stars');
        var ratingId = $rating.data('id');
        var maxStars = parseInt($rating.data('max-stars')) || 5;
        var originalText = $rating.find('.rating-stats').html();
        
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
                    var displayAverage = response.data.new_scaled_average || response.data.new_average;
                    var displayMaxStars = response.data.max_stars || 5;
                    
                    // Update text with translated string using numbered placeholders
                    originalText = shurikenReviews.i18n.averageRating
                        .replace('%1$s', displayAverage)
                        .replace('%2$s', displayMaxStars)
                        .replace('%3$s', response.data.new_total_votes);
                    $rating.find('.rating-stats').data('average', response.data.new_average);
                    $rating.find('.rating-stats').data('scaled-average', displayAverage);
                    
                    // If there's a parent rating on the page, update it too
                    if (response.data.parent_id) {
                        var $parentRating = $('.shuriken-rating[data-id="' + response.data.parent_id + '"]');
                        if ($parentRating.length) {
                            var parentDisplayAverage = response.data.parent_scaled_average || response.data.parent_average;
                            var parentMaxStars = response.data.parent_max_stars || 5;
                            var parentText = shurikenReviews.i18n.averageRating
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
        var $rating = $(this).closest('.shuriken-rating');
        var $stars = $rating.find('.stars');
        var value = $(this).data('value');

        // Check if voting is allowed (logged in or guest voting enabled)
        var isLoggedIn = shurikenReviews.logged_in === true || shurikenReviews.logged_in === "1" || shurikenReviews.logged_in === 1;
        var guestVotingAllowed = shurikenReviews.allow_guest_voting === true || shurikenReviews.allow_guest_voting === "1" || shurikenReviews.allow_guest_voting === 1;
        
        if (!isLoggedIn && !guestVotingAllowed) {
            var loginUrl = shurikenReviews.login_url + '?redirect_to=' + encodeURIComponent(window.location.href);
            
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
});
