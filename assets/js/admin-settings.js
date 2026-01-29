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

    // Initialize when DOM is ready
    $(document).ready(init);

})(jQuery);
