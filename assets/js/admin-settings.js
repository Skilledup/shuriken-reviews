/**
 * Shuriken Reviews Settings Page JavaScript
 *
 * Handles tab navigation and toggle interactions.
 *
 * @package Shuriken_Reviews
 * @since 1.10.0
 */

(function($) {
    'use strict';

    /**
     * Initialize settings page functionality
     */
    function init() {
        initCollapsibleSections();
        initToggleControls();
        initDismissWarning();
    }

    /**
     * Initialize collapsible sections controlled by toggles
     */
    function initCollapsibleSections() {
        // Set initial state based on checkbox
        $('[data-controls]').each(function() {
            var $checkbox = $(this);
            var targetId = $checkbox.data('controls');
            var $target = $('#' + targetId);
            
            if ($target.length) {
                if ($checkbox.is(':checked')) {
                    $target.addClass('is-expanded');
                } else {
                    $target.removeClass('is-expanded');
                }
            }
        });
    }

    /**
     * Initialize toggle controls that show/hide sections
     */
    function initToggleControls() {
        $(document).on('change', '[data-controls]', function() {
            var $checkbox = $(this);
            var targetId = $checkbox.data('controls');
            var $target = $('#' + targetId);
            
            if ($target.length) {
                if ($checkbox.is(':checked')) {
                    $target.addClass('is-expanded');
                } else {
                    $target.removeClass('is-expanded');
                }
            }
        });
    }

    /**
     * Initialize dismissible warning banner
     */
    function initDismissWarning() {
        $(document).on('click', '.shuriken-dismiss-warning', function() {
            var $banner = $(this).closest('.shuriken-rate-limit-warning');
            $banner.fadeOut(200, function() { $banner.remove(); });

            $.post(ajaxurl, {
                action: 'shuriken_dismiss_rate_limit_warning',
                _wpnonce: shurikenSettings.dismissNonce
            });
        });
    }

    // Initialize when DOM is ready
    $(document).ready(init);

})(jQuery);
