jQuery(document).ready(function($) {
    function updateStars($rating, average) {
        $rating.find('.star').each(function() {
            if ($(this).data('value') <= average) {
                $(this).addClass('active');
            } else {
                $(this).removeClass('active');
            }
        });
    }

    function resetStars($rating) {
        var average = parseFloat($rating.find('.rating-stats').data('average'));
        updateStars($rating, average);
    }

    // Initialize stars based on average rating
    $('.shuriken-rating').each(function() {
        var $rating = $(this);
        var average = parseFloat($rating.find('.rating-stats').data('average'));
        updateStars($rating, average);

        // Update stars after 4 seconds
        setInterval(function() {
            if (!$rating.data('hovering')) {
                resetStars($rating);
            }
        }, 4000);
    });

    $('.shuriken-rating .star').hover(
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

    $('.shuriken-rating .star').on('click', function(e) {
        e.preventDefault();
        var $rating = $(this).closest('.shuriken-rating');
        var $stars = $rating.find('.stars');
        var ratingId = $rating.data('id');
        var value = $(this).data('value');
        var originalText = $rating.find('.rating-stats').html();

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

        $.ajax({
            url: shurikenReviews.ajaxurl,
            type: 'POST',
            data: {
                action: 'submit_rating',
                rating_id: ratingId,
                rating_value: value,
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
                    // Update text with translated string using numbered placeholders
                    originalText = shurikenReviews.i18n.averageRating
                        .replace('%1$s', response.data.new_average)
                        .replace('%2$s', response.data.new_total_votes);
                    $rating.find('.rating-stats').data('average', response.data.new_average);
                } else {
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
                // Re-enable stars
                $stars.css('pointer-events', 'auto');
            }
        });
    });

    $('.shuriken-rating .star').on('keydown', function(e) {
        // Handle Enter (13) or Space (32) key press
        if (e.which === 13 || e.which === 32) {
            e.preventDefault();
            $(this).click();
        }
    });
});
