<?php
/**
 * Shuriken Reviews Voter Activity Page
 *
 * Displays voting history and statistics for a specific voter (member or guest).
 *
 * @package Shuriken_Reviews
 * @since 1.9.1
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get analytics instance
$analytics = shuriken_analytics();

// Get voter identifier (user_id for members, user_ip for guests)
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$user_ip = isset($_GET['user_ip']) ? sanitize_text_field($_GET['user_ip']) : '';

// Validate that we have either a user ID or IP
if ($user_id <= 0 && empty($user_ip)) {
    wp_die(__('Invalid voter identifier. Please provide a user ID or IP address.', 'shuriken-reviews'));
}

// Determine voter type
$is_member = $user_id > 0;
$voter_identifier = $is_member ? $user_id : $user_ip;

// Get user info for members
$user_info = $is_member ? $analytics->get_user_info($user_id) : null;

// Parse date range from request parameters
$date_range = $analytics->parse_date_range_params($_GET);
$date_range_label = $analytics->get_date_range_label($date_range);

// Determine current UI state for date filter
$range_type = isset($_GET['range_type']) ? sanitize_text_field($_GET['range_type']) : 'preset';
$preset_value = is_array($date_range) ? '30' : $date_range;
$start_date = is_array($date_range) && !empty($date_range['start']) ? $date_range['start'] : '';
$end_date = is_array($date_range) && !empty($date_range['end']) ? $date_range['end'] : '';

// Get voter stats
$stats = $analytics->get_voter_stats($user_id, $user_ip, $date_range);
$distribution_array = $analytics->get_voter_rating_distribution($user_id, $user_ip, $date_range);
$votes_over_time = $analytics->get_voter_activity_over_time($user_id, $user_ip, $date_range);

// Pagination for votes
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

// Get paginated votes
$votes_result = $analytics->get_voter_votes_paginated($user_id, $user_ip, $current_page, $per_page, $date_range);
$votes = $votes_result->votes;
$total_votes_count = $votes_result->total_count;
$total_pages = $votes_result->total_pages;
$offset = ($current_page - 1) * $per_page;

// Back URL
$back_url = admin_url('admin.php?page=shuriken-reviews-analytics');

// Base URL for filters (preserves voter identifier)
$base_filter_url = $is_member 
    ? admin_url('admin.php?page=shuriken-reviews-voter-activity&user_id=' . $user_id)
    : admin_url('admin.php?page=shuriken-reviews-voter-activity&user_ip=' . urlencode($user_ip));

// Voting tendency labels and colors
$tendency_labels = array(
    'generous' => __('Generous Rater', 'shuriken-reviews'),
    'balanced' => __('Balanced Rater', 'shuriken-reviews'),
    'critical' => __('Critical Rater', 'shuriken-reviews'),
    'none' => __('No Data', 'shuriken-reviews'),
);
$tendency_icons = array(
    'generous' => 'thumbs-up',
    'balanced' => 'editor-aligncenter',
    'critical' => 'thumbs-down',
    'none' => 'minus',
);
?>

<div class="wrap shuriken-analytics shuriken-voter-activity">
    <h1>
        <a href="<?php echo esc_url($back_url); ?>" class="page-title-action shuriken-back-btn">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php esc_html_e('Back to Analytics', 'shuriken-reviews'); ?>
        </a>
        <?php esc_html_e('Voter Activity', 'shuriken-reviews'); ?>
    </h1>
    
    <!-- Voter Info Card -->
    <div class="shuriken-voter-card">
        <?php if ($is_member && $user_info) : ?>
            <div class="voter-avatar">
                <img src="<?php echo esc_url($user_info->avatar_url); ?>" alt="<?php echo esc_attr($user_info->display_name); ?>">
            </div>
            <div class="voter-details">
                <h2><?php echo esc_html($user_info->display_name); ?></h2>
                <p class="voter-meta">
                    <span class="voter-type-badge member">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php esc_html_e('Registered Member', 'shuriken-reviews'); ?>
                    </span>
                    <span class="voter-email"><?php echo esc_html($user_info->user_email); ?></span>
                </p>
                <p class="voter-extra">
                    <span><?php printf(esc_html__('Username: %s', 'shuriken-reviews'), '<strong>' . esc_html($user_info->user_login) . '</strong>'); ?></span>
                    <span><?php printf(esc_html__('Registered: %s', 'shuriken-reviews'), '<strong>' . esc_html(mysql2date(get_option('date_format'), $user_info->user_registered)) . '</strong>'); ?></span>
                    <span><?php printf(esc_html__('Role: %s', 'shuriken-reviews'), '<strong>' . esc_html(ucfirst(implode(', ', $user_info->roles))) . '</strong>'); ?></span>
                </p>
                <p class="voter-actions">
                    <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $user_id)); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php esc_html_e('View User Profile', 'shuriken-reviews'); ?>
                    </a>
                </p>
            </div>
        <?php elseif ($is_member && !$user_info) : ?>
            <div class="voter-avatar deleted">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="voter-details">
                <h2><?php esc_html_e('Deleted User', 'shuriken-reviews'); ?></h2>
                <p class="voter-meta">
                    <span class="voter-type-badge member">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php esc_html_e('Former Member', 'shuriken-reviews'); ?>
                    </span>
                    <span><?php printf(esc_html__('User ID: %d', 'shuriken-reviews'), $user_id); ?></span>
                </p>
            </div>
        <?php else : ?>
            <div class="voter-avatar guest">
                <span class="dashicons dashicons-businessperson"></span>
            </div>
            <div class="voter-details">
                <h2><?php esc_html_e('Guest Voter', 'shuriken-reviews'); ?></h2>
                <p class="voter-meta">
                    <span class="voter-type-badge guest">
                        <span class="dashicons dashicons-businessperson"></span>
                        <?php esc_html_e('Guest', 'shuriken-reviews'); ?>
                    </span>
                    <span class="voter-ip">
                        <?php esc_html_e('IP Address:', 'shuriken-reviews'); ?>
                        <code><?php echo esc_html($user_ip); ?></code>
                    </span>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Date Range Filter -->
    <div class="shuriken-filter-bar">
        <form method="get" action="" id="shuriken-voter-date-filter-form">
            <input type="hidden" name="page" value="shuriken-reviews-voter-activity">
            <?php if ($is_member) : ?>
                <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
            <?php else : ?>
                <input type="hidden" name="user_ip" value="<?php echo esc_attr($user_ip); ?>">
            <?php endif; ?>
            <input type="hidden" name="range_type" id="voter_range_type" value="<?php echo esc_attr($range_type); ?>">
            
            <div class="filter-row">
                <label for="voter_date_range"><?php esc_html_e('Time Period:', 'shuriken-reviews'); ?></label>
                <select name="date_range" id="voter_date_range" class="preset-select">
                    <option value="7" <?php selected($preset_value, '7'); ?>><?php esc_html_e('Last 7 Days', 'shuriken-reviews'); ?></option>
                    <option value="30" <?php selected($preset_value, '30'); ?>><?php esc_html_e('Last 30 Days', 'shuriken-reviews'); ?></option>
                    <option value="90" <?php selected($preset_value, '90'); ?>><?php esc_html_e('Last 90 Days', 'shuriken-reviews'); ?></option>
                    <option value="365" <?php selected($preset_value, '365'); ?>><?php esc_html_e('Last Year', 'shuriken-reviews'); ?></option>
                    <option value="all" <?php selected($preset_value, 'all'); ?>><?php esc_html_e('All Time', 'shuriken-reviews'); ?></option>
                    <option value="custom" <?php selected($range_type, 'custom'); ?>><?php esc_html_e('Custom Range...', 'shuriken-reviews'); ?></option>
                </select>
                
                <div class="custom-date-range" style="<?php echo $range_type === 'custom' ? '' : 'display: none;'; ?>">
                    <label for="voter_start_date"><?php esc_html_e('From:', 'shuriken-reviews'); ?></label>
                    <input type="date" name="start_date" id="voter_start_date" value="<?php echo esc_attr($start_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">
                    
                    <label for="voter_end_date"><?php esc_html_e('To:', 'shuriken-reviews'); ?></label>
                    <input type="date" name="end_date" id="voter_end_date" value="<?php echo esc_attr($end_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">
                    
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
    
    <!-- Overview Stats Cards -->
    <div class="shuriken-stats-grid">
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-chart-bar"></span>
            <div class="stat-content">
                <h3><?php echo esc_html($stats->total_votes ?: 0); ?></h3>
                <p><?php esc_html_e('Total Votes', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-star-filled"></span>
            <div class="stat-content">
                <h3><?php echo esc_html($stats->average_rating_given ?: '0'); ?>/5</h3>
                <p><?php esc_html_e('Average Rating Given', 'shuriken-reviews'); ?></p>
                <?php if ($stats->average_effective_rating && $stats->average_effective_rating !== $stats->average_rating_given) : ?>
                    <small class="effective-rating-note" title="<?php esc_attr_e('Rating adjusted for negative-effect sub-ratings', 'shuriken-reviews'); ?>">
                        <?php printf(
                            /* translators: %s: effective rating value */
                            esc_html__('Effective: %s/5', 'shuriken-reviews'),
                            esc_html($stats->average_effective_rating)
                        ); ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-format-gallery"></span>
            <div class="stat-content">
                <h3><?php echo esc_html($stats->unique_items_rated ?: 0); ?></h3>
                <p><?php esc_html_e('Items Rated', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-<?php echo esc_attr($tendency_icons[$stats->voting_tendency]); ?>"></span>
            <div class="stat-content">
                <h3 class="tendency-<?php echo esc_attr($stats->voting_tendency); ?>">
                    <?php echo esc_html($tendency_labels[$stats->voting_tendency]); ?>
                </h3>
                <p><?php esc_html_e('Voting Style', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Secondary Stats -->
    <div class="shuriken-stats-grid secondary">
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($stats->positive_votes ?: 0); ?></h4>
                <p><?php esc_html_e('Positive (4-5★)', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($stats->neutral_votes ?: 0); ?></h4>
                <p><?php esc_html_e('Neutral (3★)', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($stats->negative_votes ?: 0); ?></h4>
                <p><?php esc_html_e('Negative (1-2★)', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($analytics->format_time_ago($stats->first_vote)); ?></h4>
                <p><?php esc_html_e('First Vote', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($analytics->format_time_ago($stats->last_vote)); ?></h4>
                <p><?php esc_html_e('Last Vote', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="shuriken-charts-row">
        <!-- Rating Distribution -->
        <div class="shuriken-chart-card">
            <h2><?php esc_html_e('Ratings Given Distribution', 'shuriken-reviews'); ?></h2>
            <div class="chart-container">
                <canvas id="voterRatingDistributionChart"></canvas>
            </div>
        </div>
        
        <!-- Activity Over Time -->
        <div class="shuriken-chart-card wide">
            <h2><?php esc_html_e('Voting Activity', 'shuriken-reviews'); ?></h2>
            <div class="chart-container">
                <canvas id="voterActivityChart"></canvas>
            </div>
        </div>
    </div>
    
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
                    <th class="column-item"><?php esc_html_e('Rating Item', 'shuriken-reviews'); ?></th>
                    <th class="column-rating"><?php esc_html_e('Rating Given', 'shuriken-reviews'); ?></th>
                    <th class="column-date"><?php esc_html_e('Date Created', 'shuriken-reviews'); ?></th>
                    <th class="column-modified"><?php esc_html_e('Last Modified', 'shuriken-reviews'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($votes) : ?>
                    <?php foreach ($votes as $vote) : 
                        $item_stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $vote->rating_id);
                    ?>
                        <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($item_stats_url); ?>">
                            <td class="column-id"><?php echo esc_html($vote->id); ?></td>
                            <td class="column-item">
                                <a href="<?php echo esc_url($item_stats_url); ?>" class="rating-item-link">
                                    <?php echo esc_html($vote->rating_name); ?>
                                </a>
                                <?php if ($vote->parent_id) : ?>
                                    <span class="sub-rating-indicator" title="<?php esc_attr_e('Sub-rating', 'shuriken-reviews'); ?>">
                                        <span class="dashicons dashicons-arrow-right-alt"></span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="column-rating">
                                <span class="star-rating-display">
                                    <?php echo str_repeat('★', intval($vote->rating_value)) . str_repeat('☆', 5 - intval($vote->rating_value)); ?>
                                </span>
                                <span class="rating-number">(<?php echo esc_html($vote->rating_value); ?>)</span>
                            </td>
                            <td class="column-date">
                                <?php echo esc_html($analytics->format_date($vote->date_created)); ?>
                                <br>
                                <small class="timeago"><?php echo esc_html($analytics->format_time_ago($vote->date_created)); ?></small>
                            </td>
                            <td class="column-modified">
                                <?php if ($vote->date_modified !== $vote->date_created) : ?>
                                    <?php echo esc_html($analytics->format_date($vote->date_modified)); ?>
                                    <br>
                                    <small class="timeago"><?php echo esc_html($analytics->format_time_ago($vote->date_modified)); ?></small>
                                <?php else : ?>
                                    <span class="no-modification">—</span>
                                <?php endif; ?>
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
        <p>
            <?php 
            if ($is_member && $user_info) {
                printf(esc_html__('Download all votes by "%s" as CSV.', 'shuriken-reviews'), esc_html($user_info->display_name));
            } elseif ($is_member) {
                printf(esc_html__('Download all votes by User ID %d as CSV.', 'shuriken-reviews'), $user_id);
            } else {
                printf(esc_html__('Download all votes from IP %s as CSV.', 'shuriken-reviews'), esc_html($user_ip));
            }
            ?>
        </p>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('shuriken_export_voter_votes', 'shuriken_export_voter_nonce'); ?>
            <input type="hidden" name="action" value="shuriken_export_voter_votes">
            <?php if ($is_member) : ?>
                <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
            <?php else : ?>
                <input type="hidden" name="user_ip" value="<?php echo esc_attr($user_ip); ?>">
            <?php endif; ?>
            <button type="submit" class="button button-secondary">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export Votes to CSV', 'shuriken-reviews'); ?>
            </button>
        </form>
    </div>
</div>

<script>
// Pass PHP data to JavaScript for charts
var shurikenVoterActivityData = {
    ratingDistribution: <?php echo wp_json_encode(array_values($distribution_array)); ?>,
    votesOverTime: <?php echo wp_json_encode($votes_over_time); ?>,
    i18n: {
        votes: <?php echo wp_json_encode(__('Votes', 'shuriken-reviews')); ?>,
        stars: <?php echo wp_json_encode(__('Stars', 'shuriken-reviews')); ?>
    }
};

// Initialize charts and filters when DOM is ready
jQuery(document).ready(function($) {
    // Date range filter handling
    var $voterDateSelect = $('#voter_date_range');
    var $voterCustomRange = $('#shuriken-voter-date-filter-form .custom-date-range');
    var $voterRangeType = $('#voter_range_type');
    var $voterForm = $('#shuriken-voter-date-filter-form');
    
    $voterDateSelect.on('change', function() {
        if ($(this).val() === 'custom') {
            $voterCustomRange.slideDown(200);
            $voterRangeType.val('custom');
        } else {
            $voterCustomRange.slideUp(200);
            $voterRangeType.val('preset');
            $voterForm.submit();
        }
    });
    
    $voterForm.on('submit', function(e) {
        if ($voterRangeType.val() === 'custom') {
            var startDate = $('#voter_start_date').val();
            var endDate = $('#voter_end_date').val();
            
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
    
    // Initialize charts if Chart.js is available
    if (typeof Chart !== 'undefined') {
        // Rating Distribution Chart
        var distCtx = document.getElementById('voterRatingDistributionChart');
        if (distCtx) {
            new Chart(distCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['1★', '2★', '3★', '4★', '5★'],
                    datasets: [{
                        label: shurikenVoterActivityData.i18n.votes,
                        data: shurikenVoterActivityData.ratingDistribution,
                        backgroundColor: [
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(249, 115, 22, 0.8)',
                            'rgba(234, 179, 8, 0.8)',
                            'rgba(132, 204, 22, 0.8)',
                            'rgba(34, 197, 94, 0.8)'
                        ],
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        }
        
        // Activity Over Time Chart
        var activityCtx = document.getElementById('voterActivityChart');
        if (activityCtx && shurikenVoterActivityData.votesOverTime.length > 0) {
            var labels = shurikenVoterActivityData.votesOverTime.map(function(item) {
                return item.vote_date;
            });
            var data = shurikenVoterActivityData.votesOverTime.map(function(item) {
                return parseInt(item.vote_count, 10);
            });
            
            new Chart(activityCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: shurikenVoterActivityData.i18n.votes,
                        data: data,
                        borderColor: 'rgba(102, 126, 234, 1)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: 'rgba(102, 126, 234, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0
                            }
                        }
                    }
                }
            });
        }
    }
    
    // Clickable rows
    $('.shuriken-clickable-row').on('click', function(e) {
        if ($(e.target).is('a') || $(e.target).closest('a').length) {
            return;
        }
        var href = $(this).data('href');
        if (href) {
            window.location.href = href;
        }
    });
});
</script>
