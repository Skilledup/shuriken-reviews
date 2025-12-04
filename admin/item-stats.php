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

// Get detailed stats for this item
$stats = $analytics->get_rating_stats($rating_id);

// Extract values for template
$average = $stats->average;
$member_votes = $stats->member_votes;
$guest_votes_count = $stats->guest_votes;
$unique_voters = $stats->unique_voters;
$first_vote = $stats->first_vote;
$last_vote = $stats->last_vote;
$distribution_array = $stats->distribution;
$votes_over_time = $stats->votes_over_time;

// Pagination for votes
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

// Get paginated votes
$votes_result = $analytics->get_rating_votes_paginated($rating_id, $current_page, $per_page);
$votes = $votes_result->votes;
$total_votes_count = $votes_result->total_count;
$total_pages = $votes_result->total_pages;
$offset = ($current_page - 1) * $per_page;

// Back URL
$back_url = admin_url('admin.php?page=shuriken-reviews-analytics');
$edit_url = admin_url('admin.php?page=shuriken-reviews&s=' . urlencode($rating->name) . '#rating-' . $rating->id);
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
                <h3><?php echo esc_html($rating->total_votes); ?></h3>
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
                <h3><?php echo esc_html($rating->total_rating); ?></h3>
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

// Initialize charts when DOM is ready
jQuery(document).ready(function($) {
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
