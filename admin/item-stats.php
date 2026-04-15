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

// Detect scope: does this rating have both global and contextual votes?
$has_contextual = $analytics->has_contextual_votes($rating_id);
$current_scope = null;
if ($has_contextual) {
    $current_scope = isset($_GET['scope']) && in_array($_GET['scope'], array('global', 'contextual'), true)
        ? $_GET['scope']
        : 'contextual'; // Default to contextual/per-post view
}

// When viewing contextual scope, fetch contextual-specific data
if ($current_scope === 'contextual') {
    $context_summary = $analytics->get_rating_context_summary($rating_id, $date_range);
    $top_contexts = $analytics->get_top_contexts_by_votes($rating_id, 10, $date_range);
    $context_avg_dist = $analytics->get_context_avg_distribution($rating_id, $date_range);

    // Sort params for trending table
    $trend_sort_by = isset($_GET['trend_sort_by']) && in_array($_GET['trend_sort_by'], array('recent_votes', 'velocity', 'ctx_avg'), true) ? $_GET['trend_sort_by'] : 'velocity';
    $trend_sort_order = isset($_GET['trend_sort_order']) && in_array($_GET['trend_sort_order'], array('asc', 'desc'), true) ? $_GET['trend_sort_order'] : 'desc';
    $trending_contexts = is_numeric($date_range) ? $analytics->get_trending_contexts($rating_id, $date_range, 5, $trend_sort_by, $trend_sort_order) : array();

    // Contextual votes over time
    $contextual_votes_timeline = $analytics->get_votes_over_time($date_range, $rating_id, 'contextual');

    // Paginated contexts table
    $ctx_per_page = 20;
    $ctx_page = isset($_GET['ctx_paged']) ? max(1, intval($_GET['ctx_paged'])) : 1;
    $ctx_sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], array('votes', 'average', 'last_vote')) ? $_GET['sort_by'] : 'votes';
    $ctx_sort_order = isset($_GET['sort_order']) && in_array($_GET['sort_order'], array('asc', 'desc')) ? $_GET['sort_order'] : 'desc';
    $contexts_result = $analytics->get_rating_contexts_paginated($rating_id, $ctx_page, $ctx_per_page, $date_range, $ctx_sort_by, $ctx_sort_order);
}

// When viewing global scope (or no scope toggle), fetch standard data
if ($current_scope !== 'contextual') {
    // Get detailed stats for this item with date range and scope filter
    $stats = $current_scope === 'global'
        ? $analytics->get_rating_stats($rating_id, $date_range, 'global')
        : $analytics->get_rating_stats($rating_id, $date_range);

    // Get hierarchy-related data
    $is_parent = $analytics->has_sub_ratings($rating_id);
    $is_sub = !empty($rating->parent_id);
    $is_mirror = !empty($rating->mirror_of);
    $sub_ratings = $is_parent ? $analytics->get_sub_ratings_contribution($rating_id) : array();
    $parent_rating = $is_sub ? $analytics->get_rating($rating->parent_id) : null;
    $source_rating = $is_mirror ? $analytics->get_rating($rating->mirror_of) : null;

    // For parent ratings, get breakdown data (direct, subs, total) with date range and scope support
    $stats_breakdown = $is_parent ? $analytics->get_parent_rating_stats_breakdown($rating_id, $date_range, $current_scope) : null;

$current_view = isset($_GET['view']) && in_array($_GET['view'], array('direct', 'subs', 'total')) ? $_GET['view'] : 'total';

// Extract values for template (use breakdown if parent rating, otherwise use date-filtered stats)
if ($is_parent && $stats_breakdown) {
    // For parent ratings with view selector, use breakdown data with date range applied
    $current_stats = $stats_breakdown->$current_view;
    $average = $current_stats->average;
    $display_average = $current_stats->display_average;
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
    $display_average = $stats->display_average;
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

// Sort params for vote history table
$votes_sort_by = isset($_GET['votes_sort_by']) && in_array($_GET['votes_sort_by'], array('date', 'rating'), true) ? $_GET['votes_sort_by'] : 'date';
$votes_sort_order = isset($_GET['votes_sort_order']) && in_array($_GET['votes_sort_order'], array('asc', 'desc'), true) ? $_GET['votes_sort_order'] : 'desc';

// Get paginated votes (with view filter for parent ratings and scope)
$vote_view = $is_parent ? $current_view : 'direct';
if ($current_scope === 'global') {
    $votes_result = $analytics->get_rating_votes_paginated($rating_id, $current_page, $per_page, $date_range, $vote_view, $votes_sort_by, $votes_sort_order, 'global');
} else {
    $votes_result = $analytics->get_rating_votes_paginated($rating_id, $current_page, $per_page, $date_range, $vote_view, $votes_sort_by, $votes_sort_order);
}
$votes = $votes_result->votes;
$total_votes_count = $votes_result->total_count;
$total_pages = $votes_result->total_pages;
$offset = ($current_page - 1) * $per_page;
} // end: if ($current_scope !== 'contextual')

// Back URL
$back_url = admin_url('admin.php?page=shuriken-reviews-analytics');
$edit_url = admin_url('admin.php?page=shuriken-reviews&s=' . urlencode($rating->name) . '#rating-' . $rating->id);

// Type-specific chart data
$rating_type = $rating->rating_type ?: 'stars';
$rating_type_enum = Shuriken_Rating_Type::tryFrom($rating_type) ?? Shuriken_Rating_Type::Stars;
$is_binary = $rating_type_enum->isBinary();

// Base URL for filters (preserves rating_id and scope)
$base_filter_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $rating_id);
if ($current_scope) {
    $base_filter_url = add_query_arg('scope', $current_scope, $base_filter_url);
}

if ($current_scope !== 'contextual') {
    // Comparison and velocity data (global view only)
    $type_benchmark = $analytics->get_type_benchmark($rating_type, $date_range);
    $vote_velocity = $analytics->get_rating_vote_change_percent($rating_id, $date_range);

if ($rating_type === 'like_dislike') {
    if ($current_scope === 'global') {
        $approval_trend = $analytics->get_approval_trend($rating_id, $date_range, 'global');
    } else {
        $approval_trend = $analytics->get_approval_trend($rating_id, $date_range);
    }
} elseif ($rating_type === 'approval') {
    if ($current_scope === 'global') {
        $cumulative_approvals = $analytics->get_cumulative_approvals($rating_id, $date_range, 'global');
    } else {
        $cumulative_approvals = $analytics->get_cumulative_approvals($rating_id, $date_range);
    }
} else {
    // stars/numeric: dual-axis chart data
    // For parent ratings, include votes from sub-ratings based on the current view
    $item_scale = (int) ($rating->scale ?: 5);
    if ($is_parent && $stats_breakdown) {
        $sub_rating_ids = array_map(fn($s) => $s->id, $sub_ratings);
        if ($current_view === 'direct') {
            $chart_ids = array($rating_id);
        } elseif ($current_view === 'subs') {
            $chart_ids = !empty($sub_rating_ids) ? $sub_rating_ids : array($rating_id);
        } else {
            $chart_ids = array_merge(array($rating_id), $sub_rating_ids);
        }
        $dual_axis_data = $analytics->get_votes_with_rolling_avg_for_ids($chart_ids, $date_range, $item_scale);
    } else {
        if ($current_scope === 'global') {
            $dual_axis_data = $analytics->get_votes_with_rolling_avg($rating_id, $date_range, $item_scale, 'global');
        } else {
            $dual_axis_data = $analytics->get_votes_with_rolling_avg($rating_id, $date_range, $item_scale);
        }
    }
}
} // end: if ($current_scope !== 'contextual') — chart data

?>

<div class="wrap shuriken-analytics shuriken-item-stats">
    <h1>
        <a href="<?php echo esc_url($back_url); ?>" class="page-title-action shuriken-back-btn">
            <?php Shuriken_Icons::render('arrow-left', array('width' => 18, 'height' => 18)); ?>
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
    
    <!-- Unified Filter Bar -->
    <div class="shuriken-filter-bar">
        <form method="get" action="" id="shuriken-item-date-filter-form">
            <input type="hidden" name="page" value="shuriken-reviews-item-stats">
            <input type="hidden" name="rating_id" value="<?php echo esc_attr($rating_id); ?>">
            <input type="hidden" name="range_type" id="item_range_type" value="<?php echo esc_attr($range_type); ?>">
            <?php if ($current_scope) : ?>
            <input type="hidden" name="scope" value="<?php echo esc_attr($current_scope); ?>">
            <?php endif; ?>
            <?php if ($current_scope !== 'contextual' && isset($is_parent) && $is_parent) : ?>
            <input type="hidden" name="view" value="<?php echo esc_attr($current_view); ?>">
            <?php endif; ?>
            
            <div class="filter-group">
                <span class="filter-group-label"><?php esc_html_e('Time Period', 'shuriken-reviews'); ?></span>
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
                
                <?php if ($range_type === 'custom' && ($start_date || $end_date)) : ?>
                <span class="current-range-label">
                    <?php Shuriken_Icons::render('calendar', array('width' => 14, 'height' => 14)); ?>
                    <?php echo esc_html($date_range_label); ?>
                    <a href="<?php echo esc_url($base_filter_url); ?>" class="clear-filter" title="<?php esc_attr_e('Clear', 'shuriken-reviews'); ?>">×</a>
                </span>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if ($has_contextual) : ?>
        <div class="filter-group">
            <span class="filter-group-label"><?php esc_html_e('Vote Scope', 'shuriken-reviews'); ?></span>
            <div class="filter-segmented">
                <?php
                $scope_base_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $rating_id);
                if ($range_type === 'custom') {
                    $scope_base_url = add_query_arg(array('range_type' => 'custom', 'start_date' => $start_date, 'end_date' => $end_date), $scope_base_url);
                } elseif ($preset_value !== '30') {
                    $scope_base_url = add_query_arg('date_range', $preset_value, $scope_base_url);
                }
                ?>
                <a href="<?php echo esc_url(add_query_arg('scope', 'contextual', $scope_base_url)); ?>"
                   class="filter-seg-btn <?php echo $current_scope === 'contextual' ? 'active' : ''; ?>">
                    <?php Shuriken_Icons::render('file-text', array('width' => 14, 'height' => 14)); ?>
                    <?php esc_html_e('Per-Post', 'shuriken-reviews'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('scope', 'global', $scope_base_url)); ?>"
                   class="filter-seg-btn <?php echo $current_scope === 'global' ? 'active' : ''; ?>">
                    <?php Shuriken_Icons::render('globe', array('width' => 14, 'height' => 14)); ?>
                    <?php esc_html_e('Global', 'shuriken-reviews'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($current_scope !== 'contextual' && isset($is_parent) && $is_parent && $stats_breakdown) : ?>
        <div class="filter-group">
            <span class="filter-group-label"><?php esc_html_e('Display data from', 'shuriken-reviews'); ?></span>
            <div class="filter-segmented">
                <?php 
                $views = array(
                    'total'  => __('Total', 'shuriken-reviews'),
                    'direct' => __('Direct Only', 'shuriken-reviews'),
                    'subs'   => __('Sub-ratings Only', 'shuriken-reviews'),
                );
                foreach ($views as $view_key => $view_label) :
                    $view_url = add_query_arg('view', $view_key, $base_filter_url);
                ?>
                    <a href="<?php echo esc_url($view_url); ?>"
                       class="filter-seg-btn <?php echo $current_view === $view_key ? 'active' : ''; ?>">
                        <?php echo esc_html($view_label); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($current_scope === 'contextual') : ?>
    <!-- ============================================ -->
    <!-- CONTEXTUAL / PER-POST VIEW                   -->
    <!-- ============================================ -->
    
    <!-- Overview Cards -->
    <div class="shuriken-stats-grid">
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('file-text', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3><?php echo esc_html($context_summary->total_contexts); ?></h3>
                <p><?php esc_html_e('Posts/Pages Used In', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('bar-chart-2', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3><?php echo esc_html($context_summary->total_votes); ?></h3>
                <p><?php esc_html_e('Total Contextual Votes', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('star', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3>
                    <?php
                    if ($rating_type === 'like_dislike') {
                        $ctx_approval = $context_summary->total_votes > 0 ? round(($context_summary->total_rating / $context_summary->total_votes) * 100) : 0;
                        echo esc_html($ctx_approval) . '%';
                    } elseif ($rating_type === 'approval') {
                        echo esc_html($context_summary->total_rating);
                    } else {
                        echo esc_html($analytics->format_average_display($context_summary->average, $rating_type, $rating->scale ?: 5, $context_summary->total_votes, $context_summary->total_rating));
                    }
                    ?>
                </h3>
                <p>
                    <?php
                    if ($rating_type === 'like_dislike') {
                        esc_html_e('Avg Approval Rate', 'shuriken-reviews');
                    } elseif ($rating_type === 'approval') {
                        esc_html_e('Total Approvals', 'shuriken-reviews');
                    } else {
                        esc_html_e('Avg Rating Across Posts', 'shuriken-reviews');
                    }
                    ?>
                </p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('users', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3><?php echo esc_html($context_summary->unique_voters); ?></h3>
                <p><?php esc_html_e('Unique Voters', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>
    
    <?php if ($context_summary->best_context_id) : ?>
    <div class="shuriken-stats-grid secondary">
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=shuriken-reviews-context-stats&rating_id=' . $rating_id . '&context_id=' . $context_summary->best_context_id . '&context_type=' . urlencode($context_summary->best_context_type))); ?>">
                        <?php echo esc_html($context_summary->best_context_title); ?>
                    </a>
                </h4>
                <p>
                    <?php printf(
                        esc_html__('Best Performing (%s avg, %d votes)', 'shuriken-reviews'),
                        esc_html($context_summary->best_context_avg),
                        $context_summary->best_context_votes
                    ); ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Charts Row -->
    <div class="shuriken-charts-row">
        <!-- Top Contexts by Votes -->
        <div class="shuriken-chart-card">
            <h2><?php esc_html_e('Top Posts by Votes', 'shuriken-reviews'); ?></h2>
            <div class="chart-container">
                <canvas id="ctxTopPostsChart"></canvas>
            </div>
        </div>
        
        <!-- Context Average Distribution -->
        <div class="shuriken-chart-card">
            <h2>
                <?php
                if ($is_binary) {
                    esc_html_e('Approval Rate Distribution', 'shuriken-reviews');
                } else {
                    esc_html_e('Rating Distribution Across Posts', 'shuriken-reviews');
                }
                ?>
            </h2>
            <div class="chart-container">
                <canvas id="ctxAvgDistChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Contextual Voting Activity -->
    <div class="shuriken-charts-row">
        <div class="shuriken-chart-card" style="grid-column: 1 / -1;">
            <h2><?php esc_html_e('Contextual Voting Activity', 'shuriken-reviews'); ?></h2>
            <div class="chart-container">
                <canvas id="ctxVotingActivityChart"></canvas>
            </div>
        </div>
    </div>
    
    <?php if (!empty($trending_contexts)) : ?>
    <!-- Trending Contexts -->
    <div class="shuriken-table-card full-width">
        <h2>
            <?php Shuriken_Icons::render('trending-up', array('width' => 18, 'height' => 18)); ?>
            <?php esc_html_e('Trending Posts', 'shuriken-reviews'); ?>
        </h2>
        <p class="table-description"><?php esc_html_e('Posts with rising vote activity in the current period', 'shuriken-reviews'); ?></p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Post', 'shuriken-reviews'); ?></th>
                    <th><?php esc_html_e('Type', 'shuriken-reviews'); ?></th>
                    <?php
                    $trend_sort_url = add_query_arg(array('scope' => 'contextual'), $base_filter_url);
                    ?>
                    <th><?php echo shuriken_sort_link('recent_votes', $trend_sort_by, $trend_sort_order, $trend_sort_url, __('Recent Votes', 'shuriken-reviews'), 'trend_sort_by', 'trend_sort_order'); ?></th>
                    <th><?php esc_html_e('Previous', 'shuriken-reviews'); ?></th>
                    <th><?php echo shuriken_sort_link('velocity', $trend_sort_by, $trend_sort_order, $trend_sort_url, __('Change', 'shuriken-reviews'), 'trend_sort_by', 'trend_sort_order'); ?></th>
                    <th><?php echo shuriken_sort_link('ctx_avg', $trend_sort_by, $trend_sort_order, $trend_sort_url, ($rating_type === 'like_dislike' ? __('Approval', 'shuriken-reviews') : ($rating_type === 'approval' ? __('Approvals', 'shuriken-reviews') : __('Average', 'shuriken-reviews'))), 'trend_sort_by', 'trend_sort_order'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trending_contexts as $trend) :
                    $trend_ctx_url = admin_url('admin.php?page=shuriken-reviews-context-stats&rating_id=' . $rating_id . '&context_id=' . $trend->context_id . '&context_type=' . urlencode($trend->context_type));
                ?>
                <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($trend_ctx_url); ?>">
                    <td>
                        <a href="<?php echo esc_url($trend_ctx_url); ?>" class="rating-item-link">
                            <?php echo esc_html($trend->title); ?>
                        </a>
                    </td>
                    <td><span class="context-type-badge"><?php echo esc_html(ucfirst($trend->context_type)); ?></span></td>
                    <td><strong><?php echo esc_html($trend->recent_votes); ?></strong></td>
                    <td><?php echo esc_html($trend->prev_votes); ?></td>
                    <td>
                        <span class="velocity-badge <?php echo $trend->velocity >= 0 ? 'positive' : 'negative'; ?>">
                            <?php Shuriken_Icons::render($trend->velocity >= 0 ? 'arrow-up' : 'arrow-down', array('width' => 12, 'height' => 12)); ?>
                            <?php echo esc_html(($trend->velocity >= 0 ? '+' : '') . $trend->velocity . '%'); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($analytics->format_average_display((float) $trend->ctx_avg, $rating_type, $rating->scale ?: 5, $trend->recent_votes, 0)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- All Contexts Table -->
    <div class="shuriken-table-card full-width">
        <h2>
            <?php Shuriken_Icons::render('list', array('width' => 18, 'height' => 18)); ?>
            <?php esc_html_e('All Posts & Pages', 'shuriken-reviews'); ?>
        </h2>
        <p class="table-description">
            <?php printf(
                esc_html__('Showing %1$d-%2$d of %3$d contexts', 'shuriken-reviews'),
                min(($ctx_page - 1) * $ctx_per_page + 1, $contexts_result->total_count),
                min($ctx_page * $ctx_per_page, $contexts_result->total_count),
                $contexts_result->total_count
            ); ?>
        </p>
        
        <?php
        // Sort header helper
        $sort_url_base = add_query_arg(array('scope' => 'contextual'), $base_filter_url);
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Post / Page', 'shuriken-reviews'); ?></th>
                    <th><?php esc_html_e('Type', 'shuriken-reviews'); ?></th>
                    <th><?php echo shuriken_sort_link('votes', $ctx_sort_by, $ctx_sort_order, $sort_url_base, __('Votes', 'shuriken-reviews')); ?></th>
                    <th><?php echo shuriken_sort_link('average', $ctx_sort_by, $ctx_sort_order, $sort_url_base, __('Average', 'shuriken-reviews')); ?></th>
                    <th><?php esc_html_e('Unique Voters', 'shuriken-reviews'); ?></th>
                    <th><?php echo shuriken_sort_link('last_vote', $ctx_sort_by, $ctx_sort_order, $sort_url_base, __('Last Vote', 'shuriken-reviews')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($contexts_result->contexts)) : ?>
                    <?php foreach ($contexts_result->contexts as $ctx) :
                        $ctx_stats_url = admin_url('admin.php?page=shuriken-reviews-context-stats&rating_id=' . $rating_id . '&context_id=' . $ctx->context_id . '&context_type=' . urlencode($ctx->context_type));
                    ?>
                    <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($ctx_stats_url); ?>">
                        <td>
                            <a href="<?php echo esc_url($ctx_stats_url); ?>" class="rating-item-link">
                                <?php echo esc_html($ctx->title); ?>
                            </a>
                            <div class="row-actions">
                                <span><a href="<?php echo esc_url($ctx_stats_url); ?>"><?php esc_html_e('Stats', 'shuriken-reviews'); ?></a></span>
                                <?php if ($ctx->view_url) : ?>
                                | <span><a href="<?php echo esc_url($ctx->view_url); ?>" target="_blank"><?php esc_html_e('View', 'shuriken-reviews'); ?></a></span>
                                <?php endif; ?>
                                <?php if ($ctx->edit_url) : ?>
                                | <span><a href="<?php echo esc_url($ctx->edit_url); ?>"><?php esc_html_e('Edit', 'shuriken-reviews'); ?></a></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><span class="context-type-badge"><?php echo esc_html(ucfirst($ctx->context_type)); ?></span></td>
                        <td><strong><?php echo esc_html($ctx->ctx_votes); ?></strong></td>
                        <td>
                            <?php if (!$is_binary) : ?>
                                <span class="star-display"><?php Shuriken_Icons::render('star', array('width' => 14, 'height' => 14)); ?></span>
                            <?php endif; ?>
                            <?php echo esc_html($analytics->format_average_display((float) $ctx->ctx_avg, $rating_type, $rating->scale ?: 5, $ctx->ctx_votes, $ctx->ctx_total)); ?>
                        </td>
                        <td><?php echo esc_html($ctx->unique_voters); ?></td>
                        <td>
                            <?php echo esc_html($analytics->format_date($ctx->last_vote_date)); ?>
                            <br><small class="timeago"><?php echo esc_html($analytics->format_time_ago($ctx->last_vote_date)); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No contextual votes found', 'shuriken-reviews'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($contexts_result->total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(esc_html(_n('%s context', '%s contexts', $contexts_result->total_count, 'shuriken-reviews')), number_format_i18n($contexts_result->total_count)); ?>
                </span>
                <span class="pagination-links">
                    <?php
                    echo paginate_links(array(
                        'base'      => add_query_arg('ctx_paged', '%#%'),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $contexts_result->total_pages,
                        'current'   => $ctx_page,
                    ));
                    ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php else : // Global view (existing content) ?>
    
    <?php if (isset($is_mirror) && ($is_mirror || $is_parent || $is_sub || !empty($rating->display_only))) : ?>
    <!-- Hierarchy Info -->
    <div class="shuriken-hierarchy-info">
        <?php if ($is_mirror && $source_rating) : ?>
            <span class="hierarchy-badge mirror">
                <?php Shuriken_Icons::render('link', array('width' => 18, 'height' => 18)); ?>
                <?php printf(
                    esc_html__('Mirror of: %s', 'shuriken-reviews'),
                    '<a href="' . esc_url(admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $source_rating->id)) . '">' . esc_html($source_rating->name) . '</a>'
                ); ?>
            </span>
        <?php endif; ?>
        
        <?php if ($is_parent) : ?>
            <span class="hierarchy-badge parent">
                <?php Shuriken_Icons::render('share-2', array('width' => 18, 'height' => 18)); ?>
                <?php printf(
                    esc_html__('Parent Rating with %d sub-ratings', 'shuriken-reviews'),
                    count($sub_ratings)
                ); ?>
            </span>
        <?php endif; ?>
        
        <?php if ($is_sub && $parent_rating) : ?>
            <span class="hierarchy-badge sub <?php echo esc_attr($rating->effect_type); ?>">
                <?php Shuriken_Icons::render($rating->effect_type === 'positive' ? 'arrow-up' : 'arrow-down', array('width' => 18, 'height' => 18)); ?>
                <?php printf(
                    esc_html__('Sub-rating of: %s (%s effect)', 'shuriken-reviews'),
                    '<a href="' . esc_url(admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $parent_rating->id)) . '">' . esc_html($parent_rating->name) . '</a>',
                    $rating->effect_type
                ); ?>
            </span>
        <?php endif; ?>
        
        <?php if (!empty($rating->display_only)) : ?>
            <span class="hierarchy-badge display-only">
                <?php Shuriken_Icons::render('eye-off', array('width' => 18, 'height' => 18)); ?>
                <?php esc_html_e('Display Only (calculated from sub-ratings)', 'shuriken-reviews'); ?>
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
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
                        echo esc_html($analytics->format_average_display($average, $rating->rating_type ?: 'stars', $rating->scale ?: 5, $display_total_votes, $display_total_rating));
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
                <?php
                // Comparison to type benchmark
                if ($type_benchmark->item_count > 1 && $display_total_votes > 0) :
                    if ($rating_type === 'like_dislike' && $type_benchmark->avg_approval_rate !== null) {
                        $diff = $approval_pct - $type_benchmark->avg_approval_rate;
                        $diff_display = ($diff >= 0 ? '+' : '') . round($diff) . '%';
                    } elseif ($rating_type === 'approval' && $type_benchmark->avg_votes !== null) {
                        $diff = $display_total_rating - round($type_benchmark->avg_votes);
                        $diff_display = ($diff >= 0 ? '+' : '') . intval($diff);
                    } elseif (!$is_binary && $type_benchmark->avg_rating !== null) {
                        $bench_display = Shuriken_Database::denormalize_average((float) $type_benchmark->avg_rating, (int) ($rating->scale ?: 5));
                        $diff = $display_average - $bench_display;
                        $diff_display = ($diff >= 0 ? '+' : '') . round($diff, 1);
                    } else {
                        $diff = null;
                        $diff_display = '';
                    }
                    if ($diff !== null) :
                ?>
                    <small class="benchmark-badge <?php echo $diff >= 0 ? 'above' : 'below'; ?>" title="<?php esc_attr_e('vs. type average', 'shuriken-reviews'); ?>">
                        <?php echo esc_html($diff_display); ?>
                        <?php esc_html_e('vs avg', 'shuriken-reviews'); ?>
                    </small>
                <?php endif; endif; ?>
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
                <?php if ($vote_velocity !== null) : ?>
                    <small class="velocity-badge <?php echo $vote_velocity >= 0 ? 'positive' : 'negative'; ?>">
                        <?php Shuriken_Icons::render($vote_velocity >= 0 ? 'arrow-up' : 'arrow-down', array('width' => 12, 'height' => 12)); ?>
                        <?php echo esc_html(($vote_velocity >= 0 ? '+' : '') . $vote_velocity . '%'); ?>
                        <?php esc_html_e('vs prev period', 'shuriken-reviews'); ?>
                    </small>
                <?php endif; ?>
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
            <!-- Like/Dislike: Approval Ring -->
            <div class="shuriken-chart-card">
                <h2><?php esc_html_e('Likes vs Dislikes', 'shuriken-reviews'); ?></h2>
                <div class="chart-container">
                    <canvas id="itemApprovalRingChart"></canvas>
                </div>
            </div>
            <!-- Like/Dislike: Approval Trend -->
            <div class="shuriken-chart-card wide">
                <h2><?php esc_html_e('Approval Trend', 'shuriken-reviews'); ?></h2>
                <div class="chart-container">
                    <canvas id="itemApprovalTrendChart"></canvas>
                </div>
            </div>
        <?php elseif ($rating_type === 'approval') : ?>
            <!-- Approval: Cumulative only (full width) -->
            <div class="shuriken-chart-card" style="grid-column: 1 / -1;">
                <h2><?php esc_html_e('Cumulative Approvals', 'shuriken-reviews'); ?></h2>
                <div class="chart-container">
                    <canvas id="itemCumulativeChart"></canvas>
                </div>
            </div>
        <?php else : ?>
            <!-- Stars/Numeric: Distribution + Dual-axis -->
            <div class="shuriken-chart-card">
                <h2><?php esc_html_e('Rating Distribution', 'shuriken-reviews'); ?></h2>
                <div class="chart-container">
                    <canvas id="itemRatingDistributionChart"></canvas>
                </div>
            </div>
            <div class="shuriken-chart-card wide">
                <h2><?php esc_html_e('Voting Activity', 'shuriken-reviews'); ?></h2>
                <div class="chart-container">
                    <canvas id="itemDualAxisChart"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($is_parent && !empty($sub_ratings)) : ?>
    <!-- Sub-Ratings Breakdown -->
    <div class="shuriken-table-card full-width sub-ratings-breakdown">
        <h2>
            <?php Shuriken_Icons::render('share-2', array('width' => 18, 'height' => 18)); ?>
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
                            <?php if (!(Shuriken_Rating_Type::tryFrom($sub->rating_type ?: 'stars') ?? Shuriken_Rating_Type::Stars)->isBinary()) : ?><span class="star-display"><?php Shuriken_Icons::render('star', array('width' => 14, 'height' => 14)); ?></span> <?php endif; ?><?php echo esc_html($analytics->format_average_display($sub->average ?: 0, $sub->rating_type ?: 'stars', $sub->scale ?: 5, $sub->total_votes, $sub->total_rating)); ?>
                        </td>
                        <td>
                            <span class="effective-score <?php echo $sub->effect_type === 'negative' ? 'inverted' : ''; ?>">
                                <?php echo esc_html($analytics->format_average_display($sub->effective_average ?: 0, $sub->rating_type ?: 'stars', $sub->scale ?: 5, $sub->total_votes, $sub->total_rating)); ?>
                            </span>
                            <?php if ($sub->effect_type === 'negative') : ?>
                                <small class="inverted-note"><?php esc_html_e('(reduces parent)', 'shuriken-reviews'); ?></small>
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
        
        <?php $show_source_column = $is_parent && in_array($current_view, array('subs', 'total')); ?>
        <?php
        $votes_sort_url = $base_filter_url;
        // Preserve paged param removed when sort changes
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="column-id"><?php esc_html_e('ID', 'shuriken-reviews'); ?></th>
                    <th class="column-rating"><?php echo shuriken_sort_link('rating', $votes_sort_by, $votes_sort_order, $votes_sort_url, __('Rating', 'shuriken-reviews'), 'votes_sort_by', 'votes_sort_order'); ?></th>
                    <?php if ($show_source_column) : ?>
                    <th class="column-source"><?php esc_html_e('Source', 'shuriken-reviews'); ?></th>
                    <?php endif; ?>
                    <th class="column-voter"><?php esc_html_e('Voter', 'shuriken-reviews'); ?></th>
                    <th class="column-ip"><?php esc_html_e('IP Address', 'shuriken-reviews'); ?></th>
                    <th class="column-date"><?php echo shuriken_sort_link('date', $votes_sort_by, $votes_sort_order, $votes_sort_url, __('Date & Time', 'shuriken-reviews'), 'votes_sort_by', 'votes_sort_order'); ?></th>
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
                            <?php if ($show_source_column) : ?>
                            <td class="column-source">
                                <?php if ($vote->rating_id == $rating_id) : ?>
                                    <span class="source-badge direct" title="<?php esc_attr_e('Direct vote on parent', 'shuriken-reviews'); ?>">
                                        <?php Shuriken_Icons::render('star', array('width' => 14, 'height' => 14)); ?>
                                        <?php esc_html_e('Direct', 'shuriken-reviews'); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="source-badge sub" title="<?php echo esc_attr($vote->rating_name); ?>">
                                        <?php Shuriken_Icons::render('arrow-right', array('width' => 14, 'height' => 14)); ?>
                                        <?php echo esc_html($vote->rating_name); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
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
                        <td colspan="<?php echo $show_source_column ? 6 : 5; ?>"><?php esc_html_e('No votes recorded yet', 'shuriken-reviews'); ?></td>
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
                <?php Shuriken_Icons::render('download', array('width' => 18, 'height' => 18)); ?>
                <?php esc_html_e('Export Votes to CSV', 'shuriken-reviews'); ?>
            </button>
        </form>
    </div>
    
    <?php endif; // end: global/contextual view conditional ?>
</div>

<?php if ($current_scope === 'contextual') : ?>
<!-- Contextual View Charts Script -->
<script>
var shurikenContextData = {
    topContexts: <?php echo wp_json_encode($top_contexts); ?>,
    avgDistribution: <?php echo wp_json_encode($context_avg_dist); ?>,
    votingActivity: <?php echo wp_json_encode($contextual_votes_timeline); ?>,
    ratingType: <?php echo wp_json_encode($rating_type); ?>,
    i18n: {
        votes: <?php echo wp_json_encode(__('Votes', 'shuriken-reviews')); ?>,
        posts: <?php echo wp_json_encode(__('Posts', 'shuriken-reviews')); ?>,
        average: <?php echo wp_json_encode(__('Average', 'shuriken-reviews')); ?>
    }
};

jQuery(document).ready(function($) {
    if (typeof Chart === 'undefined') return;

    var d = shurikenContextData;
    var u = window.shurikenAnalyticsUtils || {};
    var gridColor = u.colors ? u.colors.grid : '#f0f0f1';
    var tickColor = u.colors ? u.colors.tick : '#646970';
    var formatDate = u.formatDate || function(dateStr) {
        var date = new Date(dateStr);
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    };
    
    // Top Posts by Votes (horizontal bar)
    var topCtx = document.getElementById('ctxTopPostsChart');
    if (topCtx && d.topContexts && d.topContexts.length) {
        new Chart(topCtx, {
            type: 'bar',
            data: {
                labels: d.topContexts.map(function(c) {
                    var t = c.title || '#' + c.context_id;
                    return t.length > 30 ? t.substring(0, 27) + '...' : t;
                }),
                datasets: [{
                    label: d.i18n.votes,
                    data: d.topContexts.map(function(c) { return c.ctx_votes; }),
                    backgroundColor: 'rgba(34, 113, 177, 0.6)',
                    borderColor: '#2271b1',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } },
                    y: { grid: { display: false }, ticks: { color: tickColor, font: { size: 11 } } }
                }
            }
        });
    }
    
    // Average Rating Distribution Across Posts
    var distCtx = document.getElementById('ctxAvgDistChart');
    if (distCtx && d.avgDistribution) {
        var labels = Object.keys(d.avgDistribution);
        var data = Object.values(d.avgDistribution);
        var allColors = ['#dc3232', '#f56e28', '#ffb900', '#7ad03a', '#00a32a'];
        var barColors = data.length <= allColors.length
            ? allColors.slice(allColors.length - data.length)
            : data.map(function(_, i) { return allColors[i % allColors.length]; });
        
        new Chart(distCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: d.i18n.posts,
                    data: data,
                    backgroundColor: barColors,
                    borderColor: barColors,
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: tickColor } },
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } }
                }
            }
        });
    }
    
    // Contextual Voting Activity (line chart)
    var actCtx = document.getElementById('ctxVotingActivityChart');
    if (actCtx && d.votingActivity && d.votingActivity.length) {
        new Chart(actCtx, {
            type: 'line',
            data: {
                labels: d.votingActivity.map(function(r) { return formatDate(r.vote_date); }),
                datasets: [{
                    label: d.i18n.votes,
                    data: d.votingActivity.map(function(r) { return parseInt(r.vote_count, 10); }),
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12, color: tickColor } },
                    y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } }
                }
            }
        });
    }
});
</script>
<?php else : ?>

<script>
var shurikenItemStatsData = {
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
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded');
        return;
    }
    
    var d = shurikenItemStatsData;
    var u = window.shurikenAnalyticsUtils || {};
    var gridColor = u.colors ? u.colors.grid : '#f0f0f1';
    var tickColor = u.colors ? u.colors.tick : '#646970';
    var formatDate = u.formatDate || function(dateStr) {
        var date = new Date(dateStr);
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    };
    
    if (d.ratingType === 'like_dislike') {
        // Approval Ring Chart
        var ringCtx = document.getElementById('itemApprovalRingChart');
        if (ringCtx && (d.likes + d.dislikes) > 0) {
            new Chart(ringCtx, {
                type: 'doughnut',
                data: {
                    labels: [d.i18n.likes, d.i18n.dislikes],
                    datasets: [{
                        data: [d.likes, d.dislikes],
                        backgroundColor: ['#00a32a', '#dc3232'],
                        borderColor: '#fff',
                        borderWidth: 3,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } },
                        tooltip: {
                            backgroundColor: '#1d2327', titleColor: '#fff', bodyColor: '#fff', padding: 12,
                            callbacks: {
                                label: function(ctx) {
                                    var total = d.likes + d.dislikes;
                                    return ctx.label + ': ' + ctx.parsed + ' (' + Math.round((ctx.parsed / total) * 100) + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Approval Trend Line
        var trendCtx = document.getElementById('itemApprovalTrendChart');
        if (trendCtx && d.approvalTrend && d.approvalTrend.length) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: d.approvalTrend.map(function(r) { return formatDate(r.vote_date); }),
                    datasets: [{
                        label: d.i18n.approvalRate,
                        data: d.approvalTrend.map(function(r) { return parseFloat(r.approval_rate); }),
                        borderColor: '#00a32a',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12, color: tickColor } },
                        y: { beginAtZero: true, max: 100, grid: { color: gridColor }, ticks: { callback: function(v) { return v + '%'; }, color: tickColor } }
                    }
                }
            });
        }
        
    } else if (d.ratingType === 'approval') {
        // Cumulative Approvals
        var cumCtx = document.getElementById('itemCumulativeChart');
        if (cumCtx && d.cumulativeApprovals && d.cumulativeApprovals.length) {
            new Chart(cumCtx, {
                type: 'line',
                data: {
                    labels: d.cumulativeApprovals.map(function(r) { return formatDate(r.vote_date); }),
                    datasets: [{
                        label: d.i18n.cumulative,
                        data: d.cumulativeApprovals.map(function(r) { return parseInt(r.cumulative_count, 10); }),
                        borderColor: '#8c5383',
                        backgroundColor: 'rgba(140, 83, 131, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12, color: tickColor } },
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } }
                    }
                }
            });
        }
        
    } else {
        // Stars/Numeric: Distribution Bar
        var distCtx = document.getElementById('itemRatingDistributionChart');
        if (distCtx) {
            var allColors = ['#dc3232', '#f56e28', '#ffb900', '#7ad03a', '#00a32a'];
            var distData = d.ratingDistribution || [];
            var colors = distData.length <= allColors.length
                ? allColors.slice(allColors.length - distData.length)
                : allColors;
            var distLabels = d.distributionLabels || distData.map(function(_, i) { return (i + 1) + ' \u2605'; });
            new Chart(distCtx, {
                type: 'bar',
                data: {
                    labels: distLabels,
                    datasets: [{ label: d.i18n.votes, data: distData, backgroundColor: colors, borderColor: colors, borderWidth: 1, borderRadius: 4 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: tickColor } },
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { precision: 0, color: tickColor } }
                    }
                }
            });
        }
        
        // Stars/Numeric: Dual-axis (votes + avg)
        var dualCtx = document.getElementById('itemDualAxisChart');
        if (dualCtx && d.dualAxisData && d.dualAxisData.length) {
            new Chart(dualCtx, {
                type: 'bar',
                data: {
                    labels: d.dualAxisData.map(function(r) { return formatDate(r.vote_date); }),
                    datasets: [
                        {
                            type: 'bar',
                            label: d.i18n.votes,
                            data: d.dualAxisData.map(function(r) { return parseInt(r.vote_count, 10); }),
                            backgroundColor: 'rgba(34, 113, 177, 0.3)',
                            borderColor: '#2271b1',
                            borderWidth: 1,
                            borderRadius: 3,
                            yAxisID: 'y'
                        },
                        {
                            type: 'line',
                            label: d.i18n.average,
                            data: d.dualAxisData.map(function(r) { return parseFloat(r.display_daily_avg); }),
                            borderColor: '#f59e0b',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: 3,
                            pointBackgroundColor: '#f59e0b',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, padding: 12 } },
                        tooltip: {
                            backgroundColor: '#1d2327', titleColor: '#fff', bodyColor: '#fff', padding: 12
                        }
                    },
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
<?php endif; // end: contextual/global script conditional ?>
