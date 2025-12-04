/**
 * Shuriken Reviews - Admin Ratings Page JavaScript
 * Handles inline edit, bulk actions, and UI interactions
 * 
 * @package Shuriken Reviews
 * @since 1.2.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        /**
         * Inline Edit functionality
         */
        $(document).on('click', '.editinline, .row-title', function(e) {
            e.preventDefault();
            
            const href = $(this).attr('href');
            if (!href || !href.startsWith('#inline-edit-')) return;
            
            const editRowId = href.substring(1);
            const $editRow = $('#' + editRowId);
            const $dataRow = $editRow.prev('tr.iedit');
            
            // Hide any other open inline edit rows
            $('.inline-edit-row-rating').addClass('hidden');
            $('tr.iedit').show();
            
            // Show this edit row, hide data row
            $dataRow.hide();
            $editRow.removeClass('hidden');
            
            // Focus on the input
            $editRow.find('input[name="rating_name"]').focus().select();
        });
        
        /**
         * Cancel inline edit
         */
        $(document).on('click', '.inline-edit-row-rating .cancel', function(e) {
            e.preventDefault();
            
            const $editRow = $(this).closest('.inline-edit-row-rating');
            const $dataRow = $editRow.prev('tr.iedit');
            
            // Hide edit row, show data row
            $editRow.addClass('hidden');
            $dataRow.show();
        });
        
        /**
         * Handle Escape key to cancel inline edit
         */
        $(document).on('keydown', '.inline-edit-row-rating input', function(e) {
            if (e.key === 'Escape') {
                $(this).closest('.inline-edit-row-rating').find('.cancel').click();
            }
        });

        /**
         * Copy shortcode to clipboard when clicked
         */
        $(document).on('click', '.shuriken-copy-shortcode', function(e) {
            e.preventDefault();
            const shortcode = $(this).text();
            
            // Modern clipboard API
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(shortcode).then(function() {
                    showCopyNotification();
                }).catch(function(err) {
                    console.error('Failed to copy: ', err);
                    fallbackCopy(shortcode);
                });
            } else {
                fallbackCopy(shortcode);
            }
        });
        
        /**
         * Fallback copy method for older browsers
         */
        function fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showCopyNotification();
            } catch (err) {
                console.error('Failed to copy: ', err);
            }
            
            textArea.remove();
        }

        /**
         * Show copy notification
         */
        function showCopyNotification() {
            // Remove any existing notifications
            $('.shuriken-copy-notification').remove();
            
            const notification = $('<div class="shuriken-copy-notification">' + shurikenRatingsAdmin.i18n.copied + '</div>');
            $('body').append(notification);
            
            setTimeout(function() {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 2000);
        }

        /**
         * Select all checkboxes
         */
        $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('input[name="rating_ids[]"]').prop('checked', isChecked);
            $('#cb-select-all-1, #cb-select-all-2').prop('checked', isChecked);
        });

        /**
         * Update "select all" checkbox state when individual checkboxes change
         */
        $(document).on('change', 'input[name="rating_ids[]"]', function() {
            const totalCheckboxes = $('input[name="rating_ids[]"]').length;
            const checkedCheckboxes = $('input[name="rating_ids[]"]:checked').length;
            const allChecked = totalCheckboxes === checkedCheckboxes && totalCheckboxes > 0;
            
            $('#cb-select-all-1, #cb-select-all-2').prop('checked', allChecked);
        });

        /**
         * Handle page number input
         */
        $('#current-page-selector').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                const page = parseInt($(this).val(), 10);
                const totalPages = parseInt($('.total-pages').first().text().replace(/,/g, ''), 10);
                
                if (page >= 1 && page <= totalPages) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('paged', page);
                    window.location.href = url.toString();
                }
            }
        });

        /**
         * Add keyboard support for shortcode copy
         */
        $(document).on('keydown', '.shuriken-copy-shortcode', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).click();
            }
        });

        /**
         * Toggle row for mobile view
         */
        $(document).on('click', '.toggle-row', function(e) {
            e.preventDefault();
            $(this).closest('tr').toggleClass('is-expanded');
        });

    });

})(jQuery);
