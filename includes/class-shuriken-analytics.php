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

    /**
     * @var wpdb WordPress database instance
     */
    private $wpdb;

    /**
     * @var string Ratings table name
     */
    private $ratings_table;

    /**
     * @var string Votes table name
     */
    private $votes_table;

    /**
     * @var Shuriken_Database Database instance
     */
    private $db;

    /**
     * Constructor
     *
     * @param Shuriken_Database_Interface|null $db Optional database instance (for dependency injection).
     */
    public function __construct($db = null) {
        // Use provided database or get from container
        $this->db = $db ?: shuriken_db();
        $this->wpdb = $this->db->get_wpdb();
        $this->ratings_table = $this->db->get_ratings_table();
        $this->votes_table = $this->db->get_votes_table();
    }

    /**
     * Get singleton instance
     *
     * @return Shuriken_Analytics
     */
    public static function get_instance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Get the database instance
     *
     * @return Shuriken_Database
     */
    public function get_db() {
        return $this->db;
    }

    /**
     * Build date condition SQL for filtering by date range
     * 
     * Supports three modes:
     * 1. Relative days: Pass a number like '30' for "last 30 days"
     * 2. Custom range: Pass an array with 'start' and/or 'end' keys (Y-m-d format)
     * 3. All time: Pass 'all' or empty value
     *
     * @param string|int|array $date_range Number of days, 'all', or array with 'start'/'end' keys
     * @param string $column Column name for date comparison (can include table alias like 'v.date_created')
     * @return string SQL condition string
     */
    public function build_date_condition($date_range, $column = 'date_created') {
        // No filter for 'all' or empty
        if ($date_range === 'all' || empty($date_range)) {
            return '';
        }
        
        // Custom date range with start and/or end dates
        if (is_array($date_range)) {
            $conditions = array();
            
            if (!empty($date_range['start'])) {
                $start_date = sanitize_text_field($date_range['start']);
                // Validate date format (Y-m-d)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
                    $conditions[] = $this->wpdb->prepare(
                        "{$column} >= %s",
                        $start_date . ' 00:00:00'
                    );
                }
            }
            
            if (!empty($date_range['end'])) {
                $end_date = sanitize_text_field($date_range['end']);
                // Validate date format (Y-m-d)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
                    $conditions[] = $this->wpdb->prepare(
                        "{$column} <= %s",
                        $end_date . ' 23:59:59'
                    );
                }
            }
            
            if (!empty($conditions)) {
                return ' AND ' . implode(' AND ', $conditions);
            }
            return '';
        }
        
        // Relative days (e.g., '30' for last 30 days)
        return $this->wpdb->prepare(
            " AND {$column} >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            intval($date_range)
        );
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
    public function parse_date_range_params($params) {
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
    public function get_date_range_label($date_range) {
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
     * Get overall statistics
     *
     * @return object Object containing total_ratings, total_votes, overall_average, unique_voters
     */
    public function get_overall_stats() {
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
     * Get rating type counts breakdown
     *
     * @return object Object with standalone, parent, sub, display_only, mirror counts
     */
    public function get_rating_type_counts() {
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
    public function get_vote_counts($date_range = 'all') {
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
    public function get_vote_change_percent($date_range) {
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
     * Get top rated items (standalone and parent ratings only)
     *
     * @param int $limit Maximum number of items to return
     * @param int $min_votes Minimum votes required
     * @param float $min_average Minimum average rating (default 3.0 for "top" items)
     * @param string|int|array $date_range Date range filter
     * @return array Array of rating objects
     */
    public function get_top_rated($limit = 10, $min_votes = 1, $min_average = 3.0, $date_range = 'all') {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        // If no date filter, use the cached totals from ratings table (faster)
        if (empty($date_condition)) {
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, name, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of,
                        ROUND(total_rating / NULLIF(total_votes, 0), 1) as average 
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
            "SELECT r.id, r.name, r.parent_id, r.effect_type, r.display_only, r.mirror_of,
                    COUNT(v.id) as total_votes,
                    COALESCE(SUM(
                        CASE 
                            WHEN sub.effect_type = 'negative' THEN 6 - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 0) as total_rating,
                    ROUND(AVG(
                        CASE 
                            WHEN sub.effect_type = 'negative' THEN 6 - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 1) as average
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
    public function get_most_voted($limit = 10, $date_range = 'all') {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        // If no date filter, use the cached totals from ratings table (faster)
        if (empty($date_condition)) {
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, name, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of,
                        ROUND(total_rating / NULLIF(total_votes, 0), 1) as average
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
            "SELECT r.id, r.name, r.parent_id, r.effect_type, r.display_only, r.mirror_of,
                    COUNT(v.id) as total_votes,
                    COALESCE(SUM(
                        CASE 
                            WHEN sub.effect_type = 'negative' THEN 6 - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 0) as total_rating,
                    ROUND(AVG(
                        CASE 
                            WHEN sub.effect_type = 'negative' THEN 6 - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 1) as average
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
    public function get_low_performers($limit = 10, $min_votes = 1, $max_average = 3.0, $date_range = 'all') {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        // If no date filter, use the cached totals from ratings table (faster)
        if (empty($date_condition)) {
            return $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, name, total_votes, total_rating, parent_id, effect_type, display_only, mirror_of,
                        ROUND(total_rating / NULLIF(total_votes, 0), 1) as average 
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
            "SELECT r.id, r.name, r.parent_id, r.effect_type, r.display_only, r.mirror_of,
                    COUNT(v.id) as total_votes,
                    COALESCE(SUM(
                        CASE 
                            WHEN sub.effect_type = 'negative' THEN 6 - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 0) as total_rating,
                    ROUND(AVG(
                        CASE 
                            WHEN sub.effect_type = 'negative' THEN 6 - v.rating_value
                            ELSE v.rating_value
                        END
                    ), 1) as average
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
     * Build SQL condition for filtering by rating type
     *
     * @param string $type Type filter: 'all', 'standalone', 'parent', 'sub'
     * @return string SQL condition string
     */
    private function build_type_condition($type) {
        switch ($type) {
            case 'standalone':
                return "AND parent_id IS NULL AND NOT EXISTS (SELECT 1 FROM {$this->ratings_table} sub WHERE sub.parent_id = {$this->ratings_table}.id)";
            case 'parent':
                return "AND EXISTS (SELECT 1 FROM {$this->ratings_table} sub WHERE sub.parent_id = {$this->ratings_table}.id)";
            case 'sub':
                return "AND parent_id IS NOT NULL";
            default:
                return "";
        }
    }

    /**
     * Get parent ratings with their sub-rating statistics
     *
     * @param int $limit Maximum number of items to return
     * @return array Array of parent rating objects with sub-rating stats
     */
    public function get_parent_ratings_with_stats($limit = 10) {
        $parents = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DISTINCT p.id, p.name, p.total_votes, p.total_rating, p.display_only,
                    ROUND(p.total_rating / NULLIF(p.total_votes, 0), 1) as average,
                    (SELECT COUNT(*) FROM {$this->ratings_table} s WHERE s.parent_id = p.id) as sub_count,
                    (SELECT COUNT(*) FROM {$this->ratings_table} s WHERE s.parent_id = p.id AND s.effect_type = 'positive') as positive_subs,
                    (SELECT COUNT(*) FROM {$this->ratings_table} s WHERE s.parent_id = p.id AND s.effect_type = 'negative') as negative_subs
             FROM {$this->ratings_table} p
             WHERE EXISTS (SELECT 1 FROM {$this->ratings_table} sub WHERE sub.parent_id = p.id)
             ORDER BY p.total_votes DESC
             LIMIT %d",
            $limit
        ));
        
        return $parents;
    }

    /**
     * Get sub-ratings for a specific parent with contribution analysis
     *
     * @param int $parent_id Parent rating ID
     * @return array Array of sub-rating objects with contribution data
     */
    public function get_sub_ratings_contribution($parent_id) {
        $parent = $this->db->get_rating($parent_id);
        if (!$parent || $parent->total_votes == 0) {
            return array();
        }
        
        $subs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, name, total_votes, total_rating, effect_type,
                    ROUND(total_rating / NULLIF(total_votes, 0), 1) as average,
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
                    // Negative: higher rating = lower contribution
                    $sub->effective_average = 6 - $sub_average;
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
     * Get mirror ratings with their original rating info
     *
     * @param int $limit Maximum number of items to return
     * @return array Array of mirror rating objects
     */
    public function get_mirror_ratings_with_originals($limit = 10) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT m.id, m.name as mirror_name, 
                    o.id as original_id, o.name as original_name, 
                    o.total_votes, o.total_rating,
                    ROUND(o.total_rating / NULLIF(o.total_votes, 0), 1) as average
             FROM {$this->ratings_table} m
             JOIN {$this->ratings_table} o ON m.mirror_of = o.id
             ORDER BY o.total_votes DESC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get ratings that have mirrors
     *
     * @param int $limit Maximum number of items to return
     * @return array Array of original rating objects with mirror count
     */
    public function get_mirrored_ratings($limit = 10) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT r.id, r.name, r.total_votes, r.total_rating,
                    ROUND(r.total_rating / NULLIF(r.total_votes, 0), 1) as average,
                    (SELECT COUNT(*) FROM {$this->ratings_table} m WHERE m.mirror_of = r.id) as mirror_count
             FROM {$this->ratings_table} r
             WHERE EXISTS (SELECT 1 FROM {$this->ratings_table} m WHERE m.mirror_of = r.id)
             ORDER BY mirror_count DESC, r.total_votes DESC
             LIMIT %d",
            $limit
        ));
    }

    /**
     * Get rating distribution (count of each star value)
     * 
     * For sub-ratings with negative effect_type, the rating values are inverted
     * to show the effective contribution (1★→5★, 2★→4★, 3★→3★, 4★→2★, 5★→1★).
     *
     * @param string|int $date_range Number of days or 'all'
     * @param int|null $rating_id Optional specific rating ID
     * @return array Associative array with keys 1-5 and vote counts
     */
    public function get_rating_distribution($date_range = 'all', $rating_id = null) {
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        $rating_condition = $rating_id ? $this->wpdb->prepare(" AND v.rating_id = %d", $rating_id) : '';
        
        // Join with ratings table to get effect_type and invert negative effect votes
        // For negative effect: effective_value = 6 - rating_value (1→5, 2→4, 3→3, 4→2, 5→1)
        $results = $this->wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN r.effect_type = 'negative' THEN 6 - v.rating_value 
                    ELSE v.rating_value 
                END as effective_value, 
                COUNT(*) as count 
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE 1=1 {$date_condition} {$rating_condition}
             GROUP BY effective_value 
             ORDER BY effective_value"
        );
        
        // Ensure all ratings 1-5 are represented
        $distribution = array_fill(1, 5, 0);
        foreach ($results as $row) {
            $distribution[intval($row->effective_value)] = intval($row->count);
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
    public function get_votes_over_time($date_range = 30, $rating_id = null) {
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
    public function get_recent_votes($limit = 10, $rating_id = null, $date_range = 'all') {
        $rating_condition = $rating_id ? $this->wpdb->prepare("AND v.rating_id = %d", $rating_id) : '';
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT v.id, v.rating_id, v.rating_value, v.date_created, v.user_id, v.user_ip,
                    r.name as rating_name, u.display_name, u.user_email
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
    public function get_rating($rating_id) {
        return $this->db->get_rating($rating_id);
    }

    /**
     * Get detailed stats for a single rating item
     *
     * @param int $rating_id Rating ID
     * @param string|int|array $date_range Date range filter
     * @return object|null Object with comprehensive stats or null
     */
    public function get_rating_stats($rating_id, $date_range = 'all') {
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
    public function get_parent_rating_stats_breakdown($rating_id, $date_range = 'all') {
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
        
        // Build date condition
        $date_condition = $this->build_date_condition($date_range, 'date_created');
        $date_condition_v = $this->build_date_condition($date_range, 'v.date_created');
        
        $breakdown = new stdClass();
        
        // === DIRECT VOTES (on parent rating itself) ===
        $breakdown->direct = new stdClass();
        
        $direct_totals = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
             FROM {$this->votes_table} WHERE rating_id = %d {$date_condition}",
            $rating_id
        ));
        
        $breakdown->direct->total_votes = (int) $direct_totals->total_votes;
        $breakdown->direct->total_rating = (int) $direct_totals->total_rating;
        $breakdown->direct->average = $breakdown->direct->total_votes > 0 
            ? round($breakdown->direct->total_rating / $breakdown->direct->total_votes, 1) 
            : 0;
        
        $breakdown->direct->member_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id > 0 {$date_condition}",
            $rating_id
        ));
        
        $breakdown->direct->guest_votes = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->votes_table} WHERE rating_id = %d AND user_id = 0 {$date_condition}",
            $rating_id
        ));
        
        $breakdown->direct->unique_voters = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id = %d {$date_condition}",
            $rating_id
        ));
        
        $breakdown->direct->first_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MIN(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition}",
            $rating_id
        ));
        
        $breakdown->direct->last_vote = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(date_created) FROM {$this->votes_table} WHERE rating_id = %d {$date_condition}",
            $rating_id
        ));
        
        $breakdown->direct->distribution = $this->get_rating_distribution($date_range, $rating_id);
        $breakdown->direct->votes_over_time = $this->get_votes_over_time($date_range, $rating_id);
        
        // === SUB-RATINGS AGGREGATED ===
        $breakdown->subs = new stdClass();
        
        $sub_ids_placeholder = implode(',', array_map('intval', $sub_rating_ids));
        
        // Get sub-ratings with their effect types
        $sub_ratings_info = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, effect_type FROM {$this->ratings_table} WHERE parent_id = %d",
            $rating_id
        ));
        
        // Calculate effective totals from sub-ratings with date filter (applying effect_type inversion)
        $subs_total_votes = 0;
        $subs_total_rating = 0;
        foreach ($sub_ratings_info as $sub) {
            $sub_totals = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT COUNT(*) as total_votes, COALESCE(SUM(rating_value), 0) as total_rating
                 FROM {$this->votes_table} WHERE rating_id = %d {$date_condition}",
                $sub->id
            ));
            
            if ($sub_totals->total_votes > 0) {
                $subs_total_votes += (int) $sub_totals->total_votes;
                if ($sub->effect_type === 'negative') {
                    // Invert the rating for negative effect
                    $inverted_rating = ($sub_totals->total_votes * 6) - $sub_totals->total_rating;
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
        
        // Aggregate vote counts from sub-ratings with date filter
        $breakdown->subs->member_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE rating_id IN ({$sub_ids_placeholder}) AND user_id > 0 {$date_condition}"
        );
        
        $breakdown->subs->guest_votes = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->votes_table} 
             WHERE rating_id IN ({$sub_ids_placeholder}) AND user_id = 0 {$date_condition}"
        );
        
        $breakdown->subs->unique_voters = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id IN ({$sub_ids_placeholder}) {$date_condition}"
        );
        
        $breakdown->subs->first_vote = $this->wpdb->get_var(
            "SELECT MIN(date_created) FROM {$this->votes_table} WHERE rating_id IN ({$sub_ids_placeholder}) {$date_condition}"
        );
        
        $breakdown->subs->last_vote = $this->wpdb->get_var(
            "SELECT MAX(date_created) FROM {$this->votes_table} WHERE rating_id IN ({$sub_ids_placeholder}) {$date_condition}"
        );
        
        // Distribution from sub-ratings (with effect_type conversion) and date filter
        $subs_distribution_results = $this->wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN r.effect_type = 'negative' THEN 6 - v.rating_value 
                    ELSE v.rating_value 
                END as effective_value, 
                COUNT(*) as count 
             FROM {$this->votes_table} v
             JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE v.rating_id IN ({$sub_ids_placeholder}) {$date_condition_v}
             GROUP BY effective_value 
             ORDER BY effective_value"
        );
        
        $breakdown->subs->distribution = array_fill(1, 5, 0);
        foreach ($subs_distribution_results as $row) {
            $breakdown->subs->distribution[intval($row->effective_value)] = intval($row->count);
        }
        
        // Votes over time from sub-ratings with date filter
        $breakdown->subs->votes_over_time = $this->get_votes_over_time_for_ids($sub_rating_ids, $date_range);
        
        // === TOTAL (Combined) ===
        $breakdown->total = new stdClass();
        
        $breakdown->total->total_votes = $breakdown->direct->total_votes + $breakdown->subs->total_votes;
        $breakdown->total->total_rating = $breakdown->direct->total_rating + $subs_total_rating;
        $breakdown->total->average = $breakdown->total->total_votes > 0 
            ? round($breakdown->total->total_rating / $breakdown->total->total_votes, 1) 
            : 0;
        
        $breakdown->total->member_votes = $breakdown->direct->member_votes + $breakdown->subs->member_votes;
        $breakdown->total->guest_votes = $breakdown->direct->guest_votes + $breakdown->subs->guest_votes;
        
        // Unique voters across all (parent + subs) - need to recalculate for truly unique
        $all_rating_ids = array_merge(array($rating_id), $sub_rating_ids);
        $all_ids_placeholder = implode(',', array_map('intval', $all_rating_ids));
        
        $breakdown->total->unique_voters = (int) $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT CASE WHEN user_id > 0 THEN user_id ELSE user_ip END) 
             FROM {$this->votes_table} WHERE rating_id IN ({$all_ids_placeholder}) {$date_condition}"
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
        
        // Combined distribution
        $breakdown->total->distribution = array_fill(1, 5, 0);
        for ($i = 1; $i <= 5; $i++) {
            $breakdown->total->distribution[$i] = $breakdown->direct->distribution[$i] + $breakdown->subs->distribution[$i];
        }
        
        // Combined votes over time
        $breakdown->total->votes_over_time = $this->get_votes_over_time_for_ids($all_rating_ids, $date_range);
        
        return $breakdown;
    }
    
    /**
     * Get votes over time for multiple rating IDs
     *
     * @param array $rating_ids Array of rating IDs
     * @param mixed $date_range Date range (days, 'all', or array with 'start'/'end')
     * @return array Array of vote date/count objects
     */
    private function get_votes_over_time_for_ids($rating_ids, $date_range = 30) {
        if (empty($rating_ids)) {
            return array();
        }
        
        $ids_placeholder = implode(',', array_map('intval', $rating_ids));
        $date_condition = $this->build_date_condition($date_range, 'date_created');
        
        return $this->wpdb->get_results(
            "SELECT DATE(date_created) as vote_date, COUNT(*) as vote_count 
             FROM {$this->votes_table} 
             WHERE rating_id IN ({$ids_placeholder}) {$date_condition}
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
    public function get_rating_votes_paginated($rating_id, $page = 1, $per_page = 20, $date_range = 'all', $view = 'direct') {
        $offset = ($page - 1) * $per_page;
        
        $result = new stdClass();
        
        // Build date condition
        $date_condition = $this->build_date_condition($date_range, 'v.date_created');
        
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
            "SELECT v.*, u.display_name, u.user_email, r.name as rating_name
             FROM {$this->votes_table} v
             LEFT JOIN {$this->wpdb->users} u ON v.user_id = u.ID
             LEFT JOIN {$this->ratings_table} r ON v.rating_id = r.id
             WHERE v.rating_id IN ({$rating_ids_placeholder}) {$date_condition}
             ORDER BY v.date_created DESC
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
    public function format_time_ago($mysql_date) {
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
    public function format_date($mysql_date, $include_time = true) {
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
    public function get_chart_data($date_range = 30, $rating_id = null) {
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
    public function has_sub_ratings($rating_id) {
        $count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->ratings_table} WHERE parent_id = %d",
            $rating_id
        ));
        return $count > 0;
    }
}
