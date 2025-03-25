jQuery(document).ready(function($) {
    // Debug line to ensure script is loaded
    console.log('Shuriken Reviews script loaded');

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
                    alert('Error submitting rating: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Rating submission error:', error);
                alert('Error submitting rating. Please try again.');
            },
            complete: function() {
                // Re-enable stars
                $stars.css('pointer-events', 'auto');
            }
        });
    });
});
