jQuery(document).ready(function($) {
    $('.shuriken-rating .star').hover(
        function() {
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
            $(this).parent().find('.star').removeClass('active');
        }
    );

    $('.shuriken-rating .star').on('click', function(e) {
        e.preventDefault();
        var $rating = $(this).closest('.shuriken-rating');
        var $stars = $rating.find('.stars');
        var ratingId = $rating.data('id');
        var value = $(this).data('value');

        if (!shurikenReviews.logged_in) {
            var loginUrl = shurikenReviews.login_url + '?redirect_to=' + encodeURIComponent(window.location.href);
            $rating.find('.rating-stats').html('Please <a href="' + loginUrl + '">login</a> to rate.');
            return;
        }
        
        // Disable stars while processing
        $stars.css('pointer-events', 'none');

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
                        }
                    });
                } else {
                    $rating.find('.rating-stats').html('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Rating submission error:', error);
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    $rating.find('.rating-stats').html(xhr.responseJSON.data);
                } else {
                    $rating.find('.rating-stats').html('Error submitting rating. Please try again.');
                }
            },
            complete: function() {
                // Re-enable stars
                $stars.css('pointer-events', 'auto');
            }
        });
    });
});
