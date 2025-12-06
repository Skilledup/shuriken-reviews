<?php
/**
 * Shuriken Reviews Analytics Page
 *
 * Displays comprehensive statistics and analytics for ratings.
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

// Get date range filter
$date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '30';
$valid_ranges = array('7', '30', '90', '365', 'all');
if (!in_array($date_range, $valid_ranges, true)) {
    $date_range = '30';
}

// Fetch all data using the analytics class
$overall_stats    = $analytics->get_overall_stats();
$vote_counts      = $analytics->get_vote_counts($date_range);
$vote_change_percent = $analytics->get_vote_change_percent($date_range);
$top_rated        = $analytics->get_top_rated(10, 1, 3.0);
$most_voted       = $analytics->get_most_voted(10);
$low_performers   = $analytics->get_low_performers(10, 1, 3.0);
$distribution_array = $analytics->get_rating_distribution($date_range);
$votes_over_time  = $analytics->get_votes_over_time($date_range);
$recent_votes     = $analytics->get_recent_votes(10);

// New hierarchical data
$rating_types     = $overall_stats->rating_types;
$parent_ratings   = $analytics->get_parent_ratings_with_stats(5);
$mirrored_ratings = $analytics->get_mirrored_ratings(5);

// Extract values for template
$total_ratings       = $overall_stats->total_ratings;
$total_votes         = $overall_stats->total_votes;
$overall_average     = $overall_stats->overall_average;
$unique_voters       = $overall_stats->unique_voters;
$current_period_votes = $vote_counts->period_votes;
$logged_in_votes     = $vote_counts->member_votes;
$guest_votes         = $vote_counts->guest_votes;
?>

<div class="wrap shuriken-analytics">
    <h1><?php esc_html_e('Stats & Analytics', 'shuriken-reviews'); ?></h1>
    
    <!-- Date Range Filter -->
    <div class="shuriken-filter-bar">
        <form method="get" action="">
            <input type="hidden" name="page" value="shuriken-reviews-analytics">
            <label for="date_range"><?php esc_html_e('Time Period:', 'shuriken-reviews'); ?></label>
            <select name="date_range" id="date_range" onchange="this.form.submit()">
                <option value="7" <?php selected($date_range, '7'); ?>><?php esc_html_e('Last 7 Days', 'shuriken-reviews'); ?></option>
                <option value="30" <?php selected($date_range, '30'); ?>><?php esc_html_e('Last 30 Days', 'shuriken-reviews'); ?></option>
                <option value="90" <?php selected($date_range, '90'); ?>><?php esc_html_e('Last 90 Days', 'shuriken-reviews'); ?></option>
                <option value="365" <?php selected($date_range, '365'); ?>><?php esc_html_e('Last Year', 'shuriken-reviews'); ?></option>
                <option value="all" <?php selected($date_range, 'all'); ?>><?php esc_html_e('All Time', 'shuriken-reviews'); ?></option>
            </select>
        </form>
    </div>
    
    <!-- Overview Cards -->
    <div class="shuriken-stats-grid">
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-star-filled"></span>
            <div class="stat-content">
                <h3><?php echo esc_html($total_ratings); ?></h3>
                <p><?php esc_html_e('Total Rating Items', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-chart-bar"></span>
            <div class="stat-content">
                <h3>
                    <?php echo esc_html($current_period_votes ?: 0); ?>
                    <?php if ($vote_change_percent !== null) : ?>
                        <span class="stat-change <?php echo $vote_change_percent >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo ($vote_change_percent >= 0 ? '+' : '') . $vote_change_percent; ?>%
                        </span>
                    <?php endif; ?>
                </h3>
                <p><?php esc_html_e('Votes (Period)', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-performance"></span>
            <div class="stat-content">
                <h3><?php echo $overall_average ? number_format($overall_average, 1) : '0'; ?>/5</h3>
                <p><?php esc_html_e('Overall Average', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon dashicons dashicons-groups"></span>
            <div class="stat-content">
                <h3><?php echo esc_html($unique_voters ?: 0); ?></h3>
                <p><?php esc_html_e('Unique Voters', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Secondary Stats Row -->
    <div class="shuriken-stats-grid secondary">
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($total_votes ?: 0); ?></h4>
                <p><?php esc_html_e('Total Votes (All Time)', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($logged_in_votes ?: 0); ?></h4>
                <p><?php esc_html_e('Member Votes', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card small">
            <div class="stat-content">
                <h4><?php echo esc_html($guest_votes ?: 0); ?></h4>
                <p><?php esc_html_e('Guest Votes', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Rating Types Breakdown -->
    <div class="shuriken-stats-grid rating-types">
        <div class="shuriken-stat-card type-card">
            <span class="type-icon standalone">●</span>
            <div class="stat-content">
                <h4><?php echo esc_html($rating_types->standalone); ?></h4>
                <p><?php esc_html_e('Standalone', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card type-card">
            <span class="type-icon parent">●</span>
            <div class="stat-content">
                <h4><?php echo esc_html($rating_types->parent); ?></h4>
                <p><?php esc_html_e('Parent Ratings', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card type-card">
            <span class="type-icon sub">●</span>
            <div class="stat-content">
                <h4><?php echo esc_html($rating_types->sub); ?></h4>
                <p>
                    <?php esc_html_e('Sub-Ratings', 'shuriken-reviews'); ?>
                    <?php if ($rating_types->sub > 0): ?>
                        <span class="sub-breakdown">
                            (<span class="positive">+<?php echo esc_html($rating_types->sub_positive); ?></span> / 
                            <span class="negative">-<?php echo esc_html($rating_types->sub_negative); ?></span>)
                        </span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="shuriken-stat-card type-card">
            <span class="type-icon mirror">●</span>
            <div class="stat-content">
                <h4><?php echo esc_html($rating_types->mirror); ?></h4>
                <p><?php esc_html_e('Mirrors', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <div class="shuriken-stat-card type-card">
            <span class="type-icon display-only">●</span>
            <div class="stat-content">
                <h4><?php echo esc_html($rating_types->display_only); ?></h4>
                <p><?php esc_html_e('Display Only', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="shuriken-charts-row">
        <!-- Votes Over Time -->
        <div class="shuriken-chart-card wide">
            <h2><?php esc_html_e('Voting Activity', 'shuriken-reviews'); ?></h2>
            <div class="chart-container">
                <canvas id="votesOverTimeChart"></canvas>
            </div>
        </div>
        
        <!-- Rating Distribution -->
        <div class="shuriken-chart-card">
            <h2><?php esc_html_e('Rating Distribution', 'shuriken-reviews'); ?></h2>
            <div class="chart-container">
                <canvas id="ratingDistributionChart"></canvas>
            </div>
        </div>
        
        <!-- User Type Distribution -->
        <div class="shuriken-chart-card">
            <h2><?php esc_html_e('Voter Types', 'shuriken-reviews'); ?></h2>
            <div class="chart-container">
                <canvas id="userTypeChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Tables Section -->
    <div class="shuriken-tables-row">
        <!-- Top Rated -->
        <div class="shuriken-table-card">
            <h2>
                <span class="dashicons dashicons-awards"></span>
                <?php esc_html_e('Top Rated Items', 'shuriken-reviews'); ?>
            </h2>
            <p class="table-description"><?php esc_html_e('Items with average rating of 3 stars or higher', 'shuriken-reviews'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Rank', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Name', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Average', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Votes', 'shuriken-reviews'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($top_rated) : ?>
                        <?php $rank = 1; foreach ($top_rated as $item) : 
                            $item_url = admin_url('admin.php?page=shuriken-reviews&s=' . urlencode($item->name) . '#rating-' . $item->id);
                            $stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $item->id);
                        ?>
                            <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($stats_url); ?>">
                                <td>
                                    <?php if ($rank <= 3) : ?>
                                        <span class="rank-badge rank-<?php echo $rank; ?>"><?php echo $rank; ?></span>
                                    <?php else : ?>
                                        <?php echo $rank; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($item_url); ?>" class="rating-item-link">
                                        <?php echo esc_html($item->name); ?>
                                    </a>
                                    <span class="row-actions">
                                        <a href="<?php echo esc_url($stats_url); ?>" class="stats-link" title="<?php esc_attr_e('View Statistics', 'shuriken-reviews'); ?>">
                                            <span class="dashicons dashicons-chart-area"></span>
                                        </a>
                                    </span>
                                </td>
                                <td>
                                    <span class="star-display">★</span>
                                    <?php echo esc_html($item->average); ?>/5
                                </td>
                                <td><?php echo esc_html($item->total_votes); ?></td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('No top rated items yet (need average ≥ 3 stars)', 'shuriken-reviews'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Most Voted -->
        <div class="shuriken-table-card">
            <h2>
                <span class="dashicons dashicons-megaphone"></span>
                <?php esc_html_e('Most Popular Items', 'shuriken-reviews'); ?>
            </h2>
            <p class="table-description"><?php esc_html_e('Standalone and parent ratings with most votes', 'shuriken-reviews'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Rank', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Name', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Votes', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Average', 'shuriken-reviews'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($most_voted) : ?>
                        <?php $rank = 1; foreach ($most_voted as $item) : 
                            $item_url = admin_url('admin.php?page=shuriken-reviews&s=' . urlencode($item->name) . '#rating-' . $item->id);
                            $stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $item->id);
                        ?>
                            <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($stats_url); ?>">
                                <td>
                                    <?php if ($rank <= 3) : ?>
                                        <span class="rank-badge rank-<?php echo $rank; ?>"><?php echo $rank; ?></span>
                                    <?php else : ?>
                                        <?php echo $rank; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($item_url); ?>" class="rating-item-link">
                                        <?php echo esc_html($item->name); ?>
                                    </a>
                                    <span class="row-actions">
                                        <a href="<?php echo esc_url($stats_url); ?>" class="stats-link" title="<?php esc_attr_e('View Statistics', 'shuriken-reviews'); ?>">
                                            <span class="dashicons dashicons-chart-area"></span>
                                        </a>
                                    </span>
                                </td>
                                <td><strong><?php echo esc_html($item->total_votes); ?></strong></td>
                                <td>
                                    <span class="star-display">★</span>
                                    <?php echo esc_html($item->average ?: '0'); ?>/5
                                </td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('No data available yet', 'shuriken-reviews'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Bottom Section -->
    <div class="shuriken-tables-row">
        <!-- Low Performers -->
        <div class="shuriken-table-card">
            <h2>
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e('Low Performers', 'shuriken-reviews'); ?>
            </h2>
            <p class="table-description"><?php esc_html_e('Standalone and parent ratings with average below 3 stars', 'shuriken-reviews'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Average', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Votes', 'shuriken-reviews'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($low_performers) : ?>
                        <?php foreach ($low_performers as $item) : 
                            $item_url = admin_url('admin.php?page=shuriken-reviews&s=' . urlencode($item->name) . '#rating-' . $item->id);
                            $stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $item->id);
                        ?>
                            <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($stats_url); ?>">
                                <td>
                                    <a href="<?php echo esc_url($item_url); ?>" class="rating-item-link">
                                        <?php echo esc_html($item->name); ?>
                                    </a>
                                    <span class="row-actions">
                                        <a href="<?php echo esc_url($stats_url); ?>" class="stats-link" title="<?php esc_attr_e('View Statistics', 'shuriken-reviews'); ?>">
                                            <span class="dashicons dashicons-chart-area"></span>
                                        </a>
                                    </span>
                                </td>
                                <td>
                                    <span class="star-display low">★</span>
                                    <?php echo esc_html($item->average); ?>/5
                                </td>
                                <td><?php echo esc_html($item->total_votes); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="3"><?php esc_html_e('No low performers (all items have average ≥ 3 stars)', 'shuriken-reviews'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Recent Activity -->
        <div class="shuriken-table-card">
            <h2>
                <span class="dashicons dashicons-clock"></span>
                <?php esc_html_e('Recent Activity', 'shuriken-reviews'); ?>
            </h2>
            <p class="table-description"><?php esc_html_e('Latest votes received', 'shuriken-reviews'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Item', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Rating', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Voter', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Date', 'shuriken-reviews'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_votes) : ?>
                        <?php foreach ($recent_votes as $vote) : 
                            $item_url = admin_url('admin.php?page=shuriken-reviews&s=' . urlencode($vote->rating_name) . '#rating-' . $vote->rating_id);
                            $stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $vote->rating_id);
                        ?>
                            <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($stats_url); ?>">
                                <td>
                                    <a href="<?php echo esc_url($item_url); ?>" class="rating-item-link">
                                        <?php echo esc_html($vote->rating_name); ?>
                                    </a>
                                    <span class="row-actions">
                                        <a href="<?php echo esc_url($stats_url); ?>" class="stats-link" title="<?php esc_attr_e('View Statistics', 'shuriken-reviews'); ?>">
                                            <span class="dashicons dashicons-chart-area"></span>
                                        </a>
                                    </span>
                                </td>
                                <td>
                                    <span class="star-rating-display">
                                        <?php echo str_repeat('★', intval($vote->rating_value)); ?>
                                        <?php echo str_repeat('☆', 5 - intval($vote->rating_value)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($vote->user_id > 0) : ?>
                                        <?php echo $vote->display_name ? esc_html($vote->display_name) : __('Deleted User', 'shuriken-reviews'); ?>
                                    <?php else : ?>
                                        <em><?php esc_html_e('Guest', 'shuriken-reviews'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($analytics->format_time_ago($vote->date_created)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('No recent activity', 'shuriken-reviews'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($parent_ratings || $mirrored_ratings) : ?>
    <!-- Hierarchical Ratings Section -->
    <div class="shuriken-tables-row hierarchical-section">
        <?php if ($parent_ratings) : ?>
        <!-- Parent Ratings Performance -->
        <div class="shuriken-table-card">
            <h2>
                <span class="dashicons dashicons-networking"></span>
                <?php esc_html_e('Parent Ratings Performance', 'shuriken-reviews'); ?>
            </h2>
            <p class="table-description"><?php esc_html_e('Ratings with sub-ratings and their calculated scores', 'shuriken-reviews'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Average', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Sub-Ratings', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Effect Mix', 'shuriken-reviews'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parent_ratings as $parent) : 
                        $stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $parent->id);
                    ?>
                        <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($stats_url); ?>">
                            <td>
                                <strong><?php echo esc_html($parent->name); ?></strong>
                                <?php if ($parent->display_only): ?>
                                    <span class="type-badge display-only-badge" title="<?php esc_attr_e('Display Only - No direct voting', 'shuriken-reviews'); ?>">
                                        <?php esc_html_e('Display Only', 'shuriken-reviews'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="star-display">★</span>
                                <?php echo esc_html($parent->average ?: '0'); ?>/5
                                <br><small class="votes-count"><?php printf(esc_html__('%s votes', 'shuriken-reviews'), esc_html($parent->total_votes)); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($parent->sub_count); ?></strong>
                                <?php esc_html_e('sub-ratings', 'shuriken-reviews'); ?>
                            </td>
                            <td>
                                <span class="effect-indicator positive" title="<?php esc_attr_e('Positive effect', 'shuriken-reviews'); ?>">
                                    +<?php echo esc_html($parent->positive_subs); ?>
                                </span>
                                <span class="effect-indicator negative" title="<?php esc_attr_e('Negative effect', 'shuriken-reviews'); ?>">
                                    -<?php echo esc_html($parent->negative_subs); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if ($mirrored_ratings) : ?>
        <!-- Mirrored Ratings -->
        <div class="shuriken-table-card">
            <h2>
                <span class="dashicons dashicons-admin-links"></span>
                <?php esc_html_e('Mirrored Ratings', 'shuriken-reviews'); ?>
            </h2>
            <p class="table-description"><?php esc_html_e('Original ratings that have mirrors', 'shuriken-reviews'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Original Rating', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Average', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Mirrors', 'shuriken-reviews'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mirrored_ratings as $rating) : 
                        $stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $rating->id);
                    ?>
                        <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($stats_url); ?>">
                            <td>
                                <strong><?php echo esc_html($rating->name); ?></strong>
                            </td>
                            <td>
                                <span class="star-display">★</span>
                                <?php echo esc_html($rating->average ?: '0'); ?>/5
                                <br><small class="votes-count"><?php printf(esc_html__('%s votes', 'shuriken-reviews'), esc_html($rating->total_votes)); ?></small>
                            </td>
                            <td>
                                <span class="mirror-count-badge">
                                    <?php echo esc_html($rating->mirror_count); ?>
                                </span>
                                <?php echo esc_html(_n('mirror', 'mirrors', $rating->mirror_count, 'shuriken-reviews')); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Export Section -->
    <div class="shuriken-export-section">
        <h2><?php esc_html_e('Export Data', 'shuriken-reviews'); ?></h2>
        <p><?php esc_html_e('Download your ratings data for further analysis.', 'shuriken-reviews'); ?></p>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('shuriken_export_data', 'shuriken_export_nonce'); ?>
            <input type="hidden" name="action" value="shuriken_export_ratings">
            <button type="submit" class="button button-secondary">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export to CSV', 'shuriken-reviews'); ?>
            </button>
        </form>
    </div>
</div>

<script>
// Pass PHP data to JavaScript for charts
var shurikenAnalyticsData = {
    votesOverTime: <?php echo wp_json_encode($votes_over_time); ?>,
    ratingDistribution: <?php echo wp_json_encode(array_values($distribution_array)); ?>,
    userTypeData: {
        members: <?php echo intval($logged_in_votes); ?>,
        guests: <?php echo intval($guest_votes); ?>
    },
    i18n: {
        votes: <?php echo wp_json_encode(__('Votes', 'shuriken-reviews')); ?>,
        members: <?php echo wp_json_encode(__('Members', 'shuriken-reviews')); ?>,
        guests: <?php echo wp_json_encode(__('Guests', 'shuriken-reviews')); ?>,
        stars: <?php echo wp_json_encode(__('Stars', 'shuriken-reviews')); ?>
    }
};
</script>
