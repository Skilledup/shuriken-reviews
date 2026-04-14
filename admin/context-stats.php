<?php
/**
 * Shuriken Reviews Context Stats Page
 *
 * Displays detailed statistics for a single rating on a specific post/page/product.
 * Accessed from the Per-Post view in the item-stats page.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get analytics instance
$analytics = shuriken_analytics();

// Get and validate parameters
$rating_id = isset($_GET['rating_id']) ? intval($_GET['rating_id']) : 0;
$context_id = isset($_GET['context_id']) ? intval($_GET['context_id']) : 0;
$context_type = isset($_GET['context_type']) ? sanitize_text_field($_GET['context_type']) : '';

if (!$rating_id || !$context_id || !$context_type) {
    wp_die(__('Invalid parameters. Rating ID, Context ID, and Context Type are required.', 'shuriken-reviews'));
}

// Validate context type
$allowed_types = apply_filters('shuriken_allowed_context_types', array('post', 'page', 'product'));
if (!in_array($context_type, $allowed_types, true)) {
    wp_die(__('Invalid context type.', 'shuriken-reviews'));
}

// Get rating info
$rating = $analytics->get_rating($rating_id);
if (!$rating) {
    wp_die(__('Rating not found', 'shuriken-reviews'));
}

// Get post info
$context_post = get_post($context_id);
$context_title = $context_post ? get_the_title($context_post) : sprintf(__('#%d (%s)', 'shuriken-reviews'), $context_id, $context_type);
$context_edit_url = $context_post ? get_edit_post_link($context_id, 'raw') : '';
$context_view_url = $context_post ? get_permalink($context_id) : '';
$context_status = $context_post ? get_post_status_object($context_post->post_status) : null;

// Parse date range
$date_range = $analytics->parse_date_range_params($_GET);
$date_range_label = $analytics->get_date_range_label($date_range);

$range_type = isset($_GET['range_type']) ? sanitize_text_field($_GET['range_type']) : 'preset';
$preset_value = is_array($date_range) ? '30' : $date_range;
$start_date = is_array($date_range) && !empty($date_range['start']) ? $date_range['start'] : '';
$end_date = is_array($date_range) && !empty($date_range['end']) ? $date_range['end'] : '';

// Get context-specific stats
$stats = $analytics->get_context_rating_stats($rating_id, $context_id, $context_type, $date_range);

if (!$stats) {
    wp_die(__('No data available for this context', 'shuriken-reviews'));
}

// Extract values
$average = $stats->average;
$display_average = $stats->display_average;
$member_votes = $stats->member_votes;
$guest_votes_count = $stats->guest_votes;
$unique_voters = $stats->unique_voters;
$first_vote = $stats->first_vote;
$last_vote = $stats->last_vote;
$distribution_array = $stats->distribution;
$display_total_votes = $stats->total_votes;
$display_total_rating = $stats->total_rating;

// Pagination for votes
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$votes_sort_by = isset($_GET['votes_sort_by']) && in_array($_GET['votes_sort_by'], array('date', 'rating'), true) ? $_GET['votes_sort_by'] : 'date';
$votes_sort_order = isset($_GET['votes_sort_order']) && in_array($_GET['votes_sort_order'], array('asc', 'desc'), true) ? $_GET['votes_sort_order'] : 'desc';
$votes_result = $analytics->get_context_votes_paginated($rating_id, $context_id, $context_type, $current_page, $per_page, $date_range, $votes_sort_by, $votes_sort_order);
$votes = $votes_result->votes;
$total_votes_count = $votes_result->total_count;
$total_pages = $votes_result->total_pages;
$offset = ($current_page - 1) * $per_page;

// URLs
$back_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $rating_id . '&scope=contextual');
$base_filter_url = admin_url('admin.php?page=shuriken-reviews-context-stats&rating_id=' . $rating_id . '&context_id=' . $context_id . '&context_type=' . urlencode($context_type));

// Type-specific chart data
$rating_type = $rating->rating_type ?: 'stars';
$rating_type_enum = Shuriken_Rating_Type::tryFrom($rating_type) ?? Shuriken_Rating_Type::Stars;
$is_binary = $rating_type_enum->isBinary();
$item_scale = (int) ($rating->scale ?: 5);

if ($rating_type === 'like_dislike') {
    $approval_trend = $analytics->get_context_approval_trend($rating_id, $context_id, $context_type, $date_range);
} elseif ($rating_type === 'approval') {
    $cumulative_approvals = $analytics->get_context_cumulative_approvals($rating_id, $context_id, $context_type, $date_range);
} else {
    $dual_axis_data = $analytics->get_context_votes_with_rolling_avg($rating_id, $context_id, $context_type, $date_range, $item_scale);
}
?>

<div class="wrap shuriken-analytics shuriken-item-stats shuriken-context-stats">
    <h1>
        <a href="<?php echo esc_url($back_url); ?>" class="page-title-action shuriken-back-btn">
            <?php Shuriken_Icons::render('arrow-left', array('width' => 18, 'height' => 18)); ?>
            <?php esc_html_e('Back to Per-Post View', 'shuriken-reviews'); ?>
        </a>
        <?php echo esc_html($context_title); ?>
    </h1>
    
    <p class="shuriken-item-meta">
        <?php Shuriken_Icons::render('star', array('width' => 14, 'height' => 14)); ?>
        <?php printf(esc_html__('Rating: %s', 'shuriken-reviews'), '<strong>' . esc_html($rating->name) . '</strong>'); ?>
        &nbsp;|&nbsp;
        <span class="context-type-badge"><?php echo esc_html(ucfirst($context_type)); ?></span>
        <?php if ($context_status) : ?>
            &nbsp;|&nbsp;
            <?php printf(esc_html__('Status: %s', 'shuriken-reviews'), esc_html($context_status->label)); ?>
        <?php endif; ?>
        <?php if ($context_view_url) : ?>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url($context_view_url); ?>" target="_blank"><?php esc_html_e('View Post', 'shuriken-reviews'); ?> ↗</a>
        <?php endif; ?>
        <?php if ($context_edit_url) : ?>
            &nbsp;|&nbsp;
            <a href="<?php echo esc_url($context_edit_url); ?>"><?php esc_html_e('Edit Post', 'shuriken-reviews'); ?></a>
        <?php endif; ?>
    </p>
    
    <!-- Date Range Filter -->
    <div class="shuriken-filter-bar">
        <form method="get" action="" id="shuriken-ctx-date-filter-form">
            <input type="hidden" name="page" value="shuriken-reviews-context-stats">
            <input type="hidden" name="rating_id" value="<?php echo esc_attr($rating_id); ?>">
            <input type="hidden" name="context_id" value="<?php echo esc_attr($context_id); ?>">
            <input type="hidden" name="context_type" value="<?php echo esc_attr($context_type); ?>">
            <input type="hidden" name="range_type" id="ctx_range_type" value="<?php echo esc_attr($range_type); ?>">
            
            <div class="filter-row">
                <label for="ctx_date_range"><?php esc_html_e('Time Period:', 'shuriken-reviews'); ?></label>
                <select name="date_range" id="ctx_date_range" class="preset-select">
                    <option value="7" <?php selected($preset_value, '7'); ?>><?php esc_html_e('Last 7 Days', 'shuriken-reviews'); ?></option>
                    <option value="30" <?php selected($preset_value, '30'); ?>><?php esc_html_e('Last 30 Days', 'shuriken-reviews'); ?></option>
                    <option value="90" <?php selected($preset_value, '90'); ?>><?php esc_html_e('Last 90 Days', 'shuriken-reviews'); ?></option>
                    <option value="365" <?php selected($preset_value, '365'); ?>><?php esc_html_e('Last Year', 'shuriken-reviews'); ?></option>
                    <option value="all" <?php selected($preset_value, 'all'); ?>><?php esc_html_e('All Time', 'shuriken-reviews'); ?></option>
                    <option value="custom" <?php selected($range_type, 'custom'); ?>><?php esc_html_e('Custom Range...', 'shuriken-reviews'); ?></option>
                </select>
                
                <div class="custom-date-range" style="<?php echo $range_type === 'custom' ? '' : 'display: none;'; ?>">
                    <label for="ctx_start_date"><?php esc_html_e('From:', 'shuriken-reviews'); ?></label>
                    <input type="date" name="start_date" id="ctx_start_date" value="<?php echo esc_attr($start_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">
                    
                    <label for="ctx_end_date"><?php esc_html_e('To:', 'shuriken-reviews'); ?></label>
                    <input type="date" name="end_date" id="ctx_end_date" value="<?php echo esc_attr($end_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">
                    
                    <button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'shuriken-reviews'); ?></button>
                </div>
            </div>
            
            <?php if ($range_type === 'custom' && ($start_date || $end_date)) : ?>
            <div class="current-range-label">
                <?php Shuriken_Icons::render('calendar', array('width' => 18, 'height' => 18)); ?>
                <?php echo esc_html($date_range_label); ?>
                <a href="<?php echo esc_url($base_filter_url); ?>" class="clear-filter">
                    <?php esc_html_e('Clear', 'shuriken-reviews'); ?>
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Overview Cards -->
    <div class="shuriken-stats-grid">
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('star', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3>
                    <?php
                    if ($rating_type === 'like_dislike') {
                        $approval_pct = $display_total_votes > 0
                            ? round(($display_total_rating / $display_total_votes) * 100)
                            : 0;
                        echo esc_html($approval_pct) . '%';
                    } elseif ($rating_type === 'approval') {
                        echo esc_html($display_total_rating);
                    } else {
                        echo esc_html($analytics->format_average_display($average, $rating_type, $rating->scale ?: 5, $display_total_votes, $display_total_rating));
                    }
                    ?>
                </h3>
                <p>
                    <?php
                    if ($rating_type === 'like_dislike') {
                        esc_html_e('Approval Rate', 'shuriken-reviews');
                    } elseif ($rating_type === 'approval') {
                        esc_html_e('Total Approvals', 'shuriken-reviews');
                    } else {
                        esc_html_e('Average Rating', 'shuriken-reviews');
                    }
                    ?>
                </p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('bar-chart-2', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3>
                    <?php echo esc_html($display_total_votes); ?>
                    <?php if ($rating_type === 'like_dislike') : ?>
                        <small style="font-size: 14px; color: #64748b;">
                            (<?php Shuriken_Icons::render('thumbs-up', array('width' => 14, 'height' => 14)); ?> <?php echo esc_html($display_total_rating); ?> / <?php Shuriken_Icons::render('thumbs-down', array('width' => 14, 'height' => 14)); ?> <?php echo esc_html($display_total_votes - $display_total_rating); ?>)
                        </small>
                    <?php endif; ?>
                </h3>
                <p><?php esc_html_e('Total Votes', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('users', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3><?php echo esc_html($unique_voters ?: 0); ?></h3>
                <p><?php esc_html_e('Unique Voters', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('user', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3>
                    <?php 
                    $item_total = ($member_votes ?: 0) + ($guest_votes_count ?: 0);
                    $item_member_pct = $item_total > 0 ? round(($member_votes / $item_total) * 100) : 0;
                    echo esc_html($item_member_pct) . '%';
                    ?>
                </h3>
                <p><?php printf(esc_html__('Members (%s) / Guests (%s)', 'shuriken-reviews'), esc_html($member_votes ?: 0), esc_html($guest_votes_count ?: 0)); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Secondary Stats -->
    <div class="shuriken-stats-grid secondary">
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($first_vote ? $analytics->format_time_ago($first_vote) : __('N/A', 'shuriken-reviews')); ?></h4>
                <p><?php esc_html_e('First Vote', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($last_vote ? $analytics->format_time_ago($last_vote) : __('N/A', 'shuriken-reviews')); ?></h4>
                <p><?php esc_html_e('Last Vote', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Charts Row (type-aware) -->
    <div class="shuriken-charts-row">
        <?php if ($rating_type === 'like_dislike') : ?>
            <div class="shuriken-chart-card">
                <h2><?php esc_html_e('Likes vs Dislikes', 'shuriken-reviews'); ?></h2>
                <div class="chart-container">
                    <canvas id="ctxApprovalRingChart"></canvas>
                </div>
            </div>
            <div class="shuriken-chart-card wide">
                <h2><?php esc_html_e('Approval Trend', 'shuriken-reviews'); ?></h2>
                <div class="chart-container">
                    <canvas id="ctxApprovalTrendChart"></canvas>
                </div>
            </div>
        <?php elseif ($rating_type === 'approval') : ?>
            <div class="shuriken-chart-card" style="grid-column: 1 / -1;">
                <h2><?php esc_html_e('Cumulative Approvals', 'shuriken-reviews'); ?></h2>
                <div class="chart-container">
                    <canvas id="ctxCumulativeChart"></canvas>
                </div>
            </div>
        <?php else : ?>
            <div class="shuriken-chart-card">
                <h2><?php esc_html_e('Rating Distribution', 'shuriken-reviews'); ?></h2>
                <div class="chart-container">
                    <canvas id="ctxRatingDistChart"></canvas>
                </div>
            </div>
            <div class="shuriken-chart-card wide">
                <h2><?php esc_html_e('Voting Activity', 'shuriken-reviews'); ?></h2>
                <div class="chart-container">
                    <canvas id="ctxDualAxisChart"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Votes Table -->
    <div class="shuriken-table-card full-width">
        <h2>
            <?php Shuriken_Icons::render('list', array('width' => 18, 'height' => 18)); ?>
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
                    <th class="column-rating"><?php echo shuriken_sort_link('rating', $votes_sort_by, $votes_sort_order, $base_filter_url, __('Rating', 'shuriken-reviews'), 'votes_sort_by', 'votes_sort_order'); ?></th>
                    <th class="column-voter"><?php esc_html_e('Voter', 'shuriken-reviews'); ?></th>
                    <th class="column-ip"><?php esc_html_e('IP Address', 'shuriken-reviews'); ?></th>
                    <th class="column-date"><?php echo shuriken_sort_link('date', $votes_sort_by, $votes_sort_order, $base_filter_url, __('Date & Time', 'shuriken-reviews'), 'votes_sort_by', 'votes_sort_order'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($votes) : ?>
                    <?php foreach ($votes as $vote) : ?>
                        <tr>
                            <td class="column-id"><?php echo esc_html($vote->id); ?></td>
                            <td class="column-rating">
                                <span class="star-rating-display">
                                    <?php echo $analytics->format_vote_display($vote->rating_value, $vote->rating_type ?? $rating->rating_type ?? 'stars', $vote->scale ?? $rating->scale ?? 5); ?>
                                </span>
                                <?php
                                $vote_type = $vote->rating_type ?? $rating->rating_type ?? 'stars';
                                $vote_type_enum = Shuriken_Rating_Type::tryFrom($vote_type) ?? Shuriken_Rating_Type::Stars;
                                if (!$vote_type_enum->isBinary()) :
                                    $vote_scale = $vote->scale ?? $rating->scale ?? 5;
                                    $denorm_vote = round(((float) $vote->rating_value / Shuriken_Database::RATING_SCALE_DEFAULT) * $vote_scale, 1);
                                ?>
                                <span class="rating-number">(<?php echo esc_html($denorm_vote); ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-voter">
                                <?php 
                                $voter_activity_url = $vote->user_id > 0 
                                    ? admin_url('admin.php?page=shuriken-reviews-voter-activity&user_id=' . $vote->user_id)
                                    : ($vote->user_ip ? admin_url('admin.php?page=shuriken-reviews-voter-activity&user_ip=' . urlencode($vote->user_ip)) : '');
                                ?>
                                <?php if ($vote->user_id > 0) : ?>
                                    <span class="voter-type member" title="<?php esc_attr_e('Registered Member', 'shuriken-reviews'); ?>">
                                        <?php Shuriken_Icons::render('user', array('width' => 14, 'height' => 14)); ?>
                                    </span>
                                    <?php if ($vote->display_name) : ?>
                                        <a href="<?php echo esc_url($voter_activity_url); ?>" class="voter-link">
                                            <strong><?php echo esc_html($vote->display_name); ?></strong>
                                        </a>
                                        <br><small><?php echo esc_html($vote->user_email); ?></small>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url($voter_activity_url); ?>" class="voter-link">
                                            <em><?php esc_html_e('Deleted User', 'shuriken-reviews'); ?></em>
                                        </a>
                                        <br><small><?php printf(esc_html__('User ID: %d', 'shuriken-reviews'), $vote->user_id); ?></small>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <span class="voter-type guest" title="<?php esc_attr_e('Guest', 'shuriken-reviews'); ?>">
                                        <?php Shuriken_Icons::render('briefcase', array('width' => 14, 'height' => 14)); ?>
                                    </span>
                                    <?php if ($voter_activity_url) : ?>
                                        <a href="<?php echo esc_url($voter_activity_url); ?>" class="voter-link">
                                            <em><?php esc_html_e('Guest', 'shuriken-reviews'); ?></em>
                                        </a>
                                    <?php else : ?>
                                        <em><?php esc_html_e('Guest', 'shuriken-reviews'); ?></em>
                                    <?php endif; ?>
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
                        <td colspan="5"><?php esc_html_e('No votes recorded for this context', 'shuriken-reviews'); ?></td>
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
                        echo paginate_links(array(
                            'base'      => add_query_arg('paged', '%#%'),
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
    </div>
</div>

<script>
var shurikenContextStatsData = {
    ratingType: <?php echo wp_json_encode($rating_type); ?>,
    <?php if ($rating_type === 'like_dislike') : ?>
    likes: <?php echo intval($display_total_rating); ?>,
    dislikes: <?php echo intval($display_total_votes - $display_total_rating); ?>,
    approvalTrend: <?php echo wp_json_encode($approval_trend); ?>,
    <?php elseif ($rating_type === 'approval') : ?>
    cumulativeApprovals: <?php echo wp_json_encode($cumulative_approvals); ?>,
    <?php else : ?>
    ratingDistribution: <?php echo wp_json_encode(array_values($distribution_array)); ?>,
    distributionLabels: <?php echo wp_json_encode(array_map(function($k) use ($rating, $rating_type) {
        $scale = (int) ($rating->scale ?: 5);
        $denorm = Shuriken_Database::denormalize_average((float) $k, $scale);
        $label = ($scale === 5) ? $k : round($denorm);
        $suffix = ($rating_type === 'numeric') ? '' : ' ★';
        return $label . $suffix;
    }, array_keys($distribution_array))); ?>,
    dualAxisData: <?php echo wp_json_encode($dual_axis_data); ?>,
    <?php endif; ?>
    i18n: {
        votes: <?php echo wp_json_encode(__('Votes', 'shuriken-reviews')); ?>,
        average: <?php echo wp_json_encode(__('Average', 'shuriken-reviews')); ?>,
        likes: <?php echo wp_json_encode(__('Likes', 'shuriken-reviews')); ?>,
        dislikes: <?php echo wp_json_encode(__('Dislikes', 'shuriken-reviews')); ?>,
        approvalRate: <?php echo wp_json_encode(__('Approval %', 'shuriken-reviews')); ?>,
        approvals: <?php echo wp_json_encode(__('Approvals', 'shuriken-reviews')); ?>,
        cumulative: <?php echo wp_json_encode(__('Cumulative', 'shuriken-reviews')); ?>
    }
};

jQuery(document).ready(function($) {
    if (typeof Chart === 'undefined') return;
    
    var d = shurikenContextStatsData;
    var u = window.shurikenAnalyticsUtils || {};
    var gridColor = u.colors ? u.colors.grid : '#f0f0f1';
    var tickColor = u.colors ? u.colors.tick : '#646970';
    var formatDate = u.formatDate || function(dateStr) {
        var date = new Date(dateStr);
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    };
    
    if (d.ratingType === 'like_dislike') {
        // Approval Ring
        var ringCtx = document.getElementById('ctxApprovalRingChart');
        if (ringCtx && (d.likes + d.dislikes) > 0) {
            new Chart(ringCtx, {
                type: 'doughnut',
                data: {
                    labels: [d.i18n.likes, d.i18n.dislikes],
                    datasets: [{ data: [d.likes, d.dislikes], backgroundColor: ['#00a32a', '#dc3232'], borderColor: '#fff', borderWidth: 3, hoverOffset: 8 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '60%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } },
                        tooltip: { backgroundColor: '#1d2327', titleColor: '#fff', bodyColor: '#fff', padding: 12 }
                    }
                }
            });
        }
        // Approval Trend
        var trendCtx = document.getElementById('ctxApprovalTrendChart');
        if (trendCtx && d.approvalTrend && d.approvalTrend.length) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: d.approvalTrend.map(function(r) { return formatDate(r.vote_date); }),
                    datasets: [{ label: d.i18n.approvalRate, data: d.approvalTrend.map(function(r) { return parseFloat(r.approval_rate); }), borderColor: '#00a32a', backgroundColor: 'rgba(0, 163, 42, 0.1)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 3 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12, color: tickColor } }, y: { beginAtZero: true, max: 100, grid: { color: gridColor }, ticks: { callback: function(v) { return v + '%'; }, color: tickColor } } } }
            });
        }
    } else if (d.ratingType === 'approval') {
        // Cumulative
        var cumCtx = document.getElementById('ctxCumulativeChart');
        if (cumCtx && d.cumulativeApprovals && d.cumulativeApprovals.length) {
            new Chart(cumCtx, {
                type: 'line',
                data: {
                    labels: d.cumulativeApprovals.map(function(r) { return formatDate(r.vote_date); }),
                    datasets: [{ label: d.i18n.cumulative, data: d.cumulativeApprovals.map(function(r) { return parseInt(r.cumulative_count, 10); }), borderColor: '#8c5383', backgroundColor: 'rgba(140, 83, 131, 0.1)', borderWidth: 2, fill: true, tension: 0.3, pointRadius: 2 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12, color: tickColor } }, y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } } } }
            });
        }
    } else {
        // Distribution
        var distCtx = document.getElementById('ctxRatingDistChart');
        if (distCtx) {
            var allColors = ['#dc3232', '#f56e28', '#ffb900', '#7ad03a', '#00a32a'];
            var distData = d.ratingDistribution || [];
            var colors = distData.length <= allColors.length ? allColors.slice(allColors.length - distData.length) : allColors;
            var distLabels = d.distributionLabels || distData.map(function(_, i) { return (i + 1) + ' \u2605'; });
            new Chart(distCtx, {
                type: 'bar',
                data: { labels: distLabels, datasets: [{ label: d.i18n.votes, data: distData, backgroundColor: colors, borderColor: colors, borderWidth: 1, borderRadius: 4 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: tickColor } }, y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } } } }
            });
        }
        // Dual-axis
        var dualCtx = document.getElementById('ctxDualAxisChart');
        if (dualCtx && d.dualAxisData && d.dualAxisData.length) {
            new Chart(dualCtx, {
                type: 'bar',
                data: {
                    labels: d.dualAxisData.map(function(r) { return formatDate(r.vote_date); }),
                    datasets: [
                        { type: 'bar', label: d.i18n.votes, data: d.dualAxisData.map(function(r) { return parseInt(r.vote_count, 10); }), backgroundColor: 'rgba(34, 113, 177, 0.3)', borderColor: '#2271b1', borderWidth: 1, borderRadius: 3, yAxisID: 'y' },
                        { type: 'line', label: d.i18n.average, data: d.dualAxisData.map(function(r) { return parseFloat(r.display_daily_avg); }), borderColor: '#f59e0b', backgroundColor: 'transparent', borderWidth: 2, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#f59e0b', yAxisID: 'y1' }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' },
                    plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 12 } }, tooltip: { backgroundColor: '#1d2327', titleColor: '#fff', bodyColor: '#fff', padding: 12 } },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12, color: tickColor } },
                        y: { beginAtZero: true, position: 'left', grid: { color: gridColor }, ticks: { precision: 0, color: tickColor }, title: { display: true, text: d.i18n.votes, color: tickColor } },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { color: '#f59e0b' }, title: { display: true, text: d.i18n.average, color: '#f59e0b' } }
                    }
                }
            });
        }
    }
});
</script>
