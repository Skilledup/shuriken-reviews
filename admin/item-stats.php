<?php
/**
 * Shuriken Reviews Item Stats Page
 *
 * Displays detailed statistics for a single rating item.
 *
 * @package Shuriken_Reviews
 * @since 1.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get analytics instance
$analytics = shuriken_analytics();

// Get and validate rating ID
$rating_id = isset($_GET['rating_id']) ? intval($_GET['rating_id']) : 0;

if (!$rating_id) {
    wp_die(__('Invalid rating ID', 'shuriken-reviews'));
}

// Get rating info with stats
$rating = $analytics->get_rating($rating_id);

if (!$rating) {
    wp_die(__('Rating not found', 'shuriken-reviews'));
}

// Parse date range from request parameters
$date_range = $analytics->parse_date_range_params($_GET);
$date_range_label = $analytics->get_date_range_label($date_range);

// Determine current UI state for date filter
$range_type = isset($_GET['range_type']) ? sanitize_text_field($_GET['range_type']) : 'preset';
$preset_value = is_array($date_range) ? '30' : $date_range;
$start_date = is_array($date_range) && !empty($date_range['start']) ? $date_range['start'] : '';
$end_date = is_array($date_range) && !empty($date_range['end']) ? $date_range['end'] : '';

// Get detailed stats for this item with date range
$stats = $analytics->get_rating_stats($rating_id, $date_range);

// Get hierarchy-related data
$is_parent = $analytics->has_sub_ratings($rating_id);
$is_sub = !empty($rating->parent_id);
$is_mirror = !empty($rating->mirror_of);
$sub_ratings = $is_parent ? $analytics->get_sub_ratings_contribution($rating_id) : array();
$parent_rating = $is_sub ? $analytics->get_rating($rating->parent_id) : null;
$source_rating = $is_mirror ? $analytics->get_rating($rating->mirror_of) : null;

// For parent ratings, get breakdown data (direct, subs, total) with date range support
$stats_breakdown = $is_parent ? $analytics->get_parent_rating_stats_breakdown($rating_id, $date_range) : null;
$current_view = isset($_GET['view']) && in_array($_GET['view'], array('direct', 'subs', 'total')) ? $_GET['view'] : 'total';

// Extract values for template (use breakdown if parent rating, otherwise use date-filtered stats)
if ($is_parent && $stats_breakdown) {
    // For parent ratings with view selector, use breakdown data with date range applied
    $current_stats = $stats_breakdown->$current_view;
    $average = $current_stats->average;
    $member_votes = $current_stats->member_votes;
    $guest_votes_count = $current_stats->guest_votes;
    $unique_voters = $current_stats->unique_voters;
    $first_vote = $current_stats->first_vote;
    $last_vote = $current_stats->last_vote;
    $distribution_array = $current_stats->distribution;
    $votes_over_time = $current_stats->votes_over_time;
    $display_total_votes = $current_stats->total_votes;
    $display_total_rating = $current_stats->total_rating;
} else {
    // For non-parent ratings, use date-filtered stats
    $average = $stats->average;
    $member_votes = $stats->member_votes;
    $guest_votes_count = $stats->guest_votes;
    $unique_voters = $stats->unique_voters;
    $first_vote = $stats->first_vote;
    $last_vote = $stats->last_vote;
    $distribution_array = $stats->distribution;
    $votes_over_time = $stats->votes_over_time;
    $display_total_votes = $stats->total_votes;
    $display_total_rating = $stats->total_rating;
}

// Pagination for votes
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

// Get paginated votes (with view filter for parent ratings)
$vote_view = $is_parent ? $current_view : 'direct';
$votes_result = $analytics->get_rating_votes_paginated($rating_id, $current_page, $per_page, $date_range, $vote_view);
$votes = $votes_result->votes;
$total_votes_count = $votes_result->total_count;
$total_pages = $votes_result->total_pages;
$offset = ($current_page - 1) * $per_page;

// Back URL
$back_url = admin_url('admin.php?page=shuriken-reviews-analytics');
$edit_url = admin_url('admin.php?page=shuriken-reviews&s=' . urlencode($rating->name) . '#rating-' . $rating->id);

// Base URL for filters (preserves rating_id)
$base_filter_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $rating_id);
?>

<div class="wrap shuriken-analytics shuriken-item-stats">
    <h1>
        <a href="<?php echo esc_url($back_url); ?>" class="page-title-action shuriken-back-btn">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php esc_html_e('Back to Analytics', 'shuriken-reviews'); ?>
        </a>
        <?php echo esc_html($rating->name); ?>
        <a href="<?php echo esc_url($edit_url); ?>" class="page-title-action">
            <?php esc_html_e('Edit Rating', 'shuriken-reviews'); ?>
        </a>
    </h1>
    
    <p class="shuriken-item-meta">
        <?php printf(
            esc_html__('Rating ID: %d | Created: %s | Shortcode: %s', 'shuriken-reviews'),
            $rating->id,
            date_i18n(get_option('date_format'), strtotime($rating->date_created)),
            '<code>[shuriken_rating id="' . $rating->id . '"]</code>'
        ); ?>
    </p>
    
    <!-- Date Range Filter -->
    <div class="shuriken-filter-bar">
        <form method="get" action="" id="shuriken-item-date-filter-form">
            <input type="hidden" name="page" value="shuriken-reviews-item-stats">
            <input type="hidden" name="rating_id" value="<?php echo esc_attr($rating_id); ?>">
            <input type="hidden" name="range_type" id="item_range_type" value="<?php echo esc_attr($range_type); ?>">
            <?php if ($is_parent) : ?>
            <input type="hidden" name="view" value="<?php echo esc_attr($current_view); ?>">
            <?php endif; ?>
            
            <div class="filter-row">
                <label for="item_date_range"><?php esc_html_e('Time Period:', 'shuriken-reviews'); ?></label>
                <select name="date_range" id="item_date_range" class="preset-select">
                    <option value="7" <?php selected($preset_value, '7'); ?>><?php esc_html_e('Last 7 Days', 'shuriken-reviews'); ?></option>
                    <option value="30" <?php selected($preset_value, '30'); ?>><?php esc_html_e('Last 30 Days', 'shuriken-reviews'); ?></option>
                    <option value="90" <?php selected($preset_value, '90'); ?>><?php esc_html_e('Last 90 Days', 'shuriken-reviews'); ?></option>
                    <option value="365" <?php selected($preset_value, '365'); ?>><?php esc_html_e('Last Year', 'shuriken-reviews'); ?></option>
                    <option value="all" <?php selected($preset_value, 'all'); ?>><?php esc_html_e('All Time', 'shuriken-reviews'); ?></option>
                    <option value="custom" <?php selected($range_type, 'custom'); ?>><?php esc_html_e('Custom Range...', 'shuriken-reviews'); ?></option>
                </select>
                
                <div class="custom-date-range" style="<?php echo $range_type === 'custom' ? '' : 'display: none;'; ?>">
                    <label for="item_start_date"><?php esc_html_e('From:', 'shuriken-reviews'); ?></label>
                    <input type="date" name="start_date" id="item_start_date" value="<?php echo esc_attr($start_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">
                    
                    <label for="item_end_date"><?php esc_html_e('To:', 'shuriken-reviews'); ?></label>
                    <input type="date" name="end_date" id="item_end_date" value="<?php echo esc_attr($end_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">
                    
                    <button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'shuriken-reviews'); ?></button>
                </div>
            </div>
            
            <?php if ($range_type === 'custom' && ($start_date || $end_date)) : ?>
            <div class="current-range-label">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php echo esc_html($date_range_label); ?>
                <a href="<?php echo esc_url($base_filter_url); ?>" class="clear-filter">
                    <?php esc_html_e('Clear', 'shuriken-reviews'); ?>
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if ($is_mirror || $is_parent || $is_sub || !empty($rating->display_only)) : ?>
    <!-- Hierarchy Info -->
    <div class="shuriken-hierarchy-info">
        <?php if ($is_mirror && $source_rating) : ?>
            <span class="hierarchy-badge mirror">
                <span class="dashicons dashicons-admin-links"></span>
                <?php printf(
                    esc_html__('Mirror of: %s', 'shuriken-reviews'),
                    '<a href="' . esc_url(admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $source_rating->id)) . '">' . esc_html($source_rating->name) . '</a>'
                ); ?>
            </span>
        <?php endif; ?>
        
        <?php if ($is_parent) : ?>
            <span class="hierarchy-badge parent">
                <span class="dashicons dashicons-networking"></span>
                <?php printf(
                    esc_html__('Parent Rating with %d sub-ratings', 'shuriken-reviews'),
                    count($sub_ratings)
                ); ?>
            </span>
        <?php endif; ?>
        
        <?php if ($is_sub && $parent_rating) : ?>
            <span class="hierarchy-badge sub <?php echo esc_attr($rating->effect_type); ?>">
                <span class="dashicons dashicons-arrow-<?php echo $rating->effect_type === 'positive' ? 'up' : 'down'; ?>-alt"></span>
                <?php printf(
                    esc_html__('Sub-rating of: %s (%s effect)', 'shuriken-reviews'),
                    '<a href="' . esc_url(admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $parent_rating->id)) . '">' . esc_html($parent_rating->name) . '</a>',
                    $rating->effect_type
                ); ?>
            </span>
        <?php endif; ?>
        
        <?php if (!empty($rating->display_only)) : ?>
            <span class="hierarchy-badge display-only">
                <span class="dashicons dashicons-hidden"></span>
                <?php esc_html_e('Display Only (calculated from sub-ratings)', 'shuriken-reviews'); ?>
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($is_parent && $stats_breakdown) : ?>
    <!-- View Selector for Parent Ratings -->
    <div class="shuriken-view-selector">
        <label><?php esc_html_e('Display data from:', 'shuriken-reviews'); ?></label>
        <div class="view-selector-buttons">
            <?php 
            $base_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $rating_id);
            $views = array(
                'total' => __('Total (Combined)', 'shuriken-reviews'),
                'direct' => __('Direct Votes Only', 'shuriken-reviews'),
                'subs' => __('From Sub-ratings Only', 'shuriken-reviews'),
            );
            foreach ($views as $view_key => $view_label) :
                $is_active = ($current_view === $view_key);
                $view_url = add_query_arg('view', $view_key, $base_url);
            ?>
                <a href="<?php echo esc_url($view_url); ?>" 
                   class="button <?php echo $is_active ? 'button-primary' : 'button-secondary'; ?>">
                    <?php echo esc_html($view_label); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="view-description">
            <?php 
            switch ($current_view) {
                case 'direct':
                    esc_html_e('Showing statistics from votes cast directly on this parent rating.', 'shuriken-reviews');
                    break;
                case 'subs':
                    esc_html_e('Showing aggregated statistics from all sub-ratings (with effect type applied).', 'shuriken-reviews');
                    break;
                default:
                    esc_html_e('Showing combined statistics from both direct votes and sub-ratings.', 'shuriken-reviews');
            }
            ?>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Overview Cards -->
    <div class="shuriken-stats-grid">
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-star-filled"></span>
            <div class="stat-content">
                <h3><?php echo esc_html($average); ?>/5</h3>
                <p><?php esc_html_e('Average Rating', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-chart-bar"></span>
            <div class="stat-content">
                <h3><?php echo esc_html($display_total_votes); ?></h3>
                <p><?php esc_html_e('Total Votes', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-groups"></span>
            <div class="stat-content">
                <h3><?php echo esc_html($unique_voters ?: 0); ?></h3>
                <p><?php esc_html_e('Unique Voters', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-calculator"></span>
            <div class="stat-content">
                <h3><?php echo esc_html($display_total_rating); ?></h3>
                <p><?php esc_html_e('Total Points', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Secondary Stats -->
    <div class="shuriken-stats-grid secondary">
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($member_votes ?: 0); ?></h4>
                <p><?php esc_html_e('Member Votes', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($guest_votes_count ?: 0); ?></h4>
                <p><?php esc_html_e('Guest Votes', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($analytics->format_time_ago($first_vote)); ?></h4>
                <p><?php esc_html_e('First Vote', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($analytics->format_time_ago($last_vote)); ?></h4>
                <p><?php esc_html_e('Last Vote', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="shuriken-charts-row">
        <!-- Rating Distribution -->
        <div class="shuriken-chart-card">
            <h2><?php esc_html_e('Rating Distribution', 'shuriken-reviews'); ?></h2>
            <div class="chart-container">
                <canvas id="itemRatingDistributionChart"></canvas>
            </div>
        </div>
        
        <!-- Votes Over Time -->
        <div class="shuriken-chart-card wide">
            <h2><?php esc_html_e('Voting Activity (Last 30 Days)', 'shuriken-reviews'); ?></h2>
            <div class="chart-container">
                <canvas id="itemVotesOverTimeChart"></canvas>
            </div>
        </div>
    </div>
    
    <?php if ($is_parent && !empty($sub_ratings)) : ?>
    <!-- Sub-Ratings Breakdown -->
    <div class="shuriken-table-card full-width sub-ratings-breakdown">
        <h2>
            <span class="dashicons dashicons-networking"></span>
            <?php esc_html_e('Sub-Ratings Contribution', 'shuriken-reviews'); ?>
        </h2>
        <p class="table-description">
            <?php esc_html_e('How each sub-rating contributes to this parent rating', 'shuriken-reviews'); ?>
        </p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Sub-Rating', 'shuriken-reviews'); ?></th>
                    <th><?php esc_html_e('Effect', 'shuriken-reviews'); ?></th>
                    <th><?php esc_html_e('Average', 'shuriken-reviews'); ?></th>
                    <th><?php esc_html_e('Effective Score', 'shuriken-reviews'); ?></th>
                    <th><?php esc_html_e('Votes', 'shuriken-reviews'); ?></th>
                    <th><?php esc_html_e('Vote Share', 'shuriken-reviews'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sub_ratings as $sub) : 
                    $sub_stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $sub->id);
                ?>
                    <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($sub_stats_url); ?>">
                        <td>
                            <a href="<?php echo esc_url($sub_stats_url); ?>" class="rating-item-link">
                                <?php echo esc_html($sub->name); ?>
                            </a>
                        </td>
                        <td>
                            <span class="effect-indicator <?php echo esc_attr($sub->effect_type); ?>">
                                <?php echo $sub->effect_type === 'positive' ? '+' : '-'; ?>
                                <?php echo esc_html(ucfirst($sub->effect_type)); ?>
                            </span>
                        </td>
                        <td>
                            <span class="star-display">★</span>
                            <?php echo esc_html($sub->average ?: '0'); ?>/5
                        </td>
                        <td>
                            <span class="effective-score <?php echo $sub->effect_type === 'negative' ? 'inverted' : ''; ?>">
                                <?php echo esc_html($sub->effective_average ?: '0'); ?>/5
                            </span>
                            <?php if ($sub->effect_type === 'negative') : ?>
                                <small class="inverted-note"><?php esc_html_e('(inverted)', 'shuriken-reviews'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($sub->total_votes); ?></td>
                        <td>
                            <div class="vote-share-bar">
                                <div class="vote-share-fill" style="width: <?php echo esc_attr(min(100, $sub->vote_contribution_percent)); ?>%;"></div>
                            </div>
                            <span class="vote-share-percent"><?php echo esc_html($sub->vote_contribution_percent); ?>%</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Votes Table -->
    <div class="shuriken-table-card full-width">
        <h2>
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e('Vote History', 'shuriken-reviews'); ?>
        </h2>
        <p class="table-description">
            <?php printf(
                esc_html__('Showing %1$d-%2$d of %3$d votes', 'shuriken-reviews'),
                min($offset + 1, $total_votes_count),
                min($offset + $per_page, $total_votes_count),
                $total_votes_count
            ); ?>
        </p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-id"><?php esc_html_e('ID', 'shuriken-reviews'); ?></th>
                    <th class="column-rating"><?php esc_html_e('Rating', 'shuriken-reviews'); ?></th>
                    <th class="column-voter"><?php esc_html_e('Voter', 'shuriken-reviews'); ?></th>
                    <th class="column-ip"><?php esc_html_e('IP Address', 'shuriken-reviews'); ?></th>
                    <th class="column-date"><?php esc_html_e('Date & Time', 'shuriken-reviews'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($votes) : ?>
                    <?php foreach ($votes as $vote) : ?>
                        <tr>
                            <td class="column-id"><?php echo esc_html($vote->id); ?></td>
                            <td class="column-rating">
                                <span class="star-rating-display">
                                    <?php echo str_repeat('★', intval($vote->rating_value)); ?>
                                    <?php echo str_repeat('☆', 5 - intval($vote->rating_value)); ?>
                                </span>
                                <span class="rating-number">(<?php echo esc_html($vote->rating_value); ?>)</span>
                            </td>
                            <td class="column-voter">
                                <?php if ($vote->user_id > 0) : ?>
                                    <span class="voter-type member" title="<?php esc_attr_e('Registered Member', 'shuriken-reviews'); ?>">
                                        <span class="dashicons dashicons-admin-users"></span>
                                    </span>
                                    <?php if ($vote->display_name) : ?>
                                        <strong><?php echo esc_html($vote->display_name); ?></strong>
                                        <br><small><?php echo esc_html($vote->user_email); ?></small>
                                    <?php else : ?>
                                        <em><?php esc_html_e('Deleted User', 'shuriken-reviews'); ?></em>
                                        <br><small><?php printf(esc_html__('User ID: %d', 'shuriken-reviews'), $vote->user_id); ?></small>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="voter-type guest" title="<?php esc_attr_e('Guest', 'shuriken-reviews'); ?>">
                                        <span class="dashicons dashicons-businessperson"></span>
                                    </span>
                                    <em><?php esc_html_e('Guest', 'shuriken-reviews'); ?></em>
                                <?php endif; ?>
                            </td>
                            <td class="column-ip">
                                <?php if (empty($vote->user_ip)) : ?>
                                    <em><?php esc_html_e('N/A', 'shuriken-reviews'); ?></em>
                                <?php else : ?>
                                    <code><?php echo esc_html($vote->user_ip); ?></code>
                                <?php endif; ?>
                            </td>
                            <td class="column-date">
                                <?php echo esc_html($analytics->format_date($vote->date_created)); ?>
                                <br>
                                <small class="timeago"><?php echo esc_html($analytics->format_time_ago($vote->date_created)); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No votes recorded yet', 'shuriken-reviews'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(esc_html(_n('%s vote', '%s votes', $total_votes_count, 'shuriken-reviews')), number_format_i18n($total_votes_count)); ?>
                    </span>
                    <span class="pagination-links">
                        <?php
                        $page_links = paginate_links(array(
                            'base'      => add_query_arg('paged', '%#%'),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $total_pages,
                            'current'   => $current_page,
                        ));
                        echo $page_links;
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Export Section -->
    <div class="shuriken-export-section">
        <h2><?php esc_html_e('Export Votes', 'shuriken-reviews'); ?></h2>
        <p><?php printf(esc_html__('Download all votes for "%s" as CSV.', 'shuriken-reviews'), esc_html($rating->name)); ?></p>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('shuriken_export_item_votes', 'shuriken_export_item_nonce'); ?>
            <input type="hidden" name="action" value="shuriken_export_item_votes">
            <input type="hidden" name="rating_id" value="<?php echo esc_attr($rating_id); ?>">
            <button type="submit" class="button button-secondary">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export Votes to CSV', 'shuriken-reviews'); ?>
            </button>
        </form>
    </div>
</div>

<script>
// Pass PHP data to JavaScript for charts
var shurikenItemStatsData = {
    ratingDistribution: <?php echo wp_json_encode(array_values($distribution_array)); ?>,
    votesOverTime: <?php echo wp_json_encode($votes_over_time); ?>,
    i18n: {
        votes: <?php echo wp_json_encode(__('Votes', 'shuriken-reviews')); ?>,
        stars: <?php echo wp_json_encode(__('Stars', 'shuriken-reviews')); ?>
    }
};

// Initialize charts and filters when DOM is ready
jQuery(document).ready(function($) {
    // Date range filter handling for item stats (runs regardless of Chart.js)
    var $itemDateSelect = $('#item_date_range');
    var $itemCustomRange = $('#shuriken-item-date-filter-form .custom-date-range');
    var $itemRangeType = $('#item_range_type');
    var $itemForm = $('#shuriken-item-date-filter-form');
    
    $itemDateSelect.on('change', function() {
        if ($(this).val() === 'custom') {
            $itemCustomRange.slideDown(200);
            $itemRangeType.val('custom');
        } else {
            $itemCustomRange.slideUp(200);
            $itemRangeType.val('preset');
            // Auto-submit for preset options
            $itemForm.submit();
        }
    });
    
    // Validate custom date range before submit
    $itemForm.on('submit', function(e) {
        if ($itemRangeType.val() === 'custom') {
            var startDate = $('#item_start_date').val();
            var endDate = $('#item_end_date').val();
            
            if (!startDate && !endDate) {
                alert(<?php echo wp_json_encode(__('Please select at least a start or end date.', 'shuriken-reviews')); ?>);
                e.preventDefault();
                return false;
            }
            
            if (startDate && endDate && startDate > endDate) {
                alert(<?php echo wp_json_encode(__('Start date must be before end date.', 'shuriken-reviews')); ?>);
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Charts require Chart.js
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded');
        return;
    }
    
    // Rating Distribution Chart
    var distCtx = document.getElementById('itemRatingDistributionChart');
    if (distCtx) {
        var colors = ['#dc3232', '#f56e28', '#ffb900', '#7ad03a', '#00a32a'];
        new Chart(distCtx, {
            type: 'bar',
            data: {
                labels: ['1 ★', '2 ★', '3 ★', '4 ★', '5 ★'],
                datasets: [{
                    label: shurikenItemStatsData.i18n.votes,
                    data: shurikenItemStatsData.ratingDistribution,
                    backgroundColor: colors,
                    borderColor: colors,
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
    
    // Votes Over Time Chart
    var timeCtx = document.getElementById('itemVotesOverTimeChart');
    if (timeCtx) {
        var data = shurikenItemStatsData.votesOverTime || [];
        var labels = data.map(function(item) {
            var date = new Date(item.vote_date);
            return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        });
        var values = data.map(function(item) { return parseInt(item.vote_count, 10); });
        
        new Chart(timeCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: shurikenItemStatsData.i18n.votes,
                    data: values,
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#2271b1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
});
</script>
