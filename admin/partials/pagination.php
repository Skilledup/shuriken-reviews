<?php
/**
 * Pagination partial for admin tables
 *
 * @var int    $total_pages  Total number of pages
 * @var int    $current_page Current page number
 * @var int    $total_count  Total item count
 * @var string $page_arg     Query arg name (default: 'paged')
 * @var string $singular     Singular noun for count (default: 'vote')
 * @var string $plural       Plural noun for count (default: 'votes')
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_arg = $page_arg ?? 'paged';
$singular = $singular ?? __('vote', 'shuriken-reviews');
$plural   = $plural ?? __('votes', 'shuriken-reviews');

if ($total_pages > 1) : ?>
<div class="tablenav bottom">
    <div class="tablenav-pages">
        <span class="displaying-num">
            <?php
            printf(
                esc_html(_n('%s ' . $singular, '%s ' . $plural, $total_count, 'shuriken-reviews')),
                number_format_i18n($total_count)
            );
            ?>
        </span>
        <span class="pagination-links">
            <?php
            echo paginate_links(array(
                'base'      => add_query_arg($page_arg, '%#%'),
                'format'    => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total'     => $total_pages,
                'current'   => $current_page,
            ));
            ?>
        </span>
    </div>
</div>
<?php endif; ?>
