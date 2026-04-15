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
     * Initialize collapsible sections controlled by toggles
     */
    const initCollapsibleSections = () => {
        $('[data-controls]').each(function() {
            const $checkbox = $(this);
            const targetId = $checkbox.data('controls');
            const $target = $(`#${targetId}`);
            
            if ($target.length) {
                $target.toggleClass('is-expanded', $checkbox.is(':checked'));
            }
        });
    };

    /**
     * Initialize toggle controls that show/hide sections
     */
    const initToggleControls = () => {
        $(document).on('change', '[data-controls]', function() {
            const $checkbox = $(this);
            const targetId = $checkbox.data('controls');
            const $target = $(`#${targetId}`);
            
            if ($target.length) {
                $target.toggleClass('is-expanded', $checkbox.is(':checked'));
            }
        });
    };

    /**
     * Initialize dismissible warning banner
     */
    const initDismissWarning = () => {
        $(document).on('click', '.shuriken-dismiss-warning', function() {
            const $banner = $(this).closest('.shuriken-rate-limit-warning');
            $banner.fadeOut(200, function() { $banner.remove(); });

            $.post(ajaxurl, {
                action: 'shuriken_dismiss_rate_limit_warning',
                _wpnonce: shurikenSettings.dismissNonce
            });
        });
    };

    // Initialize when DOM is ready
    $(document).ready(() => {
        initCollapsibleSections();
        initToggleControls();
        initDismissWarning();
    });

})(jQuery);
