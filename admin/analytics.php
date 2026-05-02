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

// Parse date range from request parameters (supports presets and custom ranges)
$date_range = $analytics->parse_date_range_params($_GET);
$date_range_label = $analytics->get_date_range_label($date_range);

// Determine current UI state
$range_type = isset($_GET['range_type']) ? sanitize_text_field($_GET['range_type']) : 'preset';
$preset_value = is_array($date_range) ? '30' : $date_range;
$start_date = is_array($date_range) && !empty($date_range['start']) ? $date_range['start'] : '';
$end_date = is_array($date_range) && !empty($date_range['end']) ? $date_range['end'] : '';

// Fetch data
$vote_counts         = $analytics->get_vote_counts($date_range);
$vote_change_percent = $analytics->get_vote_change_percent($date_range);
$participation       = $analytics->get_participation_rate();
$per_type_summary    = $analytics->get_per_type_summary();
$votes_by_type       = $analytics->get_votes_over_time_by_type($date_range);
$heatmap_data        = $analytics->get_voting_heatmap($date_range);
$momentum            = is_numeric($date_range) ? $analytics->get_momentum_items($date_range) : (object) array('rising' => array(), 'falling' => array());
$top_rated           = $analytics->get_top_rated(10, 1, 3.0, $date_range);
$most_voted          = $analytics->get_most_voted(10, $date_range);
$low_performers      = $analytics->get_low_performers(10, 1, 3.0, $date_range);
$recent_votes        = $analytics->get_recent_votes(10, null, $date_range);

// Extract values for template
$current_period_votes = $vote_counts->period_votes;
$member_votes         = $vote_counts->member_votes;
$guest_votes          = $vote_counts->guest_votes;
$unique_voters        = $analytics->get_overall_stats()->unique_voters;
$contextual_posts     = $analytics->get_contextual_post_count();
?>

<div class="wrap shuriken-analytics">
    <h1><?php esc_html_e('Stats & Analytics', 'shuriken-reviews'); ?></h1>
    
    <?php
    $form_id = 'shuriken-date-filter-form';
    $hidden_fields_html = '<input type="hidden" name="page" value="shuriken-reviews-analytics">';
    $clear_url = admin_url('admin.php?page=shuriken-reviews-analytics');
    include __DIR__ . '/partials/date-filter-bar.php';
    ?>
    
    <!-- Overview Cards -->
    <div class="shuriken-stats-grid">
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('bar-chart-2', array('width' => 28, 'height' => 28)); ?></span>
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
            <span class="stat-icon"><?php Shuriken_Icons::render('users', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3><?php echo esc_html($unique_voters ?: 0); ?></h3>
                <p><?php esc_html_e('Unique Voters', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('eye', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3><?php echo esc_html($participation->active_items); ?> / <?php echo esc_html($participation->total_items); ?></h3>
                <p><?php esc_html_e('Items With Votes', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('user', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3>
                    <?php 
                    $total_for_pct = $member_votes + $guest_votes;
                    $member_pct = $total_for_pct > 0 ? round(($member_votes / $total_for_pct) * 100) : 0;
                    echo esc_html($member_pct) . '%';
                    ?>
                </h3>
                <p><?php printf(esc_html__('Members (%s) / Guests (%s)', 'shuriken-reviews'), esc_html($member_votes), esc_html($guest_votes)); ?></p>
            </div>
        </div>
        
        <?php if ($contextual_posts > 0) : ?>
        <div class="shuriken-stat-card">
            <span class="stat-icon"><?php Shuriken_Icons::render('map-pin', array('width' => 28, 'height' => 28)); ?></span>
            <div class="stat-content">
                <h3><?php echo esc_html($contextual_posts); ?></h3>
                <p><?php esc_html_e('Posts with Per-Post Votes', 'shuriken-reviews'); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Per-Type Summary Row -->
    <?php if (!empty($per_type_summary)) : ?>
    <div class="shuriken-type-summary-row">
        <?php foreach ($per_type_summary as $type_summary) : ?>
            <div class="type-summary-card type-<?php echo esc_attr($type_summary->rating_type); ?>">
                <span class="type-summary-icon">
                    <?php
                    $symbols = Shuriken_Icons::rating_symbols(18);
                    $type_enum = Shuriken_Rating_Type::tryFrom($type_summary->rating_type) ?? Shuriken_Rating_Type::Stars;
                    switch ($type_enum) {
                        case Shuriken_Rating_Type::Stars: echo $symbols['star_filled']; break;
                        case Shuriken_Rating_Type::LikeDislike: echo $symbols['thumbs_up'] . $symbols['thumbs_down']; break;
                        case Shuriken_Rating_Type::Numeric: echo '#'; break;
                        case Shuriken_Rating_Type::Approval: echo $symbols['thumbs_up']; break;
                    }
                    ?>
                </span>
                <div class="type-summary-content">
                    <strong>
                        <?php
                        if ($type_enum->isBinary()) {
                            echo esc_html($type_summary->approval_rate) . '%';
                        } else {
                            echo esc_html($type_summary->weighted_average) . '/' . esc_html($type_summary->scale);
                        }
                        ?>
                    </strong>
                    <span class="type-summary-label">
                        <?php
                        $type_labels = array(
                            'stars' => __('Stars', 'shuriken-reviews'),
                            'like_dislike' => __('Like/Dislike', 'shuriken-reviews'),
                            'numeric' => __('Numeric', 'shuriken-reviews'),
                            'approval' => __('Approval', 'shuriken-reviews'),
                        );
                        echo esc_html($type_labels[$type_summary->rating_type] ?? ucfirst($type_summary->rating_type));
                        ?>
                        <small>(<?php printf(esc_html(_n('%d item', '%d items', $type_summary->item_count, 'shuriken-reviews')), $type_summary->item_count); ?>)</small>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Participation Bar -->
    <div class="shuriken-participation-bar">
        <div class="participation-label">
            <?php Shuriken_Icons::render('area-chart', array('width' => 18, 'height' => 18)); ?>
            <?php esc_html_e('Participation', 'shuriken-reviews'); ?>
            <strong><?php echo esc_html($participation->rate); ?>%</strong>
        </div>
        <div class="participation-track">
            <div class="participation-fill" style="width: <?php echo esc_attr($participation->rate > 0 ? max(2, $participation->rate) : 0); ?>%;"></div>
        </div>
        <small>
            <?php printf(
                esc_html__('%d of %d ratings have received direct votes', 'shuriken-reviews'),
                $participation->active_items,
                $participation->total_items
            ); ?>
            &nbsp;&mdash;&nbsp;
            <span class="participation-note"><?php esc_html_e('Per-post ratings are excluded; the denominator is unknown until a user votes on them.', 'shuriken-reviews'); ?></span>
        </small>
    </div>
    
    <!-- Charts Section -->
    <div class="shuriken-charts-row dashboard-charts">
        <!-- Voting Activity by Type (Stacked Area) -->
        <div class="shuriken-chart-card wide">
            <h2><?php esc_html_e('Voting Activity', 'shuriken-reviews'); ?></h2>
            <div class="chart-container">
                <canvas id="votesOverTimeChart"></canvas>
            </div>
        </div>
        
        <!-- Voting Heatmap -->
        <div class="shuriken-chart-card">
            <h2><?php esc_html_e('When Users Vote', 'shuriken-reviews'); ?></h2>
            <div class="heatmap-container" id="votingHeatmap"></div>
        </div>
    </div>
    
    <?php if (!empty($momentum->rising) || !empty($momentum->falling)) : ?>
    <!-- Momentum Section -->
    <div class="shuriken-momentum-section">
        <h2>
            <?php Shuriken_Icons::render('trending-up', array('width' => 18, 'height' => 18)); ?>
            <?php esc_html_e('Momentum', 'shuriken-reviews'); ?>
        </h2>
        <div class="momentum-grid">
            <?php if (!empty($momentum->rising)) : ?>
            <div class="momentum-column rising">
                <h3><?php esc_html_e('Rising', 'shuriken-reviews'); ?></h3>
                <?php foreach ($momentum->rising as $item) :
                    $stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $item->id);
                ?>
                    <div class="momentum-item">
                        <a href="<?php echo esc_url($stats_url); ?>"><?php echo esc_html($item->name); ?></a>
                        <span class="momentum-delta positive">+<?php echo esc_html(round($item->display_delta, 1)); ?></span>
                        <small><?php echo esc_html($analytics->format_average_display($item->recent_avg, $item->rating_type ?? 'stars', $item->scale ?? 5, 0, 0)); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($momentum->falling)) : ?>
            <div class="momentum-column falling">
                <h3><?php esc_html_e('Falling', 'shuriken-reviews'); ?></h3>
                <?php foreach ($momentum->falling as $item) :
                    $stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $item->id);
                ?>
                    <div class="momentum-item">
                        <a href="<?php echo esc_url($stats_url); ?>"><?php echo esc_html($item->name); ?></a>
                        <span class="momentum-delta negative"><?php echo esc_html(round($item->display_delta, 1)); ?></span>
                        <small><?php echo esc_html($analytics->format_average_display($item->recent_avg, $item->rating_type ?? 'stars', $item->scale ?? 5, 0, 0)); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tables Section -->
    <div class="shuriken-tables-row">
        <!-- Top Rated -->
        <div class="shuriken-table-card">
            <h2>
                <?php Shuriken_Icons::render('award', array('width' => 18, 'height' => 18)); ?>
                <?php esc_html_e('Top Rated', 'shuriken-reviews'); ?>
            </h2>
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
                                    <a href="<?php echo esc_url($stats_url); ?>" class="rating-item-link">
                                        <?php echo esc_html($item->name); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($analytics->format_average_display($item->average ?: 0, $item->rating_type ?? 'stars', $item->scale ?? 5, $item->total_votes, $item->total_rating)); ?></td>
                                <td><?php echo esc_html($item->total_votes); ?></td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="4"><?php esc_html_e('No top rated items yet', 'shuriken-reviews'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Most Voted -->
        <div class="shuriken-table-card">
            <h2>
                <?php Shuriken_Icons::render('megaphone', array('width' => 18, 'height' => 18)); ?>
                <?php esc_html_e('Most Popular', 'shuriken-reviews'); ?>
            </h2>
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
                                    <a href="<?php echo esc_url($stats_url); ?>" class="rating-item-link">
                                        <?php echo esc_html($item->name); ?>
                                    </a>
                                </td>
                                <td><strong><?php echo esc_html($item->total_votes); ?></strong></td>
                                <td><?php echo esc_html($analytics->format_average_display($item->average ?: 0, $item->rating_type ?? 'stars', $item->scale ?? 5, $item->total_votes, $item->total_rating)); ?></td>
                            </tr>
                        <?php $rank++; endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="4"><?php esc_html_e('No data available yet', 'shuriken-reviews'); ?></td></tr>
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
                <?php Shuriken_Icons::render('triangle-alert', array('width' => 18, 'height' => 18)); ?>
                <?php esc_html_e('Low Performers', 'shuriken-reviews'); ?>
            </h2>
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
                            $stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $item->id);
                        ?>
                            <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($stats_url); ?>">
                                <td><a href="<?php echo esc_url($stats_url); ?>" class="rating-item-link"><?php echo esc_html($item->name); ?></a></td>
                                <td><?php
                                    $lp_type = Shuriken_Rating_Type::tryFrom($item->rating_type ?? 'stars') ?? Shuriken_Rating_Type::Stars;
                                    if ($lp_type === Shuriken_Rating_Type::Numeric) {
                                        echo '<span class="star-display low">' . Shuriken_Icons::render('hash', array('width' => 14, 'height' => 14), false) . '</span> ';
                                    } elseif (!$lp_type->isBinary()) {
                                        echo '<span class="star-display low">' . Shuriken_Icons::render('star', array('width' => 14, 'height' => 14), false) . '</span> ';
                                    }
                                    echo esc_html($analytics->format_average_display($item->average, $item->rating_type ?? 'stars', $item->scale ?? 5, $item->total_votes, $item->total_rating));
                                ?></td>
                                <td><?php echo esc_html($item->total_votes); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="3"><?php esc_html_e('No low performers', 'shuriken-reviews'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Recent Activity -->
        <div class="shuriken-table-card">
            <h2>
                <?php Shuriken_Icons::render('clock', array('width' => 18, 'height' => 18)); ?>
                <?php esc_html_e('Recent Activity', 'shuriken-reviews'); ?>
            </h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Item', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Rating', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Context', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Voter', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Date', 'shuriken-reviews'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_votes) : ?>
                        <?php foreach ($recent_votes as $vote) : 
                            $stats_url = admin_url('admin.php?page=shuriken-reviews-item-stats&rating_id=' . $vote->rating_id);
                        ?>
                            <tr class="shuriken-clickable-row" data-href="<?php echo esc_url($stats_url); ?>">
                                <td><a href="<?php echo esc_url($stats_url); ?>" class="rating-item-link"><?php echo esc_html($vote->rating_name); ?></a></td>
                                <td><span class="star-rating-display"><?php echo $analytics->format_vote_display($vote->rating_value, $vote->rating_type ?? 'stars', $vote->scale ?? 5); ?></span></td>
                                <td>
                                    <?php if (!empty($vote->context_id)) :
                                        $ctx_title = get_the_title($vote->context_id);
                                        $ctx_edit  = get_edit_post_link($vote->context_id);
                                    ?>
                                        <?php if ($ctx_edit) : ?>
                                            <a href="<?php echo esc_url($ctx_edit); ?>" class="context-link" title="<?php echo esc_attr($vote->context_type); ?>">
                                                <?php Shuriken_Icons::render('map-pin', array('width' => 14, 'height' => 14)); ?> <?php echo esc_html($ctx_title ?: '#' . $vote->context_id); ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="context-label"><?php Shuriken_Icons::render('map-pin', array('width' => 14, 'height' => 14)); ?> <?php echo esc_html($ctx_title ?: '#' . $vote->context_id); ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="context-global"><?php esc_html_e('Global', 'shuriken-reviews'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $voter_url = $vote->user_id > 0 
                                        ? admin_url('admin.php?page=shuriken-reviews-voter-activity&user_id=' . $vote->user_id)
                                        : ($vote->user_ip ? admin_url('admin.php?page=shuriken-reviews-voter-activity&user_ip=' . urlencode($vote->user_ip)) : '');
                                    ?>
                                    <?php if ($vote->user_id > 0) : ?>
                                        <a href="<?php echo esc_url($voter_url); ?>" class="voter-link"><?php echo $vote->display_name ? esc_html($vote->display_name) : __('Deleted User', 'shuriken-reviews'); ?></a>
                                    <?php elseif ($voter_url) : ?>
                                        <a href="<?php echo esc_url($voter_url); ?>" class="voter-link"><em><?php esc_html_e('Guest', 'shuriken-reviews'); ?></em></a>
                                    <?php else : ?>
                                        <em><?php esc_html_e('Guest', 'shuriken-reviews'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($analytics->format_time_ago($vote->date_created)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="5"><?php esc_html_e('No recent activity', 'shuriken-reviews'); ?></td></tr>
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
                <?php Shuriken_Icons::render('download', array('width' => 18, 'height' => 18)); ?>
                <?php esc_html_e('Export to CSV', 'shuriken-reviews'); ?>
            </button>
        </form>
    </div>
</div>

<script>
var shurikenAnalyticsData = {
    votesOverTimeByType: <?php echo wp_json_encode($votes_by_type); ?>,
    heatmap: <?php echo wp_json_encode($heatmap_data); ?>,
    i18n: {
        votes: <?php echo wp_json_encode(__('Votes', 'shuriken-reviews')); ?>,
        stars: <?php echo wp_json_encode(__('Stars', 'shuriken-reviews')); ?>,
        like_dislike: <?php echo wp_json_encode(__('Like/Dislike', 'shuriken-reviews')); ?>,
        numeric: <?php echo wp_json_encode(__('Numeric', 'shuriken-reviews')); ?>,
        approval: <?php echo wp_json_encode(__('Approval', 'shuriken-reviews')); ?>,
        sun: <?php echo wp_json_encode(__('Sun', 'shuriken-reviews')); ?>,
        mon: <?php echo wp_json_encode(__('Mon', 'shuriken-reviews')); ?>,
        tue: <?php echo wp_json_encode(__('Tue', 'shuriken-reviews')); ?>,
        wed: <?php echo wp_json_encode(__('Wed', 'shuriken-reviews')); ?>,
        thu: <?php echo wp_json_encode(__('Thu', 'shuriken-reviews')); ?>,
        fri: <?php echo wp_json_encode(__('Fri', 'shuriken-reviews')); ?>,
        sat: <?php echo wp_json_encode(__('Sat', 'shuriken-reviews')); ?>
    }
};
</script>
