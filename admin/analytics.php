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

global $wpdb;
$ratings_table = $wpdb->prefix . 'shuriken_ratings';
$votes_table = $wpdb->prefix . 'shuriken_votes';

// Get date range filter
$date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '30';
$valid_ranges = array('7', '30', '90', '365', 'all');
if (!in_array($date_range, $valid_ranges, true)) {
    $date_range = '30';
}

// Build date condition for queries
$date_condition = '';
if ($date_range !== 'all') {
    $date_condition = $wpdb->prepare(
        " AND date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)",
        intval($date_range)
    );
}

// Get overall stats
$total_ratings = $wpdb->get_var("SELECT COUNT(*) FROM $ratings_table");
$total_votes = $wpdb->get_var("SELECT SUM(total_votes) FROM $ratings_table");
$overall_average = $wpdb->get_var(
    "SELECT AVG(total_rating / NULLIF(total_votes, 0)) FROM $ratings_table WHERE total_votes > 0"
);

// Get unique voters count
$unique_voters = $wpdb->get_var(
    "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) FROM $votes_table"
);

// Get top rated items (minimum 1 vote, average >= 3 to qualify as "top")
$top_rated = $wpdb->get_results(
    "SELECT id, name, total_votes, total_rating, 
            ROUND(total_rating / NULLIF(total_votes, 0), 1) as average 
     FROM $ratings_table 
     WHERE total_votes >= 1 
       AND (total_rating / NULLIF(total_votes, 0)) >= 3
     ORDER BY average DESC, total_votes DESC 
     LIMIT 10"
);

// Get most voted items
$most_voted = $wpdb->get_results(
    "SELECT id, name, total_votes, total_rating,
            ROUND(total_rating / NULLIF(total_votes, 0), 1) as average
     FROM $ratings_table 
     ORDER BY total_votes DESC 
     LIMIT 10"
);

// Get low performers (items with average rating < 3, minimum 1 vote)
$low_performers = $wpdb->get_results(
    "SELECT id, name, total_votes, total_rating, 
            ROUND(total_rating / NULLIF(total_votes, 0), 1) as average 
     FROM $ratings_table 
     WHERE total_votes >= 1 
       AND (total_rating / NULLIF(total_votes, 0)) < 3
     ORDER BY average ASC, total_votes DESC 
     LIMIT 10"
);

// Get rating distribution
$rating_distribution = $wpdb->get_results(
    "SELECT rating_value, COUNT(*) as count 
     FROM $votes_table 
     WHERE 1=1 $date_condition
     GROUP BY rating_value 
     ORDER BY rating_value"
);

// Ensure all ratings 1-5 are represented
$distribution_array = array_fill(1, 5, 0);
foreach ($rating_distribution as $dist) {
    $distribution_array[intval($dist->rating_value)] = intval($dist->count);
}

// Get votes over time (based on selected range)
$votes_over_time = $wpdb->get_results(
    "SELECT DATE(date_created) as vote_date, COUNT(*) as vote_count 
     FROM $votes_table 
     WHERE 1=1 $date_condition
     GROUP BY DATE(date_created) 
     ORDER BY vote_date"
);

// Get logged-in vs guest ratio
$logged_in_votes = $wpdb->get_var(
    "SELECT COUNT(*) FROM $votes_table WHERE user_id > 0 $date_condition"
);
$guest_votes = $wpdb->get_var(
    "SELECT COUNT(*) FROM $votes_table WHERE user_id = 0 $date_condition"
);

// Get recent activity (last 10 votes)
$recent_votes = $wpdb->get_results(
    "SELECT v.rating_id, v.rating_value, v.date_created, v.user_id, r.name as rating_name
     FROM $votes_table v
     JOIN $ratings_table r ON v.rating_id = r.id
     ORDER BY v.date_created DESC
     LIMIT 10"
);

// Calculate vote change compared to previous period
$current_period_votes = $wpdb->get_var(
    "SELECT COUNT(*) FROM $votes_table WHERE 1=1 $date_condition"
);

if ($date_range !== 'all') {
    $previous_period_condition = $wpdb->prepare(
        " AND date_created >= DATE_SUB(NOW(), INTERVAL %d DAY) AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)",
        intval($date_range) * 2,
        intval($date_range)
    );
    $previous_period_votes = $wpdb->get_var(
        "SELECT COUNT(*) FROM $votes_table WHERE 1=1 $previous_period_condition"
    );
    
    if ($previous_period_votes > 0) {
        $vote_change_percent = round((($current_period_votes - $previous_period_votes) / $previous_period_votes) * 100, 1);
    } else {
        $vote_change_percent = $current_period_votes > 0 ? 100 : 0;
    }
} else {
    $vote_change_percent = null;
}
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
            <p class="table-description"><?php esc_html_e('Items with the most votes', 'shuriken-reviews'); ?></p>
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
            <p class="table-description"><?php esc_html_e('Items with average rating below 3 stars', 'shuriken-reviews'); ?></p>
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
                                        <?php $user = get_userdata($vote->user_id); ?>
                                        <?php echo $user ? esc_html($user->display_name) : __('Deleted User', 'shuriken-reviews'); ?>
                                    <?php else : ?>
                                        <em><?php esc_html_e('Guest', 'shuriken-reviews'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(human_time_diff(mysql2date('U', $vote->date_created), current_time('timestamp')) . ' ' . __('ago', 'shuriken-reviews')); ?></td>
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
