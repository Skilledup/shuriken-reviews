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

        if (!shurikenReviews.logged_in) {
            var loginUrl = shurikenReviews.login_url + '?redirect_to=' + encodeURIComponent(window.location.href);
            $rating.find('.rating-stats').html('Please <a href="' + loginUrl + '">login</a> to rate.');
            setTimeout(function() {
                $rating.find('.rating-stats').html(originalText);
            }, 4000);
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
                    // Show success feedback
                    $rating.find('.rating-stats').html('Thank you for rating!');
                    // Highlight selected stars
                    $stars.find('.star').each(function() {
                        if ($(this).data('value') <= value) {
                            $(this).addClass('active');
                        } else {
                            $(this).removeClass('active');
                        }
                    });
                    // Update the original text with new average and total votes
                    originalText = 'Average: ' + response.data.new_average + '/5 (' + response.data.new_total_votes + ' votes)';
                    $rating.find('.rating-stats').data('average', response.data.new_average);
                } else {
                    $rating.find('.rating-stats').html('Error: ' + response.data);
                }
                setTimeout(function() {
                    $rating.find('.rating-stats').html(originalText);
                }, 4000);
            },
            error: function(xhr, status, error) {
                console.error('Rating submission error:', error);
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    $rating.find('.rating-stats').html(xhr.responseJSON.data);
                } else {
                    $rating.find('.rating-stats').html('Error submitting rating. Please try again.');
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
});
