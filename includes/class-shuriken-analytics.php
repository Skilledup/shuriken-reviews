<?php
/**
 * Shuriken Reviews Analytics Class
 *
 * Provides reusable methods for fetching rating statistics and analytics data.
 * Can be used in admin pages, shortcodes, REST API, or widgets.
 * Uses Shuriken_Database for core database operations.
 *
 * @package Shuriken_Reviews
 * @since 1.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Analytics
 *
 * Handles all analytics and statistics data retrieval for ratings.
 *
 * @since 1.3.0
 */
class Shuriken_Analytics implements Shuriken_Analytics_Interface {

    use Shuriken_Analytics_Helpers;

    /**
     * @var \wpdb WordPress database instance
     */
    private \wpdb $wpdb;

    /**
     * @var string Ratings table name
     */
    private string $ratings_table;

    /**
     * @var string Votes table name
     */
    private string $votes_table;

    /**
     * @var Shuriken_Database_Interface Database instance
     */
    private Shuriken_Database_Interface $db;

    /**
     * Constructor
     *
     * @param Shuriken_Database_Interface|null $db Optional database instance (for dependency injection).
     */
    public function __construct(?Shuriken_Database_Interface $db = null) {
        // Use provided database or get from container
        $this->db = $db ?: shuriken_db();
        $this->wpdb = $this->db->get_wpdb();
        $this->ratings_table = $this->db->get_ratings_table();
        $this->votes_table = $this->db->get_votes_table();
    }

    /**
     * Check if a rating type uses binary values (0/1) instead of a 1-N scale
     *
     * @param string $rating_type Rating type identifier
     * @return bool
     */
    private function is_binary_type(string $rating_type): bool {
        return in_array($rating_type, array('like_dislike', 'approval'), true);
    }

    /**
     * Get the inversion constant for a rating's scale
     *
     * Stars/numeric: value range [1, scale], inversion = (scale + 1) - value
     * Binary (like_dislike, approval): value range [0, 1], inversion = 1 - value
     *
     * @param string $rating_type Rating type
     * @param int $scale Rating scale
     * @return int The constant C where inverted_value = C - original_value
     */
    private function get_inversion_constant(string $rating_type, int $scale): int {
        // Votes are normalized to 1-5 (RATING_SCALE_DEFAULT), so inversion uses the internal scale
        return $this->is_binary_type($rating_type) ? (int) $scale : (Shuriken_Database::RATING_SCALE_DEFAULT + 1);
    }

    /**
     * Build an empty distribution array for a rating type and scale
     *
     * Stars/numeric: keys 1 through scale (e.g., {1:0, 2:0, 3:0, 4:0, 5:0})
     * Binary: keys 0 and 1 (e.g., {0:0, 1:0})
     *
     * @param string $rating_type Rating type
     * @param int $scale Rating scale
     * @return array Empty distribution array
     */
    private function build_empty_distribution(string $rating_type, int $scale): array {
        if ($this->is_binary_type($rating_type)) {
            return array(0 => 0, 1 => 0);
        }
        // Always use the internal normalized scale (1-5) for distribution buckets
        // since vote values are stored normalized regardless of the display scale.
        return array_fill(1, Shuriken_Database::RATING_SCALE_DEFAULT, 0);
    }

    /**
     * Parse date range from request parameters
     * 
     * Converts GET/POST parameters into a date range value that can be passed to build_date_condition.
     * Handles both preset ranges (7, 30, 90, 365, all) and custom date ranges.
     *
     * @param array $params Request parameters (typically $_GET)
     * @return string|array Date range value ('all', number of days, or array with start/end)
     */
    public function parse_date_range_params(array $params): string|array {
        $range_type = isset($params['range_type']) ? sanitize_text_field($params['range_type']) : 'preset';
        
        if ($range_type === 'custom') {
            $start_date = isset($params['start_date']) ? sanitize_text_field($params['start_date']) : '';
            $end_date = isset($params['end_date']) ? sanitize_text_field($params['end_date']) : '';
            
            // Validate dates
            $valid_start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date);
            $valid_end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date);
            
            if ($valid_start || $valid_end) {
                return array(
                    'start' => $valid_start ? $start_date : '',
                    'end' => $valid_end ? $end_date : ''
                );
            }
        }
        
        // Preset range
        $date_range = isset($params['date_range']) ? sanitize_text_field($params['date_range']) : '30';
        $valid_ranges = array('7', '30', '90', '365', 'all');
        
        if (!in_array($date_range, $valid_ranges, true)) {
            $date_range = '30';
        }
        
        return $date_range;
    }

    /**
     * Get human-readable label for the current date range
     *
     * @param string|array $date_range Date range value
     * @return string Human-readable label
     */
    public function get_date_range_label(string|int|array $date_range): string {
        if (is_array($date_range)) {
            $start = !empty($date_range['start']) ? date_i18n(get_option('date_format'), strtotime($date_range['start'])) : '';
            $end = !empty($date_range['end']) ? date_i18n(get_option('date_format'), strtotime($date_range['end'])) : '';
            
            if ($start && $end) {
                return sprintf(__('%s to %s', 'shuriken-reviews'), $start, $end);
            } elseif ($start) {
                return sprintf(__('From %s', 'shuriken-reviews'), $start);
            } elseif ($end) {
                return sprintf(__('Until %s', 'shuriken-reviews'), $end);
            }
            return __('All Time', 'shuriken-reviews');
        }
        
        switch ($date_range) {
            case '7':
                return __('Last 7 Days', 'shuriken-reviews');
            case '30':
                return __('Last 30 Days', 'shuriken-reviews');
            case '90':
                return __('Last 90 Days', 'shuriken-reviews');
            case '365':
                return __('Last Year', 'shuriken-reviews');
            case 'all':
            default:
                return __('All Time', 'shuriken-reviews');
        }
    }

    /**
     * Format an average rating value for display, adapting to rating type
     *
     * Stars/numeric: "3.5/5"
     * Like/dislike: "72% positive"
     * Approval: "85% approved"
     *
     * @param float $average The average value (for binary: like ratio 0-1 from total_rating/total_votes)
     * @param string $rating_type Rating type
     * @param int $scale Rating scale
     * @param int $total_votes Total votes (needed for binary percentage context)
     * @param int $total_rating Total rating sum (needed for binary types)
     * @return string Formatted display string
     */
    public function format_average_display(float $average, string $rating_type = 'stars', int $scale = Shuriken_Database::RATING_SCALE_DEFAULT, int $total_votes = 0, int $total_rating = 0): string {
        if ($rating_type === 'like_dislike') {
            $pct = $total_votes > 0 ? round(($total_rating / $total_votes) * 100) : 0;
            return $pct . '% ' . __('positive', 'shuriken-reviews');
        }
        if ($rating_type === 'approval') {
            $pct = $total_votes > 0 ? round(($total_rating / $total_votes) * 100) : 0;
            return $pct . '% ' . __('approved', 'shuriken-reviews');
        }
        $display_avg = Shuriken_Database::denormalize_average((float) $average, $scale);
        return number_format($display_avg, 1) . '/' . intval($scale);
    }

    /**
     * Render a vote value for display in tables, adapting to rating type
     *
     * Stars: filled/empty star SVG icons
     * Numeric: X/N format
     * Like/dislike: thumbs-up or thumbs-down SVG icon
     * Approval: thumbs-up SVG icon
     *
     * @param int $rating_value The vote value
     * @param string $rating_type Rating type
     * @param int $scale Rating scale
     * @return string HTML display string
     */
    public function format_vote_display(float|int $rating_value, string $rating_type = 'stars', int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): string {
        $symbols = Shuriken_Icons::rating_symbols(14);
        if ($rating_type === 'like_dislike') {
            return intval($rating_value) === 1 ? $symbols['thumbs_up'] : $symbols['thumbs_down'];
        }
        if ($rating_type === 'approval') {
            return $symbols['thumbs_up'];
        }
        $s = (int) $scale;
        $display_value = (int) round(((float) $rating_value / Shuriken_Database::RATING_SCALE_DEFAULT) * $s);
        if ($rating_type === 'numeric') {
            return $display_value . '/' . $s;
        }
        $out = '';
        for ($i = 1; $i <= $s; $i++) {
            if ($i <= $display_value) {
                $out .= '<span class="svg-star filled">' . $symbols['star_filled'] . '</span>';
            } else {
                $out .= '<span class="svg-star empty">' . $symbols['star_empty'] . '</span>';
            }
        }
        return $out;
    }

    /**
     * Get overall statistics
     *
     * @return object Object containing total_ratings, total_votes, overall_average, unique_voters
     */
    public function get_overall_stats(): object {
        $stats = new stdClass();
        
        // Count all ratings
        $stats->total_ratings = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table}"
        );
        
        // Get rating type breakdown
        $stats->rating_types = $this->get_rating_type_counts();
        
        // Total votes (exclude mirrors as they don't have their own votes)
        $stats->total_votes = (int) $this->wpdb->get_var(
            "SELECT COALESCE(SUM(total_votes), 0) FROM {$this->ratings_table} WHERE mirror_of IS NULL"
        );
        
        // Overall average (only from non-mirror ratings with votes)
        $stats->overall_average = (float) $this->wpdb->get_var(
            "SELECT AVG(total_rating / NULLIF(total_votes, 0)) 
             FROM {$this->ratings_table} 
             WHERE total_votes > 0 AND mirror_of IS NULL"
        );
        
        $stats->unique_voters = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table}"
        );
        
        return $stats;
    }

    /**
     * Count distinct posts/entities that have at least one contextual vote
     *
     * @return int Number of unique contexts with votes.
     * @since 1.15.0
     */
    public function get_contextual_post_count(): int {
        return (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT CONCAT(context_id, ':', context_type))
             FROM {$this->votes_table}
             WHERE context_id IS NOT NULL"
        );
    }

    /**
     * Get rating type counts breakdown
     *
     * @return object Object with standalone, parent, sub, display_only, mirror counts
     */
    public function get_rating_type_counts(): object {
        $counts = new stdClass();
        
        // Standalone: no parent_id, no mirror_of, and not a parent itself
        $counts->standalone = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} r
             WHERE r.parent_id IS NULL 
               AND r.mirror_of IS NULL 
               AND NOT EXISTS (SELECT 1 FROM {$this->ratings_table} sub WHERE sub.parent_id = r.id)"
        );
        
        // Parent ratings: has sub-ratings
        $counts->parent = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT parent_id) FROM {$this->ratings_table} WHERE parent_id IS NOT NULL"
        );
        
        // Sub-ratings
        $counts->sub = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE parent_id IS NOT NULL"
        );
        
        // Sub-ratings with positive effect
        $counts->sub_positive = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE parent_id IS NOT NULL AND effect_type = 'positive'"
        );
        
        // Sub-ratings with negative effect
        $counts->sub_negative = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE parent_id IS NOT NULL AND effect_type = 'negative'"
        );
        
        // Display-only ratings
        $counts->display_only = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE display_only = 1"
        );
        
        // Mirror ratings
        $counts->mirror = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE mirror_of IS NOT NULL"
        );
        
        return $counts;
    }

    /**
     * Get vote counts for a specific period
     *
     * @param string|int $date_range Number of days or 'all'
     * @return object Object containing period_votes, member_votes, guest_votes
     */
    public function get_vote_counts(string|int|array $date_range = 'all'): object {
        $date_condition = $this->build_date_condition($date_range);
        
        $counts = new stdClass();
        
        $counts->period_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE 1=1 {$date_condition}"
        );
        
        $counts->member_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE user_id > 0 {$date_condition}"
        );
        
        $counts->guest_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE user_id = 0 {$date_condition}"
        );
        
        return $counts;
    }

    /**
     * Calculate vote change percentage compared to previous period
     * 
     * For preset ranges (7, 30, 90, 365 days): compares to previous same-length period
     * For custom date ranges: compares to same-length period immediately before the start date
     *
     * @param string|int|array $date_range Date range (number of days or array with start/end)
     * @return float|null Percentage change or null if not applicable
     */
    public function get_vote_change_percent(string|int|array $date_range): ?float {
        // For 'all' or empty, no comparison possible
        if ($date_range === 'all' || empty($date_range)) {
            return null;
        }
        
        // Custom date range
        if (is_array($date_range)) {
            $start = !empty($date_range['start']) ? $date_range['start'] : null;
            $end = !empty($date_range['end']) ? $date_range['end'] : date('Y-m-d');
            
            if (!$start) {
                return null; // Can't calculate without a start date
            }
            
            // Calculate period length in days
            $start_time = strtotime($start);
            $end_time = strtotime($end);
            $period_days = max(1, ($end_time - $start_time) / 86400);
            
            // Previous period: same length, ending just before current start
            $prev_end = date('Y-m-d', $start_time - 86400);
            $prev_start = date('Y-m-d', $start_time - ($period_days * 86400));
            
            $current_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table} 
                 WHERE date_created >= %s AND date_created <= %s",
                $start . ' 00:00:00',
                $end . ' 23:59:59'
            ));
            
            $previous_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table} 
                 WHERE date_created >= %s AND date_created <= %s",
                $prev_start . ' 00:00:00',
                $prev_end . ' 23:59:59'
            ));
            
            if ($previous_votes > 0) {
                return round((($current_votes - $previous_votes) / $previous_votes) * 100, 1);
            }
            
            return $current_votes > 0 ? 100 : 0;
        }
        
        // Preset relative days
        $days = intval($date_range);
        
        $current_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        $previous_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE date_created >= DATE_SUB(NOW(), INTERVAL %d DAY) 
               AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days * 2,
            $days
        ));
        
        if ($previous_votes > 0) {
            return round((($current_votes - $previous_votes) / $previous_votes) * 100, 1);
        }
        
        return $current_votes > 0 ? 100 : 0;
    }

    /**
     * Get vote change percentage for a specific rating item compared to previous period
     *
     * @param int $rating_id Rating ID
     * @param string|int|array $date_range Date range
     * @return float|null Percentage change or null if not applicable
     */
    public function get_rating_vote_change_percent(int $rating_id, string|int|array $date_range): ?float {
        if ($date_range === 'all' || empty($date_range)) {
            return null;
        }

        if (is_array($date_range)) {
            $start = !empty($date_range['start']) ? $date_range['start'] : null;
            $end = !empty($date_range['end']) ? $date_range['end'] : date('Y-m-d');
            if (!$start) {
                return null;
            }
            $start_time = strtotime($start);
            $end_time = strtotime($end);
            $period_days = max(1, ($end_time - $start_time) / 86400);
            $prev_end = date('Y-m-d', $start_time - 86400);
            $prev_start = date('Y-m-d', $start_time - ($period_days * 86400));

            $current_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table}
                 WHERE rating_id = %d AND date_created >= %s AND date_created <= %s",
                $rating_id, $start . ' 00:00:00', $end . ' 23:59:59'
            ));
            $previous_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table}
                 WHERE rating_id = %d AND date_created >= %s AND date_created <= %s",
                $rating_id, $prev_start . ' 00:00:00', $prev_end . ' 23:59:59'
            ));
        } else {
            $days = intval($date_range);
            $current_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table}
                 WHERE rating_id = %d AND date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $rating_id, $days
            ));
            $previous_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->votes_table}
                 WHERE rating_id = %d AND date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
                   AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $rating_id, $days * 2, $days
            ));
        }

        if ($previous_votes > 0) {
            return round((($current_votes - $previous_votes) / $previous_votes) * 100, 1);
        }
        return $current_votes > 0 ? 100.0 : null;
    }

    /**
     * Get benchmark stats for all items of a given rating type
     *
     * Returns the average metric across all items of the same type, useful for
     * comparing a single item's performance against the site-wide average.
     *
     * @param string $rating_type Rating type (stars, numeric, like_dislike, approval)
     * @param string|int|array $date_range Date range filter
     * @return object Object with avg_rating, avg_votes, item_count
     */
    public function get_type_benchmark(string $rating_type, string|int|array $date_range = 'all'): object {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        $is_binary = in_array($rating_type, array('like_dislike', 'approval'), true);

        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT 
                COUNT(*) as item_count,
                AVG(sub.avg_value) as avg_rating,
                AVG(sub.vote_count) as avg_votes,
                AVG(sub.approval_rate) as avg_approval_rate
             FROM (
                SELECT r.id,
                       AVG(v.rating_value) as avg_value,
                       COUNT(v.id) as vote_count,
                       CASE WHEN %s IN ('like_dislike', 'approval')
                            THEN ROUND(SUM(CASE WHEN v.rating_value > 0 THEN 1 ELSE 0 END) / COUNT(v.id) * 100)
                            ELSE NULL END as approval_rate
                FROM {$this->ratings_table} r
                JOIN {$this->votes_table} v ON v.rating_id = r.id
                WHERE COALESCE(r.rating_type, 'stars') = %s
                  AND r.mirror_of IS NULL
                  AND r.parent_id IS NULL
                  {$date_condition}
                GROUP BY r.id
             ) sub",
            $rating_type,
            $rating_type
        ));

        if (!$result || !$result->item_count) {
            $default = new stdClass();
            $default->avg_rating = null;
            $default->avg_votes = null;
            $default->avg_approval_rate = null;
            $default->item_count = 0;
            return $default;
        }

        $result->item_count = (int) $result->item_count;
        $result->avg_rating = $result->avg_rating !== null ? round((float) $result->avg_rating, 2) : null;
        $result->avg_votes = $result->avg_votes !== null ? round((float) $result->avg_votes, 1) : null;
        $result->avg_approval_rate = $result->avg_approval_rate !== null ? round((float) $result->avg_approval_rate) : null;

        return $result;
    }

    /**
     * Get top rated items (standalone and parent ratings only)
     *
     * @param int $limit Maximum number of items to return
     * @param int $min_votes Minimum votes required
     * @param float $min_average Minimum average rating (default 3.0 for "top" items)
     * @param string|int|array $date_range Date range filter
     * @return array Array of rating objects
     */
    public function get_top_rated(int $limit = 10, int $min_votes = 1, float $min_average = 3.0, string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        // If no date filter, use the cached totals from ratings table (faster)
        if (empty($date_condition)) {
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, name, rating_type, scale, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of,
                        ROUND(total_rating / NULLIF(total_votes, 0), 4) as average 
                 FROM {$this->ratings_table} 
                 WHERE total_votes >= %d 
                   AND (total_rating / NULLIF(total_votes, 0)) >= %f
                   AND mirror_of IS NULL
                   AND parent_id IS NULL
                 ORDER BY average DESC, total_votes DESC 
                 LIMIT %d",
                $min_votes,
                $min_average,
                $limit
            ));
        }
        
        // With date filter, calculate from votes table
        // For parent ratings, include votes from sub-ratings with effect_type conversion
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.id, r.name, r.rating_type, r.scale, r.parent_id, r.effect_type, r.display_only, r.mirror_of,
                    COUNT(v.id) as total_votes,
                    COALESCE(SUM(
                        CASE 
                            WHEN sub.effect_type = 'negative' AND sub.rating_type IN ('like_dislike', 'approval')
                                THEN CAST(sub.scale AS SIGNED) - v.rating_value
                            WHEN sub.effect_type = 'negative'
                                THEN (CAST(sub.scale AS SIGNED) + 1) - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 0) as total_rating,
                    ROUND(AVG(
                        CASE 
                            WHEN sub.effect_type = 'negative' AND sub.rating_type IN ('like_dislike', 'approval')
                                THEN CAST(sub.scale AS SIGNED) - v.rating_value
                            WHEN sub.effect_type = 'negative'
                                THEN (CAST(sub.scale AS SIGNED) + 1) - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 4) as average
             FROM {$this->ratings_table} r
             LEFT JOIN {$this->ratings_table} sub ON sub.parent_id = r.id
             LEFT JOIN {$this->votes_table} v ON (v.rating_id = r.id OR v.rating_id = sub.id) {$date_condition}
             WHERE r.mirror_of IS NULL
               AND r.parent_id IS NULL
             GROUP BY r.id
             HAVING total_votes >= %d AND average >= %f
             ORDER BY average DESC, total_votes DESC 
             LIMIT %d",
            $min_votes,
            $min_average,
            $limit
        ));
    }

    /**
     * Get most voted (popular) items (standalone and parent ratings only)
     *
     * @param int $limit Maximum number of items to return
     * @param string|int|array $date_range Date range filter
     * @return array Array of rating objects
     */
    public function get_most_voted(int $limit = 10, string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        // If no date filter, use the cached totals from ratings table (faster)
        if (empty($date_condition)) {
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, name, rating_type, scale, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of,
                        ROUND(total_rating / NULLIF(total_votes, 0), 4) as average
                 FROM {$this->ratings_table} 
                 WHERE mirror_of IS NULL
                   AND parent_id IS NULL
                 ORDER BY total_votes DESC 
                 LIMIT %d",
                $limit
            ));
        }
        
        // With date filter, calculate from votes table
        // For parent ratings, include votes from sub-ratings with effect_type conversion
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.id, r.name, r.rating_type, r.scale, r.parent_id, r.effect_type, r.display_only, r.mirror_of,
                    COUNT(v.id) as total_votes,
                    COALESCE(SUM(
                        CASE 
                            WHEN sub.effect_type = 'negative' AND sub.rating_type IN ('like_dislike', 'approval')
                                THEN CAST(sub.scale AS SIGNED) - v.rating_value
                            WHEN sub.effect_type = 'negative'
                                THEN (CAST(sub.scale AS SIGNED) + 1) - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 0) as total_rating,
                    ROUND(AVG(
                        CASE 
                            WHEN sub.effect_type = 'negative' AND sub.rating_type IN ('like_dislike', 'approval')
                                THEN CAST(sub.scale AS SIGNED) - v.rating_value
                            WHEN sub.effect_type = 'negative'
                                THEN (CAST(sub.scale AS SIGNED) + 1) - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 4) as average
             FROM {$this->ratings_table} r
             LEFT JOIN {$this->ratings_table} sub ON sub.parent_id = r.id
             LEFT JOIN {$this->votes_table} v ON (v.rating_id = r.id OR v.rating_id = sub.id) {$date_condition}
             WHERE r.mirror_of IS NULL
               AND r.parent_id IS NULL
             GROUP BY r.id
             HAVING total_votes > 0
             ORDER BY total_votes DESC 
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get low performing items (standalone and parent ratings only, average < threshold)
     *
     * @param int $limit Maximum number of items to return
     * @param int $min_votes Minimum votes required
     * @param float $max_average Maximum average rating (default 3.0)
     * @param string|int|array $date_range Date range filter
     * @return array Array of rating objects
     */
    public function get_low_performers(int $limit = 10, int $min_votes = 1, float $max_average = 3.0, string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        // If no date filter, use the cached totals from ratings table (faster)
        if (empty($date_condition)) {
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, name, rating_type, scale, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of,
                        ROUND(total_rating / NULLIF(total_votes, 0), 4) as average 
                 FROM {$this->ratings_table} 
                 WHERE total_votes >= %d 
                   AND (total_rating / NULLIF(total_votes, 0)) < %f
                   AND mirror_of IS NULL
                   AND parent_id IS NULL
                 ORDER BY average ASC, total_votes DESC 
                 LIMIT %d",
                $min_votes,
                $max_average,
                $limit
            ));
        }
        
        // With date filter, calculate from votes table
        // For parent ratings, include votes from sub-ratings with effect_type conversion
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.id, r.name, r.rating_type, r.scale, r.parent_id, r.effect_type, r.display_only, r.mirror_of,
                    COUNT(v.id) as total_votes,
                    COALESCE(SUM(
                        CASE 
                            WHEN sub.effect_type = 'negative' AND sub.rating_type IN ('like_dislike', 'approval')
                                THEN CAST(sub.scale AS SIGNED) - v.rating_value
                            WHEN sub.effect_type = 'negative'
                                THEN (CAST(sub.scale AS SIGNED) + 1) - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 0) as total_rating,
                    ROUND(AVG(
                        CASE 
                            WHEN sub.effect_type = 'negative' AND sub.rating_type IN ('like_dislike', 'approval')
                                THEN CAST(sub.scale AS SIGNED) - v.rating_value
                            WHEN sub.effect_type = 'negative'
                                THEN (CAST(sub.scale AS SIGNED) + 1) - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 4) as average
             FROM {$this->ratings_table} r
             LEFT JOIN {$this->ratings_table} sub ON sub.parent_id = r.id
             LEFT JOIN {$this->votes_table} v ON (v.rating_id = r.id OR v.rating_id = sub.id) {$date_condition}
             WHERE r.mirror_of IS NULL
               AND r.parent_id IS NULL
             GROUP BY r.id
             HAVING total_votes >= %d AND average < %f
             ORDER BY average ASC, total_votes DESC 
             LIMIT %d",
            $min_votes,
            $max_average,
            $limit
        ));
    }

    /**
     * Get sub-ratings for a specific parent with contribution analysis
     *
     * @param int $parent_id Parent rating ID
     * @return array Array of sub-rating objects with contribution data
     */
    public function get_sub_ratings_contribution(int $parent_id): array {
        $parent = $this->db->get_rating($parent_id);
        if (!$parent || $parent->total_votes == 0) {
            return array();
        }
        
        $subs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, name, total_votes, total_rating, effect_type, rating_type, scale,
                    ROUND(total_rating / NULLIF(total_votes, 0), 4) as average,
                    ROUND((total_votes / %d) * 100, 1) as vote_contribution_percent
             FROM {$this->ratings_table}
             WHERE parent_id = %d
             ORDER BY total_votes DESC",
            $parent->total_votes,
            $parent_id
        ));
        
        // Calculate effective contribution to parent score
        foreach ($subs as &$sub) {
            if ($sub->total_votes > 0) {
                $sub_average = $sub->total_rating / $sub->total_votes;
                if ($sub->effect_type === 'negative') {
                    $inv = $this->get_inversion_constant($sub->rating_type, $sub->scale);
                    $sub->effective_average = $inv - $sub_average;
                } else {
                    $sub->effective_average = $sub_average;
                }
                $sub->effective_average = round($sub->effective_average, 1);
            } else {
                $sub->effective_average = 0;
            }
        }
        
        return $subs;
    }

    /**
     * Get rating distribution (count of each star value)
     * 
     * For sub-ratings with negative effect_type, the rating values are inverted
     * to show the effective contribution (1★→5★, 2★→4★, 3★→3★, 4★→2★, 5★→1★).
     *
     * @param string|int $date_range Number of days or 'all'
     * @param int|null $rating_id Optional specific rating ID
     * @return array Associative array with vote value keys and counts
     */
    public function get_rating_distribution(string|int|array $date_range = 'all', ?int $rating_id = null): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        $rating_condition = $rating_id ? $this->wpdb->prepare(" AND v.rating_id = %d", $rating_id) : '';

        // Determine the target rating's type and scale for proper bucketing
        $rating_type = 'stars';
        $scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        if ($rating_id) {
            $rating = $this->db->get_rating($rating_id);
            if ($rating) {
                $rating_type = $rating->rating_type ?: 'stars';
                $scale = (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT);
            }
        }

        // When aggregating globally (no rating_id), exclude binary types from the distribution
        // since their 0/1 values are incompatible with star-scale buckets
        $type_condition = '';
        if (!$rating_id) {
            $type_condition = " AND r.rating_type NOT IN ('like_dislike', 'approval')";
        }

        // Join with ratings table to get effect_type and invert negative effect votes
        // Votes are normalized to 1-5 internally, so inversion uses RATING_SCALE_DEFAULT
        $internal_scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        $results = $this->wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN r.effect_type = 'negative' AND r.rating_type IN ('like_dislike', 'approval') 
                        THEN CAST(r.scale AS SIGNED) - v.rating_value
                    WHEN r.effect_type = 'negative' 
                        THEN ({$internal_scale} + 1) - ROUND(v.rating_value) 
                    ELSE ROUND(v.rating_value) 
                END as effective_value, 
                COUNT(*) as count 
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE 1=1 {$date_condition} {$rating_condition} {$type_condition}
             GROUP BY effective_value 
             ORDER BY effective_value"
        );
        
        // Build distribution with type-appropriate buckets
        $distribution = $this->build_empty_distribution($rating_type, $scale);
        foreach ($results as $row) {
            $key = intval($row->effective_value);
            if (array_key_exists($key, $distribution)) {
                $distribution[$key] = intval($row->count);
            }
        }
        
        return $distribution;
    }

    /**
     * Get votes over time
     *
     * @param string|int|array $date_range Number of days, 'all', or array with start/end
     * @param int|null $rating_id Optional specific rating ID
     * @return array Array of objects with vote_date and vote_count
     */
    public function get_votes_over_time(string|int|array $date_range = 30, ?int $rating_id = null): array {
        $date_condition = $this->build_date_condition($date_range);
        $rating_condition = $rating_id ? $this->wpdb->prepare(" AND rating_id = %d", $rating_id) : '';
        
        return $this->wpdb->get_results(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as vote_count 
             FROM {$this->votes_table} 
             WHERE 1=1 {$date_condition} {$rating_condition}
             GROUP BY DATE(date_created) 
             ORDER BY vote_date"
        );
    }

    /**
     * Get recent votes/activity
     *
     * @param int $limit Maximum number of votes to return
     * @param int|null $rating_id Optional specific rating ID
     * @param string|int|array $date_range Date range filter
     * @return array Array of vote objects with rating info
     */
    public function get_recent_votes(int $limit = 10, ?int $rating_id = null, string|int|array $date_range = 'all'): array {
        $rating_condition = $rating_id ? $this->wpdb->prepare("AND v.rating_id = %d", $rating_id) : '';
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.id, v.rating_id, v.rating_value, v.date_created, v.user_id, v.user_ip,
                    v.context_id, v.context_type,
                    r.name as rating_name, r.rating_type, r.scale, u.display_name, u.user_email
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             WHERE 1=1 {$rating_condition} {$date_condition}
             ORDER BY v.date_created DESC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get single rating item with stats
     *
     * @param int $rating_id Rating ID
     * @return object|null Rating object with calculated stats or null
     */
    public function get_rating(int $rating_id): ?object {
        return $this->db->get_rating($rating_id);
    }

    /**
     * Get detailed stats for a single rating item
     *
     * @param int $rating_id Rating ID
     * @param string|int|array $date_range Date range filter
     * @return object|null Object with comprehensive stats or null
     */
    public function get_rating_stats(int $rating_id, string|int|array $date_range = 'all'): ?object {
        $rating = $this->get_rating($rating_id);
        if (!$rating) {
            return null;
        }
        
        $date_condition = $this->build_date_condition($date_range);
        
        $stats = new stdClass();
        $stats->rating = $rating;
        
        // If filtering by date, recalculate average from filtered votes
        if (!empty($date_condition)) {
            $filtered_totals = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
                 FROM {$this->votes_table} WHERE rating_id = %d {$date_condition}",
                $rating_id
            ));
            $stats->total_votes = (int) $filtered_totals->total_votes;
            $stats->total_rating = (int) $filtered_totals->total_rating;
            $stats->average = $stats->total_votes > 0 ? round($stats->total_rating / $stats->total_votes, 1) : 0;
        } else {
            $stats->total_votes = $rating->total_votes;
            $stats->total_rating = $rating->total_rating;
            $stats->average = $rating->average;
        }
        
        $scale = isset($rating->scale) ? (int) $rating->scale : Shuriken_Database::RATING_SCALE_DEFAULT;
        $stats->display_average = Shuriken_Database::denormalize_average((float) $stats->average, $scale);
        
        // Vote counts by user type
        $stats->member_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id > 0 {$date_condition}",
            $rating_id
        ));
        
        $stats->guest_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id = 0 {$date_condition}",
            $rating_id
        ));
        
        // Unique voters
        $stats->unique_voters = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id = %d {$date_condition}",
            $rating_id
        ));
        
        // First and last vote (within date range)
        $stats->first_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MIN(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition}",
            $rating_id
        ));
        
        $stats->last_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition}",
            $rating_id
        ));
        
        // Distribution and timeline (use same date range)
        $stats->distribution = $this->get_rating_distribution($date_range, $rating_id);
        $stats->votes_over_time = $this->get_votes_over_time($date_range, $rating_id);
        
        return $stats;
    }

    /**
     * Get detailed stats breakdown for a parent rating
     * 
     * Returns stats from three sources:
     * - direct: Votes directly on the parent rating
     * - subs: Aggregated stats from sub-ratings
     * - total: Combined total
     *
     * @param int $rating_id Parent rating ID
     * @return object|null Object with direct, subs, and total stats or null
     */
    public function get_parent_rating_stats_breakdown(int $rating_id, string|int|array $date_range = 'all', ?string $scope = null): ?object {
        $rating = $this->get_rating($rating_id);
        if (!$rating) {
            return null;
        }
        
        // Check if this is actually a parent rating
        $sub_rating_ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT id FROM {$this->ratings_table} WHERE parent_id = %d",
            $rating_id
        ));
        
        if (empty($sub_rating_ids)) {
            return null; // Not a parent rating
        }
        
        // Build date and scope conditions
        $date_condition = $this->build_date_condition($date_range, 'date_created');
        $date_condition_v = $this->build_date_condition($date_range, 'v.date_created');
        $scope_condition = $this->build_scope_condition($scope);
        $scope_condition_v = $this->build_scope_condition($scope, 'v.');
        
        $breakdown = new stdClass();
        
        // === DIRECT VOTES (on parent rating itself) ===
        $breakdown->direct = new stdClass();
        
        $direct_totals = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
             FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $breakdown->direct->total_votes = (int) $direct_totals->total_votes;
        $breakdown->direct->total_rating = (int) $direct_totals->total_rating;
        $breakdown->direct->average = $breakdown->direct->total_votes > 0 
            ? round($breakdown->direct->total_rating / $breakdown->direct->total_votes, 1) 
            : 0;
        $scale = isset($rating->scale) ? (int) $rating->scale : Shuriken_Database::RATING_SCALE_DEFAULT;
        $breakdown->direct->display_average = Shuriken_Database::denormalize_average((float) $breakdown->direct->average, $scale);
        
        $breakdown->direct->member_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id > 0 {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $breakdown->direct->guest_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id = 0 {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $breakdown->direct->unique_voters = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $breakdown->direct->first_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MIN(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $breakdown->direct->last_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        
        $breakdown->direct->distribution = $this->get_rating_distribution_scoped($date_range, $rating_id, $scope);
        $breakdown->direct->votes_over_time = $scope ? $this->get_votes_over_time_scoped($date_range, $rating_id, $scope) : $this->get_votes_over_time($date_range, $rating_id);
        
        // === SUB-RATINGS AGGREGATED ===
        $breakdown->subs = new stdClass();
        
        $sub_ids_placeholder = implode(',', array_map('intval', $sub_rating_ids));
        
        // Get sub-ratings with their effect types and type info
        $sub_ratings_info = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, effect_type, rating_type, scale FROM {$this->ratings_table} WHERE parent_id = %d",
            $rating_id
        ));
        
        // Calculate effective totals from sub-ratings with date+scope filter (applying effect_type inversion)
        $subs_total_votes = 0;
        $subs_total_rating = 0;
        foreach ($sub_ratings_info as $sub) {
            $sub_totals = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
                 FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
                $sub->id
            ));
            
            if ($sub_totals->total_votes > 0) {
                $subs_total_votes += (int) $sub_totals->total_votes;
                if ($sub->effect_type === 'negative') {
                    $inv = $this->get_inversion_constant($sub->rating_type, $sub->scale);
                    $inverted_rating = ($sub_totals->total_votes * $inv) - $sub_totals->total_rating;
                    $subs_total_rating += $inverted_rating;
                } else {
                    $subs_total_rating += (int) $sub_totals->total_rating;
                }
            }
        }
        
        $breakdown->subs->total_votes = $subs_total_votes;
        $breakdown->subs->total_rating = $subs_total_rating;
        $breakdown->subs->average = $subs_total_votes > 0 
            ? round($subs_total_rating / $subs_total_votes, 1) 
            : 0;
        $breakdown->subs->display_average = Shuriken_Database::denormalize_average((float) $breakdown->subs->average, $scale);
        
        // Aggregate vote counts from sub-ratings with date+scope filter
        $breakdown->subs->member_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE rating_id IN ({$sub_ids_placeholder}) AND user_id > 0 {$date_condition} {$scope_condition}"
        );
        
        $breakdown->subs->guest_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE rating_id IN ({$sub_ids_placeholder}) AND user_id = 0 {$date_condition} {$scope_condition}"
        );
        
        $breakdown->subs->unique_voters = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id IN ({$sub_ids_placeholder}) {$date_condition} {$scope_condition}"
        );
        
        $breakdown->subs->first_vote = $this->wpdb->get_var(
            "SELECT MIN(date_created) FROM {$this->votes_table} WHERE rating_id IN ({$sub_ids_placeholder}) {$date_condition} {$scope_condition}"
        );
        
        $breakdown->subs->last_vote = $this->wpdb->get_var(
            "SELECT MAX(date_created) FROM {$this->votes_table} WHERE rating_id IN ({$sub_ids_placeholder}) {$date_condition} {$scope_condition}"
        );
        
        // Distribution from sub-ratings (with effect_type conversion) and date filter
        // Scale-aware inversion: binary uses r.scale, stars/numeric uses internal scale (RATING_SCALE_DEFAULT + 1)
        $internal_scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        $subs_distribution_results = $this->wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN r.effect_type = 'negative' AND r.rating_type IN ('like_dislike', 'approval') 
                        THEN CAST(r.scale AS SIGNED) - v.rating_value
                    WHEN r.effect_type = 'negative' 
                        THEN ({$internal_scale} + 1) - v.rating_value 
                    ELSE v.rating_value 
                END as effective_value, 
                COUNT(*) as count 
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE v.rating_id IN ({$sub_ids_placeholder}) {$date_condition_v} {$scope_condition_v}
             GROUP BY effective_value 
             ORDER BY effective_value"
        );

        // Use parent rating's type/scale for distribution buckets
        $parent_type = $rating->rating_type ?: 'stars';
        $parent_scale = (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT);
        
        $breakdown->subs->distribution = $this->build_empty_distribution($parent_type, $parent_scale);
        foreach ($subs_distribution_results as $row) {
            $key = intval($row->effective_value);
            if (array_key_exists($key, $breakdown->subs->distribution)) {
                $breakdown->subs->distribution[$key] = intval($row->count);
            }
        }
        
        // Votes over time from sub-ratings with date+scope filter
        $breakdown->subs->votes_over_time = $this->get_votes_over_time_for_ids($sub_rating_ids, $date_range, $scope);
        
        // === TOTAL (Combined) ===
        $breakdown->total = new stdClass();
        
        $breakdown->total->total_votes = $breakdown->direct->total_votes + $breakdown->subs->total_votes;
        $breakdown->total->total_rating = $breakdown->direct->total_rating + $subs_total_rating;
        $breakdown->total->average = $breakdown->total->total_votes > 0 
            ? round($breakdown->total->total_rating / $breakdown->total->total_votes, 1) 
            : 0;
        $breakdown->total->display_average = Shuriken_Database::denormalize_average((float) $breakdown->total->average, $scale);
        
        $breakdown->total->member_votes = $breakdown->direct->member_votes + $breakdown->subs->member_votes;
        $breakdown->total->guest_votes = $breakdown->direct->guest_votes + $breakdown->subs->guest_votes;
        
        // Unique voters across all (parent + subs) - need to recalculate for truly unique
        $all_rating_ids = array_merge(array($rating_id), $sub_rating_ids);
        $all_ids_placeholder = implode(',', array_map('intval', $all_rating_ids));
        
        $breakdown->total->unique_voters = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id IN ({$all_ids_placeholder}) {$date_condition} {$scope_condition}"
        );
        
        // First and last vote across all
        $breakdown->total->first_vote = min(
            $breakdown->direct->first_vote ?: PHP_INT_MAX,
            $breakdown->subs->first_vote ?: PHP_INT_MAX
        );
        $breakdown->total->first_vote = $breakdown->total->first_vote === PHP_INT_MAX ? null : $breakdown->total->first_vote;
        
        $breakdown->total->last_vote = max(
            $breakdown->direct->last_vote ?: '',
            $breakdown->subs->last_vote ?: ''
        ) ?: null;
        
        // Combined distribution (uses same bucket keys as direct and subs)
        $breakdown->total->distribution = $this->build_empty_distribution($parent_type, $parent_scale);
        foreach ($breakdown->total->distribution as $key => &$val) {
            $direct_val = isset($breakdown->direct->distribution[$key]) ? $breakdown->direct->distribution[$key] : 0;
            $subs_val = isset($breakdown->subs->distribution[$key]) ? $breakdown->subs->distribution[$key] : 0;
            $val = $direct_val + $subs_val;
        }
        unset($val);
        
        // Combined votes over time
        $breakdown->total->votes_over_time = $this->get_votes_over_time_for_ids($all_rating_ids, $date_range, $scope);
        
        return $breakdown;
    }
    
    /**
     * Get votes over time for multiple rating IDs
     *
     * @param array $rating_ids Array of rating IDs
     * @param mixed $date_range Date range (days, 'all', or array with 'start'/'end')
     * @return array Array of vote date/count objects
     */
    private function get_votes_over_time_for_ids(array $rating_ids, string|int|array $date_range = 30, ?string $scope = null): array {
        if (empty($rating_ids)) {
            return array();
        }
        
        $ids_placeholder = implode(',', array_map('intval', $rating_ids));
        $date_condition = $this->build_date_condition($date_range, 'date_created');
        $scope_condition = $this->build_scope_condition($scope);
        
        return $this->wpdb->get_results(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as vote_count 
             FROM {$this->votes_table} 
             WHERE rating_id IN ({$ids_placeholder}) {$date_condition} {$scope_condition}
             GROUP BY DATE(date_created) 
             ORDER BY vote_date"
        );
    }

    /**
     * Get paginated votes for a rating
     *
     * @param int $rating_id Rating ID
     * @param int $page Current page (1-indexed)
     * @param int $per_page Items per page
     * @param string|array $date_range Date range filter ('all', days, or array with start/end)
     * @param string $view For parent ratings: 'direct', 'subs', or 'total'
     * @return object Object with votes array, total_count, total_pages, current_page
     */
    public function get_rating_votes_paginated(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $view = 'direct', string $sort_by = 'date', string $sort_order = 'desc'): object {
        $offset = ($page - 1) * $per_page;
        
        $result = new stdClass();
        
        // Build date condition
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        // Safe sort column map
        $sort_col_map = array('rating' => 'v.rating_value', 'date' => 'v.date_created');
        $sort_col = $sort_col_map[$sort_by] ?? 'v.date_created';
        $sort_dir = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
        
        // Determine which rating IDs to query based on view
        $rating_ids = array($rating_id);
        if (in_array($view, array('subs', 'total'))) {
            // Get sub-rating IDs
            $sub_rating_ids = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT id FROM {$this->ratings_table} WHERE parent_id = %d",
                $rating_id
            ));
            
            if (!empty($sub_rating_ids)) {
                if ($view === 'subs') {
                    // Only subs, not direct
                    $rating_ids = $sub_rating_ids;
                } else {
                    // total: both direct and subs
                    $rating_ids = array_merge(array($rating_id), $sub_rating_ids);
                }
            }
        }
        
        $rating_ids_placeholder = implode(',', array_map('intval', $rating_ids));
        
        $result->total_count = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} v WHERE v.rating_id IN ({$rating_ids_placeholder}) {$date_condition}"
        );
        
        $result->total_pages = ceil($result->total_count / $per_page);
        $result->current_page = $page;
        $result->per_page = $per_page;
        
        $result->votes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.*, u.display_name, u.user_email, r.name as rating_name, r.rating_type, r.scale
             FROM {$this->votes_table} v
             LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             LEFT JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE v.rating_id IN ({$rating_ids_placeholder}) {$date_condition}
             ORDER BY {$sort_col} {$sort_dir}
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        // Mark which votes are from sub-ratings vs direct (for parent rating views)
        $result->is_multi_rating = count($rating_ids) > 1;
        $result->parent_rating_id = $rating_id;
        
        return $result;
    }

    /**
     * Format time ago string with proper timezone handling
     *
     * @param string $mysql_date MySQL datetime string
     * @return string Formatted "X time ago" string
     */
    public function format_time_ago(string $mysql_date): string {
        if (empty($mysql_date)) {
            return '-';
        }
        return human_time_diff(mysql2date('U', $mysql_date), current_time('timestamp')) 
               . ' ' . __('ago', 'shuriken-reviews');
    }

    /**
     * Format date with WordPress settings
     *
     * @param string $mysql_date MySQL datetime string
     * @param bool $include_time Whether to include time
     * @return string Formatted date string
     */
    public function format_date(string $mysql_date, bool $include_time = true): string {
        if (empty($mysql_date)) {
            return '-';
        }
        $format = get_option('date_format');
        if ($include_time) {
            $format .= ' ' . get_option('time_format');
        }
        return mysql2date($format, $mysql_date);
    }

    /**
     * Get data formatted for charts (Chart.js compatible)
     *
     * @param string|int $date_range Number of days or 'all'
     * @param int|null $rating_id Optional specific rating ID
     * @return array Array with distribution, votes_over_time, user_types
     */
    public function get_chart_data(string|int|array $date_range = 30, ?int $rating_id = null): array {
        $vote_counts = $rating_id ? null : $this->get_vote_counts($date_range);
        
        return array(
            'distribution' => array_values($this->get_rating_distribution($date_range, $rating_id)),
            'votes_over_time' => $this->get_votes_over_time($date_range, $rating_id),
            'user_types' => $rating_id ? null : array(
                'members' => $vote_counts->member_votes,
                'guests' => $vote_counts->guest_votes,
            ),
        );
    }

    /**
     * Check if a rating has sub-ratings
     *
     * @param int $rating_id Rating ID to check
     * @return bool True if rating has sub-ratings
     */
    public function has_sub_ratings(int $rating_id): bool {
        $count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE parent_id = %d",
            $rating_id
        ));
        return $count > 0;
    }

    // =========================================================================
    // Dashboard Analytics Methods (v2)
    // =========================================================================

    /**
     * Get voting heatmap data (hour-of-day × day-of-week)
     *
     * Returns a 7×24 matrix of vote counts for visualizing when users vote.
     * Day 1 = Sunday, Day 7 = Saturday (MySQL DAYOFWEEK convention).
     *
     * @param string|int|array $date_range Date range filter
     * @return array Array of objects with dow, hour, count
     */
    public function get_voting_heatmap(string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range);

        return $this->wpdb->get_results(
            "SELECT DAYOFWEEK(date_created) as dow, HOUR(date_created) as hour, COUNT(*) as count
             FROM {$this->votes_table}
             WHERE 1=1 {$date_condition}
             GROUP BY dow, hour
             ORDER BY dow, hour"
        );
    }

    /**
     * Get votes over time split by rating type (stars, like_dislike, numeric, approval)
     *
     * Used for the stacked area chart on the dashboard.
     *
     * @param string|int|array $date_range Date range filter
     * @return array Array of objects with vote_date, rating_type, vote_count
     */
    public function get_votes_over_time_by_type(string|int|array $date_range = 30): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');

        return $this->wpdb->get_results(
            "SELECT DATE(v.date_created) as vote_date,
                    COALESCE(r.rating_type, 'stars') as rating_type,
                    COUNT(*) as vote_count
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE 1=1 {$date_condition}
             GROUP BY vote_date, rating_type
             ORDER BY vote_date"
        );
    }

    /**
     * Get per-type summary statistics
     *
     * Returns one row per active rating type with appropriate metrics:
     * - Stars/Numeric: weighted average, item count, total votes
     * - Like/Dislike: average approval %, item count, total votes, likes, dislikes
     * - Approval: total approvals, item count
     *
     * @return array Array of type summary objects
     */
    public function get_per_type_summary(): array {
        $results = $this->wpdb->get_results(
            "SELECT COALESCE(r.rating_type, 'stars') as rating_type,
                    r.scale,
                    COUNT(DISTINCT r.id) as item_count,
                    COALESCE(SUM(r.total_votes), 0) as total_votes,
                    COALESCE(SUM(r.total_rating), 0) as total_rating
             FROM {$this->ratings_table} r
             WHERE r.mirror_of IS NULL
               AND r.parent_id IS NULL
               AND r.total_votes > 0
             GROUP BY r.rating_type, r.scale
             ORDER BY total_votes DESC"
        );

        // Aggregate by type (different scales of same type become one row)
        $summaries = array();
        foreach ($results as $row) {
            $type = $row->rating_type;
            if (!isset($summaries[$type])) {
                $summaries[$type] = (object) array(
                    'rating_type' => $type,
                    'item_count'  => 0,
                    'total_votes' => 0,
                    'total_rating' => 0,
                    'scale'       => (int) $row->scale,
                );
            }
            $summaries[$type]->item_count  += (int) $row->item_count;
            $summaries[$type]->total_votes += (int) $row->total_votes;
            $summaries[$type]->total_rating += (float) $row->total_rating;
        }

        // Calculate display metrics per type
        foreach ($summaries as &$s) {
            if (in_array($s->rating_type, array('like_dislike', 'approval'), true)) {
                $s->approval_rate = $s->total_votes > 0
                    ? round(($s->total_rating / $s->total_votes) * 100)
                    : 0;
            } else {
                $s->weighted_average = $s->total_votes > 0
                    ? Shuriken_Database::denormalize_average($s->total_rating / $s->total_votes, $s->scale)
                    : 0;
            }
        }
        unset($s);

        return array_values($summaries);
    }

    /**
     * Get participation rate — how many rating items have received at least one vote
     *
     * @return object Object with total_items, active_items, rate (0-100)
     */
    public function get_participation_rate(): object {
        $result = new stdClass();

        // Count only top-level, non-mirror ratings (sub-ratings and mirrors are excluded).
        // Participation is based on ratings that have received at least one direct (global)
        // vote — per-post (contextual) votes are intentionally excluded: a rating displayed
        // on N posts could theoretically appear on any number of posts, making "total items"
        // undefined until a user actually votes on them.
        $result->total_items = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->ratings_table}
             WHERE mirror_of IS NULL AND parent_id IS NULL"
        );

        $result->active_items = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT v.rating_id)
             FROM {$this->votes_table} v
             INNER JOIN {$this->ratings_table} r ON r.id = v.rating_id
             WHERE r.mirror_of IS NULL AND r.parent_id IS NULL AND v.context_id IS NULL"
        );

        $result->rate = $result->total_items > 0
            ? round(($result->active_items / $result->total_items) * 100)
            : 0;

        return $result;
    }

    /**
     * Get momentum items — ratings whose average is rising or falling vs. previous period
     *
     * Compares current half-period average to previous half-period average.
     * Only considers items with votes in both halves.
     *
     * @param string|int|array $date_range Date range filter (preset days only, not custom/all)
     * @param int $limit Max items per direction (rising + falling)
     * @return object Object with rising[] and falling[] arrays
     */
    public function get_momentum_items(string|int|array $date_range = 30, int $limit = 3): object {
        $result = new stdClass();
        $result->rising = array();
        $result->falling = array();

        // Only works with numeric day presets
        $days = intval($date_range);
        if ($days <= 0) {
            return $result;
        }

        $half = intval($days / 2);

        // Get items with votes in both halves, compare averages
        // Exclude binary types (approval rate momentum is different from star averages)
        $items = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.id, r.name, r.rating_type, r.scale, r.total_votes,
                    AVG(CASE WHEN v.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY) THEN v.rating_value END) as recent_avg,
                    AVG(CASE WHEN v.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
                              AND v.date_created < DATE_SUB(NOW(), INTERVAL %d DAY) THEN v.rating_value END) as prev_avg,
                    COUNT(CASE WHEN v.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 END) as recent_count,
                    COUNT(CASE WHEN v.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
                               AND v.date_created < DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 END) as prev_count
             FROM {$this->ratings_table} r
             JOIN {$this->votes_table} v ON v.rating_id = r.id
             WHERE r.mirror_of IS NULL
               AND r.parent_id IS NULL
               AND r.rating_type NOT IN ('approval')
               AND v.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY r.id
             HAVING recent_count >= 2 AND prev_count >= 2",
            $half, $days, $half, $half, $days, $half, $days
        ));

        foreach ($items as $item) {
            $recent = round((float) $item->recent_avg, 2);
            $prev = round((float) $item->prev_avg, 2);
            if ($prev == 0) continue;

            $delta = $recent - $prev;
            $item->delta = round($delta, 1);
            $item->recent_avg = round($recent, 1);
            $item->prev_avg = round($prev, 1);

            $s = isset($item->scale) ? (int) $item->scale : Shuriken_Database::RATING_SCALE_DEFAULT;
            $item->display_delta = Shuriken_Database::denormalize_average(abs((float) $item->delta), $s) * ($delta < 0 ? -1 : 1);
            $item->display_recent_avg = Shuriken_Database::denormalize_average((float) $item->recent_avg, $s);
            $item->display_prev_avg = Shuriken_Database::denormalize_average((float) $item->prev_avg, $s);

            if ($delta > 0) {
                $result->rising[] = $item;
            } elseif ($delta < 0) {
                $result->falling[] = $item;
            }
        }

        // Sort: rising by delta DESC, falling by delta ASC
        usort($result->rising, fn($a, $b) => $b->delta <=> $a->delta);
        usort($result->falling, fn($a, $b) => $a->delta <=> $b->delta);

        $result->rising = array_slice($result->rising, 0, $limit);
        $result->falling = array_slice($result->falling, 0, $limit);

        return $result;
    }

    /**
     * Get approval rate trend over time for a like/dislike rating
     *
     * Returns daily approval percentage instead of raw vote counts.
     *
     * @param int $rating_id Rating ID
     * @param string|int|array $date_range Date range filter
     * @return array Array of objects with vote_date and approval_rate (0-100)
     */
    public function get_approval_trend(int $rating_id, string|int|array $date_range = 30): array {
        $date_condition = $this->build_date_condition($date_range);

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as total,
                    SUM(rating_value) as likes,
                    ROUND((SUM(rating_value) / COUNT(*)) * 100, 1) as approval_rate
             FROM {$this->votes_table}
             WHERE rating_id = %d {$date_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id
        ));
    }

    /**
     * Get cumulative approval count over time for an approval-type rating
     *
     * @param int $rating_id Rating ID
     * @param string|int|array $date_range Date range filter
     * @return array Array of objects with vote_date, daily_count, cumulative_count
     */
    public function get_cumulative_approvals(int $rating_id, string|int|array $date_range = 30): array {
        $date_condition = $this->build_date_condition($date_range);

        $daily = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as daily_count
             FROM {$this->votes_table}
             WHERE rating_id = %d {$date_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id
        ));

        // Build cumulative
        $cumulative = 0;
        foreach ($daily as &$row) {
            $cumulative += (int) $row->daily_count;
            $row->cumulative_count = $cumulative;
        }
        unset($row);

        return $daily;
    }

    /**
     * Get votes over time with rolling average for stars/numeric items
     *
     * Returns daily vote counts alongside a rolling 7-day average score.
     *
     * @param int $rating_id Rating ID
     * @param string|int|array $date_range Date range filter
     * @param int $scale Display scale for denormalization
     * @return array Array of objects with vote_date, vote_count, daily_avg, display_daily_avg
     */
    public function get_votes_with_rolling_avg(int $rating_id, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): array {
        $date_condition = $this->build_date_condition($date_range);

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as vote_count,
                    ROUND(AVG(rating_value), 2) as daily_avg
             FROM {$this->votes_table}
             WHERE rating_id = %d {$date_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id
        ));

        foreach ($rows as &$row) {
            $row->display_daily_avg = Shuriken_Database::denormalize_average((float) $row->daily_avg, $scale);
        }
        unset($row);

        return $rows;
    }

    /**
     * Like get_votes_with_rolling_avg but accepts multiple rating IDs (for parent ratings).
     *
     * @param array $rating_ids Array of rating IDs to include
     * @param string|int|array $date_range Date range filter
     * @param int $scale Display scale for denormalization
     * @return array Array of objects with vote_date, vote_count, daily_avg, display_daily_avg
     */
    public function get_votes_with_rolling_avg_for_ids(array $rating_ids, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): array {
        if (empty($rating_ids)) {
            return array();
        }

        $ids_placeholder = implode(',', array_map('intval', $rating_ids));
        $date_condition = $this->build_date_condition($date_range);

        $rows = $this->wpdb->get_results(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as vote_count,
                    ROUND(AVG(rating_value), 2) as daily_avg
             FROM {$this->votes_table}
             WHERE rating_id IN ({$ids_placeholder}) {$date_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date"
        );

        foreach ($rows as &$row) {
            $row->display_daily_avg = Shuriken_Database::denormalize_average((float) $row->daily_avg, $scale);
        }
        unset($row);

        return $rows;
    }

    // =========================================================================
    // Contextual / Per-Post Analytics Methods
    // =========================================================================

    /**
     * Build SQL condition for vote scope filtering (global vs contextual)
     *
     * @param string|null $scope 'global' (context_id IS NULL), 'contextual' (context_id IS NOT NULL), or null (no filter)
     * @param string      $prefix Column prefix (e.g., 'v.' for aliased queries)
     * @return string SQL condition string (includes leading AND)
     */
    public function build_scope_condition(?string $scope, string $prefix = ''): string {
        return match ($scope) {
            'global'     => " AND {$prefix}context_id IS NULL",
            'contextual' => " AND {$prefix}context_id IS NOT NULL",
            default      => '',
        };
    }

    /**
     * Check if a rating has any contextual (per-post) votes
     *
     * @param int $rating_id Rating ID
     * @return bool True if at least one contextual vote exists
     * @since 1.15.0
     */
    public function has_contextual_votes(int $rating_id): bool {
        return (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT 1 FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL
             LIMIT 1",
            $rating_id
        ));
    }

    /**
     * Get global-only (scope-filtered) stats for a rating
     *
     * Like get_rating_stats() but with scope filter applied.
     *
     * @param int              $rating_id  Rating ID
     * @param string|int|array $date_range Date range filter
     * @param string|null      $scope      'global', 'contextual', or null
     * @return object|null Stats object or null
     * @since 1.15.0
     */
    public function get_rating_stats_scoped(int $rating_id, string|int|array $date_range = 'all', ?string $scope = null): ?object {
        $rating = $this->get_rating($rating_id);
        if (!$rating) {
            return null;
        }

        $date_condition = $this->build_date_condition($date_range);
        $scope_condition = $this->build_scope_condition($scope);

        $stats = new stdClass();
        $stats->rating = $rating;

        $filtered_totals = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
             FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));
        $stats->total_votes = (int) $filtered_totals->total_votes;
        $stats->total_rating = (int) $filtered_totals->total_rating;
        $stats->average = $stats->total_votes > 0 ? round($stats->total_rating / $stats->total_votes, 1) : 0;

        $scale = isset($rating->scale) ? (int) $rating->scale : Shuriken_Database::RATING_SCALE_DEFAULT;
        $stats->display_average = Shuriken_Database::denormalize_average((float) $stats->average, $scale);

        $stats->member_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id > 0 {$date_condition} {$scope_condition}",
            $rating_id
        ));

        $stats->guest_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id = 0 {$date_condition} {$scope_condition}",
            $rating_id
        ));

        $stats->unique_voters = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END)
             FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));

        $stats->first_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MIN(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));

        $stats->last_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition} {$scope_condition}",
            $rating_id
        ));

        $stats->distribution = $this->get_rating_distribution_scoped($date_range, $rating_id, $scope);
        $stats->votes_over_time = $this->get_votes_over_time_scoped($date_range, $rating_id, $scope);

        return $stats;
    }

    /**
     * Get rating distribution with scope filtering
     *
     * @param string|int|array $date_range Date range filter
     * @param int|null         $rating_id  Rating ID
     * @param string|null      $scope      'global', 'contextual', or null
     * @return array Distribution array
     * @since 1.15.0
     */
    public function get_rating_distribution_scoped(string|int|array $date_range = 'all', ?int $rating_id = null, ?string $scope = null): array {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        $rating_condition = $rating_id ? $this->wpdb->prepare(" AND v.rating_id = %d", $rating_id) : '';
        $scope_condition = $this->build_scope_condition($scope, 'v.');

        $rating_type = 'stars';
        $scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        if ($rating_id) {
            $rating = $this->db->get_rating($rating_id);
            if ($rating) {
                $rating_type = $rating->rating_type ?: 'stars';
                $scale = (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT);
            }
        }

        $type_condition = '';
        if (!$rating_id) {
            $type_condition = " AND r.rating_type NOT IN ('like_dislike', 'approval')";
        }

        $internal_scale = Shuriken_Database::RATING_SCALE_DEFAULT;
        $results = $this->wpdb->get_results(
            "SELECT
                CASE
                    WHEN r.effect_type = 'negative' AND r.rating_type IN ('like_dislike', 'approval')
                        THEN CAST(r.scale AS SIGNED) - v.rating_value
                    WHEN r.effect_type = 'negative'
                        THEN ({$internal_scale} + 1) - ROUND(v.rating_value)
                    ELSE ROUND(v.rating_value)
                END as effective_value,
                COUNT(*) as count
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE 1=1 {$date_condition} {$rating_condition} {$type_condition} {$scope_condition}
             GROUP BY effective_value
             ORDER BY effective_value"
        );

        $distribution = $this->build_empty_distribution($rating_type, $scale);
        foreach ($results as $row) {
            $key = intval($row->effective_value);
            if (array_key_exists($key, $distribution)) {
                $distribution[$key] = intval($row->count);
            }
        }

        return $distribution;
    }

    /**
     * Get votes over time with scope filtering
     *
     * @param string|int|array $date_range Date range filter
     * @param int|null         $rating_id  Rating ID
     * @param string|null      $scope      'global', 'contextual', or null
     * @return array Array of vote_date/vote_count objects
     * @since 1.15.0
     */
    public function get_votes_over_time_scoped(string|int|array $date_range = 30, ?int $rating_id = null, ?string $scope = null): array {
        $date_condition = $this->build_date_condition($date_range);
        $rating_condition = $rating_id ? $this->wpdb->prepare(" AND rating_id = %d", $rating_id) : '';
        $scope_condition = $this->build_scope_condition($scope);

        return $this->wpdb->get_results(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as vote_count
             FROM {$this->votes_table}
             WHERE 1=1 {$date_condition} {$rating_condition} {$scope_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date"
        );
    }

    /**
     * Get paginated votes for a rating with scope filtering
     *
     * @param int              $rating_id  Rating ID
     * @param int              $page       Page number
     * @param int              $per_page   Items per page
     * @param string|int|array $date_range Date range filter
     * @param string           $view       Parent view: 'direct', 'subs', 'total'
     * @param string|null      $scope      'global', 'contextual', or null
     * @return object Paginated result
     * @since 1.15.0
     */
    public function get_rating_votes_paginated_scoped(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $view = 'direct', ?string $scope = null, string $sort_by = 'date', string $sort_order = 'desc'): object {
        $offset = ($page - 1) * $per_page;
        $result = new stdClass();

        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        $scope_condition = $this->build_scope_condition($scope, 'v.');

        // Safe sort column map
        $sort_col_map = array('rating' => 'v.rating_value', 'date' => 'v.date_created');
        $sort_col = $sort_col_map[$sort_by] ?? 'v.date_created';
        $sort_dir = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

        $rating_ids = array($rating_id);
        if (in_array($view, array('subs', 'total'))) {
            $sub_rating_ids = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT id FROM {$this->ratings_table} WHERE parent_id = %d",
                $rating_id
            ));
            if (!empty($sub_rating_ids)) {
                $rating_ids = $view === 'subs' ? $sub_rating_ids : array_merge(array($rating_id), $sub_rating_ids);
            }
        }

        $rating_ids_placeholder = implode(',', array_map('intval', $rating_ids));

        $result->total_count = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} v WHERE v.rating_id IN ({$rating_ids_placeholder}) {$date_condition} {$scope_condition}"
        );

        $result->total_pages = ceil($result->total_count / $per_page);
        $result->current_page = $page;
        $result->per_page = $per_page;

        $result->votes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.*, u.display_name, u.user_email, r.name as rating_name, r.rating_type, r.scale
             FROM {$this->votes_table} v
             LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             LEFT JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE v.rating_id IN ({$rating_ids_placeholder}) {$date_condition} {$scope_condition}
             ORDER BY {$sort_col} {$sort_dir}
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        $result->is_multi_rating = count($rating_ids) > 1;
        $result->parent_rating_id = $rating_id;

        return $result;
    }

    /**
     * Get scope-filtered dual-axis chart data (votes + rolling avg)
     *
     * @param int              $rating_id  Rating ID
     * @param string|int|array $date_range Date range filter
     * @param int              $scale      Display scale for denormalization
     * @param string|null      $scope      'global', 'contextual', or null
     * @return array Chart data
     * @since 1.15.0
     */
    public function get_votes_with_rolling_avg_scoped(int $rating_id, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT, ?string $scope = null): array {
        $date_condition = $this->build_date_condition($date_range);
        $scope_condition = $this->build_scope_condition($scope);

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as vote_count,
                    ROUND(AVG(rating_value), 2) as daily_avg
             FROM {$this->votes_table}
             WHERE rating_id = %d {$date_condition} {$scope_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id
        ));

        foreach ($rows as &$row) {
            $row->display_daily_avg = Shuriken_Database::denormalize_average((float) $row->daily_avg, $scale);
        }
        unset($row);

        return $rows;
    }

    /**
     * Get scope-filtered approval trend
     *
     * @param int              $rating_id  Rating ID
     * @param string|int|array $date_range Date range filter
     * @param string|null      $scope      'global', 'contextual', or null
     * @return array Approval trend data
     * @since 1.15.0
     */
    public function get_approval_trend_scoped(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array {
        $date_condition = $this->build_date_condition($date_range);
        $scope_condition = $this->build_scope_condition($scope);

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as total,
                    SUM(rating_value) as likes,
                    ROUND((SUM(rating_value) / COUNT(*)) * 100, 1) as approval_rate
             FROM {$this->votes_table}
             WHERE rating_id = %d {$date_condition} {$scope_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id
        ));
    }

    /**
     * Get scope-filtered cumulative approvals
     *
     * @param int              $rating_id  Rating ID
     * @param string|int|array $date_range Date range filter
     * @param string|null      $scope      'global', 'contextual', or null
     * @return array Cumulative data
     * @since 1.15.0
     */
    public function get_cumulative_approvals_scoped(int $rating_id, string|int|array $date_range = 30, ?string $scope = null): array {
        $date_condition = $this->build_date_condition($date_range);
        $scope_condition = $this->build_scope_condition($scope);

        $daily = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as daily_count
             FROM {$this->votes_table}
             WHERE rating_id = %d {$date_condition} {$scope_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id
        ));

        $cumulative = 0;
        foreach ($daily as &$row) {
            $cumulative += (int) $row->daily_count;
            $row->cumulative_count = $cumulative;
        }
        unset($row);

        return $daily;
    }

    /**
     * Get contextual overview summary for a rating (used in the Per-Post view)
     *
     * Returns aggregate stats about all contextual votes for a specific rating.
     *
     * @param int              $rating_id  Rating ID
     * @param string|int|array $date_range Date range filter
     * @return object Summary with total_contexts, total_votes, avg_across_contexts, best_context
     * @since 1.15.0
     */
    public function get_rating_context_summary(int $rating_id, string|int|array $date_range = 'all'): object {
        $date_condition = $this->build_date_condition($date_range);

        $summary = new stdClass();

        // Total distinct contexts
        $summary->total_contexts = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(context_id, ':', context_type))
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}",
            $rating_id
        ));

        // Total contextual votes
        $totals = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}",
            $rating_id
        ));
        $summary->total_votes = (int) $totals->total_votes;
        $summary->total_rating = (int) $totals->total_rating;
        $summary->average = $summary->total_votes > 0 ? round($summary->total_rating / $summary->total_votes, 1) : 0;

        $rating = $this->get_rating($rating_id);
        $scale = $rating ? (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT) : Shuriken_Database::RATING_SCALE_DEFAULT;
        $summary->display_average = Shuriken_Database::denormalize_average((float) $summary->average, $scale);

        // Unique voters across all contexts
        $summary->unique_voters = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END)
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}",
            $rating_id
        ));

        // Best performing context (highest average with at least 1 vote)
        $best = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT context_id, context_type,
                    COUNT(*) as votes,
                    ROUND(AVG(rating_value), 2) as avg_rating
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}
             GROUP BY context_id, context_type
             ORDER BY avg_rating DESC, votes DESC
             LIMIT 1",
            $rating_id
        ));

        if ($best) {
            $summary->best_context_id = (int) $best->context_id;
            $summary->best_context_type = $best->context_type;
            $summary->best_context_avg = Shuriken_Database::denormalize_average((float) $best->avg_rating, $scale);
            $summary->best_context_votes = (int) $best->votes;
            $summary->best_context_title = $this->get_context_title($best->context_id, $best->context_type);
        } else {
            $summary->best_context_id = null;
        }

        return $summary;
    }

    /**
     * Get paginated list of contexts (posts/pages/products) for a rating
     *
     * @param int              $rating_id  Rating ID
     * @param int              $page       Page number
     * @param int              $per_page   Items per page
     * @param string|int|array $date_range Date range filter
     * @param string           $sort_by    Column to sort by: 'votes', 'average', 'last_vote'
     * @param string           $sort_order Sort direction: 'asc' or 'desc'
     * @return object Paginated result with contexts, total_count, total_pages
     * @since 1.15.0
     */
    public function get_rating_contexts_paginated(int $rating_id, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $sort_by = 'votes', string $sort_order = 'desc'): object {
        $offset = ($page - 1) * $per_page;
        $date_condition = $this->build_date_condition($date_range);
        $result = new stdClass();

        // Validate sort column
        $allowed_sort = array('votes' => 'ctx_votes', 'average' => 'ctx_avg', 'last_vote' => 'last_vote_date');
        $order_col = $allowed_sort[$sort_by] ?? 'ctx_votes';
        $order_dir = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

        $result->total_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CONCAT(context_id, ':', context_type))
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}",
            $rating_id
        ));

        $result->total_pages = $result->total_count > 0 ? ceil($result->total_count / $per_page) : 0;
        $result->current_page = $page;
        $result->per_page = $per_page;

        $result->contexts = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT context_id, context_type,
                    COUNT(*) as ctx_votes,
                    COALESCE(SUM(rating_value), 0) as ctx_total,
                    ROUND(AVG(rating_value), 2) as ctx_avg,
                    MAX(date_created) as last_vote_date,
                    MIN(date_created) as first_vote_date,
                    COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) as unique_voters
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}
             GROUP BY context_id, context_type
             ORDER BY {$order_col} {$order_dir}
             LIMIT %d OFFSET %d",
            $rating_id,
            $per_page,
            $offset
        ));

        // Enrich with post titles and display averages
        $rating = $this->get_rating($rating_id);
        $scale = $rating ? (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT) : Shuriken_Database::RATING_SCALE_DEFAULT;

        foreach ($result->contexts as &$ctx) {
            $ctx->context_id = (int) $ctx->context_id;
            $ctx->ctx_votes = (int) $ctx->ctx_votes;
            $ctx->ctx_total = (int) $ctx->ctx_total;
            $ctx->unique_voters = (int) $ctx->unique_voters;
            $ctx->ctx_display_avg = Shuriken_Database::denormalize_average((float) $ctx->ctx_avg, $scale);
            $ctx->title = $this->get_context_title($ctx->context_id, $ctx->context_type);
            $ctx->edit_url = get_edit_post_link($ctx->context_id, 'raw');
            $ctx->view_url = get_permalink($ctx->context_id);
        }
        unset($ctx);

        return $result;
    }

    /**
     * Get top contexts by vote count for a rating (for chart data)
     *
     * @param int              $rating_id  Rating ID
     * @param int              $limit      Max results
     * @param string|int|array $date_range Date range filter
     * @return array Array of context objects
     * @since 1.15.0
     */
    public function get_top_contexts_by_votes(int $rating_id, int $limit = 10, string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range);

        $rating = $this->get_rating($rating_id);
        $scale = $rating ? (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT) : Shuriken_Database::RATING_SCALE_DEFAULT;

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT context_id, context_type,
                    COUNT(*) as ctx_votes,
                    ROUND(AVG(rating_value), 2) as ctx_avg
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}
             GROUP BY context_id, context_type
             ORDER BY ctx_votes DESC
             LIMIT %d",
            $rating_id,
            $limit
        ));

        foreach ($rows as &$row) {
            $row->context_id = (int) $row->context_id;
            $row->ctx_votes = (int) $row->ctx_votes;
            $row->ctx_display_avg = Shuriken_Database::denormalize_average((float) $row->ctx_avg, $scale);
            $row->title = $this->get_context_title($row->context_id, $row->context_type);
        }
        unset($row);

        return $rows;
    }

    /**
     * Get distribution of average ratings across contexts (histogram buckets)
     *
     * Groups contexts into rating buckets to show how many posts fall into each
     * average rating range. Uses 0.5-step buckets for stars/numeric, percentage
     * buckets for binary types.
     *
     * @param int              $rating_id  Rating ID
     * @param string|int|array $date_range Date range filter
     * @return array Array of bucket => count pairs
     * @since 1.15.0
     */
    public function get_context_avg_distribution(int $rating_id, string|int|array $date_range = 'all'): array {
        $date_condition = $this->build_date_condition($date_range);

        $rating = $this->get_rating($rating_id);
        $rating_type = $rating ? ($rating->rating_type ?: 'stars') : 'stars';
        $scale = $rating ? (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT) : Shuriken_Database::RATING_SCALE_DEFAULT;

        // Get per-context averages
        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT context_id, context_type, AVG(rating_value) as ctx_avg
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL {$date_condition}
             GROUP BY context_id, context_type",
            $rating_id
        ));

        if ($this->is_binary_type($rating_type)) {
            // For binary types, bucket by approval rate in 10% steps
            $buckets = array();
            for ($i = 0; $i <= 100; $i += 10) {
                $buckets[$i . '%'] = 0;
            }
            foreach ($rows as $row) {
                $pct = round((float) $row->ctx_avg * 100);
                $bucket = min(100, floor($pct / 10) * 10);
                $buckets[$bucket . '%']++;
            }
            return $buckets;
        }

        // Stars/numeric: bucket by denormalized values in 1-step increments
        $buckets = array();
        for ($i = 1; $i <= $scale; $i++) {
            $buckets[(string) $i] = 0;
        }

        foreach ($rows as $row) {
            $denorm = Shuriken_Database::denormalize_average((float) $row->ctx_avg, $scale);
            $bucket = max(1, min($scale, round($denorm)));
            $buckets[(string) $bucket]++;
        }

        return $buckets;
    }

    /**
     * Get trending contexts — contexts with rising vote momentum
     *
     * Compares recent half-period to previous half-period vote counts.
     *
     * @param int              $rating_id  Rating ID
     * @param string|int|array $date_range Date range filter (numeric days only)
     * @param int              $limit      Max results
     * @return array Array of trending context objects
     * @since 1.15.0
     */
    public function get_trending_contexts(int $rating_id, string|int|array $date_range = 30, int $limit = 5, string $sort_by = 'velocity', string $sort_order = 'desc'): array {
        $days = intval($date_range);
        if ($days <= 0) {
            return array();
        }

        $half = intval($days / 2);

        $rating = $this->get_rating($rating_id);
        $scale = $rating ? (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT) : Shuriken_Database::RATING_SCALE_DEFAULT;

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT context_id, context_type,
                    COUNT(CASE WHEN date_created >= DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 END) as recent_votes,
                    COUNT(CASE WHEN date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
                               AND date_created < DATE_SUB(NOW(), INTERVAL %d DAY) THEN 1 END) as prev_votes,
                    ROUND(AVG(rating_value), 2) as ctx_avg
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id IS NOT NULL
               AND date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY context_id, context_type
             HAVING recent_votes > 0
             ORDER BY (recent_votes - prev_votes) DESC
             LIMIT %d",
            $half, $days, $half, $rating_id, $days, $limit
        ));

        foreach ($rows as &$row) {
            $row->context_id = (int) $row->context_id;
            $row->recent_votes = (int) $row->recent_votes;
            $row->prev_votes = (int) $row->prev_votes;
            $row->velocity = $row->prev_votes > 0
                ? round((($row->recent_votes - $row->prev_votes) / $row->prev_votes) * 100)
                : ($row->recent_votes > 0 ? 100 : 0);
            $row->ctx_display_avg = Shuriken_Database::denormalize_average((float) $row->ctx_avg, $scale);
            $row->title = $this->get_context_title($row->context_id, $row->context_type);
        }
        unset($row);

        // Sort the results by the requested column (PHP-level, since velocity is computed post-query)
        $sort_props = array('recent_votes' => 'recent_votes', 'velocity' => 'velocity', 'ctx_avg' => 'ctx_avg');
        $sort_prop = $sort_props[$sort_by] ?? 'velocity';
        usort($rows, function ($a, $b) use ($sort_prop, $sort_order) {
            $cmp = $a->$sort_prop <=> $b->$sort_prop;
            return strtolower($sort_order) === 'asc' ? $cmp : -$cmp;
        });

        return $rows;
    }

    /**
     * Get detailed stats for a rating scoped to a single context (post/page/product)
     *
     * @param int              $rating_id    Rating ID
     * @param int              $context_id   Post ID
     * @param string           $context_type Context type ('post', 'page', 'product')
     * @param string|int|array $date_range   Date range filter
     * @return object|null Stats object or null if no data
     * @since 1.15.0
     */
    public function get_context_rating_stats(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 'all'): ?object {
        $rating = $this->get_rating($rating_id);
        if (!$rating) {
            return null;
        }

        $date_condition = $this->build_date_condition($date_range);
        $scale = (int) ($rating->scale ?: Shuriken_Database::RATING_SCALE_DEFAULT);

        $stats = new stdClass();
        $stats->rating = $rating;

        $totals = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $stats->total_votes = (int) $totals->total_votes;
        $stats->total_rating = (int) $totals->total_rating;
        $stats->average = $stats->total_votes > 0 ? round($stats->total_rating / $stats->total_votes, 1) : 0;
        $stats->display_average = Shuriken_Database::denormalize_average((float) $stats->average, $scale);

        $stats->member_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s AND user_id > 0 {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $stats->guest_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s AND user_id = 0 {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $stats->unique_voters = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END)
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $stats->first_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MIN(date_created) FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $stats->last_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(date_created) FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        // Distribution for this context
        $rating_type = $rating->rating_type ?: 'stars';
        $distribution = $this->build_empty_distribution($rating_type, $scale);
        $dist_rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT ROUND(rating_value) as value, COUNT(*) as count
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}
             GROUP BY value ORDER BY value",
            $rating_id, $context_id, $context_type
        ));
        foreach ($dist_rows as $row) {
            $key = intval($row->value);
            if (array_key_exists($key, $distribution)) {
                $distribution[$key] = intval($row->count);
            }
        }
        $stats->distribution = $distribution;

        // Votes over time for this context
        $stats->votes_over_time = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as vote_count
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}
             GROUP BY DATE(date_created) ORDER BY vote_date",
            $rating_id, $context_id, $context_type
        ));

        return $stats;
    }

    /**
     * Get paginated votes for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID
     * @param int              $context_id   Post ID
     * @param string           $context_type Context type
     * @param int              $page         Page number
     * @param int              $per_page     Items per page
     * @param string|int|array $date_range   Date range filter
     * @return object Paginated result
     * @since 1.15.0
     */
    public function get_context_votes_paginated(int $rating_id, int $context_id, string $context_type, int $page = 1, int $per_page = 20, string|int|array $date_range = 'all', string $sort_by = 'date', string $sort_order = 'desc'): object {
        $offset = ($page - 1) * $per_page;
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        $result = new stdClass();

        // Safe sort column map
        $sort_col_map = array('rating' => 'v.rating_value', 'date' => 'v.date_created');
        $sort_col = $sort_col_map[$sort_by] ?? 'v.date_created';
        $sort_dir = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

        $result->total_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} v
             WHERE v.rating_id = %d AND v.context_id = %d AND v.context_type = %s {$date_condition}",
            $rating_id, $context_id, $context_type
        ));

        $result->total_pages = $result->total_count > 0 ? ceil($result->total_count / $per_page) : 0;
        $result->current_page = $page;
        $result->per_page = $per_page;

        $result->votes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.*, u.display_name, u.user_email, r.name as rating_name, r.rating_type, r.scale
             FROM {$this->votes_table} v
             LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             LEFT JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE v.rating_id = %d AND v.context_id = %d AND v.context_type = %s {$date_condition}
             ORDER BY {$sort_col} {$sort_dir}
             LIMIT %d OFFSET %d",
            $rating_id, $context_id, $context_type,
            $per_page, $offset
        ));

        return $result;
    }

    /**
     * Get dual-axis chart data for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID
     * @param int              $context_id   Post ID
     * @param string           $context_type Context type
     * @param string|int|array $date_range   Date range filter
     * @param int              $scale        Display scale
     * @return array Chart data
     * @since 1.15.0
     */
    public function get_context_votes_with_rolling_avg(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30, int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): array {
        $date_condition = $this->build_date_condition($date_range);

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as vote_count,
                    ROUND(AVG(rating_value), 2) as daily_avg
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id, $context_id, $context_type
        ));

        foreach ($rows as &$row) {
            $row->display_daily_avg = Shuriken_Database::denormalize_average((float) $row->daily_avg, $scale);
        }
        unset($row);

        return $rows;
    }

    /**
     * Get approval trend for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID
     * @param int              $context_id   Post ID
     * @param string           $context_type Context type
     * @param string|int|array $date_range   Date range filter
     * @return array Approval trend data
     * @since 1.15.0
     */
    public function get_context_approval_trend(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30): array {
        $date_condition = $this->build_date_condition($date_range);

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date,
                    COUNT(*) as total,
                    SUM(rating_value) as likes,
                    ROUND((SUM(rating_value) / COUNT(*)) * 100, 1) as approval_rate
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id, $context_id, $context_type
        ));
    }

    /**
     * Get cumulative approvals for a rating scoped to a single context
     *
     * @param int              $rating_id    Rating ID
     * @param int              $context_id   Post ID
     * @param string           $context_type Context type
     * @param string|int|array $date_range   Date range filter
     * @return array Cumulative data
     * @since 1.15.0
     */
    public function get_context_cumulative_approvals(int $rating_id, int $context_id, string $context_type, string|int|array $date_range = 30): array {
        $date_condition = $this->build_date_condition($date_range);

        $daily = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as daily_count
             FROM {$this->votes_table}
             WHERE rating_id = %d AND context_id = %d AND context_type = %s {$date_condition}
             GROUP BY DATE(date_created)
             ORDER BY vote_date",
            $rating_id, $context_id, $context_type
        ));

        $cumulative = 0;
        foreach ($daily as &$row) {
            $cumulative += (int) $row->daily_count;
            $row->cumulative_count = $cumulative;
        }
        unset($row);

        return $daily;
    }

    /**
     * Get the human-readable title for a context (post/page/product)
     *
     * @param int    $context_id   Post ID
     * @param string $context_type Context type
     * @return string Post title or fallback string
     * @since 1.15.0
     */
    private function get_context_title(int $context_id, string $context_type): string {
        $title = get_the_title($context_id);
        if (empty($title)) {
            return sprintf(__('#%d (%s)', 'shuriken-reviews'), $context_id, $context_type);
        }
        return $title;
    }
}
